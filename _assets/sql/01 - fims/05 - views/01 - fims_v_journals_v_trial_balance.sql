-- ============================================================================
-- Reporting views over fims.general_ledger ("01 - table/38"). Deliberately
-- views, not materialized/stored tables — a Trial Balance or Journals that
-- drifted out of sync with the GL would be worse than one that's slightly
-- slower to compute; GeneralLedgerService never needs a separate "recompute"
-- step. Runs after "04 - seed_data" (folder-number order) purely because it's
-- the last-numbered fims folder today — these views don't depend on any
-- seeded data, only on the tables existing.
-- ============================================================================

-- General Journal — one row per posted GL line, book-of-original-entry order (oldest first).
CREATE OR REPLACE VIEW fims.v_journals AS
SELECT
    g.gl_id,
    g.posting_date,
    j.jev_no,
    d.dv_no,
    g.account_code,
    c.account_title,
    g.sl_code,
    sm.sl_name,
    g.sl_detail_id,
    sd.detail_name AS sl_detail_name,
    g.rc_id,
    g.debit,
    g.credit,
    g.reference_no,
    g.description,
    g.reversed_gl_id,
    g.fiscal_yr,
    g.book_id,
    b.name AS book_name
FROM fims.general_ledger g
JOIN fims.journal_entry_vouchers j ON j.jev_id = g.jev_id
JOIN fims.disbursement_vouchers d ON d.dv_id = j.dv_id
JOIN fims.chart_of_accounts c ON c.account_code = g.account_code
LEFT JOIN fims.sl_masters sm ON sm.sl_code = g.sl_code
LEFT JOIN fims.sl_details sd ON sd.sl_detail_id = g.sl_detail_id
LEFT JOIN fims.books b ON b.book_id = g.book_id
ORDER BY g.posting_date, g.gl_id;

COMMENT ON VIEW fims.v_journals IS 'General Journal — one row per posted fims.general_ledger line, with its JEV/DV/account/SL/book labels joined in. Read-only report, nothing writes to this.';

-- Trial Balance — one row per account per fiscal year per book (fims.books, "01 - table/41"), as
-- of whatever has been posted so far. Grouped finer than "per account per fiscal year" (also by
-- book_id) so a single book can be isolated with a plain WHERE; an "all books" total re-aggregates
-- this view across book_id (see GeneralLedgerService::fetchTrialBalance()) rather than this view
-- pre-summing books together, which would make per-book filtering impossible downstream.
CREATE OR REPLACE VIEW fims.v_trial_balance AS
SELECT
    g.fiscal_yr,
    g.book_id,
    b.name AS book_name,
    g.account_code,
    c.account_id,
    c.account_title,
    c.account_type,
    SUM(g.debit)  AS total_debit,
    SUM(g.credit) AS total_credit,
    SUM(g.debit) - SUM(g.credit) AS balance
FROM fims.general_ledger g
JOIN fims.chart_of_accounts c ON c.account_code = g.account_code
LEFT JOIN fims.books b ON b.book_id = g.book_id
GROUP BY g.fiscal_yr, g.book_id, b.name, g.account_code, c.account_id, c.account_title, c.account_type;

COMMENT ON VIEW fims.v_trial_balance IS 'Trial Balance — fims.general_ledger grouped by fiscal year + book + account. balance = debit - credit; a balanced ledger sums to 0 across every account for a given fiscal year/book.';
