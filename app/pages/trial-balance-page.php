<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Trial Balance — total debit/credit per account, as of whatever has been posted so far
// (fims.v_trial_balance, over fims.general_ledger), laid out like the standard NGAS/COA Trial
// Balance report: a centered title block, then accounts grouped under a bold major classification
// header (Assets/Liabilities/Equity/Revenue/Expenses) and a bold account-group sub-header (e.g.
// "Cash and Cash Equivalents") — both resolved from chart_of_accounts.parent_account_id, see
// GeneralLedgerService::attachAccountGroups(). Read-only — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8. Separate module from Journals (chronological register),
// General Ledger (per-account ledger card) and Subsidiary Ledger (per-SL-code ledger card).
$_tb_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_tb_api = preg_match('~/api$~', $_tb_api) ? $_tb_api : $_tb_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />


<style>
    /* Table styling (.fims-report-table*) lives in _shared/css/nha-fims.css. Only page-specific, non-table rules remain here. */
    #tb-root .tb-report-title { text-align: center; margin: 0 0 18px; }
    #tb-root .tb-report-title div { font-weight: 700; line-height: 1.6; font-size: 1rem; }
    #tb-root .tb-report-title .tb-report-date { font-weight: 400; }

    /* The rest of the page used 10-13px (shared nha-fims.css), tiny next to the table. Raised here so the page reads as one scale.
       _shared/js/custom.js forces `1em * scale !important` on inputs/selects/buttons, so those get the same direct rem + !important. */
    #tb-root .nha-hdr-tag             { font-size: 0.75rem; }    /* 12px  (was 10) */
    #tb-root .nha-hdr-title           { font-size: 1.25rem; }    /* 20px  (was 17) */
    #tb-root .nha-btn-hdr             { font-size: calc(0.875rem * var(--button-scale, 1)) !important; } /* 14px  Refresh */
    #tb-root .nha-filter-label        { font-size: 0.75rem; }    /* 12px  (was 10) */
    #tb-root .nha-filter-select       { font-size: calc(0.875rem * var(--input-scale, 1)) !important; }  /* 14px  dropdowns (was 13) */
    #tb-root .nha-bdg                 { font-size: 0.8125rem; }  /* 13px  status badge (was 11) */
    /* The filter group is a flex column, which stretched the badge across the whole column — keep it pill-sized,
       lined up with the dropdowns beside it. */
    #tb-root #tb-badge { align-self: flex-start; display: inline-flex; align-items: center; min-height: 34px; padding: 0 14px; margin-top: auto; }
    #tb-root .nha-error               { font-size: 0.875rem; }   /* 14px  (was 13) */
    #tb-root .nha-hint                { font-size: 0.875rem; padding: 0 16px; margin: 0 0 14px; color: var(--afims-text-muted, #62657E); }   /* 14px  (was 13); 16px side padding lines it up with the filter card's content */


    /* Print / Export — one header button → PDF | Excel menu → preview modal (Print / Download on a bar above the preview) */
    #tb-root .tb-menu-item { display: block; width: 100%; text-align: left; padding: 8px 10px; border: 0; border-radius: 6px; background: transparent; cursor: pointer; font-family: inherit; }
    #tb-root .tb-menu-item:hover, #tb-root .tb-menu-item:focus-visible { background: var(--afims-surface-subtle, #F4F7FA); outline: none; }
    #tb-root .tb-menu-item strong { display: block; font-size: 13px; color: var(--afims-text, #1e293b); }
    #tb-root .tb-menu-item small  { display: block; font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
    #tb-root .tbx-bar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
    #tb-root .tbx-info { font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
    #tb-root .tbx-actions { display: flex; gap: 8px; }
    #tb-root .tbx-actions [hidden] { display: none !important; }
    #tb-root .tbx-stage { position: relative; height: 68vh; overflow: auto; background: #E8ECF2; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; }
    #tb-root .tbx-frame { display: block; width: 100%; height: 100%; border: 0; background: #E8ECF2; }
    #tb-root .tbx-msg { padding: 60px 20px; text-align: center; font-size: 13px; color: var(--afims-text-muted, #62657E); }
    #tb-root .tbx-err { margin-top: 10px; padding: 8px 12px; border-radius: 7px; background: #FDECEC; color: #B42318; font-size: 13px; }
    #tb-root .tbx-msg[hidden], #tb-root .tbx-err[hidden], #tb-root .tbx-frame[hidden], #tb-root .tbx-sheet[hidden] { display: none; }
    /* the Excel preview: the sheet the .xlsx will contain, with a spreadsheet's column letters / row numbers */
    #tb-root .tbx-sheet { padding: 14px; }
    #tb-root table.tbx-xl { margin: 0 auto; border-collapse: collapse; background: #fff; min-width: 760px; font: 12px Calibri, Arial, sans-serif; box-shadow: 0 1px 6px rgba(15, 32, 69, .18); }
    #tb-root table.tbx-xl th { padding: 2px 8px; border: 1px solid #d0d3d6; background: #f1f3f4; color: #5f6368; font-weight: 500; text-align: center; }
    #tb-root table.tbx-xl td { height: 20px; padding: 2px 6px; border: 1px solid #e1e3e6; }
    #tb-root table.tbx-xl td.rn { width: 34px; background: #f1f3f4; color: #5f6368; text-align: center; padding: 2px 4px; }
    #tb-root table.tbx-xl td.n { text-align: right; font-variant-numeric: tabular-nums; }

    @media print {
        #tb-root .tb-letterhead,
        #tb-root .tb-cert { display: block !important; }
    }
