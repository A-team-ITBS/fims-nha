<?php
/**
 * budget-req-readone.php
 *
 * Two read-only accordion views sharing one modal shell (JS namespace bgrv_):
 *
 *  - bgrv_openModal(requestContext, program) — "View" row action on one
 *    Program within a budget request. Lists every Sub-Program under that
 *    Program (master/reference data from fims.sub_program — NOT just the
 *    one sub-program this particular budget request happens to use), each
 *    expandable to that Sub-Program's Projects, annotated with THIS
 *    request's proposed amounts (per project, and a per-sub-program total).
 *      requestContext = { budget_reference_number, fiscal_yr, status_name, status_color, is_draft }
 *      program        = { budget_req_program_id, program_code, program_name, sub_program_name, projects[] }
 *    requestContext is used as-is (Fiscal Year / Status cards). The
 *    Sub-Programs/Projects shown are NOT read from program.projects[]
 *    though — this fetches them itself (filtered by program.program_code),
 *    since the full tree isn't part of the already-fetched list data; only
 *    the amounts come from program.projects[].
 *
 *  - bgrv_openCobModal(fiscalYr, extraParams) — "View as COB" button in the
 *    list page's filter bar (budget-req-page.php, and the FMD's Budget Proposal
 *    page, budget-prop-page.php), passed that same filter's current value
 *    ('' = every fiscal year); extraParams are extra query parameters for the
 *    fetch (the FMD page widens it to every Regional / District office);
 *    options.byUnit groups the outline per originating unit (each unit row is its own collapse/expand
 *    accordion toggle, bgrv_toggleUnit — independent of the others); options.showFund
 *    adds each Project's Type(s) of Fund (the FMD page only). No row/request
 *    context, no summary cards —
 *    otherwise a flat, always-visible indented outline (Program / Sub-Program /
 *    Project), built by its own renderer (bgrv_cobRowsHtml), not the
 *    panel/card one bgrv_openModal uses below. Sourced from the actual
 *    budget requests for that year (BGR_API + '/all?fiscal_yr=...'), not
 *    raw Program/Sub-Program/Project master data — so it only ever shows
 *    what's really been budgeted, flattened across every header the fetch
 *    returns (more than one for "All Years", or when the caller's RC scope
 *    spans several descendant rc_ids).
 *
 * Exclusive-accordion behavior (bgrv_openModal only) is scoped to each
 * panel's own immediate list (see bgrv_togglePanel).
 *
 * Included directly into budget-req-page.php and budget-prop-page.php rather than registered as its
 * own routed page — these are modals, not separate pages. Read-only: use
 * the list row's own "Edit" action to edit a Program.
 *
 * Depends on bgr_esc, bgr_fmtMoney, bgr_statusBadge and bgr_toast, plus the
 * BGR_PROGRAM_API / BGR_SUB_PROGRAM_API / BGR_PROJECT_BASE_API constants —
 * all defined in form/budget-req-form.php, which must be included on the
 * same page.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     PROGRAM VIEW MODAL   JS namespace: bgrv_
══════════════════════════════════════════════════════════ -->
<style>
    #bgrv-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #bgrv-overlay.open { display:flex; }
    #bgrv-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        transition:max-width .2s ease; }

    /* Two rows: #bgrv-header-top (icon+title, left — export/print/close, right) always on its own
       line, so the title never has to fight the amount totals for room (see #bgrv-header-total below,
       the COB-mode-only second row) — adding the "Preview and Print" button alongside "Export to Excel"
       made that a real contest on one shared row, truncating the title down to a few characters. */
    #bgrv-header { display:flex;flex-direction:column;gap:10px;
        padding:14px 18px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #bgrv-header-top { display:flex;align-items:center;justify-content:space-between;gap:12px; }
    #bgrv-header-info { display:flex;align-items:center;gap:9px;min-width:0;flex:1 1 auto; }
    #bgrv-header-info svg { width:15px;height:15px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .bgrv-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .bgrv-hdr-title { font-size:15px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    /* COB mode's headline figure — the one number meant to grab attention, so it lives in the header bar
       itself (always visible, even once the list below scrolls) rather than buried in the small counts
       line. Its own row, right-aligned under the export/print buttons it describes, so it can wrap
       on narrow widths without squeezing the title above it. Bold/white/mono against the navy header is
       the same restrained "callout" treatment the rest of this app uses for emphasis — no new color
       introduced. */
    #bgrv-header-total { display:none;align-items:center;justify-content:flex-end;gap:20px;flex-wrap:wrap; }
    #bgrv-header-total.show { display:flex; }
    .bgrv-header-total-item { display:flex;flex-direction:column;align-items:flex-end; }
    .bgrv-header-total-label { font-size:9px;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.06em;margin:0 0 1px; }
    .bgrv-header-total-val { font-size:19px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);
        font-family:'IBM Plex Mono',monospace;line-height:1; }
    #bgrv-header-actions { display:flex;align-items:center;gap:8px;flex-shrink:0; }
    #bgrv-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:16px;line-height:1;transition:background .15s; }
    #bgrv-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #bgrv-body { flex:1;min-height:0;overflow-y:auto;padding:20px; }

    #bgrv-summary { display:grid;grid-template-columns:repeat(4, 1fr);gap:10px;margin-bottom:18px; }
    .bgrv-sum-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:10px 12px;background:var(--afims-surface-subtle, #F4F7FA); }
    .bgrv-sum-label { font-size:10px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px; }
    .bgrv-sum-val { font-size:14px;font-weight:700;color:var(--afims-text, #1A1B21); }
    @media (max-width: 640px) { #bgrv-summary { grid-template-columns:repeat(2, 1fr); } }

    .bgrv-sec-hd { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px;
        display:flex;align-items:baseline;justify-content:space-between;gap:10px; }
    .bgrv-sec-hd-total { font-size:11px;font-weight:600;color:var(--afims-text-muted, #62657E);
        text-transform:none;letter-spacing:normal; }

    .bgrv-panel-list { display:flex;flex-direction:column;gap:8px; }
    .bgrv-panel-body .bgrv-panel-list { margin-top:8px; }
    .bgrv-panel { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;overflow:hidden;
        transition:box-shadow .15s,border-color .15s; }
    .bgrv-panel.open { box-shadow:0 2px 8px rgba(15,32,69,.08);border-color:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 25%, var(--afims-border, #E4ECF2)); }
    .bgrv-panel-hd { display:flex;align-items:center;justify-content:space-between;gap:10px;
        padding:10px 12px;background:var(--afims-surface-subtle, #F4F7FA);cursor:pointer;transition:background .15s; }
    .bgrv-panel-hd:hover { background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 60%, var(--afims-border, #E4ECF2)); }
    .bgrv-panel-hd-main { display:flex;align-items:center;gap:8px;min-width:0; }
    .bgrv-panel-chevron { flex-shrink:0;width:13px;height:13px;color:var(--afims-heading-fill, #000E3E);transition:transform .15s; }
    .bgrv-panel.open .bgrv-panel-chevron { transform:rotate(90deg); }
    .bgrv-panel-title { font-size:13px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .bgrv-panel-hd-meta { display:flex;align-items:center;gap:10px;flex-shrink:0; }
    .bgrv-panel-meta-item { font-size:11px;color:var(--afims-text-muted, #62657E);white-space:nowrap; }
    .bgrv-panel-meta-amt { font-size:12px;font-weight:700;color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Mono',monospace; }

    .bgrv-panel-body { display:none;padding:0 12px 12px; }
    .bgrv-panel.open .bgrv-panel-body { display:block; }

    .bgrv-proj-list { display:flex;flex-direction:column;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgrv-proj-row { display:flex;align-items:center;gap:10px;padding:8px 10px;font-size:12px;color:var(--afims-text, #1A1B21);
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgrv-proj-row:last-child { border-bottom:none; }
    .bgrv-proj-row:nth-child(even) { background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 45%, transparent); }
    .bgrv-proj-code { font-weight:700;font-family:'IBM Plex Mono',monospace;flex-shrink:0; }
    .bgrv-proj-name { color:var(--afims-text, #1A1B21);flex:1;min-width:0; }
    .bgrv-proj-amt { font-weight:700;font-family:'IBM Plex Mono',monospace;color:var(--afims-text, #1A1B21);white-space:nowrap;flex-shrink:0; }
    .bgrv-proj-empty { padding:12px;text-align:center;color:var(--afims-text-muted, #62657E);font-size:12px;font-style:italic; }

    #bgrv-empty { padding:24px 0;text-align:center;color:var(--afims-text-muted, #62657E);font-size:12px; }

    /* ── COB mode (bgrv_openCobModal): flat indented outline, no cards ── */
    .bgrv-cob-list { border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgrv-cob-row { display:flex;align-items:center;justify-content:space-between;gap:12px;
        padding:5px 10px;font-size:12px;color:var(--afims-text, #1A1B21);
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgrv-cob-list .bgrv-cob-row:last-child { border-bottom:none; }
    .bgrv-cob-program { font-weight:700;font-size:13px;background:var(--afims-surface-subtle, #F4F7FA);
        cursor:pointer;user-select:none; }
    .bgrv-cob-total { font-weight:700;font-size:13px;border-top:2px solid var(--afims-border, #E4ECF2);
        background:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 6%, var(--afims-surface, #fff));
        color:var(--afims-heading-fill, #000E3E); }
    .bgrv-cob-subprogram { padding-left:28px;font-weight:600;background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 50%, transparent);
        cursor:pointer;user-select:none; }
    .bgrv-cob-project { padding-left:52px;color:var(--afims-text-muted, #62657E); }
    /* Program/Sub-Program rows are collapse/expand toggles too (bgrv_toggleDisclosure), same mechanics as the
       unit/group ones below — chevron prefixing the row's own label, rotating via .open on the row itself,
       inheriting that row's text color (currentColor) rather than a fixed one like the unit/group chevrons,
       since Program and Sub-Program rows don't share one text color between the folded and byUnit views. */
    .bgrv-cob-row-label { display:flex;align-items:center;gap:6px;min-width:0; }
    .bgrv-cob-disclosure-chevron { flex-shrink:0;width:11px;height:11px;color:currentColor;transition:transform .22s ease; }
    .bgrv-cob-row.open .bgrv-cob-disclosure-chevron { transform:rotate(90deg); }
    .bgrv-cob-program-body,
    .bgrv-cob-subprogram-body { overflow:hidden;transition:max-height .22s ease; }
    .bgrv-cob-program-body.bgrv-collapsed,
    .bgrv-cob-subprogram-body.bgrv-collapsed { max-height:0; }
    /* Four amount columns (Proposed / Approved / Amendment / Amended) kept at a fixed width so they line up
       across every row's indent level, and under the column-header row (.bgrv-cob-col-headers below). */
    .bgrv-cob-amts { display:flex;align-items:center;gap:16px;flex-shrink:0; }
    .bgrv-cob-amt { font-family:'IBM Plex Mono',monospace;font-weight:700;color:var(--afims-text, #1A1B21);
        white-space:nowrap;flex-shrink:0;min-width:96px;text-align:right; }
    .bgrv-cob-project .bgrv-cob-amt { font-weight:600;color:var(--afims-text-muted, #62657E); }
    .bgrv-cob-empty .bgrv-cob-amt { display:none; }

    /* Column header row (Proposed Amount / Approved Amount / Amendment / Amended Amount) above the outline. */
    .bgrv-cob-col-headers { background:var(--afims-heading-fill, #000E3E);position:sticky;top:0;z-index:1; }
    .bgrv-cob-amt-label { font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.04em;min-width:96px;text-align:right; }
    /* The "CAPITAL OUTLAY" / "CORPORATE OPERATING EXPENDITURE" section label, moved into the same
       blue bar as the amount column headers so it lines up with them on one row. */
    .bgrv-cob-sec-label { font-size:11px;font-weight:700;color:#fff;text-transform:uppercase;letter-spacing:.05em; }

    /* COB mode with options.showFund (the FMD's Budget Proposal page): the Type(s) of Fund of a Project, after its name. */
    .bgrv-cob-fund-wrap { display:inline-flex;align-items:center;flex-wrap:wrap;gap:4px;margin-left:10px;vertical-align:middle; }
    .bgrv-cob-fund-label { font-size:11px;font-weight:500;color:var(--afims-text-muted, #62657E); }
    .bgrv-cob-fund { display:inline-block;padding:1px 8px;border-radius:10px;font-size:11px;font-weight:600;white-space:nowrap;
        color:var(--afims-heading-fill, #000E3E);background:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 8%, #fff); }
    .bgrv-cob-fund-none { font-size:11px;color:var(--afims-text-muted, #62657E); }
    #bgrv-card.bgrv-wide .bgrv-cob-fund-label,
    #bgrv-card.bgrv-wide .bgrv-cob-fund,
    #bgrv-card.bgrv-wide .bgrv-cob-fund-none { font-size:12px; }

    /* COB mode, one block per originating unit (options.byUnit): a unit row, then that unit's own Program / Sub-Program / Project rows,
       indented one level further than in the folded outline. The unit row is an accordion toggle (bgrv_toggleUnit) for its own
       nested rows — collapsed by default so a long list of units is scannable; its chevron rotates to show the current state. */
    .bgrv-cob-unit { align-items:flex-start;font-weight:700;font-size:13px;border-top:2px solid var(--afims-border, #E4ECF2);
        background:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 7%, #fff);cursor:pointer;user-select:none; }
    .bgrv-cob-list > .bgrv-cob-unit:first-child { border-top:none; }
    .bgrv-cob-unit-label { display:flex;align-items:flex-start;gap:6px; }
    .bgrv-cob-unit-chevron { flex-shrink:0;width:12px;height:12px;margin-top:3px;color:var(--afims-heading-fill, #000E3E);transition:transform .22s ease; }
    .bgrv-cob-unit.open .bgrv-cob-unit-chevron { transform:rotate(90deg); }
    .bgrv-cob-unit small { display:block;margin-top:2px;font-size:11px;font-weight:500;color:var(--afims-text-muted, #62657E); }
    .bgrv-cob-unit-code { font-family:'IBM Plex Mono',monospace;font-size:11px;font-weight:600;padding:1px 6px;border-radius:5px;
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text-muted, #62657E); }
    .bgrv-cob-unit-meta { display:flex;align-items:center;gap:10px;flex-shrink:0; }
    /* Animated open/close (bgrv_toggleUnit sets an explicit pixel max-height to transition from/to,
       then releases it back to the CSS default once the transition ends — see that function for why). */
    .bgrv-cob-nest { overflow:hidden;transition:max-height .22s ease; }
    .bgrv-cob-nest.bgrv-collapsed { max-height:0; }
    /* Inside a unit's nest, Programs/COE are grouped under their own "CAPITAL OUTLAY" / "CORPORATE
       OPERATING EXPENDITURE" label (bgrv-cob-group-label) one level up from Program, so Programs/PS/MOOE
       read as children of that label rather than siblings of the unit row. The label is itself a collapse/
       expand toggle (bgrv_toggleGroup) for just its own Program or PS/MOOE rows (bgrv-cob-group-body) —
       collapsed by default, same animated max-height approach as the unit row's own bgrv-cob-nest.
       Their padding-left overrides live further down (after #bgrv-card.bgrv-wide .bgrv-cob-row) — see the
       comment there for why: the COB modal is always .bgrv-wide, and that rule's own padding-left would
       otherwise win the specificity tie against these by coming later in the stylesheet. */
    .bgrv-cob-group-label { font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.04em;
        color:var(--afims-heading-fill, #000E3E);background:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 5%, #fff);
        cursor:pointer;user-select:none; }
    .bgrv-cob-group-label-text { display:flex;align-items:center;gap:6px; }
    .bgrv-cob-group-chevron { flex-shrink:0;width:11px;height:11px;color:var(--afims-heading-fill, #000E3E);transition:transform .22s ease; }
    .bgrv-cob-group-label.open .bgrv-cob-group-chevron { transform:rotate(90deg); }
    .bgrv-cob-group-body { overflow:hidden;transition:max-height .22s ease; }
    .bgrv-cob-group-body.bgrv-collapsed { max-height:0; }

    /* COB mode only — larger text to match the wider modal (.bgrv-wide, see #bgrv-card above). */
    #bgrv-card.bgrv-wide .bgrv-hdr-title { font-size:17px; }
    #bgrv-card.bgrv-wide .bgrv-header-total-label { font-size:11px; }
    #bgrv-card.bgrv-wide .bgrv-header-total-val { font-size:23px; }
    #bgrv-card.bgrv-wide .bgrv-sec-hd,
    #bgrv-card.bgrv-wide .bgrv-sec-hd-total { font-size:13px; }
    #bgrv-card.bgrv-wide .bgrv-cob-row { padding:8px 14px;font-size:14px; }
    #bgrv-card.bgrv-wide .bgrv-cob-program,
    #bgrv-card.bgrv-wide .bgrv-cob-total { font-size:15px; }
    #bgrv-card.bgrv-wide .bgrv-cob-subprogram { padding-left:32px; }
    #bgrv-card.bgrv-wide .bgrv-cob-project { padding-left:58px; }
    /* These come LAST on purpose, after every rule above that also sets padding-left: the folded
       (non-byUnit) view's .bgrv-wide subprogram/project overrides just above are tied in specificity
       (one #id + two classes) with these nest-scoped ones for a row that's inside BOTH .bgrv-wide AND
       .bgrv-cob-nest (i.e. every byUnit row) — so whichever is declared later wins that tie, and it must
       be these, or byUnit's deeper indent would collapse back down to the folded view's shallower one.
       Same tie, for the same reason, against #bgrv-card.bgrv-wide .bgrv-cob-row above (hence also after
       that). The group-label selector is padded out to #id + two classes to match that specificity (plain
       .bgrv-cob-group-label alone, with neither, would always lose regardless of order). */
    #bgrv-card .bgrv-cob-list .bgrv-cob-group-label { padding-left:28px; }
    #bgrv-card .bgrv-cob-nest .bgrv-cob-program { padding-left:72px; }
    #bgrv-card .bgrv-cob-nest .bgrv-cob-subprogram { padding-left:96px; }
    #bgrv-card .bgrv-cob-nest .bgrv-cob-project { padding-left:120px; }
    #bgrv-card.bgrv-wide .bgrv-cob-amt { font-size:14px;min-width:120px; }
    #bgrv-card.bgrv-wide .bgrv-cob-amt-label { font-size:11px;min-width:120px; }
    #bgrv-card.bgrv-wide .bgrv-cob-sec-label { font-size:13px; }
    #bgrv-card.bgrv-wide .bgrv-cob-group-label { font-size:12px; }
    #bgrv-card.bgrv-wide .bgrv-cob-unit-chevron,
    #bgrv-card.bgrv-wide .bgrv-cob-group-chevron { width:14px;height:14px; }
    .bgrv-cob-empty { font-style:italic; }

    /* ── "Export to Excel" / "Preview and Print" (COB mode only — see bgrv_printCobOfficeType()) ── */
    #bgrv-print-wrap { display:none;position:relative;gap:8px; }
    #bgrv-card.bgrv-wide #bgrv-print-wrap { display:flex; }
    #bgrv-export-excel-btn, #bgrv-print-btn { display:flex;align-items:center;gap:6px;border:none;cursor:pointer;
        padding:0 12px;height:28px;border-radius:7px;font-size:12px;font-weight:700;white-space:nowrap;
        background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);
        color:var(--afims-pure-white, #FFFFFF);transition:background .15s; }
    #bgrv-export-excel-btn:hover, #bgrv-print-btn:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent); }
    #bgrv-export-excel-btn svg, #bgrv-print-btn svg { width:13px;height:13px;flex-shrink:0; }

    /* Print layout picker — a real modal (shares .nha-overlay/.nha-modal from nha-fims.css, same
       convention as readone/cob-report-readone.php's #cobr-overlay), not an anchored dropdown: #bgrv-overlay
       (z-index 9100) is already open behind it, so this needs a z-index above that to show on top. */
    #bgrv-print-overlay.nha-overlay { z-index:9150; }
    .bgrv-print-grid { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
    .bgrv-print-card { display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;
        padding:32px 18px;border:2px solid var(--afims-border-strong, #C7D0DC);border-radius:12px;cursor:pointer;
        background:var(--afims-surface, #FFFFFF);font-family:inherit;transition:border-color .15s,background .15s,box-shadow .15s; }
    .bgrv-print-card:hover { border-color:var(--afims-heading-fill, #000E3E);background:var(--afims-surface-subtle, #F4F7FA);
        box-shadow:0 2px 10px rgba(0,14,62,.1); }
    .bgrv-print-card svg { width:32px;height:32px;stroke:var(--afims-heading-fill, #000E3E);stroke-width:1.6;flex-shrink:0; }
    .bgrv-print-card span { font-size:14px;font-weight:700;color:var(--afims-text, #1A1B21); }

    /* Step 2 (the "Per Program" drill-down, Project vs Program) "Back" link — above its own 2-card grid, not
       part of it (see bgrv_showPrintStep1()/bgrv_showPrintStep2()). */
    .bgrv-print-back { display:flex;align-items:center;gap:6px;border:none;background:none;cursor:pointer;
        padding:4px 0;margin-bottom:12px;font-size:13px;font-weight:600;color:var(--afims-text-muted, #62657E);
        font-family:inherit;transition:color .15s; }
    .bgrv-print-back:hover { color:var(--afims-heading-fill, #000E3E); }
    .bgrv-print-back svg { width:14px;height:14px;flex-shrink:0; }
</style>

<div id="bgrv-overlay">
    <div class="u-modal-lg" id="bgrv-card">
        <div id="bgrv-header">
            <div id="bgrv-header-top">
                <div id="bgrv-header-info">
                    <svg fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <div style="min-width:0;">
                        <p class="bgrv-hdr-tag" id="bgrv-hdr-tag">Program</p>
                        <p class="bgrv-hdr-title" id="bgrv-modal-title">Program</p>
                    </div>
                </div>
                <div id="bgrv-header-actions">
                    <!-- COB mode only (#bgrv-card.bgrv-wide — see the CSS above); hidden for the Program View
                         modal. Both buttons share the same layout picker — step 1: Per Region / Per Program /
                         Per Fund Source / Detailed; "Per Program" drills into step 2: Project / Program (see the
                         markup below) — which format it ends up producing (xlsx vs. pdf) is set by
                         bgrv_openPrintModal()'s argument, stored in bgrv_printFormat and read by bgrv_printCob().
                         A Regional / District user's own office_type skips the picker entirely and goes straight
                         to "Project" — see bgrv_openPrintModal(). -->
                    <div id="bgrv-print-wrap">
                        <button type="button" id="bgrv-export-excel-btn" onclick="bgrv_openPrintModal('xlsx')" title="Export this COB to an Excel file">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            Export to Excel
                        </button>
                        <button type="button" id="bgrv-print-btn" onclick="bgrv_openPrintModal('pdf')" title="Preview this COB, then print it as a PDF">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.75A2.25 2.25 0 0015.75 1.5h-7.5A2.25 2.25 0 006 3.75v3.284" />
                        </svg>
                        Preview and Print
                    </button>
                </div>
                <button type="button" id="bgrv-close">&times;</button>
            </div>
            </div>
            <div id="bgrv-header-total">
                <div class="bgrv-header-total-item">
                    <p class="bgrv-header-total-label">Total Proposed</p>
                    <p class="bgrv-header-total-val" id="bgrv-header-total-proposed-val">—</p>
                </div>
                <div class="bgrv-header-total-item">
                    <p class="bgrv-header-total-label">Total Approved</p>
                    <p class="bgrv-header-total-val" id="bgrv-header-total-val">—</p>
                </div>
                <div class="bgrv-header-total-item">
                    <p class="bgrv-header-total-label">Total Amendment</p>
                    <p class="bgrv-header-total-val" id="bgrv-header-total-adjustment-val">—</p>
                </div>
                <div class="bgrv-header-total-item">
                    <p class="bgrv-header-total-label">Total Amended Amount</p>
                    <p class="bgrv-header-total-val" id="bgrv-header-total-revised-val">—</p>
                </div>
            </div>
        </div>

        <div id="bgrv-body">
            <div id="bgrv-summary"></div>

            <p class="bgrv-sec-hd">
                <span id="bgrv-sec-hd-label">Sub-Programs</span>
                <span class="bgrv-sec-hd-total" id="bgrv-sec-hd-total"></span>
            </p>
            <div id="bgrv-panels"></div>
            <div id="bgrv-empty" style="display:none;"></div>
        </div>
    </div>
</div>

<!-- Layout picker — two steps (#bgrv-print-step1 / #bgrv-print-step2 below), opened by #bgrv-export-excel-btn / #bgrv-print-btn above
     (bgrv_openPrintModal()). A separate top-level modal (not nested in #bgrv-card) since it must sit on
     top of the already-open #bgrv-overlay, same "stack a second overlay on top" shape as
     readone/cob-report-readone.php's #cobr-overlay over its own host page. -->
<div id="bgrv-print-overlay" class="nha-overlay">
    <div class="nha-modal u-modal-sm" role="dialog" aria-modal="true" aria-labelledby="bgrv-print-modal-title">
        <div class="nha-modal-hdr">
            <h3 id="bgrv-print-modal-title">Choose a Layout</h3>
            <button type="button" class="nha-modal-close" id="bgrv-print-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body">
            <!-- Step 1: Per Region / Per Program / Per Fund Source / Detailed. "Per Program" doesn't print by
                 itself — it drills into step 2 (bgrv_showPrintStep2) to ask Project vs Program granularity
                 first. "Per Fund Source" is a placeholder (bgrv_printCob() below just toasts "not yet
                 implemented") — kept visible on purpose, functionality deferred. "Per Region" / "Per Fund
                 Source" are tagged .bgrv-print-central-only: a Regional/District user only ever works with
                 their own single office/region, so those two are hidden for them (see bgrv_openPrintModal()) —
                 they still get "Per Program" (-> Project Level / Program Level) and "Detailed". -->
            <div id="bgrv-print-step1" class="bgrv-print-grid">
                <button type="button" class="bgrv-print-card bgrv-print-central-only" onclick="bgrv_printCob('region')">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    <span>Per Region</span>
                </button>
                <button type="button" class="bgrv-print-card" onclick="bgrv_showPrintStep2()">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6m-19.5 0h19.5M3 9.75v-2.25A2.25 2.25 0 015.25 5.25h3.129c.412 0 .81.158 1.11.44l1.613 1.527a1.5 1.5 0 001.11.44h4.138a2.25 2.25 0 012.25 2.25v.093" />
                    </svg>
                    <span>Per Program</span>
                </button>
                <button type="button" class="bgrv-print-card bgrv-print-central-only" onclick="bgrv_printCob('fund_source')">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM21 10.5h.008v.008H21v-.008zM3 10.5h.008v.008H3V10.5z" />
                    </svg>
                    <span>Per Fund Source</span>
                </button>
                <button type="button" class="bgrv-print-card" onclick="bgrv_printCob('detailed')">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 16.5h7.5a2.25 2.25 0 002.25-2.25V11.25a9 9 0 00-9-9H6.75a2.25 2.25 0 00-2.25 2.25v15a2.25 2.25 0 002.25 2.25h1.5m2.25-12h1.5m-1.5 3.75h4.5m-4.5 3.75h4.5" />
                    </svg>
                    <span>Detailed</span>
                </button>
            </div>

            <!-- Step 2 (the "Per Program" drill-down): Project (Program -> Sub-Program -> Project tree) or
                 Program (one rolled-up total per Program, no Sub-Program/Project breakdown). -->
            <div id="bgrv-print-step2" style="display:none;">
                <button type="button" class="bgrv-print-back" onclick="bgrv_showPrintStep1()">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    Back
                </button>
                <div class="bgrv-print-grid">
                    <button type="button" class="bgrv-print-card" onclick="bgrv_printCob('project')">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 014.5 9.75h15A2.25 2.25 0 0121.75 12v.75m-19.5 0v6a2.25 2.25 0 002.25 2.25h15a2.25 2.25 0 002.25-2.25v-6m-19.5 0h19.5M3 9.75v-2.25A2.25 2.25 0 015.25 5.25h3.129c.412 0 .81.158 1.11.44l1.613 1.527a1.5 1.5 0 001.11.44h4.138a2.25 2.25 0 012.25 2.25v.093" />
                        </svg>
                        <span>Project Level</span>
                    </button>
                    <button type="button" class="bgrv-print-card" onclick="bgrv_printCob('program')">
                        <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                        <span>Program Level</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   PROGRAM / COB VIEW MODAL   prefix: bgrv_   — see docblock above for
   bgrv_openModal vs. bgrv_openCobModal.
═══════════════════════════════════════════════════════════════ */

const $bgrv = id => document.getElementById(id);

// The fiscal year / extra query params the COB modal is CURRENTLY showing (set by bgrv_openCobModal) — the
// layout picker has no filter bar of its own, so bgrv_printCob('region') reuses whatever the modal behind it
// is already filtered to.
let bgrv_cobFiscalYr = '';
let bgrv_cobExtraParams = {};
// Which format the layout picker is currently working towards — set by bgrv_openPrintModal()'s argument,
// read by bgrv_printCob() to decide bgrv_downloadCobReport() (xlsx) vs. bgrv_previewCobReport() (pdf).
let bgrv_printFormat = 'xlsx';

document.addEventListener('DOMContentLoaded', () => {
    $bgrv('bgrv-close')?.addEventListener('click', bgrv_closeModal);
    $bgrv('bgrv-print-modal-close')?.addEventListener('click', bgrv_closePrintModal);
    // Click the backdrop (not the modal card itself) closes it — same convention
    // readone/cob-report-readone.php's #cobr-overlay uses.
    $bgrv('bgrv-print-overlay')?.addEventListener('click', (e) => {
        if (e.target === $bgrv('bgrv-print-overlay')) bgrv_closePrintModal();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && $bgrv('bgrv-print-overlay')?.classList.contains('open')) bgrv_closePrintModal();
    });
});

function bgrv_closeModal() {
    $bgrv('bgrv-overlay').classList.remove('open');
    bgrv_closePrintModal();
}

// ── "Export to Excel" / "Preview and Print" (COB mode only) ──────────────────────────────
// fims.responsibility_code.office_type -> Regional / District / everything else — same mapping
// budget-allocation-form.php's cbaRcDeptType() uses for the same office_type values.
function bgrv_rcDeptType(officeType) {
    const t = String(officeType || '');
    if (t === 'Regional') return 'REGIONAL';
    if (t === 'District') return 'DISTRICT';
    return t ? 'MAIN' : '';
}

function bgrv_closePrintModal() {
    $bgrv('bgrv-print-overlay')?.classList.remove('open');
}

// A Regional / District user only ever works with their own single office/region, so "Per Region" (which
// groups by Region) and "Per Fund Source" are hidden for them (.bgrv-print-central-only, see the markup above)
// — but they still get the full "Per Program" (-> Project Level / Program Level) / "Detailed" choice, same as
// everyone else; nothing is auto-skipped anymore. BGR_USER_OFFICE_TYPE is defined by whichever host page
// included this file (budget-req-page.php or budget-prop-page.php), from the logged-in user's own
// responsibility_code row. format: 'xlsx' ("Export to Excel") or 'pdf' ("Preview and Print") — which button was clicked.
function bgrv_openPrintModal(format) {
    bgrv_printFormat = format === 'pdf' ? 'pdf' : 'xlsx';
    $bgrv('bgrv-print-modal-title').textContent = bgrv_printFormat === 'pdf' ? 'Preview and Print' : 'Export to Excel';
    bgrv_showPrintStep1();
    const deptType = bgrv_rcDeptType(typeof BGR_USER_OFFICE_TYPE !== 'undefined' ? BGR_USER_OFFICE_TYPE : '');
    const centralOnly = deptType !== 'REGIONAL' && deptType !== 'DISTRICT';
    document.querySelectorAll('.bgrv-print-central-only').forEach(el => { el.style.display = centralOnly ? '' : 'none'; });
    $bgrv('bgrv-print-overlay')?.classList.add('open');
}

// Step 1 <-> step 2 (the "Per Program" drill-down: Project vs Program) — see the markup above.
function bgrv_showPrintStep1() {
    $bgrv('bgrv-print-step2').style.display = 'none';
    $bgrv('bgrv-print-step1').style.display = '';
}
function bgrv_showPrintStep2() {
    $bgrv('bgrv-print-step1').style.display = 'none';
    $bgrv('bgrv-print-step2').style.display = '';
}

// mode: 'region', 'project', 'program' or 'detailed' — one of BudgetReqCobReportService::MODES. 'fund_source'
// is still a placeholder card (kept visible on purpose; no backend mode behind it yet) — falls through to the toast below.
function bgrv_printCob(mode) {
    bgrv_closePrintModal();
    const labels = { region: 'Per Region', project: 'Project', program: 'Program', detailed: 'Detailed' };
    const label = labels[mode];
    if (!label) {
        bgr_toast(`${bgrv_printFormat === 'pdf' ? 'Preview and Print' : 'Export to Excel'} (${mode}) is not yet implemented.`, 'info');
        return;
    }
    if (bgrv_printFormat === 'pdf') {
        bgrv_previewCobReport(mode, label);
    } else {
        bgrv_downloadCobReport(mode, label);
    }
}

// Shared by both formats: GET BGR_API + '/cob-print?mode=…&format=…' -> { success, filename, mime, data: base64 }.
// Same fiscal year / extra params (central, rc_code_from, …) the COB modal behind this picker is currently
// showing (bgrv_cobFiscalYr / bgrv_cobExtraParams, set by bgrv_openCobModal).
async function bgrv_fetchCobReport(mode, format) {
    const query = new URLSearchParams({ mode, format });
    if (bgrv_cobFiscalYr) query.set('fiscal_yr', bgrv_cobFiscalYr);
    Object.entries(bgrv_cobExtraParams || {}).forEach(([k, v]) => { if (v !== '' && v != null) query.set(k, v); });
    const res  = await fetch(`${BGR_API}/cob-print?${query}`, { credentials: 'same-origin' });
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to generate the report.');
    return json;
}

// "Export to Excel" — the base64 -> Blob -> anchor "download" pattern readone/cob-report-readone.php's own Excel export uses.
async function bgrv_downloadCobReport(mode, label) {
    window.PagePreloader?.show();
    try {
        const json = await bgrv_fetchCobReport(mode, 'xlsx');
        const bin = atob(json.data), bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const url = URL.createObjectURL(new Blob([bytes], { type: json.mime }));
        const a = document.createElement('a');
        a.href = url; a.download = json.filename;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// "Preview and Print" — same report, rendered server-side as a PDF (BudgetReqCobReportService::report(),
// format='pdf', via GlobalService::renderPdf()) instead of an xlsx. Same look as the REGIONAL app's budget
// allocation "Preview & Print" (app/routes/fmd/budget_allocation.php's /pdf-view/{filename}): a sized popup
// window (not a full blank tab) with an iframe filling it (toolbar hidden, #toolbar=0) showing the PDF in
// the browser's own viewer — that IS the preview — which then auto-triggers the native print dialog once
// loaded (with a timeout fallback for a slow viewer). Built entirely client-side (document.write into the
// popup) since the PDF here is already in hand as a blob: URL, with no need for that route's server-side
// temp-file + token round trip.
async function bgrv_previewCobReport(mode, label) {
    window.PagePreloader?.show();
    try {
        const json = await bgrv_fetchCobReport(mode, 'pdf');
        const bin = atob(json.data), bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const url = URL.createObjectURL(new Blob([bytes], { type: json.mime }));

        const win = window.open('', '_blank',
            `width=${Math.floor(screen.width / 2)},height=${screen.height},left=0,top=0,menubar=no,toolbar=no,location=no,status=no`);
        if (!win) {
            bgr_toast('Pop-up blocked. Please allow pop-ups for this site to preview the PDF.', 'warning');
            URL.revokeObjectURL(url);
            return;
        }
        // Deliberately no inline script tag in this markup: this template literal is itself inside the
        // tag this whole page's JS runs in, and the HTML parser has no notion of JS string nesting — it
        // just scans raw bytes for that tag's closing sequence to end it. Writing that sequence literally
        // here (even spelled out in a comment, which bit us once already) cuts this page's own script short
        // right there, dumping everything after it onto the page as plain text. The print-triggering logic
        // below is attached straight onto the popup's own DOM instead (frame.addEventListener(...)) —
        // nothing of that shape for the parser to misread.
        // #toolbar=0 hides the PDF viewer's own toolbar (so it reads as a clean preview, not a bare PDF
        // tab) — which also hides ITS print icon, so the only way back to the print dialog after the
        // auto-triggered one below is dismissed would otherwise be Ctrl+P. A small bar with its own Print
        // button sits above the iframe for that.
        win.document.open();
        win.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${bgr_esc(json.filename || label)}</title>
<style>*{margin:0;padding:0;box-sizing:border-box}html,body{height:100%;width:100%;overflow:hidden;background:#525659}
body{display:flex;flex-direction:column;font-family:Arial,Helvetica,sans-serif}
#pf-bar{flex:0 0 auto;display:flex;align-items:center;justify-content:flex-end;padding:8px 14px;background:#323639;}
#pf-print{display:flex;align-items:center;gap:6px;border:none;cursor:pointer;padding:0 16px;height:32px;border-radius:6px;
    font-size:13px;font-weight:600;font-family:inherit;background:#1a73e8;color:#fff;}
#pf-print:hover{background:#1765cc}
#pf-print svg{width:15px;height:15px;flex-shrink:0;}
#pf{flex:1 1 auto;display:block;width:100%;border:none;}</style>
</head><body>
<div id="pf-bar"><button type="button" id="pf-print"><svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.75A2.25 2.25 0 0015.75 1.5h-7.5A2.25 2.25 0 006 3.75v3.284" /></svg>Print</button></div>
<iframe id="pf" src="${url}#toolbar=0"></iframe>
</body></html>`);
        win.document.close();

        const frame = win.document.getElementById('pf');
        const doPrint = () => {
            try { frame.contentWindow.focus(); frame.contentWindow.print(); } catch (e) { /* popup may already be closing */ }
        };
        let autoPrinted = false;
        const autoPrint = () => {
            if (autoPrinted) return;
            autoPrinted = true;
            doPrint();
        };
        frame.addEventListener('load', () => setTimeout(autoPrint, 500));
        setTimeout(autoPrint, 3500);   // fallback in case the load event is slow/missed
        win.document.getElementById('pf-print')?.addEventListener('click', doPrint);
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// Replaces #bgrv-summary's cards wholesale — the two modes show entirely
// different stats (Fiscal Year/Status tied to a request vs. plain counts),
// so this is simpler than keeping two parallel hard-coded markup blocks.
// An empty array hides the row entirely (COB mode has no use for it).
function bgrv_setSummary(cards) {
    const el = $bgrv('bgrv-summary');
    el.style.display = cards.length ? '' : 'none';
    el.style.gridTemplateColumns = cards.length ? `repeat(${cards.length}, 1fr)` : '';
    el.innerHTML = cards.map(c => `
        <div class="bgrv-sum-card">
            <div class="bgrv-sum-label">${c.label}</div>
            <div class="bgrv-sum-val" id="${c.id}">${c.value ?? '—'}</div>
        </div>`).join('');
}

/* ── Mode 1: one Program's Sub-Programs -> Projects, with this request's
   own amounts annotated in ──────────────────────────────────────────── */
async function bgrv_openModal(requestContext, program) {
    $bgrv('bgrv-card').classList.remove('bgrv-wide', 'u-modal-w-80'); $bgrv('bgrv-card').classList.add('u-modal-lg'); // COB mode only — see bgrv_openCobModal
    $bgrv('bgrv-hdr-tag').textContent = 'Program';
    $bgrv('bgrv-modal-title').textContent = `${program.program_code} — ${program.program_name}`;
    $bgrv('bgrv-sec-hd-label').textContent = 'Sub-Programs';
    $bgrv('bgrv-sec-hd-total').textContent = '';
    $bgrv('bgrv-header-total').classList.remove('show'); // COB mode's headline figure only
    bgrv_setSummary([
        { id: 'bgrv-sum-fiscal-yr',    label: 'Fiscal Year',     value: bgr_esc(requestContext.fiscal_yr ?? '—') },
        { id: 'bgrv-sum-status',      label: 'Status',           value: bgr_statusBadge(requestContext.status_name, requestContext.status_color) },
        { id: 'bgrv-sum-subprograms', label: '# Sub-Programs',   value: '—' },
        { id: 'bgrv-sum-projects',    label: '# Projects',       value: '—' },
    ]);
    $bgrv('bgrv-panels').innerHTML = '';
    $bgrv('bgrv-empty').textContent = 'This program has no sub-programs yet.';
    $bgrv('bgrv-empty').style.display = 'none';
    $bgrv('bgrv-overlay').classList.add('open');

    // project_code -> proposed amount, summed from this request's own
    // chosen projects/line items — independent of the master-data fetch below.
    const projectAmounts = new Map();
    (program.projects ?? []).forEach(p => {
        const subtotal = (p.line_items ?? []).reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0);
        projectAmounts.set(p.project_code, subtotal);
    });

    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ program_code: program.program_code });
        const [subRes, projRes] = await Promise.all([
            fetch(`${BGR_SUB_PROGRAM_API}?${params}`, { credentials: 'same-origin' }),
            fetch(`${BGR_PROJECT_BASE_API}/all?${params}`, { credentials: 'same-origin' }),
        ]);
        const subJson  = await subRes.json();
        const projJson = await projRes.json();
        if (!subJson.success) throw new Error(subJson.message || 'Failed to load sub-programs.');
        if (!projJson.success) throw new Error(projJson.message || 'Failed to load projects.');

        const subPrograms = subJson.data ?? [];
        const projects    = projJson.data ?? [];
        $bgrv('bgrv-panels').innerHTML = subPrograms.length
            ? bgrv_subProgramAccordionHtml(subPrograms, projects, projectAmounts, true)
            : '';
        $bgrv('bgrv-empty').style.display = subPrograms.length ? 'none' : '';
        $bgrv('bgrv-sum-subprograms').textContent = subPrograms.length;
        $bgrv('bgrv-sum-projects').textContent = projects.length;
    } catch (e) {
        $bgrv('bgrv-panels').innerHTML = '';
        $bgrv('bgrv-empty').style.display = '';
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Mode 2: every Program/Sub-Program/Project as a flat indented outline
   — no request context, no amounts, nothing to click ─────────────────── */
// fiscalYr: '' = every fiscal year (the page filter's "All Years" option).
// Scoped through the actual budget requests (/budget_req/all), not raw
// Program/Sub-Program/Project master data — so this only ever shows what's
// really been budgeted for that year (under the caller's own RC scope,
// same as the list page), never a Program/Project that happens to exist in
// master data but was never added to any request.
// extraParams: more query parameters for GET /budget_req/all. The Budget Requirements list passes none (it is the caller's own RC scope);
// the FMD's Budget Proposal list (budget-prop-page.php) passes { central: '1', rc_code_from } so the outline covers every Regional / District
// office — the API honours central=1 only for the central review RC, so it widens nothing for anyone else.
// options.byUnit: one block per ORIGINATING UNIT (every request the fiscal year has, whatever its status, each with its own Programs /
// Sub-Programs / Projects and total) instead of one outline folded across all of them. The FMD page uses it: it is the only view
// that says which office a project belongs to, and it keeps a request that has no Program yet on the list.
async function bgrv_openCobModal(fiscalYr = '', extraParams = {}, options = {}) {
    bgrv_cobFiscalYr = fiscalYr || '';
    bgrv_cobExtraParams = extraParams || {};
    const byUnit = !!(options && options.byUnit);
    const showFund = !!(options && options.showFund);
    $bgrv('bgrv-card').classList.add('bgrv-wide', 'u-modal-w-80'); $bgrv('bgrv-card').classList.remove('u-modal-lg');
    $bgrv('bgrv-hdr-tag').textContent = 'Corporate Operating Budget (COB)';
    const what = byUnit ? 'All Originating Units' : 'All Programs';
    $bgrv('bgrv-modal-title').textContent = fiscalYr ? `${what} — FY ${bgr_esc(fiscalYr)}` : `${what} — All Years`;
    $bgrv('bgrv-sec-hd-label').textContent = byUnit ? 'Originating Units' : 'CAPITAL OUTLAY';
    $bgrv('bgrv-sec-hd-total').textContent = '';
    $bgrv('bgrv-header-total-proposed-val').textContent = '—';
    $bgrv('bgrv-header-total-val').textContent = '—';
    $bgrv('bgrv-header-total-adjustment-val').textContent = '—';
    $bgrv('bgrv-header-total-revised-val').textContent = '—';
    $bgrv('bgrv-header-total').classList.add('show');
    bgrv_setSummary([]);
    $bgrv('bgrv-panels').innerHTML = '';
    $bgrv('bgrv-empty').textContent = byUnit ? 'No budget requests found.' : 'No programs found.';
    $bgrv('bgrv-empty').style.display = 'none';
    $bgrv('bgrv-overlay').classList.add('open');

    window.PagePreloader?.show();
    try {
        const query = new URLSearchParams();
        if (fiscalYr) query.set('fiscal_yr', fiscalYr);
        Object.entries(extraParams || {}).forEach(([k, v]) => { if (v !== '' && v != null) query.set(k, v); });
        const params = query.toString() ? `?${query}` : '';
        const res  = await fetch(`${BGR_API}/all${params}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget requests.');

        const headers = json.data ?? [];

        if (byUnit) {
            // Region first (a District office under its Regional office), then the unit, then the request: every request of the
            // fiscal year is a block, so a unit that has not added a Program yet is still on the list.
            const key = h => [h.rc_parent_name || h.rc_name || '', h.rc_code || '', h.budget_reference_number || ''];
            const units = [...headers].sort((x, y) => {
                const kx = key(x), ky = key(y);
                for (let i = 0; i < kx.length; i++) {
                    const c = String(kx[i]).localeCompare(String(ky[i]), undefined, { numeric: true, sensitivity: 'base' });
                    if (c) return c;
                }
                return 0;
            });
            let grandTotal = { proposed: 0, approved: 0, adjustment: 0, revised: 0 }, programCount = 0, subProgramCount = 0, projectCount = 0, coeLineItemCount = 0;
            const unitsHtml = units.map(h => {
                const c = bgrv_cobCollect([h], showFund);
                grandTotal.proposed += c.grandTotal.proposed;
                grandTotal.approved += c.grandTotal.approved;
                grandTotal.adjustment += c.grandTotal.adjustment;
                grandTotal.revised += c.grandTotal.revised;
                programCount += c.programs.length; subProgramCount += c.subProgramCount; projectCount += c.projectCount; coeLineItemCount += c.coeLineItemCount;
                const where = [h.rc_parent_name, h.budget_reference_number].filter(Boolean).map(bgr_esc).join(' · ');
                return `<div class="bgrv-cob-row bgrv-cob-unit" onclick="bgrv_toggleUnit(this)">
                            <span class="bgrv-cob-unit-label">
                                <svg class="bgrv-cob-unit-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                <span>${bgr_esc(h.rc_name || h.rc_id || '—')}${h.rc_code ? ` <code class="bgrv-cob-unit-code">${bgr_esc(h.rc_code)}</code>` : ''}
                                    ${where ? `<small>${where}</small>` : ''}</span>
                            </span>
                            <span class="bgrv-cob-unit-meta">${bgr_statusBadge(h.status_name, h.status_color)}
                                <span class="bgrv-cob-amts">
                                    <span class="bgrv-cob-amt">${bgr_fmtMoney(c.grandTotal.proposed)}</span>
                                    <span class="bgrv-cob-amt">${bgr_fmtMoney(c.grandTotal.approved)}</span>
                                    <span class="bgrv-cob-amt">${bgr_fmtMoney(c.grandTotal.adjustment)}</span>
                                    <span class="bgrv-cob-amt">${bgr_fmtMoney(c.grandTotal.revised)}</span>
                                </span></span>
                        </div>
                        <div class="bgrv-cob-nest bgrv-collapsed">${bgrv_cobUnitNestHtml(c)}</div>`;
            }).join('');

            $bgrv('bgrv-header-total-proposed-val').textContent = bgr_fmtMoney(grandTotal.proposed);
            $bgrv('bgrv-header-total-val').textContent = bgr_fmtMoney(grandTotal.approved);
            $bgrv('bgrv-header-total-adjustment-val').textContent = bgr_fmtMoney(grandTotal.adjustment);
            $bgrv('bgrv-header-total-revised-val').textContent = bgr_fmtMoney(grandTotal.revised);
            $bgrv('bgrv-panels').innerHTML = unitsHtml ? `<div class="bgrv-cob-list">${bgrv_cobColumnHeaderHtml()}${unitsHtml}</div>` : '';
            $bgrv('bgrv-empty').style.display = units.length ? 'none' : '';
            $bgrv('bgrv-sec-hd-total').textContent =
                `${units.length} originating unit(s) · ${programCount} program(s) · ${subProgramCount} sub-program(s) · ${projectCount} project(s)`
                + (coeLineItemCount ? ` · ${coeLineItemCount} COE line item(s)` : '');
            return;
        }

        // Folded across every header this fetch returned — more than one when "All Years" is picked, or when the caller's RC scope
        // spans several descendant rc_ids for the same year. A Program (or Sub-Program) added more than once collapses to a single
        // row; its Projects and the grand total still count every occurrence.
        const c = bgrv_cobCollect(headers, showFund);
        $bgrv('bgrv-header-total-proposed-val').textContent = bgr_fmtMoney(c.grandTotal.proposed);
        $bgrv('bgrv-header-total-val').textContent = bgr_fmtMoney(c.grandTotal.approved);
        $bgrv('bgrv-header-total-adjustment-val').textContent = bgr_fmtMoney(c.grandTotal.adjustment);
        $bgrv('bgrv-header-total-revised-val').textContent = bgr_fmtMoney(c.grandTotal.revised);

        // Two independent tables: "CAPITAL OUTLAY" (the Program -> Sub-Program -> Project tree) and
        // "CORPORATE OPERATING EXPENDITURE" (the PS/MOOE rows). Either can be empty on its own without
        // affecting the other. Their section labels sit inside each table's own blue column-header bar
        // (bgrv_cobColumnHeaderHtml), aligned with the amount columns, rather than as a heading above it —
        // the static #bgrv-sec-hd-label row above #bgrv-panels is left blank here, keeping just its counts.
        $bgrv('bgrv-sec-hd-label').textContent = '';
        $bgrv('bgrv-sec-hd-total').textContent =
            `${c.programs.length} program(s) · ${c.subProgramCount} sub-program(s) · ${c.projectCount} project(s)`;
        const coTableHtml = c.programsHtml
            ? `<div class="bgrv-cob-list">${bgrv_cobColumnHeaderHtml('CAPITAL OUTLAY')}${c.programsHtml}${bgrv_cobTotalRowHtml(c.coTotal)}</div>`
            : '';
        const coeSectionHtml = c.coeLineItemCount
            ? `<p class="bgrv-sec-hd" style="margin-top:22px;">
                    <span></span>
                    <span class="bgrv-sec-hd-total">${c.coeLineItemCount} line item(s)</span>
               </p>
               <div class="bgrv-cob-list">${bgrv_cobColumnHeaderHtml('CORPORATE OPERATING EXPENDITURE')}${c.coeHtml}${bgrv_cobTotalRowHtml(c.coeTotal)}</div>`
            : '';
        $bgrv('bgrv-panels').innerHTML = coTableHtml + coeSectionHtml;
        $bgrv('bgrv-empty').style.display = c.programs.length ? 'none' : '';
    } catch (e) {
        $bgrv('bgrv-panels').innerHTML = '';
        $bgrv('bgrv-empty').style.display = '';
        $bgrv('bgrv-sec-hd-total').textContent = '';
        $bgrv('bgrv-header-total-proposed-val').textContent = '—';
        $bgrv('bgrv-header-total-val').textContent = '—';
        $bgrv('bgrv-header-total-adjustment-val').textContent = '—';
        $bgrv('bgrv-header-total-revised-val').textContent = '—';
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// bgrv_openCobModal: folds the given budget requests (headers) into Program -> Sub-Program -> Project rows. Returns the rows' HTML
// and the counts / totals they add up to. A Program (or Sub-Program) that appears in more than one header collapses to a single row; its
// Projects and the totals still count every occurrence.
// Each row/total carries FOUR amounts: proposed (the "Proposed Amount" column — budget_line_items.proposed_amount, the
// office's own original ask, set when the request is submitted) and approved (the "Approved Amount" column —
// budget_line_items.suggested_amount, 0 until Budget Processing sets one; see bgrv_approvedAmount below). suggested_amount
// is now a STABLE figure (migration "51 - budget_line_items_stable_suggested_amount.sql" — no CBA/CFA/RFA/LAA approval or
// deletion, and no Budget Revision, ever moves it again; see BudgetLineBalanceSql's class doc), so reading it here needs no
// further adjustment — unlike budget-allocation-form.php's "Available Amount"
// (cobAvailable()), which is a genuinely live, derived ceiling and is NOT what this column shows. adjustment
// (budget_line_items.adjustment — the net adjustment applied by an approved Budget Revision; 0 until one is
// approved), and revised — approved PLUS adjustment (adjustment already carries its own sign: negative for a cut,
// positive for an increase), computed here (budget_line_items.revised_amount, now generated by the table as suggested_amount + adjustment —
// 03 - alter_table/54 — would give the same number). Same three figures BudgetReqCobReportService's Print COB exports read
// (proposed/approved/amended), just with the net adjustment also broken out as its own column here.
// showFund: each Project row also lists the Type(s) of Fund of its line items (set by Budget Processing; a Project can have several, and
// none before it has been processed).
// Same account_code-prefix rule DisbursementVoucherService::deriveExpenseClass() uses — chart_of_accounts
// carries no stored expense-class column, so this is derived, not looked up, same as there. A COE line item
// is always either PS or MOOE — null for anything else (excluded from the PS/MOOE breakdown, though still
// counted in the COE bucket's own overall total).
function bgrv_deriveExpenseClass(accountCode) {
    const code = String(accountCode || '');
    if (code.startsWith('5-01')) return 'PS';
    if (code.startsWith('5-02')) return 'MOOE';
    return null;
}

// The "Approved Amount" of one line item: budget_line_items.suggested_amount, straight — 0 until Budget
// Processing sets one. No fallback to proposed_amount (there used to be one; see the bgrv_cobCollect doc
// comment above) — matches BudgetReqCobReportService's Print COB exports, which read the same column the
// same way, so they no longer disagree on an unprocessed line.
function bgrv_approvedAmount(li) {
    return Number(li.suggested_amount) || 0;
}

// The "Proposed Amount" of one line item: budget_line_items.proposed_amount — the office's own original
// ask, set when the request is submitted and never touched by Budget Processing, CBA/RFA/LAA or Budget
// Revision (those only ever move suggested_amount / adjustment). Same column BudgetReqCobReportService's
// Print COB exports read for their own "Proposed Amount" column.
function bgrv_proposedAmount(li) {
    return Number(li.proposed_amount) || 0;
}

function bgrv_cobCollect(headers, showFund = false) {
    const programMap    = new Map(); // program_code -> { program_code, program_name }
    const subByProgram  = new Map(); // program_code -> Map(sub_program_code -> { sub_program_code, sub_program_name })
    const projByProgram = new Map(); // program_code -> [{ project_code, project_name, sub_program_code, proposed, approved, adjustment, revised, funds }, ...]
    const programTotals = new Map(); // program_code -> { proposed, approved, adjustment, revised }
    const grandTotal = { proposed: 0, approved: 0, adjustment: 0, revised: 0 };
    // PS / MOOE sub-totals making up the COE table (bgrv_cobCoeClassRowsHtml) — there's no
    // overall "COE total" row anymore (see bgrv_openCobModal), only these two.
    const coeByClass = {
        PS: { count: 0, proposed: 0, approved: 0, adjustment: 0, revised: 0 },
        MOOE: { count: 0, proposed: 0, approved: 0, adjustment: 0, revised: 0 },
    };
    let projectCount = 0;
    let coeLineItemCount = 0;

    // COE headers have no Program/Sub-Program/Project tree at all — folded into coeByClass
    // instead of the Program map below, and still counted into grandTotal.
    headers.forEach(h => {
        (h.coe_line_items || []).forEach(li => {
            const proposed   = bgrv_proposedAmount(li);
            const approved   = bgrv_approvedAmount(li);
            const adjustment = Number(li.adjustment) || 0;
            const revised    = approved + adjustment;
            grandTotal.proposed += proposed;
            grandTotal.approved += approved;
            grandTotal.adjustment += adjustment;
            grandTotal.revised += revised;
            coeLineItemCount++;

            // null (neither PS nor MOOE) is still counted in coeTotal/grandTotal above — just not
            // broken into its own sub-row, since bgrv_deriveExpenseClass() no longer has an "Other" bucket.
            const cls = coeByClass[bgrv_deriveExpenseClass(li.account_code)];
            if (cls) {
                cls.count++;
                cls.proposed += proposed;
                cls.approved += approved;
                cls.adjustment += adjustment;
                cls.revised += revised;
            }
        });
    });

    headers.forEach(h => {
        (h.programs || []).forEach(prog => {
            if (!programMap.has(prog.program_code)) {
                programMap.set(prog.program_code, { program_code: prog.program_code, program_name: prog.program_name });
                subByProgram.set(prog.program_code, new Map());
                projByProgram.set(prog.program_code, []);
                programTotals.set(prog.program_code, { proposed: 0, approved: 0, adjustment: 0, revised: 0 });
            }
            if (prog.sub_program_code) {
                subByProgram.get(prog.program_code).set(prog.sub_program_code, {
                    sub_program_code: prog.sub_program_code,
                    sub_program_name: prog.sub_program_name,
                });
            }
            (prog.projects || []).forEach(p => {
                const proposed   = (p.line_items || []).reduce((sum, li) => sum + bgrv_proposedAmount(li), 0);
                const approved   = (p.line_items || []).reduce((sum, li) => sum + bgrv_approvedAmount(li), 0);
                const adjustment = (p.line_items || []).reduce((sum, li) => sum + (Number(li.adjustment) || 0), 0);
                const revised    = approved + adjustment;
                // distinct Types of Fund across the project's line items, in line-item order
                const funds = [...new Set((p.line_items || []).map(li => String(li.type_of_fund_name ?? '').trim()).filter(Boolean))];
                projByProgram.get(prog.program_code).push({
                    project_code: p.project_code,
                    project_name: p.project_name,
                    sub_program_code: prog.sub_program_code,
                    proposed,
                    approved,
                    adjustment,
                    revised,
                    funds,
                });
                const totals = programTotals.get(prog.program_code);
                totals.proposed += proposed;
                totals.approved += approved;
                totals.adjustment += adjustment;
                totals.revised += revised;
                projectCount++;
                grandTotal.proposed += proposed;
                grandTotal.approved += approved;
                grandTotal.adjustment += adjustment;
                grandTotal.revised += revised;
            });
        });
    });

    const programs = Array.from(programMap.values());
    const subProgramCount = Array.from(subByProgram.values()).reduce((sum, m) => sum + m.size, 0);
    // CO (Program -> Sub-Program -> Project tree) and COE (flat PS/MOOE rows) are two separate
    // tables now — see bgrv_openCobModal, which titles them "CO" and "COE" — so each gets its own
    // HTML instead of one combined blob.
    const programsHtml = programs.map(prog => {
        const progSubPrograms = Array.from(subByProgram.get(prog.program_code).values());
        const progProjects    = projByProgram.get(prog.program_code) || [];
        const totals = programTotals.get(prog.program_code) || { proposed: 0, approved: 0, adjustment: 0, revised: 0 };
        return `<div class="bgrv-cob-row bgrv-cob-program" onclick="bgrv_toggleDisclosure(this, 'bgrv-cob-program-body')">
                    <span class="bgrv-cob-row-label">
                        <svg class="bgrv-cob-disclosure-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        ${bgr_esc(prog.program_code)} — ${bgr_esc(prog.program_name)}
                    </span>
                    <span class="bgrv-cob-amts">
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.proposed)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.approved)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.adjustment)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.revised)}</span>
                    </span>
                </div>
                <div class="bgrv-cob-program-body bgrv-collapsed">${bgrv_cobRowsHtml(progSubPrograms, progProjects, showFund)}</div>`;
    }).join('');
    const coeHtml = bgrv_cobCoeClassRowsHtml(coeByClass);

    // CO's own total (every Program summed) and COE's own total (PS + MOOE summed) — each
    // table's Total row (see bgrv_openCobModal) is scoped to just that table, not the combined
    // grandTotal (which is the whole modal's header banner figure, CO + COE together).
    const sumAmts = list => list.reduce((acc, t) => {
        acc.proposed += t.proposed; acc.approved += t.approved; acc.adjustment += t.adjustment; acc.revised += t.revised;
        return acc;
    }, { proposed: 0, approved: 0, adjustment: 0, revised: 0 });
    const coTotal = sumAmts(Array.from(programTotals.values()));
    const coeTotal = sumAmts(Object.values(coeByClass));

    return { programs, subProgramCount, projectCount, coeLineItemCount, grandTotal, coTotal, coeTotal, programsHtml, coeHtml };
}

// bgrv_openCobModal (options.byUnit only): one originating unit's nested content — its Programs grouped
// under a "CAPITAL OUTLAY" label and its PS/MOOE rows grouped under a "CORPORATE OPERATING EXPENDITURE"
// label (bgrv-cob-group-label; see that CSS rule for the deeper indent this gives their children), mirroring
// the two separate CO/COE tables bgrv_openCobModal's folded (non-byUnit) view builds. Each label is its own
// collapse/expand toggle (bgrv_toggleGroup) for just its own rows (bgrv-cob-group-body), collapsed by
// default — opening the unit row reveals the labels, not the rows underneath them, until clicked. Either
// label is skipped when that unit has nothing of its own kind; both skipped (header has neither) falls
// back to the same "No programs added yet." placeholder the folded view's empty state uses.
function bgrv_cobUnitNestHtml(c) {
    const groupHtml = (label, bodyHtml) => `<div class="bgrv-cob-row bgrv-cob-group-label" onclick="bgrv_toggleGroup(this)">
            <span class="bgrv-cob-group-label-text">
                <svg class="bgrv-cob-group-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                ${label}
            </span>
        </div>
        <div class="bgrv-cob-group-body bgrv-collapsed">${bodyHtml}</div>`;
    const coHtml  = c.programs.length      ? groupHtml('CAPITAL OUTLAY', c.programsHtml) : '';
    const coeHtml = c.coeLineItemCount     ? groupHtml('CORPORATE OPERATING EXPENDITURE', c.coeHtml) : '';
    return (coHtml + coeHtml) || '<div class="bgrv-cob-row bgrv-cob-project bgrv-cob-empty">No programs added yet.</div>';
}

// COE's own table rows — PS and MOOE, each a top-level row (same style as a Program row, since
// there's no higher grouping above them here) with its own Proposed/Approved/Adjustment/Revised. A class
// with no line items in it is skipped rather than shown as ₱0.00.
function bgrv_cobCoeClassRowsHtml(coeByClass) {
    const labels = { PS: 'PS', MOOE: 'MOOE' };
    return Object.keys(labels).map(key => {
        const c = coeByClass[key];
        if (!c.count) return '';
        return `<div class="bgrv-cob-row bgrv-cob-program">
                    <span>${bgr_esc(labels[key])} <span style="font-weight:500;font-size:11px;color:var(--afims-text-muted, #62657E);">(${c.count} line item(s))</span></span>
                    <span class="bgrv-cob-amts">
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(c.proposed)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(c.approved)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(c.adjustment)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(c.revised)}</span>
                    </span>
                </div>`;
    }).join('');
}

// The closing "Total" row of one table (CO's or COE's own — see bgrv_openCobModal) — scoped to
// just that table, not the modal's combined grand total (the header banner figure).
function bgrv_cobTotalRowHtml(totals) {
    return `<div class="bgrv-cob-row bgrv-cob-total">
                <span>Total</span>
                <span class="bgrv-cob-amts">
                    <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.proposed)}</span>
                    <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.approved)}</span>
                    <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.adjustment)}</span>
                    <span class="bgrv-cob-amt">${bgr_fmtMoney(totals.revised)}</span>
                </span>
            </div>`;
}

// bgrv_openCobModal: the "Proposed Amount" / "Approved Amount" / "Amendment" / "Amended Amount" column-header row shown above the outline.
// label: the table's own section label ("CAPITAL OUTLAY" / "CORPORATE OPERATING EXPENDITURE"), shown on the
// same blue bar, aligned with those amount columns instead of as a separate heading above it.
function bgrv_cobColumnHeaderHtml(label = '') {
    return `<div class="bgrv-cob-row bgrv-cob-col-headers">
                <span class="bgrv-cob-sec-label">${bgr_esc(label)}</span>
                <span class="bgrv-cob-amts">
                    <span class="bgrv-cob-amt-label">Proposed Amount</span>
                    <span class="bgrv-cob-amt-label">Approved Amount</span>
                    <span class="bgrv-cob-amt-label">Amendment</span>
                    <span class="bgrv-cob-amt-label">Amended Amount</span>
                </span>
            </div>`;
}

// bgrv_openModal only: groups the given Projects by sub_program_code — a
// project with no match (including a null/empty sub_program_code) falls
// into a trailing "No Sub-Program" group rather than silently disappearing.
function bgrv_subProgramAccordionHtml(subPrograms, projects, projectAmounts, showAmounts) {
    const bySubProgram = new Map();
    subPrograms.forEach(sp => bySubProgram.set(sp.sub_program_code, []));

    const unassigned = [];
    projects.forEach(p => {
        const key = p.sub_program_code;
        if (key && bySubProgram.has(key)) {
            bySubProgram.get(key).push(p);
        } else {
            unassigned.push(p);
        }
    });

    const groups = subPrograms.map(sp => ({
        label: `${bgr_esc(sp.sub_program_code)} — ${bgr_esc(sp.sub_program_name)}`,
        projects: bySubProgram.get(sp.sub_program_code) || [],
    }));
    if (unassigned.length) {
        groups.push({ label: 'No Sub-Program', projects: unassigned });
    }

    const panelsHtml = groups.map((g, idx) => bgrv_subProgramPanelHtml(g.label, g.projects, idx === 0, projectAmounts, showAmounts));
    return `<div class="bgrv-panel-list">${panelsHtml.join('')}</div>`;
}

function bgrv_subProgramPanelHtml(label, projectsList, openByDefault, projectAmounts, showAmounts) {
    let subtotal = 0;
    const bodyHtml = projectsList.length
        ? `<div class="bgrv-proj-list">${projectsList.map(p => {
            let amtHtml = '';
            if (showAmounts) {
                const amount = projectAmounts.get(p.project_code) ?? 0;
                subtotal += amount;
                amtHtml = `<span class="bgrv-proj-amt">${bgr_fmtMoney(amount)}</span>`;
            }
            return `<div class="bgrv-proj-row">
                <span class="bgrv-proj-code">${bgr_esc(p.project_code)}</span>
                <span class="bgrv-proj-name">${bgr_esc(p.project_name)}</span>
                ${amtHtml}
            </div>`;
        }).join('')}</div>`
        : '<div class="bgrv-proj-empty">No projects added to this sub-program yet.</div>';

    const metaAmtHtml = showAmounts ? `<span class="bgrv-panel-meta-amt">${bgr_fmtMoney(subtotal)}</span>` : '';

    return `<div class="bgrv-panel${openByDefault ? ' open' : ''}">
        <div class="bgrv-panel-hd" onclick="bgrv_togglePanel(this)">
            <div class="bgrv-panel-hd-main">
                <svg class="bgrv-panel-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <div class="bgrv-panel-title">${label}</div>
            </div>
            <div class="bgrv-panel-hd-meta">
                <span class="bgrv-panel-meta-item">${projectsList.length} project(s)</span>
                ${metaAmtHtml}
            </div>
        </div>
        <div class="bgrv-panel-body">${bodyHtml}</div>
    </div>`;
}

// Exclusive accordion, scoped to the clicked panel's own immediate list (bgrv_openModal only).
function bgrv_togglePanel(headerEl) {
    const panel = headerEl.closest('.bgrv-panel');
    const list  = headerEl.closest('.bgrv-panel-list');
    Array.from(list.children).forEach(p => {
        if (p === panel) {
            p.classList.toggle('open');
        } else {
            p.classList.remove('open');
        }
    });
}

// bgrv_openCobModal (options.byUnit only): expands rowEl's next sibling — its .bgrv-cob-nest (the unit
// row) or .bgrv-cob-group-body (the "CAPITAL OUTLAY" / "CORPORATE OPERATING EXPENDITURE" group label
// row) — identified by nestClass, since both are collapsible the same way. Animated via max-height: since
// the sibling's natural height isn't known in CSS (a variable number of rows inside it), this pins an
// explicit pixel value to transition from — 0 -> scrollHeight — then, once the transition ends, releases
// max-height back to the CSS default so it isn't stuck at a stale pixel value if the content's own height
// changes. The `e.target !== nest` check matters because a group-body nested inside a unit's own nest can
// be animating at the same time — without it, a transitionend bubbling up from that inner element would be
// mistaken for this element's own.
function bgrv_expandDisclosure(rowEl, nestClass) {
    const nest = rowEl.nextElementSibling;
    if (!nest || !nest.classList.contains(nestClass) || !nest.classList.contains('bgrv-collapsed')) return;
    rowEl.classList.add('open');
    nest.classList.remove('bgrv-collapsed');
    const target = nest.scrollHeight;
    nest.style.maxHeight = '0px';
    nest.offsetHeight;   // force reflow so the 0px start is applied before animating to target
    nest.style.maxHeight = target + 'px';
    nest.addEventListener('transitionend', function onEnd(e) {
        if (e.target !== nest || e.propertyName !== 'max-height') return;
        nest.style.maxHeight = '';
        nest.removeEventListener('transitionend', onEnd);
    });
}

// The reverse of bgrv_expandDisclosure — same pin-then-animate-to-0 approach, re-adding .bgrv-collapsed once done.
function bgrv_collapseDisclosure(rowEl, nestClass) {
    const nest = rowEl.nextElementSibling;
    if (!nest || !nest.classList.contains(nestClass) || nest.classList.contains('bgrv-collapsed')) return;
    rowEl.classList.remove('open');
    const target = nest.scrollHeight;
    nest.style.maxHeight = target + 'px';
    nest.offsetHeight;   // force reflow so the pinned height is applied before animating to 0
    nest.style.maxHeight = '0px';
    nest.addEventListener('transitionend', function onEnd(e) {
        if (e.target !== nest || e.propertyName !== 'max-height') return;
        nest.classList.add('bgrv-collapsed');
        nest.style.maxHeight = '';
        nest.removeEventListener('transitionend', onEnd);
    });
}

// Exclusive accordion across every originating unit in the same outline (like bgrv_togglePanel, but
// scoped to the whole .bgrv-cob-list rather than one panel's immediate list): opening a unit collapses
// whichever other unit was open, so only one originating unit's Programs/Projects are expanded at a time.
function bgrv_toggleUnit(rowEl) {
    if (rowEl.classList.contains('open')) {
        bgrv_collapseDisclosure(rowEl, 'bgrv-cob-nest');
        return;
    }
    rowEl.closest('.bgrv-cob-list')?.querySelectorAll('.bgrv-cob-unit.open').forEach(other => {
        if (other !== rowEl) bgrv_collapseDisclosure(other, 'bgrv-cob-nest');
    });
    bgrv_expandDisclosure(rowEl, 'bgrv-cob-nest');
}

// Independent (non-exclusive) collapse/expand toggle for rowEl's own next-sibling disclosure, identified
// by nestClass — used wherever opening one row has no business closing a sibling of the same kind: a
// unit's "CAPITAL OUTLAY" / "CORPORATE OPERATING EXPENDITURE" group labels (bgrv-cob-group-body), a
// Program row's own Sub-Programs (bgrv-cob-program-body), and a Sub-Program row's own Projects
// (bgrv-cob-subprogram-body). Unlike bgrv_toggleUnit (exclusive across every unit in the outline), a
// reviewer may want several Programs — or several Sub-Programs under different Programs — open at once.
function bgrv_toggleDisclosure(rowEl, nestClass) {
    if (rowEl.classList.contains('open')) {
        bgrv_collapseDisclosure(rowEl, nestClass);
    } else {
        bgrv_expandDisclosure(rowEl, nestClass);
    }
}

// A unit's own "CAPITAL OUTLAY" / "CORPORATE OPERATING EXPENDITURE" group label.
function bgrv_toggleGroup(rowEl) {
    bgrv_toggleDisclosure(rowEl, 'bgrv-cob-group-body');
}

// bgrv_openCobModal (showFund): "Type of Fund: [chip] [chip]" for a Project row; "—" while none of its line items has one yet.
function bgrv_cobFundHtml(funds) {
    const chips = (funds || []).map(f => `<span class="bgrv-cob-fund">${bgr_esc(f)}</span>`).join('');
    return `<span class="bgrv-cob-fund-wrap"><span class="bgrv-cob-fund-label">Type of Fund:</span>${chips || '<span class="bgrv-cob-fund-none">—</span>'}</span>`;
}

// bgrv_openCobModal only: flat indented rows (Sub-Program, then its
// Projects) for one Program — same sub_program_code grouping rule as
// bgrv_subProgramAccordionHtml above, just rendered as plain rows instead
// of accordion panels. showFund adds the Project's Type(s) of Fund after its name.
function bgrv_cobRowsHtml(subPrograms, projects, showFund = false) {
    const bySubProgram = new Map();
    subPrograms.forEach(sp => bySubProgram.set(sp.sub_program_code, []));

    const unassigned = [];
    projects.forEach(p => {
        const key = p.sub_program_code;
        if (key && bySubProgram.has(key)) {
            bySubProgram.get(key).push(p);
        } else {
            unassigned.push(p);
        }
    });

    const groups = subPrograms.map(sp => ({
        label: `${bgr_esc(sp.sub_program_code)} — ${bgr_esc(sp.sub_program_name)}`,
        projects: bySubProgram.get(sp.sub_program_code) || [],
    }));
    if (unassigned.length) {
        groups.push({ label: 'No Sub-Program', projects: unassigned });
    }
    if (!groups.length) {
        return '<div class="bgrv-cob-row bgrv-cob-subprogram bgrv-cob-empty">No sub-programs under this program yet.</div>';
    }

    return groups.map(g => {
        const subTotal = g.projects.reduce((acc, p) => {
            acc.proposed += Number(p.proposed) || 0;
            acc.approved += Number(p.approved) || 0;
            acc.adjustment += Number(p.adjustment) || 0;
            acc.revised += Number(p.revised) || 0;
            return acc;
        }, { proposed: 0, approved: 0, adjustment: 0, revised: 0 });
        const projRows = g.projects.length
            ? g.projects.map(p => `<div class="bgrv-cob-row bgrv-cob-project">
                    <span>${bgr_esc(p.project_code)} — ${bgr_esc(p.project_name)}${showFund ? bgrv_cobFundHtml(p.funds) : ''}</span>
                    <span class="bgrv-cob-amts">
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(p.proposed || 0)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(p.approved || 0)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(p.adjustment || 0)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(p.revised || 0)}</span>
                    </span>
                </div>`).join('')
            : '<div class="bgrv-cob-row bgrv-cob-project bgrv-cob-empty">No projects yet.</div>';
        return `<div class="bgrv-cob-row bgrv-cob-subprogram" onclick="bgrv_toggleDisclosure(this, 'bgrv-cob-subprogram-body')">
                    <span class="bgrv-cob-row-label">
                        <svg class="bgrv-cob-disclosure-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                        ${g.label}
                    </span>
                    <span class="bgrv-cob-amts">
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(subTotal.proposed)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(subTotal.approved)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(subTotal.adjustment)}</span>
                        <span class="bgrv-cob-amt">${bgr_fmtMoney(subTotal.revised)}</span>
                    </span>
                </div>
                <div class="bgrv-cob-subprogram-body bgrv-collapsed">${projRows}</div>`;
    }).join('');
}
</script>
