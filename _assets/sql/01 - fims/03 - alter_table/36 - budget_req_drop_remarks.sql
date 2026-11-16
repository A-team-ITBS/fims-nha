-- ============================================================
-- ALTER: fims.budget_req
-- Drops remarks (added by "13 - budget_req_add_remarks.sql"). The last
-- Return reason now lives only in fims.status_change_log (status_for =
-- 'budget_req'), same as every other module's status history — read back
-- with GlobalService::getLatestRemarks('budget_req', $budgetReferenceNumber).
-- Every write that used to also set budget_req.remarks already calls
-- GlobalService::logStatusChange() with the same remarks, so no backfill
-- is needed.
-- ============================================================

ALTER TABLE fims.budget_req
    DROP COLUMN IF EXISTS remarks;
