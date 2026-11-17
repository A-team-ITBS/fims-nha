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
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
#pr-root .nha-summary { grid-template-columns: repeat(4, 1fr); }
#pr-root .pr-num { text-align: right; font-family: 'IBM Plex Mono', monospace; white-space: nowrap; }
#pr-root .pr-modal { max-width: 640px; }
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
#pr-root .pr-month-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
#pr-root .pr-month-link:hover { color: var(--afims-primary, #0035A9); }
#pr-root .pr-pane { display: none; }
#pr-root .pr-pane.active { display: block; }
#pr-root .pr-modal-xl { max-width: 1200px; width: 96%; }
#pr-root .pr-modal-md { max-width: 760px; width: 94%; }
#pr-root .pr-modal-body-scroll { max-height: 70vh; overflow: auto; }
#pr-root .pr-sub { font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
#pr-root .pr-tfoot td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); }
#pr-root .pr-search { padding: 7px 10px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 6px; font: inherit; min-width: 240px; background: var(--afims-surface, #fff); color: inherit; }
#pr-root .pr-card { border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px; padding: 14px 16px; margin-bottom: 14px; background: var(--afims-surface, #fff); }
#pr-root .pr-card h4 { margin: 0 0 10px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-heading, #000E3E); }
#pr-root .pr-chips { display: flex; flex-wrap: wrap; gap: 6px; }
#pr-root .pr-chip { font-size: 11.5px; padding: 3px 8px; border-radius: 12px; background: var(--afims-surface-subtle, #F4F7FA); border: 1px solid var(--afims-border, #E2E4ED); font-family: 'IBM Plex Mono', monospace; }
#pr-root .pr-chip small { font-family: 'IBM Plex Sans', sans-serif; color: var(--afims-text-muted, #62657E); margin-left: 4px; }
#pr-root .pr-cat-EARNING { border-left: 3px solid var(--afims-success, #11A752); }
#pr-root .pr-cat-REFUND { border-left: 3px solid var(--afims-primary, #0035A9); }
#pr-root .pr-cat-DEDUCTION { border-left: 3px solid var(--afims-warning, #E0A100); }
#pr-root .pr-cat-EMPLOYER_SHARE { border-left: 3px solid var(--afims-danger, #DC3545); }
</style>

