<?php
namespace PHPMaker2024\FIMS;
@session_start();

// General Ledger — read-only reports over fims.general_ledger (see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8). Posting happens only inside
// DisbursementVoucherService::changeStatus() (Accounting approving a DV moves its Journal Entry
// Voucher to FOR_TREASURY and posts it here) — nothing on this page writes anything.
// Two tabs: Journals (fims.v_journals, paged) and Trial Balance (fims.v_trial_balance, by fiscal year).
$_gl_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_gl_api = preg_match('~/api$~', $_gl_api) ? $_gl_api : $_gl_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="gl-root" class="nha-root">

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
                <div class="nha-hdr-title">General Ledger</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="gl-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        A Journal Entry Voucher is posted here the moment Accounting approves its Disbursement Voucher
        (For Accounting → For Treasury). Nothing on this page is editable — Journals is the book of
        original entry, Trial Balance is debits vs. credits per account.
    </p>

    <!-- Error banner -->
    <div id="gl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="gl-error-msg">Failed to load data.</span>
    </div>

    <!-- ── TABS ───────────────────────────────────────────── -->
    <div class="nha-tabs no-print" role="tablist" aria-label="Report">
        <button type="button" class="nha-tab active" id="gl-tab-journals" data-tab="JOURNALS" role="tab" aria-selected="true">Journals</button>
        <button type="button" class="nha-tab" id="gl-tab-tb" data-tab="TRIAL_BALANCE" role="tab" aria-selected="false">Trial Balance</button>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="gl-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group" id="gl-filter-account-wrap">
                <label class="nha-filter-label">Account Code</label>
                <input type="text" id="gl-filter-account" class="nha-filter-select" placeholder="e.g. 2-01-01-010" style="width:160px;">
            </div>
            <div class="nha-filter-group nha-filter-group-search" id="gl-filter-search-wrap">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="gl-filter-search" class="nha-filter-input" placeholder="JEV number or DV number…">
                </div>
            </div>
        </div>
    </div>

    <!-- ── JOURNALS TAB ───────────────────────────────────── -->
    <div id="gl-panel-journals" class="nha-tab-panel">
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
                    <tbody id="gl-journals-tbody">
                        <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="gl-from">0</span>–<span id="gl-to">0</span></strong> of <strong><span id="gl-total">0</span></strong></span>
            <div id="gl-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ── TRIAL BALANCE TAB ──────────────────────────────── -->
    <div id="gl-panel-tb" class="nha-tab-panel" style="display:none;">
        <div style="margin:0 0 12px;">
            <span id="gl-tb-badge" class="nha-bdg nha-bdg-gray">—</span>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Title</th>
                            <th>Account Type</th>
                            <th class="text-end">Total Debit</th>
                            <th class="text-end">Total Credit</th>
                            <th class="text-end">Balance</th>
                        </tr>
                    </thead>
                    <tbody id="gl-tb-tbody">
                        <tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:600;">
                            <td colspan="3">Total</td>
                            <td class="text-end nha-mono" id="gl-tb-total-debit">₱ 0.00</td>
                            <td class="text-end nha-mono" id="gl-tb-total-credit">₱ 0.00</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
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
const GL_FY_API = GL_BASE + '/fiscal_year/all';

const GL_PAGE_SIZE = 25;
let gl_page = 1;
let gl_tab  = 'JOURNALS';
let gl_searchTimer = null;

const $gl = id => document.getElementById(id);

