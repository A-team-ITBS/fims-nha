<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_coa_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

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
            <div class="nha-filter-group">
                <label class="nha-filter-label">Account Type</label>
                <select id="coa-filter-type" class="nha-filter-select">
                    <option value="">All Types</option>
                    <option value="Asset">Asset</option>
                    <option value="Liability">Liability</option>
                    <option value="Equity">Equity</option>
                    <option value="Income">Income</option>
                    <option value="Expense">Expense</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="coa-filter-status" class="nha-filter-select">
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
                        <th>Account Code</th>
                        <th>Account Title</th>
                        <th>Account Type</th>
                        <th>Parent Account</th>
                        <th>Posting Allowed</th>
                        <th>Status</th>
                        <th>Source Document</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="coa-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="coa-from">0</span>–<span id="coa-to">0</span></strong> of <strong><span id="coa-total">0</span></strong></span>
            <div id="coa-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/chart-of-accounts-form.php'; ?>

</div><!-- /#coa-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   CHART OF ACCOUNTS LIST   prefix: coa_
═══════════════════════════════════════════════════════════════ */

const COA_API = '<?php echo $_coa_api; ?>' + '/chart-of-accounts';

let coa_page = 1;
const COA_PAGE_SIZE = 15;

const $coa = id => document.getElementById(id);

async function coa_bootstrapLoad() {
    await Promise.all([coa_load(), coa_loadParentOptions()]);
    coa_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', coa_bootstrapLoad);
} else {
    coa_bootstrapLoad();
}

let coa_allAccounts = [];
async function coa_loadParentOptions() {
    try {
        const res  = await fetch(`${COA_API}/all`, { credentials: 'same-origin' });
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

function coa_bindEvents() {
    $coa('coa-refresh')?.addEventListener('click', () => { coa_page = 1; coa_load(); });
    $coa('coa-add')?.addEventListener('click', () => coa_openModal('add'));
    $coa('coa-modal-close')?.addEventListener('click', coa_closeModal);
    $coa('coa-cancel')?.addEventListener('click', coa_closeModal);
    $coa('coa-form')?.addEventListener('submit', coa_save);

    $coa('coa-filter-type')?.addEventListener('change', () => { coa_page = 1; coa_load(); });
    $coa('coa-filter-status')?.addEventListener('change', () => { coa_page = 1; coa_load(); });

    let searchTimer;
    const searchEl = $coa('coa-filter-search');
    const clearBtn = $coa('coa-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { coa_page = 1; coa_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        coa_page = 1; coa_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function coa_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search       : $coa('coa-filter-search')?.value   || '',
            account_type : $coa('coa-filter-type')?.value     || '',
            status       : $coa('coa-filter-status')?.value   || '',
            page         : coa_page,
            limit        : COA_PAGE_SIZE,
        });
        const res  = await fetch(`${COA_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        const rows = json.data ?? [];
        coa_renderTable(rows);
        coa_renderPagination(json.pagination ?? {});
        $coa('coa-kpi-total').textContent    = json.pagination?.total ?? rows.length;
        $coa('coa-kpi-active').textContent   = rows.filter(r => r.status === 'Active').length;
        $coa('coa-kpi-posting').textContent  = rows.filter(r => r.posting_allowed === true || r.posting_allowed === 't' || r.posting_allowed === true).length;
    } catch (e) {
        coa_renderTable([]);
        coa_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function coa_renderTable(rows) {
    const tbody = $coa('coa-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No accounts found.</span></div></td></tr>';
        return;
    }
    const offset = (coa_page - 1) * COA_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><code class="nha-code">${coa_esc(r.account_id)}</code></td>
        <td><code class="nha-code">${coa_esc(r.account_code)}</code></td>
        <td><div style="font-weight:600;">${coa_esc(r.account_title)}</div></td>
        <td>${r.account_type ? coa_esc(r.account_type) : '<span class="nha-td-sub">—</span>'}</td>
        <td>${r.parent_account_code ? `<code class="nha-code">${coa_esc(r.parent_account_code)}</code>` : '<span class="nha-td-sub">—</span>'}</td>
        <td>${coa_boolBadge(r.posting_allowed)}</td>
        <td>${coa_statusBadge(r.status)}</td>
        <td>${r.source_document ? coa_esc(r.source_document) : '<span class="nha-td-sub">—</span>'}</td>
        <td>
            <button class="nha-btn-act edit" onclick="coa_edit('${coa_escAttr(r.account_id)}')" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
            <button class="nha-btn-act" onclick="coa_toggleStatus('${coa_escAttr(r.account_id)}')" title="Toggle status">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                ${r.status === 'Active' ? 'Deactivate' : 'Activate'}
            </button>
            <button class="nha-btn-act danger" onclick="coa_delete('${coa_escAttr(r.account_id)}')" title="Delete">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>
        </td>
    </tr>`).join('');
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
    return coa_pill(status === 'Active' ? 'Active' : 'Inactive', status === 'Active');
}

/* ── Pagination ────────────────────────────────────────────── */
function coa_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = COA_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $coa('coa-from').textContent  = from;
    $coa('coa-to').textContent    = to;
    $coa('coa-total').textContent = total;

    const container = $coa('coa-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="coa_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="coa_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="coa_goTo(${current_page + 1})">›</button>`;
}

function coa_goTo(page) { coa_page = page; coa_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function coa_openModal(mode, row = null) {
    coa_hideErr();
    $coa('coa-mode').value = mode;
    $coa('coa-modal-title').textContent = mode === 'edit' ? 'Edit Account' : 'Add Account';
    $coa('coa-account-id').value = row?.account_id || '';
    $coa('coa-account-code').value = row?.account_code || '';
    $coa('coa-account-code').disabled = mode === 'edit';
    $coa('coa-account-title').value = row?.account_title || '';
    $coa('coa-account-type').value = row?.account_type || '';
    coa_renderParentOptions(row?.account_id || null);
    $coa('coa-parent-account-id').value = row?.parent_account_id || '';
    $coa('coa-posting-allowed').checked = !!(row && (row.posting_allowed === true || row.posting_allowed === 't'));
    $coa('coa-source-document').value = row?.source_document || '';
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
    const payload = {
        account_code       : $coa('coa-account-code').value.trim(),
        account_title      : $coa('coa-account-title').value.trim(),
        account_type       : $coa('coa-account-type').value,
        parent_account_id  : $coa('coa-parent-account-id').value,
        posting_allowed    : $coa('coa-posting-allowed').checked,
        source_document    : $coa('coa-source-document').value.trim(),
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
    if (!confirm(`Delete account '${id}'? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${COA_API}/delete/${encodeURIComponent(id)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete account.');

        coa_toast('Account deleted successfully.', 'success');
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
