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

### CSS: PHPMaker gives every text input `min-width: 250px`

The generated `css/FIMS.css` has `input[type=text]:not([size]) { min-width: 250px }`, which applies on every page, inside `.nha-root` too. A text input
in a narrow `.nha-filter-group` (the `-sm` / `-md` / `-lg` caps are 150 / 180 / 200px) therefore refuses to shrink and spills out of its group over the next
filter — the Fund Sourcing Dashboard's (since removed) Program box sat on top of Amount Unit. A selector with the same weight as the shared class cannot beat that rule, so
give the page's own filter group an **ID-scoped** override (`#my-root .my-group .nha-filter-input { min-width: 0; }`) and set the group's width on the page
class. Selects are not affected; only `type=text` / `password` without a `size` attribute.

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

### 7.2 ~~Two pages link a stylesheet path that doesn't exist~~ — fixed
`app/pages/cob-page.php` and `app/pages/budget-revision-page.php` used to link `href="/css/nha-fims.css"`,
which doesn't exist (the real file is `_shared/css/nha-fims.css`), so both rendered without the shared NHA
theme. Both now link `GetUrl('_shared/css/nha-fims.css')` like every other custom page — `cob-page.php` was
rewritten (see `system_docs/modules/COB-MONITORING.md`) and `budget-revision-page.php` was already corrected.

### 7.3 ~~`app/pages/form/budget-allocation-form.php` targets a different, non-existent API~~ — fixed
**Resolved** as part of building out the Memo Request module (2026-11-10/12, see §8.2). The file now
declares `namespace PHPMaker2024\FIMS;` and calls `Config('API.FIMS_URL')`, with a comment at the top
explaining why the namespace line is required (an include from `views/BudgetAllocationForm.php`
doesn't inherit that file's namespace). No longer targets `FMD`/`API.FMD_URL`.

### 7.4 `app/routes/crud.php` + `app/services/crudServices.php` are dead code pointing at a schema that doesn't exist
Both declare the leftover template namespace `PHPMaker2024\FMD` / `PHPMaker2024\FMD\Service`.
`CRUDServices` hardcodes `$table = 'shared.programs'` — but **no `shared` schema exists anywhere in
this repo** (only `uac` and `fims`). Grepped all of `app/pages/**` for any call to `/crud/...` —
**zero matches**. Live (auto-discovered) but uncalled; if invoked it would SQL-error on the missing
schema, not the class-not-found error from §7.1. Treat as dead code — delete it or repoint it at a
real `fims` table if the generic-CRUD idea is still wanted. Don't copy its namespace into new
service/route files — use `PHPMaker2024\FIMS`.

### 7.5 ~~`memo-req-page.php` is registered but empty~~ — built out; `AirModule` leftovers remain
**No longer empty.** `app/pages/memo-req-page.php` was built out 2026-11-10/12 into the full Memo
Request list — see §8.2. The `AirModule` leftovers below are still accurate:

`app/pages/air-module.php` (the other former empty stub) is gone: the module was built out as the
**Inspection and Acceptance Report (IAR)** module — see §8.4 — in `app/pages/iar-module.php`, served
by the PHPMaker page **`IarModule`** (`controllers/IarModuleController.php`, `models/IarModule.php`,
`views/IarModule.php`, menu item `mi_IARModule`). The original `AirModule` controller/model/view
(`*AirModule*`) are still in the tree from the earlier scaffold; `views/AirModule.php` was repointed at
`iar-module.php`, but nothing in the menu uses them any more — safe to remove once the `.pmp` project
no longer generates them.

