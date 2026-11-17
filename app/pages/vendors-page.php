<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_vnd_api = Config('API.FIMS_URL');
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

<div id="vnd-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Vendors</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="vnd-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="vnd-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a vendor">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Vendor
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover every row matching the filters, not just this page) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total Vendors</div>
                <div class="nha-sc-val" id="vnd-kpi-total">—</div>
            </div></div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Active</div>
                <div class="nha-sc-val" id="vnd-kpi-active">—</div>
            </div></div>
            <p class="nha-kpi-sub">Can be used on purchase orders</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Inactive / Blacklisted</div>
                <div class="nha-sc-val" id="vnd-kpi-inactive">—</div>
            </div></div>
            <p class="nha-kpi-sub">Hidden from the PO vendor list</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Vendor Type</label>
                <select id="vnd-filter-type" class="nha-filter-select">
                    <option value="">All Types</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="vnd-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                    <option value="BLACKLISTED">Blacklisted</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="vnd-filter-search" class="nha-filter-input" placeholder="Search vendor code, name, TIN…">
                    <button class="nha-filter-clear-btn" id="vnd-search-clear" hidden type="button">×</button>
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
                        <th>Vendor Code</th>
                        <th>Vendor Name</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Tax</th>
                        <th>GL Account</th>
                        <th>Sub-Ledger</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="vnd-tbody">
                    <tr><td colspan="10"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="vnd-from">0</span>–<span id="vnd-to">0</span></strong> of <strong><span id="vnd-total">0</span></strong></span>
            <div id="vnd-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/vendor-form.php'; ?>

<!-- ══════════════════════════════════════════════════════════
     SUB-LEDGER ASSIGNMENT MODAL   JS namespace: vnd_sl_
