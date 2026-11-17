# Payroll Integration Schema & Services

## Overview

The FIMS payroll integration enables:
- **Employee directory** for payroll processing
- **Payroll period management** (monthly + year-end 13th month)
- **Payroll records** per employee per period with automatic deduction calculation
- **Deduction tracking** (SSS, PhilHealth, Pag-IBIG, BIR tax, loans, etc.)
- **Tax annualization** support (year-end reconciliation)
- **Remittance tracking** to government agencies (BIR, SSS, PhilHealth, Pag-IBIG)
- **Disbursement Voucher linking** for payroll payment via DV

## Database Schema

### Core Tables

#### `fims.payroll_employees`
One record per NHA employee or beneficiary eligible for payroll.

**Key Columns:**
- `employee_id` — PK, auto-increment
- `employee_no` — unique identifier (e.g., EMP-2025-001)
- `first_name, last_name, middle_name` — name fields (full_name is generated)
- `tin, sss_no, philhealth_no, pagibig_no` — government ID numbers
- `position, department, salary_grade` — employment details
- `monthly_salary` — gross monthly for annualization
- `employment_status` — ACTIVE | ON_LEAVE | SEPARATED | RETIRED
- `bank_account_no` — for bank transfer payroll

**Indexes:** employee_no, tin, status

---

#### `fims.payroll_deduction_types`
Master list of standard deduction classifications.

**Key Columns:**
- `code` — short code (e.g., SSS, PHILHEALTH, BIR_TAX, SALARY_LOAN)
- `category` — TAX | SOCIAL_SECURITY | LOAN | BENEFIT | OTHER
- `remittance_agency` — BIR | SSS | PHILHEALTH | PAGIBIG | INTERNAL | NONE
- `atc_code` — BIR Accounting Treatment Code (for tax reconciliation)
- `calculation_method` — PERCENTAGE | FIXED_AMOUNT | TABLE_BASED
- `include_in_annualization` — TRUE for tax deductions that participate in year-end reconciliation
- `sort_order` — display order in forms

**Pre-seeded Types:**
- SSS (3.63% of gross, remitted to SSS)
- PhilHealth (2.25% of gross, remitted to PhilHealth)
- Pag-IBIG (1% of gross, capped at ₱5,000 salary, remitted to Pag-IBIG)
- BIR_TAX (withheld tax, table-based, remitted to BIR)
- ANNUAL_TAX_ADJ (year-end adjustment for annualization)
- SALARY_LOAN, EMERGENCY_ADVANCE, HEALTH_INSURANCE, UNION_DUES, COURT_ORDER, CALAMITY_LOAN

---

#### `fims.payroll_periods`
Fiscal year + month combinations for payroll cycles.

**Key Columns:**
- `fiscal_yr` — fiscal year (e.g., 2025)
- `payroll_month` — 1–12 for regular months, 13 for year-end
- `period_name` — human-readable (e.g., "January 2025", "13th Month 2024")
- `period_start_date, period_end_date` — date range
- `status` — OPEN | LOCKED | CLOSED

**Pre-seeded Sample Periods:**
- January 2025, February 2025, 13th Month 2024

---

#### `fims.payroll_records`
Payroll header per employee per period. Holds earnings, calculates gross/net.

**Key Columns:**
- `payroll_record_id` — PK
- `payroll_period_id` — FK to period
- `employee_id` — FK to employee
- `base_salary` — snapshot of employee salary at payroll time
- `days_worked, hours_worked` — attendance/hours
- `regular_pay, overtime_pay, bonus_pay, allowances, other_income` — earnings components
- `gross_amount` — **GENERATED**: sum of all earnings
- `total_deductions` — denormalized (sum of payroll_deductions rows)
- `net_amount` — **GENERATED**: gross_amount - total_deductions
- `status` — DRAFT | FOR_REVIEW | APPROVED | RELEASED | PAID | REVERSED
- `disbursement_voucher_id` — optional link to DV for payment
- `payment_method` — BANK_TRANSFER | CHECK | CASH

**Unique Constraint:** (payroll_period_id, employee_id)

---

#### `fims.payroll_deductions`
Detail rows for each deduction applied to a payroll record.

**Key Columns:**
- `payroll_deduction_id` — PK
- `payroll_record_id` — FK to payroll record (CASCADE delete)
- `deduction_type_id` — FK to deduction type
- `deduction_base` — salary amount subject to deduction
- `rate_percent` — for percentage-based deductions
- `fixed_amount` — for fixed deductions (e.g., ₱500 loan payment)
- `calculated_amount` — **GENERATED**: fixed_amount OR (deduction_base × rate_percent / 100)
- `maximum_base` — salary ceiling (for SSS/PhilHealth min-max)
- `is_withheld_tax` — TRUE if BIR tax (links to remittance)
- `atc_code` — BIR code for tax annualization
- `ytd_deductions` — year-to-date total (for annualization)
- `principal_amount, balance_amount, monthly_amortization` — for loans

