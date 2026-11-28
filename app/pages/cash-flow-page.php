<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Statement of Cash Flows — one fiscal year's cash inflows/outflows grouped into Operating,
// Investing and Financing Activities, built from every JEV approved and POSTED to
// fims.general_ledger, via GET /cash_flow (CashFlowService.php). Laid out like the printed NHA
// form: Particulars / Note / Current Year / Previous Year, ending with the Cash and Cash
// Equivalents roll-forward (Beginning + Net Increase/(Decrease) = Ending). Read-only.
$_cf_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_cf_api = preg_match('~/api$~', $_cf_api) ? $_cf_api : $_cf_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />


<style>
    #cf-root :is(.nha-mono, .nha-amt, .u-font-mono, .fims-mono, .amt-cell) { font-family: var(--font-body); font-variant-numeric: tabular-nums; } /* plain zero, no dotted-zero glyph */
    /* Table styling (.fims-report-table*) lives in _shared/css/nha-fims.css. Only page-specific, non-table rules remain here. */
    #cf-root .cf-report-title { text-align: center; margin: 0 0 18px; }
    #cf-root .cf-report-title div { font-weight: 700; font-size:var(--fs-md); line-height: 1.6; }
    #cf-root .cf-report-title .cf-report-date { font-weight: 400; }
    #cf-root .cf-sample { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size:var(--fs-base); background: var(--afims-warning-soft); color: var(--afims-warning-text); border: 1px solid var(--afims-warning); }

    #cf-root .nha-hint { padding: 0 16px; margin: 0 0 14px; color: var(--afims-text-muted); }


    html.afims-dark[data-bs-theme="dark"] #cf-root .cf-sample { background: #3A2F10; color: #F5D98A; border-color: #6B5A22; }
    /* Print / Export — one header button → PDF | Excel menu → preview modal (Print / Download on a bar above the preview) */
    #cf-root .cf-menu-item { display: block; width: 100%; text-align: left; padding: 8px 10px; border: 0; border-radius: 6px; background: transparent; cursor: pointer; font-family: inherit; }
    #cf-root .cf-menu-item:hover, #cf-root .cf-menu-item:focus-visible { background: var(--afims-surface-subtle, #F4F7FA); outline: none; }
    #cf-root .cf-menu-item strong { display: block; font-size:var(--fs-base); color: var(--afims-text, #1e293b); }
    #cf-root .cf-menu-item small  { display: block; font-size:var(--fs-xs); color: var(--afims-text-muted, #62657E); }
    #cf-root .cfx-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
    #cf-root .cfx-info { font-size:var(--fs-sm); color: var(--afims-text-muted, #62657E); }
    #cf-root .cfx-actions { display: flex; gap: 8px; }
    #cf-root .cfx-actions [hidden] { display: none !important; }
    #cf-root .cfx-stage { position: relative; height: 68vh; overflow: auto; background: var(--afims-surface-subtle); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; }
    #cf-root .cfx-frame { display: block; width: 100%; height: 100%; border: 0; background: var(--afims-surface-subtle); }
    #cf-root .cfx-msg { padding: 60px 20px; text-align: center; font-size:var(--fs-base); color: var(--afims-text-muted, #62657E); }
    #cf-root .cfx-err { margin-top: 10px; padding: 8px 12px; border-radius: 7px; background: var(--afims-danger-soft); color: var(--afims-danger-text); font-size:var(--fs-base); }
    #cf-root .cfx-msg[hidden], #cf-root .cfx-err[hidden], #cf-root .cfx-frame[hidden], #cf-root .cfx-sheet[hidden] { display: none; }
    /* the Excel preview: the sheet the .xlsx will contain, with a spreadsheet's column letters / row numbers, centred in the stage */
    #cf-root .cfx-sheet { padding: 14px; }
    #cf-root table.cfx-xl { margin: 0 auto; border-collapse: collapse; background: #fff; font: 12px Calibri, Arial, sans-serif; box-shadow: 0 1px 6px rgba(15, 32, 69, .18); }
    #cf-root table.cfx-xl th { padding: 2px 8px; border: 1px solid #d0d3d6; background: #f1f3f4; color: #5f6368; font-weight: 500; text-align: center; }
    #cf-root table.cfx-xl td { height: 20px; padding: 2px 6px; border: 1px solid #e1e3e6; }
    #cf-root table.cfx-xl td.rn { background: #f1f3f4; color: #5f6368; text-align: center; padding: 2px 4px; }
    #cf-root table.cfx-xl td.n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

    @media print {
        #cf-root .cf-letterhead,
        #cf-root .cf-cert { display: block !important; }
        #cf-root .cf-sample { display: none !important; }
    }
