<?php
/**
 * SOA Generator — Reports module.
 *
 * Generates a Statement of Account from this system's own recorded
 * transactions for one bank account over a date range, laid out in the same
 * Date / Description / Debit / Credit / Balance / Branch / Cheque Number
 * shape as a bank-issued SOA (the same column set the Cash Position module's
 * SOA upload already parses — see CashPositionSoaService::parseXlsx() and
 * fims.soa_transactions) — so it can stand in for a real bank SOA on Bank
 * Reconciliation, or be exported and compared against one.
 *
 * Follows the same lightweight fetch + manual render convention as
 * app/pages/bank-reconciliation-page.php (no DataTables dependency).
 * Markup/CSS classes match _shared/css/nha-fims.css.
 */

namespace PHPMaker2024\FIMS;

$sgCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css');
?>
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= $sgCssVer ?>" />

<div class="fims-wrap" id="sgModule">

    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div>
            <h1 class="app-page-title">SOA Generator</h1>
            <p class="app-page-subtitle">Generate a Statement of Account from this system's own recorded transactions, for use on Bank Reconciliation</p>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
        <div class="fims-toolbar">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <select class="fims-select fims-input-sm" id="sgBankAccount" style="width:280px">
                    <option value="">— Select Bank Account —</option>
                </select>
                <label style="font-size:12px;color:var(--fims-text-muted,#6B7280);">From</label>
                <input type="date" class="fims-input fims-input-sm" id="sgPeriodFrom" style="width:150px">
                <label style="font-size:12px;color:var(--fims-text-muted,#6B7280);">To</label>
                <input type="date" class="fims-input fims-input-sm" id="sgPeriodTo" style="width:150px">
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="sgGenerateBtn"><i class="fas fa-gears mr-1"></i>Generate</button>
                <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="sgExportBtn" disabled><i class="fas fa-file-csv mr-1"></i>Export CSV</button>
                <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="sgPrintBtn" disabled><i class="fas fa-print mr-1"></i>Print</button>
            </div>
        </div>
    </div>

    <div id="sgTableSubtitle" style="padding:10px 14px;font-size:12px;color:var(--fims-text-muted,#6B7280);background:#fff;border:1px solid var(--fims-border,#E5E7EB);border-top:none;">Select a Bank Account and date range, then Generate.</div>

    <!-- Plain spreadsheet-grid replica of the bank's own SOA export (see screenshot) — every
         cell bordered, white background, no app theming — not the usual fims-table panel look,
         so this reads/prints/exports as a straight statement, not an internal report screen. -->
    <div id="sgPrintArea" class="soa-grid-wrap">
        <div style="overflow-x:auto;max-height:65vh;overflow-y:auto;">
            <table class="soa-grid" id="sgTable">
                <thead>
                    <tr>
                        <th style="width:12%">Date</th>
                        <th>Description</th>
                        <th class="text-right" style="width:12%">Debit</th>
                        <th class="text-right" style="width:12%">Credit</th>
                        <th class="text-right" style="width:13%">Balance</th>
                        <th style="width:10%">Branch</th>
                        <th style="width:12%">Cheque Number</th>
                    </tr>
                </thead>
                <tbody id="sgTableBody"><tr><td colspan="7" style="text-align:center;">—</td></tr></tbody>
            </table>
        </div>

        <div id="sgFooterInfo" style="display:none;">
            <table class="soa-grid soa-grid-footer">
                <tbody>
                    <tr><td>Account Number</td><td id="sgFootAccountNo"></td></tr>
                    <tr><td>Currency</td><td>PHP</td></tr>
                    <tr><td>Account Type</td><td id="sgFootAccountType"></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div id="sgToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:10px 16px;border-radius:8px;color:#fff;font-size:13px;z-index:9999;"></div>
</div>

