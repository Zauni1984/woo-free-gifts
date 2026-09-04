<?php
/**
 * Plugin Name:       Woo Free Gifts Premium
 * Plugin URI:        https://github.com/zauni1984/woo-free-gifts
 * Description:       Premium free gift engine for WooCommerce: cart value thresholds, buy-X-get-gift bundles, custom (non-catalog) gifts, gift choice, progress bar, promo popup and a daily wheel of fortune.
 * Version:           1.0.0
 * Author:            Stefan Zaunreither
 * Author URI:        https://github.com/zauni1984
 * Text Domain:       woo-free-gifts
 * Domain Path:       /languages
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.9
 * Requires Plugins:  woocommerce
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'WFG_VERSION' ) ) {
	// Another copy of the plugin is already loaded – never load twice.
	return;
}

define( 'WFG_VERSION', '1.0.0' );
define( 'WFG_PLUGIN_FILE', __FILE__ );
define( 'WFG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WFG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WFG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'WFG_MIN_PHP', '7.4' );
define( 'WFG_MIN_WC', '7.0' );

/**
 * Environment check: PHP version.
 *
 * Runs before anything else is loaded so an unsupported host never sees a fatal error.
 */
if ( version_compare( PHP_VERSION, WFG_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					__( 'Woo Free Gifts requires PHP %1$s or newer. You are running PHP %2$s. The plugin stays inactive.', 'woo-free-gifts' ),
					WFG_MIN_PHP,
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

/**
 * Declare WooCommerce feature compatibility (HPOS + Cart/Checkout blocks).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Boot the plugin once all plugins are loaded, so we can verify WooCommerce is present.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Woo Free Gifts requires WooCommerce to be installed and active. The plugin stays inactive until WooCommerce is available.', 'woo-free-gifts' );
					echo '</p></div>';
				}
			);
			return;
		}

		if ( version_compare( WC_VERSION, WFG_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html(
						sprintf(
							/* translators: 1: required WooCommerce version, 2: current WooCommerce version */
							__( 'Woo Free Gifts requires WooCommerce %1$s or newer. You are running %2$s. The plugin stays inactive.', 'woo-free-gifts' ),
							WFG_MIN_WC,
							WC_VERSION
						)
					);
					echo '</p></div>';
				}
			);
			return;
		}

		require_once WFG_PLUGIN_DIR . 'includes/class-wfg-autoloader.php';
		WFG_Autoloader::register();

		WFG_Plugin::instance();
	},
	20
);

/**
 * Activation / deactivation hooks. These are registered unconditionally so the
 * hooks always fire, but they only do work when WooCommerce is present.
 */
register_activation_hook(
	__FILE__,
	static function () {
		require_once WFG_PLUGIN_DIR . 'includes/class-wfg-autoloader.php';
		WFG_Autoloader::register();
		WFG_Install::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		require_once WFG_PLUGIN_DIR . 'includes/class-wfg-autoloader.php';
		WFG_Autoloader::register();
		WFG_Install::deactivate();
	}
);

/**
 * Convenience accessor.
 *
 * @return WFG_Plugin|null
 */
function wfg() {
	return class_exists( 'WFG_Plugin' ) ? WFG_Plugin::instance() : null;
}
