-- Generic key/value app settings table — first use is a single row for
-- "fund_sweep_day_of_week" (BankManagementService::getFundSweepSettings()/
-- updateFundSweepSettings()), the ISO-8601 day-of-week (1=Monday..7=Sunday)
-- that Fund Sweeping's "Process Fund Sweep" button is allowed to run on
-- (default 4 = Thursday). No other admin-configurable value exists
-- anywhere else in this app yet — kept generic (setting_key/setting_value)
-- so a future one can reuse this table instead of inventing another.
CREATE TABLE IF NOT EXISTS fims.app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_by INTEGER,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO fims.app_settings (setting_key, setting_value)
VALUES ('fund_sweep_day_of_week', '4')
ON CONFLICT (setting_key) DO NOTHING;
