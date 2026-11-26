<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Statement of Changes in Net Assets/Equity — read-only. Shows opening balance,
// changes during the period (surplus/deficit, dividends, adjustments), and closing balance.
// Built from GL posting data via GET /statement_of_changes (single-entity) and
// /statement_of_changes/consolidated (multi-entity).
$_sc_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_sc_api = preg_match('~/api$~', $_sc_api) ? $_sc_api : $_sc_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />


<style>
    /* Table styling (.fims-report-table*) lives in _shared/css/nha-fims.css. Only page-specific, non-table rules remain here. */
    #sc-root .sc-report-title { text-align: center; margin: 0 0 18px; }
    #sc-root .sc-report-title div { font-weight: 700; font-size: 14px; line-height: 1.6; }
    #sc-root .sc-report-title .sc-report-date { font-weight: 400; }
    #sc-root .sc-sample { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; background: #FFF4D6; color: #6B4A00; border: 1px solid #F0D58A; }

    /* Same page-level type scale as the Trial Balance / Balance Sheet / Income Statement pages. _shared/js/custom.js forces
       `1em * scale !important` on inputs/selects/buttons, so those get the same direct rem + !important. */
    #sc-root .nha-hdr-tag             { font-size: 0.75rem; }    /* 12px */
    #sc-root .nha-hdr-title           { font-size: 1.25rem; }    /* 20px */
    #sc-root .nha-btn-hdr             { font-size: calc(0.875rem * var(--button-scale, 1)) !important; } /* 14px  Refresh */
    #sc-root .nha-filter-label        { font-size: 0.75rem; }    /* 12px */
    #sc-root .nha-filter-select       { font-size: calc(0.875rem * var(--input-scale, 1)) !important; }  /* 14px  dropdowns */
    #sc-root .nha-bdg                 { font-size: 0.8125rem; }  /* 13px  status badge */
    #sc-root .nha-error               { font-size: 0.875rem; }   /* 14px */
    #sc-root .nha-hint                { font-size: 0.875rem; padding: 0 16px; margin: 0 0 14px; color: var(--afims-text-muted, #62657E); }   /* 14px; 16px side padding lines it up with the filter card */


    html.afims-dark[data-bs-theme="dark"] #sc-root .sc-sample { background: #3A2F10; color: #F5D98A; border-color: #6B5A22; }
    /* Print / Export — one header button → PDF | Excel menu → preview modal (Print / Download on a bar above the preview) */
    #sc-root .sc-menu-item { display: block; width: 100%; text-align: left; padding: 8px 10px; border: 0; border-radius: 6px; background: transparent; cursor: pointer; font-family: inherit; }
    #sc-root .sc-menu-item:hover, #sc-root .sc-menu-item:focus-visible { background: var(--afims-surface-subtle, #F4F7FA); outline: none; }
    #sc-root .sc-menu-item strong { display: block; font-size: 13px; color: var(--afims-text, #1e293b); }
    #sc-root .sc-menu-item small  { display: block; font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
    #sc-root .scx-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
    #sc-root .scx-info { font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
    #sc-root .scx-actions { display: flex; gap: 8px; }
    #sc-root .scx-actions [hidden] { display: none !important; }
    #sc-root .scx-stage { position: relative; height: 68vh; overflow: auto; background: #E8ECF2; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; }
    #sc-root .scx-frame { display: block; width: 100%; height: 100%; border: 0; background: #E8ECF2; }
    #sc-root .scx-msg { padding: 60px 20px; text-align: center; font-size: 13px; color: var(--afims-text-muted, #62657E); }
    #sc-root .scx-err { margin-top: 10px; padding: 8px 12px; border-radius: 7px; background: #FDECEC; color: #B42318; font-size: 13px; }
    #sc-root .scx-msg[hidden], #sc-root .scx-err[hidden], #sc-root .scx-frame[hidden], #sc-root .scx-sheet[hidden] { display: none; }
    /* the Excel preview: the sheet the .xlsx will contain, with a spreadsheet's column letters / row numbers, centred in the stage */
    #sc-root .scx-sheet { padding: 14px; }
    #sc-root table.scx-xl { margin: 0 auto; border-collapse: collapse; background: #fff; font: 12px Calibri, Arial, sans-serif; box-shadow: 0 1px 6px rgba(15, 32, 69, .18); }
    #sc-root table.scx-xl th { padding: 2px 8px; border: 1px solid #d0d3d6; background: #f1f3f4; color: #5f6368; font-weight: 500; text-align: center; }
    #sc-root table.scx-xl td { height: 20px; padding: 2px 6px; border: 1px solid #e1e3e6; }
    #sc-root table.scx-xl td.rn { background: #f1f3f4; color: #5f6368; text-align: center; padding: 2px 4px; }
    #sc-root table.scx-xl td.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    /* SCNAE tab */
    #sc-root .sc-eq-entity { margin: 0 0 22px; }
    #sc-root .sc-eq-name { margin: 0 0 6px; padding: 0 4px; font-size: 0.9375rem; font-weight: 700; color: var(--afims-heading, #000E3E); }
    #sc-root .sc-eq-label { padding-left: 24px; }
    #sc-root .sc-eq-note { font-style: italic; }

    @media print {
        #sc-root .sc-letterhead,
        #sc-root .sc-cert { display: block !important; }
        #sc-root .sc-sample { display: none !important; }
    }
