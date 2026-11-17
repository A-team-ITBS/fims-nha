<?php
/**
 * FtlaFormModal.php
 *
 * Standalone modal stepper for Fund Transfer Letter of Advice (FTLA).
 * Include at the bottom of FtlaPrepation.php.
 * Trigger with: ftlm_open() or ftlm_open(id)
 */
namespace PHPMaker2024\FIMS;

$_ftlm_api = $apiBase ?? Config('API.FIMS_URL');
?>

<!-- ══════════════════════════════════════════════════════════
     FTLA FORM MODAL STEPPER
     JS namespace: ftlm_
══════════════════════════════════════════════════════════ -->
<style>
    /* ── Modal base ──────────────────────────────────────────── */
    #ftlm-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9100;
        background: rgba(15, 32, 69, .6);
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(3px);
    }

    #ftlm-card {
        background: var(--afims-surface, #FFFFFF);
        border-radius: 16px;
        width: 96vw;
        max-width: 1060px;
        max-height: 94vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 32px 80px rgba(0, 0, 0, .28);
        font-family: 'IBM Plex Sans', sans-serif;
        animation: ftlmSlideIn .22s ease;
        transition: max-width .2s ease;
    }
    #ftlm-card.ftlm-wide { max-width: 1200px; }

    @keyframes ftlmSlideIn {
        from { opacity: 0; transform: translateY(20px) scale(.98); }
        to   { opacity: 1; transform: translateY(0)    scale(1);   }
    }

    /* ── Header ──────────────────────────────────────────────── */
    #ftlm-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 22px;
        background: var(--afims-heading-fill, #000E3E);
        flex-shrink: 0;
    }

    #ftlm-header-info { flex: 1; }

    #ftlm-title {
        font-size: 15px;
        font-weight: 700;
        color: var(--afims-pure-white, #FFFFFF);
        letter-spacing: .01em;
    }

    #ftlm-subtitle {
        font-size: 11px;
        color: rgba(255,255,255,.55);
        margin-top: 1px;
    }

    #ftlm-close-btn {
        background: color-mix(in srgb, var(--afims-surface, #FFFFFF) 10%, transparent);
        border: none;
        border-radius: 7px;
        cursor: pointer;
        padding: 6px;
        line-height: 0;
        transition: background .12s;
        color: var(--afims-pure-white, #FFFFFF);
    }

    #ftlm-close-btn:hover { background: color-mix(in srgb, var(--afims-surface, #FFFFFF) 20%, transparent); }

    /* ── Stepper ─────────────────────────────────────────────── */
    #ftlm-stepper {
        display: flex;
        align-items: center;
        padding: 14px 24px;
        background: var(--afims-surface-subtle, #F4F7FA);
        border-bottom: 1px solid var(--afims-border, #E4ECF2);
        flex-shrink: 0;
        gap: 0;
    }

    .ftlm-step {
        display: flex;
        align-items: center;
        gap: 8px;
        flex: 1;
        position: relative;
    }

    .ftlm-step-circle {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        border: 2px solid var(--afims-border, #E4ECF2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 700;
        color: var(--afims-text-disabled, #A9AAB8);
        background: var(--afims-surface, #FFFFFF);
        flex-shrink: 0;
        transition: all .2s;
    }

    .ftlm-step.active .ftlm-step-circle {
        border-color: var(--afims-heading-fill, #000E3E);
        background: var(--afims-heading-fill, #000E3E);
        color: var(--afims-pure-white, #FFFFFF);
    }

    .ftlm-step.done .ftlm-step-circle {
        border-color: var(--afims-success, #11A752);
        background: var(--afims-success, #11A752);
        color: var(--afims-pure-white, #FFFFFF);
    }

    .ftlm-step-label {
        font-size: 12px;
        font-weight: 600;
        color: var(--afims-text-disabled, #A9AAB8);
        white-space: nowrap;
        transition: color .2s;
    }

    .ftlm-step.active .ftlm-step-label { color: var(--afims-heading, #000E3E); }
    .ftlm-step.done  .ftlm-step-label { color: var(--afims-success-hover, #0C7539); }

    .ftlm-step-connector {
        flex: 1;
        height: 2px;
        background: var(--afims-border, #E4ECF2);
        margin: 0 10px;
        min-width: 20px;
        transition: background .2s;
    }

    /* ── Body (split: form + preview) ───────────────────────── */
    #ftlm-body-split {
        flex: 1;
        display: flex;
        overflow: hidden;
        min-height: 0;
    }

    /* ── Form panel ──────────────────────────────────────────── */
    #ftlm-form-panel {
        flex: 1 1 380px;
        min-height: 0;
        min-width: 0;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 20px 22px;
    }

    .ftlm-panel { display: none; }
    .ftlm-panel.ftlm-active { display: block;
            padding: 20px;
    }

    /* ── Preview panel ───────────────────────────────────────── */
    /* Wider than the form panel — the letter is a fixed 794px-wide sheet, so it
       needs the extra room to stay legibly scaled instead of shrinking to near-nothing. */
    #ftlm-preview-panel {
        flex: 1.6 1 420px;
        min-width: 0;
        display: flex;
        flex-direction: column;
        background: var(--afims-surface-subtle, #F4F7FA);
        overflow: hidden;
    }

    #ftlm-preview-label {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: var(--afims-text-disabled, #A9AAB8);
        padding: 10px 14px 6px;
        flex-shrink: 0;
    }

    #ftlm-preview-wrap {
        flex: 1;
        overflow-y: auto;
        overflow-x: auto;
        padding: 0 10px 10px;
        position: relative;
    }

    #ftlm-preview-inner {
        transform-origin: top left;
        background: var(--afims-surface, #FFFFFF);
        box-shadow: 0 2px 12px rgba(0,0,0,.13);
    }

    .ftlm-preview-placeholder {
        padding: 40px 20px;
        text-align: center;
        font-size: 13px;
        color: var(--afims-text-disabled, #A9AAB8);
    }

    /* ── Section heading ─────────────────────────────────────── */
    .ftlm-sec-hd {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--afims-text-muted, #62657E);
        margin: 18px 0 10px;
        padding-bottom: 6px;
        border-bottom: 1px solid var(--afims-border, #E4ECF2);
    }

    .ftlm-sec-hd:first-child { margin-top: 0; }

    /* ── Form groups ─────────────────────────────────────────── */
    #ftlm-card .ftlm-fg {
        display: flex;
        flex-direction: column;
        gap: 4px;
        margin-bottom: 14px;
    }

    #ftlm-card .ftlm-fg:last-child { margin-bottom: 0; }

    #ftlm-card .ftlm-label {
        font-size: 11.5px;
        font-weight: 600;
        color: var(--afims-text, #1A1B21);
    }

    #ftlm-card .ftlm-label.req::after {
        content: ' *';
        color: var(--afims-danger, #DC3545);
    }

    #ftlm-card .ftlm-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px;
    }

    #ftlm-card .ftlm-grid-2 > * {
        min-width: 0;
        overflow: hidden;
    }

    #ftlm-card .ftlm-fg select.form-control,
    #ftlm-card .ftlm-fg input.form-control {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    #ftlm-card .ftlm-fg select.form-control:disabled {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background: var(--afims-surface-subtle, #F4F7FA);
        color: var(--afims-text-muted, #62657E);
        cursor: not-allowed;
    }

    /* ── Error banner ────────────────────────────────────────── */
    #ftlm-error {
        display: none;
        margin-bottom: 14px;
        background: var(--afims-danger-soft, #FAE1E3);
        border: 1px solid var(--afims-danger-soft, #FAE1E3);
        border-radius: 8px;
        padding: 9px 13px;
        font-size: 12.5px;
        color: var(--afims-danger, #DC3545);
        white-space: pre-wrap;
    }

    /* ── Field-level error states ────────────────────────────── */
    .ftlm-fg.has-err .form-control {
        border-color: var(--afims-danger, #DC3545) !important;
        box-shadow: 0 0 0 3px rgba(239,68,68,.1);
    }
    .ftlm-err-msg {
        display: none;
        font-size: 11px;
        color: var(--afims-danger, #DC3545);
        margin: 2px 0 0;
    }
    .ftlm-fg.has-err .ftlm-err-msg { display: block; }

    /* ── DV Numbers — searchable multi-select tag picker (same pattern as
       lv-form.php's CIBR picker: a fixed-position dropdown so results are
       never clipped by #ftlm-form-panel's own overflow-y:auto ancestor). ── */
    .ftlm-msel { position: relative; }
    /* Border/radius/background intentionally left to the composed .form-control
       class (see the HTML) so this picker's resting state matches every other
       field on the step exactly, instead of redeclaring them here. */
    .ftlm-tag-input {
        display: flex; flex-wrap: wrap; align-items: center; gap: 5px;
        padding: 6px 10px; min-height: 38px; cursor: text; transition: border-color .15s;
    }
    .ftlm-tag-input:focus-within { border-color: var(--afims-heading-fill, #000E3E); }
    .ftlm-tag-input #ftlm-dv-search {
        border: none; outline: none; font-size: 13px; font-family: inherit;
        color: var(--afims-text, #1A1B21); background: transparent; flex: 1; min-width: 160px; padding: 3px 2px;
    }
    .ftlm-msel-dropdown {
        position: fixed; z-index: 9999;
        background: var(--afims-surface, #FFFFFF); border: 1.5px solid var(--afims-border, #E4ECF2); border-radius: 8px;
        max-height: 220px; overflow-y: auto; box-shadow: 0 4px 16px rgba(0,0,0,.1);
    }
    .ftlm-msel-option {
        display: flex; align-items: center; gap: 9px; padding: 9px 12px;
        font-size: 13px; cursor: pointer; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA);
    }
    .ftlm-msel-option:last-child { border-bottom: none; }
    .ftlm-msel-option:hover { background: var(--afims-primary, #0035A9); color: var(--afims-pure-white, #FFFFFF); }
    .ftlm-msel-option:hover .ftlm-msel-option-chk { border-color: rgba(255,255,255,.6); }
    .ftlm-msel-option.selected { background: var(--afims-primary, #0035A9); color: var(--afims-pure-white, #FFFFFF); }
    .ftlm-msel-option-chk {
        width: 16px; height: 16px; border-radius: 4px; border: 1.5px solid var(--afims-border, #E4ECF2);
        flex-shrink: 0; display: flex; align-items: center; justify-content: center;
        font-size: 11px; color: var(--afims-pure-white, #FFFFFF); background: var(--afims-surface, #FFFFFF);
    }
    .ftlm-msel-option.selected .ftlm-msel-option-chk { background: var(--afims-surface, #FFFFFF); border-color: var(--afims-pure-white, #FFFFFF); color: var(--afims-primary-text, #0035A9); }
    .ftlm-msel-option-empty { padding: 10px 12px; font-size: 12px; color: var(--muted); }
    .ftlm-msel-tag {
        display: inline-flex; align-items: center; gap: 6px; background: var(--afims-primary-soft, #E6EBF6);
        color: var(--afims-primary-text, #0035A9); border-radius: 20px; padding: 4px 6px 4px 10px; font-size: 11.5px; font-weight: 600;
    }
    .ftlm-msel-tag button {
        border: none; background: rgba(30,64,175,.12); cursor: pointer; color: var(--afims-primary-text, #0035A9);
        font-size: 12px; line-height: 1; padding: 3px 5px; border-radius: 50%; display: flex;
    }
    .ftlm-msel-tag button:hover { background: rgba(30,64,175,.25); }

    /* ── Temporary toggle ────────────────────────────────────── */
    .ftlm-tmp-row {
        display: flex;
        align-items: center;
        gap: 7px;
        cursor: pointer;
        font-size: 12.5px;
        color: var(--afims-text, #1A1B21);
        margin-top: 6px;
    }

    .ftlm-tmp-row input[type=checkbox] {
        width: 15px;
        height: 15px;
        accent-color: var(--afims-heading, #000E3E);
        cursor: pointer;
    }

    /* ── Footer ──────────────────────────────────────────────── */
    #ftlm-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 22px;
        background: var(--afims-surface-subtle, #F4F7FA);
        border-top: 1px solid var(--afims-border, #E4ECF2);
        flex-shrink: 0;
    }

    #ftlm-step-counter {
        font-size: 11.5px;
        color: var(--afims-text-muted, #62657E);
        font-weight: 600;
    }

    #ftlm-footer-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    #ftlm-form-msg {
        font-size: 12px;
        color: var(--afims-text-muted, #62657E);
    }

    /* ── Footer secondary buttons (Cancel / Back) ─────────────── */
    .ftlm-btn-secondary {
        background: var(--afims-surface, #FFFFFF);
        color: var(--afims-text, #1A1B21);
        border: 1px solid var(--afims-border, #E4ECF2);
    }
    .ftlm-btn-secondary:hover { background: var(--afims-surface-subtle, #F4F7FA); opacity: 1; }

    /* ── Paper-document Review Preview (Step 5) ─────────────────
       Self-contained paper-form replica styles — mirrors the .afmp-*
       classes used by ba-form.php's Review step, scoped under a
       distinct "ftlp-" prefix (Fund Transfer Letter Preview) since
       ftla-form.php never shares a page with ba-form.php. */
    .ftlp-paper {
        font-family: 'Times New Roman', Times, serif;
        font-size: 12px; color: var(--afims-text, #1A1B21); background: var(--afims-surface, #FFFFFF);
        border: 1px solid var(--afims-text-disabled, #A9AAB8); border-radius: 2px;
        box-shadow: 0 4px 24px rgba(0,0,0,.12);
        padding: 30px 40px; width: 8.5in; min-height: 14in;
        box-sizing: border-box; margin: 0 auto;
    }
    .ftlp-bold      { font-weight: 700; }
    .ftlp-xs        { font-size: 10.5px; }
    .ftlp-underline { text-decoration: underline; }
    .ftlp-justify   { text-align: justify; }
    .ftlp-row       { display: flex; justify-content: space-between; gap: 14px; }
    .ftlp-hr        { border: none; border-top: 1.5px solid var(--afims-text, #1A1B21); margin: 16px 0 10px; }
    .ftlp-sig-block { text-align: center; }
    .ftlp-center      { text-align: center; }
    .ftlp-field-row   { display: flex; align-items: flex-start; gap: 4px; }
    .ftlp-field-label { width: 112px; flex-shrink: 0; white-space: nowrap; }

    /* ── Responsive ──────────────────────────────────────────── */
    @media (max-width: 800px) {
        #ftlm-preview-panel { display: none; }
        #ftlm-form-panel { flex: 1; border-right: none; }
        #ftlm-card { max-width: 600px; }
        #ftlm-card .ftlm-grid-2 { grid-template-columns: 1fr; }
    }
</style>

<div id="ftlm-overlay" role="dialog" aria-modal="true" aria-labelledby="ftlm-title">
    <div id="ftlm-card">

        <!-- Header -->
        <div id="ftlm-header">
            <div id="ftlm-header-info">
                <div id="ftlm-title">New FTLA</div>
                <div id="ftlm-subtitle">Fund Transfer Letter of Advice</div>
            </div>
            <button type="button" id="ftlm-close-btn" title="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        <!-- Stepper -->
        <div id="ftlm-stepper">
            <div class="ftlm-step active" id="ftlm-step-0">
                <div class="ftlm-step-circle" id="ftlm-circle-0">1</div>
                <span class="ftlm-step-label">Transfer Details</span>
                <div class="ftlm-step-connector"></div>
            </div>
            <div class="ftlm-step" id="ftlm-step-1">
                <div class="ftlm-step-circle" id="ftlm-circle-1">2</div>
                <span class="ftlm-step-label">Attachments</span>
                <div class="ftlm-step-connector"></div>
            </div>
            <div class="ftlm-step" id="ftlm-step-2">
                <div class="ftlm-step-circle" id="ftlm-circle-2">3</div>
                <span class="ftlm-step-label">Signatories</span>
                <div class="ftlm-step-connector"></div>
            </div>
            <div class="ftlm-step" id="ftlm-step-3">
                <div class="ftlm-step-circle" id="ftlm-circle-3">4</div>
                <span class="ftlm-step-label">Review</span>
            </div>
        </div>

        <div id="ftlm-body-split">
        <div id="ftlm-form-panel">

                <input type="hidden" id="ftlm_fund_transfer_id">

                <div id="ftlm-error"></div>

                <!-- ═══════════════════════════════
                     Step 1 — Transfer Details
                ═══════════════════════════════ -->
                <div class="ftlm-panel ftlm-active" id="ftlm-panel-0">

                    <div class="ftlm-sec-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/>
                        </svg>
                        Transfer Details
                    </div>

                    <div class="ftlm-grid-2">
                        <div class="ftlm-fg" id="ftlm-fg-type">
                            <label class="ftlm-label req" for="ftlm_transfer_type">Transfer Type</label>
                            <select class="form-control" id="ftlm_transfer_type" name="transfer_type" onchange="ftlm_schedulePreviewUpdate()">
                                <option value="">Loading…</option>
                            </select>
                            <p class="ftlm-err-msg">Transfer Type is required.</p>
                        </div>
                        <div class="ftlm-fg" id="ftlm-fg-date">
                            <label class="ftlm-label req" for="ftlm_transfer_date">Transfer Date</label>
                            <input type="date" class="form-control" id="ftlm_transfer_date" name="transfer_date"
                                   onchange="ftlm_schedulePreviewUpdate()">
                            <p class="ftlm-err-msg">Transfer Date is required.</p>
                        </div>
                    </div>

                    <input type="hidden" id="ftlm_ftla_number" name="ftla_number">

                    <div class="ftlm-sec-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>
                        </svg>
                        Bank Accounts
                    </div>

                    <div class="ftlm-grid-2" id="ftlm-destination-wrap">
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_from_bank_account_id">Destination Bank</label>
                            <select class="form-control" id="ftlm_from_bank_account_id" name="to_bank_account_id" disabled
                                    onchange="ftlm_updateFundSourceDisplay(); ftlm_schedulePreviewUpdate();">
                                <option value="">Loading…</option>
                            </select>
                        </div>
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_from_fund_source_name">Fund Source</label>
                            <input type="text" class="form-control" id="ftlm_from_fund_source_name"
                                   readonly placeholder="—" style="background:var(--afims-border, #E4ECF2);cursor:default;">
                        </div>
                    </div>

                    <div class="ftlm-fg" style="flex-direction:row;align-items:center;gap:8px;margin:2px 0 8px;">
                        <label class="ftlm-tmp-row">
                            <input type="checkbox" id="ftlm_multi_debit" onchange="ftlm_toggleMultiDebit()">
                            <span>Multiple debit accounts (split the source across several accounts)</span>
                        </label>
                    </div>

                    <div class="ftlm-grid-2" id="ftlm-single-debit-wrap">
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_from_source_bank_account_id">Debit (Source) Bank Account</label>
                            <select class="form-control" id="ftlm_from_source_bank_account_id" disabled onchange="ftlm_schedulePreviewUpdate()">
                                <option value="">Loading…</option>
                            </select>
                        </div>
                    </div>

                    <div id="ftlm-multi-debit-wrap" style="display:none;margin-bottom:10px;">
                        <table style="width:100%;border-collapse:collapse;" id="ftlm-debit-table">
                            <thead>
                                <tr style="border-bottom:2px solid var(--afims-border, #E4ECF2);">
                                    <th style="text-align:left;padding:5px 10px;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.06em;">Debit (From)</th>
                                    <th style="text-align:left;padding:5px 10px;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.06em;">Credit (To)</th>
                                    <th style="text-align:right;padding:5px 10px;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.06em;width:140px;">Amount</th>
                                    <th style="width:28px;"></th>
                                </tr>
                            </thead>
                            <tbody id="ftlm-debit-tbody"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="2" style="padding:6px 10px;font-size:12px;font-weight:700;text-align:right;">Total</td>
                                    <td style="padding:6px 10px;font-size:12px;font-weight:700;text-align:right;" id="ftlm-debit-total">₱0.00</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                        <button type="button" onclick="ftlm_addDebitRow()"
                            style="margin-top:8px;display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--afims-heading, #000E3E);background:none;border:1.5px dashed var(--afims-border, #E4ECF2);border-radius:8px;padding:6px 14px;cursor:pointer;">
                            <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:12px;height:12px;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                            </svg>
                            Add Account
                        </button>
                        <p style="margin:6px 0 0;font-size:11px;color:var(--afims-text-disabled, #A9AAB8);">
                            Every row starts with the same credit account — change a row's "Credit (To)" to pair that debit with its own credit account instead.
                        </p>
                    </div>

                    <div class="ftlm-sec-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                        Amount &amp; Purpose
                    </div>

                    <div class="ftlm-grid-2">
                        <div class="ftlm-fg" id="ftlm-fg-amount">
                            <label class="ftlm-label req" for="ftlm_transfer_amount">Transfer Amount (PHP)</label>
                            <input type="text" class="form-control" id="ftlm_transfer_amount" name="transfer_amount"
                                   inputmode="decimal" placeholder="0.00" autocomplete="off"
                                   oninput="ftlm_formatAmount(this); ftlm_schedulePreviewUpdate();">
                            <p class="ftlm-err-msg">Transfer Amount must be greater than 0.</p>
                        </div>
                        <div class="ftlm-fg" style="justify-content:flex-end;padding-bottom:4px;">
                            <label class="ftlm-tmp-row">
                                <input type="checkbox" id="ftlm_is_temporary" name="is_temporary">
                                <span>Temporary transfer</span>
                            </label>
                        </div>
                    </div>

                    <div class="ftlm-fg" id="ftlm-repayment-field" style="display:none">
                        <label class="ftlm-label" for="ftlm_repayment_due_date">Repayment Due Date</label>
                        <input type="date" class="form-control" id="ftlm_repayment_due_date" name="repayment_due_date">
                    </div>

                    <div class="ftlm-fg" id="ftlm-fg-purpose">
                        <label class="ftlm-label req" for="ftlm_purpose">Purpose / Particulars</label>
                        <textarea class="form-control" id="ftlm_purpose" name="purpose" rows="3"
                                  placeholder="Describe the reason for this fund transfer…"
                                  oninput="ftlm_schedulePreviewUpdate()"></textarea>
                        <p class="ftlm-err-msg">Purpose / Particulars is required.</p>
                    </div>

                </div><!-- /panel-0 -->

                <!-- ═══════════════════════════════
                     Step 2 — Supporting Documents
                ═══════════════════════════════ -->
                <div class="ftlm-panel" id="ftlm-panel-1">

                    <div class="ftlm-sec-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/>
                        </svg>
                        Supporting Documents
                    </div>

                    <p style="font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 10px;">
                        Add each document attached to this request. Type the document name and paste its file path or URL.
                    </p>

                    <table style="width:100%;border-collapse:collapse;" id="ftlm-doc-table">
                        <thead>
                            <tr style="border-bottom:2px solid var(--afims-border, #E4ECF2);">
                                <th style="width:28px;"></th>
                                <th style="text-align:left;padding:5px 10px;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.06em;width:36%;">Document Name</th>
                                <th style="text-align:left;padding:5px 10px;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;letter-spacing:.06em;">File Path / Link</th>
                                <th style="width:28px;"></th>
                            </tr>
                        </thead>
                        <tbody id="ftlm-doc-tbody"></tbody>
                    </table>

                    <button type="button" onclick="ftlm_addDocRow()"
                        style="margin-top:10px;display:inline-flex;align-items:center;gap:5px;font-size:12px;font-weight:600;color:var(--afims-heading, #000E3E);background:none;border:1.5px dashed var(--afims-border, #E4ECF2);border-radius:8px;padding:6px 14px;cursor:pointer;transition:border-color .15s,background .15s;"
                        onmouseover="this.style.borderColor='var(--afims-heading, #000E3E)';this.style.background='var(--afims-surface-subtle, #F4F7FA)';"
                        onmouseout="this.style.borderColor='var(--afims-border, #E4ECF2)';this.style.background='none';">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:12px;height:12px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
                        </svg>
                        Add Document
                    </button>

                    <div class="ftlm-sec-hd" style="margin-top:22px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        Funding Reference
                    </div>

                    <div class="ftlm-fg">
                        <label class="ftlm-label">DV Numbers <span style="font-size:10px;font-weight:400;color:var(--afims-text-disabled, #A9AAB8);">(Search and select one or more)</span></label>
                        <div class="ftlm-msel" id="ftlm-dv-msel">
                            <div class="ftlm-tag-input form-control" id="ftlm-dv-tag-input"
                                 onclick="document.getElementById('ftlm-dv-search').focus()">
                                <input type="text" id="ftlm-dv-search"
                                       placeholder="Search DV number or payee…" autocomplete="off">
                            </div>
                            <div class="ftlm-msel-dropdown" id="ftlm-dv-dropdown" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="ftlm-grid-2">
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_laa_number">LAA Number</label>
                            <select class="form-control" id="ftlm_laa_number">
                                <option value="">— Select LAA —</option>
                            </select>
                        </div>
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_rfa_number">RFA Number</label>
                            <select class="form-control" id="ftlm_rfa_number">
                                <option value="">— Select RFA —</option>
                            </select>
                        </div>
                    </div>
                    <!-- laa_id/rfa_id, CY (Fiscal Year) and RFA Date are derived, not
                         typed: they come from whichever LAA/RFA is picked above
                         (ftlm_onLaaSelect/ftlm_onRfaSelect). The LAA parenthetical
                         department and the signatory's office/region (below) both
                         reuse the existing originating_unit column server-side —
                         no client-side field for either. -->
                    <input type="hidden" id="ftlm_laa_id">
                    <input type="hidden" id="ftlm_rfa_id">
                    <input type="hidden" id="ftlm_laa_fiscal_year">
                    <input type="hidden" id="ftlm_rfa_date">

                </div><!-- /panel-1 -->

                <!-- ═══════════════════════════════
                     Step 3 — Signatories
                     The bank letter's own two signatories (sig1/sig2) — free-text
                     name/title pairs, same pattern as every other form's signatory
                     fields in this app (cba-form.php, check-disbursement-voucher-page.php):
                     decorative on the printed document, not tied to a workflow actor.
                     Saved into the signatories jsonb; FtlaService::_buildPrintPayload()
                     already reads sig1_name/sig1_title/sig2_name/sig2_title from there.
                ═══════════════════════════════ -->
                <div class="ftlm-panel" id="ftlm-panel-2">

                    <div class="ftlm-sec-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/>
                        </svg>
                        Signatories
                    </div>
                    <p style="font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 14px;">
                        Who signs the bank letter. Leave blank to use the default titles below.
                    </p>

                    <div class="ftlm-grid-2">
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_sig1_name">Signatory 1 — Name</label>
                            <input type="text" class="form-control" id="ftlm_sig1_name" placeholder="e.g. MARIA C. SANTOS">
                        </div>
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_sig1_title">Signatory 1 — Title</label>
                            <input type="text" class="form-control" id="ftlm_sig1_title" placeholder="Officer-in-Charge, Treasury Department">
                        </div>
                    </div>
                    <div class="ftlm-grid-2">
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_sig2_name">Signatory 2 — Name</label>
                            <input type="text" class="form-control" id="ftlm_sig2_name" placeholder="e.g. JOSE R. DELA CRUZ">
                        </div>
                        <div class="ftlm-fg">
                            <label class="ftlm-label" for="ftlm_sig2_title">Signatory 2 — Title</label>
                            <input type="text" class="form-control" id="ftlm_sig2_title" placeholder="Acting Group Manager, Financial Services Group">
                        </div>
                    </div>

                </div><!-- /panel-2 -->

                <!-- ═══════════════════════════════
                     Step 4 — Review
                ═══════════════════════════════ -->
                <div class="ftlm-panel" id="ftlm-panel-3">

                    <div class="ftlm-sec-hd">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="12" height="12">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                            <polyline points="14 2 14 8 20 8"/>
                        </svg>
                        Review
                    </div>
                    <p style="font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 14px;">
                        Preview of the FTLA bank letter. Verify all details before submitting.
                    </p>

                    <div id="ftlm-review-doc"></div>

                </div><!-- /panel-3 -->

        </div><!-- /ftlm-form-panel -->

        <div id="ftlm-preview-panel">
            <div id="ftlm-preview-label">Live Preview — FTLA Letter</div>
            <div id="ftlm-preview-wrap">
                <div id="ftlm-preview-inner">
                    <div class="ftlm-preview-placeholder">Select a transfer type to see preview</div>
                </div>
            </div>
        </div><!-- /ftlm-preview-panel -->
        </div><!-- /ftlm-body-split -->

        <!-- Footer -->
        <div id="ftlm-footer">
            <span id="ftlm-step-counter">Step 1 of 2</span>
            <div id="ftlm-footer-right">
                <span id="ftlm-form-msg"></span>
                <button type="button" id="ftlm-btn-back" style="display:none;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);font-size:13px;font-weight:600;font-family:var(--font);cursor:pointer;white-space:nowrap;text-decoration:none;transition:opacity .15s;">
                    ← Back
                </button>
                <button type="button" id="ftlm-btn-next" style="display:inline-flex;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:8px;border:none;background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF);font-size:13px;font-weight:600;font-family:var(--font);cursor:pointer;white-space:nowrap;text-decoration:none;transition:opacity .15s;">
                    Next →
                </button>
                <button type="button" id="ftlm-btn-submit" style="display:none;align-items:center;gap:6px;height:36px;padding:0 14px;border-radius:8px;border:none;background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF);font-size:13px;font-weight:600;font-family:var(--font);cursor:pointer;white-space:nowrap;text-decoration:none;transition:opacity .15s;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    Save &amp; Submit
                </button>
            </div>
        </div>

    </div><!-- /ftlm-card -->
</div><!-- /ftlm-overlay -->

<script>
'use strict';

/* ── State ─────────────────────────────────────────────── */
let ftlm_currentStep = 0;
let ftlm_currentId   = null;
let ftlm_bankLoaded  = false;
let ftlm_banks       = [];   // regional bank accounts (source)
let ftlm_typesLoaded = false;
let ftlm_debitRows   = [];   // "Multiple debit accounts" rows: [{bank_account_id, amount}]
let ftlm_allocLoaded = false;
let ftlm_laaOptions  = [];   // approved/terminal LAA records — Funding Reference picker
let ftlm_rfaOptions  = [];   // approved/terminal RFA records — Funding Reference picker
let ftlm_dvLoaded    = false;
let ftlm_dvOptions   = [];   // approved DV vouchers — DV Number picker
let ftlm_previewTimer = null;
const FTLM_TOTAL_STEPS = 4;
const FTLM_API_BASE    = '<?php echo rtrim($_ftlm_api, '/'); ?>';

/* ── Amount formatting ─────────────────────────────────── */
function ftlm_formatAmount(inp) {
    const cursor = inp.selectionStart;
    const oldLen = inp.value.length;
    let raw = inp.value.replace(/[^0-9.]/g, '');
    const fi = raw.indexOf('.');
    if (fi !== -1) raw = raw.slice(0, fi + 1) + raw.slice(fi + 1).replace(/\./g, '').slice(0, 2);
    const [int, dec] = raw.split('.');
    const formatted = int.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (dec !== undefined ? '.' + dec : '');
    inp.value = formatted;
    const diff = formatted.length - oldLen;
    try { inp.setSelectionRange(cursor + diff, cursor + diff); } catch (_) {}
}

function ftlm_parseAmount(val) {
    return parseFloat(String(val || '').replace(/,/g, '')) || 0;
}

function ftlm_setAmount(val) {
    const el  = ftlm$('ftlm_transfer_amount');
    if (!el) return;
    const num = parseFloat(String(val || '').replace(/,/g, ''));
    el.value  = (num > 0)
        ? num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
        : '';
}

/* ── Helpers ───────────────────────────────────────────── */
function ftlm$(id) { return document.getElementById(id); }

async function ftlm_fetch(endpoint, opts = {}) {
    const token = window.ew?.API_JWT_TOKEN || '';
    const isFormData = opts.body instanceof FormData;
    const res = await fetch(`${FTLM_API_BASE}/${endpoint}`, {
        ...opts,
        headers: {
            'X-Authorization': `Bearer ${token}`,
            ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
            ...(opts.headers || {}),
        },
    });
    const text = await res.text();
    let json = {};
    if (text) { try { json = JSON.parse(text); } catch { throw new Error(`Non-JSON response (HTTP ${res.status})`); } }
    if (!res.ok) throw new Error(json.message || `HTTP ${res.status}`);
    return json;
}

function ftlm_toast(msg, type = 'info') {
    if (typeof nha_toast === 'function') { nha_toast(msg, type); return; }
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            icon: type === 'error' ? 'error' : type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'info',
            text: msg, toast: true, position: 'top-end',
            timer: 3500, showConfirmButton: false, timerProgressBar: true,
        });
    } else {
        console.warn('[FTLM]', type.toUpperCase(), msg);
    }
}

function ftlm_showError(msg) {
    const el = ftlm$('ftlm-error');
    if (el) { el.textContent = msg; el.style.display = ''; }
}

function ftlm_hideError() {
    const el = ftlm$('ftlm-error');
    if (el) el.style.display = 'none';
}

/* ── Preview ───────────────────────────────────────────── */
function ftlm_buildPreviewData() {
    const v      = id => ftlm$(id)?.value || '';
    const bankById = id => ftlm_banks.find(b => b.bank_account_id === parseInt(id)) || {};

    // ftlm_from_bank_account_id is the Destination (to) bank — see ftlm_fillForm()'s note.
    const toBank = bankById(v('ftlm_from_bank_account_id'));

    const multi = !!ftlm$('ftlm_multi_debit')?.checked;
    let fromBank = {};
    let debitAccounts = [];
    let creditAccounts = [];
    if (multi) {
        debitAccounts  = ftlm_getDebitAccounts().map(r => {
            const b = bankById(r.bank_account_id);
            return { account_number: b.account_number || '', account_name: b.account_name || '', amount: r.amount };
        });
        creditAccounts = ftlm_getCreditAccounts().map(r => {
            const b = bankById(r.bank_account_id);
            return { account_number: b.account_number || '', account_name: b.account_name || '' };
        });
    } else {
        fromBank = bankById(v('ftlm_from_source_bank_account_id'));
    }

    return {
        transfer_type:       v('ftlm_transfer_type'),
        transfer_date:       v('ftlm_transfer_date'),
        transfer_amount:     ftlm_parseAmount(v('ftlm_transfer_amount')),
        to_bank_name:        toBank.bank_name      || '',
        to_account_number:   toBank.account_number || '',
        to_account_name:     toBank.account_name   || '',
        from_bank_name:      fromBank.bank_name      || '',
        from_account_number: fromBank.account_number || '',
        from_account_name:   fromBank.account_name   || '',
        debit_accounts:      debitAccounts,
        credit_accounts:     creditAccounts,
        purpose:             v('ftlm_purpose'),
        ftla_number:         v('ftlm_ftla_number'),
        sig_left_name:       v('ftlm_sig1_name'),
        sig_left_title:      v('ftlm_sig1_title'),
        sig_right_name:      v('ftlm_sig2_name'),
        sig_right_title:     v('ftlm_sig2_title'),
    };
}

function ftlm_updatePreview() {
    const wrap  = ftlm$('ftlm-preview-wrap');
    const inner = ftlm$('ftlm-preview-inner');
    if (!wrap || !inner) return;

    const d = ftlm_buildPreviewData();

    if (!d.transfer_type) {
        inner.innerHTML = '<div class="ftlm-preview-placeholder">Select a transfer type to see preview</div>';
        inner.style.cssText = 'transform-origin:top left;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.13)';
        inner.style.opacity = '1';
        wrap.style.height = '';
        return;
    }

    // Delegate document rendering to the FTLAPreparation instance
    const app = window._ftlaApp;
    if (!app) return;
    inner.innerHTML = app._renderPrintSummary(d);
    inner.style.cssText = 'transform-origin:top left;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.13);width:794px;opacity:1;transition:opacity .15s';
    requestAnimationFrame(() => {
        // Never shrink past a readable floor — below that the letter stops being
        // legible, so let it overflow and scroll horizontally instead (wrap has
        // overflow-x:auto) rather than keep scaling down to fit.
        const fitScale = wrap.offsetWidth / 794;
        const scale = Math.max(0.72, Math.min(1, fitScale));
        inner.style.transform = `scale(${scale})`;
        wrap.style.height = Math.ceil(inner.scrollHeight * scale) + 'px';
    });
}

function ftlm_schedulePreviewUpdate() {
    const inner = ftlm$('ftlm-preview-inner');
    if (inner) inner.style.opacity = '0.5';
    clearTimeout(ftlm_previewTimer);
    ftlm_previewTimer = setTimeout(ftlm_updatePreview, 220);
}

/* ── Paper-document Review (Step 5) ───────────────────────
   Ported directly from scripts/gen_ftla_docx.js so the on-screen
   preview matches the generated memo's wording/order exactly. */
function ftlm_fmtPeso(n) {
    const v = parseFloat(n) || 0;
    return v.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function ftlmEsc(s) {
    return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

// Uppercase, hyphenated amount-in-words, e.g. 235500 -> "TWO HUNDRED
// THIRTY-FIVE THOUSAND FIVE HUNDRED PESOS" — matches NHA memo convention.
function ftlm_numberToWordsUpper(num) {
    const ones  = ['','ONE','TWO','THREE','FOUR','FIVE','SIX','SEVEN','EIGHT','NINE',
                   'TEN','ELEVEN','TWELVE','THIRTEEN','FOURTEEN','FIFTEEN','SIXTEEN',
                   'SEVENTEEN','EIGHTEEN','NINETEEN'];
    const tens  = ['','','TWENTY','THIRTY','FORTY','FIFTY','SIXTY','SEVENTY','EIGHTY','NINETY'];
    const tiers = ['','THOUSAND','MILLION','BILLION'];
    if (num === 0) return 'ZERO PESOS';
    function b1000(n) {
        if (!n) return '';
        if (n < 20)  return ones[n];
        if (n < 100) return tens[Math.floor(n/10)] + (n%10 ? '-'+ones[n%10] : '');
        return ones[Math.floor(n/100)]+' HUNDRED'+(n%100 ? ' '+b1000(n%100) : '');
    }
    const intPart   = Math.floor(Math.abs(num));
    const centsPart = Math.round((Math.abs(num) - intPart) * 100);
    const parts = [];
    let n = intPart, tier = 0;
    if (!n) parts.push('ZERO');
    while (n > 0) {
        const chunk = n % 1000;
        if (chunk) parts.unshift(b1000(chunk) + (tiers[tier] ? ' '+tiers[tier] : ''));
        n = Math.floor(n / 1000); tier++;
    }
    let words = parts.join(' ');
    if (centsPart > 0) words += ' AND ' + centsPart + '/100';
    return words + ' PESOS';
}

/** Full-width bank-letter preview for the Review step — same renderer as the side panel. */
function ftlm_populateReviewDoc() {
    const el = ftlm$('ftlm-review-doc');
    const app = window._ftlaApp;
    if (!el || !app) return;
    el.innerHTML = app._renderPrintSummary(ftlm_buildPreviewData());
}

/* ── Stepper ───────────────────────────────────────────── */
function ftlm_goStep(n) {
    for (let i = 0; i < FTLM_TOTAL_STEPS; i++) {
        const panel  = ftlm$(`ftlm-panel-${i}`);
        const circle = ftlm$(`ftlm-circle-${i}`);
        const step   = ftlm$(`ftlm-step-${i}`);
        if (panel) panel.classList.toggle('ftlm-active', i === n);
        if (!step) continue;
        step.classList.remove('active', 'done');
        if (i < n) {
            step.classList.add('done');
            if (circle) circle.innerHTML = '✓';
        } else if (i === n) {
            step.classList.add('active');
            if (circle) circle.textContent = i + 1;
        } else {
            if (circle) circle.textContent = i + 1;
        }
    }

    const btnBack   = ftlm$('ftlm-btn-back');
    const btnNext   = ftlm$('ftlm-btn-next');
    const btnSubmit = ftlm$('ftlm-btn-submit');
    if (btnBack)   btnBack.style.display   = n > 0 ? '' : 'none';
    if (btnNext)   btnNext.style.display   = n < FTLM_TOTAL_STEPS - 1 ? '' : 'none';
    if (btnSubmit) btnSubmit.style.display = n === FTLM_TOTAL_STEPS - 1 ? '' : 'none';

    const counter = ftlm$('ftlm-step-counter');
    if (counter) counter.textContent = `Step ${n + 1} of ${FTLM_TOTAL_STEPS}`;

    const panel = ftlm$('ftlm-form-panel');
    if (panel) panel.scrollTop = 0;

    // Side-by-side bank-letter preview stays up throughout the wizard — except the
    // final Review step, which already gets its own full-width memo preview instead.
    const isReviewStep = n === FTLM_TOTAL_STEPS - 1;
    const previewPanel = ftlm$('ftlm-preview-panel');
    const formPanel    = ftlm$('ftlm-form-panel');
    if (previewPanel) previewPanel.style.display = isReviewStep ? 'none' : '';
    if (formPanel) {
        formPanel.style.flex        = isReviewStep ? '1' : '';
        formPanel.style.borderRight = isReviewStep ? 'none' : '';
    }
    if (!isReviewStep) ftlm_schedulePreviewUpdate();

    // Paper-document Review step — populate the letter preview
    if (n === FTLM_TOTAL_STEPS - 1) ftlm_populateReviewDoc();
    // Wide mode gives the side-by-side preview panel (steps 0-2) and the Review
    // step's full-width memo both more room — keep it on throughout, not just Review.
    ftlm$('ftlm-card')?.classList.add('ftlm-wide');

    ftlm_currentStep = n;
}

function ftlm_validateStep(n) {
    if (n === 0) {
        const missing = [];
        const mark = (gId, fId, label, test) => {
            const g = ftlm$(gId), f = ftlm$(fId);
            if (!g || !f) return;
            const bad = test ? !test(f) : !f.value.trim();
            g.classList.toggle('has-err', bad);
            if (bad) missing.push(label);
        };
        mark('ftlm-fg-type',    'ftlm_transfer_type',      'Transfer Type');
        mark('ftlm-fg-date',    'ftlm_transfer_date',      'Transfer Date');
        mark('ftlm-fg-amount',  'ftlm_transfer_amount',    'Transfer Amount', f => ftlm_parseAmount(f.value) > 0);
        mark('ftlm-fg-purpose', 'ftlm_purpose',            'Purpose / Particulars');
        if (missing.length) {
            ftlm_showError('Please fill in the required field' + (missing.length > 1 ? 's' : '') + ':\n' +
                missing.map(m => '• ' + m).join('\n'));
            return false;
        }
    }
    ftlm_hideError();
    return true;
}

/* ── Real-time field error clearing ─────────────────────── */
(function ftlm_bindLiveValidation() {
    const pairs = [
        ['ftlm_transfer_type',      'ftlm-fg-type'],
        ['ftlm_transfer_date',      'ftlm-fg-date'],
        ['ftlm_transfer_amount',    'ftlm-fg-amount'],
        ['ftlm_purpose',            'ftlm-fg-purpose'],
    ];
    pairs.forEach(([fId, gId]) => {
        const f = ftlm$(fId), g = ftlm$(gId);
        if (!f || !g) return;
        const clear = () => {
            const filled = fId === 'ftlm_transfer_amount'
                ? ftlm_parseAmount(f.value) > 0
                : f.value.trim() !== '';
            if (filled) {
                g.classList.remove('has-err');
                if (!document.querySelector('#ftlm-card .ftlm-fg.has-err')) ftlm_hideError();
            }
        };
        f.addEventListener('input',  clear);
        f.addEventListener('change', clear);
    });
})();

function ftlm_nextStep() {
    if (!ftlm_validateStep(ftlm_currentStep)) return;
    if (ftlm_currentStep < FTLM_TOTAL_STEPS - 1) ftlm_goStep(ftlm_currentStep + 1);
}

function ftlm_prevStep() {
    if (ftlm_currentStep > 0) ftlm_goStep(ftlm_currentStep - 1);
}

/* ── Bank accounts ─────────────────────────────────────── */
function ftlm_bankOptsHtml(banks) {
    return '<option value="">— Select —</option>' +
        banks.map(b => `<option value="${b.bank_account_id}">${b.bank_name} — ${b.account_name} (${b.account_number})</option>`).join('');
}

async function ftlm_loadBankAccounts() {
    const fromSel   = ftlm$('ftlm_from_bank_account_id');
    const sourceSel = ftlm$('ftlm_from_source_bank_account_id');
    if (fromSel)   { fromSel.disabled   = true; fromSel.innerHTML   = '<option value="">Loading…</option>'; }
    if (sourceSel) { sourceSel.disabled = true; sourceSel.innerHTML = '<option value="">Loading…</option>'; }
    try {
        const resFrom = await ftlm_fetch('ftla/bank-accounts');
        ftlm_banks = resFrom.data || [];
        const opts = ftlm_bankOptsHtml(ftlm_banks);
        if (fromSel)   { fromSel.innerHTML   = opts; fromSel.disabled   = false; }
        if (sourceSel) { sourceSel.innerHTML = opts; sourceSel.disabled = false; }
        ftlm_updateFundSourceDisplay();
        ftlm_renderDebitRows();   // re-render any rows added before banks finished loading
    } catch (e) {
        const failMsg = '<option value="">Failed to load</option>';
        if (fromSel)   { fromSel.innerHTML   = failMsg; fromSel.disabled   = false; }
        if (sourceSel) { sourceSel.innerHTML = failMsg; sourceSel.disabled = false; }
        console.error('[FTLM] bank accounts:', e.message);
    }
}

async function ftlm_loadTransferTypes() {
    const sel = ftlm$('ftlm_transfer_type');
    try {
        const res = await ftlm_fetch('ftla/transfer-types');
        const types = res.data || [];
        if (sel) {
            sel.innerHTML = '<option value="">— Select —</option>' +
                types.map(t => `<option value="${t.value}">${ftlmEsc(t.label)}</option>`).join('');
        }
    } catch (e) {
        if (sel) sel.innerHTML = '<option value="">Failed to load</option>';
        console.error('[FTLM] transfer types:', e.message);
    }
}

function ftlm_updateFundSourceDisplay() {
    const out = ftlm$('ftlm_from_fund_source_name');
    if (!out) return;
    const fromId = parseInt(ftlm$('ftlm_from_bank_account_id')?.value) || null;
    const bank   = ftlm_banks.find(b => b.bank_account_id == fromId);
    out.value = bank?.fund_source_name || '';
}

/* ── "Multiple debit accounts" (Treasury's ftla-preparation.php checkbox + multi-debit panel) ── */

function ftlm_toggleMultiDebit() {
    const on = !!ftlm$('ftlm_multi_debit')?.checked;
    ftlm$('ftlm-destination-wrap').style.display  = on ? 'none' : '';
    ftlm$('ftlm-single-debit-wrap').style.display = on ? 'none' : '';
    ftlm$('ftlm-multi-debit-wrap').style.display  = on ? '' : 'none';
    const amt = ftlm$('ftlm_transfer_amount');
    if (amt) {
        amt.readOnly = on;
        amt.style.background = on ? 'var(--afims-border, #E4ECF2)' : '';
    }
    if (on && !ftlm_debitRows.length) ftlm_addDebitRow();
    ftlm_recalcDebitTotal();
    ftlm_schedulePreviewUpdate();
}

/** New rows default their Credit (To) to the current Destination Bank — stays a plain "many debits, one credit" letter until a row's To is changed. */
function ftlm_defaultCreditAccountId() {
    return ftlm$('ftlm_from_bank_account_id')?.value || '';
}

function ftlm_addDebitRow(bankAccountId = '', amount = '', toBankAccountId = '') {
    ftlm_debitRows.push({ bank_account_id: bankAccountId, to_bank_account_id: toBankAccountId || ftlm_defaultCreditAccountId(), amount });
    ftlm_renderDebitRows();
}

function ftlm_removeDebitRow(idx) {
    ftlm_debitRows.splice(idx, 1);
    ftlm_renderDebitRows();
}

function ftlm_onDebitRowChange(idx, field, value) {
    if (!ftlm_debitRows[idx]) return;
    ftlm_debitRows[idx][field] = value;
    ftlm_recalcDebitTotal();
    ftlm_schedulePreviewUpdate();
}

function ftlm_renderDebitRows() {
    const tbody = ftlm$('ftlm-debit-tbody');
    if (!tbody) return;
    tbody.innerHTML = ftlm_debitRows.map((row, idx) => `
        <tr>
            <td style="padding:4px 10px;">
                <select class="form-control" onchange="ftlm_onDebitRowChange(${idx}, 'bank_account_id', this.value)">
                    ${ftlm_bankOptsHtml(ftlm_banks).replace(`value="${row.bank_account_id}"`, `value="${row.bank_account_id}" selected`)}
                </select>
            </td>
            <td style="padding:4px 10px;">
                <select class="form-control" onchange="ftlm_onDebitRowChange(${idx}, 'to_bank_account_id', this.value)">
                    ${ftlm_bankOptsHtml(ftlm_banks).replace(`value="${row.to_bank_account_id}"`, `value="${row.to_bank_account_id}" selected`)}
                </select>
            </td>
            <td style="padding:4px 10px;">
                <input type="text" class="form-control" inputmode="decimal" placeholder="0.00"
                       value="${row.amount || ''}" style="text-align:right;"
                       oninput="ftlm_formatAmount(this); ftlm_onDebitRowChange(${idx}, 'amount', ftlm_parseAmount(this.value))">
            </td>
            <td style="padding:4px 6px;text-align:center;">
                <button type="button" onclick="ftlm_removeDebitRow(${idx})"
                        style="background:none;border:none;cursor:pointer;color:var(--afims-danger, #DC3545);font-size:16px;line-height:1;">&times;</button>
            </td>
        </tr>
    `).join('');
    ftlm_recalcDebitTotal();
}

function ftlm_recalcDebitTotal() {
    const total = ftlm_debitRows.reduce((s, r) => s + (ftlm_parseAmount(String(r.amount || 0)) || 0), 0);
    const totalEl = ftlm$('ftlm-debit-total');
    if (totalEl) totalEl.textContent = '₱' + ftlm_fmtPeso(total);
    if (ftlm$('ftlm_multi_debit')?.checked) ftlm_setAmount(total);
}

/** [{bank_account_id, amount}] for the submit payload — [] when not in multi-debit mode. */
function ftlm_getDebitAccounts() {
    if (!ftlm$('ftlm_multi_debit')?.checked) return [];

    return ftlm_debitRows
        .filter(r => r.bank_account_id && ftlm_parseAmount(String(r.amount || 0)) > 0)
        .map(r => ({ bank_account_id: parseInt(r.bank_account_id), amount: ftlm_parseAmount(String(r.amount)) }));
}

/**
 * [{bank_account_id}] index-paired with ftlm_getDebitAccounts() — Treasury's
 * ftlafundtransfer.php "pairs" shape. A row left at the default Destination Bank
 * still sends its id here; the backend only treats this as "paired" once more
 * than one distinct credit account shows up (see FtlaService::update()/create()),
 * so an untouched table still prints as the familiar "many debits, one credit" letter.
 */
function ftlm_getCreditAccounts() {
    if (!ftlm$('ftlm_multi_debit')?.checked) return [];

    return ftlm_debitRows
        .filter(r => r.bank_account_id && ftlm_parseAmount(String(r.amount || 0)) > 0)
        .map(r => ({ bank_account_id: parseInt(r.to_bank_account_id || ftlm_defaultCreditAccountId()) }));
}

function ftlm_setDebitAccounts(rows) {
    ftlm_debitRows = (rows || []).map((r, idx) => ({
        bank_account_id:    r.bank_account_id,
        to_bank_account_id: r.to_bank_account_id || ftlm_defaultCreditAccountId(),
        amount:             r.amount,
    }));
    const multi = ftlm_debitRows.length > 1;
    const cb = ftlm$('ftlm_multi_debit');
    if (cb) cb.checked = multi;
    ftlm$('ftlm-destination-wrap').style.display  = multi ? 'none' : '';
    ftlm$('ftlm-single-debit-wrap').style.display = multi ? 'none' : '';
    ftlm$('ftlm-multi-debit-wrap').style.display  = multi ? '' : 'none';
    const amt = ftlm$('ftlm_transfer_amount');
    if (amt) { amt.readOnly = multi; amt.style.background = multi ? 'var(--afims-border, #E4ECF2)' : ''; }
    if (!multi && ftlm_debitRows.length === 1) {
        const sel = ftlm$('ftlm_from_source_bank_account_id');
        if (sel) sel.value = ftlm_debitRows[0].bank_account_id;
        ftlm_debitRows = [];
    }
    ftlm_renderDebitRows();
}

/* ── LAA / RFA pickers ─────────────────────────────────────
   Reuses the same /budget_allocation/variance/list endpoint (and terminal
   status filters) already used by ba-form.php's Parent LAA combobox and
   dv-form.php's RFA reference picker — see those files for precedent. */
function ftlm_allocOptsHtml(placeholder, rows) {
    return `<option value="">${placeholder}</option>` + rows.map(r => {
        const num = r.allocation_number || '';
        const fy  = r.fiscal_year_label ? ` — FY ${r.fiscal_year_label}` : '';
        return `<option value="${num}">${num}${fy}</option>`;
    }).join('');
}

async function ftlm_loadAllocationOptions() {
    const laaSel = ftlm$('ftlm_laa_number');
    const rfaSel = ftlm$('ftlm_rfa_number');
    try {
        const [laaRes, rfaRes] = await Promise.all([
            ftlm_fetch('budget_allocation/variance/list?type=LAA&status=PROCESSED&limit=500'),
            ftlm_fetch('budget_allocation/variance/list?type=RFA&status=APPROVED,PROCESSED&limit=500'),
        ]);
        ftlm_laaOptions = laaRes.data || [];
        ftlm_rfaOptions = rfaRes.data || [];
        if (laaSel) laaSel.innerHTML = ftlm_allocOptsHtml('— Select LAA —', ftlm_laaOptions);
        if (rfaSel) rfaSel.innerHTML = ftlm_allocOptsHtml('— Select RFA —', ftlm_rfaOptions);
    } catch (e) {
        console.error('[FTLM] allocation options:', e.message);
    }
}

// Keeps the select showing the record's actual value even if it wasn't in
// the loaded (still-terminal-status) list — e.g. an older or re-opened record.
function ftlm_ensureSelectOption(selectId, value) {
    const el = ftlm$(selectId);
    if (!el || !value) return;
    if (!Array.from(el.options).some(o => o.value === value)) {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = value;
        el.appendChild(opt);
    }
    el.value = value;
}

function ftlm_onLaaSelect() {
    const val = ftlm$('ftlm_laa_number')?.value;
    const opt = ftlm_laaOptions.find(o => o.allocation_number === val);
    const idEl = ftlm$('ftlm_laa_id');
    if (idEl) idEl.value = opt?.allocation_id || '';
    if (opt) {
        const fyEl = ftlm$('ftlm_laa_fiscal_year');
        if (fyEl && !fyEl.value && opt.fiscal_year_label) fyEl.value = opt.fiscal_year_label;
    }
    ftlm_schedulePreviewUpdate();
}

function ftlm_onRfaSelect() {
    const val = ftlm$('ftlm_rfa_number')?.value;
    const opt = ftlm_rfaOptions.find(o => o.allocation_number === val);
    const idEl = ftlm$('ftlm_rfa_id');
    if (idEl) idEl.value = opt?.allocation_id || '';
    const dateEl = ftlm$('ftlm_rfa_date');
    const dated = opt?.finalized_at || opt?.created_at;
    if (dateEl && !dateEl.value && dated) dateEl.value = String(dated).slice(0, 10);
    ftlm_schedulePreviewUpdate();
}

/* ── DV Numbers (multiple) ─────────────────────────────────
   Reuses /voucher/list (the same route the Voucher pages already use)
   filtered to approved DVs — same reuse-what-exists approach as the
   LAA/RFA pickers above. DV numbers are data-only: saved for recordkeeping,
   never referenced in the generated memo/PDF/DOCX (same treatment as Bank
   Officers/Request Reference/Remarks). Searchable multi-select tag picker —
   mirrors lv-form.php's CIBR picker (lvpCibrSelect/lvpRenderCadvTags),
   filtered client-side against the preloaded ftlm_dvOptions list instead of
   a per-keystroke server search, since it's already fetched in full
   (limit=500) before the picker can be used (see ftlm_open()). */
async function ftlm_loadDvOptions() {
    try {
        // status=RELEASED — fims.disbursement_vouchers' terminal "money actually went out"
        // status (see DisbursementVoucherService's summary query); no voucher_type filter
        // exists on this route (fims-nha doesn't split DV/other types the way REGIONAL did).
        const res  = await ftlm_fetch('voucher/list?status=RELEASED&limit=500');
        const rows = Array.isArray(res.data) ? res.data : (res.data?.rows || []);
        ftlm_dvOptions = rows;
    } catch (e) {
        console.error('[FTLM] DV options:', e.message);
    }
}

let ftlm_dvSelected = []; // array of dv_no strings, in selection order

// Escapes a value for safe use inside a single-quoted JS string literal
// embedded in a double-quoted HTML attribute (onclick="fn('...')") — unlike
// lv-form.php's CIBR picker, DV numbers are strings, not numeric ids.
function ftlm_jsAttr(s) {
    return String(s ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function ftlm_dvSearchText(r) {
    return `${r.dv_no || ''} ${r.claimant_name || ''}`.toLowerCase();
}

function ftlm_renderDvDropdown(filterText = '') {
    const dd = ftlm$('ftlm-dv-dropdown');
    if (!dd) return;
    const q    = filterText.trim().toLowerCase();
    const rows = q ? ftlm_dvOptions.filter(r => ftlm_dvSearchText(r).includes(q)) : ftlm_dvOptions;
    if (!rows.length) {
        dd.innerHTML = '<div class="ftlm-msel-option-empty">No DVs found.</div>';
        return;
    }
    dd.innerHTML = rows.map(r => {
        const num = r.dv_no || '';
        const sel = ftlm_dvSelected.includes(num);
        const amt = r.net_amount ? ` — ₱${ftlm_fmtPeso(r.net_amount)}` : '';
        const who = r.claimant_name ? ` (${ftlmEsc(r.claimant_name)})` : '';
        return `<div class="ftlm-msel-option ${sel ? 'selected' : ''}" onclick="ftlm_toggleDv('${ftlm_jsAttr(num)}')">
            <span class="ftlm-msel-option-chk">${sel ? '✓' : ''}</span>
            <span><span class="nha-mono">${ftlmEsc(num)}</span>${who}${amt}</span>
        </div>`;
    }).join('');
}

function ftlm_renderDvTags() {
    const container = ftlm$('ftlm-dv-tag-input');
    const input     = ftlm$('ftlm-dv-search');
    if (!container || !input) return;
    Array.from(container.children).forEach(el => { if (el !== input) el.remove(); });
    ftlm_dvSelected.forEach(num => {
        const tag = document.createElement('span');
        tag.className = 'ftlm-msel-tag';
        tag.innerHTML = `${ftlmEsc(num)}<button type="button" onclick="ftlm_removeDv('${ftlm_jsAttr(num)}')">&times;</button>`;
        container.insertBefore(tag, input);
    });
    input.placeholder = ftlm_dvSelected.length ? 'Search for more…' : 'Search DV number or payee…';
}

window.ftlm_toggleDv = function(num) {
    const idx = ftlm_dvSelected.indexOf(num);
    if (idx !== -1) ftlm_dvSelected.splice(idx, 1);
    else ftlm_dvSelected.push(num);
    ftlm_renderDvDropdown(ftlm$('ftlm-dv-search')?.value || '');
    ftlm_renderDvTags();
};

window.ftlm_removeDv = function(num) {
    ftlm_dvSelected = ftlm_dvSelected.filter(n => n !== num);
    ftlm_renderDvDropdown(ftlm$('ftlm-dv-search')?.value || '');
    ftlm_renderDvTags();
};

function ftlm_setDvSelected(numbers) {
    ftlm_dvSelected = (numbers || []).map(v => String(v).trim()).filter(Boolean);
    ftlm_renderDvTags();
    ftlm_renderDvDropdown('');
}

function ftlm_getDvNumbers() {
    return ftlm_dvSelected.join('\n');
}

// Search-as-you-type wiring for the DV picker — fixed-position dropdown
// repositioned on open so it's never clipped by #ftlm-form-panel's own
// overflow-y:auto ancestor (same approach as lv-form.php's lvpCadvWire()).
function ftlm_wireDvPicker() {
    const input = ftlm$('ftlm-dv-search');
    const dd    = ftlm$('ftlm-dv-dropdown');
    const wrap  = ftlm$('ftlm-dv-tag-input');
    if (!input || !dd) return;

    const positionDd = () => {
        const r = (wrap || input).getBoundingClientRect();
        dd.style.top   = (r.bottom + 2) + 'px';
        dd.style.left  = r.left + 'px';
        dd.style.width = r.width + 'px';
    };

    const isInside = (e) => {
        const path = typeof e.composedPath === 'function' ? e.composedPath() : [];
        return path.some(el => el === wrap || el === dd ||
            (el.id && ['ftlm-dv-search', 'ftlm-dv-dropdown', 'ftlm-dv-tag-input', 'ftlm-dv-msel'].includes(el.id)));
    };

    const showDd = () => {
        positionDd();
        dd.style.display = 'block';
        ftlm_renderDvDropdown(input.value);
    };

    input.addEventListener('mousedown', showDd);
    input.addEventListener('focus',     showDd);
    input.addEventListener('input',     () => { ftlm_renderDvDropdown(input.value); positionDd(); dd.style.display = 'block'; });

    document.addEventListener('mousedown', (e) => {
        if (!isInside(e)) dd.style.display = 'none';
    });
}

/* ── Supporting documents table ────────────────────────── */
const FTLM_ICON_CHECK = `<svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#22c55e" style="width:14px;height:14px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>`;
const FTLM_ICON_EMPTY = `<svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="#d1d5db" style="width:14px;height:14px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>`;

function ftlm_addDocRow(docType = '', link = '') {
    const tbody = ftlm$('ftlm-doc-tbody');
    if (!tbody) return;
    const tr = document.createElement('tr');
    tr.style.borderBottom = '1px solid var(--afims-surface-subtle, #F4F7FA)';
    tr.innerHTML = `
        <td style="width:28px;text-align:center;padding:7px 4px;" class="ftlm-doc-status">
            ${link ? FTLM_ICON_CHECK : FTLM_ICON_EMPTY}
        </td>
        <td style="padding:5px 6px;width:36%;">
            <input type="text" class="form-control ftlm-doc-type"
                   value="${String(docType).replace(/"/g,'&quot;')}"
                   placeholder="e.g. Purchase Order"
                   style="font-size:12.5px;padding:6px 9px;width:100%;box-sizing:border-box;">
        </td>
        <td style="padding:5px 6px;">
            <div style="display:flex;align-items:center;gap:6px;">
                <input type="text" class="form-control ftlm-doc-link"
                       value="${String(link).replace(/"/g,'&quot;')}"
                       placeholder="Paste file path or URL…"
                       style="flex:1;font-size:12px;padding:6px 9px;box-sizing:border-box;"
                       oninput="ftlm_onDocLinkInput(this)">
                ${link ? `<a href="${String(link).replace(/"/g,'&quot;')}" target="_blank" rel="noopener"
                    style="font-size:12px;color:var(--afims-heading, #000E3E);text-decoration:none;white-space:nowrap;flex-shrink:0;font-weight:600;">Open ↗</a>` : ''}
            </div>
        </td>
        <td style="width:28px;text-align:center;padding:5px 4px;">
            <button type="button" onclick="this.closest('tr').remove()"
                    title="Remove row"
                    style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);padding:3px;display:flex;align-items:center;border-radius:4px;transition:color .15s;"
                    onmouseover="this.style.color='var(--afims-danger, #DC3545)';" onmouseout="this.style.color='var(--afims-text-disabled, #A9AAB8)';">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:13px;height:13px;">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </td>`;
    tbody.appendChild(tr);
}

function ftlm_buildDocTable() {
    const tbody = ftlm$('ftlm-doc-tbody');
    if (!tbody) return;
    tbody.innerHTML = '';
    ftlm_addDocRow();
}

function ftlm_onDocLinkInput(input) {
    const row  = input.closest('tr');
    const link = input.value.trim();
    const statusCell = row?.querySelector('.ftlm-doc-status');
    if (statusCell) statusCell.innerHTML = link ? FTLM_ICON_CHECK : FTLM_ICON_EMPTY;
    const wrap = input.parentNode;
    let anchor = wrap.querySelector('a');
    if (link) {
        if (!anchor) {
            anchor = document.createElement('a');
            anchor.target = '_blank'; anchor.rel = 'noopener';
            anchor.style.cssText = 'font-size:12px;color:#0f2045;text-decoration:none;white-space:nowrap;flex-shrink:0;font-weight:600;';
            anchor.textContent = 'Open ↗';
            wrap.appendChild(anchor);
        }
        anchor.href = link;
    } else if (anchor) {
        anchor.remove();
    }
}

function ftlm_getDocLinks() {
    const now = new Date();
    const pad = n => String(n).padStart(2, '0');
    const uploaded_at = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    return Array.from(document.querySelectorAll('#ftlm-doc-tbody tr')).reduce((acc, row) => {
        const docType = row.querySelector('.ftlm-doc-type')?.value.trim();
        const url     = row.querySelector('.ftlm-doc-link')?.value.trim();
        if (docType || url) acc.push({ url: url || '', name: docType || '', uploaded_at, document_type: docType || '' });
        return acc;
    }, []);
}

/* ── Reset form ────────────────────────────────────────── */
function ftlm_resetForm() {
    [
        'ftlm_transfer_type', 'ftlm_transfer_date', 'ftlm_ftla_number',
        'ftlm_from_bank_account_id', 'ftlm_from_source_bank_account_id', 'ftlm_from_fund_source_name', 'ftlm_transfer_amount',
        'ftlm_repayment_due_date', 'ftlm_purpose',
        'ftlm_laa_number', 'ftlm_laa_id', 'ftlm_laa_fiscal_year',
        'ftlm_rfa_number', 'ftlm_rfa_id', 'ftlm_rfa_date',
        'ftlm_sig1_name', 'ftlm_sig1_title', 'ftlm_sig2_name', 'ftlm_sig2_title',
    ].forEach(id => { const el = ftlm$(id); if (el) el.value = ''; });
    ftlm_setDvSelected([]);
    ftlm_setDebitAccounts([]);

    const isTmp = ftlm$('ftlm_is_temporary');
    if (isTmp) isTmp.checked = false;

    const rf = ftlm$('ftlm-repayment-field');
    if (rf) rf.style.display = 'none';

    ftlm$('ftlm_fund_transfer_id').value = '';
    ftlm$('ftlm-form-msg').textContent   = '';
    ftlm_hideError();
    ftlm_buildDocTable();

    // Reset preview
    const inner = ftlm$('ftlm-preview-inner');
    if (inner) {
        inner.innerHTML = '<div class="ftlm-preview-placeholder">Select a transfer type to see preview</div>';
        inner.style.cssText = 'transform-origin:top left;background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.13)';
    }
    const wrap = ftlm$('ftlm-preview-wrap');
    if (wrap) wrap.style.height = '';
}

/* ── Fill form for edit ────────────────────────────────── */
function ftlm_fillForm(d) {
    const set = (id, val) => {
        const el = ftlm$(id);
        if (!el || val === null || val === undefined) return;
        el.type === 'checkbox' ? (el.checked = !!val) : (el.value = val ?? '');
    };
    set('ftlm_fund_transfer_id',    d.fund_transfer_id);
    set('ftlm_transfer_type',       d.transfer_type);
    set('ftlm_transfer_date',       d.transfer_date?.slice(0, 10));
    set('ftlm_ftla_number',           d.ftla_number);
    // The picked bank account is the request's destination (the region's own
    // account the funds should land in), so it's stored as to_bank_account_id
    // — Treasury fills in from_bank_account_id later when actually processing
    // the transfer out of the correct HQ fund account.
    set('ftlm_from_bank_account_id', d.to_bank_account_id);
    ftlm_updateFundSourceDisplay();
    ftlm_setAmount(d.transfer_amount);
    ftlm_setDebitAccounts((d.from_accounts || []).map((r, idx) => ({
        ...r,
        to_bank_account_id: (d.to_accounts || [])[idx]?.bank_account_id || '',
    })));
    set('ftlm_is_temporary',        d.is_temporary);
    set('ftlm_repayment_due_date',  d.repayment_due_date?.slice(0, 10));
    set('ftlm_purpose',             d.purpose);

    ftlm_setDvSelected((d.dv_numbers || '').split('\n'));

    const rf = ftlm$('ftlm-repayment-field');
    if (rf) rf.style.display = d.is_temporary ? '' : 'none';

    // Populate supporting documents table
    const tbody = ftlm$('ftlm-doc-tbody');
    if (tbody) {
        tbody.innerHTML = '';
        const raw = d.supporting_documents;
        const docs = Array.isArray(raw) ? raw : (typeof raw === 'string' ? (() => { try { return JSON.parse(raw); } catch(e) { return []; } })() : []);
        if (docs.length) {
            docs.forEach(doc => ftlm_addDocRow(doc.document_type || doc.name || '', doc.url || ''));
        } else {
            ftlm_addDocRow();
        }
    }

    // Funding reference (DV/LAA/RFA) still lives on this form — used by the bank
    // letter's DV footer and the Create-JEV prefill. laa_number/rfa_number/fiscal_year/
    // rfa_date ride in the generic signatories jsonb blob; laa_id/rfa_id are real columns.
    const sigsRaw = d.signatories || {};
    const sigs = typeof sigsRaw === 'string' ? (() => { try { return JSON.parse(sigsRaw); } catch(e) { return {}; } })() : sigsRaw;
    const setSig = (id, val) => { const el = ftlm$(id); if (el) el.value = val || ''; };
    setSig('ftlm_laa_id',            d.laa_id                || '');
    setSig('ftlm_rfa_id',            d.rfa_id                || '');
    ftlm_ensureSelectOption('ftlm_laa_number', sigs.laa_number || '');
    setSig('ftlm_laa_fiscal_year',   sigs.fiscal_year        || '');
    ftlm_ensureSelectOption('ftlm_rfa_number', sigs.rfa_number || '');
    setSig('ftlm_rfa_date',          sigs.rfa_date           || '');
    setSig('ftlm_sig1_name',         sigs.sig1_name          || '');
    setSig('ftlm_sig1_title',        sigs.sig1_title         || '');
    setSig('ftlm_sig2_name',         sigs.sig2_name          || '');
    setSig('ftlm_sig2_title',        sigs.sig2_title         || '');

    ftlm_schedulePreviewUpdate();
}

/* ── Open / Close ──────────────────────────────────────── */
async function ftlm_open(id) {
    id = id || null;
    ftlm_currentId = id;
    ftlm_resetForm();
    ftlm_goStep(0);

    // Show modal immediately so it feels responsive while banks load
    ftlm$('ftlm-overlay').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    document.body.style.paddingRight = (window.innerWidth - document.documentElement.clientWidth) + 'px';

    if (!ftlm_typesLoaded) {
        ftlm_typesLoaded = true;
        await ftlm_loadTransferTypes();
    }
    if (!ftlm_bankLoaded) {
        ftlm_bankLoaded = true;
        await ftlm_loadBankAccounts();  // await so selects have options before fillForm runs
    }
    if (!ftlm_allocLoaded) {
        ftlm_allocLoaded = true;
        await ftlm_loadAllocationOptions();  // same reasoning — LAA/RFA selects before fillForm
    }
    if (!ftlm_dvLoaded) {
        ftlm_dvLoaded = true;
        await ftlm_loadDvOptions();  // same reasoning — DV select before fillForm
    }

    if (id) {
        ftlm$('ftlm-title').textContent = 'Edit FTLA';
        try {
            const res = await ftlm_fetch(`ftla/${id}`);
            if (res.success && res.data) ftlm_fillForm(res.data);
            else throw new Error(res.message || 'No data returned.');
        } catch (e) {
            ftlm_toast('Failed to load record: ' + e.message, 'error');
        }
    } else {
        ftlm$('ftlm-title').textContent   = 'New FTLA';
        ftlm$('ftlm_transfer_type').value = 'REGIONAL_FUND_TRANSFER';
        ftlm$('ftlm_transfer_date').value = new Date().toISOString().slice(0, 10);
        ftlm_setDebitAccounts([]);
        ftlm_updatePreview();
    }
}

function ftlm_close() {
    ftlm$('ftlm-overlay').style.display = 'none';
    document.body.style.overflow        = '';
    document.body.style.paddingRight    = '';
}

/* ── Save ──────────────────────────────────────────────── */
async function ftlm_save(status) {
    if (!ftlm_validateStep(0)) {
        ftlm_goStep(0);
        return;
    }

    const msg = ftlm$('ftlm-form-msg');
    if (msg) msg.textContent = 'Saving…';

    const body = {
        transfer_type:      ftlm$('ftlm_transfer_type')?.value          || '',
        section:            'regional',
        transfer_date:      ftlm$('ftlm_transfer_date')?.value          || '',
        // Saved as to_bank_account_id, not from — see ftlm_fillForm()'s note.
        to_bank_account_id: ftlm$('ftlm_from_bank_account_id')?.value   || '',
        from_bank_account_id: ftlm$('ftlm_multi_debit')?.checked
            ? ''
            : (ftlm$('ftlm_from_source_bank_account_id')?.value || ''),
        from_accounts:      JSON.stringify(ftlm_getDebitAccounts()),
        to_accounts:        JSON.stringify(ftlm_getCreditAccounts()),
        transfer_amount:    ftlm_parseAmount(ftlm$('ftlm_transfer_amount')?.value),
        is_temporary:       ftlm$('ftlm_is_temporary')?.checked ? '1' : '0',
        repayment_due_date: ftlm$('ftlm_repayment_due_date')?.value     || '',
        purpose:            (ftlm$('ftlm_purpose')?.value || '').trim(),
        dv_numbers:           ftlm_getDvNumbers(),
        supporting_documents: ftlm_getDocLinks(),
        laa_id:      (ftlm$('ftlm_laa_id')?.value || '').trim(),
        rfa_id:      (ftlm$('ftlm_rfa_id')?.value || '').trim(),
        signatories: JSON.stringify({
            laa_number:       (ftlm$('ftlm_laa_number')?.value       || '').trim(),
            fiscal_year:      (ftlm$('ftlm_laa_fiscal_year')?.value  || '').trim(),
            rfa_number:       (ftlm$('ftlm_rfa_number')?.value       || '').trim(),
            rfa_date:         (ftlm$('ftlm_rfa_date')?.value         || '').trim(),
            // Bank letter's own two signatories — FtlaService::_buildPrintPayload()
            // reads these same keys straight from this jsonb column.
            sig1_name:        (ftlm$('ftlm_sig1_name')?.value        || '').trim(),
            sig1_title:       (ftlm$('ftlm_sig1_title')?.value       || '').trim(),
            sig2_name:        (ftlm$('ftlm_sig2_name')?.value        || '').trim(),
            sig2_title:       (ftlm$('ftlm_sig2_title')?.value       || '').trim(),
        }),
        ...(status ? { status } : {}),
    };

    try {
        const endpoint = ftlm_currentId ? `ftla/update/${ftlm_currentId}` : 'ftla/add';
        const res = await ftlm_fetch(endpoint, {
            method: 'POST',
            body: JSON.stringify(body),
        });
        if (!res.success) throw new Error(res.message || 'Save failed.');
        const ftlaNum = res.data?.ftla_number || res.ftla_number || '';
        if (ftlaNum) {
            const numEl = ftlm$('ftlm_ftla_number');
            if (numEl) numEl.value = ftlaNum;
        }
        ftlm_close();
        window._ftlaApp?._loadTable();
        window._ftlaApp?._loadKPIs();
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Saved!',
                text: ftlaNum ? `FTLA ${ftlaNum} has been saved successfully.` : (res.message || 'Saved successfully.'),
                confirmButtonColor: '#0f2045',
            });
        } else {
            ftlm_toast(ftlaNum ? `Saved — ${ftlaNum}` : (res.message || 'Saved successfully.'), 'success');
        }
    } catch (e) {
        if (msg) msg.textContent = 'Error: ' + e.message;
        ftlm_toast('Save failed: ' + e.message, 'error');
    }
}

/* ── Event bindings ────────────────────────────────────── */
(function ftlm_init() {
    ftlm$('ftlm-close-btn')  ?.addEventListener('click', ftlm_close);
    ftlm$('ftlm-btn-back')   ?.addEventListener('click', ftlm_prevStep);
    ftlm$('ftlm-btn-next')   ?.addEventListener('click', ftlm_nextStep);
    ftlm_wireDvPicker();
    ftlm$('ftlm-btn-draft')  ?.addEventListener('click', () => ftlm_save('DRAFT'));
    ftlm$('ftlm-btn-submit') ?.addEventListener('click', () => ftlm_save(null));

    ftlm$('ftlm_is_temporary')?.addEventListener('change', e => {
        const rf = ftlm$('ftlm-repayment-field');
        if (rf) rf.style.display = e.target.checked ? '' : 'none';
        ftlm_schedulePreviewUpdate();
    });

    ftlm$('ftlm_from_bank_account_id')?.addEventListener('change', ftlm_updateFundSourceDisplay);
    ftlm$('ftlm_laa_number')?.addEventListener('change', ftlm_onLaaSelect);
    ftlm$('ftlm_rfa_number')?.addEventListener('change', ftlm_onRfaSelect);

    // Wire all form fields to the live preview
    const formPanel = ftlm$('ftlm-form-panel');
    if (formPanel) {
        formPanel.addEventListener('input',  ftlm_schedulePreviewUpdate);
        formPanel.addEventListener('change', ftlm_schedulePreviewUpdate);
    }

    /* Modal closes only via close/cancel buttons */
})();
</script>
