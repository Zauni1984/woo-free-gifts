<?php
/**
 * Uninstall routine.
 *
 * Only removes data when "Delete data on uninstall" was enabled in the settings,
 * so an accidental delete/reinstall never wipes the shop's gift configuration.
 *
 * @package WooFreeGifts
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$wfg_settings = get_option( 'wfg_settings', array() );
if ( ! is_array( $wfg_settings ) || empty( $wfg_settings['delete_data_on_uninstall'] ) ) {
	return;
}

// Hidden gift products.
$wfg_gift_ids = get_option( 'wfg_custom_gift_ids', array() );
if ( is_array( $wfg_gift_ids ) ) {
	foreach ( $wfg_gift_ids as $wfg_gift_id ) {
		$wfg_gift_id = absint( $wfg_gift_id );
		if ( $wfg_gift_id && 'product' === get_post_type( $wfg_gift_id ) && 'yes' === get_post_meta( $wfg_gift_id, '_wfg_custom_gift', true ) ) {
			wp_delete_post( $wfg_gift_id, true );
		}
	}
}

// Options.
foreach ( array( 'wfg_settings', 'wfg_rules', 'wfg_rules_next_id', 'wfg_stats', 'wfg_custom_gift_ids', 'wfg_version', 'wfg_wheel_stats', 'wfg_wheel_log' ) as $wfg_option ) {
	delete_option( $wfg_option );
}

// Per-customer claim tracking and wheel cooldowns.
delete_metadata( 'user', 0, '_wfg_claimed_rules', '', true );
delete_metadata( 'user', 0, '_wfg_wheel_next', '', true );
