<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_mi_api = Config('API.FIMS_URL');
/*
 * investment-property-page.php — Investment Property (PHPMaker page InvestmentPropertyPage, view views/InvestmentPropertyPage.php).
 *
 * The infrastructure projects TRANSFERRED from Construction in Progress (Transfer to Fixed Assets) to
 *   1-05-01-010 Investment Property - Land      1-05-01-020 Investment Property - Buildings      (one tab each)
 * Same columns / depreciation as the Fixed Assets page (Land is not depreciated; Buildings: cost x .9 / 360 months). A project shows here only once its
 * transfer JEV is APPROVED. API: GET /infra-iar/investment-property, /ip-categories (tabs), /ip-filter-options (Book of Accounts / Regional Office filters).
 */
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
/* Actions dropdown (the same menu as the Work-In-Process page) */
.ipr-menu { position:relative;display:inline-block; }
.ipr-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
    border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Sans',sans-serif; }
.ipr-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA); }
.ipr-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.ipr-menu-drop { display:none;position:fixed;min-width:190px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
.ipr-menu-drop.open { display:block; }
.ipr-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;font-family:'IBM Plex Sans',sans-serif; }
.ipr-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.ipr-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
html.afims-dark[data-bs-theme="dark"] .ipr-menu-drop { background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);box-shadow:0 8px 24px rgba(0,0,0,.45); }
html.afims-dark[data-bs-theme="dark"] .ipr-menu-item { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] .ipr-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
html.afims-dark[data-bs-theme="dark"] .ipr-menu-trigger { color:#FFFFFF;background:#3861BC;border-color:#3861BC; }
#ipr-root .nha-tbl th { white-space:nowrap;line-height:1.3; }
#ipr-root .nha-tbl td { vertical-align:top; }
#ipr-root .nha-table-wrap .nha-tbl th { text-align:center !important; }   /* the list's column headers are centered (the cells keep their own alignment) */
#ipr-root .ipr-num { text-align:right;white-space:nowrap; }
/* Book / Region filter dropdowns (searchable; same look as the Work-In-Process page) */
.ipr-fc { position:relative; min-width:230px; }
.ipr-fc input { width:100%; box-sizing:border-box; padding-right:28px; }
.ipr-fc-clear { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; cursor:pointer; font-size:16px; line-height:1; color:var(--afims-text-secondary,#6b7280); padding:2px 4px; }
.ipr-fc-list { display:none; position:absolute; z-index:60; top:calc(100% + 4px); left:0; min-width:100%; width:max-content; max-width:min(420px, 90vw); max-height:260px; overflow:auto;
              background:var(--afims-surface,#fff); border:1px solid var(--afims-border,#d1d5db); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.ipr-fc.open .ipr-fc-list { display:block; }
.ipr-fc-opt { padding:8px 12px; font-size:13px; cursor:pointer; white-space:normal; word-break:break-word; }
.ipr-fc.ipr-fc-right .ipr-fc-list { left:auto; right:0; }   /* the last filter opens leftwards so its list stays on screen */
.ipr-fc-opt:hover { background:var(--afims-surface-subtle,#F4F7FA); }
.ipr-fc-opt.active { font-weight:600; color:var(--afims-primary,#1d4ed8); }
.ipr-fc-empty { padding:8px 12px; font-size:13px; color:var(--afims-text-secondary,#6b7280); }
/* Land is not depreciated: its tab hides the depreciation columns (useful life, salvage value, accumulated depreciation) */
#ipr-root.ipr-land .ipr-dep-col { display:none; }
/* View window */
.ipr-grid { display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:12px 24px; }
.ipr-k { font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted, #62657E); }
.ipr-v { font-size:13px;color:var(--afims-text, #1A1B21);margin-top:2px;word-break:break-word; }
.ipr-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:14px 16px;margin-bottom:14px; }
.ipr-card h4 { margin:0 0 10px;font-size:13px;font-weight:700;color:var(--afims-heading-fill, #000E3E); }
html.afims-dark[data-bs-theme="dark"] .ipr-card h4 { color:#FFFFFF; }
@media (max-width:760px) { .ipr-grid { grid-template-columns:minmax(0, 1fr); } }
</style>

<div id="ipr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Investment Property</h1>
            <p class="app-page-subtitle">Infrastructure projects transferred from Construction in Progress to Investment Property (Land / Buildings), with their area and cost per unit.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="ipr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── TABS: one per investment-property account (account titles only) ── -->
    <div class="nha-tabs no-print" id="ipr-tabs"></div>


    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="ipr-filter-search" class="nha-filter-input" placeholder="Search project code, project name, location, contractor, book…">
                    <button class="nha-filter-clear-btn" id="ipr-search-clear" hidden type="button">×</button>
                </div>
            </div>
            <!-- searchable dropdowns (built by ipr_fcBuild(); the choices come from GET /infra-iar/ip-filter-options) -->
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book of Accounts</label>
                <div class="ipr-fc" id="ipr-fc-book" data-all="All books"></div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Regional Office/District Office</label>
                <div class="ipr-fc ipr-fc-right" id="ipr-fc-region" data-all="All offices"></div>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>RESPONSIBILITY <br> CENTER</th>
                        <th>PROJECT</th>
                        <th>DATE</th>
                        <th>LOCATION</th>
                        <th style="text-align:right;">AREA</th>
                        <th>TCT NO.</th>
                        <th style="text-align:right;">COST</th>
                        <th style="text-align:right;">TOTAL BOOK <br> BALANCE</th>
                        <th class="ipr-dep-col" style="text-align:right;">ESTIMATED USEFUL <br> LIFE (IN YEARS)</th>
                        <th class="ipr-dep-col" style="text-align:right;">SALVAGE / <br> RESIDUAL VALUE</th>
                        <th class="ipr-dep-col" style="text-align:right;">ACCUM. <br> DEPRECIATION</th>
                        <th style="text-align:right;">BOOK <br> VALUE</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ipr-tbody">
                    <tr><td colspan="13"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="ipr-from">0</span>–<span id="ipr-to">0</span></strong> of <strong><span id="ipr-total">0</span></strong></span>
            <div id="ipr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ── VIEW WINDOW ────────────────────────────────────── -->
    <div class="nha-overlay" id="ipr-overlay">
        <div class="nha-modal u-modal-lg">
            <div class="nha-modal-hdr">
                <h3 id="ipr-view-title">Investment Property</h3>
                <button type="button" class="nha-modal-close" id="ipr-view-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="ipr-view-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="ipr-view-close2">Close</button>
            </div>
        </div>
    </div>

</div><!-- /#ipr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   INVESTMENT PROPERTY   prefix: ipr_   (GET /infra-iar/investment-property)
═══════════════════════════════════════════════════════════════ */
const IPR_API = '<?php echo $_mi_api; ?>' + '/infra-iar';
const IPR_PAGE_SIZE = 10;
let ipr_page = 1, ipr_rows = [], ipr_timer = null;
let ipr_tab = '', ipr_tabTitle = '';        // the active tab: an investment-property account (1-05-01-010 / -020) and its title
let ipr_autoView = new URLSearchParams(window.location.search).get('open') || '';   // ?open= (from the Construction in Progress page; not ?view=, which the framework itself reads as a template name): open this project's View window once the list is loaded

const ipr_$ = id => document.getElementById(id);
function ipr_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}
function ipr_esc(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
const ipr_dash  = v => (v === null || v === undefined || v === '') ? '—' : ipr_esc(v);
const ipr_money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const ipr_orDash = v => (v === null || v === undefined || v === '') ? '—' : ipr_money(v);
/* Land is not depreciated: its useful life, salvage value and accumulated depreciation show a dash */
const ipr_isLand = r => r.useful_life_years === null || r.useful_life_years === undefined;
const IPR_LAND = '1-05-01-010';
const ipr_life = r => ipr_isLand(r) ? '—' : ipr_esc(String(r.useful_life_years));
const ipr_dep  = (r, v) => ipr_isLand(r) ? '—' : ipr_money(v);
const ipr_area = v => (v === null || v === undefined || v === '') ? '—' : ipr_esc(Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 }) + ' sq.m.');   // e.g. 160,431 sq.m.
const ipr_cols = () => ipr_tab === IPR_LAND ? 10 : 13;   // Land hides the 3 depreciation columns           // columns shown (the table's colspan)

/* ── Filters: Book of Accounts and Regional Office/District Office — searchable dropdowns ── */
const IPR_FC = { 'ipr-fc-book': { param: 'book_id', key: 'books' }, 'ipr-fc-region': { param: 'rc_id', key: 'regions' } };
const ipr_fcSel  = { book_id: '', rc_id: '' };                // the chosen value of each filter ('' = all)
let   ipr_fcData = { books: [], regions: [] };                // [{value, label}] from GET /infra-iar/ip-filter-options
function ipr_fcBuild() {
    Object.entries(IPR_FC).forEach(([id, cfg]) => {
        const wrap = ipr_$(id);
        if (!wrap) return;
        wrap.innerHTML = '<input type="text" class="nha-filter-input" autocomplete="off" placeholder="' + ipr_esc(wrap.dataset.all) + '">'
            + '<button type="button" class="ipr-fc-clear" hidden title="Clear">×</button><div class="ipr-fc-list"></div>';
        const input = wrap.querySelector('input'), list = wrap.querySelector('.ipr-fc-list'), clear = wrap.querySelector('.ipr-fc-clear');
        let shown = [];
        const labelOf = v => (ipr_fcData[cfg.key].find(o => String(o.value) === String(v)) || {}).label || '';
        const sync = () => { input.value = labelOf(ipr_fcSel[cfg.param]); clear.hidden = !ipr_fcSel[cfg.param]; };
        const render = () => {
            const q = input.dataset.typing === '1' ? input.value.trim().toLowerCase() : '';     // the shown label is not a search
            shown = ipr_fcData[cfg.key].filter(o => !q || String(o.label).toLowerCase().includes(q));
            list.innerHTML = '<div class="ipr-fc-opt" data-i="-1">' + ipr_esc(wrap.dataset.all) + '</div>'
                + (shown.length ? shown.map((o, i) => '<div class="ipr-fc-opt' + (String(o.value) === String(ipr_fcSel[cfg.param]) ? ' active' : '') + '" data-i="' + i + '">' + ipr_esc(o.label) + '</div>').join('')
                                : '<div class="ipr-fc-empty">No match</div>');
        };
        const choose = v => {
            ipr_fcSel[cfg.param] = v;
            input.dataset.typing = '0'; wrap.classList.remove('open'); sync();
            ipr_page = 1; ipr_load();
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
            const opt = e.target.closest('.ipr-fc-opt');
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
async function ipr_loadFilterOptions() {
    try {
        const res  = await fetch(`${IPR_API}/ip-filter-options`, { credentials: 'same-origin', headers: ipr_authHeaders() });
        const json = await res.json();
        if (json.success) {
            ipr_fcData = { books: json.data.books || [], regions: json.data.regions || [] };
            Object.keys(IPR_FC).forEach(id => ipr_$(id)?._sync?.());
        }
    } catch (e) { /* the filters just stay empty */ }
}

/* the tabs: one per investment-property account, labelled with the account title only; the first one opens */
async function ipr_loadTabs() {
    try {
        const res  = await fetch(`${IPR_API}/ip-categories`, { credentials: 'same-origin', headers: ipr_authHeaders() });
        const json = await res.json();
        const tabs = json.success ? (json.data || []) : [];
        ipr_$('ipr-tabs').innerHTML = tabs.map(t => `<button type="button" class="nha-tab" data-code="${ipr_esc(t.account_code)}" data-title="${ipr_esc(t.account_title)}">${ipr_esc(t.account_title)}</button>`).join('');
        ipr_$('ipr-tabs').querySelectorAll('.nha-tab').forEach(b => b.addEventListener('click', () => ipr_selectTab(b.dataset.code)));
        const want = new URLSearchParams(window.location.search).get('tab');          // ?tab= (from the Construction in Progress page): open that tab
        if (tabs.length) ipr_selectTab((tabs.find(t => t.account_code === want) || tabs[0]).account_code, true);
    } catch (e) { /* no tabs: the page then lists nothing */ }
}
function ipr_selectTab(code, force) {
    if (!force && code === ipr_tab) return;
    ipr_tab = code;
    ipr_$('ipr-tabs').querySelectorAll('.nha-tab').forEach(b => {
        const on = b.dataset.code === code;
        b.classList.toggle('active', on);
        if (on) ipr_tabTitle = b.dataset.title;
    });
    ipr_$('ipr-root').classList.toggle('ipr-land', code === IPR_LAND);   // Land is not depreciated: its tab hides the depreciation columns
    ipr_page = 1;
    ipr_load();
}

async function ipr_load() {
    if (!ipr_tab) return;                                     // the tabs load first (ipr_loadTabs)
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ search: ipr_$('ipr-filter-search').value || '', page: ipr_page, limit: IPR_PAGE_SIZE, account: ipr_tab });
        if (ipr_fcSel.book_id) params.set('book_id', ipr_fcSel.book_id);   // Book of Accounts filter
        if (ipr_fcSel.rc_id)   params.set('rc_id', ipr_fcSel.rc_id);       // Regional Office/District Office filter
        const res  = await fetch(`${IPR_API}/investment-property?${params}`, { credentials: 'same-origin', headers: ipr_authHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        ipr_render(json.data ?? []);
        if (ipr_autoView) {                                   // open the View window of the project that was transferred (once)
            const k = ipr_rows.findIndex(r => r.swa_number === ipr_autoView);
            if (k >= 0) { ipr_autoView = ''; ipr_view(k); }
        }
        ipr_pagination(json.pagination ?? {});
    } catch (e) {
        ipr_render([], e.message);
        ipr_pagination({});
    } finally {
        window.PagePreloader?.hide();
    }
}

function ipr_render(rows, error) {
    const tbody = ipr_$('ipr-tbody');
    ipr_rows = rows;
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="' + ipr_cols() + '"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">' + ipr_esc(error || 'No ' + ipr_tabTitle + ' found. A project shows here once it is transferred from Construction in Progress.') + '</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td>${ipr_dash(r.requesting_office)}</td>
        <td>${ipr_dash(r.project_name)}</td>
        <td style="white-space:nowrap;">${ipr_dash(r.completion_date_revised)}</td>
        <td>${ipr_dash(r.location)}</td>
        <td class="ipr-num">${ipr_area(r.total_land_area)}</td>
        <td>${ipr_dash(r.tct_number)}</td>
        <td class="ipr-num"><strong>${ipr_money(r.total_amount)}</strong></td>
        <td class="ipr-num">${ipr_money(r.total_book_balance)}</td>
        <td class="ipr-num ipr-dep-col">${ipr_life(r)}</td>
        <td class="ipr-num ipr-dep-col">${ipr_dep(r, r.salvage_value)}</td>
        <td class="ipr-num ipr-dep-col">${ipr_dep(r, r.accum_depreciation)}</td>
        <td class="ipr-num"><strong>${ipr_money(r.book_value)}</strong></td>
        <td style="white-space:nowrap;">
            <div class="ipr-menu">
                <button type="button" class="ipr-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="ipr_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="ipr-menu-drop">
                    <button type="button" class="ipr-menu-item" onclick="ipr_closeMenus();ipr_view(${i})">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </button>
                </div>
            </div>
        </td>
    </tr>`).join('');
}

function ipr_pagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = IPR_PAGE_SIZE } = p;
    ipr_$('ipr-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    ipr_$('ipr-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    ipr_$('ipr-total').textContent = total;
    const box = ipr_$('ipr-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="ipr_goTo(${i})">${i}</button>`);
    }
    box.innerHTML = `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="ipr_goTo(${current_page - 1})">‹</button>` + pages.join('')
        + `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="ipr_goTo(${current_page + 1})">›</button>`;
}
function ipr_goTo(p) { if (p < 1) return; ipr_page = p; ipr_load(); }

/* ── Actions menu ── */
function ipr_toggleMenu(btn) {
    const drop = btn.closest('.ipr-menu').querySelector('.ipr-menu-drop');
    const isOpen = drop.classList.contains('open');
    ipr_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function ipr_closeMenus() {
    document.querySelectorAll('.ipr-menu-drop.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.ipr-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('.ipr-menu')) ipr_closeMenus(); });
window.addEventListener('scroll', ipr_closeMenus, true);

/* ── View window ── */
function ipr_view(i) {
    const r = ipr_rows[i];
    if (!r) return;
    const kv = (k, v) => `<div><div class="ipr-k">${k}</div><div class="ipr-v">${v}</div></div>`;
    ipr_$('ipr-view-title').textContent = 'Investment Property — ' + (r.project_name || r.swa_number || '');
    ipr_$('ipr-view-body').innerHTML = `
        <div class="ipr-card"><h4>Project</h4><div class="ipr-grid">
            ${kv('RFA No.', ipr_dash(r.rfa_number))}${kv('SWA No.', ipr_dash(r.swa_number))}
            ${kv('Book of Accounts', ipr_dash(r.book_name))}${kv('Regional Office / District Office', ipr_dash(r.requesting_office))}
            ${kv('Project Code', ipr_dash(r.project_code))}${kv('Name of Project', ipr_dash(r.project_name))}
            ${kv('Program', ipr_dash(r.program_name))}${kv('Location', ipr_dash(r.location))}
            ${kv('Contractor', ipr_dash(r.contractor))}${kv('Contract', ipr_dash(r.contract))}
            ${kv('TCT Number', ipr_dash(r.tct_number))}
        </div></div>
        <div class="ipr-card"><h4>Investment Property</h4><div class="ipr-grid">
            ${kv('Category', ipr_dash(r.category))}${kv('Transfer JEV', ipr_dash(r.transfer_jev_no))}
            ${kv('Area in SQM / Units', (v => v === null || v === undefined || v === '' ? '—' : Number(v).toLocaleString('en-PH', { maximumFractionDigits: 2 }) + ' sq.m.')(r.total_land_area))}${kv('Total Amount (transferred)', '<strong>' + ipr_money(r.total_amount) + '</strong>')}
            ${kv('Cost Per Sqm / Unit', ipr_orDash(r.cost_per_unit))}${kv('Date Transferred', ipr_dash(r.transfer_date))}
        </div></div>
        ${ipr_isLand(r) ? '' : `<div class="ipr-card"><h4>Depreciation</h4><div class="ipr-grid">
            ${kv('Date Acquired (Completion Date)', ipr_dash(r.date_acquired))}${kv('Acquisition Cost', ipr_money(r.acquisition_cost))}
            ${kv('Total Book Balance', ipr_money(r.total_book_balance))}${kv('Estimated Useful Life (in Years)', ipr_life(r))}
            ${kv('Salvage / Residual Value', ipr_dep(r, r.salvage_value))}${kv('Monthly Depreciation (Cost × .9 ÷ ' + (r.useful_life_years * 12) + ' months)', ipr_dep(r, r.monthly_depreciation))}
            ${kv('Months Used', ipr_esc(String(r.months_used)))}${kv('Accumulated Depreciation (Monthly × Months Used)', ipr_dep(r, r.accum_depreciation))}
            ${kv('Book Value (Acquisition Cost − Accumulated Depreciation)', '<strong>' + ipr_money(r.book_value) + '</strong>')}
        </div></div>`}
        `;
    ipr_$('ipr-overlay').classList.add('open');
}
function ipr_closeView() { ipr_$('ipr-overlay').classList.remove('open'); }

/* ── wiring ── */
(function () {
    const run = () => {
        ipr_$('ipr-refresh').addEventListener('click', () => { ipr_load(); });
        const search = ipr_$('ipr-filter-search'), clear = ipr_$('ipr-search-clear');
        search.addEventListener('input', () => {
            clear.hidden = !search.value;
            clearTimeout(ipr_timer);
            ipr_timer = setTimeout(() => { ipr_page = 1; ipr_load(); }, 300);
        });
        clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; ipr_page = 1; ipr_load(); });
        ['ipr-view-close', 'ipr-view-close2'].forEach(id => ipr_$(id).addEventListener('click', ipr_closeView));
        ipr_$('ipr-overlay').addEventListener('click', e => { if (e.target === ipr_$('ipr-overlay')) ipr_closeView(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') ipr_closeView(); });
        ipr_fcBuild();
        ipr_loadFilterOptions();
        const q0 = new URLSearchParams(window.location.search).get('q');               // ?q= (from the Construction in Progress page): the project that was transferred
        if (q0) { search.value = q0; clear.hidden = false; }
        ipr_loadTabs();                                       // opens the first tab (or ?tab=), which loads the list
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