<div id="pr-root" class="nha-root">

    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.28-.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.28.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Payroll Management</div>
                <div class="nha-hdr-sub">Import the monthly employee payroll. The payroll DV is prepared in Disbursement Voucher from an imported month.</div>
            </div>
        </div>
        <div class="nha-hdr-r">
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
                    <label class="nha-filter-label" for="pr-y-month">As of Month</label>
                    <select id="pr-y-month" class="nha-filter-select"><option value="">Full Year</option></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-y-dept">Department</label>
                    <select id="pr-y-dept" class="nha-filter-select"><option value="">All Departments</option></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-y-status">Employment Status</label>
                    <select id="pr-y-status" class="nha-filter-select">
                        <option value="">All Statuses</option>
                        <option value="ACTIVE">Active</option>
                        <option value="ON_LEAVE">On Leave</option>
                        <option value="SEPARATED">Separated</option>
                        <option value="RETIRED">Retired</option>
                    </select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="pr-y-search">Search</label>
                    <input type="text" id="pr-y-search" class="pr-search" placeholder="Employee no. or name…">
                </div>
                <div class="nha-filter-group nha-filter-group-actions">
                    <button type="button" id="pr-y-load" class="nha-btn nha-btn-primary">Generate Report</button>
                    <button type="button" id="pr-y-export" class="nha-btn nha-btn-outline" disabled>Export CSV</button>
                </div>
            </div>
        </div>
        <div class="nha-summary" id="pr-y-kpis" style="display:none;">
            <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Employees</div><div class="nha-sc-val" id="pr-y-k-emp">—</div></div></div><p class="nha-kpi-sub" id="pr-y-k-months">—</p></div>
            <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">YTD Gross Pay</div><div class="nha-sc-val" id="pr-y-k-gross">—</div></div></div><p class="nha-kpi-sub">Total earnings, year-to-date</p></div>
            <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">YTD Deductions</div><div class="nha-sc-val" id="pr-y-k-ded">—</div></div></div><p class="nha-kpi-sub">Withheld from pay (net of refunds below)</p></div>
            <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">YTD Net Pay</div><div class="nha-sc-val" id="pr-y-k-net">—</div></div></div><p class="nha-kpi-sub" id="pr-y-k-ref">—</p></div>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>Emp No.</th>
                            <th>Employee Name</th>
                            <th>Position / Department</th>
                            <th>Status</th>
                            <th>TIN</th>
                            <th>Pag-IBIG</th>
                            <th>PhilHealth</th>
                            <th class="text-end">Months</th>
                            <th class="text-end">YTD Gross Pay</th>
                            <th class="text-end">YTD Deductions</th>
                            <th class="text-end">YTD Refunds</th>
                            <th class="text-end">YTD Net Pay</th>
                            <th class="text-end">YTD Employer Share</th>
                        </tr>
                    </thead>
                    <tbody id="pr-y-tbody">
                        <tr><td colspan="13"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Choose the filters and click Generate Report.</span></div></td></tr>
                    </tbody>
                    <tfoot id="pr-y-tfoot" class="pr-tfoot"></tfoot>
                </table>
            </div>
            <div class="nha-pag-wrap" id="pr-y-pag" style="display:none;">
                <span class="nha-pag-info" id="pr-y-pag-info"></span>
                <div class="nha-pag-btns" id="pr-y-pag-btns"></div>
            </div>
        </div>
    </div>

    <!-- ═══ TAB 3 — FILE TEMPLATE ═══ -->
    <div class="pr-pane" id="pr-pane-template">
        <div class="pr-card">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
                <div>
                    <h4 style="margin-bottom:4px;">FIMS Monthly Payroll import form</h4>
                    <div class="pr-sub">The download is the actual import form (<span class="nha-mono" id="pr-t-file">MONTHLY-PAYROLL-TEMPLATE.xlsx</span>, sheet "MONTHLY PAYROLL") with no employee rows. Fill one row per employee from row 2 (a row of account codes under the headers is also accepted). Each column is recognised by its name, so the order does not matter. There is no Gross / Net column: they are computed from the items.</div>
                </div>
                <button type="button" id="pr-t-download" class="nha-btn nha-btn-primary">Download Import Form</button>
            </div>
        </div>
        <div class="pr-card">
            <h4>Required on every employee row</h4>
            <div class="pr-chips" id="pr-t-identity"></div>
        </div>
        <div class="pr-card">
            <h4>Columns of the import form vs Payroll Setup</h4>
            <div class="pr-sub" style="margin-bottom:10px;">Every amount column of the form. A column <strong>not set up</strong> can stay blank; once it has amounts in a file, the import stops until it is added in <a href="<?= htmlspecialchars(GetUrl('PayrollSetupPage')) ?>" target="_blank" rel="noopener">Payroll Setup</a>.</div>
            <div id="pr-t-form"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></div>
        </div>
        <div class="pr-card">
            <h4>Amount columns the import recognises</h4>
            <div class="pr-sub" style="margin-bottom:10px;">These are the <strong>active items in <a href="<?= htmlspecialchars(GetUrl('PayrollSetupPage')) ?>" target="_blank" rel="noopener">Payroll Setup</a></strong> — each item's code is the column name and its account is what the imported amounts post to. A column name in the file must equal the item code (the quotes the HRIS export puts around it are ignored). A column that has amounts but is not here stops the import — add it in Payroll Setup first. Inactive items are skipped.</div>
            <div id="pr-t-items"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></div>
        </div>
    </div>

    <!-- Employee records of an imported month -->
    <div id="pr-rec-overlay" class="nha-overlay">
        <div class="nha-modal pr-modal-xl" role="dialog" aria-modal="true" aria-labelledby="pr-rec-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="pr-rec-title">Employee Records</h3>
                <button type="button" class="nha-modal-close" onclick="pr_closeOverlay('pr-rec-overlay')" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body pr-modal-body-scroll">
                <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:10px;flex-wrap:wrap;">
                    <input type="text" id="pr-rec-search" class="pr-search" placeholder="Search employee no., name, department…">
                    <span style="display:flex;align-items:center;gap:10px;">
                        <span class="pr-sub" id="pr-rec-info"></span>
                        <button type="button" id="pr-rec-delete" class="nha-btn nha-btn-outline" style="display:none;color:var(--afims-danger, #DC3545);" title="Delete this month's imported payroll">Delete Import</button>
                    </span>
                </div>
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>Emp No.</th>
                            <th>Employee Name</th>
                            <th>Department</th>
                            <th class="text-end">Gross Pay</th>
                            <th class="text-end">Deductions</th>
                            <th class="text-end">Refunds</th>
                            <th class="text-end">Net Pay</th>
                            <th class="text-end">Employer Share</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="pr-rec-tbody"></tbody>
                </table>
                <div class="nha-pag-wrap" id="pr-rec-pag" style="display:none;">
                    <span class="nha-pag-info" id="pr-rec-pag-info"></span>
                    <div class="nha-pag-btns" id="pr-rec-pag-btns"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- One employee's payroll items -->
    <div id="pr-items-overlay" class="nha-overlay">
        <div class="nha-modal pr-modal-md" role="dialog" aria-modal="true" aria-labelledby="pr-items-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="pr-items-title">Payroll Items</h3>
                <button type="button" class="nha-modal-close" onclick="pr_closeOverlay('pr-items-overlay')" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body pr-modal-body-scroll">
                <table class="nha-tbl">
                    <thead><tr><th>Category</th><th>Item</th><th>Account</th><th class="text-end">Amount</th></tr></thead>
                    <tbody id="pr-items-tbody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Import modal -->
    <div id="pr-overlay" class="nha-overlay">
        <div class="nha-modal pr-modal" role="dialog" aria-modal="true" aria-labelledby="pr-modal-title">
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
            ySel.value = yy;
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
                <td><a href="#" class="pr-month-link" title="View the employee records" onclick="pr_openRecords(${Number(r.payroll_period_id)}); return false;"><strong>${pr_esc(r.month_name)}</strong></a></td>
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

