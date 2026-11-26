-- ============================================================
-- ALTER: fims.budget_line_items
-- Adds sub_component_id — the line item's own Sub-Component (a flat classification, independent of
-- component_id/fims.component_type; see fims.sub_component_type's own doc comment). Nullable:
-- existing rows have none, and it is optional on the Budget Request form, same as Component.
-- ============================================================

ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS sub_component_id INTEGER;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'budget_line_items_sub_component_id_fkey') THEN
        ALTER TABLE fims.budget_line_items
            ADD CONSTRAINT budget_line_items_sub_component_id_fkey
            FOREIGN KEY (sub_component_id) REFERENCES fims.sub_component_type (sub_component_type_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_fims_budget_line_items_sub_component_id ON fims.budget_line_items (sub_component_id);

COMMENT ON COLUMN fims.budget_line_items.sub_component_id IS 'Sub-Component classification for this line item (fims.sub_component_type) — flat, independent of component_id.';
