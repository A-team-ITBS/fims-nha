<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Closing Balance Entry — year-end Revenue/Expense sweep, mirrors opening-balance-page.php.
// Built via GET /closing_balance (preview) and POST /closing_balance (close & post).
$_cb_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_cb_api = preg_match('~/api$~', $_cb_api) ? $_cb_api : $_cb_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #cb-root .cb-header { text-align: center; margin: 0 0 15px; }
    #cb-root .cb-header .cb-title { font-weight: 700; font-size: 16px; color: var(--afims-text-primary, #1B2B6B); }
    #cb-root .cb-header .cb-subtitle { font-size: 13px; color: var(--afims-text-muted, #62657E); margin-top: 4px; }

    #cb-root .cb-status-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    #cb-root .cb-status-item { background: var(--afims-bg-soft, #F5F6FA); padding: 15px; border-radius: 6px; border-left: 4px solid #3498db; }
    #cb-root .cb-status-label { font-size: 12px; color: var(--afims-text-muted, #62657E); font-weight: 600; margin-bottom: 5px; }
    #cb-root .cb-status-value { font-size: 16px; color: var(--afims-text-primary, #1B2B6B); font-weight: 700; font-family: 'IBM Plex Mono', monospace; }
    #cb-root .cb-status-value.income { color: #27ae60; }
    #cb-root .cb-status-value.loss { color: #e74c3c; }

    #cb-root .cb-tbl-wrapper { overflow-x: auto; margin-bottom: 20px; border: 1px solid var(--afims-border, #DDE0EC); border-radius: 6px; }
    #cb-root .cb-tbl-scroll { max-height: 70vh; overflow-y: auto; }
    #cb-root .cb-tbl { width: 100%; border-collapse: collapse; }
    #cb-root .cb-tbl thead th { background: var(--afims-bg-dark, #34495e); color: white; padding: 12px; text-align: left; font-weight: 600; font-size: 13px; position: sticky; top: 0; z-index: 2; }
    #cb-root .cb-tbl tbody tr:hover { background: var(--afims-bg-hover, #f9f9f9); }
    #cb-root .cb-tbl td { padding: 12px; border-bottom: 1px solid var(--afims-border, #DDE0EC); font-size: 13px; }
    #cb-root .cb-tbl .cb-section-row { background: var(--afims-bg-soft, #E8F3FF); font-weight: 600; }
    #cb-root .cb-tbl .cb-subtotal-row { background: var(--afims-bg-soft, #F5F6FA); font-weight: 600; border-top: 2px solid var(--afims-border, #DDE0EC); }
    #cb-root .cb-tbl .cb-net-row { background: var(--afims-bg-dark, #34495e); color: white; font-weight: 700; border-top: 2px solid var(--afims-bg-dark, #34495e); border-bottom: 2px solid var(--afims-bg-dark, #34495e); position: sticky; bottom: 0; z-index: 1; }

    @media print { #cb-root .cb-tbl-scroll { max-height: none; overflow-y: visible; } }

    #cb-root .cb-amount { text-align: right; font-family: 'IBM Plex Mono', monospace; }

    #cb-root .cb-error { background: #FEE5E5; border-left: 4px solid #e74c3c; color: #c0392b; padding: 12px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; display: none; }
    #cb-root .cb-error.show { display: block; }

    #cb-root .cb-buttons { display: flex; gap: 10px; margin-top: 20px; }
    #cb-root button { padding: 10px 20px; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; font-size: 13px; }
    #cb-root .cb-btn-primary { background: #e67e22; color: white; }
    #cb-root .cb-btn-primary:hover { background: #ca6f1e; }
    #cb-root .cb-btn-primary:disabled { background: #bdc3c7; cursor: not-allowed; }
    #cb-root .cb-btn-secondary { background: #95a5a6; color: white; }
    #cb-root .cb-btn-secondary:hover { background: #7f8c8d; }

    #cb-root .cb-info-box { background: #D4EDDA; border-left: 4px solid #28a745; color: #155724; padding: 12px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; display: none; }
    #cb-root .cb-info-box.show { display: block; }

    #cb-root .cb-loading { text-align: center; padding: 60px 20px; }
    #cb-root .cb-spinner { border: 4px solid var(--afims-bg-soft, #E8F3FF); border-top-color: #3498db; border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    @media print { #cb-root .no-print { display: none !important; } }
</style>

<div id="cb-root" class="nha-root">

    <!-- HEADER -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Closing Balance Entry</div>
            </div>
        </div>
    </div>

    <!-- INFO & STATUS -->
    <div class="cb-header no-print" style="margin-bottom: 20px;">
        <div class="cb-title">Closing Entry for Fiscal Year <span id="cb-fy">2026</span></div>
        <div class="cb-subtitle">Zero out Revenue &amp; Expense accounts and sweep the net to Government Equity</div>
    </div>

    <!-- ERROR BANNER -->
    <div id="cb-error" class="cb-error no-print">
        <strong>Error:</strong> <span id="cb-error-msg">Failed to load data.</span>
    </div>

    <!-- INFO BANNER -->
    <div id="cb-info" class="cb-info-box no-print show">
        ℹ️ This preview is computed live from posted General Ledger activity for the selected fiscal year/book — nothing here is typed in by hand. Click "Close Fiscal Year" to post it.
    </div>

    <!-- SUCCESS BANNER — inline, not a JS alert() popup, which browsers can silently suppress -->
    <div id="cb-success" class="cb-info-box no-print" style="background:#D4EDDA; border-left-color:#28a745; color:#155724; white-space:pre-line;">
        <strong>✓ Success:</strong> <span id="cb-success-msg"></span>
    </div>

    <!-- STATUS LOCKED BANNER -->
    <div id="cb-locked-banner" style="display:none; background:#FEE5E5; border-left:4px solid #e74c3c; color:#c0392b; padding:12px; border-radius:4px; margin-bottom:15px; font-size:13px;">
        <strong>Closed:</strong> This fiscal year has already been closed. <span id="cb-locked-jev"></span> To redo it, that entry must be reversed first.
        <button type="button" id="cb-reverse-btn" style="margin-left:10px; padding:4px 12px; border:none; border-radius:4px; background:#c0392b; color:white; cursor:pointer; font-size:12px; font-weight:600;">Reverse →</button>
    </div>

    <!-- FILTERS -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cb-filter-fy">Fiscal Year</label>
                <select id="cb-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cb-filter-book">Book</label>
                <select id="cb-filter-book" class="nha-filter-select">
                    <option value="">Select a book…</option>
                </select>
            </div>
            <div class="nha-filter-group" style="align-self: flex-end;">
                <button type="button" id="cb-reload" class="nha-btn-primary" style="padding:8px 16px;">Reload</button>
            </div>
        </div>
    </div>

    <!-- STATUS BAR -->
    <div class="cb-status-bar no-print" id="cb-status-bar">
        <div class="cb-status-item">
            <div class="cb-status-label">Total Revenue</div>
            <div class="cb-status-value" id="cb-total-revenue">0.00</div>
        </div>
        <div class="cb-status-item">
            <div class="cb-status-label">Total Expense</div>
            <div class="cb-status-value" id="cb-total-expense">0.00</div>
        </div>
        <div class="cb-status-item">
            <div class="cb-status-label">Net Income / (Loss)</div>
            <div class="cb-status-value" id="cb-net-income">0.00</div>
        </div>
    </div>

    <!-- LOADING STATE -->
    <div id="cb-loading" class="cb-loading" style="display:none;">
        <div class="cb-spinner"></div>
        <div style="color:var(--afims-text-muted, #62657E); font-size:14px;">Loading closing preview...</div>
    </div>

    <!-- FORM TABLE -->
    <div id="cb-form" style="display:none;">
        <div class="cb-tbl-wrapper">
            <div class="cb-tbl-scroll">
                <table class="cb-tbl">
                    <thead>
                        <tr>
                            <th style="width:20%;">Account</th>
                            <th style="width:40%;">Description</th>
                            <th style="width:20%; text-align:right;">Current Balance</th>
                            <th style="width:20%; text-align:right;">Closing Entry</th>
                        </tr>
                    </thead>
                    <tbody id="cb-tbody">
                        <!-- Rows generated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cb-buttons no-print">
            <button type="button" id="cb-close-btn" class="cb-btn-primary" disabled>Close Fiscal Year →</button>
            <button type="button" id="cb-cancel-btn" class="cb-btn-secondary">Cancel</button>
        </div>
    </div>

</div>

<script>
(function() {
    'use strict';

    // State
    let accounts = [];
    let isLocked = false;
    const API_URL = '<?= $_cb_api ?>';
    const JWT_TOKEN = window.ew?.API_JWT_TOKEN || '';

    // DOM
    const $fy = document.getElementById('cb-filter-fy');
    const $bid = document.getElementById('cb-filter-book');
    const $reload = document.getElementById('cb-reload');
    const $loading = document.getElementById('cb-loading');
    const $form = document.getElementById('cb-form');
    const $tbody = document.getElementById('cb-tbody');
    const $error = document.getElementById('cb-error');
    const $errorMsg = document.getElementById('cb-error-msg');
    const $closeBtn = document.getElementById('cb-close-btn');
    const $cancelBtn = document.getElementById('cb-cancel-btn');
    const $reverseBtn = document.getElementById('cb-reverse-btn');
    const $totalRevenue = document.getElementById('cb-total-revenue');
    const $totalExpense = document.getElementById('cb-total-expense');
    const $netIncome = document.getElementById('cb-net-income');

    function fmt(n) {
        return (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // Fetch fiscal years for dropdown
    async function loadFiscalYears() {
        try {
            const resp = await fetch(`${API_URL}/fiscal_year/all`, {
                headers: { 'X-Authorization': `Bearer ${JWT_TOKEN}` }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            if (data.success && data.data) {
                data.data.forEach(fy => {
                    $fy.appendChild(Object.assign(document.createElement('option'), {
                        value: fy.fiscal_yr,
                        textContent: `${fy.fiscal_yr}`
                    }));
                });
                $fy.value = new Date().getFullYear();
            }
        } catch (e) {
            console.error('Failed to load fiscal years:', e);
        }
    }

    // Fetch books for dropdown
    async function loadBooks() {
        try {
            const resp = await fetch(`${API_URL}/books/all`, {
                headers: { 'X-Authorization': `Bearer ${JWT_TOKEN}` }
            });
            if (!resp.ok) return;
            const data = await resp.json();
            if (data.success && data.data) {
                data.data.forEach(book => {
                    $bid.appendChild(Object.assign(document.createElement('option'), {
                        value: book.book_id,
                        textContent: book.name
                    }));
                });
            }
        } catch (e) {
            console.error('Failed to load books:', e);
        }
    }

    // Fetch closing preview
    async function loadForm() {
        if (!$bid.value) {
            showError('Select a book to preview its closing entry.');
            return;
        }

        $loading.style.display = 'block';
        $form.style.display = 'none';
        $error.classList.remove('show');
        document.getElementById('cb-success').classList.remove('show');
        document.getElementById('cb-locked-banner').style.display = 'none';

        try {
            const fy = $fy.value;
            const bid = $bid.value;
            const url = `${API_URL}/closing_balance?fiscal_yr=${fy}&book_id=${bid}`;

            const resp = await fetch(url, {
                headers: { 'X-Authorization': `Bearer ${JWT_TOKEN}` }
            });
            const data = await resp.json();

            if (!data.success) {
                showError(data.message || 'Failed to load closing preview.');
                return;
            }

            accounts = data.data.accounts || [];
            isLocked = data.data.is_locked || false;

            document.getElementById('cb-fy').textContent = fy;

            $totalRevenue.textContent = fmt(data.data.total_revenue);
            $totalExpense.textContent = fmt(data.data.total_expense);
            const net = Number(data.data.net_income) || 0;
            $netIncome.textContent = fmt(net) + (net < 0 ? ' (Loss)' : '');
            $netIncome.className = 'cb-status-value ' + (net >= 0 ? 'income' : 'loss');

            if (isLocked) {
                document.getElementById('cb-locked-banner').style.display = 'block';
                document.getElementById('cb-locked-jev').textContent = `(${data.data.existing_closing_jev?.jev_no})`;
            }

            renderTable();
            $closeBtn.disabled = isLocked || accounts.length === 0;
            $loading.style.display = 'none';
            $form.style.display = 'block';
        } catch (e) {
            console.error('Load closing preview error:', e);
            showError('Failed to load closing preview.');
        }
    }

    // Render preview table
    function renderTable() {
        $tbody.innerHTML = '';

        if (accounts.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = `<td colspan="4" style="text-align:center; color:var(--afims-text-muted, #62657E); padding:30px;">No Revenue or Expense activity found for this fiscal year/book — nothing to close.</td>`;
            $tbody.appendChild(emptyRow);
            return;
        }

        let currentType = null;
        accounts.forEach(acc => {
            if (acc.account_type !== currentType) {
                currentType = acc.account_type;
                const sectionRow = document.createElement('tr');
                sectionRow.className = 'cb-section-row';
                sectionRow.innerHTML = `<td colspan="4">${currentType}</td>`;
                $tbody.appendChild(sectionRow);
            }

            const current = currentType === 'Revenue'
                ? (acc.current_credit - acc.current_debit)
                : (acc.current_debit - acc.current_credit);
            const closingLabel = acc.closing_debit > 0
                ? `Dr ${fmt(acc.closing_debit)}`
                : `Cr ${fmt(acc.closing_credit)}`;

            const row = document.createElement('tr');
            row.innerHTML = `
                <td>${acc.account_code}</td>
                <td>${acc.account_title}</td>
                <td class="cb-amount">${fmt(current)}</td>
                <td class="cb-amount">${closingLabel}</td>
            `;
            $tbody.appendChild(row);
        });

        // Net row
        const netRow = document.createElement('tr');
        netRow.className = 'cb-net-row';
        netRow.innerHTML = `
            <td colspan="2">NET INCOME / (LOSS) → GOVERNMENT EQUITY</td>
            <td class="cb-amount" colspan="2">${$netIncome.textContent}</td>
        `;
        $tbody.appendChild(netRow);
    }

    // Close the fiscal year
    async function closeFiscalYear() {
        try {
            const resp = await fetch(`${API_URL}/closing_balance`, {
                method: 'POST',
                headers: {
                    'X-Authorization': `Bearer ${JWT_TOKEN}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    fiscal_yr: parseInt($fy.value),
                    book_id: parseInt($bid.value)
                })
            });

            const data = await resp.json();

            if (data.success) {
                const cf = data.data.carry_forward;
                let msg = `Fiscal year closed successfully! JEV: ${data.data.jev_no}`;
                if (cf?.status === 'posted') {
                    msg += `\n\n✓ Fiscal year ${cf.fiscal_yr} automatically opened with the carried-forward balances (${cf.lines_saved} account(s)).`;
                } else if (cf?.status === 'skipped') {
                    msg += `\n\nNote: next fiscal year was not auto-opened (${cf.reason})`;
                }
                await loadForm();
                showSuccess(msg);
            } else {
                showError(data.message || (data.errors?.join(', ') || 'Failed to close fiscal year.'));
            }
        } catch (e) {
            console.error('Close error:', e);
            showError('Failed to close fiscal year.');
        }
    }

    function showError(msg) {
        document.getElementById('cb-success').classList.remove('show');
        $errorMsg.textContent = msg;
        $error.classList.add('show');
        $loading.style.display = 'none';
        $form.style.display = 'block';
        $tbody.innerHTML = '';
        $closeBtn.disabled = true;
    }

    // Inline banner instead of alert() — browsers (and some extensions) can silently suppress
    // repeated/unwanted JS dialogs, which made successful actions look like nothing happened.
    function showSuccess(msg) {
        $error.classList.remove('show');
        document.getElementById('cb-success-msg').textContent = msg;
        document.getElementById('cb-success').classList.add('show');
    }

    // Double-click-to-confirm instead of a native confirm() dialog — see showSuccess() comment;
    // confirm() silently returning false when blocked made this button look completely dead.
    // Reverse the posted CLOSING JEV, unlocking this fiscal year/book for re-entry. The backend
    // refuses this if next year already has a posted Opening Balance carried forward from it.
    let reversePending = false;
    async function reverseClosingBalance(ev) {
        const btn = ev.currentTarget;
        if (!reversePending) {
            reversePending = true;
            const originalText = btn.textContent;
            btn.textContent = 'Click again to confirm →';
            setTimeout(() => {
                if (reversePending) {
                    reversePending = false;
                    btn.textContent = originalText;
                }
            }, 4000);
            return;
        }
        reversePending = false;
        btn.textContent = 'Reverse →';

        try {
            const resp = await fetch(`${API_URL}/closing_balance/reverse`, {
                method: 'POST',
                headers: {
                    'X-Authorization': `Bearer ${JWT_TOKEN}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ fiscal_yr: parseInt($fy.value), book_id: parseInt($bid.value) })
            });
            const data = await resp.json();
            if (data.success) {
                await loadForm();
                showSuccess(data.message);
            } else {
                showError(data.message || 'Failed to reverse the Closing Entry.');
            }
        } catch (e) {
            console.error('Reverse error:', e);
            showError('Failed to reverse the Closing Entry.');
        }
    }

    // Event listeners
    $reload.addEventListener('click', loadForm);
    $fy.addEventListener('change', loadForm);
    $bid.addEventListener('change', loadForm);
    $closeBtn.addEventListener('click', closeFiscalYear);
    $reverseBtn.addEventListener('click', reverseClosingBalance);
    $cancelBtn.addEventListener('click', () => window.history.back());

    // Init
    (async function init() {
        await Promise.all([loadFiscalYears(), loadBooks()]);
    })();
})();
</script>
