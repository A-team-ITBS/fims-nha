-- ============================================================================
-- CAF (Certificate as to Availability of Funds) — one row per CAF issued against
-- a budget allocation (memo request) for a fiscal year. line_transaction holds the
-- CAF's lines as JSON (same shape as fims.budget_allocation.line_transaction).
--
-- Soft-delete: rows are never removed, deleted_at is stamped instead.
-- Foreign keys live in "03 - alter_table/30 - caf_add_fk.sql" (run after this file).
-- user_id has no FK on purpose — other fims tables keep user ids as plain INTEGER
-- too (no cross-schema FK to uac).
-- ============================================================================

-- =============================================
-- CAF TABLE
-- =============================================
CREATE TABLE IF NOT EXISTS fims.caf();
ALTER TABLE fims.caf
ADD COLUMN IF NOT EXISTS id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS caf_number VARCHAR(100),
ADD COLUMN IF NOT EXISTS document_type VARCHAR(255),
ADD COLUMN IF NOT EXISTS allocation_id INTEGER,
ADD COLUMN IF NOT EXISTS fiscal_year INTEGER,
ADD COLUMN IF NOT EXISTS line_transaction JSONB,
ADD COLUMN IF NOT EXISTS user_id INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;

COMMENT ON TABLE  fims.caf IS 'Certificate as to Availability of Funds (CAF) issued against a budget allocation, per fiscal year';
COMMENT ON COLUMN fims.caf.allocation_id IS 'The budget allocation (memo request) this CAF certifies (fims.budget_allocation.allocation_id)';
COMMENT ON COLUMN fims.caf.line_transaction IS 'CAF lines as JSON (an array of line objects, or a single object)';
COMMENT ON COLUMN fims.caf.user_id IS 'The user who created the CAF (uac.users.user_id) — no FK, see file header';
COMMENT ON COLUMN fims.caf.deleted_at IS 'Soft delete: NULL = active, timestamp = deleted';

-- caf indexes
CREATE INDEX IF NOT EXISTS idx_caf_caf_number    ON fims.caf (caf_number);
CREATE INDEX IF NOT EXISTS idx_caf_allocation_id ON fims.caf (allocation_id);
CREATE INDEX IF NOT EXISTS idx_caf_fiscal_year   ON fims.caf (fiscal_year);
CREATE INDEX IF NOT EXISTS idx_caf_deleted_at    ON fims.caf (deleted_at);
