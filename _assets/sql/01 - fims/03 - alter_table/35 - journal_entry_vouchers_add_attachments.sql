-- ============================================================================
-- Supporting Documents for a STANDALONE Journal Entry Voucher (no DV — period-end
-- ACCRUAL/DEPRECIATION/PREPAYMENTS/CLOSING entries, "03 - alter_table/33"). Same
-- fims.file_attachments / multiple_node_id convention as
-- fims.disbursement_vouchers (see "03 - alter_table/24") — a DV-driven JEV (SETUP
-- or PAYMENT) has no column of its own here; it relies on its own DV's Supporting
-- Documents instead, since attaching something to the DV already covers every JEV
-- derived from it.
-- ============================================================================
ALTER TABLE fims.journal_entry_vouchers
ADD COLUMN IF NOT EXISTS multiple_node_id UUID;

COMMENT ON COLUMN fims.journal_entry_vouchers.multiple_node_id IS 'Supporting Documents batch (fims.file_attachments.multiple_node_id) attached to this Journal Entry Voucher — standalone (no-DV) JEVs only; see DisbursementVoucherService::createStandaloneJev()/updateStandaloneJev()/fetchStandaloneJev()';

CREATE INDEX IF NOT EXISTS idx_journal_entry_vouchers_multiple_node_id ON fims.journal_entry_vouchers (multiple_node_id);
