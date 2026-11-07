# PagePermission — Implementation Guide

Shared permission helper for **custom pages** (hand-coded pages in
`app/pages/`) across FMD, ACCOUNTING, TREASURY, UAC and any PHPMaker-based
system in this repo.

Reads permissions from `uac.user_level_permissions` using the same bitwise
flags the **UAC → Security Management** page writes.

---

## When to use this

Use `PagePermission` when your page is **not** an auto-generated PHPMaker
table page (List/Add/Edit/Delete/View). PHPMaker's built-in
`PermissionMiddleware` already gates those.

You need `PagePermission` for:

- Custom dashboards, forms, wizards under `app/pages/*.php`
- Custom Slim API routes under `app/api/*.php` (or `app/routes/*.php`)
- Anything where you want to expose `canEdit / canAdd / ...` flags to the
  browser JavaScript

---

## One-time prerequisite per system

Each system's `app/api.php` must already boot `PagePermission`. Check for
these two lines near the top:

```php
require_once dirname(__DIR__, 2) . '/_shared/PagePermission.php';
\PagePermission::boot('PHPMaker2024\\ACCOUNTING'); // change namespace per system
```

FMD, ACCOUNTING (GAD/DCD/BAPD/PCDS), TREASURY, and UAC all boot this already
— you shouldn't need to add it unless you're wiring a new system.

---

## Naming convention (CRITICAL — read this before coding)

Every layer must agree on the exact same `table_name`. The convention is:

```
{SYSTEM_CODE}PageName.php
```

- **PascalCase** file name with the `.php` suffix (matching PHPMaker's own
  custom-page convention, e.g. `BankJevCreate.php`, `JournalEntry.php`)
- The physical file on disk can be kebab-case (`permission-test.php`) — the
  DB key doesn't have to match the filename
- `SYSTEM_CODE` is whatever appears in the `systems.system_code` column and
  in `{SYSTEM_CODE}...` entries of that system's `src/userlevelsettings.php`

**Example — ACCOUNTING-GAD:**

| Layer | Value |
|---|---|
| Physical file on disk | `ACCOUNTING-GAD/app/pages/permission-test.php` |
| DB `table_name` | `{ACCOUNTING-GAD}PermissionTest.php` |
| `userlevelsettings.php` entry [0] | `PermissionTest.php` |
| `userlevelsettings.php` entry [1] | `PermissionTest` |
| PagePermission constructor arg | `'PermissionTest.php'` |
| Menu `AllowListMenu('...')` | `{ACCOUNTING-GAD}PermissionTest.php` |
| Controller route name | `custom.PermissionTest` |

**If any of these don't match, PHPMaker silently falls back to a
"no permission" state and the page redirects to the dashboard.**

---

## Step-by-step: add a new custom page

Below, replace:

- `{SYSTEM}` → system folder (e.g. `ACCOUNTING-GAD`)
- `{SYSTEM_CODE}` → system code as stored in DB (e.g. `ACCOUNTING-GAD`)
- `{NAMESPACE}` → PHP namespace (e.g. `PHPMaker2024\\ACCOUNTING`)
- `{Name}` → PascalCase page identifier (e.g. `PermissionTest`)
- `{name-file}` → the physical filename you want on disk (e.g. `permission-test`)

### 1. Register the page in `userlevelsettings.php`

Open `{SYSTEM}/src/userlevelsettings.php`, find `$USER_LEVEL_TABLES = [...]`,
add one line before the closing `];`:

```php
["{Name}.php","{Name}","{Human Readable Title}",true,"{{SYSTEM_CODE}}","{Name}"],
```

Tuple format: `[db_name, container_var, caption, allowed_for_update, project_id, url]`

### 2. Create the page file — `{SYSTEM}/app/pages/{name-file}.php`

