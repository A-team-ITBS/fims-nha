<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Subsidiary Ledger — pick one SL master (optionally one SL detail), see every posting to it
// (fims.general_ledger, via fims.v_journals) in order with a running balance — e.g. one vendor's
// Accounts Payable sub-ledger. Read-only — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8. Separate module from General Ledger (same idea, keyed by a GL
// account instead), Journals (chronological, every account) and Trial Balance (totals, no detail).
$_sl_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_sl_api = preg_match('~/api$~', $_sl_api) ? $_sl_api : $_sl_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #sl-root .sl-combo-wrap { position: relative; }
    #sl-root .sl-combo-results {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20;
        background: #fff; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,.08); max-height: 260px; overflow-y: auto; display: none;
    }
    #sl-root .sl-combo-item { padding: 8px 12px; cursor: pointer; font-size: 13px; }
    #sl-root .sl-combo-item:hover { background: var(--afims-bg-soft, #F5F6FA); }
    #sl-root .sl-combo-empty { padding: 8px 12px; font-size: 13px; color: var(--afims-text-muted,#62657E); }
    #sl-root .sl-summary-cards { display: flex; gap: 12px; flex-wrap: wrap; margin: 0 0 14px; }
    #sl-root .sl-summary-card {
        flex: 1 1 180px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 10px;
        padding: 12px 14px; background: #fff;
    }
    #sl-root .sl-summary-label { font-size: 11px; color: var(--afims-text-muted,#62657E); text-transform: uppercase; letter-spacing: .03em; }
    #sl-root .sl-summary-value { font-size: 18px; font-weight: 600; margin-top: 4px; }
    #sl-root .sl-group-row td { background: var(--afims-bg-soft, #F5F6FA); font-weight: 600; }
</style>

<div id="sl-root" class="nha-root">

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
                <div class="nha-hdr-title">Subsidiary Ledger</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="sl-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        Showing every SL master with a posting. Pick an SL code (and, if it has any, an SL detail)
        above to see just its ledger, oldest first, with a running balance — e.g. one vendor's
        Accounts Payable sub-ledger.
    </p>

    <!-- Error banner -->
    <div id="sl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="sl-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search" style="flex:1 1 260px;">
                <label class="nha-filter-label">SL Code</label>
                <div class="sl-combo-wrap">
                    <input type="text" id="sl-code-search" class="nha-filter-input" style="width:100%;"
                           placeholder="Search SL code or name…" autocomplete="off">
                    <div id="sl-code-results" class="sl-combo-results"></div>
                </div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">SL Detail</label>
                <select id="sl-detail-select" class="nha-filter-select" disabled>
                    <option value="">All Details</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="sl-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book</label>
                <select id="sl-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── SUMMARY ────────────────────────────────────────── -->
    <div id="sl-summary" class="sl-summary-cards no-print" style="display:none;">
        <div class="sl-summary-card">
            <div class="sl-summary-label">SL Master</div>
            <div class="sl-summary-value" id="sl-summary-master" style="font-size:14px;">—</div>
        </div>
        <div class="sl-summary-card">
            <div class="sl-summary-label">Total Debit</div>
            <div class="sl-summary-value nha-mono" id="sl-summary-debit">₱ 0.00</div>
        </div>
        <div class="sl-summary-card">
            <div class="sl-summary-label">Total Credit</div>
            <div class="sl-summary-value nha-mono" id="sl-summary-credit">₱ 0.00</div>
        </div>
        <div class="sl-summary-card">
            <div class="sl-summary-label">Ending Balance</div>
            <div class="sl-summary-value nha-mono" id="sl-summary-balance">₱ 0.00</div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Posting Date</th>
                        <th>JEV Number</th>
                        <th>DV Number</th>
                        <th>Account</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody id="sl-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /#sl-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   SUBSIDIARY LEDGER   prefix: sl_
═══════════════════════════════════════════════════════════════ */

const SL_BASE = <?= json_encode($_sl_api) ?>;
const SL_API  = SL_BASE + '/general_ledger';
const SL_CODES_API = SL_BASE + '/voucher/sl-codes';
const SL_FY_API    = SL_BASE + '/fiscal_year/all';
const SL_BOOK_API  = SL_BASE + '/books/all';

let sl_selectedMaster = null; // { sl_code, sl_name }
let sl_searchTimer = null;

const $sl = id => document.getElementById(id);

