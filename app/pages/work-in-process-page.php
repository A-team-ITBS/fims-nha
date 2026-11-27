<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_wip_api = Config('API.FIMS_URL');
/*
 * work-in-process-page.php — Work-In-Process Inventory (PHPMaker page WorkInProcessPage, view views/WorkInProcessPage.php).
 *
 * One row per infrastructure project (IAR module → Infrastructure tab, fims.statement_work_accomplishments) whose pro-forma JEV was SUBMITTED and carries the
 * Work-in-Process account 1-04-03-020. API: GET /infra-iar/work-in-process (routes/infra_iar.php → InfraIarService::workInProcess()).
 * Everything about the project (office, name, location, contractor, the three contract costs) is read live from the RFA — nothing is kept on the record.
 * Physical accomplishment = all the work items' percentages combined (added up).
 */
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
/* Actions dropdown (the same menu as the IAR module list) */
.wip-menu { position:relative;display:inline-block; }
.wip-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
    border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Sans',sans-serif; }
.wip-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA); }
.wip-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.wip-menu-drop { display:none;position:fixed;min-width:190px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
.wip-menu-drop.open { display:block; }
.wip-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;font-family:'IBM Plex Sans',sans-serif; }
.wip-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.wip-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
html.afims-dark[data-bs-theme="dark"] .wip-menu-drop { background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);box-shadow:0 8px 24px rgba(0,0,0,.45); }
html.afims-dark[data-bs-theme="dark"] .wip-menu-item { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] .wip-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
html.afims-dark[data-bs-theme="dark"] .wip-menu-trigger { color:#FFFFFF;background:#3861BC;border-color:#3861BC; }
/* the table has many columns: it scrolls sideways inside its wrapper */
#wip-root .nha-tbl th { white-space:nowrap;line-height:1.3; }
#wip-root .nha-tbl td { vertical-align:top; }
#wip-root .nha-table-wrap .nha-tbl th { text-align:center !important; }   /* the list's column headers are centered (the cells keep their own alignment) */
#wip-root .wip-num { text-align:right;white-space:nowrap; }
.wip-bar { display:inline-block;vertical-align:middle;width:84px;height:8px;margin-right:8px;border-radius:4px;overflow:hidden;background:var(--afims-border, #E4ECF2); }
.wip-bar > i { display:block;height:100%;background:var(--afims-primary, #0035A9); }
.wip-bar.done > i { background:#16A34A; }
.wip-vo.pos { color:var(--afims-success, #1B7F3B);font-weight:600; } .wip-vo.neg { color:var(--afims-error, #C62828);font-weight:600; }
/* Book / Region filter dropdowns (searchable; same look as the IAR module's filters) */
.wip-fc { position:relative; min-width:230px; }
.wip-fc input { width:100%; box-sizing:border-box; padding-right:28px; }
.wip-fc-clear { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; cursor:pointer; font-size:16px; line-height:1; color:var(--afims-text-secondary,#6b7280); padding:2px 4px; }
.wip-fc-list { display:none; position:absolute; z-index:60; top:calc(100% + 4px); left:0; min-width:100%; width:max-content; max-width:min(420px, 90vw); max-height:260px; overflow:auto;
               background:var(--afims-surface,#fff); border:1px solid var(--afims-border,#d1d5db); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.wip-fc.open .wip-fc-list { display:block; }
.wip-fc-opt { padding:8px 12px; font-size:13px; cursor:pointer; white-space:normal; word-break:break-word; }   /* long names wrap instead of running off the screen */
.wip-fc.wip-fc-right .wip-fc-list { left:auto; right:0; }   /* the last filter opens leftwards so its list stays on screen */
.wip-fc-opt:hover { background:var(--afims-surface-subtle,#F4F7FA); }
.wip-fc-opt.active { font-weight:600; color:var(--afims-primary,#1d4ed8); }
.wip-fc-empty { padding:8px 12px; font-size:13px; color:var(--afims-text-secondary,#6b7280); }
/* the three cards are buttons: the selected one is outlined and its projects fill the list */
.wip-card-btn { cursor:pointer; user-select:none; }
.wip-card-btn:focus-visible { outline:2px solid var(--afims-primary, #0035A9); outline-offset:2px; }
.wip-card-btn.selected { box-shadow:0 0 0 2px var(--afims-primary, #0035A9), var(--sh-md, 0 4px 12px rgba(0,0,0,.12)); }
/* Transfer to MI/PED window */
.wip-menu-divider { height:1px; margin:4px 0; background:var(--afims-border, #E4ECF2); }
.wip-tr-msg { margin-bottom:12px; padding:8px 12px; border-radius:8px; font-size:13px; color:#B42318; background:#FDECEC; border:1px solid #F5C2C0; }
.wip-tr-msg.ok { color:#166534; background:#E7F6EC; border-color:#BBE5C8; }
.wip-tr-done { margin-bottom:12px; padding:8px 12px; border-radius:8px; font-size:13px; color:#166534; background:#E7F6EC; border:1px solid #BBE5C8; }
/* the pro-forma card: same look as the IAR module's Pro-forma JEV (Book, Particulars, account table, total, Submit) */
.wip-pf-title { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--afims-text-muted, #62657E); margin:0 0 14px; }
.wip-pf-lbl { display:block; margin:0 0 6px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--afims-text, #1A1B21); }
.wip-pf-lbl .req { color:#DC3545; } .wip-pf-lbl .opt { font-weight:400; text-transform:none; letter-spacing:0; color:var(--afims-text-muted, #62657E); }
.wip-pf-input { width:100%; box-sizing:border-box; padding:9px 12px; font-size:14px; font-family:inherit; color:var(--afims-text, #1A1B21);
    background:var(--afims-surface, #fff); border:1px solid var(--afims-border, #E4ECF2); border-radius:8px; }
.wip-pf-input:focus { outline:none; border-color:var(--afims-primary, #0035A9); }
.wip-pf-input[readonly], .wip-pf-input:disabled { background:var(--afims-surface-subtle, #F4F7FA); color:var(--afims-text-secondary, #5B5E73); }
.wip-pf-field { margin-bottom:16px; }
.wip-pf-tbl { border:1px solid var(--afims-border, #E4ECF2); border-radius:10px; overflow:visible; }
.wip-pf-row { display:grid; grid-template-columns:minmax(0, 1fr) 190px 190px; align-items:center; border-top:1px solid var(--afims-border, #E4ECF2); }
.wip-pf-row:first-child { border-top:0; }
.wip-pf-row > div { padding:10px 12px; min-width:0; }
.wip-pf-head { background:var(--afims-surface-subtle, #F4F7FA); font-size:12px; font-weight:600; color:var(--afims-text-secondary, #5B5E73); border-radius:10px 10px 0 0; }
.wip-pf-head > div:nth-child(n+2) { text-align:center; }
.wip-pf-money { display:flex; align-items:center; gap:8px; } .wip-pf-money span { color:var(--afims-text-secondary, #5B5E73); }
.wip-pf-money .wip-pf-input { text-align:right; }
.wip-pf-total { background:var(--afims-surface-subtle, #F4F7FA); font-weight:700; border-radius:0 0 10px 10px; }
.wip-pf-total .lbl { text-align:right; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
.wip-pf-total .wip-pf-money { justify-content:space-between; }
.wip-pf-combo { position:relative; }
.wip-pf-list { display:none; position:absolute; z-index:70; top:calc(100% + 4px); left:0; right:0; background:var(--afims-surface, #fff); border:1px solid var(--afims-border, #d1d5db); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.wip-pf-combo.open .wip-pf-list { display:block; }
.wip-pf-opt { padding:9px 12px; font-size:13px; cursor:pointer; }
.wip-pf-opt:hover, .wip-pf-opt.active { background:var(--afims-surface-subtle, #F4F7FA); }
.wip-pf-actions { display:flex; justify-content:flex-end; margin-top:14px; }
.wip-pf-btn { height:38px; padding:0 24px; border-radius:8px; border:1px solid var(--afims-heading-fill, #000E3E); background:var(--afims-heading-fill, #000E3E);
    color:var(--afims-pure-white, #FFFFFF); font-size:13px; font-weight:600; font-family:'IBM Plex Sans',sans-serif; cursor:pointer; }
.wip-pf-btn:hover:not(:disabled) { opacity:.9; } .wip-pf-btn:disabled { opacity:.5; cursor:not-allowed; }
#wip-tr-print { height:38px; padding:0 16px; font-size:13px; }
.wip-pf-orig { font-size:12px; color:var(--afims-text-secondary, #5B5E73); line-height:1.7; }
@media (max-width:900px) { .wip-pf-row { grid-template-columns:minmax(0, 1fr) 1fr 1fr; } }
/* View window */
.wip-grid { display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:12px 24px; }
.wip-k { font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted, #62657E); }
.wip-v { font-size:13px;color:var(--afims-text, #1A1B21);margin-top:2px;word-break:break-word; }
.wip-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:14px 16px;margin-bottom:14px; }
.wip-card h4 { margin:0 0 10px;font-size:13px;font-weight:700;color:var(--afims-heading-fill, #000E3E); }
html.afims-dark[data-bs-theme="dark"] .wip-card h4 { color:#FFFFFF; }
@media (max-width:760px) { .wip-grid { grid-template-columns:minmax(0, 1fr); } }

</style>

<div id="wip-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Work-In-Process Inventory</h1>
            <p class="app-page-subtitle">Infrastructure projects whose pro-forma JEV was submitted, with their contract costs and physical accomplishment.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="wip-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── TABS: one per WIP category (the sub-ledgers of 1-04-03-020); only the account title is shown ── -->
    <div class="nha-tabs no-print" id="wip-tabs"></div>

    <!-- ── CARDS of the selected category (GET /infra-iar/wip-summary?account=) ── -->
    <div class="nha-summary no-print" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
        <div class="nha-sc nha-sc-navy wip-card-btn" id="wip-cardbox-ongoing" data-status="ongoing" role="button" tabindex="0" title="Show the projects still in this category">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">On Going Projects</div>
                <div class="nha-sc-val" id="wip-card-ongoing">—</div>
            </div></div>
            <p class="nha-kpi-sub">Work in process in this category</p>
        </div>
        <div class="nha-sc nha-sc-amber wip-card-btn" id="wip-cardbox-transfer" data-status="transfer" role="button" tabindex="0" title="Show the projects at 90% or more that still need to be transferred">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">For Transfer</div>
                <div class="nha-sc-val" id="wip-card-transfer">—</div>
            </div></div>
            <p class="nha-kpi-sub">90% or more of completion, not yet transferred</p>
        </div>
        <div class="nha-sc nha-sc-green wip-card-btn" id="wip-cardbox-completed" data-status="completed" role="button" tabindex="0" title="Show the transferred (and 100% paid) projects">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Completed Project</div>
                <div class="nha-sc-val" id="wip-card-completed">—</div>
            </div></div>
            <p class="nha-kpi-sub">Transferred, or paid 100%</p>
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
                    <input type="text" id="wip-filter-search" class="nha-filter-input" placeholder="Search project, location, contractor, contract, book, JEV no.…">
                    <button class="nha-filter-clear-btn" id="wip-search-clear" hidden type="button">×</button>
                </div>
            </div>
            <!-- searchable dropdowns (built by wip_fcBuild(); the choices come from GET /infra-iar/wip-filter-options) -->
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book of Accounts</label>
                <div class="wip-fc" id="wip-fc-book" data-all="All books"></div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Region</label>
                <div class="wip-fc wip-fc-right" id="wip-fc-region" data-all="All regions"></div>
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
                        <th>NAME OF <br> PROJECT</th>
                        <th>LOCATION</th>
                        <th>CONTRACTOR</th>
                        <th>CONTRACT</th>
                        <th style="text-align:right;">ORIGINAL CONTRACT <br> COST</th>
                        <th style="text-align:right;">VARIATION <br> ORDER</th>
                        <th style="text-align:right;">REVISED CONTRACT <br> COST</th>
                        <th>PHYSICAL <br> ACCOMPLISHMENT</th>
                        <th style="text-align:right;">AMOUNT <br> PAID</th>
                        <th>PERCENTAGE OF <br> COMPLETION</th>
                        <th style="text-align:right;">UNBILLED <br> AMOUNT</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="wip-tbody">
                    <tr><td colspan="14"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="wip-from">0</span>–<span id="wip-to">0</span></strong> of <strong><span id="wip-total">0</span></strong></span>
            <div id="wip-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ── VIEW WINDOW ────────────────────────────────────── -->
    <div class="nha-overlay" id="wip-overlay">
        <div class="nha-modal u-modal-lg">
            <div class="nha-modal-hdr">
                <h3 id="wip-view-title">Work-In-Process Inventory</h3>
                <button type="button" class="nha-modal-close" id="wip-view-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="wip-view-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="wip-view-close2">Close</button>
            </div>
        </div>
    </div>

    <!-- ── TRANSFER TO MI/PED (pro-forma reclassification JEV) ── -->
    <div class="nha-overlay" id="wip-tr-overlay">
        <div class="nha-modal u-modal-lg">
            <div class="nha-modal-hdr">
                <h3>Transfer to MI/PED — Pro-forma JEV</h3>
                <button type="button" class="nha-modal-close" id="wip-tr-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="wip-tr-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="wip-tr-cancel">Close</button>
            </div>
        </div>
    </div>

</div><!-- /#wip-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   WORK IN PROCESS   prefix: wip_   (GET /infra-iar/work-in-process)
═══════════════════════════════════════════════════════════════ */
let wip_status = 'ongoing';              // the card selected: ongoing (default) | transfer | completed
const WIP_API = '<?php echo $_wip_api; ?>' + '/infra-iar';
const WIP_PAGE_SIZE = 10;
let wip_page = 1, wip_rows = [], wip_timer = null;
let wip_tab = '', wip_tabTitle = '';      // the active tab: a WIP sub-ledger code (1-04-03-020-01 …) and its title

const wip_$ = id => document.getElementById(id);
function wip_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}
function wip_esc(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
const wip_dash  = v => (v === null || v === undefined || v === '') ? '—' : wip_esc(v);
const wip_money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const wip_pct   = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
function wip_signed(v) { v = Number(v) || 0; return (v > 0 ? '+' : (v < 0 ? '−' : '')) + wip_money(Math.abs(v)); }
function wip_voClass(v) { v = Number(v) || 0; return v > 0 ? ' pos' : (v < 0 ? ' neg' : ''); }
function wip_bar(p) {
    const n = Math.max(0, Math.min(100, Number(p) || 0));
    return `<span class="wip-bar${(Number(p) || 0) >= 100 ? ' done' : ''}"><i style="width:${n}%"></i></span>${wip_pct(p)}`;
}

/* the three cards: On Going Projects / For Transfer / Completed Project */
async function wip_loadCards() {
    try {
        const res  = await fetch(`${WIP_API}/wip-summary?account=${encodeURIComponent(wip_tab)}`, { credentials: 'same-origin', headers: wip_authHeaders() });
        const json = await res.json();
        if (!json.success) return;
        wip_$('wip-card-ongoing').textContent   = json.data.ongoing;
        wip_$('wip-card-transfer').textContent  = json.data.for_transfer;
        wip_$('wip-card-completed').textContent = json.data.completed;
    } catch (e) { /* the cards just stay empty */ }
}

/* ── Filters: Book of Accounts and Region — searchable dropdowns ── */
const WIP_FC = { 'wip-fc-book': { param: 'book_id', key: 'books' }, 'wip-fc-region': { param: 'rc_id', key: 'regions' } };
const wip_fcSel  = { book_id: '', rc_id: '' };               // the chosen value of each filter ('' = all)
let   wip_fcData = { books: [], regions: [] };               // [{value, label}] from GET /infra-iar/wip-filter-options
function wip_fcBuild() {
    Object.entries(WIP_FC).forEach(([id, cfg]) => {
        const wrap = wip_$(id);
        if (!wrap) return;
        wrap.innerHTML = '<input type="text" class="nha-filter-input" autocomplete="off" placeholder="' + wip_esc(wrap.dataset.all) + '">'
            + '<button type="button" class="wip-fc-clear" hidden title="Clear">×</button><div class="wip-fc-list"></div>';
        const input = wrap.querySelector('input'), list = wrap.querySelector('.wip-fc-list'), clear = wrap.querySelector('.wip-fc-clear');
        let shown = [];
        const labelOf = v => (wip_fcData[cfg.key].find(o => String(o.value) === String(v)) || {}).label || '';
        const sync = () => { input.value = labelOf(wip_fcSel[cfg.param]); clear.hidden = !wip_fcSel[cfg.param]; };
        const render = () => {
            const q = input.dataset.typing === '1' ? input.value.trim().toLowerCase() : '';     // the shown label is not a search
            shown = wip_fcData[cfg.key].filter(o => !q || String(o.label).toLowerCase().includes(q));
            list.innerHTML = '<div class="wip-fc-opt" data-i="-1">' + wip_esc(wrap.dataset.all) + '</div>'
                + (shown.length ? shown.map((o, i) => '<div class="wip-fc-opt' + (String(o.value) === String(wip_fcSel[cfg.param]) ? ' active' : '') + '" data-i="' + i + '">' + wip_esc(o.label) + '</div>').join('')
                                : '<div class="wip-fc-empty">No match</div>');
        };
        const choose = v => {
            wip_fcSel[cfg.param] = v;
            input.dataset.typing = '0'; wrap.classList.remove('open'); sync();
            wip_page = 1; wip_load();
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
            const opt = e.target.closest('.wip-fc-opt');
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
async function wip_loadFilterOptions() {
    try {
        const res  = await fetch(`${WIP_API}/wip-filter-options`, { credentials: 'same-origin', headers: wip_authHeaders() });
        const json = await res.json();
        if (json.success) {
            wip_fcData = { books: json.data.books || [], regions: json.data.regions || [] };
            Object.keys(WIP_FC).forEach(id => wip_$(id)?._sync?.());
        }
    } catch (e) { /* the filters just stay empty */ }
}

/* the tabs: one per WIP sub-ledger, labelled with the account title only (e.g. "Sites and Services"); the first one opens */
async function wip_loadTabs() {
    try {
        const res  = await fetch(`${WIP_API}/wip-categories`, { credentials: 'same-origin', headers: wip_authHeaders() });
        const json = await res.json();
        const tabs = json.success ? (json.data || []) : [];
        wip_$('wip-tabs').innerHTML = tabs.map(t => `<button type="button" class="nha-tab" data-code="${wip_esc(t.account_code)}" data-title="${wip_esc(t.account_title)}">${wip_esc(t.account_title)}</button>`).join('');
        wip_$('wip-tabs').querySelectorAll('.nha-tab').forEach(b => b.addEventListener('click', () => wip_selectTab(b.dataset.code)));
        if (tabs.length) wip_selectTab(tabs[0].account_code, true);
    } catch (e) { /* no tabs: the page then lists nothing */ }
}
/* the three cards are buttons: clicking one filters the list to those projects (On Going Projects is the default; transferred projects show only under Completed Project) */
function wip_markCard() {
    document.querySelectorAll('.wip-card-btn').forEach(c => c.classList.toggle('selected', c.dataset.status === wip_status));
}
function wip_selectStatus(status) {
    if (status === wip_status) return;
    wip_status = status;
    wip_markCard();
    wip_page = 1;
    wip_load();
}

function wip_selectTab(code, force) {
    if (!force && code === wip_tab) return;
    wip_tab = code;
    wip_$('wip-tabs').querySelectorAll('.nha-tab').forEach(b => {
        const on = b.dataset.code === code;
        b.classList.toggle('active', on);
        if (on) wip_tabTitle = b.dataset.title;
    });
    wip_status = 'ongoing';                                     // a new tab starts on its On Going Projects
    wip_markCard();
    wip_page = 1;
    wip_load();
    wip_loadCards();                                         // the cards show the counts of THIS category
}

async function wip_load() {
    if (!wip_tab) return;                                    // the tabs load first (wip_loadTabs)
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ search: wip_$('wip-filter-search').value || '', page: wip_page, limit: WIP_PAGE_SIZE, account: wip_tab, status: wip_status });
        if (wip_fcSel.book_id) params.set('book_id', wip_fcSel.book_id);   // Book of Accounts filter
        if (wip_fcSel.rc_id)   params.set('rc_id', wip_fcSel.rc_id);       // Region filter
        const res  = await fetch(`${WIP_API}/work-in-process?${params}`, { credentials: 'same-origin', headers: wip_authHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        wip_render(json.data ?? []);
        wip_pagination(json.pagination ?? {});
    } catch (e) {
        wip_render([], e.message);
        wip_pagination({});
    } finally {
        window.PagePreloader?.hide();
    }
}

function wip_render(rows, error) {
    const tbody = wip_$('wip-tbody');
    wip_rows = rows;
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="14"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">' + wip_esc(error || (wip_status === 'completed' ? 'No completed ' + wip_tabTitle + ' projects yet. A project shows here once it is transferred or paid 100%.' : wip_status === 'transfer' ? 'No ' + wip_tabTitle + ' projects are waiting for transfer (90% or more of completion).' : 'No ' + wip_tabTitle + ' work in process found. A project shows here once its pro-forma JEV was submitted.')) + '</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td>${wip_dash(r.book_name)}</td>
        <td>${wip_dash(r.requesting_office)}</td>
        <td>${wip_dash(r.project_name)}</td>
        <td>${wip_dash(r.location)}</td>
        <td>${wip_dash(r.contractor)}</td>
        <td>${wip_dash(r.contract)}</td>
        <td class="wip-num">${wip_money(r.original_contract_cost)}</td>
        <td class="wip-num"><span class="wip-vo${wip_voClass(r.variation_order)}">${wip_signed(r.variation_order)}</span></td>
        <td class="wip-num"><strong>${wip_money(r.contract_cost)}</strong></td>
        <td style="white-space:nowrap;">${wip_bar(r.accomplishment)}</td>
        <td class="wip-num">${wip_money(r.amount_paid)}</td>
        <td style="white-space:nowrap;">${wip_bar(r.percent_completion)}</td>
        <td class="wip-num">${wip_money(r.unbilled_amount)}</td>
        <td style="white-space:nowrap;">
            <div class="wip-menu">
                <button type="button" class="wip-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="wip_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="wip-menu-drop">
                    <button type="button" class="wip-menu-item" onclick="wip_closeMenus();wip_view(${i})">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </button>
                    ${Number(r.percent_completion) >= 90 || r.transfer_jev_no ? `<div class="wip-menu-divider"></div>
                    <button type="button" class="wip-menu-item" onclick="wip_closeMenus();wip_transfer(${i})" title="${r.transfer_jev_no ? 'Transferred as ' + wip_esc(r.transfer_jev_no) : 'Percentage of Completion reached 90%'}">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4-4m-4 4l4 4"/></svg>
                        ${r.transfer_jev_no ? 'Transferred — ' + wip_esc(r.transfer_jev_no) : 'Transfer to MI/PED'}
                    </button>` : ''}
                </div>
            </div>
        </td>
    </tr>`).join('');
}

function wip_pagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = WIP_PAGE_SIZE } = p;
    wip_$('wip-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    wip_$('wip-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    wip_$('wip-total').textContent = total;
    const box = wip_$('wip-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="wip_goTo(${i})">${i}</button>`);
    }
    box.innerHTML = `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="wip_goTo(${current_page - 1})">‹</button>` + pages.join('')
        + `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="wip_goTo(${current_page + 1})">›</button>`;
}
function wip_goTo(p) { if (p < 1) return; wip_page = p; wip_load(); }

/* ── Actions menu ── */
function wip_toggleMenu(btn) {
    const drop = btn.closest('.wip-menu').querySelector('.wip-menu-drop');
    const isOpen = drop.classList.contains('open');
    wip_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function wip_closeMenus() {
    document.querySelectorAll('.wip-menu-drop.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.wip-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('.wip-menu')) wip_closeMenus(); });
window.addEventListener('scroll', wip_closeMenus, true);

/* ── View window ── */
function wip_view(i) {
    const r = wip_rows[i];
    if (!r) return;
    const kv = (k, v) => `<div><div class="wip-k">${k}</div><div class="wip-v">${v}</div></div>`;
    const items = r.items || [];
    wip_$('wip-view-title').textContent = 'Work-In-Process Inventory — ' + (r.project_name || r.swa_number || '');
    wip_$('wip-view-body').innerHTML = `
        <div class="wip-card"><h4>Project</h4><div class="wip-grid">
            ${kv('RFA No.', wip_dash(r.rfa_number))}${kv('SWA No.', wip_dash(r.swa_number))}
            ${kv('Book of Accounts', wip_dash(r.book_name))}${kv('Regional Office / District Office', wip_dash(r.requesting_office))}
            ${kv('Project Code', wip_dash(r.project_code))}${kv('Name of Project', wip_dash(r.project_name))}
            ${kv('Program', wip_dash(r.program_name))}${kv('Location', wip_dash(r.location))}
            ${kv('Contractor', wip_dash(r.contractor))}${kv('Contract', wip_dash(r.contract))}
            ${kv('Area in SQM / Units', r.total_land_area === null || r.total_land_area === undefined || r.total_land_area === '' ? '—' : wip_money(r.total_land_area))}${kv('TCT Number', wip_dash(r.tct_number))}
        </div></div>
        <div class="wip-card"><h4>Contract Cost</h4><div class="wip-grid">
            ${kv('Original Contract Cost', wip_money(r.original_contract_cost))}
            ${kv('Variation Order', '<span class="wip-vo' + wip_voClass(r.variation_order) + '">' + wip_signed(r.variation_order) + '</span>')}
            ${kv('Revised Contract Cost', '<strong>' + wip_money(r.contract_cost) + '</strong>')}
            ${kv('Physical Accomplishment', wip_bar(r.accomplishment))}${kv('Amount Paid', wip_money(r.amount_paid))}
            ${kv('Percentage of Completion', wip_bar(r.percent_completion))}${kv('Unbilled Amount', wip_money(r.unbilled_amount))}
            ${kv('Date Started', wip_dash(r.date_started))}${kv('Completion Date (Revised)', wip_dash(r.completion_date_revised))}
            ${kv('Contract Duration', wip_dash(r.contract_duration))}
        </div></div>
        <div class="wip-card"><h4>Statement of Work Accomplishments</h4>
            <div class="nha-tbl-scroll"><table class="nha-tbl"><thead><tr><th>Description</th><th style="text-align:right;">Cost</th><th style="text-align:right;">% Accomplishment</th></tr></thead>
            <tbody>${items.map(it => `<tr><td style="white-space:pre-wrap;">${wip_esc(it.description)}</td><td class="wip-num">${wip_money(it.cost)}</td><td class="wip-num">${wip_pct(it.accomplishment_pct)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td style="text-align:right;">Total</td><td class="wip-num">${wip_money(r.items_cost)}</td><td class="wip-num">${wip_pct(r.accomplishment)}</td></tr></tfoot></table></div>
        </div>`;
    wip_$('wip-overlay').classList.add('open');
}
function wip_closeView() { wip_$('wip-overlay').classList.remove('open'); }

/* ── Actions → Transfer to MI/PED: the reclassification pro-forma JEV (offered once the Percentage of Completion reaches 90%) ──
   Original entry (read-only): Debit the WIP sub-ledger, Credit Accounts Payable. Transfer entry: Debit the NEW account the user chooses
   (Completed Development for Sale - Land / Building, Land, Building / Community Facilities), Credit the old WIP sub-ledger. The book comes from the original JEV. */
let wip_tr = null;
const wip_trAmt = () => parseFloat(String(wip_$('wip-tr-amount').value).replace(/,/g, '')) || 0;
function wip_trParticulars() {
    const d = wip_tr.data, code = wip_tr.code;
    const t = (d.debit_accounts.find(a => a.account_code === code) || {}).account_title || '';
    // shown from the start (with the new account still to be chosen) and rewritten as soon as one is selected
    return 'To transfer Proj.Devt.cost of ' + (d.project_name || d.swa_number) + ' from WIP - ' + d.old_account.account_title + ' to ' + (t || '[select the new account]') + ' account';
}
async function wip_transfer(i) {
    const r = wip_rows[i];
    if (!r) return;
    // an already transferred project goes to the page it was transferred to — Merchandise Inventory (1-04-01-010-xx Completed Development for Sale) or
    // Property and Equipment for Distribution (1-04-02-090-xx) — on the matching tab, filtered to this project, with its View window open.
    const pages = { '1-04-01-010-': 'MerchandiseInventoryPage', '1-04-02-090-': 'PropertyAndEquipmentForDistributionPage' };
    const target = r.transfer_jev_no ? Object.keys(pages).find(p => String(r.transfer_account || '').startsWith(p)) : null;
    if (target) {
        window.location.href = window.location.pathname.replace(/[^/]*$/, '') + pages[target] + '?tab=' + encodeURIComponent(r.transfer_account) + '&q=' + encodeURIComponent(r.swa_number) + '&open=' + encodeURIComponent(r.swa_number);
        return;
    }
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${WIP_API}/transfer-proforma?id=${encodeURIComponent(r.swa_id)}`, { credentials: 'same-origin', headers: wip_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the pro-forma.');
        wip_trOpen(json.data);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
function wip_trOpen(d) {
    wip_tr = { data: d, edited: false, code: d.transfer ? d.transfer.account_code : '' };
    const done = !!d.transfer, f = wip_money;
    const orig = d.original.lines.map(l => (Number(l.debit) ? 'Debit ' : 'Credit ') + wip_esc(l.account_code) + ' - ' + wip_esc(l.account_title || '') + ' &nbsp;₱' + f(Number(l.debit) || Number(l.credit))).join('<br>');
    wip_$('wip-tr-body').innerHTML = `
        <div id="wip-tr-msg" class="wip-tr-msg" hidden></div>
        <div class="wip-card"><h4>Project</h4><div class="wip-grid">
            <div><div class="wip-k">SWA No. / RFA No.</div><div class="wip-v">${wip_esc(d.swa_number)} / ${wip_dash(d.rfa_number)}</div></div>
            <div><div class="wip-k">Name of Project</div><div class="wip-v">${wip_dash(d.project_name)}</div></div>
            <div><div class="wip-k">Percentage of Completion</div><div class="wip-v">${wip_bar(d.percent_completion)} <span style="color:var(--afims-text-muted,#62657E);">(paid ${f(d.amount_paid)})</span></div></div>
            <div><div class="wip-k">Original Entry — ${wip_esc(d.original.jev_no)}</div><div class="wip-v wip-pf-orig">${orig}</div></div>
        </div></div>
        <div class="wip-card">
            <div class="wip-pf-title">Pro-forma JEV — reclassification / transfer</div>
            ${done ? `<div class="wip-tr-done">Already submitted as Journal Entry Voucher <strong>${wip_esc(d.transfer.jev_no)}</strong>.</div>` : ''}
            <div class="wip-pf-field"><label class="wip-pf-lbl">Book <span class="req">*</span></label>
                <select class="wip-pf-input" disabled tabindex="-1"><option>${wip_esc(d.book_name || '')}</option></select></div>
            <div class="wip-pf-field"><label class="wip-pf-lbl" for="wip-tr-part">Particulars <span class="opt">(optional)</span></label>
                <textarea id="wip-tr-part" class="wip-pf-input" rows="3" style="resize:vertical;"></textarea></div>
            <div class="wip-pf-tbl">
                <div class="wip-pf-row wip-pf-head"><div>Account Code - Account Name</div><div>Debit (₱)</div><div>Credit (₱)</div></div>
                <div class="wip-pf-row">
                    <div><div class="wip-pf-combo" id="wip-tr-combo">
                        <input type="text" id="wip-tr-debit" class="wip-pf-input" placeholder="Select the new account (Debit)" autocomplete="off" readonly style="cursor:pointer;">
                        <div class="wip-pf-list">${d.debit_accounts.map(a => `<div class="wip-pf-opt" data-code="${wip_esc(a.account_code)}">${wip_esc(a.account_code)} - ${wip_esc(a.account_title)}</div>`).join('')}</div>
                    </div></div>
                    <div class="wip-pf-money"><span>₱</span><input type="text" inputmode="decimal" id="wip-tr-amount" class="wip-pf-input" value="${f(d.amount)}"></div>
                    <div class="wip-pf-money"><span></span><input type="text" class="wip-pf-input" value="0.00" readonly tabindex="-1"></div>
                </div>
                <div class="wip-pf-row">
                    <div><input type="text" class="wip-pf-input" value="${wip_esc(d.old_account.account_code)} - ${wip_esc(d.old_account.account_title)}" readonly tabindex="-1"></div>
                    <div class="wip-pf-money"><span></span><input type="text" class="wip-pf-input" value="0.00" readonly tabindex="-1"></div>
                    <div class="wip-pf-money"><span>₱</span><input type="text" id="wip-tr-credit" class="wip-pf-input" value="${f(d.amount)}" readonly tabindex="-1"></div>
                </div>
                <div class="wip-pf-row wip-pf-total"><div class="lbl">Total</div>
                    <div class="wip-pf-money"><span>₱</span><b id="wip-tr-td">${f(d.amount)}</b></div><div class="wip-pf-money"><span>₱</span><b id="wip-tr-tc">${f(d.amount)}</b></div></div>
            </div>
            <div class="wip-pf-actions">
                <button type="button" class="nha-btn-act" style="margin-right:8px;" id="wip-tr-print" title="Print this pro-forma JEV (the rows as shown)">Print JEV</button>
                <button type="button" class="wip-pf-btn" id="wip-tr-save">Submit</button>
            </div>
        </div>`;
    wip_$('wip-tr-print').addEventListener('click', wip_trPrint);
    wip_$('wip-tr-save').addEventListener('click', wip_trSubmit);
    const combo = wip_$('wip-tr-combo'), dbt = wip_$('wip-tr-debit'), amt = wip_$('wip-tr-amount'), part = wip_$('wip-tr-part'), save = wip_$('wip-tr-save');
    const titleOf = c => { const a = d.debit_accounts.find(x => x.account_code === c); return a ? a.account_code + ' - ' + a.account_title : ''; };
    const sync = () => {
        const n = wip_trAmt();
        dbt.value = titleOf(wip_tr.code);
        wip_$('wip-tr-credit').value = wip_money(n); wip_$('wip-tr-td').textContent = wip_money(n); wip_$('wip-tr-tc').textContent = wip_money(n);
        if (!wip_tr.edited) part.value = wip_trParticulars();
        save.disabled = done || !d.eligible || !wip_tr.code || n <= 0;
    };
    // the new account is chosen from the four accounts; the Credit (old WIP account) is loaded automatically
    dbt.addEventListener('click', () => { if (!done) combo.classList.toggle('open'); });
    dbt.addEventListener('blur', () => setTimeout(() => combo.classList.remove('open'), 120));
    combo.querySelectorAll('.wip-pf-opt').forEach(o => {
        if (o.dataset.code === wip_tr.code) o.classList.add('active');
        o.addEventListener('mousedown', e => {
            e.preventDefault();
            wip_tr.code = o.dataset.code;
            combo.querySelectorAll('.wip-pf-opt').forEach(x => x.classList.toggle('active', x === o));
            combo.classList.remove('open'); sync();
        });
    });
    amt.addEventListener('input', () => { amt.value = amt.value.replace(/[^0-9.,]/g, ''); sync(); });
    amt.addEventListener('blur', () => { if (amt.value) amt.value = wip_money(wip_trAmt()); sync(); });
    part.addEventListener('input', () => { wip_tr.edited = true; });
    if (done) { amt.readOnly = true; part.readOnly = true; amt.value = wip_money(d.transfer.amount || d.amount); }
    save.hidden = done;
    sync();
    wip_$('wip-tr-overlay').classList.add('open');
    if (!done) setTimeout(() => { combo.classList.add('open'); dbt.scrollIntoView({ block: 'center' }); dbt.focus(); }, 0);   // the choice of the new account opens by itself
}
function wip_trMsg(text, ok) {
    const m = wip_$('wip-tr-msg');
    m.hidden = !text; m.textContent = text || ''; m.className = 'wip-tr-msg' + (ok ? ' ok' : '');
    wip_$('wip-tr-body').scrollTop = 0;
}
async function wip_trSubmit() {
    const d = wip_tr.data, btn = wip_$('wip-tr-save');
    btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${WIP_API}/transfer-submit`, {
            method: 'POST', credentials: 'same-origin', headers: wip_authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ id: d.id, debit_account: wip_tr.code, amount: wip_trAmt(), particulars: wip_$('wip-tr-part').value.trim() }),
        });
        const json = await res.json();
        if (!json.success) throw new Error((json.errors && json.errors.length ? json.errors.join(' ') : '') || json.message || 'Failed to submit the transfer.');
        wip_trMsg(json.message || 'Transfer submitted.', true);
        wip_$('wip-tr-combo').style.pointerEvents = 'none'; wip_$('wip-tr-amount').readOnly = true; wip_$('wip-tr-part').readOnly = true;
        btn.hidden = true;
        wip_load(); wip_loadCards();
    } catch (e) {
        wip_trMsg(e.message, false);
        btn.disabled = false;
    } finally {
        window.PagePreloader?.hide();
    }
}
function wip_trClose() { wip_$('wip-tr-overlay').classList.remove('open'); }

/* Print JEV → the shared print preview (prp_open + prp_jevFromProforma, as in the IAR module): the rows as shown, the Particulars as typed;
   once submitted it carries the real JEV number and date. The pro-forma window STAYS open behind the preview (it is not closed), so closing the preview returns to it. */
function wip_trPrint() {
    const d = wip_tr.data, done = !!d.transfer, code = done ? d.transfer.account_code : wip_tr.code;
    const acct = (d.debit_accounts.find(a => a.account_code === code) || {});
    const n = done ? (parseFloat(d.transfer.amount) || wip_trAmt()) : wip_trAmt();
    const rc = {
        rfa_number: d.rfa_number || '',
        draft: {
            rows: [
                { code: code || '', title: acct.account_title || '', debit: n, credit: 0 },
                { code: d.old_account.account_code, title: d.old_account.account_title, debit: 0, credit: n },
            ],
            particulars: wip_$('wip-tr-part').value.trim(),
        },
        print_particulars: 'Pro-forma JEV — Transfer of Infrastructure SWA No. ' + d.swa_number + ' of RFA No. ' + d.rfa_number,
        ...(done ? { jev_no: d.transfer.jev_no, jev_date: d.transfer.jev_date || '' } : {}),
    };
    prp_open(prp_jevFromProforma(d.rfa_number, rc), { type: 'JEV' });
}

/* ── wiring ── */
(function () {
    const run = () => {
        wip_$('wip-refresh').addEventListener('click', () => { wip_load(); wip_loadCards(); });
        const search = wip_$('wip-filter-search'), clear = wip_$('wip-search-clear');
        search.addEventListener('input', () => {
            clear.hidden = !search.value;
            clearTimeout(wip_timer);
            wip_timer = setTimeout(() => { wip_page = 1; wip_load(); }, 300);
        });
        clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; wip_page = 1; wip_load(); });
        ['wip-view-close', 'wip-view-close2'].forEach(id => wip_$(id).addEventListener('click', wip_closeView));
        ['wip-tr-close', 'wip-tr-cancel'].forEach(id => wip_$(id).addEventListener('click', wip_trClose));
        wip_$('wip-overlay').addEventListener('click', e => { if (e.target === wip_$('wip-overlay')) wip_closeView(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') wip_closeView(); });
        document.querySelectorAll('.wip-card-btn').forEach(c => {
            c.addEventListener('click', () => wip_selectStatus(c.dataset.status));
            c.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); wip_selectStatus(c.dataset.status); } });
        });
        wip_markCard();
        wip_fcBuild();
        wip_loadFilterOptions();
        wip_loadTabs();                                      // opens the first tab, which loads the list
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
<?php
// Print JEV (Transfer to MI/PED) -> the shared print preview (prp_open + prp_jevFromProforma), as in the IAR module.
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
