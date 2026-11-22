-- ============================================================================
-- Links an FTLA to the payment Journal Entry Voucher its own submission
-- creates. FtlaService::update()'s VALIDATED -> SUBMITTED_TO_ACCOUNTING
-- transition now mirrors CheckProcessingService::saveCheck(): it builds and
-- immediately posts ONE combined payment JEV (Dr Accounts Payable per linked
-- DV / Cr Cash at the FTLA's destination bank account) and releases every
-- linked DV — same "the release action posts the payment entry itself,
-- bypassing the Accounting review chain" idea checks already use.
--
-- Nullable: a DRAFT/in-review FTLA, or one with nothing left to settle (every
-- linked DV's setup JEV already posted straight to Cash), never gets one.
-- No FK — same "soft pointer, never blocks on the other side's lifecycle"
-- convention fims.checks.dv_number / fims.ada_releases.dv_number already use,
-- since a JEV is never deleted once posted.
-- ============================================================================
ALTER TABLE fims.fund_transfers
ADD COLUMN IF NOT EXISTS jev_id INTEGER;

COMMENT ON COLUMN fims.fund_transfers.jev_id IS 'fims.journal_entry_vouchers.jev_id for the combined payment JEV this FTLA''s submission posted — NULL until SUBMITTED_TO_ACCOUNTING, or if nothing needed settling';
