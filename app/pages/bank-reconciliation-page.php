<?php
/**
 * Bank Reconciliation — Reports module.
 *
 * Listing (Bank Name / Account No / Account Name / Balance / Status) with a
 * "+ Bank Recon" button that opens the reconciliation worksheet modal: Bank +
 * "as of" Month, then Per Book vs Per Bank, each with its own Add/Less
 * adjustments, down to an Adjusted Balance. Reconciled when both adjusted
 * balances match (see app/services/BankReconciliationService.php).
 *
 * Follows the same lightweight fetch + manual render convention as
 * app/pages/bank-management-page.php (no DataTables dependency). Markup/CSS
 * classes match app/fims-css/fims.css.
 */

namespace PHPMaker2024\FIMS;

$brCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $brCssVer ?>" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div class="fims-wrap" id="brModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-money-check-dollar"></i></div>
            <h1>Bank Reconciliation</h1>
            <p>Reconcile each bank account's book balance against the bank balance, month by month</p>
        </div>
        <div class="fims-page-header-right">
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="brAddBtn">
                <i class="fas fa-plus mr-1"></i>Bank Recon
            </button>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
        <div class="fims-toolbar">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <select class="fims-select fims-input-sm" id="brFilterStatus" style="width:160px">
                    <option value="">All Statuses</option>
                    <option value="RECONCILED">Reconciled</option>
                    <option value="UNRECONCILED">Unreconciled</option>
                    <option value="PENDING">Not Yet Reconciled</option>
                </select>
                <input type="text" class="fims-input fims-input-sm" id="brSearch" placeholder="Search bank, account no., name…" style="width:240px">
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="brFilterBtn"><i class="fas fa-filter mr-1"></i>Filter</button>
                <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="brResetBtn">Reset</button>
            </div>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:0">
        <div class="fims-panel-header">
            <div class="fims-panel-title" id="brTableSubtitle">Bank Reconciliation Listing</div>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table" id="brTable">
                <thead>
                    <tr>
                        <th>Bank Name</th>
                        <th>Account No.</th>
                        <th>Account Name</th>
                        <th>As of</th>
                        <th class="text-right">Balance</th>
                        <th class="text-center">Status</th>
                        <th class="text-center" style="width:140px">Actions</th>
                    </tr>
                </thead>
                <tbody id="brTableBody"><tr class="fims-empty"><td colspan="7">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Bank Reconciliation worksheet
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-br-recon" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:920px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Bank Reconciliation</div></div>
                    <button class="fims-modal-close" data-close="m-br-recon">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-form-grid" style="display:grid;grid-template-columns:2fr 1fr;gap:12px;margin-bottom:14px;">
                        <div class="fims-form-group">
                            <label class="fims-label">Bank</label>
                            <select class="fims-select" id="field-bank-account-id">
                                <option value="">— Select —</option>
                            </select>
                        </div>
                        <div class="fims-form-group">
                            <label class="fims-label">As of (Month)</label>
                            <input type="month" class="fims-input" id="field-period">
                        </div>
                    </div>

                    <div class="br-recon-grid">
                        <!-- PER BOOK -->
                        <div class="br-recon-col">
                            <div class="br-recon-col-title">PER BOOK</div>
                            <div class="fims-form-group">
                                <label class="fims-label">Book Balance <span class="br-hint">(auto-filled, SL balance)</span></label>
                                <input type="text" class="fims-input br-mono" id="field-book-balance" readonly>
                            </div>
                            <div class="br-recon-sub">Add:</div>
                            <div class="fims-form-group"><label class="fims-label">Unrecorded Deposit</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-unrecorded-deposit" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Unreleased Check</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-unreleased-check" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Credit Memo</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-credit-memo" value="0"></div>
                            <div class="fims-form-group"><label class="fims-label">Interest Income</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-interest-income" value="0"></div>
                            <div class="br-recon-sub">Less:</div>
                            <div class="fims-form-group"><label class="fims-label">Debit Memo</label><input type="number" step="0.01" class="fims-input br-mono" id="field-less-debit-memo" value="0"></div>
                            <div class="br-recon-total">
                                <span>Adjusted Balance</span>
                                <span class="br-mono" id="calc-adjusted-book">0.00</span>
                            </div>
                        </div>

                        <!-- PER BANK -->
                        <div class="br-recon-col">
                            <div class="br-recon-col-title">PER BANK</div>
                            <div class="fims-form-group">
                                <label class="fims-label">Bank Balance</label>
                                <input type="number" step="0.01" class="fims-input br-mono" id="field-bank-balance" value="0">
                            </div>
                            <div class="br-recon-sub">Add:</div>
                            <div class="fims-form-group"><label class="fims-label">Deposit in Transit</label><input type="number" step="0.01" class="fims-input br-mono" id="field-add-deposit-in-transit" value="0"></div>
                            <div class="br-recon-sub">Less:</div>
                            <div class="fims-form-group"><label class="fims-label">Outstanding Check</label><input type="number" step="0.01" class="fims-input br-mono" id="field-less-outstanding-check" value="0"></div>
                            <div class="br-recon-total">
                                <span>Adjusted Balance</span>
                                <span class="br-mono" id="calc-adjusted-bank">0.00</span>
                            </div>
                        </div>
                    </div>

                    <div id="brStatusBanner" class="br-status-banner" style="display:none;"></div>

                    <div class="fims-form-group" style="margin-top:12px;">
                        <label class="fims-label">Remarks</label>
                        <textarea class="fims-input" id="field-remarks" rows="2" placeholder="Optional notes…"></textarea>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-br-recon">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="brSaveBtn">Save Reconciliation</button>
                </div>
            </div>
        </div>
    </div>

    <div id="brToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>
