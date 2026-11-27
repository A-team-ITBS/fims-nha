<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Remittance List (Deductions) — the taxes approved disbursement vouchers withheld, and the one monthly DV that pays them to BIR (Reports menu).
// The data comes from the REST API (app/routes/remittance_list.php → RemittanceListService), no direct DB queries on this page.
//
// The page is about ONE thing: the taxes that will be remitted. Pick a YEAR, then one of its MONTHS (the voucher's DV date) and it shows, top to bottom:
//   1) what will be paid to BIR — the total, per tax (withholding tax · withholding VAT · penalty tax), every vendor — and the single "Create DV" button;
//   2) tab "To remit": the tax lines behind that amount (vouchers already RELEASED and in no remittance DV yet — exactly what the DV will pay);
//   3) tab "Remitted": what the month has already put in a remittance DV (still in process or paid), grouped by DV. The tab only appears when
//      the month has any.
// ONE remittance DV per month pays all of it: nothing to pick (no tax, no vendor) and no FMD step — the DV goes from Draft straight to
// Accounting. Only Accounting Department staff (RC 012) and the superadmin create it (RemittanceListService::canCreateDv(), enforced on the
// server; the summary carries `can_create` and the button is replaced by a note for everyone else). Retention, advance recoupment and other deductions are not remitted to BIR, so they are not on this page. Payroll deductions
// have their own page (Payroll → Remittance).
// API.FIMS_URL is the site root; the REST routes live under /api (added unless the env value already ends in it).
$_rl_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_rl_api = preg_match('~/api$~', $_rl_api) ? $_rl_api : $_rl_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<!-- nhaSearchableSelect — the account / ATC pickers of the DV form and view modal included at the bottom of this page need it -->
<script src="/_shared/js/searchable-select.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/js/searchable-select.js') ?>"></script>

