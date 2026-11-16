-- ============================================================================
-- CKDJ / ADADJ / CRDJ demo data — backs the Check Disbursements Journal,
-- Advice to Debit Account Disbursement Journal and Cash Receipts Journal
-- tabs on the Journals page (fims.checks + fims.ada_releases +
-- fims.official_receipts/official_receipt_lines — see
-- "01 - table/39 - fims_ada_releases.sql" and "40 - fims_official_receipts.sql").
--
-- GLDEMO-0003 is a third demo DV/JEV (alongside GLDEMO-0001/0002 from
-- "09 - gl_sl_demo_postings.sql") paid via ADA instead of a check, so
-- ADADJ has its own posted entry to join against (same SL-CASH-MDS01
-- credit side GLDEMO-0002 already exercises).
--
-- Idempotent: every insert is guarded by WHERE/AND NOT EXISTS on a natural
-- key (account_number, booklet series, check number, dv_no, ada_no, or_no),
-- so re-running this file after the first install is a no-op.
-- ============================================================================

-- ── Bank account + checkbook for the CKDJ/ADADJ demo rows ──
-- account_code (1-01-02-020, Cash in Bank-Local Currency, Current Account) is what
-- GeneralLedgerService::postPaymentJev() credits when a check/ADA against this bank account is
-- released — see "03 - alter_table/31".
INSERT INTO fims.bank_accounts (account_name, account_number, bank_name, office_type, is_active, account_code)
SELECT 'MDS Checking Account', '0987-6543-21', 'Land Bank of the Philippines', 'main', true, '1-01-02-020'
WHERE NOT EXISTS (SELECT 1 FROM fims.bank_accounts WHERE account_number = '0987-6543-21');

INSERT INTO fims.check_booklets (bank_account_id, series, fiscal_year, start_check_no, end_check_no, date_received)
SELECT ba.bank_account_id, 'GLDEMO-SERIES-01', 2026, '0001001', '0001050', CURRENT_DATE
FROM fims.bank_accounts ba
WHERE ba.account_number = '0987-6543-21'
  AND NOT EXISTS (SELECT 1 FROM fims.check_booklets WHERE series = 'GLDEMO-SERIES-01');

-- ── CKDJ — check issued against GLDEMO-0002 (office supplies, paid via MDS check) ──
INSERT INTO fims.checks (bank_account_id, booklet_id, check_number, check_date, dv_number, payee, amount, status)
SELECT ba.bank_account_id, cb.booklet_id, '0001001', CURRENT_DATE, 'GLDEMO-0002',
       'Metro Office Supplies Corp.', 12500.00, 'RELEASED'
FROM fims.bank_accounts ba
JOIN fims.check_booklets cb ON cb.bank_account_id = ba.bank_account_id AND cb.series = 'GLDEMO-SERIES-01'
WHERE ba.account_number = '0987-6543-21'
  AND NOT EXISTS (SELECT 1 FROM fims.checks WHERE bank_account_id = ba.bank_account_id AND check_number = '0001001');

-- ── GLDEMO-0003 — payment to NHA Builders & Construction Inc., released via ADA instead of a check ──
INSERT INTO fims.disbursement_vouchers (
    dv_no, dv_date, fiscal_yr, expense_class, dept_project, vendor_id, claimant_name,
    particulars, gross_amount, account_code, budget_amount, obligation_amount, balance_amount,
    accounting_processed_by, accounting_processed_at, updated_by, status_id
)
SELECT
    'GLDEMO-0003', CURRENT_DATE, 2026, 'MOOE', 'General Services Department (GSD)',
    (SELECT vendor_id FROM fims.vendors WHERE vendor_name = 'NHA Builders & Construction Inc.'),
    'NHA Builders & Construction Inc.',
    'Demo: Payment for building repairs and maintenance, released via Advice to Debit Account.',
    60000.00, '5-02-13-010', 60000.00, 60000.00, 0,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'), CURRENT_TIMESTAMP,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT id FROM fims.settings_status WHERE status_for = 'disbursement_voucher' AND name = 'FOR_TREASURY')
WHERE NOT EXISTS (SELECT 1 FROM fims.disbursement_vouchers WHERE dv_no = 'GLDEMO-0003');

