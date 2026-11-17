-- ============================================================================
-- fims.payroll_records: take the totals from fims.payroll_record_items.
--
-- gross_amount / net_amount were GENERATED from the fixed earning columns
-- (regular_pay, overtime_pay, bonus_pay, allowances, other_income). With line items they
-- are generated from stored category totals instead:
--
--   gross_amount = total_earnings
--   net_amount   = total_earnings + total_refunds - total_deductions
--   (total_employer_share is the government's share — informational, not part of net pay)
--
-- The totals are denormalised snapshots, like total_deductions already was: the payroll
-- service recomputes them from payroll_record_items whenever a line changes.
--
-- Not destructive: the old earning columns stay (marked deprecated), and existing records
-- that have no line items keep their gross pay (backfilled into total_earnings below).
-- ============================================================================

ALTER TABLE fims.payroll_records
ADD COLUMN IF NOT EXISTS total_earnings NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_refunds NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_employer_share NUMERIC(18,2) NOT NULL DEFAULT 0;

-- Backfill from the old fixed columns BEFORE gross_amount is regenerated (records without line items only).
UPDATE fims.payroll_records r
SET total_earnings = r.regular_pay + COALESCE(r.overtime_pay, 0) + COALESCE(r.bonus_pay, 0)
                   + COALESCE(r.allowances, 0) + COALESCE(r.other_income, 0)
WHERE r.total_earnings = 0
  AND NOT EXISTS (SELECT 1 FROM fims.payroll_record_items i WHERE i.payroll_record_id = r.payroll_record_id);

-- Generated columns cannot be altered in place; net_amount depends on gross_amount, so drop it first.
ALTER TABLE fims.payroll_records
DROP COLUMN IF EXISTS net_amount,
DROP COLUMN IF EXISTS gross_amount;

ALTER TABLE fims.payroll_records
ADD COLUMN gross_amount NUMERIC(18,2) GENERATED ALWAYS AS (total_earnings) STORED,
ADD COLUMN net_amount NUMERIC(18,2) GENERATED ALWAYS AS (total_earnings + total_refunds - COALESCE(total_deductions, 0)) STORED;

COMMENT ON COLUMN fims.payroll_records.total_earnings IS 'Sum of payroll_record_items of category EARNING';
COMMENT ON COLUMN fims.payroll_records.total_refunds IS 'Sum of payroll_record_items of category REFUND (returned to the employee)';
COMMENT ON COLUMN fims.payroll_records.total_deductions IS 'Sum of payroll_record_items of category DEDUCTION';
COMMENT ON COLUMN fims.payroll_records.total_employer_share IS 'Sum of payroll_record_items of category EMPLOYER_SHARE (not part of net pay)';
COMMENT ON COLUMN fims.payroll_records.gross_amount IS 'Generated: total_earnings';
COMMENT ON COLUMN fims.payroll_records.net_amount IS 'Generated: total_earnings + total_refunds - total_deductions';
COMMENT ON COLUMN fims.payroll_records.regular_pay IS 'DEPRECATED — use payroll_record_items; kept so older records and code keep working';
COMMENT ON COLUMN fims.payroll_records.overtime_pay IS 'DEPRECATED — use payroll_record_items';
COMMENT ON COLUMN fims.payroll_records.bonus_pay IS 'DEPRECATED — use payroll_record_items';
COMMENT ON COLUMN fims.payroll_records.allowances IS 'DEPRECATED — use payroll_record_items';
COMMENT ON COLUMN fims.payroll_records.other_income IS 'DEPRECATED — use payroll_record_items';

-- ── Clean-up for later (NOT run) ─────────────────────────────────────────────
-- Once the payroll services read and write payroll_items / payroll_record_items:
--   ALTER TABLE fims.payroll_remittances DROP COLUMN deduction_type_id;
--   DROP TABLE fims.payroll_deductions;
--   DROP TABLE fims.payroll_deduction_types;
--   ALTER TABLE fims.payroll_records DROP COLUMN regular_pay, DROP COLUMN overtime_pay,
--       DROP COLUMN bonus_pay, DROP COLUMN allowances, DROP COLUMN other_income;
