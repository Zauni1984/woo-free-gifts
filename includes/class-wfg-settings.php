<?php
/**
 * Global plugin settings.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Settings
 */
final class WFG_Settings {

	const OPTION_KEY = 'wfg_settings';

	/**
	 * In-request cache.
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// General.
			'enabled'                  => true,
			'threshold_basis'          => 'subtotal_excl_tax', // subtotal_excl_tax | subtotal_incl_tax.
			'after_discounts'          => false,               // Deduct coupon discounts before comparing thresholds.
			'stacking'                 => 'all',               // all | highest.
			'allow_remove'             => true,                // Customers may remove a gift from the cart.
			'count_bundled_items'      => true,                // Bundle child items count for product conditions.
			'gift_badge'               => __( 'Free gift', 'woo-free-gifts' ),
			'gift_price_label'         => __( 'Free', 'woo-free-gifts' ),
			'custom_gift_virtual'      => false,

			// Cart / checkout messaging.
			'show_progress_cart'       => true,
			'show_progress_checkout'   => true,
			'show_progress_minicart'   => true,
			'show_single_hint'         => true,
			'msg_progress'             => __( 'Add {remaining} more to your cart to get {gift} for free!', 'woo-free-gifts' ),
			'msg_unlocked'             => __( '🎉 You unlocked {gift} – it has been added to your cart for free.', 'woo-free-gifts' ),
			'msg_choose'               => __( 'Pick your free gift:', 'woo-free-gifts' ),
			'msg_single_hint'          => __( '🎁 Free gift: {gift} from {threshold} order value.', 'woo-free-gifts' ),
			'progress_color'           => '#2e7d32',

			// Popup.
			'popup_enabled'            => false,
			'popup_on_single'          => true,
			'popup_on_archive'         => true,
			'popup_frequency'          => 'session', // session | days | once | always.
			'popup_days'               => 7,
			'popup_delay'              => 2,
			'popup_title'              => __( 'Free gifts waiting for you 🎁', 'woo-free-gifts' ),
			'popup_content'            => __( 'Reach the order value below and we add a gift to your cart automatically.', 'woo-free-gifts' ),
			'popup_button'             => __( 'Continue shopping', 'woo-free-gifts' ),
			'popup_button_url'         => '',
			'popup_image_id'           => 0,
			'popup_show_gifts'         => true,
			'popup_accent'             => '#2e7d32',

			// Wheel of fortune.
			'wheel_enabled'            => false,
			'wheel_theme'              => 'stoner', // stoner | classic.
			'wheel_on_single'          => true,
			'wheel_on_archive'         => true,
			'wheel_on_cart'            => true,
			'wheel_on_other'           => false,
			'wheel_cooldown_hours'     => 24,
			'wheel_delay'              => 3,
			'wheel_title'              => __( 'Spin the wheel, buddy 🍃', 'woo-free-gifts' ),
			'wheel_content'            => __( 'One free spin a day. Win coupons and free goodies – no strings attached.', 'woo-free-gifts' ),
			'wheel_button'             => __( 'Spin it 🔥', 'woo-free-gifts' ),
			'wheel_require_email'      => false,
			'wheel_email_label'        => __( 'Your e-mail address', 'woo-free-gifts' ),
			'wheel_consent_text'       => '',
			'wheel_coupon_prefix'      => 'HIGH',
			'wheel_coupon_expiry_days' => 7,
			'wheel_coupon_min_amount'  => 0,
			'wheel_auto_apply'         => true,
			'wheel_gift_valid_days'    => 7,
			'wheel_ip_check'           => true,
			'wheel_msg_win_coupon'     => __( 'Dope! Your code {code} gets you {prize}. Valid for {days} days.', 'woo-free-gifts' ),
			'wheel_msg_win_gift'       => __( 'Jackpot! {prize} lands in your cart for free with your next order.', 'woo-free-gifts' ),
			'wheel_msg_lose'           => __( 'Not this time, mate. Chill and come back tomorrow.', 'woo-free-gifts' ),
			'wheel_msg_already'        => __( 'You already spun today. Next spin in {hours} h.', 'woo-free-gifts' ),
			'wheel_accent'             => '#7CFF4D',
			'wheel_segments'           => self::default_segments(),

			// Maintenance.
			'debug_log'                => false,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Default wheel segments.
	 *
	 * @return array[]
	 */
	public static function default_segments() {
		return array(
			array(
				'label'       => __( '10 % off', 'woo-free-gifts' ),
				'type'        => 'coupon',
				'coupon_type' => 'percent',
				'amount'      => 10,
				'code'        => '',
				'product_id'  => 0,
				'weight'      => 25,
				'color'       => '#1b5e20',
			),
			array(
				'label'       => __( 'Try again 🌱', 'woo-free-gifts' ),
				'type'        => 'none',
				'coupon_type' => 'percent',
				'amount'      => 0,
				'code'        => '',
				'product_id'  => 0,
				'weight'      => 30,
				'color'       => '#263238',
			),
			array(
				'label'       => __( '5 € off', 'woo-free-gifts' ),
				'type'        => 'coupon',
				'coupon_type' => 'fixed_cart',
				'amount'      => 5,
				'code'        => '',
				'product_id'  => 0,
				'weight'      => 25,
				'color'       => '#2e7d32',
			),
			array(
				'label'       => __( 'Free gift 🎁', 'woo-free-gifts' ),
				'type'        => 'gift',
				'coupon_type' => 'percent',
				'amount'      => 0,
				'code'        => '',
				'product_id'  => 0,
				'weight'      => 5,
				'color'       => '#6a1b9a',
			),
			array(
				'label'       => __( 'Puff, puff, pass 💨', 'woo-free-gifts' ),
				'type'        => 'none',
				'coupon_type' => 'percent',
				'amount'      => 0,
				'code'        => '',
				'product_id'  => 0,
				'weight'      => 10,
				'color'       => '#37474f',
			),
			array(
				'label'       => __( '20 % off 🔥', 'woo-free-gifts' ),
				'type'        => 'coupon',
				'coupon_type' => 'percent',
				'amount'      => 20,
				'code'        => '',
				'product_id'  => 0,
				'weight'      => 5,
				'color'       => '#43a047',
			),
		);
	}

