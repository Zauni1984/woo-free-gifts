<?php
/**
 * Admin view: global settings.
 *
 * @var WFG_Settings $settings Settings.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$opt = $settings->all();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wfg-settings-form">
	<?php wp_nonce_field( 'wfg_save_settings' ); ?>
	<input type="hidden" name="action" value="wfg_save_settings">
	<input type="hidden" name="wfg_tab" value="settings">

	<div class="wfg-card">
		<h2><?php esc_html_e( 'General', 'woo-free-gifts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Free gifts', 'woo-free-gifts' ); ?></th>
				<td><?php echo WFG_Admin::checkbox( 'wfg[enabled]', $opt['enabled'], __( 'Enable the gift engine', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Cart value basis', 'woo-free-gifts' ); ?></th>
				<td>
					<select name="wfg[threshold_basis]">
						<option value="subtotal_excl_tax" <?php selected( $opt['threshold_basis'], 'subtotal_excl_tax' ); ?>><?php esc_html_e( 'Subtotal excluding tax', 'woo-free-gifts' ); ?></option>
						<option value="subtotal_incl_tax" <?php selected( $opt['threshold_basis'], 'subtotal_incl_tax' ); ?>><?php esc_html_e( 'Subtotal including tax', 'woo-free-gifts' ); ?></option>
					</select>
					<p><?php echo WFG_Admin::checkbox( 'wfg[after_discounts]', $opt['after_discounts'], __( 'Deduct coupon discounts before comparing with the thresholds', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p class="description"><?php esc_html_e( 'Gifts themselves never count towards the cart value.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Stacking', 'woo-free-gifts' ); ?></th>
				<td>
					<select name="wfg[stacking]">
						<option value="all" <?php selected( $opt['stacking'], 'all' ); ?>><?php esc_html_e( 'Every qualifying rule adds its gifts (50 € seed + 100 € book = both)', 'woo-free-gifts' ); ?></option>
						<option value="highest" <?php selected( $opt['stacking'], 'highest' ); ?>><?php esc_html_e( 'Only the qualifying rule with the highest priority / threshold', 'woo-free-gifts' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Customer control', 'woo-free-gifts' ); ?></th>
				<td>
					<?php echo WFG_Admin::checkbox( 'wfg[allow_remove]', $opt['allow_remove'], __( 'Customers may remove a gift from the cart (it is not re-added until the rule is lost and regained)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bundles', 'woo-free-gifts' ); ?></th>
				<td>
					<?php echo WFG_Admin::checkbox( 'wfg[count_bundled_items]', $opt['count_bundled_items'], __( 'Products inside bundles count for "Required products" and "Required categories"', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Custom gifts', 'woo-free-gifts' ); ?></th>
				<td>
					<?php echo WFG_Admin::checkbox( 'wfg[custom_gift_virtual]', $opt['custom_gift_virtual'], __( 'New custom gifts are virtual by default (no shipping)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: link to product list */
							esc_html__( 'Hidden gift products are excluded from the product list. %s', 'woo-free-gifts' ),
							'<a href="' . esc_url( admin_url( 'edit.php?post_type=product&wfg_show_gifts=1' ) ) . '">' . esc_html__( 'Show them', 'woo-free-gifts' ) . '</a>'
						);
						?>
					</p>
				</td>
			</tr>
		</table>
	</div>

	<div class="wfg-card">
		<h2><?php esc_html_e( 'Cart & product page', 'woo-free-gifts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Show progress bar', 'woo-free-gifts' ); ?></th>
				<td>
					<p><?php echo WFG_Admin::checkbox( 'wfg[show_progress_cart]', $opt['show_progress_cart'], __( 'Cart page', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><?php echo WFG_Admin::checkbox( 'wfg[show_progress_checkout]', $opt['show_progress_checkout'], __( 'Checkout page', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><?php echo WFG_Admin::checkbox( 'wfg[show_progress_minicart]', $opt['show_progress_minicart'], __( 'Mini cart (widget / header cart)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p><?php echo WFG_Admin::checkbox( 'wfg[show_single_hint]', $opt['show_single_hint'], __( 'Gift hint on single product pages', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
					<p class="description"><?php esc_html_e( 'Block-based cart/checkout pages: use the [wfg_progress] shortcode in a Shortcode block above the cart.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-msg-progress"><?php esc_html_e( 'Progress message', 'woo-free-gifts' ); ?></label></th>
				<td>
					<input type="text" id="wfg-msg-progress" name="wfg[msg_progress]" value="<?php echo esc_attr( $opt['msg_progress'] ); ?>" class="large-text">
					<p class="description"><?php esc_html_e( 'Placeholders: {remaining}, {threshold}, {gift}, {left}', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-msg-unlocked"><?php esc_html_e( 'Unlocked message', 'woo-free-gifts' ); ?></label></th>
				<td>
					<input type="text" id="wfg-msg-unlocked" name="wfg[msg_unlocked]" value="<?php echo esc_attr( $opt['msg_unlocked'] ); ?>" class="large-text">
					<p class="description"><?php esc_html_e( 'Placeholders: {threshold}, {gift}. Also shown as a notice when a gift is added.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-msg-choose"><?php esc_html_e( 'Gift choice title', 'woo-free-gifts' ); ?></label></th>
				<td><input type="text" id="wfg-msg-choose" name="wfg[msg_choose]" value="<?php echo esc_attr( $opt['msg_choose'] ); ?>" class="large-text"></td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-msg-single"><?php esc_html_e( 'Product page hint', 'woo-free-gifts' ); ?></label></th>
				<td>
					<input type="text" id="wfg-msg-single" name="wfg[msg_single_hint]" value="<?php echo esc_attr( $opt['msg_single_hint'] ); ?>" class="large-text">
					<p class="description"><?php esc_html_e( 'Uses the cart-value rule with the lowest threshold. Placeholders: {threshold}, {gift}', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-badge"><?php esc_html_e( 'Gift badge', 'woo-free-gifts' ); ?></label></th>
				<td>
					<input type="text" id="wfg-badge" name="wfg[gift_badge]" value="<?php echo esc_attr( $opt['gift_badge'] ); ?>" class="regular-text">
					<label for="wfg-price-label" class="wfg-ml"><?php esc_html_e( 'Price label', 'woo-free-gifts' ); ?></label>
					<input type="text" id="wfg-price-label" name="wfg[gift_price_label]" value="<?php echo esc_attr( $opt['gift_price_label'] ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Shown next to gift lines in cart, checkout, e-mails and orders.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-msg-scarcity"><?php esc_html_e( 'Scarcity line', 'woo-free-gifts' ); ?></label></th>
				<td class="wfg-inline-fields">
					<input type="text" id="wfg-msg-scarcity" name="wfg[msg_scarcity]" value="<?php echo esc_attr( $opt['msg_scarcity'] ); ?>" class="large-text">
					<label><?php esc_html_e( 'Show when at most', 'woo-free-gifts' ); ?> <input type="number" name="wfg[scarcity_threshold]" value="<?php echo esc_attr( (int) $opt['scarcity_threshold'] ); ?>" min="0" max="100000" class="small-text"> <?php esc_html_e( 'units are left (0 = always)', 'woo-free-gifts' ); ?></label>
					<p class="description"><?php esc_html_e( 'Shown under the progress bar and in the popup for rules with a gift stock or an order budget. Placeholder: {left}. Leave empty to disable.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-low-stock"><?php esc_html_e( 'Low stock warning', 'woo-free-gifts' ); ?></label></th>
				<td>
					<input type="number" id="wfg-low-stock" name="wfg[low_stock_threshold]" value="<?php echo esc_attr( (int) $opt['low_stock_threshold'] ); ?>" min="0" max="100000" class="small-text">
					<p class="description"><?php esc_html_e( 'Warn on the Free Gifts admin pages when a gift has this many units (or fewer) left.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="wfg-color"><?php esc_html_e( 'Accent colour', 'woo-free-gifts' ); ?></label></th>
				<td><input type="text" id="wfg-color" name="wfg[progress_color]" value="<?php echo esc_attr( $opt['progress_color'] ); ?>" class="wfg-color-field" data-default-color="#2e7d32"></td>
			</tr>
		</table>
	</div>

	<div class="wfg-card">
		<h2><?php esc_html_e( 'Maintenance', 'woo-free-gifts' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Debug log', 'woo-free-gifts' ); ?></th>
				<td>
					<?php echo WFG_Admin::checkbox( 'wfg[debug_log]', $opt['debug_log'], __( 'Write gift decisions to WooCommerce → Status → Logs (source: woo-free-gifts)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Updates', 'woo-free-gifts' ); ?></th>
				<td>
					<p>
						<?php
						printf(
							/* translators: 1: installed version, 2: repository link */
							esc_html__( 'Installed version %1$s. Updates are pulled from the GitHub releases of %2$s and show up under Plugins like any other update.', 'woo-free-gifts' ),
							'<strong>' . esc_html( WFG_VERSION ) . '</strong>',
							'<a href="https://github.com/Zauni1984/woo-free-gifts/releases" target="_blank" rel="noopener">Zauni1984/woo-free-gifts</a>'
						);
						?>
						<a href="<?php echo esc_url( WFG_Admin::action_url( 'wfg_check_updates' ) ); ?>" class="button"><?php esc_html_e( 'Check for updates now', 'woo-free-gifts' ); ?></a>
					</p>
					<p>
						<label for="wfg-update-token"><?php esc_html_e( 'GitHub token (only for a private repository)', 'woo-free-gifts' ); ?></label><br>
						<input type="password" id="wfg-update-token" name="wfg[update_token]" value="<?php echo esc_attr( $opt['update_token'] ); ?>" class="regular-text" autocomplete="off">
					</p>
					<p class="description"><?php esc_html_e( 'A fine-grained token with read access to the repository contents. Alternatively define WFG_GITHUB_TOKEN in wp-config.php.', 'woo-free-gifts' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Uninstall', 'woo-free-gifts' ); ?></th>
				<td>
					<?php echo WFG_Admin::checkbox( 'wfg[delete_data_on_uninstall]', $opt['delete_data_on_uninstall'], __( 'Delete all rules, settings, statistics and hidden gift products when the plugin is deleted', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</td>
			</tr>
		</table>
	</div>

	<?php submit_button( __( 'Save settings', 'woo-free-gifts' ) ); ?>
</form>