/** Delete — from the employee records window (only while no DV uses the month and the period is open). */
async function pr_delete(id, label) {
    if (!confirm(`Delete the imported ${label} payroll (all its employee records)? It can be imported again afterwards.`)) return;
    const json = await pr_json(await pr_authFetch('payroll-management/delete/' + id, { method: 'DELETE' }));
    if (!json.success) { alert(json.message || 'Failed to delete.'); return; }
    pr_closeOverlay('pr-rec-overlay');
    pr_load();
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
    $pr('pr-save').disabled = true;
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
    if (!pr_previewed) return;
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

/* ── Employee records of an imported month ──────────────────── */
let pr_recPeriod = 0, pr_recTimer = null;
function pr_openRecords(periodId) {
    pr_recPeriod = periodId;
    $pr('pr-rec-search').value = '';
    // Delete Import: only while no DV uses the month and its period is still open (the server checks again)
    const r = pr_rowsById[periodId];
    const locked = ['LOCKED', 'CLOSED'].includes(String(r?.period_status || '').toUpperCase());
    const del = $pr('pr-rec-delete');
    del.style.display = r && !r.dv_id && !locked ? '' : 'none';
    del.onclick = () => pr_delete(periodId, `${r?.month_name || ''} ${r?.fiscal_yr || ''}`.trim());
    $pr('pr-rec-overlay').classList.add('open');
    pr_loadRecords(1);
}
async function pr_loadRecords(page) {
    const body = $pr('pr-rec-tbody');
    body.innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    const params = new URLSearchParams({ page, limit: 25 });
    if ($pr('pr-rec-search').value.trim()) params.set('search', $pr('pr-rec-search').value.trim());
    try {
        const json = await pr_json(await pr_authFetch(`payroll-management/records/${pr_recPeriod}?${params}`));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        $pr('pr-rec-title').textContent = 'Employee Records — ' + (json.period?.label || '');
        const pg = json.pagination || {};
        $pr('pr-rec-info').textContent = `${pg.total || 0} employee(s)`;
        const rows = json.data || [];
        body.innerHTML = rows.length ? rows.map(r => `<tr>
                <td class="nha-mono">${pr_esc(r.employee_no)}</td>
                <td><strong>${pr_esc(pr_name(r))}</strong>${r.dv_no ? `<div class="pr-hint">DV ${pr_esc(r.dv_no)}</div>` : ''}</td>
                <td>${pr_esc(r.department || '—')}</td>
                ${pr_amt(r.gross_amount)}${pr_amt(r.total_deductions)}${pr_amt(r.total_refunds)}${pr_amt(r.net_amount)}${pr_amt(r.total_employer_share)}
                <td><button type="button" class="nha-btn-act" onclick="pr_openItems(${Number(r.payroll_record_id)}, '${pr_esc(String(r.employee_no)).replace(/'/g, '&#39;')}')">Items</button></td>
            </tr>`).join('')
            : '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No employees match.</span></div></td></tr>';
        pr_pager($pr('pr-rec-pag-info'), $pr('pr-rec-pag-btns'), $pr('pr-rec-pag'), pg.current_page || 1, pg.last_page || 1, pg.total || 0, pg.per_page || 25, pr_loadRecords);
    } catch (e) {
        body.innerHTML = `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(e.message)}</span></div></td></tr>`;
    }
}
const PR_CAT = { EARNING: 'Earning', REFUND: 'Refund', DEDUCTION: 'Deduction', EMPLOYER_SHARE: 'Employer Share' };
async function pr_openItems(recordId, empNo) {
    $pr('pr-items-title').textContent = 'Payroll Items — Employee ' + empNo;
    const body = $pr('pr-items-tbody');
    body.innerHTML = '<tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $pr('pr-items-overlay').classList.add('open');
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/record-items/' + recordId));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        const rows = json.data || [];
        body.innerHTML = rows.length ? rows.map(r => `<tr class="pr-cat-${pr_esc(r.category)}">
                <td>${pr_esc(PR_CAT[r.category] || r.category)}</td>
                <td><span class="nha-mono">${pr_esc(r.item_code)}</span><div class="pr-hint">${pr_esc(r.item_name)}</div></td>
                <td>${r.account_code ? `<span class="nha-mono">${pr_esc(r.account_code)}</span><div class="pr-hint">${pr_esc(r.account_title || '')}</div>` : '<span class="pr-hint">No account</span>'}</td>
                ${pr_amt(r.amount)}
            </tr>`).join('')
            : '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No amounts.</span></div></td></tr>';
    } catch (e) {
        body.innerHTML = `<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(e.message)}</span></div></td></tr>`;
    }
}

