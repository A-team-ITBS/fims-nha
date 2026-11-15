-- ============================================================================
-- Payment entries — closes a real gap: when Treasury actually releases a
-- check/ADA, nothing posted to the GL to settle the Accounts Payable/Due to
-- BIR/Retention balances Accounting set up earlier, or to credit Cash. AP
-- (and similar liability control accounts) sat in fims.general_ledger
-- forever at their setup-time balance; Cash never moved. See
-- GeneralLedgerService::postPaymentJev(), called from
-- CheckProcessingService::prepareCheck() right after a DV is RELEASED.
--
-- This needs a SECOND journal_entry_vouchers row per DV (a distinct JEV
-- number, e.g. JEV-26-11-0002, Dr AP / Cr Cash, sourced by the check/ADA
-- number) — but dv_id was UNIQUE, modeling "exactly one JEV per DV". Relaxed
-- here to "at most one SETUP JEV per DV, any number of PAYMENT JEVs" via
-- jev_type + a partial unique index, rather than dropping the guarantee
-- entirely — DisbursementVoucherService::fetchJev()/persistJournalEntry()/
-- saveJournalEntry() still only ever read/write the SETUP one; PAYMENT rows
-- are written exclusively by postPaymentJev() and shown read-only.
--
-- source_module/doc_ref_no (NGAS Journal-listing columns — "ACCOUNTING" vs
-- "TREASURY", "IAR-001" vs "CHECK NO. 001") are copied down from JEV to GL
-- the same way fiscal_yr/book_id already are, so Journals/CKDJ/ADADJ can show
-- them per posted line.
-- ============================================================================
ALTER TABLE fims.journal_entry_vouchers DROP CONSTRAINT IF EXISTS journal_entry_vouchers_dv_id_key;
ALTER TABLE fims.journal_entry_vouchers
ADD COLUMN IF NOT EXISTS jev_type      VARCHAR(10) NOT NULL DEFAULT 'SETUP', -- SETUP | PAYMENT
ADD COLUMN IF NOT EXISTS source_module VARCHAR(20) NOT NULL DEFAULT 'ACCOUNTING', -- ACCOUNTING | TREASURY
ADD COLUMN IF NOT EXISTS doc_ref_no    VARCHAR(50); -- e.g. 'IAR-001', 'CHECK NO. 001' — the source document, not the JEV's own number
CREATE UNIQUE INDEX IF NOT EXISTS uq_journal_entry_vouchers_dv_id_setup
    ON fims.journal_entry_vouchers (dv_id) WHERE jev_type = 'SETUP';
CREATE INDEX IF NOT EXISTS idx_journal_entry_vouchers_dv_id ON fims.journal_entry_vouchers (dv_id);
CREATE INDEX IF NOT EXISTS idx_journal_entry_vouchers_jev_type ON fims.journal_entry_vouchers (jev_type);

ALTER TABLE fims.general_ledger
ADD COLUMN IF NOT EXISTS source_module VARCHAR(20),
ADD COLUMN IF NOT EXISTS doc_ref_no    VARCHAR(50);

-- Which Cash GL account a bank account's outflow credits — e.g. MDS Checking Account -> 1-01-02-020
-- (Cash in Bank-Local Currency, Current Account). Nullable: postPaymentJev() errors out (doesn't
-- silently guess) when a bank account has no mapping yet, same as every other "required but
-- user-configured" link in this schema (sl_masters.account_code, etc.).
ALTER TABLE fims.bank_accounts
ADD COLUMN IF NOT EXISTS account_code VARCHAR(30) REFERENCES fims.chart_of_accounts(account_code);
