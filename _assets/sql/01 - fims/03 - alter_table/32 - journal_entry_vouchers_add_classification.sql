-- ============================================================================
-- JEV classification — the NGAS "nature of entry" a Journal Entry Voucher
-- represents: REGULAR (every day-to-day DV-driven entry — the only kind this
-- system produced until now), or one of the period-end adjusting/closing
-- kinds Accounting also has to book: ACCRUAL, DEPRECIATION, PREPAYMENTS,
-- CLOSING.
--
-- Deliberately a SEPARATE column from jev_type ("03 - alter_table/31" —
-- SETUP vs PAYMENT). jev_type is a GL-POSTING-MECHANICS flag: it's what the
-- partial unique index (uq_journal_entry_vouchers_dv_id_setup) and
-- GeneralLedgerService::postPaymentJev() key off to tell Accounting's
-- original entry apart from Treasury's later cash-settlement entry for the
-- SAME dv_id. jev_classification is a business/reporting label layered on
-- top of that — a SETUP (or PAYMENT) JEV can be any classification; nothing
-- here changes how postJev()/postPaymentJev()/the unique index behave.
--
-- Chosen on the Accounting Entries section of the DV form (same place Book
-- is chosen — app/pages/voucher/readone.php's vje_entriesCard()), defaults to
-- REGULAR so every pre-existing JEV backfills correctly with no judgment
-- call needed.
-- ============================================================================
ALTER TABLE fims.journal_entry_vouchers
ADD COLUMN IF NOT EXISTS jev_classification VARCHAR(20) NOT NULL DEFAULT 'REGULAR';

ALTER TABLE fims.journal_entry_vouchers DROP CONSTRAINT IF EXISTS chk_journal_entry_vouchers_classification;
ALTER TABLE fims.journal_entry_vouchers
ADD CONSTRAINT chk_journal_entry_vouchers_classification
    CHECK (jev_classification IN ('REGULAR', 'ACCRUAL', 'DEPRECIATION', 'PREPAYMENTS', 'CLOSING'));

COMMENT ON COLUMN fims.journal_entry_vouchers.jev_classification IS 'Nature of this JEV for reporting: REGULAR (default, DV-driven) | ACCRUAL | DEPRECIATION | PREPAYMENTS | CLOSING. Independent of jev_type (SETUP/PAYMENT), which is GL-posting mechanics, not a business label.';

CREATE INDEX IF NOT EXISTS idx_journal_entry_vouchers_classification ON fims.journal_entry_vouchers (jev_classification);
