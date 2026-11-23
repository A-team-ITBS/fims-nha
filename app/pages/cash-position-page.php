<?php
/**
 * Cash Position — Treasury module.
 *
 * Single section now: Position Updates (cashpositionupdates.php). Three
 * other tabs that originally shipped here were removed along the way:
 *   - Dashboard (cashpositiondashboard.php) — its KPIs, breakdown chart,
 *     grouped balance table, Working Paper summary, account-detail modal,
 *     and every dash*-prefixed function/element went with it.
 *   - Available Cash Balances (availablecashbalancestracking.php) — its
 *     KPIs, Book→Available→Usable table, low-balance alerts banner, and
 *     the Balance Trend modal went with it.
 *   - Reports (cashposreports.php) — every report type, the report
 *     filters, and the New FY Opening Balance modal went with it.
 * With no tabs left to switch between, the #cpsTabNav switcher is gone
 * too — Position Updates just renders directly.
 *
 * See app/services/CashPositionService.php's docblock for the schema
 * adaptation notes (no shared.fund_sources — fund_source is plain text on
 * fims.bank_accounts; "Region/Office" is fims.responsibility_code, not
 * uac.departments; no transaction ledger to auto-sum receipts/
 * disbursements, so those are plain editable fields; no WEEKLY
 * aggregation).
 *
 * Markup/CSS classes match app/fims-css/fims.css, same dark-blue scoped
 * theme convention as check-disbursement-voucher-page.php (#cpModule) and
 * bank-management-page.php (#bamModule).
 */

namespace PHPMaker2024\FIMS;

$cpsCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $cpsCssVer ?>" />

