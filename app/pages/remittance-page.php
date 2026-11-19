<?php

namespace PHPMaker2024\FIMS;

/**
 * remittance-page.php — Remittance (Payroll menu; page title "Deductions & Remittances"). The deductions withheld in each
 * imported payroll month (Payroll Management) are what is remitted. ONE row per month; expanding it lists its agencies,
 * each with its own "Create DV" (one remittance DV per agency: claimant = the agency, Dr its Due to … and government share,
 * Cr Accounts Payable; no RFA); it runs FMD → Accounting → Treasury, and the agency's receipt then confirms it. No scheduling step and no
 * dependency on the payroll DV (that one pays the employees, from Payroll Management).
 * API: app/routes/deduction_remittance.php → app/services/DeductionRemittanceService.php.
 */
@session_start();
$_dr_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_dr_api = preg_match('~/api$~', $_dr_api) ? $_dr_api : $_dr_api . '/api';
?>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
#dr-root .nha-summary { grid-template-columns: repeat(4, 1fr); }
#dr-root .dr-num { text-align: right; font-family: 'IBM Plex Mono', monospace; white-space: nowrap; }
#dr-root .dr-hint { font-size: 11.5px; color: var(--afims-text-muted, #62657E); margin-top: 2px; }
#dr-root .dr-link { color: inherit; text-decoration: none; border-bottom: 1px dashed currentColor; }
#dr-root .dr-link:hover { color: var(--afims-primary, #0035A9); }
#dr-root .dr-modal { max-width: 560px; width: 94%; }
#dr-root .dr-modal-xl { max-width: 1100px; width: 96%; }
#dr-root .dr-scroll { max-height: 70vh; overflow: auto; }
#dr-root .dr-form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
#dr-root .dr-form .dr-full { grid-column: 1 / -1; }
#dr-root .dr-form label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
#dr-root .dr-form input, #dr-root .dr-form select, #dr-root .dr-form textarea { width: 100%; padding: 8px 10px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 6px; font: inherit; background: var(--afims-surface, #fff); color: inherit; }
#dr-root .dr-sum { display: grid; grid-template-columns: 1fr auto; gap: 6px 16px; margin: 0 0 14px; font-size: 13px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px; padding: 10px 14px; }
#dr-root .dr-sum dt { color: var(--afims-text-muted, #62657E); }
#dr-root .dr-sum dd { margin: 0; text-align: right; font-weight: 600; font-family: 'IBM Plex Mono', monospace; }
#dr-root .dr-msg { margin-top: 12px; font-size: 12.5px; padding: 8px 10px; border-radius: 6px; display: none; }
#dr-root .dr-msg.err { display: block; background: var(--red-bg, #FDECEC); color: var(--red-txt, #B42318); }
#dr-root .dr-sec { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin: 14px 0 6px; color: var(--afims-heading, #000E3E); }
#dr-root .dr-btn-go { background: var(--afims-success, #11A752); color: #fff; border-color: transparent; }
#dr-root .dr-btn-tr { background: var(--afims-primary, #0035A9); color: #fff; border-color: transparent; }
#dr-root .dr-month td { background: var(--afims-surface, #fff); }
#dr-root .dr-month.open td { border-bottom-color: transparent; }
#dr-root .dr-toggle { background: none; border: 0; padding: 0 6px 0 0; cursor: pointer; color: inherit; font-size: 12px; }
#dr-root .dr-sub td { background: var(--afims-surface-subtle, #F4F7FA); font-size: 12.5px; }
#dr-root .dr-sub td:first-child { padding-left: 34px; }
#dr-root .dr-progress { font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
@media (max-width: 900px) { #dr-root .nha-summary { grid-template-columns: repeat(2, 1fr); } }
</style>

<div id="dr-root" class="nha-root">

    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Deductions &amp; Remittances</div>
                <div class="nha-hdr-sub">Deductions withheld in each imported payroll month — paid to each agency through its own remittance DV.</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="dr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Withheld</div><div class="nha-sc-val" id="dr-k-withheld">—</div></div></div><p class="nha-kpi-sub">Employee + government share, filters applied</p></div>
        <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">No Remittance DV Yet</div><div class="nha-sc-val" id="dr-k-pending">—</div></div></div><p class="nha-kpi-sub" id="dr-k-pending-sub">Agencies waiting for their DV</p></div>
        <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">Remittance DV in Process</div><div class="nha-sc-val" id="dr-k-scheduled">—</div></div></div><p class="nha-kpi-sub" id="dr-k-scheduled-sub">FMD → Accounting → Treasury</p></div>
        <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">Remitted</div><div class="nha-sc-val" id="dr-k-remitted">—</div></div></div><p class="nha-kpi-sub" id="dr-k-remitted-sub">Remitted / confirmed</p></div>
    </div>

    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="dr-f-year">Year</label>
                <select id="dr-f-year" class="nha-filter-select"><option value="">All Years</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="dr-f-month">Month</label>
                <select id="dr-f-month" class="nha-filter-select"><option value="">All Months</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="dr-f-status">Status</label>
                <select id="dr-f-status" class="nha-filter-select">
                    <option value="">All Statuses</option>
                    <option value="PENDING">Pending (no remittance DV yet)</option>
                    <option value="IN_PROCESS">In process</option>
                    <option value="REMITTED">Remitted</option>
                    <option value="CONFIRMED">Confirmed</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button type="button" id="dr-reset" class="nha-btn nha-btn-outline">Reset</button>
            </div>
        </div>
    </div>

    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Payroll Month / Agency</th>
                        <th class="text-end">Employee Share</th>
                        <th class="text-end">Gov't Share</th>
                        <th class="text-end">Total to Remit</th>
                        <th>Remittance DV</th>
                        <th>Status</th>
                        <th>Remitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="dr-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Detail: one month + agency -->
    <div id="dr-det-overlay" class="nha-overlay">
        <div class="nha-modal dr-modal-xl" role="dialog" aria-modal="true" aria-labelledby="dr-det-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="dr-det-title">Deductions</h3>
                <button type="button" class="nha-modal-close" onclick="dr_close('dr-det-overlay')" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body dr-scroll">
                <div class="dr-sec">Payroll items</div>
                <table class="nha-tbl">
                    <thead><tr><th>Item</th><th>Share</th><th>Account</th><th class="text-end">Employees</th><th class="text-end">Amount</th></tr></thead>
                    <tbody id="dr-det-items"></tbody>
                </table>
                <div class="dr-sec">Per employee</div>
                <table class="nha-tbl">
                    <thead><tr><th>Emp No.</th><th>Employee</th><th>Items</th><th class="text-end">Employee Share</th><th class="text-end">Gov't Share</th><th class="text-end">Total</th></tr></thead>
                    <tbody id="dr-det-emps"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Step form: confirm receipt -->
    <div id="dr-act-overlay" class="nha-overlay">
        <div class="nha-modal dr-modal" role="dialog" aria-modal="true" aria-labelledby="dr-act-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="dr-act-title">Remittance</h3>
                <button type="button" class="nha-modal-close" onclick="dr_close('dr-act-overlay')" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <dl class="dr-sum" id="dr-act-sum"></dl>
                <div class="dr-form" id="dr-act-form"></div>
                <div class="dr-msg" id="dr-act-msg"></div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-outline" onclick="dr_close('dr-act-overlay')">Cancel</button>
                <button type="button" class="nha-btn nha-btn-primary" id="dr-act-save">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   DEDUCTIONS & REMITTANCES   prefix: dr_
═══════════════════════════════════════════════════════════════ */
const DR_API = <?= json_encode($_dr_api) ?>;
const DR_VOUCHER_URL = <?= json_encode(GetUrl('Voucher')) ?>; // DVs: ?remittance= (new remittance DV), ?dv= (view)
const DR_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
// an agency of a month
const DR_STATUS = {
    PENDING:      ['nha-bdg-amber', 'No DV yet'],
    IN_DV:        ['nha-bdg-blue', 'DV in process'],
    REMITTED:     ['nha-bdg-green', 'Remitted'],
    CONFIRMED:    ['nha-bdg-green', 'Confirmed'],
    NOT_REMITTED: ['nha-bdg-gray', 'Collected by NHA'],
};
// the month
const DR_MONTH_STATUS = {
    PENDING:    ['nha-bdg-amber', 'Pending'],
    IN_PROCESS: ['nha-bdg-blue', 'In process'],
    REMITTED:   ['nha-bdg-green', 'Remitted'],
    CONFIRMED:  ['nha-bdg-green', 'Confirmed'],
};
const $dr = id => document.getElementById(id);
let dr_rows = [], dr_months = [], dr_can = {}, dr_open = new Set();   // dr_open: months whose agencies are shown

function dr_authFetch(path, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(DR_API + '/' + path, { credentials: 'same-origin', ...opts, headers });
}
async function dr_json(res) {
    if (res.status === 401) throw new Error('Your session has expired. Please refresh the page and log in again.');
    return res.json().catch(() => ({ success: false, message: 'HTTP ' + res.status }));
}
const dr_peso = v => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dr_amt  = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const dr_esc  = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
const dr_date = s => { if (!s) return ''; const d = new Date(String(s).slice(0, 10) + 'T00:00:00'); return isNaN(d) ? String(s) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }); };
const dr_today = () => new Date().toISOString().slice(0, 10);
function dr_close(id) { $dr(id).classList.remove('open'); }

