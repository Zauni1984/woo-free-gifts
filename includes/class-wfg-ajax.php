<?php
/**
 * AJAX endpoints (frontend).
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Ajax
 */
final class WFG_Ajax {

	/**
	 * Settings.
	 *
	 * @var WFG_Settings
	 */
	private $settings;

	/**
	 * Engine.
	 *
	 * @var WFG_Engine
	 */
	private $engine;

	/**
	 * Cart.
	 *
	 * @var WFG_Cart
	 */
	private $cart;

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings $settings Settings.
	 * @param WFG_Engine   $engine   Engine.
	 * @param WFG_Cart     $cart     Cart.
	 */
	public function __construct( WFG_Settings $settings, WFG_Engine $engine, WFG_Cart $cart ) {
		$this->settings = $settings;
		$this->engine   = $engine;
		$this->cart     = $cart;

		add_action( 'wp_ajax_wfg_choose_gift', array( $this, 'choose_gift' ) );
		add_action( 'wp_ajax_nopriv_wfg_choose_gift', array( $this, 'choose_gift' ) );
	}

	/**
	 * Customer picks a gift.
	 */
	public function choose_gift() {
		check_ajax_referer( 'wfg-frontend', 'nonce' );

		if ( ! $this->settings->enabled() ) {
			wp_send_json_error( array( 'message' => __( 'Gifts are currently disabled.', 'woo-free-gifts' ) ), 400 );
		}

		$rule_id    = isset( $_POST['rule_id'] ) ? absint( $_POST['rule_id'] ) : 0;
		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( ! $rule_id || ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'woo-free-gifts' ) ), 400 );
		}

		try {
			$result = $this->cart->choose( $rule_id, $product_id );
		} catch ( Throwable $e ) {
			WFG_Logger::error( 'choose_gift failed: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please reload the page.', 'woo-free-gifts' ) ), 500 );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Your gift has been added to the cart.', 'woo-free-gifts' ),
			)
		);
	}
}
