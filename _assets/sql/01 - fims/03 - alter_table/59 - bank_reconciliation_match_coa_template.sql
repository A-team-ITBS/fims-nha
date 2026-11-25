-- ============================================================================
-- Bank Reconciliation — rework the worksheet columns to match NHA's official
-- COA-style Bank Reconciliation Statement template (Unreconciled Balances ->
-- Add/Less, Per Books and Per Bank side by side, down to Reconciled
-- Balances), replacing the earlier ad-hoc field set.
--
-- add_unreleased_check was never actually used that way (the service always
-- wrote 0 to it — see the superseded comment in BankReconciliationService::
-- save() before this migration): repurposed instead of dropped, since it's
-- the same "Per Books, Less" shape as its new meaning. add_unrecorded_deposit
-- keeps its name — it already means what the template calls "Unrecorded
-- Deposits" (Per Books, Add), just unused data-wise. New columns added for
-- the lines the old worksheet had no field for at all: Withholding Tax,
-- Book Error (under/over), Bank Error (under/over).
--
-- Final line-up (see BankReconciliationService::save()):
--   Per Books Add:  add_unrecorded_deposit, add_credit_memo, add_interest_income, add_book_error_under
--   Per Books Less: less_debit_memo, less_withholding_tax, less_bank_charges, less_book_error_over
--   Per Bank  Add:  add_deposit_in_transit (auto-derived from GL<->SOA matching), add_bank_error_under (manual)
--   Per Bank  Less: less_outstanding_check (auto-derived from GL<->SOA matching), less_bank_error_over (manual)
-- ============================================================================
-- Idempotent rename: only rename if the old column still exists, otherwise make
-- sure the new one exists (covers re-runs and DBs where it was already renamed).
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = 'fims' AND table_name = 'bank_reconciliations'
                 AND column_name = 'add_unreleased_check') THEN
        ALTER TABLE fims.bank_reconciliations
            RENAME COLUMN add_unreleased_check TO less_bank_charges;
    ELSIF NOT EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = 'fims' AND table_name = 'bank_reconciliations'
                 AND column_name = 'less_bank_charges') THEN
        ALTER TABLE fims.bank_reconciliations
            ADD COLUMN less_bank_charges NUMERIC(18,2) NOT NULL DEFAULT 0;
    END IF;
END $$;
COMMENT ON COLUMN fims.bank_reconciliations.less_bank_charges IS 'Per Books, Less: Unrecorded Bank Charges/Cost of Checkbooks';
COMMENT ON COLUMN fims.bank_reconciliations.add_unrecorded_deposit IS 'Per Books, Add: Unrecorded Deposits (bank already recorded it, book has not)';

ALTER TABLE fims.bank_reconciliations
    ADD COLUMN IF NOT EXISTS less_withholding_tax  NUMERIC(18,2) NOT NULL DEFAULT 0, -- Per Books, Less: Unrecorded Withholding Tax
    ADD COLUMN IF NOT EXISTS add_book_error_under   NUMERIC(18,2) NOT NULL DEFAULT 0, -- Per Books, Add:  Book Error (under recording of deposits / misposting)
    ADD COLUMN IF NOT EXISTS less_book_error_over   NUMERIC(18,2) NOT NULL DEFAULT 0, -- Per Books, Less: Book Error (over recording of deposits / misposting)
    ADD COLUMN IF NOT EXISTS add_bank_error_under   NUMERIC(18,2) NOT NULL DEFAULT 0, -- Per Bank,  Add:  Bank Error (under validation of deposits)
    ADD COLUMN IF NOT EXISTS less_bank_error_over   NUMERIC(18,2) NOT NULL DEFAULT 0; -- Per Bank,  Less: Bank Error (over validation of deposits)
