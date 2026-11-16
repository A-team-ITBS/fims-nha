-- Fund Transfer Letter of Advice (FTLA) — standalone port of REGIONAL's
-- treasury.fund_transfers, trimmed to what exists in the `fims` schema.
-- Not ported on purpose: the generic workflow engine (workflow.workflow_steps /
-- workflow_statuses / workflow_instances), department/role-based actor
-- gating, and the from_bank_account_id / from_fund_source_id pair (Treasury
-- fills those in later, when actually processing the transfer out of the
-- correct HQ account — see app/pages/form/ftla-form.php). Status is a plain
-- column driven by FtlaService (no workflow engine) — see its STATUSES const
-- for the linear DRAFT -> ... -> COMPLETED progression (+ CANCELLED).
CREATE TABLE IF NOT EXISTS fims.fund_transfers (
    fund_transfer_id            SERIAL PRIMARY KEY,
    ftla_number                 VARCHAR(50) NOT NULL UNIQUE,
    transfer_type               VARCHAR(40) NOT NULL,
    transfer_date               DATE NOT NULL,
    to_bank_account_id          INTEGER REFERENCES fims.bank_accounts(bank_account_id),
    transfer_amount             NUMERIC(18, 2) NOT NULL DEFAULT 0,
    is_temporary                BOOLEAN NOT NULL DEFAULT FALSE,
    repayment_due_date          DATE,
    purpose                     TEXT NOT NULL,
    request_reference           VARCHAR(150),
    dv_numbers                  TEXT DEFAULT '',
    -- [{document_type, name, url, uploaded_at}, ...] — free-text type/link rows
    -- typed directly into the create form (form/ftla-form.php's "Supporting
    -- Documents" table); no file bytes involved.
    supporting_documents        JSONB NOT NULL DEFAULT '[]',
    -- [{document_type, name, link, url, size, uploaded_at}, ...] — actual file
    -- uploads via POST /ftla/attachment: bytes go through the existing
    -- FileUploadServices (MinIO/S3), this stores the durable object key
    -- (link) plus a freshly signed url on every read.
    attachments                 JSONB NOT NULL DEFAULT '[]',
    -- {sig_verified, checked, approved, posted} — free-text bank-officer names
    bank_officers                JSONB NOT NULL DEFAULT '{}',
    -- {sig1_name, sig1_title, sig2_name, sig2_title, recipient_name,
    --  recipient_title, dear_salutation, laa_number, fiscal_year, rfa_number, rfa_date}
    signatories                 JSONB NOT NULL DEFAULT '{}',
    -- Soft references (no FK): budget_allocation rows picked in the LAA/RFA
    -- pickers (form/ftla-form.php) — same reuse-what-exists approach as the
    -- DV number picker, which reuses /voucher/list instead of a new table.
    laa_id                      INTEGER,
    rfa_id                      INTEGER,
    fund_source_id              INTEGER, -- only read back for the "Create JEV" prefill
    for_name                    VARCHAR(150),
    for_title                   VARCHAR(150),
    thru_name                   VARCHAR(150),
    thru_title                  VARCHAR(150),
    subject                     VARCHAR(255),
    signatory_name_memo         VARCHAR(150),
    signatory_position_memo     VARCHAR(150),
    prepared_by                 INTEGER REFERENCES uac.users(user_id),
    reviewed_by                 INTEGER REFERENCES uac.users(user_id),
    approved_by                 INTEGER REFERENCES uac.users(user_id),
    remarks                     TEXT,
    status                      VARCHAR(30) NOT NULL DEFAULT 'DRAFT',
    -- One timestamp column per status, set the moment status moves there —
    -- read directly by the status-tracking timeline (no workflow engine).
    rm_submitted_at             TIMESTAMPTZ,
    submitted_at                TIMESTAMPTZ,
    reviewed_at                 TIMESTAMPTZ,
    approved_date               TIMESTAMPTZ,
    transmitted_at              TIMESTAMPTZ,
    validated_date              TIMESTAMPTZ,
    accounting_submission_date  TIMESTAMPTZ,
    completed_at                TIMESTAMPTZ,
    created_by                  INTEGER REFERENCES uac.users(user_id),
    created_at                  TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by                  INTEGER REFERENCES uac.users(user_id),
    updated_at                  TIMESTAMPTZ,
    CONSTRAINT chk_fund_transfers_status CHECK (status IN (
        'DRAFT', 'FOR_RM_APPROVAL', 'FOR_REVIEW', 'FOR_APPROVAL', 'APPROVED',
        'TRANSMITTED', 'VALIDATED', 'SUBMITTED_TO_ACCOUNTING', 'COMPLETED', 'CANCELLED'
    )),
    CONSTRAINT chk_fund_transfers_amount CHECK (transfer_amount >= 0)
);

COMMENT ON TABLE  fims.fund_transfers IS 'Fund Transfer Letter of Advice (FTLA) headers — Treasury department';
COMMENT ON COLUMN fims.fund_transfers.status IS 'DRAFT -> FOR_RM_APPROVAL -> FOR_REVIEW -> FOR_APPROVAL -> APPROVED -> TRANSMITTED -> VALIDATED -> SUBMITTED_TO_ACCOUNTING -> COMPLETED; DRAFT can be CANCELLED';

CREATE INDEX IF NOT EXISTS idx_fund_transfers_status             ON fims.fund_transfers (status);
CREATE INDEX IF NOT EXISTS idx_fund_transfers_transfer_date      ON fims.fund_transfers (transfer_date);
CREATE INDEX IF NOT EXISTS idx_fund_transfers_to_bank_account_id ON fims.fund_transfers (to_bank_account_id);

-- Chronological, per-row audit trail of status changes — powers /ftla/{id}/history.
CREATE TABLE IF NOT EXISTS fims.ftla_status_log (
    ftla_status_log_id  SERIAL PRIMARY KEY,
    fund_transfer_id    INTEGER NOT NULL REFERENCES fims.fund_transfers(fund_transfer_id) ON DELETE CASCADE,
    status              VARCHAR(30) NOT NULL,
    remarks             TEXT,
    changed_by          INTEGER REFERENCES uac.users(user_id),
    changed_at          TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.ftla_status_log IS 'One row per FTLA status change — chronological audit trail for the status-tracking modal';

CREATE INDEX IF NOT EXISTS idx_ftla_status_log_fund_transfer_id ON fims.ftla_status_log (fund_transfer_id);
