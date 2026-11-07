<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_bgr_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
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
                    <option value="ACCOUNT">Chart of Accounts</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead id="bgr-thead">
                    <tr>
                        <th>#</th>
                        <th>Fiscal Year</th>
                        <th>Allocated To</th>
                        <th>Allocation Target</th>
                        <th>Particulars</th>
                        <th>Expense Class</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bgr-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="bgr-from">0</span>–<span id="bgr-to">0</span></strong> of <strong><span id="bgr-total">0</span></strong></span>
            <div id="bgr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/budget-req-form.php'; ?>

</div><!-- /#bgr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REQUIREMENTS LIST   prefix: bgr_
═══════════════════════════════════════════════════════════════ */

const BGR_API                   = '<?php echo $_bgr_api; ?>' + '/budget_req';
const BGR_LINE_ITEM_API         = '<?php echo $_bgr_api; ?>' + '/budget_line_item';
const BGR_PROGRAM_API           = '<?php echo $_bgr_api; ?>' + '/program/all';
const BGR_PROJECT_API           = '<?php echo $_bgr_api; ?>' + '/project/all';
const BGR_FISCAL_YEAR_API       = '<?php echo $_bgr_api; ?>' + '/fiscal_year';
const BGR_EXPENSE_CLASS_API     = '<?php echo $_bgr_api; ?>' + '/expense_class/all';
const BGR_CHART_OF_ACCOUNTS_API = '<?php echo $_bgr_api; ?>' + '/chart_of_accounts/all';

// Expense classes allocated to a chart-of-accounts entry instead of a
// program/project, and the COA code prefix each one is scoped to.
// Must mirror BudgetReqService::$coaPrefixByExpenseClassCode on the backend.
const BGR_COA_PREFIX_BY_EXPENSE_CLASS_CODE = { PS: '5-01', MOOE: '5-02' };

let bgr_page = 1;
const BGR_PAGE_SIZE = 15;

// Cached option lists used to build each line-item row (fetched once, reused
// per row instead of a per-row network call).
let BGR_PROGRAMS = [];
let BGR_PROJECTS = [];
let BGR_COA_OPTIONS = [];
let bgr_isCoaMode = false; // true when the header's expense class resolves to a COA prefix (PS/MOOE)
let bgr_liSeq = 0;

const $bgr = id => document.getElementById(id);

