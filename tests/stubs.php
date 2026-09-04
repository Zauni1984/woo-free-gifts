<?php
/**
 * Minimal WordPress / WooCommerce stubs to exercise the plugin logic without a WP install.
 */

define( 'ABSPATH', '/fake/' );
define( 'WFG_VERSION', '1.0.0-test' );
define( 'WFG_PLUGIN_DIR', $GLOBALS['WFG_ROOT'] . '/' );
define( 'WFG_PLUGIN_URL', 'https://example.test/wp-content/plugins/woo-free-gifts/' );
define( 'WFG_PLUGIN_BASENAME', 'woo-free-gifts/woo-free-gifts.php' );

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------
$GLOBALS['wp_filters'] = array();

function add_action( $hook, $cb, $priority = 10, $args = 1 ) { return add_filter( $hook, $cb, $priority, $args ); }
function add_filter( $hook, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['wp_filters'][ $hook ][ $priority ][] = array( 'cb' => $cb, 'args' => $args );
	return true;
}
function remove_action( $hook, $cb, $priority = 10 ) { return remove_filter( $hook, $cb, $priority ); }
function remove_filter( $hook, $cb, $priority = 10 ) {
	if ( empty( $GLOBALS['wp_filters'][ $hook ][ $priority ] ) ) { return false; }
	foreach ( $GLOBALS['wp_filters'][ $hook ][ $priority ] as $i => $entry ) {
		if ( $entry['cb'] == $cb ) { unset( $GLOBALS['wp_filters'][ $hook ][ $priority ][ $i ] ); return true; }
	}
	return false;
}
function do_action( $hook, ...$args ) {
	$GLOBALS['action_counts'][ $hook ] = ( $GLOBALS['action_counts'][ $hook ] ?? 0 ) + 1;
	if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) { return; }
	$prios = $GLOBALS['wp_filters'][ $hook ]; ksort( $prios );
	foreach ( $prios as $entries ) { foreach ( $entries as $e ) { call_user_func_array( $e['cb'], array_slice( $args, 0, $e['args'] ) ); } }
}
function apply_filters( $hook, $value, ...$args ) {
	if ( empty( $GLOBALS['wp_filters'][ $hook ] ) ) { return $value; }
	$prios = $GLOBALS['wp_filters'][ $hook ]; ksort( $prios );
	foreach ( $prios as $entries ) { foreach ( $entries as $e ) { $value = call_user_func_array( $e['cb'], array_merge( array( $value ), array_slice( $args, 0, $e['args'] - 1 ) ) ); } }
	return $value;
}
function did_action( $hook ) { return $GLOBALS['action_counts'][ $hook ] ?? 0; }

