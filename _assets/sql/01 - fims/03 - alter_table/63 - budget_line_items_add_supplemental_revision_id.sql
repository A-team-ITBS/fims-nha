-- ============================================================
-- ALTER: fims.budget_line_items — the SUPPLEMENTAL tag
--
-- A budget line created by an approved SUPPLEMENTAL budget revision (BudgetRevisionService::approveSupplemental(), "03 - alter_table/62") is
-- tagged with the request that created it:
--   supplemental_revision_id  INTEGER NULL  — fims.budget_revision.id of the supplemental request. NULL for every ordinary line.
--
-- Why: a supplemental project is a NEW project released by a DBM SARO, added straight to the approved budget — it was never part of a budget
-- PROPOSAL. The COB Monitoring and RFA / LAA Monitoring pages show it (and its amount, as an Amendment); the Budget Requirements (proposal) screens
-- must not, so they leave out every line that carries this tag (and the projects / programs that exist only for such lines).
-- Before this column the only trace was the ledger row (fims.budget_line_item_revision_transaction, transaction_type 'SUPPLEMENTAL').
--
-- No FOREIGN KEY, like the other soft links to budget_revision / budget lines (a Draft proposal's lines are deleted and re-created).
-- Backfill: lines already created by an approved supplemental request are tagged from their ledger row.
-- Idempotent (IF NOT EXISTS; the backfill only fills NULLs): safe to run any number of times, on any environment.
-- ============================================================

ALTER TABLE fims.budget_line_items ADD COLUMN IF NOT EXISTS supplemental_revision_id INTEGER;

COMMENT ON COLUMN fims.budget_line_items.supplemental_revision_id IS
    'fims.budget_revision.id of the SUPPLEMENTAL request that created this line when it was approved; NULL for an ordinary (proposal) line. The proposal screens leave tagged lines out; COB / RFA-LAA Monitoring show them. No FK.';

CREATE INDEX IF NOT EXISTS idx_fims_budget_line_items_supplemental_revision
    ON fims.budget_line_items (supplemental_revision_id) WHERE supplemental_revision_id IS NOT NULL;

UPDATE fims.budget_line_items li
   SET supplemental_revision_id = lg.budget_revision_id
  FROM fims.budget_line_item_revision_transaction lg
 WHERE lg.budget_line_item_id = li.budget_line_item_id
   AND lg.transaction_type = 'SUPPLEMENTAL'
   AND lg.budget_revision_id IS NOT NULL
   AND li.supplemental_revision_id IS NULL;
