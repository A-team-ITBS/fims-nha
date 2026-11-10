<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_bgr_api = Config('API.FIMS_URL');

// User level 2 = Budget Officer: Add / Edit / Delete are hidden for this
// level, and the per-row "Budget Processing" action is shown only to this level.
// User level 8 = Budget Head: "Add Budget Request" is hidden, and only this
// level sees the "Budget Preview" action (whose modal has Return / Approve
// buttons on an Endorsed request).
$userLevel = CurrentUserLevel();
$_bgr_isBudgetOfficer = ((int) $userLevel === 2);
$_bgr_isBudgetHead    = ((int) $userLevel === 8);
$_bgr_canAdd    = !$_bgr_isBudgetOfficer && !$_bgr_isBudgetHead;
$_bgr_canModify = !$_bgr_isBudgetOfficer;
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .bgr-summary-2col { grid-template-columns: repeat(2, 1fr); }
}
.nha-root #bgr-submit-review:disabled { opacity:.45;cursor:not-allowed; }
</style>

<div id="bgr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Budget Requirements</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="bgr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <?php if ($_bgr_canAdd): ?>
            <button id="bgr-add" type="button" class="nha-btn-primary">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Budget Request
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary bgr-summary-2col">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Requests</div>
                    <div class="nha-sc-val" id="bgr-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Amount</div>
                    <div class="nha-sc-val" id="bgr-kpi-amount">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="bgr-filter-fiscal-yr" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Program</label>
                <select id="bgr-filter-program" class="nha-filter-select">
                    <option value="">All</option>
                </select>
            </div>
            <div class="nha-filter-group-actions">
                <button type="button" id="bgr-submit-review" class="nha-btn-primary" disabled title="Filter by a single Draft fiscal year to submit its request">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Submit for Review
                </button>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead id="bgr-thead">
                    <tr>
                        <th>#</th>
                        <th>Fiscal Year</th>
                        <th>Program</th>
                        <th>Sub-Program</th>
                        <th># Projects</th>
                        <th># Line Items</th>
                        <th>Proposed Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bgr-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="bgr-from">0</span>–<span id="bgr-to">0</span></strong> of <strong><span id="bgr-total">0</span></strong></span>
            <div id="bgr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/readone/budget-req-readone.php'; ?>
<?php include __DIR__ . '/form/budget-req-form.php'; ?>
<?php include __DIR__ . '/form/budget-processing-form.php'; ?>
<?php include __DIR__ . '/readone/budget-preview-readone.php'; ?>

</div><!-- /#bgr-root -->
<?php /* TEMP-PRINT-TEST — remove this whole block (down to /TEMP-PRINT-TEST) */ include __DIR__ . '/readone/print-preview-readone.php'; ?>
<div class="nha-root" style="position:fixed;right:24px;bottom:24px;z-index:1050;display:flex;gap:8px;align-items:center;
     background:#fff;border:1px solid #E4ECF2;border-radius:8px;padding:8px 10px;box-shadow:0 2px 8px rgba(0,0,0,.12);">
    <b style="font-size:12px;">Print test</b>
    <select id="tmp-print-sample" class="nha-select" style="font-size:12px;padding:4px 6px;"></select>
    <button type="button" class="nha-btn nha-btn-primary nha-btn-sm" onclick="tmpPrintOpen()">Open Print Preview</button>
