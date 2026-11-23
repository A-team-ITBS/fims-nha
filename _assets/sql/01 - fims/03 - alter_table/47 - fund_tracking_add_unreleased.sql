-- ============================================================
-- ALTER: fims.fund_tracking
--   * brings back `unreleased` NUMERIC(18,2) (dropped by "41 - fund_tracking_drop_unreleased_and_sub_program_date_default").
--
-- The values dropped by 41 are gone: the column comes back empty (NULL) for every existing record — run "48 - fund_tracking_backfill_saro_unreleased"
-- next to fill in the SAROs. The API (FundTrackingServices) returns it and keeps it up to date itself: a SARO's `unreleased` is its amount less its
-- NCAs (SAROs only; a request never writes it). A new database gets the column already from "01 - table/29" and this file changes nothing there.
-- Safe to re-run.
-- ============================================================

ALTER TABLE fims.fund_tracking ADD COLUMN IF NOT EXISTS unreleased NUMERIC(18,2);
