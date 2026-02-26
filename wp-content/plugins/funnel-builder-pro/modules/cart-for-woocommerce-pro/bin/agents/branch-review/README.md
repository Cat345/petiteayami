# Branch Review Agents

Comprehensive branch/PR review system with security, code quality, performance, and compatibility analysis.

## Quick Start

```bash
# In Claude Code
/branch-review full    # Complete review
/branch-review quick   # Fast critical-only review
/branch-review fix     # Review and auto-fix
/branch-review plan    # Generate fix plan only
/branch-review verify  # Run verification only
```

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         BRANCH REVIEW ORCHESTRATOR                           │
│                    (branch-review-orchestrator.md)                           │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │
│  │  Security   │  │   Code      │  │ Performance │  │  Breaking   │        │
│  │   Scan      │  │  Quality    │  │  Analyzer   │  │  Change     │        │
│  │ (reuses     │  │  Analyzer   │  │             │  │  Detector   │        │
│  │  security/) │  │             │  │             │  │             │        │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘        │
│         │                │                │                │                │
│         └────────────────┴────────────────┴────────────────┘                │
│                                    │                                         │
│                          ┌─────────▼─────────┐                              │
│                          │   Dependency      │                              │
│                          │   Knowledge       │                              │
│                          │   Agent           │                              │
│                          └─────────┬─────────┘                              │
│                                    │                                         │
│                          ┌─────────▼─────────┐                              │
│                          │   Plan            │                              │
│                          │   Generator       │                              │
│                          └─────────┬─────────┘                              │
│                                    │                                         │
│                          ┌─────────▼─────────┐                              │
│                          │   Verification    │                              │
│                          │   Agent           │                              │
│                          └───────────────────┘                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Agents

| Agent | File | Purpose |
|-------|------|---------|
| Orchestrator | `branch-review-orchestrator.md` | Coordinates entire workflow |
| Dependency Knowledge | `dependency-knowledge-agent.md` | External API verification |
| Code Quality | `code-quality-analyzer.md` | Empty blocks, formatting, PHPDoc |
| Performance | `performance-analyzer.md` | Queries, caching, loops |
| Verification | `verification-agent.md` | PHP lint, PHPCS, tests |

## Integration with Security Agents

This system **reuses** the existing security agents at `bin/agents/security/`:
- Does NOT duplicate security logic
- Invokes security-orchestrator for security scanning
- Aggregates security findings with other categories

## Workflow

### 1. Context Gathering
- Reads CLAUDE.md and docs/
- Understands project architecture
- Identifies external dependencies

### 2. Dependency Knowledge
For each external plugin (WPML, WooCommerce, Elementor):
- Checks if installed locally
- If local: Reads code for API understanding
- If not local: Fetches documentation from web

### 3. Parallel Analysis
Runs simultaneously:
- Security scan
- Code quality check
- Performance analysis
- Breaking change detection

### 4. Plan Generation
Creates structured fix plan (NOT interactive plan mode):
```json
{
  "phases": [
    {"phase": 1, "title": "Security Fixes", "priority": "P0"},
    {"phase": 2, "title": "Breaking Changes", "priority": "P0"},
    {"phase": 3, "title": "Performance", "priority": "P1"},
    {"phase": 4, "title": "Code Quality", "priority": "P2"}
  ]
}
```

### 5. Fix Application
Applies fixes with:
- User approval for each phase
- PHP syntax validation after each fix
- Atomic commits per logical change

### 6. Verification
- PHP lint all modified files
- PHPCS standards check
- PHPUnit tests (if available)
- Manual test checklist generation

## Reports

Saved to `reports/`:
```
reports/
├── review-{branch}-{timestamp}.json
├── plan-{branch}-{timestamp}.json
└── verify-{branch}-{timestamp}.json
```

## Key Features

### Dependency Verification
Ensures external API usage is correct:
```php
// Detects issues like:
in_array($lang, array('en', 'es'))  // WRONG: hardcoded

// Suggests:
$sitepress->get_active_languages()  // CORRECT: dynamic
```

### Atomic Commits
Creates proper commits:
```
security: sanitize REQUEST_URI inputs
fix: revert order statuses to original defaults
perf: add translation caching for WPML
refactor: remove empty code blocks
```

### Verification
Ensures fixes don't break anything:
```bash
php -l includes/*.php
composer phpcs
composer test
```

## Usage Examples

### Full Review
```
/branch-review full
```
Runs complete analysis, generates report, suggests fixes.

### Quick Critical Check
```
/branch-review quick
```
Checks P0 issues only, faster turnaround.

### Auto-Fix
```
/branch-review fix
```
Reviews and applies all fixes with verification.

### Generate Plan Only
```
/branch-review plan
```
Creates fix plan without executing.

## Adding Custom Analyzers

To add a new analyzer:

1. Create `bin/agents/branch-review/{name}-analyzer.md`
2. Follow the output format:
```json
{
  "file": "...",
  "issues": [
    {
      "id": "...",
      "type": "...",
      "severity": "critical|high|medium|low",
      "line": 123,
      "code": "...",
      "message": "...",
      "fix": "..."
    }
  ]
}
```
3. Update orchestrator to invoke new analyzer