</div>
<script>
// TEMP-PRINT-TEST: one sample payload per form / variant → prp_open(payload, { type }).
const TMP_PRINT_SAMPLES = {
    'CBA – CO': ['CBA', { allocation_number: 'CBA-2026-11-0001', document_type: 'CO', fiscal_year_label: 'FY 2026', date: '2026-11-10',
        project_description: 'construction of the Multi-Purpose Building, Phase 1', requested_amount: '12500000.50', allocated_amount: '5000000',
        fund_source_name: 'Corporate Receipts (CR)', program_of_cob: 'Housing Program', category_name: 'Goods and Services', is_bidding: true }],
    'CBA – COE': ['CBA', { allocation_number: 'CBA-2026-11-0002', document_type: 'COE', fiscal_year_label: 'FY 2026', date: '2026-11-10',
        project_description: 'repair of the Records Section office', requested_amount: '850000', fund_source_name: 'Corporate Receipts (CR)' }],
    'CBA – Regional': ['CBA', { issuing_office: 'REGIONAL', allocation_number: 'CBA-R4-2026-0012', date: 'November 10, 2026', fiscal_year_label: 'FY 2026',
        project_description: 'procurement for the Supply and Delivery of Office Equipment for the Region IV Office', amount: '1250000',
        fund_source_name: 'Corporate Receipts', signatory_name: 'MARIA L. SANTOS', signatory_title: 'Regional Manager', signatory_office: 'Region IV Office' }],
    'CAF – FMD': ['CAF', { caf_number: 'CAF-2026-11-0003', allocation_number: 'RFA-2026-11-0007', date: '2026-11-10', caf_amount: '2500000.50',
        purpose: 'the 1st tranche of the construction of the Multi-Purpose Building', payee_name: 'Municipality of San Jose',
        payee_title: 'represented by its Mayor, Hon. Juan D. Santos', payee_office_address: 'Municipal Hall, San Jose, Nueva Ecija', fund_source_name: 'Corporate Receipts (CR)' }],
    'CAF – Regional (entered into with)': ['CAF', { issuing_office: 'REGIONAL', caf_number: 'CAF-R4-0007', date: '2026-11-10', caf_amount: '1250000.25',
        purpose: 'Supply and Delivery of Construction Materials for the Bagong Silang Housing Project', payee_name: 'ABC Construction Corp.',
        payee_office_address: '123 Rizal Ave., Calamba City', fund_source_name: 'Corporate Receipts (CR)', allocation_number: 'RFA-2026-0045',
        certified_name: 'JUAN D. CRUZ', certified_office: 'Region IV Office' }],
    'CAF – Regional (in favor of)': ['CAF', { issuing_office: 'REGIONAL', body_variant: 'in_favor_of', caf_number: 'CAF-R4-0008', date: '2026-11-10',
        caf_amount: '30000.50', purpose: 'Emergency Housing Assistance Program (EHAP)', payee_name: 'Maria Reyes', fund_source_name: 'NG Subsidy (NGS)',
        allocation_number: 'LAA 2026-41-03', ref_type: 'LAA', certified_name: 'JUAN D. CRUZ' }],
    'CAF – Regional (petty cash)': ['CAF', { issuing_office: 'REGIONAL', body_variant: 'in_favor_of_petty', caf_number: 'CAF-R4-0009', date: '2026-11-10',
        caf_amount: '20000', purpose: 'necessary to facilitate payment of petty operating expenses of the Region IV Office', payee_name: 'Ana Lim',
        fund_source_name: 'Corporate Receipts (CR)', certified_name: 'JUAN D. CRUZ' }],
    'RFA – Staff (1a)': ['RFA', { allocation_number: 'RFA-2026-11-0007', payee_name: 'General Services Department (GSD)', requesting_department_type: 'MAIN',
        particulars: 'Procurement of office equipment for the Records Section\nincluding installation and delivery', amount: '1250000', date: 'November 10, 2026',
        cert_correct_name: 'JUAN D. DELA CRUZ', cert_correct_title: 'Department Manager', cert_correct_office: 'General Services Department',
        project_name: 'Records Modernization', total_estimated_cost: '2500000', total_funds_available: '10000000', total_funded_to_date: '3000000',
        funding_source: { corporate_receipts: true, saro_no: 'SARO-2026-001', saro_date: '2026-01-15' }, certified_funds_name: 'MARIA S. REYES' }],
    'RFA – Regional (2a)': ['RFA', { issuing_office: 'REGIONAL', form_variant: '2a', allocation_number: 'RFA-R4-2026-0031', payee_name: 'Region IV Office (R4)',
        particulars: 'Supply and Delivery of Office Equipment for the Region IV Office\nincluding installation and delivery', amount: '1250000', date: 'November 10, 2026',
        cert_correct_name: 'MARIA L. SANTOS', cert_correct_title: 'Regional Manager', cert_correct_office: 'Region IV Office' }],
    'LAA – CO': ['LAA', { allocation_number: 'LAA 2026-41-95', date: '2026-11-09', office_name: 'General Services Department (GSD)', fiscal_year_label: 'FY 2026',
        project_description: 'procurement of office equipment for the Records Section', amount: '1250000.50', fund_source_name: 'Corporate Receipts (CR)', program_of_cob: 'Housing Support' }],
    'LAA – COE': ['LAA', { document_type: 'COE', allocation_number: 'LAA 2026-41-01', date: '2026-11-10', office_name: 'North Sector Office (NSO)', fiscal_year_label: 'FY 2026',
        project_description: 'Purchase of Supply and Installation of Office Blinds for the North Sector Office', amount: '485320.04',
        fund_source_name: 'Corporate Receipts (CR)', to_name: 'ENGR. JOVITA G. PANOPIO' }],
    'LAA release request (Regional memo)': ['LAAREQ', { date: '2026-11-10', for_name: 'CYNTHIA FLORENCE T. CARIAGA', for_title: 'Department Manager, Financial Management Department',
        thru_name: 'LORNA S. DIOLOLA', thru_title: 'Group Manager, Financial Services Group', description: 'the Repair and Rehabilitation of the Region IV Office Building',
        amount: '2450000', sig_name: 'MARIA L. SANTOS', sig_title: 'Regional Manager, Region IV', laa_number: 'LAA 2026-41-07' }],
    'PO – Purchase Order': ['PO', { po_number: 'PO-2026-11-0004', po_date: '2026-11-10', vendor_name: 'ABC Office Supplies Trading',
        vendor_address: '123 Rizal Avenue\nSta. Cruz, Manila', vendor_tin: '123-456-789-000', mode_of_procurement: 'Small Value Procurement',
        delivery_place: 'NHA Main Office, Diliman, Quezon City', delivery_terms: '15 calendar days upon receipt of PO', delivery_date_required: '2026-11-25',
        payment_terms: '30 days after delivery',
        items: [{ item_description: 'Bond paper, A4, 80gsm', unit_of_measure: 'ream', quantity_ordered: 50, unit_cost: 245.5 },
                { item_description: 'Ballpen, black, 0.5mm\n(box of 12)', unit_of_measure: 'box', quantity_ordered: 20, unit_cost: 120 }],
        purpose: 'For the use of the Records Section', requisitioners: 'General Services Department', pr_number: 'PR-2026-11-0012', warranty: 'One (1) year',
        approving_officer_name: 'ATTY. MARIA MAGDALENA D. SIACON', general_manager_name: 'JOEBEN A. TAI', prepared_by_initials: 'jdc/mls', alobs_no: 'ALOBS-2026-0457' }],
    'PR – Purchase Requisition': ['PR', { pr_number: 'PR-2026-11-0012', pr_date: '2026-11-05', department_name: 'General Services Department',
        purpose: 'For the use of the Records Section — replenishment of office supplies for CY 2026', delivery_date: 'November 20, 2026',
        items: [{ item_description: 'Bond paper, A4, 80gsm', unit_of_measure: 'ream', quantity_requested: 50, unit_cost: 245.5 },
                { item_description: 'Ballpen, black, 0.5mm (box of 12)', unit_of_measure: 'box', quantity_requested: 20, unit_cost: 120 },
                { item_description: 'Folder, long, brown', unit_of_measure: 'pack', quantity_requested: 10, unit_cost: 185 }],
        project_description: 'Records Modernization', total_funds_available: '500000', funded_to_date: '120000', fund_balance: '380000',
        fund_allotment_no: 'RFA-2026-11-0007',
        sig1_name: 'PEDRO S. LOPEZ', sig2_name: 'JUAN D. DELA CRUZ', sig3_name: 'CYNTHIA FLORENCE T. CARIAGA', sig4_name: 'LORNA S. DIOLOLA' }],
    'IAR – Inspection and Acceptance Report': ['IAR', { iar_number: 'IAR-2026-11-0009', supplier_name: 'ABC Office Supplies Trading',
        po_number: 'PO-2026-11-0004', po_date: '2026-11-03', si_number: 'SI-004512', si_date: '2026-11-06',
        requesting_department: 'General Services Department (GSD)',
        items: [{ unit_cost: '245.00', description: 'Bond paper, A4, 70gsm (ream)', quantity: 40 },
                { unit_cost: '1250.00', description: 'Toner cartridge, black (HP 85A)', quantity: 3 },
                { unit_cost: '35.00', description: 'Ballpoint pen, black (box of 12)', quantity: 20 }],
        date_inspected: '2026-11-07', delivery: 'complete',
        inspector_name: 'ANA L. SANTOS', div_manager_name: 'RICARDO M. BAUTISTA', dept_manager_name: 'JUAN D. DELA CRUZ' }],
    'LV – Liquidation Voucher (petty cash, with ledger)': ['LV', { lv_number: 'LV-2026-11-0007',
        disbursing_officer: 'MARIA L. GONZALES — LBP SA 0012-3456-78', department_region: 'NCR — East Sector',
        particulars: [{ description: 'Liquidation of petty cash fund for October 2026' },
                      { description: 'Office supplies (PCV 2026-10-031)', amount: '4250.00' },
                      { description: 'VAT 12%', amount: '510.00', sub: true },
                      { description: 'Transportation (PCV 2026-10-033)', amount: '1800.00' }],
        gross_amount: '6050.00', fund_source_code: 'CO-01', fund_source_name: 'Corporate Operating Budget',
        ca_dv_number: 'DV-2026-09-0112', ca_amount: '10000.00', prior_liquidations: [{ lv_number: 'LV-2026-10-0003', amount: '3200.00' }],
        tax_deposit_amount: '250.00', cash_refund_deposit_amount: '500.00', refund_or_number: 'OR-778812', refund_date: '2026-11-05',
        obligation: { dept_proj: 'Office of the Regional Manager', fund_source: 'Corporate Operating Budget (CO-01)',
            budget_ref: 'LV#', budget: '10000.00', obligation_ref: 'LV-2026-11-0007', obligation: '10000.00', balance: '0.00',
            payments_ref: 'DV-2026-09-0112', payments: '3200.00', this_request_ref: 'LV-2026-11-0007', this_request: '6050.00', total_payments: '9250.00' },
        accounting_entries: [{ account_code: '5-02-03-010', debit: '4250.00' }, { account_code: '5-02-02-010', debit: '1800.00' },
                             { account_code: '1-03-05-010', credit: '6050.00' }],
        cert_a_name: 'ROBERTO C. VILLANUEVA', cert_b_name: 'ELENA P. RAMOS', cert_c_name: 'MARIA S. REYES', jev_no: 'JEV-2026-11-0044',
        pc_summary: [{ type: 'received', label: 'Petty Cash Fund received per DV-2026-09-0112', amount: '10000.00' },
                     { type: 'less', label: 'Office supplies', amount: '4760.00' }, { type: 'less', label: 'Transportation', amount: '1800.00' },
                     { type: 'balance', label: 'Balance', amount: '3440.00' }] }],
    'LV – Liquidation Voucher (plain)': ['LV', { lv_number: '2026-0031', disbursing_officer: 'JUAN D. DELA CRUZ',
        department_region: 'General Services Department', particulars: 'Liquidation of travel cash advance — Region IV-A site inspection',
        gross_amount: '2500.50', ca_dv_number: 'DV-2026-10-0201', ca_amount: '3000.00', refund_date: '2026-11-04', refund_amount: '499.50',
        cert_a_name: 'ROBERTO C. VILLANUEVA', cert_b_name: 'ELENA P. RAMOS', cert_c_name: 'MARIA S. REYES' }],
    'JEV – Journal Entry Voucher': ['JEV', { jev_number: 'JEV-2026-11-0044', jev_date: '2026-11-07', office: 'Main Office',
        responsibility_center: 'GSD',
        lines: [{ account_title: 'Office Supplies Expenses', account_code: '5-02-03-010', debit: '14675.00' },
                { account_title: 'Due to BIR', account_code: '2-02-01-010', credit: '786.16',
                  description: 'EWT 1% + VAT 5%', sl_code: 'BIR-EWT', sl_path: 'Taxes › Withholding › BIR-EWT' },
                { account_title: 'Cash in Bank — LBP Current Account', account_code: '1-01-04-010', credit: '13888.84' }],
        reference_no: 'DV-2026-11-0021', payee_name: 'ABC Office Supplies Trading',
        particulars: 'To record payment for the delivery of office supplies for the Records Section per PO-2026-11-0004 and IAR No. 2026-11-0009.',
        rfa_number: 'RFA-2026-0112', saro_number: '',
        prepared_by_name: 'MARIA S. REYES', reviewed_by_name: 'RICARDO M. BAUTISTA', approved_by_name: 'CYNTHIA FLORENCE T. CARIAGA' }],
    'JEV – Journal Entry Voucher (draft, "Approved by:")': ['JEV', { jev_number: 'TEMP-000318', jev_date: '2026-11-08', office: 'NCR East Sector',
        lines: [{ account_title: 'Cash — Collecting Officer', account_code: '1-01-01-010', debit: '2500.00' },
                { account_title: 'Rental Income', account_code: '4-02-02-050', credit: '2500.00' }],
        particulars: 'To record collection of stall rentals for October 2026.', approved_by_label: 'Approved by:',
        prepared_by_name: 'ANA L. SANTOS', footer_remarks: 'For Accounting Unit use — DCD Disbursement Section' }],
    'FTLA – Fund Transfer Letter (one account → one account)': ['FTLA', { ftla_number: 'FTLA-2026-11-0012', date: '2026-11-10',
        to_bank_name: 'Landbank of the Philippines', to_bank_branch: 'Quezon City Circle Branch',
        from_account_name: 'NHA Corporate Operating Fund', from_account_number: '0702-1086-11',
        to_account_name: 'NHA FAO Capital Outlay Fund — NCR East Sector', to_account_number: '0702-1086-91', amount: '235500.00',
        purpose: 'Release of the first progress billing for trucking and transport services — ROCKYMAR TRANSPORT',
        sig_left_name: 'MARIA C. SANTOS', sig_right_name: 'JOSE R. DELA CRUZ' }],
    'FTLA – DRFTLA (several debits → one credit, DV footer)': ['FTLA', { ftla_number: 'FTLA-2026-11-0013', date: '2026-11-10', subject: 'Authority to Transfer Funds',
        transfers: [{ from_account_number: '0702-1086-11', from_account_name: 'NHA Corporate Operating Fund', to_account_number: '0702-1086-91', to_account_name: 'NHA FAO — NCR East Sector', amount: '150000.00' },
                    { from_account_number: '0702-1086-27', from_account_name: 'NHA Housing Subsidy Fund', to_account_number: '0702-1086-91', to_account_name: 'NHA FAO — NCR East Sector', amount: '85500.50' }],
        dv_numbers: ['DV-2026-11-0021', 'DV-2026-11-0024'], fund_classification: 'COE/MOOE',
        sig_left_name: 'MARIA C. SANTOS', sig_right_name: 'JOSE R. DELA CRUZ' }],
    'FTLA – Settlement (FROM / TO table)': ['FTLA', { ftla_number: 'FTLA-2026-11-0014', date: '2026-11-10',
        subject: 'Authority to Transfer Settlement Funds to Main Depository Account',
        transfers: [{ from_account_number: '0702-1086-11', from_account_name: 'NHA Corporate Operating Fund', to_account_number: '0702-1086-91', to_account_name: 'NHA FAO — NCR East Sector', amount: '100000.00' },
                    { from_account_number: '0702-1086-11', from_account_name: 'NHA Corporate Operating Fund', to_account_number: '0702-1086-95', to_account_name: 'NHA FAO — NCR North Sector', amount: '75000.00' },
                    { from_account_number: '0702-1086-27', from_account_name: 'NHA Housing Subsidy Fund', to_account_number: '0702-1086-99', to_account_name: 'NHA FAO — Region IV-A', amount: '42000.00' }],
        sig_left_name: 'ANA L. SANTOS', sig_left_title: 'Financial Planning Analyst',
        sig_right_name: 'MARIA C. SANTOS', sig_right_title: 'Officer-In-Charge\nTreasury Department' }],
    'FTLA request – regional memo to Treasury': ['FTLAREQ', { date: '2026-11-08', for_name: 'LEO CARMELO C. BONILLA',
        for_title: 'Department Manager, Treasury Department',
        subject: 'Request for Fund Transfer in Payment for the First Progress Billing for the Procurement of Trucking and Transport Services',
        particulars: 'Release of First Progress Billing Payment for the Procurement of Trucking and Transport Services entered into with ROCKYMAR TRANSPORT',
        amount: '235500.00', laa_number: '25-43-329', laa_department: 'NCR-East Sector', fiscal_year: '2025', rfa_number: '2543-57-003', rfa_date: '2025-12-26',
        bank_account_line: 'NHA FAO CAPITAL OUTLAY FUND-NCR-EAST SECTOR LBP Account No. 0702-1086-91',
        sig_name: 'AR. MON ALEXANDER ON', sig_title: 'Regional Manager', sig_office: 'NCR East Sector Office', ftla_number: '2026-0012' }],
    'DV – Disbursement Voucher (plain paper)': ['DV', Object.assign({ paper: 'plain' }, tmpDvSample())],
    'DV – Disbursement Voucher (pre-printed form)': ['DV', Object.assign({ paper: 'preprinted' }, tmpDvSample())],
};
function tmpDvSample() {
    return { voucher_number: 'DV-2026-11-0021', route: 'MAIN', payee_name: 'ABC Office Supplies Trading', payee_address: '123 Rizal Avenue, Sta. Cruz, Manila',
        payee_tin: '123-456-789-000', particulars: 'Payment for the delivery of office supplies for the Records Section per PO-2026-11-0004\nand IAR No. 2026-11-0009',
        gross_amount: '14675.00',
        deductions: [{ description: 'EWT 1% (WC158)', amount: '131.03' }, { description: 'VAT 5% (WV010)', amount: '655.13' }],
        accounting_entries: [
            { object_code: '50203010', account_code: '5-02-03-010', debit: '14675.00' },
            { account_code: '2-02-01-010', credit: '786.16' },
            { account_code: '1-01-04-010', credit: '13888.84' }],
        cert_officer1_name: 'JUAN D. DELA CRUZ', cert_officer1_title: 'Department Manager, GSD',
        accountant_name: 'MARIA S. REYES', accountant_title: 'Chief Corporate Accountant' };
}
document.getElementById('tmp-print-sample').replaceChildren(...Object.keys(TMP_PRINT_SAMPLES).map(k => new Option(k, k)));
function tmpPrintOpen() {
    const [type, payload] = TMP_PRINT_SAMPLES[document.getElementById('tmp-print-sample').value];
    prp_open(payload, { type });
}
</script>
<?php /* /TEMP-PRINT-TEST */ ?>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REQUIREMENTS LIST   prefix: bgr_
   Modal-support functions (bgr_openModal, bgr_save, loaders, etc.) live
   in form/budget-req-form.php; the Program "View" modal (bgrv_*) lives in
   readone/budget-req-readone.php — both included above. This script
   fetches budget requests (each with nested programs[]/projects[]/
   line_items[]) and flattens them to one table row per Program (a
   fiscal year with no programs yet still gets one placeholder row).