</div>

<style>
#brModule .fims-card-blue, #brModule .fims-card-green, #brModule .fims-card-amber,
#brModule .fims-card-orange, #brModule .fims-card-gray { --fims-card-color: #000E3E; }
#brModule .fims-btn-primary { background:#000E3E; color:#fff; }
#brModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#brModule .fims-modal-content { display:flex; flex-direction:column; min-height:0; overflow:hidden; }

#brModule .br-recon-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
#brModule .br-recon-col { border:1px solid var(--fims-border,#E4ECF2); border-radius:8px; padding:12px; }
#brModule .br-recon-col-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--fims-text-subtle); margin-bottom:10px; text-align:center; }
#brModule .br-recon-sub { font-size:11px; font-weight:600; color:var(--fims-text-subtle); margin:8px 0 4px; }
#brModule .br-mono { font-family:var(--afims-mono, ui-monospace, 'IBM Plex Mono', monospace); text-align:right; }
#brModule .br-hint { font-weight:400; font-size:10px; color:var(--fims-text-subtle); text-transform:none; letter-spacing:0; }
#brModule .br-recon-total { display:flex; justify-content:space-between; align-items:center; margin-top:10px; padding-top:8px; border-top:2px solid #000E3E; font-weight:700; font-size:13px; }
#brModule .br-status-banner { margin-top:14px; padding:10px 14px; border-radius:6px; font-size:13px; font-weight:600; text-align:center; }
#brModule .br-status-banner.reconciled { background:#E6F6EC; color:#157347; border:1px solid #B7E4C7; }
#brModule .br-status-banner.unreconciled { background:#FDECEC; color:#B42318; border:1px solid #F3C6C6; }

