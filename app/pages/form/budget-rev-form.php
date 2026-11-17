<?php

namespace PHPMaker2024\FIMS;

$_brv_form_api = Config('API.FIMS_URL');
$user_id = CurrentUserID();

// Who is filing: the current user's office. uac.users gives only the user's rc_id, LEFT JOINed to fims.responsibility_code for the RC
// itself. The Financial Management Department (FMD: rc_code 35-074-0000000-032, rc_id 013 in this data, a Department) is not an
// originating office with budgets of its own — it files revisions / realignments for ANY office's approved budget, so for FMD the
// budget line pickers list every originating unit (no office restriction); everyone else only gets their own office's (the API scopes
// that). FMD is recognised by its rc_code, NOT its rc_id (the id's format differs between datasets: "013" vs "RC-013") — mirrors
// GlobalService::CENTRAL_REVIEW_RC_CODE, which the API checks again server-side before it honours central=1. A user whose office can't be
// found (no users row — e.g. the built-in administrator —, no rc_id, or a failed lookup) is not FMD: never an error, never the wider list.
$_brv_form_fmdRcCode = '35-074-0000000-032';
$_brv_form_isFmd = false;
try {
    $_brv_form_rc = ExecuteRow(
        'SELECT u.rc_id, rc.rc_code, rc.rc_name FROM uac.users u '
        .'LEFT JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id WHERE u.user_id = '.(int) $user_id,
        'DB'
    ) ?: null;
    $_brv_form_isFmd = $_brv_form_rc !== null && trim((string) ($_brv_form_rc['rc_code'] ?? '')) === $_brv_form_fmdRcCode;
} catch (\Throwable $e) {
    $_brv_form_isFmd = false;
    LogError('budget-rev-form: could not look up the current user\'s RC — '.$e->getMessage());
}
?>
<style>
/* ── Budget revision form ──────────────────────────────────────────────────
   Scoped to .nha-root so it picks up the nha-fims.css tokens. It does not rely on Bootstrap's form classes: every control is
   styled here, so the form looks the same wherever it is included. */

/* a wide modal for the two budget-line cards and the arrow between them */
.nha-root #brv-overlay .nha-modal.brv-modal-wide { max-width: 1120px; }

/* The modal is a flex column (header · scrolling body · footer) capped at 90vh, but this form's body and footer sit INSIDE a <form>.
   The form has to be the flex item that passes the height on; left as a plain block it just grows past the modal, which clips it —
   the body would not scroll and the footer (Cancel / Submit Request) would be pushed out of sight. */
.nha-root #brv-overlay .nha-modal > form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
.nha-root #brv-overlay .nha-modal-body { min-height: 0; }
.nha-root #brv-overlay .nha-modal-foot { flex-shrink: 0; }