═══════════════════════════════════════════════════════════════ */

let bgr_page = 1;
const BGR_PAGE_SIZE = 15;
// false for user level 2 (Budget Officer) — hides the row Edit / Delete actions.
const BGR_CAN_MODIFY = <?= $_bgr_canModify ? 'true' : 'false' ?>;
// true only for user level 2 (Budget Officer) — shows the row "Budget Processing" action.
const BGR_CAN_BUDGET_PROCESSING = <?= $_bgr_isBudgetOfficer ? 'true' : 'false' ?>;
// true only for user level 8 (Budget Head) — shows the row "Budget Preview" action,
// and Return / Approve inside its modal.
const BGR_IS_BUDGET_HEAD = <?= $_bgr_isBudgetHead ? 'true' : 'false' ?>;
let bgr_flatRows = [];

window.bgr_afterSave = () => bgr_load();

async function bgr_bootstrapLoad() {
    await bgr_load();
    bgr_bindListEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_bootstrapLoad);
} else {
    bgr_bootstrapLoad();
}

function bgr_bindListEvents() {
    $bgr('bgr-refresh')?.addEventListener('click', () => { bgr_page = 1; bgr_load(); });
    $bgr('bgr-add')?.addEventListener('click', () => bgr_openModal('add'));

    ['bgr-filter-fiscal-yr', 'bgr-filter-program'].forEach(id => {
        $bgr(id)?.addEventListener('change', () => { bgr_page = 1; bgr_load(); });
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function bgr_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            fiscal_yr   : $bgr('bgr-filter-fiscal-yr')?.value || '',
            program_code: $bgr('bgr-filter-program')?.value || '',
            page        : bgr_page,
            limit       : BGR_PAGE_SIZE,
        });

        const res  = await fetch(`${BGR_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        bgr_renderTable(json.data ?? []);
        bgr_updateSubmitButton(json.data ?? []);

        bgr_renderPagination(json.pagination ?? {});
        $bgr('bgr-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $bgr('bgr-kpi-amount').textContent = bgr_fmtMoney(json.total_amount ?? 0);
    } catch (e) {
        bgr_renderTable([]);
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Flatten: one table row per Program, falling back to one placeholder
   row for a fiscal year's request that has no programs yet ──────────── */
function bgr_flattenRows(headers) {
    const rows = [];
    headers.forEach(header => {
        const programs = header.programs ?? [];
        if (!programs.length) {
            rows.push({ header, program: null });
        } else {
            programs.forEach(program => rows.push({ header, program }));
        }
    });
    return rows;
}

function bgr_programTotals(program) {
    const projects = program.projects ?? [];
    let lineItemCount = 0;
    let amount = 0;
    projects.forEach(project => {
        const lineItems = project.line_items ?? [];
        lineItemCount += lineItems.length;
        lineItems.forEach(li => { amount += Number(li.proposed_amount || 0); });
    });
    return { projectCount: projects.length, lineItemCount, amount };
}

function bgr_actionButtons(flatRow, idx) {
    const { header, program } = flatRow;
    const isDraft = header.status_name === 'Draft';

    const viewBtn = program
        ? `<button class="nha-btn-act" onclick="bgr_viewProgram(${idx})" title="View">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            View
        </button>`
        : '';
    const editBtn = (BGR_CAN_MODIFY && program && isDraft)
        ? `<button class="nha-btn-act edit" onclick="bgr_editProgram(${idx})" title="Edit">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Edit
        </button>`
        : '';
    const deleteBtn = (BGR_CAN_MODIFY && isDraft)
        ? `<button class="nha-btn-act danger" onclick="bgr_delete(${header.budget_reference_number}, ${header.fiscal_yr})" title="Delete">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete
        </button>`
        : '';
    // Hidden once the request has been Endorsed or Approved — it can't be processed again.
    const budgetProcessingBtn = (BGR_CAN_BUDGET_PROCESSING && !['Endorsed', 'Approved'].includes(header.status_name))
        ? `<button class="nha-btn-act" onclick="bgr_budgetProcessing(${idx})" title="Budget Processing">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            Budget Processing
        </button>`
        : '';
    // Read-only counterpart of Budget Processing — every line item with its
    // Type of Fund, Suggestion Amount and Component, nothing to fill in.
    // Budget Head (level 8) only.
    const budgetPreviewBtn = (BGR_IS_BUDGET_HEAD && program)
        ? `<button class="nha-btn-act" onclick="bgr_budgetPreview(${idx})" title="Budget Preview">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Budget Preview
        </button>`
        : '';
    return viewBtn + editBtn + deleteBtn + budgetProcessingBtn + budgetPreviewBtn;
}

function bgr_viewProgram(idx) {
    const { header, program } = bgr_flatRows[idx];
    if (!program) return;
    bgrv_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        is_draft: header.status_name === 'Draft',
    }, program);
}

/* "Budget Processing" row action (level 2 / Budget Officer only) — opens the
   Budget Processing modal (form/budget-processing-form.php, bpr_*) for this
   row's Program: its line items, each with Type of Fund + Component. */
function bgr_budgetProcessing(idx) {
    const { header, program } = bgr_flatRows[idx];
    if (!program) {
        bgr_toast('This budget request has no programs yet.', 'warning');
        return;
    }
    bpr_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
    }, program);
}

/* "Budget Preview" row action — opens the read-only Budget Preview modal
   (readone/budget-preview-readone.php, bpv_*) for this row's Program. */
function bgr_budgetPreview(idx) {
    const { header, program } = bgr_flatRows[idx];
    if (!program) return;
    bpv_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        // Return / Approve: Budget Head only, and only once the request is Endorsed.
        can_decide: BGR_IS_BUDGET_HEAD && header.status_name === 'Endorsed',
    }, program);
}

function bgr_editProgram(idx) {
    const { header, program } = bgr_flatRows[idx];
    if (!program) return;
    bgr_openModal('edit-program', {
        ...program,
        fiscal_yr: header.fiscal_yr,
        budget_reference_number: header.budget_reference_number,
    });
}

/* ── Table — one row per Program ──────────────────────────────────── */
function bgr_renderTable(headers) {
    const tbody = $bgr('bgr-tbody');
    bgr_flatRows = bgr_flattenRows(headers);

    if (!bgr_flatRows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget requests found.</span></div></td></tr>';
        return;
    }

    tbody.innerHTML = bgr_flatRows.map((flatRow, idx) => {
        const { header, program } = flatRow;
        const totals = program ? bgr_programTotals(program) : { projectCount: 0, lineItemCount: 0, amount: 0 };

        return `<tr>
            <td class="nha-td-seq">${idx + 1}</td>
            <td>${bgr_esc(header.fiscal_yr)}</td>
            <td>
                ${program
                    ? `<div style="font-weight:500;">${bgr_esc(program.program_code)} — ${bgr_esc(program.program_name)}</div>`
                    : '<span class="nha-td-sub">No programs added yet</span>'}
            </td>
            <td>${program && program.sub_program_name ? bgr_esc(program.sub_program_name) : '<span class="nha-td-sub">—</span>'}</td>
            <td class="text-end">${totals.projectCount}</td>
            <td class="text-end">${totals.lineItemCount}</td>
            <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(totals.amount)}</div></td>
            <td>${bgr_statusBadge(header.status_name, header.status_color)}</td>
            <td>${bgr_actionButtons(flatRow, idx)}</td>
        </tr>`;
    }).join('');
}

/* ── Top-of-filters "Submit for Review" — acts on whichever single Draft
   request the Fiscal Year filter currently narrows the list down to.
   Disabled unless that filter names exactly one Draft request. ────────── */
function bgr_updateSubmitButton(headers) {
    const btn = $bgr('bgr-submit-review');
    if (!btn) return;

    // Enabled whenever the current view (filtered or not) already narrows
    // down to exactly one Draft request — no need to type a fiscal year
    // into the filter first if there's only one Draft request to begin with.
    const draftHeaders = headers.filter(h => h.status_name === 'Draft');
    const match = draftHeaders.length === 1 ? draftHeaders[0] : null;

    btn.disabled = !match;
    btn.title = match
        ? `Submit the fiscal year ${match.fiscal_yr} request for review`
        : draftHeaders.length > 1
            ? 'Filter by a single fiscal year to submit its request'
            : 'No Draft request to submit';
    btn.onclick = match ? () => bgr_submitForReview(match.budget_reference_number) : null;
}

/* ── Pagination ────────────────────────────────────────────── */
function bgr_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = BGR_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $bgr('bgr-from').textContent  = from;
    $bgr('bgr-to').textContent    = to;
    $bgr('bgr-total').textContent = total;

    const container = $bgr('bgr-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="bgr_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="bgr_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="bgr_goTo(${current_page + 1})">›</button>`;
}

function bgr_goTo(page) { bgr_page = page; bgr_load(); }

async function bgr_submitForReview(id) {
    if (!confirm('Submit this budget request for review? No more programs can be added or edited once submitted.')) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/submit-for-review/${id}`, { method: 'PUT', credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to submit for review.');

        bgr_toast(json.message || 'Submitted for review.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function bgr_delete(id, fiscalYr) {
    if (!confirm(`Delete the entire budget request for fiscal year ${fiscalYr}? This removes ALL its programs and cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/delete/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete budget request.');

        bgr_toast('Budget request deleted successfully.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}
</script>
