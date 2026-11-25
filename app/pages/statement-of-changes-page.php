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

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css') ?>" />

<style>
    /* Table styling (.fims-report-table*) lives in app/fims-css/fims.css. Only page-specific, non-table rules remain here. */
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

    /* the page header's Refresh button sits on the right of the banner, above its decorative circles (fims.css has no rule for it) */
    #sc-root .app-page-header { flex-wrap: wrap; }
    #sc-root .app-page-header-actions { position: relative; z-index: 1; margin-left: auto; display: flex; flex-wrap: wrap; gap: 8px; }

    html.afims-dark[data-bs-theme="dark"] #sc-root .sc-sample { background: #3A2F10; color: #F5D98A; border-color: #6B5A22; }
    @media print {
        #sc-root .sc-letterhead,
        #sc-root .sc-cert { display: block !important; }
        #sc-root .sc-sample { display: none !important; }
    }
</style>

<div id="sc-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access: app-page-header (styled by app/fims-css/fims.css, already loaded above) -->
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
            <button id="sc-print" type="button" class="nha-btn-hdr" title="Print">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print
            </button>
            <button id="sc-excel" type="button" class="nha-btn-hdr" title="Export to Excel">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                Excel
            </button>
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

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap fims-report-table-wrap">
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

function sc_bindEvents() {
    $sc('sc-refresh')?.addEventListener('click', sc_load);
    $sc('sc-print')?.addEventListener('click', () => window.print());
    $sc('sc-excel')?.addEventListener('click', sc_exportExcel);
    $sc('sc-filter-fy')?.addEventListener('change', sc_load);
    $sc('sc-filter-book')?.addEventListener('change', sc_load);

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

function sc_sampleData(fy) {
    return {
        fiscal_yr: fy,
        opening_balance: { total: 100000000 },
        surplus_deficit: 5000000,
        adjustments: 0,
    };
}

/* ── Excel export ──────────────────────────────────────────── */
/* Built from the table currently on screen (so it matches the picked Book / Consolidated view). Amounts are parsed
   back from the displayed text into real numbers; "—" (no NHA figure) becomes an empty cell. The page's Prior Year
   column is never filled in, so it is left out of the workbook. */
function sc_parseAmount(text) {
    const t = String(text || '').trim();
    if (!/\d/.test(t)) return null;
    const n = Number(t.replace(/[^0-9.]/g, ''));
    return /^[(-]/.test(t) ? -n : n;
}

async function sc_exportExcel() {
    if (!window.ExcelJS) { sc_showError('Excel library failed to load. Check your internet connection and reload.'); return; }
    const trs = [...document.querySelectorAll('#sc-tbody tr')];
    if (!trs.length || document.querySelector('#sc-tbody .nha-loader') || trs.some(tr => tr.cells.length === 1 && !tr.className)) {
        sc_showError('Nothing to export — no data loaded.');
        return;
    }
    const nCols = 3; // Description, Consolidated/Book, NHA (Main Office)
    const headCells = [...document.querySelectorAll('#sc-thead th')].slice(0, nCols).map(th => th.textContent.trim());

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
    title('NATIONAL HOUSING AUTHORITY');
    title($sc('sc-title-line2').textContent);
    if ($sc('sc-title-line3').style.display !== 'none') title($sc('sc-title-line3').textContent);
    title($sc('sc-report-date').textContent, false);
    if ($sc('sc-title-peso').style.display !== 'none') title($sc('sc-title-peso').textContent, false);
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
        const label = cells[0].textContent.trim();
        if (cells.length === 1) { // section header (colspan)
            const m = ws.addRow([label]);
            ws.mergeCells(m.number, 1, m.number, nCols);
            m.getCell(1).font = { bold: true };
            m.getCell(1).fill = fill('FFEDEDED');
            return;
        }
        const r = ws.addRow([label, sc_parseAmount(cells[1].textContent), sc_parseAmount(cells[2].textContent)]);
        r.getCell(2).numFmt = NUM;
        r.getCell(3).numFmt = NUM;
        r.getCell(1).alignment = { indent: 1, wrapText: true };
        if (/subtotal-row|grand-row/.test(cls)) {
            r.eachCell(c => { c.font = { bold: true }; c.border = { top: thin, bottom: /grand-row/.test(cls) ? { style: 'double' } : undefined }; });
        }
    });

    ws.addRow([]);
    const cert = ws.addRow(['I HEREBY CERTIFY that the foregoing Statement of Changes in Net Assets/Equity is a true and complete statement of the changes in the net assets/equity as shown in the books of accounts, prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).']);
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
    a.download = `Statement-of-Changes_${$sc('sc-filter-fy').value}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
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
