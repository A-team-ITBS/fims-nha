<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_bgr_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/nha-fims.css') ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .bgr-summary-2col { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div id="bgr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Budget Requirements</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="bgr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="bgr-add" type="button" class="nha-btn-primary">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Budget Request
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary bgr-summary-2col">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Requests</div>
                    <div class="nha-sc-val" id="bgr-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Amount</div>
                    <div class="nha-sc-val" id="bgr-kpi-amount">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <input type="number" id="bgr-filter-fiscal-yr" class="nha-filter-input" placeholder="All Years" min="2000" max="2100">
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Allocated To</label>
                <select id="bgr-filter-type" class="nha-filter-select">
                    <option value="">All</option>
                    <option value="PROGRAM">Program</option>
                    <option value="PROJECT">Project</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Fiscal Year</th>
                        <th>Allocated To</th>
                        <th>Program / Project</th>
                        <th>Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bgr-tbody">
                    <tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="bgr-from">0</span>–<span id="bgr-to">0</span></strong> of <strong><span id="bgr-total">0</span></strong></span>
            <div id="bgr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

</div><!-- /#bgr-root -->

<?php include __DIR__ . '/form/budget-req-form.php'; ?>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REQUIREMENTS LIST   prefix: bgr_
═══════════════════════════════════════════════════════════════ */

const BGR_API         = '<?php echo $_bgr_api; ?>' + '/budget_req';
const BGR_PROGRAM_API = '<?php echo $_bgr_api; ?>' + '/program/all';
const BGR_PROJECT_API = '<?php echo $_bgr_api; ?>' + '/project/all';

let bgr_page = 1;
const BGR_PAGE_SIZE = 15;

const $bgr = id => document.getElementById(id);

async function bgr_bootstrapLoad() {
    await Promise.all([bgr_load(), bgr_loadPrograms(), bgr_loadProjects()]);
    bgr_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_bootstrapLoad);
} else {
    bgr_bootstrapLoad();
}

