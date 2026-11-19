-- ============================================================
-- COMMENT ONLY: fims.budget_allocation.parent_id is now a legacy display mirror
-- An RFA/LAA can now draw from several parent CBAs (see migration 51) — the real, authoritative per-draw
-- parent lives on each block, fims.budget_allocation_project.parent_allocation_id. This column keeps being
-- maintained (by BudgetAllocationService::create()/update()) as the FIRST parent only, purely so existing
-- "one parent" readers — the list/read join (parent_request_referrence_no) and the Ledger of Budget
-- Allocation page — keep compiling and showing something sensible without a rewrite. No DDL on data here.
-- ============================================================
COMMENT ON COLUMN fims.budget_allocation.parent_id IS
    'LEGACY DISPLAY MIRROR ONLY: the FIRST parent CBA (lowest sort_order block) of an RFA/LAA. The real,
     authoritative per-draw parent is fims.budget_allocation_project.parent_allocation_id (migration 51).
     Maintained by BudgetAllocationService::create()/update() for list display (parent_request_referrence_no)
     and LedgerBudgetAllocationService grouping. NOTHING THAT MOVES MONEY MAY READ THIS COLUMN.';