function gl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function gl_boot() {
    await gl_loadFiscalYears();
    gl_bindEvents();
    gl_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', gl_boot);
else gl_boot();

function gl_bindEvents() {
    document.querySelectorAll('#gl-root .nha-tab').forEach(b => b.addEventListener('click', () => gl_switchTab(b.dataset.tab)));
    $gl('gl-refresh')?.addEventListener('click', () => { gl_page = 1; gl_load(); });
    $gl('gl-filter-fy')?.addEventListener('change', () => { gl_page = 1; gl_load(); });
    $gl('gl-filter-account')?.addEventListener('input', () => {
        clearTimeout(gl_searchTimer);
        gl_searchTimer = setTimeout(() => { gl_page = 1; gl_load(); }, 400);
    });
    $gl('gl-filter-search')?.addEventListener('input', () => {
        clearTimeout(gl_searchTimer);
        gl_searchTimer = setTimeout(() => { gl_page = 1; gl_load(); }, 400);
    });
}

function gl_switchTab(tab) {
    gl_tab = tab;
    document.querySelectorAll('#gl-root .nha-tab').forEach(b => {
        const active = b.dataset.tab === tab;
        b.classList.toggle('active', active);
        b.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    $gl('gl-panel-journals').style.display = tab === 'JOURNALS' ? '' : 'none';
    $gl('gl-panel-tb').style.display       = tab === 'TRIAL_BALANCE' ? '' : 'none';
    // Trial Balance has no Account Code / Search filters (fetchTrialBalance only takes fiscal_yr).
    $gl('gl-filter-account-wrap').style.display = tab === 'JOURNALS' ? '' : 'none';
    $gl('gl-filter-search-wrap').style.display  = tab === 'JOURNALS' ? '' : 'none';
    gl_page = 1;
    gl_load();
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

/* ── Load ──────────────────────────────────────────────────── */
function gl_load() {
    return gl_tab === 'JOURNALS' ? gl_loadJournals() : gl_loadTrialBalance();
}

async function gl_loadJournals() {
    window.PagePreloader?.show();
    gl_hideError();
    try {
        const search = $gl('gl-filter-search').value.trim();
        const params = new URLSearchParams({
            page         : gl_page,
            limit        : GL_PAGE_SIZE,
            fiscal_yr    : $gl('gl-filter-fy').value,
            account_code : $gl('gl-filter-account').value.trim(),
        });
        // One free-text box covers both jev_no and dv_no — try dv_no only if it looks like one
        // (JEV numbers are JEV-..., DV numbers are not), otherwise send it as jev_no.
        if (search) params.set(/^JEV-/i.test(search) ? 'jev_no' : 'dv_no', search);

        const res  = await gl_authFetch(`${GL_API}/journals?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Journals register.');

        gl_renderJournals(json.data ?? []);
        gl_renderPagination(json.pagination ?? {});
    } catch (e) {
        gl_renderJournals([]);
        gl_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function gl_loadTrialBalance() {
    window.PagePreloader?.show();
    gl_hideError();
    try {
        const params = new URLSearchParams();
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);

        const res  = await gl_authFetch(`${GL_API}/trial-balance?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Trial Balance.');

        gl_renderTrialBalance(json);
    } catch (e) {
        gl_renderTrialBalance({ data: [], total_debit: 0, total_credit: 0, balanced: true });
        gl_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Journals table ────────────────────────────────────────── */
function gl_renderJournals(rows) {
    const tbody = $gl('gl-journals-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted journal entries found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr>
        <td class="nha-td-date">${gl_fmtDate(r.posting_date)}</td>
        <td class="nha-mono">${gl_esc(r.jev_no) || '—'}</td>
        <td class="nha-mono">${gl_esc(r.dv_no) || '—'}</td>
        <td title="${gl_esc(r.account_title)}">${gl_esc(r.account_code)} ${r.account_title ? '— ' + gl_esc(r.account_title) : ''}</td>
        <td>${gl_esc(r.sl_code) || '—'}</td>
        <td class="text-end nha-mono">${Number(r.debit) > 0 ? gl_peso(r.debit) : ''}</td>
        <td class="text-end nha-mono">${Number(r.credit) > 0 ? gl_peso(r.credit) : ''}</td>
    </tr>`).join('');
}

/* ── Trial Balance table ───────────────────────────────────── */
function gl_renderTrialBalance(json) {
    const rows  = json.data ?? [];
    const tbody = $gl('gl-tb-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
    } else {
        tbody.innerHTML = rows.map(r => `<tr>
            <td class="nha-mono">${gl_esc(r.account_code)}</td>
            <td>${gl_esc(r.account_title) || '—'}</td>
            <td>${gl_esc(r.account_type) || '—'}</td>
            <td class="text-end nha-mono">${gl_peso(r.total_debit)}</td>
            <td class="text-end nha-mono">${gl_peso(r.total_credit)}</td>
            <td class="text-end nha-mono">${gl_peso(r.balance)}</td>
        </tr>`).join('');
    }
    $gl('gl-tb-total-debit').textContent  = gl_peso(json.total_debit);
    $gl('gl-tb-total-credit').textContent = gl_peso(json.total_credit);

    const badge = $gl('gl-tb-badge');
    if (json.balanced) {
        badge.className = 'nha-bdg nha-bdg-green';
        badge.textContent = 'Balanced';
    } else {
        badge.className = 'nha-bdg nha-bdg-red';
        badge.textContent = 'Out of balance';
    }
}

/* ── Pagination (Journals only) ────────────────────────────── */
function gl_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = GL_PAGE_SIZE } = p;
    $gl('gl-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $gl('gl-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $gl('gl-total').textContent = total;

    const box = $gl('gl-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="gl_goTo(${i})">${i}</button>`);
    }
    box.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="gl_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="gl_goTo(${current_page + 1})">›</button>`;
}
function gl_goTo(page) { if (page >= 1) { gl_page = page; gl_load(); } }

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
