<?php
namespace PHPMaker2024\FIMS;

// Disbursement Voucher VIEW / PRINT modal — a partial included by app/pages/voucher.php (not a page
// of its own, per the phase-1 "data-entry now, print later" scope: a clean printable summary, not a
// pixel replica of the scanned General Form No. 1).
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
// cosmetic — the API is what actually enforces it); sysadmin bypasses like everywhere else.
$_vr_isSysAdmin    = IsSysAdmin();
$_vr_currentUserId = (int) CurrentUserID();
$_vr_rcId          = $_vr_currentUserId > 0
    ? (ExecuteScalar("SELECT rc_id FROM uac.users WHERE user_id = {$_vr_currentUserId}", 'DB') ?: null)
    : null;
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

@media print {
    body * { visibility: hidden; }
    #vr-overlay, #vr-overlay * { visibility: visible; }
    #vr-overlay { position: absolute; top: 0; left: 0; width: 100%; }
    #vr-root .nha-modal-hdr, #vr-root .nha-modal-foot, #vr-root .no-print { display: none !important; }
}
</style>

<div id="vr-root" class="nha-root">
    <div id="vr-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="vr-modal-title">

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
const VR_STAGE_HOLDER_RC = { FOR_FMD_REVIEW: 'RC-013', FOR_ACCOUNTING: 'RC-012', FOR_TREASURY: 'RC-014' };
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
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load disbursement voucher.');
        vr_dv = json.data;
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

function vr_print() {
    window.print();
}

function vr_render(dv) {
    $vr('vr-modal-title').textContent = `Disbursement Voucher — ${dv.dv_no}`;

    const rejection = dv.status === 'REJECTED' && dv.rejection_reason
        ? `<div class="vr-rejection"><strong>Rejected:</strong> ${vr_esc(dv.rejection_reason)}</div>` : '';

    const claimantSection = `
        <div class="vr-card">
            <p class="vr-card-title">Claimant &amp; Particulars</p>
            <dl class="vr-dl">
                <div><dt>Claimant</dt><dd>${vr_esc(dv.claimant_name) || '—'}</dd></div>
                <div><dt>Address / Employee No. / TIN</dt><dd>${vr_esc(dv.claimant_address) || '—'}${dv.employee_no_tin ? ' / ' + vr_esc(dv.employee_no_tin) : ''}</dd></div>
                <div><dt>PO Number</dt><dd>${vr_esc(dv.po_number) || '—'}</dd></div>
                <div><dt>PO Amount</dt><dd>${dv.po_amount ? vr_peso(dv.po_amount) : '—'}</dd></div>
            </dl>
            <p class="vr-particulars" style="margin-top:12px;">${vr_esc(dv.particulars) || '—'}</p>
            <dl class="vr-dl vr-dl3" style="margin-top:12px;">
                <div><dt>Gross Amount</dt><dd>${vr_peso(dv.gross_amount)}</dd></div>
                <div><dt>VAT (${dv.vat_rate || 0}%)</dt><dd>${vr_peso(dv.vat_amount)}</dd></div>
                <div><dt>W-Tax (${dv.wtax_rate || 0}%)</dt><dd>${vr_peso(dv.wtax_amount)}</dd></div>
            </dl>
        </div>`;

    $vr('vr-body').innerHTML = `
        ${rejection}
        <div class="vr-net">
            <div><small>Net Amount</small><strong>${vr_peso(dv.net_amount)}</strong></div>
            <div style="text-align:right;"><small>Status</small><span class="nha-bdg ${vr_statusClass(dv.status)}">${vr_esc(dv.status)}</span></div>
        </div>
        <div class="vr-card">
            <p class="vr-card-title">Document Identity</p>
            <dl class="vr-dl vr-dl3">
                <div><dt>DV No.</dt><dd class="nha-mono">${vr_esc(dv.dv_no)}</dd></div>
                <div><dt>DV Date</dt><dd>${vr_fmtDate(dv.dv_date)}</dd></div>
                <div><dt>Fiscal Year</dt><dd>${vr_esc(dv.fiscal_yr)}</dd></div>
                <div><dt>Expense Class</dt><dd>${vr_esc(dv.expense_class) || '—'}</dd></div>
                <div><dt>RFA No.</dt><dd>${vr_esc(dv.rfa_allocation_number) || '—'}</dd></div>
            </dl>
        </div>
        ${claimantSection}
        <div class="vr-card">
            <p class="vr-card-title">Accounting Entries</p>
            <dl class="vr-dl vr-dl3" style="margin-bottom:12px;">
                <div><dt>Dept. / Project</dt><dd>${vr_esc(dv.dept_project) || '—'}</dd></div>
                <div><dt>Fund Source</dt><dd>${vr_esc(dv.fund_source) || '—'}</dd></div>
                <div><dt>Account Code</dt><dd>${vr_esc(dv.account_code) || '—'}</dd></div>
            </dl>
            <table class="vr-ledger">
                <tr><td class="vr-l-label">Budget</td><td class="vr-l-val">${vr_peso(dv.budget_amount)}</td></tr>
                <tr><td class="vr-l-label">Obligation</td><td class="vr-l-val">${vr_peso(dv.obligation_amount)}</td></tr>
                <tr class="vr-l-total"><td class="vr-l-label">This Voucher</td><td class="vr-l-val">${vr_peso(dv.net_amount)}</td></tr>
                <tr><td class="vr-l-label">Balance</td><td class="vr-l-val">${vr_peso(dv.balance_amount)}</td></tr>
            </table>
            ${dv.remarks ? `<p class="vr-particulars" style="margin-top:12px;"><strong>Remarks:</strong> ${vr_esc(dv.remarks)}</p>` : ''}
        </div>
        <div class="vr-card" style="margin-bottom:0;">
            <p class="vr-card-title">Workflow History</p>
            <dl class="vr-dl vr-dl3">
                <div><dt>Originating (Submitted)</dt><dd>${vr_fmtDateTime(dv.submitted_at)}</dd></div>
                <div><dt>FMD (Obligation Checked)</dt><dd>${vr_fmtDateTime(dv.fmd_approved_at)}</dd></div>
                <div><dt>Accounting (Processed)</dt><dd>${vr_fmtDateTime(dv.accounting_processed_at)}</dd></div>
                <div><dt>Treasury (Released)</dt><dd>${vr_fmtDateTime(dv.treasury_released_at)}</dd></div>
            </dl>
        </div>`;

    vr_renderActions(dv);
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
    return VR_RC_ID === holderRc;
}