```php
<?php
namespace {NAMESPACE};

// Bootstrap PagePermission. From app/pages/ we need up 3 levels to reach
// the repo root where _shared/ lives. For pages nested one folder deeper
// (app/pages/SUB/foo.php) use dirname(__DIR__, 4); two deeper, 5.
require_once dirname(__DIR__, 3) . '/_shared/PagePermission.php';
\PagePermission::boot('{NAMESPACE}');

// Page-render requests don't carry a bearer token in the Authorization
// header — they arrive through PHPMaker's session-based flow. Build a
// JWT-like payload from the session keys PHPMaker sets at login.
$jwtPayload = [
    'userlevel' => $_SESSION['{SESSION_PREFIX}_Status_UserLevel'] ?? '-2',
    'userid'    => $_SESSION['{SESSION_PREFIX}_Status_UserID']    ?? null,
    'username'  => $_SESSION['{SESSION_PREFIX}_Status_UserName']  ?? null,
];

// Server-side gate — MUST match the userlevelsettings entry exactly.
$perm = new \PagePermission('{SYSTEM_CODE}', '{Name}.php', $jwtPayload);

if (!$perm->canView()) {
    http_response_code(403);
    echo '<div class="alert alert-danger m-4"><strong>403 — Access denied.</strong></div>';
    return;
}

// Expose flags to JavaScript for row-level buttons / dynamic UI
$permJson = json_encode($perm->toArray());
?>

<h2>{Human Readable Title}</h2>

<!-- Prefer server-side hiding for static buttons — no flash on load -->
<?php if ($perm->canEdit()): ?>
    <button class="btn btn-primary">Edit Something</button>
<?php endif; ?>

<div id="content"></div>

<script>
    // Only needed for buttons built dynamically in JS (e.g. table row actions)
    const PERM = <?= $permJson ?>;
    if (!PERM.canDelete) {
        document.querySelectorAll('.btn-delete').forEach(b => b.style.display = 'none');
    }
</script>
```

**Note on `{SESSION_PREFIX}`:** each PHPMaker project stores session keys
under its own prefix — `ACCOUNTING_Status_UserLevel`, `FMD_Status_UserLevel`,
`UAC_Status_UserLevel`, etc. Check what the target system uses by
`grep`-ing an existing page (e.g. `_Status_UserLevel`).

**When to skip the manual `$jwtPayload`:** if your page is only reached
via an API/AJAX call that includes a bearer token in the header, you can
omit the third argument — `PagePermission` auto-resolves from the header.
Page-render requests almost always need the session-based payload.

### 3. Create API routes — `{SYSTEM}/app/api/{name-file}.php` (or `app/routes/`)

```php
<?php
namespace {NAMESPACE};

// Middleware factories — one per required permission flag
$requireList   = \PagePermission::middleware('{SYSTEM_CODE}', '{Name}.php', \PagePermission::ALLOW_LIST);
$requireAdd    = \PagePermission::middleware('{SYSTEM_CODE}', '{Name}.php', \PagePermission::ALLOW_ADD);
$requireEdit   = \PagePermission::middleware('{SYSTEM_CODE}', '{Name}.php', \PagePermission::ALLOW_EDIT);
$requireDelete = \PagePermission::middleware('{SYSTEM_CODE}', '{Name}.php', \PagePermission::ALLOW_DELETE);

$app->get('/{name-file}/list', function ($request, $response) {
    // ... your handler ...
    return $response->withJson(['success' => true]);
})->add($requireList)->add($jwtMiddleware);

$app->post('/{name-file}/create', function ($request, $response) {
    // ... your handler ...
    return $response->withJson(['success' => true]);
})->add($requireAdd)->add($jwtMiddleware);

// etc.
```

If your system uses **explicit includes** (like ACCOUNTING-GAD), also add:

```php
// In {SYSTEM}/app/api.php, at the end of the include block:
include(dirname(__DIR__, 1) . "/app/api/{name-file}.php");
```

If your system uses **auto-glob** (like FMD), dropping the file in
`app/routes/` is enough.

### 4. Create the controller — `{SYSTEM}/controllers/{Name}Controller.php`

```php
<?php
namespace {NAMESPACE};

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use {NAMESPACE}\Attributes\Map;

class {Name}Controller extends ControllerBase
{
    #[Map(["GET", "POST", "OPTIONS"], "/{Name}[/{params:.*}]", [PermissionMiddleware::class], "custom.{Name}")]
    public function custom(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "{Name}");
    }
}
```