// ---------------------------------------------------------------------------
// Options / meta
// ---------------------------------------------------------------------------
$GLOBALS['wp_options'] = array();
$GLOBALS['user_meta']  = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['wp_options'] ) ? $GLOBALS['wp_options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['wp_options'][ $k ] = $v; return true; }
function add_option( $k, $v, $dep = '', $autoload = 'yes' ) { if ( ! array_key_exists( $k, $GLOBALS['wp_options'] ) ) { $GLOBALS['wp_options'][ $k ] = $v; } return true; }
function delete_option( $k ) { unset( $GLOBALS['wp_options'][ $k ] ); return true; }
function get_user_meta( $uid, $key, $single = false ) { return $GLOBALS['user_meta'][ $uid ][ $key ] ?? ''; }
function update_user_meta( $uid, $key, $v ) { $GLOBALS['user_meta'][ $uid ][ $key ] = $v; return true; }

// ---------------------------------------------------------------------------
// Sanitizers & misc
// ---------------------------------------------------------------------------
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function sanitize_key( $v ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $v ) ); }
function sanitize_hex_color( $v ) { return preg_match( '/^#([A-Fa-f0-9]{3}){1,2}$/', $v ) ? $v : null; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : stripslashes( (string) $v ); }
function wp_kses_post( $v ) { return (string) $v; }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function esc_url( $v ) { return $v; }
function esc_url_raw( $v ) { return $v; }
function esc_html__( $s, $d = null ) { return $s; }
function esc_attr__( $s, $d = null ) { return $s; }
function esc_html_e( $s, $d = null ) { echo $s; }
function esc_attr_e( $s, $d = null ) { echo $s; }
function __( $s, $d = null ) { return $s; }
function _e( $s, $d = null ) { echo $s; }
function _n( $s, $p, $n, $d = null ) { return 1 === $n ? $s : $p; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function wp_list_pluck( $list, $field ) { $o = array(); foreach ( $list as $k => $item ) { $o[ $k ] = is_object( $item ) ? $item->$field : $item[ $field ]; } return $o; }
function current_time( $type ) { return time(); }
function wp_roles() { return (object) array( 'roles' => array( 'administrator' => array( 'name' => 'Administrator' ), 'customer' => array( 'name' => 'Customer' ), 'wholesale' => array( 'name' => 'Wholesale' ) ) ); }
function is_admin() { return $GLOBALS['is_admin'] ?? false; }
function wp_doing_ajax() { return false; }
function wp_doing_cron() { return false; }
function get_current_user_id() { return $GLOBALS['current_user']->ID ?? 0; }
function wp_get_current_user() { return $GLOBALS['current_user']; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function get_term( $id, $tax ) { return (object) array( 'term_id' => $id, 'name' => 'Cat ' . $id ); }
function get_the_terms( $id, $tax ) { return $GLOBALS['product_terms'][ $id ] ?? false; }
function get_ancestors( $id, $tax, $type ) { return $GLOBALS['term_ancestors'][ $id ] ?? array(); }
function checked( $a, $b = true, $echo = true ) { $s = $a == $b ? ' checked="checked"' : ''; if ( $echo ) { echo $s; } return $s; }
function selected( $a, $b = true, $echo = true ) { $s = $a == $b ? ' selected="selected"' : ''; if ( $echo ) { echo $s; } return $s; }
function get_post_type( $id ) { return $GLOBALS['post_types'][ $id ] ?? false; }
function wp_delete_post( $id, $force = false ) { $GLOBALS['deleted_posts'][] = $id; return true; }
function plugin_dir_path( $f ) { return dirname( $f ) . '/'; }

class WP_Error {
	public $code; public $message;
	public function __construct( $c = '', $m = '' ) { $this->code = $c; $this->message = $m; }
	public function get_error_message() { return $this->message; }
	public function get_error_code() { return $this->code; }
}
class WP_User { public $ID = 0; public $roles = array(); public function __construct( $id = 0, $roles = array() ) { $this->ID = $id; $this->roles = $roles; } public function exists() { return $this->ID > 0; } }
$GLOBALS['current_user'] = new WP_User( 0 );

// ---------------------------------------------------------------------------
// WooCommerce
// ---------------------------------------------------------------------------
function wc_get_price_decimals() { return 2; }
function wc_price( $v ) { return number_format( (float) $v, 2, ',', '.' ) . ' €'; }
function wc_format_decimal( $v ) { return str_replace( ',', '.', (string) $v ); }
function wc_format_localized_price( $v ) { return str_replace( '.', ',', (string) $v ); }
function wc_format_list_of_items( $items ) { return implode( ', ', $items ); }
function wc_get_price_including_tax( $p, $a = array() ) { return $p->get_price() * ( $a['qty'] ?? 1 ) * 1.19; }
function wc_get_price_excluding_tax( $p, $a = array() ) { return $p->get_price() * ( $a['qty'] ?? 1 ); }
function wc_get_price_to_display( $p, $a = array() ) { return $p->get_price() * ( $a['qty'] ?? 1 ); }
function get_woocommerce_currency_symbol() { return '€'; }
function wc_get_cart_url() { return 'https://example.test/cart/'; }
$GLOBALS['wc_notices'] = array();
function wc_add_notice( $m, $type = 'success' ) { $GLOBALS['wc_notices'][ $type ][] = $m; }
function wc_get_notices( $type = '' ) { return $type ? ( $GLOBALS['wc_notices'][ $type ] ?? array() ) : $GLOBALS['wc_notices']; }
function wc_set_notices( $n ) { $GLOBALS['wc_notices'] = $n; }
$GLOBALS['wc_log'] = array();
function wc_get_logger() { return new class { public function log( $level, $msg, $ctx = array() ) { $GLOBALS['wc_log'][] = array( $level, $msg ); } }; }
function wc_placeholder_img( $size ) { return '<img src="placeholder">'; }

class WC_Product {
	protected $d;
	public function __construct( array $d ) {
		$this->d = array_merge( array( 'id' => 0, 'name' => 'Product', 'price' => 10.0, 'type' => 'simple', 'status' => 'publish', 'stock_status' => 'instock', 'manage_stock' => false, 'stock' => null, 'backorders' => false, 'parent' => 0, 'sold_individually' => false, 'purchasable' => true, 'attributes' => array(), 'virtual' => false, 'image_id' => 0, 'weight' => '', 'description' => '' ), $d );
	}
	public function get_id() { return $this->d['id']; }
	public function get_name() { return $this->d['name']; }
	public function get_price( $ctx = 'view' ) { return $this->d['price']; }
	public function set_price( $p ) { $this->d['price'] = $p; }
	public function get_type() { return $this->d['type']; }
	public function is_type( $t ) { return is_array( $t ) ? in_array( $this->d['type'], $t, true ) : $this->d['type'] === $t; }
	public function get_status() { return $this->d['status']; }
	public function is_purchasable() { return $this->d['purchasable'] && 'publish' === $this->d['status'] && '' !== (string) $this->d['price']; }
	public function is_in_stock() { return 'instock' === $this->d['stock_status']; }
	public function managing_stock() { return $this->d['manage_stock']; }
	public function backorders_allowed() { return $this->d['backorders']; }
	public function get_stock_quantity() { return $this->d['stock']; }
	public function has_enough_stock( $q ) { return ! $this->d['manage_stock'] || $this->d['stock'] >= $q; }
	public function get_stock_managed_by_id() { return $this->d['id']; }
	public function get_parent_id() { return $this->d['parent']; }
	public function is_sold_individually() { return $this->d['sold_individually']; }
	public function get_variation_attributes() { return $this->d['attributes']; }
	public function get_image( $size = '', $attr = array() ) { return '<img src="p' . $this->d['id'] . '">'; }
	public function get_formatted_name() { return $this->d['name'] . ' (#' . $this->d['id'] . ')'; }
	public function is_virtual() { return $this->d['virtual']; }
	public function get_image_id() { return $this->d['image_id']; }
	public function get_weight() { return $this->d['weight']; }
	public function get_description() { return $this->d['description']; }
}
$GLOBALS['products'] = array();
function wc_get_product( $id ) {
	$id = (int) $id;
	if ( ! isset( $GLOBALS['products'][ $id ] ) ) { return false; }
	return new WC_Product( $GLOBALS['products'][ $id ] ); // fresh instance like WC
}
function wfg_test_product( array $d ) { $GLOBALS['products'][ $d['id'] ] = $d; }

class WC_Session_Fake {
	private $data = array();
	public function get( $k, $d = null ) { return $this->data[ $k ] ?? $d; }
	public function set( $k, $v ) { if ( null === $v ) { unset( $this->data[ $k ] ); } else { $this->data[ $k ] = $v; } }
}

class WC_Cart {
	public $cart_contents = array();
	public $calc_count = 0;
	private $coupons = array();
	public function __construct() {
		add_action( 'woocommerce_add_to_cart', array( $this, 'calculate_totals' ), 20, 0 );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'calculate_totals' ), 20, 0 );
	}
	public function get_cart() { return $this->cart_contents; }
	public function is_empty() { return empty( $this->cart_contents ); }
	public function get_cart_item( $k ) { return $this->cart_contents[ $k ] ?? array(); }
	public function get_applied_coupons() { return $this->coupons; }
	public function get_cart_item_quantities() { $q = array(); foreach ( $this->cart_contents as $i ) { $id = $i['data']->get_stock_managed_by_id(); $q[ $id ] = ( $q[ $id ] ?? 0 ) + $i['quantity']; } return $q; }
	public function generate_cart_id( $pid, $vid = 0, $variation = array(), $data = array() ) { return md5( $pid . '_' . $vid . '_' . json_encode( $variation ) . '_' . json_encode( $data ) ); }
	public function add_to_cart( $product_id = 0, $quantity = 1, $variation_id = 0, $variation = array(), $cart_item_data = array() ) {
		$product = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $product || 'trash' === $product->get_status() ) { return false; }
		$key = $this->generate_cart_id( $product_id, $variation_id, $variation, $cart_item_data );
		if ( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variation, $cart_item_data ) ) { return false; }
		if ( ! $product->is_purchasable() ) { wc_add_notice( 'Sorry, this product cannot be purchased.', 'error' ); return false; }
		if ( $product->is_sold_individually() ) { foreach ( $this->cart_contents as $i ) { if ( $i['product_id'] == $product_id ) { wc_add_notice( 'You cannot add another to your cart.', 'error' ); return false; } } }
		if ( ! $product->has_enough_stock( $quantity + ( $this->get_cart_item_quantities()[ $product->get_stock_managed_by_id() ] ?? 0 ) ) ) { wc_add_notice( 'Not enough stock.', 'error' ); return false; }
		if ( isset( $this->cart_contents[ $key ] ) ) {
			$this->cart_contents[ $key ]['quantity'] += $quantity;
		} else {
			$this->cart_contents[ $key ] = array_merge( $cart_item_data, array( 'key' => $key, 'product_id' => $product_id, 'variation_id' => $variation_id, 'variation' => $variation, 'quantity' => $quantity, 'data' => $product ) );
		}
		do_action( 'woocommerce_add_to_cart', $key, $product_id, $quantity, $variation_id, $variation, $cart_item_data );
		return $key;
	}
	public function remove_cart_item( $key ) {
		if ( ! isset( $this->cart_contents[ $key ] ) ) { return false; }
		$this->removed[ $key ] = $this->cart_contents[ $key ];
		do_action( 'woocommerce_remove_cart_item', $key, $this );
		unset( $this->cart_contents[ $key ] );
		do_action( 'woocommerce_cart_item_removed', $key, $this );
		return true;
	}
	public function restore_cart_item( $key ) {
		if ( ! isset( $this->removed[ $key ] ) ) { return false; }
		$this->cart_contents[ $key ] = $this->removed[ $key ];
		unset( $this->removed[ $key ] );
		do_action( 'woocommerce_cart_item_restored', $key, $this );
		$this->calculate_totals();
		return true;
	}
	public $removed = array();
	public function set_quantity( $key, $qty, $refresh = true ) { if ( $qty <= 0 ) { return $this->remove_cart_item( $key ); } $this->cart_contents[ $key ]['quantity'] = $qty; if ( $refresh ) { $this->calculate_totals(); } return true; }
	public function empty_cart() { $this->cart_contents = array(); do_action( 'woocommerce_cart_emptied' ); }
	public function calculate_totals() {
		++$this->calc_count;
		if ( $this->calc_count > 200 ) { throw new RuntimeException( 'calculate_totals loop detected' ); }
		if ( $this->is_empty() ) { return; }
		do_action( 'woocommerce_before_calculate_totals', $this );
		foreach ( $this->cart_contents as $k => &$item ) {
			$item['line_subtotal']     = $item['data']->get_price() * $item['quantity'];
			$item['line_subtotal_tax'] = $item['line_subtotal'] * 0.19;
			$item['line_total']        = $item['line_subtotal'];
			$item['line_tax']          = $item['line_subtotal_tax'];
		}
		unset( $item );
		do_action( 'woocommerce_after_calculate_totals', $this );
	}
	public function subtotal_paid() { $t = 0; foreach ( $this->cart_contents as $i ) { if ( empty( $i['wfg_gift'] ) ) { $t += $i['line_subtotal']; } } return $t; }
}

