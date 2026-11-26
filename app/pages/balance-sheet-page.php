<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Balance Sheet (Statement of Financial Position) — position as of a fiscal year-end, built from
// every JEV that was approved and POSTED to fims.general_ledger, via GET /balance_sheet
// (BalanceSheetService.php). Laid out like the Trial Balance page: centered print title, then
// Current/Non-Current Assets, Current/Non-Current Liabilities and Net Assets/Equity, each grouped
// by chart-of-accounts group (e.g. "1-01 Cash and Cash Equivalents"), with the prior year-end and
// the Increase/(Decrease) beside it. Read-only.
$_bs_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_bs_api = preg_match('~/api$~', $_bs_api) ? $_bs_api : $_bs_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />


<style>
    /* Table styling (.fims-report-table*) lives in _shared/css/nha-fims.css. Only page-specific, non-table rules remain here. */
    #bs-root .bs-report-title { text-align: center; margin: 0 0 18px; }
    #bs-root .bs-report-title div { font-weight: 700; font-size: 14px; line-height: 1.6; }
    #bs-root .bs-report-title .bs-report-date { font-weight: 400; }
    #bs-root .bs-sample { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; background: #FFF4D6; color: #6B4A00; border: 1px solid #F0D58A; }
    #bs-root .bs-diff { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; background: #FDECEA; color: #8A1C12; border: 1px solid #F3B8B2; }

    /* Same page-level type scale as the Trial Balance page. _shared/js/custom.js forces `1em * scale !important`
       on inputs/selects/buttons, so those get the same direct rem + !important. */
    #bs-root .nha-hdr-tag             { font-size: 0.75rem; }    /* 12px */
    #bs-root .nha-hdr-title           { font-size: 1.25rem; }    /* 20px */
    #bs-root .nha-btn-hdr             { font-size: calc(0.875rem * var(--button-scale, 1)) !important; } /* 14px  Refresh */
    #bs-root .nha-filter-label        { font-size: 0.75rem; }    /* 12px */
    #bs-root .nha-filter-select       { font-size: calc(0.875rem * var(--input-scale, 1)) !important; }  /* 14px  dropdowns */
    #bs-root .nha-bdg                 { font-size: 0.8125rem; }  /* 13px  status badge */
    #bs-root .nha-error               { font-size: 0.875rem; }   /* 14px */
    #bs-root .nha-hint                { font-size: 0.875rem; padding: 0 16px; margin: 0 0 14px; color: var(--afims-text-muted, #62657E); }   /* 14px; 16px side padding lines it up with the filter card */


    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-sample { background: #3A2F10; color: #F5D98A; border-color: #6B5A22; }
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-diff { background: #3B1A17; color: #FFB4AB; border-color: #7A332C; }
    /* Print / Export — one header button → PDF | Excel menu → preview modal (Print / Download on a bar above the preview) */
    #bs-root .bs-menu-item { display: block; width: 100%; text-align: left; padding: 8px 10px; border: 0; border-radius: 6px; background: transparent; cursor: pointer; font-family: inherit; }
    #bs-root .bs-menu-item:hover, #bs-root .bs-menu-item:focus-visible { background: var(--afims-surface-subtle, #F4F7FA); outline: none; }
    #bs-root .bs-menu-item strong { display: block; font-size: 13px; color: var(--afims-text, #1e293b); }
    #bs-root .bs-menu-item small  { display: block; font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
    #bs-root .bsx-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
    #bs-root .bsx-info { font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
    #bs-root .bsx-actions { display: flex; gap: 8px; }
    #bs-root .bsx-actions [hidden] { display: none !important; }
    #bs-root .bsx-stage { position: relative; height: 68vh; overflow: auto; background: #E8ECF2; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; }
    #bs-root .bsx-frame { display: block; width: 100%; height: 100%; border: 0; background: #E8ECF2; }
    #bs-root .bsx-msg { padding: 60px 20px; text-align: center; font-size: 13px; color: var(--afims-text-muted, #62657E); }
    #bs-root .bsx-err { margin-top: 10px; padding: 8px 12px; border-radius: 7px; background: #FDECEC; color: #B42318; font-size: 13px; }
    #bs-root .bsx-msg[hidden], #bs-root .bsx-err[hidden], #bs-root .bsx-frame[hidden], #bs-root .bsx-sheet[hidden] { display: none; }
    /* the Excel preview: the sheet the .xlsx will contain, with a spreadsheet's column letters / row numbers, centred in the stage */
    #bs-root .bsx-sheet { padding: 14px; }
    #bs-root table.bsx-xl { margin: 0 auto; border-collapse: collapse; background: #fff; font: 12px Calibri, Arial, sans-serif; box-shadow: 0 1px 6px rgba(15, 32, 69, .18); }
    #bs-root table.bsx-xl th { padding: 2px 8px; border: 1px solid #d0d3d6; background: #f1f3f4; color: #5f6368; font-weight: 500; text-align: center; }
    #bs-root table.bsx-xl td { height: 20px; padding: 2px 6px; border: 1px solid #e1e3e6; }
    #bs-root table.bsx-xl td.rn { background: #f1f3f4; color: #5f6368; text-align: center; padding: 2px 4px; }
    #bs-root table.bsx-xl td.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    @media print {
        #bs-root .bs-letterhead,
        #bs-root .bs-cert { display: block !important; }
        #bs-root .bs-sample { display: none !important; }
    }
</style>

<div id="bs-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M5 7h14M5 7l-2.5 6a3 3 0 005 0L5 7zm14 0l-2.5 6a3 3 0 005 0L19 7z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Balance Sheet</h1>
            <p class="app-page-subtitle">Statement of Financial Position as of the end of the selected fiscal year. It adds up every
                Journal Entry Voucher that was approved and posted to the General Ledger, so a voucher still in review does not appear here.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="bs-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="bs-export" type="button" class="nha-btn-hdr" title="Print or export the Balance Sheet — PDF or Excel" aria-haspopup="true" aria-expanded="false">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print / Export &#9662;
            </button>
        </div>
    </div>

    <!-- Format menu of the Print / Export button (fixed, so the banner's rounded corners can't clip it) -->
    <div id="bs-export-menu" role="menu" class="no-print" style="display:none;position:fixed;z-index:1060;min-width:230px;background:var(--afims-surface,#fff);border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:6px;">
        <button type="button" role="menuitem" class="bs-menu-item" data-format="pdf"><strong>PDF</strong><small>Preview, then print or download</small></button>
        <button type="button" role="menuitem" class="bs-menu-item" data-format="xlsx"><strong>Excel</strong><small>Preview, then download (.xlsx)</small></button>
    </div>

    <!-- ── PRINT / EXPORT PREVIEW ─────────────────────────── -->
    <div id="bs-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="bs-x-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="bs-x-title">Balance Sheet</h3>
                <button type="button" class="nha-modal-close" id="bs-x-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="bsx-bar">
                    <span class="bsx-info" id="bs-x-info"></span>
                    <span class="bsx-actions">
                        <button type="button" class="nha-btn nha-btn-outline" id="bs-x-print" disabled>Print</button>
                        <button type="button" class="nha-btn nha-btn-primary" id="bs-x-download" disabled>Download</button>
                    </span>
                </div>
                <div class="bsx-stage">
                    <div class="bsx-msg" id="bs-x-msg">Loading preview&hellip;</div>
                    <iframe class="bsx-frame" id="bs-x-frame" title="Balance Sheet PDF preview" hidden></iframe>
                    <div class="bsx-sheet" id="bs-x-sheet" hidden></div>
                </div>
                <div class="bsx-err" id="bs-x-err" role="alert" hidden></div>
            </div>
        </div>
    </div>

    <div id="bs-sample" class="bs-sample no-print">
        <strong>Sample data.</strong> These figures are made up to show the layout. They are not from the General Ledger.
    </div>

    <!-- Error banner -->
    <div id="bs-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="bs-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="bs-filter-fy">Fiscal Year</label>
                <select id="bs-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="bs-filter-book">Book</label>
                <select id="bs-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="bs-filter-view">View</label>
                <select id="bs-filter-view" class="nha-filter-select">
                    <option value="consolidated" selected>Consolidated</option>
                    <option value="detailed">Detailed</option>
                    <option value="condensed">Condensed</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <span id="bs-badge" class="nha-bdg nha-bdg-gray">—</span>
            </div>
        </div>
    </div>

    <div id="bs-diff" class="bs-diff no-print"></div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="bs-letterhead" style="display:none;border:1px solid #000;padding:8px 10px;margin-bottom:0;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:14%;vertical-align:middle;">
                <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;margin:0 auto;object-fit:contain;">
            </td>
            <td class="bs-report-title" style="width:51%;text-align:center;vertical-align:middle;">
                <div style="font-weight:700;">NATIONAL HOUSING AUTHORITY</div>
                <div id="bs-title-line2" style="font-weight:700;">STATEMENT OF FINANCIAL POSITION</div>
                <div id="bs-title-line3" style="font-weight:700;">MAIN OFFICE</div>
                <div class="bs-report-date"><span id="bs-report-date">—</span></div>
                <div id="bs-title-peso" style="display:none;font-weight:400;">(In Philippine Peso)</div>
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
                <thead id="bs-thead">
                    <tr>
                        <th>Account Title</th>
                        <th class="text-end" id="bs-th-cur">Current Year</th>
                        <th class="text-end" id="bs-th-prev">Prior Year</th>
                        <th class="text-end">Increase / (Decrease)</th>
                    </tr>
                </thead>
                <tbody id="bs-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── CERTIFICATION (shown only on print) ───────────── -->
    <div class="bs-cert" style="display:none;margin-top:28px;font-size:9.5pt;">
        <p style="margin:0 0 30px;">I HEREBY CERTIFY that the foregoing Statement of Financial Position is a true and
            complete statement of the assets, liabilities and equity as shown in the books of accounts, prepared and
            presented in conformity with International Public Sector Accounting Standards (IPSAS).</p>
        <div style="width:260px;text-align:center;">
            <div style="border-top:1px solid #000;padding-top:4px;">Signature Over Printed Name</div>
            <div>Head of Accounting Department</div>
        </div>
    </div>

</div><!-- /#bs-root -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   BALANCE SHEET   prefix: bs_
═══════════════════════════════════════════════════════════════ */

const BS_BASE     = <?= json_encode($_bs_api) ?>;
const BS_API      = BS_BASE + '/balance_sheet';
const BS_CONS_API = BS_BASE + '/balance_sheet/consolidated';
const BS_FY_API   = BS_BASE + '/fiscal_year/all';
const BS_BOOK_API = BS_BASE + '/books/all';
let bs_books = [];        // [{book_id, name}] — labels the report title
let bs_useSample = false; // sample-data preview, toggled from the console (see bs_bindEvents)

const $bs = id => document.getElementById(id);

function bs_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function bs_boot() {
    await Promise.all([bs_loadFiscalYears(), bs_loadBooks()]);
    bs_bindEvents();
    bs_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bs_boot);
else bs_boot();

function bs_bindEvents() {
    $bs('bs-refresh')?.addEventListener('click', bs_load);
    $bs('bs-filter-fy')?.addEventListener('change', bs_load);
    $bs('bs-filter-book')?.addEventListener('change', bs_load);
    $bs('bs-filter-view')?.addEventListener('change', () => { bs_syncBookFilter(); bs_load(); });
    bs_syncBookFilter();

    // Print / Export: the header button's menu (PDF | Excel) → the preview modal
    $bs('bs-export')?.addEventListener('click', e => { e.stopPropagation(); bs_toggleMenu($bs('bs-export-menu').style.display === 'none'); });
    $bs('bs-export-menu')?.addEventListener('click', e => {
        e.stopPropagation();
        const item = e.target.closest('.bs-menu-item');
        if (!item) return;
        bs_toggleMenu(false);
        bs_openExport(item.dataset.format);
    });
    document.addEventListener('click', () => bs_toggleMenu(false));
    window.addEventListener('scroll', () => bs_toggleMenu(false), true);
    window.addEventListener('resize', () => bs_toggleMenu(false));
    $bs('bs-x-close')?.addEventListener('click', bs_closeExport);
    $bs('bs-overlay')?.addEventListener('click', e => { if (e.target === $bs('bs-overlay')) bs_closeExport(); });
    $bs('bs-x-print')?.addEventListener('click', bs_printPdf);
    $bs('bs-x-download')?.addEventListener('click', bs_downloadFile);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && $bs('bs-overlay').classList.contains('open')) { e.preventDefault(); bs_closeExport(); }
    });

    /* Sample preview has no button: type `balance_sheet = true` in the browser console to show
       made-up figures, `balance_sheet = false` to go back to live data. */
    Object.defineProperty(window, 'balance_sheet', {
        configurable: true,
        get: () => bs_useSample,
        set: v => {
            bs_useSample = !!v;
            $bs('bs-sample').style.display = bs_useSample ? 'block' : 'none';
            bs_load();
        },
    });
}

