# Payroll Implementation Guide

## Quick Start

The payroll system is now ready to deploy. Follow these steps to activate it.

### Step 1: Database Setup

Run the SQL migrations in order:

```bash
# Navigate to project root
cd D:\PROJECTS\fims-nha

# Run tables (in order)
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/01 - table/42 - fims_payroll_employees.sql"
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/01 - table/43 - fims_payroll_deduction_types.sql"
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/01 - table/44 - fims_payroll_periods.sql"
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/01 - table/45 - fims_payroll_records.sql"
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/01 - table/46 - fims_payroll_deductions.sql"
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/01 - table/47 - fims_payroll_remittances.sql"

# Add foreign keys
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/03 - alter_table/36 - payroll_add_foreign_keys.sql"

# Seed master data (deduction types + sample periods)
psql -U postgres -d govflow360_nha < "_assets/sql/01 - fims/04 - seed_data/12 - payroll_master_data.sql"
```

Or use docker:
```bash
npm run demo-reset  # Resets DB and runs all migrations automatically
```

### Step 2: Verify API Routes

The API routes are in `app/routes/payroll.php`. They're automatically loaded by the routing system.

**Test with curl:**
```bash
curl http://localhost:8090/api/payroll/employees
curl http://localhost:8090/api/payroll/deduction-types
curl http://localhost:8090/api/payroll/periods
```

Expected responses:
- `GET /api/payroll/employees` → `{success: true, data: [], count: 0}`
- `GET /api/payroll/deduction-types` → 11 pre-seeded types (SSS, PhilHealth, etc.)
- `GET /api/payroll/periods` → 3 sample periods (Jan 2025, Feb 2025, 13th Month 2024)

### Step 3: Access UI Pages

The UI pages are in `app/pages/payroll/`. Register them in your system's page menu:

| Page | Path | Purpose |
|------|------|---------|
| **Payroll Employees** | `/app/pages/payroll/employees.php` | Manage employee directory + CSV import |
| **Payroll Records** | `/app/pages/payroll/records.php` | Create/manage payroll per period |
| **Payroll Remittances** | `/app/pages/payroll/remittances.php` | Track agency remittances (BIR, SSS, etc.) |

**To access:**
1. Add menu items in PHPMaker or your menu system:
   - Payroll → Employees
   - Payroll → Records
   - Payroll → Remittances

2. Or access directly:
   - http://localhost:8090/app/pages/payroll/employees.php
   - http://localhost:8090/app/pages/payroll/records.php
   - http://localhost:8090/app/pages/payroll/remittances.php

---

## Workflow: Creating Payroll for a Period

### 1. Import Employees (One-time Setup)

**Via UI:**
1. Go to **Payroll → Employees**
2. Click **Import CSV** button
3. Upload CSV file with columns:
   ```
   employee_no, first_name, last_name, middle_name, tin, sss_no, 
   philhealth_no, pagibig_no, position, department, monthly_salary, date_hired
   ```

**Via API:**
```bash
curl -X POST http://localhost:8090/api/payroll/employees/import \
  -F "file=@employees.csv"
```

**Sample CSV:**
```csv
employee_no,first_name,last_name,middle_name,tin,sss_no,position,department,monthly_salary,date_hired
EMP-001,Juan,Dela Cruz,Santos,123-456-789-001,33-2345678-8,Senior Engineer,Engineering,50000.00,2023-01-15
EMP-002,Maria,Santos,Garcia,123-456-790-002,33-2345679-9,Accountant,Finance,40000.00,2023-06-01
```

### 2. Create Payroll Period (Monthly)

**Via API:**
```bash
curl -X POST http://localhost:8090/api/payroll/periods \
  -H "Content-Type: application/json" \
  -d '{
    "fiscal_yr": 2025,
    "payroll_month": 1,
    "period_name": "January 2025",
    "period_start_date": "2025-01-01",
    "period_end_date": "2025-01-31",
    "status": "OPEN"
  }'
```