</style>

<div id="sc-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M5 7h14M5 7l-2.5 6a3 3 0 005 0L5 7zm14 0l-2.5 6a3 3 0 005 0L19 7z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Statement of Changes in Net Assets/Equity</h1>
            <p class="app-page-subtitle">For the selected fiscal year: the opening balance, the surplus/(deficit) for the period and the
                closing balance, based on approved and posted Journal Entry Vouchers.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="sc-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="sc-export" type="button" class="nha-btn-hdr" title="Print or export the Statement of Changes — PDF or Excel" aria-haspopup="true" aria-expanded="false">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print / Export &#9662;
            </button>
        </div>
    </div>

    <!-- ── TABS ───────────────────────────────────────────── -->
    <div class="nha-tabs no-print" role="tablist">
        <button type="button" class="nha-tab active" role="tab" id="sc-tab-summary" data-tab="summary" aria-selected="true">Summary</button>
        <button type="button" class="nha-tab" role="tab" id="sc-tab-scnae" data-tab="scnae" aria-selected="false">SCNAE — Changes in Equity</button>
    </div>

    <!-- Format menu of the Print / Export button (fixed, so the banner's rounded corners can't clip it) -->
    <div id="sc-export-menu" role="menu" class="no-print" style="display:none;position:fixed;z-index:1060;min-width:230px;background:var(--afims-surface,#fff);border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:6px;">
        <button type="button" role="menuitem" class="sc-menu-item" data-format="pdf"><strong>PDF</strong><small>Preview, then print or download</small></button>
        <button type="button" role="menuitem" class="sc-menu-item" data-format="xlsx"><strong>Excel</strong><small>Preview, then download (.xlsx)</small></button>
    </div>

    <!-- ── PRINT / EXPORT PREVIEW ─────────────────────────── -->
    <div id="sc-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="sc-x-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="sc-x-title">Statement of Changes in Net Assets/Equity</h3>
                <button type="button" class="nha-modal-close" id="sc-x-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="scx-bar">
                    <span class="scx-info" id="sc-x-info"></span>
                    <span class="scx-actions">
                        <button type="button" class="nha-btn nha-btn-outline" id="sc-x-print" disabled>Print</button>
                        <button type="button" class="nha-btn nha-btn-primary" id="sc-x-download" disabled>Download</button>
                    </span>
                </div>
                <div class="scx-stage">
                    <div class="scx-msg" id="sc-x-msg">Loading preview&hellip;</div>
                    <iframe class="scx-frame" id="sc-x-frame" title="Statement of Changes PDF preview" hidden></iframe>
                    <div class="scx-sheet" id="sc-x-sheet" hidden></div>
                </div>
                <div class="scx-err" id="sc-x-err" role="alert" hidden></div>
            </div>
        </div>
    </div>

    <div id="sc-sample" class="sc-sample no-print">
        <strong>Sample data.</strong> These figures are made up to show the layout. They are not from the General Ledger.
    </div>

    <!-- Error banner -->
    <div id="sc-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="sc-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="sc-filter-fy">Fiscal Year</label>
                <select id="sc-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="sc-filter-book">Book</label>
                <select id="sc-filter-book" class="nha-filter-select">
                    <option value="">Consolidated</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <span id="sc-badge" class="nha-bdg nha-bdg-gray">—</span>
            </div>
        </div>
    </div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="sc-letterhead" style="display:none;border:1px solid #000;padding:8px 10px;margin-bottom:0;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:14%;vertical-align:middle;">
                <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;margin:0 auto;object-fit:contain;">
            </td>
            <td class="sc-report-title" style="width:51%;text-align:center;vertical-align:middle;">
                <div style="font-weight:700;">NATIONAL HOUSING AUTHORITY</div>
                <div id="sc-title-line2" style="font-weight:700;">STATEMENT OF CHANGES IN NET ASSETS/EQUITY</div>
                <div id="sc-title-line3" style="font-weight:700;">MAIN OFFICE</div>
                <div class="sc-report-date"><span id="sc-report-date">—</span></div>
                <div id="sc-title-peso" style="display:none;font-weight:400;">(In Philippine Peso)</div>
            </td>
            <td style="width:35%;text-align:center;vertical-align:middle;white-space:nowrap;">
                <img src="/_shared/images/letterhead/pambansang-pabahay.png" alt="Pambansang Pabahay"
                     style="height:46px;margin-right:5px;vertical-align:middle;object-fit:contain;">
                <img src="/_shared/images/letterhead/bagong-pilipinas.png" alt="Bagong Pilipinas"
                     style="height:46px;vertical-align:middle;object-fit:contain;">
            </td>
        </tr></table>
    </div>

    <!-- ── SCNAE TAB: equity components as columns, prior year above current year, one block per entity ── -->
    <div id="sc-pane-scnae" style="display:none;">
        <p class="nha-hint" id="sc-eq-hint"></p>
        <div id="sc-eq-wrap"></div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div id="sc-pane-summary" class="nha-table-wrap fims-report-table-wrap">
        <div class="nha-tbl-scroll fims-report-table-scroll">
            <table class="nha-tbl fims-report-table">
                <thead id="sc-thead">
                    <tr>
                        <th>Description</th>
                        <th class="text-end" id="sc-th-consolidated">Consolidated</th>
                        <th class="text-end" id="sc-th-nha">NHA (Main Office)</th>
                        <th class="text-end" id="sc-th-prev">Prior Year</th>
                    </tr>
                </thead>
                <tbody id="sc-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── CERTIFICATION (shown only on print) ───────────── -->
    <div class="sc-cert" style="display:none;margin-top:28px;font-size:9.5pt;">
        <p style="margin:0 0 30px;">I HEREBY CERTIFY that the foregoing Statement of Changes in Net Assets/Equity is a true and
            complete statement of the changes in the net assets/equity as shown in the books of accounts, prepared and
            presented in conformity with International Public Sector Accounting Standards (IPSAS).</p>
        <div style="width:260px;text-align:center;">
            <div style="border-top:1px solid #000;padding-top:4px;">Signature Over Printed Name</div>
            <div>Head of Accounting Department</div>
        </div>
    </div>

