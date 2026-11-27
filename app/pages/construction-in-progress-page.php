<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_cip_api = Config('API.FIMS_URL');
/*
 * construction-in-progress-page.php — Construction in Progress (PHPMaker page ConstructionInProgressPage, view views/ConstructionInProgressPage.php).
 *
 * Like the Work-In-Process page, but ONE list (no category tabs): the infrastructure projects (IAR module → Infrastructure tab,
 * fims.statement_work_accomplishments) whose pro-forma JEV was SUBMITTED and carries a CIP account:
 *   1-06-99-010 CIP - Land Improvements          1-06-99-020 CIP - Infrastructure Assets      1-06-99-030 CIP - Building & Other Structures
 *   1-06-99-020-01 Sites & Services   -02 Slum Upgrading   -03 Housing Units   -04 Resettlement Projects   (sub-ledgers of 1-06-99-020)
 * API: GET /infra-iar/construction-in-progress (routes/infra_iar.php → InfraIarService::constructionInProgress()),
 *      GET /infra-iar/cip-filter-options (Book of Accounts / Region filters).
 * Everything about the project (office, name, location, contractor, the three contract costs) is read live from the RFA; Amount Paid / Percentage of Completion /
 * Unbilled Amount come from the project's released Disbursement Voucher; Physical accomplishment = all the work items' percentages added up.
 */
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
/* Actions dropdown (the same menu as the Work-In-Process page) */
.cip-menu { position:relative;display:inline-block; }
.cip-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
    border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Sans',sans-serif; }
