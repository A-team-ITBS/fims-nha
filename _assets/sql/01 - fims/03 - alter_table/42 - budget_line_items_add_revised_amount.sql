-- ============================================================
-- ALTER: fims.budget_line_items
-- Adds revised_amount: a plain, app-maintained amount for the line item's
-- Revised Amount (shown alongside Proposed Amount / Adjustment Amount in the
-- Budget Requirements "View as COB" modal). Unlike adjustment (maintained by
-- the Budget Revision module) this column carries no formula or trigger of
-- its own — the application decides when and how to write to it.
--
-- NOT NULL DEFAULT 0 so existing rows backfill to 0 and SUM()/arithmetic
-- over it never has to deal with NULLs, matching proposed_amount/adjustment.
-- ============================================================

ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS revised_amount NUMERIC(18, 2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN fims.budget_line_items.revised_amount IS 'Revised amount for this line item. Plain app-maintained value, no automatic formula.';
