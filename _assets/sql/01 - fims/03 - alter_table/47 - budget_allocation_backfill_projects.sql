-- ============================================================
-- BACKFILL: fims.budget_allocation -> fims.budget_allocation_project
-- Every memo request made before multi-project support becomes exactly one project block, carrying over its
-- program_code / sub_program_code / project_code / line_transaction as-is. Must run AFTER
-- "46 - budget_allocation_project_add_fk.sql" and BEFORE "48 - budget_allocation_drop_project_columns.sql" (which
-- removes the columns this reads).
--
-- Idempotent: only allocations that do not already have a block are backfilled, so running this more than once
-- (or after some requests already have blocks created through the new form) never duplicates anything.
-- ============================================================
INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, line_transaction)
SELECT a.allocation_id, 0, a.program_code, a.sub_program_code, a.project_code, a.line_transaction
FROM fims.budget_allocation a
WHERE NOT EXISTS (
    SELECT 1 FROM fims.budget_allocation_project p WHERE p.allocation_id = a.allocation_id
);