/* ── List ───────────────────────────────────────────────────── */
async function dr_load() {
    const params = new URLSearchParams();
    ['year', 'month', 'status'].forEach(k => { const v = $dr('dr-f-' + k).value; if (v) params.set(k, v); });
    const body = $dr('dr-tbody');
    body.innerHTML = '<tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const json = await dr_json(await dr_authFetch('deduction-remittance/list?' + params));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        dr_can = json.can || {};
        dr_fillFilters(json.years || []);
        const k = json.kpis || {};
        $dr('dr-k-withheld').textContent  = dr_peso(k.withheld);
        $dr('dr-k-pending').textContent   = dr_peso(k.pending);
        $dr('dr-k-scheduled').textContent = dr_peso(k.in_dv);
        $dr('dr-k-remitted').textContent  = dr_peso(k.remitted);
        $dr('dr-k-pending-sub').textContent   = `${k.pending_count ?? 0} agency remittance(s) without a DV`;
        $dr('dr-k-scheduled-sub').textContent = `${k.in_dv_count ?? 0} in FMD → Accounting → Treasury`;
        $dr('dr-k-remitted-sub').textContent  = `${k.remitted_count ?? 0} remitted / confirmed`;
        dr_rows = json.data || [];
        dr_months = json.months || [];
        dr_render();
    } catch (e) {
        body.innerHTML = `<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${dr_esc(e.message)}</span></div></td></tr>`;
    }
}

