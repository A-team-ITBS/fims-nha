<?php
namespace PHPMaker2024\FIMS;

@session_start();

// Payroll Remittances Dashboard — track withholdings remitted to BIR, SSS,
// PhilHealth, Pag-IBIG. Manage submission status and confirm receipts.

$_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_api = preg_match('~/api$~', $_api) ? $_api : $_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="rm-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Payroll Remittances</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="rm-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="rm-create" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Create remittances for period">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Create Remittances
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="rm-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4v2m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="rm-error-msg">An error occurred.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Payroll Period</label>
                <select id="rm-filter-period" class="nha-filter-select">
                    <option value="">Select period…</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Agency</label>
                <select id="rm-filter-agency" class="nha-filter-select">
                    <option value="">All Agencies</option>
                    <option value="BIR">BIR (Bureau of Internal Revenue)</option>
                    <option value="SSS">SSS (Social Security)</option>
                    <option value="PHILHEALTH">PhilHealth</option>
                    <option value="PAGIBIG">Pag-IBIG</option>
                    <option value="INTERNAL">Internal</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="rm-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="PENDING">Pending</option>
                    <option value="SUBMITTED">Submitted</option>
                    <option value="CONFIRMED">Confirmed</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Summary Cards -->
    <div id="rm-summary" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:14px;"></div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Agency</th>
                        <th>Deduction Type</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Remittance No</th>
                        <th>Receipt No</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="rm-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /#rm-root -->

<script>
const RM_API = '<?= $_api ?>/payroll';

let rm_periods = [];
let rm_remittances = [];
let rm_currentPeriodId = null;

async function rm_boot() {
    rm_bindEvents();
    await rm_loadPeriods();
    const latestPeriod = rm_periods[0];
    if (latestPeriod) {
        document.getElementById('rm-filter-period').value = latestPeriod.payroll_period_id;
        rm_currentPeriodId = latestPeriod.payroll_period_id;
        rm_load();
    }
}

function rm_bindEvents() {
    document.getElementById('rm-refresh')?.addEventListener('click', rm_load);
    document.getElementById('rm-create')?.addEventListener('click', rm_createRemittances);
    document.getElementById('rm-filter-period')?.addEventListener('change', (e) => {
        rm_currentPeriodId = parseInt(e.target.value) || null;
        rm_load();
    });
    document.getElementById('rm-filter-agency')?.addEventListener('change', rm_load);
    document.getElementById('rm-filter-status')?.addEventListener('change', rm_load);
}

