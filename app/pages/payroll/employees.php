<?php
namespace PHPMaker2024\FIMS;

@session_start();

// Payroll Employees List — manage NHA staff for payroll processing.
// Includes CSV import, status updates, and bulk management.

$_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_api = preg_match('~/api$~', $_api) ? $_api : $_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="pe-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.856-1.487M15 10a3 3 0 11-6 0 3 3 0 016 0zM16 16a5 5 0 10-10 0v2h10v-2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Payroll Employees</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="pe-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="pe-import" type="button" class="nha-btn-hdr" style="background:var(--afims-info, #2563eb);" title="Import from CSV">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Import CSV
            </button>
            <button id="pe-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add new employee">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Employee
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="pe-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4v2m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="pe-error-msg">An error occurred.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="pe-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="ON_LEAVE">On Leave</option>
                    <option value="SEPARATED">Separated</option>
                    <option value="RETIRED">Retired</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Department</label>
                <input type="text" id="pe-filter-dept" class="nha-filter-input" placeholder="Search department…">
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="pe-filter-search" class="nha-filter-input" placeholder="Name, employee no, TIN…">
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Employee No</th>
                        <th>Name</th>
                        <th>TIN</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th class="text-end">Monthly Salary</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="pe-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /#pe-root -->

<!-- Import Modal -->
<div id="pe-import-overlay" class="nha-overlay no-print" style="display:none;">
    <div class="nha-modal nha-modal-med" role="dialog" aria-modal="true">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title">Import Employees from CSV</h3>
            <button type="button" class="nha-modal-close" onclick="pe_closeImport()" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body">
            <p style="color:var(--afims-text-muted, #62657E);font-size:13px;margin-bottom:12px;">
                Upload a CSV file with columns: employee_no, first_name, last_name, middle_name, tin, sss_no, position, department, monthly_salary, date_hired
            </p>
            <div class="nha-fg">
                <label>CSV File</label>
                <input type="file" id="pe-import-file" accept=".csv" />
            </div>
            <div id="pe-import-result" style="margin-top:12px;padding:12px;border-radius:8px;display:none;"></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-ghost" onclick="pe_closeImport()">Cancel</button>
            <button type="button" class="nha-btn nha-btn-primary" onclick="pe_doImport()">Import</button>
        </div>
    </div>
</div>

<script>
const PE_API = '<?= $_api ?>/payroll';

let pe_page = 1;
let pe_employees = [];

async function pe_boot() {
    pe_bindEvents();
    pe_load();
}

function pe_bindEvents() {
    document.getElementById('pe-refresh')?.addEventListener('click', pe_load);
    document.getElementById('pe-add')?.addEventListener('click', () => alert('Add employee form coming soon'));
    document.getElementById('pe-import')?.addEventListener('click', pe_openImport);

    document.getElementById('pe-filter-status')?.addEventListener('change', pe_load);
    document.getElementById('pe-filter-dept')?.addEventListener('input', pe_load);
    document.getElementById('pe-filter-search')?.addEventListener('input', pe_load);
}

async function pe_load() {
    try {
        pe_showError(false);
        const params = new URLSearchParams({
            status: document.getElementById('pe-filter-status')?.value || '',
            department: document.getElementById('pe-filter-dept')?.value || '',
            search: document.getElementById('pe-filter-search')?.value || '',
        });

        const res = await fetch(`${PE_API}/employees?${params}`);
        const json = await res.json();

        if (!json.success) throw new Error(json.message || 'Failed to load employees.');

        pe_employees = json.data || [];
        pe_renderTable(pe_employees);
    } catch (e) {
        pe_showError(e.message);
    }
}

function pe_renderTable(employees) {
    const tbody = document.getElementById('pe-tbody');
    if (!employees.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No employees found.</span></div></td></tr>';
        return;
    }

    tbody.innerHTML = employees.map(emp => `<tr>
        <td class="nha-mono">${pe_esc(emp.employee_no)}</td>
        <td>${pe_esc(emp.full_name)}</td>
        <td class="nha-mono">${pe_esc(emp.tin || '—')}</td>
        <td>${pe_esc(emp.position || '—')}</td>
        <td>${pe_esc(emp.department || '—')}</td>
        <td class="text-end nha-mono">₱ ${(emp.monthly_salary || 0).toLocaleString('en-PH', {minimumFractionDigits: 2})}</td>
        <td><span class="nha-bdg ${pe_statusClass(emp.employment_status)}">${pe_esc(emp.employment_status)}</span></td>
        <td class="no-print" style="white-space:nowrap;">
            <button type="button" class="nha-btn-act view" onclick="alert('View/Edit coming soon')" title="View">View</button>
        </td>
    </tr>`).join('');
}

function pe_openImport() {
    document.getElementById('pe-import-overlay').style.display = 'flex';
    document.getElementById('pe-import-file').value = '';
    document.getElementById('pe-import-result').style.display = 'none';
}

function pe_closeImport() {
    document.getElementById('pe-import-overlay').style.display = 'none';
}

async function pe_doImport() {
    const file = document.getElementById('pe-import-file').files[0];
    if (!file) {
        alert('Please select a file.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('file', file);

        const res = await fetch(`${PE_API}/employees/import`, {
            method: 'POST',
            body: formData,
        });

        const json = await res.json();
        const resultEl = document.getElementById('pe-import-result');

        if (json.success) {
            resultEl.style.display = 'block';
            resultEl.className = 'nha-success-soft';
            resultEl.innerHTML = `<strong style="color:var(--afims-success-hover, #0C7539);">${json.message}</strong>
                <div style="font-size:12px;margin-top:8px;">
                    Imported: ${json.data.imported} | Updated: ${json.data.updated}
                    ${json.data.errors.length ? `<br><br><strong style="color:var(--afims-danger,#DC3545);">Errors:</strong><ul style="margin:4px 0 0;padding-left:18px;">${json.data.errors.slice(0, 5).map(e => `<li>${e}</li>`).join('')}</ul>` : ''}
                </div>`;

            setTimeout(() => {
                pe_closeImport();
                pe_load();
            }, 2000);
        } else {
            throw new Error(json.message);
        }
    } catch (e) {
        alert('Import error: ' + e.message);
    }
}

function pe_showError(show, msg = '') {
    const el = document.getElementById('pe-error');
    if (show) {
        document.getElementById('pe-error-msg').textContent = msg;
        el.style.display = 'flex';
    } else {
        el.style.display = 'none';
    }
}

function pe_esc(text) {
    const d = document.createElement('div');
    d.textContent = text || '';
    return d.innerHTML;
}

function pe_statusClass(status) {
    const map = {
        ACTIVE: 'nha-bdg-green',
        ON_LEAVE: 'nha-bdg-amber',
        SEPARATED: 'nha-bdg-red',
        RETIRED: 'nha-bdg-gray',
    };
    return map[status] || 'nha-bdg-gray';
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pe_boot);
} else {
    pe_boot();
}
</script>
