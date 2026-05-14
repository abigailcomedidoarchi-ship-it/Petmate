# Design Document — PetMate UI Redesign

## Overview

This document describes the technical design for the PetMate UI redesign. The goal is to unify the visual language across the entire application — from the public landing page through every role-specific dashboard — while preserving all existing functionality and security controls.

The redesign introduces three coordinated changes:

1. **CSS token update** — a new `--color-sidebar` token (`#1A2E2B`, Dark Teal) replaces the espresso sidebar background, and the active nav item style is updated to use a teal-tinted background with a 3 px left accent bar.
2. **Shared layout shell** — `includes/header.php` becomes the single source of truth for the sidebar and topbar across all authenticated pages, with role-aware nav items built from a PHP array.
3. **Subfolder dashboard architecture** — each role gets its own folder under `dashboards/`, with every file following a strict pattern: auth guards first, then DB queries, then the layout shell, then page content.

The landing page (`index.php`) is already substantially built. The design specifies the exact section structure and any remaining gaps.

---

## Architecture

### Request Flow

```
Browser → index.php (landing) or login.php
         ↓ (after login)
         includes/auth.php → redirectBasedOnRole()
         ↓
         dashboards/{role}/index.php
         ├── require_once '../../includes/db.php'       (PDO connection)
         ├── require_once '../../includes/auth.php'     (session + requireRole)
         ├── requireRole('{role}')                      (auth guard — MUST be first)
         ├── require_permission('view_dashboard')       (RBAC guard)
         ├── [DB queries using $pdo]
         ├── require_once '../../includes/header.php'   (opens HTML, sidebar, topbar)
         ├── [page content HTML]
         └── require_once '../../includes/footer.php'   (closes HTML)
```

### File Layout

```
Petmate/
├── index.php                          ← public landing page (already built)
├── login.php / register.php / logout.php
├── includes/
│   ├── db.php                         ← PDO connection ($pdo)
│   ├── auth.php                       ← requireRole(), redirectBasedOnRole()
│   ├── rbac.php                       ← require_permission()
│   ├── header.php                     ← shared layout shell (sidebar + topbar)
│   └── footer.php                     ← closes layout shell
├── assets/css/style.css               ← design tokens + component styles
└── dashboards/
    ├── pet_owner/
    │   ├── index.php, my_pets.php, book_sitter.php, my_bookings.php
    │   ├── messages.php, bills.php, visit_records.php, settings.php
    ├── csr/
    │   ├── index.php, pet_info.php, pet_records.php, billing.php
    │   ├── visit_summaries.php, messages.php, settings.php
    ├── vet/
    │   ├── index.php, exam_room.php, assessments.php
    │   ├── treatment_plan.php, prescriptions.php, settings.php
    ├── vet_technician/
    │   ├── index.php, assessment_queue.php, record_results.php
    │   ├── treatment_details.php, settings.php
    └── vet_assistant/
        ├── index.php, prepare_room.php, instructions.php
        ├── discharge_prep.php, administer.php, discharge.php, settings.php
```

### Dependency Graph

```
Every dashboard page
  └── requires db.php          (provides $pdo)
  └── requires auth.php        (provides requireRole, redirectBasedOnRole)
       └── requires rbac.php   (provides require_permission)
       └── requires session_guard.php
       └── requires dlp.php
  └── requires header.php      (reads $_SESSION, builds nav, opens HTML)
  └── requires footer.php      (closes HTML)
```

---

## Components and Interfaces

### 1. CSS Design Tokens (`assets/css/style.css`)

**Change:** Add `--color-sidebar` to `:root` and update `.sidebar` and `.sidebar-nav li a.active`.

