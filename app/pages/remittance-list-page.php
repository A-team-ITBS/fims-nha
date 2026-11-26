<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Remittance List (Deductions) — what approved disbursement vouchers withheld (Reports menu). Read-only: the data comes from
// the REST API (app/routes/remittance_list.php → RemittanceListService), no direct DB queries on this page.
// A voucher is listed from FOR_TREASURY on (Accounting's approval posted its journal entry, so the amounts are real payables) and
// stays through RELEASED; "Ready to remit" means its payment has been released. Payroll deductions have their own page
// (Payroll → Remittance).
//
// Layout, top to bottom, in the order a person works: 1) how much is in each state (the four cards), 2) what to pay BIR this month
// ("Pay to BIR" — one row per month and tax, with the button to create / approve the remittance DV), 3) the detail
// (what each voucher withheld — one row per voucher that opens to show its lines, or one row per line).
// API.FIMS_URL is the site root; the REST routes live under /api (added unless the env value already ends in it).
$_rl_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_rl_api = preg_match('~/api$~', $_rl_api) ? $_rl_api : $_rl_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<script src="/_shared/js/searchable-select.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/js/searchable-select.js') ?>"></script>

<style>
/* One block per task: a title + one line of help, then its content */
#rl-root .rl-sec { margin: 0 0 20px; }
/* Two tabs, one table on screen at a time */
#rl-root .rl-tabs { display: flex; gap: 4px; margin: 0 0 14px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#rl-root .rl-tab { border: 0; background: none; padding: 10px 16px; margin-bottom: -1px; font: inherit; font-size: 13.5px; font-weight: 600; color: var(--afims-text-muted, #62657E); cursor: pointer; border-bottom: 2px solid transparent; }
#rl-root .rl-tab:hover { color: var(--afims-text, #1e293b); }
#rl-root .rl-tab.active { color: var(--afims-primary, #0035A9); border-bottom-color: var(--afims-primary, #0035A9); }
#rl-root .rl-tab-badge { display: inline-block; min-width: 18px; margin-left: 6px; padding: 1px 6px; border-radius: 999px; font-size: 11px; line-height: 16px; text-align: center; color: #fff; background: var(--afims-primary, #0035A9); }
#rl-root .rl-tab-badge:empty { display: none; }
#rl-root .rl-sec-hdr { display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 6px 16px; margin: 0 0 8px; }
#rl-root .rl-sec-title { margin: 0; font-size: 15px; font-weight: 700; color: var(--afims-text, #1e293b); }
#rl-root .rl-sec-help { margin: 0; font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
#rl-root .rl-panel { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; overflow: hidden; background: var(--afims-surface, #fff); }
#rl-root .rl-panel .nha-tbl-scroll { margin: 0; }
#rl-root .rl-empty { padding: 22px 16px; text-align: center; font-size: 13px; color: var(--afims-text-muted, #62657E); }
#rl-root .rl-note { margin: 8px 2px 0; font-size: 12px; color: var(--afims-text-muted, #62657E); }
#rl-root .rl-note strong { font-family: "IBM Plex Mono", monospace; font-weight: 600; color: var(--afims-text, #1e293b); }
/* Remittances: the status cell is one short line per remittance DV / state */
#rl-root .rl-stand { display: flex; flex-direction: column; gap: 3px; font-size: 12.5px; }
#rl-root .rl-stand-row { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
#rl-root .rl-stand-row strong { font-family: "IBM Plex Mono", monospace; font-weight: 600; }
#rl-root .rl-sub { font-size: 11.5px; color: var(--afims-text-muted, #62657E); margin-top: 2px; }
#rl-root .rl-acts { display: flex; flex-direction: column; align-items: flex-start; gap: 6px; }
#rl-root .rl-acts .nha-btn { padding: 6px 14px; font-size: 12.5px; white-space: nowrap; }
/* Filters: one tidy row */
#rl-root .rl-clear { border: 0; background: none; padding: 0; font-size: 12px; color: var(--afims-primary, #0035A9); cursor: pointer; text-decoration: underline; align-self: flex-end; margin-bottom: 8px; }
/* View toggle */
#rl-root .rl-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#rl-root .rl-seg { display: inline-flex; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; overflow: hidden; background: var(--afims-surface, #fff); }
#rl-root .rl-seg button { border: 0; background: transparent; padding: 6px 14px; font-size: 12px; font-weight: 600; cursor: pointer; color: var(--afims-text-muted, #62657E); font-family: inherit; }
#rl-root .rl-seg button + button { border-left: 1px solid var(--afims-border, #E4ECF2); }
#rl-root .rl-seg button.active { background: var(--afims-primary, #0035A9); color: #fff; }
#rl-root .rl-expand-all { border: 0; background: none; padding: 0; font-size: 12px; color: var(--afims-primary, #0035A9); cursor: pointer; text-decoration: underline; }
/* By voucher: click a row to open the lines under it */
#rl-root tr.rl-parent { cursor: pointer; }
#rl-root tr.rl-parent:hover td { background: var(--afims-surface-hover, #EAF0F6); }
#rl-root tr.rl-parent:focus-visible { outline: 2px solid var(--afims-primary, #0035A9); outline-offset: -2px; }
#rl-root tr.rl-parent.open td { background: var(--afims-surface-subtle, #F4F7FA); border-bottom: 0; }
#rl-root tr.rl-parent .rl-chev { display: inline-block; width: 14px; margin-right: 4px; font-size: 10px; color: var(--afims-text-muted, #62657E); transition: transform .15s; }
#rl-root tr.rl-parent.open .rl-chev { transform: rotate(90deg); }
#rl-root tr.rl-child.rl-collapsed { display: none; }
#rl-root tr.rl-child > td { padding: 0 0 10px 28px; background: var(--afims-surface-subtle, #F4F7FA); }
#rl-root tr.rl-child .nha-tbl { margin: 0; }
#rl-root tr.rl-child .nha-tbl th { font-size: 10.5px; }
</style>

