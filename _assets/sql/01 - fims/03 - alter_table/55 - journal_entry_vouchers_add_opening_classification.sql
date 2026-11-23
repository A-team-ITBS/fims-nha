-- ============================================================================
-- Add OPENING to jev_classification enum
--
-- The Opening Balance Entry feature uses OPENING-classified JEVs to seed
-- beginning balances for accounts at the start of a fiscal year. These are
-- normal journal entries (posted to fims.general_ledger via the existing
-- GeneralLedgerService::postJev() machinery), just tagged for reporting
-- and audit purposes.
-- ============================================================================
ALTER TABLE fims.journal_entry_vouchers DROP CONSTRAINT IF EXISTS chk_journal_entry_vouchers_classification;
ALTER TABLE fims.journal_entry_vouchers
ADD CONSTRAINT chk_journal_entry_vouchers_classification
    CHECK (jev_classification IN ('OPENING', 'REGULAR', 'ACCRUAL', 'DEPRECIATION', 'PREPAYMENTS', 'CLOSING'));

COMMENT ON CONSTRAINT chk_journal_entry_vouchers_classification ON fims.journal_entry_vouchers IS 'Nature of this JEV for reporting: OPENING (fiscal year start) | REGULAR (default, DV-driven) | ACCRUAL | DEPRECIATION | PREPAYMENTS | CLOSING. Independent of jev_type (SETUP/PAYMENT), which is GL-posting mechanics, not a business label.';
