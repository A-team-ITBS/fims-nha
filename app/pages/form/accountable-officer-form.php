<?php
/**
 * accountable-officer-form.php
 *
 * Add / Edit modal for fims.accountable_officers.
 *
 * Trigger:
 *   ao_openModal('add')         — new accountable officer
 *   ao_openModal('edit', row)   — edit existing accountable officer
 *
 * Self-contained styling (scoped under #ao-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     ACCOUNTABLE OFFICER FORM MODAL   JS namespace: ao_
══════════════════════════════════════════════════════════ -->
<style>
    #ao-overlay {
        display:none;position:fixed;left:0;top:0;right:0;bottom:0;z-index:9100;
        background:rgba(15,32,69,.6);
        padding:24px;box-sizing:border-box;
        overflow-y:auto;
        text-align:center;
    }
    #ao-overlay.open { display:block; }
    #ao-overlay::after { content:'';display:inline-block;height:100%;vertical-align:middle; }

    #ao-card {
        display:inline-block;text-align:left;vertical-align:middle;
        width:100%;max-width:560px;box-sizing:border-box;
        background:var(--afims-surface, #FFFFFF);border-radius:16px;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
    }

    #ao-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);
        border-radius:16px 16px 0 0; }
    #ao-header-info { display:flex;align-items:center;gap:10px; }
    #ao-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .ao-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .ao-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #ao-modal-close { border:none;background:rgba(255,255,255,.12);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1; }
    #ao-modal-close:hover { background:rgba(255,255,255,.22);color:var(--afims-pure-white, #FFFFFF); }

    #ao-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #ao-body { max-height:70vh;overflow-y:auto;padding:22px;box-sizing:border-box; }
    .ao-section { font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);text-transform:uppercase;
        letter-spacing:.06em;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .ao-section:not(:first-child) { margin-top:22px; }
    .ao-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px; }
    .ao-group { display:flex;flex-direction:column;gap:5px;min-width:0; }
    .ao-group.full { margin-bottom:14px; }
    .ao-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .ao-label .req { color:var(--afims-danger, #DC3545); }
    .ao-input { width:100%;min-width:0;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);box-sizing:border-box; }
    .ao-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .ao-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    #ao-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);
        border-radius:0 0 16px 16px; }
    #ao-footer .ao-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    #ao-footer .ao-btn:disabled { opacity:.5;cursor:not-allowed; }
    #ao-footer .ao-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #ao-footer .ao-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #ao-footer .ao-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #ao-footer .ao-btn-navy:hover:not(:disabled) { opacity:.92; }

    @media (max-width:560px) { .ao-row { grid-template-columns:1fr; } }
</style>

<div id="ao-overlay">
    <div id="ao-card">

        <div id="ao-header">
            <div id="ao-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <div>
                    <p class="ao-hdr-tag">Accountable Officer Master Data</p>
                    <p class="ao-hdr-title" id="ao-modal-title">Add Accountable Officer</p>
                </div>
            </div>
            <button type="button" id="ao-modal-close">&times;</button>
        </div>

        <div id="ao-err"></div>

        <form id="ao-form" novalidate>
            <div id="ao-body">
                <input type="hidden" id="ao-mode" value="add">
                <input type="hidden" id="ao-id" value="">

                <p class="ao-section">Identity</p>
                <div class="ao-row">
                    <div class="ao-group">
                        <label class="ao-label">First Name <span class="req">*</span></label>
                        <input type="text" class="ao-input" id="ao-first-name" maxlength="50" placeholder="First name">
                    </div>
                    <div class="ao-group">
                        <label class="ao-label">Last Name <span class="req">*</span></label>
                        <input type="text" class="ao-input" id="ao-last-name" maxlength="50" placeholder="Last name">
                    </div>
                </div>
                <div class="ao-row">
                    <div class="ao-group">
                        <label class="ao-label">Middle Name</label>
                        <input type="text" class="ao-input" id="ao-middle-name" maxlength="50" placeholder="Middle name">
                    </div>
                    <div class="ao-group">
                        <label class="ao-label">Suffix</label>
                        <select class="ao-input" id="ao-suffix">
                            <option value="">(None)</option>
                            <option value="Jr.">Jr.</option>
                            <option value="Sr.">Sr.</option>
                            <option value="II">II</option>
                            <option value="III">III</option>
                            <option value="IV">IV</option>
                            <option value="V">V</option>
                        </select>
                    </div>
                </div>
                <div class="ao-row">
                    <div class="ao-group">
                        <label class="ao-label">Status</label>
                        <select class="ao-input" id="ao-status">
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                        </select>
                    </div>
                    <div class="ao-group">
                        <label class="ao-label">Region</label>
                        <select class="ao-input" id="ao-region">
                            <option value="">— Select region —</option>
                        </select>
                    </div>
                </div>

                <p class="ao-section">Chart of Accounts Assignment</p>
                <div class="ao-group full" style="position:relative;">
                    <label class="ao-label">Account</label>
                    <input type="hidden" id="ao-coa-account-id" value="">
                    <input type="text" class="ao-input" id="ao-coa-account-search" maxlength="500" placeholder="Search: account code or name (e.g., 01-01-010-01, Accounts Payable)">
                    <p class="ao-hint">Start typing to search Chart of Accounts</p>
                    <div id="ao-coa-dropdown" style="display:none;position:absolute;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:200px;overflow-y:auto;width:100%;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                    <div id="ao-coa-selected" style="margin-top:8px;padding:8px;background:#F4F7FA;border-radius:6px;display:none;"></div>
                </div>
            </div>
            <div id="ao-footer">
                <button type="button" class="ao-btn ao-btn-ghost" id="ao-cancel">Cancel</button>
                <button type="submit" class="ao-btn ao-btn-navy" id="ao-save">Save Accountable Officer</button>
            </div>
        </form>
    </div>
</div>
