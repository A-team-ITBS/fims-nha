# NHA FIMS — Financial Information Management System

A multi-module web application for the **National Housing Authority (NHA)** that manages financial operations across all accounting, financial management, procurement, treasury, and regional office functions.

> Also referred to internally as **AFIMS** (Accounting and Financial Information Management System).

---

## System Overview

FIMS is organized into independently deployable modules that share a common authentication layer (User Access Control), a shared component library, and a single PostgreSQL database instance with per-department schemas.

```
FIMS
├── User Access Control               — Auth, roles, permissions, audit
├── Financial Management Dept         — Budget, fund tracking, procurement, obligations
├── General Services Department       — Small Value Procurement: PR → PO → DV workflows
├── Accounting - Disbursement Control — DV processing, payroll, deductions
├── Accounting - Branch & Property    — Property & Inventory Section (PIS) + Project Cost & Departmental Section (PCDS)
├── Accounting - General Division     — JEV approval, bookkeeping, financial statements
│   ├── BABR                          — Bank Account & Bookkeeping Records Section
│   └── FRA                           — Financial Reporting & Analysis Section
├── Treasury Department               — Checks, cash position, FTLA, bank accounts, bonds, fund sweeping
├── Regional Office                   — Full regional accounting cycle (Region & District offices)
└── LIBIS Integration                 — API bridge to the external Land Information & Billing System
```

> **Legacy folders** — two top-level folders predate the current structure and should not be extended:
> - `ACCOUNTING/` pre-dates the per-section split into `ACCOUNTING-DCD/`, `-BAPD/`, `-GAD/` and is kept only for historical reference.
> - `ACCOUNTING-PCDS/` was the original standalone Project Cost & Departmental Section module. It has been superseded by `ACCOUNTING-BAPD/app/pages/ACC-PCDS/`, which is where active development now happens — `ACCOUNTING-PCDS/` is still routed in `nginx.conf` but should be treated as frozen/legacy. See `ACCOUNTING-PCDS-MODULES-FEATURES.md` for what it used to cover (now stale, several pages have since been renamed/merged under BAPD).

---

## Modules

### User Access Control
**Folder:** `UAC/`
**DB Schema:** `uac`

The central authentication and authorization hub used by all modules. Every module authenticates through UAC.

| Responsibility | Details |
|---|---|
| User accounts | Login, sessions, JWT issuance, password management |
| Role management | User levels, department assignments, office assignments |
| Permission matrix | Table-level CRUD permissions per role per module |
| Audit trail | All login/logout and data change events |
| Notifications | System-wide notification dispatch (MQTT, email) |
| Workflow engine | Shared approval workflow manager (`_shared/workflow-manager/`) |
| AI validation | AI-assisted data validation API |

**Pages** (`UAC/app/pages/`):

| Page | Description |
|---|---|
| `landing-page.php` | Public landing / login entry |
| `main-dashboard.php` | UAC administrator dashboard |
| `user-access.php` | User account & role management |
| `user-browser.php` | User directory and lookup |
| `department-management.php` | Department master data |
| `position-management.php` | Position/job title master data |
| `header-management.php` | Document/report header configuration |
| `system-management.php` | System/module registry administration |
| `signatory-management.php` | Signatory assignment for approvals & printed forms |
| `projects-programs-management.php` | NHA program/project master data |
| `security-management.php` | Permission matrix & security policies |
| `workflow-manager.php` | Workflow definition manager |
| `document-type-manager.php` | Document type catalogue |
| `document-type-editor.php` | Document type editor |
| `editor-page.php` | Generic embedded ONLYOFFICE editor |
| `file-manager.php` | File browser & uploads |
| `chat-demo.php` | MQTT chat widget demo |
| `event-playground.php` | Real-time event tester |
| `bullmq-manager.php` | Background job queue inspector |
| `id-generation-management.php` | Document/voucher ID generator config |
| `psgc-map.php` | Philippine Standard Geographic Code map |
| `api-integration-test.php` | Ad-hoc API integration test harness |

---

### Financial Management Dept
**Folder:** `FMD/`
**DB Schema:** `fmd`

Manages fund tracking, budget execution, procurement, and financial planning for the entire NHA.

**Top-level pages:**

| Page | Description |
|---|---|
| `main-dashboard.php` | FMD home dashboard |
| `finance-dashboard.php` | Finance overview |
| `accounting-dashboard.php` | Accounting overview (FMD view) |
| `treasury-dashboard.php` | Treasury overview (FMD view) |
| `account-management.php` | Account master data |
| `budget-registry.php` | Budget registry monitoring |
| `budget-version-form.php` | Budget version creation/edit |
| `caf-form.php` | Certificate of Availability of Funds form |
| `cba-form.php` | Corporate Budget Allocation form |
| `cob-form.php` | Certificate of Budget (COB) form |
| `cross-access-form.php` | Cross-access of cash/fund form |
| `carry-over-form.php` | Fund balance carry-over form |
| `fund-transfer-form.php` | Fund transfer form |
| `realignment-form.php` | Budget realignment form |
| `saro-processing-form.php` | SARO processing form |
| `trust-fund-form.php` | Trust fund form |
| `pr-form.php`, `po-form.php` | Purchase Request / Purchase Order forms (FMD side) |
| `obligation-entry.php`, `obligation-form.php` | Obligation recording |
| `disbursement-entry.php` | Disbursement entry |
| `journal-entry.php` | Journal entry |
| `reports.php` | FMD reports |
| `custom-template.php` | Custom report template |
| `dev.php` | Developer scratch page |

