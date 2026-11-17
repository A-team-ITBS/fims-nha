-- ============================================================================
-- Payroll Employee Directory — one per NHA staff member or beneficiary eligible
-- for payroll processing. Links employee records for tax annualization,
-- withholding, and remittance tracking to BIR/SSS/PhilHealth/Pag-IBIG.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_employees (
    employee_id SERIAL PRIMARY KEY,
    employee_no VARCHAR(50) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    full_name VARCHAR(255) GENERATED ALWAYS AS (
        TRIM(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name))
    ) STORED,

    -- Government ID numbers
    tin VARCHAR(20) UNIQUE,
    sss_no VARCHAR(20) UNIQUE,
    philhealth_no VARCHAR(20) UNIQUE,
    pagibig_no VARCHAR(20) UNIQUE,

    -- Position & department
    position VARCHAR(150),
    department VARCHAR(150),
    salary_grade VARCHAR(20),

    -- Salary basis
    monthly_salary NUMERIC(18,2) DEFAULT 0,
    daily_rate NUMERIC(18,2) DEFAULT 0,
    hourly_rate NUMERIC(18,2) DEFAULT 0,

    -- Employment status: ACTIVE | ON_LEAVE | SEPARATED | RETIRED
    employment_status VARCHAR(20) DEFAULT 'ACTIVE',
    date_hired DATE,
    date_separated DATE,

    -- Bank details for payroll disbursement
    bank_name VARCHAR(100),
    bank_account_no VARCHAR(30),
    account_type VARCHAR(20), -- CHECKING | SAVINGS

    remarks TEXT,
    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.payroll_employees IS 'Employee directory for payroll processing, tax withholding, and remittance tracking';
COMMENT ON COLUMN fims.payroll_employees.employee_no IS 'Unique employee identifier (e.g. EMP-2025-001)';
COMMENT ON COLUMN fims.payroll_employees.full_name IS 'Generated full name (first + middle + last)';
COMMENT ON COLUMN fims.payroll_employees.employment_status IS 'ACTIVE | ON_LEAVE | SEPARATED | RETIRED';
COMMENT ON COLUMN fims.payroll_employees.monthly_salary IS 'Gross monthly salary for annualization calculations';

CREATE INDEX IF NOT EXISTS idx_payroll_employees_employee_no ON fims.payroll_employees (employee_no);
CREATE INDEX IF NOT EXISTS idx_payroll_employees_tin ON fims.payroll_employees (tin);
CREATE INDEX IF NOT EXISTS idx_payroll_employees_status ON fims.payroll_employees (employment_status);
