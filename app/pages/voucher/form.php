<?php
namespace PHPMaker2024\FIMS;

// Disbursement Voucher ADD / EDIT modal — a partial included by app/pages/voucher.php (not a page of
// its own). Modeled on app/pages/po/form.php. One generic voucher shape for any claimant/payment
// type (vendor payment, payroll, reimbursement, etc.) — the paper General Form No. 1 is itself a
// single layout; what differs is just what gets written in Particulars/Amount. A claimant is
// optionally a registered vendor (fims.vendors via /vendors/all, autofills name/address/TIN) or
// free text typed directly (e.g. a payroll group name).
// "RFA" = fims.budget_allocation WHERE allocation_type = 'RFA' (/voucher/rfa/all, /voucher/rfa/read/{id}).
//
// API:  vf_open()      → new DV
//       vf_open(id)    → edit DV #id (DRAFT / REJECTED only)
//       vf_close()
// Hook: window.vf_onSaved = (data, message) => {...}   called after a successful save.
//
// Fonts and nha-fims.css are loaded by the including page.
if (!isset($_vf_api)) {
    $_vf_api = rtrim((string) Config('API.FIMS_URL'), '/');
    $_vf_api = preg_match('~/api$~', $_vf_api) ? $_vf_api : $_vf_api . '/api';
}
?>

<style>
#vf-root .vf-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
#vf-root .vf-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#vf-root .vf-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
#vf-root .vf-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
#vf-root .vf-span2 { grid-column: span 2; }
#vf-root .vf-span3 { grid-column: span 3; }
#vf-root .vf-hint  { font-size: 11px; color: var(--afims-text-muted, #62657E); margin-top: 3px; }
#vf-root .vf-err   { display: none; font-size: 11px; color: var(--afims-danger, #DC3545); margin-top: 3px; }

