---
description: Perform a comprehensive security scan of the WordPress plugin for vulnerabilities
argument-hint: "[full|quick|fix|report]"
---

# Security Scan Command

Perform a comprehensive security scan of the WordPress plugin.

## Usage

```
/security-scan [options]
```

### Options

- `full` - Complete scan with all checks (default)
- `quick` - Fast scan, critical patterns only
- `fix` - Scan and auto-fix P0/P1 issues
- `report` - Show last scan report

---

## What This Command Does

1. **Scans** all PHP files for security vulnerability patterns
2. **Analyzes** each finding to confirm real vs false positive
3. **Prioritizes** vulnerabilities by risk (P0-P3)
4. **Reports** findings with remediation guidance
5. **Optionally fixes** critical issues with user approval

---

## Workflow

Follow this workflow using the security agents in `bin/agents/security/`:

### Step 0: Clean Up Previous Reports (full/quick scans only)

**IMPORTANT:** For `full` or `quick` scans, delete ALL existing scan reports before starting:

```bash
# Delete all previous scan reports
rm -f bin/agents/security/reports/*.json
```

This ensures:
- Only the latest scan results are kept
- No confusion between old and new findings
- Clean state for each scan

**Note:** Skip this step for `report` (viewing) and `fix` (applying fixes) modes.

### Step 1: Initialize Scan
Read `bin/agents/security/security-orchestrator.md` for coordination instructions.

### Step 2: Run Scanner
Using patterns from `bin/agents/security/security-scanner.md`:

```bash
# Scan for SQL injection patterns
grep -rn --include="*.php" -E '\$wpdb->(query|get_results|get_var|get_row)\s*\([^)]*\$_(GET|POST|REQUEST)' . --exclude-dir={vendor,node_modules,bin}

# Scan for XSS patterns
grep -rn --include="*.php" -E 'echo\s+\$_(GET|POST|REQUEST)' . --exclude-dir={vendor,node_modules,bin}

# Scan for Object Injection
grep -rn --include="*.php" -E '(unserialize|maybe_unserialize)\s*\(\s*\$_(GET|POST|REQUEST)' . --exclude-dir={vendor,node_modules,bin}

# Continue with other patterns from security-scanner.md...
```

### Step 3: Analyze Findings
For each finding, use `bin/agents/security/security-analyzer.md`:
- Read full file context
- Trace data flow
- Check for existing sanitization
- Confirm or reject as false positive

### Step 4: Prioritize
Using `bin/agents/security/security-prioritizer.md`:
- Calculate risk scores
- Assign priority levels (P0-P3)
- Create remediation order

### Step 5: Report
Save report to `bin/agents/security/reports/scan-{timestamp}.json`

Present summary to user:
```
## Security Scan Results

| Priority | Count | Risk Level |
|----------|-------|------------|
| P0       | X     | Critical   |
| P1       | X     | High       |
| P2       | X     | Medium     |
| P3       | X     | Low        |

### Critical Issues (P0)
[List each P0 with file:line and description]

What would you like to do?
- Review all findings in detail
- Fix P0 issues
- Fix all issues
- Export report
```

### Step 6: Fix (if requested)
Using `bin/agents/security/security-fixer.md`:
- Generate fix for each vulnerability
- Apply using Edit tool
- Get user approval for each fix

### Step 7: Validate
Using `bin/agents/security/security-validator.md`:
- Verify syntax valid
- Confirm vulnerability resolved
- Check for regressions

---

## Reference Documents

- Pattern database: `docs/PATCHSTACK_VULNERABILITY_PATTERNS.md`
- Security standards: `docs/SECURITY_CHECKLIST.md`
- Scan results: `bin/agents/security/reports/`

---

## Example Output

```
## Security Scan Complete

**Plugin:** cart-for-woocommerce-pro
**Scanned:** 12 PHP files (2,500 lines)
**Duration:** 32 seconds

### Summary

| Priority | Count | Description |
|----------|-------|-------------|
| P0       | 0     | Critical - Fix immediately |
| P1       | 1     | High - Fix within 24 hours |
| P2       | 3     | Medium - Fix within 1 week |
| P3       | 2     | Low - Fix in next release |

### P1 - High Priority

**1. Missing CSRF Protection**
`admin/admin.php:245`
Form submission without nonce verification.
Fix: Add wp_nonce_field() and wp_verify_nonce()

### P2 - Medium Priority

**1. Potential XSS in Shortcode**
`includes/front.php:178`
Shortcode attribute output without escaping.
Fix: Add esc_attr() around attribute output

[...]

### Next Steps
- Run `/security-scan fix` to auto-fix P0/P1 issues
- Run `/security-scan report` to view detailed report
```
