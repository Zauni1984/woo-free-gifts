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

// ---------------------------------------------------------------------------
section( 'Stock & budget' );
wfg_test_product( array( 'id' => 17, 'name' => 'Limited 3', 'price' => 0, 'manage_stock' => true, 'stock' => 3 ) );
$ids = set_rules( array(
	array( 'title' => 'Budget 2', 'enabled' => 1, 'min_total' => 10, 'claim_limit' => 2, 'gifts' => array( array( 'product_id' => 12 ) ), 'show_progress' => 1 ),
	array( 'title' => 'Stock 3', 'enabled' => 1, 'min_total' => 500, 'gifts' => array( array( 'product_id' => 17, 'custom' => 1 ) ), 'show_progress' => 1 ),
	array( 'title' => 'Both', 'enabled' => 1, 'min_total' => 900, 'claim_limit' => 10, 'gifts' => array( array( 'product_id' => 17, 'qty' => 2, 'custom' => 1 ) ), 'show_progress' => 1 ),
	array( 'title' => 'Choice', 'enabled' => 1, 'min_total' => 950, 'gift_mode' => 'choice', 'gifts' => array( array( 'product_id' => 17 ), array( 'product_id' => 12 ) ), 'show_progress' => 1 ),
) );
$GLOBALS['wp_options'][ WFG_Order::OPTION_STATS ] = array( $ids[0] => array( 'count' => 1, 'last' => time() ) );
ok( 1 === $engine->remaining_units( $rules->get( $ids[0] ) ), 'budget 2 minus 1 claim = 1 left' );
ok( 3 === $engine->remaining_units( $rules->get( $ids[1] ) ), 'stock-managed gift: 3 left' );
ok( 1 === $engine->remaining_units( $rules->get( $ids[2] ) ), 'qty 2 from stock 3 = 1 left (limit 10 not binding)' );
ok( null === $engine->remaining_units( $rules->get( $ids[3] ) ), 'choice with an unlimited gift = unlimited' );
ok( null === $engine->remaining_units( array_merge( WFG_Rules::defaults(), array( 'gifts' => array( array( 'product_id' => 12, 'qty' => 1 ) ) ) ) ), 'unmanaged stock = unlimited' );
$cart = fresh_cart();
$cart->add_to_cart( 1, 1 ); // 20 € -> budget rule qualifies
ok( array( 12 ) === gifts_in( $cart ), 'budget rule still active with 1 claim left' );
$p = $engine->progress( $cart );
ok( $p['next'] && 3 === $p['next']['left'], 'progress exposes units left of the next rule' );
$fe = new WFG_Frontend( $settings, $engine, $cart_int );
ok( 'Only 3 left – be quick!' === $fe->scarcity_text( 3 ) && '' === $fe->scarcity_text( null ) && '' === $fe->scarcity_text( 21 ), 'scarcity text respects threshold + unlimited' );
$GLOBALS['wp_options'][ WFG_Order::OPTION_STATS ] = array( $ids[0] => array( 'count' => 2, 'last' => time() ) );
$rules = new WFG_Rules(); $GLOBALS['rules'] = $rules;
ok( ! isset( $rules->active()[ $ids[0] ] ), 'budget used up: rule no longer active' );
$engine->flush(); $cart->calculate_totals();
ok( array() === gifts_in( $cart ), 'exhausted rule removes its gift from the cart' );
$GLOBALS['wp_options'][ WFG_Order::OPTION_STATS ] = array();
$GLOBALS['products'][17]['stock'] = 0; $GLOBALS['products'][17]['stock_status'] = 'outofstock';
$engine->flush();
$p = $engine->progress( $cart );
ok( ! $p['next'] || $p['next']['rule']['id'] !== $ids[1], 'rule whose gift is out of stock is not offered as "next"' );
$r = WFG_Rules::sanitize( array( 'title' => 'x', 'claim_limit' => '-4', 'gifts' => array( array( 'product_id' => 12 ) ) ) );
ok( 4 === $r['claim_limit'], 'claim_limit sanitized' );

