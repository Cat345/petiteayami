# Modification Patterns

> How to safely modify Cart for WooCommerce Pro

---

## Table of Contents

1. [Before You Modify](#1-before-you-modify)
2. [Common Modification Patterns](#2-common-modification-patterns)
3. [Adding New Features](#3-adding-new-features)
4. [Modifying Existing Features](#4-modifying-existing-features)
5. [Safe Modification Checklist](#5-safe-modification-checklist)
6. [Anti-Patterns to Avoid](#6-anti-patterns-to-avoid)
7. [Testing Guidelines](#7-testing-guidelines)

---

## 1. Before You Modify

### Pre-Modification Checklist

- [ ] Understand the feature's current behavior
- [ ] Identify all files involved (see [FILE_MAP.md](./FILE_MAP.md))
- [ ] Check hook dependencies (see [HOOKS_REFERENCE.md](./HOOKS_REFERENCE.md))
- [ ] Verify license validation requirements
- [ ] Consider WooCommerce session state
- [ ] Plan for both frontend and admin impacts

### Key Questions to Answer

1. **Does this feature require license validation?**
   ```php
   if (Plugin::valid_l() === false) {
       return []; // Pro features gated by license
   }
   ```

2. **Does this interact with WC session?**
   ```php
   if (is_null(WC()->cart) || is_null(WC()->session)) {
       return; // Always check WC availability
   }
   ```

3. **Is this hook priority-sensitive?**
   - Check existing priorities in [HOOKS_REFERENCE.md](./HOOKS_REFERENCE.md)
   - Reward processing happens at priority 99
   - Order item meta at priority 999999

---

## 2. Common Modification Patterns

### Pattern 1: Adding a New Setting

**Step 1:** Settings are stored in base plugin via `FKCart\Includes\Data`

**Step 2:** Access the setting:
```php
$my_setting = \FKCart\Includes\Data::get_value('my_new_setting');
```

**Step 3:** Use with default fallback:
```php
$value = !empty($settings['my_setting']) ? $settings['my_setting'] : 'default';
```

---

### Pattern 2: Adding New Session Data

**Store data:**
```php
if (!is_null(WC()->session)) {
    WC()->session->set('_fkcart_my_key', $value);
}
```

**Retrieve data:**
```php
$value = WC()->session->get('_fkcart_my_key', $default);
```

**Clear on cart empty:**
```php
// In your class constructor
add_action('woocommerce_cart_emptied', [$this, 'clear_my_session']);

public function clear_my_session() {
    if (!is_null(WC()->session)) {
        WC()->session->__unset('_fkcart_my_key');
    }
}
```

---

### Pattern 3: Adding Order Meta

**During checkout:**
```php
add_action('woocommerce_checkout_create_order', [$this, 'add_my_order_meta']);

public function add_my_order_meta($order) {
    if (!$order instanceof \WC_Order) {
        return;
    }
    $order->add_meta_data('_fkcart_my_meta', $value);
}
```

**To order line items:**
```php
add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_item_meta'], 999999, 3);

public function add_item_meta($item, $cart_item_key, $values) {
    if (isset($values['_my_cart_item_data'])) {
        $item->add_meta_data('_my_order_item_meta', 'yes');
    }
}
```

---

### Pattern 4: Creating a New Singleton Class

```php
<?php
namespace FKCart\Pro;

if (!class_exists('\FKCart\Pro\MyFeature')) {
    #[\AllowDynamicProperties]
    class MyFeature {
        private static $instance = null;

        private function __construct() {
            // Check if cart is enabled
            $data = \FKCart\Includes\Data::get_db_settings();
            if (!isset($data['enable_cart']) || 0 === intval($data['enable_cart'])) {
                return false;
            }

            // Register hooks
            add_action('my_hook', [$this, 'my_callback']);
        }

        public static function getInstance() {
            if (is_null(self::$instance)) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        public function my_callback() {
            // Validate license for pro features
            if (Plugin::valid_l() === false) {
                return;
            }

            // Check WC availability
            if (is_null(WC()->cart) || is_null(WC()->session)) {
                return;
            }

            // Your logic here
        }
    }
}
```

**Register in plugin.php:**
```php
public function include_core() {
    // ... existing includes ...
    include __DIR__ . '/include/my-feature.php';
    MyFeature::getInstance();
}
```

---

### Pattern 5: Adding a REST Endpoint

**In rest/conversions.php or new file:**
```php
private function order_end_points() {
    // ... existing routes ...

    register_rest_route($this->namespace, '/fkcart-my-endpoint/', array(
        'methods'             => \WP_REST_Server::READABLE, // or CREATABLE
        'callback'            => array($this, 'my_endpoint_handler'),
        'permission_callback' => array($this, 'get_read_api_permission_check'),
        'args'                => [],
    ));
}

public function my_endpoint_handler($request) {
    // Get parameters
    $param = isset($request['param']) ? sanitize_text_field($request['param']) : '';

    // Process
    $result = $this->process_something($param);

    // Return response
    return rest_ensure_response([
        'status' => true,
        'data'   => $result
    ]);
}
```

---

### Pattern 6: Adding a Filter for Extensibility

**Create the filter:**
```php
$my_data = apply_filters('fkcart_my_feature_data', $default_data, $context);
```

**Document it in HOOKS_REFERENCE.md:**
```markdown
### fkcart_my_feature_data

Purpose: Allow modification of my feature data
Parameters:
- $data (mixed): The data being filtered
- $context (mixed): Additional context

Example:
​```php
add_filter('fkcart_my_feature_data', function($data, $context) {
    // Modify data
    return $data;
}, 10, 2);
​```
```

---

## 3. Adding New Features

### Step-by-Step: New Pro Feature

1. **Create the class file:**
   ```
   include/my-feature.php
   ```

2. **Follow the singleton pattern** (see Pattern 4 above)

3. **Add license check:**
   ```php
   if (Plugin::valid_l() === false) {
       return;
   }
   ```

4. **Register in plugin.php:**
   ```php
   include __DIR__ . '/include/my-feature.php';
   MyFeature::getInstance();
   ```

5. **Add to FILE_MAP.md documentation**

6. **Add hooks to HOOKS_REFERENCE.md**

---

### Step-by-Step: New Template

1. **Create template file:**
   ```
   templates/cart/my-template.php
   ```

2. **Use base plugin's template function:**
   ```php
   fkcart_get_template_part('cart/my-template', '', $args, true, FKCART_PRO_PATH);
   ```

3. **Hook into appropriate location:**
   ```php
   add_action('fkcart_after_coupon_section', [$this, 'render_my_template']);

   public function render_my_template($settings) {
       if (Plugin::valid_l() === false) {
           return;
       }
       fkcart_get_template_part('cart/my-template', '', [], true, FKCART_PRO_PATH);
   }
   ```

---

### Step-by-Step: New Database Column

> **Note:** Database schema changes should be in the base plugin. Pro module uses existing tables.

If absolutely necessary:

1. **Check column exists:**
   ```php
   private function column_exists($column_name) {
       global $wpdb;
       $table = $wpdb->prefix . 'fk_cart';
       $column = $wpdb->get_results($wpdb->prepare(
           "SHOW COLUMNS FROM `{$table}` LIKE %s",
           $column_name
       ));
       return !empty($column);
   }
   ```

2. **Use conditional queries:**
   ```php
   $select = $this->column_exists('my_column') ? "c.my_column," : "";
   ```

---

## 4. Modifying Existing Features

### Modifying Upsell Logic

**To change product selection:**
```php
// In include/upsells.php, modify get_upsell_ids()

// Or use the filter:
add_filter('fkcart_default_upsells', function($ids) {
    // Add/remove/reorder IDs
    return $ids;
});
```

**To change display count:**
```php
// Modify in get_upsell_products()
$max_upsell = Data::get_value('upsell_max_count');
// Or override programmatically before the check
```

---

### Modifying Reward Calculation

**To change what's included in total:**
```php
add_filter('fkcart_reward_total', function($total, $mode, $front) {
    // Subtract excluded products
    // Add bonus amounts
    return $total;
}, 10, 3);
```

**To skip specific rewards:**
```php
add_filter('fkcart_reward_rules_checking', function($skip, $reward) {
    if ($reward['type'] === 'discount' && some_condition()) {
        return true; // Skip this reward
    }
    return $skip;
}, 10, 2);
```

---

### Modifying Special Add-On

**To change auto-add behavior:**
```php
// Modify handle_special_addon_product() in special-add-on.php
// Key checks:
// - $enable_special_addon
// - $preselect_special_addon
// - $is_remove_addons session flag
```

**To change styling:**
```php
// Option 1: Modify internal_style() method
// Option 2: Use CSS variables filter
add_filter('fkcart_css_var_style', function($styles) {
    $styles .= ':root { --my-var: value; }';
    return $styles;
});
```

---

## 5. Safe Modification Checklist

### Before Committing

- [ ] **License check** included for pro features
- [ ] **WC availability** checked before using WC()
- [ ] **Session checks** before set/get operations
- [ ] **Prepared statements** for all database queries
- [ ] **Input sanitization** for all user inputs
- [ ] **Output escaping** in templates
- [ ] **Hook priorities** don't conflict
- [ ] **Error handling** with try/catch where needed
- [ ] **Backward compatibility** maintained
- [ ] **Documentation updated** (HOOKS_REFERENCE, FILE_MAP)

### Code Review Questions

1. Does this break if WC session isn't available?
2. Does this handle refunds correctly?
3. Does this work with variable products?
4. Does this respect the license state?
5. Is the hook priority appropriate?
6. Are database queries optimized?
7. Is caching used where appropriate?

---

## 6. Anti-Patterns to Avoid

### ❌ Don't: Skip License Validation

```php
// BAD - Pro feature without license check
public function pro_feature() {
    return $this->do_pro_stuff();
}

// GOOD - Always validate license
public function pro_feature() {
    if (Plugin::valid_l() === false) {
        return [];
    }
    return $this->do_pro_stuff();
}
```

---

### ❌ Don't: Assume WC is Available

```php
// BAD - Direct access without check
$cart_total = WC()->cart->get_total();

// GOOD - Always check first
if (is_null(WC()->cart)) {
    return;
}
$cart_total = WC()->cart->get_total();
```

---

### ❌ Don't: Use Raw SQL

```php
// BAD - SQL injection risk
$wpdb->query("SELECT * FROM table WHERE id = $id");

// GOOD - Use prepared statements
$wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}fk_cart WHERE oid = %d",
    $id
));
```

---

### ❌ Don't: Echo Unescaped Data in Templates

```php
// BAD - XSS vulnerability
<div><?php echo $user_input; ?></div>

// GOOD - Always escape
<div><?php echo esc_html($user_input); ?></div>
<a href="<?php echo esc_url($url); ?>">Link</a>
<div class="<?php echo esc_attr($class); ?>"></div>
```

---

### ❌ Don't: Ignore Hook Priorities

```php
// BAD - May run before rewards are calculated
add_action('woocommerce_calculate_totals', [$this, 'my_func'], 10);

// GOOD - Run after reward processing (priority 99)
add_action('woocommerce_calculate_totals', [$this, 'my_func'], 100);
```

---

### ❌ Don't: Forget Session Cleanup

```php
// BAD - Session data persists forever
WC()->session->set('_fkcart_my_data', $data);

// GOOD - Clean up when appropriate
add_action('woocommerce_cart_emptied', function() {
    WC()->session->__unset('_fkcart_my_data');
});
```

---

## 7. Testing Guidelines

### Manual Testing Checklist

#### Cart Upsells
- [ ] Upsells display correctly
- [ ] Clicking upsell adds to cart
- [ ] Order shows upsell meta
- [ ] Refund updates tracking

#### Rewards
- [ ] Progress bar updates on cart change
- [ ] Free shipping applies at threshold
- [ ] Discount coupon auto-applies
- [ ] Free gift added to cart
- [ ] Removing coupon manually prevents re-add
- [ ] Works with different calculation modes

#### Special Add-On
- [ ] Toggle adds/removes product
- [ ] Pre-select works on first add
- [ ] Variable products show variation selector
- [ ] Styling displays correctly
- [ ] WPML product mapping works

#### Analytics
- [ ] Conversion list loads
- [ ] Filters work correctly
- [ ] Overview stats calculate correctly
- [ ] Export generates valid CSV

### Edge Cases to Test

1. **Empty cart** - Features should handle gracefully
2. **Guest vs logged in** - Session handling differs
3. **Variable products** - Variation handling
4. **Out of stock** - Product availability checks
5. **Refunds** - Partial and full
6. **WPML/Polylang** - Product ID mapping
7. **No license** - Features should be disabled
8. **Expired license** - Grace period handling

### Database Testing

```sql
-- Verify data integrity
SELECT c.oid, COUNT(cp.id) as products
FROM wp_fk_cart c
LEFT JOIN wp_fk_cart_products cp ON c.oid = cp.oid
GROUP BY c.oid
HAVING products = 0; -- Should be empty or intentional

-- Check for orphaned records
SELECT cp.* FROM wp_fk_cart_products cp
LEFT JOIN wp_fk_cart c ON cp.oid = c.oid
WHERE c.oid IS NULL; -- Should be empty
```
