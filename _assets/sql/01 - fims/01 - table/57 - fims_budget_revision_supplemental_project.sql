-- ============================================================
-- fims.budget_revision_supplemental_project
-- The PROJECTS of a SUPPLEMENTAL budget request (fims.budget_revision.request_type = 'SUPPLEMENTAL').
--
-- One SARO releases the money for a supplemental request, and that SARO can fund SEVERAL new projects, so a request carries a list of
-- projects (one row each here) and every project carries its own line items (fims.budget_revision_supplemental_item.supplemental_project_id,
-- "03 - alter_table/64"). The SARO and the Program / Sub-Program the projects go under are the request's own (the supp_* columns on
-- fims.budget_revision, "03 - alter_table/62") — they are shared by all of its projects.
--
-- While the request is Saved / Submitted the projects do not exist yet, so what the user typed (name, location) is kept here and replaced as a whole when
-- the request is edited. They are only created in fims.project when FMD APPROVES the request (BudgetRevisionService::approveSupplemental()); the code that is
-- GENERATED for each project at that moment is written back to project_code (NULL until then — the form never asks for it).
--
-- sort_order keeps the order the projects were entered in. No FK to fims.project: the project does not exist before the approval.
--
-- Idempotent (IF NOT EXISTS): safe to run any number of times, on any environment.
-- ============================================================

CREATE TABLE IF NOT EXISTS fims.budget_revision_supplemental_project (
    supplemental_project_id SERIAL PRIMARY KEY,
    budget_revision_id      INTEGER      NOT NULL REFERENCES fims.budget_revision(id) ON DELETE CASCADE,
    sort_order              INTEGER      NOT NULL DEFAULT 0,
    project_name            VARCHAR(255) NOT NULL,
    location                VARCHAR(255),
    project_code            VARCHAR(64),
    created_at              TIMESTAMP    NOT NULL DEFAULT NOW()
);

COMMENT ON TABLE fims.budget_revision_supplemental_project IS 'The projects of a SUPPLEMENTAL budget request (one SARO, one Program / Sub-Program, one or more projects). Each has its own line items (budget_revision_supplemental_item.supplemental_project_id). Created in fims.project only when the request is approved.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.sort_order IS 'The order the projects were entered in on the form (0-based).';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.project_name IS 'The new project''s name, as typed on the form. Unique (ignoring case) within its request.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.location IS 'The new project''s location (optional).';
COMMENT ON COLUMN fims.budget_revision_supplemental_project.project_code IS 'The project code GENERATED (or, for a project of that name already in the program, found) when the request is approved. NULL before that. Not shown on the form.';

-- budget_revision_supplemental_project indexes
CREATE INDEX IF NOT EXISTS idx_budget_revision_supplemental_project_revision ON fims.budget_revision_supplemental_project (budget_revision_id);
