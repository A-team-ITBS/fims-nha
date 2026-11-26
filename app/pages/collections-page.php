<?php
/**
 * Collections — Collecting Officer's Collection Report register.
 *
 * Each report has two independent sections: "Collection(s)" (GL/revenue
 * account → Amount, e.g. Installment Sales Receivable, Permit Fees) and
 * "Deposit" (Bank → Period Covered From/To → Amount). See
 * app/services/CollectionsService.php's docblock for the schema
 * (fims.collection_reports + collection_report_lines/_deposits) — net-new,
 * this page and its backing table did not exist before.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-modal-overlay, etc.) match _shared/css/nha-fims.css — same convention
 * as app/pages/bank-management-page.php, including its itemized dynamic-row
 * table pattern (see field-rf-table there) reused here for both the
 * Collection(s) and Deposit sections.
 */

namespace PHPMaker2024\FIMS;

$colCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css');
?>
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= $colCssVer ?>" />

<div class="fims-wrap" id="colModule">

    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-hand-holding-usd"></i></div>
        <div>
            <h1 class="app-page-title">Collections</h1>
            <p class="app-page-subtitle">Collecting Officer's Collection &amp; Deposit Reports</p>
        </div>
    </div>

    <div class="fims-tabs-underline mb-3" id="colTabNav">
        <button class="fims-tab-underline active" data-col-tab="collections">Collections</button>
        <button class="fims-tab-underline" data-col-tab="deposits">Deposits</button>
    </div>

    <!-- "Collections" tab — every Collection line flattened across every
         report (CollectionsService::getCollectionsList()), by GL account.
         Read-only — a saved report can no longer be edited, only registered
         fresh (see openReportModal()'s own comment). -->
    <div id="colTabCollections">
        <div class="fims-stat-grid" id="colCollectionsStats" style="grid-template-columns:repeat(2,1fr)">
            <div class="fims-stat-card fims-card-blue">
                <div class="fims-stat-top"><span class="fims-stat-label">Collection Lines</span><span class="fims-stat-icon"><i class="fas fa-file-alt"></i></span></div>
                <div class="fims-stat-value" data-kpi="total">0</div>
            </div>
            <div class="fims-stat-card fims-card-green">
                <div class="fims-stat-top"><span class="fims-stat-label">Total Collections</span><span class="fims-stat-icon"><i class="fas fa-hand-holding-usd"></i></span></div>
                <div class="fims-stat-value" data-kpi="total_amount">₱0.00</div>
            </div>
        </div>
        <div class="fims-table-panel">
            <div class="fims-panel-header">
                <div class="fims-panel-title" id="colCollectionsSubtitle">Collections (0)</div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" class="fims-input fims-input-sm" id="colCollectionsSearch" placeholder="Search Collecting Office, Region, or GL Account…" style="max-width:300px;">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="colRegisterCollectionBtn">
                        <i class="fas fa-plus mr-1"></i>Register Collection
                    </button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="colCollectionsTable">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Date</th>
                            <th>Region / Office</th>
                            <th>Collecting Office</th>
                            <th>GL Account</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center" style="width:80px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="colCollectionsBody"><tr class="fims-empty"><td colspan="7">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- "Deposits" tab — every Deposit line flattened across every report
         (CollectionsService::getDepositsList()), including is_swept (Fund
         Sweeping's own per-deposit tracking — see
         app/pages/fund-sweeping-page.php). -->
    <div id="colTabDeposits" style="display:none;">
        <div class="fims-stat-grid" id="colDepositsStats" style="grid-template-columns:repeat(2,1fr)">
            <div class="fims-stat-card fims-card-blue">
                <div class="fims-stat-top"><span class="fims-stat-label">Deposit Lines</span><span class="fims-stat-icon"><i class="fas fa-file-alt"></i></span></div>
                <div class="fims-stat-value" data-kpi="total">0</div>
            </div>
            <div class="fims-stat-card fims-card-amber">
                <div class="fims-stat-top"><span class="fims-stat-label">Total Deposits</span><span class="fims-stat-icon"><i class="fas fa-university"></i></span></div>
                <div class="fims-stat-value" data-kpi="total_amount">₱0.00</div>
            </div>
        </div>
        <div class="fims-table-panel">
            <div class="fims-panel-header">
                <div class="fims-panel-title" id="colDepositsSubtitle">Deposits (0)</div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" class="fims-input fims-input-sm" id="colDepositsSearch" placeholder="Search Collecting Office, Region, or Bank…" style="max-width:300px;">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="colRegisterDepositBtn">
                        <i class="fas fa-plus mr-1"></i>Register Deposit
                    </button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="colDepositsTable">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Date</th>
                            <th>Region / Office</th>
                            <th>Collecting Office</th>
                            <th>Bank Account</th>
                            <th>Period Covered</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Swept</th>
                            <th class="text-center" style="width:80px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="colDepositsBody"><tr class="fims-empty"><td colspan="9">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Register Collection Report
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-col-report" style="display:none;">
        <div class="fims-modal-dialog u-modal-2xl">
            <div class="fims-modal-content" style="max-height:92vh;">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="colModalTitle">Register Collection Report</div></div>
                    <button class="fims-modal-close" data-close="m-col-report">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="col_report_id">

                    <div class="fims-form-section-title">Report Details</div>
                    <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:12px;">
                        <div class="fims-form-group">
                            <label class="fims-label">Region / Office *</label>
                            <select class="fims-select" id="col_rc_id">
                                <option value="">— Select Region —</option>
                            </select>
                        </div>
                        <!-- Auto-filled from the Collecting Officer(s) tagged to this
                             Region/Office (fims.collecting_officers.rc_id) — see
                             colOnRegionChange()/colApplyOfficerToForm(). Only one
                             match auto-selects itself; more than one just narrows
                             the options and waits for a manual pick. -->
                        <div class="fims-form-group">
                            <label class="fims-label">Collecting Officer</label>
                            <select class="fims-select" id="col_collecting_officer_id">
                                <option value="">— Select Region first —</option>
                            </select>
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Collecting Office</label>
                            <input type="text" class="fims-input" id="col_collecting_office">
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Date *</label>
                            <input type="date" class="fims-input" id="col_report_date">
                        </div>
                    </div>

                    <!-- "Collection(s)" — GL/revenue account + Amount, dynamic rows,
                         same itemized-table convention as bank-management-page.php's
                         Restricted Funds editor (field-rf-table there). Independent
                         total from the Deposit section below — see
                         CollectionsService's docblock. Hidden entirely in
                         'deposits'-mode registration (see colSetFormMode()) — a
                         section with no rows in it just submits an empty array,
                         so hiding it is all that's needed to make that side
                         optional on save, no extra client-side bookkeeping. -->
                    <div id="col-lines-section">
                        <div class="fims-form-section-title" style="margin-top:14px;">Collection(s)</div>
                        <div style="overflow-x:auto;">
                            <table class="fims-table" id="col-lines-table" style="table-layout:fixed;width:100%;min-width:420px;">
                                <thead><tr><th>Account Code</th><th style="width:160px">Amount</th><th style="width:56px"></th></tr></thead>
                                <tbody id="col-lines-body"></tbody>
                            </table>
                        </div>
                        <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="col-lines-add-btn" style="margin-top:10px;"><i class="fas fa-plus mr-1"></i>Add Line</button>
                        <div style="text-align:right;font-size:13px;font-weight:700;margin-top:6px;">
                            Total: <span id="col-lines-total">₱0.00</span>
                        </div>
                    </div>

                    <!-- "Deposit" — Bank + Period Covered (From/To) + Amount, dynamic
                         rows. Hidden entirely in 'collections'-mode registration —
                         same reasoning as col-lines-section above. -->
                    <div id="col-deposits-section">
                        <div style="display:grid;grid-template-columns:1fr 380px;gap:16px;align-items:start;">
                            <div style="min-width:0;">
                                <div class="fims-form-section-title" style="margin-top:14px;">Deposit</div>
                                <div style="overflow-x:auto;">
                                    <table class="fims-table" id="col-deposits-table" style="table-layout:fixed;width:100%;min-width:720px;">
                                        <thead>
                                            <tr>
                                                <th>Bank</th>
                                                <th style="width:135px">Period From</th>
                                                <th style="width:135px">Period To</th>
                                                <th style="width:130px">Amount</th>
                                                <th style="width:170px">Deposit Slip</th>
                                                <th style="width:56px"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="col-deposits-body"></tbody>
                                    </table>
                                </div>
                                <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="col-deposits-add-btn" style="margin-top:10px;"><i class="fas fa-plus mr-1"></i>Add Line</button>
                                <div style="text-align:right;font-size:13px;font-weight:700;margin-top:6px;">
                                    Total: <span id="col-deposits-total">₱0.00</span>
                                </div>
                            </div>

                            <!-- Read-only reference panel — what's already been collected
                                 (Collection(s) lines, every report, flattened — see
                                 CollectionsService::getCollectionsList()) for whichever
                                 Region/Office is selected above, so whoever's registering
                                 a Deposit can see what's on hand before entering how much
                                 of it actually went to the bank. Refreshed on every Region
                                 change (colOnRegionChange()) — never submitted itself,
                                 purely informational. Sits beside the Deposit table rather
                                 than above it so both are visible at once while entering lines. -->
                            <div style="min-width:0;border-left:1px solid var(--fims-border,#E5E7EB);padding-left:16px;">
                                <div class="fims-form-section-title" style="margin-top:14px;">Collected Collections in this Region</div>
                                <div id="col-collected-summary" style="font-size:12px;color:var(--fims-text-muted,#6B7280);margin-bottom:6px;">Select a Region/Office above to view its collected collections.</div>
                                <div class="fims-table-wrap" style="max-height:320px;overflow-y:auto;">
                                    <table class="fims-table">
                                        <thead><tr><th>Date</th><th>GL Account</th><th class="text-right">Amount</th></tr></thead>
                                        <tbody id="col-collected-list-body"><tr class="fims-empty"><td colspan="3">—</td></tr></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-col-report">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="colSaveBtn">Save Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Journal Entry Voucher prefill (Collection leg / Deposit leg) —
         review-then-submit, same "pro-forma" shape as the IAR/Property Record
         modules' own JEV submit (DisbursementVoucherService::createStandaloneJev()).
         Nothing posts until Submit; lines/amounts are server-computed and read-only.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-col-jev" style="display:none;">
        <div class="fims-modal-dialog u-modal-md">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="colJevTitle">Journal Entry Voucher</div></div>
                    <button class="fims-modal-close" data-close="m-col-jev">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div id="colJevAlready" style="display:none;padding:10px 14px;border-radius:8px;background:var(--fims-success-soft,#E4F6EC);color:var(--fims-success,#1D8A4C);font-size:13px;margin-bottom:12px;"></div>
                    <div id="colJevWarnings" style="display:none;padding:10px 14px;border-radius:8px;background:var(--fims-warning-soft,#FDF3E0);color:var(--fims-warning,#B8860B);font-size:13px;margin-bottom:12px;"></div>
                    <div id="colJevForm">
                        <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                            <div class="fims-form-group">
                                <label class="fims-label">JEV Date</label>
                                <input type="text" class="fims-input" id="colJevDate" disabled>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Book *</label>
                                <select class="fims-select" id="colJevBook"><option value="">Select a book…</option></select>
                            </div>
                            <div class="fims-form-group" style="grid-column:span 1;">
                                <label class="fims-label">Particulars</label>
                                <input type="text" class="fims-input" id="colJevParticulars">
                            </div>
                        </div>
                        <table class="fims-table" style="margin-top:12px;">
                            <thead><tr><th>Account</th><th class="text-right" style="width:140px">Debit</th><th class="text-right" style="width:140px">Credit</th></tr></thead>
                            <tbody id="colJevLinesBody"></tbody>
                            <tfoot><tr style="font-weight:700;"><td>Total</td><td class="text-right" id="colJevTotalDebit">₱0.00</td><td class="text-right" id="colJevTotalCredit">₱0.00</td></tr></tfoot>
                        </table>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-col-jev">Close</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="colJevSubmitBtn">Save as Draft JEV</button>
                </div>
            </div>
        </div>
    </div>

    <div id="colToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>

</div>

<style>
/* .fims-modal-content (shared nha-fims.css) is a plain block, not a flex
   container, so .fims-modal-body's flex:1/min-height:0/overflow-y:auto
   (which assume a flex-column parent) never actually engage — content
   just grows past .fims-modal-dialog's max-height and gets clipped by its
   overflow:hidden instead of scrolling. This modal (Report Details + two
   dynamic-row tables, all shown at once, no step wizard) is tall enough
   to hit this — same fix as bank-management-page.php's Register/Edit
   Account modal. */
#colModule .fims-modal-content { display:flex; flex-direction:column; min-height:0; overflow:hidden; }

/* #col-lines-table/#col-deposits-table use table-layout:fixed so each <th>'s
   width actually holds — but a <select>/<input> defaults to its content's
   natural width, which still overflows a fixed-width <td> instead of
   shrinking to fit. Force every control to the cell's own width. */
#colModule #col-lines-table select, #colModule #col-deposits-table select,
#colModule #col-lines-table input, #colModule #col-deposits-table input {
    width:100%; max-width:100%; box-sizing:border-box; min-width:0;
}
/* The trailing action column's <th width> (56px) leaves no room for the
   shared .fims-table td's own 9px/13px padding (26px horizontal alone)
   plus a .fims-btn-sm (padding:5px 10px + icon) — it was overflowing its
   cell and, combined with an earlier blanket "td{overflow:hidden}" fix,
   got clipped outright instead of just overflowing. Tighter padding on
   just this column's cell instead of shrinking the button itself. */
