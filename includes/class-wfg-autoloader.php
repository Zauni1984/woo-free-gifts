<?php
/**
 * Minimal PSR-0-ish autoloader for WFG_* classes.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Autoloader
 */
final class WFG_Autoloader {

	/**
	 * Whether the autoloader has been registered.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * Autoload callback.
	 *
	 * Maps `WFG_Foo_Bar` to `includes/class-wfg-foo-bar.php`, and
	 * `WFG_Admin_Foo` to `includes/admin/class-wfg-admin-foo.php`.
	 *
	 * @param string $class_name Class name.
	 */
	public static function autoload( $class_name ) {
		if ( 0 !== strpos( $class_name, 'WFG_' ) ) {
			return;
		}

		$file = 'class-' . str_replace( '_', '-', strtolower( $class_name ) ) . '.php';

		$paths = array(
			WFG_PLUGIN_DIR . 'includes/',
			WFG_PLUGIN_DIR . 'includes/admin/',
			WFG_PLUGIN_DIR . 'includes/integrations/',
		);

		foreach ( $paths as $path ) {
			$full = $path . $file;
			if ( is_readable( $full ) ) {
				require_once $full;
				return;
			}
		}
	}
}
