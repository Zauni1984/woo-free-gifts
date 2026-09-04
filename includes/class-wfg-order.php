<?php
/**
 * Order integration: gift meta on line items, statistics, once-per-customer tracking.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Order
 */
final class WFG_Order {

	const OPTION_STATS      = 'wfg_stats';
	const USER_META_CLAIMED = '_wfg_claimed_rules';
	const ITEM_META_RULE    = '_wfg_gift_rule';
	const ITEM_META_LABEL   = 'wfg_gift';
	const ORDER_META_DONE   = '_wfg_recorded';

	/**
	 * Settings.
	 *
	 * @var WFG_Settings
	 */
	private $settings;

	/**
	 * Rules.
	 *
	 * @var WFG_Rules
	 */
	private $rules;

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings $settings Settings.
	 * @param WFG_Rules    $rules    Rules.
	 */
	public function __construct( WFG_Settings $settings, WFG_Rules $rules ) {
		$this->settings = $settings;
		$this->rules    = $rules;

		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_line_item_meta' ), 10, 3 );
		add_filter( 'woocommerce_order_item_display_meta_key', array( $this, 'display_meta_key' ), 10, 2 );
		add_filter( 'woocommerce_hidden_order_itemmeta', array( $this, 'hidden_item_meta' ) );

		// Classic checkout and block checkout both end up here.
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'record_classic' ), 10, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'record' ), 10, 1 );
	}

	/**
	 * Copy gift information to the order line item.
	 *
	 * @param WC_Order_Item_Product $item          Item.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart item.
	 */
	public function add_line_item_meta( $item, $cart_item_key, $values ) {
		unset( $cart_item_key );
		if ( ! WFG_Engine::is_gift_item( $values ) || ! $item instanceof WC_Order_Item ) {
			return;
		}
		$rule_id = (int) $values['wfg_gift']['rule_id'];
		$rule    = 0 === $rule_id ? WFG_Cart::wheel_rule() : $this->rules->get( $rule_id );

		$item->add_meta_data( self::ITEM_META_RULE, $rule_id, true );
		$item->add_meta_data( self::ITEM_META_LABEL, $rule && '' !== $rule['title'] ? $rule['title'] : __( 'Free gift', 'woo-free-gifts' ), true );
	}

	/**
	 * Human label for the visible meta key.
	 *
	 * @param string             $display_key Key.
	 * @param WC_Meta_Data|mixed $meta        Meta.
	 * @return string
	 */
	public function display_meta_key( $display_key, $meta ) {
		if ( is_object( $meta ) && isset( $meta->key ) && self::ITEM_META_LABEL === $meta->key ) {
			$badge = $this->settings->get( 'gift_badge' );
			return '' !== $badge ? $badge : __( 'Free gift', 'woo-free-gifts' );
		}
		return $display_key;
	}

	/**
	 * Hide the technical meta key in the admin order screen.
	 *
	 * @param string[] $keys Hidden keys.
	 * @return string[]
	 */
	public function hidden_item_meta( $keys ) {
		$keys[] = self::ITEM_META_RULE;
		return $keys;
	}

	/**
	 * Classic checkout callback signature.
	 *
	 * @param int      $order_id Order id.
	 * @param array    $posted   Posted data.
	 * @param WC_Order $order    Order.
	 */
	public function record_classic( $order_id, $posted, $order ) {
		unset( $posted );
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		$this->record( $order );
	}

	/**
	 * Record gift statistics and per-customer claims (idempotent).
	 *
	 * @param WC_Order $order Order.
	 */
	public function record( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		if ( 'yes' === $order->get_meta( self::ORDER_META_DONE ) ) {
			return;
		}

		$rule_ids = array();
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( '' === (string) $item->get_meta( self::ITEM_META_RULE ) ) {
				continue;
			}
			$rule_id = (int) $item->get_meta( self::ITEM_META_RULE );
			if ( $rule_id > 0 ) {
				$rule_ids[] = $rule_id;
			} else {
				// Wheel prize: consumed with this order.
				WFG_Wheel::forget_gift( $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id() );
			}
		}

		try {
			$order->update_meta_data( self::ORDER_META_DONE, 'yes' );
			if ( ! empty( $rule_ids ) ) {
				$order->update_meta_data( '_wfg_gift_rules', array_values( array_unique( $rule_ids ) ) );
			}
			$order->save();
		} catch ( Throwable $e ) {
			WFG_Logger::error( 'Could not store gift meta on order: ' . $e->getMessage() );
		}

		if ( empty( $rule_ids ) ) {
			return;
		}

		// Statistics.
		$stats = get_option( self::OPTION_STATS, array() );
		$stats = is_array( $stats ) ? $stats : array();
		foreach ( $rule_ids as $rule_id ) {
			if ( ! isset( $stats[ $rule_id ] ) || ! is_array( $stats[ $rule_id ] ) ) {
				$stats[ $rule_id ] = array(
					'count' => 0,
					'last'  => 0,
				);
			}
			++$stats[ $rule_id ]['count'];
			$stats[ $rule_id ]['last'] = time();
		}
		update_option( self::OPTION_STATS, $stats, false );

		// Once-per-customer bookkeeping (registered customers only).
		$user_id = $order->get_customer_id();
		if ( $user_id > 0 ) {
			$claimed = get_user_meta( $user_id, self::USER_META_CLAIMED, true );
			$claimed = is_array( $claimed ) ? array_map( 'intval', $claimed ) : array();
			$claimed = array_values( array_unique( array_merge( $claimed, $rule_ids ) ) );
			update_user_meta( $user_id, self::USER_META_CLAIMED, $claimed );
		}

		/**
		 * Fires after gifts in an order were recorded.
		 *
		 * @param WC_Order $order    Order.
		 * @param int[]    $rule_ids Rule ids.
		 */
		do_action( 'wfg_order_recorded', $order, $rule_ids );
	}

	/**
	 * Statistics keyed by rule id.
	 *
	 * @return array
	 */
	public static function stats() {
		$stats = get_option( self::OPTION_STATS, array() );
		return is_array( $stats ) ? $stats : array();
	}

	/**
	 * Reset statistics.
	 */
	public static function reset_stats() {
		update_option( self::OPTION_STATS, array(), false );
	}
}
