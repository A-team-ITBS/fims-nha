-- ============================================================================
-- Payroll Remittances — tracking withholdings remitted to government agencies
-- (BIR, SSS, PhilHealth, Pag-IBIG) and internal allocations. One row per
-- payroll period per agency per deduction type (or consolidated per agency).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_remittances (
    payroll_remittance_id SERIAL PRIMARY KEY,
    payroll_period_id INTEGER NOT NULL,
    remittance_agency VARCHAR(30) NOT NULL, -- GSIS | BIR | PHILHEALTH | PAGIBIG | OTHERS (Deductions & Remittances page)
    deduction_type_id INTEGER,

    -- Remittance details
    due_date DATE, -- set by Accounting when it schedules the remittance for Treasury
    remittance_date DATE,
    remittance_no VARCHAR(50), -- Official remittance receipt/reference number
    remittance_method VARCHAR(30), -- ONLINE | CHECK | BANK_TRANSFER | MANUAL

    -- Amount tracking
    total_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
    amount_remitted NUMERIC(18,2) DEFAULT 0,
    amount_pending NUMERIC(18,2) DEFAULT 0,

    -- Status (Deductions & Remittances page, DeductionRemittanceService): a row is created when Accounting schedules a
    -- month's agency remittance — SCHEDULED (for Treasury) → REMITTED (Treasury paid it) → CONFIRMED (agency receipt);
    -- CANCELLED = unscheduled. PENDING (deductions not scheduled yet) is shown without a row.
    remittance_status VARCHAR(20) DEFAULT 'PENDING',

    -- Proof & audit trail
    remittance_receipt_no VARCHAR(50), -- Agency's confirmation number
    receipt_date DATE,
    receipt_attachment_id INTEGER, -- Reference to file_attachments table

    -- Consolidation: if NULL, this is a detail row; if set, this is a consolidated row covering multiple detail rows
    is_consolidated BOOLEAN DEFAULT FALSE,

    -- BIR-specific: tax return filing reference
    tax_return_form VARCHAR(50), -- 2307, 1601C, etc.
    tax_return_period VARCHAR(20), -- For 1601C (monthly) vs 2307 (annual)

    -- Related DV (remittance voucher)
    disbursement_voucher_id INTEGER,

    remarks TEXT,
    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.payroll_remittances IS 'Track withholdings remitted to BIR, SSS, PhilHealth, Pag-IBIG, and internal allocations';
COMMENT ON COLUMN fims.payroll_remittances.remittance_agency IS 'GSIS | BIR | PHILHEALTH | PAGIBIG | OTHERS';
COMMENT ON COLUMN fims.payroll_remittances.remittance_status IS 'SCHEDULED | REMITTED | CONFIRMED | CANCELLED (PENDING = not scheduled yet, no row)';
COMMENT ON COLUMN fims.payroll_remittances.due_date IS 'Due date Accounting set when scheduling the remittance for Treasury';
COMMENT ON COLUMN fims.payroll_remittances.is_consolidated IS 'TRUE if this row consolidates multiple detail rows for the same period/agency';
COMMENT ON COLUMN fims.payroll_remittances.tax_return_form IS 'BIR form reference (2307 for annual, 1601C for monthly, etc.)';
COMMENT ON COLUMN fims.payroll_remittances.disbursement_voucher_id IS 'DV used for remittance payment (optional, for consolidation)';

CREATE INDEX IF NOT EXISTS idx_payroll_remittances_payroll_period_id ON fims.payroll_remittances (payroll_period_id);
CREATE INDEX IF NOT EXISTS idx_payroll_remittances_agency ON fims.payroll_remittances (remittance_agency);
CREATE INDEX IF NOT EXISTS idx_payroll_remittances_status ON fims.payroll_remittances (remittance_status);
CREATE INDEX IF NOT EXISTS idx_payroll_remittances_dv_id ON fims.payroll_remittances (disbursement_voucher_id);
CREATE INDEX IF NOT EXISTS idx_payroll_remittances_period_agency ON fims.payroll_remittances (payroll_period_id, remittance_agency);
