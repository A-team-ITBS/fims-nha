<?php
/**
 * Check Disbursement Voucher — Check Processing module.
 *
 * Consolidates the 6 check-processing workflow pages from the TREASURY
 * reference project (_assets/TREASURY/app/pages/) into one tabbed page:
 *   1. Check Preparation           — issue checks against approved DVs
 *   2. Check Registry              — blank check stock (booklets) inventory
 *   3. Released to Claimants       — direct/email release to payees
 *   4. Released to Regions/Depts   — release to originating offices
 *   5. Cancellation & Replacements — cancel/replace issued checks
 *   6. Inventory (RAF)             — Report of Accountable Forms
 *
 * NOT ported: check_disbursement_ftla_dashboard.php — it's a combined
 * check+FTLA reporting dashboard, not a check-processing workflow page, and
 * half its widgets depend on the separate FTLA/fund-transfer module.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-tabs-underline, fims-modal-overlay, etc.) match the real classes
 * defined in app/fims-css/fims.css (merged in from the TREASURY reference
 * project's own fims-treasury.css), verified against actual usage in its
 * source pages rather than guessed.
 *
 * STATUS: UI shell + schema, backend still stubbed. The tables this module
 * needs now exist in the `fims` schema (_assets/sql/01 - fims/01 - table/26
 * through 33 — bank_accounts, check_booklets, checks, check_cancellations,
 * check_claimant_releases, check_claimant_email_log, check_region_releases,
 * check_region_email_log; FKs in "03 - alter_table/21 - ...-add_fk.sql"),
 * but app/services/CheckProcessingService.php's methods haven't been
 * rewritten to query them yet (see that file's header comment) — every
 * backend call here still goes through app/routes/check-processing.php to
 * those stub methods, which return empty-but-valid responses regardless of
 * what's in the database. Tables render correctly with "no records" until
 * each stub method is replaced with a real query against these tables. The
 * `accounting`/`shared`/`workflow`/`fmd` schemas some source pages also
 * joined against in the original TREASURY project are still out of scope.
 */

namespace PHPMaker2024\FIMS;

$cpCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
$cpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-js/fims-treasury.js');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $cpCssVer ?>" />
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" defer></script>
<script src="/app/fims-js/fims-treasury.js?v=<?= $cpJsVer ?>" defer></script>

