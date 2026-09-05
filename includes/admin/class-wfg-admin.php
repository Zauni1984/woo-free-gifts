<?php
/**
 * Admin UI: menu, tabs, form handlers.
 *
 * @package WooFreeGifts
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WFG_Admin
 */
final class WFG_Admin {

	const PAGE = 'wfg-free-gifts';
	const CAP  = 'manage_woocommerce';

	/**
	 * Settings.
	 *
	 * @var WFG_Settings
	 */
	private $settings;

	/**
	 * Rules.
	 *
	 * @var WFG_Rules
	 */
	private $rules;

	/**
	 * Gift products.
	 *
	 * @var WFG_Gift_Products
	 */
	private $gift_products;

	/**
	 * Constructor.
	 *
	 * @param WFG_Settings      $settings      Settings.
	 * @param WFG_Rules         $rules         Rules.
	 * @param WFG_Gift_Products $gift_products Gift products.
	 */
	public function __construct( WFG_Settings $settings, WFG_Rules $rules, WFG_Gift_Products $gift_products ) {
		$this->settings      = $settings;
		$this->rules         = $rules;
		$this->gift_products = $gift_products;

		add_action( 'admin_menu', array( $this, 'menu' ), 60 );
		add_filter( 'woocommerce_screen_ids', array( $this, 'screen_ids' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ), 20 );
		add_action( 'admin_notices', array( $this, 'notices' ) );

		add_action( 'admin_post_wfg_save_rule', array( $this, 'handle_save_rule' ) );
		add_action( 'admin_post_wfg_delete_rule', array( $this, 'handle_delete_rule' ) );
		add_action( 'admin_post_wfg_toggle_rule', array( $this, 'handle_toggle_rule' ) );
		add_action( 'admin_post_wfg_duplicate_rule', array( $this, 'handle_duplicate_rule' ) );
		add_action( 'admin_post_wfg_save_settings', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wfg_reset_stats', array( $this, 'handle_reset_stats' ) );
		add_action( 'admin_post_wfg_check_updates', array( $this, 'handle_check_updates' ) );

		// Mark custom gift products in the product list when revealed.
		add_filter( 'display_post_states', array( $this, 'post_states' ), 10, 2 );
	}

	// --- Menu / assets ---

	/**
	 * Register the submenu page under WooCommerce.
	 */
	public function menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Free Gifts', 'woo-free-gifts' ),
			__( 'Free Gifts', 'woo-free-gifts' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Let WooCommerce load its admin assets (enhanced select etc.) on our page.
	 *
	 * @param string[] $ids Screen ids.
	 * @return string[]
	 */
	public function screen_ids( $ids ) {
		$ids[] = 'woocommerce_page_' . self::PAGE;
		return $ids;
	}

	/**
	 * Is the current screen ours?
	 *
	 * @return bool
	 */
	private function is_our_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && 'woocommerce_page_' . self::PAGE === $screen->id;
	}

	/**
	 * Enqueue admin assets.
	 */
	public function assets() {
		if ( ! $this->is_our_screen() ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'wp-color-picker' );

		wp_enqueue_style( 'wfg-admin', WFG_PLUGIN_URL . 'assets/css/admin.css', array( 'woocommerce_admin_styles' ), WFG_VERSION );
		wp_enqueue_script( 'wfg-admin', WFG_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'wc-enhanced-select', 'wp-color-picker' ), WFG_VERSION, true );
		wp_localize_script(
			'wfg-admin',
			'wfgAdmin',
			array(
				'i18n' => array(
					'confirmDelete' => __( 'Delete this rule? Custom gifts attached to it are moved to the trash.', 'woo-free-gifts' ),
					'chooseImage'   => __( 'Choose gift image', 'woo-free-gifts' ),
					'useImage'      => __( 'Use this image', 'woo-free-gifts' ),
					'removeRow'     => __( 'Remove', 'woo-free-gifts' ),
					'minSegments'   => __( 'The wheel needs at least 2 segments.', 'woo-free-gifts' ),
				),
			)
		);
	}