Or manually via SQL:
```sql
INSERT INTO fims.payroll_periods (fiscal_yr, payroll_month, period_name, period_start_date, period_end_date, status)
VALUES (2025, 1, 'January 2025', '2025-01-01', '2025-01-31', 'OPEN');
```

### 3. Create Payroll Records

**Via UI (Coming Soon):**
- Go to **Payroll → Records**
- Click **New Record**
- Select employee, period, and earnings
- System auto-calculates deductions

**Via API:**
```bash
# Create payroll record
curl -X POST http://localhost:8090/api/payroll/records \
  -H "Content-Type: application/json" \
  -d '{
    "payroll_period_id": 1,
    "employee_id": 1,
    "days_worked": 21,
    "regular_pay": 30000,
    "overtime_pay": 2000,
    "bonus_pay": 5000,
    "allowances": 1000,
    "apply_standard_deductions": true
  }'

# Response:
# {"success": true, "message": "Payroll record created.", "data": {"payroll_record_id": 1}}
```

### 4. Review & Approve Payroll

**Via API:**
```bash
# Approve
curl -X POST http://localhost:8090/api/payroll/records/1/approve

# Release (ready for payment)
curl -X POST http://localhost:8090/api/payroll/records/1/release \
  -H "Content-Type: application/json" \
  -d '{
    "payment_method": "BANK_TRANSFER",
    "bank_account_id": 1
  }'
```

### 5. Create Disbursement Voucher (for Payroll Payment)

In the DV form (`app/pages/voucher.php`):

1. **Expense Class:** Select **PS** (Personal Services)
2. **Link to Payroll Period:** (new field to add)
   - Selector shows all RELEASED payroll records for selected period
   - Auto-populates:
     - Claimant: Department name or "Payroll Group"
     - Gross Amount: Sum of all net_amounts in the period
     - Deductions: Pre-filled from payroll_deductions
     - Account Code: Defaults to COA "6010 - Salaries & Wages"
3. **Submit to FMD** as normal

**Current workaround (until DV form is updated):**

Create DV manually with:
- **Particulars:** "Payroll disbursement for [period name]"
- **Amount:** Sum of all employees' net_amount for the period
- **Account Code:** 6010 (Salaries & Wages)
- **Remarks:** "Covers employees EMP-001 through EMP-050, payroll period [date range]"

### 6. Create & Track Remittances

**Via UI:**
1. Go to **Payroll → Remittances**
2. Select payroll period
3. Click **Create Remittances** → auto-creates one per agency
4. For each remittance:
   - **Submit:** Click, enter remittance DV number (e.g., DV-2025-100)
   - **Confirm:** After agency confirms, enter receipt number + date

**Via API:**
```bash
# Create consolidated remittances (one per agency)
curl -X POST http://localhost:8090/api/payroll/remittances/1/create?consolidated=true

# Mark submitted
curl -X POST http://localhost:8090/api/payroll/remittances/1/submit \
  -H "Content-Type: application/json" \
  -d '{"remittance_no": "DV-2025-100"}'

# Confirm receipt
curl -X POST http://localhost:8090/api/payroll/remittances/1/confirm \
  -H "Content-Type: application/json" \
  -d '{"receipt_no": "BIR-RCV-123456", "receipt_date": "2025-02-05"}'
```

### 7. Generate Remittance Disbursement Vouchers

For each remittance (BIR, SSS, PhilHealth, Pag-IBIG), create a separate DV:

| Agency | Account Code | DV Purpose |
|--------|--------------|-----------|
| **BIR** | 2140 | Remit monthly income tax withholding |
| **SSS** | 2110 | Remit employee + employer SSS contributions |
| **PhilHealth** | 2120 | Remit employee + employer health insurance |
| **Pag-IBIG** | 2130 | Remit employee + employer housing fund |