async function bgr_loadPrograms() {
    try {
        const res  = await fetch(BGR_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load programs.');
        $bgr('bgr-program-code').insertAdjacentHTML('beforeend', (json.data ?? []).map(p =>
            `<option value="${bgr_escAttr(p.program_code)}">${bgr_esc(p.program_code)} — ${bgr_esc(p.program_name)}</option>`
        ).join(''));
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

async function bgr_loadProjects() {
    try {
        const res  = await fetch(BGR_PROJECT_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load projects.');
        $bgr('bgr-project-code').insertAdjacentHTML('beforeend', (json.data ?? []).map(p =>
            `<option value="${bgr_escAttr(p.project_code)}">${bgr_esc(p.project_code)} — ${bgr_esc(p.project_name)}</option>`
        ).join(''));
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

function bgr_bindEvents() {
    $bgr('bgr-refresh')?.addEventListener('click', () => { bgr_page = 1; bgr_load(); });
    $bgr('bgr-add')?.addEventListener('click', () => bgr_openModal('add'));
    $bgr('bgr-modal-close')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-cancel')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-form')?.addEventListener('submit', bgr_save);
    $bgr('bgr-type')?.addEventListener('change', bgr_toggleTypeFields);

    ['bgr-filter-fiscal-yr', 'bgr-filter-type'].forEach(id => {
        $bgr(id)?.addEventListener('change', () => { bgr_page = 1; bgr_load(); });
    });
}

function bgr_toggleTypeFields() {
    const isProgram = $bgr('bgr-type').value === 'PROGRAM';
    $bgr('bgr-program-wrap').hidden = !isProgram;
    $bgr('bgr-project-wrap').hidden = isProgram;
    $bgr('bgr-program-code').required = isProgram;
    $bgr('bgr-project-code').required = !isProgram;
}

/* ── Load ──────────────────────────────────────────────────── */
async function bgr_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            fiscal_yr : $bgr('bgr-filter-fiscal-yr')?.value || '',
            type      : $bgr('bgr-filter-type')?.value      || '',
            page      : bgr_page,
            limit     : BGR_PAGE_SIZE,
        });
        const res  = await fetch(`${BGR_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        bgr_renderTable(json.data ?? []);
        bgr_renderPagination(json.pagination ?? {});
        $bgr('bgr-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $bgr('bgr-kpi-amount').textContent = bgr_fmtMoney(json.total_amount ?? 0);
    } catch (e) {
        bgr_renderTable([]);
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function bgr_renderTable(rows) {
    const tbody = $bgr('bgr-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget requests found.</span></div></td></tr>';
        return;
    }
    const offset = (bgr_page - 1) * BGR_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const isProgram = !!r.program_code;
        const code = isProgram ? r.program_code : r.project_code;
        const name = isProgram ? r.program_name : r.project_name;
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>${bgr_esc(r.fiscal_yr)}</td>
        <td>${isProgram ? '<span class="nha-bdg nha-bdg-blue">Program</span>' : '<span class="nha-bdg nha-bdg-teal">Project</span>'}</td>
        <td>
            <div style="font-weight:500;">${bgr_esc(code || '—')}</div>
            ${name ? `<div class="nha-td-sub" style="margin-top:2px;">${bgr_esc(name)}</div>` : ''}
        </td>
        <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(r.amount)}</div></td>
        <td>
            <button class="nha-btn-act edit" onclick="bgr_edit(${r.id})" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
            <button class="nha-btn-act danger" onclick="bgr_delete(${r.id})" title="Delete">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>
        </td>
    </tr>`;
    }).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function bgr_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = BGR_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $bgr('bgr-from').textContent  = from;
    $bgr('bgr-to').textContent    = to;
    $bgr('bgr-total').textContent = total;

    const container = $bgr('bgr-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="bgr_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="bgr_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="bgr_goTo(${current_page + 1})">›</button>`;
}

function bgr_goTo(page) { bgr_page = page; bgr_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function bgr_openModal(mode, row = null) {
    $bgr('bgr-mode').value = mode;
    $bgr('bgr-id').value = row?.id || '';
    $bgr('bgr-modal-title').textContent = mode === 'edit' ? 'Edit Budget Request' : 'Add Budget Request';
    $bgr('bgr-fiscal-yr').value = row?.fiscal_yr || '';
    $bgr('bgr-amount').value = row?.amount || '';

    const isProgram = row ? !!row.program_code : true;
    $bgr('bgr-type').value = isProgram ? 'PROGRAM' : 'PROJECT';
    $bgr('bgr-program-code').value = row?.program_code || '';
    $bgr('bgr-project-code').value = row?.project_code || '';
    bgr_toggleTypeFields();

    $bgr('bgr-overlay').classList.add('open');
}

function bgr_closeModal() {
    $bgr('bgr-overlay').classList.remove('open');
    $bgr('bgr-form').reset();
}

async function bgr_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/read/${id}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget request.');
        bgr_openModal('edit', json.data);
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function bgr_save(ev) {
    ev.preventDefault();
    const mode = $bgr('bgr-mode').value;
    const id   = $bgr('bgr-id').value;
    const isProgram = $bgr('bgr-type').value === 'PROGRAM';

    const payload = {
        fiscal_yr    : $bgr('bgr-fiscal-yr').value,
        amount       : $bgr('bgr-amount').value,
        program_code : isProgram ? $bgr('bgr-program-code').value : '',
        project_code : isProgram ? '' : $bgr('bgr-project-code').value,
    };

    if (isProgram && !payload.program_code) { bgr_toast('Please select a program.', 'error'); return; }
    if (!isProgram && !payload.project_code) { bgr_toast('Please select a project.', 'error'); return; }

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${BGR_API}/update/${id}` : `${BGR_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save budget request.');

        bgr_toast(`Budget request ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        bgr_closeModal();
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function bgr_delete(id) {
    if (!confirm(`Delete budget request #${id}? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/delete/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete budget request.');

        bgr_toast('Budget request deleted successfully.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function bgr_fmtMoney(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function bgr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function bgr_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}

function bgr_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    const icons  = {
        success: '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
        error:   '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
        warning: '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        info:    '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const color = colors[type] || colors.info;
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    t.innerHTML = `<span style="color:${color};flex-shrink:0;margin-top:1px">${icons[type] || icons.info}</span><div style="flex:1"><span>${msg}</span></div><button onclick="this.parentElement.remove()" style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
