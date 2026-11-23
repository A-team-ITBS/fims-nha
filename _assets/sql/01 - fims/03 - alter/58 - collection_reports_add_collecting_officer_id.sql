-- Links a Collection Report to the specific Collecting Officer who prepared it
-- (fims.collecting_officers) — used to auto-fill the Collection(s) lines' Account
-- Code from whichever officer is tagged to the report's own Region/Office, per the
-- GL/SL already assigned to that officer in Collecting Officer Master Data. Separate
-- from the pre-existing collecting_office (free text — an office/location name, not
-- a specific person) — this is nullable and purely a convenience/traceability field;
-- nothing in CollectionsService enforces that the two actually match.
ALTER TABLE fims.collection_reports
    ADD COLUMN IF NOT EXISTS collecting_officer_id INTEGER REFERENCES fims.collecting_officers(collecting_officer_id);

CREATE INDEX IF NOT EXISTS idx_collection_reports_collecting_officer_id ON fims.collection_reports(collecting_officer_id);
