-- ============================================================================
-- PAYROLL ITEMS — setup catalogue of everything a payroll can carry: one row per
-- earning, deduction, refund or employer share. Replaces fims.payroll_deduction_types,
-- which only covered deductions and had no link to the chart of accounts.
-- Items are created by the Payroll setup page (Accounting Records); nothing is seeded.
--
--   account_code   GL account the item posts to (4 parts, e.g. 2-02-01-020); FK to
--                  fims.chart_of_accounts (added in 03 - alter_table/37). Which agency an item
--                  belongs to is not stored: it is the account's parent in the chart.
--   sl_code        optional subsidiary ledger of that account (fims.sl_masters) — the 5th part of
--                  an account code written as 2-02-01-020-10.
--   sl_detail_id   optional detail within that SL (fims.sl_details) — the 6th part, as in
--                  2-99-99-990-02-01.
--   account_code + sl_code + sl_detail_id is the same trio fims.journal_entry_voucher_lines and
--   fims.general_ledger already carry, so payroll lines post straight into vouchers and the ledger.
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
ADD COLUMN IF NOT EXISTS sl_code VARCHAR(30),
ADD COLUMN IF NOT EXISTS sl_detail_id INTEGER,
ADD COLUMN IF NOT EXISTS sort_order INTEGER NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE'
    CHECK (status IN ('ACTIVE', 'INACTIVE')),
ADD COLUMN IF NOT EXISTS remarks TEXT,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS updated_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.payroll_items IS 'Payroll item catalogue (earnings, deductions, refunds, employer shares) with the GL account each posts to';
COMMENT ON COLUMN fims.payroll_items.item_code IS 'Unique key, e.g. BASIC_SALARY';
COMMENT ON COLUMN fims.payroll_items.category IS 'EARNING | DEDUCTION | REFUND | EMPLOYER_SHARE; also decides the posting side (DEDUCTION = credit, the others = debit)';
COMMENT ON COLUMN fims.payroll_items.account_code IS 'GL account (fims.chart_of_accounts.account_code, 4 parts); NULL until Accounting assigns one';
COMMENT ON COLUMN fims.payroll_items.sl_code IS 'Optional subsidiary ledger of account_code (fims.sl_masters); needs account_code';
COMMENT ON COLUMN fims.payroll_items.sl_detail_id IS 'Optional detail within sl_code (fims.sl_details); needs sl_code';
COMMENT ON COLUMN fims.payroll_items.sort_order IS 'Display / report order';

-- payroll_items indexes
CREATE INDEX IF NOT EXISTS idx_payroll_items_category ON fims.payroll_items (category);
CREATE INDEX IF NOT EXISTS idx_payroll_items_status ON fims.payroll_items (status);
CREATE INDEX IF NOT EXISTS idx_payroll_items_account_code ON fims.payroll_items (account_code);
CREATE INDEX IF NOT EXISTS idx_payroll_items_sl_code ON fims.payroll_items (sl_code);
CREATE INDEX IF NOT EXISTS idx_payroll_items_sort_order ON fims.payroll_items (sort_order);