> If you're fixing any of the above: §7.1 and §7.2 are independent, low-risk, verified fixes. §7.4
> is a safe delete (confirmed zero callers). §7.3 and §7.5's original issues are now resolved (see
> their updated text above) — only §7.5's `AirModule` leftover cleanup remains open. None of these,
> besides §7.3/§7.5 resolving themselves via the Memo Request build-out, have been applied — this
> guide only reports what's there.

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
| Budget Proposal | `app/pages/budget-prop-page.php` (+ the shared `form/budget-req-form.php` helpers, `form/budget-processing-form.php`, `readone/budget-preview-readone.php`) + `BudgetReqService.php` + `app/routes/fmd_budget_req.php` | `fims.budget_req` (+ its program / project / line-item tables) | The Financial Management Department's list of every Regional / District office's request: Submission Ref No., Region / Originating Unit, Submitted Date (`budget_req.submitted_at`), Amount, Status. Fetched from `GET /fmd/budget_req/list`, which fixes the scope server-side (every Regional / District office, for any logged-in user — no longer FMD only) — the page sends no RC parameters |
| Budget Revision | `app/pages/budget-revision-page.php` + `form/budget-rev-form.php` + `form/budget-rev-review.php` + `BudgetRevisionService.php` + `app/routes/budget_revision.php` | `fims.budget_req`, `budget_revision` | Revises an existing approved request; the Add/Edit form has Revision / Realignment tabs (a Realignment moves funds to a To budget line) and the table's Type column shows `budget_revision.request_type`; `budget-rev-review.php` is the approve/reject reviewer modal (`PUT /budget_revision/review/{id}`) |
| Memo Request (CBA / RFA / LAA) | `app/pages/memo-req-page.php` (list, tabbed CBA/RFA/LAA) + `form/budget-allocation-form.php` (the Add/Edit form, opened as a modal, `window.cbaModal`) + `readone/memo-process-readone.php` (FMD's Approve/Return review screen) + `BudgetAllocationService.php` + `app/routes/budget_allocation.php` | `fims.budget_allocation`, `fims.budget_line_item_earmark` (+ `budget_line_items` it reserves against) | Built out 2026-11-10/12 (§7.3/§7.5's old issues are resolved by this — see there). One table, one `allocation_type` column drives three document types: **CBA** (Certificate of Budget Allocation, the root reservation against a budget line item — no Program/Sub-Program/Project fields in the form), **RFA** (Request for Fund Allotment, draws down a parent CBA, used under ₱50M) and **LAA** (Letter of Advice of Allotment, same conversion as RFA, ₱50M and up) — both require picking a Parent CBA (`GET /budget_allocation/all?allocation_type=CBA&status=Approved`). FMD review chain (`PATCH /budget_allocation/process/{id}`): `For Review` (Section Chief) → `For Division Manager` → `For Department Manager` → `Approved`, or returned one step back; only FMD users (rc_code `35-074-0000000-032`) holding the current step's user level may act — everyone else gets a read-only "Process" action hidden and sees only their own office's requests (server-decided, not client-sent). On final Approval the request gets a server-generated `allocation_number` (template varies by type — `CBA 2026-42-86-01` / `2642-87-01` / `LAA 2026-42-01`, see `app/routes/README.md`) and an `active` row is inserted/converted in `budget_line_item_earmark` (see `system_docs/ERD/README.md` §2.1). An Approved RFA also soft-links (`budget_allocation.dv_id`, no FK) to the one Disbursement Voucher it's currently drawn into — LAA isn't wired into the DV module yet. Supporting Documents upload via `file_upload.php` (§8.5-adjacent, see `app/routes/README.md`) |
| Corporate Operating Budget (COB) — Monitoring menu | `app/pages/cob-page.php` + `readone/cob-readone.php` (the View modal, `window.cobModal`) + `readone/cob-report-readone.php` (Generate Report → PDF / Excel, `window.cobReport`) + `CobService.php` + `CobReportService.php` + `XlsxWriter.php` + `app/routes/cob.php` | `fims.budget_req` and what hangs off it: `budget_req_program`, `budget_req_project`, `budget_line_items`, `budget_req_project_attachment`, plus `budget_line_item_earmark` / `budget_allocation` for what CBAs / RFAs / LAAs drew | Read-only monitoring of **Approved** budget requests only, in the columns of the Corporate Operating Budget Monitoring sheet (Approved Amount, Revision / Realignment, Revised Amount, CBA Requested, Available for Earmarking, RFA Approved, LAA Issued, Remaining Balance; Approved Amount = `budget_line_items.suggested_amount`). See `system_docs/modules/COB-MONITORING.md` |
| Fiscal Year | `FiscalYearService.php` + `app/routes/fiscal_year.php` | `fims.fiscal_year` (reads `budget_req`) | Read key is `{fiscal_yr}`, not `{id}`. Create/delete only, no update route. No dedicated page — feeds dropdowns on the budget pages |
| Expense Class | `ExpenseClassService.php` + `app/routes/expense_class.php` | `fims.expense_class` | Standard CRUD shape. No dedicated page — dropdown source |
| Component Type | `ComponentTypeService.php` + `app/routes/component_type.php` | `fims.component_type` | Standard CRUD shape. Used by the Budget Processing line-item form |
| Type of Fund | `TypeOfFundService.php` + `app/routes/type_of_fund.php` | `fims.type_of_fund` | Standard CRUD shape. Used by the Budget Processing line-item form |