### 5. Create the model (Page class) — `{SYSTEM}/models/{Name}.php`

Copy an existing custom-page model (e.g. `models/BankJevCreate.php`) and
replace every occurrence of `BankJevCreate` with `{Name}`. Key properties
to change:

```php
public $PageObjName = "{Name}";
public $CurrentPageName = "{Name}Controller";
public $Heading = "{Human Readable Title}";

// In __construct():
define(PROJECT_NAMESPACE . "TABLE_NAME", '{Name}.php');

// In setupBreadcrumb():
$Breadcrumb = Breadcrumb::create("index")
    ->add("custom", "{Name}", CurrentUrl(), "", "{Name}", true);
```

### 6. Create the view wrapper — `{SYSTEM}/views/{Name}.php`

```php
<?php
namespace {NAMESPACE};

${Name} = &$Page;
?>
<?php $Page->showMessage(); ?>
<?php include(dirname(__DIR__, 1) . "/app/pages/{name-file}.php"); ?>

<?= GetDebugMessage() ?>
```

### 7. Add to the sidebar / navbar — `{SYSTEM}/views/menu.php`

Both the top navbar and side menu need a line:

```php
// Top navbar
$topMenu->addMenuItem(
    999,                              // unique menu ID (use a new number)
    "mi_{Name}",                      // internal name
    "{Human Readable Title}",         // caption (or use $Language->menuPhrase(...))
    "{Name}",                         // URL slug
    -1,                               // parent (-1 = top-level)
    "",
    AllowListMenu('{{SYSTEM_CODE}}{Name}.php'),
    false, false,
    "fa-solid fa-file",               // icon
    "", true, false                   // second icon, show, sidebar flag
);

// Sidebar — same line, last param `true` instead of `false`
$sideMenu->addMenuItem(999, "mi_{Name}", ..., "", true, true);
```

### 8. Clear PHPMaker route caches

New controllers are only discovered when the route-attribute cache is
regenerated:

```bash
rm {SYSTEM}/log/cache/RouteAttributes.php
rm {SYSTEM}/log/cache/ApiRouteAttributes.php
```

Hit the app once to trigger regeneration.

### 9. Sync + tick permissions in UAC

1. Log in as **admin** → **UAC → Security Management**
2. **System Selection** dropdown → pick `{SYSTEM_CODE}`
3. Click **Sync Permission** (yellow button) — this inserts empty
   permission rows for every user level
4. Pick a user level → find the new page in the grid → tick the flags you
   want (View is required to see the page at all) → **Save Changes**
5. **Log out and back in** as the user you want to test — the session
   caches permissions at login and won't pick up changes otherwise

---

## Retrofit workflow — adding PagePermission to an existing page

For pages that are already in production and have users, follow this
order to minimize disruption:

### Stage 1 — Ship the code, still-open access (no visible change)

1. Add `require + boot + $perm` at the top of the page — but leave the
   `if (!$perm->canView())` gate as a **warning-only** during rollout
   (log to Monolog instead of returning 403).
2. Register the page in `src/userlevelsettings.php`.
3. Sync Permission in UAC to create the DB row.
4. **Grant every existing user level all flags** on the new row. This
   preserves current behavior — nothing changes for existing users yet.

### Stage 2 — Protect destructive routes

5. In the route file, add the middleware factories + attach
   `->add($jevRequireAdd/Edit/Delete)` to POST/PUT/PATCH/DELETE routes.
   Leave GETs open.
6. Deploy. Because every user level still has all flags, no user sees a
   behavior change — but any unauthorized access attempt (e.g. through
   API scripting outside the app) is now blocked.

### Stage 3 — Client-side UX

7. Add server-side `<?php if ($perm->canX()) ?>` around static buttons.
8. Add `const PERM = <?= $permJson ?>` in JS and gate row-level buttons.
9. Deploy. Still no visible change (all users have all flags).

