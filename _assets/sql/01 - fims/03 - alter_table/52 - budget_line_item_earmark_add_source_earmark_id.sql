-- ============================================================
-- ALTER: fims.budget_line_item_earmark — which parent earmark row this draw came out of
-- convertEarmarkForRfaApproval() decrements one parent CBA earmark row and inserts a 'converted' row for
-- the RFA/LAA. Until now, reverseConversions() re-DERIVED the parent row at deletion time by re-matching
-- project_code against budget_allocation.parent_id — a re-match against state that may have changed since
-- approval, and impossible to do at all once an RFA/LAA can have several parents. Recording the source row
-- id at conversion time makes the reversal exact and order-independent.
-- NULL = a row converted before this column existed; reverseConversions() keeps a legacy re-match fallback
-- for those, keyed on the block's own parent_allocation_id (see migration 51) rather than the header's
-- parent_id. No backfill here — existing 'converted' rows are simply left NULL and fall back.
-- ============================================================
ALTER TABLE fims.budget_line_item_earmark
    ADD COLUMN IF NOT EXISTS source_earmark_id INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint WHERE conname = 'fk_budget_line_item_earmark_source'
    ) THEN
        ALTER TABLE fims.budget_line_item_earmark
        ADD CONSTRAINT fk_budget_line_item_earmark_source
        FOREIGN KEY (source_earmark_id) REFERENCES fims.budget_line_item_earmark(earmark_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_budget_line_item_earmark_source_earmark_id
    ON fims.budget_line_item_earmark (source_earmark_id);

COMMENT ON COLUMN fims.budget_line_item_earmark.source_earmark_id IS
    'For a converted RFA/LAA row: the exact parent-CBA earmark row it drew from. NULL = legacy row
     converted before this column existed — reverseConversions() falls back to re-matching by
     project_code against the block''s own parent_allocation_id for those.';
