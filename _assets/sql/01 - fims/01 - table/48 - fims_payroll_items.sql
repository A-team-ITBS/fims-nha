-- ============================================================================
-- PAYROLL ITEMS — setup catalogue of everything a payroll can carry: one row per
-- earning, deduction, refund or employer share. Replaces fims.payroll_deduction_types,
-- which only covered deductions and had no link to the chart of accounts.
-- Items are created by the Payroll setup page (Accounting Records); nothing is seeded.
--
--   account_code   GL account the item posts to; FK to fims.chart_of_accounts (added in
--                  03 - alter_table/37). It is a normal chart account: a 4-part account, or a
--                  sub-ledger account under one (5th / 6th part, e.g. 2-02-01-020-10), which the
--                  Payroll page adds to the chart itself when it does not exist yet. There is
--                  nothing separate to store for a sub-ledger. Which agency an item belongs to
--                  is not stored either: it is the account's parent in the chart.
--
-- Run-time amounts live in fims.payroll_record_items (49).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_items();
ALTER TABLE fims.payroll_items
ADD COLUMN IF NOT EXISTS payroll_item_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS item_code VARCHAR(60) NOT NULL UNIQUE,
ADD COLUMN IF NOT EXISTS item_name VARCHAR(150) NOT NULL,
-- EARNING: adds to gross pay | DEDUCTION: withheld from the employee | REFUND: returned to the
-- employee (adds to net pay) | EMPLOYER_SHARE: government's contribution, not part of net pay.
-- The category also decides the side the item posts to its account, so there is no entry_side column:
-- EARNING, REFUND and EMPLOYER_SHARE post as a DEBIT; DEDUCTION posts as a CREDIT.
ADD COLUMN IF NOT EXISTS category VARCHAR(20) NOT NULL
    CHECK (category IN ('EARNING', 'DEDUCTION', 'REFUND', 'EMPLOYER_SHARE')),
ADD COLUMN IF NOT EXISTS account_code VARCHAR(30),
ADD COLUMN IF NOT EXISTS sort_order INTEGER NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'
    CHECK (status IN ('ACTIVE', 'INACTIVE')),
ADD COLUMN IF NOT EXISTS remarks TEXT,
-- How the item counts in the year-end TAX ANNUALIZATION (Payroll Management → Annualization; BIR 2316):
--   TAXABLE            compensation that is taxed (basic pay, RA/TA, differentials …)
--   NON_TAXABLE        exempt compensation (PERA)
--   DE_MINIMIS_RICE    rice subsidy — exempt up to the de minimis ceiling, the excess joins BENEFIT_90K
--   DE_MINIMIS_UNIFORM uniform / clothing allowance — same
--   BENEFIT_90K        13th month pay and other benefits (mid-year / year-end bonus, cash gift, PEI …) — exempt up to ₱90,000
--   CONTRIBUTION       mandatory contributions / union dues withheld (GSIS, PhilHealth, Pag-IBIG) — non-taxable
--   WITHHOLDING_TAX    tax withheld on compensation
--   NONE               not part of it (loans, other payables, employer shares …)
-- The sign follows the category: an EARNING adds; a DEDUCTION adds to CONTRIBUTION / WITHHOLDING_TAX but takes away from
-- a pay treatment (e.g. leave without pay, a recouped overpayment); a REFUND adds to pay but takes away from
-- CONTRIBUTION / WITHHOLDING_TAX (a refund of what was withheld).
ADD COLUMN IF NOT EXISTS tax_treatment VARCHAR(20) NOT NULL DEFAULT 'NONE'
    CHECK (tax_treatment IN ('TAXABLE', 'NON_TAXABLE', 'DE_MINIMIS_RICE', 'DE_MINIMIS_UNIFORM', 'BENEFIT_90K', 'CONTRIBUTION', 'WITHHOLDING_TAX', 'NONE')),
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS updated_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.payroll_items IS 'Payroll item catalogue (earnings, deductions, refunds, employer shares) with the GL account each posts to';
COMMENT ON COLUMN fims.payroll_items.item_code IS 'Unique key, e.g. BASIC_SALARY';
COMMENT ON COLUMN fims.payroll_items.category IS 'EARNING | DEDUCTION | REFUND | EMPLOYER_SHARE; also decides the posting side (DEDUCTION = credit, the others = debit)';
COMMENT ON COLUMN fims.payroll_items.account_code IS 'GL account (fims.chart_of_accounts.account_code) — a chart account or a sub-ledger account under one; NULL until Accounting assigns one';
COMMENT ON COLUMN fims.payroll_items.sort_order IS 'Display / report order';

-- payroll_items indexes
CREATE INDEX IF NOT EXISTS idx_payroll_items_category ON fims.payroll_items (category);
CREATE INDEX IF NOT EXISTS idx_payroll_items_status ON fims.payroll_items (status);
CREATE INDEX IF NOT EXISTS idx_payroll_items_account_code ON fims.payroll_items (account_code);
CREATE INDEX IF NOT EXISTS idx_payroll_items_sort_order ON fims.payroll_items (sort_order);