### Stage 4 — Tighten permissions per role

10. In Security Management, untick flags on user levels that shouldn't
    have them. Now users start seeing hidden buttons / 403s exactly as
    intended.
11. **Announce the log-out / log-back-in step to affected users** — the
    session cache means their currently-open browsers won't see the new
    restrictions until they re-login.

**Why staged:** Stage 1 lets you validate the code path with real users
having no restrictions. Stage 2 hardens the backend. Stage 3 polishes
UX. Stage 4 is the actual policy rollout — and by then every layer is
already in production, so only the DB toggle matters.

If you skip stages and go straight to "code + tighten permissions", you
risk shipping a bug that locks out every user simultaneously.

---

## Available permission flags

Combine with `|` (bitwise OR) if you need multiple:

| Constant | Value | Meaning |
|---|---:|---|
| `PagePermission::ALLOW_ADD` | 1 | Create records |
| `PagePermission::ALLOW_DELETE` | 2 | Delete records |
| `PagePermission::ALLOW_EDIT` | 4 | Update records |
| `PagePermission::ALLOW_LIST` | 8 | See the page in menus / list rows |
| `PagePermission::ALLOW_ADMIN` | 16 | Admin-level action |
| `PagePermission::ALLOW_VIEW` | 32 | Open the page (view details) |
| `PagePermission::ALLOW_SEARCH` | 64 | Search records |
| `PagePermission::ALLOW_IMPORT` | 128 | Bulk import |
| `PagePermission::ALLOW_LOOKUP` | 256 | Autocomplete / lookup dropdown |
| `PagePermission::ALLOW_PUSH` | 512 | Push notification / broadcast |
| `PagePermission::ALLOW_EXPORT` | 1024 | Export (Excel / PDF) |

**Admin bypass:** users with userlevel `-1` bypass all checks and get
`PHP_INT_MAX` for the permission integer.

---

## Gating navigation buttons vs on-page actions (important pattern)

PHPMaker registers a **separate** `user_level_permissions` row for every
custom page. A JEV List page and a JEV Create page are two rows,
independently ticked in Security Management. This creates a design
question for buttons that navigate from one page to another: which row
should control the button's visibility?

**Recommended pattern — require BOTH rows to grant permission.**

For a "Create New JEV" button on the list page that links to the create
page, check both the current page's Add flag AND the destination page's
View flag. Unticking either row hides the button.

**Why the double-check:**

- **Current page's canAdd** is what admins intuitively reach for — "I'm
  looking at the JEV List permissions, unticking Add here should hide
  the create button on that page."
- **Destination's canView** guards against a broken flow — even if the
  button shows, the destination page's own gate would 403 the user.
  Hiding the button early is a better UX than a click-then-denied.

**Example — two `PagePermission` instances on the same page:**

```php
// Current page — controls page access + on-page actions
$perm = new \PagePermission('ACCOUNTING-GAD', 'JevListing.php', $jwtPayload);

// Destination of a navigation button — controls the "Create" link visibility
$permJevAdd = new \PagePermission('ACCOUNTING-GAD', 'JevAdd.php', $jwtPayload);

// Page-level gate
if (!$perm->canView()) { /* 403 */ }

// Navigation button — requires BOTH: current page grants Add, destination
// grants View. Unticking either row hides the button.
if ($perm->canAdd() && $permJevAdd->canView()) {
    echo '<a href="' . GetUrl('JevAdd') . '">Create New JEV</a>';
}

// On-page action button — uses only the CURRENT page's flag
if ($perm->canExport()) {
    echo '<button onclick="jevListing.exportJEVList()">Export</button>';
}

// On-page action button — uses only the CURRENT page's flag
if ($perm->canEdit()) {
    echo '<button onclick="jevListing.approveSelected()">Approve Selected</button>';
}
```

**Which flag(s) to check per button purpose:**

| Button purpose | Check |
|---|---|
| Navigate to Create/Add page | current `canAdd` **&&** destination `canView` |
| Navigate to View/Detail page | current `canView` **&&** destination `canView` |
| Navigate to Edit page | current `canEdit` **&&** destination `canView` |
| Perform an action on current-page data (Export, Approve, Reject, Post, in-place Delete) | current page's relevant flag |

