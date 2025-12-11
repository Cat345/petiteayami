== Change log ==
= 1.27.0 (2025-06-23) =
* Improved: Bump: views improved for broken WooCommerce sessions, ensuring smoother experiences. (#7402)
* Fixed: Bumps: Fixed PHP Error with WPML compatibility. (#7588)

= 1.26.0 (2025-03-05) =
* Fixed: Order Bumps - Moved bumps placeholders to `template_redirect` to ensure they are printed correctly on the page. (#7155)
* Fixed: Bump - Resolved issues with PHP 8.2 for better performance. (#7156)

= 1.25.1 (2025-02-06) =
* Fixed: Resolved a PHP error that occurred in rare cases when `woocommerce_init` ran multiple times within a single request. (#7078)

= 1.25.0 (2025-02-04) =
* Added: OrderBump - Introduced a filter hook, `wfob_printed_price`, to allow customization of bump prices before they are displayed. (#6946)
* Added: OrderBump - Now supports two new positions: "above" and "below" the mini cart for greater flexibility. (#6905)
* Improved: Bump - Compatibility with All product subscriptions plugin improved to handle default plan scheme when no discount added. (#7034)
* Fixed: The sticky button is not showing Order Bump functionality for variable products. (#6843)

= 1.24.1 (2024-10-07) =
* Improved: Bump position on mobile updated to not render after the mini cart. (#6656)
* Fixed: Bump prevented from re-adding on product switcher when the default option is checked. (#6635)
* Fixed: Fatal error during price discount when price is a non-integer value. (#6636)

= 1.24.0 (2024-08-20) =
* Added: A new filter hook was added to modify the default bump position dynamically. (#6465)
* Fixed: Order Bumps with FK checkout not showing up on tablets if the mini cart position was set and if it was hidden on tablets. (#6464)
* Fixed: Bumps getting hidden in cases of multiple checkout opened at once. (#6550)

= 1.23.0 (2024-06-25) =
* Improved: Re-run rules in case of replace cart item settings are enabled. (#6290)
* Improved: Compatibility with YITH WooCommerce Product Add-ons & Extra Options Premium by YITH. (#6098)
* Improved: Price display improved for WooCommerce Subscriptions. (#6097)
* Improved: Compatibility with Discount Rules Core Plugin by Fly Cart updated. (#6086)
* Fixed: Global settings for custom CSS were not working. (#6166)
* Fixed: Compatibility with CURCY – Multi-Currency for WooCommerce updated. (#6099)
* Fixed: Removing and reading products causing few style settings to set as default. (#5918)

= 1.22.0 (2024-04-02) =
* Improved: Bump: Compatibility with Discount Rules Core Plugin by Fly Cart updated. (#6086)
* Improved: Bump price display improved for WooCommerce Subscriptions. (#6097)
* Improved: Bump: Compatibility with YITH WooCommerce Product Add-ons & Extra Options Premium by YITH. (#6098)
* Improved: Bump - Re-run rules in case of replace cart item settings are enabled. (#6290)
* Fixed: Bump: Compatibility with CURCY – Multi-Currency for WooCommerce updated. (#6099)
* Fixed: Bump: Removing and re-adding products causing few style settings to set as default. (#5918)
* Fixed: Bump - Global settings for custom CSS were not working. (#6166)

= 1.21.0 (2024-04-02) =
* Improved: Bump: Compatibility with Divi updated. (#721)
* Improved: Bump: Discounting improved to avoid conflicts with 3rd party discounts & currency switcher plugins. (#700)
* Improved: Bump: Compatibility with Klaviyo updated. (#1705)
* Improved: Bump: Compatibility with WPC Fly Cart for WooCommerce. (#5877)
* Fixed: Bump: Duplicate Order bumps on the store checkout funnel with native checkout were not working fine. (#5934)
* Fixed: Bump: Compatibility with Yith currency exchange plugin updated (#713)

= 1.20.2 (2024-02-09) =
* Fixed: Bump- Title and description fields are switching to default content in case of blank input. (#5631)
* Improved: Bump- Compatibility with CURCY – Multi Currency for WooCommerce updated. (#5159)
* Fixed: Bump- Code refactored to prevent any issues in add to cart functionality.(#5765)

= 1.20.1 (2024-01-24) =
* Fixed: Skins 3 and 4 causing issues in mobile responsive design.(#675)

= 1.20.0 (2024-01-24) =
* Added: Compatibility with FunnelKit Funnel Builder v3.0.0
* Added: New Order Bump Skins
* Added: Ability to pre-check order bumps and hide them after selection
* Fixed: PHP Error in case of WooCommerce multi currency premium addon activated.

= 1.19.1 (2024-01-12) =
* Fixed: PHP warning showing in case of plugin Price Based on Country exists . (#666)

= 1.19.0 (2024-01-10) =
* Improved: Various code and performance optimizations. (#1659)
* Fixed: Regular price strike-through was not working when the sale price was zero. (#666)
* Fixed: Order Bump accept button CSS was getting overridden in case of checkouts designed using block editor. (#5250)

= 1.18.0 (2023-10-17) =
* Improved: OrderBump: Compatibility with OceanWP theme updated. (#5137)
* Improved: Bump reporting improved to cover cases when the order is completed using webhooks. (#5086)
* Fixed: OrderBump: The position above payment gateways was not working when the Rank Math SEO plugin was active. (#5118)
* Fixed: OrderBump: Revenue was saved without taxes. (#5120)

= 1.17.2 (2023-09-19) =
* Fixed: Compatibility updated with ‘Disable REST API’ plugin.(#4987)

= 1.17.1 (2023-09-12) =
* Fixed: PHP Error with Woomulticurcy plugin when Funnelkit Checkout is not active. (#4986)

= 1.17.0 (2023-09-11) =
* Added: WooCommerce HPOS feature compatibility. (#609)
* Added: New settings added to replace all products. (#605)
* Added: New rule operator 'contains exactly' added for cart item tags and category rule. (#629)
* Improved: Do not allow bump product to add to cart if already added once. (#581)
* Improved : Compatibility with Woomulticurcy updated. (4700)
* Improved: Prevent adding duplicate product to the cart. (#581)
* Improved: Mercadopago compatibility updated. (#627)
* Improved: Addtocart pixel events are now compatible with bundle products. (#633)
* Fixed: Prices with Strikethrough not showing up in few cases. (#586)
* Fixed: Text color CSS to prevent override by elementor.(#587)

= 1.16.0 (2023-01-09) =
* Added: Bump: Coupon text match rule added. (#574)
* Added: OrderBump- Loader effect on bump add and remove button. (#549)
* Fixed: Order Bump showing twice when WooCommerce Germanized plugin is active. (#558)
* Fixed: Order Bump- PHP fatal error when width settings are set as blank. (#561)
* Fixed: OrderBump: Checkbox checked state is not showing up on native checkout. (#3175)
* Fixed: OrderBump- Background color settings were not getting applied in the backend in some cases. (#3237)


= 1.15.1 (2022-11-01) =
* Fixed: Admin menu not showing for user roles except adminstrator. (#3307)
* Improved: Checkbox icon font loading improved for mobile devices.(#4442)

= 1.15.0 (2022-10-19) =
* Added: loader UI on add/remove button on bumps. (#540)
* Improved: Compatibility with WC Germanized plugin. (#557)
* Fixed: PHP fatal error when width settings are set as blank. (#561)

= 1.14.1 (2022-10-05) =
* Tweak: Rebranding related changes.

= 1.14.0 (2022-09-28) =
* Added: Action hook added when order bump accepted/rejected. (#523)
* Added: Hook added to modify rules matching behavior for order bumps. (#472)
* Improved: Bumps now automatically show under payment gateway in case they are added in Mini Cart and mini cart is hidden on mobile”. (#3929)
* Fixed: Few PHP notices resolved for PHP v8.1. (#526)
* Fixed: Order bump was not showing up in a few cases when the customer changes address. (#475)
* Fixed: Quantity selector showing up twice on firefox. (#498)
* Fixed: Global settings page was not showing up when only order bump is active. (#530)

= 1.13.0 (2022-01-31) =
* Compatibility with WordPress 5.9.0.
* Compatibility with WooCommerce 6.1.0.
* Added: Replace Bump product with a specific product in the cart. (#421)
* Added: Two new skins added. (#424)
* Added: Compatibility added with 'WooCommerce Dynamic Pricing & Discounts' plugin by RightPress. Disable discounting on Bump products. (#419)
* Added: Compatibility added with 'WooCommerce Memberships' By SkyVerge. Disable membership discounting on Bump products. (#428)
* Added: Compatibility added with 'WooCommerce Multi Currency' plugin by TIV.NET INC. Bump product price as per selected currency. (#438)
* Added: Compatibility added with 'Happy Elementor Addons' by weDevs. JS conflict was there. (#404)
* Improved: Bump style editing admin UI improved. (#424)
* Improved: Bump product wasn't added when it is a part of the group product. (#406)
* Improved: Bump analytics code improved. (#410, #413)
* Improved: Bump product selection admin UI CSS improvement. (#395)
* Improved: One text wasn't translatable, fixed. (#422)
* Fixed: PHP 8.1 compatibility fixes.
* Fixed: Bump CSS was coming on non-required pages, fixed. (#382)
* Fixed: A notice related to image position, fixed. (#465)
* Fixed: Slashed regular price and the sale price, sometimes not displaying, fixed. (#449)
* Fixed: Bump product 'Add' button, multiple clicks prevented. (#391)

= 1.12.0 (2021-09-14) =
* Compatibility with WooCommerce 5.7.0.
* Added: New merge tag for product unit price added.(#371)
* Fixed: Admin screen toggle issue in design settings.(#377)
* Fixed: Order bump reports not saving decimal amounts.(#380)
* Fixed: Add new button on listing was not showing up after elementor update v3.3.0(#385)
* Fixed: Some inline CSS for quick view showing on home page.(#383)
* Fixed: Compatibility with WP-deposits plugin.(#387)
* Fixed: Prevent multiple click on add products button on backend.(#392)
* Fixed: Admin screen CSS issues.(#396)
* Fixed: Rule for time was not working.(#402)
* Fixed: PHP error coming up when page is built using gutenberg and having divi theme enabled.(#400)

= 1.11.0 (2021-06-10) =
* Compatibility with PHP 8.0.
* Compatibility with WooCommerce 5.4.
* Added: New sleeker admin UI.

= 1.10.2 (2021-05-12) =
* Compatible up to WooCommerce 5.3
* Added: New merge tag: {{short_description}} added, outputs the product short description inside the bump. (#365)
* Fixed: RTL CSS styling improved of bumps. (#363)
* Fixed: One JS variable undefined error, fixed. (#367)


= 1.10.1 (2021-04-08) =
* Compatible up to WooCommerce 5.2
* Fixed: A PHP notice when WooCommerce chained product or Yith Bundle Product is in the cart, fixed. (#360)


= 1.10.0 (2021-03-18) =
* Added: Compatibility added with Avada and Woodmart theme to remove lazyload from Bump image. (#287)
* Added: Compatibility added with 'Advanced Coupons for WooCommerce Free' plugin by Rymera Web Co. Supporting auto coupon apply. (#291)
* Added: New rule: 'Customer past product purchased' added. (#302)
* Added: Compatibility added with 'WooCommerce All Products For Subscriptions' plugin, Double discounting occurring, fixed. (#309)
* Added: Compatibility added with 'PayPal PLUS for WooCommerce' by GMBH. Bump line item wasn't showing on PayPal payment screen, i.e. total haven't changed. (#315)
* Added: Compatibility added with 'Booster For WooCommerce' plugin by Booster.IO. (#351)
* Improved: Minor CSS improvement on Bump frontend. (#297, #304)
* Improved: Compatibility updated with 'CheckoutWC' plugin. PHP error in Ajax calls. (#300)
* Fixed: Bump title was still appearing even when empty, was showing default value, fixed. (#296)
* Fixed: Checkout pages created using Aero, keeps reloading if cart doesn't contain any product i.e. cart is empty. (#306)
* Fixed: PHP notice appearing in quick view popup in a rare case. (#317)
* Fixed: A scenario where bump item left in the cart even when primary item is removed. Issue occurs when more than 1 bump products available in the cart. (#319)
* Fixed: An issue with reporting related to item discounts in bump revenue. (#327)
* Fixed: An issue with Braintree plugin after bump is added. Cart total mismatch issue. Compatibility updated. (#333)
* Fixed: Sometimes quick view is not opening, results in PHP error. Due to price fetching functions. (#339)


= 1.9.1 (2020-11-25) =
* Fixed: PHP error with bump reporting.
* Improved: Optimizations in bump reporting.
* Fixed: Product deletion in the admin in Bump, showing the deleted product, fixed.


= 1.9.0 (2020-11-20) =
* Added: Two new Bump skins added.
* Added: Product category rule: New option `doesn't contain` added.
* Added: New rule: Cart item tag added.
* Added: Compatible with `Price Based on Country` plugin by Oscar Gare.
* Compatible up to WordPress 5.5.3
* Compatible up to WooCommerce 4.8
* Compatible with upcoming WooFunnel releases.
* Improved: Order Bump reporting improved.
* Improved: Quantity switcher UI improved.
* Improved: Optimize ajax calls on the checkout page on bump acceptance and removal. Overall performance improved.
* Improved: Handled scenario when the cart is virtual and change to physical via bump or vice versa.
* Fixed: PHP error during customer rules inside WP admin dashboard.
* Fixed: Compatibility added WC subscription plugin, allowing of correct price display.
* Fixed: When free product i.e. $0 value bump accepted, Single order is not showing the bump price on order listing screen.
* Fixed: An issue arose with `WP clever smart bundle` plugin. Fixed.


= 1.8.1 (2020-01-29) =
* Compatible with WooCommerce 3.9
* Fixed: Sometimes preview shows PHP errors when saved product is not available, fixed.


= 1.8.0 (2019-12-11) =
* Added: Rule operator Coupon 'matches none of'. Now control visibility of Bumps based for certain coupons
* Added: Bump import and export feature to import bumps from one site to another.
* Added: Compatibility with A/B Experiments for WooFunnels.
* Improved: Admin UX at few places
* Updated: Woofunnels core


= 1.7.2 (2019-11-14) =
* Added: Future compatibility with Woofunnels A/B experiments plugin.
* Added: Compatibility with WordPress version 5.3.0.


= 1.7.1 (2019-10-14) =
* Fixed: Sustain credit card details when bump product added or Removed.
* Fixed: Compatibility with Porto theme.


= 1.7.0 (2019-10-11) =
* Added: Detailed order bump reports added. Reporting available by date and by specific bumps. Available under WooCommerce > Reports > OrderBump
* Added: Merge tag: {{quantity_incrementer}} to allow buyers to increment quantity of bump.
* Added: Merge tag: {{variation_attribute_html}} to display selected variants labels (for variable products).
* Added: Compatibility with Finale WooCommerce Countdown timer plugin. Allowing Finale Shortcode in Bump description.
* Added: Compatibility with WOOCS - WooCommerce Currency Switcher by Realmag777.
* Improved: UX improved for variable products 'Choose an option' popup.
* Improved: Few cases handled of shipping methods when subscription product as a bump product is added or removed.
* Fixed: On Mobile collapsible 'cart total' updated on adding OrderBump to cart.
* Fixed: An issue with Pagar.me gateway in case instalment plan is chosen.
* Fixed: 'AeroCheckout page is' rule wasn't working in case of Aero embed page, fixed now.
* Fixed: Compatibility improved with WC Checkout Add-ons plugin when the bump is added or removed.
* Fixed: A bug with a Braintree payment gateway with its latest version.


= 1.6.3 (2019-04-16) =
* Improved: Display product title in case variation is not added to cart, in case of variable product.
* Fixed: Gateway conflict with latest version of WC Germanized, compatibility updated.


= 1.6.2 (2019-04-23) =
* Fixed: Cart item contains product rule code fixed.


= 1.6.1 (2019-04-18) =
* Fixed: Global setting page JS error resolved.
* Fixed: Holding payment gateways refresh until gateways list changed while adding or removing Bump product.


= 1.6.0 (2019-04-17) =
* Added: New setting 'Bump display' positions introduced. * Added: New rule 'Cart total' added
* Added: Compatible with Aero v1.8 as much changes done to support it.
* Improved: Bump end-user experience improved.
* Improved: Removing 'Bump added' items from the cart if rules got invalidated.
* Improved: Re-validating Bump rules when a coupon is added or removed.
* Fixed: Fixed discount was restricted to value 100 max, fixed.
* Fixed: Cart item quantity rule had a small issue, fixed.


= 1.5.0 (2019-01-17) =
* Added: Custom CSS setting added globally.
* Added: Maximum Bump display count setting added globally.
* Added: Support with MercadoPago payment gateway when Bump added or removed from the order.
* Added: {{product_name}} merge tag added to show the product name.
* Added: Support with 'Variation swatch' plugin by 'theme alien' to display swatches in bump product preview.
* Improved: Quick hints added under fields in the admin area.
* Improved: Compatibility code improved for 'WC Germanized' v2.2.7
* Improved: Handling added for cart total 0 case.
* Improved: Product selection rule caused a PHP error when the selected product was removed from the store.
* Fixed: Impreza theme causing conflict in admin area during bump creation, fixed now.
* Fixed: Porto theme caused PHP error as used a native WC hook with less variables.


= 1.4.0 (2018-12-19) =
* Added: WPML compatibility also allows WPML language duplication of Bumps.
* Added: Did additional code handling with 'InfusedWoo' plugin, supporting their subscriptions now.
* Fixed: Mixed product type issue resolved with Bump on checkout pages.


= 1.3.0 (2018-11-16) =
* Added: Compatible with 'WC radio buttons' plugin.
* Added: Compatible with 'Improved variable product attributes' plugin.
* Added: New field for error color in the design section.
* Improved: Remove quantity input in cart page for bump product.
* Fixed: Variable product wasn't adding to cart with older AeroCheckout version. Now compatible with AeroCheckout v1.5.
* Fixed: Rules validation now working for cart item (variation product)


= 1.2.1 (2018-10-31) =
* Improved: Sustain credit card details when bump product add or Removed. Use woocommerce fragment functionality in our ajax and refresh the essential parts of checkout page
* Improved: when subscription product removed from order bump and cart get empty then we reload the checkout page. This occur due subscription plugin  not work with mixed cart.
* Improved: Show Product Stock Error message when user add bump to cart in case of out of stock.


= 1.2.0 (2018-10-21) =
* Added: pot file added for translations.
* Improved: 'Select option' text replaced to 'choose option' with WooCommerce textdomain. Auto change to multi languages.
* Fixed: Add to cart button was not displaying for order bump pop up, now resolved.


= 1.1.0 (2018-10-12) =
* Improved: Compatible with Aero Checkout new version.
* Improved: Bump skin change, opt modal UI improved.


= 1.0.2 (2018-10-05) =
* Added: Allowed Course product type from LearnDash plugin to include as a Bump.


= 1.0.1 (2018-10-05) =
* Fixed: Debug class calls directly, fixed now.


= 1.0.0 (2018-10-03) =
* Public Release