<div id="rl-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Remittance List (Deductions)</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="rl-refresh" type="button" class="nha-btn-hdr" title="Reload the latest figures">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="rl-export" type="button" class="nha-btn-hdr" title="Download everything matching the filters as a spreadsheet (CSV)">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="rl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="rl-error-msg">Failed to load data.</span>
    </div>

    <!-- ── 1. AT A GLANCE — four cards, left to right in the order an amount moves (they cover every line matching the filters) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Waiting for release</div>
                <div class="nha-sc-val" id="rl-kpi-withheld">—</div>
            </div></div>
            <p class="nha-kpi-sub">Voucher approved, payment not released yet</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Ready to remit</div>
                <div class="nha-sc-val" id="rl-kpi-ready">—</div>
            </div></div>
            <p class="nha-kpi-sub">Voucher released — you can pay this now</p>
        </div>
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Being processed</div>
                <div class="nha-sc-val" id="rl-kpi-indv">—</div>
            </div></div>
            <p class="nha-kpi-sub">In a remittance DV that is not paid yet</p>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Paid</div>
                <div class="nha-sc-val" id="rl-kpi-remitted">—</div>
            </div></div>
            <p class="nha-kpi-sub">Remittance DV released</p>
        </div>
    </div>
    <p class="rl-note" id="rl-kpi-note" style="margin:-4px 2px 18px;"></p>

    <!-- ── TABS — "Remittances" (what to do) and "What was withheld" (the detail) show one at a time ── -->
    <div class="rl-tabs no-print" role="tablist">
        <button type="button" class="rl-tab active" id="rl-tab-pay" role="tab" aria-selected="true" aria-controls="rl-pane-pay" onclick="rl_setTab('pay')">Remittances<span class="rl-tab-badge" id="rl-tab-badge"></span></button>
        <button type="button" class="rl-tab" id="rl-tab-detail" role="tab" aria-selected="false" aria-controls="rl-pane-detail" onclick="rl_setTab('detail')">What was withheld</button>
    </div>

    <!-- ── REMITTANCES — taxes paid to BIR, one remittance DV per month and tax ───────────── -->
    <div class="rl-sec no-print" id="rl-pane-pay" role="tabpanel">
        <p class="rl-sec-help" style="margin:0 0 8px;">Withheld taxes are paid to BIR once a month for each tax, through a remittance DV. Retention, advance recoupment and other deductions are not paid from here.</p>
        <div class="rl-panel">
            <div class="nha-tbl-scroll"><table class="nha-tbl">
                <thead><tr><th>Month</th><th>Tax</th><th class="text-end">Total withheld</th><th>Status</th><th>What to do</th></tr></thead>
                <tbody id="rl-months-body"><tr><td colspan="5"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr></tbody>
            </table></div>
        </div>
    </div>

    <!-- ── WHAT WAS WITHHELD — every deduction taken from an approved voucher ──────────────────────── -->
    <div class="rl-sec" id="rl-pane-detail" role="tabpanel" style="display:none;">

        <!-- FILTERS -->
        <div class="nha-filters no-print" style="margin-bottom:10px;">
            <div class="nha-filter-row">
                <div class="nha-filter-group nha-filter-group-search">
                    <label class="nha-filter-label" for="rl-filter-search">Search</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="rl-filter-search" class="nha-filter-input" placeholder="DV number, payee, ATC code…">
                    </div>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="rl-filter-fy">Year</label>
                    <select id="rl-filter-fy" class="nha-filter-select">
                        <option value="">All years</option>
                    </select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="rl-filter-type">Kind of deduction</label>
                    <select id="rl-filter-type" class="nha-filter-select">
                        <option value="">All deductions</option>
                        <option value="EWT">Withholding tax (ATC)</option>
                        <option value="VAT">Withholding VAT</option>
                        <option value="PENALTY_TAX">Penalty tax</option>
                        <option value="RETENTION">Retention</option>
                        <option value="RECOUPMENT">Advance recoupment</option>
                        <option value="OTHER">Other</option>
                    </select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="rl-filter-agency">Goes to</label>
                    <select id="rl-filter-agency" class="nha-filter-select">
                        <option value="">Anyone</option>
                        <option value="BIR">BIR</option>
                        <option value="OTHER">Other payables</option>
                        <option value="RETAINED">Kept for the contractor</option>
                        <option value="INTERNAL">NHA (advance recovery)</option>
                    </select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="rl-filter-status">Status</label>
                    <select id="rl-filter-status" class="nha-filter-select">
                        <option value="">All statuses</option>
                        <option value="WITHHELD">Waiting for release</option>
                        <option value="READY">Ready to remit</option>
                        <option value="IN_DV">Being processed</option>
                        <option value="REMITTED">Paid</option>
                        <option value="NOT_REMITTED">Kept — not paid out</option>
                    </select>
                </div>
                <button type="button" class="rl-clear" id="rl-clear" style="display:none;">Clear filters</button>
            </div>
        </div>

        <!-- TABLE -->
        <div class="rl-panel">
            <div class="rl-toolbar no-print">
                <div class="rl-seg" role="group" aria-label="How to list">
                    <button type="button" id="rl-view-dv" class="active" title="One row per voucher — click a row to see what it withheld">By voucher</button>
                    <button type="button" id="rl-view-flat" title="One row per deduction — handy for totals by kind of deduction">All deductions</button>
                </div>
                <span class="rl-sec-help" id="rl-hint">Click a voucher to see what it withheld.
                    <button type="button" class="rl-expand-all" id="rl-expand-all" onclick="rl_toggleAll()">Open all</button></span>
            </div>
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead id="rl-thead"></thead>
                    <tbody id="rl-tbody">
                        <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
            <div class="nha-pag-wrap">
                <span class="nha-pag-info">Showing <strong><span id="rl-from">0</span>–<span id="rl-to">0</span></strong> of <strong><span id="rl-total">0</span></strong> <span id="rl-unit">vouchers</span></span>
                <div id="rl-pagination" class="nha-pag-btns"></div>
            </div>
        </div>
    </div>

