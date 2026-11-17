-- Adds the 4 remaining non-tax deduction types that "01 - table/20 - fims_deduction_types.sql"
-- left for later ("Only 'Retention' is seeded for now; more types can be added later without a
-- schema change"), so the voucher form's Deductions dropdown offers the full set:
--   OTHER        - any deduction not covered by a more specific type below
--   RECOUPMENT   - recovers a prior cash advance from the claimant's current payment
--   PENALTY_TAX  - a tax penalty/surcharge withheld and remitted to BIR
--   VAT          - Value Added Tax withheld from the gross amount
-- account_code is each type's default posting account when Accounting prepares the Journal Entry
-- Voucher (DisbursementVoucherService::persistJournalEntry() reads deduction_types.account_code as
-- a pre-fill, same as it already does for RETENTION's Guaranty/Security Deposits Payable) — nullable,
-- so OTHER is left without one (there's no single right account for "anything else").
-- Payroll DV deductions (Payroll Management → a DV prepared from an imported payroll month, see
-- PayrollManagementService::dvData()): the employees' deductions of the month are grouped by account into these:
--   PAYROLL_GSIS        Due to GSIS              (2-02-01-020) — GSIS premiums and loans
--   PAYROLL_PHILHEALTH  Due to PhilHealth        (2-02-01-040)
--   PAYROLL_PAGIBIG     Due to Pag-IBIG          (2-02-01-030) — contributions, MP2 and loans
--   PAYROLL_WTAX        Due to BIR               (2-02-01-010) — withholding tax on compensation
--   PAYROLL_OTHER       every other payroll deduction (provident fund, cooperative, other payables, receivables)
-- The DV's accounting entry uses each payroll item's own account (Payroll Setup); account_code here is the default.
-- Idempotent: ON CONFLICT (code), same pattern as the RETENTION row already there.
INSERT INTO fims.deduction_types (code, name, description, account_code) VALUES
    ('OTHER',       'Other',             'Other deduction not covered by a more specific type', NULL),
    ('RECOUPMENT',  'Advance Recoupment', 'Recovery of a prior cash advance from the claimant''s payment', '1-03-99-020'),
    ('PENALTY_TAX', 'Penalty Tax',       'Tax penalty/surcharge withheld and remitted to BIR', '2-02-01-010'),
    ('VAT',         'VAT',               'Value Added Tax withheld from the gross amount', '2-02-01-120'),
    ('PAYROLL_GSIS',       'GSIS (Payroll)',            'Employees'' GSIS premiums and loans withheld from the payroll', '2-02-01-020'),
    ('PAYROLL_PHILHEALTH', 'PhilHealth (Payroll)',      'Employees'' PhilHealth contributions withheld from the payroll', '2-02-01-040'),
    ('PAYROLL_PAGIBIG',    'Pag-IBIG (Payroll)',        'Employees'' Pag-IBIG contributions and loans withheld from the payroll', '2-02-01-030'),
    ('PAYROLL_WTAX',       'Withholding Tax (Payroll)', 'Withholding tax on compensation withheld from the payroll', '2-02-01-010'),
    ('PAYROLL_OTHER',      'Other Payroll Deductions',  'Other deductions withheld from the payroll (provident fund, cooperative, loans, other payables)', '2-99-99-990')
ON CONFLICT (code) DO UPDATE SET
    name         = EXCLUDED.name,
    description  = EXCLUDED.description,
    account_code = EXCLUDED.account_code;