function sl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function sl_boot() {
    await Promise.all([sl_loadFiscalYears(), sl_loadBooks()]);
    sl_bindEvents();
    sl_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', sl_boot);
else sl_boot();

function sl_bindEvents() {
    $sl('sl-refresh')?.addEventListener('click', () => sl_load());
    $sl('sl-filter-fy')?.addEventListener('change', () => sl_load());
    $sl('sl-filter-book')?.addEventListener('change', () => sl_load());
    $sl('sl-detail-select')?.addEventListener('change', () => sl_selectedMaster && sl_load());

    const input = $sl('sl-code-search');
    input.addEventListener('input', () => {
        sl_selectedMaster = null;
        sl_resetDetails();
        clearTimeout(sl_searchTimer);
        sl_searchTimer = setTimeout(() => sl_searchCodes(input.value.trim()), 300);
        if (!input.value.trim()) sl_load();
    });
    input.addEventListener('focus', () => { if (input.value.trim()) sl_searchCodes(input.value.trim()); });
    document.addEventListener('click', e => {
        if (!e.target.closest('#sl-code-search') && !e.target.closest('#sl-code-results')) {
            $sl('sl-code-results').style.display = 'none';
        }
    });
}

function sl_resetDetails() {
    const sel = $sl('sl-detail-select');
    sel.innerHTML = '<option value="">All Details</option>';
    sel.disabled = true;
}

/* Fiscal years come from fims.fiscal_year via GET /fiscal_year/all → {success, data:[{fiscal_yr}]}. */
async function sl_loadFiscalYears() {
    const sel = $sl('sl-filter-fy');
    try {
        const res  = await sl_authFetch(SL_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${sl_esc(r.fiscal_yr)}">${sl_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        sl_showError('Fiscal years: ' + e.message);
    }
}

/* Books come from fims.books via GET /books/all → {success, data:[{book_id, code, name}]}. */
async function sl_loadBooks() {
    const sel = $sl('sl-filter-book');
    try {
        const res  = await sl_authFetch(SL_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        sel.innerHTML = '<option value="">All Books</option>' +
            (json.data ?? []).map(r => `<option value="${sl_esc(r.book_id)}">${sl_esc(r.name)}</option>`).join('');
    } catch (e) {
        sl_showError('Books: ' + e.message);
    }
}

/* SL code combobox — GET /voucher/sl-codes?search= → {success, data:[{sl_code, sl_name, account_code}]} */
async function sl_searchCodes(search) {
    const box = $sl('sl-code-results');
    try {
        const params = new URLSearchParams({ search });
        const res  = await sl_authFetch(`${SL_CODES_API}?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        const rows = json.data ?? [];
        if (!rows.length) {
            box.innerHTML = '<div class="sl-combo-empty">No matching SL codes.</div>';
        } else {
            box.innerHTML = rows.map(r => `<div class="sl-combo-item" data-code="${sl_esc(r.sl_code)}" data-name="${sl_esc(r.sl_name)}">
                <strong class="nha-mono">${sl_esc(r.sl_code)}</strong> — ${sl_esc(r.sl_name)}
            </div>`).join('');
            box.querySelectorAll('.sl-combo-item').forEach(el => el.addEventListener('click', () => {
                sl_selectedMaster = { sl_code: el.dataset.code, sl_name: el.dataset.name };
                $sl('sl-code-search').value = `${el.dataset.code} — ${el.dataset.name}`;
                box.style.display = 'none';
                sl_loadDetails(el.dataset.code);
                sl_load();
            }));
        }
        box.style.display = 'block';
    } catch (e) {
        sl_showError('SL code search: ' + e.message);
    }
}

/* SL detail dropdown for the chosen master — GET /voucher/sl-codes/{sl_code}/details */
async function sl_loadDetails(slCode) {
    const sel = $sl('sl-detail-select');
    try {
        const res  = await sl_authFetch(`${SL_CODES_API}/${encodeURIComponent(slCode)}/details`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        const rows = json.data ?? [];
        sel.innerHTML = '<option value="">All Details</option>' +
            rows.map(r => `<option value="${sl_esc(r.sl_detail_id)}">${sl_esc(r.detail_code)} — ${sl_esc(r.detail_name)}</option>`).join('');
        sel.disabled = rows.length === 0;
    } catch (e) {
        sl_showError('SL details: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function sl_load() {
    window.PagePreloader?.show();
    sl_hideError();
    try {
        const params = new URLSearchParams();
        if (sl_selectedMaster) {
            params.set('sl_code', sl_selectedMaster.sl_code);
            const detailId = $sl('sl-detail-select').value;
            if (detailId) params.set('sl_detail_id', detailId);
        }
        const fy = $sl('sl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $sl('sl-filter-book').value;
        if (book) params.set('book_id', book);

        const res  = await sl_authFetch(`${SL_API}/subsidiary-ledger?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Subsidiary Ledger.');

        if (json.grouped) {
            $sl('sl-summary').style.display = 'none';
            sl_renderGroupedTable(json.masters ?? []);
        } else {
            sl_renderSummary(json);
            sl_renderTable(json.data ?? [], json.debit_normal);
        }
    } catch (e) {
        sl_renderTable([]);
        sl_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Summary ───────────────────────────────────────────────── */
function sl_renderSummary(json) {
    $sl('sl-summary').style.display = '';
    $sl('sl-summary-master').textContent = `${json.master.sl_code} — ${json.master.sl_name}`;
    $sl('sl-summary-debit').textContent  = sl_peso(json.total_debit);
    $sl('sl-summary-credit').textContent = sl_peso(json.total_credit);
    $sl('sl-summary-balance').textContent = sl_peso(Math.abs(json.ending_balance)) + sl_balSide(json.ending_balance, json.debit_normal);
}

/* ── Table ─────────────────────────────────────────────────── */
function sl_renderTable(rows, debitNormal) {
    const tbody = $sl('sl-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted transactions for this SL code.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => sl_rowHtml(r, debitNormal)).join('');
}

/* Default view (no SL code picked): every SL master's ledger, one sub-header + its lines each. */
function sl_renderGroupedTable(masters) {
    const tbody = $sl('sl-tbody');
    if (!masters.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = masters.map(m => `
        <tr class="sl-group-row">
            <td colspan="4">${sl_esc(m.master.sl_code)} — ${sl_esc(m.master.sl_name)}</td>
            <td class="text-end nha-mono">${sl_peso(m.total_debit)}</td>
            <td class="text-end nha-mono">${sl_peso(m.total_credit)}</td>
            <td class="text-end nha-mono">${sl_peso(Math.abs(m.ending_balance))}${sl_balSide(m.ending_balance, m.debit_normal)}</td>
        </tr>
        ${m.data.map(r => sl_rowHtml(r, m.debit_normal)).join('')}
    `).join('');
}

function sl_rowHtml(r, debitNormal) {
    return `<tr${r.reversed_gl_id ? ' style="opacity:.7;"' : ''}>
        <td class="nha-td-date">${sl_fmtDate(r.posting_date)}</td>
        <td class="nha-mono">${sl_esc(r.jev_no) || '—'}${r.reference_no && r.reference_no.endsWith('-REV') ? ' <span class="nha-bdg nha-bdg-orange" style="margin-left:4px;">Reversal</span>' : ''}</td>
        <td class="nha-mono">${sl_esc(r.dv_no) || '—'}</td>
        <td title="${sl_esc(r.account_title)}">${sl_esc(r.account_code)}</td>
        <td class="text-end nha-mono">${Number(r.debit) > 0 ? sl_peso(r.debit) : ''}</td>
        <td class="text-end nha-mono">${Number(r.credit) > 0 ? sl_peso(r.credit) : ''}</td>
        <td class="text-end nha-mono">${sl_peso(Math.abs(r.running_balance))}${sl_balSide(r.running_balance, debitNormal)}</td>
    </tr>`;
}

/* Running/ending balance is signed (debit - credit) for debit-normal masters (per the SL master's
   own control account), (credit - debit) otherwise — see GeneralLedgerService::DEBIT_NORMAL_TYPES.
   A positive value is always the account's NORMAL side, so the Dr/Cr suffix has to flip with
   debitNormal, not just the sign. */
function sl_balSide(balance, debitNormal) {
    const onDebitSide = debitNormal ? Number(balance) >= 0 : Number(balance) < 0;
    return onDebitSide ? ' Dr' : ' Cr';
}

/* ── Formatters ────────────────────────────────────────────── */
function sl_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function sl_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function sl_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function sl_showError(msg) { $sl('sl-error').style.display = 'flex'; $sl('sl-error-msg').textContent = msg; }
function sl_hideError()    { $sl('sl-error').style.display = 'none'; }
</script>
