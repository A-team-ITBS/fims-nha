<?php
/**
 * budget-req-form.php
 *
 * Add-a-Program / Edit-a-Program modal — AND the Add/Edit modal for a COE Budget
 * Request ('add-coe'/'edit-coe' modes, toggled via the #bgr-card.bgr-mode-coe CSS
 * class — see #bgr-coe-root/bgr_addCoeSubComponentBlock() for the COE-only markup
 * and bgr_saveCoe() for its own save path). Opened from:
 *   - app/pages/budget-req-page.php's "+ Add Budget Request" button ('add' mode) and
 *     "+ Add COE Budget Request" button ('add-coe' mode)
 *   - a Program row's "Edit" action, or the Edit button inside the Program
 *     View modal (readone/budget-req-readone.php) ('edit-program' mode)
 *   - a COE row's own "Edit" action ('edit-coe' mode)
 *
 * fims.budget_req is a four-level structure, one request per fiscal year:
 *   fims.budget_req          — header: Fiscal Year, Status (Draft -> For Review)
 *   fims.budget_req_program  — one "Program block" per program added to the
 *                              request (repeatable across saves — each save
 *                              of THIS form adds/edits exactly one of these)
 *   fims.budget_req_project  — one "Project line item" per project added to
 *                              a Program block (repeatable "+ Add Project"
 *                              blocks within this form)
 *   fims.budget_line_items   — one "Chart of Accounts line item" per account
 *                              allocation under a project block (repeatable
 *                              "+ Add Line Item" rows nested inside each block)
 * A COE request skips the middle two levels entirely — its budget_line_items rows
 * hang directly off the header (budget_req_project_id stays NULL); see
 * BudgetReqService::addCoeRequest()/updateCoeRequest().
 *
 * Trigger:
 *   bgr_openModal('add')                    — add a Program to the request for
 *                                              the chosen fiscal year (creates
 *                                              that request if none exists yet,
 *                                              or appends to it if it's still Draft)
 *   bgr_openModal('edit-program', program)  — edit one existing Program's own
 *                                              projects/line items; `program`
 *                                              must include budget_req_program_id,
 *                                              fiscal_yr, program_code,
 *                                              sub_program_code, projects[]
 *   bgr_openModal('add-coe')                — add line items to the COE request for
 *                                              the chosen fiscal year (same
 *                                              create-or-append rule as 'add')
 *   bgr_openModal('edit-coe', header)        — fully replace one COE request's line
 *                                              items + documents; `header` must
 *                                              include budget_reference_number,
 *                                              fiscal_yr, coe_line_items[]
 *
 * After a successful save this calls window.bgr_afterSave?.(mode, json) —
 * each host page defines that hook to its own refresh logic, keeping this
 * partial decoupled from page-specific behavior.
 *
 * Self-contained styling (scoped under #bgr-card) AND self-contained JS
 * (this file loads its own reference data and binds its own modal events)
 * so it renders and works correctly regardless of which host page includes
 * it or whether that page's own script has run yet.
 */
$_bgr_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<!-- ══════════════════════════════════════════════════════════
     BUDGET REQUEST FORM MODAL   JS namespace: bgr_