**Complementary pattern — destination page must also enforce parent gate:**

Hiding a "Create X" button on the parent listing is not enough. If a user
knows the direct URL (e.g. `/ACCOUNTING-GAD/JevAdd`), they can bypass the
button and reach the Create page. The Create page **must** enforce the
same parent-listing check on its own render:

```php
// On the Create page itself (e.g. jev-create.php)
$perm       = new \PagePermission('ACCOUNTING-GAD', 'JevAdd.php',      $jwtPayload);
$permParent = new \PagePermission('ACCOUNTING-GAD', 'JevListing.php',  $jwtPayload);

// Access requires BOTH: this page's own View AND the parent listing's Add.
// Mirror the same double-check that gates the "Create X" button on the parent.
if (!$perm->canView() || !$permParent->canAdd()) {
    http_response_code(403);
    echo '<div class="alert alert-danger m-4">403 — Access denied.</div>';
    return;
}
```

Without this mirror-gate on the Create page, unticking Add on the parent
listing only hides the button — the user can still reach the form by
typing the URL directly. Symptom: "I unticked Add but the Create page
still loads."

The rule of thumb: **the API middleware guards the action; the button
visibility gates the navigation or UX affordance.** Server enforcement
is what matters for security — button visibility is UX polish.

---

## Wiring `PERM` into JavaScript (for dynamic buttons)

Static buttons rendered by PHP can be wrapped in `<?php if ($perm->canX()) ?>`.
But buttons built by JavaScript at runtime (table row actions, dynamic
toolbars, modal buttons) can't use PHP — they need a JS-side flag object.

The pattern is: `json_encode($perm->toArray())` in PHP, and pass the
result to a `const PERM` at the top of your `<script>` block, next to
other page-level constants (`CURRENT_USER`, etc.):

```php
// In the PHP header of your page file
$permJson = json_encode($perm->toArray());
```

```html
<script>
    /* Current user injected from PHP session */
    const CURRENT_USER = {
        id:   <?= (int)$currentUserId ?>,
        name: <?= json_encode($currentUserName) ?>,
    };

    /* PagePermission flags for this page — mirrors $perm on the server */
    const PERM = <?= $permJson ?>;
</script>
```

Then anywhere in your JS row-builder:

```javascript
renderRowActions(row) {
    let html = '';

    // Show Edit only if the user's role grants it (canEdit is baked with
    // isAdmin bypass — no need for a separate isAdmin check).
    if (PERM.canEdit) {
        html += `<button onclick="editRow(${row.id})">Edit</button>`;
    }

    // Layered check: workflow logic AND page-level permission. The workflow
    // rule says "this user is on the current approval step"; canEdit says
    // "this user is generally allowed to modify records on this page".
    // Both must be true to show the button.
    if (this._actionableStatuses.has(row.status) && PERM.canEdit) {
        html += `<button onclick="approveRow(${row.id})">Approve</button>`;
    }

    return html;
}
```

**Reminder:** Client-side hiding is UX only. The `->add($jevRequireEdit)`
middleware on the API route is what enforces security — a user tampering
with DevTools to un-hide a button will still hit `403` from the server.

---

## Directory path reference

`require_once dirname(__DIR__, N)` from various file locations to reach
the repo root (where `_shared/` lives):

| File location | Use `dirname(__DIR__, ?)` |
|---|---|
| `{SYSTEM}/app/api.php` | `2` |
| `{SYSTEM}/app/pages/*.php` | `3` |
| `{SYSTEM}/app/pages/SUB/**.php` | `4` |
| `{SYSTEM}/app/routes/*.php` | `3` |
| `{SYSTEM}/app/api/*.php` | `3` |
| `{SYSTEM}/app/services/*.php` | `3` |

---

## JavaScript integration

`$perm->toArray()` returns:

