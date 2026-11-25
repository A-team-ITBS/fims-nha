<?php
namespace PHPMaker2024\FIMS;

/**
 * payroll-setup-page.php — Payroll Setup (Accounts Set-up menu). This is the real page.
 * payroll-page.php (the older "Payroll" menu entry) only includes this file, so the two cannot drift apart.
 *
 * Same shape as vendors-page.php: KPI cards, filters, table + pagination, Add / Edit modal (form/payroll-form.php).
 * This page calls ONE API, the Payroll Setup API (app/routes/payroll_setup.php → app/services/PayrollSetupService.php):
 * the item list / read / add / update (table fims.payroll_items), the account list for the Account picker, and — when the
 * form is saved with a sub-ledger (5th / 6th account part) that is not in the Chart of Accounts yet — the server creates
 * that account together with the item (same result as "Add Sub-Ledger" on chart-of-accounts-page.php).
 */
$_pyr_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="pyr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Payroll Setup</div>
                <div class="nha-hdr-sub">Payroll items &amp; chart of accounts setup</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="pyr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="pyr-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a payroll item">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Payroll Item
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover every row matching the filters, not just this page) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total Items</div>
                <div class="nha-sc-val" id="pyr-kpi-total">—</div>
            </div></div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Earnings</div>
                <div class="nha-sc-val" id="pyr-kpi-earnings">—</div>
            </div></div>
            <p class="nha-kpi-sub">Salary, PERA, allowances, bonuses</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Deductions / Refunds</div>
                <div class="nha-sc-val" id="pyr-kpi-deductions">—</div>
            </div></div>
            <p class="nha-kpi-sub">Withheld from or returned to the employee</p>
        </div>
        <div class="nha-sc nha-sc-red">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">No Account</div>
                <div class="nha-sc-val" id="pyr-kpi-noaccount">—</div>
            </div></div>
            <p class="nha-kpi-sub">Not linked to the Chart of Accounts yet</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Category</label>
                <select id="pyr-filter-category" class="nha-filter-select">
                    <option value="">All Categories</option>
                    <option value="EARNING">Earning</option>
                    <option value="DEDUCTION">Deduction</option>
                    <option value="REFUND">Refund</option>
                    <option value="EMPLOYER_SHARE">Employer Share</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Account</label>
                <select id="pyr-filter-account" class="nha-filter-select">
                    <option value="">All</option>
                    <option value="with">With an account</option>
                    <option value="none">No account yet</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="pyr-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="pyr-filter-search" class="nha-filter-input" placeholder="Search item code, name, account…">
                    <button class="nha-filter-clear-btn" id="pyr-search-clear" hidden type="button">×</button>
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
                        <th>Payroll Item</th>
                        <th>Category</th>
                        <th>Account</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pyr-tbody">
                    <tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="pyr-from">0</span>–<span id="pyr-to">0</span></strong> of <strong><span id="pyr-total">0</span></strong></span>
            <div id="pyr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/payroll-form.php'; ?>

</div><!-- /#pyr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PAYROLL ITEMS   prefix: pyr_
   List + Add / Edit against the Payroll Setup API only (app/routes/payroll_setup.php):
     GET /payroll-setup/list · /read/{id} · /accounts      POST /payroll-setup/add      PUT /payroll-setup/update/{id}
   New sub-ledgers are sent as `new_accounts`, parent first, with the item; the server creates them (and removes them again if the
   item fails to save). The account the item uses (the last part entered) is titled with the Item Name; a new 5th part that only
   makes room for a new 6th part gets its own Account Title.
═══════════════════════════════════════════════════════════════ */

const PYR_API = '<?php echo $_pyr_api; ?>' + '/payroll-setup';
const PYR_PAGE_SIZE   = 15;
const PYR_MAX_OPTIONS = 100;                                                   // account dropdown rows rendered at once

const $pyr = id => document.getElementById(id);

const PYR_FIELDS = {               // payload key → input id
    item_code : 'pyr-item-code',
    item_name : 'pyr-item-name',
    category  : 'pyr-category',
    tax_treatment : 'pyr-tax-treatment',
    status    : 'pyr-status',
    remarks   : 'pyr-remarks',
};

const PYR_CATEGORY = {             // value → [badge class, label]
    EARNING        : ['nha-bdg-green',  'Earning'],
    DEDUCTION      : ['nha-bdg-amber',  'Deduction'],
    REFUND         : ['nha-bdg-blue',   'Refund'],
    EMPLOYER_SHARE : ['nha-bdg-purple', 'Employer Share'],
};

/* The Debit / Credit side is not entered: it follows from the category. */
const PYR_SIDE = { EARNING: 'Debit', REFUND: 'Debit', EMPLOYER_SHARE: 'Debit', DEDUCTION: 'Credit' };

let pyr_page = 1;

