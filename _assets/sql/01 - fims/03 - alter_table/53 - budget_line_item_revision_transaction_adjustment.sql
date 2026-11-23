-- The ledger records each line's adjustment as it builds up, and the line's own `adjustment` is that total — both kept by the database itself.
--
-- fims.budget_line_item_revision_transaction gets an `adjustment` column: for every row, the line's adjustment AFTER that transaction — the
-- adjustments before it plus its own `amount`. So `amount` is what one approval changed and `adjustment` is the running total; the last row of a line
-- holds the line's current adjustment.
--
-- fims.budget_line_items.adjustment is that total, written straight into the table: every insert (update, delete) in the ledger re-works the
-- line's `adjustment` = SUM(amount) of its ledger rows. Nothing in the application writes it any more — BudgetRevisionService::approve() only inserts
-- the ledger row. Together with "54 - budget_line_items_revised_amount_generated.sql" the line's revised_amount follows by itself:
-- suggested_amount + adjustment.
--
-- Idempotent: the column is added once, the triggers are re-created, and the totals are re-worked from the ledger (a line with no ledger row is left alone).

ALTER TABLE fims.budget_line_item_revision_transaction
    ADD COLUMN IF NOT EXISTS adjustment NUMERIC(18,2) NOT NULL DEFAULT 0;

COMMENT ON COLUMN fims.budget_line_item_revision_transaction.adjustment IS 'The line''s adjustment right after this transaction: the total of its earlier transactions plus this one''s amount. Set by a trigger when the row is inserted; the line''s current adjustment (budget_line_items.adjustment) is the last row''s.';

-- the running totals of the rows that are already there, and the lines' totals
UPDATE fims.budget_line_item_revision_transaction t
SET adjustment = r.running
FROM (SELECT transaction_id,
             SUM(amount) OVER (PARTITION BY budget_line_item_id ORDER BY transaction_id) AS running
      FROM fims.budget_line_item_revision_transaction) r
WHERE r.transaction_id = t.transaction_id
  AND t.adjustment IS DISTINCT FROM r.running;

UPDATE fims.budget_line_items li
SET adjustment = s.total
FROM (SELECT budget_line_item_id, SUM(amount) AS total
      FROM fims.budget_line_item_revision_transaction
      GROUP BY budget_line_item_id) s
WHERE s.budget_line_item_id = li.budget_line_item_id
  AND li.adjustment IS DISTINCT FROM s.total;

-- BEFORE INSERT: this row's adjustment = the line's earlier total + its own amount. The line's row is locked first, so a second approval on the same line waits
-- for the first to commit and its total includes it.
CREATE OR REPLACE FUNCTION fims.fn_budget_line_item_revision_transaction_before_insert() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    PERFORM 1 FROM fims.budget_line_items WHERE budget_line_item_id = NEW.budget_line_item_id FOR UPDATE;
    NEW.adjustment := COALESCE((SELECT SUM(t.amount)
                                FROM fims.budget_line_item_revision_transaction t
                                WHERE t.budget_line_item_id = NEW.budget_line_item_id), 0) + NEW.amount;
    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_budget_line_item_revision_transaction_before_insert ON fims.budget_line_item_revision_transaction;
CREATE TRIGGER trg_budget_line_item_revision_transaction_before_insert
    BEFORE INSERT ON fims.budget_line_item_revision_transaction
    FOR EACH ROW EXECUTE FUNCTION fims.fn_budget_line_item_revision_transaction_before_insert();

-- AFTER INSERT / UPDATE / DELETE: the line's adjustment = the total of its ledger rows
CREATE OR REPLACE FUNCTION fims.fn_budget_line_item_revision_transaction_sync_adjustment() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP IN ('INSERT', 'UPDATE') THEN
        UPDATE fims.budget_line_items li
        SET adjustment = COALESCE((SELECT SUM(t.amount) FROM fims.budget_line_item_revision_transaction t WHERE t.budget_line_item_id = li.budget_line_item_id), 0)
        WHERE li.budget_line_item_id = NEW.budget_line_item_id;
    END IF;
    IF TG_OP = 'DELETE' OR (TG_OP = 'UPDATE' AND OLD.budget_line_item_id IS DISTINCT FROM NEW.budget_line_item_id) THEN
        UPDATE fims.budget_line_items li
        SET adjustment = COALESCE((SELECT SUM(t.amount) FROM fims.budget_line_item_revision_transaction t WHERE t.budget_line_item_id = li.budget_line_item_id), 0)
        WHERE li.budget_line_item_id = OLD.budget_line_item_id;
    END IF;
    RETURN NULL;
END;
$$;

DROP TRIGGER IF EXISTS trg_budget_line_item_revision_transaction_sync_adjustment ON fims.budget_line_item_revision_transaction;
CREATE TRIGGER trg_budget_line_item_revision_transaction_sync_adjustment
    AFTER INSERT OR DELETE OR UPDATE OF amount, budget_line_item_id ON fims.budget_line_item_revision_transaction
    FOR EACH ROW EXECUTE FUNCTION fims.fn_budget_line_item_revision_transaction_sync_adjustment();

COMMENT ON COLUMN fims.budget_line_items.adjustment IS 'The total of the line''s approved revisions / realignments (SUM of fims.budget_line_item_revision_transaction.amount), kept by a trigger on that table — do not write it. A decrease is negative.';
