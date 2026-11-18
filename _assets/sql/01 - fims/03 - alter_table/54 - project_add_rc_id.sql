-- ============================================================
-- ALTER: fims.project
-- Adds rc_id — the Responsibility Center of the user who created the
-- project, mirroring fims.budget_req.rc_id (see "10 -
-- budget_req_add_rc_id.sql"). Auto-detected server-side from the
-- creating user's own uac.users.rc_id (GlobalService::getUserRcId()) in
-- ProjectService::create() — there is no manual RC picker on the Project
-- field (Budget Request form's "type a name to search-or-create" picker).
--
-- Nullable: pre-existing projects predate this column and have no
-- creator RC to backfill.
-- ============================================================

ALTER TABLE fims.project
    ADD COLUMN IF NOT EXISTS rc_id VARCHAR(10);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'project_rc_id_fkey') THEN
        ALTER TABLE fims.project
            ADD CONSTRAINT project_rc_id_fkey
            FOREIGN KEY (rc_id) REFERENCES fims.responsibility_code (rc_id);
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_fims_project_rc_id ON fims.project (rc_id);