**Sections:**

| Section | Folder | Pages |
|---|---|---|
| FPC Division | `FPC-Division/` | Allocation read-one/variance, budget allocation dashboard, CAF allocation read-one/variance, CO-PO/PR pages, ledger pages, account-payable, utilization read-one/status |
| FP-COE | `FP-COE/` | COE dashboard, accounts-payable, allocation variance, budget allocation dashboard, budget utilization report, ledger page, PR/PO pages |
| FP-FS | `FP-FS/` | Fund sourcing dashboard & determination, SARO processing, supplemental funding, trust fund, cross-access, amortization |
| FP-MS | `FP-MS/` | Financial monitoring dashboard, budget utilization monitoring, budget version page, COB preparation, FMR, GAA status, SARO/supplemental/trust-fund monitoring, status report collation |
| Voucher | `Voucher/` | `voucher-entry.php`, `voucher-dashboard.php`, `liquidation-entry.php`, `obligation.php`, `obligation-readone.php`, `vouhcer-disbursement-readone.php` |
| Shared | `Shared/` | `import-projects.php`, `import-cob.php`, `import-fund-tracking.php`, `import-trust-fund.php` |

---

### General Services Department
**Folder:** `GSD/`
**DB Schema:** `fmd` (`purchase_requests`, `purchase_orders`), `accounting` (`vouchers`, voucher_type `DV`) — GSD has no schema of its own; it's a UI/workflow layer over FMD and Accounting data.

Runs the **Small Value Procurement** approval chain per NHA-SOP-GSD-014/015/016: Purchase Request → Purchase Order approval → approved-PO payment/delivery → Disbursement Voucher. Backed by the shared workflow engine, with a dedicated inventory-issuance dashboard for one specific user level.

**Pages** (`GSD/app/pages/`):

| Page | Description |
|---|---|
| `main-dashboard.php` | GSD landing dashboard (switches to the inventory view for user level 10002) |
| `main-dashboard-inventory.php` | Inventory Issuance dashboard variant |
| `pr-page.php`, `pr-form.php`, `pr-readone.php` | Purchase Request list, create/edit, and detail view |
| `po-page.php`, `po-form.php`, `po-readone.php` | Purchase Order list, create/edit, and detail/payment-workflow view |
| `dv-page.php`, `dv-form.php`, `dv-readone.php` | Disbursement Voucher processing list, PO-driven creation, and detail view |

---

### Accounting - Disbursement Control
**Folder:** `ACCOUNTING-DCD/`
**DB Schema:** `accounting`

Handles all disbursement processing, payroll, statutory deductions, and compliance reporting.

**Pages** (`app/pages/ACC-DCD/`):

| Page | Description |
|---|---|
| `disbursement-dashboard.php` | Overview of disbursement activity |
| `dv-processing.php` | Disbursement Voucher (DV) encoding and routing |
| `contractor-dvs.php` | DV processing for contractor payments |
| `voucher-entry.php` | Voucher entry |
| `voucher-dashboard.php` | Voucher status dashboard |
| `voucher-deduction-management.php` | Per-voucher deduction management |
| `voucher-remittance.php` | Per-voucher remittance recording |
| `mooe_voucher.php` | MOOE voucher processing |
| `capital-outlay.php` | Capital Outlay voucher processing |
| `jev-create.php`, `jev-listing.php`, `jev-view.php` | JEV creation, listing, and detail view (DCD-side) |
| `payroll-management.php` | Monthly payroll processing |
| `payroll-employees.php` | Employee master data for payroll |
| `payroll-dashboard.php` | Payroll status and summary |
| `tax-annualization.php` | Year-end tax annualization |
| `income-tax-withheld.php` | Income tax withheld tracking |
| `deduction-management.php` | BIR, GSIS, SSS, PHIC, Pag-IBIG deductions |
| `remittance-management.php` | Remittance tracking per deduction type |
| `cash-advances-liquidation.php` | Cash advance issuance and liquidation |
| `cash-replacement.php` | Petty cash replacement processing |
| `petty-cash.php` | Petty cash fund management |
| `accrual-expense.php` | Expense accrual entries |
| `ftla-processing.php` | Fund Transfer Letter Authorization processing |
| `check-db-journal.php` | Check disbursement journal |
| `payable-registry.php` | Accounts payable tracking |
| `supplier-contractor.php` | Supplier and contractor master data |
| `chart-accounts.php` | Chart of accounts reference |
| `gcg-dbm-report.php` | GCG/DBM compliance reporting |
| `reports-generation.php` | FAR and other statutory reports |

---

### Accounting - Branch & Property
**Folder:** `ACCOUNTING-BAPD/`
**DB Schema:** `accounting`, `shared` (plus read-only reference into `fmd` for GSD-sourced POs)

Covers two sections: the **Property & Inventory Section (PIS)** — fixed assets, investment property, and inventory — and the **Project Cost & Departmental Section (PCDS)** — program/project cost ledgers and SARO/RFA-LAA monitoring (migrated here from the legacy standalone `ACCOUNTING-PCDS/` module).