async function bgr_bootstrapLoad() {
    await Promise.all([bgr_load(), bgr_loadPrograms(), bgr_loadProjects(), bgr_loadFiscalYears(), bgr_loadExpenseClasses()]);
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
        BGR_PROGRAMS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

async function bgr_loadProjects() {
    try {
        const res  = await fetch(BGR_PROJECT_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load projects.');
        BGR_PROJECTS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

async function bgr_loadFiscalYears() {
    try {
        const res  = await fetch(`${BGR_FISCAL_YEAR_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');

        $bgr('bgr-fiscal-yr').insertAdjacentHTML('beforeend', (json.data ?? []).map(fy =>
            `<option value="${bgr_escAttr(fy.fiscal_yr)}">${bgr_esc(fy.fiscal_yr)}</option>`
        ).join(''));
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

async function bgr_loadExpenseClasses() {
    try {
        const res  = await fetch(BGR_EXPENSE_CLASS_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load expense classes.');
        $bgr('bgr-expense-class-id').insertAdjacentHTML('beforeend', (json.data ?? []).map(ec =>
            `<option value="${bgr_escAttr(ec.expense_class_id)}" data-code="${bgr_escAttr(ec.expense_class_code)}">${bgr_esc(ec.expense_class_code)} — ${bgr_esc(ec.expense_class_name)}</option>`
        ).join(''));
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

/* ── Expense class drives whether each line item targets a program/project
   or a chart-of-accounts entry ──────────────────────────────────────── */
async function bgr_loadChartOfAccounts(prefix) {
    try {
        const res  = await fetch(`${BGR_CHART_OF_ACCOUNTS_API}?prefix=${encodeURIComponent(prefix)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load chart of accounts.');
        BGR_COA_OPTIONS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
        BGR_COA_OPTIONS = [];
    }
}

// Resolves bgr_isCoaMode from the header's selected expense class, loads the
// COA options for that prefix if needed, and toggles which section shows
// (#bgr-co-section vs #bgr-li-section). Does NOT touch existing field values —
// callers decide whether to reset them (a live expense-class change does;
// opening the modal does its own prefill afterward).
async function bgr_refreshLineItemColumns() {
    const sel    = $bgr('bgr-expense-class-id');
    const code   = sel.selectedOptions[0]?.dataset.code || '';
    const prefix = BGR_COA_PREFIX_BY_EXPENSE_CLASS_CODE[code] || null;
    bgr_isCoaMode = !!prefix;

    if (bgr_isCoaMode) await bgr_loadChartOfAccounts(prefix);

    $bgr('bgr-co-section').hidden = bgr_isCoaMode;
    $bgr('bgr-li-section').hidden = !bgr_isCoaMode;
    $bgr('bgr-amount').required = !bgr_isCoaMode;
    $bgr('bgr-mode-hint').textContent = bgr_isCoaMode
        ? `Allocates to one or more chart of accounts entries under ${prefix}.`
        : 'Allocates to a single program or project.';

    bgr_renderLiThead();
}

// User changed the expense class while the modal is open — the section (and,
// for line items, the column structure) may have flipped, so reset the
// line items table and the CO fields to a clean slate.
async function bgr_onExpenseClassChange() {
    await bgr_refreshLineItemColumns();
    $bgr('bgr-program-code').value = '';
    $bgr('bgr-project-code').value = '';
    $bgr('bgr-location').value = '';
    $bgr('bgr-amount').value = '';
    bgr_clearLineItems();
    bgr_addLineItemRow({}, $bgr('bgr-mode').value !== 'edit');
}

/* ── Line items — repeatable rows, columns depend on bgr_isCoaMode ───── */
// Clears both the table body and its paired remove-button column (keeping
// #bgr-li-remove-head), since a row's cells and its remove cell are two
// separate DOM trees kept in sync by matching dataset.seq.
function bgr_clearLineItems() {
    $bgr('bgr-li-tbody').innerHTML = '';
    $bgr('bgr-li-remove-col').querySelectorAll('.bgr-li-remove-cell').forEach(el => el.remove());
}

function bgr_renderLiThead() {
    // No "remove" column here — it lives in the separate #bgr-li-remove-col
    // sibling (see bgr_addLineItemRow), which stays outside the scroll area.
    $bgr('bgr-li-thead').innerHTML = bgr_isCoaMode
        ? '<tr><th style="width:150px;">Account Code</th><th style="width:300px;">Account Name</th><th>Particulars</th><th style="width:170px;">Requesting Amount</th></tr>'
        : '<tr><th style="width:220px;">Program</th><th style="width:220px;">Project</th><th>Location</th><th style="width:170px;">Requesting Amount</th></tr>';
}

function bgr_programOptionsHtml(selected) {
    const sel = selected != null ? String(selected) : '';
    const opts = ['<option value="">— None —</option>'];
    BGR_PROGRAMS.forEach(p => {
        opts.push(`<option value="${bgr_escHtmlAttr(p.program_code)}" ${String(p.program_code) === sel ? 'selected' : ''}>${bgr_esc(p.program_code)} — ${bgr_esc(p.program_name)}</option>`);
    });
    return opts.join('');
}
function bgr_projectOptionsHtml(selected) {
    const sel = selected != null ? String(selected) : '';
    const opts = ['<option value="">— None —</option>'];
    BGR_PROJECTS.forEach(p => {
        opts.push(`<option value="${bgr_escHtmlAttr(p.project_code)}" ${String(p.project_code) === sel ? 'selected' : ''}>${bgr_esc(p.project_code)} — ${bgr_esc(p.project_name)}</option>`);
    });
    return opts.join('');
}
function bgr_coaOptionsHtml(selected) {
    const sel = selected != null ? String(selected) : '';
    const opts = ['<option value="">— Select —</option>'];
    BGR_COA_OPTIONS.forEach(a => {
        opts.push(`<option value="${bgr_escHtmlAttr(a.account_id)}" ${String(a.account_id) === sel ? 'selected' : ''}>${bgr_esc(a.account_code)} — ${bgr_esc(a.account_title)}</option>`);
    });
    return opts.join('');
}

function bgr_addLineItemRow(prefill = {}, removable = true) {
    bgr_liSeq++;
    const seq = bgr_liSeq;
    const tr = document.createElement('tr');
    tr.dataset.seq = seq;

    if (bgr_isCoaMode) {
        tr.innerHTML = `
            <td><select class="bgr-li-select bgr-li-account" onchange="bgr_liAccountChange(this)">${bgr_coaOptionsHtml(prefill.account_id)}</select></td>
            <td><input type="text" class="bgr-li-input bgr-li-account-name" value="${bgr_escHtmlAttr(prefill.account_title)}" title="${bgr_escHtmlAttr(prefill.account_title)}" disabled></td>
            <td><input type="text" class="bgr-li-input bgr-li-particulars" value="${bgr_escHtmlAttr(prefill.particulars)}" placeholder="Particulars" maxlength="2000"></td>
            <td><input type="text" inputmode="decimal" class="bgr-li-input bgr-li-amt" value="${bgr_fmtAmountInitial(prefill.amount)}" placeholder="0.00" oninput="bgr_fmtAmount(this)"></td>
        `;
    } else {
        tr.innerHTML = `
            <td><select class="bgr-li-select bgr-li-program" onchange="bgr_liProgramChange(this)">${bgr_programOptionsHtml(prefill.program_code)}</select></td>
            <td><select class="bgr-li-select bgr-li-project" onchange="bgr_liProjectChange(this)">${bgr_projectOptionsHtml(prefill.project_code)}</select></td>
            <td><input type="text" class="bgr-li-input bgr-li-location" value="${bgr_escHtmlAttr(prefill.location)}" placeholder="Location" maxlength="255"></td>
            <td><input type="text" inputmode="decimal" class="bgr-li-input bgr-li-amt" value="${bgr_fmtAmountInitial(prefill.amount)}" placeholder="0.00" oninput="bgr_fmtAmount(this)"></td>
        `;
    }
    $bgr('bgr-li-tbody').appendChild(tr);

    // Remove button lives in the separate #bgr-li-remove-col sibling, kept in
    // row-sync with the table via matching dataset.seq and matching CSS heights.
    const removeCell = document.createElement('div');
    removeCell.className = 'bgr-li-remove-cell';
    removeCell.dataset.seq = seq;
    if (removable) {
        removeCell.innerHTML = '<button type="button" class="bgr-li-remove" onclick="bgr_removeLineItemRow(this)" title="Remove">&times;</button>';
    }
    $bgr('bgr-li-remove-col').appendChild(removeCell);

    bgr_syncRemoveColGap();
}

function bgr_removeLineItemRow(btn) {
    const tbody = $bgr('bgr-li-tbody');
    if (tbody.rows.length <= 1) { bgr_toast('At least one line item is required.', 'error'); return; }
    const removeCell = btn.closest('.bgr-li-remove-cell');
    const seq = removeCell?.dataset.seq;
    removeCell?.remove();
    if (seq) tbody.querySelector(`tr[data-seq="${seq}"]`)?.remove();
    bgr_syncRemoveColGap();
}

// #bgr-li-wrap's horizontal scrollbar (when the table needs it) reserves
// space at its own bottom that #bgr-li-remove-col — a plain non-scrolling
// sibling — doesn't lose. Without compensating for that, the two columns'
// rows drift out of alignment by exactly the scrollbar's height. Measuring
// it directly (rather than guessing a fixed px value) keeps this correct
// regardless of OS/browser scrollbar style.
function bgr_syncRemoveColGap() {
    const wrap = $bgr('bgr-li-wrap');
    const col  = $bgr('bgr-li-remove-col');
    if (!wrap || !col) return;
    const scrollbarHeight = wrap.offsetHeight - wrap.clientHeight;
    col.style.paddingBottom = scrollbarHeight > 0 ? scrollbarHeight + 'px' : '0';
}

function bgr_liProgramChange(sel) {
    if (sel.value) sel.closest('tr').querySelector('.bgr-li-project').value = '';
}
function bgr_liProjectChange(sel) {
    if (sel.value) sel.closest('tr').querySelector('.bgr-li-program').value = '';
}
function bgr_liAccountChange(sel) {
    const tr   = sel.closest('tr');
    const opt  = BGR_COA_OPTIONS.find(a => String(a.account_id) === sel.value);
    const name = opt ? opt.account_title : '';
    const nameInput = tr.querySelector('.bgr-li-account-name');
    nameInput.value = name;
    nameInput.title = name;
}

function bgr_bindEvents() {
    $bgr('bgr-refresh')?.addEventListener('click', () => { bgr_page = 1; bgr_load(); });
    $bgr('bgr-add')?.addEventListener('click', () => bgr_openModal('add'));
    $bgr('bgr-modal-close')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-cancel')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-form')?.addEventListener('submit', bgr_save);

    // Picking one clears the other — an amount belongs to a program OR a project, not both.
    $bgr('bgr-program-code')?.addEventListener('change', () => {
        if ($bgr('bgr-program-code').value) $bgr('bgr-project-code').value = '';
    });
    $bgr('bgr-project-code')?.addEventListener('change', () => {
        if ($bgr('bgr-project-code').value) $bgr('bgr-program-code').value = '';
    });

    $bgr('bgr-expense-class-id')?.addEventListener('change', bgr_onExpenseClassChange);
    $bgr('bgr-add-line-item')?.addEventListener('click', () => bgr_addLineItemRow({}, true));

    // A window resize can change whether #bgr-li-wrap needs a horizontal
    // scrollbar at all, which changes how much gap #bgr-li-remove-col needs.
    window.addEventListener('resize', () => {
        if ($bgr('bgr-overlay')?.classList.contains('open')) bgr_syncRemoveColGap();
    });

    ['bgr-filter-fiscal-yr', 'bgr-filter-type'].forEach(id => {
        $bgr(id)?.addEventListener('change', () => { bgr_page = 1; bgr_load(); });
    });
}

/* ── Load ──────────────────────────────────────────────────── */
// "Chart of Accounts" filter sources from fims.budget_line_items (one row per
// account allocation); every other filter sources from fims.budget_req headers.
async function bgr_load() {
    window.PagePreloader?.show();
    try {
        const type = $bgr('bgr-filter-type')?.value || '';
        const params = new URLSearchParams({
            fiscal_yr : $bgr('bgr-filter-fiscal-yr')?.value || '',
            page      : bgr_page,
            limit     : BGR_PAGE_SIZE,
        });

        let json;
        if (type === 'ACCOUNT') {
            const res = await fetch(`${BGR_LINE_ITEM_API}/list?${params}`, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load.');
            bgr_renderLineItemRows(json.data ?? []);
        } else {
            params.set('type', type);
            const res = await fetch(`${BGR_API}/list?${params}`, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('HTTP ' + res.status);
            json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load.');
            bgr_renderTable(json.data ?? []);
        }

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

function bgr_actionButtons(id, kind) {
    const editBtn = `<button class="nha-btn-act edit" onclick="bgr_edit(${id}, '${kind}')" title="Edit">
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
        Edit
    </button>`;
    const deleteBtn = `<button class="nha-btn-act danger" onclick="bgr_delete(${id}, '${kind}')" title="Delete">
        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
        Delete
    </button>`;
    return editBtn + deleteBtn;
}

/* ── Table — header rows (CO: one allocation; PS/MOOE: line-item summary) ── */
function bgr_renderTable(rows) {
    const tbody = $bgr('bgr-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget requests found.</span></div></td></tr>';
        return;
    }
    const offset = (bgr_page - 1) * BGR_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        let badge, code, name, detail, amount, actions;
        if (r.program_code || r.project_code) {
            const isProgram = !!r.program_code;
            badge  = isProgram ? '<span class="nha-bdg nha-bdg-blue">Program</span>' : '<span class="nha-bdg nha-bdg-teal">Project</span>';
            code   = isProgram ? r.program_code : r.project_code;
            name   = isProgram ? r.program_name : r.project_name;
            detail = r.location || '—';
            amount = r.amount;
            actions = bgr_actionButtons(r.budget_reference_number, 'header');
        } else {
            // PS/MOOE header — the per-account breakdown lives in fims.budget_line_items;
            // switch to the "Chart of Accounts" filter to edit/delete individual line items.
            badge  = '<span class="nha-bdg nha-bdg-amber">Chart of Accounts</span>';
            code   = `${r.li_count ?? 0} line item(s)`;
            name   = '';
            detail = 'See Chart of Accounts filter';
            amount = r.li_total;
            actions = `<button class="nha-btn-act danger" onclick="bgr_delete(${r.budget_reference_number}, 'header')" title="Delete">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>`;
        }
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>${bgr_esc(r.fiscal_yr)}</td>
        <td>${badge}</td>
        <td>
            <div style="font-weight:500;">${bgr_esc(code || '—')}</div>
            ${name ? `<div class="nha-td-sub" style="margin-top:2px;">${bgr_esc(name)}</div>` : ''}
        </td>
        <td>${bgr_esc(detail)}</td>
        <td>
            <div style="font-weight:500;">${bgr_esc(r.expense_class_code || '—')}</div>
            ${r.expense_class_name ? `<div class="nha-td-sub" style="margin-top:2px;">${bgr_esc(r.expense_class_name)}</div>` : ''}
        </td>
        <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(amount)}</div></td>
        <td>${bgr_statusBadge(r.status_name, r.status_color)}</td>
        <td>${actions}</td>
    </tr>`;
    }).join('');
}

/* ── Table — individual line items ("Chart of Accounts" filter) ─────── */
function bgr_renderLineItemRows(rows) {
    const tbody = $bgr('bgr-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No line items found.</span></div></td></tr>';
        return;
    }
    const offset = (bgr_page - 1) * BGR_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>${bgr_esc(r.fiscal_yr)}</td>
        <td><span class="nha-bdg nha-bdg-amber">Chart of Accounts</span></td>
        <td>
            <div style="font-weight:500;">${bgr_esc(r.account_code || '—')}</div>
            ${r.account_title ? `<div class="nha-td-sub" style="margin-top:2px;">${bgr_esc(r.account_title)}</div>` : ''}
        </td>
        <td style="max-width:220px;"><div title="${bgr_escHtmlAttr(r.particulars)}">${bgr_esc(bgr_truncate(r.particulars, 60))}</div></td>
        <td>
            <div style="font-weight:500;">${bgr_esc(r.expense_class_code || '—')}</div>
            ${r.expense_class_name ? `<div class="nha-td-sub" style="margin-top:2px;">${bgr_esc(r.expense_class_name)}</div>` : ''}
        </td>
        <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(r.requested_amount)}</div></td>
        <td>${bgr_statusBadge(r.status_name, r.status_color)}</td>
        <td>${bgr_actionButtons(r.budget_line_item_id, 'lineitem')}</td>
    </tr>`).join('');
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
// editTarget: 'header' (fims.budget_req, CO) or 'lineitem' (fims.budget_line_items, PS/MOOE)
async function bgr_openModal(mode, row = null, editTarget = 'header') {
    bgr_hideErr();
    const isLineItemEdit = mode === 'edit' && editTarget === 'lineitem';

    $bgr('bgr-mode').value = mode;
    $bgr('bgr-edit-target').value = editTarget;
    $bgr('bgr-id').value = row ? (isLineItemEdit ? row.budget_line_item_id : row.budget_reference_number) : '';
    $bgr('bgr-modal-title').textContent = mode === 'edit'
        ? (isLineItemEdit ? 'Edit Line Item' : 'Edit Budget Request')
        : 'Add Budget Request';

    if (row) {
        $bgr('bgr-fiscal-yr').value = row.fiscal_yr || '';
        $bgr('bgr-expense-class-id').value = row.expense_class_id || '';
    } else {
        // Default a new request to the current year and CO (program/project)
        // rather than empty placeholders — the most common case, and the user
        // can still change either before saving.
        const currentYearOption = $bgr('bgr-fiscal-yr').querySelector(`option[value="${new Date().getFullYear()}"]`);
        $bgr('bgr-fiscal-yr').value = currentYearOption ? currentYearOption.value : '';

        const coOption = $bgr('bgr-expense-class-id').querySelector('option[data-code="CO"]');
        $bgr('bgr-expense-class-id').value = coOption ? coOption.value : '';
    }
    // A line item's fiscal year / expense class belong to its parent header — not editable here.
    $bgr('bgr-fiscal-yr').disabled = isLineItemEdit;
    $bgr('bgr-expense-class-id').disabled = isLineItemEdit;

    await bgr_refreshLineItemColumns();

    if (isLineItemEdit) {
        bgr_clearLineItems();
        bgr_addLineItemRow(row, false);
        $bgr('bgr-add-line-item').hidden = true;
    } else if (bgr_isCoaMode) {
        bgr_clearLineItems();
        bgr_addLineItemRow({}, mode !== 'edit');
        $bgr('bgr-add-line-item').hidden = (mode === 'edit');
    } else {
        $bgr('bgr-program-code').value = row?.program_code || '';
        $bgr('bgr-project-code').value = row?.project_code || '';
        $bgr('bgr-location').value = row?.location || '';
        $bgr('bgr-amount').value = bgr_fmtAmountInitial(row?.amount);
    }

    $bgr('bgr-overlay').classList.add('open');
}

function bgr_closeModal() {
    $bgr('bgr-overlay').classList.remove('open');
    $bgr('bgr-form').reset();
    $bgr('bgr-fiscal-yr').disabled = false;
    $bgr('bgr-expense-class-id').disabled = false;
    bgr_hideErr();
}

function bgr_showErr(msg) {
    const el = $bgr('bgr-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function bgr_hideErr() {
    $bgr('bgr-err').style.display = 'none';
}

// kind: 'header' (fims.budget_req) or 'lineitem' (fims.budget_line_items)
async function bgr_edit(id, kind = 'header') {
    window.PagePreloader?.show();
    try {
        const api  = kind === 'lineitem' ? BGR_LINE_ITEM_API : BGR_API;
        const res  = await fetch(`${api}/read/${id}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget request.');
        if (kind === 'lineitem') json.data.amount = json.data.requested_amount; // normalize for bgr_addLineItemRow()
        bgr_openModal('edit', json.data, kind);
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function bgr_save(ev) {
    ev.preventDefault();
    bgr_hideErr();
    const mode       = $bgr('bgr-mode').value;
    const editTarget = $bgr('bgr-edit-target').value;
    const id         = $bgr('bgr-id').value;
    const fiscalYr       = $bgr('bgr-fiscal-yr').value;
    const expenseClassId = $bgr('bgr-expense-class-id').value;

    let url, method, body, successMsg;

    if (mode === 'edit' && editTarget === 'lineitem') {
        const tr          = $bgr('bgr-li-tbody').rows[0];
        const accountId   = tr?.querySelector('.bgr-li-account')?.value;
        const particulars = tr?.querySelector('.bgr-li-particulars')?.value.trim();
        const amount      = bgr_unformatAmount(tr?.querySelector('.bgr-li-amt')?.value);
        if (!accountId) { bgr_showErr('Select a chart of accounts entry.'); return; }
        if (!particulars) { bgr_showErr('Particulars is required.'); return; }
        if (!amount) { bgr_showErr('Amount is required.'); return; }

        url    = `${BGR_LINE_ITEM_API}/update/${id}`;
        method = 'PUT';
        body   = { account_id: accountId, particulars, requested_amount: amount };
        successMsg = 'Line item updated successfully.';

    } else if (bgr_isCoaMode) {
        // Add mode only (PS/MOOE headers aren't edited as a whole — their line
        // items are edited individually) — header + all its line items, one call.
        if (!fiscalYr) { bgr_showErr('Select a fiscal year.'); return; }
        if (!expenseClassId) { bgr_showErr('Select an expense class.'); return; }

        const rows = Array.from($bgr('bgr-li-tbody').querySelectorAll('tr'));
        if (!rows.length) { bgr_showErr('Add at least one line item.'); return; }

        const lineItems = [];
        for (let i = 0; i < rows.length; i++) {
            const tr          = rows[i];
            const accountId   = tr.querySelector('.bgr-li-account').value;
            const particulars = tr.querySelector('.bgr-li-particulars').value.trim();
            const amount      = bgr_unformatAmount(tr.querySelector('.bgr-li-amt').value);
            if (!accountId) { bgr_showErr(`Line item ${i + 1}: select a chart of accounts entry.`); return; }
            if (!particulars) { bgr_showErr(`Line item ${i + 1}: particulars is required.`); return; }
            if (!amount) { bgr_showErr(`Line item ${i + 1}: amount is required.`); return; }
            lineItems.push({ account_id: accountId, particulars, requested_amount: amount });
        }

        url    = `${BGR_API}/add-with-items`;
        method = 'POST';
        body   = { fiscal_yr: fiscalYr, expense_class_id: expenseClassId, line_items: lineItems };
        successMsg = 'Budget request created successfully.';

    } else {
        // CO: single header row, add or edit.
        if (!fiscalYr) { bgr_showErr('Select a fiscal year.'); return; }
        if (!expenseClassId) { bgr_showErr('Select an expense class.'); return; }

        const programCode = $bgr('bgr-program-code').value;
        const projectCode = $bgr('bgr-project-code').value;
        const amount      = bgr_unformatAmount($bgr('bgr-amount').value);
        if (!programCode && !projectCode) { bgr_showErr('Select a program or a project.'); return; }
        if (programCode && projectCode) { bgr_showErr('Select a program or a project, not both.'); return; }
        if (!amount) { bgr_showErr('Amount is required.'); return; }

        url    = mode === 'edit' ? `${BGR_API}/update/${id}` : `${BGR_API}/add`;
        method = mode === 'edit' ? 'PUT' : 'POST';
        body   = {
            fiscal_yr: fiscalYr, expense_class_id: expenseClassId,
            program_code: programCode, project_code: projectCode,
            location: $bgr('bgr-location').value.trim(), amount,
        };
        successMsg = `Budget request ${mode === 'edit' ? 'updated' : 'created'} successfully.`;
    }

    window.PagePreloader?.show();
    try {
        const res  = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save.');

        bgr_toast(successMsg, 'success');
        bgr_closeModal();
        bgr_load();
    } catch (e) {
        bgr_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

// kind: 'header' (fims.budget_req, cascades its line items) or 'lineitem' (fims.budget_line_items)
async function bgr_delete(id, kind = 'header') {
    const label = kind === 'lineitem' ? 'line item' : 'budget request';
    if (!confirm(`Delete this ${label}? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const api  = kind === 'lineitem' ? BGR_LINE_ITEM_API : BGR_API;
        const res  = await fetch(`${api}/delete/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || `Failed to delete ${label}.`);

        bgr_toast(`${kind === 'lineitem' ? 'Line item' : 'Budget request'} deleted successfully.`, 'success');
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
function bgr_escHtmlAttr(text) {
    return String(text ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}
function bgr_truncate(text, max) {
    const s = String(text ?? '');
    return s.length > max ? s.slice(0, max - 1) + '…' : (s || '—');
}
/* ── Comma-formatted amount inputs (display only — commas are stripped
   before validation/submission via bgr_unformatAmount) ──────────────── */
function bgr_fmtAmount(input) {
    const pos   = input.selectionStart;
    const prev  = input.value.length;
    const raw   = input.value.replace(/[^0-9.]/g, '');
    const parts = raw.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('') : parts[0];
    const diff = input.value.length - prev;
    try { input.setSelectionRange(pos + diff, pos + diff); } catch (_) {}
}
function bgr_fmtAmountInitial(v) {
    return (v || v === 0) && v !== '' ? Number(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '';
}
function bgr_unformatAmount(v) {
    return String(v ?? '').replace(/,/g, '');
}
function bgr_statusBadge(name, color) {
    if (!name) return '<span class="nha-td-sub">—</span>';
    const c = /^#[0-9A-Fa-f]{6}$/.test(color || '') ? color : '#6B7280';
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;
        font-size:11px;font-weight:600;color:${c};background:${c}1A;border:1px solid ${c}40;white-space:nowrap;">
        <span style="width:6px;height:6px;border-radius:50%;background:${c};flex-shrink:0;"></span>${bgr_esc(name)}
    </span>`;
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
