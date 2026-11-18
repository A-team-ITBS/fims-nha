<?php
/**
 * Fidelity Bond Management module.
 *
 * Implements the workflow from files/07_Fidelity_Bond_Management_Screenshot_
 * Guide.docx (see app/services/FidelityBondService.php's own docblock for
 * the full breakdown) across 4 tabs:
 *   1. Dashboard & Bond Listing — urgency tiles + the full listing, click a
 *      tile to filter; click a "Last CL issued" number to see that letter.
 *   2. Bond Application — region renewal (existing officers whose listing
 *      is Expiring/Expired) + New Accountable Officer, both going straight
 *      to Applied. Office Order issuance is tracked by a different module,
 *      not this one — there is no Office Order section here.
 *   3. CL Upload & Register — encode a BTr Confirmation Letter, add Applied
 *      officers to it with their new Risk No./Approved Amount, and Post —
 *      all composed client-side first and sent to FidelityBondService::
 *      postNewCl() in one call (no DRAFT state, no create-then-add-one-by-
 *      one round trips). Posting closes each officer's old listing into
 *      Bond History and opens their new ACTIVE one (duplicate risk numbers
 *      are rejected against current listings, Bond History, and every
 *      posted CL — all one check, since none of those ever leave
 *      fims.bond_listings, see the service's docblock). The CL Register
 *      (for COA) lists every posted letter.
 *   4. Bond History — closed/replaced listings, strictly read-only.
 *
 * Attachments (application form, proof of payment, scanned CL) are real
 * file uploads, capped at 25MB each — see initFbUpload()'s call sites
 * below. Each field posts to the app-wide POST /api/upload/file
 * (app/routes/file_upload.php + app/services/file_uploadServices.php),
 * which re-enforces the 25MB limit server-side and streams the file to
 * object storage, returning an object key stored as that field's
 * *_file_url value — not a real URL, so any later display of it (e.g. the
 * CL summary's "Open scanned CL" link) must resolve it through
 * FileUploadServices::viewUrlFor() first (see FidelityBondService::
 * getClSummary()). In this dev stack, DO_SPACES_* in .env points at the
 * local MinIO container (docker-compose.yml's "minio" service) rather than
 * real DigitalOcean Spaces — DO_SPACES_ENDPOINT is the Docker-internal
 * address the php container uploads to, DO_SPACES_PUBLIC_ENDPOINT is the
 * host-mapped port a browser can reach for presigned view URLs.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-tabs-underline, fims-modal-overlay, etc.) match app/fims-css/fims.css,
 * same convention as check-disbursement-voucher-page.php.
 */

namespace PHPMaker2024\FIMS;

$fbCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $fbCssVer ?>" />

