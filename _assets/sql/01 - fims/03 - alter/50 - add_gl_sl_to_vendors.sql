-- Add Chart of Accounts and Sub-Ledger assignment to vendors
-- Vendors are assigned to a specific COA account (GL) and optional SL for payables routing

ALTER TABLE fims.vendors ADD COLUMN IF NOT EXISTS coa_account_id VARCHAR(10);
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_vendors_coa_account_id'
          AND conrelid = 'fims.vendors'::regclass
    ) THEN
        ALTER TABLE fims.vendors ADD CONSTRAINT fk_vendors_coa_account_id
          FOREIGN KEY (coa_account_id) REFERENCES fims.chart_of_accounts(account_id) ON DELETE SET NULL;
    END IF;
END $$;

ALTER TABLE fims.vendors ADD COLUMN IF NOT EXISTS sl_code VARCHAR(100);

COMMENT ON COLUMN fims.vendors.coa_account_id IS 'Chart of Accounts account (GL/parent) assigned to this vendor for payment routing';
COMMENT ON COLUMN fims.vendors.sl_code IS 'Child account ID from Chart of Accounts (sub-ledger) for detailed transaction tracking';

-- Indexes for faster lookups
CREATE INDEX IF NOT EXISTS idx_vendors_coa_account_id ON fims.vendors (coa_account_id);
CREATE INDEX IF NOT EXISTS idx_vendors_sl_code ON fims.vendors (sl_code);
