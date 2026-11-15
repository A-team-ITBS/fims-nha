-- ============================================================================
-- Accounting's internal review chain on a Disbursement Voucher.
--
-- The Accounting stage is no longer a single step. A DV now moves:
--   FOR_ACCOUNTING          (Section Chief processes the DV + prepares the JEV)
--   -> FOR_DIVISION_MANAGER    (Division Manager reviews)
--   -> FOR_DEPARTMENT_MANAGER  (Department Manager approves)
--   -> FOR_TREASURY            (released to Treasury; JEV posts to the GL)
-- See DisbursementVoucherService::TRANSITIONS / STAGE_HOLDER_RC.
--
-- The existing audit columns already cover the first Accounting step and the
-- ends of the chain — they're reused, not duplicated:
--   fmd_approved_*          FMD -> Accounting (unchanged)
--   accounting_processed_*  Section Chief forwards to Division Manager
--   treasury_released_*     Treasury releases (unchanged)
-- Only the two new intermediate hand-offs need their own stamp, added here:
--   division_reviewed_*     Division Manager forwards to Department Manager
--   department_approved_*   Department Manager approves onward to Treasury
-- Same (who, when) shape as the columns already on this table (see
-- "01 - table/16 - fims_disbursement_vouchers.sql").
-- ============================================================================
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS division_reviewed_by   INTEGER,     -- Division Manager -> Department Manager
ADD COLUMN IF NOT EXISTS division_reviewed_at   TIMESTAMP,
ADD COLUMN IF NOT EXISTS department_approved_by INTEGER,     -- Department Manager -> Treasury
ADD COLUMN IF NOT EXISTS department_approved_at TIMESTAMP;

COMMENT ON COLUMN fims.disbursement_vouchers.division_reviewed_by   IS 'Accounting Division Manager who reviewed the DV (FOR_DIVISION_MANAGER -> FOR_DEPARTMENT_MANAGER)';
COMMENT ON COLUMN fims.disbursement_vouchers.department_approved_by IS 'Accounting Department Manager who approved the DV onward to Treasury (FOR_DEPARTMENT_MANAGER -> FOR_TREASURY)';
