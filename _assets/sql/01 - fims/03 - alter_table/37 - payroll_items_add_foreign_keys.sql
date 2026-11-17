-- ============================================================================
-- Foreign keys / constraints for fims.payroll_items and fims.payroll_record_items,
-- and the link from fims.payroll_remittances to the new payroll items.
-- Runs after 36 - payroll_add_foreign_keys.sql (which creates the payroll_records FKs).
-- Postgres has no ADD CONSTRAINT IF NOT EXISTS, so every constraint is guarded.
-- ============================================================================

-- =============================================
-- PAYROLL_ITEMS CONSTRAINTS
-- =============================================
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_items_account_code') THEN
        ALTER TABLE fims.payroll_items
        ADD CONSTRAINT fk_payroll_items_account_code
        FOREIGN KEY (account_code) REFERENCES fims.chart_of_accounts(account_code);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_items_created_by') THEN
        ALTER TABLE fims.payroll_items
        ADD CONSTRAINT fk_payroll_items_created_by
        FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_items_updated_by') THEN
        ALTER TABLE fims.payroll_items
        ADD CONSTRAINT fk_payroll_items_updated_by
        FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;
    END IF;
END $$;

-- =============================================
-- PAYROLL_RECORD_ITEMS CONSTRAINTS
-- =============================================
DO $$
BEGIN
    -- Deleting a payroll record removes its amounts (same as payroll_deductions did).
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_record_items_payroll_record_id') THEN
        ALTER TABLE fims.payroll_record_items
        ADD CONSTRAINT fk_payroll_record_items_payroll_record_id
        FOREIGN KEY (payroll_record_id) REFERENCES fims.payroll_records(payroll_record_id) ON DELETE CASCADE;
    END IF;

    -- An item that has been used in a payroll can no longer be deleted (set it INACTIVE instead).
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_record_items_payroll_item_id') THEN
        ALTER TABLE fims.payroll_record_items
        ADD CONSTRAINT fk_payroll_record_items_payroll_item_id
        FOREIGN KEY (payroll_item_id) REFERENCES fims.payroll_items(payroll_item_id) ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_record_items_account_code') THEN
        ALTER TABLE fims.payroll_record_items
        ADD CONSTRAINT fk_payroll_record_items_account_code
        FOREIGN KEY (account_code) REFERENCES fims.chart_of_accounts(account_code);
    END IF;

    -- One amount per item per payroll record (one column per item in the monthly payroll register).
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'uq_payroll_record_items_record_item') THEN
        ALTER TABLE fims.payroll_record_items
        ADD CONSTRAINT uq_payroll_record_items_record_item
        UNIQUE (payroll_record_id, payroll_item_id);
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_record_items_created_by') THEN
        ALTER TABLE fims.payroll_record_items
        ADD CONSTRAINT fk_payroll_record_items_created_by
        FOREIGN KEY (created_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_record_items_updated_by') THEN
        ALTER TABLE fims.payroll_record_items
        ADD CONSTRAINT fk_payroll_record_items_updated_by
        FOREIGN KEY (updated_by) REFERENCES uac.users(user_id) ON DELETE SET NULL;
    END IF;
END $$;

-- =============================================
-- PAYROLL_REMITTANCES -> PAYROLL_ITEMS
-- A remittance is per agency and per item; deduction_type_id (-> payroll_deduction_types) stays
-- until the services use payroll_item_id, then it can be dropped together with the old tables.
-- =============================================
ALTER TABLE fims.payroll_remittances
ADD COLUMN IF NOT EXISTS payroll_item_id INTEGER;

COMMENT ON COLUMN fims.payroll_remittances.payroll_item_id IS 'Payroll item being remitted (replaces deduction_type_id)';

CREATE INDEX IF NOT EXISTS idx_payroll_remittances_payroll_item_id ON fims.payroll_remittances (payroll_item_id);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'fk_payroll_remittances_payroll_item_id') THEN
        ALTER TABLE fims.payroll_remittances
        ADD CONSTRAINT fk_payroll_remittances_payroll_item_id
        FOREIGN KEY (payroll_item_id) REFERENCES fims.payroll_items(payroll_item_id) ON DELETE SET NULL;
    END IF;
END $$;
