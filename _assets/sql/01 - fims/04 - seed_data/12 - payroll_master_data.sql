-- ============================================================================
-- Payroll Master Data — standard deduction types for government employees
-- (SSS, PhilHealth, Pag-IBIG, BIR withholding, etc.) and sample payroll
-- period. These are reference data used across all payroll records.
-- ============================================================================

-- ── Standard Payroll Deduction Types ──
-- Do NOT alter these rows after they're linked to actual payroll records.
-- To deprecate a type, set status = 'INACTIVE' instead of deleting.

INSERT INTO fims.payroll_deduction_types (code, name, description, category, remittance_agency, atc_code, calculation_method, default_rate, include_in_annualization, sort_order, status)
VALUES
    -- Government social security deductions (mandatory for all employees)
    ('SSS', 'Social Security System (SSS)', 'Employee contribution to SSS', 'SOCIAL_SECURITY', 'SSS', NULL, 'PERCENTAGE', 3.63, FALSE, 10, 'ACTIVE'),
    ('PHILHEALTH', 'PhilHealth', 'Employee contribution to PhilHealth', 'SOCIAL_SECURITY', 'PHILHEALTH', NULL, 'PERCENTAGE', 2.25, FALSE, 20, 'ACTIVE'),
    ('PAGIBIG', 'Pag-IBIG (Home Development Mutual Fund)', 'Employee contribution to Pag-IBIG', 'SOCIAL_SECURITY', 'PAGIBIG', NULL, 'PERCENTAGE', 1.00, FALSE, 30, 'ACTIVE'),

    -- BIR withholding tax (varies by income and status)
    ('BIR_TAX', 'BIR Withholding Tax', 'Bureau of Internal Revenue withholding (monthly)', 'TAX', 'BIR', '1701', 'TABLE_BASED', NULL, TRUE, 5, 'ACTIVE'),
    ('ANNUAL_TAX_ADJ', 'Annual Tax Adjustment', 'Year-end tax adjustment/annualization (13th month)', 'TAX', 'BIR', '1701', 'FIXED_AMOUNT', NULL, TRUE, 6, 'ACTIVE'),

    -- Salary loans and advances (employee-specific, not standard)
    ('SALARY_LOAN', 'Salary Loan Amortization', 'Monthly repayment of salary loans', 'LOAN', 'INTERNAL', NULL, 'FIXED_AMOUNT', NULL, FALSE, 40, 'ACTIVE'),
    ('EMERGENCY_ADVANCE', 'Emergency Advance Repayment', 'Repayment of emergency salary advances', 'LOAN', 'INTERNAL', NULL, 'FIXED_AMOUNT', NULL, FALSE, 45, 'ACTIVE'),

    -- Employee benefits & other deductions
    ('HEALTH_INSURANCE', 'Health Insurance Premium', 'Group health insurance contribution', 'BENEFIT', 'INTERNAL', NULL, 'FIXED_AMOUNT', NULL, FALSE, 50, 'ACTIVE'),
    ('UNION_DUES', 'Union Dues', 'Labor union membership dues', 'BENEFIT', 'INTERNAL', NULL, 'FIXED_AMOUNT', NULL, FALSE, 60, 'ACTIVE'),
    ('EMPLOYEE_CONTRIBUTION', 'Employee Contribution/Fund', 'Wellness fund or other employee fund contributions', 'BENEFIT', 'INTERNAL', NULL, 'FIXED_AMOUNT', NULL, FALSE, 65, 'ACTIVE'),

    -- Court orders & mandatory deductions
    ('COURT_ORDER', 'Court Order Deduction', 'Garnishment or other court-ordered withholding', 'OTHER', 'INTERNAL', NULL, 'FIXED_AMOUNT', NULL, FALSE, 70, 'ACTIVE'),
    ('CALAMITY_LOAN', 'Calamity Loan Repayment', 'Repayment of government calamity loans', 'LOAN', 'INTERNAL', NULL, 'FIXED_AMOUNT', NULL, FALSE, 75, 'ACTIVE')
ON CONFLICT (code) DO NOTHING;

-- ── Sample Payroll Period ──
-- Used for testing and initial setup. Admin should create actual periods per fiscal year.
INSERT INTO fims.payroll_periods (fiscal_yr, payroll_month, period_name, period_start_date, period_end_date, status)
VALUES
    (2025, 1, 'January 2025', '2025-01-01'::DATE, '2025-01-31'::DATE, 'OPEN'),
    (2025, 2, 'February 2025', '2025-02-01'::DATE, '2025-02-28'::DATE, 'OPEN'),
    (2025, 12, '13th Month 2024', '2024-12-16'::DATE, '2025-01-15'::DATE, 'OPEN')
ON CONFLICT (fiscal_yr, payroll_month) DO NOTHING;

-- ── Notes for Admin ──
-- 1. SSS contribution rate: 3.63% (employee contribution; employer contributes 8.75% + 1.69%)
--    PhilHealth: 2.25% (employee) on insurable earnings (adjusted monthly)
--    Pag-IBIG: 1% on salary up to ₱5,000
-- 2. BIR tax: table-based, uses tax schedules per marital status and dependent count
-- 3. Add new employees to fims.payroll_employees before creating payroll records
-- 4. Adjust deduction rates annually based on government announcements
-- 5. For year-end (13th month), create a payroll_period with payroll_month = 13