══════════════════════════════════════════════════════════ -->
<style>
    #vnd-sl-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #vnd-sl-overlay.open { display:flex; }
    #vnd-sl-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:600px;
        max-height:85vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:vndSlIn .2s ease; }
    @keyframes vndSlIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #vnd-sl-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #vnd-sl-header-info { display:flex;align-items:center;gap:10px; }
    .vnd-sl-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .vnd-sl-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #vnd-sl-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #vnd-sl-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #vnd-sl-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #vnd-sl-form { display:flex;flex-direction:column;flex:1 1 auto;min-height:0;margin:0; }
    #vnd-sl-body { flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:22px; }
    #vnd-sl-footer { flex-shrink:0; }
    .vnd-sl-section { font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);text-transform:uppercase;
        letter-spacing:.06em;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .vnd-sl-group { display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
    .vnd-sl-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .vnd-sl-input { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .vnd-sl-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .vnd-sl-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }
    .vnd-sl-radio { display:flex;align-items:center;gap:8px;font-size:13px;color:var(--afims-text, #1A1B21);
        padding:10px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;cursor:pointer;margin-bottom:8px;transition:background .15s; }
    .vnd-sl-radio:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    .vnd-sl-radio input[type="radio"] { width:16px;height:16px;accent-color:var(--afims-heading-fill, #000E3E); }

    #vnd-sl-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #vnd-sl-footer .vnd-sl-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #vnd-sl-footer .vnd-sl-btn:disabled { opacity:.5;cursor:not-allowed; }
    #vnd-sl-footer .vnd-sl-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #vnd-sl-footer .vnd-sl-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #vnd-sl-footer .vnd-sl-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #vnd-sl-footer .vnd-sl-btn-navy:hover:not(:disabled) { opacity:.92; }
</style>

<div id="vnd-sl-overlay">
    <div id="vnd-sl-card">
        <div id="vnd-sl-header">
            <div id="vnd-sl-header-info">
                <div>
                    <p class="vnd-sl-hdr-tag">Sub-Ledger Assignment</p>
                    <p class="vnd-sl-hdr-title">Link Sub-Ledger Code</p>
                </div>
            </div>
            <button type="button" id="vnd-sl-close">&times;</button>
        </div>

        <div id="vnd-sl-err"></div>

        <form id="vnd-sl-form" novalidate>
            <div id="vnd-sl-body">
                <input type="hidden" id="vnd-sl-vendor-id" value="">

                <p class="vnd-sl-section">1. Select GL Account</p>
                <div class="vnd-sl-group" style="position:relative;">
                    <label class="vnd-sl-label">Search GL Account <span class="req">*</span></label>
                    <input type="hidden" id="vnd-sl-gl-account-id" value="">
                    <input type="text" class="vnd-sl-input" id="vnd-sl-gl-search" maxlength="500" placeholder="Type GL account code or name...">
                    <div id="vnd-sl-gl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                    <p class="vnd-sl-hint">This is the parent payables account for the vendor.</p>
                </div>
                <div id="vnd-sl-gl-selected" style="padding:10px;background:#F4F7FA;border-radius:6px;display:none;margin-top:8px;">
                    <strong>GL:</strong> <span id="vnd-sl-gl-selected-text"></span>
                </div>

                <div id="vnd-sl-section-2" style="margin-top:22px;opacity:.5;pointer-events:none;">
                    <p class="vnd-sl-section">2. Select or Create Sub-Ledger (Child Account)</p>
                    <div class="vnd-sl-group" style="position:relative;">
                        <label class="vnd-sl-label">Search Sub-Ledger <span class="req">*</span></label>
                        <input type="text" class="vnd-sl-input" id="vnd-sl-search" maxlength="500" placeholder="Select a GL account first…" disabled>
                        <div id="vnd-sl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                        <p class="vnd-sl-hint">Pick an existing child account under this GL, or create a new one below.</p>
                        <p id="vnd-sl-empty-note" class="vnd-sl-hint" style="display:none;color:var(--afims-danger, #DC3545);">No child accounts exist under this GL account yet. Create one below.</p>
                    </div>
                    <div id="vnd-sl-selected-item" style="padding:10px;background:#F4F7FA;border-radius:6px;display:none;margin-top:8px;">
                        <strong>Selected:</strong> <span id="vnd-sl-selected-code"></span>
                    </div>
                    <input type="hidden" id="vnd-sl-selected-code-value">

                    <div class="vnd-sl-group" style="margin-top:14px;border-top:1px dashed var(--afims-border, #E4ECF2);padding-top:14px;">
                        <label class="vnd-sl-label">New Sub-Ledger Title</label>
                        <input type="text" class="vnd-sl-input" id="vnd-sl-new-title" maxlength="255" placeholder="e.g., 0213 CONSTRUCTION AND SUPPLIES OPC">
                        <p class="vnd-sl-hint">Not in the list above? Type a title and click Create.</p>
                        <button type="button" class="vnd-sl-btn vnd-sl-btn-ghost" id="vnd-sl-create-btn" style="margin-top:8px;align-self:flex-start;">+ Create Sub-Ledger Account</button>
                    </div>
                </div>
            </div>

            <div id="vnd-sl-footer">
                <button type="button" class="vnd-sl-btn vnd-sl-btn-ghost" id="vnd-sl-cancel">Cancel</button>
                <button type="submit" class="vnd-sl-btn vnd-sl-btn-navy" id="vnd-sl-save">Assign Sub-Ledger</button>
            </div>
        </form>
    </div>
</div>

</div><!-- /#vnd-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   VENDORS LIST   prefix: vnd_
   (routes live under /vendors — nginx blocks any URL containing /vendor/)
═══════════════════════════════════════════════════════════════ */

const VND_API = '<?php echo $_vnd_api; ?>' + '/vendors';

let vnd_page = 1;
const VND_PAGE_SIZE = 15;

const $vnd = id => document.getElementById(id);

async function vnd_bootstrapLoad() {
    await Promise.all([vnd_load(), vnd_loadTypes()]);
    vnd_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', vnd_bootstrapLoad);
} else {
    vnd_bootstrapLoad();
}

/* Vendor types fill both the filter and the form's select. */
async function vnd_loadTypes() {
    try {
        const res  = await fetch(`${VND_API}/types`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load vendor types.');

        const options = (json.data ?? []).map(t =>
            `<option value="${vnd_escAttr(t.vendor_type)}">${vnd_esc(t.vendor_type)}</option>`
        ).join('');
        $vnd('vnd-filter-type').insertAdjacentHTML('beforeend', options);
        $vnd('vnd-vendor-type').insertAdjacentHTML('beforeend', options);
    } catch (e) {
        vnd_toast(e.message, 'error');
    }
}

function vnd_bindEvents() {
    $vnd('vnd-refresh')?.addEventListener('click', () => { vnd_page = 1; vnd_load(); });
    $vnd('vnd-add')?.addEventListener('click', () => vnd_openModal('add'));
    $vnd('vnd-modal-close')?.addEventListener('click', vnd_closeModal);
    $vnd('vnd-cancel')?.addEventListener('click', vnd_closeModal);
    $vnd('vnd-form')?.addEventListener('submit', vnd_save);

    ['vnd-filter-type', 'vnd-filter-status'].forEach(id => {
        $vnd(id)?.addEventListener('change', () => { vnd_page = 1; vnd_load(); });
    });

    let searchTimer;
    const searchEl = $vnd('vnd-filter-search');
    const clearBtn = $vnd('vnd-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { vnd_page = 1; vnd_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        vnd_page = 1; vnd_load();
    });

    vnd_formGlSlBindEvents();
}

/* ── Load ──────────────────────────────────────────────────── */
async function vnd_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search : $vnd('vnd-filter-search')?.value || '',
            type   : $vnd('vnd-filter-type')?.value   || '',
            status : $vnd('vnd-filter-status')?.value || '',
            page   : vnd_page,
            limit  : VND_PAGE_SIZE,
        });
        const res  = await fetch(`${VND_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        vnd_renderTable(json.data ?? []);
        vnd_renderPagination(json.pagination ?? {});
        $vnd('vnd-kpi-total').textContent    = json.pagination?.total ?? (json.data ?? []).length;
        $vnd('vnd-kpi-active').textContent   = json.summary?.active   ?? '—';
        $vnd('vnd-kpi-inactive').textContent = json.summary?.inactive ?? '—';
    } catch (e) {
        vnd_renderTable([]);
        vnd_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function vnd_truthy(v) { return v === true || v === 1 || v === '1' || v === 't' || v === 'true'; }

function vnd_statusBadge(s) {
    const st  = (s || 'ACTIVE').toUpperCase();
    const map = { ACTIVE: ['nha-bdg-green', 'Active'], INACTIVE: ['nha-bdg-gray', 'Inactive'], BLACKLISTED: ['nha-bdg-red', 'Blacklisted'] };
    const [cls, label] = map[st] || ['nha-bdg-gray', st];
    return `<span class="nha-bdg ${cls}">${vnd_esc(label)}</span>`;
}

function vnd_renderTable(rows) {
    const tbody = $vnd('vnd-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No vendors found.</span></div></td></tr>';
        return;
    }
    const offset = (vnd_page - 1) * VND_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const contact = [r.contact_number, r.email].filter(Boolean).map(vnd_esc).join(' · ');
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><code class="nha-code">${vnd_esc(r.vendor_code)}</code></td>
        <td>
            <div style="font-weight:600;">${vnd_esc(r.vendor_name)}</div>
            ${r.tin ? `<div class="nha-td-sub" style="margin-top:2px;">TIN ${vnd_esc(r.tin)}</div>` : ''}
        </td>
        <td>${r.vendor_type ? `<span class="nha-bdg nha-bdg-blue">${vnd_esc(r.vendor_type)}</span>` : '—'}</td>
        <td>
            <div>${vnd_esc(r.contact_person || '—')}</div>
            ${contact ? `<div class="nha-td-sub" style="margin-top:2px;">${contact}</div>` : ''}
        </td>
        <td>
            ${vnd_truthy(r.is_vat_registered) ? '<span class="nha-bdg nha-bdg-teal">VAT</span>' : '<span class="nha-bdg nha-bdg-gray">Non-VAT</span>'}
            ${r.tax_classification ? `<div class="nha-td-sub" style="margin-top:2px;">${vnd_esc(r.tax_classification)}</div>` : ''}
        </td>
        <td><code class="nha-code">${vnd_esc(r.coa_account_code || '—')}</code></td>
        <td>
            ${r.sl_code ? `<span class="nha-bdg nha-bdg-green">${vnd_esc(r.sl_account_code || r.sl_code)}</span>` : '<span class="nha-bdg nha-bdg-gray">None</span>'}
        </td>
        <td>${vnd_statusBadge(r.status)}</td>
        <td>${vnd_actionMenu(r)}</td>
    </tr>`;
    }).join('');
}

