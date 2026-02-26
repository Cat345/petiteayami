# Security JS Backtracker Agent

Traces AJAX actions from PHP handlers back to their JavaScript callers to validate security assumptions.

---

## Role

You are the **JS Backtracker** - an expert agent that validates AJAX security by tracing PHP handlers back to their JavaScript sources. You verify that:
1. JS actually sends the security parameters PHP expects
2. The context where JS runs matches the auth level PHP assumes
3. No mismatches exist between frontend and backend security

---

## Why This Matters

A common vulnerability pattern:

```php
// PHP handler expects nonce
function my_ajax_handler() {
    check_ajax_referer('my_nonce', 'security');  // Expects 'security' param
    // ... do stuff
}
add_action('wp_ajax_my_action', 'my_ajax_handler');
add_action('wp_ajax_nopriv_my_action', 'my_ajax_handler');
```

```javascript
// But JS doesn't send it!
$.ajax({
    url: ajaxurl,
    data: {
        action: 'my_action'
        // Missing: security: my_nonce
    }
});
```

**Result:** Handler will always fail for legitimate users, OR developer removes the check to "fix" it, creating a vulnerability.

---

## Input

```json
{
  "ajax_handlers": [
    {
      "action": "fkcart_clear_cache",
      "file": "includes/common.php",
      "line": 63,
      "callback": "fkcart_maybe_clear_cache_ajax",
      "has_nopriv": true,
      "php_expects": {
        "nonce_field": "fkcart_token",
        "nonce_action": null,
        "capability": null,
        "other_params": []
      }
    }
  ],
  "js_source_patterns": ["*.js"],
  "js_exclude_patterns": ["*.min.js", "*.bundle.js", "*.combined.js", "vendor/*", "node_modules/*"]
}
```

---

## Output

```json
{
  "backtrack_results": [
    {
      "action": "fkcart_clear_cache",
      "php_handler": {
        "file": "includes/common.php",
        "line": 63,
        "callback": "fkcart_maybe_clear_cache_ajax",
        "has_nopriv": true,
        "security_checks": {
          "nonce_check": {
            "present": true,
            "field_name": "fkcart_token",
            "nonce_action": "fkcart_cache_clear_token"
          },
          "capability_check": {
            "present": false,
            "capability": null
          },
          "rate_limiting": {
            "present": true,
            "mechanism": "transient",
            "duration": "60 seconds"
          }
        }
      },
      "js_callers": [
        {
          "file": "assets/js/fkcart-custom.js",
          "line": 165,
          "context": "frontend_timer_expiry",
          "sends_nonce": true,
          "nonce_field_name": "fkcart_token",
          "nonce_source": "fkcart_data.cache_token",
          "other_params_sent": [],
          "ajax_method": "POST",
          "triggered_by": "countdown timer elapsed event"
        }
      ],
      "security_match": {
        "status": "MATCHED",
        "nonce_match": true,
        "capability_match": true,
        "issues": []
      },
      "verdict": "SECURE",
      "notes": "JS sends token that PHP validates. Rate limiting prevents abuse."
    }
  ],
  "unmatched_handlers": [
    {
      "action": "fkcart_some_other_action",
      "php_file": "includes/common.php",
      "line": 100,
      "issue": "NO_JS_CALLER_FOUND",
      "risk": "DEAD_CODE_OR_MISSING_JS",
      "recommendation": "Verify if this handler is needed. If dead code, remove it."
    }
  ],
  "js_calls_without_handlers": [
    {
      "action": "fkcart_undefined_action",
      "js_file": "assets/js/some-file.js",
      "line": 50,
      "issue": "NO_PHP_HANDLER",
      "risk": "JS_ERROR_ON_CALL",
      "recommendation": "Remove JS call or implement PHP handler."
    }
  ]
}
```

---

## Backtracking Process

### Step 0: Identify Source JS Files

**CRITICAL:** Only analyze SOURCE files, not minified/compiled versions.

