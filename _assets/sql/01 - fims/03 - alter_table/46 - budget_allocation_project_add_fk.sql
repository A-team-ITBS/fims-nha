-- ============================================================
-- ALTER: fims.budget_allocation_project
-- Foreign keys, added after all referenced tables exist. Postgres has no
-- ADD CONSTRAINT IF NOT EXISTS, so each one is guarded by a DO block.
-- Same precedent as "08 - budget_allocation_add_fk.sql".
-- ============================================================
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_project_allocation'
    ) THEN
        ALTER TABLE fims.budget_allocation_project
        ADD CONSTRAINT fk_budget_allocation_project_allocation
        FOREIGN KEY (allocation_id) REFERENCES fims.budget_allocation(allocation_id) ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_project_program'
    ) THEN
        ALTER TABLE fims.budget_allocation_project
        ADD CONSTRAINT fk_budget_allocation_project_program
        FOREIGN KEY (program_code) REFERENCES fims.program(program_code);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_project_sub_program'
    ) THEN
        ALTER TABLE fims.budget_allocation_project
        ADD CONSTRAINT fk_budget_allocation_project_sub_program
        FOREIGN KEY (sub_program_code) REFERENCES fims.sub_program(sub_program_code);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_budget_allocation_project_project'
    ) THEN
        ALTER TABLE fims.budget_allocation_project
        ADD CONSTRAINT fk_budget_allocation_project_project
        FOREIGN KEY (project_code) REFERENCES fims.project(project_code);
    END IF;
END $$;