// ---------------------------------------------------------------------------
section( 'Wheel of fortune' );
function wheel_identity( $ip ) { $GLOBALS['ip'] = $ip; unset( $_COOKIE['wfg_wheel_next'] ); return fresh_cart(); }
set_rules( array() );
set_settings( array( 'wheel_enabled' => true, 'wheel_segments' => array(
	array( 'label' => '10 %', 'type' => 'coupon', 'coupon_type' => 'percent', 'amount' => 10, 'weight' => 100, 'color' => '#111111' ),
	array( 'label' => 'Nope', 'type' => 'none', 'weight' => 0, 'color' => '#222222' ),
) ) );
$wheel = new WFG_Wheel( $settings, $engine );
$cart  = wheel_identity( '203.0.113.1' );
$cart->add_to_cart( 1, 1 );
$before = count( $GLOBALS['wc_notices'] );
$res = $wheel->spin( '' );
ok( ! is_wp_error( $res ) && 'coupon' === $res['type'] && 0 === $res['index'], 'weighted pick lands on the only weighted segment' );
ok( preg_match( '/^HIGH-[A-Z0-9]{6}$/', $res['code'] ) === 1, 'unique coupon code generated: ' . $res['code'] );
$c = $GLOBALS['coupons'][ strtolower( $res['code'] ) ];
ok( 'percent' === $c['discount_type'] && 10.0 === (float) $c['amount'] && 1 === $c['usage_limit'] && 1 === $c['usage_limit_per_user'] && $c['date_expires'] > time(), 'coupon is single-use with expiry' );
ok( $cart->has_discount( $res['code'] ), 'coupon auto-applied to the cart' );
ok( count( $GLOBALS['wc_notices'] ) === $before, 'apply_coupon notices suppressed' );
ok( false !== strpos( $res['message'], $res['code'] ) && $res['nextSpin'] > time() + 23 * 3600, 'win message + next spin in ~24h' );
$again = $wheel->spin( '' );
ok( is_wp_error( $again ) && 'wfg_wheel_cooldown' === $again->get_error_code(), 'second spin blocked (session)' );
$cart = fresh_cart(); // new session, same IP
ok( is_wp_error( $wheel->spin( '' ) ), 'new session, same IP: still blocked' );
$cart = wheel_identity( '203.0.113.2' );
$cart->add_to_cart( 1, 1 );
ok( ! is_wp_error( $wheel->spin( '' ) ), 'new identity may spin' );
$stats = WFG_Wheel::stats();
ok( 2 === $stats['spins'] && 2 === $stats['coupons'] && 2 === count( WFG_Wheel::log_entries() ), 'stats + log recorded' );

// E-mail cooldown across identities.
set_settings( array( 'wheel_enabled' => true, 'wheel_require_email' => true, 'wheel_segments' => array(
	array( 'label' => '5 €', 'type' => 'coupon', 'coupon_type' => 'fixed_cart', 'amount' => 5, 'weight' => 1, 'color' => '#111111' ),
	array( 'label' => 'Nope', 'type' => 'none', 'weight' => 0, 'color' => '#222222' ),
) ) );
$cart = wheel_identity( '203.0.113.3' );
$res  = $wheel->spin( 'stoner@example.com' );
ok( ! is_wp_error( $res ) && array( 'stoner@example.com' ) === $GLOBALS['coupons'][ strtolower( $res['code'] ) ]['email_restrictions'], 'coupon bound to the e-mail address' );
$cart = wheel_identity( '203.0.113.4' );
ok( is_wp_error( $wheel->spin( 'Stoner@Example.com ' ) ), 'same e-mail (case/space-insensitive) on a new identity is blocked' );

// Gift prize rides along in the cart.
set_settings( array( 'wheel_enabled' => true, 'wheel_segments' => array(
	array( 'label' => 'Gift', 'type' => 'gift', 'product_id' => 12, 'weight' => 100, 'color' => '#111111' ),
	array( 'label' => 'Nope', 'type' => 'none', 'weight' => 0, 'color' => '#222222' ),
) ) );
$cart = wheel_identity( '203.0.113.5' );
$res  = $wheel->spin( '' );
ok( ! is_wp_error( $res ) && 'gift' === $res['type'] && array( 12 => true ) === array_map( 'is_int', WFG_Wheel::pending_gifts() ), 'gift prize stored in session' );
ok( array() === gifts_in( $cart ), 'empty cart: prize waits' );
$cart->add_to_cart( 1, 1 );
ok( array( 12 ) === gifts_in( $cart ), 'prize added once the cart has paid items' );
$gift_item = null; $gift_key = null; foreach ( $cart->get_cart() as $k => $i ) { if ( ! empty( $i['wfg_gift'] ) ) { $gift_item = $i; $gift_key = $k; } }
ok( 0 === $gift_item['wfg_gift']['rule_id'] && 'Wheel of fortune prize' === $cart_int->item_data( array(), $gift_item )[0]['value'], 'prize line labelled as wheel prize' );
$cart->remove_cart_item( $gift_key );
ok( array() === gifts_in( $cart ) && array() === WFG_Wheel::pending_gifts(), 'removing the prize forfeits it' );

// Out-of-stock gift segment is skipped.
set_settings( array( 'wheel_enabled' => true, 'wheel_segments' => array(
	array( 'label' => 'Sold out', 'type' => 'gift', 'product_id' => 14, 'weight' => 1000, 'color' => '#111111' ),
	array( 'label' => '10 %', 'type' => 'coupon', 'coupon_type' => 'percent', 'amount' => 10, 'weight' => 1, 'color' => '#222222' ),
) ) );
$cart = wheel_identity( '203.0.113.6' );
$res  = $wheel->spin( '' );
ok( ! is_wp_error( $res ) && 'coupon' === $res['type'], 'out-of-stock gift segment skipped' );

