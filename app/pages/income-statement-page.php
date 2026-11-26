<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Income Statement (Statement of Financial Performance) — one fiscal year's Revenue less
// Expenses, built from every JEV that was approved and POSTED to fims.general_ledger, via
// GET /income_statement (IncomeStatementService.php). Laid out like the Balance Sheet page:
// centered print title, then Revenue, Current Operating Expenses, Other Non-Operating
// Income/(Loss), Discounts and Rebates, Losses and Subsidy Income, each grouped by chart-of-
// accounts group (e.g. "4-02 Service and Business Income"), with the prior year beside it and
// a running Surplus/(Deficit) after each milestone. Read-only.
$_is_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_is_api = preg_match('~/api$~', $_is_api) ? $_is_api : $_is_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />


<style>
    /* Table styling (.fims-report-table*) lives in _shared/css/nha-fims.css. Only page-specific, non-table rules remain here. */
    #is-root .is-report-title { text-align: center; margin: 0 0 18px; }
    #is-root .is-report-title div { font-weight: 700; font-size: 14px; line-height: 1.6; }
    #is-root .is-report-title .is-report-date { font-weight: 400; }
    #is-root .is-sample { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; background: #FFF4D6; color: #6B4A00; border: 1px solid #F0D58A; }

    /* Same page-level type scale as the Trial Balance / Balance Sheet pages. _shared/js/custom.js forces
       `1em * scale !important` on inputs/selects/buttons, so those get the same direct rem + !important. */
    #is-root .nha-hdr-tag             { font-size: 0.75rem; }    /* 12px */
    #is-root .nha-hdr-title           { font-size: 1.25rem; }    /* 20px */
    #is-root .nha-btn-hdr             { font-size: calc(0.875rem * var(--button-scale, 1)) !important; } /* 14px  Refresh */
    #is-root .nha-filter-label        { font-size: 0.75rem; }    /* 12px */
    #is-root .nha-filter-select       { font-size: calc(0.875rem * var(--input-scale, 1)) !important; }  /* 14px  dropdowns */
    #is-root .nha-error               { font-size: 0.875rem; }   /* 14px */
    #is-root .nha-hint                { font-size: 0.875rem; padding: 0 16px; margin: 0 0 14px; color: var(--afims-text-muted, #62657E); }   /* 14px; 16px side padding lines it up with the filter card */


    html.afims-dark[data-bs-theme="dark"] #is-root .is-sample { background: #3A2F10; color: #F5D98A; border-color: #6B5A22; }
    @media print {
        #is-root .is-letterhead,
        #is-root .is-cert { display: block !important; }
        #is-root .is-sample { display: none !important; }
    }
</style>

