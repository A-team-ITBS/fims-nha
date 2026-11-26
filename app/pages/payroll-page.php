<?php

namespace PHPMaker2024\FIMS;

/**
 * payroll-page.php — Payroll Management (PayrollPage). A separate module from Payroll Setup (payroll-setup-page.php,
 * the payroll items master): this page only IMPORTS the monthly employee payroll (the Regular FIMS MONTHLY PAYROLL
 * Excel template) and lists one summary row per imported month: Payroll Month, Year, Import Date, Status, Total
 * Payroll Amount (gross). No DV creation here — the payroll DV is prepared in the DV module from an imported month.
 * Each template column is matched to a Payroll Setup item by its item code.
 * API: app/routes/payroll_management.php → app/services/PayrollManagementService.php.
 */
@session_start();
$_pr_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_pr_api = preg_match('~/api$~', $_pr_api) ? $_pr_api : $_pr_api . '/api';
?>
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
#pr-root .nha-summary { grid-template-columns: repeat(4, 1fr); }
#pr-root .pr-num { text-align: right; font-family: 'IBM Plex Mono', monospace; white-space: nowrap; }
#pr-root .pr-form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
#pr-root .pr-form .pr-full { grid-column: 1 / -1; }
#pr-root .pr-form label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
#pr-root .pr-form select, #pr-root .pr-form input[type=file] { width: 100%; padding: 8px 10px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 6px; font: inherit; background: var(--afims-surface, #fff); color: inherit; }
#pr-root .pr-hint { font-size: 12px; color: var(--afims-text-muted, #62657E); margin-top: 4px; }
#pr-root .pr-preview { margin-top: 16px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px; padding: 12px 14px; display: none; }
#pr-root .pr-preview.visible { display: block; }
#pr-root .pr-preview dl { display: grid; grid-template-columns: 1fr auto; gap: 6px 16px; margin: 0; font-size: 13px; }
#pr-root .pr-preview dt { color: var(--afims-text-muted, #62657E); }
#pr-root .pr-preview dd { margin: 0; text-align: right; font-weight: 600; font-family: 'IBM Plex Mono', monospace; }
#pr-root .pr-msg { margin-top: 12px; font-size: 12.5px; padding: 8px 10px; border-radius: 6px; display: none; }
#pr-root .pr-msg.err  { display: block; background: var(--red-bg, #FDECEC); color: var(--red-txt, #B42318); }
#pr-root .pr-msg.warn { display: block; background: var(--amber-bg, #FFF6E5); color: var(--amber-txt, #92400E); }
#pr-root .pr-msg ul { margin: 6px 0 0 18px; padding: 0; }
@media (max-width: 900px) { #pr-root .nha-summary { grid-template-columns: repeat(2, 1fr); } }
#pr-root .pr-pane { display: none; }
#pr-root .pr-pane.active { display: block; }
#pr-root .pr-modal-body-scroll { max-height: 70vh; overflow: auto; }
#pr-root .pr-sub { font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
#pr-root .pr-tfoot td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); }
#pr-root .pr-search { padding: 7px 10px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 6px; font: inherit; min-width: 240px; background: var(--afims-surface, #fff); color: inherit; }
#pr-root .pr-card { border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; background: var(--afims-surface, #fff); }
#pr-root .pr-card h4 { margin: 0 0 10px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-heading, #000E3E); }
#pr-root .pr-chips { display: flex; flex-wrap: wrap; gap: 6px; }
#pr-root .pr-chip { font-size: 11.5px; padding: 3px 8px; border-radius: 12px; background: var(--afims-surface-subtle, #F4F7FA); border: 1px solid var(--afims-border, #E2E4ED); font-family: 'IBM Plex Mono', monospace; }
#pr-root .pr-chip small { font-family: 'IBM Plex Sans', sans-serif; color: var(--afims-text-muted, #62657E); margin-left: 4px; }
/* File Template tab */
#pr-root .pr-steps { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
#pr-root .pr-step { display: flex; gap: 12px; align-items: flex-start; padding: 12px; border-radius: 8px; background: var(--afims-surface-subtle, #F4F7FA); }
#pr-root .pr-step-no { flex: 0 0 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; background: var(--afims-primary, #0035A9); color: #fff; }
#pr-root .pr-step-title { font-weight: 600; font-size: 13.5px; margin-bottom: 3px; color: var(--afims-heading, #000E3E); }
#pr-root .pr-step .pr-sub { font-size: 12px; }
#pr-root .pr-ready { display: flex; gap: 8px; align-items: flex-start; padding: 9px 12px; border-radius: 6px; font-size: 12.5px; }
#pr-root .pr-ready.ok { background: var(--green-bg, #E8F6EE); color: var(--green-txt, #0E7A3D); }
#pr-root .pr-ready.warn { background: var(--amber-bg, #FFF6E5); color: var(--amber-txt, #92400E); }
#pr-root .pr-t-tbl td { vertical-align: top; font-size: 12.5px; }
#pr-root .pr-t-tbl .nha-mono { font-size: 12px; }
#pr-root .pr-t-scroll { max-height: 460px; overflow: auto; }
#pr-root .pr-t-scroll thead th { position: sticky; top: 0; z-index: 1; }   /* keeps the table's own header colour */
#pr-root #pr-t-search { min-width: 300px; }
#pr-root .pr-t-toolbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
#pr-root .pr-t-pills { display: flex; gap: 6px; flex-wrap: wrap; }
#pr-root .pr-t-pill { font: inherit; font-size: 12px; padding: 5px 11px; border-radius: 14px; border: 1px solid var(--afims-border, #E2E4ED); background: var(--afims-surface, #fff); color: inherit; cursor: pointer; }
#pr-root .pr-t-pill.active { background: var(--afims-primary, #0035A9); border-color: var(--afims-primary, #0035A9); color: #fff; }
#pr-root .pr-badge { display: inline-block; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; white-space: nowrap; }
#pr-root .pr-badge.EARNING { background: var(--green-bg, #E8F6EE); color: var(--green-txt, #0E7A3D); }
#pr-root .pr-badge.DEDUCTION { background: var(--amber-bg, #FFF6E5); color: var(--amber-txt, #92400E); }
#pr-root .pr-badge.REFUND { background: var(--blue-bg, #EAF1FE); color: var(--blue-txt, #1D4ED8); }
#pr-root .pr-badge.EMPLOYER_SHARE { background: var(--red-bg, #FDECEC); color: var(--red-txt, #B42318); }
#pr-root .pr-badge.st-ACTIVE { background: var(--green-bg, #E8F6EE); color: var(--green-txt, #0E7A3D); }
#pr-root .pr-badge.st-INACTIVE { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-muted, #62657E); }
#pr-root .pr-badge.st-MISSING { background: var(--amber-bg, #FFF6E5); color: var(--amber-txt, #92400E); }
#pr-root .pr-badge.req { background: var(--red-bg, #FDECEC); color: var(--red-txt, #B42318); }
#pr-root .pr-badge.opt { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-muted, #62657E); }
#pr-root .pr-t-more summary { cursor: pointer; font-weight: 700; font-size: 13px; color: var(--afims-heading, #000E3E); }
#pr-root .pr-t-more ul { margin: 10px 0 0 18px; padding: 0; font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
#pr-root .pr-t-more li { margin-bottom: 4px; }
#pr-root .pr-cat-EARNING { border-left: 3px solid var(--afims-success, #11A752); }
#pr-root .pr-cat-REFUND { border-left: 3px solid var(--afims-primary, #0035A9); }
#pr-root .pr-cat-DEDUCTION { border-left: 3px solid var(--afims-warning, #E0A100); }
#pr-root .pr-cat-EMPLOYER_SHARE { border-left: 3px solid var(--afims-danger, #DC3545); }

</style>

<div id="pr-root" class="nha-root">

    <!-- same banner as User Access (app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.28-.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.28.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        <div>
            <h1 class="app-page-title">Payroll Management</h1>
            <p class="app-page-subtitle">Import the monthly employee payroll. The payroll DV is prepared in Disbursement Voucher from an imported month.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="pr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="pr-import-btn" type="button" class="nha-btn-hdr">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Import Payroll
            </button>
        </div>
    </div>

    <div class="nha-tabs no-print">
        <button type="button" class="nha-tab active" data-tab="imports" onclick="pr_switchTab('imports')">Payroll Imports</button>
        <button type="button" class="nha-tab" data-tab="ytd" onclick="pr_switchTab('ytd')">Year-to-Date Report</button>
        <button type="button" class="nha-tab" data-tab="annual" onclick="pr_switchTab('annual')">Annualization</button>
        <button type="button" class="nha-tab" data-tab="template" onclick="pr_switchTab('template')">File Template</button>
    </div>

    <!-- ═══ TAB 1 — PAYROLL IMPORTS (import + import history) ═══ -->
    <div class="pr-pane active" id="pr-pane-imports">
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Imported Months</div><div class="nha-sc-val" id="pr-kpi-imports">—</div></div></div><p class="nha-kpi-sub">In the selected year</p></div>
        <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Payroll (Gross)</div><div class="nha-sc-val" id="pr-kpi-gross">—</div></div></div><p class="nha-kpi-sub">Sum of the imports listed</p></div>
        <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Net Pay</div><div class="nha-sc-val" id="pr-kpi-net">—</div></div></div><p class="nha-kpi-sub">Gross less employee deductions</p></div>
        <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">Awaiting DV</div><div class="nha-sc-val" id="pr-kpi-pending">—</div></div></div><p class="nha-kpi-sub">Imported months with no DV yet</p></div>
    </div>

    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="pr-f-year">Year</label>
                <select id="pr-f-year" class="nha-filter-select"><option value="">All Years</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="pr-f-status">Status</label>
                <select id="pr-f-status" class="nha-filter-select">
                    <option value="">All Statuses</option>
                    <option value="IMPORTED">Imported (no DV yet)</option>
                    <option value="PARTIAL_DV">Partly in a DV</option>
                    <option value="DV_CREATED">DV Created</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button type="button" id="pr-reset" class="nha-btn nha-btn-outline">Reset</button>
            </div>
        </div>
    </div>

    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Payroll Month</th>
                        <th>Year</th>
                        <th>Import Date</th>
                        <th>Status</th>
                        <th class="text-end">Total Payroll Amount</th>
                        <th class="text-end">Net Pay</th>
                        <th class="text-end">Employees</th>
                        <th>Disbursement Voucher</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pr-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <!-- ═══ TAB 2 — YEAR-TO-DATE REPORT ═══ -->
    <div class="pr-pane" id="pr-pane-ytd">
        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-y-year">Fiscal Year</label>
                    <select id="pr-y-year" class="nha-filter-select"></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-y-month">Month</label>
                    <select id="pr-y-month" class="nha-filter-select"><option value="">Whole Year</option></select>
                </div>
                <div class="nha-filter-group nha-filter-group-actions">
                    <button type="button" id="pr-y-export" class="nha-btn nha-btn-outline" disabled>Export CSV</button>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-y-gcg-period">GCG Quarter</label>
                    <select id="pr-y-gcg-period" class="nha-filter-select">
                        <option value="Q1">1st Quarter (Jan – Mar)</option>
                        <option value="Q2">2nd Quarter (Apr – Jun)</option>
                        <option value="Q3">3rd Quarter (Jul – Sep)</option>
                        <option value="Q4">4th Quarter (Oct – Dec)</option>
                    </select>
                </div>
                <div class="nha-filter-group nha-filter-group-actions">
                    <button type="button" id="pr-y-gcg" class="nha-btn nha-btn-primary" title="GCG Quarterly Report for the fiscal year and quarter — PDF, with Export to Excel">GCG Report</button>
                </div>
            </div>
            <p class="pr-hint" id="pr-y-note" style="margin:6px 0 0;display:none;"></p>
        </div>
        <div class="nha-summary" id="pr-y-kpis" style="display:none;">
            <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Employees Paid</div><div class="nha-sc-val" id="pr-y-k-emp">—</div></div></div><p class="nha-kpi-sub" id="pr-y-k-months">—</p></div>
            <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">Gross Pay</div><div class="nha-sc-val" id="pr-y-k-gross">—</div></div></div><p class="nha-kpi-sub" id="pr-y-k-gross-sub">Total earnings</p></div>
            <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">Deductions</div><div class="nha-sc-val" id="pr-y-k-ded">—</div></div></div><p class="nha-kpi-sub">Withheld from pay (net of refunds below)</p></div>
            <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">Net Pay</div><div class="nha-sc-val" id="pr-y-k-net">—</div></div></div><p class="nha-kpi-sub" id="pr-y-k-ref">—</p></div>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>Payroll Month</th>
                            <th class="text-end">Employees</th>
                            <th class="text-end">Gross Pay</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Refunds</th>
                            <th class="text-end">Net Pay</th>
                            <th class="text-end">Employer Share</th>
                        </tr>
                    </thead>
                    <tbody id="pr-y-tbody">
                        <tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Open this tab to load the report.</span></div></td></tr>
                    </tbody>
                    <tfoot id="pr-y-tfoot" class="pr-tfoot"></tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- ═══ TAB 3 — TAX ANNUALIZATION (year-end tax on compensation; PayrollManagementService::annualize()) ═══ -->
    <div class="pr-pane" id="pr-pane-annual">
        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-a-year">Fiscal Year</label>
                    <select id="pr-a-year" class="nha-filter-select"></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-a-search">Search</label>
                    <input type="text" id="pr-a-search" class="nha-filter-input" placeholder="Employee no. or name">
                </div>
                <div class="nha-filter-group nha-filter-group-actions">
                    <button type="button" id="pr-a-run" class="nha-btn nha-btn-primary">Annualize</button>
                    <button type="button" id="pr-a-export" class="nha-btn nha-btn-outline" disabled>Export CSV</button>
                </div>
            </div>
            <p class="pr-hint" id="pr-a-note" style="margin:6px 0 0;">Annualize computes each employee's tax on the year's compensation (TRAIN table) from the imported payroll and compares it with the tax withheld. Run it again after importing or correcting a month.</p>
        </div>
        <div class="nha-summary" id="pr-a-kpis" style="display:none;">
            <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Employees</div><div class="nha-sc-val" id="pr-a-k-emp">—</div></div></div><p class="nha-kpi-sub" id="pr-a-k-when">—</p></div>
            <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">Taxable Income</div><div class="nha-sc-val" id="pr-a-k-taxable">—</div></div></div><p class="nha-kpi-sub" id="pr-a-k-gross">—</p></div>
            <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">Tax Due vs Withheld</div><div class="nha-sc-val" id="pr-a-k-due">—</div></div></div><p class="nha-kpi-sub" id="pr-a-k-withheld">—</p></div>
            <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">Net Adjustment</div><div class="nha-sc-val" id="pr-a-k-adj">—</div></div></div><p class="nha-kpi-sub" id="pr-a-k-status">—</p></div>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th class="text-end">Months</th>
                            <th class="text-end">Gross Compensation</th>
                            <th class="text-end">Non-Taxable</th>
                            <th class="text-end">Taxable Income</th>
                            <th class="text-end">Tax Due</th>
                            <th class="text-end">Tax Withheld</th>
                            <th class="text-end">Adjustment</th>
                            <th class="text-end">Print</th>
                        </tr>
                    </thead>
                    <tbody id="pr-a-tbody">
                        <tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Open this tab to load the annualization.</span></div></td></tr>
                    </tbody>
                    <tfoot id="pr-a-tfoot" class="pr-tfoot"></tfoot>
                </table>
            </div>
        </div>
        <p class="pr-hint" style="margin:8px 0 0;">Non-taxable = PERA, de minimis (each up to its limit under Payroll Setup → De Minimis Limits), 13th month and other benefits up to ₱90,000, and the GSIS / PhilHealth / Pag-IBIG / union dues withheld — by each item's <b>Tax Treatment</b> in Payroll Setup. A positive adjustment is still to be withheld (December payroll); a negative one is to be refunded.</p>
    </div>

    <div class="pr-pane" id="pr-pane-template">
        <!-- 1. How to import: three steps, the download first -->
        <div class="pr-card">
            <h4>How to import a monthly payroll</h4>
            <div class="pr-steps">
                <div class="pr-step">
                    <span class="pr-step-no">1</span>
                    <div>
                        <div class="pr-step-title">Download the import form</div>
                        <div class="pr-sub">An empty copy of the monthly payroll Excel form (<span class="nha-mono" id="pr-t-file">MONTHLY-PAYROLL-TEMPLATE.xlsx</span>).</div>
                        <button type="button" id="pr-t-download" class="nha-btn nha-btn-primary" style="margin-top:10px;">Download Import Form</button>
                    </div>
                </div>
                <div class="pr-step">
                    <span class="pr-step-no">2</span>
                    <div>
                        <div class="pr-step-title">Fill in one row per employee</div>
                        <div class="pr-sub">Employee number and name are required. Type each amount under its column below; leave the columns that don't apply blank. Gross and net pay are computed for you.</div>
                    </div>
                </div>
                <div class="pr-step">
                    <span class="pr-step-no">3</span>
                    <div>
                        <div class="pr-step-title">Upload it in Payroll Imports</div>
                        <div class="pr-sub">Pick the month and year, check the preview, then click Import.</div>
                        <button type="button" class="nha-btn nha-btn-outline" style="margin-top:10px;" onclick="pr_switchTab('imports');pr_open();">Go to Import</button>
                    </div>
                </div>
            </div>
            <div id="pr-t-ready" style="margin-top:14px;"></div>
        </div>

        <!-- 2. Employee details -->
        <div class="pr-card">
            <h4>Employee details</h4>
            <div class="pr-sub" style="margin-bottom:10px;">The first columns of the form identify the employee.</div>
            <div class="nha-tbl-scroll">
                <table class="nha-tbl pr-t-tbl">
                    <thead><tr><th>Column in the form</th><th>What to enter</th><th>Needed?</th></tr></thead>
                    <tbody id="pr-t-identity"><tr><td colspan="3"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr></tbody>
                </table>
            </div>
        </div>

        <!-- 3. Amount columns: one table, filter by type, search -->
        <div class="pr-card">
            <h4>Amount columns</h4>
            <div class="pr-sub" style="margin-bottom:10px;">Each amount column of the form, what it is, and the account it is recorded in. To add or change one, use <a href="<?= htmlspecialchars(GetUrl('PayrollSetupPage')) ?>" target="_blank" rel="noopener">Payroll Setup</a>.</div>
            <div class="pr-t-toolbar">
                <div class="pr-t-pills" id="pr-t-pills"></div>
                <input type="search" id="pr-t-search" class="pr-search" placeholder="Search column, description or account…">
            </div>
            <div class="nha-tbl-scroll pr-t-scroll">
                <table class="nha-tbl pr-t-tbl">
                    <thead><tr><th>Column in the form</th><th>Description</th><th>Type</th><th>Recorded in account</th><th>Status</th></tr></thead>
                    <tbody id="pr-t-items"><tr><td colspan="5"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr></tbody>
                </table>
            </div>
            <div class="pr-sub" id="pr-t-count" style="margin-top:8px;"></div>
        </div>

        <!-- 4. The rules, for whoever prepares the file -->
        <details class="pr-card pr-t-more">
            <summary>Good to know</summary>
            <ul>
                <li>Columns are recognised by their name, so their order in the file doesn't matter. Quotes around a column name (as the HRIS export writes them) are ignored.</li>
                <li>Fill employees from row 2. A row of account codes just under the column names is allowed.</li>
                <li>Don't add Gross or Net Pay columns: they are computed from the amounts.</li>
                <li>A column marked <strong>Not set up</strong> can stay blank. If it has amounts, the import stops until it is added in Payroll Setup.</li>
                <li><strong>Inactive</strong> columns are skipped when importing.</li>
            </ul>
        </details>
    </div>

    <!-- Import modal -->
    <div id="pr-overlay" class="nha-overlay">
        <div class="nha-modal pr-modal u-modal-md" role="dialog" aria-modal="true" aria-labelledby="pr-modal-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="pr-modal-title">Import Monthly Payroll</h3>
                <button type="button" class="nha-modal-close" id="pr-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="pr-form">
                    <div>
                        <label for="pr-month">Payroll Month <span class="nha-req">*</span></label>
                        <select id="pr-month"></select>
                    </div>
                    <div>
                        <label for="pr-year">Payroll Year <span class="nha-req">*</span></label>
                        <select id="pr-year"></select>
                    </div>
                    <div class="pr-full">
                        <label for="pr-file">Payroll File (.xlsx) <span class="nha-req">*</span></label>
                        <input type="file" id="pr-file" accept=".xlsx">
                        <div class="pr-hint">The Regular FIMS MONTHLY PAYROLL template: column headers on row 1 (account codes on row 2 are optional). Each amount column must be a payroll item in Payroll Setup with the same item code.</div>
                    </div>
                </div>
                <div class="pr-preview" id="pr-preview">
                    <dl>
                        <dt>Payroll</dt><dd id="pr-pv-label">—</dd>
                        <dt>Employees</dt><dd id="pr-pv-count">—</dd>
                        <dt>Total Payroll Amount (Gross)</dt><dd id="pr-pv-gross">—</dd>
                        <dt>Employee Deductions</dt><dd id="pr-pv-ded">—</dd>
                        <dt>Refunds to Employees</dt><dd id="pr-pv-ref">—</dd>
                        <dt>Net Pay</dt><dd id="pr-pv-net">—</dd>
                        <dt>Employer Share (reference)</dt><dd id="pr-pv-er">—</dd>
                    </dl>
                </div>
                <div class="pr-msg" id="pr-msg"></div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-outline" id="pr-cancel">Cancel</button>
                <button type="button" class="nha-btn nha-btn-outline" id="pr-preview-btn">Preview</button>
                <button type="button" class="nha-btn nha-btn-primary" id="pr-save" disabled>Import</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   PAYROLL MANAGEMENT   prefix: pr_
═══════════════════════════════════════════════════════════════ */
const PR_API = <?= json_encode($_pr_api) ?>;
const PR_VOUCHER_URL = <?= json_encode(GetUrl('Voucher')) ?>; // DV Processing → Disbursement Voucher (Create DV / View DV)
let pr_rowsById = {}; // payroll_period_id -> summary row of the list
const PR_SETUP_URL = <?= json_encode(GetUrl('PayrollSetupPage')) ?>; // Accounts Set-up → Payroll Setup: the items / accounts the import maps columns to
const PR_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const $pr = id => document.getElementById(id);
let pr_previewed = false;   // the file / month currently in the form was previewed successfully

function pr_authFetch(path, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(PR_API + '/' + path, { credentials: 'same-origin', ...opts, headers });
}
async function pr_json(res) {
    if (res.status === 401) throw new Error('Your session has expired. Please refresh the page and log in again.');
    return res.json().catch(() => ({ success: false, message: 'HTTP ' + res.status }));
}
const pr_peso = v => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pr_esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
const pr_date = s => { if (!s) return '—'; const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? String(s) : d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }); };

