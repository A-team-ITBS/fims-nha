<?php
/**
 * CAF (Certificate as to Availability of Funds) form — a modal, opened from the RFA tab of the Memo Request list
 * (memo-req-page.php) by the "Create CAF" action of a row: window.cafModal.open(allocationId). Only an FMD user may create a CAF,
 * only for an RFA whose status is Approved, and only while that RFA has no CAF yet — a superadmin is exempt from all three
 * (applyRfa() says so on open; the API, CafService::create(), is what enforces it). After a save the page's row loses its
 * Create CAF action (mrq_onCafCreated()).
 *
 * The same modal as the memo request form (form/budget-allocation-form.php in modal mode): the same shell (.nha-overlay /
 * .nha-modal-xl / .nha-modal-hdr / .nha-modal-body), the same number card, form sections, fields, tinted amount-line cards
 * and Cancel / Save footer — cut down to what a CAF needs, and RFA only:
 *   RFA            a DISABLED select holding just the RFA of the row that opened the modal, already SELECTED: the user finds
 *                  the RFA in the list and clicks Create CAF on it, so it is shown here, never picked. Only that one RFA is
 *                  read (GET /budget_allocation/read/{id}) — there is no list of the other RFAs.
 *   Fiscal Year    read-only, the RFA's (sent as fiscal_year).
 *   CAF Number     the RFA's own number (its allocation_number): a CAF carries the number of the RFA it certifies, so it is
 *                  filled in from the selected RFA and read-only (caf_number). An RFA only gets its number on its final
 *                  approval, so before that it is blank and the CAF is saved without one.
 *   Document Type  CO / COE, the RFA's own (document_type) — disabled, like the RFA field.
 *   RFA Details    read-only: the RFA's reference, status, office, Program / Sub-Program / Project, vendor and purpose.
 *   CAF Lines      the RFA's budget allocation lines (its line_transaction, read from GET /budget_allocation/read/{id}), each
 *                  with a "CAF Amount" that is the line's requested amount — disabled, nothing is typed in this form. A line
 *                  with no usable requested amount counts as 0.00, so a locked blank can never stop the save. Saved as the
 *                  CAF's line_transaction: every field of the RFA line, plus caf_amount.
 *
 * Saved with POST /caf/add (app/routes/caf.php -> CafService -> fims.caf). The creator (user_id) is filled in by the API from
 * the verified login, never sent from here.
 *
 * LOAD ORDER: memo-req-page.php includes this file BEFORE its own <script>, which is what declares MRQ_BASE, MRQ_API and the
 * mrq_* helpers (mrq_request, mrq_esc, mrq_statusBadge, mrq_refOf, mrq_toast). So nothing of the page may be touched while this
 * script first runs — they are only used inside functions, which run later (on a click). Reading one at load time throws and
 * window.cafModal never exists.
 *
 * Its CSS is scoped under #caf-overlay (the shared stylesheet's .caf-* classes belong to the old FMD CAF form) and repeats the
 * overlay / modal essentials, like the memo form does, so a missing or stale shared stylesheet cannot break it.
 */
?>

<style>
/* ── Modal shell (the memo form's safety-net rules, scoped to this modal) ─────────────────────────── */
#caf-overlay { display:none;position:fixed;inset:0;z-index:9070;box-sizing:border-box;padding:16px;align-items:center;justify-content:center;
    background:rgba(15,32,69,.55); }
