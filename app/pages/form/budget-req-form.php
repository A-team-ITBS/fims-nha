<?php
/**
 * budget-req-form.php
 *
 * Add / Edit modal spanning two distinct backend shapes:
 *   fims.budget_req          — header; CO stores its one allocation directly on it
 *   fims.budget_line_items   — PS/MOOE allocations, one row per chart-of-accounts
 *                              entry, FK'd to a budget_req header
 *
 * The expense class picked in the header decides which section shows:
 *   CO (and anything not COA-driven) -> #bgr-co-section: single Program/Project + Location + Amount,
 *                                        submitted as one fims.budget_req row (POST/PUT /budget_req)
 *   PS / MOOE                        -> #bgr-li-section: repeatable Account Code | Account Name |
 *                                        Particulars | Amount rows, added via "+ Add Line Item" and
 *                                        submitted together as a header + line items batch
 *                                        (POST /budget_req/add-with-items)
 *
 * Trigger:
 *   bgr_openModal('add')                      — new budget request
 *   bgr_openModal('edit', row, 'header')       — edit a CO header (fims.budget_req)
 *   bgr_openModal('edit', row, 'lineitem')     — edit one PS/MOOE line item (fims.budget_line_items);
 *                                                fiscal year / expense class are locked (they belong
 *                                                to the parent header, not this line item)
 *
 * Self-contained styling (scoped under #bgr-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     BUDGET REQUEST FORM MODAL   JS namespace: bgr_
══════════════════════════════════════════════════════════ -->
<style>
    #bgr-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #bgr-overlay.open { display:flex; }
    #bgr-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:1040px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:bgrIn .2s ease; }
    @keyframes bgrIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #bgr-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #bgr-header-info { display:flex;align-items:center;gap:10px; }
    #bgr-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .bgr-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .bgr-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #bgr-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #bgr-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #bgr-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #bgr-body { flex:1;overflow-y:auto;padding:22px; }
    .bgr-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    .bgr-group { display:flex;flex-direction:column;gap:5px; }
    .bgr-group.full { margin-bottom:16px; }
    .bgr-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .bgr-label .req { color:var(--afims-danger, #DC3545); }
    .bgr-input, .bgr-select, .bgr-textarea { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .bgr-textarea { resize:vertical;min-height:64px; }
    .bgr-input:focus, .bgr-select:focus, .bgr-textarea:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .bgr-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 16px; }

    .bgr-amt-wrap { display:flex;align-items:stretch;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgr-amt-wrap:focus-within { border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .bgr-amt-prefix { display:flex;align-items:center;padding:0 12px;background:var(--afims-surface-subtle, #F4F7FA);
        color:var(--afims-text-disabled, #A9AAB8);font-weight:700;font-size:13px;border-right:1px solid var(--afims-border, #E4ECF2); }
    .bgr-amt-wrap .bgr-input { border:none;border-radius:0;text-align:right;font-family:'IBM Plex Mono',monospace; }
    .bgr-amt-wrap .bgr-input:focus { box-shadow:none; }

    .bgr-sec-hd { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em;margin:4px 0 10px; }

    /* ── Line items table ─────────────────────────────────────────────── */
    /* #bgr-li-outer holds two siblings: the scrollable table (#bgr-li-wrap)
       and a fixed remove-button column (#bgr-li-remove-col) that is NOT part
       of the scroll area, so it's always visible without scrolling right.
       Row heights are kept in sync via matching explicit heights below —
       AND, critically, #bgr-li-remove-col gets a bottom padding set by JS
       (bgr_syncRemoveColGap) equal to whatever horizontal scrollbar height
       #bgr-li-wrap is actually showing. That scrollbar reserves space at the
       bottom of #bgr-li-wrap that #bgr-li-remove-col has no equivalent for,
       and that was the real cause of the two columns drifting out of
       alignment last time — not the row heights themselves. */
    #bgr-li-outer { display:flex;align-items:stretch;border:1px solid var(--afims-border, #E4ECF2);
        border-radius:10px;overflow:hidden; }
    #bgr-li-wrap { overflow-x:auto;flex:1;min-width:0; }
    #bgr-li-tbl { width:100%;border-collapse:collapse;min-width:860px;table-layout:fixed; }
    #bgr-li-tbl th { height:34px;box-sizing:border-box;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:0 6px;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:2px solid var(--afims-border, #E4ECF2); }
    #bgr-li-tbl td { height:44px;box-sizing:border-box;padding:6px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:middle; }
    .bgr-li-input, .bgr-li-select { width:100%;padding:7px 9px;border:1px solid var(--afims-border, #E4ECF2);
        border-radius:6px;font-size:12px;font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);box-sizing:border-box; }
    .bgr-li-input:focus, .bgr-li-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E); }
    .bgr-li-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);
        text-overflow:ellipsis; }
    .bgr-li-amt { text-align:right;font-family:'IBM Plex Mono',monospace; }

    #bgr-li-remove-col { flex-shrink:0;width:42px;display:flex;flex-direction:column;
        border-left:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF); }
    #bgr-li-remove-head { height:34px;box-sizing:border-box;background:var(--afims-surface-subtle, #F4F7FA);
        border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove-cell { height:44px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove { border:none;background:transparent;color:var(--afims-danger, #DC3545);
        font-size:18px;line-height:1;cursor:pointer;width:26px;height:26px;border-radius:6px; }
    .bgr-li-remove:hover { background:var(--afims-danger-soft, #FAE1E3); }
    #bgr-add-line-item { margin-top:12px; }

    #bgr-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #bgr-footer .bgr-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #bgr-footer .bgr-btn:disabled { opacity:.5;cursor:not-allowed; }
    #bgr-footer .bgr-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #bgr-footer .bgr-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #bgr-footer .bgr-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #bgr-footer .bgr-btn-navy:hover:not(:disabled) { opacity:.92; }

    #bgr-add-line-item.bgr-btn-ghost { height:34px;padding:0 16px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);font-size:12px;font-weight:600;
        font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    #bgr-add-line-item.bgr-btn-ghost:hover { background:var(--afims-surface-subtle, #F4F7FA); }

    @media (max-width: 520px) { .bgr-row { grid-template-columns:1fr; } }
</style>

<div id="bgr-overlay">
    <div id="bgr-card">

        <div id="bgr-header">
            <div id="bgr-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
                <div>
                    <p class="bgr-hdr-tag">Budget Requirement</p>
                    <p class="bgr-hdr-title" id="bgr-modal-title">Add Budget Request</p>
                </div>
            </div>
            <button type="button" id="bgr-modal-close">&times;</button>
        </div>

        <div id="bgr-err"></div>

        <form id="bgr-form">
            <div id="bgr-body">
                <input type="hidden" id="bgr-mode" value="add">
                <input type="hidden" id="bgr-edit-target" value="header">
                <input type="hidden" id="bgr-id" value="">

                <div class="bgr-row">
                    <div class="bgr-group">
                        <label class="bgr-label">Fiscal Year <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-fiscal-yr" required>
                            <option value="">— Select fiscal year —</option>
                        </select>
                    </div>
                    <div class="bgr-group">
                        <label class="bgr-label">Expense Class <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-expense-class-id" required>
                            <option value="">— Select expense class —</option>
                        </select>
                    </div>
                </div>
                <p class="bgr-hint" id="bgr-mode-hint">Expense class decides whether this allocates to a program/project or a chart of accounts entry.</p>

                <!-- CO (and anything not COA-driven): one program/project allocation on the header itself. -->
                <div id="bgr-co-section">
                    <div class="bgr-row">
                        <div class="bgr-group">
                            <label class="bgr-label">Program</label>
                            <select class="bgr-select" id="bgr-program-code">
                                <option value="">— None —</option>
                            </select>
                        </div>
                        <div class="bgr-group">
                            <label class="bgr-label">Project</label>
                            <select class="bgr-select" id="bgr-project-code">
                                <option value="">— None —</option>
                            </select>
                        </div>
                    </div>
                    <p class="bgr-hint" style="margin-top:-10px;">Select a program or a project, not both.</p>
                    <div class="bgr-row">
                        <div class="bgr-group">
                            <label class="bgr-label">Location</label>
                            <input type="text" class="bgr-input" id="bgr-location" maxlength="255" placeholder="e.g. Region IV-A">
                        </div>
                        <div class="bgr-group">
                            <label class="bgr-label">Requesting Amount <span class="req">*</span></label>
                            <div class="bgr-amt-wrap">
                                <span class="bgr-amt-prefix">&#8369;</span>
                                <input type="text" inputmode="decimal" class="bgr-input" id="bgr-amount" placeholder="0.00" oninput="bgr_fmtAmount(this)">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PS / MOOE: repeatable chart-of-accounts line items. -->
                <div id="bgr-li-section" hidden>
                    <p class="bgr-sec-hd">Line Items</p>
                    <div id="bgr-li-outer">
                        <div id="bgr-li-wrap">
                            <table id="bgr-li-tbl">
                                <thead id="bgr-li-thead"></thead>
                                <tbody id="bgr-li-tbody"></tbody>
                            </table>
                        </div>
                        <div id="bgr-li-remove-col">
                            <div id="bgr-li-remove-head"></div>
                        </div>
                    </div>
                    <button type="button" id="bgr-add-line-item" class="bgr-btn-ghost">+ Add Line Item</button>
                </div>
            </div>
            <div id="bgr-footer">
                <button type="button" class="bgr-btn bgr-btn-ghost" id="bgr-cancel">Cancel</button>
                <button type="submit" class="bgr-btn bgr-btn-navy" id="bgr-save">Save Request</button>
            </div>
        </form>
    </div>
</div>
