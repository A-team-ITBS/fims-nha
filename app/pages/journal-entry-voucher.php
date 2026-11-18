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

    /* Supporting Documents card — same convention as voucher/form.php's #vf-root .vf-card/.vf-dz/.vf-att-*. */
    #jel-root .jaf-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
    #jel-root .jaf-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
    #jel-root .jaf-dz { border: 2px dashed var(--afims-border, #E4ECF2); border-radius: 10px; padding: 18px; text-align: center; cursor: pointer; }
    #jel-root .jaf-dz.drag-over { border-color: var(--afims-primary, #0035A9); background: var(--afims-primary-soft, #E6EBF6); }
    #jel-root .jaf-dz-hint { font-size: 11px; color: var(--afims-text-muted, #62657E); margin-top: 4px; }
    #jel-root .jaf-att-link-row { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
    #jel-root .jaf-att-link-row input { flex: 1; font-size: 13px; padding: 8px 10px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; }
    #jel-root .jaf-att-link-row input#jaf-att-link-name { flex: 0 0 200px; }
    #jel-root .jaf-att-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
    #jel-root .jaf-att-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; }
    #jel-root .jaf-att-name { flex: 1; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #jel-root .jaf-att-name a { color: var(--afims-primary-text, #0035A9); text-decoration: none; }
    #jel-root .jaf-att-name a:hover { text-decoration: underline; }
    #jel-root .jaf-att-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 2px 6px; border-radius: 5px; }
    #jel-root .jaf-att-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }

    /* Actions column (last column — review-chain buttons + Print) stays visible without having to
       scroll the table all the way right: pinned via position:sticky, same convention as a frozen
       spreadsheet column. #jel-root gives these enough specificity to beat the shared .nha-tbl rules
       (hover background, etc.) they'd otherwise be overridden by. */
    #jel-root .nha-tbl-scroll { position: relative; }
    #jel-root .nha-tbl th:last-child,
    #jel-root .nha-tbl td:last-child {
        position: sticky;
        right: 0;
        white-space: nowrap;
        box-shadow: -1px 0 0 var(--afims-border, #E2E4ED) inset;
    }
    #jel-root .nha-tbl thead th:last-child { background: var(--afims-heading-fill, #000E3E); z-index: 2; }
    /* opaque (cells scrolled under it must not show through) but in the theme's surface colour, not a hard-coded white */
    #jel-root .nha-tbl tbody td:last-child { background: var(--afims-surface, #FFFFFF); z-index: 1; }
    #jel-root .nha-tbl tbody tr:hover td:last-child { background: var(--afims-surface-subtle, #F4F7FA); }

    /* Actions dropdown menu — same convention as app/pages/budget-req-page.php's nha-tools-menu. */
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
    .nha-menu-divider { border-top:1px solid var(--afims-border, #E4ECF2);margin:3px 0; }

    /* Dark mode — the sticky Actions cell, its hover, the Actions button and its dropdown (hover text was the navy fill colour) */
    html.afims-dark[data-bs-theme="dark"] #jel-root .nha-tbl tbody td:last-child { background: var(--afims-surface, #1A1F38); }
    html.afims-dark[data-bs-theme="dark"] #jel-root .nha-tbl tbody tr:hover td:last-child { background: var(--afims-surface-subtle, #22294A); }
    html.afims-dark[data-bs-theme="dark"] #jel-root .nha-tbl th:last-child,
    html.afims-dark[data-bs-theme="dark"] #jel-root .nha-tbl td:last-child { box-shadow: -1px 0 0 var(--afims-border-strong, #4A5270) inset; }
    html.afims-dark[data-bs-theme="dark"] .nha-menu-trigger { color:#FFFFFF;background:#3861BC;border-color:#3861BC; }
    html.afims-dark[data-bs-theme="dark"] .nha-menu-trigger:hover { background:#2E59B8;border-color:#2E59B8; }
    html.afims-dark[data-bs-theme="dark"] .nha-tools-dropdown { background:var(--afims-surface, #1A1F38);border-color:var(--afims-border-strong, #4A5270);
        box-shadow:0 8px 24px rgba(0,0,0,.45); }
    html.afims-dark[data-bs-theme="dark"] .nha-menu-item { color:var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] .nha-menu-item:hover { color:#FFFFFF;background:var(--afims-primary-soft, #0F2456); }
    html.afims-dark[data-bs-theme="dark"] .nha-menu-divider { border-top-color:var(--afims-border-strong, #4A5270); }
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
                        <th>Source / Ref No.</th>
                        <th>DV Number</th>
                        <th>Claimant</th>
                        <th>Book</th>
                        <th class="text-end">Total Debit</th>
                        <th class="text-end">Total Credit</th>
                        <th>JEV Status</th>
                        <th>DV Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="jel-tbody">
                    <tr><td colspan="11"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
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
        <div class="nha-modal nha-modal-full" role="dialog" aria-modal="true" aria-labelledby="jaf-modal-title">
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
                    <div class="nha-fg" style="max-width:220px;">
                        <label>Book <span class="nha-req">*</span></label>
                        <select id="jaf-book"><option value="">Select a book…</option></select>
                    </div>
                    <div class="nha-fg" style="max-width:240px;">
                        <label>Responsibility Center</label>
                        <select id="jaf-rc"><option value="">— None —</option></select>
                    </div>
                </div>
                <div class="nha-fg" style="margin-bottom:10px;">
                    <label>Particulars</label>
                    <textarea id="jaf-particulars" rows="2" style="width:100%;"></textarea>
                </div>

                <!-- Supporting Documents (optional) — same fims.file_attachments / multiple_node_id
                     convention as voucher/form.php's Attachments card. Files upload to storage as
                     soon as they're dropped; the batch itself (multiple_node_id) is only registered
                     when the Journal Entry is saved (jaf_save -> jaf_persistAttachments). -->
                <div class="jaf-card" id="jaf-att-card">
                    <p class="jaf-card-title">Supporting Documents</p>
                    <div class="jaf-dz" id="jaf-dz" onclick="document.getElementById('jaf-att-input').click()"
                         ondragover="event.preventDefault(); this.classList.add('drag-over');"
                         ondragleave="this.classList.remove('drag-over');"
                         ondrop="jaf_onAttDrop(event)">
                        <strong>Click to upload</strong> or drag and drop supporting documents here
                        <div class="jaf-dz-hint">Max 25MB each &middot; PDF, DOC, XLS, JPG, PNG</div>
                    </div>
                    <input type="file" id="jaf-att-input" multiple hidden
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" onchange="jaf_onAttChange(event)">
                    <div class="jaf-att-link-row">
                        <input type="text" id="jaf-att-link-name" placeholder="Document name">
                        <input type="text" id="jaf-att-link-url" placeholder="e.g. https://drive.google.com/…">
                        <button type="button" class="nha-btn nha-btn-ghost" onclick="jaf_addLinkAttachment()">+ Add Link</button>
                    </div>
                    <div class="jaf-att-list" id="jaf-att-list"></div>
                </div>

                <datalist id="jaf-coa-options"></datalist>

                <div style="overflow-x:auto;">
                    <table class="nha-tbl" style="min-width:880px;">
                        <thead><tr>
                            <th style="width:36px;">#</th>
                            <th>Account Code</th>
                            <th>Description</th>
                            <th class="text-end" style="width:120px;">Debit (₱)</th>
                            <th class="text-end" style="width:120px;">Credit (₱)</th>
                            <th style="width:30px;"></th>
                        </tr></thead>
                        <tbody id="jaf-lines-tbody"></tbody>
                        <tfoot><tr class="jaf-line-total">
                            <td colspan="3" class="text-end">Total</td>
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

                <!-- Review history — each endorse/approve step with its remarks (read-only; filled by jaf_loadForEdit). -->
                <div class="jaf-card" id="jaf-history-card" style="display:none;margin-top:14px;">
                    <p class="jaf-card-title">Review History &amp; Remarks</p>
                    <div id="jaf-history-list"></div>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="jaf-btn-print" style="display:none;margin-right:auto;" onclick="jaf_print()">Print</button>
                <button type="button" class="nha-btn nha-btn-ghost" onclick="jaf_close()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-primary" id="jaf-btn-save" onclick="jaf_save()">Save as Draft</button>
            </div>
        </div>
    </div>

    <!-- ── REVIEW-CHAIN CONFIRM MODAL (remarks, then Proceed / Cancel — prefix: jrm_) ──────── -->
    <div id="jrm-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-sm" role="dialog" aria-modal="true" aria-labelledby="jrm-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="jrm-title">Confirm</h3>
                <button type="button" class="nha-modal-close" onclick="jrm_close()" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div id="jrm-error" class="nha-error" style="display:none;">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    <span id="jrm-error-msg"></span>
                </div>
                <p id="jrm-msg" style="margin:0 0 12px;font-size:13px;"></p>
                <div class="nha-fg">
                    <label for="jrm-remarks">Remarks</label>
                    <textarea id="jrm-remarks" rows="3" style="width:100%;" placeholder="Add remarks (optional)…"></textarea>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="jrm_close()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-primary" id="jrm-proceed" onclick="jrm_proceed()">Proceed</button>
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
    jel_bindEvents();
    jel_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', jel_boot);
else jel_boot();

function jel_bindEvents() {
    $jel('jel-refresh')?.addEventListener('click', () => { jel_page = 1; jel_load(); });
    $jel('jel-filter-search')?.addEventListener('input', () => {
        clearTimeout(jel_searchTimer);
        jel_searchTimer = setTimeout(() => { jel_page = 1; jel_load(); }, 400);
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function jel_load() {
    window.PagePreloader?.show();
    jel_hideError();
    try {
        const params = new URLSearchParams({
            page      : jel_page,
            limit     : JEL_PAGE_SIZE,
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
    // Drop any dropdown(s) a previous render portaled out to <body> (see jel_toggleMenu) — the
    // fresh tbody.innerHTML below creates brand-new ones, so these would otherwise leak/pile up.
    document.querySelectorAll('body > .nha-tools-dropdown').forEach(d => d.remove());

    const tbody = $jel('jel-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="11"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No journal entry vouchers found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const isStandalone = !r.dv_id;
        const rowClick = isStandalone
            ? (r.jev_status === 'DRAFT' ? `jaf_openEdit(${r.jev_id})` : `jaf_openView(${r.jev_id})`)
            : `jel_viewDv(${r.dv_id})`;
        const actions = jel_actionButtons(r, isStandalone);
        return `<tr style="cursor:pointer;" onclick="${rowClick}">
        <td class="nha-mono">${jel_esc(r.jev_no) || '—'}</td>
        <td class="nha-td-date">${jel_fmtDate(r.jev_date)}</td>
        <td title="${jel_esc(r.source_module)}${r.doc_ref_no ? ' — ' + jel_esc(r.doc_ref_no) : ''}">
            ${jel_esc(r.source_module) || '—'}${r.doc_ref_no ? '<br><span class="nha-mono" style="font-size:11px;color:var(--afims-text-muted,#62657E);">' + jel_esc(r.doc_ref_no) + '</span>' : ''}
        </td>
        <td class="nha-mono">${r.dv_id ? `<a href="${JEL_VOUCHER_VIEW}?dv=${r.dv_id}" class="nha-tbl-link" onclick="event.stopPropagation()">${jel_esc(r.dv_no) || '—'}</a>` : '— (standalone)'}</td>
        <td style="max-width:220px;" title="${jel_esc(r.claimant_name)}">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${jel_esc(r.claimant_name) || '—'}</div>
        </td>
        <td>${jel_esc(r.book_name) || '—'}</td>
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

/* ── Actions dropdown menu (same "nha-tools-menu" convention as budget-req-page.php's
   bgr_actionButtons) — one "Actions" trigger per row holding View + the review-chain action +
   Print/Edit/Delete, instead of a row of separate buttons. ──────────────────────────────── */
const JEL_ICON_VIEW    = `<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`;
const JEL_ICON_EDIT    = `<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>`;
const JEL_ICON_ADVANCE = `<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>`;
const JEL_ICON_PRINT   = `<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a1 1 0 001-1v-4a1 1 0 00-1-1H9a1 1 0 00-1 1v4a1 1 0 001 1zm8-12V5a1 1 0 00-1-1H8a1 1 0 00-1 1v4h10z"/></svg>`;
const JEL_ICON_DELETE  = `<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>`;

function jel_actionButtons(r, isStandalone) {
    // Review chain (standalone JEVs and DV-linked PAYMENT JEVs — SETUP JEVs ride the DV's own
    // FOR_ACCOUNTING -> ... -> FOR_TREASURY chain instead, so they never get an advance item here).
    // DRAFT -> FOR_SECTION_CHIEF -> FOR_DIVISION_MANAGER -> FOR_DEPARTMENT_MANAGER -> POSTED.
    const nextStep  = { DRAFT: 'FOR_SECTION_CHIEF', FOR_SECTION_CHIEF: 'FOR_DIVISION_MANAGER', FOR_DIVISION_MANAGER: 'FOR_DEPARTMENT_MANAGER', FOR_DEPARTMENT_MANAGER: 'POSTED' };
    const nextLabel = { DRAFT: 'Submit for Review', FOR_SECTION_CHIEF: 'Section Chief: Review', FOR_DIVISION_MANAGER: 'Division Mgr: Review', FOR_DEPARTMENT_MANAGER: 'Dept Mgr: Approve & Post' };

    const viewItem = isStandalone
        ? (r.jev_status === 'DRAFT'
            ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="jel_closeMenus(); jaf_openEdit(${r.jev_id});">${JEL_ICON_EDIT}Edit</a>`
            : `<a class="nha-menu-item" href="javascript:void(0);" onclick="jel_closeMenus(); jaf_openView(${r.jev_id});">${JEL_ICON_VIEW}View</a>`)
        : `<a class="nha-menu-item" href="javascript:void(0);" onclick="jel_closeMenus(); jel_viewDv(${r.dv_id});">${JEL_ICON_VIEW}View</a>`;

    const canAdvance = isStandalone ? !!nextStep[r.jev_status] : (r.jev_type === 'PAYMENT' && !!nextStep[r.jev_status]);
    const advanceItem = canAdvance
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="jel_closeMenus(); jaf_jevStatus(${r.jev_id}, '${nextStep[r.jev_status]}', null);">${JEL_ICON_ADVANCE}${nextLabel[r.jev_status]}</a>`
        : '';

    const printItem = !isStandalone
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="jel_closeMenus(); jel_print(${r.dv_id}, null, ${r.jev_id});">${JEL_ICON_PRINT}Print</a>`
        : '';

    const deleteItem = (isStandalone && r.jev_status === 'DRAFT')
        ? `<a class="nha-menu-item danger" href="javascript:void(0);" onclick="jel_closeMenus(); jaf_delete(${r.jev_id}, null);">${JEL_ICON_DELETE}Delete</a>`
        : '';

    const items = [viewItem, advanceItem, printItem].filter(Boolean).join('');
    const deleteSection = deleteItem ? `<div class="nha-menu-divider"></div>${deleteItem}` : '';

    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="event.stopPropagation(); jel_toggleMenu(this, ${r.jev_id})">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown" data-jev-id="${r.jev_id}">${items}${deleteSection}</div>
    </div>`;
}

// Open/close. The sticky Actions column (see the #jel-root CSS above) gives each row's last <td>
// its own z-index to stay pinned — which also traps a plain nested position:fixed dropdown inside
// that cell's local stacking context, so a LATER row's opaque sticky cell can paint over an open
// EARLIER row's dropdown. Fix: while open, move the dropdown out to a direct child of <body> (a
// "portal", same trick most dropdown/tooltip libraries use to escape an ancestor's stacking/overflow)
// — found by data-jev-id rather than DOM nesting, since after the first move it's no longer inside
// its row's own .nha-tools-menu. jel_renderTable() purges any leftover body-level dropdowns before
// it rebuilds the table, so closed ones never pile up.
function jel_toggleMenu(btn, jevId) {
    const drop = document.querySelector(`.nha-tools-dropdown[data-jev-id="${jevId}"]`);
    const isOpen = drop.classList.contains('open');
    jel_closeMenus();
    if (isOpen) return;

    if (drop.parentElement !== document.body) document.body.appendChild(drop);
    const rect = btn.getBoundingClientRect();
    drop.style.top = (rect.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
    drop.classList.add('open');
}

function jel_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) jel_closeMenus();
});
document.addEventListener('scroll', jel_closeMenus, true);

// Print → the JEV form in the shared print preview (included below): the JEV and its lines come with
// their DV (GET /voucher/read/{id}); prp_jevFromDv() builds the payload.
async function jel_print(dvId, btn, jevId) {
    if (btn) btn.disabled = true;
    try {
        const res  = await jel_authFetch(`${JEL_API}/read/${dvId}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the voucher.');
        const dv = json.data;
        // Print the EXACT JEV this row is for — the SETUP JEV (dv.jev) or a specific PAYMENT JEV
        // (dv.payment_jevs), so a PAYMENT row prints its own Accounts Payable → Cash entry, not the
        // setup entry. A DV can have both since the Treasury check/ADA release adds a PAYMENT JEV.
        let jev = dv.jev;
        if (jevId && (!jev || Number(jev.jev_id) !== Number(jevId))) {
            jev = (dv.payment_jevs || []).find(p => Number(p.jev_id) === Number(jevId)) || jev;
        }
        if (!jev) throw new Error('This voucher has no journal entry yet.');
        await prp_open(prp_jevFromDv(dv, jev), { type: 'JEV' });
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
// The JEV's own lifecycle (DRAFT -> APPROVED -> POSTED, or REVERSED), separate from its DV's.
function jel_jevStatusBadge(s) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'], APPROVED: ['nha-bdg-amber', 'Approved'],
        FOR_SECTION_CHIEF: ['nha-bdg-amber', 'For Section Chief'],
        FOR_DIVISION_MANAGER: ['nha-bdg-purple', 'For Division Manager'],
        FOR_DEPARTMENT_MANAGER: ['nha-bdg-navy', 'For Department Manager'],
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
let JAF_RC  = [];   // responsibility centers (fims.responsibility_code), loaded once on first open
let jaf_lookupsReady = null; // promise, so a fast double-open doesn't double-fetch
let jaf_editId = null;       // jev_id being edited, or null when adding
let jaf_readOnly = false;    // viewing a non-DRAFT standalone JEV
let jaf_lineSeq = 0;

async function jaf_ensureLookups() {
    if (jaf_lookupsReady) return jaf_lookupsReady;
    jaf_lookupsReady = (async () => {
        try {
            const [bookJson, coaJson, rcJson] = await Promise.all([
                jel_authFetch(JEL_BASE + '/books/all').then(r => r.json()),
                jel_authFetch(JEL_BASE + '/chart_of_accounts/all?postable_only=1').then(r => r.json()),
                jel_authFetch(JEL_BASE + '/responsibility-center/all').then(r => r.json()),
            ]);
            $jel('jaf-book').innerHTML = '<option value="">Select a book…</option>' +
                (bookJson.data ?? []).map(b => `<option value="${b.book_id}">${jel_esc(b.name)}</option>`).join('');
            JAF_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
            JAF_RC  = rcJson.data ?? [];
            $jel('jaf-coa-options').innerHTML = JAF_COA.map(a => `<option value="${jel_esc(a.account_code)}">${jel_esc(a.account_title)}</option>`).join('');
            $jel('jaf-rc').innerHTML = '<option value="">— None —</option>' +
                JAF_RC.map(r => `<option value="${jel_esc(r.rc_id)}">${jel_esc(r.rc_id)} — ${jel_esc(r.rc_name)}</option>`).join('');
        } catch (e) {
            jaf_showError('Failed to load form data: ' + e.message);
        }
    })();

    return jaf_lookupsReady;
}

function jaf_setFieldsDisabled(disabled) {
    ['jaf-date', 'jaf-book', 'jaf-rc', 'jaf-particulars'].forEach(id => { $jel(id).disabled = disabled; });
    $jel('jaf-btn-save').style.display = disabled ? 'none' : '';
    $jel('jaf-btn-addline').disabled = disabled;
    document.querySelectorAll('#jaf-lines-tbody input, #jaf-lines-tbody select, #jaf-lines-tbody button').forEach(el => { el.disabled = disabled; });
    // Supporting Documents: hide the upload/link controls when read-only — the list (with its
    // Remove buttons already suppressed by jaf_renderAttachments()'s own jaf_readOnly check) stays.
    $jel('jaf-dz').style.display = disabled ? 'none' : '';
    const linkRow = document.querySelector('#jaf-att-card .jaf-att-link-row');
    if (linkRow) linkRow.style.display = disabled ? 'none' : '';
}

async function jaf_openAdd() {
    await jaf_ensureLookups();
    jaf_editId = null;
    jaf_readOnly = false;
    $jel('jaf-modal-title').textContent = 'Add Journal Entry Voucher';
    jaf_hideError();
    $jel('jaf-date').value = new Date().toISOString().slice(0, 10);
    $jel('jaf-book').value = '';
    $jel('jaf-rc').value = '';
    $jel('jaf-particulars').value = '';
    $jel('jaf-lines-tbody').innerHTML = '';
    jaf_lineSeq = 0;
    jaf_addLine(); jaf_addLine();
    jaf_attachments = [];
    jaf_attDirty = false;
    jaf_existingMultipleNodeId = null;
    jaf_renderAttachments();
    jaf_renderHistory([]); // nothing saved yet — no review history
    jaf_setFieldsDisabled(false);
    $jel('jaf-btn-print').style.display = 'none'; // nothing saved yet — no jev_no to print
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
        $jel('jaf-book').value = jev.book_id || '';
        $jel('jaf-rc').value = (jev.lines ?? []).find(l => l.rc_id)?.rc_id || '';
        $jel('jaf-particulars').value = jev.particulars || '';
        $jel('jaf-lines-tbody').innerHTML = '';
        jaf_lineSeq = 0;
        (jev.lines ?? []).forEach(l => jaf_addLine(l));
        if ((jev.lines ?? []).length < 2) { jaf_addLine(); jaf_addLine(); }
        jaf_recalc();

        jaf_attachments = (jev.file_attachments ?? []).map(a => ({
            document_name: a.file_meta?.document_name || 'Supporting Document',
            link: a.file_meta?.link || null,
            size: null,
        }));
        jaf_attDirty = false;
        jaf_existingMultipleNodeId = jev.multiple_node_id || null;
        jaf_renderAttachments();
        jaf_renderHistory(jev.status_history ?? []);

        jaf_setFieldsDisabled(readOnly);
        $jel('jaf-btn-print').style.display = ''; // a saved JEV always has a jev_no to print
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

// Print this standalone JEV via the shared print preview (prp_open + prp_jevFromDv — passing an
// empty {} as the "dv" lets its fallbacks (reference_no/payee_name/rfa_number) naturally come out
// blank, which is correct here since a standalone JEV has no DV at all). Re-fetches rather than
// reusing in-memory form state, so Print always reflects what was actually saved.
async function jaf_print() {
    if (!jaf_editId) return;
    window.PagePreloader?.show();
    try {
        const res  = await jel_authFetch(`${JEL_API}/journal-entry/${jaf_editId}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Journal Entry Voucher.');
        await prp_open(prp_jevFromDv({}, json.data), { type: 'JEV' });
    } catch (e) {
        jaf_showError(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function jaf_addLine(line) {
    const id = ++jaf_lineSeq;
    const tr = document.createElement('tr');
    tr.id = `jaf-line-${id}`;
    tr.innerHTML = `
        <td class="jaf-line-no" style="text-align:center;color:var(--afims-text-muted,#62657E);"></td>
        <td><input type="text" list="jaf-coa-options" class="nha-filter-input jaf-acct" style="width:100%;" placeholder="Search GL account code (UACS)…" value="${jel_esc(line?.account_code)}"></td>
        <td><input type="text" class="nha-filter-input jaf-desc" style="width:100%;" placeholder="Description" value="${jel_esc(line?.description)}"></td>
        <td><input type="number" step="0.01" min="0" class="nha-filter-input jaf-debit text-end" style="width:100%;" placeholder="0.00" value="${line?.debit > 0 ? line.debit : ''}" oninput="jaf_onAmountInput(this, 'debit')"></td>
        <td><input type="number" step="0.01" min="0" class="nha-filter-input jaf-credit text-end" style="width:100%;" placeholder="0.00" value="${line?.credit > 0 ? line.credit : ''}" oninput="jaf_onAmountInput(this, 'credit')"></td>
        <td><button type="button" class="jaf-remove-btn" title="Remove line" onclick="jaf_removeLine(${id})">&times;</button></td>
    `;
    $jel('jaf-lines-tbody').appendChild(tr);
    tr.querySelectorAll('input').forEach(el => el.addEventListener('input', jaf_recalc));
    jaf_renumberLines();
}
function jaf_removeLine(id) {
    $jel(`jaf-line-${id}`)?.remove();
    jaf_renumberLines();
    jaf_recalc();
}
// Keeps the leading "#" column in sync (01, 02, …) whenever a line is added/removed.
function jaf_renumberLines() {
    document.querySelectorAll('#jaf-lines-tbody tr').forEach((tr, idx) => {
        const cell = tr.querySelector('.jaf-line-no');
        if (cell) cell.textContent = String(idx + 1).padStart(2, '0');
    });
}
// A line is either a debit or a credit, never both — clearing the other side when one is typed
// into mirrors readone.php's vje_ grid convention.
function jaf_onAmountInput(el, side) {
    const tr = el.closest('tr');
    if (side === 'debit' && parseFloat(el.value) > 0) tr.querySelector('.jaf-credit').value = '';
    if (side === 'credit' && parseFloat(el.value) > 0) tr.querySelector('.jaf-debit').value = '';
    jaf_recalc();
}
// Every line shares the header-level Responsibility Center (jaf-rc) — there is no per-line RC
// control anymore.
function jaf_collectLines() {
    const rcId = $jel('jaf-rc').value.trim();
    return [...document.querySelectorAll('#jaf-lines-tbody tr')].map(tr => ({
        account_code: tr.querySelector('.jaf-acct').value.trim(),
        description : tr.querySelector('.jaf-desc').value.trim(),
        rc_id       : rcId,
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

/* ── Supporting Documents (optional — same fims.file_attachments / multiple_node_id convention as
   voucher/form.php's vf_ Attachments card; see FileUploadServices) ──
   Each file uploads to storage (POST /upload/file) as soon as it's picked — jaf_attachments just
   tracks the resulting {document_name, link} entries. The batch itself (multiple_node_id) is only
   registered — via POST /upload/multi-upload — when the Journal Entry is actually saved, see
   jaf_persistAttachments(). ─────────────────────────────────────────────────────────────────────── */
const JAF_ATT_MAX_BYTES = 26214400; // 25MB — mirrors FileUploadServices' own limit
let jaf_attachments = [];             // staged Supporting Documents: { document_name, link, size, uploading }
let jaf_attDirty = false;             // true once the attachments list has changed since open
let jaf_existingMultipleNodeId = null; // this JEV's current multiple_node_id (edit mode), reused as-is when attachments weren't touched

function jaf_onAttDrop(e) {
    e.preventDefault();
    $jel('jaf-dz').classList.remove('drag-over');
    if (e.dataTransfer.files?.length) jaf_stageAttFiles(e.dataTransfer.files);
}
function jaf_onAttChange(e) {
    if (e.target.files?.length) jaf_stageAttFiles(e.target.files);
    e.target.value = '';
}

async function jaf_stageAttFiles(fileList) {
    for (const file of Array.from(fileList)) {
        if (file.size > JAF_ATT_MAX_BYTES) {
            jel_showError(`"${file.name}" is over the 25MB limit and was not added.`);
            continue;
        }
        const row = { document_name: file.name.replace(/\.[^./]+$/, ''), link: null, size: file.size, uploading: true };
        jaf_attachments.push(row);
        jaf_renderAttachments();

        const form = new FormData();
        form.append('file', file);
        try {
            const res  = await jel_authFetch(`${JEL_BASE}/upload/file`, { method: 'POST', body: form });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Upload failed.');
            row.link = json.link;
            row.uploading = false;
            jaf_attDirty = true;
        } catch (e) {
            jaf_attachments = jaf_attachments.filter(r => r !== row);
            jel_showError(`Failed to upload "${file.name}": ${e.message}`);
        }
        jaf_renderAttachments();
    }
}

function jaf_removeAttachment(idx) {
    jaf_attachments.splice(idx, 1);
    jaf_attDirty = true;
    jaf_renderAttachments();
}

/** A typed reference (Google Drive, SharePoint, etc.) instead of an uploaded file — same
    fims.ip_filemeta.link convention FileUploadServices already accepts either way. */
function jaf_addLinkAttachment() {
    const nameEl = $jel('jaf-att-link-name');
    const urlEl  = $jel('jaf-att-link-url');
    const name = nameEl.value.trim();
    const url  = urlEl.value.trim();
    if (!name || !url) { jel_showError('Enter both a document name and a link.'); return; }
    if (!/^https?:\/\//i.test(url)) { jel_showError('Link must start with http:// or https://'); return; }
    jaf_attachments.push({ document_name: name, link: url, size: null });
    jaf_attDirty = true;
    nameEl.value = '';
    urlEl.value  = '';
    jaf_renderAttachments();
}

function jaf_renderAttachments() {
    $jel('jaf-att-list').innerHTML = jaf_attachments.map((a, idx) => {
        const isLink = /^https?:\/\//i.test(a.link || '');
        const name   = isLink
            ? `<a href="${jel_esc(a.link)}" target="_blank" rel="noopener noreferrer">${jel_esc(a.document_name)}</a>`
            : jel_esc(a.document_name);
        const removeBtn = jaf_readOnly ? '' : `<button type="button" class="jaf-att-remove" title="Remove" onclick="jaf_removeAttachment(${idx})">&times;</button>`;
        return `<div class="jaf-att-row">
            <span class="jaf-att-name" title="${jel_esc(a.document_name)}">${a.uploading ? '⏳ ' : ''}${name}${a.size ? ' <small>(' + jaf_fmtSize(a.size) + ')</small>' : ''}</span>
            ${removeBtn}
        </div>`;
    }).join('');
}

// Review History — one row per status change from fims.status_change_log (status, who, when, remarks).
function jaf_renderHistory(rows) {
    const card = $jel('jaf-history-card');
    if (!rows.length) { card.style.display = 'none'; $jel('jaf-history-list').innerHTML = ''; return; }
    $jel('jaf-history-list').innerHTML = rows.map(h => {
        return `<div class="jaf-att-row" style="align-items:flex-start;">
            <div style="flex:1;font-size:13px;">
                <div>${jel_jevStatusBadge(h.status)} <span style="color:var(--afims-text-muted,#62657E);font-size:12px;">${jel_esc(h.actor_name) || '—'}</span></div>
                <div style="margin-top:4px;white-space:pre-wrap;${h.remarks ? '' : 'color:var(--afims-text-muted,#62657E);font-style:italic;'}">${h.remarks ? jel_esc(h.remarks) : 'No remarks'}</div>
            </div>
        </div>`;
    }).join('');
    card.style.display = '';
}

function jaf_fmtSize(bytes) {
    return bytes >= 1024 * 1024 ? (bytes / (1024 * 1024)).toFixed(1) + ' MB' : Math.ceil(bytes / 1024) + ' KB';
}

/**
 * Registers the staged files as one Supporting Documents batch (POST /upload/multi-upload) and
 * returns the multiple_node_id to save on the Journal Entry — null if there's nothing to attach, or
 * undefined if attachments haven't changed since this JEV was opened (jaf_attDirty), in which case
 * the caller reuses whatever multiple_node_id the JEV already has.
 */
async function jaf_persistAttachments() {
    if (!jaf_attDirty) return undefined;
    if (!jaf_attachments.length) return null;

    const file_data = jaf_attachments.filter(a => a.link).map(a => ({ document_name: a.document_name, link: a.link }));
    if (!file_data.length) return null;

    const res  = await jel_authFetch(`${JEL_BASE}/upload/multi-upload`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ file_data }),
    });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to save attachments.');
    return json.multiple_node_id;
}

async function jaf_save() {
    jaf_hideError();
    const body = {
        jev_date    : $jel('jaf-date').value,
        book_id     : $jel('jaf-book').value,
        particulars : $jel('jaf-particulars').value.trim(),
        lines       : jaf_collectLines(),
    };
    window.PagePreloader?.show();
    try {
        const persistedNodeId = await jaf_persistAttachments();
        body.multiple_node_id = persistedNodeId === undefined ? jaf_existingMultipleNodeId : persistedNodeId;

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

// Advance a standalone JEV one step along the review chain (and post it on the final step). Mirrors the
// payment-JEV chain — server re-checks RC-012/sysadmin and the allowed transition (changeJevStatus()).
// Nothing executes on click: this just opens the remarks confirmation modal (jrm_). The status change
// itself only runs from jrm_proceed(); Cancel / X / Esc / backdrop click just close it.
let jrm_pending = null; // { jevId, status } awaiting Proceed

function jaf_jevStatus(jevId, status, btn) {
    jrm_pending = { jevId, status };
    $jel('jrm-title').textContent = 'Confirm — ' + status.replace(/_/g, ' ').toLowerCase().replace(/\b\w/g, c => c.toUpperCase());
    $jel('jrm-msg').textContent = `Move this Journal Entry to ${status.replace(/_/g, ' ')}?${status === 'POSTED' ? ' This posts it to the General Ledger.' : ''}`;
    $jel('jrm-remarks').value = '';
    $jel('jrm-error').style.display = 'none';
    $jel('jrm-proceed').disabled = false;
    $jel('jrm-overlay').classList.add('open');
    setTimeout(() => $jel('jrm-remarks').focus(), 50);
}

function jrm_close() {
    $jel('jrm-overlay').classList.remove('open');
    jrm_pending = null;
}

async function jrm_proceed() {
    if (!jrm_pending) return;
    const { jevId, status } = jrm_pending;
    const remarks = $jel('jrm-remarks').value.trim();
    $jel('jrm-error').style.display = 'none';
    $jel('jrm-proceed').disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await jel_authFetch(`${JEL_API}/journal-entry/${jevId}/jev-status`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status, remarks }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to update the Journal Entry.');
        jrm_close();
        jel_load();
    } catch (e) {
        // Keep the modal open so the remarks aren't lost; show the failure inside it.
        $jel('jrm-error-msg').textContent = e.message;
        $jel('jrm-error').style.display = 'flex';
        $jel('jrm-proceed').disabled = false;
    } finally {
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
    if (e.key !== 'Escape') return;
    if ($jel('jrm-overlay')?.classList.contains('open')) jrm_close();       // topmost first
    else if ($jel('jaf-overlay')?.classList.contains('open')) jaf_close();
});
$jel('jrm-overlay')?.addEventListener('mousedown', e => { if (e.target === e.currentTarget) jrm_close(); });
</script>
<?php
// Print → the shared print preview (prp_open + prp_jevFromDv).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
