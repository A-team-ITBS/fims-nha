-- Fund Sweeping (BankManagementService::previewFundSweep()/
-- processFundSweep()) treats a Collection Report's recorded deposits
-- (fims.collection_report_deposits) as the source of truth for how much
-- is sitting in a region bank account, since fims.bank_accounts.
-- current_balance is never kept in sync by any app code. Without marking
-- which deposits have already been swept into a draft FTLA, running Fund
-- Sweeping a second time for the same account would double-count the
-- same money into a second draft FTLA.

ALTER TABLE fims.collection_report_deposits ADD COLUMN IF NOT EXISTS is_swept BOOLEAN NOT NULL DEFAULT FALSE;
ALTER TABLE fims.collection_report_deposits ADD COLUMN IF NOT EXISTS swept_fund_transfer_id INTEGER REFERENCES fims.fund_transfers(fund_transfer_id) ON DELETE SET NULL;

COMMENT ON COLUMN fims.collection_report_deposits.is_swept IS 'TRUE once this deposit has been included in a Fund Sweeping draft FTLA — excluded from future sweep balance totals so the same money is never swept twice.';
COMMENT ON COLUMN fims.collection_report_deposits.swept_fund_transfer_id IS 'The fims.fund_transfers row (draft FTLA) this deposit was swept into, if any.';

CREATE INDEX IF NOT EXISTS idx_collection_report_deposits_bank_unswept
    ON fims.collection_report_deposits (bank_account_id) WHERE is_swept = FALSE;
