<?php
/**
 * Main plugin container.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Plugin
 */
final class WFG_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var WFG_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings component.
	 *
	 * @var WFG_Settings
	 */
	public $settings;

	/**
	 * Rules repository.
	 *
	 * @var WFG_Rules
	 */
	public $rules;

	/**
	 * Custom gift product manager.
	 *
	 * @var WFG_Gift_Products
	 */
	public $gift_products;

	/**
	 * Rule engine.
	 *
	 * @var WFG_Engine
	 */
	public $engine;

	/**
	 * Cart integration.
	 *
	 * @var WFG_Cart
	 */
	public $cart;

	/**
	 * Order integration.
	 *
	 * @var WFG_Order
	 */
	public $order;

	/**
	 * Frontend rendering.
	 *
	 * @var WFG_Frontend
	 */
	public $frontend;

	/**
	 * Popup.
	 *
	 * @var WFG_Popup
	 */
	public $popup;

	/**
	 * Wheel of fortune.
	 *
	 * @var WFG_Wheel
	 */
	public $wheel;

	/**
	 * Get singleton.
	 *
	 * @return WFG_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor – wires everything up.
	 */
	private function __construct() {
		$this->load_textdomain();

		WFG_Install::maybe_upgrade();

		$this->settings      = new WFG_Settings();
		$this->rules         = new WFG_Rules();
		$this->gift_products = new WFG_Gift_Products();
		$this->engine        = new WFG_Engine( $this->settings, $this->rules );
		$this->cart          = new WFG_Cart( $this->settings, $this->engine );
		$this->order         = new WFG_Order( $this->settings, $this->rules );
		$this->frontend      = new WFG_Frontend( $this->settings, $this->engine, $this->cart );
		$this->popup         = new WFG_Popup( $this->settings, $this->rules, $this->engine );
		$this->wheel         = new WFG_Wheel( $this->settings, $this->engine );

		new WFG_Ajax( $this->settings, $this->engine, $this->cart );
		new WFG_Shortcodes( $this->frontend );

		if ( is_admin() ) {
			new WFG_Admin( $this->settings, $this->rules, $this->gift_products );
		}

		add_filter( 'plugin_action_links_' . WFG_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		/**
		 * Fires once Woo Free Gifts has finished loading.
		 *
		 * @param WFG_Plugin $plugin Plugin instance.
		 */
		do_action( 'wfg_loaded', $this );
	}

	/**
	 * Load translations.
	 */
	private function load_textdomain() {
		load_plugin_textdomain( 'woo-free-gifts', false, dirname( WFG_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Add a "Settings" link on the plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$url = admin_url( 'admin.php?page=wfg-free-gifts' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'woo-free-gifts' ) . '</a>' );
		return $links;
	}
}
