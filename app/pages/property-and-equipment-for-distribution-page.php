<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_mi_api = Config('API.FIMS_URL');
/*
 * property-and-equipment-for-distribution-page.php — Property and Equipment for Distribution (PHPMaker page PropertyAndEquipmentForDistributionPage, view views/PropertyAndEquipmentForDistributionPage.php).
 *
 * The infrastructure projects that were TRANSFERRED from Work-In-Process (Work-In-Process page → Actions → Transfer to MI/PED) to
 *   1-04-02-090-01 Land                              (tab 1)
 *   1-04-02-090-02 Building / Community Facilities  (tab 2)
 * A transfer sets the record's transfer_line and moves its category to the new account; this page lists the records by that category.
 * API: GET /infra-iar/property-distribution (routes/infra_iar.php → InfraIarService::propertyDistribution()),
 *      GET /infra-iar/ped-categories (the tabs), GET /infra-iar/ped-filter-options (Book of Accounts / Regional Office filters).
 * Everything about the project (office, project code / name) is read live from the RFA; Total Amount is the amount that was transferred;
 * Cost Per Sqm / Unit = Total Amount / Area in SQM / Units.
 */
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
/* Actions dropdown (the same menu as the Work-In-Process page) */
.pd-menu { position:relative;display:inline-block; }
.pd-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
    border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Sans',sans-serif; }
