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

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #tb-root .tb-report-title { text-align: center; margin: 0 0 18px; }
    #tb-root .tb-report-title div { font-weight: 700; font-size: 14px; line-height: 1.6; }
    #tb-root .tb-report-title .tb-report-date { font-weight: 400; }
    #tb-root .tb-major-row td { background: #1B2B6B; color: #fff; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    #tb-root .tb-group-row td { background: var(--afims-bg-soft, #F5F6FA); font-weight: 600; text-transform: uppercase; font-size: 12px; }
    #tb-root .tb-account-row td:first-child { padding-left: 28px; }
</style>

<div id="tb-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Trial Balance</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="tb-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
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
                <span id="tb-badge" class="nha-bdg nha-bdg-gray">—</span>
            </div>
        </div>
    </div>

    <!-- ── REPORT TITLE ───────────────────────────────────── -->
    <div class="tb-report-title">
        <div>NATIONAL HOUSING AUTHORITY</div>
        <div>TRIAL BALANCE<span id="tb-report-book"></span></div>
        <div class="tb-report-date">As of <span id="tb-report-date">—</span></div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Account Title</th>
                        <th>Acct Code</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                    </tr>
                </thead>
                <tbody id="tb-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
                <tfoot>
                    <tr style="font-weight:600;">
                        <td colspan="2">Total</td>
                        <td class="text-end nha-mono" id="tb-total-debit">₱ 0.00</td>
                        <td class="text-end nha-mono" id="tb-total-credit">₱ 0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

</div><!-- /#tb-root -->

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
function tb_render(json) {
    const rows  = json.data ?? [];
    const tbody = $tb('tb-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
    } else {
        let lastMajor = null, lastGroup = null;
        tbody.innerHTML = rows.map(r => {
            let header = '';
            if (r.major_group !== lastMajor) {
                header += `<tr class="tb-major-row"><td colspan="4">${tb_esc(r.major_group)}</td></tr>`;
                lastGroup = null; // a new major group always restarts its own sub-groups
            }
            if (r.account_group && r.account_group !== lastGroup) {
                header += `<tr class="tb-group-row"><td colspan="4">${tb_esc(r.account_group)}</td></tr>`;
            }
            lastMajor = r.major_group;
            lastGroup = r.account_group;

            return header + `<tr class="tb-account-row">
                <td>${tb_esc(r.account_title) || '—'}</td>
                <td class="nha-mono">${tb_esc(r.account_code)}</td>
                <td class="text-end nha-mono">${Number(r.total_debit) > 0 ? tb_peso(r.total_debit) : ''}</td>
                <td class="text-end nha-mono">${Number(r.total_credit) > 0 ? tb_peso(r.total_credit) : ''}</td>
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

/* ── Formatters ────────────────────────────────────────────── */
function tb_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
