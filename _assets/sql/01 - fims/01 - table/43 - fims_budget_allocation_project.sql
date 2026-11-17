-- One row = one Project "block" of a fims.budget_allocation memo request (CBA / RFA / LAA / CFA) — a request can
-- now cover several independent projects, each with its own Program / Sub-Program / Project and its own itemized
-- COB draws (line_transaction, same per-line shape budget_allocation.line_transaction used to carry directly).
--
-- Mirrors fims.budget_req_project (one row per Project block under a budget_req header) — budget_allocation gets
-- the same "one header, many project blocks" shape via this child table instead of a flat program_code /
-- sub_program_code / project_code / line_transaction on the header row itself.
--
-- Soft-delete: rows are never removed, deleted_at is stamped instead (same convention as budget_allocation).
-- Foreign keys live in "03 - alter_table/46 - budget_allocation_project_add_fk.sql", added after all referenced
-- tables exist.
CREATE TABLE IF NOT EXISTS fims.budget_allocation_project();
ALTER TABLE fims.budget_allocation_project
ADD COLUMN IF NOT EXISTS budget_allocation_project_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS allocation_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS sort_order INTEGER NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS program_code VARCHAR(64),
ADD COLUMN IF NOT EXISTS sub_program_code VARCHAR(64),
ADD COLUMN IF NOT EXISTS project_code VARCHAR(64),
ADD COLUMN IF NOT EXISTS line_transaction JSONB,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP;

COMMENT ON TABLE  fims.budget_allocation_project IS 'One Project block (its own Program/Sub-Program/Project + itemized COB draws) belonging to a fims.budget_allocation memo request';
COMMENT ON COLUMN fims.budget_allocation_project.allocation_id IS 'The memo request this block belongs to (fims.budget_allocation.allocation_id)';
COMMENT ON COLUMN fims.budget_allocation_project.sort_order IS 'Display order of this block within its memo request (0-based, as added in the form)';
COMMENT ON COLUMN fims.budget_allocation_project.line_transaction IS 'This block''s itemized COB draws — same shape budget_allocation.line_transaction used to carry directly (array of {budget_line_item_id, requested_amount, allocated_amount, ...})';
COMMENT ON COLUMN fims.budget_allocation_project.deleted_at IS 'Soft delete: NULL = active, timestamp = deleted';

-- budget_allocation_project indexes
CREATE INDEX IF NOT EXISTS idx_budget_allocation_project_allocation_id   ON fims.budget_allocation_project (allocation_id);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_project_program_code   ON fims.budget_allocation_project (program_code);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_project_project_code   ON fims.budget_allocation_project (project_code);
CREATE INDEX IF NOT EXISTS idx_budget_allocation_project_deleted_at     ON fims.budget_allocation_project (deleted_at);
