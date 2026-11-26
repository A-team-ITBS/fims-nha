-- ============================================================
-- ALTER: a SUPPLEMENTAL budget request may now carry SEVERAL projects
--
-- Until now one request was one project: its name, location and generated code were the supp_project_name / supp_location / supp_project_code columns of
-- fims.budget_revision ("03 - alter_table/62"), and every line item of fims.budget_revision_supplemental_item ("01 - table/56") belonged to that one project.
-- The SARO can fund several projects, so the projects now live in their own table, fims.budget_revision_supplemental_project ("01 - table/57", one row per
-- project, in the order entered), and each line item says which project it belongs to:
--   1. fims.budget_revision_supplemental_item.supplemental_project_id   the project the line item belongs to (NOT NULL once every row has one).
--   2. Every existing supplemental request becomes a request with ONE project: a project row is made from its supp_project_name / supp_location /
--      supp_project_code, and its line items are pointed at it.
--   3. The three columns that held that one project are dropped from fims.budget_revision. What stays on the request is what all of its projects share:
--      supp_fiscal_yr, supp_fund_tracking_id (the SARO), supp_program_code and supp_sub_program_code.
--
-- Steps 2 and 3 run only while supp_project_name still exists, so the file can be run again (and on a database that never had those columns) without effect.
-- Idempotent (IF NOT EXISTS / guarded): safe to run any number of times, on any environment.
-- ============================================================

ALTER TABLE fims.budget_revision_supplemental_item
    ADD COLUMN IF NOT EXISTS supplemental_project_id INTEGER
        REFERENCES fims.budget_revision_supplemental_project(supplemental_project_id) ON DELETE CASCADE;

COMMENT ON COLUMN fims.budget_revision_supplemental_item.supplemental_project_id IS 'The project (fims.budget_revision_supplemental_project) this line item belongs to. Its request is budget_revision_id.';

CREATE INDEX IF NOT EXISTS idx_budget_revision_supplemental_item_project ON fims.budget_revision_supplemental_item (supplemental_project_id);

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'fims' AND table_name = 'budget_revision' AND column_name = 'supp_project_name') THEN

        -- 2 · one project per existing supplemental request (a request that already has a project row is left alone)
        INSERT INTO fims.budget_revision_supplemental_project (budget_revision_id, sort_order, project_name, location, project_code)
        SELECT rv.id, 0, rv.supp_project_name, rv.supp_location, rv.supp_project_code
          FROM fims.budget_revision rv
         WHERE rv.request_type = 'SUPPLEMENTAL'
           AND rv.supp_project_name IS NOT NULL
           AND NOT EXISTS (SELECT 1 FROM fims.budget_revision_supplemental_project p WHERE p.budget_revision_id = rv.id);

        UPDATE fims.budget_revision_supplemental_item i
           SET supplemental_project_id = p.supplemental_project_id
          FROM fims.budget_revision_supplemental_project p
         WHERE p.budget_revision_id = i.budget_revision_id
           AND i.supplemental_project_id IS NULL;

        -- 3 · the columns that held the one project (only once nothing is left to carry over)
        IF NOT EXISTS (SELECT 1 FROM fims.budget_revision_supplemental_item WHERE supplemental_project_id IS NULL) THEN
            ALTER TABLE fims.budget_revision
                DROP COLUMN IF EXISTS supp_project_name,
                DROP COLUMN IF EXISTS supp_location,
                DROP COLUMN IF EXISTS supp_project_code;
        ELSE
            RAISE NOTICE 'fims.budget_revision_supplemental_item has line items that belong to no project; supp_project_name / supp_location / supp_project_code were NOT dropped.';
        END IF;
    END IF;

    -- 1 · every line item has its project
    IF NOT EXISTS (SELECT 1 FROM fims.budget_revision_supplemental_item WHERE supplemental_project_id IS NULL) THEN
        ALTER TABLE fims.budget_revision_supplemental_item ALTER COLUMN supplemental_project_id SET NOT NULL;
    END IF;
END $$;
