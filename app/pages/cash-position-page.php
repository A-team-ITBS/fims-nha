<?php
/**
 * Cash Position — Treasury module.
 *
 * Single section now: Position Updates (cashpositionupdates.php). Three
 * other tabs that originally shipped here were removed along the way:
 *   - Dashboard (cashpositiondashboard.php) — its KPIs, breakdown chart,
 *     grouped balance table, Working Paper summary, account-detail modal,
 *     and every dash*-prefixed function/element went with it.
 *   - Available Cash Balances (availablecashbalancestracking.php) — its
 *     KPIs, Book→Available→Usable table, low-balance alerts banner, and
 *     the Balance Trend modal went with it.
 *   - Reports (cashposreports.php) — every report type, the report
 *     filters, and the New FY Opening Balance modal went with it.
 * With no tabs left to switch between, the #cpsTabNav switcher is gone
 * too — Position Updates just renders directly.
 *
 * See app/services/CashPositionService.php's docblock for the schema
 * adaptation notes (no shared.fund_sources — fund_source is plain text on
 * fims.bank_accounts; "Region/Office" is fims.responsibility_code, not
 * uac.departments; no transaction ledger to auto-sum receipts/
 * disbursements, so those are plain editable fields; no WEEKLY
 * aggregation).
 *
 * Markup/CSS classes match app/fims-css/fims.css, same dark-blue scoped
 * theme convention as check-disbursement-voucher-page.php (#cpModule) and
 * bank-management-page.php (#bamModule).
 */

namespace PHPMaker2024\FIMS;

$cpsCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $cpsCssVer ?>" />

