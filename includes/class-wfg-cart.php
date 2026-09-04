<?php
/**
 * Cart integration: keeps gift items in sync with the rules, prices them at zero
 * and protects them against manipulation.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Cart
 */
final class WFG_Cart {

	const SESSION_DISMISSED = 'wfg_dismissed';
	const SESSION_CHOICE    = 'wfg_choice';

	/**
	 * True while the engine adds a gift (lets the add-to-cart guard pass).
	 *
	 * @var bool
	 */
	private static $adding_gift = false;

	/**
	 * Re-entrancy guard for sync().
	 *
	 * @var bool
	 */
	private static $syncing = false;

	/**
	 * Settings.
	 *
	 * @var WFG_Settings
	 */
	private $settings;

	/**
	 * Engine.
	 *
	 * @var WFG_Engine
	 */
	private $engine;

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings $settings Settings.
	 * @param WFG_Engine   $engine   Engine.
	 */
	public function __construct( WFG_Settings $settings, WFG_Engine $engine ) {
		$this->settings = $settings;
		$this->engine   = $engine;

		// Core sync.
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_gift_prices' ), 1000 );
		add_action( 'woocommerce_after_calculate_totals', array( $this, 'sync' ), 999 );

		// Customer interaction.
		add_action( 'woocommerce_remove_cart_item', array( $this, 'on_remove' ), 10, 2 );
		add_action( 'woocommerce_cart_item_restored', array( $this, 'on_restore' ), 10, 2 );
		add_action( 'woocommerce_cart_emptied', array( $this, 'on_emptied' ) );

		// Display (classic cart / checkout / mini cart).
		add_filter( 'woocommerce_cart_item_name', array( $this, 'item_name' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'item_price' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'item_subtotal' ), 20, 2 );
		add_filter( 'woocommerce_cart_item_quantity', array( $this, 'item_quantity' ), 20, 3 );
		add_filter( 'woocommerce_cart_item_remove_link', array( $this, 'item_remove_link' ), 20, 2 );
		add_filter( 'woocommerce_widget_cart_item_quantity', array( $this, 'widget_item_quantity' ), 20, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'item_data' ), 20, 2 );