══════════════════════════════════════════════════════════ -->
<style>
    #bgr-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #bgr-overlay.open { display:flex; }
    #bgr-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;
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
    #bgr-modal-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #bgr-modal-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #bgr-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 35%, transparent);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    /* #bgr-body/#bgr-footer live inside this <form>, not directly inside
       #bgr-card — a plain <form> isn't a flex container by default, so
       without this, their flex:1/flex-shrink:0 rules below do nothing: the
       form just grows to fit all its content and gets silently clipped by
       #bgr-card's overflow:hidden, with no scrollbar ever appearing. This
       makes the form itself the flex item (filling #bgr-card's remaining
       space) and a flex container in turn for its own children. */
    #bgr-form { flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden; }

    #bgr-body { flex:1;min-height:0;overflow-y:auto;padding:22px; }
    .bgr-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    /* Fiscal Year / Region / Grand Total — the request-level row (#bgr-top-row). COE mode has no
       Region (see bgr_openModal), so it switches down to the plain two-column .bgr-row layout via
       the .bgr-coe-row modifier below instead. */
    .bgr-row-3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:16px; }
    #bgr-top-row.bgr-coe-row { grid-template-columns:1fr 1fr; }
    #bgr-top-row.bgr-coe-row #bgr-region-group { display:none; }
    /* A Project's own Type of Fund/Component/Sub-Component/Proposed Amount row — four fields. */
    .bgr-row-4 { display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:16px; }
    /* Same row, plus Source of Fund and Program Source (both before Proposed Amount) — six fields. */
    .bgr-row-6 { display:grid;grid-template-columns:repeat(6, 1fr);gap:14px;margin-bottom:16px; }
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
    .bgr-select:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .bgr-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 16px; }

    .bgr-amt-wrap { display:flex;align-items:stretch;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgr-amt-wrap:focus-within { border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .bgr-amt-prefix { display:flex;align-items:center;padding:0 12px;background:var(--afims-surface-subtle, #F4F7FA);
        color:var(--afims-text-disabled, #A9AAB8);font-weight:700;font-size:13px;border-right:1px solid var(--afims-border, #E4ECF2); }
    .bgr-amt-wrap .bgr-input { border:none;border-radius:0;text-align:right;font-family:'IBM Plex Mono',monospace; }
    .bgr-amt-wrap .bgr-input:focus { box-shadow:none; }

    .bgr-sec-hd { font-size:12px;font-weight:700;color:var(--afims-heading-fill, #000E3E);
        text-transform:uppercase;letter-spacing:.05em;margin:4px 0 10px;padding-bottom:7px;
        border-bottom:2px solid var(--afims-border, #E4ECF2); }

    /* ── Program blocks (regular 'add'/'edit-program' flow only — one per fims.budget_req_program,
       repeatable via "+ Add Program"; COE mode has no Program at all, see #bgr-coe-root below) ──── */
    #bgr-pblocks-wrap { display:flex;flex-direction:column;gap:18px;margin-bottom:12px; }
    .bgr-pblock { border:1px solid color-mix(in srgb, var(--afims-heading-fill, #000E3E) 20%, var(--afims-border, #E4ECF2));
        border-radius:12px;padding:16px;background:var(--afims-surface, #FFFFFF); }
    .bgr-pblock-hd { display:flex;align-items:flex-start;gap:10px;margin-bottom:4px; }
    .bgr-pblock-fields { flex:1;min-width:0; }
    .bgr-pblock-remove { flex-shrink:0;border:1px solid var(--afims-danger-soft, #FAE1E3);
        background:var(--afims-danger-soft, #FAE1E3);color:var(--afims-danger, #DC3545);
        font-size:24px;line-height:1;cursor:pointer;width:42px;height:42px;border-radius:8px;margin-top:20px;
        transition:background .15s,color .15s; }
    .bgr-pblock-remove:hover { background:var(--afims-danger, #DC3545);color:var(--afims-pure-white, #FFFFFF); }

    /* ── Project blocks ───────────────────────────────────────────────── */
    /* Shared by #bgr-coe-blocks-wrap (COE mode's repeatable Sub-Component blocks) and every Program
       block's own .bgr-pblock-projects-wrap (regular mode) — not a single global element anymore. */
    .bgr-blocks-wrap { display:flex;flex-direction:column;gap:16px;margin-bottom:12px; }
    .bgr-block { border:1px solid color-mix(in srgb, var(--afims-border, #E4ECF2) 55%, var(--afims-text-disabled, #A9AAB8));
        border-radius:10px;padding:14px;background:var(--afims-surface-subtle, #F4F7FA);
        box-shadow:0 1px 3px rgba(15,32,69,.08);transition:box-shadow .15s,border-color .15s; }
    .bgr-block:hover { border-color:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 30%, var(--afims-border, #E4ECF2));
        box-shadow:0 2px 10px rgba(15,32,69,.12); }
    .bgr-block-hd { display:flex;align-items:flex-start;gap:10px;padding-bottom:14px;margin-bottom:14px;
        border-bottom:1px solid color-mix(in srgb, var(--afims-border, #E4ECF2) 55%, var(--afims-text-disabled, #A9AAB8)); }
    .bgr-block.bgr-collapsed .bgr-block-hd { padding-bottom:0;margin-bottom:0;border-bottom:none; }
    .bgr-block-project-wrap { flex:1;min-width:0; }
    .bgr-block-project-code-wrap { flex:0 0 260px;min-width:0; }
    .bgr-block-project-code-display { font-family:'IBM Plex Mono',monospace;background:var(--afims-surface, #FFFFFF);
        border-style:dashed;color:var(--afims-text-muted, #62657E);cursor:default; }
    /* Sum of every project block's own Total row (.bgr-block-li-total-amt) —
       see bgr_updateGrandTotal(), kept live on every amount/row/project change. */
    #bgr-grand-total-display { font-family:'IBM Plex Mono',monospace;font-weight:700;text-align:right;
        background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E);cursor:default; }
    .bgr-block-location-wrap { flex:0 0 240px;min-width:0; }
    .bgr-block-toggle { flex-shrink:0;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-heading-fill, #000E3E);
        cursor:pointer;width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        margin-top:20px;transition:transform .15s,background .15s; }
    .bgr-block-toggle:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    .bgr-block.bgr-collapsed .bgr-block-toggle { transform:rotate(-90deg); }
    .bgr-block-body { display:flex;flex-direction:column;gap:12px; }
    .bgr-block.bgr-collapsed .bgr-block-body { display:none; }
    .bgr-block-remove { flex-shrink:0;border:1px solid var(--afims-danger-soft, #FAE1E3);
        background:var(--afims-danger-soft, #FAE1E3);color:var(--afims-danger, #DC3545);
        font-size:24px;line-height:1;cursor:pointer;width:42px;height:42px;border-radius:8px;margin-top:20px;
        transition:background .15s,color .15s; }
    .bgr-block-remove:hover { background:var(--afims-danger, #DC3545);color:var(--afims-pure-white, #FFFFFF); }

    /* ── Line items table (nested per block) ──────────────────────────── */
    /* .bgr-block-li-outer holds two siblings: the scrollable table
       (.bgr-block-li-wrap) and a fixed remove-button column
       (.bgr-block-li-remove-col) that is NOT part of the scroll area, so
       it's always visible without scrolling right. Row heights are kept
       in sync via matching explicit heights below — AND, critically,
       .bgr-block-li-remove-col gets a bottom padding set by JS
       (bgr_syncRemoveColGap) equal to whatever horizontal scrollbar height
       .bgr-block-li-wrap is actually showing. That scrollbar reserves
       space at the bottom of .bgr-block-li-wrap that
       .bgr-block-li-remove-col has no equivalent for, and that was the
       real cause of the two columns drifting out of alignment last time —
       not the row heights themselves. */
    .bgr-block-li-outer { display:flex;align-items:stretch;
        border:1px solid color-mix(in srgb, var(--afims-border, #E4ECF2) 55%, var(--afims-text-disabled, #A9AAB8));
        border-radius:10px;overflow:hidden;background:var(--afims-surface, #FFFFFF); }
    .bgr-block-li-tbl td { background:var(--afims-surface, #FFFFFF); }
    .bgr-block-li-wrap { overflow-x:auto;flex:1;min-width:0; }
    .bgr-block-li-tbl { width:100%;border-collapse:collapse;table-layout:fixed; }
    .bgr-block-li-tbl th { height:34px;box-sizing:border-box;font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:0 6px;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-block-li-tbl td { height:44px;box-sizing:border-box;padding:6px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:middle;
        max-width:0;overflow:hidden; } /* max-width:0 + table-layout:fixed forces the cell (and its input) to the column's declared %, instead of growing to fit the input's own browser-default intrinsic width */
    .bgr-li-input, .bgr-li-select { width:100%;min-width:0;padding:7px 9px;border:1px solid var(--afims-border, #E4ECF2);
        border-radius:6px;font-size:12px;font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);box-sizing:border-box; }
    .bgr-li-input:focus, .bgr-li-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E); }
    .bgr-li-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);
        text-overflow:ellipsis; }
    /* Starts at the same compact height as the other row inputs; drag the
       handle to grow it for longer text. Table row height isn't forced to
       keep up (td has no max-height), so it just grows with it normally —
       except the separate .bgr-li-remove-cell sibling (a plain div, not a
       real table row, used for the split remove-button column) stays fixed
       at 44px and won't follow a resized row. Minor known mismatch, not
       worth a per-row ResizeObserver for. */
    textarea.bgr-li-particulars { resize:vertical;min-height:30px;max-height:200px;line-height:1.3;font-family:inherit; }
    .bgr-li-amt { text-align:right;font-family:'IBM Plex Mono',monospace; }

    .bgr-block-li-total-row td { height:38px;box-sizing:border-box;padding:6px 6px 6px 9px;border-bottom:none;
        background:var(--afims-surface-subtle, #F4F7FA);font-size:12px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .bgr-block-li-total-amt { text-align:right;font-family:'IBM Plex Mono',monospace; }
    /* Lines up with .bgr-block-li-total-row in the split remove-button
       column — that row has no remove button of its own (it's computed),
       but without a matching placeholder here the column's flex-stretch
       (.bgr-block-li-outer) just leaves bare empty space over it instead. */
    .bgr-li-remove-foot { height:38px;box-sizing:border-box;background:var(--afims-surface-subtle, #F4F7FA); }

    .bgr-block-li-remove-col { flex-shrink:0;width:42px;display:flex;flex-direction:column;
        border-left:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF); }
    .bgr-li-remove-head { height:34px;box-sizing:border-box;background:var(--afims-surface-subtle, #F4F7FA);
        border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove-cell { height:44px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove { border:none;background:transparent;color:var(--afims-danger, #DC3545);
        font-size:18px;line-height:1;cursor:pointer;width:26px;height:26px;border-radius:6px; }
    .bgr-li-remove:hover { background:var(--afims-danger-soft, #FAE1E3); }
    .bgr-block-add-li { margin-top:12px; }

    /* ── Per-project Supporting Documents ──────────────────────────────── */
    .bgr-block-docs { margin-top:4px;padding-top:14px;border-top:1px solid var(--afims-border, #E4ECF2); }
    .bgr-block-docs-title { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px; }
    .bgr-block-docs-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 10px; }
    .bgr-block-doc-tbl { width:100%;border-collapse:collapse;margin-bottom:10px; }
    .bgr-block-doc-tbl th { height:30px;box-sizing:border-box;font-size:10px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:0 6px;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-block-doc-tbl td { padding:6px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:middle; }
    .bgr-doc-panel { display:flex;align-items:center;gap:6px;min-width:0; }
    .bgr-doc-upload-btn { display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;
        color:var(--afims-text, #1A1B21);background:var(--afims-surface-subtle, #F4F7FA);
        border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;padding:6px 10px;
        cursor:pointer;white-space:nowrap;flex-shrink:0; }
    .bgr-doc-upload-btn:hover { background:var(--afims-surface, #FFFFFF); }
    .bgr-doc-uploaded-name { font-size:11px;color:var(--afims-text-muted, #62657E);flex:1;min-width:0;
        overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .bgr-doc-open { font-size:11.5px;color:var(--afims-heading-fill, #000E3E);text-decoration:none;
        white-space:nowrap;flex-shrink:0;font-weight:600; }
    .bgr-doc-remove { border:none;background:transparent;color:var(--afims-danger, #DC3545);
        font-size:16px;line-height:1;cursor:pointer;width:24px;height:24px;border-radius:6px;flex-shrink:0; }
    .bgr-doc-remove:hover { background:var(--afims-danger-soft, #FAE1E3); }
    .bgr-doc-row-error { font-size:11px;color:var(--afims-danger, #DC3545);margin-top:4px; }

    #bgr-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #bgr-footer .bgr-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #bgr-footer .bgr-btn:disabled { opacity:.5;cursor:not-allowed; }
    #bgr-footer .bgr-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #bgr-footer .bgr-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #bgr-footer .bgr-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #bgr-footer .bgr-btn-navy:hover:not(:disabled) { opacity:.92; }

    .bgr-btn-ghost { height:34px;padding:0 16px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);font-size:12px;font-weight:600;
        font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    .bgr-btn-ghost:hover { background:var(--afims-surface-subtle, #F4F7FA); }

    /* ── Project typeahead (twitter typeahead.js) ──────────────────────── */
    /* The library wraps the input in a plain inline-block span by default —
       force it to behave like every other full-width field in this form. */
    .bgr-block-project-wrap .twitter-typeahead,
    .bgr-block-program-source-wrap .twitter-typeahead { display:block !important;width:100%; }
    .tt-menu { width:100%;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);
        border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14);margin-top:4px;z-index:10000;
        max-height:260px;overflow-y:auto;overflow-x:hidden; }
    /* Program Source's own column is one of six in its row (.bgr-row-6) — far too narrow for its
       entries (full sentences), which wrapped onto 3-4 lines each at 100% width. Widened past the
       input's own width (anchored to its left edge, extending right over whatever sits next to it) —
       #bgr-body has no horizontal clipping, and the modal itself (.u-modal-2xl) is comfortably wider
       than this, so there's room for it to float over neighboring fields while open, same as the
       Project field's own suggestion menu already does when it overlaps sibling blocks. */
    .bgr-block-program-source-wrap .tt-menu { width:420px;max-width:min(420px, 90vw); }
    .tt-suggestion { padding:8px 12px;font-size:13px;color:var(--afims-text, #1A1B21);cursor:pointer;transition:background .1s,color .1s; }
    .tt-suggestion:hover, .tt-suggestion.tt-cursor { background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    .tt-not-found { padding:8px 12px;font-size:12px;color:var(--afims-text-disabled, #A9AAB8);font-style:italic; }

    /* ── Account Code picker (plain custom dropdown, not typeahead.js) ───
       The Account Code field sits inside .bgr-block-li-wrap (overflow-x:auto,
       which per spec forces its overflow-y to clip too) and .bgr-block-li-outer
       (overflow:hidden, for rounded corners) — both would clip a normal
       position:absolute dropdown no matter its z-index. A third-party
       library fighting that clipping by forcing position:fixed caused a
       real race (it kept re-applying its own absolute-positioned top/left
       after ours), so this field uses no library at all: bgr_initAccountPicker
       builds and positions this dropdown itself, with nothing else able to
       stomp on it. */
    .bgr-acct-menu { display:none;position:fixed;background:var(--afims-surface, #FFFFFF);
        border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14);
        z-index:10000;max-height:260px;overflow-y:auto;overflow-x:hidden; }
    .bgr-acct-menu.open { display:block; }
    .bgr-acct-option { padding:8px 12px;font-size:13px;color:var(--afims-text, #1A1B21);cursor:pointer;transition:background .1s,color .1s; }
    .bgr-acct-option:hover, .bgr-acct-option.active { background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    .bgr-acct-empty { padding:8px 12px;font-size:12px;color:var(--afims-text-disabled, #A9AAB8);font-style:italic; }

    /* ── COE mode's own Sub-Component blocks (#bgr-coe-blocks-wrap) — each is a .bgr-block (same
       chrome/line-items styling a regular Project block uses) with its own small header row (Sub-
       Component select + remove button) instead of the Project-specific header .bgr-block-hd hides
       in COE mode. ──────────────────────────────────────────────────────────────────────────────── */
    /* align-items:flex-start (not flex-end) to match .bgr-block-remove's own baked-in margin-top:20px
       — the same trick .bgr-block-hd/.bgr-pblock-hd use to align a label-less remove button with an
       input sitting below a label. */
    .bgr-coe-subcomp-hd { display:flex;align-items:flex-start;gap:10px;margin-bottom:14px; }
    .bgr-coe-subcomp-hd .bgr-group { flex:1;min-width:0;margin-bottom:0; }

    @media (max-width: 520px) { .bgr-row { grid-template-columns:1fr; } }
    @media (max-width: 760px) { .bgr-row-4 { grid-template-columns:1fr; } }
    @media (max-width: 640px) { .bgr-row-3 { grid-template-columns:1fr; } }
    @media (max-width: 1100px) { .bgr-row-6 { grid-template-columns:1fr 1fr 1fr; } }
    @media (max-width: 760px) { .bgr-row-6 { grid-template-columns:1fr 1fr; } }
    @media (max-width: 480px) { .bgr-row-6 { grid-template-columns:1fr; } }

    /* ── Dark mode ──────────────────────────────────────────────────────
       --afims-heading-fill stays NAVY in dark mode (it is a fill colour for white text), so every place above that
       uses it as a TEXT colour (Projects heading, Grand Total, collapse chevron, doc link) became navy-on-navy.
       The selects also rendered with the browser's black native dark style instead of the theme surface. */
    html.afims-dark[data-bs-theme="dark"] #bgr-card { color-scheme:dark; }
    html.afims-dark[data-bs-theme="dark"] #bgr-card :is(.bgr-select, .bgr-input, .bgr-textarea, .bgr-li-input, .bgr-li-select) {
        background-color:var(--afims-surface, #1A1F38) !important;border-color:var(--afims-border, #2E3656) !important;
        color:var(--afims-text, #E4ECF2) !important; }
    html.afims-dark[data-bs-theme="dark"] #bgr-card :is(.bgr-select, .bgr-li-select) option {
        background-color:var(--afims-surface, #1A1F38);color:var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] #bgr-card :is(.bgr-input, .bgr-textarea, .bgr-li-input)::placeholder {
        color:var(--afims-text-muted, #AAAFBE);opacity:1; }
    html.afims-dark[data-bs-theme="dark"] #bgr-card :is(.bgr-select, .bgr-input, .bgr-textarea, .bgr-li-input, .bgr-li-select):focus,
    html.afims-dark[data-bs-theme="dark"] #bgr-card .bgr-amt-wrap:focus-within {
        border-color:var(--afims-primary, #3861BC) !important;box-shadow:0 0 0 3px color-mix(in srgb, var(--afims-primary, #3861BC) 25%, transparent); }
    html.afims-dark[data-bs-theme="dark"] #bgr-card :is(.bgr-select, .bgr-li-input, .bgr-li-select):disabled {
        background-color:var(--afims-surface-subtle, #141830) !important;color:var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #bgr-card #bgr-grand-total-display {
        background-color:var(--afims-surface-subtle, #141830) !important;color:var(--afims-text, #E4ECF2) !important; }
    html.afims-dark[data-bs-theme="dark"] #bgr-card .bgr-block-project-code-display { color:var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #bgr-card .bgr-sec-hd { color:var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] #bgr-card .bgr-block-toggle { color:var(--afims-text, #E4ECF2);
        border-color:var(--afims-border, #2E3656); }
    html.afims-dark[data-bs-theme="dark"] #bgr-card .bgr-block-toggle:hover { background:var(--afims-surface-subtle, #141830); }
    html.afims-dark[data-bs-theme="dark"] #bgr-card .bgr-doc-open { color:var(--afims-primary-text, #94AADB); }
</style>

<div id="bgr-overlay">
    <div class="u-modal-2xl" id="bgr-card">

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
                <input type="hidden" id="bgr-id" value="">

                <div class="bgr-row-3" id="bgr-top-row">
                    <div class="bgr-group">
                        <label class="bgr-label">Fiscal Year <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-fiscal-yr" required>
                            <option value="">— Select fiscal year —</option>
                        </select>
                    </div>
                    <!-- Request-level now — one Region for the WHOLE budget request (every Program/Project in
                         it), not picked per-project anymore; see bgr_openModal/bgr_buildProgramPayload. Hidden
                         in COE mode (#bgr-top-row.bgr-coe-row above) — a COE request has no Program/Project at
                         all, so there's nothing for a Region to scope. -->
                    <div class="bgr-group" id="bgr-region-group">
                        <label class="bgr-label">Region <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-region">
                            <option value="">— Select region —</option>
                        </select>
                    </div>
                    <div class="bgr-group">
                        <label class="bgr-label">Grand Total</label>
                        <input type="text" class="bgr-input" id="bgr-grand-total-display" readonly value="0.00">
                    </div>
                </div>
                <p class="bgr-hint" id="bgr-program-context" style="display:none;"></p>

                <!-- 'add-coe'/'edit-coe' — a COE request has no Program/Sub-Program/Project at all,
                     just repeatable Sub-Component blocks, each with its own line-items table (see
                     bgr_openModal). Sibling of #bgr-pblocks-root below; the two are never shown at once. -->
                <div id="bgr-coe-root" style="display:none;">
                    <!-- Type of Fund/Component are shared across the WHOLE COE request (applied to every
                         line item at save time, see bgr_saveCoe()) — both default to this request's usual
                         values ("CORPORATE RECEIPT"/"COE") on a brand-new 'add-coe' request (see
                         bgr_openModal), though either can still be changed. Sub-Component is NOT shared —
                         each "+ Add Sub-Component" block below (#bgr-coe-blocks-wrap) picks its own
                         Sub-Component AND owns its own nested Line Items table, same nesting pattern
                         "+ Add Program"/"+ Add Project" use further down — a request needing both MOOE and
                         Personnel Services line items just adds one block for each. Supporting Documents
                         stays request-level, in its own section below every block (not per Sub-Component). -->
                    <div class="bgr-row">
                        <div class="bgr-group">
                            <label class="bgr-label">Type of Fund <span class="req">*</span></label>
                            <select class="bgr-select" id="bgr-coe-type-of-fund"><option value="">— Select type of fund —</option></select>
                        </div>
                        <div class="bgr-group">
                            <label class="bgr-label">Component</label>
                            <select class="bgr-select" id="bgr-coe-component"><option value="">— None —</option></select>
                        </div>
                    </div>
                    <p class="bgr-sec-hd">Sub-Components</p>
                    <div id="bgr-coe-blocks-wrap" class="bgr-blocks-wrap"></div>
                    <button type="button" id="bgr-coe-add-sub-component" class="bgr-btn-ghost">+ Add Sub-Component</button>

                    <div class="bgr-block-docs" id="bgr-coe-docs">
                        <p class="bgr-block-docs-title">Supporting Documents</p>
                        <p class="bgr-block-docs-hint">Upload a file (≤20 MB — PDF, Word, Excel, or image) or paste a link per document.</p>
                        <table class="bgr-block-doc-tbl" style="display:none;">
                            <thead><tr>
                                <th style="width:35%;">Document Name</th>
                                <th>Attachment</th>
                                <th style="width:26px;"></th>
                            </tr></thead>
                            <tbody class="bgr-block-doc-tbody"></tbody>
                        </table>
                        <div class="bgr-block-doc-actions">
                            <button type="button" class="bgr-block-doc-add-upload bgr-btn-ghost">+ Upload File</button>
                            <button type="button" class="bgr-block-doc-add-link bgr-btn-ghost">+ Paste Link</button>
                        </div>
                    </div>
                </div>

                <!-- 'add'/'edit-program' — one repeatable "Program block" per fims.budget_req_program
                     (Program + Sub-Program + its own Projects), "+ Add Program" appends another.
                     'edit-program' only ever has the one, with no add/remove affordance (see
                     bgr_openModal) — it edits a single existing Program, nothing more. -->
                <div id="bgr-pblocks-root">
                    <div id="bgr-pblocks-wrap"></div>
                    <button type="button" id="bgr-add-program-block" class="bgr-btn-ghost">+ Add Program</button>
                </div>
            </div>
            <div id="bgr-footer">
                <button type="button" class="bgr-btn bgr-btn-ghost" id="bgr-cancel">Cancel</button>
                <button type="submit" class="bgr-btn bgr-btn-navy" id="bgr-save">Save Request</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REQUEST FORM MODAL   prefix: bgr_
   Self-contained: loads its own reference data and binds its own
   events, so it works regardless of which page includes it.
═══════════════════════════════════════════════════════════════ */

const BGR_API                   = '<?php echo $_bgr_api; ?>' + '/budget_req';
const BGR_PROGRAM_API           = '<?php echo $_bgr_api; ?>' + '/program/all';
const BGR_PROJECT_BASE_API      = '<?php echo $_bgr_api; ?>' + '/project';
const BGR_FISCAL_YEAR_API       = '<?php echo $_bgr_api; ?>' + '/fiscal_year';
const BGR_SUB_PROGRAM_API       = '<?php echo $_bgr_api; ?>' + '/sub_program/all';
const BGR_CHART_OF_ACCOUNTS_API = '<?php echo $_bgr_api; ?>' + '/chart_of_accounts/all';
const BGR_ATTACHMENT_API        = '<?php echo $_bgr_api; ?>' + '/budget_req_project_attachment';
const BGR_RC_API                = '<?php echo $_bgr_api; ?>' + '/responsibility-center';
const BGR_TYPE_OF_FUND_API      = '<?php echo $_bgr_api; ?>' + '/type_of_fund';
const BGR_COMPONENT_TYPE_API    = '<?php echo $_bgr_api; ?>' + '/component_type';
const BGR_SUB_COMPONENT_TYPE_API = '<?php echo $_bgr_api; ?>' + '/sub_component_type';
const BGR_PROGRAM_SOURCE_API    = '<?php echo $_bgr_api; ?>' + '/program_source';

// Cached option lists — Program/Sub-Program are prefetched in full and
// filtered client-side (same cascade style as pof_fillProjects() in
// app/pages/po/form.php); Chart-of-Accounts options are prefetched once per
// line-item row; Project is searched live via typeahead instead (see
// bgr_initProjectTypeahead), since the list can be large and is also creatable.
let BGR_PROGRAMS = [];
let BGR_SUB_PROGRAMS = [];
let BGR_COA_OPTIONS = [];
// Regional offices only (office_type 'Regional' — excludes District offices, same office_type
// convention readone/budget-req-readone.php's bgrv_rcDeptType() uses) — the WHOLE request's own Region
// (#bgr-region, request-level), manually picked now that Program-based origination belongs to FMD,
// not the region itself. One Region governs every Program/Project in the request (and becomes the
// budget_req header's own rc_id — see BudgetReqService::fetchHeaderByFiscalYearAndRc()), not picked
// per-project anymore. Prefetched once at bootstrap, same as Programs/Sub-Programs.
let BGR_REGIONS = [];
// A Project's single line item now picks its own Type of Fund up front too (historically only ever
// set later, by Budget Processing — see BudgetLineItemService::create()'s own doc comment).
let BGR_TYPES_OF_FUND = [];
// Same idea as Type of Fund, but optional — Component stays hidden/not required everywhere else this
// column is used (budget-processing-form.php, budget-preview-readone.php), so it is here too.
let BGR_COMPONENTS = [];
// A flat classification list, independent of Component (fims.sub_component_type) — also optional.
let BGR_SUB_COMPONENTS = [];
// "Program (Source)" — a flat list of specific named source programs/projects (fims.program_source),
// independent of the Program/Sub-Program fields above (which only hold the two broad categories) —
// also optional.
let BGR_PROGRAM_SOURCES = [];
let bgr_liSeq = 0;
let bgr_blockSeq = 0;
let bgr_coeSubCompSeq = 0;

const $bgr = id => document.getElementById(id);

// bgr_loadChartOfAccounts is NOT included here — it's only needed once the
// modal actually opens (bgr_openModal awaits it there), so it's not fetched
// at all on page load unless the user opens the form.
// window.bgrFormReady settles once the dropdowns above are filled (Programs, Sub-Programs, and the Fiscal Years the list's own Fiscal Year
// filter shows too). The host page awaits it before it acts on a notification link (budget-req-page.php, bgr_openFromLink), so the year
// it asks for is already in the filter — in order, and not added twice.
let bgr_formReadyResolve;
window.bgrFormReady = new Promise(resolve => { bgr_formReadyResolve = resolve; });
async function bgr_formBootstrap() {
    try {
        await Promise.all([bgr_loadPrograms(), bgr_loadSubPrograms(), bgr_loadFiscalYears(), bgr_loadRegions(), bgr_loadTypesOfFund(), bgr_loadComponents(), bgr_loadSubComponents(), bgr_loadProgramSources()]);
        bgr_bindFormEvents();
        bgr_bindCoeDocSection();
    } finally {
        bgr_formReadyResolve();
    }
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_formBootstrap);
} else {
    bgr_formBootstrap();
}

function bgr_bindFormEvents() {
    $bgr('bgr-modal-close')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-cancel')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-form')?.addEventListener('submit', bgr_save);

    // Fiscal Year affects every Program block's project-code preview (its prefix includes the
    // fiscal year) — refresh all of them, not just one.
    $bgr('bgr-fiscal-yr')?.addEventListener('change', () => {
        document.querySelectorAll('#bgr-pblocks-wrap .bgr-pblock').forEach(bgr_refreshAllProjectCodeFields);
    });
    $bgr('bgr-region')?.addEventListener('change', bgr_onRequestRegionChange);
    $bgr('bgr-add-program-block')?.addEventListener('click', () => bgr_addProgramBlock());
    $bgr('bgr-coe-add-sub-component')?.addEventListener('click', () => bgr_addCoeSubComponentBlock());

    // Program/Sub-Program blocks (and their own "+ Add Project") are added/removed dynamically — event
    // delegation on the one static wrapper covers every block that will ever exist, present or future.
    $bgr('bgr-pblocks-wrap')?.addEventListener('change', (ev) => {
        if (ev.target.matches('.bgr-pblock-program')) bgr_onProgramChange(ev.target.closest('.bgr-pblock'));
        else if (ev.target.matches('.bgr-pblock-sub-program')) bgr_refreshAllProjectCodeFields(ev.target.closest('.bgr-pblock'));
        else if (ev.target.matches('.bgr-block-component')) bgr_onBlockComponentChange(ev.target);
    });
    $bgr('bgr-pblocks-wrap')?.addEventListener('click', (ev) => {
        if (ev.target.closest('.bgr-pblock-add-project')) {
            const pblock = ev.target.closest('.bgr-pblock');
            bgr_addProjectBlock(pblock.querySelector('.bgr-pblock-projects-wrap'), null, false);
        } else if (ev.target.closest('.bgr-pblock-remove')) {
            bgr_removeProgramBlock(ev.target.closest('.bgr-pblock'));
        }
    });

    // A window resize can change whether a block's line-items table needs a
    // horizontal scrollbar at all, which changes how much gap its remove
    // column needs.
    window.addEventListener('resize', () => {
        if ($bgr('bgr-overlay')?.classList.contains('open')) {
            document.querySelectorAll('.bgr-block').forEach(bgr_syncRemoveColGap);
        }
    });
}

async function bgr_loadPrograms() {
    try {
        const res  = await fetch(BGR_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load programs.');
        BGR_PROGRAMS = json.data ?? [];

        // The host page's own Program filter (budget-req-page.php's #bgr-filter-program) — this
        // modal's own Program select is now per-Program-block (bgr_fillPblockPrograms), built fresh
        // whenever a block is added, not a static element here.
        const optsHtml = BGR_PROGRAMS.map(p =>
            `<option value="${bgr_escHtmlAttr(p.program_code)}">${bgr_esc(p.program_code)} — ${bgr_esc(p.program_name)}</option>`
        ).join('');
        $bgr('bgr-filter-program')?.insertAdjacentHTML('beforeend', optsHtml);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Fills one Program block's Program select — `keep` is the program_code to preselect
// ('edit-program' mode; '' for a brand-new block).
function bgr_fillPblockPrograms(selectEl, keep = '') {
    selectEl.innerHTML = '<option value="">— Select program —</option>' + BGR_PROGRAMS.map(p =>
        `<option value="${bgr_escHtmlAttr(p.program_code)}">${bgr_esc(p.program_code)} — ${bgr_esc(p.program_name)}</option>`
    ).join('');
    selectEl.value = BGR_PROGRAMS.some(p => p.program_code === keep) ? keep : '';
}

async function bgr_loadSubPrograms() {
    try {
        const res  = await fetch(BGR_SUB_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load sub-programs.');
        BGR_SUB_PROGRAMS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Builds #bgr-region's own <option> list ONCE (not rebuilt per block/open — there's only ever the
// one select now) — same static-population style bgr_loadFiscalYears() uses for #bgr-fiscal-yr.
// bgr_openModal sets its value/disabled per mode on each open instead.
async function bgr_loadRegions() {
    try {
        const res  = await fetch(`${BGR_RC_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load regions.');
        BGR_REGIONS = (json.data ?? []).filter(rc => rc.office_type === 'Regional');

        const optsHtml = BGR_REGIONS.map(rc =>
            `<option value="${bgr_escHtmlAttr(rc.rc_id)}">${bgr_esc(rc.rc_name)}</option>`
        ).join('');
        $bgr('bgr-region')?.insertAdjacentHTML('beforeend', optsHtml);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Enables/disables every project block's own Project field based on whether a request-level Region
// has been picked yet — a brand-new project can't be resolved/created without one (its code's own RC
// segment needs it; see bgr_updateProjectCodeField), and the typeahead itself is filtered to that
// Region (see bgr_initProjectTypeahead), so there's nothing valid to search/type until then.
function bgr_syncProjectInputsRegionState() {
    const hasRegion = !!$bgr('bgr-region')?.value;
    document.querySelectorAll('#bgr-pblocks-wrap .bgr-block-project-name').forEach(inp => {
        inp.disabled = !hasRegion;
        inp.placeholder = hasRegion ? 'Type to search or add a new project…' : 'Select a region first';
    });
}

// The request-level Region changed — every project block's code preview depends on it (its RC
// segment), and the Project typeahead itself is filtered by it, so refresh both across every Program
// block (not just one — this field isn't scoped to a single block anymore).
function bgr_onRequestRegionChange() {
    document.querySelectorAll('#bgr-pblocks-wrap .bgr-pblock').forEach(bgr_refreshAllProjectCodeFields);
    bgr_syncProjectInputsRegionState();
}

async function bgr_loadTypesOfFund() {
    try {
        const res  = await fetch(`${BGR_TYPE_OF_FUND_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load types of fund.');
        BGR_TYPES_OF_FUND = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Looks up an option's id by its exact (case-insensitive) name — used to default the COE form's
// own Type of Fund/Component to this office's usual pick on a brand-new 'add-coe' request (see
// bgr_openModal). Returns '' if the name isn't found (e.g. it was renamed/removed server-side),
// which just leaves the field on its placeholder rather than failing outright.
function bgr_lookupIdByName(list, idField, name) {
    const match = list.find(x => (x.name ?? '').trim().toUpperCase() === name);
    return match ? String(match[idField]) : '';
}

// Fills one block's Type of Fund select — `keep` is the type_of_fund_id to preselect (editing an
// existing project's own line item; '' for a brand-new block).
function bgr_fillBlockTypeOfFund(selectEl, keep = '') {
    if (!selectEl) return;
    selectEl.innerHTML = '<option value="">— Select type of fund —</option>' + BGR_TYPES_OF_FUND.map(tf =>
        `<option value="${bgr_escHtmlAttr(tf.type_of_fund_id)}">${bgr_esc(tf.name)}</option>`
    ).join('');
    selectEl.value = BGR_TYPES_OF_FUND.some(tf => String(tf.type_of_fund_id) === String(keep)) ? String(keep) : '';
}

async function bgr_loadComponents() {
    try {
        const res  = await fetch(`${BGR_COMPONENT_TYPE_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load components.');
        BGR_COMPONENTS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Fills one block's Component select — optional, unlike Region/Type of Fund, so the placeholder
// doubles as a valid "none" choice. `keep` is the component_type_id to preselect (editing an existing
// project's own line item; '' for a brand-new block or one with none set). `excludeNames` drops
// entries that don't belong in THIS mode's own list (case-insensitive, compared against BGR_COMPONENTS'
// own name) — a regular CO Project's own Component excludes "COE" (that's a separate request type
// entirely — see #bgr-coe-root), and a COE request's own Component (#bgr-coe-component) excludes
// "CAPITAL OUTLAY"/"OTHER-CAPITAL OUTLAY" (those belong to a CO Project instead) — see both call sites.
function bgr_fillBlockComponents(selectEl, keep = '', excludeNames = []) {
    if (!selectEl) return;
    const options = BGR_COMPONENTS.filter(c => !excludeNames.includes((c.name ?? '').trim().toUpperCase()));
    selectEl.innerHTML = '<option value="">— None —</option>' + options.map(c =>
        `<option value="${bgr_escHtmlAttr(c.component_type_id)}">${bgr_esc(c.name)}</option>`
    ).join('');
    selectEl.value = options.some(c => String(c.component_type_id) === String(keep)) ? String(keep) : '';
}

async function bgr_loadSubComponents() {
    try {
        const res  = await fetch(`${BGR_SUB_COMPONENT_TYPE_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load sub-components.');
        BGR_SUB_COMPONENTS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Fills one block's Sub-Component select — optional, flat classification list. `keep` is the
// sub_component_type_id to preselect (editing an existing project's own line item; '' for a brand-new
// block or one with none set). `options` narrows WHICH entries are offered — defaults to the full
// list (COE's own Sub-Component block, #bgr-coe-subcomp-select, always gets this: it has no sibling
// Component field of its own to narrow by). A regular CO Project's own Sub-Component (paired with
// .bgr-block-component in the same block) instead passes a list fetched SERVER-SIDE, already narrowed
// to that Component's own bucket — see bgr_fetchSubComponentsForComponent()/bgr_onBlockComponentChange().
function bgr_fillBlockSubComponents(selectEl, keep = '', options = null) {
    if (!selectEl) return;
    const list = options ?? BGR_SUB_COMPONENTS;
    selectEl.innerHTML = '<option value="">— None —</option>' + list.map(c =>
        `<option value="${bgr_escHtmlAttr(c.sub_component_type_id)}">${bgr_esc(c.name)}</option>`
    ).join('');
    selectEl.value = list.some(c => String(c.sub_component_type_id) === String(keep)) ? String(keep) : '';
}

function bgr_componentNameById(componentId) {
    return BGR_COMPONENTS.find(c => String(c.component_type_id) === String(componentId))?.name ?? '';
}

// Component -> Sub-Component narrowing for a regular CO Project block's own pair, filtered SERVER-SIDE
// (?component= on /sub_component_type/all — see SubComponentTypeService::fetchAlls()'s own doc
// comment for the actual Other-Capital-Outlay/Capital-Outlay split) rather than against the cached
// BGR_SUB_COMPONENTS array — that cache stays reserved for COE's own Sub-Component block, which has
// no sibling Component field of its own to filter by (bgr_loadSubComponents, #bgr-coe-subcomp-select).
// '' (nothing picked yet) returns the full list too — same as every other unrecognized value.
async function bgr_fetchSubComponentsForComponent(componentName) {
    const params = new URLSearchParams();
    if (componentName) params.set('component', componentName);
    const res  = await fetch(`${BGR_SUB_COMPONENT_TYPE_API}/all?${params}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to load sub-components.');

    return json.data ?? [];
}

// A Project block's own Component changed — its paired Sub-Component (same block) narrows to match
// (see bgr_fetchSubComponentsForComponent); a previously-picked Sub-Component that's no longer offered
// under the new Component is cleared back to "— None —" automatically (bgr_fillBlockSubComponents'
// own `keep` guard).
async function bgr_onBlockComponentChange(selectEl) {
    const block = selectEl.closest('.bgr-block');
    const subSel = block?.querySelector('.bgr-block-sub-component');
    if (!subSel) return;
    const keep = subSel.value;
    try {
        const list = await bgr_fetchSubComponentsForComponent(bgr_componentNameById(selectEl.value));
        bgr_fillBlockSubComponents(subSel, keep, list);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

async function bgr_loadProgramSources() {
    try {
        const res  = await fetch(`${BGR_PROGRAM_SOURCE_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load program sources.');
        BGR_PROGRAM_SOURCES = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Type-to-search picker for Program (Source) — same typeahead.js widget bgr_initProjectTypeahead
// uses for Project, but filtered locally against the already-fully-loaded BGR_PROGRAM_SOURCES (a
// flat ~26-entry list, each a full sentence) rather than fetched per keystroke; there's no server
// endpoint call here at all. The visible input IS the search box; minLength:0 means focusing it
// lists every entry immediately, still filterable by typing. Picking a suggestion fills the paired
// hidden .bgr-block-program-source with its real program_source_id — optional, so clearing the text
// (or never picking one) just leaves that hidden field empty.
function bgr_initProgramSourceTypeahead($input) {
    $input.typeahead(
        { hint: false, highlight: true, minLength: 0 },
        {
            name: 'bgr-program-sources',
            display: 'name',
            limit: 50,
            source: (query, syncResults) => {
                const q = query.trim().toLowerCase();
                syncResults(q ? BGR_PROGRAM_SOURCES.filter(p => p.name.toLowerCase().includes(q)) : BGR_PROGRAM_SOURCES);
            },
            templates: {
                suggestion: item => `<div>${bgr_esc(item.name)}</div>`,
                notFound: '<div class="tt-not-found">No matching program source.</div>',
            },
        }
    );

    const $hidden = () => $input.closest('.bgr-block-program-source-wrap').find('.bgr-block-program-source');
    $input.on('typeahead:select', (ev, item) => {
        $input.data('bgr-syncing', true);
        $input.data('bgr-matched-text', item.name);
        $hidden().val(item.program_source_id);
        setTimeout(() => $input.removeData('bgr-syncing'), 0);
    });
    $input.on('input', () => {
        if ($input.data('bgr-syncing')) return;
        if ($input.val() !== $input.data('bgr-matched-text')) {
            $hidden().val('');
            $input.removeData('bgr-matched-text');
        }
    });
}

/* ── COE mode's own Sub-Component blocks (#bgr-coe-blocks-wrap) ────────────────────────────────
   "+ Add Sub-Component" appends another block — same nesting pattern "+ Add Program"/"+ Add
   Project" use below — each with its OWN Sub-Component pick and its OWN nested Line Items table
   (reusing bgr_addLineItemRow exactly as a regular Project block's COE-mode table does). A request
   needing both MOOE and Personnel Services line items just adds one block for each; a line item's
   sub_component_id is simply whatever its own block has picked — no per-row field, no deriving it
   from the account afterward. ──────────────────────────────────────────────────────────────────── */
function bgr_addCoeSubComponentBlock(prefill = null) {
    bgr_coeSubCompSeq++;
    const block = document.createElement('div');
    block.className = 'bgr-block';
    block.dataset.coeSubcompSeq = bgr_coeSubCompSeq;
    block.innerHTML = `
        <div class="bgr-coe-subcomp-hd">
            <div class="bgr-group">
                <label class="bgr-label">Sub-Component</label>
                <select class="bgr-select bgr-coe-subcomp-select"></select>
            </div>
            <button type="button" class="bgr-block-remove" title="Remove sub-component">&times;</button>
        </div>
        <div class="bgr-block-li-outer">
            <div class="bgr-block-li-wrap">
                <table class="bgr-block-li-tbl">
                    <thead><tr>
                        <th style="width:36%;">Account Code - Account Name</th>
                        <th style="width:44%;">Particulars</th>
                        <th style="width:19%;">Proposed Amount</th>
                    </tr></thead>
                    <tbody class="bgr-block-li-tbody"></tbody>
                    <tfoot><tr class="bgr-block-li-total-row">
                        <td colspan="2">Total</td>
                        <td class="bgr-block-li-total-amt">0.00</td>
                    </tr></tfoot>
                </table>
            </div>
            <div class="bgr-block-li-remove-col">
                <div class="bgr-li-remove-head"></div>
                <div class="bgr-li-remove-foot"></div>
            </div>
        </div>
        <button type="button" class="bgr-block-add-li bgr-btn-ghost">+ Add Line Item</button>
    `;
    $bgr('bgr-coe-blocks-wrap').appendChild(block);

    // COE's own Component is fixed ("COE" — see #bgr-coe-component, which excludes the other two —
    // so this always asks the backend for that bucket specifically: Personnel Services/MOOE, not the
    // full list (same SERVER-SIDE filter the regular CO flow's bgr_onBlockComponentChange() uses, see
    // SubComponentTypeService::fetchAlls()'s own doc comment for the actual three-way split). Not
    // awaited — this block (and its Line Items) render immediately; the select starts on its plain
    // "— None —" placeholder until this resolves.
    const subCompSel = block.querySelector('.bgr-coe-subcomp-select');
    const keepSubComp = prefill?.sub_component_id ?? '';
    bgr_fetchSubComponentsForComponent('COE')
        .then(list => bgr_fillBlockSubComponents(subCompSel, keepSubComp, list))
        .catch(e => bgr_toast(e.message, 'error'));
    block.querySelector('.bgr-block-remove').addEventListener('click', () => bgr_removeCoeSubComponentBlock(block));
    block.querySelector('.bgr-block-add-li').addEventListener('click', () => bgr_addLineItemRow(block, {}, true));

    const items = (prefill?.line_items?.length) ? prefill.line_items : [{}];
    items.forEach(li => bgr_addLineItemRow(block, li, true));

    bgr_updateCoeSubcompRemoveVisibility();
    bgr_updateGrandTotal();
    return block;
}

function bgr_removeCoeSubComponentBlock(block) {
    if ($bgr('bgr-coe-blocks-wrap').children.length <= 1) { bgr_toast('At least one Sub-Component block is required.', 'error'); return; }
    bgr_cleanupAccountMenus(block);
    block.remove();
    bgr_updateCoeSubcompRemoveVisibility();
    bgr_updateGrandTotal();
}

// Hides a lone Sub-Component block's own Remove button (nothing to remove down to zero) — shown
// once a second block exists, same precedent as bgr_updatePblockRemoveVisibility().
function bgr_updateCoeSubcompRemoveVisibility() {
    const blocks = $bgr('bgr-coe-blocks-wrap').children;
    Array.from(blocks).forEach(b => {
        const btn = b.querySelector('.bgr-block-remove');
        if (btn) btn.style.display = blocks.length > 1 ? '' : 'none';
    });
}

// 'edit-coe' groups the request's existing flat line items back into one block per DISTINCT
// sub_component_id they already carry (insertion order) — a blank-Sub-Component group included,
// if any items have none. [{}] (one blank block) when there are no existing items at all.
function bgr_groupCoeLineItemsBySubComponent(lineItems) {
    if (!lineItems.length) return [{}];
    const groups = [];
    const indexByKey = new Map();
    lineItems.forEach((li) => {
        const key = li.sub_component_id ?? '';
        if (!indexByKey.has(key)) {
            indexByKey.set(key, groups.length);
            groups.push({ sub_component_id: key, line_items: [] });
        }
        groups[indexByKey.get(key)].line_items.push(li);
    });
    return groups;
}

// The account-code prefix to offer within ONE Sub-Component block's own line items — the block's
// own pick, not a request-wide union (each block only ever carries one Sub-Component). 'PERSONNEL
// SERVICES' -> 5-01, 'MOOE' -> 5-02 (same prefixes as bgr_isCoeEligibleAccount()); any other
// Sub-Component (or none picked yet) returns null — no further narrowing, every PS/MOOE account
// stays offered.
function bgr_coeBlockAccountPrefix(block) {
    const subComponentId = block?.querySelector('.bgr-coe-subcomp-select')?.value;
    if (!subComponentId) return null;
    const sub = BGR_SUB_COMPONENTS.find(c => String(c.sub_component_type_id) === String(subComponentId));
    const name = (sub?.name ?? '').trim().toUpperCase();
    if (name === 'MOOE') return '5-02';
    if (name === 'PERSONNEL SERVICES') return '5-01';
    return null;
}

// Program -> Sub-Program cascade, same pattern as pof_fillProjects()/
// pof_onProgramChange() in app/pages/po/form.php: the full list is already
// cached, so this just filters it client-side into the dependent select.
// Scoped to ONE Program block's own pair of selects — each block has its own Program/Sub-Program now.
function bgr_fillSubPrograms(subSelectEl, programCode, keep = '') {
    const list = programCode ? BGR_SUB_PROGRAMS.filter(sp => sp.program_code === programCode) : [];
    subSelectEl.innerHTML = '<option value="">— None —</option>' + list.map(sp =>
        `<option value="${bgr_escHtmlAttr(sp.sub_program_code)}">${bgr_esc(sp.sub_program_code)} — ${bgr_esc(sp.sub_program_name)}</option>`
    ).join('');
    subSelectEl.value = list.some(sp => sp.sub_program_code === keep) ? keep : '';
}
function bgr_onProgramChange(pblock) {
    bgr_fillSubPrograms(pblock.querySelector('.bgr-pblock-sub-program'), pblock.querySelector('.bgr-pblock-program').value);
    // bgr_fillSubPrograms sets the Sub-Program select's value programmatically (rebuilt <option>
    // list), which doesn't fire that select's own 'change' listener — so this has to be called
    // explicitly here too.
    bgr_refreshAllProjectCodeFields(pblock);
}

async function bgr_loadFiscalYears() {
    try {
        const res  = await fetch(`${BGR_FISCAL_YEAR_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');

        const years = json.data ?? [];
        const optsHtml = years.map(fy =>
            `<option value="${bgr_escAttr(fy.fiscal_yr)}">${bgr_esc(fy.fiscal_yr)}</option>`
        ).join('');
        $bgr('bgr-fiscal-yr').insertAdjacentHTML('beforeend', optsHtml);

        // The filter already has the current year as a static, pre-selected
        // option (see budget-req-page.php) -- skip it here so the dropdown
        // doesn't end up with two identical entries.
        const currentYear = String(new Date().getFullYear());
        const filterOptsHtml = years
            .filter(fy => String(fy.fiscal_yr) !== currentYear)
            .map(fy => `<option value="${bgr_escAttr(fy.fiscal_yr)}">${bgr_esc(fy.fiscal_yr)}</option>`)
            .join('');
        $bgr('bgr-filter-fiscal-yr')?.insertAdjacentHTML('beforeend', filterOptsHtml);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// All active, postable, for-budget-proposal chart-of-accounts entries — no
// more expense-class-driven prefix filtering; every such account is
// selectable regardless of PS/MOOE/CO branch. Called when the modal opens
// (bgr_openModal), not at page load — this form's own fields are the only
// thing that needs it.
async function bgr_loadChartOfAccounts() {
    try {
        const res  = await fetch(`${BGR_CHART_OF_ACCOUNTS_API}?postable_only=1&for_budget_proposal=1`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load chart of accounts.');
        BGR_COA_OPTIONS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
        BGR_COA_OPTIONS = [];
    }
}

function bgr_accountLabel(a) {
    return `${a.account_code} — ${a.account_title}`;
}

// COE mode only (bgr_initAccountPicker) — chart_of_accounts carries no stored expense-class
// column, so PS/MOOE is derived from the account_code prefix, same rule
// DisbursementVoucherService::deriveExpenseClass() uses (also mirrored in
// readone/budget-req-readone.php for the COB view's PS/MOOE breakdown).
function bgr_isCoeEligibleAccount(accountCode) {
    const code = String(accountCode || '');
    return code.startsWith('5-01') || code.startsWith('5-02');
}


/* ── Program blocks — one per "Program block" (fims.budget_req_program), repeatable via
   "+ Add Program" ('add' mode only — 'edit-program' always has exactly one, with no add/remove
   affordance, since it edits a single existing Program and nothing more; see bgr_openModal). Each
   holds its own Program/Sub-Program pair and its own repeatable Project blocks. COE mode never
   creates one of these at all (it has no Program) — see #bgr-coe-root instead. ──────────────── */
let bgr_pblockSeq = 0;

function bgr_addProgramBlock(prefillProgram = null) {
    bgr_pblockSeq++;
    const pblock = document.createElement('div');
    pblock.className = 'bgr-pblock';
    pblock.dataset.pblockSeq = bgr_pblockSeq;
    pblock.innerHTML = `
        <div class="bgr-pblock-hd">
            <div class="bgr-pblock-fields">
                <div class="bgr-row">
                    <div class="bgr-group">
                        <label class="bgr-label">Program <span class="req">*</span></label>
                        <select class="bgr-select bgr-pblock-program" required></select>
                    </div>
                    <div class="bgr-group">
                        <label class="bgr-label">Sub-Program</label>
                        <select class="bgr-select bgr-pblock-sub-program"><option value="">— None —</option></select>
                    </div>
                </div>
            </div>
            <button type="button" class="bgr-pblock-remove" title="Remove program">&times;</button>
        </div>
        <p class="bgr-sec-hd">Projects</p>
        <div class="bgr-pblock-projects-wrap bgr-blocks-wrap"></div>
        <button type="button" class="bgr-pblock-add-project bgr-btn-ghost">+ Add Project</button>
    `;
    $bgr('bgr-pblocks-wrap').appendChild(pblock);

    const programSel = pblock.querySelector('.bgr-pblock-program');
    bgr_fillPblockPrograms(programSel, prefillProgram?.program_code ?? '');
    bgr_fillSubPrograms(pblock.querySelector('.bgr-pblock-sub-program'), programSel.value, prefillProgram?.sub_program_code ?? '');

    bgr_updatePblockRemoveVisibility();

    const projectsWrap = pblock.querySelector('.bgr-pblock-projects-wrap');
    const projects = (prefillProgram && prefillProgram.projects && prefillProgram.projects.length) ? prefillProgram.projects : [{}];
    projects.forEach(p => bgr_addProjectBlock(projectsWrap, p, false));

    return pblock;
}

function bgr_removeProgramBlock(pblock) {
    if ($bgr('bgr-pblocks-wrap').children.length <= 1) { bgr_toast('At least one program is required.', 'error'); return; }
    bgr_cleanupAccountMenus(pblock);
    pblock.remove();
    bgr_updatePblockRemoveVisibility();
    bgr_updateGrandTotal();
}

// Hides a lone Program block's own Remove button (nothing to remove down to zero) — shown once a
// second block exists. 'edit-program' mode's single block stays hidden forever (it never gets a
// sibling — see bgr_openModal), exactly like before this feature existed.
function bgr_updatePblockRemoveVisibility() {
    const pblocks = document.querySelectorAll('#bgr-pblocks-wrap .bgr-pblock');
    pblocks.forEach(pb => {
        const btn = pb.querySelector('.bgr-pblock-remove');
        if (btn) btn.style.display = pblocks.length > 1 ? '' : 'none';
    });
}

/* ── Project blocks — one per "Project line item" (fims.budget_req_project) ────────────────────
   The regular Program/Project flow simplifies a Project down to ONE Proposed Amount, posted at save
   time as a single budget_line_items row against a fixed account (chart-of-accounts id 214, "Work-
   in-Process Inventory" — see bgr_save()). Its own Region (fims.project.rc_id) is no longer picked
   per block — the whole request now shares the ONE Region picked at #bgr-region (see
   bgr_buildProgramPayload) — so a brand-new project here simply inherits it. `wrapEl` is the
   container this block is appended to — a Program block's own .bgr-pblock-projects-wrap. COE mode
   has no Project at all — see #bgr-coe-blocks-wrap/bgr_addCoeSubComponentBlock() instead, which
   keeps the flexible chart-of-accounts line-items table this used to also build in coeMode. */
function bgr_addProjectBlock(wrapEl, prefillBlock = null) {
    bgr_blockSeq++;
    const block = document.createElement('div');
    block.className = 'bgr-block';
    block.dataset.blockSeq = bgr_blockSeq;

    block.innerHTML = `
        <div class="bgr-block-hd">
            <button type="button" class="bgr-block-toggle" title="Collapse">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div class="bgr-block-project-wrap">
                <label class="bgr-label">Project <span class="req">*</span></label>
                <input type="text" class="bgr-input bgr-block-project-name" placeholder="Type to search or add a new project…" autocomplete="off">
                <input type="hidden" class="bgr-block-project-code" value="">
            </div>
            <div class="bgr-block-project-code-wrap">
                <label class="bgr-label">Project Code</label>
                <input type="text" class="bgr-input bgr-block-project-code-display" readonly placeholder="Select a program first">
            </div>
            <div class="bgr-block-location-wrap">
                <label class="bgr-label">Location</label>
                <input type="text" class="bgr-input bgr-block-location" placeholder="e.g. Brgy. San Jose, Quezon City" maxlength="255">
            </div>
            <button type="button" class="bgr-block-remove" title="Remove project">&times;</button>
        </div>
        <div class="bgr-block-body">
            <div class="bgr-row-6">
                <div class="bgr-group">
                    <label class="bgr-label">Type of Fund <span class="req">*</span></label>
                    <select class="bgr-select bgr-block-type-of-fund"><option value="">— Select type of fund —</option></select>
                </div>
                <div class="bgr-group">
                    <label class="bgr-label">Component</label>
                    <select class="bgr-select bgr-block-component"><option value="">— None —</option></select>
                </div>
                <div class="bgr-group">
                    <label class="bgr-label">Sub-Component</label>
                    <select class="bgr-select bgr-block-sub-component"><option value="">— None —</option></select>
                </div>
                <!-- Fixed 3-option list, plain text (fims.budget_line_items.source_of_fund) — no lookup
                     table/loader, unlike Type of Fund/Component/Sub-Component above (see
                     BudgetLineItemService::SOURCE_OF_FUND_OPTIONS). -->
                <div class="bgr-group">
                    <label class="bgr-label">Source of Fund <span class="req">*</span></label>
                    <select class="bgr-select bgr-block-source-of-fund">
                        <option value="">— Select source of fund —</option>
                        <option value="BEGINNING BALANCE">Beginning Balance</option>
                        <option value="NEW FUNDS">New Funds</option>
                        <option value="ACCOUNTS PAYABLE">Accounts Payable</option>
                    </select>
                </div>
                <!-- Flat list (fims.program_source — specific named source programs/projects), optional,
                     independent of the header's own Program/Sub-Program. A plain <select> here listed
                     all 26+ entries (full sentences) with no search — a type-to-search typeahead
                     instead, same widget Project uses (bgr_initProjectTypeahead), but filtered locally
                     against BGR_PROGRAM_SOURCES (already fully loaded at bootstrap — see
                     bgr_loadProgramSources) rather than fetched per keystroke. -->
                <div class="bgr-group bgr-block-program-source-wrap">
                    <label class="bgr-label">Program Source</label>
                    <input type="text" class="bgr-input bgr-block-program-source-name" placeholder="Type to search…" autocomplete="off">
                    <input type="hidden" class="bgr-block-program-source" value="">
                </div>
                <div class="bgr-group">
                    <label class="bgr-label">Proposed Amount <span class="req">*</span></label>
                    <div class="bgr-amt-wrap">
                        <span class="bgr-amt-prefix">₱</span>
                        <input type="text" inputmode="decimal" class="bgr-input bgr-block-amount" placeholder="0.00" oninput="bgr_fmtAmount(this);bgr_updateGrandTotal();">
                    </div>
                </div>
            </div>

            <div class="bgr-block-docs">
                <p class="bgr-block-docs-title">Supporting Documents</p>
                <p class="bgr-block-docs-hint">Upload a file (≤20 MB — PDF, Word, Excel, or image) or paste a link per document.</p>
                <table class="bgr-block-doc-tbl" style="display:none;">
                    <thead><tr>
                        <th style="width:35%;">Document Name</th>
                        <th>Attachment</th>
                        <th style="width:26px;"></th>
                    </tr></thead>
                    <tbody class="bgr-block-doc-tbody"></tbody>
                </table>
                <div class="bgr-block-doc-actions">
                    <button type="button" class="bgr-block-doc-add-upload bgr-btn-ghost">+ Upload File</button>
                    <button type="button" class="bgr-block-doc-add-link bgr-btn-ghost">+ Paste Link</button>
                </div>
            </div>
        </div>
    `;
    wrapEl.appendChild(block);

    const $nameInput = $(block.querySelector('.bgr-block-project-name'));
    bgr_initProjectTypeahead($nameInput);

    // Editing an existing project under the old multi-account model: there is no longer a single
    // "the" Type of Fund/Component once it had more than one line item — the first one's own is
    // as good a starting point as any (saving replaces them all with the one simplified line item
    // anyway).
    const existingTypeOfFund = (prefillBlock?.line_items ?? []).find(li => li.type_of_fund_id)?.type_of_fund_id ?? '';
    bgr_fillBlockTypeOfFund(block.querySelector('.bgr-block-type-of-fund'), existingTypeOfFund);
    const componentSel = block.querySelector('.bgr-block-component');
    const existingComponent = (prefillBlock?.line_items ?? []).find(li => li.component_id)?.component_id ?? '';
    bgr_fillBlockComponents(componentSel, existingComponent, ['COE']);
    const existingSubComponent = (prefillBlock?.line_items ?? []).find(li => li.sub_component_id)?.sub_component_id ?? '';
    // componentSel.value (not existingComponent) — bgr_fillBlockComponents may have reset it to '' if
    // existingComponent wasn't actually offered (e.g. stale "COE" data on an old CO row). Fetched from
    // the backend (not awaited — bgr_addProjectBlock stays synchronous, same "briefly empty until it
    // resolves" tradeoff the Project Code preview already makes elsewhere in this form), so the select
    // starts on its plain "— None —" placeholder until this resolves.
    bgr_fetchSubComponentsForComponent(bgr_componentNameById(componentSel.value))
        .then(list => bgr_fillBlockSubComponents(block.querySelector('.bgr-block-sub-component'), existingSubComponent, list))
        .catch(e => bgr_toast(e.message, 'error'));
    // Source of Fund is a fixed, static <option> list (no loader to call) — just preselect it directly.
    const existingSourceOfFund = (prefillBlock?.line_items ?? []).find(li => li.source_of_fund)?.source_of_fund ?? '';
    const sourceOfFundSel = block.querySelector('.bgr-block-source-of-fund');
    if (existingSourceOfFund) sourceOfFundSel.value = existingSourceOfFund;
    const $programSourceInput = $(block.querySelector('.bgr-block-program-source-name'));
    bgr_initProgramSourceTypeahead($programSourceInput);
    const existingProgramSourceId = (prefillBlock?.line_items ?? []).find(li => li.program_source_id)?.program_source_id ?? '';
    const existingProgramSource = BGR_PROGRAM_SOURCES.find(p => String(p.program_source_id) === String(existingProgramSourceId));
    if (existingProgramSource) {
        $programSourceInput.val(existingProgramSource.name).data('bgr-matched-text', existingProgramSource.name);
        block.querySelector('.bgr-block-program-source').value = existingProgramSource.program_source_id;
    }

    if (prefillBlock && prefillBlock.project_code) {
        const label = `${prefillBlock.project_code} — ${prefillBlock.project_name || ''}`;
        $nameInput.val(label).data('bgr-matched-text', label);
        bgr_setProjectCode($nameInput, prefillBlock.project_code);
    } else {
        // A brand-new block with nothing picked yet already WOULD create a
        // new project if saved as-is — preview its code immediately, rather
        // than waiting for the user to type something first (the code never
        // depends on the typed name anyway, only on the header's Program/
        // Sub-Program).
        bgr_updateProjectCodeField($nameInput);
    }
    if (prefillBlock && prefillBlock.location) {
        block.querySelector('.bgr-block-location').value = prefillBlock.location;
    }
    // A brand-new project block starts at zero — editing an existing one sums whatever line
    // items it already has under the old multi-account model (collapsing them into the one
    // figure this simplified field shows; saving replaces them with a single line item against
    // account 214, see bgr_save()).
    const amount = (prefillBlock?.line_items ?? []).reduce((sum, li) => sum + (Number(li.proposed_amount) || 0), 0);
    if (amount) block.querySelector('.bgr-block-amount').value = bgr_fmtAmountInitial(amount);

    block.querySelector('.bgr-block-remove').addEventListener('click', () => bgr_removeProjectBlock(block));
    block.querySelector('.bgr-block-toggle').addEventListener('click', () => bgr_toggleProjectBlock(block));

    bgr_wireDocSection(block, prefillBlock);

    bgr_syncProjectInputsRegionState(); // this new block's own Project field needs its disabled state set too
    bgr_updateGrandTotal();
    return block;
}

/* ── Supporting Documents — per project block, staged client-side until the
   whole request is saved (see bgr_save()'s FormData path), except an
   already-saved attachment's own delete, which is immediate (a real,
   independent server action, not part of the "nothing persists until Save"
   staging the rest of this form uses). ──────────────────────────────────── */
const BGR_DOC_MAX_BYTES = 20 * 1024 * 1024; // 20 MB — keep in sync with BudgetReqService::ATTACHMENT_MAX_BYTES
const BGR_DOC_ALLOWED_EXT = new Set(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']);
let bgr_docRowSeq = 0;

function bgr_wireDocSection(block, prefillBlock) {
    block.querySelector('.bgr-block-doc-add-upload').addEventListener('click', () => bgr_addDocRow(block, { mode: 'upload' }));
    block.querySelector('.bgr-block-doc-add-link').addEventListener('click', () => bgr_addDocRow(block, { mode: 'link' }));

    // Existing attachments only exist for a project already saved under an
    // existing program (edit mode) — a brand-new block/program has none yet.
    const budgetReqProgramId = $bgr('bgr-id').value;
    const projectCode = prefillBlock && prefillBlock.project_code;
    if (!budgetReqProgramId || !projectCode) return;

    const params = new URLSearchParams({ budget_req_program_id: budgetReqProgramId, project_code: projectCode });
    fetch(`${BGR_ATTACHMENT_API}/list?${params}`, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(json => {
            if (!json.success) return;
            (json.data || []).forEach(a => bgr_addDocRow(block, {
                mode: a.storage_key ? 'upload' : 'link',
                name: a.document_name,
                attachmentId: a.attachment_id,
                viewUrl: a.view_url,
                link: a.external_link || '',
            }));
        })
        .catch(() => {});
}

// COE's own Supporting Documents (#bgr-coe-docs) are request-level (keyed by budget_reference_number
// alone — there is no Program/Project/Sub-Component to key off) and, unlike a regular Project
// block's own per-project docs section, are NOT rebuilt from scratch on every bgr_openModal (the
// Sub-Component blocks around it are, but this section is static markup, shared by every one of
// them). So its upload/link buttons are bound exactly ONCE, at bootstrap — binding them again on
// every modal open would stack duplicate click listeners on the same persistent elements. Only the
// document ROWS themselves need to reset per open, via bgr_resetCoeDocSection() below.
function bgr_bindCoeDocSection() {
    const block = $bgr('bgr-coe-docs');
    if (!block) return;
    block.querySelector('.bgr-block-doc-add-upload').addEventListener('click', () => bgr_addDocRow(block, { mode: 'upload' }));
    block.querySelector('.bgr-block-doc-add-link').addEventListener('click', () => bgr_addDocRow(block, { mode: 'link' }));
}

// Clears whatever rows a previous modal session left behind and, given an existing request's own
// budget_reference_number ('edit-coe'), reloads its already-saved attachments. 'add-coe' passes ''
// — a brand-new request has none yet.
function bgr_resetCoeDocSection(budgetReferenceNumber) {
    const block = $bgr('bgr-coe-docs');
    block.querySelector('.bgr-block-doc-tbody').innerHTML = '';
    bgr_syncDocTableVisibility(block);
    if (!budgetReferenceNumber) return;

    const params = new URLSearchParams({ budget_reference_number: budgetReferenceNumber });
    fetch(`${BGR_ATTACHMENT_API}/list?${params}`, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(json => {
            if (!json.success) return;
            (json.data || []).forEach(a => bgr_addDocRow(block, {
                mode: a.storage_key ? 'upload' : 'link',
                name: a.document_name,
                attachmentId: a.attachment_id,
                viewUrl: a.view_url,
                link: a.external_link || '',
            }));
        })
        .catch(() => {});
}

function bgr_syncDocTableVisibility(block) {
    const tbl = block.querySelector('.bgr-block-doc-tbl');
    tbl.style.display = tbl.querySelector('.bgr-block-doc-tbody tr') ? '' : 'none';
}

function bgr_docUploadPanelHtml(fileId, label, filename, viewUrl) {
    return `<div class="bgr-doc-panel">
        <input type="file" id="${fileId}" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" style="display:none;">
        <button type="button" class="bgr-doc-upload-btn">${bgr_esc(label)}</button>
        <span class="bgr-doc-uploaded-name">${bgr_esc(filename || '')}</span>
        ${viewUrl ? `<a href="${bgr_escAttr(viewUrl)}" target="_blank" rel="noopener" class="bgr-doc-open">Open ↗</a>` : ''}
    </div>`;
}

function bgr_docLinkPanelHtml(value, viewUrl) {
    return `<div class="bgr-doc-panel">
        <input type="text" class="bgr-input bgr-doc-link" value="${bgr_escAttr(value || '')}" placeholder="https://drive.google.com/…" style="font-size:12px;padding:6px 9px;">
        ${viewUrl ? `<a href="${bgr_escAttr(viewUrl)}" target="_blank" rel="noopener" class="bgr-doc-open">Open ↗</a>` : ''}
    </div>`;
}

// opts: { mode: 'upload'|'link', name?, attachmentId?, viewUrl?, link? }
function bgr_addDocRow(block, opts) {
    const tbody = block.querySelector('.bgr-block-doc-tbody');
    const rowId = 'bgr-doc-row-' + (++bgr_docRowSeq);
    const fileId = rowId + '-file';
    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.dataset.mode = opts.mode;
    if (opts.attachmentId) tr.dataset.attachmentId = opts.attachmentId;

    const contentHtml = opts.mode === 'link'
        ? bgr_docLinkPanelHtml(opts.link, opts.viewUrl)
        : bgr_docUploadPanelHtml(fileId, opts.attachmentId ? opts.name : 'Choose File', opts.attachmentId ? opts.name : '', opts.viewUrl);

    tr.innerHTML = `
        <td><input type="text" class="bgr-input bgr-doc-name" value="${bgr_escAttr(opts.name || '')}" placeholder="Document name" style="font-size:12.5px;padding:6px 9px;"></td>
        <td>${contentHtml}</td>
        <td><button type="button" class="bgr-doc-remove" title="Remove document">&times;</button></td>`;
    tbody.appendChild(tr);
    bgr_syncDocTableVisibility(block);

    if (opts.mode === 'upload' && !opts.attachmentId) {
        const fileInput = tr.querySelector(`#${fileId}`);
        const uploadBtn = tr.querySelector('.bgr-doc-upload-btn');
        uploadBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => bgr_handleDocFileSelect(fileInput, tr));
    } else if (opts.mode === 'link') {
        tr.querySelector('.bgr-doc-link').addEventListener('input', (ev) => bgr_onDocLinkInput(ev.target));
    }

    tr.querySelector('.bgr-doc-remove').addEventListener('click', () => bgr_removeDocRow(tr, block));

    return tr;
}

function bgr_handleDocFileSelect(input, row) {
    const file = input.files[0];
    if (!file) return;

    const ext = file.name.split('.').pop().toLowerCase();
    if (!BGR_DOC_ALLOWED_EXT.has(ext)) {
        bgr_toast(`File type not allowed. Accepted: ${[...BGR_DOC_ALLOWED_EXT].join(', ')}`, 'error');
        input.value = '';
        return;
    }
    if (file.size > BGR_DOC_MAX_BYTES) {
        bgr_toast(`File too large — max is ${BGR_DOC_MAX_BYTES / 1024 / 1024} MB.`, 'error');
        input.value = '';
        return;
    }

    row._fileObj = file;
    const nameInput = row.querySelector('.bgr-doc-name');
    if (nameInput && !nameInput.value.trim()) nameInput.value = file.name.replace(/\.[^.]+$/, '');
    row.querySelector('.bgr-doc-uploaded-name').textContent = file.name;
    row.querySelector('.bgr-doc-upload-btn').textContent = 'Re-select';
}

function bgr_onDocLinkInput(input) {
    const row = input.closest('tr');
    const panel = input.closest('.bgr-doc-panel');
    let anchor = panel.querySelector('a.bgr-doc-open');
    const link = input.value.trim();
    if (link) {
        if (!anchor) {
            anchor = document.createElement('a');
            anchor.className = 'bgr-doc-open';
            anchor.target = '_blank';
            anchor.rel = 'noopener';
            anchor.textContent = 'Open ↗';
            panel.appendChild(anchor);
        }
        anchor.href = link;
    } else if (anchor) {
        anchor.remove();
    }
}

async function bgr_removeDocRow(row, block) {
    const attachmentId = row.dataset.attachmentId;
    if (attachmentId) {
        if (!confirm('Delete this document now? This cannot be undone.')) return;
        try {
            const res = await fetch(`${BGR_ATTACHMENT_API}/delete/${attachmentId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to delete document.');
            bgr_toast('Document deleted successfully.', 'success');
        } catch (e) {
            bgr_toast(e.message, 'error');
            return;
        }
    }
    row.remove();
    bgr_syncDocTableVisibility(block);
}

// Builds this block's supporting_documents entries, pushing any staged File
// objects onto the shared, request-wide fileList (one flat doc_file_N index
// across every project block — see bgr_save()).
function bgr_getProjectDocPayload(block, fileList) {
    const docs = [];
    for (const row of block.querySelectorAll('.bgr-block-doc-tbody tr')) {
        const name = row.querySelector('.bgr-doc-name')?.value.trim() || '';
        const attachmentId = row.dataset.attachmentId;
        if (attachmentId) {
            docs.push({ attachment_id: Number(attachmentId), document_name: name });
        } else if (row.dataset.mode === 'upload' && row._fileObj) {
            docs.push({ file_index: fileList.length, document_name: name });
            fileList.push(row._fileObj);
        } else if (row.dataset.mode === 'link') {
            const link = row.querySelector('.bgr-doc-link')?.value.trim() || '';
            if (link) docs.push({ link, document_name: name });
        }
    }
    return docs;
}

function bgr_removeProjectBlock(block) {
    const wrapEl = block.parentElement;
    if (wrapEl.children.length <= 1) { bgr_toast('At least one project is required.', 'error'); return; }
    const pblock = block.closest('.bgr-pblock');
    bgr_cleanupAccountMenus(block);
    block.remove();
    // Frees up the series slot this block may have reserved (see
    // bgr_setProjectCode) for whichever other "new project" blocks remain
    // under the SAME Program block.
    bgr_refreshAllProjectCodeFields(pblock);
    bgr_updateGrandTotal();
}

// Collapses a block down to just its header (project name field), hiding the
// chart-of-accounts table — for minimizing projects you're not actively
// editing when a request has several.
function bgr_toggleProjectBlock(block) {
    const collapsed = block.classList.toggle('bgr-collapsed');
    block.querySelector('.bgr-block-toggle').title = collapsed ? 'Expand' : 'Collapse';
}

// Type-directly-to-search project picker (twitter typeahead.js, already
// bundled as jquery/typeahead.jquery.min.js — no extra library needed). The
// visible input IS the search box; minLength:0 means focusing/clicking the
// field opens the dropdown immediately (listing all matching projects),
// and typing still filters that same list live (filtered by the header's
// Program/Sub-Program). Picking a suggestion fills the paired hidden
// .bgr-block-project-code field with its real project_code. Typing a name
// with no match leaves that hidden field empty — bgr_save() then sends the
// typed text as project_name so the backend resolves/creates it atomically
// when the whole request is saved (never the moment the user types).
function bgr_setProjectCode($input, code) {
    const hidden      = $input.closest('.bgr-block-project-wrap').find('.bgr-block-project-code');
    const wasResolved = !!hidden.val();
    hidden.val(code);
    const pblock = $input.closest('.bgr-pblock')[0];

    // Only an "existing project" <-> "new project" transition changes which
    // series slot this block reserves from its Program block's own code cache
    // (see bgr_updateProjectCodeField) -- that's when every other "new
    // project" block UNDER THE SAME PROGRAM needs its preview refreshed too, so
    // none of them keep holding a now-stale series number. Plain typing within an
    // already-unresolved block (code staying '' keystroke to keystroke)
    // only needs this one field re-checked.
    if (wasResolved !== !!code) {
        bgr_refreshAllProjectCodeFields(pblock);
    } else {
        bgr_updateProjectCodeField($input);
    }
}

// Seeded from the first real /project/next-code lookup under one (Program, Sub-Program, Region,
// Fiscal Year) combination, then handed out locally (prefix + nextSeries++) to every subsequent "new
// project" block that shares that SAME combination — the database would otherwise keep reporting the
// exact same "next available" series to each of them, since none of them are actually saved yet. The
// code's own RC segment is the request-level Region (#bgr-region) — the SAME one for every Program/
// Project in this request now (see GlobalService::generateProjectCode()'s own doc comment), so in
// practice every block under one Program/Sub-Program shares one cache entry; it's still keyed by the
// full combination (not just Program/Sub-Program) because Region/Fiscal Year can still change the
// code's own prefix. Stale entries (a changed Program/Sub-Program/Region/Fiscal Year) are simply
// never looked up again — a fresh Map each time the modal opens (see bgr_openModal, which rebuilds
// every block) means they never accumulate across sessions.
const BGR_PROJECT_CODE_CACHE = new Map(); // "program|subprogram|rc_id|fiscal_yr" -> { prefix: string, nextSeries: number }
function bgr_projectCodeCacheKey(programCode, subProgramCode, rcId, fiscalYr) {
    return `${programCode}|${subProgramCode}|${rcId}|${fiscalYr}`;
}

// Fills the read-only "Project Code" field alongside Project: either the
// matched existing project's real code, or a preview of what
// GlobalService::generateProjectCode() would assign if this block ends up
// creating a new one (no existing project resolved) — pure UI; the real
// code (possibly different, if another project was created in the
// meantime) is resolved by the backend only when the whole request is
// actually saved. Shown as soon as the block has no resolved project AND the
// request-level Region has been picked (the code's own RC segment needs it) —
// the preview depends on the enclosing Program block's own Program/
// Sub-Program plus #bgr-region, never on the typed name.
// Fetched once per "new project" state per that combination, not on every
// keystroke — see bgr_refreshAllProjectCodeFields()/bgr_onRequestRegionChange()
// for what clears that so a change re-fetches.
function bgr_updateProjectCodeField($input) {
    // .bgr-block-project-code-display is a sibling of .bgr-block-project-wrap
    // (its own column), not a descendant of it — scope the lookup to the
    // whole block, not just the Project field's own wrap.
    const block   = $input.closest('.bgr-block');
    const wrap    = $input.closest('.bgr-block-project-wrap');
    const display = block.find('.bgr-block-project-code-display');
    const code    = wrap.find('.bgr-block-project-code').val();

    // Matched an existing project — it already has its own code, nothing to preview.
    if (code) {
        display.val(code).removeData('bgr-fetched');
        return;
    }

    const pblock = $input.closest('.bgr-pblock')[0];
    const programCode = pblock?.querySelector('.bgr-pblock-program')?.value || '';
    if (!programCode) {
        display.val('').attr('placeholder', 'Select a program first').removeData('bgr-fetched');
        return; // not marked "fetched" — tries again once a Program is picked
    }
    const rcId = $bgr('bgr-region').value || '';
    if (!rcId) {
        display.val('').attr('placeholder', 'Select a region first').removeData('bgr-fetched');
        return; // not marked "fetched" — tries again once a Region is picked
    }
    if (display.data('bgr-fetched')) return;
    display.data('bgr-fetched', true);

    const subProgramCode = pblock.querySelector('.bgr-pblock-sub-program').value;
    const fiscalYr = $bgr('bgr-fiscal-yr').value;
    const cacheKey = bgr_projectCodeCacheKey(programCode, subProgramCode, rcId, fiscalYr);

    // Already seeded by an earlier "new project" block sharing this same Program/Sub-Program/Region/
    // Fiscal Year this session — hand out the next series locally instead of re-querying, which would
    // just return the same number the DB still shows (nothing's saved yet).
    const cached = BGR_PROJECT_CODE_CACHE.get(cacheKey);
    if (cached) {
        cached.nextSeries += 1;
        display.val(cached.prefix + String(cached.nextSeries).padStart(4, '0'));
        return;
    }

    display.val('').attr('placeholder', 'Generating…');
    const params = new URLSearchParams({ program_code: programCode, sub_program_code: subProgramCode, fiscal_yr: fiscalYr, rc_id: rcId });
    fetch(`${BGR_PROJECT_BASE_API}/next-code?${params}`, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(json => {
            if (!json.success) throw new Error(json.message || 'Could not generate a project code.');
            const fullCode = json.data.project_code;
            const m = fullCode.match(/^(.*-)(\d{4})$/);
            if (m) {
                BGR_PROJECT_CODE_CACHE.set(cacheKey, { prefix: m[1], nextSeries: parseInt(m[2], 10) });
            }
            display.val(fullCode);
        })
        .catch(() => display.attr('placeholder', 'Code unavailable'));
}

// Re-previews every project block's code UNDER ONE Program block — needed because the preview
// depends on that Program block's own Program/Sub-Program, which can change AFTER a project block
// was created (including the very first one: 'add' mode resets Program to blank before creating it)
// without that project block's own Project field ever being touched again. Clears the "already
// fetched" guard on each — a new Program/Sub-Program means a new cache key (see
// bgr_projectCodeCacheKey), so the old cached prefix/series simply stops being looked up, nothing to
// explicitly invalidate.
function bgr_refreshAllProjectCodeFields(pblock) {
    if (!pblock) return;
    pblock.querySelectorAll('.bgr-block-project-code-display').forEach(el => {
        $(el).removeData('bgr-fetched');
    });
    pblock.querySelectorAll('.bgr-block-project-name').forEach(el => {
        bgr_updateProjectCodeField($(el));
    });
}

// Every project_code already picked by another block ANYWHERE in this request (any Program block,
// not just this project's own) — a project can only appear once per budget request, so these are
// excluded from $input's own suggestions and re-checked on select as a safety net against a
// stale/in-flight async result that was built before the latest pick.
function bgr_usedProjectCodes($input) {
    const ownBlock = $input.closest('.bgr-block')[0];

    return Array.from(document.querySelectorAll('#bgr-pblocks-wrap .bgr-block-project-code'))
        .filter(el => el.closest('.bgr-block') !== ownBlock && el.value)
        .map(el => el.value);
}

function bgr_initProjectTypeahead($input) {
    $input.typeahead(
        { hint: false, highlight: true, minLength: 0 },
        {
            name: 'bgr-projects',
            display: 'label',
            limit: 8,
            source: (query, syncResults, asyncResults) => {
                clearTimeout($input.data('bgr-ta-timer'));
                $input.data('bgr-ta-timer', setTimeout(async () => {
                    try {
                        const params = new URLSearchParams({
                            search: query,
                            program_code: $input.closest('.bgr-pblock').find('.bgr-pblock-program').val(),
                            sub_program_code: $input.closest('.bgr-pblock').find('.bgr-pblock-sub-program').val(),
                            // The request-level Region (#bgr-region) — a project from any other region is
                            // filtered out server-side (ProjectService::fetchAlls), not just hidden here.
                            rc_id: $bgr('bgr-region').value || '',
                        });
                        const res  = await fetch(`${BGR_PROJECT_BASE_API}/all?${params}`, { credentials: 'same-origin' });
                        const json = await res.json();
                        const used = bgr_usedProjectCodes($input);
                        asyncResults((json.data ?? [])
                            .filter(p => !used.includes(p.project_code))
                            .map(p => ({
                                project_code: p.project_code,
                                label: `${p.project_code} — ${p.project_name}`,
                            })));
                    } catch (e) {
                        asyncResults([]);
                    }
                }, 250));
            },
            templates: {
                suggestion: item => `<div>${bgr_esc(item.label)}</div>`,
                notFound: '<div class="tt-not-found">No match — this will be added as a new project.</div>',
            },
        }
    );

    // Selecting a suggestion resolves the real project_code. Any later edit
    // that moves the text away from that exact match invalidates it, falling
    // back to "typed name, not yet a real project" — guarded by bgr-syncing
    // so the library's own value-set on selection doesn't immediately
    // re-trigger this and clear the code it just set.
    $input.on('typeahead:select', (ev, item) => {
        if (bgr_usedProjectCodes($input).includes(item.project_code)) {
            bgr_toast('That project is already added to this budget request.', 'error');
            $input.typeahead('val', '');
            bgr_setProjectCode($input, '');
            return;
        }
        $input.data('bgr-syncing', true);
        $input.data('bgr-matched-text', item.label);
        bgr_setProjectCode($input, item.project_code);
        setTimeout(() => $input.removeData('bgr-syncing'), 0);
    });
    $input.on('input', () => {
        if ($input.data('bgr-syncing')) return;
        if ($input.val() !== $input.data('bgr-matched-text')) {
            bgr_setProjectCode($input, '');
        }
    });
}

// The suggestion menu (including the "No match" message, which the library
// treats as non-empty content and keeps visible) doesn't reliably close
// itself on blur alone — a debounced async result can still land and
// re-render it just after focus moved elsewhere. Rather than guess at that
// timing, force-close every project field's menu the moment focus genuinely
// lands somewhere outside it — a different field in the same block, another
// block entirely, or outside the modal. Registered once, globally (not
// per-block), since it has to see every focus change in the document.
// This only catches KEYBOARD-driven focus changes (e.g. Tab) — clicking on
// a plain, non-focusable part of the page (empty space, a label, the
// backdrop) doesn't move focus anywhere, so 'focusin' never fires there.
// The 'mousedown' listener below (which fires on literally any click)
// handles that case.
// Project (.bgr-block-project-wrap) and Program Source (.bgr-block-program-source-wrap) both use
// this same typeahead.js widget and the same "close on focus/click elsewhere" behavior.
const BGR_TYPEAHEAD_FIELDS = [
    ['.bgr-block-project-name', '.bgr-block-project-wrap'],
    ['.bgr-block-program-source-name', '.bgr-block-program-source-wrap'],
];
function bgr_closeOtherTypeaheads(ev) {
    BGR_TYPEAHEAD_FIELDS.forEach(([fieldSel, wrapSel]) => {
        document.querySelectorAll(`#bgr-pblocks-wrap ${fieldSel}`).forEach((el) => {
            if (!el.closest(wrapSel).contains(ev.target)) {
                $(el).typeahead('close');
            }
        });
    });
}
document.addEventListener('focusin', bgr_closeOtherTypeaheads);
document.addEventListener('mousedown', bgr_closeOtherTypeaheads);

/* ── Line items — repeatable rows nested inside a project block ─────── */
function bgr_addLineItemRow(blockEl, prefill = {}, coeMode = false) {
    bgr_liSeq++;
    const seq = bgr_liSeq;
    const tr = document.createElement('tr');
    tr.dataset.seq = seq;
    tr.innerHTML = `
        <td>
            <div class="bgr-li-account-wrap">
                <input type="text" class="bgr-li-input bgr-li-account-code" placeholder="Type to search…" autocomplete="off">
                <input type="hidden" class="bgr-li-account-id" value="${bgr_escHtmlAttr(prefill.account_id)}">
            </div>
        </td>
        <td><textarea class="bgr-li-input bgr-li-particulars" placeholder="Particulars" maxlength="2000" rows="1">${bgr_esc(prefill.particulars)}</textarea></td>
        <td><input type="text" inputmode="decimal" class="bgr-li-input bgr-li-amt" value="${bgr_fmtAmountInitial(prefill.proposed_amount ?? prefill.amount)}" placeholder="0.00" oninput="bgr_fmtAmount(this)"></td>
    `;
    blockEl.querySelector('.bgr-block-li-tbody').appendChild(tr);

    const $acctInput = $(tr.querySelector('.bgr-li-account-code'));
    bgr_initAccountPicker($acctInput, coeMode);
    if (prefill.account_id != null && prefill.account_id !== '') {
        const opt = BGR_COA_OPTIONS.find(a => String(a.account_id) === String(prefill.account_id));
        const label = opt ? bgr_accountLabel(opt) : '';
        if (label) $acctInput.val(label).data('bgr-matched-text', label);
    }

    // Remove button lives in the separate .bgr-block-li-remove-col sibling,
    // kept in row-sync with the table via matching dataset.seq and matching
    // CSS heights (same split-column pattern the old single-table layout used).
    const removeCell = document.createElement('div');
    removeCell.className = 'bgr-li-remove-cell';
    removeCell.dataset.seq = seq;
    removeCell.innerHTML = '<button type="button" class="bgr-li-remove" title="Remove">&times;</button>';
    removeCell.querySelector('.bgr-li-remove').addEventListener('click', () => bgr_removeLineItemRow(blockEl, seq));
    const removeCol = blockEl.querySelector('.bgr-block-li-remove-col');
    removeCol.insertBefore(removeCell, removeCol.querySelector('.bgr-li-remove-foot'));

    bgr_syncRemoveColGap(blockEl);
    bgr_updateLineItemTotal(blockEl);
}

function bgr_removeLineItemRow(blockEl, seq) {
    const tbody = blockEl.querySelector('.bgr-block-li-tbody');
    if (tbody.rows.length <= 1) { bgr_toast('At least one line item is required.', 'error'); return; }
    blockEl.querySelector(`.bgr-li-remove-cell[data-seq="${seq}"]`)?.remove();
    const row = tbody.querySelector(`tr[data-seq="${seq}"]`);
    if (row) bgr_cleanupAccountMenus(row);
    row?.remove();
    bgr_syncRemoveColGap(blockEl);
    bgr_updateLineItemTotal(blockEl);
}

// .bgr-block-li-wrap's horizontal scrollbar (when the table needs it) reserves
// space at its own bottom that .bgr-block-li-remove-col — a plain non-scrolling
// sibling — doesn't lose. Without compensating for that, the two columns'
// rows drift out of alignment by exactly the scrollbar's height. Measuring
// it directly (rather than guessing a fixed px value) keeps this correct
// regardless of OS/browser scrollbar style.
function bgr_syncRemoveColGap(blockEl) {
    const wrap = blockEl.querySelector('.bgr-block-li-wrap');
    const col  = blockEl.querySelector('.bgr-block-li-remove-col');
    if (!wrap || !col) return;
    const scrollbarHeight = wrap.offsetHeight - wrap.clientHeight;
    col.style.paddingBottom = scrollbarHeight > 0 ? scrollbarHeight + 'px' : '0';
}

// Type-directly-to-search chart-of-accounts picker — a small custom
// dropdown, NOT typeahead.js (unlike the Project picker above). This field
// sits inside .bgr-block-li-wrap (overflow-x:auto, which per spec forces
// its overflow-y to clip too) and .bgr-block-li-outer (overflow:hidden, for
// rounded corners) — both clip a normal position:absolute dropdown no
// matter its z-index. Forcing typeahead.js's own menu to position:fixed to
// escape that caused a real race (the library kept re-applying its own
// absolute-positioned top/left after ours, sometimes winning and hiding the
// menu off-screen). A dropdown built and positioned entirely by us — one
// <div class="bgr-acct-menu"> per row, appended to <body> so no ancestor
// can clip it — has nothing else fighting it.
//
// BGR_COA_OPTIONS is already fully loaded client-side (bgr_loadChartOfAccounts),
// so matching is done locally with no extra request. Selecting an option
// resolves the hidden .bgr-li-account-id field (the input itself already
// shows "code — name"); clearing/typing away from a matched label clears
// the id, so bgr_save() can reject an unresolved row.
function bgr_setAccountId($input, id) {
    $input.closest('tr')[0].querySelector('.bgr-li-account-id').value = id || '';
}

// menu element -> its input element, so the single global focusin/scroll
// listeners below (registered once, not per-row) know which menu goes with
// which field, and bgr_removeLineItemRow can clean up its own entry.
const BGR_ACCT_MENUS = new Map();

// coeMode: only PS/MOOE accounts are offered. The regular (non-COE) flow is the mirror
// image — PS/MOOE accounts are now reserved for COE line items only, so they're excluded
// there instead. Either way, chart_of_accounts has no stored expense-class column, so this is
// the same account_code-prefix rule DisbursementVoucherService::deriveExpenseClass() uses —
// see bgr_isCoeEligibleAccount() below.
function bgr_initAccountPicker($input, coeMode = false) {
    const input = $input.get(0);
    const menu  = document.createElement('div');
    menu.className = 'bgr-acct-menu';
    document.body.appendChild(menu);
    BGR_ACCT_MENUS.set(menu, input);
    $input.data('bgr-acct-menu', menu);

    function renderOptions(query) {
        const q = query.trim().toLowerCase();
        const prefix = coeMode ? bgr_coeBlockAccountPrefix(input.closest('.bgr-block')) : null;
        const base = BGR_COA_OPTIONS.filter(a => bgr_isCoeEligibleAccount(a.account_code) === coeMode
            && (!prefix || a.account_code.startsWith(prefix)));
        const matches = base
            .filter(a => !q || a.account_code.toLowerCase().includes(q) || a.account_title.toLowerCase().includes(q))
            .slice(0, 50);
        menu.innerHTML = matches.length
            ? matches.map(a => `<div class="bgr-acct-option" data-account-id="${bgr_escHtmlAttr(a.account_id)}">${bgr_esc(bgr_accountLabel(a))}</div>`).join('')
            : `<div class="bgr-acct-empty">${prefix ? `No matching ${prefix} account.` : (coeMode ? 'No matching PS/MOOE account.' : 'No matching account.')}</div>`;
    }

    function openMenu() {
        BGR_ACCT_MENUS.forEach((_, m) => { if (m !== menu) m.classList.remove('open'); });
        renderOptions(input.value);
        const rect = input.getBoundingClientRect();
        menu.style.top   = (rect.bottom + 4) + 'px';
        menu.style.left  = rect.left + 'px';
        menu.style.width = rect.width + 'px';
        menu.classList.add('open');
    }

    $input.on('focus', openMenu);
    $input.on('input', () => {
        if ($input.data('bgr-syncing')) return;
        if ($input.val() !== $input.data('bgr-matched-text')) {
            bgr_setAccountId($input, '');
        }
        openMenu();
    });

    // mousedown, not click: a plain click would first let the browser blur
    // the input (nothing else in the menu is focusable), which the global
    // focusin listener above treats as "focus left the field" and closes
    // the menu — hiding the option out from under the click before it ever
    // fires. preventDefault() here stops that blur, so focus never leaves
    // the input and the selection below always registers.
    menu.addEventListener('mousedown', (ev) => {
        const optionEl = ev.target.closest('.bgr-acct-option');
        if (!optionEl) return;
        ev.preventDefault();

        const opt = BGR_COA_OPTIONS.find(a => String(a.account_id) === optionEl.dataset.accountId);
        if (!opt) return;

        const label = bgr_accountLabel(opt);
        $input.data('bgr-syncing', true);
        $input.val(label).data('bgr-matched-text', label);
        bgr_setAccountId($input, opt.account_id);
        setTimeout(() => $input.removeData('bgr-syncing'), 0);
        menu.classList.remove('open');
    });
}

// Registered once, globally (not per-row): closes an open account menu the
// moment focus genuinely lands somewhere outside it (a different field,
// another row, or outside the modal), and when something OTHER than the
// menu itself scrolls — the menu is position:fixed, so it would otherwise
// drift away from the input it belongs to as soon as the (horizontally-
// scrollable) table or the modal body scrolls. Scrolling the menu's own
// suggestion list (ev.target is the menu) must NOT close it — that's just
// the user scrolling through the options, which is the whole point of it
// being scrollable.
document.addEventListener('focusin', (ev) => {
    BGR_ACCT_MENUS.forEach((inputEl, menuEl) => {
        if (inputEl !== ev.target && !menuEl.contains(ev.target)) {
            menuEl.classList.remove('open');
        }
    });
});
// Same reasoning as the project picker's 'mousedown' listener above: a
// click on a plain, non-focusable part of the page doesn't move focus
// anywhere, so 'focusin' alone misses it.
document.addEventListener('mousedown', (ev) => {
    BGR_ACCT_MENUS.forEach((inputEl, menuEl) => {
        if (inputEl !== ev.target && !menuEl.contains(ev.target)) {
            menuEl.classList.remove('open');
        }
    });
});
document.addEventListener('scroll', (ev) => {
    BGR_ACCT_MENUS.forEach((_, menuEl) => {
        if (menuEl.contains(ev.target)) return;
        menuEl.classList.remove('open');
    });
}, true);

// Each account picker's dropdown lives on <body>, detached from its row —
// removing the row alone would otherwise leak that element. Call this with
// whatever's about to be removed/cleared (a single row, a whole project
// block, or an entire container of them) before it's actually removed.
function bgr_cleanupAccountMenus(containerEl) {
    containerEl.querySelectorAll('.bgr-li-account-code').forEach((el) => {
        const menu = $(el).data('bgr-acct-menu');
        if (menu) {
            BGR_ACCT_MENUS.delete(menu);
            menu.remove();
        }
    });
}

/* ── Modal ─────────────────────────────────────────────────── */
async function bgr_openModal(mode, data = null) {
    bgr_hideErr();
    $bgr('bgr-grand-total-display').value = '0.00'; // recomputed as blocks are (re)built below

    // 'add-coe'/'edit-coe': a COE request has no Program/Sub-Program/Project at all — #bgr-coe-root
    // and #bgr-pblocks-root are mutually exclusive, never both shown (see bgr_addCoeSubComponentBlock()/
    // bgr_saveCoe() for the COE-only path vs. bgr_addProgramBlock()/bgr_save() for the regular one).
    const isCoe = mode === 'add-coe' || mode === 'edit-coe';
    $bgr('bgr-card').classList.toggle('bgr-mode-coe', isCoe);
    $bgr('bgr-coe-root').style.display = isCoe ? '' : 'none';
    $bgr('bgr-pblocks-root').style.display = isCoe ? 'none' : '';
    // COE has no Region at all (no Program/Project for it to scope — see #bgr-top-row.bgr-coe-row).
    $bgr('bgr-top-row').classList.toggle('bgr-coe-row', isCoe);

    // Lazy-loaded here rather than at page load — the Account Code picker
    // (built inside bgr_addProjectBlock below) needs BGR_COA_OPTIONS already
    // populated, so this must finish before any project block is built.
    window.PagePreloader?.show();
    await bgr_loadChartOfAccounts();
    window.PagePreloader?.hide();

    $bgr('bgr-mode').value = mode;
    $bgr('bgr-fiscal-yr').disabled = false;
    $bgr('bgr-region').disabled = false;

    bgr_cleanupAccountMenus($bgr('bgr-coe-root'));
    $bgr('bgr-coe-blocks-wrap').innerHTML = '';
    bgr_cleanupAccountMenus($bgr('bgr-pblocks-wrap'));
    $bgr('bgr-pblocks-wrap').innerHTML = '';

    if (isCoe) {
        // Type of Fund/Component: 'edit-coe' starts from whatever its existing line items were
        // already saved with — every one of them carries the same shared pick (see bgr_saveCoe), so
        // the first one's own is as good a starting point as any. A brand-new 'add-coe' request has
        // no data to start from instead, so it defaults to this office's usual COE pick (Type of
        // Fund "Corporate Receipt", Component "COE") rather than an empty placeholder — either can
        // still be changed before saving. Sub-Component blocks themselves are built further below,
        // once the mode is known (grouped from existing data for 'edit-coe', a single blank one for
        // 'add-coe').
        const coeLineItems = data?.coe_line_items ?? [];
        const defaultTypeOfFundId = coeLineItems.length
            ? (coeLineItems[0]?.type_of_fund_id ?? '')
            : bgr_lookupIdByName(BGR_TYPES_OF_FUND, 'type_of_fund_id', 'CORPORATE RECEIPT');
        const defaultComponentId = coeLineItems.length
            ? (coeLineItems[0]?.component_id ?? '')
            : bgr_lookupIdByName(BGR_COMPONENTS, 'component_type_id', 'COE');
        bgr_fillBlockTypeOfFund($bgr('bgr-coe-type-of-fund'), defaultTypeOfFundId);
        bgr_fillBlockComponents($bgr('bgr-coe-component'), defaultComponentId, ['CAPITAL OUTLAY', 'OTHER-CAPITAL OUTLAY']);
    }

    if (mode === 'edit-program') {
        $bgr('bgr-id').value = data.budget_req_program_id;
        $bgr('bgr-modal-title').textContent = 'Edit Program';
        $bgr('bgr-save').textContent = 'Save Program';

        $bgr('bgr-fiscal-yr').value = data.fiscal_yr || '';
        $bgr('bgr-fiscal-yr').disabled = true;

        // The request's own Region isn't reassigned from here (it's the header's own rc_id — changing
        // it would move this Program to a different budget_req header entirely) — prefilled from the
        // existing header and locked, same as Fiscal Year just above. bgr_editProgram (budget-req-page.php)
        // passes it along as data.rc_id. Must happen BEFORE bgr_addProgramBlock below builds this
        // Program's own Project blocks — their code preview/typeahead both read #bgr-region.
        $bgr('bgr-region').value = data.rc_id || '';
        $bgr('bgr-region').disabled = true;
        bgr_syncProjectInputsRegionState();

        const ctx = $bgr('bgr-program-context');
        ctx.textContent = `Editing a program under Budget Request ${data.budget_reference_number} (FY ${data.fiscal_yr}).`;
        ctx.style.display = '';

        // Exactly one Program block — no "+ Add Program" (this mode edits a single existing
        // Program, nothing more) and bgr_updatePblockRemoveVisibility (called inside
        // bgr_addProgramBlock) already hides its lone Remove button.
        $bgr('bgr-add-program-block').style.display = 'none';
        bgr_addProgramBlock(data);
    } else if (mode === 'edit-coe') {
        // #bgr-id holds the budget_reference_number directly here (not a budget_req_program_id —
        // a COE request has no Program) — see bgr_resetCoeDocSection() and bgr_save().
        $bgr('bgr-id').value = data.budget_reference_number;
        $bgr('bgr-modal-title').textContent = 'Edit COE Budget Request';
        $bgr('bgr-save').textContent = 'Save Request';

        $bgr('bgr-fiscal-yr').value = data.fiscal_yr || '';
        $bgr('bgr-fiscal-yr').disabled = true;

        const ctx = $bgr('bgr-program-context');
        ctx.textContent = `Editing COE Budget Request ${data.budget_reference_number} (FY ${data.fiscal_yr}).`;
        ctx.style.display = '';

        bgr_groupCoeLineItemsBySubComponent(data.coe_line_items ?? []).forEach(g => bgr_addCoeSubComponentBlock(g));
        bgr_resetCoeDocSection(data.budget_reference_number);
    } else {
        // 'add' or 'add-coe'
        $bgr('bgr-id').value = '';
        $bgr('bgr-modal-title').textContent = isCoe ? 'Add COE Budget Request' : 'Add Budget Request';
        $bgr('bgr-save').textContent = 'Save Request';

        // Default a new request to the current year rather than an empty
        // placeholder — the most common case, and the user can still change it.
        const currentYearOption = $bgr('bgr-fiscal-yr').querySelector(`option[value="${new Date().getFullYear()}"]`);
        $bgr('bgr-fiscal-yr').value = currentYearOption ? currentYearOption.value : '';

        $bgr('bgr-program-context').style.display = 'none';

        if (isCoe) {
            bgr_addCoeSubComponentBlock();
            bgr_resetCoeDocSection('');
        } else {
            // No good default for a brand-new request — force a fresh pick every time (the previous
            // modal session's Region, if any, must not leak in).
            $bgr('bgr-region').value = '';
            bgr_syncProjectInputsRegionState();
            $bgr('bgr-add-program-block').style.display = '';
            bgr_addProgramBlock();
        }
    }

    $bgr('bgr-overlay').classList.add('open');
}

function bgr_closeModal() {
    $bgr('bgr-overlay').classList.remove('open');
    bgr_cleanupAccountMenus($bgr('bgr-coe-root'));
    $bgr('bgr-coe-blocks-wrap').innerHTML = '';
    bgr_cleanupAccountMenus($bgr('bgr-pblocks-wrap'));
    $bgr('bgr-pblocks-wrap').innerHTML = '';
    $bgr('bgr-form').reset();
    $bgr('bgr-fiscal-yr').disabled = false;
    $bgr('bgr-region').disabled = false;
    bgr_hideErr();
}

function bgr_showErr(msg) {
    const el = $bgr('bgr-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function bgr_hideErr() {
    $bgr('bgr-err').style.display = 'none';
}

// Shared POST/PUT helper for the non-COE save paths below — plain JSON when there are no new files
// to upload, multipart FormData (one flat doc_file_N index) otherwise, same convention bgr_saveCoe
// uses on its own.
async function bgr_postBody(url, method, body, docFiles) {
    const fetchOpts = docFiles.length
        ? (() => {
            const fd = new FormData();
            fd.append('data', JSON.stringify(body));
            docFiles.forEach((f, i) => fd.append('doc_file_' + i, f));
            return { method, credentials: 'same-origin', body: fd };
        })()
        : { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
    const res = await fetch(url, fetchOpts);
    return res.json();
}

// Builds ONE Program block's save payload — { programCode, subProgramCode, projects, docFiles } — or
// a plain string error message naming this block if something required is missing. `label` is how
// this block is named in that message: "Program" in 'edit-program' mode (only ever one, no ordinal
// needed) or "Program N" in 'add' mode (there may be several — see bgr_save).
function bgr_buildProgramPayload(pblock, label) {
    const programCode    = pblock.querySelector('.bgr-pblock-program').value;
    const subProgramCode = pblock.querySelector('.bgr-pblock-sub-program').value;
    if (!programCode) return `${label}: select a program.`;

    // The whole request's own Region — every project in it (new or existing) shares this one value
    // now (see #bgr-region/bgr_save, which already requires it before this is ever called).
    const rcId = $bgr('bgr-region').value;

    const projects = [];
    const docFiles = []; // one flat list across every project block under THIS Program — see bgr_getProjectDocPayload()
    const blocks = pblock.querySelectorAll('.bgr-pblock-projects-wrap .bgr-block');
    for (let pi = 0; pi < blocks.length; pi++) {
        const block = blocks[pi];
        // A matched suggestion resolves the hidden code; otherwise the typed
        // text has no confirmed match — send it as project_name so the
        // backend resolves/creates it atomically when the request is saved.
        const projectCode = block.querySelector('.bgr-block-project-code').value;
        const projectName = block.querySelector('.bgr-block-project-name').value.trim();
        const location    = block.querySelector('.bgr-block-location').value.trim();
        if (!projectCode && !projectName) return `${label}, project ${pi + 1}: select or enter a project.`;

        const typeOfFundId = block.querySelector('.bgr-block-type-of-fund').value;
        if (!typeOfFundId) return `${label}, project ${pi + 1}: select a type of fund.`;

        const componentId = block.querySelector('.bgr-block-component').value; // optional — '' = none
        const subComponentId = block.querySelector('.bgr-block-sub-component').value; // optional — '' = none

        const sourceOfFund = block.querySelector('.bgr-block-source-of-fund').value;
        if (!sourceOfFund) return `${label}, project ${pi + 1}: select a source of fund.`;

        const programSourceId = block.querySelector('.bgr-block-program-source').value; // optional — '' = none

        const amount = bgr_unformatAmount(block.querySelector('.bgr-block-amount').value);
        if (!amount || Number(amount) <= 0) return `${label}, project ${pi + 1}: proposed amount is required.`;

        // One line item, always against the same chart-of-accounts entry (id 214, "Work-in-Process
        // Inventory") — see bgr_addProjectBlock's own doc comment for why the per-line Account Code
        // picker was dropped from this flow.
        const lineItems = [{ account_id: '214', type_of_fund_id: typeOfFundId, component_id: componentId, sub_component_id: subComponentId, source_of_fund: sourceOfFund, program_source_id: programSourceId, particulars: projectName || projectCode, proposed_amount: amount }];

        const supportingDocuments = bgr_getProjectDocPayload(block, docFiles);

        projects.push({ project_code: projectCode, project_name: projectName, rc_id: rcId, location, line_items: lineItems, supporting_documents: supportingDocuments });
    }
    if (!projects.length) return `${label}: add at least one project.`;

    return { programCode, subProgramCode, projects, docFiles };
}

async function bgr_save(ev) {
    ev.preventDefault();
    bgr_hideErr();
    const mode = $bgr('bgr-mode').value;
    const id   = $bgr('bgr-id').value;

    const fiscalYr = $bgr('bgr-fiscal-yr').value;
    if (!fiscalYr) { bgr_showErr('Select a fiscal year.'); return; }

    if (mode === 'add-coe' || mode === 'edit-coe') { await bgr_saveCoe(mode, id, fiscalYr); return; }

    // 'add'/'edit-program' only — COE has no Region at all (see #bgr-top-row.bgr-coe-row). This is
    // the one Region the whole request (every Program/Project in it) shares — for 'add' it also
    // becomes the budget_req header's own rc_id (see the POST /budget_req/add body below).
    const regionId = $bgr('bgr-region').value;
    if (!regionId) { bgr_showErr('Select a region.'); return; }

    if (mode === 'edit-program') {
        const pblock  = document.querySelector('#bgr-pblocks-wrap .bgr-pblock');
        const payload = bgr_buildProgramPayload(pblock, 'Program');
        if (typeof payload === 'string') { bgr_showErr(payload); return; }

        const body   = { fiscal_yr: fiscalYr, program_code: payload.programCode, sub_program_code: payload.subProgramCode, projects: payload.projects };
        const url    = `${BGR_API}/program/update/${id}`;
        const method = payload.docFiles.length ? 'POST' : 'PUT'; // PHP only populates $_FILES/multipart bodies on POST

        window.PagePreloader?.show();
        try {
            const json = await bgr_postBody(url, method, body, payload.docFiles);
            if (!json.success) throw new Error(json.message || 'Failed to save.');
            bgr_toast('Program updated successfully.', 'success');
            bgr_closeModal();
            window.bgr_afterSave?.(mode, json);
        } catch (e) {
            bgr_showErr(e.message);
        } finally {
            window.PagePreloader?.hide();
        }
        return;
    }

    // 'add' — one or more Program blocks (see "+ Add Program"), each its own POST /budget_req/add
    // call, run SEQUENTIALLY (not Promise.all): every call targets the same (fiscal year, Region) —
    // the first one may need to CREATE that header (BudgetReqService::addProgram, now keyed by the
    // chosen Region's own rc_id, not FMD's own account) before the next can append another Program to
    // it. Validate every block BEFORE saving any of them, so a mistake in block 3 doesn't leave
    // blocks 1-2 already saved while block 3 is rejected.
    const pblocks  = document.querySelectorAll('#bgr-pblocks-wrap .bgr-pblock');
    const payloads = [];
    for (let i = 0; i < pblocks.length; i++) {
        const label   = pblocks.length > 1 ? `Program ${i + 1}` : 'Program';
        const payload = bgr_buildProgramPayload(pblocks[i], label);
        if (typeof payload === 'string') { bgr_showErr(payload); return; }
        payloads.push(payload);
    }

    window.PagePreloader?.show();
    let savedCount = 0;
    try {
        let lastJson = null;
        for (const payload of payloads) {
            const body = { fiscal_yr: fiscalYr, rc_id: regionId, program_code: payload.programCode, sub_program_code: payload.subProgramCode, projects: payload.projects };
            lastJson = await bgr_postBody(`${BGR_API}/add`, 'POST', body, payload.docFiles);
            if (!lastJson.success) {
                // Every Program before this one already committed server-side (each call is its own
                // transaction) — say so, rather than implying nothing was saved.
                throw new Error(
                    (savedCount > 0 ? `${savedCount} program(s) were already saved. ` : '')
                    + (lastJson.message || `Failed to save program ${savedCount + 1}.`)
                );
            }
            savedCount++;
        }
        const message = payloads.length > 1
            ? `${savedCount} programs added to the budget request.`
            : (lastJson.created_new_header ? 'Budget request created successfully.' : 'Program added to the existing budget request.');
        bgr_toast(message, 'success');
        bgr_closeModal();
        window.bgr_afterSave?.(mode, lastJson);
    } catch (e) {
        bgr_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

// 'add-coe'/'edit-coe' save — one or more Sub-Component blocks (see bgr_addCoeSubComponentBlock()),
// each with its own line-items table, plus the request-level Supporting Documents section:
// no Program/Sub-Program, no project_code/project_name/location, and it posts to the
// dedicated COE endpoints instead of /add or /program/update.
async function bgr_saveCoe(mode, id, fiscalYr) {
    const blocks = Array.from(document.querySelectorAll('#bgr-coe-blocks-wrap .bgr-block'));
    if (!blocks.length) { bgr_showErr('Add at least one Sub-Component block.'); return; }

    // Type of Fund/Component are shared across every line item in this COE request (see
    // #bgr-coe-root's own row). Sub-Component is NOT — each block below carries its own pick,
    // applied to every line item nested under THAT block only.
    const typeOfFundId = $bgr('bgr-coe-type-of-fund').value;
    const componentId  = $bgr('bgr-coe-component').value;
    if (!typeOfFundId) { bgr_showErr('Select a type of fund.'); return; }

    const lineItems = [];
    let liIndex = 0;
    for (const block of blocks) {
        const subComponentId = block.querySelector('.bgr-coe-subcomp-select').value;
        for (const tr of block.querySelectorAll('.bgr-block-li-tbody tr')) {
            liIndex++;
            const accountId   = tr.querySelector('.bgr-li-account-id').value;
            const particulars = tr.querySelector('.bgr-li-particulars').value.trim();
            const amount      = bgr_unformatAmount(tr.querySelector('.bgr-li-amt').value);
            if (!accountId) { bgr_showErr(`Line item ${liIndex}: select a chart of accounts entry.`); return; }
            if (!particulars) { bgr_showErr(`Line item ${liIndex}: particulars is required.`); return; }
            if (!amount) { bgr_showErr(`Line item ${liIndex}: proposed amount is required.`); return; }
            lineItems.push({ account_id: accountId, type_of_fund_id: typeOfFundId, component_id: componentId, sub_component_id: subComponentId, particulars, proposed_amount: amount });
        }
    }
    if (!lineItems.length) { bgr_showErr('Add at least one line item.'); return; }

    const docFiles = [];
    const supportingDocuments = bgr_getProjectDocPayload($bgr('bgr-coe-docs'), docFiles);

    const body   = { fiscal_yr: fiscalYr, line_items: lineItems, supporting_documents: supportingDocuments };
    const url    = mode === 'edit-coe' ? `${BGR_API}/coe/update/${id}` : `${BGR_API}/add-coe`;
    const method = (mode === 'edit-coe' && !docFiles.length) ? 'PUT' : 'POST';

    window.PagePreloader?.show();
    try {
        let fetchOpts;
        if (docFiles.length) {
            const fd = new FormData();
            fd.append('data', JSON.stringify(body));
            docFiles.forEach((f, i) => fd.append('doc_file_' + i, f));
            fetchOpts = { method, credentials: 'same-origin', body: fd };
        } else {
            fetchOpts = { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
        }
        const res  = await fetch(url, fetchOpts);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save.');

        const message = mode === 'edit-coe'
            ? 'COE budget request updated successfully.'
            : (json.created_new_header ? 'COE budget request created successfully.' : 'Line items added to the existing COE budget request.');
        bgr_toast(message, 'success');
        bgr_closeModal();
        window.bgr_afterSave?.(mode, json);
    } catch (e) {
        bgr_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers (also used by host pages' own scripts, loaded after this one) ── */
function bgr_fmtMoney(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function bgr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function bgr_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}
function bgr_escHtmlAttr(text) {
    return String(text ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}
/* ── Comma-formatted amount inputs (display only — commas are stripped
   before validation/submission via bgr_unformatAmount) ──────────────── */
function bgr_fmtAmount(input) {
    const pos   = input.selectionStart;
    const prev  = input.value.length;
    const raw   = input.value.replace(/[^0-9.]/g, '');
    const parts = raw.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('') : parts[0];
    const diff = input.value.length - prev;
    try { input.setSelectionRange(pos + diff, pos + diff); } catch (_) {}

    const block = input.closest('.bgr-block');
    if (block) bgr_updateLineItemTotal(block);
}

// Sum of every line item's Proposed Amount in this block, shown in the
// table's own Total row — recomputed on every amount edit and whenever a
// row is added/removed (see bgr_addLineItemRow/bgr_removeLineItemRow).
function bgr_updateLineItemTotal(blockEl) {
    const cell = blockEl.querySelector('.bgr-block-li-total-amt');
    if (!cell) return;
    const total = Array.from(blockEl.querySelectorAll('.bgr-li-amt'))
        .reduce((sum, input) => sum + (parseFloat(bgr_unformatAmount(input.value)) || 0), 0);
    cell.textContent = bgr_fmtAmountInitial(total) || '0.00';
    bgr_updateGrandTotal();
}

// Sum of every project block's own Total row — recomputed whenever any of
// them changes (bgr_updateLineItemTotal) and whenever a whole project block
// is added/removed (a brand-new block always adds at least one line item,
// which already triggers bgr_updateLineItemTotal; removing one doesn't, so
// bgr_removeProjectBlock calls this directly).
function bgr_updateGrandTotal() {
    const display = $bgr('bgr-grand-total-display');
    if (!display) return;
    // COE mode's per-project line-item totals (.bgr-block-li-total-amt, #bgr-coe-blocks-wrap's one
    // block) and the regular flow's single Proposed Amount fields (.bgr-block-amount, summed across
    // EVERY Program block's own Projects) never coexist in the same modal session — summing both,
    // unscoped, is just a convenient way to cover either mode with one function.
    const liTotal = Array.from(document.querySelectorAll('.bgr-block-li-total-amt'))
        .reduce((sum, cell) => sum + (parseFloat(bgr_unformatAmount(cell.textContent)) || 0), 0);
    const amtTotal = Array.from(document.querySelectorAll('.bgr-block-amount'))
        .reduce((sum, input) => sum + (parseFloat(bgr_unformatAmount(input.value)) || 0), 0);
    display.value = bgr_fmtAmountInitial(liTotal + amtTotal) || '0.00';
}
function bgr_fmtAmountInitial(v) {
    return (v || v === 0) && v !== '' ? Number(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '';
}
function bgr_unformatAmount(v) {
    return String(v ?? '').replace(/,/g, '');
}
function bgr_statusBadge(name, color) {
    if (!name) return '<span class="nha-td-sub">—</span>';
    const c = /^#[0-9A-Fa-f]{6}$/.test(color || '') ? color : '#6B7280';
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;
        font-size:11px;font-weight:600;color:${c};background:${c}1A;border:1px solid ${c}40;white-space:nowrap;">
        <span style="width:6px;height:6px;border-radius:50%;background:${c};flex-shrink:0;"></span>${bgr_esc(name)}
    </span>`;
}

// The toast is built on FIRST USE, not when this script loads: SweetAlert2 is loaded asynchronously by views/layout.php
// (loadjs bundle "swal"), so a top-level `const … = Swal.mixin(…)` could run before `Swal` existed, throw, and leave the
// const uninitialized for good — every later bgr_toast() then failed with "Cannot access 'BGR_TOAST_SWAL' before
// initialization" (e.g. right after a successful Endorse / Return / Approve).
let bgr_toastSwal = null;

function bgr_toast(msg, type = 'info') {
    const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'info';

    if (typeof Swal === 'undefined') {
        // SweetAlert2 is still on its way: show the toast as soon as the layout's "swal" bundle is ready
        if (typeof loadjs !== 'undefined' && loadjs.ready) loadjs.ready('swal', () => bgr_toast(msg, type));
        return;
    }

    bgr_toastSwal ??= Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: el => {
            el.onmouseenter = Swal.stopTimer;
            el.onmouseleave = Swal.resumeTimer;
        },
    });
    bgr_toastSwal.fire({ icon, title: msg });
}
</script>
