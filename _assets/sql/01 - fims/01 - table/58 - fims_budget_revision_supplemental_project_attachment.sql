-- ============================================================
-- fims.budget_revision_supplemental_project_attachment
-- The SUPPORTING DOCUMENTS of one project of a SUPPLEMENTAL budget request: a file uploaded to object storage (DO Spaces, same bucket and
-- GlobalService::uploadFileToStorage() the Budget Request's documents use) or a link pasted by the user.
--
-- Keyed by supplemental_project_id ("01 - table/57"). A Saved request is edited by updating its project rows in place (the form sends each existing
-- project's supplemental_project_id back), so an attachment survives an unrelated edit; removing a project from the request removes its attachments
-- (BudgetRevisionService deletes the stored objects first, then the rows cascade).
--
-- Idempotent (IF NOT EXISTS): safe to run any number of times, on any environment.
-- ============================================================

CREATE TABLE IF NOT EXISTS fims.budget_revision_supplemental_project_attachment (
    attachment_id           SERIAL PRIMARY KEY,
    supplemental_project_id INTEGER       NOT NULL REFERENCES fims.budget_revision_supplemental_project(supplemental_project_id) ON DELETE CASCADE,
    document_name           VARCHAR(255)  NOT NULL,
    storage_key             VARCHAR(500),   -- object key in the bucket, when a file was uploaded
    external_link           VARCHAR(1000),  -- the pasted URL, when a link was given
    uploaded_by             INTEGER,
    uploaded_at             TIMESTAMP     NOT NULL DEFAULT NOW(),
    CONSTRAINT chk_budget_revision_supplemental_project_attachment_source CHECK (storage_key IS NOT NULL OR external_link IS NOT NULL)
);

COMMENT ON TABLE  fims.budget_revision_supplemental_project_attachment IS 'Supporting documents (uploaded file or link) of one project of a SUPPLEMENTAL budget request.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project_attachment.storage_key IS 'Object key in the DO Spaces bucket when a file was uploaded; NULL for a link.';
COMMENT ON COLUMN fims.budget_revision_supplemental_project_attachment.external_link IS 'The pasted URL when the document is a link; NULL for an uploaded file.';

CREATE INDEX IF NOT EXISTS idx_budget_revision_supplemental_project_attachment_project
    ON fims.budget_revision_supplemental_project_attachment (supplemental_project_id);
