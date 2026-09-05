=== Woo Free Gifts Premium ===
Contributors: zauni1984
Tags: woocommerce, free gift, gift, cart, promotion, bundle, popup
Requires at least: 6.2
Tested up to: 6.8
Requires PHP: 7.4
WC requires at least: 7.0
WC tested up to: 9.9
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Premium free gift engine for WooCommerce: cart value thresholds, buy-X-get-gift, bundles, custom (non-catalog) gifts, gift choice, progress bar, promo popup and a daily wheel of fortune.

== Description ==

Woo Free Gifts adds gifts to the cart automatically when a rule matches – and removes them again when it stops matching.

**Rule conditions (combine freely, all must match)**

* Minimum / maximum cart value (e.g. free seed from 50 €, book from 100 €)
* Required products or variations – all of them or any of them, with a minimum quantity ("buy B and C, get X")
* Required categories (child categories included)
* Bundle products in the cart (WooCommerce Product Bundles, WPC, YITH, Composite, Grouped) – bundled items count for product conditions
* Minimum item count
* Customer roles, logged-in only, once per customer account
* Date range

**Gifts**

* Any catalog product or variation – stock is respected, out-of-stock gifts are skipped
* Custom gifts that are not listed in the shop (name, image, description, weight, virtual) – stored as hidden products, excluded from shop, search, feeds, sitemaps and related products, not purchasable on their own
* Several gifts per rule: add all automatically or let the customer pick one in the cart
* Quantity per gift
* Stacking: every qualifying rule adds its gifts, or only the highest rule
* Stock for custom gifts (WooCommerce stock management on the hidden product), order budget per rule, "Only X left" scarcity line and low-stock admin warning

**Customer experience**

* Progress bar in cart, checkout and mini cart ("Add 12,50 € more to get …")
* Gift hint on single product pages
* Gift badge and "Free" price in cart, checkout, e-mails and orders (old price struck through)
* Gift quantity locked, coupons never apply to gifts, optional "customer may remove gift"
* One-time promo popup on product pages and product archives (session / X days / once / always), cache-friendly, accessible, keyboard closable
* Shortcodes `[wfg_progress]` and `[wfg_gift_list]`
* Compatible with the classic cart/checkout and the block cart/checkout (quantity locked via Store API)

**Wheel of fortune**

* Popup wheel in a 420 / stoner style (dark green, neon glow, hemp leaf, drifting smoke) or classic white
* One spin per cooldown window, enforced server-side via account, session, signed cookie, hashed IP and hashed e-mail
* 2–12 weighted segments: coupons (auto-generated single-use codes or existing codes), free gifts or "no prize"
* Optional e-mail capture with consent checkbox, statistics and spin log

**Safe by design**

* Never crashes the site: WooCommerce/PHP version checks, every cart sync wrapped in try/catch with WooCommerce logging
* Nonces + capability checks on every admin action and AJAX call, all input sanitized, all output escaped
* HPOS (High-Performance Order Storage) compatible
* Order line items carry the gift rule as meta; per-rule statistics
* Data is only removed on uninstall when you enable it in the settings

== Installation ==

1. Upload the `woo-free-gifts` folder to `/wp-content/plugins/` or install the ZIP via Plugins → Add New.
2. Activate the plugin. WooCommerce must be active.
3. Go to WooCommerce → Free Gifts and create your first rule.

== Frequently Asked Questions ==

= How is the cart value calculated? =

The subtotal of all non-gift items, excluding or including tax (setting), optionally after coupon discounts. Gifts never count.

= What happens if a customer removes a gift? =

If "customers may remove a gift" is enabled, the gift is not re-added until the rule stops matching and matches again. Otherwise the gift is re-added on the next cart update.

= Can I show the progress bar in the block cart? =

Yes, add a Shortcode block with `[wfg_progress]` above the cart block.

= Where do I see which orders contained gifts? =

Each gift line carries a visible "Free gift" meta with the rule name. Statistics per rule are under WooCommerce → Free Gifts → Statistics.

== Changelog ==

= 1.2.0 =
* Stock field for custom gifts (WooCommerce stock management on the hidden product).
* Order budget per rule ("max. orders"), rule switches off when used up.
* "Only X left" scarcity line in progress bar and popup, low-stock admin warning.
* Rules whose gift is out of stock are no longer offered as next target.
* Built-in updates from GitHub releases (Update URI), optional token for private repositories.

= 1.1.0 =
* Wheel of fortune with daily spins, coupon and gift prizes, 420/stoner and classic themes.
* Server-side prize selection and cooldown (account, session, signed cookie, hashed IP, hashed e-mail).
* Spin statistics and log.

= 1.0.0 =
* Initial release: gift rules, custom hidden gifts, gift choice, progress bar, promo popup.
