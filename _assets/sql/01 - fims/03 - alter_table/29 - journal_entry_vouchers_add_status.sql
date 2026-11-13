-- ============================================================================
-- ALTER: fims.journal_entry_vouchers
--
-- Adds a status column so a JEV's own lifecycle (DRAFT while Accounting is
-- still editing it -> APPROVED/POSTED once the DV it belongs to is approved
-- on the Accounting side -> REVERSED if a posted DV is later cancelled) is
-- tracked explicitly, instead of only being implied by its parent DV's own
-- status. Same shared-lookup convention as disbursement_vouchers.status_id /
-- budget_req.status_id (see "03 - alter_table/21 -
-- disbursement_vouchers_status_to_settings_status.sql") — status_id FKs to
-- fims.settings_status with status_for = 'journal_entry_voucher'; seed rows
-- are in "02 - sample_data/11 - fims_settings_status_journal_entry_voucher.sql".
--
-- Every pre-existing JEV (there is no DRAFT/POSTED concept until now) is
-- backfilled to DRAFT, same as any other "new column on an old table" alter
-- in this repo — GeneralLedgerService::postJev() is what actually moves a
-- JEV to POSTED and is only ever called going forward from here.
--
-- posted_by/posted_at record who/when the POSTED transition happened — not
-- who originally prepared the JEV (that's still created_by/created_at).
--
-- Idempotent (IF NOT EXISTS / guarded), safe to run any number of times.
-- ============================================================================

ALTER TABLE fims.journal_entry_vouchers
    ADD COLUMN IF NOT EXISTS status_id INTEGER REFERENCES fims.settings_status(id),
    ADD COLUMN IF NOT EXISTS posted_by INTEGER,
    ADD COLUMN IF NOT EXISTS posted_at TIMESTAMPTZ;

COMMENT ON COLUMN fims.journal_entry_vouchers.status_id IS 'fims.settings_status (status_for = ''journal_entry_voucher''): DRAFT -> APPROVED -> POSTED, or REVERSED. Moved by GeneralLedgerService::postJev() / reverseJev().';
COMMENT ON COLUMN fims.journal_entry_vouchers.posted_by IS 'uac.users.user_id who triggered the POSTED transition (the Accounting user who advanced the DV to FOR_TREASURY), not who prepared the JEV — see created_by for that.';
COMMENT ON COLUMN fims.journal_entry_vouchers.posted_at IS 'When the JEV was posted to the General Ledger. NULL while still DRAFT/APPROVED.';

-- Backfill: every JEV that already exists predates this column — DRAFT is the correct starting
-- state for all of them (none has been through GeneralLedgerService::postJev() yet).
UPDATE fims.journal_entry_vouchers jev
SET status_id = (SELECT id FROM fims.settings_status WHERE status_for = 'journal_entry_voucher' AND name = 'DRAFT')
WHERE jev.status_id IS NULL
  AND EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'journal_entry_voucher' AND name = 'DRAFT');

CREATE INDEX IF NOT EXISTS idx_journal_entry_vouchers_status_id ON fims.journal_entry_vouchers (status_id);
