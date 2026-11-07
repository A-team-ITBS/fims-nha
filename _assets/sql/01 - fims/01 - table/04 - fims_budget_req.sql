-- One row = an amount allocated to either a program or a project for a given fiscal year.
-- Exactly one of program_code / project_code is set per row.
CREATE TABLE IF NOT EXISTS fims.budget_req (
    id SERIAL PRIMARY KEY,
    fiscal_yr SMALLINT NOT NULL REFERENCES fims.fiscal_year(fiscal_yr),
    program_code VARCHAR(20) REFERENCES fims.program(program_code),
    project_code VARCHAR(20) REFERENCES fims.project(project_code),
    amount NUMERIC(15, 2) NOT NULL,
    CONSTRAINT chk_budget_req_program_xor_project
        CHECK ((program_code IS NOT NULL) <> (project_code IS NOT NULL))
);
