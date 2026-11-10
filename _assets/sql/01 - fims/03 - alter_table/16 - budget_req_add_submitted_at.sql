-- ============================================================
-- ALTER: fims.budget_req — add submitted_at
-- The "Date Submitted" column of the Financial Management Department's Budget Requirements list.
-- BudgetReqService::submitForReview() stamps it with NOW() when a Draft request is submitted for
-- review (it is the date of the LATEST submission). It stays NULL until then — and for requests
-- that were submitted before this column existed, whose real date was never recorded and is not
-- guessed at here — so the list shows "—" for those.
-- Idempotent: safe to run any number of times.
-- ============================================================
ALTER TABLE fims.budget_req
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMPTZ;

COMMENT ON COLUMN fims.budget_req.submitted_at IS
    'When the request was last submitted for review (Draft -> For Review). NULL = never submitted, or submitted before this column existed.';
