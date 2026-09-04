<?php
/**
 * Frontend output: progress bar, gift choice, product hints, assets and fragments.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Frontend
 */
final class WFG_Frontend {

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

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );

		add_action( 'woocommerce_before_cart', array( $this, 'output_cart_box' ), 5 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'output_checkout_box' ), 5 );
		add_action( 'woocommerce_before_mini_cart', array( $this, 'output_minicart_box' ), 5 );
		add_action( 'woocommerce_single_product_summary', array( $this, 'output_single_hint' ), 25 );
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'fragments' ) );

		// Non-JS fallback for gift choice links.
		add_action( 'template_redirect', array( $this, 'handle_choice_request' ) );
	}

	/**
	 * Register and enqueue assets.
	 */
	public function enqueue() {
		if ( ! $this->settings->enabled() ) {
			return;
		}

		wp_register_style( 'wfg-frontend', WFG_PLUGIN_URL . 'assets/css/frontend.css', array(), WFG_VERSION );
		wp_register_script( 'wfg-frontend', WFG_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), WFG_VERSION, true );

		$inline = sprintf(
			':root{--wfg-accent:%1$s;--wfg-popup-accent:%2$s;}',
			$this->settings->get( 'progress_color' ),
			$this->settings->get( 'popup_accent' )
		);
		wp_add_inline_style( 'wfg-frontend', $inline );

		wp_localize_script(
			'wfg-frontend',
			'wfgData',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wfg-frontend' ),
				'i18n'    => array(
					'error' => __( 'Something went wrong. Please reload the page.', 'woo-free-gifts' ),
				),
			)
		);

		wp_enqueue_style( 'wfg-frontend' );
		wp_enqueue_script( 'wfg-frontend' );
	}

	// --- Boxes ---

	/**
	 * Cart page.
	 */
	public function output_cart_box() {
		if ( $this->settings->is( 'show_progress_cart' ) ) {
			echo $this->render_box( 'cart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template output is escaped in the template.
		}
	}

	/**
	 * Checkout page.
	 */
	public function output_checkout_box() {
		if ( $this->settings->is( 'show_progress_checkout' ) ) {
			echo $this->render_box( 'checkout' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Mini cart.
	 */
	public function output_minicart_box() {
		if ( $this->settings->is( 'show_progress_minicart' ) ) {
			echo $this->render_box( 'minicart' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Refresh the mini cart box on AJAX add-to-cart.
	 *
	 * @param array $fragments Fragments.
	 * @return array
	 */
	public function fragments( $fragments ) {
		if ( $this->settings->enabled() && $this->settings->is( 'show_progress_minicart' ) ) {
			$fragments['.wfg-box--minicart'] = $this->render_box( 'minicart' );
		}
		return $fragments;
	}

	/**
	 * Render the progress / unlocked / choice box.
	 *
	 * @param string $context cart | checkout | minicart | shortcode.
	 * @return string
	 */
	public function render_box( $context = 'cart' ) {
		$empty_box = 'minicart' === $context ? '<div class="wfg-box wfg-box--minicart wfg-box--empty"></div>' : '';
		if ( ! $this->settings->enabled() || ! WC()->cart ) {
			return $empty_box;
		}
		if ( 'shortcode' !== $context && WC()->cart->is_empty() ) {
			return $empty_box;
		}

		try {
			$data = $this->engine->progress( WC()->cart );
		} catch ( Throwable $e ) {
			WFG_Logger::error( 'Progress rendering failed: ' . $e->getMessage() );
			return '';
		}

		$args = array(
			'context'  => $context,
			'next'     => $data['next'],
			'unlocked' => $data['unlocked'],
			'choices'  => $data['choices'],
			'basis'    => $data['basis'],
			'settings' => $this->settings,
			'engine'   => $this->engine,
			'cart'     => $this->cart,
			'messages' => $this->messages( $data ),
		);

		$html = WFG_Helpers::template( 'progress-box', $args );

		return '' === trim( $html ) ? $empty_box : $html;
	}

	/**
	 * Prepare rendered messages for the template.
	 *
	 * @param array $data Progress data.
	 * @return array{progress: string, unlocked: string[]}
	 */
	private function messages( array $data ) {
		$progress = '';
		if ( $data['next'] ) {
			$rule     = $data['next']['rule'];
			$template = '' !== $rule['msg_progress'] ? $rule['msg_progress'] : $this->settings->get( 'msg_progress' );
			$progress = WFG_Helpers::placeholders(
				$template,
				array(
					'remaining' => WFG_Helpers::price_text( $data['next']['remaining'] ),
					'threshold' => WFG_Helpers::price_text( $data['next']['threshold'] ),
					'gift'      => $this->engine->gift_names( $rule ),
				)
			);
		}

		$unlocked = array();
		foreach ( $data['unlocked'] as $rule ) {
			if ( 'choice' === $rule['gift_mode'] && count( $this->engine->available_gifts( $rule ) ) > 1 ) {
				continue; // The choice box speaks for itself.
			}
			$names = $this->engine->gift_names( $rule );
			if ( '' === $names ) {
				continue;
			}
			$template   = '' !== $rule['msg_unlocked'] ? $rule['msg_unlocked'] : $this->settings->get( 'msg_unlocked' );
			$unlocked[] = WFG_Helpers::placeholders(
				$template,
				array(
					'remaining' => '',
					'threshold' => WFG_Helpers::price_text( $rule['min_total'] ),
					'gift'      => $names,
				)
			);
		}

		return array(
			'progress' => $progress,
			'unlocked' => $unlocked,
		);
	}

	// --- Single product hint ---

	/**
	 * Short teaser on the product page ("Free gift from 50 € order value").
	 */
	public function output_single_hint() {
		if ( ! $this->settings->enabled() || ! $this->settings->is( 'show_single_hint' ) ) {
			return;
		}
		$hint = $this->single_hint_text();
		if ( '' === $hint ) {
			return;
		}
		echo WFG_Helpers::template( 'single-hint', array( 'text' => $hint ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build the hint from the lowest-threshold active rule.
	 *
	 * @return string
	 */
	private function single_hint_text() {
		$best = null;
		foreach ( $this->engine->rules()->active() as $rule ) {
			if ( (float) $rule['min_total'] <= 0 || empty( $rule['show_progress'] ) ) {
				continue;
			}
			if ( ! empty( $rule['required_products'] ) || ! empty( $rule['required_categories'] ) || ! empty( $rule['require_bundle'] ) ) {
				continue;
			}
			if ( null === $best || (float) $rule['min_total'] < (float) $best['min_total'] ) {
				$best = $rule;
			}
		}
		if ( ! $best ) {
			return '';
		}
		$names = $this->engine->gift_names( $best );
		if ( '' === $names ) {
			return '';
		}
		return WFG_Helpers::placeholders(
			$this->settings->get( 'msg_single_hint' ),
			array(
				'gift'      => $names,
				'threshold' => WFG_Helpers::price_text( $best['min_total'] ),
				'remaining' => '',
			)
		);
	}

	// --- Gift choice (non-JS) ---

	/**
	 * URL that selects a gift without JavaScript.
	 *
	 * @param int $rule_id    Rule id.
	 * @param int $product_id Product id.
	 * @return string
	 */
	public static function choice_url( $rule_id, $product_id ) {
		$url = add_query_arg(
			array(
				'wfg_choose' => (int) $rule_id . '-' . (int) $product_id,
			),
			wc_get_cart_url()
		);
		return wp_nonce_url( $url, 'wfg-choose-' . (int) $rule_id . '-' . (int) $product_id, 'wfg_nonce' );
	}

	/**
	 * Handle the non-JS choice link.
	 */
	public function handle_choice_request() {
		if ( empty( $_GET['wfg_choose'] ) || empty( $_GET['wfg_nonce'] ) ) {
			return;
		}
		$raw = sanitize_text_field( wp_unslash( $_GET['wfg_choose'] ) );
		if ( ! preg_match( '/^(\d+)-(\d+)$/', $raw, $m ) ) {
			return;
		}
		$rule_id    = (int) $m[1];
		$product_id = (int) $m[2];
		$nonce      = sanitize_text_field( wp_unslash( $_GET['wfg_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'wfg-choose-' . $rule_id . '-' . $product_id ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'woo-free-gifts' ), 'error' );
		} else {
			$result = $this->cart->choose( $rule_id, $product_id );
			if ( is_wp_error( $result ) ) {
				wc_add_notice( $result->get_error_message(), 'error' );
			}
		}

		wp_safe_redirect( remove_query_arg( array( 'wfg_choose', 'wfg_nonce' ) ) );
		exit;
	}
}