function vnd_actionMenu(r) {
    const id = Number(r.vendor_id);
    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="vnd_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">
            <a class="nha-menu-item" href="javascript:void(0);" onclick="vnd_closeMenus();vnd_edit(${id});">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
            <a class="nha-menu-item" href="javascript:void(0);" onclick="vnd_closeMenus();vnd_openSlModal(${id}, '${vnd_escAttr(r.coa_account_id)}', '${vnd_escAttr(r.sl_code || '')}');">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.658 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                Link SL
            </a>
        </div>
    </div>`;
}

/* ── Actions dropdown — open/close (closes others, positions via fixed
   coords so the table's own overflow never clips it) ─────────────── */
function vnd_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    vnd_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.style.top = (rect.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
    drop.classList.add('open');
}

function vnd_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) vnd_closeMenus();
});
document.addEventListener('scroll', vnd_closeMenus, true);

/* ── Pagination ────────────────────────────────────────────── */
function vnd_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = VND_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $vnd('vnd-from').textContent  = from;
    $vnd('vnd-to').textContent    = to;
    $vnd('vnd-total').textContent = total;

    const container = $vnd('vnd-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="vnd_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="vnd_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="vnd_goTo(${current_page + 1})">›</button>`;
}

function vnd_goTo(page) { if (page >= 1) { vnd_page = page; vnd_load(); } }

/* ── Modal ─────────────────────────────────────────────────── */
const VND_TEXT_FIELDS = {          // payload key → input id
    vendor_code         : 'vnd-vendor-code',
    vendor_name         : 'vnd-vendor-name',
    vendor_type         : 'vnd-vendor-type',
    status              : 'vnd-status',
    tin                 : 'vnd-tin',
    tax_classification  : 'vnd-tax-class',
    address             : 'vnd-address',
    contact_person      : 'vnd-contact-person',
    contact_number      : 'vnd-contact-number',
    email               : 'vnd-email',
    bank_name           : 'vnd-bank-name',
    bank_account_number : 'vnd-bank-acct',
};

