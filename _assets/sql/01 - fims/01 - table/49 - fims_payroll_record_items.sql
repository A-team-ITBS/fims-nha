-- ============================================================================
-- PAYROLL RECORD ITEMS — the amounts of one payroll record (one employee, one
-- period): one row per payroll item with a value. Replaces fims.payroll_deductions
-- and the fixed earning columns of payroll_records, so earnings, deductions,
-- refunds and employer shares are all stored the same way.
--
-- Gross / net pay on fims.payroll_records are the sums of these rows by category
-- (see 03 - alter_table/38).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.payroll_record_items();
ALTER TABLE fims.payroll_record_items
ADD COLUMN IF NOT EXISTS payroll_record_item_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS payroll_record_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS payroll_item_id INTEGER NOT NULL,
-- Stored as entered; the item's category decides whether it adds to or reduces pay.
ADD COLUMN IF NOT EXISTS amount NUMERIC(18,2) NOT NULL DEFAULT 0,
-- Snapshot of the item's account when the payroll was prepared, so later changes to
-- payroll_items do not rewrite what a released payroll posted to. (The posting side follows
-- from the item's category, so it is not snapshotted.)
ADD COLUMN IF NOT EXISTS account_code VARCHAR(30),
ADD COLUMN IF NOT EXISTS sl_code VARCHAR(30),
ADD COLUMN IF NOT EXISTS sl_detail_id INTEGER,
ADD COLUMN IF NOT EXISTS reference_no VARCHAR(50), -- loan / policy number, etc.
ADD COLUMN IF NOT EXISTS remarks TEXT,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS updated_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.payroll_record_items IS 'Amount per payroll item per payroll record (earnings, deductions, refunds, employer shares)';
COMMENT ON COLUMN fims.payroll_record_items.amount IS 'Amount as entered; direction comes from payroll_items.category';
COMMENT ON COLUMN fims.payroll_record_items.account_code IS 'Snapshot of payroll_items.account_code at preparation time';
COMMENT ON COLUMN fims.payroll_record_items.sl_code IS 'Snapshot of payroll_items.sl_code at preparation time';
COMMENT ON COLUMN fims.payroll_record_items.sl_detail_id IS 'Snapshot of payroll_items.sl_detail_id at preparation time';

-- payroll_record_items indexes (the UNIQUE (payroll_record_id, payroll_item_id) constraint is in 03 - alter_table/37)
CREATE INDEX IF NOT EXISTS idx_payroll_record_items_payroll_item_id ON fims.payroll_record_items (payroll_item_id);
CREATE INDEX IF NOT EXISTS idx_payroll_record_items_account_code ON fims.payroll_record_items (account_code);
