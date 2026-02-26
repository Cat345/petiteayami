# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Cart for WooCommerce Pro is a pro module that extends the free FunnelKit Cart (Cart for WooCommerce) plugin with premium features. This is **not a standalone plugin** - it's distributed as a submodule of Funnel Builder Pro.

**Namespace:** `FKCart\Pro`

### Plugin Ecosystem

| Component | Type | Repository | Base Branch |
|-----------|------|------------|-------------|
| FunnelKit Cart (Lite) | Free plugin | https://github.com/xlplugins/cart-for-woocommerce | master |
| Cart for WooCommerce Pro | Pro module | https://github.com/xlplugins/cart-for-woocommerce-pro | master |

- **WordPress.org:** https://wordpress.org/plugins/cart-for-woocommerce/
- **Funnel Builder Pro module path:** `funnel-builder-pro/modules/cart-for-woocommerce-pro`

### Git Workflow

- Always create branches from `master`
- Always create PRs targeting `master`
- Commits go to this repo, but the plugin is used via Funnel Builder Pro submodule

## Cross-Plugin Development

Pro depends heavily on Lite. Changes in Pro may require Lite updates, and Lite changes may break Pro.

### When Modifying Pro Code

**Always check Lite for:**
1. **Base classes** - Pro extends Lite classes; ensure method signatures match
2. **Hooks availability** - Pro relies on Lite action/filter hooks
3. **Settings keys** - Pro uses `FKCart\Includes\Data::get_value()` for settings
4. **Database tables** - Pro writes to Lite's `wp_fk_cart*` tables
5. **AJAX endpoints** - Pro may extend Lite's AJAX handlers

**Update Lite documentation if:**
- Pro needs new hooks in Lite
- Pro requires new settings keys
- Pro needs database schema changes
- Pro adds features that affect Lite behavior

### Key Lite Dependencies

| Lite Component | How Pro Uses It |
|----------------|-----------------|
| `FKCart\Includes\Data` | `get_value()`, `get_settings()`, `is_cart_enabled()` |
| `FKCart\Includes\Front` | `get_instance()`, template functions |
| `FKCart\Includes\Ajax` | Base AJAX functionality |
| `fkcart_loaded` action | Pro bootstrap trigger (priority 15) |
| `fkcart_fragments` filter | Pro adds HTML to cart fragments |
| `fkcart_after_cart_items` | Upsells/add-ons insertion |
| `fkcart_before_checkout_button` | Rewards progress bar |
| `wp_fk_cart` table | Order-level tracking data |
| `wp_fk_cart_products` table | Product tracking (types 1,2,3) |

### Lite Repository

When Pro changes require Lite updates:
- **Repo:** https://github.com/xlplugins/cart-for-woocommerce
- **Local path:** `~/Sites/localwp/wp-content/plugins/cart-for-woocommerce`

### Common Cross-Plugin Tasks

| Task | Update Lite | Update Pro |
|------|-------------|------------|
| Add new Pro setting | Add to settings schema | Use in Pro code |
| New Pro hook point needed | Add action/filter in Lite | Hook in Pro |
| Database schema change | Update `db.php`, bump version | Update queries |
| New AJAX endpoint for Pro | Add in Lite `ajax.php` | Call from Pro |
| Template change | Update Lite template | Update Pro override |

## Architecture

### Plugin Entry Point
- `plugin.php` - Main plugin file that bootstraps the plugin on the `funnelkit_cart_loaded` action (priority 15)

### Core Components

**Upsells** (`include/upsells.php`)
- Manages cart upsell product recommendations
- Tracks upsell views in WC session (`_fkcart_upsell_views`)
- Handles order line item metadata for upsells (`_fkcart_upsell`)
- Integrates with WC refund system to update revenue tracking

**Rewards** (`include/rewards.php`)
- Progress bar rewards system (free shipping, discount coupons, free gifts)
- Geolocation-based free shipping calculation via custom `Geolocation` class
- Manages reward state in WC session (`_fkcart_free_shipping_methods`, `_fkcart_applied_coupons`)
- Hooks into `woocommerce_calculate_totals` to apply/remove rewards dynamically

**Special Add-On** (`include/special-add-on.php`)
- Single product add-on feature (like shipping protection)
- Auto-adds product to cart based on `preselect_special_addon` setting
- Supports variable products with variation handling
- Template: `templates/cart/special-addon-html.php`

### Data Storage

**Database Tables** (created by base plugin):
- `{prefix}fk_cart` - Cart conversion tracking (order ID, rewards, discount codes, dates)
- `{prefix}fk_cart_products` - Product-level tracking with types:
  - Type 1: Upsells
  - Type 2: Free gifts
  - Type 3: Special add-ons

### REST API (`rest/conversions.php`)
REST endpoints under `/funnelkit-app/`:
- `GET /fkcart-conversions/` - Conversion analytics with filters
- `GET /fkcart-overview/` - Summary statistics
- `GET /fkcart-upsell-performance/` - Time-series performance data
- `GET /fkcart-popular-upsells/` - Top performing upsells
- `GET /fkcart-reward-chart/` - Pie chart data for reward distribution
- `POST /fkcart-migrate-data/` - Trigger data migration