function vnd_openModal(mode, row = null) {
    vnd_hideErr();
    $vnd('vnd-mode').value = mode;
    $vnd('vnd-id').value   = row?.vendor_id || '';
    $vnd('vnd-modal-title').textContent = mode === 'edit' ? 'Edit Vendor' : 'Add Vendor';

    for (const [key, id] of Object.entries(VND_TEXT_FIELDS)) {
        $vnd(id).value = row?.[key] ?? '';
    }
    if (!row?.status) $vnd('vnd-status').value = 'ACTIVE';
    $vnd('vnd-vat').checked = vnd_truthy(row?.is_vat_registered);

    // GL / Sub-Ledger assignment (populate if editing)
    vnd_formGlSlReset();
    if (row?.coa_account_id) {
        vnd_formSelectGlAccount({ dataset: { id: row.coa_account_id, code: row.coa_account_code, name: row.coa_account_name } }, row.sl_code);
    }

    $vnd('vnd-vendor-code').disabled = mode === 'edit';
    $vnd('vnd-code-hint').textContent = mode === 'edit'
        ? 'Vendor codes cannot be changed after creation.'
        : 'Unique identifier. Cannot be changed after creation.';
    $vnd('vnd-overlay').classList.add('open');
}

function vnd_closeModal() {
    $vnd('vnd-overlay').classList.remove('open');
    $vnd('vnd-form').reset();
    vnd_formGlSlReset();
    vnd_hideErr();
}

