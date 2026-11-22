-- ============================================================================
-- RFA + DISBURSEMENT VOUCHER SAMPLE DATA (FY 2026, Region 3)
-- ----------------------------------------------------------------------------
-- A complete, internally consistent chain, written the same way the app writes it:
--
--   fims.budget_req (Approved)  ->  budget_req_program / budget_req_project
--     -> fims.budget_line_items (4 COB lines, MOOE)
--       -> 2 Approved CBAs  (fims.budget_allocation + budget_allocation_project,
--                            'active' earmarks, line account_payable bumped)
--         -> 5 RFAs drawn from those CBAs (4 Approved + 1 For Review)
--              Approved RFA = parent earmark reduced + own 'converted' earmark
--                             (source_earmark_id) + line obligation increased
--           -> 4 Disbursement Vouchers linked back through budget_allocation.dv_id
--
--   RFA (allocation no.)  Parent  COB / account               Amount      DV (status)
--   2642-57-01            CBA-01  5-02-03-010 Office Supplies  85,000.00   FOR_ACCOUNTING
--   2642-57-02            CBA-01  5-02-13-060 Repairs-Transpo  48,500.00   FOR_FMD_REVIEW
--   2642-57-03            CBA-02  5-02-12-030 Security Svcs  300,000.00   FOR_ACCOUNTING (partial 150,000)
--   2642-57-04            CBA-02  5-02-02-010 Training         60,000.00   DRAFT
--   (For Review, no no.)  CBA-01  5-02-03-010 Office Supplies  40,000.00   - (not yet approved)
--
-- DVs stop at FOR_ACCOUNTING on purpose: moving on to FOR_TREASURY needs a Journal Entry
-- Voucher (+ ATC lines), which Accounting prepares in the app.
--
-- Lives in "04 - seed_data" (not "02 - sample_data") because it needs columns added by
-- "03 - alter_table" (budget_allocation.dv_id, budget_allocation_project.parent_allocation_id,
-- disbursement_vouchers.status_id, ...). Idempotent: skipped when the sample budget request
-- already exists, or when Region 3 already has a FY 2026 budget request (UNIQUE fiscal_yr, rc_id).
-- ============================================================================

-- =============================================
-- SAMPLE VENDORS
-- =============================================
INSERT INTO fims.vendors (vendor_code, vendor_name, vendor_type, tin, address, contact_person, contact_number, is_vat_registered, status)
SELECT v.vendor_code, v.vendor_name, v.vendor_type, v.tin, v.address, v.contact_person, v.contact_number, v.is_vat_registered, 'ACTIVE'
FROM (VALUES
    ('SAMPLE-VEND-01', 'Pampanga Office Supplies Trading', 'SUPPLIER',         '201-334-556-00000', 'MacArthur Hwy, Brgy. Dolores, San Fernando City, Pampanga', 'Liza Manalo',    '0917-555-0101', TRUE),
    ('SAMPLE-VEND-02', 'Central Luzon Motor Works Inc.',    'SERVICE_PROVIDER', '202-445-667-00000', 'Olongapo-Gapan Rd, Brgy. Sindalan, San Fernando City, Pampanga', 'Ramon Dizon', '0918-555-0202', TRUE),
    ('SAMPLE-VEND-03', 'Sentinel Guard Security Agency',    'SERVICE_PROVIDER', '203-556-778-00000', 'Jose Abad Santos Ave, Brgy. Lourdes, Angeles City, Pampanga', 'Edgar Villanueva', '0919-555-0303', TRUE),
    ('SAMPLE-VEND-04', 'Luzon Public Finance Institute',    'CONSULTANT',       '204-667-889-00000', 'Clark Freeport Zone, Mabalacat City, Pampanga', 'Dr. Ana Reyes', '0920-555-0404', FALSE)
) AS v(vendor_code, vendor_name, vendor_type, tin, address, contact_person, contact_number, is_vat_registered)
WHERE NOT EXISTS (SELECT 1 FROM fims.vendors x WHERE x.vendor_code = v.vendor_code);

