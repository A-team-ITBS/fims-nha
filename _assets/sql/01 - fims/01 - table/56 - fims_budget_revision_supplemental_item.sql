-- ============================================================
-- fims.budget_revision_supplemental_item
-- The chart-of-accounts LINE ITEMS of a SUPPLEMENTAL budget request (fims.budget_revision.request_type = 'SUPPLEMENTAL').
--
-- NOTE: a request may carry several projects ("01 - table/57", "03 - alter_table/64"). Each line item belongs to ONE of them
-- (supplemental_project_id, added by file 64); budget_revision_id still says which request it is part of.
--
-- A Supplemental request adds a NEW project, released by a DBM SARO, to the budget — a project that is not in the approved COB. While
-- the request is Saved / Submitted the project does not exist yet, so what the user typed (the line items: account, particulars,
-- amount) is kept here, one row per line item, and replaced as a whole when the request is edited. The project itself, the program row,
-- the budget lines and the ledger rows are only created when FMD APPROVES the request (BudgetRevisionService::approveSupplemental()):
--   - one fims.budget_line_items row per item here (proposed_amount = amount, suggested_amount stays 0 — it is NOT "approved"),
--   - one fims.budget_line_item_revision_transaction row per line (transaction_type 'SUPPLEMENTAL', amount = this item's amount),
-- so the amount lands in the COB's Amendment / Realignment column, not in its Approved Amount column.
--
-- budget_line_item_id is filled in at approval — the budget line that was created for this item. No FK: a budget line can be
-- deleted and re-created when a Draft budget request's Program is edited (same reason fims.budget_revision has none).
-- The columns that describe the project / SARO / program of the request are on fims.budget_revision itself (supp_* columns,
-- "03 - alter_table/62 - budget_revision_add_supplemental.sql").
--
-- Idempotent (IF NOT EXISTS): safe to run any number of times, on any environment.
-- ============================================================

CREATE TABLE IF NOT EXISTS fims.budget_revision_supplemental_item (
    item_id             SERIAL PRIMARY KEY,
    budget_revision_id  INTEGER       NOT NULL REFERENCES fims.budget_revision(id) ON DELETE CASCADE,
    account_id          VARCHAR(10)   NOT NULL REFERENCES fims.chart_of_accounts(account_id),
    particulars         TEXT          NOT NULL,
    amount              NUMERIC(18,2) NOT NULL,
    budget_line_item_id INTEGER       NULL,
    created_at          TIMESTAMP     NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_budget_revision_supplemental_item_amount CHECK (amount > 0)
);

COMMENT ON TABLE fims.budget_revision_supplemental_item IS 'Line items (account, particulars, amount) of a SUPPLEMENTAL budget request. Applied to the budget — as fims.budget_line_items + ledger rows — only when the request is approved.';
COMMENT ON COLUMN fims.budget_revision_supplemental_item.amount IS 'This item''s amount: becomes the proposed_amount of its budget line and the SUPPLEMENTAL ledger amount (the Amendment figure in the COB). Always more than zero.';
COMMENT ON COLUMN fims.budget_revision_supplemental_item.budget_line_item_id IS 'The fims.budget_line_items row created for this item when the request was approved. NULL until then. No FK (a budget line can be re-created).';

-- budget_revision_supplemental_item indexes
CREATE INDEX IF NOT EXISTS idx_budget_revision_supplemental_item_revision ON fims.budget_revision_supplemental_item (budget_revision_id);
