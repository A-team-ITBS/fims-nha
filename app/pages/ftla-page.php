<?php
namespace PHPMaker2024\FIMS;
$users = ExecuteRowsAssociative("
    SELECT u.user_id, u.first_name || ' ' || u.last_name AS full_name
    FROM   uac.users u
    WHERE  u.is_active = TRUE
    ORDER  BY u.last_name, u.first_name
", "DB");

$usersJson = json_encode($users ?: []);
$apiBase = Config('API.FIMS_URL');
$apiBaseUrl       = json_encode(rtrim((string) $apiBase, '/') . '/');
$fileBaseUrl      = json_encode(rtrim(BasePath(), '/'));
?>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="ftla-root" class="nha-root">

    <div class="nha-hdr">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="24" height="24">
                    <rect x="2" y="5" width="20" height="14" rx="2" />
                    <path d="M2 10h20M7 15h2M11 15h4" />
                </svg>
            </div>
            <div class="nha-hdr-text">
                <h1 class="nha-hdr-title">FTLA Preparation &amp; Status Tracking</h1>
            </div>
        </div>
        <div class="nha-hdr-r">

            <button type="button" class="nha-btn-hdr" id="btn-new-ftla">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <line x1="12" y1="5" x2="12" y2="19" />
                    <line x1="5" y1="12" x2="19" y2="12" />
                </svg>
                New FTLA
            </button>
            <button type="button" class="nha-btn-hdr" id="btn-new-ftla-request" title="Memo requesting a fund transfer from Treasury — a separate document from the FTLA bank letter">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="9" y1="13" x2="15" y2="13"/>
                    <line x1="9" y1="17" x2="15" y2="17"/>
                </svg>
                FTLA Request
            </button>
            <button class="nha-btn-hdr" id="btn-refresh" title="Refresh data">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" width="14" height="14">
                    <path stroke-linecap="round" stroke-linejoin="round"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="nha-kpi-grid nha-kpi-grid-4">
        <a href="#" class="nha-kpi-card nha-kpi-gray" id="kpi-draft" data-filter="DRAFT">
            <span class="rg-kpi-ico"><i class="fas fa-file-pen"></i></span>
            <div class="nha-sc-top"><span class="nha-kpi-label">Draft</span><span class="nha-dot"></span></div>
            <div class="nha-kpi-val">—</div>
            <div class="nha-kpi-sub">Pending preparation</div>
        </a>
        <a href="#" class="nha-kpi-card nha-kpi-amber" id="kpi-review" data-filter="FOR_REVIEW">
            <span class="rg-kpi-ico"><i class="fas fa-eye"></i></span>
            <div class="nha-sc-top"><span class="nha-kpi-label">For Review</span><span class="nha-dot"></span></div>
            <div class="nha-kpi-val">—</div>
            <div class="nha-kpi-sub">Awaiting review</div>
        </a>
        <a href="#" class="nha-kpi-card nha-kpi-indigo" id="kpi-approval" data-filter="FOR_APPROVAL">
            <span class="rg-kpi-ico"><i class="fas fa-stamp"></i></span>
            <div class="nha-sc-top"><span class="nha-kpi-label">For Approval</span><span class="nha-dot"></span></div>
            <div class="nha-kpi-val">—</div>
            <div class="nha-kpi-sub">Pending approval</div>
        </a>


        <div class="nha-kpi-card nha-kpi-orange nha-kpi-static" id="kpi-total">
            <div class="nha-sc-top">
                <span class="nha-kpi-label">Total Amount</span>
                <span class="nha-sc-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14">
                        <line x1="12" y1="1" x2="12" y2="23" />
                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                    </svg></span>
            </div>
            <div class="nha-kpi-val nha-kpi-val-md">—</div>
            <div class="nha-kpi-sub">Philippine Peso</div>
        </div>
    </div>

    <!-- ── FILTER BAR ── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="filter-status" class="nha-filter-select">
                    <option value="">All Statuses</option>
                    <option value="DRAFT">Draft</option>
                    <option value="FOR_RM_APPROVAL">For RM Approval</option>
                    <option value="FOR_REVIEW">For Review</option>
                    <option value="FOR_APPROVAL">For Approval</option>
                    <option value="APPROVED">Approved</option>
                    <option value="TRANSMITTED">Transmitted</option>
                    <option value="VALIDATED">Validated</option>
                    <option value="SUBMITTED_TO_ACCOUNTING">Submitted to Accounting</option>
                    <option value="COMPLETED">Completed</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Bank Account</label>
                <select id="filter-bank" class="nha-filter-select">
                    <option value="">All Bank Accounts</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Month</label>
                <input type="month" id="filter-month" class="nha-filter-input">
            </div>
            <div class="nha-filter-group nha-filter-group-fixed">
                <label class="nha-filter-label">Date From</label>
                <input type="date" id="filter-date-from" class="nha-filter-input">
            </div>
            <div class="nha-filter-group nha-filter-group-fixed">
                <label class="nha-filter-label">Date To</label>
                <input type="date" id="filter-date-to" class="nha-filter-input">
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                    </svg>
                    <input type="text" id="filter-search" class="nha-filter-input"
                           placeholder="Search FTLA # / Purpose…">
                    <button class="nha-filter-clear-btn" id="ftla-search-clear" hidden type="button">×</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Panel -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl" id="ftla-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>FTLA No.</th>
                        <th>Type</th>
                        <th>Transfer Date</th>
                        <th>Destination Bank</th>
                        <th class="text-right">Amount (PHP)</th>
                        <th>Purpose</th>
                        <th class="text-center">Status</th>
                        <th class="text-center nha-th-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="ftla-tbody">
                    <tr>
                        <td colspan="9" class="nha-empty">Loading…</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span id="pagination-info">Showing 0 records</span>
            <div class="nha-pag-btns" id="pagination-controls"></div>
        </div>
    </div>

<!-- FTLA form modal is in form/ftla-form.php (included at bottom of file) -->


<!-- MODAL: Status Tracking -->
<div id="modal-status-track" class="nha-overlay" role="dialog" aria-modal="true">
    <div class="nha-modal">
        <div class="nha-modal-hdr">
            <div>
                <div class="nha-modal-title">FTLA Status Tracking</div>
                <div class="fims-modal-subtitle">Current progress and history of this transfer</div>
            </div>
            <button type="button" class="nha-modal-close" id="modal-status-close" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="nha-modal-body">
            <div class="fims-panel" style="margin-bottom:16px">
                <div class="card-body p-0" style="padding:12px 14px">
                    <div class="fims-detail-grid">
                        <div class="fims-detail-item" id="st-ftla-num"><span class="fims-detail-label">FTLA No.</span><span class="fims-detail-value">—</span></div>
                        <div class="fims-detail-item" id="st-date"><span class="fims-detail-label">Transfer Date</span><span class="fims-detail-value">—</span></div>
                        <div class="fims-detail-item" id="st-amount"><span class="fims-detail-label">Amount</span><span class="fims-detail-value">—</span></div>
                        <div class="fims-detail-item" id="st-status"><span class="fims-detail-label">Current Status</span><span class="fims-detail-value">—</span></div>
                        <div class="fims-detail-item span-2" id="st-purpose"><span class="fims-detail-label">Purpose</span><span class="fims-detail-value">—</span></div>
                    </div>
                </div>
            </div>
            <div class="fims-timeline" id="status-timeline"></div>


        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-primary" id="btn-create-jev" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                    <line x1="12" y1="18" x2="12" y2="12"/>
                    <line x1="9" y1="15" x2="15" y2="15"/>
                </svg>
                Create JEV
            </button>
            <button type="button" class="nha-btn nha-btn-ghost" id="modal-status-cancel">Close</button>

        </div>
    </div>
</div>

<!-- MODAL: Attachments -->
<div id="modal-attachments" class="nha-overlay" role="dialog" aria-modal="true">
    <div class="nha-modal nha-modal-md">
        <div class="nha-modal-hdr">
            <div>
                <div class="nha-modal-title">Manage Attachments</div>
                <div class="fims-modal-subtitle">Upload supporting documents</div>
            </div>
            <button type="button" class="nha-modal-close" id="modal-attachments-close" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="nha-modal-body">
            <div class="fims-panel" style="margin-bottom:16px">
                <div class="card-header"><span class="fims-panel-title">Upload Document</span></div>
                <div class="card-body p-0" style="padding:14px">
                    <div class="fims-form-grid fims-form-grid-2" style="margin-bottom:10px">
                        <div class="nha-fg">
                            <label class="form-label">Document Type</label>
                            <select class="form-control" id="att-type" disabled>
                                <option value="REQUEST_FUND_TRANSFER" selected>Request for Fund Transfer</option>
                                <option value="APPROVED_REQUEST">Approved Request</option>
                                <option value="MC_REFERENCE">MC Reference</option>
                                <option value="CASH_POSITION">Cash Position Report</option>
                                <option value="TRANSMITTAL">Bank Transmittal</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="nha-fg">
                            <label class="form-label">File</label>
                            <input type="file" class="form-control" id="att-file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.doc,.docx">
                        </div>
                    </div>
                    <button type="button" class="nha-btn nha-btn-primary nha-btn-sm" id="btn-upload-att">Upload</button>
                </div>
            </div>
            <div id="attachments-list"></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-ghost" id="modal-attachments-cancel">Done</button>
        </div>
    </div>
</div>

<!-- MODAL: View Documents -->
<div id="modal-view-docs" class="nha-overlay" role="dialog" aria-modal="true" style="display:none">
    <div class="nha-modal nha-modal-md">
        <div class="nha-modal-hdr">
            <div>
                <div class="nha-modal-title">Uploaded Documents</div>
                <div class="fims-modal-subtitle" id="view-docs-subtitle">Supporting documents for this FTLA</div>
            </div>
            <button type="button" class="nha-modal-close" id="modal-view-docs-close" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="nha-modal-body">
            <div id="view-docs-list"></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-ghost" id="modal-view-docs-cancel">Close</button>
        </div>
    </div>
</div>

<!-- MODAL: Print / Download -->
<div id="modal-print" class="nha-overlay" role="dialog" aria-modal="true" style="display:none">
    <div class="nha-modal" style="max-width:560px;width:96vw;">
        <div class="nha-modal-hdr">
            <div>
                <div class="nha-modal-title" id="modal-print-title">Fund Transfer Letter of Advice</div>
                <div class="fims-modal-subtitle" id="drftla-footer-info"></div>
            </div>
            <button type="button" class="nha-modal-close" id="modal-print-close" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div class="nha-modal-body" style="padding:20px 24px;">
            <div id="drftla-loading" style="display:flex;align-items:center;justify-content:center;padding:40px;gap:12px;color:var(--afims-text-muted, #62657E);font-size:13px;">
                <div class="nha-spinner-sm"></div>
                <span>Loading…</span>
            </div>
            <div id="drftla-info" style="display:none;"></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-primary" id="btn-preview-pdf" style="display:none">
                <svg fill="none" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Preview &amp; Print
            </button>
            <button type="button" class="nha-btn nha-btn-ghost" id="btn-download-docx" style="display:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Download DOCX
            </button>
            <button type="button" class="nha-btn nha-btn-ghost" id="modal-print-cancel">Close</button>
        </div>
    </div>
</div>

<!-- MODAL: FTLA Request Memo — the regional office's memo TO Treasury, a separate
     document from the FTLA bank letter itself. Print-only: nothing here is saved,
     since the real request memo is sent before any FTLA record exists. -->
<style>
    #modal-ftla-request .nha-modal { max-width: 1120px; width: 96vw; max-height: 92vh; display: flex; flex-direction: column; }
    #ftlreq-body-split { flex: 1; display: flex; overflow: hidden; min-height: 0; }
    #ftlreq-fields { flex: 1 1 380px; min-width: 0; overflow-y: auto; padding: 20px 24px; }
    #ftlreq-preview-panel {
        flex: 1.6 1 420px; min-width: 0; display: flex; flex-direction: column;
        background: var(--afims-surface-subtle, #F4F7FA); overflow: hidden;
    }
    #ftlreq-preview-label {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em;
        color: var(--afims-text-disabled, #A9AAB8); padding: 10px 14px 6px; flex-shrink: 0;
    }
    #ftlreq-preview-wrap { flex: 1; overflow: auto; padding: 0 10px 10px; position: relative; }
    #ftlreq-preview-inner {
        transform-origin: top left; background: var(--afims-surface, #FFFFFF);
        box-shadow: 0 2px 12px rgba(0,0,0,.13); width: 612px;
    }
    @media (max-width: 860px) {
        #ftlreq-preview-panel { display: none; }
        #ftlreq-fields { flex: 1; }
    }
</style>

<div id="modal-ftla-request" class="nha-overlay" role="dialog" aria-modal="true" style="display:none">
    <div class="nha-modal">
        <div class="nha-modal-hdr">
            <div>
                <div class="nha-modal-title">FTLA Request Memo</div>
                <div class="fims-modal-subtitle">Memorandum requesting a fund transfer from Treasury</div>
            </div>
            <button type="button" class="nha-modal-close" id="modal-ftla-request-close" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18" />
                    <line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>
        </div>
        <div id="ftlreq-body-split">
        <div id="ftlreq-fields">
            <div id="ftlreq-error" style="display:none;margin-bottom:12px;padding:8px 12px;border-radius:7px;background:#FDECEC;color:#B42318;font-size:13px;"></div>

            <div class="ftlm-grid-2">
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_date">Date</label>
                    <input type="date" class="form-control" id="ftlreq_date">
                </div>
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_amount">Amount (PHP)</label>
                    <input type="text" class="form-control" id="ftlreq_amount" inputmode="decimal" placeholder="0.00"
                           oninput="ftlm_formatAmount(this)">
                </div>
            </div>

            <div class="ftlm-grid-2">
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_for_name">FOR — Name</label>
                    <input type="text" class="form-control" id="ftlreq_for_name" placeholder="e.g. JOEBEN A. TAI">
                </div>
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_for_title">FOR — Title</label>
                    <input type="text" class="form-control" id="ftlreq_for_title" placeholder="e.g. General Manager">
                </div>
            </div>
            <div class="ftlm-grid-2">
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_thru_name">THRU — Name</label>
                    <input type="text" class="form-control" id="ftlreq_thru_name" placeholder="e.g. LEO CARMELO C. BONILLA">
                </div>
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_thru_title">THRU — Title</label>
                    <input type="text" class="form-control" id="ftlreq_thru_title" placeholder="e.g. Department Manager, Treasury Dept.">
                </div>
            </div>
            <div class="ftlm-fg">
                <label class="ftlm-label" for="ftlreq_subject">Subject</label>
                <textarea class="form-control" id="ftlreq_subject" rows="2" placeholder="Request for Fund Transfer"></textarea>
            </div>
            <div class="ftlm-fg">
                <label class="ftlm-label" for="ftlreq_particulars">Particulars <span style="font-size:10px;font-weight:400;color:var(--afims-text-disabled, #A9AAB8);">(what the transfer covers)</span></label>
                <textarea class="form-control" id="ftlreq_particulars" rows="2" placeholder="e.g. emergency housing assistance for Region III"></textarea>
            </div>

            <div class="ftlm-fg">
                <label class="ftlm-label" for="ftlreq_bank_account_id">Credit Account</label>
                <select class="form-control" id="ftlreq_bank_account_id">
                    <option value="">Loading…</option>
                </select>
            </div>

            <div class="ftlm-grid-2">
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_laa_number">LAA Number</label>
                    <select class="form-control" id="ftlreq_laa_number">
                        <option value="">— Select LAA —</option>
                    </select>
                </div>
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_rfa_number">RFA Number</label>
                    <select class="form-control" id="ftlreq_rfa_number">
                        <option value="">— Select RFA —</option>
                    </select>
                </div>
            </div>
            <input type="hidden" id="ftlreq_laa_fiscal_year">
            <input type="hidden" id="ftlreq_rfa_date">

            <div class="ftlm-grid-2">
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_sig_name">Signatory Name</label>
                    <input type="text" class="form-control" id="ftlreq_sig_name" placeholder="e.g. MA. FATIMA T. DELA CRUZ">
                </div>
                <div class="ftlm-fg">
                    <label class="ftlm-label" for="ftlreq_sig_title">Signatory Title</label>
                    <input type="text" class="form-control" id="ftlreq_sig_title" placeholder="e.g. Regional Manager">
                </div>
            </div>
        </div><!-- /ftlreq-fields -->

        <div id="ftlreq-preview-panel">
            <div id="ftlreq-preview-label">Live Preview — Request Memo</div>
            <div id="ftlreq-preview-wrap">
                <div id="ftlreq-preview-inner">
                    <div class="ftlm-preview-placeholder">Fill in the particulars to see the preview</div>
                </div>
            </div>
        </div><!-- /ftlreq-preview-panel -->
        </div><!-- /ftlreq-body-split -->

        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-primary" id="btn-ftlreq-preview">
                <svg fill="none" viewBox="0 0 24 24" width="13" height="13" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Preview &amp; Print
            </button>
            <button type="button" class="nha-btn nha-btn-ghost" id="modal-ftla-request-cancel">Close</button>
        </div>
    </div>
</div>

</div><!-- /#ftla-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   FTLA REQUEST MEMO — standalone, print-only (prefix: ftlreq_)
   A separate document from the FTLA bank letter — the regional office's
   memo TO Treasury asking for the transfer. Nothing here is ever saved;
   "Preview & Print" hands the payload straight to the shared print-preview
   modal (prp_open), which already knows how to render/PDF/DOCX 'FTLAREQ'
   (see print-preview-readone.php's PRP_TYPES.FTLAREQ).
═══════════════════════════════════════════════════════════════ */
let ftlreq_banksLoaded = false;
let ftlreq_banks        = [];
let ftlreq_allocLoaded  = false;
let ftlreq_laaOptions   = [];
let ftlreq_rfaOptions   = [];

function ftlreq$(id) { return document.getElementById(id); }

async function ftlreq_fetch(endpoint) {
    const token = window.ew?.API_JWT_TOKEN || '';
    const res = await fetch(`${FTLM_API_BASE}/${endpoint}`, {
        headers: { 'X-Authorization': `Bearer ${token}` },
    });
    const text = await res.text();
    let json = {};
    if (text) { try { json = JSON.parse(text); } catch { throw new Error(`Non-JSON response (HTTP ${res.status})`); } }
    if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
    return json;
}

async function ftlreq_loadBankAccounts() {
    const sel = ftlreq$('ftlreq_bank_account_id');
    try {
        const res = await ftlreq_fetch('ftla/bank-accounts');
        ftlreq_banks = res.data || [];
        if (sel) {
            sel.innerHTML = '<option value="">— Select —</option>' +
                ftlreq_banks.map(b => `<option value="${b.bank_account_id}">${ftlmEsc(b.bank_name)} — ${ftlmEsc(b.account_name)} (${ftlmEsc(b.account_number)})</option>`).join('');
        }
    } catch (e) {
        if (sel) sel.innerHTML = '<option value="">Failed to load</option>';
    }
}

function ftlreq_allocOptsHtml(placeholder, rows) {
    return `<option value="">${placeholder}</option>` + rows.map(r =>
        `<option value="${ftlmEsc(r.allocation_number)}">${ftlmEsc(r.allocation_number)}</option>`).join('');
}

async function ftlreq_loadAllocationOptions() {
    const laaSel = ftlreq$('ftlreq_laa_number');
    const rfaSel = ftlreq$('ftlreq_rfa_number');
    try {
        const [laaRes, rfaRes] = await Promise.all([
            ftlreq_fetch('budget_allocation/variance/list?type=LAA&status=PROCESSED&limit=500'),
            ftlreq_fetch('budget_allocation/variance/list?type=RFA&status=APPROVED,PROCESSED&limit=500'),
        ]);
        ftlreq_laaOptions = laaRes.data || [];
        ftlreq_rfaOptions = rfaRes.data || [];
        if (laaSel) laaSel.innerHTML = ftlreq_allocOptsHtml('— Select LAA —', ftlreq_laaOptions);
        if (rfaSel) rfaSel.innerHTML = ftlreq_allocOptsHtml('— Select RFA —', ftlreq_rfaOptions);
    } catch (e) {
        console.error('[FTLREQ] allocation options:', e.message);
    }
}

function ftlreq_onLaaSelect() {
    const opt = ftlreq_laaOptions.find(o => o.allocation_number === ftlreq$('ftlreq_laa_number')?.value);
    ftlreq$('ftlreq_laa_fiscal_year').value = opt?.fiscal_year || '';
}
function ftlreq_onRfaSelect() {
    const opt = ftlreq_rfaOptions.find(o => o.allocation_number === ftlreq$('ftlreq_rfa_number')?.value);
    ftlreq$('ftlreq_rfa_date').value = opt?.allocation_date || opt?.rfa_date || '';
}

function ftlreq_showError(msg) {
    const el = ftlreq$('ftlreq-error');
    if (el) { el.textContent = msg; el.style.display = ''; }
}
function ftlreq_hideError() {
    const el = ftlreq$('ftlreq-error');
    if (el) el.style.display = 'none';
}

async function ftlreq_open() {
    ftlreq_hideError();
    [
        'ftlreq_for_name', 'ftlreq_for_title', 'ftlreq_thru_name', 'ftlreq_thru_title',
        'ftlreq_subject', 'ftlreq_particulars', 'ftlreq_amount', 'ftlreq_bank_account_id',
        'ftlreq_laa_number', 'ftlreq_laa_fiscal_year', 'ftlreq_rfa_number', 'ftlreq_rfa_date',
        'ftlreq_sig_name', 'ftlreq_sig_title',
    ].forEach(id => { const el = ftlreq$(id); if (el) el.value = ''; });
    ftlreq$('ftlreq_date').value = new Date().toISOString().slice(0, 10);

    window._ftlaApp?._openModal('modal-ftla-request');
    ftlreq_updatePreview();

    if (!ftlreq_banksLoaded) { ftlreq_banksLoaded = true; ftlreq_loadBankAccounts(); }
    if (!ftlreq_allocLoaded) { ftlreq_allocLoaded = true; ftlreq_loadAllocationOptions(); }
}

function ftlreq_close() {
    window._ftlaApp?._closeModal('modal-ftla-request');
}

/** Mirrors LaaRequestPrintService::html()'s memo layout with FtlaRequestPrintService's own wording. */
function ftlreq_renderMemo(d) {
    const e  = s => ftlmEsc(s ?? '');
    const nb = s => (s !== '' && s != null) ? e(s) : '&nbsp;';
    const longDate = raw => {
        if (!raw) return '';
        const dt = new Date(raw);
        return isNaN(dt) ? '' : dt.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    };

    let rows = '';
    if (d.for_name) rows += `<tr><td class="l">FOR</td><td class="c">:</td><td><b>${e(d.for_name)}</b><div class="rt">${nb(d.for_title)}</div></td></tr>`;
    if (d.thru_name) rows += `<tr><td class="l">THRU</td><td class="c">:</td><td><b>${e(d.thru_name)}</b><div class="rt">${nb(d.thru_title)}</div></td></tr>`;
    rows += `<tr><td class="l">SUBJECT</td><td class="c">:</td><td><b>${e(d.subject || 'Request for Fund Transfer')}</b></td></tr>`;

    const amount = d.amount || 0;
    const words  = amount > 0 ? ftlm_numberToWordsUpper(amount) : '';
    const particularsText = d.particulars
        ? `We humbly request the transfer of fund to cover the ${e(d.particulars)} in the amount of <b>${e(words)} (Php ${e(ftlm_fmtPeso(amount))}).</b>`
        : '';

    const fundingParts = [];
    if (d.laa_number) fundingParts.push(`LAA No. <b>${e(d.laa_number)}</b>` + (d.fiscal_year ? ` CY ${e(d.fiscal_year)}` : ''));
    if (d.rfa_number) {
        const rfaDateStr = longDate(d.rfa_date);
        fundingParts.push(`RFA No. <b>${e(d.rfa_number)}</b>` + (rfaDateStr ? ` dated ${e(rfaDateStr)}` : ''));
    }
    const bankLine = d.bank_account_line ? `${e(d.bank_account_line)}.` : '';
    const transferText = bankLine
        ? (fundingParts.length
            ? `The above-mentioned amount is funded under the ${fundingParts.join(' &amp; ')}. Kindly transfer said amount to <b>${bankLine}</b>`
            : `Kindly transfer said amount to <b>${bankLine}</b>`)
        : '';

    return `<div style="font-family:Arial,Helvetica,sans-serif;font-size:11pt;color:#000;line-height:1.2;padding:36px 48px;background:#fff;">
        <p style="margin:0 0 14px;">${nb(longDate(d.date))}</p>
        <p style="font-weight:bold;margin:0 0 14px;">MEMORANDUM</p>
        <table style="border-collapse:collapse;width:100%;">
            <style>.l{width:80px;font-weight:bold;vertical-align:top;padding:0 0 6px;}.c{width:18px;vertical-align:top;padding:0 0 6px;}.rt{margin-bottom:10px;}</style>
            ${rows}
        </table>
        <div style="border-bottom:1px solid #000;margin:8px 0 12px;"></div>
        ${particularsText ? `<p style="text-align:justify;margin:0 0 14px;">${particularsText}</p>` : ''}
        ${transferText ? `<p style="text-align:justify;margin:0 0 14px;">${transferText}</p>` : ''}
        <p style="text-align:justify;margin:0 0 14px;">Kindly inform us and provide us with a copy of Fund Transfer Letter Advice (FTLA) once it is approved.</p>
        <p>For your consideration.</p>
        <div style="margin-top:44px;"><b>${nb(d.sig_name)}</b><br>${e(d.sig_title || '')}</div>
    </div>`;
}

let ftlreq_previewTimer = null;
function ftlreq_updatePreview() {
    const inner = ftlreq$('ftlreq-preview-inner');
    const wrap  = ftlreq$('ftlreq-preview-wrap');
    if (!inner || !wrap) return;
    inner.innerHTML = ftlreq_renderMemo(ftlreq_buildPayload());
    inner.style.opacity = '1';
    requestAnimationFrame(() => {
        const scale = Math.max(0.72, Math.min(1, wrap.offsetWidth / 612));
        inner.style.transform = `scale(${scale})`;
        wrap.style.height = Math.ceil(inner.scrollHeight * scale) + 'px';
    });
}
function ftlreq_schedulePreviewUpdate() {
    const inner = ftlreq$('ftlreq-preview-inner');
    if (inner) inner.style.opacity = '0.6';
    clearTimeout(ftlreq_previewTimer);
    ftlreq_previewTimer = setTimeout(ftlreq_updatePreview, 220);
}

function ftlreq_buildPayload() {
    const v = id => ftlreq$(id)?.value || '';
    const bank = ftlreq_banks.find(b => b.bank_account_id === parseInt(v('ftlreq_bank_account_id')));
    const bankAccountLine = bank ? `${bank.account_name} ${bank.bank_name} Account No.${bank.account_number}` : '';

    return {
        date: v('ftlreq_date'),
        for_name: v('ftlreq_for_name'), for_title: v('ftlreq_for_title'),
        thru_name: v('ftlreq_thru_name'), thru_title: v('ftlreq_thru_title'),
        subject: v('ftlreq_subject') || 'Request for Fund Transfer',
        particulars: v('ftlreq_particulars'),
        amount: ftlm_parseAmount(v('ftlreq_amount')),
        laa_number: v('ftlreq_laa_number'), laa_department: '', fiscal_year: v('ftlreq_laa_fiscal_year'),
        rfa_number: v('ftlreq_rfa_number'), rfa_date: v('ftlreq_rfa_date'),
        bank_account_line: bankAccountLine,
        sig_name: v('ftlreq_sig_name'), sig_title: v('ftlreq_sig_title'),
    };
}

document.addEventListener('DOMContentLoaded', () => {
    ftlreq$('modal-ftla-request-close') ?.addEventListener('click', ftlreq_close);
    ftlreq$('modal-ftla-request-cancel')?.addEventListener('click', ftlreq_close);
    ftlreq$('ftlreq_laa_number')?.addEventListener('change', ftlreq_onLaaSelect);
    ftlreq$('ftlreq_rfa_number')?.addEventListener('change', ftlreq_onRfaSelect);
    // Delegated — catches every field (typed or selected) without wiring each one individually.
    ftlreq$('ftlreq-fields')?.addEventListener('input',  ftlreq_schedulePreviewUpdate);
    ftlreq$('ftlreq-fields')?.addEventListener('change', ftlreq_schedulePreviewUpdate);
    ftlreq$('btn-ftlreq-preview')?.addEventListener('click', () => {
        if (!ftlreq$('ftlreq_particulars')?.value.trim()) {
            ftlreq_showError('Particulars is required.');
            return;
        }
        ftlreq_hideError();
        if (typeof prp_open === 'function') {
            prp_open(ftlreq_buildPayload(), { type: 'FTLAREQ' });
        } else {
            ftlreq_showError('Print preview is unavailable right now.');
        }
    });
});
</script>

<?php include __DIR__ . '/readone/print-preview-readone.php'; ?>

<!-- Remarks overlay for workflow actions that require a reason -->
<div id="ftla-remarks-overlay" style="display:none;position:fixed;inset:0;z-index:10200;background:rgba(15,32,69,.5);align-items:center;justify-content:center;padding:16px;">
    <div style="background:var(--afims-surface, #FFFFFF);border-radius:14px;width:440px;max-width:100%;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.3);font-family:'IBM Plex Sans',sans-serif;">
        <div style="background:var(--afims-heading-fill, #000E3E);padding:16px 20px;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);" id="ftla-remarks-title">Remarks Required</span>
            <button onclick="ftla_closeRemarks()" style="background:none;border:none;color:rgba(255,255,255,.6);font-size:22px;line-height:1;cursor:pointer;">&times;</button>
        </div>
        <div style="padding:20px 22px 12px;">
            <p style="font-size:13px;color:var(--afims-text, #1A1B21);margin:0 0 12px;" id="ftla-remarks-msg">Please provide a reason for this action.</p>
            <textarea id="ftla-remarks-input" rows="3" placeholder="Enter remarks…"
                style="width:100%;box-sizing:border-box;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;padding:10px;font-size:13px;font-family:'IBM Plex Sans',sans-serif;resize:vertical;outline:none;"></textarea>
            <p id="ftla-remarks-err" style="color:var(--afims-danger, #DC3545);font-size:11.5px;margin:5px 0 0;display:none;">Remarks are required.</p>
        </div>
        <div style="padding:8px 22px 18px;display:flex;justify-content:flex-end;gap:8px;">
            <button onclick="ftla_closeRemarks()" style="height:34px;padding:0 18px;background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;">Cancel</button>
            <button onclick="ftla_confirmRemarks()" style="height:34px;padding:0 22px;background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF);border:none;border-radius:8px;font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;">Confirm</button>
        </div>
    </div>
</div>

<!-- DATA BRIDGE -->
<script>
    window.FTLAConfig = {
        apiBaseUrl: <?= $apiBaseUrl ?>,
        fileBaseUrl: <?= $fileBaseUrl ?>,
        users: <?= $usersJson ?>
    };
</script>

<script>
    'use strict';

    class FTLAPreparation {

        static TOTAL_STEPS = 2;
        static MODAL_IDS = ['modal-status-track', 'modal-attachments', 'modal-view-docs', 'modal-print', 'modal-ftla-request'];
        static scrollPosition = 0;

        static TYPE_LABEL = {
            FTLA: 'Regional Fund Transfer',
            REGIONAL_FUND_TRANSFER: 'Regional Fund Transfer',
            INTER_ACCOUNT: 'Inter-Account Transfer',
            ERRONEOUS_CHARGEABILITY: 'Correct Erroneous Chargeability',
            ERRONEOUS_DEPOSIT: 'Correct Erroneous Deposit',
            REMITTANCE_ECHMLP: 'Remittance - ECHMLP',
            REMITTANCE_HLA: 'Remittance - House & Lot Awards',
            SUBSIDY_TRANSFER: 'Transfer of Subsidy',
            REVERSAL: 'Reversal of Funds',
            GENERAL_FUND: 'Transfer to General Fund',
        };

        // Mirrors FtlaPrintService::SUBJECT_BY_TYPE / FIXED_SUBJECT_BY_TYPE_AND_LAYOUT (app/services/FtlaPrintService.php)
        // so the live preview shows the same subject line the actual printed letter will.
        static SUBJECT_BY_TYPE = {
            ERRONEOUS_CHARGEABILITY: 'AUTHORITY TO DEBIT/CREDIT ACCOUNT (CORRECTION OF ERRONEOUS CHARGEABILITY)',
            ERRONEOUS_DEPOSIT:       'AUTHORITY TO DEBIT/CREDIT ACCOUNT (CORRECTION OF ERRONEOUS DEPOSIT)',
            REMITTANCE_ECHMLP:       'AUTHORITY TO DEBIT/CREDIT ACCOUNT (REMITTANCE — ECHMLP)',
            REMITTANCE_HLA:          'AUTHORITY TO DEBIT/CREDIT ACCOUNT (REMITTANCE — HOUSE & LOT AWARDS)',
            SUBSIDY_TRANSFER:        'AUTHORITY TO DEBIT/CREDIT ACCOUNT (TRANSFER OF SUBSIDY)',
            REVERSAL:                'AUTHORITY TO DEBIT/CREDIT ACCOUNT (REVERSAL OF FUNDS)',
            GENERAL_FUND:            'AUTHORITY TO TRANSFER FUNDS TO GENERAL FUND ACCOUNT',
            TEMPORARY:               'AUTHORITY TO DEBIT/CREDIT ACCOUNT (TEMPORARY CASH TRANSFER)',
        };
        static FIXED_SUBJECT_BY_TYPE_AND_LAYOUT = {
            REGIONAL_FUND_TRANSFER: { debit_list: 'AUTHORITY TO TRANSFER FUNDS', pairs: 'AUTHORITY TO TRANSFER FUNDS' },
            SETTLEMENT_TO_MAIN: {
                single: 'AUTHORITY TO TRANSFER SETTLEMENT FUNDS TO MAIN DEPOSITORY ACCOUNT',
                debit_list: 'AUTHORITY TO TRANSFER SETTLEMENT FUNDS TO MAIN DEPOSITORY ACCOUNT',
                pairs: 'AUTHORITY TO TRANSFER SETTLEMENT FUNDS TO MAIN DEPOSITORY ACCOUNT',
            },
        };

        static STATUSES = [{
                key: 'DRAFT',
                label: 'Draft'
            },
            {
                key: 'FOR_RM_APPROVAL',
                label: 'For RM Approval'
            },
            {
                key: 'FOR_REVIEW',
                label: 'For Review'
            },
            {
                key: 'FOR_APPROVAL',
                label: 'For Approval'
            },
            {
                key: 'APPROVED',
                label: 'Approved'
            },
            {
                key: 'TRANSMITTED',
                label: 'Transmitted to Bank'
            },
            {
                key: 'VALIDATED',
                label: 'Validated (Bank Confirmed)'
            },
            {
                key: 'SUBMITTED_TO_ACCOUNTING',
                label: 'Submitted to Accounting'
            },
            {
                key: 'COMPLETED',
                label: 'Completed (With Validated FTLA)'
            },
        ];

        constructor(cfg) {
            this.bankAccounts = []; // loaded async via fetchApi
            this.users = cfg.users || [];
            this.fileBaseUrl = (cfg.fileBaseUrl || '').replace(/\/$/, '');
            this.page = 1;
            this.pageSize = 15;
            this.total = 0;
            this.records = [];
            this.currentId = null;
            this.attachments = [];
            this._currentStep = 0;
            this._currentFtlaData = null; // holds data for Create JEV action

            this._bindEvents();
            this._populateSelects(); // async — bank accounts load in background
            this._initDvNumbers();
        }

        /* ══════════════════════════════════════════════════
           STEPPER
        ══════════════════════════════════════════════════ */

        _goStep(n) {
            const total = FTLAPreparation.TOTAL_STEPS;

            // Update panels
            for (let i = 0; i < total; i++) {
                const p = document.getElementById('ftla-panel-' + i);
                if (p) p.classList.toggle('ftla-active', i === n);
            }

            // Update circles / connectors
            for (let i = 0; i < total; i++) {
                const stepEl = document.getElementById('ftla-step-' + i);
                const circle = document.getElementById('ftla-circle-' + i);
                const conn = document.getElementById('ftla-connector-' + i);
                if (!stepEl) continue;

                stepEl.classList.remove('active', 'done');
                if (i < n) {
                    stepEl.classList.add('done');
                    circle.innerHTML = '✓';
                    if (conn) conn.style.background = 'var(--afims-success, #11A752)';
                } else if (i === n) {
                    stepEl.classList.add('active');
                    circle.textContent = i + 1;
                    if (conn) conn.style.background = 'var(--afims-border, #E4ECF2)';
                } else {
                    circle.textContent = i + 1;
                    if (conn) conn.style.background = 'var(--afims-border, #E4ECF2)';
                }
            }

            // Footer buttons
            const btnBack = document.getElementById('ftla-btn-back');
            const btnNext = document.getElementById('ftla-btn-next');
            const btnSubmit = document.getElementById('btn-save-submit');
            if (btnBack) btnBack.style.display = n > 0 ? '' : 'none';
            if (btnNext) btnNext.style.display = n < total - 1 ? '' : 'none';
            if (btnSubmit) btnSubmit.style.display = n === total - 1 ? '' : 'none';

            const counter = document.getElementById('ftla-step-counter');
            if (counter) counter.textContent = `Step ${n + 1} of ${total}`;

            // Scroll form panel to top
            const panel = document.getElementById('ftla-form-panel');
            if (panel) panel.scrollTop = 0;

            this._currentStep = n;
        }

        _validateStep(n) {
            if (n === 0) {
                const type = document.getElementById('f-transfer-type')?.value;
                const date = document.getElementById('f-transfer-date')?.value;
                const to = document.getElementById('f-to-bank')?.value;
                const amt = document.getElementById('f-amount')?.value;
                const pur = document.getElementById('f-purpose')?.value?.trim();

                if (!type || !date || !to || !amt || !pur) {
                    this._toast('Please fill all required fields on Step 1.', 'warning');
                    document.getElementById('ftla-form')?.reportValidity();
                    return false;
                }
            }
            return true;
        }

        _nextStep() {
            if (!this._validateStep(this._currentStep)) return;
            if (this._currentStep < FTLAPreparation.TOTAL_STEPS - 1) {
                this._goStep(this._currentStep + 1);
            }
        }

        _prevStep() {
            if (this._currentStep > 0) this._goStep(this._currentStep - 1);
        }

        /* ══════════════════════════════════════════════════
           EVENT BINDING
        ══════════════════════════════════════════════════ */

        _bindEvents() {
            const on = (id, evt, fn) => {
                const el = document.getElementById(id);
                if (el) el.addEventListener(evt, fn.bind(this));
            };

            on('btn-new-ftla', 'click', () => ftlm_open());
            on('btn-new-ftla-request', 'click', () => ftlreq_open());
            on('btn-refresh', 'click', async function (e) {
                const btn = e.currentTarget;
                btn.disabled = true;
                btn.classList.add('loading');
                window.PagePreloader?.show();
                try {
                    await Promise.all([this._loadTable(), this._loadKPIs()]);
                } finally {
                    btn.disabled = false;
                    btn.classList.remove('loading');
                    window.PagePreloader?.hide();
                }
            });

            ['filter-status', 'filter-bank', 'filter-month', 'filter-date-from', 'filter-date-to'].forEach(id =>
                on(id, 'change', () => {
                    this.page = 1;
                    this._loadTable();
                }));
            on('filter-search', 'input', () => {
                const clr = document.getElementById('ftla-search-clear');
                const inp = document.getElementById('filter-search');
                if (clr && inp) clr.hidden = !inp.value;
                this.page = 1;
                this._loadTable();
            });
            on('ftla-search-clear', 'click', () => {
                const inp = document.getElementById('filter-search');
                const clr = document.getElementById('ftla-search-clear');
                if (inp) inp.value = '';
                if (clr) clr.hidden = true;
                this.page = 1;
                this._loadTable();
            });

            document.querySelectorAll('[data-filter]').forEach(el =>
                el.addEventListener('click', e => {
                    e.preventDefault();
                    const fs = document.getElementById('filter-status');
                    if (fs) fs.value = el.dataset.filter;
                    this.page = 1;
                    this._loadTable();
                }));

            // Stepper navigation
            on('ftla-btn-next', 'click', () => this._nextStep());
            on('ftla-btn-back', 'click', () => this._prevStep());

            on('modal-ftla-close', 'click', () => this._closeModal('modal-ftla-form'));
            on('modal-ftla-cancel', 'click', () => this._closeModal('modal-ftla-form'));
            on('btn-save-draft', 'click', () => this._saveForm('DRAFT'));
            on('btn-save-submit', 'click', () => this._saveForm(null));

            // Live preview — wire to all form inputs
            const ftlaForm = document.getElementById('ftla-form');
            ftlaForm?.addEventListener('input', () => this._schedulePreviewUpdate());
            ftlaForm?.addEventListener('change', () => this._schedulePreviewUpdate());

            on('f-is-temporary', 'change', e => {
                const rf = document.getElementById('repayment-field');
                if (rf) rf.style.display = e.target.checked ? '' : 'none';
            });
            on('f-transfer-type', 'change', e => {
                const dvg = document.getElementById('dv-numbers-group');
                if (dvg) dvg.style.display = (e.target.value && e.target.value !== 'INTER_ACCOUNT') ? '' : 'none';
            });

            on('btn-manage-attachments', 'click', () => this._openAttachmentsModal());
            on('modal-attachments-close', 'click', () => this._closeModal('modal-attachments'));
            on('modal-attachments-cancel', 'click', () => this._closeModal('modal-attachments'));
            on('modal-view-docs-close', 'click', () => this._closeModal('modal-view-docs'));
            on('modal-view-docs-cancel', 'click', () => this._closeModal('modal-view-docs'));
            on('btn-upload-att', 'click', () => this._uploadAttachment());

            on('modal-status-close', 'click', () => this._closeModal('modal-status-track'));
            on('modal-status-cancel', 'click', () => this._closeModal('modal-status-track'));
            on('btn-advance-status', 'click', () => this._advanceStatus());
            on('btn-create-jev', 'click', () => this._createJevFromFtla());
            on('modal-print-close', 'click', () => this._closeModal('modal-print'));
            on('modal-print-cancel', 'click', () => this._closeModal('modal-print'));
            on('btn-download-docx', 'click', () => this._downloadDocx());
            on('btn-preview-pdf', 'click', () => this._previewPdf());

        }

        /* ══════════════════════════════════════════════════
           SELECT POPULATION
        ══════════════════════════════════════════════════ */

        async _populateSelects() {
            // ── Bank accounts — fetched from API ──────────────────────────────
            try {
                const res = await this.fetchApi('ftla/bank-accounts');
                this.bankAccounts = res.data || [];
            } catch (e) {
                console.error('[FTLA] Could not load bank accounts:', e.message);
                this.bankAccounts = [];
            }

            const bankOpts = this.bankAccounts.map(b =>
                `<option value="${b.bank_account_id}">${this._esc(b.bank_name)} — ${this._esc(b.account_name)} (${this._esc(b.account_number)})</option>`
            ).join('');

            // ── Users — baked from PHP (no sensitive data concern) ────────────
            const userOpts = this.users.map(u =>
                `<option value="${u.user_id}">${this._esc(u.full_name)}</option>`
            ).join('');

            const toSel = document.getElementById('f-to-bank');
            if (toSel) toSel.insertAdjacentHTML('beforeend', bankOpts);

            ['f-prepared-by', 'f-reviewed-by', 'f-approved-by'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.insertAdjacentHTML('beforeend', userOpts);
            });
            const fb = document.getElementById('filter-bank');
            if (fb) fb.insertAdjacentHTML('beforeend',
                this.bankAccounts.map(b =>
                    `<option value="${b.bank_account_id}">${this._esc(b.bank_name)} (${this._esc(b.account_number)})</option>`
                ).join('')
            );
        }

        _schedulePreviewUpdate() { /* no-op */ }

        /* ══════════════════════════════════════════════════
           DV NUMBERS
        ══════════════════════════════════════════════════ */

        _initDvNumbers() {
            document.getElementById('btn-add-dv')?.addEventListener('click', () => this._addDvRow());
            document.getElementById('dv-numbers-list')?.addEventListener('click', e => {
                if (e.target.closest('.ftla-dv-remove')) {
                    e.target.closest('.ftla-dv-row').remove();
                    this._schedulePreviewUpdate();
                }
            });
            document.getElementById('dv-numbers-list')?.addEventListener('input', () => this._schedulePreviewUpdate());
        }

        _addDvRow(value = '') {
            const list = document.getElementById('dv-numbers-list');
            if (!list) return;
            const row = document.createElement('div');
            row.className = 'ftla-dv-row';
            row.innerHTML = `
            <input type="text" class="form-control ftla-dv-input"
                   value="${value.replace(/"/g,'&quot;')}"
                   placeholder="e.g. 25-10-0862">
            <button type="button" class="nha-btn nha-btn-ghost nha-btn-sm ftla-dv-remove"
                    title="Remove">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="11" height="11">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>`;
            list.appendChild(row);
        }

        _getDvNumbers() {
            return Array.from(document.querySelectorAll('#dv-numbers-list .ftla-dv-input'))
                .map(i => i.value.trim()).filter(v => v).join('\n');
        }



        /* ══════════════════════════════════════════════════
           KPIs & TABLE
        ══════════════════════════════════════════════════ */

        async _loadKPIs() {
            try {
                const res = await this.fetchApi('ftla/kpis');
                const d = res.data || {};
                const upd = (id, val) => {
                    const el = document.querySelector(`#${id} .nha-kpi-val`);
                    if (el) el.textContent = val;
                };
                upd('kpi-draft', this._fmt(d.draft || 0));
                upd('kpi-review', this._fmt(d.for_review || 0));
                upd('kpi-approval', this._fmt(d.for_approval || 0));

                upd('kpi-total', this._money(d.total_amount || 0));
            } catch {
                /* non-critical */
            }
        }

        async _loadTable() {
            window.PagePreloader?.show();
            const p = new URLSearchParams({
                page: this.page,
                page_size: this.pageSize,
                status: document.getElementById('filter-status')?.value || '',
                bank_id: document.getElementById('filter-bank')?.value || '',
                month: document.getElementById('filter-month')?.value || '',
                date_from: document.getElementById('filter-date-from')?.value || '',
                date_to: document.getElementById('filter-date-to')?.value || '',
                search: document.getElementById('filter-search')?.value || '',
            });
            const tbody = document.getElementById('ftla-tbody');
            try {
                const res = await this.fetchApi(`ftla/list?${p}`);
                this.records = res.data || [];
                this.total = res.total || 0;
                this._renderTable();
                this._renderPagination();
            } catch (err) {
                tbody.innerHTML = `<tr><td colspan="9" class="nha-empty" style="color:var(--red)">Failed to load: ${this._esc(err.message)}</td></tr>`;
                nha_toast('Could not load FTLA records: ' + err.message, 'error');
            } finally {
                window.PagePreloader?.hide();
            }
        }

        _renderTable() {
            const tbody = document.getElementById('ftla-tbody');
            const offset = (this.page - 1) * this.pageSize;
            if (!this.records.length) {
                tbody.innerHTML = `<tr><td colspan="9" class="nha-empty">No FTLA records found.</td></tr>`;
                return;
            }
            tbody.innerHTML = this.records.map((r, i) => `
            <tr data-id="${r.fund_transfer_id}">
                <td class="nha-td-seq">${offset + i + 1}</td>
                <td><a href="javascript:void(0);" onclick="ftla_ro_open(${r.fund_transfer_id})" class="nha-tbl-link nha-mono">${this._esc(r.ftla_number || '—')}</a></td>
                <td class="nha-td-type">${this._esc(FTLAPreparation.TYPE_LABEL[r.transfer_type] || r.transfer_type || '—')}</td>
                <td>${this._date(r.transfer_date)}</td>
                <td><span style="font-size:12px;font-weight:600">${this._esc(r.to_bank_name||'—')}</span><br><span class="nha-mono nha-td-xs">${this._esc(r.to_account_number||'')}</span></td>
                <td class="text-right nha-amt">${this._money(r.transfer_amount)}</td>
                <td class="fims-col-truncate" title="${this._esc(r.purpose||'')}">${this._esc(r.purpose||'—')}</td>
                <td class="text-center">${this._statusBadge(r.status)}</td>
                <td class="text-center">
                    <div class="nha-tools-menu">
                        <button class="nha-menu-trigger" onclick="ftl_toggleMenu(this)">
                            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                            Actions
                        </button>
                        <div class="nha-tools-dropdown">
                            <a class="nha-menu-item" href="javascript:void(0);"
                               onclick="ftla_ro_open(${r.fund_transfer_id});ftl_closeMenus();">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                            </a>
                            ${ftla_wfActData.is_preparer && ftla_editableStatuses.has(r.status) ? `
                            <a class="nha-menu-item" href="javascript:void(0);"
                               onclick="ftlm_open(${r.fund_transfer_id});ftl_closeMenus();">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </a>` : ''}
                            ${ftla_rowActionItems(r)}
                            ${['VALIDATED','SUBMITTED_TO_ACCOUNTING','COMPLETED'].includes(r.status) ? `
                            <div class="nha-menu-divider"></div>
                            <a class="nha-menu-item" href="javascript:void(0);"
                               onclick="window._ftlaApp._downloadValidatedFtla(${r.fund_transfer_id});ftl_closeMenus();"
                               style="color:var(--afims-primary-text, #0035A9)">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                Download Validated FTLA
                            </a>` : ''}
                            ${r.status === 'VALIDATED' ? `
                            <a class="nha-menu-item" href="javascript:void(0);"
                               onclick="window._ftlaApp._openStatusTrack(${r.fund_transfer_id});ftl_closeMenus();"
                               style="color:var(--afims-success-hover, #0C7539)">
                                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
                                Prepare JEV
                            </a>` : ''}
                        </div>
                    </div>
                </td>
            </tr>`).join('');
        }

        _renderPagination() {
            const info = document.getElementById('pagination-info');
            const ctrl = document.getElementById('pagination-controls');
            const from = this.total ? (this.page - 1) * this.pageSize + 1 : 0;
            const to = Math.min(this.page * this.pageSize, this.total);
            if (info) info.textContent = this.total ? `Showing ${from}–${to} of ${this.total} records` : '';
            const pages = Math.ceil(this.total / this.pageSize) || 1;
            let html = '';
            for (let p = 1; p <= pages; p++) {
                if (pages > 7 && Math.abs(p - this.page) > 2 && p !== 1 && p !== pages) {
                    if (p === 2 || p === pages - 1) html += `<span class="nha-pag-ellipsis">…</span>`;
                    continue;
                }
                html += `<button class="nha-pag-btn${p === this.page ? ' active' : ''}" data-p="${p}">${p}</button>`;
            }
            if (ctrl) {
                ctrl.innerHTML = html;
                ctrl.querySelectorAll('[data-p]').forEach(b =>
                    b.addEventListener('click', () => {
                        this.page = parseInt(b.dataset.p, 10);
                        this._loadTable();
                    }));
            }
        }

        /* ══════════════════════════════════════════════════
           FORM OPEN / RESET / FILL / SAVE
        ══════════════════════════════════════════════════ */

        async _openForm(id) {
            this.currentId = id;
            this.attachments = [];
            this._resetForm();
            this._goStep(0);
            if (id) {
                document.getElementById('modal-ftla-title').textContent = 'Edit FTLA';
                try {
                    const res = await this.fetchApi(`ftla/${id}`);
                    this._fillForm(res.data);
                } catch (err) {
                    this._toast('Failed to load record: ' + err.message, 'error');
                    return;
                }
            } else {
                document.getElementById('modal-ftla-title').textContent = 'New FTLA';
                const dtEl = document.getElementById('f-transfer-date');
                if (dtEl) dtEl.value = new Date().toISOString().slice(0, 10);
            }
            this._openModal('modal-ftla-form');
        }

        _resetForm() {
            const form = document.getElementById('ftla-form');
            if (form) form.reset();
            const fid = document.getElementById('f-id');
            if (fid) fid.value = '';
            const rf = document.getElementById('repayment-field');
            if (rf) rf.style.display = 'none';
            const msg = document.getElementById('form-status-msg');
            if (msg) msg.textContent = '';
            this._renderAttachmentsDisplay([]);
            const dvList = document.getElementById('dv-numbers-list');
            if (dvList) dvList.innerHTML = '';
        }

        _fillForm(d) {
            const set = (id, val) => {
                const el = document.getElementById(id);
                if (!el || val === null || val === undefined) return;
                el.type === 'checkbox' ? (el.checked = !!val) : (el.value = val);
            };
            set('f-id', d.fund_transfer_id);
            set('f-ftla-number', d.ftla_number);
            set('f-transfer-date', d.transfer_date?.slice(0, 10));
            set('f-transfer-type', d.transfer_type);
            const dvg = document.getElementById('dv-numbers-group');
            if (dvg) dvg.style.display = (d.transfer_type && d.transfer_type !== 'INTER_ACCOUNT') ? '' : 'none';
            set('f-to-bank', d.to_bank_account_id);
            set('f-amount', d.transfer_amount);
            set('f-is-temporary', d.is_temporary);
            set('f-repayment-date', d.repayment_due_date?.slice(0, 10));
            set('f-purpose', d.purpose);
            set('f-request-ref', d.request_reference);
            set('f-prepared-by', d.prepared_by);
            set('f-reviewed-by', d.reviewed_by);
            set('f-approved-by', d.approved_by);
            set('f-remarks', d.remarks);
            const rf = document.getElementById('repayment-field');
            if (rf) rf.style.display = d.is_temporary ? '' : 'none';
            const dvList = document.getElementById('dv-numbers-list');
            if (dvList) {
                dvList.innerHTML = '';
                (d.dv_numbers || '').split('\n').map(v => v.trim()).filter(v => v).forEach(v => this._addDvRow(v));
            }
            this.attachments = d.attachments || [];
            this._renderAttachmentsDisplay(this.attachments);
            const setTxt = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val || '';
            };
            const bo = d.bank_officers || {};
            setTxt('f-bank-sig-verified', bo.sig_verified || '');
            setTxt('f-bank-checked', bo.checked || '');
            setTxt('f-bank-approved', bo.approved || '');
            setTxt('f-bank-posted', bo.posted || '');
            this._schedulePreviewUpdate();
        }


        async _saveForm(status) {
            // Validate step 1 before saving from any step
            if (!this._validateStep(0) && status !== 'DRAFT') return;
            const msg = document.getElementById('form-status-msg');
            if (msg) msg.textContent = 'Saving…';
            const fd = new FormData(document.getElementById('ftla-form'));
            if (this.currentId) fd.set('fund_transfer_id', this.currentId);
            if (status) fd.set('status', status);
            fd.set('bank_officers', JSON.stringify({
                sig_verified: document.getElementById('f-bank-sig-verified')?.value || '',
                checked: document.getElementById('f-bank-checked')?.value || '',
                approved: document.getElementById('f-bank-approved')?.value || '',
                posted: document.getElementById('f-bank-posted')?.value || ''
            }));
            fd.set('dv_numbers', this._getDvNumbers());
            try {
                const res = await this.fetchApi(this.currentId ? `ftla/${this.currentId}` : 'ftla', {
                    method: 'POST',
                    body: fd
                });
                this._toast(res.message || 'Saved successfully.', 'success');
                this._closeModal('modal-ftla-form');
                this._loadTable();
                this._loadKPIs();
            } catch (err) {
                if (msg) msg.textContent = 'Error: ' + err.message;
                this._toast('Save failed: ' + err.message, 'error');
            }
        }

        /* ══════════════════════════════════════════════════
           PRINT / DOWNLOAD
        ══════════════════════════════════════════════════ */

        async _printRecord(id) {
            this._printId   = id;
            this._printData = null;
            const loading = document.getElementById('drftla-loading');
            const info    = document.getElementById('drftla-info');
            const dlBtn   = document.getElementById('btn-download-docx');
            const pvBtn   = document.getElementById('btn-preview-pdf');
            if (loading) loading.style.display = 'flex';
            if (info)    info.style.display    = 'none';
            if (dlBtn)   dlBtn.style.display   = 'none';
            if (pvBtn)   pvBtn.style.display   = 'none';
            this._openModal('modal-print');
            try {
                const res = await this.fetchApi(`ftla/${id}`);
                const d   = res.data;
                this._printData = d;
                this._populateSigFields(d);
                const titleEl = document.getElementById('modal-print-title');
                if (titleEl) titleEl.textContent = d.transfer_type === 'INTER_ACCOUNT'
                    ? 'Inter-Account Transfer Advice'
                    : 'Fund Transfer Letter of Advice';
                const footerEl = document.getElementById('drftla-footer-info');
                if (footerEl) footerEl.textContent = `${this._esc(d.ftla_number || '')}  ·  ${new Date().toLocaleString('en-PH')}`;
                if (info) {
                    info.innerHTML = `
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;background:var(--afims-surface-subtle, #F4F7FA);border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:16px;">
                            <div><div style="font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.05em;">FTLA No.</div><div style="font-size:13px;font-weight:600;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Mono',monospace;">${this._esc(d.ftla_number || '—')}</div></div>
                            <div><div style="font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.05em;">Date</div><div style="font-size:13px;font-weight:600;color:var(--afims-text, #1A1B21);">${this._date(d.transfer_date)}</div></div>
                            <div><div style="font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.05em;">Amount</div><div style="font-size:13px;font-weight:600;color:var(--afims-text, #1A1B21);">${this._money(d.transfer_amount)}</div></div>
                            <div><div style="font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.05em;">Status</div><div style="font-size:13px;">${this._statusBadge(d.status)}</div></div>
                        </div>`;
                    info.style.display = '';
                }
                if (loading) loading.style.display = 'none';
                if (dlBtn)   dlBtn.style.display   = '';
                if (pvBtn)   pvBtn.style.display   = '';
            } catch (err) {
                if (loading) loading.innerHTML = `<span style="color:var(--red)">Failed: ${this._esc(err.message)}</span>`;
            }
        }

        _populateSigFields(d) {
            const sigsRaw = d.signatories || {};
            const sigs = typeof sigsRaw === 'string' ? (() => { try { return JSON.parse(sigsRaw); } catch(e) { return {}; } })() : sigsRaw;
            const boRaw = d.bank_officers || {};
            const bo   = typeof boRaw === 'string' ? (() => { try { return JSON.parse(boRaw); } catch(e) { return {}; } })() : boRaw;
            const set  = (id, val) => { const el = document.getElementById(id); if (el) el.value = val || ''; };
            set('prt-sig1-name',  sigs.sig1_name   || d.reviewed_by_name  || '');
            set('prt-sig1-title', sigs.sig1_title  || 'Resettlement Development Chief');
            set('prt-sig2-name',  sigs.sig2_name   || d.approved_by_name  || '');
            set('prt-sig2-title', sigs.sig2_title  || 'Regional Manager');
            set('prt-rec-name',   sigs.recipient_name  || '');
            set('prt-rec-title',  sigs.recipient_title || 'Branch Manager');
            set('prt-dear',       sigs.dear_salutation || "Ma'am/Sir");
            set('prt-bk-sig',     bo.sig_verified || '');
            set('prt-bk-chk',     bo.checked      || '');
            set('prt-bk-app',     bo.approved     || '');
            set('prt-bk-pst',     bo.posted       || '');
        }

        _getSigOverrides() {
            const g = id => document.getElementById(id)?.value || '';
            const d = this._printData || {};
            const sigsRaw = d.signatories || {};
            const sigs = typeof sigsRaw === 'string' ? (() => { try { return JSON.parse(sigsRaw); } catch(e) { return {}; } })() : sigsRaw;
            const boRaw = d.bank_officers || {};
            const bo   = typeof boRaw === 'string' ? (() => { try { return JSON.parse(boRaw); } catch(e) { return {}; } })() : boRaw;
            return {
                sig1_name:       g('prt-sig1-name')  || sigs.sig1_name        || d.reviewed_by_name || '',
                sig1_title:      g('prt-sig1-title') || sigs.sig1_title       || 'Resettlement Development Chief',
                sig2_name:       g('prt-sig2-name')  || sigs.sig2_name        || d.approved_by_name || '',
                sig2_title:      g('prt-sig2-title') || sigs.sig2_title       || 'Regional Manager',
                recipient_name:  g('prt-rec-name')   || sigs.recipient_name   || '',
                recipient_title: g('prt-rec-title')  || sigs.recipient_title  || 'Branch Manager',
                dear_salutation: g('prt-dear')       || sigs.dear_salutation  || "Ma'am/Sir",
                bk_sig:          g('prt-bk-sig')     || bo.sig_verified       || '',
                bk_chk:          g('prt-bk-chk')     || bo.checked            || '',
                bk_app:          g('prt-bk-app')     || bo.approved           || '',
                bk_pst:          g('prt-bk-pst')     || bo.posted             || '',
            };
        }

        async _downloadDocx() {
            const id = this._printId;
            if (!id) return;
            const btn  = document.getElementById('btn-download-docx');
            const orig = btn ? btn.innerHTML : '';
            try {
                if (btn) { btn.disabled = true; btn.innerHTML = '<span>Generating…</span>'; }
                const ov  = this._getSigOverrides();
                const res = await this.fetchApi(`ftla/${id}/print-docx`, {
                    method: 'POST',
                    body: JSON.stringify({ sig_overrides: ov }),
                });
                if (!res.success) throw new Error(res.message || 'Generation failed.');
                const bytes = atob(res.data);
                const arr   = new Uint8Array(bytes.length);
                for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
                const blob  = new Blob([arr], { type: 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' });
                const url   = URL.createObjectURL(blob);
                const a     = document.createElement('a');
                a.href = url; a.download = res.filename || `FTLA-${id}.docx`; a.click();
                setTimeout(() => URL.revokeObjectURL(url), 5000);
            } catch (err) {
                this._toast('Download failed: ' + err.message, 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            }
        }

        async _previewPdf() {
            const id = this._printId;
            if (!id) return;
            const btn  = document.getElementById('btn-preview-pdf');
            const orig = btn ? btn.innerHTML : '';
            try {
                if (btn) { btn.disabled = true; btn.innerHTML = '<span>Generating…</span>'; }
                const res = await this.fetchApi(`ftla/${id}/print-pdf`, { method: 'POST', body: JSON.stringify({}) });
                if (!res.success) throw new Error(res.message || 'PDF generation failed.');
                const bytes = atob(res.data);
                const arr   = new Uint8Array(bytes.length);
                for (let i = 0; i < bytes.length; i++) arr[i] = bytes.charCodeAt(i);
                const blob = new Blob([arr], { type: 'application/pdf' });
                const url  = URL.createObjectURL(blob);
                const win  = window.open(url, '_blank',
                    `width=${Math.floor(screen.width/2)},height=${screen.height},left=0,top=0,menubar=no,toolbar=no,location=no,status=no`);
                if (!win) this._toast('Pop-up blocked. Please allow pop-ups for this site to print.', 'error');
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            } catch (err) {
                this._toast('Preview failed: ' + err.message, 'error');
            } finally {
                if (btn) { btn.disabled = false; btn.innerHTML = orig; }
            }
        }

        /* ══════════════════════════════════════════════════
           STATUS TRACKING
        ══════════════════════════════════════════════════ */

        async _openStatusTrack(id) {
            try {
                const res = await this.fetchApi(`ftla/${id}`);
                const d = res.data;
                this.currentId = id;
                const sv = (elId, val) => {
                    const el = document.querySelector(`#${elId} .fims-detail-value`);
                    if (el) el.innerHTML = val;
                };
                sv('st-ftla-num', `<span class="nha-mono">${this._esc(d.ftla_number||'—')}</span>`);
                sv('st-date', this._date(d.transfer_date));
                sv('st-amount', `<strong>${this._money(d.transfer_amount)}</strong>`);
                sv('st-status', this._statusBadge(d.status));
                sv('st-purpose', this._esc(d.purpose || '—'));
                const currentIdx = FTLAPreparation.STATUSES.findIndex(s => s.key === d.status);
                const tl = document.getElementById('status-timeline');
                if (tl) tl.innerHTML = FTLAPreparation.STATUSES.map((s, i) => {
                    const done = i < currentIdx,
                        cur = i === currentIdx;
                    const icon = done ? `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><polyline points="20 6 9 17 4 12"/></svg>` : cur ? `<svg viewBox="0 0 24 24" fill="currentColor" width="8" height="8"><circle cx="12" cy="12" r="6"/></svg>` : '';
                    return `<div class="fims-timeline-step ${done?'done':''} ${cur?'current':''}"><div class="fims-timeline-dot">${icon}</div><div class="fims-timeline-content"><div class="fims-timeline-label">${this._esc(s.label)}</div><div class="fims-timeline-date">${this._timelineDate(d,s.key)}</div></div></div>`;
                }).join('');
                const advBtn = document.getElementById('btn-advance-status'),
                    next = FTLAPreparation.STATUSES[currentIdx + 1];
                if (advBtn) {
                    const blockAdvance = ['CANCELLED', 'FOR_APPROVAL', 'FOR_RM_APPROVAL', 'COMPLETED'];
                    if (next && !blockAdvance.includes(d.status)) {
                        advBtn.style.display = '';
                        advBtn.dataset.next = next.key;
                        const txt = advBtn.lastChild;
                        if (txt && txt.nodeType === 3) txt.textContent = ` Advance to: ${next.label}`;
                    } else {
                        advBtn.style.display = 'none';
                    }
                }

                // Show "Create JEV" button only when FTLA is finished (submitted to accounting or completed)
                const JEV_ELIGIBLE = ['VALIDATED'];
                const jevBtn = document.getElementById('btn-create-jev');
                if (jevBtn) jevBtn.style.display = JEV_ELIGIBLE.includes(d.status) ? '' : 'none';
                this._currentFtlaData = d;

                this._openModal('modal-status-track');
            } catch (err) {
                this._toast('Failed to load tracking: ' + err.message, 'error');
            }
        }

        _timelineDate(d, key) {
            const map = {
                DRAFT: d.created_at,
                FOR_RM_APPROVAL: d.rm_submitted_at,
                FOR_REVIEW: d.submitted_at,
                FOR_APPROVAL: d.reviewed_at,
                APPROVED: d.approved_date,
                TRANSMITTED: d.transmitted_at,
                VALIDATED: d.validated_date,
                SUBMITTED_TO_ACCOUNTING: d.accounting_submission_date,
                COMPLETED: d.completed_at,
            };
            return map[key] ? this._datetime(map[key]) : '—';
        }

        async _advanceStatus() {
            const btn = document.getElementById('btn-advance-status'),
                next = btn?.dataset.next;
            if (!this.currentId || !next) return;

            try {
                const res = await this.fetchApi(`ftla/update/${this.currentId}`, {
                    method: 'POST',
                    body: JSON.stringify({
                        status: next
                    })
                });
                this._toast(res.message || `Status updated.`, 'success');
                this._closeModal('modal-status-track');
                this._loadTable();
                this._loadKPIs();
            } catch (err) {
                this._toast('Failed: ' + err.message, 'error');
            }
        }



        _createJevFromFtla() {
            if (typeof jevc === 'undefined' || typeof jevc.openPrefilled !== 'function') {
                this._toast('JEV form is not available on this page.', 'error');
                return;
            }
            const d = this._currentFtlaData;
            if (!d) return;
            this._closeModal('modal-status-track');
            jevc.openPrefilled({
                particulars:    d.purpose   || '',
                fund_source_id: d.fund_source_id || null,
            });
        }

        /* ══════════════════════════════════════════════════
           ATTACHMENTS
        ══════════════════════════════════════════════════ */

        _openAttachmentsModal() {
            this._renderAttachmentsList();
            this._openModal('modal-attachments');
        }

        async _uploadAttachment() {
            const typeEl = document.getElementById('att-type'),
                fileEl = document.getElementById('att-file');
            if (!typeEl?.value) {
                this._toast('Select a document type.', 'warning');
                return;
            }
            if (!fileEl?.files.length) {
                this._toast('Select a file.', 'warning');
                return;
            }
            const fd = new FormData();
            fd.set('document_type', typeEl.value);
            fd.set('file', fileEl.files[0]);
            if (this.currentId) fd.set('fund_transfer_id', this.currentId);
            try {
                const res = await this.fetchApi('ftla/attachment', {
                    method: 'POST',
                    body: fd
                });
                this.attachments.push(res.data);
                this._renderAttachmentsList();
                this._renderAttachmentsDisplay(this.attachments);
                typeEl.value = '';
                fileEl.value = '';
                this._toast('Uploaded.', 'success');
            } catch (err) {
                this._toast('Upload failed: ' + err.message, 'error');
            }
        }

        _fileUrl(path) {
            if (!path) return '';
            if (path.startsWith('http')) return path;
            return this.fileBaseUrl + (path.startsWith('/') ? path : '/' + path);
        }

        _renderAttachmentsList() {
            const el = document.getElementById('attachments-list');
            if (!el) return;
            if (!this.attachments.length) {
                el.innerHTML = `<div class="nha-empty nha-empty-sm"><p>No attachments uploaded yet.</p></div>`;
                return;
            }
            el.innerHTML = this.attachments.map((a, i) => {
                const fileUrl = this._fileUrl(a.url || a.file_url || ''),
                    fileName = this._esc(a.name || a.file_name || 'Attachment'),
                    docType = this._esc(a.document_type || a.type || '');
                const ext = (a.name || a.file_name || '').split('.').pop().toLowerCase();
                return `<div class="nha-dr">
                <div class="nha-dr-content"><div style="font-weight:600">${fileName}</div><div class="nha-td-xs">${docType}${ext?' · '+ext.toUpperCase():''}</div></div>
                ${fileUrl?`<a href="${this._esc(fileUrl)}" target="_blank" class="nha-icon-btn" title="View"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a>`:''}
                <button class="nha-icon-btn danger" title="Remove" data-att-idx="${i}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg></button>
            </div>`;
            }).join('');
            el.querySelectorAll('[data-att-idx]').forEach(btn => btn.addEventListener('click', () => {
                this.attachments.splice(parseInt(btn.dataset.attIdx, 10), 1);
                this._renderAttachmentsList();
                this._renderAttachmentsDisplay(this.attachments);
            }));
        }

        _renderAttachmentsDisplay(list) {
            const el = document.getElementById('attachments-display');
            if (!el) return;
            el.innerHTML = list.length ? list.map(a => {
                const fileUrl = this._fileUrl(a.url || a.file_url || ''),
                    fileName = this._esc(a.name || a.file_name || 'Attachment');
                const chip = `<span class="fims-att-chip">${fileName}</span>`;
                return fileUrl ? `<a href="${this._esc(fileUrl)}" target="_blank">${chip}</a>` : chip;
            }).join('') : `<span class="nha-no-file">No attachments yet.</span>`;
        }

        _viewDocuments(record) {
            if (!record) return;
            const list = document.getElementById('view-docs-list'),
                sub = document.getElementById('view-docs-subtitle');
            const atts = record.attachments || [],
                totalCount = atts.length;
            if (sub) sub.textContent = `${record.ftla_number||'FTLA'} · ${totalCount} document${totalCount!==1?'s':''}`;
            if (!list) return;
            if (!totalCount) {
                list.innerHTML = `<div class="nha-empty nha-empty-md"><p>No documents uploaded for this FTLA.</p></div>`;
            } else {
                list.innerHTML = atts.map(a => {
                    const fileUrl = this._fileUrl(a.url || a.file_url || ''),
                        fileName = this._esc(a.name || a.file_name || 'Attachment'),
                        docType = this._esc(a.document_type || a.type || '');
                    return `<div class="nha-dr"><div class="nha-dr-content"><div style="font-weight:600">${fileName}</div><div class="nha-td-xs">${docType}</div></div>${fileUrl?`<a href="${this._esc(fileUrl)}" target="_blank" class="nha-btn nha-btn-ghost nha-btn-sm">Open</a>`:'<span class="nha-no-file">No file</span>'}</div>`;
                }).join('');
            }
            this._openModal('modal-view-docs');
        }

        /* ══════════════════════════════════════════════════
           MODAL OPEN / CLOSE
        ══════════════════════════════════════════════════ */

        _openModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            const isFirst = !FTLAPreparation.MODAL_IDS.some(mid => {
                const m = document.getElementById(mid);
                return m && m !== el && m.style.display !== 'none';
            });
            if (isFirst) {
                FTLAPreparation.scrollPosition = window.scrollY || 0;
                document.body.style.overflow = 'hidden';
                document.body.style.paddingRight = window.innerWidth - document.documentElement.clientWidth + 'px';
            }
            el.style.display = 'flex';
        }
        _closeModal(id) {
            const el = document.getElementById(id);
            if (!el) return;
            el.style.display = 'none';
            const anyOpen = FTLAPreparation.MODAL_IDS.some(mid => {
                const m = document.getElementById(mid);
                return m && m.style.display !== 'none';
            });
            if (!anyOpen) {
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
                window.scrollTo(0, FTLAPreparation.scrollPosition);
            }
        }

        /* ══════════════════════════════════════════════════
           API / UTILITIES
        ══════════════════════════════════════════════════ */

        _baseUrl() {
            return '<?php echo $apiBase; ?>';
        }

        async fetchApi(endpoint, options = {}, timeoutMs = 15000) {
            const {
                params,
                responseType = 'json',
                ...fetchOptions
            } = options;

            // Build URL
            if (params) {
                const qs = new URLSearchParams(params).toString();
                endpoint = `${endpoint}${qs ? '?' + qs : ''}`;
            }

            const isFormData = fetchOptions.body instanceof FormData;
            const token = window.ew?.API_JWT_TOKEN || '';
            const headers = {
                'X-Authorization': `Bearer ${token}`,
                ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
                ...(fetchOptions.headers || {}),
            };

            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), timeoutMs);

            try {
                const res = await fetch(`${this._baseUrl()}/${endpoint}`, {
                    ...fetchOptions,
                    headers,
                    signal: controller.signal,
                });
                clearTimeout(timeout);

                if (responseType === 'blob') {
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    return res.blob();
                }

                const text = await res.text();
                let json = {};
                if (text) {
                    try {
                        json = JSON.parse(text);
                    } catch {
                        const preview = text.substring(0, 120).replace(/\n/g, ' ');
                        throw new Error(`Server returned non-JSON (HTTP ${res.status}): ${preview}`);
                    }
                }
                if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
                return json;
            } catch (e) {
                clearTimeout(timeout);
                throw e;
            }
        }
        _toast(msg, type = 'info') {
            if (type === 'success' && typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'success', title: 'Success', text: msg, timer: 2000, showConfirmButton: false, timerProgressBar: true });
                return;
            }
            if (typeof nha_toast === 'function') { nha_toast(msg, type); return; }
            if (typeof ew !== 'undefined' && typeof ew.showToast === 'function') {
                ew.showToast(msg, type);
            } else {
                console.warn('[FTLA]', msg);
            }
        }
        _statusBadge(s) {
            const meta = s ? ftla_wfMetaData[s] : null;
            const COLOR_MAP = { secondary: 'gray', primary: 'navy', info: 'teal', success: 'green', danger: 'red', warning: 'amber' };
            const cls = meta ? (COLOR_MAP[meta.color] || 'gray') : 'gray';
            return `<span class="nha-bdg nha-bdg-${cls}">${meta?.name || s || '—'}</span>`;
        }
        _money(v) {
            return 'PHP ' + parseFloat(v || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2
            });
        }
        _fmt(v) {
            return parseInt(v || 0, 10).toLocaleString();
        }
        _date(v) {
            return v ? new Date(v).toLocaleDateString('en-PH', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }) : '—';
        }
        _datetime(v) {
            return v ? new Date(v).toLocaleString('en-PH', {
                dateStyle: 'medium',
                timeStyle: 'short'
            }) : '—';
        }
        _numberToWords(amount) {
            const n = parseFloat(amount || 0);
            if (isNaN(n)) return '';
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            const toW = n => {
                if (!n) return '';
                if (n < 20) return ones[n] + ' ';
                if (n < 100) return tens[Math.floor(n / 10)] + (n % 10 ? ' ' + ones[n % 10] : '') + ' ';
                if (n < 1000) return ones[Math.floor(n / 100)] + ' Hundred ' + toW(n % 100);
                if (n < 1e6) return toW(Math.floor(n / 1000)) + 'Thousand ' + toW(n % 1000);
                if (n < 1e9) return toW(Math.floor(n / 1e6)) + 'Million ' + toW(n % 1e6);
                return toW(Math.floor(n / 1e9)) + 'Billion ' + toW(n % 1e9);
            };
            const pesos = Math.floor(n),
                cents = Math.round((n - pesos) * 100);
            return toW(pesos).trim() + ' Pesos' + (cents ? ' and ' + toW(cents).trim() + ' Centavos' : '') + ' Only';
        }
        _downloadValidatedFtla(id) {
            const rec = this.records.find(r => r.fund_transfer_id == id);
            if (!rec) return;
            const bankAtt = (rec.attachments || []).find(a =>
                ['BANK_VALIDATED', 'TRANSMITTAL', 'VALIDATED_FTLA'].includes(a.document_type)
            );
            if (bankAtt) {
                const url = this._fileUrl(bankAtt.url || bankAtt.file_url || '');
                if (url) { window.open(url, '_blank'); return; }
            }
            this._printRecord(id);
        }
        _esc(s) {
            const d = document.createElement('span');
            d.textContent = String(s ?? '');
            return d.innerHTML;
        }

        /** Same rule as FtlaPrintService::subjectFor(): single strips the parenthetical, debit_list/pairs keep it. */
        _subjectFor(transferType, layout) {
            const fixed = FTLAPreparation.FIXED_SUBJECT_BY_TYPE_AND_LAYOUT[transferType]?.[layout];
            if (fixed) return fixed;
            const full = FTLAPreparation.SUBJECT_BY_TYPE[transferType] || 'AUTHORITY TO DEBIT/CREDIT ACCOUNT';
            return layout === 'single' ? full.replace(/\s*\(.*?\)/, '') : full;
        }

        /**
         * Live preview of the FTLA bank letter — mirrors FtlaPrintService::html() closely enough to be
         * a faithful approximation while typing (same layout rules, same subject-by-type, same field
         * labels), without round-tripping to the server on every keystroke. Called from ftla-form.php's
         * ftlm_updatePreview(). $d: see ftlm_buildPreviewData().
         */
        _renderPrintSummary(d) {
            const e   = s => this._esc(s);
            const nb  = s => s !== '' ? e(s) : '&nbsp;';
            const fmt = v => ftlm_fmtPeso(v);
            const words = v => v > 0 ? ftlm_numberToWordsUpper(v) : '';

            const debits  = Array.isArray(d.debit_accounts) ? d.debit_accounts.filter(a => a.account_number || a.account_name) : [];
            const credits = Array.isArray(d.credit_accounts) ? d.credit_accounts : [];
            const uniqueCredits = [...new Set(credits.map(c => (c.account_number || '') + '|' + (c.account_name || '')))];
            const layout = debits.length === 0 ? 'single' : (uniqueCredits.length <= 1 ? 'debit_list' : 'pairs');

            const subject = this._subjectFor(d.transfer_type, layout);
            const total = layout === 'single' ? (d.transfer_amount || 0) : debits.reduce((s, a) => s + (parseFloat(a.amount) || 0), 0);

            const toHeader = `
                <table style="width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:10px;"><tr>
                    <td style="width:72px;vertical-align:bottom;padding:0 0 3px;">TO</td>
                    <td style="width:16px;vertical-align:bottom;padding:0 0 3px;">:</td>
                    <td style="vertical-align:bottom;padding:0 0 3px;"><div style="font-weight:700;">${nb(d.to_bank_name || 'LANDBANK OF THE PHILIPPINES')}</div></td>
                </tr><tr>
                    <td style="vertical-align:bottom;padding:0;">SUBJECT</td>
                    <td style="vertical-align:bottom;padding:0;">:</td>
                    <td style="vertical-align:bottom;padding:0;font-weight:700;">${e(subject)}</td>
                </tr></table>`;

            let body;
            if (layout === 'single') {
                const ul = 'border-bottom:1px solid #000;', vb = 'vertical-align:bottom;', pad = 'padding:0 0 3px;';
                body = `<table style="width:100%;border-collapse:collapse;font-size:9.5pt;margin-bottom:8px;">
                    <tr><td style="white-space:nowrap;${vb}padding:0 6px 5px 0;">Please debit our Account</td>
                        <td colspan="2" style="${ul}${vb}${pad}text-align:center;">${nb(d.from_account_name || d.from_bank_name || '')}</td></tr>
                    <tr><td style="white-space:nowrap;${vb}padding:0 6px 5px 0;">Account No.</td>
                        <td style="${ul}${vb}${pad}text-align:center;width:38%;">${nb(d.from_account_number || '')}</td>
                        <td style="white-space:nowrap;${vb}padding:0 0 5px 8px;">the amount of PESOS:</td></tr>
                    <tr><td></td><td colspan="2" style="${ul}${vb}${pad}text-align:center;">${nb(words(d.transfer_amount))}</td></tr>
                    <tr><td></td><td colspan="2" style="padding:0 0 5px;">Php <u>${e(fmt(d.transfer_amount))}</u></td></tr>
                    <tr><td style="white-space:nowrap;${vb}padding:0 6px 5px 0;">and credit</td>
                        <td colspan="2" style="${ul}${vb}${pad}text-align:center;">${nb(d.to_account_name || '')}</td></tr>
                    <tr><td style="white-space:nowrap;${vb}padding:0 6px 5px 0;">Account No.:</td>
                        <td style="${ul}${vb}${pad}text-align:center;">${nb(d.to_account_number || '')}</td>
                        <td style="white-space:nowrap;${vb}padding:0 0 5px 8px;">representing</td></tr>
                    <tr><td colspan="3" style="${ul}${vb}${pad}padding-top:2px;">${nb(d.purpose || '')}</td></tr>
                </table>`;
            } else if (layout === 'debit_list') {
                const rows = debits.map(a => `
                    <tr><td style="border:1px solid #000;padding:3pt 6pt;text-align:center;">${nb(a.account_number)}</td>
                        <td style="border:1px solid #000;padding:3pt 6pt;">${nb(a.account_name)}</td>
                        <td style="border:1px solid #000;padding:3pt 6pt;text-align:right;">P ${e(fmt(a.amount))}</td></tr>`).join('');
                // Two blank filler rows before TOTAL — mirrors FtlaPrintService::
                // html()'s own debit_list layout (same approved reference).
                const blankRow = `
                    <tr><td style="border:1px solid #000;padding:3pt 6pt;">&nbsp;</td>
                        <td style="border:1px solid #000;padding:3pt 6pt;">&nbsp;</td>
                        <td style="border:1px solid #000;padding:3pt 6pt;text-align:right;">-</td></tr>`;
                body = `<p style="margin:0 0 6px;font-size:9.5pt;">Please <u><b>debit</b></u> the following depository/disbursing accounts:</p>
                    <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:10px;">
                        <tr><th colspan="3" style="border:1px solid #000;padding:3pt 6pt;">BANK ACCOUNT</th></tr>
                        <tr><th style="border:1px solid #000;padding:3pt 6pt;width:26%;">NUMBER</th>
                            <th style="border:1px solid #000;padding:3pt 6pt;width:48%;">NAME</th>
                            <th style="border:1px solid #000;padding:3pt 6pt;width:26%;">AMOUNT</th></tr>
                        ${rows}${blankRow}${blankRow}
                        <tr><td colspan="2" style="border:1px solid #000;padding:3pt 6pt;font-weight:700;">TOTAL</td>
                            <td style="border:1px solid #000;padding:3pt 6pt;text-align:right;font-weight:700;">P ${e(fmt(total))}</td></tr>
                    </table>
                    <p style="margin:0 0 10px;font-size:9.5pt;text-align:justify;">and <u><b>credit</b></u> the account of
                        <u>(${nb(d.to_account_number || '')}) (${nb(d.to_account_name || '')})</u> in the total amount of
                        <u>${nb(words(total))} (Php ${e(fmt(total))})</u>.</p>`;
            } else {
                // "pairs": more than one distinct credit account — each debit
                // row keeps its own index-paired credit row (debits[i] /
                // credits[i], set by ftlm_getDebitAccounts()/
                // ftlm_getCreditAccounts() in ftla-form.php). Grouped by FROM
                // account, same as FtlaPrintService::html()'s own "pairs"
                // layout — a shared FROM account spans its group's rows via
                // rowspan instead of repeating.
                const groups = [];
                const groupIndex = {};
                debits.forEach((dAcc, i) => {
                    const cAcc = credits[i] || {};
                    const key = (dAcc.account_number || '') + '|' + (dAcc.account_name || '');
                    if (!(key in groupIndex)) {
                        groupIndex[key] = groups.length;
                        groups.push({ from: dAcc, rows: [] });
                    }
                    groups[groupIndex[key]].rows.push({ to: cAcc, amount: dAcc.amount });
                });
                let rowsHtml = '';
                groups.forEach(g => {
                    g.rows.forEach((r, i) => {
                        rowsHtml += '<tr>';
                        if (i === 0) {
                            const span = g.rows.length > 1 ? ` rowspan="${g.rows.length}"` : '';
                            rowsHtml += `<td style="border:1px solid #000;padding:3pt 6pt;text-align:center;"${span}>${nb(g.from.account_number)}</td>`;
                            rowsHtml += `<td style="border:1px solid #000;padding:3pt 6pt;"${span}>${nb(g.from.account_name)}</td>`;
                        }
                        rowsHtml += `<td style="border:1px solid #000;padding:3pt 6pt;text-align:center;">${nb(r.to.account_number)}</td>`;
                        rowsHtml += `<td style="border:1px solid #000;padding:3pt 6pt;">${nb(r.to.account_name)}</td>`;
                        rowsHtml += `<td style="border:1px solid #000;padding:3pt 6pt;text-align:right;">P ${e(fmt(r.amount))}</td></tr>`;
                    });
                });
                body = `<p style="margin:0 0 6px;font-size:9.5pt;">Please effect the fund transfer as follows:</p>
                    <table style="width:100%;border-collapse:collapse;font-size:9pt;margin-bottom:10px;">
                        <tr><th colspan="2" style="border:1px solid #000;padding:3pt 6pt;">FROM</th>
                            <th colspan="2" style="border:1px solid #000;padding:3pt 6pt;">TO</th>
                            <th rowspan="2" style="border:1px solid #000;padding:3pt 6pt;">AMOUNT</th></tr>
                        <tr><th style="border:1px solid #000;padding:3pt 6pt;">Account No.</th>
                            <th style="border:1px solid #000;padding:3pt 6pt;">Account Name</th>
                            <th style="border:1px solid #000;padding:3pt 6pt;">Account No.</th>
                            <th style="border:1px solid #000;padding:3pt 6pt;">Account Name</th></tr>
                        ${rowsHtml}
                        <tr><td colspan="4" style="border:1px solid #000;padding:3pt 6pt;text-align:right;font-weight:700;">TOTAL</td>
                            <td style="border:1px solid #000;padding:3pt 6pt;text-align:right;font-weight:700;">P ${e(fmt(total))}</td></tr>
                    </table>`;
            }

            // Same letterhead markup/proportions as Treasury's _letterhead() (58px logo column,
            // 54px logo height, 46px badge height) — see ftla-form.php's Review-step preview.
            const letterhead = `
                <table style="width:100%;border-collapse:collapse;margin-bottom:10px;"><tr>
                    <td style="width:58px;vertical-align:middle;padding-right:8px;">
                        <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;object-fit:contain;">
                    </td>
                    <td style="vertical-align:middle;text-align:center;">
                        <div style="font-size:12.5pt;font-weight:900;letter-spacing:.01em;line-height:1.15;">NATIONAL HOUSING AUTHORITY</div>
                        <div style="font-size:8pt;color:#62657e;margin-top:1px;">Build Better More Housing</div>
                    </td>
                    <td style="text-align:right;vertical-align:middle;white-space:nowrap;">
                        <img src="/_shared/images/letterhead/pambansang-pabahay.png" alt="Pambansang Pabahay"
                             style="height:46px;margin-right:5px;vertical-align:middle;object-fit:contain;">
                        <img src="/_shared/images/letterhead/bagong-pilipinas.png" alt="Bagong Pilipinas"
                             style="height:46px;vertical-align:middle;object-fit:contain;">
                    </td>
                </tr></table>`;

            // Bank-letter signatories (sig1/sig2) are only ever entered at print time (the
            // Print modal's override fields) — FtlaService::_buildPrintPayload()'s own
            // fallback when nothing has been overridden yet, so the preview matches what
            // an unprinted draft would actually show: blank names, default titles.
            const sigLines = title => title.split('\n').map(l => `<div>${e(l)}</div>`).join('');
            const signatories = `
                <table style="width:100%;border-collapse:collapse;font-size:9.5pt;margin-top:30px;"><tr>
                    <td style="width:48%;vertical-align:top;">
                        <div style="font-weight:700;">${nb(d.sig_left_name || '')}</div>
                        ${sigLines(d.sig_left_title || 'Officer-in-Charge\nTreasury Department')}
                    </td>
                    <td style="width:4%;"></td>
                    <td style="width:48%;vertical-align:top;text-align:right;">
                        <div style="font-weight:700;">${nb(d.sig_right_name || '')}</div>
                        ${sigLines(d.sig_right_title || 'Acting Group Manager\nFinancial Services Group')}
                    </td>
                </tr></table>`;

            // "FOR BANK USE ONLY" — mirrors FtlaPrintService::html()'s own
            // ".bank" block (the actual print/PDF template), kept here too
            // so the live modal preview matches what printing/exporting
            // actually produces. A typed name (from the "For Bank Use Only"
            // fields on the Signatories step) prints underlined above its
            // line; left blank, it's just a blank signature line for the
            // bank to sign by hand — same fallback FtlaPrintService uses.
            const bankFields = [
                ['Signature Verified by:', d.bank_sig_verified],
                ['Checked by:',            d.bank_checked],
                ['Approved by:',           d.bank_approved],
                ['Posted by:',             d.bank_posted],
            ];
            const bankUse = `
                <div style="border-top:1.2pt solid #000;margin-top:16px;padding-top:8px;font-size:10.5pt;">
                    <p style="font-weight:700;margin:0 0 2px;">FOR BANK&#39;S USE ONLY</p>
                    <p style="margin:0 0 14px;">We have debited/credited above accounts as instructed:</p>
                    <table style="width:100%;border-collapse:collapse;font-size:9pt;"><tr>
                        ${bankFields.map(([label]) => `<td style="padding:0 12px 4px 0;white-space:nowrap;">${e(label)}</td>`).join('')}
                    </tr><tr>
                        ${bankFields.map(([, name]) => `<td style="border-top:.75pt solid #000;padding:4px 12px 0 0;font-size:9.5pt;">${name ? '<u>' + e(name) + '</u>' : '&nbsp;'}</td>`).join('')}
                    </tr></table>
                </div>`;

            // "* DV No.s:" + "COE/MOOE <ftla_number>" — mirrors
            // FtlaPrintService::model()'s own dv_numbers/fund_line (no
            // fund_classification override wired into this form, so the
            // "COE/MOOE" prefix is always the default here, same as there).
            const dvNumbers = Array.isArray(d.dv_numbers) ? d.dv_numbers.filter(Boolean) : [];
            const fundLine = dvNumbers.length && d.ftla_number ? ('COE/MOOE ' + d.ftla_number) : '';
            const footer = (dvNumbers.length || fundLine) ? `
                <div style="margin-top:10px;font-size:9pt;">
                    ${dvNumbers.length ? '<div>* DV No.s:</div>' + dvNumbers.map(dv => `<div style="padding-left:2pt;line-height:1.6;">${e(dv)}</div>`).join('') : ''}
                    ${fundLine ? `<div style="font-weight:700;margin-top:6px;">${e(fundLine)}</div>` : ''}
                </div>` : '';

            return `<div style="font-family:Arial,Helvetica,sans-serif;font-size:9.5pt;color:#1A1B21;padding:24px 30px;background:#fff;line-height:1.35;">
                ${letterhead}
                <p style="margin:0 0 8px;">${this._date(d.transfer_date) || '—'}</p>
                ${toHeader}
                ${body}
                <p style="margin:0 0 6px;text-align:justify;font-size:9.5pt;">We declare under penalty of perjury that our co-depositors are still living. This also authorizes you to debit our account for the corresponding service charge.</p>
                <p style="margin:0 0 20px;text-align:justify;font-size:9.5pt;">Kindly provide us with a certified true copy of duly validated Fund Transfer Letter Advice for our record purposes.</p>
                ${signatories}
                ${footer}
                ${bankUse}
            </div>`;
        }
    }

    function ftla_bootstrapApp() {
        document.querySelector('.content-header')?.style.setProperty('display', 'none', 'important');
        window._ftlaApp = new FTLAPreparation(window.FTLAConfig || {});
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ftla_bootstrapApp);
    } else {
        ftla_bootstrapApp();
    }

    function ftl_toggleMenu(btn) {
        const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
        const isOpen = drop.classList.contains('open');
        document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
        if (isOpen) return;
        const rect = btn.getBoundingClientRect();
        drop.style.top   = (rect.bottom + 4) + 'px';
        drop.style.right = (window.innerWidth - rect.right) + 'px';
        drop.classList.add('open');
    }
    function ftl_closeMenus() {
        document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
    }
    document.addEventListener('click', e => {
        if (!e.target.closest('.nha-tools-menu')) ftl_closeMenus();
    });