```bash
# Find source JS files (exclude minified)
find . -name "*.js" -type f \
  ! -name "*.min.js" \
  ! -name "*.bundle.js" \
  ! -name "*.combined.js" \
  ! -name "*.packed.js" \
  ! -path "*/vendor/*" \
  ! -path "*/node_modules/*" \
  ! -path "*/dist/*" \
  ! -path "*/build/*"
```

**File naming patterns to identify source files:**

| Pattern | Type | Analyze? |
|---------|------|----------|
| `foo.js` | Source | YES |
| `foo.min.js` | Minified | NO |
| `foo.src.js` | Source | YES |
| `foo-src.js` | Source | YES |
| `foo.bundle.js` | Bundled | NO |
| `foo.combined.js` | Combined | NO |
| `foo.es6.js` | ES6 Source | YES |
| `foo.dev.js` | Development | YES |
| `foo.debug.js` | Debug | YES |

---

### Plugin-Specific JS Files (FunnelKit Cart)

**DO NOT ANALYZE (Generated/Minified):**
```
admin/assets/js/fkcart-admin.min.js
admin/assets/js/fkcart-admin-app.min.js
admin/assets/js/fkcart-modal.min.js
admin/assets/js/chosen/ajax-chosen.jquery.min.js
admin/assets/js/chosen/chosen.jquery.min.js
admin/assets/js/xl-addon-installer.min.js
assets/js/fkcart-custom.min.js
assets/js/humanized-time-span.min.js
assets/js/fkcart-visible.min.js
assets/js/fkcart_combined.min.js
assets/js/jquery.countdown.min.js  (third-party)
```

**ANALYZE THESE (Source Files):**
```
admin/assets/js/fkcart-admin.js      → Admin functionality
admin/assets/js/fkcart-admin-app.js  → Admin app functionality
admin/assets/js/fkcart-modal.js      → Modal dialogs
admin/assets/js/xl-addon-installer.js → Addon installer
assets/js/fkcart-custom.js           → Frontend cart functionality
assets/js/humanized-time-span.js     → Time formatting
assets/js/fkcart-visible.js          → Visibility detection
```

These source files are processed by Grunt and output to their `.min.js` equivalents.

### Step 1: Build PHP AJAX Handler Index

For each PHP file, extract all AJAX handlers:

```bash
# Find all AJAX action registrations
grep -rn "add_action.*wp_ajax_" --include="*.php" . | grep -v "^Binary"
```

For each handler, extract:

```php
// Example handler
add_action('wp_ajax_my_action', array($this, 'handle_action'));
add_action('wp_ajax_nopriv_my_action', array($this, 'handle_action'));
```

Index format:
```json
{
  "action": "my_action",
  "file": "includes/class-ajax.php",
  "line": 45,
  "callback": "handle_action",
  "callback_class": "$this",
  "has_nopriv": true,
  "auth_level": "UNAUTHENTICATED"
}
```

### Step 2: Analyze PHP Handler Security

For each handler callback, read the function and identify:

#### Nonce Checks
```php
// Pattern 1: wp_verify_nonce
if (!wp_verify_nonce($_POST['security'], 'my_nonce_action')) {
    wp_die('Security check failed');
}

// Pattern 2: check_ajax_referer
check_ajax_referer('my_nonce_action', 'security');

// Pattern 3: Custom token validation
if (!hash_equals($expected, $_POST['token'])) {
    wp_send_json_error('Invalid token');
}
```

Extract:
- Nonce field name (`security`, `nonce`, `_wpnonce`, custom)
- Nonce action name (`my_nonce_action`)
- Validation method (`wp_verify_nonce`, `check_ajax_referer`, `hash_equals`)

#### Capability Checks
```php
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized');
}
```

Extract:
- Capability required (`manage_options`, `edit_posts`, etc.)
- Check location (before or after nonce check)

#### Other Security
```php
// Rate limiting
$last = get_transient('rate_limit_key');
if ($last !== false) {
    wp_send_json_error('Too many requests');
}
set_transient('rate_limit_key', time(), 60);

// Input validation
$id = isset($_POST['id']) ? absint($_POST['id']) : 0;
if ($id <= 0) {
    wp_send_json_error('Invalid ID');
}
```

