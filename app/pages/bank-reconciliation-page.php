<?php
/**
 * Bank Reconciliation — Reports module.
 *
 * Listing (Bank Name / Account No / Account Name / Balance / Status) with a
 * "+ Bank Recon" button that opens the reconciliation worksheet modal: Bank +
 * "as of" Month, then Per Book vs Per Bank, each with its own Add/Less
 * adjustments, down to an Adjusted Balance. Reconciled when both adjusted
 * balances match (see app/services/BankReconciliationService.php).
 *
 * Follows the same lightweight fetch + manual render convention as
 * app/pages/bank-management-page.php (no DataTables dependency). Markup/CSS
 * classes match _shared/css/nha-fims.css.
 */

namespace PHPMaker2024\FIMS;

$brCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css');
?>
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= $brCssVer ?>" />

<div class="fims-wrap" id="brModule">

    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-money-check-dollar"></i></div>
        <div>
            <h1 class="app-page-title">Bank Reconciliation</h1>
            <p class="app-page-subtitle">Reconcile each bank account's book balance against the bank balance, month by month</p>
        </div>
        <div class="app-page-header-actions">
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="brAddBtn">
                <i class="fas fa-plus mr-1"></i>Bank Recon
            </button>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
        <div class="fims-toolbar">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <select class="fims-select fims-input-sm" id="brFilterStatus" style="width:160px">
                    <option value="">All Statuses</option>
                    <option value="RECONCILED">Reconciled</option>
                    <option value="UNRECONCILED">Unreconciled</option>
                    <option value="PENDING">Not Yet Reconciled</option>
                </select>
                <input type="text" class="fims-input fims-input-sm" id="brSearch" placeholder="Search bank, account no., name…" style="width:240px">
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="brFilterBtn"><i class="fas fa-filter mr-1"></i>Filter</button>
                <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="brResetBtn">Reset</button>
            </div>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:0">
        <div class="fims-panel-header">
            <div class="fims-panel-title" id="brTableSubtitle">Bank Reconciliation Listing</div>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table" id="brTable">
                <thead>
                    <tr>
                        <th>Bank Name</th>
                        <th>Account No.</th>
                        <th>Account Name</th>
                        <th>As of</th>
                        <th class="text-right">Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width:140px">Actions</th>
                    </tr>
                </thead>
                <tbody id="brTableBody"><tr class="fims-empty"><td colspan="7">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Bank Reconciliation worksheet
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-br-recon" style="display:none;">
        <div class="fims-modal-dialog u-modal-full">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Bank Reconciliation</div></div>
                    <button class="fims-modal-close" data-close="m-br-recon">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-form-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:14px;">
                        <div class="fims-form-group">
                            <label class="fims-label">Bank</label>
                            <select class="fims-select" id="field-bank-account-id">
                                <option value="">— Select —</option>
                            </select>
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">As of (Month)</label>
                            <input type="month" class="fims-input" id="field-period">
                        </div>
                    </div>

                    <!-- BOOK LEDGER (left) vs BANK STATEMENT (right), side by side for visual matching -->
                    <div class="br-ledger-grid">
                        <div class="br-ledger-wrap">
                            <div class="br-recon-col-title">BOOK LEDGER (General Ledger)</div>
                            <div class="fims-table-wrap" style="max-height:220px;overflow-y:auto;overflow-x:auto;">
                                <table class="fims-table br-ledger-table">
                                    <thead>
                                        <tr>
                                            <th style="width:10%">Date</th>
                                            <th style="width:13%">Ref. No.</th>
                                            <th style="width:19%">Particulars</th>
                                            <th class="text-right" style="width:15%">Debit</th>
                                            <th class="text-right" style="width:15%">Credit</th>
                                            <th class="text-right" style="width:14%">Balance</th>
                                            <th class="text-center" style="width:14%">Cleared</th>
                                        </tr>
                                    </thead>
                                    <tbody id="brLedgerBody"><tr class="fims-empty"><td colspan="7">Select a Bank and period above.</td></tr></tbody>
                                </table>
                            </div>
                            <p class="br-hint" style="margin:6px 2px 0;">Tick <strong>Cleared</strong> once the bank has processed a line. Unticked lines fall into Deposits in Transit / Outstanding Checks below.</p>
                        </div>

                        <div class="br-ledger-wrap">
                            <div class="br-recon-col-title">BANK STATEMENT (Uploaded SOA)</div>
                            <div class="fims-table-wrap" style="max-height:220px;overflow-y:auto;overflow-x:auto;">
                                <table class="fims-table br-ledger-table">
                                    <thead>
                                        <tr>
                                            <th style="width:10%">Date</th>
                                            <th style="width:13%">Chq. No.</th>
                                            <th style="width:22%">Particulars</th>
                                            <th class="text-right" style="width:16%">Debit</th>
                                            <th class="text-right" style="width:16%">Credit</th>
                                            <th class="text-center" style="width:23%">Matched</th>
                                        </tr>
                                    </thead>
                                    <tbody id="brSoaLedgerBody"><tr class="fims-empty"><td colspan="6">No SOA uploaded for this account/period yet.</td></tr></tbody>
                                </table>
                            </div>
                            <p class="br-hint" style="margin:6px 2px 0;">A line tagged <strong>Unmatched</strong> is on the bank statement but not yet in the book (e.g. a bank charge or interest) — book it via the Add/Less fields below.</p>
                        </div>
                    </div>

                    <div class="br-recon-grid">
                        <!-- PER BOOKS -->
                        <div class="br-recon-col">
                            <div class="br-recon-col-title">PER BOOKS</div>
                            <div class="fims-form-group">
                                <label class="fims-label">Unreconciled Balance <span class="br-hint">(ending ledger balance)</span></label>
                                <input type="text" class="fims-input br-mono" id="field-book-balance" readonly>
                            </div>
                            <div class="br-recon-sub">Add:</div>
                            <div class="fims-form-group"><label class="fims-label">Unrecorded Deposits</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-unrecorded-deposit" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Unrecorded Credit Memo</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-credit-memo" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Unrecorded Interest Income</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-interest-income" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Book Error <span class="br-hint">(under recording of deposits / misposting)</span></label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-book-error-under" value="0"></div>
                            <div class="br-recon-sub">Less:</div>
                            <div class="fims-form-group"><label class="fims-label">Unrecorded Debit Memo</label><input type="number" step="0.01" class="fims-input br-mono" id="field-less-debit-memo" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Unrecorded Withholding Tax</label><input type="number" step="0.01" class="fims-input br-mono" id="field-less-withholding-tax" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Unrecorded Bank Charges/Cost of Checkbooks</label><input type="number" step="0.01" class="fims-input br-mono" id="field-less-bank-charges" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Book Error <span class="br-hint">(over recording of deposits / misposting)</span></label><input type="number" step="0.01" class="fims-input br-mono" id="field-less-book-error-over" value="0"></div>
                            <div class="br-recon-total">
                                <span>Reconciled Balance</span>
                                <span class="br-mono" id="calc-adjusted-book">0.00</span>
                            </div>
                        </div>

                        <!-- PER BANK -->
                        <div class="br-recon-col">
                            <div class="br-recon-col-title">PER BANK</div>
                            <div class="fims-form-group">
                                <label class="fims-label">Unreconciled Balance <span class="br-hint">(per bank statement)</span></label>
                                <input type="number" step="0.01" class="fims-input br-mono" id="field-bank-balance" value="0">
                                <p class="br-hint" id="brSoaBalanceHint" style="margin:4px 2px 0;">No SOA uploaded for this account yet.</p>
                            </div>
                            <div class="br-recon-sub">Add:</div>
                            <div class="fims-form-group">
                                <label class="fims-label">Deposit in Transit</label>
                                <ul class="br-item-list" id="brDepositsInTransitList"><li class="br-item-empty">None</li></ul>
                                <input type="text" class="fims-input br-mono" id="field-add-deposit-in-transit" value="0.00" readonly>
                            </div>
                            <div class="fims-form-group"><label class="fims-label">Bank Error <span class="br-hint">(under validation of deposits)</span></label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-bank-error-under" value="0"></div>
                            <div class="br-recon-sub">Less:</div>
                            <div class="fims-form-group">
                                <label class="fims-label">Outstanding Checks</label>
                                <ul class="br-item-list" id="brOutstandingChecksList"><li class="br-item-empty">None</li></ul>
                                <input type="text" class="fims-input br-mono" id="field-less-outstanding-check" value="0.00" readonly>
                            </div>
                            <div class="fims-form-group"><label class="fims-label">Bank Error <span class="br-hint">(over validation of deposits)</span></label><input type="number" step="0.01" class="fims-input br-mono" id="field-less-bank-error-over" value="0"></div>
                            <div class="br-recon-total">
                                <span>Reconciled Balance</span>
                                <span class="br-mono" id="calc-adjusted-bank">0.00</span>
                            </div>
                        </div>
                    </div>

                    <div id="brStatusBanner" class="br-status-banner" style="display:none;"></div>

                    <!-- ══════════════════════════════════════════════
                         Adjusting Journal Entry Voucher — drafts the GL entry for the
                         Per Books Add/Less items above (Bank Error is excluded: it's the
                         bank's own error, not something NHA's books post). Save the
                         reconciliation first; this panel only appears once it has a
                         reconciliation_id.
                    ══════════════════════════════════════════════ -->
                    <div id="brJevPanel" class="br-jev-panel" style="display:none;margin-top:14px;">
                        <div class="br-recon-col-title" style="text-align:left;">Adjusting Journal Entry</div>

                        <div id="brJevAlreadyPosted" style="display:none;" class="br-jev-posted"></div>

                        <div id="brJevForm" style="display:none;">
                            <p class="br-hint" style="margin:0 0 8px;">These Per Books amounts aren't in the General Ledger yet. Pick a contra account for each, choose a Book, and post the adjusting entry — once reconciled, it posts straight to the General Ledger (no separate Accounting review step).</p>
                            <div class="fims-form-group" style="max-width:260px;">
                                <label class="fims-label">Book</label>
                                <select class="fims-select" id="field-jev-book"></select>
                            </div>
                            <table class="fims-table" style="margin-top:6px;">
                                <thead><tr><th>Item</th><th class="text-right" style="width:140px">Amount</th><th style="width:280px">Contra Account</th></tr></thead>
                                <tbody id="brJevItemsBody"></tbody>
                            </table>
                            <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="brPostJevBtn" style="margin-top:10px;">
                                <i class="fas fa-file-invoice mr-1"></i>Post Adjusting JEV to GL
                            </button>
                        </div>

                        <p id="brJevNothingToPost" class="br-hint" style="display:none;">No Per Books Add/Less amounts to post yet.</p>
                        <p id="brJevNeedsSave" class="br-hint" style="display:none;">Save this reconciliation first to post an adjusting JEV.</p>
                        <p id="brJevNeedsReconciled" class="br-hint" style="display:none;">Unreconciled — adjust the Per Bank side until the Adjusted Book and Adjusted Bank balances match before an adjusting entry can post.</p>
                    </div>

                    <div class="fims-form-group" style="margin-top:12px;">
                        <label class="fims-label">Remarks</label>
                        <textarea class="fims-input" id="field-remarks" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-br-recon">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="brSaveBtn">Save Reconciliation</button>
                </div>
            </div>
        </div>
    </div>

    <div id="brToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>