### 8.3 Reference / Master Data
| Feature | Files | Backing table(s) | Purpose |
|---|---|---|---|
| Program (with its Sub-Programs) | `app/pages/program-page.php` + `form/program-form.php` + `form/sub-program-form.php` + `ProgramService.php` + `app/routes/program.php` | `fims.program`, `sub_program`, `project` | One tree-table page in the shape of the SARO register: a program row expands into its sub-programs; the "+" in the Program Code header adds a program, the "+" on a program row opens the Add Sub-Program form with that program picked (the page drives that form with its `spf_*` functions, so it has no "Add Program" button). Keyed by `{code}`. `GET /program/next-code` previews a server-generated code (`GlobalService::generateProgramCode()`). `GET /program/list?with_sub_programs=1` adds `sub_programs[]` and `sub_program_count` to each program and lets the search match a sub-program's code / name; without the flag (and on `/program/all`) the rows are as before. `DELETE /program/delete/{code}` is refused while the program has sub-programs (`sub_program.program_code` references it, no cascade), projects, or anything else that references it (budget requests, …) — with a message saying what, never the raw foreign-key error (`GlobalService::referencesTo()` reads the database's own foreign keys); the page shows no Delete button for a row that has sub-programs |
| Sub-Program (own page) | `app/pages/sub-program-page.php` + `form/sub-program-form.php` + `SubProgramService.php` + `app/routes/sub_program.php` | `fims.program`, `sub_program` | Keyed by `{code}`. `POST /sub_program/add-with-items` batch-creates multiple sub-programs under one program in one call. `DELETE /sub_program/delete/{code}` is refused while projects, budget requests or memo requests still use the sub-program, with a message naming them (same helper). The same form is also used by the Programs page (above); the form file is markup and styling only, each page carries its own behaviour for it |
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
  (PO picker), `GET /po-dr/accounts` (Account Code picker; `?all=1` skips the ten-account limit, `?infra=1` = the four CIP/WIP accounts of the Infrastructure tab), `GET /po-dr/read`, `GET /po-dr/proforma`,
  `POST /po-dr/proforma-submit` (the Pro-forma's Submit), `POST /po-dr/add`, `PUT /po-dr/update`, `DELETE /po-dr/delete`.
- **Budget allocation lines.** `fims.budget_allocation.line_transaction` was dropped (alter 48): the RFA's lines live on its project blocks
  (`fims.budget_allocation_project.line_transaction`, one row per block). `PoDrService::allocationLinesSql()` reads whichever layout the database has
  — never reference `a.line_transaction` directly. A PO whose RFA is linked to an **active Disbursement Voucher** (`budget_allocation.dv_id`, DV not
  CANCELLED / REJECTED) is left out of the PO picker and `create()` refuses it (`PoDrService::dvOf()`).
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
- **Edit is limited.** The edit modal (with a DR picker for POs with several DRs) can change only the **Sales Invoice # / date** and the chosen DR's
  items' **description, serial no. and property no.** Everything else is locked (IAR number, DR number / date, PO, account rows, items' amounts and
  categories; no Add row / Add item / remove). `PoDrService::update()` enforces this: a different `po_number` is refused (`The PO number cannot be
  changed`); the IAR number, DR number / date, entries, amounts and categories sent are ignored (the stored ones are kept); the items are matched by
  position (a different count is refused), the description is required, and serial numbers stay unique — so an edit can never change the PO balance.
- **Pre-fill from the Budget Allocation.** Picking a PO on a new DR pre-fills the account rows from the RFA's
  `line_transaction` (`account_code` + `requested_amount`; `GET /po-dr/pos` returns them as `lines`). The rows are
  filled in order until the PO's available balance is used up, and the user can change the accounts and amounts.
  Only a new DR whose rows are still untouched is pre-filled — an edit never is.
- **Business rules (server-enforced in `PoDrService::clean()`).** DR number + delivery date required;
  at least one account row; each row has an account code that exists in `fims.chart_of_accounts` and an
  **amount** > 0. There is **no debit/credit to balance** — the user only enters account codes and amounts;
  the validation is the PO balance (next bullet). The DR **Amount** (`total_amount`) is the sum of the
  row amounts, computed server-side. **Items are per account row**: every account row of **50,000 or more** (`ITEM_MIN_AMOUNT` in the service,
  `AMP_ITEM_MIN_AMOUNT` in the form) needs items; rows below it have none. The form creates one item per qualifying row (amount pre-filled, **description
  required**); every item has a **category** (one of the qualifying accounts, stored as `account_code` on the item), and amounts / categories are
  editable, items can be added or removed — so one account amount can be split into several items. The items **of each account must add up to that
  account's amount** (checked in the form and in `PoDrService::clean()`; items sent without categories are only compared in total). A serial number is
  unique across all items of all DRs; a DR number is unique within its PO.
