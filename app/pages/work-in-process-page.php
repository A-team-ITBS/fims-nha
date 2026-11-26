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
#wip-root .wip-num { text-align:right;white-space:nowrap; }
.wip-bar { display:inline-block;vertical-align:middle;width:84px;height:8px;margin-right:8px;border-radius:4px;overflow:hidden;background:var(--afims-border, #E4ECF2); }
.wip-bar > i { display:block;height:100%;background:var(--afims-primary, #0035A9); }
.wip-bar.done > i { background:#16A34A; }
.wip-vo.pos { color:var(--afims-success, #1B7F3B);font-weight:600; } .wip-vo.neg { color:var(--afims-error, #C62828);font-weight:600; }
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
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="wip-tbody">
                    <tr><td colspan="11"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
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

</div><!-- /#wip-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   WORK IN PROCESS   prefix: wip_   (GET /infra-iar/work-in-process)
═══════════════════════════════════════════════════════════════ */
const WIP_API = '<?php echo $_wip_api; ?>' + '/infra-iar';
const WIP_PAGE_SIZE = 10;
let wip_page = 1, wip_rows = [], wip_timer = null;

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

async function wip_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ search: wip_$('wip-filter-search').value || '', page: wip_page, limit: WIP_PAGE_SIZE });
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
        tbody.innerHTML = '<tr><td colspan="11"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">' + wip_esc(error || 'No work in process found. A project shows here once its pro-forma JEV (with account 1-04-03-020) was submitted.') + '</span></div></td></tr>';
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
        </div></div>
        <div class="wip-card"><h4>Contract Cost</h4><div class="wip-grid">
            ${kv('Original Contract Cost', wip_money(r.original_contract_cost))}
            ${kv('Variation Order', '<span class="wip-vo' + wip_voClass(r.variation_order) + '">' + wip_signed(r.variation_order) + '</span>')}
            ${kv('Revised Contract Cost', '<strong>' + wip_money(r.contract_cost) + '</strong>')}
            ${kv('Physical Accomplishment', wip_bar(r.accomplishment))}
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

/* ── wiring ── */
(function () {
    const run = () => {
        wip_$('wip-refresh').addEventListener('click', () => wip_load());
        const search = wip_$('wip-filter-search'), clear = wip_$('wip-search-clear');
        search.addEventListener('input', () => {
            clear.hidden = !search.value;
            clearTimeout(wip_timer);
            wip_timer = setTimeout(() => { wip_page = 1; wip_load(); }, 300);
        });
        clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; wip_page = 1; wip_load(); });
        ['wip-view-close', 'wip-view-close2'].forEach(id => wip_$(id).addEventListener('click', wip_closeView));
        wip_$('wip-overlay').addEventListener('click', e => { if (e.target === wip_$('wip-overlay')) wip_closeView(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') wip_closeView(); });
        wip_load();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();
</script>
