-- Accountable officers (custodians of cash/property per region) referenced by
-- the Accountable Officer page. Name columns mirror uac.users' convention
-- (first_name/middle_name/last_name/suffix). rc_id scopes the officer to a
-- region the same way fims.chart_of_accounts.region_code references a
-- Responsibility Center, and coa_account_id mirrors how fims.vendors links
-- to a GL account.
DROP TABLE IF EXISTS fims.accountable_officers;

CREATE TABLE IF NOT EXISTS fims.accountable_officers (
    accountable_officer_id SERIAL PRIMARY KEY,
    first_name              VARCHAR(50)  NOT NULL,
    middle_name             VARCHAR(50),
    last_name               VARCHAR(50)  NOT NULL,
    suffix                  VARCHAR(10),
    rc_id                   VARCHAR(10)  REFERENCES fims.responsibility_code(rc_id),
    coa_account_id          VARCHAR(10)  REFERENCES fims.chart_of_accounts(account_id),
    status                  VARCHAR(20)  DEFAULT 'ACTIVE',
    created_at              TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMPTZ  DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.accountable_officers IS 'Officers accountable for cash/property per region, with their assigned GL account';
COMMENT ON COLUMN fims.accountable_officers.suffix IS 'Name suffix: Jr., Sr., II, III, IV, V';

CREATE INDEX IF NOT EXISTS idx_accountable_officers_rc_id         ON fims.accountable_officers (rc_id);
CREATE INDEX IF NOT EXISTS idx_accountable_officers_coa_account_id ON fims.accountable_officers (coa_account_id);
CREATE INDEX IF NOT EXISTS idx_accountable_officers_status        ON fims.accountable_officers (status);