/* ── FTLA Workflow Meta & Steps ──────────────────────────────────── */
const FTLA_WF_BASE = (window.FTLAConfig?.apiBaseUrl || '').replace(/\/$/, '') + '/';
let ftla_wfActData      = { status_actor_order_map: {}, steps: [], is_preparer: false, preparer_step_order: null };
let ftla_wfMetaData     = {}; // status_value → { name, color, requires_remarks, confirm_message }
let ftla_editableStatuses = new Set();

async function ftla_loadWorkflowMeta() {
    try {
        const token   = window.ew?.API_JWT_TOKEN ?? '';
        const headers = token ? { Authorization: 'Bearer ' + token } : {};
        const res = await fetch(FTLA_WF_BASE + 'ftla/workflow-meta', { credentials: 'same-origin', headers });
        const j   = await res.json();
        if (j.success && j.data?.statuses) {
            j.data.statuses.forEach(s => {
                ftla_wfMetaData[s.value] = s;
                if (s.is_editable) ftla_editableStatuses.add(s.value);
            });
        }
    } catch (_) {}
}

async function ftla_loadWorkflowSteps() {
    try {
        const token   = window.ew?.API_JWT_TOKEN ?? '';
        const headers = token ? { Authorization: 'Bearer ' + token } : {};
        const res = await fetch(FTLA_WF_BASE + 'ftla/workflow-steps', { credentials: 'same-origin', headers });
        const j   = await res.json();
        if (j.success && j.data) {
            ftla_wfActData = j.data;
            // if (j.data.is_preparer) {
            //     document.getElementById('btn-new-ftla').style.display = '';
            // }
        }
    } catch (_) {}
}

