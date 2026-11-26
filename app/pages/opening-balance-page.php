<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Opening Balance Entry — interactive form for seeding account balances at fiscal year start.
// Built via GET /opening_balance (fetch accounts + SL details) and POST /opening_balance (save & post).
$_ob_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_ob_api = preg_match('~/api$~', $_ob_api) ? $_ob_api : $_ob_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    /* All colours come from the shared theme tokens (_shared/css/custom.css), which switch by themselves in dark mode,
       so this page matches the other report pages in both themes. */

    #ob-root .ob-header { text-align: center; margin: 0 0 15px; }
    #ob-root .ob-header .ob-title { font-weight: 700; font-size: 16px; color: var(--afims-heading, #000E3E); }
    #ob-root .ob-header .ob-subtitle { font-size: 13px; color: var(--afims-text-muted, #62657E); margin-top: 4px; }

    #ob-root .ob-status-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px; }
    @media (max-width: 760px) { #ob-root .ob-status-bar { grid-template-columns: 1fr; } }
    #ob-root .ob-status-item { background: var(--afims-surface, #FFFFFF); padding: 15px; border-radius: 10px; border: 1px solid var(--afims-border, #E4ECF2);
        border-left: 4px solid var(--afims-primary, #0035A9); }
    #ob-root .ob-status-label { font-size: 11px; color: var(--afims-text-muted, #62657E); font-weight: 700; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 5px; }
    #ob-root .ob-status-value { font-size: 16px; color: var(--afims-heading, #000E3E); font-weight: 700; font-family: 'IBM Plex Mono', monospace; }
    #ob-root .ob-status-value.balanced { color: var(--afims-success-hover, #0C7539); }
    #ob-root .ob-status-value.unbalanced { color: var(--afims-danger-text, #8F2230); }

    #ob-root .ob-tbl-wrapper { overflow-x: auto; margin-bottom: 20px; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; }
    #ob-root .ob-tbl-scroll { max-height: 70vh; overflow-y: auto; }
    #ob-root .ob-tbl { width: 100%; border-collapse: collapse; color: var(--afims-text, #1A1B21); }
    #ob-root .ob-tbl thead th { background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF); padding: 11px 12px; text-align: left; font-weight: 700;
        font-size: 11px; text-transform: uppercase; letter-spacing: .4px; position: sticky; top: 0; z-index: 2; }
    #ob-root .ob-tbl td { padding: 10px 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); font-size: 13px; }
    #ob-root .ob-tbl .ob-account-row td { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-heading, #000E3E); font-weight: 600; }
    #ob-root .ob-tbl .ob-sl-row td { background: var(--afims-surface, #FFFFFF); }
    #ob-root .ob-tbl .ob-sl-row:hover td { background: var(--afims-primary-soft, #E6EBF6); }
    #ob-root .ob-tbl .ob-subtotal-row td { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text, #1A1B21); font-weight: 600; border-top: 2px solid var(--afims-border-strong, #A9AAB8); }
    #ob-root .ob-tbl .ob-total-row td { background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF); font-weight: 700; border-bottom: 0; }
    #ob-root .ob-tbl .ob-total-row { position: sticky; bottom: 0; z-index: 1; }

    @media print { #ob-root .ob-tbl-scroll { max-height: none; overflow-y: visible; } }

    #ob-root .ob-amount { text-align: right; font-family: 'IBM Plex Mono', monospace; }
    #ob-root input[type="number"] { width: 140px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; text-align: right; font-family: 'IBM Plex Mono', monospace;
        background: var(--afims-surface, #FFFFFF); color: var(--afims-text, #1A1B21); }
    #ob-root input[type="number"]:focus { outline: none; border-color: var(--afims-primary, #0035A9); box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-primary, #0035A9) 20%, transparent); }
    #ob-root input[type="number"]:disabled { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-disabled, #A9AAB8); cursor: not-allowed; }
    html.afims-dark[data-bs-theme="dark"] #ob-root input[type="number"] { color-scheme: dark; }

    #ob-root .ob-error { background: var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); color: var(--afims-danger-text, #8F2230); padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; display: none; }
    #ob-root .ob-error.show { display: block; }
    #ob-root .ob-locked { background: var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); color: var(--afims-danger-text, #8F2230); padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; }

    #ob-root .ob-buttons { display: flex; gap: 10px; margin-top: 20px; }
    #ob-root .ob-buttons button { height: 38px; padding: 0 20px; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px; font-family: 'IBM Plex Sans', sans-serif; }
    #ob-root .ob-btn-primary { background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF); border: 1px solid var(--afims-heading-fill, #000E3E); }
    #ob-root .ob-btn-primary:hover:not(:disabled) { filter: brightness(1.15); }
    #ob-root .ob-btn-primary:disabled { opacity: .5; cursor: not-allowed; }
    #ob-root .ob-btn-secondary { background: var(--afims-surface, #FFFFFF); color: var(--afims-text, #1A1B21); border: 1px solid var(--afims-border, #E4ECF2); }
    #ob-root .ob-btn-secondary:hover { background: var(--afims-surface-subtle, #F4F7FA); }
    html.afims-dark[data-bs-theme="dark"] #ob-root .ob-btn-primary { background: #3861BC; border-color: #3861BC; }
    html.afims-dark[data-bs-theme="dark"] #ob-root .ob-btn-primary:hover:not(:disabled) { background: #2E59B8; border-color: #2E59B8; filter: none; }
    html.afims-dark[data-bs-theme="dark"] #ob-root .ob-btn-secondary { background: #22294A; border-color: #4A5270; }
    html.afims-dark[data-bs-theme="dark"] #ob-root .ob-btn-secondary:hover { background: #2E3656; }

    #ob-root .ob-info-box { background: var(--afims-primary-soft, #E6EBF6); border-left: 4px solid var(--afims-primary, #0035A9); color: var(--afims-text, #1A1B21); padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 13px; display: none; }
    #ob-root .ob-info-box.show { display: block; }
    #ob-root .ob-info-box.ob-success { background: var(--afims-success-soft, #DBF2E5); border-left-color: var(--afims-success, #11A752); color: var(--afims-success-hover, #0C7539); }

    #ob-root .ob-loading { text-align: center; padding: 60px 20px; color: var(--afims-text-muted, #62657E); }
    #ob-root .ob-spinner { border: 4px solid var(--afims-primary-soft, #E6EBF6); border-top-color: var(--afims-primary, #0035A9); border-radius: 50%; width: 50px; height: 50px; animation: spin 1s linear infinite; margin: 0 auto 20px; }
    @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }

    #ob-root .ob-status-badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; background: var(--afims-warning-soft, #FEF8D9); color: var(--afims-warning-text, #7E6908); }
    #ob-root .ob-status-badge.locked { background: var(--afims-danger-soft, #FAE1E3); color: var(--afims-danger-text, #8F2230); }

    @media print { #ob-root .no-print { display: none !important; } }
</style>

<div id="ob-root" class="nha-root">

    <!-- HEADER (same banner as User Access: app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M5 11h14M5 11L2.5 5a3 3 0 015 0L5 11zm14 0l2.5-6a3 3 0 00-5 0l2.5 6z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Opening Balance Entry</h1>
            <p class="app-page-subtitle">Set beginning balances for all accounts at the start of the fiscal year.</p>
        </div>
    </div>

    <!-- INFO & STATUS -->
    <div class="ob-header no-print" style="margin-bottom: 20px;">
        <div class="ob-title">Opening Balance Entry for Fiscal Year <span id="ob-fy">2027</span></div>
    </div>

    <!-- ERROR BANNER -->
    <div id="ob-error" class="ob-error no-print">
        <strong>Error:</strong> <span id="ob-error-msg">Failed to load data.</span>
    </div>

    <!-- INFO BANNER -->
    <div id="ob-info" class="ob-info-box no-print show">
        ℹ️ Enter debit or credit opening amounts for each account. Totals must balance. Click "Post Opening Balance" to save as this fiscal year's starting figures — shown directly on the Trial Balance, no Journal Entry Voucher is created. This is permanent once submitted: it cannot be edited, reversed, or cleared afterward.
    </div>

    <!-- SUCCESS BANNER — inline, not a JS alert() popup, which browsers can silently suppress -->
    <div id="ob-success" class="ob-info-box ob-success no-print">
        <strong>✓ Success:</strong> <span id="ob-success-msg"></span>
    </div>

    <!-- STATUS LOCKED BANNER — fully locked once submitted: no edit, no reverse, no clear -->
    <div id="ob-locked-banner" class="ob-locked" style="display:none;">
        <strong>Locked:</strong> Opening balance for this fiscal year has already been submitted. <span id="ob-locked-jev"></span> It is permanent and cannot be edited, reversed, or cleared.
    </div>

    <!-- FILTERS -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="ob-filter-fy">Fiscal Year</label>
                <select id="ob-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="ob-filter-book">Book</label>
                <select id="ob-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
            <div class="nha-filter-group" style="align-self: flex-end;">
                <button type="button" id="ob-reload" class="nha-btn nha-btn-primary">Reload</button>
            </div>
        </div>
    </div>

    <!-- STATUS BAR -->
    <div class="ob-status-bar no-print" id="ob-status-bar">
        <div class="ob-status-item">
            <div class="ob-status-label">Total Debits</div>
            <div class="ob-status-value" id="ob-total-debit">0.00</div>
        </div>
        <div class="ob-status-item">
            <div class="ob-status-label">Total Credits</div>
            <div class="ob-status-value" id="ob-total-credit">0.00</div>
        </div>
        <div class="ob-status-item">
            <div class="ob-status-label">Difference</div>
            <div class="ob-status-value" id="ob-difference">0.00 ✓</div>
        </div>
    </div>

    <!-- LOADING STATE -->
    <div id="ob-loading" class="ob-loading" style="display:none;">
        <div class="ob-spinner"></div>
        <div style="font-size:14px;">Loading form data...</div>
    </div>

    <!-- FORM TABLE -->
    <div id="ob-form" style="display:none;">
        <div class="ob-tbl-wrapper">
            <div class="ob-tbl-scroll">
                <table class="ob-tbl">
                    <thead>
                        <tr>
                            <th style="width:35%;">Account</th>
                            <th style="width:35%;">Description</th>
                            <th style="width:15%; text-align:right;">Debit</th>
                            <th style="width:15%; text-align:right;">Credit</th>
                        </tr>
                    </thead>
                    <tbody id="ob-tbody">
                        <!-- Rows generated by JS -->
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ob-buttons no-print">
            <button type="button" id="ob-post-btn" class="ob-btn-primary" disabled>Post Opening Balance →</button>
            <button type="button" id="ob-cancel-btn" class="ob-btn-secondary">Cancel</button>
        </div>
    </div>

</div>

<script>
(function() {
    'use strict';

    // State
    let accounts = [];
    let isLocked = false;
    const API_URL = '<?= $_ob_api ?>';
    const JWT_TOKEN = window.ew?.API_JWT_TOKEN || '';

    // DOM
    const $root = document.getElementById('ob-root');
    const $fy = document.getElementById('ob-filter-fy');
    const $bid = document.getElementById('ob-filter-book');
    const $reload = document.getElementById('ob-reload');
    const $loading = document.getElementById('ob-loading');
    const $form = document.getElementById('ob-form');
    const $tbody = document.getElementById('ob-tbody');
    const $error = document.getElementById('ob-error');
    const $errorMsg = document.getElementById('ob-error-msg');
    const $postBtn = document.getElementById('ob-post-btn');
    const $cancelBtn = document.getElementById('ob-cancel-btn');
    const $totalDebit = document.getElementById('ob-total-debit');
    const $totalCredit = document.getElementById('ob-total-credit');
    const $difference = document.getElementById('ob-difference');

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
                // Set default to current year
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

    // Fetch form data
    async function loadForm() {
        $loading.style.display = 'block';
        $form.style.display = 'none';
        $error.classList.remove('show');
        document.getElementById('ob-success').classList.remove('show');
        document.getElementById('ob-locked-banner').style.display = 'none';
        $postBtn.disabled = false;

        try {
            const fy = $fy.value;
            const bid = $bid.value || null;
            const url = `${API_URL}/opening_balance?fiscal_yr=${fy}${bid ? '&book_id=' + bid : ''}`;

            const resp = await fetch(url, {
                headers: { 'X-Authorization': `Bearer ${JWT_TOKEN}` }
            });
            const data = await resp.json();

            if (!data.success) {
                showError(data.message || 'Failed to load form data.');
                return;
            }

            accounts = data.data.accounts || [];
            isLocked = data.data.is_locked || false;

            document.getElementById('ob-fy').textContent = fy;

            if (isLocked) {
                document.getElementById('ob-locked-banner').style.display = 'block';
                document.getElementById('ob-locked-jev').textContent = data.data.legacy_jev?.jev_no
                    ? `(legacy JEV ${data.data.legacy_jev.jev_no})` : '';
                $postBtn.disabled = true;
            }

            renderTable();
            $loading.style.display = 'none';
            $form.style.display = 'block';
        } catch (e) {
            console.error('Load form error:', e);
            showError('Failed to load form data.');
        }
    }

    // Render account table
    function renderTable() {
        $tbody.innerHTML = '';

        // Group leaf (postable) accounts by their parent account in chart_of_accounts —
        // this is the group header; each leaf is its own editable row.
        const groups = {};
        accounts.forEach(acc => {
            if (!groups[acc.group_code]) {
                groups[acc.group_code] = { title: acc.group_title, rows: [] };
            }
            groups[acc.group_code].rows.push(acc);
        });

        Object.entries(groups).forEach(([groupCode, group]) => {
            // Group header row
            const groupRow = document.createElement('tr');
            groupRow.className = 'ob-account-row';
            groupRow.innerHTML = `
                <td><strong>${groupCode}</strong></td>
                <td><strong>${group.title}</strong></td>
                <td></td>
                <td></td>
            `;
            $tbody.appendChild(groupRow);

            // Postable account rows
            group.rows.forEach(acc => {
                const accRow = document.createElement('tr');
                accRow.className = 'ob-sl-row';
                accRow.innerHTML = `
                    <td style="padding-left:30px;">└─ ${acc.account_code}</td>
                    <td>${acc.account_title}</td>
                    <td class="ob-amount"><input type="number" step="0.01" value="${acc.debit || 0}" data-account-code="${acc.account_code}" data-group-code="${groupCode}" data-type="debit" ${isLocked ? 'disabled' : ''} /></td>
                    <td class="ob-amount"><input type="number" step="0.01" value="${acc.credit || 0}" data-account-code="${acc.account_code}" data-group-code="${groupCode}" data-type="credit" ${isLocked ? 'disabled' : ''} /></td>
                `;
                $tbody.appendChild(accRow);
            });

            // Subtotal row
            const subRow = document.createElement('tr');
            subRow.className = 'ob-subtotal-row';
            subRow.innerHTML = `
                <td colspan="2" style="text-align:right;">Subtotal: ${groupCode}</td>
                <td class="ob-amount" data-group-subtotal="${groupCode}" data-type="debit">0.00</td>
                <td class="ob-amount" data-group-subtotal="${groupCode}" data-type="credit">0.00</td>
            `;
            $tbody.appendChild(subRow);
        });

        // Grand total row
        const totalRow = document.createElement('tr');
        totalRow.className = 'ob-total-row';
        totalRow.innerHTML = `
            <td colspan="2">TOTAL</td>
            <td class="ob-amount" id="ob-tbl-debit">0.00</td>
            <td class="ob-amount" id="ob-tbl-credit">0.00</td>
        `;
        $tbody.appendChild(totalRow);

        // Attach input listeners
        $tbody.querySelectorAll('input[type="number"]').forEach(input => {
            input.addEventListener('change', updateTotals);
            input.addEventListener('input', updateTotals);
        });

        updateTotals();
    }

    // Update totals
    function updateTotals() {
        const lines = $tbody.querySelectorAll('input[type="number"]');
        let totalDebit = 0, totalCredit = 0;

        // Collect all inputs into accounts structure
        lines.forEach(input => {
            const val = parseFloat(input.value) || 0;
            if (input.dataset.type === 'debit') totalDebit += val;
            else totalCredit += val;
        });

        $totalDebit.textContent = totalDebit.toFixed(2);
        $totalCredit.textContent = totalCredit.toFixed(2);

        const diff = Math.abs(totalDebit - totalCredit);
        const $diff = document.getElementById('ob-difference');
        if (diff < 0.01) {
            $diff.textContent = '0.00 ✓';
            $diff.className = 'ob-status-value balanced';
            $postBtn.disabled = isLocked || totalDebit === 0;
        } else {
            $diff.textContent = diff.toFixed(2) + ' ✗';
            $diff.className = 'ob-status-value unbalanced';
            $postBtn.disabled = true;
        }

        // Update subtotals (per parent account group, not per leaf account)
        const groups = {};
        lines.forEach(input => {
            const groupCode = input.dataset.groupCode;
            if (!groups[groupCode]) groups[groupCode] = { debit: 0, credit: 0 };
            const val = parseFloat(input.value) || 0;
            if (input.dataset.type === 'debit') groups[groupCode].debit += val;
            else groups[groupCode].credit += val;
        });

        Object.entries(groups).forEach(([grp, totals]) => {
            const $subDebit = $tbody.querySelector(`[data-group-subtotal="${grp}"][data-type="debit"]`);
            const $subCredit = $tbody.querySelector(`[data-group-subtotal="${grp}"][data-type="credit"]`);
            if ($subDebit) $subDebit.textContent = totals.debit.toFixed(2);
            if ($subCredit) $subCredit.textContent = totals.credit.toFixed(2);
        });

        // Update table totals
        const $tblDebit = document.getElementById('ob-tbl-debit');
        const $tblCredit = document.getElementById('ob-tbl-credit');
        if ($tblDebit) $tblDebit.textContent = totalDebit.toFixed(2);
        if ($tblCredit) $tblCredit.textContent = totalCredit.toFixed(2);
    }

    // Post opening balance
    async function postOpeningBalance() {
        const lines = [];
        $tbody.querySelectorAll('input[type="number"]').forEach(input => {
            const val = parseFloat(input.value) || 0;
            if (val !== 0) {
                lines.push({
                    account_code: input.dataset.accountCode,
                    debit: input.dataset.type === 'debit' ? val : 0,
                    credit: input.dataset.type === 'credit' ? val : 0
                });
            }
        });

        try {
            const resp = await fetch(`${API_URL}/opening_balance`, {
                method: 'POST',
                headers: {
                    'X-Authorization': `Bearer ${JWT_TOKEN}`,
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    fiscal_yr: parseInt($fy.value),
                    book_id: $bid.value ? parseInt($bid.value) : null,
                    lines: lines
                })
            });

            const data = await resp.json();

            if (data.success) {
                await loadForm();  // Reload to show saved state
                showSuccess(`Opening balance saved successfully! ${data.data.lines_saved} account(s) set for fiscal year ${data.data.fiscal_yr}.`);
            } else {
                showError(data.message || (data.errors?.join(', ') || 'Failed to post.'));
            }
        } catch (e) {
            console.error('Post error:', e);
            showError('Failed to post opening balance.');
        }
    }

    function showError(msg) {
        document.getElementById('ob-success').classList.remove('show');
        $errorMsg.textContent = msg;
        $error.classList.add('show');
        $loading.style.display = 'none';
        $form.style.display = 'block';
    }

    // Inline banner instead of alert() — browsers (and some extensions) can silently suppress
    // repeated/unwanted JS dialogs, which made successful saves look like nothing happened.
    function showSuccess(msg) {
        $error.classList.remove('show');
        document.getElementById('ob-success-msg').textContent = msg;
        document.getElementById('ob-success').classList.add('show');
    }

    // Event listeners
    $reload.addEventListener('click', loadForm);
    $fy.addEventListener('change', loadForm);
    $bid.addEventListener('change', loadForm);
    $postBtn.addEventListener('click', postOpeningBalance);
    $cancelBtn.addEventListener('click', () => window.history.back());

    // Init
    (async function init() {
        await Promise.all([loadFiscalYears(), loadBooks()]);
        await loadForm();
    })();
})();
</script>
