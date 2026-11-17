-- Adds Treasury's third FTLA account shape — ftlafundtransfer.php's paired
-- FROM/TO rows (from_accounts[i] <-> to_accounts[i], both arbitrary N) — on top
-- of the single-to-account (to_bank_account_id) and single-to + multi-from
-- shapes already supported (see "36 - fund_transfers_add_from_accounts.sql").
-- to_accounts is empty for every non-paired record; from_accounts and
-- to_accounts are the same length only when FtlaService considers the record
-- "paired" (see FtlaService::normalizeAccountIds()/create()/update()).
ALTER TABLE fims.fund_transfers
    ADD COLUMN IF NOT EXISTS to_accounts JSONB NOT NULL DEFAULT '[]';

COMMENT ON COLUMN fims.fund_transfers.to_accounts IS '[{bank_account_id}, ...] credit accounts, index-paired with from_accounts when both have >1 row (Treasury''s ftlafundtransfer.php "pairs" shape); empty otherwise — see to_bank_account_id for the single-destination shape.';