function ftla_bootstrapLoad() {
    Promise.all([ftla_loadWorkflowMeta(), ftla_loadWorkflowSteps()]).then(() => {
        window._ftlaApp?._loadTable();
        window._ftlaApp?._loadKPIs();
    });
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', ftla_bootstrapLoad);
} else {
    ftla_bootstrapLoad();
}

function ftla_rowActionItems(r) {
    const actorOrder = ftla_wfActData.status_actor_order_map?.[r.status];
    if (actorOrder === undefined) return '';
    const stepData = ftla_wfActData.steps?.find(s => s.step_order === actorOrder);
    if (!stepData?.statuses?.length) return '';
    const COLOR_STYLE = { success: 'color:#059669', primary: 'color:#0f2045', warning: 'color:#b45309', danger: 'color:#dc2626', info: 'color:#0284c7' };
    const esc = s => { const d = document.createElement('span'); d.textContent = String(s ?? ''); return d.innerHTML; };
    const items = stepData.statuses.map(st => {
        const style = COLOR_STYLE[st.color] || '';
        return `<a class="nha-menu-item" href="javascript:void(0);" style="${style}"
                   data-id="${r.fund_transfer_id}"
                   data-status="${esc(st.value)}"
                   data-label="${esc(st.name)}"
                   data-confirm="${esc(st.confirm_message || '')}"
                   data-requires-remarks="${st.requires_remarks ? '1' : '0'}"
                   onclick="ftla_triggerAction(this);ftl_closeMenus();">
                   ${esc(st.name)}
               </a>`;
    }).join('');
    return `<div class="nha-menu-divider"></div>${items}`;
}