/* Consolidated lays every book out as its own column, so picking one Book to filter down to
   doesn't apply — grey the control out instead of pretending it still does something. */
function bs_syncBookFilter() {
    const isConsolidated = $bs('bs-filter-view').value === 'consolidated';
    const sel = $bs('bs-filter-book');
    sel.disabled = isConsolidated;
    sel.closest('.nha-filter-group').style.opacity = isConsolidated ? 0.5 : 1;
}

/* A balance sheet is "as of" one year-end, so there is no "All Years" option: default to the
   current year when it exists, otherwise the latest fiscal year on file. */
async function bs_loadFiscalYears() {
    const sel = $bs('bs-filter-fy');
    let years = [];
    try {
        const res  = await bs_authFetch(BS_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        years = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(Boolean);
    } catch (e) {
        bs_showError('Fiscal years: ' + e.message);
    }
    const now = new Date().getFullYear();
    if (!years.length) years = [now];
    years = [...new Set(years)].sort((a, b) => b - a);
    sel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = String(years.includes(now) ? now : years[0]);
}

async function bs_loadBooks() {
    const sel = $bs('bs-filter-book');
    try {
        const res  = await bs_authFetch(BS_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        bs_books = json.data ?? [];
        sel.innerHTML = '<option value="">All Books</option>' +
            bs_books.map(r => `<option value="${bs_esc(r.book_id)}">${bs_esc(r.name)}</option>`).join('');
    } catch (e) {
        bs_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function bs_load() {
    window.PagePreloader?.show();
    bs_hideError();
    const fy   = $bs('bs-filter-fy').value;
    const book = $bs('bs-filter-book').value;
    const view = $bs('bs-filter-view').value || 'consolidated';
    try {
        if (view === 'consolidated') {
            let data;
            if (bs_useSample) {
                data = bs_sampleConsolidatedData(Number(fy) || new Date().getFullYear());
            } else {
                const params = new URLSearchParams();
                if (fy) params.set('fiscal_yr', fy);
                const res  = await bs_authFetch(`${BS_CONS_API}?${params}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Failed to load the Consolidated Statement of Financial Position.');
                data = json.data;
            }
            bs_renderTitle(data.fiscal_yr, null, 'consolidated');
            bs_renderConsolidated(data);
        } else {
            let data;
            if (bs_useSample) {
                data = bs_applyViewMode(bs_sampleData(Number(fy) || new Date().getFullYear()), view);
            } else {
                const params = new URLSearchParams();
                if (fy) params.set('fiscal_yr', fy);
                if (book) params.set('book_id', book);
                params.set('view_mode', view);
                const res  = await bs_authFetch(`${BS_API}?${params}`);
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                if (!json.success) throw new Error(json.message || 'Failed to load the Balance Sheet.');
                data = json.data;
            }
            bs_renderTitle(data.fiscal_yr, book, view);
            bs_renderSingleThead();
            bs_render(data);
        }
    } catch (e) {
        if (view === 'consolidated') bs_renderConsolidated(null, Number(fy) || new Date().getFullYear());
        else { bs_renderSingleThead(); bs_render(null, Number(fy) || new Date().getFullYear()); }
        bs_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function bs_renderTitle(fiscalYr, bookId, view) {
    const d = new Date(Number(fiscalYr), 11, 31);
    const dateText = d.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
    const line2 = $bs('bs-title-line2'), line3 = $bs('bs-title-line3'), peso = $bs('bs-title-peso');
    if (view === 'consolidated') {
        line2.innerHTML = 'Consolidated STATEMENTS OF FINANCIAL POSITION';
        line3.style.display = 'none';
        peso.style.display = 'block';
        $bs('bs-report-date').textContent = 'As at ' + dateText;
    } else {
        line2.textContent = 'STATEMENT OF FINANCIAL POSITION';
        const book = bs_books.find(b => String(b.book_id) === String(bookId));
        line3.textContent = book ? book.name.toUpperCase() : 'MAIN OFFICE';
        line3.style.display = 'block';
        peso.style.display = 'none';
        $bs('bs-report-date').textContent = dateText;
    }
}

/* The single-entity table always has the same 5 columns, so just restore them — rebuilt because
   bs_renderConsolidated() replaces the whole <thead> with one column per book. */
function bs_renderSingleThead() {
    $bs('bs-thead').innerHTML = `<tr>
        <th>Account Title</th>
        <th class="text-end" id="bs-th-cur">Current Year</th>
        <th class="text-end" id="bs-th-prev">Prior Year</th>
        <th class="text-end">INCREASE (DECREASE)</th>
    </tr>`;
}

/* ── Consolidated table (multi-entity: one column per fims.books entity + TOTAL) ──────────── */
function bs_renderConsolidated(d, fallbackFy) {
    const tbody = $bs('bs-tbody');
    const badge = $bs('bs-badge'), diff = $bs('bs-diff');
    diff.style.display = 'none';

    const books = d ? d.books : [];
    const colCount = 1 + books.length + 1; // Account Title, one per book, TOTAL

    $bs('bs-thead').innerHTML = `<tr>
        <th>Accounts</th>
        ${books.map(b => `<th class="text-end">${bs_esc(b.name)}</th>`).join('')}
        <th class="text-end">TOTAL</th>
    </tr>`;

    if (!d || !d.sections || !d.sections.length) {
        tbody.innerHTML = `<tr><td colspan="${colCount}"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet for this fiscal year.</span></div></td></tr>`;
        badge.className = 'nha-bdg nha-bdg-gray';
        badge.textContent = '—';
        return;
    }

    const html = [];
    const amounts = byBook => books.map(b => `<td class="text-end nha-mono fims-report-table-num">${bs_peso(byBook[b.book_id] ?? 0)}</td>`).join('');
    const row = (cls, title, byBook, total) => {
        const isAccount = cls === 'fims-report-table-row';
        const netValue = isAccount && /^Net Value-/.test(String(title || ''));
        const contra = isAccount && !netValue && /^(Accumulated Depreciation|Allowance for Impairment)/i.test(String(title || ''));
        const extra = contra ? ' fims-report-table-contra-row' : (netValue ? ' fims-report-table-netvalue-row' : '');
        return `<tr class="${cls}${extra}"><td>${bs_esc(title)}</td>${amounts(byBook)}<td class="text-end nha-mono fims-report-table-num">${bs_peso(total)}</td></tr>`;
    };
    const grand = (title, byBook, total) =>
        `<tr class="fims-report-table-grand-row"><td>${bs_esc(title)}</td>${amounts(byBook)}<td class="text-end nha-mono fims-report-table-num">${bs_peso(total)}</td></tr>`;

    d.sections.forEach(s => {
        html.push(`<tr class="fims-report-table-major-row"><td colspan="${colCount}">${bs_esc(s.label)}</td></tr>`);
        s.groups.forEach(g => {
            html.push(row('fims-report-table-group-row', g.group_title, g.by_book, g.total));
            g.subgroups.forEach(sg => {
                if (sg.group_title) html.push(row('fims-report-table-subgroup-row', sg.group_title, sg.by_book, sg.total));
                sg.accounts.forEach(a => html.push(row('fims-report-table-row', a.account_title, a.by_book, a.total)));
            });
        });
        if (s.key === 'equity' && (d.net_surplus_deficit.total || Object.values(d.net_surplus_deficit.by_book).some(v => v))) {
            html.push(row('fims-report-table-row', 'Net Surplus / (Deficit), not yet closed', d.net_surplus_deficit.by_book, d.net_surplus_deficit.total));
        }
        html.push(row('fims-report-table-subtotal-row', 'Total ' + s.label, s.subtotal_by_book, s.subtotal_total));

        if (s.key === 'non_current_assets')      html.push(grand('Total Assets', d.totals.total_assets_by_book, d.totals.total_assets));
        if (s.key === 'non_current_liabilities') html.push(grand('Total Liabilities', d.totals.total_liabilities_by_book, d.totals.total_liabilities));
        if (s.key === 'equity') {
            const combined = {};
            books.forEach(b => { combined[b.book_id] = (d.totals.total_liabilities_by_book[b.book_id] ?? 0) + (d.totals.total_equity_by_book[b.book_id] ?? 0); });
            html.push(grand('Total Liabilities and Net Assets / Equity', combined, d.totals.total_liabilities_and_equity));
        }
    });
    tbody.innerHTML = html.join('');

    if (d.balanced) {
        badge.className = 'nha-bdg nha-bdg-green';
        badge.textContent = 'Balanced';
    } else {
        badge.className = 'nha-bdg nha-bdg-red';
        badge.textContent = 'Out of balance';
        diff.style.display = 'block';
        diff.textContent = 'Assets and Liabilities + Equity differ by ' + bs_peso(d.difference) +
            '. The ledger is missing entries (for example opening balances); the report does not adjust it.';
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function bs_render(d, fallbackFy) {
    const tbody = $bs('bs-tbody');
    const fy    = d ? d.fiscal_yr : fallbackFy;
    $bs('bs-th-cur').textContent  = String(fy);
    $bs('bs-th-prev').textContent = (fy - 1) + ' (As Restated)';

    const badge = $bs('bs-badge'), diff = $bs('bs-diff');
    diff.style.display = 'none';

    // "condensed" deliberately comes back with every group/account stripped, so presence of a
    // section is enough here — groups.length alone would wrongly read as empty in that mode.
    const hasData = d && d.sections && d.sections.length > 0;
    if (!d || !hasData) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet for this fiscal year.</span></div></td></tr>';
        badge.className = 'nha-bdg nha-bdg-gray';
        badge.textContent = '—';
        return;
    }

    const html = [];
    const amounts = (cur, prev) => `
        <td class="text-end nha-mono fims-report-table-num">${bs_peso(cur)}</td>
        <td class="text-end nha-mono fims-report-table-num">${bs_peso(prev)}</td>
        <td class="text-end nha-mono fims-report-table-num">${bs_peso(cur - prev)}</td>`;
    const row = (cls, title, cur, prev) => {
        const isAccount = cls === 'fims-report-table-row';
        const netValue = isAccount && /^Net Value-/.test(String(title || ''));
        const contra = isAccount && !netValue && /^(Accumulated Depreciation|Allowance for Impairment)/i.test(String(title || ''));
        const extra = contra ? ' fims-report-table-contra-row' : (netValue ? ' fims-report-table-netvalue-row' : '');
        return `<tr class="${cls}${extra}"><td>${bs_esc(title)}</td>${amounts(cur, prev)}</tr>`;
    };
    const grand = (title, cur, prev) =>
        `<tr class="fims-report-table-grand-row"><td>${bs_esc(title)}</td>${amounts(cur, prev)}</tr>`;

    d.sections.forEach(s => {
        html.push(`<tr class="fims-report-table-major-row"><td colspan="4">${bs_esc(s.label)}</td></tr>`);
        s.groups.forEach(g => {
            html.push(row('fims-report-table-group-row', g.group_title, g.balance, g.prev_balance));
            g.subgroups.forEach(sg => {
                if (sg.group_title) html.push(row('fims-report-table-subgroup-row', sg.group_title, sg.balance, sg.prev_balance));
                sg.accounts.forEach(a => html.push(row('fims-report-table-row', a.account_title, a.balance, a.prev_balance)));
            });
        });
        if (s.key === 'equity' && (d.net_surplus_deficit.balance || d.net_surplus_deficit.prev_balance)) {
            html.push(row('fims-report-table-row', 'Net Surplus / (Deficit), not yet closed', d.net_surplus_deficit.balance, d.net_surplus_deficit.prev_balance));
        }
        html.push(row('fims-report-table-subtotal-row', 'Total ' + s.label, s.subtotal, s.prev_subtotal));

        if (s.key === 'non_current_assets')      html.push(grand('Total Assets', d.totals.total_assets, d.totals.prev_total_assets));
        if (s.key === 'non_current_liabilities') html.push(grand('Total Liabilities', d.totals.total_liabilities, d.totals.prev_total_liabilities));
        if (s.key === 'equity') {
            html.push(grand('Total Liabilities and Net Assets / Equity',
                d.totals.total_liabilities_and_equity,
                d.totals.prev_total_liabilities + d.totals.prev_total_equity));
        }
    });
    tbody.innerHTML = html.join('');

    if (d.balanced) {
        badge.className = 'nha-bdg nha-bdg-green';
        badge.textContent = 'Balanced';
    } else {
        badge.className = 'nha-bdg nha-bdg-red';
        badge.textContent = 'Out of balance';
        diff.style.display = 'block';
        diff.textContent = 'Assets and Liabilities + Equity differ by ' + bs_peso(d.difference) +
            '. The ledger is missing entries (for example opening balances); the report does not adjust it.';
    }
}

/* Mirrors BalanceSheetService::build()'s view-mode stripping, client-side, so "Preview sample
   data" reflects the View toggle too instead of always showing the fully detailed sample. */
function bs_applyViewMode(data, view) {
    data.view_mode = view;
    data.sections.forEach(s => {
        if (view !== 'detailed') s.groups.forEach(g => { g.subgroups = []; });
        if (view === 'condensed') s.groups = [];
    });
    return data;
}

/* Mirrors BalanceSheetService::withNetValueRows() — inserts a computed "Net Value-{title}" row
   right after any contra account (Accumulated Depreciation / Allowance for Impairment), for both
   sample builders below. */
function bs_withNetValue(accounts, balanceOf, addBalances, makeRow) {
    const out = [];
    let prev = null;
    accounts.forEach(a => {
        out.push(a);
        if (prev && /^(Accumulated Depreciation|Allowance for Impairment)/i.test(String(a.account_title || ''))) {
            out.push(makeRow('Net Value-' + prev.account_title, addBalances(balanceOf(prev), balanceOf(a))));
        }
        prev = a;
    });
    return out;
}

/* ── Sample figures for the Consolidated view (layout preview only; same shape as GET
   /balance_sheet/consolidated) — reuses bs_sampleData()'s single-entity figures as "Main Office"
   and makes up small CISFA/NGC/MPLC figures so the multi-column layout has something to show. */
function bs_sampleConsolidatedData(fy) {
    const books = [
        { book_id: 1, name: 'Main Office' },
        { book_id: 2, name: 'CISFA' },
        { book_id: 3, name: 'NGC' },
        { book_id: 4, name: 'MPLC / PPMO' },
    ];
    const factor = { 1: 1, 2: 0.03, 3: 0.025, 4: 0.005 };
    const byBook = v => Object.fromEntries(books.map(b => [b.book_id, Math.round(v * factor[b.book_id])]));

    const base = bs_sampleData(fy);
    const pivotAccount = a => {
        const by_book = byBook(a.balance);
        return { account_code: a.account_code, account_title: a.account_title, by_book, total: Object.values(by_book).reduce((t, v) => t + v, 0) };
    };
    const sections = base.sections.map(s => ({
        key: s.key, label: s.label, type: s.type,
        groups: s.groups.map(g => {
            const subgroups = g.subgroups.map(sg => {
                const accounts = sg.accounts.map(pivotAccount);
                const sgByBook = byBook(sg.balance);
                return { group_code: sg.group_code, group_title: sg.group_title, accounts, by_book: sgByBook, total: Object.values(sgByBook).reduce((t, v) => t + v, 0) };
            });
            const gByBook = byBook(g.balance);
            return { group_code: g.group_code, group_title: g.group_title, subgroups, by_book: gByBook, total: Object.values(gByBook).reduce((t, v) => t + v, 0) };
        }),
        subtotal_by_book: byBook(s.subtotal), subtotal_total: Object.values(byBook(s.subtotal)).reduce((t, v) => t + v, 0),
    }));

    const surplusByBook = byBook(base.net_surplus_deficit.balance);
    const sumByType = type => {
        const out = Object.fromEntries(books.map(b => [b.book_id, 0]));
        sections.filter(s => s.type === type).forEach(s => books.forEach(b => { out[b.book_id] += s.subtotal_by_book[b.book_id]; }));
        return out;
    };
    const total = byBookObj => Object.values(byBookObj).reduce((t, v) => t + v, 0);
    const assetsByBook = sumByType('asset'), liabByBook = sumByType('liability'), equityByBook = sumByType('equity');
    const assets = total(assetsByBook), liab = total(liabByBook), equity = total(equityByBook);

    return {
        fiscal_yr: fy, books, sections,
        net_surplus_deficit: { by_book: surplusByBook, total: total(surplusByBook) },
        totals: {
            total_assets: assets, total_liabilities: liab, total_equity: equity, total_liabilities_and_equity: liab + equity,
            total_assets_by_book: assetsByBook, total_liabilities_by_book: liabByBook, total_equity_by_book: equityByBook,
        },
        balanced: Math.abs(assets - (liab + equity)) < 0.005, difference: assets - (liab + equity),
    };
}

/* ── Sample figures (layout preview only; same shape as GET /balance_sheet) ── */
function bs_sampleData(fy) {
    const balanceOf   = a => ({ balance: a.balance, prev_balance: a.prev_balance });
    const addBalances = (a, b) => ({ balance: a.balance + b.balance, prev_balance: a.prev_balance + b.prev_balance });
    const makeRow     = (title, bal) => ({ account_code: '', account_title: title, balance: bal.balance, prev_balance: bal.prev_balance });

    const acct = (code, title, b, p) => ({ account_code: code, account_title: title, balance: b, prev_balance: p });
    const sub  = (code, title, accts) => {
        accts = bs_withNetValue(accts, balanceOf, addBalances, makeRow);
        return {
            group_code: code, group_title: title, accounts: accts,
            balance: accts.filter(a => a.account_code).reduce((s, a) => s + a.balance, 0),
            prev_balance: accts.filter(a => a.account_code).reduce((s, a) => s + a.prev_balance, 0),
        };
    };
    const grp  = (code, title, subs) => ({
        group_code: code, group_title: title, subgroups: subs,
        balance: subs.reduce((s, sg) => s + sg.balance, 0), prev_balance: subs.reduce((s, sg) => s + sg.prev_balance, 0),
    });
    const sec = (key, label, type, groups) => ({
        key, label, type, groups,
        subtotal: groups.reduce((s, g) => s + g.balance, 0), prev_subtotal: groups.reduce((s, g) => s + g.prev_balance, 0),
    });
    const ca  = sec('current_assets', 'Current Assets', 'asset', [
        grp('1-01', 'Cash and Cash Equivalents', [
            sub('1-01-01', 'Cash on Hand', [acct('1-01-01-020', 'Petty Cash', 150000, 120000)]),
            sub('1-01-02', 'Cash in Bank-Local Currency', [acct('1-01-02-040', 'Cash in Bank - Local Currency, Current Account', 48250000, 39100000)]),
        ]),
        grp('1-03', 'Receivables', [
            sub('1-03-01', 'Loans and Receivable Accounts', [
                acct('1-03-01-010', 'Accounts Receivable', 12800000, 14350000),
                acct('1-03-01-012', 'Allowance for Impairment-Accounts Receivable', -500000, -480000),
                acct('1-03-03-010', 'Loans Receivable - Others', 96400000, 101200000),
            ]),
        ]),
        grp('1-04', 'Inventories', [sub('1-04-04', 'Supplies and Materials Inventory', [acct('1-04-04-010', 'Office Supplies Inventory', 1900000, 1650000)])]),
    ]);
    const nca = sec('non_current_assets', 'Non-Current Assets', 'asset', [
        grp('1-06', 'Property, Plant and Equipment', [
            sub('1-06-04', 'Buildings and Other Structures', [
                acct('1-06-04-010', 'Buildings', 210000000, 210000000),
                acct('1-06-04-011', 'Accumulated Depreciation - Buildings', -48500000, -43200000),
            ]),
            sub('1-06-05', 'Machinery and Equipment', [acct('1-06-05-020', 'Office Equipment', 9600000, 8900000)]),
        ]),
    ]);
    const cl  = sec('current_liabilities', 'Current Liabilities', 'liability', [
        grp('2-01', 'Financial Liabilities', [sub('', '', [acct('2-01-01-010', 'Accounts Payable', 18900000, 16400000)])]),
        grp('2-02', 'Inter-Agency Payables', [sub('', '', [acct('2-02-01-010', 'Due to BIR', 2100000, 1850000)])]),
    ]);
    const ncl = sec('non_current_liabilities', 'Non-Current Liabilities', 'liability', [
        grp('2-06', 'Provisions', [sub('', '', [acct('2-06-01-010', 'Leave Benefits Payable', 7400000, 6900000)])]),
    ]);
    const eq  = sec('equity', 'Net Assets / Equity', 'equity', [
        grp('3-01', 'Government Equity', [sub('', '', [acct('3-01-01-010', 'Accumulated Surplus/(Deficit)', 280200000, 306970000)])]),
    ]);
    const surplus = { balance: 22000000, prev_balance: 0 };
    eq.subtotal += surplus.balance; eq.prev_subtotal += surplus.prev_balance;

    const sections = [ca, nca, cl, ncl, eq];
    const sum = (type, f) => sections.filter(s => s.type === type).reduce((t, s) => t + s[f], 0);
    const a = sum('asset', 'subtotal'), l = sum('liability', 'subtotal'), e = sum('equity', 'subtotal');
    return {
        fiscal_yr: fy, prev_year: fy - 1, book_id: null, sections, net_surplus_deficit: surplus,
        totals: {
            total_assets: a, total_liabilities: l, total_equity: e, total_liabilities_and_equity: l + e,
            prev_total_assets: sum('asset', 'prev_subtotal'),
            prev_total_liabilities: sum('liability', 'prev_subtotal'),
            prev_total_equity: sum('equity', 'prev_subtotal'),
        },
        balanced: Math.abs(a - (l + e)) < 0.005, difference: a - (l + e),
    };
}

/* ── Excel export ──────────────────────────────────────────── */
/* Built from the table currently on screen, so it matches whichever View (Consolidated / Detailed / Condensed)
   is showing, including the one-column-per-book layout. Row classes decide the styling; amounts are parsed
   back from the "₱ 1,234.00" / "(₱ 1,234.00)" text into real numbers. */
function bs_parseAmount(text) {
    const t = String(text || '').trim();
    if (!/\d/.test(t)) return null;
    const n = Number(t.replace(/[^0-9.]/g, ''));
    return /^\(/.test(t) ? -n : n;
}

const BS_CERT = 'I HEREBY CERTIFY that the foregoing Statement of Financial Position is a true and complete statement of the assets, liabilities and equity as shown in the books of accounts, prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).';

/* The table on screen as data — the ONE source the PDF payload, the Excel preview and the Excel workbook are all made from, so
   the three can't disagree. rows[]: { cls (the row's classes), major (a colspan section band), title, values (amounts, null = blank) }. */
function bs_sheetModel() {
    const text = el => el.textContent.trim();
    const head = [...document.querySelectorAll('#bs-thead th')].map(text);
    const titleLines = [
        { text: 'NATIONAL HOUSING AUTHORITY', bold: true },
        { text: text($bs('bs-title-line2')), bold: true },
    ];
    if ($bs('bs-title-line3').style.display !== 'none') titleLines.push({ text: text($bs('bs-title-line3')), bold: true });
    titleLines.push({ text: text($bs('bs-report-date')), bold: false });
    if ($bs('bs-title-peso').style.display !== 'none') titleLines.push({ text: text($bs('bs-title-peso')), bold: false });

    const rows = [...document.querySelectorAll('#bs-tbody tr')].map(tr => {
        const cells = [...tr.cells];
        return { cls: tr.className, major: cells.length === 1, title: text(cells[0]), values: cells.slice(1).map(c => bs_parseAmount(c.textContent)) };
    });
    return { head, nCols: head.length, titleLines: titleLines.filter(l => l.text), rows };
}

/* The row's kind, as the server's BalanceSheetPrintService names it. "group-row" is also inside "subgroup-row", so that is tested first. */
function bs_rowKind(r) {
    if (r.major) return 'major';
    if (/grand-row/.test(r.cls)) return 'grand';
    if (/subtotal-row/.test(r.cls)) return 'subtotal';
    if (/subgroup-row/.test(r.cls)) return 'subgroup';
    if (/group-row/.test(r.cls)) return 'group';
    if (/netvalue-row/.test(r.cls)) return 'netvalue';
    if (/contra-row/.test(r.cls)) return 'contra';
    return 'account';
}

/* ── Print / Export: menu → preview → Print / Download ─────────────────────────────────────────
   PDF   — the server renders it (POST /print/bs → PrintService 'BS', Dompdf, like the other printed forms) from the table on
           screen; the preview IS that PDF, so Print and Download give exactly what is shown. Landscape when the View has more
           than 4 columns (Consolidated), as the Excel sheet is.
   Excel — built here with ExcelJS from the same model (bs_buildWorkbook); the preview draws that sheet. */
const BS_PRINT_API = BS_BASE + '/print/bs';
const bs_x = { format: 'pdf', seq: 0, file: null, url: null };   // file = { blob, filename } shown in the preview

function bs_toggleMenu(open) {
    const menu = $bs('bs-export-menu'), btn = $bs('bs-export');
    if (!menu || !btn) return;
    if (!open) { menu.style.display = 'none'; btn.setAttribute('aria-expanded', 'false'); return; }
    menu.style.display = '';
    const r = btn.getBoundingClientRect(), w = menu.offsetWidth || 230, h = menu.offsetHeight || 90;
    menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menu.style.top  = (r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
    btn.setAttribute('aria-expanded', 'true');
}

/* The file name stem both formats share: Balance-Sheet_<year>_<view> (the server builds the same one for the PDF). */
function bs_fileStem() { return `Balance-Sheet_${$bs('bs-filter-fy').value}_${$bs('bs-filter-view').value}`; }

/* There is a loaded table to export (not loading, not the "nothing posted" message). */
function bs_hasData() {
    return !!document.querySelector('#bs-tbody tr') && !document.querySelector('#bs-tbody .nha-empty') && !document.querySelector('#bs-tbody .nha-loader');
}

/* What the server prints: the table on screen. */
function bs_printPayload() {
    const m = bs_sheetModel();
    return {
        fiscal_yr: $bs('bs-filter-fy').value,
        view: $bs('bs-filter-view').value,
        lines: m.titleLines,
        columns: m.head,
        rows: m.rows.map(r => ({ kind: bs_rowKind(r), title: r.title, values: r.values })),
    };
}

function bs_xState(msg, err) {
    $bs('bs-x-msg').hidden = !msg;
    if (msg) $bs('bs-x-msg').textContent = msg;
    $bs('bs-x-err').hidden = !err;
    if (err) $bs('bs-x-err').textContent = err;
}
function bs_xReady(on) {
    $bs('bs-x-print').disabled = $bs('bs-x-download').disabled = !on;
}
function bs_releaseFile() {
    if (bs_x.url) URL.revokeObjectURL(bs_x.url);
    bs_x.url = null; bs_x.file = null;
}

async function bs_openExport(format) {
    if (!bs_hasData()) { bs_showError('Nothing to export — no postings for this fiscal year.'); return; }
    if (format === 'xlsx' && !window.ExcelJS) { bs_showError('Excel library failed to load. Check your internet connection and reload.'); return; }
    bs_hideError();

    const my = ++bs_x.seq;
    bs_releaseFile();
    bs_x.format = format === 'xlsx' ? 'xlsx' : 'pdf';
    const pdf = bs_x.format === 'pdf';
    const view = $bs('bs-filter-view').selectedOptions?.[0]?.textContent || '';
    $bs('bs-x-title').textContent = 'Balance Sheet — ' + (pdf ? 'PDF' : 'Excel');
    $bs('bs-x-info').textContent = [view, $bs('bs-report-date').textContent].filter(Boolean).join(' · ');
    $bs('bs-x-print').hidden = !pdf;                       // an Excel file is downloaded, not printed
    $bs('bs-x-download').textContent = pdf ? 'Download PDF' : 'Download Excel';
    bs_xReady(false);
    $bs('bs-x-frame').hidden = true; $bs('bs-x-frame').removeAttribute('src');
    $bs('bs-x-sheet').hidden = true;
    bs_xState('Loading preview…', '');
    $bs('bs-overlay').classList.add('open');

    try {
        if (pdf) await bs_loadPdf(my); else await bs_loadExcel(my);
    } catch (e) {
        if (my !== bs_x.seq) return;                       // closed or reopened while loading
        bs_xState('The preview could not be loaded.', e.message);
    }
}

function bs_closeExport() {
    bs_x.seq++;
    $bs('bs-overlay').classList.remove('open');
    $bs('bs-x-frame').hidden = true; $bs('bs-x-frame').removeAttribute('src');
    $bs('bs-x-sheet').innerHTML = '';
    bs_releaseFile();
}

async function bs_loadPdf(my) {
    const res = await bs_authFetch(BS_PRINT_API, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign(bs_printPayload(), { format: 'pdf' })),
    });
    let json = null; try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
    if (my !== bs_x.seq) return;

    const bin = atob(json.data), bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    bs_x.file = { blob: new Blob([bytes], { type: json.mime || 'application/pdf' }), filename: json.filename || (bs_fileStem() + '.pdf') };
    bs_x.url  = URL.createObjectURL(bs_x.file.blob);

    $bs('bs-x-frame').src = bs_x.url + '#toolbar=0&navpanes=0';   // the viewer's own toolbar is hidden: Print / Download are above
    $bs('bs-x-frame').hidden = false;
    bs_xState('', '');
    bs_xReady(true);
}

async function bs_loadExcel(my) {
    const buf = await bs_buildWorkbook().xlsx.writeBuffer();
    if (my !== bs_x.seq) return;
    bs_x.file = { blob: new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename: bs_fileStem() + '.xlsx' };
    $bs('bs-x-sheet').innerHTML = bs_excelPreviewHtml();
    $bs('bs-x-sheet').hidden = false;
    bs_xState('', '');
    bs_xReady(true);
}

/* Print the PDF shown in the preview; if the browser won't print from the frame, open it in a tab (its viewer prints). */
function bs_printPdf() {
    const frame = $bs('bs-x-frame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (e) {
        if (bs_x.url) window.open(bs_x.url, '_blank');
    }
}

function bs_downloadFile() {
    if (!bs_x.file) return;
    const a = document.createElement('a');
    const url = URL.createObjectURL(bs_x.file.blob);
    a.href = url;
    a.download = bs_x.file.filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/* The Excel preview: the sheet bs_buildWorkbook() writes (same model, same row order and styling rules), drawn as a spreadsheet —
   keep the two in step. Amounts: zero "-", negative in brackets (the workbook's number format). */
function bs_excelPreviewHtml() {
    const m = bs_sheetModel(), n = m.nCols;
    const num = v => {
        if (v === null || v === undefined) return '';
        if (Math.abs(v) < 0.005) return '-';
        const s = Math.abs(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return v < 0 ? `(${s})` : s;
    };
    const out = [];
    let rn = 0;
    const row = (cells, trStyle = '') => { rn++; out.push(`<tr style="${trStyle}"><td class="rn">${rn}</td>${cells}</tr>`); };
    const merged = (text, style = '') => row(`<td colspan="${n}" style="${style}">${bs_esc(text)}</td>`);
    const blank = () => row('<td></td>'.repeat(n));

    m.titleLines.forEach(l => merged(l.text, 'text-align:center;' + (l.bold ? 'font-weight:700;' : '')));
    blank();
    const hd = 'background:#D9E1F2;font-weight:700;border:1px solid #000;';
    row(m.head.map((h, i) => `<td style="${hd}text-align:${i > 0 ? 'right' : 'center'};">${bs_esc(h)}</td>`).join(''));

    m.rows.forEach(r => {
        if (r.major) { merged(r.title, 'background:#EDEDED;font-weight:700;'); return; }
        const kind = bs_rowKind(r);
        const total = kind === 'subtotal' || kind === 'grand';
        const titleStyle = (kind === 'group' ? 'font-weight:700;' : '')
            + (kind === 'subgroup' || kind === 'account' || kind === 'contra' || kind === 'netvalue' ? 'padding-left:16px;' : '');
        const rowStyle = (kind === 'netvalue' ? 'font-style:italic;' : '')
            + (total ? 'font-weight:700;border-top:1px solid #000;' + (kind === 'grand' ? 'border-bottom:3px double #000;' : '') : '');
        const cellStyle = total ? 'font-weight:700;border-top:1px solid #000;' + (kind === 'grand' ? 'border-bottom:3px double #000;' : '') : '';
        row(`<td style="${titleStyle}${total ? cellStyle : ''}">${bs_esc(r.title)}</td>`
            + r.values.map(v => `<td class="n" style="${cellStyle}">${num(v)}</td>`).join(''), rowStyle);
    });

    blank();
    merged(BS_CERT, 'height:48px;vertical-align:top;white-space:normal;');
    blank();
    blank();
    row('<td style="border-top:1px solid #000;text-align:center;">Signature Over Printed Name</td>' + '<td></td>'.repeat(n - 1));
    row('<td style="text-align:center;">Head of Accounting Department</td>' + '<td></td>'.repeat(n - 1));

    const cols = ['<col style="width:34px">', ...m.head.map((_, i) => `<col style="width:${i === 0 ? 380 : 140}px">`)].join('');
    const letters = m.head.map((_, i) => `<th>${String.fromCharCode(65 + i)}</th>`).join('');
    return `<table class="bsx-xl"><colgroup>${cols}</colgroup><thead><tr><th></th>${letters}</tr></thead><tbody>${out.join('')}</tbody></table>`;
}

/* ── Excel workbook ────────────────────────────────────────── */
function bs_buildWorkbook() {
    const { head: headCells, nCols, titleLines, rows } = bs_sheetModel();

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Balance Sheet', {
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
        if (major) { // major section header (colspan)
            const m = ws.addRow([rowTitle]);
            ws.mergeCells(m.number, 1, m.number, nCols);
            m.getCell(1).font = { bold: true };
            m.getCell(1).fill = fill('FFEDEDED');
            return;
        }
        const r = ws.addRow([rowTitle, ...values]);
        for (let i = 2; i <= nCols; i++) r.getCell(i).numFmt = NUM;

        const isGrand = /grand-row/.test(cls), isSub = /subtotal-row/.test(cls);
        if (/group-row/.test(cls) && !/subgroup/.test(cls)) r.getCell(1).font = { bold: true };
        if (/subgroup-row/.test(cls)) r.getCell(1).alignment = { indent: 1 };
        if (/^fims-report-table-row/.test(cls)) r.getCell(1).alignment = { indent: /subgroup/.test(cls) ? 2 : 1 };
        if (/netvalue-row/.test(cls)) r.font = { italic: true };
        if (isSub || isGrand) {
            r.eachCell(c => { c.font = { bold: true }; c.border = { top: thin, bottom: isGrand ? { style: 'double' } : undefined }; });
        }
    });

    ws.addRow([]);
    const cert = ws.addRow([BS_CERT]);
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

/* ── Formatters ────────────────────────────────────────────── */
/* Negative amounts print in parentheses, the usual financial-statement convention. */
function bs_peso(v) {
    const n = Number(v || 0);
    const s = '₱ ' + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < -0.005 ? `<span class="fims-report-table-neg">(${s})</span>` : s;
}
function bs_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function bs_showError(msg) { $bs('bs-error').style.display = 'flex'; $bs('bs-error-msg').textContent = msg; }
function bs_hideError()    { $bs('bs-error').style.display = 'none'; }
</script>