- **PO balance limit.** A DR's total may not exceed the PO's **available balance** = PO amount − the totals of
  the PO's *other* DRs (the DR being edited isn't counted against itself). Enforced in
  `PoDrService::drError()` on add and update; the form shows the **Balance (after this DR)** field next to
  PO Amount and disables Save with an "Exceeds PO balance" pill when the total (shown under the Amount column) is over it. A PO that is no longer
  an RFA PO (legacy data) has no limit. `GET /po-dr/pos` returns `dr_total` (already delivered) for the picker and
  **leaves out POs with no balance left** (`po_amount - dr_total <= 0`), so a fully delivered PO can't be picked for a new DR;
  an existing IAR of such a PO stays in the list and can still be viewed and edited.
- **Account Code picker** (`GET /po-dr/accounts`): with nothing typed it offers **only the ten property accounts** (`PoDrService::PRIORITY_ACCOUNTS`:
  `1-06-05-020 … 1-06-99-990`, and their sub-ledgers); once the user types, the **whole chart of accounts** is searched (active, `posting_allowed`; by code
  or title, 30 hits, the property accounts first). An SL is a child row of its GL account (`parent_account_id`, e.g. `5-02-03-010-01`); a GL account that has
  postable SLs is not offered itself. Only the code is stored; the form shows "code - title". A new SL's `account_id` is generated as MAX(digits)+1.
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
  (Accounts Payable) — the **vendor's own AP sub-ledger** (`vendors.sl_code` → a chart-of-accounts child row, e.g. `2-01-01-010-01`) when it has one, else the **default "Account Payable 1" `2-01-01-010-01`** (`PoDrService::payableAccount()`, shared with the Infrastructure tab), else the first active SL under
  `2-01-01-010`, else `2-01-01-010`. **Book** (defaults to Main Office, book 1) and **Particulars** (pre-filled "To record the delivery of {DR} (PO {PO})") sit inside each DR's tab.
  A **Total** row shows the debit and credit totals under their columns.
  The entries are **editable** in the modal: change a row's account (searchable over the chart of accounts) and its **debit or credit** amount (a row is
  one or the other), add a row (the **+ Add row** button is above the table) or remove one. The edits are a draft kept per DR tab (switching tabs keeps them;
  closing the modal drops them) — nothing is stored. **Submit** is disabled when a row is incomplete or when the **total debit or the total credit exceeds
  the DR's total amount** (red "Exceeds DR total" pill beside the Total). The entry must also **balance** (total debit = total credit), but that is only
  checked when **Submit is clicked**: a SweetAlert warning "Not balanced: total debit ₱X and total credit ₱Y are not equal (difference ₱Z)" is shown
  and nothing is submitted — no pill or message is shown while editing. **Submit** (SweetAlert confirm) calls `POST /po-dr/proforma-submit`: it creates a standalone JEV
  (`DisbursementVoucherService::createStandaloneJev`, date = the delivery date), records `jev_id` / `jev_no` on the DR inside `line_transaction`
  (`PoDrService::linkJev`), then moves it DRAFT → `FOR_SECTION_CHIEF` (`changeJevStatus`) and redirects to the Journal Entry Vouchers page (Accounting Dept /
  superadmin only). A DR with a JEV cannot be submitted again (409): its Pro-forma is **read-only** (saved rows, no Add row / Submit, "Submitted as … JEV"), and **Print JEV**
  prints with the JEV number and date. `PoDrService::shape()` must return `jev_id` / `jev_no` or the lock silently fails.