</style>

<div id="tb-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Trial Balance</h1>
            <p class="app-page-subtitle">The total debit and credit posted to every account, grouped under its major classification
                (Assets, Liabilities, Equity, Revenue, Expenses) — a snapshot of all General Ledger balances as of today.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="tb-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="tb-export" type="button" class="nha-btn-hdr" title="Print or export the Trial Balance — PDF or Excel" aria-haspopup="true" aria-expanded="false">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print / Export &#9662;
            </button>
        </div>
    </div>

    <!-- Format menu of the Print / Export button (fixed, so the banner's rounded corners can't clip it) -->
    <div id="tb-export-menu" role="menu" class="no-print" style="display:none;position:fixed;z-index:1060;min-width:230px;background:var(--afims-surface,#fff);border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:6px;">
        <button type="button" role="menuitem" class="tb-menu-item" data-format="pdf"><strong>PDF</strong><small>Preview, then print or download</small></button>
        <button type="button" role="menuitem" class="tb-menu-item" data-format="xlsx"><strong>Excel</strong><small>Preview, then download (.xlsx)</small></button>
    </div>

    <!-- ── PRINT / EXPORT PREVIEW ─────────────────────────── -->
    <div id="tb-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="tb-x-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="tb-x-title">Trial Balance</h3>
                <button type="button" class="nha-modal-close" id="tb-x-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="tbx-bar">
                    <span class="tbx-info" id="tb-x-info"></span>
                    <span class="tbx-actions">
                        <button type="button" class="nha-btn nha-btn-outline" id="tb-x-print" disabled>Print</button>
                        <button type="button" class="nha-btn nha-btn-primary" id="tb-x-download" disabled>Download</button>
                    </span>
                </div>
                <div class="tbx-stage">
                    <div class="tbx-msg" id="tb-x-msg">Loading preview&hellip;</div>
                    <iframe class="tbx-frame" id="tb-x-frame" title="Trial Balance PDF preview" hidden></iframe>
                    <div class="tbx-sheet" id="tb-x-sheet" hidden></div>
                </div>
                <div class="tbx-err" id="tb-x-err" role="alert" hidden></div>
            </div>
        </div>
    </div>

    <!-- Error banner -->
    <div id="tb-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="tb-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="tb-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book</label>
                <select id="tb-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <span id="tb-badge" class="nha-bdg nha-bdg-gray">—</span>
            </div>
        </div>
    </div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="tb-letterhead" style="display:none;border:1px solid #000;padding:8px 10px;margin-bottom:0;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:14%;vertical-align:middle;">
                <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;margin:0 auto;object-fit:contain;">
            </td>
            <td class="tb-report-title" style="width:51%;text-align:center;vertical-align:middle;">
                <div style="font-weight:700;">NATIONAL HOUSING AUTHORITY</div>
                <div style="font-weight:700;">TRIAL BALANCE<span id="tb-report-book"></span></div>
                <div style="font-weight:700;">MAIN OFFICE</div>
                <div class="tb-report-date"><span id="tb-report-date">—</span></div>
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
                        <th>Accounts</th>
                        <th>Acct. Code</th>
                        <th class="text-end">Debit Balances</th>
                        <th class="text-end">Credit Balances</th>
                    </tr>
                </thead>
                <tbody id="tb-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
                <tfoot>
                    <tr class="fims-report-table-total-row">
                        <td colspan="2">Total</td>
                        <td class="text-end nha-mono fims-report-table-num" id="tb-total-debit">₱ 0.00</td>
                        <td class="text-end nha-mono fims-report-table-num" id="tb-total-credit">₱ 0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ── CERTIFICATION (shown only on print) ───────────── -->
    <div class="tb-cert" style="display:none;margin-top:28px;font-size:9.5pt;">
        <p style="margin:0 0 30px;">I HEREBY CERTIFY that the trial balance is a true and complete statement of all
            accounts as shown in the ledger prepared and presented in conformity with International Public Sector
            Accounting Standards (IPSAS).</p>
        <div style="width:260px;text-align:center;">
            <div style="border-top:1px solid #000;padding-top:4px;">Signature Over Printed Name</div>
            <div>Head of Accounting Department</div>
        </div>
    </div>

