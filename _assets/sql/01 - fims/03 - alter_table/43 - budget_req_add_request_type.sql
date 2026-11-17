-- ============================================================
-- ALTER: fims.budget_req
-- Adds request_type: 'REGULAR' (the existing Program/Sub-Program/Project-shaped
-- request) or 'COE' (a flat list of chart-of-accounts line items directly under the
-- header — no Program/Project at all; see BudgetReqService::addCoeRequest()).
--
-- The existing UNIQUE (fiscal_yr, rc_id) constraint (added by "10 -
-- budget_req_add_rc_id.sql") meant one header per office per fiscal year, period.
-- Widened to (fiscal_yr, rc_id, request_type) so one office can have BOTH a REGULAR
-- and a COE header for the same fiscal year.
-- ============================================================

ALTER TABLE fims.budget_req
    ADD COLUMN IF NOT EXISTS request_type VARCHAR(10) NOT NULL DEFAULT 'REGULAR';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_budget_req_request_type') THEN
        ALTER TABLE fims.budget_req
            ADD CONSTRAINT chk_budget_req_request_type CHECK (request_type IN ('REGULAR', 'COE'));
    END IF;
END $$;

ALTER TABLE fims.budget_req
    DROP CONSTRAINT IF EXISTS uq_budget_req_fiscal_yr_rc_id;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_budget_req_fiscal_yr_rc_id_type') THEN
        ALTER TABLE fims.budget_req
            ADD CONSTRAINT uq_budget_req_fiscal_yr_rc_id_type UNIQUE (fiscal_yr, rc_id, request_type);
    END IF;
END $$;

COMMENT ON COLUMN fims.budget_req.request_type IS
    'REGULAR = Program/Sub-Program/Project-shaped request. COE = a flat list of chart-of-accounts line items directly under this header, no Program/Project.';