let pyr_accounts = new Map();      // ALL active accounts: code → { code, title, postable }
let pyr_postable = [];             // the postable ones in code order — the dropdown list
let pyr_coaState = 'idle';         // idle | loading | ready | error
let pyr_coaError = '';
let pyr_coaPromise = null;

let pyr_sel    = null;             // account picked in the dropdown
let pyr_legacyAccount = null;      // edit only: the item's current account when it is no longer an active postable one (kept unless the user picks another)
let pyr_query  = '';               // text the dropdown is filtered by
let pyr_list   = [];               // rows matching pyr_query
let pyr_active = -1;               // highlighted row
let pyr_nameTouched  = false;      // the user typed their own Item Name
let pyr_sub2Open = false;          // the "+ Add 6th part" row is open (only from a 4-part account)
let pyr_saving = false;

/* ── Helpers ───────────────────────────────────────────────── */
function pyr_truthy(v) { return v === true || v === 1 || v === '1' || v === 't' || v === 'true'; }

function pyr_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}

/* Segmented code chip, same look as the Chart of Accounts page: first part neutral, the rest blue. */
function pyr_chipHtml(code) {
    const parts = String(code ?? '').split('-').filter(p => p !== '');
    return `<span class="pyr-chip">${parts.map((p, i) => `<span class="pyr-chip-seg${i === 0 ? ' is-root' : ''}">${pyr_esc(p)}</span>`).join('')}</span>`;
}

/* BASIC_SALARY → BASIC SALARY */
function pyr_deriveName(code) { return String(code).trim().replace(/_+/g, ' ').replace(/\s+/g, ' ').toUpperCase(); }

function pyr_codeCmp(a, b) { return a.code.localeCompare(b.code, undefined, { numeric: true, sensitivity: 'base' }); }
function pyr_partCount(code) { return String(code).split('-').length; }
function pyr_display(a) { return `${a.code} — ${a.title}`; }

/* ── Bind / bootstrap ──────────────────────────────────────── */
function pyr_bind() {
    $pyr('pyr-refresh')?.addEventListener('click', () => { pyr_page = 1; pyr_load(); pyr_loadAccounts(); });
    $pyr('pyr-add')?.addEventListener('click', () => pyr_openModal('add'));
    $pyr('pyr-modal-close')?.addEventListener('click', pyr_closeModal);
    $pyr('pyr-cancel')?.addEventListener('click', pyr_closeModal);
    $pyr('pyr-form')?.addEventListener('submit', pyr_save);

    ['pyr-filter-category', 'pyr-filter-account', 'pyr-filter-status'].forEach(id => {
        $pyr(id)?.addEventListener('change', () => { pyr_page = 1; pyr_load(); });
    });
    let searchTimer;
    const fs = $pyr('pyr-filter-search'), fc = $pyr('pyr-search-clear');
    fs?.addEventListener('input', () => {
        if (fc) fc.hidden = !fs.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { pyr_page = 1; pyr_load(); }, 300);
    });
    fc?.addEventListener('click', () => {
        if (fs) { fs.value = ''; fs.focus(); }
        fc.hidden = true;
        pyr_page = 1; pyr_load();
    });

    $pyr('pyr-item-code')?.addEventListener('input', pyr_onCode);
    $pyr('pyr-item-name')?.addEventListener('input', pyr_onName);
    $pyr('pyr-category')?.addEventListener('change', pyr_syncSide);

    const search = $pyr('pyr-account-search');
    search?.addEventListener('input', pyr_onSearch);
    search?.addEventListener('focus', pyr_onFocus);
    search?.addEventListener('click', () => { if ($pyr('pyr-account-panel').hidden) pyr_onFocus(); });
    search?.addEventListener('keydown', pyr_onKey);
    search?.addEventListener('blur', pyr_onBlur);
    $pyr('pyr-account-clear')?.addEventListener('click', pyr_clearAccount);
    $pyr('pyr-account-panel')?.addEventListener('mousedown', e => e.preventDefault());   // keep focus in the search box
    $pyr('pyr-account-list')?.addEventListener('click', e => {
        const opt = e.target.closest('.pyr-opt');
        if (opt) pyr_pick(opt.dataset.code);
    });
    $pyr('pyr-sub-part')?.addEventListener('input', pyr_syncAccountUi);
    $pyr('pyr-sub-part2')?.addEventListener('input', pyr_syncAccountUi);
    $pyr('pyr-sub-add6')?.addEventListener('click', () => { pyr_sub2Open = true; pyr_syncAccountUi(); $pyr('pyr-sub-part2').focus(); });
    $pyr('pyr-sub2-remove')?.addEventListener('click', () => {
        pyr_sub2Open = false;
        $pyr('pyr-sub-part2').value = '';
        pyr_syncAccountUi();
    });
    $pyr('pyr-body')?.addEventListener('scroll', pyr_positionPanel);
    window.addEventListener('resize', pyr_positionPanel);

    pyr_load();
    pyr_loadAccounts();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', pyr_bind);
} else {
    pyr_bind();
}

