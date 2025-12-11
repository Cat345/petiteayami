=== FunnelKit One Click Upsells ===
== 3.18.0 (22/10/2025) =
* Fixed: Upsells: Compatibility updated with PayPal payments. (#8033,#8084)
* Fixed: Upsells: Compatibility updated with mollie plugin to retry fetching customer mandate before opening offer. (#8072)
* Improved: Upsells: Fixed mollie order description for upsell charge when SEPA disabled to pass offer ID. (#8200)
* Improved: Upsells: Compatibility updated with EU/UK VAT plugin related to destroyed upsell session. (#8178)
* Improved: Upsells: Enhanced recurring price and signup fee settings for upsells price widget for block editor. (#8111)
* Improved: Upsells: Improved Facebook events tracking for variations in offer page to handle few edge cases. (#8210)

== 3.17.0 (04/08/2025) =
* Improved: Upsells: Enhanced compatibility with Braintree for WooCommerce Payment Gateway. (#7889)
* Fixed: Upsells: Resolved emails not sending after orders when BACS or cheque was used, and upsells were skipped in an edge case. (#7895)
* Fixed: Upsells: Resolved PHP notice on admin pages for upsells. (#7868)
* Fixed: Upsells: Resolved test gateway not showing in checkout when WooCommerce PayPal Payments is active. (#7880)
* Fixed: Upsells: Resolved issue where purchase events were not firing for Facebook CAPI when there was more than one pixel on offer pages. (#7942)

== 3.16.0 (23/06/2025) =
* Improved: Upsells: Compatibility with Braintree gateway with 3DS card scenario improved for upsell payments. (#7296)
* Fixed: Upsells: Gutenberg blocks causing JS errors when switching to style tabs. (#7313)
* Fixed: Upsells: PHP Error during PayPal IPN with WooCommerce subscription v7.5.0. (#7570)
* Fixed: Upsells: Mollie compatibility improved to handle edge cases of multiple user return & skipping upsells in v7.5.0. (#7373, #7400, #7565)

== 3.15.1 (07/03/2025) =
* Fixed: PHP Error and warning during offer design edit page made using customizer. (#7217)

== 3.15.0 (05/03/2025) =
* Improved: Upsells - Enhanced Authorize.net CIM integration to ensure the CIM feature is checked before setting up upsells. (#7117)
* Improved: Upsells - Updated compatibility with WooPayments plugin to handle 3DS card scenarios more effectively. (#7135)
* Improved: Upsells - Fixed upsell setup issues that occurred during `apply_coupon` requests in Funnel Builder checkout. (#7136)
* Fixed: Upsells - Resolved CSS issues with Divi Visual Builder on the upsell page for a more polished appearance. (#7121)
* Fixed: Upsells - Resolved upsell order note duplication that happened when `getReturnRedirectUriForOrder` ran multiple times. (#7107)

== 3.14.1 (06/02/2025) =
* Fixed: Improved compatibility with PixelYourSite by addressing a PHP error for setups using older versions (v9.3 or lower). (#7079)

== 3.13.0 (17/12/2024) =
* Improved: Updated compatibility with WooPayments for seamless handling of 3DS authentication payments. (#6885)
* Improved: Enhanced compatibility with All Product Subscriptions and Bundled Products for smoother functionality. (#6838)
* Fixed: PHP Error coming on admin screen in case of a few specific setups. (#1926)

= 3.12.4 (21/11/2024) =
* Added: Upsell Recovery feature that allows new credit card form input when a transaction fails with FunnelKit Stripe Gateway. (#6822)
* Added: New rule in upsells for FunnelKit Automations contact tags. (#6853)
* Improved: Handling for the shipping address-related scenario for the case where upsell contains shippable product but the parent order is virtual. (#6822)
* Improved: New icons were added for upsells processing, failed & confirmation popups. (#1802)
* Fixed: Compatibility with WooPayments for v8.5.0 (#6865)


= 3.12.3 (09/10/2024) =
* Fixed: PHP Error while loading admin global settings page.

= 3.12.2 (07/10/2024) =
* Improved: Remove 'Domain' from Pixel event data as it is considered PII by Meta in case of some domains. (#6648)
* Improved: Upsell skipped order notes reordered for accurate skipped reasons. (#6715)
* Improved: Updated Compatibility with PayPal payments. (#6741)

= 3.12.1 (26/08/2024) =
* Fixed: Upsells with mollie gateways are not working since the last update. (#1872)

= 3.12.0 (20/08/2024) =
* Added: Detailed reasons for skipped upsells are now included in order notes, facilitating easier troubleshooting. (#6390)
* Added: A new personalization shortcode is available to display any order metadata. (#6554)
* Improved: The loading sequence of external scripts has been optimized to ensure they load after the event tracking script. (#6535)
* Improved: Gateway integrations are now restricted to known and supported integrations, enhancing reliability. (#6393)
* Improved: Security measures have been strengthened to better protect the plugin. (#5919, #6540)
* Fixed: Resolved a styling issue with the Short Description block widget within the editor. (#6514)
* Fixed: Addressed an issue where certain upsell rules dependent on order data were not functioning correctly when using OR conditions. (#6492)

= 3.11.2 (19/07/2024) =
* Fixed: Upsells compatibility with WooCommerce Stripe gateway for the v3.8.0 and above. (#6482)
* Fixed: PHP Error was handled in order-edit the screen for the upsell refund metabox in an edge case. (#6453)

= 3.11.1 (15/07/2024) =
* Added: Compatibility with WordPress version 6.6.(#6422)

= 3.11.0 (25/06/2024) =
* Improved: Compatibility with Woodmart theme updated. (#6079)
* Improved: Compatibility with WooCommerce Payments updated. (#6216, #6296)
* Fixed: Upgrade funnel calculation was missing shipping tax calculation. (#6223)
* Fixed: Upsell analytics were not getting deleted on order deletion in the case of HPOS. (#6091)
* Fixed: Flickity assets showing not found in case of shortcode used for image slider. (#6156)
* Fixed: Compatibility with WooCommerce germanized was not loading. (#6188)
* Fixed: PHP Error on WooCommerce order edit screens in a few cases where meta boxes reordered and ACF plugin is active. (#6259)

= 3.10.0 (02/04/2024) =
* Improved: Handle offer redirect link in case the offer post is deleted but its metadata still exists. (#1761)
* Improved: Compatibility with WooCommerce Payments updated. (#1758)
* Improved: Compatibility with Authorize.Net CIM gateway updated. (#1770)
* Improved: Upsells are now displayed on the store checkout page even for orders placed without going through the checkout process. (#1756)
* Fixed: Preview links were not correct in admin when offers were created using legacy custom page mode. (#5903)
* Fixed: Restricted phone number to prevent sending empty values on TikTok. (#1768)
* Fixed: WooCommerce Order meta-box drag was not working for non-HPOS setups. (#1762)
* Fixed: Rules were not working correctly in a few cases on the order-pay page. (#1757)
* Fixed: Compatibility with WooCommerce Memberships plugin not working since v3.0.0. (#5984)

= 3.9.5 (08/02/2024) =
* Improved: plugin security by escaping html class output for shortcodes. (#5818)
* Fixed: Refunds are not working with WooPayments v7.0.0 or greater. (#5779)
* Fixed: Order attribution meta in case of new order was not updated in WC v3.6.0 or greater. (#5856)

= 3.9.4 (08/02/2024) =
* Improved: Upsell: Compatibility updated with GeneratePress plugin. (#5639)
* Fixed: Issue with offer amount being zero in case of fixed amount discount with multiple qty. (#1733)
* Fixed: PHP notice related to mysql query in order confirmation page. (#1734)

= 3.9.3 (24/01/2024) =
* Added: Compatibility with FunnelKit funnel builder v3.0.0
* Fixed: Jump to offer settings were not copying over when importing upsells/funnels from other sites. (#1720)

= 3.9.2 (18/01/2024) =
* Fixed: Compatibility with Curcy currency switcher premium plugin updated to resolve an error on customizer template editing. (#1712)

= 3.9.1 (17/01/2024) =
* Improved: Compatibility with Woodmart theme. (#1704)
* Fixed: Compatibility with Aelia - Currency Switcher plugin updated to resolve error on checkout in some servers. (#1705)

= 3.9.0 (10/01/2024) =
* Improved: Various code and performance optimizations. (#1659,#1695)
* Improved: Compatibility with CURCY - WooCommerce Multi Currency plugin updated. (#1689)
* Improved: Compatibility with Aelia - Currency Switcher plugin updated. (#1698)

= 3.8.0 (23/11/2023) =
* Improved: Show parent order in single order UI in case of upsells new order. (#1685)
* Fixed: Template edit URL for oxygen builder was now allowing editing in some cases. (#5158)
* Fixed: Some price-layout settings do not sustain while switching devices in block editor. (#1675)
* Fixed: Taxes were not working correctly with multiple quantity from quantity switcher. (#1678)
* Fixed: Fatal error during cron emails send in case of bacs in some cases. (#1681)
* Fixed: Compatibility updated with germaized plugin. (#1683)
* Fixed: PHP error during checkout from WooCommerce Payments integration when plugin version is 7.1.0 or greater in case of 3ds cards. (#1687)

= 3.7.0 (18/10/2023) =
* Improved: Handling for the PHP Error on the edit order screen when the user meta was set incorrectly. (#5106)
* Fixed: Facebook server events were not showing up while testing with test_code. (#5101)
* Fixed: Item value was multiplying quantity for the GA4 events. (#5121)

= 3.6.7 (20/09/2023) =
* Fixed: PHP Error 'header_already_sent' error showing when tiktok events are enabled in some cases. (#5030)

= 3.6.6 (19/09/2023) =
* Fixed: Compatibility updated with ‘Disable REST API’ plugin.(#1638)

= 3.6.5 (18/09/2023) =
* Added: Compatibility with PHP v8.1. (#1640)
* Improved: Better error handling during the tokenization to prevent 'INTENT_MISMATCH' error. (#1637)

= 3.6.4 (12/09/2023) =
* Added: WooCommerce HPOS feature compatibility. (#4800)
* Fixed: Upsells-  Refund parent order not working in case of free product + shipping. (#1611)
* Fixed: Upsells- Free trial upsells were not working in case of a few gateways. (#4726)
* Fixed: Upsells- Dynamic Shipping was not working in the case of elementor popups on offer pages. (#1626)
* Fixed: Upsells - Upsells with Bundle product + subscriptions +  free trial getting charged for subscription amount. (#1634)

= 3.6.3 (02/07/2023) =
* Fixed: Handling for the critical case where meta key from WooCommerce function 'WC_Order::get_meta()' not being fetched reliably causing multiple emails.

= 3.6.2 (02/07/2023) =
* Added: Partial compatibility with PHP v8.1. (#1609)
* Added: Compatibility with elementor v3.15.0. (#1612)
* Fixed: Upsell refund parent order not working in case of free product + shipping. (#1611)

= 3.6.1 (19/06/2023) =
* Fixed: PHP error coming during install on few sites where paypal gateway was disabled. (#1579)
* Fixed: Elementor templates were not imported correctly on WPML setups. (#1584)
* Improved: Compatibility with PayPal Payments improved for timeout error scenarios. (#1582)
* Improved: Gutenberg blocks now support custom colour pallet. (#1552)

= 3.6.0 (14/04/2023) =
* Added: Upsell/Thankyou- A new rule ‘Order Item – Text Match’ added. (#1554)
* Improved: Handling while importing elementor template to prevent any possible issues with 3rd party plugins.(#1568)
* Improved: Setting to toggle display of icon in oxygen accept button widget.(#1566)
* Fixed: Upsell refunds are not working for the offer payments by PayPal Payments with ‘create new order’ settings enabled. (#1548)
* Fixed: Offer payment failing for WooCommerce Stripe Gateway v7.3.0. (#1570)
* Fixed: Upsells- Dynamic offer path settings were not cloning correctly during duplicate action. (#1564)
* Fixed: Rule for Coupon text match was not working with does’t contain operator. (#178)


= 3.5.2 (28/02/2023) =
* Added: A filter added to modify cancel order settings. (#1536)
* Improved: Compatibility with square Payment gateway to handle cases with existing users. (#1534)
* Improved: Removed old updater methods. (#1539)
* Improved: CSS improved w.r.t background color for canvas and boxed templates. (#1541)
* Improved: SQL query optimized during thankyou hook cron action. (#1543)

= 3.5.1 (18/01/2023) =
* Improved: Compatibility with Bricks builder updated. (#1527)
* Fixed: Error processing automatic renewals for the subscriptions created by upsells for stripe gateway from v3.5.0. (#1529)
* Fixed: Upsell cancel primary order settings was not working for free trial order. (#1523)
* Fixed: PHP deprecated hook notice for elementor v3.5.0 or greater. (#1525)

= 3.5.0 (09/01/2023) =
* Added: Compatibility with WooCommerce Sequential Order Numbers Pro. (#1454)
* Added: Added a filter `wfocu_gateways_paypal_support_non_reference_trans` to allow devs to show settings for their PayPal gateway. (#1487)
* Improved: Compatibility with WooCommerce Amazon Fulfillment updated for v4.0.0. (#1471)
* Improved: Upsell timeline updated to show appropriate reason when the gateway does not support subscription products. (#1475)
* Improved: Restrict registering offer page assets to site pages, causing conflicts in some cases. (#1480)
* Improved: Updated new order creation script to execute woocommerce_new_order action hook. (#1486)
* Fixed: Compatibility with Square gateway updated, showing errors during upsell accept. (#1506)
* Fixed: PHP error was showing up with learndash compatibility in a few cases. (#1508)


= 3.4.2 (31/10/2022) =
* Added: New filter to modify shipping methods priority on upsell pages for dynamic shipping. (#1145)
* Improved: Compatibility with PYS CostOfGoods plugin updated. (#1452)
* Fixed: Offer payments issue with WooCommerce Payments. (#1436)
* Fixed: Admin menu not showing for user roles except administrator. (#3307)
* Fixed: Pass current user ID as external ID to Facebook pixel event to avoid mismatch of external_id. (#1458)
* Fixed: One Click Order refund metabox was not showing in some cases. (#3266)

= 3.4.1 (05/10/2022) =
* Tweak: Re-branding related changes.

= 3.4.0 (29/09/2022) =
* Added: Compatibility with Bricks themes. (#1347)
* Added: Filter hook 'wfocu_script_tags' to allow dynamic attributes to the script tag for the tracking snippets. (#1322)
* Added: Shortcode added to display product original sale price. (#1360)
* Added: Javascript filters to skip tracking in favor of cookie consent plugins. (#1305)
* Added: Compatibility with Kadence theme. (#1412)
* Improved: Compatibility with elementor 3.7.0 version. (#455)
* Improved: WooCommerce multicurrency compatibility updated to check if enabled in settings before conversion. (#1320)
* Improved: Google ads enhanced e-commerce data pass with the purchase events. (#1324)
* Improved: The product description widget will now show product description from the parent in case of variation product. (#1335)
* Improved: Handle dynamic shipping taxes for the offers when tax is exempted in order. (#66)
* Improved: Behaviour for the cancel and upgrade feature modified in favor of processing fewer refunds. (#1331)
* Improved: Author support added for the offer post type. (#1376)
* Improved: Method to get the client IP address to improve event match quality for Facebook conversion events. (#1391)
* Improved: Facebook events will now have external_id param for the logged-in user to improve event match quality. (#1371)
* Improved: Facebook conversion api events improved to pass _fbc param in cases when pixels were not dropped. (#1354)
* Fixed: Issue with the WooCommerce Payments, refunding offer was not working. (#1318)
* Fixed: Block Compatibility issues with WordPress v6.0 (#1316)
* Fixed: An issue of reporting data not getting inserted properly in MySQL table due to column length. (#1333)
* Fixed: Few Styling-related bug fixes for the Gutenberg image gallery block. (#1334)
* Fixed: Few PHP notices resolved for PHP v8.1. (#1369)
* Fixed: Offer refund was failing in some cases for the authorize.net CIM gateway v3.7.2 (#1392)
* Fixed: Tax on upsell offers not getting applied for some cases with default customer address set add shop address. (#1396)
* Fixed: Deprecated PHP warning showing up during Facebook pixel after WooCommerce v6.0.0 when coupon used in the order. (#1414)
* Fixed: Offer payments were failing for WooCommerce PayPal payments in some cases when subscriptions in the primary order. (#1423)
* Fixed: Javascript error showing up when jquery is loaded deferred for the checkout. (#1426)

= 3.3.6 (10/05/2022) =
* Fixed: Offer Payments were failing with WooCommerce Square gateway v3.0.0 in case of different shipping address than billing. (#1312)

= 3.3.5 (09/05/2022) =
* Added: Support for Featured image for the offer post type. (#1289)
* Improved: Compatibility updated with Woodmart theme. (#1288)
* Improved: Avoid setting up upsell sessions multiple times in any case of conflict. (#1285).
* Improved: Facebook conversion API events failing in some cases of the backward cache of browsers. (#1295)
* Fixed: Pass item-total instead of item subtotal to cover discount cases at the item level in GA analytics. (#1277)
* Fixed: Offer Payments and primary checkout payments were not working with WooCommerce Square gateway v3.0.0 (#1306)
* Fixed: Checkout payments done by WooCommerce PayPal payment's credit card method was not working in case of 3ds since v4.1.0. (#1308)

= 3.3.4 (06/04/2022) =
* Fixed: Offer payments were failing for the WooCommerce Payments v3.9.0. (#1279)

= 3.3.3 (29/03/2022) =
* Added: Compatibility with Woodmart theme. (#1267)
* Improved: Improved Google Tag execution to prevent double events in case of Backward/Forward browser cache. (#1262)
* Fixed: Offer payments were failing for the WooCommerce Payments v3.9.0. (#1269)

= 3.3.2 (23/02/2022) =
* [Critical] Fixed: PHP Errors showing up on offer pages built using elementor on elementor version 3.6.0. (#1130)
* Fixed: Google ads conversion label passing with the custom events. (#1261)

= 3.3.1 (16/02/2022) =
* Fixed: Issue with a variable product purchase, variation ID was not getting attached as item meta since the last release. (#1251)
* Fixed: Issue with Upsell Payments failing for WooCommerce PayPal Payments gateway returning invalid token in some rare cases. (#1254)
* Fixed: A PHP notice showing up on update_order_review ajax request in some scenarios when WP_DEBUG set to TRUE. (#1256)

= 3.3.0 (11/02/2022) =
* Compatible with WordPress 5.9.2.
* Compatible with WooCommerce 6.3.1.
* Added: Support for WordPress revision feature for the offer post type. (#1211)
* Added: Few more controls in Gutenberg quantity selector widget. (#1218)
* Added: Compatibility with 'Cost of Goods by PixelYourSite' plugin. (#1215)
* Improved: Snapchat events firing add billing along with the purchase. (#1203)
* Improved: Application of quantity selector improved to add qty instead of multiple line items. (#1233)
* Improved: Fire PageView event even if storewide settings are ON by funnel builder. (#1209)
* Fixed: PayPal standard primary payments were throwing PHP error on WooCommerce version 6.3.1. (#1248)
* Fixed: Fatal error showing up while accessible past oxygen template after oxygen builder deactivated. (#1227)
* Fixed: Error while tracking custom events due to special characters in bump name. (#1223)
* Fixed: A fatal error while importing templates with few cases when WPML is active. (#1200)
* Fixed: Issue with Paypal payments offer payments showing incorrect total errors in a few cases. (#1180)
* Fixed: Issue with fresh elementor setups requiring toolkit generation. (#1213)
* Fixed: Remove extra line items getting added in square order for offer payments in a few cases. (#1230)
* Fixed: Snapchat tracking not working with native thankyou page. (#1243)

= 3.2.0 (01/02/2022) =
* Compatible with WordPress 5.9.0.
* Compatible with WooCommerce 6.1.1.
* Added: Support for Funnel Builder v2.0. (#1132)
* Added: Tracking events now supports TikTok and Snapchat. (#1132)
* Improved: Elementor and Divi: Templates importing speed is improved. (#1188)
* Improved: Default output shows for all page builder widgets even when no product is selected. (#1168)
* Improved: Compatibility updated with 'AffiliateWP' plugin. A PHP notice was coming, fixed. (#1177)
* Improved: esc_sql() method used as suggested by the WordFence for post meta query during upsell duplicate action. (#1176)
* Fixed: Issue with offer getting skipped if stock is not managed and same product in the primary order. (#1159)
* Fixed: Elementor `Accept button` block, icon position wasn't working, fixed. (#1185)
* Fixed: Compatibility updated with 'AffiliateWP' plugin, a PHP error in a case. (#1178)
* Fixed: PHP 8.1 compatibility fixes. (#1190)

= 3.1.0 (27/12/2021) =
* Added: Filter hook added to allow custom fonts for the Gutenberg blocks. (#1119)
* Added: Filter hook added to allow modification in offers that could cancel the primary order(#1129)
* Added: Filter hook added to allow modification in stripe refund post data (#1147)
* Fixed: Elementor widget settings were not getting saved in a few cases. (#1124)
* Fixed: Offer reject Link for the gutenberg templates was working incorrectly in few cases. (#1117)
* Fixed: Offer accept button styling breaks when price merge tag added. (#1122)
* Fixed: Google ads conversion tracking events was not getting fired correctly when used along with Google analytics. (#1126)
* Fixed: Quantity selector widget was not showing up any select when offer gets duplicated. (#1138)
* Fixed: Resolved jQuery conflict when jQuery migrate option checked in Divi theme settings. (#1143)
* Fixed: Licenses were not getting activated in multisite when funnel builder PRO activated network wide. (#1148)
* Fixed: Elementor template import was not working when setting "Improved Asset Loading" is turned ON in elementor. (#1108)
* Fixed: Offer accept/reject request failing for some server when JSON content was not getting returned. (#1150)
* Fixed: Elementor widgets alignment settings icons were missing in a few sites. (#1158)



[See changelog for all versions](https://myaccount.funnelkit.com/changelog/changelog-upstroke/).
