<?php
/**
 * saro-processing-form.php
 *
 * New / Edit modal for fims.fund_tracking (SARO, NCA, OR …) — the "SARO processing form".
 * Ported from the FMD app's saro-processing-form.php (a full page there) the same way
 * memo-req-page.php hosts budget-allocation-form.php: this file is INCLUDED by a list page
 * and renders an overlay modal that stays hidden until it is opened.
 *
 *   Host page (see saro-registry-page.php):
 *     $sarAllowedTypes = ['SARO', 'NCA'];                  // optional — see below
 *     include __DIR__ . '/form/saro-processing-form.php';
 *     window.sarModal.open();        // New entry
 *     window.sarModal.open(42);      // Edit fims.fund_tracking.id = 42
 *     window.sarModal.open(null, { nature: 'SUPPLEMENTAL' });   // New entry with that nature of fund already chosen
 *     window.sarModal.open(null, { type: 'NCA' });              // New entry with that tracking type already chosen
 *     window.sarModal.close();
 *   After a successful save the modal closes and calls window.sr_reload() when the host defines it.
 *
 *   $sarAllowedTypes — the tracking-type codes the Tracking Type dropdown offers. Defaults to
 *   SARO, NCA and OR. Trust Fund is never offered: it has its own page.
 *
 *   $sarTypeFirst — optional, defaults to false. When true (and more than one type is allowed) the form opens with a
 *   "What are you adding?" choice — SARO or NCA — instead of the Tracking Type dropdown, and the rest of the form follows it:
 *   a SARO has no parent, so choosing SARO removes the Parent Fund field (and a SARO is always saved with parent_id null);
 *   choosing NCA turns it into "SARO" — the SARO the NCA was released under, offering SAROs only. saro-registry-page.php sets it.
 *
 *   $sarExcludeNatures — optional, e.g. ['SUPPLEMENTAL']. Natures of fund this host does not deal with: they are not offered
 *   in the Nature of Fund dropdown, and the Parent Fund search leaves out records of that nature.
 *   $sarParentTypes — optional, e.g. ['SARO', 'NCA']. The tracking types the Parent Fund search offers; by default any
 *   record can be picked. (An OR's Trust Fund pick-list is a separate list and is not affected.)
 *
 *   $sarSupplementalFields — optional, defaults to true. While true, choosing the SUPPLEMENTAL nature of fund turns the
 *   form into the Supplemental one: Name is swapped for Purpose, and a Supplemental Details section
 *   (status, region, budgetary requirement, routing dates) appears. A host that sets it to false (supplemental-funds-page.php)
 *   gets the plain SARO / NCA fields for every nature of fund: SUPPLEMENTAL is then just a nature of fund like any other. The
 *   Purpose and Supplemental Details of an existing record are neither shown nor touched — they are left out of the save, so
 *   they keep what they already hold.
 *
 * API (app/routes/fund_tracking.php and friends — the user is the logged-in session, never sent):
 *   GET  /fund_tracking/read/{id}          POST /fund_tracking/add         PATCH /fund_tracking/update/{id}
 *   GET  /fund_tracking/all?search=&lean=1 (Parent Fund search)            GET   /fund_tracking/list?type=TRUST_FUND
 *   GET  /fund-tracking-type/all  /nature-of-fund/all  /fiscal_year/all  /sub_program/all (a SARO's Sub-Program)
 *   GET  /responsibility-center/all?office_type=Regional   (Supplemental Details → Region)
 *   POST /upload/file (multipart)          POST /upload/multi-upload       (Supporting Documents)
 *
 * What changed from the FMD version (the table is fims.fund_tracking now):
 *   Program (program_id)        → Sub-Program (a SARO only — not an NCA, which has no such field): a searchable list of fims.sub_program whose CODE is
 *                                 saved in fims.fund_tracking.sub_program ("03 - alter_table/45"). There is no unreleased amount any more (dropped, "41")
 *   Date                        → a required date picker: typed in, never filled in for the user (a request without one is dated the day it is created)
 *   Fiscal Year (period id)     → fiscal_year, a plain 4-digit year TYPED in (1900–2200) — not picked from fims.fiscal_year any more
 *   Bank Account (a picker)     → Bank Account No. (text) — there is no bank-accounts table in FIMS
 *   NCA Releases                → no longer a part of the SARO's form. An NCA is its own entry: choose NCA first ($sarTypeFirst),
 *                                 then pick the SARO it was released under (the "SARO" field; a SARO itself has no parent)
 *   Agency and Remarks          → not on this form. Saving never sends them, so a record's existing
 *                                 agency_name / remarks are left exactly as they are when it is edited
 *   created_by                  → no longer sent; the API stamps user_id from the session
 *
 * Supplemental Details (nature of fund = SUPPLEMENTAL) are saved by the API into
 * fmd.supplemental_fund_details, a table (and a shared.v_rc_regions view) that has no SQL in this repo
 * yet. The section is only SENT when something in it was filled in, so a Supplemental record saves fine
 * without it; with it, the save fails until those exist — and because the main record is already
 * written by then, the form switches to edit mode for it so pressing Save again can never duplicate it.
 */

namespace PHPMaker2024\FIMS;

$apiBase = Config('API.FIMS_URL');

$_sar_types = ['SARO', 'NCA', 'OR'];
if (!empty($sarAllowedTypes) && is_array($sarAllowedTypes)) {
    $_sar_types = array_values(array_filter(array_map(fn ($t) => strtoupper(trim((string) $t)), $sarAllowedTypes)));
}

// Supplemental-only fields (Purpose, Supplemental Details) — on unless the host page turns them off
$_sar_supp = !isset($sarSupplementalFields) || (bool) $sarSupplementalFields;

// Natures of fund the host does not deal with, and the tracking types its Parent Fund search is limited to (upper-cased)
$_sar_upper = fn ($list) => is_array($list)
    ? array_values(array_filter(array_map(fn ($v) => strtoupper(trim((string) $v)), $list), fn ($v) => $v !== ''))
    : [];
$_sar_exclude_natures = $_sar_upper($sarExcludeNatures ?? null);
$_sar_parent_types = $_sar_upper($sarParentTypes ?? null);

// Ask WHAT is being added first: a "What are you adding?" choice (one button per allowed tracking type) at the top of the form
$_sar_type_first = !empty($sarTypeFirst);
?>

