-- ============================================================================
-- Disbursement Vouchers (General Form No. 1) — one generic row per DV. The
-- paper form is itself one layout used for every kind of payment (vendor,
-- payroll, reimbursement, etc.) — what differs between uses is just what gets
-- written in Particulars/Amount, not the schema, so this table isn't split by
-- voucher type.
--
-- The "Accounting Entries" box on the paper form is a single ledger row per
-- voucher (Budget / Obligation / Balance), not a repeating line-item table
-- like a PO's items — so those columns live directly on this header,
-- snapshotted at save time. There's no "payments prior to this voucher"
-- figure to track: one RFA backs only one DV at a time (see
-- DisbursementVoucherService::fetchRfaOptions), so balance is always just
-- obligation_amount - net_amount.
--
-- rfa_allocation_id is a soft reference to fims.budget_allocation (the table
-- that stands in for "RFA" — see allocation_type='RFA' rows there; that
-- feature has no backend of its own yet, so only a read-only lookup is added
-- in DisbursementVoucherService, no FK to a system still being built out).
-- Foreign keys that do exist live in "03 - alter_table/11 - ...-add_fk.sql".
-- ============================================================================

-- =============================================
-- DISBURSEMENT_VOUCHERS TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS fims.disbursement_vouchers();
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS dv_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS dv_no VARCHAR(30) UNIQUE,
ADD COLUMN IF NOT EXISTS dv_date DATE,
ADD COLUMN IF NOT EXISTS fiscal_yr SMALLINT,
ADD COLUMN IF NOT EXISTS expense_class VARCHAR(20), -- MOOE | PS | CAPITAL_OUTLAY | OTHER — classification tag only, same set as fims.purchase_orders.expense_class; does not change which fields the form shows
-- Pipeline: DRAFT (Originating) -> FOR_FMD_REVIEW (FMD checks the obligation) ->
-- FOR_ACCOUNTING (Accounting processes: entries/JEV/ATC - not modeled yet, just the stage) ->
-- FOR_TREASURY (Treasury releases payment) -> RELEASED. REJECTED/CANCELLED from any
-- non-terminal stage. See DisbursementVoucherService::TRANSITIONS.
ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'DRAFT',

-- RFA linkage (see fims.budget_allocation where allocation_type = 'RFA').
-- Snapshot fields are copied at save time so the voucher still reads
-- correctly even if the RFA is edited/removed later.
ADD COLUMN IF NOT EXISTS rfa_allocation_id INTEGER,
ADD COLUMN IF NOT EXISTS rfa_allocation_number VARCHAR(100),
ADD COLUMN IF NOT EXISTS requesting_department_id INTEGER,
ADD COLUMN IF NOT EXISTS dept_project VARCHAR(255),
ADD COLUMN IF NOT EXISTS fund_source VARCHAR(255),

-- Claimant — optionally a registered vendor (vendor_id set, fields snapshotted
-- from fims.vendors); otherwise free-text (e.g. a payroll group claimant).
ADD COLUMN IF NOT EXISTS vendor_id INTEGER,
ADD COLUMN IF NOT EXISTS claimant_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS claimant_address TEXT,
ADD COLUMN IF NOT EXISTS employee_no_tin VARCHAR(100),

-- Particulars / amount. VAT/W-Tax are optional withholdings that apply to
-- some vouchers (e.g. a vendor payment) and not others (e.g. payroll, where
-- they stay 0 and net_amount = gross_amount).
ADD COLUMN IF NOT EXISTS particulars TEXT,
ADD COLUMN IF NOT EXISTS gross_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS vat_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS vat_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS wtax_rate NUMERIC(5,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS wtax_amount NUMERIC(18,2) NOT NULL DEFAULT 0,

-- PO reference (optional — only set when this voucher pays against a PO).
ADD COLUMN IF NOT EXISTS po_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS po_amount NUMERIC(18,2),

-- Accounting entries ledger snapshot.
ADD COLUMN IF NOT EXISTS account_code VARCHAR(30),
ADD COLUMN IF NOT EXISTS budget_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS obligation_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS balance_amount NUMERIC(18,2) NOT NULL DEFAULT 0,

-- Audit / workflow. The voucher moves through four offices in a straight line
-- (see status comment above); each stage past Originating stamps who moved it
-- there and when. Rejection can happen from FMD's or Accounting's stage, so
-- rejected_by/rejected_at/rejection_reason are shared rather than per-stage.
ADD COLUMN IF NOT EXISTS remarks TEXT,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS submitted_by INTEGER,              -- Originating -> FMD
ADD COLUMN IF NOT EXISTS fmd_approved_by INTEGER,           -- FMD -> Accounting (obligation checked)
ADD COLUMN IF NOT EXISTS accounting_processed_by INTEGER,   -- Accounting -> Treasury
ADD COLUMN IF NOT EXISTS treasury_released_by INTEGER,      -- Treasury -> Released
ADD COLUMN IF NOT EXISTS rejected_by INTEGER,
ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS fmd_approved_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS accounting_processed_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS treasury_released_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS rejected_at TIMESTAMP,
ADD COLUMN IF NOT EXISTS rejection_reason TEXT,
ADD COLUMN IF NOT EXISTS updated_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- net_amount is always gross minus the withheld amounts — added separately
-- since a generated column can't be declared alongside a plain ALTER batch
-- referencing columns created in the same statement.
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS net_amount NUMERIC(18,2)
    GENERATED ALWAYS AS (gross_amount - COALESCE(vat_amount, 0) - COALESCE(wtax_amount, 0)) STORED;

COMMENT ON TABLE  fims.disbursement_vouchers IS 'Disbursement Vouchers (General Form No. 1) — one generic voucher shape for any claimant/payment type';
COMMENT ON COLUMN fims.disbursement_vouchers.rfa_allocation_id IS 'Soft reference to fims.budget_allocation.allocation_id where allocation_type = ''RFA''; no FK — that feature has no backend yet';
COMMENT ON COLUMN fims.disbursement_vouchers.net_amount IS 'gross_amount - vat_amount - wtax_amount; vat/wtax default to 0 when not applicable (e.g. payroll), so net_amount = gross_amount';
COMMENT ON COLUMN fims.disbursement_vouchers.budget_amount IS 'Snapshot of the selected RFA''s funding ceiling at save time';
COMMENT ON COLUMN fims.disbursement_vouchers.balance_amount IS 'obligation_amount - net_amount; no payments-prior component since one RFA backs only one DV';

-- disbursement_vouchers indexes
CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_dv_date      ON fims.disbursement_vouchers (dv_date);
CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_fiscal_yr    ON fims.disbursement_vouchers (fiscal_yr);
CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_status       ON fims.disbursement_vouchers (status);
CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_expense_class ON fims.disbursement_vouchers (expense_class);
CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_rfa          ON fims.disbursement_vouchers (rfa_allocation_id);
CREATE INDEX IF NOT EXISTS idx_disbursement_vouchers_vendor_id    ON fims.disbursement_vouchers (vendor_id);
