-- ============================================================
-- ALTER: fims.fund_tracking
--   * `description` VARCHAR(225) -> TEXT, so a SARO / NCA can carry a long description (the SARO entry form's Description is a
--     multi-line box now). Run after "45 - fund_tracking_rename_reference_number_name_add_sub_program" (which renames `name` to `description`).
--
-- Non-destructive: every value is kept; widening to TEXT needs no table rewrite. A new database gets the column as TEXT already from
-- "01 - table/29". Safe to re-run (changing a TEXT column to TEXT does nothing).
-- ============================================================

ALTER TABLE fims.fund_tracking ALTER COLUMN description TYPE TEXT;