/* ── FTLA Workflow Action (list-page) ────────────────────────────── */
let _ftla_rmk_cb = null;

function ftla_triggerAction(btn) {
    const id           = btn.dataset.id;
    const status       = btn.dataset.status;
    const confirmMsg   = btn.dataset.confirm;
    const needsRemarks = btn.dataset.requiresRemarks === '1';
    if (needsRemarks) {
        document.getElementById('ftla-remarks-title').textContent = confirmMsg || 'Remarks Required';
        document.getElementById('ftla-remarks-msg').textContent   = 'Please provide a reason for this action.';
        _ftla_rmk_cb = remarks => ftla_doAction(id, status, remarks);
        ftla_openRemarks();
        return;
    }
    Swal.fire({
        title: btn.dataset.label || 'Confirm Action',
        text: confirmMsg,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0f2045',
    }).then(result => {
        if (result.isConfirmed) ftla_doAction(id, status, '');
    });
}

function ftla_openRemarks() {
    const inp = document.getElementById('ftla-remarks-input');
    const err = document.getElementById('ftla-remarks-err');
    if (inp) inp.value = '';
    if (err) err.style.display = 'none';
    const ov = document.getElementById('ftla-remarks-overlay');
    if (ov) { ov.style.display = 'flex'; setTimeout(() => inp?.focus(), 50); }
}

