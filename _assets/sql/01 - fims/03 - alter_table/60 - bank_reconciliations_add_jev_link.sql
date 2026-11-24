-- ============================================================================
-- Bank Reconciliation -> adjusting Journal Entry Voucher link.
--
-- The Per Books Add/Less items (Unrecorded Deposits, Credit Memo, Interest
-- Income, Book Error-under / Debit Memo, Withholding Tax, Bank Charges/Cost
-- of Checkbooks, Book Error-over) represent real cash movements the bank has
-- already recorded but the book has not — until a Journal Entry actually
-- posts them to fims.general_ledger, next month's "Book Balance" auto-fill
-- (BankReconciliationService::getBookBalance(), summed straight from the
-- ledger) would show the same gap again. jev_id/jev_no link a reconciliation
-- to the standalone adjusting JEV BankReconciliationService::postAdjustingJev()
-- creates for it (via DisbursementVoucherService::createStandaloneJev() — same
-- "backend calls the existing JEV-creation service directly" pattern already
-- used by PropertyRecordService::submitProforma()). One JEV per reconciliation
-- at most; NULL until posted. The JEV itself is created DRAFT, same as every
-- other standalone JEV — this module does not auto-post it to the GL.
-- ============================================================================
ALTER TABLE fims.bank_reconciliations
    ADD COLUMN IF NOT EXISTS jev_id INTEGER REFERENCES fims.journal_entry_vouchers(jev_id),
    ADD COLUMN IF NOT EXISTS jev_no VARCHAR(50);

CREATE INDEX IF NOT EXISTS idx_bank_reconciliations_jev_id ON fims.bank_reconciliations (jev_id);