/* ── List ───────────────────────────────────────────────────── */
async function pr_load() {
    const params = new URLSearchParams();
    if ($pr('pr-f-year').value) params.set('year', $pr('pr-f-year').value);
    if ($pr('pr-f-status').value) params.set('status', $pr('pr-f-status').value);
    const body = $pr('pr-tbody');
    body.innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/list?' + params));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        pr_fillYears(json.years || []);
        // YTD defaults to the latest year that has an import (until the user picks one)
        if (!pr_ytdYearTouched && json.years?.length) {
            const yy = String(json.years[0]), ySel = $pr('pr-y-year');
            if (![...ySel.options].some(o => o.value === yy)) ySel.insertAdjacentHTML('beforeend', `<option value="${yy}">${yy}</option>`);
            const changed = ySel.value !== yy;
            ySel.value = yy;
            if (changed && $pr('pr-pane-ytd').classList.contains('active')) pr_loadYtd();   // the report tab is open: follow the new default year
        }
        // the Annualization tab follows the latest imported year only when it is one of its two years (current / upcoming)
        if (!pr_annYearTouched && json.years?.length) {
            const yy = String(json.years[0]), aSel = $pr('pr-a-year');
            if ([...aSel.options].some(o => o.value === yy) && aSel.value !== yy) {
                aSel.value = yy;
                if ($pr('pr-pane-annual').classList.contains('active')) pr_loadAnnual();
            }
        }
        const k = json.kpis || {};
        $pr('pr-kpi-imports').textContent = k.imports ?? 0;
        $pr('pr-kpi-gross').textContent   = pr_peso(k.total_gross);
        $pr('pr-kpi-net').textContent     = pr_peso(k.total_net);
        $pr('pr-kpi-pending').textContent = k.without_dv ?? 0;
        const rows = json.data || [];
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No payroll imported yet.</span><div class="nha-empty-text">Use Import Payroll to bring in a month\'s payroll file.</div></div></td></tr>';
            return;
        }
        body.innerHTML = rows.map(r => {
            const dv = r.dv_nos ? `<span class="nha-mono">${pr_esc(r.dv_nos)}</span> <span class="nha-bdg nha-bdg-gray">${pr_esc(r.dv_statuses || '')}</span>` : '<span class="nha-text-muted">—</span>';
            const status = r.import_status === 'DV_CREATED' ? '<span class="nha-bdg nha-bdg-green">DV Created</span>'
                : r.import_status === 'PARTIAL_DV' ? '<span class="nha-bdg nha-bdg-blue">Partly in a DV</span>'
                : '<span class="nha-bdg nha-bdg-amber">Imported</span>';
            pr_rowsById[r.payroll_period_id] = r;
            // one action: the month's payroll DV — Create DV (none yet) or View DV, both on the Disbursement Voucher page
            const action = r.dv_id
                ? `<button type="button" class="nha-btn-act" title="Open DV ${pr_esc(r.dv_nos || '')}" onclick="pr_goDv('dv', ${Number(r.dv_id)})">View DV</button>`
                : `<button type="button" class="nha-btn-act" style="background:var(--afims-success, #11A752);color:#fff;border-color:transparent;" title="Prepare the payroll Disbursement Voucher for this month" onclick="pr_goDv('payroll', ${Number(r.payroll_period_id)})">Create DV</button>`;
            return `<tr>
                <td><strong>${pr_esc(r.month_name)}</strong></td>
                <td>${pr_esc(r.fiscal_yr)}</td>
                <td>${pr_esc(pr_date(r.imported_at))}${r.imported_by_name ? `<div class="pr-hint">by ${pr_esc(r.imported_by_name)}</div>` : ''}</td>
                <td>${status}</td>
                <td class="pr-num">${pr_peso(r.total_gross)}</td>
                <td class="pr-num">${pr_peso(r.total_net)}</td>
                <td class="pr-num">${pr_esc(r.employee_count)}</td>
                <td>${dv}</td>
                <td style="white-space:nowrap;">${action}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        body.innerHTML = `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(e.message)}</span></div></td></tr>`;
    }
}

function pr_fillYears(years) {
    const sel = $pr('pr-f-year'), cur = sel.value;
    const now = new Date().getFullYear();
    const all = [...new Set([...years.map(Number), now])].sort((a, b) => b - a);
    sel.innerHTML = '<option value="">All Years</option>' + all.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = cur;
}

/** Disbursement Voucher page: ?payroll={month} opens a new DV for the month, ?dv={id} opens the DV's view. */
function pr_goDv(param, id) {
    window.location.href = PR_VOUCHER_URL + '?' + param + '=' + encodeURIComponent(id);
}

/* ── Import modal ───────────────────────────────────────────── */
function pr_open() {
    const now = new Date();
    $pr('pr-month').innerHTML = PR_MONTHS.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
    $pr('pr-month').value = String(now.getMonth() + 1);
    const y = now.getFullYear();
    $pr('pr-year').innerHTML = [y + 1, y, y - 1, y - 2].map(v => `<option value="${v}">${v}</option>`).join('');
    $pr('pr-year').value = String(y);
    $pr('pr-file').value = '';
    pr_resetPreview();
    $pr('pr-overlay').classList.add('open');
}
function pr_close() { $pr('pr-overlay').classList.remove('open'); }
function pr_resetPreview() {
    pr_previewed = false;
    // clickable as soon as a file is chosen — Import previews first when that wasn't done (pr_import())
    $pr('pr-save').disabled = !$pr('pr-file').files.length;
    $pr('pr-save').textContent = 'Import';
    $pr('pr-preview').classList.remove('visible');
    pr_msg('', '');
}
function pr_msg(kind, html) {
    const m = $pr('pr-msg');
    m.className = 'pr-msg' + (kind ? ' ' + kind : '');
    m.innerHTML = html;
}
function pr_formData(replace) {
    const fd = new FormData();
    fd.append('payroll_file', $pr('pr-file').files[0]);
    fd.append('payroll_month', $pr('pr-month').value);
    fd.append('payroll_year', $pr('pr-year').value);
    if (replace) fd.append('replace', '1');
    return fd;
}

async function pr_preview() {
    pr_resetPreview();
    if (!$pr('pr-file').files.length) { pr_msg('err', 'Choose the payroll file (.xlsx).'); return; }
    window.PagePreloader?.show();
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/preview', { method: 'POST', body: pr_formData(false) }));
        if (!json.success) {
            pr_msg('err', pr_esc(json.message || 'The file could not be read.') + (json.errors?.length ? '<ul>' + json.errors.map(e => `<li>${pr_esc(e)}</li>`).join('') + '</ul>' : ''));
            return;
        }
        const d = json.data;
        $pr('pr-pv-label').textContent = d.label;
        $pr('pr-pv-count').textContent = d.employee_count;
        $pr('pr-pv-gross').textContent = pr_peso(d.total_gross);
        $pr('pr-pv-ded').textContent   = pr_peso(d.total_deductions);
        $pr('pr-pv-ref').textContent   = pr_peso(d.total_refunds);
        $pr('pr-pv-net').textContent   = pr_peso(d.total_net);
        $pr('pr-pv-er').textContent    = pr_peso(d.total_employer_share);
        $pr('pr-preview').classList.add('visible');

        if (d.existing?.blocked) {
            pr_msg('err', pr_esc(d.existing.blocked));
            return;
        }
        if (d.blocking?.length) {
            pr_msg('err', '<strong>Cannot import yet:</strong><ul>' + d.blocking.map(b => `<li>${pr_esc(b)}</li>`).join('') + '</ul>'
                + `<div style="margin-top:6px;">Add the missing items (item code = column name, with their account) in <a href="${PR_SETUP_URL}" target="_blank" rel="noopener">Payroll Setup</a>, then preview again.</div>`);
            return;
        }
        const replace = !!(d.existing && d.existing.record_count > 0);
        const notes = [];
        if (replace) notes.push(`${pr_esc(d.label)} was already imported — importing replaces it.`);
        if (d.skipped_columns?.length) notes.push('Columns not imported (the payroll item is inactive in Payroll Setup): ' + d.skipped_columns.map(pr_esc).join(', '));
        (d.warnings || []).slice(0, 10).forEach(w => notes.push(pr_esc(w)));
        if (notes.length) pr_msg('warn', '<strong>Check before importing:</strong><ul>' + notes.map(n => `<li>${n}</li>`).join('') + '</ul>');

        pr_previewed = { replace };
        $pr('pr-save').disabled = false;
        $pr('pr-save').textContent = replace ? 'Replace Import' : 'Import';
    } catch (e) {
        pr_msg('err', pr_esc(e.message));
    } finally {
        window.PagePreloader?.hide();
    }
}

async function pr_import() {
    if (!pr_previewed) {
        await pr_preview();            // shows the totals, or why the file cannot be imported
        if (!pr_previewed) return;     // blocked — the message says why
        if (pr_previewed.replace) return; // month already imported — the button now reads "Replace Import": click again to confirm
    }
    $pr('pr-save').disabled = true;
    window.PagePreloader?.show();
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/import', { method: 'POST', body: pr_formData(pr_previewed.replace) }));
        if (!json.success) {
            pr_msg('err', pr_esc(json.message || 'Import failed.') + (json.errors?.length ? '<ul>' + json.errors.map(e => `<li>${pr_esc(e)}</li>`).join('') + '</ul>' : ''));
            $pr('pr-save').disabled = false;
            return;
        }
        pr_close();
        pr_load();
    } catch (e) {
        pr_msg('err', pr_esc(e.message));
        $pr('pr-save').disabled = false;
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Tabs / shared ──────────────────────────────────────────── */
function pr_switchTab(tab) {
    document.querySelectorAll('#pr-root .nha-tabs .nha-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('#pr-root .pr-pane').forEach(p => p.classList.toggle('active', p.id === 'pr-pane-' + tab));
    const onImports = tab === 'imports';
    $pr('pr-import-btn').style.display = onImports ? '' : 'none';
    $pr('pr-refresh').style.display = onImports ? '' : 'none';
    if (tab === 'template') pr_loadTemplate();   // always fresh: items may have changed in Payroll Setup
    if (tab === 'ytd') pr_loadYtd();             // the report follows the filters — no Generate button
    if (tab === 'annual') pr_loadAnnual();       // the stored result of the year; Annualize rebuilds it
}
function pr_closeOverlay(id) { $pr(id).classList.remove('open'); }
const pr_name = r => [r.last_name, [r.first_name, r.middle_name].filter(Boolean).join(' ')].filter(Boolean).join(', ');
const pr_amt = v => `<td class="pr-num">${Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>`;
function pr_pager(infoEl, btnsEl, wrapEl, page, last, total, perPage, go) {
    if (!total) { wrapEl.style.display = 'none'; return; }
    wrapEl.style.display = '';
    infoEl.textContent = `Showing ${(page - 1) * perPage + 1}–${Math.min(page * perPage, total)} of ${total}`;
    const nums = [];
    for (let i = Math.max(1, page - 2); i <= Math.min(last, page + 2); i++) nums.push(i);
    btnsEl.innerHTML = `<button type="button" class="nha-pag-btn${page <= 1 ? ' disabled' : ''}" data-p="${page - 1}">‹</button>`
        + nums.map(i => `<button type="button" class="nha-pag-btn${i === page ? ' active' : ''}" data-p="${i}">${i}</button>`).join('')
        + `<button type="button" class="nha-pag-btn${page >= last ? ' disabled' : ''}" data-p="${page + 1}">›</button>`;
    btnsEl.querySelectorAll('button').forEach(b => b.addEventListener('click', () => {
        const p = Number(b.dataset.p);
        if (p >= 1 && p <= last && p !== page) go(p);
    }));
}

const PR_CAT = { EARNING: 'Earning', REFUND: 'Refund', DEDUCTION: 'Deduction', EMPLOYER_SHARE: 'Employer Share' };
/* ── Year-to-Date report: one row per imported month (no employee details) ── */
let pr_ytdRows = [], pr_ytdMeta = null, pr_ytdYearTouched = false;
let pr_ytdSeq = 0;   // only the latest request may fill the table (fast filter changes can't show an older answer)
function pr_ytdNote(text) {
    const n = $pr('pr-y-note');
    n.textContent = text || '';
    n.style.display = text ? '' : 'none';
}
function pr_initYtdFilters() {
    const y = new Date().getFullYear();
    $pr('pr-y-year').innerHTML = [y + 1, y, y - 1, y - 2, y - 3, y - 4].map(v => `<option value="${v}">${v}</option>`).join('');
    $pr('pr-y-year').value = String(y);
    $pr('pr-y-month').innerHTML = '<option value="">Whole Year</option>' + PR_MONTHS.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
}
async function pr_loadYtd() {
    const f = { year: $pr('pr-y-year').value, month: $pr('pr-y-month').value };
    if (!/^\d{4}$/.test(f.year)) { pr_ytdNote('Choose the fiscal year first.'); return; }
    const params = new URLSearchParams({ year: f.year });
    if (f.month) params.set('month', f.month);
    const seq = ++pr_ytdSeq;
    const body = $pr('pr-y-tbody');
    body.innerHTML = '<tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $pr('pr-y-tfoot').innerHTML = '';
    $pr('pr-y-export').disabled = true;
    pr_ytdNote('');
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/ytd?' + params));
        if (seq !== pr_ytdSeq) return;
        if (!json.success) throw new Error(json.message || 'Failed to build the report.');
        pr_ytdRows = json.data || [];
        pr_ytdMeta = { filters: f, totals: json.totals || {}, months: json.months_imported || [],
                       label: f.month ? `${PR_MONTHS[f.month - 1]} ${f.year}` : `Whole Year ${f.year}` };
        const t = pr_ytdMeta.totals;
        $pr('pr-y-kpis').style.display = '';
        $pr('pr-y-k-emp').textContent = t.employees ?? 0;
        $pr('pr-y-k-months').textContent = pr_ytdMeta.months.length ? 'Imported: ' + pr_ytdMeta.months.join(', ') : 'No month imported for ' + f.year;
        $pr('pr-y-k-gross').textContent = pr_peso(t.gross);
        $pr('pr-y-k-gross-sub').textContent = f.month ? `Total earnings, ${PR_MONTHS[f.month - 1]}` : 'Total earnings, year-to-date';
        $pr('pr-y-k-ded').textContent = pr_peso(t.deductions);
        $pr('pr-y-k-net').textContent = pr_peso(t.net);
        $pr('pr-y-k-ref').textContent = 'Refunds included: ' + pr_peso(t.refunds);
        $pr('pr-y-export').disabled = !pr_ytdRows.length;
        pr_renderYtd();
    } catch (e) {
        if (seq !== pr_ytdSeq) return;
        pr_ytdRows = [];
        pr_ytdMeta = null;
        $pr('pr-y-kpis').style.display = 'none';
        body.innerHTML = `<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(e.message)}</span></div></td></tr>`;
    }
}
function pr_renderYtd() {
    const body = $pr('pr-y-tbody'), m = pr_ytdMeta, t = m.totals;
    if (!pr_ytdRows.length) {
        const msg = !m.months.length
            ? `No payroll has been imported for ${m.filters.year} yet. Import a month in Payroll Imports, or choose another fiscal year.`
            : `No payroll imported for ${m.label}. Imported months: ${m.months.join(', ')}.`;
        body.innerHTML = `<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(msg)}</span></div></td></tr>`;
        $pr('pr-y-tfoot').innerHTML = '';
        return;
    }
    body.innerHTML = pr_ytdRows.map(r => `<tr>
            <td><strong>${pr_esc(r.month_name)}</strong> ${pr_esc(m.filters.year)}</td>
            <td class="pr-num">${pr_esc(r.employees)}</td>
            ${pr_amt(r.gross)}${pr_amt(r.deductions)}${pr_amt(r.refunds)}${pr_amt(r.net)}${pr_amt(r.employer_share)}
        </tr>`).join('');
    $pr('pr-y-tfoot').innerHTML = pr_ytdRows.length > 1
        ? `<tr><td>TOTAL — ${pr_esc(String(t.months))} month(s)</td><td class="pr-num">${pr_esc(String(t.employees))}</td>${pr_amt(t.gross)}${pr_amt(t.deductions)}${pr_amt(t.refunds)}${pr_amt(t.net)}${pr_amt(t.employer_share)}</tr>`
        : '';
}
/* Export CSV: the employee details behind the report on screen (same year / month) — one row per employee with their
   details and totals for the period, then the TOTAL row. The screen itself shows only the per-month summary. */
async function pr_exportYtd() {
    if (!pr_ytdMeta || !pr_ytdRows.length) { pr_ytdNote('Nothing to export — choose a year or month that has an imported payroll.'); return; }
    const m = pr_ytdMeta, btn = $pr('pr-y-export');
    const params = new URLSearchParams({ year: m.filters.year, detail: 'employees' });
    if (m.filters.month) params.set('month', m.filters.month);
    btn.disabled = true;
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/ytd?' + params));
        if (!json.success) throw new Error(json.message || 'Failed to export.');
        const rows = json.data || [], t = json.totals || {};
        if (!rows.length) { pr_ytdNote('Nothing to export for ' + m.label + '.'); return; }
        // Only what the import fills: position, employment status, TIN, Pag-IBIG and PhilHealth numbers are not in the
        // monthly payroll file (always blank / always ACTIVE), so they are left out.
        const cols = [['employee_no', 'Emp No.'], ['last_name', 'Last Name'], ['first_name', 'First Name'], ['middle_name', 'Middle Name'],
            ['department', 'Department'], ['months', 'Months'],
            ['ytd_gross', 'Gross Pay'], ['ytd_deductions', 'Deductions'], ['ytd_refunds', 'Refunds'], ['ytd_net', 'Net Pay'], ['ytd_employer_share', 'Employer Share']];
        const money = new Set(['ytd_gross', 'ytd_deductions', 'ytd_refunds', 'ytd_net', 'ytd_employer_share']);
        // a text cell starting with = + - @ would run as a formula in Excel — prefix it with ' (amounts stay numbers)
        const q = (v, isNum) => {
            let s = v == null ? '' : String(v);
            if (!isNum && /^[=+\-@]/.test(s)) s = "'" + s;
            return /[",\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
        };
        const cell = (r, k) => money.has(k) ? Number(r[k] || 0).toFixed(2) : q(r[k], k === 'months');
        const total = cols.map(([k]) => k === 'employee_no' ? 'TOTAL' : k === 'last_name' ? q(`${t.employees} employee(s)`) : money.has(k) ? Number(t[k] || 0).toFixed(2) : '').join(',');
        const lines = [q(`Payroll Year-to-Date Report — ${m.label}`), cols.map(c => q(c[1])).join(','),
            ...rows.map(r => cols.map(([k]) => cell(r, k)).join(',')), total];
        const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'Payroll-YTD-' + m.label.replace(/\s+/g, '-') + '.csv';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    } catch (e) {
        pr_ytdNote(e.message);
    } finally {
        btn.disabled = !pr_ytdRows.length;
    }
}

/* ── File template ──────────────────────────────────────────── */
let pr_tCols = [], pr_tCat = '';
// what to type in each employee-detail column (by the label the server gives it)
const PR_T_HINT = {
    'Employee Number': 'The employee number, e.g. 10251',
    'Department Code': 'The department code, e.g. ACC',
    'Last Name': 'Surname',
    'First Name': 'Given name',
    'Middle Initial': 'Middle initial, e.g. B',
};
const PR_T_STATUS = { ACTIVE: 'Ready', INACTIVE: 'Inactive — skipped', MISSING: 'Not set up', EXTRA: 'Ready — not in the form' };
async function pr_loadTemplate() {
    const box = $pr('pr-t-items');
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/template-columns'));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        if (json.form_file) $pr('pr-t-file').textContent = json.form_file;

        // Employee details
        $pr('pr-t-identity').innerHTML = (json.identity || []).map(c => `<tr>
                <td class="nha-mono">${pr_esc(c.column)}</td>
                <td>${pr_esc(PR_T_HINT[c.label] || c.label)}</td>
                <td>${c.required ? '<span class="pr-badge req">Required</span>' : '<span class="pr-badge opt">Optional</span>'}</td>
            </tr>`).join('');

        // One list of amount columns: the form's columns (with their Payroll Setup status) plus any active item the
        // downloaded form doesn't have yet. The account title comes from the active items.
        const items = json.items || [], byCode = new Map(items.map(i => [String(i.item_code).toUpperCase(), i]));
        const seen = new Set();
        pr_tCols = (json.form_columns || []).map(c => {
            const key = String(c.column).toUpperCase(), it = byCode.get(key);
            seen.add(key);
            const st = c.status === 'ACTIVE' || c.status === 'INACTIVE' ? c.status : 'MISSING';
            return { code: c.column, name: c.item_name || it?.item_name || '', category: c.category || it?.category || '',
                     account: c.account_code || it?.account_code || '', title: it?.account_title || '', status: st };
        });
        items.filter(i => !seen.has(String(i.item_code).toUpperCase())).forEach(i => pr_tCols.push({
            code: i.item_code, name: i.item_name, category: i.category, account: i.account_code || '', title: i.account_title || '', status: 'EXTRA',
        }));

        // Readiness: can a file filled on this form be imported as it is?
        const missing = pr_tCols.filter(c => c.status === 'MISSING');
        const ready = $pr('pr-t-ready');
        if (!pr_tCols.length) {
            ready.innerHTML = `<div class="pr-ready warn">⚠ No payroll items are set up yet, so nothing can be imported. Add them in <a href="${PR_SETUP_URL}" target="_blank" rel="noopener">Payroll Setup</a> first.</div>`;
        } else if (missing.length) {
            ready.innerHTML = `<div class="pr-ready warn">⚠ ${missing.length} column(s) of the form are not set up yet: ${missing.slice(0, 8).map(c => pr_esc(c.code)).join(', ')}${missing.length > 8 ? '…' : ''}. Leave them blank, or add them in <a href="${PR_SETUP_URL}" target="_blank" rel="noopener">Payroll Setup</a> before importing amounts in them.</div>`;
        } else {
            const n = pr_tCols.filter(c => c.status === 'ACTIVE' || c.status === 'EXTRA').length;
            ready.innerHTML = `<div class="pr-ready ok">✓ The form is ready: all ${n} amount columns are set up in Payroll Setup.</div>`;
        }

        // Type filter pills with counts
        const count = cat => pr_tCols.filter(c => !cat || c.category === cat).length;
        $pr('pr-t-pills').innerHTML = [['', 'All'], ...Object.entries(PR_CAT)].filter(([cat]) => !cat || count(cat))
            .map(([cat, label]) => `<button type="button" class="pr-t-pill${cat === pr_tCat ? ' active' : ''}" data-cat="${cat}">${pr_esc(label)} <strong>${count(cat)}</strong></button>`).join('');
        $pr('pr-t-pills').querySelectorAll('.pr-t-pill').forEach(b => b.addEventListener('click', () => {
            pr_tCat = b.dataset.cat;
            $pr('pr-t-pills').querySelectorAll('.pr-t-pill').forEach(x => x.classList.toggle('active', x === b));
            pr_renderTemplateCols();
        }));
        pr_renderTemplateCols();
    } catch (e) {
        box.innerHTML = `<tr><td colspan="5"><div class="pr-msg err" style="display:block;">${pr_esc(e.message)}</div></td></tr>`;
    }
}
function pr_renderTemplateCols() {
    const q = $pr('pr-t-search').value.trim().toLowerCase();
    const list = pr_tCols.filter(c => (!pr_tCat || c.category === pr_tCat)
        && (!q || [c.code, c.name, c.account, c.title].some(v => String(v || '').toLowerCase().includes(q))));
    $pr('pr-t-items').innerHTML = list.length ? list.map(c => `<tr>
            <td class="nha-mono">${pr_esc(c.code)}</td>
            <td>${pr_esc(c.name || '—')}</td>
            <td>${c.category ? `<span class="pr-badge ${pr_esc(c.category)}">${pr_esc(PR_CAT[c.category] || c.category)}</span>` : '—'}</td>
            <td>${c.account ? `<span class="nha-mono">${pr_esc(c.account)}</span>${c.title ? `<div class="pr-hint" style="margin-top:2px;">${pr_esc(c.title)}</div>` : ''}` : '<span class="pr-hint">No account</span>'}</td>
            <td><span class="pr-badge st-${c.status === 'EXTRA' ? 'ACTIVE' : c.status}">${PR_T_STATUS[c.status]}</span></td>
        </tr>`).join('')
        : '<tr><td colspan="5"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No column matches the search.</span></div></td></tr>';
    $pr('pr-t-count').textContent = `Showing ${list.length} of ${pr_tCols.length} amount columns.`;
}
async function pr_downloadTemplate() {
    try {
        const res = await pr_authFetch('payroll-management/template');
        if (!res.ok) { const j = await pr_json(res); throw new Error(j.message || 'Download failed.'); }
        const blob = await res.blob();
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'MONTHLY-PAYROLL-TEMPLATE.xlsx';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    } catch (e) {
        alert(e.message);
    }
}

/* ── Tax annualization: one row per employee of the year (fims.payroll_annualization) ── */
let pr_annRows = [], pr_annMeta = null, pr_annSeq = 0, pr_annYearTouched = false;
function pr_annNote(text, bad = false) {
    const n = $pr('pr-a-note');
    n.textContent = text;
    n.style.color = bad ? 'var(--afims-danger, #B42318)' : '';
}
async function pr_loadAnnual() {
    const year = $pr('pr-a-year').value;
    const seq = ++pr_annSeq;
    $pr('pr-a-tbody').innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/annualization?year=' + encodeURIComponent(year)));
        if (seq !== pr_annSeq) return;
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        pr_annRows = json.data || [];
        pr_annMeta = { year, totals: json.totals || {}, computedAt: json.computed_at, untreated: json.untreated_items || 0 };
        const t = pr_annMeta.totals;
        $pr('pr-a-kpis').style.display = pr_annRows.length ? '' : 'none';
        $pr('pr-a-k-emp').textContent = t.employees ?? 0;
        $pr('pr-a-k-when').textContent = pr_annMeta.computedAt ? 'Annualized ' + pr_date(pr_annMeta.computedAt) : '—';
        $pr('pr-a-k-taxable').textContent = pr_peso(t.taxable_income);
        $pr('pr-a-k-gross').textContent = 'Gross ' + pr_peso(t.gross_compensation) + ' · non-taxable ' + pr_peso(t.total_non_taxable);
        $pr('pr-a-k-due').textContent = pr_peso(t.tax_due);
        $pr('pr-a-k-withheld').textContent = 'Withheld ' + pr_peso(t.tax_withheld);
        $pr('pr-a-k-adj').textContent = pr_peso(t.tax_adjustment);
        $pr('pr-a-k-status').textContent = `${t.underpaid ?? 0} underpaid · ${t.overpaid ?? 0} overpaid`;
        $pr('pr-a-export').disabled = !pr_annRows.length;
        pr_annNote(!pr_annRows.length
            ? `Not annualized yet for ${year} — click Annualize (it uses the imported payroll of ${year}).`
            : (pr_annMeta.untreated ? `${pr_annMeta.untreated} earning / refund item(s) paid in ${year} have no Tax Treatment in Payroll Setup, so they were left out — set them, then Annualize again.` : 'Annualize again after importing or correcting a month.'),
            !!pr_annMeta.untreated);
        pr_renderAnnual();
    } catch (e) {
        if (seq !== pr_annSeq) return;
        pr_annRows = [];
        $pr('pr-a-kpis').style.display = 'none';
        $pr('pr-a-tbody').innerHTML = `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(e.message)}</span></div></td></tr>`;
        $pr('pr-a-tfoot').innerHTML = '';
    }
}
function pr_renderAnnual() {
    const q = $pr('pr-a-search').value.trim().toLowerCase();
    const rows = pr_annRows.filter(r => (!q ||[r.employee_no, r.last_name, r.first_name, r.middle_name].some(v => String(v || '').toLowerCase().includes(q))));
    const body = $pr('pr-a-tbody');
    if (!rows.length) {
        body.innerHTML = `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_annRows.length ? 'No employee matches the filters.' : 'Nothing annualized for ' + pr_esc(pr_annMeta?.year || '') + ' yet.'}</span></div></td></tr>`;
        $pr('pr-a-tfoot').innerHTML = '';
        return;
    }
    body.innerHTML = rows.map(r => `<tr>
        <td><div style="font-weight:600;">${pr_esc(pr_name(r))}</div><div class="pr-sub">${pr_esc(r.employee_no)}${r.tin ? ' · TIN ' + pr_esc(r.tin) : ''}</div></td>
        <td class="pr-num">${r.months_paid}</td>${pr_amt(r.gross_compensation)}${pr_amt(r.total_non_taxable)}${pr_amt(r.taxable_income)}
        ${pr_amt(r.tax_due)}${pr_amt(r.tax_withheld)}${pr_amt(r.tax_adjustment)}
        <td class="text-end" style="white-space:nowrap;"><button type="button" class="nha-btn nha-btn-outline nha-btn-sm" onclick="pr_printWorksheet(${Number(r.employee_id)})" title="Annualization of Income for the year — PDF, with Export to Excel">Annualization of Income</button>
            <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" onclick="pr_print2316(${Number(r.employee_id)})" title="BIR Form 2316 — Certificate of Compensation Payment / Tax Withheld">2316</button></td></tr>`).join('');
    const sum = k => rows.reduce((s, r) => s + Number(r[k] || 0), 0);
    $pr('pr-a-tfoot').innerHTML = `<tr><td>Total (${rows.length})</td><td></td>${pr_amt(sum('gross_compensation'))}${pr_amt(sum('total_non_taxable'))}${pr_amt(sum('taxable_income'))}
        ${pr_amt(sum('tax_due'))}${pr_amt(sum('tax_withheld'))}${pr_amt(sum('tax_adjustment'))}<td></td></tr>`;
}
/** An employee's annualization worksheet (the client's template) for the year shown — PDF preview with Export to Excel (AnnualizationPrintService). */
/** GCG report of the Year-to-Date tab's fiscal year and the chosen period — the shared print preview (GcgReportPrintService). */
function pr_printGcg() {
    const year = $pr('pr-y-year').value;
    if (!/^\d{4}$/.test(year)) { pr_ytdNote('Choose the fiscal year first.'); return; }
    if (typeof prp_open === 'function') prp_open({ fiscal_yr: Number(year), period: $pr('pr-y-gcg-period').value }, { type: 'GCG' });
}
function pr_printWorksheet(employeeId) {
    if (typeof prp_open === 'function' && pr_annMeta) prp_open({ fiscal_yr: Number(pr_annMeta.year), employee_id: employeeId }, { type: 'ANNUALWS' });
}
/** An employee's BIR 2316 for the year shown — the shared print preview opens on its Edit Form (Bir2316PrintService). */
function pr_print2316(employeeId) {
    if (typeof prp_open === 'function' && pr_annMeta) prp_open({ fiscal_yr: Number(pr_annMeta.year), employee_id: employeeId }, { type: 'BIR2316' });
}
async function pr_runAnnual() {
    const year = $pr('pr-a-year').value, btn = $pr('pr-a-run');
    if (!confirm(`Annualize ${year}? Every employee's ${year} annualization is rebuilt from the imported payroll.`)) return;
    btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/annualize', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ year }) }));
        if (!json.success) throw new Error(json.message || 'The annualization failed.');
        await pr_loadAnnual();
        pr_annNote(json.message);
    } catch (e) {
        pr_annNote(e.message, true);
    } finally {
        btn.disabled = false;
        window.PagePreloader?.hide();
    }
}
function pr_exportAnnual() {
    if (!pr_annRows.length) return;
    const cols = [['employee_no', 'Emp No.'], ['last_name', 'Last Name'], ['first_name', 'First Name'], ['middle_name', 'Middle Name'], ['tin', 'TIN'],
        ['months_paid', 'Months'], ['taxable_pay', 'Taxable Pay'], ['non_taxable_pay', 'Non-Taxable Pay (PERA)'], ['de_minimis', 'De Minimis'],
        ['de_minimis_exempt', 'De Minimis Exempt'], ['benefits', '13th Month & Other Benefits'], ['benefits_exempt', 'Benefits Exempt (≤90k)'],
        ['contributions', 'Contributions'], ['gross_compensation', 'Gross Compensation'], ['total_non_taxable', 'Total Non-Taxable'],
        ['taxable_income', 'Taxable Income'], ['tax_due', 'Tax Due'], ['tax_withheld', 'Tax Withheld'], ['tax_adjustment', 'Adjustment']];
    const cell = v => /[",\n]/.test(String(v ?? '')) ? '"' + String(v).replace(/"/g, '""') + '"' : String(v ?? '');
    const csv = [cols.map(c => c[1]).join(',')].concat(pr_annRows.map(r => cols.map(c => cell(r[c[0]])).join(','))).join('\r\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' }));
    a.download = 'Tax-Annualization-' + pr_annMeta.year + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/* ── Boot ───────────────────────────────────────────────────── */
