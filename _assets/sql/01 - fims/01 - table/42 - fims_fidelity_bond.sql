-- Fidelity Bond Management — see files/07_Fidelity_Bond_Management_Screenshot_Guide.docx
-- for the full workflow this schema backs. Requires "42 - fims_accountable_
-- officers.sql" to have already run — fims.accountable_officers is the
-- shared officer-identity table (also used by the GL Accountable Officer
-- page); this file only ADDs the columns Fidelity Bond itself needs
-- (officer_type/requires_office_order/accountability_amount/oo_file_url)
-- onto that same table rather than owning a table of its own.
--   bond_listings          — one row per bond "listing". A listing stays in
--                            this same table across its whole life (EXPIRING
--                            -> EXPIRED -> APPLIED/PENDING_BOND -> ACTIVE ->
--                            CLOSED) — "Bond History" is just this table
--                            filtered to CLOSED, not a separate table, so
--                            risk-number uniqueness can be checked with one
--                            query across "current listing, Bond History, or
--                            any posted CL" (a posted CL's officer rows
--                            become new bond_listings rows with that same
--                            risk number, see confirmation_letter_officers).
--   office_order_requests  — tracks Main Office's issuance of Office Orders
--                            for SDOs (and ad-hoc "needs OO" cases).
--   confirmation_letters / confirmation_letter_officers — BTr's Confirmation
--                            Letter (CL) encode-then-post workflow: a CL is
--                            DRAFT while officers are being added to it, then
--                            POSTED once — at which point every officer's old
--                            listing (if any) is closed (CLOSED, closed_by_cl_id
--                            set) and a new ACTIVE listing is created from the
--                            encoded risk no./approved amount, and status
--                            becomes immutable from then on (strictly
--                            read-only once posted).

ALTER TABLE fims.accountable_officers
    ADD COLUMN IF NOT EXISTS position_title        VARCHAR(150),
    ADD COLUMN IF NOT EXISTS officer_type          VARCHAR(30)   NOT NULL DEFAULT 'COLLECTING_OFFICER',
    ADD COLUMN IF NOT EXISTS requires_office_order BOOLEAN       NOT NULL DEFAULT FALSE,
    ADD COLUMN IF NOT EXISTS accountability_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
    -- Office Order scan for this officer's initial designation (Bond
    -- Application's Section B) — a plain record kept against the officer,
    -- not an issuance workflow; this module has no Office Order section.
    ADD COLUMN IF NOT EXISTS oo_file_url           TEXT;

CREATE TABLE IF NOT EXISTS fims.confirmation_letters (
    cl_id                   SERIAL PRIMARY KEY,
    cl_number               VARCHAR(50)  NOT NULL UNIQUE,
    cl_date                 DATE         NOT NULL,
    rc_id                   VARCHAR(10)  REFERENCES fims.responsibility_code(rc_id),
    new_effectivity_date    DATE         NOT NULL,
    new_expiration_date     DATE         NOT NULL,
    scanned_file_url        TEXT,
    status                  VARCHAR(20)  NOT NULL DEFAULT 'DRAFT',
    created_by              INTEGER,
    created_at              TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_by               INTEGER,
    posted_at               TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fims.bond_listings (
    listing_id                 SERIAL PRIMARY KEY,
    officer_id                 INTEGER NOT NULL REFERENCES fims.accountable_officers(accountable_officer_id),
    rc_id                       VARCHAR(10) REFERENCES fims.responsibility_code(rc_id),
    status                       VARCHAR(20) NOT NULL DEFAULT 'EXPIRING',
    bond_no                      VARCHAR(50),
    risk_no                      VARCHAR(50),
    approved_bond_amount         NUMERIC(18,2),
    effectivity_date             DATE,
    expiration_date              DATE,
    date_filed_btr               DATE,
    premium_payment_reference    VARCHAR(100),
    application_file_url         TEXT,
    proof_of_payment_file_url    TEXT,
    note                         TEXT,
    last_cl_id                   INTEGER REFERENCES fims.confirmation_letters(cl_id),
    closed_by_cl_id              INTEGER REFERENCES fims.confirmation_letters(cl_id),
    replaces_listing_id          INTEGER REFERENCES fims.bond_listings(listing_id),
    created_by                   INTEGER,
    created_at                   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fims.office_order_requests (
    request_id              SERIAL PRIMARY KEY,
    officer_id               INTEGER NOT NULL REFERENCES fims.accountable_officers(accountable_officer_id),
    listing_id                INTEGER REFERENCES fims.bond_listings(listing_id),
    rc_id                      VARCHAR(10) REFERENCES fims.responsibility_code(rc_id),
    reason                     VARCHAR(255),
    oo_no                      VARCHAR(50),
    date_issued                DATE,
    accountability_amount      NUMERIC(18,2),
    designation_year           INTEGER,
    oo_file_url                TEXT,
    status                     VARCHAR(20) NOT NULL DEFAULT 'REQUESTED',
    requested_by               INTEGER,
    requested_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    issued_by                  INTEGER,
    issued_at                  TIMESTAMP
);

CREATE TABLE IF NOT EXISTS fims.confirmation_letter_officers (
    cl_officer_id            SERIAL PRIMARY KEY,
    cl_id                     INTEGER NOT NULL REFERENCES fims.confirmation_letters(cl_id) ON DELETE CASCADE,
    officer_id                INTEGER NOT NULL REFERENCES fims.accountable_officers(accountable_officer_id),
    old_listing_id             INTEGER REFERENCES fims.bond_listings(listing_id),
    new_risk_no                 VARCHAR(50),
    approved_bond_amount        NUMERIC(18,2),
    new_listing_id               INTEGER REFERENCES fims.bond_listings(listing_id),
    UNIQUE (cl_id, officer_id)
);

CREATE INDEX IF NOT EXISTS idx_bond_listings_officer ON fims.bond_listings(officer_id);
CREATE INDEX IF NOT EXISTS idx_bond_listings_status ON fims.bond_listings(status);
CREATE INDEX IF NOT EXISTS idx_bond_listings_risk_no ON fims.bond_listings(risk_no);
CREATE INDEX IF NOT EXISTS idx_oo_requests_status ON fims.office_order_requests(status);
CREATE INDEX IF NOT EXISTS idx_cl_officers_cl ON fims.confirmation_letter_officers(cl_id);