<div class="fims-wrap" id="fbModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-shield-alt"></i></div>
            <h1>Fidelity Bond Management</h1>
            <p>Bond renewal and BTr Confirmation Letters — Accountable Officers</p>
        </div>
    </div>

    <div class="fims-tabs-underline mb-3" id="fbTabNav">
        <button class="fims-tab-underline active" data-fb-tab="dashboard">Dashboard</button>
        <button class="fims-tab-underline" data-fb-tab="application">Bond Application</button>
        <button class="fims-tab-underline" data-fb-tab="cl">CL Upload &amp; Register</button>
        <button class="fims-tab-underline" data-fb-tab="history">Bond History</button>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 1: DASHBOARD & BOND LISTING
    ══════════════════════════════════════════════ -->
    <div id="fbDashboard">
        <div class="fb-tile-row" id="fbDashKpis">
            <div class="fb-tile fb-kpi-tile" data-dot="red" data-status-filter="EXPIRED">
                <div class="fb-tile-top"><span class="fb-tile-dot fb-dot-red"></span><span class="fb-tile-label">Expired</span></div>
                <div class="fb-tile-value" data-kpi="expired">0</div>
                <div class="fb-tile-sub">Blocked from collections and cash advances</div>
            </div>
            <div class="fb-tile fb-kpi-tile" data-dot="amber" data-status-filter="EXPIRING">
                <div class="fb-tile-top"><span class="fb-tile-dot fb-dot-amber"></span><span class="fb-tile-label">Expiring</span></div>
                <div class="fb-tile-value" data-kpi="expiring">0</div>
                <div class="fb-tile-sub">Pick them in Bond Application</div>
            </div>
            <div class="fb-tile fb-kpi-tile" data-dot="gray" data-status-filter="PENDING_BOND">
                <div class="fb-tile-top"><span class="fb-tile-dot fb-dot-gray"></span><span class="fb-tile-label">Pending Bond</span></div>
                <div class="fb-tile-value" data-kpi="pending_bond">0</div>
                <div class="fb-tile-sub">New officers and SDO year orders</div>
            </div>
            <div class="fb-tile fb-kpi-tile" data-dot="blue" data-status-filter="APPLIED">
                <div class="fb-tile-top"><span class="fb-tile-dot fb-dot-blue"></span><span class="fb-tile-label">Applied</span></div>
                <div class="fb-tile-value" data-kpi="applied">0</div>
                <div class="fb-tile-sub">Post the CL when it arrives</div>
            </div>
            <div class="fb-tile fb-kpi-tile" data-dot="green" data-status-filter="ACTIVE">
                <div class="fb-tile-top"><span class="fb-tile-dot fb-dot-green"></span><span class="fb-tile-label">Bonded</span></div>
                <div class="fb-tile-value" data-kpi="bonded">0</div>
                <div class="fb-tile-sub">Covered and allowed to transact</div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="fbDashRegion" style="width:220px"><option value="">All Regions</option></select>
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="fbDashSearch" placeholder="Find officer" style="width:200px">
                    </div>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="fbDashResetBtn">Show all statuses</button>
                </div>
                <div id="fbDashActiveFilter" style="font-size:12px;color:var(--fims-text-subtle)"></div>
            </div>
        </div>
        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-table-wrap">
                <table class="fims-table" id="fbListingTable">
                    <thead>
                        <tr>
                            <th>Region</th><th>Officer / Type</th><th>Bond No. / Risk No.</th>
                            <th class="text-right">Approved Amount</th><th>Effectivity</th><th>Expiration</th>
                            <th class="text-center">Days Left</th><th>Last CL Issued</th><th>Note</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="10">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 2: BOND APPLICATION
    ══════════════════════════════════════════════ -->
    <div id="fbApplication" style="display:none">
        <div style="display:flex;gap:16px;align-items:stretch;flex-wrap:wrap;">
            <div class="fims-table-panel" style="flex:1.4 1 480px;margin-bottom:0;">
                <div class="fims-panel-header">
                    <div class="fims-panel-title">A. Renew Existing Officers</div>
                </div>
                <div style="padding:14px 16px;">
                    <div class="fims-form-group" style="max-width:320px;">
                        <label class="fims-label">Region</label>
                        <select class="fims-select" id="fbAppRegion"><option value="">All Regions</option></select>
                    </div>
                    <div class="fb-app-officers-list" id="fbAppOfficersList" style="margin-top:10px;max-height:360px;overflow-y:auto;">
                        <div class="fims-empty" style="padding:14px 0;">Loading…</div>
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-md-6"><label class="fims-label">Date filed with BTr <span class="text-danger">*</span></label><input type="date" class="fims-input" id="fbAppDateFiled"></div>
                        <div class="col-md-6"><label class="fims-label">Premium payment reference</label><input type="text" class="fims-input" id="fbAppPremiumRef" placeholder="Deposit slip / ATAP no."></div>
                    </div>
                    <div class="row g-2 mt-1">
                        <div class="col-md-6">
                            <label class="fims-label">Attach application form (max 25MB)</label>
                            <input type="file" class="fims-input" id="fbAppFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            <input type="hidden" id="fbAppFileUrl">
                            <div class="fb-upload-status" id="fbAppFileStatus"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="fims-label">Attach proof of payment (max 25MB)</label>
                            <input type="file" class="fims-input" id="fbAppProofInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            <input type="hidden" id="fbAppProofUrl">
                            <div class="fb-upload-status" id="fbAppProofStatus"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="fims-label">Upload Office Order (max 25MB)</label>
                            <input type="file" class="fims-input" id="fbAppOoFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                            <input type="hidden" id="fbAppOoFileUrl">
                            <div class="fb-upload-status" id="fbAppOoFileStatus"></div>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;gap:12px;margin-top:10px;">
                        <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fbAppSubmitBtn"><i class="fas fa-paper-plane mr-1"></i>Submit application</button>
                        <span id="fbAppHint" style="font-size:12px;color:var(--fims-text-subtle)"></span>
                    </div>
                </div>
            </div>

            <div class="fims-table-panel" style="flex:1 1 340px;margin-bottom:0;">
                <div class="fims-panel-header">
                    <div class="fims-panel-title">B. New Accountable Officer</div>
                </div>
                <div style="padding:14px 16px;">
                    <div class="fims-form-group"><label class="fims-label">First Name <span class="text-danger">*</span></label><input type="text" class="fims-input" id="fbNewOfficerFirstName"></div>
                    <div class="fims-form-group"><label class="fims-label">Middle Name</label><input type="text" class="fims-input" id="fbNewOfficerMiddleName"></div>
                    <div class="fims-form-group"><label class="fims-label">Last Name <span class="text-danger">*</span></label><input type="text" class="fims-input" id="fbNewOfficerLastName"></div>
                    <div class="fims-form-group"><label class="fims-label">Suffix</label><input type="text" class="fims-input" id="fbNewOfficerSuffix" placeholder="Jr., III..."></div>
                    <div class="fims-form-group"><label class="fims-label">Position</label><input type="text" class="fims-input" id="fbNewOfficerPosition"></div>
                    <div class="fims-form-group"><label class="fims-label">Officer Type <span class="text-danger">*</span></label>
                        <select class="fims-select" id="fbNewOfficerType">
                            <option value="COLLECTING_OFFICER">Collecting Officer</option>
                            <option value="DISBURSING_OFFICER">Disbursing Officer</option>
                            <option value="SDO">Special Disbursing Officer (SDO)</option>
                            <option value="CASHIER">Cashier</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div class="fims-form-group"><label class="fims-label">Region <span class="text-danger">*</span></label><select class="fims-select" id="fbNewOfficerRegion"><option value="">Select Region...</option></select></div>
                    <div class="fims-form-group"><label class="fims-label">Accountability Amount</label><input type="text" inputmode="decimal" class="fims-input" id="fbNewOfficerAccountability" placeholder="0.00"></div>
                    <div class="fims-form-group">
                        <label class="fims-label">Upload Office Order (max 25MB)</label>
                        <input type="file" class="fims-input" id="fbNewOfficerOoFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                        <input type="hidden" id="fbNewOfficerOoFileUrl">
                        <div class="fb-upload-status" id="fbNewOfficerOoFileStatus"></div>
                    </div>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm mt-2" id="fbNewOfficerBtn"><i class="fas fa-user-plus mr-1"></i>Add officer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 4: CL UPLOAD & REGISTER
    ══════════════════════════════════════════════ -->
    <div id="fbCl" style="display:none">
        <div class="fims-table-panel">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Encode &amp; Post the Confirmation Letter</div>
            </div>
            <div style="padding:14px 16px;">
                <label class="fims-label">Upload scanned Confirmation Letter</label>
                <div class="fb-cl-dropzone" id="fbClDropzone">
                    <input type="file" id="fbClFileInput" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" style="display:none;">
                    <input type="hidden" id="fbClFileUrl">
                    <div id="fbClDropzoneIdle">
                        <i class="fas fa-cloud-upload-alt fb-cl-dropzone-icon"></i>
                        <div class="fb-cl-dropzone-title">Click to upload, or drag and drop</div>
                        <div class="fb-cl-dropzone-hint">PDF, JPG, or PNG — up to 25MB. We'll read it and fill in the fields below.</div>
                    </div>
                    <div id="fbClDropzoneFile" class="fb-cl-dropzone-file" style="display:none;">
                        <i class="fas fa-file-alt fb-cl-file-icon" id="fbClFileIcon"></i>
                        <div class="fb-cl-file-info">
                            <div class="fb-cl-file-name" id="fbClFileName"></div>
                            <div class="fb-cl-file-status" id="fbClFileStatusText"></div>
                            <div class="fb-cl-progress-track" id="fbClProgressTrack" style="display:none;"><div class="fb-cl-progress-bar"></div></div>
                        </div>
                        <button type="button" class="fb-cl-file-remove" id="fbClFileRemoveBtn" title="Remove"><i class="fas fa-times"></i></button>
                    </div>
                </div>

                <div class="row g-2 mt-3">
                    <div class="col-md-3"><label class="fims-label">CL Number <span class="text-danger">*</span></label><input type="text" class="fims-input" id="fbClNumber" placeholder="CL-2026-0125"></div>
                    <div class="col-md-3"><label class="fims-label">CL Date <span class="text-danger">*</span></label><input type="date" class="fims-input" id="fbClDate"></div>
                    <div class="col-md-3"><label class="fims-label">Region <span class="text-danger">*</span></label><select class="fims-select" id="fbClRegion"><option value="">Select Region...</option></select></div>
                    <div class="col-md-3"><label class="fims-label">New Effectivity Date <span class="text-danger">*</span></label><input type="date" class="fims-input" id="fbClEffectivity"></div>
                </div>
                <div class="row g-2 mt-1">
                    <div class="col-md-3"><label class="fims-label">New Expiration</label><input type="text" class="fims-input" id="fbClExpirationPreview" disabled placeholder="+1 year, auto"></div>
                </div>
                <hr class="mt-3 mb-2">
                <div class="fims-label" style="margin-bottom:8px;">Officers named in the letter <span class="text-danger">*</span></div>
                <div id="fbClMissingOfficerAlert" class="fims-alert fims-alert-warning mb-2" style="display:none;"><div class="fims-alert-body"></div></div>
                <div class="fims-table-wrap mt-2">
                    <table class="fims-table fb-cl-officers-table" id="fbClOfficersTable">
                        <thead>
                            <tr><th>Officer</th><th style="width:200px">New Risk No. (BTr)</th><th class="text-right" style="width:180px">Approved Bond Amount</th><th style="width:210px">New Listing</th><th class="text-center" style="width:48px"></th></tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="5">No officers added yet.</td></tr></tbody>
                    </table>
                </div>

                <div class="fims-alert fims-alert-info mt-3"><div class="fims-alert-body" id="fbClPostSummary">Add at least one officer before posting.</div></div>
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fbClPostBtn" disabled><i class="fas fa-check mr-1"></i>Post Confirmation Letter</button>
                <div id="fbClCreateError" class="fims-alert fims-alert-danger mt-2" style="display:none;"><div class="fims-alert-body"></div></div>
            </div>
        </div>

        <div class="fims-table-panel mt-3">
            <div class="fims-panel-header">
                <div class="fims-panel-title">CL Register (for COA)</div>
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <select class="fims-select fims-input-sm" id="fbRegisterRegion" style="width:200px"><option value="">All Regions</option></select>
                    <div style="display:flex;align-items:center;gap:4px;">
                        <label class="fims-label" style="margin:0;font-size:11px;color:var(--fims-text-subtle);">Period</label>
                        <input type="date" class="fims-input fims-input-sm" id="fbRegisterDateFrom" style="width:140px">
                        <span style="color:var(--fims-text-subtle);">–</span>
                        <input type="date" class="fims-input fims-input-sm" id="fbRegisterDateTo" style="width:140px">
                    </div>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="fbRegisterFilterBtn">Filter</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="fbRegisterExportBtn"><i class="fas fa-file-excel mr-1"></i>Export for COA (.xlsx)</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="fbRegisterTable">
                    <thead>
                        <tr><th>CL No.</th><th>CL Date</th><th>Region</th><th>Accountable Officer</th><th>Risk No.</th><th>Effectivity</th><th>Expiration</th><th class="text-right">Approved Amount</th><th class="text-center" style="width:70px"></th></tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="9">No records.</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 5: BOND HISTORY
    ══════════════════════════════════════════════ -->
    <div id="fbHistory" style="display:none">
        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="fbHistRegion" style="width:220px"><option value="">All Regions</option></select>
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="fbHistSearch" placeholder="Find officer" style="width:200px">
                    </div>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fbHistFilterBtn">Filter</button>
                </div>
            </div>
        </div>
        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-table-wrap">
                <table class="fims-table" id="fbHistoryTable">
                    <thead>
                        <tr>
                            <th>Officer</th><th>Region</th><th>Bond No.</th><th>Risk No.</th>
                            <th class="text-right">Approved Amount</th><th>Effectivity</th><th>Expiration</th><th>Closed By</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="8">No records.</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: confirm submitting (new officer or renewal) with no Office
         Order attached — shared by Section A and Section B, see
         fbOoNoFileProceedAction below
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-oo-no-file" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:440px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div class="fims-modal-title">No Office Order attached</div>
                    <button class="fims-modal-close" data-close="m-oo-no-file">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <p style="margin:0;">You haven't uploaded this officer's Office Order. This is the document that actually authorizes the designation — proceed without it anyway?</p>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-oo-no-file">Cancel, let me attach it</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fbOoNoFileProceedBtn">Proceed without it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: confirm creating a CL draft with no scanned file attached
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-cl-no-file" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:440px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div class="fims-modal-title">No scanned CL attached</div>
                    <button class="fims-modal-close" data-close="m-cl-no-file">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <p style="margin:0;">You haven't uploaded the scanned Confirmation Letter. This document is important for verifying the named officers' accountability — especially a Collecting Officer who is also acting as Cashier. Proceed without it anyway?</p>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-cl-no-file">Cancel, let me attach it</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fbClNoFileProceedBtn">Proceed without it</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: CL Summary (opened from a "Last CL issued" number, or the
         CL Register / Bond History's "View" links)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-cl-summary" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:640px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="fbClSummaryTitle">Confirmation Letter</div></div>
                    <button class="fims-modal-close" data-close="m-cl-summary">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div id="fbClSummaryBody"></div>
                </div>
                <div class="fims-modal-footer">
                    <a href="#" target="_blank" id="fbClSummaryFileLink" class="fims-btn fims-btn-outline fims-btn-sm">Open scanned CL</a>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-cl-summary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="fbToast" style="display:none;position:fixed;bottom:20px;right:20px;z-index:2000;padding:10px 16px;border-radius:8px;color:#fff;font-size:13px;"></div>
</div>

<style>
#fbModule .fims-card-blue, #fbModule .fims-card-green, #fbModule .fims-card-amber,
#fbModule .fims-card-red, #fbModule .fims-card-gray { --fims-card-color: #000E3E; }
#fbModule .fims-btn-primary { background:#000E3E; color:#fff; }
#fbModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#fbModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#fbModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

/* Dashboard status tiles — one flat card per lifecycle status (Expired/
   Expiring/Pending Bond/Applied/Bonded), a colored dot standing in for the
   fims-card-* accent bar used elsewhere, since these are plain status
   counts rather than the rainbow-KPI convention. */
.fb-tile-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
.fb-tile {
    flex: 1 1 180px;
    background: var(--fims-surface, #fff);
    border: 1px solid var(--fims-border, #E4ECF2);
    border-radius: var(--fims-radius, 10px);
    padding: 12px 14px;
    cursor: pointer;
    transition: border-color .15s ease, box-shadow .15s ease;
}
.fb-tile:hover { border-color: #000E3E; box-shadow: 0 1px 4px rgba(0,14,62,.1); }
.fb-tile.fb-active { border-color: #d97706; box-shadow: 0 0 0 1px #d97706; }
.fb-tile-top { display: flex; align-items: center; gap: 6px; margin-bottom: 6px; }
.fb-tile-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.fb-dot-red    { background: #dc2626; }
.fb-dot-amber  { background: #d97706; }
.fb-dot-gray   { background: #6b7280; }
.fb-dot-blue   { background: #2563eb; }
.fb-dot-green  { background: #16a34a; }
.fb-tile-label { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: var(--fims-text-subtle, #62657E); }
.fb-tile-value { font-size: 26px; font-weight: 800; color: #1A1B21; line-height: 1.2; }
.fb-tile-sub { font-size: 11px; color: var(--fims-text-subtle, #62657E); margin-top: 2px; }
.fb-upload-status { font-size: 12px; color: var(--fims-text-subtle, #62657E); margin-top: 4px; min-height: 16px; }
.fb-upload-status.fb-upload-ok { color: #15803d; }
.fb-upload-status.fb-upload-error { color: #dc2626; }

/* "Officers named in the letter" table (CL draft) */
.fb-cl-officers-table tbody tr:nth-child(even) { background: var(--fims-surface-alt, #F7F9FC); }
.fb-cl-officers-table td { vertical-align: middle; padding-top: 10px; padding-bottom: 10px; }
.fb-cl-officer-name { font-weight: 600; color: #1A1B21; }
.fb-cl-officer-sub { font-size: 11px; color: var(--fims-text-subtle, #62657E); margin-top: 1px; }
.fb-cl-new-listing-range { font-size: 12px; color: #1A1B21; white-space: nowrap; }
.fb-app-officers-list { border: 1px solid var(--fims-border, #E4ECF2); border-radius: var(--fims-radius, 10px); }
.fb-app-officer-item {
    display: flex; align-items: center; gap: 12px;
    padding: 11px 14px; cursor: pointer;
    border-bottom: 1px solid var(--fims-border, #E4ECF2);
    transition: background .15s ease;
}
.fb-app-officer-item:last-child { border-bottom: none; }
.fb-app-officer-item:nth-child(even) { background: var(--fims-surface-alt, #F7F9FC); }
.fb-app-officer-item:hover { background: var(--afims-primary-soft, #E6EBF6); }
.fb-app-officer-item input[type="checkbox"] { flex-shrink: 0; width: 15px; height: 15px; margin: 0; }
.fb-app-officer-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1 1 auto; }
.fb-app-officer-name { font-size: 13px; font-weight: 600; color: #1A1B21; }
.fb-app-officer-sub { font-size: 11.5px; color: var(--fims-text-subtle, #62657E); }
.fb-app-new-expiry { color: var(--afims-primary-text, #0035A9); font-weight: 600; }
.fb-app-status-pill {
    flex-shrink: 0; display: flex; align-items: center; justify-content: center;
    min-width: 170px; font-size: 12px; font-weight: 600;
    padding: 9px 14px; border-radius: 10px; text-align: center;
    background: var(--afims-primary-soft, #E6EBF6); color: var(--afims-primary-text, #0035A9);
}
.fb-cl-remove-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px; border: none;
    background: transparent; color: var(--fims-text-subtle, #62657E);
    cursor: pointer; transition: background .15s ease, color .15s ease;
}
.fb-cl-remove-btn:hover { background: #FEE2E2; color: #dc2626; }

/* Confirmation Letter scan dropzone */
.fb-cl-dropzone {
    border: 2px dashed var(--fims-border, #E4ECF2);
    border-radius: var(--fims-radius-lg, 12px);
    padding: 22px 18px;
    cursor: pointer;
    transition: border-color .15s ease, background .15s ease;
    background: var(--fims-surface, #fff);
}
.fb-cl-dropzone:hover,
.fb-cl-dropzone.fb-cl-dropzone-drag { border-color: #000E3E; background: var(--afims-primary-soft, #E6EBF6); }
#fbClDropzoneIdle { text-align: center; }
.fb-cl-dropzone-icon { display: block; font-size: 30px; color: #000E3E; margin-bottom: 8px; }
.fb-cl-dropzone-title { font-size: 13.5px; font-weight: 600; color: #1A1B21; }
.fb-cl-dropzone-hint { font-size: 11.5px; color: var(--fims-text-subtle, #62657E); margin-top: 3px; }
.fb-cl-dropzone-file { display: flex; align-items: center; gap: 12px; cursor: default; }
.fb-cl-file-icon { font-size: 26px; color: #000E3E; flex-shrink: 0; width: 28px; text-align: center; }
.fb-cl-file-icon.fb-cl-spin { animation: fb-cl-spin 1s linear infinite; }
@keyframes fb-cl-spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
.fb-cl-file-info { flex: 1 1 auto; min-width: 0; }
.fb-cl-file-name { font-size: 13px; font-weight: 600; color: #1A1B21; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fb-cl-file-status { font-size: 11.5px; color: var(--fims-text-subtle, #62657E); margin-top: 2px; }
.fb-cl-file-status.fb-cl-status-ok { color: #15803d; }
.fb-cl-file-status.fb-cl-status-error { color: #dc2626; }
.fb-cl-progress-track { height: 4px; border-radius: 2px; background: var(--fims-border, #E4ECF2); margin-top: 7px; overflow: hidden; }
.fb-cl-progress-bar { height: 100%; width: 35%; border-radius: 2px; background: #000E3E; animation: fb-cl-progress-slide 1.1s ease-in-out infinite; }
@keyframes fb-cl-progress-slide { 0% { transform: translateX(-110%); } 100% { transform: translateX(380%); } }
.fb-cl-file-remove {
    flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px; border-radius: 6px; border: none; background: transparent;
    color: var(--fims-text-subtle, #62657E); cursor: pointer; transition: background .15s ease, color .15s ease;
}
.fb-cl-file-remove:hover { background: #FEE2E2; color: #dc2626; }
@media (prefers-reduced-motion: reduce) {
    .fb-cl-file-icon.fb-cl-spin { animation: none; }
    .fb-cl-progress-bar { animation: none; width: 100%; }
}
</style>

<script>
(function () {
    const API = '/api/fidelity-bond';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function jsonHeaders(extra) {
        return authHeaders(Object.assign({ 'Content-Type': 'application/json' }, extra || {}));
    }
    function toast(msg, isError) {
        const el = document.getElementById('fbToast');
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
    // Amount inputs are plain text (not type="number", which rejects
    // commas outright) so they can show thousands separators while typing.
    function formatAmountDisplay(raw) {
        let s = String(raw ?? '').replace(/[^0-9.]/g, '');
        const dot = s.indexOf('.');
        if (dot !== -1) { s = s.slice(0, dot + 1) + s.slice(dot + 1).replace(/\./g, ''); }
        const parts = s.split('.');
        let intPart = parts[0].replace(/^0+(?=\d)/, '');
        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return parts.length > 1 ? intPart + '.' + parts[1].slice(0, 2) : intPart;
    }
    function parseAmountValue(str) {
        return parseFloat(String(str ?? '').replace(/,/g, '')) || 0;
    }
    // Wires one amount <input> to live comma-formatting as the user types,
    // preserving cursor position relative to the end of the value.
    function initAmountInput(el) {
        if (!el) return;
        el.addEventListener('input', function () {
            const fromEnd = this.value.length - (this.selectionStart ?? this.value.length);
            this.value = formatAmountDisplay(this.value);
            const pos = Math.max(0, this.value.length - fromEnd);
            this.setSelectionRange(pos, pos);
        });
    }
    function openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }

    const FB_UPLOAD_MAX_BYTES = 25 * 1024 * 1024;
    // Wires a <input type="file"> to the app-wide POST /api/upload/file
    // (25MB limit re-enforced server-side) — on success, stores the
    // returned object key (not a real URL) in the paired hidden input.
    function initFbUpload(inputId, hiddenId, statusId) {
        const input = document.getElementById(inputId);
        const hidden = document.getElementById(hiddenId);
        const status = document.getElementById(statusId);
        if (!input) return;
        function setStatus(text, cls) {
            if (!status) return;
            status.textContent = text;
            status.className = 'fb-upload-status' + (cls ? ' ' + cls : '');
        }
        input.addEventListener('change', function () {
            const file = input.files && input.files[0];
            hidden.value = '';
            setStatus('');
            if (!file) return;
            if (file.size > FB_UPLOAD_MAX_BYTES) {
                setStatus(file.name + ' is over the 25MB limit.', 'fb-upload-error');
                input.value = '';
                return;
            }
            setStatus('Uploading ' + file.name + '…');
            input.disabled = true;
            const fd = new FormData();
            fd.append('file', file);
            fetch('/api/upload/file', { method: 'POST', headers: authHeaders(), body: fd })
                .then(r => r.json())
                .then(function (json) {
                    input.disabled = false;
                    if (!json.success) {
                        setStatus(json.message || 'Upload failed.', 'fb-upload-error');
                        input.value = '';
                        return;
                    }
                    hidden.value = json.link;
                    setStatus('Attached: ' + file.name, 'fb-upload-ok');
                })
                .catch(function () {
                    input.disabled = false;
                    setStatus('Could not reach the server.', 'fb-upload-error');
                    input.value = '';
                });
        });
    }
    initFbUpload('fbAppFileInput', 'fbAppFileUrl', 'fbAppFileStatus');
    initFbUpload('fbAppProofInput', 'fbAppProofUrl', 'fbAppProofStatus');
    initFbUpload('fbAppOoFileInput', 'fbAppOoFileUrl', 'fbAppOoFileStatus');
    // Confirmation Letter scan dropzone — uploads the file to storage AND
    // runs it through best-effort local-OCR autofill (no AI/API key
    // involved, see FidelityBondService::extractClFromScan()'s own
    // docblock for why that can be wrong) in parallel, animating the
    // dropzone's icon/progress bar until BOTH finish. Every autofilled
    // field stays editable regardless of what the scan read.
    function fbClFormatBytes(n) {
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return (n / 1024).toFixed(0) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }
    function fbClResetDropzone() {
        document.getElementById('fbClFileInput').value = '';
        document.getElementById('fbClFileUrl').value = '';
        fbExtractedOfficerData = {};
        document.getElementById('fbClDropzoneFile').style.display = 'none';
        document.getElementById('fbClDropzoneIdle').style.display = '';
        document.getElementById('fbClMissingOfficerAlert').style.display = 'none';
    }
    function fbClHandleFile(file) {
        if (!file) return;
        fbExtractedOfficerData = {};
        document.getElementById('fbClFileUrl').value = '';
        document.getElementById('fbClMissingOfficerAlert').style.display = 'none';
        document.getElementById('fbClDropzoneIdle').style.display = 'none';
        document.getElementById('fbClDropzoneFile').style.display = '';
        document.getElementById('fbClFileName').textContent = file.name + ' (' + fbClFormatBytes(file.size) + ')';
        const icon = document.getElementById('fbClFileIcon');
        const statusEl = document.getElementById('fbClFileStatusText');
        const track = document.getElementById('fbClProgressTrack');

        if (file.size > 25 * 1024 * 1024) {
            icon.className = 'fas fa-file-alt fb-cl-file-icon';
            statusEl.textContent = 'That file is over the 25MB limit — choose a smaller scan.';
            statusEl.className = 'fb-cl-file-status fb-cl-status-error';
            track.style.display = 'none';
            return;
        }
        icon.className = 'fas fa-spinner fb-cl-file-icon fb-cl-spin';
        statusEl.textContent = 'Uploading and reading the scan…';
        statusEl.className = 'fb-cl-file-status';
        track.style.display = '';

        let uploadDone = false, extractDone = false, uploadOk = false;
        function checkBothDone() {
            if (!uploadDone || !extractDone) return;
            track.style.display = 'none';
            icon.className = 'fas fa-file-' + (file.type.includes('pdf') ? 'pdf' : 'image') + ' fb-cl-file-icon';
            if (uploadOk && statusEl.className === 'fb-cl-file-status') {
                statusEl.textContent = 'Attached — read and ready.';
                statusEl.className = 'fb-cl-file-status fb-cl-status-ok';
            }
        }

        const uploadFd = new FormData();
        uploadFd.append('file', file);
        fetch('/api/upload/file', { method: 'POST', headers: authHeaders(), body: uploadFd })
            .then(r => r.json())
            .then(function (json) {
                uploadDone = true;
                if (!json.success) {
                    statusEl.textContent = json.message || 'Upload failed.';
                    statusEl.className = 'fb-cl-file-status fb-cl-status-error';
                } else {
                    document.getElementById('fbClFileUrl').value = json.link;
                    uploadOk = true;
                }
                checkBothDone();
            })
            .catch(function () {
                uploadDone = true;
                statusEl.textContent = 'Could not reach the server.';
                statusEl.className = 'fb-cl-file-status fb-cl-status-error';
                checkBothDone();
            });

        const extractFd = new FormData();
        extractFd.append('file', file);
        fetch(API + '/cl/extract', { method: 'POST', headers: authHeaders(), body: extractFd })
            .then(r => r.json())
            .then(function (json) {
                extractDone = true;
                if (!json.success) { checkBothDone(); return; }
                const d = json.data;
                // Logged, not shown — open devtools if a field doesn't come
                // out right and this needs troubleshooting.
                console.log('Fidelity Bond CL scan read:', d);
                if (d.cl_number) document.getElementById('fbClNumber').value = d.cl_number;
                if (d.cl_date) document.getElementById('fbClDate').value = d.cl_date;
                if (d.effectivity_date) {
                    document.getElementById('fbClEffectivity').value = d.effectivity_date;
                    document.getElementById('fbClEffectivity').dispatchEvent(new Event('change'));
                }
                if (d.rc_id_guess) document.getElementById('fbClRegion').value = d.rc_id_guess;

                // A letter can name more than one officer. A row with
                // exactly one unambiguous officer match gets added to the
                // letter below right away (Risk No./Amount prefilled from
                // that same row). The backend already tries to break a
                // same-name tie using the position/station text read off
                // this same row (see FidelityBondService::
                // disambiguateOfficerMatches()) — officer_matches only
                // still has more than one entry here when that genuinely
                // couldn't decide, so each candidate gets its own "Add"
                // button instead of guessing. No match at all just lists
                // the name as unresolved.
                const officers = d.officers || [];
                const missingAlert = document.getElementById('fbClMissingOfficerAlert');
                const needsAttention = [];
                let autoAdded = 0;
                officers.forEach(function (o, idx) {
                    if (!o.officer_name_guess) return;
                    if (o.officer_matches && o.officer_matches.length === 1) {
                        const match = o.officer_matches[0];
                        fbExtractedOfficerData[match.officer_id] = { risk_no: o.risk_no, approved_bond_amount: o.approved_bond_amount };
                        addOfficerToDraftCl(match.officer_id, match.employee_name, o.position_title_guess, null);
                        autoAdded++;
                    } else if (o.officer_matches && o.officer_matches.length > 1) {
                        needsAttention.push({ idx: idx, name: o.officer_name_guess, officer: o, candidates: o.officer_matches });
                    } else {
                        needsAttention.push({ idx: idx, name: o.officer_name_guess, officer: o, candidates: [] });
                    }
                });
                if (autoAdded) {
                    toast(autoAdded + ' officer(s) added to the letter from the scan — double-check Risk No./Amount below.');
                }
                if (needsAttention.length) {
                    missingAlert.querySelector('.fims-alert-body').innerHTML = needsAttention.map(function (n) {
                        if (!n.candidates.length) {
                            const reason = n.officer.ineligible_reason ? n.officer.ineligible_reason + ' — can\'t be added to another letter' : 'no matching officer';
                            return '<div style="margin-bottom:6px;"><strong>' + escapeHtml(n.name) + '</strong> — ' + escapeHtml(reason) + '.</div>';
                        }
                        return '<div style="margin-bottom:6px;"><strong>' + escapeHtml(n.name) + '</strong> — ' + n.candidates.length + ' possible matches, same name:<br>' +
                            n.candidates.map(function (c) {
                                return '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding:3px 0 3px 10px;">' +
                                    '<span>' + escapeHtml(c.employee_name) + (c.position_title ? ' — ' + escapeHtml(c.position_title) : '') + (c.station_name ? ' <span style="color:var(--fims-text-subtle);font-size:11px;">(' + escapeHtml(c.station_name) + ')</span>' : '') + '</span>' +
                                    '<button type="button" class="fims-btn fims-btn-outline fims-btn-sm" data-pick-officer="' + c.officer_id + '" data-pick-idx="' + n.idx + '">Add this one</button>' +
                                    '</div>';
                            }).join('') +
                            '</div>';
                    }).join('');
                    missingAlert.style.display = '';
                    missingAlert.querySelectorAll('[data-pick-officer]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            const n = needsAttention[parseInt(this.dataset.pickIdx, 10)];
                            const match = n.candidates.find(c => String(c.officer_id) === this.dataset.pickOfficer);
                            if (!match) return;
                            fbExtractedOfficerData[match.officer_id] = { risk_no: n.officer.risk_no, approved_bond_amount: n.officer.approved_bond_amount };
                            addOfficerToDraftCl(match.officer_id, match.employee_name, n.officer.position_title_guess, this.closest('div').parentElement);
                        });
                    });
                } else {
                    missingAlert.style.display = 'none';
                }
                checkBothDone();
            })
            .catch(function () { extractDone = true; checkBothDone(); });
    }
    document.getElementById('fbClFileInput')?.addEventListener('change', function () {
        fbClHandleFile(this.files && this.files[0]);
    });
    document.getElementById('fbClDropzoneIdle')?.addEventListener('click', function () {
        document.getElementById('fbClFileInput').click();
    });
    document.getElementById('fbClFileRemoveBtn')?.addEventListener('click', function (e) {
        e.stopPropagation();
        fbClResetDropzone();
    });
    (function () {
        const dz = document.getElementById('fbClDropzone');
        if (!dz) return;
        ['dragenter', 'dragover'].forEach(function (evt) {
            dz.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); dz.classList.add('fb-cl-dropzone-drag'); });
        });
        ['dragleave', 'drop'].forEach(function (evt) {
            dz.addEventListener(evt, function (e) { e.preventDefault(); e.stopPropagation(); dz.classList.remove('fb-cl-dropzone-drag'); });
        });
        dz.addEventListener('drop', function (e) {
            const file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (!file) return;
            document.getElementById('fbClFileInput').files = e.dataTransfer.files;
            fbClHandleFile(file);
        });
    })();
    initFbUpload('fbNewOfficerOoFileInput', 'fbNewOfficerOoFileUrl', 'fbNewOfficerOoFileStatus');

    initAmountInput(document.getElementById('fbNewOfficerAccountability'));
    document.querySelectorAll('#fbModule [data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#fbModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(overlay.id); });
    });
    function fillSelect(selector, items, placeholder) {
        const sel = document.querySelector(selector);
        if (!sel) return;
        sel.innerHTML = '<option value="">' + placeholder + '</option>' +
            (items || []).map(function (r) {
                return '<option value="' + escapeHtml(r.rc_id) + '">' + escapeHtml(r.rc_name) + '</option>';
            }).join('');
    }

    // ── Top-level module tabs ──
    const fbTabPaneIds = { dashboard: 'fbDashboard', application: 'fbApplication', cl: 'fbCl', history: 'fbHistory' };
    document.querySelectorAll('#fbTabNav [data-fb-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#fbTabNav .fims-tab-underline').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            const tab = this.dataset.fbTab;
            Object.entries(fbTabPaneIds).forEach(function ([key, id]) {
                document.getElementById(id).style.display = key === tab ? '' : 'none';
            });
            if (tab === 'dashboard') loadDashboard();
            else if (tab === 'application') initApplicationTab();
            else if (tab === 'cl') initClTab();
            else if (tab === 'history') loadHistory();
        });
    });

    function loadRegionsInto(selectors) {
        fetch(API + '/regions', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                selectors.forEach(function (sel) { fillSelect(sel, json.data, (sel.includes('fbNewOfficerRegion') || sel.includes('fbClLegacyRegion')) ? 'Select Region...' : 'All Regions'); });
            })
            .catch(() => {});
    }

    // ── TAB 1: Dashboard & Bond Listing ─────────────────────────────────
    let fbActiveStatusFilter = '';
    function buildDashParams() {
        const params = new URLSearchParams();
        const region = document.getElementById('fbDashRegion').value;
        const search = document.getElementById('fbDashSearch').value.trim();
        if (region) params.set('region', region);
        if (search) params.set('search', search);
        if (fbActiveStatusFilter) params.set('status_filter', fbActiveStatusFilter);
        return params;
    }
    function loadDashboard() {
        const tbody = document.querySelector('#fbListingTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Loading…</td></tr>';
        fetch(API + '/dashboard?' + buildDashParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = json.data || {};
                const kpis = d.kpis || {};
                document.querySelectorAll('#fbDashKpis [data-kpi]').forEach(function (el) {
                    el.textContent = kpis[el.dataset.kpi] ?? 0;
                });
                const rows = d.listings || [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    const daysLeft = r.days_left;
                    const daysLeftHtml = daysLeft == null ? '—' : ('<span style="color:' + (daysLeft < 0 ? '#dc2626' : daysLeft <= 30 ? '#d97706' : 'inherit') + '">' + daysLeft + '</span>');
                    const lastCl = r.last_cl_id
                        ? '<a href="#" data-view-cl="' + r.last_cl_id + '">' + escapeHtml(r.last_cl_number || ('CL #' + r.last_cl_id)) + '</a>'
                        : 'None yet';
                    return '<tr>' +
                        '<td>' + escapeHtml(r.rc_name || '—') + '</td>' +
                        '<td>' + escapeHtml(r.employee_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.position_title || r.officer_type || '') + '</span></td>' +
                        '<td>' + escapeHtml(r.bond_no || '—') + (r.risk_no ? '<br><span style="font-size:11px;color:var(--fims-text-subtle)">Risk: ' + escapeHtml(r.risk_no) + '</span>' : '') + '</td>' +
                        '<td class="text-right">' + (r.approved_bond_amount ? peso(r.approved_bond_amount) : '—') + '</td>' +
                        '<td>' + escapeHtml(r.effectivity_date || '—') + '</td>' +
                        '<td>' + escapeHtml(r.expiration_date || '—') + '</td>' +
                        '<td class="text-center">' + daysLeftHtml + '</td>' +
                        '<td>' + lastCl + '</td>' +
                        '<td>' + escapeHtml(r.note || '') + '</td>' +
                        '<td class="text-center"><span class="fims-badge">' + escapeHtml(r.status) + '</span></td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-view-cl]').forEach(function (a) {
                    a.addEventListener('click', function (e) { e.preventDefault(); openClSummary(this.dataset.viewCl); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Could not reach the server.</td></tr>'; });
    }
    document.querySelectorAll('.fb-kpi-tile').forEach(function (tile) {
        tile.addEventListener('click', function () {
            document.querySelectorAll('.fb-kpi-tile').forEach(t => t.classList.remove('fb-active'));
            this.classList.add('fb-active');
            fbActiveStatusFilter = this.dataset.statusFilter;
            document.getElementById('fbDashActiveFilter').textContent = 'Filtered: ' + this.querySelector('.fb-tile-label').textContent;
            loadDashboard();
        });
    });
    document.getElementById('fbDashResetBtn')?.addEventListener('click', function () {
        fbActiveStatusFilter = '';
        document.querySelectorAll('.fb-kpi-tile').forEach(t => t.classList.remove('fb-active'));
        document.getElementById('fbDashActiveFilter').textContent = '';
        document.getElementById('fbDashSearch').value = '';
        loadDashboard();
    });
    document.getElementById('fbDashRegion')?.addEventListener('change', loadDashboard);
    document.getElementById('fbDashSearch')?.addEventListener('keydown', function (e) { if (e.key === 'Enter') loadDashboard(); });

    // ── CL Summary modal (shared: Dashboard's "Last CL issued", CL
    // Register's view, Bond History's "Closed By") ──
    function openClSummary(clId) {
        fetch(API + '/cl/' + clId + '/summary', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) { toast(json.message || 'Could not load CL.', true); return; }
                const cl = json.data;
                document.getElementById('fbClSummaryTitle').textContent = cl.cl_number + ' — ' + (cl.rc_name || '');
                const fileLink = document.getElementById('fbClSummaryFileLink');
                if (cl.scanned_file_view_url) { fileLink.href = cl.scanned_file_view_url; fileLink.style.display = ''; }
                else { fileLink.style.display = 'none'; }
                document.getElementById('fbClSummaryBody').innerHTML =
                    '<div class="row g-2">' +
                    '<div class="col-md-6"><strong>CL Date:</strong> ' + escapeHtml(cl.cl_date || '') + '</div>' +
                    '<div class="col-md-6"><strong>Coverage:</strong> ' + escapeHtml(cl.new_effectivity_date || '') + ' to ' + escapeHtml(cl.new_expiration_date || '') + '</div>' +
                    '<div class="col-md-6"><strong>Posted by (user id):</strong> ' + escapeHtml(cl.posted_by ?? '—') + '</div>' +
                    '<div class="col-md-6"><strong>Posted at:</strong> ' + escapeHtml(cl.posted_at || 'Not yet posted') + '</div>' +
                    '<div class="col-md-12"><strong>Total approved amount:</strong> ' + peso(cl.total_approved_amount) + '</div>' +
                    '</div>' +
                    '<div class="fims-table-wrap mt-2"><table class="fims-table"><thead><tr><th>Officer</th><th>New Risk No.</th><th class="text-right">Approved Amount</th></tr></thead><tbody>' +
                    (cl.officers || []).map(function (o) {
                        return '<tr><td>' + escapeHtml(o.employee_name) + '</td><td>' + escapeHtml(o.new_risk_no || '—') + '</td><td class="text-right">' + peso(o.approved_bond_amount) + '</td></tr>';
                    }).join('') + '</tbody></table></div>';
                openModal('m-cl-summary');
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    // ── TAB 2: Bond Application ──────────────────────────────────────────
    let fbAppInitialized = false;
    function initApplicationTab() {
        if (fbAppInitialized) return;
        fbAppInitialized = true;
        loadRegionsInto(['#fbAppRegion', '#fbNewOfficerRegion']);
        loadAppOfficers();
    }
    function loadAppOfficers() {
        // Empty value = "All Regions" — show every officer due for renewal
        // across regions instead of requiring one to be picked first.
        const rcId = document.getElementById('fbAppRegion').value;
        const showingAll = !rcId;
        const list = document.getElementById('fbAppOfficersList');
        list.innerHTML = '<div class="fims-empty" style="padding:14px 0;">Loading…</div>';
        fetch(API + '/officers-for-region/' + encodeURIComponent(rcId || 'ALL'), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { list.innerHTML = '<div class="fims-empty" style="padding:14px 0;">No officers due for renewal' + (showingAll ? '.' : ' in this region.') + '</div>'; return; }
                const today = new Date(); today.setHours(0, 0, 0, 0);
                // Provisional coverage while the real Confirmation Letter
                // (which sets the actual 1-year expiry once posted) is still
                // being processed — today + 90 days, same for every row.
                const newExpiry = new Date(today); newExpiry.setDate(newExpiry.getDate() + 90);
                const newExpiryStr = newExpiry.toISOString().slice(0, 10);
                list.innerHTML = rows.map(function (r) {
                    let daysNote = '';
                    if (r.expiration_date) {
                        const days = Math.round((new Date(r.expiration_date + 'T00:00:00') - today) / 86400000);
                        daysNote = days < 0 ? ' (expired ' + Math.abs(days) + ' day' + (Math.abs(days) === 1 ? '' : 's') + ' ago)' : ' (' + days + ' day' + (days === 1 ? '' : 's') + ')';
                    }
                    const isExpired = r.status === 'EXPIRED';
                    const statusBadge = '<span class="fb-app-status-pill">' + (isExpired ? 'Expired &rarr; Renew' : 'Expiring &rarr; Renew') + '</span>';
                    const subtitle = escapeHtml(r.position_title || r.officer_type) + ' &middot; ' + escapeHtml(r.bond_no || 'No bond no.') +
                        (showingAll ? ' &middot; ' + escapeHtml(r.rc_name || '') : '') +
                        ' &middot; exp ' + escapeHtml(r.expiration_date || '—') + daysNote +
                        ' &middot; <span class="fb-app-new-expiry">new expiry (provisional): ' + newExpiryStr + '</span>';
                    return '<label class="fb-app-officer-item">' +
                        '<input type="checkbox" class="fb-app-officer-check" data-listing-id="' + r.listing_id + '">' +
                        '<span class="fb-app-officer-info"><span class="fb-app-officer-name">' + escapeHtml(r.employee_name) + '</span>' +
                        '<span class="fb-app-officer-sub">' + subtitle + '</span></span>' +
                        statusBadge +
                        '</label>';
                }).join('');
            })
            .catch(() => { list.innerHTML = '<div class="fims-empty" style="padding:14px 0;">Could not reach the server.</div>'; });
    }
    document.getElementById('fbAppRegion')?.addEventListener('change', loadAppOfficers);
    function submitApplication() {
        const btn = document.getElementById('fbAppSubmitBtn');
        const listingIds = Array.from(document.querySelectorAll('.fb-app-officer-check:checked')).map(cb => cb.dataset.listingId);
        if (!listingIds.length) { toast('Select at least one officer to renew.', true); return; }
        const dateFiled = document.getElementById('fbAppDateFiled').value;
        if (!dateFiled) { toast('Date filed with BTr is required.', true); return; }
        const payload = {
            listing_ids: listingIds,
            date_filed_btr: dateFiled,
            premium_payment_reference: document.getElementById('fbAppPremiumRef').value.trim(),
            application_file_url: document.getElementById('fbAppFileUrl').value.trim(),
            proof_of_payment_file_url: document.getElementById('fbAppProofUrl').value.trim(),
            oo_file_url: document.getElementById('fbAppOoFileUrl').value.trim(),
        };
        btn.disabled = true;
        fetch(API + '/apply', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                if (!json.success) { toast(json.message || 'Could not submit application.', true); return; }
                const d = json.data;
                document.getElementById('fbAppHint').textContent = d.applied_count + ' tagged Applied.';
                toast('Application submitted.');
                ['fbAppFileUrl', 'fbAppFileInput', 'fbAppProofUrl', 'fbAppProofInput', 'fbAppOoFileUrl', 'fbAppOoFileInput'].forEach(id => { document.getElementById(id).value = ''; });
                document.getElementById('fbAppFileStatus').textContent = '';
                document.getElementById('fbAppProofStatus').textContent = '';
                document.getElementById('fbAppOoFileStatus').textContent = '';
                document.getElementById('fbAppRegion').dispatchEvent(new Event('change'));
            })
            .catch(function () { btn.disabled = false; toast('Could not reach the server.', true); });
    }
    document.getElementById('fbAppSubmitBtn')?.addEventListener('click', function () {
        const listingIds = Array.from(document.querySelectorAll('.fb-app-officer-check:checked')).map(cb => cb.dataset.listingId);
        if (!listingIds.length) { toast('Select at least one officer to renew.', true); return; }
        if (!document.getElementById('fbAppDateFiled').value) { toast('Date filed with BTr is required.', true); return; }
        // Same nudge as Section B's missing-OO check.
        if (!document.getElementById('fbAppOoFileUrl').value.trim()) {
            fbOoNoFileProceedAction = submitApplication;
            openModal('m-oo-no-file');
            return;
        }
        submitApplication();
    });
    function submitNewOfficer() {
        const btn = document.getElementById('fbNewOfficerBtn');
        const payload = {
            first_name: document.getElementById('fbNewOfficerFirstName').value.trim(),
            middle_name: document.getElementById('fbNewOfficerMiddleName').value.trim(),
            last_name: document.getElementById('fbNewOfficerLastName').value.trim(),
            suffix: document.getElementById('fbNewOfficerSuffix').value.trim(),
            position_title: document.getElementById('fbNewOfficerPosition').value.trim(),
            officer_type: document.getElementById('fbNewOfficerType').value,
            rc_id: document.getElementById('fbNewOfficerRegion').value,
            accountability_amount: parseAmountValue(document.getElementById('fbNewOfficerAccountability').value),
            oo_file_url: document.getElementById('fbNewOfficerOoFileUrl').value.trim(),
        };
        if (!payload.first_name || !payload.last_name || !payload.rc_id) { toast('First name, last name, and region are required.', true); return; }
        btn.disabled = true;
        fetch(API + '/officers/add', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                toast(json.message || (json.success ? 'Officer added — Applied.' : 'Could not add officer.'), !json.success);
                if (json.success) {
                    ['fbNewOfficerFirstName', 'fbNewOfficerMiddleName', 'fbNewOfficerLastName', 'fbNewOfficerSuffix', 'fbNewOfficerPosition', 'fbNewOfficerAccountability', 'fbNewOfficerOoFileUrl', 'fbNewOfficerOoFileInput'].forEach(id => { document.getElementById(id).value = ''; });
                    document.getElementById('fbNewOfficerOoFileStatus').textContent = '';
                }
            })
            .catch(function () { btn.disabled = false; toast('Could not reach the server.', true); });
    }
    // Shared by Section A's and Section B's "no Office Order attached" nudge
    // — set right before opening the modal, so one Proceed button can
    // resume whichever form triggered it.
    let fbOoNoFileProceedAction = null;
    document.getElementById('fbNewOfficerBtn')?.addEventListener('click', function () {
        const payload = {
            first_name: document.getElementById('fbNewOfficerFirstName').value.trim(),
            last_name: document.getElementById('fbNewOfficerLastName').value.trim(),
            rc_id: document.getElementById('fbNewOfficerRegion').value,
        };
        if (!payload.first_name || !payload.last_name || !payload.rc_id) { toast('First name, last name, and region are required.', true); return; }
        // Same nudge as the CL's missing-scan check — easy to forget, but
        // this is the document that actually authorizes the designation.
        if (!document.getElementById('fbNewOfficerOoFileUrl').value.trim()) {
            fbOoNoFileProceedAction = submitNewOfficer;
            openModal('m-oo-no-file');
            return;
        }
        submitNewOfficer();
    });
    document.getElementById('fbOoNoFileProceedBtn')?.addEventListener('click', function () {
        closeModal('m-oo-no-file');
        if (fbOoNoFileProceedAction) { fbOoNoFileProceedAction(); fbOoNoFileProceedAction = null; }
    });

    // ── TAB 4: CL Upload & Register ──────────────────────────────────────
    // Officers are composed client-side (search, add, edit Risk No./
    // Approved Amount, remove — all local array ops, no server round trip
    // per officer) and only sent to the server once, when actually posting
    // — see FidelityBondService::postNewCl(). fbExtractedOfficerData
    // carries risk_no/approved_bond_amount read off the scan (keyed by
    // matched officer_id) so adding that officer pre-fills them instead of
    // leaving the row blank.
    let fbClInitialized = false;
    let fbClOfficers = [];
    let fbExtractedOfficerData = {};
    function initClTab() {
        loadClRegister();
        if (fbClInitialized) return;
        fbClInitialized = true;
        loadRegionsInto(['#fbClRegion', '#fbRegisterRegion']);
    }
    function fbClComputeExpiration() {
        const eff = document.getElementById('fbClEffectivity').value;
        if (!eff) return '';
        const d = new Date(eff + 'T00:00:00');
        d.setFullYear(d.getFullYear() + 1);
        return d.toISOString().slice(0, 10);
    }
    document.getElementById('fbClEffectivity')?.addEventListener('change', function () {
        document.getElementById('fbClExpirationPreview').value = fbClComputeExpiration();
        renderClOfficers();
    });
    function renderClOfficers() {
        const tbody = document.querySelector('#fbClOfficersTable tbody');
        const eff = document.getElementById('fbClEffectivity').value;
        const exp = document.getElementById('fbClExpirationPreview').value;
        // Effectivity/Expiration aren't entered per officer — they're the
        // CL's own dates above, applied identically to every officer on
        // this letter once posted. Shown here read-only so it's clear
        // where they come from, not left blank/editable.
        const newRange = (eff && exp) ? escapeHtml(eff) + ' &rarr; ' + escapeHtml(exp) : '—';
        tbody.innerHTML = fbClOfficers.length
            ? fbClOfficers.map(function (o, idx) {
                return '<tr>' +
                    '<td><div class="fb-cl-officer-name">' + escapeHtml(o.employee_name) + '</div><div class="fb-cl-officer-sub">' + escapeHtml(o.position_title || '') + '</div></td>' +
                    '<td><input type="text" class="fims-input fims-input-sm fb-cl-risk-no" placeholder="e.g. RISK-2026-0001" value="' + escapeHtml(o.new_risk_no || '') + '" data-idx="' + idx + '"></td>' +
                    '<td><input type="text" inputmode="decimal" class="fims-input fims-input-sm text-right fb-cl-amount" placeholder="0.00" value="' + formatAmountDisplay(o.approved_bond_amount || '') + '" data-idx="' + idx + '"></td>' +
                    '<td><div class="fb-cl-new-listing-range">' + newRange + '</div></td>' +
                    '<td class="text-center"><button type="button" class="fb-cl-remove-btn" title="Remove" data-remove-idx="' + idx + '"><i class="fas fa-trash"></i></button></td>' +
                    '</tr>';
            }).join('')
            : '<tr class="fims-empty"><td colspan="5">No officers added yet.</td></tr>';
        tbody.querySelectorAll('.fb-cl-amount').forEach(initAmountInput);
        tbody.querySelectorAll('.fb-cl-risk-no').forEach(function (input) {
            input.addEventListener('change', function () { fbClOfficers[this.dataset.idx].new_risk_no = this.value.trim(); refreshClPostSummary(); });
        });
        tbody.querySelectorAll('.fb-cl-amount').forEach(function (input) {
            input.addEventListener('change', function () { fbClOfficers[this.dataset.idx].approved_bond_amount = parseAmountValue(this.value); refreshClPostSummary(); });
        });
        tbody.querySelectorAll('[data-remove-idx]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                fbClOfficers.splice(this.dataset.removeIdx, 1);
                renderClOfficers();
            });
        });
        refreshClPostSummary();
    }
    function refreshClPostSummary() {
        const ready = fbClOfficers.length > 0 && fbClOfficers.every(o => o.new_risk_no && o.approved_bond_amount);
        document.getElementById('fbClPostSummary').textContent = fbClOfficers.length
            ? (fbClOfficers.length + ' listing(s) will move to Bond History, ' + fbClOfficers.length + ' new listing(s) will be created.' + (ready ? ' Ready to post.' : ' Fill in Risk No. and Approved Amount for every officer first.'))
            : 'Add at least one officer before posting.';
        document.getElementById('fbClPostBtn').disabled = !ready;
    }
    function addOfficerToDraftCl(officerId, employeeName, positionTitle, matchRowEl) {
        officerId = parseInt(officerId, 10);
        if (fbClOfficers.some(o => o.officer_id === officerId)) { toast('Already added.', true); return; }
        const extracted = fbExtractedOfficerData[officerId] || {};
        fbClOfficers.push({
            officer_id: officerId,
            employee_name: employeeName,
            position_title: positionTitle,
            new_risk_no: extracted.risk_no || '',
            approved_bond_amount: extracted.approved_bond_amount || 0,
        });
        renderClOfficers();
        // Already on the letter now — drop it from the candidates list so
        // it can't be picked again from here.
        if (matchRowEl) {
            matchRowEl.remove();
        }
    }
    function submitPostCl() {
        const btn = document.getElementById('fbClPostBtn');
        const err = document.getElementById('fbClCreateError');
        err.style.display = 'none';
        const payload = {
            cl_number: document.getElementById('fbClNumber').value.trim(),
            cl_date: document.getElementById('fbClDate').value,
            rc_id: document.getElementById('fbClRegion').value,
            new_effectivity_date: document.getElementById('fbClEffectivity').value,
            scanned_file_url: document.getElementById('fbClFileUrl').value.trim(),
            officers: fbClOfficers.map(o => ({ officer_id: o.officer_id, new_risk_no: o.new_risk_no, approved_bond_amount: o.approved_bond_amount })),
        };
        btn.disabled = true;
        fetch(API + '/cl/post-direct', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                if (!json.success) { err.querySelector('.fims-alert-body').textContent = json.message || 'Could not post.'; err.style.display = ''; return; }
                toast('Confirmation Letter posted — ' + json.data.officers_posted + ' officer(s), new expiration ' + json.data.new_expiration_date + '.');
                fbClOfficers = [];
                fbExtractedOfficerData = {};
                renderClOfficers();
                ['fbClNumber', 'fbClDate', 'fbClEffectivity', 'fbClExpirationPreview'].forEach(id => { document.getElementById(id).value = ''; });
                fbClResetDropzone();
                document.getElementById('fbClRegion').value = '';
                loadClRegister();
            })
            .catch(function () { btn.disabled = false; toast('Could not reach the server.', true); });
    }
    document.getElementById('fbClPostBtn')?.addEventListener('click', function () {
        if (!fbClOfficers.length) { toast('Add at least one officer before posting.', true); return; }
        // The scanned CL isn't required by the backend (a link can follow
        // later), but it's the one document that actually proves what the
        // named officers' new accountability is — easy to skip by mistake,
        // so skipping it needs an explicit "yes, really" instead of
        // silently going through.
        if (!document.getElementById('fbClFileUrl').value.trim()) {
            openModal('m-cl-no-file');
            return;
        }
        submitPostCl();
    });
    document.getElementById('fbClNoFileProceedBtn')?.addEventListener('click', function () {
        closeModal('m-cl-no-file');
        submitPostCl();
    });
    function loadClRegister() {
        const params = new URLSearchParams();
        const region = document.getElementById('fbRegisterRegion')?.value;
        const dateFrom = document.getElementById('fbRegisterDateFrom')?.value;
        const dateTo = document.getElementById('fbRegisterDateTo')?.value;
        if (region) params.set('region', region);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        fetch(API + '/cl-register?' + params.toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const tbody = document.querySelector('#fbRegisterTable tbody');
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="9">No records.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.cl_number) + '</td><td>' + escapeHtml(r.cl_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.rc_name || '') + '</td><td>' + escapeHtml(r.employee_name || '') + '</td>' +
                        '<td>' + escapeHtml(r.new_risk_no || '') + '</td><td>' + escapeHtml(r.new_effectivity_date || '') + '</td>' +
                        '<td>' + escapeHtml(r.new_expiration_date || '') + '</td>' +
                        '<td class="text-right">' + peso(r.approved_bond_amount) + '</td>' +
                        '<td class="text-center"><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-view-cl="' + r.cl_id + '">View</button></td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-view-cl]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openClSummary(this.dataset.viewCl); });
                });
            })
            .catch(() => {});
    }
    document.getElementById('fbRegisterFilterBtn')?.addEventListener('click', loadClRegister);
    document.getElementById('fbRegisterExportBtn')?.addEventListener('click', function () {
        const rows = Array.from(document.querySelectorAll('#fbRegisterTable tbody tr')).filter(tr => !tr.classList.contains('fims-empty'));
        if (!rows.length) { toast('Nothing to export.', true); return; }
        const btn = this;
        btn.disabled = true;
        const params = new URLSearchParams();
        const region = document.getElementById('fbRegisterRegion')?.value;
        const dateFrom = document.getElementById('fbRegisterDateFrom')?.value;
        const dateTo = document.getElementById('fbRegisterDateTo')?.value;
        if (region) params.set('region', region);
        if (dateFrom) params.set('date_from', dateFrom);
        if (dateTo) params.set('date_to', dateTo);
        // A real, auto-laid-out .xlsx built server-side (XlsxWriter) — not
        // the tab-separated text this used to fake a .xls out of.
        fetch(API + '/cl-register/export?' + params.toString(), { headers: authHeaders() })
            .then(function (r) {
                if (!r.ok) { throw new Error(); }
                return r.blob();
            })
            .then(function (blob) {
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'fidelity-bond-cl-register.xlsx';
                a.click();
                URL.revokeObjectURL(a.href);
            })
            .catch(function () { toast('Could not generate the Excel file.', true); })
            .finally(function () { btn.disabled = false; });
    });


    // ── TAB 5: Bond History ──────────────────────────────────────────────
    function loadHistory() {
        const params = new URLSearchParams();
        const region = document.getElementById('fbHistRegion').value;
        const search = document.getElementById('fbHistSearch').value.trim();
        if (region) params.set('region', region);
        if (search) params.set('search', search);
        const tbody = document.querySelector('#fbHistoryTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Loading…</td></tr>';
        fetch(API + '/history?' + params.toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">No records.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    const closedBy = r.closed_by_cl_id
                        ? '<a href="#" data-view-cl="' + r.closed_by_cl_id + '">Closed by ' + escapeHtml(r.closed_by_cl_number || ('CL #' + r.closed_by_cl_id)) + '</a>'
                        : '—';
                    return '<tr>' +
                        '<td>' + escapeHtml(r.employee_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.position_title || '') + '</span></td>' +
                        '<td>' + escapeHtml(r.rc_name || '') + '</td>' +
                        '<td>' + escapeHtml(r.bond_no || '—') + '</td><td>' + escapeHtml(r.risk_no || '—') + '</td>' +
                        '<td class="text-right">' + (r.approved_bond_amount ? peso(r.approved_bond_amount) : '—') + '</td>' +
                        '<td>' + escapeHtml(r.effectivity_date || '—') + '</td><td>' + escapeHtml(r.expiration_date || '—') + '</td>' +
                        '<td>' + closedBy + '</td></tr>';
                }).join('');
                tbody.querySelectorAll('[data-view-cl]').forEach(function (a) {
                    a.addEventListener('click', function (e) { e.preventDefault(); openClSummary(this.dataset.viewCl); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('fbHistFilterBtn')?.addEventListener('click', loadHistory);
    document.getElementById('fbHistSearch')?.addEventListener('keydown', function (e) { if (e.key === 'Enter') loadHistory(); });

    // ── Boot ──
    loadRegionsInto(['#fbDashRegion', '#fbHistRegion']);
    loadDashboard();
})();
</script>
