-- ============================================================================
-- Payroll Periods — fiscal year + month combinations for which payroll is
-- processed. One row per valid period; not automatically created, but manually
-- added by Admin as payroll cycles are established.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_periods (
    payroll_period_id SERIAL PRIMARY KEY,
    fiscal_yr SMALLINT NOT NULL,
    payroll_month SMALLINT NOT NULL, -- 1=January to 13=13th month/year-end
    period_name VARCHAR(100), -- e.g. "January 2025", "13th Month 2024"

    -- Period boundaries
    period_start_date DATE NOT NULL,
    period_end_date DATE NOT NULL,
    cutoff_date DATE, -- Last day to submit timesheets/adjustments

    -- Status: OPEN (accepting entries) | LOCKED (no changes allowed) | CLOSED (finalized)
    status VARCHAR(20) DEFAULT 'OPEN',

    -- Payroll processing
    prepared_by INTEGER,
    reviewed_by INTEGER,
    approved_by INTEGER,
    prepared_at TIMESTAMP,
    reviewed_at TIMESTAMP,
    approved_at TIMESTAMP,

    remarks TEXT,
    rfa_allocation_id INTEGER, -- the month's payroll RFA (fims.budget_allocation), created by Payroll Management → Create RFA
    created_by INTEGER,
    updated_by INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(fiscal_yr, payroll_month)
);

COMMENT ON TABLE fims.payroll_periods IS 'Fiscal year + month combinations for payroll processing cycles';
COMMENT ON COLUMN fims.payroll_periods.payroll_month IS '1-12 for regular months, 13 for year-end adjustment/13th month';
COMMENT ON COLUMN fims.payroll_periods.period_name IS 'Human-readable period (e.g. "January 2025", "December 2024 - 13th Month")';
COMMENT ON COLUMN fims.payroll_periods.status IS 'OPEN | LOCKED | CLOSED — controls edit/submission rights';

CREATE INDEX IF NOT EXISTS idx_payroll_periods_fiscal_yr ON fims.payroll_periods (fiscal_yr);
CREATE INDEX IF NOT EXISTS idx_payroll_periods_status ON fims.payroll_periods (status);
CREATE INDEX IF NOT EXISTS idx_payroll_periods_period_dates ON fims.payroll_periods (period_start_date, period_end_date);

-- Payroll RFA: Payroll Management → Create RFA builds a Draft RFA (direct COE draw) from the imported month's account codes and
-- amounts (expense accounts + employer share); once Approved it is the only RFA the month's payroll DV may use. No FK: a deleted
-- (soft-deleted) RFA simply stops counting, and Create RFA can be used again.
ALTER TABLE fims.payroll_periods ADD COLUMN IF NOT EXISTS rfa_allocation_id INTEGER;
COMMENT ON COLUMN fims.payroll_periods.rfa_allocation_id IS 'The month''s payroll RFA (fims.budget_allocation.allocation_id) — Payroll Management → Create RFA; the payroll DV must use it';
CREATE INDEX IF NOT EXISTS idx_payroll_periods_rfa ON fims.payroll_periods (rfa_allocation_id);