**Property & Inventory Section** (`app/pages/ACC-PIS/`):

| Page | Description |
|---|---|
| `fixed-assets-page.php` | Inventory of fixed assets (PPE), with lifecycle status |
| `asset-category-page.php` | Asset category master data |
| `asset-depreciation-page.php` | Depreciation schedules and entries |
| `asset-disposal-transfer-page.php` | Asset disposal/transfer processing |
| `construction-in-progress-page.php` | Construction-in-progress tracking |
| `investment-property-page.php` | Investment property register |
| `inventory-issuance.php` | Supplies/inventory issuance |
| `property-records-page.php` | Detailed property record cards |
| `property-lifecycle-status-page.php` | Property lifecycle status reference |
| `purchase-order-page.php` | Read-only view of GSD-sourced Purchase Orders |
| `report-generation-page.php` | PIS report generation |

**Top-level pages** (`app/pages/`) — receivables, merchandise/supplies inventory, and their JEV postings:

| Page | Description |
|---|---|
| `main-dashboard.php` | BAPD landing dashboard |
| `inventory_dashboard.php` / `main-dashboard-inventory.php` | Inventory overview dashboard(s) |
| `receivable-dashboard.php` / `receivable-management.php` | Receivables overview and management |
| `merchandise-inventory.php` / `supplies-materials-inventory.php` | Merchandise / supplies & materials inventory |
| `JournalVoucherView.php` | Generic JEV detail view for BAPD-originated entries |
| `ReceivableJev.php`, `ReceivableAdjustmentMonthlyJev.php`, `ReceivableCancellationMonthlyJev.php`, `ReceivableCondRestMonthlyJev.php`, `ReceivableCrdjMonthlyJev.php`, `ReceivableDepositoryMonthlyJev.php`, `ReceivableRebatesMonthlyJev.php`, `ReceivableSregMonthlyJev.php` | JEV postings for receivables (general, adjustment, cancellation, condonation/restructuring, CRDJ-sourced, depository, rebates, subsidiary registry) |
| `SalesAdjustmentJev.php` | JEV for sales adjustments |
| `MerchandiseJev.php`, `SuppliesMonthlyJev.php`, `WipToSaleableJev.php` | JEVs for merchandise, supplies consumption, and WIP-to-saleable reclassification |
| `AssetDisposalTransferJev.php` | JEV for asset disposal/transfer postings |

**Project Cost & Departmental Section** (`app/pages/ACC-PCDS/`):

| Page | Description |
|---|---|
| `ProjectCostingDashboard.php` | Central overview of project budgets, cost utilization, and RFA/LAA charging |
| `FimsProjectsPage.php` | Project master data |
| `LedgerPerProjectPage.php` | Per-project cost ledger |
| `LedgerForRFA-LAA-Page.php` | RFA/LAA-level ledger |
| `RFA-LAA-MonitoringPage.php` | Regional Fund Allocation monitoring |
| `SARO-GAAAO-MonitoringPage.php` | SARO and GAAAO fund monitoring |
| `SpecialProgramsPage.php` / `SpecialProgramCategoriesPage.php` | Special program cost tracking (calamity, mandamus, emergency) and category setup |
| `RevenueTransactionPage.php` / `ExpenseTransactionPage.php` | Revenue and expense transaction recording |
| `UnpaidAccomplishmentsPage.php` | Tracking unpaid contractor accomplishments |
| `PricingCoordinationPage.php` | Housing pricing coordination with FMD |
| `DepartmentalPerformancePage.php` | Departmental performance tracking |
| `FinancialAccountabilityReportPage.php` | Financial Accountability Report (FAR) output |
| `AdminFeeDashboardPage.php` / `AdminFeeProcessingPage.php` | Administration fee dashboard and collections processing |

**BABR re-export** (`app/pages/ACC-GAD/BABR/`) — a real (not re-exposed) bookkeeping implementation scoped to BAPD: `jev-create.php`, `jev-listing.php`, `jev-view.php`, `general-ledger.php`, `subsidiary-ledger.php`, `journals.php`.

---

### Accounting - General Division
**Folder:** `ACCOUNTING-GAD/`
**DB Schema:** `accounting`

Maintains the general ledger, journal entries, and financial statements. Divided into two sections:

**BABR — Bank Account & Bookkeeping Records Section** (`app/pages/ACC-GAD/BABR/`)

| Page | Description |
|---|---|
| `bookkeeping-dashboard.php` | Bookkeeping overview |
| `chart-of-accounts.php` | Chart of accounts editor |
| `jev-listing.php` | Journal Entry Voucher (JEV) list with workflow |
| `jev-create.php` | Create new JEV |
| `jev-view.php` | View JEV details and audit trail |
| `bank-jev-processing.php` | Bank-side JEV processing |
| `bank-jev-create.php` | Create bank JEV |
| `bank-jev-templates.php` | Bank JEV templates |
| `bank-reconciliation.php` | Bank reconciliation worksheet |
| `adjusting-entries.php` | Adjusting entries register |
| `adj-entry-create.php` | Create adjusting entry |
| `correction-entry.php` | Correction entries |
| `journals.php` | General journal listing |
| `general-ledger.php` | General ledger view |
| `subsidiary-ledger.php` | Subsidiary ledger view |
| `ledger-accuracy.php` | Ledger accuracy / tie-out checks |
| `account-monitoring.php` | Account-level monitoring |