**Example BIR Remittance DV:**
```
Claimant:     Bureau of Internal Revenue
Amount:       ₱45,000 (total BIR withheld for Jan 2025)
Particulars:  Monthly withholding tax remittance per BIR RR 7-2020
Account Code: 2140 (Tax Payable - BIR)
Remarks:      Covers 50 employees, payroll period 2025-01-01 to 2025-01-31
              Related payroll DV: DV-2025-099
```

---

## Advanced: Tax Annualization (Year-End)

### Year-End Workflow (December → 13th Month)

1. **Create 13th Month Payroll Period:**
   ```sql
   INSERT INTO fims.payroll_periods (fiscal_yr, payroll_month, period_name, period_start_date, period_end_date, status)
   VALUES (2025, 13, '13th Month - 2025 Year-End Adjustment', '2025-12-16', '2026-01-15', 'OPEN');
   ```

2. **Calculate Annual Tax Liability:**
   - Gross Income (Jan-Dec): Sum of all regular_pay for months 1-12
   - Taxable Income: Gross - Standard deduction (₱250,000 or itemized)
   - Annual Tax: Apply BIR tax schedule per filing status + dependents
   - YTD Withheld: Sum of all BIR_TAX deductions for months 1-12

3. **Annualization Adjustment:**
   ```
   Adjustment = Annual Tax Liability - YTD Withheld

   If Adjustment > 0:  Employee owes additional tax (deduct from 13th month)
   If Adjustment < 0:  Employee over-withheld (refund)
   If Adjustment = 0:  No adjustment needed
   ```

4. **Create 13th Month Payroll Record:**
   - Base: 13th month salary (usually = 1 month salary)
   - Plus: Bonuses, profit-sharing, other year-end benefits
   - Minus: Annualization adjustment
   - Result: Net 13th month payment

5. **File BIR Form 2307:**
   - Due: January 31st following fiscal year
   - Contains: All employee tax withholdings for the year
   - Generated from remittance records with `tax_return_form = '2307'`

### Implementation (Placeholder)

The services have placeholders for:
- `PayrollDeductionService::calculateBirTax()` — Returns 0 (needs real tax tables)
- `PayrollDeductionService::updateYtdDeductions()` — Tracks cumulative tax

**To implement fully:**
1. Get BIR tax tables (standard deduction method or itemized)
2. Build tax calculation in `PayrollDeductionService`
3. Create UI page for annualization review + approval
4. Generate 2307 export

For now, tax calculations can be done **manually** via:
```bash
# Get year-to-date tax deductions for an employee
SELECT 
    pd.*, pr.payroll_period_id, pp.payroll_month
FROM fims.payroll_deductions pd
JOIN fims.payroll_records pr ON pd.payroll_record_id = pr.payroll_record_id
JOIN fims.payroll_periods pp ON pr.payroll_period_id = pp.payroll_period_id
WHERE pr.employee_id = 1
AND pp.fiscal_yr = 2025
AND pd.is_withheld_tax = TRUE
ORDER BY pp.payroll_month;
```

---

## File Structure

```
D:\PROJECTS\fims-nha\
├── app\
│   ├── routes\
│   │   └── payroll.php                 ← API endpoints (25 routes)
│   ├── services\
│   │   ├── PayrollEmployeeService.php  ← Employee CRUD + CSV import
│   │   ├── PayrollRecordService.php    ← Payroll record logic
│   │   ├── PayrollDeductionService.php ← Deduction calculation
│   │   └── PayrollRemittanceService.php ← Agency remittance tracking
│   └── pages\
│       └── payroll\
│           ├── employees.php           ← Employee list + import UI
│           ├── records.php             ← Payroll records list + summary
│           └── remittances.php         ← Remittance dashboard
│
├── _assets\sql\01 - fims\
│   ├── 01 - table\
│   │   ├── 42 - fims_payroll_employees.sql
│   │   ├── 43 - fims_payroll_deduction_types.sql
│   │   ├── 44 - fims_payroll_periods.sql
│   │   ├── 45 - fims_payroll_records.sql
│   │   ├── 46 - fims_payroll_deductions.sql
│   │   └── 47 - fims_payroll_remittances.sql
│   ├── 03 - alter_table\
│   │   └── 36 - payroll_add_foreign_keys.sql
│   └── 04 - seed_data\
│       └── 12 - payroll_master_data.sql
│
├── PAYROLL_INTEGRATION.md              ← Schema & service reference
└── PAYROLL_IMPLEMENTATION_GUIDE.md     ← This file
```

