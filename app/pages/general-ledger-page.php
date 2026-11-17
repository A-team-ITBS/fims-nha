<?php
namespace PHPMaker2024\FIMS;
@session_start();

// General Ledger — pick ONE chart-of-accounts account, see every posting to it (fims.general_ledger,
// via fims.v_journals) in order with a running balance. Read-only — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8. Separate module from Subsidiary Ledger (same idea, keyed by SL
// code instead), Journals (chronological, every account) and Trial Balance (totals, no detail).
//
// Column layout matches the illustrated "DISPLAY IN FIMS" format: account header carries Normal
// Balance/Account Class, each row has its own Doc Ref No. (the source document — e.g. "CHECK NO.
// X") alongside Debit/Credit/running Balance, and the footer reads "Ending Balance as of [date]".
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
            <button id="gl-print" type="button" class="nha-btn-hdr" title="Print this account's General Ledger sheet" style="display:none;">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        Showing every posted account. Pick one above to see just its ledger, oldest first, with a running balance.
    </p>

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

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Posting Date</th>
                        <th>JEV No.</th>
                        <th>Doc Ref No.</th>
                        <th>DV Number</th>
                        <th>Particulars</th>
                        <th class="text-end">Debit</th>
                        <th class="text-end">Credit</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody id="gl-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="gl-ending" class="gl-ending-balance no-print" style="display:none;"></div>

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

async function gl_boot() {
    await Promise.all([gl_loadFiscalYears(), gl_loadBooks()]);
    gl_bindEvents();
    gl_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', gl_boot);
else gl_boot();

function gl_bindEvents() {
    $gl('gl-refresh')?.addEventListener('click', () => gl_load());
    $gl('gl-print')?.addEventListener('click', () => gl_print());
    $gl('gl-filter-fy')?.addEventListener('change', () => gl_load());
    $gl('gl-filter-book')?.addEventListener('change', () => gl_load());

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

/* Account combobox — postable (posting_allowed, Active) accounts only, GET /chart_of_accounts/all?postable_only=1 */
async function gl_searchAccounts(search) {
    const box = $gl('gl-account-results');
    try {
        const params = new URLSearchParams({ postable_only: '1', search });
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
            gl_renderGroupedTable(json.accounts ?? []);
        } else {
            gl_renderSummary(json);
            gl_renderTable(json.data ?? [], json.debit_normal);
            gl_renderEnding(json.ending_balance, json.debit_normal);
        }
    } catch (e) {
        gl_setPrintable(null);
        gl_renderTable([]);
        gl_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Print — the GENERAL LEDGER sheet (monthly summary) of the account shown ──────────────────────
   Shared print preview (readone/print-preview-readone.php): prp_glPayload(ledger) → prp_open(…, GL).
   The sheet is one fiscal year: the Fiscal Year filter, else the year of the postings shown (when they
   span several years, pick one first). Only for a single account — hidden in the all-accounts view. */
function gl_setPrintable(json) {
    gl_ledger = json;
    const btn = $gl('gl-print');
    if (btn) btn.style.display = json ? '' : 'none';
}
async function gl_print() {
    if (!gl_ledger || typeof prp_open !== 'function') return;
    gl_hideError();
    let fy = $gl('gl-filter-fy').value;
    if (!fy) {
        const years = [...new Set((gl_ledger.data ?? []).map(r => String(r.posting_date || '').slice(0, 4)).filter(Boolean))];
        if (years.length > 1) {
            gl_showError('This account has postings in ' + years.join(', ') + ' — pick a Fiscal Year to print (the ledger sheet is one year).');
            return;
        }
        fy = years[0] || String(new Date().getFullYear());
    }
    await prp_open(prp_glPayload(gl_ledger, { fiscal_year: fy }), { type: 'GL' });
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
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted transactions for this account.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => gl_rowHtml(r, debitNormal)).join('');
}

/* Default view (no account picked): every account's ledger, one sub-header + its lines each. */
function gl_renderGroupedTable(accounts) {
    const tbody = $gl('gl-tbody');
    if (!accounts.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = accounts.map(a => `
        <tr class="gl-group-row">
            <td colspan="5">${gl_esc(a.account.account_code)} — ${gl_esc(a.account.account_title)} <span style="font-weight:400;color:var(--afims-text-muted,#62657E);">(${gl_esc(a.normal_balance)}-normal, ${gl_esc(a.account.account_type)})</span></td>
            <td class="text-end nha-mono">${gl_peso(a.total_debit)}</td>
            <td class="text-end nha-mono">${gl_peso(a.total_credit)}</td>
            <td class="text-end nha-mono">${gl_peso(Math.abs(a.ending_balance))}${gl_balSide(a.ending_balance, a.debit_normal)}</td>
        </tr>
        ${a.data.map(r => gl_rowHtml(r, a.debit_normal)).join('')}
    `).join('');
}

function gl_rowHtml(r, debitNormal) {
    return `<tr${r.reversed_gl_id ? ' style="opacity:.7;"' : ''}>
        <td class="nha-td-date">${gl_fmtDate(r.posting_date)}</td>
        <td class="nha-mono">${gl_esc(r.jev_no) || '—'}${r.reference_no && r.reference_no.endsWith('-REV') ? ' <span class="nha-bdg nha-bdg-orange" style="margin-left:4px;">Reversal</span>' : ''}</td>
        <td class="nha-mono">${gl_esc(r.doc_ref_no) || '—'}</td>
        <td class="nha-mono">${gl_esc(r.dv_no) || '—'}</td>
        <td>${gl_esc(r.description) || gl_esc(r.sl_name) || '—'}</td>
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
// Print → the shared print preview (prp_open + prp_glPayload).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
