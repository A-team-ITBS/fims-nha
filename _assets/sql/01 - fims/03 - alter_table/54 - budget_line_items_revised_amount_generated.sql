-- fims.budget_line_items.revised_amount is back, as a column the database works out by itself: suggested_amount + adjustment.
--
-- suggested_amount is the approved amount (Budget Processing's figure, never moved by a revision) and adjustment is the signed total of the line's approved
-- revisions / realignments (a decrease or a realignment out is negative; see "53 - budget_line_item_revision_transaction_adjustment.sql"), so
--     revised_amount = suggested_amount + adjustment
-- is always up to date: nothing writes it and it can never drift. (It had been a plain, app-maintained column — "42 - budget_line_items_add_revised_amount.sql" — and
-- was dropped when the ledger replaced it; a database that still has that plain column gets it replaced here.)
--
-- Idempotent: a column that is already generated is left alone.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
               WHERE table_schema = 'fims' AND table_name = 'budget_line_items' AND column_name = 'revised_amount' AND is_generated = 'NEVER') THEN
        ALTER TABLE fims.budget_line_items DROP COLUMN revised_amount;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns
                   WHERE table_schema = 'fims' AND table_name = 'budget_line_items' AND column_name = 'revised_amount') THEN
        ALTER TABLE fims.budget_line_items
            ADD COLUMN revised_amount NUMERIC(18,2) GENERATED ALWAYS AS (suggested_amount + adjustment) STORED;
    END IF;
END $$;

COMMENT ON COLUMN fims.budget_line_items.revised_amount IS 'Generated: suggested_amount + adjustment — the approved amount net of the line''s approved revisions / realignments. Read-only; the remaining balance is this less what CBAs / CFAs hold and RFAs / LAAs have drawn.';
