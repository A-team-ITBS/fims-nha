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

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
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
    /* Clickable GL account header rows (open the SL popup) */
    #gl-root .gl-group-row.gl-clickable { cursor: pointer; }
    #gl-root .gl-group-row.gl-clickable:hover td { filter: brightness(.96); }
    #gl-root .gl-group-row .gl-drill { font-weight: 400; font-size: 11px; margin-left: 6px; color: var(--afims-primary, #1D4ED8); }
    /* SL popup */
    #gl-root .gl-sl-modal-sub { font-size: 12px; opacity: .8; margin-top: 2px; font-weight: 400; }
    #gl-root .gl-sl-sub-row td { background: var(--afims-bg-soft, #F5F6FA); font-weight: 600; }
    #gl-root .gl-sl-sub-row.gl-clickable { cursor: pointer; }
    #gl-root .gl-sl-sub-row.gl-clickable:hover td { filter: brightness(.96); }
    #gl-root .gl-sl-toggle { display: inline-block; width: 12px; margin-right: 2px; color: var(--afims-text-muted, #62657E); }

    /* Dark mode: --afims-bg-soft is not a theme token, so the account group rows fell back to a near-white #F5F6FA (light text on
       it = unreadable); the account search results and the summary cards were hard-coded white */
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-group-row td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; border-top: 1px solid var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-group-row td span { color: var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #gl-root :is(.gl-combo-results, .gl-summary-card) { background: var(--afims-surface, #1A1F38); border-color: var(--afims-border-strong, #4A5270); color: var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-combo-item:hover { background: var(--afims-primary-soft, #0F2456); color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #gl-root :is(.gl-summary-label, .gl-combo-empty, .gl-ending-balance) { color: var(--afims-text-muted, #AAAFBE); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-sl-sub-row td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; border-top: 1px solid var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-sl-sub-row td span { color: var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-group-row .gl-drill { color: #8FB4FF; }

    /* ── Page header: copied from User Access (.app-page-header in app/fims-css/fims.css — that file is not loaded here,
          so the rules are repeated, scoped to this page). Stays navy in dark mode too, like User Access. ── */
    #gl-root .app-page-header { position:relative;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;overflow:hidden;
        background:linear-gradient(135deg, #000E3E 0%, #001A5E 100%);border-radius:.75rem;padding:1.5rem 1.75rem;margin-bottom:1.5rem; }
    #gl-root .app-page-header::before,
    #gl-root .app-page-header::after { content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none; }
    #gl-root .app-page-header::before { width:180px;height:180px;top:-90px;right:80px; }
    #gl-root .app-page-header::after { width:120px;height:120px;bottom:-70px;right:-20px;background:rgba(255,255,255,.06); }
    #gl-root .app-page-header-icon { position:relative;z-index:1;flex:0 0 auto;width:3rem;height:3rem;border-radius:.5rem;
        display:flex;align-items:center;justify-content:center;font-size:1.25rem;
        background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#FFFFFF; }
    #gl-root .app-page-title { position:relative;z-index:1;font-size:1.6rem;font-weight:800;letter-spacing:-.01em;color:#FFFFFF;margin:0 0 .25rem; }
    #gl-root .app-page-subtitle { position:relative;z-index:1;font-size:.9rem;color:rgba(255,255,255,.75);margin:0;max-width:640px; }
    /* the Refresh / Print buttons stay on the right of the banner, above its decorative circles */
    #gl-root .app-page-header-actions { position:relative;z-index:1;margin-left:auto;display:flex;flex-wrap:wrap;gap:8px; }
</style>

<div id="gl-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in app/fims-css/fims.css) -->
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
                    <input type="text" id="gl-account-search" class="nha-filter-input" style="width:100%;"
                           placeholder="Search account code or title…" autocomplete="off">
                    <div id="gl-account-results" class="gl-combo-results"></div>
                </div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="gl-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book</label>
                <select id="gl-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
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
         account row is collapsed by default (see gl_toggleGlRows); hidden once one account is picked. -->
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
                <thead>
                    <tr>
                        <th>Posting Date</th>
                        <th>JEV No.</th>
                        <th>DV Number</th>
                        <th>Particulars</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody id="gl-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="gl-ending" class="gl-ending-balance no-print" style="display:none;"></div>

    <style>#gl-print-menu .gl-print-opt:hover:not(:disabled),#gl-print-menu .gl-print-sheet:hover{background:var(--afims-surface-subtle,#F4F7FA);}#gl-print-menu .gl-print-sheet{display:flex;justify-content:space-between;gap:12px;width:100%;text-align:left;background:none;border:0;border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--afims-text,#1e293b);font:inherit;font-size:13px;}#gl-print-menu .gl-print-sheet small{color:var(--afims-text-muted,#62657E);font-size:11px;white-space:nowrap;}#gl-print-menu .gl-print-note{padding:6px 10px;font-size:12px;color:var(--afims-text-muted,#62657E);}#gl-print-menu .gl-print-opt:disabled{opacity:.55;cursor:not-allowed !important;}</style>
    <!-- ── SL POPUP (click a GL account header row → its SL accounts, each with its JEVs) ── -->
    <div id="gl-sl-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="gl-sl-title">
            <div class="nha-modal-hdr">
                <div>
                    <h3 class="nha-modal-title" id="gl-sl-title">Subsidiary Ledgers</h3>
                    <div class="gl-sl-modal-sub" id="gl-sl-subtitle"></div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="nha-btn nha-btn-outline" id="gl-sl-print" title="Print the Subsidiary Ledger sheet of each SL with postings" style="display:none;">Print SL</button>
                    <button type="button" class="nha-modal-close" id="gl-sl-close" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="nha-modal-body">
                <div class="nha-table-wrap">
                    <div class="nha-tbl-scroll">
                        <table class="nha-tbl">
                            <thead>
                                <tr>
                                    <th>Posting Date</th>
                                    <th>JEV No.</th>
                                    <th>DV Number</th>
                                    <th>Particulars</th>
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
let gl_slReq = 0;              // guards the SL popup against out-of-order responses
let gl_ledger = null;          // the last single-account ledger loaded (what Print prints); null in the all-accounts view

const $gl = id => document.getElementById(id);

function gl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function gl_boot() {
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
    $gl('gl-sl-print')?.addEventListener('click', () => gl_printSlPopup());
    $gl('gl-filter-fy')?.addEventListener('change', () => gl_load());
    $gl('gl-filter-book')?.addEventListener('change', () => gl_load());

    // Grouped (no-account-picked) view: click "View SLs" to open the SL drill-down popup;
    // click anywhere else on the header row to toggle its own transactions open/closed.
    $gl('gl-tbody')?.addEventListener('click', e => {
        const row = e.target.closest('tr.gl-clickable');
        if (!row) return;
        if (e.target.closest('.gl-drill')) gl_openSlPopup(row.dataset.code, row.dataset.title);
        else gl_toggleGlRows(row);
    });
    $gl('gl-expand-all')?.addEventListener('click', () => gl_setAllGlRows(true));
    $gl('gl-collapse-all')?.addEventListener('click', () => gl_setAllGlRows(false));
    $gl('gl-sl-close')?.addEventListener('click', gl_closeSlPopup);
    $gl('gl-sl-overlay')?.addEventListener('click', e => { if (e.target.id === 'gl-sl-overlay') gl_closeSlPopup(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') gl_closeSlPopup(); });

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

        const res  = await gl_authFetch(`${GL_API}/ledger?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the General Ledger.');

        gl_setPrintable(json.grouped ? null : json);
        if (json.grouped) {
            $gl('gl-summary').style.display = 'none';
            $gl('gl-ending').style.display = 'none';
            $gl('gl-expand-toolbar').style.display = 'flex';
            gl_renderGroupedTable(json.accounts ?? []);
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
// SL popup's Print SL — the Subsidiary Ledger sheets of the data the popup loaded (same filters).
async function gl_printSlPopup() {
    if (!gl_slJson || typeof prp_open !== 'function') return;
    await prp_open(prp_glSlPayload(gl_slJson, gl_printOpts()), { type: 'SL' });
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

/* Default view (no account picked): every account's ledger, one sub-header + its lines each.
   Collapsed by default — only the account header (with its totals/balance) shows; clicking it
   toggles its transaction rows open/closed (accordion, same convention as the SL popup's
   gl_toggleSlRows). Clicking "View SLs" instead opens the SL drill-down popup — gl_bindEvents
   checks for that target first so it doesn't also toggle the row. */
function gl_renderGroupedTable(accounts) {
    const tbody = $gl('gl-tbody');
    if (!accounts.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = accounts.map((a, idx) => {
        const gid = `gl-grp-${idx}`;
        return `
        <tr class="gl-group-row gl-clickable" data-code="${gl_esc(a.account.account_code)}" data-title="${gl_esc(a.account.account_title)}" data-group="${gid}" title="Click to see this account's transactions">
            <td colspan="4"><span class="gl-sl-toggle">&#9656;</span>${gl_esc(a.account.account_code)} — ${gl_esc(a.account.account_title)} <span style="font-weight:400;color:var(--afims-text-muted,#62657E);">(${gl_esc(a.normal_balance)}-normal, ${gl_esc(a.account.account_type)})</span><span class="gl-drill">View SLs ›</span></td>
            <td class="text-end nha-mono">${gl_peso(a.total_debit)}</td>
            <td class="text-end nha-mono">${gl_peso(a.total_credit)}</td>
            <td class="text-end nha-mono">${gl_peso(Math.abs(a.ending_balance))}${gl_balSide(a.ending_balance, a.debit_normal)}</td>
        </tr>
        ${a.data.length
            ? a.data.map(r => gl_rowHtml(r, a.debit_normal, gid)).join('')
            : `<tr class="gl-sl-rows" data-group="${gid}" style="display:none;"><td colspan="7" style="color:var(--afims-text-muted,#62657E);font-size:12px;">No postings.</td></tr>`}
    `;
    }).join('');
}

/* ── SL popup — a GL account's SL accounts (GET /general_ledger/ledger-sl), each with its JEV lines ──
   Same per-SL header + JEV rows + running balance as the Subsidiary Ledger page, scoped to one GL account
   and the page's current Fiscal Year / Book filters. */
let gl_slJson = null;   // the SL popup's loaded GET /general_ledger/ledger-sl response (what its Print prints)
async function gl_openSlPopup(code, title) {
    const req = ++gl_slReq;
    gl_slJson = null;
    if ($gl('gl-sl-print')) $gl('gl-sl-print').style.display = 'none';
    const tbody = $gl('gl-sl-tbody');
    $gl('gl-sl-title').textContent = `${code} — ${title}`;
    $gl('gl-sl-subtitle').textContent = 'Subsidiary ledgers';
    tbody.innerHTML = '<tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $gl('gl-sl-overlay').classList.add('open');
    try {
        const params = new URLSearchParams({ account_code: code });
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);

        const res  = await gl_authFetch(`${GL_API}/ledger-sl?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (req !== gl_slReq) return; // a newer popup request superseded this one
        if (!json.success) throw new Error(json.message || 'Failed to load the sub-ledgers.');
        gl_renderSlPopup(json);
    } catch (e) {
        if (req !== gl_slReq) return;
        tbody.innerHTML = `<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Could not load sub-ledgers: ${gl_esc(e.message)}</span></div></td></tr>`;
    }
}

// Collapsed by default — only the SL (and "direct postings") summary rows show, each with its own
// totals/balance. Its transaction rows stay in the DOM (already fetched, no second request) but
// hidden via gl_rowHtml's groupId param, and only reveal when that row is clicked (gl_toggleSlRows).
function gl_renderSlPopup(json) {
    const tbody = $gl('gl-sl-tbody');
    gl_slJson = json;
    // hidden while the Subsidiary Ledger print is paused (PRP_PAUSED.SL)
    if ($gl('gl-sl-print')) $gl('gl-sl-print').style.display = (typeof prp_isPaused === 'function' && prp_isPaused('SL')) ? 'none' : '';
    const sls = json.sub_ledgers ?? [];
    const direct = json.direct && (json.direct.data ?? []).length ? json.direct : null;
    $gl('gl-sl-subtitle').textContent = `${sls.length} subsidiary ledger${sls.length === 1 ? '' : 's'} · ${json.normal_balance}-normal, ${json.account.account_type} · click a sub-ledger to see its transactions`;
    if (!sls.length && !direct) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">This account has no subsidiary ledgers and no postings.</span></div></td></tr>';
        return;
    }
    let gl_slGroupSeq = 0;
    const directHtml = direct ? (() => {
        const gid = `gl-slg-${++gl_slGroupSeq}`;
        return `
        <tr class="gl-sl-sub-row gl-clickable" onclick="gl_toggleSlRows(this, '${gid}')">
            <td colspan="4"><span class="gl-sl-toggle">&#9656;</span> Direct postings to ${gl_esc(json.account.account_code)} <span style="font-weight:400;color:var(--afims-text-muted,#62657E);">(not under a subsidiary ledger)</span></td>
            <td class="text-end nha-mono">${gl_peso(direct.total_debit)}</td>
            <td class="text-end nha-mono">${gl_peso(direct.total_credit)}</td>
            <td class="text-end nha-mono">${gl_peso(Math.abs(direct.ending_balance))}${gl_balSide(direct.ending_balance, json.debit_normal)}</td>
        </tr>
        ${direct.data.map(r => gl_rowHtml(r, json.debit_normal, gid)).join('')}`;
    })() : '';
    const noSlNote = !sls.length ? '<tr><td colspan="7" style="color:var(--afims-text-muted,#62657E);font-size:12px;">This account has no subsidiary ledgers.</td></tr>' : '';
    tbody.innerHTML = directHtml + noSlNote + sls.map(s => {
        const gid = `gl-slg-${++gl_slGroupSeq}`;
        return `
        <tr class="gl-sl-sub-row gl-clickable" onclick="gl_toggleSlRows(this, '${gid}')">
            <td colspan="4"><span class="gl-sl-toggle">&#9656;</span> ${gl_esc(s.sl.account_code)} — ${gl_esc(s.sl.account_title)}</td>
            <td class="text-end nha-mono">${gl_peso(s.total_debit)}</td>
            <td class="text-end nha-mono">${gl_peso(s.total_credit)}</td>
            <td class="text-end nha-mono">${gl_peso(Math.abs(s.ending_balance))}${gl_balSide(s.ending_balance, json.debit_normal)}</td>
        </tr>
        ${s.data.length
            ? s.data.map(r => gl_rowHtml(r, json.debit_normal, gid)).join('')
            : `<tr class="gl-sl-rows" data-group="${gid}" style="display:none;"><td colspan="7" style="color:var(--afims-text-muted,#62657E);font-size:12px;">No postings.</td></tr>`}
    `;
    }).join('');
}

// Toggles just ONE account's transaction rows open/closed — not an accordion (unlike
// gl_toggleSlRows), since Expand All (gl_setAllGlRows) needs every account open at once.
function gl_toggleGlRows(headerRow) {
    gl_setGlRowOpen(headerRow, headerRow.dataset.open !== '1');
}
function gl_setGlRowOpen(headerRow, open) {
    const gid = headerRow.dataset.group;
    headerRow.dataset.open = open ? '1' : '0';
    headerRow.querySelector('.gl-sl-toggle').innerHTML = open ? '&#9662;' : '&#9656;';
    $gl('gl-tbody').querySelectorAll(`.gl-sl-rows[data-group="${gid}"]`).forEach(row => {
        row.style.display = open ? '' : 'none';
    });
}
function gl_setAllGlRows(open) {
    $gl('gl-tbody').querySelectorAll('.gl-group-row').forEach(row => gl_setGlRowOpen(row, open));
}

// Toggles one SL's (or "direct postings") transaction rows open/closed — accordion-style, so only
// one group is expanded at a time, matching how a paper subsidiary ledger is paged through.
function gl_toggleSlRows(headerRow, gid) {
    const opening = headerRow.dataset.open !== '1';
    $gl('gl-sl-tbody').querySelectorAll('.gl-sl-sub-row').forEach(row => {
        row.dataset.open = '0';
        row.querySelector('.gl-sl-toggle').innerHTML = '&#9656;';
    });
    $gl('gl-sl-tbody').querySelectorAll('.gl-sl-rows').forEach(row => {
        row.style.display = (opening && row.dataset.group === gid) ? '' : 'none';
    });
    if (opening) {
        headerRow.dataset.open = '1';
        headerRow.querySelector('.gl-sl-toggle').innerHTML = '&#9662;';
    }
}

function gl_closeSlPopup() {
    gl_slReq++; // drop any in-flight response
    $gl('gl-sl-overlay')?.classList.remove('open');
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
