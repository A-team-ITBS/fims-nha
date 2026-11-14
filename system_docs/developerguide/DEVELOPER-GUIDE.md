# FIMS NHA — Developer Guide

> Status: this guide documents the **actual code in this repository** as of **2026-11-13** (branch
> `documents`; the Inspection and Acceptance Report module — §8.4 / §7.5 — added 2026-11-10; §8.5–§8.8
> — Disbursement Vouchers/JEV, Bank Management, Cash Position, Check Processing, Fund Tracking
> (SARO/NCA/Trust/Supplemental), Pricing Requests, and Printing/Letterhead — added 2026-11-13 once
> those modules landed in `app/`). It is deliberately narrower than `README.md` at the repo root — see
> [A note on other documentation in this repo](#a-note-on-other-documentation-in-this-repo) for why
> the two disagree, and don't be misled by the older doc's module list.

## 1. What this repo is

**FIMS** (also called **AFIMS** — Accounting and Financial Information Management System) is the
National Housing Authority's financial system. This specific checkout is a **from-scratch rebuild**:
a single, unified PHPMaker 2024 + Slim 4 project named `FIMS`, started 2026-11-06. It currently
implements a slice of the full target scope — User Access administration plus the early Budgeting
and Procurement workflows of the Financial Management Department. Everything lives in **one**
PHP namespace (`PHPMaker2024\FIMS`), **one** set of `controllers/`, `models/`, `views/` folders, and
**two** Postgres schemas (`uac`, `fims`) — there is no per-module folder split (no `UAC/`, `FMD/`,
`ACCOUNTING-*/`, `TREASURY/`, `REGIONAL/` directories in this repo).

The full intended scope of NHA's financial system — Accounting Department, District offices, FMD,
Regional offices, Treasury Department — is specified in the SRS documents under
`system_docs/modules/*.docx` (and duplicated at `_assets/System Requirements/`). Treat those as the
**target roadmap**, and this guide as **what's actually built today**. Section
[12](#12-roadmap-what-isnt-built-yet) maps the gap.

---

## 2. Architecture at a glance

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Code generator | PHPMaker 2024 (generates `controllers/`, `models/`, `views/`) |
| Web framework | Slim 4 (routing, middleware, DI container) |
| ORM | Doctrine DBAL / ORM (used by the generated layer; hand-written services use raw SQL helpers instead — see §5) |
| Database | PostgreSQL, schemas `uac` and `fims` |
| Auth | JWT (`firebase/php-jwt`), cookie + header bearer token |
| Frontend | Bootstrap 5, AdminLTE 3, jQuery, DataTables, SweetAlert2 |
| Containerization | Docker Compose (`docker-compose.yml`) |
| Reverse proxy | Nginx (`nginx.conf`), app on `http://localhost:8090` |

### Two front controllers

- **`index.php`** (repo root) — renders pages (`views/*.php` wrapping `app/pages/*.php`). Base path `/`.
- **`api/index.php`** — a *separate* Slim app, base path `/api`. Every route registered in
  `app/routes/*.php` is reached externally with an `/api` prefix, e.g. a route declared as
  `/vendors/all` is actually `GET /api/vendors/all`.

Both share the same `src/` framework code, DI container (`src/definitions.php`), and permission
tables — they're two entry points into one codebase, not two apps.

### Route registration (PHPMaker side)

Custom pages are wired through PHP 8 attributes on controller methods (`#[Map(...)]`,
`#[Get(...)]`, etc. in `src/Attributes/`), **not** a central route file. These attributes are
scanned and cached into `log/cache/RouteAttributes.php` (and `ApiRouteAttributes.php`) by:

```bash
composer routes
```

(defined in `composer.json` → `"routes": "PHPMaker2024\\FIMS\\RouteAttributes::dispatch"`). Run this
after adding a new controller or changing a `#[Map(...)]` route pattern. In Docker:

```bash
docker exec fims-nha-php sh -lc "composer routes"
```

`_docker/php/php.ini` sets `opcache.validate_timestamps = 1` with a 2-second revalidate frequency —
unlike some sibling NHA projects, you do **not** need to flush OPcache by hand after editing PHP;
just wait ~2 seconds or reload the page.

### API route registration (Slim side, `app/routes/`)

Unlike the PHPMaker attribute routes above, files in `app/services/` and `app/routes/` are
**auto-discovered** — `app/api.php` globs every `*.php` file in both folders (skipping anything
starting with `_`) and `require_once`s it. **You do not need to manually include a new
service/route file anywhere** — just drop it in the folder:

```php
// app/api.php (excerpt)
foreach (glob($appBase . 'services/[!_]*.php') ?: [] as $f) { require_once $f; }
foreach (glob($appBase . 'routes/[!_]*.php') ?: [] as $f) { require_once $f; }
```

This is simpler than the manual two-include pattern used in older NHA FIMS modules — if you've
worked on a predecessor project, drop that habit here.

---

## 3. Directory map

```
fims-nha/
├── app/
│   ├── api.php           # Slim API bootstrap — JWT/access middleware, auto-discovery of services+routes
│   ├── global.php        # App config / DB connection bootstrap
│   ├── global_script.php
│   ├── preloader.php
│   ├── lib/              # Hand-written helpers not auto-discovered (GlobalService.php, etc.)
│   ├── pages/            # Custom page bodies (HTML+PHP+JS), one file per custom feature
│   │   ├── form/          # Create/edit form fragments (budget, IAR, Memo/CBA, pricing request, …)
│   │   ├── readone/       # Detail/view fragments
│   │   ├── po/            # Purchase Order page/form/readone trio
│   │   └── voucher/       # Disbursement Voucher page/form/readone trio (§8.5)
│   ├── routes/            # Slim route files (auto-discovered, see §2)
│   └── services/          # Business-logic service classes (auto-discovered)
├── controllers/          # PHPMaker-generated — one *Controller.php per page/table (58 files)
├── models/                # PHPMaker-generated page/table classes (191 files)
├── views/                 # PHPMaker-generated view wrappers (thin — include the app/pages/ file)
├── src/                   # Framework: DI definitions, attributes, middleware, user-level settings
├── _shared/               # Cross-cutting: auth.php, PagePermission.php, workflow-manager/, notification.php, file-manager/, document/
├── _guidelines/            # House style guides — READ THESE before writing new pages/routes (§5)
├── _assets/               # SQL source tree + SRS Word docs (see §11)
├── system_docs/           # This guide, ERD notes, module SRS docs, flowcharts
├── install_system.js      # SQL + composer installer (see caveats in §13)
├── docker-compose.yml     # php / nginx / postgres / redis / minio / adminer
└── composer.json          # `composer routes` regenerates the attribute route cache
```

---

## 4. How a feature gets built here: two patterns

Every page in this app falls into one of two categories. Knowing which one you're looking at (or
need to build) saves a lot of confusion.

### Pattern A — PHPMaker-generated CRUD (master/reference tables)

For simple tables that just need Add/Edit/Delete/List/View, PHPMaker generates the full set:
`<Table>Add.php`, `<Table>Edit.php`, `<Table>Delete.php`, `<Table>List.php`, `<Table>View.php`,
`<Table>Grid.php` across `controllers/`, `models/`, `views/`. Examples in this repo: `Users`,
`Departments`, `Positions`, `PositionCategories`, `PlantillaItems`, `Offices`, `UserOffices`,
`DocumentAssetTypes`, `DocumentHeaderAssets`, `DocumentHeaderSlots`, `Systems`, `DepartmentSystems`,
`ApiSystems`, `ApiResources`, `ApiLogs`, `AuditLogs`, `JwtSessions`, `UserLevels`,
`UserLevelPermissions`, `UserLevelAssignments`, `Psgc`.

**Don't hand-edit these** unless you know PHPMaker's generation model — they're meant to be
regenerated from the `.pmp` project file.

### Pattern B — hand-built custom single-page feature (everything else)

This is how almost every business page in this repo works (`BudgetReqPage`, `ChartOfAccountsPage`,
`VendorsPage`, the `po/` Purchase Order trio, `IarModule`, `MainDashboard`, `UserAccess`,
`DepartmentManagement`, …). There is no generated Add/Edit/List split — one controller/model/view
triplet renders one page, and that page does its own CRUD via `fetch()` calls against a Slim API
route. Recipe, with a real example (`BudgetReqPage`):

1. **Service** — `app/services/BudgetReqService.php`. Plain class with methods like `getAll()`,
   `create($data)`; uses `ExecuteRowsAssociative` / `ExecuteScalar` / `QuotedValue` / `DataType`
   (no namespace prefix needed here — see the table in §5).
2. **Route** — `app/routes/budget_req.php`. `$app->get(...)`, `$app->post(...)` etc., chained with
   `->add($accessMiddleware)->add($jwtMiddleware)`. **No manual wiring needed** — auto-discovered
   (§2).
3. **Page body** — `app/pages/budget-req-page.php`. Plain PHP+HTML+JS. Reads `CurrentUserLevel()`
   for role-based UI gating (see §5), fetches data client-side with
   `'X-Authorization': 'Bearer ' + ew.API_JWT_TOKEN`.
4. **Page class** — `models/BudgetReqPage.php`. Clone of an existing single-page model
   (e.g. `models/MainDashboard.php`), renaming `PageObjName` / `CurrentPageName` / breadcrumb /
   heading. PSR-4 autoload — no `composer dump-autoload` needed for a new class under `models/`.
5. **View** — `views/BudgetReqPage.php`. Thin wrapper: sets `$<Name> = &$Page;`, calls
   `$Page->showMessage()`, then `include`s the `app/pages/<name>.php` body.
6. **Controller** — `controllers/BudgetReqPageController.php`:
   ```php
   class BudgetReqPageController extends ControllerBase
   {
       #[Map(["GET", "POST", "OPTIONS"], "/BudgetReqPage[/{params:.*}]", [PermissionMiddleware::class], "custom.budget_req_page")]
       public function custom(Request $request, Response $response, array $args): Response
       {
           return $this->runPage($request, $response, $args, "BudgetReqPage");
       }
   }
   ```
7. **Register the DI binding** — `src/definitions.php`: `"budget_req_page" => \DI\create(BudgetReqPage::class),`
8. **Register the menu entry** — `views/menu.php`:
   ```php
   $sideMenu->addMenuItem(81, "mi_budget_req_page", $Language->menuPhrase("81", "MenuText"),
       "BudgetReqPage", -1, "", AllowListMenu('{FIMS}budget-req-page.php'), false, false, "", "", false, true);
   ```
9. **Register for permission sync** — `src/userlevelsettings.php` → `$USER_LEVEL_TABLES` /
   `$USER_LEVEL_PRIVS` so the page shows up in the permission matrix and gets default-deny rows for
   `Anonymous`/`Default`.
10. **Regenerate the route cache**: `composer routes` (§2). Route is then `/BudgetReqPage`.

Some features skip the model/view/controller scaffold entirely and are **pure API + page**, with no
PHPMaker table model at all — `Vendors` and `Purchase Orders` are the clearest examples: there is no
`models/Vendors.php`; `VendorService.php` + `app/routes/vendor.php` + `app/pages/vendors-page.php`
are the whole feature. Use this lighter form when the page is fully self-contained and doesn't need
PHPMaker's page-object conveniences (breadcrumbs, built-in export, etc.).

---

## 5. Conventions you need to know

### Namespace prefix on data helpers

| Where | Prefix | Example |
|---|---|---|
| `app/services/*.php`, `app/lib/*.php` | `App\` | `App\ExecuteRow(...)` |
| `app/pages/*.php` (custom page bodies) | none | `ExecuteRow(...)` |
| `app/routes/*.php` (API route closures) | none | `ExecuteRows(...)` |

### SQL parameter quoting — always `QuotedValue()`

```php
$sql = "SELECT * FROM fims.vendors WHERE vendor_code = " .
    QuotedValue($code, DataType::STRING, "DB");
```
Never concatenate raw user input into SQL. `DataType` constants: `STRING`, `NUMBER`, `BOOLEAN`,
`DATE`, `TIME`, `MEMO`, `BLOB`, `GUID`, `XML`.

Full query-pattern reference (ExecuteScalar/ExecuteRow/ExecuteRows/Execute/transactions, response
formatting, pagination): `_guidelines/api-route-service-guidelines.md`. Page-body patterns (hybrid
server+client data loading, modals, DataTables wiring): `_guidelines/page-creation-guidelines.md`.

### Permissions

Two layers, used together:

1. **Coarse, page-level**: `AllowListMenu('{FIMS}<page-or-table>.php')` in `menu.php`, checked
   against `uac.user_level_permissions` (keyed by the `{FIMS}` project tag + table/page name) for
   the logged-in user's level. `user_level_id = -1` (superadmin) bypasses every check.
2. **Fine-grained, in-page**: custom pages read `CurrentUserLevel()` directly and hide/disable
   controls per numeric level — e.g. `app/pages/budget-req-page.php` hard-codes "level 2 = Budget
   Officer can't Add/Edit/Delete" and "level 8 = Budget Head sees Budget Preview". There's no
   declarative role system yet for this fine-grained layer — it's inline `if` checks per page.
   Follow the existing comment style there (name the role, explain the gate) when adding a new one.

Route-level enforcement: `[PermissionMiddleware::class]` in the controller's `#[Map(...)]` attribute
(page routes) and `$accessMiddleware`/`$jwtMiddleware` chained onto Slim routes (API routes). Note:
as of this writing `$accessMiddleware` in `app/api.php` is a pass-through stub (see its comment) —
API routes are JWT-authenticated but not yet permission-gated at the route layer; the enforcement
today is the in-page `CurrentUserLevel()` checks described above. Don't assume the route itself
blocks unauthorized calls.

### Default dev login

`username/password: superadmin / superadmin123` (seeded by
`_assets/sql/00 - uac/04 - seed_data/01 - superadmin_user.sql`). Passwords are stored
case-folded — the hash is of the **lowercased** password — so don't hand-write a mixed-case bcrypt
seed.

---

## 6. `app/` in depth: two generations of service wiring

`app/` is where almost all hand-written logic lives (pages, services, routes, shared libs) — the
rest of the repo (`controllers/`, `models/`, `views/`, `src/`) is PHPMaker-generated scaffolding
(§4). Within `app/`, know which wiring generation a file belongs to:

**`app/services/` (current convention — use this for new work).** Auto-discovered by `app/api.php`
(§2) — no manual registration needed.

**`app/lib/` (older convention, inherited from a predecessor module).** `app/global.php` and
`app/api.php` manually `include_once`/`require_once` these — the auto-discovery glob does **not**
touch `app/lib/`. Of the 6 files there, 4 are correctly wired (`GlobalService.php` via `api.php`;
`ApiService.php`, `NotificationManager.php`, `UserAccessService.php` via `global.php`) and 2 are
not — see §7.1. If you add a new file to `app/lib/`, it will **not** load automatically; either
wire it by hand in `global.php` or just put it in `app/services/` instead.

Two other bootstrap files: `app/global_script.php` is an opt-in shared HTML/JS partial (`include`d
by individual pages that need it — currently empty, meant for cross-page modals/helpers as they
arise), and `app/preloader.php` is the full-page loading-overlay markup/JS, included globally from
`_shared/foot.php`.

---

## 7. Known issues in `app/` (verified against a running instance, 2026-11-09)

These were found by reading every file under `app/` and live-testing suspicious wiring against the
Docker stack — not just static inspection. Fix-worthiness roughly in priority order.

### 7.1 `DepartmentManagementService` / `PositionManagementService` are never loaded
**Confirmed via `curl` against the running stack:**
```
POST http://localhost:8090/api/department-management/departments
→ {"error":{"type":"Error","description":"...Class \"PHPMaker2024\\FIMS\\Service\\DepartmentManagementService\" not found"}}

POST http://localhost:8090/api/position-management/categories
→ {"error":{"type":"Error","description":"...Class \"PHPMaker2024\\FIMS\\Service\\PositionManagementService\" not found"}}
```
**Effect:** Add/Edit Department on `department-management.php`, and Add Category/Position on
`position-management.php`, are all currently broken (the fatal above, surfaced to the user as a
generic failure toast). **Fix:** add to `app/global.php`, next to the three lines that already work:
```php
include_once(dirname(__DIR__, 1) . "/app/lib/DepartmentManagementService.php");
include_once(dirname(__DIR__, 1) . "/app/lib/PositionManagementService.php");
```
Not applied yet — flagging for a decision before touching live behavior.

### 7.2 Two pages link a stylesheet path that doesn't exist
`app/pages/cob-page.php` and `app/pages/budget-revision-page.php` both link
`href="/css/nha-fims.css"`. That file doesn't exist — confirmed `css/nha-fims.css` is absent at repo
root (the `css/` folder exists but doesn't contain it). The real file is `_shared/css/nha-fims.css`,
which every other custom page (e.g. `budget-req-page.php`) correctly references. Effect: both pages
likely render without the shared NHA theme (404 on the stylesheet request).

### 7.3 `app/pages/form/budget-allocation-form.php` targets a different, non-existent API
Declares `namespace PHPMaker2024\FMD;` (every other file in `app/` uses `PHPMaker2024\FIMS`) and
calls `Config('API.FMD_URL')`. `.env` does define `API_FMD_URL` (underscore form — every other API
config key in this repo uses the dot form, e.g. `API.FIMS_URL`). Looks like an unported leftover
from a predecessor FMD module. Not confirmed as a live crash (sits behind login) — don't build on
this page without fixing the namespace/config mismatch first.

### 7.4 `app/routes/crud.php` + `app/services/crudServices.php` are dead code pointing at a schema that doesn't exist
Both declare the leftover template namespace `PHPMaker2024\FMD` / `PHPMaker2024\FMD\Service`.
`CRUDServices` hardcodes `$table = 'shared.programs'` — but **no `shared` schema exists anywhere in
this repo** (only `uac` and `fims`). Grepped all of `app/pages/**` for any call to `/crud/...` —
**zero matches**. Live (auto-discovered) but uncalled; if invoked it would SQL-error on the missing
schema, not the class-not-found error from §7.1. Treat as dead code — delete it or repoint it at a
real `fims` table if the generic-CRUD idea is still wanted. Don't copy its namespace into new
service/route files — use `PHPMaker2024\FIMS`.

### 7.5 `memo-req-page.php` is no longer empty; `AirModule` leftovers remain

~~`app/pages/memo-req-page.php` is a zero-byte file~~ — **stale as of 2026-11-13**: the page is now a
real ~42 KB implementation (the **Memo Request** register — see §8.2), built on top of
`fims.budget_allocation` / `BudgetAllocationService` with `app/pages/cba-form.php` as its Add/Edit
modal (shared with the CBA/RFA/LAA allocation types). Don't trust this entry for current behavior —
it's kept only so the history of the stub is visible; see §8.2 for what's actually there today.

`app/pages/air-module.php` (the other former empty stub) is gone: the module was built out as the
**Inspection and Acceptance Report (IAR)** module — see §8.4 — in `app/pages/iar-module.php`, served
by the PHPMaker page **`IarModule`** (`controllers/IarModuleController.php`, `models/IarModule.php`,
`views/IarModule.php`, menu item `mi_IARModule`). The original `AirModule` controller/model/view
(`*AirModule*`) are still in the tree from the earlier scaffold; `views/AirModule.php` was repointed at
`iar-module.php`, but nothing in the menu uses them any more — safe to remove once the `.pmp` project
no longer generates them.

### 7.6 `PricingRequestService` targets two tables that no SQL file creates

`app/services/PricingRequestService.php` (table `fims.pricing_requests`) and its attachments table
(`fims.pricing_request_attachments`) back `app/pages/pricing-page.php` + `app/routes/pricing_requests.php`
(`/pricing_requests/*`), but a repo-wide search of `_assets/sql/` for either table name finds **zero
`CREATE TABLE` statements** — unlike every other module in §8, this one's schema was never committed.
**Effect:** every `pricing_requests` endpoint SQL-errors (`relation "fims.pricing_requests" does not
exist`) on a freshly installed database; the page's cards/register/form all depend on it. **Fix:**
add a `01 - table/NN - fims_pricing_requests.sql` (+ attachments) under `_assets/sql/01 - fims/`,
matching the columns `PricingRequestService`'s header comment documents (`project_code` required;
day-count fields `(c)`/`(e)`/`(g)`/total `(h)`; attachments as an uploaded object-storage key or a
pasted link, soft-deleted). Not applied yet — flagging for a decision, same as the other entries here.

> If you're fixing any of the above: §7.1, §7.2, and §7.6 are independent, low-risk, verified gaps.
> §7.4 is a safe delete (confirmed zero callers). §7.3 needs a decision on whether the page is active
> work before fixing. §7.5 isn't a fix, just a heads-up on now-stale history. None of these have been
> applied — this guide only reports what's there.

---

## 8. Feature reference

There's no formal module/folder split yet (see §1), so "module" here means a logical grouping of
pages that work together. Most `app/services/` + `app/routes/` pairs follow one uniform REST shape
(all reached with an `/api` prefix externally, e.g. `/vendors/all` → `GET /api/vendors/all`):

```
GET    /{feature}/all          — unpaginated, for dropdowns
GET    /{feature}/list         — paginated (page, limit) + filters, usually returns a `summary` block too
GET    /{feature}/read/{id}    — one record
POST   /{feature}/add          — create
PUT    /{feature}/update/{id}  — update
DELETE /{feature}/delete/{id}  — delete (often blocked if referenced elsewhere — soft-delete via status toggle instead)
```
Deviations are called out per-feature below.

### 8.1 User Access & Administration (`uac` schema)
| Feature | Files | Purpose |
|---|---|---|
| Login / session | `app/pages/login.php`, `views/Login.php`, `models/Login.php`, `_shared/auth.php` | Standalone page (not wrapped by the shared header/footer); redirects to `$_SESSION['nha_home_url']` (default `/MainDashboard`) if already logged in. Also reads `_shared/system.php` for a legacy "subsystem" dropdown (see §13) |
| `MainDashboard` | `app/pages/main-dashboard.php` | User/system/user-level counts + a 6-month user-growth chart, queried directly against `uac.users`/`systems`/`user_levels` |
| `UserAccess` | `app/pages/user-access.php` (88KB — largest page in `app/`) + `app/routes/user-access.php` + `app/lib/UserAccessService.php` | User/role CRUD, permission-matrix editing, user-level assignment — fully documented in `app/routes/README.md` |
| `UserManagement`, `Users`, `UserLevels`, `UserLevelPermissions`, `UserLevelAssignments` | generated CRUD (Pattern A) | Underlying tables behind `UserAccess`'s UI |
| `DepartmentManagement` | `app/pages/department-management.php` + `app/routes/department-management.php` + `app/lib/DepartmentManagementService.php` | **Broken — see §7.1.** Department master data, hierarchy (cycle-checked on update). KPI cards: total/active departments, budget-unit count, top-level dept count |
| `PositionManagement` | `app/pages/position-management.php` + `app/routes/position-management.php` + `app/lib/PositionManagementService.php` | **Broken — see §7.1.** Position categories + positions + `PlantillaItems`. KPIs: total/active/inactive positions, category count |
| `Departments`, `Positions`, `PositionCategories`, `PlantillaItems`, `Offices`, `UserOffices` | generated CRUD | Supporting master tables |
| `SecurityManagement` | `app/pages/security-management.php` | Read-only consolidated dashboard (users/departments/positions/user-levels breakdowns + links into the pages above) — no CRUD of its own, no dedicated route/service |
| `SignatoryManagement` | custom page | Signatory assignment for approvals/printed forms |
| `Systems`, `SystemsMaster`, `DepartmentSystems`, `ApiSystems`, `ApiResources`, `ApiLogs` | generated CRUD | System/module registry + API inventory (scaffolding for a future multi-system model — currently only one system, `FIMS`, is registered) |
| `AuditLogs`, `AggregatedAuditLogsList/Grid/View` | generated CRUD + custom aggregation page | Audit trail, with a rollup view |
| `ChangePassword`, `ResetPassword` | custom pages | Self-service password management |
| `WorkflowManager` | custom page + `_shared/workflow-manager/` | Approval workflow definitions (engine is shared infra, see §9) |
| `DocumentTypeManager`, `DocumentTypeEditor`, `DocumentAssetTypes`, `DocumentHeaderAssets`, `DocumentHeaderSlots` | custom pages + generated CRUD | Document/report header & type configuration |
| `FileManager`, `PageEditor` | custom pages | S3-backed file browser; ONLYOFFICE embedded editor |
| `IdGenerator` (`IDGenerator`) | custom page | Document/voucher ID-format configuration |
| `PsgcMap`, `Psgc` | custom page + generated CRUD | Philippine Standard Geographic Code reference + map UI |
| `EventPlayground`, `BullMqManager` | custom pages | Real-time event tester; background job queue inspector |
| `AiAssistant` | custom page | AI-assisted helper panel |
| `PermissionModuleCaptions` | custom page/table | Lets you rename a module/page row's display caption in the permission matrix without touching the generated `$USER_LEVEL_TABLES` registry |

### 8.2 Budgeting & Planning (`fims` schema)
The furthest-along business workflow in the repo — a Budget Request → Revision → Allocation cycle.

| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Budget Request | `app/pages/budget-req-page.php` + `form/budget-req-form.php` + `form/budget-processing-form.php` + `readone/budget-req-readone.php` + `readone/budget-preview-readone.php` + `BudgetReqService.php` + `app/routes/budget_req.php` | `fims.budget_req`, `budget_req_program`, `budget_req_project`, `budget_line_items` + reference tables | The richest service in `app/` — not flat CRUD. One request (per fiscal year) holds nested Program → Project → COA-line-item blocks. `/budget_req/add` adds one Program-block (auto-creates the request header if absent); `/budget_req/program/update/{id}` replaces one Program-block. State machine: `submit-for-review` → `endorse` → `approve`/`return`, moving status `Draft → For Review → Endorsed → Approved`/`Returned`. `budget-processing-form.php` is the "Endorse" modal (Type of Fund + Component required per line, Suggested Amount optional). Role-gated: level 2 = Budget Officer (no Add/Edit/Delete), level 8 = Budget Head (no Add; sees Budget Preview) — the **only** page in `app/` with inline per-level UI gating |
| Budget Proposal | `app/pages/budget-prop-page.php` (+ the shared `form/budget-req-form.php` helpers, `form/budget-processing-form.php`, `readone/budget-preview-readone.php`) + `BudgetReqService.php` + `app/routes/fmd_budget_req.php` | `fims.budget_req` (+ its program / project / line-item tables) | The Financial Management Department's list of every Regional / District office's request: Submission Ref No., Region / Originating Unit, Submitted Date (`budget_req.submitted_at`), Amount, Status. Fetched from `GET /fmd/budget_req/list`, which fixes the scope server-side (FMD only, recognised by rc_code; else 403) — the page sends no RC parameters |
| Budget Revision | `app/pages/budget-revision-page.php` + `form/budget-rev-form.php` + `form/budget-rev-review.php` + `BudgetRevisionService.php` + `app/routes/budget_revision.php` | `fims.budget_req`, `budget_revision` | Revises an existing approved request; `budget-rev-review.php` is the approve/reject reviewer modal (`PUT /budget_revision/review/{id}`). **CSS bug — see §7.2** |
| Budget Allocation (CBA) | `form/budget-allocation-form.php` | — | **Leftover-namespace bug — see §7.3.** Client-side placeholder CBA-number generation (`'CBA#'.date('Y-m-d').rand(1,999)`), not a server-generated sequence like COA/RC use |
| Certificate of Budget (COB) | `app/pages/cob-page.php` | — | **CSS bug — see §7.2** |
| Memo Request | `app/pages/memo-req-page.php` + `cba-form.php` (shared Add/Edit modal) + `BudgetAllocationService.php` + `app/routes/budget_allocation.php` | `fims.budget_allocation` | **No longer the empty stub §7.5 used to describe** — a real register of memo/PR/PO tracking rows per fiscal year + requesting RC/district. `allocation_type` distinguishes this from the CBA/RFA/LAA/CFA allocations the same table backs elsewhere (IAR's RFA lookup, §8.4/§8.5); starts life in the `Draft` status (`fims.settings_status`, `status_for='budget_allocation'`). `requesting_district_id`/`requesting_rc_id` carry no DB FK — validated in PHP (see `03 - alter_table/08`/`14`) |
| Budget Category | `BudgetCategoryService.php` + `app/routes/budget_category.php` | `fims.budget_category` | Standard CRUD shape, soft-deleted via `deleted_at`. Feeds the Category picker on the Memo Request form |
| Budget Line Item (approved) | `BudgetLineItemService.php` + `app/routes/budget_line_item.php` | `fims.budget_line_items`, `budget_line_item_earmark` | Reads/edits one line item in isolation (outside the full Budget Request nested-add flow in the row above). `GET /budget_line_item/approved` lists only lines a Budget Request has approved (ceiling = `suggested_amount`, not `proposed_amount`); `/{id}/earmarks` lists the CBA/RFA/LAA/CFA allocations that have **earmarked** (reserved, not yet obligated) an amount against that line once their request reached Approved — see `fims.budget_line_item_earmark`'s table comment for the obligation/disbursement distinction |
| Fiscal Year | `FiscalYearService.php` + `app/routes/fiscal_year.php` | `fims.fiscal_year` (reads `budget_req`) | Read key is `{fiscal_yr}`, not `{id}`. Create/delete only, no update route. No dedicated page — feeds dropdowns on the budget pages |
| Expense Class | `ExpenseClassService.php` + `app/routes/expense_class.php` | `fims.expense_class` | Standard CRUD shape. No dedicated page — dropdown source |
| Component Type | `ComponentTypeService.php` + `app/routes/component_type.php` | `fims.component_type` | Standard CRUD shape. Used by the Budget Processing line-item form |
| Type of Fund | `TypeOfFundService.php` + `app/routes/type_of_fund.php` | `fims.type_of_fund` | Standard CRUD shape. Used by the Budget Processing line-item form |

### 8.3 Reference / Master Data
| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Program | `app/pages/program-page.php` + `form/program-form.php` + `ProgramService.php` + `app/routes/program.php` | `fims.program`, `project` | Keyed by `{code}`. `GET /program/next-code` previews a server-generated code (`GlobalService::generateProgramCode()`) |
| Sub-Program | `app/pages/sub-program-page.php` + `form/sub-program-form.php` + `SubProgramService.php` + `app/routes/sub_program.php` | `fims.program`, `sub_program` | Keyed by `{code}`. `POST /sub_program/add-with-items` batch-creates multiple sub-programs under one program in one call |
| Project | `app/pages/project-page.php` + `form/project-form.php` + `ProjectService.php` + `app/routes/project.php` | `fims.program`, `project`, `sub_program` | Keyed by `{code}`. Service also exposes an internal (unrouted) `findOrCreateByName()` helper used by the budget-request add/update flow |
| Chart of Accounts | `app/pages/chart-of-accounts-page.php` + `form/chart-of-accounts-form.php` + `ChartOfAccountsService.php` + `app/routes/chart_of_accounts.php` | `fims.chart_of_accounts` | Hierarchical (parent/child), server-generated `ACC00001…` ids, soft-deletes via status toggle when referenced by PO items. Fully documented in `app/routes/README.md` |
| Responsibility Center | `app/pages/responsibility-center-page.php` + `form/responsibility-center-form.php` + `ResponsibilityCenterService.php` + `app/routes/responsibility_center.php` | `fims.responsibility_code` | Hierarchical, server-generated `RC-000…` ids. Fully documented in `app/routes/README.md` |

### 8.4 Procurement
| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Purchase Order | `app/pages/po/page.php` (list) + `po/form.php` (add/edit modal, included by `page.php` — not its own route) + `po/readone.php` (detail, separate PHPMaker page `PurchaseOrderReadone`) + `PurchaseOrderService.php` + `app/routes/purchase-order.php` | `fims.purchase_orders`, `purchase_order_items` | Header comments say this was **ported from a `GLOBAL/` predecessor module and trimmed** to fit the `fims` schema (no PR linkage, no allocation/workflow engine, no payment workflow). Status machine `DRAFT → SUBMITTED → APPROVED/REJECTED → CANCELLED`; generates `PO-YYYY-MM-NNNN` numbers. Fully documented in `app/routes/README.md` |
| Vendors | `app/pages/vendors-page.php` + `form/vendor-form.php` + `VendorService.php` + `app/routes/vendor.php` | `fims.vendors`, `vendor_types` | Standard CRUD shape. Fully documented in `app/routes/README.md` |
| Inspection and Acceptance Report (IAR) | `app/pages/iar-module.php` (list) + `form/dr-module-form.php` (add/edit modal **and** the read-only "Actual Delivery" view modal, both included by the list page) + `PoDrService.php` + `app/routes/po_dr.php`; PHPMaker page `IarModule` | `fims.inspection_acceptance_report` (`01 - table/25 - fims_inspection_acceptance_report.sql`) | Records what was delivered against a PO: **1 PO → 1 IAR → many delivery receipts (DR)**, all in **one table** — the DRs are the `line_transaction` JSONB of the PO's IAR row. The PO is picked from the Budget Allocation (an RFA's PO number). Pattern B with a PHPMaker page shell. See the notes below the table |
| Property Records | `app/pages/property-record-page.php` + `PropertyRecordService.php` + `app/routes/property_record.php`; PHPMaker page `PropertyRecordPage` | `fims.property_records` (`01 - table/34`) + the Property Inventory System (PIS) API | The user **searches a PO number in PIS, selects PAR items and saves them** — one row per PO, the saved items as JSONB. The list shows the register of the saved items (never read from PIS); **Add from PIS** opens the search window. See the notes below |

**IAR module notes** (`PoDrService.php` header comment is the quick reference):

- **Addressing.** A PO is addressed by its `po_number` passed as `?po_number=…` (numbers may contain `/`
  or `-`), not by an id. Routes (all `->add($jwtMiddleware)`): `GET /po-dr/list`, `GET /po-dr/pos`
  (PO picker), `GET /po-dr/accounts` (Account Code picker), `GET /po-dr/read`, `GET /po-dr/proforma`,
  `POST /po-dr/add`, `PUT /po-dr/update`, `DELETE /po-dr/delete`.
- **One table, delivery receipts in JSONB.** `inspection_acceptance_report` has one row per PO: `po_number` (unique),
  the user-entered `iar_number` (required, unique, editable), the Sales Invoice # / date (once per PO) and
  `line_transaction` (JSONB **array**, one element per delivery receipt — the same idea as
  `budget_allocation.line_transaction`):
  `[{dr_number, delivery_date, total_amount, lines:[{account_code, account_title, amount}], items?:[{description,
  serial_no, amount, property_no}]}]`. `items` (any number of items, **one line each**) is present only when
  the total is 50,000 or more and at least one item was entered. There are no separate DR / entry tables and no FK: deleting the PO's row deletes its DRs.
  A DR is identified inside its PO by its `dr_number` (unique within the PO); a serial number is unique across all
  items of all DRs. Both are checked in PHP (`PoDrService::drError()`) — JSONB array elements can't carry a DB unique key — and the
  row is locked (`FOR UPDATE`) while a DR is added / edited.
- **One DR per save.** Payload: `{po_number, iar_number, si_number?, si_date?, dr:{dr_ref? (edit: the dr_number the DR had
  when loaded), dr_number, delivery_date, entries:[{account_code, amount}], items?:[{description?, serial_no?,
  amount?, property_no?}]}}`. **Add** appends the DR to the PO's `line_transaction` (creating the IAR row if the
  PO has none; if it has one, the IAR number sent must match it — the form pre-fills it when the PO is picked).
  The account titles are looked up in the chart of accounts and stored with the rows. There is no per-DR delete yet (only Delete PO).
- **Edit is limited.** The edit modal (with a DR picker for POs with several DRs) can change only the **IAR number**, the **Sales Invoice # / date**,
  the **DR number and delivery date** of the chosen DR, and its **items** (add / change / remove, when the DR total is 50,000 or more).
  The **PO number** and the DR's **account entries / amounts** are locked (read-only, no "Add row"), and another DR cannot be added there
  (that is the Add form). `PoDrService::update()` enforces this: a different `po_number` is refused (`The PO number cannot be changed`), and
  whatever entries are sent are ignored — the stored rows, titles and total are kept — so an edit can never change the PO balance (no balance
  check on edit). The DR number / serial uniqueness checks still apply.
- **Pre-fill from the Budget Allocation.** Picking a PO on a new DR pre-fills the account rows from the RFA's
  `line_transaction` (`account_code` + `requested_amount`; `GET /po-dr/pos` returns them as `lines`). The rows are
  filled in order until the PO's available balance is used up, and the user can change the accounts and amounts.
  Only a new DR whose rows are still untouched is pre-filled — an edit never is.
- **Business rules (server-enforced in `PoDrService::clean()`).** DR number + delivery date required;
  at least one account row; each row has an account code that exists in `fims.chart_of_accounts` and an
  **amount** > 0. There is **no debit/credit to balance** — the user only enters account codes and amounts;
  the validation is the PO balance (next bullet). The DR **Amount** (`total_amount`) is the sum of the
  row amounts, computed server-side. From **50,000** (`ITEM_MIN_AMOUNT`
  in the service, `AMP_ITEM_MIN_AMOUNT` in the form) the optional items (any number, one line each) open; below it any entered items are
  discarded. A serial number is unique across all items of all DRs; a DR number is unique within its PO.
- **PO balance limit.** A DR's total may not exceed the PO's **available balance** = PO amount − the totals of
  the PO's *other* DRs (the DR being edited isn't counted against itself). Enforced in
  `PoDrService::drError()` on add and update; the form shows the **Balance (after this DR)** field next to
  PO Amount and disables Save with an "Exceeds PO balance" pill when the total (shown under the Amount column) is over it. A PO that is no longer
  an RFA PO (legacy data) has no limit. `GET /po-dr/pos` returns `dr_total` (already delivered) for the picker and
  **leaves out POs with no balance left** (`po_amount - dr_total <= 0`), so a fully delivered PO can't be picked for a new DR;
  an existing IAR of such a PO stays in the list and can still be viewed and edited.
- **Account Code picker** searches the **whole chart of accounts** (active, `posting_allowed`; by code
  or title, 30 hits) — not limited to a category list. Only the code is stored; the form shows
  "code - title".
- **PO comes from the Budget Allocation.** The PO Number field is a search-and-pick combobox
  (`GET /po-dr/pos?search=`), not free text. It lists the `po_number` of `fims.budget_allocation` rows with
  `allocation_type = 'RFA'` (one per PO number, PO number + PO date shown). Picking one fills the read-only
  Requesting Office/Dept (`responsibility_code.rc_name` of `requesting_rc_id`), Vendor (`vendors.vendor_name`),
  PO Date and PO Amount. **PO Amount = sum of `requested_amount` over `line_transaction`**.
  `PoDrService::create()` (and `update()` when the PO number changes) rejects a PO that isn't an RFA PO.
  **PO Date is the RFA's `created_at` date** — `budget_allocation` has no PO-date column; change the
  `po_date` expression in `PoDrService::poSource()` if one is added. There is still no DB FK to the PO.
- **List page** columns: `# | IAR Number | PO Number | PO Date | Requesting Office/Dept | Vendor | Sales Invoice # |
  PO Amount | Total DR Amount | Balance | Actions`. **Balance = PO Amount − Total DR Amount** (red when
  over-delivered, green at 0; "—" for a legacy PO that no longer matches an RFA). Actions: **View** (every
  DR with its account entries, amount and item details), **Pro-forma**, Edit (no Delete button — a PO's IAR can no longer be deleted from the page; `DELETE /po-dr/delete` still exists in the API).
- **Pro-forma JEV** (`GET /po-dr/proforma`, returns `po_number`, `iar_number` and one entry per DR): one tab per delivery receipt. The header shows
  the **IAR Number**, the **Delivery Receipt No.** and the DR's **Total Amount**. The list has three columns — `Account Code - Account Name`,
  `Debit`, `Credit` — and is built from the DR's stored `lines`: each line is a **debit**, and the DR total is **credited to `2-01-01-010`**
  (Accounts Payable). A **Total** row shows the debit and credit totals under their columns.
  The entries are **editable** in the modal: change a row's account (searchable over the chart of accounts) and its **debit or credit** amount (a row is
  one or the other), add a row (the **+ Add row** button is above the table) or remove one. The edits are a draft kept per DR tab (switching tabs keeps them;
  closing the modal drops them) — nothing is stored. **Submit** is disabled when a row is incomplete or when the **total debit or the total credit exceeds
  the DR's total amount** (red "Exceeds DR total" pill beside the Total). The entry must also **balance** (total debit = total credit), but that is only
  checked when **Submit is clicked**: a SweetAlert warning "Not balanced: total debit ₱X and total credit ₱Y are not equal (difference ₱Z)" is shown
  and nothing is submitted — no pill or message is shown while editing. Submit is still a placeholder (no JEV endpoint yet): once the entry is valid it
  only shows a notice.
- **Screens.** The list's Actions column is one **Actions** dropdown (View · Pro-forma · Edit), the same menu as the Memo Requests list. The **View** (Actual Delivery) and **Pro-forma JEV** windows share one
  modal in the Memo Request details design (dark header with the IAR number, light body of white cards, Close in the footer); the Pro-forma opens wider (1500px). There is **no PAR # field**: the PIS property
  number is the PAR number (Property Records), so an item line is Description | Serial No. | Amount | Prop. No.
- **Install.** `01 - table/25 - fims_inspection_acceptance_report.sql` creates the one table. If an earlier local
  version exists, drop `po_delivery_receipt_entry`, `po_delivery_receipt` and `iar_module` first (the script is
  `IF NOT EXISTS`; the table was renamed from `iar_module` and absorbed the delivery-receipt tables).
  The service was exercised end to end against the database on 2026-11-12 (add / duplicate DR and serial / balance and
  IAR-number checks / edit and rename / list and search / pro-forma / delete); the pages were not browser-tested.

**Property Records notes** (`PropertyRecordService.php` header comment is the quick reference):

- **The list never reads PIS.** The page (cards + register) is `GET /api/property-record/list`, which reads only `fims.property_records`. PIS is just a source of data to insert: **Add from PIS** (header) opens a window
  (nothing is requested until the user searches) — **search → select → save**. `GET /api/property-record/search?po_number=` reads the PO's items from the NHA **Property Inventory System**
  (`GET {PIS_API_BASE_URL}/inventory_by_po?page=&pageSize=&PO_NO=`, `Authorization: Bearer {PIS_API_TOKEN}`, all pages) and returns the items with status **1 Issued, 2 Returned: Unserviceable, 3 Returned: Serviceable,
  4 Donation** (the API only allows the `PO_NO` filter, so the status is filtered in PHP). The user ticks items and clicks **Save Selected** → `POST /api/property-record/save {po_number, inv_ids[]}`. **PIS is asked again on
  save**: only the PO number and the chosen `inv_id` values come from the browser; an id that is not in that PO, has another status, or is already saved is not saved. A PO typed with this system's `PO-` prefix that finds nothing is retried without it.
- **Only POs that are in the IAR module** (`fims.inspection_acceptance_report.po_number`; with or without the `PO-` prefix, any case). Otherwise: *"PO '…' is not in the IAR module. Only a PO that already has an IAR can be searched — add its IAR in the IAR module first."*
  Checked first in `search()` (PIS is not even called) and again in `save()`.
- **Only PAR (PIS `TYPENO` = 1) is a property record.** Other types (e.g. 2) are listed in the search window but disabled, tagged *Not a property record*, with a warning; `save()` refuses them too
  ("This is not a property record: only items with TYPENO 1 (PAR) can be saved…").
- **One responsibility center per PO** (`fims.responsibility_code`), the first that matches: PIS `RCCODE` = `rc_code` → PIS department / district text equal to a center's name → the center of the PO in the IAR module (its budget allocation RFA).
  An existing PO row keeps its center. The user does not choose it.
- **Token.** `PIS_API_BASE_URL` / `PIS_API_TOKEN` are in `.env` (git-ignored; `.env.example` has the placeholders), read with `Env()` on the server only; PIS needs a `Bearer` token.
- **One table, one row per PO, items as JSONB.** `fims.property_records`: `po_number` (unique), `rc_id` (FK → `responsibility_code.rc_id`) and `line_transaction` (JSONB array, one element per saved unit). Only these PIS fields are kept, under the PIS's own names:
  `inv_id, TYPENO, GL, accnt_desc, GLCODE_SUB, inv_glcode_sub, PROPNO, SNO, ITEMDESC, MODELNO, DATEACQD, COST, UNIT, SERV, RCCODE, dept_proj_desc, PO_NO, DEPTCD, District`. Saving more items of a PO appends to its row; `inv_id` uniqueness is checked in PHP.
  `created_by` is filled from the logged-in user (empty for the built-in admin, who has no `uac.users` row).
- **The list = the register.** Built by `PropertyRecordService::groupRows()`, **looped per responsibility center** (a `{center}:` line, then its items). Units of the **same PO + category (GL) + sub category + item name** are ONE row (model, serial,
  property number, department, cost and status may differ; punctuation / case are ignored): quantity added, property numbers and departments stacked, description `{qty} {unit} {ITEMDESC}; {serial} / …, {model} (PAR No. {PROPNO}, …)`.
  Columns: Responsibility Area · Qty · Unit · Description · Location/Department within Responsibility Area (the department code, e.g. BML) · JEV Reference/Date (IAR # and PO #) · Particular document · Date Acquired/Donated · Property Number ·
  **Unit Cost** (one unit; the average if the units differ) · **Acquisition Cost (a)** (qty × unit cost) · **Additional Cost (b)** (0 — not in PIS) · **Total book balance (c = a + b)** · **Estimated Useful Life** (5 years) · **Salvage/Residual value** (10% of c) ·
  Accumulated Dep. at January 1 · Depreciation for the year · Monthly Depr'n. · Accumulated Depreciation (f = d + e) · Book Value (c − f) · Remarks · Status · Actions, then a **GRAND TOTAL** row (of every row matching the filters) for Total book balance, Salvage, the depreciation columns and Book Value.
  Depreciation is straight line from each unit's own acquisition date (60 months, 10% salvage) — PIS carries no useful life, so these are assumptions.
- **Cards.** Only *Property Record Breakdown by Category*: the 10 register categories by GL code (1-06-05-020 Office Equipment … 1-06-99-990 Other Property, Plant & Equipment), each with book value, `Depr:` accumulated depreciation and number of rows; 5 per line; a click filters the table.
- **View window** (Actions → View): the Memo Request details design — Property Record, Purchase Order (IAR/PO number, date acquired, Unit Cost, Acquisition Cost a, Additional Cost b), Responsibility Center & Location (the center and the description of the department code, e.g.
  `Bukidnon | Misamis Occidental | Lanao del Norte District Office (BML)`), a Units table and a Depreciation table.
- **Install.** `01 - table/34 - fims_property_records.sql` creates the table (`IF NOT EXISTS`). The service and the page were exercised against the real PIS API and the local database; the page was not browser-tested.

### 8.5 Disbursement Vouchers & Journal Entries

API: `app/routes/voucher.php` (`/voucher/*`). Pages: `app/pages/voucher/{page,form,readone}.php` +
`app/pages/journal-entry-voucher.php`. Service: `DisbursementVoucherService.php` (+ `DvPrintService`,
`JevPrintService` — see §8.8). Fully documented in the ERD doc's **§2.3**, not duplicated here — read
that for the table-level detail (Deductions, multi-row ATC, Subsidiary Ledger). Summary: one generic
DV shape backs every claimant/payment type, pipelined `DRAFT → FOR_FMD_REVIEW → FOR_ACCOUNTING →
FOR_TREASURY → RELEASED` (or `REJECTED`/`CANCELLED`), with the Journal Entry (one per DV) prepared
during `FOR_ACCOUNTING` and its Accounting Entries grid auto-filled from the DV's Deduction/ATC rows.
`CheckProcessingService` (§8.6) is the only thing that can move a DV from `FOR_TREASURY` to
`RELEASED`, by preparing a check against it.

**General Ledger posting (2026-11-14):** `GeneralLedgerService.php` posts a DV's JEV to
`fims.general_ledger` the moment Accounting approves it (`FOR_ACCOUNTING → FOR_TREASURY`) —
`DisbursementVoucherService::changeStatus()` now wraps its own status update and
`GeneralLedgerService::postJev()` in one DB transaction (that method wasn't transactional before
this change). A DV cancelled from `FOR_TREASURY` (the only point a posted JEV can still be unwound —
`RELEASED` is terminal) triggers `GeneralLedgerService::reverseJev()` instead, which inserts
offsetting entries rather than deleting anything. `fims.v_journals`/`fims.v_trial_balance` are the
reports built on top. Fully documented, including the design differences from the original draft, in
the ERD doc's **§2.8** — read that before extending posting/reversal logic.

**Route + UI — four separate modules (2026-11-14):** `GeneralLedgerPage`/`JournalsPage`/
`TrialBalancePage`/`SubsidiaryLedgerPage` are four independent custom pages (not tabs of one page —
an earlier same-day iteration combined Journals + Trial Balance into one page's tabs; that was split
apart once the intended module boundary was clarified), all backed by `app/routes/general_ledger.php`
(all four endpoints read-only — posting itself is never reachable via a route, only from
`DisbursementVoucherService::changeStatus()`) and `GeneralLedgerService.php`:

| Module | Page | Endpoint | Shows |
|---|---|---|---|
| Journals | `app/pages/journals-page.php` | `GET /general_ledger/journals` | The book of original entry — every posted line, chronological, paged. Filterable by fiscal year/account code/JEV or DV number |
| General Ledger | `app/pages/general-ledger-page.php` | `GET /general_ledger/ledger?account_code=` | Pick ONE chart-of-accounts account (combobox over `GET /chart_of_accounts/all?postable_only=1`) → every posting to it, oldest first, with a **running balance** signed the normal accounting way (`GeneralLedgerService::DEBIT_NORMAL_TYPES` — Asset/Expense accounts run debit-minus-credit, Liability/Equity/Revenue run credit-minus-debit) |
| Subsidiary Ledger | `app/pages/subsidiary-ledger-page.php` | `GET /general_ledger/subsidiary-ledger?sl_code=` | Same ledger-card idea as General Ledger, but keyed by SL code (+ optional SL detail, reusing the existing `GET /voucher/sl-codes` / `.../{sl_code}/details` pickers) instead of a GL account — e.g. one vendor's Accounts Payable sub-ledger. Normal-balance side follows the SL master's own control account (`fims.sl_masters.account_code`); falls back to credit-normal if a master has none |
| Trial Balance | `app/pages/trial-balance-page.php` | `GET /general_ledger/trial-balance` | Total debit/credit per account, as of now, with a Balanced/Out-of-balance badge |

Each page is a full PHPMaker custom page (controller + model + view, DI-bound in
`src/definitions.php`, registered in `src/userlevelsettings.php`) under a new sidebar group,
**"Ledgers"** (menu id 394, icon `fa-scale-balanced`) — General Ledger (393), Subsidiary Ledger (395),
Journals (396), Trial Balance (397), in that order; General Ledger's menu item moved out of the
"Vouchers" group into this new one.

**Verified end-to-end against the live dev stack** (2026-11-14): the migration was applied for real
to the app's actual database (`host.docker.internal:5432/fims` — *not* the separate
`fims-nha-postgres` docker-compose container on port 5433, a different, unrelated Postgres instance;
don't confuse the two when applying a migration for real, see §10), `composer routes` regenerated,
then confirmed via `curl` with a real session cookie end to end: created a DV, approved it through to
`FOR_TREASURY` (posting fired), confirmed the General Ledger ledger-card, Subsidiary Ledger
ledger-card (a second DV whose JEV line carried an `sl_code`), Journals, and Trial Balance **all**
show the posting with correctly-signed running balances; cancelled it from `FOR_TREASURY`
(`reverseJev()` fired) and confirmed both ledger cards net back to exactly `0.00`; confirmed the
idempotency guard (retrying an already-`FOR_TREASURY` transition is rejected by the state machine,
no double-post); and confirmed all four pages render 200 with no PHP errors and the new "Ledgers"
menu group appears on `/MainDashboard`.

### 8.6 Treasury operations: Bank Management, Cash Position, Check Processing

These three modules are **ports of the separate `TREASURY` reference project**, adapted to this
repo's `fims` schema and RC-based (not department-based) scoping. Each service's header comment names
exactly which TREASURY page/service it came from and what was deliberately **not** ported (usually:
whatever needs a TREASURY subsystem — Collections, Investments, FTLA, Garnished/Expropriation
Accounts — that doesn't exist here) — read those comments before extending any of the three, they're
more detailed than this table.

| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Bank Account Management | `app/pages/bank-management-page.php` + `BankManagementService.php` + `app/routes/bank-management.php` | `fims.bank_accounts` (+ `bank_account_documents`) | One row per disbursing bank account; `fund_source` is free text (no `shared.fund_sources` lookup here, unlike TREASURY); Region/Office is a Responsibility Center (`rc_id`, unenforced), not `uac.departments`. `status` (ACTIVE/DORMANT/CLOSED) is kept in sync with the pre-existing `is_active` boolean. Documents are Google-Drive links, not uploads |
| Cash Position | `app/pages/cash-position-page.php` + `CashPositionService.php` / `CashPositionSoaService.php` / `CashPositionReportsService.php` + `app/routes/cash-position.php` | `fims.cash_position`, `cash_position_activity_log`, `bank_transactions`/`soa_bank_transactions` | 4 tabs, 3 real + 1 mostly stubbed. **Dashboard** and **Position Updates** (manual entries + SOA upload — a verbatim-ported pure-PHP xlsx parser, `ZipArchive`+`SimpleXML`, no PhpSpreadsheet) and **Available Cash Balances Tracking** are fully wired. **Reports** (18 report types off one `report_type` dispatch) has daily/period/working-paper/individual-ledger/new-FY-opening-balance real against this schema; the rest (`cash_flow`, `investment`, `interest_earnings*`, `garnished`, `expropriation*`, `investment_schedule`) are **intentionally stubbed** — each needs a whole TREASURY subsystem (Collections/Investments/FTLA/Garnished/Expropriation Accounts) this project has never built |
| Check Disbursement Voucher (Check Processing) | `app/pages/check-disbursement-voucher-page.php` + `CheckProcessingService.php` + `app/routes/check-processing.php` | `fims.checks`, `check_booklets`, `check_register`, `check_cancellations`, `check_claimant_releases`, `check_claimant_email_log`, `check_region_releases`, `check_region_email_log` | 7 tabs ported from TREASURY's 7 check-processing pages: **Check Preparation** (only a `FOR_TREASURY` DV can get a check — doing so calls `DisbursementVoucherService::changeStatus()` to flip it `RELEASED`, same auth as every other caller), **Check Registry** (`check_booklets`/`check_register`, incl. the RAF inventory tab computed live from `fims.checks` rows in a booklet's number range, not stored), **Released to Claimants**, **Released to Regions/Departments** (both send real SMTP email via `App\SendEmail()`, a PHPMailer wrapper — Regions' Google-Drive attachment is still a `saveRegionDriveLink()` stub), and **Cancellation & Replacements**. All RC-scoped like Bank Management, not department-scoped |

### 8.7 Fund Tracking (SARO / NCA / Trust Funds / Supplemental)

One table, `fims.fund_tracking`, backs **4 distinct pages** filtered by `tracking_type_id` /
`nature_of_fund_id` — know which page you're looking at before assuming a filter is missing:

| Page | Files | Filter | Purpose |
|---|---|---|---|
| SARO / NCA Tracking | `app/pages/saro-registry-page.php` + `form/saro-processing-form.php` | `tracking_type IN (SARO, NCA)`, `exclude_nature_of_fund=SUPPLEMENTAL` | Register of every SARO (parent row) with its NCA releases nested under it (one save can add NCA releases alongside the SARO) |
| Supplemental Fund Tracking | `app/pages/supplemental-funds-page.php` | `nature_of_fund = SUPPLEMENTAL` | Flat list, no tree |
| Trust Funds | `app/pages/trust-funds-page.php` + `form/trust-fund-form.php` | `tracking_type = TRUST` | Register of Trust Fund records + their Official Receipts |
| Fund Sourcing Dashboard | `app/pages/fund-sourcing-dashboard-page.php` | (aggregated, no single filter) | Summary view over the whole table — `GET /fund_tracking/fund_sourcing_determination` / `/supplemental_status_summary` / `/dashboard` |

Backing services: `fund_trackingServices.php` + `app/routes/fund_tracking.php` (`/fund_tracking/*` —
also `/import` and `/import-trust-fund` for spreadsheet ingestion). `fims.fund_tracking` declares
**no FOREIGN KEYs at all**; `validateReferences()` checks every id/code in code instead. Two lookup
tables feed it: `fund_tracking_type` (`fund_tracking_type_id`: SARO/NCA/TRUST/OR — GAAAO and
SUPPLEMENTAL are **natures of fund**, not tracking types, don't confuse the two) via
`fund_trackingTypeServices.php` + `app/routes/fund_tracking_type.php`, and `nature_of_fund`
(Insertions/Equity/GAAAO/Unprogrammed/Supplemental) via `nature_of_fundServices.php` +
`app/routes/nature_of_fund.php` — both standard CRUD shapes. A third lookup, **Agency**
(`fims.agency` — source agency of a fund tracking record) via `agencyServices.php` +
`app/routes/agency.php`, is referenced from `fund_tracking.agency_name` **by name, not id** (the
lookup table is for picking a value, the column stores the string).

### 8.8 Pricing Requests

`app/pages/pricing-page.php` + `form/pricing-request-form.php` + `readone/pricing-request-readone.php`
+ `PricingRequestService.php` + `app/routes/pricing_requests.php`. Fund Sourcing Division requests,
keyed by `project_code`; day-count fields and a total are computed client-side and saved as sent;
attachments are an uploaded object-storage key or a pasted link. **The backing tables
(`fims.pricing_requests`/`pricing_request_attachments`) have no SQL file anywhere in this repo — see
§7.6.** Don't rely on this feature working against a fresh install until that's fixed.

### 8.9 Printing & document branding

**`PrintService.php`** (`app/routes/print.php`, `POST /print/{doc}`) is the shared pipeline behind
every printed form — one registry (`PrintService::DOCS`) maps a doc code to its own
`<Doc>PrintService` (wording/layout) plus an optional DOCX generator script
(`scripts/gen_<form>_docx.js`, Node + the `docx` package):

| Doc code | Service | Notes |
|---|---|---|
| `CBA` / `CAF` | `CbaPrintService` / `CafPrintService` | CAF reuses the CBA layout |
| `RFA` | `RfaPrintService` | |
| `LAA` / `LAAREQ` | `LaaPrintService` / `LaaRequestPrintService` | `LAAREQ` is the release request's memo layout |
| `FTLA` / `FTLAREQ` | `FtlaPrintService` / `FtlaRequestPrintService` | `FTLAREQ` reuses `LAAREQ`'s memo layout |
| `CFA` | `CfaPrintService` | Funding-request memo, the shared memo layout |
| `MEMO` | `MemoRequestPrintService` | The originating unit's (non-FMD) memo request, same shared memo layout |
| `PO` / `PR` | `PurchaseOrderPrintService` / `PurchaseRequestPrintService` | |
| `IAR` | `IarPrintService` | |
| `LV` | `LvPrintService` | |
| `JEV` | `JevPrintService` | A4 landscape |
| `DV` | `DvPrintService` | **`pdf_only`** — an official PDF form filled directly, no HTML/DOCX layout or letterhead |

Every non-`pdf_only` doc renders through one path so preview/PDF/DOCX can't drift: `html()` → preview
(`app/pages/readone/print-preview-readone.php`) and the Dompdf-rendered PDF; the DOCX script reads the
same `model()` payload. `GET /print/resolve-signatory` looks up the current holder of a signatory
role for the form's signature block.

**`LetterheadLayoutService.php`** (`app/routes/letterhead.php`, `/letterhead/{doc}`) stores a
per-document-code default header/footer layout (`fims.letterhead_layout` — freely positioned logo/
brand/seal/title items + a footer line, in px on a 630px-wide reference header that each form scales
to its own paper width). "Save as default header" in the print preview writes a row; deleting it
("Restore original") falls back to the built-in `_shared/DocumentBranding.php` banner. Not every doc
code registered in `PrintService::DOCS` necessarily has a matching data-entry page yet (e.g. `LV`,
`FTLA`) — the print registry can be ahead of the feature it prints.

**File uploads**: `app/routes/file_upload.php` (`/upload/single-upload`, `/upload/multi-upload`,
`/upload/file`) is the one shared object-storage upload endpoint several of the modules above
(Pricing Request attachments, Bank Management documents, Budget Request supporting docs) are meant to
funnel through — see `GlobalService::uploadFileToStorage()` (§5) for the underlying primitive.

### 8.10 Platform utilities (not page-backed)
| Feature | Files | Purpose |
|---|---|---|
| Notifications | `app/routes/notifications.php` + `app/services/NotificationService.php` | Notification hub on `uac.notifications` / `uac.notification_reads`: recent / count / mark-read / mark-all-read / send (super admin only). Raised from code with `NotificationService::toUser(...)` etc. — see **§8.11**. Consumed by the shared notification bell (`_shared/notification.php`). The old `app/lib/NotificationManager.php` is legacy and no longer used by the routes |
| Swagger generation trigger | `app/routes/service.php` | `GET /Service` — generates OpenAPI docs via `ApiService` |
| Generic CRUD template | `app/routes/crud.php` + `app/services/crudServices.php` | **Dead code — see §7.4** |

> Full current API surface for `user-access`, `department-management`, `position-management`,
> `vendor`, `purchase-order`, `chart_of_accounts`, and `responsibility_center` routes is kept in
> **`app/routes/README.md`** — read it before calling or extending any of those endpoints; it's more
> current than this guide for exact HTTP verbs/paths. The newer modules in §8.5–§8.9 are not yet
> covered by that file — read their service header comments instead.

### 8.11 Notifications (bell + optional real-time push)

**Storage** (`_assets/sql/00 - uac/02 - alter_table/06 - notifications_hub.sql`): `uac.notifications`
(`id`, `type`, `target`, `subject`, `body`, `link`, `from_system`, `sender_id`, `event_key`, `created_at`)
and `uac.notification_reads` (one row per user who has read a notification). A partial unique index on
`event_key` makes a repeated event a no-op.

**Audience** — a notification is addressed with `type` + `target`; there are no other types:

| `type` | `target` | Who sees it |
|---|---|---|
| `all` | `all` | everyone |
| `system` | `uac.systems.system_id` | users with a level assignment for that system |
| `user` | `uac.users.user_id` | that user |
| `userlevel` | `uac.user_levels.user_level_id` | users assigned that level |
| `office` | `uac.offices.office_id` | active members of that office |
| `usergroup` | supervisor's `user_id` | users who report to that supervisor |

**Raise one from business code** (`app/services/NotificationService.php`) — static helpers that never
throw (they return the new id, or `null` when de-duped or failed):

```php
NotificationService::toUser($userId, "Program {$code} created", "{$name} was added to Programs.",
    '/ProgramPage', ['event_key' => "program-created:{$code}"]);
// also: toAll, toSystem, toUserLevel, toOffice, toUserGroup
```

`link` must be a relative same-origin path (anything else is dropped). `event_key` is optional; use a
stable key so retries don't notify twice. Example in use: `ProgramService::create()` notifies the creating
user.

**API** (`app/routes/notifications.php`, all behind `$jwtMiddleware`): `GET /notifications/recent`,
`GET /notifications/count`, `POST /notifications/mark-read`, `POST /notifications/mark-all-read`,
`POST /notifications/send` (super admin only). The browser sends `X-Authorization: Bearer ew.API_JWT_TOKEN`.
`_shared/notification.php` builds its base URL from `Config('API.FIMS_URL')` and strips a trailing `/api`
before appending `/api/notifications`, so it works whether or not the config value ends in `/api`.

**Delivery:** the bell polls `/recent` every 30 s (and on tab focus) — polling is the guarantee. After an
insert, `NotificationService` also publishes to EMQX (`POST {EMQX.API_URL}/api/v5/publish`, topic
`app/notifications/{type}/{target}`) when `EMQX_API_URL` and `EMQX_API_KEY` are set; the browser client
(`_shared/mqtt-notification.js`) then triggers an immediate refresh. With EMQX off or unconfigured,
nothing breaks — notifications just arrive on the next poll. After a local action the page can refresh at
once with `window.NotificationManager?.updateNotifications()` (done in `program-page.php`).

**EMQX:** the `emqx` service in `docker-compose.yml` (`README-Docker.md` has setup and the API key steps).
`EMQX_API_URL` must point at the REST port `18083`, not the MQTT port `1883`.

**Known gaps:** `app/lib/NotificationManager.php` and its include in `app/global.php` are legacy and can be
removed; `mqtt-notification.js` hard-codes `wss://localhost:8084/mqtt` and `/node_modules/mqtt/dist/mqtt.min.js`
returns 404, so push is not active yet and the bell relies on polling; there is no retention job for old rows.

---

## 9. Shared infrastructure (`_shared/`)

Cross-cutting code used by every page — coordinate before changing:

| Component | Path |
|---|---|
| Auth | `_shared/auth.php` |
| Page permissions | `_shared/PagePermission.php` (+ `_shared/README-PagePermission.md`) |
| Notifications | `_shared/notification.php` (bell UI), `_shared/mqtt-notification.js` (optional MQTT push) — design in `_shared/NOTIFICATION-IMPLEMENTATION-PLAN.md`, usage in `_shared/NOTIFICATION_README.md`, summary in §8.11 |
| Workflow engine | `_shared/workflow-manager/` |
| File manager | `_shared/file-manager/` |
| Document/editor | `_shared/document/` |
| Chat widget | `_shared/chat-widget/` |
| Job queue | `_shared/bullmq-manager/` |
| Swagger generation | `_shared/Swaggerize.php` |
| Branding / templates | `_shared/branding/`, `_shared/template/`, `_shared/nha-logos/` |

---

## 10. Running it locally

```bash
docker-compose up -d
# php-fpm + nginx (localhost:8090) + postgres (localhost:5433) + redis (6380) + minio (9020/9021) + emqx (1883/8083/18083) + adminer (8091)
```

First boot runs `composer install` automatically (named volume `fims-vendor`). Load the schema with
`install_system.js` (see caveats below) or by hand via `psql`/Adminer using the files under
`_assets/sql/` in folder-number order: `00 - public` → `00 - uac` → `01 - fims`.

`composer routes` must be re-run inside the container after adding/changing a `#[Map(...)]` route
attribute (§2).

**Two different Postgres instances, same DB name — don't confuse them (verified 2026-11-14).**
`docker-compose.yml` runs its own `postgres` service (container `fims-nha-postgres`, exposed on host
port **5433**, also named database `fims`), and Adminer is pre-wired to it. But `.env`'s
`DB_HOST=host.docker.internal` / `DB_PORT=5432` means `fims-nha-php` actually connects to a
**separate Postgres on the host machine itself, port 5432** — the docker-compose `postgres` service
is not in that connection path at all. If you apply a migration or seed by hand, target
`host.docker.internal:5432` (from inside a container) / `localhost:5432` (from the host) — applying
it to the `fims-nha-postgres` container on 5433 instead will silently do nothing for the running app.

---

## 11. Documentation index

| Document | Covers |
|---|---|
| **This file** | Current, verified architecture + module inventory |
| `_guidelines/page-creation-guidelines.md` | Page body patterns: data loading, UI components, forms/modals, error handling |
| `_guidelines/api-route-service-guidelines.md` | Service/route implementation, query patterns, middleware, transactions |
| `app/routes/README.md` | Authoritative per-endpoint reference for the routes it covers (see §8 note) |
| `_assets/sql/README.md` | SQL folder conventions — **partially stale**, see §13 |
| `system_docs/modules/*.docx` (also at `_assets/System Requirements/`) | Full target-scope SRS: Accounting Department, District, FMD, Regional, Treasury Department — the roadmap, not current state |
| `system_docs/ERD/` | Entity-relationship diagrams for `uac` + `fims` (rewritten 2026-11-09, latest update 2026-11-13) — expand as schemas grow |
| `README-Docker.md`, `README-DEPLOY.md`, `README-installation.md` | Docker setup, on-prem deploy, project-wizard tooling |

---

## 12. Roadmap: what isn't built yet

Per the SRS docs in `system_docs/modules/`, the target system also covers **Accounting Department**
(Disbursement Control, Branch & Property, General Division — vouchers, JEVs, ledgers, payroll,
assets, receivables), **Treasury Department** (checks, cash position, FTLA, bonds, fund sweeping),
and **Regional/District offices** (field-office accounting cycle). As of 2026-11-13 this is **no
longer all unbuilt** — Disbursement Vouchers & Journal Entries (§8.5), Bank Account Management, Cash
Position, and Check Processing (§8.6, ported from the separate `TREASURY` reference project) exist
and are largely wired against this schema. Still genuinely missing: payroll, fixed assets,
receivables, bonds/fund-sweeping, FTLA, and the full Regional/District field-office cycle — none of
those tables or pages exist here yet. When scoping new work, check the relevant SRS docx first; don't
assume prior-NHA-FIMS conventions (per-module folders, multi-schema `fmd`/`accounting`/`treasury`/
`regional` layout) apply here until they're actually implemented, and check §8 before assuming a
Treasury-sounding feature is unbuilt — several now are.

---

## 13. A note on other documentation in this repo

Several existing docs describe a **different, larger architecture** than what's actually on disk —
they were carried over from a predecessor NHA FIMS project as forward-looking reference material and
haven't been rewritten for this rebuild yet. Don't let them mislead you:

- **`README.md`** (root) describes per-module folders (`UAC/`, `FMD/`, `GSD/`, `ACCOUNTING-DCD/`,
  `ACCOUNTING-BAPD/`, `ACCOUNTING-GAD/`, `TREASURY/`, `REGIONAL/`, `LIBIS/`) with their own
  `composer.json`/`app/`/`src/` each. **None of these folders exist in this repo.** Everything is
  flat at the repo root (§3).
- **`_assets/sql/README.md`** describes 371 files across 9 schemas (`shared`, `uac`, `fmd`,
  `accounting`, `treasury`, `workflow`, `regional`, `district`, `integration`) including an
  `ACCOUNTING-BAPD/sql/` tree. The actual `_assets/sql/` tree today has **139 files** (14 under
  `00 - uac`, 123 under `01 - fims`, 2 under `00 - public`) across two schemas (`uac`, `fims`) — see
  §3/§10. Use this guide's description, not that README's, until it's updated. The count has grown a
  lot since the 2026-11-09 rewrite (49 files then) as §8.5–§8.9's modules landed — see the ERD doc's
  §2 for the table-level detail, not duplicated here.
- **`install_system.js`** still contains logic referencing an `ACCOUNTING-BAPD/sql/` root that
  doesn't exist here — **verified 2026-11-09 that this is harmless**: the script checks
  `fs.pathExists()` before walking a root, so the missing folder just contributes zero files and
  doesn't error. A full dry run (the 49 files under `_assets/sql/` as of that date, in the exact
  order the script computes) on a throwaway database completed with **no errors** and produced the
  expected 30 `uac` + 20 `fims` tables, with `superadmin` seeded correctly. The 90 files added since
  (§8.5–§8.9's tables) have **not** been re-verified with a fresh dry run as of this update — they
  follow the same `IF NOT EXISTS`/idempotent convention and install in folder-number order, so this
  is low-risk, but don't take the "no errors" claim above as covering them. So: the installer's
  file-discovery and execution logic is sound for this repo as-is — you do not need to fix it before
  using it (for the part that has actually been re-verified).
  One piece of real (cosmetic) cruft found during that dry run: `00 - public/schema.sql` still runs
  `CREATE SCHEMA IF NOT EXISTS` for `fmd` and `workflow` — leftover from the old multi-schema plan.
  Both schemas get created empty (no tables land in them anywhere in this repo) — harmless, but
  worth dropping from `schema.sql` next time someone's in that file, so `\dn` doesn't show two dead
  schemas. Not tested in this pass: the interactive prompt flow itself (host/port/db-name questions,
  the composer-install step, and `--consolidated`/`--reset-only`/re-run idempotency) — only the SQL
  discovery-and-execution path was verified.
- **`_shared/system.php`** still lists the old multi-system roster (`UAC`, `FMD`, `ACCOUNTING-GAD`,
  `ACCOUNTING-BAPD`, `ACCOUNTING-PCDS`, `ACCOUNTING-DCD`, `TREASURY`, `REGIONAL`, `GSD`, `DOCUMENT`,
  `LIBIS`) and is read by `models/Login.php` to populate a "subsystem" dropdown on login. Only one
  system (`FIMS`) actually exists, so that dropdown currently lists mostly-dead entries — worth
  cleaning up when someone touches the login page.
- **`app/routes/crud.php`** + **`app/services/crudServices.php`** are declared under namespace
  `PHPMaker2024\FMD\Service` / `PHPMaker2024\FMD` (not `PHPMaker2024\FIMS`, like everything else in
  this repo) — a leftover from the `__BASE__` template this project was generated from. It still
  loads fine (PHP namespaces don't collide with the auto-discovery glob), but don't copy its
  namespace when creating a new service/route file — use `PHPMaker2024\FIMS`.

If you fix one of the above, update this section rather than deleting it outright, so the next
person knows what changed and when.

---

## 14. Where to start (new contributor)

1. `docker-compose up -d`, load the schema (§10), log in as `superadmin` / `superadmin123` (§5).
2. Read `_guidelines/page-creation-guidelines.md` and `_guidelines/api-route-service-guidelines.md`.
3. Pick one small, already-working feature and trace it end-to-end: `ResponsibilityCenterPage` is a
   good choice — page (`app/pages/responsibility-center-page.php`) → route
   (`app/routes/responsibility_center.php`) → service (`ResponsibilityCenterService.php`) → table
   (`fims.responsibility_code`).
4. Re-read §4 of this guide before building your first new page, so you pick the right pattern
   (generated CRUD vs. hand-built custom page vs. pure API+page) instead of over- or
   under-building.
