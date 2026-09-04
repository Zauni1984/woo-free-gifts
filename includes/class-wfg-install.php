<?php
/**
 * Activation, upgrade and deactivation routines.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Install
 */
final class WFG_Install {

	const OPTION_VERSION = 'wfg_version';

	/**
	 * Activation hook.
	 */
	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// Make sure defaults exist without overwriting existing settings.
		if ( false === get_option( WFG_Settings::OPTION_KEY, false ) ) {
			add_option( WFG_Settings::OPTION_KEY, WFG_Settings::defaults(), '', 'yes' );
		}
		if ( false === get_option( WFG_Rules::OPTION_KEY, false ) ) {
			add_option( WFG_Rules::OPTION_KEY, array(), '', 'yes' );
		}
		if ( false === get_option( WFG_Rules::OPTION_NEXT_ID, false ) ) {
			add_option( WFG_Rules::OPTION_NEXT_ID, 1, '', 'no' );
		}
		if ( false === get_option( WFG_Order::OPTION_STATS, false ) ) {
			add_option( WFG_Order::OPTION_STATS, array(), '', 'no' );
		}

		update_option( self::OPTION_VERSION, WFG_VERSION, false );

		// Clear cached rule lookups.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'wfg' );
		}
	}

	/**
	 * Deactivation hook – nothing destructive here; data is kept for re-activation.
	 */
	public static function deactivate() {
		// Intentionally empty. Data removal happens in uninstall.php when enabled in settings.
	}

	/**
	 * Run per-version upgrade routines when the stored version differs.
	 */
	public static function maybe_upgrade() {
		$stored = get_option( self::OPTION_VERSION, '' );
		if ( WFG_VERSION === $stored ) {
			return;
		}

		// Future migrations go here, keyed on $stored.
		update_option( self::OPTION_VERSION, WFG_VERSION, false );
	}
}