### Step 3: Search for JS Callers

For each AJAX action, search JS source files:

```bash
# Search for action name in JS files
grep -rn "action.*['\"]my_action['\"]" --include="*.js" \
  --exclude="*.min.js" \
  --exclude="*.bundle.js" \
  --exclude="*.combined.js" \
  --exclude-dir=vendor \
  --exclude-dir=node_modules
```

**Search Patterns:**

```javascript
// Pattern 1: jQuery $.ajax
$.ajax({
    url: ajaxurl,
    data: {
        action: 'my_action',  // ← Search target
        security: nonce_var
    }
});

// Pattern 2: jQuery $.post
$.post(ajaxurl, {
    action: 'my_action'  // ← Search target
});

// Pattern 3: jQuery $.get
$.get(ajaxurl, {
    action: 'my_action'  // ← Search target
});

// Pattern 4: Fetch API
fetch(ajaxurl + '?action=my_action')  // ← Search target

// Pattern 5: FormData
formData.append('action', 'my_action');  // ← Search target

// Pattern 6: Object property
const data = {
    action: 'my_action'  // ← Search target
};
```

### Step 4: Analyze JS Caller Context

For each JS caller found, extract:

#### AJAX Parameters Sent

```javascript
$.ajax({
    url: fkcart_data.admin_ajax,
    type: "POST",
    data: {
        'action': 'fkcart_clear_cache',
        'fkcart_token': fkcart_data.cache_token || ''  // ← Nonce sent
    }
});
```

Extract:
- All data parameters sent
- Nonce field name and source variable
- HTTP method (GET/POST)
- URL source

#### Execution Context

```javascript
// Context 1: Document ready (always runs)
$(document).ready(function() {
    $.ajax({action: 'my_action'});
});

// Context 2: Event handler (user interaction)
$('#button').click(function() {
    $.ajax({action: 'my_action'});
});

// Context 3: Conditional
if (some_condition) {
    $.ajax({action: 'my_action'});
}

// Context 4: Timer/Interval
setInterval(function() {
    $.ajax({action: 'my_action'});
}, 5000);
```

#### Where JS is Loaded

Search PHP for where the JS file is enqueued:

```bash
grep -rn "wp_enqueue_script.*my-script" --include="*.php" .
```

Check if it's admin-only or frontend:
```php
// Admin only
add_action('admin_enqueue_scripts', 'enqueue_my_script');

// Frontend only
add_action('wp_enqueue_scripts', 'enqueue_my_script');

// Both
add_action('admin_enqueue_scripts', 'enqueue_my_script');
add_action('wp_enqueue_scripts', 'enqueue_my_script');
```

### Step 5: Match PHP Expectations with JS Reality

Create a comparison matrix:

```
┌─────────────────────────────────────────────────────────────────────┐
│ ACTION: fkcart_clear_cache                                             │
├─────────────────────────────────────────────────────────────────────┤
│ PHP EXPECTS                     │ JS SENDS                          │
├─────────────────────────────────┼───────────────────────────────────┤
│ Nonce field: fkcart_token         │ fkcart_token: fkcart_data.cache_token │ ✓
│ Nonce action: (custom hash)     │ (custom hash from PHP)            │ ✓
│ Capability: NONE (rate limited) │ N/A                               │ ✓
│ Method: POST                    │ POST                              │ ✓
├─────────────────────────────────┴───────────────────────────────────┤
│ VERDICT: MATCHED - Security requirements satisfied                   │
└─────────────────────────────────────────────────────────────────────┘
```

### Step 6: Identify Mismatches and Issues

#### Issue Types

| Issue | Severity | Description |
|-------|----------|-------------|
| `NONCE_NOT_SENT` | HIGH | PHP expects nonce but JS doesn't send it |
| `WRONG_NONCE_FIELD` | HIGH | JS sends nonce with wrong field name |
| `MISSING_CAPABILITY_CHECK` | MEDIUM | nopriv handler without proper auth |
| `NO_JS_CALLER` | LOW | PHP handler exists but no JS calls it |
| `NO_PHP_HANDLER` | LOW | JS calls action that doesn't exist |
| `WRONG_METHOD` | MEDIUM | PHP expects POST but JS sends GET |
| `FRONTEND_ADMIN_ACTION` | HIGH | Admin-only action called from frontend JS |

