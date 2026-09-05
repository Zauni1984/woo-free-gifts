<?php
/**
 * Admin view: popup settings.
 *
 * @var WFG_Settings $settings Settings.
 * @var WFG_Rules    $rules    Rules.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$opt       = $settings->all();
$image_src = $opt['popup_image_id'] ? wp_get_attachment_image_url( (int) $opt['popup_image_id'], 'medium' ) : '';
$in_popup  = 0;
foreach ( $rules->active() as $r ) {
	if ( ! empty( $r['show_in_popup'] ) ) {
		++$in_popup;
	}
}
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wfg-settings-form">
	<?php wp_nonce_field( 'wfg_save_settings' ); ?>
	<input type="hidden" name="action" value="wfg_save_settings">
	<input type="hidden" name="wfg_tab" value="popup">

	<div class="wfg-card">
		<h2><?php esc_html_e( 'Promo popup', 'woo-free-gifts' ); ?></h2>
		<p class="description"><?php esc_html_e( 'A lightweight, cache-friendly popup that tells visitors about your gift offers. It is shown once per visitor (configurable) on product pages and product archives.', 'woo-free-gifts' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Popup', 'woo-free-gifts' ); ?></th>
				<td><?php echo WFG_Admin::checkbox( 'wfg[popup_enabled]', $opt['popup_enabled'], __( 'Enable the popup', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Show on', 'woo-free-gifts' ); ?></th>
				<td>
					<p><?php echo WFG_Admin::checkbox( 'wfg[popup_on_single]', $opt['popup_on_single'], __( 'Single product pages', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><?php echo WFG_Admin::checkbox( 'wfg[popup_on_archive]', $opt['popup_on_archive'], __( 'Shop page and product categories / tags', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-popup-frequency"><?php esc_html_e( 'Frequency', 'woo-free-gifts' ); ?></label></th>
				<td class="wfg-inline-fields">
					<select id="wfg-popup-frequency" name="wfg[popup_frequency]">
						<option value="session" <?php selected( $opt['popup_frequency'], 'session' ); ?>><?php esc_html_e( 'Once per browser session', 'woo-free-gifts' ); ?></option>
						<option value="days" <?php selected( $opt['popup_frequency'], 'days' ); ?>><?php esc_html_e( 'Once every X days', 'woo-free-gifts' ); ?></option>
						<option value="once" <?php selected( $opt['popup_frequency'], 'once' ); ?>><?php esc_html_e( 'Only once per visitor', 'woo-free-gifts' ); ?></option>
						<option value="always" <?php selected( $opt['popup_frequency'], 'always' ); ?>><?php esc_html_e( 'On every page view (testing)', 'woo-free-gifts' ); ?></option>
					</select>
					<label><?php esc_html_e( 'Days', 'woo-free-gifts' ); ?> <input type="number" name="wfg[popup_days]" value="<?php echo esc_attr( (int) $opt['popup_days'] ); ?>" min="1" max="365" class="small-text"></label>
					<label><?php esc_html_e( 'Delay (seconds)', 'woo-free-gifts' ); ?> <input type="number" name="wfg[popup_delay]" value="<?php echo esc_attr( (int) $opt['popup_delay'] ); ?>" min="0" max="120" class="small-text"></label>
					<p class="description"><?php esc_html_e( 'Changing title, text or offers shows the popup again to visitors who already dismissed it.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-popup-title"><?php esc_html_e( 'Title', 'woo-free-gifts' ); ?></label></th>
				<td><input type="text" id="wfg-popup-title" name="wfg[popup_title]" value="<?php echo esc_attr( $opt['popup_title'] ); ?>" class="large-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-popup-content"><?php esc_html_e( 'Text', 'woo-free-gifts' ); ?></label></th>
				<td>
					<?php
					wp_editor(
						$opt['popup_content'],
						'wfg-popup-content',
						array(
							'textarea_name' => 'wfg[popup_content]',
							'textarea_rows' => 6,
							'media_buttons' => false,
							'teeny'         => true,
						)
					);
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Offers', 'woo-free-gifts' ); ?></th>
				<td>
					<?php echo WFG_Admin::checkbox( 'wfg[popup_show_gifts]', $opt['popup_show_gifts'], __( 'List the active gift offers with images', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p class="description">
						<?php
						printf(
							/* translators: %d: number of rules */
							esc_html( _n( '%d active rule is flagged for the popup.', '%d active rules are flagged for the popup.', $in_popup, 'woo-free-gifts' ) ),
							(int) $in_popup
						);
						?>
						<?php esc_html_e( 'You control this per rule under "Messages & popup".', 'woo-free-gifts' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Image', 'woo-free-gifts' ); ?></th>
				<td>
					<div class="wfg-custom-image wfg-custom-image--wide">
						<input type="hidden" name="wfg[popup_image_id]" value="<?php echo esc_attr( (int) $opt['popup_image_id'] ); ?>" class="wfg-image-id">
						<div class="wfg-image-preview">
							<?php if ( $image_src ) : ?>
								<img src="<?php echo esc_url( $image_src ); ?>" alt="">
							<?php else : ?>
								<span class="dashicons dashicons-format-image"></span>
							<?php endif; ?>
						</div>
						<button type="button" class="button wfg-image-pick"><?php esc_html_e( 'Choose image', 'woo-free-gifts' ); ?></button>
						<button type="button" class="button-link wfg-image-clear" <?php echo $image_src ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove image', 'woo-free-gifts' ); ?></button>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-popup-button"><?php esc_html_e( 'Button', 'woo-free-gifts' ); ?></label></th>
				<td class="wfg-inline-fields">
					<input type="text" id="wfg-popup-button" name="wfg[popup_button]" value="<?php echo esc_attr( $opt['popup_button'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Button label', 'woo-free-gifts' ); ?>">
					<input type="url" name="wfg[popup_button_url]" value="<?php echo esc_attr( $opt['popup_button_url'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Optional link (empty = just close)', 'woo-free-gifts' ); ?>">
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-popup-accent"><?php esc_html_e( 'Accent colour', 'woo-free-gifts' ); ?></label></th>
				<td><input type="text" id="wfg-popup-accent" name="wfg[popup_accent]" value="<?php echo esc_attr( $opt['popup_accent'] ); ?>" class="wfg-color-field" data-default-color="#2e7d32"></td>
			</tr>
		</table>
	</div>

	<?php submit_button( __( 'Save popup', 'woo-free-gifts' ) ); ?>
</form>
