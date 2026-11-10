<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_mrq_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<style>
/* ── Actions dropdown menu (same pattern as budget-req-page.php) ──────── */
.nha-tools-menu { position:relative;display:inline-block; }
.nha-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;
    font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--afims-border, #E4ECF2);
    background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;
    font-family:'IBM Plex Sans',sans-serif;transition:all .15s; }
.nha-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA);border-color:var(--afims-text-disabled, #A9AAB8); }
.nha-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.nha-tools-dropdown { display:none;position:fixed;min-width:170px;background:var(--afims-surface, #FFFFFF);
    border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);
    z-index:9999;padding:4px 0; }
.nha-tools-dropdown.open { display:block; }
.nha-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;
    color:var(--afims-text, #1A1B21);text-decoration:none;white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;transition:background .1s; }
.nha-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.nha-menu-item.danger:hover { background:#fdecec;color:#c0392b; }
.nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
.nha-menu-divider { border-top:1px solid var(--afims-border, #E4ECF2);margin:3px 0; }
</style>

<div id="mrq-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Memo Requests</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="mrq-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="mrq-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Create a memo request">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Memo Request
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover every row matching the filters, not just this page) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total Memo Requests</div>
                <div class="nha-sc-val" id="mrq-kpi-total">—</div>
            </div></div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total PR Amount</div>
                <div class="nha-sc-val" id="mrq-kpi-pr">—</div>
            </div></div>
            <p class="nha-kpi-sub">Purchase requests raised</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total PO Amount</div>
                <div class="nha-sc-val" id="mrq-kpi-po">—</div>
            </div></div>
            <p class="nha-kpi-sub">Purchase orders issued</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="mrq-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="mrq-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="mrq-filter-search" class="nha-filter-input" placeholder="Search memo ref, allocation no., PR / PO no., vendor…">
                    <button class="nha-filter-clear-btn" id="mrq-search-clear" hidden type="button">×</button>
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
                        <th>Memo Ref No</th>
                        <th>FY</th>
                        <th>Requesting Office</th>
                        <th>Program / Project</th>
                        <th>Vendor</th>
                        <th>PR</th>
                        <th>PO</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="mrq-tbody">
                    <tr><td colspan="10"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="mrq-from">0</span>–<span id="mrq-to">0</span></strong> of <strong><span id="mrq-total">0</span></strong></span>
            <div id="mrq-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php
// "New Memo Request" opens the CBA form as a modal (window.cbaModal.open()). $cbaAsModal tells the
// form to render inside an overlay modal instead of as a full page — the same file is still the
// standalone page behind the "Memo Request Form" menu item. In modal mode it exposes
// window.cbaModal and calls window.mrq_load() after a successful save, to refresh this list.
$cbaAsModal = true;
include __DIR__ . '/form/budget-allocation-form.php';
?>

</div><!-- /#mrq-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   MEMO REQUESTS LIST   prefix: mrq_
   Backed by BudgetAllocationService → fims.budget_allocation
   (routes in app/routes/budget_allocation.php, modeled on /crud)

   Create  → the CBA form modal (app/pages/form/budget-allocation-form.php, window.cbaModal)
   Edit    → the same form as a full page: BudgetAllocationForm?id=N
═══════════════════════════════════════════════════════════════ */

const MRQ_BASE = '<?php echo $_mrq_api; ?>';
const MRQ_API  = MRQ_BASE + '/budget_allocation';

let mrq_page = 1;
let mrq_rows = [];
const MRQ_PAGE_SIZE = 15;

// Loaded once at start-up; they fill the two filter dropdowns.
const MRQ_LOOKUPS = { fiscalYears: [], statuses: [] };
const MRQ_LOOKUP_SOURCES = {
    fiscalYears : `${MRQ_BASE}/fiscal_year/all`,
    statuses    : `${MRQ_API}/statuses`,
};

const $mrq = id => document.getElementById(id);

async function mrq_bootstrapLoad() {
    window.PagePreloader?.show();
    try {
        await mrq_loadLookups();
    } finally {
        window.PagePreloader?.hide();
    }
    mrq_bindEvents();
    await mrq_load();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mrq_bootstrapLoad);
} else {
    mrq_bootstrapLoad();
}

/* ── HTTP ──────────────────────────────────────────────────── */
// Resolves with the parsed body on { success: true }; otherwise throws the
// server's message (the API answers 400 / 404 with { success:false, message }).
async function mrq_request(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    let json = null;
    try { json = await res.json(); } catch (_) { /* non-JSON body */ }
    if (!res.ok || !json?.success) {
        throw new Error(json?.message || `Request failed (HTTP ${res.status}).`);
    }
    return json;
}

/* ── Lookups ───────────────────────────────────────────────── */
async function mrq_loadLookups() {
    const keys    = Object.keys(MRQ_LOOKUP_SOURCES);
    const results = await Promise.allSettled(keys.map(k => mrq_request(MRQ_LOOKUP_SOURCES[k])));
    results.forEach((r, i) => {
        if (r.status === 'fulfilled') {
            MRQ_LOOKUPS[keys[i]] = r.value.data ?? [];
        } else {
            mrq_toast(`Could not load ${keys[i]}: ${r.reason.message}`, 'error');
        }
    });
    mrq_fillFilters();
}

function mrq_optionsHtml(placeholder, rows, valueOf, labelOf) {
    return `<option value="">${mrq_esc(placeholder)}</option>` +
        rows.map(r => `<option value="${mrq_esc(valueOf(r))}">${mrq_esc(labelOf(r))}</option>`).join('');
}

function mrq_fillFilters() {
    $mrq('mrq-filter-fy').innerHTML     = mrq_optionsHtml('All Years',  MRQ_LOOKUPS.fiscalYears, f => f.fiscal_yr, f => f.fiscal_yr);
    $mrq('mrq-filter-status').innerHTML = mrq_optionsHtml('All Status', MRQ_LOOKUPS.statuses,    s => s.id,        s => s.name);
}

/* ── Events ────────────────────────────────────────────────── */
function mrq_bindEvents() {
    $mrq('mrq-refresh')?.addEventListener('click', () => { mrq_page = 1; mrq_load(); });
    $mrq('mrq-add')?.addEventListener('click', mrq_new);

    ['mrq-filter-fy', 'mrq-filter-status'].forEach(id => {
        $mrq(id)?.addEventListener('change', () => { mrq_page = 1; mrq_load(); });
    });

    let searchTimer;
    const searchEl = $mrq('mrq-filter-search');
    const clearBtn = $mrq('mrq-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { mrq_page = 1; mrq_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        mrq_page = 1; mrq_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function mrq_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search      : $mrq('mrq-filter-search')?.value || '',
            fiscal_year : $mrq('mrq-filter-fy')?.value     || '',
            status_id   : $mrq('mrq-filter-status')?.value || '',
            page        : mrq_page,
            limit       : MRQ_PAGE_SIZE,
        });
        const json = await mrq_request(`${MRQ_API}/list?${params}`);

        mrq_rows = json.data ?? [];
        mrq_renderTable(mrq_rows);
        mrq_renderPagination(json.pagination ?? {});
        $mrq('mrq-kpi-total').textContent = json.pagination?.total ?? mrq_rows.length;
        $mrq('mrq-kpi-pr').textContent    = mrq_money(json.summary?.total_pr_amount);
        $mrq('mrq-kpi-po').textContent    = mrq_money(json.summary?.total_po_amount);
    } catch (e) {
        mrq_rows = [];
        mrq_renderTable([]);
        mrq_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function mrq_money(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// status_color comes from settings_status — only a plain hex colour is allowed
// into the inline style.
function mrq_statusBadge(name, color) {
    if (!name) return '—';
    const safe = /^#[0-9a-fA-F]{3,8}$/.test(color || '') ? color : '#6B7280';
    return `<span class="nha-bdg" style="color:${safe};background:color-mix(in srgb, ${safe} 14%, #fff);">${mrq_esc(name)}</span>`;
}

function mrq_docCell(number, amount) {
    if (!number && (amount === null || amount === undefined || amount === '')) return '—';
    return `<div>${number ? `<code class="nha-code">${mrq_esc(number)}</code>` : '—'}</div>` +
           (amount !== null && amount !== undefined && amount !== ''
               ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_money(amount)}</div>` : '');
}

function mrq_renderTable(rows) {
    const tbody = $mrq('mrq-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No memo requests found.</span></div></td></tr>';
        return;
    }
    const offset = (mrq_page - 1) * MRQ_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const id = Number(r.allocation_id);
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>
            <code class="nha-code">${mrq_esc(r.memo_ref_no)}</code>
            ${r.allocation_number ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.allocation_number)}</div>` : ''}
            <div style="margin-top:3px;display:flex;gap:4px;flex-wrap:wrap;">
                ${r.allocation_type ? `<span class="nha-bdg nha-bdg-blue">${mrq_esc(r.allocation_type)}</span>` : ''}
                ${r.document_type   ? `<span class="nha-bdg nha-bdg-gray">${mrq_esc(r.document_type)}</span>` : ''}
                ${mrq_truthy(r.is_bidding) ? '<span class="nha-bdg nha-bdg-purple">Bidding</span>' : ''}
            </div>
            ${r.parent_memo_ref_no ? `<div class="nha-td-sub" style="margin-top:3px;">↳ ${mrq_esc(r.parent_memo_ref_no)}</div>` : ''}
        </td>
        <td>${mrq_esc(r.fiscal_year)}</td>
        <td>
            <div>${mrq_esc(r.requesting_rc_name || '—')}</div>
            ${r.requesting_district_name ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.requesting_district_name)}</div>` : ''}
        </td>
        <td>
            <div>${mrq_esc(r.program_name || '—')}</div>
            ${r.sub_program_name ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.sub_program_name)}</div>` : ''}
            ${r.project_name ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.project_name)}</div>` : ''}
        </td>
        <td>${mrq_esc(r.vendor_name || '—')}</td>
        <td>${mrq_docCell(r.pr_number, r.pr_amount)}</td>
        <td>${mrq_docCell(r.po_number, r.po_amount)}</td>
        <td>${mrq_statusBadge(r.status_name, r.status_color)}</td>
        <td>${mrq_actionButtons(id)}</td>
    </tr>`;
    }).join('');
}