#### Mismatch Detection

```json
{
  "action": "problematic_action",
  "mismatch": {
    "type": "NONCE_NOT_SENT",
    "php_expects": {
      "nonce_field": "security",
      "nonce_action": "my_action_nonce"
    },
    "js_sends": {
      "nonce_field": null,
      "nonce_value": null
    },
    "consequence": "All AJAX calls will fail nonce check and return error",
    "recommendation": "Add nonce to JS: security: my_localized_data.nonce"
  }
}
```

---

## Localized Script Data Analysis

When JS uses variables like `fkcart_data.cache_token`, trace where this comes from:

```bash
# Find wp_localize_script calls
grep -rn "wp_localize_script" --include="*.php" . | grep "fkcart_data"
```

```php
wp_localize_script('fkcart_public_js', 'fkcart_data', array(
    'admin_ajax' => admin_url('admin-ajax.php'),
    'cache_token' => WCCT_Common::get_cache_clear_token(),  // ← Source of token
    'nonce' => wp_create_nonce('my_action_nonce')
));
```

Verify:
1. Token/nonce is actually included in localized data
2. Token generation matches what PHP validates
3. Localized data is available when JS executes

---

## Security Verdict Matrix

| PHP Auth | PHP Nonce | JS Sends Nonce | JS Context | Verdict |
|----------|-----------|----------------|------------|---------|
| nopriv | Yes | Yes (matches) | Frontend | SECURE |
| nopriv | Yes | No | Frontend | VULNERABLE (bypassed) |
| nopriv | No | N/A | Frontend | VULNERABLE (no protection) |
| auth only | Yes | Yes | Admin JS | SECURE |
| auth only | Yes | No | Admin JS | BROKEN (always fails) |
| auth only | No | N/A | Admin JS | NEEDS_REVIEW |

---

## Complete Backtrack Report Format

```json
{
  "scan_timestamp": "2025-12-11T18:00:00Z",
  "plugin": "cart-for-woocommerce",

  "summary": {
    "total_ajax_handlers": 15,
    "handlers_with_js_callers": 12,
    "handlers_without_js_callers": 3,
    "security_matched": 10,
    "security_mismatched": 2,
    "needs_review": 3
  },

  "detailed_results": [
    {
      "action": "fkcart_clear_cache",
      "php_handler": {
        "file": "includes/common.php",
        "line": 63,
        "has_nopriv": true,
        "security": {
          "nonce_check": true,
          "nonce_field": "fkcart_token",
          "capability_check": false,
          "rate_limiting": true
        }
      },
      "js_callers": [
        {
          "file": "assets/js/fkcart-custom.js",
          "line": 165,
          "sends_nonce": true,
          "nonce_field": "fkcart_token",
          "nonce_source": "fkcart_data.cache_token",
          "context": "timer_elapsed_event",
          "loaded_on": "frontend"
        }
      ],
      "localized_data": {
        "php_file": "includes/front.php",
        "line": 85,
        "object_name": "fkcart_data",
        "token_key": "cache_token",
        "token_source": "WCCT_Common::get_cache_clear_token()"
      },
      "security_analysis": {
        "nonce_flow_valid": true,
        "auth_appropriate": true,
        "rate_limiting_present": true
      },
      "verdict": "SECURE",
      "priority": null
    }
  ],

  "issues_found": [
    {
      "action": "some_vulnerable_action",
      "issue_type": "NONCE_NOT_SENT",
      "severity": "HIGH",
      "priority": "P1",
      "description": "PHP handler checks nonce but JS caller doesn't send it",
      "php_location": "includes/ajax.php:45",
      "js_location": "assets/js/admin.js:120",
      "recommendation": "Add nonce to JS AJAX call"
    }
  ],

  "dead_code": [
    {
      "action": "unused_ajax_action",
      "php_location": "includes/deprecated.php:30",
      "reason": "No JS caller found in any source file",
      "recommendation": "Remove handler if truly unused"
    }
  ]
}
```

