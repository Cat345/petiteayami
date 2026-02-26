# Dependencies Map

> What depends on what in Cart for WooCommerce Pro

---

## Table of Contents

1. [External Dependencies](#1-external-dependencies)
2. [Internal Dependencies](#2-internal-dependencies)
3. [Class Dependencies](#3-class-dependencies)
4. [Hook Dependencies](#4-hook-dependencies)
5. [Data Dependencies](#5-data-dependencies)
6. [Dependency Graphs](#6-dependency-graphs)

---

## 1. External Dependencies

### Required Plugins

| Plugin | Namespace/Class | Purpose | Required Version |
|--------|-----------------|---------|------------------|
| WooCommerce | `WC()`, `WC_*` classes | Core e-commerce | 4.0+ |
| FunnelKit Cart (Lite) | `FKCart\Includes\*` | Base cart functionality | Latest |
| FunnelKit Funnel Builder | `WFFN_Core` | Core framework, licensing | 3.0+ |
| FunnelKit Funnel Builder Pro | `WFFN_Pro_Core` | Export functionality | Latest |

### Plugin Detection

```php
// FunnelKit Cart loaded check
add_action('funnelkit_cart_loaded', [$this, 'include_core'], 15);

// WFFN_Core availability check
if (!class_exists('WFFN_Core')) {
    return; // Don't load pro features
}

// WFFN Pro Core for exports
if (class_exists('WFFN_Pro_Core')) {
    WFFN_Pro_Core()->exporter->register($exporter);
}
```

---

### WooCommerce Classes Used

| Class | Usage Location | Purpose |
|-------|----------------|---------|
| `WC_Product` | upsells.php, rewards.php, special-add-on.php | Product operations |
| `WC_Product_Variable` | rewards.php, special-add-on.php | Variable product handling |
| `WC_Product_Variation` | rewards.php, conversions.php | Variation handling |
| `WC_Order` | upsells.php | Order operations |
| `WC_Order_Item` | upsells.php | Order item meta |
| `WC_Order_Refund` | upsells.php | Refund processing |
| `WC_Geolocation` | geolocation.php | IP geolocation (extended) |
| `WC_Shipping_Zone` | rewards.php | Shipping zone detection |
| `WC_Customer` | rewards.php | Customer address data |
| `WC_Cache_Helper` | rewards.php | Cache key generation |
| `WC_Coupon` | rewards.php (indirect) | Coupon operations |

---

### FunnelKit Cart (Lite) Classes

| Class | Usage | Purpose |
|-------|-------|---------|
| `FKCart\Includes\Data` | All files | Settings and data access |
| `FKCart\Includes\Front` | upsells.php, rewards.php | Frontend cart operations |
| `FKCart\Includes\cart` | special-add-on.php | Cart operations |
| `FKCart\Compatibilities\Compatibility` | rewards.php, special-add-on.php | Third-party compatibility |

**Key Methods Used:**

```php
// Data class
Data::get_db_settings()      // All settings
Data::get_value($key)        // Single setting
Data::get_settings()         // Formatted settings
Data::is_rewards_enabled()   // Feature check

// Front class
Front::get_instance()
Front::get_items()           // Cart items
Front::get_preview_item()    // Product display data
Front::get_subtotal_row()    // Subtotal value
Front::get_total_row()       // Total value

// Compatibility class
Compatibility::get_fixed_currency_price()   // Multi-currency
Compatibility::get_free_shipping()          // Shipping method data
Compatibility::get_compatibility_class()    // Plugin-specific handler
```

---

### FunnelKit Core Classes

| Class | Usage | Purpose |
|-------|-------|---------|
| `WFFN_Core` | plugin.php | Core framework access |
| `WFFN_REST_Controller` | conversions.php | REST API base class |
| `WooFunnels_Background_Updater` | fkcart-db-migrator.php | Background processing |
| `WooFunnels_licenses` | plugin.php | License validation |
| `WFFN_Abstract_Exporter` | fkcart-export-cart-conversion.php | Export base class |
| `WFFN_Common` | conversions.php | Order URL generation |

---

### WordPress APIs

| API | Usage | Purpose |
|-----|-------|---------|
| Options API | All files | Settings storage |
| Transients API | geolocation.php | Geolocation caching |
| REST API | conversions.php | Admin endpoints |
| Object Cache | rewards.php | Zone caching |
| HTTP API | geolocation.php | External API calls |

---

## 2. Internal Dependencies

### File Load Order

```
1. plugin.php (entry point)
   ├── Waits for: funnelkit_cart_loaded
   │
2. include/upsells.php
   ├── Requires: FKCart\Includes\Data
   ├── Requires: FKCart\Includes\Front
   │
3. include/rewards.php
   ├── Requires: FKCart\Includes\Data
   ├── Requires: FKCart\Includes\Front
   ├── Requires: FKCart\Compatibilities\Compatibility
   ├── Includes: include/geolocation.php (on demand)
   │
4. include/special-add-on.php
   ├── Requires: FKCart\Includes\Data
   ├── Requires: FKCart\Includes\cart
   ├── Uses: templates/cart/special-addon-html.php
   │
5. include/fkcart-db-migrator.php
   ├── Extends: WooFunnels_Background_Updater
   │
6. rest/conversions.php (loaded on rest_api_init)
   ├── Extends: WFFN_REST_Controller
   │
7. include/fkcart-export-cart-conversion.php (loaded on wffn_pro_loaded)
   ├── Extends: WFFN_Abstract_Exporter
   ├── Uses: rest/conversions.php for data
```

---

### Cross-Class Dependencies

| Class | Depends On | How |
|-------|------------|-----|
| `Plugin` | `WFFN_Core` | License validation |
| `Upsells` | `Plugin` | License check |
| `Upsells` | `Rewards` | Get free gift views |
| `Rewards` | `Plugin` | License check |
| `Rewards` | `Geolocation` | Shipping zone detection |
| `Special_Add_On` | `Plugin` | License check |
| `Special_Add_On` | `Compatibility` | WPML/Polylang mapping |
| `Conversions` | `FKCART_DB_Migrator` | Migration status |
| `FKCART_Export` | `Conversions` | Data retrieval |

---

## 3. Class Dependencies

### Plugin Class

```
Plugin
├── WFFN_Core (license config)
├── WooFunnels_licenses (license validation)
├── Upsells (instantiates)
├── Rewards (instantiates)
└── Special_Add_On (instantiates)
```

**Dependency Injection Points:**
- None (uses static access and singletons)

---

### Upsells Class

```
Upsells
├── FKCart\Includes\Data
│   ├── get_db_settings()
│   └── get_value()
├── FKCart\Includes\Front
│   ├── get_instance()
│   ├── get_items()
│   └── get_preview_item()
├── Plugin
│   └── valid_l()
├── Rewards
│   └── getInstance()->get_free_gift_views()
├── WC_Order
├── WC_Order_Item
├── WC_Order_Refund
└── WC_Product
```

---

### Rewards Class

```
Rewards
├── FKCart\Includes\Data
│   ├── get_db_settings()
│   ├── get_value()
│   └── is_rewards_enabled()
├── FKCart\Includes\Front
│   ├── get_instance()
│   ├── get_subtotal_row()
│   └── get_total_row()
├── FKCart\Compatibilities\Compatibility
│   ├── get_fixed_currency_price()
│   └── get_free_shipping()
├── Plugin
│   └── valid_l()
├── Geolocation
│   ├── geolocate_ip()
│   └── get_ip_address()
├── WC_Geolocation
├── WC_Shipping_Zone
├── WC_Customer
├── WC_Product
├── WC_Product_Variable
├── WC_Product_Variation
├── WC_Cache_Helper
├── WFOCU_Core (optional)
└── WFOB_Public (optional)
```

---

### Special_Add_On Class

```
Special_Add_On
├── FKCart\Includes\Data
│   ├── get_settings()
│   ├── get_value()
│   └── get_variation_product_type()
├── FKCart\Includes\cart
│   └── update_addon_views()
├── FKCart\Compatibilities\Compatibility
│   └── get_compatibility_class()
├── Plugin
│   └── valid_l()
├── WC_Product
└── WC_Product_Variable
```

---

### Conversions Class

```
Conversions
├── WFFN_REST_Controller (extends)
├── WFFN_Common
│   └── add_order_urls()
├── FKCART_DB_Migrator
│   └── get_upgrade_state()
├── wffn_rest_api_helpers()
├── WC_Product
└── WC_Product_Variation
```

---

### FKCART_DB_Migrator Class

```
FKCART_DB_Migrator
├── WooFunnels_Background_Updater (extends)
├── WFFN_Core
│   └── logger
└── wc_get_order()
```

---

### FKCART_Export_Cart_Conversion Class

```
FKCART_Export_Cart_Conversion
├── WFFN_Abstract_Exporter (extends)
├── Conversions
│   └── get_cart_upsell_data()
├── WFFN_Core
│   └── logger
└── WFFN_Pro_Core
    └── exporter->register()
```

---

## 4. Hook Dependencies

### Initialization Hooks

```
plugins_loaded
└── FunnelKit Cart loads
    └── funnelkit_cart_loaded (priority 15)
        └── Plugin::include_core()
            ├── Upsells::getInstance()
            ├── Rewards::getInstance()
            └── Special_Add_On::getInstance()

init
└── rest_api_init (priority 9)
    └── Plugin::init_rest_api()
        └── Conversions registered

wffn_pro_loaded (priority 11)
└── Plugin::load_exporters()
    └── FKCART_Export registered
```

---

### Runtime Hook Chain

**Cart Calculation:**
```
woocommerce_cart_loaded_from_session (98)
└── Rewards::update_free_gift()

woocommerce_before_calculate_totals (90)
└── Anonymous: Add price filters

woocommerce_before_calculate_totals (98)
└── Rewards::update_free_gift()

woocommerce_calculate_totals (99)
└── Rewards::update_reward()
    ├── Applies coupons
    ├── Adds/removes gifts
    └── Sets shipping
```

**Order Creation:**
```
woocommerce_checkout_create_order
└── Upsells::update_reward_data_in_order()

woocommerce_checkout_create_order_line_item (999999)
└── Upsells::woocommerce_create_order_line_item()
```

---

## 5. Data Dependencies

### Session Data Flow

```
Cart Add
├── Special Add-On checks preselect
│   └── Sets: _fkcart_spl_addon_product_id
│   └── Sets: _fkcart_spl_addon_product_cart_key
│
Calculate Totals
├── Rewards::update_reward()
│   ├── Sets: _fkcart_applied_coupons
│   ├── Sets: _fkcart_free_shipping_methods
│   └── Sets: _fkcart_free_gift_views
│
├── Upsells::update_upsell_view()
│   └── Sets: _fkcart_upsell_views
│
Coupon Removed (user action)
├── Rewards::stored_removed_coupon()
│   └── Sets: _fkcart_removed_coupons
│
Cart Emptied
├── Rewards::unset_removed_coupon()
│   ├── Unsets: _fkcart_removed_coupons
│   └── Unsets: _fkcart_applied_coupons
│
└── Special_Add_On::unset_special_addon_product()
    ├── Unsets: _fkcart_spl_addon_product_id
    ├── Unsets: _fkcart_spl_addon_product_cart_key
    └── Unsets: _fkcart_remove_addons
```

---

### Database Data Flow

```
Order Completed
├── Session data → Order meta
│   ├── _fkcart_upsell_views
│   ├── _fkcart_free_gift_views
│   ├── _fkcart_free_shipping_methods
│   └── _fkcart_discount_code_views
│
├── Cart items → fk_cart_products
│   ├── Upsells (type=1)
│   ├── Free gifts (type=2)
│   └── Special add-ons (type=3)
│
└── Order summary → fk_cart
    ├── oid, onumber
    ├── discount (coupon codes)
    ├── free_shipping
    └── upsells_viewed
```

---

### Settings Dependencies

```
enable_cart (master switch)
├── If disabled, all pro features skip initialization
│
reward (array)
├── Depends on: enable_cart
├── Contains: type, amount, title, coupon, freeProduct
│
upsell_type
├── Depends on: enable_cart
├── Values: 'upsell', 'cross_sell', 'both'
│
enable_special_addon
├── Depends on: enable_cart
├── Enables: special_addon_product, preselect_special_addon
│
reward_calculation_based
├── Depends on: reward enabled
├── Values: 'subtotal', 'total'
```

---

## 6. Dependency Graphs

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      WordPress Core                          │
├─────────────────────────────────────────────────────────────┤
│                      WooCommerce                             │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐ ┌─────────────────┐   │
│  │  Cart   │ │ Session │ │ Orders  │ │ Shipping Zones  │   │
│  └────┬────┘ └────┬────┘ └────┬────┘ └────────┬────────┘   │
├───────┼──────────┼──────────┼─────────────────┼─────────────┤
│       │          │          │                 │              │
│  ┌────┴──────────┴──────────┴─────────────────┴────────┐    │
│  │              FunnelKit Cart (Lite)                   │    │
│  │  ┌──────┐ ┌───────┐ ┌──────┐ ┌───────────────────┐  │    │
│  │  │ Data │ │ Front │ │ Cart │ │ Compatibility     │  │    │
│  │  └──┬───┘ └───┬───┘ └──┬───┘ └─────────┬─────────┘  │    │
│  └─────┼─────────┼────────┼───────────────┼────────────┘    │
├────────┼─────────┼────────┼───────────────┼─────────────────┤
│        │         │        │               │                  │
│  ┌─────┴─────────┴────────┴───────────────┴──────────────┐  │
│  │           Cart for WooCommerce Pro                     │  │
│  │                                                        │  │
│  │  ┌────────┐  ┌─────────┐  ┌──────────────────────┐    │  │
│  │  │ Plugin │──│ Upsells │  │ Special Add-On       │    │  │
│  │  └───┬────┘  └────┬────┘  └──────────┬───────────┘    │  │
│  │      │            │                   │                │  │
│  │      │       ┌────┴────┐              │                │  │
│  │      │       │ Rewards │──────────────┤                │  │
│  │      │       └────┬────┘              │                │  │
│  │      │            │                   │                │  │
│  │      │       ┌────┴────────┐          │                │  │
│  │      │       │ Geolocation │          │                │  │
│  │      │       └─────────────┘          │                │  │
│  │      │                                │                │  │
│  │  ┌───┴─────────────┐  ┌───────────────┴───────────┐   │  │
│  │  │ REST Conversions│  │ Templates                 │   │  │
│  │  └───────┬─────────┘  └───────────────────────────┘   │  │
│  │          │                                             │  │
│  │  ┌───────┴─────────┐  ┌─────────────────────────┐     │  │
│  │  │ Export          │  │ DB Migrator             │     │  │
│  │  └─────────────────┘  └─────────────────────────┘     │  │
│  └────────────────────────────────────────────────────────┘  │
├──────────────────────────────────────────────────────────────┤
│                   FunnelKit Funnel Builder                    │
│  ┌──────────┐ ┌────────────────┐ ┌─────────────────────┐    │
│  │WFFN_Core │ │ REST Controller│ │ Background Updater  │    │
│  └──────────┘ └────────────────┘ └─────────────────────┘    │
│                                                               │
│                   FunnelKit Funnel Builder Pro               │
│  ┌────────────────┐ ┌──────────────────────┐                │
│  │ WFFN_Pro_Core  │ │ Abstract Exporter    │                │
│  └────────────────┘ └──────────────────────┘                │
└──────────────────────────────────────────────────────────────┘
```

---

### Rewards Data Flow

```
┌─────────────────┐
│  Cart Change    │
└────────┬────────┘
         │
         ▼
┌─────────────────┐     ┌──────────────────┐
│ calculate_totals│────▶│ Rewards::        │
│  (priority 99)  │     │ update_reward()  │
└─────────────────┘     └────────┬─────────┘
                                 │
         ┌───────────────────────┼───────────────────────┐
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ process_coupons │     │ process_gifts   │     │ handle_free_    │
│                 │     │                 │     │ shipping        │
└────────┬────────┘     └────────┬────────┘     └────────┬────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│ WC()->cart->    │     │ WC()->cart->    │     │ WC()->session-> │
│ add_discount()  │     │ add_to_cart()   │     │ set(shipping)   │
└────────┬────────┘     └────────┬────────┘     └─────────────────┘
         │                       │
         ▼                       ▼
┌─────────────────┐     ┌─────────────────┐
│ Session:        │     │ Session:        │
│ _applied_coupons│     │ _free_gift_views│
└─────────────────┘     └─────────────────┘
```

---

### Plugin Compatibility Check

```
┌──────────────────────────────────────────────────────────────┐
│  What happens if dependency is missing?                       │
├──────────────────────────────────────────────────────────────┤
│                                                               │
│  WooCommerce missing                                          │
│  └── Plugin won't activate (WordPress dependency check)       │
│                                                               │
│  FunnelKit Cart missing                                       │
│  └── funnelkit_cart_loaded never fires                        │
│  └── Pro features never load                                  │
│                                                               │
│  WFFN_Core missing                                            │
│  └── include_core() returns early                             │
│  └── Pro features never load                                  │
│                                                               │
│  WFFN_Pro_Core missing                                        │
│  └── Export functionality not registered                      │
│  └── Other pro features still work                            │
│                                                               │
│  WC Session unavailable (CLI/cron)                            │
│  └── Session-dependent code returns early                     │
│  └── Graceful degradation                                     │
│                                                               │
│  License invalid                                              │
│  └── valid_l() returns false                                  │
│  └── Pro features return empty/early                          │
│  └── UI may show, but functionality disabled                  │
│                                                               │
└──────────────────────────────────────────────────────────────┘
```
