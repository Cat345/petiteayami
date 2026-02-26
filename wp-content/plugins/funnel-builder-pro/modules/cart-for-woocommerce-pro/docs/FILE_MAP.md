# File Map

> Feature-to-files mapping for Cart for WooCommerce Pro

---

## Quick Navigation

| Feature | Primary File | Related Files |
|---------|--------------|---------------|
| Plugin Bootstrap | `plugin.php` | - |
| Cart Upsells | `include/upsells.php` | - |
| Rewards System | `include/rewards.php` | `include/geolocation.php` |
| Special Add-On | `include/special-add-on.php` | `templates/cart/special-addon-html.php` |
| Analytics API | `rest/conversions.php` | - |
| Data Export | `include/fkcart-export-cart-conversion.php` | `rest/conversions.php` |
| DB Migration | `include/fkcart-db-migrator.php` | - |
| Geolocation | `include/geolocation.php` | - |

---

## Detailed File Breakdown

### plugin.php (119 lines)

**Primary Purpose:** Plugin entry point and license validation

**Features Implemented:**
- Plugin initialization and bootstrapping
- License validation (`valid_l()`, `get_current_app_state()`)
- Feature class loading

**Key Touchpoints:**
- Lines 17-18: Hook registration
- Lines 21-37: Core loading logic
- Lines 62-75: License validation
- Lines 78-116: License state detection

**Modifies When:**
- Adding new pro features (add include in `include_core()`)
- Changing license requirements
- Adding new initialization hooks

---

### include/upsells.php (331 lines)

**Primary Purpose:** Cart upsell product management and tracking

**Features Implemented:**
- Upsell product recommendations
- Session-based view tracking
- Order item metadata
- Refund revenue tracking

**Key Touchpoints:**
- Lines 13-23: Constructor with hook registration
- Lines 42-49: Order line item meta addition
- Lines 51-80: Order reward data storage
- Lines 90-147: Partial refund handling
- Lines 149-178: Full refund handling
- Lines 186-217: Get upsell products for display
- Lines 224-274: Get upsell product IDs
- Lines 307-314: Session view tracking

**Modifies When:**
- Changing upsell display logic
- Adding new upsell sources
- Modifying order tracking
- Changing refund behavior

---

### include/rewards.php (1217 lines)

**Primary Purpose:** Progress bar rewards system

**Features Implemented:**
- Free shipping rewards
- Discount coupon rewards
- Free gift products
- Geolocation-based shipping
- Session state management
- Checkout field handling

**Key Sections:**

| Lines | Functionality |
|-------|---------------|
| 15-53 | Constructor with extensive hook setup |
| 73-105 | Free gift price handling |
| 107-137 | Delete link prevention for gifts |
| 143-170 | Gift removal on cart empty |
| 181-222 | Main reward processing (`update_reward()`) |
| 233-268 | Coupon processing |
| 279-367 | Gift product processing |
| 377-441 | Gift product addition |
| 450-463 | Free shipping handling |
| 470-647 | `get_rewards()` - main calculation |
| 684-775 | Shipping zone/method detection |
| 817-840 | Removed coupon tracking |
| 901-912 | Cart total calculation |
| 922-940 | Variation attribute mapping |
| 946-961 | Shipping method selection at checkout |
| 970-1002 | Geolocation data handling |
| 1088-1214 | Checkout field modifications |

**Modifies When:**
- Adding new reward types
- Changing calculation logic
- Modifying free shipping detection
- Updating checkout behavior

---

### include/special-add-on.php (558 lines)

**Primary Purpose:** Single product add-on feature

**Features Implemented:**
- Auto-add product to cart
- Toggle/checkbox UI
- Variable product handling
- CSS styling
- WPML/Polylang compatibility

**Key Sections:**

| Lines | Functionality |
|-------|---------------|
| 10-23 | Constructor with hooks |
| 41-89 | Auto-add on cart add (`handle_special_addon_product()`) |
| 94-143 | Variable product handling |
| 148-200 | Toggle on/off via AJAX (`special_product_addon()`) |
| 202-209 | Session cleanup |
| 211-499 | Internal CSS styles |
| 501-519 | CSS variables filter |
| 521-529 | Template rendering |
| 531-542 | Cart existence check |
| 545-557 | WPML/Polylang product mapping |

**Modifies When:**
- Changing add-on behavior
- Updating styling
- Adding new settings
- Modifying variable product support

---

### include/geolocation.php (151 lines)

**Primary Purpose:** Extended geolocation for shipping rewards

**Features Implemented:**
- IP-based location detection
- External API fallback
- Transient caching

**Key Sections:**

| Lines | Functionality |
|-------|---------------|
| 20-84 | `geolocate_ip()` - main entry point |
| 93-149 | `geolocate_via_api()` - external API calls |

**Modifies When:**
- Adding geolocation providers
- Changing caching strategy
- Updating geolocation data structure

---

### include/fkcart-db-migrator.php (270 lines)

**Primary Purpose:** Background data migration

**Features Implemented:**
- Batch processing
- Stuck process detection
- Order number migration

**Key Sections:**

