CREATE TABLE IF NOT EXISTS fims.pricing_requests();
ALTER TABLE fims.pricing_requests
ADD COLUMN IF NOT EXISTS id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS project_code VARCHAR(30) NOT NULL REFERENCES fims.project(project_code),
ADD COLUMN IF NOT EXISTS type_of_request VARCHAR(100),
ADD COLUMN IF NOT EXISTS status VARCHAR(50),
ADD COLUMN IF NOT EXISTS signed_by VARCHAR(150),
ADD COLUMN IF NOT EXISTS assigned_to VARCHAR(150),
ADD COLUMN IF NOT EXISTS unsold_inventories NUMERIC(18,2),
ADD COLUMN IF NOT EXISTS regional_office_id INTEGER REFERENCES uac.offices(office_id),
ADD COLUMN IF NOT EXISTS district_office_id INTEGER REFERENCES uac.offices(office_id),
ADD COLUMN IF NOT EXISTS date_received DATE,
ADD COLUMN IF NOT EXISTS date_received_complete_docs DATE,   -- (a)
ADD COLUMN IF NOT EXISTS date_forwarded_fsg DATE,            -- (b)
ADD COLUMN IF NOT EXISTS days_with_fmd INTEGER,              -- (c)
ADD COLUMN IF NOT EXISTS date_forwarded_ogm DATE,            -- (d)
ADD COLUMN IF NOT EXISTS days_with_fsg INTEGER,              -- (e)
ADD COLUMN IF NOT EXISTS date_approved_ogm DATE,             -- (f)
ADD COLUMN IF NOT EXISTS days_with_ogm INTEGER,              -- (g)
ADD COLUMN IF NOT EXISTS total_days INTEGER,                 -- (h) = (c)+(e)+(g)
ADD COLUMN IF NOT EXISTS remarks TEXT,                       -- Remarks (Pending) / Memorandum & MC No.
ADD COLUMN IF NOT EXISTS is_active BOOLEAN NOT NULL DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP;

COMMENT ON TABLE fims.pricing_requests IS 'Fund Sourcing Division pricing requests with routing/turnaround dates (FMD -> FSG -> OGM); day counts saved as typed';
COMMENT ON COLUMN fims.pricing_requests.project_code IS 'fims.project.project_code; the project name is read by joining on it';
COMMENT ON COLUMN fims.pricing_requests.regional_office_id IS 'uac.offices.office_id of the regional office';
COMMENT ON COLUMN fims.pricing_requests.district_office_id IS 'uac.offices.office_id of the district office (child of the regional office via parent_office_id; validated in app)';
COMMENT ON COLUMN fims.pricing_requests.total_days IS '(h) = days_with_fmd (c) + days_with_fsg (e) + days_with_ogm (g); saved as typed, not recalculated';
COMMENT ON COLUMN fims.pricing_requests.created_by IS 'uac.users.user_id of the creator (plain integer, no cross-schema FK)';

-- pricing_requests indexes
CREATE UNIQUE INDEX IF NOT EXISTS uq_pricing_requests_project_type_received
    ON fims.pricing_requests (project_code, type_of_request, date_received);
CREATE INDEX IF NOT EXISTS idx_pricing_requests_project_code ON fims.pricing_requests (project_code);
CREATE INDEX IF NOT EXISTS idx_pricing_requests_status ON fims.pricing_requests (status);
CREATE INDEX IF NOT EXISTS idx_pricing_requests_regional_office_id ON fims.pricing_requests (regional_office_id);
CREATE INDEX IF NOT EXISTS idx_pricing_requests_district_office_id ON fims.pricing_requests (district_office_id);
CREATE INDEX IF NOT EXISTS idx_pricing_requests_date_received ON fims.pricing_requests (date_received);
