<?php
/**
 * Rule engine: decides which rules a cart qualifies for and which gifts are available.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Engine
 */
final class WFG_Engine {

	/**
	 * Cart item keys used by bundle plugins to mark child items.
	 *
	 * @var string[]
	 */
	const BUNDLE_CHILD_KEYS = array( 'bundled_by', 'woosb_parent_id', 'yith_wcpb_bundled_by', 'composite_parent' );

	/**
	 * Cart item keys used by bundle plugins to mark container items.
	 *
	 * @var string[]
	 */
	const BUNDLE_PARENT_KEYS = array( 'bundled_items', 'woosb_ids', 'yith_wcpb_bundled_items', 'composite_children' );

	/**
	 * Product types that count as bundles.
	 *
	 * @var string[]
	 */
	const BUNDLE_TYPES = array( 'bundle', 'woosb', 'yith_bundle', 'composite', 'grouped' );

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
	 * Per-request evaluation cache keyed by cart signature.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings $settings Settings.
	 * @param WFG_Rules    $rules    Rules.
	 */
	public function __construct( WFG_Settings $settings, WFG_Rules $rules ) {
		$this->settings = $settings;
		$this->rules    = $rules;
	}

	/**
	 * Rules repository accessor.
	 *
	 * @return WFG_Rules
	 */
	public function rules() {
		return $this->rules;
	}

	/**
	 * Clear the evaluation cache (after cart changes).
	 */
	public function flush() {
		$this->cache = array();
	}

	// --- Cart facts ---

	/**
	 * Is the cart item a gift added by this plugin?
	 *
	 * @param array $item Cart item.
	 * @return bool
	 */
	public static function is_gift_item( $item ) {
		return is_array( $item ) && ! empty( $item['wfg_gift'] ) && is_array( $item['wfg_gift'] );
	}

