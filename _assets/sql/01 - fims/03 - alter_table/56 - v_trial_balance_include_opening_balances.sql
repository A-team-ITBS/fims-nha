-- ============================================================================
-- Fold fims.opening_balances into fims.v_trial_balance
--
-- Ending Balance (what this view reports) = Beginning Balance (from
-- fims.opening_balances, if any) + net GL activity for that fiscal_yr/book.
-- A fresh account with a beginning balance and no other postings now shows
-- that same figure as both its beginning and ending balance.
--
-- Backward compatible with years that used the old OPENING-classified-JEV
-- method (no row in fims.opening_balances — COALESCEs to 0, and that JEV's
-- effect is already included in the GL aggregate instead).
-- ============================================================================
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
