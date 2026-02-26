# Database Schema

> Complete database structure documentation for Cart for WooCommerce Pro

---

## Table of Contents

1. [Custom Tables](#1-custom-tables)
2. [WordPress Options](#2-wordpress-options)
3. [Transients](#3-transients)
4. [WooCommerce Session Data](#4-woocommerce-session-data)
5. [Order Meta Data](#5-order-meta-data)
6. [Post Meta Data](#6-post-meta-data)
7. [Database Queries Reference](#7-database-queries-reference)
8. [Migration Information](#8-migration-information)

---

## 1. Custom Tables

> **Note:** Custom tables are created by the base FunnelKit Cart plugin. This pro module uses these tables for data storage.

### {prefix}fk_cart

**Purpose:** Stores cart conversion tracking data per order

**Table Name:** `{$wpdb->prefix}fk_cart`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) | NO | AUTO_INCREMENT | Primary key |
| `oid` | BIGINT(20) | NO | - | WooCommerce Order ID |
| `onumber` | VARCHAR(100) | YES | NULL | Order number (display) |
| `discount` | TEXT | YES | NULL | JSON array of applied discount codes |
| `free_shipping` | TINYINT(1) | YES | 0 | Whether free shipping reward was used |
| `upsells_viewed` | TEXT | YES | NULL | JSON array of viewed upsell product IDs |
| `addon_viewed` | TEXT | YES | NULL | JSON array of viewed add-on product IDs |
| `date_created` | DATETIME | NO | - | Record creation timestamp |

**Indexes:**
- PRIMARY KEY (`id`)
- INDEX on `oid`
- INDEX on `date_created`

**Example Data:**
```sql
INSERT INTO wp_fk_cart (oid, onumber, discount, free_shipping, upsells_viewed, date_created)
VALUES (123, '1023', '["SAVE10"]', 1, '["45","67"]', '2024-01-15 10:30:00');
```

---

### {prefix}fk_cart_products

**Purpose:** Stores product-level tracking for upsells, gifts, and add-ons

**Table Name:** `{$wpdb->prefix}fk_cart_products`

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) | NO | AUTO_INCREMENT | Primary key |
| `oid` | BIGINT(20) | NO | - | WooCommerce Order ID |
| `product_id` | BIGINT(20) | NO | - | WooCommerce Product ID |
| `type` | TINYINT(1) | NO | - | Product type (see below) |
| `price` | DECIMAL(10,2) | YES | 0.00 | Product price at purchase |

**Product Type Values:**

| Type | Meaning | Description |
|------|---------|-------------|
| 1 | Upsell | Cart upsell product purchased |
| 2 | Free Gift | Free gift reward product |
| 3 | Special Add-On | Special add-on product purchased |

**Indexes:**
- PRIMARY KEY (`id`)
- INDEX on `oid`
- INDEX on `product_id`
- INDEX on `type`
- COMPOSITE INDEX on (`oid`, `type`)

**Example Data:**
```sql
INSERT INTO wp_fk_cart_products (oid, product_id, type, price) VALUES
(123, 45, 1, 29.99),  -- Upsell
(123, 67, 2, 0.00),   -- Free Gift
(123, 89, 3, 4.99);   -- Special Add-On
```

---

### {prefix}fk_cart_stats (LEGACY)

**Purpose:** Legacy statistics table - **DROPPED AFTER MIGRATION**

**Note:** This table is automatically dropped when the DB migration completes. It's only mentioned for reference during migration troubleshooting.

---

## 2. WordPress Options

### Plugin State Options

| Option Key | Type | Default | Purpose |
|------------|------|---------|---------|
| `_fkcart_upgrade` | int | 0 | Migration state indicator |

**Migration State Values:**

| Value | State | Description |
|-------|-------|-------------|
| 0 | Default | Nothing set, migration not started |
| 1 | Available | Upgrade is available |
| 2 | In Progress | Migration currently running |
| 3 | Completed | Migration finished successfully |
| 4 | Unavailable | Upgrade not available |

---

### Migration Tracking Options

| Option Key | Type | Default | Purpose |
|------------|------|---------|---------|
| `_bwf_fkcart_offset` | int | 0 | Current migration offset |
| `_bwf_fkcart_last_offsets` | array | [] | Last 5 offsets for stuck detection |
| `fkcart_order_maxid` | int | 0 | Maximum order ID to migrate |

**Stuck Detection Logic:**
```php
// If last 5 offsets are identical, process is stuck
$offsets = get_option('_bwf_fkcart_last_offsets', []);
if (count($offsets) === 5 && count(array_unique($offsets)) === 1) {
    // Kill stuck process
}
```

---

## 3. Transients

### Geolocation Cache

| Transient Key | TTL | Data Type | Purpose |
|---------------|-----|-----------|---------|
| `fkcart_geoip_{ip_address}` | 1 day | array | Cached geolocation for IP |
| `geoip_{ip_address}` | 1 day | string | WC-compatible country code cache |

**Data Structure:**
```php
[
    'country'  => 'US',
    'state'    => 'CA',
    'city'     => 'Los Angeles',
    'postcode' => '90001'
]
```

**Access Pattern:**
```php
// Check cache
$country_data = get_transient('fkcart_geoip_' . $ip_address);

// Set cache
set_transient('fkcart_geoip_' . $ip_address, $country_data, DAY_IN_SECONDS);
```

---

### Shipping Zone Cache (Object Cache)

| Cache Key | Group | Purpose |
|-----------|-------|---------|
| `wc_shipping_zone_{hash}` | `fkcart_shipping_zones` | Matched shipping zone ID |

**Key Generation:**
```php
$zone_cache_key = WC_Cache_Helper::get_cache_prefix('shipping_zones')
    . 'wc_shipping_zone_'
    . md5(sprintf('%s+%s+%s', $country, $state, $postcode));
```

---

## 4. WooCommerce Session Data

All session data stored via `WC()->session`:

### Upsell Tracking

| Session Key | Type | Purpose |
|-------------|------|---------|
| `_fkcart_upsell_views` | array | Product IDs of viewed upsells |

**Access:**
```php
// Get
$views = WC()->session->get('_fkcart_upsell_views', []);

// Set
WC()->session->set('_fkcart_upsell_views', array_unique($views));
```

---

### Reward Tracking

| Session Key | Type | Purpose |
|-------------|------|---------|
| `_fkcart_free_gift_views` | array | Product IDs of viewed free gifts |
| `_fkcart_discount_code_views` | array | Discount codes applied via rewards |
| `_fkcart_free_shipping_methods` | string | Active free shipping method ID |
| `_fkcart_applied_coupons` | array | Coupon codes applied by rewards |
| `_fkcart_removed_coupons` | array | Coupons user manually removed |

**Removed Coupons Structure:**
```php
[
    'coupon_code_lower' => true,
    'another_code' => true
]
```

---

### Special Add-On Tracking

| Session Key | Type | Purpose |
|-------------|------|---------|
| `_fkcart_spl_addon_product_id` | int | Product ID of special add-on |
| `_fkcart_spl_addon_product_cart_key` | string | Cart item key for add-on |
| `_fkcart_remove_addons` | string | 'yes' to prevent auto-add |

---

## 5. Order Meta Data

### Order-Level Meta

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_fkcart_upsell_views` | JSON string | Product IDs of upsells shown |
| `_fkcart_free_gift_views` | JSON string | Product IDs of gifts shown |
| `_fkcart_free_shipping_methods` | string | Free shipping method if used |
| `_fkcart_discount_code_views` | JSON string | Discount codes applied |

**Storage Format:**
```php
$order->add_meta_data('_fkcart_upsell_views', wp_json_encode(['45', '67', '89']));
```

---

### Order Item Meta

| Meta Key | Type | Purpose |
|----------|------|---------|
| `_fkcart_upsell` | string | 'yes' if upsell product |
| `_fkcart_free_gift` | string | 'yes' if free gift |
| `_fkcart_spl_addon` | bool | true if special add-on |

**Storage:**
```php
// During checkout
$item->add_meta_data('_fkcart_upsell', 'yes');
$item->add_meta_data('_fkcart_free_gift', 'yes');
```

---

## 6. Post Meta Data

The plugin does not create custom post meta. Product meta is used via WooCommerce's existing `_fkcart_free_gift` runtime property.

---

## 7. Database Queries Reference

### Common Read Queries

#### Get Conversion Data with Filters

```sql
SELECT
    c.oid AS order_id,
    c.onumber AS order_number,
    GROUP_CONCAT(DISTINCT CASE WHEN cp.type = 1 THEN cp.product_id END SEPARATOR ', ') AS cart_upsell_ids,
    SUM(CASE WHEN cp.type = 1 THEN cp.price ELSE 0 END) AS upsell_revenue,
    GROUP_CONCAT(DISTINCT CASE WHEN cp.type = 3 THEN cp.product_id END SEPARATOR ', ') AS special_addon_ids,
    SUM(CASE WHEN cp.type = 3 THEN cp.price ELSE 0 END) AS special_addon_revenue,
    GROUP_CONCAT(DISTINCT CASE WHEN cp.type = 2 THEN cp.product_id END SEPARATOR ', ') AS free_gift_ids,
    c.free_shipping AS free_shipping_orders,
    COUNT(DISTINCT CASE WHEN cp.type = 2 THEN cp.id END) AS free_gift_orders,
    c.discount AS discount,
    c.date_created AS date
FROM wp_fk_cart c
LEFT JOIN wp_fk_cart_products cp ON c.oid = cp.oid
WHERE c.date_created BETWEEN %s AND %s
GROUP BY c.oid, c.free_shipping, c.discount, c.date_created, c.onumber
ORDER BY c.date_created DESC
LIMIT %d OFFSET %d
```

---

#### Get Overview Statistics

```sql
SELECT
    COUNT(DISTINCT c.oid) as total_orders,
    SUM(cp.price) as total_revenue,
    COUNT(DISTINCT CASE WHEN c.upsells_viewed IS NOT NULL AND c.upsells_viewed != '' THEN c.oid END) AS upsell_views,
    COUNT(DISTINCT CASE WHEN cp.type = 1 THEN c.oid END) as upsell_orders,
    SUM(CASE WHEN cp.type = 1 THEN cp.price END) as upsell_total_revenue,
    COUNT(DISTINCT CASE WHEN c.free_shipping = 1 THEN c.oid END) as free_shipping_orders,
    COUNT(CASE WHEN cp.type = 2 THEN cp.id END) as free_gift_orders,
    COUNT(DISTINCT CASE WHEN c.discount IS NOT NULL AND c.discount != '' THEN c.oid END) AS discount_count,
    COUNT(DISTINCT CASE WHEN cp.type = 3 THEN c.oid END) as special_addon_orders,
    SUM(CASE WHEN cp.type = 3 THEN cp.price ELSE 0 END) as special_addon_revenue
FROM wp_fk_cart c
LEFT JOIN wp_fk_cart_products cp ON c.oid = cp.oid
WHERE c.date_created BETWEEN %s AND %s
```

---

#### Get Popular Upsells

```sql
SELECT
    cp.product_id as pid,
    p.post_title as product_name,
    SUM(cp.price) as revenue,
    COUNT(DISTINCT cp.oid) as conversions
FROM wp_fk_cart_products cp
JOIN wp_fk_cart c ON cp.oid = c.oid
JOIN wp_posts p ON cp.product_id = p.ID
WHERE cp.type = 1
  AND c.date_created BETWEEN %s AND %s
GROUP BY cp.product_id, p.post_title
ORDER BY revenue DESC
LIMIT %d OFFSET %d
```

---

### Common Write Queries

#### Update Refund Price

```php
$wpdb->update(
    $wpdb->prefix . "fk_cart_products",
    ['price' => $new_price],
    ['type' => 1, 'id' => $record_id]
);
```

---

#### Delete Order Conversions (Full Refund)

```php
$wpdb->query('START TRANSACTION');
$wpdb->delete($wpdb->prefix . 'fk_cart_products', ['oid' => $order_id]);
$wpdb->delete($wpdb->prefix . 'fk_cart', ['oid' => $order_id]);
$wpdb->query('COMMIT');
```

---

#### Migration Update (Order Number)

```php
$wpdb->update(
    "{$wpdb->prefix}fk_cart",
    ['onumber' => $order_number],
    ['oid' => $order_id]
);
```

---

### Bulk Operations

#### Bulk Delete by Order IDs

```php
$placeholders = implode(',', array_fill(0, count($order_ids), '%d'));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->prefix}fk_cart_products WHERE oid IN ($placeholders)",
    ...$order_ids
));
$wpdb->query($wpdb->prepare(
    "DELETE FROM {$wpdb->prefix}fk_cart WHERE oid IN ($placeholders)",
    ...$order_ids
));
```

---

## 8. Migration Information

### Migration Process

The DB migrator updates the `onumber` column with display-friendly order numbers:

```php
// For each cart record
$order = wc_get_order($item['oid']);
$onumber = $order->get_order_number(); // May differ from ID
$wpdb->update(
    "{$wpdb->prefix}fk_cart",
    ['onumber' => $onumber],
    ['oid' => $item['oid']]
);
```

### Migration Flow

1. REST endpoint `/fkcart-migrate-data/` triggers migration
2. Background updater processes 100 records per batch
3. Offset tracked in `_bwf_fkcart_offset`
4. Stuck detection via `_bwf_fkcart_last_offsets`
5. On completion:
   - `_fkcart_upgrade` set to 3
   - `wp_fk_cart_stats` table dropped
   - Offset reset to 0

### Checking Migration Status

```php
// Get current state
$state = fkcart_db_migrator()->get_upgrade_state();

// States:
// 0 = Not started
// 2 = In progress
// 3 = Completed
```

---

## Data Integrity Considerations

### Foreign Key Relationships

No formal foreign keys exist, but logical relationships:

```
wp_fk_cart.oid → wp_wc_orders.id (or wp_posts.ID for legacy)
wp_fk_cart_products.oid → wp_fk_cart.oid
wp_fk_cart_products.product_id → wp_posts.ID (products)
```

### Data Cleanup on Refunds

When an order is refunded:
- **Full refund:** Records deleted from both tables
- **Partial refund:** Price updated in `fk_cart_products`

### Session Data Lifecycle

Session data cleared on:
- `woocommerce_cart_emptied` action
- Manual session destruction
- Session expiration (WC default: 48 hours)

---

## Backup Recommendations

Before any migration or major update:

```sql
-- Backup cart tables
CREATE TABLE wp_fk_cart_backup AS SELECT * FROM wp_fk_cart;
CREATE TABLE wp_fk_cart_products_backup AS SELECT * FROM wp_fk_cart_products;

-- Backup relevant options
SELECT * FROM wp_options WHERE option_name LIKE '%fkcart%';
```
