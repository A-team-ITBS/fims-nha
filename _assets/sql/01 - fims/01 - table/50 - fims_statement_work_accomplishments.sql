-- Inspection and Acceptance Report (IAR) Module — INFRASTRUCTURE tab.
--   fims.statement_work_accomplishments   one row per approved Infrastructure RFA that has NO purchase order: the IAR number
--                                         (typed by the user, unique), a snapshot of the project's details loaded from the RFA
--                                         (project, location, program, contractor, contract cost, project cost), the dates
--                                         (started / completion (revised) / contract duration), the account entries, and the
--                                         Statement of Work Accomplishments (line_transaction).
-- The RFA is picked from the Budget Allocation by the app (approved, Infrastructure category, empty po_number, no active DV).
--
-- account_entries (array) — the RFA's accounts, pre-filled from its line_transaction; the total may not exceed contract_cost:
--   [ { "account_code": "1-06-04-010", "account_title": "Buildings", "amount": "12500000.00" } ]
-- line_transaction (array) — the Statement of Work Accomplishments, one element per work item:
--   [ { "description": "Excavation", "qty": "1.00", "cost": "500000.00", "accomplishment_pct": "35.00" } ]
--   description is required, qty > 0, cost >= 0, accomplishment_pct between 0 and 100 (checked by the app).

CREATE TABLE IF NOT EXISTS fims.statement_work_accomplishments (
    swa_id                   SERIAL PRIMARY KEY,
    iar_number               VARCHAR(50)   NOT NULL,            -- typed by the user (required, unique)
    allocation_id            INTEGER       NOT NULL,            -- the approved RFA (fims.budget_allocation.allocation_id)
    rfa_number               VARCHAR(100),                      -- the RFA number when it was recorded
    project_code             VARCHAR(64),
    project_name             VARCHAR(255),
    location                 VARCHAR(255),
    program_code             VARCHAR(64),
    program_name             VARCHAR(255),
    vendor_id                INTEGER,                           -- the contractor (fims.vendors.vendor_id)
    contractor               VARCHAR(255),
    contract_cost            NUMERIC(18,2) NOT NULL DEFAULT 0,  -- the amount approved on the RFA
    project_cost             NUMERIC(18,2),                     -- the original cost of the project
    date_started             DATE,
    completion_date_revised  DATE,
    contract_duration        VARCHAR(100),                      -- e.g. "180 calendar days"
    account_entries          JSONB         NOT NULL DEFAULT '[]'::jsonb,
    line_transaction         JSONB         NOT NULL DEFAULT '[]'::jsonb,
    created_by               INTEGER,
    created_at               TIMESTAMPTZ   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by               INTEGER,
    updated_at               TIMESTAMPTZ,
    CONSTRAINT uq_swa_iar_number     UNIQUE (iar_number),
    CONSTRAINT uq_swa_allocation_id  UNIQUE (allocation_id),
    CONSTRAINT fk_swa_allocation     FOREIGN KEY (allocation_id) REFERENCES fims.budget_allocation (allocation_id) ON DELETE RESTRICT,
    CONSTRAINT chk_swa_entries_array CHECK (jsonb_typeof(account_entries) = 'array'),
    CONSTRAINT chk_swa_lines_array   CHECK (jsonb_typeof(line_transaction) = 'array')
);
COMMENT ON TABLE  fims.statement_work_accomplishments IS 'IAR (Infrastructure): one row per approved Infrastructure RFA without a PO — project details loaded from the RFA, account entries and the Statement of Work Accomplishments';
COMMENT ON COLUMN fims.statement_work_accomplishments.contract_cost IS 'Amount approved on the RFA (sum of its requested amounts)';
COMMENT ON COLUMN fims.statement_work_accomplishments.project_cost  IS 'Original cost of the project';
COMMENT ON COLUMN fims.statement_work_accomplishments.line_transaction IS 'Statement of Work Accomplishments: array of {description, qty, cost, accomplishment_pct}';

CREATE INDEX IF NOT EXISTS idx_swa_vendor_id ON fims.statement_work_accomplishments (vendor_id);

-- The pro-forma JEV submitted for the record (IAR module → Pro-forma → Submit): once set, the pro-forma is read-only and cannot be submitted again.
ALTER TABLE fims.statement_work_accomplishments
    ADD COLUMN IF NOT EXISTS jev_id INTEGER,
    ADD COLUMN IF NOT EXISTS jev_no VARCHAR(50);
COMMENT ON COLUMN fims.statement_work_accomplishments.jev_id IS 'The Journal Entry Voucher its pro-forma was submitted as (fims.journal_entry_vouchers.jev_id)';

-- Contract cost breakdown (read-only on the form, taken from the RFA when the record is added):
--   original_contract_cost = the regional's original amount (sum of the project's budget line items' proposed amounts)
--   variation_order        = revised - original (positive = increase, negative = decrease; set when FMD / the CBA changes the amount)
--   contract_cost          = the REVISED contract cost (the amount approved on the RFA)
-- A record saved before these columns existed has NULL in both: it reads as original = contract_cost and variation order 0.
ALTER TABLE fims.statement_work_accomplishments
    ADD COLUMN IF NOT EXISTS original_contract_cost NUMERIC(18,2),
    ADD COLUMN IF NOT EXISTS variation_order NUMERIC(18,2);
COMMENT ON COLUMN fims.statement_work_accomplishments.original_contract_cost IS 'Original contract cost: the regional''s original amount (budget line items'' proposed amounts)';
COMMENT ON COLUMN fims.statement_work_accomplishments.variation_order IS 'Variation order: revised contract cost - original contract cost (+ increase / - decrease)';

-- Contract: typed by the user on the form (replaces the Project Cost input, which is no longer written; project_cost stays as an unused column).
ALTER TABLE fims.statement_work_accomplishments ADD COLUMN IF NOT EXISTS contract VARCHAR(255);
COMMENT ON COLUMN fims.statement_work_accomplishments.contract IS 'Contract: typed by the user';
