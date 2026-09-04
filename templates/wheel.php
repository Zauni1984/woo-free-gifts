<?php
/**
 * Wheel of fortune popup.
 *
 * Override: yourtheme/woo-free-gifts/wheel.php
 *
 * @var string  $theme         stoner | classic
 * @var string  $accent        Accent colour.
 * @var string  $title         Title.
 * @var string  $content       Intro text (HTML allowed, sanitized).
 * @var string  $button        Spin button label.
 * @var array[] $segments      label, color, type.
 * @var bool    $require_email Ask guests for an e-mail address.
 * @var string  $email_label   E-mail field label.
 * @var string  $consent_text  Optional consent checkbox label.
 * @var array   $config        delay, segments, nextSpin, requireEmail, consent, version.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$wfg_count = max( 1, count( $segments ) );
$wfg_angle = 360 / $wfg_count;

// Conic gradient for the wheel face.
$wfg_stops = array();
foreach ( $segments as $i => $segment ) {
	$wfg_stops[] = sprintf( '%s %sdeg %sdeg', $segment['color'], round( $i * $wfg_angle, 3 ), round( ( $i + 1 ) * $wfg_angle, 3 ) );
}
$wfg_gradient = 'conic-gradient(from 0deg, ' . implode( ', ', $wfg_stops ) . ')';
?>
<div class="wfg-wheel wfg-wheel--<?php echo esc_attr( $theme ); ?>" id="wfg-wheel" hidden data-wfg-wheel="<?php echo esc_attr( wp_json_encode( $config ) ); ?>" style="--wfg-wheel-accent: <?php echo esc_attr( $accent ); ?>;">
	<div class="wfg-wheel__overlay" data-wfg-wheel-close></div>

	<?php if ( 'stoner' === $theme ) : ?>
		<div class="wfg-wheel__smoke" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
	<?php endif; ?>

	<div class="wfg-wheel__dialog" role="dialog" aria-modal="true" aria-labelledby="wfg-wheel-title" tabindex="-1">
		<button type="button" class="wfg-wheel__close" data-wfg-wheel-close aria-label="<?php esc_attr_e( 'Close', 'woo-free-gifts' ); ?>">&times;</button>

		<div class="wfg-wheel__intro">
			<h2 class="wfg-wheel__title" id="wfg-wheel-title"><?php echo esc_html( $title ); ?></h2>
			<?php if ( '' !== trim( $content ) ) : ?>
				<div class="wfg-wheel__content"><?php echo wp_kses_post( wpautop( $content ) ); ?></div>
			<?php endif; ?>
		</div>

		<div class="wfg-wheel__stage">
			<div class="wfg-wheel__pointer" aria-hidden="true"></div>
			<div class="wfg-wheel__disc" style="background: <?php echo esc_attr( $wfg_gradient ); ?>;">
				<?php foreach ( $segments as $i => $segment ) : ?>
					<span class="wfg-wheel__label wfg-wheel__label--<?php echo esc_attr( $segment['type'] ); ?>" style="transform: rotate(<?php echo esc_attr( round( $i * $wfg_angle + $wfg_angle / 2 - 90, 3 ) ); ?>deg);">
						<span><?php echo esc_html( $segment['label'] ); ?></span>
					</span>
				<?php endforeach; ?>
				<div class="wfg-wheel__hub" aria-hidden="true">
					<?php if ( 'stoner' === $theme ) : ?>
						<svg viewBox="0 0 100 100" class="wfg-wheel__leaf" focusable="false"><path fill="currentColor" d="M50 4c4 12 7 26 6 40 6-12 15-22 27-28-5 13-12 25-22 34 12-4 25-5 36-2-10 7-22 12-34 13 11 3 21 9 28 18-12-2-24-7-33-14 3 10 4 21 2 31l-6-8-4 8c-2-10-1-21 2-31-9 7-21 12-33 14 7-9 17-15 28-18C35 60 23 55 13 48c11-3 24-2 36 2-10-9-17-21-22-34 12 6 21 16 27 28-1-14 2-28 6-40z"/></svg>
					<?php else : ?>
						<span class="wfg-wheel__hub-dot"></span>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<form class="wfg-wheel__form" novalidate>
			<?php if ( $require_email ) : ?>
				<p class="wfg-wheel__field">
					<label for="wfg-wheel-email"><?php echo esc_html( $email_label ); ?></label>
					<input type="email" id="wfg-wheel-email" name="email" autocomplete="email" required placeholder="you@example.com">
				</p>
			<?php endif; ?>
			<?php if ( '' !== $consent_text ) : ?>
				<p class="wfg-wheel__field wfg-wheel__field--consent">
					<label><input type="checkbox" name="consent" value="1" required> <?php echo esc_html( $consent_text ); ?></label>
				</p>
			<?php endif; ?>
			<p class="wfg-wheel__actions">
				<button type="submit" class="wfg-wheel__spin"><?php echo esc_html( $button ); ?></button>
			</p>
			<p class="wfg-wheel__error" role="alert" hidden></p>
		</form>

		<div class="wfg-wheel__result" hidden aria-live="polite">
			<p class="wfg-wheel__result-label"></p>
			<p class="wfg-wheel__result-message"></p>
			<p class="wfg-wheel__result-code" hidden>
				<code></code>
				<button type="button" class="wfg-wheel__copy" data-copied="<?php esc_attr_e( 'Copied!', 'woo-free-gifts' ); ?>"><?php esc_html_e( 'Copy code', 'woo-free-gifts' ); ?></button>
			</p>
			<p class="wfg-wheel__result-actions">
				<a class="wfg-wheel__continue" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Keep shopping', 'woo-free-gifts' ); ?></a>
			</p>
		</div>
	</div>
</div>