/* ── List ──────────────────────────────────────────────────── */
async function pyr_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search   : $pyr('pyr-filter-search')?.value   || '',
            category : $pyr('pyr-filter-category')?.value || '',
            account  : $pyr('pyr-filter-account')?.value  || '',
            status   : $pyr('pyr-filter-status')?.value   || '',
            page     : pyr_page,
            limit    : PYR_PAGE_SIZE,
        });
        const res  = await fetch(`${PYR_API}/list?${params}`, { credentials: 'same-origin' });
        let json = null;
        try { json = await res.json(); } catch (_) { /* non-JSON error body */ }
        if (!res.ok || !json) throw new Error((json && json.message) || 'HTTP ' + res.status);   // prefer the server's own message
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        pyr_renderTable(json.data ?? []);
        pyr_renderPagination(json.pagination ?? {});
        $pyr('pyr-kpi-total').textContent      = json.pagination?.total ?? (json.data ?? []).length;
        $pyr('pyr-kpi-earnings').textContent   = json.summary?.earnings   ?? '—';
        $pyr('pyr-kpi-deductions').textContent = json.summary?.deductions ?? '—';
        $pyr('pyr-kpi-noaccount').textContent  = json.summary?.no_account ?? '—';
    } catch (e) {
        pyr_renderTable([], e.message);
        pyr_renderPagination({});
        pyr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function pyr_categoryBadge(c) {
    const [cls, label] = PYR_CATEGORY[c] || ['nha-bdg-gray', c || '—'];
    return `<span class="nha-bdg ${cls}">${pyr_esc(label)}</span>`;
}
function pyr_statusBadge(s) {
    const st  = (s || 'ACTIVE').toUpperCase();
    const map = { ACTIVE: ['nha-bdg-green', 'Active'], INACTIVE: ['nha-bdg-gray', 'Inactive'] };
    const [cls, label] = map[st] || ['nha-bdg-gray', st];
    return `<span class="nha-bdg ${cls}">${pyr_esc(label)}</span>`;
}

function pyr_renderTable(rows, errorMsg = '') {
    const tbody = $pyr('pyr-tbody');
    if (!rows.length) {
        tbody.innerHTML = errorMsg
            ? `<tr><td colspan="6"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Could not load payroll items.</span><span class="nha-empty-text">${pyr_esc(errorMsg)}</span></div></td></tr>`
            : '<tr><td colspan="6"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No payroll items found.</span><span class="nha-empty-text">Use “Add Payroll Item” to create one.</span></div></td></tr>';
        return;
    }
    const offset = (pyr_page - 1) * PYR_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const side = PYR_SIDE[r.category];
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>
            <div style="font-weight:600;">${pyr_esc(r.item_name)}</div>
            <div class="nha-td-sub" style="margin-top:2px;"><code class="nha-code">${pyr_esc(r.item_code)}</code></div>
        </td>
        <td>
            ${pyr_categoryBadge(r.category)}
            ${side ? `<div class="nha-td-sub" style="margin-top:2px;">Posts as ${side}</div>` : ''}
        </td>
        <td>
            ${r.account_code
                ? `<div style="display:flex;align-items:center;gap:8px;">${pyr_chipHtml(r.account_code)}</div>
                   ${r.account_title ? `<div class="nha-td-sub" style="margin-top:3px;">${pyr_esc(r.account_title)}</div>` : ''}`
                : '<span class="nha-bdg nha-bdg-amber">No account</span>'}
        </td>
        <td>${pyr_statusBadge(r.status)}</td>
        <td>
            <button class="nha-btn-act edit" onclick="pyr_edit(${Number(r.payroll_item_id)})" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
        </td>
    </tr>`;
    }).join('');
}

function pyr_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = PYR_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $pyr('pyr-from').textContent  = from;
    $pyr('pyr-to').textContent    = to;
    $pyr('pyr-total').textContent = total;

    const container = $pyr('pyr-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="pyr_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="pyr_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="pyr_goTo(${current_page + 1})">›</button>`;
}

function pyr_goTo(page) { if (page >= 1) { pyr_page = page; pyr_load(); } }

/* ── Item Code → Item Name ─────────────────────────────────── */
function pyr_onCode() {
    if (!pyr_nameTouched) $pyr('pyr-item-name').value = pyr_deriveName($pyr('pyr-item-code').value);
}
function pyr_onName() {
    const name = $pyr('pyr-item-name').value;
    pyr_nameTouched = name !== '' && name !== pyr_deriveName($pyr('pyr-item-code').value);   // emptied or matching again → follow the code again
}
/* Forgets the next-part inputs, the title of a new 5th part and the 6th-part row (a different account was picked / the form was reset). */
function pyr_clearParts() {
    $pyr('pyr-sub-part').value = '';  $pyr('pyr-sub-part2').value = '';
    $pyr('pyr-new-title').value = '';
    pyr_sub2Open = false;
}

/* Tells the user which side the chosen category posts to. */
function pyr_syncSide() {
    const side = PYR_SIDE[$pyr('pyr-category').value];
    $pyr('pyr-side-hint').textContent = side
        ? `Posts to its account as a ${side}.`
        : 'Decides how the item posts to its account.';
}

/* ── Chart of Accounts ─────────────────────────────────────── */
function pyr_loadAccounts() {
    if (pyr_coaState === 'loading') return pyr_coaPromise;
    pyr_coaPromise = pyr_doLoadAccounts();
    return pyr_coaPromise;
}
async function pyr_doLoadAccounts() {
    pyr_coaState = 'loading'; pyr_coaError = '';
    pyr_syncAccountUi();
    try {
        const res  = await fetch(`${PYR_API}/accounts`, { credentials: 'same-origin' });
        let json = null;
        try { json = await res.json(); } catch (_) { /* non-JSON error body */ }
        if (!res.ok || !json) throw new Error((json && json.message) || 'HTTP ' + res.status);
        if (!json.success) throw new Error(json.message || 'Failed to load the chart of accounts.');

        pyr_accounts = new Map();
        for (const a of (json.data ?? [])) {
            if (!a.account_code) continue;
            pyr_accounts.set(a.account_code, { code: a.account_code, title: a.account_title || '', postable: pyr_truthy(a.posting_allowed) });
        }
        pyr_postable = [...pyr_accounts.values()].filter(a => a.postable).sort(pyr_codeCmp);
        // a selection made before a refresh keeps working: point it at the fresh record
        if (pyr_sel && pyr_accounts.has(pyr_sel.code)) pyr_sel = pyr_accounts.get(pyr_sel.code);
        pyr_coaState = 'ready';
    } catch (e) {
        pyr_coaState = 'error'; pyr_coaError = e.message;
        pyr_toast('Chart of accounts could not be loaded: ' + e.message, 'error');
    }
    pyr_syncAccountUi();
    if (!$pyr('pyr-account-panel').hidden) pyr_renderPanel();
}

/* ── Account dropdown ──────────────────────────────────────── */
function pyr_filter(q) {
    q = q.trim().toLowerCase();
    if (!q) return pyr_postable;
    const tokens = q.split(/\s+/);
    const hits = pyr_postable.filter(a => {
        const hay = (a.code + ' ' + a.title).toLowerCase();
        return tokens.every(t => hay.includes(t));
    });
    // codes that START with the text first; the sort is stable, so each group stays in code order
    return hits.sort((a, b) => Number(b.code.startsWith(q)) - Number(a.code.startsWith(q)));
}

function pyr_renderPanel() {
    const list = $pyr('pyr-account-list'), foot = $pyr('pyr-account-foot');
    list.textContent = '';

    if (pyr_coaState === 'loading' || pyr_coaState === 'error') {
        pyr_list = []; pyr_active = -1;
        const d = document.createElement('div');
        d.className = 'pyr-combo-empty';
        d.textContent = pyr_coaState === 'loading' ? 'Loading chart of accounts…' : 'Chart of accounts unavailable: ' + pyr_coaError;
        list.appendChild(d); foot.textContent = '';
        pyr_positionPanel();
        return;
    }

    pyr_list = pyr_filter(pyr_query);
    const shown = pyr_list.slice(0, PYR_MAX_OPTIONS);
    if (!shown.length) {
        const d = document.createElement('div');
        d.className = 'pyr-combo-empty';
        d.textContent = pyr_query.trim() ? `No postable account matches “${pyr_query.trim()}”.` : 'No postable accounts.';
        list.appendChild(d);
    }
    shown.forEach((a, i) => {
        const o = document.createElement('div');
        o.className = 'pyr-opt';
        o.id = 'pyr-opt-' + i;
        o.setAttribute('role', 'option');
        o.setAttribute('aria-selected', String(!!pyr_sel && pyr_sel.code === a.code));
        o.dataset.code = a.code;
        o.innerHTML = pyr_chipHtml(a.code);
        const t = document.createElement('span');
        t.className = 'pyr-opt-title';
        t.textContent = a.title;
        t.title = a.title;
        o.appendChild(t);
        list.appendChild(o);
    });
    const selIdx = pyr_sel ? shown.findIndex(a => a.code === pyr_sel.code) : -1;
    pyr_active = shown.length ? Math.max(selIdx, 0) : -1;
    foot.textContent = pyr_list.length > shown.length
        ? `${pyr_list.length} accounts — showing the first ${PYR_MAX_OPTIONS}. Keep typing to narrow.`
        : `${pyr_list.length} account${pyr_list.length === 1 ? '' : 's'}`;
    pyr_markActive(true);
    pyr_positionPanel();
}

function pyr_markActive(scroll) {
    const input = $pyr('pyr-account-search');
    $pyr('pyr-account-list').querySelectorAll('.pyr-opt').forEach((o, i) => o.classList.toggle('is-active', i === pyr_active));
    if (pyr_active >= 0) {
        input.setAttribute('aria-activedescendant', 'pyr-opt-' + pyr_active);
        if (scroll) $pyr('pyr-opt-' + pyr_active)?.scrollIntoView?.({ block: 'nearest' });
    } else {
        input.removeAttribute('aria-activedescendant');
    }
}

/* The panel is position:fixed so the modal body's overflow cannot clip it; it opens upward when there is more room above. */
function pyr_positionPanel() {
    const panel = $pyr('pyr-account-panel');
    if (!panel || panel.hidden) return;
    const r = $pyr('pyr-account-search').getBoundingClientRect();
    const below = window.innerHeight - r.bottom - 12, above = r.top - 12;
    const up = below < 200 && above > below;
    const maxH = Math.max(120, Math.min(320, up ? above : below));
    panel.style.left = r.left + 'px';
    panel.style.width = r.width + 'px';
    panel.style.maxHeight = maxH + 'px';
    if (up) { panel.style.top = 'auto'; panel.style.bottom = (window.innerHeight - r.top + 4) + 'px'; }
    else    { panel.style.bottom = 'auto'; panel.style.top = (r.bottom + 4) + 'px'; }
}

function pyr_openPanel() {
    const panel = $pyr('pyr-account-panel');
    panel.hidden = false;
    $pyr('pyr-account-search').setAttribute('aria-expanded', 'true');
    pyr_renderPanel();
}
function pyr_closePanel() {
    $pyr('pyr-account-panel').hidden = true;
    const input = $pyr('pyr-account-search');
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
}

function pyr_onFocus() {
    pyr_query = pyr_sel ? '' : $pyr('pyr-account-search').value;     // a picked account → show the whole list again
    pyr_openPanel();
}
function pyr_onSearch() {
    pyr_sel = null;                                                  // typing replaces the picked account
    pyr_legacyAccount = null;
    pyr_query = $pyr('pyr-account-search').value;
    pyr_clearParts();
    pyr_syncAccountUi();
    pyr_openPanel();
}
function pyr_onBlur() {
    pyr_closePanel();
    pyr_pickExactTyped();
    $pyr('pyr-account-search').value = pyr_sel ? pyr_display(pyr_sel) : '';
    pyr_syncAccountUi();
}
/* Typing a full code and leaving the box (or pressing Enter) picks that account. */
function pyr_pickExactTyped() {
    if (pyr_sel) return;
    const a = pyr_accounts.get($pyr('pyr-account-search').value.trim());
    if (a && a.postable) pyr_pick(a.code, false);
}

function pyr_move(delta) {
    const n = Math.min(pyr_list.length, PYR_MAX_OPTIONS);
    if (!n) return;
    pyr_active = (pyr_active + delta + n) % n;
    pyr_markActive(true);
}
function pyr_onKey(e) {
    const open = !$pyr('pyr-account-panel').hidden;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (open) pyr_move(1); else pyr_onFocus();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (open) pyr_move(-1);
    } else if (e.key === 'Enter') {
        if (open || $pyr('pyr-account-search').value.trim()) e.preventDefault();      // never submit the form from here
        const a = open ? pyr_list[pyr_active] : null;
        if (a) pyr_pick(a.code); else pyr_pickExactTyped();
    } else if (e.key === 'Escape' && open) {
        e.preventDefault(); e.stopPropagation();
        pyr_closePanel();
        $pyr('pyr-account-search').value = pyr_sel ? pyr_display(pyr_sel) : $pyr('pyr-account-search').value;
    } else if (e.key === 'Tab') {
        pyr_closePanel();
    }
}