function ftla_closeRemarks() {
    const ov = document.getElementById('ftla-remarks-overlay');
    if (ov) ov.style.display = 'none';
    _ftla_rmk_cb = null;
}

function ftla_confirmRemarks() {
    const inp = document.getElementById('ftla-remarks-input');
    const err = document.getElementById('ftla-remarks-err');
    const val = inp?.value.trim() || '';
    if (!val) { if (err) err.style.display = ''; return; }
    const cb = _ftla_rmk_cb;
    ftla_closeRemarks();
    if (typeof cb === 'function') cb(val);
}

async function ftla_doAction(id, status, remarks) {
    try {
        const token   = window.ew?.API_JWT_TOKEN ?? '';
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers.Authorization = 'Bearer ' + token;
        const res = await fetch(FTLA_WF_BASE + `ftla/update/${id}`, {
            method: 'POST', credentials: 'same-origin', headers,
            body: JSON.stringify({ status, ...(remarks ? { remarks } : {}) }),
        });
        const j = await res.json();
        if (!j.success) throw new Error(j.message || 'Action failed.');
        const _meta  = ftla_wfMetaData[status];
        const _label = _meta?.name ?? status.replace(/_/g, ' ');
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: 'Success', text: 'Status updated: ' + _label + '.', timer: 2000, showConfirmButton: false, timerProgressBar: true });
        } else {
            nha_toast('Status updated: ' + _label + '.', 'success');
        }
        window._ftlaApp?._loadTable?.();
        window._ftlaApp?._loadKPIs?.();
    } catch (e) {
        nha_toast('Error: ' + e.message, 'error');
    }
}

