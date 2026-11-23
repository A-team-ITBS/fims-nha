<?php
/**
 * Bank Account Management — Treasury module.
 *
 * Ported from the TREASURY reference project's
 * _assets/TREASURY/app/pages/bankaccmanagement.php, adapted to this
 * project's schema (see app/services/BankManagementService.php's docblock
 * for the field-level mapping: region/office is always keyed by
 * Responsibility Center (fims.responsibility_code), not uac.departments).
 *
 * Unlike TREASURY's version (jQuery DataTables, server-side processing),
 * this page follows the same lightweight fetch + manual render + client
 * pagination convention already established in
 * check-disbursement-voucher-page.php, so the module doesn't pull in a new
 * jQuery/DataTables dependency.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-modal-overlay, etc.) match app/fims-css/fims.css.
 */

namespace PHPMaker2024\FIMS;

$bamCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $bamCssVer ?>" />
<!-- nha-fims.css: shared design tokens (--afims-*) used throughout this page's styles. -->
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div class="fims-wrap" id="bamModule">

    <!-- same banner as User Access: app-page-header (styled by app/fims-css/fims.css, already loaded above) -->
    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-landmark"></i></div>
        <div>
            <h1 class="app-page-title">Bank Account Management</h1>
            <p class="app-page-subtitle">Register, update, and monitor all treasury bank accounts</p>
        </div>
        <div class="app-page-header-actions">
            <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="bamDormancyBtn">
                <i class="fas fa-bell mr-1"></i>Dormancy Alerts
                <span class="fims-badge fims-badge-red" id="bamDormancyBadge" style="display:none;margin-left:6px;">0</span>
            </button>
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="bamRegisterBtn">
                <i class="fas fa-plus mr-1"></i>Register Account
            </button>
        </div>
    </div>

    <div class="fims-stat-grid" id="bamStats" style="grid-template-columns:repeat(5,1fr)">
        <div class="fims-stat-card fims-card-blue">
            <div class="fims-stat-top"><span class="fims-stat-label">Total Accounts</span><span class="fims-stat-icon"><i class="fas fa-university"></i></span></div>
            <div class="fims-stat-value" data-kpi="total">0</div>
        </div>
        <div class="fims-stat-card fims-card-green">
            <div class="fims-stat-top"><span class="fims-stat-label">Active</span><span class="fims-stat-icon"><i class="fas fa-check-circle"></i></span></div>
            <div class="fims-stat-value" data-kpi="active">0</div>
        </div>
        <div class="fims-stat-card fims-card-amber">
            <div class="fims-stat-top"><span class="fims-stat-label">Dormant</span><span class="fims-stat-icon"><i class="fas fa-clock"></i></span></div>
            <div class="fims-stat-value" data-kpi="dormant">0</div>
            <div class="fims-stat-sub">No transaction &ge; 1 year</div>
        </div>
        <div class="fims-stat-card fims-card-gray">
            <div class="fims-stat-top"><span class="fims-stat-label">Closed</span><span class="fims-stat-icon"><i class="fas fa-times-circle"></i></span></div>
            <div class="fims-stat-value" data-kpi="closed">0</div>
        </div>
        <div class="fims-stat-card fims-card-orange">
            <div class="fims-stat-top"><span class="fims-stat-label">Near Dormant</span><span class="fims-stat-icon"><i class="fas fa-exclamation-circle"></i></span></div>
            <div class="fims-stat-value" data-kpi="near_dormant">0</div>
            <div class="fims-stat-sub">Within 2-month warning window</div>
        </div>
    </div>

    <div class="fims-table-panel" id="bamDormancyPanel" style="display:none;">
        <div class="fims-panel-header">
            <div class="fims-panel-title"><i class="fas fa-bell mr-1"></i>Dormancy Alerts</div>
            <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="bamDormancyCloseBtn">&times;</button>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table">
                <thead><tr><th>Account Name</th><th>Account Number</th><th>Bank</th><th>Last Transaction</th><th class="text-right">Days Inactive</th></tr></thead>
                <tbody id="bamDormancyBody"><tr class="fims-empty"><td colspan="5">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
        <div class="fims-toolbar">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <select class="fims-select fims-input-sm" id="bamFilterStatus" style="width:140px">
                    <option value="">All Statuses</option>
                    <option value="ACTIVE">Active</option>
                    <option value="DORMANT">Dormant</option>
                    <option value="CLOSED">Closed</option>
                </select>
                <input type="text" class="fims-input fims-input-sm" id="bamSearch" placeholder="Search name, number, bank…" style="width:220px">
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="bamFilterBtn"><i class="fas fa-filter mr-1"></i>Filter</button>
                <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="bamResetBtn">Reset</button>
            </div>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:0">
        <div class="fims-panel-header">
            <div class="fims-panel-title" id="bamTableSubtitle">Bank Accounts</div>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table" id="bamTable">
                <thead>
                    <tr>
                        <th style="width:36px">#</th>
                        <th>Account Name</th>
                        <th>Account Number</th>
                        <th>Bank / Branch</th>
                        <th>Region / Office</th>
                        <th class="text-center">Main</th>
                        <th>Assigned Main Depository</th>
                        <th class="text-center">Status</th>
                        <th>Date Opened</th>
                        <th class="text-center" style="width:110px">Actions</th>
                    </tr>
                </thead>
                <tbody id="bamTableBody"><tr class="fims-empty"><td colspan="10">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Register / Edit Account
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-bam-account" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:960px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="bamModalTitle">Register Account</div></div>
                    <button class="fims-modal-close" data-close="m-bam-account">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="field-bank-account-id">

                    <div class="fims-form-section-title">Bank Information</div>
                    <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="fims-form-group">
                            <label class="fims-label">Bank Name *</label>
                            <select class="fims-select" id="field-bank-name-select">
                                <option value="">— Select Bank —</option>
                                <option>Land Bank of the Philippines</option>
                                <option>Development Bank of the Philippines</option>
                                <option>Philippine National Bank</option>
                                <option>Bangko Sentral ng Pilipinas</option>
                                <option>Banco De Oro</option>
                                <option>Metropolitan Bank and Trust Company</option>
                                <option>Bank of the Philippine Islands</option>
                                <option>Union Bank of the Philippines</option>
                                <option>Philippine Veterans Bank</option>
                                <option value="__other__">Other…</option>
                            </select>
                            <input type="text" class="fims-input mt-1" id="field-bank-name" placeholder="Bank name" style="display:none;">
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Bank Branch</label>
                            <input type="text" class="fims-input" id="field-bank-branch">
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Status *</label>
                            <select class="fims-select" id="field-status">
                                <option value="ACTIVE">Active</option>
                                <option value="DORMANT">Dormant</option>
                                <option value="CLOSED">Closed</option>
                            </select>
                        </div>
                        <div class="fims-form-group" style="display:flex;align-items:flex-end;">
                            <!-- Tags THIS account as Main Depository on Save (payload field
                                 read by BankManagementService::store()/update()). Just a
                                 plain per-account flag — no exclusivity, doesn't touch any
                                 other account's flag. -->
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;">
                                <input type="checkbox" id="field-is-main-depository">
                                Main Depository Account
                            </label>
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Date Opened</label>
                            <input type="date" class="fims-input" id="field-date-opened">
                        </div>
                        <div class="fims-form-group">
                            <!-- A separate column from is_main_depository (above) —
                                 self-referencing FK into fims.bank_accounts (see migration
                                 "55 - bank_accounts_add_main_depository_account_id.sql").
                                 is_main_depository flags THIS account AS a Main Depository;
                                 this instead records WHICH other account is ASSIGNED as this
                                 account's Main Depository. Populated by
                                 refreshAssignedMainDepositorySelect() below — only lists
                                 accounts actually tagged is_main_depository, minus whichever
                                 account is open in this modal (can't assign itself). -->
                            <label class="fims-label">Assigned Main Depository Account</label>
                            <select class="fims-select" id="field-main-depository-account-id">
                                <option value="">— None —</option>
                            </select>
                        </div>
                    </div>

                    <div class="fims-form-section-title" style="margin-top:14px;">Account Information</div>
                    <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="fims-form-group">
                            <label class="fims-label">Account Name *</label>
                            <input type="text" class="fims-input" id="field-account-name">
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Account Number *</label>
                            <input type="text" class="fims-input" id="field-account-number">
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Account Type</label>
                            <select class="fims-select" id="field-account-type">
                                <option value="">— Select —</option>
                                <option value="CURRENT">Current</option>
                                <option value="SAVINGS">Savings</option>
                                <option value="NON_DRAWING_SAVINGS">Non-Drawing Savings</option>
                                <option value="TIME_DEPOSIT">Time Deposit</option>
                                <option value="TRUST">Trust</option>
                            </select>
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">Region / Office (Responsibility Center)</label>
                            <select class="fims-select" id="field-rc-id">
                                <option value="">— Main Office —</option>
                            </select>
                        </div>
                    </div>

                    <div class="fims-form-section-title" style="margin-top:14px;">1. Search GL Account</div>
                    <div class="fims-form-group" style="position:relative;">
                        <label class="fims-label">GL Account (Chart of Accounts)</label>
                        <input type="hidden" id="field-gl-account-id" value="">
                        <input type="text" class="fims-input" id="field-gl-search" maxlength="500" placeholder="Type GL account code or title…">
                        <div id="field-gl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                    </div>
                    <div id="field-gl-selected" style="padding:10px;background:var(--fims-surface-subtle,#F4F7FA);border-radius:6px;display:none;margin-top:4px;"></div>

                    <div id="field-sl-section" style="margin-top:14px;opacity:.5;pointer-events:none;">
                        <div class="fims-form-section-title">2. Select or Create Sub-Ledger (Child Account)</div>
                        <div class="fims-form-group" style="position:relative;">
                            <label class="fims-label">Search Sub-Ledger</label>
                            <input type="text" class="fims-input" id="field-sl-search" maxlength="500" placeholder="Select a GL account first…" disabled>
                            <div id="field-sl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                        </div>
                        <p id="field-sl-empty-note" style="display:none;font-size:11px;color:var(--fims-danger,#DC3545);margin:4px 0 0;">No child accounts exist under this GL account yet. Create one below.</p>
                        <div id="field-sl-selected-item" style="padding:8px;background:var(--fims-surface-subtle,#F4F7FA);border-radius:6px;display:none;margin-top:8px;">
                            <strong>Selected:</strong> <span id="field-sl-selected-code"></span>
                        </div>
                        <input type="hidden" id="field-sl-selected-code-value">
                        <div style="margin-top:10px;border-top:1px dashed var(--fims-border);padding-top:10px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                            <div class="fims-form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                                <label class="fims-label">New Sub-Ledger Title</label>
                                <input type="text" class="fims-input" id="field-sl-new-title" maxlength="255" placeholder="e.g., MDS Checking Account">
                            </div>
                            <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="field-sl-create-btn" style="height:38px;">+ Create Sub-Ledger</button>
                        </div>
                    </div>

                    <!-- Restricted funds don't live on fims.bank_accounts itself —
                         they're part of this account's today's fims.cash_position row
                         (same table/shape as Cash Position's "Add Restricted Fund").
                         Saving the account, when anything here is filled in, makes a
                         second call to the Cash Position API right after
                         (saveRestrictedFundsForAccount()) — same computed-balance +
                         save sequence Cash Position's own Add flow uses, just reachable
                         from here too so a newly-registered account doesn't need a
                         separate trip to Cash Position just to set this. -->
                    <div class="fims-form-section-title" style="margin-top:14px;">Restricted Funds (optional — itemized, or leave empty to use a flat amount)</div>
                    <table class="fims-table" id="field-rf-table">
                        <thead><tr><th>Label</th><th style="width:150px">Amount</th><th style="width:40px"></th></tr></thead>
                        <tbody id="field-rf-body"></tbody>
                    </table>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="field-rf-add-row-btn"><i class="fas fa-plus mr-1"></i>Add Line</button>
                    <div class="fims-form-group" style="margin-top:8px;max-width:240px;">
                        <label class="fims-label">Flat Restricted Amount</label>
                        <input type="number" step="0.01" class="fims-input" id="field-restricted-amount" value="0">
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-bam-account">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="bamSaveBtn">Save Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Quick Tag GL Account Code
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-bam-tag-code" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:480px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div>
                        <div class="fims-modal-title">Tag GL / Sub-Ledger Account</div>
                        <div style="font-size:12px;color:var(--fims-text-subtle)" id="tagCodeAccountName"></div>
                    </div>
                    <button class="fims-modal-close" data-close="m-bam-tag-code">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-form-section-title">1. Search GL Account</div>
                    <div class="fims-form-group" style="position:relative;">
                        <label class="fims-label">GL Account (Chart of Accounts)</label>
                        <input type="hidden" id="tag-gl-account-id" value="">
                        <input type="text" class="fims-input" id="tag-gl-search" maxlength="500" placeholder="Type GL account code or title…">
                        <div id="tag-gl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                    </div>
                    <div id="tag-gl-selected" style="padding:10px;background:var(--fims-surface-subtle,#F4F7FA);border-radius:6px;display:none;margin-top:4px;"></div>

                    <div id="tag-sl-section" style="margin-top:14px;opacity:.5;pointer-events:none;">
                        <div class="fims-form-section-title">2. Select or Create Sub-Ledger (Child Account)</div>
                        <div class="fims-form-group" style="position:relative;">
                            <label class="fims-label">Search Sub-Ledger</label>
                            <input type="text" class="fims-input" id="tag-sl-search" maxlength="500" placeholder="Select a GL account first…" disabled>
                            <div id="tag-sl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:180px;overflow-y:auto;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                        </div>
                        <p id="tag-sl-empty-note" style="display:none;font-size:11px;color:var(--fims-danger,#DC3545);margin:4px 0 0;">No child accounts exist under this GL account yet. Create one below.</p>
                        <div id="tag-sl-selected-item" style="padding:8px;background:var(--fims-surface-subtle,#F4F7FA);border-radius:6px;display:none;margin-top:8px;">
                            <strong>Selected:</strong> <span id="tag-sl-selected-code"></span>
                        </div>
                        <input type="hidden" id="tag-sl-selected-code-value">
                        <div style="margin-top:10px;border-top:1px dashed var(--fims-border);padding-top:10px;display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                            <div class="fims-form-group" style="flex:1;min-width:160px;margin-bottom:0;">
                                <label class="fims-label">New Sub-Ledger Title</label>
                                <input type="text" class="fims-input" id="tag-sl-new-title" maxlength="255">
                            </div>
                            <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="tag-sl-create-btn" style="height:38px;">+ Create</button>
                        </div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-bam-tag-code">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="tagCodeSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <div id="bamToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>
</div>

<style>
/* Dark-blue theme, scoped to this module only — same convention used by
   #cpModule in check-disbursement-voucher-page.php, so every treasury-ish
   module in this app shares one accent instead of the shared rainbow
   .fims-card-* palette. */
#bamModule .fims-card-blue,
#bamModule .fims-card-green,
#bamModule .fims-card-amber,
#bamModule .fims-card-orange,
#bamModule .fims-card-gray { --fims-card-color: #000E3E; }

#bamModule .fims-btn-primary { background:#000E3E; color:#fff; }
#bamModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#bamModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#bamModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

/* .fims-modal-content (shared fims.css) is a plain block, not a flex
   container, so .fims-modal-body's flex:1/min-height:0/overflow-y:auto
   (which assume a flex-column parent) never actually engage — content
   just grows past .fims-modal-dialog's max-height and gets clipped by its
   overflow:hidden instead of scrolling. The Register/Edit Account modal
   (Account Info + the GL+SL two-step picker + Bank Info, all shown at
   once, no step wizard) is tall enough to hit this, so make this module's
   modal content a proper flex column to let the body scroll as intended. */
#bamModule .fims-modal-content { display:flex; flex-direction:column; min-height:0; overflow:hidden; }

.fims-form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--fims-text-subtle); margin-bottom:8px; }

