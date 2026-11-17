-- ============================================================
-- ALTER: fims.budget_line_item_earmark — which project block holds this earmark
-- A CBA can now have several project blocks (fims.budget_allocation_project), each able to earmark the same COB
-- independently. Every earmark row must say which block it belongs to so an RFA/LAA drawing against one block
-- can never resolve (and decrement/convert) a DIFFERENT block's earmark for the same budget_line_item_id — see
-- BudgetLineItemService::convertEarmarkForRfaApproval() / validateRfaAmounts().
--
-- Nullable: an earmark predates multi-project support only on a database that has not yet run the backfill below.
-- Must run AFTER "47 - budget_allocation_backfill_projects.sql" (every allocation has exactly one block by then,
-- so the backfill here is unambiguous).
-- ============================================================
ALTER TABLE fims.budget_line_item_earmark
    ADD COLUMN IF NOT EXISTS budget_allocation_project_id INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_line_item_earmark_project'
    ) THEN
        ALTER TABLE fims.budget_line_item_earmark
        ADD CONSTRAINT fk_budget_line_item_earmark_project
        FOREIGN KEY (budget_allocation_project_id) REFERENCES fims.budget_allocation_project(budget_allocation_project_id) ON DELETE CASCADE;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_budget_line_item_earmark_project_id ON fims.budget_line_item_earmark (budget_allocation_project_id);

-- Backfill: at this point every allocation_id has exactly one budget_allocation_project row, so the match is 1:1.
UPDATE fims.budget_line_item_earmark e
SET budget_allocation_project_id = p.budget_allocation_project_id
FROM fims.budget_allocation_project p
WHERE p.allocation_id = e.allocation_id
  AND e.budget_allocation_project_id IS NULL;
