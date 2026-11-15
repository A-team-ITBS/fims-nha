<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Journal Entry Voucher register. Most rows are still DV-driven — prepared from within a DV's
// Accounting stage (see app/pages/voucher/readone.php's "Journal Entry" section +
// DisbursementVoucherService::saveJournalEntry()) and read-only here; click one to open its
// voucher. This page ALSO lets Accounting add a standalone JEV with no DV at all — period-end
// ACCRUAL/DEPRECIATION/PREPAYMENTS/CLOSING entries real NGAS practice books directly in the
// General Journal (dv_id IS NULL, "03 - alter_table/33" — see the jaf_ "Add JEV" modal below,
// DisbursementVoucherService::createStandaloneJev()/postStandaloneJev()). Data comes from the
// REST API (GET /voucher/journal-entries in app/routes/voucher.php).
$_jel_api  = rtrim((string) Config('API.FIMS_URL'), '/');
$_jel_api  = preg_match('~/api$~', $_jel_api) ? $_jel_api : $_jel_api . '/api';
// Voucher.php's own view modal is what actually renders a JEV's lines — this page's "View" action
// just links there instead of duplicating that detail view.
$_jel_voucherView = 'Voucher';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #jel-root .jaf-line-total td { border-top: 2px solid var(--afims-border, #E2E4ED); font-weight: 700; }
    #jel-root .jaf-line-total td.jaf-l-val { text-align: right; font-family: "IBM Plex Mono", monospace; }
    #jel-root .jaf-remove-btn { background: none; border: none; cursor: pointer; color: var(--afims-danger, #C0392B); font-size: 16px; line-height: 1; padding: 4px 6px; border-radius: 5px; }
    #jel-root .jaf-remove-btn:hover { background: var(--afims-danger-soft, #FAE1E3); }
</style>

