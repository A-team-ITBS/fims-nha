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
├── notifications.php   # Notification hub API (uac.notifications) for the bell
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

### voucher.php (`DisbursementVoucherService`, table `fims.disbursement_vouchers`)
One generic voucher shape for any claimant/payment type (vendor payment, payroll, reimbursement, etc.) — `claimant_name`/`claimant_address`/`employee_no_tin` are either snapshotted from a selected `vendor_id`, or typed free text.
- `GET /voucher/all` - All DVs, no pagination. Optional filters: `status`, `fiscal_yr`, `expense_class` (MOOE/PS/CAPITAL_OUTLAY/OTHER — classification tag only, same set `fims.purchase_orders.expense_class` uses), `search`
- `GET /voucher/list` - Paginated (`page`, `limit` + same filters); also returns `summary` (totals / counts for every row matching the filters)
- `GET /voucher/fiscal-years` - Fiscal years for dropdowns
- `GET /voucher/dv-number?dv_date=YYYY-MM-DD` - Next free `{YY}-{MM}-{NNNN}` DV number
- `GET /voucher/rfa/all` - RFA picker options: `fims.budget_allocation` rows where `allocation_type = 'RFA'` (that table has no CRUD backend of its own — this is a read-only lookup added for the DV form). Several DVs can be drawn against the same RFA over time (progress payments, matching the paper General Form No. 1's Box B "Payments / This Request / Total Payments" lines) — each option includes `budget_amount` (ceiling), `paid_amount` (sum of `net_amount` already drawn against it by other non-cancelled/rejected vouchers), and `remaining_balance`. Optional: `fiscal_yr`, `search`, `exclude_dv_id` (editing — excludes this voucher's own amount from `paid_amount`)
- `GET /voucher/rfa/read/{allocation_id}` - One RFA + its `budget_amount`/`paid_amount`/`remaining_balance`. Optional: `exclude_dv_id` when editing an existing DV so its own prior amount isn't double-counted
- `GET /voucher/read/{id}` - One DV with `allowed_transitions`, its deduction lines embedded as `deductions[]`, its ATC withholding lines as `atc_lines[]`, and its Journal Entry Voucher as `jev` (header + `lines[]`, each line with `sl_code`/`sl_name`/`sl_detail_id`/`sl_detail_name`; `jev` is `null` if Accounting hasn't prepared one yet)
- `POST /voucher/add` - Create (`rfa_allocation_id`, `claimant_name` or `vendor_id`, `particulars`, `gross_amount` required). Optional `deductions: [{deduction_type_id, rate?, gross_amount?, deduction_amount?}]` (non-tax deductions e.g. retention — `rate` applied to `gross_amount` when given, otherwise `deduction_amount` is used directly); `net_amount = gross_amount - sum(deduction_amount)` at this point (withholding tax isn't known yet — see the Journal Entry route below). Also optionally accepts `atc_lines`/`lines`, same shape as `POST /voucher/{id}/journal-entry` below — Originating can pre-fill the ATC Code / Accounting Entries sections here if already known, though it's normally Accounting's job during FOR_ACCOUNTING; unlike that route, 0 lines is valid (nothing filled in yet, not an error), but if any are given the usual ≥2-lines/balanced/posting_allowed checks still apply, and net_amount/balance_amount are recomputed the same way. `status` DRAFT (default) or FOR_FMD_REVIEW
- `PUT /voucher/update/{id}` - Replace fields + deductions + optional `atc_lines`/`lines` (full replace, same shape as `POST /voucher/add`); DRAFT / REJECTED only
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

### print.php (`PrintService`) — printed forms (CBA, CAF, RFA, LAA, LAA release request, PO, PR, IAR, LV, JEV, FTLA, FTLA request, DV)
One endpoint for every form; each form is a service registered in `PrintService::DOCS` (`CbaPrintService`, `CafPrintService`, `RfaPrintService`, `LaaPrintService`, `LaaRequestPrintService`, `PurchaseOrderPrintService`, `PurchaseRequestPrintService`, `IarPrintService`, `LvPrintService`, `JevPrintService`, `FtlaPrintService`, `FtlaRequestPrintService`, `DvPrintService`). One form per document across departments — a department's version is an option (`issuing_office: 'REGIONAL'` on `cba`, `caf`, `rfa`). Docs: `system_docs/modules/PRINT-FORMS.md`. Preview modal: `app/pages/readone/print-preview-readone.php` (`prp_open(payload, { type })`).
- `POST /print/{doc}` - Print a form from a payload, `{doc}` = `cba` | `caf` | `rfa` | `laa` | `laareq` | `po` | `pr` | `iar` | `lv` | `jev` | `ftla` | `ftlareq` | `dv`. Body: `{ format: html|pdf|docx, ...payload, letterhead? }` (`letterhead` = `{items, footer_text}`, this print's header — see LetterheadLayoutService). `html` → `{success, html}`; `pdf`/`docx` → `{success, filename, mime, data: base64}` (PDF via Dompdf, DOCX via `node scripts/gen_<form>_docx.js`). Unknown `{doc}` → 404. Payload fields per form (all optional; documented on each form's `model()`):
  - `cba` - Certificate of Budget Allocation (A4): `allocation_number, document_type (CO|COE), project_description, requested_amount, allocated_amount, fiscal_year_label, fund_source_name, program_of_cob, category_name, is_bidding, date, approved_by_name`. Regional (`issuing_office: 'REGIONAL'`): `allocation_number, date, project_description, amount, fiscal_year_label, fund_source_name, signatory_name/_title/_office`
  - `caf` - Certificate as to Availability of Funds (A4, the CBA layout): `caf_number, allocation_number (linked RFA) | rfa_number, caf_amount | amount, purpose, payee_name, payee_title, payee_office_address, fund_source_name, date, approved_by_name`. Regional (`issuing_office: 'REGIONAL'`): `body_variant (entered_into_with|in_favor_of|in_favor_of_petty), caf_number, date, caf_amount, purpose, payee_name, payee_office_address, fund_source_name, allocation_number, ref_type (RFA|LAA), certified_name/_title/_office`
  - `rfa` - Request for Fund Allotment (legal paper): `allocation_number, date, payee_name, requesting_department_type | requesting_department_id | requesting_region_id, form_variant, filled_out_by, rfa_type, particulars, amount, cert_correct_name/_title/_office, project_name, total_estimated_cost, total_funds_available, total_funded_to_date, balance, payments_outstanding, this_request, total_payments, total_contract_cost, adjustment_additive, adjustment_deductive, total_allotment, funding_source{…}, certified_funds_name/_title, recommending_name, approved_name`; `issuing_office: 'REGIONAL'` adds the regional form's labels
  - `po` - Purchase Order (legal paper): `po_id` (or `id`) prints the saved PO (`PurchaseOrderService::fetchById`); payload fields override it or print alone: `po_number, po_date, vendor_name, vendor_address, vendor_tin, mode_of_procurement, delivery_place, delivery_terms, delivery_date_required, payment_terms, items[{item_description, unit_of_measure, quantity_ordered, unit_cost}], total_amount, purpose, requisitioners, references | pr_number, warranty, approving_officer_name/_title, general_manager_name/_title, certified_by_name/_title, prepared_by_initials, funds_available, alobs_no, alobs_amount, alobs_adj`. No letterhead by default (its own title block)
  - `pr` - Purchase Requisition (legal paper): `pr_number, pr_date, purpose, department_name | charge_to, delivery_date, items[{item_description, unit_of_measure, quantity_requested, unit_cost, warehouse_stock?, dept_qty_on_hand?}], total_amount, note, project_description, total_estimate_cost, total_funds_available, funded_to_date, fund_balance, outstanding_payable, request_if_approved, total_payments_project, total_contract_cost, additional_allotment, total_allotment, prior_payment, this_payment, total_payments, balance_to_complete, fund_allotment_no, sig1_name … sig4_name, sig1_role … sig4_role`
  - `laareq` - LAA release request, the regional office's memorandum (letter paper): `date, for_name/_title, thru_name/_title, subject, description, amount, sig_name/_title, laa_number`
  - `iar` - Inspection and Acceptance Report (letter paper, no letterhead by default): `iar_number, supplier_name, po_number, po_date, si_number, si_date, requesting_department, items[{stock_no?, unit_cost, description, quantity}], date_inspected, delivery (complete|partial) | is_complete_delivery, inspector_name/_label, div_manager_name/_label, dept_manager_name/_label`
  - `lv` - Liquidation Voucher (legal paper, its own title box): `lv_number, disbursing_officer, department_region, particulars (text | [{description, amount, sub?}]), gross_amount, amount_in_words, chargeability | fund_source_code + fund_source_name, ca_dv_number, ca_amount, prior_liquidations[{lv_number, amount}], tax_deposit_amount, cash_refund_deposit_amount, refund_or_number, refund_date, refund_amount, obligation{dept_proj, fund_source, budget_ref, budget, obligation_ref, obligation, balance, payments_ref, payments, this_request_ref, this_request, total_payments}, accounting_entries[{obligation?, account_code, debit, credit}], journalized_by_date, indexed_by_date, cert_a/b/c_name/_title, cert_b_amount, jev_no, pc_summary[{type, label, amount}]` (page 2)
  - `jev` - Journal Entry Voucher (A4 landscape, its own title box): `jev_number (blank | TEMP-… → No. blank + temporary reference), jev_date, office, responsibility_center, lines[{account_title, account_code, debit, credit, description?, sl_code?, sl_path?, responsibility_center?}], reference_no, payee_name, particulars, rfa_number, saro_number, prepared_by_name/_title, reviewed_by_name/_title, approved_by_name/_title, approved_by_label, footer_remarks`
  - `ftla` - Fund Transfer Letter of Advice, the letter to the bank (A4, NHA letterhead): `ftla_number, date | transfer_date, to_bank_name, to_bank_branch, subject, from_account_name, from_account_number, to_account_name, to_account_number, amount | transfer_amount, purpose` or `transfers[{from_account_number, from_account_name, to_account_number, to_account_name, amount}]` (body layout follows the accounts: single / several debits → one credit / FROM-TO table), `sig_left_name/_title, sig_right_name/_title, dv_numbers, fund_classification, bank_sig_verified, bank_checked, bank_approved, bank_posted`
  - `ftlareq` - FTLA request, the regional office's memo to Treasury (the `laareq` memo layout): `date, for_name/_title, thru_name/_title, subject, particulars, amount, amount_words, laa_number, laa_department, fiscal_year, rfa_number, rfa_date, bank_account_line, sig_name/_title/_office, ftla_number`
  - `dv` - Disbursement Voucher (legal paper), **PDF only** (`html`/`docx` → 400; pdftk fill of the official form, no letterhead): `voucher_number, route (MAIN|REGIONAL), paying_agency, payee_name, payee_address, payee_tin, particulars, gross_amount | amount, deductions[{description, amount}], total_deductions, accounting_entries[{obligation, fpa, object_code, account_code, debit, credit}], cert_officer1_name/_title, cert_officer2_name/_title, fmd_officer_name/_title, accountant_name/_title, approving_officer, approver_title, paper (plain|preprinted), back_page`
  - `laa` - Letter of Advice of Allotment (A4), CO by default or COE with `document_type: 'COE'` (named Regional Manager addressee, "Dear Engr. …" greeting, "To transfer obligation made to …" wording): `document_type, allocation_number, date, office_name | requesting_department_id | requesting_region_id, project_description, amount, fund_source_name, program_of_cob, fiscal_year_label, to_name/_title/_org/_address, greeting, cert_name/_title/_department, close_name/_title/_department`
- `GET /print/resolve-signatory?role=FMD%20Department%20Manager` - `{success, name}` of the current role holder (only roles in `PrintService::SIGNATORY_ROLES`)

### letterhead.php (`LetterheadLayoutService`, table `fims.letterhead_layout`) — letterhead designer
`{doc}` = document code (`CBA`, `RFA`, `LAA`, `CAF`, `LAAREQ`, `PR`, `FTLA`, `FTLAREQ` — `LetterheadLayoutService::DOCUMENTS`); each keeps its own saved default. The PO, IAR, LV, JEV and DV have no letterhead (their own printed title) — 404 here, and `/print/{doc}` ignores a `letterhead` sent for them.
- `GET /letterhead/{doc}` - What the print preview's letterhead editor starts from: `{layout, saved, can_save, factory, assets}`
- `POST /letterhead/{doc}` - Save as default header: `{layout: {items, footer_text}}`. System Administrator / FMD Department Manager only (403 otherwise)
- `DELETE /letterhead/{doc}` - Restore original (drop the saved default). Same roles

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

### notifications.php (`NotificationService`, tables `uac.notifications` / `uac.notification_reads`)
Implements `_shared/NOTIFICATION-IMPLEMENTATION-PLAN.md`. The caller is always the JWT user (`X-Authorization`).
- `GET /notifications/recent?limit=` - `{data:[{id,type,target,subject,body,link,from_system,created_at,time_ago,read_by_user}], unread_count}`; default 10, clamp 1-200, unread first
- `GET /notifications/count` - `{unread_count}`
- `POST /notifications/mark-read` `{id}` - only rows in the caller's audience; idempotent
- `POST /notifications/mark-all-read` - `{updated}`
- `POST /notifications/send` - superadmin only; `{type, target, subject, body?, link?, from_system?, event_key?}`
- Audience `type` is one of `all | system | user | userlevel | office | usergroup`. Application code sends with
  `NotificationService::toUser() / toUserLevel() / toOffice() / toUserGroup() / toSystem() / toAll()` (never throws; returns the id or null).
- Wired so far: Purchase Order approved / rejected / cancelled -> notifies the creator.

