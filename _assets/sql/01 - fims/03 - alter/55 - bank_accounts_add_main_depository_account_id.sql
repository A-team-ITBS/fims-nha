-- Adds a per-account "assigned Main Depository account" reference,
-- separate from the existing is_main_depository flag
-- ("23 - bank_accounts_add_management_fields.sql"). is_main_depository
-- marks an account AS a Main Depository itself (plain per-account flag,
-- no exclusivity — see BankManagementService::setMainDepository()/
-- clearMainDepository()); main_depository_account_id instead records
-- WHICH other account a given account points to as ITS Main Depository —
-- a self-referencing FK into the same table.

ALTER TABLE fims.bank_accounts ADD COLUMN IF NOT EXISTS main_depository_account_id INTEGER;
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_bank_accounts_main_depository_account_id'
          AND conrelid = 'fims.bank_accounts'::regclass
    ) THEN
        ALTER TABLE fims.bank_accounts ADD CONSTRAINT fk_bank_accounts_main_depository_account_id
          FOREIGN KEY (main_depository_account_id) REFERENCES fims.bank_accounts(bank_account_id) ON DELETE SET NULL;
    END IF;
END $$;

COMMENT ON COLUMN fims.bank_accounts.main_depository_account_id IS 'The account assigned as this account''s Main Depository (self-referencing FK) — separate from is_main_depository, which flags an account AS a Main Depository itself';

CREATE INDEX IF NOT EXISTS idx_bank_accounts_main_depository_account_id ON fims.bank_accounts (main_depository_account_id);