		// Blocks / Store API.
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'store_api_quantity_editable' ), 20, 3 );

		// Coupons never apply to gifts.
		add_filter( 'woocommerce_coupon_is_valid_for_product', array( $this, 'coupon_not_for_gifts' ), 20, 4 );
	}

	/**
	 * Whether the engine is currently adding a gift.
	 *
	 * @return bool
	 */
	public static function is_adding_gift() {
		return self::$adding_gift;
	}

	/**
	 * Engine accessor.
	 *
	 * @return WFG_Engine
	 */
	public function engine() {
		return $this->engine;
	}

	// --- Pricing ---

	/**
	 * Price every gift line at zero.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function apply_gift_prices( $cart ) {
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( WFG_Engine::is_gift_item( $item ) && isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
				$item['data']->set_price( 0 );
			}
		}
	}

	// --- Sync ---

	/**
	 * Reconcile gift items with the rules. Runs after every totals calculation.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public function sync( $cart ) {
		if ( self::$syncing || ! $cart instanceof WC_Cart ) {
			return;
		}
		if ( ( is_admin() && ! wp_doing_ajax() ) || wp_doing_cron() ) {
			return;
		}
		if ( ! WC()->session ) {
			return;
		}

		self::$syncing = true;
		try {
			$changed = $this->reconcile( $cart );
			if ( $changed ) {
				$this->engine->flush();
				$cart->calculate_totals();
			}
		} catch ( Throwable $e ) {
			WFG_Logger::error(
				'Gift sync failed: ' . $e->getMessage(),
				array(
					'file' => $e->getFile(),
					'line' => $e->getLine(),
				)
			);
		} finally {
			self::$syncing = false;
		}
	}

	/**
	 * Add/remove/adjust gift lines. Returns true when the cart was modified.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return bool
	 */
	private function reconcile( WC_Cart $cart ) {
		$existing = array(); // Keyed by gift id, value is the cart item key.
		foreach ( $cart->get_cart() as $key => $item ) {
			if ( WFG_Engine::is_gift_item( $item ) ) {
				$existing[ $this->gift_id( $item['wfg_gift']['rule_id'], $item['wfg_gift']['product_id'] ) ] = $key;
			}
		}

		$desired = array(); // Keyed by gift id, each entry holds rule, product and qty.
		if ( $this->settings->enabled() && ! $this->cart_has_only_gifts( $cart ) ) {
			$winners   = $this->engine->winning_rules( $cart );
			$dismissed = $this->session_get( self::SESSION_DISMISSED );
			$choices   = $this->session_get( self::SESSION_CHOICE );

			foreach ( $winners as $rule_id => $rule ) {
				$gifts = $this->engine->available_gifts( $rule );
				if ( 'choice' === $rule['gift_mode'] && count( $gifts ) > 1 ) {
					$chosen = isset( $choices[ $rule_id ] ) ? (int) $choices[ $rule_id ] : 0;
					$gifts  = array_values(
						array_filter(
							$gifts,
							static function ( $g ) use ( $chosen ) {
								return $g['product']->get_id() === $chosen;
							}
						)
					);
				}
				foreach ( $gifts as $gift ) {
					$pid = $gift['product']->get_id();
					if ( ! empty( $dismissed[ $rule_id ][ $pid ] ) ) {
						continue;
					}
					$desired[ $this->gift_id( $rule_id, $pid ) ] = array(
						'rule'    => $rule,
						'product' => $gift['product'],
						'qty'     => $gift['qty'],
					);
				}
			}

			// A rule that no longer applies forgets its dismissals, so a later re-qualification re-adds the gift.
			$reset = false;
			foreach ( array_keys( $dismissed ) as $rule_id ) {
				if ( ! isset( $winners[ $rule_id ] ) ) {
					unset( $dismissed[ $rule_id ] );
					$reset = true;
				}
			}
			if ( $reset ) {
				$this->session_set( self::SESSION_DISMISSED, $dismissed );
			}
		}

		$changed = false;

		// Avoid a totals recalculation per add/remove; we recalculate once at the end.
		$had_add_hook    = remove_action( 'woocommerce_add_to_cart', array( $cart, 'calculate_totals' ), 20 );
		$had_remove_hook = remove_action( 'woocommerce_cart_item_removed', array( $cart, 'calculate_totals' ), 20 );

		try {
			// Remove obsolete gifts.
			foreach ( $existing as $gid => $key ) {
				if ( ! isset( $desired[ $gid ] ) ) {
					$cart->remove_cart_item( $key );
					$changed = true;
					WFG_Logger::debug( 'Removed gift ' . $gid );
				}
			}

			// Adjust or add.
			foreach ( $desired as $gid => $d ) {
				if ( isset( $existing[ $gid ] ) ) {
					$key  = $existing[ $gid ];
					$item = $cart->get_cart_item( $key );
					if ( $item && (int) $item['quantity'] !== (int) $d['qty'] ) {
						$cart->set_quantity( $key, (int) $d['qty'], false );
						$changed = true;
					}
					continue;
				}
				if ( $this->add_gift( $cart, $d['rule'], $d['product'], (int) $d['qty'] ) ) {
					$changed = true;
				}
			}
		} finally {
			if ( $had_add_hook ) {
				add_action( 'woocommerce_add_to_cart', array( $cart, 'calculate_totals' ), 20, 0 );
			}
			if ( $had_remove_hook ) {
				add_action( 'woocommerce_cart_item_removed', array( $cart, 'calculate_totals' ), 20, 0 );
			}
		}

		return $changed;
	}

	/**
	 * Add one gift line to the cart. Never surfaces WooCommerce error notices to the customer.
	 *
	 * @param WC_Cart    $cart    Cart.
	 * @param array      $rule    Rule.
	 * @param WC_Product $product Gift product.
	 * @param int        $qty     Quantity.
	 * @return bool
	 */
	private function add_gift( WC_Cart $cart, array $rule, WC_Product $product, $qty ) {
		$product_id   = $product->get_id();
		$variation_id = 0;
		$attributes   = array();

		if ( $product->is_type( 'variation' ) ) {
			$variation_id = $product->get_id();
			$product_id   = $product->get_parent_id();
			$attributes   = (array) $product->get_variation_attributes();
		}

		// Stock guard: the gift must not push the product over its stock.
		if ( $product->managing_stock() ) {
			$in_cart   = $cart->get_cart_item_quantities();
			$stock_key = $product->get_stock_managed_by_id();
			$already   = isset( $in_cart[ $stock_key ] ) ? (int) $in_cart[ $stock_key ] : 0;
			if ( ! $product->has_enough_stock( $already + $qty ) ) {
				WFG_Logger::debug( 'Gift skipped (stock): product ' . $product->get_id() );
				return false;
			}
		}

		// Sold-individually products cannot be duplicated as gift.
		if ( $product->is_sold_individually() ) {
			foreach ( $cart->get_cart() as $item ) {
				if ( (int) $item['product_id'] === (int) $product_id && (int) $item['variation_id'] === (int) $variation_id ) {
					WFG_Logger::debug( 'Gift skipped (sold individually): product ' . $product->get_id() );
					return false;
				}
			}
		}

		$cart_item_data = array(
			'wfg_gift' => array(
				'rule_id'    => (int) $rule['id'],
				'product_id' => (int) $product->get_id(),
				'v'          => 1,
			),
		);

		$notices = ( function_exists( 'wc_get_notices' ) && WC()->session ) ? wc_get_notices() : null;

		self::$adding_gift = true;
		try {
			$key = $cart->add_to_cart( $product_id, $qty, $variation_id, $attributes, $cart_item_data );
		} catch ( Throwable $e ) {
			$key = false;
			WFG_Logger::error( 'add_to_cart threw: ' . $e->getMessage() );
		} finally {
			self::$adding_gift = false;
		}

		if ( ! $key ) {
			// Roll back any error notice WooCommerce produced while trying to add the gift.
			if ( null !== $notices ) {
				wc_set_notices( $notices );
			}
			WFG_Logger::debug( 'Gift could not be added: product ' . $product->get_id() );
			return false;
		}

		WFG_Logger::debug( 'Added gift ' . $product->get_id() . ' for rule ' . $rule['id'] );

		if ( $this->should_notify() ) {
			$msg = '' !== $rule['msg_unlocked'] ? $rule['msg_unlocked'] : $this->settings->get( 'msg_unlocked' );
			$msg = WFG_Helpers::placeholders(
				$msg,
				array(
					'gift'      => $product->get_name(),
					'threshold' => wp_strip_all_tags( wc_price( $rule['min_total'] ) ),
					'remaining' => '',
				)
			);
			if ( '' !== trim( $msg ) ) {
				wc_add_notice( esc_html( $msg ), 'success' );
			}
		}

		/**
		 * Fires after a gift was added to the cart.
		 *
		 * @param string     $key     Cart item key.
		 * @param array      $rule    Rule.
		 * @param WC_Product $product Product.
		 */
		do_action( 'wfg_gift_added', $key, $rule, $product );

		return true;
	}

	/**
	 * Whether a success notice makes sense in the current request.
	 *
	 * @return bool
	 */
	private function should_notify() {
		if ( ! function_exists( 'wc_add_notice' ) || ! WC()->session ) {
			return false;
		}
		if ( function_exists( 'WC' ) && method_exists( WC(), 'is_rest_api_request' ) && WC()->is_rest_api_request() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		return (bool) apply_filters( 'wfg_notify_on_unlock', true );
	}

	/**
	 * True when the cart contains nothing but gifts (the customer removed all paid items).
	 *
	 * @param WC_Cart $cart Cart.
	 * @return bool
	 */
	private function cart_has_only_gifts( WC_Cart $cart ) {
		foreach ( $cart->get_cart() as $item ) {
			if ( ! WFG_Engine::is_gift_item( $item ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Composite id for a gift line.
	 *
	 * @param int $rule_id    Rule id.
	 * @param int $product_id Product id.
	 * @return string
	 */
	private function gift_id( $rule_id, $product_id ) {
		return (int) $rule_id . ':' . (int) $product_id;
	}

	// --- Customer choice / dismissal ---

	/**
	 * Customer picked a gift for a "choice" rule.
	 *
	 * @param int $rule_id    Rule id.
	 * @param int $product_id Product id.
	 * @return true|WP_Error
	 */
	public function choose( $rule_id, $product_id ) {
		$rule_id    = absint( $rule_id );
		$product_id = absint( $product_id );

		if ( ! WC()->cart || ! WC()->session ) {
			return new WP_Error( 'wfg_no_cart', __( 'Your cart is not available.', 'woo-free-gifts' ) );
		}

		$winners = $this->engine->winning_rules( WC()->cart );
		if ( ! isset( $winners[ $rule_id ] ) ) {
			return new WP_Error( 'wfg_not_qualified', __( 'Your cart does not qualify for this gift (yet).', 'woo-free-gifts' ) );
		}

		$allowed = false;
		foreach ( $this->engine->available_gifts( $winners[ $rule_id ] ) as $gift ) {
			if ( $gift['product']->get_id() === $product_id ) {
				$allowed = true;
				break;
			}
		}
		if ( ! $allowed ) {
			return new WP_Error( 'wfg_invalid_gift', __( 'This gift is not available.', 'woo-free-gifts' ) );
		}

		$choices             = $this->session_get( self::SESSION_CHOICE );
		$choices[ $rule_id ] = $product_id;
		$this->session_set( self::SESSION_CHOICE, $choices );

		$dismissed = $this->session_get( self::SESSION_DISMISSED );
		unset( $dismissed[ $rule_id ] );
		$this->session_set( self::SESSION_DISMISSED, $dismissed );

		$this->engine->flush();
		WC()->cart->calculate_totals();

		return true;
	}

	/**
	 * Current choice for a rule.
	 *
	 * @param int $rule_id Rule id.
	 * @return int Product id or 0.
	 */
	public function chosen_gift( $rule_id ) {
		$choices = $this->session_get( self::SESSION_CHOICE );
		return isset( $choices[ (int) $rule_id ] ) ? (int) $choices[ (int) $rule_id ] : 0;
	}

	/**
	 * Customer removed a cart line – remember when it was a gift.
	 *
	 * @param string  $cart_item_key Key.
	 * @param WC_Cart $cart          Cart.
	 */
	public function on_remove( $cart_item_key, $cart ) {
		if ( self::$syncing || ! $cart instanceof WC_Cart ) {
			return;
		}
		$item = $cart->get_cart_item( $cart_item_key );
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return;
		}
		if ( ! $this->settings->is( 'allow_remove' ) ) {
			// Removal is not allowed – the next sync re-adds the gift.
			return;
		}
		$rule_id = (int) $item['wfg_gift']['rule_id'];
		$pid     = (int) $item['wfg_gift']['product_id'];

		$dismissed                     = $this->session_get( self::SESSION_DISMISSED );
		$dismissed[ $rule_id ][ $pid ] = true;
		$this->session_set( self::SESSION_DISMISSED, $dismissed );

		$choices = $this->session_get( self::SESSION_CHOICE );
		if ( isset( $choices[ $rule_id ] ) && (int) $choices[ $rule_id ] === $pid ) {
			unset( $choices[ $rule_id ] );
			$this->session_set( self::SESSION_CHOICE, $choices );
		}
	}

	/**
	 * Customer clicked "Undo" after removing a gift.
	 *
	 * @param string  $cart_item_key Key.
	 * @param WC_Cart $cart          Cart.
	 */
	public function on_restore( $cart_item_key, $cart ) {
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}
		$item = $cart->get_cart_item( $cart_item_key );
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return;
		}
		$rule_id   = (int) $item['wfg_gift']['rule_id'];
		$pid       = (int) $item['wfg_gift']['product_id'];
		$dismissed = $this->session_get( self::SESSION_DISMISSED );
		unset( $dismissed[ $rule_id ][ $pid ] );
		$this->session_set( self::SESSION_DISMISSED, $dismissed );
	}

	/**
	 * Cart emptied – forget everything.
	 */
	public function on_emptied() {
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_DISMISSED, null );
			WC()->session->set( self::SESSION_CHOICE, null );
		}
	}

	/**
	 * Read an array from the session.
	 *
	 * @param string $key Session key.
	 * @return array
	 */
	private function session_get( $key ) {
		if ( ! WC()->session ) {
			return array();
		}
		$value = WC()->session->get( $key );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Write an array to the session.
	 *
	 * @param string $key   Session key.
	 * @param array  $value Value.
	 */
	private function session_set( $key, array $value ) {
		if ( WC()->session ) {
			WC()->session->set( $key, empty( $value ) ? null : $value );
		}
	}

	// --- Display ---

	/**
	 * Badge after the product name.
	 *
	 * @param string $name Name HTML.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function item_name( $name, $item ) {
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return $name;
		}
		$badge = $this->settings->get( 'gift_badge' );
		if ( '' === $badge ) {
			return $name;
		}
		return $name . ' <span class="wfg-badge">' . esc_html( $badge ) . '</span>';
	}

	/**
	 * Price column.
	 *
	 * @param string $html Price HTML.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function item_price( $html, $item ) {
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return $html;
		}
		return $this->free_price_html( $item, 1 );
	}

	/**
	 * Subtotal column.
	 *
	 * @param string $html Subtotal HTML.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function item_subtotal( $html, $item ) {
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return $html;
		}
		return $this->free_price_html( $item, isset( $item['quantity'] ) ? (int) $item['quantity'] : 1 );
	}

	/**
	 * "Free" with the regular price struck through (if the gift has one).
	 *
	 * @param array $item Cart item.
	 * @param int   $qty  Quantity multiplier.
	 * @return string
	 */
	private function free_price_html( array $item, $qty ) {
		$label   = esc_html( $this->settings->get( 'gift_price_label' ) );
		$id      = ! empty( $item['variation_id'] ) ? (int) $item['variation_id'] : (int) $item['product_id'];
		$product = wc_get_product( $id );
		$regular = '';
		if ( $product instanceof WC_Product && (float) $product->get_price() > 0 ) {
			$regular = '<del aria-hidden="true">' . wc_price( wc_get_price_to_display( $product, array( 'qty' => max( 1, $qty ) ) ) ) . '</del> ';
		}
		return '<span class="wfg-free">' . $regular . '<ins>' . $label . '</ins></span>';
	}

	/**
	 * Quantity column – gifts have a fixed quantity.
	 *
	 * @param string $html          Quantity HTML.
	 * @param string $cart_item_key Key.
	 * @param array  $item          Cart item.
	 * @return string
	 */
	public function item_quantity( $html, $cart_item_key, $item ) {
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return $html;
		}
		$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
		return '<span class="wfg-qty">' . esc_html( $qty ) . '</span>';
	}

	/**
	 * Remove link – hidden when the customer must not remove gifts.
	 *
	 * @param string $html          Link HTML.
	 * @param string $cart_item_key Key.
	 * @return string
	 */
	public function item_remove_link( $html, $cart_item_key ) {
		if ( $this->settings->is( 'allow_remove' ) || ! WC()->cart ) {
			return $html;
		}
		$item = WC()->cart->get_cart_item( $cart_item_key );
		return WFG_Engine::is_gift_item( $item ) ? '' : $html;
	}

	/**
	 * Mini cart quantity/price line.
	 *
	 * @param string $html Quantity HTML.
	 * @param array  $item Cart item.
	 * @return string
	 */
	public function widget_item_quantity( $html, $item ) {
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return $html;
		}
		$qty = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
		return '<span class="quantity">' . esc_html( $qty ) . ' &times; <span class="wfg-free"><ins>' . esc_html( $this->settings->get( 'gift_price_label' ) ) . '</ins></span></span>';
	}

	/**
	 * Item meta line – also used by the Store API so block carts show the badge.
	 *
	 * @param array $data Item data.
	 * @param array $item Cart item.
	 * @return array
	 */
	public function item_data( $data, $item ) {
		if ( ! WFG_Engine::is_gift_item( $item ) ) {
			return $data;
		}
		$rule  = $this->engine->rules()->get( $item['wfg_gift']['rule_id'] );
		$title = $rule ? $rule['title'] : '';
		$badge = $this->settings->get( 'gift_badge' );
		if ( '' === $badge ) {
			$badge = __( 'Free gift', 'woo-free-gifts' );
		}
		$data[] = array(
			'key'     => $badge,
			'value'   => '' !== $title ? $title : '🎁',
			'display' => '',
		);
		return $data;
	}

	/**
	 * Block cart: quantity of a gift is not editable.
	 *
	 * @param bool       $editable  Editable.
	 * @param WC_Product $product   Product.
	 * @param array      $cart_item Cart item.
	 * @return bool
	 */
	public function store_api_quantity_editable( $editable, $product, $cart_item ) {
		unset( $product );
		return WFG_Engine::is_gift_item( $cart_item ) ? false : $editable;
	}

	/**
	 * Coupons never discount gift lines.
	 *
	 * @param bool       $valid   Valid.
	 * @param WC_Product $product Product.
	 * @param WC_Coupon  $coupon  Coupon.
	 * @param array      $values  Cart item.
	 * @return bool
	 */
	public function coupon_not_for_gifts( $valid, $product, $coupon, $values ) {
		unset( $product, $coupon );
		return WFG_Engine::is_gift_item( $values ) ? false : $valid;
	}
}
