-- ============================================================================
-- Chart of Accounts alignment for the 108 payroll items seeded in
-- 19 - payroll_items_setup_for_import.sql. Run AFTER that file.
--
-- Two things happen here:
--   1. Where the existing UACS COA already has a fitting 4-part account
--      (Salaries and Wages, PERA, Due to GSIS/Pag-IBIG/PhilHealth/BIR, Other
--      Payables, etc.) the payroll item is pointed straight at it.
--   2. Where the UACS COA has only ONE account for a government agency that
--      the payroll form tracks as MANY distinct loan/contribution lines
--      (e.g. nine different GSIS loan types all withheld to the one "Due to
--      GSIS" account, or PFAI — a private employee association with no UACS
--      code at all), new sub-ledger accounts are added as 5th-segment
--      children of the nearest UACS parent. This is exactly the mechanism
--      fims.payroll_items itself documents (01 - table/48): "a sub-ledger
--      account under one (5th/6th part, e.g. 2-02-01-020-10), which the
--      Payroll page adds to the chart itself when it does not exist yet."
--      The parent 4-part account stays posting_allowed — these are FIMS-side
--      subsidiary detail, not a UACS revision.
--
-- Parent lookups use account_code (the stable business key), never
-- account_id, so this runs the same whether the live DB was seeded from
-- "02 - seed_data/01" (COA-00001 ids) or "02 - sample_data/03" (numeric ids)
-- — see project_coa_code_pathb memory.
--
-- New account_ids use a PYR#### namespace to avoid any collision with either
-- seed's id scheme.
-- ============================================================================

-- ── Step 1: new accounts whose parent already exists in the UACS COA ──
INSERT INTO fims.chart_of_accounts (account_id, account_code, account_title, account_type, parent_account_id, posting_allowed, status, source_document)
SELECT v.account_id, v.account_code, v.account_title, 'Liability', p.account_id, v.posting_allowed, 'Active', 'Payroll Items Setup'
FROM (VALUES
    -- GSIS loan/contribution sub-ledgers, under Due to GSIS (2-02-01-020)
    ('PYR001', '2-02-01-020-10', 'GSIS Consolidated Loan Payable',                '2-02-01-020', TRUE),
    ('PYR002', '2-02-01-020-11', 'GSIS CEAP Loan Payable',                        '2-02-01-020', TRUE),
    ('PYR003', '2-02-01-020-12', 'GSIS Educational Assistance Loan Payable',      '2-02-01-020', TRUE),
    ('PYR004', '2-02-01-020-13', 'GSIS Real Estate Loan Payable',                 '2-02-01-020', TRUE),
    ('PYR005', '2-02-01-020-14', 'GSIS Emergency Loan Payable',                   '2-02-01-020', TRUE),
    ('PYR006', '2-02-01-020-15', 'GSIS Optional Insurance Payable',               '2-02-01-020', TRUE),
    ('PYR007', '2-02-01-020-16', 'GSIS Regular Policy Loan Payable',              '2-02-01-020', TRUE),
    ('PYR008', '2-02-01-020-17', 'GSIS Optional Policy Loan Payable',             '2-02-01-020', TRUE),
    ('PYR009', '2-02-01-020-18', 'GSIS Enhanced Consolidated Loan Payable',       '2-02-01-020', TRUE),
    ('PYR010', '2-02-01-020-19', 'GSIS Loan Backpremium Payable',                 '2-02-01-020', TRUE),
    ('PYR011', '2-02-01-020-20', 'GSIS Social Insurance Contribution Payable',    '2-02-01-020', TRUE),
    -- Pag-IBIG/HDMF sub-ledgers, under Due to Pag-IBIG (2-02-01-030)
    ('PYR012', '2-02-01-030-10', 'Pag-IBIG/HDMF Contribution Payable',            '2-02-01-030', TRUE),
    ('PYR013', '2-02-01-030-11', 'Pag-IBIG Calamity Loan Payable',                '2-02-01-030', TRUE),
    ('PYR014', '2-02-01-030-12', 'Pag-IBIG Modified Pag-IBIG II Loan Payable',    '2-02-01-030', TRUE),
    ('PYR015', '2-02-01-030-13', 'Pag-IBIG Multi-Purpose Loan Payable',           '2-02-01-030', TRUE),
    ('PYR016', '2-02-01-030-14', 'NHMFC Housing Loan Payable',                    '2-02-01-030', TRUE),
    ('PYR017', '2-02-01-030-15', 'Pag-IBIG Contribution Backpremium Payable',     '2-02-01-030', TRUE),
    ('PYR018', '2-02-01-030-16', 'Pag-IBIG Corporate Share Overpayment Recoupment', '2-02-01-030', TRUE),
    -- PhilHealth sub-ledgers, under Due to PhilHealth (2-02-01-040)
    ('PYR019', '2-02-01-040-10', 'PhilHealth Contribution Payable (Employee Share)', '2-02-01-040', TRUE),
    ('PYR020', '2-02-01-040-11', 'PhilHealth Contribution Backpremium Payable',   '2-02-01-040', TRUE),
    ('PYR021', '2-02-01-040-12', 'PhilHealth Backpremium Recoupment Payable',     '2-02-01-040', TRUE),
    ('PYR022', '2-02-01-040-13', 'PhilHealth Corporate Share Overpayment Recoupment', '2-02-01-040', TRUE),
    -- PFAI (Provident Fund Association, Inc.) has no UACS code at all — add it
    -- as its own 4-part account alongside the other Inter-Agency Payables,
    -- under 2-02-01 (Inter-Agency Payables), matching that branch's own level.
    ('PYR023', '2-02-01-150',    'Due to PFAI (Provident Fund Association, Inc.)', '2-02-01', TRUE),
    -- New grouping accounts under Other Payables (2-99-99) — non-leaf, like
    -- the existing 2-99-99 siblings (839/840 in the sample seed).
    ('PYR036', '2-99-99-991',    'Due to Credit Union/Cooperative',               '2-99-99', FALSE),
    ('PYR042', '2-99-99-992',    'Due to NHA Employee Loan Programs',             '2-99-99', FALSE),
    ('PYR047', '2-99-99-993',    'Other Employee Deductions Payable',             '2-99-99', FALSE)
) AS v(account_id, account_code, account_title, parent_code, posting_allowed)
JOIN fims.chart_of_accounts p ON p.account_code = v.parent_code
ON CONFLICT (account_code) DO NOTHING;