```json
{
  "isAdmin":   false,
  "canAdd":    true,
  "canDelete": false,
  "canEdit":   true,
  "canList":   true,
  "canView":   true,
  "canSearch": true,
  "canImport": false,
  "canExport": false,
  "canLookup": false,
  "canPush":   false,
  "permission": 109
}
```

Typical usage in the page's `<script>` block:

```javascript
const PERM = <?= json_encode($perm->toArray()) ?>;

// Hide entire sections
if (!PERM.canEdit) document.getElementById('edit-panel').remove();

// Disable individual buttons
document.querySelectorAll('.btn-delete').forEach(btn => {
    if (!PERM.canDelete && !PERM.isAdmin) {
        btn.disabled = true;
        btn.title = 'You do not have Delete permission.';
    }
});
```

**Client-side visibility is UX only — never rely on it for security.**
The API middleware still enforces on the backend, so a user tampering
with DevTools will hit a 403 from the server.

---

## Common pitfalls (all of these will silently fail)

1. **Kebab-case vs PascalCase mismatch.** If your `userlevelsettings.php`
   entry uses `["permission-test.php","permission_test", ...]` but your
   controller's route is `custom.PermissionTest`, PHPMaker looks up
   `{SYSTEM}PermissionTest.php` while your DB row is under
   `{SYSTEM}permission-test.php` — no match → dashboard redirect.
   **Always use PascalCase + `.php` in the first two tuple slots.**

2. **Forgot to `boot()` on page renders.** `app/api.php` boots
   PagePermission for API routes, but page requests never touch it. Every
   custom page must include:
   ```php
   require_once dirname(__DIR__, 3) . '/_shared/PagePermission.php';
   \PagePermission::boot('{NAMESPACE}');
   ```

3. **Stale `RouteAttributes.php` cache.** After adding a new controller,
   delete both cache files under `{SYSTEM}/log/cache/` — PHPMaker only
   regenerates on cache-miss.

4. **Ticked permissions but nothing changed.** The user's session privs
   are loaded at login. Log out and back in after every permission change.

5. **Ticks went to the wrong row.** If you renamed the page in
   `userlevelsettings.php` after already syncing, the UAC grid may still
   show ticks against the OLD table_name. Delete the orphan row and
   re-sync:
   ```sql
   DELETE FROM uac.user_level_permissions
    WHERE table_name = '{SYSTEM}old-name.php';
   ```

6. **`Container("{Name}")` fails.** PHPMaker's `PermissionMiddleware`
   calls `Container($table)` where `$table` is parsed from the route
   name. If no model class matches, some page paths crash silently.
   Always create `models/{Name}.php` even if it's just boilerplate.

7. **Composer autoload not finding the new class.** If your model class
   isn't loaded, `runPage()` throws `HttpNotFoundException` (404). PSR-4
   autoloading from `models/` normally works without regeneration, but if
   the project is running with `composer dump-autoload -o` (classmap only)
   you'll need to re-dump:
   ```bash
   composer dump-autoload
   ```

8. **Table name sanitizer stripping the dot.** An earlier version of the
   shared class had a regex that stripped `.` from the sanitized
   `table_name`, so `PermissionTest.php` became `PermissionTestphp` and
   no DB row matched. Fixed in the current class — but if you ever fork
   or copy `PagePermission.php` elsewhere, keep `\.` in the character
   class on line ~271: `'/[^a-zA-Z0-9_\-\.\{\}]/'`.

9. **Page render request never called `boot()`.** `app/api.php` boots
   PagePermission for API routes, but page-render requests (`/PageName`)
   arrive through the PHPMaker controller flow, not through `app/api.php`.
   The internal `$fnExecuteScalar` closure is `null` and every query
   silently returns `0`. Always call `\PagePermission::boot(...)` at the
   top of the page file too — it's idempotent, so calling twice is safe.

10. **Checking the current page's `canAdd` for a "Create X" button that
    navigates elsewhere.** See the "Gating navigation buttons" section
    above. Symptom: admin ticks Add on the Create page's row (correct
    intuition), button still hidden because the code checks the list
    page's Add flag instead of the create page's View flag.