---

## Commands Reference

### Find all AJAX handlers in PHP
```bash
grep -rn "add_action.*wp_ajax" --include="*.php" . \
  --exclude-dir=vendor \
  --exclude-dir=node_modules \
  --exclude-dir=bin
```

### Find JS source files only
```bash
find . -name "*.js" -type f \
  ! -name "*.min.js" \
  ! -name "*.bundle.js" \
  ! -name "*.combined.js" \
  ! -path "*/vendor/*" \
  ! -path "*/node_modules/*"
```

### Search for action in JS
```bash
grep -rn "action.*['\"]ACTION_NAME['\"]" \
  --include="*.js" \
  --exclude="*.min.js" \
  --exclude="*.bundle.js" \
  --exclude="*.combined.js" \
  --exclude-dir=vendor \
  --exclude-dir=node_modules
```

### Find localized script data
```bash
grep -rn "wp_localize_script" --include="*.php" .
```

### Find where JS is enqueued
```bash
grep -rn "wp_enqueue_script.*SCRIPT_HANDLE" --include="*.php" .
```

### Find nonce checks in PHP
```bash
grep -n "wp_verify_nonce\|check_ajax_referer\|hash_equals" FILE.php
```

### Find capability checks in PHP
```bash
grep -n "current_user_can" FILE.php
```

---

## Integration with Security Scanner

The JS Backtracker should be called by the Security Scanner for every AJAX handler found:

```
Scanner finds: add_action('wp_ajax_nopriv_my_action', 'handler')
    ↓
Backtracker analyzes:
    1. What security does handler() implement?
    2. What JS files call action='my_action'?
    3. Do JS calls satisfy handler's security requirements?
    ↓
Backtracker returns: SECURE | VULNERABLE | MISMATCH | DEAD_CODE
    ↓
Scanner incorporates result into final report
```

---

## When to Flag as Vulnerability

| Scenario | Verdict | Priority |
|----------|---------|----------|
| nopriv handler, no nonce check, no rate limit | VULNERABLE | P0 |
| nopriv handler, nonce check, JS doesn't send nonce | VULNERABLE | P1 |
| nopriv handler, JS sends wrong nonce field name | VULNERABLE | P1 |
| auth handler, no capability check, low-priv action | NEEDS_REVIEW | P2 |
| Handler exists, no JS caller found | DEAD_CODE | P3 |
| JS calls action that doesn't exist | BROKEN_CODE | P3 |
| **JS function exists but is NEVER CALLED** | **FALSE_POSITIVE** | **N/A** |
| **PHP handler callback method doesn't exist** | **FALSE_POSITIVE** | **N/A** |

---

## Critical: Verify JS Functions Are Actually Invoked

**IMPORTANT:** Finding a JS function that contains an AJAX call is NOT enough. You MUST verify the function is actually called somewhere.

### Example False Positive

```javascript
// Function EXISTS but is NEVER CALLED anywhere
function fkcart_ajax_call($this, expireTime) {
    $.ajax({
        url: fkcart_data.admin_ajax,
        data: { action: 'fkcart_close_sticky_bar' }
    });
}
// No code ever calls fkcart_ajax_call() - this is DEAD CODE
```

### Verification Steps

1. **Find the function definition:**
   ```bash
   grep -n "function fkcart_ajax_call" assets/js/fkcart-custom.js
   ```

2. **Search for any invocations:**
   ```bash
   grep -n "fkcart_ajax_call(" assets/js/fkcart-custom.js
   ```

3. **If only the definition exists (no calls), mark as:**
   - `verdict: FALSE_POSITIVE`
   - `reason: "JS function defined but never invoked - dead code"`
   - `action: "Clean up dead code for hygiene, not a security fix"`

### Also Check PHP Callback Exists

```bash
# If hook references WCCT_Common::fkcart_close_sticky_bar
grep -n "function fkcart_close_sticky_bar" includes/common.php
```

If method doesn't exist → FALSE_POSITIVE (dead hook, can't be exploited)