**FRA — Financial Reporting & Analysis Section** (`app/pages/ACC-GAD/FRA/`)

| Page | Description |
|---|---|
| `financial-reporting-dashboard.php` | FRA dashboard |
| `financial-statements.php` | Financial statements generator |
| `trial-balance.php` | Trial balance |
| `far-reports.php` | FAR (Financial Accountability Report) outputs |
| `regulatory-reports.php` | Statutory / regulatory reports |
| `fund-utilization.php` | Fund utilization analysis |
| `financial-data-analysis.php` | Ad-hoc financial data analysis |
| `financial-operations.php` | Operations-side financial views |
| `performance-scorecard.php` | Departmental performance scorecard |
| `closing-jev-create.php` | Period-closing JEV creation |
| `month-end-close.php` | Month-end close processing |
| `prior-period-adjustments.php` | Prior-period adjustment entries |
| `coa-schedule-disclosures.php` | Chart-of-accounts schedule disclosures |
| `scf-line-config.php` | Statement of Cash Flows line configuration |

**JEV Workflow** (state machine in the `workflow` schema):

```
DRAFT → FOR_REVIEW → FOR_APPROVAL → APPROVED → POSTED
                  ↘ REJECTED → DRAFT
```

The actual transitions are driven by `_shared/workflow-manager/` and the `workflow_instances` table; the labels above match the JEV configuration. Note: JEV's terminal status is `POSTED`, not `PAID` — cash release is a separate Disbursement Voucher linked via `related_jev_id`.

**Key API Endpoints** (`/ACCOUNTING-GAD/app/api/` — 30+ files):

| File | Purpose |
|---|---|
| `jev_listing.php` | JEV list, stats, view, submit, workflow actions, batch post |
| `chart_of_accounts.php` | Chart of accounts CRUD |
| `bank_jev.php`, `bank_reconciliation.php` | Bank JEV & reconciliation |
| `adjusting_entries.php` | Adjusting entries CRUD |
| `journals.php`, `general_ledger.php`, `subsidiary_ledger.php` | Ledger queries |
| `trial_balance.php`, `financial_statements.php` | Reporting |
| `far_reports.php`, `regulatory_reports.php` | Statutory reports |
| `fund_utilization.php`, `performance_scorecard.php` | Analytics |
| `bookkeeping_dashboard.php`, `account_monitoring.php` | Dashboards |
| `cash_advances.php`, `accrual.php`, `petty-cash.php`, `payable_registry.php` | Disbursement-side helpers |
| `Payroll.php`, `DeductionManagement.php`, `Remittance.php`, `VendorManagement.php` | Cross-cut services |

> Each API file has a matching service class in `app/services/` (e.g. `JevListingService`, `JevWorkflowService`, `ChartOfAccountsService`, `FinancialStatementsService`).

---

### Treasury Department
**Folder:** `TREASURY/`
**DB Schema:** `treasury`

Manages cash position, check lifecycle, fund transfers, bank accounts, bonds, fund sweeping, and remittances. The largest module in the system.

| Category | Pages | Description |
|---|---|---|
| Dashboards | `main-dashboard.php`, `finance-dashboard.php`, `accounting-dashboard.php`, `treasury-dashboard.php` | Overview dashboards |
| Cash Position | `cashpositiondashboard.php`, `cashpositionupdates.php`, `cashposreports.php` | Daily cash position by fund source |
| Cash Receiving | `cashreceivingdashboard.php`, `dailycashreceiving.php` | Daily cash collection recording |
| Cash Flow | `cashflowgenfund.php` | General fund cash flow monitoring |
| Cash Reversion | `cashreversion.php`, `crossaccessofcash.php`, `crossaccessandreversiondashboard.php`, `crossaccessreversionreports.php` | Cross-access and reversion of cash |
| Checks | `check-preparation.php`, `check-registry.php`, `check-inventory-raf.php`, `check-released-to-claimants.php`, `check-released-regions.php`, `check-cancellation-replacements.php`, `check_disbursement_ftla_dashboard.php` | Check preparation, registry, release, cancellation, and disbursement/FTLA overview |
| FTLA | `ftla-preparation.php`, `ftlafundtransfer.php`, `ftlasettlement.php` | Fund Transfer Letter Authorization preparation, transfer, and settlement |
| Fund | `fundavailverification.php` | Fund availability verification |
| Fund Sweeping | `fundsweepingdashboard.php`, `fundsweepingprocessing.php`, `fundsweepingschedule.php` | Automated fund sweeping |
| Bonds | `aobondregister.php`, `aofidelitybondmonitoring.php`, `fidelitybondcoverage.php`, `fidelitybonddashboard.php` | Accountable-officer bond register and fidelity bond monitoring |
| Bank Accounts | `bankaccmanagement.php`, `bankaccountmasterfile.php`, `bankaccountsdashboard.php`, `bank-reconciliation.php` | Bank account management, master file, and reconciliation |
| Accounts | `account-management.php` | Treasury account management |
| Obligations | `obligation-entry.php` | Obligation recording |
| Disbursements | `disbursement-entry.php`, `disbursementreports.php`, `disbursementsummaries.php` | Disbursement tracking |
| Remittances | `remittances.php` | Remittance monitoring |
| Subsidy & Trust Funds | `subsidyfunddashboard.php`, `subsidyfundreporting.php`, `ngsubsidycashbalances.php`, `trustfunddashboard.php`, `trustfundmultimoa.php`, `trustfundstatusreporting.php`, `moafunddownloads.php` | NG subsidy and trust fund monitoring/reporting |
| SARO/NCA | `saroncamonitoring.php` | SARO/NCA monitoring |
| Investments | `investmentmgmt.php`, `interests.php` | Investment and interest management |
| Invoice Inventory | `invoiceinventory.php` | OR/receipt-booklet invoice inventory |
| Office Orders | `approvedofficeorders.php` | Approved office orders register |
| Budget | `budget-registry.php` | Budget registry |
| Journal | `journal-entry.php` | Journal entries |
| Reports | `reports.php`, `otherreports.php`, `systemgeneratedreports.php` | Treasury reports |

