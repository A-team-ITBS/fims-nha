-- ============================================================================
-- Payroll Items setup — registers the 108 amount columns found in the
-- payroll import form into fims.payroll_items (the Payroll Setup catalogue),
-- so the import stops rejecting files that carry amounts in these columns.
--
-- item_code below matches the import form's column header EXACTLY (including
-- apostrophes and slashes) because the importer matches columns by that code.
-- account_code is not set here: 20 - chart_of_accounts_payroll_items_alignment.sql
-- (run right after this file) adds the sub-ledger accounts and points every item
-- at its account. Accounting can still change it from the Payroll Setup page.
-- Re-running refreshes name / category / order / status (ON CONFLICT DO UPDATE).
--
-- category assignment is a first-pass best guess based on the item's name:
--   EARNING         -> adds to gross pay (basic pay, allowances, bonuses)
--   DEDUCTION       -> withheld from the employee (loans, premiums, taxes,
--                      overpayment recoupment, union dues, payables)
--   REFUND          -> returned to the employee, adds to net pay
--   EMPLOYER_SHARE  -> NHA's/government's counterpart share, not part of net pay
-- Accounting should review and correct any row before go-live, then attach
-- the real account_code (UPDATE fims.payroll_items SET account_code = ...
-- WHERE item_code = ...).
-- ============================================================================