| Lines | Functionality |
|-------|---------------|
| 12-15 | Constructor and hook |
| 26-47 | Stuck process redispatch |
| 58-60 | Process termination |
| 69-88 | Offset tracking for stuck detection |
| 90-101 | Migration completion |
| 103-117 | State management |
| 123-192 | Main migration logic |
| 202-252 | Bulk data operations |

**Modifies When:**
- Changing migration logic
- Adding new columns to migrate
- Updating stuck detection

---

### include/fkcart-export-cart-conversion.php (145 lines)

**Primary Purpose:** CSV export functionality

**Features Implemented:**
- Export column definitions
- Batched data export
- CSV file generation

**Key Sections:**

| Lines | Functionality |
|-------|---------------|
| 49-62 | Column definitions |
| 64-80 | Total row count |
| 86-120 | Export data retrieval |
| 129-139 | CSV file writing |

**Modifies When:**
- Adding export columns
- Changing export format
- Modifying data transformation

---

### rest/conversions.php (1013 lines)

**Primary Purpose:** REST API for analytics

**Features Implemented:**
- Conversion data endpoints
- Filter configuration
- Overview statistics
- Performance charts
- Product/coupon search

**Key Sections:**

| Lines | Functionality |
|-------|---------------|
| 27-29 | Constructor |
| 43-98 | REST route registration |
| 100-122 | Permission checks |
| 124-134 | Migration trigger |
| 136-158 | Filter preparation |
| 160-233 | Filter configuration |
| 235-262 | Product name resolution |
| 264-533 | Main conversion data (`get_cart_upsell_data()`) |
| 535-588 | Product search API |
| 590-631 | Coupon search API |
| 633-662 | Pie chart data |
| 664-773 | Popular upsells |
| 775-937 | Overview/performance data |
| 940-944 | Performance endpoint |
| 951-980 | Export queue addition |
| 989-1009 | Bulk delete |

**Modifies When:**
- Adding new endpoints
- Changing filter options
- Updating statistics calculations
- Modifying search behavior

---

### templates/cart/special-addon-html.php (246 lines)

**Primary Purpose:** Special add-on display template

**Features Implemented:**
- Add-on product display
- Toggle/checkbox rendering
- Price display
- Variable product meta

**Key Sections:**

| Lines | Functionality |
|-------|---------------|
| 1-42 | Settings and product validation |
| 44-67 | Product data retrieval |
| 69-162 | CSS class configuration |
| 164-195 | Cart state detection |
| 207-245 | HTML template output |

**Modifies When:**
- Changing add-on layout
- Adding new display elements
- Updating interactive behavior

---

## Feature → File Quick Reference

### "I want to modify..."

| Modification | File(s) to Edit |
|--------------|-----------------|
| Upsell product selection | `include/upsells.php:224-274` |
| Upsell display count | Settings + `include/upsells.php:197-209` |
| Reward calculation | `include/rewards.php:470-647` |
| Free shipping detection | `include/rewards.php:684-775` |
| Gift product handling | `include/rewards.php:279-441` |
| Coupon auto-apply | `include/rewards.php:233-268` |
| Special add-on auto-add | `include/special-add-on.php:41-89` |
| Add-on styling | `include/special-add-on.php:211-499` |
| Add-on template | `templates/cart/special-addon-html.php` |
| Analytics filters | `rest/conversions.php:160-233` |
| Analytics calculations | `rest/conversions.php:775-937` |
| Export columns | `include/fkcart-export-cart-conversion.php:49-62` |
| Geolocation providers | `include/geolocation.php:101-104` |
| License checking | `plugin.php:62-116` |

---

## File Dependencies

```
plugin.php
├── include/upsells.php
│   └── (uses FKCart\Includes\Data, FKCart\Includes\Front)
├── include/rewards.php
│   ├── include/geolocation.php
│   └── (uses FKCart\Includes\Data, FKCart\Includes\Front, Compatibility)
├── include/special-add-on.php
│   ├── templates/cart/special-addon-html.php
│   └── (uses FKCart\Includes\Data, FKCart\Includes\cart)
├── include/fkcart-db-migrator.php
│   └── (extends WooFunnels_Background_Updater)
├── include/fkcart-export-cart-conversion.php
│   ├── rest/conversions.php (data source)
│   └── (extends WFFN_Abstract_Exporter)
└── rest/conversions.php
    └── (extends WFFN_REST_Controller)
```

---

## Entry Points by Trigger

### WordPress Hooks

| Hook | Files Activated |
|------|-----------------|
| `funnelkit_cart_loaded` | All include files loaded |
| `rest_api_init` | `rest/conversions.php` |
| `wffn_pro_loaded` | `include/fkcart-export-cart-conversion.php` |

### WooCommerce Events

| Event | File Responding |
|-------|-----------------|
| Cart add | `include/special-add-on.php`, `include/rewards.php` |
| Cart calculate | `include/rewards.php` |
| Order create | `include/upsells.php` |
| Order refund | `include/upsells.php` |

### REST Requests

| Endpoint Pattern | File Handler |
|------------------|--------------|
| `/fkcart-*` | `rest/conversions.php` |
| `/cart-conversions/export/*` | `rest/conversions.php` + `include/fkcart-export-cart-conversion.php` |

### Background Processing

| Trigger | File Handler |
|---------|--------------|
| `wffn_conversion_migration_complete_batch` | `include/fkcart-db-migrator.php` |