---

### Regional Office
**Folder:** `REGIONAL/`
**DB Schema:** `regional` (reads `uac`, `shared`, `fmd`, `accounting` for reference data)

Runs the full regional accounting cycle at both the **Region** level (bank, cashbook, GL/SL, JEV, payroll, PR/PO, liquidation, remittances, signatories) and the **District** level (supplier-contractor master list, fund management, DV for suppliers/contractors, liquidation & reimbursement, petty cash). Pages are organized by function and grouped into shared form/readone/tab fragments.

| Subfolder | Contents |
|---|---|
| `Region/` | Full regional-office accounting pages: `accountable-officer-page.php`, `bank-page.php`, `bur-page.php`, `cashbook-registry.php`, `cdr-page.php`, check inventory/preparation/registry, `cibr-page.php`, `ckdj-page.php`, `coa-page.php`, `fb-page.php`, `gl-page.php`, `jev-page.php`, `jev-remittances.php`, liquidation (COE & fund transfer), payroll (deduction codes, DV preparation, payroll page), `po-page.php`, `pr-page.php`, `remittance-schedule.php`, `signatory-management.php`, `sl-page.php`, `sol-page.php` |
| `District/` | SOP-numbered district workflows: **1.1** Supplier-Contractor Master List, **1.2** Fund Management (CA release, fidelity bond, dashboard), **1.3** Disbursement Voucher for Suppliers-Contractors, **1.4** Liquidation and Reimbursement Voucher (incl. cash book posting), **1.5** Petty Cash Fund Processing & SL reports |
| `form/` | Create/edit forms matching each Region-level page (`dv-form.php`, `jev-form.php`, `po-form.php`, `pr-form.php`, `ftla-form.php`, `payroll-dv-preparation-form.php`, etc.) |
| `readone/` | Detail/view pages matching each Region-level record (`dv-readone.php`, `jev-readone.php`, `po-readone.php`, `payroll-readone.php`, etc.) |
| `shared/`, `tabs/` | Reusable fragments (e.g. BIR 2307 modal, NHA remarks modal, payroll contribution/imported-records tabs) |
| `BudgetVarianceTable.php`, `CafVariance.php`, `CbaVariance.php`, `LaaVariance.php`, `RfaVariance.php` | Budget/CAF/CBA/LAA/RFA variance monitoring |
| `CashAdvance.php`, `ca-page.php`, `rv-page.php`, `dv-ca.php`, `dv-deduction.php`, `dv-payroll.php`, `dv-replenishment.php`, `dv-rv.php`, `dv-vendor.php` | Cash advance and DV variants by payee/purpose |
| `FtlaPrepation.php`, `financial-statement.php`, `import-projects.php`, `disbursement-dashboard.php`, `main-dashboard.php` | FTLA preparation, financial statement, project import, and dashboards |

---

### LIBIS Integration
**Folder:** `LIBIS/`
**Type:** API bridge, not a full PHPMaker module (no `pages/`, `models/`, `controllers/`, or `composer.json` — standalone endpoints under `/LIBIS/api/v1/`)

Lets the external **Land Information & Billing System (LIBIS)** read reference data from FIMS and write Treasury invoice-inventory registrations back into FIMS. FIMS is the system of record for the NGAS Chart of Accounts and the invoice/OR-booklet registry.

| Capability | Direction | Backing table/schema |
|---|---|---|
| Chart of Accounts + Subsidiary Ledger | LIBIS reads | `shared`/`accounting` chart of accounts |
| Bank listing | LIBIS reads | `shared.bank_accounts` |
| Invoice-series registration, booklet deliveries, series-status changes | LIBIS writes | Treasury invoice inventory |

Auth: LIBIS logs in once via a service-account credential and reuses an HS512 JWT (10-minute default expiry) on every call. See `LIBIS/README.md` for the full endpoint list, request/response conventions, and how to add a new endpoint.

---

## Shared Infrastructure

### `_shared/` — Cross-Module Utilities

Used by all modules. Never modify directly without coordination; changes here affect the entire system.