function pyr_pick(code, focusNext = true) {
    const a = pyr_accounts.get(code);
    if (!a) return;
    pyr_sel = a;
    pyr_legacyAccount = null;
    pyr_query = '';
    $pyr('pyr-account-search').value = pyr_display(a);
    pyr_clearParts();
    pyr_closePanel();
    pyr_syncAccountUi();
    if (focusNext && !$pyr('pyr-sub').hidden) $pyr('pyr-sub-part').focus();
}

function pyr_clearAccount() {
    pyr_sel = null; pyr_legacyAccount = null; pyr_query = '';
    $pyr('pyr-account-search').value = '';
    pyr_clearParts();
    pyr_syncAccountUi();
    $pyr('pyr-account-search').focus();
}

/* ── Next parts (5th / 6th) ────────────────────────────────── */
/* What the picked account + the typed next part(s) amount to. From a 4-part account the 5th part is one level and the
   optional 6th part (after "+ Add 6th part") a second; from a 5-part account the single input is the 6th part.
   state: none | base (no part typed) | invalid | exists | new | notpostable | parentblocked
   levels: [{ code, acct, state: exists | notpostable | new }] in order (parent first) */
function pyr_resolve() {
    if (!pyr_sel) return { state: 'none', levels: [] };
    const n = pyr_partCount(pyr_sel.code);
    if (n >= 6) return { state: 'base', code: pyr_sel.code, acct: pyr_sel, levels: [] };
    const raws = [$pyr('pyr-sub-part').value.trim()];
    if (n === 4 && pyr_sub2Open) raws.push($pyr('pyr-sub-part2').value.trim());

    const levels = [];
    let cur = pyr_sel.code;
    for (let i = 0; i < raws.length; i++) {
        if (!raws[i]) break;
        if (!/^\d{1,3}$/.test(raws[i])) return { state: 'invalid', level: i, levels };
        cur += '-' + raws[i].padStart(2, '0');
        const acct = pyr_accounts.get(cur);
        levels.push({ code: cur, acct, state: !acct ? 'new' : (acct.postable ? 'exists' : 'notpostable') });
    }
    if (!levels.length) return { state: 'base', code: pyr_sel.code, acct: pyr_sel, levels };

    const last = levels[levels.length - 1];
    // a new account cannot hang under an existing account that does not allow posting
    const blockedIdx = levels.findIndex((l, i) => i > 0 && l.state === 'new' && levels[i - 1].state === 'notpostable');
    let state;
    if (last.state === 'notpostable') state = 'notpostable';
    else if (blockedIdx >= 0)         state = 'parentblocked';
    else                              state = levels.some(l => l.state === 'new') ? 'new' : 'exists';
    return { state, code: last.code, acct: last.acct, levels, blockedIdx };
}

