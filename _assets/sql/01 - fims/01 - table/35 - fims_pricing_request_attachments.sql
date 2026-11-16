

CREATE TABLE IF NOT EXISTS fims.pricing_request_attachments();
ALTER TABLE fims.pricing_request_attachments
ADD COLUMN IF NOT EXISTS attachment_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS pricing_request_id INTEGER NOT NULL REFERENCES fims.pricing_requests(id) ON DELETE CASCADE,
ADD COLUMN IF NOT EXISTS document_name VARCHAR(255) NOT NULL,
ADD COLUMN IF NOT EXISTS storage_key VARCHAR(500),      -- object-storage key, when uploaded
ADD COLUMN IF NOT EXISTS external_link VARCHAR(1000),   -- pasted URL, when mode = link
ADD COLUMN IF NOT EXISTS uploaded_by INTEGER,
ADD COLUMN IF NOT EXISTS uploaded_at TIMESTAMP NOT NULL DEFAULT NOW(),
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;

-- Postgres has no ADD CONSTRAINT IF NOT EXISTS, so guard it with a DO block.
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_pricing_request_attachments_source') THEN
        ALTER TABLE fims.pricing_request_attachments
        ADD CONSTRAINT chk_pricing_request_attachments_source
        CHECK (storage_key IS NOT NULL OR external_link IS NOT NULL);
    END IF;
END $$;

COMMENT ON TABLE fims.pricing_request_attachments IS 'Supporting documents (uploaded file or external link) of a pricing request';
COMMENT ON COLUMN fims.pricing_request_attachments.storage_key IS 'Object-storage key when a file was uploaded';
COMMENT ON COLUMN fims.pricing_request_attachments.external_link IS 'Pasted URL / reference when the document is a link';
COMMENT ON COLUMN fims.pricing_request_attachments.uploaded_by IS 'uac.users.user_id of the uploader (plain integer, no cross-schema FK)';
COMMENT ON COLUMN fims.pricing_request_attachments.deleted_at IS 'Soft delete: NULL = active, timestamp = deleted';

-- pricing_request_attachments indexes
CREATE INDEX IF NOT EXISTS idx_pricing_request_attachments_request ON fims.pricing_request_attachments (pricing_request_id);
CREATE INDEX IF NOT EXISTS idx_pricing_request_attachments_deleted_at ON fims.pricing_request_attachments (deleted_at);
