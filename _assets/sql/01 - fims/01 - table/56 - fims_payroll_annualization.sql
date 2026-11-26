-- ============================================================================
-- PAYROLL ANNUALIZATION — the year-end withholding tax on compensation, one row per employee per fiscal year
-- (Payroll Management → Annualization; PayrollManagementService::annualize()). It feeds the employee's BIR 2316.
--
-- Rebuilt from the imported payroll each time Accounting clicks Annualize (no hand edits here — correct the payroll and
-- annualize again): every payroll_record_items line of the year's months, by its Payroll Setup item's tax_treatment
-- (01 - table/48), summed per employee:
--   gross_compensation  = taxable + non-taxable pay + de minimis + 13th month / other benefits
--   de minimis          = rice / uniform up to their ceilings; the excess joins the benefits
--   benefits_exempt     = 13th month and other benefits (+ excess de minimis) up to ₱90,000; the rest is taxable
--   total_non_taxable   = non-taxable pay + de minimis exempt + benefits_exempt + contributions
--   taxable_income      = gross_compensation − total_non_taxable (not below 0)
--   tax_due             = the TRAIN graduated table (2023 onward) on taxable_income
--   tax_adjustment      = tax_due − tax_withheld:  > 0 UNDERPAID (to withhold), < 0 OVERPAID (to refund), else CORRECT
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_annualization (
    annualization_id        SERIAL PRIMARY KEY,
    fiscal_yr               INTEGER NOT NULL,
    employee_id             INTEGER NOT NULL REFERENCES fims.payroll_employees (employee_id),
    months_paid             INTEGER NOT NULL DEFAULT 0,
    taxable_pay             NUMERIC(18,2) NOT NULL DEFAULT 0,   -- TAXABLE items (net of LWOP / recouped overpayments)
    non_taxable_pay         NUMERIC(18,2) NOT NULL DEFAULT 0,   -- NON_TAXABLE items (PERA)
    de_minimis              NUMERIC(18,2) NOT NULL DEFAULT 0,   -- rice + uniform paid
    de_minimis_exempt       NUMERIC(18,2) NOT NULL DEFAULT 0,   -- … up to their ceilings
    benefits                NUMERIC(18,2) NOT NULL DEFAULT 0,   -- 13th month / other benefits + de minimis over the ceilings
    benefits_exempt         NUMERIC(18,2) NOT NULL DEFAULT 0,   -- … up to ₱90,000
    contributions           NUMERIC(18,2) NOT NULL DEFAULT 0,   -- GSIS / PhilHealth / Pag-IBIG / union dues
    gross_compensation      NUMERIC(18,2) NOT NULL DEFAULT 0,
    total_non_taxable       NUMERIC(18,2) NOT NULL DEFAULT 0,
    taxable_income          NUMERIC(18,2) NOT NULL DEFAULT 0,
    tax_due                 NUMERIC(18,2) NOT NULL DEFAULT 0,
    tax_withheld            NUMERIC(18,2) NOT NULL DEFAULT 0,
    tax_adjustment          NUMERIC(18,2) NOT NULL DEFAULT 0,
    status                  VARCHAR(10) NOT NULL DEFAULT 'CORRECT' CHECK (status IN ('CORRECT', 'UNDERPAID', 'OVERPAID')),
    computed_by             INTEGER,
    computed_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_payroll_annualization UNIQUE (fiscal_yr, employee_id)
);

COMMENT ON TABLE fims.payroll_annualization IS 'Year-end tax annualization per employee per fiscal year — rebuilt from the imported payroll by Annualize (PayrollManagementService::annualize()); feeds the BIR 2316';
COMMENT ON COLUMN fims.payroll_annualization.tax_adjustment IS 'tax_due − tax_withheld: positive = still to withhold (UNDERPAID), negative = to refund (OVERPAID)';

CREATE INDEX IF NOT EXISTS idx_payroll_annualization_year ON fims.payroll_annualization (fiscal_yr);