-- ── Step 2: sub-ledgers whose parent is one of the new accounts from Step 1 ──
INSERT INTO fims.chart_of_accounts (account_id, account_code, account_title, account_type, parent_account_id, posting_allowed, status, source_document)
SELECT v.account_id, v.account_code, v.account_title, 'Liability', p.account_id, TRUE, 'Active', 'Payroll Items Setup'
FROM (VALUES
    -- PFAI loan/benefit sub-ledgers, under Due to PFAI (2-02-01-150)
    ('PYR024', '2-02-01-150-10', 'PFAI Contribution Payable',                   '2-02-01-150'),
    ('PYR025', '2-02-01-150-11', 'PFAI Cash/Consumer Benefit Loan (CBL) Payable', '2-02-01-150'),
    ('PYR026', '2-02-01-150-12', 'PFAI Multi-Purpose Loan/EL4 Payable',          '2-02-01-150'),
    ('PYR027', '2-02-01-150-13', 'PFAI Salary/Special Benefit Loan (SBL) Payable', '2-02-01-150'),
    ('PYR028', '2-02-01-150-14', 'PFAI Welfare Fund Payable',                    '2-02-01-150'),
    ('PYR029', '2-02-01-150-15', 'PFAI Emergency Loan Payable',                  '2-02-01-150'),
    ('PYR030', '2-02-01-150-16', 'PFAI Special Loan Payable',                    '2-02-01-150'),
    ('PYR031', '2-02-01-150-17', 'PFAI Co-Maker Loan Payable',                   '2-02-01-150'),
    ('PYR032', '2-02-01-150-18', 'PFAI Healthcare Payable',                      '2-02-01-150'),
    ('PYR033', '2-02-01-150-19', 'PFAI LRI Premium Payable',                     '2-02-01-150'),
    ('PYR034', '2-02-01-150-20', 'PFAI CNA Loans Payable',                       '2-02-01-150'),
    ('PYR035', '2-02-01-150-21', 'PFAI Contribution Backpremium Payable',        '2-02-01-150'),
    -- Credit Union / Cooperative sub-ledgers, under Due to Credit Union/Cooperative (2-99-99-991)
    ('PYR037', '2-99-99-991-10', 'Credit Union Fixed Deposit Payable',           '2-99-99-991'),
    ('PYR038', '2-99-99-991-11', 'Credit Union Loan Amortization Payable',       '2-99-99-991'),
    ('PYR039', '2-99-99-991-12', 'ECCAI Accounts Receivable Payable',            '2-99-99-991'),
    ('PYR040', '2-99-99-991-13', 'ECCAI Appliance Loan Payable',                 '2-99-99-991'),
    ('PYR041', '2-99-99-991-14', 'Credit Union Emergency (CUE) Loan Payable',    '2-99-99-991'),
    -- NHA employee loan program sub-ledgers, under Due to NHA Employee Loan Programs (2-99-99-992)
    ('PYR043', '2-99-99-992-10', 'NHA Car Loan Payable',                         '2-99-99-992'),
    ('PYR044', '2-99-99-992-11', 'NHA House Repair Loan Payable',                '2-99-99-992'),
    ('PYR045', '2-99-99-992-12', 'NHA Housing Loan Payable',                     '2-99-99-992'),
    ('PYR046', '2-99-99-992-13', 'MRI Housing Loan Payable',                     '2-99-99-992'),
    -- Other employee deduction sub-ledgers, under Other Employee Deductions Payable (2-99-99-993)
    ('PYR048', '2-99-99-993-10', 'Union Dues / Agency Fee Payable',              '2-99-99-993'),
    ('PYR049', '2-99-99-993-11', 'Cashier''s Bond/Shortage Payable',             '2-99-99-993'),
    ('PYR050', '2-99-99-993-12', 'Smart/Globe Mobile Plan Payable',              '2-99-99-993'),
    ('PYR051', '2-99-99-993-13', 'UMID Cash Advance Payable',                    '2-99-99-993'),
    ('PYR052', '2-99-99-993-14', 'Cocolife Premium Payable',                     '2-99-99-993'),
    ('PYR053', '2-99-99-993-15', 'Acquired Assets Payable',                      '2-99-99-993'),
    ('PYR054', '2-99-99-993-16', 'Cash Accountabilities Payable',                '2-99-99-993'),
    ('PYR055', '2-99-99-993-17', 'Overpayment - Salary Recoupment',              '2-99-99-993'),
    ('PYR056', '2-99-99-993-18', 'Overpayment - PERA Recoupment',                '2-99-99-993'),
    ('PYR057', '2-99-99-993-19', 'Overpayment - Transportation Allowance Recoupment', '2-99-99-993'),
    ('PYR058', '2-99-99-993-20', 'Overpayment - Representation Allowance Recoupment', '2-99-99-993'),
    ('PYR059', '2-99-99-993-21', 'Overpayment - Children''s Allowance Recoupment', '2-99-99-993'),
    ('PYR060', '2-99-99-993-22', 'Overpayment - MYFA 2018 Recoupment',           '2-99-99-993'),
    ('PYR061', '2-99-99-993-23', 'Overpayment - Additional Compensation Recoupment', '2-99-99-993')
) AS v(account_id, account_code, account_title, parent_code)
JOIN fims.chart_of_accounts p ON p.account_code = v.parent_code
ON CONFLICT (account_code) DO NOTHING;