**Calculation Example:**
```
SSS (3.63%):    fixed_amount=0, rate_percent=3.63
                calculated = base × 3.63 / 100

Salary Loan:    fixed_amount=500, rate_percent=0
                calculated = 500
```

---

#### `fims.payroll_remittances`
Track remittance of deductions to government agencies.

**Key Columns:**
- `payroll_remittance_id` — PK
- `payroll_period_id` — FK to period
- `remittance_agency` — BIR | SSS | PHILHEALTH | PAGIBIG | INTERNAL
- `deduction_type_id` — FK (NULL if consolidated)
- `total_amount` — total deductions collected for this agency/type this period
- `remittance_status` — PENDING | SUBMITTED | CONFIRMED | CANCELLED
- `remittance_no` — official remittance receipt number from agency
- `receipt_date` — when agency confirmed receipt
- `is_consolidated` — TRUE if one record covers multiple detail deductions
- `tax_return_form` — BIR form reference (2307, 1601C, etc.)
- `disbursement_voucher_id` — DV used for payment of remittance

**Workflow:**
1. Payroll posted → `remittance_status = PENDING` (deductions collected)
2. Admin remits to agency → `remittance_status = SUBMITTED` (pending agency confirmation)
3. Agency confirms → `remittance_status = CONFIRMED` (with receipt_no, receipt_date)

---

## Services

### `PayrollEmployeeService`

Manages employee records and CSV import.

**Methods:**
- `listEmployees($filters)` — all active employees
- `getEmployee($id)` — single employee record
- `saveEmployee($data)` — create/update
- `importFromCsv($filePath, $userId)` — bulk import from CSV
  - **Expected columns:** employee_no, first_name, last_name, middle_name, tin, sss_no, position, department, monthly_salary, date_hired
  - **Returns:** `['imported' => int, 'updated' => int, 'errors' => []]`
- `deactivateEmployees($ids)` — set status to SEPARATED

**Usage:**
```php
$service = new PayrollEmployeeService($db);

// Import employees
$result = $service->importFromCsv('/tmp/employees.csv', $userId);
echo "Imported: {$result['imported']}, Updated: {$result['updated']}";
```

---

### `PayrollRecordService`

Manages payroll records per employee per period.

**Methods:**
- `listRecordsByPeriod($periodId, $filters)` — all records in a period
- `getRecord($recordId)` — with deductions
- `createRecord($periodId, $employeeId, $data, $userId)` — new record
- `updateRecord($recordId, $data, $userId)` — edit earnings
- `applyStandardDeductions($recordId, $userId)` — add SSS/PhilHealth/Pag-IBIG/BIR
- `recalculateDeductionsTotal($recordId)` — recompute net_amount
- `approve($recordId, $userId)` — move to APPROVED
- `release($recordId, $userId, $paymentMethod, $bankAccountId)` — move to RELEASED
- `getPeriodSummary($periodId)` — total gross/deductions/net
- `linkToDisbursementVoucher($recordId, $dvId)` — associate with DV

**Usage:**
```php
$service = new PayrollRecordService($db, $deductionService);

// Create payroll record
$recordId = $service->createRecord($periodId, $employeeId, [
    'days_worked' => 21,
    'regular_pay' => 30000,
    'overtime_pay' => 2000,
], $userId);

// Apply standard deductions
$service->applyStandardDeductions($recordId, $userId);

// Approve and release
$service->approve($recordId, $userId);
$service->release($recordId, $userId, 'BANK_TRANSFER', $bankAccountId);

// Link to DV for payment
$service->linkToDisbursementVoucher($recordId, $dvId);

// Summary
$summary = $service->getPeriodSummary($periodId);
echo "Total employees: {$summary['employee_count']}, Total gross: {$summary['total_gross']}";
```

---

### `PayrollDeductionService`

Manages individual deductions and tax calculations.

**Methods:**
- `listDeductionTypes($activeOnly)` — all standard types
- `getDeductionType($code)` — by code
- `getDeductionsByRecord($recordId)` — all deductions for a record
- `addDeduction($recordId, $typeId, $data, $userId)` — add one deduction
- `updateDeduction($deductionId, $data, $userId)` — edit
- `removeDeduction($deductionId)` — delete
- `calculateBirTax($grossAmount, $taxRegime)` — tax calculation (placeholder)
- `getTaxDeductionsByPeriod($periodId)` — all tax withholdings for remittance
- `getDeductionsByCategory($periodId, $category)` — grouped by category
- `updateYtdDeductions($employeeId, $fiscalYear, $userId)` — track year-to-date for annualization