-- =============================================
-- BUDGET -> CBA -> RFA -> DV CHAIN
-- =============================================
DO $$
DECLARE
    c_ref     CONSTANT VARCHAR := 'BR-2026-R03-SAMPLE';
    c_fy      CONSTANT INTEGER := 2026;
    c_rc      CONSTANT VARCHAR := 'R03';
    c_proj1   CONSTANT VARCHAR := '2026-R03-101-001-0001';
    c_proj2   CONSTANT VARCHAR := '2026-R03-101-002-0001';
    c_fund    CONSTANT INTEGER := 3;   -- fims.type_of_fund 'CORPORATE RECEIPT'
    c_comp    CONSTANT INTEGER := 3;   -- fims.component_type 'COE'

    st_br_approved  INTEGER;
    st_ba_approved  INTEGER;
    st_ba_review    INTEGER;
    st_dv_draft     INTEGER;
    st_dv_fmd       INTEGER;
    st_dv_acctg     INTEGER;

    u_region  INTEGER;   -- r3.finance       (requesting / originating office)
    u_fmd     INTEGER;   -- fmd_fundsourcing (FMD obligation check)

    v_supplies INTEGER; v_motor INTEGER; v_security INTEGER; v_training INTEGER;

    prog1 INTEGER; prog2 INTEGER; brp1 INTEGER; brp2 INTEGER;
    li1 INTEGER; li2 INTEGER; li3 INTEGER; li4 INTEGER;     -- COB lines
    cba1 INTEGER; cba2 INTEGER; cba1_blk INTEGER; cba2_blk INTEGER;
    em_c1_l1 INTEGER; em_c1_l2 INTEGER; em_c2_l3 INTEGER; em_c2_l4 INTEGER;
    rfa1 INTEGER; rfa2 INTEGER; rfa3 INTEGER; rfa4 INTEGER; rfa5 INTEGER;
    blk INTEGER;
    dv1 INTEGER; dv2 INTEGER; dv3 INTEGER; dv4 INTEGER;
    dv_seq INTEGER;
    rc_name VARCHAR;
