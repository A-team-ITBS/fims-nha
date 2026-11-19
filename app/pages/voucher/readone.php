<?php
namespace PHPMaker2024\FIMS;

// Disbursement Voucher VIEW modal — a partial included by app/pages/voucher.php (not a page of its
// own). Print opens the official DV (General Form No. 1, DvPrintService) in the shared print preview
// included at the end. Printing this DV's own Journal Entry Voucher(s) (JevPrintService) is done from
// the Journal Entry Voucher register instead (each row's own Print action there).
//
// API:  vr_open(id)
//       vr_close()
//
// Fonts and nha-fims.css are loaded by the including page.
if (!isset($_vr_api)) {
    $_vr_api = rtrim((string) Config('API.FIMS_URL'), '/');
    $_vr_api = preg_match('~/api$~', $_vr_api) ? $_vr_api : $_vr_api . '/api';
}
// Mirrors DisbursementVoucherService::authorizeTransition() for UX only (hiding a button here is
// cosmetic — the API is what actually enforces it); superadmin (uac.users.user_level_id = '-1')
// bypasses like everywhere else — GlobalService::getUserRcScope() would be the shared helper for
// this, but app/lib isn't autoloaded in page context (only app/api.php requires it), so this is
// inlined the same way $_vr_rcId already was. NOT IsSysAdmin(), which only tracks PHPMaker's own
// hardcoded admin-login path and is never true for a DB-authenticated user.
$_vr_currentUserId = (int) CurrentUserID();
$_vr_userRow        = $_vr_currentUserId > 0
    ? ExecuteRow("SELECT user_level_id, rc_id FROM uac.users WHERE user_id = {$_vr_currentUserId}", 'DB')
    : null;
$_vr_isSysAdmin    = !$_vr_userRow || SameText((string) $_vr_userRow['user_level_id'], '-1');
$_vr_rcId          = $_vr_isSysAdmin ? null : ($_vr_userRow['rc_id'] ?? null);
?>