class WooCommerce_Fake { public $cart; public $session; public function is_rest_api_request() { return false; } }
function WC() { return $GLOBALS['wc']; }
function wfg_test_reset_wc() {
	$GLOBALS['wp_filters'] = array_filter( $GLOBALS['wp_filters'], function ( $k ) { return true; }, ARRAY_FILTER_USE_KEY );
	$GLOBALS['wc'] = new WooCommerce_Fake();
	$GLOBALS['wc']->session = new WC_Session_Fake();
	$GLOBALS['wc']->cart = new WC_Cart();
	$GLOBALS['wc_notices'] = array();
	return $GLOBALS['wc']->cart;
}

// ---------------------------------------------------------------------------
// Rendering stubs (templates + admin views)
// ---------------------------------------------------------------------------
function wc_get_template( $name, $args = array(), $path = '', $default_path = '' ) {
	extract( $args );
	include $default_path . $name;
}
function add_query_arg( $args, $url = '' ) { return $url . ( strpos( $url, '?' ) === false ? '?' : '&' ) . http_build_query( $args ); }
function remove_query_arg( $keys, $url = '' ) { return 'https://example.test/cart/'; }
function wp_nonce_url( $url, $action, $name = '_wpnonce' ) { return $url . '&' . $name . '=nonce'; }
function wp_create_nonce( $a ) { return 'nonce'; }
function wp_nonce_field( $a ) { echo '<input type="hidden" name="_wpnonce" value="nonce">'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function submit_button( $t ) { echo '<p><button type="submit">' . $t . '</button></p>'; }
function get_terms( $args ) { return array( (object) array( 'term_id' => 100, 'name' => 'Seeds' ), (object) array( 'term_id' => 200, 'name' => 'Books' ) ); }
function translate_user_role( $n ) { return $n; }
function wp_get_attachment_image_url( $id, $size ) { return 'https://example.test/img' . $id . '.jpg'; }
function wp_get_attachment_image( $id, $size, $icon = false, $attr = array() ) { return '<img src="img' . $id . '">'; }
function wp_editor( $content, $id, $s = array() ) { echo '<textarea name="' . $s['textarea_name'] . '">' . esc_textarea( $content ) . '</textarea>'; }
function esc_textarea( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); }
function number_format_i18n( $n ) { return number_format( $n ); }
function wp_date( $f, $ts ) { return date( $f, $ts ); }
function wpautop( $t ) { return '<p>' . $t . '</p>'; }
function wp_safe_redirect( $u ) { $GLOBALS['redirect'] = $u; }
function get_object_taxonomies( $t ) { return array( 'product_cat', 'product_tag' ); }