<div id="jel-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Journal Entry Vouchers</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="jel-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="jaf-btn-add" type="button" class="nha-btn-hdr" title="Add a standalone Journal Entry Voucher (no Disbursement Voucher)" onclick="jaf_openAdd()">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add JEV
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        Most Journal Entries are prepared by Accounting while processing a Disbursement Voucher — click a row with a DV Number to open its voucher. Use <strong>+ Add JEV</strong> for a standalone entry with no DV (period-end Accrual/Depreciation/Prepayments/Closing adjustments).
    </p>

    <!-- Error banner -->
    <div id="jel-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="jel-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="jel-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Type</label>
                <select id="jel-filter-type" class="nha-filter-select">
                    <option value="">All Types</option>
                    <option value="SETUP">Setup</option>
                    <option value="PAYMENT">Payment</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Classification</label>
                <select id="jel-filter-classification" class="nha-filter-select">
                    <option value="">All Classifications</option>
                    <option value="REGULAR">Regular</option>
                    <option value="ACCRUAL">Accrual</option>
                    <option value="DEPRECIATION">Depreciation</option>
                    <option value="PREPAYMENTS">Prepayments</option>
                    <option value="CLOSING">Closing</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="jel-filter-search" class="nha-filter-input" placeholder="JEV number, DV number, claimant…">
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
                        <th>JEV Number</th>
                        <th>JEV Date</th>
                        <th>Type</th>
                        <th>Classification</th>
                        <th>Source / Ref No.</th>
                        <th>DV Number</th>
                        <th>Claimant</th>
                        <th>Book</th>
                        <th>Fiscal Year</th>
                        <th class="text-end">Total Debit</th>
                        <th class="text-end">Total Credit</th>
                        <th>JEV Status</th>
                        <th>DV Status</th>
                        <th class="no-print">Print</th>
                    </tr>
                </thead>
                <tbody id="jel-tbody">
                    <tr><td colspan="14"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="jel-from">0</span>–<span id="jel-to">0</span></strong> of <strong><span id="jel-total">0</span></strong></span>
            <div id="jel-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ── ADD/EDIT JEV MODAL (standalone — no DV, prefix: jaf_) ──────── -->
    <div id="jaf-overlay" class="nha-overlay no-print">
        <div class="nha-modal" role="dialog" aria-modal="true" aria-labelledby="jaf-modal-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="jaf-modal-title">Add Journal Entry Voucher</h3>
                <button type="button" class="nha-modal-close" onclick="jaf_close()" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div id="jaf-error" class="nha-error" style="display:none;">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span id="jaf-error-msg"></span>
                </div>
                <p class="nha-hint" style="margin:0 0 10px;">No Disbursement Voucher — for period-end Accrual/Depreciation/Prepayments/Closing entries (or any other direct General Journal entry) Accounting books without a disbursement.</p>
                <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px;">
                    <div class="nha-fg" style="max-width:180px;">
                        <label>JEV Date <span class="nha-req">*</span></label>
                        <input type="date" id="jaf-date">
                    </div>
                    <div class="nha-fg" style="max-width:160px;">
                        <label>Fiscal Year <span class="nha-req">*</span></label>
                        <select id="jaf-fy"><option value="">Select…</option></select>
                    </div>
                    <div class="nha-fg" style="max-width:220px;">
                        <label>Book <span class="nha-req">*</span></label>
                        <select id="jaf-book"><option value="">Select a book…</option></select>
                    </div>
                    <div class="nha-fg" style="max-width:220px;">
                        <label>Classification</label>
                        <select id="jaf-classification">
                            <option value="REGULAR">Regular</option>
                            <option value="ACCRUAL">Accrual</option>
                            <option value="DEPRECIATION">Depreciation</option>
                            <option value="PREPAYMENTS">Prepayments</option>
                            <option value="CLOSING">Closing</option>
                        </select>
                    </div>
                </div>
                <div class="nha-fg" style="margin-bottom:10px;">
                    <label>Particulars</label>
                    <textarea id="jaf-particulars" rows="2" style="width:100%;"></textarea>
                </div>

                <datalist id="jaf-coa-options"></datalist>
                <datalist id="jaf-sl-options"></datalist>

                <div style="overflow-x:auto;">
                    <table class="nha-tbl" style="min-width:680px;">
                        <thead><tr>
                            <th>Account Code</th>
                            <th>SL Code</th>
                            <th class="text-end" style="width:120px;">Debit (₱)</th>
                            <th class="text-end" style="width:120px;">Credit (₱)</th>
                            <th style="width:30px;"></th>
                        </tr></thead>
                        <tbody id="jaf-lines-tbody"></tbody>
                        <tfoot><tr class="jaf-line-total">
                            <td class="text-end">Total</td>
                            <td></td>
                            <td class="jaf-l-val" id="jaf-total-debit">₱ 0.00</td>
                            <td class="jaf-l-val" id="jaf-total-credit">₱ 0.00</td>
                            <td></td>
                        </tr></tfoot>
                    </table>
                </div>
                <button type="button" id="jaf-btn-addline" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="jaf_addLine()">+ Add Line</button>
                <div style="margin-top:12px;">
                    <span id="jaf-balance-msg" style="font-weight:600;font-size:13px;"></span>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="jaf_close()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-primary" id="jaf-btn-save" onclick="jaf_save()">Save as Draft</button>
            </div>
        </div>
    </div>

</div><!-- /#jel-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   JOURNAL ENTRY VOUCHER REGISTER   prefix: jel_
═══════════════════════════════════════════════════════════════ */

const JEL_BASE = <?= json_encode($_jel_api) ?>;
const JEL_API  = JEL_BASE + '/voucher';
const JEL_VOUCHER_VIEW = <?= json_encode($_jel_voucherView) ?>;

const JEL_PAGE_SIZE = 15;
let jel_page = 1;
let jel_searchTimer = null;

const $jel = id => document.getElementById(id);