/* ── Year-to-Date report ────────────────────────────────────── */
let pr_ytdRows = [], pr_ytdPage = 1, pr_ytdMeta = null, pr_ytdYearTouched = false;
const PR_YTD_PER_PAGE = 25;
function pr_initYtdFilters() {
    const y = new Date().getFullYear();
    $pr('pr-y-year').innerHTML = [y + 1, y, y - 1, y - 2, y - 3, y - 4].map(v => `<option value="${v}">${v}</option>`).join('');
    $pr('pr-y-year').value = String(y);
    $pr('pr-y-month').innerHTML = '<option value="">Full Year</option>' + PR_MONTHS.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
}
async function pr_loadYtd() {
    const params = new URLSearchParams({ year: $pr('pr-y-year').value });
    if ($pr('pr-y-month').value) params.set('month', $pr('pr-y-month').value);
    if ($pr('pr-y-dept').value) params.set('department', $pr('pr-y-dept').value);
    if ($pr('pr-y-status').value) params.set('status', $pr('pr-y-status').value);
    if ($pr('pr-y-search').value.trim()) params.set('search', $pr('pr-y-search').value.trim());
    const body = $pr('pr-y-tbody');
    body.innerHTML = '<tr><td colspan="13"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $pr('pr-y-tfoot').innerHTML = '';
    $pr('pr-y-export').disabled = true;
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/ytd?' + params));
        if (!json.success) throw new Error(json.message || 'Failed to build the report.');
        const dSel = $pr('pr-y-dept'), dCur = dSel.value;
        dSel.innerHTML = '<option value="">All Departments</option>' + (json.departments || []).map(d => `<option value="${pr_esc(d)}">${pr_esc(d)}</option>`).join('');
        dSel.value = dCur;
        pr_ytdRows = json.data || [];
        pr_ytdMeta = { totals: json.totals || {}, months: json.months_imported || [], label: $pr('pr-y-year').value + ($pr('pr-y-month').value ? ' as of ' + PR_MONTHS[$pr('pr-y-month').value - 1] : '') };
        const t = pr_ytdMeta.totals;
        $pr('pr-y-kpis').style.display = '';
        $pr('pr-y-k-emp').textContent = t.employees ?? 0;
        $pr('pr-y-k-months').textContent = pr_ytdMeta.months.length ? 'Imported: ' + pr_ytdMeta.months.join(', ') : 'No month imported for this year';
        $pr('pr-y-k-gross').textContent = pr_peso(t.ytd_gross);
        $pr('pr-y-k-ded').textContent = pr_peso(t.ytd_deductions);
        $pr('pr-y-k-net').textContent = pr_peso(t.ytd_net);
        $pr('pr-y-k-ref').textContent = 'Refunds included: ' + pr_peso(t.ytd_refunds);
        $pr('pr-y-export').disabled = !pr_ytdRows.length;
        pr_renderYtd(1);
    } catch (e) {
        body.innerHTML = `<tr><td colspan="13"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(e.message)}</span></div></td></tr>`;
        $pr('pr-y-pag').style.display = 'none';
    }
}
function pr_renderYtd(page) {
    pr_ytdPage = page;
    const body = $pr('pr-y-tbody'), total = pr_ytdRows.length, last = Math.max(1, Math.ceil(total / PR_YTD_PER_PAGE));
    if (!total) {
        body.innerHTML = '<tr><td colspan="13"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No imported payroll for these filters.</span></div></td></tr>';
        $pr('pr-y-tfoot').innerHTML = '';
        $pr('pr-y-pag').style.display = 'none';
        return;
    }
    body.innerHTML = pr_ytdRows.slice((page - 1) * PR_YTD_PER_PAGE, page * PR_YTD_PER_PAGE).map(r => `<tr>
            <td class="nha-mono">${pr_esc(r.employee_no)}</td>
            <td><strong>${pr_esc(pr_name(r))}</strong></td>
            <td>${pr_esc(r.position || '—')}<div class="pr-hint">${pr_esc(r.department || '')}</div></td>
            <td>${pr_esc(r.employment_status || '—')}</td>
            <td class="nha-mono">${pr_esc(r.tin || '—')}</td>
            <td class="nha-mono">${pr_esc(r.pagibig_no || '—')}</td>
            <td class="nha-mono">${pr_esc(r.philhealth_no || '—')}</td>
            <td class="pr-num">${pr_esc(r.months)}</td>
            ${pr_amt(r.ytd_gross)}${pr_amt(r.ytd_deductions)}${pr_amt(r.ytd_refunds)}${pr_amt(r.ytd_net)}${pr_amt(r.ytd_employer_share)}
        </tr>`).join('');
    const t = pr_ytdMeta.totals;
    $pr('pr-y-tfoot').innerHTML = `<tr><td colspan="8">TOTAL — ${pr_esc(String(t.employees))} employee(s)</td>${pr_amt(t.ytd_gross)}${pr_amt(t.ytd_deductions)}${pr_amt(t.ytd_refunds)}${pr_amt(t.ytd_net)}${pr_amt(t.ytd_employer_share)}</tr>`;
    pr_pager($pr('pr-y-pag-info'), $pr('pr-y-pag-btns'), $pr('pr-y-pag'), page, last, total, PR_YTD_PER_PAGE, pr_renderYtd);
}
function pr_exportYtd() {
    if (!pr_ytdRows.length) return;
    const cols = [['employee_no', 'Emp No.'], ['last_name', 'Last Name'], ['first_name', 'First Name'], ['middle_name', 'Middle Name'], ['position', 'Position'],
        ['department', 'Department'], ['employment_status', 'Employment Status'], ['tin', 'TIN'], ['pagibig_no', 'Pag-IBIG'], ['philhealth_no', 'PhilHealth'],
        ['months', 'Months'], ['ytd_gross', 'YTD Gross Pay'], ['ytd_deductions', 'YTD Deductions'], ['ytd_refunds', 'YTD Refunds'], ['ytd_net', 'YTD Net Pay'], ['ytd_employer_share', 'YTD Employer Share']];
    const q = v => { const s = v == null ? '' : String(v); return /[",\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
    const lines = [cols.map(c => q(c[1])).join(',')].concat(pr_ytdRows.map(r => cols.map(c => q(r[c[0]])).join(',')));
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'Payroll-YTD-' + pr_ytdMeta.label.replace(/\s+/g, '-') + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/* ── File template ──────────────────────────────────────────── */
async function pr_loadTemplate() {
    const box = $pr('pr-t-items');
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/template-columns'));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        if (json.form_file) $pr('pr-t-file').textContent = json.form_file;
        const fc = json.form_columns || [];
        const fcSet = fc.filter(c => c.status === 'ACTIVE').length, fcOff = fc.filter(c => c.status === 'INACTIVE').length;
        $pr('pr-t-form').innerHTML = !fc.length ? '<div class="pr-msg err" style="display:block;">The import form file was not found.</div>'
            : `<div class="pr-sub" style="margin-bottom:8px;"><strong>${fc.length}</strong> amount columns — <strong>${fcSet}</strong> set up, ${fcOff} inactive, <strong>${fc.length - fcSet - fcOff}</strong> not set up yet.</div><div class="pr-chips">`
              + fc.map(c => {
                  const st = c.status === 'ACTIVE' ? `${PR_CAT[c.category] || c.category}${c.account_code ? ' · ' + c.account_code : ''}` : c.status === 'INACTIVE' ? 'inactive' : 'not set up';
                  const bg = c.status === 'ACTIVE' ? '' : c.status === 'INACTIVE' ? 'opacity:.6;' : 'border-style:dashed;opacity:.75;';
                  return `<span class="pr-chip" style="${bg}" title="${pr_esc(c.item_name || '')}">${pr_esc(c.column)}<small>${pr_esc(st)}</small></span>`;
              }).join('') + '</div>';
        $pr('pr-t-identity').innerHTML = (json.identity || []).map(c => `<span class="pr-chip">${pr_esc(c.column)}<small>${pr_esc(c.label)}${c.required ? '' : ' (optional)'}</small></span>`).join('');
        const items = json.items || [];
        if (!items.length) {
            box.innerHTML = `<div class="pr-msg warn" style="display:block;">No active items in Payroll Setup yet — nothing can be imported until the payroll items are set up in <a href="${PR_SETUP_URL}" target="_blank" rel="noopener">Payroll Setup</a>.</div>`;
            return;
        }
        box.innerHTML = Object.keys(PR_CAT).map(cat => {
            const list = items.filter(i => i.category === cat);
            if (!list.length) return '';
            return `<div class="pr-card pr-cat-${cat}" style="margin-bottom:10px;"><h4>${PR_CAT[cat]} (${list.length})</h4><div class="pr-chips">`
                + list.map(i => `<span class="pr-chip" title="${pr_esc(i.item_name)}">${pr_esc(i.item_code)}<small>${i.account_code ? pr_esc(i.account_code) : 'no account'}</small></span>`).join('')
                + '</div></div>';
        }).join('');
    } catch (e) {
        box.innerHTML = `<div class="pr-msg err" style="display:block;">${pr_esc(e.message)}</div>`;
    }
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

/* ── Boot ───────────────────────────────────────────────────── */
function pr_boot() {
    pr_initYtdFilters();
    $pr('pr-y-load').addEventListener('click', pr_loadYtd);
    $pr('pr-y-year').addEventListener('change', () => { pr_ytdYearTouched = true; });
    $pr('pr-y-export').addEventListener('click', pr_exportYtd);
    $pr('pr-y-search').addEventListener('keydown', e => { if (e.key === 'Enter') pr_loadYtd(); });
    $pr('pr-t-download').addEventListener('click', pr_downloadTemplate);
    $pr('pr-rec-search').addEventListener('input', () => { clearTimeout(pr_recTimer); pr_recTimer = setTimeout(() => pr_loadRecords(1), 350); });
    $pr('pr-refresh').addEventListener('click', pr_load);
    $pr('pr-f-year').addEventListener('change', pr_load);
    $pr('pr-f-status').addEventListener('change', pr_load);
    $pr('pr-reset').addEventListener('click', () => { $pr('pr-f-year').value = ''; $pr('pr-f-status').value = ''; pr_load(); });
    $pr('pr-import-btn').addEventListener('click', pr_open);
    $pr('pr-close').addEventListener('click', pr_close);
    $pr('pr-cancel').addEventListener('click', pr_close);
    $pr('pr-preview-btn').addEventListener('click', pr_preview);
    $pr('pr-save').addEventListener('click', pr_import);
    ['pr-month', 'pr-year', 'pr-file'].forEach(id => $pr(id).addEventListener('change', pr_resetPreview));
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const open = ['pr-items-overlay', 'pr-rec-overlay', 'pr-overlay'].find(id => $pr(id).classList.contains('open'));
        if (open) pr_closeOverlay(open);
    });
    pr_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pr_boot);
else pr_boot();
</script>
