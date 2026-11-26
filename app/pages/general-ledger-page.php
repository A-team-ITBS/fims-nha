<?php
namespace PHPMaker2024\FIMS;
@session_start();

// General Ledger — pick ONE chart-of-accounts account, see every posting to it (fims.general_ledger,
// via fims.v_journals) in order with a running balance. Read-only — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8. Separate module from Subsidiary Ledger (same idea, keyed by SL
// code instead), Journals (chronological, every account) and Trial Balance (totals, no detail).
//
// Column layout follows the illustrated "DISPLAY IN FIMS" format: account header carries Normal
// Balance/Account Class, each row has its JEV No. and DV Number alongside Debit/Credit/running
// Balance, and the footer reads "Ending Balance as of [date]". (The Doc Ref No. column was removed
// from the list and the SL popup; the ledger API still returns it, and the printed sheets use it.)
// (The Entry Side + single Amount convention in that same illustration is the raw JEV-listing/
// back-end representation — that's Journals (GJ), not this page.)
$_gl_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_gl_api = preg_match('~/api$~', $_gl_api) ? $_gl_api : $_gl_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #gl-root .gl-combo-wrap { position: relative; }
    #gl-root .gl-combo-results {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20;
        background: #fff; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,.08); max-height: 260px; overflow-y: auto; display: none;
    }
    #gl-root .gl-combo-item { padding: 8px 12px; cursor: pointer; font-size: 13px; }
    #gl-root .gl-combo-item:hover { background: var(--afims-bg-soft, #F5F6FA); }
    #gl-root .gl-combo-empty { padding: 8px 12px; font-size: 13px; color: var(--afims-text-muted,#62657E); }
    #gl-root .gl-summary-cards { display: flex; gap: 12px; flex-wrap: wrap; margin: 0 0 14px; }
    #gl-root .gl-summary-card {
        flex: 1 1 160px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 10px;
        padding: 12px 14px; background: #fff;
    }
    #gl-root .gl-summary-label { font-size: 11px; color: var(--afims-text-muted,#62657E); text-transform: uppercase; letter-spacing: .03em; }
    #gl-root .gl-summary-value { font-size: 18px; font-weight: 600; margin-top: 4px; }
    #gl-root .gl-group-row td { background: var(--afims-bg-soft, #F5F6FA); font-weight: 600; }
    #gl-root .gl-ending-balance { text-align: right; padding: 8px 4px; font-size: 13px; color: var(--afims-text-muted,#62657E); }
    #gl-root .gl-ending-balance strong { color: inherit; font-weight: 700; }
    #gl-root .gl-group-row.gl-clickable { cursor: pointer; }
    #gl-root .gl-group-row.gl-clickable:hover td { filter: brightness(.96); }
    #gl-root .gl-node .gl-lbl { padding-left: calc(12px + var(--gl-depth, 0) * 22px); }
    #gl-root .gl-txn td:first-child { padding-left: calc(12px + var(--gl-depth, 0) * 22px); }
    #gl-root .gl-k-group td { background: var(--afims-bg-soft, #F5F6FA); font-weight: 700; }
    #gl-root .gl-k-gl td { font-weight: 600; }
    #gl-root .gl-k-gl .gl-lbl { color: var(--afims-primary, #1D4ED8); }
    #gl-root .gl-k-sl td { font-weight: 400; }
    #gl-root .gl-node.gl-clickable { cursor: pointer; }
    #gl-root .gl-node.gl-clickable:hover td { filter: brightness(.96); }
    #gl-root .gl-combo-wrap .gl-search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--afims-text-muted, #62657E); pointer-events: none; z-index: 1; }
    /* light column header of the GL tree (Account code / Account description / Debit / Credit / Balance) */
    #gl-root thead.gl-head-light th { background: transparent !important; color: var(--afims-text-muted, #62657E) !important; font-size: 11.5px; letter-spacing: .04em; text-transform: uppercase; border-bottom: 1px solid var(--afims-border, #E2E4ED); }
    #gl-root .gl-link { color: var(--afims-primary, #1D4ED8); text-decoration: none; cursor: pointer; font-weight: 500; }
    #gl-sl-overlay .nha-tbl td, #gl-sl-overlay .nha-tbl th { white-space: nowrap; }
    #gl-sl-overlay .nha-tbl td:nth-child(4) { white-space: normal; min-width: 220px; }
    #gl-root .gl-amt { color: inherit; text-decoration: none; cursor: pointer; }
    #gl-root .gl-amt:hover { color: var(--afims-primary, #1D4ED8); text-decoration: underline; }
    #gl-root .gl-link .gl-link-icon { margin-left: 6px; vertical-align: -1px; }
    #gl-root .gl-link:hover { text-decoration: underline; }
    #gl-root .gl-code { font-size: 12px; color: var(--afims-text-muted, #62657E); margin-right: 8px; }
    #gl-root .gl-abnormal { color: #C0392B; }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-k-group td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-k-gl .gl-lbl, html.afims-dark[data-bs-theme="dark"] #gl-root .gl-link { color: #8FB4FF; }
    #gl-root .gl-sl-modal-sub { font-size: 12px; color: rgba(255,255,255,.8); margin-top: 2px; font-weight: 400; }
    #gl-root .gl-sl-toggle { display: inline-block; width: 12px; margin-right: 2px; color: var(--afims-text-muted, #62657E); }

    /* Dark mode: --afims-bg-soft is not a theme token, so the account group rows fell back to a near-white #F5F6FA (light text on
       it = unreadable); the account search results and the summary cards were hard-coded white */
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-group-row td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; border-top: 1px solid var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-group-row td span { color: var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #gl-root :is(.gl-combo-results, .gl-summary-card) { background: var(--afims-surface, #1A1F38); border-color: var(--afims-border-strong, #4A5270); color: var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-combo-item:hover { background: var(--afims-primary-soft, #0F2456); color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #gl-root :is(.gl-summary-label, .gl-combo-empty, .gl-ending-balance) { color: var(--afims-text-muted, #AAAFBE); }

</style>

<div id="gl-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">General Ledger</h1>
            <p class="app-page-subtitle">Showing every posted account. Pick one below to see just its ledger, oldest first, with a running balance.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="gl-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <span id="gl-print-wrap" style="position:relative;display:none;">
                <button id="gl-print" type="button" class="nha-btn-hdr" title="Print this account — General Ledger or Subsidiary Ledger" aria-haspopup="true" aria-expanded="false">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print &#9662;
            </button>
            </span>
            <div id="gl-print-menu" role="menu" class="no-print" style="display:none;position:fixed;z-index:1060;min-width:280px;background:var(--afims-surface,#fff);border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:6px;">
                <div style="padding:6px 10px 4px;">
                    <strong style="font-size:13px;color:var(--afims-text,#1e293b);">General Ledger</strong>
                    <div style="font-size:11.5px;color:var(--afims-text-muted,#62657E);">NHA Form No. 11 — pick the book and year to print</div>
                </div>
                <div id="gl-print-sheets" style="max-height:220px;overflow-y:auto;"></div>
                <div style="height:1px;background:var(--afims-border,#E4ECF2);margin:4px 2px;"></div>
                <div style="padding:6px 10px 4px;">
                    <strong style="font-size:13px;color:var(--afims-text,#1e293b);">Subsidiary Ledger</strong>
                    <div style="font-size:11.5px;color:var(--afims-text-muted,#62657E);">A sheet per SL account under it — pick the book and year</div>
                </div>
                <div id="gl-print-sl-sheets" style="max-height:200px;overflow-y:auto;"></div>
            </div>
        </div>
    </div>

    <!-- Error banner -->
    <div id="gl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="gl-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search" style="flex:1 1 320px;">
                <label class="nha-filter-label">Account</label>
                <div class="gl-combo-wrap">
                    <svg class="gl-search-icon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/></svg>
                    <input type="text" id="gl-account-search" class="nha-filter-input" style="width:100%;padding-left:34px;"
                           placeholder="4th-level code or title, e.g. 1-01-02" autocomplete="off">
                    <div id="gl-account-results" class="gl-combo-results"></div>
                </div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal year</label>
                <select id="gl-filter-fy" class="nha-filter-select">
                    <option value="">All years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book</label>
                <select id="gl-filter-book" class="nha-filter-select">
                    <option value="">All books</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── SUMMARY (account header — code/name/normal balance/class) ──────── -->
    <div id="gl-summary" class="gl-summary-cards no-print" style="display:none;">
        <div class="gl-summary-card">
            <div class="gl-summary-label">Account</div>
            <div class="gl-summary-value" id="gl-summary-account" style="font-size:14px;">—</div>
        </div>
        <div class="gl-summary-card">
            <div class="gl-summary-label">Normal Balance</div>
            <div class="gl-summary-value" id="gl-summary-normal" style="font-size:14px;">—</div>
        </div>
        <div class="gl-summary-card">
            <div class="gl-summary-label">Account Class</div>
            <div class="gl-summary-value" id="gl-summary-class" style="font-size:14px;">—</div>
        </div>
        <div class="gl-summary-card">
            <div class="gl-summary-label">Total Debit</div>
            <div class="gl-summary-value nha-mono" id="gl-summary-debit">₱ 0.00</div>
        </div>
        <div class="gl-summary-card">
            <div class="gl-summary-label">Total Credit</div>
            <div class="gl-summary-value nha-mono" id="gl-summary-credit">₱ 0.00</div>
        </div>
    </div>

    <!-- Expand/Collapse All — only meaningful for the grouped (no-account-picked) view, where each
         GL rows are collapsed by default (see gl_toggleNode); hidden once one account is picked. -->
    <div id="gl-expand-toolbar" class="no-print" style="display:none;justify-content:flex-end;gap:8px;margin:0 0 10px;">
        <button id="gl-expand-all" type="button" class="nha-btn-hdr">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4m12-4v4h-4"/></svg>
            Expand All
        </button>
        <button id="gl-collapse-all" type="button" class="nha-btn-hdr">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4v4H5m10-4v4h4M9 20v-4H5m10 4v-4h4"/></svg>
            Collapse All
        </button>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead id="gl-thead"></thead>
                <tbody id="gl-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="gl-ending" class="gl-ending-balance no-print" style="display:none;"></div>

    <style>#gl-print-menu .gl-print-opt:hover:not(:disabled),#gl-print-menu .gl-print-sheet:hover{background:var(--afims-surface-subtle,#F4F7FA);}#gl-print-menu .gl-print-sheet{display:flex;justify-content:space-between;gap:12px;width:100%;text-align:left;background:none;border:0;border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--afims-text,#1e293b);font:inherit;font-size:13px;}#gl-print-menu .gl-print-sheet small{color:var(--afims-text-muted,#62657E);font-size:11px;white-space:nowrap;}#gl-print-menu .gl-print-note{padding:6px 10px;font-size:12px;color:var(--afims-text-muted,#62657E);}#gl-print-menu .gl-print-opt:disabled{opacity:.55;cursor:not-allowed !important;}</style>
    <!-- ── SL POPUP (click a GL account → its SL accounts; click an SL → sub-SLs / transactions) ── -->
    <div id="gl-sl-overlay" class="nha-overlay no-print">
        <div class="nha-modal u-modal-xl" role="dialog" aria-modal="true" aria-labelledby="gl-sl-title">
            <div class="nha-modal-hdr">
                <div>
                    <h3 class="nha-modal-title" id="gl-sl-title">Subsidiary Ledgers</h3>
                    <div class="gl-sl-modal-sub" id="gl-sl-subtitle"></div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="nha-btn nha-btn-outline" id="gl-sl-print" style="display:none;" title="Preview the General Ledger report — print or export to Excel">Preview</button>
                    <button type="button" class="nha-modal-close" id="gl-sl-close" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="nha-modal-body">
                <div class="nha-table-wrap">
                    <div class="nha-tbl-scroll">
                        <table class="nha-tbl">
                            <thead>
                                <tr>
                                    <th>Account / Posting Date</th>
                                    <th>JEV No.</th>
                                    <th>DV Number</th>
                                    <th>Description / Particulars</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="gl-sl-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /#gl-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   GENERAL LEDGER   prefix: gl_
═══════════════════════════════════════════════════════════════ */

const GL_BASE = <?= json_encode($_gl_api) ?>;
const GL_API  = GL_BASE + '/general_ledger';
const GL_COA_API = GL_BASE + '/chart_of_accounts/all';
const GL_FY_API  = GL_BASE + '/fiscal_year/all';
const GL_BOOK_API = GL_BASE + '/books/all';

let gl_selectedAccount = null; // { account_code, account_title }
let gl_searchTimer = null;
let gl_ledger = null;          // the last single-account ledger loaded (what Print prints); null in the all-accounts view

const $gl = id => document.getElementById(id);

function gl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

// The table header follows the view: the GL tree (account columns) or one account's ledger (transaction columns)
function gl_setHead(tree) {
    const h = $gl('gl-thead');
    h.className = tree ? 'gl-head-light' : '';
    h.innerHTML = tree
        ? '<tr><th>Account code</th><th colspan="3">Account description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr>'
        : '<tr><th>Posting Date</th><th>JEV No.</th><th>DV Number</th><th>Particulars</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end">Balance</th></tr>';
}
async function gl_boot() {
    gl_setHead(true);
    await Promise.all([gl_loadFiscalYears(), gl_loadBooks()]);
    gl_bindEvents();
    gl_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', gl_boot);
else gl_boot();

function gl_bindEvents() {
    $gl('gl-refresh')?.addEventListener('click', () => gl_load());
    $gl('gl-print')?.addEventListener('click', e => {
        e.stopPropagation();
        const open = $gl('gl-print-menu').style.display !== 'none';
        gl_togglePrintMenu(!open, e.currentTarget, gl_ledger?.account?.account_code);
    });
    window.addEventListener('scroll', () => gl_togglePrintMenu(false), true);
    window.addEventListener('resize', () => gl_togglePrintMenu(false));
    $gl('gl-print-sheets')?.addEventListener('click', e => {
        e.stopPropagation();
        const b = e.target.closest('.gl-print-sheet');
        if (!b) return;
        gl_togglePrintMenu(false);
        gl_print(gl_printCode, { book_id: b.dataset.book, fund: b.dataset.fund, fiscal_year: b.dataset.year });
    });
    $gl('gl-print-menu')?.addEventListener('click', e => e.stopPropagation());
    $gl('gl-print-sl-sheets')?.addEventListener('click', e => {
        e.stopPropagation();
        const b = e.target.closest('.gl-print-sheet');
        if (!b) return;
        gl_togglePrintMenu(false);
        gl_printSl(gl_printCode, { book_id: b.dataset.book, fund: b.dataset.fund, fiscal_year: b.dataset.year });
    });
    document.addEventListener('click', () => gl_togglePrintMenu(false));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') gl_togglePrintMenu(false); });
    $gl('gl-filter-fy')?.addEventListener('change', () => gl_load());
    $gl('gl-filter-book')?.addEventListener('change', () => gl_load());

    // Grouped (no-account-picked) view: click "View SLs" to open the SL drill-down popup;
    // click anywhere else on the header row to toggle its own transactions open/closed.
    // GL row → SL popup; inside the popup an SL row → its sub-SLs / transactions
    $gl('gl-tbody')?.addEventListener('click', e => {
        const amt = e.target.closest('.gl-amt');
        if (amt) { e.preventDefault(); gl_openGlTxns(amt.closest('tr').dataset.id); return; }
        const link = e.target.closest('.gl-link');
        if (!link) return;
        e.preventDefault();
        gl_openGlPopup(link.closest('tr').dataset.id);
    });
    $gl('gl-sl-tbody')?.addEventListener('click', e => {
        const row = e.target.closest('tr.gl-node.gl-clickable');
        if (row) gl_toggleNode(row);
    });
    $gl('gl-sl-close')?.addEventListener('click', gl_closeGlPopup);
    $gl('gl-sl-print')?.addEventListener('click', gl_previewTxns);
    $gl('gl-sl-overlay')?.addEventListener('click', e => { if (e.target.id === 'gl-sl-overlay') gl_closeGlPopup(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') gl_closeGlPopup(); });

    const input = $gl('gl-account-search');
    input.addEventListener('input', () => {
        gl_selectedAccount = null;
        clearTimeout(gl_searchTimer);
        gl_searchTimer = setTimeout(() => gl_searchAccounts(input.value.trim()), 300);
        if (!input.value.trim()) gl_load();
    });
    input.addEventListener('focus', () => { if (input.value.trim()) gl_searchAccounts(input.value.trim()); });
    document.addEventListener('click', e => {
        if (!e.target.closest('#gl-account-search') && !e.target.closest('#gl-account-results')) {
            $gl('gl-account-results').style.display = 'none';
        }
    });
}

/* Fiscal years come from fims.fiscal_year via GET /fiscal_year/all → {success, data:[{fiscal_yr}]}. */
async function gl_loadFiscalYears() {
    const sel = $gl('gl-filter-fy');
    try {
        const res  = await gl_authFetch(GL_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${gl_esc(r.fiscal_yr)}">${gl_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        gl_showError('Fiscal years: ' + e.message);
    }
}

/* Books come from fims.books via GET /books/all → {success, data:[{book_id, code, name}]}. */
async function gl_loadBooks() {
    const sel = $gl('gl-filter-book');
    try {
        const res  = await gl_authFetch(GL_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        sel.innerHTML = '<option value="">All Books</option>' +
            (json.data ?? []).map(r => `<option value="${gl_esc(r.book_id)}">${gl_esc(r.name)}</option>`).join('');
    } catch (e) {
        gl_showError('Books: ' + e.message);
    }
}

/* Account combobox — any chart-of-accounts account (GL headers like 1-01-01 as well as postable ones),
   GET /chart_of_accounts/all?search=… — a GL account's ledger includes its SLs' postings. */
async function gl_searchAccounts(search) {
    const box = $gl('gl-account-results');
    try {
        const params = new URLSearchParams({ search });
        const res  = await gl_authFetch(`${GL_COA_API}?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        const rows = (json.data ?? []).slice(0, 30);
        if (!rows.length) {
            box.innerHTML = '<div class="gl-combo-empty">No matching accounts.</div>';
        } else {
            box.innerHTML = rows.map(r => `<div class="gl-combo-item" data-code="${gl_esc(r.account_code)}" data-title="${gl_esc(r.account_title)}">
                <strong class="nha-mono">${gl_esc(r.account_code)}</strong> — ${gl_esc(r.account_title)}
            </div>`).join('');
            box.querySelectorAll('.gl-combo-item').forEach(el => el.addEventListener('click', () => {
                gl_selectedAccount = { account_code: el.dataset.code, account_title: el.dataset.title };
                $gl('gl-account-search').value = `${el.dataset.code} — ${el.dataset.title}`;
                box.style.display = 'none';
                gl_load();
            }));
        }
        box.style.display = 'block';
    } catch (e) {
        gl_showError('Account search: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function gl_load() {
    window.PagePreloader?.show();
    gl_hideError();
    try {
        const params = new URLSearchParams();
        if (gl_selectedAccount) params.set('account_code', gl_selectedAccount.account_code);
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);

        // no account picked → the GL/SL tree; one account picked → its flat ledger
        const res  = await gl_authFetch(`${GL_API}/${gl_selectedAccount ? 'ledger' : 'tree'}?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the General Ledger.');

        const isTree = !gl_selectedAccount;
        gl_setHead(isTree);
        gl_setPrintable(isTree ? null : json);
        if (isTree) {
            $gl('gl-summary').style.display = 'none';
            $gl('gl-ending').style.display = 'none';
            $gl('gl-expand-toolbar').style.display = 'none';
            gl_renderTree(json.data ?? []);
        } else {
            $gl('gl-expand-toolbar').style.display = 'none';
            gl_renderSummary(json);
            gl_renderTable(json.data ?? [], json.debit_normal);
            gl_renderEnding(json.ending_balance, json.debit_normal);
        }
    } catch (e) {
        gl_setPrintable(null);
        $gl('gl-expand-toolbar').style.display = 'none';
        gl_renderTable([]);
        gl_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Print ────────────────────────────────────────────────────────────────────────────────────────────
   Print (a menu: General Ledger | Subsidiary Ledger) — in the header, once one account is picked in the Account
   search (the account shown). General Ledger: the GENERAL LEDGER sheet, NHA Form No. 11 (prp_glPayload → prp_open(…, GL)): the account shown, for
   ONE fiscal year and ONE book (the sheet is kept per book — the Book prints as the Fund): the Beginning Balance (the
   book's Opening Balance), then per month one line per journal (CKDJ / CRDJ / ADADJ / GJ), the Closing Entry and the
   Ending Balance. Needs one Book (the Fiscal Year filter, else the postings' single year).
   Subsidiary Ledger (header menu, or the SL popup's "Print SL") — the SUBSIDIARY LEDGER sheet of each SL with postings (prp_glSlPayload → prp_open(…, SL)). */
function gl_setPrintable(json) {
    gl_ledger = json;
    const wrap = $gl('gl-print-wrap');
    if (wrap) wrap.style.display = json ? 'inline-block' : 'none';   // shown once one account is picked
    if (!json) gl_togglePrintMenu(false);
}
// The Print menu (General Ledger | Subsidiary Ledger) — opened from the header Print (the account shown); `code` is the account it prints. Shown beside the button that opened it.
let gl_printCode = '';
function gl_togglePrintMenu(open, anchor, code) {
    const menu = $gl('gl-print-menu');
    if (!menu) return;
    if (!open || !anchor || !code) {
        menu.style.display = 'none';
        $gl('gl-print')?.setAttribute('aria-expanded', 'false');
        return;
    }
    gl_hideError();
    gl_printCode = code;
    gl_loadPrintSheets(code);
    menu.style.display = '';
    const r = anchor.getBoundingClientRect();
    const w = menu.offsetWidth || 250;
    const h = menu.offsetHeight || 120;
    menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menu.style.top  = (r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
    if (anchor.id === 'gl-print') anchor.setAttribute('aria-expanded', 'true');
}
// The General Ledger sheets the account can print — only (book, fiscal year) pairs with postings or an Opening
// Balance (GET /print/gl-sheets), honouring the Fiscal Year filter — so the user picks from what exists.
let gl_sheetsReq = 0;
async function gl_loadPrintSheets(code) {
    const box = $gl('gl-print-sheets');
    const slBox = $gl('gl-print-sl-sheets');
    if (!box) return;
    const req = ++gl_sheetsReq;
    box.innerHTML = '<div class="gl-print-note">Loading…</div>';
    if (slBox) slBox.innerHTML = '';
    try {
        const params = new URLSearchParams({ account_code: code });
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const json = await gl_authFetch(`${GL_BASE.replace(/\/$/, '')}/print/gl-sheets?${params}`).then(r => r.json());
        if (req !== gl_sheetsReq) return;
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        const rows = json.data ?? [];
        box.innerHTML = rows.length
            ? rows.map(r => `<button type="button" role="menuitem" class="gl-print-sheet" data-book="${r.book_id}" data-fund="${gl_esc(r.book_name)}" data-year="${r.fiscal_yr}">
                    <span>${gl_esc(r.book_name || ('Book ' + r.book_id))} — FY ${r.fiscal_yr}</span>
                    <small>${r.postings ? r.postings + ' posting' + (r.postings === 1 ? '' : 's') : 'Beginning balance only'}</small>
                </button>`).join('')
            : `<div class="gl-print-note">Nothing to print${fy ? ' for FY ' + gl_esc(fy) : ''} — this account has no postings${fy ? ' that year' : ''}.</div>`;
        // Subsidiary Ledger: only the book/year sheets where an SL account under it has postings or a beginning balance
        const slRows = rows.filter(r => r.sl_postings || r.sl_opening);
        if (slBox) slBox.innerHTML = slRows.length
            ? slRows.map(r => `<button type="button" role="menuitem" class="gl-print-sheet" data-book="${r.book_id}" data-fund="${gl_esc(r.book_name)}" data-year="${r.fiscal_yr}">
                    <span>${gl_esc(r.book_name || ('Book ' + r.book_id))} — FY ${r.fiscal_yr}</span>
                    <small>${r.sl_postings ? r.sl_postings + ' SL posting' + (r.sl_postings === 1 ? '' : 's') : 'Beginning balances only'}</small>
                </button>`).join('')
            : '<div class="gl-print-note">No subsidiary ledger postings under this account.</div>';
    } catch (e) {
        if (req !== gl_sheetsReq) return;
        box.innerHTML = `<div class="gl-print-note">Could not load the books: ${gl_esc(e.message)}</div>`;
    }
}
function gl_printOpts() {
    const book = $gl('gl-filter-book');
    return {
        fund: book && book.value ? (book.options[book.selectedIndex]?.text || '') : '',
        book_id: book ? book.value : '',
        fiscal_year: $gl('gl-filter-fy').value,
    };
}
// General Ledger — the account's ledger (GET /general_ledger/ledger with the page's Fiscal Year / Book filters; the one
// already loaded when it is the account shown) → NHA Form No. 11.
async function gl_print(code, pick = null) {
    if (!code || typeof prp_open !== 'function') return;
    gl_hideError();
    // a sheet picked from the menu (its own book and year), else the page's filters
    const opts = pick ? { book_id: String(pick.book_id), fund: pick.fund || '', fiscal_year: String(pick.fiscal_year || '') } : gl_printOpts();
    if (!opts.book_id) {
        gl_showError('Pick a Book to print — the General Ledger sheet is kept per book (the Book prints as the Fund).');
        return;
    }
    window.PagePreloader?.show();
    try {
        const f = gl_printOpts();
        let ledger = gl_ledger && gl_ledger.account?.account_code === code && f.book_id === opts.book_id && f.fiscal_year === opts.fiscal_year ? gl_ledger : null;
        if (!ledger) {
            const params = new URLSearchParams({ account_code: code, book_id: opts.book_id });
            if (opts.fiscal_year) params.set('fiscal_yr', opts.fiscal_year);
            ledger = await gl_authFetch(`${GL_API}/ledger?${params}`).then(r => r.json());
            if (!ledger.success) throw new Error(ledger.message || 'Failed to load the account ledger.');
        }
        if (!opts.fiscal_year) {
            const years = [...new Set((ledger.data ?? []).map(r => String(r.posting_date || '').slice(0, 4)).filter(Boolean))];
            if (years.length > 1) {
                gl_showError(code + ' has postings in ' + years.join(', ') + ' — pick a Fiscal Year to print (the ledger sheet is one year).');
                return;
            }
            opts.fiscal_year = years[0] || String(new Date().getFullYear());
        }
        await prp_open(prp_glPayload(ledger, opts), { type: 'GL' });
    } catch (e) {
        gl_showError('Could not print: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
// Subsidiary Ledger — the SL sheets of the account (GET /general_ledger/ledger-sl with the page's Fiscal Year / Book
// filters — the same data the SL popup loads) → one SUBSIDIARY LEDGER sheet per SL with data in that book and year.
async function gl_printSl(code, pick = null) {
    if (!code || typeof prp_open !== 'function') return;
    gl_hideError();
    const params = new URLSearchParams({ account_code: code });
    // a sheet picked from the menu (its own book and year), else the page's filters
    const opts = pick ? { book_id: String(pick.book_id), fund: pick.fund || '', fiscal_year: String(pick.fiscal_year || '') } : gl_printOpts();
    if (opts.fiscal_year) params.set('fiscal_yr', opts.fiscal_year);
    if (opts.book_id) params.set('book_id', opts.book_id);
    window.PagePreloader?.show();
    try {
        const json = await gl_authFetch(`${GL_API}/ledger-sl?${params}`).then(r => r.json());
        if (!json.success) throw new Error(json.message || 'Failed to load the sub-ledgers.');
        await prp_open(prp_glSlPayload(json, opts), { type: 'SL' });
    } catch (e) {
        gl_showError('Could not print: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
/* ── Summary (account header — code/name/normal balance/class) ──────────────── */
function gl_renderSummary(json) {
    $gl('gl-summary').style.display = '';
    $gl('gl-summary-account').textContent = `${json.account.account_code} — ${json.account.account_title}`;
    $gl('gl-summary-normal').textContent  = json.normal_balance || '—';
    $gl('gl-summary-class').textContent   = json.account.account_type || '—';
    $gl('gl-summary-debit').textContent   = gl_peso(json.total_debit);
    $gl('gl-summary-credit').textContent  = gl_peso(json.total_credit);
}

/* "Ending Balance as of {today}" footer, shown only for a single-account ledger (grouped/all-accounts
   view has its own per-account ending balance already in the group header). */
function gl_renderEnding(endingBalance, debitNormal) {
    const el = $gl('gl-ending');
    el.style.display = '';
    el.innerHTML = `Ending Balance as of ${gl_fmtDate(new Date().toISOString())}: <strong>${gl_peso(Math.abs(endingBalance))}${gl_balSide(endingBalance, debitNormal)}</strong>`;
}

/* ── Table ─────────────────────────────────────────────────── */
function gl_renderTable(rows, debitNormal) {
    const tbody = $gl('gl-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted transactions for this account.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => gl_rowHtml(r, debitNormal)).join('');
}

/* Default view (no account picked): the GL accounts only (GET /general_ledger/tree), in the Code / Description / Debit /
   Credit / Balance format. Click a GL → a popup lists its SL accounts; click an SL that has sub-SLs → they appear under it;
   click an account with postings → its transactions (GET /general_ledger/postings, fetched once). */
let gl_nodes = {};   // account_id → node (GL + every SL under it, depth re-based so a GL is 0)
let gl_open  = {};   // account_id → true when expanded in the popup
let gl_list  = [];   // nodes in display order

function gl_nodeRow(n, popup) {
    const expandable = popup ? (n.has_children || n.own_count > 0) : (n.has_children || n.own_count > 0);
    const bal = Number(n.balance);
    const side = bal === 0 ? '' : (bal > 0 ? ' Dr' : ' Cr');
    const abnormal = bal !== 0 && ((bal > 0) !== !!n.debit_normal);
    const blank = n.direct;   // "Direct postings" row — no own totals available
    const depth = popup ? n.depth - 1 : 0;
    // in the GL list the amounts are links → every transaction of the account (GL + its SLs)
    const amt = (html, show) => (!popup && show) ? `<a href="#" class="gl-amt" role="button" title="Click to see all transactions">${html}</a>` : html;
    return `<tr class="gl-node gl-k-${n.kind}${expandable && popup ? ' gl-clickable' : ''}" data-id="${gl_esc(n.account_id)}" data-parent="${gl_esc(n.parent_id ?? '')}" data-code="${gl_esc(n.account_code)}" style="--gl-depth:${depth};">
        ${popup ? '' : `<td class="gl-lbl"><span class="nha-mono gl-code">${gl_esc(n.account_code)}</span></td><td colspan="3">${expandable ? `<a href="#" class="gl-link" role="button" title="Click to see the subsidiary ledgers">${gl_esc(n.account_title)}<svg class="gl-link-icon" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg></a>` : gl_esc(n.account_title)}</td>`}
        ${!popup ? '' : `<td colspan="4" class="gl-lbl"><span class="gl-sl-toggle">${expandable ? '&#9656;' : ''}</span><span class="nha-mono gl-code">${n.direct ? '' : gl_esc(n.account_code)}</span> ${n.direct ? 'Direct postings to ' + gl_esc(n.account_code) : gl_esc(n.account_title)}</td>`}
        <td class="text-end nha-mono">${blank ? '' : amt(Number(n.total_debit) ? gl_peso(n.total_debit) : '—', Number(n.total_debit))}</td>
        <td class="text-end nha-mono">${blank ? '' : amt(Number(n.total_credit) ? gl_peso(n.total_credit) : '—', Number(n.total_credit))}</td>
        <td class="text-end nha-mono${abnormal && !blank ? ' gl-abnormal' : ''}">${blank ? '' : amt((bal === 0 ? '0.00' : gl_peso(Math.abs(bal))) + side, bal !== 0)}</td>
    </tr>`;
}

function gl_renderTree(nodes) {
    const tbody = $gl('gl-tbody');
    gl_nodes = {}; gl_open = {};
    // classification headers above the GL accounts are left out; depth is re-based so a GL sits at 0
    let glDepth = 0;
    gl_list = nodes.filter(n => n.kind !== 'group').map(n => {
        if (n.kind === 'gl') glDepth = n.depth;
        return { ...n, depth: n.depth - glDepth };
    });
    gl_list.forEach(n => { gl_nodes[n.account_id] = n; });
    const gls = gl_list.filter(n => n.kind === 'gl');
    if (!gls.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = gls.map(n => gl_nodeRow(n, false)).join('');
}

// GL click → popup with the SL accounts under it
function gl_openGlPopup(id) {
    const gl = gl_nodes[id];
    if (!gl) return;
    const idx = gl_list.findIndex(n => n.account_id === id);
    const sls = [];
    for (let i = idx + 1; i < gl_list.length && gl_list[i].kind !== 'gl'; i++) sls.push(gl_list[i]);
    gl_open = {};
    // postings made straight to the GL (not to an SL) are not listed here — they belong on an SL account
    gl_txnData = null; gl_showExport(false);
    const tbody = $gl('gl-sl-tbody');
    $gl('gl-sl-title').textContent = `${gl.account_code} — ${gl.account_title}`;
    $gl('gl-sl-subtitle').textContent = sls.length
        ? `${sls.filter(n => !n.direct).length} subsidiary ledger${sls.filter(n => !n.direct).length === 1 ? '' : 's'} · click one to see its sub-accounts or transactions`
        : '';
    if (!sls.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No subsidiary ledgers under this account.</span></div></td></tr>';
        $gl('gl-sl-overlay').classList.add('open');
        return;
    }
    tbody.innerHTML = sls.map(n => gl_nodeRow(n, true)).join('');
    gl_txnData = { kind: 'sl', gl, sls: sls.filter(n => !n.direct), json: null };   // Preview fetches the postings when clicked
    gl_showExport(true);
    $gl('gl-sl-overlay').classList.add('open');
    gl_applyTree(tbody);
}
/* ── Preview / Print / Excel — of the transactions popup (kind 'txn') and of the SL list popup (kind 'sl') ─────────────── */
let gl_txnData = null;   // { kind, gl, json, sls? } of the popup now showing — what Preview / Print / Excel use
function gl_showExport(show) {
    ['gl-sl-print'].forEach(id => { const b = $gl(id); if (b) b.style.display = show ? '' : 'none'; });
}
function gl_filterText() {
    const sel = id => { const e = $gl(id); return e && e.value ? e.options[e.selectedIndex].text : ''; };
    return [['Fiscal year', sel('gl-filter-fy') || 'All years'], ['Book', sel('gl-filter-book') || 'All books']];
}
const gl_num = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// Report layout: Date | Particulars | REF | JEV / DV # | Check No. / Check Date | DEBIT | CREDIT | BALANCE, a year line
// ("2026") above its postings, dates as "JAN 1", REF = the journal (GJ / CKDJ / ADADJ / CRDJ)
const GL_MON = ['JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
const GL_HEAD = ['Date', 'Particulars', 'REF', 'JEV / DV #', 'Check No. / Check Date', 'DEBIT', 'CREDIT', 'BALANCE'];
// the ledger lines of one account → year line, Beginning Balance, a row per posting, and the Ending Balance row
function gl_ledgerSection(rows, debitNormal, balOf) {
    const bal = v => gl_num(Math.abs(v)) + gl_balSide(v, debitNormal);
    const items = [];
    let year = null, td = 0, tc = 0, last = 0;
    rows.forEach(r => {
        const d = String(r.posting_date || '').slice(0, 10);
        const y = d.slice(0, 4);
        if (y !== year) {
            year = y; items.push({ year: y });
            if (items.length === 1) items.push({ cells: ['JAN 1', 'Beginning Balance', '', '', '', '-', '-', '-'] });
        }
        const chk = [r.check_no, r.check_date ? gl_fmtDate(r.check_date) : ''].filter(Boolean).join(' / ');
        td += Number(r.debit) || 0; tc += Number(r.credit) || 0; last = balOf(r);
        items.push({ cells: [
            d ? `${GL_MON[Number(d.slice(5, 7)) - 1]} ${Number(d.slice(8, 10))}` : '',
            r.jev_particulars || r.description || '',   // the JEV's particulars (else its DV's)
            r.journal || 'GJ',
            [r.jev_no, r.dv_no].filter(Boolean).join(' / '),
            chk,
            Number(r.debit) > 0 ? Number(r.debit) : '',
            Number(r.credit) > 0 ? Number(r.credit) : '',
            bal(last),
        ] });
    });
    return { items, total: [year ? 'DEC 31' : '', 'Ending Balance', '', '', '', Math.round(td * 100) / 100, Math.round(tc * 100) / 100, bal(last)] };
}
function gl_report() {
    const { gl, json } = gl_txnData;
    return { meta: gl_filterText(), ...gl_ledgerSection(json.data ?? [], json.debit_normal, r => Number(r.running_balance)) };
}
// the SL list popup → one ledger section per SL / sub-SL that has postings, balances running per SL
function gl_slSections() {
    const { gl, json, sls } = gl_txnData;
    const dn = json.debit_normal;
    const byCode = {};
    (json.data ?? []).forEach(r => { if (r.sl_account_code) (byCode[r.sl_account_code] ||= []).push(r); });
    // only the SL / sub-SL accounts — postings made straight to the GL account itself are not part of the Subsidiary Ledger
    const order = sls.map(n => ({ code: n.account_code, title: n.account_title }));
    return order.filter(o => byCode[o.code]).map(o => {
        let run = 0;
        return { code: o.code, title: o.title, ...gl_ledgerSection(byCode[o.code], dn, r => (run += dn ? (Number(r.debit) - Number(r.credit)) : (Number(r.credit) - Number(r.debit)))) };
    });
}
// NHA Form No. 11 look — the form header block, then the bordered ledger. excel = raw numbers + an Excel number format.
function gl_fund(meta) {
    const bookSel = $gl('gl-filter-book');
    return bookSel && bookSel.value ? bookSel.options[bookSel.selectedIndex].text : '';
}
function gl_ledgerRows(items, total, excel) {
    const money = v => (v === '' || v === '-') ? v : (excel ? v : gl_num(v));
    const td = (v, i) => `<td class="${i >= 5 ? 'n' : ''}"${excel && (i === 5 || i === 6) && typeof v === 'number' ? ' style="mso-number-format:\'#,##0.00\'"' : ''}>${i === 5 || i === 6 ? money(v) : gl_esc(v)}</td>`;
    return `<tr class="hd">${GL_HEAD.map((h, i) => `<th class="${i >= 5 ? 'n' : ''}">${h}</th>`).join('')}</tr>`
        + items.map(it => it.year
            ? `<tr class="yr"><td class="c"><b>${gl_esc(it.year)}</b></td><td colspan="7"></td></tr>`
            : `<tr>${it.cells.map((v, i) => td(v, i)).join('')}</tr>`).join('')
        + `<tr class="end">${total.map((v, i) => td(v, i)).join('')}</tr>`;
}
function gl_formHeader(title, formNo, lines, meta) {
    return `<tbody class="meta">
        <tr class="nb"><td colspan="8" class="frm">${formNo}&nbsp;</td></tr>
        <tr class="nb"><td colspan="8" class="ttl">${title}</td></tr>
        <tr class="nb"><td colspan="8" class="ctr"><u><b>NATIONAL HOUSING AUTHORITY</b></u></td></tr>
        <tr class="nb"><td colspan="8" class="ctr agy">Agency</td></tr>
        ${lines}
        <tr class="nb"><td colspan="8" class="agy">${meta.map(m => gl_esc(m[0]) + ': ' + gl_esc(m[1])).join(' &nbsp;|&nbsp; ')}</td></tr>
        <tr class="nb"><td colspan="8">&nbsp;</td></tr></tbody>`;
}
function gl_reportTable(excel) {
    const { gl, kind } = gl_txnData;
    const meta = gl_filterText();
    const fund = gl_fund(meta) || 'All books';
    if (kind === 'sl') {
        const secs = gl_slSections();
        return `<table class="gl-form">${gl_formHeader('SUBSIDIARY LEDGER', '',
            `<tr class="nb"><td colspan="5"><b>Fund:</b> &nbsp; ${gl_esc(fund)}</td><td colspan="3"><b>GL Account Code:</b> &nbsp;${gl_esc(gl.account_code)}</td></tr>
             <tr class="nb"><td colspan="5"><b>GL Account Title:</b> &nbsp;<i>${gl_esc(gl.account_title)}</i></td><td colspan="3"><b>Sheet No.</b> &nbsp;</td></tr>`, meta)}
            ${secs.length ? secs.map(sec => `<tbody class="led"><tr class="sec"><td colspan="8"><b>SL Account Code:</b> ${gl_esc(sec.code)} &nbsp;&nbsp; <b>SL Account Title:</b> <i>${gl_esc(sec.title)}</i></td></tr>${gl_ledgerRows(sec.items, sec.total, excel)}</tbody>`).join('')
                : '<tbody class="led"><tr><td colspan="8">No postings under the subsidiary ledgers.</td></tr></tbody>'}</table>`;
    }
    const r = gl_report();
    return `<table class="gl-form">${gl_formHeader('GENERAL LEDGER', 'NHA Form No. 11',
        `<tr class="nb"><td colspan="5"><b>Fund:</b> &nbsp; ${gl_esc(fund)}</td><td colspan="3"><b>Account Code:</b> &nbsp;${gl_esc(gl.account_code)}</td></tr>
         <tr class="nb"><td colspan="5"><b>Account Title:</b> &nbsp;<i>${gl_esc(gl.account_title)}</i></td><td colspan="3"><b>Sheet No.</b> &nbsp;</td></tr>`, r.meta)}
        <tbody class="led">${gl_ledgerRows(r.items, r.total, excel)}</tbody></table>`;
}
const GL_REPORT_CSS = `*{box-sizing:border-box} body{margin:0;font-family:Arial,Helvetica,sans-serif;color:#000;background:#fff}
 table.gl-form{width:100%;border-collapse:collapse;font-size:12px}
 table.gl-form td,table.gl-form th{padding:3px 6px;vertical-align:top}
 tr.nb td{border:0} td.frm{font-weight:bold;font-size:13px} td.ttl{text-align:center;font-weight:bold;font-size:24px;padding:6px 0} td.ctr{text-align:center} td.agy{font-size:10px;font-style:italic}
 td.agy:not(.ctr){text-align:left;color:#444} td.ctr.agy{font-size:10px}
 tr.hd th{border:1px solid #000;border-top:2px solid #000;border-bottom:2px solid #000;font-weight:bold;text-align:center;background:#fff;color:#000;padding:5px 6px}
 tbody.led td{border-left:1px solid #000;border-right:1px solid #000;border-bottom:1px dotted #777}
 tbody.led tr.sec td{border:0;padding-top:16px;font-size:12.5px}
 tr.yr td{border-bottom:0} tr.yr td.c{text-align:center} th.n,td.n{text-align:right;white-space:nowrap} tbody.led td:nth-child(1),tbody.led td:nth-child(4){white-space:nowrap}
 tr.end td{border-top:2px solid #000;border-bottom:2px solid #000;font-weight:bold}`;
const gl_reportName = () => gl_txnData.kind === 'sl' ? `Subsidiary-Ledger_${gl_txnData.gl.account_code}` : `General-Ledger_${gl_txnData.gl.account_code}`;
// Preview → a report window with an Excel button and a Print button in its toolbar. The SL list popup fetches its postings first.
async function gl_previewTxns() {
    if (!gl_txnData) return;
    const w = window.open('', '_blank', 'width=1100,height=800');   // opened inside the click, before any await
    if (!w) { gl_showError('Allow pop-ups for this site to open the preview.'); return; }
    w.document.write('<p style="font-family:Arial;padding:24px">Preparing the report…</p>');
    try {
        if (gl_txnData.kind === 'sl' && !gl_txnData.json) {
            const params = new URLSearchParams({ account_code: gl_txnData.gl.account_code });
            const fy = $gl('gl-filter-fy').value;
            if (fy) params.set('fiscal_yr', fy);
            const book = $gl('gl-filter-book').value;
            if (book) params.set('book_id', book);
                const res  = await gl_authFetch(`${GL_API}/ledger?${params}`);
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load the postings.');
            gl_txnData.json = json;
        }
        const html = gl_reportTable(false);
        w.document.open();
        w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${gl_esc(gl_reportName())}</title>
<style>@page{size:landscape;margin:12mm}${GL_REPORT_CSS}
 .bar{position:sticky;top:0;background:#2f3336;padding:10px 24px;display:flex;justify-content:flex-end;gap:10px}
 .bar button{background:#1a73e8;color:#fff;border:0;border-radius:8px;padding:9px 16px;font-weight:600;font-size:14px;cursor:pointer}
 .bar button.alt{background:#fff;color:#1f497d} .page{padding:24px 40px 40px}
 @media print{.bar{display:none}.page{padding:0}}</style></head><body>
<div class="bar"><button class="alt" id="x">Excel</button><button id="p">Print</button></div>
<div class="page">${html}</div>
<script>
 document.getElementById('p').onclick = function () { window.print(); };
 document.getElementById('x').onclick = function () { try { window.opener.gl_excelTxns(); } catch (e) { alert('Close this preview and use the page again to export.'); } };
<\/script></body></html>`);
        w.document.close();
    } catch (e) {
        w.close();
        gl_showError('Could not prepare the preview: ' + e.message);
    }
}
function gl_excelTxns() {
    if (!gl_txnData || !gl_txnData.json) return;
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><style>${GL_REPORT_CSS}</style></head><body>${gl_reportTable(true)}</body></html>`;
    const url = URL.createObjectURL(new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = gl_reportName() + '.xls';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// Amount click → every posting of the account (its SLs' included, same rollup as the single-account ledger), oldest first
let gl_txnReq = 0;
async function gl_openGlTxns(id) {
    const gl = gl_nodes[id];
    if (!gl) return;
    const req = ++gl_txnReq;
    gl_txnData = null; gl_showExport(false);
    const tbody = $gl('gl-sl-tbody');
    $gl('gl-sl-title').textContent = `${gl.account_code} — ${gl.account_title}`;
    $gl('gl-sl-subtitle').textContent = 'All transactions';
    tbody.innerHTML = '<tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $gl('gl-sl-overlay').classList.add('open');
    try {
        const params = new URLSearchParams({ account_code: gl.account_code });
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);
        const res  = await gl_authFetch(`${GL_API}/ledger?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (req !== gl_txnReq) return;
        if (!json.success) throw new Error(json.message || 'Failed to load the transactions.');
        const rows = json.data ?? [];
        gl_txnData = { kind: 'txn', gl, json };
        gl_showExport(rows.length > 0);
        $gl('gl-sl-subtitle').textContent = `${rows.length} transaction${rows.length === 1 ? '' : 's'} · ${json.normal_balance}-normal, ${json.account.account_type}`;
        tbody.innerHTML = rows.length
            ? rows.map(r => gl_rowHtml(r, json.debit_normal)).join('') + `<tr class="gl-k-group"><td colspan="4" style="font-weight:700;">Total</td>
                <td class="text-end nha-mono" style="font-weight:700;">${gl_peso(json.total_debit)}</td>
                <td class="text-end nha-mono" style="font-weight:700;">${gl_peso(json.total_credit)}</td>
                <td class="text-end nha-mono" style="font-weight:700;">${gl_peso(Math.abs(json.ending_balance))}${gl_balSide(json.ending_balance, json.debit_normal)}</td></tr>`
            : '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted transactions for this account.</span></div></td></tr>';
    } catch (e) {
        if (req !== gl_txnReq) return;
        tbody.innerHTML = `<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Could not load transactions: ${gl_esc(e.message)}</span></div></td></tr>`;
    }
}
function gl_closeGlPopup() { gl_txnReq++; gl_txnData = null; $gl('gl-sl-overlay')?.classList.remove('open'); }

// shows a row when every ancestor within the popup is open; a node's transaction rows (gl-txn) follow their owner
function gl_applyTree(tbody) {
    tbody = tbody || $gl('gl-sl-tbody');
    const vis = {};
    tbody.querySelectorAll('tr').forEach(tr => {
        const id = tr.dataset.id;
        if (id !== undefined) {
            const p = tr.dataset.parent;
            vis[id] = !(p in vis) || (vis[p] && gl_open[p]);
            tr.style.display = (vis[id] && !tr.classList.contains('gl-self')) ? '' : 'none';
            const t = tr.querySelector('.gl-sl-toggle');
            if (t && tr.classList.contains('gl-clickable')) t.innerHTML = gl_open[id] ? '&#9662;' : '&#9656;';
        } else if (tr.dataset.txnOf !== undefined) {
            tr.style.display = (vis[tr.dataset.txnOf] && gl_open[tr.dataset.txnOf]) ? '' : 'none';
        }
    });
}

async function gl_toggleNode(row) {
    const id = row.dataset.id, n = gl_nodes[id];
    gl_open[id] = !gl_open[id];
    gl_applyTree(row.closest('tbody'));
    if (gl_open[id] && n.own_count > 0 && !row.dataset.txnLoaded) await gl_loadTxns(row, n);
}

// The account's own postings, inserted right under its row (above its sub-accounts when it has any)
async function gl_loadTxns(row, n) {
    row.dataset.txnLoaded = '1';
    try {
        const params = new URLSearchParams({ account_code: n.account_code });
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);
        const res  = await gl_authFetch(`${GL_API}/postings?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the transactions.');
        const label = '';
        const tpl = document.createElement('tbody');
        tpl.innerHTML = label + ((json.data ?? []).length ? json.data.map(r => gl_rowHtml(r, json.debit_normal)).join('') : '<tr><td colspan="7" style="color:var(--afims-text-muted,#62657E);font-size:12px;">No postings.</td></tr>');
        tpl.querySelectorAll('tr').forEach(tr => {
            tr.classList.add('gl-txn');
            tr.dataset.txnOf = n.account_id;
            tr.style.setProperty('--gl-depth', n.depth);
        });
        const tb = row.closest('tbody');
        row.after(...tpl.children);
        gl_applyTree(tb);
    } catch (e) {
        row.dataset.txnLoaded = '';
        gl_showError('Could not load transactions: ' + e.message);
    }
}

function gl_rowHtml(r, debitNormal, groupId) {
    const styles = [];
    if (groupId) styles.push('display:none');
    if (r.reversed_gl_id) styles.push('opacity:.7');
    const attrs = (groupId ? ` class="gl-sl-rows" data-group="${groupId}"` : '') + (styles.length ? ` style="${styles.join(';')}"` : '');
    return `<tr${attrs}>
        <td class="nha-td-date">${gl_fmtDate(r.posting_date)}</td>
        <td class="nha-mono">${gl_esc(r.jev_no) || '—'}${r.reference_no && r.reference_no.endsWith('-REV') ? ' <span class="nha-bdg nha-bdg-orange" style="margin-left:4px;">Reversal</span>' : ''}</td>
        <td class="nha-mono">${gl_esc(r.dv_no) || '—'}</td>
        <td>${r.sl_account_code ? `<span class="nha-mono" style="font-size:11px;color:var(--afims-text-muted,#62657E);margin-right:6px;" title="Posted to this subsidiary ledger">${gl_esc(r.sl_account_code)}</span>` : ''}${gl_esc(r.description) || gl_esc(r.sl_name) || '—'}</td>
        <td class="text-end nha-mono">${Number(r.debit) > 0 ? gl_peso(r.debit) : ''}</td>
        <td class="text-end nha-mono">${Number(r.credit) > 0 ? gl_peso(r.credit) : ''}</td>
        <td class="text-end nha-mono">${gl_peso(Math.abs(r.running_balance))}${gl_balSide(r.running_balance, debitNormal)}</td>
    </tr>`;
}

/* Running/ending balance is signed (debit - credit) for debit-normal accounts, (credit - debit)
   otherwise — see GeneralLedgerService::DEBIT_NORMAL_TYPES. A positive value is always the
   account's NORMAL side, so the Dr/Cr suffix has to flip with debitNormal, not just the sign. */
function gl_balSide(balance, debitNormal) {
    const onDebitSide = debitNormal ? Number(balance) >= 0 : Number(balance) < 0;
    return onDebitSide ? ' Dr' : ' Cr';
}

/* ── Formatters ────────────────────────────────────────────── */
function gl_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function gl_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function gl_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function gl_showError(msg) { $gl('gl-error').style.display = 'flex'; $gl('gl-error-msg').textContent = msg; }
function gl_hideError()    { $gl('gl-error').style.display = 'none'; }
</script>
<?php
// Print → the shared print preview (prp_open + prp_glPayload — GL, NHA Form No. 11; prp_glSlPayload — SL).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