| Component | Path | Purpose |
|---|---|---|
| Authentication | `auth.php` | JWT validation, session management |
| Permissions | `PagePermission.php` | Role-based page access enforcement |
| Notifications | `notification.php`, `NOTIFICATION_README.md` | Dispatch to MQTT, email, in-app |
| Workflow Engine | `workflow-manager/` | Approval workflow state machine (with its own README + 5 guides) |
| File Manager | `file-manager/` | Upload/download with S3 integration |
| Document System | `document/` | Categories, ONLYOFFICE editor integration, type management |
| Chat Widget | `chat-widget/` | Real-time MQTT chat |
| Job Queue | `bullmq-manager/` | Background job processing |
| API Utilities | `api/` | Shared Slim route helpers, `api-client.js` |
| Templates | `template/` | Shared spreadsheet templates (e.g. payroll) |
| Footer / System | `foot.php`, `system.php` | Shared layout fragments |
| MQTT Clients | `mqtt-chat-client.js`, `mqtt-notification.js` | Browser-side MQTT |
| Swagger | `Swaggerize.php` | Auto-generated API docs |
| FMD shared | `FMD/` | Cross-section helpers used by FMD |

### `__BASE__/` — Module Template

Every module was bootstrapped from `__BASE__` using `create_project_wizard.js`.

```bash
node create_project_wizard.js
# Prompts for module code, namespace, and database name
# Copies and configures __BASE__ into a new module directory
```

The wizard rewrites placeholders (`__BASE__` → project code, `ITBS_STANDARD_APP` → namespace, `standard_db_uac` → DB name) across `.php`, `.md`, `.pmp`, `.sql`, `.js`, and `.json` files.

---

## Technology Stack

| Layer | Technology |
|---|---|
| Language | PHP 8.1+ |
| Web Framework | Slim 4 (REST APIs) |
| ORM / DB layer | Doctrine ORM 2.18, Doctrine DBAL 3.6 |
| Auth | Firebase JWT (HS512) |
| Code Generator | PHPMaker 2024 |
| Frontend | Bootstrap 5, jQuery 3.6, AdminLTE 3, DataTables, Chart.js, SweetAlert2 |
| Database | PostgreSQL 14+ (multi-schema) |
| Real-time | MQTT via EMQX |
| Job Queue | BullMQ (Redis) |
| File Storage | DigitalOcean Spaces (S3-compatible) |
| Document Editing | ONLYOFFICE |
| Document Generation | PDFMake, PHPSpreadsheet, docx |
| Email | PHPMailer |
| Logging | Monolog |
| Containerization | Docker + Docker Compose |
| Reverse Proxy | Nginx (`nginx.conf`) |
| CI/CD | GitHub Actions (`.github/workflows/deploy.yml`) |
| API Docs | Swagger UI via `swagger-ui.php` + `Swaggerize.php` |

---

## Database Schemas

| Schema | Module | Contents |
|---|---|---|
| `uac` | User Access Control | Users, departments, roles, permissions, audit logs, notifications |
| `shared` | All | Chart of accounts, fund sources, programs, projects, vendors, ATC codes, RCA, document checklist |
| `fmd` | Financial Management Dept, General Services Dept | Budget versions, allocations, obligations, PRs, POs, fund tracking, CAF |
| `accounting` | Disbursement Control, Branch & Property, General Division | Vouchers, JEVs, ledger, payroll, assets, receivables, deductions, subsidiary ledger masters |
| `treasury` | Treasury Department | Checks, cash position, FTLA, bank accounts, cash advances, collections, bonds |
| `regional` | Regional Office | Region/District budget requests, cash advances, FTLA, check disbursements |
| `workflow` | All | Approval workflow definitions, instances, state transitions |
| `public` | All | ID sequences, shared lookup tables |

General Services Department (`GSD/`) and LIBIS Integration (`LIBIS/`) have no schema of their own — GSD writes into `fmd`/`accounting`, and LIBIS reads/writes existing `shared`/`accounting`/`treasury` tables through its API bridge.

Cross-schema queries work because all schemas live in the same PostgreSQL instance and each module's connection has the necessary `search_path`.

---

## Workflow Categories

The approval workflow engine uses these category values — one per module. General Services Department workflows (PR/PO/DV Small Value Procurement) run under the `Financial Management Dept` category rather than their own, since `GSD` is not in the `workflow_workflows.category` CHECK list.

| Category | Module |
|---|---|
| `Accounting - Disbursement Control` | Accounting - Disbursement Control |
| `Accounting - Branch & Property` | Accounting - Branch & Property |
| `Accounting - Project Cost Departmental` | Accounting - Branch & Property (PCDS section) |
| `Accounting - General Division` | Accounting - General Division |
| `Financial Management Dept` | Financial Management Dept, General Services Department |
| `Treasury Department` | Treasury Department |
| `Regional Office` | Regional Office |

---

## SQL Directory

**Folder:** `_assets/sql/` — ~370 `.sql` files organized by **operation type** (not by module) across 8 numbered phase folders. Discovered and executed in natural-sort order by `install_system.js` at the repo root. Full details, module-subfolder numbering, and naming conventions are documented in `_assets/sql/README.md` — read that file before adding a new script.

