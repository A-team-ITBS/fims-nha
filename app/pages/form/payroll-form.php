<?php
/**
 * payroll-form.php
 *
 * Add / Edit modal for a payroll item (an earning, deduction, refund or employer share of the monthly payroll).
 * Opened by payroll-setup-page.php: pyr_openModal('add') or pyr_openModal('edit', row) — the item code is locked on edit.
 * Everything goes through the Payroll Setup API only (POST /payroll-setup/add, PUT /payroll-setup/update/{id}).
 *
 * Section order: Identity → Status → Chart of Accounts.
 *   - Item Name fills in from the Item Code (BASIC_SALARY → BASIC SALARY) until the user types their own.
 *   - Account is a searchable dropdown of the postable accounts (GET /payroll-setup/accounts, loaded by the page).
 *   - After an account is picked, its NEXT part (the 5th, or the 6th for a 5-part account) is editable.
 *     If that account does not exist yet, a Title box appears; the page sends it as `new_account` with the item and the
 *     server adds the sub-ledger to the Chart of Accounts (same as "Add Sub-Ledger" on chart-of-accounts-page.php),
 *     removing it again if the item then fails to save.
 *   - There is no Debit/Credit field: the side follows from the Category
 *     (earning, refund, employer share = Debit; deduction = Credit).
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
    .pyr-mono { font-family:'IBM Plex Mono',monospace; }
    textarea.pyr-input { resize:vertical;min-height:64px; }
    .pyr-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }
    .pyr-hint.ok { color:var(--afims-success, #11A752); }
    .pyr-hint.bad { color:var(--afims-danger, #DC3545); }

    /* ── Segmented account-code chip (same look as the Chart of Accounts page) ── */
    .pyr-chip { display:inline-flex;flex-shrink:0;overflow:hidden;border-radius:6px;border:1px solid var(--afims-border, #E4ECF2); }
    .pyr-chip-seg { padding:2px 7px;font:700 11.5px 'IBM Plex Mono',monospace;color:#2F5FD6;background:#EAF1FE;
        border-left:1px solid rgba(15,32,69,.08); }
    .pyr-chip-seg.is-root { color:#1A1B21;background:#EEF1F7;border-left:0; }

    /* ── Account combobox ── */
    .pyr-combo { position:relative; }
    .pyr-combo .pyr-input { padding-right:34px; }
    .pyr-combo-clear { position:absolute;right:6px;top:50%;transform:translateY(-50%);width:24px;height:24px;border:none;border-radius:6px;
        background:transparent;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;cursor:pointer; }
    .pyr-combo-clear:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text, #1A1B21); }
    .pyr-combo-clear[hidden] { display:none; }
    .pyr-combo-panel { position:fixed;z-index:9300;display:flex;flex-direction:column;overflow:hidden;
        background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border-strong, #C9D3DD);border-radius:10px;
        box-shadow:0 12px 32px rgba(15,32,69,.22); }
    .pyr-combo-panel[hidden] { display:none; }
    .pyr-combo-list { flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:4px; }
    .pyr-opt { display:flex;align-items:center;gap:10px;padding:7px 10px;border-radius:7px;cursor:pointer;
        font-size:13px;color:var(--afims-text, #1A1B21); }
    .pyr-opt:hover,.pyr-opt.is-active { background:var(--afims-surface-subtle, #F4F7FA); }
    .pyr-opt.is-active { box-shadow:inset 3px 0 0 var(--afims-heading-fill, #000E3E); }
    .pyr-opt-title { min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .pyr-combo-foot { padding:7px 12px;border-top:1px solid var(--afims-border, #E4ECF2);font-size:11px;
        color:var(--afims-text-muted, #62657E);background:var(--afims-surface-subtle, #F4F7FA); }
    .pyr-combo-empty { padding:14px 12px;font-size:12px;color:var(--afims-text-muted, #62657E); }

    /* ── Next part (5th / 6th) + new sub-ledger ── */
    .pyr-sub { margin-top:14px; }
    .pyr-sub[hidden], .pyr-new[hidden] { display:none; }
    .pyr-sub-row { display:flex;align-items:center;gap:8px;margin:5px 0; }
    .pyr-sub-row .pyr-input { width:120px; }
    .pyr-sub-dash { font:700 14px 'IBM Plex Mono',monospace;color:var(--afims-text-muted, #62657E); }
    .pyr-new { margin-top:14px;padding:12px 14px;display:flex;flex-direction:column;gap:5px;
        border:1px solid var(--afims-border, #E4ECF2);border-left:4px solid var(--afims-info, #2563EB);border-radius:8px;
        background:var(--afims-surface-subtle, #F4F7FA); }
    .pyr-new-title { font-size:12px;font-weight:700;color:var(--afims-text, #1A1B21); }

    #pyr-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-shrink:0;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2); }
    #pyr-footer .pyr-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #pyr-footer .pyr-btn:disabled { opacity:.5;cursor:not-allowed; }
    #pyr-footer .pyr-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #pyr-footer .pyr-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #pyr-footer .pyr-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #pyr-footer .pyr-btn-navy:hover:not(:disabled) { opacity:.92; }

    /* ── Dark mode (the chip colours and fixed panel are hard-coded / fixed, like on the Chart of Accounts page) ── */
    html.afims-dark[data-bs-theme="dark"] .pyr-chip { border-color:#5B7FCF; }
    html.afims-dark[data-bs-theme="dark"] .pyr-chip-seg { color:#FFFFFF;background:#3861BC;border-left-color:rgba(255,255,255,.25); }
    html.afims-dark[data-bs-theme="dark"] .pyr-chip-seg.is-root { color:#FFFFFF;background:#22305E; }

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
                    <p class="pyr-hdr-title" id="pyr-modal-title">Add Payroll Item</p>
                </div>
            </div>
            <button type="button" id="pyr-modal-close">&times;</button>
        </div>

        <div id="pyr-err"></div>

        <form id="pyr-form" novalidate>
            <div id="pyr-body">
                <input type="hidden" id="pyr-mode" value="add">
                <input type="hidden" id="pyr-id" value="">

                <p class="pyr-section">Identity</p>
                <div class="pyr-row">
                    <div class="pyr-group">
                        <label class="pyr-label" for="pyr-item-code">Item Code <span class="req">*</span></label>
                        <input type="text" class="pyr-input pyr-mono" id="pyr-item-code" maxlength="60" autocomplete="off" placeholder="e.g. BASIC_SALARY">
                        <p class="pyr-hint" id="pyr-code-hint">Unique key. Same as the Excel column header, without the quotes.</p>
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
                    <input type="text" class="pyr-input" id="pyr-item-name" maxlength="150" autocomplete="off" placeholder="e.g. BASIC SALARY">
                    <p class="pyr-hint">Fills in from the Item Code. You can still type your own.</p>
                </div>

                <p class="pyr-section">Status</p>
                <div class="pyr-row">
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

                <p class="pyr-section">Chart of Accounts</p>
                <div class="pyr-group full" style="margin-bottom:0;">
                    <label class="pyr-label" for="pyr-account-search">Account</label>
                    <div class="pyr-combo" id="pyr-combo">
                        <input type="text" class="pyr-input" id="pyr-account-search" autocomplete="off" spellcheck="false"
                               role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="pyr-account-list"
                               placeholder="Search account code or title…">
                        <button type="button" class="pyr-combo-clear" id="pyr-account-clear" aria-label="Clear account" hidden>&times;</button>
                        <div class="pyr-combo-panel" id="pyr-account-panel" hidden>
                            <div class="pyr-combo-list" id="pyr-account-list" role="listbox"></div>
                            <div class="pyr-combo-foot" id="pyr-account-foot"></div>
                        </div>
                    </div>
                    <p class="pyr-hint" id="pyr-account-hint">Loading chart of accounts…</p>

                    <div class="pyr-sub" id="pyr-sub" hidden>
                        <label class="pyr-label" for="pyr-sub-part" id="pyr-sub-label">5th part</label>
                        <div class="pyr-sub-row">
                            <span id="pyr-sub-prefix"></span>
                            <span class="pyr-sub-dash">-</span>
                            <input type="text" class="pyr-input pyr-mono" id="pyr-sub-part" maxlength="3" inputmode="numeric" autocomplete="off" placeholder="e.g. 37">
                        </div>
                        <p class="pyr-hint" id="pyr-sub-hint"></p>
                    </div>

                    <div class="pyr-new" id="pyr-new" hidden>
                        <span class="pyr-new-title" id="pyr-new-heading">New sub-ledger</span>
                        <label class="pyr-label" for="pyr-new-title">Account Title <span class="req">*</span></label>
                        <input type="text" class="pyr-input" id="pyr-new-title" maxlength="255" autocomplete="off" placeholder="Title of the new sub-ledger account">
                        <p class="pyr-hint">This account does not exist yet. It is added to the Chart of Accounts under the account above when you save.</p>
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
