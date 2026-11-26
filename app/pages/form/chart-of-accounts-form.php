<?php
/**
 * chart-of-accounts-form.php
 *
 * Add / Edit modal for fims.chart_of_accounts.
 *
 * Trigger:
 *   coa_openModal('add')         — new account
 *   coa_openModal('edit', row)   — edit existing account (account_code locked)
 *
 * Self-contained styling (scoped under #coa-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     CHART OF ACCOUNTS FORM MODAL   JS namespace: coa_
══════════════════════════════════════════════════════════ -->
<style>
    #coa-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #coa-overlay.open { display:flex; }
    #coa-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:coaIn .2s ease; }
    @keyframes coaIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #coa-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #coa-header-info { display:flex;align-items:center;gap:10px; }
    #coa-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .coa-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .coa-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    /* (was #coa-close, which matched nothing — the button's id is coa-modal-close — so it showed as a bare grey browser button) */
    #coa-modal-close { flex-shrink:0;width:30px;height:30px;padding:0;border-radius:8px;cursor:pointer;
        display:flex;align-items:center;justify-content:center;font-size:20px;line-height:1;
        color:var(--afims-pure-white, #FFFFFF);background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);
        transition:background .15s; }
    #coa-modal-close:hover { background:rgba(255,255,255,.22); }

    #coa-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #coa-body { flex:1;overflow-y:auto;padding:22px; }
    .coa-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    .coa-group { display:flex;flex-direction:column;gap:5px; }
    .coa-group.full { margin-bottom:16px; }
    .coa-group:last-child { margin-bottom:0; }
    .coa-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .coa-label .req { color:var(--afims-danger, #DC3545); }
    .coa-input, .coa-select { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .coa-input:focus, .coa-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .coa-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .coa-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    .coa-check-row { display:flex;align-items:center;gap:8px; }
    .coa-check-row input[type="checkbox"] { width:16px;height:16px; }
    .coa-check-row label { font-size:13px;color:var(--afims-text, #1A1B21);margin:0; }

    #coa-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #coa-footer .coa-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #coa-footer .coa-btn:disabled { opacity:.5;cursor:not-allowed; }
    #coa-footer .coa-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #coa-footer .coa-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #coa-footer .coa-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #coa-footer .coa-btn-navy:hover:not(:disabled) { opacity:.92; }

    @media (max-width: 560px) { .coa-row { grid-template-columns:1fr; } }

    /* ── Dark mode — the selects rendered with the browser's black native style; match the text inputs and the theme ── */
    html.afims-dark[data-bs-theme="dark"] #coa-card { color-scheme:dark; }
    html.afims-dark[data-bs-theme="dark"] #coa-card :is(.coa-input, .coa-select) {
        background-color:var(--afims-surface, #1A1F38) !important;border-color:var(--afims-border-strong, #4A5270) !important;
        color:var(--afims-text, #E4ECF2) !important; }
    html.afims-dark[data-bs-theme="dark"] #coa-card .coa-select option { background-color:var(--afims-surface, #1A1F38);color:var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] #coa-card .coa-input::placeholder { color:#9AA3B5;opacity:1; }
    html.afims-dark[data-bs-theme="dark"] #coa-card :is(.coa-input, .coa-select):focus {
        border-color:var(--afims-primary, #3861BC) !important;box-shadow:0 0 0 3px color-mix(in srgb, var(--afims-primary, #3861BC) 25%, transparent); }
    html.afims-dark[data-bs-theme="dark"] #coa-card :is(.coa-input, .coa-select):disabled {
        background-color:var(--afims-surface-subtle, #22294A) !important;color:var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #coa-card .coa-hint { color:#9AA3B5; }
    html.afims-dark[data-bs-theme="dark"] #coa-footer .coa-btn-ghost { color:var(--afims-text, #E4ECF2);background:var(--afims-surface, #1A1F38);
        border-color:var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #coa-footer .coa-btn-navy { background:#3861BC;border-color:#3861BC; }
    html.afims-dark[data-bs-theme="dark"] #coa-footer .coa-btn-navy:hover:not(:disabled) { background:#2E59B8;opacity:1; }
</style>

<div id="coa-overlay">
    <div class="u-modal-w-70" id="coa-card">

        <div id="coa-header">
            <div id="coa-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
                <div>
                    <p class="coa-hdr-tag">Chart of Accounts</p>
                    <p class="coa-hdr-title" id="coa-modal-title">Add Account</p>
                </div>
            </div>
            <button type="button" id="coa-modal-close">&times;</button>
        </div>

        <div id="coa-err"></div>

        <form id="coa-form">
            <div id="coa-body">
                <input type="hidden" id="coa-mode" value="add">
                <input type="hidden" id="coa-account-id" value="">

                <div class="coa-row">
                    <div class="coa-group">
                        <label class="coa-label">Account Code <span class="req">*</span></label>
                        <input type="text" class="coa-input" id="coa-account-code" required maxlength="30" placeholder="e.g. 1-01-02-030">
                        <p class="coa-hint" id="coa-code-hint">Cannot be changed after creation.</p>
                    </div>
                    <div class="coa-group">
                        <label class="coa-label">Account Type</label>
                        <select class="coa-select" id="coa-account-type">
                            <option value="">— Select type —</option>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                            <option value="Equity">Equity</option>
                            <option value="Income">Income</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>
                </div>

                <div class="coa-group full">
                    <label class="coa-label">Account Title <span class="req">*</span></label>
                    <input type="text" class="coa-input" id="coa-account-title" required maxlength="255" placeholder="e.g. Cash in Bank-Local Currency, Savings Account">
                </div>

                <div class="coa-row">
                    <div class="coa-group">
                        <label class="coa-label">Region (RCA Code)</label>
                        <select class="coa-select" id="coa-region-code">
                            <option value="">— None (national/HO) —</option>
                        </select>
                    </div>
                    <div class="coa-group">
                        <label class="coa-label">Project</label>
                        <select class="coa-select" id="coa-project-code">
                            <option value="">— None —</option>
                        </select>
                    </div>
                </div>

                <div class="coa-row">
                    <div class="coa-group">
                        <label class="coa-label">Parent Account</label>
                        <select class="coa-select" id="coa-parent-account-id">
                            <option value="">— No parent (top-level) —</option>
                        </select>
                    </div>
                    <div class="coa-group">
                        <label class="coa-label">Account Purpose</label>
                        <select class="coa-select" id="coa-account-purpose">
                            <option value="">— None —</option>
                            <option value="Vendor Payable">Vendor Payable</option>
                            <option value="Employee Compensation">Employee Compensation</option>
                            <option value="Bank Account">Bank Account</option>
                            <option value="Operating Expense">Operating Expense</option>
                            <option value="Revenue">Revenue</option>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                        </select>
                    </div>
                </div>

                <div class="coa-row">
                    <div class="coa-group full">
                        <label class="coa-label">Source Document</label>
                        <input type="text" class="coa-input" id="coa-source-document" maxlength="255" placeholder="e.g. UACS Manual 2020">
                    </div>
                </div>

                <div class="coa-row">
                    <div class="coa-group full">
                        <label class="coa-label">Books (Fund Sources)</label>
                        <div id="coa-books-container" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; padding: 12px; background: var(--afims-surface-subtle, #F4F7FA); border-radius: 8px; border: 1px solid var(--afims-border, #E4ECF2);">
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="coa-book-mo" value="MO" style="width: 16px; height: 16px; cursor: pointer;">
                                <label for="coa-book-mo" style="cursor: pointer; margin: 0; font-size: 13px; font-weight: 500;">MO</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="coa-book-cisfa" value="CISFA" style="width: 16px; height: 16px; cursor: pointer;">
                                <label for="coa-book-cisfa" style="cursor: pointer; margin: 0; font-size: 13px; font-weight: 500;">CISFA</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="coa-book-ngc" value="NGC" style="width: 16px; height: 16px; cursor: pointer;">
                                <label for="coa-book-ngc" style="cursor: pointer; margin: 0; font-size: 13px; font-weight: 500;">NGC</label>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="coa-book-mplc" value="MPLC" style="width: 16px; height: 16px; cursor: pointer;">
                                <label for="coa-book-mplc" style="cursor: pointer; margin: 0; font-size: 13px; font-weight: 500;">MPLC</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="coa-group full">
                    <div class="coa-check-row">
                        <input type="checkbox" id="coa-posting-allowed">
                        <label for="coa-posting-allowed">Posting Allowed — transactions can post directly to this account</label>
                    </div>
                </div>
            </div>
            <div id="coa-footer">
                <button type="button" class="coa-btn coa-btn-ghost" id="coa-cancel">Cancel</button>
                <button type="submit" class="coa-btn coa-btn-navy" id="coa-save">Save Account</button>
            </div>
        </form>
    </div>
</div>