// Actions dropdown — same kebab-menu pattern as budget-req-page.php's bgr_actionButtons().
function mrq_actionButtons(id) {
    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="mrq_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">
            <a class="nha-menu-item" href="javascript:void(0);" onclick="mrq_edit(${id});mrq_closeMenus();">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            <div class="nha-menu-divider"></div>
            <a class="nha-menu-item danger" href="javascript:void(0);" onclick="mrq_closeMenus();mrq_delete(${id});">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </a>
        </div>
    </div>`;
}

// Open/close — same positioning pattern as budget-req-page.php's bgr_toggleMenu/bgr_closeMenus
// (fixed coords so the table's own overflow never clips the dropdown).
function mrq_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    mrq_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.style.top = (rect.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
    drop.classList.add('open');
}

function mrq_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) mrq_closeMenus();
});
document.addEventListener('scroll', mrq_closeMenus, true);

function mrq_truthy(v) { return v === true || v === 1 || v === '1' || v === 't' || v === 'true'; }

/* ── Pagination ────────────────────────────────────────────── */
function mrq_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = MRQ_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $mrq('mrq-from').textContent  = from;
    $mrq('mrq-to').textContent    = to;
    $mrq('mrq-total').textContent = total;

    const container = $mrq('mrq-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="mrq_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="mrq_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="mrq_goTo(${current_page + 1})">›</button>`;
}

