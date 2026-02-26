---
description: Synchronize plugin documentation with current codebase (two-way sync)
---

# Documentation Sync Command

You are an expert WordPress developer tasked with synchronizing the plugin documentation with the current codebase state. This is a TWO-WAY sync that ensures docs accurately reflect the code.

## Usage

```
/sync-docs
```

Performs a complete two-way sync: scans codebase, compares with docs, generates report, and applies updates.

---

## OBJECTIVE

Perform bidirectional synchronization between the codebase and documentation:
1. **Forward Sync (Code → Docs):** Find new/changed code not yet documented
2. **Backward Sync (Docs → Code):** Find documented items that no longer exist in code

---

## PHASE 1: INVENTORY CURRENT CODEBASE

### 1.1 Extract All Entry Points

Scan the codebase and extract:

```bash
# AJAX Handlers
grep -rn "add_action.*wp_ajax" --include="*.php" .

# REST API Routes
grep -rn "register_rest_route" --include="*.php" .

# Shortcodes
grep -rn "add_shortcode" --include="*.php" .

# Custom Post Types
grep -rn "register_post_type" --include="*.php" .

# Admin Pages
grep -rn "add_menu_page\|add_submenu_page" --include="*.php" .

# Custom Hooks (do_action, apply_filters with plugin prefix)
grep -rn "do_action.*fkcart\|apply_filters.*fkcart" --include="*.php" .

# Filters Added
grep -rn "add_filter" --include="*.php" .

# Actions Added
grep -rn "add_action" --include="*.php" .
```

### 1.2 Extract Classes & Functions

```bash
# All Classes
grep -rn "^class " --include="*.php" .

# All Functions
grep -rn "^function " --include="*.php" .

# Class Methods (public)
grep -rn "public function" --include="*.php" .
```

### 1.3 Extract Database Usage

```bash
# Options
grep -rn "get_option\|update_option\|add_option" --include="*.php" .

# Post Meta Keys
grep -rn "get_post_meta\|update_post_meta" --include="*.php" .

# Transients
grep -rn "get_transient\|set_transient" --include="*.php" .

# Direct DB Queries
grep -rn "\$wpdb->" --include="*.php" .
```

---

## PHASE 2: INVENTORY CURRENT DOCUMENTATION

### 2.1 Read All Doc Files

Read these files from `docs/` folder:
- `PLUGIN_KNOWLEDGE_BASE.md`
- `HOOKS_REFERENCE.md`
- `DATABASE_SCHEMA.md`
- `FILE_MAP.md`
- `MODIFICATION_PATTERNS.md`
- `DEPENDENCIES_MAP.md`
- `SECURITY_CHECKLIST.md`
- `API_REFERENCE.md`

Also read:
- `CLAUDE.md` (root)

### 2.2 Extract Documented Items

From each doc file, extract:
- Hook names (actions, filters)
- Function names
- Class names
- AJAX action names
- REST routes
- Option names
- Meta keys
- File references

---

## PHASE 3: COMPARE & IDENTIFY GAPS

### 3.1 Forward Gaps (Code exists, Docs missing)

```
┌─────────────────────────────────────────────────────────────┐
│ UNDOCUMENTED CODE                                           │
├─────────────────────────────────────────────────────────────┤
│ Type        │ Name                    │ File:Line           │
├─────────────────────────────────────────────────────────────┤
│ AJAX        │ new_ajax_handler        │ includes/foo.php:45 │
│ Hook        │ fkcart_new_filter       │ includes/bar.php:78 │
│ Function    │ fkcart_helper_func      │ includes/util.php:12│
└─────────────────────────────────────────────────────────────┘
```

### 3.2 Backward Gaps (Docs exist, Code missing)

```
┌─────────────────────────────────────────────────────────────┐
│ STALE DOCUMENTATION                                         │
├─────────────────────────────────────────────────────────────┤
│ Type        │ Name                    │ Doc File            │
├─────────────────────────────────────────────────────────────┤
│ AJAX        │ fkcart_close_sticky_bar │ HOOKS_REFERENCE.md  │
│ Function    │ removed_function        │ API_REFERENCE.md    │
└─────────────────────────────────────────────────────────────┘
```

### 3.3 Modified Items (Signature/behavior changed)

```
┌─────────────────────────────────────────────────────────────┐
│ OUTDATED DOCUMENTATION                                      │
├─────────────────────────────────────────────────────────────┤
│ Item              │ Doc Says          │ Code Actually       │
├─────────────────────────────────────────────────────────────┤
│ func_name params  │ ($a, $b)          │ ($a, $b, $c)        │
│ ajax auth level   │ nopriv            │ auth only           │
└─────────────────────────────────────────────────────────────┘
```

---

## PHASE 4: GENERATE SYNC REPORT

Create a sync report showing:

```markdown
# Documentation Sync Report
**Date:** YYYY-MM-DD
**Plugin Version:** X.X.X

## Summary
| Category | Undocumented | Stale | Modified |
|----------|--------------|-------|----------|
| AJAX Handlers | 2 | 1 | 0 |
| Hooks | 5 | 2 | 1 |
| Functions | 3 | 0 | 0 |
| Classes | 0 | 0 | 0 |
| Options | 1 | 0 | 0 |

## Action Required
- [ ] Add 11 new items to docs
- [ ] Remove 3 stale items from docs
- [ ] Update 1 modified item in docs
```

---

## PHASE 5: UPDATE DOCUMENTATION

### 5.1 Update Strategy

For each doc file, apply changes:

**HOOKS_REFERENCE.md:**
- Add new hooks with: name, file, line, callback, priority
- Remove hooks that no longer exist
- Update hooks with changed signatures

**FILE_MAP.md:**
- Add new files with their purpose
- Remove deleted files
- Update file descriptions if purpose changed

**DATABASE_SCHEMA.md:**
- Add new options/meta keys
- Remove unused options/meta keys
- Update descriptions

**SECURITY_CHECKLIST.md:**
- Add new AJAX handlers with their security status
- Remove deleted handlers
- Update security patterns

**API_REFERENCE.md:**
- Add new public functions/methods
- Remove deprecated/deleted APIs
- Update signatures and descriptions

**CLAUDE.md:**
- Update architecture section if structure changed
- Update security section if handlers changed
- Update any file references

### 5.2 Update Format

When adding new items, follow existing doc patterns:

```markdown
### New Hook: fkcart_example_filter
- **File:** includes/example.php:123
- **Type:** Filter
- **Parameters:** $value, $post_id
- **Description:** Filters the example value before display
- **Added:** December 2025
```

When removing items, simply delete the section.

When updating items, modify in place with clear descriptions.

---

## PHASE 6: VERIFICATION

After updates:

1. **Validate Markdown:** Ensure all docs are valid markdown
2. **Check Links:** Verify internal links still work
3. **Consistency:** Ensure naming conventions are consistent
4. **Completeness:** Confirm all gaps addressed

---

## OUTPUT

1. **Sync Report:** Display summary of changes needed
2. **Updated Docs:** Apply all changes to doc files
3. **Change Log:** List all modifications made

---

## EXECUTION INSTRUCTIONS

1. Start with Phase 1 - scan entire codebase
2. Read all existing docs in Phase 2
3. Generate comparison tables in Phase 3
4. Show sync report to user in Phase 4
5. Ask user for approval before Phase 5
6. Apply updates and verify in Phase 6

**Important:**
- Do NOT delete documentation for items you're unsure about - flag for review
- Preserve existing formatting and style of each doc file
- Add "Last synced: DATE" at the top of each updated file
- Create backup note of what was removed (in case of errors)

Begin the sync process now.
