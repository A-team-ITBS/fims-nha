-- A deduction type's account_code (fims.deduction_types.account_code) is the DEFAULT posting
-- account the Accounting Entries grid auto-fills from — nullable, because a type like "OTHER" has
-- no single right account ("08 - deduction_types_other_recoupment_penalty_vat.sql"). Previously, a
-- deduction line under a type with no default silently got no Accounting Entries line at all — the
-- preparer wasn't told why, and the resulting Journal Entry Voucher could end up unbalanced.
--
-- This column is the per-line override DisbursementVoucherService::prepareDeductions() now
-- requires whenever the chosen deduction_type has no default account_code: the preparer must pick
-- one explicitly. NULL when the type already has its own default (that default always wins; see
-- fetchDeductions()'s COALESCE). Not FK-constrained to chart_of_accounts — validated in PHP
-- instead, same convention fims.journal_entry_voucher_lines.account_code already uses.
ALTER TABLE fims.disbursement_voucher_deductions
    ADD COLUMN IF NOT EXISTS account_code VARCHAR(20);

COMMENT ON COLUMN fims.disbursement_voucher_deductions.account_code IS 'Per-line override of the posting account, required when deduction_types.account_code is NULL for this line''s type (e.g. "OTHER"); ignored otherwise since the type''s own default always wins.';
