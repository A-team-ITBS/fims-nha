<?php

namespace PHPMaker2024\FIMS;

/**
 * program-page.php — "Programs": the programs (fims.program) and their sub-programs (fims.sub_program) on ONE page, as a tree table in
 * the same shape as saro-registry-page.php.
 *
 * Each program is a parent row that expands into its sub-programs (same columns, indented); a program with no sub-program has nothing to
 * expand. The columns are Program Code · Name · Sub-Programs (how many) · Actions. There is no "Add Program" button — like the SARO
 * register, the "+" buttons do it:
 *   the "+" in the Program Code header   opens the Add Program form (form/program-form.php: the next free code is suggested),
 *   the "+" beside a program's count     opens the Add Sub-Program form (form/sub-program-form.php) with THAT program already picked and
 *                                         the next sub-program code suggested; the program then opens to show what was added.
 * A program that has sub-programs has no Delete (the API refuses it too: sub_program.program_code references it) — delete its
 * sub-programs first. A program a project still uses is refused by the API as before.
 *
 * Two summary cards: Total Programs (the programs the search matches) and Total Sub-Programs (the sub-programs under all of them — the API's
 * totals.sub_programs, so it is right across pages).
 *
 * Search looks at the program's code and name AND at its sub-programs' codes and names; a program found through a sub-program opens by
 * itself so the match is in view.
 *
 * Data comes from the FIMS API only:
 *   GET /program/list?with_sub_programs=1 (programs, each with sub_programs[])   GET /program/all (the Program choices of the sub-program form)
 *   /program/read|add|update|delete   /sub_program/read|add-with-items|update|delete|next-code
 * The Sub-Programs menu page (sub-program-page.php) still exists on its own; its form is the same one, driven from here by the spf_ functions
 * below (the page that owns a form owns its behaviour — sub-program-form.php is markup and styling only).
 */
