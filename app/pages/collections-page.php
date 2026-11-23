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
 * fims-modal-overlay, etc.) match app/fims-css/fims.css — same convention
 * as app/pages/bank-management-page.php, including its itemized dynamic-row
 * table pattern (see field-rf-table there) reused here for both the
 * Collection(s) and Deposit sections.
 */

namespace PHPMaker2024\FIMS;

$colCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $colCssVer ?>" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div class="fims-wrap" id="colModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <h1>Collections</h1>
            <p>Collecting Officer's Collection &amp; Deposit Reports</p>
        </div>
        <div class="fims-page-header-right">
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="colRegisterBtn">
                <i class="fas fa-plus mr-1"></i>Register Collection Report
            </button>
        </div>
    </div>

    <div class="fims-tabs-underline mb-3" id="colTabNav">
        <button class="fims-tab-underline active" data-col-tab="reports">Collection Reports</button>
        <button class="fims-tab-underline" data-col-tab="fundsweep">Fund Sweeping</button>
    </div>

    <div id="colTabReports">
        <div class="fims-stat-grid" id="colStats" style="grid-template-columns:repeat(3,1fr)">
            <div class="fims-stat-card fims-card-blue">
                <div class="fims-stat-top"><span class="fims-stat-label">Reports</span><span class="fims-stat-icon"><i class="fas fa-file-alt"></i></span></div>
                <div class="fims-stat-value" data-kpi="total">0</div>
            </div>
            <div class="fims-stat-card fims-card-green">
                <div class="fims-stat-top"><span class="fims-stat-label">Total Collections</span><span class="fims-stat-icon"><i class="fas fa-hand-holding-usd"></i></span></div>
                <div class="fims-stat-value" data-kpi="total_collections">₱0.00</div>
            </div>
            <div class="fims-stat-card fims-card-amber">
                <div class="fims-stat-top"><span class="fims-stat-label">Total Deposits</span><span class="fims-stat-icon"><i class="fas fa-university"></i></span></div>
                <div class="fims-stat-value" data-kpi="total_deposits">₱0.00</div>
            </div>
        </div>

        <div class="fims-table-panel">
            <div class="fims-panel-header">
                <div class="fims-panel-title" id="colTableSubtitle">Collection Reports (0)</div>
                <input type="text" class="fims-input fims-input-sm" id="colSearch" placeholder="Search Collecting Office or Region…" style="max-width:260px;">
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="colTable">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Date</th>
                            <th>Region / Office</th>
                            <th>Collecting Office</th>
                            <th class="text-right">Total Collections</th>
                            <th class="text-right">Total Deposits</th>
                            <th class="text-center">Swept</th>
                            <th class="text-center" style="width:150px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="colTableBody"><tr class="fims-empty"><td colspan="8">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Fund Sweeping: bulk-prepares DRAFT FTLAs (fims.fund_transfers, via
         FtlaService::create() — same endpoint the manual FTLA form uses)
         that move a Region/Office's bank accounts' un-swept Deposits
         (fims.collection_report_deposits.is_swept = FALSE, NOT
         fims.bank_accounts.current_balance — that column is never kept in
         sync by anything) into their already-assigned
         main_depository_account_id. Doesn't move money itself — see
         BankManagementService::previewFundSweep()/processFundSweep()'s
         docblocks. Accounts with no Main Depository assigned are listed as
         "Not assigned" and skipped; assign one via Bank Account
         Management's Register/Edit modal first. Calls the Bank Account
         Management module's own API (BANK_MANAGEMENT_API below) — the
         sweep logic lives there since it operates on fims.bank_accounts,
         even though the balance it now sums comes from this module's own
         Deposit records. -->
    <div id="colTabFundSweep" style="display:none;">
        <div class="fims-table-panel">
            <div class="fims-panel-header">
                <div class="fims-panel-title"><i class="fas fa-broom mr-1"></i>Fund Sweeping</div>
            </div>
            <div style="padding:12px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--fims-border, #E4ECF2);">
                <label class="fims-label" style="margin:0;white-space:nowrap;">Region / Office</label>
                <select class="fims-select fims-input-sm" id="fsRegionSelect" style="max-width:320px;">
                    <option value="">— Main Office —</option>
                </select>
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fsProcessBtn" disabled>
                    <i class="fas fa-paper-plane mr-1"></i>Process Fund Sweep
                </button>
                <span id="fsHint" style="font-size:11px;color:var(--fims-text-subtle);"></span>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table">
                    <thead><tr>
                        <th style="width:32px"><input type="checkbox" id="fsSelectAll"></th>
                        <th>Region Bank Account</th><th class="text-right">Balance</th><th>Main Depository Account</th>
                    </tr></thead>
                    <tbody id="fsBody"><tr class="fims-empty"><td colspan="4">Select a Region/Office to preview its accounts.</td></tr></tbody>
                </table>
            </div>
            <div id="fsResults" style="display:none;padding:12px 16px;border-top:1px solid var(--fims-border, #E4ECF2);font-size:12px;"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Register / Edit Collection Report
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-col-report" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:1200px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="colModalTitle">Register Collection Report</div></div>
                    <button class="fims-modal-close" data-close="m-col-report">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="col_report_id">

                    <div class="fims-form-section-title">Report Details</div>
                    <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                        <div class="fims-form-group">
                            <label class="fims-label">Region / Office *</label>
                            <select class="fims-select" id="col_rc_id">
                                <option value="">— Select Region —</option>
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
                         CollectionsService's docblock. -->
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

                    <!-- "Deposit" — Bank + Period Covered (From/To) + Amount, dynamic
                         rows. -->
                    <div class="fims-form-section-title" style="margin-top:14px;">Deposit</div>
                    <div style="overflow-x:auto;">
                        <table class="fims-table" id="col-deposits-table" style="table-layout:fixed;width:100%;min-width:720px;">
                            <thead>
                                <tr>
                                    <th>Bank</th>
                                    <th style="width:135px">Period From</th>
                                    <th style="width:135px">Period To</th>
                                    <th style="width:130px">Amount</th>
                                    <th style="width:100px">Status</th>
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
        <div class="fims-modal-dialog" style="max-width:760px;width:95vw;">
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
/* .fims-modal-content (shared fims.css) is a plain block, not a flex
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
    // Fund Sweeping operates on fims.bank_accounts — that logic lives in
    // BankManagementService, not CollectionsService, so it's called here.
    const BANK_MANAGEMENT_API = '/api/bank-management';

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
        // z-index must clear .fims-modal-overlay's own 9000 (fims.css) — this
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
    function renderColDepositRow(bankAccountId, periodFrom, periodTo, amount, isSwept) {
        const tr = document.createElement('tr');
        const disabledAttr = isSwept ? ' disabled' : '';
        const bank = bankAccounts.find(b => String(b.bank_account_id) === String(bankAccountId || ''));
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
            '<td>' + (isSwept ? '<span class="fims-badge fims-badge-green">Swept</span>' : '<span class="fims-badge fims-badge-gray">Not Swept</span>') + '</td>' +
            '<td>' + (isSwept ? '' : '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-col-dep><i class="fas fa-trash"></i></button>') + '</td>';
        tr.dataset.swept = isSwept ? '1' : '0';
        let dropdown = null;
        if (isSwept) {
            tr.style.opacity = '0.75';
        } else {
            dropdown = wireColDepBankSearch(tr);
            tr.querySelector('.col-dep-amount').addEventListener('input', recalcColDepositsTotal);
            tr.querySelector('[data-remove-col-dep]').addEventListener('click', function () {
                if (dropdown) dropdown.remove();
                tr.remove();
                recalcColDepositsTotal();
            });
        }
        return tr;
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
    function addColDepositRow(bankAccountId, periodFrom, periodTo, amount, isSwept) {
        document.getElementById('col-deposits-body').appendChild(renderColDepositRow(bankAccountId, periodFrom, periodTo, amount, isSwept));
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
        ]).then(function ([rcJson, glJson, bankJson]) {
            regions = (rcJson && rcJson.success && Array.isArray(rcJson.data)) ? rcJson.data : [];
            glAccounts = (glJson && glJson.success && Array.isArray(glJson.data)) ? glJson.data : [];
            bankAccounts = (bankJson && bankJson.success && Array.isArray(bankJson.data)) ? bankJson.data : [];

            const rcSelect = document.getElementById('col_rc_id');
            rcSelect.innerHTML = '<option value="">— Select Region —</option>' + regions.map(function (rc) {
                return '<option value="' + rc.rc_id + '">' + escapeHtml(rc.rc_code + ' — ' + rc.rc_name) + '</option>';
            }).join('');

            const fsSel = document.getElementById('fsRegionSelect');
            fsSel.innerHTML = '<option value="">— Main Office —</option>' + regions.map(function (rc) {
                return '<option value="' + rc.rc_id + '">' + escapeHtml(rc.rc_code + ' — ' + rc.rc_name) + '</option>';
            }).join('');
        }).catch(() => toast('Could not load lookups.', true));
    }

    // ── List ──
    // Swept = every deposit on the report has been bulk-prepared into a
    // draft FTLA by Fund Sweeping (collection_report_deposits.is_swept —
    // see BankManagementService::processFundSweep()); Partially Swept =
    // some but not all; Not Swept = none yet.
    function sweptBadge(r) {
        const total = parseInt(r.deposit_count) || 0;
        const swept = parseInt(r.swept_deposit_count) || 0;
        if (total === 0) return '<span class="fims-badge fims-badge-gray">—</span>';
        if (swept === 0) return '<span class="fims-badge fims-badge-gray">Not Swept</span>';
        if (swept === total) return '<span class="fims-badge fims-badge-green">Swept</span>';
        return '<span class="fims-badge fims-badge-amber">Partially Swept (' + swept + '/' + total + ')</span>';
    }

    function loadList() {
        const tbody = document.getElementById('colTableBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Loading…</td></tr>';
        const q = document.getElementById('colSearch').value.trim();
        fetch(API + '/list?search=' + encodeURIComponent(q), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                document.getElementById('colTableSubtitle').textContent = 'Collection Reports (' + rows.length + ')';

                let totalCollections = 0, totalDeposits = 0;
                rows.forEach(function (r) {
                    totalCollections += parseFloat(r.total_collections) || 0;
                    totalDeposits += parseFloat(r.total_deposits) || 0;
                });
                document.querySelector('#colStats [data-kpi="total"]').textContent = rows.length;
                document.querySelector('#colStats [data-kpi="total_collections"]').textContent = fmtPeso(totalCollections);
                document.querySelector('#colStats [data-kpi="total_deposits"]').textContent = fmtPeso(totalDeposits);

                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const regionLabel = r.rc_code ? escapeHtml(r.rc_code + ' — ' + r.rc_name) : '—';
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.report_date || '—') + '</td>' +
                        '<td>' + regionLabel + '</td>' +
                        '<td>' + escapeHtml(r.collecting_office || '—') + '</td>' +
                        '<td class="text-right">' + fmtPeso(r.total_collections) + '</td>' +
                        '<td class="text-right">' + fmtPeso(r.total_deposits) + '</td>' +
                        '<td class="text-center">' + sweptBadge(r) + '</td>' +
                        '<td class="text-center">' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-edit="' + r.collection_report_id + '" title="Edit"><i class="fas fa-pen"></i></button>' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-jev="' + r.collection_report_id + '" data-leg="collection" title="' + (r.collection_jev_id ? 'View Collection JEV' : 'Create Collection JEV (Dr Cash-Collecting Officers / Cr Collection lines)') + '"><i class="fas ' + (r.collection_jev_id ? 'fa-check-circle text-success' : 'fa-file-invoice-dollar') + '"></i></button>' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-jev="' + r.collection_report_id + '" data-leg="deposit" title="' + (r.deposit_jev_id ? 'View Deposit JEV' : 'Create Deposit JEV (Dr bank GL accounts / Cr Cash-Collecting Officers)') + '"><i class="fas ' + (r.deposit_jev_id ? 'fa-check-circle text-success' : 'fa-university') + '"></i></button>' +
                        '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openReportModal(this.dataset.edit); });
                });
                tbody.querySelectorAll('[data-jev]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openJevModal(this.dataset.jev, this.dataset.leg); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('colSearch').addEventListener('input', function () {
        clearTimeout(window._colSearchTimer);
        window._colSearchTimer = setTimeout(loadList, 300);
    });

    // ── Register / Edit modal ──
    function clearReportModal() {
        document.getElementById('col_report_id').value = '';
        document.getElementById('col_rc_id').value = '';
        document.getElementById('col_collecting_office').value = '';
        document.getElementById('col_report_date').value = '';
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

    window.openReportModal = function (id) {
        clearReportModal();
        if (!id) {
            document.getElementById('colModalTitle').textContent = 'Register Collection Report';
            addColLineRow('', 0);
            addColDepositRow('', '', '', 0);
            openModal('m-col-report');
            return;
        }
        document.getElementById('colModalTitle').textContent = 'Edit Collection Report';
        fetch(API + '/' + id, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load report.', true); return; }
                const d = json.data;
                document.getElementById('col_report_id').value = d.collection_report_id;
                document.getElementById('col_rc_id').value = d.rc_id || '';
                document.getElementById('col_collecting_office').value = d.collecting_office || '';
                document.getElementById('col_report_date').value = d.report_date || '';
                (d.lines || []).forEach(function (l) { addColLineRow(l.coa_account_id, l.amount); });
                (d.deposits || []).forEach(function (dep) { addColDepositRow(dep.bank_account_id, dep.period_from, dep.period_to, dep.amount, !!dep.is_swept); });
                recalcColLinesTotal();
                recalcColDepositsTotal();
                openModal('m-col-report');
            })
            .catch(() => toast('Could not reach the server.', true));
    };

    document.getElementById('colRegisterBtn').addEventListener('click', function () { openReportModal(null); });

    document.getElementById('colSaveBtn').addEventListener('click', function () {
        const id = document.getElementById('col_report_id').value;
        const rcId = document.getElementById('col_rc_id').value;
        const reportDate = document.getElementById('col_report_date').value;
        if (!rcId) { toast('Region / Office is required.', true); return; }
        if (!reportDate) { toast('Date is required.', true); return; }
        const lines = getColLines();
        if (!lines.length) { toast('At least one Collection line is required.', true); return; }
        const deposits = getColDeposits();
        const hasSweptDeposit = document.querySelector('#col-deposits-body tr[data-swept="1"]') !== null;
        if (!deposits.length && !hasSweptDeposit) { toast('At least one Deposit line is required.', true); return; }

        const payload = {
            rc_id: rcId,
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
                loadList();
                btn.disabled = false;
            })
            .catch(() => { toast('Could not reach the server.', true); btn.disabled = false; });
    });

    // ── Top-level module tabs ── (same fims-tabs-underline convention as
    // app/pages/fidelity-bond-page.php's #fbTabNav)
    const colTabPaneIds = { reports: 'colTabReports', fundsweep: 'colTabFundSweep' };
    let fsLoadedOnce = false;
    document.querySelectorAll('#colTabNav [data-col-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#colTabNav .fims-tab-underline').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const tab = this.dataset.colTab;
            Object.entries(colTabPaneIds).forEach(function ([key, id]) {
                document.getElementById(id).style.display = key === tab ? '' : 'none';
            });
            if (tab === 'fundsweep' && !fsLoadedOnce) { fsLoadedOnce = true; loadFundSweepPreview(); }
        });
    });

    // ── Fund Sweeping tab ──
    // Preview + bulk-prepare DRAFT FTLAs that move a Region/Office's
    // non-zero bank accounts into their already-assigned Main Depository
    // account — see BankManagementService::previewFundSweep()/
    // processFundSweep()'s docblocks. Doesn't move money itself. Hits
    // BANK_MANAGEMENT_API, not this page's own API — see its declaration
    // above.
    document.getElementById('fsRegionSelect').addEventListener('change', loadFundSweepPreview);

    function fsUpdateProcessBtn() {
        const checked = document.querySelectorAll('#fsBody .fs-row-check:checked').length;
        document.getElementById('fsProcessBtn').disabled = checked === 0;
    }

    document.getElementById('fsSelectAll').addEventListener('change', function () {
        document.querySelectorAll('#fsBody .fs-row-check').forEach(cb => { cb.checked = this.checked; });
        fsUpdateProcessBtn();
    });

    function loadFundSweepPreview() {
        const tbody = document.getElementById('fsBody');
        const rcId = document.getElementById('fsRegionSelect').value;
        const processBtn = document.getElementById('fsProcessBtn');
        const selectAll = document.getElementById('fsSelectAll');
        document.getElementById('fsResults').style.display = 'none';
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="4">Loading…</td></tr>';
        processBtn.disabled = true;
        selectAll.checked = false;

        fetch(BANK_MANAGEMENT_API + '/fund-sweep/preview?rc_id=' + encodeURIComponent(rcId), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="4">No accounts on file for this Region/Office.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    const balance = parseFloat(r.current_balance) || 0;
                    const eligible = balance > 0 && !!r.main_depository_account_id;
                    const mainCell = r.main_depository_account_id
                        ? escapeHtml(r.main_depository_account_name || '')
                        : '<span style="color:var(--fims-text-subtle);">— Not assigned —</span>';
                    return '<tr>' +
                        '<td>' + (eligible ? '<input type="checkbox" class="fs-row-check" data-account-id="' + r.bank_account_id + '" checked>' : '') + '</td>' +
                        '<td>' + escapeHtml(r.account_name) + (r.bank_name ? ' <span style="color:var(--fims-text-subtle);font-size:11px;">(' + escapeHtml(r.bank_name) + ')</span>' : '') + '</td>' +
                        '<td class="text-right">' + balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                        '<td>' + mainCell + '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('.fs-row-check').forEach(function (cb) {
                    cb.addEventListener('change', fsUpdateProcessBtn);
                });
                selectAll.checked = tbody.querySelectorAll('.fs-row-check').length > 0;
                fsUpdateProcessBtn();
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="4">Could not reach the server.</td></tr>'; });
    }

    document.getElementById('fsProcessBtn').addEventListener('click', function () {
        const btn = this;
        const rcId = document.getElementById('fsRegionSelect').value;
        const accountIds = Array.from(document.querySelectorAll('#fsBody .fs-row-check:checked'))
            .map(cb => parseInt(cb.dataset.accountId));
        if (!accountIds.length) { toast('Select at least one account to sweep.', true); return; }
        btn.disabled = true;
        document.getElementById('fsHint').textContent = 'Processing…';
        fetch(BANK_MANAGEMENT_API + '/fund-sweep/process', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ rc_id: rcId, account_ids: accountIds }) })
            .then(r => r.json())
            .then(function (json) {
                document.getElementById('fsHint').textContent = '';
                if (!json || !json.success) { toast((json && json.message) || 'Fund sweep failed.', true); return; }
                const created = json.data.created || [];
                const skipped = json.data.skipped || [];
                const resultsEl = document.getElementById('fsResults');
                resultsEl.style.display = '';
                let html = '';
                if (created.length) {
                    html += '<div style="color:#16a34a;font-weight:600;margin-bottom:4px;">Created ' + created.length + ' draft FTLA(s):</div>' +
                        '<ul style="margin:0 0 8px 18px;padding:0;">' + created.map(c => '<li>' + escapeHtml(c.account_name) + ' — ' + escapeHtml(c.ftla_number || '') + '</li>').join('') + '</ul>';
                }
                if (skipped.length) {
                    html += '<div style="color:var(--fims-text-subtle);font-weight:600;margin-bottom:4px;">Skipped ' + skipped.length + ':</div>' +
                        '<ul style="margin:0;padding:0 0 0 18px;color:var(--fims-text-subtle);">' + skipped.map(s => '<li>' + escapeHtml(s.account_name) + (s.reason ? ' — ' + escapeHtml(s.reason) : ' — no balance or no Main Depository assigned') + '</li>').join('') + '</ul>';
                }
                resultsEl.innerHTML = html || 'Nothing to sweep.';
                toast(created.length ? 'Fund sweep prepared ' + created.length + ' draft FTLA(s).' : 'Nothing was eligible to sweep.');
                loadFundSweepPreview();
            })
            .catch(() => { document.getElementById('fsHint').textContent = ''; toast('Could not reach the server.', true); btn.disabled = false; });
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
            return '<tr>' +
                '<td>' + escapeHtml(l.account_code || '') + (l.account_title ? ' — ' + escapeHtml(l.account_title) : '') + '</td>' +
                '<td class="text-right">' + (l.debit > 0 ? fmtPeso(l.debit) : '') + '</td>' +
                '<td class="text-right">' + (l.credit > 0 ? fmtPeso(l.credit) : '') + '</td>' +
                '</tr>';
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
                loadList();
            })
            .catch(function () { toast('Could not reach the server.', true); btn.disabled = false; });
    });

    loadLookups().then(loadList);
})();
</script>
