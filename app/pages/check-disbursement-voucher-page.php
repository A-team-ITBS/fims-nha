<?php
/**
 * Check Disbursement Voucher — Check Processing module.
 *
 * Consolidates 3 of the 6 check-processing workflow pages from the TREASURY
 * reference project (_assets/TREASURY/app/pages/) into one tabbed page:
 *   1. Check Preparation     — issue checks against approved DVs
 *   2. Released to Claimants — direct/email release to payees
 *   3. Released to Regions/Depts — release to originating offices
 *
 * A second top-level tab, "FTLA", is a full embed of app/pages/ftla-page.php
 * (its own complete header/table/New FTLA wizard, not a re-port) — checks
 * are often funded by an FTLA fund transfer, so Treasury can reach both
 * without leaving this module. ftla-page.php is entirely self-contained
 * (its own IIFE-free globals, all ftlm_/ftlreq_-prefixed, plus
 * window._ftlaApp) and was already designed to be <?php include()?>'d
 * as-is elsewhere (views/FtlaPage.php does the same), so it's included
 * verbatim rather than copied — same convention as reusing
 * readone/print-preview-readone.php.
 *
 * NOT here: Check Registry (blank check stock/booklets inventory) — split
 * out into its own standalone page, app/pages/check-registry-page.php (see
 * that file's docblock), reusing the same backend. Inventory (RAF, Report
 * of Accountable Forms) tab was removed — its backend
 * (CheckProcessingService::getRafSummary()/getRafList()/getRafYears(), routes
 * under /api/check-processing/raf/*) is left in place but currently has no
 * frontend caller. Cancellation & Replacements tab was also removed — its
 * action (cancelCheck()) is now a per-row "Cancel" button in Check
 * Preparation (see openCancelCheckModal()), and its own list view is gone
 * entirely now that Check Preparation's "Cancelled" status pill shows the
 * same checks with a Reason column (getChecksList()'s LEFT JOIN to
 * fims.check_cancellations) — getCancellationKpis()/getCancellationList()
 * are left in place but have no frontend caller either. Also NOT ported:
 * check_disbursement_ftla_dashboard.php — it's a combined check+FTLA
 * reporting dashboard, not a check-processing workflow page, and half its
 * widgets depend on the separate FTLA/fund-transfer module.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-tabs-underline, fims-modal-overlay, etc.) match the real classes
 * defined in _shared/css/nha-fims.css (merged in from the TREASURY reference
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

$cpCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css');
$cpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-js/fims-treasury.js');
?>
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= $cpCssVer ?>" />
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" defer></script>
<script src="/app/fims-js/fims-treasury.js?v=<?= $cpJsVer ?>" defer></script>
<!-- Required by the embedded FTLA tab (app/pages/ftla-page.php) — its own
     head assets, duplicated here since that file is included mid-page
     rather than as a full standalone page render. -->

<div class="fims-wrap" id="cpModule">

    <!-- same banner as User Access: app-page-header (styled by the global _shared/css/nha-fims.css) -->
    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-money-check-alt"></i></div>
        <div>
            <h1 class="app-page-title">Check Disbursement Voucher</h1>
            <p class="app-page-subtitle">Check preparation, release, and cancellation — Disbursing Section</p>
        </div>
    </div>

    <div class="fims-tabs-underline mb-3" id="cpTabNav">
        <button class="fims-tab-underline active" data-cp-tab="prep">Check Preparation</button>
        <button class="fims-tab-underline" data-cp-tab="ftla">FTLA Preparation</button>
    </div>

    <div id="cpPrep">
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
            <?php foreach (["" => "All", "PREPARED" => "Prepared", "RELEASED_PENDING" => "For Release", "RELEASED_DONE" => "Released", "STALE" => "Stale", "CANCELLED" => "Cancelled"] as $val => $label): ?>
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
                            <th class="prep-col-claimant">Claimant</th>
                            <th>Bank / Account</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="prep-col-reason">Reason</th>
                            <th class="text-center" style="width:340px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="12">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB: FTLA — full embed of app/pages/ftla-page.php (see this file's
         docblock). Its own #ftla-root/.nha-root wrapper, header, and New
         FTLA wizard all come along as-is; only hidden/shown via display,
         same as cpPrep.
    ══════════════════════════════════════════════ -->
    <div id="cpFtla" style="display:none">
        <?php include __DIR__ . '/ftla-page.php'; ?>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Prepare Check (primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-prepare-check" style="display:none;">
<<<<<<< HEAD
        <div class="fims-modal-dialog" style="max-width:1160px;width:95vw;">
=======
        <div class="fims-modal-dialog u-modal-md">
>>>>>>> 33ce84f4f241f54e59e521b27a33e5fa2136f59e
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
                    <div id="prepLowStockAlert" class="fims-alert fims-alert-warning prep-low-stock-pulse" style="display:none;">
                        <div class="fims-alert-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="fims-alert-body"><strong>Low check stock!</strong> Only <span id="prepLowStockCount">0</span> unused check(s) left for this bank account — register a new batch in Check Registry soon.</div>
                    </div>
                    <div style="display:flex;gap:20px;align-items:center;">
                        <div style="flex:1 1 420px;min-width:380px;">
                            <div class="row g-2">
                                <div class="col-md-6"><label class="fims-label">DV Number <span class="text-danger">*</span></label>
                                    <select class="fims-select" id="prepDvSelect"><option value="">Select a DV...</option></select>
                                </div>
                                <div class="col-md-6"><label class="fims-label">Program</label><input type="text" class="fims-input" id="prepProgram" disabled placeholder="—"></div>
                                <div class="col-md-6"><label class="fims-label">Payee</label><input type="text" class="fims-input" id="prepPayee" disabled></div>
                                <div class="col-md-6"><label class="fims-label">Bank Account <span class="text-danger">*</span></label>
                                    <select class="fims-select" id="prepBankAccount"><option value="">Select bank account...</option></select>
                                </div>
                                <div class="col-md-6"><label class="fims-label">SARO Reference</label><input type="text" class="fims-input" id="prepSaro" disabled placeholder="— (no SARO linked to this DV)"></div>
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
                        <div style="flex:0 0 661px;max-width:100%;">
                            <label class="fims-label">Live Preview</label>
                            <div id="prepLivePreviewBox" style="width:661px;max-width:100%;height:245px;overflow:hidden;border:1px solid var(--fims-border,#dde3ea);border-radius:6px;background:#fff;">
                                <div id="prepLivePreview" style="transform:scale(0.85);transform-origin:top left;width:777.6px;"></div>
                            </div>
                            <div style="font-size:11px;color:var(--fims-text-subtle,#8a8f9b);margin-top:4px;text-align:center;">Updates as you fill in the form.</div>
                        </div>
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
         cancelCheck()); this is for a manual correction that nothing else
         in this module sets on its own.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-set-check-status" style="display:none;">
        <div class="fims-modal-dialog u-modal-sm">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Set Check Status</div><p class="fims-modal-subtitle" id="setStatusSubtitle" style="margin:0;font-size:12px;color:var(--fims-text-subtle);"></p></div>
                    <button class="fims-modal-close" data-close="m-set-check-status">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <label class="fims-label">Status <span class="text-danger">*</span></label>
                    <select class="fims-select" id="setStatusSelect">
                        <option value="PREPARED">Prepared</option>
                        <option value="RELEASED">For Release</option>
                        <option value="STALE">Stale</option>
                        <option value="CANCELLED">Cancelled</option>
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
        <div class="fims-modal-dialog u-modal-lg">
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
        <div class="fims-modal-dialog u-modal-sm">
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
        <div class="fims-modal-dialog u-modal-md">
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
         MODAL: Send Notice — opened from Check Preparation's per-row "Send
         Notice" action (see openSendNoticeModal()), not from the For
         Released tab (its own toolbar button was removed; Check Prep is now
         the only entry point). Scope toggle swaps between a single
         Email-Notice release (claimant case, pre-filled by the row that
         opened it) and a bundled monthly notice to a region/department.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-send-notice" style="display:none;">
        <div class="fims-modal-dialog u-modal-md">
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
                        <div class="col-md-6"><label class="fims-label">Attach Annex <span class="text-muted" style="font-weight:400;">(optional, multiple allowed)</span></label><input type="file" class="fims-input" id="noticeAttachment" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx" multiple></div>
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
        <div class="fims-modal-dialog u-modal-sm">
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

/* Claimant/Reason columns are only meaningful for one status pill each
   (Claimant: For Release/Released; Reason: Cancelled) — hidden everywhere
   else via a class toggled on #prepChecksTable in buildPrepParams()'s
   caller, instead of showing mostly-empty columns under "All". */
