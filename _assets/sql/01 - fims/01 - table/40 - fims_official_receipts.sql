-- ============================================================================
-- Official Receipts — backs the CRDJ (Cash Receipts Journal) tab on the
-- Journals page. Standalone collections register, not yet wired into
-- fims.general_ledger — this project has no receivables/collections
-- workflow of its own today, only disbursement; a collecting officer's
-- receipts accumulate here (debit_account_code is almost always a
-- Cash-Collecting-Officers account) until a later, separately-prepared
-- Journal Entry Voucher deposits them to the bank and posts the entry
-- (the existing fims.journal_entry_vouchers/general_ledger pipeline) —
-- same "record first, journalize later" shape fims.checks already has
-- relative to the DV that pays it.
--
-- One receipt can collect for more than one revenue account at once (e.g.
-- Permit Fees + Registration Fees in a single OR), so the credited side is
-- its own line table, same one-header/many-lines shape as
-- fims.journal_entry_vouchers/journal_entry_voucher_lines.
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.official_receipts();
ALTER TABLE fims.official_receipts
ADD COLUMN IF NOT EXISTS receipt_id         SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS or_no              VARCHAR(30) UNIQUE,
ADD COLUMN IF NOT EXISTS or_date            DATE,
ADD COLUMN IF NOT EXISTS fiscal_yr          SMALLINT,
ADD COLUMN IF NOT EXISTS collecting_officer VARCHAR(255),
ADD COLUMN IF NOT EXISTS payor_name         VARCHAR(255),
ADD COLUMN IF NOT EXISTS particulars        TEXT,
ADD COLUMN IF NOT EXISTS debit_account_code VARCHAR(30) REFERENCES fims.chart_of_accounts(account_code),
ADD COLUMN IF NOT EXISTS total_amount       NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS created_by         INTEGER,
ADD COLUMN IF NOT EXISTS created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.official_receipts IS 'Collections register — one row per Official Receipt issued, backs the CRDJ journal report. Not posted to fims.general_ledger; a later JEV journalizes the deposit.';

CREATE TABLE IF NOT EXISTS fims.official_receipt_lines();
ALTER TABLE fims.official_receipt_lines
ADD COLUMN IF NOT EXISTS line_id      SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS receipt_id   INTEGER NOT NULL REFERENCES fims.official_receipts(receipt_id) ON DELETE CASCADE,
ADD COLUMN IF NOT EXISTS line_number  INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS account_code VARCHAR(30) NOT NULL REFERENCES fims.chart_of_accounts(account_code),
ADD COLUMN IF NOT EXISTS amount       NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS description  VARCHAR(255);

COMMENT ON TABLE fims.official_receipt_lines IS 'Credited account breakdown of one Official Receipt (see fims.official_receipts) — the CREDIT/SUNDRIES side of the collection.';

CREATE UNIQUE INDEX IF NOT EXISTS uq_official_receipt_lines_receipt_line
    ON fims.official_receipt_lines (receipt_id, line_number);

CREATE INDEX IF NOT EXISTS idx_official_receipts_or_date   ON fims.official_receipts (or_date);
CREATE INDEX IF NOT EXISTS idx_official_receipts_fiscal_yr ON fims.official_receipts (fiscal_yr);
CREATE INDEX IF NOT EXISTS idx_official_receipt_lines_receipt_id ON fims.official_receipt_lines (receipt_id);