<div class="fims-wrap" id="cashPositionModule">

    <!-- same banner as User Access: app-page-header (styled by app/fims-css/fims.css, already loaded above) -->
    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-wallet"></i></div>
        <div>
            <h1 class="app-page-title">Cash Position</h1>
            <p class="app-page-subtitle">Daily cash position and manual updates — Treasury Department</p>
        </div>
    </div>

    <div class="fims-tab-underline-content active" id="cpsUpdates">
        <div class="fims-stat-grid" id="updKpis" style="grid-template-columns:repeat(3,1fr)">
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Active Accounts</span></div><div class="fims-stat-value" data-kpi="total_accounts">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Total Available Balance</span></div><div class="fims-stat-value" data-kpi="total_ending">₱0.00</div></div>
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Available Beginning Balance</span></div><div class="fims-stat-value" data-kpi="total_opening">₱0.00</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="updFilterBank" style="width:190px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="updFilterFundSource" style="width:170px"><option value="">All Fund Sources</option></select>
                    <input type="date" class="fims-input fims-input-sm" id="updFilterDate" title="Position Date (blank = each account's latest)">
                    <input type="text" class="fims-input fims-input-sm" id="updFilterSearch" placeholder="Account name / number / bank" style="width:220px">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="updFilterBtn"><i class="fas fa-filter mr-1"></i>Apply</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="updResetBtn">Reset</button>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Cash Position Records</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="updUploadSoaBtn"><i class="fas fa-file-upload mr-1"></i>Upload SOA</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="updBatchUploadBtn"><i class="fas fa-layer-group mr-1"></i>Batch Upload</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="updTable">
                    <thead>
                        <tr>
                            <th>Bank Account</th><th>Date</th>
                            <th class="text-right">Available Beginning Balance</th><th class="text-right">+ Receipts</th><th class="text-right">− Disbursements</th>
                            <th class="text-right">Closing Balance</th>
                            <th class="text-right">Available Balance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="8">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;gap:14px;margin-top:14px;align-items:stretch;">
            <div class="fims-table-panel" style="flex:2 1 0;min-width:0;margin-top:0;display:flex;flex-direction:column;">
                <div class="fims-panel-header">
                    <div class="fims-panel-title">SOA Comparison</div>
                </div>
                <div class="fims-table-wrap" style="flex:1;max-height:320px;overflow-y:auto;">
                    <table class="fims-table" id="soaCmpTable">
                        <thead>
                            <tr><th>Account</th><th class="text-right">Latest SOA Balance</th>
                                <th class="text-right">Difference</th><th class="text-right">Receipts</th><th class="text-right">Disbursements</th><th class="text-center">Status</th></tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="6">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="fims-table-panel" style="flex:1 1 0;min-width:0;margin-top:0;display:flex;flex-direction:column;">
                <div class="fims-panel-header">
                    <div class="fims-panel-title">Recent Update Activity</div>
                </div>
                <ul id="updActivityList" style="list-style:none;padding:12px 16px;margin:0;font-size:13px;flex:1;max-height:320px;overflow-y:auto;"></ul>
            </div>
        </div>

        <div class="fims-table-panel" style="margin-top:14px;">
            <div class="fims-panel-header">
                <div class="fims-panel-title">SOA Transactions (by Upload)</div>
            </div>
            <div id="soaGroupsList" style="padding:0;max-height:400px;overflow-y:auto;"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Cash Position Entry — two entry points:
           - openAddEntryModal(bankAccountId): a bank account with NO entry
             yet (Position Updates' "Add" action on a "No entry yet" row).
             Position Date is editable (defaults to today) and Beginning
             Balance is computed live as it changes.
           - openManualEntryModal(entryId): an EXISTING entry (Position
             Updates' "Edit" action). Position Date is locked to the
             existing row and only Restricted Funds is actually editable.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-manual-entry" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:480px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="meModalTitle">Cash Position Entry</div></div>
                    <button class="fims-modal-close" data-close="m-manual-entry">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="meCashPositionId">

                    <!-- Position Date, Beginning Balance, Bank Statement Balance,
                         Total Receipts, and Total Disbursements: all computed/
                         defaulted automatically (today's date; prior closing
                         balance or this account's GL balance; the posted
                         transaction ledger) and carried in the Save payload,
                         but never shown or hand-typed — only Restricted Funds
                         below is actually editable, for a brand-new entry and
                         an existing one alike. -->
                    <div style="display:none;">
                        <input type="date" id="mePositionDate">
                        <input type="number" step="0.01" id="meOpeningBalance">
                        <span id="meOpeningHint"></span>
                        <input type="number" step="0.01" id="meBankStatementBalance">
                        <input type="number" step="0.01" id="meTotalReceipts" value="0">
                        <input type="number" step="0.01" id="meTotalDisbursements" value="0">
                    </div>

                    <div class="fims-form-section-title">Restricted Funds (itemized — leave empty to use a flat amount)</div>
                    <table class="fims-table" id="meRestrictedTable">
                        <thead><tr><th>Label</th><th style="width:150px">Amount</th><th style="width:40px"></th></tr></thead>
                        <tbody id="meRestrictedBody"></tbody>
                    </table>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="meAddRestrictedRowBtn"><i class="fas fa-plus mr-1"></i>Add Line</button>
                    <div class="fims-form-group" style="margin-top:8px;max-width:240px;">
                        <label class="fims-label">Flat Restricted Amount</label>
                        <input type="number" step="0.01" class="fims-input" id="meRestrictedAmount" value="0">
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-manual-entry">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="meSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Upload SOA — ported from TREASURY's single-account
         #modal-bulk-upload flow.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-upload-soa" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:800px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Upload SOA (Bank Statement)</div></div>
                    <button class="fims-modal-close" data-close="m-upload-soa">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-alert fims-alert-info"><div class="fims-alert-body">Upload an .xlsx export of the bank statement. Columns are detected from the header row (Date, Description, Debit, Credit, Balance, Branch, Check No., Other Details — any order).</div></div>
                    <div class="fims-form-group" style="max-width:320px;">
                        <label class="fims-label">Bank Account *</label>
                        <select class="fims-select" id="soaBankAccount"><option value="">Select bank account...</option></select>
                    </div>
                    <div id="soaDropzone" style="border:2px dashed var(--fims-border);border-radius:8px;padding:24px;text-align:center;margin-top:10px;cursor:pointer;">
                        <i class="fas fa-file-excel" style="font-size:28px;color:var(--fims-text-subtle);"></i>
                        <p style="margin:8px 0 0;" id="soaDropzoneText">Click to choose an .xlsx file, or drag it here</p>
                        <input type="file" id="soaFileInput" accept=".xlsx" style="display:none;">
                    </div>
                    <div id="soaPreviewArea" style="margin-top:14px;display:none;">
                        <div class="fims-alert fims-alert-info" id="soaPreviewSummary"></div>
                        <div class="fims-table-wrap" style="max-height:280px;overflow-y:auto;">
                            <table class="fims-table">
                                <thead><tr><th>Date</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Check No.</th><th></th></tr></thead>
                                <tbody id="soaPreviewBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-upload-soa">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="soaParseBtn">Parse File</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="soaConfirmBtn" style="display:none;">Confirm &amp; Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Batch Upload SOA — ported from TREASURY's
         #modal-batch-upload flow: one row per bank account, each parsed
         and confirmed sequentially (not concurrently) through the same
         /upload-soa and /confirm-upload endpoints the single-account
         modal uses — there is no separate batch backend, by design (same
         as TREASURY, where this is purely a client-side orchestration
         layer over the per-account flow).
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-batch-upload-soa" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:920px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Batch Upload SOA</div></div>
                    <button class="fims-modal-close" data-close="m-batch-upload-soa">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-alert fims-alert-info"><div class="fims-alert-body">Pre-select the bank accounts you're uploading statements for, or add blank rows and pick accounts one at a time. Rows are processed one at a time.</div></div>

                    <div class="fims-form-section-title">Pre-select Bank Accounts</div>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                        <input type="text" class="fims-input fims-input-sm" id="batchSoaAccountSearch" placeholder="Search accounts..." style="max-width:260px;">
                        <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="batchSoaAddSelectedBtn"><i class="fas fa-plus mr-1"></i>Add Selected</button>
                    </div>
                    <div id="batchSoaAccountPicker" style="max-height:140px;overflow-y:auto;border:1px solid var(--fims-border);border-radius:6px;padding:8px;margin-bottom:14px;"></div>

                    <div class="fims-form-section-title">Upload Rows</div>
                    <div class="fims-table-wrap" style="max-height:260px;overflow-y:auto;">
                        <table class="fims-table">
                            <thead><tr><th style="width:220px">Bank Account</th><th>File</th><th style="width:220px">Status</th><th style="width:40px"></th></tr></thead>
                            <tbody id="batchSoaRows"></tbody>
                        </table>
                    </div>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="batchSoaAddRowBtn" style="margin-top:8px;"><i class="fas fa-plus mr-1"></i>Add Blank Row</button>
                    <div id="batchSoaSummary" class="fims-alert fims-alert-info" style="margin-top:12px;display:none;"></div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-batch-upload-soa">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="batchSoaParseAllBtn">Parse All</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="batchSoaConfirmAllBtn" disabled>Confirm &amp; Save All</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Disbursement Transactions (row-click drill-down from the
         Position Updates list — see cpsOpenTransactions())
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-disbursement-transactions" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:900px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Disbursement Transactions</div><div id="dtxSubtitle" style="font-size:12px;color:var(--fims-text-subtle);"></div></div>
                    <button class="fims-modal-close" data-close="m-disbursement-transactions">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-table-wrap">
                        <table class="fims-table">
                            <thead>
                                <tr>
                                    <th>Transaction ID</th><th>Reference No.</th><th>Type</th>
                                    <th>Description</th><th>Counterpart</th>
                                    <th class="text-right">Amount</th><th class="text-center">Status</th>
                                </tr>
                            </thead>
                            <tbody id="dtxTableBody"><tr class="fims-empty"><td colspan="7">Loading…</td></tr></tbody>
                        </table>
                    </div>
                    <div style="text-align:right;font-size:13px;font-weight:700;margin-top:10px;">
                        Total: <span id="dtxTotal">₱0.00</span>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-disbursement-transactions">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="cpsToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>
</div>

<style>
#cashPositionModule .fims-card-blue,
#cashPositionModule .fims-card-green,
#cashPositionModule .fims-card-amber,
#cashPositionModule .fims-card-red,
#cashPositionModule .fims-card-gray,
#cashPositionModule .fims-card-indigo { --fims-card-color: #000E3E; }

#cashPositionModule .fims-btn-primary { background:#000E3E; color:#fff; }
#cashPositionModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#cashPositionModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#cashPositionModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

/* .fims-modal-content has no flex layout globally (just border-radius/border —
   see app/fims-css/fims.css) — the display:flex/flex-direction:column below
   only controls how ITS OWN children (header/body/footer) lay out; it does
   NOT, by itself, make .fims-modal-content stretch to fill its PARENT
   (.fims-modal-dialog)'s available height — only a direct <form> child gets
   that globally (.fims-modal-dialog > form { flex:1 1 auto; min-height:0 }),
   and this page's modals use a plain <div class="fims-modal-content">, not a
   form. Without flex:1 1 auto/min-height:0 here too, .fims-modal-content
   sizes to its own content and content just stacks past .fims-modal-dialog's
   max-height, clipped by that dialog's own overflow:hidden instead of
   scrolling — the footer (and its Save button) disappears off-screen with no
   way to reach it. (The identical #colModule/#bamModule overrides in
   collections-page.php/bank-management-page.php are missing this same
   flex:1 1 auto/min-height:0 too — likely masked there by shorter content,
   not actually fixed.) */
#cashPositionModule .fims-modal-content { display:flex; flex-direction:column; flex:1 1 auto; min-height:0; overflow:hidden; }

.fims-form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--fims-text-subtle); margin-bottom:8px; }
.cps-group-header { cursor:pointer; background:var(--fims-table-head-bg, #f4f6fb); }

/* ── Dark mode ──
   The page hard-codes navy (#000E3E) for its primary / outline buttons and the active step (navy on dark), the fund-source
   group rows fall back to a light #f4f6fb (light text on near-white), and the selects use the browser's native grey. */
html.afims-dark[data-bs-theme="dark"] #cashPositionModule { color-scheme:dark; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-primary { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-primary:hover { background:#2E59B8; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-outline { color:#94AADB; border-color:#5B7FCF; background:transparent; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-outline:hover { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-ghost { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule :is(select.fims-select, .fims-input) {
    background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule select.fims-select option { background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .cps-group-header,
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .cps-group-header > td { background:var(--afims-surface-subtle, #22294A) !important; color:var(--afims-text, #E4ECF2) !important; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .cps-group-header :is(span, strong, small, td *) { color:inherit; }
</style>

<script>
(function () {
    const API = '/api/cash-position';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function toast(msg, isError) {
        const el = document.getElementById('cpsToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function peso(n) {
        n = Number(n) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#cashPositionModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.style.display = 'none'; });
    });
    function fillSelect(selector, items, placeholder, valueKey, labelFn) {
        const sel = document.querySelector(selector);
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        (items || []).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = labelFn(item);
            sel.appendChild(opt);
        });
        sel.value = current;
    }

    // ══════════════════════ POSITION UPDATES ══════════════════════
    let bankAccountsById = {};
    function loadUpdatesFilters() {
        fetch(API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                (json.data || []).forEach(function (b) { bankAccountsById[b.bank_account_id] = b; });
                fillSelect('#updFilterBank', json.data, 'All Banks', 'bank_account_id', i => i.account_name);
            }).catch(() => {});
        fetch(API + '/lookup/fund-sources', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#updFilterFundSource', (json.data || []).map(f => ({ v: f })), 'All Fund Sources', 'v', i => i.v)).catch(() => {});
    }
    function buildUpdParams() {
        const params = new URLSearchParams();
        params.set('draw', '1');
        const bank = document.getElementById('updFilterBank').value;
        const fs = document.getElementById('updFilterFundSource').value;
        const date = document.getElementById('updFilterDate').value;
        const search = document.getElementById('updFilterSearch').value.trim();
        if (bank) params.set('bank_account_id', bank);
        if (fs) params.set('fund_source', fs);
        if (date) params.set('position_date', date);
        if (search) params.set('search', search);
        return params;
    }
    function loadUpdatesKpis() {
        fetch(API + '/updates/kpis', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#updKpis [data-kpi]').forEach(function (el) {
                    const key = el.dataset.kpi;
                    el.textContent = key === 'total_accounts' ? (d[key] ?? 0) : peso(d[key] ?? 0);
                });
            }).catch(() => {});
    }
    function loadUpdatesList() {
        const tbody = document.querySelector('#updTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Loading…</td></tr>';
        fetch(API + '/updates/list?' + buildUpdParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">No records.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    // Closing Balance is the raw cp.closing_balance; Available
                    // Balance is that minus restricted funds (cp.available_balance,
                    // a DB-generated column) — both now shown since they
                    // legitimately differ whenever any amount is restricted.
                    // Row itself is clickable (not just a dedicated Actions
                    // column) — opens the disbursement-transactions drill-down
                    // for this exact account/date (cpsOpenTransactions()).
                    return '<tr class="cps-upd-row" style="cursor:pointer;" data-bank-account-id="' + r.bank_account_id + '" data-position-date="' + escapeHtml(r.position_date || '') +
                            '" data-account-name="' + escapeHtml(r.account_name || '') + '" title="Click to view disbursement transactions">' +
                        '<td>' + escapeHtml(r.account_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_name || '') + ' · ' + escapeHtml(r.account_number || '') + '</span></td>' +
                        '<td>' + escapeHtml(r.position_date || '') + '</td>' +
                        '<td class="text-right">' + peso(r.opening_balance) + '</td>' +
                        '<td class="text-right">' + peso(r.total_receipts) + '</td>' +
                        '<td class="text-right">' + peso(r.total_disbursements) + '</td>' +
                        '<td class="text-right">' + peso(r.closing_balance) + '</td>' +
                        '<td class="text-right"><strong>' + peso(r.available_balance) + '</strong></td>' +
                        '<td class="text-center"><span class="fims-badge fims-badge-green">Active</span></td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('.cps-upd-row').forEach(function (tr) {
                    tr.addEventListener('click', function () {
                        cpsOpenTransactions(this.dataset.bankAccountId, this.dataset.positionDate, this.dataset.accountName);
                    });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Could not reach the server.</td></tr>'; });
    }

    // ── Disbursement Transactions drill-down (row-click from the list above) ──
    function cpsOpenTransactions(bankAccountId, positionDate, accountName) {
        const tbody = document.getElementById('dtxTableBody');
        const subtitle = document.getElementById('dtxSubtitle');
        const totalEl = document.getElementById('dtxTotal');
        if (subtitle) subtitle.textContent = accountName + '  ·  ' + positionDate;
        if (totalEl) totalEl.textContent = peso(0);
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        openModal('m-disbursement-transactions');
        fetch(API + '/updates/transactions?bank_account_id=' + encodeURIComponent(bankAccountId) + '&position_date=' + encodeURIComponent(positionDate), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">No disbursement transactions for this date.</td></tr>'; return; }
                let total = 0;
                tbody.innerHTML = rows.map(function (t) {
                    total += parseFloat(t.amount) || 0;
                    return '<tr>' +
                        '<td>' + escapeHtml(t.transaction_id || '') + '</td>' +
                        '<td>' + escapeHtml(t.reference_number || '—') + '</td>' +
                        '<td>' + escapeHtml(t.transaction_type || '') + '</td>' +
                        '<td>' + escapeHtml(t.description || '—') + '</td>' +
                        '<td>' + escapeHtml(t.counterpart || '—') + '</td>' +
                        '<td class="text-right">' + peso(t.amount) + '</td>' +
                        '<td class="text-center">' + escapeHtml(t.status || '') + '</td>' +
                        '</tr>';
                }).join('');
                if (totalEl) totalEl.textContent = peso(total);
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('updFilterBtn')?.addEventListener('click', loadUpdatesList);
    document.getElementById('updResetBtn')?.addEventListener('click', function () {
        document.getElementById('updFilterBank').value = '';
        document.getElementById('updFilterFundSource').value = '';
        document.getElementById('updFilterDate').value = '';
        document.getElementById('updFilterSearch').value = '';
        loadUpdatesList();
    });
    function loadActivityLog() {
        const list = document.getElementById('updActivityList');
        fetch(API + '/updates/activity-log?limit=10', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { list.innerHTML = '<li style="color:var(--fims-text-subtle)">No recent activity.</li>'; return; }
                list.innerHTML = rows.map(function (a) {
                    const badge = a.action === 'CREATE' ? 'fims-badge-green' : a.action === 'SOA_UPLOAD' ? 'fims-badge-amber' : 'fims-badge-blue';
                    return '<li style="padding:6px 0;border-bottom:1px solid var(--fims-border);">' +
                        '<span class="fims-badge ' + badge + '">' + escapeHtml(a.action) + '</span> ' +
                        '<strong>' + escapeHtml(a.account_name) + '</strong> (' + escapeHtml(a.bank_name || '') + ') — ' + escapeHtml(a.position_date) + '<br>' +
                        '<span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(a.details || '') + ' · ' + escapeHtml(a.performed_at) + '</span>' +
                        '</li>';
                }).join('');
            })
            .catch(() => { list.innerHTML = '<li>Could not reach the server.</li>'; });
    }

    // ── SOA Comparison ──
    // Latest SOA Balance is read straight from the uploaded statement
    // (fims.soa_transactions) purely for cross-checking — it does NOT feed
    // back into Available Balance/Closing Balance/Receipts/Disbursements
    // anywhere (see CashPositionSoaService::confirmUpload()'s own comment:
    // SOA upload only records the statement, it doesn't auto-post to the
    // ledger or sync Cash Position).
    function loadSoaComparison() {
        const tbody = document.querySelector('#soaCmpTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="6">Loading…</td></tr>';
        fetch(API + '/soa-comparison', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="6">No SOA data uploaded yet.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    const hasSoa = r.latest_soa_balance != null;
                    const hasPos = r.available_balance != null;
                    const matched = hasSoa && hasPos && Math.abs(Number(r.latest_soa_balance) - Number(r.available_balance)) < 0.01;
                    const statusBadge = !hasSoa ? '<span class="fims-badge fims-badge-gray">No SOA</span>'
                        : !hasPos ? '<span class="fims-badge fims-badge-gray">No Position</span>'
                        : matched ? '<span class="fims-badge fims-badge-green">Matched</span>'
                        : '<span class="fims-badge fims-badge-red">Mismatch</span>';
                    return '<tr>' +
                        '<td>' + escapeHtml(r.account_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_name || '') + '</span></td>' +
                        '<td class="text-right">' + (hasSoa ? '<span style="color:var(--fims-green-text, #0C7539);font-weight:700;">' + peso(r.latest_soa_balance) + '</span>' : '—') + '</td>' +
                        '<td class="text-right">' + (hasSoa && hasPos ? peso(r.difference) : '—') + '</td>' +
                        '<td class="text-right">' + (r.total_receipts != null ? peso(r.total_receipts) : '—') + '</td>' +
                        '<td class="text-right">' + (r.total_disbursements != null ? peso(r.total_disbursements) : '—') + '</td>' +
                        '<td class="text-center">' + statusBadge + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="6">Could not reach the server.</td></tr>'; });
    }

    // ── SOA Transactions grouped-by-upload accordion ──
    function loadSoaGroups() {
        const list = document.getElementById('soaGroupsList');
        list.innerHTML = '<div class="fims-empty" style="padding:16px;">Loading…</div>';
        fetch(API + '/soa-groups?limit=10', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const groups = (json && json.data) ? json.data : [];
                if (!groups.length) { list.innerHTML = '<div class="fims-empty" style="padding:16px;">No SOA uploads yet.</div>'; return; }
                list.innerHTML = groups.map(function (g, gi) {
                    return '<div style="border-bottom:1px solid var(--fims-border);">' +
                        '<div class="cps-group-header" data-soa-toggle="soa-grp-' + gi + '" data-batch="' + escapeHtml(g.upload_batch_id) + '" data-bank="' + g.bank_account_id + '" data-loaded="0" style="padding:10px 14px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;">' +
                        '<div><i class="fas fa-caret-right mr-1"></i><strong>' + escapeHtml(g.account_name) + '</strong> — ' + escapeHtml(g.bank_name || '') + ' <span class="fims-badge">' + g.transaction_count + ' line(s)</span></div>' +
                        '<div style="font-size:12px;color:var(--fims-text-subtle);">Uploaded ' + escapeHtml(g.upload_datetime || '') + ' · Balance: <strong>' + peso(g.latest_balance) + '</strong></div>' +
                        '</div>' +
                        '<div id="soa-grp-' + gi + '" style="display:none;padding:0 14px 10px;"></div>' +
                        '</div>';
                }).join('');
                list.querySelectorAll('[data-soa-toggle]').forEach(function (header) {
                    header.addEventListener('click', function () {
                        const targetId = this.dataset.soaToggle;
                        const target = document.getElementById(targetId);
                        const willShow = target.style.display === 'none';
                        target.style.display = willShow ? '' : 'none';
                        const caret = this.querySelector('.fa-caret-right, .fa-caret-down');
                        if (caret) caret.className = 'fas ' + (willShow ? 'fa-caret-down' : 'fa-caret-right') + ' mr-1';
                        if (willShow && this.dataset.loaded === '0') {
                            this.dataset.loaded = '1';
                            loadSoaGroupRows(target, this.dataset.bank, this.dataset.batch);
                        }
                    });
                });
            })
            .catch(() => { list.innerHTML = '<div class="fims-empty" style="padding:16px;">Could not reach the server.</div>'; });
    }
    function loadSoaGroupRows(container, bankAccountId, batchId) {
        container.innerHTML = '<div class="fims-empty" style="padding:10px;">Loading…</div>';
        const params = new URLSearchParams({ bank_account_id: bankAccountId, upload_batch_id: batchId, limit: 500 });
        fetch(API + '/soa-transactions?' + params.toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { container.innerHTML = '<div class="fims-empty" style="padding:10px;">No lines.</div>'; return; }
                container.innerHTML = '<table class="fims-table"><thead><tr><th>Date</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th>Check No.</th></tr></thead><tbody>' +
                    rows.map(function (r) {
                        return '<tr><td>' + escapeHtml(r.transaction_date) + '</td><td>' + escapeHtml(r.description || '') + '</td>' +
                            '<td class="text-right">' + (Number(r.debit_amount) ? peso(r.debit_amount) : '—') + '</td>' +
                            '<td class="text-right">' + (Number(r.credit_amount) ? peso(r.credit_amount) : '—') + '</td>' +
                            '<td>' + escapeHtml(r.check_no || '') + '</td></tr>';
                    }).join('') + '</tbody></table>';
            })
            .catch(() => { container.innerHTML = '<div class="fims-empty" style="padding:10px;">Could not reach the server.</div>'; });
    }

    // ── Upload SOA modal ──
    let soaParsedRows = [];
    document.getElementById('updUploadSoaBtn')?.addEventListener('click', function () {
        fetch(API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#soaBankAccount', json.data, 'Select bank account...', 'bank_account_id', i => i.account_name)).catch(() => {});
        document.getElementById('soaFileInput').value = '';
        document.getElementById('soaDropzoneText').textContent = 'Click to choose an .xlsx file, or drag it here';
        document.getElementById('soaPreviewArea').style.display = 'none';
        document.getElementById('soaConfirmBtn').style.display = 'none';
        soaParsedRows = [];
        openModal('m-upload-soa');
    });
    const soaDropzone = document.getElementById('soaDropzone');
    const soaFileInput = document.getElementById('soaFileInput');
    soaDropzone?.addEventListener('click', function () { soaFileInput.click(); });
    soaDropzone?.addEventListener('dragover', function (e) { e.preventDefault(); soaDropzone.style.borderColor = '#000E3E'; });
    soaDropzone?.addEventListener('dragleave', function () { soaDropzone.style.borderColor = ''; });
    soaDropzone?.addEventListener('drop', function (e) {
        e.preventDefault();
        soaDropzone.style.borderColor = '';
        if (e.dataTransfer.files.length) { soaFileInput.files = e.dataTransfer.files; updateSoaFileName(); }
    });
    soaFileInput?.addEventListener('change', updateSoaFileName);
    function updateSoaFileName() {
        document.getElementById('soaDropzoneText').textContent = soaFileInput.files.length ? soaFileInput.files[0].name : 'Click to choose an .xlsx file, or drag it here';
    }

    document.getElementById('soaParseBtn')?.addEventListener('click', function () {
        const bankAccountId = document.getElementById('soaBankAccount').value;
        if (!bankAccountId) { toast('Select a bank account.', true); return; }
        if (!soaFileInput.files.length) { toast('Choose a file first.', true); return; }
        const formData = new FormData();
        formData.append('bank_account_id', bankAccountId);
        formData.append('soa_file', soaFileInput.files[0]);
        const btn = this;
        btn.disabled = true;
        fetch(API + '/upload-soa', { method: 'POST', headers: authHeaders(), body: formData })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) { toast(json.message || 'Could not parse file.', true); return; }
                soaParsedRows = json.parsed_rows || [];
                document.getElementById('soaPreviewArea').style.display = '';
                document.getElementById('soaPreviewSummary').innerHTML = escapeHtml(json.message);
                document.getElementById('soaPreviewBody').innerHTML = soaParsedRows.length
                    ? soaParsedRows.map(function (r) {
                        const style = r.is_duplicate ? 'opacity:.45;' : '';
                        const badge = r.is_duplicate ? ' <span class="fims-badge fims-badge-gray">Exists</span>' : (r.is_processed ? ' <span class="fims-badge fims-badge-amber">Processed</span>' : ' <span class="fims-badge fims-badge-green">New</span>');
                        return '<tr style="' + style + '"><td>' + escapeHtml(r.date) + '</td><td>' + escapeHtml(r.description) + '</td>' +
                            '<td class="text-right">' + (r.debit ? peso(r.debit) : '—') + '</td><td class="text-right">' + (r.credit ? peso(r.credit) : '—') + '</td>' +
                            '<td>' + escapeHtml(r.check_no || '') + '</td><td>' + badge + '</td></tr>';
                    }).join('')
                    : '<tr class="fims-empty"><td colspan="6">No rows could be read from this file — check that the header row matches the expected columns (Date, Description, Debit, Credit, Balance, Branch, Check No., Other Details) and that the data is on the first sheet.</td></tr>';
                // Always shown after a successful parse (even 0 new rows) —
                // confirmUpload() itself safely filters out duplicates again
                // server-side, so there's no harm in letting Save be clicked;
                // hiding it outright just blocks the user with no way to
                // retry/inspect what happened.
                document.getElementById('soaConfirmBtn').style.display = soaParsedRows.length ? '' : 'none';
                document.getElementById('soaConfirmBtn').textContent = 'Confirm & Save (' + (json.new_count || 0) + ' new)';
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    document.getElementById('soaConfirmBtn')?.addEventListener('click', function () {
        const bankAccountId = document.getElementById('soaBankAccount').value;
        const btn = this;
        btn.disabled = true;
        fetch(API + '/confirm-upload', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ bank_account_id: bankAccountId, parsed_rows: soaParsedRows }) })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? 'Saved.' : 'Could not save.'), !json.success);
                if (json.success) {
                    closeModal('m-upload-soa');
                    loadUpdatesKpis();
                    loadUpdatesList();
                    loadActivityLog();
                    loadSoaComparison();
                    loadSoaGroups();
                }
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    // ── Batch Upload SOA modal ──
    // Each row holds its own {bankAccountId, file, parsedRows, newCount, status} state,
    // keyed by a row id. "Parse All"/"Confirm All" walk the rows sequentially
    // (one fetch at a time, via Array.reduce chaining) — same as TREASURY,
    // not concurrently, so the per-row status updates stay readable and any
    // one row's failure doesn't race with the others.
    let batchSoaRowId = 0;
    const batchSoaJobs = {};
    let batchSoaBankAccounts = [];

    function addBatchSoaRow(preselectBankAccountId) {
        const id = ++batchSoaRowId;
        batchSoaJobs[id] = { bankAccountId: preselectBankAccountId || null, file: null, parsedRows: [], newCount: 0, status: 'pending' };
        const tr = document.createElement('tr');
        tr.dataset.rowId = id;
        tr.innerHTML =
            '<td><select class="fims-select fims-input-sm batch-soa-bank"><option value="">Select bank account...</option>' +
                batchSoaBankAccounts.map(b => '<option value="' + b.bank_account_id + '">' + escapeHtml(b.account_name) + '</option>').join('') +
            '</select></td>' +
            '<td><input type="file" class="batch-soa-file" accept=".xlsx" style="font-size:12px;"></td>' +
            '<td class="batch-soa-status" style="font-size:12px;color:var(--fims-text-subtle);">Not parsed</td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-batch-row><i class="fas fa-trash"></i></button></td>';
        if (preselectBankAccountId) tr.querySelector('.batch-soa-bank').value = preselectBankAccountId;
        tr.querySelector('.batch-soa-bank').addEventListener('change', function () { batchSoaJobs[id].bankAccountId = this.value; });
        tr.querySelector('.batch-soa-file').addEventListener('change', function () { batchSoaJobs[id].file = this.files[0] || null; });
        tr.querySelector('[data-remove-batch-row]').addEventListener('click', function () { delete batchSoaJobs[id]; tr.remove(); });
        document.getElementById('batchSoaRows').appendChild(tr);
        return id;
    }

    function renderBatchSoaAccountPicker(filterText) {
        const picker = document.getElementById('batchSoaAccountPicker');
        const needle = (filterText || '').toLowerCase().trim();
        const list = batchSoaBankAccounts.filter(b => !needle || b.account_name.toLowerCase().includes(needle) || (b.bank_name || '').toLowerCase().includes(needle));
        picker.innerHTML = list.length ? list.map(function (b) {
            return '<label style="display:block;padding:3px 0;">' +
                '<input type="checkbox" class="batch-soa-picker-cb" value="' + b.bank_account_id + '"> ' +
                escapeHtml(b.account_name) + ' <span style="color:var(--fims-text-subtle);font-size:11px;">— ' + escapeHtml(b.bank_name || '') + '</span>' +
                '</label>';
        }).join('') : '<span style="color:var(--fims-text-subtle);font-size:12px;">No matching accounts.</span>';
    }
    document.getElementById('batchSoaAccountSearch')?.addEventListener('input', function () { renderBatchSoaAccountPicker(this.value); });

    document.getElementById('batchSoaAddSelectedBtn')?.addEventListener('click', function () {
        const checked = Array.from(document.querySelectorAll('.batch-soa-picker-cb:checked')).map(cb => cb.value);
        if (!checked.length) { toast('Select at least one bank account.', true); return; }
        const alreadyAdded = new Set(Object.values(batchSoaJobs).map(j => String(j.bankAccountId)));
        let added = 0;
        checked.forEach(function (bankAccountId) {
            if (alreadyAdded.has(bankAccountId)) return;
            addBatchSoaRow(bankAccountId);
            alreadyAdded.add(bankAccountId);
            added++;
        });
        document.querySelectorAll('.batch-soa-picker-cb:checked').forEach(cb => { cb.checked = false; });
        if (added) toast('Added ' + added + ' row(s).');
        else toast('Those accounts are already in the row list.', true);
    });

    document.getElementById('updBatchUploadBtn')?.addEventListener('click', function () {
        document.getElementById('batchSoaRows').innerHTML = '';
        document.getElementById('batchSoaSummary').style.display = 'none';
        document.getElementById('batchSoaConfirmAllBtn').disabled = true;
        document.getElementById('batchSoaAccountSearch').value = '';
        Object.keys(batchSoaJobs).forEach(k => delete batchSoaJobs[k]);
        fetch(API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                batchSoaBankAccounts = json.data || [];
                renderBatchSoaAccountPicker('');
            })
            .catch(() => { renderBatchSoaAccountPicker(''); });
        openModal('m-batch-upload-soa');
    });
    document.getElementById('batchSoaAddRowBtn')?.addEventListener('click', function () { addBatchSoaRow(null); });

    function parseOneBatchRow(id) {
        const job = batchSoaJobs[id];
        const tr = document.querySelector('#batchSoaRows tr[data-row-id="' + id + '"]');
        const statusCell = tr.querySelector('.batch-soa-status');
        if (!job.bankAccountId || !job.file) {
            statusCell.textContent = 'Skipped (missing bank account or file)';
            return Promise.resolve();
        }
        statusCell.textContent = 'Parsing…';
        const formData = new FormData();
        formData.append('bank_account_id', job.bankAccountId);
        formData.append('soa_file', job.file);
        return fetch(API + '/upload-soa', { method: 'POST', headers: authHeaders(), body: formData })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) { statusCell.textContent = 'Parse failed: ' + (json.message || ''); job.status = 'failed'; return; }
                job.parsedRows = json.parsed_rows || [];
                job.newCount = json.new_count || 0;
                job.status = 'parsed';
                statusCell.textContent = job.newCount + ' new, ' + json.duplicate_count + ' duplicate(s)';
            })
            .catch(() => { statusCell.textContent = 'Could not reach the server.'; job.status = 'failed'; });
    }

    document.getElementById('batchSoaParseAllBtn')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        const ids = Object.keys(batchSoaJobs);
        ids.reduce(function (chain, id) { return chain.then(() => parseOneBatchRow(id)); }, Promise.resolve())
            .then(function () {
                const anyNew = ids.some(id => batchSoaJobs[id].newCount > 0);
                document.getElementById('batchSoaConfirmAllBtn').disabled = !anyNew;
            })
            .finally(() => { btn.disabled = false; });
    });

    function confirmOneBatchRow(id) {
        const job = batchSoaJobs[id];
        const tr = document.querySelector('#batchSoaRows tr[data-row-id="' + id + '"]');
        const statusCell = tr.querySelector('.batch-soa-status');
        if (job.status !== 'parsed' || !job.parsedRows.length) {
            return Promise.resolve({ saved: 0 });
        }
        statusCell.textContent = 'Saving…';
        return fetch(API + '/confirm-upload', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ bank_account_id: job.bankAccountId, parsed_rows: job.parsedRows }) })
            .then(r => r.json())
            .then(function (json) {
                statusCell.textContent = json.success ? ('Saved ' + json.saved + ' line(s)') : ('Failed: ' + (json.message || ''));
                return json;
            })
            .catch(function () {
                statusCell.textContent = 'Could not reach the server.';
                return { saved: 0 };
            });
    }

    document.getElementById('batchSoaConfirmAllBtn')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        const ids = Object.keys(batchSoaJobs);
        let totalSaved = 0;
        let totalFailed = 0;
        ids.reduce(function (chain, id) {
            return chain.then(() => confirmOneBatchRow(id)).then(function (result) {
                if (result && result.success) totalSaved += result.saved || 0;
                else if (batchSoaJobs[id].status === 'parsed') totalFailed++;
            });
        }, Promise.resolve())
            .then(function () {
                const summary = document.getElementById('batchSoaSummary');
                summary.style.display = '';
                summary.innerHTML = '<div class="fims-alert-body">Batch complete — ' + totalSaved + ' line(s) saved across ' + ids.length + ' row(s), ' + totalFailed + ' failure(s).</div>';
                loadUpdatesKpis();
                loadUpdatesList();
                loadActivityLog();
                loadSoaComparison();
                loadSoaGroups();
            })
            .finally(() => { btn.disabled = false; });
    });

    function initUpdates() { loadUpdatesFilters(); loadUpdatesKpis(); loadUpdatesList(); loadActivityLog(); loadSoaComparison(); loadSoaGroups(); }

    // ── Cash Position Entry modal — shared by two entry points,
    // openAddEntryModal() (a bank account with no entry yet) and
    // openManualEntryModal() (editing an existing one). Bank Statement
    // Balance/Total Receipts/Total Disbursements are always computed, never
    // shown or hand-typed; Restricted Funds is the only field an existing
    // entry's edit actually changes (Position Date/Beginning Balance are
    // additionally live for a brand-new entry — see refreshComputedBalanceForAdd()). ──
    function renderRestrictedRows(rows) {
        const tbody = document.getElementById('meRestrictedBody');
        tbody.innerHTML = (rows || []).map(function (item, idx) {
            return '<tr><td><input type="text" class="fims-input fims-input-sm me-rf-label" value="' + escapeHtml(item.label || '') + '"></td>' +
                '<td><input type="number" step="0.01" class="fims-input fims-input-sm me-rf-amount" value="' + (item.amount || 0) + '"></td>' +
                '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-rf="' + idx + '"><i class="fas fa-trash"></i></button></td></tr>';
        }).join('');
        tbody.querySelectorAll('[data-remove-rf]').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.closest('tr').remove(); });
        });
    }
    document.getElementById('meAddRestrictedRowBtn')?.addEventListener('click', function () {
        const tbody = document.getElementById('meRestrictedBody');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" class="fims-input fims-input-sm me-rf-label"></td>' +
            '<td><input type="number" step="0.01" class="fims-input fims-input-sm me-rf-amount" value="0"></td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-rf><i class="fas fa-trash"></i></button></td>';
        tr.querySelector('[data-remove-rf]').addEventListener('click', function () { tr.remove(); });
        tbody.appendChild(tr);
    });
    function getRestrictedBreakdown() {
        return Array.from(document.querySelectorAll('#meRestrictedBody tr')).map(function (tr) {
            return { label: tr.querySelector('.me-rf-label').value.trim(), amount: parseFloat(tr.querySelector('.me-rf-amount').value) || 0 };
        }).filter(item => item.amount > 0);
    }

    // Which bank account this modal instance is scoped to — set by
    // whichever of openAddEntryModal()/openManualEntryModal() opened it,
    // since the account is never itself a visible/editable field (implied
    // by which row's Add/Edit action was clicked).
    let meBankAccountId = null;
    // Set by refreshComputedBalanceForAdd() when a brand-new entry's
    // account has neither a prior fims.cash_position row nor a GL account
    // tagged to compute a Beginning Balance from — there's no reasonable
    // value to save in that case, so this blocks Save rather than letting
    // a fabricated number through.
    let meOpeningBalanceBlocked = false;

    function clearManualEntryModal() {
        meBankAccountId = null;
        meOpeningBalanceBlocked = false;
        document.getElementById('meCashPositionId').value = '';
        document.getElementById('mePositionDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('mePositionDate').disabled = false;
        document.getElementById('meOpeningBalance').value = '';
        document.getElementById('meOpeningHint').textContent = '';
        document.getElementById('meOpeningHint').style.color = '';
        document.getElementById('meBankStatementBalance').value = '';
        document.getElementById('meTotalReceipts').value = 0;
        document.getElementById('meTotalDisbursements').value = 0;
        document.getElementById('meRestrictedAmount').value = 0;
        renderRestrictedRows([]);
    }

    // Beginning Balance for a brand-new entry — pulled from the prior
    // day's closing balance, or (this account's very first entry ever) its
    // tagged GL account's balance, same convention CashPositionService::
    // getComputedBalance() has always used. Re-runs whenever Position Date
    // changes, since the computation is as-of that date.
    function refreshComputedBalanceForAdd() {
        const positionDate = document.getElementById('mePositionDate').value;
        const hint = document.getElementById('meOpeningHint');
        const field = document.getElementById('meOpeningBalance');
        if (!meBankAccountId || !positionDate) return;
        fetch(API + '/updates/computed-balance?bank_account_id=' + meBankAccountId + '&position_date=' + positionDate, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = json.data || {};
                if (d.opening_balance_source === 'PRIOR_CLOSING') {
                    field.value = d.opening_balance;
                    hint.textContent = 'Auto-pulled from the prior day\'s ending balance.';
                    hint.style.color = '';
                    meOpeningBalanceBlocked = false;
                } else if (d.opening_balance_source === 'OPENING_BALANCE') {
                    field.value = d.opening_balance;
                    hint.textContent = 'Auto-pulled from this account\'s recorded Opening Balance (first entry on record).';
                    hint.style.color = '';
                    meOpeningBalanceBlocked = false;
                } else if (d.opening_balance_source === 'GENERAL_LEDGER') {
                    field.value = d.opening_balance;
                    hint.textContent = 'Auto-pulled from this account\'s General Ledger balance (first entry on record).';
                    hint.style.color = '';
                    meOpeningBalanceBlocked = false;
                } else {
                    field.value = '';
                    hint.textContent = 'No prior entry and no GL account tagged — tag one in Bank Account Management first.';
                    hint.style.color = '#dc2626';
                    meOpeningBalanceBlocked = true;
                }
                document.getElementById('meTotalDisbursements').value = d.total_disbursements || 0;
                document.getElementById('meTotalReceipts').value = d.total_receipts || 0;
            })
            .catch(() => {});
    }
    document.getElementById('mePositionDate')?.addEventListener('change', function () {
        // Only a brand-new entry's date is actually editable (see
        // openManualEntryModal(), which disables this field) — guard here
        // too in case that ever changes.
        if (!document.getElementById('meCashPositionId').value && !this.disabled) refreshComputedBalanceForAdd();
    });

    // A bank account with no fims.cash_position row yet (Position Updates'
    // "Add" action, only shown on "No entry yet" rows) — Position Date
    // stays editable (defaults to today) since this is the one place it's
    // actually meaningful to choose.
    function openAddEntryModal(bankAccountId) {
        clearManualEntryModal();
        meBankAccountId = bankAccountId;
        document.getElementById('meModalTitle').textContent = 'New Cash Position Entry';
        refreshComputedBalanceForAdd();
        openModal('m-manual-entry');
    }

    // An existing entry (Position Updates' "Edit" action). Position Date
    // is locked to the entry's own date (changing it would really mean a
    // different record); Beginning Balance/Bank Statement Balance/Total
    // Receipts/Total Disbursements are carried over unchanged into the form
    // fields purely so the Save payload still has them —
    // CashPositionService::updateCashPosition() recomputes Closing Balance
    // from whatever's in the payload, not from the DB row, so dropping them
    // would zero out the balance on every restricted-funds-only edit.
    function openManualEntryModal(entryId) {
        clearManualEntryModal();
        document.getElementById('meModalTitle').textContent = 'Edit Cash Position Entry';
        fetch(API + '/updates/' + entryId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load entry.', true); return; }
                const d = json.data;
                meBankAccountId = d.bank_account_id;
                document.getElementById('meCashPositionId').value = d.cash_position_id;
                document.getElementById('mePositionDate').value = d.position_date;
                document.getElementById('mePositionDate').disabled = true;
                document.getElementById('meOpeningBalance').value = d.opening_balance;
                document.getElementById('meBankStatementBalance').value = d.bank_statement_balance || '';
                document.getElementById('meTotalReceipts').value = d.total_receipts;
                document.getElementById('meTotalDisbursements').value = d.total_disbursements;
                document.getElementById('meRestrictedAmount').value = d.restricted_amount || 0;
                renderRestrictedRows(d.restricted_breakdown || []);
                openModal('m-manual-entry');
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    document.getElementById('meSaveBtn')?.addEventListener('click', function () {
        const entryId = document.getElementById('meCashPositionId').value;
        const positionDate = document.getElementById('mePositionDate').value;
        if (!positionDate) { toast('Position date is required.', true); return; }
        if (!entryId && meOpeningBalanceBlocked) { toast('No prior entry and no GL account tagged — tag one in Bank Account Management first.', true); return; }
        if (!entryId && document.getElementById('meOpeningBalance').value === '') { toast('Beginning balance could not be determined for this date.', true); return; }

        const payload = {
            bank_account_id: meBankAccountId,
            position_date: positionDate,
            // Required by saveManualEntry()'s validation on a brand-new
            // entry, but — same as the old wizard's Update Type field —
            // never persisted as its own column; it only ever gated which
            // fields were editable in the UI, which no longer applies here.
            update_type: 'ADJUSTMENT',
            opening_balance: parseFloat(document.getElementById('meOpeningBalance').value) || 0,
            total_receipts: parseFloat(document.getElementById('meTotalReceipts').value) || 0,
            total_disbursements: parseFloat(document.getElementById('meTotalDisbursements').value) || 0,
            bank_statement_balance: document.getElementById('meBankStatementBalance').value,
            restricted_amount: parseFloat(document.getElementById('meRestrictedAmount').value) || 0,
            restricted_breakdown: getRestrictedBreakdown(),
        };
        const btn = this;
        btn.disabled = true;
        const url = entryId ? (API + '/updates/' + entryId + '/update') : (API + '/updates/save');
        fetch(url, { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? 'Cash position saved.' : 'Could not save.'), !json.success);
                if (json.success) {
                    closeModal('m-manual-entry');
                    loadUpdatesKpis();
                    loadUpdatesList();
                    loadActivityLog();
                }
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    // ── Boot ──
    initUpdates();
})();
</script>
