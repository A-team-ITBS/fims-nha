-- ============================================================================
-- SARO (fims.fund_tracking) -> Disbursement Voucher link, plus the bank
-- account that SARO's check is to be drawn against.
--
-- dv_id: soft reference to fims.disbursement_vouchers.dv_id, same reasoning
-- (and same convention) as "03 - alter_table/23 - budget_allocation_add_dv_id.sql"
-- for RFA <-> DV: the link lives on the fund_tracking SIDE (one SARO points at
-- one DV at a time) so DisbursementVoucherService can enforce the "one DV at a
-- time" exclusivity rule itself (see linkSaro()), not the database. No FK —
-- fims.fund_tracking already declares none of its other references as FKs
-- (see "01 - table/29 - fims_fund_tracking.sql"'s own header comment).
--
-- bank_account_id: soft reference to fims.bank_accounts.bank_account_id — the
-- SARO form (saro-processing-form.php) already captures a bank account, but
-- only as free text in bank_account_no (the account NUMBER). This adds the
-- real id alongside it (bank_account_no is kept, unchanged, for backward
-- compatibility / display of old rows whose number isn't on the bank_accounts
-- list any more) so Check Preparation can resolve the linked SARO's bank
-- account and auto-fill it once a DV is selected.
-- ============================================================================
ALTER TABLE fims.fund_tracking
ADD COLUMN IF NOT EXISTS dv_id INTEGER,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER;

COMMENT ON COLUMN fims.fund_tracking.dv_id IS 'fims.disbursement_vouchers.dv_id this SARO is currently drawn into, if any (soft reference, no FK — see DisbursementVoucherService::linkSaro()). NULL = not yet linked to any voucher.';
COMMENT ON COLUMN fims.fund_tracking.bank_account_id IS 'fims.bank_accounts.bank_account_id this SARO''s funds are held in, if picked from the list (soft reference, no FK). bank_account_no keeps the account NUMBER as free text alongside it for backward compatibility.';

CREATE INDEX IF NOT EXISTS idx_fund_tracking_dv_id ON fims.fund_tracking (dv_id);
CREATE INDEX IF NOT EXISTS idx_fund_tracking_bank_account_id ON fims.fund_tracking (bank_account_id);