.pd-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA); }
.pd-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.pd-menu-drop { display:none;position:fixed;min-width:190px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
.pd-menu-drop.open { display:block; }
.pd-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;font-family:'IBM Plex Sans',sans-serif; }
.pd-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.pd-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
html.afims-dark[data-bs-theme="dark"] .pd-menu-drop { background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);box-shadow:0 8px 24px rgba(0,0,0,.45); }
html.afims-dark[data-bs-theme="dark"] .pd-menu-item { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] .pd-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
html.afims-dark[data-bs-theme="dark"] .pd-menu-trigger { color:#FFFFFF;background:#3861BC;border-color:#3861BC; }
#pd-root .nha-tbl th { white-space:nowrap;line-height:1.3; }
#pd-root .nha-tbl td { vertical-align:top; }
#pd-root .nha-table-wrap .nha-tbl th { text-align:center !important; }   /* the list's column headers are centered (the cells keep their own alignment) */
#pd-root .pd-num { text-align:right;white-space:nowrap; }
/* Book / Region filter dropdowns (searchable; same look as the Work-In-Process page) */
.pd-fc { position:relative; min-width:230px; }
.pd-fc input { width:100%; box-sizing:border-box; padding-right:28px; }
.pd-fc-clear { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; cursor:pointer; font-size:16px; line-height:1; color:var(--afims-text-secondary,#6b7280); padding:2px 4px; }
.pd-fc-list { display:none; position:absolute; z-index:60; top:calc(100% + 4px); left:0; min-width:100%; width:max-content; max-width:min(420px, 90vw); max-height:260px; overflow:auto;
              background:var(--afims-surface,#fff); border:1px solid var(--afims-border,#d1d5db); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.pd-fc.open .pd-fc-list { display:block; }
.pd-fc-opt { padding:8px 12px; font-size:13px; cursor:pointer; white-space:normal; word-break:break-word; }
.pd-fc.pd-fc-right .pd-fc-list { left:auto; right:0; }   /* the last filter opens leftwards so its list stays on screen */
.pd-fc-opt:hover { background:var(--afims-surface-subtle,#F4F7FA); }
.pd-fc-opt.active { font-weight:600; color:var(--afims-primary,#1d4ed8); }
.pd-fc-empty { padding:8px 12px; font-size:13px; color:var(--afims-text-secondary,#6b7280); }
/* View window */
.pd-grid { display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:12px 24px; }
.pd-k { font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted, #62657E); }
.pd-v { font-size:13px;color:var(--afims-text, #1A1B21);margin-top:2px;word-break:break-word; }
.pd-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:14px 16px;margin-bottom:14px; }
.pd-card h4 { margin:0 0 10px;font-size:13px;font-weight:700;color:var(--afims-heading-fill, #000E3E); }
html.afims-dark[data-bs-theme="dark"] .pd-card h4 { color:#FFFFFF; }
@media (max-width:760px) { .pd-grid { grid-template-columns:minmax(0, 1fr); } }
</style>

<div id="pd-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Property and Equipment for Distribution</h1>
            <p class="app-page-subtitle">Infrastructure projects transferred from Work-In-Process to Property and Equipment for Distribution, with their area and cost per unit.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="pd-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── TABS: Land / Building and Community Facilities (account titles only) ── -->
    <div class="nha-tabs no-print" id="pd-tabs"></div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="pd-filter-search" class="nha-filter-input" placeholder="Search project code, project name, location, contractor, book…">
                    <button class="nha-filter-clear-btn" id="pd-search-clear" hidden type="button">×</button>
                </div>
            </div>
            <!-- searchable dropdowns (built by pd_fcBuild(); the choices come from GET /infra-iar/ped-filter-options) -->
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book of Accounts</label>
                <div class="pd-fc" id="pd-fc-book" data-all="All books"></div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Regional Office/District Office</label>
                <div class="pd-fc pd-fc-right" id="pd-fc-region" data-all="All offices"></div>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>BOOK OF <br> ACCOUNTS</th>
                        <th>REGIONAL OFFICE/DISTRICT <br> OFFICE</th>
                        <th>PROJECT <br> CODE</th>
                        <th>PROJECT <br> NAME</th>
                        <th style="text-align:right;">AREA / <br> UNITS</th>
                        <th style="text-align:right;">TOTAL <br> AMOUNT</th>
                        <th style="text-align:right;">COST PER <br> SQM / UNIT</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pd-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="pd-from">0</span>–<span id="pd-to">0</span></strong> of <strong><span id="pd-total">0</span></strong></span>
            <div id="pd-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ── VIEW WINDOW ────────────────────────────────────── -->
    <div class="nha-overlay" id="pd-overlay">
        <div class="nha-modal u-modal-lg">
            <div class="nha-modal-hdr">
                <h3 id="pd-view-title">Property and Equipment for Distribution</h3>
                <button type="button" class="nha-modal-close" id="pd-view-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="pd-view-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="pd-view-close2">Close</button>
            </div>
        </div>
    </div>

</div><!-- /#pd-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PROPERTY AND EQUIPMENT FOR DISTRIBUTION   prefix: pd_   (GET /infra-iar/property-distribution)
═══════════════════════════════════════════════════════════════ */
const PD_API = '<?php echo $_mi_api; ?>' + '/infra-iar';
const PD_PAGE_SIZE = 10;
let pd_page = 1, pd_rows = [], pd_timer = null;
let pd_tab = '', pd_tabTitle = '';        // the active tab: 1-04-02-090-01 | 1-04-02-090-02 and its title
let pd_autoView = new URLSearchParams(window.location.search).get('open') || '';   // ?open= (from the Work-In-Process page; not ?view=, which the framework itself reads as a template name): open this project's View window once the list is loaded

const pd_$ = id => document.getElementById(id);
function pd_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}
function pd_esc(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
const pd_dash  = v => (v === null || v === undefined || v === '') ? '—' : pd_esc(v);
const pd_money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pd_orDash = v => (v === null || v === undefined || v === '') ? '—' : pd_money(v);

/* ── Filters: Book of Accounts and Regional Office/District Office — searchable dropdowns ── */
const PD_FC = { 'pd-fc-book': { param: 'book_id', key: 'books' }, 'pd-fc-region': { param: 'rc_id', key: 'regions' } };
const pd_fcSel  = { book_id: '', rc_id: '' };                // the chosen value of each filter ('' = all)
let   pd_fcData = { books: [], regions: [] };                // [{value, label}] from GET /infra-iar/ped-filter-options
function pd_fcBuild() {
    Object.entries(PD_FC).forEach(([id, cfg]) => {
        const wrap = pd_$(id);
        if (!wrap) return;
        wrap.innerHTML = '<input type="text" class="nha-filter-input" autocomplete="off" placeholder="' + pd_esc(wrap.dataset.all) + '">'
            + '<button type="button" class="pd-fc-clear" hidden title="Clear">×</button><div class="pd-fc-list"></div>';
        const input = wrap.querySelector('input'), list = wrap.querySelector('.pd-fc-list'), clear = wrap.querySelector('.pd-fc-clear');
        let shown = [];
        const labelOf = v => (pd_fcData[cfg.key].find(o => String(o.value) === String(v)) || {}).label || '';
        const sync = () => { input.value = labelOf(pd_fcSel[cfg.param]); clear.hidden = !pd_fcSel[cfg.param]; };
        const render = () => {
            const q = input.dataset.typing === '1' ? input.value.trim().toLowerCase() : '';     // the shown label is not a search
            shown = pd_fcData[cfg.key].filter(o => !q || String(o.label).toLowerCase().includes(q));
            list.innerHTML = '<div class="pd-fc-opt" data-i="-1">' + pd_esc(wrap.dataset.all) + '</div>'
                + (shown.length ? shown.map((o, i) => '<div class="pd-fc-opt' + (String(o.value) === String(pd_fcSel[cfg.param]) ? ' active' : '') + '" data-i="' + i + '">' + pd_esc(o.label) + '</div>').join('')
                                : '<div class="pd-fc-empty">No match</div>');
        };
        const choose = v => {
            pd_fcSel[cfg.param] = v;
            input.dataset.typing = '0'; wrap.classList.remove('open'); sync();
            pd_page = 1; pd_load();
        };
        input.addEventListener('focus', () => { input.dataset.typing = '0'; input.select(); wrap.classList.add('open'); render(); });
        input.addEventListener('click', () => { if (!wrap.classList.contains('open')) { wrap.classList.add('open'); render(); } });
        input.addEventListener('input', () => { input.dataset.typing = '1'; wrap.classList.add('open'); render(); });
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape') { wrap.classList.remove('open'); sync(); input.blur(); }
            if (e.key === 'Enter') { e.preventDefault(); if (shown.length === 1 || (input.dataset.typing === '1' && shown[0])) choose(String(shown[0].value)); }
        });
        input.addEventListener('blur', () => { wrap.classList.remove('open'); input.dataset.typing = '0'; sync(); });   // half-typed text goes back to the chosen value
        list.addEventListener('mousedown', e => {
            const opt = e.target.closest('.pd-fc-opt');
            if (!opt) return;
            e.preventDefault();
            const i = Number(opt.dataset.i);
            choose(i < 0 ? '' : String(shown[i].value));
        });
        clear.addEventListener('mousedown', e => { e.preventDefault(); choose(''); });
        wrap._sync = sync;
        sync();
    });
}
async function pd_loadFilterOptions() {
    try {
        const res  = await fetch(`${PD_API}/ped-filter-options`, { credentials: 'same-origin', headers: pd_authHeaders() });
        const json = await res.json();
        if (json.success) {
            pd_fcData = { books: json.data.books || [], regions: json.data.regions || [] };
            Object.keys(PD_FC).forEach(id => pd_$(id)?._sync?.());
        }
    } catch (e) { /* the filters just stay empty */ }
}

/* the tabs: Land / Building and Community Facilities, labelled with the account title only; the first one opens */
async function pd_loadTabs() {
    try {
        const res  = await fetch(`${PD_API}/ped-categories`, { credentials: 'same-origin', headers: pd_authHeaders() });
        const json = await res.json();
        const tabs = json.success ? (json.data || []) : [];
        pd_$('pd-tabs').innerHTML = tabs.map(t => `<button type="button" class="nha-tab" data-code="${pd_esc(t.account_code)}" data-title="${pd_esc(t.account_title)}">${pd_esc(t.account_title)}</button>`).join('');
        pd_$('pd-tabs').querySelectorAll('.nha-tab').forEach(b => b.addEventListener('click', () => pd_selectTab(b.dataset.code)));
        const want = new URLSearchParams(window.location.search).get('tab');          // ?tab= (from the Work-In-Process page): open that tab
        if (tabs.length) pd_selectTab((tabs.find(t => t.account_code === want) || tabs[0]).account_code, true);
    } catch (e) { /* no tabs: the page then lists nothing */ }
}
function pd_selectTab(code, force) {
    if (!force && code === pd_tab) return;
    pd_tab = code;
    pd_$('pd-tabs').querySelectorAll('.nha-tab').forEach(b => {
        const on = b.dataset.code === code;
        b.classList.toggle('active', on);
        if (on) pd_tabTitle = b.dataset.title;
    });
    pd_page = 1;
    pd_load();
}

async function pd_load() {
    if (!pd_tab) return;                                     // the tabs load first (pd_loadTabs)
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ search: pd_$('pd-filter-search').value || '', page: pd_page, limit: PD_PAGE_SIZE, account: pd_tab });
        if (pd_fcSel.book_id) params.set('book_id', pd_fcSel.book_id);   // Book of Accounts filter
        if (pd_fcSel.rc_id)   params.set('rc_id', pd_fcSel.rc_id);       // Regional Office/District Office filter
        const res  = await fetch(`${PD_API}/property-distribution?${params}`, { credentials: 'same-origin', headers: pd_authHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        pd_render(json.data ?? []);
        if (pd_autoView) {                                   // open the View window of the project that was transferred (once)
            const k = pd_rows.findIndex(r => r.swa_number === pd_autoView);
            if (k >= 0) { pd_autoView = ''; pd_view(k); }
        }
        pd_pagination(json.pagination ?? {});
    } catch (e) {
        pd_render([], e.message);
        pd_pagination({});
    } finally {
        window.PagePreloader?.hide();
    }
}

function pd_render(rows, error) {
    const tbody = pd_$('pd-tbody');
    pd_rows = rows;
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">' + pd_esc(error || 'No ' + pd_tabTitle + ' found. A project shows here once it is transferred from Work-In-Process.') + '</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td>${pd_dash(r.book_name)}</td>
        <td>${pd_dash(r.requesting_office)}</td>
        <td>${pd_dash(r.project_code)}</td>
        <td>${pd_dash(r.project_name)}</td>
        <td class="pd-num">${pd_orDash(r.total_land_area)}</td>
        <td class="pd-num"><strong>${pd_money(r.total_amount)}</strong></td>
        <td class="pd-num">${pd_orDash(r.cost_per_unit)}</td>
        <td style="white-space:nowrap;">
            <div class="pd-menu">
                <button type="button" class="pd-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="pd_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="pd-menu-drop">
                    <button type="button" class="pd-menu-item" onclick="pd_closeMenus();pd_view(${i})">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </button>
                </div>
            </div>
        </td>
    </tr>`).join('');
}

function pd_pagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = PD_PAGE_SIZE } = p;
    pd_$('pd-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    pd_$('pd-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    pd_$('pd-total').textContent = total;
    const box = pd_$('pd-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="pd_goTo(${i})">${i}</button>`);
    }
    box.innerHTML = `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="pd_goTo(${current_page - 1})">‹</button>` + pages.join('')
        + `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="pd_goTo(${current_page + 1})">›</button>`;
}
function pd_goTo(p) { if (p < 1) return; pd_page = p; pd_load(); }

/* ── Actions menu ── */
function pd_toggleMenu(btn) {
    const drop = btn.closest('.pd-menu').querySelector('.pd-menu-drop');
    const isOpen = drop.classList.contains('open');
    pd_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function pd_closeMenus() {
    document.querySelectorAll('.pd-menu-drop.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.pd-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('.pd-menu')) pd_closeMenus(); });
window.addEventListener('scroll', pd_closeMenus, true);

/* ── View window ── */
function pd_view(i) {
    const r = pd_rows[i];
    if (!r) return;
    const kv = (k, v) => `<div><div class="pd-k">${k}</div><div class="pd-v">${v}</div></div>`;
    pd_$('pd-view-title').textContent = 'Property and Equipment for Distribution — ' + (r.project_name || r.swa_number || '');
    pd_$('pd-view-body').innerHTML = `
        <div class="pd-card"><h4>Project</h4><div class="pd-grid">
            ${kv('RFA No.', pd_dash(r.rfa_number))}${kv('SWA No.', pd_dash(r.swa_number))}
            ${kv('Book of Accounts', pd_dash(r.book_name))}${kv('Regional Office / District Office', pd_dash(r.requesting_office))}
            ${kv('Project Code', pd_dash(r.project_code))}${kv('Name of Project', pd_dash(r.project_name))}
            ${kv('Program', pd_dash(r.program_name))}${kv('Location', pd_dash(r.location))}
            ${kv('Contractor', pd_dash(r.contractor))}${kv('Contract', pd_dash(r.contract))}
            ${kv('TCT Number', pd_dash(r.tct_number))}
        </div></div>
        <div class="pd-card"><h4>Inventory</h4><div class="pd-grid">
            ${kv('Category', pd_dash(r.category))}${kv('Transfer JEV', pd_dash(r.transfer_jev_no))}
            ${kv('Area in SQM / Units', pd_orDash(r.total_land_area))}${kv('Total Amount (transferred)', '<strong>' + pd_money(r.total_amount) + '</strong>')}
            ${kv('Cost Per Sqm / Unit', pd_orDash(r.cost_per_unit))}${kv('Date Transferred', pd_dash(r.transfer_date))}
        </div></div>`;
    pd_$('pd-overlay').classList.add('open');
}
function pd_closeView() { pd_$('pd-overlay').classList.remove('open'); }

/* ── wiring ── */
(function () {
    const run = () => {
        pd_$('pd-refresh').addEventListener('click', () => pd_load());
        const search = pd_$('pd-filter-search'), clear = pd_$('pd-search-clear');
        search.addEventListener('input', () => {
            clear.hidden = !search.value;
            clearTimeout(pd_timer);
            pd_timer = setTimeout(() => { pd_page = 1; pd_load(); }, 300);
        });
        clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; pd_page = 1; pd_load(); });
        ['pd-view-close', 'pd-view-close2'].forEach(id => pd_$(id).addEventListener('click', pd_closeView));
        pd_$('pd-overlay').addEventListener('click', e => { if (e.target === pd_$('pd-overlay')) pd_closeView(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') pd_closeView(); });
        pd_fcBuild();
        pd_loadFilterOptions();
        const q0 = new URLSearchParams(window.location.search).get('q');               // ?q= (from the Work-In-Process page): the project that was transferred
        if (q0) { search.value = q0; clear.hidden = false; }
        pd_loadTabs();                                       // opens the first tab (or ?tab=), which loads the list
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
