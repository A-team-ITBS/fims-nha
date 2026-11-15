-- Adds balance_applied to fims.budget_line_item_earmark: TRUE when the final approval of the CBA / CFA that owns the row
-- also took its amount off the line item itself (BudgetLineItemService::createForApproval()):
--     budget_line_items.suggested_amount - R, account_payable + R, obligation + R      (R = the row's original_amount)
-- Deleting that Approved CBA / CFA gives exactly that back (releaseForAllocation()), and an RFA / LAA that draws against the row
-- adds nothing to obligation (convertEarmarkForRfaApproval()) because the CBA already did.
--
-- FALSE (the default) for every row that already exists: those CBAs / CFAs were approved before this rule, their line items
-- were never charged, so deleting one gives nothing back and an RFA / LAA drawing on it still adds obligation, as it always did.
-- The flag is what keeps those old rows from being "reversed" for money that was never taken.
--
-- NOTE for data that is already Approved: the line items' suggested_amount / account_payable / obligation of those older
-- CBAs / CFAs are NOT adjusted by this script, and the Available Balance (suggested_amount) no longer subtracts their
-- still-active earmarks. If that data matters, apply the deduction to it by hand once.
ALTER TABLE fims.budget_line_item_earmark
    ADD COLUMN IF NOT EXISTS balance_applied BOOLEAN NOT NULL DEFAULT FALSE;

COMMENT ON COLUMN fims.budget_line_item_earmark.balance_applied IS 'TRUE = the approval of this CBA/CFA also deducted its amount from budget_line_items.suggested_amount and added it to account_payable and obligation (reversed when it is deleted; its RFA/LAA draws then add nothing to obligation). FALSE = approved before that rule, line item untouched.';