---

## API Reference

### Employees

- `GET /api/payroll/employees` — List all active
- `GET /api/payroll/employees/{id}` — Get one
- `POST /api/payroll/employees` — Create/update
- `POST /api/payroll/employees/import` — CSV import

### Periods

- `GET /api/payroll/periods` — List all
- `POST /api/payroll/periods` — Create

### Payroll Records

- `GET /api/payroll/records/{periodId}` — List by period
- `GET /api/payroll/records/{recordId}/detail` — Get one (with deductions)
- `POST /api/payroll/records` — Create
- `PUT /api/payroll/records/{recordId}` — Update
- `POST /api/payroll/records/{recordId}/approve` — Approve
- `POST /api/payroll/records/{recordId}/release` — Release for payment
- `GET /api/payroll/periods/{periodId}/summary` — Summary (gross/deductions/net)

### Deductions

- `GET /api/payroll/deduction-types` — List all types
- `GET /api/payroll/records/{recordId}/deductions` — Get deductions
- `POST /api/payroll/records/{recordId}/deductions` — Add
- `DELETE /api/payroll/records/{recordId}/deductions/{deductionId}` — Remove

### Remittances

- `GET /api/payroll/remittances/{periodId}` — List by period
- `POST /api/payroll/remittances/{periodId}/create` — Auto-create
- `POST /api/payroll/remittances/{remittanceId}/submit` — Mark submitted
- `POST /api/payroll/remittances/{remittanceId}/confirm` — Confirm receipt
- `GET /api/payroll/remittances/{periodId}/summary` — Summary by agency
- `GET /api/payroll/remittances/{agency}/{fiscalYear}/ytd` — Year-to-date

---

## Testing

### Test Data

**Sample Employees (import via CSV):**
```csv
employee_no,first_name,last_name,tin,sss_no,position,department,monthly_salary,date_hired
TEST-001,Juan,Dela Cruz,111-111-111-111,33-1111111-1,Manager,Admin,50000,2023-01-01
TEST-002,Maria,Santos,222-222-222-222,33-2222222-2,Clerk,Finance,30000,2023-06-01
TEST-003,Pedro,Reyes,333-333-333-333,33-3333333-3,Technician,IT,40000,2023-03-01
```

**Sample Payroll Record (via API):**
```bash
curl -X POST http://localhost:8090/api/payroll/records \
  -H "Content-Type: application/json" \
  -d '{
    "payroll_period_id": 1,
    "employee_id": 1,
    "days_worked": 21,
    "regular_pay": 47000,
    "overtime_pay": 3000,
    "apply_standard_deductions": true
  }'
```

### Validation Checklist

- [ ] Employees imported successfully (check via GET /api/payroll/employees)
- [ ] Deduction types loaded (11 types visible)
- [ ] Payroll period created (Jan 2025, 13th Month visible)
- [ ] Payroll record created with gross/net calculation
- [ ] Standard deductions auto-applied (SSS, PhilHealth, Pag-IBIG calculated)
- [ ] Remittances auto-created (1 per agency)
- [ ] Remittance submission workflow (PENDING → SUBMITTED → CONFIRMED)
- [ ] DV linkage (payroll_record_id stored in disbursement_vouchers)

---

## Known Limitations & TODO

### Current (MVP)

- ✅ Employee management + CSV import
- ✅ Payroll records (create, edit, approve, release)
- ✅ Automatic deduction calculation (SSS, PhilHealth, Pag-IBIG)
- ✅ BIR tax withholding tracking
- ✅ Remittance to agencies (BIR, SSS, PhilHealth, Pag-IBIG)
- ✅ Year-to-date tracking (foundation)
- ✅ API routes (25 endpoints)
- ✅ UI pages (3 main pages)

