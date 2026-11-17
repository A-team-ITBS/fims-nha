-- ============================================================================
-- Add foreign keys for payroll tables and link disbursement_vouchers to
-- payroll records for PS (Personal Services) payments.
-- ============================================================================

-- ── Payroll Employees FKs ──
ALTER TABLE fims.payroll_employees
ADD CONSTRAINT fk_payroll_employees_created_by
    FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_employees
ADD CONSTRAINT fk_payroll_employees_updated_by
    FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

-- ── Payroll Deduction Types FKs ──
ALTER TABLE fims.payroll_deduction_types
ADD CONSTRAINT fk_payroll_deduction_types_created_by
    FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_deduction_types
ADD CONSTRAINT fk_payroll_deduction_types_updated_by
    FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

-- ── Payroll Periods FKs ──
ALTER TABLE fims.payroll_periods
ADD CONSTRAINT fk_payroll_periods_prepared_by
    FOREIGN KEY (prepared_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_periods
ADD CONSTRAINT fk_payroll_periods_reviewed_by
    FOREIGN KEY (reviewed_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_periods
ADD CONSTRAINT fk_payroll_periods_approved_by
    FOREIGN KEY (approved_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_periods
ADD CONSTRAINT fk_payroll_periods_created_by
    FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_periods
ADD CONSTRAINT fk_payroll_periods_updated_by
    FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

-- ── Payroll Records FKs ──
ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_payroll_period_id
    FOREIGN KEY (payroll_period_id) REFERENCES fims.payroll_periods(payroll_period_id) ON DELETE RESTRICT;

ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_employee_id
    FOREIGN KEY (employee_id) REFERENCES fims.payroll_employees(employee_id) ON DELETE RESTRICT;

ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_prepared_by
    FOREIGN KEY (prepared_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_reviewed_by
    FOREIGN KEY (reviewed_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_approved_by
    FOREIGN KEY (approved_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_released_by
    FOREIGN KEY (released_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_created_by
    FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_updated_by
    FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

-- Note: disbursement_voucher_id FK will be added when DV columns are modified below

-- ── Payroll Deductions FKs ──
ALTER TABLE fims.payroll_deductions
ADD CONSTRAINT fk_payroll_deductions_payroll_record_id
    FOREIGN KEY (payroll_record_id) REFERENCES fims.payroll_records(payroll_record_id) ON DELETE CASCADE;

ALTER TABLE fims.payroll_deductions
ADD CONSTRAINT fk_payroll_deductions_deduction_type_id
    FOREIGN KEY (deduction_type_id) REFERENCES fims.payroll_deduction_types(deduction_type_id) ON DELETE RESTRICT;

ALTER TABLE fims.payroll_deductions
ADD CONSTRAINT fk_payroll_deductions_created_by
    FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_deductions
ADD CONSTRAINT fk_payroll_deductions_updated_by
    FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

-- ── Payroll Remittances FKs ──
ALTER TABLE fims.payroll_remittances
ADD CONSTRAINT fk_payroll_remittances_payroll_period_id
    FOREIGN KEY (payroll_period_id) REFERENCES fims.payroll_periods(payroll_period_id) ON DELETE RESTRICT;

ALTER TABLE fims.payroll_remittances
ADD CONSTRAINT fk_payroll_remittances_deduction_type_id
    FOREIGN KEY (deduction_type_id) REFERENCES fims.payroll_deduction_types(deduction_type_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_remittances
ADD CONSTRAINT fk_payroll_remittances_created_by
    FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

ALTER TABLE fims.payroll_remittances
ADD CONSTRAINT fk_payroll_remittances_updated_by
    FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;

-- Note: disbursement_voucher_id FK will be added when DV columns are modified

-- ════════════════════════════════════════════════════════════════
-- Link disbursement_vouchers to payroll records
-- ════════════════════════════════════════════════════════════════

-- Add payroll_record_id column if it doesn't exist (optional, for direct DV-to-payroll linking)
ALTER TABLE fims.disbursement_vouchers
ADD COLUMN IF NOT EXISTS payroll_record_id INTEGER;

-- Add FK if the column now exists
ALTER TABLE fims.disbursement_vouchers
ADD CONSTRAINT fk_disbursement_vouchers_payroll_record_id
    FOREIGN KEY (payroll_record_id) REFERENCES fims.payroll_records(payroll_record_id) ON DELETE SET NULL;

-- Add FK for payroll_records linking back to DV
ALTER TABLE fims.payroll_records
ADD CONSTRAINT fk_payroll_records_disbursement_voucher_id
    FOREIGN KEY (disbursement_voucher_id) REFERENCES fims.disbursement_vouchers(dv_id) ON DELETE SET NULL;

-- Add FK for remittance DVs
ALTER TABLE fims.payroll_remittances
ADD CONSTRAINT fk_payroll_remittances_disbursement_voucher_id
    FOREIGN KEY (disbursement_voucher_id) REFERENCES fims.disbursement_vouchers(dv_id) ON DELETE SET NULL;