INSERT INTO fims.journal_entry_vouchers (
    jev_no, jev_date, dv_id, fiscal_yr, particulars, total_debit, total_credit,
    created_by, updated_by, status_id, posted_by, posted_at
)
SELECT
    'JEV-GLDEMO-0003', CURRENT_DATE, dv.dv_id, 2026,
    'Demo: Payment for building repairs and maintenance, released via Advice to Debit Account.',
    60000.00, 60000.00,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'),
    (SELECT id FROM fims.settings_status WHERE status_for = 'journal_entry_voucher' AND name = 'POSTED'),
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer'), CURRENT_TIMESTAMP
FROM fims.disbursement_vouchers dv
WHERE dv.dv_no = 'GLDEMO-0003'
  AND NOT EXISTS (SELECT 1 FROM fims.journal_entry_vouchers WHERE jev_no = 'JEV-GLDEMO-0003');

INSERT INTO fims.journal_entry_voucher_lines (jev_id, line_number, account_code, debit, credit, description, sl_code)
SELECT jev.jev_id, l.line_number, l.account_code, l.debit, l.credit, l.description, l.sl_code
FROM fims.journal_entry_vouchers jev
JOIN (VALUES
    (1, '5-02-13-010', 60000.00::numeric(18,2), 0.00::numeric(18,2),    'Building repairs and maintenance', NULL),
    (2, '1-01-02-020', 0.00::numeric(18,2),     60000.00::numeric(18,2), 'Released via Advice to Debit Account', 'SL-CASH-MDS01')
) AS l(line_number, account_code, debit, credit, description, sl_code)
    ON TRUE
WHERE jev.jev_no = 'JEV-GLDEMO-0003'
  AND NOT EXISTS (
    SELECT 1 FROM fims.journal_entry_voucher_lines jevl
    WHERE jevl.jev_id = jev.jev_id AND jevl.line_number = l.line_number
  );

INSERT INTO fims.general_ledger (jev_id, jev_line_id, account_code, sl_code, fiscal_yr, posting_date, debit, credit, reference_no, description, posted_by)
SELECT jevl.jev_id, jevl.jevl_id, jevl.account_code, jevl.sl_code, jev.fiscal_yr, CURRENT_DATE, jevl.debit, jevl.credit, jev.jev_no, jevl.description,
    (SELECT user_id FROM uac.users WHERE username = 'accounting_officer')
FROM fims.journal_entry_voucher_lines jevl
JOIN fims.journal_entry_vouchers jev ON jev.jev_id = jevl.jev_id
WHERE jev.jev_no = 'JEV-GLDEMO-0003'
  AND NOT EXISTS (SELECT 1 FROM fims.general_ledger gl WHERE gl.jev_line_id = jevl.jevl_id AND gl.reversed_gl_id IS NULL);

-- ── ADADJ — ADA issued against GLDEMO-0003 ──
INSERT INTO fims.ada_releases (ada_no, ada_date, dv_number, bank_account_id, region, payee, amount, status)
SELECT 'ADA-GLDEMO-0001', CURRENT_DATE, 'GLDEMO-0003', ba.bank_account_id, 'NCR - Central Office',
       'NHA Builders & Construction Inc.', 60000.00, 'RELEASED'
FROM fims.bank_accounts ba
WHERE ba.account_number = '0987-6543-21'
  AND NOT EXISTS (SELECT 1 FROM fims.ada_releases WHERE ada_no = 'ADA-GLDEMO-0001');

-- ── CRDJ — collections pending deposit (the same permit/registration fees GLDEMO-0001 later
-- deposits to the bank; this OR represents the collecting officer's side before that deposit) ──
INSERT INTO fims.official_receipts (or_no, or_date, fiscal_yr, collecting_officer, payor_name, particulars, debit_account_code, total_amount)
SELECT 'OR-2026-0001', CURRENT_DATE, 2026, 'Maria Santos', 'Various Permit & Registration Applicants',
       'Collection of permit and registration fees from walk-in applicants, pending deposit.',
       '1-01-01-010', 45000.00
WHERE NOT EXISTS (SELECT 1 FROM fims.official_receipts WHERE or_no = 'OR-2026-0001');

INSERT INTO fims.official_receipt_lines (receipt_id, line_number, account_code, amount, description)
SELECT r.receipt_id, l.line_number, l.account_code, l.amount, l.description
FROM fims.official_receipts r
JOIN (VALUES
    (1, '4-02-01-010', 20000.00::numeric(18,2), 'Permit fee collections'),
    (2, '4-02-01-020', 25000.00::numeric(18,2), 'Registration fee collections')
) AS l(line_number, account_code, amount, description)
    ON TRUE
WHERE r.or_no = 'OR-2026-0001'
  AND NOT EXISTS (
    SELECT 1 FROM fims.official_receipt_lines orl
    WHERE orl.receipt_id = r.receipt_id AND orl.line_number = l.line_number
  );
