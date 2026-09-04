<?php
/**
 * Progress bar, unlocked gifts and gift choice.
 *
 * Override: yourtheme/woo-free-gifts/progress-box.php
 *
 * @var string       $context  cart | checkout | minicart | shortcode
 * @var array|null   $next     Next threshold: rule, remaining, threshold, percent
 * @var array[]      $unlocked Unlocked rules
 * @var array[]      $choices  Rules with a gift choice: rule, gifts
 * @var float        $basis    Cart value used for thresholds
 * @var WFG_Settings $settings Settings
 * @var WFG_Engine   $engine   Engine
 * @var WFG_Cart     $cart     Cart integration
 * @var array        $messages progress (string), unlocked (string[])
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$has_content = ( $next && '' !== $messages['progress'] ) || ! empty( $messages['unlocked'] ) || ! empty( $choices );
if ( ! $has_content ) {
	return;
}
$compact = 'minicart' === $context;
?>
<div class="wfg-box wfg-box--<?php echo esc_attr( $context ); ?>" role="status" aria-live="polite">

	<?php if ( $next && '' !== $messages['progress'] ) : ?>
		<div class="wfg-progress">
			<p class="wfg-progress__text"><?php echo esc_html( $messages['progress'] ); ?></p>
			<div class="wfg-progress__bar" aria-hidden="true">
				<span class="wfg-progress__fill" style="width: <?php echo esc_attr( round( $next['percent'], 1 ) ); ?>%;"></span>
			</div>
			<?php if ( ! $compact ) : ?>
				<p class="wfg-progress__meta">
					<span><?php echo wp_kses_post( wc_price( $basis ) ); ?></span>
					<span><?php echo wp_kses_post( wc_price( $next['threshold'] ) ); ?></span>
				</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $messages['unlocked'] as $message ) : ?>
		<p class="wfg-unlocked"><?php echo esc_html( $message ); ?></p>
	<?php endforeach; ?>

	<?php if ( ! $compact ) : ?>
		<?php foreach ( $choices as $choice ) : ?>
			<?php
			$rule    = $choice['rule'];
			$current = $cart->chosen_gift( $rule['id'] );
			?>
			<div class="wfg-choice" data-rule="<?php echo esc_attr( $rule['id'] ); ?>">
				<p class="wfg-choice__title"><?php echo esc_html( $settings->get( 'msg_choose' ) ); ?></p>
				<ul class="wfg-choice__list">
					<?php foreach ( $choice['gifts'] as $gift ) : ?>
						<?php
						$product  = $gift['product'];
						$selected = $product->get_id() === $current;
						?>
						<li class="wfg-choice__item<?php echo $selected ? ' is-selected' : ''; ?>">
							<a class="wfg-choice__button" href="<?php echo esc_url( WFG_Frontend::choice_url( $rule['id'], $product->get_id() ) ); ?>" data-rule="<?php echo esc_attr( $rule['id'] ); ?>" data-product="<?php echo esc_attr( $product->get_id() ); ?>" aria-pressed="<?php echo $selected ? 'true' : 'false'; ?>">
								<span class="wfg-choice__image"><?php echo wp_kses_post( WFG_Helpers::gift_image( $product ) ); ?></span>
								<span class="wfg-choice__name"><?php echo esc_html( $product->get_name() ); ?></span>
								<?php if ( $gift['qty'] > 1 ) : ?>
									<span class="wfg-choice__qty"><?php echo esc_html( $gift['qty'] . '×' ); ?></span>
								<?php endif; ?>
								<span class="wfg-choice__state"><?php echo $selected ? esc_html__( 'Selected', 'woo-free-gifts' ) : esc_html__( 'Choose', 'woo-free-gifts' ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endforeach; ?>
	<?php elseif ( ! empty( $choices ) ) : ?>
		<p class="wfg-choice__hint"><a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php echo esc_html( $settings->get( 'msg_choose' ) ); ?></a></p>
	<?php endif; ?>

</div>