INSERT INTO fims.payroll_items (item_code, item_name, category, sort_order, status)
VALUES
    -- ── EARNING ──────────────────────────────────────────────────────────
    ('BASIC_SALARY',                 'Basic Salary',                                   'EARNING', 10, 'ACTIVE'),
    ('PERA',                         'Personnel Economic Relief Allowance (PERA)',     'EARNING', 20, 'ACTIVE'),
    ('REPRESENTATION_ALLOWANCE',     'Representation Allowance (RA)',                  'EARNING', 30, 'ACTIVE'),
    ('TRANSPORTATION_ALLOWANCE',     'Transportation Allowance (TA)',                  'EARNING', 40, 'ACTIVE'),
    ('MEAL_SUBSIDY',                 'Meal Subsidy',                                   'EARNING', 50, 'ACTIVE'),
    ('RICE_SUBSIDY',                 'Rice Subsidy',                                   'EARNING', 60, 'ACTIVE'),
    ('CHILDREN''S_ALLOWANCE',        'Children''s Allowance',                          'EARNING', 70, 'ACTIVE'),
    ('INCENTIVE_DESIGNATION',        'Incentive Designation Pay',                      'EARNING', 80, 'ACTIVE'),
    ('STEP_INCREMENT',               'Step Increment',                                 'EARNING', 90, 'ACTIVE'),
    ('CHILDREN''S_ALLOW_ADJ',        'Children''s Allowance Adjustment',               'EARNING', 100, 'ACTIVE'),
    ('MYFA_2017',                    'Monetization of Yearly Fringe Allowance 2017',   'EARNING', 110, 'ACTIVE'),
    ('OTHERS_PERA',                  'Others — PERA',                                  'EARNING', 120, 'ACTIVE'),
    ('SALARY_DIFFERENTIAL',          'Salary Differential',                            'EARNING', 130, 'ACTIVE'),
    ('UNIFORM',                      'Uniform/Clothing Allowance',                     'EARNING', 140, 'ACTIVE'),
    ('MEDICAL',                      'Medical Allowance',                              'EARNING', 150, 'ACTIVE'),
    ('MIDYEAR',                      'Mid-Year Bonus',                                 'EARNING', 160, 'ACTIVE'),
    ('YEAR_END',                     'Year-End Bonus',                                 'EARNING', 170, 'ACTIVE'),
    ('CASH_GIFT',                    'Cash Gift',                                      'EARNING', 180, 'ACTIVE'),
    ('3K',                           '3K Incentive',                                   'EARNING', 190, 'ACTIVE'),
    ('50K',                          '50K Incentive',                                  'EARNING', 200, 'ACTIVE'),
    ('SUPP_CLOTHING ALLOW',          'Supplementary Clothing Allowance',               'EARNING', 210, 'ACTIVE'),
    ('LOYALTY',                      'Loyalty Award',                                  'EARNING', 220, 'ACTIVE'),

    -- ── REFUND ───────────────────────────────────────────────────────────
    ('REFUND_GSIS_CONSO',            'Refund — GSIS Consolidated Loan',                'REFUND', 300, 'ACTIVE'),
    ('REFUND_GSIS_EAL',              'Refund — GSIS Emergency Loan (EAL)',             'REFUND', 310, 'ACTIVE'),
    ('REFUND_GSIS_ENH-CONSO',        'Refund — GSIS Enhanced Consolidated Loan',       'REFUND', 320, 'ACTIVE'),
    ('REFUND_GSIS_PL',                'Refund — GSIS Policy Loan',                      'REFUND', 330, 'ACTIVE'),
    ('OTHERS_LWOP_REFUND',           'Others — Leave Without Pay Refund',              'REFUND', 340, 'ACTIVE'),
    ('REFUND_PHILHEALTH',            'Refund — PhilHealth',                            'REFUND', 350, 'ACTIVE'),
    ('REFUND_GSIS_SIC',              'Refund — GSIS Social Insurance Contribution',    'REFUND', 360, 'ACTIVE'),
    ('REFUND_EXP_W/TAX',             'Refund — Expanded Withholding Tax',              'REFUND', 370, 'ACTIVE'),
    ('REFUND_PERCENTAGE_TAX',        'Refund — Percentage Tax',                        'REFUND', 380, 'ACTIVE'),
    ('REFUND_GSIS_EL',               'Refund — GSIS Educational Loan',                 'REFUND', 390, 'ACTIVE'),
    ('REFUND_OPT',                   'Refund — GSIS Optional Insurance',               'REFUND', 400, 'ACTIVE'),
    ('REFUND_PFAI',                  'Refund — PFAI',                                  'REFUND', 410, 'ACTIVE'),
    ('REFUND_REPRESENTATION',        'Refund — Representation Allowance',              'REFUND', 420, 'ACTIVE'),
    ('REFUND_TRANSPORTATION',        'Refund — Transportation Allowance',              'REFUND', 430, 'ACTIVE'),
    ('TAX_REFUND',                   'Tax Refund',                                     'REFUND', 440, 'ACTIVE'),
    ('2016_CNA_IN_REF',              '2016 CNA Incentive Refund',                      'REFUND', 450, 'ACTIVE'),

    -- ── EMPLOYER_SHARE ───────────────────────────────────────────────────
    ('CORP/SHARE_PAGIBIG_CONT',      'Corporate Share — Pag-IBIG Contribution',        'EMPLOYER_SHARE', 500, 'ACTIVE'),
    ('CORP/SHARE_PHILHEALTH',        'Corporate Share — PhilHealth',                   'EMPLOYER_SHARE', 510, 'ACTIVE'),
    ('CORP/SHARE_PFAI',              'Corporate Share — PFAI',                         'EMPLOYER_SHARE', 520, 'ACTIVE'),
    ('CORP/SHARE_GSIS',              'Corporate Share — GSIS',                         'EMPLOYER_SHARE', 530, 'ACTIVE'),
    ('BPR_CORP/SHARE_GSIS',          'Backpremium — Corporate Share GSIS',             'EMPLOYER_SHARE', 540, 'ACTIVE'),
    ('CORP/SHARE_STATEINS',          'Corporate Share — State Insurance',              'EMPLOYER_SHARE', 550, 'ACTIVE'),
    ('BPR_CORP/SHARE_PFAI',          'Backpremium — Corporate Share PFAI',             'EMPLOYER_SHARE', 560, 'ACTIVE'),

    -- ── DEDUCTION ────────────────────────────────────────────────────────
    ('CASHIER''S',                   'Cashier''s Bond/Shortage',                       'DEDUCTION', 600, 'ACTIVE'),
    ('CU_FIX_DEPOSIT',               'Credit Union — Fixed Deposit',                   'DEDUCTION', 610, 'ACTIVE'),
    ('CU_LOAN_RECEIVABLE',           'Credit Union — Loan Receivable',                 'DEDUCTION', 620, 'ACTIVE'),
    ('GSIS_CONSOLIDATED_LOAN',       'GSIS Consolidated Loan',                         'DEDUCTION', 630, 'ACTIVE'),
    ('ECCAI_ACC_RECEIVABLE',         'ECCAI Accounts Receivable',                      'DEDUCTION', 640, 'ACTIVE'),
    ('ECCAI_APPLIANCE_LOAN',         'ECCAI Appliance Loan',                           'DEDUCTION', 650, 'ACTIVE'),
    ('GSIS/SIC',                     'GSIS Social Insurance Contribution',             'DEDUCTION', 660, 'ACTIVE'),
    ('GSIS_CEAP',                    'GSIS CEAP',                                      'DEDUCTION', 670, 'ACTIVE'),
    ('GSIS_EDUC_ASSIST_LOAN',        'GSIS Educational Assistance Loan',               'DEDUCTION', 680, 'ACTIVE'),
    ('GSIS_REALESTATE_LOAN',         'GSIS Real Estate Loan',                          'DEDUCTION', 690, 'ACTIVE'),
    ('GSIS_EMERGENCY_LOAN',          'GSIS Emergency Loan',                            'DEDUCTION', 700, 'ACTIVE'),
    ('GSIS_OPTIONAL_INSURANCE',      'GSIS Optional Insurance',                        'DEDUCTION', 710, 'ACTIVE'),
    ('PFAI',                         'PFAI Contribution',                              'DEDUCTION', 720, 'ACTIVE'),
    ('PFAI_CBL',                     'PFAI Cash/Consumer Benefit Loan (CBL)',          'DEDUCTION', 730, 'ACTIVE'),
    ('PFAI_MPL/EL4',                 'PFAI Multi-Purpose Loan / EL4',                  'DEDUCTION', 740, 'ACTIVE'),
    ('PFAI_SBL',                     'PFAI Salary/Special Benefit Loan (SBL)',         'DEDUCTION', 750, 'ACTIVE'),
    ('PFAI_WELFARE_FUND',            'PFAI Welfare Fund',                              'DEDUCTION', 760, 'ACTIVE'),
    ('UNDERTIME_W/O_PAY',            'Undertime Without Pay',                          'DEDUCTION', 770, 'ACTIVE'),
    ('UNION_DUES/AGENCY_FEE',        'Union Dues / Agency Fee',                        'DEDUCTION', 780, 'ACTIVE'),
    ('WITHHOLDING_TAX',              'Withholding Tax',                                'DEDUCTION', 790, 'ACTIVE'),
    ('OTHER_PAYABLES',               'Other Payables',                                 'DEDUCTION', 800, 'ACTIVE'),
    ('PHILHEALTH',                   'PhilHealth Contribution',                        'DEDUCTION', 810, 'ACTIVE'),
    ('ACQUIRED_ASSETS',              'Acquired Assets',                                'DEDUCTION', 820, 'ACTIVE'),
    ('HDMF',                         'HDMF (Pag-IBIG) Contribution',                   'DEDUCTION', 830, 'ACTIVE'),
    ('HDMF_CALAMITY_LOAN',           'HDMF Calamity Loan',                             'DEDUCTION', 840, 'ACTIVE'),
    ('HDMF_MODIFIED_PAGIBIG_II',     'HDMF Modified Pag-IBIG II',                      'DEDUCTION', 850, 'ACTIVE'),
    ('HDMF_PAGIBIG_MPL',             'HDMF Pag-IBIG Multi-Purpose Loan',               'DEDUCTION', 860, 'ACTIVE'),
    ('LEAVE_W/O_PAY',                'Leave Without Pay',                              'DEDUCTION', 870, 'ACTIVE'),
    ('NHA_CAR_LOAN',                 'NHA Car Loan',                                   'DEDUCTION', 880, 'ACTIVE'),
    ('NHA_HOUSE_REPAIR_LOAN',        'NHA House Repair Loan',                          'DEDUCTION', 890, 'ACTIVE'),
    ('NHA_HOUSING_LOAN',             'NHA Housing Loan',                               'DEDUCTION', 900, 'ACTIVE'),
    ('NHMFC_HOUSING_LOAN',           'NHMFC Housing Loan',                             'DEDUCTION', 910, 'ACTIVE'),
    ('O/P_TRANS_ALLOWANCE',          'Overpayment — Transportation Allowance',         'DEDUCTION', 920, 'ACTIVE'),
    ('GSIS_REG_POLICY_LOAN',         'GSIS Regular Policy Loan',                       'DEDUCTION', 930, 'ACTIVE'),
    ('GSIS_OPTIONAL_POLICY_LOAN',    'GSIS Optional Policy Loan',                       'DEDUCTION', 940, 'ACTIVE'),
    ('PFAI_HEALTHCARE',              'PFAI Healthcare',                                'DEDUCTION', 950, 'ACTIVE'),
    ('O/P_REP_ALLOW',                'Overpayment — Representation Allowance',         'DEDUCTION', 960, 'ACTIVE'),
    ('CASH_ACCOUNTABILITIES',        'Cash Accountabilities',                         'DEDUCTION', 970, 'ACTIVE'),
    ('ENHANCED_CONSO_LOAN',          'Enhanced Consolidated Loan',                     'DEDUCTION', 980, 'ACTIVE'),
    ('PFAI_EL',                      'PFAI Emergency Loan',                            'DEDUCTION', 990, 'ACTIVE'),
    ('MRI_HOUSING',                  'MRI Housing',                                    'DEDUCTION', 1000, 'ACTIVE'),
    ('PFAI_SPL',                     'PFAI Special Loan',                              'DEDUCTION', 1010, 'ACTIVE'),
    ('PFAI_COMAKER',                 'PFAI Co-Maker Loan',                             'DEDUCTION', 1020, 'ACTIVE'),
    ('BACKPAYMENT_W/HOLDING',        'Backpayment — Withholding Tax',                  'DEDUCTION', 1030, 'ACTIVE'),
    ('BACKPREMIUM_PHILHEALTH',       'Backpremium — PhilHealth',                       'DEDUCTION', 1040, 'ACTIVE'),
    ('BACKPREMIUM_GSIS',             'Backpremium — GSIS',                             'DEDUCTION', 1050, 'ACTIVE'),
    ('BPR_PFAI',                     'Backpremium — PFAI',                             'DEDUCTION', 1060, 'ACTIVE'),
    ('BPR_PHILHEALTH',               'Backpremium — PhilHealth (BPR)',                 'DEDUCTION', 1070, 'ACTIVE'),
    ('OVERPAYMENT_SAL',              'Overpayment — Salary',                           'DEDUCTION', 1080, 'ACTIVE'),
    ('OVERPAYMENT_PERA',             'Overpayment — PERA',                             'DEDUCTION', 1090, 'ACTIVE'),
    ('O/P_CHILD_ALLOW',              'Overpayment — Children''s Allowance',            'DEDUCTION', 1100, 'ACTIVE'),
    ('OP_PHIC_CS',                   'Overpayment — PhilHealth Corporate Share',       'DEDUCTION', 1110, 'ACTIVE'),
    ('OP_HDMF_CS',                   'Overpayment — HDMF Corporate Share',             'DEDUCTION', 1120, 'ACTIVE'),
    ('SMART/GLOBE/_MOB',             'Smart/Globe Mobile Plan',                        'DEDUCTION', 1130, 'ACTIVE'),
    ('UMID_CASHADV',                 'UMID Cash Advance',                              'DEDUCTION', 1140, 'ACTIVE'),
    ('COCOLIFE',                     'Cocolife Premium',                               'DEDUCTION', 1150, 'ACTIVE'),
    ('PFAI_CNA_LOANS',               'PFAI CNA Loans',                                 'DEDUCTION', 1160, 'ACTIVE'),
    ('O/P_MYFA2018',                 'Overpayment — MYFA 2018',                        'DEDUCTION', 1170, 'ACTIVE'),
    ('BPR_HDMF',                     'Backpremium — HDMF',                             'DEDUCTION', 1180, 'ACTIVE'),
    ('CUE_EMERGENCY_LOAN',           'CUE Emergency Loan',                             'DEDUCTION', 1190, 'ACTIVE'),
    ('O/P_ADDL_COMPENSATION',        'Overpayment — Additional Compensation',          'DEDUCTION', 1200, 'ACTIVE'),
    ('PFAI_LRI_PREMIUM',             'PFAI LRI Premium',                               'DEDUCTION', 1210, 'ACTIVE'),
    ('GSIS_SOCIAL_INS_CONS',         'GSIS Social Insurance Consolidated',             'DEDUCTION', 1220, 'ACTIVE')
