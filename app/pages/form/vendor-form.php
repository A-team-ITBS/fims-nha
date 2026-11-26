<?php
/**
 * vendor-form.php
 *
 * Add / Edit modal for fims.vendors.
 *
 * Trigger:
 *   vnd_openModal('add')         — new vendor
 *   vnd_openModal('edit', row)   — edit existing vendor (vendor_code locked)
 *
 * Self-contained styling (scoped under #vnd-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     VENDOR FORM MODAL   JS namespace: vnd_
══════════════════════════════════════════════════════════ -->
<style>
    #vnd-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #vnd-overlay.open { display:flex; }
    #vnd-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:vndIn .2s ease; }
    @keyframes vndIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #vnd-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #vnd-header-info { display:flex;align-items:center;gap:10px; }
    #vnd-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .vnd-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .vnd-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #vnd-modal-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #vnd-modal-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #vnd-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    /* The form sits between the flex card and the body: make it part of the flex chain so only the body scrolls. */
    #vnd-form { display:flex;flex-direction:column;flex:1 1 auto;min-height:0;margin:0; }
    #vnd-err { flex-shrink:0; }
    #vnd-body { flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:22px; }
    #vnd-footer { flex-shrink:0; }
    .vnd-section { font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);text-transform:uppercase;
        letter-spacing:.06em;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .vnd-section:not(:first-child) { margin-top:22px; }
    .vnd-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px; }
    .vnd-group { display:flex;flex-direction:column;gap:5px; }
    .vnd-group.full { margin-bottom:14px; }
    .vnd-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .vnd-label .req { color:var(--afims-danger, #DC3545); }
    .vnd-input { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .vnd-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .vnd-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    textarea.vnd-input { resize:vertical;min-height:64px; }
    .vnd-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }
    .vnd-check { display:flex;align-items:center;gap:8px;font-size:13px;color:var(--afims-text, #1A1B21);
        height:38px;cursor:pointer; }
    .vnd-check input { width:16px;height:16px;accent-color:var(--afims-heading-fill, #000E3E); }

    #vnd-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #vnd-footer .vnd-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #vnd-footer .vnd-btn:disabled { opacity:.5;cursor:not-allowed; }
    #vnd-footer .vnd-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #vnd-footer .vnd-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #vnd-footer .vnd-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #vnd-footer .vnd-btn-navy:hover:not(:disabled) { opacity:.92; }

    /* "+ Create Sub-Ledger Account" sits in the body, not the footer, so the #vnd-footer button rules above
       never reached it and it showed as a bare grey browser button. */
    #vnd-body .vnd-btn { height:34px;padding:0 16px;border-radius:8px;border:1px solid #3861BC;
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;
        display:inline-flex;align-items:center;gap:6px;background:#3861BC;color:#FFFFFF;transition:background .15s,border-color .15s; }
    #vnd-body .vnd-btn:hover:not(:disabled) { background:#2E59B8;border-color:#2E59B8; }
    #vnd-body .vnd-btn:disabled { opacity:.5;cursor:not-allowed; }

    /* ── Dark mode ── */
    html.afims-dark[data-bs-theme="dark"] #vnd-card { color-scheme:dark;border:1px solid var(--afims-border, #2E3656); }
    html.afims-dark[data-bs-theme="dark"] #vnd-modal-close { background:rgba(255,255,255,.10);color:#C3CCE0; }
    html.afims-dark[data-bs-theme="dark"] #vnd-modal-close:hover { background:rgba(255,255,255,.22);color:#FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #vnd-card .vnd-input {
        background-color:var(--afims-surface, #1A1F38) !important;border-color:var(--afims-border-strong, #4A5270) !important;
        color:var(--afims-text, #E4ECF2) !important; }
    html.afims-dark[data-bs-theme="dark"] #vnd-card .vnd-input::placeholder { color:#9AA3B5;opacity:1; }
    html.afims-dark[data-bs-theme="dark"] #vnd-card .vnd-input:focus {
        border-color:#3861BC !important;box-shadow:0 0 0 3px rgba(56,97,188,.25); }
    html.afims-dark[data-bs-theme="dark"] #vnd-card .vnd-input:disabled {
        background-color:var(--afims-surface-subtle, #22294A) !important;color:var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #vnd-card .vnd-hint { color:#9AA3B5; }
    html.afims-dark[data-bs-theme="dark"] #vnd-card .vnd-check input { accent-color:#3861BC; }
    /* GL / Sub-Ledger search lists and the "Selected" boxes have their colours inline (style="…" and set from JS). */
    html.afims-dark[data-bs-theme="dark"] :is(#vnd-form-gl-dropdown, #vnd-form-sl-dropdown) {
        background:#1A1F38 !important;border-color:#4A5270 !important;box-shadow:0 8px 20px rgba(0,0,0,.45) !important; }
    html.afims-dark[data-bs-theme="dark"] :is(#vnd-form-gl-dropdown, #vnd-form-sl-dropdown) > div {
        color:#E4ECF2;border-bottom-color:#2E3656 !important;background:transparent !important; }
    html.afims-dark[data-bs-theme="dark"] :is(#vnd-form-gl-dropdown, #vnd-form-sl-dropdown) > div:hover { background:#22294A !important; }
    html.afims-dark[data-bs-theme="dark"] :is(#vnd-form-gl-dropdown, #vnd-form-sl-dropdown) > div div { color:#9AA3B5 !important; }
    html.afims-dark[data-bs-theme="dark"] :is(#vnd-form-gl-selected, #vnd-form-sl-selected-item) {
        background:#0F2456 !important;color:#E4ECF2;border:1px solid #2E3656; }
    html.afims-dark[data-bs-theme="dark"] #vnd-footer .vnd-btn-ghost { color:#E4ECF2;background:var(--afims-surface, #1A1F38);
        border-color:var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #vnd-footer .vnd-btn-ghost:hover:not(:disabled) { background:#22294A; }
    html.afims-dark[data-bs-theme="dark"] #vnd-footer .vnd-btn-navy { background:#3861BC;border-color:#3861BC; }
    html.afims-dark[data-bs-theme="dark"] #vnd-footer .vnd-btn-navy:hover:not(:disabled) { background:#2E59B8;opacity:1; }

    @media (max-width:640px) { .vnd-row { grid-template-columns:1fr; } }
</style>

<div id="vnd-overlay">
    <div class="u-modal-md" id="vnd-card">

        <div id="vnd-header">
            <div id="vnd-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <div>
                    <p class="vnd-hdr-tag">Vendor Master Data</p>
                    <p class="vnd-hdr-title" id="vnd-modal-title">Add Vendor</p>
                </div>
            </div>
            <button type="button" id="vnd-modal-close">&times;</button>
        </div>

        <div id="vnd-err"></div>

        <form id="vnd-form" novalidate>
            <div id="vnd-body">
                <input type="hidden" id="vnd-mode" value="add">
                <input type="hidden" id="vnd-id" value="">

                <p class="vnd-section">Identity</p>
                <div class="vnd-row">
                    <div class="vnd-group">
                        <label class="vnd-label">Vendor Code <span class="req">*</span></label>
                        <input type="text" class="vnd-input" id="vnd-vendor-code" maxlength="50" placeholder="e.g. VEN-2026-001">
                        <p class="vnd-hint" id="vnd-code-hint">Unique identifier.</p>
                    </div>
                    <div class="vnd-group">
                        <label class="vnd-label">Vendor Type</label>
                        <select class="vnd-input" id="vnd-vendor-type">
                            <option value="">— Select type —</option>
                        </select>
                    </div>
                </div>
                <div class="vnd-group full">
                    <label class="vnd-label">Vendor Name <span class="req">*</span></label>
                    <input type="text" class="vnd-input" id="vnd-vendor-name" maxlength="500" placeholder="Registered business name">
                </div>
                <div class="vnd-row">
                    <div class="vnd-group">
                        <label class="vnd-label">TIN</label>
                        <input type="text" class="vnd-input" id="vnd-tin" maxlength="50" placeholder="000-000-000-000">
                    </div>
                    <div class="vnd-group">
                        <label class="vnd-label">Status</label>
                        <select class="vnd-input" id="vnd-status">
                            <option value="ACTIVE">Active</option>
                            <option value="INACTIVE">Inactive</option>
                            <option value="BLACKLISTED">Blacklisted</option>
                        </select>
                        <p class="vnd-hint">Only Active vendors can be picked on a purchase order.</p>
                    </div>
                </div>

                <p class="vnd-section">Tax</p>
                <div class="vnd-row">
                    <div class="vnd-group">
                        <label class="vnd-label">Tax Classification</label>
                        <input type="text" class="vnd-input" id="vnd-tax-class" maxlength="50" placeholder="e.g. VAT, Non-VAT">
                    </div>
                    <div class="vnd-group">
                        <label class="vnd-label">&nbsp;</label>
                        <label class="vnd-check"><input type="checkbox" id="vnd-vat"> VAT registered</label>
                    </div>
                </div>

                <p class="vnd-section">Contact</p>
                <div class="vnd-group full">
                    <label class="vnd-label">Address</label>
                    <textarea class="vnd-input" id="vnd-address" rows="2" placeholder="Street, barangay, city / municipality, province"></textarea>
                </div>
                <div class="vnd-row">
                    <div class="vnd-group">
                        <label class="vnd-label">Contact Person</label>
                        <input type="text" class="vnd-input" id="vnd-contact-person" maxlength="255">
                    </div>
                    <div class="vnd-group">
                        <label class="vnd-label">Contact Number</label>
                        <input type="text" class="vnd-input" id="vnd-contact-number" maxlength="50">
                    </div>
                </div>
                <div class="vnd-group full">
                    <label class="vnd-label">Email</label>
                    <input type="email" class="vnd-input" id="vnd-email" maxlength="255" placeholder="name@company.ph">
                </div>

                <p class="vnd-section">Bank</p>
                <div class="vnd-row" style="margin-bottom:0;">
                    <div class="vnd-group">
                        <label class="vnd-label">Bank Name</label>
                        <input type="text" class="vnd-input" id="vnd-bank-name" maxlength="255">
                    </div>
                    <div class="vnd-group">
                        <label class="vnd-label">Bank Account Number</label>
                        <input type="text" class="vnd-input" id="vnd-bank-acct" maxlength="100">
                    </div>
                </div>

                <p class="vnd-section">Chart of Accounts Assignment</p>
                <p style="font-size:12px;color:var(--afims-text-muted, #62657E);margin:-8px 0 14px;">Optional. GL and Sub-Ledger can also be linked later via "Link SL" on the vendor list.</p>

                <p class="vnd-hint" style="font-weight:700;color:var(--afims-text, #1A1B21);text-transform:uppercase;letter-spacing:.06em;font-size:11px;margin-bottom:12px;">1. Select GL Account</p>
                <div class="vnd-group full" style="position:relative;">
                    <label class="vnd-label">Search GL Account</label>
                    <input type="hidden" id="vnd-form-gl-account-id" value="">
                    <input type="text" class="vnd-input" id="vnd-form-gl-search" maxlength="500" placeholder="Type GL account code or name...">
                    <div id="vnd-form-gl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:200px;overflow-y:auto;z-index:1000;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                    <p class="vnd-hint">This is the parent payables account for the vendor.</p>
                </div>
                <div id="vnd-form-gl-selected" style="padding:10px;background:#F4F7FA;border-radius:6px;display:none;margin:-4px 0 14px;">
                    <strong>GL:</strong> <span id="vnd-form-gl-selected-text"></span>
                </div>

                <div id="vnd-form-sl-section" style="margin-top:18px;opacity:.5;pointer-events:none;">
                    <p class="vnd-hint" style="font-weight:700;color:var(--afims-text, #1A1B21);text-transform:uppercase;letter-spacing:.06em;font-size:11px;margin-bottom:12px;">2. Select or Create Sub-Ledger (Child Account)</p>
                    <div class="vnd-group full" style="position:relative;">
                        <label class="vnd-label">Search Sub-Ledger</label>
                        <input type="text" class="vnd-input" id="vnd-form-sl-search" maxlength="500" placeholder="Select a GL account first…" disabled>
                        <div id="vnd-form-sl-dropdown" style="display:none;position:absolute;top:100%;left:0;right:0;margin-top:4px;background:#fff;border:1px solid #E4ECF2;border-radius:8px;max-height:200px;overflow-y:auto;z-index:999;box-shadow:0 4px 12px rgba(0,0,0,.1);"></div>
                        <p class="vnd-hint">Pick an existing child account under this GL, or create a new one below.</p>
                        <p id="vnd-form-sl-empty-note" class="vnd-hint" style="display:none;color:var(--afims-danger, #DC3545);">No child accounts exist under this GL account yet. Create one below.</p>
                    </div>
                    <div id="vnd-form-sl-selected-item" style="padding:10px;background:#F4F7FA;border-radius:6px;display:none;margin-top:8px;">
                        <strong>Selected:</strong> <span id="vnd-form-sl-selected-code"></span>
                    </div>
                    <input type="hidden" id="vnd-form-sl-selected-code-value">

                    <div class="vnd-group full" style="margin-top:14px;border-top:1px dashed var(--afims-border, #E4ECF2);padding-top:14px;">
                        <label class="vnd-label">New Sub-Ledger Title</label>
                        <input type="text" class="vnd-input" id="vnd-form-sl-new-title" maxlength="255" placeholder="e.g., 0213 CONSTRUCTION AND SUPPLIES OPC">
                        <p class="vnd-hint">Not in the list above? Type a title and click Create.</p>
                        <button type="button" class="vnd-btn vnd-btn-ghost" id="vnd-form-sl-create-btn" style="margin-top:8px;align-self:flex-start;">+ Create Sub-Ledger Account</button>
                    </div>
                </div>
            </div>
            <div id="vnd-footer">
                <button type="button" class="vnd-btn vnd-btn-ghost" id="vnd-cancel">Cancel</button>
                <button type="submit" class="vnd-btn vnd-btn-navy" id="vnd-save">Save Vendor</button>
            </div>
        </form>
    </div>
</div>