</div>

<style>
#brModule .fims-card-blue, #brModule .fims-card-green, #brModule .fims-card-amber,
#brModule .fims-card-orange, #brModule .fims-card-gray { --fims-card-color: #000E3E; }
#brModule .fims-btn-primary { background:#000E3E; color:#fff; }
#brModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#brModule .fims-modal-content { display:flex; flex-direction:column; min-height:0; overflow:hidden; }

/* page header: the "+ Bank Recon" button sits on the right of the banner, above its decorative circles, in the
   green the other pages use for their Add buttons (the page's navy primary would vanish on the navy banner) */
#brModule .app-page-header { flex-wrap:wrap; }
#brModule .app-page-header-actions { position:relative; z-index:1; margin-left:auto; display:flex; flex-wrap:wrap; gap:8px; }
#brModule .app-page-header-actions .fims-btn-primary,
html.afims-dark[data-bs-theme="dark"] #brModule .app-page-header-actions .fims-btn-primary { background:#11A752; border-color:#11A752; color:#fff; }
#brModule .app-page-header-actions .fims-btn-primary:hover,
html.afims-dark[data-bs-theme="dark"] #brModule .app-page-header-actions .fims-btn-primary:hover { background:#0E8F46; border-color:#0E8F46; color:#fff; }

