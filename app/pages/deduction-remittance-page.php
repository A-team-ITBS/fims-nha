<?php

namespace PHPMaker2024\FIMS;

/**
 * deduction-remittance-page.php — Deductions & Remittances (Payroll section). One page for both modules: the deductions
 * withheld in each imported payroll month (Payroll Management), per agency, ARE what is remitted — Accounting schedules
 * a month's agency remittance for Treasury, Treasury records that it was remitted, then the agency's receipt confirms it.
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
                <div class="nha-hdr-sub">Deductions withheld in each imported payroll month, per agency — scheduled for Treasury and remitted.</div>
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
        <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">Pending Schedule</div><div class="nha-sc-val" id="dr-k-pending">—</div></div></div><p class="nha-kpi-sub" id="dr-k-pending-sub">Not yet scheduled for Treasury</p></div>
        <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">For Treasury</div><div class="nha-sc-val" id="dr-k-scheduled">—</div></div></div><p class="nha-kpi-sub" id="dr-k-scheduled-sub">Scheduled, awaiting remittance</p></div>
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
                <label class="nha-filter-label" for="dr-f-agency">Agency</label>
                <select id="dr-f-agency" class="nha-filter-select"><option value="">All Agencies</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="dr-f-status">Status</label>
                <select id="dr-f-status" class="nha-filter-select">
                    <option value="">All Statuses</option>
                    <option value="PENDING">Pending schedule</option>
                    <option value="SCHEDULED">For Treasury (scheduled)</option>
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
                        <th>Payroll Month</th>
                        <th>Agency</th>
                        <th class="text-end">Employee Share</th>
                        <th class="text-end">Gov't Share</th>
                        <th class="text-end">Total to Remit</th>
                        <th>Payroll DV</th>
                        <th>Status</th>
                        <th>Due / Remitted</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="dr-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
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

    <!-- Step forms: schedule / remit / confirm -->
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
const DR_VOUCHER_URL = <?= json_encode(GetUrl('Voucher')) ?>; // the payroll DV (View DV / Create DV)
const DR_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const DR_STATUS = {
    PENDING:      ['nha-bdg-amber', 'Pending schedule'],
    SCHEDULED:    ['nha-bdg-blue', 'For Treasury'],
    REMITTED:     ['nha-bdg-green', 'Remitted'],
    CONFIRMED:    ['nha-bdg-green', 'Confirmed'],
    NOT_REMITTED: ['nha-bdg-gray', 'Collected by NHA'],
};
const $dr = id => document.getElementById(id);
let dr_rows = [], dr_can = {}, dr_agencies = {};

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
    ['year', 'month', 'agency', 'status'].forEach(k => { const v = $dr('dr-f-' + k).value; if (v) params.set(k, v); });
    const body = $dr('dr-tbody');
    body.innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const json = await dr_json(await dr_authFetch('deduction-remittance/list?' + params));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        dr_can = json.can || {};
        dr_agencies = json.agencies || {};
        dr_fillFilters(json.years || []);
        const k = json.kpis || {};
        $dr('dr-k-withheld').textContent  = dr_peso(k.withheld);
        $dr('dr-k-pending').textContent   = dr_peso(k.pending);
        $dr('dr-k-scheduled').textContent = dr_peso(k.scheduled);
        $dr('dr-k-remitted').textContent  = dr_peso(k.remitted);
        $dr('dr-k-pending-sub').textContent   = `${k.pending_count ?? 0} not yet scheduled for Treasury`;
        $dr('dr-k-scheduled-sub').textContent = `${k.scheduled_count ?? 0} scheduled, awaiting remittance`;
        $dr('dr-k-remitted-sub').textContent  = `${k.remitted_count ?? 0} remitted / confirmed`;
        dr_rows = json.data || [];
        if (!dr_rows.length) {
            body.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No deductions to show.</span><div class="nha-empty-text">Deductions appear here once a payroll month is imported in Payroll Management.</div></div></td></tr>';
            return;
        }
        body.innerHTML = dr_rows.map((r, i) => {
            const [cls, txt] = DR_STATUS[r.status] || ['nha-bdg-gray', r.status];
            const dv = r.payroll_dv_id
                ? `<a href="${DR_VOUCHER_URL}?dv=${Number(r.payroll_dv_id)}" class="dr-link nha-mono">${dr_esc(r.payroll_dv_no)}</a><div class="dr-hint">${dr_esc(r.payroll_dv_status || '')}</div>`
                : `<span class="dr-hint">No payroll DV yet</span>`;
            let when = '—';
            if (r.status === 'SCHEDULED') when = `Due ${dr_esc(dr_date(r.due_date))}`;
            if (r.status === 'REMITTED' || r.status === 'CONFIRMED') when = `${dr_esc(dr_date(r.remittance_date))}<div class="dr-hint">${dr_esc(r.remittance_method || '')} · ${dr_esc(r.remittance_no || '')}${r.receipt_no ? ' · OR ' + dr_esc(r.receipt_no) : ''}</div>`;
            const amountNote = r.scheduled_amount !== null && Math.abs(r.scheduled_amount - r.total) >= 0.005 ? `<div class="dr-hint">scheduled ${dr_peso(r.scheduled_amount)}</div>` : '';
            return `<tr>
                <td><strong>${dr_esc(r.month_name)}</strong> ${dr_esc(r.fiscal_yr)}</td>
                <td><a href="#" class="dr-link" title="View the items and employees" onclick="dr_openDetail(${i}); return false;"><strong>${dr_esc(r.agency_name)}</strong></a><div class="dr-hint">${dr_esc(r.employees)} employees</div></td>
                <td class="dr-num">${dr_amt(r.employee_share)}</td>
                <td class="dr-num">${dr_amt(r.employer_share)}</td>
                <td class="dr-num"><strong>${dr_peso(r.total)}</strong>${amountNote}</td>
                <td>${dv}</td>
                <td><span class="nha-bdg ${cls}">${dr_esc(txt)}</span></td>
                <td>${when}</td>
                <td style="white-space:nowrap;">${dr_actions(r, i)}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        body.innerHTML = `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${dr_esc(e.message)}</span></div></td></tr>`;
    }
}

/** The one next step for the row, for whoever may do it. */
function dr_actions(r, i) {
    if (r.status === 'PENDING') {
        if (!r.payroll_dv_id) return `<a href="${DR_VOUCHER_URL}?payroll=${Number(r.payroll_period_id)}" class="nha-btn-act" title="The deductions are remitted once the payroll is paid">Create Payroll DV</a>`;
        return dr_can.schedule ? `<button type="button" class="nha-btn-act dr-btn-go" onclick="dr_openStep('schedule', ${i})">Schedule for Treasury</button>` : '<span class="dr-hint">Waiting for Accounting</span>';
    }
    if (r.status === 'SCHEDULED') {
        return (dr_can.remit ? `<button type="button" class="nha-btn-act dr-btn-tr" onclick="dr_openStep('remit', ${i})">Record Remittance</button> ` : '<span class="dr-hint">Waiting for Treasury</span> ')
            + (dr_can.schedule ? `<button type="button" class="nha-btn-act" onclick="dr_unschedule(${i})">Unschedule</button>` : '');
    }
    if (r.status === 'REMITTED') {
        return dr_can.confirm ? `<button type="button" class="nha-btn-act" onclick="dr_openStep('confirm', ${i})">Confirm Receipt</button>` : '<span class="dr-hint">Awaiting receipt</span>';
    }
    if (r.status === 'CONFIRMED') return '<span class="dr-hint">Done</span>';
    return '<span class="dr-hint">Not remitted</span>';
}

