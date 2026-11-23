-- Add Chart of Accounts and Sub-Ledger assignment to bank accounts
-- Bank accounts already had a flat account_code (see "03 - alter_table/31 -
-- jev_payment_entries_and_bank_account_code.sql") used by
-- GeneralLedgerService::postPaymentJev() to know which Cash GL account to
-- credit — left untouched so that posting logic keeps working unmodified.
-- This adds the same GL (parent) + SL (child) pair already used by
-- fims.vendors ("50 - add_gl_sl_to_vendors.sql") and
-- fims.accountable_officers ("51 - add_sl_to_accountable_officers.sql"), so
-- Bank Account Management can offer the same two-step GL+SL picker UI. The
-- UI keeps account_code in sync with coa_account_id's account_code on save.

ALTER TABLE fims.bank_accounts ADD COLUMN IF NOT EXISTS coa_account_id VARCHAR(10);
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_bank_accounts_coa_account_id'
          AND conrelid = 'fims.bank_accounts'::regclass
    ) THEN
        ALTER TABLE fims.bank_accounts ADD CONSTRAINT fk_bank_accounts_coa_account_id
          FOREIGN KEY (coa_account_id) REFERENCES fims.chart_of_accounts(account_id) ON DELETE SET NULL;
    END IF;
END $$;

ALTER TABLE fims.bank_accounts ADD COLUMN IF NOT EXISTS sl_code VARCHAR(100);

COMMENT ON COLUMN fims.bank_accounts.coa_account_id IS 'Chart of Accounts account (GL/parent) assigned to this bank account';
COMMENT ON COLUMN fims.bank_accounts.sl_code IS 'Child account ID from Chart of Accounts (sub-ledger) for detailed transaction tracking';

CREATE INDEX IF NOT EXISTS idx_bank_accounts_coa_account_id ON fims.bank_accounts (coa_account_id);
CREATE INDEX IF NOT EXISTS idx_bank_accounts_sl_code ON fims.bank_accounts (sl_code);