.nha-root .brv-intro { margin: 0 0 18px; font-size: 13px; line-height: 1.5; color: var(--sec, #62657E); }

/* ── numbered steps ── */
.nha-root .brv-step + .brv-step { margin-top: 24px; padding-top: 22px; border-top: 1px solid var(--bdr, #E4ECF2); }
.nha-root .brv-step-hd {
    display: flex; align-items: center; flex-wrap: wrap; gap: 4px 10px; margin: 0 0 14px;
    font-size: 14px; font-weight: 700; color: var(--afims-heading, #000E3E);
}
.nha-root .brv-step-n {
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
    font-size: 12px; font-weight: 700; color: var(--afims-pure-white, #FFFFFF); background: var(--afims-heading-fill, #000E3E);
}
.nha-root .brv-step-sub { font-size: 12px; font-weight: 400; color: var(--subtle, #62657E); }

/* ── labels, inputs ── */
.nha-root .brv-lbl {
    display: block; margin: 0 0 5px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: var(--sec, #62657E);
}
.nha-root .brv-req { color: var(--red, #DC3545); }
.nha-root .brv-optional { font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--subtle, #62657E); }
.nha-root .brv-in {
    display: block; box-sizing: border-box; width: 100%; padding: 8px 12px; font: inherit; font-size: 13px; color: var(--txt, #1A1B21);
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr, #E4ECF2); border-radius: 8px;
    transition: border-color .15s, box-shadow .15s;
}
/* the theme gives every text input min-width:250px (FIMS.css, >= 576px) — it would overflow the compact filter bar */
.nha-root .brv-in[type="text"] { min-width: 0 !important; }
.nha-root .brv-in::placeholder { color: var(--afims-text-disabled, #A9AAB8); }
.nha-root .brv-in:focus { outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15, 32, 69, .1); }
.nha-root .brv-in:disabled { color: var(--subtle, #62657E); background: var(--surf, #F4F7FA); cursor: not-allowed; }
.nha-root textarea.brv-in { min-height: 84px; resize: vertical; line-height: 1.45; }

/* ── step 1: From budget line → To budget line ──
   Three columns (card · arrow · card). Where the browser supports subgrid the cards share their three rows (label, box, summary), so
   the two boxes are the same height and the arrow sits level with them; elsewhere the arrow is centred on the whole row. */
.nha-root .brv-cobrow { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; align-items: start; }
.nha-root .brv-cobcol { min-width: 0; }
.nha-root .brv-flow {
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 30px; height: 30px; border-radius: 50%; justify-self: center; align-self: center;
    color: var(--sec, #62657E); background: var(--surf, #F4F7FA); border: 1px solid var(--bdr, #E4ECF2);
}
.nha-root .brv-flow svg { width: 15px; height: 15px; }
@media (max-width: 767px) { .nha-root .brv-cobrow > .brv-flow svg { transform: rotate(90deg); } }
@media (min-width: 768px) {
    .nha-root .brv-cobrow { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); column-gap: 12px; row-gap: 0; }
    /* explicit columns: the arrow is pinned to a row below, and an item with a fixed row but no column would be auto-placed first */
    .nha-root .brv-cobrow > :nth-child(1) { grid-column: 1; }
    .nha-root .brv-cobrow > :nth-child(2) { grid-column: 2; }
    .nha-root .brv-cobrow > :nth-child(3) { grid-column: 3; }
}
@media (min-width: 768px) {
    @supports (grid-template-rows: subgrid) {
        .nha-root .brv-cobrow { grid-template-rows: auto auto auto; }
        .nha-root .brv-cobcol { display: grid; grid-row: span 3; grid-template-rows: subgrid; }
        .nha-root .brv-cobrow > .brv-flow { grid-row: 2; }
    }
}

/* Revision / Realignment tabs (setType). A Revision has no To budget line, so its row is just the From card, full width; the To card and
   the arrow are `hidden` — which `display: grid / flex` above would otherwise override. */
.nha-root .brv-type-tabs { margin-bottom: 14px; }
.nha-root .brv-cobrow > [hidden] { display: none; }
@media (min-width: 768px) {
    .nha-root .brv-cobrow.is-single { grid-template-columns: minmax(0, 1fr); }
}

/* a card's title row: the label + what the card is for */
.nha-root .brv-cob-hd { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.nha-root .brv-cob-hd .brv-lbl { margin: 0; }
.nha-root .brv-cob-role { font-size: 11.5px; color: var(--subtle, #62657E); text-align: right; }

/* the box: the filter bar on top, the search field below. No overflow:hidden — the dropdown lists open outside it. */
.nha-root .brv-reqgroup { display: flex; flex-direction: column; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr, #E4ECF2); border-radius: 10px; }
.nha-root .brv-filterbar {
    position: relative;   /* the filter lists anchor to the bar, not to their narrow inputs */
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    padding: 8px 10px; background: var(--surf, #F4F7FA);
    border-bottom: 1px solid var(--bdr, #E4ECF2);
    border-radius: 9px 9px 0 0;
}
.nha-root .brv-filterbar-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--sec, #62657E);
}
.nha-root .brv-filterbar .brv-combo { position: static; flex: 1 1 90px; min-width: 0; }
.nha-root .brv-combo-field { position: relative; }   /* holds the input + its clear (×) button */
/* the Year filter is a plain dropdown of the fiscal years (GET /fiscal_year/all), not a type-ahead like the others */
.nha-root .brv-filterbar .brv-combo-fy { flex: 0 1 92px; min-width: 0; }
.nha-root .brv-filterbar .brv-fy-select { min-width: 0 !important; cursor: pointer; text-overflow: ellipsis; }
.nha-root .brv-filterbar .brv-in { height: 30px; padding: 3px 26px 3px 10px; font-size: 12.5px; }
.nha-root .brv-filterbar .brv-combo-clear { right: 3px; font-size: 16px; }
/* the filter lists span the whole bar (positioned against the bar), so they never overflow the modal */
.nha-root .brv-filterbar .brv-combo-list { left: 0; right: 0; top: calc(100% + 4px); }
.nha-root .brv-filter-reset {
    margin-left: auto; border: 0; background: transparent; padding: 2px 4px; cursor: pointer;
    font-size: 12px; font-weight: 600; text-decoration: underline;
    color: var(--afims-primary-text, #0035A9);
}
.nha-root .brv-filter-reset[hidden] { display: none; }
.nha-root .brv-reqfield { padding: 10px; }

/* searchable dropdown (combobox) */
.nha-root .brv-combo { position: relative; }
.nha-root .brv-combo .brv-in { padding-right: 34px; }
.nha-root .brv-combo-clear {
    position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
    border: 0; background: transparent; color: var(--muted, #A9AAB8);
    font-size: 18px; line-height: 1; padding: 2px 6px; cursor: pointer;
}
.nha-root .brv-combo-clear:hover { color: var(--txt, #1A1B21); }
.nha-root .brv-combo-list {
    position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0;
    max-height: 240px; overflow-y: auto; margin: 0; padding: 4px; list-style: none;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr, #E4ECF2);
    border-radius: 10px; box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}
.nha-root .brv-combo-clear[hidden],
.nha-root .brv-combo-list[hidden],
.nha-root .brv-summary[hidden],
.nha-root .brv-change[hidden] { display: none; }
.nha-root .brv-opt {
    display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1px 8px; align-items: center;
    padding: 8px 10px; border-radius: 7px; cursor: pointer;
    font-size: 13px; color: var(--txt, #1A1B21);
}
.nha-root .brv-opt:hover,
.nha-root .brv-opt.is-active { background: var(--navy-lt, #F4F7FA); }
.nha-root .brv-opt-main { font-weight: 600; overflow-wrap: anywhere; }
.nha-root .brv-opt-sub { grid-column: 1 / -1; font-size: 11.5px; color: var(--subtle, #62657E); }
.nha-root .brv-opt-empty { padding: 10px; font-size: 12.5px; text-align: center; color: var(--subtle, #62657E); }
.nha-root .brv-hint { margin-top: 4px; font-size: 11.5px; color: var(--subtle, #62657E); }
/* "Showing n of N" on the left, the Clear filters link on the right — it never changes the box height */
.nha-root .brv-hintrow { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; min-height: 22px; }

/* the picked budget request */
.nha-root .brv-summary {
    margin-top: 10px; padding: 11px 14px; font-size: 12.5px;
    background: var(--blue-bg, #E6EBF6); border: 1px solid var(--navy-bdr, #E6EBF6); border-left: 3px solid var(--afims-heading-fill, #000E3E);
    border-radius: 10px;
}
.nha-root .brv-summary-top { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.nha-root .brv-summary-sub { margin-top: 3px; color: var(--subtle, #62657E); white-space: pre-line; overflow-wrap: anywhere; line-height: 1.45; }
.nha-root .brv-summary-amt { margin-top: 7px; }

/* ── step 2: the revision ── */
.nha-root .brv-rev { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; align-items: start; }
@media (min-width: 640px) {
    .nha-root .brv-rev { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); }
    /* the arrow sits level with the two inputs (below their labels) */
    .nha-root .brv-rev > .brv-flow { margin-top: 24px; align-self: start; }
}
@media (max-width: 639px) { .nha-root .brv-rev > .brv-flow svg { transform: rotate(90deg); } }   /* stacked: the arrow points down */
.nha-root .brv-fld { min-width: 0; }
.nha-root .brv-money { position: relative; }
.nha-root .brv-money-sym { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; color: var(--sec, #62657E); pointer-events: none; }
.nha-root .brv-money .brv-in { padding-left: 28px; text-align: right; font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums; -moz-appearance: textfield; }
.nha-root .brv-money .brv-in::-webkit-outer-spin-button,
.nha-root .brv-money .brv-in::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

/* from/to change indicator */
.nha-root .brv-change {
    margin-top: 12px; padding: 9px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
    background: var(--surf, #F4F7FA); color: var(--subtle, #62657E);
}
.nha-root .brv-change.is-up   { background: var(--green-bg, #DBF2E5); color: var(--green-txt, #0C7539); }
.nha-root .brv-change.is-down { background: var(--red-bg, #FAE1E3);   color: var(--red-txt, #8F222D); }
.nha-root .brv-change.is-move { background: var(--blue-bg, #E6EBF6);  color: var(--afims-primary-text, #0035A9); }
.nha-root .brv-reason-fld { margin-top: 16px; }   /* not .brv-reason: the page's table uses that name for its truncated Reason cell */

/* ── Dark mode — same palette as the Memo Request / Budget Preview modals ──
   The secondary / subtle tokens are light-mode greys and the navy fill stays navy, so labels, hints and the step numbers
   were too dim on the dark surface, and the disabled From Amount rendered near-black. */
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay { color-scheme: dark; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay :is(.brv-intro, .brv-summary-sub) { color: var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-step-hd { color: #FFFFFF; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-step-n { color: #FFFFFF; background: #3861BC; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay :is(.brv-lbl, .brv-filterbar-label, .brv-money-sym) { color: var(--afims-text-muted, #AAAFBE); }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay :is(.brv-step-sub, .brv-cob-role, .brv-optional, .brv-hint, .brv-opt-sub, .brv-opt-empty) { color: #9AA3B5; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-in {
    color: var(--afims-text, #E4ECF2); background: var(--afims-surface, #1A1F38); border-color: var(--afims-border-strong, #4A5270);
}
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-in::placeholder { color: #9AA3B5; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-in:focus {
    border-color: var(--afims-primary, #3861BC); box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-primary, #3861BC) 25%, transparent);
}
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-in:disabled {
    color: var(--afims-text, #E4ECF2); background: var(--afims-surface-subtle, #22294A); opacity: 1;
}
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-in:disabled::placeholder { color: #9AA3B5; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-flow { color: var(--afims-text, #E4ECF2); border-color: var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-combo-clear { color: #9AA3B5; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-combo-clear:hover { color: #FFFFFF; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-opt { color: var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay :is(.brv-opt:hover, .brv-opt.is-active) { background: var(--afims-primary-soft, #0F2456); }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-filter-reset { color: var(--afims-primary-text, #94AADB); }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-summary {
    color: var(--afims-text, #E4ECF2); background: var(--afims-primary-soft, #0F2456);
    border-color: color-mix(in srgb, #3861BC 50%, transparent); border-left-color: #3861BC;
}
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay .brv-change.is-move { color: var(--afims-primary-text, #94AADB); background: var(--afims-primary-soft, #0F2456); }
/* footer: Cancel readable, Submit on the brand blue */
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay #brv-cancel {
    color: var(--afims-text, #E4ECF2); background: var(--afims-surface, #1A1F38); border-color: var(--afims-border-strong, #4A5270);
}
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay #brv-save { color: #FFFFFF; background: #3861BC; border-color: #3861BC; }
html.afims-dark[data-bs-theme="dark"] .nha-root #brv-overlay #brv-save:hover:not([disabled]) { background: #2E59B8; }
</style>

<!-- The wrapper makes the modal styles apply wherever this form is included
     (every .nha-modal rule is scoped to .nha-root); padding/background are
     neutralised so it takes no space on the page. -->
<div class="nha-root" style="padding:0;background:none;">

<!-- ── ADD / EDIT MODAL ─────────────────────────────────── -->
<div id="brv-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-md brv-modal-wide">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="brv-modal-title">Add Budget Revision Request</h3>
            <button type="button" class="nha-modal-close" id="brv-modal-close" aria-label="Close">&times;</button>
        </div>
        <form id="brv-form">
            <div class="nha-modal-body">
                <input type="hidden" id="brv-mode" value="add">
                <input type="hidden" id="brv-id" value="">
                <input type="hidden" id="brv-line-id" value="">
                <input type="hidden" id="brv-to-line-id" value="">
                <input type="hidden" id="brv-type" value="REVISION">

                <!-- what is being filed: a Revision changes one line's amount; a Realignment moves funds to a second line (setType) -->
                <div class="nha-tabs brv-type-tabs" id="brv-type-tabs" role="tablist" aria-label="What are you filing?">
                    <button type="button" class="nha-tab active" role="tab" aria-selected="true" data-type="REVISION">Revision</button>
                    <button type="button" class="nha-tab" role="tab" aria-selected="false" data-type="REALIGNMENT">Realignment</button>
                </div>

                <p class="brv-intro" id="brv-intro"></p>

                <!-- ── 1 · the budget lines: From → To (a Revision has no To line) ── -->
                <section class="brv-step">
                    <h4 class="brv-step-hd"><span class="brv-step-n">1</span> <span id="brv-step1-title">Budget line</span> <span class="brv-step-sub" id="brv-step1-sub"></span></h4>

                    <div class="brv-cobrow is-single" id="brv-cobrow">

                        <!-- From budget line (budget_revision.from_budget_line_item_id): a filter bar (Fiscal Year · Originating Unit · Program · Project) attached to the searchable field -->
                        <div class="brv-cobcol">
                            <div class="brv-cob-hd">
                                <label class="brv-lbl" for="brv-req-input">From Budget Line <span class="brv-req">*</span></label>
                                <span class="brv-cob-role">The line being revised</span>
                            </div>

                            <div class="brv-reqgroup">
                                <div class="brv-filterbar" role="group" aria-label="Filter the budget lines">
                                    <span class="brv-filterbar-label">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                        Filter
                                    </span>
                                    <div class="brv-combo-fy">
                                        <select class="brv-in brv-fy-select" id="brv-fy-select" aria-label="Filter by fiscal year"><option value="">Year</option></select>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-unit-input" placeholder="Originating Unit" aria-label="Filter by originating unit"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-unit-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-unit-clear" aria-label="Clear originating unit" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-unit-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-program-input" placeholder="Program" aria-label="Filter by program"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-program-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-program-clear" aria-label="Clear program" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-program-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-project-input" placeholder="Project" aria-label="Filter by project"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-project-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-project-clear" aria-label="Clear project" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-project-list" role="listbox" hidden></ul>
                                    </div>
                                </div>

                                <div class="brv-reqfield">
                                    <div class="brv-combo">
                                        <input type="text" class="brv-in" id="brv-req-input" placeholder="Search by account, particulars, reference no. or project"
                                               required autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-req-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-req-clear" aria-label="Clear budget line" hidden>&times;</button>
                                        <ul class="brv-combo-list" id="brv-req-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-hintrow">
                                        <div class="brv-hint" id="brv-req-hint">Loading budget requests…</div>
                                        <button type="button" class="brv-filter-reset" id="brv-filter-reset" hidden>Clear filters</button>
                                    </div>
                                </div>
                            </div>

                            <div class="brv-summary" id="brv-req-summary" hidden>
                                <div class="brv-summary-top">
                                    <span class="nha-bdg nha-bdg-gray" id="brv-sum-type">Status</span>
                                    <strong id="brv-sum-title"></strong>
                                </div>
                                <div class="brv-summary-sub" id="brv-sum-sub"></div>
                                <div class="brv-summary-amt">
                                    <span>Current amount <strong id="brv-sum-amount"></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- the funds flow from the From line to the To line (Realignment only) -->
                        <div class="brv-flow" id="brv-cob-flow" hidden aria-hidden="true" title="Funds move from the From budget line to the To budget line">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
                        </div>

                        <!-- To budget line (budget_revision.to_budget_line_item_id): the line that receives the funds the From line gives up.
                             Only on the Realignment tab, where it is required; a Revision hides it. The same box as the From line (the same
                             filter bar on top, the searchable field below). -->
                        <div class="brv-cobcol" id="brv-to-col" hidden>
                            <div class="brv-cob-hd">
                                <label class="brv-lbl" for="brv-to-req-input">To Budget Line <span class="brv-req">*</span></label>
                                <span class="brv-cob-role">Receives the funds</span>
                            </div>

                            <div class="brv-reqgroup">
                                <div class="brv-filterbar" role="group" aria-label="Filter the To budget lines">
                                    <span class="brv-filterbar-label">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                        Filter
                                    </span>
                                    <div class="brv-combo-fy">
                                        <select class="brv-in brv-fy-select" id="brv-to-fy-select" aria-label="Filter the To budget lines by fiscal year"><option value="">Year</option></select>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-to-unit-input" placeholder="Originating Unit" aria-label="Filter the To budget lines by originating unit"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-to-unit-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-to-unit-clear" aria-label="Clear originating unit" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-to-unit-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-to-program-input" placeholder="Program" aria-label="Filter the To budget lines by program"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-to-program-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-to-program-clear" aria-label="Clear program" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-to-program-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-to-project-input" placeholder="Project" aria-label="Filter the To budget lines by project"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-to-project-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-to-project-clear" aria-label="Clear project" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-to-project-list" role="listbox" hidden></ul>
                                    </div>
                                </div>

                                <div class="brv-reqfield">
                                    <div class="brv-combo">
                                        <input type="text" class="brv-in" id="brv-to-req-input" placeholder="Search by account, particulars, reference no. or project"
                                               autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-to-req-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-to-req-clear" aria-label="Clear To budget line" hidden>&times;</button>
                                        <ul class="brv-combo-list" id="brv-to-req-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-hintrow">
                                        <div class="brv-hint" id="brv-to-req-hint">Loading budget requests…</div>
                                        <button type="button" class="brv-filter-reset" id="brv-to-filter-reset" hidden>Clear filters</button>
                                    </div>
                                </div>
                            </div>

                            <div class="brv-summary" id="brv-to-req-summary" hidden>
                                <div class="brv-summary-top">
                                    <span class="nha-bdg nha-bdg-gray" id="brv-to-sum-type">Status</span>
                                    <strong id="brv-to-sum-title"></strong>
                                </div>
                                <div class="brv-summary-sub" id="brv-to-sum-sub"></div>
                                <div class="brv-summary-amt">
                                    <span>Current amount <strong id="brv-to-sum-amount"></strong></span>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.brv-cobrow -->
                </section>

                <!-- ── 2 · the revision: amounts and reason ── -->
                <section class="brv-step">
                    <h4 class="brv-step-hd"><span class="brv-step-n">2</span> <span id="brv-step2-title">Revision</span></h4>

                    <div class="brv-rev">
                        <div class="brv-fld">
                            <label class="brv-lbl" for="brv-from-amount"><span id="brv-from-lbl">Current Amount</span> <span class="brv-req">*</span></label>
                            <div class="brv-money">
                                <span class="brv-money-sym">&#8369;</span>
                                <input type="number" class="brv-in" id="brv-from-amount" step="0.01" min="0" placeholder="Auto-filled" disabled>
                            </div>
                            <div class="brv-hint" id="brv-from-hint">The budget line's current amount.</div>
                        </div>
                        <div class="brv-flow" aria-hidden="true">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
                        </div>
                        <div class="brv-fld">
                            <label class="brv-lbl" for="brv-to-amount"><span id="brv-to-lbl">New Amount</span> <span class="brv-req">*</span></label>
                            <div class="brv-money">
                                <span class="brv-money-sym">&#8369;</span>
                                <input type="number" class="brv-in" id="brv-to-amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                            <div class="brv-hint" id="brv-to-hint">What the budget line's amount becomes.</div>
                        </div>
                    </div>

                    <div class="brv-change" id="brv-change" hidden></div>

                    <div class="brv-fld brv-reason-fld">
                        <label class="brv-lbl" for="brv-reason">Reason</label>
                        <textarea class="brv-in" id="brv-reason" rows="3" placeholder="Explain why this budget needs to be revised"></textarea>
                    </div>
                </section>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="brv-cancel">Cancel</button>
                <button type="submit" class="nha-btn nha-btn-primary" id="brv-save">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════════════════
   Budget line pickers — data comes from GET /budget_req/all (fetched once, shared): the user's own office's requests, or — for the
   Financial Management Department (IS_FMD) — every originating unit's (?central=1), so FMD can revise any office's approved budget.

   A revision changes a BUDGET LINE: one chart-of-accounts line item (fims.budget_line_items) of a budget request. The request
   is a header (reference no. "BP-001", office, fiscal year, status) carrying programs[] > projects[] > line_items[]; the pickers
   flatten that into one list of lines — only the lines of an APPROVED request (a request still in review / draft / returned has
   no budget to revise yet). A revision saved earlier against a line of another status still opens in Edit with that line shown. A line's CURRENT amount is its proposed amount plus its adjustment — what approving a
   revision changes (the From line's adjustment goes down, the To line's goes up). The value a picker holds is the line's id.

   Two pickers share one layout, and each has its OWN filters and selection:
     From budget line — required (budget_revision.from_budget_line_item_id)
     To budget line   — only on the Realignment tab, where it is required (budget_revision.to_budget_line_item_id): the line that
                        receives the funds the From line gives up. Its list never offers the From line. A Revision hides it.

   Each picker is five searchable dropdowns that work together:
     Fiscal Year  — a plain dropdown of the fiscal years (GET /fiscal_year/all, the host page's own list; the years that have a
                    budget line if that can't be read)
     Originating Unit — the office that filed the request (budget_req.rc_id), by RC code. It narrows the lines, and Program / Project
                    then offer only that office's. For FMD, who sees every office's approved lines, it is how to find one office's
     Program      — programs that have a budget line
     Project      — projects only. Pick a program first and it lists just that
                    program's projects; pick a project first and it fills in
                    its program.
     the field    — the filters form a compact bar attached to it (with a
                    "Clear" link); the list is narrowed by them and by whatever
                    is typed into the field itself (account, particulars, reference
                    no., office, program, project or status)

   Self-contained: needs nothing from the host page. It exposes:
     window.brvBudgetReqsReady  promise → [{ value, label }] — the budget REQUESTS, value = the reference number (the host page
                                reuses it for its own filter, no second request)
     window.brvForm.setBudgetLine(fromId, toId)   preselect the From / To line by id (used by Edit); does NOT touch From Amount,
                                so a saved value survives

   From Amount is disabled: it always follows the picked From line. Picking a
   From line fills it with that line's current amount; clearing the line
   clears it.

   Year filters: a new request starts with both on next year (when that year is on the fiscal-year list); an existing one shows its From
   line's year. A Realignment's To line must be in the From line's fiscal year, so its To Year filter follows the From line's year and
   is locked (see syncToYear()).

   With a To line the revision MOVES funds, so To Amount must be lower than From Amount:
   the From line goes down to To Amount and the To line goes up by the difference.
══════════════════════════════════════════════════════════════════════════ */
(function () {
    const $ = id => document.getElementById(id);
    // true when the logged-in user belongs to the Financial Management Department (see the PHP at the top of this file)
    const IS_FMD = <?php echo $_brv_form_isFmd ? 'true' : 'false'; ?>;
    // The budget requests the pickers draw on. For FMD, central=1 lifts the office restriction: every originating unit's requests (the API
    // honours it only for the central review RC — for anyone else it changes nothing and they keep their own office's). The pickers
    // themselves only ever list the lines of APPROVED requests (isApproved), so FMD sees every approved budget line of every office.
    const API = '<?php echo $_brv_form_api; ?>' + '/budget_req/all' + (IS_FMD ? '?central=1' : '');
    const FY_API = '<?php echo $_brv_form_api; ?>' + '/fiscal_year/all';   // the fiscal years the Year filters list
    const peso = v => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const signedPeso = v => (v < 0 ? '−' : '+') + peso(Math.abs(v));
    const LIST_MAX = 100;   // most lines listed at once; typing narrows the rest down

    // reqs: the budget requests · lines: the budget lines the pickers OFFER — only those of an Approved budget request, flattened
    // (both pickers read these) · allLines: every budget line whatever its request's status, only used to look up a line by id (a
    // revision filed earlier may point at a line of a request that is not Approved, and Edit must still be able to show it) ·
    // loaded: the first fetch has finished
    const S = { reqs: [], lines: [], allLines: [], loaded: false, years: [] };   // years: the fiscal years from GET /fiscal_year/all, newest first

    // What is being filed — the tab the form is on (setType): 'REVISION' changes one budget line's amount, 'REALIGNMENT' moves funds to a
    // To budget line. Only a Realignment has (and requires) the To line.
    let type = 'REVISION';
    const TYPE_TEXT = {
        REVISION: {
            intro: "Revise a budget line's amount. A revision only takes effect once it has been approved; then the line's adjustment and suggested amount change by the difference.",
            step1: 'Budget line', step1Sub: 'Pick the budget line to revise.',
            step2: 'Revision', from: 'Current Amount', to: 'New Amount',
            fromHint: "The budget line's current amount.", toHint: "What the budget line's amount becomes.",
        },
        REALIGNMENT: {
            intro: "Move funds from one budget line to another. A realignment only takes effect once it has been approved; then the From line's adjustment and suggested amount go down and the To line's go up.",
            step1: 'Budget lines', step1Sub: 'Pick the line to take funds from and the line that receives them.',
            step2: 'Realignment', from: 'From Amount', to: 'To Amount',
            fromHint: "The From budget line's current amount.", toHint: "What the From budget line's amount becomes.",
        },
    };

    // Only a line of an Approved budget request may be revised or receive funds (status compared ignoring case and spaces,
    // the same rule BudgetLineItemService::fetchApproved() uses for the Memo Request form's COBs)
    const isApproved = l => String(l.status || '').trim().toLowerCase() === 'approved';

    // One line, as the pickers use it: its id, the request it belongs to (reference no., fiscal year, office, status), its program and
    // project, its account, its particulars and its amounts. amount = proposed + adjustment, the line's CURRENT amount.
    function toLines(requests) {
        const out = [];
        requests.forEach(h => (Array.isArray(h.programs) ? h.programs : []).forEach(pg =>
            (Array.isArray(pg.projects) ? pg.projects : []).forEach(pj =>
                (Array.isArray(pj.line_items) ? pj.line_items : []).forEach(li => {
                    const proposed = Number(li.proposed_amount || 0), adjustment = Number(li.adjustment || 0);
                    // suggested: the line's live balance (suggested_amount) — what a revision that lowers the line may not exceed
                    const suggested = Number(li.suggested_amount || 0);
                    out.push({
                        id: li.budget_line_item_id, ref: String(h.budget_reference_number), fy: h.fiscal_yr,
                        // rcId / officeCode: the originating unit — the office that filed the request (budget_req.rc_id) — and its RC code, to order by
                        rcId: h.rc_id === null || h.rc_id === undefined ? '' : String(h.rc_id), officeCode: h.rc_code || '',
                        office: h.rc_name || h.rc_code || '', parentOffice: h.rc_parent_name || '', status: h.status_name, statusColor: h.status_color,
                        programCode: pg.program_code || '', programName: pg.program_name || '', projectCode: pj.project_code || '', projectName: pj.project_name || '',
                        accountId: li.account_id || '', accountCode: li.account_code || '', accountTitle: li.account_title || '', particulars: li.particulars || '',
                        proposed, adjustment, suggested, amount: proposed + adjustment,
                    });
                }))));
        return out;
    }

    const keyOf      = l => String(l.id);
    const amountOf   = l => l.amount;
    const withName   = (code, name) => (code ? code + (name ? ' — ' + name : '') : '');
    // "5-02-010 — Travelling Expenses"; a line with no account known shows its id
    const titleOf    = l => withName(l.accountCode, l.accountTitle) || l.accountTitle || `Budget line #${l.id}`;
    // "5-02-010 (BP-001)": short enough for a sentence
    const nameOf     = l => (l.accountCode || `line #${l.id}`) + ` (${l.ref})`;
    const placeOf    = l => [l.programCode, l.projectCode].filter(Boolean).join(' / ') || 'No project yet';
    const shortLabel = l => `FY ${l.fy} — ${l.ref} — ${titleOf(l)}`;
    // What a picker's input shows once a line is picked: its account. The full shortLabel is the input's tooltip and the summary
    // card shows the rest.
    const inputLabel = l => titleOf(l);
    const plural     = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
    const programLabel = code => {
        const l = S.lines.find(x => x.programCode === code);
        return l ? withName(code, l.programName) : code;
    };
    const searchText = l => [l.ref, l.fy, l.office, l.parentOffice, l.status, l.programCode, l.programName, l.projectCode, l.projectName,
        l.accountId, l.accountCode, l.accountTitle, l.particulars].filter(v => v !== null && v !== undefined && v !== '').join(' ').toLowerCase();
    const rowItem = l => ({
        row: l,
        main: titleOf(l),
        // FMD sees every originating unit's lines side by side, so each one says which office it belongs to
        sub: `${l.ref}${IS_FMD && l.office ? ' · ' + l.office : ''} · FY ${l.fy} · ${placeOf(l)} · ${peso(amountOf(l))}`,
        status: { name: l.status, color: l.statusColor },
    });
    // A budget request, as the host page's Budget Request filter lists it
    const reqLabel = h => `FY ${h.fiscal_yr} — ${h.budget_reference_number}` + ((h.rc_name || h.rc_code) ? ' — ' + (h.rc_name || h.rc_code) : '');

    // A status pill in the status's own colour (settings_status.color) — the same look as the Budget Requests list. Only a plain
    // #RRGGBB colour is put into a style; anything else (or none) falls back to a grey pill.
    const HEX_COLOR = /^#[0-9A-Fa-f]{6}$/;
    function paintStatus(el, name, color) {
        el.textContent = name || '—';
        if (name && HEX_COLOR.test(color || '')) {
            el.className = 'nha-bdg';
            el.style.cssText = `color:${color};background:${color}1A;border:1px solid ${color}40;`;
        } else {
            el.className = 'nha-bdg nha-bdg-gray';
            el.style.cssText = '';
        }
        return el;
    }

    // the form-level fields (not part of either picker)
    const el = { form: $('brv-form'), from: $('brv-from-amount'), to: $('brv-to-amount'), change: $('brv-change') };

    // element ids of each picker (the From picker keeps the original ids)
    const FROM_IDS = {
        id: 'brv-line-id',
        fySel: 'brv-fy-select',
        unIn: 'brv-unit-input', unClear: 'brv-unit-clear', unList: 'brv-unit-list',
        prIn: 'brv-program-input', prClear: 'brv-program-clear', prList: 'brv-program-list',
        pjIn: 'brv-project-input', pjClear: 'brv-project-clear', pjList: 'brv-project-list',
        reqIn: 'brv-req-input', reqClear: 'brv-req-clear', reqList: 'brv-req-list',
        hint: 'brv-req-hint', reset: 'brv-filter-reset',
        summary: 'brv-req-summary', sumType: 'brv-sum-type', sumTitle: 'brv-sum-title', sumSub: 'brv-sum-sub', sumAmount: 'brv-sum-amount',
    };
    const TO_IDS = {
        id: 'brv-to-line-id',
        fySel: 'brv-to-fy-select',
        unIn: 'brv-to-unit-input', unClear: 'brv-to-unit-clear', unList: 'brv-to-unit-list',
        prIn: 'brv-to-program-input', prClear: 'brv-to-program-clear', prList: 'brv-to-program-list',
        pjIn: 'brv-to-project-input', pjClear: 'brv-to-project-clear', pjList: 'brv-to-project-list',
        reqIn: 'brv-to-req-input', reqClear: 'brv-to-req-clear', reqList: 'brv-to-req-list',
        hint: 'brv-to-req-hint', reset: 'brv-to-filter-reset',
        summary: 'brv-to-req-summary', sumType: 'brv-to-sum-type', sumTitle: 'brv-to-sum-title', sumSub: 'brv-to-sum-sub', sumAmount: 'brv-to-sum-amount',
    };

    /* ── searchable dropdown helper ────────────────────────────────────── */
    function combo({ input, list, clear, items, query, empty, onPick, onType, onClear, onBlur }) {
        let shown = [], active = -1;
        const isOpen = () => !list.hidden;
        const syncClear = () => { if (clear) clear.hidden = !input.value; };

        function render() {
            shown = items(query());
            list.textContent = '';
            active = -1;
            if (!shown.length) {
                const li = document.createElement('li');
                li.className = 'brv-opt-empty';
                li.textContent = !S.loaded ? 'Loading…' : (empty ? empty() : 'No matches');
                list.appendChild(li);
                return;
            }
            shown.forEach((it, i) => {
                const li = document.createElement('li');
                li.className = 'brv-opt';
                li.setAttribute('role', 'option');
                li.dataset.i = String(i);
                const main = document.createElement('span');
                main.className = 'brv-opt-main';
                main.textContent = it.main;
                li.appendChild(main);
                if (it.status && it.status.name) li.appendChild(paintStatus(document.createElement('span'), it.status.name, it.status.color));
                if (it.sub) {
                    const s = document.createElement('span');
                    s.className = 'brv-opt-sub';
                    s.textContent = it.sub;
                    li.appendChild(s);
                }
                list.appendChild(li);
            });
            if (shown.more > 0) {   // a long list is cut short: say so
                const li = document.createElement('li');
                li.className = 'brv-opt-empty';
                li.textContent = `Showing the first ${shown.length} of ${shown.length + shown.more} — type to narrow down`;
                list.appendChild(li);
            }
        }
        function open()  { render(); list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
        function setActive(i) {
            const opts = list.querySelectorAll('.brv-opt');
            opts.forEach(o => o.classList.remove('is-active'));
            if (!opts.length) { active = -1; return; }
            active = (i + opts.length) % opts.length;
            opts[active].classList.add('is-active');
            opts[active].scrollIntoView({ block: 'nearest' });
        }
        function pick(i) {
            const it = shown[i];
            if (!it) return;
            close();
            onPick(it);
            syncClear();
        }

        input.addEventListener('focus', open);
        input.addEventListener('click', () => { if (!isOpen()) open(); });
        input.addEventListener('input', () => { onType && onType(input.value); syncClear(); open(); });
        input.addEventListener('blur', () => { close(); onBlur && onBlur(); syncClear(); });
        input.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown')      { e.preventDefault(); if (!isOpen()) open(); setActive(active + 1); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); if (!isOpen()) open(); setActive(active - 1); }
            else if (e.key === 'Enter' && isOpen()) {
                e.preventDefault();                       // never submit the form while picking
                if (active >= 0) pick(active);
                else if (shown.length === 1) pick(0);
            }
            else if (e.key === 'Escape' && isOpen()) { e.preventDefault(); e.stopPropagation(); close(); }
            else if (e.key === 'Tab') close();
        });
        // mousedown (not click) so the input keeps focus and blur never beats the pick
        list.addEventListener('mousedown', e => {
            e.preventDefault();
            const li = e.target.closest('.brv-opt');
            if (li) pick(Number(li.dataset.i));
        });
        if (clear) clear.addEventListener('click', () => { onClear(); syncClear(); input.focus(); });
        document.addEventListener('mousedown', e => { if (!input.parentElement.contains(e.target) && !list.contains(e.target)) close(); });

        return { close, syncClear };
    }

    // The card under a picker (From and To share the same layout): the request's status, the line's account, then — one per line —
    // its request / office / fiscal year, its program › project, its particulars and (when revisions changed it) proposed vs adjusted,
    // and its current amount.
    function renderCard(l, box, statusEl, titleEl, subEl, amountEl) {
        box.hidden = !l;
        if (!l) return;
        paintStatus(statusEl, l.status, l.statusColor);
        titleEl.textContent = titleOf(l);
        const place = [withName(l.programCode, l.programName), withName(l.projectCode, l.projectName)].filter(Boolean).join(' › ') || 'No project yet';
        subEl.textContent = [
            `${l.ref}${l.office ? ' · ' + l.office : ''} · FY ${l.fy}`,
            place,
            l.particulars,
            l.adjustment !== 0 ? `Proposed ${peso(l.proposed)} · adjusted ${signedPeso(l.adjustment)}` : '',
            `Suggested amount (balance) ${peso(l.suggested)}`,
        ].filter(Boolean).join('\n');
        amountEl.textContent = peso(amountOf(l));
    }

    /* ── one picker: filter bar + searchable field + summary card ──────────
       ids       the element ids (FROM_IDS / TO_IDS)
       exclude   () => a line the list must not offer (the To picker excludes the From line)
       required  the field is mandatory — native validation until a line is picked. true / false, or a function that says so right now
                 (the To picker: only on the Realignment tab — call sync() when that changes)
       onSelect  (line, { autofill }) after the selection changed
       onClear   the user pressed × on the field (runs just before the selection is cleared) */
    function makePicker(ids, { exclude = () => null, required = false, requiredMsg = '', onSelect = () => {}, onClear = () => {}, onYear = () => {} } = {}) {
        const isRequired = typeof required === 'function' ? required : () => required;
        const pe = {};
        Object.keys(ids).forEach(k => { pe[k] = $(ids[k]); });

        // fy: '' or '2026' · unit: null | { key, label, full } (key = the originating office's rc_id) · program: null | { code, label, full }
        // project: null | { code, label, full, program }
        // selected: the picked budget line or null
        const P = { fy: '', unit: null, program: null, project: null, selected: null };

        // every line this picker may offer
        const baseRows = () => {
            const ex = exclude();
            return ex ? S.lines.filter(l => keyOf(l) !== keyOf(ex)) : S.lines;
        };

        /* ── filtering ── */
        // Lines that satisfy the active filters. Each dropdown switches off the
        // filter(s) it feeds, so its suggestions only react to the OTHER filters.
        function matchRows({ fy = true, unit = true, program = true, project = true, q = '' } = {}) {
            const needle = q.toLowerCase();
            return baseRows().filter(l => {
                if (fy && P.fy && String(l.fy) !== P.fy) return false;
                if (unit && P.unit && l.rcId !== P.unit.key) return false;
                if (program && P.program && l.programCode !== P.program.code) return false;
                if (project && P.project && l.projectCode !== P.project.code) return false;
                if (!needle) return true;
                return searchText(l).includes(needle);
            });
        }

        // The originating units — the offices that filed a request — that have a budget line, by RC code. Reacts to Year / Program / Project, not
        // to itself. A District office says which office it sits under (its parent RC).
        function unitItems(q) {
            const units = new Map();   // rc_id → { name, code, parent, n }
            matchRows({ unit: false }).forEach(l => {
                if (!l.rcId) return;
                const u = units.get(l.rcId) || { name: l.office || l.rcId, code: l.officeCode, parent: l.parentOffice, n: 0 };
                u.n++;
                units.set(l.rcId, u);
            });
            const needle = q.toLowerCase();
            return [...units.entries()]
                .sort(([, a], [, b]) => a.code.localeCompare(b.code, undefined, { numeric: true }) || a.name.localeCompare(b.name))
                .filter(([key, u]) => !needle || `${key} ${u.name} ${u.parent}`.toLowerCase().includes(needle))
                .map(([key, u]) => ({
                    key, label: u.name, full: withName(key, u.name), main: u.name,
                    sub: (u.parent ? `Under ${u.parent} · ` : '') + plural(u.n, 'budget line'),
                }));
        }

        // The programs that have a budget line.
        function programItems(q) {
            const progs = new Map();   // code → { name, n }
            matchRows({ program: false, project: false }).forEach(l => {
                if (!l.programCode) return;
                const p = progs.get(l.programCode) || { name: l.programName, n: 0 };
                p.n++;
                progs.set(l.programCode, p);
            });
            const needle = q.toLowerCase();
            return [...progs.keys()].sort()
                .filter(code => !needle || `${code} ${progs.get(code).name}`.toLowerCase().includes(needle))
                .map(code => {
                    const label = withName(code, progs.get(code).name);
                    return { code, label, main: label, sub: plural(progs.get(code).n, 'budget line') };
                });
        }

        // Projects only — just the picked program's projects when a program is set.
        function projectItems(q) {
            const projs = new Map();   // code → { name, program, programName, n }
            matchRows({ project: false }).forEach(l => {
                if (!l.projectCode) return;
                const p = projs.get(l.projectCode) || { name: l.projectName, program: l.programCode, programName: l.programName, n: 0 };
                p.n++;
                projs.set(l.projectCode, p);
            });
            const needle = q.toLowerCase();
            return [...projs.entries()]
                .sort(([ac, a], [bc, b]) => String(a.program).localeCompare(String(b.program)) || ac.localeCompare(bc))
                .filter(([code, p]) => !needle || `${code} ${p.name} ${p.program}`.toLowerCase().includes(needle))
                .map(([code, p]) => {
                    const label = withName(code, p.name);
                    return {
                        code, label, main: label, program: p.program,
                        // with a program already picked the parent is obvious; otherwise show it
                        sub: (P.program ? '' : `Under ${withName(p.program, p.programName)} · `) + plural(p.n, 'budget line'),
                    };
                });
        }

        const reqItems = q => {
            const all = matchRows({ q });
            const items = all.slice(0, LIST_MAX).map(rowItem);
            items.more = all.length - items.length;
            return items;
        };

        /* ── state → UI ── */
        function refresh() {
            if (S.loaded) pe.hint.textContent = `Showing ${matchRows().length} of ${baseRows().length} budget lines`;
            // "Clear filters" shows only while a filter it can clear is on (a locked Year is not one of them)
            pe.reset.hidden = !((P.fy && !pe.fySel.disabled) || P.unit || P.program || P.project);
        }

        // The native "pick one" message while the field is required and nothing is picked; none otherwise (a hidden, optional field must
        // never leave the form invalid).
        function syncRequired() {
            pe.reqIn.setCustomValidity(isRequired() && !P.selected ? requiredMsg : '');
        }

        // Set (or clear) the picked budget line.
        function select(line, opts = {}) {
            const ex = exclude();
            if (line && ex && keyOf(line) === keyOf(ex)) line = null;   // never the same line as the other picker
            P.selected = line || null;
            pe.id.value = line ? keyOf(line) : '';   // the budget line's id
            pe.reqIn.value = line ? inputLabel(line) : '';
            pe.reqIn.title = line ? shortLabel(line) : '';
            pe.reqClear.hidden = !line;
            // native validation message when submitting without a pick
            syncRequired();
            renderCard(P.selected, pe.summary, pe.sumType, pe.sumTitle, pe.sumSub, pe.sumAmount);
            onSelect(P.selected, opts);
        }

        /* ── wire the dropdowns ── */
        // A typed value is only a search; the applied filter changes on pick/clear,
        // and leaving the field restores the applied value.
        // The compact filter inputs show just the code (label); the full "code — name" is their tooltip.
        const showUnit    = () => { pe.unIn.value = P.unit ? P.unit.label : ''; pe.unIn.title = P.unit ? P.unit.full : ''; };
        const showProgram = () => { pe.prIn.value = P.program ? P.program.label : ''; pe.prIn.title = P.program ? P.program.full : ''; };
        const showProject = () => { pe.pjIn.value = P.project ? P.project.label : ''; pe.pjIn.title = P.project ? P.project.full : ''; };
        const unQuery = () => (P.unit && pe.unIn.value === P.unit.label ? '' : pe.unIn.value.trim());
        const prQuery = () => (P.program && pe.prIn.value === P.program.label ? '' : pe.prIn.value.trim());
        const pjQuery = () => (P.project && pe.pjIn.value === P.project.label ? '' : pe.pjIn.value.trim());

        // Fiscal Year: a dropdown of the fiscal years (fillYearSelects); the empty first option ("Year") is no filter
        pe.fySel.addEventListener('change', () => { P.fy = pe.fySel.value; refresh(); onYear(P.fy); });

        // Set the Year filter from code (the default year, or the To picker following the From line). The year is added to the dropdown
        // if it is not there yet (the fiscal years may still be loading), so what the dropdown shows is always what filters. Does not
        // call onYear: only a change by the user does.
        function setYear(y) {
            y = String(y ?? '');
            if (y && ![...pe.fySel.options].some(o => o.value === y)) {
                const o = document.createElement('option');
                o.value = y;
                o.textContent = y;
                pe.fySel.appendChild(o);
            }
            pe.fySel.value = y;
            P.fy = y;
            refresh();
        }

        // Lock the Year filter to year y ('' = no year) — it shows y, cannot be changed, and `why` is its tooltip — or, with null, unlock it.
        function lockYear(y, why = '') {
            const lock = y !== null;
            if (lock) setYear(y);
            pe.fySel.disabled = lock;
            pe.fySel.title = lock ? why : '';
            refresh();
        }

        const clearProject = () => { P.project = null; showProject(); pjCombo.syncClear(); };

        // Originating Unit: narrows the list to that office's budget lines (and so what Program / Project offer). Independent of the others —
        // it neither fills in nor clears Program or Project.
        const unCombo = combo({
            input: pe.unIn, list: pe.unList, clear: pe.unClear, items: unitItems, query: unQuery,
            onPick: it => { P.unit = { key: it.key, label: it.label, full: it.full }; showUnit(); refresh(); },
            onClear: () => { P.unit = null; showUnit(); refresh(); },
            onBlur: () => showUnit(),
        });

        const prCombo = combo({
            input: pe.prIn, list: pe.prList, clear: pe.prClear, items: programItems, query: prQuery,
            onPick: it => {
                P.program = { code: it.code, label: it.code, full: it.label };
                showProgram();
                // a project only makes sense under its own program
                if (P.project && P.project.program !== it.code) clearProject();
                refresh();
            },
            onClear: () => { P.program = null; showProgram(); clearProject(); refresh(); },
            onBlur: () => showProgram(),
        });

        const pjCombo = combo({
            input: pe.pjIn, list: pe.pjList, clear: pe.pjClear, items: projectItems, query: pjQuery,
            empty: () => (P.program && !pjQuery() ? 'No projects under this program' : 'No matches'),
            onPick: it => {
                P.project = { code: it.code, label: it.code, full: it.label, program: it.program };
                showProject();
                // picking a project fills in the program it belongs to
                if (!P.program || P.program.code !== it.program) {
                    P.program = { code: it.program, label: it.program, full: programLabel(it.program) };
                    showProgram();
                    prCombo.syncClear();
                }
                refresh();
            },
            onClear: () => { P.project = null; showProject(); refresh(); },
            onBlur: () => showProject(),
        });

        const reqCombo = combo({
            input: pe.reqIn, list: pe.reqList, clear: pe.reqClear, items: reqItems,
            query: () => (P.selected && pe.reqIn.value === inputLabel(P.selected) ? '' : pe.reqIn.value.trim()),
            onPick: it => select(it.row, { autofill: true }),
            onClear: () => { onClear(); select(null); },
            onBlur: () => { pe.reqIn.value = P.selected ? inputLabel(P.selected) : ''; },
        });

        // Drop every filter. The picked budget line, if any, is left alone.
        function clearFilters() {
            P.unit = null; P.program = null; P.project = null;
            if (!pe.fySel.disabled) { P.fy = ''; pe.fySel.value = ''; }   // a locked Year stays
            showUnit(); showProgram(); showProject();
            [unCombo, prCombo, pjCombo].forEach(c => { c.close(); c.syncClear(); });
            refresh();
        }
        pe.reset.addEventListener('click', clearFilters);

        // filters + pick back to empty
        function reset() {
            clearFilters();
            reqCombo.close();
            select(null);
        }

        return { select, selected: () => P.selected, refresh, reset, sync: syncRequired, year: () => P.fy, setYear, lockYear };
    }

    /* ── the two pickers ───────────────────────────────────────────────── */
    let fromPicker, toPicker;   // each one's callbacks look at the other

    fromPicker = makePicker(FROM_IDS, {
        required: true,
        requiredMsg: 'Please select a From budget line from the list.',
        onSelect: (line, { autofill = false } = {}) => {
            // funds can't move from a line to itself, so picking the To line as the From line drops the To line
            const to = toPicker && toPicker.selected();
            if (line && to && keyOf(to) === keyOf(line)) toPicker.select(null);
            // autofill: true only when the USER picks a line. Preselecting one for Edit
            // must leave the saved From Amount alone.
            if (line && autofill) el.from.value = amountOf(line).toFixed(2);   // From Amount (disabled) = its current amount
            if (toPicker) toPicker.refresh();   // the To list no longer offers the From line
            syncToYear();                       // a Realignment's To line has to be in the From line's fiscal year
            updateChange();
        },
        // From Amount follows the From line, so clearing the line clears it too
        onClear: () => { el.from.value = ''; },
        // the user picked another Year in the From filter: a Realignment's To filter follows it
        onYear: () => syncToYear(),
    });
    toPicker = makePicker(TO_IDS, {
        exclude: () => fromPicker && fromPicker.selected(),
        required: () => type === 'REALIGNMENT',   // a Realignment moves funds to it; a Revision has no To line at all
        requiredMsg: 'Please select a To budget line from the list.',
        onSelect: () => updateChange(),
    });

    /* ── fiscal years: what the Year filters start on, and the Realignment lock ───────────────────────────────────────────────
       A new request starts with both Year filters on NEXT year (the year budgets are being prepared for) — when that year is on the
       list. A Realignment moves funds only within one fiscal year, so its To line has to be in the From line's year: the To picker's
       Year filter shows the From line's year (or, until a From line is picked, the year the From filter is on) and is LOCKED; its list
       only offers that year's lines, and a To line of another year is dropped. A Revision has no To line, so nothing is locked. The
       server refuses a transfer between years too (BudgetRevisionService::sameYearError()). */
    function syncToYear() {
        if (type !== 'REALIGNMENT') { toPicker.lockYear(null); return; }
        const from = fromPicker.selected();
        const y = from ? String(from.fy) : fromPicker.year();
        toPicker.lockYear(y, y
            ? `Locked to the From budget line's fiscal year (FY ${y}) — funds only move within one fiscal year.`
            : "Follows the From budget line's fiscal year — funds only move within one fiscal year.");
        const to = toPicker.selected();
        if (to && y && String(to.fy) !== y) toPicker.select(null);   // a To line of another year cannot stay
    }

    function applyDefaultYear() {
        const next = String(new Date().getFullYear() + 1);
        const y = yearList().includes(next) ? next : '';
        fromPicker.setYear(y);
        if (type !== 'REALIGNMENT') toPicker.setYear(y);   // (a Realignment's To filter follows the From one — syncToYear)
        syncToYear();
    }

    // The From line may not be left with a negative balance. Approving moves its suggested_amount by (To Amount − its current amount),
    // so lowering the line by more than that suggested_amount would take it below zero. Returns the message, or '' when it is fine
    // (an increase, or a decrease that fits). The server checks the same rule (BudgetRevisionService::fromBalanceError()).
    function fromBalanceError(line, toAmount) {
        if (!line) return '';
        const change = Math.round((Number(toAmount) - amountOf(line)) * 100) / 100;   // negative = the line goes down
        const left   = Math.round((line.suggested + change) * 100) / 100;
        if (change >= 0 || left >= 0) return '';
        return `The From budget line has only ${peso(line.suggested)} left (its suggested amount), so it can't go down by ${peso(-change)} — that would leave it at −${peso(-left)}.`;
    }

    function updateChange() {
        const f = el.from.value, t = el.to.value;
        const from = fromPicker.selected(), to = toPicker.selected();
        el.to.setCustomValidity('');   // set again below when the amounts are invalid
        if (f === '' || t === '' || isNaN(f) || isNaN(t)) { el.change.hidden = true; return; }
        const d = Math.round((Number(t) - Number(f)) * 100) / 100;
        el.change.hidden = false;

        // nothing is submitted that would leave the From line negative
        const low = fromBalanceError(from, t);
        if (low) {
            el.change.className = 'brv-change is-down';
            el.change.textContent = low;
            el.to.setCustomValidity(low);
            return;
        }

        if (to || type === 'REALIGNMENT') {
            // A realignment (always, even before its To line is picked) moves funds: the From line must go DOWN, the To line gets the difference.
            if (d < 0) {
                el.change.className = 'brv-change is-move';
                el.change.textContent = `${peso(-d)} moves from ${from ? nameOf(from) : 'the From budget line'} to ${to ? nameOf(to) : 'the To budget line'}`;
            } else {
                const msg = 'To Amount must be lower than From Amount to move funds to the To budget line.';
                el.change.className = 'brv-change is-down';
                el.change.textContent = msg;
                el.to.setCustomValidity(msg);
            }
            return;
        }

        el.change.className = 'brv-change' + (d > 0 ? ' is-up' : d < 0 ? ' is-down' : '');
        el.change.textContent = d === 0 ? 'No change' : `${d > 0 ? 'Increase' : 'Decrease'} of ${peso(Math.abs(d))}`;
    }
    el.to.addEventListener('input', updateChange);

    /* ── Revision | Realignment tabs ───────────────────────────────────────
       A Revision changes one budget line's amount, so the To budget line (and the arrow to it) is hidden and emptied; a Realignment
       moves funds, so the To line is shown and required. The wording of the form follows. The host page picks the tab when it opens
       the modal (brvForm.setType) and reads it back from #brv-type when it saves. */
    const tabs = Array.from(document.querySelectorAll('#brv-type-tabs .nha-tab'));
    function setType(next) {
        type = next === 'REALIGNMENT' ? 'REALIGNMENT' : 'REVISION';
        const re = type === 'REALIGNMENT', t = TYPE_TEXT[type];
        $('brv-type').value = type;
        tabs.forEach(b => {
            const on = b.dataset.type === type;
            b.classList.toggle('active', on);
            b.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        $('brv-to-col').hidden = !re;
        $('brv-cob-flow').hidden = !re;
        $('brv-cobrow').classList.toggle('is-single', !re);
        if (!re) toPicker.select(null);   // a Revision has no To line — whatever was picked goes
        toPicker.sync();                  // the To line is required on a Realignment only
        $('brv-intro').textContent = t.intro;
        $('brv-step1-title').textContent = t.step1;
        $('brv-step1-sub').textContent = t.step1Sub;
        $('brv-step2-title').textContent = t.step2;
        $('brv-from-lbl').textContent = t.from;
        $('brv-to-lbl').textContent = t.to;
        $('brv-from-hint').textContent = t.fromHint;
        $('brv-to-hint').textContent = t.toHint;
        syncToYear();   // a Realignment locks the To Year to the From line's; a Revision unlocks it
        updateChange();
    }
    $('brv-type-tabs').addEventListener('click', e => {
        const b = e.target.closest('.nha-tab');
        if (b && b.dataset.type !== type) setType(b.dataset.type);
    });

    function resetUI() {
        fromPicker.reset();
        toPicker.lockYear(null);   // (a locked Year filter is not cleared by reset)
        toPicker.reset();
        setType('REVISION');   // back to the default tab (also refreshes the change chip)
    }
    // the host page calls form.reset() when closing the modal
    el.form.addEventListener('reset', () => setTimeout(resetUI, 0));
    fromPicker.select(null);
    toPicker.select(null);
    setType('REVISION');

    /* ── fiscal years for the Year filters ─────────────────────────────── */
    // The Year filter of each picker is a dropdown of the fiscal years from GET /fiscal_year/all — the same list the host page's own
    // Fiscal Year filter uses — newest first. If that can't be read, the years that have a budget line are listed instead, so the
    // filter still works.
    const yearList = () => (S.years.length
        ? S.years
        : [...new Set(S.lines.map(l => String(l.fy)).filter(Boolean))].sort((a, b) => b - a));

    function fillYearSelects() {
        const years = yearList();
        [FROM_IDS.fySel, TO_IDS.fySel].forEach(id => {
            const sel = $(id), keep = sel.value;
            sel.textContent = '';
            const first = document.createElement('option');
            first.value = '';
            first.textContent = 'Year';
            sel.appendChild(first);
            years.forEach(y => {
                const o = document.createElement('option');
                o.value = y;
                o.textContent = y;
                sel.appendChild(o);
            });
            const next = years.includes(keep) ? keep : '';
            sel.value = next;
            if (next !== keep) sel.dispatchEvent(new Event('change'));   // the picked year is no longer on the list: drop that filter
        });
    }

    async function loadYears() {
        try {
            const headers = typeof window.brv_apiHeaders === 'function' ? window.brv_apiHeaders() : {};
            const res = await fetch(FY_API, { credentials: 'same-origin', headers });
            const json = await res.json().catch(() => null);
            if (!res.ok || !json || !json.success) throw new Error((json && json.message) || 'HTTP ' + res.status);
            S.years = [...new Set((json.data ?? []).map(r => String(r.fiscal_yr ?? '').trim()).filter(Boolean))].sort((a, b) => b - a);
        } catch (e) {
            console.error('[budget-rev-form] fiscal years', e);
            if (typeof window.brv_toast === 'function') window.brv_toast('Could not load the fiscal years: ' + e.message, 'warning');
        }
        fillYearSelects();
    }

    /* ── load data ─────────────────────────────────────────────────────── */
    // Fetch /budget_req/all (every originating unit's for FMD — see IS_FMD) into S.reqs + S.lines; resolves to the [{ value, label }] list of
    // budget requests the host page uses.
    async function loadRows() {
        const res = await fetch(API, { credentials: 'same-origin' });
        // Read the body even on an error status, so the server's own message is shown rather than a bare "HTTP 4xx".
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error((json && json.message) || (res.ok ? 'Failed to load budget requests.' : 'HTTP ' + res.status));

        // newest year first; within a year by office, then by reference number (BP-2 before BP-10)
        const officeOf = h => h.rc_name || h.rc_code || '';
        S.reqs = (json.data ?? []).slice().sort((a, b) =>
            (b.fiscal_yr - a.fiscal_yr)
            || officeOf(a).localeCompare(officeOf(b))
            || String(a.budget_reference_number).localeCompare(String(b.budget_reference_number), undefined, { numeric: true }));
        // the lines follow that order, then program, project and account
        S.allLines = toLines(S.reqs).map((l, i) => ({ l, i })).sort((a, b) =>
            (a.l.ref === b.l.ref ? 0 : a.i - b.i)
            || a.l.programCode.localeCompare(b.l.programCode) || a.l.projectCode.localeCompare(b.l.projectCode)
            || a.l.accountCode.localeCompare(b.l.accountCode, undefined, { numeric: true }) || a.i - b.i).map(x => x.l);
        S.lines = S.allLines.filter(isApproved);   // what the From / To pickers list
        S.loaded = true;
        fillYearSelects();   // (only matters when the fiscal years could not be read: then the lines' own years are listed)
        fromPicker.refresh();
        toPicker.refresh();
        return S.reqs.map(h => ({ value: String(h.budget_reference_number), label: reqLabel(h) }));
    }

    const yearsReady = loadYears();   // fills the Year dropdowns; independent of the budget lines below (never rejects)
    const ready = (async function () {
        try {
            return await loadRows();
        } catch (e) {
            S.loaded = true;
            [FROM_IDS.hint, TO_IDS.hint].forEach(id => { $(id).textContent = 'Could not load budget lines.'; });
            if (typeof window.brv_toast === 'function') window.brv_toast(e.message, 'error');
            else console.error(e);
            return [];
        }
    })();

    window.brvBudgetReqsReady = ready;
    window.brvForm = {
        ready,
        // Pick the Revision or Realignment tab ('REVISION' | 'REALIGNMENT'); a Revision hides and empties the To budget line.
        setType,
        getType: () => type,
        // Preselect the From line and the To line by id (the latter may be omitted / '' / null for none; a line that is no longer on
        // the list — or a revision filed before budget lines were revised, which has none — leaves that picker empty). The host
        // page has already put the saved From/To Amount in the inputs, so refresh the chip too.
        // Also sets the Year filters: a new request (no From line) starts on next year; an existing one shows its From line's year.
        setBudgetLine: (fromId, toId) => Promise.all([ready, yearsReady]).then(() => {
            const find = v => (v === '' || v == null ? null : (S.allLines.find(l => keyOf(l) === String(v)) || null));
            const from = find(fromId);
            if (from) fromPicker.setYear(String(from.fy)); else applyDefaultYear();
            fromPicker.select(from);
            toPicker.select(find(toId));
            syncToYear();   // a saved Realignment across two years (filed before the rule) loses its To line here — it must be picked again
            updateChange();
        }),
        // Re-fetch the budget requests — a line's amount changes when a revision is approved.
        // Keeps the current From / To pick (with its fresh amount) but never touches From Amount,
        // which only follows a user pick. Resolves to the new [{ value, label }] list of requests, or null on failure.
        reload: async () => {
            try {
                const items = await loadRows();
                const fresh = line => (line ? S.allLines.find(l => keyOf(l) === keyOf(line)) || null : null);
                if (fromPicker.selected()) fromPicker.select(fresh(fromPicker.selected()));
                if (toPicker.selected()) toPicker.select(fresh(toPicker.selected()));
                return items;
            } catch (e) {
                console.error(e);
                return null;
            }
        },
    };
})();
</script>

</div><!-- /.nha-root (form wrapper) -->
