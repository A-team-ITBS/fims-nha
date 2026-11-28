-- ============================================================
-- ALTER: fims.budget_line_items
-- Adds source_of_fund — the line item's own Source of Fund, a fixed short list
-- (BEGINNING BALANCE / NEW FUNDS / ACCOUNTS PAYABLE) picked on the Budget Request form
-- (form/budget-req-form.php). Plain text, not FK'd to a lookup table — same
-- convention this codebase already uses for fund-source-like fields with no
-- admin-managed list (fims.disbursement_vouchers.fund_source,
-- fims.bank_accounts.fund_source). Required on the form (see
-- BudgetReqService::validateLineItem()), but nullable here so existing rows
-- aren't broken by this migration.
-- ============================================================

ALTER TABLE fims.budget_line_items
    ADD COLUMN IF NOT EXISTS source_of_fund VARCHAR(50);

COMMENT ON COLUMN fims.budget_line_items.source_of_fund IS 'Source of Fund for this line item — fixed list (BEGINNING BALANCE / NEW FUNDS / ACCOUNTS PAYABLE), plain text, not a lookup table.';
