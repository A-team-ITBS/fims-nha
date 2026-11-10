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
├── chart_of_accounts.php # Chart of Accounts CRUD + status routes
├── responsibility_center.php # Responsibility Center CRUD + status routes
├── budget_allocation.php     # Memo Request (budget allocation) CRUD + lookups
├── budget_category.php       # Budget category CRUD (Infrastructure, Goods and Services, Consultancy)
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

### budget_allocation.php (`BudgetAllocationService`, table `fims.budget_allocation`; page `app/pages/memo-req-page.php`)
Shaped like `crud.php` (`all` / `list` / `read` / `add` / `PATCH update` / `delete`). Soft-deleted via `deleted_at`.
- `GET /budget_allocation/all` - All memo requests, no pagination (Parent picker). Optional: `search`, `fiscal_year`, `status_id`, `allocation_type`, `exclude_id`
- `GET /budget_allocation/list` - Paginated (`page`, `limit` + same filters); also returns `summary` {total, total_pr_amount, total_po_amount} for every row matching the filters. Rows exclude `line_transaction`
- `GET /budget_allocation/read/{id}` - One memo request (all columns, `line_transaction` decoded), plus `file_attachments[]` (its Supporting Documents: `file_meta` {`document_name`, `link`, `view_url`}), joined with status / vendor / requesting RC (`requesting_rc_code`, `requesting_rc_name`, `requesting_rc_office_type`) / district / program / project / parent names
- `POST /budget_allocation/add` - Create (`memo_ref_no` + `fiscal_year` required). Status defaults to `Draft`; `user_id` is the JWT user, never read from the body. So is `requesting_rc_id`: it is that user's own Responsibility Center (`uac.users.rc_id`, a `fims.responsibility_code.rc_id`; left empty for a user without one), set on create only. Returns `allocation_id`
- `PATCH /budget_allocation/update/{id}` - Partial update: only the fields sent change (`''` / `null` clears an optional field). `user_id`, `requesting_rc_id`, `created_at`, `deleted_at` are never writable
- `DELETE /budget_allocation/delete/{id}` - Soft delete; blocked while other memo requests still have it as `parent_id`
- `GET /budget_allocation/departments` - Active `uac.departments` (District picker)
- `GET /budget_allocation/statuses` - `fims.settings_status` rows with `status_for = 'budget_allocation'`

Validation (400 with a message): fiscal year / vendor / status / department / district / program / sub-program / project must exist; the sub-program must belong to the program and the project to the program (and sub-program, when the project has one); `parent_id` can't be the row itself or one of its descendants; amounts are 0 – 9,999,999,999,999.99; `line_transaction` must be a JSON array/object; `multiple_node_id` must be a UUID.

### budget_category.php (`BudgetCategoryService`, table `fims.budget_category`)
Shaped like `crud.php` (`all` / `list` / `read` / `add` / `PATCH update` / `delete`). Soft-deleted via `deleted_at`. Seeded with Infrastructure, Goods and Services, Consultancy (ids 1–3).
- `GET /budget_category/all` - All categories, no pagination (dropdown). Optional: `search` (case-insensitive, partial match on `name`)
- `GET /budget_category/list` - Paginated (`page`, `limit`, max 200; same `search`)
- `GET /budget_category/read/{id}` - One category (404 when missing or deleted)
- `POST /budget_category/add` - Create (`name` required, ≤ 50 chars, unique ignoring case among non-deleted rows). Returns `budget_category_id`
- `PATCH /budget_category/update/{id}` - Rename (`name` is the only writable field; same rules). Stamps `updated_at`
- `DELETE /budget_category/delete/{id}` - Soft delete; the name can be reused afterwards

### budget_req.php (`BudgetReqService`, table `fims.budget_req`; page `app/pages/budget-req-page.php`)
Only the list routes' RC handling is described here. `GET /budget_req/all` (no paging) and `GET /budget_req/list` (`page`, `limit`) return one header per fiscal year + RC, each with `programs[]` > `projects[]` > `line_items[]`, plus its RC (`rc_code`, `rc_name`, `rc_office_type`) and that RC's parent (`rc_parent_id`, `rc_parent_code`, `rc_parent_name`), its total proposed amount (`li_total`) and `submitted_at` (when it was last submitted for review via `PUT /budget_req/submit-for-review/{id}`; `null` until then and for requests submitted before the column existed). Filters: `fiscal_yr`, `program_code`, and:
- `rc_code_from` - only requests of RCs whose `rc_code` sorts at or after this value. The Financial Management Department's list sends `35-074-0000001-000` (the Regional and District offices; the central departments come before it).
- `central=1` - widens the list to every RC, but only for the central review RC (the Financial Management Department, recognised by its rc_code `GlobalService::CENTRAL_REVIEW_RC_CODE` = `35-074-0000000-032` — not by its rc_id, whose format differs between datasets), whose own RC has no children. Any other user keeps the usual scope (their RC and its descendants); the flag alone grants nothing. `GlobalService::resolveBudgetReqRcFilter()` decides this on the server.