```css
:root {
  /* ... existing tokens preserved ... */
  --color-sidebar: #1A2E2B;   /* NEW — Dark Teal sidebar background */
}

.sidebar {
  background-color: var(--color-sidebar);   /* was: var(--color-espresso) */
  /* all other properties unchanged */
}

.sidebar-nav li a.active {
  background: rgba(196, 133, 106, 0.18);   /* teal-tinted, not solid accent */
  color: #FFFFFF;
  border-left: 3px solid var(--color-accent);
  border-radius: 0;
  margin: 0;
  padding: 12px 20px 12px 17px;            /* compensate for 3px border */
}
```

All existing tokens (`--color-bg`, `--color-espresso`, `--color-accent`, `--color-surface`, `--color-border`, etc.) are preserved without modification.

### 2. Shared Header (`includes/header.php`)

The header is already substantially built. The redesign updates it to:

- Use `--color-sidebar` (via the CSS token) for the sidebar background.
- Remove the `review_record.php` link from the CSR nav array.
- Add a `vet` role nav array pointing to `dashboards/vet/` paths.
- Update the `vet_assistant` nav array to include `administer.php` and `discharge.php`.
- Trim the `vet_technician` nav array to the four specified links.

**Nav arrays per role (final specification):**

| Role | Nav Items |
|------|-----------|
| `pet_owner` | Dashboard → `/pet_owner/index.php`, My Pets, Book Sitter, My Bookings, Messages, Bills, Visit Records, Settings |
| `csr` | Dashboard → `/csr/index.php`, Pet Info, Pet Records, Billing, Visit Summaries, Messages, Settings (**no** Review Record) |
| `vet` | Dashboard → `/vet/index.php`, Exam Room, Assessments, Treatment Plan, Prescriptions, Settings |
| `vet_technician` | Dashboard → `/vet_technician/index.php`, Assessment Queue, Record Results, Treatment Details, Settings |
| `vet_assistant` | Dashboard → `/vet_assistant/index.php`, Prepare Room, Instructions, Discharge Prep, Administer, Discharge, Settings |

**Active detection logic:**

```php
$isActive = (strpos($_SERVER['REQUEST_URI'], strtok($href, '?')) !== false);
```

For admin tab-based pages, the tab query parameter is also compared. This logic ensures at most one nav item is active per page load.

**PHP variables exposed to page body:**

| Variable | Source | Example |
|----------|--------|---------|
| `$role` | `$_SESSION['role']` | `'csr'` |
| `$userName` | `$_SESSION['name']` | `'Maria Santos'` |
| `$initials` | Computed from `$userName` | `'MS'` |
| `$roleLabel` | `ucwords(str_replace('_', ' ', $role))` | `'Csr'` |

**Initials computation:**

```php
$initials = strtoupper(implode('', array_map(
    fn($w) => $w[0],
    array_slice(explode(' ', trim($userName)), 0, 2)
)));
```

### 3. Dashboard Page Pattern

Every dashboard file follows this exact pattern:

```php
<?php
require_once '../../includes/db.php';
require_once '../../includes/auth.php';

requireRole('{role}');                        // MUST come before any DB query
require_permission('view_dashboard');

// --- DB queries using $pdo ---
$count = (int)$pdo->query("SELECT COUNT(*) FROM ...")->fetchColumn();

require_once '../../includes/header.php';
?>

<div class="action-bar">
  <div>
    <h1 class="page-heading">Page Title</h1>
    <p class="breadcrumb">PetMate <span>›</span> Role <span>›</span> Page</p>
  </div>
</div>

<!-- page content using .card, .stat-card, .table-responsive, etc. -->

<?php require_once '../../includes/footer.php'; ?>
```

**Settings pages** additionally require `auth.php` for password change functionality but follow the same pattern.

### 4. `includes/auth.php` — `redirectBasedOnRole()`

The function is updated to point all roles to their subfolder `index.php`:

