-- ============================================================
-- ALTER: fims.budget_allocation — drop the single-project columns
-- A memo request can now cover several independent projects (fims.budget_allocation_project, one row per block,
-- each with its own program_code / sub_program_code / project_code / line_transaction) instead of exactly one.
-- Every existing row was backfilled into one block by "47 - budget_allocation_backfill_projects.sql" — this MUST
-- run after that backfill. BudgetAllocationService / the Memo Request form no longer read or write these columns.
--
-- THIS DELETES THE DATA in these columns (same "accept the loss, it's been backfilled" precedent as
-- "20 - budget_allocation_drop_memo_ref_no.sql"). Their FK constraints and indexes are dropped automatically with
-- each column (Postgres has no CASCADE needed for a single-column constraint/index); no view, function or trigger
-- refers to them.
-- Idempotent: safe to run any number of times (a database that never had these columns is left alone).
-- ============================================================
ALTER TABLE fims.budget_allocation
    DROP COLUMN IF EXISTS program_code,
    DROP COLUMN IF EXISTS sub_program_code,
    DROP COLUMN IF EXISTS project_code,
    DROP COLUMN IF EXISTS line_transaction;
