<?php
/**
 * payroll-form.php
 *
 * Add modal for a payroll item (an earning, deduction, refund or employer share of the monthly payroll).
 * The Account Code is picked from the live Chart of Accounts (GET /chart_of_accounts/all, loaded by
 * payroll-page.php), then optionally one of that account's subsidiary ledgers (SL Code) and a detail
 * within it (SL Detail) — the 5th and 6th parts of an account written as 2-99-99-990-02-01.
 * There is no Debit/Credit field: the side follows from the Category
 * (earning, refund, employer share = Debit; deduction = Credit).
 *
 * PREVIEW ONLY — no payroll database yet; submitting validates the fields and nothing is stored.
 *
 * Trigger:
 *   pyr_openModal()   — open the form
 *
 * Self-contained styling (scoped under #pyr-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     PAYROLL ITEM FORM MODAL   JS namespace: pyr_
══════════════════════════════════════════════════════════ -->
<style>
    #pyr-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #pyr-overlay.open { display:flex; }
    #pyr-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:720px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:pyrIn .2s ease; }
    @keyframes pyrIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #pyr-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #pyr-header-info { display:flex;align-items:center;gap:10px; }
    #pyr-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .pyr-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .pyr-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #pyr-modal-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #pyr-modal-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #pyr-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545);flex-shrink:0; }

    /* The form sits between the flex card and the body: make it part of the flex chain so only the body scrolls. */
    #pyr-form { display:flex;flex-direction:column;flex:1 1 auto;min-height:0;margin:0; }
    #pyr-body { flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:22px; }
    .pyr-section { font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);text-transform:uppercase;
        letter-spacing:.06em;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .pyr-section:not(:first-child) { margin-top:22px; }
    .pyr-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px; }
    .pyr-group { display:flex;flex-direction:column;gap:5px; }
    .pyr-group.full { margin-bottom:14px; }
    .pyr-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .pyr-label .req { color:var(--afims-danger, #DC3545); }
    .pyr-input { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .pyr-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .pyr-input:disabled,.pyr-input[readonly] { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-muted, #62657E); }
    .pyr-input[readonly] { cursor:default; }
    .pyr-mono { font-family:'IBM Plex Mono',monospace; }
    textarea.pyr-input { resize:vertical;min-height:64px; }
    .pyr-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }
    .pyr-hint.ok { color:var(--afims-success, #11A752); }
    .pyr-hint.bad { color:var(--afims-danger, #DC3545); }

    #pyr-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-shrink:0;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2); }
    #pyr-footer .pyr-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #pyr-footer .pyr-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #pyr-footer .pyr-btn-ghost:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    #pyr-footer .pyr-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #pyr-footer .pyr-btn-navy:hover { opacity:.92; }

    @media (max-width:640px) { .pyr-row { grid-template-columns:1fr; } }
</style>

<div id="pyr-overlay">
    <div id="pyr-card">

        <div id="pyr-header">
            <div id="pyr-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <div>
                    <p class="pyr-hdr-tag">Payroll Item Setup</p>
                    <p class="pyr-hdr-title">Add Payroll Item</p>
                </div>
            </div>
            <button type="button" id="pyr-modal-close">&times;</button>
        </div>

        <div id="pyr-err"></div>

        <form id="pyr-form" novalidate>
            <div id="pyr-body">

                <p class="pyr-section">Identity</p>
                <div class="pyr-row">
                    <div class="pyr-group">
                        <label class="pyr-label" for="pyr-item-code">Item Code <span class="req">*</span></label>
                        <input type="text" class="pyr-input pyr-mono" id="pyr-item-code" maxlength="60" placeholder="e.g. BASIC_SALARY">
                        <p class="pyr-hint">Unique key. Same as the Excel column header, without the quotes.</p>
                    </div>
                    <div class="pyr-group">
                        <label class="pyr-label" for="pyr-category">Category <span class="req">*</span></label>
                        <select class="pyr-input" id="pyr-category">
                            <option value="">— Select category —</option>
                            <option value="EARNING">Earning</option>
                            <option value="DEDUCTION">Deduction</option>
                            <option value="REFUND">Refund</option>
                            <option value="EMPLOYER_SHARE">Employer Share</option>
                        </select>
                        <p class="pyr-hint" id="pyr-side-hint">Decides how the item posts to its account.</p>
                    </div>
                </div>
                <div class="pyr-group full">
                    <label class="pyr-label" for="pyr-item-name">Item Name <span class="req">*</span></label>
                    <input type="text" class="pyr-input" id="pyr-item-name" maxlength="150" placeholder="e.g. Basic Salary">
                    <p class="pyr-hint">Readable label shown to users.</p>
                </div>

                <p class="pyr-section">Chart of Accounts</p>
                <div class="pyr-group full">
                    <label class="pyr-label" for="pyr-account-code">Account Code</label>
                    <input type="text" class="pyr-input pyr-mono" id="pyr-account-code" maxlength="30" list="pyr-account-list" autocomplete="off" placeholder="Type a code or account title">
                    <datalist id="pyr-account-list"></datalist>
                    <p class="pyr-hint" id="pyr-account-hint">Loading chart of accounts…</p>
                </div>
                <div class="pyr-row">
                    <div class="pyr-group">
                        <label class="pyr-label" for="pyr-sl-code">SL Code</label>
                        <select class="pyr-input" id="pyr-sl-code" disabled>
                            <option value="">— None —</option>
                        </select>
                        <p class="pyr-hint" id="pyr-sl-hint">Pick an account first.</p>
                    </div>
                    <div class="pyr-group">
                        <label class="pyr-label" for="pyr-sl-detail">SL Detail</label>
                        <select class="pyr-input" id="pyr-sl-detail" disabled>
                            <option value="">— None —</option>
                        </select>
                        <p class="pyr-hint" id="pyr-sl-detail-hint">Optional. Choose an SL Code first.</p>
                    </div>
                </div>

                <p class="pyr-section">Status</p>
                <div class="pyr-row" style="margin-bottom:0;">
                    <div class="pyr-group">
                        <label class="pyr-label" for="pyr-status">Status</label>
                        <select class="pyr-input" id="pyr-status">
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                        </select>
                        <p class="pyr-hint">Inactive items are hidden from new payroll runs.</p>
                    </div>
                    <div class="pyr-group">
                        <label class="pyr-label" for="pyr-remarks">Remarks</label>
                        <textarea class="pyr-input" id="pyr-remarks" rows="2" placeholder="Notes for accounting (optional)"></textarea>
                    </div>
                </div>
            </div>
            <div id="pyr-footer">
                <button type="button" class="pyr-btn pyr-btn-ghost" id="pyr-cancel">Cancel</button>
                <button type="submit" class="pyr-btn pyr-btn-navy" id="pyr-save">Save Item</button>
            </div>
        </form>
    </div>
</div>
