<?php
namespace PHPMaker2024\FIMS;

@session_start();

// Payroll Records List — view and manage payroll per employee per period.
// Create, edit, approve, and release payroll records for payment.

$_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_api = preg_match('~/api$~', $_api) ? $_api : $_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="pr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Payroll Records</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="pr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="pr-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Create new payroll record">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Record
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="pr-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4v2m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="pr-error-msg">An error occurred.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Payroll Period</label>
                <select id="pr-filter-period" class="nha-filter-select">
                    <option value="">Select period…</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="pr-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="DRAFT">Draft</option>
                    <option value="FOR_REVIEW">For Review</option>
                    <option value="APPROVED">Approved</option>
                    <option value="RELEASED">Released</option>
                    <option value="PAID">Paid</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="pr-filter-search" class="nha-filter-input" placeholder="Employee name, no…">
                </div>
            </div>
        </div>
    </div>

    <!-- Period Summary -->
    <div id="pr-summary" style="display:none;padding:12px 16px;background:var(--afims-primary-soft,#E6EBF6);border-radius:10px;margin-bottom:14px;font-size:13px;">
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
            <div>
                <div style="color:var(--afims-text-muted,#62657E);font-weight:600;">Employees</div>
                <div style="font-size:18px;font-weight:700;color:var(--afims-primary-text,#0035A9);" id="pr-sum-employees">0</div>
            </div>
            <div>
                <div style="color:var(--afims-text-muted,#62657E);font-weight:600;">Total Gross</div>
                <div style="font-size:18px;font-weight:700;color:var(--afims-primary-text,#0035A9);">₱ <span id="pr-sum-gross">0.00</span></div>
            </div>
            <div>
                <div style="color:var(--afims-text-muted,#62657E);font-weight:600;">Total Deductions</div>
                <div style="font-size:18px;font-weight:700;color:var(--afims-danger,#DC3545);">₱ <span id="pr-sum-deductions">0.00</span></div>
            </div>
            <div>
                <div style="color:var(--afims-text-muted,#62657E);font-weight:600;">Total Net</div>
                <div style="font-size:18px;font-weight:700;color:var(--afims-success-hover,#0C7539);">₱ <span id="pr-sum-net">0.00</span></div>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th class="text-end">Gross Amount</th>
                        <th class="text-end">Deductions</th>
                        <th class="text-end">Net Amount</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="pr-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /#pr-root -->

<script>
const PR_API = '<?= $_api ?>/payroll';

let pr_periods = [];
let pr_records = [];
let pr_currentPeriodId = null;

async function pr_boot() {
    pr_bindEvents();
    await pr_loadPeriods();
    // Default to latest period
    const latestPeriod = pr_periods[0];
    if (latestPeriod) {
        document.getElementById('pr-filter-period').value = latestPeriod.payroll_period_id;
        pr_currentPeriodId = latestPeriod.payroll_period_id;
        pr_load();
    }
}

function pr_bindEvents() {
    document.getElementById('pr-refresh')?.addEventListener('click', pr_load);
    document.getElementById('pr-add')?.addEventListener('click', () => alert('New record form coming soon'));
    document.getElementById('pr-filter-period')?.addEventListener('change', (e) => {
        pr_currentPeriodId = parseInt(e.target.value) || null;
        pr_load();
    });
    document.getElementById('pr-filter-status')?.addEventListener('change', pr_load);
    document.getElementById('pr-filter-search')?.addEventListener('input', pr_load);
}

async function pr_loadPeriods() {
    try {
        const res = await fetch(`${PR_API}/periods`);
        const json = await res.json();

        if (json.success) {
            pr_periods = json.data || [];
            const select = document.getElementById('pr-filter-period');
            pr_periods.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.payroll_period_id;
                opt.textContent = p.period_name || `FY ${p.fiscal_yr} - Month ${p.payroll_month}`;
                select.appendChild(opt);
            });
        }
    } catch (e) {
        console.error('Failed to load periods:', e);
    }
}

async function pr_load() {
    if (!pr_currentPeriodId) return;

    try {
        pr_showError(false);
        const params = new URLSearchParams({
            status: document.getElementById('pr-filter-status')?.value || '',
            search: document.getElementById('pr-filter-search')?.value || '',
        });

        const res = await fetch(`${PR_API}/records/${pr_currentPeriodId}?${params}`);
        const json = await res.json();

        if (!json.success) throw new Error(json.message || 'Failed to load records.');

        pr_records = json.data || [];
        pr_renderTable(pr_records);
        await pr_loadSummary();
    } catch (e) {
        pr_showError(e.message);
    }
}

async function pr_loadSummary() {
    if (!pr_currentPeriodId) return;

    try {
        const res = await fetch(`${PR_API}/periods/${pr_currentPeriodId}/summary`);
        const json = await res.json();

        if (json.success && json.data) {
            const data = json.data;
            document.getElementById('pr-sum-employees').textContent = data.employee_count || 0;
            document.getElementById('pr-sum-gross').textContent = (data.total_gross || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('pr-sum-deductions').textContent = (data.total_deductions || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('pr-sum-net').textContent = (data.total_net || 0).toLocaleString('en-PH', {minimumFractionDigits: 2});
            document.getElementById('pr-summary').style.display = 'block';
        }
    } catch (e) {
        console.error('Failed to load summary:', e);
    }
}

function pr_renderTable(records) {
    const tbody = document.getElementById('pr-tbody');
    if (!records.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No payroll records found.</span></div></td></tr>';
        return;
    }

    tbody.innerHTML = records.map(r => `<tr>
        <td><strong>${pr_esc(r.full_name)}</strong><br><span style="font-size:11px;color:var(--afims-text-muted,#62657E);">${pr_esc(r.employee_no)}</span></td>
        <td>${pr_esc(r.position || '—')}</td>
        <td class="text-end nha-mono">₱ ${(r.gross_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
        <td class="text-end nha-mono">${(r.total_deductions || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
        <td class="text-end nha-mono" style="font-weight:600;">₱ ${(r.net_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
        <td><span class="nha-bdg ${pr_statusClass(r.status)}">${pr_esc(r.status)}</span></td>
        <td class="no-print" style="white-space:nowrap;">
            <button type="button" class="nha-btn-act view" onclick="alert('View/Edit coming soon')" title="View">View</button>
            <button type="button" class="nha-btn-act edit" onclick="alert('Edit coming soon')" title="Edit">Edit</button>
        </td>
    </tr>`).join('');
}

function pr_showError(show, msg = '') {
    const el = document.getElementById('pr-error');
    if (show) {
        document.getElementById('pr-error-msg').textContent = msg;
        el.style.display = 'flex';
    } else {
        el.style.display = 'none';
    }
}

function pr_esc(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

function pr_statusClass(status) {
    const map = {
        DRAFT: 'nha-bdg-gray',
        FOR_REVIEW: 'nha-bdg-amber',
        APPROVED: 'nha-bdg-blue',
        RELEASED: 'nha-bdg-purple',
        PAID: 'nha-bdg-green',
        REVERSED: 'nha-bdg-red',
    };
    return map[status] || 'nha-bdg-gray';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pr_boot);
} else {
    pr_boot();
}
</script>
