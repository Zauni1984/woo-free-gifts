<?php
/**
 * Functional tests for the gift engine + cart sync using stubs.
 */
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );
$GLOBALS['WFG_ROOT'] = dirname( __DIR__ );
require __DIR__ . '/stubs.php';
require $GLOBALS['WFG_ROOT'] . '/includes/class-wfg-autoloader.php';
WFG_Autoloader::register();

$passed = 0; $failed = 0;
function ok( $cond, $msg ) {
	global $passed, $failed;
	if ( $cond ) { ++$passed; echo "  ✓ $msg\n"; } else { ++$failed; echo "  ✗ FAIL: $msg\n"; }
}
function section( $t ) { echo "\n== $t ==\n"; }

// Products.
wfg_test_product( array( 'id' => 1, 'name' => 'Seeds A', 'price' => 20.0 ) );
wfg_test_product( array( 'id' => 2, 'name' => 'Product B', 'price' => 30.0 ) );
wfg_test_product( array( 'id' => 3, 'name' => 'Product C', 'price' => 45.0 ) );
wfg_test_product( array( 'id' => 10, 'name' => 'Free Seed', 'price' => 0, ) );          // custom gift
wfg_test_product( array( 'id' => 11, 'name' => 'Grow Book', 'price' => 25.0 ) );          // catalog gift
wfg_test_product( array( 'id' => 12, 'name' => 'Gift X', 'price' => 5.0 ) );
wfg_test_product( array( 'id' => 13, 'name' => 'Sticker', 'price' => 1.0 ) );
wfg_test_product( array( 'id' => 14, 'name' => 'Sold out', 'price' => 9.0, 'stock_status' => 'outofstock' ) );
wfg_test_product( array( 'id' => 15, 'name' => 'Limited', 'price' => 9.0, 'manage_stock' => true, 'stock' => 1 ) );
wfg_test_product( array( 'id' => 16, 'name' => 'Single', 'price' => 9.0, 'sold_individually' => true ) );
wfg_test_product( array( 'id' => 20, 'name' => 'Bundle', 'price' => 50.0, 'type' => 'bundle' ) );
$GLOBALS['product_terms'] = array( 1 => array( (object) array( 'term_id' => 100 ) ), 2 => array( (object) array( 'term_id' => 200 ) ) );
$GLOBALS['term_ancestors'] = array( 200 => array( 250 ) );
$GLOBALS['wp_options']['wfg_custom_gift_ids'] = array( 10 );

// Plugin components (hooks registered once).
$settings      = new WFG_Settings();
$rules         = new WFG_Rules();
$gift_products = new WFG_Gift_Products();
$engine        = new WFG_Engine( $settings, $rules );
$cart_int      = new WFG_Cart( $settings, $engine );

function fresh_cart() {
	global $engine;
	unset( $GLOBALS['wp_filters']['woocommerce_add_to_cart'][20], $GLOBALS['wp_filters']['woocommerce_cart_item_removed'][20] );
	$engine->flush();
	return wfg_test_reset_wc();
}
function gifts_in( WC_Cart $cart ) { $o = array(); foreach ( $cart->get_cart() as $i ) { if ( ! empty( $i['wfg_gift'] ) ) { $o[] = (int) $i['wfg_gift']['product_id']; } } sort( $o ); return $o; }
function set_settings( array $over ) { global $settings; $settings->save( array_merge( WFG_Settings::defaults(), $over ) ); }
function set_rules( array $list ) { global $rules, $engine; update_option( WFG_Rules::OPTION_KEY, array() ); $rules = new WFG_Rules(); $ids = array(); foreach ( $list as $r ) { $ids[] = $rules->save( WFG_Rules::sanitize( $r ) ); } $engine = new WFG_Engine( $GLOBALS['settings'], $rules ); $GLOBALS['engine'] = $engine; $GLOBALS['rules'] = $rules;
	// Rebind cart integration to the new engine.
	$ref = new ReflectionProperty( 'WFG_Cart', 'engine' ); $ref->setAccessible( true ); $ref->setValue( $GLOBALS['cart_int'], $engine );
	return $ids; }

set_settings( array() );

