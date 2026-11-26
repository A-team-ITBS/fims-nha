-- fims.settings_status.triggers_jev: marks the status that makes FTLA post its Payment/Settlement
-- JEV the moment a record moves into it (status_for = 'fund_transfers'; read by
-- FtlaService::jevTriggerStatuses()). Toggle it per status row instead of editing code.
-- Default: APPROVED and COMPLETED. Re-runnable.
ALTER TABLE fims.settings_status ADD COLUMN IF NOT EXISTS triggers_jev BOOLEAN NOT NULL DEFAULT FALSE;
COMMENT ON COLUMN fims.settings_status.triggers_jev IS 'FTLA (status_for = fund_transfers): entering this status posts the FTLA JEV (skipped if it already has one)';

UPDATE fims.settings_status SET triggers_jev = TRUE
WHERE status_for = 'fund_transfers' AND status_code IN ('APPROVED', 'COMPLETED');
