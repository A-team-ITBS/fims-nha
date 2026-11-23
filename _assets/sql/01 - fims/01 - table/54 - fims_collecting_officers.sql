-- Collecting Officers — previously shared fims.accountable_officers with Accountable
-- Officers (discriminated by an officer_type column that went unused for a while, then
-- briefly used to filter one shared table). Split into its own table instead: a
-- Collecting Officer isn't an Accountable Officer in the Fidelity Bond sense (no
-- bond_listings/confirmation_letter_officers/office_order_requests coverage — those stay
-- exclusive to fims.accountable_officers), so the extra columns that table carries for
-- that purpose (officer_type, requires_office_order, accountability_amount, oo_file_url)
-- don't apply here. Only the identity + Region/Office + GL/SL tagging columns both kinds
-- of officer actually share are mirrored.
CREATE TABLE IF NOT EXISTS fims.collecting_officers (
    collecting_officer_id SERIAL PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    middle_name VARCHAR(50),
    last_name VARCHAR(50) NOT NULL,
    suffix VARCHAR(10),
    rc_id VARCHAR(10) REFERENCES fims.responsibility_code(rc_id),
    coa_account_id VARCHAR(10) REFERENCES fims.chart_of_accounts(account_id),
    sl_code VARCHAR(100),
    status VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
    position_title VARCHAR(150),
    created_by INTEGER,
    created_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_collecting_officers_rc_id ON fims.collecting_officers(rc_id);
CREATE INDEX IF NOT EXISTS idx_collecting_officers_coa_account_id ON fims.collecting_officers(coa_account_id);
CREATE INDEX IF NOT EXISTS idx_collecting_officers_sl_code ON fims.collecting_officers(sl_code);
CREATE INDEX IF NOT EXISTS idx_collecting_officers_status ON fims.collecting_officers(status);