<div class="fims-wrap" id="cpModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-money-check-alt"></i></div>
            <h1>Check Disbursement Voucher</h1>
            <p>Check preparation, registry, release, and cancellation — Disbursing Section</p>
        </div>
    </div>

    <div class="fims-tabs-underline mb-3" id="cpTabNav">
        <button class="fims-tab-underline active" data-cp-tab="prep">Check Preparation</button>
        <button class="fims-tab-underline" data-cp-tab="registry">Check Registry</button>
        <button class="fims-tab-underline" data-cp-tab="released">Released</button>
        <button class="fims-tab-underline" data-cp-tab="cancel">Cancellation &amp; Replacements</button>
        <button class="fims-tab-underline" data-cp-tab="raf">Inventory (RAF)</button>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 1: CHECK PREPARATION
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content active" id="cpPrep">
        <div class="fims-alert fims-alert-warning" id="print-agent-warning" style="display:none;margin-bottom:10px;">
            <div class="fims-alert-body">Print agent not detected at <code>http://127.0.0.1:9123</code>. Checks can't be printed until it's running.
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="prepInstallAgentBtn" style="margin-left:8px;"><i class="fas fa-download mr-1"></i>Download &amp; Install Print Agent</button>
            </div>
        </div>
        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <input type="text" class="fims-input fims-input-sm" id="prepFilterCheckNo" placeholder="Check Number" style="width:140px">
                    <input type="text" class="fims-input fims-input-sm" id="prepFilterDvNo" placeholder="DV Number" style="width:140px">
                    <input type="text" class="fims-input fims-input-sm" id="prepFilterPayee" placeholder="Payee" style="width:160px">
                    <select class="fims-select fims-input-sm" id="prepFilterBank" style="width:180px"><option value="">All Banks</option></select>
                    <input type="date" class="fims-input fims-input-sm" id="prepFilterDateFrom">
                    <span style="font-size:11px;color:var(--fims-text-subtle)">–</span>
                    <input type="date" class="fims-input fims-input-sm" id="prepFilterDateTo">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="prepFilterBtn"><i class="fas fa-filter mr-1"></i>Filter</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="prepResetBtn">Reset</button>
                </div>
            </div>
        </div>

        <div class="cp-status-tabs" id="prepStatusTabs">
            <?php foreach (["" => "All", "PREPARED" => "Prepared", "RELEASED" => "Released", "ENCASHED" => "Encashed", "OUTSTANDING" => "Outstanding", "STALE" => "Stale", "CANCELLED" => "Cancelled", "VOIDED" => "Voided"] as $val => $label): ?>
            <button type="button" class="fims-badge cp-status-tab<?= $val === "" ? " active" : "" ?>" data-status="<?= $val ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>

        <div class="fims-table-panel" style="border-radius:0">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Checks</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="prepBatchPrintBtn"><i class="fas fa-print mr-1"></i>Batch Print</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="prepSubsidyReportBtn"><i class="fas fa-file-invoice-dollar mr-1"></i>Subsidy Report</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="prepPrepareBtn"><i class="fas fa-plus mr-1"></i>Prepare Check</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="prepChecksTable">
                    <thead>
                        <tr>
                            <th style="width:32px"><input type="checkbox" id="prepSelectAll"></th>
                            <th style="width:36px">#</th>
                            <th>Check No.</th>
                            <th>Check Date</th>
                            <th>DV Number</th>
                            <th>Payee</th>
                            <th>Bank / Account</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:260px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="10">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 2: CHECK REGISTRY
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpRegistry" style="display:none">
        <div class="fims-stat-grid" id="registryKpiCards" style="grid-template-columns:repeat(3,1fr)">
            <div class="fims-stat-card fims-card-blue">
                <div class="fims-stat-top"><span class="fims-stat-label">Unused / Available</span></div>
                <div class="fims-stat-value" data-kpi="UNUSED">0</div>
            </div>
            <div class="fims-stat-card fims-card-green">
                <div class="fims-stat-top"><span class="fims-stat-label">Issued</span></div>
                <div class="fims-stat-value" data-kpi="ISSUED">0</div>
            </div>
            <div class="fims-stat-card fims-card-red">
                <div class="fims-stat-top"><span class="fims-stat-label">Cancelled</span></div>
                <div class="fims-stat-value" data-kpi="CANCELLED">0</div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="regFilterOfficeType" style="width:150px">
                        <option value="">Office Type: All</option>
                        <option value="MAIN_OFFICE">Main Office</option>
                        <option value="REGION">Region</option>
                    </select>
                    <select class="fims-select fims-input-sm" id="regFilterBank" style="width:170px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="regFilterSeries" style="width:150px"><option value="">All Series</option></select>
                    <select class="fims-select fims-input-sm" id="regFilterStatus" style="width:150px">
                        <option value="">Status: All</option>
                        <option value="UNUSED">Unused</option>
                        <option value="ISSUED">Issued</option>
                        <option value="ENCASHED">Encashed</option>
                        <option value="OUTSTANDING">Outstanding</option>
                        <option value="CANCELLED">Cancelled</option>
                        <option value="VOIDED">Voided</option>
                        <option value="SPOILED">Spoiled</option>
                        <option value="STALE">Stale</option>
                    </select>
                    <select class="fims-select fims-input-sm" id="regFilterEntryType" style="width:150px">
                        <option value="">Entry Type: All</option>
                        <option value="RECEIVED">Received</option>
                        <option value="ISSUED">Issued</option>
                        <option value="CANCELLED">Cancelled</option>
                        <option value="VOIDED">Voided</option>
                        <option value="SPOILED">Spoiled</option>
                        <option value="STALE">Stale</option>
                        <option value="REPLACED">Replaced</option>
                    </select>
                    <input type="date" class="fims-input fims-input-sm" id="regFilterDateFrom">
                    <input type="date" class="fims-input fims-input-sm" id="regFilterDateTo">
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="regFilterSearch" placeholder="Check # or Series or RAF" style="width:200px">
                    </div>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regSearchBtn"><i class="fas fa-search mr-1"></i>Search</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="regClearBtn">Clear</button>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Check Register</div>
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regAddBatchBtn"><i class="fas fa-plus mr-1"></i>Add Batch</button>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="registryTable">
                    <thead>
                        <tr>
                            <th>#</th><th>Booklet No.</th><th>Series</th><th>Bank / Account</th>
                            <th>Dept. Type</th><th class="text-center">Checks</th><th>Status Summary</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="7">No records.</td></tr></tbody>
                </table>
            </div>
            <div class="fims-pagination" id="registryPagination"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 3: RELEASED TO CLAIMANTS
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpReleased" style="display:none">
        <div class="fims-stat-grid" id="releasedKpiCards" style="grid-template-columns:repeat(6,1fr)">
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Pending</span></div><div class="fims-stat-value" data-kpi="PENDING">0</div></div>
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Notified</span></div><div class="fims-stat-value" data-kpi="NOTIFIED">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Released</span></div><div class="fims-stat-value" data-kpi="RELEASED">0</div></div>
            <div class="fims-stat-card fims-card-indigo"><div class="fims-stat-top"><span class="fims-stat-label">Acknowledged</span></div><div class="fims-stat-value" data-kpi="ACKNOWLEDGED">0</div></div>
            <div class="fims-stat-card fims-card-red"><div class="fims-stat-top"><span class="fims-stat-label">Unclaimed</span></div><div class="fims-stat-value" data-kpi="UNCLAIMED">0</div></div>
            <div class="fims-stat-card fims-card-violet"><div class="fims-stat-top"><span class="fims-stat-label">Return Paid DVs</span></div><div class="fims-stat-value" data-kpi="DV_RETURNED">0</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="relFilterSearch" placeholder="Check No. or Payee" style="width:190px">
                    </div>
                    <select class="fims-select fims-input-sm" id="relFilterStatus" style="width:160px">
                        <option value="">Status: All</option>
                        <option value="PENDING">Pending</option>
                        <option value="NOTIFIED">Notified</option>
                        <option value="RELEASED">Released</option>
                        <option value="ACKNOWLEDGED">Acknowledged</option>
                        <option value="UNCLAIMED">Unclaimed</option>
                    </select>
                    <select class="fims-select fims-input-sm" id="relFilterType" style="width:170px">
                        <option value="">Type: All</option>
                        <option value="DIRECT">Direct</option>
                        <option value="EMAIL_NOTICE">Email Notice</option>
                        <option value="REGION">Region/Dept</option>
                    </select>
                    <select class="fims-select fims-input-sm" id="relFilterDept" style="width:190px"><option value="">All Regions/Depts</option></select>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="relRecordReleaseBtn"><i class="fas fa-plus mr-1"></i>Record Release</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="relSendNoticeBtn"><i class="fas fa-envelope mr-1"></i>Send Notice</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="relFollowUpBtn"><i class="fas fa-bell mr-1"></i>Follow-up Unclaimed</button>
                </div>
            </div>
        </div>

        <div class="fims-tabs-underline mb-0" id="relSubTabs" style="border-radius:0;border-top:none;">
            <button class="fims-tab-underline active" data-sub-target="relListPane">Check Release List</button>
            <button class="fims-tab-underline" data-sub-target="relUnclaimedPane">Unclaimed / Unreleased</button>
            <button class="fims-tab-underline" data-sub-target="relAckPane">Acknowledgment</button>
            <button class="fims-tab-underline" data-sub-target="relEmailLogPane">Email Log</button>
            <button class="fims-tab-underline" data-sub-target="relDvPane">Return Paid DVs</button>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-tab-underline-content active" id="relListPane">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="releasesTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>Payee / Amount</th><th>Type</th><th>Claimant / Region</th>
                                <th>ID / RDTF No.</th><th>Release Date</th>
                                <th class="text-center">Status</th><th>Acknowledgment</th><th class="text-center" style="width:120px"></th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="10">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="relUnclaimedPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="relUnclaimedTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>Payee / Amount</th><th>Type</th><th>Claimant / Region</th>
                                <th class="text-center">Status</th><th>Days Since Prep.</th><th>Last Follow-up</th>
                                <th>Follow-up Remarks</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="9">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="relAckPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="relAckTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>Payee</th><th class="text-right">Amount</th><th>Type</th><th>Claimant / Region</th>
                                <th>Release Date</th><th>Acknowledgment Date</th><th>ID / Submitted</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="9">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="relEmailLogPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="relEmailLogTable">
                        <thead>
                            <tr><th>#</th><th>Check No. / Region</th><th>Recipient Email</th><th>Checks</th><th class="text-right">Total Amount</th><th>Sent At</th><th>Sent By</th><th class="text-center">Status</th></tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="8">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="relDvPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="relDvTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>DV Reference</th><th>Payee</th><th class="text-right">Amount</th>
                                <th>Region/Dept</th><th>Release Date</th><th>DV Return Date</th><th>Days Pending</th><th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="10">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 5: CANCELLATION & REPLACEMENTS
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpCancel" style="display:none">
        <div class="fims-stat-grid" id="cancelKpiCards" style="grid-template-columns:repeat(4,1fr)">
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Total Cancelled</span></div><div class="fims-stat-value" data-kpi="total_cancelled">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">With Replacement</span></div><div class="fims-stat-value" data-kpi="with_replacement">0</div></div>
            <div class="fims-stat-card fims-card-amber"><div class="fims-stat-top"><span class="fims-stat-label">Pending Replacement</span></div><div class="fims-stat-value" data-kpi="pending_replacement">0</div></div>
            <div class="fims-stat-card fims-card-red"><div class="fims-stat-top"><span class="fims-stat-label">Cancelled Amount</span></div><div class="fims-stat-value" data-kpi="cancelled_amount">₱0.00</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="cancelFilterSearch" placeholder="Check no., payee…" style="width:186px">
                    </div>
                    <select class="fims-select fims-input-sm" id="cancelFilterBank" style="width:200px"><option value="">All Banks</option></select>
                    <input type="date" class="fims-input fims-input-sm" id="cancelFilterDateFrom">
                    <input type="date" class="fims-input fims-input-sm" id="cancelFilterDateTo">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="cancelSearchBtn"><i class="fas fa-search mr-1"></i>Search</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="cancelClearBtn" style="display:none">Clear</button>
                </div>
                <div id="cancelListMeta" style="font-size:11px;color:var(--fims-text-subtle)">No records.</div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Cancelled Checks</div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="cancelTable">
                    <thead>
                        <tr>
                            <th>#</th><th>Check No.</th><th>Bank / Account</th><th>Payee</th><th class="text-right">Amount</th>
                            <th>Check Date</th><th>Cancelled Date</th><th>Reason</th><th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="9">No records.</td></tr></tbody>
                </table>
            </div>
            <div class="fims-pagination" id="cancelPagination"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 6: INVENTORY (RAF)
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpRaf" style="display:none">
        <div class="fims-stat-grid" id="rafKpiCards" style="grid-template-columns:repeat(4,1fr)">
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Check Booklets</span></div><div class="fims-stat-value" data-kpi="booklets">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Remaining Checks</span></div><div class="fims-stat-value" data-kpi="remaining">0</div></div>
            <div class="fims-stat-card fims-card-indigo"><div class="fims-stat-top"><span class="fims-stat-label">Checks Issued</span></div><div class="fims-stat-value" data-kpi="issued">0</div></div>
            <div class="fims-stat-card fims-card-red"><div class="fims-stat-top"><span class="fims-stat-label">Low Stock Accounts</span></div><div class="fims-stat-value" data-kpi="low_stock">0</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="rafFilterYear" style="width:140px"><option value="">Fiscal Year: All</option></select>
                    <select class="fims-select fims-input-sm" id="rafFilterBank" style="width:170px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="rafFilterLowStock" style="width:140px">
                        <option value="">All</option>
                        <option value="1">Low Stock Only</option>
                    </select>
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="rafFilterSearch" placeholder="Check Series / Booklet No." style="width:200px">
                    </div>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Inventory of Checks for Issuance</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="rafPreviewBtn"><i class="fas fa-eye mr-1"></i>Preview</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="rafPrintBtn"><i class="fas fa-print mr-1"></i>Print</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="rafDownloadBtn"><i class="fas fa-file-pdf mr-1"></i>Download PDF</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="rafTable">
                    <thead>
                        <tr>
                            <th>Booklet No.</th><th>Series</th><th>Fiscal Year</th><th>Check Range</th>
                            <th>Received</th><th>Issued</th><th>Cancelled</th><th>Remaining</th>
                            <th>Last Entry</th><th class="text-center">Stock Status</th><th class="text-center" style="width:70px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="11">No records — check register has no booklets yet.</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Prepare Check (tab 1 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-prepare-check" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:760px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Prepare Check</div></div>
                    <button class="fims-modal-close" data-close="m-prepare-check">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-alert fims-alert-info">
                        <div class="fims-alert-body">Only Disbursement Vouchers Treasury is currently holding (status <code>FOR_TREASURY</code>) are listed below. Preparing a check here releases the DV.</div>
                    </div>
                    <div id="prepModalError" class="fims-alert fims-alert-danger" style="display:none;"><div class="fims-alert-body"></div></div>
                    <div id="prepCashCheck" class="fims-alert" style="display:none;"><div class="fims-alert-body"></div></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">DV Number <span class="text-danger">*</span></label>
                            <select class="fims-select" id="prepDvSelect"><option value="">Select a DV...</option></select>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Program</label><input type="text" class="fims-input" id="prepProgram" disabled placeholder="—"></div>
                        <div class="col-md-6"><label class="fims-label">Payee</label><input type="text" class="fims-input" id="prepPayee" disabled></div>
                        <div class="col-md-6"><label class="fims-label">Bank Account <span class="text-danger">*</span></label>
                            <select class="fims-select" id="prepBankAccount"><option value="">Select bank account...</option></select>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Check Date <span class="text-danger">*</span></label><input type="date" class="fims-input" id="prepCheckDate"></div>
                        <div class="col-md-6"><label class="fims-label">Check Number <span class="text-danger">*</span></label>
                            <input type="text" class="fims-input" id="prepCheckNumber" placeholder="Select bank account first..." disabled>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Net Amount</label><input type="text" class="fims-input" id="prepNetAmount" disabled></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="fims-label">Signatory 1 Name</label><input type="text" class="fims-input" id="prepSig1Name" placeholder="e.g. JUAN D. DELA CRUZ"></div>
                        <div class="col-md-6"><label class="fims-label">Signatory 1 Designation</label><input type="text" class="fims-input" id="prepSig1Desig" placeholder="e.g. Manager"></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6"><label class="fims-label">Signatory 2 Name</label><input type="text" class="fims-input" id="prepSig2Name" placeholder="e.g. MARIA S. SANTOS"></div>
                        <div class="col-md-6"><label class="fims-label">Signatory 2 Designation</label><input type="text" class="fims-input" id="prepSig2Desig" placeholder="e.g. Department Manager"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-prepare-check">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="prepSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Set Check Status — manual override of fims.checks.status
         directly from Check Preparation. The Released/Cancellation tabs
         keep this in sync automatically for the normal release/cancel
         flows (see CheckProcessingService::syncCheckStatusFromRelease() /
         cancelCheck()); this is for everything else (ENCASHED/OUTSTANDING/
         VOIDED, or a manual correction) that nothing else in this module
         sets on its own.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-set-check-status" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:420px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Set Check Status</div><p class="fims-modal-subtitle" id="setStatusSubtitle" style="margin:0;font-size:12px;color:var(--fims-text-subtle);"></p></div>
                    <button class="fims-modal-close" data-close="m-set-check-status">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <label class="fims-label">Status <span class="text-danger">*</span></label>
                    <select class="fims-select" id="setStatusSelect">
                        <option value="PREPARED">Prepared</option>
                        <option value="RELEASED">Released</option>
                        <option value="ENCASHED">Encashed</option>
                        <option value="OUTSTANDING">Outstanding</option>
                        <option value="STALE">Stale</option>
                        <option value="CANCELLED">Cancelled</option>
                        <option value="VOIDED">Voided</option>
                    </select>
                    <div id="setStatusReasonGroup" style="display:none;margin-top:10px;">
                        <label class="fims-label">Cancellation Reason <span class="text-danger">*</span></label>
                        <select class="fims-select" id="setStatusReason">
                            <option value="LOST">Lost</option>
                            <option value="DAMAGED">Damaged</option>
                            <option value="ERROR">Error in Check</option>
                            <option value="PAYEE_REQUEST">Payee Request</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div id="setStatusClaimantGroup" style="display:none;margin-top:10px;">
                        <label class="fims-label">Claimant Type</label>
                        <select class="fims-select" id="setStatusClaimantType" style="margin-bottom:8px;">
                            <option value="PAYEE">Payee</option>
                            <option value="AUTHORIZED_REP">Authorized Representative</option>
                            <option value="ORIGINATING_UNIT">Originating Unit</option>
                        </select>
                        <label class="fims-label">Quick Pick</label>
                        <select class="fims-select" id="setStatusClaimantQuickPick" style="margin-bottom:8px;"><option value="">Type manually…</option></select>
                        <label class="fims-label">Claimant Name</label>
                        <input type="text" class="fims-input" id="setStatusClaimantName">
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-set-check-status">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="setStatusSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Set Claimant — edits a release record's claimant name/type
         after the fact. Mainly for releases created by Check Preparation's
         "Set Status" shortcut, which has no claimant field of its own and
         always inserts with claimant_name NULL.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-set-claimant" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:420px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Set Claimant</div><p class="fims-modal-subtitle" id="setClaimantSubtitle" style="margin:0;font-size:12px;color:var(--fims-text-subtle);"></p></div>
                    <button class="fims-modal-close" data-close="m-set-claimant">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-12" id="setClaimantTypeGroup"><label class="fims-label">Claimant Type</label>
                            <select class="fims-select" id="setClaimantType">
                                <option value="PAYEE">Payee</option>
                                <option value="AUTHORIZED_REP">Authorized Representative</option>
                                <option value="ORIGINATING_UNIT">Originating Unit</option>
                            </select>
                        </div>
                        <div class="col-md-12">
                            <label class="fims-label">Quick Pick</label>
                            <select class="fims-select" id="setClaimantQuickPick"><option value="">Type manually…</option></select>
                        </div>
                        <div class="col-md-12"><label class="fims-label" id="setClaimantNameLabel">Claimant Name</label><input type="text" class="fims-input" id="setClaimantName"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-set-claimant">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="setClaimantSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Acknowledge Release — captures the ID presented at pickup
         (type + number) and flips the release to ACKNOWLEDGED. Previously
         had no UI entry point at all despite the backend already
         supporting it.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-acknowledge-release" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:420px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Acknowledge Release</div><p class="fims-modal-subtitle" id="ackSubtitle" style="margin:0;font-size:12px;color:var(--fims-text-subtle);"></p></div>
                    <button class="fims-modal-close" data-close="m-acknowledge-release">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-12"><label class="fims-label">ID Type</label>
                            <select class="fims-select" id="ackIdType">
                                <option value="">Select...</option>
                                <option value="DRIVERS_LICENSE">Driver's License</option>
                                <option value="PASSPORT">Passport</option>
                                <option value="UMID">UMID</option>
                                <option value="SSS_ID">SSS ID</option>
                                <option value="PHILHEALTH_ID">PhilHealth ID</option>
                                <option value="VOTERS_ID">Voter's ID</option>
                                <option value="POSTAL_ID">Postal ID</option>
                                <option value="COMPANY_ID">Company ID</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-md-12"><label class="fims-label">ID Number</label><input type="text" class="fims-input" id="ackIdNumber"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-acknowledge-release">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="ackSaveBtn">Acknowledge</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Check Preview — ported from TREASURY's modal-check-preview.
         Renders through the same client-side bank template
         (buildCheckHtml()/.nha-chk) the print agent receives, into an
         always-present inline panel (#check-preview-content) — not an
         iframe. TREASURY also defines an iframe/server-rendered path for a
         GET /check-preview/{id} HTML route, but it has zero live callers in
         its actual UI (confirmed by reading every call site), so it is not
         ported here — this inline path is the one TREASURY's View/Print
         table actions and its own Prepare Check "Preview" button actually use.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-check-preview" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:900px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div>
                        <div class="fims-modal-title">Check Disbursement Voucher</div>
                        <p class="fims-modal-subtitle" id="previewSubtitle" style="margin:0;font-size:12px;color:var(--fims-text-subtle);">Preview</p>
                    </div>
                    <button class="fims-modal-close" data-close="m-check-preview">&times;</button>
                </div>
                <div class="fims-modal-body" style="padding:20px;overflow:auto;">
                    <div id="check-preview-content"></div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-check-preview">Close</button>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;margin-left:12px;">
                        <input type="checkbox" id="chk-pchc-preview">
                        PCHC preview (print mode)
                    </label>
                    <label style="display:flex;align-items:center;gap:6px;font-size:12px;cursor:pointer;margin-right:auto;margin-left:14px;">
                        <input type="checkbox" id="chk-show-dv">
                        Show DV No.
                    </label>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="btn-do-print"><i class="fas fa-print mr-1"></i>Print</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Print Options — talks directly to the local print agent
         (http://127.0.0.1:9123), ported from TREASURY's check-preparation.php.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-print-options" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:480px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Print Check</div></div>
                    <button class="fims-modal-close" data-close="m-print-options">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-alert fims-alert-info" id="pqStatus" style="display:flex;"><div class="fims-alert-body"><span>Connecting to print agent…</span></div></div>
                    <div id="pqForm" style="display:none;">
                        <div class="fims-form-group">
                            <label class="fims-label">Printer</label>
                            <select class="fims-select" id="pqPrinter"></select>
                            <span style="font-size:11px;color:var(--fims-text-subtle)" id="pqPrinterStatus"></span>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6"><label class="fims-label">Copies</label><input type="number" class="fims-input" id="pqCopies" value="1" min="1"></div>
                            <div class="col-md-6"><label class="fims-label">Color Mode</label>
                                <select class="fims-select" id="pqColorMode">
                                    <option value="color">Color</option>
                                    <option value="grayscale">Grayscale</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-print-options">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="btnConfirmPrint" disabled>Print</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Batch Print — row selection happens via the Checks table's
         checkboxes; this modal only picks the template + confirms.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-batch-print" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:640px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Batch Print</div></div>
                    <button class="fims-modal-close" data-close="m-batch-print">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-table-wrap" style="max-height:260px;overflow-y:auto;">
                        <table class="fims-table">
                            <thead><tr><th>Check No.</th><th>Payee</th><th class="text-right">Amount</th><th class="text-center">Status</th></tr></thead>
                            <tbody id="batchPrintReviewBody"></tbody>
                        </table>
                    </div>
                    <div class="fims-alert fims-alert-info" style="margin-top:10px;"><div class="fims-alert-body">Total: <strong id="batchPrintTotal">₱0.00</strong> — <span id="batchPrintCount">0</span> check(s) selected</div></div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-batch-print">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="btnConfirmBatchPrint">Confirm &amp; Print</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Add Check Batch (tab 2 primary action) — ported from
         TREASURY's check-registry.php Range/Individual tabs.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-add-batch" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:680px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Register Check Batch</div></div>
                    <button class="fims-modal-close" data-close="m-add-batch">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Bank Account <span class="text-danger">*</span></label><select class="fims-select" id="batchBankAccount"><option value="">Select bank account...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Batch Series <span class="text-danger">*</span></label><input type="text" class="fims-input" id="batchSeries" placeholder="e.g. CHK-A-2026"></div>
                        <div class="col-md-12"><label class="fims-label">Department Type <span class="text-danger">*</span></label>
                            <select class="fims-select" id="batchDeptType">
                                <option value="MAIN_OFFICE">Main Office</option>
                                <option value="REGION">Region</option>
                                <option value="BRANCH">Branch</option>
                                <option value="PROJECT">Project</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="fims-tabs-underline mt-2 mb-2" id="batchTabNav">
                        <button type="button" class="fims-tab-underline active" data-batch-tab="range">Range</button>
                        <button type="button" class="fims-tab-underline" data-batch-tab="individual">Individual Checks</button>
                    </div>

                    <div id="batchRangePanel">
                        <div class="row g-2">
                            <div class="col-md-5"><label class="fims-label">Start Check No.</label><input type="text" class="fims-input" id="batchRangeStart" placeholder="e.g. 0010440"></div>
                            <div class="col-md-5"><label class="fims-label">End Check No.</label><input type="text" class="fims-input" id="batchRangeEnd" placeholder="e.g. 0010490"></div>
                            <div class="col-md-2"><label class="fims-label">Date</label><input type="date" class="fims-input" id="batchRangeDate"></div>
                        </div>
                        <div id="batchRangePreview" class="text-muted small mt-2"></div>
                    </div>

                    <div id="batchIndividualPanel" style="display:none">
                        <table class="fims-table">
                            <thead><tr><th>Check No.</th><th style="width:150px">Entry Date</th><th style="width:40px"></th></tr></thead>
                            <tbody id="batchIndividualRows"></tbody>
                        </table>
                        <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="batchAddRowBtn"><i class="fas fa-plus mr-1"></i>Add Row</button>
                        <div id="batchIndividualPreview" class="text-muted small mt-2"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-add-batch">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="batchSaveBtn">Save Batch</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Update Register Status — ported from TREASURY's
         check-registry.php #modal-status-overlay.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-update-status" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:480px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Update Status</div></div>
                    <button class="fims-modal-close" data-close="m-update-status">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="statusEntryId">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Check No.</label><input type="text" class="fims-input" id="statusCheckNumber" readonly></div>
                        <div class="col-md-6"><label class="fims-label">Current Status</label><input type="text" class="fims-input" id="statusCurrent" readonly></div>
                        <div class="col-md-12"><label class="fims-label">New Status <span class="text-danger">*</span></label>
                            <select class="fims-select" id="statusNew">
                                <option value="">— Select —</option>
                                <option value="ISSUED">Issued</option>
                                <option value="ENCASHED">Encashed</option>
                                <option value="OUTSTANDING">Outstanding</option>
                                <option value="CANCELLED">Cancelled</option>
                                <option value="VOIDED">Voided</option>
                                <option value="SPOILED">Spoiled</option>
                                <option value="STALE">Stale</option>
                            </select>
                        </div>
                        <div class="col-md-12"><label class="fims-label" id="statusDateLabel">Date</label><input type="date" class="fims-input" id="statusDate"></div>
                        <div class="col-md-12" id="statusReasonGroup" style="display:none"><label class="fims-label">Reason</label><textarea class="fims-input" id="statusReason" rows="2"></textarea></div>
                        <div class="col-md-12" id="statusReplacementGroup" style="display:none"><label class="fims-label">Replacement Check No.</label><input type="text" class="fims-input" id="statusReplacement"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-update-status">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="statusSaveBtn">Save Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Record Release (tab 3 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-record-release" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:720px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Record Check Release</div></div>
                    <button class="fims-modal-close" data-close="m-record-release">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Check Number <span class="text-danger">*</span></label><select class="fims-select" id="releaseCheckNumber"><option value="">Select check...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Amount</label><input type="text" class="fims-input" id="releaseAmount" readonly></div>
                        <div class="col-md-6"><label class="fims-label">Release Type <span class="text-danger">*</span></label>
                            <select class="fims-select" id="releaseType">
                                <option value="DIRECT">Direct (to claimant)</option>
                                <option value="EMAIL_NOTICE">Email Notice (to claimant)</option>
                                <option value="REGION">Region / Department</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="releaseClaimantTypeGroup"><label class="fims-label">Claimant Type</label>
                            <select class="fims-select" id="releaseClaimantType">
                                <option value="PAYEE">Payee</option>
                                <option value="AUTHORIZED_REP">Authorized Representative</option>
                                <option value="ORIGINATING_UNIT">Originating Unit</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="releaseDeptGroup" style="display:none;"><label class="fims-label">Region/Department <span class="text-danger">*</span></label>
                            <select class="fims-select" id="releaseDept"><option value="">Select...</option></select>
                        </div>
                        <div class="col-md-6"><label class="fims-label" id="releaseClaimantNameLabel">Claimant Name</label><input type="text" class="fims-input" id="releaseClaimantName"></div>
                        <div class="col-md-6" id="releaseEmailGroup" style="display:none;"><label class="fims-label">Recipient Email <span class="text-danger">*</span></label><input type="email" class="fims-input" id="releaseClaimantEmail" placeholder="claimant@example.com"></div>
                        <div class="col-md-6" id="releaseRdtfGroup" style="display:none;"><label class="fims-label">RDTF No.</label><input type="text" class="fims-input" id="releaseRdtf"></div>
                        <div class="col-md-6"><label class="fims-label">Release Date</label><input type="date" class="fims-input" id="releaseDate"></div>
                        <div class="col-md-12"><label class="fims-label">Remarks</label><textarea class="fims-input" id="releaseRemarks" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-record-release">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="releaseSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Send Notice (unified — Released tab's primary notice action).
         Scope toggle swaps between a single pending Email-Notice release
         (claimant case) and a bundled monthly notice to a region/department
         (region case) — replaces the two separate modals that existed when
         Tabs 3/4 were separate.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-send-notice" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:720px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Send Notice</div></div>
                    <button class="fims-modal-close" data-close="m-send-notice">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div id="noticeError" class="fims-alert fims-alert-danger" style="display:none;"><div class="fims-alert-body"></div></div>
                    <div class="row g-2">
                        <div class="col-md-12"><label class="fims-label">Notice Scope <span class="text-danger">*</span></label>
                            <select class="fims-select" id="noticeScope">
                                <option value="SINGLE">Single Release (Email Notice)</option>
                                <option value="BULK">Region/Department Bundle (Monthly)</option>
                            </select>
                        </div>
                        <div class="col-md-12" id="noticeSingleGroup">
                            <label class="fims-label">Pending Notice <span class="text-danger">*</span></label>
                            <select class="fims-select" id="noticeSingleRelease"><option value="">Select a pending notice...</option></select>
                        </div>
                        <div class="col-md-6" id="noticeBulkDeptGroup" style="display:none;"><label class="fims-label">Region/Department <span class="text-danger">*</span></label>
                            <select class="fims-select" id="noticeBulkDept"><option value="">Select...</option></select>
                        </div>
                        <div class="col-md-6" id="noticeBulkPeriodGroup" style="display:none;"><label class="fims-label">Period</label><input type="text" class="fims-input" id="noticeBulkPeriod" placeholder="e.g. November 2026"></div>
                        <div class="col-md-6"><label class="fims-label">Recipient Email <span class="text-danger">*</span></label><input type="email" class="fims-input" id="noticeRecipient"></div>
                        <div class="col-md-12"><label class="fims-label">Additional Message</label><textarea class="fims-input" id="noticeMessage" rows="3" placeholder="Optional note to include in the email"></textarea></div>
                    </div>
                    <div id="noticeBulkPendingWrap" style="display:none;">
                        <div class="fims-label" style="margin-top:8px;">Checks to include (currently pending for this department)</div>
                        <div class="fims-table-wrap" style="max-height:220px;overflow-y:auto;">
                            <table class="fims-table">
                                <thead><tr><th>Check No.</th><th>Date</th><th>Payee</th><th class="text-right">Amount</th></tr></thead>
                                <tbody id="noticeBulkPendingBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-send-notice">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="noticeSendBtn"><i class="fas fa-paper-plane mr-1"></i>Send</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Cancel Check (tab 5 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-cancel-check" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:560px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Cancel Check</div></div>
                    <button class="fims-modal-close" data-close="m-cancel-check">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="mb-2"><label class="fims-label">Check <span class="text-danger">*</span></label><select class="fims-select" id="cancelCheckSelect"><option value="">Select a check to cancel...</option></select></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Reason <span class="text-danger">*</span></label>
                            <select class="fims-select" id="cancelReason">
                                <option value="">Select reason...</option>
                                <option value="LOST">Lost</option>
                                <option value="DAMAGED">Damaged</option>
                                <option value="ERROR">Error in Check</option>
                                <option value="PAYEE_REQUEST">Payee Request</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Cancelled Date <span class="text-danger">*</span></label><input type="date" class="fims-input" id="cancelDate"></div>
                        <div class="col-md-12"><label class="fims-label">Reason Description</label><textarea class="fims-input" id="cancelDescription" rows="2"></textarea></div>
                        <div class="col-md-12">
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                                <input type="checkbox" id="cancelIssueReplacement"> Issue a replacement check immediately
                            </label>
                        </div>
                    </div>
                    <div id="cancelReplacementBox" class="row g-2 mt-1" style="display:none;">
                        <div class="col-md-6"><label class="fims-label">New Check No.</label><input type="text" class="fims-input" id="cancelNewCheckNo"></div>
                        <div class="col-md-6"><label class="fims-label">Check Date</label><input type="date" class="fims-input" id="cancelNewCheckDate"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-cancel-check">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-danger fims-btn-sm" id="cancelProcessBtn">Process Cancellation</button>
                </div>
            </div>
        </div>
    </div>

    <div id="cpToast" style="display:none;position:fixed;bottom:20px;right:20px;z-index:2000;padding:10px 16px;border-radius:8px;color:#fff;font-size:13px;"></div>
</div>

<style>
/* Classes local to this page (not part of the shared fims-treasury.css
   design system) — tab buttons reuse the real .fims-tab-underline look, but
   need no-border/background reset since <button> isn't styled by default
   the way the source project's own buttons already were via browser UA
   reset + Bootstrap. The cp-status-tabs is a small quick-filter row unique
   to Check Preparation (status tabs), not present elsewhere in the design
   system. */
.fims-tab-underline { background: none; border: none; cursor: pointer; }
.cp-status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
.cp-status-tab { cursor: pointer; opacity: .55; border: none; }
.cp-status-tab.active { opacity: 1; }

/* Dark-blue theme: this module's KPI cards reuse the shared .fims-card-*
   classes (which are red/green/amber/violet elsewhere in the app), but here
   they're just visual groupings, not meaningful status colors — so override
   their accent to the brand blue-shade family for consistency with the rest
   of this module (scoped to #cpModule only; other pages keep the rainbow). */
#cpModule .fims-card-blue,
#cpModule .fims-card-green,
#cpModule .fims-card-amber,
#cpModule .fims-card-red,
#cpModule .fims-card-gray,
#cpModule .fims-card-indigo,
#cpModule .fims-card-violet { --fims-card-color: #000E3E; }

/* Buttons: same single dark blue, scoped to this module only (the shared
   .fims-btn-primary/-outline classes stay the lighter brand blue everywhere
   else in the app). .fims-btn-danger is left red on purpose — it marks a
   destructive action (Cancel Check), and that warning color is functional,
   not decorative. */
#cpModule .fims-btn-primary { background:#000E3E; color:#fff; }
#cpModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#cpModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#cpModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }
</style>

<script>
(function () {
    const API = '/api/check-processing';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }

    function toast(msg, isError) {
        const el = document.getElementById('cpToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    function peso(n) {
        n = Number(n) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // ── Modal open/close (real fims-modal-overlay pattern: display:flex/none) ──
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
    document.querySelectorAll('.fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.style.display = 'none';
        });
    });

    // ── Top-level module tabs ──
    const initialized = {};
    const tabPaneIds = { prep: 'cpPrep', registry: 'cpRegistry', released: 'cpReleased', cancel: 'cpCancel', raf: 'cpRaf' };
    document.querySelectorAll('#cpTabNav [data-cp-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#cpTabNav .fims-tab-underline').forEach(b => b.classList.remove('active'));
            Object.values(tabPaneIds).forEach(id => { document.getElementById(id).style.display = 'none'; document.getElementById(id).classList.remove('active'); });
            this.classList.add('active');
            const tab = this.dataset.cpTab;
            const pane = document.getElementById(tabPaneIds[tab]);
            pane.style.display = '';
            pane.classList.add('active');
            if (!initialized[tab]) { initialized[tab] = true; initTab(tab); }
        });
    });

    // ── Sub-tabs (within Claimants / Regions tabs) ──
    document.querySelectorAll('[id$="SubTabs"].fims-tabs-underline').forEach(function (nav) {
        const panesContainer = nav.nextElementSibling; // the .fims-table-panel holding this nav's panes
        nav.querySelectorAll('[data-sub-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                nav.querySelectorAll('.fims-tab-underline').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                panesContainer.querySelectorAll('.fims-tab-underline-content').forEach(p => {
                    p.style.display = 'none';
                    p.classList.remove('active');
                });
                const target = document.getElementById(this.dataset.subTarget);
                if (target) { target.style.display = ''; target.classList.add('active'); }
            });
        });
    });

    function initTab(tab) {
        if (tab === 'prep') initPrep();
        else if (tab === 'registry') initRegistry();
        else if (tab === 'released') initReleased();
        else if (tab === 'cancel') initCancel();
        else if (tab === 'raf') initRaf();
    }

    // Status tabs (Check Preparation) — defaults to "All" rather than
    // TREASURY's PREPARED default, so previously-made checks (anything that
    // has since moved to Released/Cancelled/etc.) stay visible here instead
    // of silently dropping out of view the moment their status changes.
    function updateBatchPrintVisibility(status) {
        const btn = document.getElementById('prepBatchPrintBtn');
        if (btn) btn.style.display = status === 'PREPARED' ? '' : 'none';
    }
    document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateBatchPrintVisibility(this.dataset.status);
            loadPrepChecks();
        });
    });
    updateBatchPrintVisibility('');
    document.getElementById('prepFilterBtn')?.addEventListener('click', loadPrepChecks);
    document.getElementById('prepResetBtn')?.addEventListener('click', function () {
        ['prepFilterCheckNo', 'prepFilterDvNo', 'prepFilterPayee', 'prepFilterBank', 'prepFilterDateFrom', 'prepFilterDateTo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(b => b.classList.toggle('active', b.dataset.status === ''));
        updateBatchPrintVisibility('');
        loadPrepChecks();
    });
    document.getElementById('prepPrepareBtn')?.addEventListener('click', function () {
        openPrepareCheckModal();
    });

    function renderEmptyNotice(tbody, json, colspan) {
        const rows = (json && json.data) ? json.data : [];
        if (!rows.length) {
            tbody.innerHTML = '<tr class="fims-empty"><td colspan="' + colspan + '">No records.</td></tr>';
        } else {
            tbody.innerHTML = '';
        }
    }

    function buildPrepParams() {
        const params = new URLSearchParams();
        params.set('draw', '1');
        const activeStatus = document.querySelector('#prepStatusTabs .cp-status-tab.active')?.dataset.status || '';
        if (activeStatus) params.set('status', activeStatus);
        const map = {
            prepFilterCheckNo: 'check_number', prepFilterDvNo: 'dv_number', prepFilterPayee: 'payee',
            prepFilterBank: 'bank_account_id', prepFilterDateFrom: 'date_from', prepFilterDateTo: 'date_to',
        };
        Object.keys(map).forEach(function (id) {
            const val = document.getElementById(id)?.value?.trim();
            if (val) params.set(map[id], val);
        });
        return params;
    }

    let prepChecksById = {};
    const prepSelectedChecks = new Set();
    function loadPrepChecks() {
        const tbody = document.querySelector('#prepChecksTable tbody');
        fetch(API + '/preparation/checks?' + buildPrepParams().toString(), { headers: authHeaders(), cache: 'no-store' })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                prepChecksById = {};
                rows.forEach(function (r) { prepChecksById[r.check_id] = r; });
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const printedBadge = r.printed_at ? ' <span class="fims-badge fims-badge-blue" title="Printed ' + escapeHtml(r.printed_at) + '"><i class="fas fa-print"></i></span>' : '';
                    return '<tr data-row-check-id="' + r.check_id + '">' +
                        '<td><input type="checkbox" class="prep-row-check" data-check-id="' + r.check_id + '"></td>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.check_number) + printedBadge + '</td>' +
                        '<td>' + escapeHtml(r.check_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.dv_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.payee || '') + '</td>' +
                        '<td>' + escapeHtml(r.account_name || '') + '</td>' +
                        '<td class="text-right">' + peso(r.amount) + '</td>' +
                        '<td class="text-center prep-status-cell"><span class="fims-badge">' + escapeHtml(r.status) + '</span></td>' +
                        '<td class="text-center prep-actions-cell">' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-view-check="' + r.check_id + '" title="View"><i class="fas fa-eye mr-1"></i>View</button> ' +
                            (r.status === 'PREPARED' ? '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-view-check="' + r.check_id + '" title="Print"><i class="fas fa-print mr-1"></i>Print</button> ' : '') +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-set-status="' + r.check_id + '" title="Set Status"><i class="fas fa-flag mr-1"></i>Set Status</button>' +
                        '</td>' +
                        '</tr>';
                }).join('');
                // Both View and Print table actions just open the preview modal
                // (matches TREASURY: _printCheck() calls the same _viewCheck()
                // as the eye icon — the local print agent is only reached from
                // the Print button inside the preview modal itself).
                tbody.querySelectorAll('[data-view-check]').forEach(function (btn) {
                    btn.addEventListener('click', function () { viewCheck(this.dataset.viewCheck); });
                });
                tbody.querySelectorAll('[data-set-status]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openSetStatusModal(this.dataset.setStatus); });
                });
                tbody.querySelectorAll('.prep-row-check').forEach(function (cb) {
                    cb.checked = prepSelectedChecks.has(cb.dataset.checkId);
                    cb.addEventListener('change', function () {
                        if (this.checked) prepSelectedChecks.add(this.dataset.checkId);
                        else prepSelectedChecks.delete(this.dataset.checkId);
                    });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('prepSelectAll')?.addEventListener('change', function () {
        const checked = this.checked;
        document.querySelectorAll('.prep-row-check').forEach(function (cb) {
            cb.checked = checked;
            if (checked) prepSelectedChecks.add(cb.dataset.checkId);
            else prepSelectedChecks.delete(cb.dataset.checkId);
        });
    });

    // ── Set Check Status — manual override; see the modal's own comment. ──
    let setStatusCheckId = null;
    function openSetStatusModal(checkId) {
        const check = prepChecksById[checkId];
        setStatusCheckId = checkId;
        document.getElementById('setStatusSubtitle').textContent = check ? ('Check #' + check.check_number) : '';
        document.getElementById('setStatusSelect').value = check ? check.status : 'PREPARED';
        document.getElementById('setStatusReason').value = 'OTHER';
        document.getElementById('setStatusClaimantType').value = 'PAYEE';
        document.getElementById('setStatusClaimantName').value = '';
        const names = new Set();
        if (check && check.payee) names.add(check.payee);
        if (typeof releasesById === 'object') {
            Object.values(releasesById).forEach(function (r) { if (r.claimant_name) names.add(r.claimant_name); });
        }
        const quickPick = document.getElementById('setStatusClaimantQuickPick');
        quickPick.innerHTML = '<option value="">Type manually…</option>' +
            Array.from(names).map(function (n) { return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>'; }).join('');
        quickPick.value = '';
        const currentStatus = document.getElementById('setStatusSelect').value;
        document.getElementById('setStatusReasonGroup').style.display = currentStatus === 'CANCELLED' ? '' : 'none';
        document.getElementById('setStatusClaimantGroup').style.display = currentStatus === 'RELEASED' ? '' : 'none';
        openModal('m-set-check-status');
    }
    document.getElementById('setStatusSelect')?.addEventListener('change', function () {
        document.getElementById('setStatusReasonGroup').style.display = this.value === 'CANCELLED' ? '' : 'none';
        document.getElementById('setStatusClaimantGroup').style.display = this.value === 'RELEASED' ? '' : 'none';
    });
    document.getElementById('setStatusClaimantQuickPick')?.addEventListener('change', function () {
        if (this.value) document.getElementById('setStatusClaimantName').value = this.value;
    });
    document.getElementById('setStatusSaveBtn')?.addEventListener('click', function () {
        const btn = this;
        const checkId = setStatusCheckId;
        const newStatus = document.getElementById('setStatusSelect').value;
        const payload = { status: newStatus };
        if (newStatus === 'CANCELLED') payload.reason = document.getElementById('setStatusReason').value;
        if (newStatus === 'RELEASED') {
            payload.claimant_type = document.getElementById('setStatusClaimantType').value;
            payload.claimant_name = document.getElementById('setStatusClaimantName').value.trim();
        }
        btn.disabled = true;
        fetch(API + '/preparation/check/' + checkId + '/set-status', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload),
        })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                toast(json.message || (json.success ? 'Status updated.' : 'Could not update status.'), !json.success);
                if (json.success) { closeModal('m-set-check-status'); applyStatusInPlace(checkId, newStatus); }
            })
            .catch(function () { btn.disabled = false; toast('Could not reach the server.', true); });
    });

    // Updates the row's status badge (and Print button visibility) in place
    // instead of reloading the filtered list — the user explicitly doesn't
    // want a status change to make the row vanish out from under them just
    // because it no longer matches whatever status tab happens to be active.
    function applyStatusInPlace(checkId, newStatus) {
        const check = prepChecksById[checkId];
        if (check) check.status = newStatus;
        const row = document.querySelector('#prepChecksTable tbody tr[data-row-check-id="' + checkId + '"]');
        if (!row) return;
        const statusCell = row.querySelector('.prep-status-cell');
        if (statusCell) statusCell.innerHTML = '<span class="fims-badge">' + escapeHtml(newStatus) + '</span>';
        const actionsCell = row.querySelector('.prep-actions-cell');
        if (actionsCell) {
            actionsCell.innerHTML =
                '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-view-check="' + checkId + '" title="View"><i class="fas fa-eye mr-1"></i>View</button> ' +
                (newStatus === 'PREPARED' ? '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-view-check="' + checkId + '" title="Print"><i class="fas fa-print mr-1"></i>Print</button> ' : '') +
                '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-set-status="' + checkId + '" title="Set Status"><i class="fas fa-flag mr-1"></i>Set Status</button>';
            actionsCell.querySelectorAll('[data-view-check]').forEach(function (btn) {
                btn.addEventListener('click', function () { viewCheck(this.dataset.viewCheck); });
            });
            actionsCell.querySelectorAll('[data-set-status]').forEach(function (btn) {
                btn.addEventListener('click', function () { openSetStatusModal(this.dataset.setStatus); });
            });
        }
    }

    function fillSelect(selector, items, placeholder) {
        const sel = document.querySelector(selector);
        if (!sel) return;
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        (items || []).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id ?? item.bank_account_id ?? '';
            opt.textContent = item.name ?? item.account_name ?? '';
            sel.appendChild(opt);
        });
    }

    function initPrep() {
        loadPrepChecks();
        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#prepFilterBank', json.data, 'All Banks')).catch(() => {});
        checkPrintAgent();
    }

    // ══════════════════════ PRINT AGENT ══════════════════════
    // Ported from TREASURY's check-preparation.php: a local PHP HTTP
    // server (/print-agent at the project root) that the browser talks to
    // directly at http://127.0.0.1:9123, since browsers can't talk to a
    // printer or install local software themselves. See
    // CheckProcessingService's docblock for the full architecture note —
    // including why this uses one generic check layout instead of
    // TREASURY's three pixel-tuned per-bank ones.
    const PRINT_AGENT = 'http://127.0.0.1:9123';
    let agentRetryTimer = null;

    function checkPrintAgent() {
        const warn = document.getElementById('print-agent-warning');
        fetch(PRINT_AGENT + '/ping', { signal: AbortSignal.timeout(3000) })
            .then(r => r.json())
            .then(function (res) { warn.style.display = res.success ? 'none' : 'flex'; })
            .catch(function () { warn.style.display = 'flex'; });
    }
    document.getElementById('prepInstallAgentBtn')?.addEventListener('click', downloadPrintAgentInstaller);
    function downloadPrintAgentInstaller() {
        window.open(API + '/print-agent-package', '_blank');
    }

    function fmtNum(n) {
        return (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function numberToWords(amount) {
        const n = parseFloat(amount || 0);
        if (isNaN(n)) return '';
        const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        const toW = function (n) {
            if (!n) return '';
            if (n < 20) return ones[n] + ' ';
            if (n < 100) return tens[Math.floor(n / 10)] + (n % 10 ? ' ' + ones[n % 10] : '') + ' ';
            if (n < 1000) return ones[Math.floor(n / 100)] + ' Hundred ' + toW(n % 100);
            if (n < 1e6) return toW(Math.floor(n / 1000)) + 'Thousand ' + toW(n % 1000);
            if (n < 1e9) return toW(Math.floor(n / 1e6)) + 'Million ' + toW(n % 1e6);
            return toW(Math.floor(n / 1e9)) + 'Billion ' + toW(n % 1e9);
        };
        const pesos = Math.floor(n);
        const cents = Math.round((n - pesos) * 100);
        return toW(pesos).trim() + ' Pesos' + (cents ? ' and ' + toW(cents).trim() + ' Centavos' : ' Only');
    }

    // Bank name → template id, ported verbatim from TREASURY's _viewCheck()/
    // _syncTemplateToBank() bank-detection logic.
    function getCheckTemplate(bankName) {
        const bn = (bankName || '').toUpperCase();
        if (bn.includes('DEVELOPMENT BANK') || bn.includes('DBP')) return 'NHA_DBP';
        if (bn.includes('VETERANS') || bn.includes('PVB')) return 'NHA_PVB';
        return 'NHA_LBP';
    }

    // QR code (DBP only) — ported verbatim from TREASURY's _buildQrSvg().
    // Uses QRCode.create() (the qrcode npm package's low-level sync API,
    // loaded via CDN above) rather than QRCode.toCanvas() — canvas pixel
    // data doesn't survive innerHTML/outerHTML serialization, which is what
    // the print agent's headless-Chrome render depends on.
    function buildQrSvg(text) {
        const qr = QRCode.create(text || '0000000000', { errorCorrectionLevel: 'M' });
        const size = qr.modules.size;
        const data = qr.modules.data;
        const rects = [];
        for (let row = 0; row < size; row++) {
            for (let col = 0; col < size; col++) {
                if (data[row * size + col] & 1) {
                    rects.push('<rect x="' + col + '" y="' + row + '" width="1" height="1" fill="#000"/>');
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges" width="100%" height="100%" style="display:block;">' + rects.join('') + '</svg>';
    }

    // Barcode (LBP/PVB) — ported verbatim from TREASURY's _buildBarcode(): a
    // realistic-looking Code-128-style SVG generated from a digit string,
    // not a real Code-128 encoding (this is cosmetic — it is not actually
    // scanned by anything in this workflow).
    function buildBarcode(text) {
        const digits = (text || '').replace(/\D/g, '').slice(-6).padStart(6, '0');
        const H = 26;
        const rects = [];
        let x = 0;
        x += 4;
        const guard = [2, 1, 2];
        guard.forEach(w => { rects.push('<rect x="' + x + '" y="0" width="' + w + '" height="' + H + '" fill="#000"/>'); x += w + 1; });
        x += 1;
        const patterns = [
            [2, 1, 2, 2, 1], [1, 2, 1, 3, 1], [3, 1, 1, 2, 2], [1, 3, 2, 1, 2], [2, 2, 3, 1, 1],
            [1, 1, 2, 3, 2], [2, 3, 1, 1, 2], [1, 2, 3, 2, 1], [3, 2, 1, 2, 1], [1, 1, 3, 2, 2],
        ];
        for (const ch of digits) {
            const pat = patterns[parseInt(ch, 10)];
            pat.forEach((w, i) => {
                if (i % 2 === 0) rects.push('<rect x="' + x + '" y="0" width="' + w + '" height="' + H + '" fill="#000"/>');
                x += w + 1;
            });
        }
        x += 1;
        const stop = [2, 1, 2, 1, 3];
        stop.forEach((w) => { rects.push('<rect x="' + x + '" y="0" width="' + w + '" height="' + H + '" fill="#000"/>'); x += w + 1; });
        x += 4;
        const label = digits.slice(-3);
        return '<div style="text-align:center;line-height:1;min-width:2.4cm;margin:0 auto;">' +
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + x + ' ' + H + '" preserveAspectRatio="none" width="100%" height="' + H + '" style="display:block;margin:0 auto;min-width:2.4cm;height:6mm;">' + rects.join('') + '</svg>' +
            '<div style="font-family:Arial,sans-serif;font-size:8px;margin-top:2px;">' + label + '</div></div>';
    }

    // Three pixel-tuned per-bank check layouts (NHA_LBP/NHA_DBP/NHA_PVB) —
    // ported verbatim from TREASURY's client-side _buildCheckHtml(), the
    // template actually used by its View/Preview/Print flows (its PHP
    // generateCheckHtml() is a plain, non-bank-specific document used only
    // by an iframe-preview route that has no live callers — see this file's
    // Check Preview modal comment). Absolute translateX(...) offsets are
    // calibrated against NHA's real pre-printed check stock; keep them as-is.
    function buildCheckFragment(c, opts) {
        opts = opts || {};
        const tpl = getCheckTemplate(c.bank_name);
        const isLBP = tpl === 'NHA_LBP';
        const isPVB = tpl === 'NHA_PVB';
        const num = c.check_number || '';
        const dateIso = c.check_date || '';
        const payee = c.payee || '';
        const amount = c.amount || 0;
        const words = numberToWords(amount);
        const dvNum = c.dv_number || '';
        const sig1Name = c.signatory1_name || '';
        const sig1Desig = c.signatory1_designation || '';
        const sig2Name = c.signatory2_name || '';
        const sig2Desig = c.signatory2_designation || '';

        const bName = escapeHtml(c.bank_name) || (isLBP ? 'LAND BANK OF THE PHILIPPINES' : isPVB ? 'PHILIPPINE VETERANS BANK' : 'DEVELOPMENT BANK OF THE PHILIPPINES');
        const bBranch = escapeHtml(c.bank_branch) || (isLBP ? 'QC CIRCLE BRANCH' : isPVB ? 'MAIN BRANCH' : 'COMMONWEALTH BRANCH');
        const acctNum = escapeHtml(c.account_number) || '—';
        const acctName = escapeHtml(c.account_name) || 'NATIONAL HOUSING AUTHORITY';

        const brstn1 = isLBP ? '01035' : isPVB ? '01057' : '01059';
        const brstn2 = isLBP ? '0290' : isPVB ? '0010' : '004-7';
        const logoTxt = isLBP ? 'LANDBANK' : isPVB ? 'PVB' : 'DBP';
        const logoUrl = isLBP ? '/_assets/image-logos/Landbank_New.png'
            : isPVB ? '/_assets/image-logos/Philippine_Veterans_Bank_logo.svg'
            : '/_assets/image-logos/DBP_company_logo.svg.png';

        const bAddr = isLBP
            ? (bBranch + '<br>PCA BLDG COMMONWEALTH AVE DILIMAN QC')
            : isPVB
            ? (bBranch + '<br>PVB BLDG., CORNER ARNAIZ AVE. &amp; PASEO DE ROXAS<br>MAKATI CITY')
            : (bBranch + '<br>DBP BLDG., COMMONWEALTH AVE.<br>DILIMAN, QUEZON CITY 1121');

        const disclaimer = isLBP
            ? '&ldquo;I/We allow the electronic clearing of this check and hereby waive the presentation for payment of this original to LANDBANK.&rdquo;'
            : isPVB
            ? '&ldquo;I/We allow the electronic clearing of this check and hereby waive the presentation for payment of this original to PHILIPPINE VETERANS BANK.&rdquo;'
            : '&ldquo;I/We allow the electronic clearing of this check and hereby waive the presentation for payment of this original to DBP.&rdquo;';

        const micrAcct = (c.account_number || '').replace(/\D/g, '').padStart(12, '0');
        const micrCheck = (num || '').replace(/\D/g, '').padStart(10, '0');

        let mm1 = '', mm2 = '', dd1 = '', dd2 = '', yy1 = '', yy2 = '', yy3 = '', yy4 = '';
        if (dateIso) {
            const p = dateIso.split('-'); // Y-m-d
            if (p.length >= 3) {
                mm1 = p[1]?.[0] || ''; mm2 = p[1]?.[1] || '';
                dd1 = p[2]?.[0] || ''; dd2 = p[2]?.[1] || '';
                yy1 = p[0]?.[0] || ''; yy2 = p[0]?.[1] || ''; yy3 = p[0]?.[2] || ''; yy4 = p[0]?.[3] || '';
            }
        }
        const dc = v => '<span class="nha-chk-datecell">' + (v || '&nbsp;') + '</span>';
        const dateCells = dc(mm1) + dc(mm2) + '<span class="nha-chk-datesep">&ndash;</span>' + dc(dd1) + dc(dd2) + '<span class="nha-chk-datesep">&ndash;</span>' + dc(yy1) + dc(yy2) + dc(yy3) + dc(yy4);
        const dg = v => '<span class="nha-chk-dateguide-cell">' + v + '</span>';
        const dateGuide = dg('M') + dg('M') + '<span class="nha-chk-dateguide-sep"></span>' + dg('D') + dg('D') + '<span class="nha-chk-dateguide-sep"></span>' + dg('Y') + dg('Y') + dg('Y') + dg('Y');

        const codeArea = (!isPVB && !isLBP)
            ? ('<div class="nha-chk-qr" style="width:30px;height:30px;">' + buildQrSvg(num) + '</div>')
            : buildBarcode(num || '0000000000');

        const sigBlock = (name, desig) => (
            '<div class="nha-chk-sig">' +
                '<div class="nha-chk-sigline"></div>' +
                (name ? ('<div class="nha-chk-signame">' + escapeHtml(name) + '</div><div class="nha-chk-sigdesig">' + escapeHtml(desig || '') + '</div>') : '') +
            '</div>'
        );

        return '' +
        '<div class="nha-chk' + (opts.isDup ? ' nha-chk--dup' : '') + (opts.showDv ? ' nha-chk--show-dv' : '') + '">' +
            '<div class="nha-chk-stamp"><span>DOCUMENTARY STAMPS PAID</span></div>' +
            '<div class="nha-chk-body">' +
                '<div class="nha-chk-header">' +
                    '<div class="nha-chk-hcol" style="min-width:110px;transform: translateX(6px);">' +
                        '<div class="nha-chk-hlbl">ACCOUNT No.</div><div class="nha-chk-hval">' + acctNum + '</div>' +
                    '</div>' +
                    '<div class="nha-chk-hcol" style="transform: translateX(56px);flex:1;">' +
                        '<div class="nha-chk-hlbl">ACCOUNT NAME</div><div class="nha-chk-hval" style="font-size:9.5px;">' + acctName + '</div>' +
                    '</div>' +
                    '<div class="nha-chk-hcol" style="transform: translateX(-130px);align-items:center;justify-content:center;padding:4px 8px;margin-left:-4mm;">' + codeArea + '</div>' +
                    '<div class="nha-chk-hcol" style="transform: translateX(-100px);min-width:100px;margin-left:-4mm;">' +
                        '<div class="nha-chk-hlbl">CHECK No.</div><div class="nha-chk-hval">' + (escapeHtml(num) || '') + '</div>' +
                    '</div>' +
                    '<div class="nha-chk-hcol" style="transform: translateX(-28px);min-width:44px;text-align:right;margin-left:-4mm;">' +
                        '<div class="nha-chk-hlbl">BRSTN</div><div class="nha-chk-hval" style="font-size:9px;">' + brstn1 + '</div><div class="nha-chk-hval" style="font-size:9px;">' + brstn2 + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="nha-chk-pdic-date">' +
                    '<div class="nha-chk-pdic">' + ((isLBP || isPVB) ? '&ldquo;Member: PDIC&rdquo;' : '') + '</div>' +
                    '<div class="nha-chk-dvmid">' + (dvNum ? ('DV# ' + escapeHtml(dvNum)) : '') + '</div>' +
                    '<div style="text-align:right;">' +
                        '<div class="nha-chk-datearea">' +
                            '<span class="nha-chk-datelbl">DATE</span>' +
                            '<div class="nha-chk-datecol">' +
                                '<span class="nha-chk-datecells">' + dateCells + '</span>' +
                                '<div class="nha-chk-dateguide">' + dateGuide + '</div>' +
                            '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="nha-chk-payrow">' +
                    '<div class="nha-chk-paylbl">PAY TO THE<br>ORDER&nbsp;   OF</div>' +
                    '<div class="nha-chk-payline">' + (escapeHtml(payee) || '') + '</div>' +
                    '<div class="nha-chk-amtrow">' +
                        '<div class="nha-chk-pesosign">&#8369;</div>' +
                        '<div class="nha-chk-amtbox">' + fmtNum(amount) + '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="nha-chk-wordrow">' +
                    '<div class="nha-chk-wordlbl">PESOS</div>' +
                    '<div class="nha-chk-wordline">' + (escapeHtml(words) || '') + '</div>' +
                '</div>' +
                '<div class="nha-chk-logorow">' +
                    '<div class="nha-chk-bankinfo">' +
                        '<img src="' + logoUrl + '" alt="' + logoTxt + '" style="height:' + (isLBP ? '52' : isPVB ? '48' : '44') + 'px;max-width:190px;object-fit:contain;display:block;" onerror="this.style.display=\'none\';this.nextElementSibling.style.display=\'block\';">' +
                        '<div style="display:none;font-size:13px;font-weight:700;color:#000E3E;line-height:1.2;">' + logoTxt + '</div>' +
                        '<div class="nha-chk-bankaddr">' + bAddr + '</div>' +
                    '</div>' +
                    '<div class="nha-chk-disclaimer">' + disclaimer + '</div>' +
                '</div>' +
                '<div class="nha-chk-bankrow"><div class="nha-chk-sigrow">' + sigBlock(sig1Name, sig1Desig) + sigBlock(sig2Name, sig2Desig) + '</div></div>' +
                '<div class="nha-chk-micr">&#9286;' + micrCheck + '&#9286;' + brstn1 + '&#9286;' + brstn2 + '&#9286;' + micrAcct + '&#9286;&nbsp;&nbsp;000</div>' +
            '</div>' +
        '</div>';
    }

    // Standalone document wrapping buildCheckFragment() — what actually gets
    // sent to the print agent's POST /print (htmls[]), since the agent
    // screenshots each entry as a complete page via headless Chrome. Pulls
    // in the .nha-chk CSS from this page's own stylesheet so the agent's
    // file:/// load can still resolve it via the real web server.
    function buildCheckHtml(c, opts) {
        const cssHref = new URL('/app/fims-css/fims.css', window.location.origin).href;
        return '<!DOCTYPE html><html><head><meta charset="UTF-8">' +
            '<link rel="stylesheet" href="' + cssHref + '">' +
            '<style>@page{size:8.1in 3in;margin:0} body{margin:0;}</style></head><body>' +
            buildCheckFragment(c, opts) +
            '</body></html>';
    }

    // ── Print Options modal (single check) ──
    let pendingPrintCheckId = null;
    let printerAvailability = {};
    function openPrintOptions(checkId) {
        pendingPrintCheckId = checkId;
        openModal('m-print-options');
        connectPrintAgent();
    }
    function connectPrintAgent() {
        clearInterval(agentRetryTimer);
        const status = document.getElementById('pqStatus');
        const form = document.getElementById('pqForm');
        const btn = document.getElementById('btnConfirmPrint');
        form.style.display = 'none';
        btn.disabled = true;
        status.style.display = 'flex';
        status.className = 'fims-alert fims-alert-info';
        status.querySelector('.fims-alert-body').innerHTML = '<span>Connecting to print agent…</span>';

        fetch(PRINT_AGENT + '/printers')
            .then(r => r.json())
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Could not list printers.');
                const list = res.data || [];
                if (!list.length) throw new Error('No printers found on this computer.');
                const sel = document.getElementById('pqPrinter');
                sel.innerHTML = '';
                printerAvailability = {};
                list.forEach(function (p) {
                    printerAvailability[p.name] = p;
                    const opt = new Option(p.name + (p.available ? '' : '  (Offline)'), p.name);
                    sel.appendChild(opt);
                });
                const remembered = localStorage.getItem('cp_print_printer');
                if (remembered && list.some(p => p.name === remembered)) sel.value = remembered;
                document.getElementById('pqColorMode').value = localStorage.getItem('cp_print_color_mode') || 'color';
                updatePrinterStatusHint();
                clearInterval(agentRetryTimer);
                status.style.display = 'none';
                form.style.display = 'flex';
                btn.disabled = false;
            })
            .catch(function (err) {
                status.className = 'fims-alert fims-alert-danger';
                status.querySelector('.fims-alert-body').innerHTML =
                    '<span>Print agent not detected on this computer. ' +
                    '<button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="pqInstallBtn" style="margin:8px 0 4px;display:block;"><i class="fas fa-download"></i> Download &amp; Install Print Agent</button>' +
                    'Run the downloaded file once — it installs, starts the agent, and sets it to auto-start at login. This will reconnect on its own once it\'s running.' +
                    '<br><small>' + escapeHtml(err.message || String(err)) + '</small></span>';
                document.getElementById('pqInstallBtn')?.addEventListener('click', downloadPrintAgentInstaller);
                let attempts = 0;
                agentRetryTimer = setInterval(function () {
                    attempts++;
                    if (attempts > 100) { clearInterval(agentRetryTimer); return; }
                    fetch(PRINT_AGENT + '/printers', { signal: AbortSignal.timeout(2000) })
                        .then(r => r.json()).then(res => { if (res.success) connectPrintAgent(); }).catch(() => {});
                }, 3000);
            });
    }
    function updatePrinterStatusHint() {
        const sel = document.getElementById('pqPrinter');
        const p = printerAvailability[sel.value];
        document.getElementById('pqPrinterStatus').textContent = p && !p.available ? 'This printer reports status: ' + p.status : '';
    }
    document.getElementById('pqPrinter')?.addEventListener('change', updatePrinterStatusHint);

    document.getElementById('btnConfirmPrint')?.addEventListener('click', function () {
        let htmls;
        if (prepBatchHtmls) {
            htmls = prepBatchHtmls;
        } else {
            const check = prepChecksById[pendingPrintCheckId];
            if (!check) { toast('Check not found.', true); return; }
            htmls = [buildCheckHtml(check)];
        }
        const printer = document.getElementById('pqPrinter').value;
        const copies = parseInt(document.getElementById('pqCopies').value, 10) || 1;
        const colorMode = document.getElementById('pqColorMode').value;
        localStorage.setItem('cp_print_printer', printer);
        localStorage.setItem('cp_print_color_mode', colorMode);
        const btn = this;
        btn.disabled = true;
        fetch(PRINT_AGENT + '/print', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ printer, copies, colorMode, widthIn: 8.1, heightIn: 3, htmls: htmls }),
        })
            .then(r => r.json())
            .then(function (res) {
                if (!res.success) throw new Error(res.message || 'Print failed.');
                toast('Sent to printer.');
                closeModal('m-print-options');
                if (pendingPrintCheckId) {
                    return fetch(API + '/preparation/check/' + pendingPrintCheckId + '/mark-printed', { method: 'POST', headers: authHeaders() });
                }
            })
            .then(function () { pendingPrintCheckId = null; prepBatchHtmls = null; loadPrepChecks(); })
            .catch(function (err) { toast('Print failed: ' + (err.message || String(err)), true); })
            .finally(function () { btn.disabled = false; });
    });

    // ── Batch Print modal ──
    document.getElementById('prepBatchPrintBtn')?.addEventListener('click', function () {
        const ids = Array.from(prepSelectedChecks);
        if (!ids.length) { toast('Select at least one check first.', true); return; }
        const checks = ids.map(id => prepChecksById[id]).filter(Boolean);
        document.getElementById('batchPrintReviewBody').innerHTML = checks.map(function (c) {
            return '<tr><td>' + escapeHtml(c.check_number) + '</td><td>' + escapeHtml(c.payee || '') + '</td>' +
                '<td class="text-right">' + peso(c.amount) + '</td><td class="text-center"><span class="fims-badge">' + escapeHtml(c.status) + '</span></td></tr>';
        }).join('');
        const total = checks.reduce((s, c) => s + Number(c.amount || 0), 0);
        document.getElementById('batchPrintTotal').textContent = peso(total);
        document.getElementById('batchPrintCount').textContent = checks.length;
        openModal('m-batch-print');
    });
    document.getElementById('btnConfirmBatchPrint')?.addEventListener('click', function () {
        const ids = Array.from(prepSelectedChecks);
        const checks = ids.map(id => prepChecksById[id]).filter(Boolean);
        if (!checks.length) { toast('No checks selected.', true); return; }
        closeModal('m-batch-print');
        pendingPrintCheckId = null; // batch prints don't mark a single check as printed, same as TREASURY.
        prepBatchHtmls = checks.map(buildCheckHtml);
        openModal('m-print-options');
        connectPrintAgent();
    });
    let prepBatchHtmls = null;

    // ── Check Preview modal — ported from TREASURY's modal-check-preview /
    // _viewCheck()/_fillCheckPreview(): fetches the check fresh, renders it
    // inline via buildCheckFragment() into #check-preview-content, and lets
    // the PCHC/Show-DV checkboxes toggle CSS classes directly on the
    // rendered .nha-chk element (not a re-render) — same as TREASURY's
    // chk-pchc-preview/chk-show-dv listeners. ──
    let previewCheckId = null;
    function viewCheck(checkId) {
        fetch(API + '/preparation/check/' + checkId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (res) {
                if (!res.success) { toast(res.message || 'Could not load check.', true); return; }
                const c = res.data;
                previewCheckId = c.check_id;
                document.getElementById('previewSubtitle').textContent = 'Check #' + c.check_number + (c.status ? ' — ' + c.status : '');
                document.getElementById('check-preview-content').innerHTML = buildCheckFragment(c, {});
                document.getElementById('chk-pchc-preview').checked = false;
                document.getElementById('chk-show-dv').checked = false;
                openModal('m-check-preview');
            })
            .catch(function () { toast('Could not reach the server.', true); });
    }
    document.getElementById('chk-pchc-preview')?.addEventListener('change', function (e) {
        const chk = document.querySelector('#check-preview-content .nha-chk');
        if (chk) chk.classList.toggle('nha-chk--plain', e.target.checked);
    });
    document.getElementById('chk-show-dv')?.addEventListener('change', function (e) {
        const chk = document.querySelector('#check-preview-content .nha-chk');
        if (chk) chk.classList.toggle('nha-chk--show-dv', e.target.checked);
    });
    document.getElementById('btn-do-print')?.addEventListener('click', function () {
        closeModal('m-check-preview');
        openPrintOptions(previewCheckId);
    });

    // ── Prepare Check modal: connects a check to a FOR_TREASURY disbursement
    // voucher (see CheckProcessingService::getDvsForPrep/saveCheck). ──
    let prepDvsById = {};
    function openPrepareCheckModal() {
        const err = document.getElementById('prepModalError');
        if (err) err.style.display = 'none';
        document.getElementById('prepCashCheck').style.display = 'none';
        document.getElementById('prepPayee').value = '';
        document.getElementById('prepProgram').value = '';
        document.getElementById('prepNetAmount').value = '';
        const checkNoSel = document.getElementById('prepCheckNumber');
        checkNoSel.value = '';
        checkNoSel.placeholder = 'Select bank account first...';
        checkNoSel.disabled = true;
        document.getElementById('prepCheckDate').value = new Date().toISOString().slice(0, 10);
        ['prepSig1Name', 'prepSig1Desig', 'prepSig2Name', 'prepSig2Desig'].forEach(id => {
            document.getElementById(id).value = '';
        });

        fetch(API + '/preparation/dvs-for-prep?draw=1', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                prepDvsById = {};
                rows.forEach(function (dv) { prepDvsById[dv.dv_id] = dv; });
                const sel = document.getElementById('prepDvSelect');
                sel.innerHTML = '<option value="">' +
                    (rows.length ? 'Select a DV...' : 'No DVs currently FOR_TREASURY') +
                    '</option>' +
                    rows.map(dv => '<option value="' + dv.dv_id + '">' + escapeHtml(dv.dv_no) + ' — ' + escapeHtml(dv.claimant_name || '') + '</option>').join('');
            })
            .catch(() => {});

        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => fillSelect('#prepBankAccount', json.data, 'Select bank account...'))
            .catch(() => {});

        openModal('m-prepare-check');
    }

    document.getElementById('prepDvSelect')?.addEventListener('change', function () {
        const dv = prepDvsById[this.value];
        document.getElementById('prepPayee').value = dv ? (dv.claimant_name || '') : '';
        document.getElementById('prepProgram').value = dv ? (dv.program_names || '') : '';
        document.getElementById('prepNetAmount').value = dv ? peso(dv.net_amount) : '';
        verifyPrepCash();
    });

    document.getElementById('prepBankAccount')?.addEventListener('change', function () {
        const checkNoSel = document.getElementById('prepCheckNumber');
        const bankAccountId = this.value;
        verifyPrepCash();
        checkNoSel.value = '';
        if (!bankAccountId) {
            checkNoSel.placeholder = 'Select bank account first...';
            checkNoSel.disabled = true;
            return;
        }
        checkNoSel.placeholder = 'Loading...';
        checkNoSel.disabled = true;
        fetch(API + '/preparation/unused-checks/' + bankAccountId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const numbers = (json && json.data) ? json.data : [];
                // Only the next number in line (the registry's own ordering —
                // lowest unused check_number) is shown, not the whole
                // remaining batch, so checks always get used strictly in
                // order instead of letting someone pick an arbitrary one out
                // of sequence. Plain read-only field, not a dropdown — there's
                // never more than this one value to show.
                if (!numbers.length) {
                    checkNoSel.placeholder = 'No unused checks — add a batch in Check Registry';
                    checkNoSel.disabled = true;
                    return;
                }
                checkNoSel.value = numbers[0];
                checkNoSel.disabled = true;
            })
            .catch(function () {
                checkNoSel.placeholder = 'Could not load check numbers';
            });
    });

    // Ported from TREASURY's _verifyCash(): purely informational — shows
    // available cash for the selected bank account vs. the selected DV's
    // net amount, but never blocks the Save button (saveCheck() has its
    // own, separately-queried soft block server-side).
    function verifyPrepCash() {
        const box = document.getElementById('prepCashCheck');
        const bankAccountId = document.getElementById('prepBankAccount').value;
        const dv = prepDvsById[document.getElementById('prepDvSelect').value];
        const amt = dv ? parseFloat(dv.net_amount) : 0;
        if (!bankAccountId || !amt) {
            box.style.display = 'none';
            return;
        }
        fetch(API + '/preparation/cash-availability?bank_account_id=' + bankAccountId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const row = (json && json.success && json.data && json.data[0]) ? json.data[0] : null;
                if (!row) { box.style.display = 'none'; return; }
                const bal = parseFloat(row.available_balance || 0);
                const ok = bal >= amt;
                box.className = 'fims-alert ' + (ok ? 'fims-alert-success' : 'fims-alert-danger');
                box.style.display = '';
                box.querySelector('.fims-alert-body').innerHTML = ok
                    ? 'Cash available: <strong>' + peso(bal) + '</strong> — sufficient for ' + peso(amt) + '.'
                    : 'Insufficient cash: Available <strong>' + peso(bal) + '</strong>, Required ' + peso(amt) + '.';
            })
            .catch(() => { box.style.display = 'none'; });
    }

    document.getElementById('prepSaveBtn')?.addEventListener('click', function () {
        const btn = this;
        const err = document.getElementById('prepModalError');
        const payload = {
            dv_id: document.getElementById('prepDvSelect').value,
            bank_account_id: document.getElementById('prepBankAccount').value,
            check_date: document.getElementById('prepCheckDate').value,
            check_number: document.getElementById('prepCheckNumber').value.trim(),
            signatory1_name: document.getElementById('prepSig1Name').value.trim(),
            signatory1_designation: document.getElementById('prepSig1Desig').value.trim(),
            signatory2_name: document.getElementById('prepSig2Name').value.trim(),
            signatory2_designation: document.getElementById('prepSig2Desig').value.trim(),
        };
        if (!payload.dv_id || !payload.bank_account_id || !payload.check_date || !payload.check_number) {
            if (err) { err.querySelector('.fims-alert-body').textContent = 'DV, bank account, check date, and check number are all required.'; err.style.display = ''; }
            return;
        }
        btn.disabled = true;
        fetch(API + '/preparation/save', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                if (json.success) {
                    toast('Check ' + json.data.check_number + ' saved.');
                    closeModal('m-prepare-check');
                    loadPrepChecks();
                } else if (err) {
                    err.querySelector('.fims-alert-body').textContent = json.message || 'Could not save check.';
                    err.style.display = '';
                }
            })
            .catch(function () {
                btn.disabled = false;
                if (err) { err.querySelector('.fims-alert-body').textContent = 'Could not reach the server. Please try again.'; err.style.display = ''; }
            });
    });

    // ── Registry — ported from TREASURY's check-registry.php ──
    document.getElementById('regSearchBtn')?.addEventListener('click', loadRegistry);
    document.getElementById('regClearBtn')?.addEventListener('click', function () {
        ['regFilterOfficeType', 'regFilterBank', 'regFilterSeries', 'regFilterStatus', 'regFilterEntryType', 'regFilterDateFrom', 'regFilterDateTo', 'regFilterSearch'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        loadRegistry();
    });
    document.getElementById('regAddBatchBtn')?.addEventListener('click', function () {
        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#batchBankAccount', json.data, 'Select bank account...')).catch(() => {});
        document.getElementById('batchSeries').value = '';
        document.getElementById('batchRangeStart').value = '';
        document.getElementById('batchRangeEnd').value = '';
        document.getElementById('batchRangeDate').value = '';
        document.getElementById('batchRangePreview').textContent = '';
        document.getElementById('batchIndividualRows').innerHTML = '';
        document.getElementById('batchIndividualPreview').textContent = '';
        switchBatchTab('range');
        addIndividualRow();
        openModal('m-add-batch');
    });

    // ── Add Batch: Range / Individual tabs ──
    function switchBatchTab(tab) {
        document.querySelectorAll('#batchTabNav [data-batch-tab]').forEach(b => b.classList.toggle('active', b.dataset.batchTab === tab));
        document.getElementById('batchRangePanel').style.display = tab === 'range' ? '' : 'none';
        document.getElementById('batchIndividualPanel').style.display = tab === 'individual' ? '' : 'none';
    }
    document.querySelectorAll('#batchTabNav [data-batch-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () { switchBatchTab(this.dataset.batchTab); });
    });

    // Parses a check number string into {prefix, num, padLen} — e.g.
    // "0010440" -> {prefix:'', num:10440, padLen:7}. Mirrors TREASURY's
    // _parseCheckNumber() exactly: padLen comes from the literal digit
    // count the user typed, not the numeric magnitude, so a correctly
    // computed Math.max(start.padLen, end.padLen) never truncates when
    // padding every number in the range (see the Postgres LPAD pitfall
    // noted server-side in addRegisterBatch()'s docblock).
    function parseCheckNumber(val) {
        const m = String(val || '').match(/^(.*?)(\d+)$/);
        if (!m) return null;
        return { prefix: m[1], num: parseInt(m[2], 10), padLen: m[2].length };
    }

    function buildRangeChecks(startVal, endVal, date) {
        const s = parseCheckNumber(startVal);
        const e = parseCheckNumber(endVal);
        if (!s || !e) return { error: 'Check numbers must end with digits (e.g. 0010440).' };
        if (s.prefix !== e.prefix) return { error: 'Start and End check numbers have different prefixes.' };
        if (e.num < s.num) return { error: 'End check number must be ≥ Start check number.' };
        const count = e.num - s.num + 1;
        if (count > 2000) return { error: 'Range of ' + count + ' exceeds the 2000-check limit per batch.' };
        const padLen = Math.max(s.padLen, e.padLen);
        const checks = [];
        for (let n = s.num; n <= e.num; n++) {
            checks.push({ check_number: s.prefix + String(n).padStart(padLen, '0'), entry_date: date });
        }
        return { checks, count };
    }

    document.getElementById('batchRangeStart')?.addEventListener('input', updateRangePreview);
    document.getElementById('batchRangeEnd')?.addEventListener('input', updateRangePreview);
    function updateRangePreview() {
        const start = document.getElementById('batchRangeStart').value.trim();
        const end = document.getElementById('batchRangeEnd').value.trim();
        const preview = document.getElementById('batchRangePreview');
        if (!start || !end) { preview.textContent = ''; return; }
        const result = buildRangeChecks(start, end, '2026-01-01');
        preview.textContent = result.error ? result.error : (result.count + ' check(s) will be registered.');
        preview.style.color = result.error ? '#dc2626' : '';
    }

    function addIndividualRow() {
        const tbody = document.getElementById('batchIndividualRows');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" class="fims-input fims-input-sm ind-check-number"></td>' +
            '<td><input type="date" class="fims-input fims-input-sm ind-entry-date"></td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-row><i class="fas fa-trash"></i></button></td>';
        tr.querySelector('[data-remove-row]').addEventListener('click', function () { tr.remove(); });
        tbody.appendChild(tr);
    }
    document.getElementById('batchAddRowBtn')?.addEventListener('click', addIndividualRow);

    // Groups checks into "Booklet N" batches of 100 (by array index, not
    // check-number value), continuing from whatever booklet numbers
    // already exist for this bank account's check series — mirrors
    // TREASURY's _assignBookletNames()/_nextBookletStart().
    function assignBookletNames(checks, bankAccountId, series) {
        return fetch(API + '/registry/booklets?bank_account_id=' + encodeURIComponent(bankAccountId), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                let next = 1;
                ((json && json.data) || []).forEach(function (b) {
                    if (b.check_series !== series) return;
                    const m = /^Booklet\s+(\d+)$/i.exec(b.booklet_no || '');
                    if (m) next = Math.max(next, parseInt(m[1], 10) + 1);
                });
                checks.forEach(function (c, i) {
                    c.booklet_no = 'Booklet ' + (next + Math.floor(i / 100));
                });
                return checks;
            })
            .catch(() => { checks.forEach(c => { c.booklet_no = ''; }); return checks; });
    }

    document.getElementById('batchSaveBtn')?.addEventListener('click', function () {
        const bankAccountId = document.getElementById('batchBankAccount').value;
        const series = document.getElementById('batchSeries').value.trim();
        const deptType = document.getElementById('batchDeptType').value;
        if (!bankAccountId) { toast('Select a bank account.', true); return; }
        if (!series) { toast('Batch series is required.', true); return; }

        const activeTab = document.querySelector('#batchTabNav [data-batch-tab].active')?.dataset.batchTab;
        let checks = [];
        if (activeTab === 'range') {
            const start = document.getElementById('batchRangeStart').value.trim();
            const end = document.getElementById('batchRangeEnd').value.trim();
            const date = document.getElementById('batchRangeDate').value;
            if (!date) { toast('Date is required.', true); return; }
            const result = buildRangeChecks(start, end, date);
            if (result.error) { toast(result.error, true); return; }
            checks = result.checks;
        } else {
            document.querySelectorAll('#batchIndividualRows tr').forEach(function (tr) {
                const checkNumber = tr.querySelector('.ind-check-number').value.trim();
                const entryDate = tr.querySelector('.ind-entry-date').value;
                if (checkNumber && entryDate) checks.push({ check_number: checkNumber, entry_date: entryDate });
            });
            if (!checks.length) { toast('Add at least one check.', true); return; }
        }

        const btn = this;
        btn.disabled = true;
        assignBookletNames(checks, bankAccountId, series).then(function (checksWithBooklets) {
            const payload = { bank_account_id: bankAccountId, check_series: series, department_type: deptType, checks: checksWithBooklets };
            return fetch(API + '/registry/add-batch', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
                .then(r => r.json())
                .then(function (json) {
                    toast(json.message || (json.success ? ('Registered ' + json.data.inserted + ' check(s).') : 'Could not save batch.'), !json.success);
                    if (json.success) {
                        closeModal('m-add-batch');
                        loadRegistrySeries();
                        loadRegistry();
                    }
                });
        }).finally(() => { btn.disabled = false; });
    });

    function loadRegistryFilters() {
        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#regFilterBank', json.data, 'All Banks')).catch(() => {});
        loadRegistrySeries();
    }

    function loadRegistrySeries() {
        fetch(API + '/registry/series-list', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const sel = document.getElementById('regFilterSeries');
                const current = sel.value;
                sel.innerHTML = '<option value="">All Series</option>';
                ((json && json.data) || []).forEach(function (row) {
                    const opt = document.createElement('option');
                    opt.value = row.check_series;
                    opt.textContent = row.check_series + ' (' + row.total_checks + ')';
                    sel.appendChild(opt);
                });
                sel.value = current;
            })
            .catch(() => {});
    }

    function buildRegistryParams() {
        const params = new URLSearchParams();
        const map = {
            regFilterOfficeType: 'office_type', regFilterBank: 'bank_account_id', regFilterSeries: 'check_series',
            regFilterStatus: 'status', regFilterEntryType: 'entry_type',
            regFilterDateFrom: 'date_from', regFilterDateTo: 'date_to', regFilterSearch: 'search',
        };
        Object.keys(map).forEach(function (id) {
            const val = document.getElementById(id)?.value?.trim();
            if (val) params.set(map[id], val);
        });
        return params;
    }

    const REGISTRY_STATUS_BADGE = {
        UNUSED: 'fims-badge-blue', ISSUED: 'fims-badge-green', ENCASHED: 'fims-badge-indigo',
        OUTSTANDING: 'fims-badge-amber', CANCELLED: 'fims-badge-red', VOIDED: 'fims-badge-gray',
        SPOILED: 'fims-badge-gray', STALE: 'fims-badge-amber',
    };

    // Groups the flat register list by (bank account, booklet) — mirrors
    // TREASURY's bank -> series -> booklet grouping, collapsed to one
    // level since booklet_no already carries the series context. Rows with
    // no booklet_no (individually-added checks) fall into their own
    // "No Booklet" group per bank account rather than being hidden. This
    // tab shows only these booklet/batch-level summaries — never the
    // individual check rows within a booklet.
    function groupRegistryRows(rows) {
        const groups = [];
        const index = {};
        rows.forEach(function (r) {
            const key = r.bank_account_id + '|' + (r.booklet_no || '');
            if (!index[key]) {
                index[key] = {
                    id: groups.length, account_name: r.account_name, bank_name: r.bank_name,
                    booklet_no: r.booklet_no || null, check_series: r.check_series,
                    department_type: r.department_type, rows: [],
                };
                groups.push(index[key]);
            }
            index[key].rows.push(r);
        });
        return groups;
    }

    function registryGroupSummary(rows) {
        const counts = {};
        rows.forEach(function (r) { counts[r.current_status] = (counts[r.current_status] || 0) + 1; });
        return Object.keys(counts).map(function (status) { return counts[status] + ' ' + status.charAt(0) + status.slice(1).toLowerCase(); }).join(', ');
    }

    // Client-side pagination (no refetch) over the booklet/batch groups.
    const REG_GROUPS_PER_PAGE = 20;
    let regGroups = [];
    let regPage = 1;

    function loadRegistry() {
        fetch(API + '/registry/summary', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#registryKpiCards [data-kpi]').forEach(el => { el.textContent = d[el.dataset.kpi] ?? 0; });
            }).catch(() => {});

        const tbody = document.querySelector('#registryTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        fetch(API + '/registry/list?' + buildRegistryParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                regGroups = groupRegistryRows(rows);
                regPage = 1;
                renderRegistryPage();
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Could not reach the server.</td></tr>'; });
    }

    // Plain booklet/batch summary rows — no per-check list underneath
    // (each row is just its booklet_no/series/bank/dept + a check count and
    // a status breakdown, not expandable).
    function renderRegistryPage() {
        const tbody = document.querySelector('#registryTable tbody');
        if (!regGroups.length) {
            tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">No records.</td></tr>';
            renderRegistryPagination();
            return;
        }
        const lastPage = Math.max(1, Math.ceil(regGroups.length / REG_GROUPS_PER_PAGE));
        regPage = Math.min(Math.max(1, regPage), lastPage);
        const start = (regPage - 1) * REG_GROUPS_PER_PAGE;
        const pageGroups = regGroups.slice(start, start + REG_GROUPS_PER_PAGE);

        tbody.innerHTML = pageGroups.map(function (g, i) {
            const bankLabel = g.account_name
                ? escapeHtml(g.account_name) + (g.bank_name ? ' — ' + escapeHtml(g.bank_name) : '')
                : '—';
            return '<tr>' +
                '<td>' + (start + i + 1) + '</td>' +
                '<td>' + escapeHtml(g.booklet_no || 'No Booklet') + '</td>' +
                '<td><span class="fims-badge">' + escapeHtml(g.check_series || '—') + '</span></td>' +
                '<td>' + bankLabel + '</td>' +
                '<td>' + escapeHtml(g.department_type || '—') + '</td>' +
                '<td class="text-center">' + g.rows.length + '</td>' +
                '<td>' + (registryGroupSummary(g.rows) || '—') + '</td>' +
                '</tr>';
        }).join('');
        renderRegistryPagination();
    }

    function renderRegistryPagination() {
        const box = document.getElementById('registryPagination');
        const totalChecks = regGroups.reduce((sum, g) => sum + g.rows.length, 0);
        const lastPage = Math.max(1, Math.ceil(regGroups.length / REG_GROUPS_PER_PAGE));
        if (!regGroups.length) { box.innerHTML = ''; return; }

        const pages = [];
        for (let i = Math.max(1, regPage - 2); i <= Math.min(lastPage, regPage + 2); i++) {
            pages.push('<button type="button" class="fims-page-btn' + (i === regPage ? ' active' : '') + '" data-reg-page="' + i + '">' + i + '</button>');
        }
        box.innerHTML =
            '<span style="font-size:12px;color:var(--fims-text-subtle);margin-right:10px;">' + regGroups.length + ' booklet(s), ' + totalChecks + ' check(s)</span>' +
            '<button type="button" class="fims-page-btn" data-reg-page="' + (regPage - 1) + '"' + (regPage === 1 ? ' disabled' : '') + '>‹</button>' +
            pages.join('') +
            '<button type="button" class="fims-page-btn" data-reg-page="' + (regPage + 1) + '"' + (regPage === lastPage ? ' disabled' : '') + '>›</button>';
        box.querySelectorAll('[data-reg-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                regPage = parseInt(this.dataset.regPage, 10);
                renderRegistryPage();
            });
        });
    }
    function initRegistry() { loadRegistryFilters(); loadRegistry(); }

    // ── Update Status modal ──
    const STATUS_DATE_LABELS = {
        ISSUED: 'Issue Date', ENCASHED: 'Encashment Date', OUTSTANDING: 'Effective Date',
        CANCELLED: 'Cancellation Date', VOIDED: 'Void Date', SPOILED: 'Spoiled Date', STALE: 'Stale Date',
    };

    function openUpdateStatusModal(entryId) {
        fetch(API + '/registry/' + entryId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load entry.', true); return; }
                const d = json.data;
                document.getElementById('statusEntryId').value = d.register_entry_id;
                document.getElementById('statusCheckNumber').value = d.check_number;
                document.getElementById('statusCurrent').value = d.current_status;
                document.getElementById('statusNew').value = '';
                document.getElementById('statusDate').value = '';
                document.getElementById('statusReason').value = '';
                document.getElementById('statusReplacement').value = '';
                document.getElementById('statusReasonGroup').style.display = 'none';
                document.getElementById('statusReplacementGroup').style.display = 'none';
                document.getElementById('statusDateLabel').textContent = 'Date';
                openModal('m-update-status');
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    document.getElementById('statusNew')?.addEventListener('change', function () {
        const status = this.value;
        document.getElementById('statusDateLabel').textContent = STATUS_DATE_LABELS[status] || 'Date';
        document.getElementById('statusReasonGroup').style.display = ['CANCELLED', 'VOIDED', 'SPOILED', 'STALE'].includes(status) ? '' : 'none';
        document.getElementById('statusReplacementGroup').style.display = status === 'CANCELLED' ? '' : 'none';
    });

    document.getElementById('statusSaveBtn')?.addEventListener('click', function () {
        const status = document.getElementById('statusNew').value;
        if (!status) { toast('Select a new status.', true); return; }
        const payload = {
            register_entry_id: document.getElementById('statusEntryId').value,
            status: status,
            date: document.getElementById('statusDate').value,
            reason: document.getElementById('statusReason').value,
            replacement_check_number: document.getElementById('statusReplacement').value,
        };
        fetch(API + '/registry/update-status', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? 'Status updated.' : 'Could not update status.'), !json.success);
                if (json.success) {
                    closeModal('m-update-status');
                    loadRegistry();
                }
            })
            .catch(() => toast('Could not reach the server.', true));
    });

    // ── Released (unified — was two separate tabs/tables, "Released to
    // Claimants" and "Released to Regions/Departments"; see
    // CheckProcessingService's docblock for the merge). ──
    function fillCheckSelect(selector, placeholder) {
        fetch(API + '/checks/lookup', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const sel = document.querySelector(selector);
                if (!sel) return;
                sel.innerHTML = '<option value="">' + placeholder + '</option>' +
                    rows.map(c => '<option value="' + c.check_id + '" data-amount="' + c.amount + '">' +
                        escapeHtml(c.check_number) + ' — ' + escapeHtml(c.payee || '') + '</option>').join('');
            })
            .catch(() => {});
    }
    function fillDepartmentSelect(selector, placeholder) {
        fetch(API + '/releases/departments', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const sel = document.querySelector(selector);
                if (!sel) return;
                sel.innerHTML = '<option value="">' + placeholder + '</option>' +
                    rows.map(d => '<option value="' + d.department_id + '">' +
                        escapeHtml(d.department_acronym ? (d.department_acronym + ' - ' + d.department_name) : d.department_name) + '</option>').join('');
            })
            .catch(() => {});
    }

    // ── Record Release modal — release type drives which fields apply
    // (Claimant Type/Email for DIRECT/EMAIL_NOTICE, Department/RDTF for
    // REGION) — see CheckProcessingService::saveRelease(). ──
    document.getElementById('relRecordReleaseBtn')?.addEventListener('click', function () {
        document.getElementById('releaseAmount').value = '';
        document.getElementById('releaseClaimantName').value = '';
        document.getElementById('releaseClaimantEmail').value = '';
        document.getElementById('releaseRdtf').value = '';
        document.getElementById('releaseDate').value = '';
        document.getElementById('releaseRemarks').value = '';
        document.getElementById('releaseType').value = 'DIRECT';
        document.getElementById('releaseType').dispatchEvent(new Event('change'));
        fillCheckSelect('#releaseCheckNumber', 'Select check...');
        fillDepartmentSelect('#releaseDept', 'Select...');
        openModal('m-record-release');
    });
    document.getElementById('releaseCheckNumber')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        document.getElementById('releaseAmount').value = opt && opt.dataset.amount ? peso(opt.dataset.amount) : '';
    });
    document.getElementById('releaseType')?.addEventListener('change', function () {
        const isRegion = this.value === 'REGION';
        document.getElementById('releaseEmailGroup').style.display = this.value === 'EMAIL_NOTICE' ? '' : 'none';
        document.getElementById('releaseClaimantTypeGroup').style.display = isRegion ? 'none' : '';
        document.getElementById('releaseDeptGroup').style.display = isRegion ? '' : 'none';
        document.getElementById('releaseRdtfGroup').style.display = isRegion ? '' : 'none';
        document.getElementById('releaseClaimantNameLabel').textContent = isRegion ? 'Authorized Representative' : 'Claimant Name';
    });
    document.getElementById('releaseSaveBtn')?.addEventListener('click', function () {
        const payload = {
            check_number: document.getElementById('releaseCheckNumber').value,
            release_type: document.getElementById('releaseType').value,
            claimant_type: document.getElementById('releaseClaimantType').value,
            claimant_name: document.getElementById('releaseClaimantName').value,
            claimant_email: document.getElementById('releaseClaimantEmail').value,
            department_id: document.getElementById('releaseDept').value,
            rdtf_no: document.getElementById('releaseRdtf').value,
            release_date: document.getElementById('releaseDate').value,
            remarks: document.getElementById('releaseRemarks').value,
        };
        fetch(API + '/releases/save', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(json => { toast(json.message || (json.success ? 'Saved.' : 'Could not save.'), !json.success); if (json.success) { closeModal('m-record-release'); initReleased(); } });
    });

    const RELEASE_TYPE_LABELS = { DIRECT: 'Direct', EMAIL_NOTICE: 'Email Notice', REGION: 'Region/Dept' };
    let releasesById = {};

    function loadReleasesList() {
        fetch(API + '/releases', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                releasesById = {};
                rows.forEach(function (r) { releasesById[r.release_id] = r; });
                const tbody = document.querySelector('#releasesTable tbody');
                if (!tbody) return;
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const isRegion = r.release_type === 'REGION';
                    return '<tr data-row-release-id="' + r.release_id + '">' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.check_number) + '</td>' +
                        '<td>' + escapeHtml(r.payee || '') + ' / ' + peso(r.amount) + '</td>' +
                        '<td>' + escapeHtml(RELEASE_TYPE_LABELS[r.release_type] || r.release_type) + '</td>' +
                        '<td class="rel-claimant-cell">' + escapeHtml(isRegion ? (r.region_dept || '') : (r.claimant_name || '')) + '</td>' +
                        '<td class="rel-id-cell">' + escapeHtml(isRegion ? (r.rdtf_no || '') : ([r.id_type, r.id_number].filter(Boolean).join(' '))) + '</td>' +
                        '<td>' + escapeHtml(r.release_date || '') + '</td>' +
                        '<td class="text-center rel-status-cell"><span class="fims-badge">' + escapeHtml(r.status) + '</span></td>' +
                        '<td class="rel-ack-cell">' + escapeHtml(r.acknowledgment_date || '') + '</td>' +
                        '<td class="text-center rel-ack-actions">' + (r.status === 'ACKNOWLEDGED' ? '' : ('<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-acknowledge="' + r.release_id + '" title="Acknowledge"><i class="fas fa-check-circle mr-1"></i>Acknowledge</button>')) + '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-acknowledge]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openAcknowledgeModal(this.dataset.acknowledge); });
                });
            })
            .catch(() => {});
    }

    // ── Set Claimant — edits a release's claimant name/type after the fact
    // (mainly for releases created by Check Preparation's "Set Status"
    // shortcut, which leaves claimant_name NULL). Updates in place, same
    // reasoning as applyStatusInPlace(): don't make the row vanish or
    // reorder just because one field changed. ──
    let setClaimantReleaseId = null;
    function openSetClaimantModal(releaseId) {
        const release = releasesById[releaseId];
        const isRegion = release && release.release_type === 'REGION';
        setClaimantReleaseId = releaseId;
        document.getElementById('setClaimantSubtitle').textContent = release ? ('Check #' + release.check_number) : '';
        document.getElementById('setClaimantTypeGroup').style.display = isRegion ? 'none' : '';
        document.getElementById('setClaimantNameLabel').textContent = isRegion ? 'Authorized Representative' : 'Claimant Name';
        document.getElementById('setClaimantType').value = (release && release.claimant_type) ? release.claimant_type : 'PAYEE';
        document.getElementById('setClaimantName').value = release ? (release.claimant_name || '') : '';

        // Quick Pick: the check's own payee, plus any claimant names already
        // used elsewhere (so a repeat claimant doesn't need re-typing).
        const names = new Set();
        if (release && release.payee) names.add(release.payee);
        Object.values(releasesById).forEach(function (r) { if (r.claimant_name) names.add(r.claimant_name); });
        const quickPick = document.getElementById('setClaimantQuickPick');
        quickPick.innerHTML = '<option value="">Type manually…</option>' +
            Array.from(names).map(function (n) { return '<option value="' + escapeHtml(n) + '">' + escapeHtml(n) + '</option>'; }).join('');
        quickPick.value = '';
        openModal('m-set-claimant');
    }
    document.getElementById('setClaimantQuickPick')?.addEventListener('change', function () {
        if (this.value) document.getElementById('setClaimantName').value = this.value;
    });
    document.getElementById('setClaimantSaveBtn')?.addEventListener('click', function () {
        const btn = this;
        const releaseId = setClaimantReleaseId;
        const release = releasesById[releaseId];
        const isRegion = release && release.release_type === 'REGION';
        const newName = document.getElementById('setClaimantName').value.trim();
        const payload = { claimant_name: newName };
        if (!isRegion) payload.claimant_type = document.getElementById('setClaimantType').value;
        btn.disabled = true;
        fetch(API + '/releases/' + releaseId + '/set-claimant', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload),
        })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                toast(json.message || (json.success ? 'Claimant updated.' : 'Could not update claimant.'), !json.success);
                if (json.success) {
                    closeModal('m-set-claimant');
                    if (release) {
                        release.claimant_name = newName;
                        if (!isRegion) release.claimant_type = payload.claimant_type;
                    }
                    const row = document.querySelector('#releasesTable tbody tr[data-row-release-id="' + releaseId + '"]');
                    const cell = row ? row.querySelector('.rel-claimant-cell') : null;
                    if (cell) cell.textContent = isRegion ? (release.region_dept || '') : newName;
                }
            })
            .catch(function () { btn.disabled = false; toast('Could not reach the server.', true); });
    });

    // ── Acknowledge Release — had no UI entry point at all before this;
    // the backend (acknowledgeRelease()) and the Acknowledgment sub-tab's
    // read-only list already existed, but nothing could actually put a
    // release into that state. ──
    let ackReleaseId = null;
    function openAcknowledgeModal(releaseId) {
        const release = releasesById[releaseId];
        ackReleaseId = releaseId;
        document.getElementById('ackSubtitle').textContent = release ? ('Check #' + release.check_number) : '';
        document.getElementById('ackIdType').value = (release && release.id_type) ? release.id_type : '';
        document.getElementById('ackIdNumber').value = release ? (release.id_number || '') : '';
        openModal('m-acknowledge-release');
    }
    document.getElementById('ackSaveBtn')?.addEventListener('click', function () {
        const btn = this;
        const releaseId = ackReleaseId;
        const release = releasesById[releaseId];
        const idType = document.getElementById('ackIdType').value;
        const idNumber = document.getElementById('ackIdNumber').value.trim();
        btn.disabled = true;
        fetch(API + '/releases/' + releaseId + '/acknowledge', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ id_type: idType, id_number: idNumber }),
        })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                toast(json.message || (json.success ? 'Release acknowledged.' : 'Could not acknowledge release.'), !json.success);
                if (json.success) {
                    closeModal('m-acknowledge-release');
                    const today = new Date().toISOString().slice(0, 10);
                    if (release) {
                        release.status = 'ACKNOWLEDGED';
                        release.acknowledgment_date = today;
                        if (idType) release.id_type = idType;
                        if (idNumber) release.id_number = idNumber;
                    }
                    const row = document.querySelector('#releasesTable tbody tr[data-row-release-id="' + releaseId + '"]');
                    if (row) {
                        const statusCell = row.querySelector('.rel-status-cell');
                        if (statusCell) statusCell.innerHTML = '<span class="fims-badge">ACKNOWLEDGED</span>';
                        const ackCell = row.querySelector('.rel-ack-cell');
                        if (ackCell) ackCell.textContent = today;
                        const idCell = row.querySelector('.rel-id-cell');
                        if (idCell && release && release.release_type !== 'REGION') {
                            idCell.textContent = [release.id_type, release.id_number].filter(Boolean).join(' ');
                        }
                        const actionsCell = row.querySelector('.rel-ack-actions');
                        if (actionsCell) actionsCell.innerHTML = '';
                    }
                    loadReleasesAck();
                }
            })
            .catch(function () { btn.disabled = false; toast('Could not reach the server.', true); });
    });

    function loadReleasesUnclaimed() {
        fetch(API + '/releases/unclaimed', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const tbody = document.querySelector('#relUnclaimedTable tbody');
                if (!tbody) return;
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="9">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const isRegion = r.release_type === 'REGION';
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.check_number) + '</td>' +
                        '<td>' + escapeHtml(r.payee || '') + ' / ' + peso(r.amount) + '</td>' +
                        '<td>' + escapeHtml(RELEASE_TYPE_LABELS[r.release_type] || r.release_type) + '</td>' +
                        '<td>' + escapeHtml(isRegion ? (r.region_dept || '') : (r.claimant_name || '')) + '</td>' +
                        '<td class="text-center"><span class="fims-badge">' + escapeHtml(r.status) + '</span></td>' +
                        '<td>' + (r.days_since_prep != null ? r.days_since_prep : '—') + '</td>' +
                        '<td>' + escapeHtml(r.last_follow_up_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.follow_up_remarks || '') + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => {});
    }

    function loadReleasesAck() {
        fetch(API + '/releases/acknowledgment-list', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const tbody = document.querySelector('#relAckTable tbody');
                if (!tbody) return;
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="9">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const isRegion = r.release_type === 'REGION';
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.check_number) + '</td>' +
                        '<td>' + escapeHtml(r.payee || '') + '</td>' +
                        '<td class="text-right">' + peso(r.amount) + '</td>' +
                        '<td>' + escapeHtml(RELEASE_TYPE_LABELS[r.release_type] || r.release_type) + '</td>' +
                        '<td>' + escapeHtml(isRegion ? (r.region_dept || '') : (r.claimant_name || '')) + '</td>' +
                        '<td>' + escapeHtml(r.release_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.acknowledgment_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.id_number || '') + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => {});
    }

    function loadReleasesEmailLog() {
        fetch(API + '/releases/email-log', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const tbody = document.querySelector('#relEmailLogTable tbody');
                if (!tbody) return;
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const isBulk = r.check_number == null;
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(isBulk ? (r.region_dept || '') : r.check_number) + '</td>' +
                        '<td>' + escapeHtml(r.recipient_email || '') + '</td>' +
                        '<td>' + (isBulk ? (r.checks_count ?? '—') : '—') + '</td>' +
                        '<td class="text-right">' + (isBulk ? peso(r.total_amount) : '—') + '</td>' +
                        '<td>' + escapeHtml(r.sent_at || '') + '</td>' +
                        '<td>' + escapeHtml(r.sent_by || '') + '</td>' +
                        '<td class="text-center"><span class="fims-badge">' + escapeHtml(r.status) + '</span></td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => {});
    }

    function loadReleasesDvMonitoring() {
        fetch(API + '/releases/dv-monitoring', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const tbody = document.querySelector('#relDvTable tbody');
                if (!tbody) return;
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.check_number) + '</td>' +
                        '<td>' + escapeHtml(r.dv_reference || '') + '</td>' +
                        '<td>' + escapeHtml(r.payee || '') + '</td>' +
                        '<td class="text-right">' + peso(r.amount) + '</td>' +
                        '<td>' + escapeHtml(r.region_dept || '') + '</td>' +
                        '<td>' + escapeHtml(r.release_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.dv_return_date || '') + '</td>' +
                        '<td>' + (r.days_pending != null ? r.days_pending : '—') + '</td>' +
                        '<td class="text-center"><span class="fims-badge">' + escapeHtml(r.status) + '</span></td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => {});
    }

    function initReleased() {
        loadReleasesList();
        loadReleasesUnclaimed();
        loadReleasesAck();
        loadReleasesEmailLog();
        loadReleasesDvMonitoring();
        fillDepartmentSelect('#relFilterDept', 'All Regions/Depts');
    }

    // "Send Notice" — scope toggles between a single pending Email-Notice
    // release (claimant case) and a bundled monthly notice to a department
    // (region case) — see CheckProcessingService::sendReleaseNotice() /
    // sendBulkReleaseNotice().
    document.getElementById('relSendNoticeBtn')?.addEventListener('click', function () {
        const err = document.getElementById('noticeError');
        if (err) err.style.display = 'none';
        document.getElementById('noticeMessage').value = '';
        document.getElementById('noticeRecipient').value = '';
        document.getElementById('noticeBulkPeriod').value = '';
        document.getElementById('noticeBulkPendingWrap').style.display = 'none';
        document.getElementById('noticeBulkPendingBody').innerHTML = '';
        document.getElementById('noticeScope').value = 'SINGLE';
        document.getElementById('noticeScope').dispatchEvent(new Event('change'));
        fillDepartmentSelect('#noticeBulkDept', 'Select...');
        fetch(API + '/releases', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const pending = ((json && json.data) ? json.data : []).filter(r => r.release_type === 'EMAIL_NOTICE' && r.status === 'PENDING');
                const sel = document.getElementById('noticeSingleRelease');
                sel.innerHTML = '<option value="">' + (pending.length ? 'Select a pending notice...' : 'No pending email notices') + '</option>' +
                    pending.map(r => '<option value="' + r.release_id + '" data-email="' + escapeHtml(r.claimant_email || '') + '">' +
                        escapeHtml(r.check_number) + ' — ' + escapeHtml(r.claimant_name || '') + '</option>').join('');
            })
            .catch(() => {});
        openModal('m-send-notice');
    });
    document.getElementById('noticeScope')?.addEventListener('change', function () {
        const isBulk = this.value === 'BULK';
        document.getElementById('noticeSingleGroup').style.display = isBulk ? 'none' : '';
        document.getElementById('noticeBulkDeptGroup').style.display = isBulk ? '' : 'none';
        document.getElementById('noticeBulkPeriodGroup').style.display = isBulk ? '' : 'none';
        document.getElementById('noticeBulkPendingWrap').style.display = 'none';
        document.getElementById('noticeBulkPendingBody').innerHTML = '';
        document.getElementById('noticeRecipient').value = '';
    });
    document.getElementById('noticeSingleRelease')?.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        document.getElementById('noticeRecipient').value = opt ? (opt.dataset.email || '') : '';
    });
    document.getElementById('noticeBulkDept')?.addEventListener('change', function () {
        const departmentId = this.value;
        const wrap = document.getElementById('noticeBulkPendingWrap');
        const body = document.getElementById('noticeBulkPendingBody');
        if (!departmentId) { wrap.style.display = 'none'; body.innerHTML = ''; return; }
        fetch(API + '/releases/pending/' + departmentId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                wrap.style.display = '';
                body.innerHTML = rows.length
                    ? rows.map(r => '<tr><td>' + escapeHtml(r.check_number) + '</td><td>' + escapeHtml(r.check_date || '') + '</td><td>' +
                        escapeHtml(r.payee || '') + '</td><td class="text-right">' + peso(r.amount) + '</td></tr>').join('')
                    : '<tr class="fims-empty"><td colspan="4">No pending releases for this department.</td></tr>';
            })
            .catch(() => { body.innerHTML = '<tr class="fims-empty"><td colspan="4">Could not load pending releases.</td></tr>'; });
    });
    document.getElementById('noticeSendBtn')?.addEventListener('click', function () {
        const btn = this;
        const err = document.getElementById('noticeError');
        const isBulk = document.getElementById('noticeScope').value === 'BULK';
        const recipient = document.getElementById('noticeRecipient').value.trim();
        const message = document.getElementById('noticeMessage').value;

        let url, payload;
        if (isBulk) {
            const departmentId = document.getElementById('noticeBulkDept').value;
            if (!departmentId || !recipient) {
                if (err) { err.querySelector('.fims-alert-body').textContent = 'Select a region/department and enter a recipient email.'; err.style.display = ''; }
                return;
            }
            url = API + '/releases/bulk-send-notice';
            payload = { department_id: departmentId, recipient_email: recipient, period: document.getElementById('noticeBulkPeriod').value, message: message };
        } else {
            const releaseId = document.getElementById('noticeSingleRelease').value;
            if (!releaseId || !recipient) {
                if (err) { err.querySelector('.fims-alert-body').textContent = 'Select a pending notice and enter a recipient email.'; err.style.display = ''; }
                return;
            }
            url = API + '/releases/' + releaseId + '/send-notice';
            payload = { recipient_email: recipient, message: message };
        }

        btn.disabled = true;
        fetch(url, { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                if (json.success) {
                    toast(json.message || 'Notice sent.');
                    closeModal('m-send-notice');
                    initReleased();
                } else if (err) {
                    err.querySelector('.fims-alert-body').textContent = json.message || 'Could not send email.';
                    err.style.display = '';
                }
            })
            .catch(function () {
                btn.disabled = false;
                if (err) { err.querySelector('.fims-alert-body').textContent = 'Could not reach the server. Please try again.'; err.style.display = ''; }
            });
    });

    // ── Cancellation ──
    document.getElementById('cancelCheckBtn')?.addEventListener('click', function () {
        document.getElementById('cancelReason').value = '';
        document.getElementById('cancelDate').value = '';
        document.getElementById('cancelDescription').value = '';
        document.getElementById('cancelIssueReplacement').checked = false;
        document.getElementById('cancelReplacementBox').style.display = 'none';
        document.getElementById('cancelNewCheckNo').value = '';
        document.getElementById('cancelNewCheckDate').value = '';
        fetch(API + '/cancellation/lookups', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data && json.data.eligible_checks) ? json.data.eligible_checks : [];
                const sel = document.getElementById('cancelCheckSelect');
                sel.innerHTML = '<option value="">Select a check to cancel...</option>' +
                    rows.map(c => '<option value="' + c.check_id + '">' + escapeHtml(c.check_number) + ' — ' + escapeHtml(c.payee || '') + ' (' + escapeHtml(c.status) + ')</option>').join('');
            })
            .catch(() => {});
        openModal('m-cancel-check');
    });
    document.getElementById('cancelIssueReplacement')?.addEventListener('change', function () {
        document.getElementById('cancelReplacementBox').style.display = this.checked ? 'flex' : 'none';
    });
    document.getElementById('cancelProcessBtn')?.addEventListener('click', function () {
        const payload = {
            check_id: document.getElementById('cancelCheckSelect').value,
            reason: document.getElementById('cancelReason').value,
            cancelled_date: document.getElementById('cancelDate').value,
            description: document.getElementById('cancelDescription').value,
            issue_replacement: document.getElementById('cancelIssueReplacement').checked,
            new_check_no: document.getElementById('cancelNewCheckNo').value,
            new_check_date: document.getElementById('cancelNewCheckDate').value,
        };
        fetch(API + '/cancellation/cancel', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(json => { toast(json.message || (json.success ? 'Cancelled.' : 'Could not process.'), !json.success); if (json.success) { closeModal('m-cancel-check'); initCancel(); } });
    });

    function loadCancellationList() {
        fetch(API + '/cancellation/list', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const tbody = document.querySelector('#cancelTable tbody');
                document.getElementById('cancelListMeta').textContent = rows.length ? (rows.length + ' record(s).') : 'No records.';
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="9">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const replacement = r.replacement_check_id ? (' <span class="fims-badge fims-badge-blue" title="Replaced by check ' + escapeHtml(r.replacement_check_number || r.replacement_check_id) + '">Replaced</span>') : '';
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.check_number) + '</td>' +
                        '<td>' + escapeHtml(r.bank_name || '') + ' — ' + escapeHtml(r.account_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.payee || '') + '</td>' +
                        '<td class="text-right">' + peso(r.amount) + '</td>' +
                        '<td>' + escapeHtml(r.check_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.cancelled_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.reason || '') + '</td>' +
                        '<td class="text-center"><span class="fims-badge">' + escapeHtml(r.status) + '</span>' + replacement + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => {});
    }

    function initCancel() {
        fetch(API + '/cancellation/kpis', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#cancelKpiCards [data-kpi]').forEach(el => {
                    const key = el.dataset.kpi;
                    el.textContent = key === 'cancelled_amount' ? peso(d[key]) : (d[key] ?? 0);
                });
            }).catch(() => {});
        fetch(API + '/cancellation/lookups', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => fillSelect('#cancelFilterBank', json.data ? json.data.bank_accounts : [], 'All Banks'))
            .catch(() => {});
        loadCancellationList();
    }

    // ── Inventory (RAF) — computed from fims.check_register's own booklet
    // groupings (same source as Check Registry's summary view), not a
    // server-rendered static page like TREASURY's original — see
    // CheckProcessingService::rafGroupsSql()'s comment for why this doesn't
    // read fims.check_booklets (exists in the schema, but never actually
    // populated by Add Batch). ──
    const RAF_STOCK_BADGE = { NORMAL: 'fims-badge-green', LOW_STOCK: 'fims-badge-red' };

    function buildRafParams() {
        const params = new URLSearchParams();
        const fiscalYear = document.getElementById('rafFilterYear')?.value;
        const bankAccountId = document.getElementById('rafFilterBank')?.value;
        const lowStock = document.getElementById('rafFilterLowStock')?.value;
        const search = document.getElementById('rafFilterSearch')?.value?.trim();
        if (fiscalYear) params.set('fiscal_year', fiscalYear);
        if (bankAccountId) params.set('bank_account_id', bankAccountId);
        if (lowStock) params.set('low_stock', lowStock);
        if (search) params.set('search', search);
        return params;
    }

    function loadRaf() {
        fetch(API + '/raf/summary', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#rafKpiCards [data-kpi]').forEach(el => { el.textContent = d[el.dataset.kpi] ?? 0; });
            }).catch(() => {});

        const tbody = document.querySelector('#rafTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="11">Loading…</td></tr>';
        fetch(API + '/raf/list?' + buildRafParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="11">No records — check register has no booklets yet.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    const badgeClass = RAF_STOCK_BADGE[r.stock_status] || 'fims-badge-gray';
                    const bankLabel = r.account_name ? escapeHtml(r.account_name) + (r.bank_name ? ' — ' + escapeHtml(r.bank_name) : '') : '—';
                    return '<tr>' +
                        '<td>' + escapeHtml(r.booklet_no || 'No Booklet') + '</td>' +
                        '<td><span class="fims-badge">' + escapeHtml(r.check_series || '—') + '</span></td>' +
                        '<td>' + escapeHtml(r.fiscal_year || '—') + '</td>' +
                        '<td>' + escapeHtml(r.start_check_no) + ' – ' + escapeHtml(r.end_check_no) + '</td>' +
                        '<td class="text-center">' + r.received + '</td>' +
                        '<td class="text-center">' + r.issued + '</td>' +
                        '<td class="text-center">' + r.cancelled + '</td>' +
                        '<td class="text-center">' + r.remaining + '</td>' +
                        '<td>' + escapeHtml(r.last_entry || '—') + '</td>' +
                        '<td class="text-center"><span class="fims-badge ' + badgeClass + '">' + escapeHtml(r.stock_status.replace('_', ' ')) + '</span></td>' +
                        '<td class="text-center">—</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="11">Could not reach the server.</td></tr>'; });
    }

    function initRaf() {
        fetch(API + '/raf/years', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const years = (json && json.data) ? json.data : [];
                const sel = document.getElementById('rafFilterYear');
                sel.innerHTML = '<option value="">Fiscal Year: All</option>' +
                    years.map(y => '<option value="' + y + '">' + y + '</option>').join('');
            })
            .catch(() => {});
        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#rafFilterBank', json.data, 'All Banks')).catch(() => {});
        loadRaf();
    }
    ['rafFilterYear', 'rafFilterBank', 'rafFilterLowStock'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('change', loadRaf);
    });
    document.getElementById('rafFilterSearch')?.addEventListener('keyup', function (e) {
        if (e.key === 'Enter') loadRaf();
    });

    // First tab is active by default on page load.
    initTab('prep');
})();
</script>
