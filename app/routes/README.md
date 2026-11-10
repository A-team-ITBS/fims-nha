# API Routes Directory

This directory contains organized API route definitions for the __BASE__ application template.

## Structure

```
routes/
├── notifications.php         # Notification management routes
├── service.php               # General service routes
├── user-access.php           # UserAccess page routes (create user, save permissions)
├── department-management.php # Department Management page routes (create/update department)
├── position-management.php   # Position Management page routes (create/update category, position)
├── vendor.php          # Vendor + vendor type routes
├── purchase-order.php  # Purchase order CRUD + status routes
├── voucher.php         # Disbursement Voucher CRUD + status + RFA lookup routes
├── chart_of_accounts.php # Chart of Accounts CRUD + status routes
├── responsibility_center.php # Responsibility Center CRUD + status routes
└── README.md                 # This documentation
```

## Route Organization

**Note:** These routes are only registered when `app/api.php` is included via the `ApiActionEvent`
(`src/userfn.php` → `Api_Action()`), which is dispatched from `api/index.php` — a *separate* front
controller from the main `index.php` that renders pages. `api/index.php` sets its Slim base path to
`/api`, so every route below is reached externally (and from `fetch()` calls in `app/pages/*.php`)
with an `/api` prefix, e.g. `POST /user-access/users` below is actually `POST /api/user-access/users`.

### notifications.php
- `GET /notifications/recent` - Get recent notifications
- `GET /notifications/count` - Get unread count
- `POST /notifications/mark-read` - Mark notification as read
- `POST /notifications/mark-all-read` - Mark all as read
- `POST /notifications/send` - Send notification
- `GET /notifications/test` - Send test notifications

### service.php
- `GET /Service` - Generate OpenAPI documentation

