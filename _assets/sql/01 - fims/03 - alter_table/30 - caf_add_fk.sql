-- ============================================================
-- ALTER: fims.caf
-- Foreign keys, added after all referenced tables exist. Postgres has no
-- ADD CONSTRAINT IF NOT EXISTS, so each one is guarded by a DO block.
--
-- Deliberately NOT constrained:
--   * user_id — no cross-schema FK to uac (same as fims.budget_allocation.user_id).
-- ============================================================

-- =============================================
-- CAF CONSTRAINTS
-- =============================================
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_caf_allocation'
    ) THEN
        ALTER TABLE fims.caf
        ADD CONSTRAINT fk_caf_allocation
        FOREIGN KEY (allocation_id) REFERENCES fims.budget_allocation(allocation_id);
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_caf_fiscal_year'
    ) THEN
        ALTER TABLE fims.caf
        ADD CONSTRAINT fk_caf_fiscal_year
        FOREIGN KEY (fiscal_year) REFERENCES fims.fiscal_year(fiscal_yr);
    END IF;
END $$;
