-- ============================================================================
-- Payroll Records — one per employee per payroll period. Links an employee
-- to a payroll period and holds the gross/net amounts. Detail deductions
-- (SSS, PhilHealth, tax, loans, etc.) live in fims.payroll_deductions.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_records (
    payroll_record_id SERIAL PRIMARY KEY,
    payroll_period_id INTEGER NOT NULL,
    employee_id INTEGER NOT NULL,

    -- Salary basis (snapshot at time of payroll)
    base_salary NUMERIC(18,2) NOT NULL DEFAULT 0,
    days_worked NUMERIC(5,2) DEFAULT 0,
    hours_worked NUMERIC(8,2) DEFAULT 0,

    -- Earnings components
    regular_pay NUMERIC(18,2) NOT NULL DEFAULT 0, -- base_salary or daily/hourly × days/hours
    overtime_pay NUMERIC(18,2) DEFAULT 0,
    bonus_pay NUMERIC(18,2) DEFAULT 0,
    allowances NUMERIC(18,2) DEFAULT 0, -- Representation, transportation, etc.
    other_income NUMERIC(18,2) DEFAULT 0,

    gross_amount NUMERIC(18,2) GENERATED ALWAYS AS (
        regular_pay + COALESCE(overtime_pay, 0) + COALESCE(bonus_pay, 0) +
        COALESCE(allowances, 0) + COALESCE(other_income, 0)
    ) STORED,

    -- Total deductions (sum of all payroll_deductions rows for this record)
    -- Kept as denormalized snapshot for performance
    total_deductions NUMERIC(18,2) DEFAULT 0,

    -- A generated column cannot reference another generated column (gross_amount), so the earnings are repeated.
    -- 03 - alter_table/38 later regenerates both from the payroll_record_items totals.
    net_amount NUMERIC(18,2) GENERATED ALWAYS AS (
        regular_pay + COALESCE(overtime_pay, 0) + COALESCE(bonus_pay, 0) +
        COALESCE(allowances, 0) + COALESCE(other_income, 0) - COALESCE(total_deductions, 0)
    ) STORED,

    -- Payment status: DRAFT | FOR_REVIEW | APPROVED | RELEASED | PAID | REVERSED
    status VARCHAR(20) DEFAULT 'DRAFT',

    -- Payment details (set when RELEASED)
    payment_method VARCHAR(20), -- BANK_TRANSFER | CHECK | CASH
    bank_account_id INTEGER,
    check_no VARCHAR(20),
    release_date DATE,

    -- Payroll DV linkage (one DV per payroll record or multiple employees per DV)
    disbursement_voucher_id INTEGER,

    -- Audit trail
    prepared_by INTEGER,
    reviewed_by INTEGER,
    approved_by INTEGER,
    released_by INTEGER,
    prepared_at TIMESTAMP,
    reviewed_at TIMESTAMP,
    approved_at TIMESTAMP,
    released_at TIMESTAMP,

    remarks TEXT,
    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(payroll_period_id, employee_id)
);

COMMENT ON TABLE fims.payroll_records IS 'Payroll header record per employee per period; deductions stored in payroll_deductions';
COMMENT ON COLUMN fims.payroll_records.base_salary IS 'Employee monthly salary at time of payroll (snapshot)';
COMMENT ON COLUMN fims.payroll_records.gross_amount IS 'Sum of all earnings (regular + overtime + bonus + allowances + other)';
COMMENT ON COLUMN fims.payroll_records.total_deductions IS 'Denormalized sum of all deductions (payroll_deductions rows)';
COMMENT ON COLUMN fims.payroll_records.net_amount IS 'gross_amount - total_deductions';
COMMENT ON COLUMN fims.payroll_records.disbursement_voucher_id IS 'Optional link to DV if payroll is paid via DV';
COMMENT ON COLUMN fims.payroll_records.status IS 'DRAFT | FOR_REVIEW | APPROVED | RELEASED | PAID | REVERSED';

CREATE INDEX IF NOT EXISTS idx_payroll_records_payroll_period_id ON fims.payroll_records (payroll_period_id);
CREATE INDEX IF NOT EXISTS idx_payroll_records_employee_id ON fims.payroll_records (employee_id);
CREATE INDEX IF NOT EXISTS idx_payroll_records_status ON fims.payroll_records (status);
CREATE INDEX IF NOT EXISTS idx_payroll_records_dv_id ON fims.payroll_records (disbursement_voucher_id);
CREATE INDEX IF NOT EXISTS idx_payroll_records_period_employee ON fims.payroll_records (payroll_period_id, employee_id);
