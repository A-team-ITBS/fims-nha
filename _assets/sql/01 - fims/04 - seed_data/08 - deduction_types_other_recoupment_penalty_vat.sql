-- Adds the 4 remaining non-tax deduction types that "01 - table/20 - fims_deduction_types.sql"
-- left for later ("Only 'Retention' is seeded for now; more types can be added later without a
-- schema change"), so the voucher form's Deductions dropdown offers the full set:
--   OTHER        - any deduction not covered by a more specific type below
--   RECOUPMENT   - recovers a prior cash advance from the claimant's current payment
--   PENALTY_TAX  - a tax penalty/surcharge withheld and remitted to BIR
--   VAT          - Value Added Tax withheld from the gross amount
-- account_code is each type's default posting account when Accounting prepares the Journal Entry
-- Voucher (DisbursementVoucherService::persistJournalEntry() reads deduction_types.account_code as
-- a pre-fill, same as it already does for RETENTION's Guaranty/Security Deposits Payable) — nullable,
-- so OTHER is left without one (there's no single right account for "anything else").
-- Idempotent: ON CONFLICT (code), same pattern as the RETENTION row already there.
INSERT INTO fims.deduction_types (code, name, description, account_code) VALUES
    ('OTHER',       'Other',             'Other deduction not covered by a more specific type', NULL),
    ('RECOUPMENT',  'Advance Recoupment', 'Recovery of a prior cash advance from the claimant''s payment', '1-03-99-020'),
    ('PENALTY_TAX', 'Penalty Tax',       'Tax penalty/surcharge withheld and remitted to BIR', '2-02-01-010'),
    ('VAT',         'VAT',               'Value Added Tax withheld from the gross amount', '2-02-01-120')
ON CONFLICT (code) DO UPDATE SET
    name         = EXCLUDED.name,
    description  = EXCLUDED.description,
    account_code = EXCLUDED.account_code;
