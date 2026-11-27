-- ============================================================================
-- A Disbursement Voucher carries the RFA(s) it draws from, so one RFA can back
-- several DVs for as long as it has a remaining balance (budget less the gross
-- of its active DVs). Soft references, no FK.
--
-- fims.budget_allocation.dv_id is KEPT and maintained by DisbursementVoucherService
-- as "the latest active DV drawing from this RFA", so COB / BUR / FTLA / check
-- processing keep working unchanged.
-- ============================================================================
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS rfa_allocation_ids INTEGER[] NOT NULL DEFAULT '{}';

COMMENT ON COLUMN fims.disbursement_vouchers.rfa_allocation_ids IS 'fims.budget_allocation.allocation_id of every RFA this voucher draws from (soft reference).';

CREATE INDEX IF NOT EXISTS idx_dv_rfa_allocation_ids ON fims.disbursement_vouchers USING GIN (rfa_allocation_ids);

-- Backfill from the RFA-side single link.
UPDATE fims.disbursement_vouchers dv
SET rfa_allocation_ids = COALESCE((SELECT array_agg(a.allocation_id ORDER BY a.allocation_id)
                                   FROM fims.budget_allocation a
                                   WHERE a.allocation_type = 'RFA' AND a.dv_id = dv.dv_id), '{}')
WHERE rfa_allocation_ids = '{}';
