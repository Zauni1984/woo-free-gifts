<?php
/**
 * Gift teaser on the single product page.
 *
 * Override: yourtheme/woo-free-gifts/single-hint.php
 *
 * @var string $text Hint text.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="wfg-single-hint"><?php echo esc_html( $text ); ?></p>
