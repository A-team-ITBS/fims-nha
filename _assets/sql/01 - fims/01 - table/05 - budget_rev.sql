CREATE TABLE fims.budget_revision (
  id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  request_type      VARCHAR(20)    NOT NULL DEFAULT 'REVISION',   -- REVISION: no To line, the From line's amount changes | REALIGNMENT: a To line, funds move to it
  from_amount       NUMERIC(18,2)  NOT NULL,
  to_amount         NUMERIC(18,2)  NOT NULL,
  reason            TEXT           NULL,
  user_id           INTEGER        NOT NULL,
  status_id         INTEGER        NOT NULL,
  created_at        TIMESTAMP      NOT NULL DEFAULT NOW(),
  update_at         TIMESTAMP      NOT NULL DEFAULT NOW(),
  deleted_at        TIMESTAMP      NULL,
  CONSTRAINT chk_budget_revision_request_type CHECK (request_type IN ('REVISION', 'REALIGNMENT'))
);

-- The budget lines a request revises (from_budget_line_item_id / to_budget_line_item_id) are added by
-- "03 - alter_table/23". The earlier from_budget_req_id / to_budget_req_id columns (a budget request's id, which no longer exists) are
-- no longer created: "03 - alter_table/42" drops them from databases that still have them and adds request_type.
