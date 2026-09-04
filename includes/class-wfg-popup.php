<?php
/**
 * One-time promotional popup on product pages and product archives.
 *
 * Rendering is cache-friendly: the markup is always printed on the relevant pages and
 * the browser (localStorage/sessionStorage) decides whether it was already shown.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Popup
 */
final class WFG_Popup {

	/**
	 * Settings.
	 *
	 * @var WFG_Settings
	 */
	private $settings;

	/**
	 * Rules.
	 *
	 * @var WFG_Rules
	 */
	private $rules;

	/**
	 * Engine.
	 *
	 * @var WFG_Engine
	 */
	private $engine;

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings $settings Settings.
	 * @param WFG_Rules    $rules    Rules.
	 * @param WFG_Engine   $engine   Engine.
	 */
	public function __construct( WFG_Settings $settings, WFG_Rules $rules, WFG_Engine $engine ) {
		$this->settings = $settings;
		$this->rules    = $rules;
		$this->engine   = $engine;

		add_action( 'wp_footer', array( $this, 'output' ), 50 );
	}

	/**
	 * Should the popup markup be printed on this request?
	 *
	 * @return bool
	 */
	public function should_render() {
		if ( ! $this->settings->enabled() || ! $this->settings->is( 'popup_enabled' ) ) {
			return false;
		}
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_404() ) {
			return false;
		}
		if ( ! function_exists( 'is_product' ) ) {
			return false;
		}
		$single  = $this->settings->is( 'popup_on_single' ) && is_product();
		$archive = $this->settings->is( 'popup_on_archive' ) && ( is_shop() || is_product_taxonomy() );

		/**
		 * Filter whether the popup markup is printed on the current page.
		 *
		 * @param bool $render Render.
		 */
		return (bool) apply_filters( 'wfg_popup_should_render', $single || $archive );
	}

	/**
	 * Rules listed inside the popup.
	 *
	 * @return array[] Each: title, text, gifts (WC_Product[]), threshold.
	 */
	public function offers() {
		$offers = array();
		foreach ( $this->rules->active() as $rule ) {
			if ( empty( $rule['show_in_popup'] ) ) {
				continue;
			}
			$gifts = $this->engine->available_gifts( $rule );
			if ( empty( $gifts ) ) {
				continue;
			}
			$text = $rule['popup_text'];
			if ( '' === $text ) {
				$text = $this->default_offer_text( $rule );
			}
			$offers[] = array(
				'rule'  => $rule,
				'text'  => $text,
				'gifts' => $gifts,
			);
		}
		return $offers;
	}

	/**
	 * Auto-generated one-liner for a rule.
	 *
	 * @param array $rule Rule.
	 * @return string
	 */
	private function default_offer_text( array $rule ) {
		$names = $this->engine->gift_names( $rule );
		if ( (float) $rule['min_total'] > 0 ) {
			return sprintf(
				/* translators: 1: gift name(s), 2: formatted amount */
				__( '%1$s for free from %2$s order value', 'woo-free-gifts' ),
				$names,
				WFG_Helpers::price_text( $rule['min_total'] )
			);
		}
		$conditions = WFG_Rules::describe_conditions( $rule );
		return sprintf(
			/* translators: 1: gift name(s), 2: condition description */
			__( '%1$s for free – %2$s', 'woo-free-gifts' ),
			$names,
			implode( ', ', $conditions )
		);
	}

	/**
	 * Print the popup markup + config.
	 */
	public function output() {
		if ( ! $this->should_render() ) {
			return;
		}

		$offers = $this->settings->is( 'popup_show_gifts' ) ? $this->offers() : array();
		if ( empty( $offers ) && '' === trim( wp_strip_all_tags( $this->settings->get( 'popup_content' ) ) ) ) {
			return;
		}

		$config = array(
			'frequency' => $this->settings->get( 'popup_frequency' ),
			'days'      => (int) $this->settings->get( 'popup_days' ),
			'delay'     => (int) $this->settings->get( 'popup_delay' ),
			// Changing the popup content re-shows it to visitors who dismissed an older version.
			'version'   => substr( md5( wp_json_encode( array( $this->settings->get( 'popup_title' ), $this->settings->get( 'popup_content' ), wp_list_pluck( $offers, 'text' ) ) ) ), 0, 10 ),
		);

		$image = '';
		$img   = (int) $this->settings->get( 'popup_image_id' );
		if ( $img ) {
			$image = wp_get_attachment_image(
				$img,
				'large',
				false,
				array(
					'class'   => 'wfg-popup__image',
					'loading' => 'lazy',
				)
			);
		}

		$button_url = $this->settings->get( 'popup_button_url' );

		$html = WFG_Helpers::template(
			'popup',
			array(
				'title'      => $this->settings->get( 'popup_title' ),
				'content'    => $this->settings->get( 'popup_content' ),
				'button'     => $this->settings->get( 'popup_button' ),
				'button_url' => $button_url ? $button_url : '',
				'image'      => $image,
				'offers'     => $offers,
				'config'     => $config,
			)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its output.
	}
}
