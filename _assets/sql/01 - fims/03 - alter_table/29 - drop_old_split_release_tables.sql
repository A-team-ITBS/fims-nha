-- ============================================================================
-- Drops the four tables superseded by fims.check_releases /
-- fims.check_release_email_log (see "01 - table/38 -
-- fims_check_releases_unified.sql") now that Tabs 3+4 (Released to Claimants
-- / Released to Regions) have been merged into one "Released" tab backed by
-- that unified pair.
--
-- Safe to drop outright, not soft-delete/rename-and-keep: as of this
-- migration all four tables were still completely empty in every
-- environment this was checked against (feature had only just been wired,
-- never used) — there is no historical data to preserve or migrate.
-- ============================================================================

DROP TABLE IF EXISTS fims.check_claimant_email_log;
DROP TABLE IF EXISTS fims.check_region_email_log;
DROP TABLE IF EXISTS fims.check_claimant_releases;
DROP TABLE IF EXISTS fims.check_region_releases;