-- ── Step 3: point every payroll item at its account_code ──
UPDATE fims.payroll_items pi
SET account_code = m.account_code
FROM (VALUES
    -- EARNING — existing UACS Personnel Services expense accounts
    ('BASIC_SALARY',             '5-01-01-010'),
    ('PERA',                     '5-01-02-010'),
    ('REPRESENTATION_ALLOWANCE', '5-01-02-020'),
    ('TRANSPORTATION_ALLOWANCE', '5-01-02-030'),
    ('MEAL_SUBSIDY',             '5-01-02-050'),
    ('RICE_SUBSIDY',             '5-01-02-990'),
    ('CHILDREN''S_ALLOWANCE',    '5-01-02-990'),
    ('INCENTIVE_DESIGNATION',    '5-01-02-080'),
    ('STEP_INCREMENT',           '5-01-01-010'),
    ('CHILDREN''S_ALLOW_ADJ',    '5-01-02-990'),
    ('MYFA_2017',                '5-01-02-990'),
    ('OTHERS_PERA',              '5-01-02-010'),
    ('SALARY_DIFFERENTIAL',      '5-01-01-010'),
    ('UNIFORM',                  '5-01-02-040'),
    ('MEDICAL',                  '5-01-02-990'),
    ('MIDYEAR',                  '5-01-02-160'),
    ('YEAR_END',                 '5-01-02-140'),
    ('CASH_GIFT',                '5-01-02-150'),
    ('3K',                       '5-01-02-990'),
    ('50K',                      '5-01-02-990'),
    ('SUPP_CLOTHING ALLOW',      '5-01-02-040'),
    ('LOYALTY',                  '5-01-02-120'),

    -- EMPLOYER_SHARE — existing UACS Personnel Benefit Contributions expense accounts
    ('CORP/SHARE_PAGIBIG_CONT',  '5-01-03-020'),
    ('CORP/SHARE_PHILHEALTH',    '5-01-03-030'),
    ('CORP/SHARE_PFAI',          '5-01-03-050'),
    ('CORP/SHARE_GSIS',          '5-01-03-010'),
    ('BPR_CORP/SHARE_GSIS',      '5-01-03-010'),
    ('CORP/SHARE_STATEINS',      '5-01-03-040'),
    ('BPR_CORP/SHARE_PFAI',      '5-01-03-050'),

    -- REFUND — same account as the deduction/allowance being reversed
    ('REFUND_GSIS_CONSO',        '2-02-01-020-10'),
    ('REFUND_GSIS_EAL',          '2-02-01-020-14'),
    ('REFUND_GSIS_ENH-CONSO',    '2-02-01-020-18'),
    ('REFUND_GSIS_PL',           '2-02-01-020-16'),
    ('OTHERS_LWOP_REFUND',       '5-01-01-010'),
    ('REFUND_PHILHEALTH',        '2-02-01-040-10'),
    ('REFUND_GSIS_SIC',          '2-02-01-020-20'),
    ('REFUND_EXP_W/TAX',         '2-02-01-010'),
    ('REFUND_PERCENTAGE_TAX',    '2-02-01-010'),
    ('REFUND_GSIS_EL',           '2-02-01-020-12'),
    ('REFUND_OPT',               '2-02-01-020-15'),
    ('REFUND_PFAI',              '2-02-01-150-10'),
    ('REFUND_REPRESENTATION',    '5-01-02-020'),
    ('REFUND_TRANSPORTATION',    '5-01-02-030'),
    ('TAX_REFUND',               '2-02-01-010'),
    ('2016_CNA_IN_REF',          '5-01-02-990'),

    -- DEDUCTION — existing UACS Inter-Agency Payables / Other Payables, or the new sub-ledgers from Step 1-2
    ('CASHIER''S',               '2-99-99-993-11'),
    ('CU_FIX_DEPOSIT',           '2-99-99-991-10'),
    ('CU_LOAN_RECEIVABLE',       '2-99-99-991-11'),
    ('GSIS_CONSOLIDATED_LOAN',   '2-02-01-020-10'),
    ('ECCAI_ACC_RECEIVABLE',     '2-99-99-991-12'),
    ('ECCAI_APPLIANCE_LOAN',     '2-99-99-991-13'),
    ('GSIS/SIC',                 '2-02-01-020-20'),
    ('GSIS_CEAP',                '2-02-01-020-11'),
    ('GSIS_EDUC_ASSIST_LOAN',    '2-02-01-020-12'),
    ('GSIS_REALESTATE_LOAN',     '2-02-01-020-13'),
    ('GSIS_EMERGENCY_LOAN',      '2-02-01-020-14'),
    ('GSIS_OPTIONAL_INSURANCE',  '2-02-01-020-15'),
    ('PFAI',                     '2-02-01-150-10'),
    ('PFAI_CBL',                 '2-02-01-150-11'),
    ('PFAI_MPL/EL4',             '2-02-01-150-12'),
    ('PFAI_SBL',                 '2-02-01-150-13'),
    ('PFAI_WELFARE_FUND',        '2-02-01-150-14'),
    ('UNDERTIME_W/O_PAY',        '5-01-01-010'),
    ('UNION_DUES/AGENCY_FEE',    '2-99-99-993-10'),
    ('WITHHOLDING_TAX',          '2-02-01-010'),
    ('OTHER_PAYABLES',           '2-99-99-990'),
    ('PHILHEALTH',               '2-02-01-040-10'),
    ('ACQUIRED_ASSETS',          '2-99-99-993-15'),
    ('HDMF',                     '2-02-01-030-10'),
    ('HDMF_CALAMITY_LOAN',       '2-02-01-030-11'),
    ('HDMF_MODIFIED_PAGIBIG_II', '2-02-01-030-12'),
    ('HDMF_PAGIBIG_MPL',         '2-02-01-030-13'),
    ('LEAVE_W/O_PAY',            '5-01-01-010'),
    ('NHA_CAR_LOAN',             '2-99-99-992-10'),
    ('NHA_HOUSE_REPAIR_LOAN',    '2-99-99-992-11'),
    ('NHA_HOUSING_LOAN',         '2-99-99-992-12'),
    ('NHMFC_HOUSING_LOAN',       '2-02-01-030-14'),
    ('O/P_TRANS_ALLOWANCE',      '2-99-99-993-19'),
    ('GSIS_REG_POLICY_LOAN',     '2-02-01-020-16'),
    ('GSIS_OPTIONAL_POLICY_LOAN', '2-02-01-020-17'),
    ('PFAI_HEALTHCARE',          '2-02-01-150-18'),
    ('O/P_REP_ALLOW',            '2-99-99-993-20'),
    ('CASH_ACCOUNTABILITIES',    '2-99-99-993-16'),
    ('ENHANCED_CONSO_LOAN',      '2-02-01-020-18'),
    ('PFAI_EL',                  '2-02-01-150-15'),
    ('MRI_HOUSING',              '2-99-99-992-13'),
    ('PFAI_SPL',                 '2-02-01-150-16'),
    ('PFAI_COMAKER',             '2-02-01-150-17'),
    ('BACKPAYMENT_W/HOLDING',    '2-02-01-010'),
    ('BACKPREMIUM_PHILHEALTH',   '2-02-01-040-11'),
    ('BACKPREMIUM_GSIS',         '2-02-01-020-19'),
    ('BPR_PFAI',                 '2-02-01-150-21'),
    ('BPR_PHILHEALTH',           '2-02-01-040-12'),
    ('OVERPAYMENT_SAL',          '2-99-99-993-17'),
    ('OVERPAYMENT_PERA',         '2-99-99-993-18'),
    ('O/P_CHILD_ALLOW',          '2-99-99-993-21'),
    ('OP_PHIC_CS',               '2-02-01-040-13'),
    ('OP_HDMF_CS',               '2-02-01-030-16'),
    ('SMART/GLOBE/_MOB',         '2-99-99-993-12'),
    ('UMID_CASHADV',             '2-99-99-993-13'),
    ('COCOLIFE',                 '2-99-99-993-14'),
    ('PFAI_CNA_LOANS',           '2-02-01-150-20'),
    ('O/P_MYFA2018',             '2-99-99-993-22'),
    ('BPR_HDMF',                 '2-02-01-030-15'),
    ('CUE_EMERGENCY_LOAN',       '2-99-99-991-14'),
    ('O/P_ADDL_COMPENSATION',    '2-99-99-993-23'),
    ('PFAI_LRI_PREMIUM',         '2-02-01-150-19'),
    ('GSIS_SOCIAL_INS_CONS',     '2-02-01-020-20')
) AS m(item_code, account_code)
WHERE pi.item_code = m.item_code;

-- Sanity check: any payroll item still unmapped (should return 0 rows).
-- SELECT item_code FROM fims.payroll_items WHERE account_code IS NULL;