</div><!-- /#rl-root -->

<?php
include __DIR__ . '/voucher/form.php';   // the DV add / edit modal — "Create remittance DV" opens it filled in (vf_openForDvRemit) and it calls window.vf_onSaved after saving
include __DIR__ . '/voucher/readone.php'; // the DV view modal — "Review & approve" opens it (vr_open); its footer has the approve / forward / reject / release buttons and it calls window.vf_onSaved after a status change
?>

<script>
/* ══════════════════════════════════════════════════════════════
   REMITTANCE LIST (DEDUCTIONS)   prefix: rl_
═══════════════════════════════════════════════════════════════ */

const RL_API     = <?= json_encode($_rl_api) ?> + '/remittance-list';
const RL_VOUCHER = <?= json_encode(GetUrl('Voucher')) ?>; // ?dv=<dv_id> opens that voucher's view
const RL_FY_API  = <?= json_encode($_rl_api) ?> + '/voucher/fiscal-years';

const RL_PAGE_SIZE = 15;
let rl_page = 1;
let rl_searchTimer = null;
let rl_mode = 'dv';          // 'dv' = one row per voucher (click it for its deductions) · 'flat' = one row per deduction
let rl_expanded = new Set();   // dv_ids whose deductions are shown (kept while paging / filtering)
let rl_byId = {};            // dv_id -> the voucher entry of the page currently shown

const $rl = id => document.getElementById(id);

