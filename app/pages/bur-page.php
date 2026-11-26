<?php

namespace PHPMaker2024\FIMS;

/**
 * bur-page.php — Budget Utilization Report (BUR).
 *
 * Personal Services (chart-of-accounts '5-01') and Maintenance and Other Operating Expenses ('5-02'),
 * rolled up from every APPROVED budget request's line items — GET /bur/rollup (BurService.php, routed
 * in app/routes/bur.php). Only chart-of-accounts codes that actually carry approved budget (directly or
 * through a descendant) come back — a code with none is filtered out server-side, never fetched by this
 * page. The two roots, "5-01 Personal Services" and "5-02 Maintenance and Other Operating Expenses",
 * come back with no parent of their own, so they render as the tree's top-level rows (same account_id /
 * account_code / account_title / parent_account_id shape chart-of-accounts-page.php's tree uses).
 *
 * Fiscal Year is the one filter (GET /bur/filters for its options), same pattern as cob-page.php: the
 * page opens on the newest year that has approved data.
 */
@session_start();
$_bur_api = Config('API.FIMS_URL');
?>

<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
#bur-root #bur-table { table-layout: fixed; }
#bur-root #bur-table .bur-name span { overflow-wrap: break-word; }

#bur-root .nha-summary { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 900px) { #bur-root .nha-summary { grid-template-columns: repeat(2, 1fr); } }

#bur-root .nha-tbl .text-right { text-align: right; }
#bur-root .bur-row-total > td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); border-top: 2px solid var(--afims-border, #E4ECF2); }
#bur-root .bur-name { display: flex; align-items: center; gap: 8px; }
#bur-root .bur-toggle {
    width: 20px; height: 20px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    border: none; background: none; cursor: pointer; padding: 0; border-radius: 4px;
    color: var(--afims-text-muted, #62657E); transition: background .15s, color .15s;
}
#bur-root .bur-toggle:hover { background: var(--afims-border, #E4ECF2); color: var(--afims-heading, #000E3E); }
#bur-root .bur-toggle svg { pointer-events: none; transition: transform .15s; }
#bur-root .bur-toggle-spacer { display: inline-block; width: 20px; flex-shrink: 0; }
#bur-root .bur-d0 > td { background: var(--afims-surface-subtle, #F4F7FA); font-weight: 700; }
#bur-root .bur-neg { color: #dc2626; }
</style>