<style>
#rl-root .rl-panel { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; overflow: hidden; background: var(--afims-surface, #fff); }
#rl-root .rl-panel .nha-tbl-scroll { margin: 0; }
#rl-root .rl-empty { padding: 26px 16px; text-align: center; font-size: 13px; color: var(--afims-text-muted, #62657E); }
#rl-root .rl-empty strong { display: block; margin-bottom: 4px; font-size: 14px; color: var(--afims-text, #1e293b); }
#rl-root .rl-sub { font-size: 11.5px; color: var(--afims-text-muted, #62657E); margin-top: 2px; }
#rl-root .rl-lbl { font-size: 11.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); }
/* 1. What will be paid to BIR */
#rl-root .rl-sum { margin: 0 0 18px; }
#rl-root .rl-sum-top { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px 24px; padding: 16px 18px; }
#rl-root .rl-sum-amt { font-family: "IBM Plex Mono", monospace; font-size: 28px; font-weight: 600; line-height: 1.2; color: var(--afims-text, #1e293b); }
#rl-root .rl-sum-amt.none { color: var(--afims-text-muted, #62657E); }
#rl-root .rl-sum-act { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
#rl-root .rl-sum-act .nha-btn { padding: 10px 26px; font-size: 14px; white-space: nowrap; }
#rl-root .rl-sum-act .nha-btn[disabled] { opacity: .5; cursor: not-allowed; }
#rl-root .rl-tiles { display: flex; flex-wrap: wrap; gap: 10px; padding: 0 18px 16px; }
#rl-root .rl-tile { min-width: 190px; padding: 9px 14px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; background: var(--afims-surface-subtle, #F4F7FA); }
#rl-root .rl-tile-amt { font-family: "IBM Plex Mono", monospace; font-size: 15px; font-weight: 600; }
#rl-root .rl-wait { padding: 10px 18px; border-top: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-warning-bg, #FFF8E5); color: var(--afims-warning-text, #8A6400); font-size: 12.5px; }
/* Tabs: one list on screen at a time */
#rl-root .rl-tabs { display: flex; gap: 4px; margin: 0 0 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#rl-root .rl-tab { border: 0; background: none; padding: 10px 16px; margin-bottom: -1px; font: inherit; font-size: 13.5px; font-weight: 600; color: var(--afims-text-muted, #62657E); cursor: pointer; border-bottom: 2px solid transparent; }
#rl-root .rl-tab:hover { color: var(--afims-text, #1e293b); }
#rl-root .rl-tab.active { color: var(--afims-primary, #0035A9); border-bottom-color: var(--afims-primary, #0035A9); }
#rl-root .rl-tab-badge { display: inline-block; min-width: 18px; margin-left: 6px; padding: 1px 6px; border-radius: 999px; font-size: 11px; line-height: 16px; text-align: center; color: #fff; background: var(--afims-primary, #0035A9); }
#rl-root .rl-tab-badge:empty { display: none; }
#rl-root .rl-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#rl-root .rl-toolbar .nha-filter-search-wrap { min-width: 260px; }
/* Lists: a header row per tax (To remit) or per remittance DV (Remitted) */
#rl-root tr.rl-grp td { padding: 10px 12px; background: var(--afims-surface-subtle, #F4F7FA); border-top: 1px solid var(--afims-border, #E4ECF2); font-weight: 700; font-size: 13px; }
#rl-root :is(tr.rl-grp, tr.rl-total) .rl-grp-sum { float: right; font-family: "IBM Plex Mono", monospace; }
#rl-root tr.rl-grp .rl-grp-meta { margin-left: 10px; font-weight: 400; font-size: 12px; color: var(--afims-text-muted, #62657E); }
/* the shared .nha-tbl forces header cells left and only knows th.text-right: right-align the amount columns' headers over their figures */
#rl-root .nha-tbl thead th.text-end { text-align: right; }
#rl-sum-body td.nha-mono { white-space: nowrap; }
#rl-sum-body th { white-space: nowrap; }
#rl-root .rl-h { margin: 22px 0 8px; font-size: 14px; font-weight: 700; color: var(--afims-text, #1e293b); }
#rl-root .rl-h small { margin-left: 8px; font-size: 12px; font-weight: 400; color: var(--afims-text-muted, #62657E); }
#rl-root tr.rl-rate td { padding-top: 5px; padding-bottom: 5px; font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
#rl-root tr.rl-rate td:first-child { padding-left: 28px; }
#rl-sum-body tr.rl-rate td:first-child .rl-sub { max-width: 440px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#rl-root tr.rl-total td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); border-top: 2px solid var(--afims-border, #E4ECF2); }
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
            <button id="rl-summary-btn" type="button" class="nha-btn-hdr" title="The selected month in detail: each tax by ATC code, each vendor, and what is remitted">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-4M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Summary of Tax Deductions
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="rl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="rl-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS — first the year, then one of its months; the month drives everything below ── -->
    <div class="nha-filters no-print" style="margin-bottom:14px;">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rl-filter-year">Year</label>
                <select id="rl-filter-year" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rl-filter-period">Month</label>
                <select id="rl-filter-period" class="nha-filter-select"></select>
            </div>
        </div>
    </div>

    <!-- ── 1. WHAT WILL BE PAID TO BIR — the total, per tax, and the one Create DV button ── -->
    <div class="rl-panel rl-sum" id="rl-summary">
        <div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>
    </div>

    <!-- ── TABS — "To remit" always; "Remitted" only when the month already has something in a remittance DV ── -->
    <div class="rl-tabs no-print" role="tablist" id="rl-tabs" style="display:none;">
        <button type="button" class="rl-tab active" id="rl-tab-open" role="tab" aria-selected="true" aria-controls="rl-pane" onclick="rl_setTab('open')">To remit<span class="rl-tab-badge" id="rl-badge-open"></span></button>
        <button type="button" class="rl-tab" id="rl-tab-done" role="tab" aria-selected="false" aria-controls="rl-pane" onclick="rl_setTab('done')" style="display:none;">Remitted<span class="rl-tab-badge" id="rl-badge-done"></span></button>
    </div>

    <!-- ── 2. THE TAX LINES of the open tab ── -->
    <div class="rl-panel" id="rl-pane" role="tabpanel" style="display:none;">
        <div class="rl-toolbar no-print" id="rl-toolbar">
            <div class="nha-filter-search-wrap">
                <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                </svg>
                <input type="text" id="rl-search" class="nha-filter-input" placeholder="Search DV number, vendor, ATC code…" aria-label="Search the list">
            </div>
            <span class="rl-sub" id="rl-count" style="margin:0;"></span>
        </div>
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead id="rl-thead"></thead>
                <tbody id="rl-tbody"></tbody>
            </table>
        </div>
    </div>

    <!-- ── SUMMARY OF TAX DEDUCTIONS — the selected month in detail ── -->
    <div id="rl-sum-overlay" class="nha-overlay no-print" onclick="if(event.target===this)rl_closeSummary()">
        <div class="nha-modal u-modal-w-90" role="dialog" aria-modal="true" aria-labelledby="rl-sum-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="rl-sum-title">Summary of Tax Deductions</h3>
                <button type="button" class="nha-modal-close" onclick="rl_closeSummary()" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="rl-sum-body"></div>
            <div class="nha-modal-foot no-print">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="rl_closeSummary()">Close</button>
            </div>
        </div>
    </div>

