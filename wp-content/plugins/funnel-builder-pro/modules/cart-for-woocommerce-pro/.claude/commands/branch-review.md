---
description: Comprehensive branch/PR review with security, quality, performance, and compatibility analysis
argument-hint: "[full|quick|fix|plan|verify]"
---

# Branch Review Command

Perform comprehensive review of the current branch before merging.

## Usage

```
/branch-review [options]
```

### Options

- `full` - Complete review with all checks (default)
- `quick` - Fast review, critical issues only
- `fix` - Review and auto-fix issues
- `plan` - Generate fix plan without executing
- `verify` - Run verification checks only

---

## What This Command Does

1. **Gathers Context** - Reads CLAUDE.md, docs/, understands architecture
2. **Analyzes Diff** - Understands what changed in the branch
3. **Checks Dependencies** - Verifies external plugin API usage (WPML, WooCommerce, etc.)
4. **Runs Parallel Analysis:**
   - Security scan (reuses `/security-scan`)
   - Code quality check (empty blocks, formatting, PHPDoc)
   - Performance analysis (queries, caching, loops)
   - Breaking change detection
5. **Generates Fix Plan** - Prioritized, phased remediation plan
6. **Applies Fixes** - With user approval
7. **Verifies** - PHP lint, PHPCS, tests
8. **Creates Commits** - Atomic commits with proper messages
9. **Updates PR** - Comprehensive PR description

---

## Workflow

Follow this workflow using agents in `bin/agents/branch-review/`:

### Step 0: Read Context

**ALWAYS read these files first:**

```bash
Read CLAUDE.md
Read docs/*.md (if exists)
```

### Step 1: Analyze Branch

```bash
# Get base branch
git log --oneline master..HEAD

# Get changed files
git diff --name-only master...HEAD

# Get detailed diff
git diff master...HEAD --stat
```

### Step 2: Check Dependencies

For each external dependency detected (WPML, WooCommerce, Elementor):

1. Check if installed locally: `ls wp-content/plugins/{slug}/`
2. If local: Read its code for API understanding
3. If not local: Fetch documentation from web

See `bin/agents/branch-review/dependency-knowledge-agent.md`

### Step 3: Run Parallel Analysis

Spawn these in parallel using Task tool:

1. **Security Scan**
   - Use existing `/security-scan quick`
   - Or spawn security-orchestrator agent

2. **Code Quality**
   - Use `bin/agents/branch-review/code-quality-analyzer.md`
   - Check: empty blocks, formatting, PHPDoc

3. **Performance**
   - Use `bin/agents/branch-review/performance-analyzer.md`
   - Check: queries, caching, loops

4. **Breaking Changes**
   - Compare function signatures
   - Check default value changes
   - Verify hook compatibility

### Step 4: Aggregate & Prioritize

Combine findings:

| Priority | Description | Action |
|----------|-------------|--------|
| P0 | Critical security/breaking | Must fix |
| P1 | High risk issues | Should fix |
| P2 | Medium issues | Recommended |
| P3 | Low issues | Nice to have |

### Step 5: Generate Plan

Create fix plan in `bin/agents/branch-review/plans/`:

```json
{
  "branch": "fix/160",
  "phases": [
    {"phase": 1, "title": "Security Fixes", "tasks": [...]},
    {"phase": 2, "title": "Breaking Changes", "tasks": [...]},
    {"phase": 3, "title": "Performance", "tasks": [...]},
    {"phase": 4, "title": "Code Quality", "tasks": [...]}
  ]
}
```

### Step 6: Present to User

```markdown
## Branch Review Complete

**Branch:** fix/160 → master
**Files Changed:** 5

### Findings Summary

| Category | P0 | P1 | P2 | P3 |
|----------|----|----|----|----|
| Security | 1  | 0  | 0  | 0  |
| Quality  | 0  | 0  | 3  | 2  |
| Perf     | 0  | 2  | 1  | 0  |
| Breaking | 1  | 0  | 0  | 0  |

### Actions

- Run `/branch-review fix` to apply fixes
- Run `/branch-review plan` to see detailed plan
- Run `/branch-review verify` to check current state
```

### Step 7: Apply Fixes (if requested)

For each phase:
1. Apply fixes using Edit tool
2. Run PHP lint
3. Commit with atomic message
4. Proceed to next phase

### Step 8: Verify

```bash
# Syntax check
php -l {modified_files}

# Standards check
composer phpcs

# Tests (if available)
composer test
```

### Step 9: Update PR

If PR exists, update with comprehensive description.

---

## Reference Agents

- Orchestrator: `bin/agents/branch-review/branch-review-orchestrator.md`
- Dependency: `bin/agents/branch-review/dependency-knowledge-agent.md`
- Code Quality: `bin/agents/branch-review/code-quality-analyzer.md`
- Performance: `bin/agents/branch-review/performance-analyzer.md`
- Verification: `bin/agents/branch-review/verification-agent.md`
- Security: `bin/agents/security/security-orchestrator.md` (reused)

---

## Example Output

```
## Branch Review: fix/160

### Summary

| Check | Status | Issues |
|-------|--------|--------|
| Security | ISSUES FOUND | 1 critical |
| Code Quality | ISSUES FOUND | 5 medium |
| Performance | ISSUES FOUND | 3 high |
| Breaking Changes | ISSUES FOUND | 1 critical |
| Dependencies | OK | WPML API verified |

### Critical Issues (P0)

1. **[SECURITY] Unsanitized $_SERVER['REQUEST_URI']**
   - Files: includes/front.php (3 locations)
   - Risk: ReDoS attack vector
   - Fix: Add sanitization helper

2. **[BREAKING] Cart display settings changed**
   - File: includes/data.php
   - Impact: Cart position defaults may affect existing sites
   - Fix: Preserve backward compatibility

### Recommended Actions

Run `/branch-review fix` to apply all fixes automatically.
```

---

## Integration with Security Scan

This command REUSES the existing security scanning system:

- Does NOT duplicate security logic
- Invokes `/security-scan` or security agents
- Aggregates security findings with other categories

---

## Reports

Reports saved to: `bin/agents/branch-review/reports/`

```
reports/
├── review-fix-160-20251231.json
├── plan-fix-160-20251231.json
└── verify-fix-160-20251231.json
```
