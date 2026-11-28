-- Lookup of "Program (Sources)" — specific named source programs/projects (resettlement programs,
-- disaster-response housing projects, etc.), a flat list independent of fims.program (which only
-- holds the two broad categories RESETTLEMENT/HOUSING ASSISTANCE FOR CALAMITY VICTIMS — see the
-- Budget Request form's own "Program"/"Sub-Program" fields). Referenced by
-- fims.budget_line_items.program_source_id.
-- Soft-deleted via deleted_at, same as fims.sub_component_type.
CREATE TABLE IF NOT EXISTS fims.program_source (
    program_source_id SERIAL PRIMARY KEY,
    name VARCHAR(500) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_fims_program_source_deleted_at ON fims.program_source (deleted_at);

-- Seed: guarded so re-running this file doesn't duplicate rows, since name has no unique
-- constraint. ORDER BY ord makes the rows go in exactly this order, so a fresh table gets ids 1-26
-- in list order — without it Postgres is free to insert them (and hand out the serial ids) in any
-- order, same caveat fims.fund_tracking_type's own seed documents.
INSERT INTO fims.program_source (name)
SELECT v.name
FROM (VALUES
    (1, 'Resettlement Program'),
    (2, 'Housing Program for Calamity Victims'),
    (3, 'Housing Support'),
    (4, 'Resettlement Program for ISFs Affected by DOTR Infrastructure Projects'),
    (5, 'Resettlement Project in Talisay, Batangas for families affected by the Taal Volcano eruption'),
    (6, 'Site acquisition, development and shelter construction, Tiwi, Albay'),
    (7, 'Relocation of ISFs in Brgys. Cuta and Sta. Clara, Batangas'),
    (8, 'For Families Affected by the Construction of Bukidnon Airport'),
    (9, 'Regional Resettlement Program-Socialized Housing at Maslabeng Buluan, Maguindanao'),
    (10, 'Cagayan De Oro City ISF Housing Project in Brgy. San Simon, Cagayan De Oro City'),
    (11, 'Housing Assistance Program for Indigenous People'),
    (12, 'Housing Program for Indigenous Peoples-Badjau Housing Project in Zamboanga City'),
    (13, 'Construction of four (4) units of five-storey low-rise residential buildings in Region VI for the resettlement of ISFs'),
    (14, 'Resettlement Project in Brgy. Mauraro, Guinobatan, Albay for families affected by the Mayon Volcano eruption (Phase 2)'),
    (15, 'Emergency Housing Assistance Program'),
    (16, 'Comprehensive and Integrated Housing Program'),
    (17, 'Resettlement Program For ISFs living in Danger Areas in Metro Manila'),
    (18, 'Resettlement Program For ISF''s Affected by the SC''s Mandamus to Clean-up the Manila Bay Area'),
    (19, 'Quick Response Fund'),
    (20, 'Resettlement Project - Maanyag Maramag Pabahay in Brgy. Anahawon, Maramag, Bukidnon'),
    (21, 'Resettlement Project - Quezon Residences Phase 1 in Brgy. Poblacion, Quezon, Bukidnon'),
    (22, 'Corporate Receipts'),
    (23, 'Pasig-Marikina River Channel Improvement Project Phase IV'),
    (24, 'MMFMP'),
    (25, 'Technical Services for the conduct of CoHSEP-TCV of 5,000 ISFS Residing within Six Priority Areas in Pasay City of the MIAA'),
    (26, 'Pasig River Urban Development')
) AS v(ord, name)
WHERE NOT EXISTS (SELECT 1 FROM fims.program_source p WHERE p.name = v.name)
ORDER BY v.ord;