function jel_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function jel_boot() {
    await jel_loadFiscalYears();
    jel_bindEvents();
    jel_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', jel_boot);
else jel_boot();

function jel_bindEvents() {
    $jel('jel-refresh')?.addEventListener('click', () => { jel_page = 1; jel_load(); });
    $jel('jel-filter-fy')?.addEventListener('change', () => { jel_page = 1; jel_load(); });
    $jel('jel-filter-type')?.addEventListener('change', () => { jel_page = 1; jel_load(); });
    $jel('jel-filter-classification')?.addEventListener('change', () => { jel_page = 1; jel_load(); });
    $jel('jel-filter-search')?.addEventListener('input', () => {
        clearTimeout(jel_searchTimer);
        jel_searchTimer = setTimeout(() => { jel_page = 1; jel_load(); }, 400);
    });
}

/* Fiscal years come from fims.fiscal_year via GET /voucher/fiscal-years → {success, data:[{fiscal_yr}]}, newest first. */
async function jel_loadFiscalYears() {
    const sel = $jel('jel-filter-fy');
    try {
        const res  = await jel_authFetch(JEL_API + '/fiscal-years');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${jel_esc(r.fiscal_yr)}">${jel_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        jel_showError('Fiscal years: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function jel_load() {
    window.PagePreloader?.show();
    jel_hideError();
    try {
        const params = new URLSearchParams({
            page      : jel_page,
            limit     : JEL_PAGE_SIZE,
            fiscal_yr : $jel('jel-filter-fy').value,
            jev_type           : $jel('jel-filter-type').value,
            jev_classification : $jel('jel-filter-classification').value,
            search             : $jel('jel-filter-search').value.trim(),
        });
        const res  = await jel_authFetch(`${JEL_API}/journal-entries?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load journal entry vouchers.');

        jel_renderTable(json.data ?? []);
        jel_renderPagination(json.pagination ?? {});
    } catch (e) {
        jel_renderTable([]);
        jel_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function jel_renderTable(rows) {
    const tbody = $jel('jel-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="14"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No journal entry vouchers found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const isStandalone = !r.dv_id;
        const rowClick = isStandalone
            ? (r.jev_status === 'DRAFT' ? `jaf_openEdit(${r.jev_id})` : `jaf_openView(${r.jev_id})`)
            : `jel_viewDv(${r.dv_id})`;
        const actions = isStandalone
            ? (r.jev_status === 'DRAFT'
                ? `<button type="button" class="nha-btn-act" title="Edit" onclick="event.stopPropagation(); jaf_openEdit(${r.jev_id})">Edit</button>
                   <button type="button" class="nha-btn-act" title="Post to the General Ledger" onclick="event.stopPropagation(); jaf_post(${r.jev_id}, this)">Post</button>
                   <button type="button" class="nha-btn-act" title="Delete" onclick="event.stopPropagation(); jaf_delete(${r.jev_id}, this)">Delete</button>`
                : `<button type="button" class="nha-btn-act" title="View" onclick="event.stopPropagation(); jaf_openView(${r.jev_id})">View</button>`)
            : `<button type="button" class="nha-btn-act" title="Print this Journal Entry Voucher" onclick="event.stopPropagation(); jel_print(${r.dv_id}, this)">Print</button>`;
        return `<tr style="cursor:pointer;" onclick="${rowClick}">
        <td class="nha-mono">${jel_esc(r.jev_no) || '—'}</td>
        <td class="nha-td-date">${jel_fmtDate(r.jev_date)}</td>
        <td>${jel_typeBadge(r.jev_type)}</td>
        <td>${jel_classificationBadge(r.jev_classification)}</td>
        <td title="${jel_esc(r.source_module)}${r.doc_ref_no ? ' — ' + jel_esc(r.doc_ref_no) : ''}">
            ${jel_esc(r.source_module) || '—'}${r.doc_ref_no ? '<br><span class="nha-mono" style="font-size:11px;color:var(--afims-text-muted,#62657E);">' + jel_esc(r.doc_ref_no) + '</span>' : ''}
        </td>
        <td class="nha-mono">${r.dv_id ? `<a href="${JEL_VOUCHER_VIEW}?dv=${r.dv_id}" class="nha-tbl-link" onclick="event.stopPropagation()">${jel_esc(r.dv_no) || '—'}</a>` : '— (standalone)'}</td>
        <td style="max-width:220px;" title="${jel_esc(r.claimant_name)}">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${jel_esc(r.claimant_name) || '—'}</div>
        </td>
        <td>${jel_esc(r.book_name) || '—'}</td>
        <td>${jel_esc(r.fiscal_yr) || '—'}</td>
        <td class="text-end nha-mono">${jel_peso(r.total_debit)}</td>
        <td class="text-end nha-mono">${jel_peso(r.total_credit)}</td>
        <td>${jel_jevStatusBadge(r.jev_status)}</td>
        <td>${isStandalone ? '—' : jel_statusBadge(r.dv_status)}</td>
        <td class="no-print" style="white-space:nowrap;">${actions}</td>
    </tr>`;
    }).join('');
}

function jel_viewDv(dvId) {
    window.location.href = `${JEL_VOUCHER_VIEW}?dv=${dvId}`;
}

// Print → the JEV form in the shared print preview (included below): the JEV and its lines come with
// their DV (GET /voucher/read/{id}); prp_jevFromDv() builds the payload.
async function jel_print(dvId, btn) {
    if (btn) btn.disabled = true;
    try {
        const res  = await jel_authFetch(`${JEL_API}/read/${dvId}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the voucher.');
        if (!json.data.jev) throw new Error('This voucher has no journal entry yet.');
        await prp_open(prp_jevFromDv(json.data), { type: 'JEV' });
    } catch (e) {
        jel_showError(e.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* ── Pagination ────────────────────────────────────────────── */
function jel_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = JEL_PAGE_SIZE } = p;
    $jel('jel-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $jel('jel-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $jel('jel-total').textContent = total;

    const box = $jel('jel-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="jel_goTo(${i})">${i}</button>`);
    }
    box.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="jel_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="jel_goTo(${current_page + 1})">›</button>`;
}
function jel_goTo(page) { if (page >= 1) { jel_page = page; jel_load(); } }

/* ── Badges / formatters ───────────────────────────────────── */
function jel_statusBadge(s) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'], FOR_FMD_REVIEW: ['nha-bdg-amber', 'For FMD Review'],
        FOR_ACCOUNTING: ['nha-bdg-blue', 'For Accounting'],
        FOR_DIVISION_MANAGER: ['nha-bdg-purple', 'For Division Manager'], FOR_DEPARTMENT_MANAGER: ['nha-bdg-navy', 'For Department Manager'],
        FOR_TREASURY: ['nha-bdg-teal', 'For Treasury'],
        RELEASED: ['nha-bdg-green', 'Released'], REJECTED: ['nha-bdg-red', 'Rejected'], CANCELLED: ['nha-bdg-orange', 'Cancelled'],
    };
    const [cls, label] = map[s] || ['nha-bdg-gray', s || '—'];
    return `<span class="nha-bdg ${cls}">${jel_esc(label)}</span>`;
}
// SETUP = Accounting's original Dr/Cr entry when processing the DV; PAYMENT = the second JEV
// GeneralLedgerService::postPaymentJev() posts once Treasury actually releases the check/ADA.
function jel_typeBadge(t) {
    const [cls, label] = t === 'PAYMENT' ? ['nha-bdg-teal', 'Payment'] : ['nha-bdg-blue', 'Setup'];
    return `<span class="nha-bdg ${cls}">${jel_esc(label)}</span>`;
}
// Business/reporting nature of the entry (REGULAR default, or a period-end adjusting/closing
// kind) — independent of jel_typeBadge()'s SETUP/PAYMENT, which is GL-posting mechanics.
function jel_classificationBadge(c) {
    const map = {
        REGULAR: ['nha-bdg-gray', 'Regular'], ACCRUAL: ['nha-bdg-amber', 'Accrual'],
        DEPRECIATION: ['nha-bdg-blue', 'Depreciation'], PREPAYMENTS: ['nha-bdg-teal', 'Prepayments'],
        CLOSING: ['nha-bdg-orange', 'Closing'],
    };
    const [cls, label] = map[c] || ['nha-bdg-gray', c || 'Regular'];
    return `<span class="nha-bdg ${cls}">${jel_esc(label)}</span>`;
}
// The JEV's own lifecycle (DRAFT -> APPROVED -> POSTED, or REVERSED), separate from its DV's.
function jel_jevStatusBadge(s) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'], APPROVED: ['nha-bdg-amber', 'Approved'],
        POSTED: ['nha-bdg-green', 'Posted'], REVERSED: ['nha-bdg-red', 'Reversed'],
    };
    const [cls, label] = map[s] || ['nha-bdg-gray', s || '—'];
    return `<span class="nha-bdg ${cls}">${jel_esc(label)}</span>`;
}
function jel_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function jel_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function jel_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function jel_showError(msg) { $jel('jel-error').style.display = 'flex'; $jel('jel-error-msg').textContent = msg; }
function jel_hideError()    { $jel('jel-error').style.display = 'none'; }

/* ══════════════════════════════════════════════════════════════
   STANDALONE ADD/EDIT JEV (no DV)   prefix: jaf_
   POST /voucher/journal-entry/add, PUT .../update/{id}, GET .../{id},
   POST .../{id}/post, DELETE .../delete/{id} — see app/routes/voucher.php.
═══════════════════════════════════════════════════════════════ */
let JAF_COA = [];   // postable chart-of-accounts rows, loaded once on first open
let JAF_SL  = [];   // active SL masters, loaded once on first open
let jaf_lookupsReady = null; // promise, so a fast double-open doesn't double-fetch
let jaf_editId = null;       // jev_id being edited, or null when adding
let jaf_readOnly = false;    // viewing a non-DRAFT standalone JEV
let jaf_lineSeq = 0;

async function jaf_ensureLookups() {
    if (jaf_lookupsReady) return jaf_lookupsReady;
    jaf_lookupsReady = (async () => {
        try {
            const [fyJson, bookJson, coaJson, slJson] = await Promise.all([
                jel_authFetch(JEL_API + '/fiscal-years').then(r => r.json()),
                jel_authFetch(JEL_BASE + '/books/all').then(r => r.json()),
                jel_authFetch(JEL_BASE + '/chart_of_accounts/all?postable_only=1').then(r => r.json()),
                jel_authFetch(JEL_API + '/sl-codes').then(r => r.json()),
            ]);
            $jel('jaf-fy').innerHTML = '<option value="">Select…</option>' +
                (fyJson.data ?? []).map(r => `<option value="${jel_esc(r.fiscal_yr)}">${jel_esc(r.fiscal_yr)}</option>`).join('');
            $jel('jaf-book').innerHTML = '<option value="">Select a book…</option>' +
                (bookJson.data ?? []).map(b => `<option value="${b.book_id}">${jel_esc(b.name)}</option>`).join('');
            JAF_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
            JAF_SL  = slJson.data ?? [];
            $jel('jaf-coa-options').innerHTML = JAF_COA.map(a => `<option value="${jel_esc(a.account_code)}">${jel_esc(a.account_title)}</option>`).join('');
            $jel('jaf-sl-options').innerHTML  = JAF_SL.map(s => `<option value="${jel_esc(s.sl_code)}">${jel_esc(s.sl_name)}</option>`).join('');
        } catch (e) {
            jaf_showError('Failed to load form data: ' + e.message);
        }
    })();

    return jaf_lookupsReady;
}

function jaf_setFieldsDisabled(disabled) {
    ['jaf-date', 'jaf-fy', 'jaf-book', 'jaf-classification', 'jaf-particulars'].forEach(id => { $jel(id).disabled = disabled; });
    $jel('jaf-btn-save').style.display = disabled ? 'none' : '';
    $jel('jaf-btn-addline').disabled = disabled;
    document.querySelectorAll('#jaf-lines-tbody input, #jaf-lines-tbody button').forEach(el => { el.disabled = disabled; });
}

async function jaf_openAdd() {
    await jaf_ensureLookups();
    jaf_editId = null;
    jaf_readOnly = false;
    $jel('jaf-modal-title').textContent = 'Add Journal Entry Voucher';
    jaf_hideError();
    $jel('jaf-date').value = new Date().toISOString().slice(0, 10);
    $jel('jaf-fy').value = '';
    $jel('jaf-book').value = '';
    $jel('jaf-classification').value = 'REGULAR';
    $jel('jaf-particulars').value = '';
    $jel('jaf-lines-tbody').innerHTML = '';
    jaf_lineSeq = 0;
    jaf_addLine(); jaf_addLine();
    jaf_setFieldsDisabled(false);
    $jel('jaf-overlay').classList.add('open');
}

async function jaf_openEdit(jevId) {
    await jaf_loadForEdit(jevId, false);
}
async function jaf_openView(jevId) {
    await jaf_loadForEdit(jevId, true);
}
async function jaf_loadForEdit(jevId, readOnly) {
    await jaf_ensureLookups();
    jaf_hideError();
    window.PagePreloader?.show();
    try {
        const res  = await jel_authFetch(`${JEL_API}/journal-entry/${jevId}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Journal Entry Voucher.');
        const jev = json.data;

        jaf_editId = jevId;
        jaf_readOnly = readOnly;
        $jel('jaf-modal-title').textContent = readOnly ? `View Journal Entry Voucher — ${jel_esc(jev.jev_no)}` : `Edit Journal Entry Voucher — ${jel_esc(jev.jev_no)}`;
        $jel('jaf-date').value = String(jev.jev_date).slice(0, 10);
        $jel('jaf-fy').value = jev.fiscal_yr || '';
        $jel('jaf-book').value = jev.book_id || '';
        $jel('jaf-classification').value = jev.jev_classification || 'REGULAR';
        $jel('jaf-particulars').value = jev.particulars || '';
        $jel('jaf-lines-tbody').innerHTML = '';
        jaf_lineSeq = 0;
        (jev.lines ?? []).forEach(l => jaf_addLine(l));
        if ((jev.lines ?? []).length < 2) { jaf_addLine(); jaf_addLine(); }
        jaf_recalc();
        jaf_setFieldsDisabled(readOnly);
        $jel('jaf-overlay').classList.add('open');
    } catch (e) {
        jaf_showError(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function jaf_close() {
    $jel('jaf-overlay').classList.remove('open');
}

function jaf_addLine(line) {
    const id = ++jaf_lineSeq;
    const tr = document.createElement('tr');
    tr.id = `jaf-line-${id}`;
    tr.innerHTML = `
        <td><input type="text" list="jaf-coa-options" class="nha-filter-input jaf-acct" style="width:100%;" value="${jel_esc(line?.account_code)}"></td>
        <td><input type="text" list="jaf-sl-options" class="nha-filter-input jaf-sl" style="width:100%;" value="${jel_esc(line?.sl_code)}"></td>
        <td><input type="number" step="0.01" min="0" class="nha-filter-input jaf-debit text-end" style="width:100%;" value="${line?.debit > 0 ? line.debit : ''}" oninput="jaf_onAmountInput(this, 'debit')"></td>
        <td><input type="number" step="0.01" min="0" class="nha-filter-input jaf-credit text-end" style="width:100%;" value="${line?.credit > 0 ? line.credit : ''}" oninput="jaf_onAmountInput(this, 'credit')"></td>
        <td><button type="button" class="jaf-remove-btn" title="Remove line" onclick="jaf_removeLine(${id})">&times;</button></td>
    `;
    $jel('jaf-lines-tbody').appendChild(tr);
    tr.querySelectorAll('input').forEach(el => el.addEventListener('input', jaf_recalc));
}
function jaf_removeLine(id) {
    $jel(`jaf-line-${id}`)?.remove();
    jaf_recalc();
}
// A line is either a debit or a credit, never both — clearing the other side when one is typed
// into mirrors readone.php's vje_ grid convention.
function jaf_onAmountInput(el, side) {
    const tr = el.closest('tr');
    if (side === 'debit' && parseFloat(el.value) > 0) tr.querySelector('.jaf-credit').value = '';
    if (side === 'credit' && parseFloat(el.value) > 0) tr.querySelector('.jaf-debit').value = '';
    jaf_recalc();
}
function jaf_collectLines() {
    return [...document.querySelectorAll('#jaf-lines-tbody tr')].map(tr => ({
        account_code: tr.querySelector('.jaf-acct').value.trim(),
        sl_code     : tr.querySelector('.jaf-sl').value.trim(),
        debit       : parseFloat(tr.querySelector('.jaf-debit').value) || 0,
        credit      : parseFloat(tr.querySelector('.jaf-credit').value) || 0,
    })).filter(l => l.account_code || l.debit || l.credit);
}
function jaf_recalc() {
    const lines = jaf_collectLines();
    const totalDebit  = lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
    $jel('jaf-total-debit').textContent  = jel_peso(totalDebit);
    $jel('jaf-total-credit').textContent = jel_peso(totalCredit);
    const msg = $jel('jaf-balance-msg');
    const balanced = lines.length >= 2 && totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.005;
    msg.textContent = balanced ? '✓ Balanced' : 'Debits and credits must balance (≥2 lines).';
    msg.style.color = balanced ? 'var(--afims-success,#1D8A4C)' : 'var(--afims-danger,#C0392B)';
}

async function jaf_save() {
    jaf_hideError();
    const body = {
        jev_date          : $jel('jaf-date').value,
        fiscal_yr         : $jel('jaf-fy').value,
        book_id           : $jel('jaf-book').value,
        jev_classification: $jel('jaf-classification').value,
        particulars       : $jel('jaf-particulars').value.trim(),
        lines             : jaf_collectLines(),
    };
    window.PagePreloader?.show();
    try {
        const url    = jaf_editId ? `${JEL_API}/journal-entry/update/${jaf_editId}` : `${JEL_API}/journal-entry/add`;
        const method = jaf_editId ? 'PUT' : 'POST';
        const res    = await jel_authFetch(url, { method, headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
        const json   = await res.json();
        if (!json.success) throw new Error((json.errors?.length ? json.errors.join('\n') : null) || json.message || 'Failed to save.');
        jaf_close();
        jel_page = 1;
        jel_load();
    } catch (e) {
        jaf_showError(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function jaf_post(jevId, btn) {
    if (!confirm('Post this Journal Entry Voucher to the General Ledger? This cannot be undone from here.')) return;
    if (btn) btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await jel_authFetch(`${JEL_API}/journal-entry/${jevId}/post`, { method: 'POST' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to post.');
        jel_load();
    } catch (e) {
        jel_showError(e.message);
    } finally {
        if (btn) btn.disabled = false;
        window.PagePreloader?.hide();
    }
}

async function jaf_delete(jevId, btn) {
    if (!confirm('Delete this draft Journal Entry Voucher? This cannot be undone.')) return;
    if (btn) btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await jel_authFetch(`${JEL_API}/journal-entry/delete/${jevId}`, { method: 'DELETE' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete.');
        jel_load();
    } catch (e) {
        jel_showError(e.message);
    } finally {
        if (btn) btn.disabled = false;
        window.PagePreloader?.hide();
    }
}

function jaf_showError(msg) { $jel('jaf-error').style.display = 'flex'; $jel('jaf-error-msg').textContent = msg; }
function jaf_hideError()    { $jel('jaf-error').style.display = 'none'; }
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $jel('jaf-overlay')?.classList.contains('open')) jaf_close();
});
</script>
<?php
// Print → the shared print preview (prp_open + prp_jevFromDv).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