- **Infrastructure tab** (second tab of the IAR page; `form/infra-iar-form.php` + `InfraIarService.php` + `app/routes/infra_iar.php`; table `fims.statement_work_accomplishments`,
  `01 - table/50`; sample RFAs / locations `02 - sample_data/12`). One record per **approved Infrastructure RFA without a PO** (`allocation_type='RFA'`, status Approved, category
  Infrastructure, no `po_number`, no active DV, no record yet). The header button reads **Add Infrastructure Record**; the list shows SWA Number, RFA Number, Project Name, Contractor,
  **Regional Office/District Office**, Revised Contract Cost and % Accomplishment.
  - **What is stored**: only `swa_number`, `allocation_id`, **Contract**, the dates / duration and `line_transaction`. Everything the RFA already knows — RFA number, project code / name, program,
    contractor, location, regional / district office **and the cost figures** (original contract cost, variation order, revised contract cost, `rfa_amount`) — is **read live** by
    `InfraIarService::live()` through `allocation_id` (RFA → project / program / vendor / budget request / budget line items) every time (list, view, pro-forma, search), like the IAR reads its PO and
    supplier; those columns were dropped (`01 - table/50` migrates an older table: `iar_number` → `swa_number`, drops the columns, converts `line_transaction`).
  - **`line_transaction`** (same idea as a delivery receipt in the IAR module's `line_transaction`): one element `{ items[] (the work items), lines[] (the account entries), jev_id, jev_no }`.
    `jev_id` / `jev_no` are written into it when the pro-forma is submitted (`linkJev()` / `jevOf()`).
  - **Add**: pick the RFA (searchable) and the form loads, all read-only: Regional Office/District Office, **Project Name**, **Location** (`budget_req_project.location`), **Program**, **Contractor** (the
    RFA's vendor) and the cost breakdown — **Original Contract Cost** (Σ `suggested_amount` of the budget line items the RFA draws on; `proposed_amount`, then the RFA amount, when there is none),
    **Variation Order** (Σ of those lines' `budget_line_items.adjustment` = the net of their *approved* Budget Revisions / Realignments; + increase green, − decrease red; **0 until a revision is
    approved** — a request that is only filed or in review does not move it) and **Revised Contract Cost** (original + variation; the RFA amount when no line is linked). The user types **Contract**
    (free text, up to 255 characters), **Date Started** and **Completion Date (Revised)**; **Contract Duration** is auto-computed ("N calendar days", start → end inclusive) or can be typed (typing
    stops the auto value, clearing the box brings it back).
  - **SWA Number**: generated, never typed — `SWA-YYYY-MM-0001`… (`InfraIarService::nextSwaNumber()`, stored in `swa_number`). The Add form previews it read-only (`GET /infra-iar/next-number`);
    the server assigns it again on save.
  - **Account Entries**: pre-filled from the RFA lines; the Account Code picker (`GET /po-dr/accounts?infra=1`) offers **only** `1-06-99-010`, `1-06-99-020`, `1-06-99-030` (CIP) and
    `1-04-03-020` (WIP). Their total may not exceed the **RFA amount** (`rfa_amount`). They are **not editable after the record exists and the whole section is hidden in the Edit window**.
  - **Statement of Work Accomplishments** (items): `Description | Cost | % Accomplishment` (no quantity). Description is a dropdown below the box with the standard work items
    (`IFR_SWA_ITEMS` in the form; filters while typing) and can still be typed freely. Two rules, both shown in red and enforced again by the server:
    **the items' total cost may not exceed the total of the Account Entries** (from the item that takes the running total past it, its cost box turns red, as does the Total cost figure), and
    **each item's % is its share of the whole project, so the % column adds up to at most 100** (rows past 100 turn red; the overall accomplishment is the sum, shown uncapped, e.g. 60 + 50 = 110% in red).
    Save is disabled while either is broken. At least one item.
  - **Edit** changes only the contract, dates, contract duration and items (RFA, SWA number and account entries stay). No Delete.
  - **View** card: SWA No., RFA No., Location, Contractor / Regional Office/District Office, Project Code, Project Name, Program, Contract, Original Contract Cost, Variation Order, Revised Contract Cost,
    dates, duration, the Account Entries and the Statement of Work items.
  - **Pro-forma JEV** (Actions → Pro-forma; the Deliveries window with `amp_pf.kind = 'infra'`, `GET /infra-iar/proforma`): no receipt tabs and no Total Amount card (one record per RFA);
    the card shows the SWA no., RFA, **Regional Office/District Office**, **Project Code**, **Project Name**, **Program**, **Location** and **Contractor**. Each account entry is a debit and the total is
    credited to the contractor's AP SL, else the default `2-01-01-010-01`. The amounts are entered by the user — **not capped at the record's amount** (only debit = credit is required). Submit →
    `POST /infra-iar/proforma-submit`, same creation + auto-submit as Deliveries, and `jev_id` / `jev_no` are stored in the record's `line_transaction` (409 on resubmit; the pro-forma then opens read-only).
  - **Print JEV** (Deliveries and Infrastructure): prints the **RFA No.** and the **Particulars** text exactly as typed (the generic "Pro-forma JEV — …" line is only the fallback for an empty box).
- **Work-In-Process Inventory page** (`app/pages/work-in-process-page.php`, PHPMaker page `WorkInProcessPage`, menu "Work-In-Process Inventory"): one row per infrastructure project whose pro-forma JEV was
  **submitted** (the JEV is in the record's `line_transaction`) **and carries account `1-04-03-020`** (Work-in-Process, or one of its sub-ledgers) — a JEV without it (e.g. CIP only) does not show.
  Columns: Book of Accounts (the JEV's book), Regional Office/District Office, Name of Project, Location, Contractor, Contract, Original Contract Cost, Variation Order, Revised Contract Cost,
  Physical Accomplishment (all the work items' % combined = their sum) and Actions (View only). The View window shows RFA No., SWA No., Book of Accounts, Regional Office/District Office, Project Code,
  Name of Project, Program, Location, Contractor, Contract, the three costs, Physical Accomplishment, Date Started, Completion Date (Revised), Contract Duration and the work items (no JEV details).
  Data: `GET /infra-iar/work-in-process` (`InfraIarService::workInProcess()`); everything about the project is read live from the RFA, as on the Infrastructure tab.- **Screens.** The list's Actions column is one **Actions** dropdown (View · Pro-forma · Edit), the same menu as the Memo Requests list. The **View** (Actual Delivery) and **Pro-forma JEV** windows share one
  modal in the Memo Request details design (dark header with the IAR number, light body of white cards, Close in the footer); the Pro-forma opens wider (1500px). There is **no PAR # field**: the PIS property
  number is the PAR number (Property Records), so an item line is Description | Serial No. | Amount | Prop. No.
- **View card** (Actions → View): `IAR No. | Total Amount`, `Supplier | RFA No.`, `P.O. No. | P.O. Date`, `S.I. No. | S.I. Date`, `Requisitioning Office/Dept`, then the delivery
  receipts. The add / edit form also shows the read-only **RFA Number** (`budget_allocation.allocation_number`, returned as `rfa_number`).
- **JEV print** (`JevPrintService`, used by every JEV print): lines are ordered **debit first, then credit, by GL code**; an account code with a 5th part
  (`2-01-01-010-01`) prints as a bold GL row (the GL amount) with an indented SL row (SL code `01`, amount in the Subsidiary Ledger columns); a GL with no SL
  also fills the Subsidiary Ledger columns, so both totals equal the voucher total. No separate "P" column: one ₱ at the left of the first debit in the SL and in
  the GL columns (and in the TOTAL row); the TOTAL label sits in the Accounts column. `scripts/gen_jev_docx.js` (DOCX) was not updated.
- **Sample data.** `02 - sample_data/12 - fims_iar_property_sample.sql` adds six RFA POs (`SAMPLE-PO-PPE-01…06`) with IARs and property items (Jan–Jul 2026, several
  responsibility centers) for testing the Property Records page and the depreciation pro-forma; idempotent, works with either `budget_allocation` layout.
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
- **Page layout now.** The **Add from PIS** button is hidden (its code is kept); there is no empty-state box and no category cards. The page shows the **items entered in the IAR module**
  for the property accounts: `GET /property-record/iar-items` (`PropertyRecordService::iarItems()`) returns the DR `items` whose account is one of the 10 property GL codes
  (`CATEGORIES`, or a sub-ledger of one), with the PO's responsibility center (the RFA's `requesting_rc_id`), the DR's delivery date as the acquisition date and the item amount as the
  acquisition cost. An item saved before the category existed is matched to the account row of the same amount.
- **The list** (one row per property account, all ten even at 0.00): `ITEM | ACCT. CODE | Amount | Acct. Code (the accumulated-depreciation account, e.g. 1-06-05-021) | Accu. Depr'n | Net Book Value`,
  a bold **TOTAL** row (₱ at the left of the amount, only on the first row and the total), filters (searchable Responsibility Center and Account Code, a text Search), **Expand All / Collapse All**,
  and clicking a row opens its items (`Description - Item | Serial No. | Property No. | Responsibility Center | Acq. Date | Acq. Cost | Residual Value | Monthly Depr'n. | Accumulated Depreciation |
  Net Book Value as of today`); clicking an item opens its details.
- **Depreciation** (computed in the page, `prp_depr()`; `PropertyRecordService::monthsSince()` follows the same rule for the saved register): Monthly = cost × .9 ÷ life months (**360** buildings
  `1-06-04…`, **120** land improvements `1-06-03…`, **60** everything else); Residual value = 10% of the cost; Accumulated = Monthly × months used, capped at 90%; Net Book Value = cost − accumulated.
  **Depreciation starts the month after the acquisition (delivery) date** (delivered Jan 1 → first month February); the "as of" date is today.
- **Pro-forma JEV** (header button beside Refresh): the depreciation of a quarter. Book (default Main Office), Quarter + Year (default the latest completed quarter) and Particulars ("To take up Depreciation
  Cost of PPE for the 2nd Quarter (April - June) 2026 Main Office"). Per property category the quarter's depreciation (accumulated at quarter end − at the previous quarter end) is debited to the
  depreciation expense account + SL and credited to the accumulated-depreciation account (`PRP_JEV_CFG` in the page: e.g. Office Equipment → Dr `5-05-01-050-02`, Cr `1-06-05-021`; the SLs come from
  `02 - sample_data` / the SL seed `09 - fims_chart_of_accounts_sl.sql`). The rows are editable like the IAR pro-forma; **Submit** = `POST /property-record/proforma-submit`
  (standalone JEV dated the last day of the quarter, sent for approval; refused when a JEV of that book with the same particulars exists — `proformaJev()` / `GET /property-record/proforma-jev` lock the
  window read-only for a submitted quarter); **Print JEV** posts the lines to `POST /print/jev` (own preview + PDF download — the shared print preview can't be included here, it uses the same `prp_` names).
  `GET /property-record/account-titles?codes=` supplies the account titles.
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
FOR_TREASURY → RELEASED` (or `REJECTED`/`CANCELLED`), with the **SETUP** Journal Entry prepared
during `FOR_ACCOUNTING` (its Accounting Entries grid auto-filled from the DV's Deduction/ATC rows, and
its **Book of Accounts** + optional **classification** chosen there too — see below). A DV now carries
**one SETUP JEV plus any number of PAYMENT JEVs** (not strictly one JEV per DV any more — see the
Payment-settlement note). `CheckProcessingService` (§8.6) is the only thing that can move a DV from
`FOR_TREASURY` to `RELEASED`, by preparing a check against it.

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

**Extended 2026-11-15** (the SQL for this is **uncommitted** in `_assets/sql/` as of this writing;
read from code, not re-verified against a fresh install). Read the ERD doc's **§2.8** for the
schema-level detail — the summary of what's new, and where each piece lives:

- **Books of Accounts** (`fims.books` — Main Office, CISFA, NGC, MPLC/PPMO, PPMO). A "Book" is an
  Accounting classification chosen on the DV's Accounting Entries section (`readone.php`'s
  `vje_entriesCard()`), **not** a DV-header field — it's saved onto the JEV (`persistJournalEntry()`'s
  `$bookId`) and copied down to each `general_ledger` row at post time. All four ledger pages (and the
  JEV register, CKDJ/ADADJ) scope by it. `book_id` was added to `journal_entry_vouchers`,
  `general_ledger`, and `official_receipts` (`03 - alter_table/30`); `fims.books` is `01 - table/41`.
- **Payment-settlement JEVs** (`GeneralLedgerService::postPaymentJev()`, called from
  `CheckProcessingService::prepareCheck()` right after a DV is `RELEASED`). The SETUP JEV raises
  Accounts Payable at approval time; this inserts a **second, distinct** JEV (`jev_type = 'PAYMENT'`,
  `source_module = 'TREASURY'`, `doc_ref_no = 'CHECK NO. …'`) that Debits AP / Credits the bank
  account's mapped Cash account (`fims.bank_accounts.account_code`, new nullable FK) and posts it —
  closing the loop so AP comes back down and Cash moves. Errors out if the bank account has no Cash
  account mapped; a clean no-op if the SETUP JEV never routed through AP. This is why
  `journal_entry_vouchers.dv_id` is no longer `UNIQUE` — the guarantee became a partial unique index
  on `(dv_id) WHERE jev_type = 'SETUP'` (`03 - alter_table/31`). `source_module`/`doc_ref_no` are on
  both `journal_entry_vouchers` and `general_ledger`.
- **JEV classification** (`journal_entry_vouchers.jev_classification`: `REGULAR` default / `ACCRUAL` /
  `DEPRECIATION` / `PREPAYMENTS` / `CLOSING`, `03 - alter_table/32`) — a reporting label, **orthogonal
  to `jev_type`** (SETUP/PAYMENT, which is posting mechanics). Passed to `saveJournalEntry()` /
  `POST /voucher/{id}/journal-entry` as an optional `jev_classification` field.
- **Standalone (no-DV) Journal Entry Vouchers** (`03 - alter_table/33` makes `dv_id` nullable). For
  period-end adjusting/closing entries Accounting books directly, with no Disbursement Voucher. Backed
  by `DisbursementVoucherService::{create,update,delete,post}StandaloneJev()` and new routes under
  `app/routes/voucher.php`: `GET /voucher/journal-entry/{id}`, `POST /voucher/journal-entry/add`,
  `PUT /voucher/journal-entry/update/{id}`, `DELETE /voucher/journal-entry/delete/{id}`,
  `POST /voucher/journal-entry/{id}/post` (the standalone equivalent of a DV's
  `FOR_ACCOUNTING → FOR_TREASURY` transition triggering a post). The Add-JEV form lives in
  `app/pages/journal-entry-voucher.php` (the JEV-register page, +411 lines).
- **JEV register page / view.** `app/pages/journal-entry-voucher.php` lists JEVs from the new
  `fims.v_journal_entry_vouchers` view (`05 - views/02`) — one row per JEV header with its DV, book,
  and both `jev_status`/`dv_status` labels. `GET /voucher/journal-entries` now reads this view and
  accepts `jev_type` / `jev_classification` filters in addition to `fiscal_yr` / `search`. The JEV's
  own lifecycle (`DRAFT → APPROVED → POSTED`, or `REVERSED`) is distinct from its DV's; note that while
  all four statuses are seeded and badged, `postJev()` currently writes `DRAFT → POSTED` directly (no
  code path writes `APPROVED` yet — see the ERD §2.8 correction note).

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
| Check Disbursement Voucher (Check Processing) | `app/pages/check-disbursement-voucher-page.php` + `CheckProcessingService.php` + `app/routes/check-processing.php` | `fims.checks`, `check_booklets`, `check_register`, `check_cancellations`, `check_claimant_releases`, `check_claimant_email_log`, `check_region_releases`, `check_region_email_log` | 7 tabs ported from TREASURY's 7 check-processing pages: **Check Preparation** (only a `FOR_TREASURY` DV can get a check — doing so calls `DisbursementVoucherService::changeStatus()` to flip it `RELEASED`, same auth as every other caller, and then posts a **payment-settlement JEV** via `GeneralLedgerService::postPaymentJev()` — see §8.5's 2026-11-15 note — so Accounts Payable comes down and Cash moves; the bank account must have a Cash `account_code` mapped first), **Check Registry** (`check_booklets`/`check_register`, incl. the RAF inventory tab computed live from `fims.checks` rows in a booklet's number range, not stored), **Released to Claimants**, **Released to Regions/Departments** (both send real SMTP email via `App\SendEmail()`, a PHPMailer wrapper — Regions' Google-Drive attachment is still a `saveRegionDriveLink()` stub), and **Cancellation & Replacements**. All RC-scoped like Bank Management, not department-scoped |

### 8.7 Fund Tracking (SARO / NCA / Trust Funds / Supplemental)

One table, `fims.fund_tracking`, backs **4 distinct pages** filtered by `tracking_type_id` /
`nature_of_fund_id` — know which page you're looking at before assuming a filter is missing:

| Page | Files | Filter | Purpose |
|---|---|---|---|
| SARO / NCA Tracking | `app/pages/saro-registry-page.php` + `form/saro-processing-form.php` | `tracking_type IN (SARO, NCA)`, `exclude_nature_of_fund=SUPPLEMENTAL` | One tree table, no tabs: every SARO is a parent row with its NCA releases nested under it (an NCA with no SARO is a row of its own), and the Tracking Type filter (All Types / SARO / NCA — NCA is a flat list) narrows it. Columns run Date → Amount → NCA Received → Unreleased (a SARO's amount less its NCAs, calculated from the NCAs, not read from the stored `unreleased` column; an NCA has no Nature of Fund). The entry form opens with "What are you adding?" (SARO or NCA, `$sarTypeFirst`); choosing SARO removes the parent field (a SARO has no parent and is saved with `parent_id` null); choosing NCA shows a **SARO** field (SAROs only) for the SARO it was released under |
| Supplemental Fund Tracking | `app/pages/supplemental-funds-page.php` | `nature_of_fund = SUPPLEMENTAL` | Flat list, no tree |
| Trust Funds | `app/pages/trust-funds-page.php` + `form/trust-fund-form.php` | `tracking_type = TRUST` | Register of Trust Fund records + their Official Receipts |
| Fund Sourcing Dashboard | `app/pages/fund-sourcing-dashboard-page.php` | (aggregated, no single filter) | Summary view over the whole table — `GET /fund_tracking/fund_sourcing_determination` / `/supplemental_status_summary` / `/dashboard` |

`unreleased` (and `sub_program_code`) were dropped from `fims.fund_tracking` (`_assets/sql/01 - fims/03 - alter_table/41`, destructive: the values were lost);
`unreleased` NUMERIC(18,2) is back (`03 - alter_table/47`, backfilled by `48`): on a SARO it is its **balance** — the SARO's amount less every live NCA released under it —
which the API (`FundTrackingServices::refreshSaroUnreleased()`) saves and keeps up to date whenever a SARO or one of its NCAs is added, changed or deleted
(Excel imports included); it is NULL on every other record and a request can't write it. The SARO / NCA Tracking page still works its Unreleased column out
from the SARO's NCAs, which comes to the same figure.
The record's number and title columns are `saro_id` (was `reference_number`) and `description` (was `name`), renamed by `03 - alter_table/45`, which also
brought back `sub_program` VARCHAR(225) (the API reads and writes it; the SARO entry form's Sub-Program picker — a SARO only — saves the chosen `fims.sub_program` code in it, and no other page lists it yet). The SARO / NCA Tracking page labels them **SARO ID** and **Description**. A record's `date` is **typed in**: the SARO entry form (SARO, NCA and Supplemental) has a required Date, sent on add and update, and its
Fiscal Year is a typed 4-digit year too (no longer a pick-list). A request that sends no date — the Trust Fund and OR forms have no Date field — is dated
the day it is created; the Excel imports keep a date their sheet carries and default to the import day.

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
