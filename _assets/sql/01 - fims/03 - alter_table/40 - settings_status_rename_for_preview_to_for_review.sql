-- ============================================================
-- ALTER: fims.settings_status (status_for = 'budget_allocation' — the Memo Request page)
-- The status a memo request takes when its requesting office submits it is now called 'For Review' (it was 'For Preview'):
-- the form's "Submit for Review" button, the list's "Submit for Review" action and BudgetAllocationService::REVIEW_STEPS all look it
-- up BY NAME. This renames it in a database that already has the old name, keeping its id — so every request that is 'For Preview' is
-- now 'For Review', and its colour and next_actors (the FMD Section Chief) stay as they are.
--
-- A new database gets 'For Review' straight from "02 - sample_data/06" and this file does nothing. If both names exist (the seed was
-- run again after the code was updated), the requests are pointed at the 'For Review' row and the old row is removed.
-- The status history (fims.status_change_log) is renamed with it, so a request's history reads the same name as its status.
-- Safe to re-run.
-- ============================================================

DO $$
DECLARE
    old_id INTEGER;
    new_id INTEGER;
BEGIN
    SELECT id INTO old_id FROM fims.settings_status
        WHERE status_for = 'budget_allocation' AND name = 'For Preview' ORDER BY id LIMIT 1;

    IF old_id IS NOT NULL THEN
        SELECT id INTO new_id FROM fims.settings_status
            WHERE status_for = 'budget_allocation' AND name = 'For Review' ORDER BY id LIMIT 1;

        IF new_id IS NULL THEN
            UPDATE fims.settings_status SET name = 'For Review' WHERE id = old_id;
        ELSE
            UPDATE fims.budget_allocation SET status_id = new_id WHERE status_id = old_id;
            DELETE FROM fims.settings_status WHERE id = old_id;
        END IF;
    END IF;

    IF to_regclass('fims.status_change_log') IS NOT NULL THEN
        UPDATE fims.status_change_log
           SET status = 'For Review'
         WHERE status_for = 'budget_allocation' AND status = 'For Preview';
    END IF;
END $$;
