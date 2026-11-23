-- ============================================================
-- ALTER: fims.budget_allocation_project — a COE account-code draw with no Parent CBA
-- An RFA/LAA block normally draws from a Parent CBA, which always carries a Project (parent_allocation_id,
-- migration 51). COE (Corporate Operating Expenditure) allocations have no Project to anchor against, so a
-- block may instead draw directly against a COB/account code — no parent, no project, just its own
-- line_transaction. parent_allocation_id and project_code were already nullable for exactly this case, but
-- that NULL/NULL combination is ambiguous with "an RFA/LAA block the user hasn't finished picking yet" —
-- this flag makes the intent explicit instead of inferring it, so validateReferences() / process() /
-- BudgetLineItemService::createDirectEarmarkForRfaApproval() can branch on it unambiguously.
-- FALSE (the default) for every existing block and for CBA/CFA, which never draw directly this way.
-- ============================================================
ALTER TABLE fims.budget_allocation_project
    ADD COLUMN IF NOT EXISTS is_direct_draw BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN fims.budget_allocation_project.is_direct_draw IS
    'RFA/LAA only: this block draws directly against a COE account code, with no Parent CBA and no
     Project (parent_allocation_id and project_code both NULL). FALSE for every CBA/CFA block and for a
     normal Parent-CBA-driven RFA/LAA block.';
