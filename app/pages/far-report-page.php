<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Financial Accountability Reports (DBM FAR 1, 1-A, 3, 4, 5) — read-only, one tab per report.
// Data: GET /api/far_report/{far1|far1a|far3|far4|far5} (app/routes/far_report.php, app/services/FarReportService.php).
// Tables behind each figure: FAR-ERD.md (repo root). Shown in the menu through Reports -> FMR (app/pages/fmr-page.php).
$_far_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_far_api = preg_match('~/api$~', $_far_api) ? $_far_api : $_far_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #far-root .nha-tabs { flex-wrap: wrap; }
    #far-root .far-about { display: flex; gap: 12px; align-items: flex-start; margin: 0 0 14px; padding: 12px 16px; border-radius: 10px;
        background: var(--afims-primary-soft, #EAF0FB); border: 1px solid var(--afims-border, #E4ECF2); font-size: 13px; line-height: 1.55;
        color: var(--afims-text, #1A1B21); }
    #far-root .far-about b { display: block; font-size: 14px; margin-bottom: 2px; }
    #far-root .far-about .far-badge { flex-shrink: 0; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-size: 12px;
        background: var(--afims-heading-fill, #000E3E); color: #fff; }
    #far-root .far-summary { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 14px; }
    @media (max-width: 900px) { #far-root .far-summary { grid-template-columns: repeat(2, 1fr); } }
    #far-root .far-filter-extra { display: none; }
    #far-root .far-filter-extra.show { display: flex; }
    #far-root .nha-tbl th, #far-root .nha-tbl td { white-space: nowrap; }
    /* FAR tables: bigger than the shared 11px / 12px — grows from 1rem (narrow screens) to 1.2rem (wide screens) */
    /* !important + the full table.nha-tbl path: the shared .nha-root .nha-tbl th / td sizes (11px / 12px) kept winning */
    #far-root table.nha-tbl,
    #far-root table.nha-tbl > thead > tr > th,
    #far-root table.nha-tbl > tbody > tr > td,
    #far-root table.nha-tbl > tfoot > tr > td { font-size: clamp(.875rem, .8rem + .2vw, 1rem) !important; }
    #far-root table.nha-tbl :is(.nha-td-sub, .nha-td-date, .nha-mono, .nha-code, .nha-bdg) { font-size: .85em !important; }
    @media print {
        #far-root table.nha-tbl > thead > tr > th,
        #far-root table.nha-tbl > tbody > tr > td,
        #far-root table.nha-tbl > tfoot > tr > td { font-size: 10px !important; }
    }
    #far-root .nha-tbl td.num, #far-root .nha-tbl th.num { text-align: right; font-variant-numeric: tabular-nums; }
    #far-root .nha-tbl .far-grp th { text-align: center; border-bottom: 1px solid rgba(255,255,255,.25); }
    #far-root .far-section td { background: var(--afims-surface-subtle, #F4F7FA); font-weight: 700; text-transform: uppercase; font-size: 12px; }
    #far-root .far-subtotal td { font-weight: 700; border-top: 1px solid var(--afims-border, #E4ECF2); }
    #far-root .nha-tbl tfoot td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); border-top: 2px solid var(--afims-border, #E4ECF2); }
    #far-root .far-neg { color: var(--afims-danger, #DC3545); }
    #far-root .far-zero { color: var(--afims-text-disabled, #A9AAB8); }
    #far-root .far-note { font-size: 12px; color: var(--afims-text-muted, #62657E); margin: 8px 2px 0; }
    #far-root .far-print-title { display: none; text-align: center; margin-bottom: 14px; font-weight: 700; line-height: 1.6; }

    html.afims-dark[data-bs-theme="dark"] #far-root .far-about { background: #0F2456; border-color: #2E3656; color: #E4ECF2; }
    html.afims-dark[data-bs-theme="dark"] #far-root .far-about .far-badge { background: #3861BC; }
    html.afims-dark[data-bs-theme="dark"] #far-root .far-section td,
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tbl tfoot td { background: var(--afims-surface-subtle, #22294A); color: #fff; border-color: var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #far-root .far-neg { color: #F87171; }
    html.afims-dark[data-bs-theme="dark"] #far-root .far-zero { color: #6B7390; }
    html.afims-dark[data-bs-theme="dark"] #far-root { color-scheme: dark; }
    /* filters: selects + the date picker took the browser's light defaults */
    html.afims-dark[data-bs-theme="dark"] #far-root :is(.nha-filter-select, input[type="date"]) {
        background-color: var(--afims-surface, #1A1F38) !important; border-color: var(--afims-border-strong, #4A5270) !important; color: #E4ECF2 !important; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-filter-select option { background: var(--afims-surface, #1A1F38); color: #E4ECF2; }
    html.afims-dark[data-bs-theme="dark"] #far-root :is(.nha-filter-select, input[type="date"]):focus {
        border-color: #3861BC !important; box-shadow: 0 0 0 3px rgba(56,97,188,.3); outline: none; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-filter-label { color: #AAAFBE; }
    /* tabs */
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tab { color: #AAAFBE; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tab:hover { color: #FFFFFF; background: #2E3656; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tab.active { background: #3861BC; color: #FFFFFF; }
    /* table: body text, two-row group header, sub lines, row borders */
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tbl td { color: #E4ECF2; border-color: #2E3656; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tbl thead th { background: #1B2B6B; color: #FFFFFF; border-color: #2E4A8F; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tbl .far-grp th { border-bottom-color: rgba(255,255,255,.2); }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-tbl tbody tr:hover td { background: rgba(56,97,188,.12); }
    html.afims-dark[data-bs-theme="dark"] #far-root :is(.nha-td-sub, .nha-td-date) { color: #9AA3B5; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-td-bold { color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #far-root .far-section td { background: #2E4A8F; color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #far-root .far-subtotal td { border-top-color: #4A5270; background: rgba(255,255,255,.03); }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-mono { color: #94AADB; }
    html.afims-dark[data-bs-theme="dark"] #far-root .far-note { color: #9AA3B5; }
    /* "about" box text, and the gray object-class chip in FAR 4 */
    html.afims-dark[data-bs-theme="dark"] #far-root .far-about b { color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-bdg-gray { background: #2E3656; color: #E4ECF2; }
    /* header buttons on the navy banner */
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-btn-hdr { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.28); color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #far-root .nha-btn-hdr:hover { background: rgba(255,255,255,.22); }

    @media print {
        #far-root .far-print-title { display: block; }
        #far-root .nha-tbl th, #far-root .nha-tbl td { font-size: 10px; padding: 4px 6px; }
    }
</style>

<div id="far-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Financial Accountability Reports</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="far-print" type="button" class="nha-btn-hdr" title="Print this report">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4H7v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
            <button id="far-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── REPORT TABS ────────────────────────────────────── -->
    <div class="nha-tabs no-print" id="far-tabs">
        <button type="button" class="nha-tab active" data-far="far1">FAR 1 · Budget Status</button>
        <button type="button" class="nha-tab" data-far="far1a">FAR 1-A · By Expense Type</button>
        <button type="button" class="nha-tab" data-far="far3">FAR 3 · Unpaid Obligations</button>
        <button type="button" class="nha-tab" data-far="far4">FAR 4 · Monthly Disbursements</button>
        <button type="button" class="nha-tab" data-far="far5">FAR 5 · Quarterly Receipts</button>
    </div>

    <!-- ── WHAT THIS REPORT SHOWS ─────────────────────────── -->
    <div class="far-about no-print">
        <span class="far-badge" id="far-about-code">FAR 1</span>
        <div><b id="far-about-title"></b><span id="far-about-text"></span></div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group" id="far-fy-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="far-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group far-filter-extra" data-for="far3">
                <label class="nha-filter-label">Unpaid as of</label>
                <input type="date" id="far-asof" class="nha-filter-select">
            </div>
            <div class="nha-filter-group far-filter-extra" data-for="far4">
                <label class="nha-filter-label">Month</label>
                <select id="far-month" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group far-filter-extra" data-for="far5">
                <label class="nha-filter-label">Quarter</label>
                <select id="far-quarter" class="nha-filter-select">
                    <option value="1">Q1 (Jan – Mar)</option>
                    <option value="2">Q2 (Apr – Jun)</option>
                    <option value="3">Q3 (Jul – Sep)</option>
                    <option value="4">Q4 (Oct – Dec)</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── SUMMARY CARDS ──────────────────────────────────── -->
    <div class="far-summary" id="far-cards"></div>

    <!-- ── REPORT TABLE ───────────────────────────────────── -->
    <div class="far-print-title" id="far-print-title"></div>
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead id="far-thead"></thead>
                <tbody id="far-tbody">
                    <tr><td><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
                <tfoot id="far-tfoot"></tfoot>
            </table>
        </div>
    </div>
    <p class="far-note" id="far-note"></p>
</div>

<script>
const FAR_API = '<?= $_far_api ?>' + '/far_report/';
const $far = id => document.getElementById(id);
const FAR_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];

// What each tab is, in plain words: title, explanation, footnote.
const FAR_INFO = {
    far1:  { code: 'FAR 1',   title: 'Statement of Appropriations, Allotments, Obligations, Disbursements and Balances',
             text: 'Where the budget stands for each project: how much was approved (appropriation), released (allotment), committed (obligation) and paid (disbursement), and what is left.',
             note: 'Appropriation = approved budget requests · Allotment = approved LAAs · Obligation = approved RFAs (by the quarter they were approved) · Disbursement = RFAs whose DV Treasury released.' },
    far1a: { code: 'FAR 1-A', title: 'Summary by Object of Expenditure',
             text: 'The same figures as FAR 1, grouped by type of expense: PS (personnel), MOOE (operating expenses) and CO (capital outlay), down to each account.',
             note: 'The expense type comes from the account code: 5-01 PS · 5-02 / 5-03 MOOE · 1-06 / 1-07 CO.' },
    far3:  { code: 'FAR 3',   title: 'Aging of Due and Demandable Obligations',
             text: 'Every approved obligation, from any fiscal year, that is still unpaid on the chosen date, and how long it has been waiting.',
             note: 'Days are counted from the IAR date (goods accepted) when the RFA has a PO, otherwise from the date the RFA was approved.' },
    far4:  { code: 'FAR 4',   title: 'Monthly Report of Disbursements',
             text: 'Payments made in the month (checks and ADA) for each bank account, compared with the cash allocation (NCA) received.',
             note: 'NCA balance = NCAs received up to the month\'s end − everything paid from January to the month\'s end.' },
    far5:  { code: 'FAR 5',   title: 'Quarterly Report of Revenues and Other Receipts',
             text: 'Money collected through Official Receipts, per revenue account, for each month of the quarter.',
             note: 'Year to date = every Official Receipt from January up to the end of the chosen quarter.' },
};

let far_tab = 'far1';

/* ── Helpers ─────────────────────────────────────────── */
function far_esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }
function far_money(v) {
    const n = Number(v || 0);
    const s = '₱' + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < 0 ? `<span class="far-neg">(${s})</span>` : (n === 0 ? `<span class="far-zero">${s}</span>` : s);
}
const far_num = v => `<td class="num">${far_money(v)}</td>`;
function far_card(color, label, value, sub) {
    return `<div class="nha-sc nha-sc-${color}"><div class="nha-sc-top"><div>
        <div class="nha-sc-label">${far_esc(label)}</div><div class="nha-sc-val">${value}</div></div></div>
        <p class="nha-kpi-sub">${far_esc(sub)}</p></div>`;
}
function far_empty(cols, msg = 'No data for the selected period.') {
    return `<tr><td colspan="${cols}"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${msg}</span></div></td></tr>`;
}
function far_fetch(url) {
    const headers = {};
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', headers }).then(r => r.json());
}

/* ── Boot ─────────────────────────────────────────────── */
function far_boot() {
    const now = new Date();
    const y = now.getFullYear();
    $far('far-fy').innerHTML = [y + 1, y, y - 1, y - 2].map(v => `<option value="${v}"${v === y ? ' selected' : ''}>${v}</option>`).join('');
    $far('far-month').innerHTML = FAR_MONTHS.map((m, i) => `<option value="${i + 1}"${i === now.getMonth() ? ' selected' : ''}>${m}</option>`).join('');
    $far('far-quarter').value = String(Math.floor(now.getMonth() / 3) + 1);
    $far('far-asof').value = now.toISOString().slice(0, 10);

    document.querySelectorAll('#far-tabs .nha-tab').forEach(b => b.addEventListener('click', () => far_switch(b.dataset.far)));
    ['far-fy', 'far-month', 'far-quarter', 'far-asof'].forEach(id => $far(id).addEventListener('change', far_load));
    $far('far-refresh').addEventListener('click', far_load);
    $far('far-print').addEventListener('click', () => window.print());
    far_switch('far1');
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', far_boot);
else far_boot();

function far_switch(tab) {
    far_tab = tab;
    document.querySelectorAll('#far-tabs .nha-tab').forEach(b => b.classList.toggle('active', b.dataset.far === tab));
    document.querySelectorAll('#far-root .far-filter-extra').forEach(f => f.classList.toggle('show', f.dataset.for === tab));
    $far('far-fy-group').style.display = tab === 'far3' ? 'none' : '';   // FAR 3: the "Unpaid as of" date is the only filter
    const info = FAR_INFO[tab];
    $far('far-about-code').textContent = info.code;
    $far('far-about-title').textContent = info.title;
    $far('far-about-text').textContent = info.text;
    $far('far-note').textContent = info.note;
    far_load();
}

/* ── Load ─────────────────────────────────────────────── */
async function far_load() {
    const tab = far_tab;
    // FAR 3 is a snapshot of every unpaid obligation as of a date, whatever its fiscal year: no fiscal_year
    const p = new URLSearchParams(tab === 'far3' ? {} : { fiscal_year: $far('far-fy').value });
    if (tab === 'far3') p.set('as_of', $far('far-asof').value);
    if (tab === 'far4') p.set('month', $far('far-month').value);
    if (tab === 'far5') p.set('quarter', $far('far-quarter').value);

    $far('far-tbody').innerHTML = '<tr><td><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $far('far-tfoot').innerHTML = '';
    try {
        const json = await far_fetch(FAR_API + tab + '?' + p);
        if (tab !== far_tab) return;   // the user switched tabs meanwhile
        if (!json || !json.success) throw new Error(json?.message || 'Could not load the report.');
        ({ far1: far_render1, far1a: far_render1a, far3: far_render3, far4: far_render4, far5: far_render5 })[tab](json);
        $far('far-print-title').innerHTML = `National Housing Authority<br>${far_esc(FAR_INFO[tab].code + ' — ' + FAR_INFO[tab].title)}<br>
            <span style="font-weight:400">${far_esc(far_periodLabel(json))}</span>`;
    } catch (e) {
        $far('far-cards').innerHTML = '';
        $far('far-thead').innerHTML = '';
        $far('far-tbody').innerHTML = far_empty(1, far_esc(e.message));
    }
}

function far_periodLabel(json) {
    if (far_tab === 'far3') return `Unpaid as of ${json.as_of}`;
    if (far_tab === 'far4') return `${FAR_MONTHS[json.month - 1]} ${json.fiscal_year}`;
    if (far_tab === 'far5') return `Q${json.quarter} (${json.months.join(', ')}) ${json.fiscal_year}`;
    return `Fiscal Year ${json.fiscal_year}`;
}

/* ── FAR 1 ────────────────────────────────────────────── */
function far_render1(json) {
    const t = json.totals || {};
    $far('far-cards').innerHTML =
        far_card('navy', 'Appropriation', far_money(t.appropriation), 'Approved budget') +
        far_card('blue', 'Allotment', far_money(t.allotment), `SARO received: ${far_money(t.saro_allotment).replace(/<[^>]+>/g, '')}`) +
        far_card('amber', 'Obligation', far_money(t.obligation), `Unobligated: ${far_money(t.unobligated_allotment).replace(/<[^>]+>/g, '')}`) +
        far_card('green', 'Disbursement', far_money(t.disbursement), `Unpaid: ${far_money(t.unpaid_obligation).replace(/<[^>]+>/g, '')}`);

    $far('far-thead').innerHTML = `
        <tr class="far-grp"><th rowspan="2">Program / Project</th><th rowspan="2" class="num">Appropriation</th><th rowspan="2" class="num">Allotment</th>
            <th colspan="5">Obligations</th><th colspan="5">Disbursements</th><th colspan="3">Balances</th></tr>
        <tr><th class="num">Q1</th><th class="num">Q2</th><th class="num">Q3</th><th class="num">Q4</th><th class="num">Total</th>
            <th class="num">Q1</th><th class="num">Q2</th><th class="num">Q3</th><th class="num">Q4</th><th class="num">Total</th>
            <th class="num">Not yet released</th><th class="num">Not yet obligated</th><th class="num">Not yet paid</th></tr>`;

    const cols = r => far_num(r.appropriation) + far_num(r.allotment)
        + [1, 2, 3, 4].map(q => far_num(r['obligation_q' + q])).join('') + far_num(r.obligation)
        + [1, 2, 3, 4].map(q => far_num(r['disbursement_q' + q])).join('') + far_num(r.disbursement)
        + far_num(r.unreleased_appropriation) + far_num(r.unobligated_allotment) + far_num(r.unpaid_obligation);

    const rows = json.data || [];
    if (!rows.length) { $far('far-tbody').innerHTML = far_empty(16); return; }

    let html = '', prog = null;
    rows.forEach(r => {
        if (r.program_code !== prog) {
            prog = r.program_code;
            html += `<tr class="far-section"><td colspan="16">Program ${far_esc(r.program_code || '—')} · ${far_esc(r.program_name || '')}</td></tr>`;
        }
        html += `<tr><td style="white-space:normal;min-width:260px;"><div class="nha-td-bold">${far_esc(r.project_name || '(No project)')}</div>
            <div class="nha-td-sub nha-mono">${far_esc(r.project_code || '')}</div></td>${cols(r)}</tr>`;
    });
    $far('far-tbody').innerHTML = html;
    $far('far-tfoot').innerHTML = `<tr><td>GRAND TOTAL</td>${cols(t)}</tr>`;
}

/* ── FAR 1-A ──────────────────────────────────────────── */
function far_render1a(json) {
    const t = json.totals || {};
    const groups = json.data || [];
    const sub = c => (groups.find(g => g.object_class === c)?.subtotal?.obligation) || 0;
    $far('far-cards').innerHTML =
        far_card('navy', 'Total Obligation', far_money(t.obligation), `of ${far_money(t.allotment).replace(/<[^>]+>/g, '')} allotted`) +
        far_card('purple', 'PS · Personnel', far_money(sub('PS')), 'Obligated') +
        far_card('blue', 'MOOE · Operating', far_money(sub('MOOE')), 'Obligated') +
        far_card('teal', 'CO · Capital Outlay', far_money(sub('CO')), 'Obligated');

    $far('far-thead').innerHTML = `<tr><th>Account</th><th class="num">Appropriation</th><th class="num">Allotment</th><th class="num">Obligation</th>
        <th class="num">Disbursement</th><th class="num">Not yet released</th><th class="num">Not yet obligated</th><th class="num">Not yet paid</th></tr>`;
    const cols = r => far_num(r.appropriation) + far_num(r.allotment) + far_num(r.obligation) + far_num(r.disbursement)
        + far_num(r.unreleased_appropriation) + far_num(r.unobligated_allotment) + far_num(r.unpaid_obligation);
    const NAMES = { PS: 'Personnel Services (PS)', MOOE: 'Maintenance and Other Operating Expenses (MOOE)', CO: 'Capital Outlay (CO)', Others: 'Others' };

    if (!groups.length) { $far('far-tbody').innerHTML = far_empty(8); return; }
    $far('far-tbody').innerHTML = groups.map(g =>
        `<tr class="far-section"><td colspan="8">${far_esc(NAMES[g.object_class] || g.object_class)}</td></tr>` +
        g.accounts.map(a => `<tr><td style="padding-left:24px;"><span class="nha-mono">${far_esc(a.account_code || '—')}</span> ${far_esc(a.account_title || '')}</td>${cols(a)}</tr>`).join('') +
        `<tr class="far-subtotal"><td style="padding-left:24px;">Subtotal ${far_esc(g.object_class)}</td>${cols(g.subtotal)}</tr>`
    ).join('');
    $far('far-tfoot').innerHTML = `<tr><td>GRAND TOTAL</td>${cols(t)}</tr>`;
}

/* ── FAR 3 ────────────────────────────────────────────── */
function far_render3(json) {
    const t = json.totals || {};
    $far('far-cards').innerHTML =
        far_card('green', '0 – 30 days', far_money(t['0_30']), 'Recently due') +
        far_card('amber', '31 – 90 days', far_money(t['31_90']), 'Follow up') +
        far_card('orange', '91 – 365 days', far_money(t['91_365']), 'Overdue') +
        far_card('red', 'Over 1 year', far_money(t.over_365), `Total unpaid: ${far_money(t.total).replace(/<[^>]+>/g, '')} (${t.count || 0})`);

    $far('far-thead').innerHTML = `<tr><th>RFA No.</th><th>Payee</th><th>Office</th><th>PO / DV</th><th>Obligated</th><th>Due since</th>
        <th class="num">Days</th><th>Age</th><th class="num">Amount</th></tr>`;
    const BADGE = { '0_30': ['green', '0 – 30 days'], '31_90': ['amber', '31 – 90 days'], '91_365': ['orange', '91 – 365 days'], over_365: ['red', 'Over 1 year'] };
    const rows = json.data || [];
    if (!rows.length) { $far('far-tbody').innerHTML = far_empty(9, 'No unpaid obligations on this date.'); return; }

    $far('far-tbody').innerHTML = rows.map(r => {
        const [color, label] = BADGE[r.age_bucket];
        return `<tr>
            <td><code class="nha-code">${far_esc(r.allocation_number || r.request_referrence_no)}</code><div class="nha-td-sub">${far_esc(r.project_code || '')}</div></td>
            <td>${far_esc(r.payee || '—')}</td>
            <td>${far_esc(r.rc_name || '—')}</td>
            <td>${far_esc(r.po_number || '—')}<div class="nha-td-sub">${r.dv_no ? 'DV ' + far_esc(r.dv_no) + ' (not yet released)' : 'No DV yet'}</div></td>
            <td class="nha-td-date">${far_esc(r.obligated_date)}</td>
            <td class="nha-td-date">${far_esc(r.due_date)}</td>
            <td class="num">${r.days_outstanding}</td>
            <td><span class="nha-bdg nha-bdg-${color}">${label}</span></td>
            ${far_num(r.amount)}</tr>`;
    }).join('');
    $far('far-tfoot').innerHTML = `<tr><td colspan="8">TOTAL UNPAID (${t.count || 0})</td>${far_num(t.total)}</tr>`;
}

/* ── FAR 4 ────────────────────────────────────────────── */
function far_render4(json) {
    const t = json.totals || {};
    $far('far-cards').innerHTML =
        far_card('blue', 'NCA Received', far_money(t.nca_received), 'Up to the month\'s end') +
        far_card('navy', 'Paid this Month', far_money(t.total_month), `Checks ${far_money(t.checks).replace(/<[^>]+>/g, '')} · ADA ${far_money(t.ada).replace(/<[^>]+>/g, '')}`) +
        far_card('amber', 'Paid Year to Date', far_money(t.disbursed_to_date), 'January to the month\'s end') +
        far_card('green', 'NCA Balance', far_money(t.nca_balance), 'Cash allocation still available');

    $far('far-thead').innerHTML = `
        <tr class="far-grp"><th rowspan="2">Bank Account</th><th rowspan="2" class="num">NCA Received</th><th colspan="2">Paid this month by</th>
            <th colspan="4">Paid this month for</th><th rowspan="2" class="num">Total this Month</th><th rowspan="2" class="num">Paid Year to Date</th><th rowspan="2" class="num">NCA Balance</th></tr>
        <tr><th class="num">Check</th><th class="num">ADA</th><th class="num">PS</th><th class="num">MOOE</th><th class="num">CO</th><th class="num">Others</th></tr>`;
    const cols = r => far_num(r.nca_received) + far_num(r.checks) + far_num(r.ada) + far_num(r.ps) + far_num(r.mooe) + far_num(r.co) + far_num(r.others)
        + far_num(r.total_month) + far_num(r.disbursed_to_date) + far_num(r.nca_balance);
    const rows = json.data || [];
    if (!rows.length) { $far('far-tbody').innerHTML = far_empty(11); return; }

    let html = rows.map(r => `<tr><td><div class="nha-td-bold">${far_esc(r.account_name || r.bank_name || '—')}</div>
        <div class="nha-td-sub nha-mono">${far_esc(r.account_number || '')}</div></td>${cols(r)}</tr>`).join('');

    const det = json.details || [];
    if (det.length) {
        html += `<tr class="far-section"><td colspan="11">Payments this month (${det.length})</td></tr>` +
            det.map(d => `<tr><td colspan="2" style="padding-left:24px;"><span class="nha-bdg nha-bdg-${d.mode === 'CHECK' ? 'blue' : 'teal'}">${d.mode === 'CHECK' ? 'Check' : 'ADA'}</span>
                <span class="nha-mono">${far_esc(d.doc_no)}</span> · ${far_esc(d.doc_date)}</td>
                <td colspan="4">${far_esc(d.payee || '—')}<div class="nha-td-sub">DV ${far_esc(d.dv_no || '—')}</div></td>
                <td colspan="2"><span class="nha-bdg nha-bdg-gray">${far_esc(d.object_class)}</span></td>
                ${far_num(d.amount)}<td colspan="2"></td></tr>`).join('');
    }
    $far('far-tbody').innerHTML = html;
    $far('far-tfoot').innerHTML = `<tr><td>TOTAL</td>${cols(t)}</tr>`;
}

/* ── FAR 5 ────────────────────────────────────────────── */
function far_render5(json) {
    const t = json.totals || {};
    const m = json.months || ['Month 1', 'Month 2', 'Month 3'];
    $far('far-cards').innerHTML =
        far_card('blue', m[0], far_money(t.month1), 'Collected') +
        far_card('indigo', m[1], far_money(t.month2), 'Collected') +
        far_card('navy', 'Quarter Total', far_money(t.quarter_total), `Q${json.quarter} ${json.fiscal_year}`) +
        far_card('green', 'Year to Date', far_money(t.year_to_date), `${t.receipts || 0} official receipts`);

    $far('far-thead').innerHTML = `<tr><th>Revenue Account</th><th class="num">${far_esc(m[0])}</th><th class="num">${far_esc(m[1])}</th>
        <th class="num">${far_esc(m[2])}</th><th class="num">Quarter Total</th><th class="num">Year to Date</th></tr>`;
    const cols = r => far_num(r.month1) + far_num(r.month2) + far_num(r.month3) + far_num(r.quarter_total) + far_num(r.year_to_date);
    const rows = json.data || [];
    if (!rows.length) { $far('far-tbody').innerHTML = far_empty(6, 'No collections for the selected period.'); return; }

    $far('far-tbody').innerHTML = rows.map(r => `<tr><td><span class="nha-mono">${far_esc(r.account_code || '—')}</span> ${far_esc(r.account_title || '')}</td>${cols(r)}</tr>`).join('');
    $far('far-tfoot').innerHTML = `<tr><td>TOTAL</td>${cols(t)}</tr>`;
}
</script>