	/**
	 * Is the cart item a bundle child?
	 *
	 * @param array $item Cart item.
	 * @return bool
	 */
	public static function is_bundle_child( $item ) {
		foreach ( self::BUNDLE_CHILD_KEYS as $key ) {
			if ( ! empty( $item[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Is the cart item a bundle container (or a bundle-type product)?
	 *
	 * @param array $item Cart item.
	 * @return bool
	 */
	public static function is_bundle_parent( $item ) {
		foreach ( self::BUNDLE_PARENT_KEYS as $key ) {
			if ( ! empty( $item[ $key ] ) ) {
				return true;
			}
		}
		if ( isset( $item['data'] ) && $item['data'] instanceof WC_Product ) {
			return in_array( $item['data']->get_type(), self::BUNDLE_TYPES, true );
		}
		return false;
	}

	/**
	 * Cart value used for threshold comparisons (gifts excluded).
	 *
	 * @param WC_Cart $cart Cart.
	 * @return float
	 */
	public function basis_total( WC_Cart $cart ) {
		$incl_tax = 'subtotal_incl_tax' === $this->settings->get( 'threshold_basis' );
		$after    = $this->settings->is( 'after_discounts' );
		$total    = 0.0;

		foreach ( $cart->get_cart() as $item ) {
			if ( self::is_gift_item( $item ) ) {
				continue;
			}
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			if ( $after && isset( $item['line_total'] ) ) {
				$line = (float) $item['line_total'] + ( $incl_tax ? (float) $item['line_tax'] : 0 );
			} elseif ( isset( $item['line_subtotal'] ) ) {
				$line = (float) $item['line_subtotal'] + ( $incl_tax ? (float) $item['line_subtotal_tax'] : 0 );
			} else {
				// Totals not calculated yet – fall back to product price.
				$qty  = isset( $item['quantity'] ) ? (float) $item['quantity'] : 1;
				$line = $incl_tax ? wc_get_price_including_tax( $product, array( 'qty' => $qty ) ) : wc_get_price_excluding_tax( $product, array( 'qty' => $qty ) );
			}
			$total += (float) $line;
		}

		/**
		 * Filter the cart value used for gift thresholds.
		 *
		 * @param float   $total Cart value.
		 * @param WC_Cart $cart  Cart.
		 */
		return (float) apply_filters( 'wfg_basis_total', round( $total, wc_get_price_decimals() ), $cart );
	}

	/**
	 * Quantity map of non-gift products in the cart: product_id => qty, variation_id => qty, parent => qty.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array{qty: array<int,int>, items: int, has_bundle: bool, categories: int[]}
	 */
	public function cart_facts( WC_Cart $cart ) {
		$count_bundled = $this->settings->is( 'count_bundled_items' );
		$qty_map       = array();
		$items         = 0;
		$has_bundle    = false;
		$categories    = array();

		foreach ( $cart->get_cart() as $item ) {
			if ( self::is_gift_item( $item ) ) {
				continue;
			}
			$product = isset( $item['data'] ) ? $item['data'] : null;
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( self::is_bundle_parent( $item ) ) {
				$has_bundle = true;
			}
			if ( ! $count_bundled && self::is_bundle_child( $item ) ) {
				continue;
			}

			$qty    = isset( $item['quantity'] ) ? (int) $item['quantity'] : 0;
			$items += $qty;

			$ids = array_unique(
				array_filter(
					array(
						(int) $item['product_id'],
						isset( $item['variation_id'] ) ? (int) $item['variation_id'] : 0,
						(int) $product->get_id(),
						(int) $product->get_parent_id(),
					)
				)
			);
			foreach ( $ids as $id ) {
				$qty_map[ $id ] = ( isset( $qty_map[ $id ] ) ? $qty_map[ $id ] : 0 ) + $qty;
			}

			$cat_source = $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
			$terms      = get_the_terms( $cat_source, 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$categories[] = (int) $term->term_id;
					// Include ancestors so a parent category matches its children.
					foreach ( get_ancestors( $term->term_id, 'product_cat', 'taxonomy' ) as $anc ) {
						$categories[] = (int) $anc;
					}
				}
			}
		}

		return array(
			'qty'        => $qty_map,
			'items'      => $items,
			'has_bundle' => $has_bundle,
			'categories' => array_values( array_unique( $categories ) ),
		);
	}

	// --- Evaluation ---

	/**
	 * Evaluate all active rules against the cart.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array[] Keyed by rule id: rule, qualified, remaining, basis, user_ok.
	 */
	public function evaluate( WC_Cart $cart ) {
		$signature = $this->signature( $cart );
		if ( isset( $this->cache[ $signature ] ) ) {
			return $this->cache[ $signature ];
		}

		$basis   = $this->basis_total( $cart );
		$facts   = $this->cart_facts( $cart );
		$results = array();

		foreach ( $this->rules->active() as $rule ) {
			$user_ok   = $this->user_matches( $rule );
			$remaining = max( 0.0, (float) $rule['min_total'] - $basis );
			$qualified = $user_ok && $this->conditions_match( $rule, $basis, $facts );

			$results[ $rule['id'] ] = array(
				'rule'      => $rule,
				'qualified' => $qualified,
				'user_ok'   => $user_ok,
				'remaining' => $remaining,
				'basis'     => $basis,
			);
		}

		/**
		 * Filter evaluation results.
		 *
		 * @param array[] $results Results keyed by rule id.
		 * @param WC_Cart $cart    Cart.
		 */
		$results = apply_filters( 'wfg_evaluate_rules', $results, $cart );

		$this->cache[ $signature ] = $results;
		return $results;
	}

	/**
	 * Qualified rules after applying the stacking strategy.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array[] Rules keyed by id.
	 */
	public function winning_rules( WC_Cart $cart ) {
		$winners = array();
		foreach ( $this->evaluate( $cart ) as $id => $res ) {
			if ( $res['qualified'] ) {
				$winners[ $id ] = $res['rule'];
			}
		}

		if ( 'highest' === $this->settings->get( 'stacking' ) && count( $winners ) > 1 ) {
			// Rules are already ordered by priority desc, then threshold desc.
			$winners = array_slice( $winners, 0, 1, true );
		}

		/**
		 * Filter the rules whose gifts go into the cart.
		 *
		 * @param array[] $winners Rules keyed by id.
		 * @param WC_Cart $cart    Cart.
		 */
		return apply_filters( 'wfg_winning_rules', $winners, $cart );
	}

	/**
	 * Check cart conditions of a rule.
	 *
	 * @param array $rule  Rule.
	 * @param float $basis Cart basis total.
	 * @param array $facts Cart facts.
	 * @return bool
	 */
	private function conditions_match( array $rule, $basis, array $facts ) {
		if ( (float) $rule['min_total'] > 0 && $basis + 0.00001 < (float) $rule['min_total'] ) {
			return false;
		}
		if ( (float) $rule['max_total'] > 0 && $basis > (float) $rule['max_total'] + 0.00001 ) {
			return false;
		}
		if ( (int) $rule['min_items'] > 0 && $facts['items'] < (int) $rule['min_items'] ) {
			return false;
		}
		if ( ! empty( $rule['require_bundle'] ) && ! $facts['has_bundle'] ) {
			return false;
		}

		if ( ! empty( $rule['required_products'] ) ) {
			$need    = max( 1, (int) $rule['required_qty'] );
			$matched = 0;
			foreach ( $rule['required_products'] as $pid ) {
				$pid = (int) $pid;
				if ( isset( $facts['qty'][ $pid ] ) && $facts['qty'][ $pid ] >= $need ) {
					++$matched;
				}
			}
			if ( 'any' === $rule['required_match'] ) {
				if ( 0 === $matched ) {
					return false;
				}
			} elseif ( $matched < count( $rule['required_products'] ) ) {
				return false;
			}
		}

		if ( ! empty( $rule['required_categories'] ) ) {
			$hit = array_intersect( array_map( 'intval', $rule['required_categories'] ), $facts['categories'] );
			if ( empty( $hit ) ) {
				return false;
			}
		}

		/**
		 * Filter whether a rule's cart conditions match.
		 *
		 * @param bool  $match Match state.
		 * @param array $rule  Rule.
		 * @param float $basis Cart basis total.
		 * @param array $facts Cart facts.
		 */
		return (bool) apply_filters( 'wfg_rule_conditions_match', true, $rule, $basis, $facts );
	}

	/**
	 * Check user conditions (roles, login, once per customer).
	 *
	 * @param array $rule Rule.
	 * @return bool
	 */
	private function user_matches( array $rule ) {
		$user = wp_get_current_user();
		$in   = $user && $user->exists();

		if ( ! empty( $rule['logged_in_only'] ) && ! $in ) {
			return false;
		}
		if ( ! empty( $rule['user_roles'] ) ) {
			if ( ! $in ) {
				return false;
			}
			if ( empty( array_intersect( (array) $user->roles, $rule['user_roles'] ) ) ) {
				return false;
			}
		}
		if ( ! empty( $rule['once_per_customer'] ) && $in ) {
			$claimed = get_user_meta( $user->ID, WFG_Order::USER_META_CLAIMED, true );
			if ( is_array( $claimed ) && in_array( (int) $rule['id'], array_map( 'intval', $claimed ), true ) ) {
				return false;
			}
		}
		return true;
	}

	// --- Gifts ---

	/**
	 * Resolve a rule's gifts to purchasable product objects.
	 *
	 * @param array $rule Rule.
	 * @return array[] Each: product (WC_Product), qty, custom.
	 */
	public function available_gifts( array $rule ) {
		$out = array();
		foreach ( (array) $rule['gifts'] as $gift ) {
			$product = wc_get_product( (int) $gift['product_id'] );
			if ( ! $product instanceof WC_Product ) {
				continue;
			}
			if ( 'publish' !== $product->get_status() || ! $product->is_purchasable() ) {
				continue;
			}
			if ( ! $product->is_in_stock() ) {
				continue;
			}
			if ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) || $product->is_type( 'external' ) ) {
				// Variable parents cannot be added; the admin has to pick a variation.
				continue;
			}
			$qty = max( 1, (int) $gift['qty'] );
			if ( $product->managing_stock() && ! $product->backorders_allowed() && (int) $product->get_stock_quantity() < $qty ) {
				continue;
			}
			$out[] = array(
				'product' => $product,
				'qty'     => $qty,
				'custom'  => ! empty( $gift['custom'] ),
			);
		}

		/**
		 * Filter the available gifts for a rule.
		 *
		 * @param array[] $out  Gifts.
		 * @param array   $rule Rule.
		 */
		return apply_filters( 'wfg_available_gifts', $out, $rule );
	}

	/**
	 * Comma separated gift names for messaging.
	 *
	 * @param array $rule Rule.
	 * @return string
	 */
	public function gift_names( array $rule ) {
		$names = array();
		foreach ( $this->available_gifts( $rule ) as $gift ) {
			$names[] = $gift['product']->get_name();
		}
		if ( empty( $names ) ) {
			return '';
		}
		if ( 'choice' === $rule['gift_mode'] && count( $names ) > 1 ) {
			return implode( ' ' . __( 'or', 'woo-free-gifts' ) . ' ', $names );
		}
		return wc_format_list_of_items( $names );
	}

	// --- Progress data for the frontend ---

	/**
	 * Data for progress bars and gift choice boxes.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return array{next: array|null, unlocked: array[], choices: array[], basis: float}
	 */
	public function progress( WC_Cart $cart ) {
		$results  = $this->evaluate( $cart );
		$winners  = $this->winning_rules( $cart );
		$basis    = $this->basis_total( $cart );
		$next     = null;
		$unlocked = array();
		$choices  = array();

		foreach ( $results as $id => $res ) {
			$rule = $res['rule'];
			if ( ! $res['user_ok'] ) {
				continue;
			}
			if ( isset( $winners[ $id ] ) ) {
				$unlocked[] = $rule;
				if ( 'choice' === $rule['gift_mode'] ) {
					$gifts = $this->available_gifts( $rule );
					if ( count( $gifts ) > 0 ) {
						$choices[] = array(
							'rule'  => $rule,
							'gifts' => $gifts,
						);
					}
				}
				continue;
			}
			// Candidate for "next": threshold rules that only miss the amount.
			if ( (float) $rule['min_total'] > 0 && ! empty( $rule['show_progress'] ) && $res['remaining'] > 0 ) {
				if ( ! $this->other_conditions_met( $rule, $cart ) ) {
					continue;
				}
				if ( null === $next || $res['remaining'] < $next['remaining'] ) {
					$next = array(
						'rule'      => $rule,
						'remaining' => $res['remaining'],
						'threshold' => (float) $rule['min_total'],
						'percent'   => (float) $rule['min_total'] > 0 ? min( 100, max( 0, ( $basis / (float) $rule['min_total'] ) * 100 ) ) : 100,
					);
				}
			}
		}

		return array(
			'next'     => $next,
			'unlocked' => $unlocked,
			'choices'  => $choices,
			'basis'    => $basis,
		);
	}

	/**
	 * Would the rule match if only the cart value were high enough?
	 *
	 * @param array   $rule Rule.
	 * @param WC_Cart $cart Cart.
	 * @return bool
	 */
	private function other_conditions_met( array $rule, WC_Cart $cart ) {
		$copy              = $rule;
		$copy['min_total'] = 0.0;
		$copy['max_total'] = 0.0;
		return $this->conditions_match( $copy, PHP_FLOAT_MAX, $this->cart_facts( $cart ) );
	}

	/**
	 * Cheap cart signature for the evaluation cache.
	 *
	 * @param WC_Cart $cart Cart.
	 * @return string
	 */
	private function signature( WC_Cart $cart ) {
		$parts = array( get_current_user_id() );
		foreach ( $cart->get_cart() as $key => $item ) {
			$parts[] = $key . ':' . ( isset( $item['quantity'] ) ? $item['quantity'] : 0 ) . ':' . ( isset( $item['line_subtotal'] ) ? $item['line_subtotal'] : '' ) . ':' . ( isset( $item['line_total'] ) ? $item['line_total'] : '' );
		}
		$parts[] = implode( ',', $cart->get_applied_coupons() );
		return md5( implode( '|', $parts ) );
	}
}
