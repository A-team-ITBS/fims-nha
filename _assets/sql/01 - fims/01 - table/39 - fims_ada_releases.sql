-- ============================================================================
-- Advice to Debit Account (ADA) releases — backs the ADADJ (Advice to Debit
-- Account Disbursement Journal) tab on the Journals page. Mirrors
-- fims.checks' own shape and conventions: dv_number is a soft (non-FK)
-- pointer to fims.disbursement_vouchers.dv_no, same reasoning as
-- checks.dv_number — a payment method record shouldn't need the DV/JEV
-- posting pipeline to know about it, only vice versa (the journal report
-- joins the other way, by dv_no/dv_number).
--
-- Separate table from fims.checks (not a payment_method column on one
-- shared table) because the two forms carry different fields end to end
-- (checks have a physical check_number/booklet/signatories; an ADA has a
-- region and a receiving bank, no physical instrument at all) — same
-- "two forms, two tables" reasoning TREASURY's own check_register vs
-- fims.checks split already uses.
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.ada_releases();
ALTER TABLE fims.ada_releases
ADD COLUMN IF NOT EXISTS ada_id          SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS ada_no          VARCHAR(30) UNIQUE,
ADD COLUMN IF NOT EXISTS ada_date        DATE,
ADD COLUMN IF NOT EXISTS dv_number       VARCHAR(30),
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER REFERENCES fims.bank_accounts(bank_account_id),
ADD COLUMN IF NOT EXISTS region          VARCHAR(100),
ADD COLUMN IF NOT EXISTS payee           VARCHAR(255),
ADD COLUMN IF NOT EXISTS amount          NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS status          VARCHAR(20) NOT NULL DEFAULT 'RELEASED', -- RELEASED|CANCELLED
ADD COLUMN IF NOT EXISTS created_by      INTEGER,
ADD COLUMN IF NOT EXISTS created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE  fims.ada_releases IS 'Advice to Debit Account releases — one row per ADA issued against a disbursement voucher, backs the ADADJ journal report';
COMMENT ON COLUMN fims.ada_releases.dv_number IS 'Soft reference to fims.disbursement_vouchers.dv_no, same convention as fims.checks.dv_number — no FK so this record never blocks on the DV''s own lifecycle';

CREATE INDEX IF NOT EXISTS idx_ada_releases_dv_number ON fims.ada_releases (dv_number);
CREATE INDEX IF NOT EXISTS idx_ada_releases_ada_date  ON fims.ada_releases (ada_date);
CREATE INDEX IF NOT EXISTS idx_ada_releases_status     ON fims.ada_releases (status);