BEGIN
    IF EXISTS (SELECT 1 FROM fims.budget_req WHERE budget_reference_number = c_ref) THEN
        RAISE NOTICE 'RFA/DV sample: % already exists, skipped.', c_ref;
        RETURN;
    END IF;
    IF EXISTS (SELECT 1 FROM fims.budget_req WHERE fiscal_yr = c_fy AND rc_id = c_rc) THEN
        RAISE NOTICE 'RFA/DV sample: % already has a FY % budget request, skipped.', c_rc, c_fy;
        RETURN;
    END IF;

    SELECT id INTO st_br_approved FROM fims.settings_status WHERE status_for = 'budget_req'           AND name = 'Approved'       ORDER BY id LIMIT 1;
    SELECT id INTO st_ba_approved FROM fims.settings_status WHERE status_for = 'budget_allocation'    AND name = 'Approved'       ORDER BY id LIMIT 1;
    SELECT id INTO st_ba_review   FROM fims.settings_status WHERE status_for = 'budget_allocation'    AND name = 'For Review'     ORDER BY id LIMIT 1;
    SELECT id INTO st_dv_draft    FROM fims.settings_status WHERE status_for = 'disbursement_voucher' AND name = 'DRAFT'          ORDER BY id LIMIT 1;
    SELECT id INTO st_dv_fmd      FROM fims.settings_status WHERE status_for = 'disbursement_voucher' AND name = 'FOR_FMD_REVIEW' ORDER BY id LIMIT 1;
    SELECT id INTO st_dv_acctg    FROM fims.settings_status WHERE status_for = 'disbursement_voucher' AND name = 'FOR_ACCOUNTING' ORDER BY id LIMIT 1;

    SELECT user_id INTO u_region FROM uac.users WHERE username = 'r3.finance';
    SELECT user_id INTO u_fmd    FROM uac.users WHERE username = 'fmd_fundsourcing';

    SELECT vendor_id INTO v_supplies FROM fims.vendors WHERE vendor_code = 'SAMPLE-VEND-01';
    SELECT vendor_id INTO v_motor    FROM fims.vendors WHERE vendor_code = 'SAMPLE-VEND-02';
    SELECT vendor_id INTO v_security FROM fims.vendors WHERE vendor_code = 'SAMPLE-VEND-03';
    SELECT vendor_id INTO v_training FROM fims.vendors WHERE vendor_code = 'SAMPLE-VEND-04';

    SELECT r.rc_name INTO rc_name FROM fims.responsibility_code r WHERE r.rc_id = c_rc;

    -- ── Approved budget request + its COB lines ──
    INSERT INTO fims.budget_req (budget_reference_number, fiscal_yr, status_id, rc_id, submitted_at)
    VALUES (c_ref, c_fy, st_br_approved, c_rc, '2026-01-15 09:00:00+08');

    INSERT INTO fims.budget_req_program (budget_reference_number, program_code, sub_program_code)
    VALUES (c_ref, '101', '101-001') RETURNING budget_req_program_id INTO prog1;
    INSERT INTO fims.budget_req_program (budget_reference_number, program_code, sub_program_code)
    VALUES (c_ref, '101', '101-002') RETURNING budget_req_program_id INTO prog2;

    INSERT INTO fims.budget_req_project (budget_reference_number, project_code, budget_req_program_id, location)
    VALUES (c_ref, c_proj1, prog1, 'Binan City, Laguna') RETURNING budget_req_project_id INTO brp1;
    INSERT INTO fims.budget_req_project (budget_reference_number, project_code, budget_req_program_id, location)
    VALUES (c_ref, c_proj2, prog2, 'Bacoor, Cavite') RETURNING budget_req_project_id INTO brp2;

    -- obligation = what the Approved RFAs below draw; account_payable = what the CBAs reserved
    INSERT INTO fims.budget_line_items (budget_reference_number, budget_req_project_id, account_id, particulars, proposed_amount, suggested_amount, obligation, account_payable, type_of_fund_id, component_id)
    SELECT c_ref, brp1, coa.account_id, 'Office supplies for project site office', 550000, 500000, 85000, 200000, c_fund, c_comp
    FROM fims.chart_of_accounts coa WHERE coa.account_code = '5-02-03-010'
    RETURNING budget_line_item_id INTO li1;
    INSERT INTO fims.budget_line_items (budget_reference_number, budget_req_project_id, account_id, particulars, proposed_amount, suggested_amount, obligation, account_payable, type_of_fund_id, component_id)
    SELECT c_ref, brp1, coa.account_id, 'Repair and maintenance of project service vehicles', 320000, 300000, 48500, 150000, c_fund, c_comp
    FROM fims.chart_of_accounts coa WHERE coa.account_code = '5-02-13-060'
    RETURNING budget_line_item_id INTO li2;
    INSERT INTO fims.budget_line_items (budget_reference_number, budget_req_project_id, account_id, particulars, proposed_amount, suggested_amount, obligation, account_payable, type_of_fund_id, component_id)
    SELECT c_ref, brp2, coa.account_id, 'Security services for project site', 1200000, 1200000, 300000, 600000, c_fund, c_comp
    FROM fims.chart_of_accounts coa WHERE coa.account_code = '5-02-12-030'
    RETURNING budget_line_item_id INTO li3;
    INSERT INTO fims.budget_line_items (budget_reference_number, budget_req_project_id, account_id, particulars, proposed_amount, suggested_amount, obligation, account_payable, type_of_fund_id, component_id)
    SELECT c_ref, brp2, coa.account_id, 'Capacity building / training of project staff', 300000, 250000, 60000, 100000, c_fund, c_comp
    FROM fims.chart_of_accounts coa WHERE coa.account_code = '5-02-02-010'
    RETURNING budget_line_item_id INTO li4;

    IF li1 IS NULL OR li2 IS NULL OR li3 IS NULL OR li4 IS NULL THEN
        RAISE EXCEPTION 'RFA/DV sample: a required chart of accounts code is missing.';
    END IF;

    -- ── CBA-01 (project 1) and CBA-02 (project 2), both Approved ──
    INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, document_type, description, category_id, is_bidding, status_id, user_id, created_at, updated_at)
    VALUES ('CBA', 'CBA 2026-41-86-01', c_fy, c_rc, 'COE', 'Office supplies and vehicle maintenance - Casa Teodora Phase 1', 2, FALSE, st_ba_approved, u_region, '2026-10-05 09:00', '2026-10-08 15:00')
    RETURNING allocation_id INTO cba1;
    INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, line_transaction)
    VALUES (cba1, 0, '101', '101-001', c_proj1, jsonb_build_array(
        jsonb_build_object('budget_line_item_id', li1::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-03-010', 'account_title', 'Office Supplies Expenses', 'fiscal_yr', c_fy::text, 'proposed_amount', '550000.00', 'suggested_amount', '500000.00', 'available_amount', '500000.00', 'requested_amount', '200000.00', 'allocated_amount', '200000.00'),
        jsonb_build_object('budget_line_item_id', li2::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-13-060', 'account_title', 'Repairs and Maintenance-Transportation Equipment', 'fiscal_yr', c_fy::text, 'proposed_amount', '320000.00', 'suggested_amount', '300000.00', 'available_amount', '300000.00', 'requested_amount', '150000.00', 'allocated_amount', '150000.00')))
    RETURNING budget_allocation_project_id INTO cba1_blk;

    INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, document_type, description, category_id, is_bidding, status_id, user_id, created_at, updated_at)
    VALUES ('CBA', 'CBA 2026-41-86-02', c_fy, c_rc, 'COE', 'Site security and staff training - Ciudad Kaunlaran Phase 2C', 2, FALSE, st_ba_approved, u_region, '2026-10-06 09:00', '2026-10-09 15:00')
    RETURNING allocation_id INTO cba2;
    INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, line_transaction)
    VALUES (cba2, 0, '101', '101-002', c_proj2, jsonb_build_array(
        jsonb_build_object('budget_line_item_id', li3::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-12-030', 'account_title', 'Security Services', 'fiscal_yr', c_fy::text, 'proposed_amount', '1200000.00', 'suggested_amount', '1200000.00', 'available_amount', '1200000.00', 'requested_amount', '600000.00', 'allocated_amount', '600000.00'),
        jsonb_build_object('budget_line_item_id', li4::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-02-010', 'account_title', 'Training Expenses', 'fiscal_yr', c_fy::text, 'proposed_amount', '300000.00', 'suggested_amount', '250000.00', 'available_amount', '250000.00', 'requested_amount', '100000.00', 'allocated_amount', '100000.00')))
    RETURNING budget_allocation_project_id INTO cba2_blk;

    -- CBA 'active' earmarks: amount = original - what the Approved RFAs below have drawn
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, balance_applied, created_at)
    VALUES (li1, cba1, cba1_blk, 115000, 200000, 'active', FALSE, '2026-10-08 15:00') RETURNING earmark_id INTO em_c1_l1;
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, balance_applied, created_at)
    VALUES (li2, cba1, cba1_blk, 101500, 150000, 'active', FALSE, '2026-10-08 15:00') RETURNING earmark_id INTO em_c1_l2;
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, balance_applied, created_at)
    VALUES (li3, cba2, cba2_blk, 300000, 600000, 'active', FALSE, '2026-10-09 15:00') RETURNING earmark_id INTO em_c2_l3;
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, balance_applied, created_at)
    VALUES (li4, cba2, cba2_blk,  40000, 100000, 'active', FALSE, '2026-10-09 15:00') RETURNING earmark_id INTO em_c2_l4;

    -- ── RFAs ──
    -- RFA 1: office supplies (Approved)
    INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, vendor_id, parent_id, document_type, description, po_number, category_id, is_bidding, status_id, user_id, created_at, updated_at)
    VALUES ('RFA', '2642-57-01', c_fy, c_rc, v_supplies, cba1, 'COE', 'Purchase of office supplies for Casa Teodora Phase 1 site office', 'PO-2026-R03-0101', 2, FALSE, st_ba_approved, u_region, '2026-11-03 09:00', '2026-11-04 14:00')
    RETURNING allocation_id INTO rfa1;
    INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, parent_allocation_id, line_transaction)
    VALUES (rfa1, 0, '101', '101-001', c_proj1, cba1, jsonb_build_array(
        jsonb_build_object('budget_line_item_id', li1::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-03-010', 'account_title', 'Office Supplies Expenses', 'fiscal_yr', c_fy::text, 'description', 'Bond paper, toner and filing supplies', 'requested_amount', '85000.00', 'allocated_amount', '85000.00')))
    RETURNING budget_allocation_project_id INTO blk;
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, released_at, source_earmark_id, created_at)
    VALUES (li1, rfa1, blk, 85000, 85000, 'converted', '2026-11-04 14:00', em_c1_l1, '2026-11-04 14:00');

    -- RFA 2: vehicle repair (Approved)
    INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, vendor_id, parent_id, document_type, description, po_number, category_id, is_bidding, status_id, user_id, created_at, updated_at)
    VALUES ('RFA', '2642-57-02', c_fy, c_rc, v_motor, cba1, 'COE', 'Repair and preventive maintenance of project service vehicle SAB-1234', 'PO-2026-R03-0102', 2, FALSE, st_ba_approved, u_region, '2026-11-04 09:00', '2026-11-05 14:00')
    RETURNING allocation_id INTO rfa2;
    INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, parent_allocation_id, line_transaction)
    VALUES (rfa2, 0, '101', '101-001', c_proj1, cba1, jsonb_build_array(
        jsonb_build_object('budget_line_item_id', li2::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-13-060', 'account_title', 'Repairs and Maintenance-Transportation Equipment', 'fiscal_yr', c_fy::text, 'description', 'Brake system overhaul and change oil', 'requested_amount', '48500.00', 'allocated_amount', '48500.00')))
    RETURNING budget_allocation_project_id INTO blk;
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, released_at, source_earmark_id, created_at)
    VALUES (li2, rfa2, blk, 48500, 48500, 'converted', '2026-11-05 14:00', em_c1_l2, '2026-11-05 14:00');

    -- RFA 3: security services (Approved) — DV draws only half (progress payment)
    INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, vendor_id, parent_id, document_type, description, po_number, category_id, is_bidding, status_id, user_id, created_at, updated_at)
    VALUES ('RFA', '2642-57-03', c_fy, c_rc, v_security, cba2, 'COE', 'Security services for Ciudad Kaunlaran Phase 2C, Nov 2026 - Apr 2027', 'PO-2026-R03-0103', 2, TRUE, st_ba_approved, u_region, '2026-11-05 09:00', '2026-11-06 14:00')
    RETURNING allocation_id INTO rfa3;
    INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, parent_allocation_id, line_transaction)
    VALUES (rfa3, 0, '101', '101-002', c_proj2, cba2, jsonb_build_array(
        jsonb_build_object('budget_line_item_id', li3::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-12-030', 'account_title', 'Security Services', 'fiscal_yr', c_fy::text, 'description', '4 guards x 6 months', 'requested_amount', '300000.00', 'allocated_amount', '300000.00')))
    RETURNING budget_allocation_project_id INTO blk;
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, released_at, source_earmark_id, created_at)
    VALUES (li3, rfa3, blk, 300000, 300000, 'converted', '2026-11-06 14:00', em_c2_l3, '2026-11-06 14:00');

    -- RFA 4: staff training (Approved)
    INSERT INTO fims.budget_allocation (allocation_type, allocation_number, fiscal_year, requesting_rc_id, vendor_id, parent_id, document_type, description, category_id, is_bidding, status_id, user_id, created_at, updated_at)
    VALUES ('RFA', '2642-57-04', c_fy, c_rc, v_training, cba2, 'COE', 'Project financial management training for 12 project staff', 3, FALSE, st_ba_approved, u_region, '2026-11-06 09:00', '2026-11-09 14:00')
    RETURNING allocation_id INTO rfa4;
    INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, parent_allocation_id, line_transaction)
    VALUES (rfa4, 0, '101', '101-002', c_proj2, cba2, jsonb_build_array(
        jsonb_build_object('budget_line_item_id', li4::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-02-010', 'account_title', 'Training Expenses', 'fiscal_yr', c_fy::text, 'description', '3-day seminar, 12 participants', 'requested_amount', '60000.00', 'allocated_amount', '60000.00')))
    RETURNING budget_allocation_project_id INTO blk;
    INSERT INTO fims.budget_line_item_earmark (budget_line_item_id, allocation_id, budget_allocation_project_id, amount, original_amount, status, released_at, source_earmark_id, created_at)
    VALUES (li4, rfa4, blk, 60000, 60000, 'converted', '2026-11-09 14:00', em_c2_l4, '2026-11-09 14:00');

    -- RFA 5: For Review — no allocation number and no earmark movement until it is approved
    INSERT INTO fims.budget_allocation (allocation_type, fiscal_year, requesting_rc_id, vendor_id, parent_id, document_type, description, category_id, is_bidding, status_id, user_id, created_at, updated_at)
    VALUES ('RFA', c_fy, c_rc, v_supplies, cba1, 'COE', 'Additional office supplies for Q1 2027 site operations', 2, FALSE, st_ba_review, u_region, '2026-11-12 10:00', '2026-11-12 10:00')
    RETURNING allocation_id INTO rfa5;
    INSERT INTO fims.budget_allocation_project (allocation_id, sort_order, program_code, sub_program_code, project_code, parent_allocation_id, line_transaction)
    VALUES (rfa5, 0, '101', '101-001', c_proj1, cba1, jsonb_build_array(
        jsonb_build_object('budget_line_item_id', li1::text, 'type_of_fund_id', c_fund::text, 'type_of_fund', 'CORPORATE RECEIPT', 'account_code', '5-02-03-010', 'account_title', 'Office Supplies Expenses', 'fiscal_yr', c_fy::text, 'description', 'Office supplies replenishment', 'requested_amount', '40000.00', 'allocated_amount', '')));

    -- ── Disbursement Vouchers (dv_no = YY-MM-NNNN, continuing any existing series) ──
    SELECT COALESCE(MAX(CAST(SUBSTRING(dv_no FROM '[0-9]+$') AS INTEGER)), 0) INTO dv_seq
    FROM fims.disbursement_vouchers WHERE dv_no LIKE '26-11-%' AND dv_no ~ '-[0-9]+$';

    -- DV 1 <- RFA 1, cleared by FMD, now with Accounting
    INSERT INTO fims.disbursement_vouchers (dv_no, dv_date, fiscal_yr, expense_class, status_id, dept_project, fund_source,
        vendor_id, claimant_name, claimant_address, employee_no_tin, particulars, gross_amount, net_amount,
        po_number, po_amount, account_code, budget_amount, obligation_amount, payments_prior_amount, balance_amount,
        remarks, created_by, submitted_by, submitted_at, fmd_approved_by, fmd_approved_at, created_at, updated_at)
    SELECT '26-11-' || lpad((dv_seq + 1)::text, 4, '0'), DATE '2026-11-10', c_fy, 'MOOE', st_dv_acctg, rc_name, 'CORPORATE RECEIPT',
        v.vendor_id, v.vendor_name, v.address, v.tin, 'Payment for office supplies delivered to Casa Teodora Phase 1 site office per PO-2026-R03-0101', 85000, 85000,
        'PO-2026-R03-0101', 85000, '5-02-03-010', 85000, 85000, 0, 0,
        NULL, u_region, u_region, '2026-11-10 09:30', u_fmd, '2026-11-11 10:15', '2026-11-10 09:00', '2026-11-11 10:15'
    FROM fims.vendors v WHERE v.vendor_id = v_supplies
    RETURNING dv_id INTO dv1;

    -- DV 2 <- RFA 2, submitted, waiting on FMD
    INSERT INTO fims.disbursement_vouchers (dv_no, dv_date, fiscal_yr, expense_class, status_id, dept_project, fund_source,
        vendor_id, claimant_name, claimant_address, employee_no_tin, particulars, gross_amount, net_amount,
        po_number, po_amount, account_code, budget_amount, obligation_amount, payments_prior_amount, balance_amount,
        remarks, created_by, submitted_by, submitted_at, created_at, updated_at)
    SELECT '26-11-' || lpad((dv_seq + 2)::text, 4, '0'), DATE '2026-11-13', c_fy, 'MOOE', st_dv_fmd, rc_name, 'CORPORATE RECEIPT',
        v.vendor_id, v.vendor_name, v.address, v.tin, 'Payment for repair and preventive maintenance of service vehicle SAB-1234 per PO-2026-R03-0102', 48500, 48500,
        'PO-2026-R03-0102', 48500, '5-02-13-060', 48500, 48500, 0, 0,
        NULL, u_region, u_region, '2026-11-13 11:00', '2026-11-13 10:30', '2026-11-13 11:00'
    FROM fims.vendors v WHERE v.vendor_id = v_motor
    RETURNING dv_id INTO dv2;

    -- DV 3 <- RFA 3, partial draw (Nov 2026 - Jan 2027), with Accounting; balance_amount shows the 150,000 left
    INSERT INTO fims.disbursement_vouchers (dv_no, dv_date, fiscal_yr, expense_class, status_id, dept_project, fund_source,
        vendor_id, claimant_name, claimant_address, employee_no_tin, particulars, gross_amount, net_amount,
        po_number, po_amount, account_code, budget_amount, obligation_amount, payments_prior_amount, balance_amount,
        remarks, created_by, submitted_by, submitted_at, fmd_approved_by, fmd_approved_at, created_at, updated_at)
    SELECT '26-11-' || lpad((dv_seq + 3)::text, 4, '0'), DATE '2026-11-16', c_fy, 'MOOE', st_dv_acctg, rc_name, 'CORPORATE RECEIPT',
        v.vendor_id, v.vendor_name, v.address, v.tin, 'Security services for Ciudad Kaunlaran Phase 2C, Nov 2026 - Jan 2027', 150000, 150000,
        'PO-2026-R03-0103', 300000, '5-02-12-030', 300000, 300000, 0, 150000,
        'Partial billing: 3 of 6 months', u_region, u_region, '2026-11-16 09:00', u_fmd, '2026-11-17 13:40', '2026-11-16 08:45', '2026-11-17 13:40'
    FROM fims.vendors v WHERE v.vendor_id = v_security
    RETURNING dv_id INTO dv3;

    -- DV 4 <- RFA 4, still a draft at the originating office
    INSERT INTO fims.disbursement_vouchers (dv_no, dv_date, fiscal_yr, expense_class, status_id, dept_project, fund_source,
        vendor_id, claimant_name, claimant_address, employee_no_tin, particulars, gross_amount, net_amount,
        po_number, po_amount, account_code, budget_amount, obligation_amount, payments_prior_amount, balance_amount,
        remarks, created_by, created_at, updated_at)
    SELECT '26-11-' || lpad((dv_seq + 4)::text, 4, '0'), DATE '2026-11-18', c_fy, 'MOOE', st_dv_draft, rc_name, 'CORPORATE RECEIPT',
        v.vendor_id, v.vendor_name, v.address, v.tin, 'Training fees, project financial management seminar for 12 project staff', 60000, 60000,
        NULL, NULL, '5-02-02-010', 60000, 60000, 0, 0,
        NULL, u_region, '2026-11-18 14:00', '2026-11-18 14:00'
    FROM fims.vendors v WHERE v.vendor_id = v_training
    RETURNING dv_id INTO dv4;

    -- The RFA -> DV link lives on the RFA side (one RFA, one active DV)
    UPDATE fims.budget_allocation SET dv_id = dv1 WHERE allocation_id = rfa1;
    UPDATE fims.budget_allocation SET dv_id = dv2 WHERE allocation_id = rfa2;
    UPDATE fims.budget_allocation SET dv_id = dv3 WHERE allocation_id = rfa3;
    UPDATE fims.budget_allocation SET dv_id = dv4 WHERE allocation_id = rfa4;

    RAISE NOTICE 'RFA/DV sample: CBAs %/%, RFAs %/%/%/%/%, DVs %/%/%/%.', cba1, cba2, rfa1, rfa2, rfa3, rfa4, rfa5, dv1, dv2, dv3, dv4;
END $$;
