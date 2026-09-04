<?php
/**
 * Wheel of fortune: one spin per cooldown window, prizes are coupons or free gifts.
 *
 * The outcome is decided server-side (weighted random), the cooldown is enforced
 * server-side across user meta, WooCommerce session, a signed cookie, the hashed
 * IP address and the hashed e-mail address – the browser only animates the result.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Wheel
 */
final class WFG_Wheel {

	const SESSION_GIFTS  = 'wfg_wheel_gifts';
	const SESSION_NEXT   = 'wfg_wheel_next';
	const USER_META_NEXT = '_wfg_wheel_next';
	const COOKIE         = 'wfg_wheel_next';
	const OPTION_STATS   = 'wfg_wheel_stats';
	const OPTION_LOG     = 'wfg_wheel_log';
	const LOG_LIMIT      = 200;
	const COUPON_META    = '_wfg_wheel_coupon';

	/**
	 * Settings.
	 *
	 * @var WFG_Settings
	 */
	private $settings;

	/**
	 * Engine.
	 *
	 * @var WFG_Engine
	 */
	private $engine;

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings $settings Settings.
	 * @param WFG_Engine   $engine   Engine.
	 */
	public function __construct( WFG_Settings $settings, WFG_Engine $engine ) {
		$this->settings = $settings;
		$this->engine   = $engine;

		add_action( 'wp_footer', array( $this, 'output' ), 51 );
		add_action( 'wp_ajax_wfg_wheel_spin', array( $this, 'ajax_spin' ) );
		add_action( 'wp_ajax_nopriv_wfg_wheel_spin', array( $this, 'ajax_spin' ) );
	}

	/**
	 * Is the wheel switched on?
	 *
	 * @return bool
	 */
	public function enabled() {
		return $this->settings->is( 'wheel_enabled' ) && count( $this->segments() ) >= 2;
	}

	/**
	 * Configured segments.
	 *
	 * @return array[]
	 */
	public function segments() {
		$segments = $this->settings->get( 'wheel_segments' );
		return is_array( $segments ) ? array_values( $segments ) : array();
	}

	/**
	 * Print the wheel markup on the configured pages.
	 *
	 * @return bool
	 */
	public function should_render() {
		if ( ! $this->enabled() ) {
			return false;
		}
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_404() || ! function_exists( 'is_product' ) ) {
			return false;
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return false;
		}
		$show = false;
		if ( $this->settings->is( 'wheel_on_single' ) && is_product() ) {
			$show = true;
		} elseif ( $this->settings->is( 'wheel_on_archive' ) && ( is_shop() || is_product_taxonomy() ) ) {
			$show = true;
		} elseif ( $this->settings->is( 'wheel_on_cart' ) && function_exists( 'is_cart' ) && is_cart() ) {
			$show = true;
		} elseif ( $this->settings->is( 'wheel_on_other' ) && ! is_product() && ! is_shop() && ! is_product_taxonomy() && ! ( function_exists( 'is_cart' ) && is_cart() ) ) {
			$show = true;
		}