<style>
    /* Plain spreadsheet-grid look, matching the bank's own SOA export: white
       background, every cell bordered, no row striping/hover, Excel-like font
       and sizing — not the app's rounded/themed fims-table styling. */
    .soa-grid-wrap { background: #fff; border: 1px solid #ccc; padding: 10px; }
    table.soa-grid {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        font-family: Calibri, 'Segoe UI', Arial, sans-serif;
        font-size: 13px;
        color: #000;
    }
    table.soa-grid th, table.soa-grid td {
        border: 1px solid #999;
        padding: 4px 8px;
        text-align: left;
        white-space: nowrap;
    }
    table.soa-grid thead th {
        background: #f0f0f0;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 12px;
    }
    table.soa-grid td.text-right, table.soa-grid th.text-right { text-align: right; }
    table.soa-grid-footer { width: auto; margin-top: 18px; }
    table.soa-grid-footer td:first-child { font-weight: 700; padding-right: 20px; }

    @media print {
        #sgModule .app-page-header,
        #sgModule .fims-toolbar,
        #sgModule #sgTableSubtitle,
        #sgToast { display: none !important; }
        .soa-grid-wrap { border: none; padding: 0; }
        #sgPrintArea > div { max-height: none !important; overflow: visible !important; }
    }
</style>

<script>
(function () {
    const API = '/api/soa-generator';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }

    function toast(msg, isError) {
        const el = document.getElementById('sgToast');
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

    // ── Bank Account dropdown ──
    function loadBankAccounts() {
        return fetch(API + '/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const sel = document.getElementById('sgBankAccount');
                sel.innerHTML = '<option value="">— Select Bank Account —</option>' +
                    rows.map(a => '<option value="' + a.bank_account_id + '">' + escapeHtml(a.bank_name + ' — ' + a.account_name + ' (' + (a.account_number || '') + ')') + '</option>').join('');
            })
            .catch(() => {});
    }

    // Default range: current month to date — same "reasonable default, always overridable"
    // convention as Bank Reconciliation's own period field.
    (function setDefaultRange() {
        const now = new Date();
        const first = new Date(now.getFullYear(), now.getMonth(), 1);
        document.getElementById('sgPeriodFrom').value = first.toISOString().slice(0, 10);
        document.getElementById('sgPeriodTo').value = now.toISOString().slice(0, 10);
    })();

    let sgRows = [];
    let sgBankInfo = null;

    function generate() {
        const bankAccountId = document.getElementById('sgBankAccount').value;
        const periodFrom = document.getElementById('sgPeriodFrom').value;
        const periodTo = document.getElementById('sgPeriodTo').value;
        const subtitle = document.getElementById('sgTableSubtitle');
        const tbody = document.getElementById('sgTableBody');
        const footer = document.getElementById('sgFooterInfo');

        if (!bankAccountId) { toast('Select a Bank Account first.', true); return; }
        if (!periodFrom || !periodTo) { toast('Select both a From and To date.', true); return; }

        subtitle.textContent = 'Generating…';
        tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading…</td></tr>';
        document.getElementById('sgExportBtn').disabled = true;
        document.getElementById('sgPrintBtn').disabled = true;
        footer.style.display = 'none';

        fetch(API + '/generate?bank_account_id=' + encodeURIComponent(bankAccountId) +
            '&period_start=' + encodeURIComponent(periodFrom) + '&period_end=' + encodeURIComponent(periodTo), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) {
                    subtitle.textContent = json.message || 'Could not generate the SOA.';
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">' + escapeHtml(json.message || 'Could not generate the SOA.') + '</td></tr>';
                    return;
                }
                const d = json.data;
                sgBankInfo = d.bank;

                if (!d.has_gl_account) {
                    subtitle.textContent = 'This bank account has no GL account code tagged — nothing to generate.';
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">This bank account has no GL account code tagged (see Bank Management) — there is nothing to generate against.</td></tr>';
                    sgRows = [];
                    return;
                }

                // Newest first, matching a typical bank-issued SOA — each row already carries
                // its own correctly-computed running balance from the service, so reversing
                // the display order here doesn't touch that.
                sgRows = d.rows.slice().reverse();

                if (!sgRows.length) {
                    subtitle.textContent = 'No transactions recorded for this bank account in the selected range.';
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No transactions recorded for this bank account in the selected range.</td></tr>';
                } else {
                    subtitle.textContent = sgRows.length + ' transaction' + (sgRows.length === 1 ? '' : 's') +
                        ' — Beginning Balance: ' + peso(d.beginning_balance) + ' — Ending Balance: ' + peso(d.ending_balance);
                    tbody.innerHTML = sgRows.map(function (r) {
                        return '<tr>' +
                            '<td>' + escapeHtml(r.date || '') + '</td>' +
                            '<td>' + escapeHtml(r.description || '') + '</td>' +
                            '<td class="text-right">' + (r.debit != null ? peso(r.debit) : '') + '</td>' +
                            '<td class="text-right">' + (r.credit != null ? peso(r.credit) : '') + '</td>' +
                            '<td class="text-right">' + peso(r.balance) + '</td>' +
                            '<td>' + escapeHtml(r.branch || '') + '</td>' +
                            '<td>' + escapeHtml(r.cheque_number || '') + '</td>' +
                            '</tr>';
                    }).join('');
                    document.getElementById('sgExportBtn').disabled = false;
                    document.getElementById('sgPrintBtn').disabled = false;
                }

                document.getElementById('sgFootAccountNo').textContent = sgBankInfo.account_number || '—';
                document.getElementById('sgFootAccountType').textContent = sgBankInfo.account_type || '—';
                footer.style.display = '';
            })
            .catch(function () {
                subtitle.textContent = 'Could not reach the server.';
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Could not reach the server.</td></tr>';
            });
    }
    document.getElementById('sgGenerateBtn').addEventListener('click', generate);

    // Exact replica of the bank's own SOA export shape (see the reference
    // screenshot): Date/Description/Debit/Credit/Balance/Branch/Cheque Number
    // columns, then a blank row, then the Account Number/Currency/Account
    // Type footer — same layout, not the upload-parser's Check No./Other
    // Details header names (that's a different, compatibility-oriented need).
    document.getElementById('sgExportBtn').addEventListener('click', function () {
        if (!sgRows.length) return;
        const csvEscape = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
        const lines = [['Date', 'Description', 'Debit', 'Credit', 'Balance', 'Branch', 'Cheque Number'].map(csvEscape).join(',')];
        sgRows.forEach(function (r) {
            lines.push([
                r.date || '', r.description || '',
                r.debit != null ? r.debit.toFixed(2) : '', r.credit != null ? r.credit.toFixed(2) : '', r.balance.toFixed(2),
                r.branch || '', r.cheque_number || '',
            ].map(csvEscape).join(','));
        });
        lines.push('');
        lines.push(['Account Number', sgBankInfo ? (sgBankInfo.account_number || '') : ''].map(csvEscape).join(','));
        lines.push(['Currency', 'PHP'].map(csvEscape).join(','));
        lines.push(['Account Type', sgBankInfo ? (sgBankInfo.account_type || '') : ''].map(csvEscape).join(','));
        const blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'SOA-' + (sgBankInfo ? sgBankInfo.account_number : 'export') + '-' +
            document.getElementById('sgPeriodFrom').value + '_to_' + document.getElementById('sgPeriodTo').value + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    });

    document.getElementById('sgPrintBtn').addEventListener('click', function () { window.print(); });

    loadBankAccounts();
})();
</script>
