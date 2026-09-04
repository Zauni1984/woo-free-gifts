<?php
/**
 * Render templates and admin views to catch runtime notices / undefined variables.
 */
error_reporting( E_ALL );
$GLOBALS['WFG_ROOT'] = dirname( __DIR__ );
require __DIR__ . '/stubs.php';
require $GLOBALS['WFG_ROOT'] . '/includes/class-wfg-autoloader.php';
WFG_Autoloader::register();
set_error_handler( function ( $no, $str, $file, $line ) { throw new ErrorException( $str, 0, $no, $file, $line ); } );

wfg_test_product( array( 'id' => 1, 'name' => 'Seeds A', 'price' => 20.0 ) );
wfg_test_product( array( 'id' => 10, 'name' => 'Free Seed', 'price' => 0, 'image_id' => 7 ) );
wfg_test_product( array( 'id' => 11, 'name' => 'Grow Book', 'price' => 25.0 ) );
wfg_test_product( array( 'id' => 12, 'name' => 'Gift X', 'price' => 5.0 ) );
$GLOBALS['wp_options']['wfg_custom_gift_ids'] = array( 10 );

$settings = new WFG_Settings();
$settings->save( array_merge( WFG_Settings::defaults(), array( 'popup_enabled' => true, 'popup_image_id' => 9 ) ) );
$rules = new WFG_Rules();
$rules->save( WFG_Rules::sanitize( array( 'title' => 'Seed 50', 'enabled' => 1, 'min_total' => 50, 'gifts' => array( array( 'product_id' => 10, 'custom' => 1 ) ), 'show_progress' => 1, 'show_in_popup' => 1, 'date_to' => '2030-01-01' ) ) );
$rules->save( WFG_Rules::sanitize( array( 'title' => 'Choose 10', 'enabled' => 1, 'min_total' => 10, 'gift_mode' => 'choice', 'gifts' => array( array( 'product_id' => 11 ), array( 'product_id' => 12, 'qty' => 2 ) ), 'show_progress' => 1, 'show_in_popup' => 1 ) ) );
$rules->save( WFG_Rules::sanitize( array( 'title' => 'Off', 'enabled' => 0, 'required_products' => array( 1 ), 'required_categories' => array( 100 ), 'require_bundle' => 1, 'min_items' => 3, 'gifts' => array( array( 'product_id' => 99 ) ) ) ) );
$engine = new WFG_Engine( $settings, $rules );
$cart_int = new WFG_Cart( $settings, $engine );
$frontend = new WFG_Frontend( $settings, $engine, $cart_int );
$popup = new WFG_Popup( $settings, $rules, $engine );
$gift_products = new WFG_Gift_Products();
$cart = wfg_test_reset_wc();
$cart->add_to_cart( 1, 1 ); // 20 € -> choice rule qualified, seed rule 30 € away

$ok = 0; $fail = 0;
function render( $label, callable $fn ) {
	global $ok, $fail;
	try {
		ob_start(); $fn(); $out = ob_get_clean();
		++$ok; echo "  ✓ $label (" . strlen( $out ) . " bytes)\n"; return $out;
	} catch ( Throwable $e ) {
		ob_end_clean(); ++$fail; echo "  ✗ $label: " . $e->getMessage() . ' @ ' . basename( $e->getFile() ) . ':' . $e->getLine() . "\n"; return '';
	}
}

echo "== Frontend templates ==\n";
$html = render( 'progress-box (cart)', function () use ( $frontend ) { echo $frontend->render_box( 'cart' ); } );
if ( strpos( $html, 'wfg-progress__fill' ) === false || strpos( $html, 'wfg-choice__button' ) === false ) { echo "  ✗ progress box missing bar or choice\n"; $fail++; }
render( 'progress-box (minicart)', function () use ( $frontend ) { echo $frontend->render_box( 'minicart' ); } );
render( 'progress-box (checkout)', function () use ( $frontend ) { echo $frontend->render_box( 'checkout' ); } );
render( 'single hint', function () use ( $frontend ) { $frontend->output_single_hint(); } );
function is_product() { return true; } function is_shop() { return false; } function is_product_taxonomy() { return false; } function is_feed() { return false; } function is_404() { return false; }
$html = render( 'popup', function () use ( $popup ) { $popup->output(); } );
if ( strpos( $html, 'data-wfg-popup' ) === false || strpos( $html, 'wfg-popup__offer' ) === false ) { echo "  ✗ popup missing config or offers\n"; $fail++; }
render( 'gift list', function () use ( $popup ) { echo WFG_Helpers::template( 'gift-list', array( 'offers' => $popup->offers() ) ); } );

echo "== Admin views ==\n";
$GLOBALS['is_admin'] = true;
$view = function ( $name, array $data ) { extract( $data ); include $GLOBALS['WFG_ROOT'] . '/includes/admin/views/' . $name . '.php'; };
$stats = array( 1 => array( 'count' => 3, 'last' => time() ), 77 => array( 'count' => 1, 'last' => time() ) );
render( 'rules-list', function () use ( $view, $settings, $rules, $stats ) { $view( 'rules-list', compact( 'settings', 'rules', 'stats' ) ); } );
render( 'rule-edit (existing)', function () use ( $view, $settings, $rules ) { $rule = $rules->get( 1 ); $view( 'rule-edit', compact( 'settings', 'rules', 'rule' ) ); } );
render( 'rule-edit (choice rule)', function () use ( $view, $settings, $rules ) { $rule = $rules->get( 2 ); $view( 'rule-edit', compact( 'settings', 'rules', 'rule' ) ); } );
render( 'rule-edit (new)', function () use ( $view, $settings, $rules ) { $rule = WFG_Rules::defaults(); $view( 'rule-edit', compact( 'settings', 'rules', 'rule' ) ); } );
render( 'settings', function () use ( $view, $settings, $rules ) { $view( 'settings', compact( 'settings', 'rules' ) ); } );
render( 'popup settings', function () use ( $view, $settings, $rules ) { $view( 'popup', compact( 'settings', 'rules' ) ); } );
render( 'stats', function () use ( $view, $settings, $rules, $stats ) { $view( 'stats', compact( 'settings', 'rules', 'stats' ) ); } );

echo "\n$ok ok, $fail failed\n";
exit( $fail ? 1 : 0 );
