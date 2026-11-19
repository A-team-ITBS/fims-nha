-- ============================================================================
-- Bank Reconciliations — one row per bank account per reconciliation month.
--
-- Backs the Bank Reconciliation module (app/pages/bank-reconciliation-page.php
-- / app/services/BankReconciliationService.php), Reports > Bank Reconciliation.
-- Mirrors the classic two-column reconciliation worksheet: Per Book vs Per
-- Bank, each with its own "Add"/"Less" adjustments, reconciled when both
-- adjusted balances match.
--
-- book_balance is a snapshot of fims.general_ledger's cumulative balance for
-- the bank account's tagged GL account (fims.bank_accounts.account_code —
-- same column GeneralLedgerService::postPaymentJev() credits; see
-- "03 - alter_table/31") as of as_of_date, taken at save time — same
-- "snapshot, not a live join" convention as fims.general_ledger's own
-- account_code/sl_code columns, so a reconciliation already saved doesn't
-- silently change if more JEVs post later for the same month. NOTE: when
-- several bank accounts share the same GL account_code (no dedicated SL
-- posting — see the "SL registry vs surrogate gap" project note), this
-- balance reflects ALL of them combined, not just this one row's account;
-- that is a pre-existing posting-granularity gap, not something this module
-- introduces.
--
-- bank_balance is always manual entry (there is no bank statement feed).
-- adjusted_book_balance / adjusted_bank_balance / status are computed by the
-- service on every save, never trusted from the client.
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.bank_reconciliations (
    reconciliation_id       SERIAL PRIMARY KEY,
    bank_account_id         INTEGER NOT NULL REFERENCES fims.bank_accounts(bank_account_id),
    period_year             SMALLINT NOT NULL,
    period_month            SMALLINT NOT NULL CHECK (period_month BETWEEN 1 AND 12),
    as_of_date              DATE NOT NULL, -- last day of (period_year, period_month)

    book_balance             NUMERIC(18,2) NOT NULL DEFAULT 0, -- snapshot from the GL, see above
    add_unrecorded_deposit   NUMERIC(18,2) NOT NULL DEFAULT 0,
    add_unreleased_check     NUMERIC(18,2) NOT NULL DEFAULT 0,
    add_credit_memo          NUMERIC(18,2) NOT NULL DEFAULT 0,
    add_interest_income      NUMERIC(18,2) NOT NULL DEFAULT 0,
    less_debit_memo          NUMERIC(18,2) NOT NULL DEFAULT 0,
    adjusted_book_balance    NUMERIC(18,2) NOT NULL DEFAULT 0,

    bank_balance             NUMERIC(18,2) NOT NULL DEFAULT 0, -- manual entry
    add_deposit_in_transit   NUMERIC(18,2) NOT NULL DEFAULT 0,
    less_outstanding_check   NUMERIC(18,2) NOT NULL DEFAULT 0,
    adjusted_bank_balance    NUMERIC(18,2) NOT NULL DEFAULT 0,

    status                   VARCHAR(20) NOT NULL DEFAULT 'UNRECONCILED', -- RECONCILED | UNRECONCILED
    remarks                  VARCHAR(500),

    prepared_by              INTEGER,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at               TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.bank_reconciliations IS 'One row per bank account per reconciliation month — Per Book vs Per Bank worksheet (see app/services/BankReconciliationService.php)';

-- One reconciliation per bank account per month.
CREATE UNIQUE INDEX IF NOT EXISTS uq_bank_reconciliations_account_period
    ON fims.bank_reconciliations (bank_account_id, period_year, period_month);

CREATE INDEX IF NOT EXISTS idx_bank_reconciliations_bank_account_id ON fims.bank_reconciliations (bank_account_id);
CREATE INDEX IF NOT EXISTS idx_bank_reconciliations_status          ON fims.bank_reconciliations (status);