#prepChecksTable:not(.show-claimant-col) .prep-col-claimant { display: none; }
#prepChecksTable:not(.show-reason-col) .prep-col-reason { display: none; }

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

/* Low-stock alert in the Prepare Check modal — a looping pulse on the
   border/shadow (not the layout, so neighboring fields never shift) to
   stay noticeable without being distracting. Respects reduced-motion. */
@keyframes prepLowStockPulse {
    0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, .35); }
    50%      { box-shadow: 0 0 0 7px rgba(220, 38, 38, 0); }
}
.prep-low-stock-pulse { animation: prepLowStockPulse 1.6s ease-in-out infinite; }
@media (prefers-reduced-motion: reduce) {
    .prep-low-stock-pulse { animation: none; }
}

/* Hides the "FTLA Request" button only within the embedded FTLA tab here —
   the standalone FTLA page (ftla-page.php via views/FtlaPage.php) keeps it;
   this is a scoped override, not an edit to that shared file. */
#cpFtla #btn-new-ftla-request { display: none; }
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

    // Display label for a check's own main status (PREPARED/RELEASED/ENCASHED/...).
    // The check row's status code stays RELEASED for its entire life in that
    // state — it doesn't flip to some other code once the hand-over is
    // actually confirmed — so distinguishing "still waiting" from "done"
    // requires looking at its associated check_releases row's own status
    // too (see getChecksList()'s LATERAL join): RELEASED/ACKNOWLEDGED there
    // means truly handed over ("Released"); PENDING/NOTIFIED/UNCLAIMED (or
    // no release row at all yet) means "For Release". releaseStatus is
    // optional — callers without it (e.g. the Cancellation list, where
    // status is always CANCELLED anyway) just get the plain status code.
    function checkStatusLabel(status, releaseStatus) {
        if (status !== 'RELEASED') return status;
        return ['RELEASED', 'ACKNOWLEDGED'].includes(releaseStatus) ? 'Released' : 'For Release';
    }

    function checkStatusBadgeClass(status, releaseStatus) {
        if (status !== 'RELEASED') return '';
        return ['RELEASED', 'ACKNOWLEDGED'].includes(releaseStatus) ? 'fims-badge-green' : 'fims-badge-gray';
    }

    // Display label for a check_releases row's own status (PENDING/NOTIFIED/
    // RELEASED/ACKNOWLEDGED/UNCLAIMED) — PENDING (not yet handed over) reads as
    // "For Release" here too, matching the KPI card and status filter above.
    function releaseStatusLabel(status) {
        return status === 'PENDING' ? 'For Release' : status;
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

    // ── Top-level module tabs: Check Preparation / FTLA. Just a visibility
    // toggle between the two panes — the embedded FTLA tab (app/pages/
    // ftla-page.php) already loads its own data immediately on page load
    // regardless of which pane is visible, so there's no lazy-init to do
    // here the way the old prep/released/cancel tabs needed. ──
    const cpTabPaneIds = { prep: 'cpPrep', ftla: 'cpFtla' };
    document.querySelectorAll('#cpTabNav [data-cp-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#cpTabNav .fims-tab-underline').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const tab = this.dataset.cpTab;
            Object.entries(cpTabPaneIds).forEach(function ([key, id]) {
                document.getElementById(id).style.display = key === tab ? '' : 'none';
            });
        });
    });

    // Status tabs (Check Preparation) — defaults to "All" rather than
    // TREASURY's PREPARED default, so previously-made checks (anything that
    // has since moved to Released/Cancelled/etc.) stay visible here instead
    // of silently dropping out of view the moment their status changes.
    function updateBatchPrintVisibility(status) {
        const btn = document.getElementById('prepBatchPrintBtn');
        if (btn) btn.style.display = status === 'PREPARED' ? '' : 'none';
    }
    // Claimant only means anything under the two release-related pills;
    // Reason only means anything under Cancelled — hide both elsewhere
    // (including "All") rather than show mostly-empty columns.
    function updatePrepColumnVisibility(status) {
        const table = document.getElementById('prepChecksTable');
        if (!table) return;
        table.classList.toggle('show-claimant-col', status === 'RELEASED_PENDING' || status === 'RELEASED_DONE');
        table.classList.toggle('show-reason-col', status === 'CANCELLED');
    }
    document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            updateBatchPrintVisibility(this.dataset.status);
            updatePrepColumnVisibility(this.dataset.status);
            loadPrepChecks();
        });
    });
    updateBatchPrintVisibility('');
    updatePrepColumnVisibility('');
    document.getElementById('prepFilterBtn')?.addEventListener('click', loadPrepChecks);
    document.getElementById('prepResetBtn')?.addEventListener('click', function () {
        ['prepFilterCheckNo', 'prepFilterDvNo', 'prepFilterPayee', 'prepFilterBank', 'prepFilterDateFrom', 'prepFilterDateTo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(b => b.classList.toggle('active', b.dataset.status === ''));
        updateBatchPrintVisibility('');
        updatePrepColumnVisibility('');
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
        // RELEASED_PENDING/RELEASED_DONE are UI-only pill keys, not real check
        // status codes — both are status=RELEASED underneath (see
        // syncCheckStatusFromRelease()'s comment), split apart server-side by
        // the associated release row's own status via release_phase.
        const activeStatus = document.querySelector('#prepStatusTabs .cp-status-tab.active')?.dataset.status || '';
        if (activeStatus === 'RELEASED_PENDING') {
            params.set('status', 'RELEASED');
            params.set('release_phase', 'PENDING');
        } else if (activeStatus === 'RELEASED_DONE') {
            params.set('status', 'RELEASED');
            params.set('release_phase', 'DONE');
        } else if (activeStatus) {
            params.set('status', activeStatus);
        }
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
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="12">No records.</td></tr>';
                    return;
                }
                // Under "All" (no status pill selected), Actions only shows
                // View/Set Status — Print, Mark as Released, Send Notice, and
                // Cancel are status-specific actions that only make sense once
                // the list is actually narrowed to the pill they belong to.
                const isAllFilter = !(document.querySelector('#prepStatusTabs .cp-status-tab.active')?.dataset.status || '');
                tbody.innerHTML = rows.map(function (r, i) {
                    const printedBadge = r.printed_at ? ' <span class="fims-badge fims-badge-blue" title="Printed ' + escapeHtml(r.printed_at) + '"><i class="fas fa-print"></i></span>' : '';
                    // "For Release" (status RELEASED) with a release row still
                    // short of RELEASED/ACKNOWLEDGED — Mark as Released confirms
                    // the hand-over in place. Send Notice is offered for any
                    // "For Release" row regardless of release_type (not just
                    // EMAIL_NOTICE) — the recipient email just comes pre-filled
                    // when the release already has one, and is typed in by hand
                    // otherwise (e.g. a DIRECT release created via Set Status).
                    const notYetReleased = r.release_id && !['RELEASED', 'ACKNOWLEDGED'].includes(r.release_status);
                    const canMarkReleased = !isAllFilter && notYetReleased;
                    const canSendNotice = !isAllFilter && notYetReleased;
                    const canCancel = !isAllFilter && !['CANCELLED', 'VOIDED'].includes(r.status);
                    const canPrint = !isAllFilter && r.status === 'PREPARED';
                    const reasonCell = r.cancel_reason
                        ? escapeHtml(r.cancel_reason) + (r.replacement_check_number ? ' <span class="fims-badge fims-badge-blue" title="Replaced by check ' + escapeHtml(r.replacement_check_number) + '">Replaced</span>' : '')
                        : '—';
                    return '<tr data-row-check-id="' + r.check_id + '">' +
                        '<td><input type="checkbox" class="prep-row-check" data-check-id="' + r.check_id + '"></td>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.check_number) + printedBadge + '</td>' +
                        '<td>' + escapeHtml(r.check_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.dv_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.payee || '') + '</td>' +
                        '<td class="prep-claimant-cell prep-col-claimant">' + escapeHtml(r.claimant_name || '—') + '</td>' +
                        '<td>' + escapeHtml(r.account_name || '') + '</td>' +
                        '<td class="text-right">' + peso(r.amount) + '</td>' +
                        '<td class="text-center prep-status-cell"><span class="fims-badge ' + checkStatusBadgeClass(r.status, r.release_status) + '">' + escapeHtml(checkStatusLabel(r.status, r.release_status)) + '</span></td>' +
                        '<td class="prep-reason-cell prep-col-reason" title="' + escapeHtml(r.cancel_reason_description || '') + '">' + reasonCell + '</td>' +
                        '<td class="text-center prep-actions-cell">' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-view-check="' + r.check_id + '" title="View"><i class="fas fa-eye mr-1"></i>View</button> ' +
                            (canPrint ? '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-view-check="' + r.check_id + '" title="Print"><i class="fas fa-print mr-1"></i>Print</button> ' : '') +
                            (canMarkReleased ? '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-mark-released="' + r.release_id + '" title="Mark as Released"><i class="fas fa-hand-holding-usd mr-1"></i>Mark as Released</button> ' : '') +
                            (canSendNotice ? '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-send-notice="' + r.release_id + '" title="Send Notice"><i class="fas fa-envelope mr-1"></i>Send Notice</button> ' : '') +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-set-status="' + r.check_id + '" title="Set Status"><i class="fas fa-flag mr-1"></i>Set Status</button> ' +
                            (canCancel ? '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-cancel-check="' + r.check_id + '" title="Cancel Check"><i class="fas fa-ban mr-1"></i>Cancel</button>' : '') +
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
                tbody.querySelectorAll('[data-mark-released]').forEach(function (btn) {
                    btn.addEventListener('click', function () { markPrepCheckReleased(this.dataset.markReleased, this); });
                });
                tbody.querySelectorAll('[data-send-notice]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        const row = btn.closest('tr');
                        const checkId = row ? row.dataset.rowCheckId : null;
                        const r = checkId ? prepChecksById[checkId] : null;
                        openSendNoticeModal(this.dataset.sendNotice, r ? r.check_number : '', r ? r.claimant_name : '', r ? r.claimant_email : '');
                    });
                });
                tbody.querySelectorAll('[data-cancel-check]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openCancelCheckModal(this.dataset.cancelCheck); });
                });
                tbody.querySelectorAll('.prep-row-check').forEach(function (cb) {
                    cb.checked = prepSelectedChecks.has(cb.dataset.checkId);
                    cb.addEventListener('change', function () {
                        if (this.checked) prepSelectedChecks.add(this.dataset.checkId);
                        else prepSelectedChecks.delete(this.dataset.checkId);
                    });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="12">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('prepSelectAll')?.addEventListener('change', function () {
        const checked = this.checked;
        document.querySelectorAll('.prep-row-check').forEach(function (cb) {
            cb.checked = checked;
            if (checked) prepSelectedChecks.add(cb.dataset.checkId);
            else prepSelectedChecks.delete(cb.dataset.checkId);
        });
    });

    // ── Mark as Released / Send Notice — per-row shortcuts for a "For
    // Released" check, acting on its associated check_releases row (see
    // getChecksList()'s LATERAL join). Mark as Released reuses the same
    // generic update-status endpoint the For Release tab's own action
    // does; Send Notice opens the shared m-send-notice modal pre-filled for
    // this one release (that modal's Send Notice toolbar button in the For
    // Released tab was removed — this is now the only entry point). ──
    function markPrepCheckReleased(releaseId, btn) {
        btn.disabled = true;
        fetch(API + '/releases/' + releaseId + '/update-status', {
            method: 'POST',
            headers: authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ status: 'RELEASED' }),
        })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? 'Marked as Released.' : 'Could not update release.'), !json.success);
                if (!json.success) { btn.disabled = false; return; }
                loadPrepChecks();
            })
            .catch(function () { btn.disabled = false; toast('Could not reach the server.', true); });
    }

    // Populates the Bulk scope's department picker from
    // GET /check-processing/releases/departments — fetched fresh each time
    // the modal opens rather than cached, same reasoning as the other
    // lookups on this page.
    function loadNoticeBulkDepartments() {
        const sel = document.getElementById('noticeBulkDept');
        if (!sel) return;
        sel.innerHTML = '<option value="">Loading...</option>';
        fetch(API + '/releases/departments', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                sel.innerHTML = '<option value="">Select...</option>' +
                    rows.map(d => '<option value="' + d.department_id + '">' + escapeHtml(d.department_name) +
                        (d.department_acronym ? ' (' + escapeHtml(d.department_acronym) + ')' : '') + '</option>').join('');
            })
            .catch(() => { sel.innerHTML = '<option value="">Failed to load</option>'; });
    }

    function openSendNoticeModal(releaseId, checkNumber, claimantName, claimantEmail) {
        const err = document.getElementById('noticeError');
        if (err) err.style.display = 'none';
        document.getElementById('noticeMessage').value = '';
        document.getElementById('noticeAttachment').value = '';
        document.getElementById('noticeBulkPeriod').value = '';
        document.getElementById('noticeBulkPendingWrap').style.display = 'none';
        document.getElementById('noticeBulkPendingBody').innerHTML = '';
        document.getElementById('noticeScope').value = 'SINGLE';
        document.getElementById('noticeScope').dispatchEvent(new Event('change'));
        loadNoticeBulkDepartments();
        const sel = document.getElementById('noticeSingleRelease');
        sel.innerHTML = '<option value="' + releaseId + '" data-email="' + escapeHtml(claimantEmail || '') + '">' +
            escapeHtml(checkNumber || '') + ' — ' + escapeHtml(claimantName || '') + '</option>';
        sel.value = releaseId;
        document.getElementById('noticeRecipient').value = claimantEmail || '';
        openModal('m-send-notice');
    }

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
        Object.values(prepChecksById).forEach(function (r) { if (r.claimant_name) names.add(r.claimant_name); });
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
        const cssHref = new URL('/_shared/css/nha-fims.css', window.location.origin).href;
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
                '<td class="text-right">' + peso(c.amount) + '</td><td class="text-center"><span class="fims-badge ' + checkStatusBadgeClass(c.status, c.release_status) + '">' + escapeHtml(checkStatusLabel(c.status, c.release_status)) + '</span></td></tr>';
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

    // Builds a buildCheckFragment()-shaped object straight from whatever's
    // currently in the Prepare Check form, no save required (dv may be null
    // before a DV is picked; every field below tolerates that).
    function getPrepFormCheckData() {
        const dv = prepDvsById[document.getElementById('prepDvSelect').value];
        const ba = prepBankAccountsById[document.getElementById('prepBankAccount').value] || {};
        return {
            bank_name: ba.bank_name || '',
            account_number: ba.account_number || '',
            account_name: ba.account_name || '',
            check_number: document.getElementById('prepCheckNumber').value || '',
            check_date: document.getElementById('prepCheckDate').value || '',
            payee: document.getElementById('prepPayee').value || (dv ? dv.claimant_name : '') || '',
            amount: dv ? (parseFloat(dv.net_amount) || 0) : 0,
            dv_number: dv ? dv.dv_no : '',
            signatory1_name: document.getElementById('prepSig1Name').value || '',
            signatory1_designation: document.getElementById('prepSig1Desig').value || '',
            signatory2_name: document.getElementById('prepSig2Name').value || '',
            signatory2_designation: document.getElementById('prepSig2Desig').value || '',
        };
    }

    // Inline preview — re-rendered on every field change so it always
    // reflects the current form state. Scaled down slightly (0.85x — see
    // #prepLivePreview's inline transform) from the same .nha-chk the print
    // agent uses; #prepLivePreviewBox still scrolls horizontally as a
    // fallback if the modal is ever narrower than that scaled width.
    function renderPrepLivePreview() {
        const inner = document.getElementById('prepLivePreview');
        if (inner) inner.innerHTML = buildCheckFragment(getPrepFormCheckData(), {});
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
    let prepBankAccountsById = {};
    function openPrepareCheckModal() {
        const err = document.getElementById('prepModalError');
        if (err) err.style.display = 'none';
        document.getElementById('prepCashCheck').style.display = 'none';
        document.getElementById('prepLowStockAlert').style.display = 'none';
        document.getElementById('prepPayee').value = '';
        document.getElementById('prepProgram').value = '';
        document.getElementById('prepNetAmount').value = '';
        document.getElementById('prepSaro').value = '';
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
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                prepBankAccountsById = {};
                rows.forEach(function (ba) { prepBankAccountsById[ba.bank_account_id] = ba; });
                fillSelect('#prepBankAccount', rows, 'Select bank account...');
            })
            .catch(() => {});

        openModal('m-prepare-check');
        renderPrepLivePreview();
    }

    document.getElementById('prepDvSelect')?.addEventListener('change', function () {
        const dv = prepDvsById[this.value];
        document.getElementById('prepPayee').value = dv ? (dv.claimant_name || '') : '';
        document.getElementById('prepProgram').value = dv ? (dv.program_names || '') : '';
        document.getElementById('prepNetAmount').value = dv ? peso(dv.net_amount) : '';
        verifyPrepCash();
        prepAutoFillBankFromSaro(dv ? dv.dv_no : null);
        renderPrepLivePreview();
    });

    ['prepCheckDate', 'prepSig1Name', 'prepSig1Desig', 'prepSig2Name', 'prepSig2Desig'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', renderPrepLivePreview);
    });

    // Shows the DV's linked SARO (fims.fund_tracking.dv_id — see DisbursementVoucherService::
    // linkSaro() / CheckProcessingService::getSaroForDv()) and auto-fills the Bank Account select
    // from its bank_account_id, same "auto-fill, not lock" convention ftlm_autoFillRfaFromDv() uses
    // in ftla-form.php: only sets the Bank Account when it's still empty (openPrepareCheckModal()
    // always resets it, so this only ever wins on a fresh pick, never stomping an account the
    // preparer already chose for this session) and only when the select actually carries that
    // option. The SARO Reference field itself is always refreshed/cleared regardless.
    function prepAutoFillBankFromSaro(dvNo) {
        const sel = document.getElementById('prepBankAccount');
        const saroField = document.getElementById('prepSaro');
        if (saroField) saroField.value = '';
        if (sel) sel.title = '';
        if (!dvNo) return;
        fetch(API + '/dv-saro?dv_no=' + encodeURIComponent(dvNo), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const saro = (json && json.success) ? json.data : null;
                if (!saro) return;
                const ref = saro.saro_id || ('#' + saro.id);
                if (saroField) {
                    saroField.value = ref + (saro.bank_name ? ' — ' + saro.bank_name : '') + (saro.account_number ? ' (' + saro.account_number + ')' : '');
                }
                if (!saro.bank_account_id || !sel) return;
                sel.title = 'From linked SARO ' + ref;
                if (!sel.value && [...sel.options].some(o => o.value === String(saro.bank_account_id))) {
                    sel.value = String(saro.bank_account_id);
                    sel.dispatchEvent(new Event('change'));
                }
            })
            .catch(() => {});
    }

    // Mirrors REGISTRY_LOW_STOCK_THRESHOLD in check-registry-page.php /
    // CheckProcessingService::RAF_LOW_STOCK_THRESHOLD — same "10 or fewer
    // unused checks left" definition of low stock, applied here too so
    // Treasury gets warned about it right where they're about to use one up,
    // not just when they happen to visit the Registry.
    const PREP_LOW_STOCK_THRESHOLD = 10;

    document.getElementById('prepBankAccount')?.addEventListener('change', function () {
        const checkNoSel = document.getElementById('prepCheckNumber');
        const lowStockAlert = document.getElementById('prepLowStockAlert');
        const bankAccountId = this.value;
        verifyPrepCash();
        checkNoSel.value = '';
        lowStockAlert.style.display = 'none';
        renderPrepLivePreview();
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
                if (numbers.length <= PREP_LOW_STOCK_THRESHOLD) {
                    document.getElementById('prepLowStockCount').textContent = numbers.length;
                    lowStockAlert.style.display = '';
                }
                renderPrepLivePreview();
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

    // "Send Notice" modal — scope toggles between a single Email-Notice
    // release (opened per-row from Check Preparation's "Send Notice" action,
    // see openSendNoticeModal()) and a bundled monthly notice to a
    // department (region case) — see CheckProcessingService::
    // sendReleaseNotice() / sendBulkReleaseNotice().
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

        // multipart/form-data (not JSON) so the optional annex file rides
        // along in the same request — see buildEmailAttachment() server-side.
        const formData = new FormData();
        let url;
        if (isBulk) {
            const departmentId = document.getElementById('noticeBulkDept').value;
            if (!departmentId || !recipient) {
                if (err) { err.querySelector('.fims-alert-body').textContent = 'Select a region/department and enter a recipient email.'; err.style.display = ''; }
                return;
            }
            url = API + '/releases/bulk-send-notice';
            formData.set('department_id', departmentId);
            formData.set('recipient_email', recipient);
            formData.set('period', document.getElementById('noticeBulkPeriod').value);
            formData.set('message', message);
        } else {
            const releaseId = document.getElementById('noticeSingleRelease').value;
            if (!releaseId || !recipient) {
                if (err) { err.querySelector('.fims-alert-body').textContent = 'Select a pending notice and enter a recipient email.'; err.style.display = ''; }
                return;
            }
            url = API + '/releases/' + releaseId + '/send-notice';
            formData.set('recipient_email', recipient);
            formData.set('message', message);
        }
        Array.from(document.getElementById('noticeAttachment').files).forEach(function (f) {
            formData.append('attachment[]', f);
        });

        btn.disabled = true;
        fetch(url, { method: 'POST', headers: authHeaders(), body: formData })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                if (json.success) {
                    toast(json.message || 'Notice sent.');
                    closeModal('m-send-notice');
                    loadPrepChecks();
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

    // ── Cancellation — per-row "Cancel" action from Check Preparation (the
    // Cancellation & Replacements tab was removed; this modal is now opened
    // directly for a specific check rather than from a tab-level button
    // with a free-standing check picker). The picker stays in the modal
    // (still useful to redirect to a different check without re-opening),
    // just pre-selected to the row that was clicked. ──
    function openCancelCheckModal(checkId) {
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
                if (checkId) sel.value = checkId;
            })
            .catch(() => {});
        openModal('m-cancel-check');
    }
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
            .then(json => { toast(json.message || (json.success ? 'Cancelled.' : 'Could not process.'), !json.success); if (json.success) { closeModal('m-cancel-check'); loadPrepChecks(); } });
    });

    initPrep();
})();
</script>
