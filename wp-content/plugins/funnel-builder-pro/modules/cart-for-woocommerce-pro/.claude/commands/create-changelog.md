---
description: Generate changelog from merged GitHub PRs for release
argument-hint: "<pr_number>"
---

# Create Changelog Command

Generate a changelog from merged GitHub PRs starting from the specified PR number.

## Usage

```
/create-changelog <pr_number>
```

### Examples

```
/create-changelog 150    # Generate changelog from PR #150 to latest
/create-changelog 200    # Generate changelog from PR #200 to latest
```

---

## What This Command Does

1. Reads project context (CLAUDE.md, docs/)
2. Fetches the input PR to get its merge date as the starting point
3. Fetches all merged PRs from current time going backward
4. Includes PRs merged on or after the input PR's merge date
5. Filters to include only `fix/*` and `feature/*` branches
6. Analyzes each PR's changes and categorizes them
7. Generates one-liner changelog entries
8. Groups by type, sorts oldest to latest within groups
9. Clears and writes to `changelog.md`
10. Commits the changes

---

## Workflow

### Step 1: Read Context

```bash
Read CLAUDE.md
Read docs/*.md
```

Understand the plugin architecture and terminology.

### Step 2: Fetch Merged PRs

```bash
# First, get the input PR's merge date
gh pr view <pr_number> --json mergedAt

# Then get all merged PRs
gh pr list --state merged --json number,title,headRefName,mergedAt,body,files --limit 500
```

Filter PRs where:
- Merge date >= input PR's merge date (time-based, not PR number)
- Branch matches `fix/*` or `feature/*` pattern

Exclude branches:
- `test/*` - Internal testing
- `ai/*` - Internal tooling
- `docs/*` - Documentation only
- `chore/*` - Maintenance

### Step 3: Analyze Each PR

For each PR:

1. **Check if Pro**: Look for files in `pro/` directory or "Pro" in title/body
2. **Determine Category**:
   - Security: Security fixes, sanitization, escaping
   - New: New features, components, integrations
   - Added: New functionality to existing features
   - Improved: Enhancements, optimizations
   - Fixed: Bug fixes
   - Devs: Developer-facing changes (hooks, filters, API)

3. **Write One-Liner**: Clear, concise description ending with `(fix/XXX)` or `(feature/XXX)`

### Step 4: Generate Changelog

Group entries by type in this order:
1. Security
2. New
3. Added
4. Improved
5. Fixed
6. Devs

Within each group, sort oldest PR first.

### Step 5: Write File

```bash
# Clear changelog.md completely
> changelog.md

# Write new content
```

### Step 6: Commit

```bash
git add changelog.md
git commit -m "docs: update changelog for release"
```

---

## Output Format

```markdown
## [Unreleased]

### Security
- Security: Description of security fix. (fix/123)

### New
- New: Major new feature. (feature/456)
  - Sub-feature detail
  - Another detail

### Added
- Added: Pro: Feature for pro version. (fix/789)
- Added: Feature for lite. (fix/790)

### Improved
- Improved: Pro: Enhancement. (fix/791)
- Improved: Performance optimization. (fix/792)

### Fixed
- Fixed: Pro: Bug fix for pro. (fix/793)
- Fixed: Bug description. (fix/794)

### Devs
- Devs: New filter hook. (fix/795)
```

---

## Formatting Rules

1. No emojis
2. Each entry ends with branch reference: `(fix/XXX)` or `(feature/XXX)`
3. Pro changes: `Added: Pro: Description` (Pro after type)
4. Use bullet points (-)
5. Sub-points allowed for complex features
6. Omit empty sections

---

## Category Keywords

| Category | Keywords |
|----------|----------|
| Security | security, vulnerability, sanitize, escape, XSS, CSRF, nonce |
| New | new feature, new component, introduces, integration, compatibility |
| Added | added, add support, new option, new setting, new filter |
| Improved | improved, enhanced, optimized, better, faster, UX |
| Fixed | fixed, resolved, bug, issue, broken, not working |
| Devs | developer, hook, filter, API, refactor, internal |

---

## Reference

Agent documentation: `bin/agents/changelog/changelog-generator.md`

---

## Example Output

For `/create-changelog 160`:

```markdown
## [Unreleased]

### Security
- Security: Sanitized REQUEST_URI inputs to prevent ReDoS attacks. (fix/160)

### New
- New: WPML compatibility for multilingual cart labels. (fix/160)

### Added
- Added: Dynamic language detection for cart totals. (fix/160)

### Improved
- Improved: Cart fragment caching for better performance. (fix/160)

### Fixed
- Fixed: Cart count not updating on AJAX add to cart. (fix/160)
- Fixed: Coupon box visibility settings not saving. (fix/160)
```
