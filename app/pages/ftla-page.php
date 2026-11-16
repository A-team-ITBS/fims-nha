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
<style>
    /* ── Tools dropdown menu ─────────────────────────────────────── */
    .nha-tools-menu { position: relative; display: inline-block; }
    .nha-menu-trigger {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 5px 10px; border-radius: 6px; font-size: 11px;
        font-weight: 600; cursor: pointer; border: 1px solid var(--afims-border, #E4ECF2);
        background: var(--afims-surface, #FFFFFF); color: var(--afims-text, #1A1B21); white-space: nowrap;
        font-family: 'IBM Plex Sans', sans-serif; transition: all .15s;
    }
    .nha-menu-trigger:hover { background: var(--afims-surface-subtle, #F4F7FA); border-color: var(--afims-text-disabled, #A9AAB8); }
    .nha-menu-trigger svg { width: 12px; height: 12px; flex-shrink: 0; }
    .nha-tools-dropdown {
        display: none; position: fixed;
        min-width: 165px; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--afims-border, #E4ECF2);
        border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.12);
        z-index: 9999; padding: 4px 0;
    }
    .nha-tools-dropdown.open { display: block; }
    .nha-menu-item {
        display: flex; align-items: center; gap: 8px;
        padding: 8px 12px; font-size: 12px; font-weight: 500;
        color: var(--afims-text, #1A1B21); text-decoration: none; white-space: nowrap;
        cursor: pointer; transition: background .1s;
    }
    .nha-menu-item:hover { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-heading, #000E3E); }
    .nha-menu-item svg { width: 13px; height: 13px; flex-shrink: 0; opacity: .7; }
    .nha-menu-divider { border-top: 1px solid var(--afims-border, #E4ECF2); margin: 3px 0; }
    /* ── NHA Toast ──────────────────────────────────────────────── */
    #nha-toast-container{position:fixed;top:24px;right:24px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;}
    .nha-toast{display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border-radius:10px;background:var(--afims-surface, #FFFFFF);box-shadow:0 8px 24px rgba(0,0,0,.15),0 2px 6px rgba(0,0,0,.08);font-family:'IBM Plex Sans',sans-serif;font-size:13px;font-weight:500;color:var(--afims-text, #1A1B21);min-width:260px;max-width:360px;pointer-events:auto;transform:translateX(120%);transition:transform .3s cubic-bezier(.34,1.56,.64,1),opacity .3s;opacity:0;border-left:4px solid var(--afims-border, #E4ECF2);}
    .nha-toast.show{transform:translateX(0);opacity:1;}
    .nha-toast-success{border-left-color:var(--afims-success, #11A752);}.nha-toast-error{border-left-color:var(--afims-danger, #DC3545);}.nha-toast-warning{border-left-color:var(--afims-warning, #FCD20F);}.nha-toast-info{border-left-color:var(--afims-primary, #0035A9);}
    .nha-toast-ico{width:18px;height:18px;flex-shrink:0;margin-top:1px;}.nha-toast-body{flex:1;}
    .nha-toast-title{font-weight:700;font-size:12px;margin-bottom:2px;text-transform:uppercase;letter-spacing:.04em;}.nha-toast-msg{font-size:12.5px;color:var(--afims-text-muted, #62657E);}
    .nha-toast-close{border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:16px;line-height:1;padding:0;flex-shrink:0;}.nha-toast-close:hover{color:var(--afims-text-muted, #62657E);}
    /* ── NHA Alert Modal ─────────────────────────────────────────── */
    #nha-alert-overlay{position:fixed;inset:0;z-index:99990;background:rgba(15,32,69,.45);display:none;align-items:center;justify-content:center;padding:16px;}
    #nha-alert-box{background:var(--afims-surface, #FFFFFF);border-radius:14px;width:420px;max-width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;font-family:'IBM Plex Sans',sans-serif;}
    #nha-alert-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 13px;background:var(--afims-heading-fill, #000E3E);}
    #nha-alert-hdr-title{font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);display:flex;align-items:center;gap:8px;}
    #nha-alert-hdr-title svg{width:16px;height:16px;flex-shrink:0;}#nha-alert-close{border:none;background:none;cursor:pointer;color:rgba(255,255,255,.6);font-size:20px;line-height:1;padding:0;}#nha-alert-close:hover{color:var(--afims-pure-white, #FFFFFF);}
    #nha-alert-body{padding:20px 22px 8px;font-size:13.5px;color:var(--afims-text, #1A1B21);line-height:1.55;}
    #nha-alert-foot{padding:12px 22px 18px;display:flex;justify-content:flex-end;}
    #nha-alert-ok{height:34px;padding:0 22px;background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF);border:none;border-radius:8px;font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;}#nha-alert-ok:hover{background:var(--afims-heading-fill, #000E3E);}
    /* ── NHA Confirm Modal ───────────────────────────────────────── */
    #nha-confirm-overlay{position:fixed;inset:0;z-index:99991;background:rgba(15,32,69,.45);display:none;align-items:center;justify-content:center;padding:16px;}
    #nha-confirm-box{background:var(--afims-surface, #FFFFFF);border-radius:14px;width:420px;max-width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);overflow:hidden;font-family:'IBM Plex Sans',sans-serif;}
    #nha-confirm-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 13px;background:var(--afims-heading-fill, #000E3E);}
    #nha-confirm-hdr-title{font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);}#nha-confirm-close{border:none;background:none;cursor:pointer;color:rgba(255,255,255,.6);font-size:20px;line-height:1;padding:0;}#nha-confirm-close:hover{color:var(--afims-pure-white, #FFFFFF);}
    #nha-confirm-body{padding:20px 22px 8px;font-size:13.5px;color:var(--afims-text, #1A1B21);line-height:1.55;}
    #nha-confirm-foot{padding:12px 22px 18px;display:flex;justify-content:flex-end;gap:8px;}
    .nha-confirm-btn-cancel{height:34px;padding:0 18px;background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;}.nha-confirm-btn-cancel:hover{background:var(--afims-surface-subtle, #F4F7FA);}
    .nha-confirm-btn-action{height:34px;padding:0 22px;border:none;border-radius:8px;font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;color:var(--afims-pure-white, #FFFFFF);}
    .nha-confirm-btn-action.danger{background:var(--afims-danger, #DC3545);}.nha-confirm-btn-action.danger:hover{background:var(--afims-danger, #DC3545);}
    .nha-confirm-btn-action.navy{background:var(--afims-heading-fill, #000E3E);}.nha-confirm-btn-action.navy:hover{background:var(--afims-heading-fill, #000E3E);}
</style>

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

</div><!-- /#ftla-root -->

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
        static MODAL_IDS = ['modal-status-track', 'modal-attachments', 'modal-view-docs', 'modal-print'];
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
                const win = window.open(res.view_url, '_blank',
                    `width=${Math.floor(screen.width/2)},height=${screen.height},left=0,top=0,menubar=no,toolbar=no,location=no,status=no`);
                if (!win) this._toast('Pop-up blocked. Please allow pop-ups for this site to print.', 'error');
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