</div><!-- /#rl-root -->

<?php
include __DIR__ . '/voucher/form.php';   // the DV add / edit modal — "Create DV" opens it filled in (vf_openForDvRemit) and it calls window.vf_onSaved after saving
include __DIR__ . '/voucher/readone.php'; // the DV view modal — opens a remittance DV (vr_open); its footer has the submit / forward / reject / release buttons and it calls window.vf_onSaved after a status change
?>

<script>
/* ══════════════════════════════════════════════════════════════
   REMITTANCE LIST (DEDUCTIONS)   prefix: rl_
═══════════════════════════════════════════════════════════════ */

const RL_API     = <?= json_encode($_rl_api) ?> + '/remittance-list';
const RL_VOUCHER = <?= json_encode(GetUrl('Voucher')) ?>; // ?dv=<dv_id> opens that voucher's view

const RL_TAX = { EWT: 'Withholding Tax (EWT)', VAT: 'Withholding VAT', PENALTY_TAX: 'Penalty Tax' };

let rl_periods = [];     // every month that has BIR tax withheld, newest first — the Year filter lists their years, the Month filter the chosen year's months
let rl_period  = '';     // the selected month, 'YYYY-MM'
let rl_sum     = {};     // the month's summary (GET summary)
let rl_open    = [];     // tax lines the next Create DV will pay
let rl_done    = [];     // tax lines already in a remittance DV
let rl_tab     = 'open'; // 'open' | 'done'
let rl_showDoneAfterLoad = false;   // set after a DV was just created: land on the Remitted tab, where it now is

const $rl = id => document.getElementById(id);