function dr_render() {
    const body = $dr('dr-tbody');
    if (!dr_months.length) {
        body.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No deductions to show.</span><div class="nha-empty-text">A payroll month appears here once it is imported in Payroll Management.</div></div></td></tr>';
        return;
    }
    body.innerHTML = dr_months.map((m, mi) => {
        const open = dr_open.has(m.payroll_period_id);
        const [cls, txt] = DR_MONTH_STATUS[m.status] || ['nha-bdg-gray', m.status];
        const emp = m.agencies.filter(a => a.agency !== 'INTERNAL').reduce((t, a) => t + Number(a.employee_share || 0), 0);
        const gov = m.agencies.filter(a => a.agency !== 'INTERNAL').reduce((t, a) => t + Number(a.employer_share || 0), 0);
        const progress = m.with_dv
            ? `<span class="dr-progress">${m.with_dv} of ${m.agency_count} DVs created · ${m.remitted_count} remitted</span>`
            : `<span class="dr-progress">${m.agency_count} agencies</span>`;
        const month = `<tr class="dr-month${open ? ' open' : ''}">
                <td><button type="button" class="dr-toggle" onclick="dr_toggle(${mi})" aria-expanded="${open}" title="Show the agencies">${open ? '▾' : '▸'}</button>
                    <strong>${dr_esc(m.label)}</strong><div class="dr-hint" style="padding-left:18px;">${dr_esc(m.employees)} employees · ${m.payroll_dv_id
                        ? `Payroll DV <a href="${DR_VOUCHER_URL}?dv=${Number(m.payroll_dv_id)}" class="dr-link nha-mono">${dr_esc(m.payroll_dv_no)}</a>${(m.payroll_rfa_nos || []).length ? ' · RFA ' + m.payroll_rfa_nos.map(dr_esc).join(', ') : ''}`
                        : 'No payroll DV yet'}</div></td>
                <td class="dr-num">${dr_amt(emp)}</td>
                <td class="dr-num">${dr_amt(gov)}</td>
                <td class="dr-num"><strong>${dr_peso(m.total)}</strong></td>
                <td>${progress}</td>
                <td><span class="nha-bdg ${cls}">${dr_esc(txt)}</span></td>
                <td></td>
                <td style="white-space:nowrap;">${dr_monthAction(m)}</td>
            </tr>`;
        if (!open) return month;
        return month + m.agencies.map(a => {
            const i = dr_rows.indexOf(a);
            const [acls, atxt] = DR_STATUS[a.status] || ['nha-bdg-gray', a.status];
            const dv = a.remittance_dv_id
                ? `<a href="${DR_VOUCHER_URL}?dv=${Number(a.remittance_dv_id)}" class="dr-link nha-mono">${dr_esc(a.remittance_dv_no)}</a><div class="dr-hint">${dr_esc(a.remittance_dv_status || '')}</div>`
                : '—';
            const paid = (a.status === 'REMITTED' || a.status === 'CONFIRMED')
                ? `${dr_esc(dr_date(a.remittance_date))}<div class="dr-hint">${[a.remittance_method, a.remittance_no, a.receipt_no ? 'OR ' + a.receipt_no : ''].filter(Boolean).map(dr_esc).join(' · ')}</div>` : '—';
            const act = dr_agencyAction(a, i);
            return `<tr class="dr-sub">
                <td><a href="#" class="dr-link" title="View the items and employees" onclick="dr_openDetail(${i}); return false;">${dr_esc(a.agency_name)}</a></td>
                <td class="dr-num">${dr_amt(a.employee_share)}</td>
                <td class="dr-num">${dr_amt(a.employer_share)}</td>
                <td class="dr-num">${dr_amt(a.total)}</td>
                <td>${dv}</td>
                <td>${['CONFIRMED', 'NOT_REMITTED'].includes(a.status) ? `<span class="nha-bdg ${acls}">${dr_esc(atxt)}</span>` : ''}</td>
                <td>${paid}</td>
                <td style="white-space:nowrap;">${act}</td>
            </tr>`;
        }).join('');
    }).join('');
}

