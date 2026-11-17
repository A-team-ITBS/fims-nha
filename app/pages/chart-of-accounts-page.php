<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_coa_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<style>
    .coa-action-dropdown {
        position: relative;
        display: inline-block;
    }
    .coa-action-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        background: var(--afims-info, #2563EB);
        color: white;
        border: none;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s;
    }
    .coa-action-btn:hover {
        background: var(--afims-info-dark, #1D4ED8);
    }
    .coa-action-menu {
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 4px;
        background: white;
        border: 1px solid var(--afims-border, #E4ECF2);
        border-radius: 8px;
        min-width: 180px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
        z-index: 1000;
        display: none;
    }
    .coa-action-menu.open {
        display: flex;
        flex-direction: column;
    }
    .coa-menu-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 10px 14px;
        background: none;
        border: none;
        color: var(--afims-text, #1A1B21);
        font-size: 12px;
        text-align: left;
        cursor: pointer;
        transition: background 0.15s;
    }
    .coa-menu-item:hover {
        background: var(--afims-surface-subtle, #F4F7FA);
    }
    .coa-menu-item.danger {
        color: var(--afims-danger, #DC3545);
    }
    .coa-menu-item.danger:hover {
        background: rgba(220, 53, 69, 0.08);
    }
    .coa-menu-item svg {
        flex-shrink: 0;
    }

    /* ── Account column: expand toggle, segmented account-code chip, account title ── */
    #coa-root .coa-toggle { width:22px;height:22px;flex-shrink:0;padding:0;font-size:12px;cursor:pointer;border-radius:6px;
        display:inline-flex;align-items:center;justify-content:center;
        color:var(--afims-heading-fill, #000E3E);background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2); }
    #coa-root .coa-chip { display:inline-flex;flex-shrink:0;overflow:hidden;border-radius:6px;border:1px solid var(--afims-border, #E4ECF2); }
    #coa-root .coa-chip-seg { padding:3px 8px;font:700 12px 'IBM Plex Mono',monospace;color:#2F5FD6;background:#EAF1FE;
        border-left:1px solid rgba(15,32,69,.08); }
    #coa-root .coa-chip-seg.is-root { color:#1A1B21;background:#EEF1F7;border-left:0; }
    #coa-root .coa-title { font-weight:700;color:var(--afims-heading-fill, #000E3E); }

    /* ── Dark mode ──
       Account column: --afims-heading-fill stays navy (a fill colour), so the title and toggle were navy on navy.
       Actions menu: its background was a hard-coded white while the items take the (light, in dark mode) theme text colour,
       so Edit / Deactivate were white on white. */
    html.afims-dark[data-bs-theme="dark"] #coa-root .coa-title { color:#FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #coa-root .coa-toggle { color:#FFFFFF;background:#3861BC;border-color:#5B7FCF; }
    html.afims-dark[data-bs-theme="dark"] #coa-root .coa-toggle:hover { background:#2E59B8; }
    html.afims-dark[data-bs-theme="dark"] #coa-root .coa-chip { border-color:#5B7FCF; }
    html.afims-dark[data-bs-theme="dark"] #coa-root .coa-chip-seg { color:#FFFFFF;background:#3861BC;border-left-color:rgba(255,255,255,.25); }
    html.afims-dark[data-bs-theme="dark"] #coa-root .coa-chip-seg.is-root { color:#FFFFFF;background:#22305E; }

    html.afims-dark[data-bs-theme="dark"] .coa-action-menu {
        background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);box-shadow:0 8px 24px rgba(0,0,0,.45);
    }
    html.afims-dark[data-bs-theme="dark"] .coa-menu-item { color:var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] .coa-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
    html.afims-dark[data-bs-theme="dark"] .coa-menu-item.danger { color:#F87171; }
    html.afims-dark[data-bs-theme="dark"] .coa-menu-item.danger:hover { background:rgba(220,53,69,.18); }
    html.afims-dark[data-bs-theme="dark"] .coa-action-btn { color:#FFFFFF;background:#3861BC; }
    html.afims-dark[data-bs-theme="dark"] .coa-action-btn:hover { background:#2E59B8; }
</style>

<div id="coa-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Chart of Accounts</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="coa-expand-all" type="button" class="nha-btn-hdr" title="Expand all accounts">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4M20 16v4h-4"/></svg>
                Expand All
            </button>
            <button id="coa-collapse-all" type="button" class="nha-btn-hdr" title="Collapse all accounts">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4v4H5M15 4v4h4M9 20v-4H5M15 20v-4h4"/></svg>
                Collapse All
            </button>
            <button id="coa-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="coa-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add an account">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Account
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Accounts</div>
                    <div class="nha-sc-val" id="coa-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
        <div class="nha-sc">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Active</div>
                    <div class="nha-sc-val" id="coa-kpi-active">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Status = Active</p>
        </div>
        <div class="nha-sc">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Posting Allowed</div>
                    <div class="nha-sc-val" id="coa-kpi-posting">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Transactions can post directly</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="coa-filter-search" class="nha-filter-input" placeholder="Search account code, title…">
                    <button class="nha-filter-clear-btn" id="coa-search-clear" hidden type="button">×</button>
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
                        <th>Account ID</th>
                        <th>Account</th>
                        <th>Books</th>
                        <th>Account Type</th>
                        <th>Parent Account</th>
                        <th>Posting Allowed</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="coa-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="coa-page-from">0</span>–<span id="coa-page-to">0</span></strong> of <strong><span id="coa-total">0</span></strong> account(s)</span>
            <div id="coa-pagination-controls" style="display:flex;align-items:center;gap:8px;margin-left:auto;">
                <button id="coa-page-prev" type="button" onclick="coa_prevPage()" style="padding:6px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;background:var(--afims-surface, #FFFFFF);cursor:pointer;font-size:12px;font-weight:600;">← Previous</button>
                <span id="coa-page-info" style="font-size:12px;">Page <span id="coa-page-num">1</span></span>
                <button id="coa-page-next" type="button" onclick="coa_nextPage()" style="padding:6px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;background:var(--afims-surface, #FFFFFF);cursor:pointer;font-size:12px;font-weight:600;">Next →</button>
            </div>
        </div>
    </div>

<?php include __DIR__ . '/form/chart-of-accounts-form.php'; ?>

</div><!-- /#coa-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   CHART OF ACCOUNTS LIST   prefix: coa_
═══════════════════════════════════════════════════════════════ */

const COA_API = '<?php echo $_coa_api; ?>' + '/chart-of-accounts';

let coa_rows = [];
let coa_collapsed = new Set();
let coa_scope = { is_admin: true, region_code: null, region_name: null };
let coa_currentPage = 1;
const coa_pageSize = 20;

const $coa = id => document.getElementById(id);

function coa_toggleDropdown(event, menuId) {
    event.stopPropagation();
    const menu = document.getElementById(menuId);
    const isOpen = menu.classList.contains('open');

    document.querySelectorAll('.coa-action-menu.open').forEach(m => {
        if (m.id !== menuId) m.classList.remove('open');
    });

    if (isOpen) {
        menu.classList.remove('open');
    } else {
        menu.classList.add('open');
    }
}

function coa_closeDropdown(menuId) {
    const menu = document.getElementById(menuId);
    if (menu) menu.classList.remove('open');
}

document.addEventListener('click', () => {
    document.querySelectorAll('.coa-action-menu.open').forEach(m => {
        m.classList.remove('open');
    });
});

async function coa_bootstrapLoad() {
    await coa_loadParentOptions();
    await coa_loadRegions();
    await coa_loadProjects();
    coa_bindEvents();
    await coa_load();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', coa_bootstrapLoad);
} else {
    coa_bootstrapLoad();
}


function coa_bindEvents() {
    $coa('coa-refresh')?.addEventListener('click', () => coa_load());
    $coa('coa-expand-all')?.addEventListener('click', coa_expandAll);
    $coa('coa-collapse-all')?.addEventListener('click', coa_collapseAll);
    $coa('coa-add')?.addEventListener('click', () => coa_openModal('add'));
    $coa('coa-modal-close')?.addEventListener('click', coa_closeModal);
    $coa('coa-cancel')?.addEventListener('click', coa_closeModal);
    $coa('coa-form')?.addEventListener('submit', coa_save);

    let searchTimer;
    const searchEl = $coa('coa-filter-search');
    const clearBtn = $coa('coa-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => coa_load(), 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        coa_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
let coa_allAccounts = [];
async function coa_loadParentOptions() {
    try {
        const res = await fetch(`${COA_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load accounts.');

        coa_allAccounts = json.data ?? [];
        coa_renderParentOptions();
    } catch (e) {
        coa_toast(e.message, 'error');
    }
}

function coa_renderParentOptions(excludeId = null) {
    const sel = $coa('coa-parent-account-id');
    const current = sel.value;
    const options = coa_allAccounts
        .filter(a => a.account_id !== excludeId)
        .map(a => `<option value="${coa_escAttr(a.account_id)}">${coa_esc(a.account_code)} — ${coa_esc(a.account_title)}</option>`)
        .join('');
    sel.innerHTML = '<option value="">— No parent (top-level) —</option>' + options;
    sel.value = current;
}

async function coa_loadRegions() {
    try {
        const res = await fetch(`${COA_API}/regions`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load regions.');

        const sel = $coa('coa-region-code');
        const current = sel.value;
        const options = (json.data ?? [])
            .map(r => `<option value="${coa_escAttr(r.region_code)}">${coa_esc(r.region_name)}</option>`)
            .join('');
        sel.innerHTML = '<option value="">— None (national/HO) —</option>' + options;
        sel.value = current;
    } catch (e) {
        coa_toast(e.message, 'error');
    }
}

async function coa_loadProjects() {
    try {
        const res = await fetch(`${COA_API}/projects`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load projects.');

        const sel = $coa('coa-project-code');
        const current = sel.value;
        const options = (json.data ?? [])
            .map(p => `<option value="${coa_escAttr(p.project_code)}">${coa_esc(p.project_code)} — ${coa_esc(p.project_name)}</option>`)
            .join('');
        sel.innerHTML = '<option value="">— None —</option>' + options;
        sel.value = current;
    } catch (e) {
        coa_toast(e.message, 'error');
    }
}

async function coa_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search: $coa('coa-filter-search')?.value || '',
        });
        const res = await fetch(`${COA_API}/all?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        coa_rows = json.data ?? [];
        coa_currentPage = 1;
        coa_renderTable(coa_rows);
        $coa('coa-kpi-total').textContent = coa_rows.length;
        $coa('coa-kpi-active').textContent = coa_rows.filter(r => r.status === 'Active').length;
        $coa('coa-kpi-posting').textContent = coa_rows.filter(r => r.posting_allowed === true || r.posting_allowed === 't').length;
    } catch (e) {
        coa_rows = [];
        coa_renderTable([]);
        coa_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Tree ──────────────────────────────────────────────────── */
function coa_buildTree(rows) {
    const byId = new Map(rows.map(r => [r.account_id, { ...r, children: [] }]));
    const roots = [];
    byId.forEach(node => {
        const parent = node.parent_account_id && byId.get(node.parent_account_id);
        if (parent) {
            parent.children.push(node);
        } else {
            roots.push(node);
        }
    });
    const cmp = (a, b) => a.account_code.localeCompare(b.account_code, undefined, { numeric: true, sensitivity: 'base' });
    const sortRec = list => { list.sort(cmp); list.forEach(n => sortRec(n.children)); };
    sortRec(roots);
    return roots;
}

function coa_flattenTree(nodes, depth, out) {
    nodes.forEach(node => {
        out.push({ ...node, _depth: depth, _hasChildren: node.children.length > 0 });
        if (node.children.length && !coa_collapsed.has(node.account_id)) {
            coa_flattenTree(node.children, depth + 1, out);
        }
    });
    return out;
}

function coa_toggleNode(id) {
    if (coa_collapsed.has(id)) {
        coa_collapsed.delete(id);
    } else {
        coa_collapsed.add(id);
    }
    coa_renderTable(coa_rows);
}

function coa_expandAll() {
    coa_collapsed.clear();
    coa_renderTable(coa_rows);
}

function coa_collapseAll() {
    coa_collapsed = new Set(coa_rows.map(r => r.parent_account_id).filter(Boolean));
    coa_renderTable(coa_rows);
}

/* ── Table ─────────────────────────────────────────────────── */
function coa_renderTable(rows) {
    const tbody = $coa('coa-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No accounts found.</span></div></td></tr>';
        coa_renderPagination(0, 0);
        return;
    }
    const tree = coa_buildTree(rows);
    const flat = coa_flattenTree(tree, 0, []);

    const totalPages = Math.ceil(flat.length / coa_pageSize);
    if (coa_currentPage > totalPages && totalPages > 0) {
        coa_currentPage = totalPages;
    }

    const startIdx = (coa_currentPage - 1) * coa_pageSize;
    const endIdx = startIdx + coa_pageSize;
    const pageData = flat.slice(startIdx, endIdx);

    tbody.innerHTML = pageData.map((r, i) => {
        const indent = r._depth * 26;
        const expanded = !coa_collapsed.has(r.account_id);
        const toggle = r._hasChildren
            ? `<button type="button" class="coa-toggle" onclick="coa_toggleNode('${coa_escAttr(r.account_id)}')" title="${expanded ? 'Collapse' : 'Expand'}">
                   <span style="display:inline-block;transform:rotate(${expanded ? 90 : 0}deg);transition:transform .15s;">›</span>
               </button>`
            : `<span style="width:22px;height:22px;flex-shrink:0;display:inline-block;"></span>`;
        const rowBg = r._depth === 0 ? 'background:rgba(15,32,69,.035);' : '';

        return `<tr style="${rowBg}">
        <td class="nha-td-seq">${i + 1}</td>
        <td><code class="nha-code">${coa_esc(r.account_id)}</code></td>
        <td>
            <div style="display:flex;align-items:center;gap:8px;padding-left:${indent}px;">
                ${toggle}
                ${coa_codeChip(r.account_code)}
                <span class="coa-title">${coa_esc(r.account_title)}</span>
            </div>
        </td>
        <td>${r.books ? coa_esc(r.books) : '<span class="nha-td-sub">—</span>'}</td>
        <td>${r.account_type ? coa_esc(r.account_type) : '<span class="nha-td-sub">—</span>'}</td>
        <td>${r.parent_account_id ? coa_esc(r.parent_account_id) : '<span class="nha-td-sub">—</span>'}</td>
        <td>${coa_boolBadge(r.posting_allowed)}</td>
        <td>${coa_statusBadge(r.status)}</td>
        <td>
            <div class="coa-action-dropdown">
                <button class="coa-action-btn" onclick="coa_toggleDropdown(event, 'coa-menu-${coa_escAttr(r.account_id)}')">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                    Actions
                </button>
                <div class="coa-action-menu" id="coa-menu-${coa_escAttr(r.account_id)}">
                    ${(r.posting_allowed === true || r.posting_allowed === 't') ? `<button type="button" class="coa-menu-item" onclick="coa_addSL('${coa_escAttr(r.account_id)}', '${coa_escAttr(r.account_code)}'); coa_closeDropdown('coa-menu-${coa_escAttr(r.account_id)}')">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add Sub-Ledger
                    </button>` : ''}
                    <button type="button" class="coa-menu-item" onclick="coa_edit('${coa_escAttr(r.account_id)}'); coa_closeDropdown('coa-menu-${coa_escAttr(r.account_id)}')">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </button>
                    <button type="button" class="coa-menu-item" onclick="coa_toggleStatus('${coa_escAttr(r.account_id)}'); coa_closeDropdown('coa-menu-${coa_escAttr(r.account_id)}')">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        ${r.status === 'Active' ? 'Deactivate' : 'Activate'}
                    </button>
                </div>
            </div>
        </td>
    </tr>`;
    }).join('');

    coa_renderPagination(flat.length, totalPages);
}

function coa_renderPagination(totalRows, totalPages) {
    const startIdx = (coa_currentPage - 1) * coa_pageSize + 1;
    const endIdx = Math.min(coa_currentPage * coa_pageSize, totalRows);

    $coa('coa-total').textContent = totalRows;
    $coa('coa-page-from').textContent = totalRows > 0 ? startIdx : 0;
    $coa('coa-page-to').textContent = totalRows > 0 ? endIdx : 0;
    $coa('coa-page-num').textContent = totalPages > 0 ? coa_currentPage : 1;

    const prevBtn = $coa('coa-page-prev');
    const nextBtn = $coa('coa-page-next');
    const pageInfo = $coa('coa-page-info');

    if (totalPages <= 1) {
        $coa('coa-pagination-controls').style.display = 'none';
    } else {
        $coa('coa-pagination-controls').style.display = 'flex';
        prevBtn.disabled = coa_currentPage === 1;
        nextBtn.disabled = coa_currentPage === totalPages;
        pageInfo.innerHTML = `Page <span id="coa-page-num">${coa_currentPage}</span> of ${totalPages}`;
    }
}

function coa_nextPage() {
    const tree = coa_buildTree(coa_rows);
    const flat = coa_flattenTree(tree, 0, []);
    const totalPages = Math.ceil(flat.length / coa_pageSize);
    if (coa_currentPage < totalPages) {
        coa_currentPage++;
        coa_renderTable(coa_rows);
    }
}

function coa_prevPage() {
    if (coa_currentPage > 1) {
        coa_currentPage--;
        coa_renderTable(coa_rows);
    }
}

function coa_codeChip(code) {
    const parts = String(code ?? '').split('-').filter(p => p !== '');
    if (!parts.length) return '';
    // first segment = the account class (neutral), the rest = sub-codes (blue); colours in the <style> block above (light + dark)
    const segs = parts.map((p, i) => `<span class="coa-chip-seg${i === 0 ? ' is-root' : ''}">${coa_esc(p)}</span>`).join('');
    return `<span class="coa-chip">${segs}</span>`;
}

function coa_pill(text, on) {
    const bg = on ? 'rgba(17,167,82,.12)' : 'rgba(169,170,184,.18)';
    const fg = on ? 'var(--afims-success, #11A752)' : 'var(--afims-text-disabled, #6B6D7A)';
    return `<span style="display:inline-block;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:600;background:${bg};color:${fg};">${text}</span>`;
}
function coa_boolBadge(val) {
    const on = val === true || val === 't' || val === 'true' || val === 1;
    return coa_pill(on ? 'Yes' : 'No', on);
}
function coa_statusBadge(status) {
    const active = status === 'Active' || status === 'true';
    return coa_pill(active ? 'Active' : 'Inactive', active);
}

/* ── Modal ─────────────────────────────────────────────────── */
async function coa_openModal(mode, row = null, parentId = null) {
    coa_hideErr();
    await coa_loadRegions();
    await coa_loadProjects();

    $coa('coa-mode').value = mode;
    $coa('coa-modal-title').textContent = mode === 'edit' ? 'Edit Account' : 'Add Account';
    $coa('coa-account-id').value = row?.account_id || '';
    $coa('coa-account-code').value = row?.account_code || '';
    $coa('coa-account-code').disabled = mode === 'edit';
    $coa('coa-account-title').value = row?.account_title || '';
    $coa('coa-account-type').value = row?.account_type || '';
    coa_renderParentOptions(row?.account_id || null);
    $coa('coa-parent-account-id').value = parentId || row?.parent_account_id || '';
    $coa('coa-account-purpose').value = row?.account_purpose || '';
    $coa('coa-posting-allowed').checked = !!(row && (row.posting_allowed === true || row.posting_allowed === 't'));
    $coa('coa-source-document').value = row?.source_document || '';
    $coa('coa-region-code').value = row?.region_code || '';
    $coa('coa-project-code').value = row?.project_code || '';

    // Set checkboxes for books
    const booksArray = row?.books ? row.books.split(',').map(b => b.trim()).filter(b => b) : [];
    document.getElementById('coa-book-mo').checked = booksArray.includes('MO');
    document.getElementById('coa-book-cisfa').checked = booksArray.includes('CISFA');
    document.getElementById('coa-book-ngc').checked = booksArray.includes('NGC');
    document.getElementById('coa-book-mplc').checked = booksArray.includes('MPLC');

    $coa('coa-overlay').classList.add('open');
}

async function coa_addSL(parentId, parentCode) {
    const parent = coa_rows.find(r => r.account_id === parentId);
    if (!parent) return;

    // Find all children of this parent and generate next code
    const children = coa_rows.filter(r => r.parent_account_id === parentId);
    const childCodes = children.map(c => c.account_code).filter(Boolean);

    let nextCode = `${parentCode}-01`;
    if (childCodes.length > 0) {
        const lastSegments = childCodes.map(code => {
            const parts = code.split('-');
            return parseInt(parts[parts.length - 1], 10) || 0;
        });
        const maxSegment = Math.max(...lastSegments);
        const nextSegment = String(maxSegment + 1).padStart(2, '0');
        nextCode = `${parentCode}-${nextSegment}`;
    }

    coa_hideErr();
    await coa_loadRegions();
    await coa_loadProjects();

    $coa('coa-mode').value = 'add';
    $coa('coa-modal-title').textContent = `Add Sub-Ledger under ${parent.account_code}`;
    $coa('coa-account-id').value = '';
    $coa('coa-account-code').value = nextCode;
    $coa('coa-account-code').disabled = false;
    $coa('coa-account-code').placeholder = `e.g. ${nextCode} (auto-increment)`;
    $coa('coa-account-title').value = '';
    $coa('coa-account-type').value = parent.account_type || '';
    $coa('coa-region-code').value = parent.region_code || '';
    $coa('coa-project-code').value = parent.project_code || '';
    coa_renderParentOptions(null);
    $coa('coa-parent-account-id').value = parentId;
    $coa('coa-account-purpose').value = parent?.account_purpose || '';
    $coa('coa-posting-allowed').checked = false;
    $coa('coa-source-document').value = parent?.source_document || '';

    // Set checkboxes for books
    const parentBooksArray = parent?.books ? parent.books.split(',').map(b => b.trim()).filter(b => b) : [];
    document.getElementById('coa-book-mo').checked = parentBooksArray.includes('MO');
    document.getElementById('coa-book-cisfa').checked = parentBooksArray.includes('CISFA');
    document.getElementById('coa-book-ngc').checked = parentBooksArray.includes('NGC');
    document.getElementById('coa-book-mplc').checked = parentBooksArray.includes('MPLC');

    $coa('coa-overlay').classList.add('open');
}


function coa_closeModal() {
    $coa('coa-overlay').classList.remove('open');
    $coa('coa-form').reset();
    coa_hideErr();
}

function coa_showErr(msg) {
    const el = $coa('coa-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function coa_hideErr() {
    $coa('coa-err').style.display = 'none';
}

async function coa_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${COA_API}/read/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load account.');
        coa_openModal('edit', json.data);
    } catch (e) {
        coa_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function coa_save(ev) {
    ev.preventDefault();
    coa_hideErr();
    const mode = $coa('coa-mode').value;
    const id   = $coa('coa-account-id').value;
    // Get selected books as comma-separated string from checkboxes
    const selectedBooks = [];
    if (document.getElementById('coa-book-mo').checked) selectedBooks.push('MO');
    if (document.getElementById('coa-book-cisfa').checked) selectedBooks.push('CISFA');
    if (document.getElementById('coa-book-ngc').checked) selectedBooks.push('NGC');
    if (document.getElementById('coa-book-mplc').checked) selectedBooks.push('MPLC');

    const payload = {
        account_code       : $coa('coa-account-code').value.trim(),
        account_title      : $coa('coa-account-title').value.trim(),
        account_type       : $coa('coa-account-type').value,
        region_code        : $coa('coa-region-code').value,
        project_code       : $coa('coa-project-code').value,
        parent_account_id  : $coa('coa-parent-account-id').value,
        account_purpose    : $coa('coa-account-purpose').value,
        posting_allowed    : $coa('coa-posting-allowed').checked,
        source_document    : $coa('coa-source-document').value.trim(),
        books              : selectedBooks.join(', '),
    };

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${COA_API}/update/${encodeURIComponent(id)}` : `${COA_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save account.');

        coa_toast(`Account ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        coa_closeModal();
        coa_load();
        coa_loadParentOptions();
    } catch (e) {
        coa_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function coa_toggleStatus(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${COA_API}/${encodeURIComponent(id)}/toggle-status`, {
            method: 'PATCH',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to update status.');

        coa_toast(json.message, 'success');
        coa_load();
    } catch (e) {
        coa_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function coa_delete(id) {
    if (!confirm(`Deactivate account '${id}'?`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${COA_API}/delete/${encodeURIComponent(id)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to deactivate account.');

        coa_toast('Account deactivated successfully.', 'success');
        coa_load();
        coa_loadParentOptions();
    } catch (e) {
        coa_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function coa_esc(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function coa_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}

function coa_toast(msg, type = 'info') {
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
