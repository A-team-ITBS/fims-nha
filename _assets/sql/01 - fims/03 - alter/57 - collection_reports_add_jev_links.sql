-- Collection Reports: link each report to the standalone Journal Entry Vouchers prepared from it.
-- Collections has always been "record first, journalize later" (see CollectionsService's docblock)
-- — no accounting entries were created at all. This adds the ability to prefill a standalone JEV
-- (DisbursementVoucherService::createStandaloneJev(), same mechanism as the IAR/Property Record
-- modules' "pro-forma" JEV submit) for review, in two separate legs:
--   - collection_jev_id: Dr Cash-Collecting Officers / Cr each Collection line's GL account
--   - deposit_jev_id:    Dr each Deposit's bank account GL account / Cr Cash-Collecting Officers
-- Nullable — a report may have neither, either, or both prepared. Once set, the report's own
-- Collection/Deposit lines are shown read-only for that leg (same convention as deposits already
-- do for is_swept) so the same money is never journalized twice.

ALTER TABLE fims.collection_reports ADD COLUMN IF NOT EXISTS collection_jev_id INTEGER REFERENCES fims.journal_entry_vouchers(jev_id) ON DELETE SET NULL;
ALTER TABLE fims.collection_reports ADD COLUMN IF NOT EXISTS deposit_jev_id INTEGER REFERENCES fims.journal_entry_vouchers(jev_id) ON DELETE SET NULL;

COMMENT ON COLUMN fims.collection_reports.collection_jev_id IS 'Standalone JEV (Dr Cash-Collecting Officers / Cr revenue-receivable lines) prepared from this report''s Collection(s) section, if any.';
COMMENT ON COLUMN fims.collection_reports.deposit_jev_id IS 'Standalone JEV (Dr bank GL accounts / Cr Cash-Collecting Officers) prepared from this report''s Deposit section, if any.';
