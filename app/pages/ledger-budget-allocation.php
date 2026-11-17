<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Ledger of Budget Allocation — every RFA / LAA, grouped under the allocation it draws against (its parent,
// normally an Approved CBA), with the parent's ceiling, each entry's amount and the running balance. Only an
// Approved RFA / LAA is drawn (deducted); the others are listed but leave the balance unchanged. Read-only —
// see app/services/LedgerBudgetAllocationService.php and GET /api/ledger_budget_allocation/list.
$_lba_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_lba_api = preg_match('~/api$~', $_lba_api) ? $_lba_api : $_lba_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #lba-root .lba-summary-cards { display: flex; gap: 12px; flex-wrap: wrap; margin: 0 0 14px; }
    #lba-root .lba-summary-card {
        flex: 1 1 160px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 10px;
        padding: 12px 14px; background: #fff;
    }
    #lba-root .lba-summary-label { font-size: 11px; color: var(--afims-text-muted,#62657E); text-transform: uppercase; letter-spacing: .03em; }
    #lba-root .lba-summary-value { font-size: 18px; font-weight: 600; margin-top: 4px; }
    #lba-root .lba-group-row td { background: var(--afims-bg-soft, #F5F6FA); font-weight: 600; }
    #lba-root .lba-group-sub { font-weight: 400; color: var(--afims-text-muted,#62657E); font-size: 12px; }
    #lba-root .lba-group-foot td { font-size: 12.5px; border-bottom: 2px solid var(--afims-border, #E2E4ED); }
    #lba-root tr.lba-not-drawn td { color: var(--afims-text-muted,#62657E); }
    #lba-root .lba-muted { color: var(--afims-text-muted,#62657E); font-size: 12px; }
    #lba-root .lba-neg { color: #C0392B; }
</style>

<div id="lba-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Ledger of Budget Allocation</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="lba-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        RFAs and LAAs grouped under the allocation they draw against. Only an <strong>Approved</strong> RFA / LAA is
        deducted from the balance; the others are listed in grey and leave the balance unchanged.
    </p>

    <!-- Error banner -->
    <div id="lba-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="lba-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search" style="flex:1 1 280px;">
                <label class="nha-filter-label">Search</label>
                <input type="text" id="lba-search" class="nha-filter-input" style="width:100%;"
                       placeholder="Allocation no., ref no., CBA, DV no., vendor…" autocomplete="off">
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="lba-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Type</label>
                <select id="lba-filter-type" class="nha-filter-select">
                    <option value="">RFA &amp; LAA</option>
                    <option value="RFA">RFA</option>
                    <option value="LAA">LAA</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── SUMMARY ────────────────────────────────────────── -->
    <div class="lba-summary-cards">
        <div class="lba-summary-card">
            <div class="lba-summary-label">Entries</div>
            <div class="lba-summary-value" id="lba-sum-entries">0</div>
            <div class="lba-muted" id="lba-sum-split">RFA 0 · LAA 0</div>
        </div>
        <div class="lba-summary-card">
            <div class="lba-summary-label">Total Amount</div>
            <div class="lba-summary-value nha-mono" id="lba-sum-amount">₱ 0.00</div>
        </div>
        <div class="lba-summary-card">
            <div class="lba-summary-label">Drawn (Approved)</div>
            <div class="lba-summary-value nha-mono" id="lba-sum-drawn">₱ 0.00</div>
        </div>
        <div class="lba-summary-card">
            <div class="lba-summary-label">Linked to a DV</div>
            <div class="lba-summary-value" id="lba-sum-dv">0</div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Allocation No.</th>
                        <th>Ref No.</th>
                        <th>Particulars</th>
                        <th>Office</th>
                        <th>Status</th>
                        <th>DV No.</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Balance</th>
                    </tr>
                </thead>
                <tbody id="lba-tbody">
                    <tr><td colspan="10"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /#lba-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   LEDGER OF BUDGET ALLOCATION   prefix: lba_
═══════════════════════════════════════════════════════════════ */