ON CONFLICT (item_code) DO UPDATE SET
    item_name  = EXCLUDED.item_name,
    category   = EXCLUDED.category,
    sort_order = EXCLUDED.sort_order,
    status     = EXCLUDED.status,
    updated_at = CURRENT_TIMESTAMP;

-- ── Tax treatment for the year-end annualization (fims.payroll_items.tax_treatment — see 01 - table/48) ──
-- Following the old ACCOUNTING annualization (PayrollService::taxComponents()): PERA exempt; rice / uniform de minimis;
-- 13th month, mid-year / year-end bonus, cash gift, loyalty and the incentives under the ₱90,000 ceiling; GSIS,
-- PhilHealth, Pag-IBIG (and their back premiums) and union dues as non-taxable contributions; everything else paid as
-- taxable. Loans, payables, PFAI, credit-union items and employer shares are NONE (the column's default).
-- Accounting can change any item on the Payroll Setup page.
UPDATE fims.payroll_items pi
SET tax_treatment = m.tax_treatment
FROM (VALUES
    -- EARNING
    ('BASIC_SALARY', 'TAXABLE'), ('PERA', 'NON_TAXABLE'), ('REPRESENTATION_ALLOWANCE', 'TAXABLE'), ('TRANSPORTATION_ALLOWANCE', 'TAXABLE'),
    ('MEAL_SUBSIDY', 'TAXABLE'), ('RICE_SUBSIDY', 'DE_MINIMIS_RICE'), ('CHILDREN''S_ALLOWANCE', 'TAXABLE'), ('INCENTIVE_DESIGNATION', 'TAXABLE'),
    ('STEP_INCREMENT', 'TAXABLE'), ('CHILDREN''S_ALLOW_ADJ', 'TAXABLE'), ('MYFA_2017', 'BENEFIT_90K'), ('OTHERS_PERA', 'NON_TAXABLE'),
    ('SALARY_DIFFERENTIAL', 'TAXABLE'), ('UNIFORM', 'DE_MINIMIS_UNIFORM'), ('MEDICAL', 'TAXABLE'), ('MIDYEAR', 'BENEFIT_90K'),
    ('YEAR_END', 'BENEFIT_90K'), ('CASH_GIFT', 'BENEFIT_90K'), ('3K', 'BENEFIT_90K'), ('50K', 'BENEFIT_90K'),
    ('SUPP_CLOTHING ALLOW', 'DE_MINIMIS_UNIFORM'), ('LOYALTY', 'BENEFIT_90K'),
    -- REFUND (pay given back → adds; a refunded contribution / tax → takes away)
    ('OTHERS_LWOP_REFUND', 'TAXABLE'), ('REFUND_PHILHEALTH', 'CONTRIBUTION'), ('REFUND_GSIS_SIC', 'CONTRIBUTION'),
    ('REFUND_REPRESENTATION', 'TAXABLE'), ('REFUND_TRANSPORTATION', 'TAXABLE'), ('TAX_REFUND', 'WITHHOLDING_TAX'),
    ('2016_CNA_IN_REF', 'BENEFIT_90K'),
    -- DEDUCTION (contributions / tax withheld → add; pay taken back → takes away)
    ('GSIS/SIC', 'CONTRIBUTION'), ('GSIS_SOCIAL_INS_CONS', 'CONTRIBUTION'), ('PHILHEALTH', 'CONTRIBUTION'), ('HDMF', 'CONTRIBUTION'),
    ('BACKPREMIUM_GSIS', 'CONTRIBUTION'), ('BACKPREMIUM_PHILHEALTH', 'CONTRIBUTION'), ('BPR_PHILHEALTH', 'CONTRIBUTION'), ('BPR_HDMF', 'CONTRIBUTION'),
    ('UNION_DUES/AGENCY_FEE', 'CONTRIBUTION'),
    ('WITHHOLDING_TAX', 'WITHHOLDING_TAX'), ('BACKPAYMENT_W/HOLDING', 'WITHHOLDING_TAX'),
    ('UNDERTIME_W/O_PAY', 'TAXABLE'), ('LEAVE_W/O_PAY', 'TAXABLE'), ('OVERPAYMENT_SAL', 'TAXABLE'), ('OVERPAYMENT_PERA', 'NON_TAXABLE'),
    ('O/P_TRANS_ALLOWANCE', 'TAXABLE'), ('O/P_REP_ALLOW', 'TAXABLE'), ('O/P_CHILD_ALLOW', 'TAXABLE'), ('O/P_ADDL_COMPENSATION', 'TAXABLE'),
    ('O/P_MYFA2018', 'BENEFIT_90K')
) AS m(item_code, tax_treatment)
WHERE pi.item_code = m.item_code;

-- Sanity check: should return 108 rows affected across all categories.
-- SELECT category, COUNT(*) FROM fims.payroll_items GROUP BY category ORDER BY category;