```php
function redirectBasedOnRole($role) {
    switch ($role) {
        case 'admin':
            header("Location: /Petmate/dashboards/admin/index.php");
            break;
        case 'pet_owner':
            header("Location: /Petmate/dashboards/pet_owner/index.php");
            break;
        case 'csr':
            header("Location: /Petmate/dashboards/csr/index.php");
            break;
        case 'vet':
            header("Location: /Petmate/dashboards/vet/index.php");
            break;
        case 'vet_technician':
            header("Location: /Petmate/dashboards/vet_technician/index.php");
            break;
        case 'vet_assistant':
            header("Location: /Petmate/dashboards/vet_assistant/index.php");
            break;
        default:
            header("Location: /Petmate/login.php");
            break;
    }
    exit();
}
```

Note: The `veterinarian` role alias is removed; the canonical role name is `vet`.

### 5. Landing Page (`index.php`)

The landing page is already built. The design specifies the canonical section structure:

```
index.php
├── <nav class="lp-nav">          Navbar (fixed, scroll-aware)
├── <section class="lp-hero">     Hero (full-viewport, CTA buttons)
│   └── <div class="lp-ticker">  Marquee ticker (8+ items, pauses on hover)
├── <section class="lp-about">   About (two-column: blush placeholder | text + features)
│   └── CTA → register.php       "Discover More" / "Get Started Today"
└── <footer class="lp-footer">   Footer (4-column grid, social icons, bottom bar)
```

The hero subtitle paragraph (`<p class="lp-hero-sub">`) is currently empty in the existing file and should be populated with descriptive copy.

---

## Data Models

### DB Queries Per Dashboard Index Page

Each index page queries real data. No hardcoded dummy values are permitted.

#### `dashboards/pet_owner/index.php`

```sql
-- Total registered pets
SELECT COUNT(*) FROM pets WHERE owner_id = ?

-- Active bookings (pet_records with non-terminal status)
SELECT COUNT(*) FROM pet_records pr
JOIN pets p ON pr.pet_id = p.id
WHERE p.owner_id = ? AND pr.status NOT IN ('completed', 'rejected')

-- Outstanding bill amount (sum of unpaid bills)
SELECT COALESCE(SUM(amount), 0) FROM bills
WHERE owner_id = ? AND status = 'unpaid'

-- Unread messages (placeholder — 0 until messages table exists)
-- Currently: $unread_messages = 0;
```

#### `dashboards/csr/index.php`

```sql
-- Pending validations
SELECT COUNT(*) FROM pet_records WHERE status = 'pending'

-- New records (created today)
SELECT COUNT(*) FROM pet_records WHERE DATE(visit_date) = CURDATE()

-- Billing queue (records awaiting billing)
SELECT COUNT(*) FROM pet_records WHERE status = 'pending_billing'
```

#### `dashboards/vet/index.php`

```sql
-- Today's exam rooms (in_use status)
SELECT COUNT(*) FROM examination_rooms WHERE status = 'in_use'

-- Pending assessments
SELECT COUNT(*) FROM assessments WHERE result IS NULL OR result = ''

-- Active treatments (treatment plans in progress)
SELECT COUNT(*) FROM treatment_plans
WHERE workflow_status IN ('in_prep', 'forwarded', 'administered')
```

#### `dashboards/vet_technician/index.php`

```sql
-- Assessment queue (pending tests)
SELECT COUNT(*) FROM assessments WHERE result IS NULL OR result = ''

-- Pending results (assessments submitted today)
SELECT COUNT(*) FROM assessments
WHERE DATE(date) = CURDATE() AND result IS NOT NULL AND result != ''
```

#### `dashboards/vet_assistant/index.php`

```sql
-- Rooms to prepare (validated records awaiting room prep)
SELECT COUNT(*) FROM pet_records WHERE status = 'validated'

-- Active treatments (examination rooms in use)
SELECT COUNT(*) FROM examination_rooms WHERE status = 'in_use'

-- Discharge queue (treatment plans in administered state)
SELECT COUNT(*) FROM treatment_plans WHERE workflow_status = 'administered'
```

### Session Variables

All dashboard pages rely on these session variables set at login:

