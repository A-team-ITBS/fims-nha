-- fims.accountable_officers never got a created_by column — neither the
-- base table ("42 - fims_accountable_officers.sql") nor the Fidelity Bond
-- extension ("42 - fims_fidelity_bond.sql", which added position_title/
-- officer_type/requires_office_order/accountability_amount/oo_file_url)
-- added it, but FidelityBondService::addOfficer() has always written it
-- (same convention as fims.bond_listings.created_by in that same file).

ALTER TABLE fims.accountable_officers ADD COLUMN IF NOT EXISTS created_by INTEGER;
