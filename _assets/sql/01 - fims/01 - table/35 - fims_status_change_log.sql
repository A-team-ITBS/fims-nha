-- Shared, cross-module status-change audit trail. Any table that drives its
-- own status off a plain VARCHAR column (PurchaseOrderService, Disbursement-
-- VoucherService, FtlaService, …) can log into this ONE table instead of each
-- growing its own "<module>_status_log" — no FK on record_id since it points
-- at a different table per status_for, same scoping idea as
-- fims.settings_status.status_for (see "01 - fims_settings_status.sql").
--
-- Write one row per status change: status_for (the owning table's name, e.g.
-- 'fund_transfers', 'budget_allocation'), record_id (that table's own PK, as
-- text — most PKs here are numeric, but e.g. fims.budget_req's is the string
-- budget_reference_number, "BP-007"; GlobalService::logStatusChange() accepts
-- int|string and casts either to text), status (the value moved to), optional
-- remarks, who changed it and when.
--
-- Written to by FtlaService, BudgetAllocationService (LAA/RFA/CBA share this
-- one table), DisbursementVoucherService::changeJevStatus(), and
-- BudgetReqService. PurchaseOrderService / DisbursementVoucherService's own
-- DV status changes can adopt it later without a schema change.
CREATE TABLE IF NOT EXISTS fims.status_change_log (
    status_change_log_id SERIAL PRIMARY KEY,
    status_for            VARCHAR(100) NOT NULL,
    record_id              VARCHAR(50) NOT NULL,
    status                 VARCHAR(50) NOT NULL,
    remarks                 TEXT,
    changed_by              INTEGER REFERENCES uac.users(user_id),
    changed_at              TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.status_change_log IS 'Shared, cross-module chronological audit trail of status changes, scoped per table via status_for (no FK — see fims.settings_status.status_for for the same convention)';
COMMENT ON COLUMN fims.status_change_log.record_id IS 'The owning table''s own PK, as text — numeric PKs are cast to string, e.g. fims.budget_req''s PK already is one (budget_reference_number, "BP-007")';

CREATE INDEX IF NOT EXISTS idx_status_change_log_for_record ON fims.status_change_log (status_for, record_id);
