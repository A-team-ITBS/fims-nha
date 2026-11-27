<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_mi_api = Config('API.FIMS_URL');
/*
 * fixed-assets-page.php — Fixed Assets (PHPMaker page FixedAssetsPage, view views/FixedAssetsPage.php).
 *
 * The infrastructure projects that were TRANSFERRED from Construction in Progress (Construction in Progress page → Actions → Transfer to Fixed Assets) to
 *   1-06-04-010 Buildings                                        1-06-09-020 Leased Asset Improvements          1-06-01-010 Land
 *   1-06-04-990-03 Other Structures - Properties Held for Rent Building      1-06-02-990-02 Other Land Improvements   (one tab each)
 * A transfer sets the record's transfer_line and moves its category to the new account; this page lists the records by that category.
 * API: GET /infra-iar/fixed-assets (routes/infra_iar.php → InfraIarService::fixedAssets()),
 *      GET /infra-iar/fa-categories (the tabs), GET /infra-iar/fa-filter-options (Book of Accounts / Regional Office filters).
 * Everything about the project (office, project code / name) is read live from the RFA; Total Amount is the amount that was transferred;
 * Cost Per Sqm / Unit = Total Amount / Area in SQM / Units.
 */
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
/* Actions dropdown (the same menu as the Work-In-Process page) */
.fxa-menu { position:relative;display:inline-block; }
.fxa-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
    border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Sans',sans-serif; }
