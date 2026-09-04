<?php
/**
 * Marketing list of all active gift offers ([wfg_gift_list]).
 *
 * Override: yourtheme/woo-free-gifts/gift-list.php
 *
 * @var array[] $offers rule, text, gifts (product, qty, custom).
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;
?>
<ul class="wfg-gift-list">
	<?php foreach ( $offers as $offer ) : ?>
		<li class="wfg-gift-list__item">
			<span class="wfg-gift-list__images">
				<?php foreach ( array_slice( $offer['gifts'], 0, 3 ) as $gift ) : ?>
					<?php echo wp_kses_post( WFG_Helpers::gift_image( $gift['product'] ) ); ?>
				<?php endforeach; ?>
			</span>
			<span class="wfg-gift-list__text"><?php echo esc_html( $offer['text'] ); ?></span>
		</li>
	<?php endforeach; ?>
</ul>