	/**
	 * Get all settings (merged with defaults).
	 *
	 * @return array
	 */
	public function all() {
		if ( null === $this->cache ) {
			$stored      = get_option( self::OPTION_KEY, array() );
			$stored      = is_array( $stored ) ? $stored : array();
			$this->cache = wp_parse_args( $stored, self::defaults() );
		}
		return $this->cache;
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $fallback Fallback when key is unknown.
	 * @return mixed
	 */
	public function get( $key, $fallback = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Boolean helper.
	 *
	 * @param string $key Setting key.
	 * @return bool
	 */
	public function is( $key ) {
		return (bool) $this->get( $key, false );
	}

	/**
	 * Whether the whole plugin is switched on.
	 *
	 * @return bool
	 */
	public function enabled() {
		return $this->is( 'enabled' );
	}

	/**
	 * Persist sanitized settings.
	 *
	 * @param array $raw Raw input (e.g. $_POST['wfg']).
	 * @return array Sanitized settings that were stored.
	 */
	public function save( array $raw ) {
		$clean = self::sanitize( $raw );
		update_option( self::OPTION_KEY, $clean, 'yes' );
		$this->cache = null;
		return $clean;
	}

	/**
	 * Sanitize a raw settings array against the schema.
	 *
	 * @param array $raw Raw input.
	 * @return array
	 */
	public static function sanitize( array $raw ) {
		$d     = self::defaults();
		$clean = array();

		$bools = array(
			'enabled',
			'after_discounts',
			'allow_remove',
			'count_bundled_items',
			'custom_gift_virtual',
			'show_progress_cart',
			'show_progress_checkout',
			'show_progress_minicart',
			'show_single_hint',
			'popup_enabled',
			'popup_on_single',
			'popup_on_archive',
			'popup_show_gifts',
			'wheel_enabled',
			'wheel_on_single',
			'wheel_on_archive',
			'wheel_on_cart',
			'wheel_on_other',
			'wheel_require_email',
			'wheel_auto_apply',
			'wheel_ip_check',
			'debug_log',
			'delete_data_on_uninstall',
		);
		foreach ( $bools as $key ) {
			$clean[ $key ] = ! empty( $raw[ $key ] );
		}

		$texts = array(
			'gift_badge',
			'gift_price_label',
			'msg_progress',
			'msg_unlocked',
			'msg_choose',
			'msg_single_hint',
			'popup_title',
			'popup_button',
			'wheel_title',
			'wheel_button',
			'wheel_email_label',
			'wheel_consent_text',
			'wheel_msg_win_coupon',
			'wheel_msg_win_gift',
			'wheel_msg_lose',
			'wheel_msg_already',
		);
		foreach ( $texts as $key ) {
			$clean[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : $d[ $key ];
		}

		$clean['wheel_content']            = isset( $raw['wheel_content'] ) ? wp_kses_post( $raw['wheel_content'] ) : $d['wheel_content'];
		$clean['wheel_theme']              = ( isset( $raw['wheel_theme'] ) && 'classic' === $raw['wheel_theme'] ) ? 'classic' : 'stoner';
		$clean['wheel_cooldown_hours']     = isset( $raw['wheel_cooldown_hours'] ) ? max( 1, min( 8760, absint( $raw['wheel_cooldown_hours'] ) ) ) : $d['wheel_cooldown_hours'];
		$clean['wheel_delay']              = isset( $raw['wheel_delay'] ) ? max( 0, min( 120, absint( $raw['wheel_delay'] ) ) ) : $d['wheel_delay'];
		$clean['wheel_coupon_expiry_days'] = isset( $raw['wheel_coupon_expiry_days'] ) ? max( 0, min( 365, absint( $raw['wheel_coupon_expiry_days'] ) ) ) : $d['wheel_coupon_expiry_days'];
		$clean['wheel_gift_valid_days']    = isset( $raw['wheel_gift_valid_days'] ) ? max( 1, min( 365, absint( $raw['wheel_gift_valid_days'] ) ) ) : $d['wheel_gift_valid_days'];
		$clean['wheel_coupon_min_amount']  = isset( $raw['wheel_coupon_min_amount'] ) ? max( 0.0, (float) wc_format_decimal( (string) $raw['wheel_coupon_min_amount'] ) ) : 0.0;
		$prefix                            = isset( $raw['wheel_coupon_prefix'] ) ? strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $raw['wheel_coupon_prefix'] ) ) : '';
		$clean['wheel_coupon_prefix']      = '' !== $prefix ? substr( $prefix, 0, 12 ) : $d['wheel_coupon_prefix'];
		$clean['wheel_accent']             = self::sanitize_color( isset( $raw['wheel_accent'] ) ? $raw['wheel_accent'] : '', $d['wheel_accent'] );
		$clean['wheel_segments']           = self::sanitize_segments( isset( $raw['wheel_segments'] ) ? $raw['wheel_segments'] : null, $d['wheel_segments'] );

		$clean['popup_content'] = isset( $raw['popup_content'] ) ? wp_kses_post( $raw['popup_content'] ) : $d['popup_content'];

		$clean['threshold_basis'] = ( isset( $raw['threshold_basis'] ) && 'subtotal_incl_tax' === $raw['threshold_basis'] ) ? 'subtotal_incl_tax' : 'subtotal_excl_tax';
		$clean['stacking']        = ( isset( $raw['stacking'] ) && 'highest' === $raw['stacking'] ) ? 'highest' : 'all';

		$freq                     = isset( $raw['popup_frequency'] ) ? sanitize_key( $raw['popup_frequency'] ) : 'session';
		$clean['popup_frequency'] = in_array( $freq, array( 'session', 'days', 'once', 'always' ), true ) ? $freq : 'session';

		$clean['popup_days']       = isset( $raw['popup_days'] ) ? max( 1, min( 365, absint( $raw['popup_days'] ) ) ) : $d['popup_days'];
		$clean['popup_delay']      = isset( $raw['popup_delay'] ) ? max( 0, min( 120, absint( $raw['popup_delay'] ) ) ) : $d['popup_delay'];
		$clean['popup_image_id']   = isset( $raw['popup_image_id'] ) ? absint( $raw['popup_image_id'] ) : 0;
		$clean['popup_button_url'] = isset( $raw['popup_button_url'] ) ? esc_url_raw( $raw['popup_button_url'] ) : '';

		$clean['progress_color'] = self::sanitize_color( isset( $raw['progress_color'] ) ? $raw['progress_color'] : '', $d['progress_color'] );
		$clean['popup_accent']   = self::sanitize_color( isset( $raw['popup_accent'] ) ? $raw['popup_accent'] : '', $d['popup_accent'] );

		return $clean;
	}

	/**
	 * Sanitize the wheel segments (2–12 rows).
	 *
	 * @param mixed $raw      Raw rows (null = keep defaults).
	 * @param array $fallback Default segments.
	 * @return array[]
	 */
	public static function sanitize_segments( $raw, array $fallback ) {
		if ( null === $raw ) {
			return $fallback;
		}
		if ( ! is_array( $raw ) ) {
			return $fallback;
		}
		$palette = array( '#1b5e20', '#263238', '#2e7d32', '#6a1b9a', '#37474f', '#43a047', '#004d40', '#4a148c' );
		$out     = array();
		$i       = 0;
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label ) {
				continue;
			}
			$type = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'none';
			if ( ! in_array( $type, array( 'coupon', 'gift', 'none' ), true ) ) {
				$type = 'none';
			}
			$coupon_type = ( isset( $row['coupon_type'] ) && 'fixed_cart' === $row['coupon_type'] ) ? 'fixed_cart' : 'percent';
			$amount      = isset( $row['amount'] ) ? (float) wc_format_decimal( (string) $row['amount'] ) : 0.0;
			$amount      = max( 0.0, 'percent' === $coupon_type ? min( 100.0, $amount ) : $amount );
			$code        = isset( $row['code'] ) ? wc_format_coupon_code( sanitize_text_field( $row['code'] ) ) : '';
			$product_id  = isset( $row['product_id'] ) ? absint( is_array( $row['product_id'] ) ? reset( $row['product_id'] ) : $row['product_id'] ) : 0;

			if ( 'coupon' === $type && $amount <= 0 && '' === $code ) {
				$type = 'none';
			}
			if ( 'gift' === $type && ! $product_id ) {
				$type = 'none';
			}

			$out[] = array(
				'label'       => $label,
				'type'        => $type,
				'coupon_type' => $coupon_type,
				'amount'      => $amount,
				'code'        => $code,
				'product_id'  => $product_id,
				'weight'      => isset( $row['weight'] ) ? max( 0, min( 1000, absint( $row['weight'] ) ) ) : 10,
				'color'       => self::sanitize_color( isset( $row['color'] ) ? $row['color'] : '', $palette[ $i % count( $palette ) ] ),
			);
			++$i;
			if ( $i >= 12 ) {
				break;
			}
		}
		return count( $out ) >= 2 ? $out : $fallback;
	}

	/**
	 * Sanitize a hex colour.
	 *
	 * @param string $value    Raw value.
	 * @param string $fallback Default.
	 * @return string
	 */
	public static function sanitize_color( $value, $fallback ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		$hex   = sanitize_hex_color( $value );
		return $hex ? $hex : $fallback;
	}
}