<style>
#vr-root .vr-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
#vr-root .vr-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#vr-root dl.vr-dl { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 20px; margin: 0; }
#vr-root dl.vr-dl.vr-dl3 { grid-template-columns: repeat(3, 1fr); }
#vr-root dl.vr-dl dt { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0; }
#vr-root dl.vr-dl dd { font-size: 14px; font-weight: 600; color: var(--afims-text, #1e293b); margin: 2px 0 0; }
#vr-root .vr-particulars { font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
#vr-root .vr-ledger { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vr-ledger td { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vr-ledger td.vr-l-label { color: var(--afims-text-muted, #62657E); font-weight: 600; width: 55%; }
#vr-root .vr-ledger td.vr-l-val { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; }
#vr-root .vr-ledger tr.vr-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; }
#vr-root .vr-net { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px; margin-bottom: 14px;
    border: 2px solid var(--afims-primary-soft, #E6EBF6); border-radius: 12px;
    background: linear-gradient(to right, var(--afims-primary-soft, #E6EBF6), var(--afims-surface, #fff)); }
#vr-root .vr-net small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
#vr-root .vr-net strong { font-family: "IBM Plex Mono", monospace; font-size: 20px; color: var(--afims-primary-text, #0035A9); }
#vr-root .vr-rejection { padding: 10px 14px; background: var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 8px; font-size: 13px; color: var(--afims-danger-text, #8F222D); margin-bottom: 14px; }
#vr-root .vje-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); padding: 4px 6px; border-radius: 5px; font-size: 16px; line-height: 1; }
#vr-root .vje-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
/* Accounting's editable Deduction / ATC rows — same blocks and "+ TYPE" pills as the entry form (form.php). */
#vr-root .vje-row { position: relative; margin-bottom: 10px; }
#vr-root .vje-row .vje-remove { position: absolute; top: 0; right: 0; }
#vr-root .vje-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 6px; }
#vr-root .vje-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 6px; }
#vr-root .vje-fg label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin-bottom: 3px; }
#vr-root .vje-fg input, #vr-root .vje-fg select { width: 100%; font-size: 13px; padding: 8px 12px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; box-sizing: border-box; font-family: inherit; background: var(--afims-surface, #fff); color: var(--afims-text, #1e293b); }
#vr-root .vje-fg input[readonly] { background: var(--afims-surface-subtle, #F4F7FA); cursor: default; pointer-events: none; user-select: none; }
#vr-root .vje-fg input[readonly]:focus { outline: none; box-shadow: none; border-color: var(--afims-border, #E4ECF2); }
#vr-root .vr-ded-types { display: flex; flex-wrap: wrap; gap: 8px; }
#vr-root .vr-ded-type-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface, #FFFFFF);
    color: var(--afims-heading-fill, #000E3E); font-size: 12px; font-weight: 600; letter-spacing: .02em; cursor: pointer; transition: background .15s, border-color .15s; }
#vr-root .vr-ded-type-btn:hover { background: var(--afims-surface-subtle, #F4F7FA); border-color: var(--afims-heading-fill, #000E3E); }
#vr-root .vr-ledger input, #vr-root .vr-ledger select { width: 100%; font-size: 12.5px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; font-family: inherit; }
#vr-root .vje-section-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
#vr-root .vje-badge { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 3px 9px; border-radius: 999px;
    background: var(--afims-success-soft, #DBF2E5); color: var(--afims-success-hover, #0C7539); }
#vr-root .vje-amt-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text, #1e293b); }
#vr-root .vje-auto-tag { display: inline-block; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
    padding: 1px 6px; border-radius: 999px; background: var(--afims-primary-soft, #E6EBF6); color: var(--afims-primary-text, #0035A9); margin-left: 4px; }
/* Section B mirrors the entry form: RFA Breakdown, then plain deduction / ATC rows (no borders), all in one box. */
#vr-root .vr-ded-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vr-card > .vr-ded-section:first-child { margin-top: 0; padding-top: 0; border-top: none; }
#vr-root .vr-ded-section + .vr-ded-section { margin-top: 10px; padding-top: 0; border-top: none; } /* Deductions and ATC read as one list */
#vr-root .vr-ded-row { margin-bottom: 10px; }
#vr-root .vr-ded-row:last-child { margin-bottom: 0; }
#vr-root .vr-ded-label { font-size: 13px; font-weight: 600; color: var(--afims-heading-fill, #000E3E); margin-bottom: 4px; }
#vr-root .vr-rfa-list { display: flex; flex-direction: column; gap: 10px; }
#vr-root .vr-rfa-item { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; padding: 10px 12px; }
#vr-root .vr-att-list { display: flex; flex-direction: column; gap: 6px; }
#vr-root .vr-att-item { display: block; font-size: 13px; color: var(--afims-primary-text, #0035A9); text-decoration: none; }
#vr-root .vr-att-item:hover { text-decoration: underline; }

#vr-root .vje-grp-table { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vje-grp-table th { padding: 5px 4px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--afims-text-muted, #62657E); border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vje-grp-table td { padding: 5px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-grp-hdr td { font-weight: 700; padding-top: 10px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vje-grp-hdr:first-child td { padding-top: 5px; }
#vr-root .vje-grp-sub td:first-child { padding-left: 20px; color: var(--afims-text, #1e293b); font-weight: 400; }
#vr-root .vje-grp-amt { text-align: right; font-family: "IBM Plex Mono", monospace; white-space: nowrap; width: 120px; }
#vr-root .vje-grp-code { text-align: center; font-family: "IBM Plex Mono", monospace; color: var(--afims-text-muted, #62657E); width: 60px; }

/* Flat Accounting Entries table */
#vr-root .vje-edit-table { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vje-edit-table td, #vr-root .vje-edit-table th { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-edit-table th { text-align: left; font-weight: 600; background: var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-edit-table tr.vje-line td:nth-child(1) { text-align: center; font-family: "IBM Plex Mono", monospace; }
#vr-root .vje-edit-table input { width: 100%; font-size: 12.5px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; font-family: inherit; }
#vr-root .vje-edit-table tr.vr-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; text-align: right; }

/* "Document" layout — lettered sections (A/B/C/D) styled like a printed form, same convention as
   app/pages/voucher/form.php's own vf-doc-* rules (this view is the read-only counterpart of that
   form). Purely a presentation layer over the existing #vr-root markup — no id or JS binding
   renamed. */
#vr-root .vr-doc-section { margin-bottom: 22px; }
#vr-root .vr-doc-section-hdr {
    font-size: 15px; font-weight: 700; color: var(--afims-heading-fill, #000E3E);
    margin: 0 0 12px; letter-spacing: .01em;
}
#vr-root .vr-doc-table { width: 100%; border-collapse: collapse; border: 1px solid var(--afims-border, #D7DCE6); margin-bottom: 14px; }
#vr-root .vr-doc-table td { border: 1px solid var(--afims-border, #D7DCE6); padding: 10px 14px; vertical-align: top; width: 50%; }
#vr-root .vr-doc-table td.vr-doc-full { width: 100%; }
#vr-root .vr-doc-table .vr-doc-k { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); margin: 0 0 6px; }
#vr-root .vr-doc-summary-table { width: 100%; border-collapse: collapse; border: 1px solid var(--afims-border, #D7DCE6); margin-top: 14px; }
#vr-root .vr-doc-summary-table td { border: 1px solid var(--afims-border, #D7DCE6); padding: 10px 14px; font-size: 13px; }
#vr-root .vr-doc-summary-table td:first-child { font-weight: 600; color: var(--afims-text, #1C1E29); }
#vr-root .vr-doc-summary-table td:last-child { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; width: 220px; }
#vr-root .vr-doc-summary-table tr.vr-doc-summary-final td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); text-transform: uppercase; font-size: 12.5px; }
#vr-root .vr-doc-summary-table tr.vr-doc-summary-final td:last-child { text-transform: none; font-size: 14px; }

@media print {
    body * { visibility: hidden; }
    #vr-overlay, #vr-overlay * { visibility: visible; }
    #vr-overlay { position: absolute; top: 0; left: 0; width: 100%; }
    #vr-root .nha-modal-hdr, #vr-root .nha-modal-foot, #vr-root .no-print { display: none !important; }
}
</style>

<div id="vr-root" class="nha-root">
    <div id="vr-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-full" role="dialog" aria-modal="true" aria-labelledby="vr-modal-title">

            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="vr-modal-title">Disbursement Voucher</h3>
                <button type="button" class="nha-modal-close" onclick="vr_close()" aria-label="Close">&times;</button>
            </div>

            <div class="nha-modal-body" id="vr-body">
                <div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>
            </div>

            <div class="nha-modal-foot no-print">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="vr_close()">Close</button>
                <button type="button" class="nha-btn nha-btn-outline" onclick="vr_print()">Print</button>
                <span id="vr-actions"></span>
            </div>

        </div>
    </div>
</div><!-- /#vr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   DISBURSEMENT VOUCHER VIEW / PRINT MODAL   prefix: vr_
═══════════════════════════════════════════════════════════════ */

const VR_API = <?= json_encode($_vr_api) ?>;
const VR_IS_SYSADMIN     = <?= json_encode((bool) $_vr_isSysAdmin) ?>;
const VR_CURRENT_USER_ID = <?= json_encode($_vr_currentUserId) ?>;
const VR_RC_ID           = <?= json_encode($_vr_rcId) ?>;
// Mirrors DisbursementVoucherService::STAGE_HOLDER_RC — UX only, server is authoritative.
const VR_STAGE_HOLDER_RC = { FOR_FMD_REVIEW: '013', FOR_ACCOUNTING: '012', FOR_DIVISION_MANAGER: '012', FOR_DEPARTMENT_MANAGER: '012', FOR_TREASURY: '014' };
const $vr = id => document.getElementById(id);
let vr_id  = 0;
let vr_dv  = null;

function vr_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function vr_open(id) {
    vr_id = Number(id) || 0;
    if (!vr_id) return;
    $vr('vr-overlay').classList.add('open');
    $vr('vr-body').innerHTML = '<div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>';
    $vr('vr-actions').innerHTML = '';

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/read/${vr_id}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load disbursement voucher.');
        vr_dv = json.data;
        // vje_groupedEntriesCard() needs GL account titles even outside the Accounting stage (a
        // RELEASED voucher's printout should still show its entries) — vje_init() only loads this
        // while vje_visible(dv), so fetch it here too whenever there's a JEV to show at all.
        if (!VJE_COA.length && (vr_dv.jev?.lines ?? []).length) {
            try {
                const coaJson = await vr_authFetch(`${VR_API}/chart_of_accounts/all?postable_only=1`).then(r => r.json());
                VJE_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
            } catch (e) { /* grouped card falls back to showing the raw account_code */ }
        }
        vr_render(vr_dv);
    } catch (e) {
        $vr('vr-body').innerHTML = `<div class="nha-error" style="display:flex;"><span>${vr_esc(e.message)}</span></div>`;
    } finally {
        window.PagePreloader?.hide();
    }
}

function vr_close() {
    $vr('vr-overlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $vr('vr-overlay')?.classList.contains('open')) vr_close();
});

// Print → the official DV form (PDF, pdftk fill), via the shared print preview (included below) —
// the payload is built by its prp_dvPayload() helper. Printing this DV's own Journal Entry Voucher(s)
// is done from the Journal Entry Voucher register instead (each row's own Print action there).
function vr_print() {
    if (vr_dv) prp_open(prp_dvPayload(vr_dv), { type: 'DV' });
}

function vr_render(dv) {
    $vr('vr-modal-title').textContent = `Disbursement Voucher — ${dv.dv_no}`;

    const rejection = dv.status === 'REJECTED' && dv.rejection_reason
        ? `<div class="vr-rejection"><strong>Rejected:</strong> ${vr_esc(dv.rejection_reason)}</div>` : '';

    // Deductions / ATC are shown read-only (vr_dedAtcRowsHtml(), same look as the entry form) for every stage OTHER
    // than FOR_ACCOUNTING — while FOR_ACCOUNTING, vje_dedAtcEditor() renders the SAME data as editable
    // grids instead (Accounting can add/change/remove rows through the same pills as the entry form, not just Originating at creation).
    const totalDeductions = (dv.deductions ?? []).reduce((s, d) => s + (Number(d.deduction_amount) || 0), 0);
    const attachmentsHtml = vr_attachmentsSection(dv);
    const breakdownHtml = vr_rfaBreakdownHtml(dv);
    // Deductions + ATC live in the same box as the RFA Breakdown (like the entry form): editable grids for Accounting,
    // plain read-only rows otherwise. Each one is its own .vr-ded-section block.
    const dedAtcHtml = vje_visible(dv) ? vje_dedAtcEditor(dv) : vr_dedAtcRowsHtml(dv);

    // Section D is Accounting's. While Originating/FMD hold the voucher it isn't shown at all (any entries
    // a payroll/remittance DV was pre-filled with stay stored, just not displayed until Accounting has it).
    const dHiddenStage = ['DRAFT', 'FOR_FMD_REVIEW', 'REJECTED'].includes(dv.status);
    const dEntriesHtml = dHiddenStage ? '' : (vje_visible(dv) ? vje_entriesCard(dv) : vje_groupedEntriesCard(dv));
    const dPaymentHtml = dHiddenStage ? '' : vje_paymentEntriesCard(dv);
    const dSection = (dEntriesHtml || dPaymentHtml) ? `
        <div class="vr-doc-section" style="margin-bottom:0;">
            <p class="vr-doc-section-hdr">D. Pro Forma Journal Entry</p>
            ${dEntriesHtml}
            ${dPaymentHtml}
        </div>` : '';

    $vr('vr-body').innerHTML = `
        ${rejection}
        <div class="vr-net">
            <div><small>Net Amount</small><strong>${vr_peso(dv.net_amount)}</strong></div>
            <div style="text-align:right;"><small>Status</small><span class="nha-bdg ${vr_statusClass(dv.status)}">${vr_esc(dv.status)}</span></div>
        </div>

        <!-- ══ A. VOUCHER INFORMATION — same grouping as app/pages/voucher/form.php's own
             section A: Claimant/RFA, DV No./Date, Particulars. ══ -->
        <div class="vr-doc-section">
            <p class="vr-doc-section-hdr">A. Voucher Information</p>
            <div class="vr-card">
                <p class="vr-card-title">Request for Allotment (RFA)</p>
                ${vr_rfaSection(dv)}
            </div>
            <div class="vr-card">
                <p class="vr-card-title">Claimant</p>
                <dl class="vr-dl">
                    <div><dt>Claimant</dt><dd>${vr_esc(dv.claimant_name) || '—'}</dd></div>
                    <div><dt>Address / Employee No. / TIN</dt><dd>${vr_esc(dv.claimant_address) || '—'}${dv.employee_no_tin ? ' / ' + vr_esc(dv.employee_no_tin) : ''}</dd></div>
                    <div><dt>PO Number</dt><dd>${vr_esc(dv.po_number) || '—'}</dd></div>
                    <div><dt>PO Amount</dt><dd>${dv.po_amount ? vr_peso(dv.po_amount) : '—'}</dd></div>
                </dl>
            </div>
            <table class="vr-doc-table">
                <tr>
                    <td><p class="vr-doc-k">DV No.</p><p class="nha-mono" style="margin:0;">${vr_esc(dv.dv_no)}</p></td>
                    <td><p class="vr-doc-k">DV Date</p><p style="margin:0;">${vr_fmtDate(dv.dv_date)}</p></td>
                </tr>
                <tr>
                    <td class="vr-doc-full" colspan="2">
                        <p class="vr-doc-k">Particulars</p>
                        <p class="vr-particulars" style="margin:0;">${vr_esc(dv.particulars) || '—'}</p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- ══ B. AMOUNT AND DEDUCTIONS — laid out like the entry form (form.php): the RFA Breakdown with the
             deduction / ATC rows under it in one box, then the Gross / Less / Net summary. ══ -->
        <div class="vr-doc-section">
            <p class="vr-doc-section-hdr">B. Amount and Deductions</p>
            ${breakdownHtml || dedAtcHtml ? `<div class="vr-card">
                ${breakdownHtml}
                ${dedAtcHtml}
            </div>` : ''}
            <table class="vr-doc-summary-table">
                <tr><td>Gross Amount</td><td>${vr_peso(dv.gross_amount)}</td></tr>
                <tr><td>Less: Total Deductions</td><td>${vr_peso(totalDeductions)}</td></tr>
                <tr><td>Less: Withholding Tax (ATC)</td><td>${vr_peso((dv.atc_lines ?? []).reduce((s, a) => s + (Number(a.withholding_tax) || 0), 0))}</td></tr>
                <tr class="vr-doc-summary-final"><td>Net Amount Payable</td><td>${vr_peso(dv.net_amount)}</td></tr>
            </table>
            ${dv.remarks ? `<p class="vr-particulars" style="margin:14px 0 0;"><strong>Remarks:</strong> ${vr_esc(dv.remarks)}</p>` : ''}
        </div>

        <!-- ══ C. SUPPORTING DOCUMENTS ══ -->
        <div class="vr-doc-section">
            <p class="vr-doc-section-hdr">C. Supporting Documents</p>
            ${attachmentsHtml || '<p class="vr-hint" style="margin:0;">None — no supporting documents attached.</p>'}
        </div>

        <!-- ══ D. PRO FORMA JOURNAL ENTRY — Accounting's section: the Accounting Entries grid (editable
             while Accounting is actively preparing it, read-only/grouped afterwards), plus the Payment
             Entry. Hidden while Originating/FMD hold the voucher (DRAFT / FOR_FMD_REVIEW / REJECTED)
             and whenever there is nothing to show, so they never see an empty heading. ══ -->
        ${dSection}

        <div class="vr-card" style="margin-bottom:0;">
            <p class="vr-card-title">Workflow History</p>
            <dl class="vr-dl vr-dl3">
                <div><dt>Originating (Submitted)</dt><dd>${vr_fmtDateTime(dv.submitted_at)}</dd></div>
                <div><dt>FMD (Obligation Checked)</dt><dd>${vr_fmtDateTime(dv.fmd_approved_at)}</dd></div>
                <div><dt>Accounting — Section Chief (Processed)</dt><dd>${vr_fmtDateTime(dv.accounting_processed_at)}</dd></div>
                <div><dt>Accounting — Division Manager (Reviewed)</dt><dd>${vr_fmtDateTime(dv.division_reviewed_at)}</dd></div>
                <div><dt>Accounting — Department Manager (Approved)</dt><dd>${vr_fmtDateTime(dv.department_approved_at)}</dd></div>
                <div><dt>Treasury (Released)</dt><dd>${vr_fmtDateTime(dv.treasury_released_at)}</dd></div>
            </dl>
        </div>`;

    vr_renderActions(dv);
    if (vje_visible(dv)) vje_init(dv);
}

/* One or more RFAs this DV was drawn against (see app/pages/voucher/form.php's multi-select) —
   dv.rfas is the full-detail array from DisbursementVoucherService::fetchById(), built from
   fims.budget_allocation.dv_id (reverse lookup — see DisbursementVoucherService::linkRfas()). */
function vr_rfaSection(dv) {
    const rfas = dv.rfas ?? [];
    if (!rfas.length) return `<p class="vr-particulars">—</p>`;
    return `<div class="vr-rfa-list">${rfas.map(r => `
        <div class="vr-rfa-item">
            <strong class="nha-mono">${vr_esc(r.allocation_number)}</strong>
            <dl class="vr-dl vr-dl3" style="margin-top:6px;">
                <div><dt>Department</dt><dd>${vr_esc(r.department_name) || 'Unassigned'}</dd></div>
                <div><dt>Fiscal Year</dt><dd>${vr_esc(r.fiscal_year)}</dd></div>
                <div><dt>Vendor</dt><dd>${vr_esc(r.vendor_name) || '—'}</dd></div>
                <div><dt>Remaining</dt><dd>${vr_peso(r.remaining_balance)} of ${vr_peso(r.budget_amount)}</dd></div>
            </dl>
        </div>`).join('')}</div>`;
}

/* Supporting Documents attached via the Attachments card on the form — dv.file_attachments comes
   from FileUploadServices::attachmentsFor(). Nothing to show when the voucher has none. */
function vr_attachmentsSection(dv) {
    const files = dv.file_attachments ?? [];
    if (!files.length) return '';
    return `<div class="vr-card">
        <p class="vr-card-title">Attachments</p>
        <div class="vr-att-list">${files.map(f => {
            const url = f.file_meta?.view_url || f.file_meta?.link || '';
            const name = vr_esc(f.file_meta?.document_name || 'Supporting Document');
            return url ? `<a class="vr-att-item" href="${url}" target="_blank" rel="noopener noreferrer">${name}</a>` : `<span class="vr-att-item">${name}</span>`;
        }).join('')}</div>
    </div>`;
}

// Mirrors DisbursementVoucherService::authorizeTransition() — hiding a button is cosmetic only,
// the API re-checks and is the real gate.
function vr_mayTransition(dv, target) {
    if (VR_IS_SYSADMIN) return true;
    const holderRc = VR_STAGE_HOLDER_RC[dv.status] || null; // RC currently holding the voucher (null = DRAFT)
    if (target === 'CANCELLED') {
        const isCreator = VR_CURRENT_USER_ID > 0 && Number(dv.created_by) === VR_CURRENT_USER_ID;
        const isHolder = holderRc !== null && VR_RC_ID === holderRc;
        return isCreator || isHolder;
    }
    if (holderRc === null) return true; // DRAFT -> FOR_FMD_REVIEW: Originating submits, open to anyone.
    if (VR_RC_ID !== holderRc) return false;
    // Separation of duties inside Accounting — whoever already acted on an earlier Accounting step of this
    // voucher can't take the next one (mirrors DisbursementVoucherService::authorizeTransition()).
    const me = VR_CURRENT_USER_ID;
    if (me > 0) {
        if (target === 'FOR_DEPARTMENT_MANAGER' && Number(dv.accounting_processed_by) === me) return false;
        if (target === 'FOR_TREASURY' && (Number(dv.accounting_processed_by) === me || Number(dv.division_reviewed_by) === me)) return false;
    }
    return true;
}

function vr_renderActions(dv) {
    const allowed = (dv.allowed_transitions || []).filter(t => vr_mayTransition(dv, t));
    const btn = (label, status, cls) => `<button type="button" class="nha-btn ${cls}" style="margin-left:8px;" onclick="vr_changeStatus('${status}')">${label}</button>`;
    let html = '';
    if (allowed.includes('FOR_FMD_REVIEW')) html += btn('Submit to FMD', 'FOR_FMD_REVIEW', 'nha-btn-primary');
    if (allowed.includes('FOR_ACCOUNTING')) html += btn('FMD Approve', 'FOR_ACCOUNTING', 'nha-btn-primary');
    if (allowed.includes('FOR_DIVISION_MANAGER'))   html += btn('Forward to Division Manager', 'FOR_DIVISION_MANAGER', 'nha-btn-primary');
    if (allowed.includes('FOR_DEPARTMENT_MANAGER')) html += btn('Forward to Department Manager', 'FOR_DEPARTMENT_MANAGER', 'nha-btn-primary');
    if (allowed.includes('FOR_TREASURY'))   html += btn('Approve &amp; Forward to Treasury', 'FOR_TREASURY', 'nha-btn-primary');
    if (allowed.includes('RELEASED'))       html += btn('Release Payment', 'RELEASED', 'nha-btn-primary');
    if (allowed.includes('REJECTED'))       html += btn('Reject', 'REJECTED', 'nha-btn-outline');
    if (allowed.includes('CANCELLED'))      html += btn('Cancel', 'CANCELLED', 'nha-btn-ghost');
    $vr('vr-actions').innerHTML = html;
}

/* ══════════════════════════════════════════════════════════════
   JOURNAL ENTRY (Accounting stage)   prefix: vje_
   Three sub-sections, all saved together via one POST .../journal-entry:
     - Deduction — 0+ rows, fims.disbursement_voucher_deductions (editable here; was a static
       read-only table set only by Originating until Accounting could edit it too)
     - Withholding Tax Classification (ATC) — 0+ rows, fims.disbursement_voucher_atc_lines
     - Accounting Entries (DV) — the actual debit/credit JEV, with optional SL Code/SL Detail
═══════════════════════════════════════════════════════════════ */

let VJE_COA = []; // posting_allowed chart-of-accounts rows, loaded once per modal open
let VJE_ATC = []; // active ATC codes, loaded once per modal open
let VJE_DED = []; // active deduction types, loaded once per modal open
let VJE_RC  = []; // responsibility centers, for ATC "Chargeability", loaded once per modal open
let VJE_SL  = []; // SL masters, for the Accounting Entries grid's SL Code picker, loaded once per modal open
let VJE_BOOKS = []; // fims.books (Main Office/CISFA/NGC/MPLC-PPMO/PPMO), for the Accounting Entries Book picker, loaded once per modal open
let VJE_RFA = []; // RFAs, for the Accounting Entries RFA list, loaded once per modal open
let vje_seq    = 0;
let vje_atcSeq = 0;
let vje_dedSeq = 0;
let vje_dv     = null; // the open DV — vje_liveGrossLessDeductions() reads its gross_amount

// Visible only while the voucher is at FOR_ACCOUNTING and the viewer is Accounting Dept (RC 012)
// or sysadmin — same rule the server enforces in DisbursementVoucherService::saveJournalEntry().
function vje_visible(dv) {
    return dv.status === 'FOR_ACCOUNTING' && (VR_IS_SYSADMIN || VR_RC_ID === '012');
}

function vje_section(dv) {
    return vje_dedAtcEditor(dv) + vje_entriesCard(dv);
}

/**
 * Read-only "Accounting Entries" presentation shown whenever a JEV exists but Accounting isn't
 * actively editing it (any stage past FOR_ACCOUNTING — see vje_visible()'s doc comment) — the
 * editable grid (vje_entriesCard) disappears once the DV moves on, so without this the printed
 * voucher would show no journal entry at all past that point. Grouped by GL account (UACS code):
 * the group's own row carries the account's Debit/Credit TOTAL (summed from its own lines), and
 * each line under it is its SL Code breakdown. The sub-line label is the line's own description
 * (set when Accounting prepared the JEV — see vje_syncAutoLines()) falling back to its SL Detail /
 * SL Code name, and the small number is the line's position in the JEV (jevl.line_number), not an
 * SL code.
 */
/** Account code rendered as separate boxed segments (e.g. "2 01 01 010 02") — same visual
    convention as the Chart of Accounts page's own code chip (chart-of-accounts-page.php's
    coa_codeChip()), reused here so a line's account reads the same way in both places. */
function vr_codeChip(code) {
    const parts = String(code ?? '').split('-').filter(p => p !== '');
    if (!parts.length) return '';
    const segs = parts.map((p, i) => {
        const bg     = i === 0 ? '#EEF1F7' : '#EAF1FE';
        const fg     = i === 0 ? '#1A1B21' : '#2F5FD6';
        const border = i > 0 ? 'border-left:1px solid rgba(15,32,69,.08);' : '';
        return `<span style="background:${bg};color:${fg};padding:2px 6px;font-size:11px;font-weight:700;font-family:'IBM Plex Mono',monospace;${border}">${vr_esc(p)}</span>`;
    }).join('');
    return `<span style="display:inline-flex;border:1px solid var(--afims-border, #E4ECF2);border-radius:5px;overflow:hidden;vertical-align:middle;">${segs}</span>`;
}

/** A group's sub-lines under its GL title — General Form No. 1 convention: the claimant/vendor
    name prints in red (it's the filled-in variable, same as a handwritten entry on the paper
    form), each withholding tax rate/type breakdown prints in plain black, and any line actually
    posted to a chart_of_accounts child row (parent_account_id set — a vendor/SL sub-account; SL
    is no longer a separate sl_masters table, see "03 - alter_table/37") shows that child account's
    own code labeled "SL Code:" — the group header above already shows its PARENT control account. */
function vje_groupSubLines(group, dv) {
    // One sub-row per line in the group, each with its OWN Debit / Credit (the group's header row carries the total),
    // so every sub-account / withholding-tax breakdown is visible with its amount, not just the account's total.
    const amt = (v, show) => `<td style="padding:2px 4px 10px;text-align:right;font-size:12.5px;color:var(--afims-text-muted, #62657E);font-family:'IBM Plex Mono',monospace;width:130px;">${show ? vr_peso(v) : ''}</td>`;
    return group.items.map(it => {
        const l      = it.line;
        const debit  = Number(l.debit)  || 0;
        const credit = Number(l.credit) || 0;
        let label;
        if (it.isChild) {
            label = `<span style="font-size:12px;color:var(--afims-text-muted, #62657E);"><span style="margin-right:4px;">SL Code:</span>${vr_codeChip(l.account_code)}<span style="margin-left:6px;">${vr_esc(l.account_title || '—')}</span></span>`;
        } else if (dv?.account_code && group.account_code === dv.account_code && debit > 0 && (dv.claimant_name || dv.vendor_name)) {
            // General Form No. 1 convention: the claimant/vendor name prints in red (the filled-in variable).
            label = `<span style="font-size:13px;color:#D32F2F;">${vr_esc(dv.claimant_name || dv.vendor_name)}</span>`;
        } else {
            const atc = (dv?.atc_lines ?? []).find(a => a.account_code === group.account_code && Math.abs((Number(a.withholding_tax) || 0) - credit) < 0.01);
            label = atc
                ? `<span style="font-size:13px;color:var(--afims-text, #1e293b);"><span style="display:inline-block;width:40px;">${vr_esc(atc.rate)}%</span>${vr_esc(atc.atc_description || atc.atc_code)}</span>`
                : (l.description ? `<span style="font-size:13px;color:var(--afims-text-muted, #62657E);">${vr_esc(l.description)}</span>` : '');
        }
        if (!label) return '';
        return `<tr style="border-bottom:1px solid var(--afims-surface-subtle, #F4F7FA);">
                <td style="padding:2px 4px 10px;"></td>
                <td colspan="2" style="padding:2px 4px 10px 22px;">${label}</td>
                ${amt(debit, debit > 0)}${amt(credit, credit > 0)}
            </tr>`;
    }).join('');
}
/** GL-grouped, read-only rendering of a posted JEV's lines — shared by the SETUP JEV's read-only
    presentation (vje_groupedEntriesCard()) and each PAYMENT JEV's own card (vje_paymentEntriesCard()).
    A line posted directly to a chart_of_accounts child row (an SL/vendor sub-account) groups under
    its PARENT control account instead of its own code, same as the paper form shows one control
    account with its SL breakdown underneath — see vje_groupSubLines() for that breakdown. */
function vje_groupedTable(lines, dv = null) {
    const totalDebit  = lines.reduce((s, l) => s + (Number(l.debit)  || 0), 0);
    const totalCredit = lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);

    const resolved = lines.map(l => {
        const acct   = (VJE_COA || []).find(a => a.account_code === l.account_code);
        const parent = acct?.parent_account_id ? (VJE_COA || []).find(a => a.account_id === acct.parent_account_id) : null;
        return {
            line: l,
            isChild: !!parent,
            headerCode:  parent ? parent.account_code : l.account_code,
            headerTitle: parent ? parent.account_title : l.account_title,
        };
    });

    const groups = [];
    const byCode = new Map();
    resolved.forEach(it => {
        if (!byCode.has(it.headerCode)) {
            const g = { account_code: it.headerCode, account_title: it.headerTitle, items: [], debit: 0, credit: 0 };
            byCode.set(it.headerCode, g);
            groups.push(g);
        }
        const g = byCode.get(it.headerCode);
        g.items.push(it);
        g.debit  += Number(it.line.debit)  || 0;
        g.credit += Number(it.line.credit) || 0;
    });

    const rows = groups.map((g, idx) => `<tr style="border-bottom:none;">
                <td style="padding:10px 4px;text-align:center;font-weight:600;color:var(--afims-text-muted, #62657E);font-size:13px;width:40px;">${String(idx + 1).padStart(2, '0')}</td>
                <td style="padding:10px 4px;font-size:13px;color:var(--afims-text, #1e293b);">${vr_codeChip(g.account_code)} <span style="margin-left:6px;">${vr_esc(g.account_title || '—')}</span></td>
                <td style="padding:10px 4px;"></td>
                <td style="padding:10px 4px;text-align:right;font-size:13px;color:var(--afims-text, #1e293b);font-family:'IBM Plex Mono',monospace;width:130px;">${Number(g.debit) > 0 ? vr_peso(g.debit) : '0.00'}</td>
                <td style="padding:10px 4px;text-align:right;font-size:13px;color:var(--afims-text, #1e293b);font-family:'IBM Plex Mono',monospace;width:130px;">${Number(g.credit) > 0 ? vr_peso(g.credit) : '0.00'}</td>
            </tr>${vje_groupSubLines(g, dv)}`).join('');

    return `<div style="overflow-x:auto;">
        <table class="vje-grp-table" style="min-width:100%;border-collapse:collapse;">
            <thead><tr style="border-bottom:2px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #fff);">
                <th style="text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted, #62657E);padding:10px 4px;width:40px;">#</th>
                <th style="text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted, #62657E);padding:10px 4px;">Account Code</th>
                <th style="text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted, #62657E);padding:10px 4px;">Description</th>
                <th style="text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted, #62657E);padding:10px 4px;width:130px;">Debit (₱)</th>
                <th style="text-align:right;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted, #62657E);padding:10px 4px;width:130px;">Credit (₱)</th>
            </tr></thead>
            <tbody>${rows}</tbody>
            <tfoot><tr style="border-top:2px solid var(--afims-border, #E4ECF2);font-weight:700;background:var(--afims-surface-subtle, #F4F7FA);">
                <td colspan="3" style="text-align:right;padding:10px 4px;font-size:13px;">Total</td>
                <td style="text-align:right;padding:10px 4px;font-size:13px;font-family:'IBM Plex Mono',monospace;">${vr_peso(totalDebit)}</td>
                <td style="text-align:right;padding:10px 4px;font-size:13px;font-family:'IBM Plex Mono',monospace;">${vr_peso(totalCredit)}</td>
            </tr></tfoot>
        </table>
        </div>`;
}

function vje_groupedEntriesCard(dv) {
    const lines = dv.jev?.lines ?? [];
    if (!lines.length) return '';

    return `<div class="vr-card">
        <p class="vr-card-title">Accounting Entries${dv.jev?.book_name ? ` — ${vr_esc(dv.jev.book_name)}` : ''}</p>
        ${vje_groupedTable(lines, dv)}
    </div>`;
}

/** The PAYMENT JEV — the settlement entry (Dr Accounts Payable / Cr Cash) for a released DV. Accounting
    owns its whole lifecycle: a preparer builds it ("Prepare Payment JEV" -> preparePaymentJev()), then it
    runs the review chain (Section Chief -> Division Manager -> Department Manager posts it, via
    vr_jevStatus -> DisbursementVoucherService::changeJevStatus()). Treasury never prepares or posts it.
    Separate from the SETUP JEV above since it's its own JEV number/source document. When no payment JEV
    exists yet and the DV is RELEASED, this shows the "Prepare Payment JEV" prompt instead. */
function vje_paymentEntriesCard(dv) {
    const jevs = dv.payment_jevs ?? [];
    // Accounting (RC 012) or a superadmin drives the whole payment-JEV lifecycle: prepare it, then move
    // it through the review chain. Treasury never prepares or posts it.
    const canPost = VR_IS_SYSADMIN || VR_RC_ID === '012';

    // No payment JEV yet, but Treasury has released the voucher — Accounting prepares it here.
    if (!jevs.length) {
        if (dv.status === 'RELEASED' && canPost) {
            return `<div class="vr-card">
                <p class="vr-card-title">Payment Entry</p>
                <p class="vr-hint" style="margin:6px 0 10px;">Treasury has released this voucher. Accounting can now prepare the payment Journal Entry (Dr Accounts Payable / Cr Cash), which then goes through review: Section Chief → Division Manager → Department Manager posts it.</p>
                <button type="button" class="nha-btn nha-btn-primary" onclick="vr_preparePaymentJev(${dv.dv_id})">Prepare Payment JEV</button>
            </div>`;
        }
        return '';
    }

    // Read-only here: the review chain is actioned in the Journal Entry Voucher register (that module's
    // own row buttons), not in this DV modal. This card just shows where each payment JEV stands.
    const chain = {
        DRAFT:                  ['nha-bdg-gray',   'Draft'],
        FOR_SECTION_CHIEF:      ['nha-bdg-amber',  'For Section Chief'],
        FOR_DIVISION_MANAGER:   ['nha-bdg-purple', 'For Division Manager'],
        FOR_DEPARTMENT_MANAGER: ['nha-bdg-navy',   'For Department Manager'],
        POSTED:                 ['nha-bdg-green',  'Posted to GL'],
        REVERSED:               ['nha-bdg-red',    'Reversed'],
    };

    return jevs.map(jev => {
        const [cls, label] = chain[jev.status] || ['nha-bdg-gray', jev.status || '—'];
        const inReview = !['POSTED', 'REVERSED'].includes(jev.status);
        const hint = inReview
            ? `<p class="vr-hint" style="margin:6px 0 8px;">In Accounting review — advance it from the <strong>Journal Entry Vouchers</strong> register (Section Chief → Division Manager → Department Manager posts).</p>`
            : '';
        return `<div class="vr-card">
            <div class="vje-section-hdr">
                <p class="vr-card-title" style="margin:0;">Payment Entry — ${vr_esc(jev.jev_no)} (${vr_esc(jev.source_module)} — ${vr_esc(jev.doc_ref_no)})${jev.book_name ? ` — ${vr_esc(jev.book_name)}` : ''}</p>
                <span class="nha-bdg ${cls}">${vr_esc(label)}</span>
            </div>
            ${hint}
            ${vje_groupedTable(jev.lines ?? [], dv)}
        </div>`;
    }).join('');
}

/** Editable at the Accounting stage — was a static read-only table set only by Originating at
    creation; Accounting can now add/change/remove rows here too, same as ATC below. Feeds
    vje_syncAutoLines()'s Cr lines the same way a loaded one from creation always did. */
function vje_dedAtcEditor(dv) {
    // Same as the entry form: deduction and ATC rows share one list (added in the order clicked, each new one below the
    // previous), with one "+ TYPE" pill per deduction type and a "+ ATC ROW" pill under it. The pills are filled in by
    // vje_renderDedTypeButtons() once the deduction types have loaded (vje_init()).
    return `
        <div class="vr-ded-section">
            <div id="vje-ded-list"></div>
            <div class="vr-ded-types" id="vje-ded-types"></div>
        </div>`;
}

function vje_entriesCard(dv) {
    return `
        <div class="vr-card">
            <div class="vje-section-hdr">
                <p class="vr-card-title" style="margin:0;">Accounting Entries <span class="vr-hint" style="display:inline;font-weight:400;text-transform:none;">(optional)</span></p>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                <span class="vje-amt-badge">Required if filled in: DR = CR (balanced)</span>
            </div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px;">
                <div class="nha-fg" style="max-width:260px;">
                    <label>Book <span class="nha-req">*</span></label>
                    <select id="vje-book"><option value="">Select a book…</option></select>
                    <span class="vje-err" id="vje-err-book" style="display:none;color:var(--afims-danger,#C0392B);font-size:12px;">Book is required.</span>
                </div>
            </div>
            <p class="vr-hint" style="margin:0 0 8px;">Add one line per journal entry.</p>
            <div style="overflow-x:auto;">
                <table class="vr-ledger vje-edit-table" style="min-width:900px;">
                    <thead><tr>
                        <th style="width:40px;text-align:center;">#</th>
                        <th style="text-align:left;min-width:220px;">Account Code</th>
                        <th style="text-align:left;min-width:200px;">Description</th>
                        <th style="text-align:right;width:110px;">Debit (₱)</th>
                        <th style="text-align:right;width:110px;">Credit (₱)</th>
                        <th style="width:30px;"></th>
                    </tr></thead>
                    <tbody id="vje-lines-tbody"></tbody>
                    <tfoot><tr class="vr-l-total">
                        <td colspan="3" style="text-align:right;">Total</td>
                        <td class="vr-l-val" id="vje-total-debit">₱ 0.00</td>
                        <td class="vr-l-val" id="vje-total-credit">₱ 0.00</td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
            <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vje_addNewLine()">+ Add Line</button>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                <span id="vje-balance-msg" style="font-weight:600;font-size:13px;"></span>
            </div>
        </div>`;
}

// path must NOT start with "/" — see app/pages/voucher/form.php's vf_getJson() doc comment for why
// (the sub-folder deployment shim double-prefixes a bare-quoted "/word" literal).
async function vje_getJson(path) {
    const res  = await vr_authFetch(VR_API + '/' + path);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

async function vje_init(dv) {
    vje_dv = dv;
    try {
        const [coaJson, atcJson, dedJson, rcJson, slJson, booksJson, rfaJson] = await Promise.all([
            // postable_only (not the region-scoped /chart-of-accounts/all) — posting a voucher
            // isn't restricted to the preparer's own region, so every active+postable GL account
            // should be selectable here regardless of which region it belongs to.
            vje_getJson('chart_of_accounts/all?postable_only=1'),
            vje_getJson('voucher/atc-codes'),
            vje_getJson('voucher/deduction-types'),
            vje_getJson('responsibility-center/all'),
            vje_getJson('voucher/sl-codes'),
            vje_getJson('books/all'),
            vje_getJson('voucher/rfa/all'),
        ]);
        VJE_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
        VJE_ATC = atcJson.data ?? [];
        VJE_DED = dedJson.data ?? [];
        VJE_RC  = rcJson.data ?? [];
        VJE_SL  = slJson.data ?? [];
        VJE_BOOKS = booksJson.data ?? [];
        VJE_RFA = rfaJson.data ?? [];
    } catch (e) {
        VJE_COA = []; VJE_ATC = []; VJE_DED = []; VJE_RC = []; VJE_SL = []; VJE_BOOKS = []; VJE_RFA = [];
    }

    const vjeBookSel = $vr('vje-book');
    if (vjeBookSel) {
        vjeBookSel.innerHTML = '<option value="">Select a book…</option>' +
            VJE_BOOKS.map(b => `<option value="${b.book_id}">${vr_esc(b.name)}</option>`).join('');
        vjeBookSel.value = dv.jev?.book_id || '';
    }
    vje_renderDedTypeButtons();
    vje_dedSeq = 0;
    vje_dedRows().forEach(r => r.remove());
    (dv.deductions ?? []).forEach(d => vje_addDeductionLine(d));
    vje_recalcDeductions();

    vje_atcSeq = 0;
    vje_atcRows().forEach(r => r.remove());
    (dv.atc_lines ?? []).forEach(a => vje_addAtcLine(a));
    vje_recalcAtc();

    vje_seq = 0;
    $vr('vje-lines-tbody').innerHTML = '';
    if (dv.jev && (dv.jev.lines ?? []).length) {
        // Reload whatever was saved before (one flat row each, same order), then TAG each loaded
        // line with the same data-auto-source vje_upsertAutoLine() would have given it, by matching
        // it against the Dr account/the just-loaded Deduction rows/the just-loaded ATC rows.
        // Accounting Entries now ALWAYS auto-syncs (see vje_syncAutoLines()'s doc comment) — without
        // this, that sync would duplicate a line that's already there instead of recognizing and
        // updating it. A loaded line matching none of those (a manual split Accounting entered by
        // hand, etc.) is left untagged, so sync never touches it.
        const usedDed = new Set(), usedAtc = new Set();
        let drUsed = false, apUsed = false;
        dv.jev.lines.forEach(l => {
            const lineId = vje_addLine(l);
            const tr = $vr(`vje-row-${lineId}`);
            const debit  = Number(l.debit)  || 0;
            const credit = Number(l.credit) || 0;

            if (!drUsed && debit > 0 && l.account_code === (dv.account_code || '')) {
                tr.dataset.autoSource = 'dr';
                drUsed = true;
                return;
            }
            const dedMatch = [...vje_dedRows()].find(dtr => {
                const idx = dtr.id.replace('vje-ded-row-', '');
                if (usedDed.has(idx)) return false;
                const type = VJE_DED.find(t => String(t.deduction_type_id) === dtr.querySelector('.vje-ded-type').value);
                const acctCode = type?.account_code || dtr.querySelector('.vje-ded-acct')?.value.trim() || '';
                const amount = parseFloat(dtr.querySelector('.vje-ded-amount').value) || 0;
                return acctCode === l.account_code && Math.abs(amount - credit) < 0.01;
            });
            if (dedMatch) {
                const idx = dedMatch.id.replace('vje-ded-row-', '');
                usedDed.add(idx);
                tr.dataset.autoSource = `ded:${idx}`;
                return;
            }
            const atcMatch = [...vje_atcRows()].find(atr => {
                const idx = atr.id.replace('vje-atc-row-', '');
                if (usedAtc.has(idx)) return false;
                const def  = VJE_ATC.find(c => c.atc_code === atr.querySelector('.vje-atc-code').value);
                const rate = parseFloat(atr.querySelector('.vje-atc-rate').value) || 0;
                const base = parseFloat(atr.querySelector('.vje-atc-base').value) || 0;
                return def?.account_code === l.account_code && Math.abs((Math.round(base * rate) / 100) - credit) < 0.01;
            });
            if (atcMatch) {
                const idx = atcMatch.id.replace('vje-atc-row-', '');
                usedAtc.add(idx);
                tr.dataset.autoSource = `atc:${idx}`;
                return;
            }
            if (!apUsed && credit > 0 && l.account_code === (dv.vendor_ap_account_code || '2-01-01-010')) {
                tr.dataset.autoSource = 'ap';
                apUsed = true;
            }
        });
        vje_renumber();
        vje_recalc();
    }
    vje_syncAutoLines();

    vje_updateAcctgEntryPanels(dv);
}

/** Gross minus whatever is currently in the Deduction grid — the live "net before withholding tax"
    figure. Used wherever the old code read the DV's static dv.net_amount (which was fixed at
    whatever deductions existed at save time); now that Deductions are editable here, that snapshot
    would go stale the moment a row changes. */
function vje_liveGrossLessDeductions() {
    return Math.max(0, (Number(vje_dv?.gross_amount) || 0) - vje_totalDeductions());
}

/* ── Deduction + ATC rows (editable — Accounting can add/change/remove them here too, not just Originating at creation).
   Same blocks as the entry form: one list (#vje-ded-list), each new row added below the previous one. ───────────── */
function vje_dedRows() { return $vr('vje-ded-list').querySelectorAll('.vje-ded-row'); }
function vje_atcRows() { return $vr('vje-ded-list').querySelectorAll('.vje-atc-row'); }

/* One "+ TYPE" pill per deduction type (payroll-only types only on a payroll DV) plus "+ ATC ROW" — same as the form's. */
function vje_renderDedTypeButtons() {
    const box = $vr('vje-ded-types');
    if (!box) return;
    const types = VJE_DED.filter(t => vje_dv?.payroll || !String(t.code || '').startsWith('PAYROLL_'));
    box.innerHTML = types.map(t => `<button type="button" class="vr-ded-type-btn" onclick="vje_addDeductionLine({deduction_type_id:'${vr_esc(t.deduction_type_id)}'})">+ ${vr_esc(t.name).toUpperCase()}</button>`).join('')
        + '<button type="button" class="vr-ded-type-btn" onclick="vje_addAtcLine()">+ ATC ROW</button>';
}

function vje_addDeductionLine(d = null) {
    const idx = ++vje_dedSeq;
    const typeId = d?.deduction_type_id != null ? String(d.deduction_type_id) : '';
    const type = VJE_DED.find(t => String(t.deduction_type_id) === typeId);
    // The posting-account picker shows only when the deduction type has no default posting account
    // (deduction_types.account_code IS NULL — e.g. "OTHER"); the type's own default always wins — see fetchDeductions()'s COALESCE.
    const needsAcct = !!type && !type.account_code;
    const div = document.createElement('div');
    div.className = 'vje-row vje-ded-row';
    div.id = `vje-ded-row-${idx}`;
    div.innerHTML = `
        <button type="button" class="vje-remove" title="Remove" onclick="vje_removeDeductionLine(${idx})">&times;</button>
        <div class="vr-ded-label">${vr_esc(type?.name || d?.name) || '—'}</div>
        <input type="hidden" class="vje-ded-type" value="${vr_esc(typeId)}">
        <div class="vje-grid2">
            <div class="vje-fg"><label>Rate (%)</label><input type="number" class="vje-ded-rate" min="0" step="0.01" placeholder="e.g. 10"></div>
            <div class="vje-fg"><label>Deduction Amount (₱)</label><input type="number" class="vje-ded-amount" min="0" step="0.01" placeholder="Auto-computed"></div>
        </div>
        ${needsAcct ? `<div class="vje-fg" style="margin-top:6px;"><label>Posting Account *</label><input type="text" class="vje-ded-acct" placeholder="Search GL account code (UACS)…"></div>` : ''}`;
    div.querySelector('.vje-ded-rate').value = d?.rate ?? '';
    div.querySelector('.vje-ded-amount').value = d?.deduction_amount != null ? Number(d.deduction_amount || 0).toFixed(2) : '';
    div.querySelector('.vje-ded-rate').addEventListener('input', () => vje_recalcDeductionLine(idx));
    div.querySelector('.vje-ded-amount').addEventListener('input', () => vje_recalcDeductionLine(idx));
    $vr('vje-ded-list').appendChild(div);
    const acct = div.querySelector('.vje-ded-acct');
    if (acct) {
        acct.value = d?.account_code ?? '';
        nhaSearchableSelect(acct, VJE_COA.map(a => ({ value: a.account_code, label: a.account_title })));
        acct.addEventListener('input', () => vje_recalcDeductionLine(idx));
    }
    vje_recalcDeductionLine(idx);
}

function vje_removeDeductionLine(idx) {
    $vr(`vje-ded-row-${idx}`)?.remove();
    vje_recalcDeductions();
}

/* A deduction's gross_amount always mirrors this DV's own gross_amount (not separately editable —
   see the RFA Breakdown / summary); its deduction_amount is rate% of that unless the
   preparer types an amount directly (no rate given = flat peso deduction). Same convention as
   app/pages/voucher/form.php's vf_recalcDeductionLine(). */
function vje_recalcDeductionLine(idx) {
    const tr = $vr(`vje-ded-row-${idx}`);
    if (!tr) return;
    const gross = Number(vje_dv?.gross_amount) || 0;
    const rate = parseFloat(tr.querySelector('.vje-ded-rate').value);
    if (!isNaN(rate) && rate > 0) {
        tr.querySelector('.vje-ded-amount').value = (gross * rate / 100).toFixed(2);
    }
    vje_recalcDeductions();
}

function vje_collectDeductions() {
    const out = [];
    vje_dedRows().forEach(tr => {
        const typeId = tr.querySelector('.vje-ded-type').value;
        const amount = parseFloat(tr.querySelector('.vje-ded-amount').value) || 0;
        if (!typeId && amount <= 0) return; // blank trailing row
        out.push({
            deduction_type_id: typeId || null,
            rate             : tr.querySelector('.vje-ded-rate').value === '' ? null : parseFloat(tr.querySelector('.vje-ded-rate').value),
            gross_amount     : Number(vje_dv?.gross_amount) || 0,
            deduction_amount : amount,
            account_code     : tr.querySelector('.vje-ded-acct')?.value.trim() || null,
        });
    });
    return out;
}

function vje_totalDeductions() {
    return vje_collectDeductions().reduce((s, d) => s + d.deduction_amount, 0);
}

function vje_recalcDeductions() {
    const badge = $vr('vje-badge-net-ded');
    if (badge) badge.textContent = vr_peso(vje_liveGrossLessDeductions());
    vje_syncAutoLines();
}

/* ── Withholding Tax Classification (ATC) ────────────────────── */
function vje_addAtcLine(a = null) {
    const idx = ++vje_atcSeq;
    const div = document.createElement('div');
    div.className = 'vje-row vje-atc-row';
    div.id = `vje-atc-row-${idx}`;
    div.innerHTML = `
        <button type="button" class="vje-remove" title="Remove" onclick="vje_removeAtcLine(${idx})">&times;</button>
        <div class="vr-ded-label">ATC (Withholding Tax)</div>
        <div class="vje-fg" style="margin-top:6px;"><label>ATC Code — Description</label><input type="text" class="vje-atc-code" placeholder="Search ATC code…"></div>
        <div class="vje-grid3">
            <div class="vje-fg"><label>Rate (%)</label><input type="number" class="vje-atc-rate" min="0" max="100" step="0.01" placeholder="0.00"></div>
            <div class="vje-fg"><label>Tax Base (₱)</label><input type="number" class="vje-atc-base" min="0" step="0.01" placeholder="0.00"></div>
            <div class="vje-fg"><label>Withholding Tax (₱)</label><input type="text" class="vje-atc-wh" readonly value="₱ 0.00"></div>
        </div>
        <div class="vje-fg" style="display:none;"><label>Chargeability</label>
            <select class="vje-atc-rc">
                <option value="">— None —</option>
                ${VJE_RC.map(r => `<option value="${vr_esc(r.rc_id)}">${vr_esc(r.rc_id)} — ${vr_esc(r.rc_name)}</option>`).join('')}
            </select>
        </div>`;
    div.querySelector('.vje-atc-code').value = a?.atc_code ?? '';
    div.querySelector('.vje-atc-rate').value = a?.rate ?? '';
    div.querySelector('.vje-atc-base').value = a ? (Number(a.tax_base) || 0).toFixed(2) : vje_liveGrossLessDeductions().toFixed(2);
    div.querySelector('.vje-atc-rc').value   = a?.chargeability ?? '';
    div.querySelector('.vje-atc-code').addEventListener('change', () => vje_onAtcCodeSelect(idx));
    div.querySelectorAll('.vje-atc-rate, .vje-atc-base').forEach(el => el.addEventListener('input', () => vje_recalcAtcLine(idx)));
    $vr('vje-ded-list').appendChild(div);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(div.querySelector('.vje-atc-code'), VJE_ATC.map(c => ({ value: c.atc_code, label: c.description })));
    vje_recalcAtcLine(idx);
}

function vje_removeAtcLine(idx) {
    $vr(`vje-atc-row-${idx}`)?.remove();
    vje_recalcAtc();
    vje_syncAutoLines();
}

/**
 * Picking an ATC code prefills the rest of its row — same convention as app/pages/voucher/form.php's
 * vf_onAtcCodeSelect(): Rate(%) always follows the code's own BIR default (fims.atc_codes.tax_rate,
 * overwriting whatever was there), Tax Base is already defaulted to this DV's net_amount at row
 * creation (see vje_addAtcLine) and is left alone here, and Chargeability defaults — if not already
 * set — to the first RFA this DV cites (fims.budget_allocation.requesting_rc_id), since that's who
 * the withholding is normally charged against.
 */
function vje_onAtcCodeSelect(idx) {
    const tr = $vr(`vje-atc-row-${idx}`);
    if (!tr) return;
    const code   = tr.querySelector('.vje-atc-code').value;
    const atcDef = VJE_ATC.find(c => c.atc_code === code);
    const rateEl = tr.querySelector('.vje-atc-rate');
    const rcEl   = tr.querySelector('.vje-atc-rc');

    if (atcDef && isFinite(Number(atcDef.tax_rate))) rateEl.value = Number(atcDef.tax_rate).toFixed(2);

    if (!rcEl.value) {
        const primaryRc = vje_dv?.rfas?.[0]?.requesting_rc_id;
        if (primaryRc) rcEl.value = primaryRc;
    }

    vje_recalcAtcLine(idx);
}

function vje_recalcAtcLine(idx) {
    const tr = $vr(`vje-atc-row-${idx}`);
    if (!tr) return;
    const rate = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
    const base = parseFloat(tr.querySelector('.vje-atc-base').value) || 0;
    tr.querySelector('.vje-atc-wh').value = vr_peso(base * rate / 100);
    vje_recalcAtc();
    vje_syncAutoLines();
}

function vje_collectAtcLines() {
    const lines = [];
    vje_atcRows().forEach(tr => {
        const atc_code = tr.querySelector('.vje-atc-code').value;
        const rate     = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
        const tax_base = parseFloat(tr.querySelector('.vje-atc-base').value) || 0;
        if (!atc_code && tax_base <= 0) return; // blank trailing row
        // Chargeability isn't asked for any more — the system takes it from the first RFA this DV cites.
        lines.push({ atc_code, rate, tax_base, chargeability: tr.querySelector('.vje-atc-rc').value || vje_dv?.rfas?.[0]?.requesting_rc_id || null });
    });
    return lines;
}

function vje_recalcAtc() {
    // Nothing to total here any more — the summary table under Section B shows Less: Withholding Tax (ATC).
}

/* ── Accounting Entries (DV) ──────────────────────────────────── */

/**
 * Upserts the auto-derived lines (Dr expense for the full gross, Cr each Deduction/ATC row that
 * carries a mapped UACS account code, Cr Accounts Payable for whatever's left) into the grid,
 * tracked via each row's data-auto-source so repeat syncs update in place instead of duplicating.
 * Always runs, on every Deduction/ATC/Account Code change, including after a Journal Entry has
 * already been saved once — a line the preparer added manually (no data-auto-source) is never
 * touched; see vje_init()'s matching logic for how a RELOADED saved line gets re-tagged so this
 * doesn't duplicate it.
 */
/* ── Accounting Entries grid: flat, one row per journal entry line — same convention as
   app/pages/voucher/form.php's own Accounting Entries table (vf_addJevLine). ── */

function vje_addNewLine() {
    vje_addLine();
}

function vje_addLine(d = null) {
    const lineId = ++vje_seq;
    const tr = document.createElement('tr');
    tr.id = `vje-row-${lineId}`;
    tr.className = 'vje-line';
    tr.dataset.lineId = lineId;
    tr.innerHTML = `
        <td class="vr-l-val vje-line-no"></td>
        <td><input type="text" class="vje-acct-code" placeholder="Search GL account code (UACS)…"></td>
        <td><input type="text" class="vje-line-desc" placeholder="Description"></td>
        <td><input type="number" class="vje-debit" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="vje-credit" min="0" step="0.01" placeholder="0.00"></td>
        <td><button type="button" class="vje-remove" title="Remove line" onclick="vje_removeLine(${lineId})">&times;</button></td>`;
    const acctInput = tr.querySelector('.vje-acct-code');
    const descInput = tr.querySelector('.vje-line-desc');
    const debitInput = tr.querySelector('.vje-debit');
    const creditInput = tr.querySelector('.vje-credit');
    acctInput.value = d?.account_code ?? '';
    descInput.value = d?.description ?? '';
    debitInput.value = Number(d?.debit) > 0 ? Number(d.debit).toFixed(2) : '';
    creditInput.value = Number(d?.credit) > 0 ? Number(d.credit).toFixed(2) : '';
    nhaSearchableSelect(acctInput, VJE_COA.map(a => ({ value: a.account_code, label: a.account_title })));
    debitInput.addEventListener('input', vje_recalc);
    creditInput.addEventListener('input', vje_recalc);
    $vr('vje-lines-tbody').appendChild(tr);
    vje_renumber();
    vje_recalc();
    return lineId;
}

function vje_removeLine(lineId) {
    const tr = $vr(`vje-row-${lineId}`);
    if (tr) {
        tr.remove();
        vje_renumber();
        vje_recalc();
    }
}

/** Running "01", "02", … across every line, in on-screen order. */
function vje_renumber() {
    let n = 0;
    $vr('vje-lines-tbody').querySelectorAll('tr.vje-line').forEach(tr => {
        n += 1;
        tr.querySelector('.vje-line-no').textContent = String(n).padStart(2, '0');
    });
}

/** Keeps the Accounting Entries grid in sync with the DV's own numbers: Dr the claimant/expense
    line for the full gross, Cr each Deduction/ATC row that carries a mapped UACS account code, Cr
    Accounts Payable for whatever's left. Tracked via each row's data-auto-source so repeat syncs
    update it in place instead of duplicating it; a line the preparer added by hand (no
    data-auto-source) is never touched. */
function vje_syncAutoLines() {
    const gross = Number(vje_dv?.gross_amount) || 0;

    vje_upsertAutoLine('dr', { account_code: vje_dv?.account_code || '', debit: gross, credit: 0, description: vje_dv?.claimant_name || '' });

    // Reads the LIVE Deduction grid (vje_dedAtcEditor()), not a static vje_dv.deductions snapshot —
    // Accounting can now add/change/remove rows here, see vje_addDeductionLine()'s doc comment.
    const dedIds = new Set();
    let dedCreditTotal = 0;
    vje_dedRows().forEach(tr => {
        const idx      = tr.id.replace('vje-ded-row-', '');
        const typeId   = tr.querySelector('.vje-ded-type').value;
        const amount   = parseFloat(tr.querySelector('.vje-ded-amount').value) || 0;
        const type     = VJE_DED.find(t => String(t.deduction_type_id) === typeId);
        const acctCode = type?.account_code || tr.querySelector('.vje-ded-acct')?.value.trim() || '';
        if (!typeId || !acctCode || amount <= 0) return;
        dedIds.add(idx);
        dedCreditTotal += amount;
        vje_upsertAutoLine(`ded:${idx}`, { account_code: acctCode, debit: 0, credit: amount, description: type.name });
    });
    vje_pruneAutoLines('ded:', id => dedIds.has(id));

    const atcIds = new Set();
    let atcCreditTotal = 0;
    vje_atcRows().forEach(tr => {
        const idx  = tr.id.replace('vje-atc-row-', '');
        const code = tr.querySelector('.vje-atc-code').value;
        const rate = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
        const base = parseFloat(tr.querySelector('.vje-atc-base').value) || 0;
        const atcDef = VJE_ATC.find(c => c.atc_code === code);
        if (!code || !atcDef?.account_code || base <= 0 || rate <= 0) return;
        const credit = Math.round(base * rate) / 100;
        atcIds.add(idx);
        atcCreditTotal += credit;
        vje_upsertAutoLine(`atc:${idx}`, { account_code: atcDef.account_code, debit: 0, credit, description: `${rate}% ${atcDef.description}` });
    });
    vje_pruneAutoLines('atc:', id => atcIds.has(id));

    // Credits the vendor's own Accounts Payable sub-ledger account (fims.vendors.coa_account_id,
    // e.g. "2-01-01-010-01") when the claimant is a registered vendor with one set up, so the
    // payable actually lands in that vendor's subsidiary ledger instead of just the bare AP
    // control account — see DisbursementVoucherService::fetchById()'s vendor_ap_account_code.
    const apAccountCode = vje_dv?.vendor_ap_account_code || '2-01-01-010';
    const apRemainder = Math.round((gross - dedCreditTotal - atcCreditTotal) * 100) / 100;
    if (apRemainder > 0.004) {
        vje_upsertAutoLine('ap', { account_code: apAccountCode, debit: 0, credit: apRemainder, description: 'Net Amount Payable' });
    } else {
        $vr('vje-lines-tbody').querySelector('tr.vje-line[data-auto-source="ap"]')?.remove();
    }

    vje_renumber();
    vje_recalc();
}

function vje_upsertAutoLine(sourceKey, d) {
    let tr = $vr('vje-lines-tbody').querySelector(`tr.vje-line[data-auto-source="${sourceKey}"]`);
    if (!tr) {
        const lineId = vje_addLine(d);
        tr = $vr(`vje-row-${lineId}`);
        tr.dataset.autoSource = sourceKey;
        return;
    }
    tr.querySelector('.vje-acct-code').value = d.account_code || '';
    tr.querySelector('.vje-line-desc').value = d.description || '';
    tr.querySelector('.vje-debit').value  = d.debit  > 0 ? d.debit.toFixed(2)  : '';
    tr.querySelector('.vje-credit').value = d.credit > 0 ? d.credit.toFixed(2) : '';
}

/** Removes auto-tracked lines whose source (a Deduction or live ATC row) no longer applies. */
function vje_pruneAutoLines(prefix, keepFn) {
    $vr('vje-lines-tbody').querySelectorAll(`tr.vje-line[data-auto-source^="${prefix}"]`).forEach(tr => {
        if (keepFn(tr.dataset.autoSource.slice(prefix.length))) return;
        tr.remove();
    });
}

function vje_collectLines() {
    const lines = [];
    $vr('vje-lines-tbody').querySelectorAll('tr.vje-line').forEach(tr => {
        const account_code = tr.querySelector('.vje-acct-code').value.trim();
        const debit        = parseFloat(tr.querySelector('.vje-debit').value) || 0;
        const credit       = parseFloat(tr.querySelector('.vje-credit').value) || 0;
        const description  = tr.querySelector('.vje-line-desc').value.trim();
        if (!account_code && debit <= 0 && credit <= 0 && !description) return; // blank trailing line
        lines.push({ account_code, debit, credit, description: description || null });
    });
    return lines;
}

function vje_updateAcctgEntryPanels(dv) {
    const vendor = dv?.vendor_name || '—';
    const amount = Number(dv?.gross_amount) || 0;
    const acctCode = dv?.account_code || '—';

    const vendorEl = $vr('vje-acc-vendor-name');
    if (vendorEl) vendorEl.textContent = vendor;

    const amountEl = $vr('vje-acc-amount');
    if (amountEl) amountEl.textContent = vr_peso(amount);

    const acctEl = $vr('vje-acc-account-code');
    if (acctEl) acctEl.textContent = acctCode;

    // Populate RFA list
    const rfaList = $vr('vje-rfa-list');
    if (rfaList) {
        const rfarefs = VJE_RFA || [];
        rfaList.innerHTML = '';
        rfarefs.forEach(r => {
            const div = document.createElement('div');
            div.style.cssText = 'padding:8px;border-radius:6px;background:var(--afims-surface-subtle,#F4F7FA);cursor:pointer;';
            div.onclick = () => vje_selectRfa(r.allocation_number);
            div.innerHTML = `
                <div style="font-weight:600;color:var(--afims-text,#1e293b);">${r.allocation_number}</div>
                <div style="font-size:12px;color:var(--afims-text-muted,#62657E);margin-top:2px;">${r.request_referrence_no || ''}</div>
            `;
            rfaList.appendChild(div);
        });
    }
}

function vje_selectRfa(rfaNumber) {
    const tr = $vr('vje-lines-tbody').querySelector('tr.vje-line:last-child');
    if (tr) {
        tr.querySelector('.vje-line-rfa').value = rfaNumber;
        vje_recalc();
    }
}

function vje_recalc() {
    const lines = vje_collectLines();
    const totalDebit  = lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
    $vr('vje-total-debit').textContent  = vr_peso(totalDebit);
    $vr('vje-total-credit').textContent = vr_peso(totalCredit);
    const balanced = lines.length >= 2 && totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.005;
    const msg = $vr('vje-balance-msg');
    msg.textContent = balanced ? 'Balanced ✓' : 'Not balanced — debits must equal credits';
    msg.style.color = balanced ? 'var(--afims-success, #16A34A)' : 'var(--afims-danger, #DC3545)';
    return balanced;
}

// Saves the Deductions / ATC / Accounting Entries grids. There's no separate "Save Journal Entry"
// button anymore — vr_changeStatus() calls this automatically right before forwarding the voucher
// to the Division Manager, and only forwards if it returns true. Resolves false (after alerting)
// on a validation/server error; doesn't reload the modal — the status change that follows does.
async function vje_save() {
    const lines = vje_collectLines();
    const atc_lines = vje_collectAtcLines();
    const deductions = vje_collectDeductions();
    const book_id = $vr('vje-book')?.value || null;
    const jev_classification = 'REGULAR';

    const errBook = $vr('vje-err-book');
    if (lines.length && !book_id) {
        if (errBook) errBook.style.display = 'block';
        alert('Book is required once Accounting Entries lines are filled in.');
        return false;
    }
    if (errBook) errBook.style.display = 'none';

    const missingAcct = deductions.find(d => {
        const type = VJE_DED.find(t => String(t.deduction_type_id) === String(d.deduction_type_id));
        return !!d.deduction_type_id && !type?.account_code && !d.account_code;
    });
    if (missingAcct) {
        const type = VJE_DED.find(t => String(t.deduction_type_id) === String(missingAcct.deduction_type_id));
        alert(`"${type?.name || 'This deduction'}" has no default posting account — pick one before saving.`);
        return false;
    }

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${vr_id}/journal-entry`, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({ atc_lines, lines, deductions, book_id, jev_classification }),
        });
        const json = await res.json();
        if (!json.success) {
            alert((json.errors?.length ? json.errors.join('\n') : null) || json.message || 'Failed to save Journal Entry.');
            return false;
        }
        return true;
    } catch (e) {
        alert('Could not save: ' + e.message);
        return false;
    } finally {
        window.PagePreloader?.hide();
    }
}

async function vr_changeStatus(status) {
    let notes = null;
    if (status === 'REJECTED') {
        notes = prompt('Reason for rejecting this disbursement voucher:');
        if (notes === null) return;
        if (!notes.trim()) { alert('A reason is required when rejecting.'); return; }
    } else if (!confirm(`Change this disbursement voucher to ${status}?`)) {
        return;
    }

    // Accounting's Journal Entry grids are saved as part of forwarding — no separate Save step.
    if (status === 'FOR_DIVISION_MANAGER' && vr_dv && vje_visible(vr_dv)) {
        if (!(await vje_save())) return;
    }

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${vr_id}/status`, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({ status, notes }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to change status.');
        window.vf_onSaved?.(null, json.message); // reuses the list page's toast + reload hook
        await vr_open(vr_id);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

// Accounting prepares the payment Journal Entry for a released DV (Dr A/P / Cr Cash from its check).
// Server re-checks RC-012/sysadmin — see DisbursementVoucherService::preparePaymentJev().
async function vr_preparePaymentJev(dvId) {
    if (!confirm('Prepare the payment Journal Entry for this voucher? It will enter the Accounting review chain: Section Chief → Division Manager → Department Manager posts it.')) return;
    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${dvId}/prepare-payment-jev`, { method: 'POST' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to prepare the payment entry.');
        window.vf_onSaved?.(null, json.message);
        await vr_open(vr_id);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

// Advance a payment Journal Entry one step along the review chain (and post it on the final step).
// Server re-checks RC-012/sysadmin and the allowed transition — see DisbursementVoucherService::changeJevStatus().
async function vr_jevStatus(jevId, status) {
    if (!confirm(`Move this payment Journal Entry to ${status.replace(/_/g, ' ')}?`)) return;
    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/journal-entry/${jevId}/jev-status`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to update the Journal Entry.');
        window.vf_onSaved?.(null, json.message);
        await vr_open(vr_id);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function vr_statusClass(s) {
    const map = {
        DRAFT: 'nha-bdg-gray', FOR_FMD_REVIEW: 'nha-bdg-amber', FOR_ACCOUNTING: 'nha-bdg-blue',
        FOR_DIVISION_MANAGER: 'nha-bdg-purple', FOR_DEPARTMENT_MANAGER: 'nha-bdg-navy',
        FOR_TREASURY: 'nha-bdg-teal', RELEASED: 'nha-bdg-green', REJECTED: 'nha-bdg-red', CANCELLED: 'nha-bdg-orange',
    };
    return map[s] || 'nha-bdg-gray';
}
function vr_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
/* Shows the account title alongside the code — same Chart of Accounts lookup the searchable-select
   pickers (nhaSearchableSelect, form.php / vje_addLine) show as their option labels — and flags it
   if this DV's own account isn't posting_allowed (shouldn't happen for a voucher saved since that
   check was added, but can for older/legacy data). */
/* "RFA Breakdown" — same table as the entry form: one row per itemized RFA line ("account_code - description"),
   the RFA it belongs to, that RFA's balance, and the Amount. The per-line split isn't stored (only gross_amount is),
   so the gross is spread over the lines in order, each up to its own amount, any excess on the last line — exactly
   what the form shows by default. No Gross Amount row here: the summary below has it. */
function vr_rfaBreakdownHtml(dv) {
    const gross = Number(dv.gross_amount) || 0;
    const rows = [];
    (dv.rfas ?? []).forEach(r => {
        const lines = (r.lines || []).length
            ? r.lines.map(l => ({ code: l.account_code, text: l.account_title || l.description, cap: Number(l.amount) || 0 }))
            : [{ code: r.account_code, text: r.account_title || r.description, cap: Number(r.remaining_balance) || 0 }];
        lines.forEach((l, i) => rows.push({ rfa: r, first: i === 0, span: lines.length, ...l }));
    });
    if (!rows.length) return '';
    let left = gross;
    rows.forEach((r, i) => {
        const v = i === rows.length - 1 ? left : Math.min(r.cap, left);
        r.amount = Math.max(0, Math.round(v * 100) / 100);
        left -= r.amount;
    });
    const label = (code, text) => [code, text].filter(Boolean).map(vr_esc).join(' - ') || '—';
    const body = rows.map(r => `<tr>
        <td>${label(r.code, r.text)}</td>
        ${r.first ? `<td rowspan="${r.span}" style="vertical-align:top;">${vr_esc(r.rfa.allocation_number)}</td>
        <td rowspan="${r.span}" style="text-align:right;vertical-align:top;">${vr_peso(r.rfa.remaining_balance)}</td>` : ''}
        <td style="text-align:right;width:150px;">${r.amount ? vr_peso(r.amount) : '—'}</td>
    </tr>`).join('');
    return `<p class="vr-card-title">RFA Breakdown</p>
        <div style="overflow-x:auto;"><table class="vr-ledger">
            <thead><tr>
                <th style="text-align:left;">Account Code - Description</th>
                <th style="text-align:left;">RFA</th>
                <th style="text-align:right;">RFA Balance</th>
                <th style="text-align:right;">Amount</th>
            </tr></thead>
            <tbody>${body}</tbody>
        </table></div>`;
}

/* Read-only deduction + ATC rows — the SAME blocks Accounting sees (vje_addDeductionLine() / vje_addAtcLine()) and the entry
   form shows, but view-only: read-only boxes that can't be clicked or focused (see .vje-fg input[readonly] — no caret, no focus
   ring, no pointer), with no × remove mark and no "+ TYPE" pills. */
function vr_dedAtcRowsHtml(dv) {
    const ro = (label, value, extra = '') => `<div class="vje-fg"${extra}><label>${label}</label><input type="text" readonly tabindex="-1" value="${vr_esc(value)}"></div>`;
    const ded = (dv.deductions ?? []).map(d => `<div class="vje-row">
        <div class="vr-ded-label">${vr_esc(d.name) || '—'}</div>
        <div class="vje-grid2">
            ${ro('Rate (%)', d.rate != null ? d.rate : '')}
            ${ro('Deduction Amount (₱)', vr_peso(d.deduction_amount))}
        </div>
        ${!d.type_account_code && d.account_code ? ro('Posting Account', d.account_code, ' style="margin-top:6px;"') : ''}
    </div>`);
    const atc = (dv.atc_lines ?? []).map(a => `<div class="vje-row">
        <div class="vr-ded-label">ATC (Withholding Tax)</div>
        ${ro('ATC Code — Description', [a.atc_code, a.atc_description].filter(Boolean).join(' — '), ' style="margin-top:6px;"')}
        <div class="vje-grid3">
            ${ro('Rate (%)', a.rate != null ? a.rate : '')}
            ${ro('Tax Base (₱)', vr_peso(a.tax_base))}
            ${ro('Withholding Tax (₱)', vr_peso(a.withholding_tax))}
        </div>
    </div>`);
    const all = ded.concat(atc);
    return all.length ? `<div class="vr-ded-section">${all.join('')}</div>` : '';
}

function vr_accountCodeDisplay(dv) {
    if (!dv.account_code) return '—';
    const code = vr_esc(dv.account_code);
    if (dv.account_posting_allowed === false) {
        return `${code} <span class="nha-bdg nha-bdg-red" title="Not postable or no longer exists in the Chart of Accounts">⚠ invalid</span>`;
    }
    return dv.account_title ? `${code} — ${vr_esc(dv.account_title)}` : code;
}
function vr_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function vr_fmtDateTime(s) {
    if (!s) return '— (not yet)';
    const d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function vr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
<?php
// Print → the shared print preview (prp_open + the prp_dvPayload helper).
include_once __DIR__ . '/../readone/print-preview-readone.php';
?>
