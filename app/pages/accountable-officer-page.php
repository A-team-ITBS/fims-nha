<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_ao_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<style>
/* ── Actions dropdown menu ──────────────────────────────────── */
.nha-tools-menu { position:relative;display:inline-block; }
.nha-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;
    font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--afims-border, #E4ECF2);
    background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;
    font-family:'IBM Plex Sans',sans-serif;transition:all .15s; }
.nha-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA);border-color:var(--afims-text-disabled, #A9AAB8); }
.nha-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.nha-tools-dropdown { display:none;position:fixed;min-width:170px;background:var(--afims-surface, #FFFFFF);
    border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);
    z-index:9999;padding:4px 0; }
.nha-tools-dropdown.open { display:block; }
.nha-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;
    color:var(--afims-text, #1A1B21);text-decoration:none;white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;transition:background .1s; }
.nha-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
</style>

<div id="ao-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Accountable Officers</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="ao-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="ao-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add an accountable officer">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Accountable Officer
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover every row matching the filters, not just this page) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total Accountable Officers</div>
                <div class="nha-sc-val" id="ao-kpi-total">—</div>
            </div></div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Active</div>
                <div class="nha-sc-val" id="ao-kpi-active">—</div>
            </div></div>
            <p class="nha-kpi-sub">Currently accountable</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Inactive</div>
                <div class="nha-sc-val" id="ao-kpi-inactive">—</div>
            </div></div>
            <p class="nha-kpi-sub">No longer accountable</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="ao-filter-status" class="nha-filter-select">
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
                    <input type="text" id="ao-filter-search" class="nha-filter-input" placeholder="Search officer name, region…">
                    <button class="nha-filter-clear-btn" id="ao-search-clear" hidden type="button">×</button>
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
                        <th>Officer Name</th>
                        <th>Region</th>
                        <th>GL Account</th>
                        <th>Sub-Ledger</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="ao-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="ao-from">0</span>–<span id="ao-to">0</span></strong> of <strong><span id="ao-total">0</span></strong></span>
            <div id="ao-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/accountable-officer-form.php'; ?>

<!-- ══════════════════════════════════════════════════════════
     SUB-LEDGER ASSIGNMENT MODAL   JS namespace: ao_sl_
══════════════════════════════════════════════════════════ -->
<style>
    #ao-sl-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #ao-sl-overlay.open { display:flex; }
    #ao-sl-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:600px;
        max-height:85vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:aoSlIn .2s ease; }
    @keyframes aoSlIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #ao-sl-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #ao-sl-header-info { display:flex;align-items:center;gap:10px; }
    .ao-sl-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .ao-sl-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #ao-sl-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #ao-sl-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #ao-sl-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #ao-sl-form { display:flex;flex-direction:column;flex:1 1 auto;min-height:0;margin:0; }
    #ao-sl-body { flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:22px; }
    #ao-sl-footer { flex-shrink:0; }
    .ao-sl-section { font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);text-transform:uppercase;
        letter-spacing:.06em;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .ao-sl-group { display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
    .ao-sl-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .ao-sl-input { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .ao-sl-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .ao-sl-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    #ao-sl-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #ao-sl-footer .ao-sl-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #ao-sl-footer .ao-sl-btn:disabled { opacity:.5;cursor:not-allowed; }
    #ao-sl-footer .ao-sl-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #ao-sl-footer .ao-sl-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #ao-sl-footer .ao-sl-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #ao-sl-footer .ao-sl-btn-navy:hover:not(:disabled) { opacity:.92; }
</style>

<div id="ao-sl-overlay">
    <div id="ao-sl-card">
        <div id="ao-sl-header">
            <div id="ao-sl-header-info">
                <div>
                    <p class="ao-sl-hdr-tag">Sub-Ledger Assignment</p>
                    <p class="ao-sl-hdr-title">Link Sub-Ledger Code</p>
                </div>
            </div>
            <button type="button" id="ao-sl-close">&times;</button>
        </div>

        <div id="ao-sl-err"></div>

        <form id="ao-sl-form" novalidate>
            <div id="ao-sl-body">
                <input type="hidden" id="ao-sl-officer-id" value="">

                <p class="ao-sl-section">1. Select GL Account</p>
                <div class="ao-sl-group" style="position:relative;">
                    <label class="ao-sl-label">Search GL Account <span class="req">*</span></label>
                    <input type="hidden" id="ao-sl-gl-account-id" value="">
                    <input type="text" class="ao-sl-input" id="ao-sl-gl-search" maxlength="500" placeholder="Type GL account code or name...">
                    <div id="ao-sl-gl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                    <p class="ao-sl-hint">This is the parent GL account for the officer.</p>
                </div>
                <div id="ao-sl-gl-selected" style="padding:10px;background:#F4F7FA;border-radius:6px;display:none;margin-top:8px;">
                    <strong>GL:</strong> <span id="ao-sl-gl-selected-text"></span>
                </div>

                <div id="ao-sl-section-2" style="margin-top:22px;opacity:.5;pointer-events:none;">
                    <p class="ao-sl-section">2. Select or Create Sub-Ledger (Child Account)</p>
                    <div class="ao-sl-group" style="position:relative;">
                        <label class="ao-sl-label">Search Sub-Ledger <span class="req">*</span></label>
                        <input type="text" class="ao-sl-input" id="ao-sl-search" maxlength="500" placeholder="Select a GL account first…" disabled>
                        <div id="ao-sl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                        <p class="ao-sl-hint">Pick an existing child account under this GL, or create a new one below.</p>
                        <p id="ao-sl-empty-note" class="ao-sl-hint" style="display:none;color:var(--afims-danger, #DC3545);">No child accounts exist under this GL account yet. Create one below.</p>
                    </div>
                    <div id="ao-sl-selected-item" style="padding:10px;background:#F4F7FA;border-radius:6px;display:none;margin-top:8px;">
                        <strong>Selected:</strong> <span id="ao-sl-selected-code"></span>
                    </div>
                    <input type="hidden" id="ao-sl-selected-code-value">

                    <div class="ao-sl-group" style="margin-top:14px;border-top:1px dashed var(--afims-border, #E4ECF2);padding-top:14px;">
                        <label class="ao-sl-label">New Sub-Ledger Title</label>
                        <input type="text" class="ao-sl-input" id="ao-sl-new-title" maxlength="255" placeholder="e.g., 0213 CONSTRUCTION AND SUPPLIES OPC">
                        <p class="ao-sl-hint">Not in the list above? Type a title and click Create.</p>
                        <button type="button" class="ao-sl-btn ao-sl-btn-ghost" id="ao-sl-create-btn" style="margin-top:8px;align-self:flex-start;">+ Create Sub-Ledger Account</button>
                    </div>
                </div>
            </div>

            <div id="ao-sl-footer">
                <button type="button" class="ao-sl-btn ao-sl-btn-ghost" id="ao-sl-cancel">Cancel</button>
                <button type="submit" class="ao-sl-btn ao-sl-btn-navy" id="ao-sl-save">Assign Sub-Ledger</button>
            </div>
        </form>
    </div>
</div>

</div><!-- /#ao-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   ACCOUNTABLE OFFICERS LIST   prefix: ao_
═══════════════════════════════════════════════════════════════ */

const AO_API = '<?php echo $_ao_api; ?>' + '/accountable-officers';

let ao_page = 1;
const AO_PAGE_SIZE = 15;

const $ao = id => document.getElementById(id);

async function ao_bootstrapLoad() {
    await Promise.all([ao_load(), ao_loadRegions()]);
    ao_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ao_bootstrapLoad);
} else {
    ao_bootstrapLoad();
}

/* Regions fill the form's Region select — reuses the Responsibility Center lookup. */
async function ao_loadRegions() {
    try {
        const res  = await fetch(`${AO_API}/../responsibility-center/all?status=Active`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load regions.');

        const options = (json.data ?? []).map(r =>
            `<option value="${ao_escAttr(r.rc_id)}">${ao_esc(r.rc_code)} — ${ao_esc(r.rc_name)}</option>`
        ).join('');
        $ao('ao-region').insertAdjacentHTML('beforeend', options);
    } catch (e) {
        ao_toast(e.message, 'error');
    }
}

function ao_bindEvents() {
    $ao('ao-refresh')?.addEventListener('click', () => { ao_page = 1; ao_load(); });
    $ao('ao-add')?.addEventListener('click', () => ao_openModal('add'));
    $ao('ao-modal-close')?.addEventListener('click', ao_closeModal);
    $ao('ao-cancel')?.addEventListener('click', ao_closeModal);
    $ao('ao-form')?.addEventListener('submit', ao_save);

    $ao('ao-filter-status')?.addEventListener('change', () => { ao_page = 1; ao_load(); });

    let searchTimer;
    const searchEl = $ao('ao-filter-search');
    const clearBtn = $ao('ao-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { ao_page = 1; ao_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        ao_page = 1; ao_load();
    });

    // COA account search
    const coaSearch = $ao('ao-coa-account-search');
    if (coaSearch) {
        coaSearch.addEventListener('input', ao_searchCoaAccounts);
        coaSearch.addEventListener('focus', () => {
            const dropdown = $ao('ao-coa-dropdown');
            if (coaSearch.value.trim() && dropdown.children.length) {
                dropdown.style.display = 'block';
            }
        });
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#ao-coa-account-search') && !e.target.closest('#ao-coa-dropdown')) {
                $ao('ao-coa-dropdown').style.display = 'none';
            }
        });
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function ao_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search : $ao('ao-filter-search')?.value || '',
            status : $ao('ao-filter-status')?.value || '',
            page   : ao_page,
            limit  : AO_PAGE_SIZE,
        });
        const res  = await fetch(`${AO_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        ao_renderTable(json.data ?? []);
        ao_renderPagination(json.pagination ?? {});
        $ao('ao-kpi-total').textContent    = json.pagination?.total ?? (json.data ?? []).length;
        $ao('ao-kpi-active').textContent   = json.summary?.active   ?? '—';
        $ao('ao-kpi-inactive').textContent = json.summary?.inactive ?? '—';
    } catch (e) {
        ao_renderTable([]);
        ao_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function ao_statusBadge(s) {
    const st  = (s || 'ACTIVE').toUpperCase();
    const map = { ACTIVE: ['nha-bdg-green', 'Active'], INACTIVE: ['nha-bdg-gray', 'Inactive'] };
    const [cls, label] = map[st] || ['nha-bdg-gray', st];
    return `<span class="nha-bdg ${cls}">${ao_esc(label)}</span>`;
}

function ao_renderTable(rows) {
    const tbody = $ao('ao-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No accountable officers found.</span></div></td></tr>';
        return;
    }
    const offset = (ao_page - 1) * AO_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><div style="font-weight:600;">${ao_esc(r.officer_name)}</div></td>
        <td>${r.rc_code ? `<span class="nha-bdg nha-bdg-blue">${ao_esc(r.rc_code)} — ${ao_esc(r.rc_name)}</span>` : '—'}</td>
        <td><code class="nha-code">${ao_esc(r.coa_account_code || '—')}</code></td>
        <td>
            ${r.sl_code ? `<span class="nha-bdg nha-bdg-green">${ao_esc(r.sl_account_code || r.sl_code)}</span>` : '<span class="nha-bdg nha-bdg-gray">None</span>'}
        </td>
        <td>${ao_statusBadge(r.status)}</td>
        <td>${ao_actionMenu(r)}</td>
    </tr>`).join('');
}