function pr_boot() {
    pr_initYtdFilters();
    // Annualization: only the current and the upcoming fiscal year (default: the current one)
    const pr_annThisYear = new Date().getFullYear();
    $pr('pr-a-year').innerHTML = [pr_annThisYear, pr_annThisYear + 1].map(v => `<option value="${v}">${v}</option>`).join('');
    $pr('pr-a-year').value = String(pr_annThisYear);
    $pr('pr-a-year').addEventListener('change', () => { pr_annYearTouched = true; pr_loadAnnual(); });
    $pr('pr-a-search').addEventListener('input', pr_renderAnnual);
    $pr('pr-a-run').addEventListener('click', pr_runAnnual);
    $pr('pr-a-export').addEventListener('click', pr_exportAnnual);
    $pr('pr-y-year').addEventListener('change', () => { pr_ytdYearTouched = true; pr_loadYtd(); });
    $pr('pr-y-month').addEventListener('change', pr_loadYtd);
    $pr('pr-y-export').addEventListener('click', pr_exportYtd);
    $pr('pr-y-gcg').addEventListener('click', pr_printGcg);
    $pr('pr-y-gcg-period').value = 'Q' + Math.ceil((new Date().getMonth() + 1) / 3);
    $pr('pr-t-download').addEventListener('click', pr_downloadTemplate);
    $pr('pr-t-search').addEventListener('input', pr_renderTemplateCols);
    $pr('pr-refresh').addEventListener('click', pr_load);
    $pr('pr-f-year').addEventListener('change', pr_load);
    $pr('pr-f-status').addEventListener('change', pr_load);
    $pr('pr-reset').addEventListener('click', () => { $pr('pr-f-year').value = ''; $pr('pr-f-status').value = ''; pr_load(); });
    $pr('pr-import-btn').addEventListener('click', pr_open);
    $pr('pr-close').addEventListener('click', pr_close);
    $pr('pr-cancel').addEventListener('click', pr_close);
    $pr('pr-preview-btn').addEventListener('click', pr_preview);
    $pr('pr-save').addEventListener('click', pr_import);
    // choosing the file / month / year previews right away (when a file is chosen)
    ['pr-month', 'pr-year', 'pr-file'].forEach(id => $pr(id).addEventListener('change', () => {
        pr_resetPreview();
        if ($pr('pr-file').files.length) pr_preview();
    }));
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const open = ['pr-overlay'].find(id => $pr(id).classList.contains('open'));
        if (open) pr_closeOverlay(open);
    });
    pr_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pr_boot);
else pr_boot();
</script>
<?php
// Annualization → each employee's BIR 2316 through the shared print preview (prp_open({ fiscal_yr, employee_id }, { type: 'BIR2316' })).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
