-- ============================================================================
-- PAYROLL DE MINIMIS BENEFITS — the de minimis benefits and their tax-exempt LIMITS, for the year-end tax annualization
-- (PayrollManagementService::annualize() / annualizationWorksheet(); the worksheet's DE MINIMIS BENEFITS block: actual /
-- limit / excess). Editable on the Payroll Setup page (De Minimis Limits) — the BIR revises these ceilings.
--
--   limit_amount  the exempt amount, per limit_per:
--   limit_per     MONTH — limit_amount each month (the year's limit = limit_amount × 12), e.g. rice subsidy 2,000 / month;
--                 YEAR  — limit_amount for the whole year, e.g. uniform / clothing allowance
-- Whatever an employee receives over the limit joins the 13th month & other benefits (exempt up to ₱90,000).
--
-- A payroll item counts as one of these through fims.payroll_items.tax_treatment = 'DE_MINIMIS' + de_minimis_id (added
-- here, after this table exists — 48 runs before 57). The seed (04 - seed_data/19) links the monthly-import items RICE,
-- UNIFORM, CASH_GIFT and LOYALTY; SRI and PEI are paid through separate (special) payrolls — not linked until that template.
-- Seed values: the client's annualization worksheet (ANNUALIZATION TEMPLATE.xlsx) — to be confirmed by the client.
-- Re-running keeps edited limits (ON CONFLICT updates only the name / order).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_de_minimis_benefits (
    de_minimis_id   SERIAL PRIMARY KEY,
    code            VARCHAR(30) NOT NULL UNIQUE,
    name            VARCHAR(150) NOT NULL,
    limit_amount    NUMERIC(18,2) NOT NULL DEFAULT 0 CHECK (limit_amount >= 0),
    limit_per       VARCHAR(5) NOT NULL DEFAULT 'YEAR' CHECK (limit_per IN ('MONTH', 'YEAR')),
    sort_order      INTEGER NOT NULL DEFAULT 0,
    status          VARCHAR(20) NOT NULL DEFAULT 'ACTIVE' CHECK (status IN ('ACTIVE', 'INACTIVE')),
    remarks         TEXT,
    updated_by      INTEGER,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.payroll_de_minimis_benefits IS 'De minimis benefits and their tax-exempt limits for the year-end annualization (editable on Payroll Setup)';
COMMENT ON COLUMN fims.payroll_de_minimis_benefits.limit_per IS 'MONTH: limit_amount each month (× 12 for the year) | YEAR: limit_amount for the year';

INSERT INTO fims.payroll_de_minimis_benefits (code, name, limit_amount, limit_per, sort_order)
VALUES
    ('RICE',     'Rice Subsidy',                  2000.00, 'MONTH', 10),
    ('UNIFORM',  'Uniform / Clothing Allowance',  7000.00, 'YEAR',  20),
    ('CASH_GIFT','Cash Gift',                     5000.00, 'YEAR',  30),
    ('SRI',      'Service Recognition Incentive', 5000.00, 'YEAR',  40),
    ('PEI',      'Productivity Enhancement Incentive', 5000.00, 'YEAR', 50),
    ('LOYALTY',  'Loyalty Award',                10000.00, 'YEAR',  60)
ON CONFLICT (code) DO UPDATE SET name = EXCLUDED.name, sort_order = EXCLUDED.sort_order;

-- the payroll item → its de minimis benefit (only for tax_treatment = 'DE_MINIMIS')
ALTER TABLE fims.payroll_items
ADD COLUMN IF NOT EXISTS de_minimis_id INTEGER REFERENCES fims.payroll_de_minimis_benefits (de_minimis_id);

COMMENT ON COLUMN fims.payroll_items.de_minimis_id IS 'The de minimis benefit this item is (fims.payroll_de_minimis_benefits) — set when tax_treatment = DE_MINIMIS';