| Variable | Type | Description |
|----------|------|-------------|
| `$_SESSION['user_id']` | int | Authenticated user's primary key |
| `$_SESSION['role']` | string | One of: `pet_owner`, `csr`, `vet`, `vet_technician`, `vet_assistant`, `admin` |
| `$_SESSION['name']` | string | User's display name (used for initials) |

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Auth guard precedes DB queries and output

*For any* PHP file under `dashboards/{role}/`, the call to `requireRole()` MUST appear before any use of `$pdo` (database query) and before any HTML output. No data is fetched and no content is rendered for an unauthenticated or wrong-role request.

**Validates: Requirements 8.2, 9.2, 10.2, 11.2, 12.2**

### Property 2: Role nav items match specification exactly

*For any* valid role string (`pet_owner`, `csr`, `vet`, `vet_technician`, `vet_assistant`), the nav items array built in `includes/header.php` MUST contain exactly the links specified in Requirement 5.8 — no more, no fewer. The CSR array in particular MUST NOT contain a "Review Record" link.

**Validates: Requirements 5.8, 9.1**

### Property 3: Active nav item is unique per page load

*For any* `REQUEST_URI` string and any role's nav items array, the active detection logic MUST mark at most one nav item as active. It is never the case that two or more nav items are simultaneously active on a single page load.

**Validates: Requirements 5.5**

### Property 4: User-supplied output is always HTML-escaped

*For any* string value read from the database or from `$_SESSION` and rendered into HTML, the value MUST be passed through `htmlspecialchars()` before output. This holds for all dashboard pages across all roles.

**Validates: Requirements 8.3–8.7, 9.3–9.5, 10.3–10.6, 11.3–11.5, 12.3–12.7**

### Property 5: Role redirect mapping is total and correct

*For any* role string in the set `{pet_owner, csr, vet, vet_technician, vet_assistant, admin}`, `redirectBasedOnRole()` MUST produce a `Location` header pointing to the correct subfolder `index.php` path as specified in Requirement 14. No valid role string produces a redirect to a non-existent path.

**Validates: Requirements 14.1–14.6**

---

## Error Handling

### Authentication Failures

- `requireRole()` calls `redirectBasedOnRole()` on role mismatch — the user is sent to their own dashboard, not shown an error page.
- `require_permission()` calls `die()` with an inline HTML error message on permission failure. This is acceptable for internal role mismatches but should be replaced with a proper error page in a future iteration.
- Unauthenticated requests to any dashboard page are redirected to `/Petmate/login.php` by `requireLogin()` inside `requireRole()`.

### Database Errors

- All DB queries in dashboard index pages use `fetchColumn()` with `(int)` cast, so a failed query returns `0` rather than crashing the stat card display.
- Prepared statements with `execute([$param])` are used for all user-supplied parameters to prevent SQL injection.
- No raw `$_GET` or `$_POST` values are interpolated directly into SQL strings.

### XSS Prevention

- All values from `$_SESSION`, database result sets, and `$_GET`/`$_POST` that are rendered into HTML MUST be wrapped in `htmlspecialchars($value)`.
- The `ENT_QUOTES` flag is recommended for attribute contexts but the existing codebase uses the default `ENT_COMPAT`; this is acceptable for the current scope.

### Missing Session Variables

- `$_SESSION['name']` may be empty for some legacy accounts. The initials computation uses `trim()` and `array_slice(..., 0, 2)`, so an empty name produces an empty string `''` rather than a PHP warning.
- `$_SESSION['role']` is always set after `requireRole()` succeeds, so `$role` is safe to use in the page body.

---

## Testing Strategy

### Unit Tests

Unit tests verify specific examples and edge cases for pure logic functions:

- **`redirectBasedOnRole()` mapping** — one test per role verifying the correct `Location` header value.
- **Initials computation** — examples: `"Maria Santos"` → `"MS"`, `"Juan"` → `"J"`, `""` → `""`.
- **Active nav detection** — examples: URI matches first item, URI matches last item, URI matches none.
- **`htmlspecialchars()` coverage** — examples with `<script>`, `"`, `'`, `&` characters.