function ao_actionMenu(r) {
    const id = Number(r.accountable_officer_id);
    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="ao_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">
            <a class="nha-menu-item" href="javascript:void(0);" onclick="ao_closeMenus();ao_edit(${id});">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            <a class="nha-menu-item" href="javascript:void(0);" onclick="ao_closeMenus();ao_openSlModal(${id}, '${ao_escAttr(r.coa_account_id)}', '${ao_escAttr(r.sl_code || '')}');">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.658 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                Link SL
            </a>
        </div>
    </div>`;
}

/* ── Actions dropdown — open/close (closes others, positions via fixed
   coords so the table's own overflow never clips it) ─────────────── */
function ao_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    ao_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.style.top = (rect.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
    drop.classList.add('open');
}

function ao_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) ao_closeMenus();
});
document.addEventListener('scroll', ao_closeMenus, true);

/* ── Pagination ────────────────────────────────────────────── */
function ao_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = AO_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $ao('ao-from').textContent  = from;
    $ao('ao-to').textContent    = to;
    $ao('ao-total').textContent = total;

    const container = $ao('ao-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="ao_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="ao_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="ao_goTo(${current_page + 1})">›</button>`;
}

function ao_goTo(page) { if (page >= 1) { ao_page = page; ao_load(); } }

/* ── Modal ─────────────────────────────────────────────────── */
function ao_openModal(mode, row = null) {
    ao_hideErr();
    $ao('ao-mode').value = mode;
    $ao('ao-id').value   = row?.accountable_officer_id || '';
    $ao('ao-modal-title').textContent = mode === 'edit' ? 'Edit Accountable Officer' : 'Add Accountable Officer';

    $ao('ao-first-name').value  = row?.first_name ?? '';
    $ao('ao-middle-name').value = row?.middle_name ?? '';
    $ao('ao-last-name').value   = row?.last_name ?? '';
    $ao('ao-suffix').value      = row?.suffix ?? '';
    $ao('ao-region').value      = row?.rc_id ?? '';
    if (!row?.status) $ao('ao-status').value = 'ACTIVE'; else $ao('ao-status').value = row.status;

    // COA account (populate if editing)
    $ao('ao-coa-account-id').value = row?.coa_account_id || '';
    if (row?.coa_account_id) {
        $ao('ao-coa-account-search').value = `${row.coa_account_code} — ${row.coa_account_name}`;
        $ao('ao-coa-selected').innerHTML = `<strong>${row.coa_account_code}</strong><br><small>${row.coa_account_name}</small>`;
        $ao('ao-coa-selected').style.display = 'block';
    } else {
        $ao('ao-coa-account-search').value = '';
        $ao('ao-coa-selected').style.display = 'none';
    }
    $ao('ao-coa-dropdown').style.display = 'none';
    $ao('ao-coa-dropdown').innerHTML = '';

    $ao('ao-overlay').classList.add('open');
}