// ---------------------------------------------------------------------------
section( 'Sanitizing' );
$r = WFG_Rules::sanitize( array( 'title' => ' <b>Seed</b> ', 'min_total' => '50,00', 'required_products' => array( '2', 'x', '3', '2' ), 'date_from' => '2026-13-01', 'date_to' => '2026-12-24', 'gifts' => array( array( 'product_id' => 10, 'qty' => '0', 'custom' => '1' ) ), 'priority' => '-5', 'user_roles' => array( 'customer', 'hacker' ) ) );
ok( 'Seed' === $r['title'], 'title stripped' );
ok( 50.0 === $r['min_total'], 'localized min_total parsed' );
ok( array( 2, 3 ) === $r['required_products'], 'product ids deduped' );
ok( '' === $r['date_from'] && '2026-12-24' === $r['date_to'], 'dates validated' );
ok( 1 === $r['gifts'][0]['qty'] && true === $r['gifts'][0]['custom'], 'gift qty min 1, custom flag' );
ok( 0 === $r['priority'], 'priority clamped' );
ok( array( 'customer' ) === $r['user_roles'], 'unknown roles dropped' );
$s = WFG_Settings::sanitize( array( 'stacking' => 'evil', 'progress_color' => 'red', 'popup_days' => 9999, 'enabled' => '1' ) );
ok( 'all' === $s['stacking'] && '#2e7d32' === $s['progress_color'] && 365 === $s['popup_days'] && true === $s['enabled'], 'settings sanitized' );

// ---------------------------------------------------------------------------
section( 'Threshold rule: seed from 50, book from 100' );
set_rules( array(
	array( 'title' => 'Seed 50', 'enabled' => 1, 'min_total' => 50, 'gifts' => array( array( 'product_id' => 10, 'qty' => 1, 'custom' => 1 ) ), 'show_progress' => 1 ),
	array( 'title' => 'Book 100', 'enabled' => 1, 'min_total' => 100, 'gifts' => array( array( 'product_id' => 11, 'qty' => 1 ) ), 'show_progress' => 1 ),
) );
$cart = fresh_cart();
$cart->add_to_cart( 1, 2 ); // 40
ok( array() === gifts_in( $cart ), 'no gift at 40 €' );
$p = $engine->progress( $cart );
ok( $p['next'] && 10.0 === $p['next']['remaining'] && 80.0 === round( $p['next']['percent'] ), 'progress: 10 € remaining, 80 %' );
$cart->add_to_cart( 2, 1 ); // 70
ok( array( 10 ) === gifts_in( $cart ), 'seed added at 70 €' );
ok( 70.0 === $cart->subtotal_paid(), 'gift not counted in subtotal' );
foreach ( $cart->get_cart() as $i ) { if ( ! empty( $i['wfg_gift'] ) ) { ok( 0.0 === (float) $i['line_subtotal'], 'gift line priced at 0' ); } }
ok( ! empty( $GLOBALS['wc_notices']['success'] ) && false !== strpos( $GLOBALS['wc_notices']['success'][0], 'Free Seed' ), 'unlock notice shown' );
$cart->add_to_cart( 3, 1 ); // 115
ok( array( 10, 11 ) === gifts_in( $cart ), 'both gifts at 115 € (stacking all)' );
ok( $cart->calc_count < 30, 'no recalculation storm (' . $cart->calc_count . ' calcs)' );
// Remove paid items -> gifts go.
foreach ( array_keys( $cart->get_cart() ) as $k ) { $i = $cart->get_cart_item( $k ); if ( $i && empty( $i['wfg_gift'] ) && 3 == $i['product_id'] ) { $cart->remove_cart_item( $k ); } }
ok( array( 10 ) === gifts_in( $cart ), 'book removed when dropping below 100' );
foreach ( array_keys( $cart->get_cart() ) as $k ) { $i = $cart->get_cart_item( $k ); if ( $i && empty( $i['wfg_gift'] ) ) { $cart->remove_cart_item( $k ); } }
ok( array() === gifts_in( $cart ) && $cart->is_empty(), 'cart with only gifts becomes empty' );

section( 'Stacking highest' );
set_settings( array( 'stacking' => 'highest' ) );
$cart = fresh_cart();
$cart->add_to_cart( 2, 4 ); // 120
ok( array( 11 ) === gifts_in( $cart ), 'only the book at 120 € with stacking=highest' );
set_settings( array() );

