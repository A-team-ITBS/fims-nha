<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Journals — four special journals, tabbed: GJ (General Journal, every posted line in
// fims.general_ledger via fims.v_journals), CKDJ (Check Disbursements Journal, fims.checks),
// ADADJ (Advice to Debit Account Disbursement Journal, fims.ada_releases) and CRDJ (Cash Receipts
// Journal, fims.official_receipts/official_receipt_lines). Read-only — see
// GeneralLedgerService.php and system_docs/ERD/README.md §2.8. Separate module from General
// Ledger (per-account ledger card), Subsidiary Ledger (per-SL-code ledger card) and Trial Balance
// (totals per account).
$_jr_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_jr_api = preg_match('~/api$~', $_jr_api) ? $_jr_api : $_jr_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #jr-root .jr-tabs { display: flex; gap: 4px; margin: 0 0 14px; border-bottom: 1px solid var(--afims-border, #E2E4ED); }
    #jr-root .jr-tab-btn {
        appearance: none; border: none; background: none; cursor: pointer; padding: 9px 16px;
        font-size: 13px; font-weight: 600; color: var(--afims-text-muted, #62657E);
        border-bottom: 2px solid transparent; margin-bottom: -1px;
    }
    #jr-root .jr-tab-btn.active { color: var(--afims-brand, #1B2B6B); border-bottom-color: var(--afims-brand, #1B2B6B); }
    #jr-root .jr-tab-panel { display: none; }
    #jr-root .jr-tab-panel.active { display: block; }
</style>

<div id="jr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Journals</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="jr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── TABS ───────────────────────────────────────────── -->
    <div class="jr-tabs no-print">
        <button type="button" class="jr-tab-btn active" data-tab="gj">GJ</button>
        <button type="button" class="jr-tab-btn" data-tab="ckdj">CKDJ</button>
        <button type="button" class="jr-tab-btn" data-tab="adadj">ADADJ</button>
        <button type="button" class="jr-tab-btn" data-tab="crdj">CRDJ</button>
    </div>

    <!-- Error banner (shared across tabs) -->
    <div id="jr-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="jr-error-msg">Failed to load data.</span>
    </div>

    <!-- ══ GJ — General Journal ══ -->
    <div id="jr-panel-gj" class="jr-tab-panel active">
        <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
            The book of original entry — every posted Journal Entry Voucher line, in order. Read-only;
            posting happens automatically when Accounting approves a Disbursement Voucher.
        </p>

        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Fiscal Year</label>
                    <select id="jr-filter-fy" class="nha-filter-select">
                        <option value="">All Years</option>
                    </select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Book</label>
                    <select id="jr-filter-book" class="nha-filter-select"><option value="">All Books</option></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Account Code</label>
                    <input type="text" id="jr-filter-account" class="nha-filter-select" placeholder="e.g. 2-01-01-010" style="width:160px;">
                </div>
                <div class="nha-filter-group nha-filter-group-search">
                    <label class="nha-filter-label">Search</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="jr-filter-search" class="nha-filter-input" placeholder="JEV number or DV number…">
                    </div>
                </div>
            </div>
        </div>

        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>Posting Date</th>
                            <th>JEV Number</th>
                            <th>DV Number</th>
                            <th>Account</th>
                            <th>SL Code</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                        </tr>
                    </thead>
                    <tbody id="jr-tbody">
                        <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="jr-from">0</span>–<span id="jr-to">0</span></strong> of <strong><span id="jr-total">0</span></strong></span>
            <div id="jr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ══ CKDJ — Check Disbursements Journal ══ -->
    <div id="jr-panel-ckdj" class="jr-tab-panel">
        <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
            Every check issued against a Disbursement Voucher, oldest first.
        </p>
        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Fiscal Year</label>
                    <select id="ckdj-filter-fy" class="nha-filter-select"><option value="">All Years</option></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Book</label>
                    <select id="ckdj-filter-book" class="nha-filter-select"><option value="">All Books</option></select>
                </div>
                <div class="nha-filter-group nha-filter-group-search">
                    <label class="nha-filter-label">Search</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="ckdj-filter-search" class="nha-filter-input" placeholder="Check number, DV number or payee…">
                    </div>
                </div>
            </div>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>Check Date</th>
                            <th>Check Number</th>
                            <th>DV Number</th>
                            <th>JEV Number</th>
                            <th>Payee</th>
                            <th>Bank Account</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="ckdj-tbody">
                        <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ ADADJ — Advice to Debit Account Disbursement Journal ══ -->
    <div id="jr-panel-adadj" class="jr-tab-panel">
        <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
            Every payment released via Advice to Debit Account instead of a physical check, oldest first.
        </p>
        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Fiscal Year</label>
                    <select id="adadj-filter-fy" class="nha-filter-select"><option value="">All Years</option></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Book</label>
                    <select id="adadj-filter-book" class="nha-filter-select"><option value="">All Books</option></select>
                </div>
                <div class="nha-filter-group nha-filter-group-search">
                    <label class="nha-filter-label">Search</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="adadj-filter-search" class="nha-filter-input" placeholder="ADA number, DV number or payee…">
                    </div>
                </div>
            </div>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>ADA Date</th>
                            <th>ADA Number</th>
                            <th>DV Number</th>
                            <th>JEV Number</th>
                            <th>Payee</th>
                            <th>Region</th>
                            <th>Bank Account</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="adadj-tbody">
                        <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══ CRDJ — Cash Receipts Journal ══ -->
    <div id="jr-panel-crdj" class="jr-tab-panel">
        <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
            Every Official Receipt issued by a collecting officer, oldest first. Not yet posted to
            the General Ledger — a later Journal Entry Voucher journalizes the eventual bank deposit.
        </p>
        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Fiscal Year</label>
                    <select id="crdj-filter-fy" class="nha-filter-select"><option value="">All Years</option></select>
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Book</label>
                    <select id="crdj-filter-book" class="nha-filter-select"><option value="">All Books</option></select>
                </div>
                <div class="nha-filter-group nha-filter-group-search">
                    <label class="nha-filter-label">Search</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="crdj-filter-search" class="nha-filter-input" placeholder="OR number, payor or collecting officer…">
                    </div>
                </div>
            </div>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>OR Date</th>
                            <th>OR Number</th>
                            <th>Collecting Officer</th>
                            <th>Payor</th>
                            <th>Credited Account</th>
                            <th class="text-end">Amount</th>
                            <th class="text-end">OR Total</th>
                        </tr>
                    </thead>
                    <tbody id="crdj-tbody">
                        <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div><!-- /#jr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   JOURNALS   prefix: jr_
═══════════════════════════════════════════════════════════════ */

const JR_BASE = <?= json_encode($_jr_api) ?>;
const JR_API  = JR_BASE + '/general_ledger';
const JR_FY_API = JR_BASE + '/fiscal_year/all';
const JR_BOOK_API = JR_BASE + '/books/all';

const JR_PAGE_SIZE = 25;
let jr_page = 1;
let jr_searchTimer = null;
let jr_activeTab = 'gj';
const jr_tabLoaded = { gj: false, ckdj: false, adadj: false, crdj: false };

const $jr = id => document.getElementById(id);

function jr_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function jr_boot() {
    await Promise.all([
        jr_loadFiscalYears(['jr-filter-fy', 'ckdj-filter-fy', 'adadj-filter-fy', 'crdj-filter-fy']),
        jr_loadBooks(['jr-filter-book', 'ckdj-filter-book', 'adadj-filter-book', 'crdj-filter-book']),
    ]);
    jr_bindEvents();
    jr_load();
    jr_tabLoaded.gj = true;
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', jr_boot);
else jr_boot();

function jr_bindEvents() {
    document.querySelectorAll('.jr-tab-btn').forEach(btn => {
        btn.addEventListener('click', () => jr_switchTab(btn.dataset.tab));
    });

    $jr('jr-refresh')?.addEventListener('click', () => jr_reloadActiveTab());
    $jr('jr-filter-fy')?.addEventListener('change', () => { jr_page = 1; jr_load(); });
    $jr('jr-filter-book')?.addEventListener('change', () => { jr_page = 1; jr_load(); });
    $jr('jr-filter-account')?.addEventListener('input', () => {
        clearTimeout(jr_searchTimer);
        jr_searchTimer = setTimeout(() => { jr_page = 1; jr_load(); }, 400);
    });
    $jr('jr-filter-search')?.addEventListener('input', () => {
        clearTimeout(jr_searchTimer);
        jr_searchTimer = setTimeout(() => { jr_page = 1; jr_load(); }, 400);
    });

    $jr('ckdj-filter-fy')?.addEventListener('change', ckdj_load);
    $jr('ckdj-filter-book')?.addEventListener('change', ckdj_load);
    $jr('ckdj-filter-search')?.addEventListener('input', () => { clearTimeout(jr_searchTimer); jr_searchTimer = setTimeout(ckdj_load, 400); });
    $jr('adadj-filter-fy')?.addEventListener('change', adadj_load);
    $jr('adadj-filter-book')?.addEventListener('change', adadj_load);
    $jr('adadj-filter-search')?.addEventListener('input', () => { clearTimeout(jr_searchTimer); jr_searchTimer = setTimeout(adadj_load, 400); });
    $jr('crdj-filter-fy')?.addEventListener('change', crdj_load);
    $jr('crdj-filter-book')?.addEventListener('change', crdj_load);
    $jr('crdj-filter-search')?.addEventListener('input', () => { clearTimeout(jr_searchTimer); jr_searchTimer = setTimeout(crdj_load, 400); });
}

/* ── Tabs ──────────────────────────────────────────────────── */
function jr_switchTab(tab) {
    jr_activeTab = tab;
    document.querySelectorAll('.jr-tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.jr-tab-panel').forEach(p => p.classList.toggle('active', p.id === `jr-panel-${tab}`));
    jr_hideError();
    if (!jr_tabLoaded[tab]) {
        jr_tabLoaded[tab] = true;
        jr_reloadActiveTab();
    }
}
function jr_reloadActiveTab() {
    if (jr_activeTab === 'gj') { jr_page = 1; jr_load(); }
    else if (jr_activeTab === 'ckdj') ckdj_load();
    else if (jr_activeTab === 'adadj') adadj_load();
    else if (jr_activeTab === 'crdj') crdj_load();
}

/* Fiscal years come from fims.fiscal_year via GET /fiscal_year/all → {success, data:[{fiscal_yr}]}. One fetch, reused to populate every tab's fiscal-year <select>. */
async function jr_loadFiscalYears(selectIds) {
    try {
        const res  = await jr_authFetch(JR_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        const options = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${jr_esc(r.fiscal_yr)}">${jr_esc(r.fiscal_yr)}</option>`).join('');
        selectIds.forEach(id => { const sel = $jr(id); if (sel) sel.innerHTML = options; });
    } catch (e) {
        jr_showError('Fiscal years: ' + e.message);
    }
}

/* Books come from fims.books via GET /books/all → {success, data:[{book_id, code, name}]}. One fetch, reused to populate every tab's Book <select>. */
async function jr_loadBooks(selectIds) {
    try {
        const res  = await jr_authFetch(JR_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        const options = '<option value="">All Books</option>' +
            (json.data ?? []).map(r => `<option value="${jr_esc(r.book_id)}">${jr_esc(r.name)}</option>`).join('');
        selectIds.forEach(id => { const sel = $jr(id); if (sel) sel.innerHTML = options; });
    } catch (e) {
        jr_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function jr_load() {
    window.PagePreloader?.show();
    jr_hideError();
    try {
        const search = $jr('jr-filter-search').value.trim();
        const params = new URLSearchParams({
            page            : jr_page,
            limit           : JR_PAGE_SIZE,
            fiscal_yr       : $jr('jr-filter-fy').value,
            book_id : $jr('jr-filter-book').value,
            account_code    : $jr('jr-filter-account').value.trim(),
        });
        // One free-text box covers both jev_no and dv_no — try dv_no only if it looks like one
        // (JEV numbers are JEV-..., DV numbers are not), otherwise send it as jev_no.
        if (search) params.set(/^JEV-/i.test(search) ? 'jev_no' : 'dv_no', search);

        const res  = await jr_authFetch(`${JR_API}/journals?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Journals register.');

        jr_renderTable(json.data ?? []);
        jr_renderPagination(json.pagination ?? {});
    } catch (e) {
        jr_renderTable([]);
        jr_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function jr_renderTable(rows) {
    const tbody = $jr('jr-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted journal entries found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
        <td class="nha-td-date">${jr_fmtDate(r.posting_date)}</td>
        <td class="nha-mono">${jr_esc(r.jev_no) || '—'}</td>
        <td class="nha-mono">${jr_esc(r.dv_no) || '—'}</td>
        <td title="${jr_esc(r.account_title)}">${jr_esc(r.account_code)} ${r.account_title ? '— ' + jr_esc(r.account_title) : ''}</td>
        <td>${jr_esc(r.sl_code) || '—'}</td>
        <td class="text-end nha-mono">${Number(r.debit) > 0 ? jr_peso(r.debit) : ''}</td>
        <td class="text-end nha-mono">${Number(r.credit) > 0 ? jr_peso(r.credit) : ''}</td>
    </tr>`).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function jr_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = JR_PAGE_SIZE } = p;
    $jr('jr-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $jr('jr-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $jr('jr-total').textContent = total;

    const box = $jr('jr-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="jr_goTo(${i})">${i}</button>`);
    }
    box.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="jr_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="jr_goTo(${current_page + 1})">›</button>`;
}
function jr_goTo(page) { if (page >= 1) { jr_page = page; jr_load(); } }

/* ── Formatters ────────────────────────────────────────────── */
function jr_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function jr_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function jr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function jr_showError(msg) { $jr('jr-error').style.display = 'flex'; $jr('jr-error-msg').textContent = msg; }
function jr_hideError()    { $jr('jr-error').style.display = 'none'; }

/* ══════════════════════════════════════════════════════════════
   CKDJ — Check Disbursements Journal   prefix: ckdj_
═══════════════════════════════════════════════════════════════ */
async function ckdj_load() {
    window.PagePreloader?.show();
    jr_hideError();
    try {
        const params = new URLSearchParams();
        const fy = $jr('ckdj-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $jr('ckdj-filter-book').value;
        if (book) params.set('book_id', book);
        const search = $jr('ckdj-filter-search').value.trim();
        if (search) params.set('search', search);

        const res  = await jr_authFetch(`${JR_API}/ckdj?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Check Disbursements Journal.');

        ckdj_renderTable(json.data ?? []);
    } catch (e) {
        ckdj_renderTable([]);
        jr_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
function ckdj_renderTable(rows) {
    const tbody = $jr('ckdj-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No checks issued.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
        <td class="nha-td-date">${jr_fmtDate(r.check_date)}</td>
        <td class="nha-mono">${jr_esc(r.check_number) || '—'}</td>
        <td class="nha-mono">${jr_esc(r.dv_number) || '—'}</td>
        <td class="nha-mono">${jr_esc(r.jev_no) || '—'}</td>
        <td>${jr_esc(r.payee) || '—'}</td>
        <td>${jr_esc(r.bank_account_name) || '—'}${r.bank_account_number ? ' (' + jr_esc(r.bank_account_number) + ')' : ''}</td>
        <td class="text-end nha-mono">${jr_peso(r.amount)}</td>
        <td>${jr_esc(r.status) || '—'}</td>
    </tr>`).join('');
}

/* ══════════════════════════════════════════════════════════════
   ADADJ — Advice to Debit Account Disbursement Journal   prefix: adadj_
═══════════════════════════════════════════════════════════════ */
async function adadj_load() {
    window.PagePreloader?.show();
    jr_hideError();
    try {
        const params = new URLSearchParams();
        const fy = $jr('adadj-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $jr('adadj-filter-book').value;
        if (book) params.set('book_id', book);
        const search = $jr('adadj-filter-search').value.trim();
        if (search) params.set('search', search);

        const res  = await jr_authFetch(`${JR_API}/adadj?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Advice to Debit Account Disbursement Journal.');

        adadj_renderTable(json.data ?? []);
    } catch (e) {
        adadj_renderTable([]);
        jr_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
function adadj_renderTable(rows) {
    const tbody = $jr('adadj-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No ADAs released.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
        <td class="nha-td-date">${jr_fmtDate(r.ada_date)}</td>
        <td class="nha-mono">${jr_esc(r.ada_no) || '—'}</td>
        <td class="nha-mono">${jr_esc(r.dv_number) || '—'}</td>
        <td class="nha-mono">${jr_esc(r.jev_no) || '—'}</td>
        <td>${jr_esc(r.payee) || '—'}</td>
        <td>${jr_esc(r.region) || '—'}</td>
        <td>${jr_esc(r.bank_account_name) || '—'}${r.bank_account_number ? ' (' + jr_esc(r.bank_account_number) + ')' : ''}</td>
        <td class="text-end nha-mono">${jr_peso(r.amount)}</td>
        <td>${jr_esc(r.status) || '—'}</td>
    </tr>`).join('');
}

/* ══════════════════════════════════════════════════════════════
   CRDJ — Cash Receipts Journal   prefix: crdj_
   One API row per credited (SUNDRY) line; grouped here so an OR's header
   cells (date/number/officer/payor/total) only print once, on its first line.
═══════════════════════════════════════════════════════════════ */
async function crdj_load() {
    window.PagePreloader?.show();
    jr_hideError();
    try {
        const params = new URLSearchParams();
        const fy = $jr('crdj-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $jr('crdj-filter-book').value;
        if (book) params.set('book_id', book);
        const search = $jr('crdj-filter-search').value.trim();
        if (search) params.set('search', search);

        const res  = await jr_authFetch(`${JR_API}/crdj?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Cash Receipts Journal.');

        crdj_renderTable(json.data ?? []);
    } catch (e) {
        crdj_renderTable([]);
        jr_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
function crdj_renderTable(rows) {
    const tbody = $jr('crdj-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No receipts issued.</span></div></td></tr>';
        return;
    }
    let lastReceiptId = null;
    tbody.innerHTML = rows.map(r => {
        const isFirstLine = r.receipt_id !== lastReceiptId;
        lastReceiptId = r.receipt_id;
        return `<tr${isFirstLine ? ' style="border-top:2px solid var(--afims-border,#E2E4ED);"' : ''}>
            <td class="nha-td-date">${isFirstLine ? jr_fmtDate(r.or_date) : ''}</td>
            <td class="nha-mono">${isFirstLine ? (jr_esc(r.or_no) || '—') : ''}</td>
            <td>${isFirstLine ? (jr_esc(r.collecting_officer) || '—') : ''}</td>
            <td>${isFirstLine ? (jr_esc(r.payor_name) || '—') : ''}</td>
            <td title="${jr_esc(r.credit_account_title)}">${jr_esc(r.credit_account_code)}${r.credit_account_title ? ' — ' + jr_esc(r.credit_account_title) : ''}</td>
            <td class="text-end nha-mono">${jr_peso(r.amount)}</td>
            <td class="text-end nha-mono">${isFirstLine ? jr_peso(r.total_amount) : ''}</td>
        </tr>`;
    }).join('');
}
</script>