<div id="bur-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Budget Utilization Report (BUR)</div>
                <p class="nha-hdr-sub">Personal Services and Maintenance and Other Operating Expenses — approved budget and how much of it has been used.</p>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="bur-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (the grand totals — the two roots' figures) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Approved Budget</div>
                <div class="nha-sc-val" id="bur-kpi-approved">—</div>
            </div></div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Realigned Budget</div>
                <div class="nha-sc-val" id="bur-kpi-realigned">—</div>
            </div></div>
            <p class="nha-kpi-sub">Approved Budget net of Increase (Decrease)</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Utilization</div>
                <div class="nha-sc-val" id="bur-kpi-utilization">—</div>
            </div></div>
            <p class="nha-kpi-sub">What approved RFAs / LAAs have drawn</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Balance</div>
                <div class="nha-sc-val" id="bur-kpi-balance">—</div>
            </div></div>
            <p class="nha-kpi-sub">Realigned Budget less what is held and drawn</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="bur-f-fy">Fiscal Year</label>
                <select id="bur-f-fy" class="nha-filter-select"><option value="">All Years</option></select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="bur-filter-search" class="nha-filter-input" placeholder="Search expense classification or account code…" autocomplete="off">
                    <button class="nha-filter-clear-btn" id="bur-search-clear" hidden type="button" aria-label="Clear search">×</button>
                </div>
            </div>
            <div class="nha-filter-group" style="margin-left:auto;">
                <label class="nha-filter-label">&nbsp;</label>
                <span style="display:flex;gap:6px;">
                    <button id="bur-expand-all" type="button" class="nha-btn nha-btn-outline nha-btn-sm">Expand All</button>
                    <button id="bur-collapse-all" type="button" class="nha-btn nha-btn-outline nha-btn-sm">Collapse All</button>
                </span>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl" id="bur-table">
                <thead>
                    <tr>
                        <th style="width:280px;">Expense Classification (NGAS)</th>
                        <th style="width:110px;">Acct. Code</th>
                        <th class="text-right">Approved Budget</th>
                        <th class="text-right">Realigned Budget</th>
                        <th class="text-right">Increase (Decrease)</th>
                        <th class="text-right">Utilization</th>
                        <th class="text-right">Balance</th>
                        <th class="text-right">Payment</th>
                        <th class="text-right">Accounts Payable</th>
                    </tr>
                </thead>
                <tbody id="bur-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /#bur-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET UTILIZATION REPORT (BUR)   prefix: bur_   (read-only)
   Backed by BurService → app/routes/bur.php:
     GET /bur/filters   the Fiscal Year drop-down's options (years that have approved PS / MOOE budget)
     GET /bur/rollup?fiscal_yr=   the report: one row per chart-of-accounts code that carries approved
                                  budget, plus the grand totals (the KPI cards) — fiscal_yr omitted = every year.

   Rows: a chart-of-accounts tree (account_id / account_code / account_title / parent_account_id, same
   shape chart-of-accounts-page.php uses), but only the codes that actually carry approved PS (5-01) /
   MOOE (5-02) budget — BurService filters that server-side, so a code with none is never in bur_rows.
   The seven amount columns per row (see BurService's class doc for what each means):
     approved_amount   -> Approved Budget         revision_amount -> Increase (Decrease)
     revised_amount    -> Realigned Budget         utilization     -> Utilization
     balance           -> Balance                 payment         -> Payment
     account_payable   -> Accounts Payable
═══════════════════════════════════════════════════════════════ */

const BUR_API = '<?php echo $_bur_api; ?>' + '/bur';
const $bur = id => document.getElementById(id);
// the table's amount columns, in the order the header lists them (left to right)
const BUR_COLS = ['approved_amount', 'revised_amount', 'revision_amount', 'utilization', 'balance', 'payment', 'account_payable'];
const BUR_FY_STORE = 'bur.fiscalYr';

let bur_rows = [];
let bur_totals = null;
let bur_collapsed = new Set();
let bur_search = '';
let bur_fiscalYr = '';
let bur_defaultFiscalYr = '';

function bur_esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function bur_fmtPeso(v) {
    const n = Number(v) || 0;
    const sign = n < 0 ? '-' : '';
    return `${sign}₱ ${Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}
function bur_cell(v) {
    const n = Number(v) || 0;
    return Math.abs(n) < 0.005 ? bur_fmtPeso(0) : `<span class="${n < 0 ? 'bur-neg' : ''}">${bur_fmtPeso(n)}</span>`;
}
function bur_amountCells(o) {
    return BUR_COLS.map(k => `<td class="nha-mono text-right">${bur_cell(o?.[k])}</td>`).join('');
}

/* ── Fiscal Year filter ── */
async function bur_loadFilterOptions(first) {
    const res = await fetch(`${BUR_API}/filters`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to load filters.');

    const years = json.data?.fiscal_years ?? [];
    const sel = $bur('bur-f-fy'), keep = sel.value;
    sel.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y}">${y}</option>`).join('');
    bur_defaultFiscalYr = years.length ? String(years[0]) : '';
    if (first) {
        let stored = '';
        try { stored = localStorage.getItem(BUR_FY_STORE) || ''; } catch (e) { /* storage may be blocked */ }
        sel.value = stored && years.some(y => String(y) === stored) ? stored : bur_defaultFiscalYr;
    } else {
        sel.value = [...sel.options].some(o => o.value === keep) ? keep : '';
    }
    bur_fiscalYr = sel.value;
}

