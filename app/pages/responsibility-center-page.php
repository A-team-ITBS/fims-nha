<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_rc_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<style>
/* ── Actions dropdown — same menu as the Memo Requests / Budget Proposal lists ── */
#rc-root .nha-tools-menu { position:relative;display:inline-block; }
#rc-root .nha-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
    border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;
    font-family:'IBM Plex Sans',sans-serif;transition:all .15s; }
#rc-root .nha-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA);border-color:var(--afims-text-disabled, #A9AAB8); }
#rc-root .nha-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
#rc-root .nha-tools-dropdown { display:none;position:fixed;min-width:170px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);
    border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
#rc-root .nha-tools-dropdown.open { display:block; }
#rc-root .nha-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);
    white-space:nowrap;cursor:pointer;border:none;background:none;width:100%;text-align:left;transition:background .1s;font-family:'IBM Plex Sans',sans-serif; }
#rc-root .nha-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
#rc-root .nha-menu-item.danger { color:var(--afims-danger, #DC3545); }
#rc-root .nha-menu-item.danger:hover { background:#fdecec;color:#c0392b; }
#rc-root .nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
#rc-root .nha-menu-divider { border-top:1px solid var(--afims-border, #E4ECF2);margin:3px 0; }
/* dark mode */
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-trigger { color:#FFFFFF;background:#3861BC;border-color:#3861BC; }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-trigger:hover { background:#2E59B8;border-color:#2E59B8; }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-tools-dropdown { background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);box-shadow:0 8px 24px rgba(0,0,0,.45); }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-item { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-item svg { opacity:.85; }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-item.danger { color:#F87171; }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-item.danger:hover { color:#FCA5A5;background:rgba(220,53,69,.18); }
html.afims-dark[data-bs-theme="dark"] #rc-root .nha-menu-divider { border-top-color:var(--afims-border-strong, #4A5270); }

/* ── Page header: copied from User Access (.app-page-header in app/fims-css/fims.css — that file is not loaded here,
      so the rules are repeated, scoped to this page). Stays navy in dark mode too, like User Access. ── */
#rc-root .app-page-header { position:relative;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;overflow:hidden;
    background:linear-gradient(135deg, #000E3E 0%, #001A5E 100%);border-radius:.75rem;padding:1.5rem 1.75rem;margin-bottom:1.5rem; }
#rc-root .app-page-header::before,
#rc-root .app-page-header::after { content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none; }
#rc-root .app-page-header::before { width:180px;height:180px;top:-90px;right:80px; }
#rc-root .app-page-header::after { width:120px;height:120px;bottom:-70px;right:-20px;background:rgba(255,255,255,.06); }
#rc-root .app-page-header-icon { position:relative;z-index:1;flex:0 0 auto;width:3rem;height:3rem;border-radius:.5rem;
    display:flex;align-items:center;justify-content:center;font-size:1.25rem;
    background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#FFFFFF; }
#rc-root .app-page-title { position:relative;z-index:1;font-size:1.6rem;font-weight:800;letter-spacing:-.01em;color:#FFFFFF;margin:0 0 .25rem; }
#rc-root .app-page-subtitle { position:relative;z-index:1;font-size:.9rem;color:rgba(255,255,255,.75);margin:0;max-width:640px; }
/* the Refresh / Add RC buttons stay on the right of the banner, above its decorative circles */
#rc-root .app-page-header-actions { position:relative;z-index:1;margin-left:auto;display:flex;flex-wrap:wrap;gap:8px; }
</style>

<div id="rc-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in app/fims-css/fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4m-4 6h.01M9 14h.01M9 17h.01"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Responsibility Center</h1>
            <p class="app-page-subtitle">The offices of the agency — groups, departments, regions and districts — and which of them hold a budget.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="rc-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="rc-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a responsibility center">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add RC
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total RCs</div>
                    <div class="nha-sc-val" id="rc-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
        <div class="nha-sc">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Active</div>
                    <div class="nha-sc-val" id="rc-kpi-active">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Status = Active</p>
        </div>
        <div class="nha-sc">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Budget-Holding</div>
                    <div class="nha-sc-val" id="rc-kpi-budget-holding">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Can hold a budget allocation directly</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Office Type</label>
                <select id="rc-filter-type" class="nha-filter-select">
                    <option value="">All Types</option>
                    <option value="Agency">Agency</option>
                    <option value="Group">Group</option>
                    <option value="Department">Department</option>
                    <option value="Regional">Regional</option>
                    <option value="District">District</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="rc-filter-status" class="nha-filter-select">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="rc-filter-search" class="nha-filter-input" placeholder="Search RC code, name…">
                    <button class="nha-filter-clear-btn" id="rc-search-clear" hidden type="button">×</button>
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
                        <th>#</th>
                        <th>RC ID</th>
                        <th>RC Code</th>
                        <th>RC Name</th>
                        <th>Parent RC</th>
                        <th>Office Type</th>
                        <th>Status</th>
                        <th>Source Document</th>
                        <th>Budget-Holding</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="rc-tbody">
                    <tr><td colspan="10"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="rc-from">0</span>–<span id="rc-to">0</span></strong> of <strong><span id="rc-total">0</span></strong></span>
            <div id="rc-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/responsibility-center-form.php'; ?>

</div><!-- /#rc-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   RESPONSIBILITY CENTER LIST   prefix: rc_
═══════════════════════════════════════════════════════════════ */

const RC_API = '<?php echo $_rc_api; ?>' + '/responsibility-center';

let rc_page = 1;
const RC_PAGE_SIZE = 15;

const $rc = id => document.getElementById(id);

async function rc_bootstrapLoad() {
    await Promise.all([rc_load(), rc_loadParentOptions()]);
    rc_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', rc_bootstrapLoad);
} else {
    rc_bootstrapLoad();
}

let rc_allCenters = [];
async function rc_loadParentOptions() {
    try {
        const res  = await fetch(`${RC_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load responsibility centers.');

        rc_allCenters = json.data ?? [];
        rc_renderParentOptions();
    } catch (e) {
        rc_toast(e.message, 'error');
    }
}

function rc_renderParentOptions(excludeId = null) {
    const sel = $rc('rc-parent-rc-id');
    const current = sel.value;
    const options = rc_allCenters
        .filter(a => a.rc_id !== excludeId)
        .map(a => `<option value="${rc_escAttr(a.rc_id)}">${rc_esc(a.rc_code)} — ${rc_esc(a.rc_name)}</option>`)
        .join('');
    sel.innerHTML = '<option value="">— No parent (top-level) —</option>' + options;
    sel.value = current;
}

function rc_bindEvents() {
    $rc('rc-refresh')?.addEventListener('click', () => { rc_page = 1; rc_load(); });
    $rc('rc-add')?.addEventListener('click', () => rc_openModal('add'));
    $rc('rc-modal-close')?.addEventListener('click', rc_closeModal);
    $rc('rc-cancel')?.addEventListener('click', rc_closeModal);
    $rc('rc-form')?.addEventListener('submit', rc_save);

    $rc('rc-filter-type')?.addEventListener('change', () => { rc_page = 1; rc_load(); });
    $rc('rc-filter-status')?.addEventListener('change', () => { rc_page = 1; rc_load(); });

    let searchTimer;
    const searchEl = $rc('rc-filter-search');
    const clearBtn = $rc('rc-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { rc_page = 1; rc_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        rc_page = 1; rc_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function rc_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search      : $rc('rc-filter-search')?.value  || '',
            office_type : $rc('rc-filter-type')?.value    || '',
            status      : $rc('rc-filter-status')?.value  || '',
            page        : rc_page,
            limit       : RC_PAGE_SIZE,
        });
        const res  = await fetch(`${RC_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        const rows = json.data ?? [];
        rc_renderTable(rows);
        rc_renderPagination(json.pagination ?? {});
        $rc('rc-kpi-total').textContent          = json.pagination?.total ?? rows.length;
        $rc('rc-kpi-active').textContent         = rows.filter(r => r.status === 'Active').length;
        $rc('rc-kpi-budget-holding').textContent = rows.filter(r => r.is_budget_holding_rc === true || r.is_budget_holding_rc === 't').length;
    } catch (e) {
        rc_renderTable([]);
        rc_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function rc_renderTable(rows) {
    const tbody = $rc('rc-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No responsibility centers found.</span></div></td></tr>';
        return;
    }
    const offset = (rc_page - 1) * RC_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><code class="nha-code">${rc_esc(r.rc_id)}</code></td>
        <td><code class="nha-code">${rc_esc(r.rc_code)}</code></td>
        <td><div style="font-weight:600;">${rc_esc(r.rc_name)}</div></td>
        <td>${r.parent_rc_code ? `<code class="nha-code">${rc_esc(r.parent_rc_code)}</code>` : '<span class="nha-td-sub">—</span>'}</td>
        <td>${r.office_type ? rc_esc(r.office_type) : '<span class="nha-td-sub">—</span>'}</td>
        <td>${rc_statusBadge(r.status)}</td>
        <td>${r.source_document ? rc_esc(r.source_document) : '<span class="nha-td-sub">—</span>'}</td>
        <td>${rc_boolBadge(r.is_budget_holding_rc)}</td>
        <td>
            <div class="nha-tools-menu">
                <button type="button" class="nha-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="rc_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="nha-tools-dropdown">
                    <button type="button" class="nha-menu-item" onclick="rc_closeMenus();rc_edit('${rc_escAttr(r.rc_id)}')" title="Edit">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </button>
                    <button type="button" class="nha-menu-item" onclick="rc_closeMenus();rc_toggleStatus('${rc_escAttr(r.rc_id)}')" title="Toggle status">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        ${r.status === 'Active' ? 'Deactivate' : 'Activate'}
                    </button>
                    <div class="nha-menu-divider"></div>
                    <button type="button" class="nha-menu-item danger" onclick="rc_closeMenus();rc_delete('${rc_escAttr(r.rc_id)}')" title="Delete">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Delete
                    </button>
                </div>
            </div>
        </td>
    </tr>`).join('');
}

/* ── Actions dropdown — opens below its button with fixed coords, so the table's own overflow never clips it ── */
function rc_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    rc_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function rc_closeMenus() {
    document.querySelectorAll('#rc-root .nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('#rc-root .nha-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('#rc-root .nha-tools-menu')) rc_closeMenus(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') rc_closeMenus(); });
document.addEventListener('scroll', rc_closeMenus, true);

function rc_pill(text, on) {
    const bg = on ? 'rgba(17,167,82,.12)' : 'rgba(169,170,184,.18)';
    const fg = on ? 'var(--afims-success, #11A752)' : 'var(--afims-text-disabled, #6B6D7A)';
    return `<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:${bg};color:${fg};">${text}</span>`;
}
function rc_boolBadge(val) {
    const on = val === true || val === 't' || val === 'true' || val === 1;
    return rc_pill(on ? 'Yes' : 'No', on);
}
function rc_statusBadge(status) {
    return rc_pill(status === 'Active' ? 'Active' : 'Inactive', status === 'Active');
}

/* ── Pagination ────────────────────────────────────────────── */
function rc_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = RC_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $rc('rc-from').textContent  = from;
    $rc('rc-to').textContent    = to;
    $rc('rc-total').textContent = total;

    const container = $rc('rc-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="rc_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="rc_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="rc_goTo(${current_page + 1})">›</button>`;
}

function rc_goTo(page) { rc_page = page; rc_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function rc_openModal(mode, row = null) {
    rc_hideErr();
    $rc('rc-mode').value = mode;
    $rc('rc-modal-title').textContent = mode === 'edit' ? 'Edit Responsibility Center' : 'Add Responsibility Center';
    $rc('rc-rc-id').value = row?.rc_id || '';
    $rc('rc-rc-code').value = row?.rc_code || '';
    $rc('rc-rc-code').disabled = mode === 'edit';
    $rc('rc-rc-name').value = row?.rc_name || '';
    $rc('rc-office-type').value = row?.office_type || '';
    rc_renderParentOptions(row?.rc_id || null);
    $rc('rc-parent-rc-id').value = row?.parent_rc_id || '';
    $rc('rc-is-budget-holding').checked = !!(row && (row.is_budget_holding_rc === true || row.is_budget_holding_rc === 't'));
    $rc('rc-source-document').value = row?.source_document || '';
    $rc('rc-overlay').classList.add('open');
}

function rc_closeModal() {
    $rc('rc-overlay').classList.remove('open');
    $rc('rc-form').reset();
    rc_hideErr();
}

function rc_showErr(msg) {
    const el = $rc('rc-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function rc_hideErr() {
    $rc('rc-err').style.display = 'none';
}

async function rc_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${RC_API}/read/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load responsibility center.');
        rc_openModal('edit', json.data);
    } catch (e) {
        rc_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function rc_save(ev) {
    ev.preventDefault();
    rc_hideErr();
    const mode = $rc('rc-mode').value;
    const id   = $rc('rc-rc-id').value;
    const payload = {
        rc_code              : $rc('rc-rc-code').value.trim(),
        rc_name              : $rc('rc-rc-name').value.trim(),
        office_type          : $rc('rc-office-type').value,
        parent_rc_id         : $rc('rc-parent-rc-id').value,
        is_budget_holding_rc : $rc('rc-is-budget-holding').checked,
        source_document      : $rc('rc-source-document').value.trim(),
    };

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${RC_API}/update/${encodeURIComponent(id)}` : `${RC_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save responsibility center.');

        rc_toast(`Responsibility center ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        rc_closeModal();
        rc_load();
        rc_loadParentOptions();
    } catch (e) {
        rc_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function rc_toggleStatus(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${RC_API}/${encodeURIComponent(id)}/toggle-status`, {
            method: 'PATCH',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to update status.');

        rc_toast(json.message, 'success');
        rc_load();
    } catch (e) {
        rc_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function rc_delete(id) {
    if (!confirm(`Delete responsibility center '${id}'? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${RC_API}/delete/${encodeURIComponent(id)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete responsibility center.');

        rc_toast('Responsibility center deleted successfully.', 'success');
        rc_load();
        rc_loadParentOptions();
    } catch (e) {
        rc_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function rc_esc(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function rc_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}

function rc_toast(msg, type = 'info') {
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
