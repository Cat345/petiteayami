=== Bundles For WooCommerce ===
Contributors: funnelkit
Tags: woocommerce, product bundles, fixed bundle, frequently bought together, bundle discount
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
WC requires at least: 8.8
Stable tag: 1.0.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create Fixed Bundle and Frequently Bought Together offers for WooCommerce, right on the product page.

== Description ==

Bundles For WooCommerce lets you build two kinds of bundle offer from your existing catalog and show them on the product page shoppers are already looking at.

Key features:

* **Fixed Bundle**: sell a set of specific products together as one packaged offer, priced as a whole or per item.
* **Frequently Bought Together**: show the products that go with the one being viewed and let shoppers add them together, with a discount for taking them as a set.
* A storefront widget that renders on the product page, themed to match the active theme.
* Configurable placement (above or below the Add to Cart button, or within the product summary).
* Compatibility with popular page builders and checkout/upsell plugins.

Looking for Quantity Breaks, progressive gifts, bundle-dependent upsells, Mix & Match, or analytics? Those are part of the separate FunnelKit Funnel Builder Pro - Bundles Addon. Bundles ships as a module of FunnelKit Funnel Builder and works fully there, with no license or upgrade required for the features above.

It requires WooCommerce to be installed and active.

== Installation ==

1. Install and activate FunnelKit Funnel Builder. Bundles comes with it, in its `modules/bundles/` folder.
2. Make sure WooCommerce is installed and active.
3. Configure your bundles from FunnelKit > Bundles.

== Frequently Asked Questions ==

= Does Bundles require WooCommerce? =

Yes. WooCommerce must be installed and active for Bundles For WooCommerce to work.

= What is the minimum WooCommerce/WordPress version? =

Bundles requires WordPress 6.5 or later and PHP 7.4 or later.

== External services ==

Bundles does not connect to any external service. It makes no HTTP requests of its own and loads no remote fonts, scripts or styles.

The admin screens contain ordinary links to funnelkit.com. They open only when clicked, and Bundles sends no data to that site.

== Source code ==

Unminified source for Bundles' compiled JavaScript and CSS, along with the build configuration and build steps, is in FunnelKit Funnel Builder's source folder, under `modules/bundles/`: https://drive.google.com/drive/folders/1Z-R0_Nbb0BOCyqauxvCftjhS9Ve--IHH

To build the admin app from that source:

1. `npm ci`
2. `npm run build`

This produces `admin/app/dist/`, which Bundles enqueues in wp-admin.

== Changelog ==

= 1.0.1 (Nov 20, 2026) =
* Improved: Bundles: Stores whose plan includes the Bundles Addon now see an Install Bundles Addon button on the Quantity Breaks and Mix & Match cards, with a Download from My Account link beneath it. (#9933)
* Fixed: Bundles: Design tab changes on a Frequently Bought Together bundle now show in the live preview right away, without saving. (#359)
* Fixed: Bundles: Upsell and progressive gift setup is now kept when its toggle is switched off and back on. (#360)

= 1.0.0 =
* Initial release.
* Fixed Bundle: a set of specific products sold together as one pack, discounted as a whole or per item. Members that are variable products have their options chosen in the widget.
* Frequently Bought Together: products that go with the one being viewed, from a list you choose or from the product's own cross-sells, added together with a discount on the set.
* Create and edit bundles from the Bundles screen under Marketing, after Coupons, or from the Bundles tab on the product edit screen.
* Bundle lines are grouped in the cart, checkout, order, emails and Thank-You page, on both the classic and the block cart and checkout.
* Archive and loop "Add to cart" buttons for a Fixed Bundle product link to the product page, so its options are chosen before it is added.
* Elementor and Elementor Pro compatibility, including the "Custom Add To Cart" widget, the Menu Cart dropdown and the editor preview. Divi and Bricks product pages are supported too.
* FunnelKit Funnel Builder compatibility: a bundle can be sold on the Aero checkout and through One-Click Upsells.
* FunnelKit Cart compatibility: bundle lines are grouped in the slide-out cart.
* Multi-currency compatibility with FOX Currency Switcher (WOOCS), Aelia Currency Switcher and YayCurrency. Bundle prices, discounts and totals are correct in every currency, including YayCurrency rounding rules and per-currency fixed prices.
* Discount Rules and Advanced Dynamic Pricing compatibility: bundle lines keep the bundle price and the other plugin's rules apply around them.
* FunnelKit PayPal and WooCommerce PayPal Payments compatibility for buying a bundle from the product page buttons.

== Upgrade Notice ==

= 1.0.1 =
Shows an Install Bundles Addon button on the Quantity Breaks and Mix & Match cards, updates the FBT Design tab preview without saving, and keeps upsell and gift setup when its toggle is switched off and on.

= 1.0.0 =
Initial release.
