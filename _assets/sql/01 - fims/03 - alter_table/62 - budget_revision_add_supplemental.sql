-- ============================================================
-- ALTER: fims.budget_revision + fims.budget_line_item_revision_transaction — the SUPPLEMENTAL request type
--
-- A SUPPLEMENTAL request (a third tab of the Budget Amendment form, next to Amendment and Realignment) adds a NEW project, released by a
-- DBM SARO and not in the approved COB, under a Program / Sub-Program. It follows the same Saved -> Submitted -> Approved | Declined flow.
-- Only the APPROVAL changes the budget (BudgetRevisionService::approveSupplemental(), one transaction):
--   * the project is created (fims.project, its code GENERATED — the form does not show or ask for it), under the Program / Sub-Program,
--   * the filing user's office's budget request of the SARO's fiscal year (fims.budget_req, Approved) gets the project + one budget line
--     per line item (fims.budget_line_items, suggested_amount left at 0 = nothing "approved"),
--   * the ledger gets one row per budget line, transaction_type 'SUPPLEMENTAL', amount = the item's amount — so the amount shows in the
--     COB's Amendment / Realignment column (BudgetLineBalanceSql::revisionExpr()) and NOT in its Approved Amount column.
--
-- What changes here:
--   1. fims.budget_revision.request_type may now be 'SUPPLEMENTAL'. A SUPPLEMENTAL request has no From / To budget line
--      (from_budget_line_item_id / to_budget_line_item_id stay NULL); from_amount = 0 and to_amount = the total of its line items, so the
--      list's From / To / Change columns read +total like any other increase.
--   2. fims.budget_revision gets the supp_* columns that describe the request. They carry a prefix because the API's SELECT also returns the
--      budget line's own fiscal_yr / program_code / project_code / project_name under those names (BudgetRevisionService::lineSelect()):
--        supp_fiscal_yr           the SARO's fiscal year — the year of the COB the project is added to
--        supp_fund_tracking_id    fims.fund_tracking.id of the SARO (soft link: fund_tracking declares no keys, and is soft-deleted)
--        supp_program_code        the Program (and, optionally, supp_sub_program_code the Sub-Program) the project goes under
--        supp_project_name        what the user typed; supp_location its location (optional)
--        supp_project_code        the generated code — NULL until the request is approved
--      The line items live in fims.budget_revision_supplemental_item ("01 - table/56").
--   3. fims.budget_line_item_revision_transaction.transaction_type may now be 'SUPPLEMENTAL'. A row is written for a line that did not exist
--      before the approval: previous_amount 0, amount = the item's amount, new_amount = amount (the table's check new = previous + amount holds).
--
-- No FOREIGN KEYs on the supp_* columns, like the line-id columns beside them: they are checked by the API.
-- Idempotent (IF NOT EXISTS / DROP IF EXISTS guards): safe to run any number of times, on any environment.
-- ============================================================

ALTER TABLE fims.budget_revision
    ADD COLUMN IF NOT EXISTS supp_fiscal_yr        SMALLINT,
    ADD COLUMN IF NOT EXISTS supp_fund_tracking_id INTEGER,
    ADD COLUMN IF NOT EXISTS supp_program_code     VARCHAR(64),
    ADD COLUMN IF NOT EXISTS supp_sub_program_code VARCHAR(64),
    ADD COLUMN IF NOT EXISTS supp_project_name     VARCHAR(255),
    ADD COLUMN IF NOT EXISTS supp_project_code     VARCHAR(64),
    ADD COLUMN IF NOT EXISTS supp_location         VARCHAR(255);

-- request_type: REVISION | REALIGNMENT | SUPPLEMENTAL
ALTER TABLE fims.budget_revision DROP CONSTRAINT IF EXISTS chk_budget_revision_request_type;
ALTER TABLE fims.budget_revision
    ADD CONSTRAINT chk_budget_revision_request_type CHECK (request_type IN ('REVISION', 'REALIGNMENT', 'SUPPLEMENTAL'));

COMMENT ON COLUMN fims.budget_revision.request_type IS
    'REVISION (no To line: the From line''s amount changes), REALIGNMENT (a To line: funds move from the From line to it) or SUPPLEMENTAL (a NEW project funded by a SARO; no budget lines until it is approved — see the supp_* columns).';
COMMENT ON COLUMN fims.budget_revision.supp_fiscal_yr IS 'SUPPLEMENTAL only: the SARO''s fiscal year, i.e. the fiscal year of the budget request / COB the project is added to.';
COMMENT ON COLUMN fims.budget_revision.supp_fund_tracking_id IS 'SUPPLEMENTAL only: fims.fund_tracking.id of the SARO that released the project''s funds. No FK (fund_tracking declares none and is soft-deleted).';
COMMENT ON COLUMN fims.budget_revision.supp_program_code IS 'SUPPLEMENTAL only: fims.program.program_code the project goes under. Checked by the API.';
COMMENT ON COLUMN fims.budget_revision.supp_sub_program_code IS 'SUPPLEMENTAL only: fims.sub_program.sub_program_code (optional; must belong to supp_program_code). Checked by the API.';
COMMENT ON COLUMN fims.budget_revision.supp_project_name IS 'SUPPLEMENTAL only: the new project''s name, as typed on the form.';
COMMENT ON COLUMN fims.budget_revision.supp_project_code IS 'SUPPLEMENTAL only: the project code GENERATED when the request is approved (NULL before that). Not shown on the form.';
COMMENT ON COLUMN fims.budget_revision.supp_location IS 'SUPPLEMENTAL only: the new project''s location (optional).';

CREATE INDEX IF NOT EXISTS idx_fims_budget_revision_supp_fund_tracking ON fims.budget_revision (supp_fund_tracking_id);

-- the ledger: transaction_type may now be SUPPLEMENTAL
ALTER TABLE fims.budget_line_item_revision_transaction DROP CONSTRAINT IF EXISTS chk_budget_line_item_revision_transaction_type;
ALTER TABLE fims.budget_line_item_revision_transaction
    ADD CONSTRAINT chk_budget_line_item_revision_transaction_type
    CHECK (transaction_type IN ('REVISION', 'REALIGNMENT_OUT', 'REALIGNMENT_IN', 'LEGACY', 'SUPPLEMENTAL'));

COMMENT ON COLUMN fims.budget_line_item_revision_transaction.transaction_type IS
    'REVISION = the From line of a revision; REALIGNMENT_OUT / REALIGNMENT_IN = the From / To line of a realignment; SUPPLEMENTAL = a budget line created by an approved supplemental request (previous_amount 0, amount = its amount); LEGACY = the net of what was approved before this ledger existed.';