#brModule .br-ledger-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
#brModule .br-ledger-wrap { border:1px solid var(--fims-border,#E4ECF2); border-radius:8px; padding:12px; }
@media (max-width:900px) { #brModule .br-ledger-grid { grid-template-columns:1fr; } }
#brModule .br-ledger-table { table-layout:fixed; width:100%; box-sizing:border-box; }
#brModule .br-ledger-table th, #brModule .br-ledger-table td { padding:6px 8px; font-size:12.5px; overflow-wrap:break-word; word-break:break-word; box-sizing:border-box; }
#brModule .br-ledger-table th { font-size:10.5px; white-space:normal; line-height:1.25; vertical-align:bottom; }
#brModule .br-ledger-table td { white-space:normal; }
#brModule .br-ledger-table td.text-right, #brModule .br-ledger-table td.fims-col-mono { white-space:nowrap; }
#brModule .br-ledger-table tr.br-row-uncleared td { color:#B42318; }
#brModule .br-ledger-table input[type="checkbox"] { width:16px; height:16px; cursor:pointer; vertical-align:middle; }
#brModule .br-ledger-table .fims-badge { white-space:nowrap; }
#brModule .br-match-cell { line-height:1.5; }
#brModule .br-match-ref { display:block; font-size:10px; font-family:var(--afims-mono, ui-monospace, 'IBM Plex Mono', monospace); color:var(--fims-text-subtle); margin-top:2px; }
#brModule .br-soa-tag { display:inline-block; margin-left:4px; padding:1px 5px; border-radius:4px; font-size:9px; font-weight:700; text-transform:uppercase; letter-spacing:.03em; vertical-align:middle; white-space:nowrap; }
#brModule .br-soa-tag-soa { background:#E6F0FD; color:#0B5FFF; }
#brModule .br-soa-tag-manual { background:#FFF4E0; color:#B2690A; }
#brModule .br-item-list { list-style:none; margin:0 0 6px; padding:0; font-size:12px; }
#brModule .br-item-list li { display:flex; justify-content:space-between; gap:8px; padding:2px 4px; border-bottom:1px dashed var(--fims-border,#E4ECF2); }
#brModule .br-item-list .br-item-empty { color:var(--fims-text-subtle); justify-content:center; border-bottom:none; }
#brModule .br-recon-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
#brModule .br-recon-col { border:1px solid var(--fims-border,#E4ECF2); border-radius:8px; padding:12px; }
#brModule .br-recon-col-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--fims-text-subtle); margin-bottom:10px; text-align:center; }
#brModule .br-recon-sub { font-size:11px; font-weight:600; color:var(--fims-text-subtle); margin:8px 0 4px; }
#brModule .br-mono { font-family:var(--afims-mono, ui-monospace, 'IBM Plex Mono', monospace); text-align:right; }
#brModule .br-hint { font-weight:400; font-size:10px; color:var(--fims-text-subtle); text-transform:none; letter-spacing:0; }
#brModule .br-recon-total { display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:8px; border-top:2px solid #000E3E; font-weight:700; font-size:13px; }
#brModule .br-status-banner { margin-top:14px; padding:10px 14px; border-radius:6px; font-size:13px; font-weight:600; text-align:center; }
#brModule .br-status-banner.reconciled { background:#E6F6EC; color:#157347; border:1px solid #B7E4C7; }
#brModule .br-status-banner.unreconciled { background:#FDECEC; color:#B42318; border:1px solid #F3C6C6; }

#brModule .br-jev-panel { border:1px solid var(--fims-border,#E4ECF2); border-radius:8px; padding:12px; }
#brModule .br-jev-posted { padding:8px 10px; border-radius:6px; background:#E6F0FD; color:#0B5FFF; font-size:13px; font-weight:600; }
#brModule .br-jev-account-cell { position:relative; }
#brModule .br-jev-account-search { width:100%; }
#brModule .br-jev-account-dropdown { display:none; position:absolute; top:100%; left:0; right:0; margin-top:2px; background:#fff; border:1px solid #E4ECF2; border-radius:6px; max-height:160px; overflow-y:auto; z-index:1002; box-shadow:0 4px 12px rgba(0,0,0,.1); }
#brModule .br-jev-account-item { padding:7px 10px; cursor:pointer; font-size:12px; border-bottom:1px solid #E4ECF2; }
#brModule .br-jev-account-item:hover { background:#F4F7FA; }

html.afims-dark[data-bs-theme="dark"] #brModule { color-scheme:dark; }
html.afims-dark[data-bs-theme="dark"] #brModule .fims-btn-primary { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #brModule .fims-btn-primary:hover { background:#2E59B8; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #brModule :is(select.fims-select, .fims-input, textarea.fims-input) {
    background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-recon-col,
html.afims-dark[data-bs-theme="dark"] #brModule .br-ledger-wrap { border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-item-list li { border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-ledger-table tr.br-row-uncleared td { color:#F5A3A3; }
html.afims-dark[data-bs-theme="dark"] #brModule .br-soa-tag-soa { background:#15294D; color:#8FB8FF; }
html.afims-dark[data-bs-theme="dark"] #brModule .br-soa-tag-manual { background:#3A2A10; color:#F0B35E; }
html.afims-dark[data-bs-theme="dark"] #brModule .br-status-banner.reconciled { background:#123524; color:#8FE3B3; border-color:#1F5A3B; }
html.afims-dark[data-bs-theme="dark"] #brModule .br-status-banner.unreconciled { background:#3A1414; color:#F5A3A3; border-color:#6B2222; }
html.afims-dark[data-bs-theme="dark"] #brModule .br-jev-panel { border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-jev-posted { background:#15294D; color:#8FB8FF; }
html.afims-dark[data-bs-theme="dark"] #brModule .br-jev-account-dropdown { background:var(--afims-surface, #1A1F38); border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-jev-account-item { border-color:var(--afims-border-strong, #4A5270); color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-jev-account-item:hover { background:var(--afims-surface-subtle, #22294A); }
</style>

<script>
(function () {
    const API = '/api/bank-reconciliation';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function jsonHeaders(extra) {
        return authHeaders(Object.assign({ 'Content-Type': 'application/json' }, extra || {}));
    }

    function toast(msg, isError) {
        const el = document.getElementById('brToast');
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

    function peso(v) {
        const n = Number(v || 0);
        return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#brModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.style.display = 'none'; });
    });

    function statusBadge(status) {
        if (status === 'RECONCILED') return '<span class="fims-badge fims-badge-green">Reconciled</span>';
        if (status === 'UNRECONCILED') return '<span class="fims-badge fims-badge-red">Unreconciled</span>';
        return '<span class="fims-badge fims-badge-gray">Not Yet Reconciled</span>';
    }

    // ── Bank dropdown ──
    let bankAccounts = [];
    function loadBankAccounts() {
        return fetch(API + '/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                bankAccounts = (json && json.data) ? json.data : [];
                const sel = document.getElementById('field-bank-account-id');
                sel.innerHTML = '<option value="">— Select —</option>' +
                    bankAccounts.map(a => '<option value="' + a.bank_account_id + '">' + escapeHtml(a.bank_name + ' — ' + a.account_name + ' (' + (a.account_number || '') + ')') + '</option>').join('');
            })
            .catch(() => {});
    }

    // ── Table ──
    function buildListParams() {
        const params = new URLSearchParams();
        const status = document.getElementById('brFilterStatus').value;
        const search = document.getElementById('brSearch').value.trim();
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        return params;
    }

    function loadList() {
        const tbody = document.getElementById('brTableBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        fetch(API + '/list?' + buildListParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                document.getElementById('brTableSubtitle').textContent = 'Bank Reconciliation Listing (' + rows.length + ')';
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">No bank accounts found.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    const asOf = r.as_of_date ? r.as_of_date : '—';
                    const balance = r.adjusted_bank_balance != null ? r.adjusted_bank_balance : (r.adjusted_book_balance != null ? r.adjusted_book_balance : null);
                    return '<tr>' +
                        '<td>' + escapeHtml(r.bank_name || '') + '</td>' +
                        '<td class="fims-col-mono">' + escapeHtml(r.account_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.account_name || '') + '</td>' +
                        '<td>' + escapeHtml(asOf) + '</td>' +
                        '<td class="text-right br-mono">' + (balance != null ? peso(balance) : '—') + '</td>' +
                        '<td class="text-center">' + statusBadge(r.status) + '</td>' +
                        '<td class="text-center"><button type="button" class="fims-btn fims-btn-outline fims-btn-sm" data-recon="' + r.bank_account_id + '" data-recon-id="' + (r.reconciliation_id || '') + '"><i class="fas fa-scale-balanced mr-1"></i>' + (r.reconciliation_id ? 'Edit' : 'Reconcile') + '</button></td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-recon]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openReconModal(this.dataset.recon, this.dataset.reconId || null); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Could not reach the server.</td></tr>'; });
    }

    document.getElementById('brFilterBtn').addEventListener('click', loadList);
    document.getElementById('brResetBtn').addEventListener('click', function () {
        document.getElementById('brFilterStatus').value = '';
        document.getElementById('brSearch').value = '';
        loadList();
    });
    document.getElementById('brSearch').addEventListener('keydown', function (e) { if (e.key === 'Enter') loadList(); });

    // ── Reconciliation modal ──
    const ADD_FIELD_IDS = [
        'field-add-unrecorded-deposit', 'field-add-credit-memo', 'field-add-interest-income', 'field-add-book-error-under',
        'field-less-debit-memo', 'field-less-withholding-tax', 'field-less-bank-charges', 'field-less-book-error-over',
        'field-add-bank-error-under', 'field-less-bank-error-over',
        'field-bank-balance',
    ];
    let currentBankAccountId = null;
    let currentReconciliationId = null;

    function clearReconModal() {
        document.getElementById('field-bank-account-id').value = '';
        document.getElementById('field-bank-account-id').disabled = false;
        document.getElementById('field-period').value = '';
        document.getElementById('field-book-balance').value = '';
        document.getElementById('field-add-deposit-in-transit').value = '0.00';
        document.getElementById('field-less-outstanding-check').value = '0.00';
        ADD_FIELD_IDS.forEach(id => document.getElementById(id).value = '0');
        delete document.getElementById('field-bank-balance').dataset.touched;
        document.getElementById('brSoaBalanceHint').textContent = 'No SOA uploaded for this account yet.';
        document.getElementById('field-remarks').value = '';
        document.getElementById('brStatusBanner').style.display = 'none';
        document.getElementById('brLedgerBody').innerHTML = '<tr class="fims-empty"><td colspan="7">Select a Bank and period above.</td></tr>';
        document.getElementById('brSoaLedgerBody').innerHTML = '<tr class="fims-empty"><td colspan="6">No SOA uploaded for this account/period yet.</td></tr>';
        document.getElementById('brDepositsInTransitList').innerHTML = '<li class="br-item-empty">None</li>';
        document.getElementById('brOutstandingChecksList').innerHTML = '<li class="br-item-empty">None</li>';
        currentBankAccountId = null;
        currentReconciliationId = null;
        document.getElementById('brJevPanel').style.display = 'none';
        recompute();
    }

    function recompute() {
        const bookBalance = Number(document.getElementById('field-book-balance').value) || 0;
        const addUnrecordedDeposit = Number(document.getElementById('field-add-unrecorded-deposit').value) || 0;
        const addCm = Number(document.getElementById('field-add-credit-memo').value) || 0;
        const addInt = Number(document.getElementById('field-add-interest-income').value) || 0;
        const addBookErrorUnder = Number(document.getElementById('field-add-book-error-under').value) || 0;
        const lessDm = Number(document.getElementById('field-less-debit-memo').value) || 0;
        const lessWht = Number(document.getElementById('field-less-withholding-tax').value) || 0;
        const lessBankCharges = Number(document.getElementById('field-less-bank-charges').value) || 0;
        const lessBookErrorOver = Number(document.getElementById('field-less-book-error-over').value) || 0;
        const adjBook = bookBalance + addUnrecordedDeposit + addCm + addInt + addBookErrorUnder
            - lessDm - lessWht - lessBankCharges - lessBookErrorOver;

        const bankBalance = Number(document.getElementById('field-bank-balance').value) || 0;
        const addDit = Number(document.getElementById('field-add-deposit-in-transit').value) || 0;
        const addBankErrorUnder = Number(document.getElementById('field-add-bank-error-under').value) || 0;
        const lessOc = Number(document.getElementById('field-less-outstanding-check').value) || 0;
        const lessBankErrorOver = Number(document.getElementById('field-less-bank-error-over').value) || 0;
        const adjBank = bankBalance + addDit + addBankErrorUnder - lessOc - lessBankErrorOver;

        document.getElementById('calc-adjusted-book').textContent = peso(adjBook);
        document.getElementById('calc-adjusted-bank').textContent = peso(adjBank);

        const banner = document.getElementById('brStatusBanner');
        const reconciled = Math.abs(adjBook - adjBank) < 0.01;
        banner.style.display = 'block';
        banner.className = 'br-status-banner ' + (reconciled ? 'reconciled' : 'unreconciled');
        banner.innerHTML = reconciled
            ? '<i class="fas fa-check-circle mr-1"></i>Reconciled — both reconciled balances match.'
            : '<i class="fas fa-triangle-exclamation mr-1"></i>Unreconciled — difference of ' + peso(Math.abs(adjBook - adjBank)) + '.';

        return { adjBook, adjBank };
    }
    document.querySelectorAll('#brModule .br-recon-col input[type="number"]').forEach(function (inp) {
        inp.addEventListener('input', recompute);
    });
    document.getElementById('field-bank-balance').addEventListener('input', function () { this.dataset.touched = '1'; });

    function periodToAsOfDate(period) {
        const d = new Date(period + '-01');
        return new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
    }

    function renderItemList(elId, rows, amountKey) {
        const el = document.getElementById(elId);
        if (!rows.length) { el.innerHTML = '<li class="br-item-empty">None</li>'; return; }
        el.innerHTML = rows.map(r =>
            '<li><span>' + escapeHtml(r.reference_no || '') + ' — ' + escapeHtml(r.description || '') + '</span><span class="br-mono">' + peso(r[amountKey]) + '</span></li>'
        ).join('');
    }

    function renderSoaLedger(soaRows) {
        const tbody = document.getElementById('brSoaLedgerBody');
        if (!soaRows || !soaRows.length) {
            tbody.innerHTML = '<tr class="fims-empty"><td colspan="6">No SOA uploaded for this account/period yet.</td></tr>';
            return;
        }
        tbody.innerHTML = soaRows.map(function (r) {
            const matchCell = r.matched
                ? '<span class="fims-badge fims-badge-green">Matched</span><span class="br-match-ref">' + escapeHtml(r.matched_reference_no || '') + '</span>'
                : '<span class="fims-badge fims-badge-gray">Unmatched</span>';
            return '<tr class="' + (r.matched ? '' : 'br-row-uncleared') + '">' +
                '<td>' + escapeHtml(r.transaction_date) + '</td>' +
                '<td class="fims-col-mono">' + escapeHtml(r.check_no || '') + '</td>' +
                '<td>' + escapeHtml(r.description || '') + '</td>' +
                '<td class="text-right br-mono">' + (r.debit_amount > 0 ? peso(r.debit_amount) : '') + '</td>' +
                '<td class="text-right br-mono">' + (r.credit_amount > 0 ? peso(r.credit_amount) : '') + '</td>' +
                '<td class="text-center br-match-cell">' + matchCell + '</td>' +
                '</tr>';
        }).join('');
    }

    function loadLedger(bankAccountId, period) {
        if (!bankAccountId || !period) return;
        currentBankAccountId = bankAccountId;
        const asOfDate = periodToAsOfDate(period);
        const periodStart = period + '-01';
        fetch(API + '/ledger?bank_account_id=' + encodeURIComponent(bankAccountId) + '&period_start=' + periodStart + '&as_of_date=' + asOfDate, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load the ledger.', true); return; }
                const d = json.data;
                const tbody = document.getElementById('brLedgerBody');

                if (!d.has_gl_account) {
                    document.getElementById('field-book-balance').value = '0';
                    document.getElementById('field-book-balance').readOnly = false;
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">This bank account has no GL account tagged yet — enter the Book Balance manually.</td></tr>';
                    renderSoaLedger(d.soa_rows);
                    renderItemList('brDepositsInTransitList', [], 'debit');
                    renderItemList('brOutstandingChecksList', [], 'credit');
                    document.getElementById('field-add-deposit-in-transit').value = '0.00';
                    document.getElementById('field-less-outstanding-check').value = '0.00';
                    recompute();
                    return;
                }

                document.getElementById('field-book-balance').value = d.ending_balance;
                document.getElementById('field-book-balance').readOnly = true;

                let rowsHtml = '<tr><td colspan="3"><strong>Beginning Balance</strong></td><td class="text-right br-mono"></td><td class="text-right br-mono"></td><td class="text-right br-mono"><strong>' + peso(d.beginning_balance) + '</strong></td><td></td></tr>';
                if (!d.rows.length) {
                    rowsHtml += '<tr class="fims-empty"><td colspan="7">No transactions posted for this period yet.</td></tr>';
                } else {
                    rowsHtml += d.rows.map(function (r) {
                        let badge = '';
                        if (r.overridden) {
                            badge = '<span class="br-soa-tag br-soa-tag-manual" title="Manually overridden">manual</span>';
                        } else if (r.soa_matched) {
                            badge = '<span class="br-soa-tag br-soa-tag-soa" title="Matched to an uploaded SOA line">SOA</span>';
                        }
                        return '<tr class="' + (r.cleared ? '' : 'br-row-uncleared') + '">' +
                            '<td>' + escapeHtml(r.posting_date) + '</td>' +
                            '<td class="fims-col-mono">' + escapeHtml(r.reference_no || '') + '</td>' +
                            '<td>' + escapeHtml(r.description || '') + '</td>' +
                            '<td class="text-right br-mono">' + (r.debit > 0 ? peso(r.debit) : '') + '</td>' +
                            '<td class="text-right br-mono">' + (r.credit > 0 ? peso(r.credit) : '') + '</td>' +
                            '<td class="text-right br-mono">' + peso(r.balance) + '</td>' +
                            '<td class="text-center"><input type="checkbox" data-gl-id="' + r.gl_id + '" ' + (r.cleared ? 'checked' : '') + '>' + badge + '</td>' +
                            '</tr>';
                    }).join('');
                }
                tbody.innerHTML = rowsHtml;
                tbody.querySelectorAll('[data-gl-id]').forEach(function (cb) {
                    cb.addEventListener('change', function () {
                        const glId = this.dataset.glId;
                        const cleared = this.checked;
                        this.disabled = true;
                        fetch(API + '/clear-item', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ bank_account_id: currentBankAccountId, gl_id: glId, cleared: cleared }) })
                            .then(r => r.json())
                            .then(function (json) {
                                if (!json || !json.success) { toast('Could not update.', true); return; }
                                loadLedger(currentBankAccountId, document.getElementById('field-period').value);
                            })
                            .catch(() => toast('Could not reach the server.', true));
                    });
                });

                renderSoaLedger(d.soa_rows);
                renderItemList('brDepositsInTransitList', d.outstanding_deposits, 'debit');
                renderItemList('brOutstandingChecksList', d.outstanding_checks, 'credit');
                document.getElementById('field-add-deposit-in-transit').value = d.outstanding_deposits.reduce((s, r) => s + r.debit, 0).toFixed(2);
                document.getElementById('field-less-outstanding-check').value = d.outstanding_checks.reduce((s, r) => s + r.credit, 0).toFixed(2);

                const soaHint = document.getElementById('brSoaBalanceHint');
                const bankBalanceField = document.getElementById('field-bank-balance');
                if (d.soa_bank_balance !== null) {
                    soaHint.textContent = 'Per uploaded SOA: ' + peso(d.soa_bank_balance) + ' as of ' + d.soa_as_of_date + '.';
                    if (!bankBalanceField.dataset.touched) { bankBalanceField.value = d.soa_bank_balance; }
                } else {
                    soaHint.textContent = 'No SOA uploaded for this account yet — enter the Bank Balance manually.';
                }
                recompute();
            })
            .catch(() => toast('Could not reach the server.', true));
    }
    document.getElementById('field-bank-account-id').addEventListener('change', function () {
        loadLedger(this.value, document.getElementById('field-period').value);
    });
    document.getElementById('field-period').addEventListener('change', function () {
        loadLedger(document.getElementById('field-bank-account-id').value, this.value);
    });

    function fillReconFields(d) {
        document.getElementById('field-bank-account-id').value = d.bank_account_id;
        document.getElementById('field-bank-account-id').disabled = true; // editing an existing reconciliation — the bank account is fixed, changing it would silently start a different record
        document.getElementById('field-period').value = String(d.period_year) + '-' + String(d.period_month).padStart(2, '0');
        document.getElementById('field-add-unrecorded-deposit').value = d.add_unrecorded_deposit;
        document.getElementById('field-add-credit-memo').value = d.add_credit_memo;
        document.getElementById('field-add-interest-income').value = d.add_interest_income;
        document.getElementById('field-add-book-error-under').value = d.add_book_error_under;
        document.getElementById('field-less-debit-memo').value = d.less_debit_memo;
        document.getElementById('field-less-withholding-tax').value = d.less_withholding_tax;
        document.getElementById('field-less-bank-charges').value = d.less_bank_charges;
        document.getElementById('field-less-book-error-over').value = d.less_book_error_over;
        document.getElementById('field-add-bank-error-under').value = d.add_bank_error_under;
        document.getElementById('field-less-bank-error-over').value = d.less_bank_error_over;
        document.getElementById('field-bank-balance').value = d.bank_balance;
        document.getElementById('field-bank-balance').dataset.touched = '1'; // preserve the saved figure — don't let loadLedger() overwrite it with the live SOA balance
        document.getElementById('field-remarks').value = d.remarks || '';
        currentReconciliationId = d.reconciliation_id;
        loadLedger(d.bank_account_id, document.getElementById('field-period').value);
        loadJevPanel(currentReconciliationId);
    }

    // bankAccountId: pre-select the Bank dropdown (new or edit alike).
    // reconciliationId: when given (the row already has a saved reconciliation), loads and
    // pre-fills EVERY saved field — otherwise "Edit" would silently reset Bank Balance and every
    // adjustment back to 0, discarding what was entered before.
    function openReconModal(bankAccountId, reconciliationId) {
        clearReconModal();
        if (reconciliationId) {
            fetch(API + '/' + reconciliationId, { headers: authHeaders() })
                .then(r => r.json())
                .then(function (json) {
                    if (!json || !json.success) { toast((json && json.message) || 'Could not load this reconciliation.', true); return; }
                    fillReconFields(json.data);
                    openModal('m-br-recon');
                })
                .catch(() => toast('Could not reach the server.', true));
            return;
        }
        if (bankAccountId) {
            document.getElementById('field-bank-account-id').value = bankAccountId;
            document.getElementById('field-bank-account-id').disabled = true; // opened against a specific row (Reconcile) — bank is fixed, same as Edit
            const now = new Date();
            document.getElementById('field-period').value = now.toISOString().slice(0, 7);
            loadLedger(bankAccountId, document.getElementById('field-period').value);
        }
        openModal('m-br-recon');
    }
    document.getElementById('brAddBtn').addEventListener('click', function () { openReconModal(null, null); });

    // ── Adjusting Journal Entry Voucher panel ──────────────────────────
    let jevBooksLoaded = false;
    function loadJevBooks() {
        if (jevBooksLoaded) return Promise.resolve();
        jevBooksLoaded = true;
        return fetch('/api/books/all', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                document.getElementById('field-jev-book').innerHTML = rows.map(b => '<option value="' + b.book_id + '">' + escapeHtml(b.name) + '</option>').join('');
            })
            .catch(() => {});
    }

    function jevAccountCellHtml(itemKey, defaultAccount) {
        const val = defaultAccount || '';
        return '<div class="br-jev-account-cell">' +
            '<input type="hidden" class="jev-account-code" data-item="' + itemKey + '" value="' + escapeHtml(val) + '">' +
            '<input type="text" class="fims-input fims-input-sm br-jev-account-search" data-item="' + itemKey + '" placeholder="Search GL account…" value="' + escapeHtml(val) + '">' +
            '<div class="br-jev-account-dropdown"></div>' +
            '</div>';
    }

    function bindJevAccountSearch(input) {
        let timer;
        const wrap = input.closest('.br-jev-account-cell');
        const dropdown = wrap.querySelector('.br-jev-account-dropdown');
        const hidden = wrap.querySelector('.jev-account-code');
        input.addEventListener('input', function () {
            hidden.value = ''; // typing invalidates the previously picked/defaulted code until a suggestion is clicked
            const q = input.value.trim();
            clearTimeout(timer);
            if (!q) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch('/api/accounting/coa/search?q=' + encodeURIComponent(q) + '&limit=15', { headers: authHeaders() })
                    .then(r => r.json())
                    .then(function (json) {
                        const rows = (json && json.data) ? json.data : [];
                        if (!rows.length) {
                            dropdown.innerHTML = '<div class="br-jev-account-item">No matches.</div>';
                            dropdown.style.display = 'block';
                            return;
                        }
                        dropdown.innerHTML = rows.map(a =>
                            '<div class="br-jev-account-item" data-code="' + escapeHtml(a.account_code) + '" data-title="' + escapeHtml(a.account_title) + '"><strong>' + escapeHtml(a.account_code) + '</strong> — ' + escapeHtml(a.account_title) + '</div>'
                        ).join('');
                        dropdown.style.display = 'block';
                        dropdown.querySelectorAll('.br-jev-account-item[data-code]').forEach(function (item) {
                            item.addEventListener('click', function () {
                                hidden.value = this.dataset.code;
                                input.value = this.dataset.code + ' — ' + this.dataset.title;
                                dropdown.style.display = 'none';
                            });
                        });
                    })
                    .catch(() => {});
            }, 300);
        });
        document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) dropdown.style.display = 'none'; });
    }

    function postAdjustingJevRequest(bookId, contraAccounts) {
        return fetch(API + '/' + currentReconciliationId + '/post-jev', {
            method: 'POST', headers: jsonHeaders(),
            body: JSON.stringify({ book_id: bookId, contra_accounts: contraAccounts }),
        })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not post the adjusting JEV.', true); return; }
                toast(json.data.message || ('Adjusting JEV ' + json.data.jev_no + ' posted.'), !json.data.posted);
                loadJevPanel(currentReconciliationId);
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    function loadJevPanel(reconciliationId) {
        const panel = document.getElementById('brJevPanel');
        const alreadyEl = document.getElementById('brJevAlreadyPosted');
        const formEl = document.getElementById('brJevForm');
        const nothingEl = document.getElementById('brJevNothingToPost');
        const needsSaveEl = document.getElementById('brJevNeedsSave');
        const needsReconciledEl = document.getElementById('brJevNeedsReconciled');
        panel.style.display = 'block';
        [alreadyEl, formEl, nothingEl, needsSaveEl, needsReconciledEl].forEach(el => el.style.display = 'none');

        if (!reconciliationId) {
            needsSaveEl.style.display = 'block';
            return;
        }

        fetch(API + '/' + reconciliationId + '/jev-preview', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load the adjusting JEV preview.', true); return; }
                const d = json.data;
                if (d.already_posted) {
                    alreadyEl.style.display = 'block';
                    const posted = d.jev_status === 'POSTED';
                    alreadyEl.innerHTML = '<i class="fas fa-check-circle mr-1"></i>Adjusting JEV <strong>' + escapeHtml(d.jev_no) + '</strong> '
                        + (posted ? 'posted to the General Ledger' : ('created (status: ' + escapeHtml(d.jev_status || 'unknown') + ')')) + ' for this reconciliation.';
                    return;
                }
                if (!d.items.length) {
                    nothingEl.style.display = 'block';
                    return;
                }
                if (d.status !== 'RECONCILED') {
                    needsReconciledEl.style.display = 'block';
                    return;
                }
                if (d.auto_postable) {
                    // Every item already resolves to a default contra account and the reconciliation
                    // is Reconciled — post straight to the GL, no click needed.
                    loadJevBooks().then(function () {
                        const bookSel = document.getElementById('field-jev-book');
                        const contraAccounts = {};
                        d.items.forEach(it => { contraAccounts[it.key] = it.default_account; });
                        postAdjustingJevRequest(bookSel.value, contraAccounts);
                    });
                    return;
                }
                formEl.style.display = 'block';
                loadJevBooks();
                const tbody = document.getElementById('brJevItemsBody');
                tbody.innerHTML = d.items.map(it =>
                    '<tr><td>' + escapeHtml(it.label) + ' <span class="fims-badge ' + (it.side === 'add' ? 'fims-badge-green' : 'fims-badge-red') + '">' + (it.side === 'add' ? 'Add' : 'Less') + '</span></td>' +
                    '<td class="text-right br-mono">' + peso(it.amount) + '</td>' +
                    '<td>' + jevAccountCellHtml(it.key, it.default_account) + '</td></tr>'
                ).join('');
                tbody.querySelectorAll('.br-jev-account-search').forEach(bindJevAccountSearch);
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    document.getElementById('brPostJevBtn').addEventListener('click', function () {
        if (!currentReconciliationId) { toast('Save the reconciliation first.', true); return; }
        const bookId = document.getElementById('field-jev-book').value;
        if (!bookId) { toast('Select a Book.', true); return; }
        const contraAccounts = {};
        document.querySelectorAll('#brJevItemsBody .jev-account-code').forEach(function (hidden) {
            if (hidden.value) contraAccounts[hidden.dataset.item] = hidden.value;
        });

        this.disabled = true;
        postAdjustingJevRequest(bookId, contraAccounts)
            .finally(() => { document.getElementById('brPostJevBtn').disabled = false; });
    });

    document.getElementById('brSaveBtn').addEventListener('click', function () {
        const bankAccountId = document.getElementById('field-bank-account-id').value;
        const period = document.getElementById('field-period').value;
        if (!bankAccountId) { toast('Select a Bank.', true); return; }
        if (!period) { toast('Select the reconciliation month.', true); return; }
        const [year, month] = period.split('-').map(Number);

        const payload = {
            bank_account_id: bankAccountId,
            period_year: year,
            period_month: month,
            book_balance: document.getElementById('field-book-balance').value,
            add_unrecorded_deposit: document.getElementById('field-add-unrecorded-deposit').value,
            add_credit_memo: document.getElementById('field-add-credit-memo').value,
            add_interest_income: document.getElementById('field-add-interest-income').value,
            add_book_error_under: document.getElementById('field-add-book-error-under').value,
            less_debit_memo: document.getElementById('field-less-debit-memo').value,
            less_withholding_tax: document.getElementById('field-less-withholding-tax').value,
            less_bank_charges: document.getElementById('field-less-bank-charges').value,
            less_book_error_over: document.getElementById('field-less-book-error-over').value,
            bank_balance: document.getElementById('field-bank-balance').value,
            add_bank_error_under: document.getElementById('field-add-bank-error-under').value,
            less_bank_error_over: document.getElementById('field-less-bank-error-over').value,
            remarks: document.getElementById('field-remarks').value.trim(),
        };

        this.disabled = true;
        fetch(API + '/save', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Save failed.', true); return; }
                toast('Bank reconciliation saved.');
                currentReconciliationId = json.data.reconciliation_id;
                document.getElementById('field-bank-account-id').disabled = true; // now an existing record
                loadList();
                loadJevPanel(currentReconciliationId); // stay open so Add/Less amounts can be posted as a JEV right away
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { document.getElementById('brSaveBtn').disabled = false; });
    });

    // ── Boot ──
    loadBankAccounts();
    loadList();
})();
</script>