</div><!-- /#sc-root -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   STATEMENT OF CHANGES IN NET ASSETS/EQUITY   prefix: sc_
═══════════════════════════════════════════════════════════════ */

const SC_BASE = <?= json_encode($_sc_api) ?>;
const SC_API  = SC_BASE + '/statement_of_changes';
const SC_CONS_API = SC_BASE + '/statement_of_changes/consolidated';
const SC_EQUITY_API = SC_BASE + '/statement_of_changes/equity';
const SC_FY_API   = SC_BASE + '/fiscal_year/all';
const SC_BOOK_API = SC_BASE + '/books/all';

let sc_books = [];
let sc_useSample = false;

const $sc = id => document.getElementById(id);

function sc_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function sc_boot() {
    await Promise.all([sc_loadFiscalYears(), sc_loadBooks()]);
    sc_bindEvents();
    sc_load();
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sc_boot);
else sc_boot();

/* Tabs: Summary (the existing table + Print / Export) | SCNAE (equity components as columns). The Book and Fiscal Year
   filters and Refresh drive whichever tab is open; Print / Export only knows the Summary table, so it is hidden on SCNAE. */
let sc_tab = 'summary';

function sc_switchTab(tab) {
    sc_tab = tab === 'scnae' ? 'scnae' : 'summary';
    document.querySelectorAll('#sc-root .nha-tab').forEach(b => {
        const on = b.dataset.tab === sc_tab;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    $sc('sc-pane-summary').style.display = sc_tab === 'summary' ? '' : 'none';
    $sc('sc-pane-scnae').style.display   = sc_tab === 'scnae' ? '' : 'none';
    $sc('sc-export').style.display       = sc_tab === 'summary' ? '' : 'none';
    sc_toggleMenu(false);
    sc_load();
}

function sc_bindEvents() {
    document.querySelectorAll('#sc-root .nha-tab').forEach(b => b.addEventListener('click', () => sc_switchTab(b.dataset.tab)));
    $sc('sc-refresh')?.addEventListener('click', sc_load);
    $sc('sc-filter-fy')?.addEventListener('change', sc_load);
    $sc('sc-filter-book')?.addEventListener('change', sc_load);

    // Print / Export: the header button's menu (PDF | Excel) → the preview modal
    $sc('sc-export')?.addEventListener('click', e => { e.stopPropagation(); sc_toggleMenu($sc('sc-export-menu').style.display === 'none'); });
    $sc('sc-export-menu')?.addEventListener('click', e => {
        e.stopPropagation();
        const item = e.target.closest('.sc-menu-item');
        if (!item) return;
        sc_toggleMenu(false);
        sc_openExport(item.dataset.format);
    });
    document.addEventListener('click', () => sc_toggleMenu(false));
    window.addEventListener('scroll', () => sc_toggleMenu(false), true);
    window.addEventListener('resize', () => sc_toggleMenu(false));
    $sc('sc-x-close')?.addEventListener('click', sc_closeExport);
    $sc('sc-overlay')?.addEventListener('click', e => { if (e.target === $sc('sc-overlay')) sc_closeExport(); });
    $sc('sc-x-print')?.addEventListener('click', sc_printPdf);
    $sc('sc-x-download')?.addEventListener('click', sc_downloadFile);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && $sc('sc-overlay').classList.contains('open')) { e.preventDefault(); sc_closeExport(); }
    });

    Object.defineProperty(window, 'statement_of_changes', {
        configurable: true,
        get: () => sc_useSample,
        set: v => {
            sc_useSample = !!v;
            $sc('sc-sample').style.display = sc_useSample ? 'block' : 'none';
            sc_load();
        },
    });
}

