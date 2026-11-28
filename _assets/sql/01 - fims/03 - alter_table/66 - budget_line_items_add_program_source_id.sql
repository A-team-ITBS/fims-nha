-- ============================================================
-- ALTER: fims.budget_line_items
-- Adds program_source_id — the line item's own "Program (Source)" (fims.program_source, a flat list
-- of specific named source programs/projects), optional, independent of type_of_fund_id/component_id/
-- sub_component_id. Nullable: existing rows have none, and it is optional on the Budget Request form,
-- same as Component/Sub-Component.
-- ============================================================

ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS program_source_id INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_line_items_program_source_id_fkey') THEN
        ALTER TABLE fims.budget_line_items
            ADD CONSTRAINT budget_line_items_program_source_id_fkey
            FOREIGN KEY (program_source_id) REFERENCES fims.program_source (program_source_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_fims_budget_line_items_program_source_id ON fims.budget_line_items (program_source_id);

COMMENT ON COLUMN fims.budget_line_items.program_source_id IS 'Program (Source) classification for this line item (fims.program_source) — flat, optional, independent of type_of_fund_id/component_id/sub_component_id.';