	/**
	 * Label custom gifts in the product list.
	 *
	 * @param string[] $states States.
	 * @param WP_Post  $post   Post.
	 * @return string[]
	 */
	public function post_states( $states, $post ) {
		if ( $post instanceof WP_Post && 'product' === $post->post_type && WFG_Gift_Products::is_custom_gift( $post->ID ) ) {
			$states['wfg_gift'] = __( 'Free gift (hidden)', 'woo-free-gifts' );
		}
		return $states;
	}

	// --- Page rendering ---

	/**
	 * Current tab.
	 *
	 * @return string
	 */
	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'rules'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return in_array( $tab, array( 'rules', 'settings', 'popup', 'wheel', 'stats' ), true ) ? $tab : 'rules';
	}

	/**
	 * Tab URL helper.
	 *
	 * @param string $tab  Tab.
	 * @param array  $args Extra args.
	 * @return string
	 */
	public static function url( $tab = 'rules', array $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE,
					'tab'  => $tab,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Nonce-protected action URL.
	 *
	 * @param string $action  admin-post action.
	 * @param array  $args    Args.
	 * @return string
	 */
	public static function action_url( $action, array $args = array() ) {
		$url = add_query_arg( array_merge( array( 'action' => $action ), $args ), admin_url( 'admin-post.php' ) );
		return wp_nonce_url( $url, $action );
	}

	/**
	 * Route to the right view.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-free-gifts' ) );
		}

		$tab  = $this->current_tab();
		$view = $tab;
		$data = array(
			'settings' => $this->settings,
			'rules'    => $this->rules,
			'tab'      => $tab,
		);

		if ( 'rules' === $tab ) {
			$action = isset( $_GET['action'] ) ? sanitize_key( $_GET['action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'edit' === $action || 'new' === $action ) {
				$rule_id = isset( $_GET['rule'] ) ? absint( $_GET['rule'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$rule    = $rule_id ? $this->rules->get( $rule_id ) : null;
				if ( 'edit' === $action && ! $rule ) {
					wp_die( esc_html__( 'Rule not found.', 'woo-free-gifts' ) );
				}
				$data['rule'] = $rule ? $rule : WFG_Rules::defaults();
				$view         = 'rule-edit';
			} else {
				$data['stats'] = WFG_Order::stats();
				$view          = 'rules-list';
			}
		} elseif ( 'stats' === $tab ) {
			$data['stats']       = WFG_Order::stats();
			$data['wheel_stats'] = WFG_Wheel::stats();
			$data['wheel_log']   = WFG_Wheel::log_entries();
		}

		echo '<div class="wrap wfg-wrap">';
		echo '<h1 class="wp-heading-inline">' . esc_html__( 'Woo Free Gifts', 'woo-free-gifts' ) . '</h1>';
		if ( 'rules-list' === $view ) {
			echo ' <a href="' . esc_url( self::url( 'rules', array( 'action' => 'new' ) ) ) . '" class="page-title-action">' . esc_html__( 'Add rule', 'woo-free-gifts' ) . '</a>';
		}
		echo '<hr class="wp-header-end">';

		$this->render_tabs( $tab );
		$this->view( $view, $data );
		echo '</div>';
	}

	/**
	 * Tab navigation.
	 *
	 * @param string $current Current tab.
	 */
	private function render_tabs( $current ) {
		$tabs = array(
			'rules'    => __( 'Gift rules', 'woo-free-gifts' ),
			'settings' => __( 'Settings', 'woo-free-gifts' ),
			'popup'    => __( 'Popup', 'woo-free-gifts' ),
			'wheel'    => __( 'Wheel of fortune', 'woo-free-gifts' ),
			'stats'    => __( 'Statistics', 'woo-free-gifts' ),
		);
		echo '<nav class="nav-tab-wrapper woo-nav-tab-wrapper wfg-tabs">';
		foreach ( $tabs as $key => $label ) {
			printf(
				'<a href="%s" class="nav-tab%s">%s</a>',
				esc_url( self::url( $key ) ),
				$key === $current ? ' nav-tab-active' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}

	/**
	 * Include a view file.
	 *
	 * @param string $name View name.
	 * @param array  $data Variables.
	 */
	private function view( $name, array $data ) {
		$file = WFG_PLUGIN_DIR . 'includes/admin/views/' . $name . '.php';
		if ( ! is_readable( $file ) ) {
			return;
		}
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- controlled view variables.
		include $file;
	}

	// --- Notices ---

	/**
	 * Show feedback after redirects.
	 */
	public function notices() {
		if ( ! $this->is_our_screen() ) {
			return;
		}
		$this->low_stock_notice();
		if ( empty( $_GET['wfg_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$code     = sanitize_key( $_GET['wfg_msg'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'saved'      => array( 'success', __( 'Rule saved.', 'woo-free-gifts' ) ),
			'deleted'    => array( 'success', __( 'Rule deleted.', 'woo-free-gifts' ) ),
			'duplicated' => array( 'success', __( 'Rule duplicated. It is disabled until you enable it.', 'woo-free-gifts' ) ),
			'toggled'    => array( 'success', __( 'Rule updated.', 'woo-free-gifts' ) ),
			'settings'   => array( 'success', __( 'Settings saved.', 'woo-free-gifts' ) ),
			'stats'      => array( 'success', __( 'Statistics reset.', 'woo-free-gifts' ) ),
			'updates'    => array( 'success', __( 'Update check refreshed. If a newer release exists it is listed under Plugins.', 'woo-free-gifts' ) ),
			'nogift'     => array( 'error', __( 'The rule was not saved: add at least one gift.', 'woo-free-gifts' ) ),
			'error'      => array( 'error', __( 'Something went wrong. Please try again.', 'woo-free-gifts' ) ),
		);
		if ( ! isset( $messages[ $code ] ) ) {
			return;
		}
		$detail = '';
		if ( ! empty( $_GET['wfg_detail'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$detail = ' ' . sanitize_text_field( wp_unslash( $_GET['wfg_detail'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $code ][0] ),
			esc_html( $messages[ $code ][1] . $detail )
		);
	}

	/**
	 * Warn about gifts that are (almost) exhausted.
	 */
	private function low_stock_notice() {
		$threshold = (int) $this->settings->get( 'low_stock_threshold' );
		$lines     = array();
		$plugin    = wfg();
		if ( ! $plugin || ! $plugin->engine ) {
			return;
		}
		foreach ( $this->rules->all() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			$left = $plugin->engine->remaining_units( $rule );
			if ( null === $left || $left > $threshold ) {
				continue;
			}
			$lines[] = sprintf(
				'<a href="%s">%s</a>: %s',
				esc_url(
					self::url(
						'rules',
						array(
							'action' => 'edit',
							'rule'   => $rule['id'],
						)
					)
				),
				esc_html( $rule['title'] ),
				0 === $left ? esc_html__( 'exhausted – the rule is no longer shown to customers', 'woo-free-gifts' ) : esc_html( sprintf( /* translators: %d: units left */ _n( 'only %d unit left', 'only %d units left', $left, 'woo-free-gifts' ), $left ) )
			);
		}
		if ( empty( $lines ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Gift stock is running low:', 'woo-free-gifts' ) . '</strong></p><ul class="wfg-notice-list"><li>' . implode( '</li><li>', $lines ) . '</li></ul></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above.
	}

	/**
	 * Redirect back with a message.
	 *
	 * @param string $url    Target.
	 * @param string $code   Message code.
	 * @param string $detail Optional detail text.
	 */
	private function redirect( $url, $code, $detail = '' ) {
		$args = array( 'wfg_msg' => $code );
		if ( '' !== $detail ) {
			$args['wfg_detail'] = rawurlencode( $detail );
		}
		wp_safe_redirect( add_query_arg( $args, $url ) );
		exit;
	}

	/**
	 * Shared guard for handlers.
	 *
	 * @param string $action Nonce action.
	 * @param string $method GET|POST.
	 */
	private function guard( $action, $method = 'POST' ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'woo-free-gifts' ), 403 );
		}
		if ( 'POST' === $method ) {
			check_admin_referer( $action );
		} else {
			check_admin_referer( $action, '_wpnonce' );
		}
	}

	// --- Handlers ---

	/**
	 * Save a rule (create or update).
	 */
	public function handle_save_rule() {
		$this->guard( 'wfg_save_rule' );

		$raw = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification -- nonce verified in guard(); sanitized in WFG_Rules::sanitize().
		$raw = $this->prepare_raw_rule( $raw );

		$rule_id  = isset( $raw['id'] ) ? absint( $raw['id'] ) : 0;
		$existing = $rule_id ? $this->rules->get( $rule_id ) : null;
		if ( $rule_id && ! $existing ) {
			$this->redirect( self::url( 'rules' ), 'error' );
		}

		// Resolve gift rows (product picks + custom gifts).
		$gift_rows = isset( $_POST['gifts'] ) && is_array( $_POST['gifts'] ) ? wp_unslash( $_POST['gifts'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification -- nonce verified in guard(); sanitized in resolve_gifts().
		$resolved  = $this->resolve_gifts( $gift_rows );
		if ( is_wp_error( $resolved ) ) {
			$this->redirect(
				self::url(
					'rules',
					$rule_id ? array(
						'action' => 'edit',
						'rule'   => $rule_id,
					) : array( 'action' => 'new' )
				),
				'error',
				$resolved->get_error_message()
			);
		}
		$raw['gifts'] = $resolved;

		$clean = WFG_Rules::sanitize( $raw );
		if ( '' === $clean['title'] ) {
			$clean['title'] = __( 'Gift rule', 'woo-free-gifts' );
		}
		if ( empty( $clean['gifts'] ) ) {
			$this->redirect(
				self::url(
					'rules',
					$rule_id ? array(
						'action' => 'edit',
						'rule'   => $rule_id,
					) : array( 'action' => 'new' )
				),
				'nogift'
			);
		}

		// Trash custom gift products that are no longer referenced by this rule.
		if ( $existing ) {
			$keep = wp_list_pluck( $clean['gifts'], 'product_id' );
			foreach ( $existing['gifts'] as $old ) {
				if ( ! empty( $old['custom'] ) && ! in_array( (int) $old['product_id'], array_map( 'intval', $keep ), true ) ) {
					$this->gift_products->remove_custom( $old['product_id'] );
				}
			}
		}

		$id = $this->rules->save( $clean );
		$this->redirect(
			self::url(
				'rules',
				array(
					'action' => 'edit',
					'rule'   => $id,
				)
			),
			'saved'
		);
	}

	/**
	 * Normalize checkbox/array inputs from the rule form.
	 *
	 * @param array $raw Raw rule.
	 * @return array
	 */
	private function prepare_raw_rule( array $raw ) {
		foreach ( array( 'required_products', 'required_categories', 'user_roles' ) as $key ) {
			if ( ! isset( $raw[ $key ] ) || ! is_array( $raw[ $key ] ) ) {
				$raw[ $key ] = array();
			}
		}
		return $raw;
	}

	/**
	 * Turn posted gift rows into [product_id, qty, custom] entries.
	 *
	 * @param array $rows Posted rows.
	 * @return array|WP_Error
	 */
	private function resolve_gifts( array $rows ) {
		$gifts = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type = isset( $row['type'] ) && 'custom' === $row['type'] ? 'custom' : 'product';
			$qty  = isset( $row['qty'] ) ? max( 1, min( 99, absint( $row['qty'] ) ) ) : 1;

			if ( 'product' === $type ) {
				$pid = isset( $row['product_id'] ) ? absint( is_array( $row['product_id'] ) ? reset( $row['product_id'] ) : $row['product_id'] ) : 0;
				if ( ! $pid ) {
					continue;
				}
				$product = wc_get_product( $pid );
				if ( ! $product instanceof WC_Product || WFG_Gift_Products::is_custom_gift( $pid ) ) {
					continue;
				}
				if ( $product->is_type( 'variable' ) ) {
					return new WP_Error( 'wfg_variable', __( 'Please pick a specific variation instead of the variable parent product.', 'woo-free-gifts' ) );
				}
				$gifts[] = array(
					'product_id' => $pid,
					'qty'        => $qty,
					'custom'     => false,
				);
				continue;
			}

			// Custom gift.
			$name = isset( $row['custom_name'] ) ? sanitize_text_field( $row['custom_name'] ) : '';
			if ( '' === $name ) {
				continue; // Empty row.
			}
			$existing_id = isset( $row['custom_id'] ) ? absint( $row['custom_id'] ) : 0;
			$result      = $this->gift_products->save_custom(
				array(
					'name'        => $name,
					'description' => isset( $row['custom_desc'] ) ? $row['custom_desc'] : '',
					'image_id'    => isset( $row['custom_image_id'] ) ? absint( $row['custom_image_id'] ) : 0,
					'weight'      => isset( $row['custom_weight'] ) ? $row['custom_weight'] : '',
					'virtual'     => isset( $row['custom_virtual'] ) ? ! empty( $row['custom_virtual'] ) : $this->settings->is( 'custom_gift_virtual' ),
					'stock'       => isset( $row['custom_stock'] ) ? sanitize_text_field( $row['custom_stock'] ) : '',
				),
				$existing_id
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$gifts[] = array(
				'product_id' => (int) $result,
				'qty'        => $qty,
				'custom'     => true,
			);
		}
		return $gifts;
	}

	/**
	 * Delete a rule.
	 */
	public function handle_delete_rule() {
		$this->guard( 'wfg_delete_rule', 'GET' );
		$rule_id = isset( $_GET['rule'] ) ? absint( $_GET['rule'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in guard().
		$rule    = $this->rules->get( $rule_id );
		if ( $rule ) {
			foreach ( $rule['gifts'] as $gift ) {
				if ( ! empty( $gift['custom'] ) ) {
					$this->gift_products->remove_custom( $gift['product_id'] );
				}
			}
			$this->rules->delete( $rule_id );
		}
		$this->redirect( self::url( 'rules' ), $rule ? 'deleted' : 'error' );
	}

	/**
	 * Enable/disable a rule.
	 */
	public function handle_toggle_rule() {
		$this->guard( 'wfg_toggle_rule', 'GET' );
		$rule_id = isset( $_GET['rule'] ) ? absint( $_GET['rule'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in guard().
		$rule    = $this->rules->get( $rule_id );
		if ( $rule ) {
			$this->rules->set_enabled( $rule_id, empty( $rule['enabled'] ) );
		}
		$this->redirect( self::url( 'rules' ), $rule ? 'toggled' : 'error' );
	}

	/**
	 * Duplicate a rule (custom gifts are copied into new hidden products).
	 */
	public function handle_duplicate_rule() {
		$this->guard( 'wfg_duplicate_rule', 'GET' );
		$rule_id = isset( $_GET['rule'] ) ? absint( $_GET['rule'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification -- verified in guard().
		$rule    = $this->rules->get( $rule_id );
		if ( ! $rule ) {
			$this->redirect( self::url( 'rules' ), 'error' );
		}

		$copy            = $rule;
		$copy['id']      = 0;
		$copy['enabled'] = false;
		/* translators: %s: original rule title */
		$copy['title'] = sprintf( __( '%s (copy)', 'woo-free-gifts' ), $rule['title'] );
		$copy['gifts'] = array();

		foreach ( $rule['gifts'] as $gift ) {
			if ( empty( $gift['custom'] ) ) {
				$copy['gifts'][] = $gift;
				continue;
			}
			$source = wc_get_product( $gift['product_id'] );
			if ( ! $source ) {
				continue;
			}
			$new_id = $this->gift_products->save_custom(
				array(
					'name'        => $source->get_name(),
					'description' => $source->get_description(),
					'image_id'    => $source->get_image_id(),
					'weight'      => $source->get_weight(),
					'virtual'     => $source->is_virtual(),
					'stock'       => $source->managing_stock() ? (string) (int) $source->get_stock_quantity() : '',
				)
			);
			if ( ! is_wp_error( $new_id ) ) {
				$copy['gifts'][] = array(
					'product_id' => (int) $new_id,
					'qty'        => $gift['qty'],
					'custom'     => true,
				);
			}
		}

		$new = $this->rules->save( $copy );
		$this->redirect(
			self::url(
				'rules',
				array(
					'action' => 'edit',
					'rule'   => $new,
				)
			),
			'duplicated'
		);
	}

	/**
	 * Save global settings (settings + popup tabs share this handler).
	 */
	public function handle_save_settings() {
		$this->guard( 'wfg_save_settings' );

		$posted = isset( $_POST['wfg'] ) && is_array( $_POST['wfg'] ) ? wp_unslash( $_POST['wfg'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification -- nonce verified in guard(); sanitized in WFG_Settings::sanitize().
		$stored = get_option( WFG_Settings::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();

		// Tabs post only their own fields; everything else keeps its stored value.
		$merged = array_merge( $stored, $posted );

		// Fewer than two valid wheel segments keep the previously stored wheel instead of resetting it.
		if ( isset( $posted['wheel_segments'] ) ) {
			$previous                 = isset( $stored['wheel_segments'] ) && is_array( $stored['wheel_segments'] ) ? $stored['wheel_segments'] : WFG_Settings::default_segments();
			$merged['wheel_segments'] = WFG_Settings::sanitize_segments( $posted['wheel_segments'], $previous );
		}
		$this->settings->save( $merged );

		$tab = isset( $_POST['wfg_tab'] ) ? sanitize_key( $_POST['wfg_tab'] ) : 'settings'; // phpcs:ignore WordPress.Security.NonceVerification -- verified in guard().
		$this->redirect( self::url( in_array( $tab, array( 'settings', 'popup', 'wheel' ), true ) ? $tab : 'settings' ), 'settings' );
	}

	/**
	 * Manual update check.
	 */
	public function handle_check_updates() {
		$this->guard( 'wfg_check_updates', 'GET' );
		$plugin = wfg();
		if ( $plugin && $plugin->updater ) {
			$plugin->updater->force_check();
		}
		$this->redirect( self::url( 'settings' ), 'updates' );
	}

	/**
	 * Reset statistics.
	 */
	public function handle_reset_stats() {
		$this->guard( 'wfg_reset_stats', 'GET' );
		WFG_Order::reset_stats();
		WFG_Wheel::reset();
		$this->redirect( self::url( 'stats' ), 'stats' );
	}

	// --- View helpers ---

	/**
	 * Options HTML for a preselected product multi-select.
	 *
	 * @param int[] $ids Product ids.
	 * @return string
	 */
	public static function product_options( array $ids ) {
		$html = '';
		foreach ( $ids as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$html .= '<option value="' . esc_attr( $id ) . '" selected="selected">' . esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ) . '</option>';
		}
		return $html;
	}

	/**
	 * Checkbox with hidden fallback so unchecked boxes are posted as 0.
	 *
	 * @param string $name    Field name.
	 * @param bool   $checked Checked.
	 * @param string $label   Label.
	 * @return string
	 */
	public static function checkbox( $name, $checked, $label ) {
		return sprintf(
			'<input type="hidden" name="%1$s" value="0"><label><input type="checkbox" name="%1$s" value="1"%2$s> %3$s</label>',
			esc_attr( $name ),
			checked( (bool) $checked, true, false ),
			esc_html( $label )
		);
	}
}