</div><!-- /#tb-root -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   TRIAL BALANCE   prefix: tb_
═══════════════════════════════════════════════════════════════ */

const TB_BASE = <?= json_encode($_tb_api) ?>;
const TB_API  = TB_BASE + '/general_ledger';
const TB_FY_API = TB_BASE + '/fiscal_year/all';
const TB_BOOK_API = TB_BASE + '/books/all';
let tb_books = []; // [{book_id, name}] — kept around to label the report title

const $tb = id => document.getElementById(id);

function tb_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function tb_boot() {
    await Promise.all([tb_loadFiscalYears(), tb_loadBooks()]);
    tb_bindEvents();
    tb_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tb_boot);
else tb_boot();

function tb_bindEvents() {
    $tb('tb-refresh')?.addEventListener('click', tb_load);
    $tb('tb-filter-fy')?.addEventListener('change', tb_load);
    $tb('tb-filter-book')?.addEventListener('change', tb_load);

    // Print / Export: the header button's menu (PDF | Excel) → the preview modal
    $tb('tb-export')?.addEventListener('click', e => { e.stopPropagation(); tb_toggleMenu($tb('tb-export-menu').style.display === 'none'); });
    $tb('tb-export-menu')?.addEventListener('click', e => {
        e.stopPropagation();
        const item = e.target.closest('.tb-menu-item');
        if (!item) return;
        tb_toggleMenu(false);
        tb_openExport(item.dataset.format);
    });
    document.addEventListener('click', () => tb_toggleMenu(false));
    window.addEventListener('scroll', () => tb_toggleMenu(false), true);
    window.addEventListener('resize', () => tb_toggleMenu(false));
    $tb('tb-x-close')?.addEventListener('click', tb_closeExport);
    $tb('tb-overlay')?.addEventListener('click', e => { if (e.target === $tb('tb-overlay')) tb_closeExport(); });
    $tb('tb-x-print')?.addEventListener('click', tb_printPdf);
    $tb('tb-x-download')?.addEventListener('click', tb_downloadFile);
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && $tb('tb-overlay').classList.contains('open')) { e.preventDefault(); tb_closeExport(); }
    });
}