section( 'Threshold incl. tax and after discounts' );
set_settings( array( 'threshold_basis' => 'subtotal_incl_tax' ) );
$cart = fresh_cart();
$cart->add_to_cart( 3, 1 ); // 45 net = 53.55 gross
ok( array( 10 ) === gifts_in( $cart ), 'incl. tax basis reaches 50' );
set_settings( array() );

// ---------------------------------------------------------------------------
section( 'Buy B and C get X' );
set_rules( array(
	array( 'title' => 'B+C', 'enabled' => 1, 'required_products' => array( 2, 3 ), 'required_match' => 'all', 'gifts' => array( array( 'product_id' => 12, 'qty' => 2 ) ) ),
	array( 'title' => 'B or C x2', 'enabled' => 1, 'required_products' => array( 2, 3 ), 'required_match' => 'any', 'required_qty' => 2, 'gifts' => array( array( 'product_id' => 13, 'qty' => 1 ) ) ),
) );
$cart = fresh_cart();
$cart->add_to_cart( 2, 1 );
ok( array() === gifts_in( $cart ), 'only B: nothing' );
$cart->add_to_cart( 3, 1 );
ok( array( 12 ) === gifts_in( $cart ), 'B + C: gift X' );
foreach ( $cart->get_cart() as $i ) { if ( ! empty( $i['wfg_gift'] ) ) { ok( 2 === $i['quantity'], 'gift qty 2' ); } }
$cart->add_to_cart( 2, 1 ); // B x2
ok( array( 12, 13 ) === gifts_in( $cart ), 'B x2 unlocks the any-rule with qty 2' );

section( 'Category (ancestor) + bundle + min items' );
set_rules( array(
	array( 'title' => 'Cat', 'enabled' => 1, 'required_categories' => array( 250 ), 'gifts' => array( array( 'product_id' => 13 ) ) ),
	array( 'title' => 'Bundle', 'enabled' => 1, 'require_bundle' => 1, 'gifts' => array( array( 'product_id' => 12 ) ) ),
	array( 'title' => 'Items', 'enabled' => 1, 'min_items' => 5, 'gifts' => array( array( 'product_id' => 11 ) ) ),
) );
$cart = fresh_cart();
$cart->add_to_cart( 1, 1 );
ok( array() === gifts_in( $cart ), 'product in unrelated category: nothing' );
$cart->add_to_cart( 2, 1 );
ok( array( 13 ) === gifts_in( $cart ), 'parent category 250 matches via ancestor' );
$cart->add_to_cart( 20, 1 );
ok( array( 12, 13 ) === gifts_in( $cart ), 'bundle product type unlocks bundle rule' );
$cart->add_to_cart( 1, 2 );
ok( array( 11, 12, 13 ) === gifts_in( $cart ), '5 items unlock min_items rule' );

// ---------------------------------------------------------------------------
section( 'Gift choice' );
$ids = set_rules( array(
	array( 'title' => 'Choose', 'enabled' => 1, 'min_total' => 10, 'gift_mode' => 'choice', 'gifts' => array( array( 'product_id' => 12 ), array( 'product_id' => 13 ) ) ),
) );
$cart = fresh_cart();
$cart->add_to_cart( 1, 1 );
ok( array() === gifts_in( $cart ), 'choice rule adds nothing until chosen' );
$p = $engine->progress( $cart );
ok( 1 === count( $p['choices'] ) && 2 === count( $p['choices'][0]['gifts'] ), 'progress exposes 2 choices' );
$res = $cart_int->choose( $ids[0], 99 );
ok( is_wp_error( $res ), 'invalid gift rejected' );
$res = $cart_int->choose( $ids[0], 13 );
ok( true === $res && array( 13 ) === gifts_in( $cart ), 'chosen gift added' );
$cart_int->choose( $ids[0], 12 );
ok( array( 12 ) === gifts_in( $cart ), 'switching choice swaps the gift' );
ok( 12 === $cart_int->chosen_gift( $ids[0] ), 'choice persisted in session' );