/* ── Row Actions dropdown (same menu as the Memo Requests / Responsibility Center lists) ── */
#bamModule .bam-menu { position:relative; display:inline-block; }
#bamModule .bam-menu-trigger { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border-radius:6px; font-size:11px; font-weight:600;
    cursor:pointer; white-space:nowrap; border:1px solid var(--fims-border, #E4ECF2); background:var(--afims-surface, #FFFFFF); color:var(--afims-text, #1A1B21); }
#bamModule .bam-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA); }
#bamModule .bam-menu-drop { display:none; position:fixed; z-index:9999; min-width:190px; padding:4px 0; text-align:left;
    background:var(--afims-surface, #FFFFFF); border:1px solid var(--fims-border, #E4ECF2); border-radius:8px; box-shadow:0 8px 24px rgba(0,0,0,.12); }
#bamModule .bam-menu-drop.open { display:block; }
#bamModule .bam-menu-item { display:flex; align-items:center; gap:8px; width:100%; padding:8px 12px; font-size:12px; font-weight:500; text-align:left;
    white-space:nowrap; cursor:pointer; border:none; background:none; color:var(--afims-text, #1A1B21); }
#bamModule .bam-menu-item i { width:13px; opacity:.7; }
#bamModule .bam-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA); color:#000E3E; }

/* ── Dark mode — the page hard-codes navy (#000E3E) for its primary / outline buttons and active step (navy on dark, and
   invisible on the navy header), and the selects use the browser's native grey ── */
html.afims-dark[data-bs-theme="dark"] #bamModule { color-scheme:dark; }
html.afims-dark[data-bs-theme="dark"] #bamModule .fims-btn-primary { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #bamModule .fims-btn-primary:hover { background:#2E59B8; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #bamModule .fims-btn-outline { color:#94AADB; border-color:#5B7FCF; background:transparent; }
html.afims-dark[data-bs-theme="dark"] #bamModule .fims-btn-outline:hover { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #bamModule .fims-btn-ghost { color:var(--afims-text, #E4ECF2); }
/* page header: the Dormancy Alerts / Register Account buttons sit on the right of the navy banner, above its decorative
   circles — Dormancy Alerts as a translucent white button, Register Account in the green the other pages use for Add
   (the page's navy primary would vanish on the navy banner); the same in light and dark mode */
#bamModule .app-page-header { flex-wrap:wrap; }
#bamModule .app-page-header-actions { position:relative; z-index:1; margin-left:auto; display:flex; flex-wrap:wrap; gap:8px; align-items:center; }
#bamModule .app-page-header-actions .fims-btn-ghost,
html.afims-dark[data-bs-theme="dark"] #bamModule .app-page-header-actions .fims-btn-ghost { background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.25); color:#fff; }
#bamModule .app-page-header-actions .fims-btn-ghost:hover,
html.afims-dark[data-bs-theme="dark"] #bamModule .app-page-header-actions .fims-btn-ghost:hover { background:rgba(255,255,255,.22); color:#fff; }
#bamModule .app-page-header-actions .fims-btn-primary,
html.afims-dark[data-bs-theme="dark"] #bamModule .app-page-header-actions .fims-btn-primary { background:#11A752; border-color:#11A752; color:#fff; }
#bamModule .app-page-header-actions .fims-btn-primary:hover,
html.afims-dark[data-bs-theme="dark"] #bamModule .app-page-header-actions .fims-btn-primary:hover { background:#0E8F46; border-color:#0E8F46; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #bamModule :is(select.fims-select, .fims-input) {
    background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #bamModule select.fims-select option { background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #bamModule .fims-input::placeholder { color:#9AA3B5; }
html.afims-dark[data-bs-theme="dark"] #bamModule .bam-menu-trigger { color:#fff; background:#3861BC; border-color:#3861BC; }
html.afims-dark[data-bs-theme="dark"] #bamModule .bam-menu-trigger:hover { background:#2E59B8; }
html.afims-dark[data-bs-theme="dark"] #bamModule .bam-menu-drop { background:var(--afims-surface, #1A1F38); border-color:var(--afims-border-strong, #4A5270); box-shadow:0 8px 24px rgba(0,0,0,.45); }
html.afims-dark[data-bs-theme="dark"] #bamModule .bam-menu-item { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #bamModule .bam-menu-item i { opacity:.85; }
html.afims-dark[data-bs-theme="dark"] #bamModule .bam-menu-item:hover { color:#fff; background:var(--afims-primary-soft, #0F2456); }
</style>

<script>
(function () {
    const API = '/api/bank-management';
    // Restricted Funds entered here is saved as this account's TODAY cash
    // position entry via the Cash Position module's own API — see
    // saveRestrictedFundsForAccount(), called right after a successful
    // account save. There is no restricted_amount column on
    // fims.bank_accounts itself.
    const CASH_POSITION_API = '/api/cash-position';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function jsonHeaders(extra) {
        return authHeaders(Object.assign({ 'Content-Type': 'application/json' }, extra || {}));
    }

    function toast(msg, isError) {
        const el = document.getElementById('bamToast');
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
    document.querySelectorAll('#bamModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.style.display = 'none';
        });
    });

    function statusBadge(status) {
        if (status === 'ACTIVE') return '<span class="fims-badge fims-badge-green">Active</span>';
        if (status === 'DORMANT') return '<span class="fims-badge fims-badge-amber">Dormant</span>';
        if (status === 'CLOSED') return '<span class="fims-badge fims-badge-gray">Closed</span>';
        return '<span class="fims-badge fims-badge-gray">' + escapeHtml(status || '—') + '</span>';
    }

    let responsibilityCentersById = {};

    // ── Stats ──
    function loadStats() {
        fetch(API + '/stats', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = (json && json.data) ? json.data : {};
                document.querySelectorAll('#bamStats [data-kpi]').forEach(function (el) {
                    el.textContent = d[el.dataset.kpi] ?? 0;
                });
                const badge = document.getElementById('bamDormancyBadge');
                if (d.near_dormant > 0) {
                    badge.style.display = '';
                    badge.textContent = d.near_dormant;
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    }

    // ── Dormancy panel ──
    document.getElementById('bamDormancyBtn').addEventListener('click', function () {
        const panel = document.getElementById('bamDormancyPanel');
        const show = panel.style.display === 'none';
        panel.style.display = show ? '' : 'none';
        if (show) loadDormancyAlerts();
    });
    document.getElementById('bamDormancyCloseBtn').addEventListener('click', function () {
        document.getElementById('bamDormancyPanel').style.display = 'none';
    });

    function loadDormancyAlerts() {
        const tbody = document.getElementById('bamDormancyBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">Loading…</td></tr>';
        fetch(API + '/dormancy-alerts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">No accounts within the warning window.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.account_name) + '</td>' +
                        '<td>' + escapeHtml(r.account_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.bank_name || '') + '</td>' +
                        '<td>' + escapeHtml(r.last_transaction_date || '') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.days_inactive) + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">Could not reach the server.</td></tr>'; });
    }

    // ── GL + Sub-Ledger two-step picker ──────────────────────────────
    // Shared by the Register/Edit Account modal's step-1 fields (prefix
    // 'field-') and the Tag GL/SL quick modal (prefix 'tag-') — same
    // two-step shape as app/pages/accountable-officer-page.php's own
    // GL+SL picker (step 1 searches /accounting/coa/search for the parent
    // GL; step 2 searches/creates children of that GL via /accounting/sl/*).
    function bamSlIds(prefix) {
        return {
            search       : prefix + 'sl-search',
            dropdown     : prefix + 'sl-dropdown',
            emptyNote    : prefix + 'sl-empty-note',
            selectedItem : prefix + 'sl-selected-item',
            selectedCode : prefix + 'sl-selected-code',
            selectedValue: prefix + 'sl-selected-code-value',
        };
    }

    function bamSlReset(prefix) {
        const ids = bamSlIds(prefix);
        document.getElementById(ids.search).value = '';
        document.getElementById(ids.selectedValue).value = '';
        document.getElementById(ids.selectedItem).style.display = 'none';
        document.getElementById(ids.dropdown).innerHTML = '';
        document.getElementById(ids.dropdown).style.display = 'none';
        document.getElementById(ids.emptyNote).style.display = 'none';
        const newTitle = document.getElementById(prefix + 'sl-new-title');
        if (newTitle) newTitle.value = '';
    }

    function bamSlEnable(prefix) {
        const search = document.getElementById(bamSlIds(prefix).search);
        search.disabled = false;
        search.placeholder = 'Type account code or description...';
        const section = document.getElementById(prefix + 'sl-section');
        section.style.opacity = '1';
        section.style.pointerEvents = 'auto';
    }

    function bamSlDisable(prefix) {
        const search = document.getElementById(bamSlIds(prefix).search);
        search.disabled = true;
        search.placeholder = 'Select a GL account first…';
        const section = document.getElementById(prefix + 'sl-section');
        section.style.opacity = '.5';
        section.style.pointerEvents = 'none';
    }

    // Load SL codes for a GL account_id; optionally pre-select a known current SL id.
    function bamLoadSlCodesForAccount(prefix, accountId, preselectId) {
        const ids = bamSlIds(prefix);
        fetch('/api/accounting/sl/by-account?account_id=' + encodeURIComponent(accountId) + '&limit=50', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success || !json.data?.length) {
                    document.getElementById(ids.emptyNote).style.display = 'block';
                    document.getElementById(ids.dropdown).style.display = 'none';
                    return;
                }
                document.getElementById(ids.emptyNote).style.display = 'none';
                bamRenderSlList(prefix, json.data);
                document.getElementById(ids.dropdown).style.display = 'none';
                if (preselectId) {
                    const match = json.data.find(a => String(a.account_id) === String(preselectId));
                    if (match) {
                        document.getElementById(ids.search).value = match.account_code;
                        document.getElementById(ids.selectedValue).value = match.account_id;
                        document.getElementById(ids.selectedCode).textContent = match.account_code;
                        document.getElementById(ids.selectedItem).style.display = 'block';
                    }
                }
            })
            .catch(e => toast('Failed to load sub-ledgers: ' + e.message, true));
    }

    function bamRenderSlList(prefix, items) {
        const ids = bamSlIds(prefix);
        const dropdown = document.getElementById(ids.dropdown);
        dropdown.innerHTML = items.map(item =>
            '<div class="bam-gl-sl-item" data-code="' + escapeHtml(item.account_id) + '" data-display="' + escapeHtml(item.account_code) + '" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;">' +
                '<div style="font-weight:600;">' + escapeHtml(item.account_code) + '</div>' +
                (item.account_title ? '<div style="font-size:11px;color:#999;margin-top:2px;">' + escapeHtml(item.account_title) + '</div>' : '') +
            '</div>'
        ).join('');
        dropdown.style.display = 'block';
        dropdown.querySelectorAll('.bam-gl-sl-item').forEach(function (item) {
            item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
            item.addEventListener('mouseout', () => item.style.background = 'transparent');
            item.addEventListener('click', () => bamSelectSlCode(prefix, item));
        });
    }

    function bamSelectSlCode(prefix, item) {
        const ids = bamSlIds(prefix);
        document.getElementById(ids.search).value = item.dataset.display;
        document.getElementById(ids.selectedValue).value = item.dataset.code;
        document.getElementById(ids.selectedCode).textContent = item.dataset.display;
        document.getElementById(ids.selectedItem).style.display = 'block';
        document.getElementById(ids.dropdown).style.display = 'none';
    }

    function bamBindSlSearch(prefix) {
        const ids = bamSlIds(prefix);
        let timer;
        document.getElementById(ids.search).addEventListener('input', function (ev) {
            const query = ev.target.value.trim();
            clearTimeout(timer);
            if (!query) { document.getElementById(ids.dropdown).style.display = 'none'; return; }
            timer = setTimeout(function () {
                const glAccountId = document.getElementById(prefix + 'gl-account-id').value;
                if (!glAccountId) return;
                fetch('/api/accounting/sl/search?account_id=' + encodeURIComponent(glAccountId) + '&q=' + encodeURIComponent(query) + '&limit=50', { headers: authHeaders() })
                    .then(r => r.json())
                    .then(function (json) {
                        if (json.success && json.data?.length) {
                            bamRenderSlList(prefix, json.data);
                        } else {
                            const dropdown = document.getElementById(ids.dropdown);
                            dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No matching sub-ledgers. Try creating one below.</div>';
                            dropdown.style.display = 'block';
                        }
                    })
                    .catch(e => toast('Search failed: ' + e.message, true));
            }, 300);
        });
    }

    function bamBindSlCreate(prefix) {
        const ids = bamSlIds(prefix);
        document.getElementById(prefix + 'sl-create-btn').addEventListener('click', function () {
            const glAccountId = document.getElementById(prefix + 'gl-account-id').value;
            const title = document.getElementById(prefix + 'sl-new-title').value.trim();
            if (!glAccountId) { toast('Select a GL account first.', true); return; }
            if (!title) { toast('Enter a title for the new sub-ledger.', true); return; }
            fetch('/api/accounting/sl/create', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ parent_account_id: glAccountId, account_title: title }) })
                .then(r => r.json())
                .then(function (json) {
                    if (!json.success) { toast(json.message || 'Failed to create sub-ledger.', true); return; }
                    const created = json.data;
                    document.getElementById(ids.search).value = created.account_code;
                    document.getElementById(ids.selectedValue).value = created.account_id;
                    document.getElementById(ids.selectedCode).textContent = created.account_code;
                    document.getElementById(ids.selectedItem).style.display = 'block';
                    document.getElementById(ids.emptyNote).style.display = 'none';
                    document.getElementById(prefix + 'sl-new-title').value = '';
                    toast('Sub-ledger account created.');
                })
                .catch(() => toast('Could not reach the server.', true));
        });
    }

    // Step 1 of the two-step picker: search the parent GL account, wire its
    // selection to reset+enable step 2 (changing the GL invalidates any
    // previously-picked SL, same as the officer page's own picker).
    function bamBindGlSearch(prefix) {
        let timer;
        const search = document.getElementById(prefix + 'gl-search');
        const dropdown = document.getElementById(prefix + 'gl-dropdown');
        search.addEventListener('input', function (ev) {
            const query = ev.target.value.trim();
            clearTimeout(timer);
            if (!query) { dropdown.style.display = 'none'; return; }
            timer = setTimeout(function () {
                fetch('/api/accounting/coa/search?q=' + encodeURIComponent(query) + '&limit=20', { headers: authHeaders() })
                    .then(r => r.json())
                    .then(function (json) {
                        if (json.success && json.data?.length) {
                            dropdown.innerHTML = json.data.map(a =>
                                '<div class="bam-gl-sl-item" data-id="' + escapeHtml(a.account_id) + '" data-code="' + escapeHtml(a.account_code) + '" data-name="' + escapeHtml(a.account_title) + '" style="padding:10px 12px;border-bottom:1px solid #E4ECF2;cursor:pointer;font-size:13px;">' +
                                    '<div style="font-weight:600;">' + escapeHtml(a.account_code) + '</div>' +
                                    '<div style="font-size:11px;color:#999;margin-top:2px;">' + escapeHtml(a.account_title) + '</div>' +
                                '</div>'
                            ).join('');
                            dropdown.style.display = 'block';
                            dropdown.querySelectorAll('.bam-gl-sl-item').forEach(function (item) {
                                item.addEventListener('mouseover', () => item.style.background = '#F4F7FA');
                                item.addEventListener('mouseout', () => item.style.background = 'transparent');
                                item.addEventListener('click', function () {
                                    document.getElementById(prefix + 'gl-account-id').value = this.dataset.id;
                                    search.value = this.dataset.code + ' — ' + this.dataset.name;
                                    dropdown.style.display = 'none';
                                    const selected = document.getElementById(prefix + 'gl-selected');
                                    selected.innerHTML = '<strong>' + escapeHtml(this.dataset.code) + '</strong> — ' + escapeHtml(this.dataset.name);
                                    selected.style.display = 'block';

                                    bamSlReset(prefix);
                                    bamSlEnable(prefix);
                                    bamLoadSlCodesForAccount(prefix, this.dataset.id);
                                });
                            });
                        } else {
                            dropdown.innerHTML = '<div style="padding:10px;color:#999;font-size:12px;">No accounts found.</div>';
                            dropdown.style.display = 'block';
                        }
                    })
                    .catch(e => toast('GL search failed: ' + e.message, true));
            }, 300);
        });
    }

    document.addEventListener('click', function (e) {
        ['field-', 'tag-'].forEach(function (prefix) {
            if (!e.target.closest('#' + prefix + 'gl-search') && !e.target.closest('#' + prefix + 'gl-dropdown')) {
                document.getElementById(prefix + 'gl-dropdown').style.display = 'none';
            }
            if (!e.target.closest('#' + prefix + 'sl-search') && !e.target.closest('#' + prefix + 'sl-dropdown')) {
                document.getElementById(prefix + 'sl-dropdown').style.display = 'none';
            }
        });
    });

    ['field-', 'tag-'].forEach(function (prefix) {
        bamBindGlSearch(prefix);
        bamBindSlSearch(prefix);
        bamBindSlCreate(prefix);
    });

    function loadResponsibilityCenters() {
        fetch(API + '/lookup/responsibility-centers', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const sel = document.getElementById('field-rc-id');
                sel.innerHTML = '<option value="">— Main Office —</option>';
                rows.forEach(function (rc) {
                    responsibilityCentersById[rc.rc_id] = rc;
                    const opt = document.createElement('option');
                    opt.value = rc.rc_id;
                    opt.textContent = rc.rc_code + ' — ' + rc.rc_name;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    // ── Bank name select <-> free-text sync ──
    const bankSelect = document.getElementById('field-bank-name-select');
    const bankInput = document.getElementById('field-bank-name');
    bankSelect.addEventListener('change', function () {
        if (this.value === '__other__') {
            bankInput.style.display = '';
            bankInput.value = '';
            bankInput.focus();
        } else {
            bankInput.style.display = 'none';
            bankInput.value = this.value;
        }
    });

    // ── Table ──
    function buildListParams() {
        const params = new URLSearchParams();
        const status = document.getElementById('bamFilterStatus').value;
        const search = document.getElementById('bamSearch').value.trim();
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        return params;
    }

    function loadList() {
        const tbody = document.getElementById('bamTableBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Loading…</td></tr>';
        fetch(API + '/list?' + buildListParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                document.getElementById('bamTableSubtitle').textContent = 'Bank Accounts (' + rows.length + ')';
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const regionLabel = r.rc_code
                        ? escapeHtml(r.rc_code + ' — ' + r.rc_name)
                        : 'Main Office';
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.account_name) + (r.is_main_depository ? ' <span class="fims-badge fims-badge-blue">Main Depository</span>' : '') + '</td>' +
                        '<td class="fims-col-mono">' + escapeHtml(r.account_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.bank_name || '') + (r.bank_branch ? '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_branch) + '</span>' : '') + '</td>' +
                        '<td>' + regionLabel + '</td>' +
                        '<td class="text-center">' + (r.is_main_depository ? '<i class="fas fa-check" style="color:#000E3E"></i>' : '—') + '</td>' +
                        '<td>' + escapeHtml(r.main_depository_account_name || '—') + '</td>' +
                        '<td class="text-center">' + statusBadge(r.status) + '</td>' +
                        '<td>' + escapeHtml(r.date_opened || '—') + '</td>' +
                        '<td class="text-center">' +
                            '<div class="bam-menu">' +
                                '<button type="button" class="bam-menu-trigger" aria-haspopup="true" aria-expanded="false">' +
                                    '<i class="fas fa-ellipsis-v"></i> Actions</button>' +
                                '<div class="bam-menu-drop">' +
                                    '<button type="button" class="bam-menu-item" data-edit="' + r.bank_account_id + '"><i class="fas fa-pen"></i> Edit</button>' +
                                    '<button type="button" class="bam-menu-item" data-tag-code="' + r.bank_account_id + '" data-tag-code-name="' + escapeHtml(r.account_name) + '" data-tag-code-gl-id="' + escapeHtml(r.coa_account_id || '') + '" data-tag-code-gl-code="' + escapeHtml(r.gl_account_code || '') + '" data-tag-code-gl-title="' + escapeHtml(r.gl_account_title || '') + '" data-tag-code-sl="' + escapeHtml(r.sl_code || '') + '"><i class="fas fa-tag"></i> Tag GL/SL Account</button>' +
                                '</div>' +
                            '</div>' +
                        '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('.bam-menu-trigger').forEach(function (btn) {
                    btn.addEventListener('click', function (e) { e.stopPropagation(); toggleMenu(this); });
                });
                tbody.querySelectorAll('.bam-menu-item').forEach(function (btn) {
                    btn.addEventListener('click', closeMenus);   // runs before each item's own action below
                });
                tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openAccountModal(this.dataset.edit); });
                });
                tbody.querySelectorAll('[data-tag-code]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        openTagCodeModal(this.dataset.tagCode, this.dataset.tagCodeName, {
                            glAccountId: this.dataset.tagCodeGlId,
                            glCode: this.dataset.tagCodeGlCode,
                            glTitle: this.dataset.tagCodeGlTitle,
                            slCode: this.dataset.tagCodeSl,
                        });
                    });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Could not reach the server.</td></tr>'; });
    }

    // ── Row Actions dropdown — fixed position below its button, so the table's overflow never clips it ──
    function closeMenus() {
        document.querySelectorAll('#bamModule .bam-menu-drop.open').forEach(d => d.classList.remove('open'));
        document.querySelectorAll('#bamModule .bam-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
    }
    function toggleMenu(btn) {
        const drop = btn.parentElement.querySelector('.bam-menu-drop');
        const isOpen = drop.classList.contains('open');
        closeMenus();
        if (isOpen) return;
        const r = btn.getBoundingClientRect();
        drop.style.top = (r.bottom + 4) + 'px';
        drop.style.right = (window.innerWidth - r.right) + 'px';
        drop.classList.add('open');
        btn.setAttribute('aria-expanded', 'true');
    }
    document.addEventListener('click', function (e) { if (!e.target.closest('#bamModule .bam-menu')) closeMenus(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeMenus(); });
    document.addEventListener('scroll', closeMenus, true);

    document.getElementById('bamFilterBtn').addEventListener('click', loadList);
    document.getElementById('bamResetBtn').addEventListener('click', function () {
        document.getElementById('bamFilterStatus').value = '';
        document.getElementById('bamSearch').value = '';
        loadList();
    });
    document.getElementById('bamSearch').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') loadList();
    });

    // ── Register/Edit modal — single view, no step wizard; every field is
    // shown at once (Account Info, the GL+SL two-step picker, Bank Info),
    // all validated together by validateAccountModal() below. ──
    // ── Restricted Funds (itemized rows + flat amount) — same shape/markup
    // convention as Cash Position's own Restricted Funds editor, just
    // field-prefixed ids since this lives inside Bank Management's modal. ──
    function renderFieldRestrictedRows(rows) {
        const tbody = document.getElementById('field-rf-body');
        tbody.innerHTML = (rows || []).map(function (item, idx) {
            return '<tr><td><input type="text" class="fims-input fims-input-sm field-rf-label" value="' + escapeHtml(item.label || '') + '"></td>' +
                '<td><input type="number" step="0.01" class="fims-input fims-input-sm field-rf-amount" value="' + (item.amount || 0) + '"></td>' +
                '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-field-rf="' + idx + '"><i class="fas fa-trash"></i></button></td></tr>';
        }).join('');
        tbody.querySelectorAll('[data-remove-field-rf]').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.closest('tr').remove(); });
        });
    }
    document.getElementById('field-rf-add-row-btn')?.addEventListener('click', function () {
        const tbody = document.getElementById('field-rf-body');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" class="fims-input fims-input-sm field-rf-label"></td>' +
            '<td><input type="number" step="0.01" class="fims-input fims-input-sm field-rf-amount" value="0"></td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-field-rf><i class="fas fa-trash"></i></button></td>';
        tr.querySelector('[data-remove-field-rf]').addEventListener('click', function () { tr.remove(); });
        tbody.appendChild(tr);
    });
    function getFieldRestrictedBreakdown() {
        return Array.from(document.querySelectorAll('#field-rf-body tr')).map(function (tr) {
            return { label: tr.querySelector('.field-rf-label').value.trim(), amount: parseFloat(tr.querySelector('.field-rf-amount').value) || 0 };
        }).filter(item => item.amount > 0);
    }

    // Saves whatever was entered in the Restricted Funds section as this
    // account's TODAY fims.cash_position row, via the Cash Position
    // module's own API — same computed-balance + save sequence Cash
    // Position's "Add Restricted Fund" flow uses (getComputedBalance() for
    // Beginning Balance/Receipts/Disbursements, since those still have to
    // be correct even though this form never shows them). Skipped entirely
    // when nothing was actually entered, so registering/editing an account
    // without touching this section never creates a stray cash position
    // row. Runs AFTER the account itself has already saved successfully.
    function saveRestrictedFundsForAccount(bankAccountId) {
        const restrictedAmount = parseFloat(document.getElementById('field-restricted-amount').value) || 0;
        const restrictedBreakdown = getFieldRestrictedBreakdown();
        if (restrictedAmount <= 0 && !restrictedBreakdown.length) {
            return Promise.resolve();
        }
        const positionDate = new Date().toISOString().slice(0, 10);
        return fetch(CASH_POSITION_API + '/updates/computed-balance?bank_account_id=' + bankAccountId + '&position_date=' + positionDate, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = (json && json.data) ? json.data : {};
                return fetch(CASH_POSITION_API + '/updates/save', {
                    method: 'POST',
                    headers: jsonHeaders(),
                    body: JSON.stringify({
                        bank_account_id: bankAccountId,
                        position_date: positionDate,
                        update_type: 'ADJUSTMENT',
                        opening_balance: d.opening_balance || 0,
                        total_receipts: d.total_receipts || 0,
                        total_disbursements: d.total_disbursements || 0,
                        bank_statement_balance: '',
                        restricted_amount: restrictedAmount,
                        restricted_breakdown: restrictedBreakdown,
                    }),
                }).then(r => r.json());
            })
            .then(function (json) {
                if (!json || !json.success) {
                    toast((json && json.message) || 'Account saved, but restricted funds could not be recorded.', true);
                }
            })
            .catch(() => toast('Account saved, but could not reach the server to record restricted funds.', true));
    }

    // field-main-depository-account-id: a separate field from
    // field-is-main-depository — records WHICH other account is assigned
    // as THIS account's Main Depository (main_depository_account_id,
    // self-referencing FK — see BankManagementService). Only accounts
    // actually tagged is_main_depository are valid candidates (assigning
    // an account that isn't itself a Main Depository wouldn't make
    // sense), minus whichever account is currently open in this modal
    // (can't assign itself). Preselects whatever's already saved. Only
    // takes effect on Save (read from doSaveAccount()'s payload below).
    function refreshAssignedMainDepositorySelect(selectedId) {
        const select = document.getElementById('field-main-depository-account-id');
        const ownId = document.getElementById('field-bank-account-id').value;
        fetch(API + '/accounts-for-region', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                let html = '<option value="">— None —</option>';
                html += rows.filter(row => row.is_main_depository && String(row.bank_account_id) !== String(ownId)).map(function (row) {
                    const label = (row.account_name || '') + (row.bank_name ? ' (' + row.bank_name + ')' : '');
                    return '<option value="' + row.bank_account_id + '">' + escapeHtml(label) + '</option>';
                }).join('');
                select.innerHTML = html;
                select.value = selectedId || '';
            })
            .catch(function () { select.innerHTML = '<option value="">— None —</option>'; });
    }

    function clearAccountModal() {
        document.getElementById('field-bank-account-id').value = '';
        document.getElementById('field-account-name').value = '';
        document.getElementById('field-account-number').value = '';
        document.getElementById('field-account-type').value = '';
        document.getElementById('field-rc-id').value = '';
        document.getElementById('field-gl-account-id').value = '';
        document.getElementById('field-gl-search').value = '';
        document.getElementById('field-gl-dropdown').style.display = 'none';
        document.getElementById('field-gl-dropdown').innerHTML = '';
        document.getElementById('field-gl-selected').style.display = 'none';
        bamSlReset('field-');
        bamSlDisable('field-');
        bankSelect.value = '';
        bankInput.value = '';
        bankInput.style.display = 'none';
        document.getElementById('field-bank-branch').value = '';
        document.getElementById('field-status').value = 'ACTIVE';
        document.getElementById('field-is-main-depository').checked = false;
        document.getElementById('field-date-opened').value = '';
        document.getElementById('field-restricted-amount').value = 0;
        renderFieldRestrictedRows([]);
        refreshAssignedMainDepositorySelect('');
    }

    function validateAccountModal() {
        if (!document.getElementById('field-account-name').value.trim()) return 'Account Name is required.';
        if (!document.getElementById('field-account-number').value.trim()) return 'Account Number is required.';
        if (!getBankNameValue()) return 'Bank Name is required.';
        return null;
    }

    function setBankNameValue(name) {
        const match = Array.from(bankSelect.options).find(o => o.value === name);
        if (match) {
            bankSelect.value = name;
            bankInput.style.display = 'none';
            bankInput.value = name;
        } else if (name) {
            bankSelect.value = '__other__';
            bankInput.style.display = '';
            bankInput.value = name;
        }
    }

    function getBankNameValue() {
        return bankSelect.value === '__other__' ? bankInput.value.trim() : bankSelect.value;
    }

    function openAccountModal(id) {
        clearAccountModal();
        if (!id) {
            document.getElementById('bamModalTitle').textContent = 'Register Account';
            openModal('m-bam-account');
            return;
        }
        document.getElementById('bamModalTitle').textContent = 'Edit Account';
        fetch(API + '/' + id, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load account.', true); return; }
                const d = json.data;
                document.getElementById('field-bank-account-id').value = d.bank_account_id;
                document.getElementById('field-account-name').value = d.account_name || '';
                document.getElementById('field-account-number').value = d.account_number || '';
                document.getElementById('field-account-type').value = d.account_type || '';
                document.getElementById('field-rc-id').value = d.rc_id || '';
                if (d.coa_account_id) {
                    document.getElementById('field-gl-account-id').value = d.coa_account_id;
                    document.getElementById('field-gl-search').value = (d.gl_account_code || '') + ' — ' + (d.gl_account_title || '');
                    document.getElementById('field-gl-selected').innerHTML = '<strong>' + escapeHtml(d.gl_account_code || '') + '</strong> — ' + escapeHtml(d.gl_account_title || '');
                    document.getElementById('field-gl-selected').style.display = 'block';
                    bamSlEnable('field-');
                    bamLoadSlCodesForAccount('field-', d.coa_account_id, d.sl_code || null);
                } else {
                    document.getElementById('field-gl-account-id').value = '';
                    document.getElementById('field-gl-search').value = '';
                    document.getElementById('field-gl-selected').style.display = 'none';
                    bamSlDisable('field-');
                }
                setBankNameValue(d.bank_name || '');
                document.getElementById('field-bank-branch').value = d.bank_branch || '';
                document.getElementById('field-status').value = d.status || 'ACTIVE';
                document.getElementById('field-is-main-depository').checked = !!d.is_main_depository;
                refreshAssignedMainDepositorySelect(d.main_depository_account_id || '');
                document.getElementById('field-date-opened').value = d.date_opened || '';
                openModal('m-bam-account');
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    document.getElementById('bamRegisterBtn').addEventListener('click', function () { openAccountModal(null); });

    // Actually submits the account (and, if anything was entered,
    // Restricted Funds). field-is-main-depository tags THIS account on
    // Save; field-main-depository-account-id assigns which other account
    // is its Main Depository, also only on Save.
    function doSaveAccount() {
        const id = document.getElementById('field-bank-account-id').value;
        const payload = {
            account_name: document.getElementById('field-account-name').value.trim(),
            account_number: document.getElementById('field-account-number').value.trim(),
            account_type: document.getElementById('field-account-type').value,
            rc_id: document.getElementById('field-rc-id').value,
            coa_account_id: document.getElementById('field-gl-account-id').value,
            sl_code: document.getElementById('field-sl-selected-code-value').value,
            bank_name: getBankNameValue(),
            bank_branch: document.getElementById('field-bank-branch').value.trim(),
            status: document.getElementById('field-status').value,
            is_main_depository: document.getElementById('field-is-main-depository').checked,
            main_depository_account_id: document.getElementById('field-main-depository-account-id').value,
            date_opened: document.getElementById('field-date-opened').value,
        };
        const url = id ? (API + '/' + id + '/update') : (API + '/store');
        const btn = document.getElementById('bamSaveBtn');
        btn.disabled = true;
        fetch(url, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Save failed.', true); btn.disabled = false; return; }
                const savedId = id || json.data.bank_account_id;
                saveRestrictedFundsForAccount(savedId).finally(function () {
                    toast('Bank account saved.');
                    closeModal('m-bam-account');
                    loadList();
                    loadStats();
                    btn.disabled = false;
                });
            })
            .catch(() => { toast('Could not reach the server.', true); btn.disabled = false; });
    }

    document.getElementById('bamSaveBtn').addEventListener('click', function () {
        const err = validateAccountModal();
        if (err) { toast(err, true); return; }
        doSaveAccount();
    });

    // ── Quick tag GL/SL account modal ──
    let tagCodeAccountId = null;
    function openTagCodeModal(id, name, current) {
        tagCodeAccountId = id;
        document.getElementById('tagCodeAccountName').textContent = name || '';

        document.getElementById('tag-gl-account-id').value = '';
        document.getElementById('tag-gl-search').value = '';
        document.getElementById('tag-gl-dropdown').style.display = 'none';
        document.getElementById('tag-gl-dropdown').innerHTML = '';
        document.getElementById('tag-gl-selected').style.display = 'none';
        bamSlReset('tag-');
        bamSlDisable('tag-');

        if (current && current.glAccountId) {
            document.getElementById('tag-gl-account-id').value = current.glAccountId;
            document.getElementById('tag-gl-search').value = (current.glCode || '') + ' — ' + (current.glTitle || '');
            const selected = document.getElementById('tag-gl-selected');
            selected.innerHTML = '<strong>' + escapeHtml(current.glCode || '') + '</strong> — ' + escapeHtml(current.glTitle || '');
            selected.style.display = 'block';
            bamSlEnable('tag-');
            bamLoadSlCodesForAccount('tag-', current.glAccountId, current.slCode || null);
        }

        openModal('m-bam-tag-code');
    }
    document.getElementById('tagCodeSaveBtn').addEventListener('click', function () {
        const payload = {
            coa_account_id: document.getElementById('tag-gl-account-id').value,
            sl_code: document.getElementById('tag-sl-selected-code-value').value,
        };
        this.disabled = true;
        fetch(API + '/' + tagCodeAccountId + '/tag-account-code', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not tag GL/SL account.', true); return; }
                toast('GL/SL account updated.');
                closeModal('m-bam-tag-code');
                loadList();
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { document.getElementById('tagCodeSaveBtn').disabled = false; });
    });

    // ── Boot ──
    loadStats();
    loadList();
    loadResponsibilityCenters();
})();
</script>
