<?php
/**
 * Admin view: statistics.
 *
 * @var WFG_Rules $rules       Rules.
 * @var array     $stats       Statistics keyed by rule id.
 * @var array     $wheel_stats spins, coupons, gifts.
 * @var array[]   $wheel_log   Recent spins.
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

	<p class="description"><?php esc_html_e( 'Counts are recorded when an order is placed. Each order line also carries the gift rule as order item meta, so you can filter orders in reports.', 'woo-free-gifts' ); ?></p>
</div>

<div class="wfg-card">
	<h2><?php esc_html_e( 'Wheel of fortune', 'woo-free-gifts' ); ?></h2>
	<p class="wfg-stat-row">
		<span><strong><?php echo esc_html( number_format_i18n( (int) $wheel_stats['spins'] ) ); ?></strong> <?php esc_html_e( 'spins', 'woo-free-gifts' ); ?></span>
		<span><strong><?php echo esc_html( number_format_i18n( (int) $wheel_stats['coupons'] ) ); ?></strong> <?php esc_html_e( 'coupons won', 'woo-free-gifts' ); ?></span>
		<span><strong><?php echo esc_html( number_format_i18n( (int) $wheel_stats['gifts'] ) ); ?></strong> <?php esc_html_e( 'gifts won', 'woo-free-gifts' ); ?></span>
	</p>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Time', 'woo-free-gifts' ); ?></th>
				<th><?php esc_html_e( 'Visitor', 'woo-free-gifts' ); ?></th>
				<th><?php esc_html_e( 'Segment', 'woo-free-gifts' ); ?></th>
				<th><?php esc_html_e( 'Result', 'woo-free-gifts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $wheel_log ) ) : ?>
				<tr><td colspan="4"><?php esc_html_e( 'No spins yet.', 'woo-free-gifts' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( array_slice( $wheel_log, 0, 50 ) as $entry ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $entry['time'] ) ); ?></td>
					<td>
						<?php
						if ( ! empty( $entry['user'] ) ) {
							$wfg_user = get_userdata( (int) $entry['user'] );
							echo esc_html( $wfg_user ? $wfg_user->display_name : '#' . (int) $entry['user'] );
						} elseif ( ! empty( $entry['email'] ) ) {
							echo esc_html( $entry['email'] );
						} else {
							esc_html_e( 'Guest', 'woo-free-gifts' );
						}
						?>
					</td>
					<td><?php echo esc_html( $entry['label'] ); ?></td>
					<td>
						<?php
						if ( 'coupon' === $entry['type'] ) {
							echo '<code>' . esc_html( $entry['code'] ) . '</code>';
						} elseif ( 'gift' === $entry['type'] ) {
							esc_html_e( 'Free gift', 'woo-free-gifts' );
						} else {
							echo '–';
						}
						?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<p>
		<a href="<?php echo esc_url( WFG_Admin::action_url( 'wfg_reset_stats' ) ); ?>" class="button wfg-confirm" data-confirm="<?php esc_attr_e( 'Reset all statistics?', 'woo-free-gifts' ); ?>"><?php esc_html_e( 'Reset statistics', 'woo-free-gifts' ); ?></a>
	</p>
</div>