<div class="fims-wrap" id="cashPositionModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-wallet"></i></div>
            <h1>Cash Position</h1>
            <p>Daily cash position and manual updates — Treasury Department</p>
        </div>
    </div>

    <div class="fims-tab-underline-content active" id="cpsUpdates">
        <div class="fims-stat-grid" id="updKpis" style="grid-template-columns:repeat(3,1fr)">
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Active Accounts</span></div><div class="fims-stat-value" data-kpi="total_accounts">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Total Available Balance</span></div><div class="fims-stat-value" data-kpi="total_ending">₱0.00</div></div>
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Available Beginning Balance</span></div><div class="fims-stat-value" data-kpi="total_opening">₱0.00</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="updFilterBank" style="width:190px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="updFilterFundSource" style="width:170px"><option value="">All Fund Sources</option></select>
                    <input type="date" class="fims-input fims-input-sm" id="updFilterDate" title="Position Date (blank = each account's latest)">
                    <input type="text" class="fims-input fims-input-sm" id="updFilterSearch" placeholder="Account name / number / bank" style="width:220px">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="updFilterBtn"><i class="fas fa-filter mr-1"></i>Apply</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="updResetBtn">Reset</button>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Cash Position Records</div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="updTable">
                    <thead>
                        <tr>
                            <th>Bank Account</th><th>Date</th>
                            <th class="text-right">Available Beginning Balance</th><th class="text-right">+ Receipts</th><th class="text-right">− Disbursements</th>
                            <th class="text-right">Closing Balance</th>
                            <th class="text-right">Available Balance</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="8">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="fims-table-panel" style="margin-top:14px;">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Recent Update Activity</div>
            </div>
            <ul id="updActivityList" style="list-style:none;padding:12px 16px;margin:0;font-size:13px;max-height:320px;overflow-y:auto;"></ul>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Cash Position Entry — two entry points:
           - openAddEntryModal(bankAccountId): a bank account with NO entry
             yet (Position Updates' "Add" action on a "No entry yet" row).
             Position Date is editable (defaults to today) and Beginning
             Balance is computed live as it changes.
           - openManualEntryModal(entryId): an EXISTING entry (Position
             Updates' "Edit" action). Position Date is locked to the
             existing row and only Restricted Funds is actually editable.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-manual-entry" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:480px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="meModalTitle">Cash Position Entry</div></div>
                    <button class="fims-modal-close" data-close="m-manual-entry">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="meCashPositionId">

                    <!-- Position Date, Beginning Balance, Bank Statement Balance,
                         Total Receipts, and Total Disbursements: all computed/
                         defaulted automatically (today's date; prior closing
                         balance or this account's GL balance; the posted
                         transaction ledger) and carried in the Save payload,
                         but never shown or hand-typed — only Restricted Funds
                         below is actually editable, for a brand-new entry and
                         an existing one alike. -->
                    <div style="display:none;">
                        <input type="date" id="mePositionDate">
                        <input type="number" step="0.01" id="meOpeningBalance">
                        <span id="meOpeningHint"></span>
                        <input type="number" step="0.01" id="meBankStatementBalance">
                        <input type="number" step="0.01" id="meTotalReceipts" value="0">
                        <input type="number" step="0.01" id="meTotalDisbursements" value="0">
                    </div>

                    <div class="fims-form-section-title">Restricted Funds (itemized — leave empty to use a flat amount)</div>
                    <table class="fims-table" id="meRestrictedTable">
                        <thead><tr><th>Label</th><th style="width:150px">Amount</th><th style="width:40px"></th></tr></thead>
                        <tbody id="meRestrictedBody"></tbody>
                    </table>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="meAddRestrictedRowBtn"><i class="fas fa-plus mr-1"></i>Add Line</button>
                    <div class="fims-form-group" style="margin-top:8px;max-width:240px;">
                        <label class="fims-label">Flat Restricted Amount</label>
                        <input type="number" step="0.01" class="fims-input" id="meRestrictedAmount" value="0">
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-manual-entry">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="meSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>


    <div id="cpsToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>
</div>

<style>
#cashPositionModule .fims-card-blue,
#cashPositionModule .fims-card-green,
#cashPositionModule .fims-card-amber,
#cashPositionModule .fims-card-red,
#cashPositionModule .fims-card-gray,
#cashPositionModule .fims-card-indigo { --fims-card-color: #000E3E; }

#cashPositionModule .fims-btn-primary { background:#000E3E; color:#fff; }
#cashPositionModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#cashPositionModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#cashPositionModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

.fims-form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--fims-text-subtle); margin-bottom:8px; }
.cps-group-header { cursor:pointer; background:var(--fims-table-head-bg, #f4f6fb); }

/* ── Dark mode ──
   The page hard-codes navy (#000E3E) for its primary / outline buttons and the active step (navy on dark), the fund-source
   group rows fall back to a light #f4f6fb (light text on near-white), and the selects use the browser's native grey. */
html.afims-dark[data-bs-theme="dark"] #cashPositionModule { color-scheme:dark; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-primary { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-primary:hover { background:#2E59B8; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-outline { color:#94AADB; border-color:#5B7FCF; background:transparent; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-outline:hover { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .fims-btn-ghost { color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule :is(select.fims-select, .fims-input) {
    background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule select.fims-select option { background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .cps-group-header,
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .cps-group-header > td { background:var(--afims-surface-subtle, #22294A) !important; color:var(--afims-text, #E4ECF2) !important; }
html.afims-dark[data-bs-theme="dark"] #cashPositionModule .cps-group-header :is(span, strong, small, td *) { color:inherit; }
</style>

<script>
(function () {
    const API = '/api/cash-position';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function toast(msg, isError) {
        const el = document.getElementById('cpsToast');
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
    function peso(n) {
        n = Number(n) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#cashPositionModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.style.display = 'none'; });
    });
    function fillSelect(selector, items, placeholder, valueKey, labelFn) {
        const sel = document.querySelector(selector);
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        (items || []).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = labelFn(item);
            sel.appendChild(opt);
        });
        sel.value = current;
    }

    // ══════════════════════ POSITION UPDATES ══════════════════════
    let bankAccountsById = {};
    function loadUpdatesFilters() {
        fetch(API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                (json.data || []).forEach(function (b) { bankAccountsById[b.bank_account_id] = b; });
                fillSelect('#updFilterBank', json.data, 'All Banks', 'bank_account_id', i => i.account_name);
            }).catch(() => {});
        fetch(API + '/lookup/fund-sources', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#updFilterFundSource', (json.data || []).map(f => ({ v: f })), 'All Fund Sources', 'v', i => i.v)).catch(() => {});
    }
    function buildUpdParams() {
        const params = new URLSearchParams();
        params.set('draw', '1');
        const bank = document.getElementById('updFilterBank').value;
        const fs = document.getElementById('updFilterFundSource').value;
        const date = document.getElementById('updFilterDate').value;
        const search = document.getElementById('updFilterSearch').value.trim();
        if (bank) params.set('bank_account_id', bank);
        if (fs) params.set('fund_source', fs);
        if (date) params.set('position_date', date);
        if (search) params.set('search', search);
        return params;
    }
    function loadUpdatesKpis() {
        fetch(API + '/updates/kpis', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#updKpis [data-kpi]').forEach(function (el) {
                    const key = el.dataset.kpi;
                    el.textContent = key === 'total_accounts' ? (d[key] ?? 0) : peso(d[key] ?? 0);
                });
            }).catch(() => {});
    }
    function loadUpdatesList() {
        const tbody = document.querySelector('#updTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        fetch(API + '/updates/list?' + buildUpdParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">No records.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    // Closing Balance is the raw cp.closing_balance; Available
                    // Balance is that minus restricted funds (cp.available_balance,
                    // a DB-generated column) — both now shown since they
                    // legitimately differ whenever any amount is restricted.
                    return '<tr>' +
                        '<td>' + escapeHtml(r.account_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_name || '') + ' · ' + escapeHtml(r.account_number || '') + '</span></td>' +
                        '<td>' + escapeHtml(r.position_date || '') + '</td>' +
                        '<td class="text-right">' + peso(r.opening_balance) + '</td>' +
                        '<td class="text-right">' + peso(r.total_receipts) + '</td>' +
                        '<td class="text-right">' + peso(r.total_disbursements) + '</td>' +
                        '<td class="text-right">' + peso(r.closing_balance) + '</td>' +
                        '<td class="text-right"><strong>' + peso(r.available_balance) + '</strong></td>' +
                        '<td class="text-center"><span class="fims-badge fims-badge-green">Active</span></td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('updFilterBtn')?.addEventListener('click', loadUpdatesList);
    document.getElementById('updResetBtn')?.addEventListener('click', function () {
        document.getElementById('updFilterBank').value = '';
        document.getElementById('updFilterFundSource').value = '';
        document.getElementById('updFilterDate').value = '';
        document.getElementById('updFilterSearch').value = '';
        loadUpdatesList();
    });
    function loadActivityLog() {
        const list = document.getElementById('updActivityList');
        fetch(API + '/updates/activity-log?limit=10', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { list.innerHTML = '<li style="color:var(--fims-text-subtle)">No recent activity.</li>'; return; }
                list.innerHTML = rows.map(function (a) {
                    const badge = a.action === 'CREATE' ? 'fims-badge-green' : 'fims-badge-blue';
                    return '<li style="padding:6px 0;border-bottom:1px solid var(--fims-border);">' +
                        '<span class="fims-badge ' + badge + '">' + escapeHtml(a.action) + '</span> ' +
                        '<strong>' + escapeHtml(a.account_name) + '</strong> (' + escapeHtml(a.bank_name || '') + ') — ' + escapeHtml(a.position_date) + '<br>' +
                        '<span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(a.details || '') + ' · ' + escapeHtml(a.performed_at) + '</span>' +
                        '</li>';
                }).join('');
            })
            .catch(() => { list.innerHTML = '<li>Could not reach the server.</li>'; });
    }
    function initUpdates() { loadUpdatesFilters(); loadUpdatesKpis(); loadUpdatesList(); loadActivityLog(); }

    // ── Cash Position Entry modal — shared by two entry points,
    // openAddEntryModal() (a bank account with no entry yet) and
    // openManualEntryModal() (editing an existing one). Bank Statement
    // Balance/Total Receipts/Total Disbursements are always computed, never
    // shown or hand-typed; Restricted Funds is the only field an existing
    // entry's edit actually changes (Position Date/Beginning Balance are
    // additionally live for a brand-new entry — see refreshComputedBalanceForAdd()). ──
    function renderRestrictedRows(rows) {
        const tbody = document.getElementById('meRestrictedBody');
        tbody.innerHTML = (rows || []).map(function (item, idx) {
            return '<tr><td><input type="text" class="fims-input fims-input-sm me-rf-label" value="' + escapeHtml(item.label || '') + '"></td>' +
                '<td><input type="number" step="0.01" class="fims-input fims-input-sm me-rf-amount" value="' + (item.amount || 0) + '"></td>' +
                '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-rf="' + idx + '"><i class="fas fa-trash"></i></button></td></tr>';
        }).join('');
        tbody.querySelectorAll('[data-remove-rf]').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.closest('tr').remove(); });
        });
    }
    document.getElementById('meAddRestrictedRowBtn')?.addEventListener('click', function () {
        const tbody = document.getElementById('meRestrictedBody');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" class="fims-input fims-input-sm me-rf-label"></td>' +
            '<td><input type="number" step="0.01" class="fims-input fims-input-sm me-rf-amount" value="0"></td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-rf><i class="fas fa-trash"></i></button></td>';
        tr.querySelector('[data-remove-rf]').addEventListener('click', function () { tr.remove(); });
        tbody.appendChild(tr);
    });
    function getRestrictedBreakdown() {
        return Array.from(document.querySelectorAll('#meRestrictedBody tr')).map(function (tr) {
            return { label: tr.querySelector('.me-rf-label').value.trim(), amount: parseFloat(tr.querySelector('.me-rf-amount').value) || 0 };
        }).filter(item => item.amount > 0);
    }

    // Which bank account this modal instance is scoped to — set by
    // whichever of openAddEntryModal()/openManualEntryModal() opened it,
    // since the account is never itself a visible/editable field (implied
    // by which row's Add/Edit action was clicked).
    let meBankAccountId = null;
    // Set by refreshComputedBalanceForAdd() when a brand-new entry's
    // account has neither a prior fims.cash_position row nor a GL account
    // tagged to compute a Beginning Balance from — there's no reasonable
    // value to save in that case, so this blocks Save rather than letting
    // a fabricated number through.
    let meOpeningBalanceBlocked = false;

    function clearManualEntryModal() {
        meBankAccountId = null;
        meOpeningBalanceBlocked = false;
        document.getElementById('meCashPositionId').value = '';
        document.getElementById('mePositionDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('mePositionDate').disabled = false;
        document.getElementById('meOpeningBalance').value = '';
        document.getElementById('meOpeningHint').textContent = '';
        document.getElementById('meOpeningHint').style.color = '';
        document.getElementById('meBankStatementBalance').value = '';
        document.getElementById('meTotalReceipts').value = 0;
        document.getElementById('meTotalDisbursements').value = 0;
        document.getElementById('meRestrictedAmount').value = 0;
        renderRestrictedRows([]);
    }

    // Beginning Balance for a brand-new entry — pulled from the prior
    // day's closing balance, or (this account's very first entry ever) its
    // tagged GL account's balance, same convention CashPositionService::
    // getComputedBalance() has always used. Re-runs whenever Position Date
    // changes, since the computation is as-of that date.
    function refreshComputedBalanceForAdd() {
        const positionDate = document.getElementById('mePositionDate').value;
        const hint = document.getElementById('meOpeningHint');
        const field = document.getElementById('meOpeningBalance');
        if (!meBankAccountId || !positionDate) return;
        fetch(API + '/updates/computed-balance?bank_account_id=' + meBankAccountId + '&position_date=' + positionDate, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = json.data || {};
                if (d.opening_balance_source === 'PRIOR_CLOSING') {
                    field.value = d.opening_balance;
                    hint.textContent = 'Auto-pulled from the prior day\'s ending balance.';
                    hint.style.color = '';
                    meOpeningBalanceBlocked = false;
                } else if (d.opening_balance_source === 'OPENING_BALANCE') {
                    field.value = d.opening_balance;
                    hint.textContent = 'Auto-pulled from this account\'s recorded Opening Balance (first entry on record).';
                    hint.style.color = '';
                    meOpeningBalanceBlocked = false;
                } else if (d.opening_balance_source === 'GENERAL_LEDGER') {
                    field.value = d.opening_balance;
                    hint.textContent = 'Auto-pulled from this account\'s General Ledger balance (first entry on record).';
                    hint.style.color = '';
                    meOpeningBalanceBlocked = false;
                } else {
                    field.value = '';
                    hint.textContent = 'No prior entry and no GL account tagged — tag one in Bank Account Management first.';
                    hint.style.color = '#dc2626';
                    meOpeningBalanceBlocked = true;
                }
                document.getElementById('meTotalDisbursements').value = d.total_disbursements || 0;
                document.getElementById('meTotalReceipts').value = d.total_receipts || 0;
            })
            .catch(() => {});
    }
    document.getElementById('mePositionDate')?.addEventListener('change', function () {
        // Only a brand-new entry's date is actually editable (see
        // openManualEntryModal(), which disables this field) — guard here
        // too in case that ever changes.
        if (!document.getElementById('meCashPositionId').value && !this.disabled) refreshComputedBalanceForAdd();
    });

    // A bank account with no fims.cash_position row yet (Position Updates'
    // "Add" action, only shown on "No entry yet" rows) — Position Date
    // stays editable (defaults to today) since this is the one place it's
    // actually meaningful to choose.
    function openAddEntryModal(bankAccountId) {
        clearManualEntryModal();
        meBankAccountId = bankAccountId;
        document.getElementById('meModalTitle').textContent = 'New Cash Position Entry';
        refreshComputedBalanceForAdd();
        openModal('m-manual-entry');
    }

    // An existing entry (Position Updates' "Edit" action). Position Date
    // is locked to the entry's own date (changing it would really mean a
    // different record); Beginning Balance/Bank Statement Balance/Total
    // Receipts/Total Disbursements are carried over unchanged into the form
    // fields purely so the Save payload still has them —
    // CashPositionService::updateCashPosition() recomputes Closing Balance
    // from whatever's in the payload, not from the DB row, so dropping them
    // would zero out the balance on every restricted-funds-only edit.
    function openManualEntryModal(entryId) {
        clearManualEntryModal();
        document.getElementById('meModalTitle').textContent = 'Edit Cash Position Entry';
        fetch(API + '/updates/' + entryId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load entry.', true); return; }
                const d = json.data;
                meBankAccountId = d.bank_account_id;
                document.getElementById('meCashPositionId').value = d.cash_position_id;
                document.getElementById('mePositionDate').value = d.position_date;
                document.getElementById('mePositionDate').disabled = true;
                document.getElementById('meOpeningBalance').value = d.opening_balance;
                document.getElementById('meBankStatementBalance').value = d.bank_statement_balance || '';
                document.getElementById('meTotalReceipts').value = d.total_receipts;
                document.getElementById('meTotalDisbursements').value = d.total_disbursements;
                document.getElementById('meRestrictedAmount').value = d.restricted_amount || 0;
                renderRestrictedRows(d.restricted_breakdown || []);
                openModal('m-manual-entry');
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    document.getElementById('meSaveBtn')?.addEventListener('click', function () {
        const entryId = document.getElementById('meCashPositionId').value;
        const positionDate = document.getElementById('mePositionDate').value;
        if (!positionDate) { toast('Position date is required.', true); return; }
        if (!entryId && meOpeningBalanceBlocked) { toast('No prior entry and no GL account tagged — tag one in Bank Account Management first.', true); return; }
        if (!entryId && document.getElementById('meOpeningBalance').value === '') { toast('Beginning balance could not be determined for this date.', true); return; }

        const payload = {
            bank_account_id: meBankAccountId,
            position_date: positionDate,
            // Required by saveManualEntry()'s validation on a brand-new
            // entry, but — same as the old wizard's Update Type field —
            // never persisted as its own column; it only ever gated which
            // fields were editable in the UI, which no longer applies here.
            update_type: 'ADJUSTMENT',
            opening_balance: parseFloat(document.getElementById('meOpeningBalance').value) || 0,
            total_receipts: parseFloat(document.getElementById('meTotalReceipts').value) || 0,
            total_disbursements: parseFloat(document.getElementById('meTotalDisbursements').value) || 0,
            bank_statement_balance: document.getElementById('meBankStatementBalance').value,
            restricted_amount: parseFloat(document.getElementById('meRestrictedAmount').value) || 0,
            restricted_breakdown: getRestrictedBreakdown(),
        };
        const btn = this;
        btn.disabled = true;
        const url = entryId ? (API + '/updates/' + entryId + '/update') : (API + '/updates/save');
        fetch(url, { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? 'Cash position saved.' : 'Could not save.'), !json.success);
                if (json.success) {
                    closeModal('m-manual-entry');
                    loadUpdatesKpis();
                    loadUpdatesList();
                    loadActivityLog();
                }
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    // ── Boot ──
    initUpdates();
})();
</script>
