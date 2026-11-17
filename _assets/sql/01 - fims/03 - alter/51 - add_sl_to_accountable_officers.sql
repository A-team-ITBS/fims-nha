-- Add Sub-Ledger assignment to accountable officers
-- Officers already carry coa_account_id (GL); sl_code adds an optional child
-- account (sub-ledger) under that GL for detailed tracking, mirroring fims.vendors.

ALTER TABLE fims.accountable_officers ADD COLUMN IF NOT EXISTS sl_code VARCHAR(100);

COMMENT ON COLUMN fims.accountable_officers.sl_code IS 'Child account ID from Chart of Accounts (sub-ledger) for detailed transaction tracking';

CREATE INDEX IF NOT EXISTS idx_accountable_officers_sl_code ON fims.accountable_officers (sl_code);