function mrq_goTo(page) { if (page >= 1) { mrq_page = page; mrq_load(); } }

/* ── Create / edit ─────────────────────────────────────────── */
// New: the CBA form pops up as a modal. window.cbaModal comes from the include above.
function mrq_new() {
    if (!window.cbaModal) {
        mrq_toast('The memo request form could not be loaded. Please refresh the page.', 'error');
        return;
    }
    window.cbaModal.open();
}

// Edit: the same form as a full page, which reads ?id= and loads that record.
function mrq_edit(id) {
    window.location.href = 'BudgetAllocationForm?id=' + encodeURIComponent(id);
}

/* ── Delete (soft) ─────────────────────────────────────────── */
async function mrq_delete(id) {
    const row = mrq_rows.find(r => Number(r.allocation_id) === Number(id));
    const ref = row?.memo_ref_no ? `"${row.memo_ref_no}"` : `#${id}`;
    if (!confirm(`Delete memo request ${ref}? It will no longer appear in the list.`)) return;

    window.PagePreloader?.show();
    try {
        await mrq_request(`${MRQ_API}/delete/${encodeURIComponent(id)}`, { method: 'DELETE' });
        mrq_toast('Memo request deleted successfully.', 'success');
        // deleting the last row of a page would leave an empty page behind
        if (mrq_rows.length === 1 && mrq_page > 1) mrq_page--;
        mrq_load();
    } catch (e) {
        mrq_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function mrq_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}

function mrq_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
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
    const span = document.createElement('span');
    span.style.flex = '1';
    span.textContent = msg;                 // textContent — messages may contain server text
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
