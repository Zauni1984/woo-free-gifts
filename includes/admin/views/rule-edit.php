<?php
/**
 * Admin view: rule editor.
 *
 * @var WFG_Settings $settings Settings.
 * @var WFG_Rules    $rules    Rules.
 * @var array        $rule     Rule being edited (defaults for a new rule).
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$is_new     = empty( $rule['id'] );
$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
		'number'     => 500,
	)
);
$categories = is_wp_error( $categories ) ? array() : $categories;
$roles      = wp_roles()->roles;

/**
 * Render a gift repeater row.
 *
 * @param string|int $index Row index or placeholder.
 * @param array      $gift  Gift row: type, product_id, qty, custom_id, custom_name, custom_desc, custom_image_id, custom_weight, custom_virtual.
 */
$wfg_render_gift_row = static function ( $index, array $gift ) use ( $settings ) {
	$type      = isset( $gift['type'] ) && 'custom' === $gift['type'] ? 'custom' : 'product';
	$name      = 'gifts[' . $index . ']';
	$image_id  = isset( $gift['custom_image_id'] ) ? (int) $gift['custom_image_id'] : 0;
	$image_src = $image_id ? wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
	?>
	<div class="wfg-gift-row" data-index="<?php echo esc_attr( $index ); ?>">
		<div class="wfg-gift-row__head">
			<span class="wfg-gift-row__handle dashicons dashicons-menu" aria-hidden="true"></span>
			<label>
				<span class="screen-reader-text"><?php esc_html_e( 'Gift type', 'woo-free-gifts' ); ?></span>
				<select name="<?php echo esc_attr( $name ); ?>[type]" class="wfg-gift-type">
					<option value="product" <?php selected( $type, 'product' ); ?>><?php esc_html_e( 'Product from the catalog', 'woo-free-gifts' ); ?></option>
					<option value="custom" <?php selected( $type, 'custom' ); ?>><?php esc_html_e( 'Custom gift (not listed in the shop)', 'woo-free-gifts' ); ?></option>
				</select>
			</label>
			<label class="wfg-gift-qty">
				<?php esc_html_e( 'Qty', 'woo-free-gifts' ); ?>
				<input type="number" name="<?php echo esc_attr( $name ); ?>[qty]" value="<?php echo esc_attr( isset( $gift['qty'] ) ? max( 1, (int) $gift['qty'] ) : 1 ); ?>" min="1" max="99" step="1" class="small-text">
			</label>
			<button type="button" class="button-link-delete wfg-gift-remove"><?php esc_html_e( 'Remove', 'woo-free-gifts' ); ?></button>
		</div>

		<div class="wfg-gift-row__product" <?php echo 'product' === $type ? '' : 'hidden'; ?>>
			<select class="wc-product-search wfg-product-select" style="width: 100%;" name="<?php echo esc_attr( $name ); ?>[product_id]" data-placeholder="<?php esc_attr_e( 'Search for a product or variation…', 'woo-free-gifts' ); ?>" data-action="woocommerce_json_search_products_and_variations" data-exclude_type="variable,grouped,external" data-allow_clear="true">
				<?php
				if ( 'product' === $type && ! empty( $gift['product_id'] ) ) {
					echo WFG_Admin::product_options( array( (int) $gift['product_id'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in helper.
				}
				?>
			</select>
			<p class="description"><?php esc_html_e( 'Variable products: pick the exact variation. Stock is respected – an out-of-stock gift is skipped automatically.', 'woo-free-gifts' ); ?></p>
		</div>

		<div class="wfg-gift-row__custom" <?php echo 'custom' === $type ? '' : 'hidden'; ?>>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>[custom_id]" value="<?php echo esc_attr( isset( $gift['custom_id'] ) ? (int) $gift['custom_id'] : 0 ); ?>">
			<div class="wfg-custom-grid">
				<div class="wfg-custom-image">
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>[custom_image_id]" value="<?php echo esc_attr( $image_id ); ?>" class="wfg-image-id">
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
				<div class="wfg-custom-fields">
					<p>
						<label>
							<strong><?php esc_html_e( 'Gift name', 'woo-free-gifts' ); ?></strong>
							<input type="text" name="<?php echo esc_attr( $name ); ?>[custom_name]" value="<?php echo esc_attr( isset( $gift['custom_name'] ) ? $gift['custom_name'] : '' ); ?>" class="regular-text wfg-custom-name" placeholder="<?php esc_attr_e( 'e.g. Free seed sample', 'woo-free-gifts' ); ?>">
						</label>
					</p>
					<p>
						<label>
							<?php esc_html_e( 'Description (optional)', 'woo-free-gifts' ); ?>
							<textarea name="<?php echo esc_attr( $name ); ?>[custom_desc]" rows="2" class="large-text"><?php echo esc_textarea( isset( $gift['custom_desc'] ) ? $gift['custom_desc'] : '' ); ?></textarea>
						</label>
					</p>
					<p class="wfg-inline-fields">
						<label>
							<?php echo esc_html( sprintf( /* translators: %s: weight unit */ __( 'Weight (%s)', 'woo-free-gifts' ), get_option( 'woocommerce_weight_unit', 'kg' ) ) ); ?>
							<input type="text" name="<?php echo esc_attr( $name ); ?>[custom_weight]" value="<?php echo esc_attr( isset( $gift['custom_weight'] ) ? $gift['custom_weight'] : '' ); ?>" class="small-text" placeholder="0">
						</label>
						<?php
						$virtual = isset( $gift['custom_virtual'] ) ? (bool) $gift['custom_virtual'] : $settings->is( 'custom_gift_virtual' );
						echo WFG_Admin::checkbox( $name . '[custom_virtual]', $virtual, __( 'Virtual (no shipping)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						?>
						<label>
							<?php esc_html_e( 'Stock', 'woo-free-gifts' ); ?>
							<input type="number" name="<?php echo esc_attr( $name ); ?>[custom_stock]" value="<?php echo esc_attr( isset( $gift['custom_stock'] ) ? $gift['custom_stock'] : '' ); ?>" min="0" step="1" class="small-text" placeholder="∞">
						</label>
					</p>
					<p class="description"><?php esc_html_e( 'Stock: number of units available (empty = unlimited). WooCommerce deducts one unit per order and restocks on cancellation; when the stock hits zero the gift is skipped automatically.', 'woo-free-gifts' ); ?></p>
					<p class="description"><?php esc_html_e( 'A hidden product is created for this gift. It never appears in the shop, search, feeds or sitemaps and cannot be bought separately.', 'woo-free-gifts' ); ?></p>
				</div>
			</div>
		</div>
	</div>
	<?php
};

// Prepare existing gift rows for the form.
$gift_rows = array();
foreach ( (array) $rule['gifts'] as $gift ) {
	$product = wc_get_product( $gift['product_id'] );
	if ( ! empty( $gift['custom'] ) ) {
		$gift_rows[] = array(
			'type'            => 'custom',
			'qty'             => $gift['qty'],
			'custom_id'       => $gift['product_id'],
			'custom_name'     => $product ? $product->get_name() : '',
			'custom_desc'     => $product ? $product->get_description() : '',
			'custom_image_id' => $product ? $product->get_image_id() : 0,
			'custom_weight'   => $product ? $product->get_weight() : '',
			'custom_virtual'  => $product ? $product->is_virtual() : $settings->is( 'custom_gift_virtual' ),
			'custom_stock'    => $product && $product->managing_stock() ? (int) $product->get_stock_quantity() : '',
		);
	} else {
		$gift_rows[] = array(
			'type'       => 'product',
			'qty'        => $gift['qty'],
			'product_id' => $gift['product_id'],
		);
	}
}
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wfg-rule-form">
	<?php wp_nonce_field( 'wfg_save_rule' ); ?>
	<input type="hidden" name="action" value="wfg_save_rule">
	<input type="hidden" name="rule[id]" value="<?php echo esc_attr( (int) $rule['id'] ); ?>">

	<div class="wfg-columns">
		<div class="wfg-main">

			<div class="wfg-card">
				<h2><?php echo $is_new ? esc_html__( 'New gift rule', 'woo-free-gifts' ) : esc_html__( 'Edit gift rule', 'woo-free-gifts' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wfg-title"><?php esc_html_e( 'Rule name', 'woo-free-gifts' ); ?></label></th>
						<td>
							<input type="text" id="wfg-title" name="rule[title]" value="<?php echo esc_attr( $rule['title'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Free seed from 50 €', 'woo-free-gifts' ); ?>" required>
							<p class="description"><?php esc_html_e( 'Internal name. Also shown on the order line as the gift label.', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'woo-free-gifts' ); ?></th>
						<td><?php echo WFG_Admin::checkbox( 'rule[enabled]', $rule['enabled'], __( 'Rule is active', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					</tr>
					<tr>
						<th scope="row"><label for="wfg-priority"><?php esc_html_e( 'Priority', 'woo-free-gifts' ); ?></label></th>
						<td>
							<input type="number" id="wfg-priority" name="rule[priority]" value="<?php echo esc_attr( (int) $rule['priority'] ); ?>" min="0" max="9999" class="small-text">
							<p class="description"><?php esc_html_e( 'Higher wins when the stacking mode is "highest rule only". With the same priority the higher cart threshold wins.', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="wfg-card">
				<h2><?php esc_html_e( 'Conditions', 'woo-free-gifts' ); ?></h2>
				<p class="description"><?php esc_html_e( 'All conditions you fill in must be met (AND). Leave a condition empty to ignore it.', 'woo-free-gifts' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="wfg-min-total"><?php esc_html_e( 'Minimum cart value', 'woo-free-gifts' ); ?></label></th>
						<td>
							<input type="text" id="wfg-min-total" name="rule[min_total]" value="<?php echo esc_attr( $rule['min_total'] > 0 ? wc_format_localized_price( $rule['min_total'] ) : '' ); ?>" class="wc_input_price small-text" placeholder="0">
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
							&nbsp;&nbsp;
							<label for="wfg-max-total"><?php esc_html_e( 'Maximum', 'woo-free-gifts' ); ?></label>
							<input type="text" id="wfg-max-total" name="rule[max_total]" value="<?php echo esc_attr( $rule['max_total'] > 0 ? wc_format_localized_price( $rule['max_total'] ) : '' ); ?>" class="wc_input_price small-text" placeholder="∞">
							<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
							<p class="description">
								<?php
								printf(
									/* translators: %s: settings link */
									esc_html__( 'Compared against the cart value without gifts. Tax / coupon handling is configured under %s.', 'woo-free-gifts' ),
									'<a href="' . esc_url( WFG_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'woo-free-gifts' ) . '</a>'
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wfg-required-products"><?php esc_html_e( 'Required products', 'woo-free-gifts' ); ?></label></th>
						<td>
							<select id="wfg-required-products" class="wc-product-search" multiple="multiple" style="width: 100%;" name="rule[required_products][]" data-placeholder="<?php esc_attr_e( 'Search products, variations or bundles…', 'woo-free-gifts' ); ?>" data-action="woocommerce_json_search_products_and_variations">
								<?php echo WFG_Admin::product_options( (array) $rule['required_products'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</select>
							<p class="wfg-inline-fields">
								<label>
									<select name="rule[required_match]">
										<option value="all" <?php selected( $rule['required_match'], 'all' ); ?>><?php esc_html_e( 'All of these products must be in the cart', 'woo-free-gifts' ); ?></option>
										<option value="any" <?php selected( $rule['required_match'], 'any' ); ?>><?php esc_html_e( 'Any one of these products is enough', 'woo-free-gifts' ); ?></option>
									</select>
								</label>
								<label>
									<?php esc_html_e( 'Min. quantity each', 'woo-free-gifts' ); ?>
									<input type="number" name="rule[required_qty]" value="<?php echo esc_attr( (int) $rule['required_qty'] ); ?>" min="1" max="9999" class="small-text">
								</label>
							</p>
							<p class="description"><?php esc_html_e( '"Buy product B and C, get gift X". Items inside product bundles count as well (configurable in the settings).', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wfg-required-categories"><?php esc_html_e( 'Required categories', 'woo-free-gifts' ); ?></label></th>
						<td>
							<select id="wfg-required-categories" class="wc-enhanced-select" multiple="multiple" style="width: 100%;" name="rule[required_categories][]" data-placeholder="<?php esc_attr_e( 'Any category…', 'woo-free-gifts' ); ?>">
								<?php foreach ( $categories as $product_cat ) : ?>
									<option value="<?php echo esc_attr( $product_cat->term_id ); ?>" <?php selected( in_array( (int) $product_cat->term_id, array_map( 'intval', (array) $rule['required_categories'] ), true ) ); ?>><?php echo esc_html( $product_cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
							<p class="description"><?php esc_html_e( 'The cart must contain at least one product from one of these categories (child categories included).', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Bundles', 'woo-free-gifts' ); ?></th>
						<td>
							<?php echo WFG_Admin::checkbox( 'rule[require_bundle]', $rule['require_bundle'], __( 'Only when the cart contains a bundle product', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<p class="description"><?php esc_html_e( 'Works with WooCommerce Product Bundles, WPC Product Bundles, YITH Bundles, Composite and Grouped products. To require a specific bundle, add it under "Required products".', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wfg-min-items"><?php esc_html_e( 'Minimum item count', 'woo-free-gifts' ); ?></label></th>
						<td><input type="number" id="wfg-min-items" name="rule[min_items]" value="<?php echo esc_attr( (int) $rule['min_items'] ? (int) $rule['min_items'] : '' ); ?>" min="0" max="9999" class="small-text" placeholder="0"></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Customers', 'woo-free-gifts' ); ?></th>
						<td>
							<select class="wc-enhanced-select" multiple="multiple" style="width: 50%;" name="rule[user_roles][]" data-placeholder="<?php esc_attr_e( 'All customer roles…', 'woo-free-gifts' ); ?>">
								<?php foreach ( $roles as $role_key => $role_data ) : ?>
									<option value="<?php echo esc_attr( $role_key ); ?>" <?php selected( in_array( $role_key, (array) $rule['user_roles'], true ) ); ?>><?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?></option>
								<?php endforeach; ?>
							</select>
							<p><?php echo WFG_Admin::checkbox( 'rule[logged_in_only]', $rule['logged_in_only'], __( 'Logged-in customers only', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
							<p><?php echo WFG_Admin::checkbox( 'rule[once_per_customer]', $rule['once_per_customer'], __( 'Only once per customer account (guests are not tracked)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wfg-claim-limit"><?php esc_html_e( 'Budget', 'woo-free-gifts' ); ?></label></th>
						<td class="wfg-inline-fields">
							<label>
								<?php esc_html_e( 'Max. orders', 'woo-free-gifts' ); ?>
								<input type="number" id="wfg-claim-limit" name="rule[claim_limit]" value="<?php echo esc_attr( (int) $rule['claim_limit'] ? (int) $rule['claim_limit'] : '' ); ?>" min="0" max="1000000" class="small-text" placeholder="∞">
							</label>
							<?php if ( ! $is_new ) : ?>
								<span class="description"><?php echo esc_html( sprintf( /* translators: %d: number of orders */ __( 'claimed so far: %d', 'woo-free-gifts' ), WFG_Rules::claims( $rule['id'] ) ) ); ?></span>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Once this many orders have claimed the rule it switches off automatically. Together with the gift stock this drives the "Only X left" scarcity line (see Settings).', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Schedule', 'woo-free-gifts' ); ?></th>
						<td class="wfg-inline-fields">
							<label><?php esc_html_e( 'From', 'woo-free-gifts' ); ?> <input type="date" name="rule[date_from]" value="<?php echo esc_attr( $rule['date_from'] ); ?>"></label>
							<label><?php esc_html_e( 'Until', 'woo-free-gifts' ); ?> <input type="date" name="rule[date_to]" value="<?php echo esc_attr( $rule['date_to'] ); ?>"></label>
							<p class="description"><?php esc_html_e( 'Leave empty for no time limit. Dates use the site timezone.', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
				</table>
			</div>

			<div class="wfg-card">
				<h2><?php esc_html_e( 'Gifts', 'woo-free-gifts' ); ?></h2>
				<p>
					<label>
						<strong><?php esc_html_e( 'When several gifts are listed', 'woo-free-gifts' ); ?></strong><br>
						<select name="rule[gift_mode]">
							<option value="auto" <?php selected( $rule['gift_mode'], 'auto' ); ?>><?php esc_html_e( 'Add all of them automatically', 'woo-free-gifts' ); ?></option>
							<option value="choice" <?php selected( $rule['gift_mode'], 'choice' ); ?>><?php esc_html_e( 'Let the customer pick one in the cart', 'woo-free-gifts' ); ?></option>
						</select>
					</label>
				</p>
				<div class="wfg-gift-rows">
					<?php
					if ( empty( $gift_rows ) ) {
						$gift_rows[] = array( 'type' => 'product' );
					}
					foreach ( $gift_rows as $i => $row ) {
						$wfg_render_gift_row( $i, $row );
					}
					?>
				</div>
				<p><button type="button" class="button wfg-gift-add"><?php esc_html_e( '+ Add gift', 'woo-free-gifts' ); ?></button></p>
			</div>

			<div class="wfg-card">
				<h2><?php esc_html_e( 'Messages & popup', 'woo-free-gifts' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Progress bar', 'woo-free-gifts' ); ?></th>
						<td>
							<?php echo WFG_Admin::checkbox( 'rule[show_progress]', $rule['show_progress'], __( 'Show this rule in the cart progress bar and product page hint (cart-value rules only)', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wfg-msg-progress"><?php esc_html_e( 'Progress message', 'woo-free-gifts' ); ?></label></th>
						<td>
							<input type="text" id="wfg-msg-progress" name="rule[msg_progress]" value="<?php echo esc_attr( $rule['msg_progress'] ); ?>" class="large-text" placeholder="<?php echo esc_attr( $settings->get( 'msg_progress' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Overrides the global text. Placeholders: {remaining}, {threshold}, {gift}', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="wfg-msg-unlocked"><?php esc_html_e( 'Unlocked message', 'woo-free-gifts' ); ?></label></th>
						<td>
							<input type="text" id="wfg-msg-unlocked" name="rule[msg_unlocked]" value="<?php echo esc_attr( $rule['msg_unlocked'] ); ?>" class="large-text" placeholder="<?php echo esc_attr( $settings->get( 'msg_unlocked' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Overrides the global text. Placeholders: {threshold}, {gift}', 'woo-free-gifts' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Popup', 'woo-free-gifts' ); ?></th>
						<td>
							<?php echo WFG_Admin::checkbox( 'rule[show_in_popup]', $rule['show_in_popup'], __( 'List this offer in the promo popup', 'woo-free-gifts' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<p><input type="text" name="rule[popup_text]" value="<?php echo esc_attr( $rule['popup_text'] ); ?>" class="large-text" placeholder="<?php esc_attr_e( 'Optional custom line, e.g. "Free seed with every order over 50 €"', 'woo-free-gifts' ); ?>"></p>
						</td>
					</tr>
				</table>
			</div>

		</div>

		<div class="wfg-side">
			<div class="wfg-card wfg-card--sticky">
				<p><button type="submit" class="button button-primary button-large"><?php echo $is_new ? esc_html__( 'Create rule', 'woo-free-gifts' ) : esc_html__( 'Save rule', 'woo-free-gifts' ); ?></button></p>
				<p><a href="<?php echo esc_url( WFG_Admin::url( 'rules' ) ); ?>"><?php esc_html_e( '← Back to all rules', 'woo-free-gifts' ); ?></a></p>
				<?php if ( ! $is_new ) : ?>
					<p><a href="<?php echo esc_url( WFG_Admin::action_url( 'wfg_delete_rule', array( 'rule' => $rule['id'] ) ) ); ?>" class="wfg-delete submitdelete"><?php esc_html_e( 'Delete rule', 'woo-free-gifts' ); ?></a></p>
				<?php endif; ?>
				<hr>
				<h3><?php esc_html_e( 'Examples', 'woo-free-gifts' ); ?></h3>
				<ul class="wfg-examples">
					<li><?php esc_html_e( 'Free seed from 50 €: set "Minimum cart value" to 50 and add the seed as gift.', 'woo-free-gifts' ); ?></li>
					<li><?php esc_html_e( 'Book from 100 €: second rule with 100 as minimum. With stacking "all", a 120 € cart gets both.', 'woo-free-gifts' ); ?></li>
					<li><?php esc_html_e( 'Buy B and C, get X: add B and C under "Required products" (match: all).', 'woo-free-gifts' ); ?></li>
					<li><?php esc_html_e( 'Gift with a bundle: add the bundle product under "Required products" or tick "Only when the cart contains a bundle".', 'woo-free-gifts' ); ?></li>
				</ul>
			</div>
		</div>
	</div>
</form>

<script type="text/template" id="wfg-gift-row-template">
	<?php $wfg_render_gift_row( '__INDEX__', array( 'type' => 'product' ) ); ?>
</script>
