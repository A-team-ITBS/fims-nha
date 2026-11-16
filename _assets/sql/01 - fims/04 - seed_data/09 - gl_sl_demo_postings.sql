-- ============================================================================
-- GL/SL demo postings — two more posted Journal Entry Vouchers so General
-- Ledger / Subsidiary Ledger / Journals / Trial Balance have more than the
-- single DV-processing demo JEV (JEV-26-11-0001, "01 - fims/01 - table/16..19")
-- to show. Inserted directly into fims.general_ledger the same way the rest
-- of this folder seeds already-processed records — these are meant to look
-- like Accounting already finished processing them (status FOR_TREASURY on
-- the DV, POSTED on the JEV), same as GeneralLedgerService::postJev() would
-- leave them, just without going through the service.
--
-- GLDEMO-0001 exercises the Asset/Revenue side (Cash in Bank debit against
-- Permit/Registration Fee revenue credits) and the previously-unused
-- SL-CASH-MDS01 subsidiary ledger (see "01 - table/23 - fims_sl_masters.sql").
-- GLDEMO-0002 exercises a direct cash disbursement (Expense debit against
-- that same Cash-in-Bank SL, credit side this time) so SL-CASH-MDS01 shows a
-- running balance across both a deposit and a withdrawal, and so Trial
-- Balance/Journals have a second, non-AP-vendor JEV to group.
--
-- Idempotent: every insert is guarded by WHERE/AND NOT EXISTS on the unique
-- dv_no/jev_no (and, for lines/GL, on jev_id + line_number / jev_line_id), so
-- re-running this file after the first install is a no-op.
-- ============================================================================

-- ── GLDEMO-0001 — Collection of permit/registration fees, deposited to the MDS current account ──
INSERT INTO fims.disbursement_vouchers (
    dv_no, dv_date, fiscal_yr, expense_class, dept_project, claimant_name,
    particulars, gross_amount, account_code, budget_amount, obligation_amount, balance_amount,
    accounting_processed_by, accounting_processed_at, updated_by, status_id
)
SELECT
    'GLDEMO-0001', CURRENT_DATE, 2026, 'OTHER', 'Finance Management Department (FMD)',
    'Various Permit & Registration Applicants',
    'Demo: Collection of permit and registration fees, deposited to the MDS current account.',
    45000.00, '1-01-02-020', 0, 0, 0,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'), CURRENT_TIMESTAMP,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT id FROM fims.settings_status WHERE status_for = 'disbursement_voucher' AND name = 'FOR_TREASURY')
WHERE NOT EXISTS (SELECT 1 FROM fims.disbursement_vouchers WHERE dv_no = 'GLDEMO-0001');

INSERT INTO fims.journal_entry_vouchers (
    jev_no, jev_date, dv_id, fiscal_yr, particulars, total_debit, total_credit,
    created_by, updated_by, status_id, posted_by, posted_at
)
SELECT
    'JEV-GLDEMO-0001', CURRENT_DATE, dv.dv_id, 2026,
    'Demo: Collection of permit and registration fees, deposited to the MDS current account.',
    45000.00, 45000.00,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT id FROM fims.settings_status WHERE status_for = 'journal_entry_voucher' AND name = 'POSTED'),
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'), CURRENT_TIMESTAMP
FROM fims.disbursement_vouchers dv
WHERE dv.dv_no = 'GLDEMO-0001'
  AND NOT EXISTS (SELECT 1 FROM fims.journal_entry_vouchers WHERE jev_no = 'JEV-GLDEMO-0001');

INSERT INTO fims.journal_entry_voucher_lines (jev_id, line_number, account_code, debit, credit, description, sl_code)
SELECT jev.jev_id, l.line_number, l.account_code, l.debit, l.credit, l.description, l.sl_code
FROM fims.journal_entry_vouchers jev
JOIN (VALUES
    (1, '1-01-02-020', 45000.00::numeric(18,2), 0.00::numeric(18,2),    'Collections deposited to MDS current account', 'SL-CASH-MDS01'),
    (2, '4-02-01-010', 0.00::numeric(18,2),    20000.00::numeric(18,2), 'Permit fee collections', NULL),
    (3, '4-02-01-020', 0.00::numeric(18,2),    25000.00::numeric(18,2), 'Registration fee collections', NULL)
) AS l(line_number, account_code, debit, credit, description, sl_code)
    ON TRUE
WHERE jev.jev_no = 'JEV-GLDEMO-0001'
  AND NOT EXISTS (
    SELECT 1 FROM fims.journal_entry_voucher_lines jevl
    WHERE jevl.jev_id = jev.jev_id AND jevl.line_number = l.line_number
  );