function vr_renderActions(dv) {
    const allowed = (dv.allowed_transitions || []).filter(t => vr_mayTransition(dv, t));
    const btn = (label, status, cls) => `<button type="button" class="nha-btn ${cls}" style="margin-left:8px;" onclick="vr_changeStatus('${status}')">${label}</button>`;
    let html = '';
    if (allowed.includes('FOR_FMD_REVIEW')) html += btn('Submit to FMD', 'FOR_FMD_REVIEW', 'nha-btn-primary');
    if (allowed.includes('FOR_ACCOUNTING')) html += btn('FMD Approve', 'FOR_ACCOUNTING', 'nha-btn-primary');
    if (allowed.includes('FOR_TREASURY'))   html += btn('Forward to Treasury', 'FOR_TREASURY', 'nha-btn-primary');
    if (allowed.includes('RELEASED'))       html += btn('Release Payment', 'RELEASED', 'nha-btn-primary');
    if (allowed.includes('REJECTED'))       html += btn('Reject', 'REJECTED', 'nha-btn-outline');
    if (allowed.includes('CANCELLED'))      html += btn('Cancel', 'CANCELLED', 'nha-btn-ghost');
    $vr('vr-actions').innerHTML = html;
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

/* ── Helpers ───────────────────────────────────────────────── */
function vr_statusClass(s) {
    const map = {
        DRAFT: 'nha-bdg-gray', FOR_FMD_REVIEW: 'nha-bdg-amber', FOR_ACCOUNTING: 'nha-bdg-blue',
        FOR_TREASURY: 'nha-bdg-teal', RELEASED: 'nha-bdg-green', REJECTED: 'nha-bdg-red', CANCELLED: 'nha-bdg-orange',
    };
    return map[s] || 'nha-bdg-gray';
}
function vr_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
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
