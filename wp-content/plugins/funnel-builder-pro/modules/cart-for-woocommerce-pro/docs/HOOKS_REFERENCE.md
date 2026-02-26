# Hooks Reference

> Complete registry of all WordPress actions and filters used by Cart for WooCommerce Pro

---

## Table of Contents

1. [Actions Added by Plugin](#1-actions-added-by-plugin)
2. [Filters Added by Plugin](#2-filters-added-by-plugin)
3. [Custom Actions (Extensibility)](#3-custom-actions-extensibility)
4. [Custom Filters (Extensibility)](#4-custom-filters-extensibility)
5. [WooCommerce Hooks Used](#5-woocommerce-hooks-used)
6. [Hook Execution Order](#6-hook-execution-order)

---

## 1. Actions Added by Plugin

### Plugin Initialization

| Hook | Priority | Callback | File | Purpose |
|------|----------|----------|------|---------|
| `funnelkit_cart_loaded` | 15 | `Plugin::include_core` | plugin.php:17 | Load pro features after base plugin |
| `wffn_pro_loaded` | 11 | `Plugin::load_exporters` | plugin.php:18 | Register CSV exporters |
| `rest_api_init` | 9 | `Plugin::init_rest_api` | plugin.php:36 | Register REST endpoints |

### Upsells Class

| Hook | Priority | Callback | File | Purpose |
|------|----------|----------|------|---------|
| `woocommerce_checkout_create_order_line_item` | 999999 | `Upsells::woocommerce_create_order_line_item` | include/upsells.php:18 | Add upsell/gift meta to order items |
| `woocommerce_order_fully_refunded` | 10 | `Upsells::fully_refunded_process` | include/upsells.php:19 | Handle full refund revenue tracking |
| `woocommerce_order_partially_refunded` | 10 | `Upsells::partially_refunded_process` | include/upsells.php:20 | Handle partial refund revenue tracking |
| `woocommerce_checkout_create_order` | 10 | `Upsells::update_reward_data_in_order` | include/upsells.php:21 | Store reward view data in order |
| `woocommerce_delete_order` | 10 | `Upsells::fully_refunded_process` | include/upsells.php:22 | Clean up on order deletion |

### Rewards Class

| Hook | Priority | Callback | File | Purpose |
|------|----------|----------|------|---------|
| `wp` | 10 | `Rewards::maybe_remove_free_gifts` | include/rewards.php:21 | Remove free gifts if cart empty/invalid |
| `woocommerce_cart_loaded_from_session` | 98 | `Rewards::update_free_gift` | include/rewards.php:22 | Set free gift prices to 0 |
| `woocommerce_before_calculate_totals` | 98 | `Rewards::update_free_gift` | include/rewards.php:23 | Set free gift prices to 0 |
| `woocommerce_calculate_totals` | 99 | `Rewards::update_reward` | include/rewards.php:24 | Apply/remove rewards based on cart total |
| `fkcart_variable_product_before_update` | 99 | `Rewards::remove_action_update_reward` | include/rewards.php:25 | Temporarily remove reward hook |
| `fkcart_variable_product_after_update` | 99 | `Rewards::update_reward` | include/rewards.php:26 | Re-run reward processing |
| `woocommerce_before_calculate_totals` | 90 | Anonymous function | include/rewards.php:35-39 | Add price filters for free gifts |
| `wp` | 22 | `Rewards::update_choosen_shipping_method` | include/rewards.php:40 | Set free shipping at checkout |
| `woocommerce_removed_coupon` | 10 | `Rewards::stored_removed_coupon` | include/rewards.php:33 | Track user-removed coupons |
| `woocommerce_cart_emptied` | 10 | `Rewards::unset_removed_coupon` | include/rewards.php:34 | Clear session on cart empty |
| `wp` | 10 | `Rewards::may_be_checkout_field_update` | include/rewards.php:48 | Handle checkout fields for free shipping |

### Special Add-On Class

| Hook | Priority | Callback | File | Purpose |
|------|----------|----------|------|---------|
| `woocommerce_add_to_cart` | 9999 | `Special_Add_On::handle_special_addon_product` | include/special-add-on.php:14 | Auto-add special add-on product |
| `woocommerce_cart_emptied` | 10 | `Special_Add_On::unset_special_addon_product` | include/special-add-on.php:15 | Clear add-on session data |
| `wp_footer` | 10 | `Special_Add_On::internal_style` | include/special-add-on.php:19 | Output CSS styles |
| `admin_footer` | 10 | `Special_Add_On::internal_style` | include/special-add-on.php:20 | Output CSS in admin |
| `fkcart_after_coupon_section` | 10 | `Special_Add_On::special_addon_html` | include/special-add-on.php:22 | Render add-on template |

### DB Migrator Class

| Hook | Priority | Callback | File | Purpose |
|------|----------|----------|------|---------|
| `wffn_conversion_migration_complete_batch` | 10 | `FKCART_DB_Migrator::db_migrator` | include/fkcart-db-migrator.php:14 | Run migration batch |

### REST Endpoints

| Hook | Priority | Callback | File | Purpose |
|------|----------|----------|------|---------|
| `rest_api_init` | 11 | `Conversions::register_contact_data_endpoint` | rest/conversions.php:28 | Register REST routes |

---

## 2. Filters Added by Plugin

### Rewards Class

| Filter | Priority | Callback | File | Purpose |
|--------|----------|----------|------|---------|
| `woocommerce_cart_item_remove_link` | 10 | `Rewards::do_not_allow_delete_free_gift` | include/rewards.php:28 | Hide delete link for free gifts |
| `wfacp_enable_delete_item` | 10 | `Rewards::aero_disabled_delete_icon` | include/rewards.php:29 | Disable Aero delete for gifts |
| `wfacp_mini_cart_enable_delete_item` | 10 | `Rewards::aero_disabled_delete_icon` | include/rewards.php:30 | Disable Aero mini cart delete |
| `pre_option_woocommerce_shipping_cost_requires_address` | 10 | `Rewards::disable_hide_shipping_method_until_address` | include/rewards.php:32 | Disable "hide until address" for rewards |
| `woocommerce_product_get_price` | 10000 | `Rewards::handle_reward_free_product` | include/rewards.php:36 | Set free gift price to 0 |
| `woocommerce_product_variation_get_price` | 10000 | `Rewards::handle_reward_free_product` | include/rewards.php:37 | Set variation gift price to 0 |
| `woocommerce_product_variation_get_regular_price` | 10000 | `Rewards::handle_reward_free_product` | include/rewards.php:38 | Set variation regular price to 0 |
| `fkcart_woocommerce_geolocate_ip` | 10 | `Rewards::pass_customer_geo_data` | include/rewards.php:41 | Pass billing address to geolocation |
| `woocommerce_checkout_fields` | 10 | `Rewards::set_blank_checkout_fields` | include/rewards.php:1096 | Blank checkout fields for free shipping |
| `woocommerce_checkout_get_value` | 10 | `Rewards::ensure_blank_checkout_fields` | include/rewards.php:1097 | Ensure fields stay blank |
| `wfacp_default_values` | 10 | `Rewards::do_no_set_default_value` | include/rewards.php:52 | Prevent Aero default values |

### Special Add-On Class

| Filter | Priority | Callback | File | Purpose |
|--------|----------|----------|------|---------|
| `fkcart_css_var_style` | 10 | `Special_Add_On::add_special_addon_css_variables` | include/special-add-on.php:21 | Add CSS variables |

---

## 3. Custom Actions (Extensibility)

These actions are triggered by the plugin for developer extensibility:

### fkcart_geolocation

```php
do_action('fkcart_geolocation', array $geolocation, string $ip_address);
```

**Purpose:** Fired when geolocation data is determined

**Parameters:**
- `$geolocation` (array): `['country' => '', 'state' => '', 'city' => '', 'postcode' => '']`
- `$ip_address` (string): IP address that was geolocated

**Location:** `include/geolocation.php:40, 60, 96, 145`

**Use Case:** Hook into geolocation to modify or log location data

---

### fkcart_spl_addon_before_add_to_cart

```php
do_action('fkcart_spl_addon_before_add_to_cart', array $_POST);
```

**Purpose:** Fired before special add-on is added to cart

**Parameters:**
- `$_POST` (array): POST data including product ID and action

**Location:** `include/special-add-on.php:165`

**Use Case:** Validate or modify add-on before cart addition

---

## 4. Custom Filters (Extensibility)

These filters allow developers to modify plugin behavior:

### fkcart_default_upsells

```php
$product_ids = apply_filters('fkcart_default_upsells', array $product_ids);
```

**Purpose:** Modify default upsell product IDs

**Default:** Product IDs from settings

**Location:** `include/upsells.php:296`

**Example:**
```php
add_filter('fkcart_default_upsells', function($ids) {
    // Add a specific product to defaults
    $ids[] = 123;
    return $ids;
});
```

---

### fkcart_reward_calculation_based_on

```php
$mode = apply_filters('fkcart_reward_calculation_based_on', string $calculation_mode);
```

**Purpose:** Override reward calculation base (subtotal vs total)

**Default:** Value from settings ('subtotal' or 'total')

**Location:** `include/rewards.php:902`

**Example:**
```php
add_filter('fkcart_reward_calculation_based_on', function($mode) {
    return 'subtotal'; // Always use subtotal
});
```

---

### fkcart_reward_total

```php
$total = apply_filters('fkcart_reward_total', float $total, string $calculation_mode, object $front);
```

**Purpose:** Modify the calculated cart total for reward evaluation

**Parameters:**
- `$total` (float): Calculated cart total
- `$calculation_mode` (string): 'subtotal' or 'total'
- `$front` (FKCart\Includes\Front): Front instance

**Location:** `include/rewards.php:911`

**Example:**
```php
add_filter('fkcart_reward_total', function($total, $mode, $front) {
    // Exclude specific product from reward calculation
    return $total - get_excluded_product_total();
}, 10, 3);
```

---

### fkcart_rewards_list

```php
$rewards = apply_filters('fkcart_rewards_list', array $rewards_data);
```

**Purpose:** Modify final rewards data before processing

**Parameters:**
- `$rewards_data` (array): Complete rewards array with achievements

**Location:** `include/rewards.php:637`

**Example:**
```php
add_filter('fkcart_rewards_list', function($data) {
    // Add custom data to rewards
    $data['custom_field'] = 'value';
    return $data;
});
```

---

### fkcart_reward_rules_checking

```php
$skip = apply_filters('fkcart_reward_rules_checking', bool $skip, array $reward);
```

**Purpose:** Conditionally disable specific rewards

**Default:** `false` (don't skip)

**Parameters:**
- `$skip` (bool): Whether to skip this reward
- `$reward` (array): Reward configuration

**Location:** `include/rewards.php:520`

**Example:**
```php
add_filter('fkcart_reward_rules_checking', function($skip, $reward) {
    // Skip discount rewards for certain users
    if ($reward['type'] === 'discount' && is_user_wholesale()) {
        return true;
    }
    return $skip;
}, 10, 2);
```

---

### fkcart_gift_products

```php
$gifts = apply_filters('fkcart_gift_products', array $gifts, array $rewards);
```

**Purpose:** Modify gift products to add/remove

**Parameters:**
- `$gifts` (array): `['add' => [], 'remove' => []]`
- `$rewards` (array): Full rewards data

**Location:** `include/rewards.php:208`

**Example:**
```php
add_filter('fkcart_gift_products', function($gifts, $rewards) {
    // Add conditional gift
    if (WC()->cart->get_subtotal() > 200) {
        $gifts['add'][] = 456;
    }
    return $gifts;
}, 10, 2);
```

---

### fkcart_free_shipping

```php
$shipping_data = apply_filters('fkcart_free_shipping', array|false $shipping_data);
```

**Purpose:** Modify free shipping configuration

**Parameters:**
- `$shipping_data` (array|false): `['min_amount' => X, 'method_id' => 'Y']` or false

**Location:** `include/rewards.php:490`

**Example:**
```php
add_filter('fkcart_free_shipping', function($data) {
    if ($data && is_user_vip()) {
        $data['min_amount'] = 0; // Free shipping always for VIP
    }
    return $data;
});
```

---

### fkcart_need_to_set_free_shipping_method

```php
$should_set = apply_filters('fkcart_need_to_set_free_shipping_method', bool $should_set);
```

**Purpose:** Control whether to auto-select free shipping method

**Default:** `true`

**Location:** `include/rewards.php:1010`

---

### fkcart_allow_wc_geolocate_filters

```php
$allow = apply_filters('fkcart_allow_wc_geolocate_filters', bool $allow);
```

**Purpose:** Enable WooCommerce native geolocation filters

**Default:** `false`

**Location:** `include/geolocation.php:28`

---

### fkcart_woocommerce_geolocate_ip

```php
$geolocation = apply_filters('fkcart_woocommerce_geolocate_ip', array $geolocation, string $ip_address);
```

**Purpose:** Override geolocation data

**Default:** Empty geolocation array

**Location:** `include/geolocation.php:29`

**Example:**
```php
add_filter('fkcart_woocommerce_geolocate_ip', function($geo, $ip) {
    // Use customer's saved address
    if (is_user_logged_in()) {
        $customer = WC()->customer;
        return [
            'country' => $customer->get_billing_country(),
            'state' => $customer->get_billing_state(),
            'city' => $customer->get_billing_city(),
            'postcode' => $customer->get_billing_postcode()
        ];
    }
    return $geo;
}, 10, 2);
```

---

### fkcart_set_geolocation_data_to_customer

```php
$should_set = apply_filters('fkcart_set_geolocation_data_to_customer', bool $should_set);
```

**Purpose:** Control auto-population of customer location from geolocation

**Default:** `true`

**Location:** `include/rewards.php:992`

---

### fkcart_allow_product_types

```php
$types = apply_filters('fkcart_allow_product_types', array $allowed_types);
```

**Purpose:** Modify allowed product types for upsells/search

**Default:** `['simple', 'variable', 'variation', 'variable-subscription', 'subscription']`

**Location:** `rest/conversions.php:550`

---

### fkcart_shipping_protection_learn_more

```php
$text = apply_filters('fkcart_shipping_protection_learn_more', string $text);
```

**Purpose:** Modify "Learn More" link text

**Default:** `__('Learn More', 'woocommerce')`

**Location:** `templates/cart/special-addon-html.php:223`

---

## 5. WooCommerce Hooks Used

### Cart Hooks

| Hook | Type | Purpose in Plugin |
|------|------|-------------------|
| `woocommerce_cart_loaded_from_session` | Action | Initialize free gift prices |
| `woocommerce_before_calculate_totals` | Action | Set free gift prices, add price filters |
| `woocommerce_calculate_totals` | Action | Apply/remove rewards |
| `woocommerce_cart_item_remove_link` | Filter | Hide delete for free gifts |
| `woocommerce_add_to_cart` | Action | Auto-add special add-on |
| `woocommerce_cart_emptied` | Action | Clear session data |
| `woocommerce_removed_coupon` | Action | Track removed coupons |

### Order Hooks

| Hook | Type | Purpose in Plugin |
|------|------|-------------------|
| `woocommerce_checkout_create_order` | Action | Store reward view data |
| `woocommerce_checkout_create_order_line_item` | Action | Add upsell/gift meta |
| `woocommerce_order_fully_refunded` | Action | Handle refund tracking |
| `woocommerce_order_partially_refunded` | Action | Handle partial refund |
| `woocommerce_delete_order` | Action | Clean up tracking data |

### Product Hooks

| Hook | Type | Purpose in Plugin |
|------|------|-------------------|
| `woocommerce_product_get_price` | Filter | Zero price for free gifts |
| `woocommerce_product_variation_get_price` | Filter | Zero price for gift variations |
| `woocommerce_product_variation_get_regular_price` | Filter | Zero regular price for gifts |

### Checkout Hooks

| Hook | Type | Purpose in Plugin |
|------|------|-------------------|
| `woocommerce_checkout_fields` | Filter | Blank fields for free shipping |
| `woocommerce_checkout_get_value` | Filter | Ensure fields blank |

### Geolocation Hooks

| Hook | Type | Purpose in Plugin |
|------|------|-------------------|
| `woocommerce_geolocate_ip` | Filter | Get country code (conditional) |
| `woocommerce_get_geolocation` | Filter | Get full geolocation data |
| `woocommerce_geolocation_geoip_apis` | Filter | API endpoints for geolocation |

### Settings Hooks

| Hook | Type | Purpose in Plugin |
|------|------|-------------------|
| `pre_option_woocommerce_shipping_cost_requires_address` | Filter | Disable hide shipping setting |

---

## 6. Hook Execution Order

### Cart Load Sequence

```
1. woocommerce_cart_loaded_from_session (98)
   └─ Rewards::update_free_gift - Set gift prices to 0

2. woocommerce_before_calculate_totals (90)
   └─ Anonymous function - Add price filters

3. woocommerce_before_calculate_totals (98)
   └─ Rewards::update_free_gift - Set gift prices to 0

4. woocommerce_calculate_totals (99)
   └─ Rewards::update_reward - Main reward processing
```

### Page Load Sequence

```
1. wp (10)
   └─ Rewards::maybe_remove_free_gifts - Clean invalid gifts

2. wp (22)
   └─ Rewards::update_choosen_shipping_method - Set shipping

3. wp_footer (10)
   └─ Special_Add_On::internal_style - Output CSS
```

### Add to Cart Sequence

```
1. woocommerce_add_to_cart (9999)
   └─ Special_Add_On::handle_special_addon_product
      └─ May add special add-on product

2. woocommerce_before_calculate_totals
   └─ (Cart recalculation triggers reward processing)
```

### Checkout/Order Sequence

```
1. woocommerce_checkout_create_order (10)
   └─ Upsells::update_reward_data_in_order - Store view data

2. woocommerce_checkout_create_order_line_item (999999)
   └─ Upsells::woocommerce_create_order_line_item - Add item meta
```

---

## Quick Reference: Hook Priorities

| Priority | Usage |
|----------|-------|
| 9 | REST API init |
| 10 | Default for most hooks |
| 11 | Exporter registration |
| 15 | Plugin initialization |
| 22 | Late `wp` hook for shipping |
| 90 | Early calculate totals for filters |
| 98 | Free gift price setting |
| 99 | Reward processing |
| 9999 | Special add-on add to cart |
| 10000 | Price filters for free gifts |
| 999999 | Order line item processing |

---

## Developer Tips

### Adding Custom Rewards

```php
// Add custom reward type processing
add_filter('fkcart_rewards_list', function($data) {
    foreach ($data['rewards'] as $key => $reward) {
        if ($reward['type'] === 'custom_type') {
            // Process custom reward
            if ($data['subtotal'] >= $reward['amount']) {
                // Reward achieved
                do_something_custom($reward);
            }
        }
    }
    return $data;
});
```

### Conditional Reward Disabling

```php
// Disable rewards for specific user roles
add_filter('fkcart_reward_rules_checking', function($skip, $reward) {
    if (current_user_can('wholesale_customer')) {
        return true; // Skip all rewards
    }
    return $skip;
}, 10, 2);
```

### Custom Upsell Sources

```php
// Add products from custom source to upsells
add_filter('fkcart_default_upsells', function($ids) {
    // Add recently viewed products
    $viewed = get_user_meta(get_current_user_id(), 'recently_viewed', true);
    if (is_array($viewed)) {
        $ids = array_merge($ids, array_slice($viewed, 0, 3));
    }
    return array_unique($ids);
});
```
