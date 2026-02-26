# Cart for WooCommerce Pro - Plugin Knowledge Base

> Comprehensive documentation for AI agents working with this codebase

---

## Table of Contents

1. [Plugin Overview](#1-plugin-overview)
2. [Architecture Mapping](#2-architecture-mapping)
3. [WordPress Integration](#3-wordpress-integration)
4. [Code Inventory](#4-code-inventory)
5. [Frontend Functionality](#5-frontend-functionality)
6. [Admin Functionality](#6-admin-functionality)
7. [Data Flow & Business Logic](#7-data-flow--business-logic)
8. [Third-Party Integrations](#8-third-party-integrations)
9. [Security Architecture](#9-security-architecture)
10. [Configuration & Settings](#10-configuration--settings)
11. [Extensibility & Developer APIs](#11-extensibility--developer-apis)
12. [Performance Considerations](#12-performance-considerations)
13. [Common Patterns & Conventions](#13-common-patterns--conventions)

---

## 1. Plugin Overview

### Basic Information

| Property | Value |
|----------|-------|
| **Plugin Name** | Cart For WooCommerce Pro |
| **Version** | 0.9.0 |
| **Namespace** | `FKCart\Pro` |
| **Author** | FunnelKit |
| **Plugin URI** | https://funnelkit.com/ |
| **Primary Purpose** | Premium cart features for WooCommerce |

### Distribution Model

This is **NOT a standalone plugin**. It's distributed as:
- A submodule of **Funnel Builder Pro**
- Module path: `funnel-builder-pro/modules/cart-for-woocommerce-pro`
- Requires the free FunnelKit Cart plugin as base

### Repository Information

| Component | Repository | Base Branch |
|-----------|------------|-------------|
| FunnelKit Cart (Lite) | https://github.com/xlplugins/cart-for-woocommerce | master |
| Cart for WooCommerce Pro | https://github.com/xlplugins/cart-for-woocommerce-pro | master |

### Main Functionality & Features

1. **Cart Upsells** - Product recommendations in the cart drawer
2. **Rewards System** - Progress bar with milestones (free shipping, discounts, free gifts)
3. **Special Add-On** - Single product add-on (e.g., shipping protection)
4. **Conversion Analytics** - REST API for tracking and reporting
5. **Data Export** - CSV export of cart conversions

### Version Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 5.0+ (assumed) |
| PHP | 7.4+ (uses union types in catch blocks) |
| WooCommerce | 4.0+ |
| FunnelKit Cart | Required (base plugin) |
| WFFN_Core | Required (Funnel Builder) |

### Key Dependencies

**Internal (FunnelKit Ecosystem):**
- `FKCart\Includes\Data` - Settings and data access
- `FKCart\Includes\Front` - Frontend cart functionality
- `FKCart\Includes\cart` - Cart operations
- `WFFN_Core` - FunnelKit core functionality
- `WFFN_REST_Controller` - REST API base class
- `WooFunnels_Background_Updater` - Background processing

**External:**
- WooCommerce core classes (`WC_Product`, `WC_Order`, `WC_Geolocation`, etc.)
- WordPress APIs (REST, Options, Transients)

---

## 2. Architecture Mapping

### 2.1 Directory Structure

```
cart-for-woocommerce-pro/
├── plugin.php                    # Main plugin file, entry point
├── CLAUDE.md                     # AI agent guidance
├── .gitignore                    # Git ignore rules
├── include/                      # Core PHP classes
│   ├── upsells.php              # Cart upsell functionality
│   ├── rewards.php              # Rewards system (1217 lines - largest file)
│   ├── special-add-on.php       # Special product add-on feature
│   ├── geolocation.php          # Extended WC_Geolocation class
│   ├── fkcart-db-migrator.php   # Database migration handler
│   └── fkcart-export-cart-conversion.php  # CSV export functionality
├── rest/                         # REST API endpoints
│   └── conversions.php          # Analytics and conversion endpoints
├── templates/                    # Template files
│   └── cart/
│       └── special-addon-html.php  # Special add-on template
└── docs/                         # Documentation
    └── [documentation files]
```

### 2.2 Organizational Pattern

**Pattern:** Modular with Singleton instances

The plugin follows a **feature-based modular architecture**:
- Each major feature is a self-contained class with singleton pattern
- Classes hook into WordPress/WooCommerce at construction time
- No autoloader - direct file includes
- REST API separated into dedicated directory

### 2.3 Entry Points

#### Main Plugin File (`plugin.php`)

```php
namespace FKCart\Pro;

class Plugin {
    // Singleton pattern
    private static $instance = null;

    private function __construct() {
        // Hook into FunnelKit Cart loaded event
        add_action('funnelkit_cart_loaded', [$this, 'include_core'], 15);
        add_action('wffn_pro_loaded', [$this, 'load_exporters'], 11);
    }
}

Plugin::getInstance();
```

#### Initialization Sequence

```
1. WordPress loads plugin.php
2. Plugin::getInstance() creates singleton
3. Waits for 'funnelkit_cart_loaded' action (priority 15)
4. Plugin::include_core() checks for WFFN_Core
5. Defines FKCART_PRO_PATH constant
6. Includes and initializes:
   - Upsells::getInstance()
   - Rewards::getInstance()
   - Special_Add_On::getInstance()
7. On 'rest_api_init', loads REST endpoints
8. On 'wffn_pro_loaded', registers exporters
```

#### Hook Priorities

| Hook | Priority | Callback | Purpose |
|------|----------|----------|---------|
| `funnelkit_cart_loaded` | 15 | `include_core` | Load pro features |
| `wffn_pro_loaded` | 11 | `load_exporters` | Register CSV exporters |
| `rest_api_init` | 9 | `init_rest_api` | Register REST routes |

### 2.4 Core Components

#### Plugin Class (`plugin.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | Bootstrap and license validation |
| **Singleton** | `Plugin::getInstance()` |
| **Key Methods** | `include_core()`, `valid_l()`, `get_current_app_state()` |
| **Dependencies** | WFFN_Core, WooFunnels_licenses |

#### Upsells Class (`include/upsells.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | Cart upsell product recommendations and tracking |
| **Singleton** | `Upsells::getInstance()` |
| **Namespace** | `FKCart\Pro` |
| **Lines** | ~331 |
| **Key Features** | - Get upsell products based on cart contents<br>- Track upsell views in session<br>- Store upsell metadata on orders<br>- Handle refunds and revenue tracking |

#### Rewards Class (`include/rewards.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | Progress bar rewards system |
| **Singleton** | `Rewards::getInstance()` |
| **Namespace** | `FKCart\Pro` |
| **Lines** | ~1217 (largest file) |
| **Key Features** | - Free shipping rewards<br>- Discount coupon application<br>- Free gift product management<br>- Geolocation-based shipping calculation<br>- Session state management |

#### Special_Add_On Class (`include/special-add-on.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | Single product add-on feature |
| **Singleton** | `Special_Add_On::getInstance()` |
| **Namespace** | `FKCart\Pro` |
| **Lines** | ~558 |
| **Key Features** | - Auto-add product to cart<br>- Toggle/checkbox UI<br>- Variable product support<br>- WPML/Polylang compatibility |

#### Geolocation Class (`include/geolocation.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | Extended geolocation for shipping rewards |
| **Extends** | `WC_Geolocation` |
| **Namespace** | `FKCart\Pro` |
| **Lines** | ~151 |
| **Key Features** | - IP-based location detection<br>- External API fallback (ip-api.com, ipinfo.io)<br>- Country, state, city, postcode resolution |

#### Conversions REST Controller (`rest/conversions.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | Analytics and conversion tracking API |
| **Extends** | `WFFN_REST_Controller` |
| **Namespace** | `FKCart\Pro\Rest` |
| **Lines** | ~1013 |
| **API Namespace** | `funnelkit-app` |

#### FKCART_DB_Migrator (`include/fkcart-db-migrator.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | Background data migration |
| **Extends** | `WooFunnels_Background_Updater` |
| **Namespace** | Global |
| **Lines** | ~270 |
| **Key Features** | - Batch processing<br>- Stuck process detection<br>- Order number migration |

#### FKCART_Export_Cart_Conversion (`include/fkcart-export-cart-conversion.php`)

| Property | Description |
|----------|-------------|
| **Purpose** | CSV export functionality |
| **Extends** | `WFFN_Abstract_Exporter` |
| **Namespace** | Global |
| **Lines** | ~145 |

---

## 3. WordPress Integration

### 3.1 Hooks & Filters Registry

See [HOOKS_REFERENCE.md](./HOOKS_REFERENCE.md) for complete registry.

**Summary:**

| Type | Count | Primary Usage |
|------|-------|---------------|
| Actions Added | 25+ | WooCommerce lifecycle hooks |
| Filters Added | 15+ | Price modification, cart manipulation |
| Custom Actions | 3 | `fkcart_geolocation`, etc. |
| Custom Filters | 10+ | Developer extensibility |

### 3.2 Database Schema

See [DATABASE_SCHEMA.md](./DATABASE_SCHEMA.md) for complete schema.

**Summary:**

| Table | Purpose |
|-------|---------|
| `{prefix}fk_cart` | Cart conversion tracking |
| `{prefix}fk_cart_products` | Product-level upsell/gift tracking |
| `{prefix}fk_cart_stats` | Legacy stats (dropped after migration) |

### 3.3 WordPress APIs Used

#### REST API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/funnelkit-app/fkcart-conversions/` | GET | Conversion analytics with filters |
| `/funnelkit-app/fkcart-overview/` | GET | Summary statistics |
| `/funnelkit-app/fkcart-upsell-performance/` | GET | Time-series data |
| `/funnelkit-app/fkcart-popular-upsells/` | GET | Top performing upsells |
| `/funnelkit-app/fkcart-reward-chart/` | GET | Reward distribution pie chart |
| `/funnelkit-app/fkcart-product-search/` | GET | Product search for filters |
| `/funnelkit-app/fkcart-coupon-search/` | GET | Coupon search for filters |
| `/funnelkit-app/fkcart-migrate-data/` | POST | Trigger data migration |
| `/funnelkit-app/cart-conversions/export/add` | POST | Add export to queue |

#### Options API

| Option Key | Purpose |
|------------|---------|
| `_fkcart_upgrade` | Migration state (0-4) |
| `_bwf_fkcart_offset` | Migration progress offset |
| `_bwf_fkcart_last_offsets` | Stuck detection array |
| `fkcart_order_maxid` | Migration max order ID |

#### Transients

| Transient Key | TTL | Purpose |
|---------------|-----|---------|
| `fkcart_geoip_{ip}` | 1 day | Cached geolocation data |
| `geoip_{ip}` | 1 day | WC-compatible geolocation cache |

#### WC Session Keys

| Key | Data Type | Purpose |
|-----|-----------|---------|
| `_fkcart_upsell_views` | array | Viewed upsell product IDs |
| `_fkcart_free_gift_views` | array | Viewed free gift product IDs |
| `_fkcart_discount_code_views` | array | Applied discount codes |
| `_fkcart_free_shipping_methods` | string | Active free shipping method |
| `_fkcart_applied_coupons` | array | Reward-applied coupon codes |
| `_fkcart_removed_coupons` | array | User-removed coupons |
| `_fkcart_spl_addon_product_id` | int | Special add-on product ID |
| `_fkcart_spl_addon_product_cart_key` | string | Cart key for special add-on |
| `_fkcart_remove_addons` | string | Flag to prevent auto-add |

---

## 4. Code Inventory

### 4.1 Classes

#### FKCart\Pro\Plugin

```
Class: Plugin
File: plugin.php
Purpose: Main plugin bootstrap and license validation
Dependencies: WFFN_Core, WooFunnels_licenses

Methods:
  - __construct(): private, sets up hooks
  - getInstance(): static, returns singleton instance
  - include_core(): loads feature classes on funnelkit_cart_loaded
  - load_exporters(): registers CSV exporters
  - init_rest_api(): includes REST endpoint file
  - valid_l(): static, validates license status, returns bool
  - get_current_app_state(): static, returns license state string

Key Properties:
  - $instance: static, singleton instance

Hooks Added:
  - add_action('funnelkit_cart_loaded', [$this, 'include_core'], 15)
  - add_action('wffn_pro_loaded', [$this, 'load_exporters'], 11)
  - add_action('rest_api_init', [$this, 'init_rest_api'], 9)
```

#### FKCart\Pro\Upsells

```
Class: Upsells
File: include/upsells.php
Purpose: Cart upsell product recommendations and order tracking
Dependencies: FKCart\Includes\Data, FKCart\Includes\Front, Plugin

Methods:
  - __construct(): private, hooks into WC order events
  - getInstance(): static, returns singleton
  - woocommerce_create_order_line_item($item, $cart_item_key, $values): adds meta to order items
  - update_reward_data_in_order($order): saves reward view data to order
  - partially_refunded_process($order_id, $refund_id): handles partial refunds
  - fully_refunded_process($order_id): handles full refunds/deletions
  - update_refund_price($product_id, $order_id, $refund_amount, $type): updates DB on refund
  - get_upsell_products(): public, returns array of upsell product data
  - get_upsell_ids(): public, returns array of upsell product IDs
  - get_recommendation_type(): returns 'upsell', 'cross_sell', or 'both'
  - get_default_upsells(): returns default upsell product IDs
  - update_upsell_view($upsells): stores viewed upsells in session
  - get_upsell_views(): returns viewed upsell IDs from session

Hooks Added:
  - woocommerce_checkout_create_order_line_item (priority 999999)
  - woocommerce_order_fully_refunded
  - woocommerce_order_partially_refunded (priority 10, 2 args)
  - woocommerce_checkout_create_order
  - woocommerce_delete_order
```

#### FKCart\Pro\Rewards

```
Class: Rewards
File: include/rewards.php
Purpose: Progress bar rewards system (free shipping, discounts, free gifts)
Dependencies: FKCart\Includes\Data, FKCart\Includes\Front, FKCart\Compatibilities\Compatibility, Plugin

Methods:
  - __construct(): private, extensive hook setup
  - getInstance(): static, returns singleton
  - update_free_gift($cart): modifies cart to set free gift prices to 0
  - set_price_to_zero_for_free_gift($cart_items): protected, modifies cart item
  - handle_reward_free_product($price, $product): filter callback for product price
  - do_not_allow_delete_free_gift($link, $cart_item_key): removes delete link for free gifts
  - maybe_remove_free_gifts(): removes free gifts if cart only has gifts
  - update_reward(): main reward processing on woocommerce_calculate_totals
  - process_coupons($remove, $add, $removed): private, applies/removes coupons
  - process_gift_products($add, $remove): private, manages free gift products
  - handle_free_shipping($free_shipping): private, sets shipping method
  - get_rewards($raw_data = false): static, returns processed rewards array
  - is_free_shipping_reward_available(): static, checks if free shipping configured
  - get_shipping_min_amount(): static, gets min amount for free shipping
  - get_cart_total(): static, returns cart total based on calculation mode
  - map_variation_attributes($variation_attr, $product_attr): static, handles Any-Any variations
  - update_choosen_shipping_method(): sets shipping method on page load
  - pass_customer_geo_data($geolocation): filter, passes billing address to geolocation
  - set_geolocation_data_to_customer($geolocation): sets geolocation to WC customer
  - [+ many more checkout field methods]

Key Properties:
  - $meta_data: array, cached shipping zone data
  - $default_wc_location: string, WC default customer address setting

Hooks Added (partial list):
  - wp (priority 22, and default)
  - woocommerce_cart_loaded_from_session (priority 98)
  - woocommerce_before_calculate_totals (priority 98, 90)
  - woocommerce_calculate_totals (priority 99)
  - woocommerce_cart_item_remove_link (priority 10, 2 args)
  - woocommerce_removed_coupon
  - woocommerce_cart_emptied
  - woocommerce_checkout_fields
  - woocommerce_checkout_get_value
  - [and more...]
```

#### FKCart\Pro\Special_Add_On

```
Class: Special_Add_On
File: include/special-add-on.php
Purpose: Single product add-on feature (e.g., shipping protection)
Dependencies: FKCart\Includes\Data, FKCart\Includes\cart, Plugin

Methods:
  - __construct(): private, sets up hooks
  - getInstance(): static, returns singleton
  - get_settings(): static, returns plugin settings
  - handle_special_addon_product($cart_id, $product_id): auto-adds product on add_to_cart
  - handle_variable_product($product): static, adds variable product variation
  - special_product_addon($data = []): static, handles toggle on/off via AJAX
  - unset_special_addon_product(): clears session on cart empty
  - internal_style(): outputs CSS in footer
  - add_special_addon_css_variables($var_style): filter, adds CSS variables
  - special_addon_html($cart_settings): outputs template
  - check_special_addon_exist_in_cart($id): static, checks if product in cart
  - get_map_product($product_id): static, WPML/Polylang product ID mapping

Hooks Added:
  - woocommerce_add_to_cart (priority 9999)
  - woocommerce_cart_emptied
  - wp_footer
  - admin_footer
  - fkcart_css_var_style (filter)
  - fkcart_after_coupon_section
```

#### FKCart\Pro\Geolocation

```
Class: Geolocation
File: include/geolocation.php
Purpose: Extended geolocation for shipping zone detection
Extends: WC_Geolocation

Methods:
  - geolocate_ip($ip_address = '', $fallback = false, $api_fallback = true): static, returns geo array
  - geolocate_via_api($ip_address): private static, fetches from external APIs

External APIs Used:
  - ip-api.com: http://ip-api.com/json/{ip}
  - ipinfo.io: https://ipinfo.io/{ip}/json
```

#### FKCart\Pro\Rest\Conversions

```
Class: Conversions
File: rest/conversions.php
Purpose: REST API for conversion analytics
Extends: WFFN_REST_Controller
Namespace: FKCart\Pro\Rest

Methods:
  - __construct(): private, registers endpoints
  - get_instance(): static, returns singleton
  - register_contact_data_endpoint(): calls order_end_points()
  - order_end_points(): private, registers all REST routes
  - get_read_api_permission_check(): permission callback for read
  - get_write_api_permission_check(): permission callback for write
  - column_exists($column_name): private, checks if DB column exists
  - migrator_run(): POST handler for migration trigger
  - prepare_filters($filters): processes filter array
  - filters_list($args, $optin = false): returns filter configuration
  - get_product_names($ids): converts product IDs to names
  - get_cart_upsell_data($request, $return_data = false): main conversion data endpoint
  - custom_product_search_api($request): product search for filters
  - discount_search_api($request): coupon search for filters
  - generate_pie_chart_data($request): reward distribution data
  - get_most_popular_upsells($request): top performing upsells
  - get_cart_upsell_overview($request, $is_data = false, $is_interval = false): overview stats
  - get_cart_upsell_performance($request): time-series performance
  - add_cart_conversions_export($request): queues CSV export
  - delete_orders_by_oid($order_ids_csv): deletes conversion records

Key Properties:
  - $namespace: 'funnelkit-app'
  - $args: array, current request arguments
```

#### FKCART_DB_Migrator

```
Class: FKCART_DB_Migrator
File: include/fkcart-db-migrator.php
Purpose: Background data migration for order numbers
Extends: WooFunnels_Background_Updater
Namespace: Global

Methods:
  - __construct(): sets up migration action hook
  - get_instance(): static, returns singleton
  - maybe_re_dispatch_background_process(): checks and restarts stuck processes
  - get_action(): returns action name
  - kill_process(): stops processing
  - get_last_offsets(): returns last 5 offsets for stuck detection
  - manage_last_offsets(): tracks offset history
  - update_last_offsets($offsets): saves offset array
  - complete(): protected, runs on completion (drops legacy table)
  - get_upgrade_state(): returns migration state (0-4)
  - set_upgrade_state($state): sets migration state
  - db_migrator(): main migration logic, processes 100 records per batch
  - insert_data($data, $table_name): bulk insert helper
  - update_data($data, $table_name): bulk update helper

Constants:
  - MAX_SAME_OFFSET_THRESHOLD: 5 (stuck detection threshold)

Key Properties:
  - $prefix: 'bwf_fkcart_1'
  - $action: 'migrator'
```

#### FKCART_Export_Cart_Conversion

```
Class: FKCART_Export_Cart_Conversion
File: include/fkcart-export-cart-conversion.php
Purpose: CSV export for cart conversions
Extends: WFFN_Abstract_Exporter
Namespace: Global

Methods:
  - __construct(): calls parent constructor
  - get_instance(): static, returns singleton
  - get_slug(): static, returns 'cart_conversion'
  - get_title(): returns 'Cart'
  - action_hook(): returns 'bwf_funnel_cart_conversion'
  - get_columns(): returns export column definitions
  - total_rows($args): counts total exportable rows
  - export_data(): main export logic, batched
  - data_populated_in_csv($funnel_id, $data): writes data to CSV file

Export Columns:
  - order_id, order_number, cart_upsell, upsell_revenue
  - special_addon, special_addon_revenue, free_shipping_orders
  - free_gift, discount, date
```

### 4.2 Functions

#### fkcart_db_migrator()

```
Function: fkcart_db_migrator()
File: include/fkcart-db-migrator.php
Purpose: Returns singleton instance of FKCART_DB_Migrator
Parameters: None
Returns: FKCART_DB_Migrator instance
Called by: REST endpoint, background processing
```

#### fkcart_run_db_migrator()

```
Function: fkcart_run_db_migrator()
File: include/fkcart-db-migrator.php
Purpose: Executes the migration batch
Parameters: None
Returns: bool - success status
Called by: Background updater action hook
```

### 4.3 Templates

#### special-addon-html.php

```
Template: templates/cart/special-addon-html.php
Purpose: Renders special add-on product in cart drawer
Hook Location: fkcart_after_coupon_section

Variables Available:
  - $front: FKCart\Includes\Front instance
  - $settings: Special add-on settings array
  - $special_addon_product_id: Product ID
  - $product_obj: WC_Product instance
  - $special_addon_product_price: Price HTML
  - $special_addon_product_is_variable: bool
  - $preselect_special_addon: bool
  - $special_addon_heading: string
  - $special_addon_desc: string
  - $enable_special_addon_image: bool
  - $special_addon_image_type: 'product' or 'custom'
  - $special_addon_custom_image: URL
  - $special_addon_image_size: int (pixels)
  - $special_addon_selection_type: 'toggle' or 'checkbox'
  - $fkspl_cart_item_key: Cart item key if in cart
  - $variable_meta: Variation attributes HTML

CSS Classes Applied:
  - fkcart-spl-addons-wrap
  - fkcart-spl-addons-vaiation-product (if variable)
  - fkcart-spl-addon-preselect (if preselected)
  - fkcart-image-disabled (if image disabled)
  - fkcart-toggle-selected / fkcart-checkbox-selected
```

---

## 5. Frontend Functionality

### 5.1 User-Facing Features

#### Cart Upsells
- Displayed in cart drawer below cart items
- Products sourced from WC upsells/cross-sells or default upsells
- Maximum count configurable via `upsell_max_count` setting
- Click adds product to cart with `_fkcart_upsell` meta

#### Rewards Progress Bar
- Shows progress toward milestones
- Types: Free shipping, Discount coupon, Free gift
- Auto-applies/removes rewards based on cart total
- Calculation based on subtotal or total (configurable)

#### Special Add-On
- Toggle or checkbox UI
- Can be pre-selected (auto-added on first cart add)
- Supports variable products
- "Learn More" expandable text

### 5.2 Session State Management

All frontend state stored in WC session:

```php
// Upsell views tracking
WC()->session->set('_fkcart_upsell_views', $product_ids);

// Free gift views
WC()->session->set('_fkcart_free_gift_views', $gift_ids);

// Discount code views
WC()->session->set('_fkcart_discount_code_views', $coupon_codes);

// Free shipping method
WC()->session->set('_fkcart_free_shipping_methods', $method_id);

// Applied reward coupons
WC()->session->set('_fkcart_applied_coupons', $coupon_codes);

// Removed coupons (prevents re-add)
WC()->session->set('_fkcart_removed_coupons', $removed);

// Special add-on state
WC()->session->set('_fkcart_spl_addon_product_id', $product_id);
WC()->session->set('_fkcart_spl_addon_product_cart_key', $cart_key);
WC()->session->set('_fkcart_remove_addons', 'yes'); // Prevents auto-add
```

### 5.3 CSS Variables

Special add-on styling via CSS variables:

```css
:root {
    --fkcart-spl-addon-special-addon-image-width: {size}px;
    --fkcart-spl-addon-special-addon-image-height: {size}px;
    --fkcart-spl-addon-toggle-color: {color};
    --fkcart-spl-addon-bg-color: {color};
    --fkcart-spl-addon-heading-color: {color};
    --fkcart-spl-addon-description-color: {color};
}
```

---

## 6. Admin Functionality

### 6.1 REST API for Admin UI

All admin functionality delivered via REST API endpoints consumed by React UI in FunnelKit admin:

| Endpoint | Purpose |
|----------|---------|
| `fkcart-conversions` | Paginated conversion list with filters |
| `fkcart-overview` | Dashboard summary cards |
| `fkcart-upsell-performance` | Performance chart data |
| `fkcart-popular-upsells` | Top upsells table |
| `fkcart-reward-chart` | Pie chart for reward distribution |

### 6.2 Filter Configuration

Filters returned by `filters_list()`:

```php
[
    'period' => [
        'type' => 'date-range',
        'title' => 'Date Created'
    ],
    'cart_upsell' => [
        'type' => 'search',
        'operators' => ['accepted', 'rejected'],
        'api' => '/fkcart-product-search'
    ],
    'special_addon' => [
        'type' => 'search',
        'operators' => ['accepted', 'rejected']
    ],
    'rewards' => [
        'type' => 'select',
        'operators' => ['free_shipping', 'discount', 'free_gift'],
        'options' => ['yes', 'no']
    ]
]
```

### 6.3 Permission Checks

```php
// Read permission
public function get_read_api_permission_check() {
    return wffn_rest_api_helpers()->get_api_permission_check('analytics', 'read');
}

// Write permission
public function get_write_api_permission_check() {
    return wffn_rest_api_helpers()->get_api_permission_check('analytics', 'write');
}

// Fallback for missing helper
if (!function_exists('wffn_rest_api_helpers')) {
    return current_user_can('administrator');
}
```

---

## 7. Data Flow & Business Logic

### 7.1 Critical User Flows

#### Upsell Purchase Flow

```
1. User opens cart drawer
2. get_upsell_products() called
   └─ Validates license (Plugin::valid_l())
   └─ Gets cart items
   └─ Collects upsell/cross-sell IDs from products
   └─ Adds default upsells if configured
   └─ Filters out products already in cart
   └─ Limits to max_upsell count
   └─ Updates session with viewed upsells
3. User clicks "Add" on upsell
4. Product added with _fkcart_upsell cart item data
5. On checkout, woocommerce_checkout_create_order_line_item fires
   └─ Adds _fkcart_upsell meta to order item
6. On order creation, update_reward_data_in_order fires
   └─ Stores upsell views in order meta
7. Data written to fk_cart_products table (type=1)
```

#### Rewards Flow

```
1. woocommerce_calculate_totals hook fires (priority 99)
2. update_reward() called
   └─ get_rewards() processes reward configuration
   └─ Calculates cart total (subtotal or total based on setting)
   └─ Determines which milestones are achieved
3. For each achieved milestone:
   └─ Discount: Applies coupon via WC()->cart->add_discount()
   └─ Free Gift: Adds product with _fkcart_free_gift meta
   └─ Free Shipping: Sets session key for shipping method
4. For unachieved milestones:
   └─ Removes previously applied coupons/gifts
5. Session updated with applied coupons list
6. Notices cleared/restored to prevent duplicate messages
```

#### Free Shipping Detection Flow

```
1. is_free_shipping_reward_available() checks if configured
2. get_shipping_min_amount() called
   └─ Gets customer IP via Geolocation::get_ip_address()
   └─ geolocate_ip() determines country/state/postcode
   └─ Matches to WC shipping zone
   └─ Finds free shipping method in zone
   └─ Returns min_amount and method_id
3. If achieved, sets _fkcart_free_shipping_methods in session
4. Disables "hide until address" setting if free shipping earned
```

### 7.2 Data Processing

#### Input Validation

REST endpoint filters:
```php
$filters['search'] = sanitize_text_field($request['s']);
$filters['limit'] = intval($request['limit']);
$date_after = sanitize_text_field($filter['data']['after']);
$product_id = absint($_POST['fkcart_spl_product_id']);
```

#### Database Query Preparation

All queries use `$wpdb->prepare()`:
```php
$wpdb->prepare(
    "SELECT id, price FROM {$wpdb->prefix}fk_cart_products WHERE type = %d AND product_id = %d AND oid = %d",
    $type, $product_id, $order_id
);
```

#### Output Escaping

Template output:
```php
echo esc_attr(implode(' ', $base_class));
echo esc_url(get_the_permalink($product_id));
```

---

## 8. Third-Party Integrations

### 8.1 External APIs

#### Geolocation Services

| Service | Endpoint | Data Returned |
|---------|----------|---------------|
| ip-api.com | `http://ip-api.com/json/{ip}` | country, region, zip, city |
| ipinfo.io | `https://ipinfo.io/{ip}/json` | country, postal, city |

Request configuration:
```php
wp_safe_remote_get($endpoint, [
    'timeout' => 2,
    'user-agent' => 'WooCommerce/' . wc()->version
]);
```

### 8.2 Plugin Interactions

#### Required Plugins

| Plugin | Usage |
|--------|-------|
| WooCommerce | Core e-commerce functionality |
| FunnelKit Cart (Lite) | Base cart functionality |
| FunnelKit Funnel Builder | WFFN_Core, licensing |
| FunnelKit Funnel Builder Pro | WFFN_Pro_Core, exporters |

#### Compatibility Integrations

| Plugin | Integration |
|--------|-------------|
| WPML | Product ID mapping via `Compatibility::get_compatibility_class('wpml')` |
| Polylang | Product ID mapping via `Compatibility::get_compatibility_class('poly_lang')` |
| WooCommerce Subscriptions | Allowed product types include `subscription`, `variable-subscription` |
| WFACP (Aero Checkout) | Delete icon disabled for free gifts |
| WFOCU (One Click Upsell) | Removed from coupon apply action during reward processing |
| WFOB (Order Bumps) | `re_run_rules_after_bump_removed()` called after gift added |

---

## 9. Security Architecture

See [SECURITY_CHECKLIST.md](./SECURITY_CHECKLIST.md) for complete security requirements.

### 9.1 Security Measures

#### Permission Checks

```php
// REST API permissions
wffn_rest_api_helpers()->get_api_permission_check('analytics', 'read');
wffn_rest_api_helpers()->get_api_permission_check('analytics', 'write');

// Fallback check
current_user_can('administrator');
```

#### Data Sanitization

```php
sanitize_text_field()   // String inputs
absint()                // Integer IDs
intval()                // Numeric values
wc_clean()              // WC-specific cleaning
$wpdb->esc_like()       // LIKE query escaping
```

#### SQL Injection Prevention

All database queries use prepared statements:
```php
$wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id);
```

### 9.2 Sensitive Operations

#### File Operations

CSV export writes to:
```php
WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file']
```

#### External API Calls

Limited to geolocation services with 2-second timeout.

---

## 10. Configuration & Settings

### 10.1 Constants

| Constant | Defined In | Purpose |
|----------|-----------|---------|
| `FKCART_PRO_PATH` | plugin.php | Plugin directory path |

### 10.2 Settings (via FKCart\Includes\Data)

| Setting Key | Type | Purpose |
|-------------|------|---------|
| `enable_cart` | bool | Master cart enable |
| `upsell_type` | string | 'upsell', 'cross_sell', 'both' |
| `upsell_max_count` | int | Max upsells to show |
| `default_upsell` | array | Default upsell products |
| `show_default_upsell` | bool | Always show defaults |
| `reward` | array | Reward configuration |
| `reward_title` | string | Completed rewards title |
| `reward_calculation_based` | string | 'subtotal' or 'total' |
| `enable_special_addon` | bool | Enable special add-on |
| `preselect_special_addon` | bool | Auto-add special add-on |
| `special_addon_product` | array | Product configuration |
| `special_addon_heading` | string | Display heading |
| `special_addon_desc` | string | Description text |
| `enable_special_addon_image` | bool | Show product image |
| `special_addon_image_type` | string | 'product' or 'custom' |
| `special_addon_custom_image` | string | Custom image URL |
| `special_addon_image_size` | int | Image size in pixels |
| `special_addon_selection_type` | string | 'toggle' or 'checkbox' |
| `special_addon_toggle_color` | string | Toggle active color |
| `special_addon_bg_color` | string | Background color |
| `special_addon_heading_color` | string | Heading text color |

---

## 11. Extensibility & Developer APIs

See [API_REFERENCE.md](./API_REFERENCE.md) for complete API documentation.

### 11.1 Custom Hooks for Developers

#### Filters

```php
// Modify upsell product list
apply_filters('fkcart_default_upsells', $product_ids);

// Modify rewards calculation
apply_filters('fkcart_reward_calculation_based_on', $calculation_mode);
apply_filters('fkcart_reward_total', $total, $calculation_mode, $front);
apply_filters('fkcart_rewards_list', $rewards_data);
apply_filters('fkcart_reward_rules_checking', false, $reward);

// Modify gift products
apply_filters('fkcart_gift_products', $gifts, $rewards);

// Free shipping
apply_filters('fkcart_free_shipping', $shipping_data);
apply_filters('fkcart_need_to_set_free_shipping_method', true);

// Geolocation
apply_filters('fkcart_allow_wc_geolocate_filters', false);
apply_filters('fkcart_woocommerce_geolocate_ip', $geolocation, $ip_address);
apply_filters('fkcart_set_geolocation_data_to_customer', true);

// Product types
apply_filters('fkcart_allow_product_types', $allowed_types);
```

#### Actions

```php
// Before/after special add-on cart operation
do_action('fkcart_spl_addon_before_add_to_cart', $_POST);

// Geolocation completed
do_action('fkcart_geolocation', $country_data, $ip_address);
```

### 11.2 Public Methods

#### Upsells::get_upsell_products()

Returns array of upsell product data for display.

#### Rewards::get_rewards($raw_data = false)

Returns processed rewards array with achievement status.

#### Rewards::get_cart_total()

Returns cart total for reward calculations.

#### Rewards::is_free_shipping_reward_available()

Checks if free shipping reward is configured.

---

## 12. Performance Considerations

### 12.1 Caching Strategies

#### Transient Caching

```php
// Geolocation cached for 1 day
set_transient('fkcart_geoip_' . $ip_address, $data, DAY_IN_SECONDS);
get_transient('fkcart_geoip_' . $ip_address);
```

#### Object Caching

```php
// Shipping zone cached in WP object cache
wp_cache_get($zone_cache_key, 'fkcart_shipping_zones');
wp_cache_set($zone_cache_key, $matched_zone_key, 'fkcart_shipping_zones');
```

#### Instance Caching

```php
// Shipping data cached in class property
self::getInstance()->meta_data[$zone_cache_key] = $free_shipping;
```

### 12.2 Database Optimization

#### Batched Processing

Migration processes 100 records per batch:
```php
$per_page = 100;
$entries = $wpdb->get_results($wpdb->prepare(
    "SELECT oid FROM {$wpdb->prefix}fk_cart WHERE id <= %d LIMIT %d OFFSET %d",
    $max_id, $per_page, $offset
));
```

#### Indexed Queries

Primary queries use indexed columns (`oid`, `type`, `product_id`).

### 12.3 Known Performance Patterns

- Rewards calculation runs on every `woocommerce_calculate_totals`
- Geolocation API has 2-second timeout
- Background migration prevents blocking during upgrade

---

## 13. Common Patterns & Conventions

### 13.1 Naming Conventions

| Type | Convention | Example |
|------|------------|---------|
| Classes | PascalCase | `Special_Add_On` |
| Methods | camelCase | `getInstance()` |
| Functions | snake_case | `fkcart_db_migrator()` |
| Hooks | snake_case with prefix | `fkcart_reward_total` |
| Session keys | underscore prefix | `_fkcart_upsell_views` |
| DB columns | snake_case | `free_shipping` |
| Option keys | underscore prefix | `_fkcart_upgrade` |

### 13.2 Singleton Pattern

All major classes use singleton:

```php
class ClassName {
    private static $instance = null;

    private function __construct() {
        // Setup hooks
    }

    public static function getInstance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### 13.3 Error Handling

Exceptions caught silently in most cases:

```php
try {
    // Operation
} catch (\Exception|\Error $e) {
    // Silent catch - no logging
}
```

Some operations log errors:
```php
if (!empty($wpdb->last_error)) {
    WFFN_Core()->logger->log('Error: ' . $wpdb->last_error, 'fkcart_migration', true);
}
```

### 13.4 License Validation Pattern

```php
if (Plugin::valid_l() === false) {
    return []; // or early return
}
```

### 13.5 WC Availability Checks

```php
if (is_null(WC()->cart) || is_null(WC()->session)) {
    return;
}
```

### 13.6 Product Type Handling

```php
if (fkcart_is_variation_product_type($product->get_type())) {
    // Handle variation
} elseif (fkcart_is_variable_product_type($product->get_type())) {
    // Handle variable
} else {
    // Handle simple
}
```

---

## Quick Reference

### File → Feature Mapping

| Feature | Primary File |
|---------|--------------|
| Upsells | `include/upsells.php` |
| Rewards | `include/rewards.php` |
| Free Shipping | `include/rewards.php`, `include/geolocation.php` |
| Special Add-On | `include/special-add-on.php`, `templates/cart/special-addon-html.php` |
| Analytics API | `rest/conversions.php` |
| CSV Export | `include/fkcart-export-cart-conversion.php` |
| Data Migration | `include/fkcart-db-migrator.php` |
| License Check | `plugin.php` |

### Session Key Reference

| Key | Feature |
|-----|---------|
| `_fkcart_upsell_views` | Upsells |
| `_fkcart_free_gift_views` | Rewards |
| `_fkcart_discount_code_views` | Rewards |
| `_fkcart_free_shipping_methods` | Rewards |
| `_fkcart_applied_coupons` | Rewards |
| `_fkcart_removed_coupons` | Rewards |
| `_fkcart_spl_addon_product_id` | Special Add-On |
| `_fkcart_spl_addon_product_cart_key` | Special Add-On |
| `_fkcart_remove_addons` | Special Add-On |

### Database Product Types

| Type | Meaning |
|------|---------|
| 1 | Upsell |
| 2 | Free Gift |
| 3 | Special Add-On |
