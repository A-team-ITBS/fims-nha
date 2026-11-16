-- DROP: fims.sl_masters and fims.sl_details tables
-- These tables are no longer needed as SL (Sub-Ledger) accounts are now handled
-- through the Chart of Accounts parent-child hierarchy instead.
-- All ledger reporting (GL, SL, Journal, Trial Balance) now uses chart_of_accounts.

DROP TABLE IF EXISTS fims.sl_details CASCADE;
DROP TABLE IF EXISTS fims.sl_masters CASCADE;