- `PUT /budget_req/endorse/{id}` - Budget Processing "Endorse". Body `{ items: [{ budget_line_item_id, type_of_fund_id, component_id, suggested_amount?, program_source_code? }, ...] }`: `type_of_fund_id` and `component_id` are required on every item; `suggested_amount` is the modal's Suggested Amount column (blank = 0.00; the Amount column beside it is the line's read-only `proposed_amount`); `program_source_code` is optional and is a `fims.program.program_code` (a program source IS a program; key absent = unchanged, blank = cleared, unknown program = error), saved to `budget_line_items.program_source_code`. Moves the request to `Endorsed` in one transaction.
- `PUT /budget_req/return/{id}` - Budget Processing / Budget Preview "Return": moves the request to `Returned` and saves the reason in `budget_req.remarks`. Body `{ remarks }` is **required** (non-blank, at most 2000 characters, `BudgetReqService::REMARKS_MAX_LENGTH`); both modals ask for it in a pop-up before sending. A Cancelled, already-Returned or Approved request cannot be returned. The list / read responses carry `remarks` (null until a request has been returned)

The Financial Management Department's users (current user -> `uac.users.rc_id` -> `fims.responsibility_code`, whose `rc_code` is `35-074-0000000-032`) get a different list on the Budget Requirements page, one row per request over every Regional / District office, paged through `GET /budget_req/list` with `central=1` and `rc_code_from`: Submission Ref No., Region / Originating Unit, Date Submitted, Amount, Status (+ the row actions, which act on the whole request). Everyone else keeps the flat one-row-per-Program list.

### fmd_budget_req.php (`BudgetReqService`, table `fims.budget_req`; page `app/pages/budget-prop-page.php`, "Budget Proposal")
- `GET /fmd/budget_req/list` - the Financial Management Department's list: every Regional / District office's request, paged, in the same shape as `GET /budget_req/list` (header + `programs[]` > `projects[]` > `line_items[]`, its RC and parent RC, `li_total` = the amount, `submitted_at`, status). Query: `fiscal_yr`, `program_code`, `page` (default 1), `limit` (default 15, capped at 100).
  The scope is fixed **on the server**: the caller must belong to the central review RC (FMD, `GlobalService::isCentralReviewRc()`, by rc_code) — anyone else, including an unknown user, a user without an RC or an administrator without that RC, gets `403` — and the list is always every office from `GlobalService::CENTRAL_REVIEW_RC_CODE_FROM` on. `central`, `rc_id` and `rc_code_from` are not read. The RC comes from `GlobalService::getUserRcId()`, not `getUserRcScope()`, whose "unknown user = administrator" answer fails open.

### file_upload.php (`FileUploadServices` in `file_uploadServices.php`, tables `fims.ip_filemeta` + `fims.file_attachments`)
The Supporting Documents section of the CBA / Memo Request form. All three routes need the JWT; the uploader (`created_by`) is the verified user, never read from the body. Errors return a generic message (the details go to the log).
- `POST /upload/file` - Multipart, field `file` (max 25MB; pdf, doc, docx, xls, xlsx, jpg, jpeg, png). Streams it to object storage (MinIO / S3, `DO_SPACES_*` in `.env`) under `FIMS/supporting-documents/YYYY/MM/` and returns `link` (the object key), `document_name`, `size` and a one-hour `preview_url`. Says so when storage is not configured
- `POST /upload/multi-upload` - Body `{ file_data: [{ document_name (<= 225 chars), link }, ...] }` (1-50 entries). In one transaction: a row per file in `fims.ip_filemeta`, a row per file in `fims.file_attachments`, all sharing a new `multiple_node_id`, which is returned for the caller to store on its record (`budget_allocation.multiple_node_id`). 400 on validation errors, nothing saved
- `POST /upload/single-upload` - Body `{ document_name, link }`: one `fims.ip_filemeta` row; returns its `file_node_id`

### budget_line_item.php (`BudgetLineItemService`, table `fims.budget_line_items`)
Plus the Budget Request pages' own `all` / `list` / `read` / `add` / `update` / `delete`. Used by the Memo Request form:
- `GET /budget_line_item/approved` - Line items of **Approved** budget requests (`settings_status.status_for = 'budget_req'`, name `Approved`), each with its `type_of_fund` and `account_code` / `account_title`, plus `fiscal_yr`, `program_code`, `sub_program_code`, `project_code`, `proposed_amount`. Optional filters, each narrowing to what is attached to it: `program_code`, `sub_program_code`, `project_code`, `fiscal_yr`, `type_of_fund_id`. A line item reaches its program through `budget_req_project` -> `budget_req_program`, so rows without a program block only appear while no program / sub-program is chosen. Ordered by account code
- `GET /budget_line_item/approved/type_of_fund` - The types of fund (`type_of_fund_id`, `name`) that exist on those approved line items: the Memo Request form's Type of Fund options. Same optional filters except `type_of_fund_id`; a type of fund no approved line item uses is not listed

The `chart_of_accounts.php`, `responsibility_center.php`, `budget_allocation.php`, `budget_category.php` and (for `/budget_line_item/approved`) `budget_line_item.php` route files use `->add($accessMiddleware)->add($jwtMiddleware)`. `$accessMiddleware` is currently a
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