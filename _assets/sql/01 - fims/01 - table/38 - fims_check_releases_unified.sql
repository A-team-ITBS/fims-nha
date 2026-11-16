-- ============================================================================
-- Check Releases (unified) — replaces the two previously-separate tables
-- fims.check_claimant_releases (Tab 3, Released to Claimants) and
-- fims.check_region_releases (Tab 4, Released to Regions/Departments), which
-- were themselves ports of two separate TREASURY pages. Merged into one
-- release_type-discriminated table per the decision to present these as one
-- "Released" tab/workflow instead of two:
--
--   release_type = DIRECT        -> handed to the payee immediately, no email.
--   release_type = EMAIL_NOTICE  -> payee notified by email (claimant_email),
--                                   starts PENDING until sendReleaseNotice()
--                                   flips it to NOTIFIED.
--   release_type = REGION        -> routed through a department; always
--                                   notified later, in bulk, via
--                                   sendRegionReleaseNotice() rather than at
--                                   save time — there is no NOTIFIED step for
--                                   this type (it goes PENDING -> RELEASED).
--
-- Columns that only apply to one release_type stay NULL for the others
-- (department_id/region_dept/rdtf_no/dv_* are REGION-only; claimant_email is
-- EMAIL_NOTICE-only; last_follow_up_date/follow_up_remarks are mainly used
-- by claimant-type "Unclaimed" follow-up, though nothing stops a REGION row
-- using them too) — same reconciliation approach as every other "superset of
-- two divergent tables" merge in this schema (e.g. fims.fund_tracking).
--
-- id_number additionally absorbs the old check_region_releases.id_submitted
-- (captured at acknowledgment time for either release_type, not just
-- REGION) — one column instead of two differently-named ones doing the same
-- job.
--
-- department_id is a real FK (uac.departments); region_dept stays a
-- snapshot of the department's name/acronym at release time, same
-- convention as disbursement_vouchers.rfa_allocation_number snapshotting
-- fims.budget_allocation — a later department rename/merge doesn't change
-- what a historical release displays.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_releases();
ALTER TABLE fims.check_releases
ADD COLUMN IF NOT EXISTS release_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS check_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS release_type VARCHAR(20) NOT NULL DEFAULT 'DIRECT', -- DIRECT | EMAIL_NOTICE | REGION
ADD COLUMN IF NOT EXISTS claimant_type VARCHAR(20) DEFAULT 'PAYEE', -- PAYEE | AUTHORIZED_REP | ORIGINATING_UNIT | REGION_DEPT
ADD COLUMN IF NOT EXISTS claimant_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS claimant_email VARCHAR(150),
ADD COLUMN IF NOT EXISTS id_type VARCHAR(50),
ADD COLUMN IF NOT EXISTS id_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS department_id INTEGER,
ADD COLUMN IF NOT EXISTS region_dept VARCHAR(150),
ADD COLUMN IF NOT EXISTS rdtf_no VARCHAR(50),
ADD COLUMN IF NOT EXISTS dv_returned BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS dv_reference VARCHAR(50),
ADD COLUMN IF NOT EXISTS dv_return_date DATE,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'PENDING', -- PENDING|NOTIFIED|RELEASED|ACKNOWLEDGED|UNCLAIMED
ADD COLUMN IF NOT EXISTS release_date DATE,
ADD COLUMN IF NOT EXISTS acknowledgment_date DATE,
ADD COLUMN IF NOT EXISTS last_follow_up_date DATE,
ADD COLUMN IF NOT EXISTS follow_up_remarks TEXT,
ADD COLUMN IF NOT EXISTS remarks TEXT,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_releases IS 'Unified check-release records — DIRECT/EMAIL_NOTICE (to a claimant) or REGION (to a department); backs the single "Released" tab''s List, Unclaimed, and DV Returns sub-tabs';

CREATE INDEX IF NOT EXISTS idx_check_releases_check_id      ON fims.check_releases (check_id);
CREATE INDEX IF NOT EXISTS idx_check_releases_status        ON fims.check_releases (status);
CREATE INDEX IF NOT EXISTS idx_check_releases_release_type  ON fims.check_releases (release_type);
CREATE INDEX IF NOT EXISTS idx_check_releases_department_id ON fims.check_releases (department_id);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_releases_department') THEN
        ALTER TABLE fims.check_releases
        ADD CONSTRAINT fk_check_releases_department
        FOREIGN KEY (department_id) REFERENCES uac.departments(department_id);
    END IF;
END $$;

-- ============================================================================
-- Check Release Email Log (unified) — replaces check_claimant_email_log and
-- check_region_email_log. A claimant EMAIL_NOTICE send is a single check
-- (release_id/check_id populated, department_id/region_dept/checks_count/
-- total_amount NULL); a region bulk monthly notice bundles many releases
-- into one email (department_id/region_dept/checks_count/total_amount
-- populated, release_id/check_id NULL) — same "which columns are NULL
-- depends on the row's shape" approach as check_releases itself.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_release_email_log();
ALTER TABLE fims.check_release_email_log
ADD COLUMN IF NOT EXISTS log_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS release_id INTEGER,
ADD COLUMN IF NOT EXISTS check_id INTEGER,
ADD COLUMN IF NOT EXISTS department_id INTEGER,
ADD COLUMN IF NOT EXISTS region_dept VARCHAR(150),
ADD COLUMN IF NOT EXISTS checks_count INTEGER,
ADD COLUMN IF NOT EXISTS total_amount NUMERIC(18,2),
ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(150) NOT NULL,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'SENT', -- SENT | FAILED
ADD COLUMN IF NOT EXISTS error_message TEXT,
ADD COLUMN IF NOT EXISTS sent_by INTEGER,
ADD COLUMN IF NOT EXISTS sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_release_email_log IS 'Email-notice send attempts for the unified "Released" tab — single-check (claimant EMAIL_NOTICE) or bundled multi-check (region monthly notice)';

CREATE INDEX IF NOT EXISTS idx_check_release_email_log_release_id    ON fims.check_release_email_log (release_id);
CREATE INDEX IF NOT EXISTS idx_check_release_email_log_check_id      ON fims.check_release_email_log (check_id);
CREATE INDEX IF NOT EXISTS idx_check_release_email_log_department_id ON fims.check_release_email_log (department_id);
CREATE INDEX IF NOT EXISTS idx_check_release_email_log_sent_at       ON fims.check_release_email_log (sent_at);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_check_release_email_log_department') THEN
        ALTER TABLE fims.check_release_email_log
        ADD CONSTRAINT fk_check_release_email_log_department
        FOREIGN KEY (department_id) REFERENCES uac.departments(department_id);
    END IF;
END $$;
