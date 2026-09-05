<?php
/**
 * Plugin updates from GitHub releases.
 *
 * WordPress core (5.8+) routes plugins with an `Update URI` header to the
 * `update_plugins_{hostname}` filter. We answer it with the latest GitHub release
 * of the repository, so the shop shows "update available" and installs the release
 * ZIP exactly like a wordpress.org plugin. Private repositories work with a token.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Updater
 */
final class WFG_Updater {

	const REPO      = 'Zauni1984/woo-free-gifts';
	const SLUG      = 'woo-free-gifts';
	const TRANSIENT = 'wfg_latest_release';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	/**
	 * Settings.
	 *
	 * @var WFG_Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings $settings Settings.
	 */
	public function __construct( WFG_Settings $settings ) {
		$this->settings = $settings;

		add_filter( 'update_plugins_github.com', array( $this, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );
		add_filter( 'upgrader_source_selection', array( $this, 'fix_folder_name' ), 10, 4 );
		add_filter( 'http_request_args', array( $this, 'authorize_download' ), 10, 2 );
		add_action( 'upgrader_process_complete', array( $this, 'flush' ), 10, 0 );
	}

	// --- WordPress hooks ---

	/**
	 * Tell WordPress whether a newer release exists.
	 *
	 * @param array|false $update      Update data so far.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public function check( $update, $plugin_data, $plugin_file ) {
		unset( $plugin_data );
		if ( WFG_PLUGIN_BASENAME !== $plugin_file ) {
			return $update;
		}
		$release = $this->release();
		if ( ! $release || ! version_compare( $release['version'], WFG_VERSION, '>' ) ) {
			return false;
		}
		return array(
			'id'           => 'github.com/' . self::REPO,
			'slug'         => self::SLUG,
			'plugin'       => $plugin_file,
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'tested'       => '6.8',
			'requires_php' => WFG_MIN_PHP,
			'icons'        => array(),
			'banners'      => array(),
		);
	}

	/**
	 * "View details" modal on the plugins screen.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   Arguments.
	 * @return false|object
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || self::SLUG !== $args->slug ) {
			return $result;
		}
		$release = $this->release();
		if ( ! $release ) {
			return $result;
		}
		$info                = new stdClass();
		$info->name          = 'Woo Free Gifts Premium';
		$info->slug          = self::SLUG;
		$info->version       = $release['version'];
		$info->author        = '<a href="https://github.com/Zauni1984">Stefan Zaunreither</a>';
		$info->homepage      = 'https://github.com/' . self::REPO;
		$info->download_link = $release['package'];
		$info->requires      = '6.2';
		$info->requires_php  = WFG_MIN_PHP;
		$info->last_updated  = $release['published'];
		$info->sections      = array(
			'description' => esc_html__( 'Premium free gift engine for WooCommerce with cart-value gifts, buy-X-get-Y, custom hidden gifts, promo popup and a daily wheel of fortune.', 'woo-free-gifts' ),
			'changelog'   => $release['changelog'],
		);
		return $info;
	}

	/**
	 * GitHub ZIPs may extract to "Owner-repo-hash/"; the plugin folder must stay "woo-free-gifts".
	 *
	 * @param string      $source        Extracted source path.
	 * @param string      $remote_source Remote source path.
	 * @param WP_Upgrader $upgrader      Upgrader.
	 * @param array       $hook_extra    Extra data.
	 * @return string|WP_Error
	 */
	public function fix_folder_name( $source, $remote_source, $upgrader, $hook_extra ) {
		unset( $upgrader );
		if ( empty( $hook_extra['plugin'] ) || WFG_PLUGIN_BASENAME !== $hook_extra['plugin'] ) {
			return $source;
		}
		global $wp_filesystem;
		$target = trailingslashit( $remote_source ) . self::SLUG . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $target ) || ! $wp_filesystem ) {
			return $source;
		}
		if ( $wp_filesystem->move( $source, $target, true ) ) {
			return $target;
		}
		return new WP_Error( 'wfg_rename_failed', __( 'Could not rename the update folder.', 'woo-free-gifts' ) );
	}

	/**
	 * Add the token when WordPress downloads a release asset from the GitHub API (private repos).
	 *
	 * @param array  $args Request args.
	 * @param string $url  URL.
	 * @return array
	 */
	public function authorize_download( $args, $url ) {
		$token = $this->token();
		if ( '' === $token || false === strpos( $url, 'api.github.com/repos/' . self::REPO ) ) {
			return $args;
		}
		$args['headers'] = isset( $args['headers'] ) && is_array( $args['headers'] ) ? $args['headers'] : array();
		if ( empty( $args['headers']['Authorization'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $token;
		}
		if ( false !== strpos( $url, '/releases/assets/' ) ) {
			$args['headers']['Accept'] = 'application/octet-stream';
		}
		return $args;
	}

	/**
	 * Forget the cached release (after an update or on manual re-check).
	 */
	public function flush() {
		delete_site_transient( self::TRANSIENT );
	}

	/**
	 * Manual "check now": drop caches so the next plugins-screen load asks GitHub again.
	 */
	public function force_check() {
		$this->flush();
		delete_site_transient( 'update_plugins' );
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
	}

	// --- GitHub ---

	/**
	 * Latest release, cached.
	 *
	 * @return array|null version, url, package, changelog, published
	 */
	public function release() {
		$cached = get_site_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['version'] ) ) {
			return $cached;
		}
		if ( is_array( $cached ) && isset( $cached['none'] ) ) {
			return null; // Negative cache.
		}

		$release = $this->fetch();
		set_site_transient( self::TRANSIENT, $release ? $release : array( 'none' => true ), $release ? self::CACHE_TTL : HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Query the GitHub API.
	 *
	 * @return array|null
	 */
	private function fetch() {
		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'woo-free-gifts/' . WFG_VERSION . '; ' . home_url(),
		);
		$token   = $this->token();
		if ( '' !== $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => $headers,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			WFG_Logger::debug( 'Update check failed: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ) );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return self::parse( is_array( $data ) ? $data : array(), '' !== $token );
	}

	/**
	 * Normalize a GitHub release payload.
	 *
	 * @param array $data    Release JSON.
	 * @param bool  $use_api Use API asset URLs (token available).
	 * @return array|null
	 */
	public static function parse( array $data, $use_api = false ) {
		if ( empty( $data['tag_name'] ) || ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			return null;
		}
		$version = ltrim( trim( (string) $data['tag_name'] ), 'vV' );
		if ( ! preg_match( '/^\d+(\.\d+){1,3}$/', $version ) ) {
			return null;
		}

		$package = '';
		if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
			foreach ( $data['assets'] as $asset ) {
				if ( ! empty( $asset['name'] ) && '.zip' === substr( $asset['name'], -4 ) ) {
					$package = $use_api && ! empty( $asset['url'] ) ? $asset['url'] : ( isset( $asset['browser_download_url'] ) ? $asset['browser_download_url'] : '' );
					if ( 0 === strpos( $asset['name'], self::SLUG ) ) {
						break; // Prefer our own build.
					}
				}
			}
		}
		if ( '' === $package && ! empty( $data['zipball_url'] ) ) {
			$package = $data['zipball_url'];
		}
		if ( '' === $package ) {
			return null;
		}

		$body = isset( $data['body'] ) ? (string) $data['body'] : '';

		return array(
			'version'   => $version,
			'url'       => isset( $data['html_url'] ) ? esc_url_raw( $data['html_url'] ) : 'https://github.com/' . self::REPO . '/releases',
			'package'   => esc_url_raw( $package ),
			'changelog' => '' !== trim( $body ) ? wp_kses_post( wpautop( esc_html( $body ) ) ) : '',
			'published' => isset( $data['published_at'] ) ? sanitize_text_field( $data['published_at'] ) : '',
		);
	}

	/**
	 * Optional GitHub token (needed for private repositories).
	 *
	 * @return string
	 */
	private function token() {
		if ( defined( 'WFG_GITHUB_TOKEN' ) && WFG_GITHUB_TOKEN ) {
			return (string) WFG_GITHUB_TOKEN;
		}
		return (string) $this->settings->get( 'update_token', '' );
	}
}
