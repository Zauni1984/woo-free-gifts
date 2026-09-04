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

			// Maintenance.
			'debug_log'                => false,
			'delete_data_on_uninstall' => false,
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
		);
		foreach ( $texts as $key ) {
			$clean[ $key ] = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : $d[ $key ];
		}

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
