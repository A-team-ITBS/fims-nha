-- Moves what was approved BEFORE fims.budget_line_item_revision_transaction existed into it, so every Revision Amount comes from the ledger.
--
-- Until now an approval only changed budget_line_items.adjustment (the running net of the approved revisions). The ledger is now where the Revision
-- Amount lives (BudgetLineBalanceSql::revisionExpr()), so for each line that has an adjustment and no ledger row yet, that adjustment is written as ONE
-- LEGACY ledger row — no budget_revision_id, since the individual requests were never recorded:
--     previous_amount = suggested_amount                                                 (its revised amount without the legacy net)
--     amount          = adjustment
--     new_amount      = suggested_amount + adjustment                                    (its revised amount)
--
-- suggested_amount is deliberately NOT touched here. Under the stable-suggested_amount model (BudgetLineBalanceSql; migration
-- "51 - budget_line_items_stable_suggested_amount.sql", which runs first) nothing ever moves it again and that migration is what restores it for
-- lines an old approval had moved — doing it here as well would take the revision out twice.
-- Idempotent: a line it has written a row for is skipped the second time. ("53 - budget_line_item_revision_transaction_adjustment.sql" then gives each row its running
-- adjustment and keeps the lines' `adjustment` equal to the ledger's total.)
INSERT INTO fims.budget_line_item_revision_transaction
    (budget_revision_id, budget_line_item_id, transaction_type, previous_amount, amount, new_amount, created_by)
SELECT NULL,
       li.budget_line_item_id,
       'LEGACY',
       li.suggested_amount,
       li.adjustment,
       li.suggested_amount + li.adjustment,
       NULL
FROM fims.budget_line_items li
WHERE li.adjustment <> 0
  AND NOT EXISTS (SELECT 1 FROM fims.budget_line_item_revision_transaction t WHERE t.budget_line_item_id = li.budget_line_item_id);