.fxa-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA); }
.fxa-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.fxa-menu-drop { display:none;position:fixed;min-width:190px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
.fxa-menu-drop.open { display:block; }
.fxa-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;font-family:'IBM Plex Sans',sans-serif; }
.fxa-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.fxa-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
html.afims-dark[data-bs-theme="dark"] .fxa-menu-drop { background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);box-shadow:0 8px 24px rgba(0,0,0,.45); }
html.afims-dark[data-bs-theme="dark"] .fxa-menu-item { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] .fxa-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
html.afims-dark[data-bs-theme="dark"] .fxa-menu-trigger { color:#FFFFFF;background:#3861BC;border-color:#3861BC; }
#fxa-root .nha-tbl th { white-space:nowrap;line-height:1.3; }
#fxa-root .nha-tbl td { vertical-align:top; }
#fxa-root .nha-table-wrap .nha-tbl th { text-align:center !important; }   /* the list's column headers are centered (the cells keep their own alignment) */
#fxa-root .fxa-num { text-align:right;white-space:nowrap; }
/* Book / Region filter dropdowns (searchable; same look as the Work-In-Process page) */
.fxa-fc { position:relative; min-width:230px; }
.fxa-fc input { width:100%; box-sizing:border-box; padding-right:28px; }
.fxa-fc-clear { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; cursor:pointer; font-size:16px; line-height:1; color:var(--afims-text-secondary,#6b7280); padding:2px 4px; }
.fxa-fc-list { display:none; position:absolute; z-index:60; top:calc(100% + 4px); left:0; min-width:100%; width:max-content; max-width:min(420px, 90vw); max-height:260px; overflow:auto;
              background:var(--afims-surface,#fff); border:1px solid var(--afims-border,#d1d5db); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.fxa-fc.open .fxa-fc-list { display:block; }
.fxa-fc-opt { padding:8px 12px; font-size:13px; cursor:pointer; white-space:normal; word-break:break-word; }
.fxa-fc.fxa-fc-right .fxa-fc-list { left:auto; right:0; }   /* the last filter opens leftwards so its list stays on screen */
.fxa-fc-opt:hover { background:var(--afims-surface-subtle,#F4F7FA); }
.fxa-fc-opt.active { font-weight:600; color:var(--afims-primary,#1d4ed8); }
.fxa-fc-empty { padding:8px 12px; font-size:13px; color:var(--afims-text-secondary,#6b7280); }
/* Land is not depreciated: its tab hides the depreciation columns (useful life, salvage value, accumulated depreciation) */
#fxa-root.fxa-land .fxa-dep-col { display:none; }
/* View window */
.fxa-grid { display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:12px 24px; }
.fxa-k { font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted, #62657E); }
.fxa-v { font-size:13px;color:var(--afims-text, #1A1B21);margin-top:2px;word-break:break-word; }
.fxa-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:14px 16px;margin-bottom:14px; }
.fxa-card h4 { margin:0 0 10px;font-size:13px;font-weight:700;color:var(--afims-heading-fill, #000E3E); }
html.afims-dark[data-bs-theme="dark"] .fxa-card h4 { color:#FFFFFF; }
@media (max-width:760px) { .fxa-grid { grid-template-columns:minmax(0, 1fr); } }
</style>

<div id="fxa-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Fixed Assets</h1>
            <p class="app-page-subtitle">Infrastructure projects transferred from Construction in Progress to Fixed Assets, with their area and cost per unit.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="fxa-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── TABS: one per fixed-asset account (account titles only) ── -->
    <div class="nha-tabs no-print" id="fxa-tabs"></div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="fxa-filter-search" class="nha-filter-input" placeholder="Search project code, project name, location, contractor, book…">
                    <button class="nha-filter-clear-btn" id="fxa-search-clear" hidden type="button">×</button>
                </div>
            </div>
            <!-- searchable dropdowns (built by fxa_fcBuild(); the choices come from GET /infra-iar/fa-filter-options) -->
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book of Accounts</label>
                <div class="fxa-fc" id="fxa-fc-book" data-all="All books"></div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Regional Office/District Office</label>
                <div class="fxa-fc fxa-fc-right" id="fxa-fc-region" data-all="All offices"></div>
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
                        <th>DESCRIPTION</th>
                        <th>LOCATION</th>
                        <th>CONTRACTOR</th>
                        <th>JEV <br> REFERENCE</th>
                        <th>DATE ACQUIRED / <br> DONATED</th>
                        <th style="text-align:right;">ACQUISITION <br> COST</th>
                        <th style="text-align:right;">TOTAL BOOK <br> BALANCE</th>
                        <th class="fxa-dep-col" style="text-align:right;">ESTIMATED USEFUL <br> LIFE (IN YEARS)</th>
                        <th class="fxa-dep-col" style="text-align:right;">SALVAGE / <br> RESIDUAL VALUE</th>
                        <th class="fxa-dep-col" style="text-align:right;">ACCUM. <br> DEPRECIATION</th>
                        <th style="text-align:right;">BOOK <br> VALUE</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="fxa-tbody">
                    <tr><td colspan="13"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="fxa-from">0</span>–<span id="fxa-to">0</span></strong> of <strong><span id="fxa-total">0</span></strong></span>
            <div id="fxa-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ── VIEW WINDOW ────────────────────────────────────── -->
    <div class="nha-overlay" id="fxa-overlay">
        <div class="nha-modal u-modal-lg">
            <div class="nha-modal-hdr">
                <h3 id="fxa-view-title">Fixed Assets</h3>
                <button type="button" class="nha-modal-close" id="fxa-view-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="fxa-view-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="fxa-view-close2">Close</button>
            </div>
        </div>
    </div>

</div><!-- /#fxa-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   FIXED ASSETS   prefix: fxa_   (GET /infra-iar/fixed-assets)
═══════════════════════════════════════════════════════════════ */
const FXA_API = '<?php echo $_mi_api; ?>' + '/infra-iar';
const FXA_PAGE_SIZE = 10;
let fxa_page = 1, fxa_rows = [], fxa_timer = null;
let fxa_tab = '', fxa_tabTitle = '';        // the active tab: a fixed-asset account (1-06-04-010 …) and its title
let fxa_autoView = new URLSearchParams(window.location.search).get('open') || '';   // ?open= (from the Construction in Progress page; not ?view=, which the framework itself reads as a template name): open this project's View window once the list is loaded

const fxa_$ = id => document.getElementById(id);
function fxa_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}
function fxa_esc(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
const fxa_dash  = v => (v === null || v === undefined || v === '') ? '—' : fxa_esc(v);
const fxa_money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fxa_orDash = v => (v === null || v === undefined || v === '') ? '—' : fxa_money(v);
/* Land is not depreciated: its useful life, salvage value and accumulated depreciation show a dash */
const fxa_isLand = r => r.useful_life_years === null || r.useful_life_years === undefined;
const FXA_LAND = '1-06-01-010';                                  // the Land account: not depreciated
const fxa_cols = () => fxa_tab === FXA_LAND ? 10 : 13;           // columns shown (the table's colspan)
const fxa_life = r => fxa_isLand(r) ? '—' : fxa_esc(String(r.useful_life_years));
const fxa_dep  = (r, v) => fxa_isLand(r) ? '—' : fxa_money(v);

/* ── Filters: Book of Accounts and Regional Office/District Office — searchable dropdowns ── */
const FXA_FC = { 'fxa-fc-book': { param: 'book_id', key: 'books' }, 'fxa-fc-region': { param: 'rc_id', key: 'regions' } };
const fxa_fcSel  = { book_id: '', rc_id: '' };                // the chosen value of each filter ('' = all)
let   fxa_fcData = { books: [], regions: [] };                // [{value, label}] from GET /infra-iar/fa-filter-options
function fxa_fcBuild() {
    Object.entries(FXA_FC).forEach(([id, cfg]) => {
        const wrap = fxa_$(id);
        if (!wrap) return;
        wrap.innerHTML = '<input type="text" class="nha-filter-input" autocomplete="off" placeholder="' + fxa_esc(wrap.dataset.all) + '">'
            + '<button type="button" class="fxa-fc-clear" hidden title="Clear">×</button><div class="fxa-fc-list"></div>';
        const input = wrap.querySelector('input'), list = wrap.querySelector('.fxa-fc-list'), clear = wrap.querySelector('.fxa-fc-clear');
        let shown = [];
        const labelOf = v => (fxa_fcData[cfg.key].find(o => String(o.value) === String(v)) || {}).label || '';
        const sync = () => { input.value = labelOf(fxa_fcSel[cfg.param]); clear.hidden = !fxa_fcSel[cfg.param]; };
        const render = () => {
            const q = input.dataset.typing === '1' ? input.value.trim().toLowerCase() : '';     // the shown label is not a search
            shown = fxa_fcData[cfg.key].filter(o => !q || String(o.label).toLowerCase().includes(q));
            list.innerHTML = '<div class="fxa-fc-opt" data-i="-1">' + fxa_esc(wrap.dataset.all) + '</div>'
                + (shown.length ? shown.map((o, i) => '<div class="fxa-fc-opt' + (String(o.value) === String(fxa_fcSel[cfg.param]) ? ' active' : '') + '" data-i="' + i + '">' + fxa_esc(o.label) + '</div>').join('')
                                : '<div class="fxa-fc-empty">No match</div>');
        };
        const choose = v => {
            fxa_fcSel[cfg.param] = v;
            input.dataset.typing = '0'; wrap.classList.remove('open'); sync();
            fxa_page = 1; fxa_load();
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
            const opt = e.target.closest('.fxa-fc-opt');
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
async function fxa_loadFilterOptions() {
    try {
        const res  = await fetch(`${FXA_API}/fa-filter-options`, { credentials: 'same-origin', headers: fxa_authHeaders() });
        const json = await res.json();
        if (json.success) {
            fxa_fcData = { books: json.data.books || [], regions: json.data.regions || [] };
            Object.keys(FXA_FC).forEach(id => fxa_$(id)?._sync?.());
        }
    } catch (e) { /* the filters just stay empty */ }
}

/* the tabs: one per fixed-asset account, labelled with the account title only; the first one opens */
async function fxa_loadTabs() {
    try {
        const res  = await fetch(`${FXA_API}/fa-categories`, { credentials: 'same-origin', headers: fxa_authHeaders() });
        const json = await res.json();
        const tabs = json.success ? (json.data || []) : [];
        fxa_$('fxa-tabs').innerHTML = tabs.map(t => `<button type="button" class="nha-tab" data-code="${fxa_esc(t.account_code)}" data-title="${fxa_esc(t.account_title)}">${fxa_esc(t.account_title)}</button>`).join('');
        fxa_$('fxa-tabs').querySelectorAll('.nha-tab').forEach(b => b.addEventListener('click', () => fxa_selectTab(b.dataset.code)));
        const want = new URLSearchParams(window.location.search).get('tab');          // ?tab= (from the Construction in Progress page): open that tab
        if (tabs.length) fxa_selectTab((tabs.find(t => t.account_code === want) || tabs[0]).account_code, true);
    } catch (e) { /* no tabs: the page then lists nothing */ }
}
function fxa_selectTab(code, force) {
    if (!force && code === fxa_tab) return;
    fxa_tab = code;
    fxa_$('fxa-tabs').querySelectorAll('.nha-tab').forEach(b => {
        const on = b.dataset.code === code;
        b.classList.toggle('active', on);
        if (on) fxa_tabTitle = b.dataset.title;
    });
    fxa_$('fxa-root').classList.toggle('fxa-land', code === FXA_LAND);   // Land is not depreciated: its tab hides the depreciation columns
    fxa_page = 1;
    fxa_load();
}

async function fxa_load() {
    if (!fxa_tab) return;                                     // the tabs load first (fxa_loadTabs)
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ search: fxa_$('fxa-filter-search').value || '', page: fxa_page, limit: FXA_PAGE_SIZE, account: fxa_tab });
        if (fxa_fcSel.book_id) params.set('book_id', fxa_fcSel.book_id);   // Book of Accounts filter
        if (fxa_fcSel.rc_id)   params.set('rc_id', fxa_fcSel.rc_id);       // Regional Office/District Office filter
        const res  = await fetch(`${FXA_API}/fixed-assets?${params}`, { credentials: 'same-origin', headers: fxa_authHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        fxa_render(json.data ?? []);
        if (fxa_autoView) {                                   // open the View window of the project that was transferred (once)
            const k = fxa_rows.findIndex(r => r.swa_number === fxa_autoView);
            if (k >= 0) { fxa_autoView = ''; fxa_view(k); }
        }
        fxa_pagination(json.pagination ?? {});
    } catch (e) {
        fxa_render([], e.message);
        fxa_pagination({});
    } finally {
        window.PagePreloader?.hide();
    }
}

function fxa_render(rows, error) {
    const tbody = fxa_$('fxa-tbody');
    fxa_rows = rows;
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="' + fxa_cols() + '"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">' + fxa_esc(error || 'No ' + fxa_tabTitle + ' found. A project shows here once it is transferred from Construction in Progress.') + '</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td>${fxa_dash(r.requesting_office)}</td>
        <td>${fxa_dash(r.project_name)}</td>
        <td>${fxa_dash(r.location)}</td>
        <td>${fxa_dash(r.contractor)}</td>
        <td>${fxa_dash(r.transfer_jev_no)}</td>
        <td style="white-space:nowrap;">${fxa_dash(r.date_acquired)}</td>
        <td class="fxa-num"><strong>${fxa_money(r.acquisition_cost)}</strong></td>
        <td class="fxa-num">${fxa_money(r.total_book_balance)}</td>
        <td class="fxa-num fxa-dep-col">${fxa_life(r)}</td>
        <td class="fxa-num fxa-dep-col">${fxa_dep(r, r.salvage_value)}</td>
        <td class="fxa-num fxa-dep-col">${fxa_dep(r, r.accum_depreciation)}</td>
        <td class="fxa-num"><strong>${fxa_money(r.book_value)}</strong></td>
        <td style="white-space:nowrap;">
            <div class="fxa-menu">
                <button type="button" class="fxa-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="fxa_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="fxa-menu-drop">
                    <button type="button" class="fxa-menu-item" onclick="fxa_closeMenus();fxa_view(${i})">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </button>
                </div>
            </div>
        </td>
    </tr>`).join('');
}

function fxa_pagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = FXA_PAGE_SIZE } = p;
    fxa_$('fxa-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    fxa_$('fxa-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    fxa_$('fxa-total').textContent = total;
    const box = fxa_$('fxa-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="fxa_goTo(${i})">${i}</button>`);
    }
    box.innerHTML = `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="fxa_goTo(${current_page - 1})">‹</button>` + pages.join('')
        + `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="fxa_goTo(${current_page + 1})">›</button>`;
}
function fxa_goTo(p) { if (p < 1) return; fxa_page = p; fxa_load(); }

/* ── Actions menu ── */
function fxa_toggleMenu(btn) {
    const drop = btn.closest('.fxa-menu').querySelector('.fxa-menu-drop');
    const isOpen = drop.classList.contains('open');
    fxa_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function fxa_closeMenus() {
    document.querySelectorAll('.fxa-menu-drop.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.fxa-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('.fxa-menu')) fxa_closeMenus(); });
window.addEventListener('scroll', fxa_closeMenus, true);

/* ── View window ── */
function fxa_view(i) {
    const r = fxa_rows[i];
    if (!r) return;
    const kv = (k, v) => `<div><div class="fxa-k">${k}</div><div class="fxa-v">${v}</div></div>`;
    fxa_$('fxa-view-title').textContent = 'Fixed Assets — ' + (r.project_name || r.swa_number || '');
    fxa_$('fxa-view-body').innerHTML = `
        <div class="fxa-card"><h4>Project</h4><div class="fxa-grid">
            ${kv('RFA No.', fxa_dash(r.rfa_number))}${kv('SWA No.', fxa_dash(r.swa_number))}
            ${kv('Book of Accounts', fxa_dash(r.book_name))}${kv('Regional Office / District Office', fxa_dash(r.requesting_office))}
            ${kv('Project Code', fxa_dash(r.project_code))}${kv('Name of Project', fxa_dash(r.project_name))}
            ${kv('Program', fxa_dash(r.program_name))}${kv('Location', fxa_dash(r.location))}
            ${kv('Contractor', fxa_dash(r.contractor))}${kv('Contract', fxa_dash(r.contract))}
            ${kv('TCT Number', fxa_dash(r.tct_number))}
        </div></div>
        <div class="fxa-card"><h4>Fixed Asset</h4><div class="fxa-grid">
            ${kv('Category', fxa_dash(r.category))}${kv('Transfer JEV', fxa_dash(r.transfer_jev_no))}
            ${kv('Area in SQM / Units', fxa_orDash(r.total_land_area))}${kv('Total Amount (transferred)', '<strong>' + fxa_money(r.total_amount) + '</strong>')}
            ${kv('Cost Per Sqm / Unit', fxa_orDash(r.cost_per_unit))}${kv('Date Transferred', fxa_dash(r.transfer_date))}
        </div></div>
        ${fxa_isLand(r) ? '' : `<div class="fxa-card"><h4>Depreciation</h4><div class="fxa-grid">
            ${kv('Date Acquired / Donated (Completion Date)', fxa_dash(r.date_acquired))}${kv('Acquisition Cost', fxa_money(r.acquisition_cost))}
            ${kv('Total Book Balance', fxa_money(r.total_book_balance))}${kv('Estimated Useful Life (in Years)', fxa_life(r))}
            ${kv('Salvage / Residual Value', fxa_dep(r, r.salvage_value))}${kv('Monthly Depreciation (Cost × .9 ÷ ' + (fxa_isLand(r) ? '—' : r.useful_life_years * 12) + ' months)', fxa_dep(r, r.monthly_depreciation))}
            ${kv('Months Used', fxa_isLand(r) ? '—' : fxa_esc(String(r.months_used)))}${kv('Accumulated Depreciation (Monthly × Months Used)', fxa_dep(r, r.accum_depreciation))}
            ${kv('Book Value (Acquisition Cost − Accumulated Depreciation)', '<strong>' + fxa_money(r.book_value) + '</strong>')}
        </div></div>`}`;
    fxa_$('fxa-overlay').classList.add('open');
}
function fxa_closeView() { fxa_$('fxa-overlay').classList.remove('open'); }

/* ── wiring ── */
(function () {
    const run = () => {
        fxa_$('fxa-refresh').addEventListener('click', () => fxa_load());
        const search = fxa_$('fxa-filter-search'), clear = fxa_$('fxa-search-clear');
        search.addEventListener('input', () => {
            clear.hidden = !search.value;
            clearTimeout(fxa_timer);
            fxa_timer = setTimeout(() => { fxa_page = 1; fxa_load(); }, 300);
        });
        clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; fxa_page = 1; fxa_load(); });
        ['fxa-view-close', 'fxa-view-close2'].forEach(id => fxa_$(id).addEventListener('click', fxa_closeView));
        fxa_$('fxa-overlay').addEventListener('click', e => { if (e.target === fxa_$('fxa-overlay')) fxa_closeView(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') fxa_closeView(); });
        fxa_fcBuild();
        fxa_loadFilterOptions();
        const q0 = new URLSearchParams(window.location.search).get('q');               // ?q= (from the Construction in Progress page): the project that was transferred
        if (q0) { search.value = q0; clear.hidden = false; }
        fxa_loadTabs();                                       // opens the first tab (or ?tab=), which loads the list
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
