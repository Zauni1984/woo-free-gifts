<?php
/**
 * Gift rule repository (storage + sanitization).
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Rules
 */
final class WFG_Rules {

	const OPTION_KEY     = 'wfg_rules';
	const OPTION_NEXT_ID = 'wfg_rules_next_id';

	/**
	 * In-request cache.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Default rule structure.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'id'                  => 0,
			'title'               => '',
			'enabled'             => true,
			'priority'            => 10,

			// Conditions – all non-empty conditions must match (AND).
			'min_total'           => 0.0,
			'max_total'           => 0.0,
			'required_products'   => array(),
			'required_match'      => 'all', // all | any.
			'required_qty'        => 1,
			'required_categories' => array(),
			'require_bundle'      => false,
			'min_items'           => 0,
			'user_roles'          => array(),
			'logged_in_only'      => false,
			'once_per_customer'   => false,
			'date_from'           => '',
			'date_to'             => '',

			// Gifts.
			'gift_mode'           => 'auto', // auto | choice.
			'gifts'               => array(),

			// Messaging.
			'show_progress'       => true,
			'msg_progress'        => '',
			'msg_unlocked'        => '',
			'show_in_popup'       => true,
			'popup_text'          => '',
		);
	}

	/**
	 * All rules keyed by id.
	 *
	 * @return array[]
	 */
	public function all() {
		if ( null === $this->cache ) {
			$stored = get_option( self::OPTION_KEY, array() );
			$rules  = array();
			if ( is_array( $stored ) ) {
				foreach ( $stored as $rule ) {
					if ( ! is_array( $rule ) || empty( $rule['id'] ) ) {
						continue;
					}
					$rule                 = wp_parse_args( $rule, self::defaults() );
					$rules[ $rule['id'] ] = $rule;
				}
			}
			$this->cache = $rules;
		}
		return $this->cache;
	}