const LBA_BASE   = <?= json_encode($_lba_api) ?>;
const LBA_API    = LBA_BASE + '/ledger_budget_allocation/list';
const LBA_FY_API = LBA_BASE + '/fiscal_year/all';
const LBA_COLS   = 10;

let lba_searchTimer = null;
let lba_loadSeq = 0;

const $lba = id => document.getElementById(id);

function lba_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function lba_boot() {
    await lba_loadFiscalYears();
    lba_bindEvents();
    lba_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', lba_boot);
else lba_boot();

function lba_bindEvents() {
    $lba('lba-refresh')?.addEventListener('click', () => lba_load());
    ['lba-filter-fy', 'lba-filter-type'].forEach(id =>
        $lba(id)?.addEventListener('change', () => lba_load()));
    $lba('lba-search')?.addEventListener('input', () => {
        clearTimeout(lba_searchTimer);
        lba_searchTimer = setTimeout(() => lba_load(), 350);
    });
}

/* Fiscal years: GET /fiscal_year/all → {success, data:[{fiscal_yr}]}. Defaults to the current year when listed. */
async function lba_loadFiscalYears() {
    const sel = $lba('lba-filter-fy');
    try {
        const res  = await lba_authFetch(LBA_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        const years = (json.data ?? []).map(r => String(r.fiscal_yr));
        sel.innerHTML = '<option value="">All Years</option>' +
            years.map(y => `<option value="${lba_esc(y)}">${lba_esc(y)}</option>`).join('');
        const thisYear = String(new Date().getFullYear());
        if (years.includes(thisYear)) sel.value = thisYear;
    } catch (e) {
        lba_showError('Fiscal years: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function lba_load() {
    const seq = ++lba_loadSeq;
    window.PagePreloader?.show();
    lba_hideError();
    try {
        const params = new URLSearchParams();
        const fy = $lba('lba-filter-fy').value;
        if (fy) params.set('fiscal_year', fy);
        const type = $lba('lba-filter-type').value;
        if (type) params.set('allocation_type', type);
        const search = $lba('lba-search').value.trim();
        if (search) params.set('search', search);

        const res  = await lba_authFetch(`${LBA_API}?${params}`);
        const json = await res.json().catch(() => ({}));
        if (seq !== lba_loadSeq) return; // a newer load started meanwhile
        if (!res.ok || !json.success) throw new Error(json.message || ('HTTP ' + res.status));

        lba_renderSummary(json.summary || {});
        lba_renderTable(json.data ?? []);
    } catch (e) {
        if (seq !== lba_loadSeq) return;
        lba_renderSummary({});
        lba_renderTable([]);
        lba_showError('Could not load data: ' + e.message);
    } finally {
        if (seq === lba_loadSeq) window.PagePreloader?.hide();
    }
}

/* ── Summary ───────────────────────────────────────────────── */
function lba_renderSummary(s) {
    $lba('lba-sum-entries').textContent = Number(s.entries || 0).toLocaleString('en-PH');
    $lba('lba-sum-split').textContent   = `RFA ${Number(s.rfa_count || 0)} · LAA ${Number(s.laa_count || 0)}`;
    $lba('lba-sum-amount').textContent  = lba_peso(s.total_amount);
    $lba('lba-sum-drawn').textContent   = lba_peso(s.total_drawn);
    $lba('lba-sum-dv').textContent      = Number(s.with_dv || 0).toLocaleString('en-PH');
}

/* ── Table: one header row per parent allocation, its RFA / LAA entries, then a total row ── */
function lba_renderTable(groups) {
    const tbody = $lba('lba-tbody');
    if (!groups.length) {
        tbody.innerHTML = `<tr><td colspan="${LBA_COLS}"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No RFA / LAA matches these filters.</span></div></td></tr>`;
        return;
    }
    tbody.innerHTML = groups.map(lba_groupHtml).join('');
}

function lba_groupHtml(g) {
    const p = g.parent;
    let head;
    if (p) {
        const where = [p.program_name || p.program_code, p.project_name || p.project_code].filter(Boolean).join(' / ');
        head = `<tr class="lba-group-row">
            <td colspan="${LBA_COLS - 2}">
                <span class="nha-bdg nha-bdg-navy">${lba_esc(p.allocation_type || 'Parent')}</span>
                <span class="nha-mono">${lba_esc(p.allocation_number) || '(no allocation no.)'}</span>
                <span class="lba-group-sub">· ${lba_esc(p.request_referrence_no) || '—'}${p.status_name ? ' · ' + lba_esc(p.status_name) : ''}${where ? ' · ' + lba_esc(where) : ''}</span>
            </td>
            <td class="text-end"><span class="lba-group-sub">Ceiling</span></td>
            <td class="text-end nha-mono">${lba_peso(g.ceiling)}</td>
        </tr>`;
    } else {
        head = `<tr class="lba-group-row">
            <td colspan="${LBA_COLS}">No parent allocation <span class="lba-group-sub">· not drawn against a CBA, no running balance</span></td>
        </tr>`;
    }

    const rows = g.entries.map(e => `<tr class="${e.is_drawn ? '' : 'lba-not-drawn'}">
        <td class="nha-td-date">${lba_fmtDate(e.created_at)}</td>
        <td><span class="nha-bdg ${String(e.allocation_type).toUpperCase() === 'RFA' ? 'nha-bdg-blue' : 'nha-bdg-teal'}">${lba_esc(e.allocation_type)}</span>${e.document_type ? ` <span class="nha-bdg nha-bdg-gray">${lba_esc(e.document_type)}</span>` : ''}</td>
        <td class="nha-mono">${lba_esc(e.allocation_number) || '—'}</td>
        <td class="nha-mono">${lba_esc(e.request_referrence_no) || '—'}</td>
        <td>${lba_esc(e.description) || '—'}${e.vendor_name ? `<div class="lba-muted">${lba_esc(e.vendor_name)}</div>` : ''}</td>
        <td>${lba_esc(e.requesting_rc_name) || '—'}${e.requesting_rc_code ? `<div class="lba-muted nha-mono">${lba_esc(e.requesting_rc_code)}</div>` : ''}</td>
        <td>${lba_statusBadge(e.status_name, e.status_color)}</td>
        <td>${e.dv_no ? `<span class="nha-mono">${lba_esc(e.dv_no)}</span><div class="lba-muted">${lba_esc(e.dv_status || '')}${e.dv_date ? ' · ' + lba_fmtDate(e.dv_date) : ''}</div>` : '—'}</td>
        <td class="text-end nha-mono">${lba_peso(e.amount)}</td>
        <td class="text-end nha-mono ${Number(e.running_balance) < 0 ? 'lba-neg' : ''}">${e.running_balance === null || e.running_balance === undefined ? '—' : lba_peso(e.running_balance)}</td>
    </tr>`).join('');

    const foot = p ? `<tr class="lba-group-foot">
        <td colspan="${LBA_COLS - 2}" class="text-end lba-muted">Total drawn (all Approved RFA / LAA of this allocation) · Remaining balance</td>
        <td class="text-end nha-mono">${lba_peso(g.total_drawn)}</td>
        <td class="text-end nha-mono ${Number(g.balance) < 0 ? 'lba-neg' : ''}"><strong>${lba_peso(g.balance)}</strong></td>
    </tr>` : '';

    return head + rows + foot;
}

/* status_color comes from settings_status — only a plain hex colour is allowed into the inline style. */
function lba_statusBadge(name, color) {
    if (!name) return '—';
    const safe = /^#[0-9a-fA-F]{3,8}$/.test(color || '') ? color : '#6B7280';
    return `<span class="nha-bdg" style="color:${safe};background:color-mix(in srgb, ${safe} 14%, #fff);">${lba_esc(name)}</span>`;
}

/* ── Formatters ────────────────────────────────────────────── */
function lba_peso(v) {
    const n = Number(v || 0);
    return (n < 0 ? '-₱ ' : '₱ ') + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function lba_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function lba_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function lba_showError(msg) { $lba('lba-error').style.display = 'flex'; $lba('lba-error-msg').textContent = msg; }
function lba_hideError()    { $lba('lba-error').style.display = 'none'; }
</script>