function rl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function rl_getJson(path, params) {
    const res = await rl_authFetch(`${RL_API}/${path}?${new URLSearchParams(params)}`);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

async function rl_boot() {
    await rl_loadPeriods();   // opens on the latest month that has taxes to remit
    rl_bindEvents();
    rl_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', rl_boot);
else rl_boot();

function rl_bindEvents() {
    $rl('rl-refresh')?.addEventListener('click', async () => { await rl_loadPeriods(); rl_load(); });
    $rl('rl-summary-btn')?.addEventListener('click', rl_openSummary);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') rl_closeSummary(); });
    $rl('rl-filter-year')?.addEventListener('change', () => { rl_fillMonths(); rl_changed(); });
    $rl('rl-filter-period')?.addEventListener('change', rl_changed);
    $rl('rl-search')?.addEventListener('input', rl_renderList);
}

/* A different month was chosen: back to the "To remit" list, with a clean search. */
function rl_changed() { rl_tab = 'open'; $rl('rl-search').value = ''; rl_load(); }

/* Year, then month. `periods` lists every month that has BIR tax withheld (the month is the voucher's DV date). The Year filter has the years of
   those months — no "all years"; the Month filter has the months of the chosen year (names only). A selection that is still there stays;
   otherwise the latest year / month that has taxes to remit (so the page opens ready to create the DV), else the latest. */
const rl_best = list => list.find(p => Number(p.ready) > 0) || list[0] || null;

async function rl_loadPeriods() {
    try {
        rl_periods = (await rl_getJson('periods', {})).data ?? [];
    } catch (e) {
        rl_periods = [];
        rl_toast('Months: ' + e.message, 'error');
    }
    const ySel  = $rl('rl-filter-year');
    const keep  = ySel.value;
    const years = [...new Set(rl_periods.map(p => p.period.slice(0, 4)))];   // periods are newest first, so the years are too
    ySel.innerHTML = years.length ? years.map(y => `<option value="${rl_esc(y)}">${rl_esc(y)}</option>`).join('') : '<option value="">—</option>';
    ySel.value = years.includes(keep) ? keep : ((rl_best(rl_periods) || {}).period || '').slice(0, 4);
    rl_fillMonths();
}

function rl_fillMonths() {
    const mSel = $rl('rl-filter-period');
    const keep = mSel.value;
    const list = rl_periods.filter(p => p.period.startsWith($rl('rl-filter-year').value + '-'));
    mSel.innerHTML = list.length
        ? list.map(p => `<option value="${rl_esc(p.period)}">${rl_esc(p.period_label.replace(/\s+\d{4}$/, ''))}</option>`).join('')
        : '<option value="">No taxes withheld yet</option>';
    mSel.value = list.some(p => p.period === keep) ? keep : ((rl_best(list) || {}).period || '');
}

/* ── Load ──────────────────────────────────────────────────── */
async function rl_load() {
    rl_period = $rl('rl-filter-period').value;
    rl_hideError();
    if (!rl_period) { rl_sum = {}; rl_open = []; rl_done = []; rl_renderAll(); return; }
    window.PagePreloader?.show();
    try {
        rl_sum = (await rl_getJson('summary', { period: rl_period })).data ?? {};
        const [open, done] = await Promise.all([
            rl_getJson('taxes', { period: rl_period, scope: 'OPEN' }),
            rl_sum.has_remitted ? rl_getJson('taxes', { period: rl_period, scope: 'REMITTED' }) : Promise.resolve({ data: [] }),
        ]);
        rl_open = open.data ?? [];
        rl_done = done.data ?? [];
    } catch (e) {
        rl_sum = {}; rl_open = []; rl_done = [];
        rl_showError('Could not load the data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
    // nothing left to remit but the month has remitted taxes (or a DV was just created): show those instead of an empty list
    if (rl_done.length && (rl_showDoneAfterLoad || !rl_open.length)) rl_tab = 'done';
    rl_showDoneAfterLoad = false;
    if (rl_tab === 'done' && !rl_done.length) rl_tab = 'open';
    rl_renderAll();
}

function rl_renderAll() {
    rl_renderSummary();
    const hasDone = rl_done.length > 0;
    // the tab bar only exists when the month has something already remitted; otherwise the list sits directly under the summary
    $rl('rl-tabs').style.display = hasDone ? '' : 'none';
    $rl('rl-tab-done').style.display = hasDone ? '' : 'none';
    $rl('rl-badge-open').textContent = rl_open.length ? String(new Set(rl_open.map(l => l.dv_id)).size) : '';
    $rl('rl-badge-done').textContent = hasDone ? String((rl_sum.dvs || []).length || '') : '';
    rl_setTab(rl_tab);
}

function rl_setTab(tab) {
    rl_tab = tab === 'done' && rl_done.length ? 'done' : 'open';
    [['rl-tab-open', rl_tab === 'open'], ['rl-tab-done', rl_tab === 'done']].forEach(([id, on]) => {
        $rl(id).classList.toggle('active', on);
        $rl(id).setAttribute('aria-selected', String(on));
    });
    $rl('rl-pane').style.display = rl_period && Object.keys(rl_sum).length ? '' : 'none';
    rl_renderList();
}

/* ── 1. What will be paid to BIR ───────────────────────────── */
/* The total the next DV pays, per tax, and the ONE "Create DV" button. It opens the new-DV FORM right here (the Voucher page's own, included below)
   filled in: claimant BIR, the month's total, the breakdown by tax and rate, entry Dr the liabilities / Cr Cash in Bank, with the voucher-by-voucher
   schedule. Accounting reviews it and saves it as a draft or submits it (straight to Accounting — there is no FMD step); saving creates the DV and
   links the month's released vouchers, then this page reloads and the DV shows under "Remitted". */
function rl_remit() {
    if (rl_sum.can_create === false) { rl_toast('Only Accounting Department staff can create the remittance DV.', 'warning'); return; }
    if (!rl_period) { rl_toast('Choose a month first — one remittance DV pays one month.', 'warning'); return; }
    if (typeof vf_openForDvRemit !== 'function') { rl_toast('The voucher form is not available on this page.', 'error'); return; }
    vf_openForDvRemit(rl_period);
}

// the voucher form's hooks: it reports a successful save here and borrows this page's toast for its warnings
window.vf_onSaved = (data, message) => {
    rl_toast(message || 'Remittance DV saved.', 'success');
    rl_showDoneAfterLoad = true;
    rl_loadPeriods().then(rl_load);
};
window.vl_toast = (msg, type) => rl_toast(msg, type);

function rl_renderSummary() {
    const box = $rl('rl-summary');
    const s = rl_sum;
    if (!rl_period || !Object.keys(s).length) {
        box.innerHTML = `<div class="rl-empty"><strong>No taxes to remit yet</strong>A month appears here once a voucher with a withholding tax, withholding VAT or penalty tax is approved.</div>`;
        return;
    }
    const ready = Number(s.ready) || 0;
    const nv    = Number(s.ready_vouchers) || 0;
    const nw    = Number(s.withheld_vouchers) || 0;

    let sub;
    if (ready > 0) sub = `${nv} released voucher${nv === 1 ? '' : 's'} · every tax, every vendor, in one DV`;
    else if (s.has_remitted && !(Number(s.withheld) > 0)) sub = 'Everything withheld in ' + s.period_label + ' is already in a remittance DV.';
    else if (Number(s.withheld) > 0) sub = 'Nothing has been released yet.';
    else sub = '';

    const top = `<div class="rl-sum-top">
        <div><div class="rl-lbl">${ready > 0 ? 'To remit to BIR' : 'Nothing to remit'} — ${rl_esc(s.period_label)}</div>
            <div class="rl-sum-amt${ready > 0 ? '' : ' none'}">${rl_peso(ready)}</div>
            ${sub ? `<div class="rl-sub">${rl_esc(sub)}</div>` : ''}</div>
        <div class="rl-sum-act no-print">${s.can_create === false
            // the remittance DV is Accounting's (server-enforced); everyone else with access to this page just reads it
            ? '<p class="rl-sub" style="max-width:260px;text-align:right;margin:0;">Only Accounting Department staff can create the remittance DV.</p>'
            : `<button type="button" class="nha-btn nha-btn-primary" onclick="rl_remit()" ${s.can_remit ? '' : 'disabled'}
                title="${s.can_remit ? 'Prepare the one DV that pays BIR every tax listed below for ' + rl_esc(s.period_label) : 'Needs released vouchers whose taxes are not in a remittance DV yet'}">Create DV</button>`}
        </div></div>`;

    // per tax — only the taxes this DV will pay; the rates under each
    const tiles = (s.taxes || []).filter(t => Number(t.ready) > 0).map(t => {
        const rates = (t.rates || []).filter(r => r.rate !== null).map(r => `${rl_rate(r.rate)} ${rl_peso(r.amount)}`).join(' · ');
        return `<div class="rl-tile"><div class="rl-lbl">${rl_esc(t.type_name)}</div><div class="rl-tile-amt">${rl_peso(t.ready)}</div>${rates ? `<div class="rl-sub">${rl_esc(rates)}</div>` : ''}</div>`;
    }).join('');

    const wait = Number(s.withheld) > 0
        ? `<div class="rl-wait">${rl_peso(s.withheld)} from ${nw} voucher${nw === 1 ? ' is' : 's are'} not released yet, so ${nw === 1 ? 'it is' : 'they are'} not in this DV. A follow-up DV can pay ${nw === 1 ? 'it' : 'them'} once released.</div>` : '';

    box.innerHTML = top + (tiles ? `<div class="rl-tiles">${tiles}</div>` : '') + wait;
}

/* ── 2. The lists ──────────────────────────────────────────── */
function rl_matches(l, q) {
    return !q || [l.dv_no, l.claimant_name, l.atc_code, l.type_name, l.remit_dv_no].some(v => String(v ?? '').toLowerCase().includes(q));
}

function rl_renderList() {
    const q = $rl('rl-search').value.trim().toLowerCase();
    const done = rl_tab === 'done';
    const lines = (done ? rl_done : rl_open).filter(l => rl_matches(l, q));
    const bare  = !lines.length && !q;   // nothing at all to list: just the message, no search box or column headers
    $rl('rl-toolbar').style.display = bare ? 'none' : '';
    $rl('rl-count').textContent = lines.length ? `${lines.length} line${lines.length === 1 ? '' : 's'} · ${new Set(lines.map(l => l.dv_id)).size} voucher${new Set(lines.map(l => l.dv_id)).size === 1 ? '' : 's'}` : '';

    // To remit: grouped by tax (the columns are the same in every group). Remitted: grouped by remittance DV, with the tax named on each line.
    $rl('rl-thead').innerHTML = bare ? '' : `<tr><th>DV number</th><th>Date</th><th>Vendor</th><th>${done ? 'Tax' : 'ATC code'}</th><th class="text-end">Rate</th><th class="text-end">Tax base</th><th class="text-end">${done ? 'Tax in the DV' : 'Tax to remit'}</th></tr>`;
    const tbody = $rl('rl-tbody');
    if (!lines.length) {
        tbody.innerHTML = `<tr><td colspan="7"><div class="rl-empty">${rl_emptyText(done, q)}</div></td></tr>`;
        return;
    }
    const groups = [];
    lines.forEach(l => {
        const key = done ? 'd' + l.remit_dv_id : l.type_key;
        let g = groups.find(x => x.key === key);
        if (!g) groups.push(g = { key, line: l, rows: [], sum: 0 });
        g.rows.push(l);
        g.sum += Number(l.amount) || 0;
    });
    tbody.innerHTML = groups.map(g => {
        const n = g.rows.length;
        const head = done
            ? `<a class="nha-tbl-link nha-mono" href="${rl_esc(RL_VOUCHER)}?dv=${Number(g.line.remit_dv_id)}" title="Open this remittance DV" onclick="event.preventDefault();rl_reviewDv(${Number(g.line.remit_dv_id)})">Remittance DV ${rl_esc(g.line.remit_dv_no)}</a> ${rl_dvStageBadge(g.line.remit_dv_status)}`
            : rl_esc(RL_TAX[g.line.type_key] || g.line.type_name);
        return `<tr class="rl-grp"><td colspan="7"><span class="rl-grp-sum">${rl_peso(g.sum)}</span>${head}<span class="rl-grp-meta">${n} line${n === 1 ? '' : 's'}</span></td></tr>` +
            g.rows.map(l => `<tr>
                <td class="nha-mono"><a href="${rl_esc(RL_VOUCHER)}?dv=${Number(l.dv_id)}" class="nha-tbl-link" title="Open this voucher" onclick="event.preventDefault();rl_reviewDv(${Number(l.dv_id)})">${rl_esc(l.dv_no) || '—'}</a></td>
                <td class="nha-td-date">${rl_fmtDate(l.dv_date)}</td>
                <td style="max-width:260px;" title="${rl_esc(l.claimant_name)}"><div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${rl_esc(l.claimant_name) || '—'}</div></td>
                <td>${done ? rl_esc(RL_TAX[l.type_key] || l.type_name) + (l.atc_code ? ` <span class="nha-mono rl-sub">${rl_esc(l.atc_code)}</span>` : '') : (l.atc_code ? `<span class="nha-mono">${rl_esc(l.atc_code)}</span>` : '<span class="rl-sub">—</span>')}</td>
                <td class="text-end nha-mono">${rl_rate(l.rate)}</td>
                <td class="text-end nha-mono">${rl_peso(l.base)}</td>
                <td class="text-end nha-mono" style="font-weight:600;">${rl_peso(l.amount)}</td>
            </tr>`).join('');
    }).join('') + (done ? '' : `<tr class="rl-total"><td colspan="7"><span class="rl-grp-sum">${rl_peso(lines.reduce((a, l) => a + (Number(l.amount) || 0), 0))}</span>Total to remit${q ? ' (matching the search)' : ''}</td></tr>`);
}

function rl_emptyText(done, q) {
    if (q) return 'Nothing matches the search.';
    if (done) return 'Nothing of this month is in a remittance DV yet.';
    const s = rl_sum;
    if (!rl_period) return '<strong>No taxes to remit yet</strong>';
    if (s.has_remitted && !(Number(s.withheld) > 0)) return '<strong>Nothing left to remit</strong>Everything withheld in ' + rl_esc(s.period_label) + ' is already in a remittance DV — see the Remitted tab.';
    if (Number(s.withheld) > 0) return '<strong>Nothing to remit yet</strong>The vouchers of ' + rl_esc(s.period_label) + ' have not been released. Their taxes appear here once they are.';
    return '<strong>Nothing to remit</strong>';
}

/* Where a Remittance DV stands: with whom it is now, or that it was paid / is not going ahead. (No FMD — the DV goes from Draft straight to Accounting.) */
function rl_dvStageBadge(st) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'], FOR_FMD_REVIEW: ['nha-bdg-amber', 'With FMD'], FOR_ACCOUNTING: ['nha-bdg-blue', 'With Accounting'],
        FOR_DIVISION_MANAGER: ['nha-bdg-purple', 'With Division Manager'], FOR_DEPARTMENT_MANAGER: ['nha-bdg-navy', 'With Department Manager'],
        FOR_TREASURY: ['nha-bdg-teal', 'With Treasury'], RELEASED: ['nha-bdg-green', 'Paid'], REJECTED: ['nha-bdg-red', 'Rejected'],
    };
    const [cls, label] = map[st] || ['nha-bdg-gray', st || '—'];
    return `<span class="nha-bdg ${cls}">${rl_esc(label)}</span>`;
}

/* Open a voucher in the voucher view modal — for a remittance DV its footer has the submit / forward / reject / release buttons for whoever may use them. */
function rl_reviewDv(id) {
    if (typeof vr_open !== 'function') { window.location.href = `${RL_VOUCHER}?dv=${Number(id)}`; return; }
    vr_open(Number(id));
}

/* ── Summary of Tax Deductions ─────────────────────────────── */
/* The month shown on the page, IN DETAIL: the month's totals (remitted / to remit / not released yet), each tax by ATC code and rate, each vendor with its
   TIN, and the remittance DVs already made. */
function rl_monthLabel(period) {
    const p = rl_periods.find(x => x.period === period);
    if (p) return p.period_label;
    const d = new Date(period + '-01T00:00:00');
    return isNaN(d) ? period : d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
}

async function rl_openSummary() {
    const period = $rl('rl-filter-period').value;
    $rl('rl-sum-title').textContent = 'Summary of Tax Deductions' + (period ? ' — ' + rl_monthLabel(period) : '');
    $rl('rl-sum-overlay').classList.add('open');
    const body = $rl('rl-sum-body');
    if (!period) { body.innerHTML = '<div class="rl-empty"><strong>No taxes withheld yet</strong></div>'; return; }
    body.innerHTML = '<div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>';
    try {
        const json = await rl_getJson('tax-summary', { period });
        if ($rl('rl-filter-period').value !== period) return;   // another month was picked on the page meanwhile
        body.innerHTML = rl_renderMonthDetail(json.data ?? {});
    } catch (e) {
        body.innerHTML = `<div class="rl-empty">Could not load the summary: ${rl_esc(e.message)}</div>`;
    }
}

function rl_closeSummary() { $rl('rl-sum-overlay')?.classList.remove('open'); }

/* One month in detail. Everything adds up to the month's total: by tax, by rate within a tax, and by vendor; and total = remitted + to remit + not released. */
function rl_renderMonthDetail(d) {
    const t   = d.totals || {};
    const amt = v => Number(v) > 0 ? rl_peso(v) : '<span class="rl-sub">—</span>';
    const nv  = c => `${Number(c) || 0} voucher${Number(c) === 1 ? '' : 's'}`;
    if (!Number(t.total)) return `<div class="rl-empty"><strong>No taxes withheld in ${rl_esc(d.period_label || '')}</strong></div>`;

    const tile  = (label, amount, count, note) => `<div class="rl-tile"><div class="rl-lbl">${label}</div><div class="rl-tile-amt">${rl_peso(amount)}</div><div class="rl-sub">${nv(count)} · ${note}</div></div>`;
    const tiles = '<div class="rl-tiles" style="padding:0 0 2px;">' +
        tile('Total withheld', t.total, t.vouchers, 'all three taxes') +
        tile('Remitted', t.remitted, t.remitted_vouchers, 'in a remittance DV') +
        tile('To remit', t.ready, t.ready_vouchers, 'released, not in a DV yet') +
        tile('Not released yet', t.withheld, t.withheld_vouchers, 'voucher not released') + '</div>';

    // the split of an amount: already in a remittance DV / ready for the next one / voucher not released yet
    const split = b => `<td class="text-end nha-mono">${amt(b.remitted)}</td><td class="text-end nha-mono">${amt(b.ready)}</td><td class="text-end nha-mono">${amt(b.withheld)}</td>`;
    const splitHead = '<th class="text-end">Remitted</th><th class="text-end">To remit</th><th class="text-end">Not released yet</th>';

    // 1) each tax, and under it each ATC code with its rate and description — BIR's withholding returns and the quarterly alphalist are organized by ATC
    //    (the VAT and Penalty Tax deductions carry no ATC code, so they are listed by rate)
    const taxRows = (d.by_tax || []).map(x => {
        const rows = (x.rates || []).filter(r => r.rate !== null || r.atc_code);
        const label = r => r.atc_code
            ? `<span class="nha-mono" style="font-weight:600;">${rl_esc(r.atc_code)}</span> · ${rl_rate(r.rate)}${r.atc_description ? `<div class="rl-sub" title="${rl_esc(r.atc_description)}">${rl_esc(r.atc_description)}</div>` : ''}`
            : `at ${rl_rate(r.rate)}`;
        return `<tr><td style="font-weight:700;">${rl_esc(x.type_name)}</td><td class="text-end">${Number(x.vouchers)}</td><td class="text-end nha-mono">${amt(x.base)}</td>
            <td class="text-end nha-mono" style="font-weight:700;">${rl_peso(x.total)}</td>${split(x)}</tr>` +
            rows.map(r => `<tr class="rl-rate"><td>${label(r)}</td><td class="text-end">${Number(r.vouchers)}</td><td class="text-end nha-mono">${amt(r.base)}</td>
                <td class="text-end nha-mono">${rl_peso(r.total)}</td>${split(r)}</tr>`).join('');
    }).join('');
    const byTax = `<div class="nha-tbl-scroll"><table class="nha-tbl">
        <thead><tr><th>Tax / ATC code / rate</th><th class="text-end">Vouchers</th><th class="text-end">Tax base</th><th class="text-end">Tax withheld</th>${splitHead}</tr></thead>
        <tbody>${taxRows}<tr class="rl-total"><td>Total</td><td class="text-end">${Number(t.vouchers)}</td><td></td><td class="text-end nha-mono">${rl_peso(t.total)}</td>${split(t)}</tr></tbody>
    </table></div>`;

    // 2) each vendor (payee) — the same people the BIR 2307s are issued to
    const taxTotal = k => ((d.by_tax || []).find(x => x.type_key === k) || {}).total || 0;
    const noTin = (d.by_vendor || []).filter(v => v.missing_tin);
    const tinCell = v => v.tin ? `<span class="nha-mono">${rl_esc(v.tin)}</span>` : (v.missing_tin ? '<span class="nha-bdg nha-bdg-amber" title="This payee has a creditable tax line, so its BIR 2307 and the alphalist need its TIN">No TIN</span>' : '<span class="rl-sub">—</span>');
    const vendorRows = (d.by_vendor || []).map(v => `<tr><td style="font-weight:600;">${rl_esc(v.vendor)}</td><td>${tinCell(v)}</td><td class="text-end">${Number(v.vouchers)}</td>
        ${Object.keys(RL_TAX).map(k => `<td class="text-end nha-mono">${amt((v.taxes || {})[k])}</td>`).join('')}
        <td class="text-end nha-mono" style="font-weight:700;">${rl_peso(v.total)}</td>${split(v)}</tr>`).join('');
    const byVendor = `<div class="nha-tbl-scroll"><table class="nha-tbl">
        <thead><tr><th>Vendor</th><th>TIN</th><th class="text-end">Vouchers</th>${Object.keys(RL_TAX).map(k => `<th class="text-end">${rl_esc(RL_TAX[k])}</th>`).join('')}<th class="text-end">Total withheld</th>${splitHead}</tr></thead>
        <tbody>${vendorRows}<tr class="rl-total"><td>Total</td><td></td><td class="text-end">${Number(t.vouchers)}</td>${Object.keys(RL_TAX).map(k => `<td class="text-end nha-mono">${amt(taxTotal(k))}</td>`).join('')}
            <td class="text-end nha-mono">${rl_peso(t.total)}</td>${split(t)}</tr></tbody>
    </table></div>`;

    // 3) the remittance DVs already made for the month
    const dvs = d.dvs || [];
    const dvBlock = dvs.length
        ? `<div class="nha-tbl-scroll"><table class="nha-tbl"><thead><tr><th>Remittance DV</th><th>Status</th><th class="text-end">Vouchers</th><th class="text-end">Tax in the DV</th></tr></thead><tbody>${dvs.map(v =>
            `<tr><td class="nha-mono"><a href="#" class="nha-tbl-link" title="Open this remittance DV" onclick="event.preventDefault();rl_reviewDv(${Number(v.dv_id)})">${rl_esc(v.dv_no)}</a></td>
                <td>${rl_dvStageBadge(v.status)}</td><td class="text-end">${Number(v.vouchers)}</td><td class="text-end nha-mono">${rl_peso(v.amount)}</td></tr>`).join('')}</tbody></table></div>`
        : `<p class="rl-sub" style="margin:0;">No remittance DV has been made for ${rl_esc(d.period_label)} yet.</p>`;

    return `<p class="rl-sub" style="margin:0 0 12px;">The month is the voucher's date. <strong>Remitted</strong> = already in a remittance DV (in process or paid) · <strong>To remit</strong> = released, not in a DV yet · <strong>Not released yet</strong> = the voucher is still with Treasury.</p>
        ${tiles}
        <h4 class="rl-h">By tax and ATC code</h4>${byTax}
        <h4 class="rl-h">By vendor <small>${(d.by_vendor || []).length} vendor${(d.by_vendor || []).length === 1 ? '' : 's'} — the payees the BIR 2307s are issued to</small></h4>
        ${noTin.length ? `<div class="rl-wait" style="margin:0 0 10px;border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;">${noTin.length} of ${(d.by_vendor || []).length} payee${noTin.length === 1 ? ' has' : 's have'} no TIN on record — a BIR 2307 and the alphalist need the payee's TIN. Add it to the vendor (or the voucher's TIN field) before issuing: ${noTin.map(v => rl_esc(v.vendor)).join(', ')}.</div>` : ''}
        ${byVendor}
        <h4 class="rl-h">Remittance DVs for ${rl_esc(d.period_label)}</h4>${dvBlock}`;
}

/* ── Formatters ────────────────────────────────────────────── */
function rl_rate(r) { return r !== null && r !== undefined && r !== '' ? Number(r) + '%' : '—'; }
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
