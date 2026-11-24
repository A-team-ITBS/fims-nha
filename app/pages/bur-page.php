<?php

namespace PHPMaker2024\FIMS;

/**
 * bur-page.php — Budget Utilization Report (BUR): UI-only first pass.
 *
 * Rows are the real Chart of Accounts tree (account_code / account_title / parent_account_id), fetched the same way
 * chart-of-accounts-page.php does (GET /chart-of-accounts/all), then filtered down to the Personnel Services (5-01)
 * and Maintenance and Other Operating Expenses (5-02) subtrees only (see BUR_SCOPE_CODES below) — shown as flattened
 * top-level rows. Every money column (Approved Budget, Realigned Budget, Increase (Decrease), Utilization, Balance,
 * Payment, Accounts Payable) is a hardcoded 0 — no computation yet. Those get wired up once the budget-utilization
 * figures are available.
 */
@session_start();
$_bur_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
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
                <p class="nha-hdr-sub">Expense classification against the Chart of Accounts — figures pending.</p>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="bur-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (placeholders — no figures computed yet) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Approved Budget</div>
                <div class="nha-sc-val">₱ 0.00</div>
            </div></div>
            <p class="nha-kpi-sub">Not yet computed</p>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Realigned Budget</div>
                <div class="nha-sc-val">₱ 0.00</div>
            </div></div>
            <p class="nha-kpi-sub">Not yet computed</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Utilization</div>
                <div class="nha-sc-val">₱ 0.00</div>
            </div></div>
            <p class="nha-kpi-sub">Not yet computed</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Balance</div>
                <div class="nha-sc-val">₱ 0.00</div>
            </div></div>
            <p class="nha-kpi-sub">Not yet computed</p>
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
   BUDGET UTILIZATION REPORT (BUR)   prefix: bur_   (UI-only pass)

   Rows: the real Chart of Accounts tree (GET /chart-of-accounts/all — same endpoint and account_id / account_code /
   account_title / parent_account_id shape as chart-of-accounts-page.php). Every money column below is hardcoded to 0
   — Approved Budget, Realigned Budget, Increase (Decrease), Utilization, Balance, Payment, Accounts Payable all need
   their own computation, wired up later.
═══════════════════════════════════════════════════════════════ */

const BUR_API = '<?php echo $_bur_api; ?>' + '/chart-of-accounts';
const $bur = id => document.getElementById(id);

/* Scope: only Personnel Services (5-01) and Maintenance and Other Operating Expenses (5-02), flattened to root rows. */
const BUR_SCOPE_CODES = ['5-01', '5-02'];

let bur_rows = [];
let bur_collapsed = new Set();
let bur_search = '';

function bur_esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}
function bur_fmtPeso(v) {
    return '₱ ' + (Number(v) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ── Load (real accounts; the money columns next to them are always 0) ── */
async function bur_load() {
    window.PagePreloader?.show();
    $bur('bur-tbody').innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const res = await fetch(`${BUR_API}/all`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load accounts.');

        const allRows = json.data ?? [];
        const byId = new Map(allRows.map(r => [r.account_id, r]));
        const isUnderScope = id => {
            let cur = id && byId.get(id);
            while (cur) {
                if (BUR_SCOPE_CODES.includes(cur.account_code)) return true;
                cur = cur.parent_account_id ? byId.get(cur.parent_account_id) : null;
            }
            return false;
        };
        bur_rows = allRows.filter(r => BUR_SCOPE_CODES.includes(r.account_code) || isUnderScope(r.parent_account_id));
        bur_render();
    } catch (e) {
        bur_rows = [];
        $bur('bur-tbody').innerHTML = `<tr><td colspan="9"><div class="nha-error">${bur_esc(e.message)}</div></td></tr>`;
    } finally {
        window.PagePreloader?.hide();
    }
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

    const totalRow = `<tr class="bur-row-total">
        <td>TOTAL</td><td></td>
        <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
        <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
        <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
        <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
        <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
        <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
        <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
    </tr>`;

    if (!rows.length) {
        tbody.innerHTML = totalRow + `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No accounts match your search.</span></div></td></tr>`;
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
            <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
            <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
            <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
            <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
            <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
            <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
            <td class="nha-mono text-right">${bur_fmtPeso(0)}</td>
        </tr>`;
    }).join('');

    tbody.innerHTML = totalRow + bodyRows;
}

function bur_expandAll() { bur_collapsed.clear(); bur_render(); }
function bur_collapseAll() { bur_collapsed = new Set(bur_rows.map(r => r.parent_account_id).filter(Boolean)); bur_render(); }

/* ── Events ──────────────────────────────────────────────── */
function bur_bindEvents() {
    $bur('bur-refresh')?.addEventListener('click', bur_load);
    $bur('bur-expand-all')?.addEventListener('click', bur_expandAll);
    $bur('bur-collapse-all')?.addEventListener('click', bur_collapseAll);

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

/* ── Init ────────────────────────────────────────────────── */
function bur_bootstrapLoad() {
    bur_bindEvents();
    bur_load();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bur_bootstrapLoad);
} else {
    bur_bootstrapLoad();
}
</script>