async function rm_loadPeriods() {
    try {
        const res = await fetch(`${RM_API}/periods`);
        const json = await res.json();

        if (json.success) {
            rm_periods = json.data || [];
            const select = document.getElementById('rm-filter-period');
            rm_periods.forEach(p => {
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

async function rm_load() {
    if (!rm_currentPeriodId) return;

    try {
        rm_showError(false);
        const params = new URLSearchParams({
            agency: document.getElementById('rm-filter-agency')?.value || '',
            status: document.getElementById('rm-filter-status')?.value || '',
        });

        const res = await fetch(`${RM_API}/remittances/${rm_currentPeriodId}?${params}`);
        const json = await res.json();

        if (!json.success) throw new Error(json.message || 'Failed to load remittances.');

        rm_remittances = json.data || [];
        rm_renderTable(rm_remittances);
        await rm_loadSummary();
    } catch (e) {
        rm_showError(e.message);
    }
}

async function rm_loadSummary() {
    if (!rm_currentPeriodId) return;

    try {
        const res = await fetch(`${RM_API}/remittances/${rm_currentPeriodId}/summary`);
        const json = await res.json();

        if (json.success && json.data) {
            const data = json.data;
            const summaryEl = document.getElementById('rm-summary');
            summaryEl.innerHTML = '';

            // Group by agency
            const byAgency = {};
            data.forEach(row => {
                if (!byAgency[row.remittance_agency]) {
                    byAgency[row.remittance_agency] = {total: 0, statuses: {}};
                }
                byAgency[row.remittance_agency].total += parseFloat(row.total_amount || 0);
                byAgency[row.remittance_agency].statuses[row.remittance_status] = (byAgency[row.remittance_agency].statuses[row.remittance_status] || 0) + row.count;
            });

            Object.entries(byAgency).forEach(([agency, stats]) => {
                const card = document.createElement('div');
                card.className = 'nha-card';
                card.style.cssText = 'border:1px solid var(--afims-border,#E4ECF2);border-radius:10px;padding:12px;background:var(--afims-surface,#fff);';

                const statusBadges = Object.entries(stats.statuses)
                    .map(([s, c]) => `<span class="nha-bdg" style="font-size:10px;margin-right:4px;margin-top:4px;">${s} (${c})</span>`)
                    .join('');

                card.innerHTML = `
                    <div style="font-weight:600;font-size:12px;color:var(--afims-text-muted,#62657E);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">${agency}</div>
                    <div style="font-size:18px;font-weight:700;color:var(--afims-primary-text,#0035A9);margin-bottom:8px;">₱ ${stats.total.toLocaleString('en-PH', {minimumFractionDigits: 2})}</div>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;">${statusBadges}</div>
                `;
                summaryEl.appendChild(card);
            });
        }
    } catch (e) {
        console.error('Failed to load summary:', e);
    }
}

function rm_renderTable(remittances) {
    const tbody = document.getElementById('rm-tbody');
    if (!remittances.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No remittances found.</span></div></td></tr>';
        return;
    }

    tbody.innerHTML = remittances.map(r => `<tr>
        <td><strong>${rm_esc(r.remittance_agency)}</strong></td>
        <td>${rm_esc(r.deduction_type_id ? 'Multiple' : 'Consolidated')}</td>
        <td class="text-end nha-mono">₱ ${(r.total_amount || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
        <td><span class="nha-bdg ${rm_statusClass(r.remittance_status)}">${rm_esc(r.remittance_status)}</span></td>
        <td class="nha-mono" style="font-size:11px;">${rm_esc(r.remittance_no || '—')}</td>
        <td class="nha-mono" style="font-size:11px;">${rm_esc(r.remittance_receipt_no || '—')}</td>
        <td class="no-print" style="white-space:nowrap;font-size:12px;">
            ${r.remittance_status === 'PENDING' ? `<button type="button" class="nha-btn-act" onclick="rm_submit(${r.payroll_remittance_id})" style="padding:4px 8px;">Submit</button>` : ''}
            ${r.remittance_status === 'SUBMITTED' ? `<button type="button" class="nha-btn-act" onclick="rm_confirm(${r.payroll_remittance_id})" style="padding:4px 8px;color:var(--afims-success-hover,#0C7539);">Confirm</button>` : ''}
        </td>
    </tr>`).join('');
}

async function rm_createRemittances() {
    if (!rm_currentPeriodId) {
        alert('Please select a payroll period.');
        return;
    }

    if (!confirm('Create remittances for this period? One per agency will be created.')) return;

    try {
        const res = await fetch(`${RM_API}/remittances/${rm_currentPeriodId}/create`, {method: 'POST'});
        const json = await res.json();

        if (json.success) {
            alert(json.message);
            rm_load();
        } else {
            throw new Error(json.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function rm_submit(remittanceId) {
    const remittanceNo = prompt('Enter remittance number (e.g., DV-2025-001):');
    if (!remittanceNo) return;

    try {
        const res = await fetch(`${RM_API}/remittances/${remittanceId}/submit`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({remittance_no: remittanceNo}),
        });
        const json = await res.json();

        if (json.success) {
            alert(json.message);
            rm_load();
        } else {
            throw new Error(json.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function rm_confirm(remittanceId) {
    const receiptNo = prompt('Enter agency receipt number:');
    if (!receiptNo) return;

    try {
        const res = await fetch(`${RM_API}/remittances/${remittanceId}/confirm`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({receipt_no: receiptNo, receipt_date: new Date().toISOString().split('T')[0]}),
        });
        const json = await res.json();

        if (json.success) {
            alert(json.message);
            rm_load();
        } else {
            throw new Error(json.message);
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

function rm_showError(show, msg = '') {
    const el = document.getElementById('rm-error');
    if (show) {
        document.getElementById('rm-error-msg').textContent = msg;
        el.style.display = 'flex';
    } else {
        el.style.display = 'none';
    }
}

function rm_esc(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

function rm_statusClass(status) {
    const map = {
        PENDING: 'nha-bdg-amber',
        SUBMITTED: 'nha-bdg-blue',
        CONFIRMED: 'nha-bdg-green',
        CANCELLED: 'nha-bdg-red',
    };
    return map[status] || 'nha-bdg-gray';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rm_boot);
} else {
    rm_boot();
}
</script>