function vnd_showErr(msg) {
    const el = $vnd('vnd-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function vnd_hideErr() {
    $vnd('vnd-err').style.display = 'none';
}

async function vnd_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${VND_API}/read/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load vendor.');
        vnd_openModal('edit', json.data);
    } catch (e) {
        vnd_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function vnd_save(ev) {
    ev.preventDefault();
    vnd_hideErr();
    const mode = $vnd('vnd-mode').value;
    const id   = $vnd('vnd-id').value;

    const payload = {};
    for (const [key, inputId] of Object.entries(VND_TEXT_FIELDS)) {
        payload[key] = $vnd(inputId).value.trim();
    }
    payload.is_vat_registered = $vnd('vnd-vat').checked;
    if (mode === 'edit') delete payload.vendor_code;       // locked

    const coaAccountId = $vnd('vnd-form-gl-account-id').value;
    const slCode       = $vnd('vnd-form-sl-selected-code-value').value;

    if (mode === 'add' && !payload.vendor_code) { vnd_showErr('Vendor code is required.'); return; }
    if (!payload.vendor_name)                   { vnd_showErr('Vendor name is required.'); return; }
    if (payload.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) { vnd_showErr('Email address is not valid.'); return; }

    if (coaAccountId) payload.coa_account_id = coaAccountId;  // Optional — Link SL can assign this later
    if (slCode)        payload.sl_code = slCode;

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${VND_API}/update/${encodeURIComponent(id)}` : `${VND_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save vendor.');

        vnd_toast(`Vendor ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        vnd_closeModal();
        vnd_load();
    } catch (e) {
        vnd_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function vnd_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function vnd_escAttr(text) {
    return vnd_esc(text);
}

function vnd_toast(msg, type = 'info') {
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

/* ── GL + Sub-Ledger Picker (Add/Edit Vendor form) ──────────── */
function vnd_formGlSlBindEvents() {
    const glSearch = $vnd('vnd-form-gl-search');
    if (glSearch) {
        glSearch.addEventListener('input', vnd_formSearchGlAccounts);
        document.addEventListener('click', (e) => {
            if (!e.target.closest('#vnd-form-gl-search') && !e.target.closest('#vnd-form-gl-dropdown')) {
                $vnd('vnd-form-gl-dropdown').style.display = 'none';
            }
            if (!e.target.closest('#vnd-form-sl-search') && !e.target.closest('#vnd-form-sl-dropdown')) {
                $vnd('vnd-form-sl-dropdown').style.display = 'none';
            }
        });
    }
    $vnd('vnd-form-sl-search')?.addEventListener('input', vnd_formSearchSlAccounts);
    $vnd('vnd-form-sl-create-btn')?.addEventListener('click', vnd_formCreateSlAccount);
}

function vnd_formGlSlReset() {
    $vnd('vnd-form-gl-account-id').value = '';
    $vnd('vnd-form-gl-search').value = '';
    $vnd('vnd-form-gl-dropdown').innerHTML = '';
    $vnd('vnd-form-gl-dropdown').style.display = 'none';
    $vnd('vnd-form-gl-selected').style.display = 'none';

    $vnd('vnd-form-sl-search').value = '';
    $vnd('vnd-form-sl-search').disabled = true;
    $vnd('vnd-form-sl-search').placeholder = 'Select a GL account first…';
    $vnd('vnd-form-sl-selected-code-value').value = '';
    $vnd('vnd-form-sl-selected-item').style.display = 'none';
    $vnd('vnd-form-sl-dropdown').innerHTML = '';
    $vnd('vnd-form-sl-dropdown').style.display = 'none';
    $vnd('vnd-form-sl-empty-note').style.display = 'none';
    $vnd('vnd-form-sl-new-title').value = '';
    $vnd('vnd-form-sl-section').style.opacity = '.5';
    $vnd('vnd-form-sl-section').style.pointerEvents = 'none';
}

let vnd_formGlSearchTimer;
function vnd_formSearchGlAccounts(ev) {
    const query = ev.target.value.trim();
    const dropdown = $vnd('vnd-form-gl-dropdown');
    clearTimeout(vnd_formGlSearchTimer);

    if (!query) {
        dropdown.style.display = 'none';
        return;
    }

    vnd_formGlSearchTimer = setTimeout(() => {
        fetch(`${VND_API}/../accounting/coa/search?q=${encodeURIComponent(query)}&limit=20`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data?.length) {
                    dropdown.innerHTML = json.data.map(a =>
                        `<div class="vnd-form-gl-item" data-id="${a.account_id}" data-code="${vnd_escAttr(a.account_code)}" data-name="${vnd_escAttr(a.account_title)}" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;transition:background .15s;">
                            <div style="font-weight:600;">${vnd_esc(a.account_code)}</div>
                            <div style="font-size:11px;color:#999;margin-top:2px;">${vnd_esc(a.account_title)}</div>
                        </div>`
                    ).join('');
                    dropdown.style.display = 'block';
                    dropdown.querySelectorAll('.vnd-form-gl-item').forEach(item => {
                        item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
                        item.addEventListener('mouseout', () => item.style.background = 'transparent');
                        item.addEventListener('click', () => vnd_formSelectGlAccount(item));
                    });
                } else {
                    dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No accounts found.</div>';
                    dropdown.style.display = 'block';
                }
            })
            .catch(e => vnd_showErr('GL search failed: ' + e.message));
    }, 300);
}

function vnd_formSelectGlAccount(item, preselectSlId = null) {
    const accountId = item.dataset.id;
    const code = item.dataset.code;
    const name = item.dataset.name;

    $vnd('vnd-form-gl-account-id').value = accountId;
    $vnd('vnd-form-gl-search').value = `${code} — ${name}`;
    $vnd('vnd-form-gl-dropdown').style.display = 'none';

    const selected = $vnd('vnd-form-gl-selected');
    selected.innerHTML = `<strong>${vnd_esc(code)}</strong> — ${vnd_esc(name)}`;
    selected.style.display = 'block';

    // Changing the GL invalidates any previously selected SL.
    $vnd('vnd-form-sl-selected-code-value').value = '';
    $vnd('vnd-form-sl-selected-item').style.display = 'none';
    $vnd('vnd-form-sl-search').value = '';
    $vnd('vnd-form-sl-search').disabled = false;
    $vnd('vnd-form-sl-search').placeholder = 'Type account code or description...';
    $vnd('vnd-form-sl-section').style.opacity = '1';
    $vnd('vnd-form-sl-section').style.pointerEvents = 'auto';

    vnd_formLoadSlCodesForAccount(accountId, preselectSlId);
}

async function vnd_formLoadSlCodesForAccount(accountId, preselectId = null) {
    try {
        const res = await fetch(`${VND_API}/../accounting/sl/by-account?account_id=${encodeURIComponent(accountId)}&limit=50`, { credentials: 'same-origin' });
        const json = await res.json();

        if (!json.success || !json.data?.length) {
            $vnd('vnd-form-sl-empty-note').style.display = 'block';
            $vnd('vnd-form-sl-dropdown').style.display = 'none';
            return;
        }

        $vnd('vnd-form-sl-empty-note').style.display = 'none';
        vnd_formRenderSlList(json.data);
        $vnd('vnd-form-sl-dropdown').style.display = 'none';

        if (preselectId) {
            const match = json.data.find(a => String(a.account_id) === String(preselectId));
            if (match) {
                $vnd('vnd-form-sl-search').value = match.account_code;
                $vnd('vnd-form-sl-selected-code-value').value = match.account_id;
                $vnd('vnd-form-sl-selected-code').textContent = match.account_code;
                $vnd('vnd-form-sl-selected-item').style.display = 'block';
            }
        }
    } catch (e) {
        vnd_showErr('Failed to load sub-ledgers: ' + e.message);
    }
}

function vnd_formRenderSlList(items) {
    const dropdown = $vnd('vnd-form-sl-dropdown');
    dropdown.innerHTML = items.map(item =>
        `<div class="vnd-form-sl-item" data-code="${vnd_escAttr(item.account_id)}" data-display="${vnd_escAttr(item.account_code)}" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;transition:background .15s;">
            <div style="font-weight:600;">${vnd_esc(item.account_code)}</div>
            ${item.account_title ? `<div style="font-size:11px;color:#999;margin-top:2px;">${vnd_esc(item.account_title)}</div>` : ''}
        </div>`
    ).join('');

    dropdown.style.display = 'block';
    dropdown.querySelectorAll('.vnd-form-sl-item').forEach(item => {
        item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
        item.addEventListener('mouseout', () => item.style.background = 'transparent');
        item.addEventListener('click', () => vnd_formSelectSlCode(item));
    });
}

function vnd_formSelectSlCode(item) {
    const slId = item.dataset.code;
    const display = item.dataset.display;
    $vnd('vnd-form-sl-search').value = display;
    $vnd('vnd-form-sl-selected-code-value').value = slId;
    $vnd('vnd-form-sl-selected-code').textContent = display;
    $vnd('vnd-form-sl-selected-item').style.display = 'block';
    $vnd('vnd-form-sl-dropdown').style.display = 'none';
}

let vnd_formSlSearchTimer;
function vnd_formSearchSlAccounts(ev) {
    const query = ev.target.value.trim();
    clearTimeout(vnd_formSlSearchTimer);

    if (!query) {
        $vnd('vnd-form-sl-dropdown').style.display = 'none';
        return;
    }

    vnd_formSlSearchTimer = setTimeout(() => {
        const accountId = $vnd('vnd-form-gl-account-id').value;
        if (!accountId) return;
        fetch(`${VND_API}/../accounting/sl/search?account_id=${encodeURIComponent(accountId)}&q=${encodeURIComponent(query)}&limit=50`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data?.length) {
                    vnd_formRenderSlList(json.data);
                } else {
                    const dropdown = $vnd('vnd-form-sl-dropdown');
                    dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No matching sub-ledgers. Try creating one below.</div>';
                    dropdown.style.display = 'block';
                }
            })
            .catch(e => vnd_showErr('Search failed: ' + e.message));
    }, 300);
}

async function vnd_formCreateSlAccount() {
    vnd_hideErr();
    const glAccountId = $vnd('vnd-form-gl-account-id').value;
    const title = $vnd('vnd-form-sl-new-title').value.trim();

    if (!glAccountId) { vnd_showErr('Select a GL account first.'); return; }
    if (!title)        { vnd_showErr('Enter a title for the new sub-ledger.'); return; }

    try {
        window.PagePreloader?.show();
        const res = await fetch(`${VND_API}/../accounting/sl/create`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ parent_account_id: glAccountId, account_title: title }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to create sub-ledger.');

        const created = json.data;
        $vnd('vnd-form-sl-search').value = created.account_code;
        $vnd('vnd-form-sl-selected-code-value').value = created.account_id;
        $vnd('vnd-form-sl-selected-code').textContent = created.account_code;
        $vnd('vnd-form-sl-selected-item').style.display = 'block';
        $vnd('vnd-form-sl-empty-note').style.display = 'none';
        $vnd('vnd-form-sl-new-title').value = '';

        vnd_toast('Sub-ledger account created.', 'success');
    } catch (e) {
        vnd_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── SL Modal Functions ─────────────────────────────────────── */
function vnd_openSlModal(vendorId, accountId, currentSl) {
    $vnd('vnd-sl-vendor-id').value = vendorId;

    // Reset GL section
    $vnd('vnd-sl-gl-account-id').value = '';
    $vnd('vnd-sl-gl-search').value = '';
    $vnd('vnd-sl-gl-dropdown').innerHTML = '';
    $vnd('vnd-sl-gl-dropdown').style.display = 'none';
    $vnd('vnd-sl-gl-selected').style.display = 'none';

    // Reset SL section
    $vnd('vnd-sl-search').value = '';
    $vnd('vnd-sl-search').disabled = true;
    $vnd('vnd-sl-search').placeholder = 'Select a GL account first…';
    $vnd('vnd-sl-selected-code-value').value = '';
    $vnd('vnd-sl-selected-item').style.display = 'none';
    $vnd('vnd-sl-dropdown').innerHTML = '';
    $vnd('vnd-sl-dropdown').style.display = 'none';
    $vnd('vnd-sl-empty-note').style.display = 'none';
    $vnd('vnd-sl-new-title').value = '';
    $vnd('vnd-sl-section-2').style.opacity = '.5';
    $vnd('vnd-sl-section-2').style.pointerEvents = 'none';

    vnd_slHideErr();
    $vnd('vnd-sl-overlay').classList.add('open');

    // Pre-fill GL if the vendor already has one assigned.
    if (accountId) {
        vnd_fetchAndSelectGl(accountId, currentSl);
    }
}

// Look up the GL account's display info, then wire up the SL section for it.
async function vnd_fetchAndSelectGl(accountId, currentSl) {
    try {
        const res = await fetch(`${VND_API}/../accounting/coa/search?q=${encodeURIComponent(accountId)}&limit=50`, { credentials: 'same-origin' });
        const json = await res.json();
        const match = json.success ? (json.data || []).find(a => String(a.account_id) === String(accountId)) : null;
        if (match) {
            vnd_selectGlAccount({ dataset: { id: match.account_id, code: match.account_code, name: match.account_title } });
        }
    } catch (e) {
        // Non-fatal — user can still pick a GL manually.
    }

    if (currentSl) {
        vnd_loadSlCodesForAccount(accountId, currentSl);
    }
}

function vnd_closeSlModal() {
    $vnd('vnd-sl-overlay').classList.remove('open');
    $vnd('vnd-sl-form').reset();
    vnd_slHideErr();
}

function vnd_slShowErr(msg) {
    const el = $vnd('vnd-sl-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function vnd_slHideErr() {
    $vnd('vnd-sl-err').style.display = 'none';
}

/* ── Step 1: GL Account picker ──────────────────────────────── */
let vnd_slGlSearchTimer;
$vnd('vnd-sl-gl-search')?.addEventListener('input', (ev) => {
    const query = ev.target.value.trim();
    const dropdown = $vnd('vnd-sl-gl-dropdown');
    clearTimeout(vnd_slGlSearchTimer);

    if (!query) {
        dropdown.style.display = 'none';
        return;
    }

    vnd_slGlSearchTimer = setTimeout(() => {
        fetch(`${VND_API}/../accounting/coa/search?q=${encodeURIComponent(query)}&limit=20`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data?.length) {
                    dropdown.innerHTML = json.data.map(a =>
                        `<div class="vnd-sl-gl-item" data-id="${a.account_id}" data-code="${vnd_escAttr(a.account_code)}" data-name="${vnd_escAttr(a.account_title)}" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;transition:background .15s;">
                            <div style="font-weight:600;">${vnd_esc(a.account_code)}</div>
                            <div style="font-size:11px;color:#999;margin-top:2px;">${vnd_esc(a.account_title)}</div>
                        </div>`
                    ).join('');
                    dropdown.style.display = 'block';
                    dropdown.querySelectorAll('.vnd-sl-gl-item').forEach(item => {
                        item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
                        item.addEventListener('mouseout', () => item.style.background = 'transparent');
                        item.addEventListener('click', () => vnd_selectGlAccount(item));
                    });
                } else {
                    dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No accounts found.</div>';
                    dropdown.style.display = 'block';
                }
            })
            .catch(e => vnd_slShowErr('GL search failed: ' + e.message));
    }, 300);
});

function vnd_selectGlAccount(item) {
    const accountId = item.dataset.id;
    const code = item.dataset.code;
    const name = item.dataset.name;

    $vnd('vnd-sl-gl-account-id').value = accountId;
    $vnd('vnd-sl-gl-search').value = `${code} — ${name}`;
    $vnd('vnd-sl-gl-dropdown').style.display = 'none';

    const selected = $vnd('vnd-sl-gl-selected');
    selected.innerHTML = `<strong>${vnd_esc(code)}</strong> — ${vnd_esc(name)}`;
    selected.style.display = 'block';

    // Changing the GL invalidates any previously selected SL.
    $vnd('vnd-sl-selected-code-value').value = '';
    $vnd('vnd-sl-selected-item').style.display = 'none';
    $vnd('vnd-sl-search').value = '';
    $vnd('vnd-sl-search').disabled = false;
    $vnd('vnd-sl-search').placeholder = 'Type account code or description...';
    $vnd('vnd-sl-section-2').style.opacity = '1';
    $vnd('vnd-sl-section-2').style.pointerEvents = 'auto';

    vnd_loadSlCodesForAccount(accountId);
}

/* ── Step 2: Sub-Ledger (child account) picker ──────────────── */
// Load SL codes for an account; optionally pre-select a known current SL id.
async function vnd_loadSlCodesForAccount(accountId, preselectId = null) {
    try {
        const res = await fetch(`${VND_API}/../accounting/sl/by-account?account_id=${encodeURIComponent(accountId)}&limit=50`, { credentials: 'same-origin' });
        const json = await res.json();

        if (!json.success || !json.data?.length) {
            // Quiet inline note instead of an overlapping dropdown box.
            $vnd('vnd-sl-empty-note').style.display = 'block';
            $vnd('vnd-sl-dropdown').style.display = 'none';
            return;
        }

        $vnd('vnd-sl-empty-note').style.display = 'none';
        vnd_renderSlList(json.data);
        $vnd('vnd-sl-dropdown').style.display = 'none'; // don't force it open — just cache for typing

        if (preselectId) {
            const match = json.data.find(a => String(a.account_id) === String(preselectId));
            if (match) {
                $vnd('vnd-sl-search').value = match.account_code;
                $vnd('vnd-sl-selected-code-value').value = match.account_id;
                $vnd('vnd-sl-selected-code').textContent = match.account_code;
                $vnd('vnd-sl-selected-item').style.display = 'block';
            }
        }
    } catch (e) {
        vnd_slShowErr('Failed to load sub-ledgers: ' + e.message);
    }
}

// Render SL dropdown list
function vnd_renderSlList(items) {
    const dropdown = $vnd('vnd-sl-dropdown');
    dropdown.innerHTML = items.map(item =>
        `<div class="vnd-sl-item" data-code="${vnd_escAttr(item.account_id)}" data-display="${vnd_escAttr(item.account_code)}" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;transition:background .15s;">
            <div style="font-weight:600;">${vnd_esc(item.account_code)}</div>
            ${item.account_title ? `<div style="font-size:11px;color:#999;margin-top:2px;">${vnd_esc(item.account_title)}</div>` : ''}
        </div>`
    ).join('');

    dropdown.style.display = 'block';
    dropdown.querySelectorAll('.vnd-sl-item').forEach(item => {
        item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
        item.addEventListener('mouseout', () => item.style.background = 'transparent');
        item.addEventListener('click', () => vnd_selectSlCode(item));
    });
}

// Select a SL code
function vnd_selectSlCode(item) {
    const slId = item.dataset.code;  // This is the account_id from COA
    const display = item.dataset.display;  // This is the account_code for display
    $vnd('vnd-sl-search').value = display;
    $vnd('vnd-sl-selected-code-value').value = slId;
    $vnd('vnd-sl-selected-code').textContent = display;
    $vnd('vnd-sl-selected-item').style.display = 'block';
    $vnd('vnd-sl-dropdown').style.display = 'none';
}

// Handle SL search input
let vnd_slSearchTimer;
$vnd('vnd-sl-search')?.addEventListener('input', (ev) => {
    const query = ev.target.value.trim();
    clearTimeout(vnd_slSearchTimer);

    if (!query) {
        $vnd('vnd-sl-dropdown').style.display = 'none';
        return;
    }

    vnd_slSearchTimer = setTimeout(() => {
        const accountId = $vnd('vnd-sl-gl-account-id').value;
        if (!accountId) return;
        fetch(`${VND_API}/../accounting/sl/search?account_id=${encodeURIComponent(accountId)}&q=${encodeURIComponent(query)}&limit=50`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(json => {
                if (json.success && json.data?.length) {
                    vnd_renderSlList(json.data);
                } else {
                    const dropdown = $vnd('vnd-sl-dropdown');
                    dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No matching sub-ledgers. Try creating one below.</div>';
                    dropdown.style.display = 'block';
                }
            })
            .catch(e => vnd_slShowErr('Search failed: ' + e.message));
    }, 300);
});

/* ── Step 2b: Create a new sub-ledger under the selected GL ───── */
$vnd('vnd-sl-create-btn')?.addEventListener('click', async () => {
    vnd_slHideErr();
    const glAccountId = $vnd('vnd-sl-gl-account-id').value;
    const title = $vnd('vnd-sl-new-title').value.trim();

    if (!glAccountId) { vnd_slShowErr('Select a GL account first.'); return; }
    if (!title)        { vnd_slShowErr('Enter a title for the new sub-ledger.'); return; }

    try {
        window.PagePreloader?.show();
        const res = await fetch(`${VND_API}/../accounting/sl/create`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ parent_account_id: glAccountId, account_title: title }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to create sub-ledger.');

        const created = json.data;
        $vnd('vnd-sl-search').value = created.account_code;
        $vnd('vnd-sl-selected-code-value').value = created.account_id;
        $vnd('vnd-sl-selected-code').textContent = created.account_code;
        $vnd('vnd-sl-selected-item').style.display = 'block';
        $vnd('vnd-sl-empty-note').style.display = 'none';
        $vnd('vnd-sl-new-title').value = '';

        vnd_toast('Sub-ledger account created.', 'success');
    } catch (e) {
        vnd_slShowErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
});

/* ── Save: GL + SL assignment ───────────────────────────────── */
$vnd('vnd-sl-form')?.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    vnd_slHideErr();

    const vendorId = $vnd('vnd-sl-vendor-id').value;
    const glAccountId = $vnd('vnd-sl-gl-account-id').value;
    const slCode = $vnd('vnd-sl-selected-code-value').value;

    if (!glAccountId) { vnd_slShowErr('Please select a GL account.'); return; }
    if (!slCode)       { vnd_slShowErr('Please select or create a sub-ledger account.'); return; }

    try {
        window.PagePreloader?.show();
        const res = await fetch(`${VND_API}/update/${encodeURIComponent(vendorId)}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ coa_account_id: glAccountId, sl_code: slCode }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to update vendor.');

        vnd_toast(`Sub-ledger assigned successfully.`, 'success');
        vnd_closeSlModal();
        vnd_load();
    } catch (e) {
        vnd_slShowErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
});

// Close button
document.addEventListener('click', (e) => {
    if (!e.target.closest('#vnd-sl-gl-search') && !e.target.closest('#vnd-sl-gl-dropdown')) {
        $vnd('vnd-sl-gl-dropdown').style.display = 'none';
    }
    if (!e.target.closest('#vnd-sl-search') && !e.target.closest('#vnd-sl-dropdown')) {
        $vnd('vnd-sl-dropdown').style.display = 'none';
    }
});
$vnd('vnd-sl-close')?.addEventListener('click', vnd_closeSlModal);
$vnd('vnd-sl-cancel')?.addEventListener('click', vnd_closeSlModal);
</script>
