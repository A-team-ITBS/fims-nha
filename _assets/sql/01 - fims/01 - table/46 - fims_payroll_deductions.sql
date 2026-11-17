-- ============================================================================
-- Payroll Deductions — detail rows for each deduction applied to a payroll
-- record. One row per deduction type per employee per period. Tracks SSS,
-- PhilHealth, Pag-IBIG, BIR tax withholding, loans, union dues, etc.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_deductions (
    payroll_deduction_id SERIAL PRIMARY KEY,
    payroll_record_id INTEGER NOT NULL,
    deduction_type_id INTEGER NOT NULL,

    -- Deduction amount computation
    deduction_base NUMERIC(18,2) NOT NULL DEFAULT 0, -- Salary amount subject to deduction
    rate_percent NUMERIC(5,2) DEFAULT 0, -- For percentage-based deductions
    fixed_amount NUMERIC(18,2) DEFAULT 0, -- For fixed deductions
    calculated_amount NUMERIC(18,2) GENERATED ALWAYS AS (
        CASE
            WHEN fixed_amount > 0 THEN fixed_amount
            ELSE ROUND(deduction_base * rate_percent / 100, 2)
        END
    ) STORED,

    -- Deduction cap (if any, for SSS/PhilHealth salary ceiling)
    maximum_base NUMERIC(18,2),

    -- Year-to-date tracking (for annualization)
    ytd_deductions NUMERIC(18,2) DEFAULT 0,

    -- Reference details
    reference_no VARCHAR(50), -- Loan ID, check number, etc.
    reference_date DATE,

    -- Tax-specific fields
    is_withheld_tax BOOLEAN DEFAULT FALSE,
    atc_code VARCHAR(10), -- Links to payroll_deduction_types.atc_code for BIR reconciliation

    -- For loans/advances: repayment terms
    principal_amount NUMERIC(18,2),
    balance_amount NUMERIC(18,2),
    monthly_amortization NUMERIC(18,2),

    remarks TEXT,
    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.payroll_deductions IS 'Detail deduction rows per employee per payroll period; SSS, PhilHealth, tax, loans, etc.';
COMMENT ON COLUMN fims.payroll_deductions.deduction_base IS 'Gross salary or salary range subject to this deduction';
COMMENT ON COLUMN fims.payroll_deductions.calculated_amount IS 'Computed deduction: fixed_amount OR (deduction_base × rate_percent / 100)';
COMMENT ON COLUMN fims.payroll_deductions.ytd_deductions IS 'Year-to-date total for this deduction (used in annualization)';
COMMENT ON COLUMN fims.payroll_deductions.is_withheld_tax IS 'TRUE if this is a BIR withholding tax (links to remittance)';
COMMENT ON COLUMN fims.payroll_deductions.atc_code IS 'BIR Accounting Treatment Code for tax annualization reconciliation';

CREATE INDEX IF NOT EXISTS idx_payroll_deductions_payroll_record_id ON fims.payroll_deductions (payroll_record_id);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_deduction_type_id ON fims.payroll_deductions (deduction_type_id);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_is_withheld_tax ON fims.payroll_deductions (is_withheld_tax);
CREATE INDEX IF NOT EXISTS idx_payroll_deductions_atc_code ON fims.payroll_deductions (atc_code);