```
_assets/sql/
├── 00 - public/                          Phase 1: extensions and schemas
├── 01 - schema_table_structure/          Phase 2: CREATE TABLE
├── 02 - schema_view_structure/           Phase 3: CREATE VIEW
├── 03 - schema_function_structure/       Phase 4: CREATE FUNCTION
├── 04 - schema_alter_table_structure/    Phase 5: ALTER TABLE (+ ALTER VIEW) — largest phase
├── 05 - schema_sample_data_structure/    Phase 6: INSERT seed/sample data
├── 06 - schema_truncate_structure/       Phase 7: TRUNCATE helpers (dev only, commented out)
├── 07 - CROSS-MODULE-DEMO/                NOT run by the installer — manual demo seed
└── 07 - schema_maintenance_structure/    Runs LAST, always
```

Each phase (except `04`) is divided into module subfolders following the same numbering: `1.1 - shared`, `01 - UAC`, `02 - FMD`, `03 - ACCOUNTING`, `04 - TREASURY`, `05 - WORKFLOW`, `06 - REGIONAL` / `06 - INTEGRATION`, `07 - DISTRICT`. Phase `04` renumbers these — always copy whatever numbering the phase you're editing already uses.

```bash
npm run install-system   # SQL + composer install, across all modules
npm run sql              # SQL only
npm run sql-consolidated # SQL only, from the consolidated build (see below)
npm run sql-reset        # maintenance files only (resets sequences, keeps data)
```

A derived, single-file-per-concern build also exists at `_assets/sql-consolidated/` (by phase and by schema). It's rebuilt from the source tree above with `node consolidate_sql.js` — treat it as a generated artifact, never hand-edit it.

Each module also has its own migration scripts under `MODULE/_source/sql/`.

### Cross-Module Demo Data

`_assets/sql/07 - CROSS-MODULE-DEMO/` holds a manual demo dataset whose records are **linked across every system** (FMD → Regional → Accounting → Treasury) so the same money trail appears on all modules' screens and reports, and all five documented process flows (CBA/RFA/LAA, DV, PR/PO, Liquidation, JEV→reports) are backed by data. It is **not** run by `install_system.js`. Run it on a dev/demo DB *after* the base install:

```bash
npm run demo-data     # load the connected demo data + all-5-flows coverage (non-destructive, idempotent)
npm run demo-verify   # read-only proof the data is linked across all systems (cross-schema JOINs)
npm run demo-reset    # DESTRUCTIVE: truncate all transactions in the 4 schemas, then reload demo + verify
```

- `demo-data` only refreshes its own `*DEMO-`-tagged rows — it never truncates. Safe to re-run.
- `demo-verify` runs `verify_connections.sql` (VERIFY 1–4). A fully-filled joined row = that chain is connected end-to-end; VERIFY 4 confirms all five flows have data.
- `demo-reset` **permanently deletes all transaction data** (regional/fmd/accounting/treasury; keeps reference/config) — only for a throwaway demo DB. Back up first (`pg_dump`).
- DB connection comes from env (defaults to the local dev DB); `psql` is auto-resolved (`PSQL_PATH` → PATH → Windows autodetect) by `run_demo_data.js`.

Files: `00 - all_connected_demo_data.sql` (bundle of the connected chains), `02 - flow_coverage_extend_sample.sql` (all-5-flows gap filler, tag `FLOWDEMO-`), `verify_connections.sql` (proof), `01 - RESET_truncate_transactions.sql` (used by `demo-reset`). See also `README-PROCESS-FLOW.md` for the end-to-end walkthrough.

---

## Project Structure (Per Module)

Every PHPMaker-generated module follows the same layout:

```
MODULE-FOLDER/
├── _source/
│   ├── MODULE.pmp           # PHPMaker project file
│   └── sql/                 # Module-specific migrations
├── app/
│   ├── api.php              # Slim API router (entry point)
│   ├── global.php           # Module configuration & DB connections
│   ├── pages/               # Custom UI pages (loaded into PHPMaker views)
│   ├── routes/               # Slim route definitions
│   ├── services/             # Business logic classes
│   ├── api/                  # Endpoint files paired with services
│   ├── lib/                  # Utility helpers (ApiService, NotificationManager)
│   └── css/                  # Module-specific styles
├── models/                  # PHPMaker-generated table models
├── controllers/              # PHPMaker-generated controllers
├── views/                   # PHPMaker-generated views
├── src/                     # Framework config (DI, routing, config)
├── vendor/                  # Composer dependencies
├── index.php                # Module entry point
└── composer.json
```

`LIBIS/` is the exception — it's a thin API bridge (`api/`, `app/routes/`, `app/services/` only, no PHPMaker scaffolding).

When fixing bugs or adding features, you'll usually edit `app/services/`, `app/api/`, or `app/pages/`. The `models/`, `controllers/`, and `views/` folders are mostly regenerated by PHPMaker — avoid hand-editing them unless you know what you're doing.

---

## Setup

### Prerequisites

- PHP 8.1+
- PostgreSQL 14+
- Node.js 18+
- Composer 2+
- Docker (optional, for containerized deployment)

### Local Installation

**1. Copy configuration:**
```bash
cp config.sample.php UAC/src/config.php
# Edit with your DB credentials, JWT secret, EMQX settings, S3 keys
# Repeat per module (FMD, GSD, ACCOUNTING-*, TREASURY, REGIONAL), adjusting the "schema" key
```

