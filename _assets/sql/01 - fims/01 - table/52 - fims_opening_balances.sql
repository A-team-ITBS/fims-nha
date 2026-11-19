-- ============================================================================
-- fims.opening_balances — raw beginning-balance figures per account, fiscal
-- year, and book. NOT a journal entry — no JEV is created or posted to
-- fims.general_ledger for these rows. Each account gets one standalone debit
-- OR credit amount (no requirement that the full set balances to zero,
-- unlike a JEV — you can enter Cash = 500,000 on its own, no offsetting
-- Equity line required).
--
-- fims.v_trial_balance (see alter 56) adds these on top of GL activity, so a
-- fresh account with no other postings shows the same figure as both its
-- Beginning and Ending balance on the Trial Balance report.
-- ============================================================================
CREATE TABLE fims.opening_balances (
    opening_balance_id SERIAL PRIMARY KEY,
    fiscal_yr SMALLINT NOT NULL,
    book_id INTEGER NOT NULL REFERENCES fims.books(book_id),
    account_code VARCHAR(30) NOT NULL REFERENCES fims.chart_of_accounts(account_code),
    debit NUMERIC(18,2) NOT NULL DEFAULT 0,
    credit NUMERIC(18,2) NOT NULL DEFAULT 0,
    created_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by INTEGER,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_opening_balances_fy_book_account UNIQUE (fiscal_yr, book_id, account_code),
    CONSTRAINT chk_opening_balances_one_sided CHECK (
        (debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)
    )
);

CREATE INDEX idx_opening_balances_fy_book ON fims.opening_balances(fiscal_yr, book_id);

COMMENT ON TABLE fims.opening_balances IS 'Beginning balance per account/fiscal_yr/book — not a JEV, not posted to general_ledger. fims.v_trial_balance adds these to GL activity to compute Ending Balance.';
