<?php
/**
 * Small static helpers.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Helpers
 */
final class WFG_Helpers {

	/**
	 * Replace {placeholders} in a message.
	 *
	 * @param string $message Message with placeholders.
	 * @param array  $values  key => replacement (plain text).
	 * @return string
	 */
	public static function placeholders( $message, array $values ) {
		$search  = array();
		$replace = array();
		foreach ( $values as $key => $value ) {
			$search[]  = '{' . $key . '}';
			$replace[] = (string) $value;
		}
		return str_replace( $search, $replace, (string) $message );
	}

	/**
	 * Load a frontend template, overridable in the theme under `woo-free-gifts/<name>.php`.
	 *
	 * @param string $name Template file name (without .php).
	 * @param array  $args Variables for the template.
	 * @return string Rendered HTML.
	 */
	public static function template( $name, array $args = array() ) {
		ob_start();
		wc_get_template( $name . '.php', $args, 'woo-free-gifts/', WFG_PLUGIN_DIR . 'templates/' );
		return (string) ob_get_clean();
	}

	/**
	 * Thumbnail HTML for a gift product (falls back to the placeholder).
	 *
	 * @param WC_Product $product Product.
	 * @param string     $size    Image size.
	 * @return string
	 */
	public static function gift_image( WC_Product $product, $size = 'woocommerce_gallery_thumbnail' ) {
		$html = $product->get_image(
			$size,
			array(
				'class'   => 'wfg-gift-image',
				'loading' => 'lazy',
			)
		);
		return $html ? $html : wc_placeholder_img( $size );
	}

	/**
	 * Formatted price without HTML for use in plain text.
	 *
	 * @param float $amount Amount.
	 * @return string
	 */
	public static function price_text( $amount ) {
		return html_entity_decode( wp_strip_all_tags( wc_price( (float) $amount ) ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * "Only X left" line, or empty when unlimited / above the configured threshold.
	 *
	 * @param WFG_Settings $settings Settings.
	 * @param int|null     $left     Units left (null = unlimited).
	 * @return string
	 */
	public static function scarcity_text( WFG_Settings $settings, $left ) {
		if ( null === $left ) {
			return '';
		}
		$threshold = (int) $settings->get( 'scarcity_threshold' );
		if ( $threshold > 0 && (int) $left > $threshold ) {
			return '';
		}
		$template = (string) $settings->get( 'msg_scarcity' );
		return '' === trim( $template ) ? '' : self::placeholders( $template, array( 'left' => (int) $left ) );
	}

	/**
	 * Is the current request from the WP admin (not AJAX)?
	 *
	 * @return bool
	 */
	public static function is_admin_screen() {
		return is_admin() && ! wp_doing_ajax();
	}
}