11. **Multiple related DB rows for one feature.** In Security Management,
    a single feature like "Journal Entry Vouchers" may show up as
    3 separate rows: `JevListing.php` (the list), `JevView.php` (detail),
    `JevAdd.php` (create). Each is a separate `user_level_permissions`
    entry. Search the UAC grid before ticking — you may need to grant
    permission on 2-3 rows for the whole feature to work.

12. **Ticking "Delete" shows no visible button.** Pages that follow the
    **soft-delete pattern** (accounting data, financial records, chart
    of accounts, ledgers) intentionally do not expose a hard-delete
    trash icon. Instead, they surface a **Deactivate** button that
    calls a `PATCH .../toggle-status` route — which is gated by
    `canEdit`, not `canDelete`. The `Delete` tick on such a row is a
    **backend-only lockdown**: it prevents anyone from calling the
    `DELETE /...` API routes (via curl / Postman / scripting) even if
    they somehow have UI access. This is intentional. For financial
    tables, **prefer soft-delete + leave `Delete` unticked for almost
    all roles**. Hard-deleting a record that has downstream references
    (voucher lines, ledger entries, audit trail) breaks referential
    integrity and is very hard to recover from.

    **Rule of thumb per page type:**

    | Page type | Delete UX | `canDelete` tick |
    |---|---|---|
    | Financial records (COA, ledgers, JEVs, vouchers) | Deactivate button (soft) | Untick for most roles — backend lockdown only |
    | Reference data (RCs, lookup tables, users, roles) | Deactivate button (soft) preferred, hard-delete optional | Untick unless there's a cleanup case |
    | Configuration / test data (templates, drafts) | Hard-delete acceptable | Tick for admin/editor roles |

---

## Reference implementations

### Small, isolated demo — Permission Test

The simplest end-to-end example. Use this as a template for **new**
custom pages:

| File | Purpose |
|---|---|
| `ACCOUNTING-GAD/app/pages/permission-test.php` | Page content |
| `ACCOUNTING-GAD/app/api/permission_test.php` | Protected API routes |
| `ACCOUNTING-GAD/controllers/PermissionTestController.php` | URL controller |
| `ACCOUNTING-GAD/models/PermissionTest.php` | Page class |
| `ACCOUNTING-GAD/views/PermissionTest.php` | View wrapper |
| `ACCOUNTING-GAD/src/userlevelsettings.php` (registered entry) | Sync registration |
| `ACCOUNTING-GAD/views/menu.php` (registered menu item) | Navbar + sidebar |

URL: `/ACCOUNTING-GAD/PermissionTest`

Copy each file, rename `PermissionTest` → your page's PascalCase name,
adjust namespaces, and follow the checklist above.

### Real-world retrofit — JEV Listing

The larger example, showing how to add `PagePermission` to an **existing**
page with many API routes:

| File | What to look at |
|---|---|
| `ACCOUNTING-GAD/app/pages/ACC-GAD/BABR/jev-listing.php` | Session-based `$jwtPayload` build; two `PagePermission` instances (`$perm` for current, `$permJevAdd` for destination); double-check pattern on "Create New JEV" button; `<?php if ($perm->canExport()) ?>` on Export button; `const PERM = <?= $permJson ?>` in the JS block; row-level Edit button gated inside the JS row-render function with layered workflow + `PERM.canEdit` check |
| `ACCOUNTING-GAD/app/api/jev_listing.php` | Three middleware factories at top (`$jevRequireAdd/Edit/Delete`); attached to 17 destructive routes as `->add($jevRequireEdit)->add($jwtMiddleware);`; 22 GET routes left open to any logged-in user (rollout Stage 2) |

URL: `/ACCOUNTING-GAD/JevListing`

Reference this when you're **retrofitting** an existing custom page —
especially for:

- Session-based auth (page-render request without bearer token)
- Two `PagePermission` instances on one page (current + destination)
- Mixing PHP-side gating (static buttons) with JS-side gating (dynamic row buttons)
- The "protect destructive routes but leave GETs open" pattern for
  low-risk rollout on a page with existing users