</style>

<div id="cf-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m4 14v-8m4 8V9m4 10V7m4 12V3"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Statement of Cashflow</h1>
            <p class="app-page-subtitle">Statement of Cash Flows for the selected fiscal year, by Operating, Investing and Financing Activities
                (Direct Method). A few printed lines (Bayanihan Act BTr refund, Expropriated Account closure, and most project-specific
                disbursement lines) have no dedicated account in the chart of accounts yet and print at 0 rather than being dropped.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="cf-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="cf-export" type="button" class="nha-btn-hdr" title="Print or export the Statement of Cash Flows — PDF or Excel" aria-haspopup="true" aria-expanded="false">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print / Export &#9662;
            </button>
        </div>
    </div>

    <!-- Format menu of the Print / Export button (fixed, so the banner's rounded corners can't clip it) -->
    <div id="cf-export-menu" role="menu" class="no-print" style="display:none;position:fixed;z-index:1060;min-width:230px;background:var(--afims-surface,#fff);border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:6px;">
        <button type="button" role="menuitem" class="cf-menu-item" data-format="pdf"><strong>PDF</strong><small>Preview, then print or download</small></button>
        <button type="button" role="menuitem" class="cf-menu-item" data-format="xlsx"><strong>Excel</strong><small>Preview, then download (.xlsx)</small></button>
    </div>

    <!-- ── PRINT / EXPORT PREVIEW ─────────────────────────── -->
    <div id="cf-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="cf-x-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="cf-x-title">Statement of Cash Flows</h3>
                <button type="button" class="nha-modal-close" id="cf-x-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="cfx-bar">
                    <span class="cfx-info" id="cf-x-info"></span>
                    <span class="cfx-actions">
                        <button type="button" class="nha-btn nha-btn-outline" id="cf-x-print" disabled>Print</button>
                        <button type="button" class="nha-btn nha-btn-primary" id="cf-x-download" disabled>Download</button>
                    </span>
                </div>
                <div class="cfx-stage">
                    <div class="cfx-msg" id="cf-x-msg">Loading preview&hellip;</div>
                    <iframe class="cfx-frame" id="cf-x-frame" title="Statement of Cash Flows PDF preview" hidden></iframe>
                    <div class="cfx-sheet" id="cf-x-sheet" hidden></div>
                </div>
                <div class="cfx-err" id="cf-x-err" role="alert" hidden></div>
            </div>
        </div>
    </div>

    <div id="cf-sample" class="cf-sample no-print">
        <strong>Sample data.</strong> These figures are made up to show the layout. They are not from the General Ledger.
    </div>

    <!-- Error banner -->
    <div id="cf-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="cf-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cf-filter-fy">Fiscal Year</label>
                <select id="cf-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cf-filter-book">Book</label>
                <select id="cf-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="cf-letterhead" style="display:none;border:1px solid #000;padding:8px 10px;margin-bottom:0;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:14%;vertical-align:middle;">
                <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;margin:0 auto;object-fit:contain;">
            </td>
            <td class="cf-report-title" style="width:51%;text-align:center;vertical-align:middle;">
                <div style="font-weight:700;">NATIONAL HOUSING AUTHORITY</div>
                <div style="font-weight:700;">STATEMENTS OF CASH FLOW</div>
                <div id="cf-title-book" style="font-weight:700;">MAIN OFFICE</div>
                <div class="cf-report-date"><span id="cf-report-date">—</span></div>
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
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th>Note</th>
                        <th class="text-end" id="cf-th-cur">Current Year</th>
                        <th class="text-end" id="cf-th-prev">Previous Year</th>
                    </tr>
                </thead>
                <tbody id="cf-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── CERTIFICATION (shown only on print) ───────────── -->
    <div class="cf-cert" style="display:none;margin-top:28px;font-size:9.5pt;">
        <p style="margin:0 0 30px;">I HEREBY CERTIFY that the foregoing Statement of Cash Flows is a true and
            complete statement of the cash receipts and disbursements as shown in the books of accounts, prepared
            and presented in conformity with International Public Sector Accounting Standards (IPSAS).</p>
        <div style="width:260px;text-align:center;">
            <div style="border-top:1px solid #000;padding-top:4px;">Signature Over Printed Name</div>
            <div>Head of Accounting Department</div>
        </div>
    </div>

