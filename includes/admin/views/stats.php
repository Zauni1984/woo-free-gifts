<?php
/**
 * Admin view: statistics.
 *
 * @var WFG_Rules $rules Rules.
 * @var array     $stats Statistics keyed by rule id.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

$total = 0;
foreach ( $stats as $row ) {
	$total += isset( $row['count'] ) ? (int) $row['count'] : 0;
}
?>
<div class="wfg-card">
	<h2><?php esc_html_e( 'Gifts claimed', 'woo-free-gifts' ); ?></h2>
	<p class="wfg-stat-total"><?php echo esc_html( number_format_i18n( $total ) ); ?> <span class="description"><?php esc_html_e( 'orders with gifts in total', 'woo-free-gifts' ); ?></span></p>

	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Rule', 'woo-free-gifts' ); ?></th>
				<th class="wfg-col-small"><?php esc_html_e( 'Orders', 'woo-free-gifts' ); ?></th>
				<th><?php esc_html_e( 'Last claimed', 'woo-free-gifts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $stats ) ) : ?>
				<tr><td colspan="3"><?php esc_html_e( 'No gifts have been claimed yet.', 'woo-free-gifts' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $stats as $rule_id => $row ) : ?>
				<?php $rule = $rules->get( $rule_id ); ?>
				<tr>
					<td>
						<?php if ( $rule ) : ?>
							<a href="
							<?php
							echo esc_url(
								WFG_Admin::url(
									'rules',
									array(
										'action' => 'edit',
										'rule'   => $rule_id,
									)
								)
							);
							?>
										"><?php echo esc_html( $rule['title'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( sprintf( /* translators: %d: rule id */ __( 'Deleted rule #%d', 'woo-free-gifts' ), (int) $rule_id ) ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( number_format_i18n( isset( $row['count'] ) ? (int) $row['count'] : 0 ) ); ?></td>
					<td><?php echo ! empty( $row['last'] ) ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $row['last'] ) ) : '–'; ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<p>
		<a href="<?php echo esc_url( WFG_Admin::action_url( 'wfg_reset_stats' ) ); ?>" class="button wfg-confirm" data-confirm="<?php esc_attr_e( 'Reset all statistics?', 'woo-free-gifts' ); ?>"><?php esc_html_e( 'Reset statistics', 'woo-free-gifts' ); ?></a>
	</p>
	<p class="description"><?php esc_html_e( 'Counts are recorded when an order is placed. Each order line also carries the gift rule as order item meta, so you can filter orders in reports.', 'woo-free-gifts' ); ?></p>
</div>