/* Fiscal years come from fims.fiscal_year via GET /fiscal_year/all → {success, data:[{fiscal_yr}]}. */
async function tb_loadFiscalYears() {
    const sel = $tb('tb-filter-fy');
    try {
        const res  = await tb_authFetch(TB_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${tb_esc(r.fiscal_yr)}">${tb_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        tb_showError('Fiscal years: ' + e.message);
    }
}

/* Books come from fims.books via GET /books/all → {success, data:[{book_id, code, name}]}. */
async function tb_loadBooks() {
    const sel = $tb('tb-filter-book');
    try {
        const res  = await tb_authFetch(TB_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        tb_books = json.data ?? [];
        sel.innerHTML = '<option value="">All Books</option>' +
            tb_books.map(r => `<option value="${tb_esc(r.book_id)}">${tb_esc(r.name)}</option>`).join('');
    } catch (e) {
        tb_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function tb_load() {
    window.PagePreloader?.show();
    tb_hideError();
    try {
        const fy   = $tb('tb-filter-fy').value;
        const book = $tb('tb-filter-book').value;
        const params = new URLSearchParams();
        if (fy) params.set('fiscal_yr', fy);
        if (book) params.set('book_id', book);

        const res  = await tb_authFetch(`${TB_API}/trial-balance?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Trial Balance.');

        tb_renderDate(fy);
        tb_renderBookLabel(book);
        tb_render(json);
    } catch (e) {
        tb_render({ data: [], total_debit: 0, total_credit: 0, balanced: true });
        tb_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* "As of December 31, {fiscal_yr}" when one year is picked; otherwise today's date (an all-years
   trial balance is a running snapshot, not tied to one fiscal year-end). */
function tb_renderDate(fiscalYr) {
    const d = fiscalYr ? new Date(Number(fiscalYr), 11, 31) : new Date();
    $tb('tb-report-date').textContent = d.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
}

/* "TRIAL BALANCE — CISFA" when one book is picked; plain "TRIAL BALANCE" for all books combined. */
function tb_renderBookLabel(bookId) {
    const book = tb_books.find(b => String(b.book_id) === String(bookId));
    $tb('tb-report-book').textContent = book ? ` — ${book.name}` : '';
}

/* ── Table ─────────────────────────────────────────────────── */
let tb_last = { data: [], total_debit: 0, total_credit: 0 }; // last rendered result, reused by the Excel export

function tb_render(json) {
    tb_last = json;
    const rows  = json.data ?? [];
    const tbody = $tb('tb-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
    } else {
        let lastMajor = null, lastGroup = null;
        tbody.innerHTML = rows.map(r => {
            let header = '';
            if (r.major_group !== lastMajor) {
                header += `<tr class="fims-report-table-major-row"><td colspan="4">${tb_esc(r.major_group)}</td></tr>`;
                lastGroup = null; // a new major group always restarts its own sub-groups
            }
            if (r.account_group && r.account_group !== lastGroup) {
                // Assets keep the filled sub-header (e.g. "Cash and Cash Equivalents"). Every other class —
                // Liabilities especially — lists its group (e.g. "Financial Liabilities" 2-01, "Inter-Agency
                // Payables" 2-02) as a plain row at the same level as the accounts, like the NHA reference.
                if (/^assets$/i.test(String(r.major_group).trim())) {
                    header += `<tr class="fims-report-table-group-row"><td colspan="4">${tb_esc(r.account_group)}</td></tr>`;
                } else {
                    header += `<tr class="fims-report-table-row">
                        <td>${tb_esc(r.account_group)}</td>
                        <td class="nha-mono fims-report-table-num">${tb_esc(r.account_group_code)}</td>
                        <td></td><td></td>
                    </tr>`;
                }
            }
            lastMajor = r.major_group;
            lastGroup = r.account_group;

            const contra = /^(Accumulated Depreciation|Allowance for Impairment)/i.test(String(r.account_title || ''));
            return header + `<tr class="fims-report-table-row${contra ? ' fims-report-table-contra-row' : ''}">
                <td>${tb_esc(r.account_title) || '—'}</td>
                <td class="nha-mono fims-report-table-num">${tb_esc(r.account_code)}</td>
                <td class="text-end nha-mono fims-report-table-num">${tb_pesoOrDash(r.total_debit)}</td>
                <td class="text-end nha-mono fims-report-table-num">${tb_pesoOrDash(r.total_credit)}</td>
            </tr>`;
        }).join('');
    }
    $tb('tb-total-debit').textContent  = tb_peso(json.total_debit);
    $tb('tb-total-credit').textContent = tb_peso(json.total_credit);

    const badge = $tb('tb-badge');
    if (json.balanced) {
        badge.className = 'nha-bdg nha-bdg-green';
        badge.textContent = 'Balanced';
    } else {
        badge.className = 'nha-bdg nha-bdg-red';
        badge.textContent = 'Out of balance';
    }
}

/* ── Print / Export: menu → preview → Print / Download ─────────────────────────────────────────
   PDF   — the server renders it (POST /print/tb → PrintService 'TB', Dompdf, like the other printed forms); the preview IS that
           PDF, so Print and Download give exactly what is shown.
   Excel — built here with ExcelJS from the rows on screen (tb_buildWorkbook); the preview imitates that sheet. */
const TB_PRINT_API = TB_BASE + '/print/tb';
const tb_x = { format: 'pdf', seq: 0, file: null, url: null };   // file = { blob, filename } shown in the preview

function tb_toggleMenu(open) {
    const menu = $tb('tb-export-menu'), btn = $tb('tb-export');
    if (!menu || !btn) return;
    if (!open) { menu.style.display = 'none'; btn.setAttribute('aria-expanded', 'false'); return; }
    menu.style.display = '';
    const r = btn.getBoundingClientRect(), w = menu.offsetWidth || 230, h = menu.offsetHeight || 90;
    menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menu.style.top  = (r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
    btn.setAttribute('aria-expanded', 'true');
}

/* The file name stem both formats share: Trial-Balance_<year | all-years> (the server builds the same one for the PDF). */
function tb_fileStem() { return 'Trial-Balance_' + ($tb('tb-filter-fy').value || 'all-years'); }

/* What the server prints: the rows the page loaded + the title block's labels. */
function tb_printPayload() {
    const book = tb_books.find(b => String(b.book_id) === String($tb('tb-filter-book').value));
    return {
        fiscal_yr: $tb('tb-filter-fy').value,
        as_of: $tb('tb-report-date').textContent,
        book_name: book ? book.name : '',
        rows: (tb_last.data ?? []).map(r => ({
            major_group: r.major_group, account_group: r.account_group, account_group_code: r.account_group_code,
            account_title: r.account_title, account_code: r.account_code, total_debit: r.total_debit, total_credit: r.total_credit,
        })),
    };
}

function tb_xState(msg, err) {
    $tb('tb-x-msg').hidden = !msg;
    if (msg) $tb('tb-x-msg').textContent = msg;
    $tb('tb-x-err').hidden = !err;
    if (err) $tb('tb-x-err').textContent = err;
}
function tb_xReady(on) {
    $tb('tb-x-print').disabled = $tb('tb-x-download').disabled = !on;
}
function tb_releaseFile() {
    if (tb_x.url) URL.revokeObjectURL(tb_x.url);
    tb_x.url = null; tb_x.file = null;
}

async function tb_openExport(format) {
    if (!(tb_last.data ?? []).length) { tb_showError('Nothing to export — no postings yet.'); return; }
    if (format === 'xlsx' && !window.ExcelJS) { tb_showError('Excel library failed to load. Check your internet connection and reload.'); return; }
    tb_hideError();

    const my = ++tb_x.seq;
    tb_releaseFile();
    tb_x.format = format === 'xlsx' ? 'xlsx' : 'pdf';
    const pdf = tb_x.format === 'pdf';
    $tb('tb-x-title').textContent = 'Trial Balance — ' + (pdf ? 'PDF' : 'Excel');
    $tb('tb-x-info').textContent = $tb('tb-report-date').textContent + ($tb('tb-report-book').textContent || '');
    $tb('tb-x-print').hidden = !pdf;                       // an Excel file is downloaded, not printed
    $tb('tb-x-download').textContent = pdf ? 'Download PDF' : 'Download Excel';
    tb_xReady(false);
    $tb('tb-x-frame').hidden = true; $tb('tb-x-frame').removeAttribute('src');
    $tb('tb-x-sheet').hidden = true;
    tb_xState('Loading preview…', '');
    $tb('tb-overlay').classList.add('open');

    try {
        if (pdf) await tb_loadPdf(my); else await tb_loadExcel(my);
    } catch (e) {
        if (my !== tb_x.seq) return;                       // closed or reopened while loading
        tb_xState('The preview could not be loaded.', e.message);
    }
}

function tb_closeExport() {
    tb_x.seq++;
    $tb('tb-overlay').classList.remove('open');
    $tb('tb-x-frame').hidden = true; $tb('tb-x-frame').removeAttribute('src');
    $tb('tb-x-sheet').innerHTML = '';
    tb_releaseFile();
}

async function tb_loadPdf(my) {
    const res  = await tb_authFetch(TB_PRINT_API, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(Object.assign(tb_printPayload(), { format: 'pdf' })),
    });
    let json = null; try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
    if (my !== tb_x.seq) return;

    const bin = atob(json.data), bytes = new Uint8Array(bin.length);
    for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
    tb_x.file = { blob: new Blob([bytes], { type: json.mime || 'application/pdf' }), filename: json.filename || (tb_fileStem() + '.pdf') };
    tb_x.url  = URL.createObjectURL(tb_x.file.blob);

    $tb('tb-x-frame').src = tb_x.url + '#toolbar=0&navpanes=0';   // the viewer's own toolbar is hidden: Print / Download are above
    $tb('tb-x-frame').hidden = false;
    tb_xState('', '');
    tb_xReady(true);
}

async function tb_loadExcel(my) {
    const buf = await tb_buildWorkbook().xlsx.writeBuffer();
    if (my !== tb_x.seq) return;
    tb_x.file = { blob: new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }), filename: tb_fileStem() + '.xlsx' };
    $tb('tb-x-sheet').innerHTML = tb_excelPreviewHtml();
    $tb('tb-x-sheet').hidden = false;
    tb_xState('', '');
    tb_xReady(true);
}

/* Print the PDF shown in the preview; if the browser won't print from the frame, open it in a tab (its viewer prints). */
function tb_printPdf() {
    const frame = $tb('tb-x-frame');
    try {
        frame.contentWindow.focus();
        frame.contentWindow.print();
    } catch (e) {
        if (tb_x.url) window.open(tb_x.url, '_blank');
    }
}

function tb_downloadFile() {
    if (!tb_x.file) return;
    const a = document.createElement('a');
    const url = URL.createObjectURL(tb_x.file.blob);
    a.href = url;
    a.download = tb_x.file.filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/* The Excel preview: the sheet tb_buildWorkbook() writes (same rows, same order), drawn as a spreadsheet — keep the two in step. */
function tb_excelPreviewHtml() {
    const rows = tb_last.data ?? [];
    const num = v => {
        v = Number(v || 0);
        if (Math.abs(v) < 0.005) return '-';
        const s = Math.abs(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return v < 0 ? `(${s})` : s;
    };
    const out = [];
    let n = 0;
    const row = (cells, trStyle = '') => { n++; out.push(`<tr style="${trStyle}"><td class="rn">${n}</td>${cells}</tr>`); };
    const merged = (text, style = '') => row(`<td colspan="4" style="${style}">${tb_esc(text)}</td>`);

    merged('NATIONAL HOUSING AUTHORITY', 'text-align:center;font-weight:700;');
    merged($tb('tb-report-book').textContent ? 'TRIAL BALANCE' + $tb('tb-report-book').textContent : 'TRIAL BALANCE', 'text-align:center;font-weight:700;');
    merged('MAIN OFFICE', 'text-align:center;font-weight:700;');
    merged($tb('tb-report-date').textContent, 'text-align:center;');
    row('<td></td><td></td><td></td><td></td>');
    const hd = 'background:#D9E1F2;font-weight:700;border:1px solid #000;';
    row(`<td style="${hd}text-align:center;">Accounts</td><td style="${hd}text-align:center;">Acct. Code</td><td style="${hd}text-align:right;">Debit Balances</td><td style="${hd}text-align:right;">Credit Balances</td>`);

    let lastMajor = null, lastGroup = null;
    rows.forEach(r => {
        if (r.major_group !== lastMajor) {
            merged(r.major_group, 'background:#EDEDED;font-weight:700;');
            lastGroup = null;
        }
        if (r.account_group && r.account_group !== lastGroup) {
            if (/^assets$/i.test(String(r.major_group).trim())) merged(r.account_group, 'font-weight:700;');
            else row(`<td>${tb_esc(r.account_group)}</td><td>${tb_esc(r.account_group_code ?? '')}</td><td></td><td></td>`);
        }
        lastMajor = r.major_group;
        lastGroup = r.account_group;
        row(`<td style="padding-left:16px;">${tb_esc(r.account_title) || '—'}</td><td>${tb_esc(r.account_code ?? '')}</td><td class="n">${num(r.total_debit)}</td><td class="n">${num(r.total_credit)}</td>`);
    });

    const tot = 'font-weight:700;border-top:1px solid #000;border-bottom:3px double #000;';
    row(`<td colspan="2" style="${tot}">Total</td><td class="n" style="${tot}">${num(tb_last.total_debit)}</td><td class="n" style="${tot}">${num(tb_last.total_credit)}</td>`);
    row('<td></td><td></td><td></td><td></td>');
    merged('I HEREBY CERTIFY that the trial balance is a true and complete statement of all accounts as shown in the ledger prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).', 'height:48px;vertical-align:top;white-space:normal;');
    row('<td></td><td></td><td></td><td></td>');
    row('<td></td><td></td><td></td><td></td>');
    row('<td style="border-top:1px solid #000;text-align:center;">Signature Over Printed Name</td><td></td><td></td><td></td>');
    row('<td style="text-align:center;">Head of Accounting Department</td><td></td><td></td><td></td>');

    return `<table class="tbx-xl"><colgroup><col style="width:34px"><col style="width:380px"><col style="width:120px"><col style="width:140px"><col style="width:140px"></colgroup>`
        + `<thead><tr><th></th><th>A</th><th>B</th><th>C</th><th>D</th></tr></thead><tbody>${out.join('')}</tbody></table>`;
}

/* ── Excel workbook ────────────────────────────────────────── */
/* Mirrors the on-screen/print layout: title block, then Major group → account group → accounts, then Total + certification. */
function tb_buildWorkbook() {
    const rows = tb_last.data ?? [];

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Trial Balance', {
        pageSetup: { paperSize: 9, orientation: 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });
    ws.columns = [{ width: 58 }, { width: 18 }, { width: 22 }, { width: 22 }];
    const NUM = '#,##0.00;(#,##0.00);"-"';
    const thin = { style: 'thin' };

    const title = (text, bold = true) => {
        const r = ws.addRow([text]);
        ws.mergeCells(r.number, 1, r.number, 4);
        r.getCell(1).alignment = { horizontal: 'center' };
        r.getCell(1).font = { bold, size: 11 };
    };
    title('NATIONAL HOUSING AUTHORITY');
    title($tb('tb-report-book').textContent ? 'TRIAL BALANCE' + $tb('tb-report-book').textContent : 'TRIAL BALANCE');
    title('MAIN OFFICE');
    title($tb('tb-report-date').textContent, false);
    ws.addRow([]);

    const head = ws.addRow(['Accounts', 'Acct. Code', 'Debit Balances', 'Credit Balances']);
    head.eachCell((c, i) => {
        c.font = { bold: true };
        c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } };
        c.border = { top: thin, bottom: thin, left: thin, right: thin };
        c.alignment = { horizontal: i > 2 ? 'right' : 'center' };
    });

    let lastMajor = null, lastGroup = null;
    rows.forEach(r => {
        if (r.major_group !== lastMajor) {
            const m = ws.addRow([r.major_group]);
            ws.mergeCells(m.number, 1, m.number, 4);
            m.getCell(1).font = { bold: true };
            m.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEDEDED' } };
            lastGroup = null;
        }
        if (r.account_group && r.account_group !== lastGroup) {
            if (/^assets$/i.test(String(r.major_group).trim())) {
                const g = ws.addRow([r.account_group]);
                ws.mergeCells(g.number, 1, g.number, 4);
                g.getCell(1).font = { bold: true };
            } else {
                ws.addRow([r.account_group, r.account_group_code ?? '']);
            }
        }
        lastMajor = r.major_group;
        lastGroup = r.account_group;

        const row = ws.addRow([r.account_title || '—', r.account_code ?? '', Number(r.total_debit || 0), Number(r.total_credit || 0)]);
        row.getCell(1).alignment = { indent: 1 };
        row.getCell(3).numFmt = NUM;
        row.getCell(4).numFmt = NUM;
    });

    const tot = ws.addRow(['Total', '', Number(tb_last.total_debit || 0), Number(tb_last.total_credit || 0)]);
    ws.mergeCells(tot.number, 1, tot.number, 2);
    tot.eachCell(c => { c.font = { bold: true }; c.border = { top: thin, bottom: { style: 'double' } }; });
    tot.getCell(3).numFmt = NUM;
    tot.getCell(4).numFmt = NUM;

    ws.addRow([]);
    const cert = ws.addRow(['I HEREBY CERTIFY that the trial balance is a true and complete statement of all accounts as shown in the ledger prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).']);
    ws.mergeCells(cert.number, 1, cert.number, 4);
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
function tb_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
/* An empty side of an account (balance method: one side per account) shows "-" instead of ₱ 0.00. */
function tb_pesoOrDash(v) {
    return Math.abs(Number(v || 0)) < 0.005 ? '-' : tb_peso(v);
}
function tb_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function tb_showError(msg) { $tb('tb-error').style.display = 'flex'; $tb('tb-error-msg').textContent = msg; }
function tb_hideError()    { $tb('tb-error').style.display = 'none'; }
</script>