</div><!-- /#cf-root -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   STATEMENT OF CASH FLOWS   prefix: cf_
═══════════════════════════════════════════════════════════════ */

const CF_BASE     = <?= json_encode($_cf_api) ?>;
const CF_API      = CF_BASE + '/cash_flow';
const CF_FY_API   = CF_BASE + '/fiscal_year/all';
const CF_BOOK_API = CF_BASE + '/books/all';
let cf_books = [];
let cf_useSample = false;

const $cf = id => document.getElementById(id);

function cf_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function cf_boot() {
    await Promise.all([cf_loadFiscalYears(), cf_loadBooks()]);
    cf_bindEvents();
    cf_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cf_boot);
else cf_boot();

function cf_bindEvents() {
    $cf('cf-refresh')?.addEventListener('click', cf_load);
    $cf('cf-filter-fy').addEventListener('change', cf_load);
    $cf('cf-filter-book').addEventListener('change', cf_load);

    // Print / Export: the header button's menu (PDF | Excel) → the preview modal
    $cf('cf-export')?.addEventListener('click', e => { e.stopPropagation(); cf_toggleMenu($cf('cf-export-menu').style.display === 'none'); });
    $cf('cf-export-menu')?.addEventListener('click', e => {
        e.stopPropagation();
        const item = e.target.closest('.cf-menu-item');
        if (!item) return;
        cf_toggleMenu(false);
        cf_openExport(item.dataset.format);
    });
    document.addEventListener('click', () => cf_toggleMenu(false));
    window.addEventListener('scroll', () => cf_toggleMenu(false), true);
    window.addEventListener('resize', () => cf_toggleMenu(false));
    $cf('cf-x-close')?.addEventListener('click', cf_closeExport);
    $cf('cf-overlay')?.addEventListener('click', e => { if (e.target === $cf('cf-overlay')) cf_closeExport(); });
    $cf('cf-x-print')?.addEventListener('click', cf_printPdf);
    $cf('cf-x-download')?.addEventListener('click', cf_downloadFile);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && $cf('cf-overlay').classList.contains('open')) { e.preventDefault(); cf_closeExport(); }
    });
    /* Sample preview has no button: type `cash_flow = true` in the browser console to show
       made-up figures, `cash_flow = false` to go back to live data. */
    Object.defineProperty(window, 'cash_flow', {
        configurable: true,
        get: () => cf_useSample,
        set: v => {
            cf_useSample = !!v;
            $cf('cf-sample').style.display = cf_useSample ? 'block' : 'none';
            cf_load();
        },
    });
}