function ao_closeModal() {
    $ao('ao-overlay').classList.remove('open');
    $ao('ao-form').reset();
    ao_hideErr();
}

function ao_showErr(msg) {
    const el = $ao('ao-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function ao_hideErr() {
    $ao('ao-err').style.display = 'none';
}

async function ao_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${AO_API}/read/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load accountable officer.');
        ao_openModal('edit', json.data);
    } catch (e) {
        ao_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function ao_save(ev) {
    ev.preventDefault();
    ao_hideErr();
    const mode = $ao('ao-mode').value;
    const id   = $ao('ao-id').value;

    const payload = {
        first_name  : $ao('ao-first-name').value.trim(),
        middle_name : $ao('ao-middle-name').value.trim(),
        last_name   : $ao('ao-last-name').value.trim(),
        suffix      : $ao('ao-suffix').value,
        rc_id       : $ao('ao-region').value,
        status      : $ao('ao-status').value,
    };
    const coaAccountId = $ao('ao-coa-account-id').value;

    if (!payload.first_name || !payload.last_name) { ao_showErr('First name and last name are required.'); return; }

    if (coaAccountId) payload.coa_account_id = coaAccountId;

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${AO_API}/update/${encodeURIComponent(id)}` : `${AO_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save accountable officer.');

        ao_toast(`Accountable officer ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        ao_closeModal();
        ao_load();
    } catch (e) {
        ao_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function ao_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function ao_escAttr(text) {
    return ao_esc(text);
}

function ao_toast(msg, type = 'info') {
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
    span.textContent = msg;                 // textContent — messages may contain server text
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

/* ── COA Account Picker ────────────────────────────────────── */
let ao_coaSearchTimer;
async function ao_searchCoaAccounts(ev) {
    const query = ev.target.value.trim();
    const dropdown = $ao('ao-coa-dropdown');

    if (!query || query.length < 1) {
        dropdown.style.display = 'none';
        dropdown.innerHTML = '';
        return;
    }

    clearTimeout(ao_coaSearchTimer);
    ao_coaSearchTimer = setTimeout(async () => {
        try {
            const res = await fetch(`${AO_API}/../accounting/coa/search?q=${encodeURIComponent(query)}&limit=20`, { credentials: 'same-origin' });
            const json = await res.json();

            if (!json.success || !json.data?.length) {
                dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No accounts found.</div>';
                dropdown.style.display = 'block';
                return;
            }

            dropdown.innerHTML = json.data.map(account =>
                `<div class="ao-coa-item" data-id="${account.account_id}" data-code="${ao_esc(account.account_code)}" data-name="${ao_esc(account.account_title)}" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;transition:background .15s;">
                    <div style="font-weight:600;">${ao_esc(account.account_code)}</div>
                    <div style="font-size:11px;color:#999;margin-top:2px;">${ao_esc(account.account_title)}</div>
                </div>`
            ).join('');

            dropdown.style.display = 'block';
            dropdown.querySelectorAll('.ao-coa-item').forEach(item => {
                item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
                item.addEventListener('mouseout', () => item.style.background = 'transparent');
                item.addEventListener('click', () => ao_selectCoaAccount(item));
            });
        } catch (e) {
            dropdown.innerHTML = `<div style="padding:10px;color:#d32f2f;font-size:12px;">Error: ${ao_esc(e.message)}</div>`;
            dropdown.style.display = 'block';
        }
    }, 300);
}

function ao_selectCoaAccount(item) {
    const accountId = item.dataset.id;
    const code = item.dataset.code;
    const name = item.dataset.name;

    $ao('ao-coa-account-id').value = accountId;
    $ao('ao-coa-account-search').value = `${code} — ${name}`;
    $ao('ao-coa-dropdown').style.display = 'none';

    const selected = $ao('ao-coa-selected');
    selected.innerHTML = `<strong>${code}</strong><br><small>${name}</small>`;
    selected.style.display = 'block';
}

/* ── SL Modal Functions ─────────────────────────────────────── */
function ao_openSlModal(officerId, accountId, currentSl) {
    $ao('ao-sl-officer-id').value = officerId;

    // Reset GL section
    $ao('ao-sl-gl-account-id').value = '';
    $ao('ao-sl-gl-search').value = '';
    $ao('ao-sl-gl-dropdown').innerHTML = '';
    $ao('ao-sl-gl-dropdown').style.display = 'none';
    $ao('ao-sl-gl-selected').style.display = 'none';

    // Reset SL section
    $ao('ao-sl-search').value = '';
    $ao('ao-sl-search').disabled = true;
    $ao('ao-sl-search').placeholder = 'Select a GL account first…';
    $ao('ao-sl-selected-code-value').value = '';
    $ao('ao-sl-selected-item').style.display = 'none';
    $ao('ao-sl-dropdown').innerHTML = '';
    $ao('ao-sl-dropdown').style.display = 'none';
    $ao('ao-sl-empty-note').style.display = 'none';
    $ao('ao-sl-new-title').value = '';
    $ao('ao-sl-section-2').style.opacity = '.5';
    $ao('ao-sl-section-2').style.pointerEvents = 'none';

    ao_slHideErr();
    $ao('ao-sl-overlay').classList.add('open');

    // Pre-fill GL if the officer already has one assigned.
    if (accountId) {
        ao_fetchAndSelectGl(accountId, currentSl);
    }
}

// Look up the GL account's display info, then wire up the SL section for it.
async function ao_fetchAndSelectGl(accountId, currentSl) {
    try {
        const res = await fetch(`${AO_API}/../accounting/coa/search?q=${encodeURIComponent(accountId)}&limit=50`, { credentials: 'same-origin' });
        const json = await res.json();
        const match = json.success ? (json.data || []).find(a => String(a.account_id) === String(accountId)) : null;
        if (match) {
            ao_selectGlAccount({ dataset: { id: match.account_id, code: match.account_code, name: match.account_title } });
        }
    } catch (e) {
        // Non-fatal — user can still pick a GL manually.
    }

    if (currentSl) {
        ao_loadSlCodesForAccount(accountId, currentSl);
    }
}

function ao_closeSlModal() {
    $ao('ao-sl-overlay').classList.remove('open');
    $ao('ao-sl-form').reset();
    ao_slHideErr();
}

function ao_slShowErr(msg) {
    const el = $ao('ao-sl-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function ao_slHideErr() {
    $ao('ao-sl-err').style.display = 'none';
}

/* ── Step 1: GL Account picker ──────────────────────────────── */
let ao_slGlSearchTimer;
$ao('ao-sl-gl-search')?.addEventListener('input', (ev) => {
    const query = ev.target.value.trim();
    const dropdown = $ao('ao-sl-gl-dropdown');
    clearTimeout(ao_slGlSearchTimer);

    if (!query) {
        dropdown.style.display = 'none';
        return;
    }

    ao_slGlSearchTimer = setTimeout(() => {
        fetch(`${AO_API}/../accounting/coa/search?q=${encodeURIComponent(query)}&limit=20`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data?.length) {
                    dropdown.innerHTML = json.data.map(a =>
                        `<div class="ao-sl-gl-item" data-id="${a.account_id}" data-code="${ao_escAttr(a.account_code)}" data-name="${ao_escAttr(a.account_title)}" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;transition:background .15s;">
                            <div style="font-weight:600;">${ao_esc(a.account_code)}</div>
                            <div style="font-size:11px;color:#999;margin-top:2px;">${ao_esc(a.account_title)}</div>
                        </div>`
                    ).join('');
                    dropdown.style.display = 'block';
                    dropdown.querySelectorAll('.ao-sl-gl-item').forEach(item => {
                        item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
                        item.addEventListener('mouseout', () => item.style.background = 'transparent');
                        item.addEventListener('click', () => ao_selectGlAccount(item));
                    });
                } else {
                    dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No accounts found.</div>';
                    dropdown.style.display = 'block';
                }
            })
            .catch(e => ao_slShowErr('GL search failed: ' + e.message));
    }, 300);
});

function ao_selectGlAccount(item) {
    const accountId = item.dataset.id;
    const code = item.dataset.code;
    const name = item.dataset.name;

    $ao('ao-sl-gl-account-id').value = accountId;
    $ao('ao-sl-gl-search').value = `${code} — ${name}`;
    $ao('ao-sl-gl-dropdown').style.display = 'none';

    const selected = $ao('ao-sl-gl-selected');
    selected.innerHTML = `<strong>${ao_esc(code)}</strong> — ${ao_esc(name)}`;
    selected.style.display = 'block';

    // Changing the GL invalidates any previously selected SL.
    $ao('ao-sl-selected-code-value').value = '';
    $ao('ao-sl-selected-item').style.display = 'none';
    $ao('ao-sl-search').value = '';
    $ao('ao-sl-search').disabled = false;
    $ao('ao-sl-search').placeholder = 'Type account code or description...';
    $ao('ao-sl-section-2').style.opacity = '1';
    $ao('ao-sl-section-2').style.pointerEvents = 'auto';

    ao_loadSlCodesForAccount(accountId);
}

/* ── Step 2: Sub-Ledger (child account) picker ──────────────── */
// Load SL codes for an account; optionally pre-select a known current SL id.
async function ao_loadSlCodesForAccount(accountId, preselectId = null) {
    try {
        const res = await fetch(`${AO_API}/../accounting/sl/by-account?account_id=${encodeURIComponent(accountId)}&limit=50`, { credentials: 'same-origin' });
        const json = await res.json();

        if (!json.success || !json.data?.length) {
            // Quiet inline note instead of an overlapping dropdown box.
            $ao('ao-sl-empty-note').style.display = 'block';
            $ao('ao-sl-dropdown').style.display = 'none';
            return;
        }

        $ao('ao-sl-empty-note').style.display = 'none';
        ao_renderSlList(json.data);
        $ao('ao-sl-dropdown').style.display = 'none'; // don't force it open — just cache for typing

        if (preselectId) {
            const match = json.data.find(a => String(a.account_id) === String(preselectId));
            if (match) {
                $ao('ao-sl-search').value = match.account_code;
                $ao('ao-sl-selected-code-value').value = match.account_id;
                $ao('ao-sl-selected-code').textContent = match.account_code;
                $ao('ao-sl-selected-item').style.display = 'block';
            }
        }
    } catch (e) {
        ao_slShowErr('Failed to load sub-ledgers: ' + e.message);
    }
}

// Render SL dropdown list
function ao_renderSlList(items) {
    const dropdown = $ao('ao-sl-dropdown');
    dropdown.innerHTML = items.map(item =>
        `<div class="ao-sl-item" data-code="${ao_escAttr(item.account_id)}" data-display="${ao_escAttr(item.account_code)}" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;transition:background .15s;">
            <div style="font-weight:600;">${ao_esc(item.account_code)}</div>
            ${item.account_title ? `<div style="font-size:11px;color:#999;margin-top:2px;">${ao_esc(item.account_title)}</div>` : ''}
        </div>`
    ).join('');

    dropdown.style.display = 'block';
    dropdown.querySelectorAll('.ao-sl-item').forEach(item => {
        item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
        item.addEventListener('mouseout', () => item.style.background = 'transparent');
        item.addEventListener('click', () => ao_selectSlCode(item));
    });
}

// Select a SL code
function ao_selectSlCode(item) {
    const slId = item.dataset.code;  // This is the account_id from COA
    const display = item.dataset.display;  // This is the account_code for display
    $ao('ao-sl-search').value = display;
    $ao('ao-sl-selected-code-value').value = slId;
    $ao('ao-sl-selected-code').textContent = display;
    $ao('ao-sl-selected-item').style.display = 'block';
    $ao('ao-sl-dropdown').style.display = 'none';
}

// Handle SL search input
let ao_slSearchTimer;
$ao('ao-sl-search')?.addEventListener('input', (ev) => {
    const query = ev.target.value.trim();
    clearTimeout(ao_slSearchTimer);

    if (!query) {
        $ao('ao-sl-dropdown').style.display = 'none';
        return;
    }

    ao_slSearchTimer = setTimeout(() => {
        const accountId = $ao('ao-sl-gl-account-id').value;
        if (!accountId) return;
        fetch(`${AO_API}/../accounting/sl/search?account_id=${encodeURIComponent(accountId)}&q=${encodeURIComponent(query)}&limit=50`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data?.length) {
                    ao_renderSlList(json.data);
                } else {
                    const dropdown = $ao('ao-sl-dropdown');
                    dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No matching sub-ledgers. Try creating one below.</div>';
                    dropdown.style.display = 'block';
                }
            })
            .catch(e => ao_slShowErr('Search failed: ' + e.message));
    }, 300);
});

/* ── Step 2b: Create a new sub-ledger under the selected GL ───── */
$ao('ao-sl-create-btn')?.addEventListener('click', async () => {
    ao_slHideErr();
    const glAccountId = $ao('ao-sl-gl-account-id').value;
    const title = $ao('ao-sl-new-title').value.trim();

    if (!glAccountId) { ao_slShowErr('Select a GL account first.'); return; }
    if (!title)        { ao_slShowErr('Enter a title for the new sub-ledger.'); return; }

    try {
        window.PagePreloader?.show();
        const res = await fetch(`${AO_API}/../accounting/sl/create`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ parent_account_id: glAccountId, account_title: title }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to create sub-ledger.');

        const created = json.data;
        $ao('ao-sl-search').value = created.account_code;
        $ao('ao-sl-selected-code-value').value = created.account_id;
        $ao('ao-sl-selected-code').textContent = created.account_code;
        $ao('ao-sl-selected-item').style.display = 'block';
        $ao('ao-sl-empty-note').style.display = 'none';
        $ao('ao-sl-new-title').value = '';

        ao_toast('Sub-ledger account created.', 'success');
    } catch (e) {
        ao_slShowErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
});

/* ── Save: GL + SL assignment ───────────────────────────────── */
$ao('ao-sl-form')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    ao_slHideErr();

    const officerId = $ao('ao-sl-officer-id').value;
    const glAccountId = $ao('ao-sl-gl-account-id').value;
    const slCode = $ao('ao-sl-selected-code-value').value;

    if (!glAccountId) { ao_slShowErr('Please select a GL account.'); return; }
    if (!slCode)       { ao_slShowErr('Please select or create a sub-ledger account.'); return; }

    try {
        window.PagePreloader?.show();
        const res = await fetch(`${AO_API}/update/${encodeURIComponent(officerId)}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coa_account_id: glAccountId, sl_code: slCode }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to update accountable officer.');

        ao_toast(`Sub-ledger assigned successfully.`, 'success');
        ao_closeSlModal();
        ao_load();
    } catch (e) {
        ao_slShowErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
});

// Close dropdowns on outside click
document.addEventListener('click', (e) => {
    if (!e.target.closest('#ao-sl-gl-search') && !e.target.closest('#ao-sl-gl-dropdown')) {
        $ao('ao-sl-gl-dropdown').style.display = 'none';
    }
    if (!e.target.closest('#ao-sl-search') && !e.target.closest('#ao-sl-dropdown')) {
        $ao('ao-sl-dropdown').style.display = 'none';
    }
});
$ao('ao-sl-close')?.addEventListener('click', ao_closeSlModal);
$ao('ao-sl-cancel')?.addEventListener('click', ao_closeSlModal);
</script>
