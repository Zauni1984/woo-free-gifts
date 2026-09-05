<?php
/**
 * Shortcodes.
 *
 * [wfg_progress]  – progress bar / unlocked gifts / gift choice for the current cart.
 * [wfg_gift_list] – marketing list of all active gift offers (for landing pages).
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Shortcodes
 */
final class WFG_Shortcodes {

	/**
	 * Frontend.
	 *
	 * @var WFG_Frontend
	 */
	private $frontend;

	/**
	 * Constructor.
	 *
	 * @param WFG_Frontend $frontend Frontend.
	 */
	public function __construct( WFG_Frontend $frontend ) {
		$this->frontend = $frontend;
		add_shortcode( 'wfg_progress', array( $this, 'progress' ) );
		add_shortcode( 'wfg_gift_list', array( $this, 'gift_list' ) );
	}

	/**
	 * [wfg_progress]
	 *
	 * @return string
	 */
	public function progress() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return '';
		}
		return $this->frontend->render_box( 'shortcode' );
	}

	/**
	 * [wfg_gift_list]
	 *
	 * @return string
	 */
	public function gift_list() {
		$plugin = wfg();
		if ( ! $plugin || ! $plugin->popup ) {
			return '';
		}
		$offers = $plugin->popup->offers();
		if ( empty( $offers ) ) {
			return '';
		}
		return WFG_Helpers::template( 'gift-list', array( 'offers' => $offers ) );
	}
}
