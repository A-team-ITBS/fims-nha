-- ============================================================
-- ALTER: fims.settings_status (status_for = 'budget_req' — the Budget Requirements / Budget Proposal pages)
-- The status a budget request takes once the Budget Officer's step is done is now called 'For Endorsement'
-- (it was 'Endorsed'): BudgetReqService::REVIEW_STEPS, submitForReview() and endorse() all look it up BY NAME.
-- A CO (Program-based) request, which the Budget Officer now files themselves, goes straight to this status
-- from Draft — there is no one else's review to wait through first, so the old name ("Endorsed", implying
-- something had already been formally endorsed by someone else) stopped fitting; a COE request still reaches
-- it the same way as before (Budget Processing's "Endorse" action, from "For Review").
--
-- This renames it in a database that already has the old name, keeping its id — so every request that is
-- 'Endorsed' is now 'For Endorsement', and its colour and next_actors (the Budget Head) stay as they are.
--
-- A new database gets 'For Endorsement' straight from "02 - sample_data/01" and this file does nothing. If
-- both names exist (the seed was run again after the code was updated), the requests are pointed at the
-- 'For Endorsement' row and the old row is removed. Same pattern as "40 - settings_status_rename_for_preview_to_for_review.sql".
-- The status history (fims.status_change_log) is renamed with it, so a request's history reads the same
-- name as its status. Safe to re-run.
-- ============================================================

DO $$
DECLARE
    old_id INTEGER;
    new_id INTEGER;
BEGIN
    SELECT id INTO old_id FROM fims.settings_status
        WHERE status_for = 'budget_req' AND name = 'Endorsed' ORDER BY id LIMIT 1;

    IF old_id IS NOT NULL THEN
        SELECT id INTO new_id FROM fims.settings_status
            WHERE status_for = 'budget_req' AND name = 'For Endorsement' ORDER BY id LIMIT 1;

        IF new_id IS NULL THEN
            UPDATE fims.settings_status SET name = 'For Endorsement' WHERE id = old_id;
        ELSE
            UPDATE fims.budget_req SET status_id = new_id WHERE status_id = old_id;
            DELETE FROM fims.settings_status WHERE id = old_id;
        END IF;
    END IF;

    IF to_regclass('fims.status_change_log') IS NOT NULL THEN
        UPDATE fims.status_change_log
           SET status = 'For Endorsement'
         WHERE status_for = 'budget_req' AND status = 'Endorsed';
    END IF;
END $$;