## Key Dependencies

- Base plugin: FunnelKit Cart (`FKCart\Includes\Data`, `FKCart\Includes\Front`)
- FunnelKit Core: `WFFN_Core`, license validation
- WooCommerce: Cart, session, product, order APIs
- Background processing: `WooFunnels_Background_Updater` for data migration

## License Validation

`Plugin::valid_l()` checks license state before enabling pro features. States include:
- `pro` - Valid license
- `license_expired` - Expired license
- `pro_without_license` - Pro installed without license
- Grace periods for both scenarios

## Important Patterns

### Singleton Pattern
All main classes use `getInstance()` static method with private constructor.

### WC Session Keys
Pro features store state in WC session:
- `_fkcart_upsell_views` - Array of viewed upsell product IDs
- `_fkcart_free_gift_views` - Array of viewed free gift product IDs
- `_fkcart_free_shipping_methods` - Active free shipping method
- `_fkcart_applied_coupons` - Reward-applied coupon codes
- `_fkcart_removed_coupons` - User-removed coupons (prevents re-adding)
- `_fkcart_spl_addon_product_id` - Special add-on product ID
- `_fkcart_spl_addon_product_cart_key` - Cart key for special add-on

### WPML/Polylang Compatibility
Product ID mapping via `Special_Add_On::get_map_product()` for multilingual stores.

### Variable Product Handling
Both Rewards and Special Add-On handle variable products with "Any" attribute mapping through `map_variation_attributes()` / `fkcart_map_variation_attributes()`.

## Documentation

Comprehensive documentation is available in the `docs/` directory:

| Document | Purpose |
|----------|---------|
| [PLUGIN_KNOWLEDGE_BASE.md](docs/PLUGIN_KNOWLEDGE_BASE.md) | Complete architecture, code inventory, and patterns |
| [HOOKS_REFERENCE.md](docs/HOOKS_REFERENCE.md) | All actions and filters with examples |
| [DATABASE_SCHEMA.md](docs/DATABASE_SCHEMA.md) | Tables, options, transients, session keys |
| [FILE_MAP.md](docs/FILE_MAP.md) | Feature-to-file mapping with line references |
| [MODIFICATION_PATTERNS.md](docs/MODIFICATION_PATTERNS.md) | Safe modification guide and anti-patterns |
| [DEPENDENCIES_MAP.md](docs/DEPENDENCIES_MAP.md) | Internal/external dependency relationships |
| [SECURITY_CHECKLIST.md](docs/SECURITY_CHECKLIST.md) | Security requirements and best practices |
| [API_REFERENCE.md](docs/API_REFERENCE.md) | REST endpoints, PHP methods, data structures |
| [PATCHSTACK_VULNERABILITY_PATTERNS.md](docs/PATCHSTACK_VULNERABILITY_PATTERNS.md) | Vulnerability detection patterns |

### Quick Reference

**Find the right file:**
- Modifying upsells → `include/upsells.php`
- Modifying rewards/free shipping → `include/rewards.php`
- Modifying special add-on → `include/special-add-on.php` + `templates/cart/special-addon-html.php`
- Adding REST endpoints → `rest/conversions.php`

**Database product types:**
- Type 1 = Upsell
- Type 2 = Free Gift
- Type 3 = Special Add-On

**Always remember:**
1. Check `Plugin::valid_l()` for pro features
2. Check `WC()->session` availability before session operations
3. Use `$wpdb->prepare()` for all database queries
4. Escape all template output with `esc_*` functions

## Slash Commands

Custom Claude Code commands in `.claude/commands/`:

| Command | Description |
|---------|-------------|
| `/security-scan` | Comprehensive security vulnerability scanning |
| `/branch-review` | Code review for feature branches |
| `/create-changelog` | Generate changelog from git commits |
| `/sync-docs` | Synchronize documentation with codebase |

## AI Agents

Specialized agents in `bin/agents/` for automated workflows:

### Security Agents (`bin/agents/security/`)
- `security-orchestrator.md` - Coordinates security workflow
- `security-scanner.md` - Pattern-based vulnerability scanning
- `security-analyzer.md` - Confirms vulnerabilities vs false positives
- `security-prioritizer.md` - Ranks issues by severity (P0-P3)
- `security-fixer.md` - Generates and applies security patches
- `security-validator.md` - Verifies fixes are correct
- `security-js-backtracker.md` - Traces AJAX handlers to JS callers
- `security-rest-backtracker.md` - Analyzes REST API endpoints

### Branch Review Agents (`bin/agents/branch-review/`)
- `branch-review-orchestrator.md` - Coordinates review process
- `code-quality-analyzer.md` - Checks formatting, PHPDoc, patterns
- `dependency-knowledge-agent.md` - Validates external API usage
- `performance-analyzer.md` - Detects performance bottlenecks
- `verification-agent.md` - Verifies fixes and runs checks

### Changelog Agent (`bin/agents/changelog/`)
- `changelog-generator.md` - Generates changelogs from commits