function dr_toggle(mi) {
    // one month open at a time: opening another closes the previous; clicking the open one closes it
    const id = dr_months[mi].payroll_period_id;
    const wasOpen = dr_open.has(id);
    dr_open.clear();
    if (!wasOpen) dr_open.add(id);
    dr_render();
}

/* The month row has no button: each agency below it has its own Create DV. It only says where the month stands. */
function dr_monthAction(m) {
    if (m.status === 'PENDING') return '<span class="dr-hint">Expand to create each agency\'s DV</span>';
    if (m.status === 'IN_PROCESS') return m.missing_dv ? `<span class="dr-hint">${m.missing_dv} agenc${m.missing_dv === 1 ? 'y' : 'ies'} without a DV</span>` : '<span class="dr-hint">In FMD → Accounting → Treasury</span>';
    if (m.status === 'REMITTED') return '<span class="dr-hint">Remitted — confirm each receipt</span>';
    if (m.status === 'CONFIRMED') return '<span class="dr-hint">Done</span>';
    return '';
}

/* An agency's action: Create DV (Accounting, no DV yet) → opens the DV form for that agency; Confirm Receipt once remitted. */
function dr_agencyAction(a, i) {
    if (a.status === 'PENDING') {
        return dr_can.create_dv
            ? `<button type="button" class="nha-btn-act dr-btn-go" onclick="dr_startDv(${Number(a.payroll_period_id)}, '${dr_esc(a.agency)}', this)" title="A DV paying ${dr_esc(a.agency_name)} for ${dr_esc(a.label)} — no RFA needed">Create DV</button>`
            : '<span class="dr-hint">Waiting for Accounting</span>';
    }
    if (a.status === 'REMITTED' && dr_can.confirm) {
        return `<button type="button" class="nha-btn-act dr-btn-tr" onclick="dr_openStep('confirm', ${i})">Confirm Receipt</button>`;
    }
    return '';
}

async function dr_startDv(periodId, agency, btn) {
    if (btn) btn.disabled = true;
    try {
        const json = await dr_json(await dr_authFetch('deduction-remittance/start-dv', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ payroll_period_id: periodId, agency }),
        }));
        if (!json.success) throw new Error(json.message || 'Failed.');
        location.href = `${DR_VOUCHER_URL}?remittance=${Number(json.data.payroll_remittance_id)}`;
    } catch (e) {
        alert(e.message);
        if (btn) btn.disabled = false;
        dr_load();
    }
}

function dr_fillFilters(years) {
    const y = $dr('dr-f-year'), yc = y.value;
    y.innerHTML = '<option value="">All Years</option>' + years.map(v => `<option value="${v}">${v}</option>`).join('');
    y.value = yc;
}