### Property-Based Tests

Property-based testing is appropriate for this feature because several correctness requirements are universal across a range of inputs (role strings, URI strings, user-supplied data). The PHP property-based testing library **[eris](https://github.com/giorgiosironi/eris)** (or **[PHPUnit + data providers](https://phpunit.de/)** with generated inputs) is recommended.

Each property test MUST run a minimum of **100 iterations**.

Tag format: `Feature: petmate-ui-redesign, Property {N}: {property_text}`

#### Property Test 1 — Auth guard precedes DB queries and output

```
Feature: petmate-ui-redesign, Property 1: Auth guard precedes DB queries and output
```

- **Generator**: Enumerate all PHP files under `dashboards/` subfolders.
- **Assertion**: For each file, parse the source and verify `requireRole(` appears before the first `$pdo->` call and before the first `require_once '../../includes/header.php'`.
- **Implementation**: Static analysis via `file_get_contents` + `strpos` comparison.

#### Property Test 2 — Role nav items match specification exactly

```
Feature: petmate-ui-redesign, Property 2: Role nav items match specification exactly
```

- **Generator**: Generate each valid role string from the set `{pet_owner, csr, vet, vet_technician, vet_assistant}`.
- **Assertion**: Set `$_SESSION['role']` to the generated role, include `header.php` in output buffering, and verify the rendered nav links match the specification exactly (correct count, correct hrefs, no extra items).
- **Special case**: CSR nav MUST NOT contain `/review_record.php`.

#### Property Test 3 — Active nav item is unique per page load

```
Feature: petmate-ui-redesign, Property 3: Active nav item is unique per page load
```

- **Generator**: Generate arbitrary URI strings (alphanumeric paths, query strings, fragment identifiers) and a role string.
- **Assertion**: For the generated URI and role, count the number of nav items where `strpos($uri, strtok($href, '?')) !== false`. The count MUST be 0 or 1.

#### Property Test 4 — User-supplied output is always HTML-escaped

```
Feature: petmate-ui-redesign, Property 4: User-supplied output is always HTML-escaped
```

- **Generator**: Generate arbitrary strings including HTML special characters (`<`, `>`, `"`, `'`, `&`) and XSS payloads.
- **Assertion**: Pass the generated string through `htmlspecialchars()` and verify the output contains no unescaped `<`, `>`, or `"` characters. Then verify that all dashboard template rendering functions call `htmlspecialchars()` on database-sourced values before output.

#### Property Test 5 — Role redirect mapping is total and correct

```
Feature: petmate-ui-redesign, Property 5: Role redirect mapping is total and correct
```

- **Generator**: Generate each valid role string from `{pet_owner, csr, vet, vet_technician, vet_assistant, admin}`.
- **Assertion**: Call `redirectBasedOnRole($role)` with output buffering and header capture. Verify the `Location` header matches the expected path from Requirement 14.

### Integration Tests

- **Login → redirect flow**: Log in as each role and verify the browser lands on the correct subfolder `index.php`.
- **Sidebar rendering**: Load each role's dashboard and verify the sidebar renders the correct nav items.
- **Stat card data**: Load each role's `index.php` and verify stat cards display numeric values (not empty or "0" for a seeded database).

### Smoke Tests

- **CSS token presence**: Verify `--color-sidebar: #1A2E2B` exists in `style.css`.
- **File existence**: Verify all required PHP files exist under each role subfolder.
- **Sidebar CSS**: Verify `.sidebar` uses `var(--color-sidebar)` as `background-color`.
- **Active nav CSS**: Verify `.sidebar-nav li a.active` has `border-left: 3px solid var(--color-accent)`.
- **Responsive breakpoints**: Verify `@media (max-width: 640px)` hides the sidebar and `@media (max-width: 900px)` collapses grid layouts.