#caf-overlay.open { display:flex; }
#caf-overlay .nha-modal { display:flex;flex-direction:column;width:100%;max-width:1100px;max-height:90vh;overflow:hidden;
    background:var(--afims-surface, #FFFFFF);border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.22); }
#caf-overlay .nha-modal-hdr { display:flex;flex-shrink:0;align-items:center;justify-content:space-between;padding:16px 20px;
    background:var(--afims-heading-fill, #000E3E); }
#caf-overlay .nha-modal-title { margin:0;font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF); }
#caf-overlay .nha-modal-close { width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:18px;line-height:1;
    color:var(--afims-pure-white, #FFFFFF);cursor:pointer;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);border-radius:6px; }
#caf-overlay .nha-modal-close:disabled { opacity:.5;cursor:not-allowed; }
#caf-overlay .nha-modal-body { flex:1;padding:18px 22px;overflow-y:auto; }

/* ── Form base layer (the memo form's .cba-form-container rules, scoped to this modal) ─────────────── */
#caf-overlay .caf-container { font-family:'IBM Plex Sans', sans-serif;font-size:14px;color:var(--afims-text, #1A1B21); }
#caf-overlay .caf-container *, #caf-overlay .caf-container *::before, #caf-overlay .caf-container *::after { box-sizing:border-box; }

/* number card */
#caf-overlay .form-header { margin-bottom:18px; }
#caf-overlay .caf-numcard { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;padding:16px 22px;
    background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-left:4px solid var(--afims-primary, #0035A9);
    border-radius:12px;box-shadow:0 1px 2px rgba(15,32,69,.05); }
#caf-overlay .caf-numcard-label { margin:0 0 4px;font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;
    color:var(--afims-text-muted, #62657E); }
#caf-overlay .caf-numcard-value { margin:0;font-family:'IBM Plex Mono', monospace;font-size:22px;font-weight:600;letter-spacing:-.01em;
    color:var(--afims-heading, #000E3E); }
#caf-overlay .caf-numcard-date { margin:0;font-size:14px;font-weight:600;color:var(--afims-heading, #000E3E); }

/* section cards */
#caf-overlay .form-section { margin-bottom:18px;padding:22px 24px 24px;background:var(--afims-surface, #FFFFFF);
    border:1px solid var(--afims-border, #E4ECF2);border-radius:12px;box-shadow:0 1px 2px rgba(15,32,69,.05); }
#caf-overlay .section-header { display:flex;align-items:center;gap:10px;margin:0 0 18px;padding-bottom:12px;
    border-bottom:1px solid var(--afims-border, #E4ECF2);font-family:inherit;font-size:15px;font-weight:700;line-height:1.3;
    color:var(--afims-heading, #000E3E); }
#caf-overlay .section-header::before { content:'';flex-shrink:0;width:4px;height:16px;border-radius:2px;background:var(--afims-primary, #0035A9); }

/* grid */
#caf-overlay .form-row { display:grid;grid-template-columns:repeat(2, minmax(0, 1fr));gap:18px 20px;align-items:start;margin-bottom:18px; }
#caf-overlay .form-row.full-width { grid-template-columns:minmax(0, 1fr); }
#caf-overlay .form-row.cols-3 { grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); }
/* CAF line: Type of Fund | Account | RFA Requested | CAF Amount */
#caf-overlay .form-row.cols-caf { grid-template-columns:1fr 2fr 1fr 1fr;margin-bottom:0; }
#caf-overlay .form-section > .form-row:last-child { margin-bottom:0; }
#caf-overlay .form-group { display:flex;flex-direction:column;gap:6px;min-width:0;position:relative; }

/* labels, hints, errors — an ID selector outranks custom.css's `body.afims-ui .content-wrapper .form-label` */
#caf-overlay .form-label { display:block;margin:0;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;
    color:var(--afims-text, #1A1B21); }
#caf-overlay .required { margin-left:2px;color:var(--afims-danger, #DC3545); }
#caf-overlay .input-hint { margin:0;font-size:11.5px;line-height:1.5;color:var(--afims-text-muted, #62657E); }
#caf-overlay .error-box { display:none;margin:0 0 14px;padding:8px 12px;font-size:12px;line-height:1.5;color:var(--afims-danger, #DC3545);
    background:var(--afims-danger-soft, #FAE1E3);border:1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 25%, transparent);
    border-radius:8px; }
#caf-overlay .error-box.visible { display:block; }

/* fields */
#caf-overlay .form-input, #caf-overlay .form-select, #caf-overlay .form-textarea { display:block;width:100%;min-height:40px;padding:9px 12px;
    font-family:'IBM Plex Sans', sans-serif;font-weight:400;line-height:1.4;color:var(--afims-text, #1A1B21);
    background-color:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;outline:none;
    box-shadow:none;transition:border-color .15s, box-shadow .15s, background-color .15s; }
#caf-overlay .form-select { appearance:none;-webkit-appearance:none;padding-right:34px;cursor:pointer;
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%237a7d92' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;background-size:14px; }
html.afims-dark[data-bs-theme="dark"] #caf-overlay .form-select {
    background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23b5b8cc' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat:no-repeat;background-position:right 12px center;background-size:14px; }
#caf-overlay .form-textarea { min-height:84px;line-height:1.55;resize:none; }
#caf-overlay .form-input::placeholder, #caf-overlay .form-textarea::placeholder { color:var(--afims-text-disabled, #A9AAB8); }
#caf-overlay .form-input:hover:not(:disabled):not(:focus):not([readonly]), #caf-overlay .form-select:hover:not(:disabled):not(:focus) {
    border-color:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, var(--afims-border, #E4ECF2)); }
#caf-overlay .form-input:focus, #caf-overlay .form-select:focus, #caf-overlay .form-textarea:focus {
    border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px color-mix(in srgb, var(--afims-heading-fill, #000E3E) 12%, transparent); }
/* disabled = locked values here (RFA, document type, CAF amounts): greyed and not-allowed, but still readable — the washed-out
   disabled text colour would make the amounts hard to read (-webkit-text-fill-color: Safari ignores `color` on disabled fields) */
#caf-overlay .form-input:disabled, #caf-overlay .form-select:disabled, #caf-overlay .form-textarea:disabled {
    color:var(--afims-text-secondary, #475569);-webkit-text-fill-color:currentColor;opacity:1;
    background-color:var(--afims-surface-subtle, #F4F7FA);cursor:not-allowed; }
/* read-only fields: shown, filled in by script, not typed */
#caf-overlay .form-input[readonly], #caf-overlay .form-textarea[readonly] { background-color:var(--afims-surface-subtle, #F4F7FA);cursor:default; }
/* a value that is not an input (the status badge), same height as the fields around it */
#caf-overlay .caf-static { display:flex;align-items:center;min-height:40px; }
/* peso prefix inside amount inputs */
#caf-overlay .form-group.has-prefix .input-prefix { position:absolute;left:13px;bottom:0;z-index:1;display:flex;align-items:center;height:40px;
    font-size:13px;font-weight:600;color:var(--afims-text-muted, #62657E);pointer-events:none; }
#caf-overlay .form-group.has-prefix .form-input { padding-left:30px; }
#caf-overlay .amt-input { font-weight:500;font-variant-numeric:tabular-nums;text-align:right; }

/* amount lines: each becomes a tinted card */
#caf-overlay .baa-line { margin-bottom:14px;padding:16px;background:var(--afims-surface-subtle, #F4F7FA);
    border:1px solid var(--afims-border, #E4ECF2);border-radius:10px; }
#caf-overlay .caf-line-no { margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;
    color:var(--afims-text-muted, #62657E); }
#caf-overlay .caf-lines-total { margin-top:4px;max-width:340px;margin-left:auto; }
#caf-overlay .caf-empty { padding:14px;text-align:center;font-size:12.5px;color:var(--afims-text-muted, #62657E); }

/* buttons — size / padding / radius come from custom.css like every other FIMS button */
#caf-overlay .btn { font-family:'IBM Plex Sans', sans-serif;font-weight:600;cursor:pointer;border:1px solid transparent; }
#caf-overlay .btn:disabled { opacity:.55;cursor:not-allowed; }
#caf-overlay .btn-icon { flex-shrink:0;width:16px;height:16px; }
#caf-overlay .btn-primary { color:var(--afims-pure-white, #FFFFFF);background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E); }
#caf-overlay .btn-primary:hover:not(:disabled) { opacity:.9; }
/* Dark mode: custom.css paints every .btn-primary with `var(--navy) !important`, and inside .nha-root nha-fims.css
   redefines --navy as the (light) heading TEXT colour — Save CAF became white text on a near-white fill. */
html.afims-dark[data-bs-theme="dark"] #caf-overlay button.btn.btn-primary,
html.afims-dark[data-bs-theme="dark"] #caf-overlay button.btn.btn-primary:disabled {
    color:var(--afims-pure-white, #FFFFFF) !important;background-color:#3861BC !important;border-color:#3861BC !important; }
html.afims-dark[data-bs-theme="dark"] #caf-overlay button.btn.btn-primary:hover:not(:disabled),
html.afims-dark[data-bs-theme="dark"] #caf-overlay button.btn.btn-primary:focus:not(:disabled) {
    color:var(--afims-pure-white, #FFFFFF) !important;background-color:#2E59B8 !important;border-color:#2E59B8 !important; }
#caf-overlay .btn-secondary { color:var(--afims-text, #1A1B21);background:var(--afims-surface, #FFFFFF);border-color:var(--afims-border, #E4ECF2); }
#caf-overlay .btn-secondary:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
#caf-overlay .form-actions { display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:4px;padding:16px 20px;
    background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:12px;box-shadow:0 1px 2px rgba(15,32,69,.05); }
#caf-overlay .btn-group { display:flex;flex-wrap:wrap;align-items:center;gap:10px; }
#caf-overlay .btn-group > .btn { border-radius:var(--ui-radius, 8px) !important; }

@media (max-width:960px) {
    #caf-overlay .form-row.cols-caf { grid-template-columns:repeat(2, minmax(0, 1fr)); }
}
@media (max-width:760px) {
    #caf-overlay .form-row, #caf-overlay .form-row.cols-caf { grid-template-columns:minmax(0, 1fr); }
    #caf-overlay .form-section { padding:18px 16px 20px; }
    #caf-overlay .form-actions, #caf-overlay .btn-group { flex-direction:column;align-items:stretch; }
    #caf-overlay .caf-lines-total { max-width:none; }
}
</style>

<!-- The wrapper makes the shared .nha-overlay / .nha-modal rules apply (they are scoped to .nha-root); padding / background are
     neutralised so it takes no space — the same wrapper as the memo form's modal. -->
<div class="nha-root" style="padding:0;background:none;">
<div id="caf-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-xl caf-modal" role="dialog" aria-modal="true" aria-labelledby="caf-modal-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="caf-modal-title">Create CAF</h3>
            <button type="button" class="nha-modal-close" id="caf-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body" id="caf-modal-body">
<div class="caf-container">

    <!-- Number card: the RFA this CAF is for, and today's date -->
    <div class="form-header">
        <div class="caf-numcard">
            <div>
                <p class="caf-numcard-label">RFA</p>
                <p class="caf-numcard-value" id="caf-card-ref">—</p>
            </div>
            <div style="text-align:right;">
                <p class="caf-numcard-label">Date</p>
                <p class="caf-numcard-date" id="caf-card-date">—</p>
            </div>
        </div>
    </div>

    <form id="caf-form" novalidate autocomplete="off">
        <div class="error-box" id="caf-err" role="alert"></div>

        <!-- ── RFA (preselected from the row that opened the modal) ── -->
        <div class="form-section">
            <h2 class="section-header">RFA</h2>
            <div class="form-row">
                <div class="form-group" id="caf-allocation-group">
                    <label for="caf-allocation" class="form-label">RFA <span class="required">*</span></label>
                    <select id="caf-allocation" name="allocation_id" class="form-select" required disabled aria-readonly="true">
                        <option value="">Loading…</option>
                    </select>
                    <p class="input-hint">The RFA you chose in the list. To create a CAF for another RFA, use Create CAF on that row.</p>
                </div>
                <div class="form-group">
                    <label for="caf-fy" class="form-label">Fiscal Year</label>
                    <input type="text" id="caf-fy" class="form-input" readonly tabindex="-1" placeholder="From the RFA">
                </div>
            </div>
        </div>

        <!-- ── CAF details ── -->
        <div class="form-section">
            <h2 class="section-header">CAF Details</h2>
            <div class="form-row">
                <div class="form-group" id="caf-number-group">
                    <label for="caf-number" class="form-label">CAF Number</label>
                    <input type="text" id="caf-number" name="caf_number" class="form-input" readonly tabindex="-1" placeholder="Not assigned yet">
                    <p class="input-hint" id="caf-number-hint">Same as the RFA number.</p>
                </div>
                <div class="form-group">
                    <label for="caf-doctype" class="form-label">Document Type</label>
                    <select id="caf-doctype" name="document_type" class="form-select" disabled aria-readonly="true">
                        <option value="">Select document type</option>
                        <option value="CO">CO &mdash; Capital Outlay</option>
                        <option value="COE">COE &mdash; Corporate Operating Expenditure</option>
                    </select>
                    <p class="input-hint">The RFA's own document type.</p>
                </div>
            </div>
        </div>

        <!-- ── RFA details (read-only) ── -->
        <div class="form-section">
            <h2 class="section-header">RFA Details</h2>
            <div id="caf-summary"><div class="caf-empty">Select an RFA.</div></div>
        </div>

        <!-- ── CAF lines ── -->
        <div class="form-section">
            <h2 class="section-header">CAF Lines</h2>
            <div id="caf-lines"><div class="caf-empty">Select an RFA.</div></div>
        </div>

        <!-- Form Actions -->
        <div class="form-actions">
            <button type="button" class="btn btn-secondary" id="caf-cancel">
                <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                Cancel
            </button>
            <div class="btn-group">
                <button type="submit" class="btn btn-primary" id="caf-save" disabled>
                    <svg class="btn-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span id="caf-save-label">Save CAF</span>
                </button>
            </div>
        </div>
    </form>
</div>
        </div><!-- /.nha-modal-body -->
    </div><!-- /.nha-modal -->
</div><!-- /#caf-overlay -->
</div><!-- /.nha-root (modal wrapper) -->

<script>
/* ══════════════════════════════════════════════════════════════
   CAF FORM MODAL   window.cafModal.open(allocationId) / .close()   (see the notes at the top of this file)
   NOTHING of memo-req-page.php's script (MRQ_*, mrq_*) may be used at load time — see "LOAD ORDER" above.
═══════════════════════════════════════════════════════════════ */
(function () {
    'use strict';

    const ALLOCATION_TYPE = 'RFA';             // this form is for RFAs only

    const byId   = id => document.getElementById(id);
    const cafApi = () => MRQ_BASE + '/caf';    // read when used (a click), not at load: the page declares MRQ_BASE after this script

    let seq     = 0;       // bumped on every open / close / RFA change, so a slow answer for something replaced meanwhile is dropped
    let busy    = false;   // a load or a save is in flight
    let saving  = false;   // the CAF itself is being saved (the modal cannot be closed meanwhile)
    let opener  = null;    // what had the focus before the modal opened
    let rfa     = null;    // the RFA shown: the full record from /budget_allocation/read/{id}
    let lines   = [];      // [{ src: <the RFA's line as stored>, amount: '<its CAF Amount: the requested amount, "1234.50">' }]

    /* ── Small helpers ─────────────────────────────────────────── */
    // a read-only field, in the look of the form's own fields
    const roField = (label, value, extraCls = '') =>
        `<div class="form-group${extraCls}"><label class="form-label">${mrq_esc(label)}</label>` +
        `<input type="text" class="form-input" readonly tabindex="-1" value="${mrq_esc(value)}" placeholder="—"></div>`;
    // "<name> (<code>)" for a Program / Sub-Program / Project / office
    const named = (name, code) => [name, code ? `(${code})` : ''].filter(Boolean).join(' ');

    // "1,234.50" / 1234.5 / "" -> a plain "1234.50" string, or '' when it is not a (non-negative) number
    function plainAmount(v) {
        const n = Number(String(v ?? '').replace(/,/g, '').trim());
        return (v === null || v === undefined || String(v).trim() === '' || !isFinite(n) || n < 0) ? '' : n.toFixed(2);
    }
    // 1234.5 -> "1,234.50" (no peso sign: the field carries its own ₱ prefix)
    const fmt = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // line_transaction is a JSON array of lines (or, in an old record, one object); anything else means no lines.
    function normalizeLines(lt) {
        if (Array.isArray(lt)) return lt.filter(l => l && typeof l === 'object');
        if (lt && typeof lt === 'object') return [lt];
        return [];
    }

    // How an RFA is named in the select: its allocation number (only given by the final approval), else its request
    // reference (RR-001, ...), then the project when the list carries it.
    function rfaLabel(r) {
        const ref   = r.allocation_number || r.request_referrence_no || `#${r.allocation_id}`;
        const extra = r.project_name || r.program_name || '';
        return extra ? `${ref} — ${extra}` : ref;
    }

    function showErr(msg) {
        const box = byId('caf-err');
        box.textContent = msg || '';
        box.classList.toggle('visible', !!msg);
    }

    // While a load or a save is in flight nothing can be changed or sent twice.
    function setBusy(isBusy, isSaving = false) {
        busy   = isBusy;
        saving = isBusy && isSaving;
        byId('caf-save').disabled   = isBusy || !rfa;
        byId('caf-cancel').disabled = saving;
        byId('caf-close').disabled  = saving;
        byId('caf-save-label').textContent = saving ? 'Saving…' : 'Save CAF';
    }

    /* ── Render ────────────────────────────────────────────────── */
    function renderCard() {
        byId('caf-card-ref').textContent = rfa ? (rfa.allocation_number || mrq_refOf(rfa) || `#${rfa.allocation_id}`) : '—';
    }

    // The CAF Number is the RFA's number (allocation_number) — shown read-only, and what is saved as caf_number.
    function cafNumberOf(r) {
        return r ? String(r.allocation_number || '').trim() : '';
    }
    // The Document Type is the RFA's own (document_type) — shown in the disabled select, and what is saved.
    function docTypeOf(r) {
        return r ? String(r.document_type || '').trim() : '';
    }
    function renderCafNumber() {
        const number = cafNumberOf(rfa);
        byId('caf-number').value = number;
        byId('caf-number-hint').textContent = !rfa || number
            ? 'Same as the RFA number.'
            : 'This RFA has no number yet — it is given on its final approval, so the CAF is saved without one.';
    }

    function renderSummary() {
        if (!rfa) { byId('caf-summary').innerHTML = '<div class="caf-empty">Select an RFA.</div>'; return; }
        const office = [named(rfa.requesting_rc_name, rfa.requesting_rc_code), rfa.requesting_district_name].filter(Boolean).join(' — ');
        byId('caf-summary').innerHTML =
            `<div class="form-row">` +
                roField('Request Reference Number', mrq_refOf(rfa)) +
                roField('Allocation Number', rfa.allocation_number || '') +
            `</div><div class="form-row">` +
                `<div class="form-group"><label class="form-label">Status</label><div class="caf-static">${mrq_statusBadge(rfa.status_name, rfa.status_color)}</div></div>` +
                roField('Requesting Office', office) +
            `</div><div class="form-row cols-3">` +
                roField('Program', named(rfa.program_name, rfa.program_code)) +
                roField('Sub-Program', named(rfa.sub_program_name, rfa.sub_program_code)) +
                roField('Project', named(rfa.project_name, rfa.project_code)) +
            `</div><div class="form-row">` +
                roField('Vendor', rfa.vendor_name || '') +
                `<div class="form-group"></div>` +
            `</div><div class="form-row full-width">` +
                `<div class="form-group"><label class="form-label">Purpose / Description</label>` +
                `<textarea class="form-textarea" readonly tabindex="-1" placeholder="No description.">${mrq_esc(rfa.description || '')}</textarea></div>` +
            `</div>`;
    }

    function total() {
        return lines.reduce((sum, l) => sum + (Number(l.amount) || 0), 0);
    }

    function renderLines() {
        const box = byId('caf-lines');
        if (!rfa) { box.innerHTML = '<div class="caf-empty">Select an RFA.</div>'; return; }
        if (!lines.length) { box.innerHTML = '<div class="caf-empty">This RFA has no budget allocation lines.</div>'; return; }
        const cards = lines.map((l, i) => {
            const s = l.src;
            const account = [s.account_code, s.account_title].filter(Boolean).join(' — ');
            return `<div class="baa-line">
                <p class="caf-line-no">Line ${i + 1}</p>
                <div class="form-row cols-caf">
                    ${roField('Type of Fund', s.type_of_fund || '')}
                    ${roField('Account', account)}
                    <div class="form-group has-prefix"><label class="form-label">RFA Requested Amount</label><span class="input-prefix">&#8369;</span>
                        <input type="text" class="form-input amt-input" readonly tabindex="-1" value="${mrq_esc(fmt(s.requested_amount))}"></div>
                    <div class="form-group has-prefix"><label class="form-label" for="caf-amount-${i}">CAF Amount</label><span class="input-prefix">&#8369;</span>
                        <input type="text" id="caf-amount-${i}" class="form-input amt-input caf-amount" disabled aria-readonly="true" tabindex="-1"
                            value="${mrq_esc(fmt(l.amount))}"></div>
                </div>
            </div>`;
        }).join('');
        box.innerHTML = cards +
            `<div class="form-group has-prefix caf-lines-total"><label class="form-label" for="caf-total">Total CAF Amount</label><span class="input-prefix">&#8369;</span>` +
            `<input type="text" id="caf-total" class="form-input amt-input" readonly tabindex="-1" value="${mrq_esc(fmt(total()))}"></div>`;
    }

    /* ── Load ──────────────────────────────────────────────────── */
    // The RFA field: a select that is always disabled and holds just the RFA the modal was opened for (SELECTED). The user
    // already found the RFA in the list and clicked "Create CAF" on it, so it is shown, never chosen here.
    // r = null: no RFA (the record turned out not to be an RFA, or could not be read).
    function showRfa(r) {
        const sel = byId('caf-allocation');
        sel.innerHTML = r
            ? `<option value="${mrq_esc(r.allocation_id)}">${mrq_esc(rfaLabel(r))}</option>`
            : '<option value="">—</option>';
        sel.value = r ? String(r.allocation_id) : '';
    }

    // Shows a loaded RFA in the form: fiscal year, document type, the summary and the lines. Throws a message when the
    // record is not an RFA (the read answers for any allocation type) — nothing is changed then.
    function applyRfa(r) {
        if (String(r.allocation_type || '').toUpperCase() !== ALLOCATION_TYPE) {
            throw new Error(`Only an RFA can have a CAF — this request is ${r.allocation_type ? 'a ' + r.allocation_type : 'not an RFA'}.`);
        }
        // Only an FMD user may create a CAF, and only for an Approved RFA — a superadmin is exempt from both. This is the
        // same message the API answers with (CafService::create() refuses the rest), shown before anything is filled in.
        if (!MRQ_IS_ADMIN) {
            if (!MRQ_IS_FMD) {
                throw new Error('Only the Financial Management Department (FMD) can create a CAF.');
            }
            if (!mrq_isApproved(r)) {
                throw new Error(`A CAF can only be created for an Approved RFA — this RFA is ${r.status_name || 'without a status'}.`);
            }
            if (mrq_hasCaf(r)) {
                throw new Error('This RFA already has a CAF — an RFA can only have one.');
            }
        }
        rfa   = r;
        // each line's CAF Amount is its requested amount; none usable (blank / not a number) counts as 0.00 — it is locked, so
        // it must never be a value that cannot be saved
        lines = normalizeLines(r.line_transaction).map(src => ({ src, amount: plainAmount(src.requested_amount) || '0.00' }));

        byId('caf-fy').value = r.fiscal_year ?? '';
        // the RFA's own document type; one the select does not offer (should not happen) is added, never lost
        const docType = docTypeOf(r);
        const sel = byId('caf-doctype');
        if (docType && ![...sel.options].some(o => o.value === docType)) sel.add(new Option(docType, docType));
        sel.value = docType;

        renderCard();
        renderCafNumber();
        renderSummary();
        renderLines();
    }

    function clearRfa() {
        rfa = null;
        lines = [];
        byId('caf-fy').value = '';
        byId('caf-doctype').value = '';
        renderCard();
        renderCafNumber();
        renderSummary();
        renderLines();
    }

    async function readRfa(id) {
        const json = await mrq_request(`${MRQ_API}/read/${encodeURIComponent(id)}`);
        return json.data;
    }

    /* ── Open / close ──────────────────────────────────────────── */
    async function open(allocationId) {
        const id = Number(allocationId);
        const my = ++seq;
        opener = document.activeElement;

        byId('caf-form').reset();
        showErr('');
        clearRfa();
        byId('caf-card-date').textContent = new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: '2-digit' });
        byId('caf-allocation').innerHTML = '<option value="">Loading…</option>';
        byId('caf-summary').innerHTML = '<div class="caf-empty">Loading…</div>';
        byId('caf-lines').innerHTML   = '<div class="caf-empty">Loading…</div>';
        setBusy(true);
        byId('caf-overlay').classList.add('open');
        byId('caf-modal-body').scrollTop = 0;
        byId('caf-cancel').focus();

        if (!(id > 0)) {
            showRfa(null);
            clearRfa();
            setBusy(false);
            showErr('No RFA was given for this CAF.');
            return;
        }

        // The RFA the modal was opened for is the only one this CAF can be for: it is read and the form is built from it.
        let record;
        try {
            record = await readRfa(id);
        } catch (e) {
            if (my !== seq) return;
            showRfa(null);
            clearRfa();
            setBusy(false);
            showErr(e.message);
            return;
        }
        if (my !== seq) return;

        try {
            applyRfa(record);                       // throws when the record is not an RFA
            showRfa(record);
        } catch (e) {
            showRfa(null);
            clearRfa();
            showErr(e.message);
        }
        setBusy(false);
    }

    function close() {
        if (saving) return;   // not while the CAF is being saved
        seq++;
        busy = false;
        saving = false;
        byId('caf-overlay').classList.remove('open');
        if (opener && typeof opener.focus === 'function') opener.focus();
    }

    /* ── Save ──────────────────────────────────────────────────── */
    async function save(ev) {
        ev.preventDefault();
        if (busy) return;
        showErr('');

        if (!rfa) {   // Save is disabled without an RFA, so this is only a safety net
            showErr('No RFA is loaded for this CAF. Close this form and use Create CAF on an RFA in the list.');
            return;
        }

        // Nothing is typed in this form: every value comes from the RFA, so there is nothing to validate here (the API still does).
        const payload = {
            allocation_id   : Number(rfa.allocation_id),
            fiscal_year     : rfa.fiscal_year !== null && rfa.fiscal_year !== undefined && rfa.fiscal_year !== '' ? Number(rfa.fiscal_year) : null,
            caf_number      : cafNumberOf(rfa),   // the CAF carries its RFA's number
            document_type   : docTypeOf(rfa),     // ... and its document type
            line_transaction: lines.map(l => ({ ...l.src, caf_amount: l.amount })),   // ... and each line's requested amount
        };

        const my = seq;
        setBusy(true, true);
        try {
            const json = await mrq_request(`${cafApi()}/add`, {
                method : 'POST',
                headers: { 'Content-Type': 'application/json' },
                body   : JSON.stringify(payload),
            });
            setBusy(false);
            mrq_toast(json.message || 'CAF created successfully.', 'success');
            close();
            // the RFA now has its CAF: its row in the list loses Create CAF
            if (typeof mrq_onCafCreated === 'function') mrq_onCafCreated(payload.allocation_id);
        } catch (e) {
            setBusy(false);
            if (my === seq) showErr(e.message);   // stays open: the reason is shown above the form
        }
    }

    /* ── Wiring (DOM only — all of these elements are above this script) ───── */
    byId('caf-form').addEventListener('submit', save);
    byId('caf-close').addEventListener('click', close);
    byId('caf-cancel').addEventListener('click', close);
    // a click on the dark backdrop (not on the box) closes it
    byId('caf-overlay').addEventListener('mousedown', e => { if (e.target === byId('caf-overlay')) close(); });
    document.addEventListener('keydown', e => {
        if (!byId('caf-overlay').classList.contains('open')) return;
        if (e.key === 'Escape') { e.preventDefault(); close(); return; }
        if (e.key === 'Tab') {   // keep the focus inside the modal
            const els = [...byId('caf-overlay').querySelectorAll('input:not([readonly]):not([disabled]), select:not([disabled]), button:not([disabled])')];
            if (!els.length) return;
            const first = els[0], last = els[els.length - 1];
            if (!byId('caf-overlay').contains(document.activeElement) || (e.shiftKey && document.activeElement === first)) { e.preventDefault(); (e.shiftKey ? last : first).focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });

    window.cafModal = { open, close };
})();
</script>