#vf-root .vf-number { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px; margin-bottom: 14px;
    border: 2px solid var(--afims-primary-soft, #E6EBF6); border-radius: 12px;
    background: linear-gradient(to right, var(--afims-primary-soft, #E6EBF6), var(--afims-surface, #fff)); }
#vf-root .vf-number small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-number strong { font-family: "IBM Plex Mono", monospace; font-size: 20px; color: var(--afims-primary-text, #0035A9); }

#vf-root .vf-vendor-card { display: none; margin-top: 10px; padding: 12px 16px; gap: 20px; flex-wrap: wrap;
    background: var(--afims-success-soft, #DBF2E5); border: 1px solid var(--afims-success-soft-hover, #C4E9D4); border-radius: 10px; }
#vf-root .vf-vendor-card.visible { display: flex; }
#vf-root .vf-vendor-card label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-success-hover, #0C7539); }
#vf-root .vf-vendor-card span { font-size: 13px; font-weight: 600; color: var(--afims-success-hover, #0C7539); }

#vf-root .vf-ledger { border-collapse: collapse; width: 100%; font-size: 13px; }
#vf-root .vf-ledger td { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-ledger td.vf-l-label { color: var(--afims-text-muted, #62657E); font-weight: 600; width: 45%; }
#vf-root .vf-ledger td.vf-l-val { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; }
#vf-root .vf-ledger tr.vf-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; }

#vf-root .vf-errors { display: none; margin-bottom: 14px; padding: 12px 16px; background: var(--afims-danger-soft, #FAE1E3);
    border: 1px solid var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 10px; }
#vf-root .vf-errors.visible { display: block; }
#vf-root .vf-errors ul { margin: 6px 0 0; padding-left: 18px; font-size: 13px; color: var(--afims-danger-text, #8F222D); line-height: 1.7; }

@media (max-width: 768px) {
    #vf-root .vf-grid3, #vf-root .vf-grid2 { grid-template-columns: 1fr; }
    #vf-root .vf-span2, #vf-root .vf-span3 { grid-column: span 1; }
}
</style>

<div id="vf-root" class="nha-root">
    <div id="vf-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="vf-modal-title">

            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="vf-modal-title">New Disbursement Voucher</h3>
                <button type="button" class="nha-modal-close" onclick="vf_close()" aria-label="Close">&times;</button>
            </div>

            <div class="nha-modal-body">

                <div class="vf-number">
                    <div><small>DV Number</small><strong id="vf-dv-number">—</strong></div>
                    <div style="text-align:right;"><small>Status</small><span class="nha-bdg nha-bdg-gray" id="vf-status">DRAFT</span></div>
                </div>

                <div class="vf-errors" id="vf-errors">
                    <strong style="font-size:13px;color:var(--afims-danger,#DC3545);">Please fix the following before saving:</strong>
                    <ul id="vf-errors-ul"></ul>
                </div>

                <!-- 1 — Document identity -->
                <div class="vf-card">
                    <p class="vf-card-title">Document Identity</p>
                    <div class="vf-grid3">
                        <div class="nha-fg">
                            <label>DV Date <span class="nha-req">*</span></label>
                            <input type="date" id="vf-dv-date">
                            <span class="vf-err" id="err-dv-date">DV Date is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Fiscal Year <span class="nha-req">*</span></label>
                            <select id="vf-fiscal-yr"><option value="">Loading…</option></select>
                            <span class="vf-err" id="err-fiscal-yr">Fiscal year is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Expense Class <span class="vf-hint" style="display:inline;">(optional, for reporting)</span></label>
                            <select id="vf-expense-class">
                                <option value="">— None —</option>
                                <option value="MOOE">MOOE</option>
                                <option value="PS">Personnel Services</option>
                                <option value="CAPITAL_OUTLAY">Capital Outlay</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 2 — RFA -->
                <div class="vf-card">
                    <p class="vf-card-title">Request for Allotment (RFA)</p>
                    <div class="nha-fg">
                        <label>RFA</label>
                        <select id="vf-rfa"><option value="">— None / manual entry —</option></select>
                        <span class="vf-hint">Picking an RFA fills in the department, vendor (if any), and accounting-entries ledger below.</span>
                    </div>
                </div>

                <!-- 3 — Claimant & particulars -->
                <div class="vf-card">
                    <p class="vf-card-title">Claimant &amp; Particulars</p>
                    <div class="vf-grid2">
                        <div class="nha-fg">
                            <label>Vendor <span class="vf-hint" style="display:inline;">(optional — autofills claimant below)</span></label>
                            <select id="vf-vendor"><option value="">— None / type claimant manually —</option></select>
                        </div>
                        <div class="nha-fg">
                            <label>PO Number <span class="vf-hint" style="display:inline;">(optional)</span></label>
                            <input type="text" id="vf-po-number" placeholder="e.g. GSD-2025-09-103">
                        </div>
                    </div>
                    <div class="vf-vendor-card" id="vf-vendor-card">
                        <div><label>Vendor Name</label><span id="vf-vc-name">—</span></div>
                        <div><label>Address</label><span id="vf-vc-address">—</span></div>
                        <div><label>TIN</label><span id="vf-vc-tin">—</span></div>
                    </div>
                    <div class="vf-grid2" style="margin-top:12px;">
                        <div class="nha-fg">
                            <label>Claimant Name <span class="nha-req">*</span></label>
                            <input type="text" id="vf-claimant" placeholder="e.g. Jackson Auto Services, or Abelar, Reena E. et al.">
                            <span class="vf-err" id="err-claimant">Claimant Name is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Address / Employee No. / TIN</label>
                            <input type="text" id="vf-claimant-address" placeholder="Address">
                            <input type="text" id="vf-claimant-tin" placeholder="Employee No. / TIN" style="margin-top:6px;">
                        </div>
                    </div>
                    <div class="nha-fg" style="margin-top:12px;">
                        <label>Particulars <span class="nha-req">*</span></label>
                        <textarea id="vf-particulars" rows="3" placeholder="To payment for…"></textarea>
                        <span class="vf-err" id="err-particulars">Particulars is required.</span>
                    </div>
                    <div class="vf-grid3" style="margin-top:12px;">
                        <div class="nha-fg">
                            <label>Amount <span class="nha-req">*</span></label>
                            <input type="number" id="vf-gross" min="0" step="0.01" placeholder="0.00">
                            <span class="vf-err" id="err-gross">Amount must be greater than zero.</span>
                        </div>
                        <div class="nha-fg">
                            <label>VAT Rate (%) / Amount <span class="vf-hint" style="display:inline;">(optional)</span></label>
                            <div style="display:flex;gap:6px;">
                                <input type="number" id="vf-vat-rate" min="0" step="0.01" placeholder="0" style="width:70px;">
                                <input type="number" id="vf-vat-amount" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                        <div class="nha-fg">
                            <label>W-Tax Rate (%) / Amount <span class="vf-hint" style="display:inline;">(optional)</span></label>
                            <div style="display:flex;gap:6px;">
                                <input type="number" id="vf-wtax-rate" min="0" step="0.01" placeholder="0" style="width:70px;">
                                <input type="number" id="vf-wtax-amount" min="0" step="0.01" placeholder="0.00">
                            </div>
                        </div>
                    </div>
                    <div class="vf-number" style="margin-top:12px;margin-bottom:0;">
                        <div><small>Net Amount</small><strong id="vf-net">₱ 0.00</strong></div>
                    </div>
                </div>

                <!-- 4 — Accounting entries -->
                <div class="vf-card" style="margin-bottom:0;">
                    <p class="vf-card-title">Accounting Entries</p>
                    <div class="vf-grid3">
                        <div class="nha-fg">
                            <label>Dept. / Project</label>
                            <input type="text" id="vf-dept-project" placeholder="e.g. GSD / RM-Motor Vehicle">
                        </div>
                        <div class="nha-fg">
                            <label>Fund Source</label>
                            <input type="text" id="vf-fund-source" placeholder="e.g. Corporate Receipts">
                        </div>
                        <div class="nha-fg">
                            <label>Account Code</label>
                            <input type="text" id="vf-account-code" placeholder="Chart of Accounts code">
                        </div>
                    </div>
                    <table class="vf-ledger" style="margin-top:10px;">
                        <tr>
                            <td class="vf-l-label">Budget</td>
                            <td><input type="number" id="vf-budget" min="0" step="0.01" placeholder="0.00"></td>
                        </tr>
                        <tr>
                            <td class="vf-l-label">Obligation</td>
                            <td><input type="number" id="vf-obligation" min="0" step="0.01" placeholder="0.00"></td>
                        </tr>
                        <tr class="vf-l-total"><td class="vf-l-label">This Request</td><td class="vf-l-val" id="vf-this-request">₱ 0.00</td></tr>
                        <tr><td class="vf-l-label">Balance</td><td class="vf-l-val" id="vf-balance">₱ 0.00</td></tr>
                    </table>
                    <div class="nha-fg" style="margin-top:12px;">
                        <label>Remarks</label>
                        <textarea id="vf-remarks" rows="2" placeholder="Optional remarks…"></textarea>
                    </div>
                </div>

            </div><!-- /.nha-modal-body -->

            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="vf_close()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-outline" id="vf-btn-draft"  onclick="vf_save('DRAFT')">Save as Draft</button>
                <button type="button" class="nha-btn nha-btn-primary" id="vf-btn-submit" onclick="vf_save('FOR_FMD_REVIEW')">Save &amp; Submit to FMD</button>
            </div>

        </div>
    </div>
</div><!-- /#vf-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   DISBURSEMENT VOUCHER FORM MODAL   prefix: vf_
═══════════════════════════════════════════════════════════════ */

const VF_API      = <?= json_encode($_vf_api) ?>;
const VF_EDITABLE = ['DRAFT', 'REJECTED'];

const $vf = id => document.getElementById(id);
let vf_id      = 0;         // 0 = add mode, otherwise the dv_id being edited
let vf_saving  = false;
let vf_vendors = {};         // vendor_id -> row, for the summary card
let vf_rfas    = {};         // allocation_id -> row
let vf_refReady = null;      // promise: fiscal years, loaded once on first open
let vf_bound   = false;

function vf_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function vf_getJson(path) {
    const res  = await vf_authFetch(VF_API + path);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

/* ── Open / close ──────────────────────────────────────────── */
async function vf_open(id = 0) {
    vf_id = Number(id) || 0;
    vf_reset();
    $vf('vf-modal-title').textContent = vf_id ? 'Edit Disbursement Voucher' : 'New Disbursement Voucher';
    $vf('vf-overlay').classList.add('open');

    if (!vf_bound) { vf_bindEvents(); vf_bound = true; }

    window.PagePreloader?.show();
    try {
        vf_refReady ??= vf_loadFiscalYears();
        await Promise.all([vf_refReady, vf_loadVendors(), vf_loadRfaOptions()]);

        if (vf_id) {
            await vf_loadExisting();
        } else {
            $vf('vf-fiscal-yr').value = [...$vf('vf-fiscal-yr').options].some(o => o.value === String(new Date().getFullYear()))
                ? String(new Date().getFullYear()) : '';
            vf_refreshDvNumber();
        }
        vf_recalc();
    } catch (e) {
        vf_showErrors([e.message]);
    } finally {
        window.PagePreloader?.hide();
    }
}

function vf_close() {
    if (vf_saving) return;
    $vf('vf-overlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $vf('vf-overlay')?.classList.contains('open')) vf_close();
});

function vf_reset() {
    vf_showErrors([]);
    ['dv-date', 'fiscal-yr', 'claimant', 'particulars', 'gross'].forEach(vf_clearFieldError);
    $vf('vf-dv-date').value       = new Date().toISOString().slice(0, 10);
    $vf('vf-fiscal-yr').value     = '';
    $vf('vf-expense-class').value = '';
    $vf('vf-rfa').value           = '';

    $vf('vf-vendor').value = '';
    $vf('vf-vendor-card').classList.remove('visible');
    $vf('vf-po-number').value = '';
    $vf('vf-claimant').value = '';
    $vf('vf-claimant-address').value = '';
    $vf('vf-claimant-tin').value = '';
    $vf('vf-particulars').value = '';
    $vf('vf-gross').value = '';
    $vf('vf-vat-rate').value = '';
    $vf('vf-wtax-rate').value = '';
    $vf('vf-vat-amount').value = '';
    $vf('vf-wtax-amount').value = '';

    $vf('vf-dept-project').value = '';
    $vf('vf-fund-source').value = '';
    $vf('vf-account-code').value = '';
    $vf('vf-budget').value = '';
    $vf('vf-obligation').value = '';
    $vf('vf-remarks').value = '';

    $vf('vf-dv-number').textContent = '—';
    $vf('vf-status').textContent    = 'DRAFT';
    $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = false;
}

function vf_bindEvents() {
    $vf('vf-dv-date').addEventListener('change', () => { if (!vf_id) vf_refreshDvNumber(); });
    $vf('vf-rfa').addEventListener('change', vf_onRfaChange);
    $vf('vf-vendor').addEventListener('change', vf_onVendorChange);
    ['vf-gross', 'vf-vat-rate', 'vf-wtax-rate', 'vf-vat-amount', 'vf-wtax-amount'].forEach(id => $vf(id).addEventListener('input', () => vf_recalc(true)));
    $vf('vf-obligation').addEventListener('input', () => vf_recalc());
}

/* ── Reference data ────────────────────────────────────────── */
async function vf_loadFiscalYears() {
    const sel = $vf('vf-fiscal-yr');
    try {
        const json = await vf_getJson('/voucher/fiscal-years');
        sel.innerHTML = '<option value="">Select fiscal year</option>' + (json.data ?? []).map(r =>
            `<option value="${vf_esc(r.fiscal_yr)}">${vf_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load</option>';
        vf_refReady = null;
        throw new Error('Failed to load fiscal years: ' + e.message);
    }
}

async function vf_loadVendors() {
    const sel = $vf('vf-vendor');
    try {
        const json = await vf_getJson('/vendors/all?active=1');
        vf_vendors = {};
        (json.data ?? []).forEach(v => { vf_vendors[v.vendor_id] = v; });
        sel.innerHTML = '<option value="">— None / type claimant manually —</option>' + (json.data ?? []).map(v =>
            `<option value="${vf_esc(v.vendor_id)}">${vf_esc(v.vendor_code)} — ${vf_esc(v.vendor_name)}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load vendors</option>';
        throw new Error('Failed to load vendors: ' + e.message);
    }
}

async function vf_loadRfaOptions() {
    const sel = $vf('vf-rfa');
    try {
        // exclude_dv_id: an RFA already used by THIS voucher (editing) still shows as available to it;
        // the server leaves out any RFA used by a different, still-active voucher entirely.
        const qs   = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const json = await vf_getJson(`/voucher/rfa/all${qs}`);
        vf_rfas = {};
        (json.data ?? []).forEach(r => { vf_rfas[r.allocation_id] = r; });
        sel.innerHTML = '<option value="">— None / manual entry —</option>' + (json.data ?? []).map(r =>
            `<option value="${vf_esc(r.allocation_id)}">${vf_esc(r.allocation_number)} — ${vf_esc(r.department_name || 'Unassigned Dept.')} (FY ${vf_esc(r.fiscal_year)})</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load RFAs</option>';
        throw new Error('Failed to load RFAs: ' + e.message);
    }
}

function vf_onVendorChange() {
    const v = vf_vendors[$vf('vf-vendor').value];
    const card = $vf('vf-vendor-card');
    if (!v) { card.classList.remove('visible'); return; }
    $vf('vf-vc-name').textContent    = v.vendor_name || '—';
    $vf('vf-vc-address').textContent = v.address || '—';
    $vf('vf-vc-tin').textContent     = v.tin || '—';
    card.classList.add('visible');
    // Vendor fields win over whatever was typed manually, so the claimant always matches the vendor on file.
    $vf('vf-claimant').value         = v.vendor_name || '';
    $vf('vf-claimant-address').value = v.address || '';
    $vf('vf-claimant-tin').value     = v.tin || '';
    vf_clearFieldError('claimant');
}

/* ── RFA autofill ──────────────────────────────────────────── */
async function vf_onRfaChange() {
    const id = $vf('vf-rfa').value;
    if (!id) { vf_recalc(); return; }

    window.PagePreloader?.show();
    try {
        const qs   = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const json = await vf_getJson(`/voucher/rfa/read/${id}${qs}`);
        const rfa  = json.data;

        $vf('vf-dept-project').value = rfa.department_name || '';
        $vf('vf-budget').value       = rfa.budget_amount || '';
        $vf('vf-obligation').value   = rfa.budget_amount || '';

        if (rfa.po_number) $vf('vf-po-number').value = rfa.po_number;

        if (rfa.vendor_id) {
            if (!vf_vendors[rfa.vendor_id]) {
                vf_vendors[rfa.vendor_id] = { vendor_id: rfa.vendor_id, vendor_name: rfa.vendor_name, address: rfa.vendor_address, tin: rfa.vendor_tin };
                $vf('vf-vendor').insertAdjacentHTML('beforeend',
                    `<option value="${vf_esc(rfa.vendor_id)}">${vf_esc(rfa.vendor_name)}</option>`);
            }
            $vf('vf-vendor').value = rfa.vendor_id;
            vf_onVendorChange();
        }
    } catch (e) {
        vf_showErrors(['Could not load RFA: ' + e.message]);
    } finally {
        window.PagePreloader?.hide();
        vf_recalc();
    }
}

async function vf_refreshDvNumber() {
    const d = $vf('vf-dv-date').value;
    if (!d) return;
    try {
        const json = await vf_getJson('/voucher/dv-number?dv_date=' + encodeURIComponent(d));
        $vf('vf-dv-number').textContent = json.data.dv_no;
    } catch (e) { /* the server assigns a number on save if this stays "—" */ }
}

/* ── Live computation ──────────────────────────────────────── */
function vf_recalc(autoCalc = false) {
    const gross = parseFloat($vf('vf-gross').value) || 0;
    const vatRate  = parseFloat($vf('vf-vat-rate').value) || 0;
    const wtaxRate = parseFloat($vf('vf-wtax-rate').value) || 0;

    // Only live user input (autoCalc) recomputes VAT/W-Tax from the rate; loading an existing
    // voucher or an RFA just redraws totals from whatever is already in the fields, so a
    // manually-typed/saved amount is never silently overwritten.
    const active = document.activeElement && document.activeElement.id;
    if (autoCalc && active !== 'vf-vat-amount' && vatRate > 0)  $vf('vf-vat-amount').value  = gross > 0 ? (gross * vatRate / 100).toFixed(2) : '';
    if (autoCalc && active !== 'vf-wtax-amount' && wtaxRate > 0) $vf('vf-wtax-amount').value = gross > 0 ? (gross * wtaxRate / 100).toFixed(2) : '';
    if (!autoCalc && gross > 0 && vatRate > 0 && $vf('vf-vat-amount').value === '')   $vf('vf-vat-amount').value  = (gross * vatRate / 100).toFixed(2);
    if (!autoCalc && gross > 0 && wtaxRate > 0 && $vf('vf-wtax-amount').value === '') $vf('vf-wtax-amount').value = (gross * wtaxRate / 100).toFixed(2);

    const vatAmt  = parseFloat($vf('vf-vat-amount').value) || 0;
    const wtaxAmt = parseFloat($vf('vf-wtax-amount').value) || 0;
    const netAmount = Math.max(0, gross - vatAmt - wtaxAmt);
    $vf('vf-net').textContent = vf_peso(netAmount);

    const obligation = parseFloat($vf('vf-obligation').value) || 0;
    const balance = obligation - netAmount;

    $vf('vf-this-request').textContent = vf_peso(netAmount);
    $vf('vf-balance').textContent      = vf_peso(balance);
}

/* ── Edit mode ─────────────────────────────────────────────── */
async function vf_loadExisting() {
    const json = await vf_getJson('/voucher/read/' + vf_id);
    const dv   = json.data;
    if (!VF_EDITABLE.includes(dv.status)) {
        vf_showErrors([`This disbursement voucher is ${dv.status} and can no longer be edited.`]);
        $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = true;
    }

    $vf('vf-dv-number').textContent = dv.dv_no;
    $vf('vf-status').textContent    = dv.status;
    $vf('vf-dv-date').value         = String(dv.dv_date).slice(0, 10);
    $vf('vf-fiscal-yr').value       = dv.fiscal_yr;
    $vf('vf-expense-class').value   = dv.expense_class || '';
    $vf('vf-rfa').value             = dv.rfa_allocation_id || '';

    if (dv.vendor_id && !vf_vendors[dv.vendor_id]) {
        vf_vendors[dv.vendor_id] = { vendor_id: dv.vendor_id, vendor_name: dv.claimant_name, address: dv.claimant_address, tin: dv.employee_no_tin };
        $vf('vf-vendor').insertAdjacentHTML('beforeend',
            `<option value="${vf_esc(dv.vendor_id)}">${vf_esc(dv.claimant_name)} (inactive)</option>`);
    }
    $vf('vf-vendor').value = dv.vendor_id || '';
    vf_onVendorChange();

    $vf('vf-po-number').value        = dv.po_number || '';
    $vf('vf-claimant').value         = dv.claimant_name || '';
    $vf('vf-claimant-address').value = dv.claimant_address || '';
    $vf('vf-claimant-tin').value     = dv.employee_no_tin || '';
    $vf('vf-particulars').value      = dv.particulars || '';
    $vf('vf-gross').value            = dv.gross_amount || '';
    $vf('vf-vat-rate').value         = dv.vat_rate || '';
    $vf('vf-vat-amount').value       = dv.vat_amount || '';
    $vf('vf-wtax-rate').value        = dv.wtax_rate || '';
    $vf('vf-wtax-amount').value      = dv.wtax_amount || '';

    $vf('vf-dept-project').value = dv.dept_project || '';
    $vf('vf-fund-source').value  = dv.fund_source || '';
    $vf('vf-account-code').value = dv.account_code || '';
    $vf('vf-budget').value       = dv.budget_amount || '';
    $vf('vf-obligation').value   = dv.obligation_amount || '';
    $vf('vf-remarks').value      = dv.remarks || '';
}

/* ── Validation ────────────────────────────────────────────── */
function vf_setFieldError(field, show) {
    const err = $vf(`err-${field}`);
    if (err) err.style.display = show ? 'block' : 'none';
}
function vf_clearFieldError(field) { vf_setFieldError(field, false); }

function vf_validate() {
    const errors = [];
    const check = (ok, field, msg) => { vf_setFieldError(field, !ok); if (!ok) errors.push(msg); };

    check(!!$vf('vf-dv-date').value,   'dv-date',   'DV Date is required.');
    check(!!$vf('vf-fiscal-yr').value, 'fiscal-yr', 'Fiscal Year is required.');
    check(!!$vf('vf-claimant').value.trim(), 'claimant', 'Claimant Name is required.');
    check(!!$vf('vf-particulars').value.trim(), 'particulars', 'Particulars is required.');
    check((parseFloat($vf('vf-gross').value) || 0) > 0, 'gross', 'Amount must be greater than zero.');

    return errors;
}

function vf_showErrors(list) {
    const ul = $vf('vf-errors-ul');
    ul.innerHTML = '';
    list.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
    $vf('vf-errors').classList.toggle('visible', list.length > 0);
    if (list.length) $vf('vf-errors').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── Save ──────────────────────────────────────────────────── */
async function vf_save(status) {
    if (vf_saving) return;
    vf_showErrors([]);

    const errors = vf_validate();
    if (errors.length) { vf_showErrors(errors); return; }

    const dvNumber = $vf('vf-dv-number').textContent.trim();
    const body = {
        dv_no              : vf_id || dvNumber === '—' ? undefined : dvNumber,
        dv_date            : $vf('vf-dv-date').value,
        fiscal_yr          : $vf('vf-fiscal-yr').value,
        expense_class      : $vf('vf-expense-class').value,
        rfa_allocation_id  : $vf('vf-rfa').value || null,
        dept_project       : $vf('vf-dept-project').value.trim(),
        fund_source        : $vf('vf-fund-source').value.trim(),
        account_code       : $vf('vf-account-code').value.trim(),
        budget_amount      : parseFloat($vf('vf-budget').value) || 0,
        obligation_amount  : parseFloat($vf('vf-obligation').value) || 0,
        remarks            : $vf('vf-remarks').value.trim(),
        vendor_id          : $vf('vf-vendor').value || null,
        claimant_name      : $vf('vf-claimant').value.trim(),
        claimant_address   : $vf('vf-claimant-address').value.trim(),
        employee_no_tin    : $vf('vf-claimant-tin').value.trim(),
        po_number          : $vf('vf-po-number').value.trim(),
        particulars        : $vf('vf-particulars').value.trim(),
        gross_amount       : parseFloat($vf('vf-gross').value) || 0,
        vat_rate           : parseFloat($vf('vf-vat-rate').value) || 0,
        vat_amount         : parseFloat($vf('vf-vat-amount').value) || 0,
        wtax_rate          : parseFloat($vf('vf-wtax-rate').value) || 0,
        wtax_amount        : parseFloat($vf('vf-wtax-amount').value) || 0,
        status,
    };

    vf_saving = true;
    $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = true;
    window.PagePreloader?.show();
    let saved = null;
    try {
        const res  = await vf_authFetch(vf_id ? `${VF_API}/voucher/update/${vf_id}` : `${VF_API}/voucher/add`, {
            method  : vf_id ? 'PUT' : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) {
            vf_showErrors(json.errors?.length ? json.errors : [json.message || 'Failed to save disbursement voucher.']);
            return;
        }
        saved = json;
    } catch (e) {
        vf_showErrors(['Could not save: ' + e.message]);
        return;
    } finally {
        vf_saving = false;
        window.PagePreloader?.hide();
        $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = false;
    }

    vf_close();
    if (typeof window.vf_onSaved === 'function') window.vf_onSaved(saved.data, saved.message);
}

/* ── Helpers ───────────────────────────────────────────────── */
function vf_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function vf_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
