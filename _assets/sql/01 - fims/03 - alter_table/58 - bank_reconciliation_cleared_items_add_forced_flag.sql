-- ============================================================================
-- Bank Reconciliation ledger now auto-matches each GL line against the
-- uploaded SOA (bank Statement of Account — fims.soa_transactions, see
-- CashPositionSoaService) by date + amount, instead of relying only on a
-- manual checkbox. fims.bank_reconciliation_cleared_items therefore needs to
-- distinguish "the user explicitly forced this" from "just a leftover
-- presence row" — forced_cleared records which way the user forced it
-- (TRUE = force cleared even if no/different SOA match, FALSE = force NOT
-- cleared even though SOA auto-matched it — e.g. a false-positive match).
-- See BankReconciliationService::getLedger()/setCleared().
-- ============================================================================
ALTER TABLE fims.bank_reconciliation_cleared_items
    ADD COLUMN IF NOT EXISTS forced_cleared BOOLEAN NOT NULL DEFAULT TRUE;

COMMENT ON COLUMN fims.bank_reconciliation_cleared_items.forced_cleared IS 'Manual override direction: TRUE forces cleared, FALSE forces NOT cleared (overrides an auto SOA match). Row absence = no override, defer to the SOA auto-match.';