function pyr_ordinal(n) { return n + 'th'; }

/* Hint under one part row, from its resolved level. `last` = this is the account the item will use (it gets the Item Name as its
   title); a new level that is NOT the last one is only created so that the next part can hang under it, and needs its own title. */
function pyr_levelHint(el, level, whenEmpty, blockedBy, last) {
    el.className = 'pyr-hint';
    if (!level)                          { el.textContent = whenEmpty; return; }
    if (blockedBy)                       { el.textContent = `${blockedBy.code} exists but does not allow posting, so ${level.code} cannot be added under it.`; el.classList.add('bad'); return; }
    if (level.state === 'exists')        { el.textContent = `${level.code} — ${level.acct.title}`; el.classList.add('ok'); return; }
    if (level.state === 'notpostable')   { el.textContent = `${level.code} exists but does not allow posting.`; el.classList.add('bad'); return; }
    el.textContent = last
        ? `${level.code} does not exist yet. It is added to the Chart of Accounts when you save, with the Item Name as its account title.`
        : `${level.code} does not exist yet. It is added to the Chart of Accounts too, so the next part can sit under it — give it an Account Title below.`;
}

function pyr_syncAccountUi() {
    const hint = $pyr('pyr-account-hint');
    if (!hint) return;
    $pyr('pyr-account-clear').hidden = !($pyr('pyr-account-search').value || pyr_sel || pyr_legacyAccount);

    hint.className = 'pyr-hint';
    if (pyr_coaState === 'loading')      hint.textContent = 'Loading chart of accounts…';
    else if (pyr_coaState === 'error')   { hint.textContent = 'Chart of accounts unavailable: ' + pyr_coaError; hint.classList.add('bad'); }
    else if (pyr_legacyAccount && !pyr_sel) { hint.textContent = `Current account ${pyr_legacyAccount} is no longer an active, postable account. It is kept unless you pick another.`; hint.classList.add('bad'); }
    else if (!pyr_sel)                   hint.textContent = `Search ${pyr_postable.length} postable accounts. Leave blank if none assigned yet.`;
    else                                 hint.textContent = pyr_partCount(pyr_sel.code) < 6
        ? `Posts to this account. Add a ${pyr_ordinal(pyr_partCount(pyr_sel.code) + 1)} part below to use a sub-ledger of it.`
        : 'Posts to this account.';

    const n = pyr_sel ? pyr_partCount(pyr_sel.code) : 0;
    const canExtend = !!pyr_sel && n < 6;

    // the 6th-part row only exists under a valid 5th part, and only from a 4-part account
    let r = pyr_resolve();
    const level0Valid = r.levels.length >= 1;
    if (pyr_sub2Open && !(n === 4 && level0Valid)) {
        pyr_sub2Open = false;
        $pyr('pyr-sub-part2').value = '';
        r = pyr_resolve();
    }
    const [l0, l1] = r.levels;

    // 1st row: the 5th part (or the 6th, from a 5-part account)
    $pyr('pyr-sub').hidden = !canExtend;
    if (canExtend) {
        $pyr('pyr-sub-label').textContent = `${pyr_ordinal(n + 1)} part (sub-ledger)`;
        $pyr('pyr-sub-prefix').innerHTML = pyr_chipHtml(pyr_sel.code);
        const subHint = $pyr('pyr-sub-hint');
        if (r.state === 'invalid' && r.level === 0) { subHint.className = 'pyr-hint bad'; subHint.textContent = 'Digits only, e.g. 37.'; }
        else pyr_levelHint(subHint, l0, `Optional. Leave blank to post to ${pyr_sel.code}.`, null, !l1);
    } else {
        $pyr('pyr-sub-part').value = '';
    }

    // "+ Add 6th part" (from a 4-part account, once the 5th part is a valid number)
    $pyr('pyr-sub-add6').hidden = !(n === 4 && level0Valid && !pyr_sub2Open);

    // 2nd row: the 6th part
    $pyr('pyr-sub2').hidden = !(n === 4 && pyr_sub2Open && level0Valid);
    if (!$pyr('pyr-sub2').hidden) {
        $pyr('pyr-sub2-prefix').innerHTML = pyr_chipHtml(l0.code);
        const sub2Hint = $pyr('pyr-sub2-hint');
        if (r.state === 'invalid' && r.level === 1) { sub2Hint.className = 'pyr-hint bad'; sub2Hint.textContent = 'Digits only, e.g. 01.'; }
        else pyr_levelHint(sub2Hint, l1, `Enter the 6th part, or remove it to post to ${l0.code}.`, l1 && l1.state === 'new' && l0.state === 'notpostable' ? l0 : null, true);
    }

    // The account the item uses is the LAST part entered and takes the Item Name as its title (no box for it).
    // Only a NEW 5th part that a 6th part hangs under is created just to make the hierarchy exist — that one needs its own title.
    // (The box keeps what was typed while it is only hidden for a moment; it is cleared by pyr_clearParts.)
    const box = $pyr('pyr-new');
    box.hidden = !(l0 && l0.state === 'new' && l1);
    if (!box.hidden) {
        $pyr('pyr-new-heading').textContent = `New ${pyr_ordinal(pyr_partCount(l0.code))}-part account ${l0.code}`;
        $pyr('pyr-new-hint').textContent = `It does not exist yet. It is added to the Chart of Accounts under ${pyr_sel.code}, so that ${l1.code} can sit under it.`;
    }
}