async function cf_loadFiscalYears() {
    const sel = $cf('cf-filter-fy');
    let years = [];
    try {
        const res  = await cf_authFetch(CF_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        years = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(Boolean);
    } catch (e) {
        cf_showError('Fiscal years: ' + e.message);
    }
    const now = new Date().getFullYear();
    if (!years.length) years = [now];
    years = [...new Set(years)].sort((a, b) => b - a);
    sel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = String(years.includes(now) ? now : years[0]);
}

async function cf_loadBooks() {
    const sel = $cf('cf-filter-book');
    try {
        const res  = await cf_authFetch(CF_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        cf_books = json.data ?? [];
        sel.innerHTML = '<option value="">All Books</option>' +
            cf_books.map(r => `<option value="${cf_esc(r.book_id)}">${cf_esc(r.name)}</option>`).join('');
    } catch (e) {
        cf_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function cf_load() {
    window.PagePreloader?.show();
    cf_hideError();
    const fy   = $cf('cf-filter-fy').value;
    const book = $cf('cf-filter-book').value;
    try {
        let data;
        if (cf_useSample) {
            data = cf_sampleData(Number(fy) || new Date().getFullYear());
        } else {
            const params = new URLSearchParams();
            if (fy) params.set('fiscal_yr', fy);
            if (book) params.set('book_id', book);
            const res  = await cf_authFetch(`${CF_API}?${params}`);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load the Statement of Cash Flows.');
            data = json.data;
        }
        cf_renderTitle(data.fiscal_yr, book);
        cf_render(data);
    } catch (e) {
        cf_render(null, Number(fy) || new Date().getFullYear());
        cf_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function cf_renderTitle(fiscalYr, bookId) {
    const book = cf_books.find(b => String(b.book_id) === String(bookId));
    $cf('cf-title-book').textContent = book ? book.name.toUpperCase() : 'MAIN OFFICE';
    $cf('cf-report-date').textContent = 'For the Year Ended December 31, ' + fiscalYr + ' and ' + (fiscalYr - 1);
}

/* ── Table ─────────────────────────────────────────────────── */
function cf_render(d, fallbackFy) {
    const tbody = $cf('cf-tbody');
    const fy    = d ? d.fiscal_yr : fallbackFy;
    $cf('cf-th-cur').textContent  = String(fy);
    $cf('cf-th-prev').textContent = String(fy - 1);

    if (!d) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet for this fiscal year.</span></div></td></tr>';
        return;
    }

    const html = [];
    const line = l => `<tr class="fims-report-table-row${l.indent ? ' fims-report-table-indent-row' : ''}"><td>${cf_esc(l.label)}</td><td></td>${cf_amt(l.cur)}${cf_amt(l.prev)}</tr>`;
    const subtotal = (label, cur, prev) => `<tr class="fims-report-table-subtotal-row"><td colspan="2">${cf_esc(label)}</td>${cf_amt(cur)}${cf_amt(prev)}</tr>`;
    const grand = (label, cur, prev) => `<tr class="fims-report-table-grand-row fims-report-table-normalcase"><td colspan="2">${cf_esc(label)}</td>${cf_amt(cur)}${cf_amt(prev)}</tr>`;

    const sectionSubtotalLabel = {
        operating: 'Net cash provided by (used in) operating activities',
        investing: 'Net Cash Provided by (Used in) Investing Activities',
        financing: 'Net Cash Provided by (Used in) Financing Activities',
    };

    d.sections.forEach(s => {
        html.push(`<tr class="fims-report-table-major-row"><td colspan="4">${cf_esc(s.label)}</td></tr>`);
        let disbHeaderShown = false;
        s.lines.forEach(l => {
            if (l.indent && !disbHeaderShown) {
                html.push('<tr class="fims-report-table-row"><td colspan="4">Disbursements made for:</td></tr>');
                disbHeaderShown = true;
            }
            html.push(line(l));
        });
        html.push(subtotal(sectionSubtotalLabel[s.key] || ('Net cash — ' + s.label), s.subtotal_cur, s.subtotal_prev));
    });

    const t = d.totals;
    html.push(grand('Net Increase/(Decrease) in Cash and Cash Equivalents', t.net_increase_cur, t.net_increase_prev));
    html.push(`<tr class="fims-report-table-row"><td colspan="2">Add: Cash and Cash Equivalents - Beginning</td>${cf_amt(t.beginning_cur)}${cf_amt(t.beginning_prev)}</tr>`);
    html.push(grand('Cash and Cash Equivalents, Ending', t.ending_cur, t.ending_prev));

    tbody.innerHTML = html.join('');
}

/* ── Sample figures (layout preview only; same shape as GET /cash_flow) ── */
function cf_sampleData(fy) {
    const l = (key, label, cur, prev, indent = false) => ({ key, label, cur, prev, indent });
    const sec = (key, label, lines) => ({
        key, label, lines,
        subtotal_cur: lines.reduce((s, x) => s + x.cur, 0),
        subtotal_prev: lines.reduce((s, x) => s + x.prev, 0),
    });

    const operating = sec('operating', 'CASH FLOWS FROM OPERATING ACTIVITIES', [
        l('subsidy_ng', 'Subsidy from National Government', 5531211983, 5102340112),
        l('trust_fund_borrowed', 'Cash borrowed from (reverted to) trust funds', 12500000, -3200000),
        l('collections_awardees', 'Collections from awardees/beneficiaries', 847392104, 790115223),
        l('other_collections', 'Other collections', 44050438, 43342390),
        l('interest_earned', 'Interest earned on savings/time deposit', 3184512, 2910044),
        l('trust_fund_received', 'Trust Fund received from other agencies', 9800000, 7250000),
        l('cash_donations', 'Cash Donations Received', 0, 150000),
        l('btr_refund_ra11469', 'Refund from BTr re: Remittance in compliance to RA No. 11469 (Bayanihan to Heal as One Act)', 0, 0),
        l('expropriated_closure', 'Proceeds from Closure of Expropriated Account', 0, 0),
        l('cash_paid_suppliers', 'Cash paid to suppliers and employees', -1040110483, -1038551436),
        l('disb_calamity', 'Various calamity projects', 0, 0, true),
        l('disb_resettlement', 'Various resettlement projects', -62400000, -58100000, true),
        l('disb_marawi', 'Rehabilitation of most affected areas in Marawi City', 0, 0, true),
        l('disb_afp_pnp', 'AFP/PNP Housing', 0, 0, true),
        l('disb_relocation', 'Northrail/Southrail/North Triangle Relocation Projects', 0, 0, true),
        l('disb_other_housing', 'Other housing programs/projects', -18750000, -15400000, true),
        l('remittances_trust', 'Remittances to other agencies under Trust Agreement', -9800000, -7250000),
        l('financial_expenses', 'Financial expenses', -129193, -118785),
    ]);
    const investing = sec('investing', 'CASH FLOWS FROM INVESTING ACTIVITIES', [
        l('purchase_ppe', 'Purchase of property and equipment', -29120671, -29069283),
        l('sale_unserviceable', 'Collections on sale of unserviceable property', 145412, 145412),
    ]);
    const financing = sec('financing', 'CASH FLOWS FROM FINANCING ACTIVITIES', [
        l('dividends_paid', 'Dividends paid to the National Government', -5000000, -4500000),
    ]);

    const netCur  = operating.subtotal_cur + investing.subtotal_cur + financing.subtotal_cur;
    const netPrev = operating.subtotal_prev + investing.subtotal_prev + financing.subtotal_prev;
    const beginCur  = 612300000;
    const beginPrev = beginCur - netPrev;

    return {
        fiscal_yr: fy, prev_year: fy - 1, book_id: null,
        sections: [operating, investing, financing],
        totals: {
            net_increase_cur: netCur, net_increase_prev: netPrev,
            beginning_cur: beginCur, beginning_prev: beginPrev,
            ending_cur: beginCur + netCur, ending_prev: beginPrev + netPrev,
        },
    };
}

/* ── Excel / PDF ───────────────────────────────────────────── */
/* Both are built from the table currently on screen. Rows come in three shapes: a full-width row (1 cell: a section header or
   "Disbursements made for:"), a subtotal / grand-total / "Add:" row whose label spans the Note column too (3 cells), and a normal
   line (4 cells). Amounts are parsed back from the displayed text ("1,234.00", "(1,234.00)", "-" for zero) into real numbers. */
function cf_parseAmount(text) {
    const t = String(text || '').trim();
    if (t === '-' || t === '') return 0;
    if (!/\d/.test(t)) return null;
    const n = Number(t.replace(/[^0-9.]/g, ''));
    return /^\(/.test(t) ? -n : n;
}

const CF_CERT = 'I HEREBY CERTIFY that the foregoing Statement of Cash Flows is a true and complete statement of the cash receipts and disbursements as shown in the books of accounts, prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).';

/* The table on screen as data — the ONE source the PDF payload, the Excel preview and the Excel workbook are all made from, so
   the three can't disagree. rows[]: { cls (the row's classes), major (a full-width row), title, note, values ([current, previous]; null = blank) }. */
function cf_sheetModel() {
    const text = el => el.textContent.trim();
    const head = ['Particulars', 'Note', text($cf('cf-th-cur')), text($cf('cf-th-prev'))];
    const titleLines = [
        { text: 'NATIONAL HOUSING AUTHORITY', bold: true },
        { text: 'STATEMENTS OF CASH FLOW', bold: true },
        { text: text($cf('cf-title-book')), bold: true },
        { text: text($cf('cf-report-date')), bold: false },
    ];
    const rows = [...document.querySelectorAll('#cf-tbody tr')].map(tr => {
        const cells = [...tr.cells], n = cells.length;
        return {
            cls: tr.className,
            major: n === 1,
            title: text(cells[0]),
            note: n >= 4 ? text(cells[1]) : '',
            values: n === 1 ? [] : [cf_parseAmount(cells[n - 2].textContent), cf_parseAmount(cells[n - 1].textContent)],
        };
    });
    return { head, nCols: head.length, titleLines: titleLines.filter(l => l.text), rows };
}

/* The row's kind, as the server's print form names it (BalanceSheetPrintService). "Disbursements made for:" is a full-width row that is
   not a section band, so it prints as an ordinary line; the disbursement lines under it are `detail` (one level deeper). */
function cf_rowKind(r) {
    if (/major-row/.test(r.cls)) return 'major';
    if (/grand-row/.test(r.cls)) return /normalcase/.test(r.cls) ? 'total' : 'grand';   // the page prints these in normal case
    if (/subtotal-row/.test(r.cls)) return 'subtotal';
    if (/indent-row/.test(r.cls)) return 'detail';
    return 'account';
}

/* ── Print / Export: menu → preview → Print / Download ─────────────────────────────────────────
   PDF   — the server renders it (POST /print/cf → PrintService 'CF', Dompdf, like the other printed forms) from the table on
           screen; the preview IS that PDF, so Print and Download give exactly what is shown.
   Excel — built here with ExcelJS from the same model (cf_buildWorkbook); the preview draws that sheet. */
const CF_PRINT_API = CF_BASE + '/print/cf';
const cf_x = { format: 'pdf', seq: 0, file: null, url: null };   // file = { blob, filename } shown in the preview

function cf_toggleMenu(open) {
    const menu = $cf('cf-export-menu'), btn = $cf('cf-export');
    if (!menu || !btn) return;
    if (!open) { menu.style.display = 'none'; btn.setAttribute('aria-expanded', 'false'); return; }
    menu.style.display = '';
    const r = btn.getBoundingClientRect(), w = menu.offsetWidth || 230, h = menu.offsetHeight || 90;
    menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menu.style.top  = (r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
    btn.setAttribute('aria-expanded', 'true');
}

/* The file name stem both formats share: Statement-of-Cash-Flows_<year> (the server builds the same one for the PDF). */
function cf_fileStem() { return `Statement-of-Cash-Flows_${$cf('cf-filter-fy').value}`; }

/* There is a loaded table to export (not loading, not the "nothing posted" message). */
function cf_hasData() {
    return !!document.querySelector('#cf-tbody tr') && !document.querySelector('#cf-tbody .nha-empty') && !document.querySelector('#cf-tbody .nha-loader');
}

/* What the server prints: the table on screen. The Note column is always blank on the page, so it is sent as blank. */
function cf_printPayload() {
    const m = cf_sheetModel();
    return {
        fiscal_yr: $cf('cf-filter-fy').value,
        lines: m.titleLines,
        columns: m.head,
        rows: m.rows.map(r => ({ kind: cf_rowKind(r), title: r.title, values: r.major ? [] : [null, ...r.values] })),
    };
}

function cf_xState(msg, err) {
    $cf('cf-x-msg').hidden = !msg;
    if (msg) $cf('cf-x-msg').textContent = msg;
    $cf('cf-x-err').hidden = !err;
    if (err) $cf('cf-x-err').textContent = err;
}
function cf_xReady(on) {
    $cf('cf-x-print').disabled = $cf('cf-x-download').disabled = !on;
}
function cf_releaseFile() {
    if (cf_x.url) URL.revokeObjectURL(cf_x.url);
    cf_x.url = null; cf_x.file = null;
}

async function cf_openExport(format) {
    if (!cf_hasData()) { cf_showError('Nothing to export — no data for this fiscal year.'); return; }
    if (format === 'xlsx' && !window.ExcelJS) { cf_showError('Excel library failed to load. Check your internet connection and reload.'); return; }
    cf_hideError();

    const my = ++cf_x.seq;
    cf_releaseFile();
    cf_x.format = format === 'xlsx' ? 'xlsx' : 'pdf';
    const pdf = cf_x.format === 'pdf';
    const book = $cf('cf-filter-book').selectedOptions?.[0]?.textContent || '';
    $cf('cf-x-title').textContent = 'Statement of Cash Flows — ' + (pdf ? 'PDF' : 'Excel');
    $cf('cf-x-info').textContent = [book, $cf('cf-report-date').textContent].filter(Boolean).join(' · ');
    $cf('cf-x-print').hidden = !pdf;                       // an Excel file is downloaded, not printed
    $cf('cf-x-download').textContent = pdf ? 'Download PDF' : 'Download Excel';
    cf_xReady(false);
    $cf('cf-x-frame').hidden = true; $cf('cf-x-frame').removeAttribute('src');
    $cf('cf-x-sheet').hidden = true;
    cf_xState('Loading preview…', '');
    $cf('cf-overlay').classList.add('open');

    try {
        if (pdf) await cf_loadPdf(my); else await cf_loadExcel(my);
    } catch (e) {
        if (my !== cf_x.seq) return;                       // closed or reopened while loading
        cf_xState('The preview could not be loaded.', e.message);
    }
}

function cf_closeExport() {
    cf_x.seq++;
    $cf('cf-overlay').classList.remove('open');
    $cf('cf-x-frame').hidden = true; $cf('cf-x-frame').removeAttribute('src');
    $cf('cf-x-sheet').innerHTML = '';
    cf_releaseFile();
}

async function cf_loadPdf(my) {
    const res = await cf_authFetch(CF_PRINT_API, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign(cf_printPayload(), { format: 'pdf' })),
    });
    let json = null; try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
    if (my !== cf_x.seq) return;

    const bin = atob(json.data), bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    cf_x.file = { blob: new Blob([bytes], { type: json.mime || 'application/pdf' }), filename: json.filename || (cf_fileStem() + '.pdf') };
    cf_x.url  = URL.createObjectURL(cf_x.file.blob);

    $cf('cf-x-frame').src = cf_x.url + '#toolbar=0&navpanes=0';   // the viewer's own toolbar is hidden: Print / Download are above
    $cf('cf-x-frame').hidden = false;
    cf_xState('', '');
    cf_xReady(true);
}

async function cf_loadExcel(my) {
    const buf = await cf_buildWorkbook().xlsx.writeBuffer();
    if (my !== cf_x.seq) return;
    cf_x.file = { blob: new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename: cf_fileStem() + '.xlsx' };
    $cf('cf-x-sheet').innerHTML = cf_excelPreviewHtml();
    $cf('cf-x-sheet').hidden = false;
    cf_xState('', '');
    cf_xReady(true);
}

/* Print the PDF shown in the preview; if the browser won't print from the frame, open it in a tab (its viewer prints). */
function cf_printPdf() {
    const frame = $cf('cf-x-frame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (e) {
        if (cf_x.url) window.open(cf_x.url, '_blank');
    }
}

function cf_downloadFile() {
    if (!cf_x.file) return;
    const a = document.createElement('a');
    const url = URL.createObjectURL(cf_x.file.blob);
    a.href = url;
    a.download = cf_x.file.filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/* How far the workbook indents a row's label (alignment.indent): disbursement lines 3, other lines 1, subtotals / totals 0. */
function cf_indent(cls) { return /indent-row/.test(cls) ? 3 : (/^fims-report-table-row/.test(cls) ? 1 : 0); }

/* The Excel preview: the sheet cf_buildWorkbook() writes (same model, same row order and styling rules), drawn as a spreadsheet —
   keep the two in step. Amounts: zero "-", negative in brackets (the workbook's number format). */
function cf_excelPreviewHtml() {
    const m = cf_sheetModel(), n = m.nCols;
    const num = v => {
        if (v === null || v === undefined) return '';
        if (Math.abs(v) < 0.005) return '-';
        const s = Math.abs(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return v < 0 ? `(${s})` : s;
    };
    const out = [];
    let rn = 0;
    const row = (cells, trStyle = '') => { rn++; out.push(`<tr style="${trStyle}"><td class="rn">${rn}</td>${cells}</tr>`); };
    const merged = (text, style = '') => row(`<td colspan="${n}" style="${style}">${cf_esc(text)}</td>`);
    const blank = () => row('<td></td>'.repeat(n));

    m.titleLines.forEach(l => merged(l.text, 'text-align:center;' + (l.bold ? 'font-weight:700;' : '')));
    blank();
    const hd = 'background:#D9E1F2;font-weight:700;border:1px solid #000;';
    row(m.head.map((h, i) => `<td style="${hd}text-align:${i > 1 ? 'right' : 'center'};">${cf_esc(h)}</td>`).join(''));

    m.rows.forEach(r => {
        if (r.major) {
            if (/major-row/.test(r.cls)) merged(r.title, 'background:#EDEDED;font-weight:700;');
            else merged(r.title, 'padding-left:' + (cf_indent(r.cls) * 9 + 6) + 'px;');
            return;
        }
        const kind = cf_rowKind(r);
        const total = kind === 'subtotal' || kind === 'grand' || kind === 'total';
        const rule = total ? 'font-weight:700;border-top:1px solid #000;' + (kind !== 'subtotal' ?'border-bottom:3px double #000;' : '') : '';
        row(`<td style="padding-left:${cf_indent(r.cls) * 9 + 6}px;white-space:normal;vertical-align:top;${rule}">${cf_esc(r.title)}</td>`
            + `<td style="${rule}">${cf_esc(r.note)}</td>`
            + r.values.map(v => `<td class="n" style="vertical-align:top;${rule}">${num(v)}</td>`).join(''));
    });

    blank();
    merged(CF_CERT, 'height:48px;vertical-align:top;white-space:normal;');
    blank();
    blank();
    row('<td style="border-top:1px solid #000;text-align:center;">Signature Over Printed Name</td>' + '<td></td>'.repeat(n - 1));
    row('<td style="text-align:center;">Head of Accounting Department</td>' + '<td></td>'.repeat(n - 1));

    const widths = [400, 56, 140, 140];
    const cols = ['<col style="width:34px">', ...m.head.map((_, i) => `<col style="width:${widths[i] || 140}px">`)].join('');
    const letters = m.head.map((_, i) => `<th>${String.fromCharCode(65 + i)}</th>`).join('');
    return `<table class="cfx-xl"><colgroup>${cols}</colgroup><thead><tr><th></th>${letters}</tr></thead><tbody>${out.join('')}</tbody></table>`;
}

/* ── Excel workbook ────────────────────────────────────────── */
function cf_buildWorkbook() {
    const { head: headCells, nCols, titleLines, rows } = cf_sheetModel();

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Cash Flows', {
        pageSetup: { paperSize: 9, orientation: 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });
    ws.columns = [{ width: 70 }, { width: 8 }, { width: 22 }, { width: 22 }];
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
        c.alignment = { horizontal: i > 2 ? 'right' : 'center', wrapText: true };
    });

    rows.forEach(({ cls, major, title: rowTitle, note, values }) => {
        if (major) { // full-width row: section header, or "Disbursements made for:"
            const m = ws.addRow([rowTitle]);
            ws.mergeCells(m.number, 1, m.number, nCols);
            if (/major-row/.test(cls)) {
                m.getCell(1).font = { bold: true };
                m.getCell(1).fill = fill('FFEDEDED');
            } else {
                m.getCell(1).alignment = { indent: 1 };
            }
            return;
        }
        const r = ws.addRow([rowTitle, note, ...values]);
        r.getCell(3).numFmt = NUM;
        r.getCell(4).numFmt = NUM;
        r.getCell(1).alignment = { indent: cf_indent(cls), wrapText: true, vertical: 'top' };
        if (/subtotal-row|grand-row/.test(cls)) {
            r.eachCell(c => { c.font = { bold: true }; c.border = { top: thin, bottom: /grand-row/.test(cls) ? { style: 'double' } : undefined }; });
        }
    });

    ws.addRow([]);
    const cert = ws.addRow([CF_CERT]);
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
function cf_amt(v) {
    return `<td class="text-end nha-mono fims-report-table-num">${cf_peso(v)}</td>`;
}
function cf_peso(v) {
    const n = Number(v || 0);
    if (n === 0) return '-';
    const s = Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < -0.005 ? `<span class="fims-report-table-neg">(${s})</span>` : s;
}
function cf_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function cf_showError(msg) { $cf('cf-error').style.display = 'flex'; $cf('cf-error-msg').textContent = msg; }
function cf_hideError()    { $cf('cf-error').style.display = 'none'; }
</script>