#colModule #col-lines-table td:last-child, #colModule #col-deposits-table td:last-child {
    padding:6px 8px; text-align:center;
}
</style>

<script>
(function () {
    const API = '/api/collections';

    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function jsonHeaders(extra) {
        return authHeaders(Object.assign({ 'Content-Type': 'application/json' }, extra || {}));
    }

    function toast(msg, isError) {
        const el = document.getElementById('colToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function fmtPeso(n) {
        return '₱' + (parseFloat(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'flex';
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'none';
    }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#colModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.style.display = 'none';
        });
    });

    let glAccounts = [];
    let bankAccounts = [];
    let regions = [];
    let collectingOfficers = [];

    // ── "Collection(s)" dynamic rows (Account Code + Amount) ──
    // The Account Code picker is a type-to-filter combobox, not a plain
    // <select> — glAccounts is already loaded in full by loadLookups() (it's
    // just the postable chart-of-accounts list, same one glOptionsHtml()
    // used to render as <option>s), so filtering happens client-side
    // against that in-memory array rather than a server round-trip.
    // Every selectable account here is an SL (sub-ledger) code — a child of
    // some non-postable GL grouping account (see CollectionsService::
    // getGlAccounts()) — so the label leads with the SL code itself and
    // shows its parent GL code/title for context, when there is one.
    function colLineLabel(account) {
        if (!account) return '';
        const parent = account.parent_code ? (account.parent_code + ' ' + account.parent_title + ' › ') : '';
        return parent + account.account_code + ' — ' + account.account_title;
    }
    function renderColLineRow(coaAccountId, amount) {
        const tr = document.createElement('tr');
        const account = glAccounts.find(a => String(a.account_id) === String(coaAccountId || ''));
        tr.innerHTML =
            '<td>' +
                '<input type="hidden" class="col-line-coa" value="' + (coaAccountId || '') + '">' +
                // position:relative here (wrapping just the input), not on the
                // <td> itself — the input only fills the td's CONTENT box, so
                // anchoring the chevron to the td instead left it sitting in
                // the cell's own right padding, outside the visible input box.
                '<div style="position:relative;">' +
                    '<input type="text" class="fims-input fims-input-sm col-line-coa-search" autocomplete="off" ' +
                        'style="padding-right:26px;" placeholder="Click or type to search…" value="' + escapeHtml(colLineLabel(account)) + '">' +
                    '<i class="fas fa-chevron-down" style="position:absolute;right:9px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--fims-text-subtle,#99A1B3);pointer-events:none;"></i>' +
                '</div>' +
            '</td>' +
            '<td><input type="number" step="0.01" class="fims-input fims-input-sm col-line-amount" value="' + (amount || 0) + '"></td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-col-line><i class="fas fa-trash"></i></button></td>';
        tr.querySelector('.col-line-amount').addEventListener('input', recalcColLinesTotal);
        const dropdown = wireColLineCoaSearch(tr);
        tr.querySelector('[data-remove-col-line]').addEventListener('click', function () {
            dropdown.remove();
            tr.remove();
            recalcColLinesTotal();
        });
        return tr;
    }
    // Filters glAccounts by code/title substring (case-insensitive) as the
    // user types; picking a result fills the hidden id + the input's
    // display text. Clearing the input also clears the picked id, so a
    // half-typed search can't silently submit a stale selection.
    //
    // The dropdown panel is appended to <body> and positioned with
    // position:fixed from the input's own bounding rect — NOT nested
    // inside the <td> with position:absolute. #col-lines-table sits inside
    // a plain `overflow-x:auto` wrapper div, and per the CSS overflow spec,
    // setting overflow-x alone forces the browser to compute overflow-y as
    // `auto` too (not `visible`) — so an absolutely-positioned child
    // extending below the row was being silently clipped to the row's own
    // height by that ancestor, making an otherwise correctly-populated
    // dropdown look empty. Anchoring to <body> instead sidesteps that
    // ancestor entirely.
    function wireColLineCoaSearch(tr) {
        const hidden   = tr.querySelector('.col-line-coa');
        const search   = tr.querySelector('.col-line-coa-search');
        const dropdown = document.createElement('div');
        dropdown.className = 'col-line-coa-dropdown';
        // z-index must clear .fims-modal-overlay's own 9000 (nha-fims.css) — this
        // panel lives on <body>, a sibling of the modal overlay, not a
        // descendant of it, so a lower z-index here would render underneath
        // the modal instead of on top of it.
        dropdown.style.cssText = 'display:none;position:fixed;background:#fff;border:1px solid #E4ECF2;border-radius:6px;max-height:200px;overflow-y:auto;z-index:9500;box-shadow:0 4px 12px rgba(0,0,0,.15);';
        document.body.appendChild(dropdown);

        function positionDropdown() {
            const rect = search.getBoundingClientRect();
            dropdown.style.left  = rect.left + 'px';
            dropdown.style.top   = rect.bottom + 2 + 'px';
            dropdown.style.width = rect.width + 'px';
        }
        function renderDropdown(query) {
            const q = query.trim().toLowerCase();
            const matches = (q ? glAccounts.filter(a =>
                a.account_code.toLowerCase().includes(q) || a.account_title.toLowerCase().includes(q) ||
                (a.parent_code || '').toLowerCase().includes(q) || (a.parent_title || '').toLowerCase().includes(q)
            ) : glAccounts).slice(0, 50);
            if (!matches.length) {
                dropdown.innerHTML = '<div style="padding:8px 12px;font-size:12px;color:var(--fims-text-subtle);">No matching accounts</div>';
            } else {
                // SL code (this account's own code) leads, bold, on its own
                // line; the GL parent it rolls up to sits beneath it, small
                // and muted — same "SL first, GL for context" emphasis as
                // the label text itself (colLineLabel()).
                dropdown.innerHTML = matches.map(a =>
                    '<div class="col-line-coa-item" data-id="' + a.account_id + '" style="padding:7px 12px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #F4F7FA;">' +
                        '<div><strong>' + escapeHtml(a.account_code) + '</strong> — ' + escapeHtml(a.account_title) + '</div>' +
                        (a.parent_code ? '<div style="font-size:11px;color:var(--fims-text-subtle,#99A1B3);margin-top:1px;">GL: ' + escapeHtml(a.parent_code) + ' — ' + escapeHtml(a.parent_title) + '</div>' : '') +
                    '</div>'
                ).join('');
                dropdown.querySelectorAll('.col-line-coa-item').forEach(function (item) {
                    item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
                    item.addEventListener('mouseout', () => item.style.background = 'transparent');
                    item.addEventListener('click', function () {
                        const account = glAccounts.find(a => String(a.account_id) === this.dataset.id);
                        hidden.value = this.dataset.id;
                        search.value = colLineLabel(account);
                        dropdown.style.display = 'none';
                    });
                });
            }
            positionDropdown();
            dropdown.style.display = 'block';
        }
        // Clicking/focusing opens the FULL list (like a real dropdown), not
        // the current field filtered by its own "CODE — Title" display text
        // (which would otherwise match nothing and show "No matching
        // accounts" every time an already-picked row is reopened). Typing
        // afterward narrows it, same as a combobox.
        search.addEventListener('focus', function () {
            this.select();
            renderDropdown('');
        });
        search.addEventListener('input', function () {
            hidden.value = '';
            renderDropdown(this.value);
        });
        // Capture-phase so a scroll on any ancestor (the modal body, the
        // table's own overflow-x:auto wrapper, etc.) closes the dropdown —
        // but it ALSO catches two scroll events that aren't an ancestor
        // moving at all: the dropdown's own list scrolling (it has its own
        // overflow-y:auto), and the native internal text-scroll an <input>
        // fires once typed text outgrows its visible width. Either one was
        // closing the dropdown mid-keystroke, making typing look broken.
        window.addEventListener('scroll', function (e) {
            if (e.target !== search && !dropdown.contains(e.target)) dropdown.style.display = 'none';
        }, true);
        window.addEventListener('resize', function () { dropdown.style.display = 'none'; });
        document.addEventListener('click', function (e) {
            if (e.target !== search && !dropdown.contains(e.target)) dropdown.style.display = 'none';
        });
        return dropdown;
    }
    function addColLineRow(coaAccountId, amount) {
        document.getElementById('col-lines-body').appendChild(renderColLineRow(coaAccountId, amount));
    }
    function recalcColLinesTotal() {
        const total = Array.from(document.querySelectorAll('#col-lines-body .col-line-amount'))
            .reduce((sum, el) => sum + (parseFloat(el.value) || 0), 0);
        document.getElementById('col-lines-total').textContent = fmtPeso(total);
    }
    function getColLines() {
        return Array.from(document.querySelectorAll('#col-lines-body tr')).map(function (tr) {
            return {
                coa_account_id: tr.querySelector('.col-line-coa').value,
                amount: parseFloat(tr.querySelector('.col-line-amount').value) || 0,
            };
        }).filter(l => l.coa_account_id && l.amount > 0);
    }
    document.getElementById('col-lines-add-btn').addEventListener('click', function () { addColLineRow('', 0); });

    // ── "Deposit" dynamic rows (Bank + Period From/To + Amount) ──
    // isSwept (collection_report_deposits.is_swept — see Fund Sweeping)
    // locks the row read-only and shows a "Swept" badge instead of the
    // remove button: once Fund Sweeping has bulk-prepared a draft FTLA
    // from this deposit, it can no longer be edited or removed here —
    // see CollectionsService::update()'s docblock for why (resubmitting it
    // unmodified would otherwise risk losing its swept tracking).
    // Same type-to-filter combobox as the Account Code field above, for the
    // Bank picker — bankAccounts is already loaded in full by loadLookups(),
    // so this is a second instance of the same generic pattern, just a
    // plain <input>+hidden pair swapped in for the old <select>.
    function colDepBankLabel(bank) {
        return bank ? (bank.account_name + ' — ' + (bank.bank_name || '')) : '';
    }
    function renderColDepositRow(bankAccountId, periodFrom, periodTo, amount, isSwept, depositSlipUrl, depositSlipViewUrl) {
        const tr = document.createElement('tr');
        const disabledAttr = isSwept ? ' disabled' : '';
        const bank = bankAccounts.find(b => String(b.bank_account_id) === String(bankAccountId || ''));
        const slipUrl = depositSlipUrl || '';
        tr.innerHTML =
            '<td>' +
                '<input type="hidden" class="col-dep-bank" value="' + (bankAccountId || '') + '">' +
                '<div style="position:relative;">' +
                    '<input type="text" class="fims-input fims-input-sm col-dep-bank-search" autocomplete="off"' + disabledAttr +
                        ' style="padding-right:26px;" placeholder="Click or type to search…" value="' + escapeHtml(colDepBankLabel(bank)) + '">' +
                    (isSwept ? '' : '<i class="fas fa-chevron-down" style="position:absolute;right:9px;top:50%;transform:translateY(-50%);font-size:10px;color:var(--fims-text-subtle,#99A1B3);pointer-events:none;"></i>') +
                '</div>' +
            '</td>' +
            '<td><input type="date" class="fims-input fims-input-sm col-dep-from" value="' + (periodFrom || '') + '"' + disabledAttr + '></td>' +
            '<td><input type="date" class="fims-input fims-input-sm col-dep-to" value="' + (periodTo || '') + '"' + disabledAttr + '></td>' +
            '<td><input type="number" step="0.01" class="fims-input fims-input-sm col-dep-amount" value="' + (amount || 0) + '"' + disabledAttr + '></td>' +
            // Real file upload (POST /api/upload/file — same pattern as
            // fidelity-bond-page.php's initFbUpload()), not a pasted link —
            // col-dep-slip (hidden) holds the stable object key to resubmit;
            // col-dep-slip-status shows the current attachment + a fresh
            // "Open ↗" link (depositSlipViewUrl — a presigned URL resolved
            // server-side on every read, since the raw key isn't browsable
            // and a presigned link expires after an hour, so it can't just
            // be stored once — see CollectionsService::withDepositSlipViewUrls()).
            '<td>' +
                '<input type="hidden" class="col-dep-slip" value="' + escapeHtml(slipUrl) + '">' +
                '<input type="file" class="fims-input fims-input-sm col-dep-slip-file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"' + disabledAttr + '>' +
                '<div class="col-dep-slip-status" style="font-size:11px;margin-top:3px;">' +
                    (slipUrl ? 'Attached' + (depositSlipViewUrl ? ' — <a href="' + escapeHtml(depositSlipViewUrl) + '" target="_blank" rel="noopener">Open ↗</a>' : '') : '') +
                '</div>' +
            '</td>' +
            '<td>' + (isSwept ? '' : '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-col-dep><i class="fas fa-trash"></i></button>') + '</td>';
        tr.dataset.swept = isSwept ? '1' : '0';
        let dropdown = null;
        if (isSwept) {
            tr.style.opacity = '0.75';
        } else {
            wireColDepSlipUpload(tr);
            dropdown = wireColDepBankSearch(tr);
            tr.querySelector('.col-dep-amount').addEventListener('input', recalcColDepositsTotal);
            tr.querySelector('.col-dep-from').addEventListener('change', function () { colOnDepositPeriodChange(tr); });
            tr.querySelector('.col-dep-to').addEventListener('change', function () { colOnDepositPeriodChange(tr); });
            tr.querySelector('[data-remove-col-dep]').addEventListener('click', function () {
                if (dropdown) dropdown.remove();
                tr.remove();
                recalcColDepositsTotal();
            });
        }
        return tr;
    }

    // Deposit Slip real upload — same POST /api/upload/file endpoint and
    // 25MB limit as fidelity-bond-page.php's initFbUpload(), just row-scoped
    // (class-based lookup within this <tr>, since Deposit rows are added
    // dynamically) instead of fixed element ids.
    const COL_DEP_SLIP_MAX_BYTES = 25 * 1024 * 1024;
    function wireColDepSlipUpload(tr) {
        const fileInput = tr.querySelector('.col-dep-slip-file');
        const hidden = tr.querySelector('.col-dep-slip');
        const status = tr.querySelector('.col-dep-slip-status');
        fileInput.addEventListener('change', function () {
            const file = fileInput.files && fileInput.files[0];
            if (!file) return;
            if (file.size > COL_DEP_SLIP_MAX_BYTES) {
                status.textContent = file.name + ' is over the 25MB limit.';
                status.style.color = 'var(--fims-danger, #DC3545)';
                fileInput.value = '';
                return;
            }
            status.textContent = 'Uploading ' + file.name + '…';
            status.style.color = '';
            fileInput.disabled = true;
            const fd = new FormData();
            fd.append('file', file);
            fetch('/api/upload/file', { method: 'POST', headers: authHeaders(), body: fd })
                .then(r => r.json())
                .then(function (json) {
                    fileInput.disabled = false;
                    if (!json.success) {
                        status.textContent = json.message || 'Upload failed.';
                        status.style.color = 'var(--fims-danger, #DC3545)';
                        fileInput.value = '';
                        return;
                    }
                    hidden.value = json.link;
                    status.style.color = '';
                    status.innerHTML = 'Attached: ' + escapeHtml(file.name) +
                        (json.preview_url ? ' — <a href="' + escapeHtml(json.preview_url) + '" target="_blank" rel="noopener">Open ↗</a>' : '');
                })
                .catch(function () {
                    fileInput.disabled = false;
                    status.textContent = 'Could not reach the server.';
                    status.style.color = 'var(--fims-danger, #DC3545)';
                    fileInput.value = '';
                });
        });
    }

    // See wireColLineCoaSearch() above for why the dropdown lives on <body>
    // with position:fixed (escaping #col-deposits-table's own
    // overflow-x:auto ancestor) and why its z-index has to clear the modal
    // overlay's own 9000.
    function wireColDepBankSearch(tr) {
        const hidden   = tr.querySelector('.col-dep-bank');
        const search   = tr.querySelector('.col-dep-bank-search');
        const dropdown = document.createElement('div');
        dropdown.className = 'col-dep-bank-dropdown';
        dropdown.style.cssText = 'display:none;position:fixed;background:#fff;border:1px solid #E4ECF2;border-radius:6px;max-height:200px;overflow-y:auto;z-index:9500;box-shadow:0 4px 12px rgba(0,0,0,.15);';
        document.body.appendChild(dropdown);

        function positionDropdown() {
            const rect = search.getBoundingClientRect();
            dropdown.style.left  = rect.left + 'px';
            dropdown.style.top   = rect.bottom + 2 + 'px';
            dropdown.style.width = rect.width + 'px';
        }
        function renderDropdown(query) {
            const q = query.trim().toLowerCase();
            const matches = (q ? bankAccounts.filter(b =>
                b.account_name.toLowerCase().includes(q) || (b.bank_name || '').toLowerCase().includes(q)
            ) : bankAccounts).slice(0, 50);
            if (!matches.length) {
                dropdown.innerHTML = '<div style="padding:8px 12px;font-size:12px;color:var(--fims-text-subtle);">No matching banks</div>';
            } else {
                dropdown.innerHTML = matches.map(b =>
                    '<div class="col-dep-bank-item" data-id="' + b.bank_account_id + '" style="padding:7px 12px;cursor:pointer;font-size:12.5px;border-bottom:1px solid #F4F7FA;">' +
                        '<strong>' + escapeHtml(b.account_name) + '</strong> — ' + escapeHtml(b.bank_name || '') +
                    '</div>'
                ).join('');
                dropdown.querySelectorAll('.col-dep-bank-item').forEach(function (item) {
                    item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
                    item.addEventListener('mouseout', () => item.style.background = 'transparent');
                    item.addEventListener('click', function () {
                        const bank = bankAccounts.find(b => String(b.bank_account_id) === this.dataset.id);
                        hidden.value = this.dataset.id;
                        search.value = colDepBankLabel(bank);
                        dropdown.style.display = 'none';
                    });
                });
            }
            positionDropdown();
            dropdown.style.display = 'block';
        }
        search.addEventListener('focus', function () {
            this.select();
            renderDropdown('');
        });
        search.addEventListener('input', function () {
            hidden.value = '';
            renderDropdown(this.value);
        });
        window.addEventListener('scroll', function (e) {
            if (e.target !== search && !dropdown.contains(e.target)) dropdown.style.display = 'none';
        }, true);
        window.addEventListener('resize', function () { dropdown.style.display = 'none'; });
        document.addEventListener('click', function (e) {
            if (e.target !== search && !dropdown.contains(e.target)) dropdown.style.display = 'none';
        });
        return dropdown;
    }
    function addColDepositRow(bankAccountId, periodFrom, periodTo, amount, isSwept, depositSlipUrl, depositSlipViewUrl) {
        document.getElementById('col-deposits-body').appendChild(renderColDepositRow(bankAccountId, periodFrom, periodTo, amount, isSwept, depositSlipUrl, depositSlipViewUrl));
    }
    function recalcColDepositsTotal() {
        const total = Array.from(document.querySelectorAll('#col-deposits-body .col-dep-amount'))
            .reduce((sum, el) => sum + (parseFloat(el.value) || 0), 0);
        document.getElementById('col-deposits-total').textContent = fmtPeso(total);
    }
    // Swept rows are excluded — they're read-only and the backend leaves
    // them untouched on update() rather than having them resubmitted.
    function getColDeposits() {
        return Array.from(document.querySelectorAll('#col-deposits-body tr'))
            .filter(tr => tr.dataset.swept !== '1')
            .map(function (tr) {
                return {
                    bank_account_id: tr.querySelector('.col-dep-bank').value,
                    period_from: tr.querySelector('.col-dep-from').value,
                    period_to: tr.querySelector('.col-dep-to').value,
                    amount: parseFloat(tr.querySelector('.col-dep-amount').value) || 0,
                    deposit_slip_url: tr.querySelector('.col-dep-slip').value.trim(),
                };
            }).filter(d => d.bank_account_id && d.amount > 0);
    }
    document.getElementById('col-deposits-add-btn').addEventListener('click', function () { addColDepositRow('', '', '', 0); });

    // ── Lookups ──
    function loadLookups() {
        return Promise.all([
            fetch(API + '/lookup/responsibility-centers', { headers: authHeaders() }).then(r => r.json()),
            fetch(API + '/lookup/gl-accounts', { headers: authHeaders() }).then(r => r.json()),
            fetch(API + '/lookup/bank-accounts', { headers: authHeaders() }).then(r => r.json()),
            // Cross-module call, same precedent as bank-management-page.php
            // calls elsewhere in this project — Collecting Officer Master
            // Data lives in its own module, not Collections' own API.
            fetch('/api/collecting-officers/all', { headers: authHeaders() }).then(r => r.json()),
        ]).then(function ([rcJson, glJson, bankJson, coJson]) {
            regions = (rcJson && rcJson.success && Array.isArray(rcJson.data)) ? rcJson.data : [];
            glAccounts = (glJson && glJson.success && Array.isArray(glJson.data)) ? glJson.data : [];
            bankAccounts = (bankJson && bankJson.success && Array.isArray(bankJson.data)) ? bankJson.data : [];
            collectingOfficers = (coJson && coJson.success && Array.isArray(coJson.data)) ? coJson.data : [];

            const rcSelect = document.getElementById('col_rc_id');
            rcSelect.innerHTML = '<option value="">— Select Region —</option>' + regions.map(function (rc) {
                return '<option value="' + rc.rc_id + '">' + escapeHtml(rc.rc_code + ' — ' + rc.rc_name) + '</option>';
            }).join('');
        }).catch(() => toast('Could not load lookups.', true));
    }

    // ── Collecting Officer / Collecting Office auto-fill ──
    // Narrows the Collecting Officer select to whichever officers are
    // tagged (fims.collecting_officers.rc_id) to the chosen Region/Office;
    // auto-picks the officer outright when there's exactly one match, else
    // just waits for a manual pick among the narrowed list. Collecting
    // Office (the free-text field) defaults to the Region's own name —
    // still a plain editable input, not locked, so typing over it afterward
    // just sticks until the Region is changed again. Does NOT touch
    // Account Code / GL-SL on the Collection(s) lines — those stay entirely
    // manual regardless of which officer or region is picked.
    function colOfficersForRegion(rcId) {
        return rcId ? collectingOfficers.filter(o => String(o.rc_id) === String(rcId)) : [];
    }

    function colOnRegionChange() {
        const rcId = document.getElementById('col_rc_id').value;
        const region = regions.find(r => String(r.rc_id) === String(rcId));
        document.getElementById('col_collecting_office').value = region ? region.rc_name : '';

        const matches = colOfficersForRegion(rcId);
        const sel = document.getElementById('col_collecting_officer_id');
        sel.innerHTML = '<option value="">' + (matches.length ? '— Select Collecting Officer —' : '— None tagged to this Region —') + '</option>' +
            matches.map(function (o) {
                return '<option value="' + o.collecting_officer_id + '">' + escapeHtml(o.officer_name) + '</option>';
            }).join('');
        if (matches.length === 1) sel.value = matches[0].collecting_officer_id;

        colLoadCollectedForRegion(rcId);
    }
    document.getElementById('col_rc_id').addEventListener('change', colOnRegionChange);

    // ── "Collected Collections in this Region" reference list (Deposit
    // section) — see that section's own comment for why this exists. Kept
    // in colCollectedForRegion (not just rendered) so each Deposit row's
    // Period From/To can sum against it — see colSumCollectedForPeriod().
    // Shows every collected line for this Region/Office, full stop — an
    // earlier version tried to hide lines already "covered" by a Deposit,
    // inferred by matching region-level totals oldest-first, but a Deposit
    // isn't actually recorded against the Collection(s) it covers ("Register
    // Deposit" always creates its own separate Collection Report row), so
    // that was a guess dressed up as a fact and could hide genuinely
    // undeposited money just because unrelated totals happened to add up.
    // Better to show everything and let whoever's entering the Deposit use
    // their own judgment than to silently hide something wrong. ──
    let colCollectedForRegion = [];
    // Periods already covered by an existing (saved) Deposit for this Region/Office — unlike the
    // amount-matching heuristic reverted above, this is exact, not inferred: a Deposit row's own
    // Period From/To is real data the preparer entered, so a Collection line whose report_date
    // falls inside one of these ranges really was already deposited. Used by
    // colSumCollectedForPeriod() to stop the SAME range (picked again, by accident or on purpose)
    // from re-summing money that's already gone to the bank. Rows with neither period bound set are
    // skipped — there's no date range to exclude against.
    let colDepositedRangesForRegion = [];
    function colLoadCollectedForRegion(rcId) {
        const tbody = document.getElementById('col-collected-list-body');
        const summary = document.getElementById('col-collected-summary');
        colCollectedForRegion = [];
        colDepositedRangesForRegion = [];
        if (!rcId) {
            tbody.innerHTML = '<tr class="fims-empty"><td colspan="3">—</td></tr>';
            summary.textContent = 'Select a Region/Office above to view its collected collections.';
            return;
        }
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="3">Loading…</td></tr>';
        summary.textContent = 'Loading…';
        Promise.all([
            fetch(API + '/collections-list?rc_id=' + encodeURIComponent(rcId), { headers: authHeaders() }).then(r => r.json()),
            fetch(API + '/undeposited-summary?rc_id=' + encodeURIComponent(rcId), { headers: authHeaders() }).then(r => r.json()),
            fetch(API + '/deposits-list?rc_id=' + encodeURIComponent(rcId), { headers: authHeaders() }).then(r => r.json()),
        ])
            .then(function (results) {
                const json = results[0];
                const summaryJson = results[1];
                const depositsJson = results[2];
                const rows = (json && json.data) ? json.data : [];
                colCollectedForRegion = rows;
                colDepositedRangesForRegion = ((depositsJson && depositsJson.data) ? depositsJson.data : [])
                    .filter(function (d) { return d.period_from || d.period_to; })
                    .map(function (d) { return { from: d.period_from || null, to: d.period_to || null }; });
                // "Already deposited" nets against every Deposit ever recorded for this Region/Office
                // regardless of is_swept — see getUndepositedSummary()'s own comment for why the full
                // collected-lines list below is still shown as-is (never hidden), only this summary
                // line nets the two totals.
                const balance = (summaryJson && summaryJson.data) ? summaryJson.data : null;
                const balanceLine = balance
                    ? ' — Already deposited: ' + fmtPeso(balance.total_deposited) + ' — Undeposited balance: ' + fmtPeso(balance.undeposited_balance)
                    : '';
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="3">No collections on record for this Region/Office.</td></tr>';
                    summary.textContent = 'No collections on record for this Region/Office.' + balanceLine;
                    return;
                }
                const total = rows.reduce((sum, r) => sum + (parseFloat(r.amount) || 0), 0);
                summary.textContent = rows.length + ' collection' + (rows.length === 1 ? '' : 's') + ' found — Total: ' + fmtPeso(total) + balanceLine;
                tbody.innerHTML = rows.map(function (r) {
                    const glLabel = r.account_code ? escapeHtml(r.account_code + ' — ' + (r.account_title || '')) : '—';
                    return '<tr>' +
                        '<td>' + escapeHtml(r.report_date || '—') + '</td>' +
                        '<td>' + glLabel + '</td>' +
                        '<td class="text-right">' + fmtPeso(r.amount) + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => {
                tbody.innerHTML = '<tr class="fims-empty"><td colspan="3">Could not reach the server.</td></tr>';
                summary.textContent = 'Could not reach the server.';
            });
    }

    // A date already falls inside an existing Deposit's own Period From/To — see
    // colDepositedRangesForRegion's own comment.
    function colDateAlreadyDeposited(d) {
        return colDepositedRangesForRegion.some(function (r) {
            if (r.from && d < r.from) return false;
            if (r.to && d > r.to) return false;
            return true;
        });
    }

    // Sums colCollectedForRegion's amounts whose report_date falls within
    // [periodFrom, periodTo] (inclusive) — an unset bound is treated as
    // open-ended on that side, so entering just one date still sums
    // everything on-or-after/on-or-before it. Excludes any line already
    // covered by an earlier Deposit's own period (colDateAlreadyDeposited())
    // so re-picking the same range doesn't re-count money already banked.
    function colSumCollectedForPeriod(periodFrom, periodTo) {
        return colCollectedForRegion
            .filter(function (r) {
                const d = r.report_date;
                if (!d) return false;
                if (periodFrom && d < periodFrom) return false;
                if (periodTo && d > periodTo) return false;
                if (colDateAlreadyDeposited(d)) return false;
                return true;
            })
            .reduce(function (sum, r) { return sum + (parseFloat(r.amount) || 0); }, 0);
    }

    // Auto-fills a Deposit row's Amount from the Collected Collections sum
    // for whatever Period From/To is now set — fires on either date
    // changing. Still a plain editable field afterward, same "auto-fill,
    // not lock" precedent as Collecting Office/Officer above.
    function colOnDepositPeriodChange(tr) {
        const from = tr.querySelector('.col-dep-from').value;
        const to = tr.querySelector('.col-dep-to').value;
        if (!from && !to) return;
        const sum = colSumCollectedForPeriod(from, to);
        tr.querySelector('.col-dep-amount').value = sum ? sum.toFixed(2) : '';
        recalcColDepositsTotal();
    }

    // ── Top-level tabs ── (same fims-tabs-underline convention as
    // app/pages/fidelity-bond-page.php's #fbTabNav) — Collections tab loads
    // immediately on boot; Deposits is lazy-loaded on first switch.
    const colLoaded = { collections: false, deposits: false };
    document.querySelectorAll('#colTabNav [data-col-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#colTabNav .fims-tab-underline').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const tab = this.dataset.colTab;
            document.getElementById('colTabCollections').style.display = tab === 'collections' ? '' : 'none';
            document.getElementById('colTabDeposits').style.display = tab === 'deposits' ? '' : 'none';
            if (tab === 'collections' && !colLoaded.collections) { colLoaded.collections = true; loadCollectionsList(); }
            if (tab === 'deposits' && !colLoaded.deposits) { colLoaded.deposits = true; loadDepositsList(); }
        });
    });
    // Both the Register/Edit modal's Save and Delete reload whichever of
    // these two lists has already been loaded, so switching back to a tab
    // visited earlier in this session shows the fresh data without an
    // extra round-trip for a tab that was never opened.
    function refreshColLists() {
        if (colLoaded.collections) loadCollectionsList();
        if (colLoaded.deposits) loadDepositsList();
    }

    // ── Collections tab ──
    // No Deposited/Not Deposited status here — a Deposit isn't recorded
    // against the same collection_report_id as the Collection(s) it covers
    // ("Register Deposit" always creates its own separate Collection Report
    // row — see CollectionsService's own docblock), so there's no reliable
    // way to tell which specific report a given deposit actually settled.
    // An earlier attempt inferred it by matching region-level totals
    // oldest-first, but that's a guess dressed up as a fact — it can (and
    // did) mislabel a report as "Fully Deposited" just because the totals
    // happened to add up, not because that money actually went there.
    function loadCollectionsList() {
        const tbody = document.getElementById('colCollectionsBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        const q = document.getElementById('colCollectionsSearch').value.trim();
        fetch(API + '/collections-list?search=' + encodeURIComponent(q), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                let total = 0;
                rows.forEach(function (r) { total += parseFloat(r.amount) || 0; });
                document.querySelector('#colCollectionsStats [data-kpi="total"]').textContent = rows.length;
                document.querySelector('#colCollectionsStats [data-kpi="total_amount"]').textContent = fmtPeso(total);

                // Each API row is one GL line; group them back by their parent
                // Collection Report so one Register Collection submission reads
                // as one row (with its GL lines stacked inside), instead of
                // repeating the Date/Region/Office/Actions once per line.
                const groupsById = {};
                const groupOrder = [];
                rows.forEach(function (r) {
                    const key = r.collection_report_id;
                    if (!groupsById[key]) {
                        groupsById[key] = { report_date: r.report_date, rc_code: r.rc_code, rc_name: r.rc_name, collecting_office: r.collecting_office, collection_report_id: r.collection_report_id, lines: [], amount: 0 };
                        groupOrder.push(key);
                    }
                    groupsById[key].lines.push(r);
                    groupsById[key].amount += parseFloat(r.amount) || 0;
                });
                const groups = groupOrder.map(function (k) { return groupsById[k]; });

                document.getElementById('colCollectionsSubtitle').textContent = 'Collections (' + groups.length + ')';

                if (!groups.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = groups.map(function (g, i) {
                    const regionLabel = g.rc_code ? escapeHtml(g.rc_code + ' — ' + g.rc_name) : '—';
                    const glLabel = g.lines.map(function (l) {
                        return l.account_code ? escapeHtml(l.account_code + ' — ' + (l.account_title || '')) : '—';
                    }).join('<br>');
                    const amountLabel = g.lines.length > 1
                        ? g.lines.map(function (l) { return fmtPeso(l.amount); }).join('<br>') + '<br><strong>' + fmtPeso(g.amount) + '</strong>'
                        : fmtPeso(g.amount);
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(g.report_date || '—') + '</td>' +
                        '<td>' + regionLabel + '</td>' +
                        '<td>' + escapeHtml(g.collecting_office || '—') + '</td>' +
                        '<td>' + glLabel + '</td>' +
                        '<td class="text-right">' + amountLabel + '</td>' +
                        '<td class="text-center">' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-jev="' + g.collection_report_id + '" title="Create Collection JEV (Dr Cash-Collecting Officers / Cr Collection lines)"><i class="fas fa-file-invoice-dollar"></i></button>' +
                        '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-jev]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openJevModal(this.dataset.jev, 'collection'); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('colCollectionsSearch').addEventListener('input', function () {
        clearTimeout(window._colCollectionsSearchTimer);
        window._colCollectionsSearchTimer = setTimeout(loadCollectionsList, 300);
    });

    // ── Deposits tab ──
    // is_swept is per-deposit now (not the parent report's aggregate), so
    // there's no "Partially Swept" state here the way the old combined
    // report list needed — just Swept/Not Swept per row.
    function depositSweptBadge(isSwept) {
        return isSwept
            ? '<span class="fims-badge fims-badge-green">Swept</span>'
            : '<span class="fims-badge fims-badge-gray">Not Swept</span>';
    }

    function loadDepositsList() {
        const tbody = document.getElementById('colDepositsBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="9">Loading…</td></tr>';
        const q = document.getElementById('colDepositsSearch').value.trim();
        fetch(API + '/deposits-list?search=' + encodeURIComponent(q), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                document.getElementById('colDepositsSubtitle').textContent = 'Deposits (' + rows.length + ')';
                let total = 0;
                rows.forEach(function (r) { total += parseFloat(r.amount) || 0; });
                document.querySelector('#colDepositsStats [data-kpi="total"]').textContent = rows.length;
                document.querySelector('#colDepositsStats [data-kpi="total_amount"]').textContent = fmtPeso(total);

                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="9">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const regionLabel = r.rc_code ? escapeHtml(r.rc_code + ' — ' + r.rc_name) : '—';
                    const bankLabel = r.account_name ? escapeHtml(r.account_name + (r.bank_name ? ' (' + r.bank_name + ')' : '')) : '—';
                    const period = (r.period_from || r.period_to) ? escapeHtml((r.period_from || '—') + ' to ' + (r.period_to || '—')) : '—';
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.report_date || '—') + '</td>' +
                        '<td>' + regionLabel + '</td>' +
                        '<td>' + escapeHtml(r.collecting_office || '—') + '</td>' +
                        '<td>' + bankLabel + '</td>' +
                        '<td>' + period + '</td>' +
                        '<td class="text-right">' + fmtPeso(r.amount) + '</td>' +
                        '<td class="text-center">' + depositSweptBadge(r.is_swept) + '</td>' +
                        '<td class="text-center">' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-jev="' + r.collection_report_id + '" title="Create Deposit JEV (Dr bank GL accounts / Cr Cash-Collecting Officers)"><i class="fas fa-university"></i></button>' +
                        '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-jev]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openJevModal(this.dataset.jev, 'deposit'); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="9">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('colDepositsSearch').addEventListener('input', function () {
        clearTimeout(window._colDepositsSearchTimer);
        window._colDepositsSearchTimer = setTimeout(loadDepositsList, 300);
    });

    // ── Register / Edit modal ──
    function clearReportModal() {
        document.getElementById('col_report_id').value = '';
        document.getElementById('col_rc_id').value = '';
        document.getElementById('col_collecting_officer_id').innerHTML = '<option value="">— Select Region first —</option>';
        document.getElementById('col_collecting_office').value = '';
        document.getElementById('col_report_date').value = '';
        colLoadCollectedForRegion('');
        // Each row's Account Code / Bank dropdown lives on <body> (see
        // wireColLineCoaSearch()'s comment), not inside col-lines-body /
        // col-deposits-body — wiping those containers' innerHTML alone
        // would orphan them.
        document.querySelectorAll('body > .col-line-coa-dropdown, body > .col-dep-bank-dropdown').forEach(d => d.remove());
        document.getElementById('col-lines-body').innerHTML = '';
        document.getElementById('col-deposits-body').innerHTML = '';
        recalcColLinesTotal();
        recalcColDepositsTotal();
    }

    // Registering from the Collections tab only shows/requires the
    // Collection(s) section; from the Deposits tab, only Deposit. A section
    // that's hidden is also never given a default blank row, so it submits
    // as an empty array — CollectionsService::store() only requires AT LEAST
    // ONE of the two sections to have a line, not both (see its own comment).
    function colSetFormMode(mode) {
        document.getElementById('col-lines-section').style.display = mode === 'deposits' ? 'none' : '';
        document.getElementById('col-deposits-section').style.display = mode === 'collections' ? 'none' : '';
    }

    // Register-only — a saved Collection/Deposit report can no longer be reopened for editing (no
    // UI entry point calls this with an id anymore), so there's nothing left to correct a mistake
    // except registering a new report. mode is 'collections' or 'deposits' (see colSetFormMode()).
    window.openReportModal = function (mode) {
        clearReportModal();
        mode = mode || 'full';
        colSetFormMode(mode);
        document.getElementById('colModalTitle').textContent =
            mode === 'collections' ? 'Register Collection' : mode === 'deposits' ? 'Register Deposit' : 'Register Collection Report';
        if (mode !== 'deposits') addColLineRow('', 0);
        if (mode !== 'collections') addColDepositRow('', '', '', 0);
        openModal('m-col-report');
    };

    document.getElementById('colRegisterCollectionBtn').addEventListener('click', function () { openReportModal('collections'); });
    document.getElementById('colRegisterDepositBtn').addEventListener('click', function () { openReportModal('deposits'); });

    document.getElementById('colSaveBtn').addEventListener('click', function () {
        const id = document.getElementById('col_report_id').value;
        const rcId = document.getElementById('col_rc_id').value;
        const reportDate = document.getElementById('col_report_date').value;
        if (!rcId) { toast('Region / Office is required.', true); return; }
        if (!reportDate) { toast('Date is required.', true); return; }
        const lines = getColLines();
        const deposits = getColDeposits();
        const hasSweptDeposit = document.querySelector('#col-deposits-body tr[data-swept="1"]') !== null;
        // A dedicated Register Collection / Register Deposit form only has
        // its own section to fill — see colSetFormMode() — so only requires
        // ONE of the two sections to have a line, matching the server-side
        // relaxation in CollectionsService::store()/update().
        if (!lines.length && !deposits.length && !hasSweptDeposit) {
            toast('At least one Collection line or Deposit line is required.', true);
            return;
        }

        const payload = {
            rc_id: rcId,
            collecting_officer_id: document.getElementById('col_collecting_officer_id').value,
            collecting_office: document.getElementById('col_collecting_office').value.trim(),
            report_date: reportDate,
            lines: lines,
            deposits: deposits,
        };
        const url = id ? (API + '/' + id + '/update') : (API + '/store');
        const btn = this;
        btn.disabled = true;
        fetch(url, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Save failed.', true); btn.disabled = false; return; }
                toast('Collection report saved.');
                closeModal('m-col-report');
                refreshColLists();
                btn.disabled = false;
            })
            .catch(() => { toast('Could not reach the server.', true); btn.disabled = false; });
    });

    // ── Journal Entry Voucher prefill modal (Collection leg / Deposit leg) ──
    let colJevState = null; // { reportId, leg }
    let colJevBooksLoaded = null;

    function colJevLoadBooks() {
        if (colJevBooksLoaded) return colJevBooksLoaded;
        colJevBooksLoaded = fetch('/api/books/all', { headers: authHeaders() }).then(r => r.json()).then(function (json) {
            const books = (json && json.data) ? json.data : [];
            document.getElementById('colJevBook').innerHTML = '<option value="">Select a book…</option>' +
                books.map(b => '<option value="' + b.book_id + '">' + escapeHtml(b.name) + '</option>').join('');
        });
        return colJevBooksLoaded;
    }

    function colJevRenderLines(lines) {
        const tbody = document.getElementById('colJevLinesBody');
        let totalDebit = 0, totalCredit = 0;
        tbody.innerHTML = lines.map(function (l) {
            totalDebit += parseFloat(l.debit) || 0;
            totalCredit += parseFloat(l.credit) || 0;
            let row = '<tr>' +
                '<td>' + escapeHtml(l.account_code || '') + (l.account_title ? ' — ' + escapeHtml(l.account_title) : '') + '</td>' +
                '<td class="text-right">' + (l.debit > 0 ? fmtPeso(l.debit) : '') + '</td>' +
                '<td class="text-right">' + (l.credit > 0 ? fmtPeso(l.credit) : '') + '</td>' +
                '</tr>';
            if (l.sl_code) {
                const slLabel = l.sl_name ? (l.sl_name + ' (' + l.sl_code + ')') : l.sl_code;
                row += '<tr>' +
                    '<td style="padding-left:24px;font-size:12px;color:var(--fims-text-muted,#6B7280);">↳ SL: ' + escapeHtml(slLabel) + '</td>' +
                    '<td class="text-right" style="font-size:12px;color:var(--fims-text-muted,#6B7280);">' + (l.debit > 0 ? fmtPeso(l.debit) : '') + '</td>' +
                    '<td class="text-right" style="font-size:12px;color:var(--fims-text-muted,#6B7280);">' + (l.credit > 0 ? fmtPeso(l.credit) : '') + '</td>' +
                    '</tr>';
            }
            return row;
        }).join('');
        document.getElementById('colJevTotalDebit').textContent = fmtPeso(totalDebit);
        document.getElementById('colJevTotalCredit').textContent = fmtPeso(totalCredit);
    }

    window.openJevModal = function (reportId, leg) {
        colJevState = { reportId: reportId, leg: leg };
        document.getElementById('colJevTitle').textContent = (leg === 'collection' ? 'Collection' : 'Deposit') + ' Journal Entry Voucher';
        document.getElementById('colJevAlready').style.display = 'none';
        document.getElementById('colJevWarnings').style.display = 'none';
        document.getElementById('colJevForm').style.display = '';
        document.getElementById('colJevSubmitBtn').style.display = '';
        document.getElementById('colJevSubmitBtn').disabled = true;
        document.getElementById('colJevParticulars').value = '';
        document.getElementById('colJevLinesBody').innerHTML = '';
        openModal('m-col-jev');
        colJevLoadBooks();
        fetch(API + '/' + reportId + '/jev-prefill/' + leg, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) {
                    document.getElementById('colJevForm').style.display = 'none';
                    document.getElementById('colJevSubmitBtn').style.display = 'none';
                    document.getElementById('colJevAlready').style.display = '';
                    document.getElementById('colJevAlready').style.background = 'var(--fims-danger-soft,#FAE1E3)';
                    document.getElementById('colJevAlready').style.color = 'var(--fims-danger,#C0392B)';
                    document.getElementById('colJevAlready').textContent = json.message || 'Could not prepare this Journal Entry Voucher.';
                    return;
                }
                const d = json.data;
                if (d.already) {
                    document.getElementById('colJevForm').style.display = 'none';
                    document.getElementById('colJevSubmitBtn').style.display = 'none';
                    document.getElementById('colJevAlready').style.display = '';
                    document.getElementById('colJevAlready').textContent = 'Already journalized as ' + d.already.jev_no +
                        (d.already.jev_status ? ' (' + String(d.already.jev_status).replace(/_/g, ' ').toLowerCase() + ')' : '') + '. See Journal Entry Vouchers.';
                    return;
                }
                document.getElementById('colJevDate').value = d.jev_date || '';
                document.getElementById('colJevParticulars').value = d.particulars || '';
                colJevRenderLines(d.lines || []);
                document.getElementById('colJevSubmitBtn').disabled = false;
                if (d.warnings && d.warnings.length) {
                    document.getElementById('colJevWarnings').style.display = '';
                    document.getElementById('colJevWarnings').innerHTML = d.warnings.map(w => escapeHtml(w)).join('<br>');
                }
            })
            .catch(function () {
                document.getElementById('colJevForm').style.display = 'none';
                document.getElementById('colJevAlready').style.display = '';
                document.getElementById('colJevAlready').textContent = 'Could not reach the server.';
            });
    };

    document.getElementById('colJevSubmitBtn').addEventListener('click', function () {
        if (!colJevState) return;
        const bookId = document.getElementById('colJevBook').value;
        if (!bookId) { toast('Select a Book before submitting.', true); return; }
        const btn = this;
        btn.disabled = true;
        fetch(API + '/' + colJevState.reportId + '/jev-prefill/' + colJevState.leg, {
            method: 'POST', headers: jsonHeaders(),
            body: JSON.stringify({ book_id: bookId, particulars: document.getElementById('colJevParticulars').value.trim() }),
        })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) { toast(json.message || 'Failed to save the Journal Entry Voucher.', true); btn.disabled = false; return; }
                toast('Journal Entry Voucher ' + (json.data.jev_no || '') + ' saved as Draft.');
                closeModal('m-col-jev');
                refreshColLists();
            })
            .catch(function () { toast('Could not reach the server.', true); btn.disabled = false; });
    });

    loadLookups().then(function () { colLoaded.collections = true; loadCollectionsList(); });
})();
</script>
