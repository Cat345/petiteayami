# Security Checklist

> Security requirements and best practices for Cart for WooCommerce Pro

---

## Table of Contents

1. [Security Overview](#1-security-overview)
2. [Input Validation](#2-input-validation)
3. [Output Escaping](#3-output-escaping)
4. [SQL Security](#4-sql-security)
5. [Authentication & Authorization](#5-authentication--authorization)
6. [Session Security](#6-session-security)
7. [File Operations](#7-file-operations)
8. [External API Security](#8-external-api-security)
9. [Security Audit Checklist](#9-security-audit-checklist)
10. [Vulnerability Patterns to Avoid](#10-vulnerability-patterns-to-avoid)

---

## 1. Security Overview

### Security Measures in Place

| Area | Implementation |
|------|----------------|
| SQL Injection | All queries use `$wpdb->prepare()` |
| XSS | Template output uses `esc_*` functions |
| CSRF | REST API uses capability checks |
| Access Control | REST endpoints check permissions |
| Input Validation | `sanitize_*`, `absint()`, `intval()` |

### Security Dependencies

- WooCommerce nonce system
- WordPress capability system
- FunnelKit REST API permission helpers

---

## 2. Input Validation

### Required Sanitization Functions

| Data Type | Function | Example |
|-----------|----------|---------|
| Text strings | `sanitize_text_field()` | User search input |
| Integers | `absint()` or `intval()` | Product IDs |
| Arrays | `array_map('absint', $array)` | ID arrays |
| Email | `sanitize_email()` | Email addresses |
| URLs | `esc_url_raw()` | URLs for storage |
| HTML | `wp_kses_post()` | Rich text content |
| File names | `sanitize_file_name()` | Uploaded files |

### Current Implementation Examples

**REST Parameter Sanitization:**
```php
// rest/conversions.php
$filters = array(
    'search'      => isset($request['s']) ? sanitize_text_field($request['s']) : '',
    'limit'       => isset($request['limit']) ? intval($request['limit']) : get_option('posts_per_page'),
    'offset'      => isset($request['offset']) ? intval($request['offset']) : 0,
    'page_no'     => isset($request['page_no']) ? intval($request['page_no']) : 1,
);
```

**Product ID Sanitization:**
```php
// include/special-add-on.php
$product_id = absint($_POST['fkcart_spl_product_id']);
$product_id = self::get_map_product($product_id);
```

**Date Sanitization:**
```php
$date_after = sanitize_text_field($filter['data']['after']);
$date_before = sanitize_text_field($filter['data']['before']);
```

### Validation Checklist

- [ ] All `$_POST` data sanitized before use
- [ ] All `$_GET` data sanitized before use
- [ ] All REST request parameters validated
- [ ] Numeric values cast to int/float
- [ ] Arrays validated for expected structure
- [ ] Empty checks performed where needed

---

## 3. Output Escaping

### Required Escaping Functions

| Context | Function | Example |
|---------|----------|---------|
| HTML content | `esc_html()` | `<div><?php echo esc_html($text); ?></div>` |
| HTML attributes | `esc_attr()` | `<div class="<?php echo esc_attr($class); ?>">` |
| URLs | `esc_url()` | `<a href="<?php echo esc_url($url); ?>">` |
| JavaScript | `esc_js()` | `onclick="func('<?php echo esc_js($val); ?>')">` |
| Textarea | `esc_textarea()` | `<textarea><?php echo esc_textarea($content); ?></textarea>` |

### Current Implementation Examples

**Template Escaping:**
```php
// templates/cart/special-addon-html.php
<div class="<?php echo esc_attr(implode(' ', $base_class)); ?>"
     id="fkcart-spl-addon"
     data-fkcart-product-id='<?php echo esc_attr($special_addon_product_id); ?>'>

<a target="_blank" href="<?php echo esc_url(get_the_permalink($special_addon_product_id)); ?>">
```

### ⚠️ Areas Needing Review

Some template outputs may need additional escaping:
```php
// Should be reviewed:
<?php echo $special_addon_heading; ?>
<?php echo $special_addon_desc; ?>
<?php echo $variable_meta; ?>
<?php echo $special_addon_product_price; ?>
```

**Recommended fixes:**
```php
<?php echo esc_html($special_addon_heading); ?>
<?php echo wp_kses_post($special_addon_desc); ?>
<?php echo wp_kses_post($variable_meta); ?>
<?php echo wp_kses_post($special_addon_product_price); // WC price HTML ?>
```

---

## 4. SQL Security

### Prepared Statements

**Always use `$wpdb->prepare()` for queries with variables:**

```php
// CORRECT - Using prepared statement
$wpdb->get_row($wpdb->prepare(
    "SELECT id, price FROM {$wpdb->prefix}fk_cart_products
     WHERE type = %d AND product_id = %d AND oid = %d",
    $type, $product_id, $order_id
), ARRAY_A);

// CORRECT - LIKE query escaping
$search_term = '%' . $wpdb->esc_like($filters['search']) . '%';
$wpdb->prepare("WHERE oid LIKE %s", $search_term);
```

### Current Query Patterns

**Select with prepared statement:**
```php
// rest/conversions.php
$wpdb->get_results($wpdb->prepare(
    "SELECT c.oid AS order_id...
     WHERE c.date_created BETWEEN %s AND %s
     LIMIT %d OFFSET %d",
    array_merge($where_args, [$offset, $limit])
));
```

**Update with prepared statement:**
```php
// include/fkcart-db-migrator.php
$wpdb->update(
    "{$wpdb->prefix}fk_cart",
    ['onumber' => $onumber],  // Data
    ['oid' => $item['oid']]   // Where
);
```

**Delete with prepared statement:**
```php
// include/upsells.php
$wpdb->delete(
    $wpdb->prefix . 'fk_cart_products',
    array('oid' => $order_id)
);
```

### SQL Security Checklist

- [ ] All SELECT queries use `$wpdb->prepare()`
- [ ] All INSERT queries use `$wpdb->insert()` or prepared
- [ ] All UPDATE queries use `$wpdb->update()` or prepared
- [ ] All DELETE queries use `$wpdb->delete()` or prepared
- [ ] LIKE patterns use `$wpdb->esc_like()`
- [ ] Table names use `$wpdb->prefix`
- [ ] No direct variable interpolation in SQL

---

## 5. Authentication & Authorization

### REST API Permission Checks

```php
// rest/conversions.php
public function get_read_api_permission_check() {
    if (!function_exists('wffn_rest_api_helpers')) {
        return current_user_can('administrator');
    }
    return wffn_rest_api_helpers()->get_api_permission_check('analytics', 'read');
}

public function get_write_api_permission_check() {
    if (!function_exists('wffn_rest_api_helpers')) {
        return current_user_can('administrator');
    }
    return wffn_rest_api_helpers()->get_api_permission_check('analytics', 'write');
}
```

### Permission Levels

| Endpoint | Permission Required |
|----------|---------------------|
| GET `/fkcart-conversions/` | `analytics:read` |
| GET `/fkcart-overview/` | `analytics:read` |
| GET `/fkcart-popular-upsells/` | `analytics:read` |
| POST `/fkcart-migrate-data/` | `analytics:write` |
| POST `/cart-conversions/export/add` | `analytics:write` |

### Authorization Checklist

- [ ] All REST endpoints have permission callbacks
- [ ] Admin-only operations check `current_user_can()`
- [ ] License validation for pro features
- [ ] Capability fallback to `administrator` when helper unavailable

---

## 6. Session Security

### WooCommerce Session Usage

**Session data is stored server-side by WooCommerce:**

```php
// Store in session
WC()->session->set('_fkcart_upsell_views', $views);

// Retrieve from session
$views = WC()->session->get('_fkcart_upsell_views', []);

// Remove from session
WC()->session->__unset('_fkcart_upsell_views');
```

### Session Security Best Practices

1. **Always check session availability:**
   ```php
   if (is_null(WC()->session)) {
       return;
   }
   ```

2. **Use prefixed keys:**
   - All keys start with `_fkcart_`
   - Prevents collision with other plugins

3. **Clean up on cart empty:**
   ```php
   add_action('woocommerce_cart_emptied', [$this, 'cleanup_session']);
   ```

4. **Don't store sensitive data:**
   - Session stores product IDs, not customer data
   - No PII in session keys

### Session Security Checklist

- [ ] All session operations check `WC()->session` availability
- [ ] Session keys use plugin prefix
- [ ] Session cleaned on cart empty
- [ ] No sensitive data stored in session
- [ ] Session data validated on retrieval

---

## 7. File Operations

### CSV Export Security

```php
// include/fkcart-export-cart-conversion.php
$file = fopen(WFFN_PRO_EXPORT_DIR . '/' . $this->export_meta['file'], "a");
// ... write data ...
fclose($file);
```

### File Security Considerations

1. **Export directory protection:**
   - Handled by WFFN Pro Core
   - Should have `.htaccess` protection
   - Should be outside web root or protected

2. **File name sanitization:**
   - File names generated by export system
   - Should not contain user input

3. **Path traversal prevention:**
   - Use constants for directories
   - Never concatenate user input to paths

### File Security Checklist

- [ ] Export directory has proper permissions
- [ ] File names don't include user input
- [ ] No path traversal vulnerabilities
- [ ] Temporary files cleaned up
- [ ] Directory traversal prevented

---

## 8. External API Security

### Geolocation API Calls

```php
// include/geolocation.php
$response = wp_safe_remote_get(
    sprintf($service_endpoint, $ip_address),
    array(
        'timeout'    => 2,
        'user-agent' => 'WooCommerce/' . wc()->version,
    )
);
```

### External API Security Measures

1. **Use `wp_safe_remote_get()`:**
   - Validates URL
   - Blocks internal/localhost requests
   - Safer than `wp_remote_get()`

2. **Timeout limit:**
   - 2-second timeout prevents hanging
   - Protects against slow responses

3. **Response validation:**
   ```php
   if (is_wp_error($response) || empty($response['body'])) {
       continue; // Skip invalid responses
   }
   ```

4. **Data caching:**
   - Geolocation cached for 24 hours
   - Reduces external API calls
   - Stored in transients

### External API Checklist

- [ ] Use `wp_safe_remote_*` functions
- [ ] Set reasonable timeouts
- [ ] Validate API responses
- [ ] Handle errors gracefully
- [ ] Cache responses appropriately
- [ ] Don't expose API keys in code

---

## 9. Security Audit Checklist

### Pre-Release Security Review

#### Input Handling
- [ ] All user input sanitized
- [ ] Type validation for expected data types
- [ ] Empty/null checks where needed
- [ ] Array structure validation

#### Output Handling
- [ ] All dynamic output escaped
- [ ] Context-appropriate escaping used
- [ ] HTML in database escaped on output

#### Database Security
- [ ] No direct SQL with variables
- [ ] All queries use prepared statements
- [ ] Table operations use WordPress functions
- [ ] LIKE queries properly escaped

#### Authentication
- [ ] All endpoints check permissions
- [ ] Capability checks for admin functions
- [ ] License validation for pro features

#### Session Management
- [ ] Session availability checked
- [ ] Data cleaned on appropriate actions
- [ ] No sensitive data in sessions

#### External Communications
- [ ] Safe remote functions used
- [ ] Timeouts configured
- [ ] Responses validated

---

## 10. Vulnerability Patterns to Avoid

### ❌ SQL Injection

```php
// VULNERABLE - Never do this
$wpdb->query("SELECT * FROM table WHERE id = $user_input");
$wpdb->query("SELECT * FROM table WHERE name = '$user_input'");

// SAFE - Always do this
$wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}table WHERE id = %d",
    $user_input
));
```

---

### ❌ Cross-Site Scripting (XSS)

```php
// VULNERABLE - Never do this
echo $_GET['search'];
echo $user_data;
<div class="<?php echo $class; ?>">

// SAFE - Always do this
echo esc_html(sanitize_text_field($_GET['search']));
echo esc_html($user_data);
<div class="<?php echo esc_attr($class); ?>">
```

---

### ❌ Unauthorized Access

```php
// VULNERABLE - No permission check
public function admin_action() {
    // Do admin stuff
}

// SAFE - Check permissions
public function admin_action() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }
    // Do admin stuff
}
```

---

### ❌ Path Traversal

```php
// VULNERABLE - User input in path
$file = $_GET['file'];
include("/path/to/templates/" . $file);

// SAFE - Whitelist allowed files
$allowed = ['template1.php', 'template2.php'];
if (in_array($file, $allowed, true)) {
    include("/path/to/templates/" . $file);
}
```

---

### ❌ Object Injection

```php
// VULNERABLE - Unserializing user data
$data = unserialize($_POST['data']);

// SAFE - Use JSON or validate
$data = json_decode($_POST['data'], true);
// Or use allowed_classes in PHP 7+
$data = unserialize($input, ['allowed_classes' => false]);
```

---

### ❌ Information Disclosure

```php
// VULNERABLE - Exposing errors
try {
    // code
} catch (Exception $e) {
    echo $e->getMessage(); // May expose sensitive info
}

// SAFE - Log errors, show generic message
try {
    // code
} catch (Exception $e) {
    error_log($e->getMessage());
    // Show generic error to user
}
```

---

## Security Contacts

If you discover a security vulnerability:

1. **Do not** disclose publicly
2. Contact FunnelKit security team
3. Provide detailed reproduction steps
4. Allow reasonable time for fix before disclosure
