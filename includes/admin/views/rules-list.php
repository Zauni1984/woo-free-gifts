<?php
/**
 * Admin view: rules list.
 *
 * @var WFG_Settings $settings Settings.
 * @var WFG_Rules    $rules    Rules.
 * @var array        $stats    Statistics keyed by rule id.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$all_rules = $rules->all();
uasort(
	$all_rules,
	static function ( $a, $b ) {
		return (int) $b['priority'] <=> (int) $a['priority'];
	}
);
?>

<?php if ( ! $settings->enabled() ) : ?>
	<div class="notice notice-warning inline"><p>
		<?php
		printf(
			/* translators: %s: settings link */
			esc_html__( 'Gifts are currently disabled globally. Enable them under %s.', 'woo-free-gifts' ),
			'<a href="' . esc_url( WFG_Admin::url( 'settings' ) ) . '">' . esc_html__( 'Settings', 'woo-free-gifts' ) . '</a>'
		);
		?>
	</p></div>
<?php endif; ?>

<table class="wp-list-table widefat fixed striped wfg-rules-table">
	<thead>
		<tr>
			<th class="column-primary"><?php esc_html_e( 'Rule', 'woo-free-gifts' ); ?></th>
			<th><?php esc_html_e( 'Conditions', 'woo-free-gifts' ); ?></th>
			<th><?php esc_html_e( 'Gifts', 'woo-free-gifts' ); ?></th>
			<th class="wfg-col-small"><?php esc_html_e( 'Priority', 'woo-free-gifts' ); ?></th>
			<th class="wfg-col-small"><?php esc_html_e( 'Claimed', 'woo-free-gifts' ); ?></th>
			<th class="wfg-col-small"><?php esc_html_e( 'Status', 'woo-free-gifts' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php if ( empty( $all_rules ) ) : ?>
		<tr><td colspan="6">
			<?php esc_html_e( 'No gift rules yet.', 'woo-free-gifts' ); ?>
			<a href="<?php echo esc_url( WFG_Admin::url( 'rules', array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Create your first rule', 'woo-free-gifts' ); ?></a>
		</td></tr>
	<?php endif; ?>
	<?php foreach ( $all_rules as $rule ) : ?>
		<?php
		$edit_url = WFG_Admin::url(
			'rules',
			array(
				'action' => 'edit',
				'rule'   => $rule['id'],
			)
		);
		$count    = isset( $stats[ $rule['id'] ]['count'] ) ? (int) $stats[ $rule['id'] ]['count'] : 0;
		$window   = '';
		if ( '' !== $rule['date_from'] || '' !== $rule['date_to'] ) {
			$window = trim( $rule['date_from'] . ' → ' . $rule['date_to'], ' →' );
		}
		?>
		<tr class="<?php echo $rule['enabled'] ? '' : 'wfg-row-disabled'; ?>">
			<td class="column-primary">
				<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $rule['title'] ); ?></a></strong>
				<?php if ( $window ) : ?>
					<br><span class="description"><?php echo esc_html( $window ); ?></span>
				<?php endif; ?>
				<div class="row-actions">
					<span class="edit"><a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'woo-free-gifts' ); ?></a> | </span>
					<span class="toggle"><a href="<?php echo esc_url( WFG_Admin::action_url( 'wfg_toggle_rule', array( 'rule' => $rule['id'] ) ) ); ?>"><?php echo $rule['enabled'] ? esc_html__( 'Disable', 'woo-free-gifts' ) : esc_html__( 'Enable', 'woo-free-gifts' ); ?></a> | </span>
					<span class="duplicate"><a href="<?php echo esc_url( WFG_Admin::action_url( 'wfg_duplicate_rule', array( 'rule' => $rule['id'] ) ) ); ?>"><?php esc_html_e( 'Duplicate', 'woo-free-gifts' ); ?></a> | </span>
					<span class="trash"><a href="<?php echo esc_url( WFG_Admin::action_url( 'wfg_delete_rule', array( 'rule' => $rule['id'] ) ) ); ?>" class="wfg-delete submitdelete"><?php esc_html_e( 'Delete', 'woo-free-gifts' ); ?></a></span>
				</div>
			</td>
			<td>
				<ul class="wfg-inline-list">
					<?php foreach ( WFG_Rules::describe_conditions( $rule ) as $line ) : ?>
						<li><?php echo esc_html( $line ); ?></li>
					<?php endforeach; ?>
					<?php if ( ! empty( $rule['user_roles'] ) ) : ?>
						<li><?php echo esc_html( __( 'Roles:', 'woo-free-gifts' ) . ' ' . implode( ', ', $rule['user_roles'] ) ); ?></li>
					<?php endif; ?>
					<?php if ( ! empty( $rule['once_per_customer'] ) ) : ?>
						<li><?php esc_html_e( 'Once per customer', 'woo-free-gifts' ); ?></li>
					<?php endif; ?>
				</ul>
			</td>
			<td>
				<ul class="wfg-inline-list">
					<?php foreach ( $rule['gifts'] as $gift ) : ?>
						<?php $product = wc_get_product( $gift['product_id'] ); ?>
						<li>
							<?php if ( $product ) : ?>
								<?php echo wp_kses_post( $product->get_image( array( 32, 32 ) ) ); ?>
								<?php echo esc_html( $product->get_name() ); ?>
								<?php if ( $gift['qty'] > 1 ) : ?>
									<span class="description">&times;<?php echo esc_html( $gift['qty'] ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $gift['custom'] ) ) : ?>
									<span class="wfg-pill"><?php esc_html_e( 'custom', 'woo-free-gifts' ); ?></span>
								<?php endif; ?>
								<?php if ( ! $product->is_in_stock() ) : ?>
									<span class="wfg-pill wfg-pill--warn"><?php esc_html_e( 'out of stock', 'woo-free-gifts' ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<span class="wfg-pill wfg-pill--warn"><?php echo esc_html( sprintf( /* translators: %d: product id */ __( 'Missing product #%d', 'woo-free-gifts' ), $gift['product_id'] ) ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( 'choice' === $rule['gift_mode'] && count( $rule['gifts'] ) > 1 ) : ?>
					<span class="description"><?php esc_html_e( 'Customer picks one', 'woo-free-gifts' ); ?></span>
				<?php endif; ?>
			</td>
			<td><?php echo esc_html( $rule['priority'] ); ?></td>
			<td><?php echo esc_html( number_format_i18n( $count ) ); ?></td>
			<td>
				<?php if ( $rule['enabled'] ) : ?>
					<span class="wfg-status wfg-status--on"><?php esc_html_e( 'Active', 'woo-free-gifts' ); ?></span>
				<?php else : ?>
					<span class="wfg-status wfg-status--off"><?php esc_html_e( 'Disabled', 'woo-free-gifts' ); ?></span>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>

<p class="description wfg-help">
	<?php
	if ( 'highest' === $settings->get( 'stacking' ) ) {
		esc_html_e( 'Stacking: only the qualifying rule with the highest priority adds its gifts.', 'woo-free-gifts' );
	} else {
		esc_html_e( 'Stacking: every qualifying rule adds its gifts.', 'woo-free-gifts' );
	}
	?>
	<?php esc_html_e( 'Shortcodes: [wfg_progress] shows the progress bar anywhere, [wfg_gift_list] lists all active offers.', 'woo-free-gifts' ); ?>
</p>
