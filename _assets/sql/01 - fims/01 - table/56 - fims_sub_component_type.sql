-- Lookup of sub-component classifications (Project Development - Carry-Over Works, Housing Support,
-- Land Acquisition/Assembly, etc.) — a flat list, independent of fims.component_type (no parent/child
-- relationship between the two; see Budget Request form's own "Component"/"Sub-Component" fields).
-- Referenced by fims.budget_line_items.sub_component_id.
-- Soft-deleted via deleted_at, same as fims.component_type.
CREATE TABLE IF NOT EXISTS fims.sub_component_type (
    sub_component_type_id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fims_sub_component_type_deleted_at ON fims.sub_component_type (deleted_at);

-- Seed: guarded so re-running this file doesn't duplicate rows, since name
-- has no unique constraint. Inserted in this order, so a fresh table gets
-- ids 1-9.
INSERT INTO fims.sub_component_type (name)
SELECT v.name
FROM (VALUES
    ('PROJECT DEVELOPMENT - CARRY-OVER WORKS'),
    ('PROJECT DEVELOPMENT - NEW WORKS'),
    ('HOUSING SUPPORT'),
    ('LAND ACQUISITION/ASSEMBLY'),
    ('OTHER PROJECT RELATED CAPITAL OUTLAY'),
    ('NHA BUILDING CONSTRUCTION AND REPAIR'),
    ('EQUIPMENT OUTLAY'),
    ('PERSONNEL SERVICES'),
    ('MOOE')
) AS v(name)
WHERE NOT EXISTS (SELECT 1 FROM fims.sub_component_type c WHERE c.name = v.name);