**2. Install Composer dependencies per module:**
```bash
cd UAC && composer install
cd ../FMD && composer install
cd ../GSD && composer install
cd ../ACCOUNTING-DCD && composer install
cd ../ACCOUNTING-BAPD && composer install
cd ../ACCOUNTING-GAD && composer install
cd ../TREASURY && composer install
cd ../REGIONAL && composer install
```
`LIBIS/` has no `composer.json` — it needs no separate install step.

**3. Install root Node.js tools, then run SQL + Composer across all modules:**
```bash
npm install
npm run install-system   # see "SQL Directory" above for what this runs
```

### Docker Deployment

```bash
docker-compose up -d
# Access at http://localhost:8080
```

See `README-Docker.md` for full Docker setup.

### Creating a New Module

```bash
node create_project_wizard.js
```

The wizard prompts for a project code, PHP namespace, and database name, then duplicates `__BASE__/` with all placeholders replaced. See `README-installation.md` for how the wizard itself works (it documents the wizard tool, not general system installation).

---

## API Documentation

Each PHPMaker module exposes a Swagger-generated API surface via `Swaggerize.php`. View it at:

```
/swagger-ui.php          # Swagger UI viewer
/api-docs.php            # OpenAPI JSON
```

LIBIS has its own equivalents: `swagger-ui-libis.php` / `api-docs-libis.php`.

See `README-swaggerized.md` for how the Swagger generation works and how to document new endpoints.

---

## Documentation

| Document | Location | Contents |
|---|---|---|
| Architecture | `_assets/NHA-AFIMS-Architecture.md` | System design and component overview |
| Tech Architecture | `_assets/NHA-AFIMS-Architecture-tech-present.md` | Tech-focused presentation variant |
| Slides Guide | `_assets/NHA-AFIMS-Slides-Guide.md` | Presentation/slides walkthrough |
| Security & SDD | `_assets/SSDD-NHA-AFIMS.md` | Software System Design Document |
| System Design | `_assets/System Design/` | Per-department system design (FMD, AD, TD, Other Requirements) |
| SRS — Accounting | `_assets/System Requirements/AD/` | Accounting Division requirements |
| SRS — FMD | `_assets/System Requirements/FMD/` | Financial Management Dept requirements |
| SRS — Treasury | `_assets/System Requirements/TD/` | Treasury Department requirements |
| ERD & Data Dictionary | `_assets/erd/` | Entity-relationship diagrams + Mermaid + Word docs per schema/section |
| AVP Materials | `_assets/avp/` | Audio-visual presentation: feature overview, storyboard, scripts |
| EMQX Realtime DataTable | `_assets/EMQX-REALTIME-DATATABLE.md` | Live-updating DataTables over MQTT |
| File Upload | `_assets/FILE-UPLOAD.md` | Upload pipeline and S3/DigitalOcean Spaces integration |
| Header Management | `_assets/HEADER-MANAGEMENT.md` | Document/report header configuration (UAC `header-management.php`) |
| ID Generation | `_assets/ID-GENERATION.md` | Document/voucher ID generator design |
| JEV Modules | `_assets/JEV-MODULES.md` | Cross-module JEV posting conventions |
| Signatory Management | `_assets/SIGNATORY-MANAGEMENT.md` | Signatory assignment for approvals & printed forms |
| API Guidelines | `_guidelines/api-route-service-guidelines.md` | How to write routes and services |
| Page Guidelines | `_guidelines/page-creation-guidelines.md` | Creating custom pages |
| Permission System | `_guidelines/PagePermission-System-Guide.md` | Role-based access implementation |
| DB Best Practices | `_guidelines/db-best-practices.md` | Database standards |
| PHPMaker Config | `_guidelines/PHPMaker XML Configuration Guide.md` | How module XML configs are wired |
| Sample UAC | `_guidelines/Sample-UAC.md` | Reference UAC setup |
| UI Modernization | `_guidelines/ui-modernization-guide.md` | Frontend refresh conventions |
| UI Rollout Status | `_guidelines/ui-rollout-status.md` | Tracking of the UI modernization rollout per module |
| General Division Module | `ACCOUNTING-GAD/README.md` | JEV system, services, API endpoints |
| LIBIS Integration Module | `LIBIS/README.md` | API bridge endpoints, auth flow, testing, troubleshooting |
| Notifications | `_shared/NOTIFICATION_README.md` | Notification system guide |
| Workflow Engine | `_shared/workflow-manager/README.md` | Approval workflow internals |
| Docker | `README-Docker.md` | Docker setup and deployment |
| Production Deployment | `README-DEPLOY.md` | On-premises Ubuntu server deployment guide |
| Project Wizard | `README-installation.md` | How `create_project_wizard.js` works (not a general install guide — see "Setup" above for that) |
| Swagger | `README-swaggerized.md` | API documentation setup |
| SQL Directory | `_assets/sql/README.md` | Phase folders, module numbering, and `install_system.js` usage |

---

## Where to Start (for new contributors)

1. Read the "Setup" section above to get a local environment running.
2. Skim `_assets/NHA-AFIMS-Architecture.md` for the big-picture design.
3. Read `_guidelines/page-creation-guidelines.md` and `_guidelines/api-route-service-guidelines.md` before writing any new pages or routes.
4. Read `_guidelines/PagePermission-System-Guide.md` to understand how access control is enforced per page.
5. Pick a small module (UAC or LIBIS) and trace one feature end-to-end: page/endpoint → route → service → database.