### In Progress / TODO

- **DV Form Integration:** Link payroll_record_id, auto-populate deductions
- **Tax Annualization:** Implement actual BIR tax tables + 13th month adjustment
- **BIR Form 2307:** Generate annual withholding report
- **Payroll Dashboard:** Summary reports, trend charts
- **Loan Management:** Amortization schedules, balance tracking
- **Deduction Approval:** Workflow for salary loans, court orders, etc.
- **Bank Integration:** Auto-generate bank transfer files (BAI format)
- **Mobile UI:** Responsive design enhancements
- **Audit Reports:** Detailed change logs, reconciliation reports

### Workarounds (Until Features Implemented)

1. **BIR Tax Calculation:** Enter manually after running personal tax calculator
2. **13th Month Adjustment:** Calculate in Excel, enter adjustment deduction manually
3. **DV Linkage:** Document payroll period in DV remarks (temporary)
4. **Bank Files:** Export remittance summary, format manually for bank

---

## Support & Debugging

### Common Issues

**Q: "No employees found" after import?**
- Check CSV column names match exactly
- Ensure CSV is UTF-8 encoded
- Look at error details in import response

**Q: Deductions not calculating?**
- Verify deduction_type_id exists in fims.payroll_deduction_types
- Check deduction_base is not 0
- Ensure rate_percent > 0 for percentage-based deductions

**Q: Remittance status stuck on "PENDING"?**
- Use Submit endpoint: POST /api/payroll/remittances/{id}/submit
- Provide remittance_no (usually the DV number, e.g., DV-2025-100)

**Q: Payroll record net amount is 0?**
- Verify gross_amount was calculated (check regular_pay + overtime, etc.)
- Check total_deductions is not exceeding gross_amount
- Call recalculateDeductionsTotal() API if needed

### Debug Queries

```sql
-- Check employees
SELECT COUNT(*) FROM fims.payroll_employees WHERE employment_status = 'ACTIVE';

-- Check payroll records for a period
SELECT pr.payroll_record_id, pe.full_name, pr.gross_amount, pr.total_deductions, pr.net_amount, pr.status
FROM fims.payroll_records pr
JOIN fims.payroll_employees pe ON pr.employee_id = pe.employee_id
WHERE pr.payroll_period_id = 1
ORDER BY pe.last_name;

-- Check deductions for a record
SELECT pdt.code, pdt.name, pd.deduction_base, pd.rate_percent, pd.calculated_amount
FROM fims.payroll_deductions pd
JOIN fims.payroll_deduction_types pdt ON pd.deduction_type_id = pdt.deduction_type_id
WHERE pd.payroll_record_id = 1
ORDER BY pdt.sort_order;

-- Check remittances for a period
SELECT remittance_agency, remittance_status, COUNT(*) as count, SUM(total_amount) as total
FROM fims.payroll_remittances
WHERE payroll_period_id = 1
GROUP BY remittance_agency, remittance_status;
```

---

## Next Priorities

1. **Update DV Form** to accept `payroll_record_id` and auto-populate
2. **Implement Tax Calculation** module with BIR tax tables
3. **Build Annualization** page for year-end adjustments
4. **Create Payroll Dashboard** with charts and summary
5. **Add Loan Management** (salary loans, advances, amortization)

---

## Contact & Resources

- **TOR Reference:** NHA FIMS Technical Requirements (Section 2.3 Payroll & Compensation)
- **BIR Resources:**
  - Form 1701 (Annual Income Tax Return)
  - Form 2307 (Annual Withholding)
  - Form 1601-C (Monthly Remittance)
  - RR 7-2020 (Withholding Procedures)
- **DOLE/SSS:**
  - SSS Contribution Table (annual updates)
  - PhilHealth Premium Rates
  - Pag-IBIG Loan & Contribution Rules

