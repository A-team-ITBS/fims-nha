-- Subsidiary ledgers (SL) of the GL accounts that are broken down further. In this project an SL is a CHILD ROW of its GL account in
-- fims.chart_of_accounts (parent_account_id) — fims.sl_masters / sl_details were dropped (03 - alter_table/37). NGAS 5-part code:
-- SL code = GL code + "-" + the SL segment, e.g. 5-02-03-010-01 "Office Supplies" under 5-02-03-010 "Office Supplies Expenses".
-- The IAR module's Account Code picker offers an SL INSTEAD of its GL account (a GL account with postable SLs is not offered itself).
-- Taken from the accounting project's sl_masters seed (06 - sl_code_data.sql, book MAIN-OFFICE only: the CISFA / NGC rows are the
-- same SLs of other books, and the chart of accounts has no book). Only the SLs whose GL account exists are added; account_id is
-- 'SL' + a 4-digit number (account_id is VARCHAR(10)); a code that already exists is only refreshed. Idempotent.
INSERT INTO fims.chart_of_accounts (account_id, account_code, account_title, account_type, parent_account_id, posting_allowed, status)
SELECT 'SL' || LPAD(v.n::text, 4, '0'), p.account_code || '-' || v.sl, v.sl_name, p.account_type, p.account_id, TRUE, 'Active'
FROM (VALUES
    -- expense side of the supplies-issuance JEV (same breakdown as the inventory side below)
    (1,  '5-02-03-010', '01', 'Office Supplies'),
    (2,  '5-02-03-010', '02', 'Electrical Supplies'),
    (3,  '5-02-03-010', '03', 'Janitorial Supplies'),

    -- Office Supplies Inventory
    (4,  '1-04-04-010', '01', 'Office Supplies'),
    (5,  '1-04-04-010', '02', 'Electrical Supplies'),
    (6,  '1-04-04-010', '03', 'Janitorial Supplies'),

    -- Merchandise
    (7,  '1-04-01-010', '01', 'Completed Development for Sale - Land'),
    (8,  '1-04-01-010', '02', 'Completed Development for Sale - Building'),

    -- Construction in Progress - types of building
    (9,  '1-04-03-020', '01', 'Sites and Services'),
    (10, '1-04-03-020', '02', 'Slum Upgrading'),
    (11, '1-04-03-020', '03', 'Housing Units'),
    (12, '1-04-03-020', '04', 'Resettlement Projects'),
    (13, '1-04-03-020', '05', 'Community Facilities'),

    (14, '1-04-02-090', '01', 'Land'),
    (15, '1-04-02-090', '02', 'Building / Community Facilities'),

    (16, '1-06-01-010', '02', 'Housing and Community Development Administrative Site'),
    (17, '1-06-01-010', '03', 'Properties Held for Rent'),
    (18, '1-06-01-010', '04', 'Land Acquisition'),

    -- Fixed assets
    (19, '5-05-01-040', '01', 'Buildings'),
    (20, '5-05-01-040', '02', 'Other Structures'),
    (21, '5-05-01-090', '02', 'Leased Asset Improvements'),

    -- Depreciation - Machinery and Equipment (one GL shared by several PPE categories, told apart by the SL)
    (22, '5-05-01-050', '02', 'Office Equipment'),
    (23, '5-05-01-050', '03', 'Information and Communications Technology Equipment'),
    (24, '5-05-01-050', '07', 'Communication Equipment'),
    (25, '5-05-01-050', '08', 'Construction and Heavy Equipment'),
    (26, '5-05-01-050', '11', 'Medical Equipment'),
    (27, '5-05-01-050', '13', 'Sports Equipment'),
    (28, '5-05-01-050', '99', 'Other Machinery and Equipment'),

    -- Depreciation - Transportation Equipment
    (29, '5-05-01-060', '01', 'Motor Vehicles'),

    -- Disposal
    (30, '3-01-01-010', '02', 'Prior Years'' Charges'),
    (31, '3-01-01-010', '03', 'Prior Years'' Credits')
) AS v(n, gl, sl, sl_name)
JOIN fims.chart_of_accounts p ON p.account_code = v.gl
ON CONFLICT (account_code) DO UPDATE
    SET account_title = EXCLUDED.account_title, parent_account_id = EXCLUDED.parent_account_id,
        posting_allowed = TRUE, status = 'Active';