// Logged-in user cooldown via user meta.
$GLOBALS['current_user'] = new WP_User( 9, array( 'customer' ) );
$cart = wheel_identity( '203.0.113.7' );
ok( ! is_wp_error( $wheel->spin( '' ) ), 'logged-in user spins' );
$cart = wheel_identity( '203.0.113.8' );
ok( is_wp_error( $wheel->spin( '' ) ) && $GLOBALS['user_meta'][9]['_wfg_wheel_next'] > time(), 'user meta blocks a second spin from another network' );
$GLOBALS['current_user'] = new WP_User( 0 );

// Segment sanitizing.
$seg = WFG_Settings::sanitize_segments( array(
	array( 'label' => 'A', 'type' => 'coupon', 'coupon_type' => 'percent', 'amount' => '150', 'code' => ' MyCode ', 'weight' => '-3' ),
	array( 'label' => '', 'type' => 'coupon' ),
	array( 'label' => 'B', 'type' => 'gift', 'product_id' => 0 ),
	array( 'label' => 'C', 'type' => 'evil', 'color' => 'nope' ),
), array() );
ok( 3 === count( $seg ) && 100.0 === $seg[0]['amount'] && 'mycode' === $seg[0]['code'] && 3 === $seg[0]['weight'] && 'none' === $seg[1]['type'] && 'none' === $seg[2]['type'] && '#' === $seg[2]['color'][0], 'segments sanitized (cap, code, gift without product → none)' );
ok( array( 'x' ) === WFG_Settings::sanitize_segments( array( array( 'label' => 'only one' ) ), array( 'x' ) ), 'fewer than 2 segments keep the fallback' );

// ---------------------------------------------------------------------------
section( 'Updater' );
$release = array( 'tag_name' => 'v1.3.0', 'html_url' => 'https://github.com/Zauni1984/woo-free-gifts/releases/tag/v1.3.0', 'body' => "- Fix <b>x</b>", 'published_at' => '2026-10-01T10:00:00Z', 'zipball_url' => 'https://api.github.com/repos/Zauni1984/woo-free-gifts/zipball/v1.3.0', 'assets' => array(
	array( 'name' => 'source.zip', 'browser_download_url' => 'https://github.com/x/source.zip', 'url' => 'https://api.github.com/repos/Zauni1984/woo-free-gifts/releases/assets/1' ),
	array( 'name' => 'woo-free-gifts-1.3.0.zip', 'browser_download_url' => 'https://github.com/x/woo-free-gifts-1.3.0.zip', 'url' => 'https://api.github.com/repos/Zauni1984/woo-free-gifts/releases/assets/2' ),
) );
$parsed = WFG_Updater::parse( $release );
ok( '1.3.0' === $parsed['version'] && 'https://github.com/x/woo-free-gifts-1.3.0.zip' === $parsed['package'], 'release parsed, own ZIP asset preferred' );
ok( false === strpos( $parsed['changelog'], '<b>' ) && false !== strpos( $parsed['changelog'], 'Fix' ), 'changelog escaped' );
ok( 'https://api.github.com/repos/Zauni1984/woo-free-gifts/releases/assets/2' === WFG_Updater::parse( $release, true )['package'], 'private repo uses API asset URL' );
ok( null === WFG_Updater::parse( array_merge( $release, array( 'prerelease' => true ) ) ), 'pre-releases ignored' );
ok( null === WFG_Updater::parse( array_merge( $release, array( 'tag_name' => 'beta' ) ) ), 'non-numeric tags ignored' );
$no_assets = $release; unset( $no_assets['assets'] );
ok( $no_assets['zipball_url'] === WFG_Updater::parse( $no_assets )['package'], 'falls back to zipball' );
$updater = new WFG_Updater( $settings );
set_site_transient( WFG_Updater::TRANSIENT, $parsed );
$u = $updater->check( false, array(), WFG_PLUGIN_BASENAME );
ok( is_array( $u ) && '1.3.0' === $u['version'] && WFG_PLUGIN_BASENAME === $u['plugin'], 'newer release reported to WordPress' );
set_site_transient( WFG_Updater::TRANSIENT, array_merge( $parsed, array( 'version' => '0.9.0' ) ) );
ok( false === $updater->check( false, array(), WFG_PLUGIN_BASENAME ), 'older release: no update' );
ok( 'keep' === $updater->check( 'keep', array(), 'other/plugin.php' ), 'other plugins untouched' );
$args = $updater->authorize_download( array(), 'https://api.github.com/repos/Zauni1984/woo-free-gifts/releases/assets/2' );
ok( empty( $args['headers'] ), 'no token: no auth header' );

echo "\n$passed passed, $failed failed\n";
exit( $failed ? 1 : 0 );