<div id="is-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m4 14v-8m4 8V9m4 10V7m4 12V3"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Income Statement</h1>
            <p class="app-page-subtitle">Statement of Financial Performance for the selected fiscal year. It adds up every Journal Entry Voucher
                that was approved and posted to the General Ledger, so a voucher still in review does not appear here.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="is-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="is-print" type="button" class="nha-btn-hdr" title="Print">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print
            </button>
            <button id="is-excel" type="button" class="nha-btn-hdr" title="Export to Excel">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                Excel
            </button>
        </div>
    </div>

    <div id="is-sample" class="is-sample no-print">
        <strong>Sample data.</strong> These figures are made up to show the layout. They are not from the General Ledger.
    </div>

    <!-- Error banner -->
    <div id="is-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="is-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="is-filter-fy">Fiscal Year</label>
                <select id="is-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="is-filter-book">Book</label>
                <select id="is-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="is-filter-view">View</label>
                <select id="is-filter-view" class="nha-filter-select">
                    <option value="consolidated" selected>Consolidated</option>
                    <option value="detailed">Detailed</option>
                    <option value="condensed">Condensed</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="is-letterhead" style="display:none;border:1px solid #000;padding:8px 10px;margin-bottom:0;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:14%;vertical-align:middle;">
                <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;margin:0 auto;object-fit:contain;">
            </td>
            <td class="is-report-title" style="width:51%;text-align:center;vertical-align:middle;">
                <div style="font-weight:700;">NATIONAL HOUSING AUTHORITY</div>
                <div id="is-title-line2" style="font-weight:700;">STATEMENT OF FINANCIAL PERFORMANCE</div>
                <div id="is-title-line3" style="font-weight:700;">MAIN OFFICE</div>
                <div class="is-report-date"><span id="is-report-date">—</span></div>
                <div id="is-title-peso" style="display:none;font-weight:400;">(In Philippine Peso)</div>
            </td>
            <td style="width:35%;text-align:center;vertical-align:middle;white-space:nowrap;">
                <img src="/_shared/images/letterhead/pambansang-pabahay.png" alt="Pambansang Pabahay"
                     style="height:46px;margin-right:5px;vertical-align:middle;object-fit:contain;">
                <img src="/_shared/images/letterhead/bagong-pilipinas.png" alt="Bagong Pilipinas"
                     style="height:46px;vertical-align:middle;object-fit:contain;">
            </td>
        </tr></table>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap fims-report-table-wrap">
        <div class="nha-tbl-scroll fims-report-table-scroll">
            <table class="nha-tbl fims-report-table">
                <thead id="is-thead">
                    <tr>
                        <th>Particulars</th>
                        <th class="text-end" id="is-th-cur">Current Year</th>
                        <th class="text-end" id="is-th-prev">Prior Year</th>
                        <th class="text-end">Increase / (Decrease)</th>
                    </tr>
                </thead>
                <tbody id="is-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── CERTIFICATION (shown only on print) ───────────── -->
    <div class="is-cert" style="display:none;margin-top:28px;font-size:9.5pt;">
        <p style="margin:0 0 30px;">I HEREBY CERTIFY that the foregoing Statement of Financial Performance is a true and
            complete statement of the revenue earned and expenses incurred as shown in the books of accounts, prepared
            and presented in conformity with International Public Sector Accounting Standards (IPSAS).</p>
        <div style="width:260px;text-align:center;">
            <div style="border-top:1px solid #000;padding-top:4px;">Signature Over Printed Name</div>
            <div>Head of Accounting Department</div>
        </div>
    </div>

</div><!-- /#is-root -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   INCOME STATEMENT   prefix: is_
═══════════════════════════════════════════════════════════════ */

const IS_BASE     = <?= json_encode($_is_api) ?>;
const IS_API      = IS_BASE + '/income_statement';
const IS_CONS_API = IS_BASE + '/income_statement/consolidated';
const IS_FY_API   = IS_BASE + '/fiscal_year/all';
const IS_BOOK_API = IS_BASE + '/books/all';
let is_books = [];        // [{book_id, name}] — labels the report title
let is_useSample = false; // sample-data preview, toggled from the console (see is_bindEvents)

// Section order + the running "Surplus/(Deficit)" checkpoint grand row shown right after it.
const IS_SECTION_META = {
    revenue:                    { subtotalLabel: 'Total Revenue' },
    current_operating_expenses: { subtotalLabel: 'Current Operating Expenses', grandAfter: { label: 'Surplus (Deficit) from Current Operations', key: 'surplus_from_operations' } },
    other_non_operating_income: { subtotalLabel: 'Total Other Non-Operating Income/(Loss)' },
    discounts_and_rebates:      { subtotalLabel: 'Total Discounts and Rebates' },
    losses:                     { subtotalLabel: 'Total Losses', grandAfter: { label: 'Surplus (Deficit) Before Subsidy', key: 'surplus_before_subsidy' } },
    subsidy_income:             { subtotalLabel: 'Net Subsidy', grandAfter: { label: 'Surplus (Deficit) After Subsidy', key: 'surplus_after_subsidy' } },
};

const $is = id => document.getElementById(id);

