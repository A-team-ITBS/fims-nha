-- Seed data for fims.settings_status, status_for = 'budget_revision' (fims.budget_revision — the Budget Revision Requests page).
--
--   Pending   a revision request that was filed and waits for the Financial Management Department (FMD)
--   Approved  FMD approved it: the change was applied to the budget line(s) (BudgetRevisionService::approve())
--   Declined  FMD declined it: nothing was changed
--
-- Until now fims.budget_revision.status_id held bare numbers (1 = Pending, 2 = Approved, 3 = Declined) hard-coded in
-- BudgetRevisionService, with no status table behind them — and those numbers are also real ids of OTHER modules' statuses
-- in fims.settings_status. BudgetRevisionService now looks the three statuses up BY NAME (status_for = 'budget_revision'), like
-- the Memo Request and Budget Request modules do, so the names below must stay as they are (the API says so, and changes
-- nothing, when one is missing). The colour is what the page paints the status badge with.
--
-- This file does three things, in this order, and is safe to run any number of times:
--   1. adds the three statuses (guarded with WHERE NOT EXISTS);
--   2. moves every existing revision from the old number to the id of its new status — once: a row that already points at a
--      'budget_revision' status is left alone, and update_at is not touched (the page shows "Approved on <update_at>");
--   3. adds the foreign key budget_revision.status_id -> settings_status.id (like budget_req.status_id has), unless some row still
--      has a status_id that matches no status at all — then it only says so and skips it.
-- It lives in 04 - seed_data (after 03 - alter_table) for the same reason as "04 - memo_request_review_chain_statuses.sql".

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Pending', 'budget_revision', '#D97706'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Pending' AND status_for = 'budget_revision');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Approved', 'budget_revision', '#16A34A'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Approved' AND status_for = 'budget_revision');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Declined', 'budget_revision', '#DC3545'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Declined' AND status_for = 'budget_revision');

-- 1 / 2 / 3 -> the ids of Pending / Approved / Declined. One statement, so no row is mapped twice.
UPDATE fims.budget_revision rv
SET status_id = s.id
FROM (VALUES (1, 'Pending'), (2, 'Approved'), (3, 'Declined')) AS v(old_id, status_name)
JOIN fims.settings_status s ON s.status_for = 'budget_revision' AND s.name = v.status_name
WHERE rv.status_id = v.old_id
  AND NOT EXISTS (SELECT 1 FROM fims.settings_status x WHERE x.id = rv.status_id AND x.status_for = 'budget_revision');

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_budget_revision_status' AND conrelid = 'fims.budget_revision'::regclass) THEN
        RETURN;
    END IF;

    IF EXISTS (SELECT 1 FROM fims.budget_revision rv WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status s WHERE s.id = rv.status_id)) THEN
        RAISE NOTICE 'fims.budget_revision has a status_id that is not in fims.settings_status: the foreign key was NOT added. Fix those rows and run this file again.';
    ELSE
        ALTER TABLE fims.budget_revision
            ADD CONSTRAINT fk_budget_revision_status FOREIGN KEY (status_id) REFERENCES fims.settings_status (id);
    END IF;
END $$;

COMMENT ON COLUMN fims.budget_revision.status_id IS 'fims.settings_status.id of a status_for = ''budget_revision'' row: Pending / Approved / Declined (looked up by name — see BudgetRevisionService).';