### user-access.php
- `POST /user-access/users` - Create a user (backs the Add User modal on `app/pages/user-access.php`)
- `PUT /user-access/users/{id}` - Update a user (backs the Edit User modal; no redirect to `UsersEdit`)
- `POST /user-access/permissions` - Save a user level's module permission bitmask (backs the Permission Matrix on `app/pages/user-access.php`)
- `POST /user-access/sync-modules` - Reconcile `uac.user_level_permissions` with PHPMaker's own table/page registry (`$USER_LEVEL_TABLES`), adding zero-permission rows for any module missing from a user level
- `POST /user-access/module-caption` - Rename a module/page row's display caption in the Permissions Management matrix (upserts into `permission_module_captions`; does not touch the PHPMaker-generated `$USER_LEVEL_TABLES` registry)
- `POST /user-access/module-visibility` - Hide or restore a single module/page row in the Permissions Management matrix (`permission_module_captions.is_hidden`); hiding does not delete any `uac.user_level_permissions` rows, and is reversible via the "Show hidden modules" checkbox
- `POST /user-access/user-levels` - Create a user level / role (backs the Create User Level modal; also seeds zero-permission rows for every known module, matching `UserLevelsAdd`'s own behavior)
- `PUT /user-access/user-levels/{id}` - Update a user level / role's name, description, and system (backs the Edit User Level button next to the role dropdown; no redirect to `UserLevelsEdit`)
- `POST /user-access/assignments` - Assign a user level (role) to a user (backs the Assign Access button; no redirect to `UserLevelAssignmentsAdd`)

### department-management.php
- `POST /department-management/departments` - Create a department (backs the Add Department modal on `app/pages/department-management.php`; no redirect to `DepartmentsAdd`)
- `PUT /department-management/departments/{id}` - Update a department (backs the Edit Department modal; no redirect to `DepartmentsEdit`; rejects parent choices that would create a hierarchy cycle)

### position-management.php
- `POST /position-management/categories` - Create a position category (backs the Add Category modal; no redirect to `PositionCategoriesAdd`)
- `PUT /position-management/categories/{id}` - Update a position category (no redirect to `PositionCategoriesEdit`)
- `POST /position-management/positions` - Create a position (backs the Add Position modal; no redirect to `PositionsAdd`)
- `PUT /position-management/positions/{id}` - Update a position (no redirect to `PositionsEdit`)

### vendor.php (`VendorService`, table `fims.vendors` / `fims.vendor_types`)
- `GET /vendors/types` - Active vendor types (dropdown)
- `GET /vendors/all` - All vendors, no pagination (dropdown). Optional: `search`, `type`, `active=1` (hides non-ACTIVE)
- `GET /vendors/list` - Paginated; also returns `summary` {active, inactive} for every row matching the filters. Optional: `page`, `limit`, `search`, `type`, `active`, `status` (ACTIVE / INACTIVE / BLACKLISTED)
- `GET /vendors/read/{id}` - One vendor
- `POST /vendors/add` - Create (`vendor_code` and `vendor_name` required, `vendor_code` unique)
- `PUT /vendors/update/{id}` - Update

### purchase-order.php (`PurchaseOrderService`, tables `fims.purchase_orders` / `fims.purchase_order_items`)
- `GET /purchase-order/all` - All POs, no pagination. Optional filters: `status`, `fiscal_yr`, `expense_class`, `vendor_id`, `search`
- `GET /purchase-order/list` - Paginated (`page`, `limit` + same filters); also returns `summary` (totals / counts for every row matching the filters)
- `GET /purchase-order/fiscal-years` - Fiscal years for dropdowns
- `GET /purchase-order/po-number?po_date=YYYY-MM-DD` - Next free `PO-YYYY-MM-NNNN`
- `GET /purchase-order/read/{id}` - One PO (numeric id or po_number) with `items[]` and `allowed_transitions`
- `POST /purchase-order/add` - Create PO + items; `status` DRAFT (default) or SUBMITTED
- `PUT /purchase-order/update/{id}` - Replace fields + items; DRAFT / REJECTED only
- `POST /purchase-order/{id}/status` - `{status, notes?}`: SUBMITTED / APPROVED / REJECTED (notes required) / CANCELLED
- `DELETE /purchase-order/delete/{id}` - DRAFT or CANCELLED only; items cascade

### voucher.php (`DisbursementVoucherService`, table `fims.disbursement_vouchers`)
One generic voucher shape for any claimant/payment type (vendor payment, payroll, reimbursement, etc.) — `claimant_name`/`claimant_address`/`employee_no_tin` are either snapshotted from a selected `vendor_id`, or typed free text.
- `GET /voucher/all` - All DVs, no pagination. Optional filters: `status`, `fiscal_yr`, `expense_class` (MOOE/PS/CAPITAL_OUTLAY/OTHER — classification tag only, same set `fims.purchase_orders.expense_class` uses), `search`
- `GET /voucher/list` - Paginated (`page`, `limit` + same filters); also returns `summary` (totals / counts for every row matching the filters)
- `GET /voucher/fiscal-years` - Fiscal years for dropdowns
- `GET /voucher/dv-number?dv_date=YYYY-MM-DD` - Next free `{YY}-{MM}-{NNNN}` DV number
- `GET /voucher/rfa/all` - RFA picker options: `fims.budget_allocation` rows where `allocation_type = 'RFA'` (that table has no CRUD backend of its own — this is a read-only lookup added for the DV form). Several DVs can be drawn against the same RFA over time (progress payments, matching the paper General Form No. 1's Box B "Payments / This Request / Total Payments" lines) — each option includes `budget_amount` (ceiling), `paid_amount` (sum of `net_amount` already drawn against it by other non-cancelled/rejected vouchers), and `remaining_balance`. Optional: `fiscal_yr`, `search`, `exclude_dv_id` (editing — excludes this voucher's own amount from `paid_amount`)
- `GET /voucher/rfa/read/{allocation_id}` - One RFA + its `budget_amount`/`paid_amount`/`remaining_balance`. Optional: `exclude_dv_id` when editing an existing DV so its own prior amount isn't double-counted
- `GET /voucher/read/{id}` - One DV with `allowed_transitions`, its deduction lines embedded as `deductions[]`, its ATC withholding lines as `atc_lines[]`, and its Journal Entry Voucher as `jev` (header + `lines[]`, each line with `sl_code`/`sl_name`/`sl_detail_id`/`sl_detail_name`; `jev` is `null` if Accounting hasn't prepared one yet)
- `POST /voucher/add` - Create (`claimant_name` or `vendor_id`, `particulars`, `gross_amount` required). Optional `deductions: [{deduction_type_id, rate?, gross_amount?, deduction_amount?}]` (non-tax deductions e.g. retention — `rate` applied to `gross_amount` when given, otherwise `deduction_amount` is used directly); `net_amount = gross_amount - sum(deduction_amount)` at this point (withholding tax isn't known yet — see the Journal Entry route below). `status` DRAFT (default) or FOR_FMD_REVIEW
- `PUT /voucher/update/{id}` - Replace fields + deductions (full replace, same shape as `POST /voucher/add`); DRAFT / REJECTED only
- `POST /voucher/{id}/status` - `{status, notes?}` — moves the voucher along the four-office pipeline: `DRAFT -> FOR_FMD_REVIEW -> FOR_ACCOUNTING -> FOR_TREASURY -> RELEASED` (Originating -> FMD checks the obligation -> Accounting processes -> Treasury releases payment), or `REJECTED` (notes required) / `CANCELLED` from any non-terminal stage. `403` if the caller's `uac.users.rc_id` (Responsibility Center) doesn't match the office that owns the target stage (sysadmin bypasses) — see `DisbursementVoucherService::authorizeTransition()`
- `GET /voucher/journal-entries` - Paginated JEV register, each row joined with its DV (`dv_no`, `claimant_name`, `dv_status`). Read-only — a JEV is only created/edited from within its DV's Accounting stage. Optional: `page`, `limit`, `fiscal_yr`, `search` (JEV no / DV no / claimant)
- `GET /voucher/atc-codes` - Active BIR Alphanumeric Tax Codes (`fims.atc_codes`) for the ATC line picker
- `GET /voucher/deduction-types` - Active deduction types (`fims.deduction_types`) for the Deductions section's type dropdown
- `GET /voucher/sl-codes?search=` - Subsidiary Ledger code picker (`fims.sl_masters`), for the Accounting Entries grid's SL Code autocomplete
- `GET /voucher/sl-codes/{sl_code}/details` - SL Detail options (`fims.sl_details`) scoped to one SL Code
- `POST /voucher/{id}/journal-entry` - `{atc_lines: [{atc_code, rate, tax_base, chargeability?}], lines: [{account_code, debit, credit, description?, sl_code?, sl_detail_id?}]}` — Accounting saves/edits the DV's Journal Entry Voucher *and* its BIR withholding tax classification together (full replace of both, same shape as PO items). FOR_ACCOUNTING status + Accounting Department (RC `012`) only (sysadmin bypasses). `atc_lines` is optional (0 or more — a DV can withhold under several ATCs at once); each line's `withholding_tax` is server-computed (`tax_base * rate / 100`), never trusted from the client. `lines` must be ≥2, each exactly one of debit/credit, account codes must be `posting_allowed`; `sl_code`/`sl_detail_id` are optional and, if given, must exist and `sl_detail_id` must belong to `sl_code`. Total debits must equal total credits. Recomputes the DV's `net_amount`/`balance_amount` (`gross_amount - total deductions - total withholding_tax`). A balanced JEV is a prerequisite `POST /voucher/{id}/status` checks before allowing `FOR_ACCOUNTING -> FOR_TREASURY`
- `DELETE /voucher/delete/{id}` - DRAFT or CANCELLED only

### chart_of_accounts.php (`ChartOfAccountsService`, table `fims.chart_of_accounts`)
- `GET /chart-of-accounts/all` - All accounts, no pagination (dropdown / parent-account picker). Optional: `search`, `account_type`, `region_code`, `project_code`, `status`
- `GET /chart-of-accounts/regions` - `fims.region` lookup list (Region filter/dropdown)
- `GET /chart-of-accounts/list` - Paginated (`page`, `limit` + same filters as `/all`)
- `GET /chart-of-accounts/read/{id}` - One account (by `account_id`), joined with region/project/parent names
- `POST /chart-of-accounts/add` - Create (`account_code` + `account_title` required, `account_code` unique); `account_id` is server-generated (`ACC00001`, …)
- `PUT /chart-of-accounts/update/{id}` - Update
- `PATCH /chart-of-accounts/{id}/toggle-status` - Flip `status` between `Active` / `Inactive`
- `DELETE /chart-of-accounts/delete/{id}` - Blocked if the account has child accounts or is referenced by `fims.purchase_order_items` (deactivate instead)

### responsibility_center.php (`ResponsibilityCenterService`, table `fims.responsibility_code`)
- `GET /responsibility-center/all` - All RCs, no pagination (dropdown / parent-RC picker). Optional: `search`, `office_type`, `status`
- `GET /responsibility-center/list` - Paginated (`page`, `limit` + same filters as `/all`)
- `GET /responsibility-center/read/{id}` - One RC (by `rc_id`), joined with parent RC code/name
- `POST /responsibility-center/add` - Create (`rc_code` + `rc_name` required, `rc_code` unique); `rc_id` is server-generated (`RC-000`, …)
- `PUT /responsibility-center/update/{id}` - Update
- `PATCH /responsibility-center/{id}/toggle-status` - Flip `status` between `Active` / `Inactive`
- `DELETE /responsibility-center/delete/{id}` - Blocked if the RC has child RCs under it

Both files use `->add($accessMiddleware)->add($jwtMiddleware)`. `$accessMiddleware` is currently a
pass-through stub, so these routes are authenticated but not permission-gated.

## Template Usage

When duplicating this template:
1. Replace `ITBS_STANDARD_APP` with your project namespace
2. Replace `__BASE__` with your project code (e.g., UAC, DMS)
3. Update system references and documentation accordingly

## Adding New Routes

To add new route files:
1. Create new PHP file in this directory
2. Follow the naming convention: `feature-name.php`
3. Include the file in `/app/api.php`
4. Update this README with new endpoints

## Middleware Usage

- Add `->add($jwtMiddleware)` for authentication
- Add `->add($accessMiddleware)` for permission checks
- Chain middleware as needed: `->add($accessMiddleware)->add($jwtMiddleware)` 