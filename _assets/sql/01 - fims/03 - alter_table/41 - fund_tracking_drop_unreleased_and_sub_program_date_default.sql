-- ============================================================
-- ALTER: fims.fund_tracking
--   * drops `unreleased` and `sub_program_code` — a fund tracking record (SARO / NCA / Trust Fund / OR) no longer carries
--     an unreleased amount or a sub-program. The SARO / NCA, Trust Fund, Supplemental Funds and Fund Sourcing pages, the
--     API (FundTrackingServices) and the Excel imports no longer read or write either.
--   * `date` is the day the record was created: the column now defaults to CURRENT_DATE, the API stamps it on create (it is no
--     longer entered on any form), and records saved without one (date IS NULL) get their created_at day.
--
-- DESTRUCTIVE: the values in the two dropped columns are lost. Take a backup first if they might be needed.
-- A new database gets this table already without them from "01 - table/29" and this file only sets the default.
-- Safe to re-run.
-- ============================================================

ALTER TABLE fims.fund_tracking DROP COLUMN IF EXISTS unreleased;
ALTER TABLE fims.fund_tracking DROP COLUMN IF EXISTS sub_program_code;

ALTER TABLE fims.fund_tracking ALTER COLUMN date SET DEFAULT CURRENT_DATE;

UPDATE fims.fund_tracking
   SET date = created_at::date
 WHERE date IS NULL
   AND created_at IS NOT NULL;
