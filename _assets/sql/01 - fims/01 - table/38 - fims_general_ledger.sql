-- ============================================================================
-- General Ledger — one row per POSTED Journal Entry Voucher line.
--
-- Written once, by GeneralLedgerService::postJev(), when a Disbursement
-- Voucher's Journal Entry is approved/posted (today: the FOR_ACCOUNTING ->
-- FOR_TREASURY transition in DisbursementVoucherService::changeStatus(), the
-- same transition that already requires a balanced JEV — see
-- "01 - table/18 - fims_journal_entry_vouchers.sql"). Not written directly by
-- any route; nothing else should INSERT into this table.
--
-- jev_line_id is unique AMONG ORIGINAL POSTINGS (partial unique index, below)
-- so one JEV line can only ever be posted once — re-running the posting step
-- against an already-posted JEV is a no-op, not a duplicate (post-once, same
-- "guard on the row you read" idea DisbursementVoucherService::changeStatus()
-- already uses for status_id). A reversing row (reversed_gl_id IS NOT NULL,
-- see GeneralLedgerService::reverseJev()) re-states the SAME jev_line_id with
-- debit/credit swapped, so the uniqueness can't apply there too — the partial
-- index only covers reversed_gl_id IS NULL rows.
--
-- account_code / sl_code / sl_detail_id / rc_id are copied straight from the
-- journal_entry_voucher_lines row at posting time, same "snapshot, not a live
-- join" convention as fims.purchase_orders' vendor fields and
-- fims.disbursement_vouchers' budget/obligation amounts — a later rename in
-- the chart of accounts or an SL master doesn't rewrite a row already posted.
--
-- fims.v_journals / fims.v_trial_balance (see "05 - views/") are the reports
-- built on top of this table — deliberately not materialized here, so they
-- can never drift out of sync with it.
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.general_ledger (
    gl_id         SERIAL PRIMARY KEY,
    jev_id        INTEGER NOT NULL REFERENCES fims.journal_entry_vouchers(jev_id),
    jev_line_id   INTEGER NOT NULL REFERENCES fims.journal_entry_voucher_lines(jevl_id),
    account_code  VARCHAR(30) NOT NULL REFERENCES fims.chart_of_accounts(account_code),
    sl_code       VARCHAR(30) REFERENCES fims.sl_masters(sl_code),
    sl_detail_id  INTEGER REFERENCES fims.sl_details(sl_detail_id),
    rc_id         VARCHAR(10) REFERENCES fims.responsibility_code(rc_id),
    fiscal_yr     SMALLINT, -- copied from journal_entry_vouchers.fiscal_yr, which is itself
                            -- nullable/unconstrained (see "01 - table/18"); no FK here either,
                            -- same reasoning
    posting_date  DATE NOT NULL DEFAULT CURRENT_DATE,
    debit         NUMERIC(18,2) NOT NULL DEFAULT 0,
    credit        NUMERIC(18,2) NOT NULL DEFAULT 0,
    reference_no  VARCHAR(30),
    description   VARCHAR(255),
    reversed_gl_id INTEGER REFERENCES fims.general_ledger(gl_id),
    posted_by     INTEGER,
    posted_at     TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_general_ledger_one_sided CHECK ((debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0))
);

COMMENT ON TABLE fims.general_ledger IS 'One row per posted Journal Entry Voucher line — the GL. Written once by GeneralLedgerService::postJev(); never updated, only reversed (see reversed_gl_id).';
COMMENT ON COLUMN fims.general_ledger.reversed_gl_id IS 'When this row is itself a reversing entry (CANCELLED after RELEASED), the original fims.general_ledger row it reverses. NULL for an ordinary posting.';

-- Post-once: only an ORIGINAL posting (reversed_gl_id IS NULL) must be unique per jev_line_id.
CREATE UNIQUE INDEX IF NOT EXISTS uq_general_ledger_jev_line_id_original
    ON fims.general_ledger (jev_line_id) WHERE reversed_gl_id IS NULL;

CREATE INDEX IF NOT EXISTS idx_general_ledger_jev_id       ON fims.general_ledger (jev_id);
CREATE INDEX IF NOT EXISTS idx_general_ledger_account_code ON fims.general_ledger (account_code);
CREATE INDEX IF NOT EXISTS idx_general_ledger_fiscal_yr     ON fims.general_ledger (fiscal_yr);
CREATE INDEX IF NOT EXISTS idx_general_ledger_sl_code       ON fims.general_ledger (sl_code);
CREATE INDEX IF NOT EXISTS idx_general_ledger_posting_date  ON fims.general_ledger (posting_date);