@session_start();
$_prg_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #prg-root { --prg-line: color-mix(in srgb, var(--afims-success, #11A752) 50%, transparent); }

    /* ── Tree table: a program is the strong parent row, its sub-programs the recessed rows below it ── */
    #prg-root .prg-tbl { min-width: 620px; }
    #prg-root .prg-num { text-align: right; white-space: nowrap; }
    #prg-root .prg-empty { text-align: center; color: var(--afims-text-disabled, #A9AAB8); padding: 32px; font-size: 13px; }
    #prg-root .prg-actions { white-space: nowrap; }

    /* Each row sets --prg-row-bg; its cells paint it. */
    #prg-root .prg-parent { --prg-row-bg: var(--afims-surface, #FFFFFF); }
    #prg-root .prg-parent > td, #prg-root .prg-child > td { background: var(--prg-row-bg); }
    #prg-root .prg-parent.has-kids { cursor: pointer; }
    #prg-root .prg-parent:hover { --prg-row-bg: var(--afims-surface-subtle, #F4F7FA); }

    /* parent: the STRONG row — bolder type; opened, a deeper blue band, a blue left rail and a stem down to its sub-programs */
    #prg-root .prg-parent > td:first-child { vertical-align: top; white-space: nowrap; }
    #prg-root .prg-parent .prg-code { font-size: 13px; font-weight: 700; }
    #prg-root .prg-parent .prg-name { font-weight: 700; }
    #prg-root .prg-parent.expanded { --prg-row-bg: color-mix(in srgb, var(--afims-primary-soft, #E6EBF6) 90%, var(--afims-primary, #0035A9)); }
    #prg-root .prg-parent.expanded > td { border-bottom: none; }
    #prg-root .prg-parent.expanded:hover { --prg-row-bg: color-mix(in srgb, var(--afims-primary-soft, #E6EBF6) 80%, var(--afims-primary, #0035A9)); }
    #prg-root .prg-parent.expanded > td:first-child { position: relative; box-shadow: inset 4px 0 0 var(--afims-primary, #0035A9); }
    #prg-root .prg-parent.expanded > td:first-child::after { content: ''; position: absolute; left: 26px; top: 34px; bottom: -1px; width: 2px; background: var(--prg-line); }

    #prg-root .prg-ref-line { display: flex; align-items: center; }
    #prg-root .prg-caret { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 22px; height: 22px; margin-right: 6px;
        border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; background: var(--afims-surface, #FFFFFF); color: var(--afims-heading, #000E3E);
        transition: background .15s, color .15s, border-color .15s; }
    #prg-root .prg-caret svg { width: 12px; height: 12px; transition: transform .15s; }
    #prg-root .prg-caret.none { visibility: hidden; }
    #prg-root .prg-parent.has-kids:hover .prg-caret { border-color: var(--afims-heading, #000E3E); }
    #prg-root .prg-parent.expanded .prg-caret { background: var(--afims-heading-fill, #000E3E); border-color: var(--afims-primary, #0035A9); color: var(--afims-pure-white, #FFFFFF); }
    #prg-root .prg-parent.expanded .prg-caret svg { transform: rotate(90deg); }

    /* children: the LIGHTER rows — smaller type, a green rail, a tree connector (│ └) from the parent's chevron, dashed row lines and a
       solid closing line after the last one */
    #prg-root .prg-child { --prg-row-bg: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 14%, var(--afims-surface, #FFFFFF)); }
    #prg-root .prg-child > td { border-bottom: 1px dashed var(--afims-border, #E4ECF2); font-size: 12px; }
    #prg-root .prg-child:hover { --prg-row-bg: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 34%, var(--afims-surface, #FFFFFF)); }
    #prg-root .prg-child.last > td { border-bottom: 2px solid var(--afims-border-strong, #C5CEDA); }
    #prg-root .prg-child > td:first-child { position: relative; white-space: nowrap; box-shadow: inset 3px 0 0 var(--afims-success, #11A752); }
    #prg-root .prg-child > td:first-child::before { content: ''; position: absolute; left: 26px; top: 0; bottom: -1px; width: 2px; background: var(--prg-line); }
    #prg-root .prg-child.last > td:first-child::before { bottom: 50%; }
    #prg-root .prg-child > td:first-child::after { content: ''; position: absolute; left: 26px; top: 50%; width: 16px; height: 2px; background: var(--prg-line); }
    #prg-root .prg-child-ref { padding-left: 40px; }
    #prg-root .prg-child .prg-code { font-size: 12px; font-weight: 600; }

    /* the "+" in the Program Code header: a new program. The header is navy, so it is an outlined light circle that fills on hover. */
    #prg-root .prg-th-add { display: inline-flex; align-items: center; gap: 8px; }
    #prg-root .prg-th-plus { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 20px; height: 20px; padding: 0;
        border-radius: 50%; cursor: pointer; border: 1px solid rgba(255, 255, 255, .75); background: transparent; color: var(--afims-pure-white, #FFFFFF);
        transition: background .15s, color .15s, box-shadow .15s; }
    #prg-root .prg-th-plus svg { width: 11px; height: 11px; }
    #prg-root .prg-th-plus:hover, #prg-root .prg-th-plus:focus-visible { background: var(--afims-pure-white, #FFFFFF); color: var(--afims-heading-fill, #000E3E); outline: none;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, .28); }

    /* the "+" beside a program's sub-program count: adds a sub-program under that program */
    #prg-root .prg-sub-cell { display: inline-flex; align-items: center; justify-content: flex-end; gap: 8px; }
    #prg-root .prg-count { font-family: 'IBM Plex Mono', monospace; }
    #prg-root .prg-add-sub { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 22px; height: 22px; padding: 0;
        border-radius: 50%; cursor: pointer; border: 1px solid var(--afims-success, #11A752); background: var(--afims-surface, #FFFFFF);
        color: var(--afims-success, #11A752); transition: background .15s, color .15s, box-shadow .15s; }
    #prg-root .prg-add-sub svg { width: 12px; height: 12px; }
    #prg-root .prg-add-sub:hover, #prg-root .prg-add-sub:focus-visible {
        background: var(--afims-success, #11A752); color: var(--afims-pure-white, #FFFFFF); outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-success, #11A752) 22%, transparent); }

    /* ── Page header: copied from User Access (.app-page-header in app/fims-css/fims.css — that file is not loaded here,
          so the rules are repeated, scoped to this page). Stays navy in dark mode too, like User Access. ── */
    #prg-root .app-page-header { position:relative;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;overflow:hidden;
        background:linear-gradient(135deg, #000E3E 0%, #001A5E 100%);border-radius:.75rem;padding:1.5rem 1.75rem;margin-bottom:1.5rem; }
    #prg-root .app-page-header::before,
    #prg-root .app-page-header::after { content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none; }
    #prg-root .app-page-header::before { width:180px;height:180px;top:-90px;right:80px; }
    #prg-root .app-page-header::after { width:120px;height:120px;bottom:-70px;right:-20px;background:rgba(255,255,255,.06); }
    #prg-root .app-page-header-icon { position:relative;z-index:1;flex:0 0 auto;width:3rem;height:3rem;border-radius:.5rem;
        display:flex;align-items:center;justify-content:center;font-size:1.25rem;
        background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#FFFFFF; }
    #prg-root .app-page-title { position:relative;z-index:1;font-size:1.6rem;font-weight:800;letter-spacing:-.01em;color:#FFFFFF;margin:0 0 .25rem; }
    #prg-root .app-page-subtitle { position:relative;z-index:1;font-size:.9rem;color:rgba(255,255,255,.75);margin:0;max-width:640px; }
    /* the Refresh button stays on the right of the banner, above its decorative circles */
    #prg-root .app-page-header-actions { position:relative;z-index:1;margin-left:auto;display:flex;flex-wrap:wrap;gap:8px; }
</style>

<div id="prg-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in app/fims-css/fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Programs</h1>
            <p class="app-page-subtitle">Programs and their sub-programs</p>
        </div>
        <div class="app-page-header-actions">
            <button id="prg-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Programs</div>
                    <div class="nha-sc-val" id="prg-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Matches the search</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Sub-Programs</div>
                    <div class="nha-sc-val" id="prg-kpi-subs">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Under the matching programs</p>
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
                    <input type="text" id="prg-filter-search" class="nha-filter-input" placeholder="Search program or sub-program code, name…">
                    <button class="nha-filter-clear-btn" id="prg-search-clear" hidden type="button">×</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl prg-tbl">
                <thead>
                    <tr>
                        <th style="min-width:200px;">
                            <span class="prg-th-add">Program Code
                                <!-- icon only: a new program (prg_openModal('add')) -->
                                <button type="button" id="prg-add-program" class="prg-th-plus no-print" title="New Program" aria-label="New Program"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg></button>
                            </span>
                        </th>
                        <th>Name</th>
                        <th style="text-align:right;" title="How many sub-programs the program has — the + adds one">Sub-Programs</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="prg-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap no-print">
            <span class="nha-pag-info">Showing <strong><span id="prg-from">0</span>–<span id="prg-to">0</span></strong> of <strong><span id="prg-total">0</span></strong> programs — click a row with a chevron to expand its sub-programs</span>
            <div id="prg-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/program-form.php'; ?>
<?php include __DIR__ . '/form/sub-program-form.php'; ?>

</div><!-- /#prg-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PROGRAMS + SUB-PROGRAMS   prefix: prg_ (the page and the program form) · spf_ (the sub-program form)
═══════════════════════════════════════════════════════════════ */

const PRG_BASE    = <?= json_encode($_prg_api) ?>;
const PRG_API     = PRG_BASE + '/program';
const PRG_SUB_API = PRG_BASE + '/sub_program';
const PRG_PAGE_SIZE = 15;

const $prg = id => document.getElementById(id);
const prg_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ── State ──────────────────────────────────────────────────── */
const prg = {
    page: 1, limit: PRG_PAGE_SIZE,
    total: 0,                 // programs matching the search
    subTotal: null,           // sub-programs under ALL of those programs (the API's totals.sub_programs); null = not known
    rows: [],                 // this page's programs, each with its sub_programs[]
    loaded: false, error: null,
    req: 0,                   // bumped per request so a stale response never overwrites a newer one
    expanded: new Set(),      // codes of the programs currently open
    programs: [],             // every program (GET /program/all): the Program choices of the sub-program form
};
let prg_programsReady = Promise.resolve();   // the Program choices have been loaded (the sub-program form waits for them)

/* ── API ────────────────────────────────────────────────────── */
// fetch + JSON; an error status still reads the body, so the server's own message is what the user sees.
async function prg_api(url, opts = {}) {
    const res  = await fetch(url, { credentials: 'same-origin', ...opts, headers: prg_authHeaders(opts.headers || {}) });
    const json = await res.json().catch(() => null);
    if (!res.ok || !json || !json.success) throw new Error((json && json.message) || 'HTTP ' + res.status);
    return json;
}
const PRG_JSON = { 'Content-Type': 'application/json' };

/* ── Boot ───────────────────────────────────────────────────── */
async function prg_boot() {
    prg_bindEvents();
    prg_programsReady = prg_loadProgramOptions();
    await prg_load();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', prg_boot);
} else {
    prg_boot();
}

function prg_bindEvents() {
    $prg('prg-refresh')?.addEventListener('click', () => { prg.page = 1; prg_programsReady = prg_loadProgramOptions(); prg_load(); });
    $prg('prg-add-program')?.addEventListener('click', () => prg_openModal('add'));

    // the program form (form/program-form.php)
    $prg('prg-modal-close')?.addEventListener('click', prg_closeModal);
    $prg('prg-cancel')?.addEventListener('click', prg_closeModal);
    $prg('prg-form')?.addEventListener('submit', prg_save);
    // Program Name is always in capitals: what is typed or pasted is turned into capitals as it goes (the caret stays where it was)
    $prg('prg-program-name')?.addEventListener('input', e => {
        const el = e.target, from = el.selectionStart, to = el.selectionEnd;
        const up = el.value.toUpperCase();
        if (up !== el.value) { el.value = up; el.setSelectionRange(from, to); }
    });

    // the sub-program form (form/sub-program-form.php)
    $prg('sp-modal-close')?.addEventListener('click', spf_close);
    $prg('sp-cancel')?.addEventListener('click', spf_close);
    $prg('sp-form')?.addEventListener('submit', spf_save);
    $prg('sp-li-add')?.addEventListener('click', () => spf_addRow());
    $prg('sp-program-code')?.addEventListener('change', spf_refreshCodes);

    // one listener for every button and row of the table (a code never goes into an inline handler)
    $prg('prg-tbody')?.addEventListener('click', prg_onTableClick);

    let searchTimer;
    const searchEl = $prg('prg-filter-search');
    const clearBtn = $prg('prg-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { prg.page = 1; prg_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        prg.page = 1; prg_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function prg_load() {
    const req = ++prg.req;
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search: $prg('prg-filter-search')?.value.trim() || '',
            page: prg.page,
            limit: prg.limit,
            with_sub_programs: 1,
        });
        const json = await prg_api(`${PRG_API}/list?${params}`);
        if (req !== prg.req) return;   // a newer request is on its way

        prg.rows  = json.data ?? [];
        prg.total = Number(json.pagination?.total ?? prg.rows.length);
        // the whole search's sub-program count from the API; failing that, what the rows hold — right only when every program is on this page
        prg.subTotal = json.totals?.sub_programs != null ? Number(json.totals.sub_programs)
            : (prg.total <= prg.rows.length ? prg.rows.reduce((n, r) => n + prg_kids(r).length, 0) : null);
        prg.error = null;
        // the page just emptied (its last program was deleted): step back to the last page that has rows
        if (!prg.rows.length && prg.page > 1 && prg.total > 0) {
            prg.page = Math.ceil(prg.total / prg.limit);
            return prg_load();
        }
        prg_openMatches();
    } catch (e) {
        if (req !== prg.req) return;
        prg.rows = []; prg.total = 0; prg.subTotal = null; prg.error = e.message;
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
    if (req !== prg.req) return;
    prg.loaded = true;
    prg_renderTable();
}

// The Program choices of the sub-program form: every program, not just the page the table shows.
async function prg_loadProgramOptions() {
    try {
        const json = await prg_api(`${PRG_API}/all`);
        prg.programs = json.data ?? [];
    } catch (e) {
        prg_toast('Could not load the programs for the sub-program form: ' + e.message, 'warning');
    }
    const sel = $prg('sp-program-code');
    if (!sel) return;
    const keep = sel.value;
    sel.innerHTML = '<option value="">— Select program —</option>' + prg.programs.map(p =>
        `<option value="${prg_esc(p.program_code)}">${prg_esc(p.program_code)} — ${prg_esc(p.program_name)}</option>`).join('');
    if (keep) sel.value = keep;
}

// With a search on, a program that was found through one of its sub-programs opens by itself, so the match is in view.
function prg_openMatches() {
    const q = ($prg('prg-filter-search')?.value || '').trim().toLowerCase();
    if (!q) return;
    prg.rows.forEach(r => {
        if ((r.sub_programs || []).some(s => `${s.sub_program_code} ${s.sub_program_name}`.toLowerCase().includes(q))) prg.expanded.add(String(r.program_code));
    });
}

/* ── Tree table ──────────────────────────────────────────────────
   GET /program/list?with_sub_programs=1: page / limit count PROGRAMS, so paging walks programs and a program's sub-programs are never split
   across pages. A program with sub-programs opens and closes on a click of its row; its buttons (+, Edit, Delete) never do. */
const PRG_CARET = '<span class="prg-caret__CLS__" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>';
const PRG_PLUS  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>';
const PRG_EDIT_BTN = (act, code, program, label) => `<button type="button" class="nha-btn-act edit" data-act="${act}" data-code="${prg_esc(code)}"${program !== undefined ? ` data-program="${prg_esc(program)}"` : ''} title="Edit ${prg_esc(label)}">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>`;
const PRG_DEL_BTN = (act, code, program, label) => `<button type="button" class="nha-btn-act danger" data-act="${act}" data-code="${prg_esc(code)}"${program !== undefined ? ` data-program="${prg_esc(program)}"` : ''} title="Delete ${prg_esc(label)}">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>`;

const prg_kids = r => (Array.isArray(r.sub_programs) ? r.sub_programs : []);

// A program: caret · code · name · how many sub-programs (+ a button that adds one) · Edit / Delete.
// A program that has sub-programs has no Delete (the API refuses it): delete them first.
function prg_parentRow(r, expanded) {
    const kids = prg_kids(r);
    const has  = kids.length > 0;
    const code = String(r.program_code);
    const add  = `Add a sub-program under program ${code}`;
    return `
    <tr class="prg-parent${has ? ' has-kids' : ''}${expanded ? ' expanded' : ''}" data-code="${prg_esc(code)}"${has ? ` aria-expanded="${expanded ? 'true' : 'false'}"` : ''}>
        <td><div class="prg-ref-line">${PRG_CARET.replace('__CLS__', has ? '' : ' none')}<code class="nha-code prg-code">${prg_esc(code)}</code></div></td>
        <td><div class="prg-name">${prg_esc(r.program_name)}</div></td>
        <td class="prg-num"><span class="prg-sub-cell">${has ? `<span class="prg-count" title="${kids.length} sub-program${kids.length === 1 ? '' : 's'}">${kids.length}</span>` : '<span class="nha-td-sub">—</span>'}<button type="button" class="prg-add-sub" data-act="add-sub" data-code="${prg_esc(code)}" title="${prg_esc(add)}" aria-label="${prg_esc(add)}">${PRG_PLUS}</button></span></td>
        <td class="no-print prg-actions">
            ${PRG_EDIT_BTN('edit', code, undefined, 'program ' + code)}
            ${has ? '' : PRG_DEL_BTN('del', code, undefined, 'program ' + code)}
        </td>
    </tr>`;
}

// One sub-program under its program — a recessed row with a tree connector back to the parent's chevron.
function prg_childRow(s, isLast) {
    const code = String(s.sub_program_code), program = String(s.program_code);
    return `
    <tr class="prg-child${isLast ? ' last' : ''}" data-code="${prg_esc(code)}" data-program="${prg_esc(program)}">
        <td><div class="prg-child-ref"><code class="nha-code prg-code">${prg_esc(code)}</code></div></td>
        <td>${prg_esc(s.sub_program_name)}</td>
        <td class="prg-num"><span class="nha-td-sub">—</span></td>
        <td class="no-print prg-actions">
            ${PRG_EDIT_BTN('edit-sub', code, program, 'sub-program ' + code)}
            ${PRG_DEL_BTN('del-sub', code, program, 'sub-program ' + code)}
        </td>
    </tr>`;
}

function prg_renderTable() {
    const body = $prg('prg-tbody');
    if (prg.error) {
        body.innerHTML = `<tr><td colspan="4" class="prg-empty">Could not load programs: ${prg_esc(prg.error)}</td></tr>`;
    } else if (!prg.loaded) {
        body.innerHTML = '<tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    } else if (!prg.rows.length) {
        body.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No programs found.</span></div></td></tr>';
    } else {
        body.innerHTML = prg.rows.map(r => {
            const kids = prg_kids(r);
            const open = kids.length > 0 && prg.expanded.has(String(r.program_code));
            return prg_parentRow(r, open) + (open ? kids.map((s, i) => prg_childRow(s, i === kids.length - 1)).join('') : '');
        }).join('');
    }
    $prg('prg-kpi-total').textContent = prg.loaded && !prg.error ? prg.total : '—';
    $prg('prg-kpi-subs').textContent = prg.loaded && !prg.error && prg.subTotal !== null ? prg.subTotal : '—';
    prg_renderPager();
}

function prg_toggle(code) {
    code = String(code);
    if (prg.expanded.has(code)) prg.expanded.delete(code); else prg.expanded.add(code);
    prg_renderTable();
}

// A click anywhere in the table: a button does its own thing; any other click on a program that has sub-programs opens / closes it.
function prg_onTableClick(e) {
    const btn = e.target.closest('[data-act]');
    if (btn) {
        e.stopPropagation();
        prg_act(btn.dataset.act, btn.dataset.code, btn.dataset.program);
        return;
    }
    const row = e.target.closest('tr.prg-parent.has-kids');
    if (row) prg_toggle(row.dataset.code);
}

function prg_act(act, code, program) {
    switch (act) {
        case 'add-sub':  return spf_open('add', null, code);   // the program of the clicked row is already picked
        case 'edit':     return prg_edit(code);
        case 'del':      return prg_delete(code);
        case 'edit-sub': return spf_edit(code);
        case 'del-sub':  return spf_delete(code);
    }
}

/* ── Pagination ────────────────────────────────────────────── */
function prg_renderPager() {
    const total = prg.loaded && !prg.error ? prg.total : 0;
    const last  = Math.max(1, Math.ceil(total / prg.limit));
    const from  = total ? (prg.page - 1) * prg.limit + 1 : 0;
    const to    = total ? Math.min(from + prg.rows.length - 1, total) : 0;
    $prg('prg-from').textContent  = from;
    $prg('prg-to').textContent    = to;
    $prg('prg-total').textContent = total;

    const container = $prg('prg-pagination');
    if (last <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, prg.page - 2); i <= Math.min(last, prg.page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === prg.page ? ' active' : ''}" onclick="prg_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${prg.page === 1 ? ' disabled' : ''}" onclick="prg_goTo(${prg.page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${prg.page === last ? ' disabled' : ''}" onclick="prg_goTo(${prg.page + 1})">›</button>`;
}

function prg_goTo(page) {
    const last = Math.max(1, Math.ceil(prg.total / prg.limit));
    if (page < 1 || page > last) return;
    prg.page = page;
    prg_load();
}

/* ══════════════════════════════════════════════════════════════
   PROGRAM FORM (form/program-form.php)
═══════════════════════════════════════════════════════════════ */
function prg_openModal(mode, row = null) {
    prg_hideErr();
    $prg('prg-mode').value = mode;
    $prg('prg-modal-title').textContent = mode === 'edit' ? 'Edit Program' : 'Add Program';
    $prg('prg-program-code').value = row?.program_code || '';
    $prg('prg-program-code').disabled = true;   // add: auto-generated by GET /program/next-code; edit: locked
    $prg('prg-code-hint').textContent = mode === 'edit'
        ? 'Program codes cannot be changed after creation.'
        : 'Auto-generated next 3-digit code.';
    $prg('prg-program-name').value = String(row?.program_name || '').toUpperCase();   // always shown (and saved) in capitals
    $prg('prg-save').disabled = false;
    if (mode === 'add') prg_loadCodePreview();     // defined in form/program-form.php
    $prg('prg-overlay').classList.add('open');
}

function prg_closeModal() {
    $prg('prg-overlay').classList.remove('open');
    $prg('prg-form').reset();
    prg_hideErr();
}

function prg_showErr(msg) {
    const el = $prg('prg-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function prg_hideErr() {
    $prg('prg-err').style.display = 'none';
}

async function prg_edit(code) {
    window.PagePreloader?.show();
    try {
        const json = await prg_api(`${PRG_API}/read/${encodeURIComponent(code)}`);
        prg_openModal('edit', json.data);
    } catch (e) {
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function prg_save(ev) {
    ev.preventDefault();
    prg_hideErr();
    const mode = $prg('prg-mode').value;
    const code = $prg('prg-program-code').value.trim();
    const payload = {
        program_code : code,
        program_name : $prg('prg-program-name').value.trim().toUpperCase(),   // capitals, whatever got into the box
    };

    window.PagePreloader?.show();
    try {
        const url  = mode === 'edit' ? `${PRG_API}/update/${encodeURIComponent(code)}` : `${PRG_API}/add`;
        const json = await prg_api(url, {
            method: mode === 'edit' ? 'PUT' : 'POST',
            headers: PRG_JSON,   // + the JWT (prg_api) → the server knows whose RC code to use
            body: JSON.stringify(payload),
        });

        // On add, the server's message names the code it actually assigned ("Program 102 created successfully.").
        prg_toast(mode === 'edit' ? 'Program updated successfully.' : (json.message || 'Program created successfully.'), 'success');
        prg_closeModal();
        prg_programsReady = prg_loadProgramOptions();   // the sub-program form's Program choices
        prg_load();
    } catch (e) {
        prg_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function prg_delete(code) {
    if (!confirm(`Delete program '${code}'? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        await prg_api(`${PRG_API}/delete/${encodeURIComponent(code)}`, { method: 'DELETE' });
        prg_toast('Program deleted successfully.', 'success');
        prg.expanded.delete(String(code));
        prg_programsReady = prg_loadProgramOptions();
        prg_load();
    } catch (e) {
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ══════════════════════════════════════════════════════════════
   SUB-PROGRAM FORM (form/sub-program-form.php)
   Add: pick a Program (already picked when opened from a program's "+") and give it one or more sub-programs — saved together by
   /sub_program/add-with-items. Their codes are suggested as "<last 3 digits of the program>-<NNN>" (GET /sub_program/next-code).
   Edit: one existing sub-program (its code is locked; its program can be changed).
═══════════════════════════════════════════════════════════════ */
const $spf = id => document.getElementById(id);
let spf_seq = 0;
let spf_prefix = null;   // last 3 digits of the selected program code, e.g. "001"
let spf_next   = 1;      // next sub-program number for that prefix (from GET /sub_program/next-code)
let spf_req    = 0;      // guards against out-of-order responses when the program changes quickly

function spf_showErr(msg) {
    const el = $spf('sp-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function spf_hideErr() {
    $spf('sp-err').style.display = 'none';
}

function spf_addRow(values = null) {
    const id = ++spf_seq;
    // No explicit code -> the next suggested one (001-001, 001-002, …) once a program is picked.
    const autoCode = values ? '' : spf_takeCode();
    const row = document.createElement('div');
    row.className = 'sp-li-row';
    row.dataset.rowId = id;
    row.innerHTML = `
        <input type="text" class="sp-input sp-li-code" maxlength="20" placeholder="e.g. SUBPRG-001" value="${prg_esc(values?.sub_program_code || autoCode)}">
        <input type="text" class="sp-input sp-li-name" maxlength="255" placeholder="e.g. Core Shelter Assistance" value="${prg_esc(values?.sub_program_name || '')}">
        <button type="button" class="sp-li-rm" title="Remove"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    `;
    row.querySelector('.sp-li-rm').addEventListener('click', () => spf_removeRow(id));
    const codeInput = row.querySelector('.sp-li-code');
    if (autoCode) codeInput.dataset.auto = '1';
    codeInput.addEventListener('input', () => { delete codeInput.dataset.auto; });   // the user edited it: leave it alone
    $spf('sp-li-rows').appendChild(row);
    spf_updateRemoveState();
}

function spf_removeRow(id) {
    $spf('sp-li-rows').querySelector(`[data-row-id="${id}"]`)?.remove();
    spf_updateRemoveState();
}

function spf_updateRemoveState() {
    const rows = $spf('sp-li-rows').querySelectorAll('.sp-li-row');
    rows.forEach(row => { row.querySelector('.sp-li-rm').disabled = rows.length <= 1; });
}

function spf_clearRows() {
    $spf('sp-li-rows').innerHTML = '';
    spf_seq = 0;
    spf_prefix = null;
    spf_next   = 1;
    spf_req++;
}

function spf_takeCode() {
    if (spf_prefix === null) return '';
    return `${spf_prefix}-${String(spf_next++).padStart(3, '0')}`;
}

// A program was picked / changed: fetch where its sequence starts, then (re)number the rows still holding a suggested (or empty) code.
// A code the user typed is kept as it is.
async function spf_refreshCodes() {
    if ($spf('sp-mode').value !== 'add') return;
    const programCode = $spf('sp-program-code').value;
    const req = ++spf_req;
    spf_prefix = null;
    spf_next   = 1;

    $spf('sp-li-rows').querySelectorAll('.sp-li-code').forEach(inp => { if (inp.dataset.auto === '1') { inp.value = ''; delete inp.dataset.auto; } });
    if (!programCode) return;

    try {
        const json = await prg_api(`${PRG_SUB_API}/next-code?program_code=${encodeURIComponent(programCode)}`);
        if (req !== spf_req) return;   // the program changed again, or the form was closed
        spf_prefix = json.data.prefix;
        spf_next   = json.data.next;
        $spf('sp-li-rows').querySelectorAll('.sp-li-code').forEach(inp => {
            if (inp.value.trim() === '') { inp.value = spf_takeCode(); inp.dataset.auto = '1'; }
        });
    } catch (e) {
        if (req === spf_req) spf_showErr(e.message);
    }
}

// mode 'add': programCode (optional) is the program to start on — a program row's "+" passes its own.
// mode 'edit': row is the sub-program being edited.
async function spf_open(mode, row = null, programCode = '') {
    await prg_programsReady;   // the Program choices must be there for the picked one to show
    spf_hideErr();
    $spf('sp-mode').value = mode;
    $spf('sp-modal-title').textContent = mode === 'edit' ? 'Edit Sub-Program' : 'Add Sub-Program';
    $spf('sp-program-code').value = row?.program_code || programCode || '';

    if (mode === 'edit') {
        $spf('sp-edit-fields').style.display = '';
        $spf('sp-li-section').style.display = 'none';
        $spf('sp-sub-program-code').value = row?.sub_program_code || '';
        $spf('sp-sub-program-code').disabled = true;
        $spf('sp-sub-program-name').value = row?.sub_program_name || '';
    } else {
        $spf('sp-edit-fields').style.display = 'none';
        $spf('sp-li-section').style.display = '';
        spf_clearRows();
        spf_addRow();
        if ($spf('sp-program-code').value) spf_refreshCodes();   // a program is already picked: suggest its codes now
    }
    $spf('sp-overlay').classList.add('open');
}

function spf_close() {
    $spf('sp-overlay').classList.remove('open');
    $spf('sp-form').reset();
    spf_clearRows();
    spf_hideErr();
}

async function spf_edit(code) {
    window.PagePreloader?.show();
    try {
        const json = await prg_api(`${PRG_SUB_API}/read/${encodeURIComponent(code)}`);
        await spf_open('edit', json.data);
    } catch (e) {
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function spf_save(ev) {
    ev.preventDefault();
    spf_hideErr();
    const mode = $spf('sp-mode').value;
    const programCode = $spf('sp-program-code').value;

    window.PagePreloader?.show();
    try {
        let url, method, payload;

        if (mode === 'edit') {
            const code = $spf('sp-sub-program-code').value.trim();
            payload = {
                sub_program_name : $spf('sp-sub-program-name').value.trim(),
                program_code     : programCode,
            };
            url    = `${PRG_SUB_API}/update/${encodeURIComponent(code)}`;
            method = 'PUT';
        } else {
            const rows = Array.from($spf('sp-li-rows').querySelectorAll('.sp-li-row'));
            const subPrograms = rows.map(row => ({
                sub_program_code : row.querySelector('.sp-li-code').value.trim(),
                sub_program_name : row.querySelector('.sp-li-name').value.trim(),
            }));

            if (!programCode) throw new Error('Please select a program.');
            if (!subPrograms.length) throw new Error('Please add at least one sub-program.');
            for (const s of subPrograms) {
                if (!s.sub_program_code || !s.sub_program_name) throw new Error('Each line item needs both a code and a name.');
            }

            payload = { program_code: programCode, sub_programs: subPrograms };
            url    = `${PRG_SUB_API}/add-with-items`;
            method = 'POST';
        }

        await prg_api(url, { method, headers: PRG_JSON, body: JSON.stringify(payload) });

        prg_toast(`Sub-program ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        prg.expanded.add(String(programCode));   // open the program the change landed in, so it is in view
        spf_close();
        prg_load();
    } catch (e) {
        spf_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function spf_delete(code) {
    if (!confirm(`Delete sub-program '${code}'? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        await prg_api(`${PRG_SUB_API}/delete/${encodeURIComponent(code)}`, { method: 'DELETE' });
        prg_toast('Sub-program deleted successfully.', 'success');
        prg_load();
    } catch (e) {
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Toast ─────────────────────────────────────────────────── */
function prg_toast(msg, type = 'info') {
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
    // the message is escaped: a server message can quote what the user typed (a code, a name)
    t.innerHTML = `<span style="color:${color};flex-shrink:0;margin-top:1px">${icons[type] || icons.info}</span><div style="flex:1"><span>${prg_esc(msg)}</span></div><button onclick="this.parentElement.remove()" style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
