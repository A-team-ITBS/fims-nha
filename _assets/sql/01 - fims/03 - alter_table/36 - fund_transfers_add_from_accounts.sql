-- Merge Treasury's single-account and multi-account FTLA shapes into one: a
-- transfer always has ONE destination (to_bank_account_id, existing) and either
-- no explicit source (legacy single-account records) or one-or-more source/debit
-- accounts. from_bank_account_id mirrors the first row of from_accounts (same
-- "scalar FK mirror" convention Treasury's ftla-preparation.php uses) so simple
-- single-source transfers can still be queried/joined without unpacking JSON.
ALTER TABLE fims.fund_transfers
    ADD COLUMN IF NOT EXISTS from_bank_account_id INTEGER REFERENCES fims.bank_accounts(bank_account_id),
    -- [{bank_account_id, amount}, ...] — empty for a transfer with no declared
    -- source account (legacy behavior); one row for a single-source transfer;
    -- several rows for a "multiple debit accounts" transfer, whose transfer_amount
    -- is then the sum of these rows (see FtlaService::normalizeFromAccounts()).
    ADD COLUMN IF NOT EXISTS from_accounts JSONB NOT NULL DEFAULT '[]';

CREATE INDEX IF NOT EXISTS idx_fund_transfers_from_bank_account_id ON fims.fund_transfers (from_bank_account_id);

COMMENT ON COLUMN fims.fund_transfers.from_bank_account_id IS 'Scalar mirror of from_accounts[0].bank_account_id; NULL when from_accounts is empty.';
COMMENT ON COLUMN fims.fund_transfers.from_accounts IS '[{bank_account_id, amount}, ...] source/debit accounts; empty array = no declared source (legacy single-account record).';