**Usage:**
```php
$service = new PayrollDeductionService($db);

// List standard deduction types
$types = $service->listDeductionTypes(true); // active only

// Get deductions for a payroll record
$deductions = $service->getDeductionsByRecord($recordId);

// Manually add a loan deduction (if not auto-added)
$service->addDeduction($recordId, $loanTypeId, [
    'fixed_amount' => 5000,
    'principal_amount' => 50000,
    'balance_amount' => 45000,
    'monthly_amortization' => 5000,
], $userId);

// Get deductions grouped by category (for reporting)
$social = $service->getDeductionsByCategory($periodId, 'SOCIAL_SECURITY');
```

---

### `PayrollRemittanceService`

Tracks remittance to government agencies.

**Methods:**
- `listRemittancesByPeriod($periodId, $filters)` — all remittances in period
- `getRemittance($id)` — single record
- `createRemittanceForPeriod($periodId, $userId, $consolidated)` — auto-create
  - If `$consolidated = true`: one record per agency per period
  - If `$consolidated = false`: one record per deduction type per period
- `markSubmitted($id, $remittanceNo, $userId)` — status → SUBMITTED
- `confirmReceipt($id, $receiptNo, $receiptDate, $userId)` — status → CONFIRMED
- `linkToDisbursementVoucher($id, $dvId)` — associate with DV
- `getRemittanceSummary($periodId)` — by agency/status
- `getYtdRemittances($agency, $fiscalYear)` — year-to-date history
- `getRemittanceReport($periodId, $agency)` — detailed report for agency filing

**Usage:**
```php
$service = new PayrollRemittanceService($db);

// Create remittances (consolidated: one per agency)
$ids = $service->createRemittanceForPeriod($periodId, $userId, true);

// Mark as submitted to BIR
$service->markSubmitted($remittanceId, 'BIR-2025-001-DV', $userId);

// After agency confirms, mark receipt
$service->confirmReceipt($remittanceId, 'BIR-RECEIPT-12345', '2025-02-05', $userId);

// Get remittance summary
$summary = $service->getRemittanceSummary($periodId);
// Output:
// BIR: 5 pending, 2 confirmed, total: 45000
// SSS: 1 pending, total: 21000

// Generate detailed remittance report for BIR filing
$report = $service->getRemittanceReport($periodId, 'BIR');
```

---

## Integration with Disbursement Vouchers

### Workflow: Payroll via DV

1. **Create Payroll Records**
   - Admin creates employees in `payroll_employees`
   - Admin creates `payroll_periods` (monthly + year-end)
   - Create `payroll_records` per employee per period
   - Apply standard deductions (SSS, PhilHealth, etc.)

2. **Generate DV for Payroll**
   - Originating user creates DV with expense_class = **PS** (Personal Services)
   - Link `payroll_record_id` to DV (new column in disbursement_vouchers)
   - DV details auto-populate from payroll:
     - Claimant: department/payroll group name
     - Gross Amount: sum of all payroll_records for this period
     - Deductions: pre-filled from payroll_deductions
     - Net Amount: gross minus deductions
   - Account Code: use COA "PS" or "6010 - Salaries & Wages"

3. **Post DV → Ledger**
   - When DV is POSTED:
     - GL entry: DR 6010 (Salaries), CR 2010 (Accrued Payroll / Payables)
     - Update `payroll_records.status = PAID`
     - Update `payroll_remittances.status = PENDING` (ready for agency remittance)

4. **Remit Deductions to Agencies**
   - Admin calls `createRemittanceForPeriod()` → creates BIR, SSS, PhilHealth remittance records
   - Create separate **Remittance DVs** for each agency:
     - **BIR DV:** account code = 2140 (Tax Payable - BIR)
     - **SSS DV:** account code = 2110 (SSS Payable)
     - **PhilHealth DV:** account code = 2120 (PhilHealth Payable)
   - Link remittance to remittance DV: `linkToDisbursementVoucher($remittanceId, $remittanceDvId)`
   - When remittance DV is POSTED:
     - CR 2140/2110/2120 (Payable), DR 2010 (Accrued Payroll) or bank if cash paid
     - Admin confirms receipt from agency: `confirmReceipt()`

5. **Tax Annualization (Year-End)**
   - In 12th month (month 13 payroll_period):
     - Calculate cumulative tax for fiscal year (YTD)
     - Compute annual tax liability (standard deduction or itemized)
     - Calculate annualization adjustment = annual_tax_liability - YTD_tax_paid
     - Create special "13th Month + Adjustment" DV
     - If adjustment < 0 (over-withheld), refund to employee
     - If adjustment > 0 (under-withheld), deduct from 13th month
   - File BIR Form 2307 (Annual Withholding) before year-end

---

## CSV Import Format

