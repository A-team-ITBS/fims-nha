-- ============================================================================
-- Payroll Deduction Types — standard deduction classifications used across
-- all payroll records. Maps to government remittance agencies (BIR, SSS,
-- PhilHealth, Pag-IBIG, etc.) and internal cost allocations.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_deduction_types (
    deduction_type_id SERIAL PRIMARY KEY,
    code VARCHAR(30) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    description TEXT,

    -- Category determines treatment: TAX (withheld, remitted to BIR),
    -- SOCIAL_SECURITY (SSS/PhilHealth/Pag-IBIG), LOAN (salary loans, advances),
    -- BENEFIT (health insurance, dental, etc.), OTHER (unions, club dues, etc.)
    category VARCHAR(30) NOT NULL, -- TAX | SOCIAL_SECURITY | LOAN | BENEFIT | OTHER

    -- Remittance agency (if any): BIR | SSS | PHILHEALTH | PAGIBIG | INTERNAL | NONE
    remittance_agency VARCHAR(30),

    -- Withholding tax-specific: atc_code links to BIR's Accounting Treatment Code
    -- for tax annualization reconciliation
    atc_code VARCHAR(10),

    -- Calculation method: PERCENTAGE (rate * base), FIXED_AMOUNT, TABLE_BASED
    calculation_method VARCHAR(30) DEFAULT 'PERCENTAGE',
    default_rate NUMERIC(5,2), -- For percentage-based deductions

    -- For tax deductions: whether to include in annual tax computation
    include_in_annualization BOOLEAN DEFAULT FALSE,

    -- Display order in payroll forms
    sort_order INTEGER DEFAULT 0,

    -- Status: ACTIVE | INACTIVE (inactive types won't appear in new deductions)
    status VARCHAR(20) DEFAULT 'ACTIVE',

    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.payroll_deduction_types IS 'Standard deduction types mapped to government agencies and cost allocations';
COMMENT ON COLUMN fims.payroll_deduction_types.code IS 'Short code (e.g. BIR_TAX, SSS, PHILHEALTH, PAGIBIG, WITAX, LOAN_ADV)';
COMMENT ON COLUMN fims.payroll_deduction_types.category IS 'TAX | SOCIAL_SECURITY | LOAN | BENEFIT | OTHER';
COMMENT ON COLUMN fims.payroll_deduction_types.remittance_agency IS 'Agency to which deductions are remitted (for reconciliation)';
COMMENT ON COLUMN fims.payroll_deduction_types.atc_code IS 'BIR Accounting Treatment Code for tax deductions (for annualization)';
COMMENT ON COLUMN fims.payroll_deduction_types.include_in_annualization IS 'Whether this deduction participates in year-end tax annualization';

CREATE INDEX IF NOT EXISTS idx_payroll_deduction_types_code ON fims.payroll_deduction_types (code);
CREATE INDEX IF NOT EXISTS idx_payroll_deduction_types_category ON fims.payroll_deduction_types (category);
CREATE INDEX IF NOT EXISTS idx_payroll_deduction_types_remittance_agency ON fims.payroll_deduction_types (remittance_agency);
CREATE INDEX IF NOT EXISTS idx_payroll_deduction_types_status ON fims.payroll_deduction_types (status);