/* ── Load ── */
async function bur_load() {
    window.PagePreloader?.show();
    $bur('bur-tbody').innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const qs = bur_fiscalYr ? `?${new URLSearchParams({ fiscal_yr: bur_fiscalYr })}` : '';
        const res = await fetch(`${BUR_API}/rollup${qs}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the report.');

        bur_rows = json.data ?? [];
        bur_totals = json.totals ?? null;
        bur_renderSummary();
        bur_render();
    } catch (e) {
        bur_rows = []; bur_totals = null;
        bur_renderSummary();
        $bur('bur-tbody').innerHTML = `<tr><td colspan="9"><div class="nha-error">${bur_esc(e.message)}</div></td></tr>`;
    } finally {
        window.PagePreloader?.hide();
    }
}
function bur_renderSummary() {
    const t = bur_totals;
    $bur('bur-kpi-approved').textContent = t ? bur_fmtPeso(t.approved_amount) : '—';
    $bur('bur-kpi-realigned').textContent = t ? bur_fmtPeso(t.revised_amount) : '—';
    $bur('bur-kpi-utilization').textContent = t ? bur_fmtPeso(t.utilization) : '—';
    $bur('bur-kpi-balance').textContent = t ? bur_fmtPeso(t.balance) : '—';
}

/* ── Tree (same shape as chart-of-accounts-page.php: account_id / parent_account_id) ── */
function bur_buildTree(rows) {
    const byId = new Map(rows.map(r => [r.account_id, { ...r, children: [] }]));
    const roots = [];
    byId.forEach(node => {
        const parent = node.parent_account_id && byId.get(node.parent_account_id);
        if (parent) parent.children.push(node); else roots.push(node);
    });
    const cmp = (a, b) => (a.account_code || '').localeCompare(b.account_code || '', undefined, { numeric: true, sensitivity: 'base' });
    const sortRec = list => { list.sort(cmp); list.forEach(n => sortRec(n.children)); };
    sortRec(roots);
    return roots;
}
function bur_flattenTree(nodes, depth, out) {
    nodes.forEach(node => {
        out.push({ ...node, _depth: depth, _hasChildren: node.children.length > 0 });
        if (node.children.length && !bur_collapsed.has(node.account_id)) bur_flattenTree(node.children, depth + 1, out);
    });
    return out;
}

/* ── Search (keeps matches, their ancestors, and their descendants) ── */
function bur_visibleRows() {
    const kw = bur_search.trim().toLowerCase();
    if (!kw) return bur_rows;

    const byId = new Map(bur_rows.map(r => [r.account_id, r]));
    const keep = new Set();
    const addDescendants = id => {
        bur_rows.forEach(r => {
            if (r.parent_account_id === id && !keep.has(r.account_id)) { keep.add(r.account_id); addDescendants(r.account_id); }
        });
    };
    bur_rows.forEach(r => {
        const matches = (r.account_title || '').toLowerCase().includes(kw) || (r.account_code || '').toLowerCase().includes(kw);
        if (!matches) return;
        let cur = r;
        while (cur && !keep.has(cur.account_id)) { keep.add(cur.account_id); cur = cur.parent_account_id ? byId.get(cur.parent_account_id) : null; }
        addDescendants(r.account_id);
    });
    return bur_rows.filter(r => keep.has(r.account_id));
}

function bur_toggleNode(id) {
    if (bur_collapsed.has(id)) bur_collapsed.delete(id); else bur_collapsed.add(id);
    bur_render();
}

/* ── Render ──────────────────────────────────────────────── */
function bur_render() {
    const rows = bur_visibleRows();
    const tbody = $bur('bur-tbody');

    const totalRow = `<tr class="bur-row-total"><td>TOTAL</td><td></td>${bur_amountCells(bur_totals)}</tr>`;

    if (!rows.length) {
        tbody.innerHTML = totalRow + `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No approved budget found.</span>
            <div class="nha-empty-text">Only Approved budget requests are part of the BUR. Try a different fiscal year or clear the search.</div></div></td></tr>`;
        return;
    }

    const tree = bur_buildTree(rows);
    const flat = bur_flattenTree(tree, 0, []);

    const bodyRows = flat.map(r => {
        const indent = r._depth * 22;
        const expanded = !bur_collapsed.has(r.account_id);
        const toggle = r._hasChildren
            ? `<button type="button" class="bur-toggle" onclick="bur_toggleNode('${bur_esc(r.account_id)}')" title="${expanded ? 'Collapse' : 'Expand'}">
                   <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                        style="transform:rotate(${expanded ? 90 : 0}deg);">
                       <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                   </svg>
               </button>`
            : `<span class="bur-toggle-spacer"></span>`;

        return `<tr class="bur-d${Math.min(r._depth, 4)}">
            <td>
                <div class="bur-name" style="padding-left:${indent}px;">
                    ${toggle}
                    <span>${bur_esc(r.account_title || '—')}</span>
                </div>
            </td>
            <td class="nha-mono nha-td-sub">${bur_esc(r.account_code || '—')}</td>
            ${bur_amountCells(r)}
        </tr>`;
    }).join('');

    tbody.innerHTML = totalRow + bodyRows;
}

function bur_expandAll() { bur_collapsed.clear(); bur_render(); }
function bur_collapseAll() { bur_collapsed = new Set(bur_rows.map(r => r.parent_account_id).filter(Boolean)); bur_render(); }

/* ── Events ──────────────────────────────────────────────── */
function bur_bindEvents() {
    $bur('bur-refresh')?.addEventListener('click', bur_refresh);
    $bur('bur-expand-all')?.addEventListener('click', bur_expandAll);
    $bur('bur-collapse-all')?.addEventListener('click', bur_collapseAll);
    $bur('bur-f-fy')?.addEventListener('change', e => {
        bur_fiscalYr = e.target.value;
        try { localStorage.setItem(BUR_FY_STORE, bur_fiscalYr); } catch (err) { /* convenience only */ }
        bur_load();
    });

    let searchTimer;
    const searchEl = $bur('bur-filter-search');
    const clearBtn = $bur('bur-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { bur_search = searchEl.value; bur_render(); }, 250);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        bur_search = '';
        bur_render();
    });
}
async function bur_refresh() {
    window.PagePreloader?.show();
    try { await bur_loadFilterOptions(false); } catch (e) { /* keep showing the last-known years */ }
    finally { window.PagePreloader?.hide(); }
    bur_load();
}

/* ── Init ────────────────────────────────────────────────── */
async function bur_bootstrapLoad() {
    bur_bindEvents();
    window.PagePreloader?.show();
    try { await bur_loadFilterOptions(true); } catch (e) { /* the Fiscal Year filter just stays empty */ }
    finally { window.PagePreloader?.hide(); }
    bur_load();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bur_bootstrapLoad);
} else {
    bur_bootstrapLoad();
}
</script>
