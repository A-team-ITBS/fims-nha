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
-- NOTE: sl_code and sl_detail_id are kept for backward compatibility but are now populated
-- from the Chart of Accounts hierarchy (no separate SL Masters/Details tables).
CREATE OR REPLACE VIEW fims.v_journals AS
SELECT
    g.gl_id,
    g.posting_date,
    j.jev_no,
    d.dv_no,
    g.account_code,
    c.account_title,
    g.sl_code,
    NULL::VARCHAR(100) AS sl_name,
    g.sl_detail_id,
    NULL::VARCHAR(255) AS sl_detail_name,
    g.rc_id,
    g.debit,
    g.credit,
    g.reference_no,
    g.description,
    g.reversed_gl_id,
    g.fiscal_yr,
    g.book_id,
    b.name AS book_name,
    g.source_module,
    g.doc_ref_no
FROM fims.general_ledger g
JOIN fims.journal_entry_vouchers j ON j.jev_id = g.jev_id
LEFT JOIN fims.disbursement_vouchers d ON d.dv_id = j.dv_id
JOIN fims.chart_of_accounts c ON c.account_code = g.account_code
LEFT JOIN fims.books b ON b.book_id = g.book_id
ORDER BY g.posting_date, g.gl_id;

COMMENT ON VIEW fims.v_journals IS 'General Journal — one row per posted fims.general_ledger line, with its JEV/DV/account/book labels joined in. d/dv_no is LEFT JOINed (not every JEV has a dv_id — see "03 - alter_table/33": standalone adjusting/closing entries have none). SL data is now from Chart of Accounts hierarchy. Read-only report, nothing writes to this.';

-- Trial Balance — one row per account per fiscal year per book (fims.books, "01 - table/41"), as
-- of whatever has been posted so far. Grouped finer than "per account per fiscal year" (also by
-- book_id) so a single book can be isolated with a plain WHERE; an "all books" total re-aggregates
-- this view across book_id (see GeneralLedgerService::fetchTrialBalance()) rather than this view
-- pre-summing books together, which would make per-book filtering impossible downstream.
--
-- Ending Balance (what this view reports) = Beginning Balance (from fims.opening_balances, if
-- any) + net GL activity for that fiscal_yr/book. A fresh account with a beginning balance and no
-- other postings still shows that same figure as both its beginning and ending balance.
-- Backward compatible with years that used the old OPENING-classified-JEV method (no row in
-- fims.opening_balances — COALESCEs to 0, and that JEV's effect is already included in the GL
-- aggregate instead).
--
-- NOTE: this definition must stay in sync with "03 - alter_table/56 -
-- v_trial_balance_include_opening_balances.sql", which runs earlier in folder order and would
-- otherwise be silently overwritten by this one.
CREATE OR REPLACE VIEW fims.v_trial_balance AS
WITH gl_agg AS (
    SELECT fiscal_yr, book_id, account_code,
           SUM(debit) AS debit,
           SUM(credit) AS credit
    FROM fims.general_ledger
    GROUP BY fiscal_yr, book_id, account_code
)
SELECT
    COALESCE(g.fiscal_yr, ob.fiscal_yr) AS fiscal_yr,
    COALESCE(g.book_id, ob.book_id) AS book_id,
    b.name AS book_name,
    COALESCE(g.account_code, ob.account_code) AS account_code,
    c.account_id,
    c.account_title,
    c.account_type,
    COALESCE(g.debit, 0) + COALESCE(ob.debit, 0) AS total_debit,
    COALESCE(g.credit, 0) + COALESCE(ob.credit, 0) AS total_credit,
    (COALESCE(g.debit, 0) + COALESCE(ob.debit, 0)) - (COALESCE(g.credit, 0) + COALESCE(ob.credit, 0)) AS balance
FROM gl_agg g
FULL OUTER JOIN fims.opening_balances ob
    ON ob.fiscal_yr = g.fiscal_yr AND ob.book_id = g.book_id AND ob.account_code = g.account_code
JOIN fims.chart_of_accounts c ON c.account_code = COALESCE(g.account_code, ob.account_code)
LEFT JOIN fims.books b ON b.book_id = COALESCE(g.book_id, ob.book_id);

COMMENT ON VIEW fims.v_trial_balance IS 'Trial Balance — fims.general_ledger grouped by fiscal year + book + account, folded with fims.opening_balances beginning balances. balance = debit - credit; a balanced ledger sums to 0 across every account for a given fiscal year/book.';