.cip-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA); }
.cip-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.cip-menu-drop { display:none;position:fixed;min-width:190px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
.cip-menu-drop.open { display:block; }
.cip-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;font-family:'IBM Plex Sans',sans-serif; }
.cip-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.cip-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
html.afims-dark[data-bs-theme="dark"] .cip-menu-drop { background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);box-shadow:0 8px 24px rgba(0,0,0,.45); }
html.afims-dark[data-bs-theme="dark"] .cip-menu-item { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] .cip-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
html.afims-dark[data-bs-theme="dark"] .cip-menu-trigger { color:#FFFFFF;background:#3861BC;border-color:#3861BC; }
/* the table has many columns: it scrolls sideways inside its wrapper */
#cip-root .nha-tbl th { white-space:nowrap;line-height:1.3; }
#cip-root .nha-tbl td { vertical-align:top; }
#cip-root .nha-table-wrap .nha-tbl th { text-align:center !important; }   /* the list's column headers are centered (the cells keep their own alignment) */
#cip-root .cip-num { text-align:right;white-space:nowrap; }
.cip-bar { display:inline-block;vertical-align:middle;width:84px;height:8px;margin-right:8px;border-radius:4px;overflow:hidden;background:var(--afims-border, #E4ECF2); }
.cip-bar > i { display:block;height:100%;background:var(--afims-primary, #0035A9); }
.cip-bar.done > i { background:#16A34A; }
.cip-vo.pos { color:var(--afims-success, #1B7F3B);font-weight:600; } .cip-vo.neg { color:var(--afims-error, #C62828);font-weight:600; }
/* the three cards are buttons: the selected one is outlined and its projects fill the list */
.cip-card-btn { cursor:pointer; user-select:none; }
.cip-card-btn:focus-visible { outline:2px solid var(--afims-primary, #0035A9); outline-offset:2px; }
.cip-card-btn.selected { box-shadow:0 0 0 2px var(--afims-primary, #0035A9), var(--sh-md, 0 4px 12px rgba(0,0,0,.12)); }
/* Book / Region filter dropdowns (searchable; same look as the Work-In-Process page) */
.cip-fc { position:relative; min-width:230px; }
.cip-fc input { width:100%; box-sizing:border-box; padding-right:28px; }
.cip-fc-clear { position:absolute; right:6px; top:50%; transform:translateY(-50%); border:0; background:none; cursor:pointer; font-size:16px; line-height:1; color:var(--afims-text-secondary,#6b7280); padding:2px 4px; }
.cip-fc-list { display:none; position:absolute; z-index:60; top:calc(100% + 4px); left:0; min-width:100%; width:max-content; max-width:min(420px, 90vw); max-height:260px; overflow:auto;
               background:var(--afims-surface,#fff); border:1px solid var(--afims-border,#d1d5db); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.cip-fc.open .cip-fc-list { display:block; }
.cip-fc-opt { padding:8px 12px; font-size:13px; cursor:pointer; white-space:normal; word-break:break-word; }
.cip-fc.cip-fc-right .cip-fc-list { left:auto; right:0; }   /* the last filter opens leftwards so its list stays on screen */
.cip-fc-opt:hover { background:var(--afims-surface-subtle,#F4F7FA); }
.cip-fc-opt.active { font-weight:600; color:var(--afims-primary,#1d4ed8); }
.cip-fc-empty { padding:8px 12px; font-size:13px; color:var(--afims-text-secondary,#6b7280); }
/* View window */
.cip-grid { display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:12px 24px; }
.cip-k { font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted, #62657E); }
.cip-v { font-size:13px;color:var(--afims-text, #1A1B21);margin-top:2px;word-break:break-word; }
.cip-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:14px 16px;margin-bottom:14px; }
.cip-card h4 { margin:0 0 10px;font-size:13px;font-weight:700;color:var(--afims-heading-fill, #000E3E); }
html.afims-dark[data-bs-theme="dark"] .cip-card h4 { color:#FFFFFF; }
@media (max-width:760px) { .cip-grid { grid-template-columns:minmax(0, 1fr); } }
/* Transfer to MI/PED window */
.cip-menu-divider { height:1px; margin:4px 0; background:var(--afims-border, #E4ECF2); }
.cip-tr-msg { margin-bottom:12px; padding:8px 12px; border-radius:8px; font-size:13px; color:#B42318; background:#FDECEC; border:1px solid #F5C2C0; }
.cip-tr-msg.ok { color:#166534; background:#E7F6EC; border-color:#BBE5C8; }
.cip-tr-done { margin-bottom:12px; padding:8px 12px; border-radius:8px; font-size:13px; color:#166534; background:#E7F6EC; border:1px solid #BBE5C8; }
/* the pro-forma card: same look as the IAR module's Pro-forma JEV (Book, Particulars, account table, total, Submit) */
.cip-pf-title { font-size:11px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; color:var(--afims-text-muted, #62657E); margin:0 0 14px; }
.cip-pf-lbl { display:block; margin:0 0 6px; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--afims-text, #1A1B21); }
.cip-pf-lbl .req { color:#DC3545; } .cip-pf-lbl .opt { font-weight:400; text-transform:none; letter-spacing:0; color:var(--afims-text-muted, #62657E); }
.cip-pf-input { width:100%; box-sizing:border-box; padding:9px 12px; font-size:14px; font-family:inherit; color:var(--afims-text, #1A1B21);
    background:var(--afims-surface, #fff); border:1px solid var(--afims-border, #E4ECF2); border-radius:8px; }
.cip-pf-input:focus { outline:none; border-color:var(--afims-primary, #0035A9); }
.cip-pf-input[readonly], .cip-pf-input:disabled { background:var(--afims-surface-subtle, #F4F7FA); color:var(--afims-text-secondary, #5B5E73); }
.cip-pf-field { margin-bottom:16px; }
.cip-pf-tbl { border:1px solid var(--afims-border, #E4ECF2); border-radius:10px; overflow:visible; }
.cip-pf-row { display:grid; grid-template-columns:minmax(0, 1fr) 190px 190px; align-items:center; border-top:1px solid var(--afims-border, #E4ECF2); }
.cip-pf-row:first-child { border-top:0; }
.cip-pf-row > div { padding:10px 12px; min-width:0; }
.cip-pf-head { background:var(--afims-surface-subtle, #F4F7FA); font-size:12px; font-weight:600; color:var(--afims-text-secondary, #5B5E73); border-radius:10px 10px 0 0; }
.cip-pf-head > div:nth-child(n+2) { text-align:center; }
.cip-pf-money { display:flex; align-items:center; gap:8px; } .cip-pf-money span { color:var(--afims-text-secondary, #5B5E73); }
.cip-pf-money .cip-pf-input { text-align:right; }
.cip-pf-total { background:var(--afims-surface-subtle, #F4F7FA); font-weight:700; border-radius:0 0 10px 10px; }
.cip-pf-total .lbl { text-align:right; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
.cip-pf-total .cip-pf-money { justify-content:space-between; }
.cip-pf-combo { position:relative; }
.cip-pf-list { display:none; position:absolute; z-index:70; top:calc(100% + 4px); left:0; right:0; background:var(--afims-surface, #fff); border:1px solid var(--afims-border, #d1d5db); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
.cip-pf-combo.open .cip-pf-list { display:block; }
.cip-pf-opt { padding:9px 12px; font-size:13px; cursor:pointer; }
.cip-pf-opt:hover, .cip-pf-opt.active { background:var(--afims-surface-subtle, #F4F7FA); }
.cip-pf-actions { display:flex; justify-content:flex-end; margin-top:14px; }
.cip-pf-btn { height:38px; padding:0 24px; border-radius:8px; border:1px solid var(--afims-heading-fill, #000E3E); background:var(--afims-heading-fill, #000E3E);
    color:var(--afims-pure-white, #FFFFFF); font-size:13px; font-weight:600; font-family:'IBM Plex Sans',sans-serif; cursor:pointer; }
.cip-pf-btn:hover:not(:disabled) { opacity:.9; } .cip-pf-btn:disabled { opacity:.5; cursor:not-allowed; }
#cip-tr-print { height:38px; padding:0 16px; font-size:13px; }
.cip-pf-orig { font-size:12px; color:var(--afims-text-secondary, #5B5E73); line-height:1.7; }
@media (max-width:900px) { .cip-pf-row { grid-template-columns:minmax(0, 1fr) 1fr 1fr; } }
</style>

<div id="cip-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Construction in Progress</h1>
            <p class="app-page-subtitle">Infrastructure projects whose pro-forma JEV was submitted to a CIP account, with their contract costs, payments and physical accomplishment.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="cip-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── TABS: one per CIP category (account titles only) ── -->
    <div class="nha-tabs no-print" id="cip-tabs"></div>

    <!-- ── CARDS of the selected category, by PHYSICAL accomplishment (GET /infra-iar/cip-summary?account=); clicking one filters the list ── -->
    <div class="nha-summary no-print" style="grid-template-columns:repeat(3, minmax(0, 1fr));">
        <div class="nha-sc nha-sc-navy cip-card-btn" data-status="ongoing" role="button" tabindex="0" title="Show the projects still being built (under 100% physical accomplishment)">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">On Going Projects</div>
                <div class="nha-sc-val" id="cip-card-ongoing">—</div>
            </div></div>
            <p class="nha-kpi-sub">Under 100% physical accomplishment</p>
        </div>
        <div class="nha-sc nha-sc-amber cip-card-btn" data-status="transfer" role="button" tabindex="0" title="Show the projects at 90% or more of physical accomplishment">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">For Transfer</div>
                <div class="nha-sc-val" id="cip-card-transfer">—</div>
            </div></div>
            <p class="nha-kpi-sub">90% or more physical accomplishment</p>
        </div>
        <div class="nha-sc nha-sc-green cip-card-btn" data-status="completed" role="button" tabindex="0" title="Show the projects that reached 100% physical accomplishment">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Completed Project</div>
                <div class="nha-sc-val" id="cip-card-completed">—</div>
            </div></div>
            <p class="nha-kpi-sub">100% physical accomplishment</p>
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
                    <input type="text" id="cip-filter-search" class="nha-filter-input" placeholder="Search project, location, contractor, contract, book, JEV no.…">
                    <button class="nha-filter-clear-btn" id="cip-search-clear" hidden type="button">×</button>
                </div>
            </div>
            <!-- searchable dropdowns (built by cip_fcBuild(); the choices come from GET /infra-iar/cip-filter-options) -->
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book of Accounts</label>
                <div class="cip-fc" id="cip-fc-book" data-all="All books"></div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Region</label>
                <div class="cip-fc cip-fc-right" id="cip-fc-region" data-all="All regions"></div>
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
                <tbody id="cip-tbody">
                    <tr><td colspan="14"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="cip-from">0</span>–<span id="cip-to">0</span></strong> of <strong><span id="cip-total">0</span></strong></span>
            <div id="cip-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ── VIEW WINDOW ────────────────────────────────────── -->
    <div class="nha-overlay" id="cip-overlay">
        <div class="nha-modal u-modal-lg">
            <div class="nha-modal-hdr">
                <h3 id="cip-view-title">Construction in Progress</h3>
                <button type="button" class="nha-modal-close" id="cip-view-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="cip-view-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="cip-view-close2">Close</button>
            </div>
        </div>
    </div>

    <!-- ── TRANSFER TO FIXED ASSETS (pro-forma reclassification JEV) ── -->
    <div class="nha-overlay" id="cip-tr-overlay">
        <div class="nha-modal u-modal-lg">
            <div class="nha-modal-hdr">
                <h3>Transfer to Fixed Assets — Pro-forma JEV</h3>
                <button type="button" class="nha-modal-close" id="cip-tr-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="cip-tr-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="cip-tr-cancel">Close</button>
            </div>
        </div>
    </div>
</div><!-- /#cip-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   CONSTRUCTION IN PROGRESS   prefix: cip_   (GET /infra-iar/construction-in-progress)
═══════════════════════════════════════════════════════════════ */
const CIP_API = '<?php echo $_cip_api; ?>' + '/infra-iar';
const CIP_PAGE_SIZE = 10;
let cip_page = 1, cip_rows = [], cip_timer = null;
let cip_tab = '', cip_tabTitle = '';      // the active tab: a CIP account code (1-06-99-010, 1-06-99-020-01 …) and its title
let cip_status = 'ongoing';             // the card selected: ongoing (default) | transfer | completed — all by physical accomplishment

const cip_$ = id => document.getElementById(id);
function cip_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}
function cip_esc(v) { return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
const cip_dash  = v => (v === null || v === undefined || v === '') ? '—' : cip_esc(v);
const cip_money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const cip_pct   = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
function cip_signed(v) { v = Number(v) || 0; return (v > 0 ? '+' : (v < 0 ? '−' : '')) + cip_money(Math.abs(v)); }
function cip_voClass(v) { v = Number(v) || 0; return v > 0 ? ' pos' : (v < 0 ? ' neg' : ''); }
function cip_bar(p) {
    const n = Math.max(0, Math.min(100, Number(p) || 0));
    return `<span class="cip-bar${(Number(p) || 0) >= 100 ? ' done' : ''}"><i style="width:${n}%"></i></span>${cip_pct(p)}`;
}

/* ── Filters: Book of Accounts and Region — searchable dropdowns ── */
const CIP_FC = { 'cip-fc-book': { param: 'book_id', key: 'books' }, 'cip-fc-region': { param: 'rc_id', key: 'regions' } };
const cip_fcSel  = { book_id: '', rc_id: '' };               // the chosen value of each filter ('' = all)
let   cip_fcData = { books: [], regions: [] };               // [{value, label}] from GET /infra-iar/cip-filter-options
function cip_fcBuild() {
    Object.entries(CIP_FC).forEach(([id, cfg]) => {
        const wrap = cip_$(id);
        if (!wrap) return;
        wrap.innerHTML = '<input type="text" class="nha-filter-input" autocomplete="off" placeholder="' + cip_esc(wrap.dataset.all) + '">'
            + '<button type="button" class="cip-fc-clear" hidden title="Clear">×</button><div class="cip-fc-list"></div>';
        const input = wrap.querySelector('input'), list = wrap.querySelector('.cip-fc-list'), clear = wrap.querySelector('.cip-fc-clear');
        let shown = [];
        const labelOf = v => (cip_fcData[cfg.key].find(o => String(o.value) === String(v)) || {}).label || '';
        const sync = () => { input.value = labelOf(cip_fcSel[cfg.param]); clear.hidden = !cip_fcSel[cfg.param]; };
        const render = () => {
            const q = input.dataset.typing === '1' ? input.value.trim().toLowerCase() : '';     // the shown label is not a search
            shown = cip_fcData[cfg.key].filter(o => !q || String(o.label).toLowerCase().includes(q));
            list.innerHTML = '<div class="cip-fc-opt" data-i="-1">' + cip_esc(wrap.dataset.all) + '</div>'
                + (shown.length ? shown.map((o, i) => '<div class="cip-fc-opt' + (String(o.value) === String(cip_fcSel[cfg.param]) ? ' active' : '') + '" data-i="' + i + '">' + cip_esc(o.label) + '</div>').join('')
                                : '<div class="cip-fc-empty">No match</div>');
        };
        const choose = v => {
            cip_fcSel[cfg.param] = v;
            input.dataset.typing = '0'; wrap.classList.remove('open'); sync();
            cip_page = 1; cip_load();
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
            const opt = e.target.closest('.cip-fc-opt');
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
async function cip_loadFilterOptions() {
    try {
        const res  = await fetch(`${CIP_API}/cip-filter-options`, { credentials: 'same-origin', headers: cip_authHeaders() });
        const json = await res.json();
        if (json.success) {
            cip_fcData = { books: json.data.books || [], regions: json.data.regions || [] };
            Object.keys(CIP_FC).forEach(id => cip_$(id)?._sync?.());
        }
    } catch (e) { /* the filters just stay empty */ }
}

/* the tabs: one per CIP account an entry can be posted to, labelled with the account title only (e.g. "CIP - Sites & Services"); the first one opens */
async function cip_loadTabs() {
    try {
        const res  = await fetch(`${CIP_API}/cip-categories`, { credentials: 'same-origin', headers: cip_authHeaders() });
        const json = await res.json();
        const tabs = json.success ? (json.data || []) : [];
        cip_$('cip-tabs').innerHTML = tabs.map(t => `<button type="button" class="nha-tab" data-code="${cip_esc(t.account_code)}" data-title="${cip_esc(t.account_title)}">${cip_esc(t.account_title)}</button>`).join('');
        cip_$('cip-tabs').querySelectorAll('.nha-tab').forEach(b => b.addEventListener('click', () => cip_selectTab(b.dataset.code)));
        if (tabs.length) cip_selectTab(tabs[0].account_code, true);
    } catch (e) { /* no tabs: the page then lists nothing */ }
}
/* the three cards: On Going Projects / For Transfer / Completed Project of the selected category, by PHYSICAL accomplishment */
async function cip_loadCards() {
    try {
        const res  = await fetch(`${CIP_API}/cip-summary?account=${encodeURIComponent(cip_tab)}`, { credentials: 'same-origin', headers: cip_authHeaders() });
        const json = await res.json();
        if (!json.success) return;
        cip_$('cip-card-ongoing').textContent   = json.data.ongoing;
        cip_$('cip-card-transfer').textContent  = json.data.for_transfer;
        cip_$('cip-card-completed').textContent = json.data.completed;
    } catch (e) { /* the cards just stay empty */ }
}
/* the cards are buttons: clicking one filters the list to those projects (On Going Projects is the default) */
function cip_markCard() {
    document.querySelectorAll('.cip-card-btn').forEach(c => c.classList.toggle('selected', c.dataset.status === cip_status));
}
function cip_selectStatus(status) {
    if (status === cip_status) return;
    cip_status = status;
    cip_markCard();
    cip_page = 1;
    cip_load();
}

function cip_selectTab(code, force) {
    if (!force && code === cip_tab) return;
    cip_tab = code;
    cip_$('cip-tabs').querySelectorAll('.nha-tab').forEach(b => {
        const on = b.dataset.code === code;
        b.classList.toggle('active', on);
        if (on) cip_tabTitle = b.dataset.title;
    });
    cip_status = 'ongoing';                                  // a new tab starts on its On Going Projects
    cip_markCard();
    cip_page = 1;
    cip_load();
    cip_loadCards();                                         // the cards show the counts of THIS category
}

async function cip_load() {
    if (!cip_tab) return;                                    // the tabs load first (cip_loadTabs)
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ search: cip_$('cip-filter-search').value || '', page: cip_page, limit: CIP_PAGE_SIZE, account: cip_tab, status: cip_status });
        if (cip_fcSel.book_id) params.set('book_id', cip_fcSel.book_id);   // Book of Accounts filter
        if (cip_fcSel.rc_id)   params.set('rc_id', cip_fcSel.rc_id);       // Region filter
        const res  = await fetch(`${CIP_API}/construction-in-progress?${params}`, { credentials: 'same-origin', headers: cip_authHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        cip_render(json.data ?? []);
        cip_pagination(json.pagination ?? {});
    } catch (e) {
        cip_render([], e.message);
        cip_pagination({});
    } finally {
        window.PagePreloader?.hide();
    }
}

function cip_render(rows, error) {
    const tbody = cip_$('cip-tbody');
    cip_rows = rows;
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="14"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">' + cip_esc(error || (cip_status === 'completed' ? 'No completed ' + cip_tabTitle + ' projects yet (100% physical accomplishment).' : cip_status === 'transfer' ? 'No ' + cip_tabTitle + ' projects have reached 90% physical accomplishment.' : 'No ' + cip_tabTitle + ' construction in progress found. A project shows here once its pro-forma JEV was submitted.')) + '</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td>${cip_dash(r.book_name)}</td>
        <td>${cip_dash(r.requesting_office)}</td>
        <td>${cip_dash(r.project_name)}</td>
        <td>${cip_dash(r.location)}</td>
        <td>${cip_dash(r.contractor)}</td>
        <td>${cip_dash(r.contract)}</td>
        <td class="cip-num">${cip_money(r.original_contract_cost)}</td>
        <td class="cip-num"><span class="cip-vo${cip_voClass(r.variation_order)}">${cip_signed(r.variation_order)}</span></td>
        <td class="cip-num"><strong>${cip_money(r.contract_cost)}</strong></td>
        <td style="white-space:nowrap;">${cip_bar(r.accomplishment)}</td>
        <td class="cip-num">${cip_money(r.amount_paid)}</td>
        <td style="white-space:nowrap;">${cip_bar(r.percent_completion)}</td>
        <td class="cip-num">${cip_money(r.unbilled_amount)}</td>
        <td style="white-space:nowrap;">
            <div class="cip-menu">
                <button type="button" class="cip-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="cip_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="cip-menu-drop">
                    <button type="button" class="cip-menu-item" onclick="cip_closeMenus();cip_view(${i})">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </button>
                    ${Number(r.accomplishment) >= 90 || r.transfer_jev_no ? `<div class="cip-menu-divider"></div>
                    <button type="button" class="cip-menu-item" onclick="cip_closeMenus();cip_transfer(${i})" title="${r.transfer_jev_no ? (r.transfer_approved ? 'Transferred as ' : 'Waiting for the approval of ') + cip_esc(r.transfer_jev_no) : 'Physical accomplishment reached 90%'}">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4-4m-4 4l4 4"/></svg>
                        ${r.transfer_jev_no ? (r.transfer_approved ? 'Transferred — ' : 'For approval — ') + cip_esc(r.transfer_jev_no) : 'Transfer to Fixed Assets'}
                    </button>` : ''}
                </div>
            </div>
        </td>
    </tr>`).join('');
}

function cip_pagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = CIP_PAGE_SIZE } = p;
    cip_$('cip-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    cip_$('cip-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    cip_$('cip-total').textContent = total;
    const box = cip_$('cip-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="cip_goTo(${i})">${i}</button>`);
    }
    box.innerHTML = `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="cip_goTo(${current_page - 1})">‹</button>` + pages.join('')
        + `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="cip_goTo(${current_page + 1})">›</button>`;
}
function cip_goTo(p) { if (p < 1) return; cip_page = p; cip_load(); }

/* ── Actions menu ── */
function cip_toggleMenu(btn) {
    const drop = btn.closest('.cip-menu').querySelector('.cip-menu-drop');
    const isOpen = drop.classList.contains('open');
    cip_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function cip_closeMenus() {
    document.querySelectorAll('.cip-menu-drop.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.cip-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('.cip-menu')) cip_closeMenus(); });
window.addEventListener('scroll', cip_closeMenus, true);

/* ── View window ── */
function cip_view(i) {
    const r = cip_rows[i];
    if (!r) return;
    const kv = (k, v) => `<div><div class="cip-k">${k}</div><div class="cip-v">${v}</div></div>`;
    const items = r.items || [];
    cip_$('cip-view-title').textContent = 'Construction in Progress — ' + (r.project_name || r.swa_number || '');
    cip_$('cip-view-body').innerHTML = `
        <div class="cip-card"><h4>Project</h4><div class="cip-grid">
            ${kv('RFA No.', cip_dash(r.rfa_number))}${kv('SWA No.', cip_dash(r.swa_number))}
            ${kv('Book of Accounts', cip_dash(r.book_name))}${kv('Regional Office / District Office', cip_dash(r.requesting_office))}
            ${kv('Project Code', cip_dash(r.project_code))}${kv('Name of Project', cip_dash(r.project_name))}
            ${kv('Program', cip_dash(r.program_name))}${kv('Location', cip_dash(r.location))}
            ${kv('Contractor', cip_dash(r.contractor))}${kv('Contract', cip_dash(r.contract))}
            ${kv('Area in SQM / Units', r.total_land_area === null || r.total_land_area === undefined || r.total_land_area === '' ? '—' : cip_money(r.total_land_area))}${kv('TCT Number', cip_dash(r.tct_number))}
            ${kv('Category', cip_dash(r.category))}${kv('JEV', cip_dash(r.jev_no))}
        </div></div>
        <div class="cip-card"><h4>Contract Cost</h4><div class="cip-grid">
            ${kv('Original Contract Cost', cip_money(r.original_contract_cost))}
            ${kv('Variation Order', '<span class="cip-vo' + cip_voClass(r.variation_order) + '">' + cip_signed(r.variation_order) + '</span>')}
            ${kv('Revised Contract Cost', '<strong>' + cip_money(r.contract_cost) + '</strong>')}
            ${kv('Physical Accomplishment', cip_bar(r.accomplishment))}${kv('Amount Paid', cip_money(r.amount_paid))}
            ${kv('Percentage of Completion', cip_bar(r.percent_completion))}${kv('Unbilled Amount', cip_money(r.unbilled_amount))}
            ${kv('Date Started', cip_dash(r.date_started))}${kv('Completion Date (Revised)', cip_dash(r.completion_date_revised))}
            ${kv('Contract Duration', cip_dash(r.contract_duration))}
        </div></div>
        <div class="cip-card"><h4>Statement of Work Accomplishments</h4>
            <div class="nha-tbl-scroll"><table class="nha-tbl"><thead><tr><th>Description</th><th style="text-align:right;">Cost</th><th style="text-align:right;">% Accomplishment</th></tr></thead>
            <tbody>${items.map(it => `<tr><td style="white-space:pre-wrap;">${cip_esc(it.description)}</td><td class="cip-num">${cip_money(it.cost)}</td><td class="cip-num">${cip_pct(it.accomplishment_pct)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td style="text-align:right;">Total</td><td class="cip-num">${cip_money(r.items_cost)}</td><td class="cip-num">${cip_pct(r.accomplishment)}</td></tr></tfoot></table></div>
        </div>`;
    cip_$('cip-overlay').classList.add('open');
}
function cip_closeView() { cip_$('cip-overlay').classList.remove('open'); }

/* ── Actions → Transfer to Fixed Assets: the reclassification pro-forma JEV (offered once the Physical Accomplishment reaches 90%) ──
   Original entry (read-only): Debit the CIP account, Credit Accounts Payable. Transfer entry: Debit the NEW fixed-asset account the user chooses
   (Buildings, Leased Asset Improvements, Land, Other Structures - Properties Held for Rent Building, Other Land Improvements - Housing & Community Dev't Administrative Site, Investment Property - Land / Buildings), Credit the old CIP account. The book comes from the original JEV. */
let cip_tr = null;
const cip_trAmt = () => parseFloat(String(cip_$('cip-tr-amount').value).replace(/,/g, '')) || 0;
function cip_trParticulars() {
    const d = cip_tr.data, code = cip_tr.code;
    const t = (d.debit_accounts.find(a => a.account_code === code) || {}).account_title || '';
    // shown from the start (with the new account still to be chosen) and rewritten as soon as one is selected
    return 'To transfer Proj.Devt.cost of ' + (d.project_name || d.swa_number) + ' from ' + d.old_account.account_title + ' to ' + (t || '[select the new account]') + ' account';
}
async function cip_transfer(i) {
    const r = cip_rows[i];
    if (!r) return;
    // an already transferred project goes to the Fixed Assets page (the account it was transferred to is one of its tabs), on that tab, filtered to this
    // project, with its View window open. A transfer to an account that has no tab there (Investment Property) opens the read-only pro-forma instead.
    const faTabs = ['1-06-04-010', '1-06-09-020', '1-06-01-010', '1-06-04-990-03', '1-06-02-990-02'];
    const mplcTab = ['1-06-03-050', '1-06-04-020', '1-06-04-030', '1-06-04-040'];   // an MPLC-book transfer lands on the MPLC tab
    if (r.transfer_approved && (faTabs.includes(String(r.transfer_account || '')) || mplcTab.includes(String(r.transfer_account || '')))) {
        window.location.href = window.location.pathname.replace(/[^/]*$/, '') + 'FixedAssetsPage?tab=' + encodeURIComponent(mplcTab.includes(String(r.transfer_account)) ? 'MPLC' : r.transfer_account) + (mplcTab.includes(String(r.transfer_account)) ? '&sub=' + encodeURIComponent(r.transfer_account) : '') + '&q=' + encodeURIComponent(r.swa_number) + '&open=' + encodeURIComponent(r.swa_number);
        return;
    }
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${CIP_API}/transfer-proforma?id=${encodeURIComponent(r.swa_id)}&kind=fa`, { credentials: 'same-origin', headers: cip_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the pro-forma.');
        cip_trOpen(json.data);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
function cip_trOpen(d) {
    cip_tr = { data: d, edited: false, code: d.transfer ? d.transfer.account_code : '' };
    const done = !!d.transfer, f = cip_money;
    cip_$('cip-tr-body').innerHTML = `
        <div id="cip-tr-msg" class="cip-tr-msg" hidden></div>
        <div class="cip-card"><h4>Project</h4><div class="cip-grid">
            <div><div class="cip-k">SWA No. / RFA No.</div><div class="cip-v">${cip_esc(d.swa_number)} / ${cip_dash(d.rfa_number)}</div></div>
            <div><div class="cip-k">Name of Project</div><div class="cip-v">${cip_dash(d.project_name)}</div></div>
            <div><div class="cip-k">Physical Accomplishment</div><div class="cip-v">${cip_bar(d.percent_completion)}</div></div>
        </div></div>
        <div class="cip-card">
            <div class="cip-pf-title">Pro-forma JEV — reclassification / transfer</div>
            ${done ? `<div class="cip-tr-done">Already submitted as Journal Entry Voucher <strong>${cip_esc(d.transfer.jev_no)}</strong>.</div>` : ''}
            <div class="cip-pf-field"><label class="cip-pf-lbl">Book <span class="req">*</span></label>
                <select class="cip-pf-input" disabled tabindex="-1"><option>${cip_esc(d.book_name || '')}</option></select>
                ${d.mplc ? '<div style="margin-top:6px;font-size:12px;color:var(--afims-text-secondary,#5B5E73);">MPLC book: only the MPLC accounts can be chosen (Power Supply Systems, School Buildings, Hospitals and Health Centers, Markets).</div>' : ''}</div>
            <div class="cip-pf-field"><label class="cip-pf-lbl" for="cip-tr-part">Particulars <span class="opt">(optional)</span></label>
                <textarea id="cip-tr-part" class="cip-pf-input" rows="3" style="resize:vertical;"></textarea></div>
            <div class="cip-pf-tbl">
                <div class="cip-pf-row cip-pf-head"><div>Account Code - Account Name</div><div>Debit (₱)</div><div>Credit (₱)</div></div>
                <div class="cip-pf-row">
                    <div><div class="cip-pf-combo" id="cip-tr-combo">
                        <input type="text" id="cip-tr-debit" class="cip-pf-input" placeholder="Select the new account (Debit)" autocomplete="off" readonly style="cursor:pointer;">
                        <div class="cip-pf-list">${d.debit_accounts.map(a => `<div class="cip-pf-opt" data-code="${cip_esc(a.account_code)}">${cip_esc(a.account_code)} - ${cip_esc(a.account_title)}</div>`).join('')}</div>
                    </div></div>
                    <div class="cip-pf-money"><span>₱</span><input type="text" inputmode="decimal" id="cip-tr-amount" class="cip-pf-input" value="${f(d.amount)}"></div>
                    <div class="cip-pf-money"><span></span><input type="text" class="cip-pf-input" value="0.00" readonly tabindex="-1"></div>
                </div>
                <div class="cip-pf-row">
                    <div><input type="text" class="cip-pf-input" value="${cip_esc(d.old_account.account_code)} - ${cip_esc(d.old_account.account_title)}" readonly tabindex="-1"></div>
                    <div class="cip-pf-money"><span></span><input type="text" class="cip-pf-input" value="0.00" readonly tabindex="-1"></div>
                    <div class="cip-pf-money"><span>₱</span><input type="text" id="cip-tr-credit" class="cip-pf-input" value="${f(d.amount)}" readonly tabindex="-1"></div>
                </div>
                <div class="cip-pf-row cip-pf-total"><div class="lbl">Total</div>
                    <div class="cip-pf-money"><span>₱</span><b id="cip-tr-td">${f(d.amount)}</b></div><div class="cip-pf-money"><span>₱</span><b id="cip-tr-tc">${f(d.amount)}</b></div></div>
            </div>
            <div class="cip-pf-actions">
                <button type="button" class="nha-btn-act" style="margin-right:8px;" id="cip-tr-print" title="Print this pro-forma JEV (the rows as shown)">Print JEV</button>
                <button type="button" class="cip-pf-btn" id="cip-tr-save">Submit</button>
            </div>
        </div>`;
    cip_$('cip-tr-print').addEventListener('click', cip_trPrint);
    cip_$('cip-tr-save').addEventListener('click', cip_trSubmit);
    const combo = cip_$('cip-tr-combo'), dbt = cip_$('cip-tr-debit'), amt = cip_$('cip-tr-amount'), part = cip_$('cip-tr-part'), save = cip_$('cip-tr-save');
    const titleOf = c => { const a = d.debit_accounts.find(x => x.account_code === c); return a ? a.account_code + ' - ' + a.account_title : ''; };
    const sync = () => {
        const n = cip_trAmt();
        dbt.value = titleOf(cip_tr.code);
        cip_$('cip-tr-credit').value = cip_money(n); cip_$('cip-tr-td').textContent = cip_money(n); cip_$('cip-tr-tc').textContent = cip_money(n);
        if (!cip_tr.edited) part.value = cip_trParticulars();
        save.disabled = done || !d.eligible || !cip_tr.code || n <= 0;
    };
    // the new account is chosen from the four accounts; the Credit (old CIP account) is loaded automatically
    dbt.addEventListener('click', () => { if (!done) combo.classList.toggle('open'); });
    dbt.addEventListener('blur', () => setTimeout(() => combo.classList.remove('open'), 120));
    combo.querySelectorAll('.cip-pf-opt').forEach(o => {
        if (o.dataset.code === cip_tr.code) o.classList.add('active');
        o.addEventListener('mousedown', e => {
            e.preventDefault();
            cip_tr.code = o.dataset.code;
            combo.querySelectorAll('.cip-pf-opt').forEach(x => x.classList.toggle('active', x === o));
            combo.classList.remove('open'); sync();
        });
    });
    amt.addEventListener('input', () => { amt.value = amt.value.replace(/[^0-9.,]/g, ''); sync(); });
    amt.addEventListener('blur', () => { if (amt.value) amt.value = cip_money(cip_trAmt()); sync(); });
    part.addEventListener('input', () => { cip_tr.edited = true; });
    if (done) { amt.readOnly = true; part.readOnly = true; amt.value = cip_money(d.transfer.amount || d.amount); }
    save.hidden = done;
    sync();
    cip_$('cip-tr-overlay').classList.add('open');
    if (!done) setTimeout(() => { combo.classList.add('open'); dbt.scrollIntoView({ block: 'center' }); dbt.focus(); }, 0);   // the choice of the new account opens by itself
}
function cip_trMsg(text, ok) {
    const m = cip_$('cip-tr-msg');
    m.hidden = !text; m.textContent = text || ''; m.className = 'cip-tr-msg' + (ok ? ' ok' : '');
    cip_$('cip-tr-body').scrollTop = 0;
}
async function cip_trSubmit() {
    const d = cip_tr.data, btn = cip_$('cip-tr-save');
    btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${CIP_API}/transfer-submit`, {
            method: 'POST', credentials: 'same-origin', headers: cip_authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ kind: 'fa', id: d.id, debit_account: cip_tr.code, amount: cip_trAmt(), particulars: cip_$('cip-tr-part').value.trim() }),
        });
        const json = await res.json();
        if (!json.success) throw new Error((json.errors && json.errors.length ? json.errors.join(' ') : '') || json.message || 'Failed to submit the transfer.');
        cip_trMsg(json.message || 'Transfer submitted.', true);
        cip_$('cip-tr-combo').style.pointerEvents = 'none'; cip_$('cip-tr-amount').readOnly = true; cip_$('cip-tr-part').readOnly = true;
        btn.hidden = true;
        cip_load(); cip_loadCards();
    } catch (e) {
        cip_trMsg(e.message, false);
        btn.disabled = false;
    } finally {
        window.PagePreloader?.hide();
    }
}
function cip_trClose() { cip_$('cip-tr-overlay').classList.remove('open'); }

/* Print JEV → the shared print preview (prp_open + prp_jevFromProforma, as in the IAR module): the rows as shown, the Particulars as typed;
   once submitted it carries the real JEV number and date. The pro-forma window STAYS open behind the preview (it is not closed), so closing the preview returns to it. */
function cip_trPrint() {
    const d = cip_tr.data, done = !!d.transfer, code = done ? d.transfer.account_code : cip_tr.code;
    const acct = (d.debit_accounts.find(a => a.account_code === code) || {});
    const n = done ? (parseFloat(d.transfer.amount) || cip_trAmt()) : cip_trAmt();
    const rc = {
        rfa_number: d.rfa_number || '',
        draft: {
            rows: [
                { code: code || '', title: acct.account_title || '', debit: n, credit: 0 },
                { code: d.old_account.account_code, title: d.old_account.account_title, debit: 0, credit: n },
            ],
            particulars: cip_$('cip-tr-part').value.trim(),
        },
        print_particulars: 'Pro-forma JEV — Transfer to Fixed Assets of Infrastructure SWA No. ' + d.swa_number + ' of RFA No. ' + d.rfa_number,
        ...(done ? { jev_no: d.transfer.jev_no, jev_date: d.transfer.jev_date || '' } : {}),
    };
    prp_open(prp_jevFromProforma(d.rfa_number, rc), { type: 'JEV' });
}
/* ── wiring ── */
(function () {
    const run = () => {
        cip_$('cip-refresh').addEventListener('click', () => { cip_load(); cip_loadCards(); });
        const search = cip_$('cip-filter-search'), clear = cip_$('cip-search-clear');
        search.addEventListener('input', () => {
            clear.hidden = !search.value;
            clearTimeout(cip_timer);
            cip_timer = setTimeout(() => { cip_page = 1; cip_load(); }, 300);
        });
        clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; cip_page = 1; cip_load(); });
        ['cip-view-close', 'cip-view-close2'].forEach(id => cip_$(id).addEventListener('click', cip_closeView));
        cip_$('cip-overlay').addEventListener('click', e => { if (e.target === cip_$('cip-overlay')) cip_closeView(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') cip_closeView(); });
        ['cip-tr-close', 'cip-tr-cancel'].forEach(id => cip_$(id).addEventListener('click', cip_trClose));
        document.querySelectorAll('.cip-card-btn').forEach(c => {
            c.addEventListener('click', () => cip_selectStatus(c.dataset.status));
            c.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cip_selectStatus(c.dataset.status); } });
        });
        cip_markCard();
        cip_fcBuild();
        cip_loadFilterOptions();
        cip_loadTabs();                                      // opens the first tab, which loads the list
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
<?php
// Print JEV (Transfer to Fixed Assets) -> the shared print preview (prp_open + prp_jevFromProforma), as in the IAR module.
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