	/**
	 * Get one rule.
	 *
	 * @param int $id Rule id.
	 * @return array|null
	 */
	public function get( $id ) {
		$id  = absint( $id );
		$all = $this->all();
		return isset( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Enabled rules whose date window is open, ordered by priority (desc) then threshold (desc).
	 *
	 * @return array[]
	 */
	public function active() {
		$now   = current_time( 'timestamp' ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- local time is intended for date windows.
		$rules = array();
		foreach ( $this->all() as $rule ) {
			if ( empty( $rule['enabled'] ) || empty( $rule['gifts'] ) ) {
				continue;
			}
			if ( '' !== $rule['date_from'] && $now < strtotime( $rule['date_from'] . ' 00:00:00' ) ) {
				continue;
			}
			if ( '' !== $rule['date_to'] && $now > strtotime( $rule['date_to'] . ' 23:59:59' ) ) {
				continue;
			}
			$rules[ $rule['id'] ] = $rule;
		}

		uasort(
			$rules,
			static function ( $a, $b ) {
				if ( (int) $a['priority'] !== (int) $b['priority'] ) {
					return (int) $b['priority'] <=> (int) $a['priority'];
				}
				if ( (float) $a['min_total'] !== (float) $b['min_total'] ) {
					return (float) $b['min_total'] <=> (float) $a['min_total'];
				}
				return (int) $a['id'] <=> (int) $b['id'];
			}
		);

		/**
		 * Filter the list of active gift rules.
		 *
		 * @param array[] $rules Active rules keyed by id.
		 */
		return apply_filters( 'wfg_active_rules', $rules );
	}

	/**
	 * Insert or update a rule.
	 *
	 * @param array $rule Sanitized rule (see sanitize()).
	 * @return int Rule id.
	 */
	public function save( array $rule ) {
		$all = $this->all();

		if ( empty( $rule['id'] ) ) {
			$rule['id'] = $this->next_id();
		}
		$rule['id'] = absint( $rule['id'] );

		$all[ $rule['id'] ] = wp_parse_args( $rule, self::defaults() );
		$this->persist( $all );

		return $rule['id'];
	}

	/**
	 * Delete a rule.
	 *
	 * @param int $id Rule id.
	 * @return bool
	 */
	public function delete( $id ) {
		$id  = absint( $id );
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return false;
		}
		unset( $all[ $id ] );
		$this->persist( $all );
		return true;
	}

	/**
	 * Toggle enabled flag.
	 *
	 * @param int  $id      Rule id.
	 * @param bool $enabled New state.
	 */
	public function set_enabled( $id, $enabled ) {
		$rule = $this->get( $id );
		if ( $rule ) {
			$rule['enabled'] = (bool) $enabled;
			$this->save( $rule );
		}
	}

	/**
	 * Store all rules.
	 *
	 * @param array $all Rules keyed by id.
	 */
	private function persist( array $all ) {
		update_option( self::OPTION_KEY, array_values( $all ), 'yes' );
		$this->cache = null;
	}

	/**
	 * Allocate the next auto-increment id.
	 *
	 * @return int
	 */
	private function next_id() {
		$next = absint( get_option( self::OPTION_NEXT_ID, 1 ) );
		$max  = 0;
		foreach ( $this->all() as $rule ) {
			$max = max( $max, (int) $rule['id'] );
		}
		$next = max( $next, $max + 1 );
		update_option( self::OPTION_NEXT_ID, $next + 1, false );
		return $next;
	}

	/**
	 * Sanitize a raw rule (from the admin form) into the canonical structure.
	 *
	 * Gift rows must already be resolved to product ids (see WFG_Admin::handle_save_rule()).
	 *
	 * @param array $raw Raw input.
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$d = self::defaults();
		$r = array();

		$r['id']       = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
		$r['title']    = isset( $raw['title'] ) ? sanitize_text_field( $raw['title'] ) : '';
		$r['enabled']  = ! empty( $raw['enabled'] );
		$r['priority'] = isset( $raw['priority'] ) ? max( 0, min( 9999, (int) $raw['priority'] ) ) : $d['priority'];

		$r['min_total'] = isset( $raw['min_total'] ) ? self::to_float( $raw['min_total'] ) : 0.0;
		$r['max_total'] = isset( $raw['max_total'] ) ? self::to_float( $raw['max_total'] ) : 0.0;
		if ( $r['max_total'] > 0 && $r['max_total'] < $r['min_total'] ) {
			$r['max_total'] = 0.0;
		}

		$r['required_products']   = self::id_list( isset( $raw['required_products'] ) ? $raw['required_products'] : array() );
		$r['required_match']      = ( isset( $raw['required_match'] ) && 'any' === $raw['required_match'] ) ? 'any' : 'all';
		$r['required_qty']        = isset( $raw['required_qty'] ) ? max( 1, min( 9999, absint( $raw['required_qty'] ) ) ) : 1;
		$r['required_categories'] = self::id_list( isset( $raw['required_categories'] ) ? $raw['required_categories'] : array() );
		$r['require_bundle']      = ! empty( $raw['require_bundle'] );
		$r['min_items']           = isset( $raw['min_items'] ) ? max( 0, min( 9999, absint( $raw['min_items'] ) ) ) : 0;

		$roles = array();
		if ( ! empty( $raw['user_roles'] ) && is_array( $raw['user_roles'] ) ) {
			$valid = array_keys( wp_roles()->roles );
			foreach ( $raw['user_roles'] as $role ) {
				$role = sanitize_key( $role );
				if ( in_array( $role, $valid, true ) ) {
					$roles[] = $role;
				}
			}
		}
		$r['user_roles']        = array_values( array_unique( $roles ) );
		$r['logged_in_only']    = ! empty( $raw['logged_in_only'] );
		$r['once_per_customer'] = ! empty( $raw['once_per_customer'] );
		$r['date_from']         = self::sanitize_date( isset( $raw['date_from'] ) ? $raw['date_from'] : '' );
		$r['date_to']           = self::sanitize_date( isset( $raw['date_to'] ) ? $raw['date_to'] : '' );

		$r['gift_mode'] = ( isset( $raw['gift_mode'] ) && 'choice' === $raw['gift_mode'] ) ? 'choice' : 'auto';

		$gifts = array();
		if ( ! empty( $raw['gifts'] ) && is_array( $raw['gifts'] ) ) {
			foreach ( $raw['gifts'] as $gift ) {
				if ( ! is_array( $gift ) ) {
					continue;
				}
				$pid = isset( $gift['product_id'] ) ? absint( $gift['product_id'] ) : 0;
				if ( ! $pid ) {
					continue;
				}
				$gifts[] = array(
					'product_id' => $pid,
					'qty'        => isset( $gift['qty'] ) ? max( 1, min( 99, absint( $gift['qty'] ) ) ) : 1,
					'custom'     => ! empty( $gift['custom'] ),
				);
			}
		}
		$r['gifts'] = $gifts;

		$r['show_progress'] = ! empty( $raw['show_progress'] );
		$r['msg_progress']  = isset( $raw['msg_progress'] ) ? sanitize_text_field( $raw['msg_progress'] ) : '';
		$r['msg_unlocked']  = isset( $raw['msg_unlocked'] ) ? sanitize_text_field( $raw['msg_unlocked'] ) : '';
		$r['show_in_popup'] = ! empty( $raw['show_in_popup'] );
		$r['popup_text']    = isset( $raw['popup_text'] ) ? sanitize_text_field( $raw['popup_text'] ) : '';

		return $r;
	}

	/**
	 * Human readable summary of a rule's conditions (admin list).
	 *
	 * @param array $rule Rule.
	 * @return string[]
	 */
	public static function describe_conditions( array $rule ) {
		$parts = array();
		if ( $rule['min_total'] > 0 ) {
			/* translators: %s: formatted amount */
			$parts[] = sprintf( __( 'Cart value ≥ %s', 'woo-free-gifts' ), wp_strip_all_tags( wc_price( $rule['min_total'] ) ) );
		}
		if ( $rule['max_total'] > 0 ) {
			/* translators: %s: formatted amount */
			$parts[] = sprintf( __( 'Cart value ≤ %s', 'woo-free-gifts' ), wp_strip_all_tags( wc_price( $rule['max_total'] ) ) );
		}
		if ( ! empty( $rule['required_products'] ) ) {
			$names = array();
			foreach ( $rule['required_products'] as $pid ) {
				$p       = wc_get_product( $pid );
				$names[] = $p ? $p->get_formatted_name() : '#' . $pid;
			}
			$parts[] = sprintf(
				/* translators: 1: the word all or the word any, 2: product list */
				__( 'Cart contains %1$s of: %2$s', 'woo-free-gifts' ),
				'any' === $rule['required_match'] ? __( 'any', 'woo-free-gifts' ) : __( 'all', 'woo-free-gifts' ),
				implode( ', ', $names )
			);
		}
		if ( ! empty( $rule['required_categories'] ) ) {
			$names = array();
			foreach ( $rule['required_categories'] as $tid ) {
				$term    = get_term( $tid, 'product_cat' );
				$names[] = ( $term && ! is_wp_error( $term ) ) ? $term->name : '#' . $tid;
			}
			/* translators: %s: category list */
			$parts[] = sprintf( __( 'Cart contains a product from: %s', 'woo-free-gifts' ), implode( ', ', $names ) );
		}
		if ( ! empty( $rule['require_bundle'] ) ) {
			$parts[] = __( 'Cart contains a bundle product', 'woo-free-gifts' );
		}
		if ( $rule['min_items'] > 0 ) {
			/* translators: %d: item count */
			$parts[] = sprintf( __( 'At least %d items in cart', 'woo-free-gifts' ), $rule['min_items'] );
		}
		if ( empty( $parts ) ) {
			$parts[] = __( 'Always (no conditions)', 'woo-free-gifts' );
		}
		return $parts;
	}

	/**
	 * Parse a localized/decimal string to float.
	 *
	 * @param mixed $value Raw.
	 * @return float
	 */
	private static function to_float( $value ) {
		if ( is_array( $value ) ) {
			return 0.0;
		}
		$value = wc_format_decimal( (string) $value );
		$value = is_numeric( $value ) ? (float) $value : 0.0;
		return max( 0.0, $value );
	}

	/**
	 * Sanitize a list of ids (array or comma string).
	 *
	 * @param mixed $value Raw.
	 * @return int[]
	 */
	private static function id_list( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		if ( ! is_array( $value ) ) {
			return array();
		}
		$ids = array_filter( array_map( 'absint', $value ) );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Sanitize a Y-m-d date.
	 *
	 * @param mixed $value Raw.
	 * @return string
	 */
	private static function sanitize_date( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( '' === $value ) {
			return '';
		}
		$dt = DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $dt && $dt->format( 'Y-m-d' ) === $value ) ? $value : '';
	}
}
