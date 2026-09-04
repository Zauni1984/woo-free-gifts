<?php
/**
 * Logging via WooCommerce's logger (WooCommerce → Status → Logs, source "woo-free-gifts").
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Logger
 */
final class WFG_Logger {

	const SOURCE = 'woo-free-gifts';

	/**
	 * Debug entries are only written when debug logging is enabled in the settings.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public static function debug( $message, array $context = array() ) {
		$plugin = function_exists( 'wfg' ) ? wfg() : null;
		if ( ! $plugin || ! $plugin->settings || ! $plugin->settings->is( 'debug_log' ) ) {
			return;
		}
		self::write( 'debug', $message, $context );
	}

	/**
	 * Errors are always written.
	 *
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	public static function error( $message, array $context = array() ) {
		self::write( 'error', $message, $context );
	}

	/**
	 * Write to the WC log.
	 *
	 * @param string $level   Level.
	 * @param string $message Message.
	 * @param array  $context Context.
	 */
	private static function write( $level, $message, array $context ) {
		if ( ! function_exists( 'wc_get_logger' ) ) {
			return;
		}
		try {
			$logger = wc_get_logger();
			$logger->log( $level, (string) $message, array_merge( array( 'source' => self::SOURCE ), $context ) );
		} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Logging must never break the site.
		}
	}
}
