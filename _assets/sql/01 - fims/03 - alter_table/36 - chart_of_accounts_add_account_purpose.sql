-- ALTER: fims.chart_of_accounts
-- Add account_purpose column to classify the account's intended use
-- (e.g., Vendor Payable, Bank Account, Employee Compensation, etc.)
-- Used for account organization and soft validation hints during transaction posting
ALTER TABLE fims.chart_of_accounts
    ADD COLUMN IF NOT EXISTS account_purpose VARCHAR(100) DEFAULT NULL;