INSERT INTO fims.general_ledger (jev_id, jev_line_id, account_code, sl_code, fiscal_yr, posting_date, debit, credit, reference_no, description, posted_by)
SELECT jevl.jev_id, jevl.jevl_id, jevl.account_code, jevl.sl_code, jev.fiscal_yr, CURRENT_DATE, jevl.debit, jevl.credit, jev.jev_no, jevl.description,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer')
FROM fims.journal_entry_voucher_lines jevl
JOIN fims.journal_entry_vouchers jev ON jev.jev_id = jevl.jev_id
WHERE jev.jev_no = 'JEV-GLDEMO-0001'
  AND NOT EXISTS (SELECT 1 FROM fims.general_ledger gl WHERE gl.jev_line_id = jevl.jevl_id AND gl.reversed_gl_id IS NULL);

-- ── GLDEMO-0002 — Direct cash purchase of office supplies via MDS check (no Accounts Payable) ──
INSERT INTO fims.disbursement_vouchers (
    dv_no, dv_date, fiscal_yr, expense_class, dept_project, vendor_id, claimant_name,
    particulars, gross_amount, account_code, budget_amount, obligation_amount, balance_amount,
    accounting_processed_by, accounting_processed_at, updated_by, status_id
)
SELECT
    'GLDEMO-0002', CURRENT_DATE, 2026, 'MOOE', 'General Services Department (GSD)',
    (SELECT vendor_id FROM fims.vendors WHERE vendor_name = 'Metro Office Supplies Corp.'),
    'Metro Office Supplies Corp.',
    'Demo: Payment for office supplies, paid directly via MDS check (no Accounts Payable).',
    12500.00, '5-02-03-010', 12500.00, 12500.00, 0,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'), CURRENT_TIMESTAMP,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT id FROM fims.settings_status WHERE status_for = 'disbursement_voucher' AND name = 'FOR_TREASURY')
WHERE NOT EXISTS (SELECT 1 FROM fims.disbursement_vouchers WHERE dv_no = 'GLDEMO-0002');

INSERT INTO fims.journal_entry_vouchers (
    jev_no, jev_date, dv_id, fiscal_yr, particulars, total_debit, total_credit,
    created_by, updated_by, status_id, posted_by, posted_at
)
SELECT
    'JEV-GLDEMO-0002', CURRENT_DATE, dv.dv_id, 2026,
    'Demo: Payment for office supplies, paid directly via MDS check (no Accounts Payable).',
    12500.00, 12500.00,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT id FROM fims.settings_status WHERE status_for = 'journal_entry_voucher' AND name = 'POSTED'),
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'), CURRENT_TIMESTAMP
FROM fims.disbursement_vouchers dv
WHERE dv.dv_no = 'GLDEMO-0002'
  AND NOT EXISTS (SELECT 1 FROM fims.journal_entry_vouchers WHERE jev_no = 'JEV-GLDEMO-0002');

INSERT INTO fims.journal_entry_voucher_lines (jev_id, line_number, account_code, debit, credit, description, sl_code)
SELECT jev.jev_id, l.line_number, l.account_code, l.debit, l.credit, l.description, l.sl_code
FROM fims.journal_entry_vouchers jev
JOIN (VALUES
    (1, '5-02-03-010', 12500.00::numeric(18,2), 0.00::numeric(18,2),   'Office supplies purchased', NULL),
    (2, '1-01-02-020', 0.00::numeric(18,2),     12500.00::numeric(18,2), 'Payment via MDS check', 'SL-CASH-MDS01')
) AS l(line_number, account_code, debit, credit, description, sl_code)
    ON TRUE
WHERE jev.jev_no = 'JEV-GLDEMO-0002'
  AND NOT EXISTS (
    SELECT 1 FROM fims.journal_entry_voucher_lines jevl
    WHERE jevl.jev_id = jev.jev_id AND jevl.line_number = l.line_number
  );

INSERT INTO fims.general_ledger (jev_id, jev_line_id, account_code, sl_code, fiscal_yr, posting_date, debit, credit, reference_no, description, posted_by)
SELECT jevl.jev_id, jevl.jevl_id, jevl.account_code, jevl.sl_code, jev.fiscal_yr, CURRENT_DATE, jevl.debit, jevl.credit, jev.jev_no, jevl.description,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer')
FROM fims.journal_entry_voucher_lines jevl
JOIN fims.journal_entry_vouchers jev ON jev.jev_id = jevl.jev_id
WHERE jev.jev_no = 'JEV-GLDEMO-0002'
  AND NOT EXISTS (SELECT 1 FROM fims.general_ledger gl WHERE gl.jev_line_id = jevl.jevl_id AND gl.reversed_gl_id IS NULL);