async function sc_loadFiscalYears() {
    const sel = $sc('sc-filter-fy');
    let years = [];
    try {
        const res = await sc_authFetch(SC_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        years = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(Boolean);
    } catch (e) {
        sc_showError('Fiscal years: ' + e.message);
    }
    const now = new Date().getFullYear();
    if (!years.length) years = [now];
    years = [...new Set(years)].sort((a, b) => b - a);
    sel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = String(years.includes(now) ? now : years[0]);
}

async function sc_loadBooks() {
    const sel = $sc('sc-filter-book');
    try {
        const res = await sc_authFetch(SC_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        sc_books = json.data ?? [];
        sel.innerHTML = '<option value="">Consolidated</option>' +
            sc_books.filter(b => b.book_id !== undefined && b.name).map(r =>
                `<option value="${sc_esc(r.book_id)}">${sc_esc(r.name)}</option>`
            ).join('');
    } catch (e) {
        sc_showError('Books: ' + e.message);
    }
}

async function sc_load() {
    window.PagePreloader?.show();
    sc_hideError();
    const fy = $sc('sc-filter-fy').value;
    const book = $sc('sc-filter-book').value;

    if (sc_tab === 'scnae') {
        try { await sc_loadEquity(Number(fy) || new Date().getFullYear(), book); }
        catch (e) { sc_showError(e.message); }
        finally { window.PagePreloader?.hide(); }
        return;
    }

    try {
        let data;
        if (sc_useSample) {
            data = sc_sampleData(Number(fy) || new Date().getFullYear());
        } else {
            const params = new URLSearchParams();
            if (fy) params.set('fiscal_yr', fy);
            if (book) {
                const res = await sc_authFetch(`${SC_API}?${params}&book_id=${book}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Failed to load data.');
                data = json.data;
            } else {
                const res = await sc_authFetch(`${SC_CONS_API}?${params}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Failed to load data.');
                data = sc_normalizeConsolidated(json.data);
            }
        }
        sc_renderTitle(Number(fy) || new Date().getFullYear(), book);
        sc_renderTable(data, Number(fy) || new Date().getFullYear(), book);
    } catch (e) {
        sc_showError(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* Print letterhead: consolidated (no book picked) shows "(In Philippine Peso)" and no entity line;
   a single book shows its name on the third line, same as the other financial statements. */
function sc_renderTitle(fy, bookId) {
    const line3 = $sc('sc-title-line3'), peso = $sc('sc-title-peso');
    if (bookId) {
        const book = sc_books.find(b => String(b.book_id) === String(bookId));
        line3.textContent = book ? book.name.toUpperCase() : 'MAIN OFFICE';
        line3.style.display = 'block';
        peso.style.display = 'none';
    } else {
        line3.style.display = 'none';
        peso.style.display = 'block';
    }
    $sc('sc-report-date').textContent = 'For the Year Ended December 31, ' + fy;
}

// MAIN-OFFICE_BOOK_ID matches fims.books seed row 1 ("Main Office") — see "01 - table/41 - fims_books.sql".
const SC_MAIN_OFFICE_BOOK_ID = 1;

/* Consolidated endpoint returns by_book: { book_id: {opening, closing, surplus_deficit,
   adjustments, book_name} } — sum every book for the Consolidated column and isolate
   book_id=1 for the NHA (Main Office) column, into the single shape sc_renderTable reads
   for both the single-entity and consolidated cases. */
function sc_normalizeConsolidated(data) {
    const byBook = data.by_book || {};
    const books = Object.values(byBook);
    const sum = (key) => round2(books.reduce((t, b) => t + Number(b[key] || 0), 0));
    const nha = byBook[SC_MAIN_OFFICE_BOOK_ID] || null;

    return {
        opening_balance: { total: sum('opening') },
        closing_balance: { total: sum('closing') },
        surplus_deficit: sum('surplus_deficit'),
        adjustments: sum('adjustments'),
        nha: nha && {
            opening_balance: { total: round2(nha.opening) },
            closing_balance: { total: round2(nha.closing) },
            surplus_deficit: round2(nha.surplus_deficit),
            adjustments: round2(nha.adjustments),
        },
    };
}

function sc_renderTable(data, fy, book) {
    const tbody = $sc('sc-tbody');
    if (!data) {
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">No data available.</td></tr>';
        return;
    }

    // Single-book view: the selected book's own figures double as the NHA (Main Office) column
    // only when that book IS Main Office; otherwise NHA stays blank. Consolidated view carries
    // its own "nha" slice from sc_normalizeConsolidated.
    const nhaData = book
        ? (Number(book) === SC_MAIN_OFFICE_BOOK_ID ? data : null)
        : data.nha;

    const main = {
        opening: data.opening_balance?.total || 0,
        surplus: data.surplus_deficit || 0,
        adjustments: data.adjustments || 0,
    };
    main.closing = round2(main.opening + main.surplus + main.adjustments);

    const nha = nhaData && {
        opening: nhaData.opening_balance?.total || 0,
        surplus: nhaData.surplus_deficit || 0,
        adjustments: nhaData.adjustments || 0,
    };
    if (nha) {
        nha.closing = round2(nha.opening + nha.surplus + nha.adjustments);
    }

    const rows = [];

    // Opening balance
    rows.push({ description: 'BALANCE AT JANUARY 1, ' + fy, section: true, class: 'fims-report-table-group-row' });
    rows.push({ description: '  Accumulated Surplus', amount: main.opening, nha: nha?.opening, class: 'fims-report-table-row' });

    // Changes during period
    rows.push({ description: 'CHANGES IN NET ASSETS/EQUITY FOR CY ' + fy, section: true, class: 'fims-report-table-group-row' });
    rows.push({ description: '  Add/(Deduct): Surplus/(Deficit) for the period', amount: main.surplus, nha: nha?.surplus, class: 'fims-report-table-row' });
    rows.push({ description: '  Adjustments (capital, dividends, impairment, prior-year — not yet broken out by type)', amount: main.adjustments, nha: nha?.adjustments, class: 'fims-report-table-row' });

    const totalChanges = round2(main.surplus + main.adjustments);
    const nhaTotalChanges = nha && round2(nha.surplus + nha.adjustments);
    rows.push({ description: 'Net Changes in Net Assets/Equity', amount: totalChanges, nha: nhaTotalChanges, subtotal: true, class: 'fims-report-table-subtotal-row' });

    // Closing balance
    rows.push({ description: 'BALANCE AT DECEMBER 31, ' + fy, section: true, class: 'fims-report-table-group-row' });
    rows.push({ description: '  Accumulated Surplus', amount: main.closing, nha: nha?.closing, grand: true, class: 'fims-report-table-grand-row' });

    tbody.innerHTML = rows.map(r => {
        if (r.section) {
            return `<tr class="${r.class}"><td colspan="4">${sc_esc(r.description)}</td></tr>`;
        }
        // Negative figures are wrapped in a span (not the <td>) so the shared red colour wins over the cell's own text colour.
        const amt = (v, str) => v < 0 ? `<span class="fims-report-table-neg">${str}</span>` : str;
        const amtStr = amt(r.amount, formatNumber(r.amount));
        const nhaStr = r.nha === null || r.nha === undefined ? '—' : amt(r.nha, formatNumber(r.nha));
        return `<tr class="${r.class}"><td>${sc_esc(r.description)}</td><td class="text-end fims-report-table-num">${amtStr}</td><td class="text-end fims-report-table-num">${nhaStr}</td><td class="text-end fims-report-table-num"></td></tr>`;
    }).join('');
}

/* ── SCNAE tab ─────────────────────────────────────────────── */
/* GET /statement_of_changes/equity → { columns, entities: [{ name, has_activity, periods: [prior year, current year] }] }.
   Consolidated view: the Consolidated block, then every book that has any balance or movement. A picked book: just that book. */
async function sc_loadEquity(fy, book) {
    const params = new URLSearchParams({ fiscal_yr: fy });
    if (book) params.set('book_id', book);
    const res = await sc_authFetch(`${SC_EQUITY_API}?${params}`);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to load data.');

    const d = json.data;
    $sc('sc-eq-hint').textContent = `For the Year Ended December 31, ${fy} · prior year shown above the current year · amounts in Philippine Peso`;
    const entities = (d.entities || []).filter(e => !e.book_id || book || e.has_activity);
    $sc('sc-eq-wrap').innerHTML = entities.length
        ? entities.map(e => sc_equityBlock(e, d.columns)).join('')
        : '<div class="nha-table-wrap fims-report-table-wrap"><p class="text-center" style="padding:16px;">No data available.</p></div>';
}

function sc_equityAmt(v) {
    if (v === null || v === undefined || Math.abs(v) < 0.005) return '-';
    const s = new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Math.abs(v));
    return v < 0 ? `<span class="fims-report-table-neg">(${s})</span>` : s;
}

function sc_equityBlock(entity, columns) {
    const span = columns.length + 2;                              // label + one per equity column + Total
    const cells = v => columns.map(c => `<td class="text-end fims-report-table-num">${sc_equityAmt(v[c.key])}</td>`).join('')
        + `<td class="text-end fims-report-table-num">${sc_equityAmt(v.total)}</td>`;
    const head = '<th></th>' + columns.map(c => `<th class="text-end">${sc_esc(c.label)}</th>`).join('') + '<th class="text-end">Total</th>';

    const body = (entity.periods || []).map((p, i) => {
        const rows = [];
        if (i > 0) rows.push(`<tr><td colspan="${span}" style="height:10px;"></td></tr>`);
        rows.push(`<tr class="fims-report-table-subtotal-row"><td>${sc_esc(p.opening_label)}</td>${cells(p.opening)}</tr>`);
        rows.push(`<tr class="fims-report-table-group-row"><td colspan="${span}">${sc_esc(p.changes_label)}</td></tr>`);
        (p.rows || []).forEach(r => {
            rows.push(r.type === 'label'
                ? `<tr class="fims-report-table-row"><td colspan="${span}" class="sc-eq-label sc-eq-note">${sc_esc(r.label)}</td></tr>`
                : `<tr class="fims-report-table-row"><td class="sc-eq-label">${sc_esc(r.label)}</td>${cells(r)}</tr>`);
        });
        rows.push(`<tr class="fims-report-table-grand-row"><td>${sc_esc(p.closing_label)}</td>${cells(p.closing)}</tr>`);
        return rows.join('');
    }).join('');

    return `<div class="sc-eq-entity">
        <div class="sc-eq-name">${sc_esc(entity.name)}</div>
        <div class="nha-table-wrap fims-report-table-wrap"><div class="nha-tbl-scroll fims-report-table-scroll">
            <table class="nha-tbl fims-report-table"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table>
        </div></div>
    </div>`;
}

function sc_sampleData(fy) {
    return {
        fiscal_yr: fy,
        opening_balance: { total: 100000000 },
        surplus_deficit: 5000000,
        adjustments: 0,
    };
}

/* ── Excel / PDF ───────────────────────────────────────────── */
/* Both are built from the table currently on screen (so they match the picked Book / Consolidated view). Amounts are parsed
   back from the displayed text into real numbers; "—" (no NHA figure) becomes an empty cell. The page's Prior Year
   column is never filled in, so it is left out of both. */
function sc_parseAmount(text) {
    const t = String(text || '').trim();
    if (!/\d/.test(t)) return null;
    const n = Number(t.replace(/[^0-9.]/g, ''));
    return /^[(-]/.test(t) ? -n : n;
}

const SC_CERT = 'I HEREBY CERTIFY that the foregoing Statement of Changes in Net Assets/Equity is a true and complete statement of the changes in the net assets/equity as shown in the books of accounts, prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).';

/* The table on screen as data — the ONE source the PDF payload, the Excel preview and the Excel workbook are all made from, so
   the three can't disagree. rows[]: { cls (the row's classes), major (a colspan section band), title, values (amounts, null = blank) }. */
function sc_sheetModel() {
    const text = el => el.textContent.trim();
    const nCols = 3; // Description, Consolidated/Book, NHA (Main Office)
    const head = [...document.querySelectorAll('#sc-thead th')].slice(0, nCols).map(text);
    const titleLines = [
        { text: 'NATIONAL HOUSING AUTHORITY', bold: true },
        { text: text($sc('sc-title-line2')), bold: true },
    ];
    if ($sc('sc-title-line3').style.display !== 'none') titleLines.push({ text: text($sc('sc-title-line3')), bold: true });
    titleLines.push({ text: text($sc('sc-report-date')), bold: false });
    if ($sc('sc-title-peso').style.display !== 'none') titleLines.push({ text: text($sc('sc-title-peso')), bold: false });

    const rows = [...document.querySelectorAll('#sc-tbody tr')].map(tr => {
        const cells = [...tr.cells];
        return { cls: tr.className, major: cells.length === 1, title: text(cells[0]), values: cells.slice(1, nCols).map(c => sc_parseAmount(c.textContent)) };
    });
    return { head, nCols, titleLines: titleLines.filter(l => l.text), rows };
}

/* The row's kind, as the server's print form names it (BalanceSheetPrintService). */
function sc_rowKind(r) {
    if (r.major) return 'major';
    if (/grand-row/.test(r.cls)) return 'grand';
    if (/subtotal-row/.test(r.cls)) return 'subtotal';
    return 'account';
}

/* ── Print / Export: menu → preview → Print / Download ─────────────────────────────────────────
   PDF   — the server renders it (POST /print/sc → PrintService 'SC', Dompdf, like the other printed forms) from the table on
           screen; the preview IS that PDF, so Print and Download give exactly what is shown.
   Excel — built here with ExcelJS from the same model (sc_buildWorkbook); the preview draws that sheet. */
const SC_PRINT_API = SC_BASE + '/print/sc';
const sc_x = { format: 'pdf', seq: 0, file: null, url: null };   // file = { blob, filename } shown in the preview

function sc_toggleMenu(open) {
    const menu = $sc('sc-export-menu'), btn = $sc('sc-export');
    if (!menu || !btn) return;
    if (!open) { menu.style.display = 'none'; btn.setAttribute('aria-expanded', 'false'); return; }
    menu.style.display = '';
    const r = btn.getBoundingClientRect(), w = menu.offsetWidth || 230, h = menu.offsetHeight || 90;
    menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menu.style.top  = (r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
    btn.setAttribute('aria-expanded', 'true');
}

/* The file name stem both formats share: Statement-of-Changes_<year> (the server builds the same one for the PDF). */
function sc_fileStem() { return `Statement-of-Changes_${$sc('sc-filter-fy').value}`; }

/* There is a loaded table to export (not loading, not the "No data available." message). */
function sc_hasData() {
    const trs = [...document.querySelectorAll('#sc-tbody tr')];
    return trs.length > 0 && !document.querySelector('#sc-tbody .nha-loader') && !trs.some(tr => tr.cells.length === 1 && !tr.className);
}

/* What the server prints: the table on screen. */
function sc_printPayload() {
    const m = sc_sheetModel();
    return {
        fiscal_yr: $sc('sc-filter-fy').value,
        lines: m.titleLines,
        columns: m.head,
        rows: m.rows.map(r => ({ kind: sc_rowKind(r), title: r.title, values: r.values })),
    };
}

function sc_xState(msg, err) {
    $sc('sc-x-msg').hidden = !msg;
    if (msg) $sc('sc-x-msg').textContent = msg;
    $sc('sc-x-err').hidden = !err;
    if (err) $sc('sc-x-err').textContent = err;
}
function sc_xReady(on) {
    $sc('sc-x-print').disabled = $sc('sc-x-download').disabled = !on;
}
function sc_releaseFile() {
    if (sc_x.url) URL.revokeObjectURL(sc_x.url);
    sc_x.url = null; sc_x.file = null;
}

async function sc_openExport(format) {
    if (!sc_hasData()) { sc_showError('Nothing to export — no data loaded.'); return; }
    if (format === 'xlsx' && !window.ExcelJS) { sc_showError('Excel library failed to load. Check your internet connection and reload.'); return; }
    sc_hideError();

    const my = ++sc_x.seq;
    sc_releaseFile();
    sc_x.format = format === 'xlsx' ? 'xlsx' : 'pdf';
    const pdf = sc_x.format === 'pdf';
    const book = $sc('sc-filter-book').selectedOptions?.[0]?.textContent || '';
    $sc('sc-x-title').textContent = 'Statement of Changes in Net Assets/Equity — ' + (pdf ? 'PDF' : 'Excel');
    $sc('sc-x-info').textContent = [book, $sc('sc-report-date').textContent].filter(Boolean).join(' · ');
    $sc('sc-x-print').hidden = !pdf;                       // an Excel file is downloaded, not printed
    $sc('sc-x-download').textContent = pdf ? 'Download PDF' : 'Download Excel';
    sc_xReady(false);
    $sc('sc-x-frame').hidden = true; $sc('sc-x-frame').removeAttribute('src');
    $sc('sc-x-sheet').hidden = true;
    sc_xState('Loading preview…', '');
    $sc('sc-overlay').classList.add('open');

    try {
        if (pdf) await sc_loadPdf(my); else await sc_loadExcel(my);
    } catch (e) {
        if (my !== sc_x.seq) return;                       // closed or reopened while loading
        sc_xState('The preview could not be loaded.', e.message);
    }
}

function sc_closeExport() {
    sc_x.seq++;
    $sc('sc-overlay').classList.remove('open');
    $sc('sc-x-frame').hidden = true; $sc('sc-x-frame').removeAttribute('src');
    $sc('sc-x-sheet').innerHTML = '';
    sc_releaseFile();
}

async function sc_loadPdf(my) {
    const res = await sc_authFetch(SC_PRINT_API, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign(sc_printPayload(), { format: 'pdf' })),
    });
    let json = null; try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
    if (my !== sc_x.seq) return;

    const bin = atob(json.data), bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    sc_x.file = { blob: new Blob([bytes], { type: json.mime || 'application/pdf' }), filename: json.filename || (sc_fileStem() + '.pdf') };
    sc_x.url  = URL.createObjectURL(sc_x.file.blob);

    $sc('sc-x-frame').src = sc_x.url + '#toolbar=0&navpanes=0';   // the viewer's own toolbar is hidden: Print / Download are above
    $sc('sc-x-frame').hidden = false;
    sc_xState('', '');
    sc_xReady(true);
}

async function sc_loadExcel(my) {
    const buf = await sc_buildWorkbook().xlsx.writeBuffer();
    if (my !== sc_x.seq) return;
    sc_x.file = { blob: new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename: sc_fileStem() + '.xlsx' };
    $sc('sc-x-sheet').innerHTML = sc_excelPreviewHtml();
    $sc('sc-x-sheet').hidden = false;
    sc_xState('', '');
    sc_xReady(true);
}

/* Print the PDF shown in the preview; if the browser won't print from the frame, open it in a tab (its viewer prints). */
function sc_printPdf() {
    const frame = $sc('sc-x-frame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (e) {
        if (sc_x.url) window.open(sc_x.url, '_blank');
    }
}

function sc_downloadFile() {
    if (!sc_x.file) return;
    const a = document.createElement('a');
    const url = URL.createObjectURL(sc_x.file.blob);
    a.href = url;
    a.download = sc_x.file.filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/* The Excel preview: the sheet sc_buildWorkbook() writes (same model, same row order and styling rules), drawn as a spreadsheet —
   keep the two in step. Amounts: zero "-", negative in brackets (the workbook's number format). */
function sc_excelPreviewHtml() {
    const m = sc_sheetModel(), n = m.nCols;
    const num = v => {
        if (v === null || v === undefined) return '';
        if (Math.abs(v) < 0.005) return '-';
        const s = Math.abs(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return v < 0 ? `(${s})` : s;
    };
    const out = [];
    let rn = 0;
    const row = (cells, trStyle = '') => { rn++; out.push(`<tr style="${trStyle}"><td class="rn">${rn}</td>${cells}</tr>`); };
    const merged = (text, style = '') => row(`<td colspan="${n}" style="${style}">${sc_esc(text)}</td>`);
    const blank = () => row('<td></td>'.repeat(n));

    m.titleLines.forEach(l => merged(l.text, 'text-align:center;' + (l.bold ? 'font-weight:700;' : '')));
    blank();
    const hd = 'background:#D9E1F2;font-weight:700;border:1px solid #000;';
    row(m.head.map((h, i) => `<td style="${hd}text-align:${i > 0 ? 'right' : 'center'};">${sc_esc(h)}</td>`).join(''));

    m.rows.forEach(r => {
        if (r.major) { merged(r.title, 'background:#EDEDED;font-weight:700;'); return; }
        const kind = sc_rowKind(r);
        const total = kind === 'subtotal' || kind === 'grand';
        const rule = total ? 'font-weight:700;border-top:1px solid #000;' + (kind === 'grand' ? 'border-bottom:3px double #000;' : '') : '';
        // as the workbook: every line indented one level
        row(`<td style="padding-left:16px;white-space:normal;${rule}">${sc_esc(r.title)}</td>` + r.values.map(v => `<td class="n" style="${rule}">${num(v)}</td>`).join(''));
    });

    blank();
    merged(SC_CERT, 'height:48px;vertical-align:top;white-space:normal;');
    blank();
    blank();
    row('<td style="border-top:1px solid #000;text-align:center;">Signature Over Printed Name</td>' + '<td></td>'.repeat(n - 1));
    row('<td style="text-align:center;">Head of Accounting Department</td>' + '<td></td>'.repeat(n - 1));

    const cols = ['<col style="width:34px">', ...m.head.map((_, i) => `<col style="width:${i === 0 ? 420 : 140}px">`)].join('');
    const letters = m.head.map((_, i) => `<th>${String.fromCharCode(65 + i)}</th>`).join('');
    return `<table class="scx-xl"><colgroup>${cols}</colgroup><thead><tr><th></th>${letters}</tr></thead><tbody>${out.join('')}</tbody></table>`;
}

/* ── Excel workbook ────────────────────────────────────────── */
function sc_buildWorkbook() {
    const { head: headCells, nCols, titleLines, rows } = sc_sheetModel();

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Changes in Net Assets', {
        pageSetup: { paperSize: 9, orientation: 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });
    ws.columns = [{ width: 70 }, { width: 22 }, { width: 22 }];
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
    titleLines.forEach(l => title(l.text, l.bold));
    ws.addRow([]);

    const head = ws.addRow(headCells);
    head.eachCell((c, i) => {
        c.font = { bold: true };
        c.fill = fill('FFD9E1F2');
        c.border = { top: thin, bottom: thin, left: thin, right: thin };
        c.alignment = { horizontal: i > 1 ? 'right' : 'center', wrapText: true };
    });

    rows.forEach(({ cls, major, title: rowTitle, values }) => {
        if (major) { // section header (colspan)
            const m = ws.addRow([rowTitle]);
            ws.mergeCells(m.number, 1, m.number, nCols);
            m.getCell(1).font = { bold: true };
            m.getCell(1).fill = fill('FFEDEDED');
            return;
        }
        const r = ws.addRow([rowTitle, ...values]);
        r.getCell(2).numFmt = NUM;
        r.getCell(3).numFmt = NUM;
        r.getCell(1).alignment = { indent: 1, wrapText: true };
        if (/subtotal-row|grand-row/.test(cls)) {
            r.eachCell(c => { c.font = { bold: true }; c.border = { top: thin, bottom: /grand-row/.test(cls) ? { style: 'double' } : undefined }; });
        }
    });

    ws.addRow([]);
    const cert = ws.addRow([SC_CERT]);
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

    return wb;
}

function sc_showError(msg) {
    const err = $sc('sc-error');
    if (err) {
        $sc('sc-error-msg').textContent = msg;
        err.style.display = 'block';
    }
}

function sc_hideError() {
    const err = $sc('sc-error');
    if (err) err.style.display = 'none';
}

function sc_esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function formatNumber(n) {
    if (!n && n !== 0) return '—';
    return new Intl.NumberFormat('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
}

function round2(n) {
    return Math.round(n * 100) / 100;
}
</script>