function is_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function is_boot() {
    await Promise.all([is_loadFiscalYears(), is_loadBooks()]);
    is_bindEvents();
    is_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', is_boot);
else is_boot();

function is_bindEvents() {
    $is('is-refresh')?.addEventListener('click', is_load);
    $is('is-print')?.addEventListener('click', () => window.print());
    $is('is-excel')?.addEventListener('click', is_exportExcel);
    $is('is-filter-fy')?.addEventListener('change', is_load);
    $is('is-filter-book')?.addEventListener('change', is_load);
    $is('is-filter-view')?.addEventListener('change', () => { is_syncBookFilter(); is_load(); });
    is_syncBookFilter();
    /* Sample preview has no button: type `income_statement = true` in the browser console to show
       made-up figures, `income_statement = false` to go back to live data. */
    Object.defineProperty(window, 'income_statement', {
        configurable: true,
        get: () => is_useSample,
        set: v => {
            is_useSample = !!v;
            $is('is-sample').style.display = is_useSample ? 'block' : 'none';
            is_load();
        },
    });
}

/* Consolidated lays every book out as its own column, so picking one Book to filter down to
   doesn't apply — grey the control out instead of pretending it still does something. */
function is_syncBookFilter() {
    const isConsolidated = $is('is-filter-view').value === 'consolidated';
    const sel = $is('is-filter-book');
    sel.disabled = isConsolidated;
    sel.closest('.nha-filter-group').style.opacity = isConsolidated ? 0.5 : 1;
}

async function is_loadFiscalYears() {
    const sel = $is('is-filter-fy');
    let years = [];
    try {
        const res  = await is_authFetch(IS_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        years = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(Boolean);
    } catch (e) {
        is_showError('Fiscal years: ' + e.message);
    }
    const now = new Date().getFullYear();
    if (!years.length) years = [now];
    years = [...new Set(years)].sort((a, b) => b - a);
    sel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = String(years.includes(now) ? now : years[0]);
}

async function is_loadBooks() {
    const sel = $is('is-filter-book');
    try {
        const res  = await is_authFetch(IS_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        is_books = json.data ?? [];
        sel.innerHTML = '<option value="">All Books</option>' +
            is_books.map(r => `<option value="${is_esc(r.book_id)}">${is_esc(r.name)}</option>`).join('');
    } catch (e) {
        is_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function is_load() {
    window.PagePreloader?.show();
    is_hideError();
    const fy   = $is('is-filter-fy').value;
    const book = $is('is-filter-book').value;
    const view = $is('is-filter-view').value || 'consolidated';
    try {
        if (view === 'consolidated') {
            let data;
            if (is_useSample) {
                data = is_sampleConsolidatedData(Number(fy) || new Date().getFullYear());
            } else {
                const params = new URLSearchParams();
                if (fy) params.set('fiscal_yr', fy);
                const res  = await is_authFetch(`${IS_CONS_API}?${params}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Failed to load the Consolidated Statement of Financial Performance.');
                data = json.data;
            }
            is_renderTitle(data.fiscal_yr, null, 'consolidated');
            is_renderConsolidated(data);
        } else {
            let data;
            if (is_useSample) {
                data = is_applyViewMode(is_sampleData(Number(fy) || new Date().getFullYear()), view);
            } else {
                const params = new URLSearchParams();
                if (fy) params.set('fiscal_yr', fy);
                if (book) params.set('book_id', book);
                params.set('view_mode', view);
                const res  = await is_authFetch(`${IS_API}?${params}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Failed to load the Income Statement.');
                data = json.data;
            }
            is_renderTitle(data.fiscal_yr, book, view);
            is_renderSingleThead();
            is_render(data);
        }
    } catch (e) {
        if (view === 'consolidated') is_renderConsolidated(null, Number(fy) || new Date().getFullYear());
        else { is_renderSingleThead(); is_render(null, Number(fy) || new Date().getFullYear()); }
        is_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function is_renderTitle(fiscalYr, bookId, view) {
    const line2 = $is('is-title-line2'), line3 = $is('is-title-line3'), peso = $is('is-title-peso');
    if (view === 'consolidated') {
        line2.innerHTML = 'Consolidated STATEMENTS OF FINANCIAL PERFORMANCE';
        line3.style.display = 'none';
        peso.style.display = 'block';
        $is('is-report-date').textContent = 'For the Year Ended December 31, ' + fiscalYr;
    } else {
        line2.textContent = 'STATEMENT OF FINANCIAL PERFORMANCE';
        const book = is_books.find(b => String(b.book_id) === String(bookId));
        line3.textContent = book ? book.name.toUpperCase() : 'MAIN OFFICE';
        line3.style.display = 'block';
        peso.style.display = 'none';
        $is('is-report-date').textContent = 'For the Year Ended December 31, ' + fiscalYr;
    }
}

/* The single-entity table always has the same 5 columns, so just restore them — rebuilt because
   is_renderConsolidated() replaces the whole <thead> with one column per book. */
function is_renderSingleThead() {
    $is('is-thead').innerHTML = `<tr>
        <th>Particulars</th>
        <th class="text-end" id="is-th-cur">Current Year</th>
        <th class="text-end" id="is-th-prev">Prior Year</th>
        <th class="text-end">INCREASE (DECREASE)</th>
    </tr>`;
}

/* ── Consolidated table (multi-entity: one column per fims.books entity + TOTAL) ──────────── */
function is_renderConsolidated(d, fallbackFy) {
    const tbody = $is('is-tbody');

    const books = d ? d.books : [];
    const colCount = 1 + books.length + 1; // Particulars, one per book, TOTAL

    $is('is-thead').innerHTML = `<tr>
        <th>Particulars</th>
        ${books.map(b => `<th class="text-end">${is_esc(b.name)}</th>`).join('')}
        <th class="text-end">TOTAL</th>
    </tr>`;

    if (!d || !d.sections || !d.sections.length) {
        tbody.innerHTML = `<tr><td colspan="${colCount}"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet for this fiscal year.</span></div></td></tr>`;
        return;
    }

    const html = [];
    const amounts = byBook => books.map(b => `<td class="text-end nha-mono fims-report-table-num">${is_peso(byBook[b.book_id] ?? 0)}</td>`).join('');
    const row = (cls, title, byBook, total) =>
        `<tr class="${cls}"><td>${is_esc(title)}</td>${amounts(byBook)}<td class="text-end nha-mono fims-report-table-num">${is_peso(total)}</td></tr>`;
    const grand = (title, byBook, total) =>
        `<tr class="fims-report-table-grand-row"><td>${is_esc(title)}</td>${amounts(byBook)}<td class="text-end nha-mono fims-report-table-num">${is_peso(total)}</td></tr>`;

    d.sections.forEach(s => {
        const meta = IS_SECTION_META[s.key] || {};
        html.push(`<tr class="fims-report-table-major-row"><td colspan="${colCount}">${is_esc(s.label)}</td></tr>`);
        s.groups.forEach(g => {
            html.push(row('fims-report-table-group-row', g.group_title, g.by_book, g.total));
            g.subgroups.forEach(sg => {
                if (sg.group_title) html.push(row('fims-report-table-subgroup-row', sg.group_title, sg.by_book, sg.total));
                sg.accounts.forEach(a => html.push(row('fims-report-table-row', a.account_title, a.by_book, a.total)));
            });
        });
        html.push(row('fims-report-table-subtotal-row', meta.subtotalLabel || ('Total ' + s.label), s.subtotal_by_book, s.subtotal_total));
        if (meta.grandAfter) {
            html.push(grand(meta.grandAfter.label, d.totals[meta.grandAfter.key + '_by_book'], d.totals[meta.grandAfter.key]));
        }
    });
    tbody.innerHTML = html.join('');
}

/* ── Table ─────────────────────────────────────────────────── */
function is_render(d, fallbackFy) {
    const tbody = $is('is-tbody');
    const fy    = d ? d.fiscal_yr : fallbackFy;
    $is('is-th-cur').textContent  = String(fy);
    $is('is-th-prev').textContent = (fy - 1);

    const hasData = d && d.sections && d.sections.length > 0;
    if (!d || !hasData) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet for this fiscal year.</span></div></td></tr>';
        return;
    }

    const html = [];
    const amounts = (cur, prev) => `
        <td class="text-end nha-mono fims-report-table-num">${is_peso(cur)}</td>
        <td class="text-end nha-mono fims-report-table-num">${is_peso(prev)}</td>
        <td class="text-end nha-mono fims-report-table-num">${is_peso(cur - prev)}</td>`;
    const row = (cls, title, cur, prev) =>
        `<tr class="${cls}"><td>${is_esc(title)}</td>${amounts(cur, prev)}</tr>`;
    const grand = (title, cur, prev) =>
        `<tr class="fims-report-table-grand-row"><td>${is_esc(title)}</td>${amounts(cur, prev)}</tr>`;

    d.sections.forEach(s => {
        const meta = IS_SECTION_META[s.key] || {};
        html.push(`<tr class="fims-report-table-major-row"><td colspan="4">${is_esc(s.label)}</td></tr>`);
        s.groups.forEach(g => {
            html.push(row('fims-report-table-group-row', g.group_title, g.balance, g.prev_balance));
            g.subgroups.forEach(sg => {
                if (sg.group_title) html.push(row('fims-report-table-subgroup-row', sg.group_title, sg.balance, sg.prev_balance));
                sg.accounts.forEach(a => html.push(row('fims-report-table-row', a.account_title, a.balance, a.prev_balance)));
            });
        });
        html.push(row('fims-report-table-subtotal-row', meta.subtotalLabel || ('Total ' + s.label), s.subtotal, s.prev_subtotal));
        if (meta.grandAfter) {
            html.push(grand(meta.grandAfter.label, d.totals[meta.grandAfter.key], d.totals['prev_' + meta.grandAfter.key]));
        }
    });
    tbody.innerHTML = html.join('');
}

/* Mirrors IncomeStatementService::build()'s view-mode stripping, client-side, so "Preview sample
   data" reflects the View toggle too instead of always showing the fully detailed sample. */
function is_applyViewMode(data, view) {
    data.view_mode = view;
    data.sections.forEach(s => {
        if (view !== 'detailed') s.groups.forEach(g => { g.subgroups = []; });
        if (view === 'condensed') s.groups = [];
    });
    return data;
}

/* ── Sample figures (layout preview only; same shape as GET /income_statement) ── */
function is_sampleData(fy) {
    const acct = (code, title, b, p) => ({ account_code: code, account_title: title, balance: b, prev_balance: p });
    const sub  = (code, title, accts) => ({
        group_code: code, group_title: title, accounts: accts,
        balance: accts.reduce((s, a) => s + a.balance, 0), prev_balance: accts.reduce((s, a) => s + a.prev_balance, 0),
    });
    const grp  = (code, title, subs) => ({
        group_code: code, group_title: title, subgroups: subs,
        balance: subs.reduce((s, sg) => s + sg.balance, 0), prev_balance: subs.reduce((s, sg) => s + sg.prev_balance, 0),
    });
    const sec = (key, label, groups) => ({
        key, label, groups,
        subtotal: groups.reduce((s, g) => s + g.balance, 0), prev_subtotal: groups.reduce((s, g) => s + g.prev_balance, 0),
    });

    const revenue = sec('revenue', 'Revenue', [
        grp('4-02', 'Service and Business Income', [
            sub('4-02-01', 'Service Income', [
                acct('4-02-01-010', 'Permit Fees', 63948.15, 63548.15),
                acct('4-02-01-130', 'Processing Fees', 44050438.29, 43342390.47),
            ]),
            sub('4-02-02', 'Business Income', [
                acct('4-02-02-050', 'Rent/Lease Income', 384347208.54, 378217101.08),
                acct('4-02-02-160', 'Sales Revenue', 477145293.44, 414715713.15),
            ]),
        ]),
        grp('4-04', 'Shares, Donations and Grants', [sub('', '', [acct('4-04-02-010', 'Donations in Cash', 0, 0)])]),
    ]);
    const opex = sec('current_operating_expenses', 'Less: Current Operating Expenses', [
        grp('5-01', 'Personnel Services', [
            sub('5-01-01', 'Salaries and Wages', [acct('5-01-01-010', 'Salaries and Wages-Regular', 998938336.09, 998506906.09)]),
        ]),
        grp('5-02', 'Maintenance and Other Operating Expenses', [
            sub('5-02-04', 'Utility Expenses', [acct('5-02-04-020', 'Electricity Expenses', 41172147.85, 40044530.96)]),
        ]),
        grp('5-03', 'Financial Expenses', [sub('', '', [acct('5-03-01-040', 'Bank Charges', 129192.54, 118785.00)])]),
        grp('5-02-14', 'Assistance/Subsidy/Contribution', [sub('', '', [acct('5-02-14-990', 'Assistance/Subsidies/Contribution-Others', 0, 0)])]),
        grp('5-05', 'Non-Cash Expenses', [
            sub('5-05-01', 'Depreciation', [acct('5-05-01-050', 'Depreciation-Machinery and Equipment', 29120670.58, 29069283.22)]),
            sub('5-05-03', 'Impairment Loss', [acct('5-05-03-020', 'Impairment Loss-Loans and Receivables', 384834335.62, 340748457.16)]),
        ]),
    ]);
    const nonOp = sec('other_non_operating_income', 'Add: Other Non-Operating Income/(Loss)', [
        grp('4-05', 'Gains', [sub('', '', [acct('4-05-01-170', 'Gain on Sale of Unserviceable Property', 145411.97, 145411.97)])]),
        grp('4-06', 'Other Non-Operating Income', [sub('', '', [acct('4-06-99-990', 'Miscellaneous Income', 18718277.64, 12707829.36)])]),
    ]);
    const discounts = sec('discounts_and_rebates', 'Less: Discounts and Rebates', [
        grp('5-05-05', 'Discounts and Rebates', [sub('', '', [acct('5-05-05-020', 'Rebates', 56768232.31, 51093479.69)])]),
    ]);
    const losses = sec('losses', 'Less: Losses', [
        grp('5-05-04', 'Losses', [sub('', '', [acct('5-05-04-080', 'Loss on Sale of Assets', 102959.36, 102959.36)])]),
    ]);
    const subsidy = sec('subsidy_income', 'Add (Deduct): Subsidy Income', [
        grp('4-03', 'Assistance and Subsidy from', [sub('', '', [acct('4-03-01-010', 'Subsidy from National Government', 5531211983.00, 5531211983.00)])]),
    ]);

    const sections = [revenue, opex, nonOp, discounts, losses, subsidy];
    const surplusFromOps       = revenue.subtotal - opex.subtotal;
    const surplusBeforeSubsidy = surplusFromOps + nonOp.subtotal - discounts.subtotal - losses.subtotal;
    const surplusAfterSubsidy  = surplusBeforeSubsidy + subsidy.subtotal;
    const prevSurplusFromOps       = revenue.prev_subtotal - opex.prev_subtotal;
    const prevSurplusBeforeSubsidy = prevSurplusFromOps + nonOp.prev_subtotal - discounts.prev_subtotal - losses.prev_subtotal;
    const prevSurplusAfterSubsidy  = prevSurplusBeforeSubsidy + subsidy.prev_subtotal;

    return {
        fiscal_yr: fy, prev_year: fy - 1, book_id: null, sections,
        totals: {
            surplus_from_operations: surplusFromOps, surplus_before_subsidy: surplusBeforeSubsidy, surplus_after_subsidy: surplusAfterSubsidy,
            prev_surplus_from_operations: prevSurplusFromOps, prev_surplus_before_subsidy: prevSurplusBeforeSubsidy, prev_surplus_after_subsidy: prevSurplusAfterSubsidy,
        },
    };
}

/* ── Sample figures for the Consolidated view (layout preview only; same shape as GET
   /income_statement/consolidated) — reuses is_sampleData()'s single-entity figures as "Main
   Office" and makes up small CISFA/NGC/MPLC figures so the multi-column layout has something to show. */
function is_sampleConsolidatedData(fy) {
    const books = [
        { book_id: 1, name: 'Main Office' },
        { book_id: 2, name: 'CISFA' },
        { book_id: 3, name: 'NGC' },
        { book_id: 4, name: 'MPLC / PPMO' },
    ];
    const factor = { 1: 1, 2: 0.03, 3: 0.025, 4: 0.005 };
    const byBook = v => Object.fromEntries(books.map(b => [b.book_id, Math.round(v * factor[b.book_id])]));
    const sumByBook = byBookObj => Object.values(byBookObj).reduce((t, v) => t + v, 0);

    const base = is_sampleData(fy);
    const pivotAccount = a => {
        const by_book = byBook(a.balance);
        return { account_code: a.account_code, account_title: a.account_title, by_book, total: sumByBook(by_book) };
    };
    const sections = base.sections.map(s => ({
        key: s.key, label: s.label,
        groups: s.groups.map(g => {
            const subgroups = g.subgroups.map(sg => {
                const accounts = sg.accounts.map(pivotAccount);
                const sgByBook = byBook(sg.balance);
                return { group_code: sg.group_code, group_title: sg.group_title, accounts, by_book: sgByBook, total: sumByBook(sgByBook) };
            });
            const gByBook = byBook(g.balance);
            return { group_code: g.group_code, group_title: g.group_title, subgroups, by_book: gByBook, total: sumByBook(gByBook) };
        }),
        subtotal_by_book: byBook(s.subtotal), subtotal_total: sumByBook(byBook(s.subtotal)),
    }));

    const byKey = key => sections.find(s => s.key === key);
    const surplusFromOpsByBook = Object.fromEntries(books.map(b => [b.book_id,
        (byKey('revenue').subtotal_by_book[b.book_id] ?? 0) - (byKey('current_operating_expenses').subtotal_by_book[b.book_id] ?? 0)]));
    const surplusBeforeSubsidyByBook = Object.fromEntries(books.map(b => [b.book_id,
        surplusFromOpsByBook[b.book_id]
        + (byKey('other_non_operating_income').subtotal_by_book[b.book_id] ?? 0)
        - (byKey('discounts_and_rebates').subtotal_by_book[b.book_id] ?? 0)
        - (byKey('losses').subtotal_by_book[b.book_id] ?? 0)]));
    const surplusAfterSubsidyByBook = Object.fromEntries(books.map(b => [b.book_id,
        surplusBeforeSubsidyByBook[b.book_id] + (byKey('subsidy_income').subtotal_by_book[b.book_id] ?? 0)]));

    return {
        fiscal_yr: fy, books, sections,
        totals: {
            surplus_from_operations: sumByBook(surplusFromOpsByBook),
            surplus_before_subsidy: sumByBook(surplusBeforeSubsidyByBook),
            surplus_after_subsidy: sumByBook(surplusAfterSubsidyByBook),
            surplus_from_operations_by_book: surplusFromOpsByBook,
            surplus_before_subsidy_by_book: surplusBeforeSubsidyByBook,
            surplus_after_subsidy_by_book: surplusAfterSubsidyByBook,
        },
    };
}

/* ── Excel export ──────────────────────────────────────────── */
/* Built from the table currently on screen, so it matches whichever View (Consolidated / Detailed / Condensed)
   is showing, including the one-column-per-book layout. Row classes decide the styling; amounts are parsed
   back from the "₱ 1,234.00" / "(₱ 1,234.00)" text into real numbers. */
function is_parseAmount(text) {
    const t = String(text || '').trim();
    if (!/\d/.test(t)) return null;
    const n = Number(t.replace(/[^0-9.]/g, ''));
    return /^\(/.test(t) ? -n : n;
}

async function is_exportExcel() {
    if (!window.ExcelJS) { is_showError('Excel library failed to load. Check your internet connection and reload.'); return; }
    const trs = [...document.querySelectorAll('#is-tbody tr')];
    if (!trs.length || document.querySelector('#is-tbody .nha-empty') || document.querySelector('#is-tbody .nha-loader')) {
        is_showError('Nothing to export — no postings for this fiscal year.');
        return;
    }
    const headCells = [...document.querySelectorAll('#is-thead th')].map(th => th.textContent.trim());
    const nCols = headCells.length;

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Income Statement', {
        pageSetup: { paperSize: 9, orientation: nCols > 4 ? 'landscape' : 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });
    ws.columns = headCells.map((_, i) => ({ width: i === 0 ? 58 : 20 }));
    const NUM = '#,##0.00;(#,##0.00);"-"';
    const thin = { style: 'thin' };
    const fill = argb => ({ type: 'pattern', pattern: 'solid', fgColor: { argb } });

    const title = (text, bold = true) => {
        if (!text) return;
        const r = ws.addRow([text]);
        ws.mergeCells(r.number, 1, r.number, nCols);
        r.getCell(1).alignment = { horizontal: 'center' };
        r.getCell(1).font = { bold, size: 11 };
    };
    title('NATIONAL HOUSING AUTHORITY');
    title($is('is-title-line2').textContent);
    if ($is('is-title-line3').style.display !== 'none') title($is('is-title-line3').textContent);
    title($is('is-report-date').textContent, false);
    if ($is('is-title-peso').style.display !== 'none') title($is('is-title-peso').textContent, false);
    ws.addRow([]);

    const head = ws.addRow(headCells);
    head.eachCell((c, i) => {
        c.font = { bold: true };
        c.fill = fill('FFD9E1F2');
        c.border = { top: thin, bottom: thin, left: thin, right: thin };
        c.alignment = { horizontal: i > 1 ? 'right' : 'center', wrapText: true };
    });

    trs.forEach(tr => {
        const cls = tr.className;
        const cells = [...tr.cells];
        if (cells.length === 1) { // major section header (colspan)
            const m = ws.addRow([cells[0].textContent.trim()]);
            ws.mergeCells(m.number, 1, m.number, nCols);
            m.getCell(1).font = { bold: true };
            m.getCell(1).fill = fill('FFEDEDED');
            return;
        }
        const vals = cells.map((c, i) => i === 0 ? c.textContent.trim() : is_parseAmount(c.textContent));
        const r = ws.addRow(vals);
        for (let i = 2; i <= nCols; i++) r.getCell(i).numFmt = NUM;

        const isGrand = /grand-row/.test(cls), isSub = /subtotal-row/.test(cls);
        if (/group-row/.test(cls) && !/subgroup/.test(cls)) r.getCell(1).font = { bold: true };
        if (/subgroup-row/.test(cls)) r.getCell(1).alignment = { indent: 1 };
        if (/^fims-report-table-row/.test(cls)) r.getCell(1).alignment = { indent: 2 };
        if (isSub || isGrand) {
            r.eachCell(c => { c.font = { bold: true }; c.border = { top: thin, bottom: isGrand ? { style: 'double' } : undefined }; });
        }
    });

    ws.addRow([]);
    const cert = ws.addRow(['I HEREBY CERTIFY that the foregoing Statement of Financial Performance is a true and complete statement of the revenue earned and expenses incurred as shown in the books of accounts, prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).']);
    ws.mergeCells(cert.number, 1, cert.number, nCols);
    cert.getCell(1).alignment = { wrapText: true, vertical: 'top' };
    cert.height = 48;
    ws.addRow([]);
    ws.addRow([]);
    const sig = ws.addRow(['Signature Over Printed Name']);
    sig.getCell(1).border = { top: thin };
    sig.getCell(1).alignment = { horizontal: 'center' };
    ws.addRow(['Head of Accounting Department']).getCell(1).alignment = { horizontal: 'center' };

    ws.views = [{ state: 'frozen', ySplit: head.number }];

    const buf  = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `Income-Statement_${$is('is-filter-fy').value}_${$is('is-filter-view').value}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/* ── Formatters ────────────────────────────────────────────── */
/* Negative amounts print in parentheses, the usual financial-statement convention. */
function is_peso(v) {
    const n = Number(v || 0);
    const s = '₱ ' + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < -0.005 ? `<span class="fims-report-table-neg">(${s})</span>` : s;
}
function is_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function is_showError(msg) { $is('is-error').style.display = 'flex'; $is('is-error-msg').textContent = msg; }
function is_hideError()    { $is('is-error').style.display = 'none'; }
</script>
