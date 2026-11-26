-- ============================================================
-- ALTER: the project of a SUPPLEMENTAL budget request now looks like the project of a Budget Request
--
-- A supplemental project used to be a name + location with a table of line items (account, particulars, amount) under it, and the Program /
-- Sub-Program were the request's own. It is now the same card the Budget Request form uses for a project — Region, Project, Location, Type of
-- Fund, Component, Sub-Component, ONE Proposed Amount, Supporting Documents — and a request can hold several Program blocks, each with its own
-- projects. So the project row ("01 - table/57") carries all of that:
--   program_code, sub_program_code   the Program block the project is under
--   rc_id                            the Region (fims.responsibility_code.rc_id, an office of type Regional) — becomes fims.project.rc_id on approval
--   type_of_fund_id                  fims.type_of_fund            (required on the form)
--   component_id                     fims.component_type          (optional)
--   sub_component_id                 fims.sub_component_type      (optional)
--   proposed_amount                  the project's amount; on approval ONE budget line (account 214, as the Budget Request does) is created with it
--   budget_line_item_id              the budget line created on approval (NULL until then; no FK — a line can be re-created)
-- project_code stays NULL until approval, except when the user PICKED an existing project in the Project field (then its code is already known).
--
-- What this file does to existing data:
--   1. Adds the columns above.
--   2. Backfills them from what a request already holds: the request's own supp_program_code / supp_sub_program_code go onto each of its projects;
--      proposed_amount = the sum of the project's line items; rc_id = the existing project's region, or else the filer's office.
--   3. Drops supp_program_code / supp_sub_program_code from fims.budget_revision (the Program is per project now). The request keeps the SARO
--      (supp_fund_tracking_id) and its fiscal year (supp_fiscal_yr), which are shared by every project.
--   4. fims.budget_revision_supplemental_item (the line items, "01 - table/56") is NOT dropped: nothing writes to it any more, and it is kept as the
--      record of what older requests were typed with. Nothing reads it either.
--
-- Steps 2 and 3 run only while supp_program_code still exists, so the file can be run again (and on a database that never had it) without effect.
-- Idempotent (IF NOT EXISTS / guarded): safe to run any number of times, on any environment.
-- ============================================================

ALTER TABLE fims.budget_revision_supplemental_project
    ADD COLUMN IF NOT EXISTS program_code        VARCHAR(64),
    ADD COLUMN IF NOT EXISTS sub_program_code    VARCHAR(64),
    ADD COLUMN IF NOT EXISTS rc_id               VARCHAR(10),
    ADD COLUMN IF NOT EXISTS type_of_fund_id     INTEGER,
    ADD COLUMN IF NOT EXISTS component_id        INTEGER,
    ADD COLUMN IF NOT EXISTS sub_component_id    INTEGER,
    ADD COLUMN IF NOT EXISTS proposed_amount     NUMERIC(18,2),
    ADD COLUMN IF NOT EXISTS budget_line_item_id INTEGER;

COMMENT ON COLUMN fims.budget_revision_supplemental_project.program_code IS 'The Program block the project is under (fims.program.program_code, soft reference — checked by the API).';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.sub_program_code IS 'The Sub-Program of that block (fims.sub_program.sub_program_code), optional; must belong to program_code.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.rc_id IS 'The Region (fims.responsibility_code.rc_id): becomes fims.project.rc_id when a NEW project is created on approval.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.type_of_fund_id IS 'fims.type_of_fund.type_of_fund_id — copied to the budget line created on approval.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.component_id IS 'fims.component_type.component_type_id (optional) — copied to the budget line created on approval.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.sub_component_id IS 'fims.sub_component_type.sub_component_type_id (optional) — copied to the budget line created on approval.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.proposed_amount IS 'The project''s amount, more than zero. On approval ONE budget line (account 214) is created with it as proposed_amount and as the SUPPLEMENTAL ledger amount.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.budget_line_item_id IS 'The fims.budget_line_items row created for this project on approval. NULL until then. No FK (a budget line can be re-created).';

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'fims' AND table_name = 'budget_revision' AND column_name = 'supp_program_code') THEN

        -- 2 · what an existing request already holds
        UPDATE fims.budget_revision_supplemental_project p
           SET program_code     = COALESCE(p.program_code, rv.supp_program_code),
               sub_program_code = COALESCE(p.sub_program_code, NULLIF(rv.supp_sub_program_code, ''))
          FROM fims.budget_revision rv
         WHERE rv.id = p.budget_revision_id;

        UPDATE fims.budget_revision_supplemental_project p
           SET proposed_amount = s.total
          FROM (SELECT supplemental_project_id, SUM(amount) AS total
                  FROM fims.budget_revision_supplemental_item
                 GROUP BY supplemental_project_id) s
         WHERE s.supplemental_project_id = p.supplemental_project_id
           AND p.proposed_amount IS NULL;

        UPDATE fims.budget_revision_supplemental_project p
           SET rc_id = pj.rc_id
          FROM fims.project pj
         WHERE pj.project_code = p.project_code
           AND p.rc_id IS NULL;

        UPDATE fims.budget_revision_supplemental_project p
           SET rc_id = u.rc_id
          FROM fims.budget_revision rv
          JOIN uac.users u ON u.user_id = rv.user_id
         WHERE rv.id = p.budget_revision_id
           AND p.rc_id IS NULL
           AND u.rc_id IS NOT NULL;

        -- 3 · the Program is per project now (only once every project has it)
        IF NOT EXISTS (SELECT 1 FROM fims.budget_revision_supplemental_project WHERE program_code IS NULL) THEN
            ALTER TABLE fims.budget_revision
                DROP COLUMN IF EXISTS supp_program_code,
                DROP COLUMN IF EXISTS supp_sub_program_code;
        ELSE
            RAISE NOTICE 'some supplemental projects have no program_code; supp_program_code / supp_sub_program_code were NOT dropped.';
        END IF;
    END IF;

    -- the amount is more than zero (a project whose request had no line items yet stays NULL)
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_budget_revision_supplemental_project_amount') THEN
        ALTER TABLE fims.budget_revision_supplemental_project
            ADD CONSTRAINT chk_budget_revision_supplemental_project_amount CHECK (proposed_amount IS NULL OR proposed_amount > 0);
    END IF;

    -- a project always has its Program; NOT NULL once every row does
    IF NOT EXISTS (SELECT 1 FROM fims.budget_revision_supplemental_project WHERE program_code IS NULL) THEN
        ALTER TABLE fims.budget_revision_supplemental_project ALTER COLUMN program_code SET NOT NULL;
    END IF;
END $$;

COMMENT ON TABLE fims.budget_revision_supplemental_item IS 'LEGACY — the line items (account, particulars, amount) older SUPPLEMENTAL requests were typed with. Nothing writes to or reads from it any more: a supplemental project now carries ONE proposed_amount (fims.budget_revision_supplemental_project, "03 - alter_table/65"). Kept so that data is not lost.';
