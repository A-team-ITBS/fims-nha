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

-- NHA as the withholding agent on the BIR forms it issues (2307 now; 2316 next) — Bir2307PrintService reads these:
-- Part II (payor) and the payor's default signatory. The values the old ACCOUNTING module had hard-coded (the print falls
-- back to the same values while a key is missing — Bir2307PrintService::FALLBACK).
INSERT INTO fims.app_settings (setting_key, setting_value)
VALUES
    ('bir_payor_tin',       '000-916-384-000'),
    ('bir_payor_name',      'NATIONAL HOUSING AUTHORITY'),
    ('bir_payor_address',   'Elliptical Rd., Diliman, Quezon City'),
    ('bir_payor_zip',       '1101'),
    ('bir_payor_nature',    'Government Agency'),
    ('bir_signatory_name',  'CRISALYN T. HUGO'),
    ('bir_signatory_title', 'Officer-In-Charge, Acctg. Dept.'),
    ('bir_signatory_tin',   '416-806-839')
ON CONFLICT (setting_key) DO UPDATE SET setting_value = EXCLUDED.setting_value, updated_at = CURRENT_TIMESTAMP;
