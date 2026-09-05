<?php
/**
 * Promo popup.
 *
 * Override: yourtheme/woo-free-gifts/popup.php
 *
 * @var string  $title      Title.
 * @var string  $content    Content (HTML allowed, already sanitized with wp_kses_post).
 * @var string  $button     Button label.
 * @var string  $button_url Optional button URL (empty = just close).
 * @var string  $image      Image HTML (may be empty).
 * @var array[] $offers     rule, text, gifts (product, qty, custom).
 * @var array   $config     frequency, days, delay, version.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wfg-popup" id="wfg-popup" hidden data-wfg-popup="<?php echo esc_attr( wp_json_encode( $config ) ); ?>">
	<div class="wfg-popup__overlay" data-wfg-close></div>
	<div class="wfg-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="wfg-popup-title" tabindex="-1">
		<button type="button" class="wfg-popup__close" data-wfg-close aria-label="<?php esc_attr_e( 'Close', 'woo-free-gifts' ); ?>">&times;</button>

		<?php if ( $image ) : ?>
			<div class="wfg-popup__media"><?php echo wp_kses_post( $image ); ?></div>
		<?php endif; ?>

		<div class="wfg-popup__body">
			<?php if ( '' !== $title ) : ?>
				<h2 class="wfg-popup__title" id="wfg-popup-title"><?php echo esc_html( $title ); ?></h2>
			<?php endif; ?>

			<?php if ( '' !== trim( $content ) ) : ?>
				<div class="wfg-popup__content"><?php echo wp_kses_post( wpautop( $content ) ); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $offers ) ) : ?>
				<ul class="wfg-popup__offers">
					<?php foreach ( $offers as $offer ) : ?>
						<li class="wfg-popup__offer">
							<span class="wfg-popup__offer-images">
								<?php foreach ( array_slice( $offer['gifts'], 0, 3 ) as $gift ) : ?>
									<?php echo wp_kses_post( WFG_Helpers::gift_image( $gift['product'] ) ); ?>
								<?php endforeach; ?>
							</span>
							<span class="wfg-popup__offer-text"><?php echo esc_html( $offer['text'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( '' !== $button ) : ?>
				<p class="wfg-popup__actions">
					<?php if ( '' !== $button_url ) : ?>
						<a class="wfg-popup__button button" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( $button ); ?></a>
					<?php else : ?>
						<button type="button" class="wfg-popup__button button" data-wfg-close><?php echo esc_html( $button ); ?></button>
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</div>
	</div>
</div>
