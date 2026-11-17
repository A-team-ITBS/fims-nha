-- ============================================================
-- ALTER: fims.budget_revision
--   * adds `request_type` — what kind of request it is:
--       REVISION     no To line (to_budget_line_item_id IS NULL): the From line's amount changes
--       REALIGNMENT  a To line: funds move from the From line to it
--     Every existing request is one or the other, so it is filled in from its To line: no To line -> REVISION, a To line ->
--     REALIGNMENT. From now on the API stores it when a request is filed and keeps it in step with the To line when one is edited.
--   * drops `from_budget_req_id` and `to_budget_req_id` — legacy and unused since "23 - budget_revision_on_budget_reference_number":
--     they held a budget REQUEST's id, which no longer exists (a request is now a header keyed by budget_reference_number), and
--     nothing in the app reads or writes them. Budget revisions point at budget LINES (from_budget_line_item_id / to_budget_line_item_id).
--
-- DESTRUCTIVE: the values in the two dropped columns are lost. They only ever held ids of budget requests that no longer exist, but
-- take a backup first if they might be needed.
-- A new database gets the table already in this shape from "01 - table/05"; this file brings the others up to date.
-- Idempotent (IF NOT EXISTS / IF EXISTS guards): safe to run any number of times, on any environment.
-- ============================================================

ALTER TABLE fims.budget_revision ADD COLUMN IF NOT EXISTS request_type VARCHAR(20);

-- the type of every request that has none yet, from its To line (a request filed before the budget-line columns existed has none: REVISION)
UPDATE fims.budget_revision
   SET request_type = CASE WHEN to_budget_line_item_id IS NULL THEN 'REVISION' ELSE 'REALIGNMENT' END
 WHERE request_type IS NULL;

ALTER TABLE fims.budget_revision ALTER COLUMN request_type SET DEFAULT 'REVISION';
ALTER TABLE fims.budget_revision ALTER COLUMN request_type SET NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
         WHERE conname = 'chk_budget_revision_request_type'
           AND conrelid = 'fims.budget_revision'::regclass
    ) THEN
        ALTER TABLE fims.budget_revision
            ADD CONSTRAINT chk_budget_revision_request_type CHECK (request_type IN ('REVISION', 'REALIGNMENT'));
    END IF;
END
$$;

COMMENT ON COLUMN fims.budget_revision.request_type IS
    'REVISION (no To line: the From line''s amount changes) or REALIGNMENT (a To line: funds move from the From line to it).';

ALTER TABLE fims.budget_revision DROP COLUMN IF EXISTS from_budget_req_id;
ALTER TABLE fims.budget_revision DROP COLUMN IF EXISTS to_budget_req_id;
