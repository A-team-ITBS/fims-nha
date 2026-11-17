-- ============================================================
-- ALTER: fims.budget_req_project_attachment
-- A COE request's Supporting Documents have no Program/Project to key off — only the
-- header (budget_reference_number). Drops the NOT NULLs on budget_req_program_id /
-- project_code so an attachment can be headless (both NULL, scoped by
-- budget_reference_number alone) as well as Project-scoped (both NOT NULL, the
-- existing REGULAR-request shape) — never a mix of one NULL and the other set.
-- ============================================================

ALTER TABLE fims.budget_req_project_attachment
    ALTER COLUMN budget_req_program_id DROP NOT NULL,
    ALTER COLUMN project_code DROP NOT NULL;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_budget_req_project_attachment_scope') THEN
        ALTER TABLE fims.budget_req_project_attachment
            ADD CONSTRAINT chk_budget_req_project_attachment_scope
            CHECK (
                (budget_req_program_id IS NOT NULL AND project_code IS NOT NULL)
                OR (budget_req_program_id IS NULL AND project_code IS NULL)
            );
    END IF;
END $$;

-- The existing index is (budget_req_program_id, project_code) — useless for a
-- headless (COE) lookup, which only ever has budget_reference_number to go on.
CREATE INDEX IF NOT EXISTS idx_budget_req_project_attachment_brn
    ON fims.budget_req_project_attachment (budget_reference_number)
    WHERE budget_req_program_id IS NULL;

COMMENT ON COLUMN fims.budget_req_project_attachment.budget_req_program_id IS
    'NULL for a COE request''s headless attachment (scoped by budget_reference_number alone instead).';
COMMENT ON COLUMN fims.budget_req_project_attachment.project_code IS
    'NULL for a COE request''s headless attachment (scoped by budget_reference_number alone instead).';