/* ── Detail ─────────────────────────────────────────────────── */
async function dr_openDetail(i) {
    const r = dr_rows[i];
    $dr('dr-det-title').textContent = `${r.agency_name} — ${r.label}`;
    $dr('dr-det-items').innerHTML = $dr('dr-det-emps').innerHTML = '<tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $dr('dr-det-overlay').classList.add('open');
    try {
        const json = await dr_json(await dr_authFetch(`deduction-remittance/detail/${Number(r.payroll_period_id)}/${encodeURIComponent(r.agency)}`));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        const d = json.data;
        $dr('dr-det-items').innerHTML = (d.items || []).map(it => `<tr>
                <td><span class="nha-mono">${dr_esc(it.item_code)}</span><div class="dr-hint">${dr_esc(it.item_name)}</div></td>
                <td>${it.share === 'EMPLOYER' ? "Gov't share" : (it.category === 'REFUND' ? 'Refund (less)' : 'Employee share')}</td>
                <td class="nha-mono">${dr_esc(it.account_code || '')}</td>
                <td class="dr-num">${dr_esc(it.employees)}</td>
                <td class="dr-num">${dr_amt(it.amount)}</td>
            </tr>`).join('') || '<tr><td colspan="5">No items.</td></tr>';
        $dr('dr-det-emps').innerHTML = (d.employees || []).map(e => `<tr>
                <td class="nha-mono">${dr_esc(e.employee_no)}</td>
                <td>${dr_esc(e.name)}</td>
                <td class="dr-hint">${(e.items || []).map(x => dr_esc(x.item_code) + ' ' + dr_amt(x.amount)).join(' · ')}</td>
                <td class="dr-num">${dr_amt(e.employee_share)}</td>
                <td class="dr-num">${dr_amt(e.employer_share)}</td>
                <td class="dr-num"><strong>${dr_amt(e.total)}</strong></td>
            </tr>`).join('') || '<tr><td colspan="6">No employees.</td></tr>';
    } catch (e) {
        $dr('dr-det-items').innerHTML = `<tr><td colspan="5">${dr_esc(e.message)}</td></tr>`;
        $dr('dr-det-emps').innerHTML = '';
    }
}

/* ── Step: confirm receipt (Treasury / Accounting) — paying it is the remittance DV ── */
let dr_step = null;
function dr_openStep(kind, i) {
    const r = dr_rows[i];
    dr_step = { kind, r };
    const amount = r.scheduled_amount ?? r.total;
    $dr('dr-act-sum').innerHTML = `<dt>Payroll month</dt><dd>${dr_esc(r.label)}</dd><dt>Agency</dt><dd>${dr_esc(r.agency_name)}</dd>`
        + `<dt>Remittance DV</dt><dd>${dr_esc(r.remittance_dv_no || '—')}</dd><dt>Total remitted</dt><dd>${dr_peso(amount)}</dd>`;
    $dr('dr-act-title').textContent = 'Confirm Receipt';
    $dr('dr-act-save').textContent = 'Confirm';
    $dr('dr-act-form').innerHTML = `<div><label for="dr-in-or">Agency receipt / confirmation no. <span class="nha-req">*</span></label><input type="text" id="dr-in-or"></div>
        <div><label for="dr-in-ordate">Receipt date <span class="nha-req">*</span></label><input type="date" id="dr-in-ordate" value="${dr_today()}"></div>`;
    $dr('dr-act-msg').className = 'dr-msg';
    $dr('dr-act-save').disabled = false;
    $dr('dr-act-overlay').classList.add('open');
}

async function dr_saveStep() {
    if (!dr_step) return;
    const { r } = dr_step;
    const path = `deduction-remittance/${Number(r.payroll_remittance_id)}/confirm`;
    const body = { receipt_no: $dr('dr-in-or').value.trim(), receipt_date: $dr('dr-in-ordate').value };
    $dr('dr-act-save').disabled = true;
    try {
        const json = await dr_json(await dr_authFetch(path, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) }));
        if (!json.success) throw new Error(json.message || 'Failed.');
        dr_close('dr-act-overlay');
        dr_load();
    } catch (e) {
        $dr('dr-act-msg').className = 'dr-msg err';
        $dr('dr-act-msg').textContent = e.message;
        $dr('dr-act-save').disabled = false;
    }
}

/* ── Boot ───────────────────────────────────────────────────── */
function dr_boot() {
    $dr('dr-f-month').innerHTML = '<option value="">All Months</option>' + DR_MONTHS.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
    ['dr-f-year', 'dr-f-month', 'dr-f-status'].forEach(id => $dr(id).addEventListener('change', dr_load));
    $dr('dr-reset').addEventListener('click', () => { ['dr-f-year', 'dr-f-month', 'dr-f-status'].forEach(id => { $dr(id).value = ''; }); dr_load(); });
    $dr('dr-refresh').addEventListener('click', dr_load);
    $dr('dr-act-save').addEventListener('click', dr_saveStep);
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const open = ['dr-act-overlay', 'dr-det-overlay'].find(id => $dr(id).classList.contains('open'));
        if (open) dr_close(open);
    });
    dr_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', dr_boot);
else dr_boot();
</script>