html.afims-dark[data-bs-theme="dark"] #brModule { color-scheme:dark; }
html.afims-dark[data-bs-theme="dark"] #brModule .fims-btn-primary { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #brModule .fims-btn-primary:hover { background:#2E59B8; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #brModule :is(select.fims-select, .fims-input, textarea.fims-input) {
    background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-recon-col { border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #brModule .br-status-banner.reconciled { background:#123524; color:#8FE3B3; border-color:#1F5A3B; }
html.afims-dark[data-bs-theme="dark"] #brModule .br-status-banner.unreconciled { background:#3A1414; color:#F5A3A3; border-color:#6B2222; }
</style>

<script>
(function () {
    const API = '/api/bank-reconciliation';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function jsonHeaders(extra) {
        return authHeaders(Object.assign({ 'Content-Type': 'application/json' }, extra || {}));
    }

    function toast(msg, isError) {
        const el = document.getElementById('brToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function peso(v) {
        const n = Number(v || 0);
        return n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#brModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.style.display = 'none'; });
    });

    function statusBadge(status) {
        if (status === 'RECONCILED') return '<span class="fims-badge fims-badge-green">Reconciled</span>';
        if (status === 'UNRECONCILED') return '<span class="fims-badge fims-badge-red">Unreconciled</span>';
        return '<span class="fims-badge fims-badge-gray">Not Yet Reconciled</span>';
    }

    // ── Bank dropdown ──
    let bankAccounts = [];
    function loadBankAccounts() {
        return fetch(API + '/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                bankAccounts = (json && json.data) ? json.data : [];
                const sel = document.getElementById('field-bank-account-id');
                sel.innerHTML = '<option value="">— Select —</option>' +
                    bankAccounts.map(a => '<option value="' + a.bank_account_id + '">' + escapeHtml(a.bank_name + ' — ' + a.account_name + ' (' + (a.account_number || '') + ')') + '</option>').join('');
            })
            .catch(() => {});
    }

    // ── Table ──
    function buildListParams() {
        const params = new URLSearchParams();
        const status = document.getElementById('brFilterStatus').value;
        const search = document.getElementById('brSearch').value.trim();
        if (status) params.set('status', status);
        if (search) params.set('search', search);
        return params;
    }

    function loadList() {
        const tbody = document.getElementById('brTableBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        fetch(API + '/list?' + buildListParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                document.getElementById('brTableSubtitle').textContent = 'Bank Reconciliation Listing (' + rows.length + ')';
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">No bank accounts found.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    const asOf = r.as_of_date ? r.as_of_date : '—';
                    const balance = r.adjusted_bank_balance != null ? r.adjusted_bank_balance : (r.adjusted_book_balance != null ? r.adjusted_book_balance : null);
                    return '<tr>' +
                        '<td>' + escapeHtml(r.bank_name || '') + '</td>' +
                        '<td class="fims-col-mono">' + escapeHtml(r.account_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.account_name || '') + '</td>' +
                        '<td>' + escapeHtml(asOf) + '</td>' +
                        '<td class="text-right br-mono">' + (balance != null ? peso(balance) : '—') + '</td>' +
                        '<td class="text-center">' + statusBadge(r.status) + '</td>' +
                        '<td class="text-center"><button type="button" class="fims-btn fims-btn-outline fims-btn-sm" data-recon="' + r.bank_account_id + '" data-recon-id="' + (r.reconciliation_id || '') + '"><i class="fas fa-scale-balanced mr-1"></i>' + (r.reconciliation_id ? 'Edit' : 'Reconcile') + '</button></td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-recon]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openReconModal(this.dataset.recon, this.dataset.reconId || null); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Could not reach the server.</td></tr>'; });
    }

    document.getElementById('brFilterBtn').addEventListener('click', loadList);
    document.getElementById('brResetBtn').addEventListener('click', function () {
        document.getElementById('brFilterStatus').value = '';
        document.getElementById('brSearch').value = '';
        loadList();
    });
    document.getElementById('brSearch').addEventListener('keydown', function (e) { if (e.key === 'Enter') loadList(); });

    // ── Reconciliation modal ──
    const ADD_FIELD_IDS = ['field-add-unrecorded-deposit', 'field-add-unreleased-check', 'field-add-credit-memo', 'field-add-interest-income', 'field-less-debit-memo', 'field-bank-balance', 'field-add-deposit-in-transit', 'field-less-outstanding-check'];

    function clearReconModal() {
        document.getElementById('field-bank-account-id').value = '';
        document.getElementById('field-period').value = '';
        document.getElementById('field-book-balance').value = '';
        ADD_FIELD_IDS.forEach(id => document.getElementById(id).value = '0');
        document.getElementById('field-remarks').value = '';
        document.getElementById('brStatusBanner').style.display = 'none';
        recompute();
    }

    function recompute() {
        const bookBalance = Number(document.getElementById('field-book-balance').value) || 0;
        const addDep = Number(document.getElementById('field-add-unrecorded-deposit').value) || 0;
        const addChk = Number(document.getElementById('field-add-unreleased-check').value) || 0;
        const addCm = Number(document.getElementById('field-add-credit-memo').value) || 0;
        const addInt = Number(document.getElementById('field-add-interest-income').value) || 0;
        const lessDm = Number(document.getElementById('field-less-debit-memo').value) || 0;
        const adjBook = bookBalance + addDep + addChk + addCm + addInt - lessDm;

        const bankBalance = Number(document.getElementById('field-bank-balance').value) || 0;
        const addDit = Number(document.getElementById('field-add-deposit-in-transit').value) || 0;
        const lessOc = Number(document.getElementById('field-less-outstanding-check').value) || 0;
        const adjBank = bankBalance + addDit - lessOc;

        document.getElementById('calc-adjusted-book').textContent = peso(adjBook);
        document.getElementById('calc-adjusted-bank').textContent = peso(adjBank);

        const banner = document.getElementById('brStatusBanner');
        const reconciled = Math.abs(adjBook - adjBank) < 0.01;
        banner.style.display = 'block';
        banner.className = 'br-status-banner ' + (reconciled ? 'reconciled' : 'unreconciled');
        banner.innerHTML = reconciled
            ? '<i class="fas fa-check-circle mr-1"></i>Reconciled — both adjusted balances match.'
            : '<i class="fas fa-triangle-exclamation mr-1"></i>Unreconciled — difference of ' + peso(Math.abs(adjBook - adjBank)) + '.';

        return { adjBook, adjBank };
    }
    document.querySelectorAll('#brModule .br-recon-col input[type="number"]').forEach(function (inp) {
        inp.addEventListener('input', recompute);
    });

    function loadBookBalance(bankAccountId, period) {
        if (!bankAccountId || !period) return;
        const asOfDate = period + '-01';
        const d = new Date(asOfDate);
        const lastDay = new Date(d.getFullYear(), d.getMonth() + 1, 0).toISOString().slice(0, 10);
        fetch(API + '/book-balance?bank_account_id=' + encodeURIComponent(bankAccountId) + '&as_of_date=' + lastDay, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (json && json.success && json.data && json.data.book_balance !== null) {
                    document.getElementById('field-book-balance').value = json.data.book_balance;
                    document.getElementById('field-book-balance').readOnly = true;
                } else {
                    document.getElementById('field-book-balance').value = '0';
                    document.getElementById('field-book-balance').readOnly = false;
                    if (json && json.data && json.data.message) toast(json.data.message, false);
                }
                recompute();
            })
            .catch(() => {});
    }
    document.getElementById('field-bank-account-id').addEventListener('change', function () {
        loadBookBalance(this.value, document.getElementById('field-period').value);
    });
    document.getElementById('field-period').addEventListener('change', function () {
        loadBookBalance(document.getElementById('field-bank-account-id').value, this.value);
    });

    function fillReconFields(d) {
        document.getElementById('field-bank-account-id').value = d.bank_account_id;
        document.getElementById('field-period').value = String(d.period_year) + '-' + String(d.period_month).padStart(2, '0');
        document.getElementById('field-book-balance').value = d.book_balance;
        document.getElementById('field-book-balance').readOnly = true;
        document.getElementById('field-add-unrecorded-deposit').value = d.add_unrecorded_deposit;
        document.getElementById('field-add-unreleased-check').value = d.add_unreleased_check;
        document.getElementById('field-add-credit-memo').value = d.add_credit_memo;
        document.getElementById('field-add-interest-income').value = d.add_interest_income;
        document.getElementById('field-less-debit-memo').value = d.less_debit_memo;
        document.getElementById('field-bank-balance').value = d.bank_balance;
        document.getElementById('field-add-deposit-in-transit').value = d.add_deposit_in_transit;
        document.getElementById('field-less-outstanding-check').value = d.less_outstanding_check;
        document.getElementById('field-remarks').value = d.remarks || '';
        recompute();
    }

    // bankAccountId: pre-select the Bank dropdown (new or edit alike).
    // reconciliationId: when given (the row already has a saved reconciliation), loads and
    // pre-fills EVERY saved field — otherwise "Edit" would silently reset Bank Balance and every
    // adjustment back to 0, discarding what was entered before.
    function openReconModal(bankAccountId, reconciliationId) {
        clearReconModal();
        if (reconciliationId) {
            fetch(API + '/' + reconciliationId, { headers: authHeaders() })
                .then(r => r.json())
                .then(function (json) {
                    if (!json || !json.success) { toast((json && json.message) || 'Could not load this reconciliation.', true); return; }
                    fillReconFields(json.data);
                    openModal('m-br-recon');
                })
                .catch(() => toast('Could not reach the server.', true));
            return;
        }
        if (bankAccountId) {
            document.getElementById('field-bank-account-id').value = bankAccountId;
            const now = new Date();
            document.getElementById('field-period').value = now.toISOString().slice(0, 7);
            loadBookBalance(bankAccountId, document.getElementById('field-period').value);
        }
        openModal('m-br-recon');
    }
    document.getElementById('brAddBtn').addEventListener('click', function () { openReconModal(null, null); });

    document.getElementById('brSaveBtn').addEventListener('click', function () {
        const bankAccountId = document.getElementById('field-bank-account-id').value;
        const period = document.getElementById('field-period').value;
        if (!bankAccountId) { toast('Select a Bank.', true); return; }
        if (!period) { toast('Select the reconciliation month.', true); return; }
        const [year, month] = period.split('-').map(Number);

        const payload = {
            bank_account_id: bankAccountId,
            period_year: year,
            period_month: month,
            book_balance: document.getElementById('field-book-balance').value,
            add_unrecorded_deposit: document.getElementById('field-add-unrecorded-deposit').value,
            add_unreleased_check: document.getElementById('field-add-unreleased-check').value,
            add_credit_memo: document.getElementById('field-add-credit-memo').value,
            add_interest_income: document.getElementById('field-add-interest-income').value,
            less_debit_memo: document.getElementById('field-less-debit-memo').value,
            bank_balance: document.getElementById('field-bank-balance').value,
            add_deposit_in_transit: document.getElementById('field-add-deposit-in-transit').value,
            less_outstanding_check: document.getElementById('field-less-outstanding-check').value,
            remarks: document.getElementById('field-remarks').value.trim(),
        };

        this.disabled = true;
        fetch(API + '/save', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Save failed.', true); return; }
                toast('Bank reconciliation saved.');
                closeModal('m-br-recon');
                loadList();
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { document.getElementById('brSaveBtn').disabled = false; });
    });

    // ── Boot ──
    loadBankAccounts();
    loadList();
})();
</script>