		/**
		 * Filter whether the wheel markup is printed on the current page.
		 *
		 * @param bool $show Show.
		 */
		return (bool) apply_filters( 'wfg_wheel_should_render', $show );
	}

	/**
	 * Print the wheel.
	 */
	public function output() {
		if ( ! $this->should_render() ) {
			return;
		}

		$segments = array();
		foreach ( $this->segments() as $segment ) {
			$segments[] = array(
				'label' => $segment['label'],
				'color' => $segment['color'],
				'type'  => $segment['type'],
			);
		}

		$logged_in = is_user_logged_in();
		$next      = $this->next_allowed_spin( '' );

		$config = array(
			'delay'        => (int) $this->settings->get( 'wheel_delay' ),
			'segments'     => count( $segments ),
			'nextSpin'     => $next > time() ? $next : 0, // Server knowledge for logged-in users / sessions; the browser also remembers.
			'requireEmail' => $this->settings->is( 'wheel_require_email' ) && ! $logged_in,
			'consent'      => '' !== $this->settings->get( 'wheel_consent_text' ),
			'version'      => substr( md5( wp_json_encode( array( $this->settings->get( 'wheel_title' ), wp_list_pluck( $segments, 'label' ) ) ) ), 0, 8 ),
		);

		$html = WFG_Helpers::template(
			'wheel',
			array(
				'theme'         => $this->settings->get( 'wheel_theme' ),
				'accent'        => $this->settings->get( 'wheel_accent' ),
				'title'         => $this->settings->get( 'wheel_title' ),
				'content'       => $this->settings->get( 'wheel_content' ),
				'button'        => $this->settings->get( 'wheel_button' ),
				'segments'      => $segments,
				'require_email' => $config['requireEmail'],
				'email_label'   => $this->settings->get( 'wheel_email_label' ),
				'consent_text'  => $this->settings->get( 'wheel_consent_text' ),
				'config'        => $config,
			)
		);

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes its output.
	}

	// --- Spinning ---

	/**
	 * AJAX: spin the wheel.
	 */
	public function ajax_spin() {
		check_ajax_referer( 'wfg-frontend', 'nonce' );

		if ( ! $this->enabled() ) {
			wp_send_json_error( array( 'message' => __( 'The wheel is currently not available.', 'woo-free-gifts' ) ), 400 );
		}

		$email = '';
		if ( is_user_logged_in() ) {
			$user  = wp_get_current_user();
			$email = $user ? (string) $user->user_email : '';
		} elseif ( $this->settings->is( 'wheel_require_email' ) ) {
			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			if ( '' === $email || ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid e-mail address.', 'woo-free-gifts' ) ), 400 );
			}
		}
		if ( '' !== $this->settings->get( 'wheel_consent_text' ) && empty( $_POST['consent'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Please accept the terms to spin.', 'woo-free-gifts' ) ), 400 );
		}

		try {
			$result = $this->spin( $email );
		} catch ( Throwable $e ) {
			WFG_Logger::error( 'Wheel spin failed: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => __( 'Something went wrong. Please reload the page.', 'woo-free-gifts' ) ), 500 );
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message'  => $result->get_error_message(),
					'code'     => $result->get_error_code(),
					'nextSpin' => (int) $result->get_error_data(),
				),
				429
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * Perform a spin for the current visitor.
	 *
	 * @param string $email E-mail (may be empty).
	 * @return array|WP_Error index, type, label, message, code, nextSpin
	 */
	public function spin( $email = '' ) {
		$next = $this->next_allowed_spin( $email );
		if ( $next > time() ) {
			$hours = max( 1, (int) ceil( ( $next - time() ) / HOUR_IN_SECONDS ) );
			$msg   = WFG_Helpers::placeholders( $this->settings->get( 'wheel_msg_already' ), array( 'hours' => $hours ) );
			return new WP_Error( 'wfg_wheel_cooldown', $msg, $next );
		}

		$index = $this->pick_segment();
		if ( null === $index ) {
			return new WP_Error( 'wfg_wheel_no_prizes', __( 'The wheel is currently not available.', 'woo-free-gifts' ), 0 );
		}
		$segments = $this->segments();
		$segment  = $segments[ $index ];

		// Lock first, so a failure while awarding never grants a second spin.
		$next = $this->lock( $email );

		$result = array(
			'index'    => $index,
			'type'     => $segment['type'],
			'label'    => $segment['label'],
			'message'  => '',
			'code'     => '',
			'nextSpin' => $next,
		);

		switch ( $segment['type'] ) {
			case 'coupon':
				$code = $this->award_coupon( $segment, $email );
				if ( is_wp_error( $code ) ) {
					WFG_Logger::error( 'Wheel coupon failed: ' . $code->get_error_message() );
					$result['type']    = 'none';
					$result['message'] = __( 'Something went wrong while creating your coupon. Please contact us.', 'woo-free-gifts' );
					break;
				}
				$result['code']    = $code;
				$result['message'] = WFG_Helpers::placeholders(
					$this->settings->get( 'wheel_msg_win_coupon' ),
					array(
						'code'  => $code,
						'prize' => $segment['label'],
						'days'  => (int) $this->settings->get( 'wheel_coupon_expiry_days' ),
					)
				);
				$this->bump_stat( 'coupons' );
				break;

			case 'gift':
				$product = $this->award_gift( $segment );
				if ( ! $product ) {
					$result['type']    = 'none';
					$result['message'] = $this->settings->get( 'wheel_msg_lose' );
					break;
				}
				$result['message'] = WFG_Helpers::placeholders(
					$this->settings->get( 'wheel_msg_win_gift' ),
					array(
						'prize' => $product->get_name(),
						'days'  => (int) $this->settings->get( 'wheel_gift_valid_days' ),
					)
				);
				$this->bump_stat( 'gifts' );
				break;

			default:
				$result['message'] = $this->settings->get( 'wheel_msg_lose' );
		}

		$this->bump_stat( 'spins' );
		$this->log( $email, $segment['label'], $result['type'], $result['code'] );

		/**
		 * Fires after a wheel spin.
		 *
		 * @param array  $result  Spin result.
		 * @param array  $segment Winning segment.
		 * @param string $email   E-mail (may be empty).
		 */
		do_action( 'wfg_wheel_spun', $result, $segment, $email );

		return $result;
	}

	/**
	 * Weighted random pick. Gift segments whose product is unavailable are skipped.
	 *
	 * @return int|null Segment index.
	 */
	private function pick_segment() {
		$weights = array();
		foreach ( $this->segments() as $i => $segment ) {
			$weight = (int) $segment['weight'];
			if ( $weight <= 0 ) {
				continue;
			}
			if ( 'gift' === $segment['type'] && ! $this->gift_product( $segment ) ) {
				continue;
			}
			$weights[ $i ] = $weight;
		}
		if ( empty( $weights ) ) {
			return null;
		}
		$total = array_sum( $weights );
		$roll  = random_int( 1, $total );
		foreach ( $weights as $i => $weight ) {
			$roll -= $weight;
			if ( $roll <= 0 ) {
				return $i;
			}
		}
		return (int) array_key_last( $weights );
	}

	/**
	 * Purchasable, in-stock product of a gift segment.
	 *
	 * @param array $segment Segment.
	 * @return WC_Product|null
	 */
	private function gift_product( array $segment ) {
		$gifts = $this->engine->available_gifts(
			array(
				'gift_mode' => 'auto',
				'gifts'     => array(
					array(
						'product_id' => (int) $segment['product_id'],
						'qty'        => 1,
						'custom'     => WFG_Gift_Products::is_custom_gift( (int) $segment['product_id'] ),
					),
				),
			)
		);
		return ! empty( $gifts ) ? $gifts[0]['product'] : null;
	}

	// --- Prizes ---

	/**
	 * Create (or reuse) the coupon for a segment.
	 *
	 * @param array  $segment Segment.
	 * @param string $email   E-mail for the restriction (may be empty).
	 * @return string|WP_Error Coupon code.
	 */
	private function award_coupon( array $segment, $email ) {
		if ( '' !== $segment['code'] ) {
			if ( ! wc_get_coupon_id_by_code( $segment['code'] ) ) {
				return new WP_Error( 'wfg_wheel_coupon_missing', 'Coupon "' . $segment['code'] . '" does not exist.' );
			}
			$code = $segment['code'];
		} else {
			$code   = $this->unique_code();
			$coupon = new WC_Coupon();
			$coupon->set_code( $code );
			$coupon->set_discount_type( 'fixed_cart' === $segment['coupon_type'] ? 'fixed_cart' : 'percent' );
			$coupon->set_amount( (float) $segment['amount'] );
			$coupon->set_usage_limit( 1 );
			$coupon->set_usage_limit_per_user( 1 );
			$coupon->set_individual_use( false );
			$coupon->set_description( __( 'Wheel of fortune prize', 'woo-free-gifts' ) );

			$days = (int) $this->settings->get( 'wheel_coupon_expiry_days' );
			if ( $days > 0 ) {
				$coupon->set_date_expires( time() + $days * DAY_IN_SECONDS );
			}
			$min = (float) $this->settings->get( 'wheel_coupon_min_amount' );
			if ( $min > 0 ) {
				$coupon->set_minimum_amount( $min );
			}
			if ( '' !== $email ) {
				$coupon->set_email_restrictions( array( $email ) );
			}
			$coupon->update_meta_data( self::COUPON_META, 'yes' );

			try {
				$id = $coupon->save();
			} catch ( Throwable $e ) {
				return new WP_Error( 'wfg_wheel_coupon_save', $e->getMessage() );
			}
			if ( ! $id ) {
				return new WP_Error( 'wfg_wheel_coupon_save', 'Coupon could not be saved.' );
			}
		}

		// Auto-apply to the current cart, silently.
		if ( $this->settings->is( 'wheel_auto_apply' ) && WC()->cart && ! WC()->cart->is_empty() && ! WC()->cart->has_discount( $code ) ) {
			$notices = WC()->session ? wc_get_notices() : null;
			try {
				WC()->cart->apply_coupon( $code );
			} catch ( Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// The customer still has the code.
			}
			if ( null !== $notices ) {
				wc_set_notices( $notices );
			}
		}

		return $code;
	}

	/**
	 * Generate a unique coupon code.
	 *
	 * @return string
	 */
	private function unique_code() {
		$prefix = $this->settings->get( 'wheel_coupon_prefix' );
		for ( $i = 0; $i < 10; $i++ ) {
			$code = $prefix . '-' . strtoupper( wp_generate_password( 6, false, false ) );
			if ( ! wc_get_coupon_id_by_code( $code ) ) {
				return $code;
			}
		}
		return $prefix . '-' . strtoupper( wp_generate_password( 10, false, false ) );
	}

	/**
	 * Remember a won gift in the session; the cart sync adds it once there are paid items.
	 *
	 * @param array $segment Segment.
	 * @return WC_Product|null
	 */
	private function award_gift( array $segment ) {
		$product = $this->gift_product( $segment );
		if ( ! $product || ! WC()->session ) {
			return null;
		}
		$gifts                       = self::pending_gifts();
		$gifts[ $product->get_id() ] = time() + (int) $this->settings->get( 'wheel_gift_valid_days' ) * DAY_IN_SECONDS;
		WC()->session->set( self::SESSION_GIFTS, $gifts );

		if ( WC()->cart && ! WC()->cart->is_empty() ) {
			$this->engine->flush();
			WC()->cart->calculate_totals();
		}
		return $product;
	}

	/**
	 * Won gifts that have not expired: product_id => expiry timestamp.
	 *
	 * @return array<int,int>
	 */
	public static function pending_gifts() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return array();
		}
		$gifts = WC()->session->get( self::SESSION_GIFTS );
		if ( ! is_array( $gifts ) ) {
			return array();
		}
		$now   = time();
		$clean = array();
		foreach ( $gifts as $pid => $expires ) {
			if ( (int) $expires > $now ) {
				$clean[ (int) $pid ] = (int) $expires;
			}
		}
		if ( count( $clean ) !== count( $gifts ) ) {
			WC()->session->set( self::SESSION_GIFTS, empty( $clean ) ? null : $clean );
		}
		return $clean;
	}

	/**
	 * Forget a won gift (customer removed it or it was ordered).
	 *
	 * @param int $product_id Product id.
	 */
	public static function forget_gift( $product_id ) {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}
		$gifts = self::pending_gifts();
		unset( $gifts[ (int) $product_id ] );
		WC()->session->set( self::SESSION_GIFTS, empty( $gifts ) ? null : $gifts );
	}

	// --- Cooldown ---

	/**
	 * Earliest timestamp at which the visitor may spin again (0 = now).
	 *
	 * @param string $email E-mail (may be empty).
	 * @return int
	 */
	public function next_allowed_spin( $email = '' ) {
		$next = 0;

		if ( is_user_logged_in() ) {
			$next = max( $next, (int) get_user_meta( get_current_user_id(), self::USER_META_NEXT, true ) );
		}
		if ( function_exists( 'WC' ) && WC()->session ) {
			$next = max( $next, (int) WC()->session->get( self::SESSION_NEXT ) );
		}
		$next = max( $next, $this->cookie_value() );
		if ( $this->settings->is( 'wheel_ip_check' ) ) {
			$next = max( $next, (int) get_transient( $this->ip_key() ) );
		}
		if ( '' !== $email ) {
			$next = max( $next, (int) get_transient( $this->email_key( $email ) ) );
		}

		/**
		 * Filter the next allowed spin timestamp.
		 *
		 * @param int    $next  Timestamp (0 = now).
		 * @param string $email E-mail.
		 */
		return (int) apply_filters( 'wfg_wheel_next_allowed_spin', $next, $email );
	}

	/**
	 * Store the cooldown on every channel we have.
	 *
	 * @param string $email E-mail (may be empty).
	 * @return int Next allowed timestamp.
	 */
	private function lock( $email ) {
		$ttl  = (int) $this->settings->get( 'wheel_cooldown_hours' ) * HOUR_IN_SECONDS;
		$next = time() + $ttl;

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), self::USER_META_NEXT, $next );
		}
		if ( function_exists( 'WC' ) && WC()->session ) {
			WC()->session->set( self::SESSION_NEXT, $next );
		}
		$this->set_cookie( $next, $ttl );
		if ( $this->settings->is( 'wheel_ip_check' ) ) {
			set_transient( $this->ip_key(), $next, $ttl );
		}
		if ( '' !== $email ) {
			set_transient( $this->email_key( $email ), $next, $ttl );
		}
		return $next;
	}

	/**
	 * Signed cookie value (timestamp) or 0.
	 *
	 * @return int
	 */
	private function cookie_value() {
		if ( empty( $_COOKIE[ self::COOKIE ] ) ) {
			return 0;
		}
		$raw   = sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE ] ) );
		$parts = explode( '|', $raw );
		if ( 2 !== count( $parts ) ) {
			return 0;
		}
		$ts = (int) $parts[0];
		if ( ! hash_equals( $this->sign( $ts ), $parts[1] ) ) {
			return 0;
		}
		return $ts;
	}

	/**
	 * Set the signed cooldown cookie.
	 *
	 * @param int $next Timestamp.
	 * @param int $ttl  Seconds.
	 */
	private function set_cookie( $next, $ttl ) {
		if ( headers_sent() ) {
			return;
		}
		$value = $next . '|' . $this->sign( $next );
		setcookie(
			self::COOKIE,
			$value,
			array(
				'expires'  => time() + $ttl,
				'path'     => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
		$_COOKIE[ self::COOKIE ] = $value;
	}

	/**
	 * HMAC for the cookie.
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	private function sign( $ts ) {
		return hash_hmac( 'sha256', 'wfg_wheel|' . (int) $ts, wp_salt( 'auth' ) );
	}

	/**
	 * Transient key for the hashed IP.
	 *
	 * @return string
	 */
	private function ip_key() {
		$ip = '';
		if ( class_exists( 'WC_Geolocation' ) ) {
			$ip = WC_Geolocation::get_ip_address();
		}
		if ( '' === $ip && isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return 'wfg_wheel_ip_' . substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
	}

	/**
	 * Transient key for the hashed e-mail.
	 *
	 * @param string $email E-mail.
	 * @return string
	 */
	private function email_key( $email ) {
		return 'wfg_wheel_em_' . substr( hash_hmac( 'sha256', strtolower( trim( $email ) ), wp_salt( 'nonce' ) ), 0, 32 );
	}

	// --- Stats / log ---

	/**
	 * Increment a counter.
	 *
	 * @param string $key spins | coupons | gifts.
	 */
	private function bump_stat( $key ) {
		$stats         = self::stats();
		$stats[ $key ] = ( isset( $stats[ $key ] ) ? (int) $stats[ $key ] : 0 ) + 1;
		update_option( self::OPTION_STATS, $stats, false );
	}

	/**
	 * Counters.
	 *
	 * @return array{spins:int,coupons:int,gifts:int}
	 */
	public static function stats() {
		$stats = get_option( self::OPTION_STATS, array() );
		$stats = is_array( $stats ) ? $stats : array();
		return wp_parse_args(
			$stats,
			array(
				'spins'   => 0,
				'coupons' => 0,
				'gifts'   => 0,
			)
		);
	}

	/**
	 * Append to the capped spin log.
	 *
	 * @param string $email  E-mail (may be empty).
	 * @param string $label  Segment label.
	 * @param string $type   Result type.
	 * @param string $code   Coupon code.
	 */
	private function log( $email, $label, $type, $code ) {
		$log = self::log_entries();
		array_unshift(
			$log,
			array(
				'time'  => time(),
				'user'  => is_user_logged_in() ? get_current_user_id() : 0,
				'email' => $email,
				'label' => $label,
				'type'  => $type,
				'code'  => $code,
			)
		);
		update_option( self::OPTION_LOG, array_slice( $log, 0, self::LOG_LIMIT ), false );
	}

	/**
	 * Spin log (newest first).
	 *
	 * @return array[]
	 */
	public static function log_entries() {
		$log = get_option( self::OPTION_LOG, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Reset counters and log.
	 */
	public static function reset() {
		delete_option( self::OPTION_STATS );
		delete_option( self::OPTION_LOG );
	}
}