/* ── Modal (Add / Edit) ────────────────────────────────────── */
function pyr_resetState() {
    $pyr('pyr-form').reset();
    pyr_sel = null; pyr_legacyAccount = null; pyr_query = ''; pyr_list = []; pyr_active = -1;
    pyr_nameTouched = false;
    pyr_closePanel();
    $pyr('pyr-account-search').value = '';
    $pyr('pyr-account-search').placeholder = 'Search account code or title…';
    pyr_clearParts();
    pyr_syncAccountUi();
    pyr_syncSide();
}

async function pyr_openModal(mode, row = null) {
    pyr_hideErr();
    pyr_resetState();
    $pyr('pyr-mode').value = mode;
    $pyr('pyr-id').value   = row?.payroll_item_id || '';
    $pyr('pyr-modal-title').textContent = mode === 'edit' ? 'Edit Payroll Item' : 'Add Payroll Item';
    $pyr('pyr-status').value = 'ACTIVE';
    $pyr('pyr-item-code').disabled = mode === 'edit';
    $pyr('pyr-code-hint').textContent = mode === 'edit'
        ? 'Item codes cannot be changed after creation.'
        : 'Unique key. Same as the Excel column header, without the quotes.';
    if (pyr_coaState === 'error') pyr_loadAccounts();      // retry a failed load

    if (mode === 'edit' && row) {
        $pyr('pyr-item-code').value = row.item_code ?? '';
        $pyr('pyr-item-name').value = row.item_name ?? '';
        pyr_nameTouched = true;                             // never overwrite the saved name
        $pyr('pyr-category').value = row.category ?? '';
        $pyr('pyr-tax-treatment').value = row.tax_treatment || 'NONE';
        $pyr('pyr-status').value   = row.status || 'ACTIVE';
        $pyr('pyr-remarks').value  = row.remarks ?? '';
        pyr_syncSide();
        $pyr('pyr-overlay').classList.add('open');
        if (row.account_code) {
            if (pyr_coaState === 'loading' && pyr_coaPromise) await pyr_coaPromise;   // the account list may still be arriving
            const a = pyr_accounts.get(row.account_code);
            if (a && a.postable) {
                pyr_pick(a.code, false);
            } else if (pyr_coaState === 'ready') {
                pyr_legacyAccount = row.account_code;       // exists on the item but is no longer pickable
                $pyr('pyr-account-search').placeholder = `Current: ${row.account_code} (no longer postable)`;
                pyr_syncAccountUi();
            }
        }
        return;
    }
    $pyr('pyr-overlay').classList.add('open');
    $pyr('pyr-item-code').focus();
}

