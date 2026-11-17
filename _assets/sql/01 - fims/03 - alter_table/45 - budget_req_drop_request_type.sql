-- ============================================================
-- ALTER: fims.budget_req
-- Reverts "43 - budget_req_add_request_type.sql": a COE Budget Request does NOT get its
-- own separate header after all — it shares the SAME one budget_req row a regular
-- request would use for that fiscal year + office (found/created by the same
-- fetchHeaderByFiscalYearAndRc() both addProgram() and addCoeRequest() now call,
-- unfiltered by type). A header can therefore hold Programs, COE line items
-- (budget_line_items.budget_req_project_id IS NULL), or both at once — so a single
-- REGULAR/COE label on the header stopped being meaningful; "COE-ness" is now derived
-- per-request from whether it has any headless line items, not a stored column.
-- ============================================================

ALTER TABLE fims.budget_req
    DROP CONSTRAINT IF EXISTS uq_budget_req_fiscal_yr_rc_id_type;

ALTER TABLE fims.budget_req
    DROP CONSTRAINT IF EXISTS chk_budget_req_request_type;

ALTER TABLE fims.budget_req
    DROP COLUMN IF EXISTS request_type;

-- Dev/test data from while request_type existed: a (fiscal_yr, rc_id) pair may now have TWO
-- budget_req rows (one that was REGULAR, one that was COE) — duplicates the UNIQUE constraint
-- below is about to forbid. Collapse each such pair onto one survivor (the lowest
-- budget_reference_number) by re-pointing every child row's OWN budget_reference_number FK
-- (budget_req_program, budget_req_project, budget_line_items, budget_req_project_attachment —
-- all four are FK'd ON DELETE CASCADE straight to budget_req, not just transitively through
-- budget_req_program, so each needs its own UPDATE) onto the survivor, THEN deleting the
-- now-empty duplicate — never the other way around, or the cascade would take its data with it.
DO $$
DECLARE
    grp RECORD;
    survivor VARCHAR(20);
BEGIN
    FOR grp IN
        SELECT fiscal_yr, rc_id FROM fims.budget_req GROUP BY fiscal_yr, rc_id HAVING COUNT(*) > 1
    LOOP
        SELECT MIN(budget_reference_number) INTO survivor
        FROM fims.budget_req WHERE fiscal_yr = grp.fiscal_yr AND rc_id = grp.rc_id;

        UPDATE fims.budget_req_program SET budget_reference_number = survivor
        WHERE budget_reference_number IN (
            SELECT budget_reference_number FROM fims.budget_req
            WHERE fiscal_yr = grp.fiscal_yr AND rc_id = grp.rc_id AND budget_reference_number <> survivor
        );
        UPDATE fims.budget_req_project SET budget_reference_number = survivor
        WHERE budget_reference_number IN (
            SELECT budget_reference_number FROM fims.budget_req
            WHERE fiscal_yr = grp.fiscal_yr AND rc_id = grp.rc_id AND budget_reference_number <> survivor
        );
        UPDATE fims.budget_line_items SET budget_reference_number = survivor
        WHERE budget_reference_number IN (
            SELECT budget_reference_number FROM fims.budget_req
            WHERE fiscal_yr = grp.fiscal_yr AND rc_id = grp.rc_id AND budget_reference_number <> survivor
        );
        UPDATE fims.budget_req_project_attachment SET budget_reference_number = survivor
        WHERE budget_reference_number IN (
            SELECT budget_reference_number FROM fims.budget_req
            WHERE fiscal_yr = grp.fiscal_yr AND rc_id = grp.rc_id AND budget_reference_number <> survivor
        );

        DELETE FROM fims.budget_req
        WHERE fiscal_yr = grp.fiscal_yr AND rc_id = grp.rc_id AND budget_reference_number <> survivor;

        RAISE NOTICE 'Merged duplicate budget_req rows for fiscal_yr=%, rc_id=% onto %', grp.fiscal_yr, grp.rc_id, survivor;
    END LOOP;
END $$;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_budget_req_fiscal_yr_rc_id') THEN
        ALTER TABLE fims.budget_req
            ADD CONSTRAINT uq_budget_req_fiscal_yr_rc_id UNIQUE (fiscal_yr, rc_id);
    END IF;
END $$;