function dr_fillFilters(years) {
    const y = $dr('dr-f-year'), yc = y.value;
    y.innerHTML = '<option value="">All Years</option>' + years.map(v => `<option value="${v}">${v}</option>`).join('');
    y.value = yc;
    const a = $dr('dr-f-agency'), ac = a.value;
    a.innerHTML = '<option value="">All Agencies</option>' + Object.entries(dr_agencies).map(([k, v]) => `<option value="${dr_esc(k)}">${dr_esc(v)}</option>`).join('');
    a.value = ac;
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

/* ── Steps: schedule (Accounting) · remit (Treasury) · confirm receipt ── */
let dr_step = null;
function dr_nextMonth10(r) { // default due date: the 10th of the month after the payroll month
    const y = r.payroll_month === 12 ? r.fiscal_yr + 1 : r.fiscal_yr, m = r.payroll_month === 12 ? 1 : r.payroll_month + 1;
    return `${y}-${String(m).padStart(2, '0')}-10`;
}
function dr_openStep(kind, i) {
    const r = dr_rows[i];
    dr_step = { kind, r };
    const amount = r.scheduled_amount ?? r.total;
    $dr('dr-act-sum').innerHTML = `<dt>Payroll month</dt><dd>${dr_esc(r.label)}</dd><dt>Agency</dt><dd>${dr_esc(r.agency_name)}</dd>`
        + `<dt>Employee share</dt><dd>${dr_peso(r.employee_share)}</dd><dt>Gov't share</dt><dd>${dr_peso(r.employer_share)}</dd>`
        + `<dt>Total to remit</dt><dd>${dr_peso(amount)}</dd>` + (r.due_date ? `<dt>Due date</dt><dd>${dr_esc(dr_date(r.due_date))}</dd>` : '');
    const f = $dr('dr-act-form');
    if (kind === 'schedule') {
        $dr('dr-act-title').textContent = 'Schedule Remittance for Treasury';
        $dr('dr-act-save').textContent = 'Schedule for Treasury';
        f.innerHTML = `<div><label for="dr-in-due">Due date <span class="nha-req">*</span></label><input type="date" id="dr-in-due" value="${dr_nextMonth10(r)}"></div>
            <div class="dr-full"><label for="dr-in-remarks">Remarks</label><textarea id="dr-in-remarks" rows="2" placeholder="Optional note for Treasury"></textarea></div>`;
    } else if (kind === 'remit') {
        $dr('dr-act-title').textContent = 'Record Remittance';
        $dr('dr-act-save').textContent = 'Record Remittance';
        f.innerHTML = `<div><label for="dr-in-date">Remittance date <span class="nha-req">*</span></label><input type="date" id="dr-in-date" value="${dr_today()}"></div>
            <div><label for="dr-in-method">Method <span class="nha-req">*</span></label><select id="dr-in-method"><option value="ONLINE">Online</option><option value="BANK_TRANSFER">Bank transfer</option><option value="CHECK">Check</option><option value="MANUAL">Manual</option></select></div>
            <div class="dr-full"><label for="dr-in-ref">Reference no. <span class="nha-req">*</span></label><input type="text" id="dr-in-ref" placeholder="Payment / transaction / check no."></div>`;
    } else {
        $dr('dr-act-title').textContent = 'Confirm Receipt';
        $dr('dr-act-save').textContent = 'Confirm';
        f.innerHTML = `<div><label for="dr-in-or">Agency receipt / confirmation no. <span class="nha-req">*</span></label><input type="text" id="dr-in-or"></div>
            <div><label for="dr-in-ordate">Receipt date <span class="nha-req">*</span></label><input type="date" id="dr-in-ordate" value="${dr_today()}"></div>`;
    }
    $dr('dr-act-msg').className = 'dr-msg';
    $dr('dr-act-save').disabled = false;
    $dr('dr-act-overlay').classList.add('open');
}

async function dr_saveStep() {
    if (!dr_step) return;
    const { kind, r } = dr_step;
    let path, body;
    if (kind === 'schedule') {
        path = 'deduction-remittance/schedule';
        body = { payroll_period_id: r.payroll_period_id, agency: r.agency, due_date: $dr('dr-in-due').value, remarks: $dr('dr-in-remarks').value.trim() };
    } else if (kind === 'remit') {
        path = `deduction-remittance/${Number(r.payroll_remittance_id)}/remit`;
        body = { remittance_date: $dr('dr-in-date').value, remittance_method: $dr('dr-in-method').value, remittance_no: $dr('dr-in-ref').value.trim() };
    } else {
        path = `deduction-remittance/${Number(r.payroll_remittance_id)}/confirm`;
        body = { receipt_no: $dr('dr-in-or').value.trim(), receipt_date: $dr('dr-in-ordate').value };
    }
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

async function dr_unschedule(i) {
    const r = dr_rows[i];
    if (!confirm(`Unschedule the ${r.label} ${r.agency_name} remittance? It goes back to Pending.`)) return;
    const json = await dr_json(await dr_authFetch(`deduction-remittance/${Number(r.payroll_remittance_id)}/unschedule`, { method: 'POST' }));
    if (!json.success) { alert(json.message || 'Failed.'); return; }
    dr_load();
}

/* ── Boot ───────────────────────────────────────────────────── */
function dr_boot() {
    $dr('dr-f-month').innerHTML = '<option value="">All Months</option>' + DR_MONTHS.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
    ['dr-f-year', 'dr-f-month', 'dr-f-agency', 'dr-f-status'].forEach(id => $dr(id).addEventListener('change', dr_load));
    $dr('dr-reset').addEventListener('click', () => { ['dr-f-year', 'dr-f-month', 'dr-f-agency', 'dr-f-status'].forEach(id => { $dr(id).value = ''; }); dr_load(); });
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
