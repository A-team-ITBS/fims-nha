-- ============================================================================
-- Collection Reports — a Collecting Officer's periodic report of what was
-- collected (by revenue/receivable GL account, e.g. Installment Sales
-- Receivable, Permit Fees) and what of that was deposited to the bank
-- (by bank account and period covered), for a given Region/Office and
-- date. Same "record first, journalize later" shape as
-- fims.official_receipts ("40 - fims_official_receipts.sql") — not yet
-- posted to fims.general_ledger.
--
-- Two separate running totals (Collections vs Deposits), independent of
-- each other — this report does not enforce that deposits reconcile to
-- collections.
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.collection_reports();
ALTER TABLE fims.collection_reports
ADD COLUMN IF NOT EXISTS collection_report_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS report_date           DATE NOT NULL,
ADD COLUMN IF NOT EXISTS rc_id                 VARCHAR(10) NOT NULL REFERENCES fims.responsibility_code(rc_id),
ADD COLUMN IF NOT EXISTS collecting_office     VARCHAR(255),
ADD COLUMN IF NOT EXISTS total_collections     NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_deposits        NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS created_by            INTEGER,
ADD COLUMN IF NOT EXISTS created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.collection_reports IS 'Header for a Collecting Officer''s Collection & Deposit Report — see fims.collection_report_lines (what was collected) and fims.collection_report_deposits (what was deposited).';

-- "Collection(s)" section — GL Account + Amount, one row per revenue/
-- receivable account collected against (mirrors
-- fims.official_receipt_lines' one-header/many-lines shape).
CREATE TABLE IF NOT EXISTS fims.collection_report_lines();
ALTER TABLE fims.collection_report_lines
ADD COLUMN IF NOT EXISTS line_id               SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS collection_report_id  INTEGER NOT NULL REFERENCES fims.collection_reports(collection_report_id) ON DELETE CASCADE,
ADD COLUMN IF NOT EXISTS line_number           INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS coa_account_id        VARCHAR(10) NOT NULL REFERENCES fims.chart_of_accounts(account_id),
ADD COLUMN IF NOT EXISTS amount                NUMERIC(18,2) NOT NULL DEFAULT 0;

COMMENT ON TABLE fims.collection_report_lines IS 'The "Collection(s)" section of a Collection Report (see fims.collection_reports) — one row per GL/revenue account collected against.';

-- "Deposit" section — Bank + Period Covered (From/To) + Amount, one row
-- per bank deposit slip.
CREATE TABLE IF NOT EXISTS fims.collection_report_deposits();
ALTER TABLE fims.collection_report_deposits
ADD COLUMN IF NOT EXISTS deposit_id            SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS collection_report_id  INTEGER NOT NULL REFERENCES fims.collection_reports(collection_report_id) ON DELETE CASCADE,
ADD COLUMN IF NOT EXISTS line_number           INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS bank_account_id       INTEGER NOT NULL REFERENCES fims.bank_accounts(bank_account_id),
ADD COLUMN IF NOT EXISTS period_from           DATE,
ADD COLUMN IF NOT EXISTS period_to             DATE,
ADD COLUMN IF NOT EXISTS amount                NUMERIC(18,2) NOT NULL DEFAULT 0;

COMMENT ON TABLE fims.collection_report_deposits IS 'The "Deposit" section of a Collection Report (see fims.collection_reports) — one row per bank deposit, with the period of collections it covers.';

CREATE UNIQUE INDEX IF NOT EXISTS uq_collection_report_lines_report_line
    ON fims.collection_report_lines (collection_report_id, line_number);
CREATE UNIQUE INDEX IF NOT EXISTS uq_collection_report_deposits_report_line
    ON fims.collection_report_deposits (collection_report_id, line_number);

CREATE INDEX IF NOT EXISTS idx_collection_reports_report_date ON fims.collection_reports (report_date);
CREATE INDEX IF NOT EXISTS idx_collection_reports_rc_id       ON fims.collection_reports (rc_id);
CREATE INDEX IF NOT EXISTS idx_collection_report_lines_report_id    ON fims.collection_report_lines (collection_report_id);
CREATE INDEX IF NOT EXISTS idx_collection_report_deposits_report_id ON fims.collection_report_deposits (collection_report_id);