function rl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function rl_boot() {
    await rl_loadFiscalYears();
    rl_bindEvents();
    rl_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', rl_boot);
else rl_boot();

const RL_FILTER_IDS = ['rl-filter-fy', 'rl-filter-type', 'rl-filter-agency', 'rl-filter-status'];

function rl_bindEvents() {
    $rl('rl-refresh')?.addEventListener('click', () => { rl_page = 1; rl_load(); });
    $rl('rl-export')?.addEventListener('click', rl_exportCsv);
    $rl('rl-view-dv')?.addEventListener('click', () => rl_setMode('dv'));
    $rl('rl-view-flat')?.addEventListener('click', () => rl_setMode('flat'));
    $rl('rl-clear')?.addEventListener('click', () => {
        RL_FILTER_IDS.forEach(id => { $rl(id).value = ''; });
        $rl('rl-filter-search').value = '';
        rl_page = 1; rl_load();
    });
    RL_FILTER_IDS.forEach(id => {
        $rl(id)?.addEventListener('change', () => { rl_page = 1; rl_load(); });
    });
    $rl('rl-filter-search')?.addEventListener('input', () => {
        clearTimeout(rl_searchTimer);
        rl_searchTimer = setTimeout(() => { rl_page = 1; rl_load(); }, 400);
    });
}

/* Fiscal years come from GET /voucher/fiscal-years → {success, data:[{fiscal_yr}]}, newest first. */
async function rl_loadFiscalYears() {
    const sel = $rl('rl-filter-fy');
    try {
        const res  = await rl_authFetch(RL_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All years</option>' +
            (json.data ?? []).map(r => `<option value="${rl_esc(r.fiscal_yr)}">${rl_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        rl_toast('Years: ' + e.message, 'error');
    }
}

function rl_filters() {
    return {
        fiscal_yr: $rl('rl-filter-fy').value,
        type     : $rl('rl-filter-type').value,
        agency   : $rl('rl-filter-agency').value,
        status   : $rl('rl-filter-status').value,
        search   : $rl('rl-filter-search').value.trim(),
    };
}

/* ── Load ──────────────────────────────────────────────────── */
async function rl_load() {
    window.PagePreloader?.show();
    rl_hideError();
    $rl('rl-clear').style.display = Object.values(rl_filters()).some(x => x !== '') ? '' : 'none';
    try {
        const params = new URLSearchParams({ page: rl_page, limit: RL_PAGE_SIZE, ...rl_filters() });
        const res  = await rl_authFetch(`${RL_API}/${rl_mode === 'dv' ? 'by-dv' : 'list'}?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the remittance list.');

        rl_renderKpis(json.summary ?? {});
        rl_render(json.data ?? []);
        rl_renderPagination(json.pagination ?? {});
        rl_loadMonths();
    } catch (e) {
        rl_renderKpis({});
        rl_render([]);
        rl_renderPagination({});
        rl_showError('Could not load the data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function rl_renderKpis(s) {
    const ok = s.total !== undefined;
    $rl('rl-kpi-withheld').textContent = ok ? rl_peso(s.withheld) : '—';
    $rl('rl-kpi-ready').textContent    = ok ? rl_peso(s.ready) : '—';
    $rl('rl-kpi-indv').textContent     = ok ? rl_peso(s.in_dv) : '—';
    $rl('rl-kpi-remitted').textContent = ok ? rl_peso(s.remitted) : '—';
    // the two figures the four cards leave out, as one quiet line
    $rl('rl-kpi-note').innerHTML = !ok ? '' :
        `Total withheld: <strong>${rl_peso(s.total)}</strong> from ${Number(s.vouchers || 0)} voucher${Number(s.vouchers || 0) === 1 ? '' : 's'}` +
        (Number(s.not_remitted || 0) > 0 ? ` &nbsp;·&nbsp; Kept, not paid out (retention and advance recoupment): <strong>${rl_peso(s.not_remitted)}</strong>` : '');
}

/* ── View mode ─────────────────────────────────────────────── */
function rl_setMode(mode) {
    if (mode === rl_mode) return;
    rl_mode = mode;
    rl_page = 1;
    $rl('rl-view-dv').classList.toggle('active', mode === 'dv');
    $rl('rl-view-flat').classList.toggle('active', mode === 'flat');
    $rl('rl-hint').style.display = mode === 'dv' ? '' : 'none';
    $rl('rl-unit').textContent = mode === 'dv' ? 'vouchers' : 'deductions';
    rl_load();
}

/* ── Table ─────────────────────────────────────────────────── */
const RL_AGENCY = { BIR: 'BIR', OTHER: 'Other payables', RETAINED: 'Kept for the contractor', INTERNAL: 'NHA (advance recovery)' };
const RL_MUTED  = 'color:var(--afims-text-muted,#62657E);';

function rl_render(rows) { rl_mode === 'dv' ? rl_renderVouchers(rows) : rl_renderTable(rows); }

function rl_emptyRow(cols) {
    const filtered = Object.values(rl_filters()).some(x => x !== '');
    return `<tr><td colspan="${cols}"><div class="rl-empty">${filtered
        ? 'Nothing matches these filters. Try clearing them.'
        : 'Nothing has been withheld yet. Deductions appear here once a voucher is approved.'}</div></td></tr>`;
}

/* One row per voucher — click it to open what it withheld right underneath. The lines shown are the ones matching the filters. */
function rl_renderVouchers(rows) {
    $rl('rl-thead').innerHTML = `<tr><th>DV number</th><th>Date</th><th>Payee</th><th class="text-end">Gross</th>
        <th class="text-end">Withheld</th><th class="text-end">Net paid</th><th>Status</th></tr>`;
    rl_byId = {};
    rows.forEach(v => { rl_byId[Number(v.dv_id)] = v; });
    const tbody = $rl('rl-tbody');
    if (!rows.length) { tbody.innerHTML = rl_emptyRow(7); rl_syncExpandAll(); return; }
    tbody.innerHTML = rows.map(v => {
        const id = Number(v.dv_id);
        const open = rl_expanded.has(id);
        const n = Number(v.lines || 0);
        return `<tr class="rl-parent${open ? ' open' : ''}" data-dv="${id}" tabindex="0" role="button" aria-expanded="${open}" title="Click to see what this voucher withheld"
                    onclick="rl_toggleDv(${id})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();rl_toggleDv(${id});}">
            <td class="nha-mono"><span class="rl-chev" aria-hidden="true">▶</span><a href="${rl_esc(RL_VOUCHER)}?dv=${id}" class="nha-tbl-link" title="Open this voucher" onclick="event.stopPropagation()">${rl_esc(v.dv_no) || '—'}</a></td>
            <td class="nha-td-date">${rl_fmtDate(v.dv_date)}</td>
            <td style="max-width:240px;" title="${rl_esc(v.claimant_name)}">
                <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${rl_esc(v.claimant_name) || '—'}</div>
            </td>
            <td class="text-end nha-mono">${rl_peso(v.dv_gross)}</td>
            <td class="text-end nha-mono" style="font-weight:600;">${rl_peso(v.total)}<div class="rl-sub" style="font-family:inherit;font-weight:400;">${n} deduction${n === 1 ? '' : 's'}</div></td>
            <td class="text-end nha-mono">${rl_peso(v.dv_net)}</td>
            <td>${rl_statusBadge(v.status)}</td>
        </tr>
        <tr class="rl-child${open ? '' : ' rl-collapsed'}" data-dv-child="${id}"><td colspan="7">${rl_dedTable(v.items || [])}</td></tr>`;
    }).join('');
    rl_syncExpandAll();
}

/* Show / hide one voucher's lines (parent row + the child row under it). */
function rl_toggleDv(id) {
    id = Number(id);
    const open = !rl_expanded.has(id);
    open ? rl_expanded.add(id) : rl_expanded.delete(id);
    const tr = document.querySelector(`#rl-tbody tr.rl-parent[data-dv="${id}"]`);
    if (tr) { tr.classList.toggle('open', open); tr.setAttribute('aria-expanded', String(open)); }
    document.querySelector(`#rl-tbody tr.rl-child[data-dv-child="${id}"]`)?.classList.toggle('rl-collapsed', !open);
    rl_syncExpandAll();
}

/* "Open all" / "Close all" for the vouchers on the page. */
function rl_toggleAll() {
    const ids = Object.keys(rl_byId).map(Number);
    const allOpen = ids.length > 0 && ids.every(i => rl_expanded.has(i));
    ids.forEach(i => { if (allOpen !== !rl_expanded.has(i)) rl_toggleDv(i); });
}

function rl_syncExpandAll() {
    const ids = Object.keys(rl_byId).map(Number);
    const btn = $rl('rl-expand-all');
    if (btn) btn.textContent = ids.length && ids.every(i => rl_expanded.has(i)) ? 'Close all' : 'Open all';
}

/* What one voucher withheld: the same few columns in both views, with the account as a quiet second line. */
function rl_dedTable(items) {
    if (!items.length) return `<div style="padding:10px;${RL_MUTED}">No deductions.</div>`;
    return `<div class="nha-tbl-scroll"><table class="nha-tbl"><thead><tr>
            <th>Deduction</th><th class="text-end">Rate</th><th class="text-end">Amount based on</th><th class="text-end">Withheld</th>
            <th>Goes to</th><th>Status</th></tr></thead><tbody>` +
        items.map(r => `<tr>
            <td>${rl_dedName(r)}</td>
            <td class="text-end nha-mono">${rl_rate(r.rate)}</td>
            <td class="text-end nha-mono">${rl_peso(r.base)}</td>
            <td class="text-end nha-mono" style="font-weight:600;">${rl_peso(r.amount)}</td>
            <td>${rl_esc(RL_AGENCY[r.agency] || r.agency)}</td>
            <td>${rl_statusBadge(r.remit_status)}${r.remit_dv_no ? `<div class="rl-sub"><a class="nha-tbl-link nha-mono" href="${rl_esc(RL_VOUCHER)}?dv=${Number(r.remit_dv_id)}">Remittance DV ${rl_esc(r.remit_dv_no)}</a></div>` : ''}</td>
        </tr>`).join('') + '</tbody></table></div>';
}

/* "Withholding tax  WI010" with the payable account underneath. */
function rl_dedName(r) {
    const acct = r.account_code ? `<div class="rl-sub" title="Payable account">${rl_esc(r.account_code)}${r.account_title ? ' · ' + rl_esc(r.account_title) : ''}</div>` : '';
    return `${rl_esc(r.type_name)}${r.atc_code ? ` <span class="nha-mono" style="font-size:11px;${RL_MUTED}">${rl_esc(r.atc_code)}</span>` : ''}${acct}`;
}

function rl_rate(r) { return r !== null && r !== undefined && r !== '' ? Number(r) + '%' : '—'; }

/* The flat view: one row per deduction line. */
function rl_renderTable(rows) {
    $rl('rl-thead').innerHTML = `<tr><th>DV number</th><th>Date</th><th>Payee</th><th>Deduction</th><th class="text-end">Rate</th>
        <th class="text-end">Amount based on</th><th class="text-end">Withheld</th><th>Goes to</th><th>Status</th></tr>`;
    const tbody = $rl('rl-tbody');
    if (!rows.length) { tbody.innerHTML = rl_emptyRow(9); return; }
    tbody.innerHTML = rows.map(r => `<tr>
        <td class="nha-mono"><a href="${rl_esc(RL_VOUCHER)}?dv=${Number(r.dv_id)}" class="nha-tbl-link" title="Open this voucher">${rl_esc(r.dv_no) || '—'}</a></td>
        <td class="nha-td-date">${rl_fmtDate(r.dv_date)}</td>
        <td style="max-width:220px;" title="${rl_esc(r.claimant_name)}">
            <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${rl_esc(r.claimant_name) || '—'}</div>
        </td>
        <td>${rl_dedName(r)}</td>
        <td class="text-end nha-mono">${rl_rate(r.rate)}</td>
        <td class="text-end nha-mono">${rl_peso(r.base)}</td>
        <td class="text-end nha-mono" style="font-weight:600;">${rl_peso(r.amount)}</td>
        <td>${rl_esc(RL_AGENCY[r.agency] || r.agency)}</td>
        <td>${rl_statusBadge(r.remit_status)}${r.remit_dv_no ? `<div class="rl-sub">Remittance DV ${rl_esc(r.remit_dv_no)}</div>` : ''}</td>
    </tr>`).join('');
}

/* ── Tabs ──────────────────────────────────────────────────── */
function rl_setTab(tab) {
    const pay = tab !== 'detail';
    $rl('rl-pane-pay').style.display    = pay ? '' : 'none';
    $rl('rl-pane-detail').style.display = pay ? 'none' : '';
    [['rl-tab-pay', pay], ['rl-tab-detail', !pay]].forEach(([id, on]) => {
        $rl(id).classList.toggle('active', on);
        $rl(id).setAttribute('aria-selected', String(on));
    });
}

/* ── Remittances (taxes paid to BIR) ───────────────────────── */
/* One row per month and BIR tax: what is withheld, where it stands, and the one thing to do next. "Create remittance DV" opens the new-DV FORM
   right here (the Voucher page's own form, included below) filled in: claimant BIR, the month's total, the breakdown by rate, entry Dr the liability /
   Cr Cash in Bank, with the voucher-by-voucher schedule. Accounting reviews it and saves it as a draft or submits it; saving creates the DV and links
   the month's vouchers (one DV per month and tax), then this page reloads and the row shows the new DV number. */
function rl_remit(period, type) {
    if (typeof vf_openForDvRemit !== 'function') { rl_toast('The voucher form is not available on this page.', 'error'); return; }
    vf_openForDvRemit(period, type);
}

// the voucher form's hooks: it reports a successful save here and borrows this page's toast for its warnings
window.vf_onSaved = (data, message) => { rl_toast(message || 'Remittance DV saved.', 'success'); rl_load(); };
window.vl_toast = (msg, type) => rl_toast(msg, type);

async function rl_loadMonths() {
    try {
        const res  = await rl_authFetch(`${RL_API}/months?${new URLSearchParams({ fiscal_yr: rl_filters().fiscal_yr })}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the monthly remittances.');
        rl_renderMonths(json.data ?? []);
    } catch (e) {
        rl_renderMonths([]);
    }
}

function rl_renderMonths(rows) {
    const body = $rl('rl-months-body');
    // the tab's badge = the rows that need someone to act (create a DV, or move one along)
    const todo = rows.filter(r => r.can_remit || (r.dvs || []).some(d => !['RELEASED', 'CANCELLED'].includes(d.status))).length;
    $rl('rl-tab-badge').textContent = todo > 0 ? String(todo) : '';
    if (!rows.length) {
        body.innerHTML = '<tr><td colspan="5"><div class="rl-empty">Nothing to remit yet. A month appears here once a voucher with withholding tax is approved.</div></td></tr>';
        return;
    }
    body.innerHTML = rows.map(r => {
        const dvList = r.dvs || [];
        const open   = dvList.filter(d => !['RELEASED', 'CANCELLED'].includes(d.status));

        // status: one short line per remittance DV (its number and who has it), plus a badge for amounts that still need a DV or a release
        const stand = dvList.map(d => `<div class="rl-stand-row"><a class="nha-tbl-link nha-mono" href="${rl_esc(RL_VOUCHER)}?dv=${Number(d.dv_id)}" title="Open this remittance DV">DV ${rl_esc(d.dv_no)}</a> ${rl_dvStageBadge(d.status)}</div>`);
        if (Number(r.ready) > 0) stand.push(`<div class="rl-stand-row"><span class="nha-bdg nha-bdg-green">Ready to remit</span></div>`);
        if (Number(r.withheld) > 0) stand.push(`<div class="rl-stand-row"><span class="nha-bdg nha-bdg-amber">Waiting for release</span></div>`);
        if (!stand.length) stand.push('<span class="rl-sub">—</span>');
        const parts = [];   // what the total is made of, when it is not all in one state
        if (Number(r.ready) > 0 && Number(r.ready) < Number(r.total)) parts.push(`${rl_peso(r.ready)} ready`);
        if (Number(r.withheld) > 0 && Number(r.withheld) < Number(r.total)) parts.push(`${rl_peso(r.withheld)} waiting`);

        // the one thing to do next
        const acts = [];
        if (r.can_remit) {
            acts.push(`<button type="button" class="nha-btn nha-btn-primary" data-act="remit" title="Prepare the ${rl_esc(r.type_name)} payment to BIR for ${rl_esc(r.period_label)}"
                onclick="rl_remit('${rl_esc(r.period)}', '${rl_esc(r.type_key)}')">Create remittance DV</button>`);
        }
        open.forEach(d => acts.push(`<button type="button" class="nha-btn nha-btn-primary" data-act="review"
            title="Open DV ${rl_esc(d.dv_no)} to review it and move it to the next step" onclick="rl_reviewDv(${Number(d.dv_id)})">${['DRAFT', 'REJECTED'].includes(d.status) ? 'Open DV' : 'Review &amp; approve'}</button>`));
        if (!acts.length) {
            acts.push(`<span class="rl-sub" style="margin:0;font-size:12.5px;">${Number(r.withheld) > 0 ? 'Nothing yet — waiting for the vouchers to be released' : (Number(r.remitted) > 0 ? 'Done — paid' : '—')}</span>`);
        }

        return `<tr data-period="${rl_esc(r.period)}" data-type="${rl_esc(r.type_key)}">
            <td style="font-weight:600;white-space:nowrap;">${rl_esc(r.period_label)}</td>
            <td>${rl_esc(r.type_name)}</td>
            <td class="text-end nha-mono" style="font-weight:600;">${rl_peso(r.total)}<div class="rl-sub" style="font-family:inherit;font-weight:400;"><button type="button" class="rl-expand-all" title="Click to see which vouchers" onclick="rl_toggleSrc(this)">${Number(r.vouchers || 0)} voucher${Number(r.vouchers) === 1 ? '' : 's'} ▾</button>${parts.length ? ' · ' + parts.join(' · ') : ''}</div>
                <div class="rl-src" style="display:none;">${(r.source_dvs || []).map(s => `<div class="rl-sub" style="font-family:inherit;"><a class="nha-tbl-link nha-mono" href="${rl_esc(RL_VOUCHER)}?dv=${Number(s.dv_id)}" title="${rl_esc(s.payee)} — click to open" onclick="event.preventDefault();rl_reviewDv(${Number(s.dv_id)})">DV ${rl_esc(s.dv_no)}</a> · ${rl_peso(s.amount)}</div>`).join('')}</div></td>
            <td><div class="rl-stand">${stand.join('')}</div></td>
            <td class="no-print"><div class="rl-acts">${acts.join('')}</div></td>
        </tr>`;
    }).join('');
}

/* Show / hide the vouchers behind a month's total. */
function rl_toggleSrc(btn) {
    const box = btn.closest('td').querySelector('.rl-src');
    if (!box) return;
    const show = box.style.display === 'none';
    box.style.display = show ? '' : 'none';
    btn.textContent = btn.textContent.replace(/[▾▴]/, show ? '▴' : '▾');
}

/* Where a Remittance DV stands: with whom it is now, or that it was paid / is not going ahead. */
function rl_dvStageBadge(st) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'], FOR_FMD_REVIEW: ['nha-bdg-amber', 'With FMD'], FOR_ACCOUNTING: ['nha-bdg-blue', 'With Accounting'],
        FOR_DIVISION_MANAGER: ['nha-bdg-purple', 'With Division Manager'], FOR_DEPARTMENT_MANAGER: ['nha-bdg-navy', 'With Department Manager'],
        FOR_TREASURY: ['nha-bdg-teal', 'With Treasury'], RELEASED: ['nha-bdg-green', 'Paid'], REJECTED: ['nha-bdg-red', 'Rejected'],
    };
    const [cls, label] = map[st] || ['nha-bdg-gray', st || '—'];
    return `<span class="nha-bdg ${cls}">${rl_esc(label)}</span>`;
}

/* Open the Remittance DV in the voucher view modal — its footer has the approve / forward / reject / release buttons for whoever may use them. */
function rl_reviewDv(id) {
    if (typeof vr_open !== 'function') { window.location.href = `${RL_VOUCHER}?dv=${Number(id)}`; return; }
    vr_open(Number(id));
}

/* ── Pagination ────────────────────────────────────────────── */
function rl_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = RL_PAGE_SIZE } = p;
    $rl('rl-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $rl('rl-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $rl('rl-total').textContent = total;

    const box = $rl('rl-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="rl_goTo(${i})">${i}</button>`);
    }
    box.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="rl_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="rl_goTo(${current_page + 1})">›</button>`;
}
function rl_goTo(page) { if (page >= 1) { rl_page = page; rl_load(); } }

/* ── Export ────────────────────────────────────────────────── */
async function rl_exportCsv() {
    window.PagePreloader?.show();
    try {
        const res  = await rl_authFetch(`${RL_API}/all?${new URLSearchParams(rl_filters())}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to export.');
        const rows = json.data ?? [];
        if (!rows.length) { rl_toast('Nothing to download.', 'warning'); return; }
        const cell = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
        const head = ['DV Number', 'DV Date', 'Payee', 'Deduction', 'ATC Code', 'Rate (%)', 'Amount Based On', 'Amount Withheld', 'Account Code', 'Account Title', 'Goes To', 'Status', 'DV Status', 'Remittance DV'];
        const lines = [head.map(cell).join(',')].concat(rows.map(r => [
            r.dv_no, String(r.dv_date || '').slice(0, 10), r.claimant_name, r.type_name, r.atc_code, r.rate, r.base, r.amount,
            r.account_code, r.account_title, RL_AGENCY[r.agency] || r.agency, rl_statusLabel(r.remit_status), r.dv_status, r.remit_dv_no,
        ].map(cell).join(',')));
        const url = URL.createObjectURL(new Blob(['﻿' + lines.join('\n')], { type: 'text/csv;charset=utf-8' }));
        const a = Object.assign(document.createElement('a'), { href: url, download: `remittance_list_${new Date().toISOString().slice(0, 10)}.csv` });
        a.click();
        URL.revokeObjectURL(url);
    } catch (e) {
        rl_toast('Download: ' + e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Badges / formatters ───────────────────────────────────── */
function rl_statusLabel(s) { return { READY: 'Ready to remit', WITHHELD: 'Waiting for release', IN_DV: 'Being processed', REMITTED: 'Paid', NOT_REMITTED: 'Kept — not paid out' }[s] || s || '—'; }
function rl_statusBadge(s) {
    const cls = { READY: 'nha-bdg-green', WITHHELD: 'nha-bdg-amber', IN_DV: 'nha-bdg-blue', REMITTED: 'nha-bdg-teal', NOT_REMITTED: 'nha-bdg-gray' }[s] || 'nha-bdg-gray';
    return `<span class="nha-bdg ${cls}">${rl_esc(rl_statusLabel(s))}</span>`;
}
function rl_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function rl_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function rl_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function rl_showError(msg) { $rl('rl-error').style.display = 'flex'; $rl('rl-error-msg').textContent = msg; }
function rl_hideError()    { $rl('rl-error').style.display = 'none'; }

function rl_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const color = colors[type] || colors.info;
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    const span = document.createElement('span');
    span.style.flex = '1';
    span.textContent = msg;
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
