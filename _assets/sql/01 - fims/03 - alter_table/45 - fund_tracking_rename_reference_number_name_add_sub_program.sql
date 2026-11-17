-- ============================================================
-- ALTER: fims.fund_tracking
--   * renames `reference_number` -> `saro_id`   (the SARO ID shown on the SARO / NCA Tracking page; for an NCA, Trust Fund or OR
--     record it holds that record's own number, as reference_number did)
--   * renames `name`             -> `description`
--   * brings back `sub_program` VARCHAR(225) (free text; dropped together with `sub_program_code` by "41 - fund_tracking_drop_...")
--
-- The API (FundTrackingServices), the SARO / NCA, Trust Fund, Supplemental Funds and Fund Sourcing pages and their entry forms read and
-- write `saro_id` / `description` / `sub_program` — run this before deploying that code.
--
-- Non-destructive: the renames keep every value, the new column starts empty. A new database gets this table already in this shape from
-- "01 - table/29" and this file changes nothing there.
-- Safe to re-run (each rename only runs while the old column still exists).
-- ============================================================

DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'fims' AND table_name = 'fund_tracking' AND column_name = 'reference_number')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'fims' AND table_name = 'fund_tracking' AND column_name = 'saro_id') THEN
        ALTER TABLE fims.fund_tracking RENAME COLUMN reference_number TO saro_id;
    END IF;

    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'fims' AND table_name = 'fund_tracking' AND column_name = 'name')
       AND NOT EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'fims' AND table_name = 'fund_tracking' AND column_name = 'description') THEN
        ALTER TABLE fims.fund_tracking RENAME COLUMN name TO description;
    END IF;
END $$;

ALTER TABLE fims.fund_tracking ADD COLUMN IF NOT EXISTS sub_program VARCHAR(225);