function pyr_closeModal() {
    $pyr('pyr-overlay').classList.remove('open');
    pyr_resetState();
    $pyr('pyr-item-code').disabled = false;
    pyr_hideErr();
}

function pyr_showErr(msg) {
    const el = $pyr('pyr-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function pyr_hideErr() {
    $pyr('pyr-err').style.display = 'none';
}

async function pyr_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${PYR_API}/read/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load payroll item.');
        await pyr_openModal('edit', json.data);
    } catch (e) {
        pyr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function pyr_save(ev) {
    ev.preventDefault();
    if (pyr_saving) return;
    pyr_hideErr();
    const mode = $pyr('pyr-mode').value;
    const id   = $pyr('pyr-id').value;

    const v = {};
    for (const [key, inputId] of Object.entries(PYR_FIELDS)) {
        v[key] = $pyr(inputId).value.trim();
    }
    v.item_code = v.item_code.toUpperCase();

    if (!v.item_code) { pyr_showErr('Item code is required.'); return; }
    if (!v.item_name) { pyr_showErr('Item name is required.'); return; }
    if (!v.category)  { pyr_showErr('Category is required.'); return; }

    const acc = pyr_resolve();
    const newAccounts = [];                                         // sub-ledgers to create, parent first
    if (pyr_sel) {
        if (pyr_coaState !== 'ready') { pyr_showErr('The chart of accounts is not loaded, so the account cannot be checked.'); return; }
        const first = pyr_partCount(pyr_sel.code) + 1;
        if (acc.state === 'invalid')       { pyr_showErr(`The ${pyr_ordinal(first + acc.level)} part must be digits only, e.g. 37.`); return; }
        if (acc.state === 'notpostable')   { pyr_showErr(`Account ${acc.code} exists but does not allow posting.`); return; }
        if (acc.state === 'parentblocked') { pyr_showErr(`Account ${acc.levels[acc.blockedIdx - 1].code} exists but does not allow posting, so ${acc.levels[acc.blockedIdx].code} cannot be added under it.`); return; }
        for (let i = 0; i < acc.levels.length; i++) {
            if (acc.levels[i].state !== 'new') continue;
            const isLast = i === acc.levels.length - 1;
            // the account the item uses is titled with the Item Name; a new 5th part that only makes room for the 6th has its own title
            const title = isLast ? v.item_name : $pyr('pyr-new-title').value.trim();
            if (!title) { pyr_showErr(`Enter an account title for the new sub-ledger ${acc.levels[i].code}.`); return; }
            newAccounts.push({ account_code: acc.levels[i].code, account_title: title });
        }
    } else if ($pyr('pyr-account-search').value.trim()) {
        pyr_showErr('Pick an account from the list, or clear the account field.'); return;
    }

    // the account the item will carry: the picked (or new) one, the unchanged legacy one, or none
    const accountCode = pyr_sel ? acc.code : (pyr_legacyAccount || '');
    const payload = {
        item_name    : v.item_name,
        category     : v.category,
        tax_treatment: v.tax_treatment || 'NONE',
        status       : v.status,
        remarks      : v.remarks,
        account_code : accountCode,
    };
    if (mode !== 'edit') payload.item_code = v.item_code;          // the code is immutable once created
    // Sub-ledgers that are not in the chart yet: the server creates them (parent first) together with the item,
    // and takes them back out if the item fails to save
    if (newAccounts.length) payload.new_accounts = newAccounts;

    pyr_saving = true;
    $pyr('pyr-save').disabled = true;
    try {
        const res = await fetch(mode === 'edit' ? `${PYR_API}/update/${encodeURIComponent(id)}` : `${PYR_API}/add`, {
            method: mode === 'edit' ? 'PUT' : 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        let json = null;
        try { json = await res.json(); } catch (_) { /* non-JSON error body */ }
        if (!res.ok || !json || !json.success) {
            throw new Error((json && json.message) || `Failed to save the payroll item (HTTP ${res.status}).`);
        }

        const created = json.data?.created_accounts ?? [];
        if (created.length) {
            pyr_toast(`Sub-ledger${created.length > 1 ? 's' : ''} ${created.join(' and ')} ${created.length > 1 ? 'were' : 'was'} added to the Chart of Accounts.`, 'success');
            pyr_loadAccounts();                                     // pick up the new account(s) for the next item
        }
        pyr_toast(`Payroll item ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        pyr_closeModal();
        pyr_load();
    } catch (e) {
        pyr_showErr(e.message);       // nothing was saved, and no sub-ledger was left behind
    } finally {
        pyr_saving = false;
        $pyr('pyr-save').disabled = false;
    }
}

/* ── Toast ─────────────────────────────────────────────────── */
function pyr_toast(msg, type = 'info') {
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
    span.textContent = msg;
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
