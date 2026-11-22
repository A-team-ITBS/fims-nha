-- ============================================================================
-- Bank Reconciliation Cleared Items — per-bank-account "has this GL line
-- cleared the bank yet?" marker, one row per fims.general_ledger line that
-- has ever been toggled.
--
-- Backs the ledger view in the Bank Reconciliation module (see
-- app/services/BankReconciliationService.php::getLedger()/setCleared()).
-- Keyed by (bank_account_id, gl_id) rather than reconciliation_id — clearing
-- status is a property of the transaction itself (has the bank processed it
-- yet), not of any one month's worksheet, so a check marked cleared in
-- January stays cleared when November's worksheet looks back at it. A GL
-- line with no row here is treated as NOT YET cleared (outstanding) by
-- default — see getLedger()'s LEFT JOIN — so unchecked is the natural state
-- for a freshly-posted transaction the bank hasn't processed yet.
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.bank_reconciliation_cleared_items (
    bank_account_id INTEGER NOT NULL REFERENCES fims.bank_accounts(bank_account_id),
    gl_id           INTEGER NOT NULL REFERENCES fims.general_ledger(gl_id),
    cleared_at      TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    cleared_by      INTEGER,
    PRIMARY KEY (bank_account_id, gl_id)
);

COMMENT ON TABLE fims.bank_reconciliation_cleared_items IS 'Presence of a row = that GL line has cleared the bank, for this bank account''s reconciliation ledger view.';

CREATE INDEX IF NOT EXISTS idx_bank_reconciliation_cleared_items_gl_id ON fims.bank_reconciliation_cleared_items (gl_id);