/* ── NHA Toast / Alert / Confirm utilities ──────────────────────── */
function nha_toast(msg, type, title) {
    const ICONS = {
        success: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--afims-success-hover, #0C7539)"><polyline points="20 6 9 17 4 12"/></svg>',
        error:   '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--afims-danger, #DC3545)"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        warning: '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--afims-warning-text, #7E6908)"><path d="M10 3L19 17H1z"/><line x1="10" y1="9" x2="10" y2="13"/><circle cx="10" cy="15.5" r=".5" fill="currentColor"/></svg>',
        info:    '<svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color:var(--afims-primary-text, #0035A9)"><circle cx="10" cy="10" r="9"/><line x1="10" y1="9" x2="10" y2="14"/><circle cx="10" cy="6.5" r=".5" fill="currentColor"/></svg>',
    };
    const LABELS = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Info' };
    type = type || 'info';
    let c = document.getElementById('nha-toast-container');
    if (!c) { c = document.createElement('div'); c.id = 'nha-toast-container'; document.body.appendChild(c); }
    const t = document.createElement('div');
    t.className = `nha-toast nha-toast-${type}`;
    t.innerHTML = `<div class="nha-toast-ico">${ICONS[type] || ICONS.info}</div><div class="nha-toast-body"><div class="nha-toast-title">${title || LABELS[type] || 'Notice'}</div><div class="nha-toast-msg">${msg}</div></div><button class="nha-toast-close" onclick="this.closest('.nha-toast').remove()">×</button>`;
    c.appendChild(t);
    requestAnimationFrame(() => { requestAnimationFrame(() => t.classList.add('show')); });
    setTimeout(() => { t.classList.remove('show'); t.addEventListener('transitionend', () => t.remove(), { once: true }); }, 3500);
}
function nha_alert(title, msg) {
    let ov = document.getElementById('nha-alert-overlay');
    if (!ov) {
        ov = document.createElement('div'); ov.id = 'nha-alert-overlay';
        ov.innerHTML = `<div id="nha-alert-box"><div id="nha-alert-hdr"><div id="nha-alert-hdr-title"><svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="10" cy="10" r="9"/><line x1="10" y1="9" x2="10" y2="14"/><circle cx="10" cy="6.5" r=".5" fill="currentColor"/></svg><span id="nha-alert-hdr-text"></span></div><button id="nha-alert-close">×</button></div><div id="nha-alert-body"></div><div id="nha-alert-foot"><button id="nha-alert-ok">OK</button></div></div>`;
        document.body.appendChild(ov);
        const close = () => { ov.style.display = 'none'; if (ov._resolve) ov._resolve(); };
        document.getElementById('nha-alert-close').addEventListener('click', close);
        document.getElementById('nha-alert-ok').addEventListener('click', close);
    }
    document.getElementById('nha-alert-hdr-text').textContent = title || 'Notice';
    document.getElementById('nha-alert-body').textContent = msg || '';
    ov.style.display = 'flex';
    return new Promise(r => { ov._resolve = r; });
}
function nha_confirm(title, msg, actionLabel, actionStyle, onConfirm) {
    let ov = document.getElementById('nha-confirm-overlay');
    if (!ov) {
        ov = document.createElement('div'); ov.id = 'nha-confirm-overlay';
        ov.innerHTML = `<div id="nha-confirm-box"><div id="nha-confirm-hdr"><div id="nha-confirm-hdr-title"></div><button id="nha-confirm-close">×</button></div><div id="nha-confirm-body"></div><div id="nha-confirm-foot"><button class="nha-confirm-btn-cancel" id="nha-confirm-cancel">Cancel</button><button class="nha-confirm-btn-action" id="nha-confirm-action"></button></div></div>`;
        document.body.appendChild(ov);
        document.getElementById('nha-confirm-close').addEventListener('click', () => { ov.style.display = 'none'; });
        document.getElementById('nha-confirm-cancel').addEventListener('click', () => { ov.style.display = 'none'; });
    }
    document.getElementById('nha-confirm-hdr-title').textContent = title || 'Confirm';
    document.getElementById('nha-confirm-body').textContent = msg || '';
    const btn = document.getElementById('nha-confirm-action');
    btn.textContent = actionLabel || 'Confirm';
    btn.className = `nha-confirm-btn-action ${actionStyle || 'navy'}`;
    btn.onclick = () => { ov.style.display = 'none'; if (typeof onConfirm === 'function') onConfirm(); };
    ov.style.display = 'flex';
}
</script>

<?php include __DIR__ . '/form/ftla-form.php'; ?>
<?php include __DIR__ . '/readone/ftla-readone.php'; ?>
<?php /* The "Create JEV" button in the status-tracking modal degrades gracefully (see
         _createJevFromFtla() above) when the Journal Entry Voucher form isn't on the page —
         form/jev-form.php isn't part of this port, so its include is intentionally omitted. */ ?>
