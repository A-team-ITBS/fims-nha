-- ============================================================
-- ALTER: fims.budget_allocation_project — which parent CBA THIS block draws from
-- An RFA/LAA can draw from several CBAs at once (multi-funding-source). The parent lives on the BLOCK,
-- not the header: each selected parent CBA (single-project only, going forward) contributes exactly one
-- block, so "which CBA does this draw come from" is structural, not re-derived by matching project_code
-- at runtime. NULL for CBA/CFA blocks and for an RFA/LAA block with no parent chosen yet.
-- fims.budget_allocation.parent_id survives as a DISPLAY-ONLY first-parent mirror — see migration 53.
-- ============================================================
ALTER TABLE fims.budget_allocation_project
    ADD COLUMN IF NOT EXISTS parent_allocation_id INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_project_parent_allocation'
    ) THEN
        ALTER TABLE fims.budget_allocation_project
        ADD CONSTRAINT fk_budget_allocation_project_parent_allocation
        FOREIGN KEY (parent_allocation_id) REFERENCES fims.budget_allocation(allocation_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_budget_allocation_project_parent_allocation_id
    ON fims.budget_allocation_project (parent_allocation_id);

COMMENT ON COLUMN fims.budget_allocation_project.parent_allocation_id IS
    'The parent CBA this block draws its budget from (RFA/LAA only; NULL for CBA/CFA)';
