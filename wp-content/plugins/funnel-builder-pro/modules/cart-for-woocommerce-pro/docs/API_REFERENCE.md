# API Reference

> Complete API documentation for Cart for WooCommerce Pro

---

## Table of Contents

1. [REST API Endpoints](#1-rest-api-endpoints)
2. [Public PHP Methods](#2-public-php-methods)
3. [Filters Reference](#3-filters-reference)
4. [Actions Reference](#4-actions-reference)
5. [Data Structures](#5-data-structures)
6. [Usage Examples](#6-usage-examples)

---

## 1. REST API Endpoints

**Base URL:** `/wp-json/funnelkit-app/`

**Authentication:** All endpoints require valid WordPress authentication with appropriate capabilities.

---

### GET /fkcart-conversions/

**Purpose:** Retrieve paginated cart conversion data with filtering

**Permission:** `analytics:read`

#### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `s` | string | No | '' | Search term (order ID, order number, product title) |
| `limit` | int | No | `posts_per_page` | Records per page |
| `offset` | int | No | 0 | Starting offset |
| `page_no` | int | No | 1 | Page number |
| `total_count` | string | No | '' | Set to 'yes' to include total count |
| `filters` | JSON | No | [] | Filter configuration (see below) |
| `delete_ids` | string | No | '' | Comma-separated order IDs to delete |

#### Filter Structure

```json
[
  {
    "filter": "period",
    "data": {
      "after": "2024-01-01 00:00:00",
      "before": "2024-12-31 23:59:59"
    }
  },
  {
    "filter": "cart_upsell",
    "rule": "accepted",
    "data": [
      {"id": 123, "name": "Product A"}
    ]
  },
  {
    "filter": "rewards",
    "rule": "discount",
    "data": "yes",
    "data_2": [
      {"id": "save10", "name": "SAVE10"}
    ]
  }
]
```

#### Response

```json
{
  "status": true,
  "records": [
    {
      "order_id": 123,
      "order_number": "1023",
      "order_url": "https://...",
      "cart_upsell": {"45": "Product Name"},
      "upsell_revenue": "29.99",
      "special_addon": "-",
      "special_addon_revenue": "-",
      "free_gift": {"67": "Gift Product"},
      "free_shipping_orders": "Yes",
      "discount": "SAVE10",
      "date": "2024-01-15 10:30:00"
    }
  ],
  "filters_list": [...],
  "conversion_migration_status": 3,
  "total_count": 150
}
```

---

### GET /fkcart-overview/

**Purpose:** Get summary statistics for dashboard

**Permission:** `analytics:read`

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `filters` | JSON | No | Same as conversions endpoint |

#### Response

```json
{
  "status": true,
  "data": {
    "total_orders": 150,
    "total_revenue": "4500.00",
    "conversion_rate": "12.50",
    "free_shipping_orders": 45,
    "free_gift_orders": 30,
    "discount": 60,
    "special_addon": 25,
    "special_addon_revenue": "124.75",
    "special_addon_conversion_rate": "8.33"
  },
  "conversion_migration_status": 3
}
```

---

### GET /fkcart-upsell-performance/

**Purpose:** Get time-series performance data for charts

**Permission:** `analytics:read`

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `filters` | JSON | No | Date period filter |
| `interval` | string | No | 'day', 'week', 'month' |

#### Response

```json
{
  "status": true,
  "data": {
    "intervals": [
      {
        "interval": "2024-01",
        "start_date": "2024-01-01",
        "end_date": "2024-01-31",
        "subtotals": {
          "total_orders": 50,
          "total_revenue": "1500.00",
          "conversion_rate": "15.00",
          "free_shipping_orders": 15,
          "free_gift_orders": 10,
          "discount": 20,
          "special_addon": 8,
          "special_addon_revenue": "39.92",
          "special_addon_conversion_rate": "10.00"
        }
      }
    ],
    "interval_type": "month"
  }
}
```

---

### GET /fkcart-popular-upsells/

**Purpose:** Get top-performing upsell products

**Permission:** `analytics:read`

#### Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `limit` | int | No | `posts_per_page` | Number of products |
| `offset` | int | No | 0 | Starting offset |
| `s` | string | No | '' | Search by product name |
| `filters` | JSON | No | [] | Date period filter |

#### Response

```json
{
  "status": true,
  "data": [
    {
      "pid": "45",
      "name": "Premium Widget",
      "revenue": "1250.00",
      "conversion_rate": 18.50
    }
  ],
  "total_count": 25
}
```

---

### GET /fkcart-reward-chart/

**Purpose:** Get pie chart data for reward distribution

**Permission:** `analytics:read`

#### Parameters

Same as `/fkcart-overview/`

#### Response

```json
{
  "status": true,
  "data": [
    {
      "key": "free_shipping",
      "title": "Free Shipping",
      "percentage": 33.33,
      "count": 45
    },
    {
      "key": "free_gift",
      "title": "Free Gift",
      "percentage": 22.22,
      "count": 30
    },
    {
      "key": "discount",
      "title": "Discount",
      "percentage": 44.45,
      "count": 60
    }
  ]
}
```

---

### GET /fkcart-product-search/

**Purpose:** Search products for filter UI

**Permission:** `analytics:read`

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `s` | string | Yes | Search term |
| `variations` | string | No | Set to '0' to exclude variations |

#### Response

```json
[
  {
    "id": 45,
    "name": "Premium Widget"
  },
  {
    "id": 46,
    "name": "Premium Widget - Blue, Large"
  }
]
```

---

### GET /fkcart-coupon-search/

**Purpose:** Search coupons from conversion data

**Permission:** `analytics:read`

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `s` | string | No | Search term |

#### Response

```json
[
  {
    "id": "save10",
    "name": "SAVE10"
  },
  {
    "id": "freeship",
    "name": "FREESHIP"
  }
]
```

---

### POST /fkcart-migrate-data/

**Purpose:** Trigger database migration for order numbers

**Permission:** `analytics:write`

#### Response

```json
{
  "success": true
}
```

---

### POST /cart-conversions/export/add

**Purpose:** Queue CSV export job

**Permission:** `analytics:write`

#### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `filters` | JSON | No | Export filters |

#### Response

```json
{
  "status": true,
  "message": "Export Added to Queue",
  "response": {
    "export_id": 123
  }
}
```

---

## 2. Public PHP Methods

### Plugin Class

#### Plugin::getInstance()

```php
/**
 * Get singleton instance
 * @return Plugin
 */
public static function getInstance(): Plugin
```

#### Plugin::valid_l()

```php
/**
 * Validate license status
 * @return bool True if license is valid
 */
public static function valid_l(): bool
```

#### Plugin::get_current_app_state()

```php
/**
 * Get current license/app state
 * @return string One of: 'pro', 'license_expired', 'pro_without_license',
 *                        'license_expired_on_grace_period', 'pro_without_license_on_grace_period'
 */
public static function get_current_app_state(): string
```

---

### Upsells Class

#### Upsells::getInstance()

```php
/**
 * Get singleton instance
 * @return Upsells
 */
public static function getInstance(): Upsells
```

#### Upsells::get_upsell_products()

```php
/**
 * Get upsell products for cart display
 * @return array Array of product data keyed by product ID
 */
public function get_upsell_products(): array

// Return structure:
[
    product_id => [
        'product_id' => int,
        'product_name' => string,
        'product' => WC_Product,
        'price' => string,
        'image' => string,
        // ... other product data from Front::get_preview_item()
    ]
]
```

#### Upsells::get_upsell_ids()

```php
/**
 * Get array of upsell product IDs for current cart
 * @return array Product IDs
 */
public function get_upsell_ids(): array
```

#### Upsells::get_upsell_views()

```php
/**
 * Get viewed upsell product IDs from session
 * @return array Product IDs
 */
public function get_upsell_views(): array
```

---

### Rewards Class

#### Rewards::getInstance()

```php
/**
 * Get singleton instance
 * @return Rewards
 */
public static function getInstance(): Rewards
```

#### Rewards::get_rewards()

```php
/**
 * Get processed rewards data
 * @param bool $raw_data If true, skip some processing
 * @return array|void Rewards data or void if disabled
 */
public static function get_rewards(bool $raw_data = false): ?array

// Return structure:
[
    'max_amount' => float,
    'title' => string,
    'coupons' => [
        'add' => array,
        'remove' => array
    ],
    'gifts' => [
        'add' => array,
        'remove' => array
    ],
    'free_shipping' => string|false,
    'rewards' => array,
    'progress_bar' => float,
    'subtotal' => float
]
```

#### Rewards::is_free_shipping_reward_available()

```php
/**
 * Check if free shipping reward is configured
 * @return bool
 */
public static function is_free_shipping_reward_available(): bool
```

#### Rewards::get_shipping_min_amount()

```php
/**
 * Get minimum amount for free shipping
 * @return array|false ['min_amount' => float, 'method_id' => string] or false
 */
public static function get_shipping_min_amount()
```

#### Rewards::get_cart_total()

```php
/**
 * Get cart total for reward calculations
 * @return float
 */
public static function get_cart_total(): float
```

#### Rewards::map_variation_attributes()

```php
/**
 * Map variation attributes for Any-Any case handling
 * @param array $variation_attr Variation attributes
 * @param array $product_attr Product attributes
 * @return array Mapped attributes with 'attribute_' prefix
 */
public static function map_variation_attributes(array $variation_attr, array $product_attr): array
```

---

### Special_Add_On Class

#### Special_Add_On::getInstance()

```php
/**
 * Get singleton instance
 * @return Special_Add_On
 */
public static function getInstance(): Special_Add_On
```

#### Special_Add_On::get_settings()

```php
/**
 * Get plugin settings
 * @return array Settings array
 */
public static function get_settings(): array
```

#### Special_Add_On::special_product_addon()

```php
/**
 * Handle add/remove special add-on via AJAX
 * @param array $data Optional POST data override
 * @return string|void Success/error message
 */
public static function special_product_addon(array $data = [])
```

#### Special_Add_On::get_map_product()

```php
/**
 * Get translated product ID for WPML/Polylang
 * @param int $product_id Original product ID
 * @return int Translated product ID
 */
public static function get_map_product(int $product_id): int
```

#### Special_Add_On::check_special_addon_exist_in_cart()

```php
/**
 * Check if special add-on product exists in cart (not as add-on)
 * @param int $special_addon_product_id Product ID to check
 * @return bool True if exists as regular product
 */
public static function check_special_addon_exist_in_cart(int $special_addon_product_id): bool
```

---

### Geolocation Class

#### Geolocation::geolocate_ip()

```php
/**
 * Get geolocation data for IP address
 * @param string $ip_address IP to locate (default: current)
 * @param bool $fallback Try external IP if local fails
 * @param bool $api_fallback Use external API
 * @return array ['country', 'state', 'city', 'postcode']
 */
public static function geolocate_ip(
    string $ip_address = '',
    bool $fallback = false,
    bool $api_fallback = true
): array
```

---

### FKCART_DB_Migrator Class

#### fkcart_db_migrator()

```php
/**
 * Get migrator singleton instance
 * @return FKCART_DB_Migrator
 */
function fkcart_db_migrator(): FKCART_DB_Migrator
```

#### FKCART_DB_Migrator::get_upgrade_state()

```php
/**
 * Get current migration state
 * @return int 0=default, 1=available, 2=in_progress, 3=completed, 4=unavailable
 */
public function get_upgrade_state(): int
```

#### FKCART_DB_Migrator::set_upgrade_state()

```php
/**
 * Set migration state
 * @param int $state New state value
 */
public function set_upgrade_state(int $state): void
```

---

## 3. Filters Reference

### Upsell Filters

```php
/**
 * Modify default upsell product IDs
 * @param array $product_ids Default product IDs
 * @return array Modified product IDs
 */
apply_filters('fkcart_default_upsells', array $product_ids): array
```

---

### Reward Filters

```php
/**
 * Override reward calculation mode
 * @param string $mode 'subtotal' or 'total'
 * @return string Modified mode
 */
apply_filters('fkcart_reward_calculation_based_on', string $mode): string

/**
 * Modify cart total for reward calculation
 * @param float $total Calculated total
 * @param string $mode Calculation mode
 * @param Front $front Front instance
 * @return float Modified total
 */
apply_filters('fkcart_reward_total', float $total, string $mode, Front $front): float

/**
 * Modify final rewards list
 * @param array $rewards Complete rewards data
 * @return array Modified rewards
 */
apply_filters('fkcart_rewards_list', array $rewards): array

/**
 * Skip specific reward
 * @param bool $skip Whether to skip
 * @param array $reward Reward configuration
 * @return bool True to skip
 */
apply_filters('fkcart_reward_rules_checking', bool $skip, array $reward): bool

/**
 * Modify gift products to add/remove
 * @param array $gifts ['add' => [], 'remove' => []]
 * @param array $rewards Full rewards data
 * @return array Modified gifts
 */
apply_filters('fkcart_gift_products', array $gifts, array $rewards): array

/**
 * Modify free shipping configuration
 * @param array|false $data Shipping data or false
 * @return array|false Modified data
 */
apply_filters('fkcart_free_shipping', $data)

/**
 * Control auto-selection of free shipping method
 * @param bool $should_set Default true
 * @return bool
 */
apply_filters('fkcart_need_to_set_free_shipping_method', bool $should_set): bool
```

---

### Geolocation Filters

```php
/**
 * Enable WooCommerce native geolocation filters
 * @param bool $allow Default false
 * @return bool
 */
apply_filters('fkcart_allow_wc_geolocate_filters', bool $allow): bool

/**
 * Override geolocation data
 * @param array $geolocation Empty geolocation array
 * @param string $ip_address IP being located
 * @return array Geolocation data
 */
apply_filters('fkcart_woocommerce_geolocate_ip', array $geolocation, string $ip_address): array

/**
 * Control customer location auto-population
 * @param bool $should_set Default true
 * @return bool
 */
apply_filters('fkcart_set_geolocation_data_to_customer', bool $should_set): bool
```

---

### Product Filters

```php
/**
 * Modify allowed product types for upsells/search
 * @param array $types Default types
 * @return array Modified types
 */
apply_filters('fkcart_allow_product_types', array $types): array

// Default: ['simple', 'variable', 'variation', 'variable-subscription', 'subscription']
```

---

## 4. Actions Reference

```php
/**
 * Fired when geolocation is determined
 * @param array $geolocation Location data
 * @param string $ip_address IP address
 */
do_action('fkcart_geolocation', array $geolocation, string $ip_address)

/**
 * Fired before special add-on is added to cart
 * @param array $post_data POST data
 */
do_action('fkcart_spl_addon_before_add_to_cart', array $post_data)
```

---

## 5. Data Structures

### Reward Configuration

```php
[
    'type' => 'freeshipping|discount|freegift',
    'amount' => 50.00,           // Threshold amount
    'title' => 'Spend {{remaining_amount}} more for free shipping',

    // For discount type:
    'coupon' => 'SAVE10',

    // For freegift type:
    'freeProduct' => [
        ['key' => 123, 'value' => 'Product Name']
    ]
]
```

### Processed Rewards

```php
[
    'max_amount' => 100.00,
    'title' => 'Spend $25.00 more for free shipping',
    'coupons' => [
        'add' => ['SAVE10'],
        'remove' => []
    ],
    'gifts' => [
        'add' => [123],
        'remove' => []
    ],
    'free_shipping' => 'free_shipping:1',
    'rewards' => [
        [
            'type' => 'freeshipping',
            'amount' => 50.00,
            'achieved' => true,
            'pending_amount' => 0,
            'progress_width' => 50
        ]
    ],
    'progress_bar' => 75.5,
    'subtotal' => 75.00
]
```

### Cart Item Data Keys

```php
// Added to cart item during add_to_cart:
'_fkcart_upsell' => 1,        // Upsell product
'_fkcart_free_gift' => 1,     // Free gift product
'_fkcart_spl_addon' => true,  // Special add-on

// For variations:
'_fkcart_variation_gift' => true,
'_fkcart_variable_gift' => true
```

### Order Meta Keys

```php
// Order-level meta:
'_fkcart_upsell_views'          // JSON: viewed upsell product IDs
'_fkcart_free_gift_views'       // JSON: viewed gift product IDs
'_fkcart_free_shipping_methods' // String: shipping method ID
'_fkcart_discount_code_views'   // JSON: applied discount codes

// Order item meta:
'_fkcart_upsell'     // 'yes' if upsell
'_fkcart_free_gift'  // 'yes' if free gift
'_fkcart_spl_addon'  // 'yes' if special add-on
```

---

## 6. Usage Examples

### Adding Custom Upsells

```php
add_filter('fkcart_default_upsells', function($ids) {
    // Add recently viewed products
    $viewed = get_user_meta(get_current_user_id(), 'recently_viewed', true);
    if (is_array($viewed)) {
        $ids = array_merge($ids, array_slice($viewed, 0, 3));
    }
    return array_unique($ids);
});
```

### Conditional Reward Disabling

```php
add_filter('fkcart_reward_rules_checking', function($skip, $reward) {
    // Skip free shipping for bulky items
    if ($reward['type'] === 'freeshipping') {
        foreach (WC()->cart->get_cart() as $item) {
            if ($item['data']->get_shipping_class() === 'bulky') {
                return true;
            }
        }
    }
    return $skip;
}, 10, 2);
```

### Custom Reward Total Calculation

```php
add_filter('fkcart_reward_total', function($total, $mode, $front) {
    // Exclude gift cards from reward calculation
    foreach (WC()->cart->get_cart() as $item) {
        if ($item['data']->is_type('gift_card')) {
            $total -= $item['line_total'];
        }
    }
    return max(0, $total);
}, 10, 3);
```

### Using Geolocation Override

```php
add_filter('fkcart_woocommerce_geolocate_ip', function($geo, $ip) {
    // Use saved customer address if available
    if (is_user_logged_in()) {
        $customer = new WC_Customer(get_current_user_id());
        if ($customer->get_billing_country()) {
            return [
                'country' => $customer->get_billing_country(),
                'state' => $customer->get_billing_state(),
                'city' => $customer->get_billing_city(),
                'postcode' => $customer->get_billing_postcode()
            ];
        }
    }
    return $geo;
}, 10, 2);
```

### Checking Upsell Status

```php
// Check if product was an upsell
$order = wc_get_order($order_id);
foreach ($order->get_items() as $item) {
    if ($item->get_meta('_fkcart_upsell') === 'yes') {
        // This was an upsell product
    }
    if ($item->get_meta('_fkcart_free_gift') === 'yes') {
        // This was a free gift
    }
}
```

### REST API Request Example

```javascript
// Fetch conversion data
const response = await fetch('/wp-json/funnelkit-app/fkcart-conversions/', {
    method: 'GET',
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
        'Content-Type': 'application/json'
    },
    credentials: 'same-origin'
});

const data = await response.json();
console.log(data.records);
```

```javascript
// With filters
const params = new URLSearchParams({
    limit: 20,
    page_no: 1,
    total_count: 'yes',
    filters: JSON.stringify([
        {
            filter: 'period',
            data: {
                after: '2024-01-01 00:00:00',
                before: '2024-12-31 23:59:59'
            }
        }
    ])
});

const response = await fetch(`/wp-json/funnelkit-app/fkcart-conversions/?${params}`, {
    headers: { 'X-WP-Nonce': wpApiSettings.nonce },
    credentials: 'same-origin'
});
```
