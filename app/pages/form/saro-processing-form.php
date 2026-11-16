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
 *   GET  /fund-tracking-type/all  /nature-of-fund/all  /fiscal_year/all
 *   GET  /responsibility-center/all?office_type=Regional   (Supplemental Details → Region)
 *   POST /upload/file (multipart)          POST /upload/multi-upload       (Supporting Documents)
 *
 * What changed from the FMD version (the table is fims.fund_tracking now):
 *   Program (program_id)        → not on this form: a record has no sub-program (nor an unreleased amount) any more — both columns
 *                                 were dropped from fims.fund_tracking, and the form neither shows nor sends them
 *   Date                        → not on this form: a record's date is the day it is created, stamped by the API
 *   Fiscal Year (period id)     → fiscal_year, a plain year picked from fims.fiscal_year
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

            <!-- ── Tracking Classification ──────────────────────────── -->
            <div class="sar-sec">
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
                        <input type="text" id="sar-parent" placeholder="Search reference number…">
                        <input type="hidden" id="sar-parent-id" value="">
                        <span class="sar-hint" id="sar-parent-hint"></span>
                    </div>
                </div>
                <div class="nha-fg-row">
                    <div class="nha-fg" id="sar-nature-group" style="margin-bottom:0;">
                        <label for="sar-nature">Nature of Fund</label>
                        <select id="sar-nature"><option value="">Loading…</option></select>
                        <span class="sar-hint">Insertions, Equity, GAAAO, Unprogrammed or SUPPLEMENTAL</span>
                    </div>
                    <div></div>
                </div>
            </div>

            <!-- ── Fiscal Year ───────────────────────────────────────── -->
            <div class="sar-sec">
                <h4 class="sar-sec-hd">Fiscal Year</h4>
                <div class="nha-fg-row">
                    <div class="nha-fg" style="margin-bottom:0;">
                        <label for="sar-year">Fiscal Year <span class="nha-req">*</span></label>
                        <select id="sar-year"><option value="">Loading…</option></select>
                    </div>
                    <div></div>
                </div>
            </div>

            <!-- ── Fund Information ──────────────────────────────────── -->
            <div class="sar-sec">
                <h4 class="sar-sec-hd">Fund Information</h4>
                <div class="nha-fg-row">
                    <div class="nha-fg" id="sar-ref-group">
                        <label for="sar-ref" id="sar-ref-label">Reference Number <span class="nha-req">*</span></label>
                        <input type="text" id="sar-ref" maxlength="225" placeholder="Enter reference number">
                        <span class="sar-hint" id="sar-ref-hint"></span>
                    </div>
                    <div class="nha-fg" id="sar-name-group">
                        <label for="sar-name">Name</label>
                        <input type="text" id="sar-name" maxlength="225" placeholder="e.g. Name of this fund record">
                    </div>
                </div>

                <!-- Purpose is for SUPPLEMENTAL only (sar_syncNature); it stays in the DOM so an existing record's
                     purpose still round-trips when the nature of fund is something else. -->
                <div class="nha-fg" id="sar-purpose-group" style="display:none;">
                    <label for="sar-purpose">Purpose</label>
                    <textarea id="sar-purpose" rows="2" placeholder="e.g. Typhoon Response Fund"></textarea>
                </div>

                <div class="nha-fg-row">
                    <div class="nha-fg" style="margin-bottom:0;">
                        <label for="sar-amount">Amount <span class="nha-req">*</span></label>
                        <div class="sar-money"><span>&#8369;</span><input type="text" inputmode="decimal" id="sar-amount" data-money placeholder="0.00"></div>
                        <span class="sar-hint">Total allotment amount</span>
                    </div>
                    <div class="nha-fg" style="margin-bottom:0;">
                        <label for="sar-bank">Bank Account No.</label>
                        <input type="text" id="sar-bank" maxlength="225" placeholder="e.g. 0702-1068-34">
                        <span class="sar-hint">Optional</span>
                    </div>
                </div>
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
                        <li>Tracking Type, Fiscal Year and Amount are required; Nature of Fund is optional. The Reference Number is generated automatically only when the Nature of Fund is GAAAO — otherwise enter it yourself</li>
                        <li>An NCA is its own entry: choose NCA above, then pick the SARO it was released under. A SARO has no parent</li>
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
        years:   API + '/fiscal_year/all',
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
        parentMode: 'search',  // 'search' (any record, by reference) | 'trust' (OR → pick a Trust Fund)
        parentKind: '',        // what the parent field is for: 'none' (hidden — a SARO) | 'saro' (an NCA's SARO) | 'search' | 'trust'
        trustPromise: null,    // the Trust Fund list, read once per open
        docsDirty: false,      // supporting documents were added / removed / replaced since the form opened
        docSeq: 0,
        hadSupp: false,        // the record being edited already has supplemental details
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
        years: {
            label: 'fiscal years', url: U.years,
            apply(rows) {
                const ys = rows.map(r => Number(r.fiscal_yr)).filter(Boolean).sort((a, b) => a - b);
                fill('sar-year', 'Select fiscal year', ys.map(y => opt(y, String(y), { year: y })));
            },
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

    // a Trust Fund as the OR's parent shows by its agency and reference number
    const trustLabel = r => {
        const ref = r.reference_number || '';
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
        match: r => [r.reference_number, r.agency && r.agency.name].join(' '),
        key: r => r.id,
        label: r => (S.parentMode === 'trust' ? trustLabel(r) : (r.reference_number || '#' + r.id) + ' (' + typeCodeOf(r) + ')'),
        render: (r, q) => {
            if (S.parentMode === 'trust') {
                return `<span class="nha-ss-opt-label" style="white-space:normal;"><strong>${r.agency && r.agency.name ? esc(r.agency.name) : '<em>No agency</em>'}</strong><br><span class="sar-opt-sub">${r.reference_number ? hl(r.reference_number, q) : '—'}</span></span>`;
            }
            return `<span class="nha-ss-opt-code">${hl(r.reference_number || '#' + r.id, q)}</span><span class="nha-ss-opt-label">${esc(typeCodeOf(r))}${r.name ? ' · ' + esc(r.name) : ''}</span>`;
        },
        empty: () => (S.parentMode === 'trust' ? 'No trust funds found' : ncaUnderSaro() ? 'Type to search SAROs by reference number' : 'Type to search reference numbers'),
        noMatch: 'No matching fund tracking records',
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
    // On a host that asks "What are you adding?": a SARO has no parent at all (the field is not on the form), and an NCA's one
    // field is the SARO it was released under ("SARO", offering SAROs only). Any other host keeps the plain "Parent Fund" field.
    const parentHidden = () => typeChooserOn() && isSaro();
    const ncaUnderSaro = () => typeChooserOn() && isNca();

    // "What are you adding?" — one button per allowed tracking type, standing in for the Tracking Type dropdown (which stays, hidden,
    // as the one place the choice is kept: the rest of the form reads it, and a button just sets it and fires its change event).
    // Only when the host asks for it (TYPE_FIRST) and there is something to choose between.
    const typeChooserOn = () => TYPE_FIRST && S.types.length > 1;
    function renderTypeChooser() {
        const on = typeChooserOn();
        $('sar-typefirst').style.display = on ? '' : 'none';
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

    // Supplemental Funds show Purpose and Supplemental Details instead of Name
    function syncNature() {
        const supp = isSupplemental();
        $('sar-name-group').style.display = supp ? 'none' : '';
        $('sar-purpose-group').style.display = supp ? '' : 'none';
        $('sar-supp-section').style.display = supp ? '' : 'none';
    }

    // Reference Number preview — "<NATURE OF FUND>-<FISCAL YEAR>", e.g. GAAAO-2026. Display only: the API generates
    // the real value on save (FundTrackingServices::generateReferenceNumber), so it is never sent.
    function referencePreview() {
        const name = ((selOpt('sar-nature') || {}).dataset?.name || '').toUpperCase().replace(/[^A-Z0-9]+/g, '-').replace(/^-+|-+$/g, '');
        const year = $('sar-year').value;
        return name && year ? name + '-' + year : '';
    }

    // Only GAAAO records get an auto-generated reference; every other nature of fund types it in.
    // keepValue: true when populating an existing record (don't wipe the value it already has).
    function syncReference(keepValue) {
        const input = $('sar-ref'), label = $('sar-ref-label'), hint = $('sar-ref-hint');
        const auto = isGaaao();
        const mode = auto ? 'auto' : 'manual';
        const changed = input.dataset.mode !== mode;
        input.dataset.mode = mode;
        if (auto) {
            input.readOnly = input.disabled = true;
            input.placeholder = 'Auto-generated (e.g. GAAAO-2026)';
            label.innerHTML = 'Reference Number';
            hint.textContent = 'Generated from the Nature of Fund and Fiscal Year';
            if (!keepValue) input.value = referencePreview();
        } else {
            const or = isOr();
            input.readOnly = input.disabled = false;
            input.placeholder = or ? 'Enter OR number' : 'Enter reference number';
            label.innerHTML = (or ? 'OR No.' : 'Reference Number') + ' <span class="nha-req">*</span>';
            hint.textContent = or ? 'Enter the actual OR number' : '';
            if (changed && !keepValue) input.value = '';
        }
    }

    // An OR is linked to a Trust Fund (a pick-list); anything else searches any record by reference number.
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
        $('sar-parent-label').textContent = nca ? 'SARO' : 'Parent Fund';
        $('sar-parent').placeholder = mode === 'trust' ? 'Select a trust fund or search…' : nca ? 'Search the SARO\'s reference number…' : 'Search reference number…';
        $('sar-parent-hint').textContent = mode === 'trust'
            ? 'Select the Trust Fund this OR is linked to'
            : nca ? 'The SARO this NCA was released under (optional) — search by reference number'
                  : 'Link this record to its parent fund (optional) — search by reference number';
        if (mode === 'trust') loadTrustFunds().catch(() => {});
        // a parent picked for another kind of field isn't valid here (a SARO has none; an NCA's must be a SARO); a hidden field never keeps one
        if ((changed && !keepSelection) || hidden) parentCombo.reset();
    }

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
            parent_id: parentHidden() ? null : intOrNull('sar-parent-id'),   // a SARO has no parent (null also clears one on update)
            name: supp ? null : v('sar-name'),                       // a Supplemental record has no name — its Purpose is the text
            amount: parseMoney(v('sar-amount')) || null,
            bank_account_no: v('sar-bank'),
        };
        // Purpose: the typed text for a Supplemental record, null (clears it) for any other. With SUPP_FIELDS off the field is
        // not on the form at all, so the key is left out and a record's stored purpose is left exactly as it is.
        if (SUPP_FIELDS) payload.purpose = supp ? v('sar-purpose') : null;
        if (!isGaaao()) payload.reference_number = v('sar-ref');   // for GAAAO the API generates it
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
        $('sar-docs').innerHTML = '';
        $('sar-form').querySelectorAll('.sar-invalid').forEach(el => el.classList.remove('sar-invalid'));
        delete $('sar-supp-status').dataset.manual;
        delete $('sar-ref').dataset.mode;
        S.docsDirty = false;
        S.hadSupp = false;
        S.trustPromise = null;
        S.parentMode = 'search';
        S.parentKind = '';
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
            parentCombo.set(par.id, (par.reference_number || '#' + par.id) + ' (' + String(par.tracking_type?.fund_tracking_code || 'N/A').toUpperCase() + ')');
            if (S.parentMode === 'trust') {
                loadTrustFunds().then(list => {
                    const hit = list.find(t => Number(t.id) === Number(r.parent_id));
                    if (hit && Number($('sar-parent-id').value) === Number(r.parent_id)) $('sar-parent').value = trustLabel(hit);
                }).catch(() => {});
            }
        }

        $('sar-nature').value = r.nature_of_fund_id != null ? String(r.nature_of_fund_id) : '';
        selectValue('sar-year', r.fiscal_year, r.fiscal_year != null ? String(r.fiscal_year) : null);
        syncNature();
        syncReference(true);

        $('sar-ref').value = r.reference_number || '';
        $('sar-name').value = r.name || '';
        $('sar-purpose').value = r.purpose || '';
        $('sar-amount').value = r.amount != null ? fmtMoney(r.amount) : '';
        $('sar-bank').value = r.bank_account_no || '';

        S.hadSupp = !!r.supplemental;
        if (isSupplemental() && r.supplemental) populateSupplemental(r.supplemental);

        const docs = (r.file_attachments || []).filter(a => !a.deleted_at && a.file_meta && !a.file_meta.deleted_at);
        if (docs.length) docs.forEach(a => addExistingDocRow(a.file_meta.document_name, a.file_meta.link, a.file_meta.view_url));
        else addDocRow();
    }

    // opts.nature — a new entry starts with this nature of fund (its name, e.g. 'SUPPLEMENTAL') already chosen; ignored when editing.
    // opts.type — a new entry starts with this tracking type (its code, e.g. 'NCA') instead of the first allowed one; ignored when editing.
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
                // a new entry starts as the first allowed type, in the current fiscal year if it is on the list
                if (S.types[0]) $('sar-type').value = String(S.types[0].id);
                if (opts && opts.type) {                                  // …or this one, when the caller says so (the SARO / NCA tab)
                    const want = S.types.find(t => t.code === String(opts.type).trim().toUpperCase());
                    if (want) $('sar-type').value = String(want.id);
                }
                const thisYear = String(new Date().getFullYear());
                if ([...$('sar-year').options].some(o => o.value === thisYear)) $('sar-year').value = thisYear;
                if (opts && opts.nature) {
                    const want = String(opts.nature).trim().toUpperCase();
                    const hit = [...$('sar-nature').options].find(o => String(o.dataset.name || '').trim().toUpperCase() === want);
                    if (hit) $('sar-nature').value = hit.value;
                }
                syncParentMode(true);
                syncNature();
                syncReference(false);
                addDocRow();
            }
            syncTypeChooser();   // the buttons show the type the form opened with (set above, or by populate())
        } finally {
            S.opening = false;
            window.PagePreloader?.hide();
        }
    }

    function close() {
        $('sar-overlay').classList.remove('open');
        parentCombo.hide();
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
        need('sar-year', 'Fiscal Year', !$('sar-year').value);
        need('sar-amount', 'Amount', isNaN(parseMoney($('sar-amount').value)));
        if (!isGaaao()) need('sar-ref', isOr() ? 'OR No.' : 'Reference Number', $('sar-ref').value.trim() === '');
        if (missing.length) return showErr('Please fill in the required fields: ' + missing.join(', ') + '.');

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
    $('sar-type').addEventListener('change', () => { syncReference(true); syncParentMode(false); syncTypeChooser(); });
    $('sar-seg').addEventListener('click', e => {
        const b = e.target.closest('.sar-seg-btn');
        if (!b || $('sar-type').value === b.dataset.id) return;
        $('sar-type').value = b.dataset.id;
        $('sar-type').dispatchEvent(new Event('change', { bubbles: true }));
    });
    // Nature of fund: GAAAO's generated reference, and the Supplemental-only fields
    $('sar-nature').addEventListener('change', () => { syncNature(); syncReference(false); });
    $('sar-year').addEventListener('change', () => { if (isGaaao()) $('sar-ref').value = referencePreview(); });

    // the day counts only ever come from the dates; 'change' fires on user edits only, so loading a saved record never
    // overwrites its stored counts or status
    $('sar-supp-status').addEventListener('change', function () { this.dataset.manual = this.value === '' ? '' : '1'; });
    ['date_received', 'date_forwarded_ndrrmc', 'date_forwarded_op', 'date_approved_op'].forEach(f => {
        $('sar-supp-' + f).addEventListener('change', () => { suppRecalc(); suppSyncStatus(); });
    });

    window.sarModal = { open, close };
}());
</script>