// ---------------------------------------------------------------------------
section( 'Customer removes gift' );
set_rules( array( array( 'title' => 'Seed', 'enabled' => 1, 'min_total' => 50, 'gifts' => array( array( 'product_id' => 10, 'custom' => 1 ) ) ) ) );
$cart = fresh_cart();
$cart->add_to_cart( 2, 2 ); // 60
ok( array( 10 ) === gifts_in( $cart ), 'gift present' );
$gift_key = null; foreach ( $cart->get_cart() as $k => $i ) { if ( ! empty( $i['wfg_gift'] ) ) { $gift_key = $k; } }
$cart->remove_cart_item( $gift_key ); // customer action
ok( array() === gifts_in( $cart ), 'gift stays removed after customer removal' );
$cart->add_to_cart( 1, 1 ); // 80, still qualified
ok( array() === gifts_in( $cart ), 'not re-added while rule still qualifies' );
foreach ( array_keys( $cart->get_cart() ) as $k ) { $i = $cart->get_cart_item( $k ); if ( $i && 2 == $i['product_id'] ) { $cart->remove_cart_item( $k ); } } // 20
ok( array() === gifts_in( $cart ), 'below threshold: nothing' );
$cart->add_to_cart( 2, 2 ); // 80 again
ok( array( 10 ) === gifts_in( $cart ), 're-added after losing and regaining the rule' );
// Undo restore path.
$gift_key = null; foreach ( $cart->get_cart() as $k => $i ) { if ( ! empty( $i['wfg_gift'] ) ) { $gift_key = $k; } }
$cart->remove_cart_item( $gift_key );
$cart->restore_cart_item( $gift_key );
ok( array( 10 ) === gifts_in( $cart ), 'undo restores the gift and clears dismissal' );

set_settings( array( 'allow_remove' => false ) );
$cart = fresh_cart();
$cart->add_to_cart( 2, 2 );
$gift_key = null; foreach ( $cart->get_cart() as $k => $i ) { if ( ! empty( $i['wfg_gift'] ) ) { $gift_key = $k; } }
$cart->remove_cart_item( $gift_key );
ok( array( 10 ) === gifts_in( $cart ), 'allow_remove=false: gift re-added immediately' );
set_settings( array() );

// ---------------------------------------------------------------------------
section( 'Protection of custom gifts' );
$cart = fresh_cart();
$key = $cart->add_to_cart( 10, 1 );
ok( false === $key && ! empty( $GLOBALS['wc_notices']['error'] ), 'custom gift cannot be added directly' );
$q = new stdClass();
ok( true === $gift_products->not_visible( true, 10 ) ? false : true, 'custom gift is never visible' );
ok( 'hidden' === $gift_products->force_hidden_visibility( 'visible', wc_get_product( 10 ) ), 'visibility forced hidden' );

section( 'Stock / availability' );
set_rules( array(
	array( 'title' => 'Out', 'enabled' => 1, 'min_total' => 10, 'gifts' => array( array( 'product_id' => 14 ) ) ),
	array( 'title' => 'Limited', 'enabled' => 1, 'min_total' => 10, 'gifts' => array( array( 'product_id' => 15 ) ) ),
	array( 'title' => 'Single', 'enabled' => 1, 'min_total' => 10, 'gifts' => array( array( 'product_id' => 16 ) ) ),
) );
$cart = fresh_cart();
$cart->add_to_cart( 15, 1 ); // customer buys the last "Limited"
$cart->add_to_cart( 16, 1 ); // and the sold-individually one
ok( array() === gifts_in( $cart ), 'out-of-stock, stock-exhausted and sold-individually gifts skipped' );
ok( empty( $GLOBALS['wc_notices']['error'] ), 'no error notices leaked to the customer' );
$cart = fresh_cart();
$cart->add_to_cart( 2, 1 );
ok( array( 15, 16 ) === gifts_in( $cart ), 'limited + single gifts added when available' );

