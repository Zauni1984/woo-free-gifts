<?php
/**
 * Admin view: wheel of fortune settings.
 *
 * @var WFG_Settings $settings Settings.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$opt      = $settings->all();
$segments = is_array( $opt['wheel_segments'] ) ? array_values( $opt['wheel_segments'] ) : array();
$currency = get_woocommerce_currency_symbol();

/**
 * Render one segment row.
 *
 * @param string|int $index   Row index or placeholder.
 * @param array      $segment Segment.
 * @param string     $currency Currency symbol.
 */
$wfg_render_segment = static function ( $index, array $segment, $currency ) {
	$name = 'wfg[wheel_segments][' . $index . ']';
	$type = isset( $segment['type'] ) ? $segment['type'] : 'none';
	?>
	<div class="wfg-segment" data-index="<?php echo esc_attr( $index ); ?>">
		<div class="wfg-segment__head">
			<span class="wfg-segment__num">#<span class="wfg-segment__index"></span></span>
			<input type="color" name="<?php echo esc_attr( $name ); ?>[color]" value="<?php echo esc_attr( isset( $segment['color'] ) ? $segment['color'] : '#2e7d32' ); ?>" class="wfg-segment-color" title="<?php esc_attr_e( 'Segment colour', 'woo-free-gifts' ); ?>">
			<input type="text" name="<?php echo esc_attr( $name ); ?>[label]" value="<?php echo esc_attr( isset( $segment['label'] ) ? $segment['label'] : '' ); ?>" class="regular-text wfg-segment-label" placeholder="<?php esc_attr_e( 'Label on the wheel, e.g. 10 % off', 'woo-free-gifts' ); ?>" required>
			<select name="<?php echo esc_attr( $name ); ?>[type]" class="wfg-segment-type">
				<option value="coupon" <?php selected( $type, 'coupon' ); ?>><?php esc_html_e( 'Coupon', 'woo-free-gifts' ); ?></option>
				<option value="gift" <?php selected( $type, 'gift' ); ?>><?php esc_html_e( 'Free gift', 'woo-free-gifts' ); ?></option>
				<option value="none" <?php selected( $type, 'none' ); ?>><?php esc_html_e( 'No prize', 'woo-free-gifts' ); ?></option>
			</select>
			<label class="wfg-segment__weight">
				<?php esc_html_e( 'Weight', 'woo-free-gifts' ); ?>
				<input type="number" name="<?php echo esc_attr( $name ); ?>[weight]" value="<?php echo esc_attr( isset( $segment['weight'] ) ? (int) $segment['weight'] : 10 ); ?>" min="0" max="1000" class="small-text wfg-segment-weight">
				<span class="wfg-segment__chance description"></span>
			</label>
			<button type="button" class="button-link-delete wfg-segment-remove"><?php esc_html_e( 'Remove', 'woo-free-gifts' ); ?></button>
		</div>
		<div class="wfg-segment__coupon wfg-inline-fields" <?php echo 'coupon' === $type ? '' : 'hidden'; ?>>
			<label>
				<select name="<?php echo esc_attr( $name ); ?>[coupon_type]">
					<option value="percent" <?php selected( isset( $segment['coupon_type'] ) ? $segment['coupon_type'] : 'percent', 'percent' ); ?>><?php esc_html_e( 'Percentage discount', 'woo-free-gifts' ); ?></option>
					<option value="fixed_cart" <?php selected( isset( $segment['coupon_type'] ) ? $segment['coupon_type'] : '', 'fixed_cart' ); ?>><?php echo esc_html( sprintf( /* translators: %s: currency symbol */ __( 'Fixed cart discount (%s)', 'woo-free-gifts' ), $currency ) ); ?></option>
				</select>
			</label>
			<label>
				<?php esc_html_e( 'Amount', 'woo-free-gifts' ); ?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[amount]" value="<?php echo esc_attr( isset( $segment['amount'] ) ? wc_format_localized_price( $segment['amount'] ) : '' ); ?>" class="small-text wc_input_price">
			</label>
			<label>
				<?php esc_html_e( 'or existing coupon code', 'woo-free-gifts' ); ?>
				<input type="text" name="<?php echo esc_attr( $name ); ?>[code]" value="<?php echo esc_attr( isset( $segment['code'] ) ? $segment['code'] : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'leave empty to generate unique codes', 'woo-free-gifts' ); ?>">
			</label>
		</div>
		<div class="wfg-segment__gift" <?php echo 'gift' === $type ? '' : 'hidden'; ?>>
			<select class="wc-product-search" style="width: 60%;" name="<?php echo esc_attr( $name ); ?>[product_id]" data-placeholder="<?php esc_attr_e( 'Search for the gift product…', 'woo-free-gifts' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude_type="variable,grouped,external" data-allow_clear="true">
				<?php
				if ( ! empty( $segment['product_id'] ) ) {
					echo WFG_Admin::product_options( array( (int) $segment['product_id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Added to the cart for free with the next order (as long as the prize is valid). Hidden custom gifts from your rules can be used as well.', 'woo-free-gifts' ); ?></p>
		</div>
	</div>
	<?php
};
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wfg-settings-form">
	<?php wp_nonce_field( 'wfg_save_settings' ); ?>
	<input type="hidden" name="action" value="wfg_save_settings">
	<input type="hidden" name="wfg_tab" value="wheel">

	<div class="wfg-card">
		<h2><?php esc_html_e( 'Wheel of fortune', 'woo-free-gifts' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Visitors spin once per cooldown window and win coupons or free gifts. The result is decided on the server and the cooldown is enforced via customer account, session, a signed cookie, the hashed IP address and the hashed e-mail address.', 'woo-free-gifts' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Wheel', 'woo-free-gifts' ); ?></th>
				<td><?php echo WFG_Admin::checkbox( 'wfg[wheel_enabled]', $opt['wheel_enabled'], __( 'Enable the wheel of fortune', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-wheel-theme"><?php esc_html_e( 'Style', 'woo-free-gifts' ); ?></label></th>
				<td class="wfg-inline-fields">
					<select id="wfg-wheel-theme" name="wfg[wheel_theme]">
						<option value="stoner" <?php selected( $opt['wheel_theme'], 'stoner' ); ?>><?php esc_html_e( '420 / stoner – dark green, neon glow, hemp leaf, drifting smoke', 'woo-free-gifts' ); ?></option>
						<option value="classic" <?php selected( $opt['wheel_theme'], 'classic' ); ?>><?php esc_html_e( 'Classic – clean white', 'woo-free-gifts' ); ?></option>
					</select>
					<label for="wfg-wheel-accent"><?php esc_html_e( 'Accent colour', 'woo-free-gifts' ); ?></label>
					<input type="text" id="wfg-wheel-accent" name="wfg[wheel_accent]" value="<?php echo esc_attr( $opt['wheel_accent'] ); ?>" class="wfg-color-field" data-default-color="#7CFF4D">
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show on', 'woo-free-gifts' ); ?></th>
				<td>
					<p><?php echo WFG_Admin::checkbox( 'wfg[wheel_on_single]', $opt['wheel_on_single'], __( 'Single product pages', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><?php echo WFG_Admin::checkbox( 'wfg[wheel_on_archive]', $opt['wheel_on_archive'], __( 'Shop page and product categories / tags', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><?php echo WFG_Admin::checkbox( 'wfg[wheel_on_cart]', $opt['wheel_on_cart'], __( 'Cart page', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><?php echo WFG_Admin::checkbox( 'wfg[wheel_on_other]', $opt['wheel_on_other'], __( 'All other pages (front page, blog, …)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p class="description"><?php esc_html_e( 'Never shown on the checkout. Visitors who close the wheel without spinning do not see it again during their browser session.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-wheel-cooldown"><?php esc_html_e( 'Spin frequency', 'woo-free-gifts' ); ?></label></th>
				<td class="wfg-inline-fields">
					<label><?php esc_html_e( 'One spin every', 'woo-free-gifts' ); ?> <input type="number" id="wfg-wheel-cooldown" name="wfg[wheel_cooldown_hours]" value="<?php echo esc_attr( (int) $opt['wheel_cooldown_hours'] ); ?>" min="1" max="8760" class="small-text"> <?php esc_html_e( 'hours', 'woo-free-gifts' ); ?></label>
					<label><?php esc_html_e( 'Delay (seconds)', 'woo-free-gifts' ); ?> <input type="number" name="wfg[wheel_delay]" value="<?php echo esc_attr( (int) $opt['wheel_delay'] ); ?>" min="0" max="120" class="small-text"></label>
					<?php echo WFG_Admin::checkbox( 'wfg[wheel_ip_check]', $opt['wheel_ip_check'], __( 'Also limit by (hashed) IP address', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'E-mail capture', 'woo-free-gifts' ); ?></th>
				<td>
					<p><?php echo WFG_Admin::checkbox( 'wfg[wheel_require_email]', $opt['wheel_require_email'], __( 'Guests must enter their e-mail address to spin (coupons are then bound to that address)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><input type="text" name="wfg[wheel_email_label]" value="<?php echo esc_attr( $opt['wheel_email_label'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'E-mail field label', 'woo-free-gifts' ); ?>"></p>
					<p><input type="text" name="wfg[wheel_consent_text]" value="<?php echo esc_attr( $opt['wheel_consent_text'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Optional consent checkbox, e.g. "I accept the terms and the privacy policy" (empty = no checkbox)', 'woo-free-gifts' ); ?>"></p>
				</td>
			</tr>
		</table>
	</div>

	<div class="wfg-card">
		<h2><?php esc_html_e( 'Texts', 'woo-free-gifts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wfg-wheel-title"><?php esc_html_e( 'Title', 'woo-free-gifts' ); ?></label></th>
				<td><input type="text" id="wfg-wheel-title" name="wfg[wheel_title]" value="<?php echo esc_attr( $opt['wheel_title'] ); ?>" class="large-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-wheel-content"><?php esc_html_e( 'Intro text', 'woo-free-gifts' ); ?></label></th>
				<td><textarea id="wfg-wheel-content" name="wfg[wheel_content]" rows="3" class="large-text"><?php echo esc_textarea( $opt['wheel_content'] ); ?></textarea></td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-wheel-button"><?php esc_html_e( 'Spin button', 'woo-free-gifts' ); ?></label></th>
				<td><input type="text" id="wfg-wheel-button" name="wfg[wheel_button]" value="<?php echo esc_attr( $opt['wheel_button'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Result messages', 'woo-free-gifts' ); ?></th>
				<td>
					<p><label><?php esc_html_e( 'Coupon won', 'woo-free-gifts' ); ?><input type="text" name="wfg[wheel_msg_win_coupon]" value="<?php echo esc_attr( $opt['wheel_msg_win_coupon'] ); ?>" class="large-text"></label></p>
					<p><label><?php esc_html_e( 'Gift won', 'woo-free-gifts' ); ?><input type="text" name="wfg[wheel_msg_win_gift]" value="<?php echo esc_attr( $opt['wheel_msg_win_gift'] ); ?>" class="large-text"></label></p>
					<p><label><?php esc_html_e( 'No prize', 'woo-free-gifts' ); ?><input type="text" name="wfg[wheel_msg_lose]" value="<?php echo esc_attr( $opt['wheel_msg_lose'] ); ?>" class="large-text"></label></p>
					<p><label><?php esc_html_e( 'Already spun', 'woo-free-gifts' ); ?><input type="text" name="wfg[wheel_msg_already]" value="<?php echo esc_attr( $opt['wheel_msg_already'] ); ?>" class="large-text"></label></p>
					<p class="description"><?php esc_html_e( 'Placeholders: {code}, {prize}, {days}, {hours}', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
		</table>
	</div>

	<div class="wfg-card">
		<h2><?php esc_html_e( 'Coupons & gifts', 'woo-free-gifts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="wfg-wheel-prefix"><?php esc_html_e( 'Coupon code prefix', 'woo-free-gifts' ); ?></label></th>
				<td class="wfg-inline-fields">
					<input type="text" id="wfg-wheel-prefix" name="wfg[wheel_coupon_prefix]" value="<?php echo esc_attr( $opt['wheel_coupon_prefix'] ); ?>" class="small-text" maxlength="12">
					<label><?php esc_html_e( 'Valid for', 'woo-free-gifts' ); ?> <input type="number" name="wfg[wheel_coupon_expiry_days]" value="<?php echo esc_attr( (int) $opt['wheel_coupon_expiry_days'] ); ?>" min="0" max="365" class="small-text"> <?php esc_html_e( 'days (0 = no expiry)', 'woo-free-gifts' ); ?></label>
					<label><?php esc_html_e( 'Minimum order', 'woo-free-gifts' ); ?> <input type="text" name="wfg[wheel_coupon_min_amount]" value="<?php echo esc_attr( $opt['wheel_coupon_min_amount'] > 0 ? wc_format_localized_price( $opt['wheel_coupon_min_amount'] ) : '' ); ?>" class="small-text wc_input_price" placeholder="0"> <?php echo esc_html( $currency ); ?></label>
					<p class="description"><?php esc_html_e( 'Generated coupons are single-use (one use, one per customer) and excluded from gift lines. They appear under Marketing → Coupons.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Apply automatically', 'woo-free-gifts' ); ?></th>
				<td><?php echo WFG_Admin::checkbox( 'wfg[wheel_auto_apply]', $opt['wheel_auto_apply'], __( 'Apply a won coupon to the current cart right away', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-wheel-gift-days"><?php esc_html_e( 'Gift validity', 'woo-free-gifts' ); ?></label></th>
				<td><input type="number" id="wfg-wheel-gift-days" name="wfg[wheel_gift_valid_days]" value="<?php echo esc_attr( (int) $opt['wheel_gift_valid_days'] ); ?>" min="1" max="365" class="small-text"> <?php esc_html_e( 'days – a won gift is added to the cart as soon as it contains paid items', 'woo-free-gifts' ); ?></td>
			</tr>
		</table>
	</div>

	<div class="wfg-card">
		<h2><?php esc_html_e( 'Segments', 'woo-free-gifts' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Between 2 and 12 segments. The weight sets the probability (weight ÷ sum of all weights). A gift segment whose product is out of stock is skipped automatically.', 'woo-free-gifts' ); ?></p>
		<div class="wfg-segments-wrap">
			<div class="wfg-segments">
				<?php
				foreach ( $segments as $i => $segment ) {
					$wfg_render_segment( $i, $segment, $currency ); }
				?>
			</div>
			<div class="wfg-segments-preview" aria-hidden="true"></div>
		</div>
		<p><button type="button" class="button wfg-segment-add"><?php esc_html_e( '+ Add segment', 'woo-free-gifts' ); ?></button></p>
	</div>

	<?php submit_button( __( 'Save wheel', 'woo-free-gifts' ) ); ?>
</form>

<script type="text/template" id="wfg-segment-template">
	<?php
	$wfg_render_segment(
		'__INDEX__',
		array(
			'type'        => 'coupon',
			'coupon_type' => 'percent',
			'weight'      => 10,
			'color'       => '#2e7d32',
		),
		$currency
	);
	?>
</script>