**File:** `employees.csv`
```csv
employee_no,first_name,last_name,middle_name,tin,sss_no,philhealth_no,pagibig_no,position,department,monthly_salary,date_hired
EMP-2025-001,Juan,Dela Cruz,Santos,123-456-789-001,33-2345678-8,031234567891,121234567891,Senior Engineer,Engineering,50000.00,2023-01-15
EMP-2025-002,Maria,Santos,Garcia,123-456-790-002,33-2345679-9,031234567892,121234567892,Accountant,Finance,40000.00,2023-06-01
```

**Upload via:**
```php
$employeeService = new PayrollEmployeeService($db);
$result = $employeeService->importFromCsv($uploadedFile, $currentUserId);
```

---

## Sample Queries

### 1. Get Payroll Summary for January 2025

```php
$periodId = $db->fetchOne('SELECT payroll_period_id FROM fims.payroll_periods 
                            WHERE fiscal_yr = 2025 AND payroll_month = 1');
$summary = $payrollRecordService->getPeriodSummary($periodId);
// Result: ['employee_count' => 50, 'total_gross' => 1500000, 
//          'total_deductions' => 300000, 'total_net' => 1200000]
```

### 2. Get BIR Tax Deductions for Remittance

```php
$taxDeductions = $deductionService->getTaxDeductionsByPeriod($periodId);
foreach ($taxDeductions as $row) {
    echo "{$row['full_name']}: {$row['calculated_amount']}\n";
}
// Output:
// Juan Dela Cruz: 2500.00
// Maria Santos: 1800.00
// ...
// Total: 45000.00
```

### 3. Create Remittances & Link to DVs

```php
// Create consolidated remittance records (one per agency)
$remittanceIds = $remittanceService->createRemittanceForPeriod($periodId, $userId, true);

// Create and link remittance DVs
foreach (['BIR' => '2140', 'SSS' => '2110', 'PHILHEALTH' => '2120'] as $agency => $accountCode) {
    $remittance = $remittanceService->listRemittancesByPeriod($periodId, ['agency' => $agency])[0];
    
    // Create DV for remittance
    $dvData = [
        'particulars' => "Remittance of {$agency} deductions for month",
        'gross_amount' => $remittance['total_amount'],
        'account_code' => $accountCode,
        // ... other DV fields
    ];
    $dvId = $dvService->create($dvData, $userId);
    
    // Link remittance to DV
    $remittanceService->linkToDisbursementVoucher($remittance['payroll_remittance_id'], $dvId);
    $remittanceService->markSubmitted($remittance['payroll_remittance_id'], "DV-{$dvId}", $userId);
}
```

---

## Next Steps for Implementation

1. **Run SQL migrations:**
   ```bash
   # Tables
   psql govflow360_nha < _assets/sql/01 - fims/01 - table/42 - fims_payroll_employees.sql
   psql govflow360_nha < _assets/sql/01 - fims/01 - table/43 - fims_payroll_deduction_types.sql
   # ... (continue for all 7 tables)
   
   # Foreign keys
   psql govflow360_nha < _assets/sql/01 - fims/03 - alter_table/36 - payroll_add_foreign_keys.sql
   
   # Seed data
   psql govflow360_nha < _assets/sql/01 - fims/04 - seed_data/12 - payroll_master_data.sql
   ```

2. **Create API endpoints** (in `app/routes/payroll.php`):
   - `GET /api/payroll/employees` — list
   - `POST /api/payroll/employees/import` — CSV upload
   - `GET /api/payroll/records/:periodId` — list records
   - `POST /api/payroll/records` — create
   - `PUT /api/payroll/records/:id` — update
   - `POST /api/payroll/remittances/:periodId/create` — auto-create remittances
   - `GET /api/payroll/remittances/:periodId` — list

3. **Create UI pages** (in `app/pages/payroll/`):
   - Employees list + import modal
   - Payroll records list + form modal
   - Remittances dashboard + tracking
   - Payroll summary reports

4. **Integrate with DV Form:**
   - Add `payroll_record_id` selector when expense_class = PS
   - Auto-populate gross/deductions from linked payroll record
   - Prevent manual edits of deduction lines if linked to payroll

5. **Tax Annualization Module:**
   - Calculate YTD tax per employee
   - Generate annualization adjustments
   - File BIR 2307 forms

---

## Notes

- **BIR Tax Calculation:** Currently a placeholder (returns 0). Implement actual tax tables per marital status, dependent count, and income level. Refer to BIR Form 1701.
- **Concurrent Payroll:** If monthly + 13th month overlap, create separate `payroll_periods` with `payroll_month = 13`.
- **Payment Method:** Bank transfer (recommended) or check. Cash requires special audit controls.
- **Deduction Edits:** Once payroll is APPROVED, prevent changes (maintain audit trail). Only allow edits in DRAFT.
- **Remittance Timing:** Monthly deductions should be remitted within 10 days of month-end (per BIR/SSS/PhilHealth rules).