section( 'User conditions' );
$ids = set_rules( array(
	array( 'title' => 'Wholesale', 'enabled' => 1, 'user_roles' => array( 'wholesale' ), 'gifts' => array( array( 'product_id' => 12 ) ) ),
	array( 'title' => 'Once', 'enabled' => 1, 'once_per_customer' => 1, 'gifts' => array( array( 'product_id' => 13 ) ) ),
	array( 'title' => 'Login', 'enabled' => 1, 'logged_in_only' => 1, 'gifts' => array( array( 'product_id' => 11 ) ) ),
) );
$cart = fresh_cart();
$cart->add_to_cart( 1, 1 );
ok( array( 13 ) === gifts_in( $cart ), 'guest: only the unconditional once-rule' );
$GLOBALS['current_user'] = new WP_User( 5, array( 'wholesale' ) );
$GLOBALS['user_meta'][5][ WFG_Order::USER_META_CLAIMED ] = array( $ids[1] );
$cart = fresh_cart();
$cart->add_to_cart( 1, 1 );
ok( array( 11, 12 ) === gifts_in( $cart ), 'wholesale user: role + login rules, once-rule already claimed' );
$GLOBALS['current_user'] = new WP_User( 0 );

section( 'Plugin disabled / rule deleted' );
$ids = set_rules( array( array( 'title' => 'Seed', 'enabled' => 1, 'min_total' => 50, 'gifts' => array( array( 'product_id' => 10, 'custom' => 1 ) ) ) ) );
$cart = fresh_cart();
$cart->add_to_cart( 2, 2 );
ok( array( 10 ) === gifts_in( $cart ), 'gift present' );
set_settings( array( 'enabled' => false ) );
$engine->flush(); $cart->calculate_totals();
ok( array() === gifts_in( $cart ), 'disabling the plugin removes gifts' );
set_settings( array() );
$engine->flush(); $cart->calculate_totals();
ok( array( 10 ) === gifts_in( $cart ), 're-enabled: gift back' );
$rules->delete( $ids[0] ); $engine->flush(); $cart->calculate_totals();
ok( array() === gifts_in( $cart ), 'deleted rule: orphan gift removed' );

section( 'Date window' );
set_rules( array(
	array( 'title' => 'Past', 'enabled' => 1, 'date_to' => '2000-01-01', 'gifts' => array( array( 'product_id' => 12 ) ) ),
	array( 'title' => 'Future', 'enabled' => 1, 'date_from' => '2099-01-01', 'gifts' => array( array( 'product_id' => 13 ) ) ),
	array( 'title' => 'Now', 'enabled' => 1, 'date_from' => '2000-01-01', 'date_to' => '2099-01-01', 'gifts' => array( array( 'product_id' => 11 ) ) ),
) );
$cart = fresh_cart();
$cart->add_to_cart( 1, 1 );
ok( array( 11 ) === gifts_in( $cart ), 'only the rule inside its date window applies' );

section( 'Display filters' );
$cart = fresh_cart();
set_rules( array( array( 'title' => 'Seed', 'enabled' => 1, 'min_total' => 10, 'gifts' => array( array( 'product_id' => 11 ) ) ) ) );
$cart->add_to_cart( 1, 1 );
$gift_item = null; foreach ( $cart->get_cart() as $k => $i ) { if ( ! empty( $i['wfg_gift'] ) ) { $gift_item = $i; $gift_key = $k; } }
ok( false !== strpos( $cart_int->item_name( 'Grow Book', $gift_item ), 'wfg-badge' ), 'badge appended' );
ok( false !== strpos( $cart_int->item_price( '25', $gift_item ), '<del' ) && false !== strpos( $cart_int->item_price( '25', $gift_item ), 'Free' ), 'price shows del + Free' );
ok( '<span class="wfg-qty">1</span>' === $cart_int->item_quantity( 'x', $gift_key, $gift_item ), 'qty locked' );
ok( false === $cart_int->coupon_not_for_gifts( true, null, null, $gift_item ), 'coupons excluded' );
ok( false === $cart_int->store_api_quantity_editable( true, null, $gift_item ), 'store api qty not editable' );
$data = $cart_int->item_data( array(), $gift_item );
ok( 1 === count( $data ) && 'Seed' === $data[0]['value'], 'item data carries rule title' );
$html = WFG_Helpers::placeholders( 'Add {remaining} to get {gift}', array( 'remaining' => '5 €', 'gift' => 'X' ) );
ok( 'Add 5 € to get X' === $html, 'placeholders' );

echo "\n$passed passed, $failed failed\n";
exit( $failed ? 1 : 0 );