<style>
    /* ── Layout ─────────────────────────────────────────────── */
    .nha-root .sar-modal .nha-modal-body { padding: 18px 22px; }
    .nha-root .sar-sec { margin-bottom: 22px; }
    .nha-root .sar-sec:last-child { margin-bottom: 0; }
    .nha-root .sar-sec-hd { margin: 0 0 12px; padding-bottom: 6px; border-bottom: 1.5px solid var(--afims-border, #E4ECF2);
        font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--afims-heading, #000E3E); }
    .nha-root .sar-sub-hd { margin: 14px 0 4px; font-size: 13px; font-weight: 700; color: var(--afims-heading, #000E3E); }
    .nha-root .sar-cols-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
    .nha-root .sar-cols-4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
    @media (max-width: 760px) {
        .nha-root .sar-cols-3, .nha-root .sar-cols-4 { grid-template-columns: 1fr 1fr; }
    }
    .nha-root .sar-hint { display: block; margin-top: 4px; font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); }
    /* SARO ID's hint, with the GAAAO "Generate" link on its right */
    .nha-root .sar-ref-hintrow { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; }
    .nha-root .sar-ref-hintrow .sar-hint:empty { display: none; }
    .nha-root .sar-ref-gen { flex-shrink: 0; margin-top: 4px; padding: 0; border: 0; background: transparent; cursor: pointer; white-space: nowrap;
        font-size: 11.5px; font-weight: 600; text-decoration: underline; color: var(--afims-primary-text, #0035A9); }
    .nha-root .sar-ref-gen:hover { color: var(--afims-heading, #000E3E); }
    .nha-root .sar-ref-gen[hidden] { display: none; }
    /* NCA Details (syncNcaLayout): the NCA's fields in one two-column grid — the SARO and the SARO's balance take the full width; the
       SARO ID and Fiscal Year sit side by side, and so do the Amount and the Date. The fields keep their own margins out of it (the gap spaces them). */
    .nha-root .sar-nca-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 12px; }
    .nha-root .sar-nca-grid > * { margin: 0 !important; min-width: 0; }
    .nha-root .sar-nca-grid > #sar-parent-group,
    .nha-root .sar-nca-grid > #sar-saro-bal { grid-column: 1 / -1; }
    @media (max-width: 560px) { .nha-root .sar-nca-grid { grid-template-columns: 1fr; } }
    /* NCA under a SARO: the SARO's amount less the NCAs released under it (see syncSaroBalance) */
    .nha-root .sar-saro-bal { margin-top: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; line-height: 1.6;
        background: var(--afims-surface-subtle, #F4F7FA); border: 1px solid var(--afims-border, #E4ECF2); color: var(--afims-text, #1A1B21); }
    .nha-root .sar-saro-bal .sar-bal-row { display: flex; justify-content: space-between; gap: 12px; }
    .nha-root .sar-saro-bal .sar-bal-row b { font-family: 'IBM Plex Mono', monospace; font-weight: 600; white-space: nowrap; }
    .nha-root .sar-saro-bal .sar-bal-less { color: var(--afims-text-muted, #62657E); }
    .nha-root .sar-saro-bal .sar-bal-total { margin-top: 4px; padding-top: 4px; border-top: 1px solid var(--afims-border, #E4ECF2); font-weight: 700; }
    .nha-root .sar-saro-bal .sar-bal-total b { font-weight: 700; }
    .nha-root .sar-saro-bal .sar-bal-warn { margin-top: 4px; font-size: 11.5px; }
    .nha-root .sar-saro-bal.sar-bal-over { background: var(--afims-danger-soft, #FAE1E3); border-color: var(--afims-danger-soft, #FAE1E3); color: var(--afims-danger, #DC3545); }
    .nha-root .sar-err { display: none; margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; line-height: 1.45;
        background: var(--afims-danger-soft, #FAE1E3); border: 1px solid var(--afims-danger-soft, #FAE1E3); color: var(--afims-danger, #DC3545); }

    /* ── Fields ─────────────────────────────────────────────── */
    .nha-root .nha-fg .sar-money { position: relative; }
    .nha-root .nha-fg .sar-money > span { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px;
        color: var(--afims-text-muted, #62657E); pointer-events: none; }
    .nha-root .nha-fg .sar-money > input { padding-left: 28px; }
    .nha-root .nha-fg .sar-invalid { border-color: var(--afims-danger, #DC3545); box-shadow: 0 0 0 3px rgba(220, 53, 69, .12); }
    .nha-root .nha-fg .sar-has-value { border-color: var(--afims-heading-fill, #000E3E); }
    .nha-root .nha-fg input:disabled, .nha-root .nha-fg select:disabled { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-disabled, #A9AAB8); cursor: not-allowed; }
    .nha-root .nha-fg input[readonly] { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-muted, #62657E); cursor: not-allowed; }
    .nha-root .sar-opt-sub { font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); }
    .nha-ss-dropdown mark { background: var(--afims-warning-soft, #FFF8DB); color: inherit; border-radius: 2px; padding: 0 1px; }

    /* ── Supporting documents ───────────────────────────────── */
    .nha-root .sar-doc-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: flex-start; margin-bottom: 12px; }
    .nha-root .sar-doc-label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600; color: var(--afims-text, #1A1B21); }
    .nha-root .sar-doc-rm { margin-top: 22px; background: none; border: 1px solid var(--afims-danger-soft, #FAE1E3); border-radius: 6px; padding: 7px 10px;
        cursor: pointer; color: var(--afims-danger, #DC3545); display: flex; align-items: center; justify-content: center; transition: background .15s; }
    .nha-root .sar-doc-rm:hover { background: var(--afims-danger-soft, #FAE1E3); }
    .nha-root .sar-dz { position: relative; border: 2px dashed var(--afims-border, #E4ECF2); border-radius: 8px; background: var(--afims-surface-subtle, #F4F7FA);
        padding: 12px; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; }
    .nha-root .sar-dz.drag-over { border-color: var(--afims-primary, #0035A9); background: var(--afims-primary-soft, #E6EBF6); }
    .nha-root .sar-dz.uploading { pointer-events: none; opacity: .6; }
    .nha-root .sar-dz input[type="file"] { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; padding: 0; border: 0; }
    .nha-root .sar-dz svg { width: 18px; height: 18px; color: var(--afims-text-disabled, #A9AAB8); margin: 0 auto 4px; display: block; }
    .nha-root .sar-dz p { margin: 0; font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
    .nha-root .sar-dz small { font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); }
    .nha-root .sar-chip { display: none; align-items: center; gap: 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px;
        padding: 8px 10px; background: var(--afims-surface, #FFFFFF); font-size: 12.5px; }
    .nha-root .sar-chip-name { font-weight: 500; color: var(--afims-heading, #000E3E); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 320px; }
    .nha-root a.sar-chip-name { text-decoration: underline; }
    .nha-root .sar-chip-size { color: var(--afims-text-muted, #62657E); font-size: 11px; white-space: nowrap; }
    .nha-root .sar-chip-x { margin-left: auto; background: none; border: none; color: var(--afims-danger, #DC3545); cursor: pointer; font-size: 12px; }
    .nha-root .sar-doc-note { display: none; margin: 6px 0 0; font-size: 11.5px; color: var(--afims-warning-text, #7E6908); }
    .nha-root .sar-doc-note.error { color: var(--afims-danger, #DC3545); }

    /* ── "What are you adding?" — the first thing on the form (see $sarTypeFirst) ── */
    .nha-root .sar-typefirst { margin-bottom: 20px; }
    .nha-root .sar-seg { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; border-radius: 12px; }
    .nha-root .sar-seg-btn { display: flex; flex-direction: column; align-items: flex-start; gap: 2px; padding: 12px 16px; text-align: left; cursor: pointer;
        border: 1.5px solid var(--afims-border, #E4ECF2); border-radius: 12px; background: var(--afims-surface, #FFFFFF);
        font-family: inherit; color: var(--afims-text, #1A1B21); transition: border-color .15s, background .15s, box-shadow .15s; }
    .nha-root .sar-seg-btn:hover { border-color: var(--afims-heading-fill, #000E3E); }
    .nha-root .sar-seg-btn strong { font-size: 15px; letter-spacing: .02em; color: var(--afims-heading, #000E3E); }
    .nha-root .sar-seg-btn span { font-size: 11.5px; line-height: 1.35; color: var(--afims-text-muted, #62657E); }
    .nha-root .sar-seg-btn.active { border-color: var(--afims-heading-fill, #000E3E); background: var(--afims-heading-fill, #000E3E); box-shadow: 0 2px 10px rgba(0, 14, 62, .18); }
    .nha-root .sar-seg-btn.active strong, .nha-root .sar-seg-btn.active span { color: #fff; }
    .nha-root .sar-seg.sar-invalid { outline: 2px solid var(--afims-danger, #DC3545); outline-offset: 3px; }

    /* ── Info card ──────────────────────────────────────────── */
    .nha-root .sar-info { display: flex; gap: 10px; margin-top: 18px; padding: 12px 14px; border-radius: 8px;
        background: var(--afims-primary-soft, #E6EBF6); font-size: 12px; color: var(--afims-text, #1A1B21); }
    .nha-root .sar-info svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; color: var(--afims-primary, #0035A9); }
    .nha-root .sar-info p { margin: 0 0 4px; font-weight: 700; }
    .nha-root .sar-info ul { margin: 0; padding-left: 18px; }
</style>

<!-- The wrapper makes the shared .nha-overlay / .nha-modal rules (scoped to .nha-root) apply wherever this
     form is included; padding/background are neutralised so it takes no space. -->
<div class="nha-root" style="padding:0;background:none;">
<div id="sar-overlay" class="nha-overlay no-print">
    <form id="sar-form" class="nha-modal nha-modal-lg sar-modal" novalidate autocomplete="off">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="sar-title">Fund Tracking — New Entry</h3>
            <button type="button" class="nha-modal-close" id="sar-close" aria-label="Close">&times;</button>
        </div>

        <div class="nha-modal-body" id="sar-body">
            <div id="sar-err" class="sar-err" role="alert"></div>

            <!-- ── What are you adding? (only when the host asks for it: $sarTypeFirst) ────────── -->
            <div class="sar-sec sar-typefirst" id="sar-typefirst" style="display:none;">
                <h4 class="sar-sec-hd">What are you adding? <span class="nha-req">*</span></h4>
                <div class="sar-seg" id="sar-seg" role="radiogroup" aria-label="What are you adding?"></div>
            </div>

            <!-- ── NCA Details — an NCA's few fields gathered in one place (syncNcaLayout moves them here and back) ── -->
            <div class="sar-sec" id="sar-nca-sec" style="display:none;">
                <h4 class="sar-sec-hd">NCA Details</h4>
                <div class="sar-nca-grid" id="sar-nca-grid"></div>
            </div>

            <!-- ── Tracking Classification ──────────────────────────── -->
            <div class="sar-sec" id="sar-sec-class">
                <h4 class="sar-sec-hd">Tracking Classification</h4>
                <!-- Tracking Type (dropdown, or the "What are you adding?" buttons above) | the parent field. A SARO has no parent, so on a
                     "What are you adding?" host this whole row is out of the way for a SARO (sar syncParentMode). -->
                <div class="nha-fg-row" id="sar-class-row">
                    <div class="nha-fg" id="sar-type-group">
                        <label for="sar-type">Tracking Type <span class="nha-req">*</span></label>
                        <select id="sar-type"><option value="">Loading…</option></select>
                    </div>
                    <div class="nha-fg" id="sar-parent-group">
                        <label for="sar-parent" id="sar-parent-label">Parent Fund</label>
                        <input type="text" id="sar-parent" placeholder="Search SARO ID…">
                        <input type="hidden" id="sar-parent-id" value="">
                        <span class="sar-hint" id="sar-parent-hint"></span>
                    </div>
                </div>
                <div class="nha-fg-row" id="sar-nature-row">
                    <div class="nha-fg" id="sar-nature-group" style="margin-bottom:0;">
                        <label for="sar-nature">Nature of Fund</label>
                        <select id="sar-nature"><option value="">Loading…</option></select>
                        <span class="sar-hint">Insertions, Equity, GAAAO, Unprogrammed or SUPPLEMENTAL</span>
                    </div>
                    <!-- SARO only (syncNature): a searchable list of fims.sub_program; the code picked is what is saved in fund_tracking.sub_program -->
                    <div class="nha-fg" id="sar-sub-group" style="margin-bottom:0;display:none;">
                        <label for="sar-sub">Sub-Program</label>
                        <input type="text" id="sar-sub" placeholder="Search sub-program code, name or program…" autocomplete="off">
                        <input type="hidden" id="sar-sub-code" value="">
                        <span class="sar-hint">Link this SARO to a sub-program (optional)</span>
                    </div>
                </div>
            </div>

            <!-- ── Fiscal Year ───────────────────────────────────────── -->
            <div class="sar-sec" id="sar-sec-year">
                <h4 class="sar-sec-hd">Fiscal Year &amp; Date</h4>
                <div class="nha-fg-row">
                    <!-- both are typed in: the Fiscal Year as a 4-digit year, the Date with the date picker (neither is filled in for the user) -->
                    <div class="nha-fg" id="sar-year-group" style="margin-bottom:0;">
                        <label for="sar-year">Fiscal Year <span class="nha-req">*</span></label>
                        <input type="text" id="sar-year" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="e.g. 2026">
                    </div>
                    <div class="nha-fg" id="sar-date-group" style="margin-bottom:0;">
                        <label for="sar-date">Date <span class="nha-req">*</span></label>
                        <input type="date" id="sar-date">
                    </div>
                </div>
            </div>

            <!-- ── Fund Information ──────────────────────────────────── -->
            <div class="sar-sec" id="sar-sec-fund">
                <h4 class="sar-sec-hd">Fund Information</h4>
                <div class="nha-fg-row">
                    <div class="nha-fg" id="sar-ref-group">
                        <label for="sar-ref" id="sar-ref-label">SARO ID <span class="nha-req">*</span></label>
                        <input type="text" id="sar-ref" maxlength="225" placeholder="Enter SARO ID">
                        <div class="sar-ref-hintrow">
                            <span class="sar-hint" id="sar-ref-hint"></span>
                            <!-- GAAAO only: fills the field in with the generated reference again (syncReference / refreshGenerated) -->
                            <button type="button" class="sar-ref-gen" id="sar-ref-gen" hidden title="Fill in the reference generated from the Nature of Fund and Fiscal Year">Generate</button>
                        </div>
                    </div>
                    <div class="nha-fg" id="sar-amount-group">
                        <label for="sar-amount">Amount <span class="nha-req">*</span></label>
                        <div class="sar-money"><span>&#8369;</span><input type="text" inputmode="decimal" id="sar-amount" data-money placeholder="0.00"></div>
                        <span class="sar-hint" id="sar-amount-hint">Total allotment amount</span>
                    </div>
                </div>

                <!-- Description: long text (fims.fund_tracking.description is TEXT), so a full-width box. A Supplemental record has Purpose instead. -->
                <div class="nha-fg" id="sar-name-group">
                    <label for="sar-name">Description</label>
                    <textarea id="sar-name" rows="3" placeholder="e.g. Description of this fund record"></textarea>
                </div>

                <!-- Purpose is for SUPPLEMENTAL only (sar_syncNature); it stays in the DOM so an existing record's
                     purpose still round-trips when the nature of fund is something else. -->
                <div class="nha-fg" id="sar-purpose-group" style="display:none;">
                    <label for="sar-purpose">Purpose</label>
                    <textarea id="sar-purpose" rows="2" placeholder="e.g. Typhoon Response Fund"></textarea>
                </div>

                <div class="nha-fg-row">
                    <div class="nha-fg" id="sar-bank-group" style="margin-bottom:0;">
                        <label for="sar-bank">Bank Account No.</label>
                        <input type="text" id="sar-bank" maxlength="225" placeholder="e.g. 0702-1068-34">
                        <span class="sar-hint">Optional</span>
                    </div>
                    <div></div>
                </div>

                <!-- An NCA released under a SARO: what that SARO has left once this NCA is deducted (syncSaroBalance) -->
                <div class="sar-saro-bal" id="sar-saro-bal" style="display:none;" aria-live="polite"></div>
            </div>

            <!-- ── Supplemental Details (Nature of Fund = SUPPLEMENTAL only) ── -->
            <div class="sar-sec" id="sar-supp-section" style="display:none;">
                <h4 class="sar-sec-hd">Supplemental Details</h4>

                <div class="nha-fg-row">
                    <div class="nha-fg">
                        <label for="sar-supp-status">Status</label>
                        <select id="sar-supp-status">
                            <option value="">Pending</option>
                            <option value="APPROVED">Approved</option>
                            <option value="DENIED">Denied</option>
                        </select>
                        <span class="sar-hint">Pending until you choose Approved or Denied; Approved is also picked when a Date of OP Approval is entered</span>
                    </div>
                    <div class="nha-fg">
                        <label for="sar-supp-region">Region</label>
                        <select id="sar-supp-region"><option value="">— none —</option></select>
                    </div>
                </div>

                <div class="nha-fg-row">
                    <div class="nha-fg">
                        <label for="sar-supp-budget">Budgetary Requirement</label>
                        <div class="sar-money"><span>&#8369;</span><input type="text" inputmode="decimal" id="sar-supp-budget" data-money placeholder="0.00"></div>
                    </div>
                    <div></div>
                </div>

                <h5 class="sar-sub-hd">Routing &amp; Turnaround</h5>
                <span class="sar-hint" style="margin:0 0 12px;">Day counts are working days (Mon–Fri, excluding holidays), calculated from the dates. They cannot be edited by hand.</span>

                <div class="sar-cols-3">
                    <div class="nha-fg">
                        <label for="sar-supp-date_received">Date Received</label>
                        <input type="date" id="sar-supp-date_received">
                    </div>
                    <div class="nha-fg">
                        <label for="sar-supp-date_forwarded_ndrrmc">Date Forwarded to NDRRMC (a)</label>
                        <input type="date" id="sar-supp-date_forwarded_ndrrmc">
                    </div>
                    <div class="nha-fg">
                        <label for="sar-supp-days_fmd">No. of Days with FMD</label>
                        <input type="number" id="sar-supp-days_fmd" min="0" step="1" readonly tabindex="-1">
                        <span class="sar-hint">Date Received to (a)</span>
                    </div>
                </div>
                <div class="sar-cols-4">
                    <div class="nha-fg">
                        <label for="sar-supp-date_forwarded_op">Date Forwarded to OP (b)</label>
                        <input type="date" id="sar-supp-date_forwarded_op">
                    </div>
                    <div class="nha-fg">
                        <label for="sar-supp-days_ndrrmc">No. of Days with NDRRMC</label>
                        <input type="number" id="sar-supp-days_ndrrmc" min="0" step="1" readonly tabindex="-1">
                        <span class="sar-hint">(a) to (b)</span>
                    </div>
                    <div class="nha-fg">
                        <label for="sar-supp-date_approved_op">Date of OP Approval (c)</label>
                        <input type="date" id="sar-supp-date_approved_op">
                    </div>
                    <div class="nha-fg">
                        <label for="sar-supp-days_op">No. of Days with OP</label>
                        <input type="number" id="sar-supp-days_op" min="0" step="1" readonly tabindex="-1">
                        <span class="sar-hint">(b) to (c)</span>
                    </div>
                </div>
                <div class="nha-fg-row">
                    <div class="nha-fg" style="margin-bottom:0;">
                        <label for="sar-supp-days_total">Total No. of Days</label>
                        <input type="number" id="sar-supp-days_total" min="0" step="1" readonly tabindex="-1">
                        <span class="sar-hint">Days with FMD + NDRRMC + OP</span>
                    </div>
                    <div></div>
                </div>
            </div>

            <!-- ── Supporting Documents ───────────────────────────────── -->
            <div class="sar-sec">
                <h4 class="sar-sec-hd">Supporting Documents</h4>
                <span class="sar-hint" style="margin:0 0 12px;">Drag a file onto a row or click it to upload. The document name is taken from the file.</span>
                <div id="sar-docs"></div>
                <button type="button" class="nha-btn nha-btn-ghost nha-btn-sm" id="sar-doc-add">
                    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    Add Document
                </button>
            </div>

            <div class="sar-info">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p>Important Reminders</p>
                    <ul>
                        <li>Tracking Type, Fiscal Year and Amount are required; Nature of Fund is optional. The SARO ID is filled in for you (and can be edited, or generated again) only when the Nature of Fund is GAAAO — otherwise enter it yourself</li>
                        <li>An NCA is its own entry: choose NCA above, then pick the SARO it was released under (required) — the NCA's amount is deducted from that SARO. A SARO has no parent</li>
                        <li>The record is dated the day it is saved — there is no date to enter</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-ghost" id="sar-cancel">Cancel</button>
            <button type="submit" class="nha-btn nha-btn-primary" id="sar-save">Save Record</button>
        </div>
    </form>
</div>
</div><!-- /.nha-root (modal wrapper) -->

<script>
(function () {
    'use strict';

    const API = <?= json_encode($apiBase) ?>;
    const ALLOWED_TYPES = <?= json_encode($_sar_types) ?>;
    const SUPP_FIELDS = <?= json_encode($_sar_supp) ?>;   // false → no Purpose / Supplemental Details, whatever the nature of fund
    const EXCLUDE_NATURES = <?= json_encode($_sar_exclude_natures) ?>;   // natures of fund (upper-case names) this host leaves out
    const PARENT_TYPES = <?= json_encode($_sar_parent_types) ?>;         // tracking types the Parent Fund search is limited to; [] = any
    const TYPE_FIRST = <?= json_encode($_sar_type_first) ?>;             // true → the form opens with "What are you adding?" (SARO / NCA)
    const TYPE_HINTS = { SARO: 'Special Allotment Release Order', NCA: 'Notice of Cash Allocation — released under a SARO', OR: 'Official Receipt — linked to a Trust Fund' };
    const U = {
        read:    id => API + '/fund_tracking/read/' + encodeURIComponent(id),
        add:     API + '/fund_tracking/add',
        update:  id => API + '/fund_tracking/update/' + encodeURIComponent(id),
        list:    API + '/fund_tracking/list',
        all:     API + '/fund_tracking/all',
        upload:  API + '/upload/file',
        multi:   API + '/upload/multi-upload',
        types:   API + '/fund-tracking-type/all',
        natures: API + '/nature-of-fund/all',
        subs:    API + '/sub_program/all',
        regions: API + '/responsibility-center/all?office_type=Regional',
    };
    const MAX_BYTES = 25 * 1024 * 1024;

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Escaped text with the first case-insensitive match of q wrapped in <mark>
    const hl = (text, q) => {
        text = String(text ?? '');
        const i = q ? text.toLowerCase().indexOf(q.toLowerCase()) : -1;
        return i < 0 ? esc(text) : esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
    };

    const S = {
        editId: null,
        saving: false,
        opening: false,
        loaded: {},            // which lookups have been read
        types: [],             // [{id, code, name}]
        subs: [],              // sub-programs [{sub_program_code, sub_program_name, program_code, program_name}] — the SARO's Sub-Program list
        parentMode: 'search',  // 'search' (any record, by reference) | 'trust' (OR → pick a Trust Fund)
        parentKind: '',        // what the parent field is for: 'none' (hidden — a SARO) | 'saro' (an NCA's SARO) | 'search' | 'trust'
        trustPromise: null,    // the Trust Fund list, read once per open
        docsDirty: false,      // supporting documents were added / removed / replaced since the form opened
        docSeq: 0,
        hadSupp: false,        // the record being edited already has supplemental details
        typeLocked: false,     // a NEW entry opened with a type (the registry's "+" buttons, SARO or NCA): the type is settled, so "What are you adding?" is hidden
        saroBal: { id: null, ref: '', amount: 0, released: 0 },   // the SARO an NCA is released under, as last read (syncSaroBalance)
        saroBalSeq: 0,         // guards that read against a newer pick
    };

    /* ── HTTP ─────────────────────────────────────────────────────
       Resolves with the parsed body on { success: true }; otherwise throws the server's message (with the
       body on err.data — a create that half-failed carries the new id there). */
    async function api(url, opts = {}) {
        const headers = typeof opts.body === 'string' ? { 'Content-Type': 'application/json' } : {};
        const res = await fetch(url, { credentials: 'same-origin', ...opts, headers: { ...headers, ...(opts.headers || {}) } });
        let json = null;
        try { json = await res.json(); } catch (_) { /* not JSON */ }
        if (!res.ok || !json || json.success === false) {
            const err = new Error((json && (json.message || json.error)) || ('HTTP ' + res.status));
            err.data = json;
            throw err;
        }
        return json;
    }

    function toast(msg, type = 'info') {
        const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
        let wrap = document.getElementById('nha-toast-wrap');
        if (!wrap) {
            wrap = document.createElement('div');
            wrap.id = 'nha-toast-wrap';
            wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
            document.body.appendChild(wrap);
        }
        const color = colors[type] || colors.info;
        const t = document.createElement('div');
        t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
        const span = document.createElement('span');
        span.style.flex = '1';
        span.textContent = msg;
        const x = document.createElement('button');
        x.type = 'button';
        x.innerHTML = '&times;';
        x.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
        x.addEventListener('click', () => t.remove());
        t.append(span, x);
        wrap.appendChild(t);
        setTimeout(() => t.remove(), 5000);
    }

    function showErr(msg) {
        const el = $('sar-err');
        el.textContent = msg || '';
        el.style.display = msg ? 'block' : 'none';
        if (msg) $('sar-body').scrollTop = 0;
    }

    /* ── Money inputs: comma-separated while typing, 2dp on blur ── */
    const parseMoney = str => parseFloat(String(str ?? '').replace(/,/g, ''));
    const fmtMoney = v => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function fmtMoneyTyping(str) {
        const s = String(str).replace(/[^\d.]/g, '');
        const dot = s.indexOf('.');
        let intPart = dot === -1 ? s : s.slice(0, dot);
        const dec = dot === -1 ? null : s.slice(dot + 1).replace(/\./g, '').slice(0, 2);
        intPart = intPart.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return dec === null ? intPart : (intPart || '0') + '.' + dec;
    }

    // one money input; also called for the inputs of NCA rows, which are added after the form is wired
    function wireMoneyInput(input) {
        input.addEventListener('input', () => {
            const before = input.value;
            const caret = input.selectionStart;
            const sig = before.slice(0, caret).replace(/[^\d.]/g, '').length;   // digits/dots left of the caret
            const formatted = fmtMoneyTyping(before);
            if (formatted === before) return;
            input.value = formatted;
            let pos = 0, seen = 0;
            while (pos < formatted.length && seen < sig) { if (/[\d.]/.test(formatted.charAt(pos))) seen++; pos++; }
            input.setSelectionRange(pos, pos);
        });
        input.addEventListener('blur', () => {
            if (input.value === '') return;
            const v = parseMoney(input.value);
            if (!isNaN(v)) input.value = fmtMoney(v);
        });
    }

    function wireMoney() {
        document.querySelectorAll('#sar-form input[data-money]').forEach(wireMoneyInput);
    }

    /* ── Searchable combobox ──────────────────────────────────────
       A text box + hidden value + a dropdown (the shared .nha-ss-dropdown look, positioned `fixed` from the
       box so the modal's scrolling never clips it and it floats above the overlay).
         source(q)   → the items (array or Promise)   minChars() → how much must be typed before it is asked
         local()     → true when source() returns the FULL list, filtered here with match(item)
         key / label → the saved value / the text kept in the box once picked        render(item, q) → option html
         onChange()  → (optional) called when the USER changes the value: picks one, or empties it
         input / hidden are element ids or the elements themselves (an NCA row's picker is made on its own elements);
         destroy() removes the dropdown and its listeners when such a row goes away. */
    function makeCombo(cfg) {
        const el = x => (typeof x === 'string' ? $(x) : x);
        const input = el(cfg.input), hidden = el(cfg.hidden);
        const box = document.createElement('div');
        box.className = 'nha-ss-dropdown';
        box.style.fontFamily = "'IBM Plex Sans', sans-serif";   // it lives on <body>, outside .nha-root, so it doesn't inherit the page font
        document.body.appendChild(box);
        let items = [], seq = 0, timer = null, atFocus = '';   // atFocus: the value when the box got focus

        const position = () => {
            const r = input.getBoundingClientRect();
            Object.assign(box.style, { position: 'fixed', left: r.left + 'px', top: (r.bottom + 2) + 'px', width: Math.max(r.width, 280) + 'px' });
        };
        const hide = () => { box.style.display = 'none'; };
        const show = html => { box.innerHTML = html; position(); box.style.display = 'block'; };
        const note = text => show(`<div class="nha-ss-empty">${esc(text)}</div>`);

        function render(list, q) {
            items = list;
            if (!list.length) return note(q ? cfg.noMatch : cfg.empty());
            show(list.slice(0, 60).map((it, i) => `<div class="nha-ss-opt" data-i="${i}">${cfg.render(it, q)}</div>`).join(''));
        }

        async function refresh(q) {
            const mine = ++seq;
            if (q.length < cfg.minChars()) return note(cfg.empty());
            note('Loading…');
            try {
                let list = await cfg.source(q);
                if (mine !== seq) return;                       // a newer keystroke superseded this one
                if (cfg.local() && q) { const ql = q.toLowerCase(); list = list.filter(it => cfg.match(it).toLowerCase().includes(ql)); }
                render(list, q);
            } catch (e) {
                if (mine === seq) note('Could not load: ' + e.message);
            }
        }

        function pick(item) {
            hidden.value = cfg.key(item);
            input.value = cfg.label(item);
            input.classList.add('sar-has-value');
            hide();
            atFocus = hidden.value;
            if (cfg.onChange) cfg.onChange();
        }

        input.addEventListener('input', () => {
            hidden.value = '';
            input.classList.remove('sar-has-value');
            clearTimeout(timer);
            timer = setTimeout(() => refresh(input.value.trim()), cfg.local() ? 0 : 300);
        });
        // a picked item leaves its label in the box, which is not a search term — show the full list instead
        input.addEventListener('focus', () => { atFocus = hidden.value; refresh(hidden.value ? '' : input.value.trim()); });
        input.addEventListener('blur', () => setTimeout(() => {
            hide();
            if (!hidden.value) { input.value = ''; input.classList.remove('sar-has-value'); }   // nothing picked → nothing kept
            if (hidden.value !== atFocus) { atFocus = hidden.value; if (cfg.onChange) cfg.onChange(); }   // the user emptied it
        }, 150));
        box.addEventListener('mousedown', e => {
            const el = e.target.closest('.nha-ss-opt');
            if (!el) return;
            e.preventDefault();                                  // keep focus on the input
            pick(items[Number(el.dataset.i)]);
        });
        const reposition = () => { if (box.style.display === 'block') position(); };
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);

        return {
            set(key, label) { hidden.value = key; input.value = label; input.classList.add('sar-has-value'); },
            reset() { hidden.value = ''; input.value = ''; input.classList.remove('sar-has-value'); hide(); },
            hide,
            value: () => hidden.value,
            destroy() {
                box.remove();
                window.removeEventListener('scroll', reposition, true);
                window.removeEventListener('resize', reposition);
            },
        };
    }

    /* ── Lookups (read once; a failed one is retried the next time the form opens) ── */
    const opt = (value, text, data = {}) => {
        const o = document.createElement('option');
        o.value = value;
        o.textContent = text;
        Object.entries(data).forEach(([k, v]) => { o.dataset[k] = v; });
        return o;
    };
    const fill = (id, first, options) => { const el = $(id); el.innerHTML = ''; el.appendChild(opt('', first)); options.forEach(o => el.appendChild(o)); };

    const LOOKUPS = {
        types: {
            label: 'tracking types', url: U.types,
            apply(rows) {
                S.types = rows
                    .filter(t => !t.deleted_at && ALLOWED_TYPES.includes(String(t.fund_tracking_code || '').toUpperCase()))
                    .map(t => ({ id: Number(t.fund_tracking_type_id), code: String(t.fund_tracking_code).toUpperCase(), name: t.fund_tracking_name || '' }));
                fill('sar-type', 'Select tracking type', S.types.map(t => opt(t.id, t.code + (t.name ? ' — ' + t.name : ''), { code: t.code })));
                renderTypeChooser();
            },
        },
        natures: {
            label: 'natures of fund', url: U.natures,
            apply(rows) {
                fill('sar-nature', '— None —', rows
                    .filter(n => n.is_active !== false && !EXCLUDE_NATURES.includes(String(n.name || '').trim().toUpperCase()))
                    .map(n => opt(n.nature_of_fund_id, n.name, { name: n.name || '' })));
            },
        },
        subs: {
            label: 'sub-programs', url: U.subs,
            apply(rows) { S.subs = rows; },
        },
        regions: {
            label: 'regions', url: U.regions,
            apply(rows) {
                fill('sar-supp-region', '— none —', rows
                    .filter(r => String(r.status || 'Active').toLowerCase() !== 'inactive')
                    .sort((a, b) => String(a.rc_code).localeCompare(String(b.rc_code), undefined, { numeric: true }))
                    .map(r => opt(r.rc_name, r.rc_name)));
            },
        },
    };

    async function ensureLookups() {
        // the regions only feed Supplemental Details — not worth a request (or a "could not load" error) when that is off
        const pending = Object.keys(LOOKUPS).filter(k => !S.loaded[k] && (SUPP_FIELDS || k !== 'regions'));
        const results = await Promise.allSettled(pending.map(k => api(LOOKUPS[k].url)));
        const failed = [];
        results.forEach((r, i) => {
            const k = pending[i];
            if (r.status === 'fulfilled') { LOOKUPS[k].apply(r.value.data || []); S.loaded[k] = true; }
            else failed.push(`${LOOKUPS[k].label} (${r.reason.message})`);
        });
        if (failed.length) showErr('Could not load ' + failed.join(', ') + '. Close and reopen this form to try again.');
    }

    /* ── Parent Fund picker ─────────────────────────────────────── */
    const typeCodeOf = r => String(r.tracking_type?.fund_tracking_code || '').toUpperCase() || 'N/A';

    // The Trust Fund list the Parent Fund shows for an OR (all pages, read once per open)
    function loadTrustFunds() {
        if (S.trustPromise) return S.trustPromise;
        S.trustPromise = (async () => {
            const rows = [];
            let page = 1, last = 1;
            do {
                const json = await api(`${U.list}?type=TRUST_FUND&limit=500&page=${page}`);
                rows.push(...(json.data || []));
                last = json.pagination?.last_page ?? 1;
                page++;
            } while (page <= last);
            return rows;
        })();
        S.trustPromise.catch(() => { S.trustPromise = null; });   // let the next focus retry
        return S.trustPromise;
    }

    // a Trust Fund as the OR's parent shows by its agency and SARO ID
    const trustLabel = r => {
        const ref = r.saro_id || '';
        const head = (r.agency && r.agency.name) || '';
        return head ? head + (ref ? ' — ' + ref : '') : (ref || 'Trust Fund #' + r.id);
    };

    // Whether a record may be offered as a Parent Fund (search mode): its tracking type is one the host limits the search to
    // (PARENT_TYPES, empty = any) and its nature of fund is not one the host leaves out (EXCLUDE_NATURES). A record with no
    // nature of fund is always fine.
    // For an NCA on a host that asks "What are you adding?" the field is the SARO it was released under, so only SAROs are offered.
    const parentAllowed = r =>
        (!PARENT_TYPES.length || PARENT_TYPES.includes(typeCodeOf(r)))
        && (!ncaUnderSaro() || typeCodeOf(r) === 'SARO')
        && !EXCLUDE_NATURES.includes(String(r.nature_of_fund?.name || '').trim().toUpperCase());

    const parentCombo = makeCombo({
        input: 'sar-parent', hidden: 'sar-parent-id',
        minChars: () => (S.parentMode === 'trust' ? 0 : 1),
        local: () => S.parentMode === 'trust',
        // search mode: ask the API (GET /fund_tracking/all?search=) on every (debounced) keystroke; trust mode: the cached Trust Fund list
        source: async q => {
            const rows = S.parentMode === 'trust'
                ? await loadTrustFunds()
                : (await api(`${U.all}?lean=1&search=${encodeURIComponent(q)}`)).data || [];
            // a record can't be its own parent; and in search mode the host may limit the parents to some tracking types
            // and leave some natures of fund out (the OR → Trust Fund pick-list is exempt: a Trust Fund is what it needs)
            return rows.filter(r => Number(r.id) !== S.editId && (S.parentMode === 'trust' || parentAllowed(r)));
        },
        match: r => [r.saro_id, r.agency && r.agency.name].join(' '),
        key: r => r.id,
        label: r => (S.parentMode === 'trust' ? trustLabel(r) : (r.saro_id || '#' + r.id) + ' (' + typeCodeOf(r) + ')'),
        render: (r, q) => {
            if (S.parentMode === 'trust') {
                return `<span class="nha-ss-opt-label" style="white-space:normal;"><strong>${r.agency && r.agency.name ? esc(r.agency.name) : '<em>No agency</em>'}</strong><br><span class="sar-opt-sub">${r.saro_id ? hl(r.saro_id, q) : '—'}</span></span>`;
            }
            return `<span class="nha-ss-opt-code">${hl(r.saro_id || '#' + r.id, q)}</span><span class="nha-ss-opt-label">${esc(typeCodeOf(r))}${r.description ? ' · ' + esc(r.description) : ''}</span>`;
        },
        empty: () => (S.parentMode === 'trust' ? 'No trust funds found' : ncaUnderSaro() ? 'Type to search SAROs by SARO ID' : 'Type to search SARO IDs'),
        noMatch: 'No matching fund tracking records',
        onChange: () => syncSaroBalance(),   // an NCA's SARO was picked / emptied
    });

    /* ── Sub-Program picker (SARO only) ─────────────────────────────
       A searchable list over fims.sub_program (GET /sub_program/all, read once). What is stored in fims.fund_tracking.sub_program is the
       sub-program CODE (e.g. 001-001); the box shows "code — name". */
    const subLabel = s => s.sub_program_code + ' — ' + s.sub_program_name;
    const subCombo = makeCombo({
        input: 'sar-sub', hidden: 'sar-sub-code',
        minChars: () => 0, local: () => true,
        source: () => S.subs,
        match: s => [s.sub_program_code, s.sub_program_name, s.program_code, s.program_name].join(' '),
        key: s => s.sub_program_code,
        label: subLabel,
        render: (s, q) => `<span class="nha-ss-opt-code">${hl(s.sub_program_code, q)}</span><span class="nha-ss-opt-label">${hl(s.sub_program_name, q)}${s.program_name ? ' · ' + esc(s.program_name) : ''}</span>`,
        empty: () => 'No sub-programs found', noMatch: 'No matching sub-programs',
    });

    /* ── Field logic ────────────────────────────────────────────── */
    const selOpt = id => { const el = $(id); return el.options[el.selectedIndex] || null; };
    // Everything Supplemental-specific on this form (Purpose instead of Name, Supplemental Details, what is
    // sent on save) goes through here, so a host that turns SUPP_FIELDS off gets the plain SARO / NCA form.
    const isSupplemental = () => SUPP_FIELDS && ((selOpt('sar-nature') || {}).dataset?.name || '').trim().toUpperCase() === 'SUPPLEMENTAL';
    const isGaaao = () => ((selOpt('sar-nature') || {}).dataset?.name || '').trim().toUpperCase() === 'GAAAO';
    const isOr = () => ((selOpt('sar-type') || {}).dataset?.code || '').trim().toUpperCase() === 'OR';
    const isNca = () => ((selOpt('sar-type') || {}).dataset?.code || '').trim().toUpperCase() === 'NCA';
    const isSaro = () => ((selOpt('sar-type') || {}).dataset?.code || '').trim().toUpperCase() === 'SARO';
    // what the record's number field is called: the SARO ID of a SARO, the NCA ID of an NCA, the OR No. of an OR
    const idName = () => (isOr() ? 'OR No.' : isNca() ? 'NCA ID' : 'SARO ID');
    // On a host that asks "What are you adding?": a SARO has no parent at all (the field is not on the form), and an NCA's one
    // field is the SARO it was released under ("SARO", offering SAROs only). Any other host keeps the plain "Parent Fund" field.
    const parentHidden = () => typeChooserOn() && isSaro();
    const ncaUnderSaro = () => typeChooserOn() && isNca();
    // An NCA has no Nature of Fund, Name or Bank Account No. (on that same host): those fields are not on the form and nothing is
    // saved for them (see syncNature).
    const ncaFieldsHidden = () => ncaUnderSaro();

    // "What are you adding?" — one button per allowed tracking type, standing in for the Tracking Type dropdown (which stays, hidden,
    // as the one place the choice is kept: the rest of the form reads it, and a button just sets it and fires its change event).
    // Only when the host asks for it (TYPE_FIRST) and there is something to choose between.
    const typeChooserOn = () => TYPE_FIRST && S.types.length > 1;
    function renderTypeChooser() {
        const on = typeChooserOn();
        $('sar-typefirst').style.display = on && !S.typeLocked ? '' : 'none';
        $('sar-type-group').style.display = on ? 'none' : '';
        if (!on) return;
        $('sar-seg').innerHTML = S.types.map(t => `<button type="button" class="sar-seg-btn" role="radio" aria-checked="false" data-id="${esc(t.id)}">
            <strong>${esc(t.code)}</strong><span>${esc(TYPE_HINTS[t.code] || t.name || '')}</span></button>`).join('');
        syncTypeChooser();
    }
    // shows the dropdown's current value on the buttons (called after anything that sets it)
    function syncTypeChooser() {
        if (!typeChooserOn()) return;
        const cur = $('sar-type').value;
        $('sar-seg').querySelectorAll('.sar-seg-btn').forEach(b => {
            const on = b.dataset.id === cur;
            b.classList.toggle('active', on);
            b.setAttribute('aria-checked', on ? 'true' : 'false');
        });
        $('sar-seg').classList.remove('sar-invalid');
    }

    // Supplemental Funds show Purpose and Supplemental Details instead of Name.
    // An NCA has no Nature of Fund, Name or Bank Account No.: those fields go, and so does whatever was entered in them (a SARO's
    // GAAAO or name must not ride along into an NCA). Returns true when it cleared a nature that had been chosen — the caller then
    // re-syncs the SARO ID.
    function syncNature() {
        const hide = ncaFieldsHidden();
        const cleared = hide && $('sar-nature').value !== '';
        $('sar-nature-row').style.display = hide ? 'none' : '';
        $('sar-bank-group').style.display = hide ? 'none' : '';
        if (hide) { $('sar-nature').value = ''; $('sar-name').value = ''; $('sar-bank').value = ''; }
        // Sub-Program belongs to a SARO only: any other type has no such field, and a choice made while it was a SARO goes with it
        const sub = isSaro();
        $('sar-sub-group').style.display = sub ? '' : 'none';
        if (!sub) subCombo.reset();
        const supp = isSupplemental();
        $('sar-name-group').style.display = supp || hide ? 'none' : '';
        $('sar-purpose-group').style.display = supp ? '' : 'none';
        $('sar-supp-section').style.display = supp ? '' : 'none';
        syncNcaLayout();
        return cleared;
    }

    // An NCA has so few fields that, in the usual sections, each would stand alone in a half-width row. On the "What are you adding?"
    // host they are gathered into one "NCA Details" section instead — SARO, SARO ID and Fiscal Year, Amount, and the SARO's
    // balance — and the usual Tracking Classification / Fiscal Year / Fund Information sections step aside. The field groups are MOVED
    // (not copied), so their ids, values and listeners go with them; leaving NCA puts each back exactly where it was.
    const NCA_FIELDS = ['sar-parent-group', 'sar-ref-group', 'sar-year-group', 'sar-amount-group', 'sar-date-group', 'sar-saro-bal'];   // in section order
    const NCA_STEP_ASIDE = ['sar-sec-class', 'sar-sec-year', 'sar-sec-fund'];
    const ncaHome = {};
    function syncNcaLayout() {
        const on = ncaFieldsHidden();
        const grid = $('sar-nca-grid');
        if (on === (grid.dataset.on === '1')) return;   // already in the right layout
        grid.dataset.on = on ? '1' : '0';
        if (on) {
            NCA_FIELDS.forEach(id => {
                const el = $(id);
                ncaHome[id] = { parent: el.parentNode, next: el.nextSibling };
                grid.appendChild(el);
            });
        } else {
            NCA_FIELDS.slice().reverse().forEach(id => { const h = ncaHome[id]; h.parent.insertBefore($(id), h.next); });
        }
        $('sar-nca-sec').style.display = on ? '' : 'none';
        NCA_STEP_ASIDE.forEach(id => { $(id).style.display = on ? 'none' : ''; });
        $('sar-amount-hint').textContent = on ? 'Amount of this NCA — deducted from the SARO' : 'Total allotment amount';
    }

    // The reference generated for a GAAAO record — "<NATURE OF FUND>-<FISCAL YEAR>", e.g. GAAAO-2026 — the same format the API generates
    // (FundTrackingServices::generateReferenceNumber). '' until the nature of fund and the fiscal year are both picked.
    function referencePreview() {
        const name = ((selOpt('sar-nature') || {}).dataset?.name || '').toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        const year = /^\d{4}$/.test($('sar-year').value) ? $('sar-year').value : '';   // a complete year only (it is typed in)
        return name && year ? name + '-' + year : '';
    }

    // GAAAO: put the generated reference in the field. It is only a DEFAULT — the field stays editable, and the last generated value is
    // remembered (dataset.auto) so that a change of Nature of Fund / Fiscal Year updates the field only while it still holds that value
    // (or nothing): a reference the user typed or edited is never overwritten. force = the Generate link: replace whatever is there.
    function refreshGenerated(force) {
        const input = $('sar-ref'), next = referencePreview();
        if (force || input.value.trim() === '' || input.value === (input.dataset.auto || '')) input.value = next;
        input.dataset.auto = next;
    }

    // GAAAO gets a reference filled in for it, but it is optional and editable (blank = the API generates it on save, and the Generate link
    // fills it in again); every other nature of fund types it in.
    // keepValue: true when populating an existing record (don't wipe the value it already has).
    function syncReference(keepValue) {
        const input = $('sar-ref'), label = $('sar-ref-label'), hint = $('sar-ref-hint'), gen = $('sar-ref-gen');
        const auto = isGaaao();
        const mode = auto ? 'auto' : 'manual';
        const changed = input.dataset.mode !== mode;
        input.dataset.mode = mode;
        input.readOnly = input.disabled = false;
        gen.hidden = !auto;
        if (auto) {
            input.placeholder = 'Generated (e.g. GAAAO-2026) — or type your own';
            label.innerHTML = idName();
            hint.textContent = 'Generated from the Nature of Fund and Fiscal Year — edit it freely, or leave it blank to generate on save';
            refreshGenerated(false);
        } else {
            const or = isOr();
            input.placeholder = or ? 'Enter OR number' : 'Enter ' + idName();
            label.innerHTML = idName() + ' <span class="nha-req">*</span>';
            hint.textContent = or ? 'Enter the actual OR number' : '';
            // leaving GAAAO takes the generated value with it, but a reference the user typed or edited stays
            if (changed && !keepValue && input.value === (input.dataset.auto || '')) input.value = '';
            input.dataset.auto = '';
        }
    }

    // An OR is linked to a Trust Fund (a pick-list); anything else searches any record by SARO ID.
    // On a "What are you adding?" host a SARO has no parent field at all, and an NCA's is "SARO" (see parentHidden / ncaUnderSaro).
    function syncParentMode(keepSelection) {
        const mode = isOr() ? 'trust' : 'search';
        const hidden = mode !== 'trust' && parentHidden();
        const nca = mode !== 'trust' && ncaUnderSaro();
        const kind = hidden ? 'none' : nca ? 'saro' : mode;   // what the field is for right now
        const changed = kind !== S.parentKind;
        S.parentMode = mode;
        S.parentKind = kind;
        $('sar-parent-group').style.display = hidden ? 'none' : '';
        // a SARO has nothing in that row (the Tracking Type is the buttons above), so the row goes with the field — no empty gap
        $('sar-class-row').style.display = hidden ? 'none' : '';
        $('sar-parent-label').innerHTML = nca ? 'SARO <span class="nha-req">*</span>' : 'Parent Fund';   // an NCA must name its SARO
        $('sar-parent').placeholder = mode === 'trust' ? 'Select a trust fund or search…' : nca ? 'Search the SARO ID…' : 'Search SARO ID…';
        $('sar-parent-hint').textContent = mode === 'trust'
            ? 'Select the Trust Fund this OR is linked to'
            : nca ? 'The SARO this NCA was released under — search by SARO ID'
                  : 'Link this record to its parent fund (optional) — search by SARO ID';
        if (mode === 'trust') loadTrustFunds().catch(() => {});
        // a parent picked for another kind of field isn't valid here (a SARO has none; an NCA's must be a SARO); a hidden field never keeps one
        if ((changed && !keepSelection) || hidden) parentCombo.reset();
    }

    /* ── NCA under a SARO: what the SARO has left ───────────────────
       The amount of an NCA is deducted from the SARO it is released under:
           unreleased SARO amount = SARO amount − every NCA released under it (this one included).
       The SARO's stored Amount is never rewritten — it stays the original allotment (the Fund Sourcing dashboard already works out
       "available funds" as SARO total − NCA total, so writing the deduction into the SARO would deduct it twice). The registry's
       "Unreleased" column is the same sum. Here it is shown live while the NCA is being entered, and an NCA that is more than the SARO has left
       cannot be saved (save() refuses it, and so does the API — a SARO may never go negative). The SARO's NCAs are read once per pick (GET /fund_tracking/read/{id}); when an
       NCA is being edited its own saved amount is left out of "already released", since the amount typed replaces it. */
    const peso = v => '₱ ' + fmtMoney(v);
    async function syncSaroBalance() {
        const box = $('sar-saro-bal');
        const pid = Number($('sar-parent-id').value) || 0;
        if (!ncaUnderSaro() || !pid) { S.saroBalSeq++; box.style.display = 'none'; return; }

        if (S.saroBal.id !== pid) {
            const mine = ++S.saroBalSeq;
            box.className = 'sar-saro-bal';
            box.style.display = '';
            box.textContent = 'Reading the SARO…';
            try {
                const d = (await api(U.read(pid))).data || {};
                if (mine !== S.saroBalSeq) return;                       // a newer pick (or a close) superseded this read
                // every live NCA under the SARO counts, whatever its nature of fund — the same set the API checks the limit against
                const others = (Array.isArray(d.children) ? d.children : []).filter(c => typeCodeOf(c) === 'NCA' && Number(c.id) !== S.editId);
                S.saroBal = {
                    id: pid,
                    ref: d.saro_id || '#' + pid,
                    amount: parseFloat(d.amount) || 0,
                    released: others.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0),
                };
            } catch (e) {
                if (mine !== S.saroBalSeq) return;
                box.textContent = 'Could not read the SARO\'s amount: ' + e.message;
                return;
            }
        }

        const b = S.saroBal;
        const typed = parseMoney($('sar-amount').value);
        const nca = isNaN(typed) ? 0 : typed;
        const current = b.amount - b.released - nca;
        const row = (label, value, cls = '') => `<div class="sar-bal-row ${cls}"><span>${label}</span><b>${esc(value)}</b></div>`;
        box.className = 'sar-saro-bal' + (current < 0 ? ' sar-bal-over' : '');
        box.style.display = '';
        box.innerHTML = row('SARO Amount <span class="sar-hint" style="display:inline;margin:0 0 0 4px;">' + esc(b.ref) + '</span>', peso(b.amount))
            + (b.released ? row('Less: NCAs already released', '− ' + peso(b.released), 'sar-bal-less') : '')
            + row('Less: this NCA', '− ' + peso(nca), 'sar-bal-less')
            + row('Unreleased SARO Amount', peso(current), 'sar-bal-total')
            + (current < 0 ? `<div class="sar-bal-warn">This NCA is ${esc(peso(-current))} more than the SARO has left — it cannot be saved. Lower the amount to ${esc(peso(b.amount - b.released))} or less.</div>` : '');
    }

    // The most this NCA can be: what its SARO has left (null while the SARO is unknown). The API enforces the same limit.
    const saroLeft = () => (S.saroBal.id !== null && S.saroBal.id === (Number($('sar-parent-id').value) || 0)) ? S.saroBal.amount - S.saroBal.released : null;

    /* ── Supplemental Details ───────────────────────────────────── */
    // Working days from `from` through the day BEFORE `to` (the pricing sheet's NETWORKDAYS(from, to-1, holidays)).
    // The holiday list is the same one amortization-form.php uses — extend both yearly.
    const HOLIDAYS = new Set([
        '2025-12-24', '2025-12-25', '2025-12-29', '2025-12-30', '2025-12-31',
        '2026-01-01', '2026-01-02', '2026-02-17', '2026-04-02', '2026-04-03', '2026-04-09',
        '2026-05-01', '2026-06-12', '2026-08-19', '2026-08-21', '2026-08-31', '2026-11-02',
        '2026-11-30', '2026-12-08', '2026-12-24', '2026-12-25', '2026-12-30', '2026-12-31',
    ]);
    const ymd = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');

    function workdays(from, to) {
        const re = /^\d{4}-\d{2}-\d{2}$/;
        if (!re.test(from) || !re.test(to)) return '';
        const a = from.split('-').map(Number), b = to.split('-').map(Number);
        const cur = new Date(a[0], a[1] - 1, a[2]);
        const last = new Date(b[0], b[1] - 1, b[2] - 1);
        let n = 0;
        for (; cur <= last; cur.setDate(cur.getDate() + 1)) {
            const dow = cur.getDay();
            if (dow !== 0 && dow !== 6 && !HOLIDAYS.has(ymd(cur))) n++;
        }
        return n;
    }

    const sv = f => $('sar-supp-' + f).value;

    function suppRecalc() {
        $('sar-supp-days_fmd').value = workdays(sv('date_received'), sv('date_forwarded_ndrrmc'));
        $('sar-supp-days_ndrrmc').value = workdays(sv('date_forwarded_ndrrmc'), sv('date_forwarded_op'));
        $('sar-supp-days_op').value = workdays(sv('date_forwarded_op'), sv('date_approved_op'));
        const parts = ['days_fmd', 'days_ndrrmc', 'days_op'].map(sv);
        $('sar-supp-days_total').value = parts.every(v => v === '') ? '' : parts.reduce((sum, v) => sum + (Number(v) || 0), 0);
    }

    // The Status dropdown only offers Approved / Denied; with neither chosen the request is Pending. Entering a Date
    // of OP Approval picks Approved for you (unless a status was chosen by hand — data-manual); clearing it goes
    // back to Pending. DENIED is only ever set by hand: no date says "denied".
    function suppSyncStatus() {
        const sel = $('sar-supp-status');
        if (sel.value === 'DENIED' || sel.dataset.manual === '1') return;
        sel.value = sv('date_approved_op') ? 'APPROVED' : '';
    }

    // What is saved. Approved / Denied as chosen; while Pending the stored status still records how far the request
    // has got (In Process → At NDRRMC → At OP) from the routing dates, which the dashboard counts.
    function suppStatusValue() {
        const chosen = sv('status');
        if (chosen === 'APPROVED' || chosen === 'DENIED') return chosen;
        return (sv('date_forwarded_op') || sv('date_approved_op')) ? 'AT_OP' : sv('date_forwarded_ndrrmc') ? 'AT_NDRRMC' : 'IN_PROCESS';
    }

    const suppTouched = () =>
        ['status', 'region', 'budget', 'date_received', 'date_forwarded_ndrrmc', 'date_forwarded_op', 'date_approved_op'].some(f => sv(f).trim() !== '');

    function buildSupplemental() {
        const num = f => { const n = parseInt(sv(f), 10); return isNaN(n) ? null : n; };
        return {
            status: suppStatusValue(),
            regional_office: sv('region') || null,
            budgetary_requirement: parseMoney(sv('budget')) || null,
            date_received: sv('date_received') || null,
            date_forwarded_ndrrmc: sv('date_forwarded_ndrrmc') || null,
            days_fmd: num('days_fmd'),
            date_forwarded_op: sv('date_forwarded_op') || null,
            days_ndrrmc: num('days_ndrrmc'),
            date_approved_op: sv('date_approved_op') || null,
            days_op: num('days_op'),
            days_total: num('days_total'),
        };
    }

    function populateSupplemental(s) {
        const statusSel = $('sar-supp-status');
        statusSel.value = (s.status === 'APPROVED' || s.status === 'DENIED') ? s.status : '';   // In Process / At NDRRMC / At OP are "Pending"
        // Denied, or Approved with no approval date, was set by hand — keep it when the dates change later
        statusSel.dataset.manual = (s.status === 'DENIED' || (s.status === 'APPROVED' && !s.date_approved_op)) ? '1' : '';

        const region = $('sar-supp-region');
        if (s.regional_office && ![...region.options].some(o => o.value === s.regional_office)) region.appendChild(opt(s.regional_office, s.regional_office));
        region.value = s.regional_office || '';

        $('sar-supp-budget').value = s.budgetary_requirement != null ? fmtMoney(s.budgetary_requirement) : '';
        ['date_received', 'date_forwarded_ndrrmc', 'date_forwarded_op', 'date_approved_op'].forEach(f => { $('sar-supp-' + f).value = s[f] ? String(s[f]).slice(0, 10) : ''; });
        ['days_fmd', 'days_ndrrmc', 'days_op', 'days_total'].forEach(f => { $('sar-supp-' + f).value = s[f] == null ? '' : s[f]; });
    }

    /* ── Supporting Documents ───────────────────────────────────────
       Files upload one at a time via POST /upload/file (the document name is taken from the file); the saved
       record is written FIRST and only then are the documents grouped (POST /upload/multi-upload → a new
       multiple_node_id, PATCHed onto the record) — doing it the other way round left orphaned file rows behind
       whenever the record itself was rejected. Editing a record only re-groups its documents if they changed. */
    const DZ_ICON = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M12 12v9m0-9l-3 3m3-3l3 3"/></svg>';

    function fmtSize(bytes) { return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.ceil(bytes / 1024) + ' KB'; }

    // `list` is the container to add the row to — the SARO's own list by default, or an NCA row's own
    function addDocRow(label, list) {
        const row = document.createElement('div');
        row.className = 'sar-doc-row';
        row.dataset.idx = ++S.docSeq;
        row.innerHTML = `
            <div>
                <label class="sar-doc-label">${esc(label || 'Supporting Document')}</label>
                <div class="sar-dz">
                    <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    ${DZ_ICON}
                    <p>Drag &amp; drop a file, or click to browse</p>
                    <small>Max 25MB · PDF, DOC, XLS, JPG, PNG</small>
                </div>
                <div class="sar-chip"><span class="sar-chip-name"></span><span class="sar-chip-size"></span><button type="button" class="sar-chip-x">&#10005; Remove</button></div>
                <input type="text" class="sar-doc-link" placeholder="e.g. https://drive.google.com/…" style="display:none;margin-top:8px;">
                <p class="sar-doc-note"></p>
                <input type="hidden" class="sar-doc-name" value="">
            </div>
            <button type="button" class="sar-doc-rm" title="Remove this row" aria-label="Remove this row">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>`;
        (list || $('sar-docs')).appendChild(row);
        return row;
    }

    // A document that is already attached (edit mode): there is no local File to re-select, so the row goes straight
    // to its chip with the stored name / link — which is exactly what getDocs() reads.
    function addExistingDocRow(name, link, viewUrl) {
        const row = addDocRow(name || 'Supporting Document');
        row.querySelector('.sar-doc-name').value = name || '';
        row.querySelector('.sar-doc-link').value = link || '';
        row.querySelector('.sar-dz').style.display = 'none';
        const nameEl = row.querySelector('.sar-chip-name');
        const url = viewUrl || (/^https?:\/\//i.test(link || '') ? link : '');
        if (url) {
            const a = document.createElement('a');
            a.className = 'sar-chip-name';
            a.href = url; a.target = '_blank'; a.rel = 'noopener';
            a.textContent = name || 'Supporting Document';
            nameEl.replaceWith(a);
        } else {
            nameEl.textContent = name || 'Supporting Document';
        }
        row.querySelector('.sar-chip-size').textContent = 'Previously uploaded';
        row.querySelector('.sar-chip').style.display = 'flex';
        return row;
    }

    function docNote(row, message, isError) {
        const n = row.querySelector('.sar-doc-note');
        n.textContent = message || '';
        n.className = 'sar-doc-note' + (isError ? ' error' : '');
        n.style.display = message ? 'block' : 'none';
    }

    async function processFile(row, file) {
        docNote(row, '', false);
        // the document name always comes from the file itself — set up front so it survives an oversize fallback
        row.querySelector('.sar-doc-name').value = file.name.replace(/\.[^./]+$/, '');
        if (row.closest('#sar-docs')) S.docsDirty = true;   // (an NCA's documents are new by definition — no "changed" flag)

        if (file.size > MAX_BYTES) {
            row.querySelector('.sar-dz').style.display = 'none';
            row.querySelector('.sar-chip').style.display = 'none';
            const link = row.querySelector('.sar-doc-link');
            link.style.display = 'block';
            link.focus();
            docNote(row, 'That file is ' + fmtSize(file.size) + ', which is over the 25MB limit. Please provide a link instead.', true);
            return;
        }

        const dz = row.querySelector('.sar-dz');
        dz.classList.add('uploading');
        docNote(row, 'Uploading…', false);
        const fd = new FormData();
        fd.append('file', file);
        try {
            const json = await api(U.upload, { method: 'POST', body: fd });
            row.querySelector('.sar-doc-link').value = json.link;
            row.querySelector('.sar-chip-name').textContent = file.name;
            row.querySelector('.sar-chip-size').textContent = fmtSize(file.size);
            dz.style.display = 'none';
            row.querySelector('.sar-chip').style.display = 'flex';
            docNote(row, '', false);
        } catch (e) {
            docNote(row, e.message || 'Upload failed. Please try again or use a link instead.', true);
        } finally {
            dz.classList.remove('uploading');
        }
    }

    function clearDocFile(row) {
        row.querySelector('.sar-doc-name').value = '';
        row.querySelector('.sar-doc-link').value = '';
        const file = row.querySelector('input[type="file"]');
        if (file) file.value = '';
        row.querySelector('.sar-chip').style.display = 'none';
        row.querySelector('.sar-dz').style.display = 'block';
        docNote(row, '', false);
        if (row.closest('#sar-docs')) S.docsDirty = true;
    }

    // the documents in one list (the SARO's own by default)
    function getDocs(list) {
        return [...(list || $('sar-docs')).querySelectorAll('.sar-doc-row')].map(row => ({
            document_name: row.querySelector('.sar-doc-name').value.trim(),
            link: row.querySelector('.sar-doc-link').value.trim(),
        })).filter(d => d.document_name || d.link);
    }

    // Wires one document list (drag & drop, remove, "Add Document"); the SARO's own list and every NCA row's list use it.
    function wireDocList(list, addBtn) {
        const own = list.id === 'sar-docs';   // only the SARO's own list feeds the "documents changed" flag
        list.addEventListener('change', e => {
            if (e.target.matches('input[type="file"]') && e.target.files && e.target.files[0]) processFile(e.target.closest('.sar-doc-row'), e.target.files[0]);
        });
        list.addEventListener('input', e => { if (own && e.target.matches('.sar-doc-link')) S.docsDirty = true; });
        list.addEventListener('dragover', e => { const dz = e.target.closest('.sar-dz'); if (dz) { e.preventDefault(); dz.classList.add('drag-over'); } });
        list.addEventListener('dragleave', e => { const dz = e.target.closest('.sar-dz'); if (dz) dz.classList.remove('drag-over'); });
        list.addEventListener('drop', e => {
            const dz = e.target.closest('.sar-dz');
            if (!dz) return;
            e.preventDefault();
            dz.classList.remove('drag-over');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) processFile(dz.closest('.sar-doc-row'), e.dataTransfer.files[0]);
        });
        list.addEventListener('click', e => {
            if (e.target.closest('.sar-chip-x')) clearDocFile(e.target.closest('.sar-doc-row'));
            else if (e.target.closest('.sar-doc-rm')) { e.target.closest('.sar-doc-row').remove(); if (own) S.docsDirty = true; }
        });
        addBtn.addEventListener('click', () => { addDocRow(undefined, list); if (own) S.docsDirty = true; });
    }

    const wireDocs = () => wireDocList($('sar-docs'), $('sar-doc-add'));

    // Called only AFTER the record is saved. Non-fatal: the record already exists, so a failure here is a warning.
    async function attachDocs(id) {
        if (!id || !S.docsDirty) return;
        const docs = getDocs();
        try {
            if (docs.length) {
                const up = await api(U.multi, { method: 'POST', body: JSON.stringify({ file_data: docs }) });
                if (up.multiple_node_id) await api(U.update(id), { method: 'PATCH', body: JSON.stringify({ multiple_node_id: up.multiple_node_id }) });
            } else if (S.editId) {
                await api(U.update(id), { method: 'PATCH', body: JSON.stringify({ multiple_node_id: null }) });   // every document was removed
            }
        } catch (e) {
            console.warn('[sar attachDocs]', e);
            toast('The record was saved, but its supporting documents could not be attached: ' + e.message, 'warning');
        }
    }

    /* ── Payload ────────────────────────────────────────────────── */
    // Every field is always sent: null clears an optional id / code on update and is simply left out on create;
    // '' clears a text field (the API stores it as NULL).
    function buildPayload() {
        const v = id => $(id).value.trim();
        const supp = isSupplemental();
        const intOrNull = id => { const n = parseInt(v(id), 10); return isNaN(n) ? null : n; };
        const payload = {
            tracking_type_id: intOrNull('sar-type'),
            nature_of_fund_id: intOrNull('sar-nature'),
            fiscal_year: intOrNull('sar-year'),
            date: v('sar-date'),                                            // typed in (YYYY-MM-DD) — nothing fills it in
            parent_id: parentHidden() ? null : intOrNull('sar-parent-id'),   // a SARO has no parent (null also clears one on update)
            description: supp ? null : v('sar-name'),                       // a Supplemental record has no description — its Purpose is the text
            amount: parseMoney(v('sar-amount')) || null,
            bank_account_no: v('sar-bank'),
        };
        // Purpose: the typed text for a Supplemental record, null (clears it) for any other. With SUPP_FIELDS off the field is
        // not on the form at all, so the key is left out and a record's stored purpose is left exactly as it is.
        if (SUPP_FIELDS) payload.purpose = supp ? v('sar-purpose') : null;
        // always sent: for GAAAO it is the generated reference as the user left it (edited or not); blank = the API generates it
        payload.saro_id = v('sar-ref');
        // Sub-Program: a SARO's pick (null clears it on update). Any other type has no such field on the form, so the key is left out
        // and whatever the record already has stays exactly as it is.
        if (isSaro()) payload.sub_program = subCombo.value() || null;
        if (supp && (suppTouched() || S.hadSupp)) payload.supplemental = buildSupplemental();
        return payload;
    }

    /* ── Open / populate / close ────────────────────────────────── */
    function setMode() {
        $('sar-title').textContent = S.editId ? 'Fund Tracking — Edit Entry' : 'Fund Tracking — New Entry';
        $('sar-save').textContent = S.editId ? 'Update Record' : 'Save Record';
    }

    function resetForm() {
        $('sar-form').reset();
        showErr('');
        parentCombo.reset();
        subCombo.reset();
        $('sar-docs').innerHTML = '';
        $('sar-form').querySelectorAll('.sar-invalid').forEach(el => el.classList.remove('sar-invalid'));
        delete $('sar-supp-status').dataset.manual;
        delete $('sar-ref').dataset.mode;
        delete $('sar-ref').dataset.auto;
        S.docsDirty = false;
        S.hadSupp = false;
        S.trustPromise = null;
        S.parentMode = 'search';
        S.parentKind = '';
        S.typeLocked = false;
        S.saroBal = { id: null, ref: '', amount: 0, released: 0 };   // read again for this open: its NCAs may have changed since
        S.saroBalSeq++;
        $('sar-saro-bal').style.display = 'none';
    }

    // select `value`, adding an option for it first when the list doesn't have it (a year / type retired since the record was saved)
    function selectValue(id, value, text) {
        const el = $(id);
        if (value === null || value === undefined || value === '') { el.value = ''; return; }
        if (![...el.options].some(o => String(o.value) === String(value))) el.appendChild(opt(value, text ?? String(value)));
        el.value = String(value);
    }

    function populate(r) {
        const type = r.tracking_type;
        selectValue('sar-type', r.tracking_type_id, type ? type.fund_tracking_code : null);
        syncParentMode(true);

        const par = (r.parent || [])[0];
        if (r.parent_id && par && !parentHidden()) {
            parentCombo.set(par.id, (par.saro_id || '#' + par.id) + ' (' + String(par.tracking_type?.fund_tracking_code || 'N/A').toUpperCase() + ')');
            if (S.parentMode === 'trust') {
                loadTrustFunds().then(list => {
                    const hit = list.find(t => Number(t.id) === Number(r.parent_id));
                    if (hit && Number($('sar-parent-id').value) === Number(r.parent_id)) $('sar-parent').value = trustLabel(hit);
                }).catch(() => {});
            }
        }

        $('sar-nature').value = r.nature_of_fund_id != null ? String(r.nature_of_fund_id) : '';
        $('sar-year').value = r.fiscal_year != null ? String(r.fiscal_year) : '';
        $('sar-date').value = r.date ? String(r.date).slice(0, 10) : '';
        syncNature();
        syncReference(true);

        // the saved sub-program code, shown as "code — name" when it is on the list (a code that is not — retired since — shows as it is)
        if (r.sub_program && isSaro()) {
            const sp = S.subs.find(s => s.sub_program_code === r.sub_program);
            subCombo.set(r.sub_program, sp ? subLabel(sp) : r.sub_program);
        }

        $('sar-ref').value = r.saro_id || '';
        $('sar-name').value = r.description || '';
        $('sar-purpose').value = r.purpose || '';
        $('sar-amount').value = r.amount != null ? fmtMoney(r.amount) : '';
        $('sar-bank').value = r.bank_account_no || '';
        syncSaroBalance();   // an NCA being edited: its SARO's amount, with this NCA's amount deducted

        S.hadSupp = !!r.supplemental;
        if (isSupplemental() && r.supplemental) populateSupplemental(r.supplemental);

        const docs = (r.file_attachments || []).filter(a => !a.deleted_at && a.file_meta && !a.file_meta.deleted_at);
        if (docs.length) docs.forEach(a => addExistingDocRow(a.file_meta.document_name, a.file_meta.link, a.file_meta.view_url));
        else addDocRow();
    }

    // opts.nature — a new entry starts with this nature of fund (its name, e.g. 'SUPPLEMENTAL') already chosen; ignored when editing.
    // opts.type — a new entry starts with this tracking type (its code, e.g. 'NCA') instead of the first allowed one; ignored when editing.
    // opts.parent — a new NCA starts with this record already picked as its SARO ({ id, saro_id, type }): the "+" on a SARO's
    //               NCA Received cell in the registry. Only for an NCA (it has a parent field); ignored when editing.
    async function open(id, opts = {}) {
        if (S.opening) return;
        S.opening = true;
        window.PagePreloader?.show();
        try {
            resetForm();
            S.editId = id ? Number(id) : null;
            setMode();
            $('sar-overlay').classList.add('open');
            $('sar-body').scrollTop = 0;

            await ensureLookups();

            if (S.editId) {
                try {
                    populate((await api(U.read(S.editId))).data);
                } catch (e) {
                    toast('Could not load record #' + S.editId + ': ' + e.message, 'error');
                    close();
                    return;
                }
            } else {
                // a new entry starts as the first allowed type (the Fiscal Year and the Date are left for the user to type)
                if (S.types[0]) $('sar-type').value = String(S.types[0].id);
                if (opts && opts.type) {                                  // …or this one, when the caller says so (the SARO / NCA tab)
                    const want = S.types.find(t => t.code === String(opts.type).trim().toUpperCase());
                    if (want) $('sar-type').value = String(want.id);
                    // opened with a type — a SARO from the "+" in the SARO ID header, an NCA from the "+" beside a SARO's NCA Received amount:
                    // nothing is left to choose, so "What are you adding?" stays out of the way. (Edit keeps it.)
                    S.typeLocked = !!want;
                }
                if (opts && opts.nature) {
                    const want = String(opts.nature).trim().toUpperCase();
                    const hit = [...$('sar-nature').options].find(o => String(o.dataset.name || '').trim().toUpperCase() === want);
                    if (hit) $('sar-nature').value = hit.value;
                }
                syncParentMode(true);
                // an NCA opened from a SARO's "+": that SARO is already picked (same label the search list gives it); the balance
                // box below then reads it
                if (opts && opts.parent && opts.parent.id && isNca() && !parentHidden()) {
                    parentCombo.set(opts.parent.id, (opts.parent.saro_id || '#' + opts.parent.id)
                        + ' (' + String(opts.parent.type || 'SARO').toUpperCase() + ')');
                }
                syncNature();
                syncReference(false);
                syncSaroBalance();
                addDocRow();
            }
            syncTypeChooser();   // the buttons show the type the form opened with (set above, or by populate())
            $('sar-typefirst').style.display = typeChooserOn() && !S.typeLocked ? '' : 'none';   // shown or hidden for THIS open
        } finally {
            S.opening = false;
            window.PagePreloader?.hide();
        }
    }

    function close() {
        $('sar-overlay').classList.remove('open');
        parentCombo.hide();
        subCombo.hide();
    }

    /* ── Save ───────────────────────────────────────────────────── */
    async function save(ev) {
        ev.preventDefault();
        if (S.saving) return;
        showErr('');

        const form = $('sar-form');
        form.querySelectorAll('.sar-invalid').forEach(el => el.classList.remove('sar-invalid'));
        const missing = [];
        const need = (id, label, bad) => { if (bad) { $(id).classList.add('sar-invalid'); missing.push(label); } };
        need(typeChooserOn() ? 'sar-seg' : 'sar-type', typeChooserOn() ? 'What you are adding (SARO or NCA)' : 'Tracking Type', !$('sar-type').value);
        // an NCA must name the SARO it was released under, and release something from it (an amount above zero)
        need('sar-parent', 'SARO', ncaUnderSaro() && !$('sar-parent-id').value);
        // Fiscal Year is typed: a 4-digit year in the range the API accepts. Date is picked: it must be filled in.
        const yr = Number($('sar-year').value);
        need('sar-year', 'Fiscal Year (a 4-digit year)', !/^\d{4}$/.test($('sar-year').value) || yr < 1900 || yr > 2200);
        need('sar-date', 'Date', !$('sar-date').value);
        need('sar-amount', 'Amount', isNaN(parseMoney($('sar-amount').value)) || (ncaUnderSaro() && !(parseMoney($('sar-amount').value) > 0)));
        if (!isGaaao()) need('sar-ref', idName(), $('sar-ref').value.trim() === '');
        if (missing.length) return showErr('Please fill in the required fields: ' + missing.join(', ') + '.');

        // an NCA cannot release more than its SARO has left — the SARO would go negative (the API refuses it too)
        if (ncaUnderSaro()) {
            const left = saroLeft(), typed = parseMoney($('sar-amount').value);
            if (left !== null && Math.round((typed - left) * 100) > 0) {
                $('sar-amount').classList.add('sar-invalid');
                return showErr(`This NCA (${peso(typed)}) is more than SARO ${S.saroBal.ref} has left (${peso(Math.max(left, 0))}). Lower the amount to ${peso(Math.max(left, 0))} or less.`);
            }
        }

        const btn = $('sar-save');
        const label = btn.textContent;
        S.saving = true;
        btn.disabled = true;
        btn.textContent = S.editId ? 'Updating…' : 'Saving…';
        window.PagePreloader?.show();
        try {
            const payload = buildPayload();
            const wasEdit = !!S.editId;
            const json = wasEdit
                ? await api(U.update(S.editId), { method: 'PATCH', body: JSON.stringify(payload) })
                : await api(U.add, { method: 'POST', body: JSON.stringify(payload) });
            const id = wasEdit ? S.editId : json.id;

            await attachDocs(id);

            close();
            toast(wasEdit ? 'Fund tracking record updated successfully.' : 'Fund tracking record saved successfully.', 'success');
            if (typeof window.sr_reload === 'function') window.sr_reload();
        } catch (e) {
            // A create that half-failed (the record is in, its supplemental details are not) reports the new id: carry on
            // in edit mode for it, so saving again updates that record instead of creating a second one.
            if (!S.editId && e.data && e.data.id) {
                S.editId = Number(e.data.id);
                setMode();
                if (typeof window.sr_reload === 'function') window.sr_reload();
                showErr(e.message + ' — the record itself is saved; fix the problem (or clear the Supplemental Details) and press Update Record.');
            } else {
                showErr(e.message);
            }
        } finally {
            S.saving = false;
            btn.disabled = false;
            if (btn.textContent.endsWith('…')) btn.textContent = S.editId ? 'Update Record' : label;
            window.PagePreloader?.hide();
        }
    }

    /* ── Wire-up (the markup exists once, here; every open starts it over via resetForm) ── */
    wireMoney();
    wireDocs();
    $('sar-form').addEventListener('submit', save);
    $('sar-form').addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') e.preventDefault(); });
    $('sar-close').addEventListener('click', close);
    $('sar-cancel').addEventListener('click', close);

    // Tracking type: "OR No." label, the Trust Fund pick-list for an OR's parent, no parent field for a SARO / a "SARO" field for an
    // NCA (on a "What are you adding?" host), and the buttons of "What are you adding?"
    // (an NCA has no Nature of Fund, so choosing it hides that field — and a GAAAO reference generated for the SARO goes with it)
    $('sar-type').addEventListener('change', () => { const cleared = syncNature(); syncReference(!cleared); syncParentMode(false); syncSaroBalance(); syncTypeChooser(); });
    // the SARO's balance follows the amount being typed
    $('sar-amount').addEventListener('input', () => { if (ncaUnderSaro()) syncSaroBalance(); });
    $('sar-seg').addEventListener('click', e => {
        const b = e.target.closest('.sar-seg-btn');
        if (!b || $('sar-type').value === b.dataset.id) return;
        $('sar-type').value = b.dataset.id;
        $('sar-type').dispatchEvent(new Event('change', { bubbles: true }));
    });
    // Nature of fund: GAAAO's generated reference, and the Supplemental-only fields
    $('sar-nature').addEventListener('change', () => { syncNature(); syncReference(false); });
    // a new Fiscal Year updates the generated GAAAO reference — unless the user has edited it
    $('sar-year').addEventListener('input', () => {
        const y = $('sar-year');
        y.value = y.value.replace(/\D/g, '').slice(0, 4);   // digits only, at most 4
        if (isGaaao()) refreshGenerated(false);
    });
    // Generate: fill the GAAAO reference in again, replacing whatever is typed
    $('sar-ref-gen').addEventListener('click', () => {
        if (!referencePreview()) { toast('Pick the Fiscal Year first — the reference is generated from it.', 'warning'); return; }
        refreshGenerated(true);
        $('sar-ref').focus();
    });

    // the day counts only ever come from the dates; 'change' fires on user edits only, so loading a saved record never
    // overwrites its stored counts or status
    $('sar-supp-status').addEventListener('change', function () { this.dataset.manual = this.value === '' ? '' : '1'; });
    ['date_received', 'date_forwarded_ndrrmc', 'date_forwarded_op', 'date_approved_op'].forEach(f => {
        $('sar-supp-' + f).addEventListener('change', () => { suppRecalc(); suppSyncStatus(); });
    });

    window.sarModal = { open, close };
}());
</script>
