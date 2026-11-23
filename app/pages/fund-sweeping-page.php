<?php
/**
 * Fund Sweeping — bulk-prepares DRAFT FTLAs (fims.fund_transfers, via
 * FtlaService::create() — same endpoint the manual FTLA form uses) that move
 * a Region/Office's bank accounts' un-swept Deposits
 * (fims.collection_report_deposits.is_swept = FALSE, NOT
 * fims.bank_accounts.current_balance — that column is never kept in sync by
 * anything) into their already-assigned main_depository_account_id. Doesn't
 * move money itself — see BankManagementService::previewFundSweep()/
 * processFundSweep()'s docblocks. Accounts with no Main Depository assigned
 * are listed as "Not assigned" and skipped; assign one via Bank Account
 * Management's Register/Edit modal first.
 *
 * Split out of app/pages/collections-page.php (where it used to be a
 * second tab) into its own page — the sweep logic itself has always lived
 * in BankManagementService (it operates on fims.bank_accounts), even though
 * the balance it sums comes from Collections' own Deposit records; this
 * page just calls that same API directly instead of being nested inside
 * the Collections module.
 *
 * Markup/CSS classes (fims-page-header, fims-table-panel, fims-modal-overlay
 * conventions, etc.) match app/fims-css/fims.css, same as collections-page.php.
 */

namespace PHPMaker2024\FIMS;

$fsCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $fsCssVer ?>" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div class="fims-wrap" id="fsModule">

    <!-- same banner as User Access: app-page-header (styled by app/fims-css/fims.css, already loaded above) -->
    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-broom"></i></div>
        <div>
            <h1 class="app-page-title">Fund Sweeping</h1>
            <p class="app-page-subtitle">Bulk-prepare draft FTLAs from a Region/Office's un-swept Deposits</p>
        </div>
    </div>

    <!-- No automatic sweep — Fund Sweep is only ever triggered by a user
         clicking "Process Fund Sweep" below, and only on the configured day
         (default Thursday — see fims.app_settings' fund_sweep_day_of_week,
         changeable via the Settings button; both enforced server-side too —
         see BankManagementService::processFundSweep()'s own day-of-week
         check). This countdown is just a client-side clock toward the next
         allowed day so whoever's on this page knows when the button will
         accept a click — it doesn't call any API or trigger anything
         itself. -->
    <div class="fims-table-panel" style="margin-bottom:16px;">
        <div style="padding:14px 16px;display:flex;align-items:center;gap:12px;">
            <i class="fas fa-clock" style="font-size:18px;color:#000E3E;"></i>
            <div style="flex:1;">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:.04em;color:var(--fims-text-subtle);" id="fsCountdownLabel">Next Sweep Window</div>
                <div id="fsCountdown" style="font-size:20px;font-weight:700;font-variant-numeric:tabular-nums;">—</div>
            </div>
            <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="fsSettingsBtn">
                <i class="fas fa-gear mr-1"></i>Settings
            </button>
        </div>
    </div>

    <!-- Settings — just the one value today: which day of the week Fund
         Sweep is allowed to run on. Not a modal overlay like the rest of
         this project's forms; a small inline panel is plenty for one field. -->
    <div class="fims-table-panel" id="fsSettingsPanel" style="display:none;margin-bottom:16px;">
        <div style="padding:14px 16px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <label class="fims-label" style="margin:0;white-space:nowrap;">Sweep Day</label>
            <select class="fims-select fims-input-sm" id="fsSweepDaySelect" style="max-width:200px;">
                <option value="1">Monday</option>
                <option value="2">Tuesday</option>
                <option value="3">Wednesday</option>
                <option value="4">Thursday</option>
                <option value="5">Friday</option>
                <option value="6">Saturday</option>
                <option value="7">Sunday</option>
            </select>
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fsSettingsSaveBtn">Save</button>
            <span id="fsSettingsHint" style="font-size:11px;color:var(--fims-text-subtle);"></span>
        </div>
    </div>

    <div class="fims-table-panel">
        <div class="fims-panel-header">
            <div class="fims-panel-title"><i class="fas fa-broom mr-1"></i>Fund Sweeping</div>
        </div>
        <div style="padding:12px 16px;display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--fims-border, #E4ECF2);">
            <label class="fims-label" style="margin:0;white-space:nowrap;">Region / Office</label>
            <select class="fims-select fims-input-sm" id="fsRegionSelect" style="max-width:320px;">
                <option value="">— Main Office —</option>
            </select>
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fsProcessBtn" disabled>
                <i class="fas fa-paper-plane mr-1"></i>Process Fund Sweep
            </button>
            <span id="fsHint" style="font-size:11px;color:var(--fims-text-subtle);"></span>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table">
                <thead><tr>
                    <th style="width:32px"><input type="checkbox" id="fsSelectAll"></th>
                    <th>Region Bank Account</th><th>GL / SL Account Code</th><th class="text-right">Balance</th><th>Main Depository Account</th><th style="width:36px"></th>
                </tr></thead>
                <tbody id="fsBody"><tr class="fims-empty"><td colspan="6">Select a Region/Office to preview its accounts.</td></tr></tbody>
            </table>
        </div>
        <div id="fsResults" style="display:none;padding:12px 16px;border-top:1px solid var(--fims-border, #E4ECF2);font-size:12px;"></div>
    </div>

    <div id="fsToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>

</div>

<style>
#fsModule .fims-card-blue,
#fsModule .fims-card-green,
#fsModule .fims-card-amber,
#fsModule .fims-card-red,
#fsModule .fims-card-gray,
#fsModule .fims-card-indigo { --fims-card-color: #000E3E; }

#fsModule .fims-btn-primary { background:#000E3E; color:#fff; }
#fsModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#fsModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#fsModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

html.afims-dark[data-bs-theme="dark"] #fsModule { color-scheme:dark; }
html.afims-dark[data-bs-theme="dark"] #fsModule .fims-btn-primary { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #fsModule .fims-btn-primary:hover { background:#2E59B8; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #fsModule .fims-btn-outline { color:#94AADB; border-color:#5B7FCF; background:transparent; }
html.afims-dark[data-bs-theme="dark"] #fsModule .fims-btn-outline:hover { background:#3861BC; border-color:#3861BC; color:#fff; }
html.afims-dark[data-bs-theme="dark"] #fsModule :is(select.fims-select, .fims-input) {
    background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); border-color:var(--afims-border-strong, #4A5270); }
html.afims-dark[data-bs-theme="dark"] #fsModule select.fims-select option { background-color:var(--afims-surface, #1A1F38); color:var(--afims-text, #E4ECF2); }
</style>

<script>
(function () {
    // Fund Sweeping operates on fims.bank_accounts — that logic lives in
    // BankManagementService, not a service of this page's own, so it's
    // called directly here (same cross-module call the old Collections tab
    // made). Region/Office lookup is Collections' own endpoint — same
    // fims.responsibility_code list every other "Region/Office" picker in
    // this project reads from.
    const COLLECTIONS_API = '/api/collections';
    const BANK_MANAGEMENT_API = '/api/bank-management';

    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function jsonHeaders(extra) {
        return authHeaders(Object.assign({ 'Content-Type': 'application/json' }, extra || {}));
    }

    function toast(msg, isError) {
        const el = document.getElementById('fsToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function loadRegions() {
        fetch(COLLECTIONS_API + '/lookup/responsibility-centers', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const regions = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                const fsSel = document.getElementById('fsRegionSelect');
                fsSel.innerHTML = '<option value="">— Main Office —</option>' + regions.map(function (rc) {
                    return '<option value="' + rc.rc_id + '">' + escapeHtml(rc.rc_code + ' — ' + rc.rc_name) + '</option>';
                }).join('');
            })
            .catch(() => toast('Could not load Region/Office list.', true));
    }

    document.getElementById('fsRegionSelect').addEventListener('change', loadFundSweepPreview);

    // Fund Sweep only runs when a user clicks "Process Fund Sweep" — no
    // automatic trigger exists — and only on the configured day of the week
    // (fsSweepDayOfWeek, ISO-8601 1=Monday..7=Sunday, loaded from
    // GET /fund-sweep/settings below, default 4/Thursday), same rule
    // enforced again server-side (BankManagementService::processFundSweep())
    // since that's the one place that actually creates the draft FTLAs;
    // this is just the button-level reflection of it.
    let fsSweepDayOfWeek = 4;
    const FS_DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    function fsIsSweepDay() {
        return new Date().getDay() === (fsSweepDayOfWeek % 7); // ISO 7 (Sunday) -> JS 0
    }

    function fsUpdateProcessBtn() {
        const checked = document.querySelectorAll('#fsBody .fs-row-check:checked').length;
        const btn = document.getElementById('fsProcessBtn');
        const hint = document.getElementById('fsHint');
        if (!fsIsSweepDay()) {
            btn.disabled = true;
            hint.textContent = 'Fund Sweep can only be processed on ' + FS_DAY_NAMES[fsSweepDayOfWeek % 7] + 's.';
            return;
        }
        btn.disabled = checked === 0;
        hint.textContent = '';
    }

    function loadFundSweepSettings() {
        return fetch(BANK_MANAGEMENT_API + '/fund-sweep/settings', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (json && json.success && json.data && json.data.sweep_day_of_week) {
                    fsSweepDayOfWeek = parseInt(json.data.sweep_day_of_week, 10) || 4;
                }
                document.getElementById('fsSweepDaySelect').value = String(fsSweepDayOfWeek);
            })
            .catch(() => {});
    }

    document.getElementById('fsSettingsBtn').addEventListener('click', function () {
        const panel = document.getElementById('fsSettingsPanel');
        panel.style.display = panel.style.display === 'none' ? '' : 'none';
    });

    document.getElementById('fsSettingsSaveBtn').addEventListener('click', function () {
        const btn = this;
        const dayOfWeek = parseInt(document.getElementById('fsSweepDaySelect').value, 10);
        const hint = document.getElementById('fsSettingsHint');
        btn.disabled = true;
        hint.textContent = 'Saving…';
        fetch(BANK_MANAGEMENT_API + '/fund-sweep/settings', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ sweep_day_of_week: dayOfWeek }) })
            .then(r => r.json())
            .then(function (json) {
                btn.disabled = false;
                if (!json || !json.success) { hint.textContent = ''; toast((json && json.message) || 'Could not save setting.', true); return; }
                fsSweepDayOfWeek = parseInt(json.data.sweep_day_of_week, 10) || 4;
                hint.textContent = '';
                toast('Sweep day set to ' + FS_DAY_NAMES[fsSweepDayOfWeek % 7] + '.');
                fsUpdateProcessBtn();
                fsTickCountdown();
            })
            .catch(() => { btn.disabled = false; hint.textContent = ''; toast('Could not reach the server.', true); });
    });

    document.getElementById('fsSelectAll').addEventListener('change', function () {
        document.querySelectorAll('#fsBody .fs-row-check').forEach(cb => { cb.checked = this.checked; });
        fsUpdateProcessBtn();
    });

    function loadFundSweepPreview() {
        const tbody = document.getElementById('fsBody');
        const rcId = document.getElementById('fsRegionSelect').value;
        const processBtn = document.getElementById('fsProcessBtn');
        const selectAll = document.getElementById('fsSelectAll');
        document.getElementById('fsResults').style.display = 'none';
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="6">Loading…</td></tr>';
        processBtn.disabled = true;
        selectAll.checked = false;

        fetch(BANK_MANAGEMENT_API + '/fund-sweep/preview?rc_id=' + encodeURIComponent(rcId), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="6">No accounts on file for this Region/Office.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    const balance = parseFloat(r.current_balance) || 0;
                    const eligible = balance > 0 && !!r.main_depository_account_id;
                    const mainCell = r.main_depository_account_id
                        ? escapeHtml(r.main_depository_account_name || '')
                        : '<span style="color:var(--fims-text-subtle);">— Not assigned —</span>';
                    const glLabel = r.gl_account_code
                        ? escapeHtml(r.gl_account_code + ' — ' + (r.gl_account_title || ''))
                        : '<span style="color:var(--fims-text-subtle);">— Not tagged —</span>';
                    const slLabel = r.sl_account_code ? '<br><span style="font-size:11px;color:var(--fims-text-subtle);">SL: ' + escapeHtml(r.sl_account_code + ' — ' + (r.sl_account_title || '')) + '</span>' : '';
                    return '<tr class="fs-account-row" data-account-id="' + r.bank_account_id + '">' +
                        '<td>' + (eligible ? '<input type="checkbox" class="fs-row-check" data-account-id="' + r.bank_account_id + '" checked>' : '') + '</td>' +
                        '<td>' + escapeHtml(r.account_name) + (r.bank_name ? ' <span style="color:var(--fims-text-subtle);font-size:11px;">(' + escapeHtml(r.bank_name) + ')</span>' : '') + '</td>' +
                        '<td>' + glLabel + slLabel + '</td>' +
                        '<td class="text-right">' + balance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                        '<td>' + mainCell + '</td>' +
                        '<td class="text-center">' + (balance > 0 ? '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm fs-breakdown-toggle" data-account-id="' + r.bank_account_id + '" title="Show deposit breakdown"><i class="fas fa-chevron-down"></i></button>' : '') + '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('.fs-row-check').forEach(function (cb) {
                    cb.addEventListener('change', fsUpdateProcessBtn);
                });
                tbody.querySelectorAll('.fs-breakdown-toggle').forEach(function (btn) {
                    btn.addEventListener('click', function () { fsToggleBreakdown(this); });
                });
                selectAll.checked = tbody.querySelectorAll('.fs-row-check').length > 0;
                fsUpdateProcessBtn();
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="6">Could not reach the server.</td></tr>'; });
    }

    // Expands/collapses a nested breakdown row directly beneath the clicked
    // account row, listing the individual Collection Report Deposits that
    // make up its "Balance" figure (same scope as
    // BankManagementService::unsweptDepositBalanceSql() — up through
    // yesterday, weekends excluded, no lower age bound).
    function fsToggleBreakdown(btn) {
        const accountId = btn.dataset.accountId;
        const accountRow = btn.closest('tr.fs-account-row');
        const existing = accountRow.nextElementSibling;
        if (existing && existing.classList.contains('fs-breakdown-row')) {
            existing.remove();
            btn.querySelector('i').className = 'fas fa-chevron-down';
            return;
        }
        document.querySelectorAll('.fs-breakdown-row').forEach(function (row) {
            const toggleBtn = row.previousElementSibling && row.previousElementSibling.querySelector('.fs-breakdown-toggle i');
            if (toggleBtn) toggleBtn.className = 'fas fa-chevron-down';
            row.remove();
        });

        const detailRow = document.createElement('tr');
        detailRow.className = 'fs-breakdown-row';
        detailRow.innerHTML = '<td></td><td colspan="5" style="padding:0;">' +
            '<div style="padding:10px 14px;background:var(--fims-surface-2,#F7FAFC);">Loading…</div></td>';
        accountRow.parentNode.insertBefore(detailRow, accountRow.nextSibling);
        btn.querySelector('i').className = 'fas fa-chevron-up';

        fetch(BANK_MANAGEMENT_API + '/fund-sweep/deposits?bank_account_id=' + encodeURIComponent(accountId), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                const cell = detailRow.querySelector('td:last-child > div');
                if (!rows.length) {
                    cell.textContent = 'No un-swept deposits on record for this account.';
                    return;
                }
                let total = 0;
                const tableHtml = '<table class="fims-table" style="margin:0;"><thead><tr>' +
                    '<th>Date</th><th>Region / Office</th><th class="text-right">Amount</th>' +
                    '</tr></thead><tbody>' +
                    rows.map(function (d) {
                        total += parseFloat(d.amount) || 0;
                        const regionLabel = d.rc_code ? escapeHtml(d.rc_code + ' — ' + d.rc_name) : '—';
                        return '<tr>' +
                            '<td>' + escapeHtml(d.deposit_date || '—') + '</td>' +
                            '<td>' + regionLabel + (d.collecting_office ? ' <span style="color:var(--fims-text-subtle);font-size:11px;">(' + escapeHtml(d.collecting_office) + ')</span>' : '') + '</td>' +
                            '<td class="text-right">' + (parseFloat(d.amount) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
                            '</tr>';
                    }).join('') +
                    '</tbody><tfoot><tr style="font-weight:700;"><td colspan="2">Total</td><td class="text-right">' +
                    total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td></tr></tfoot></table>';
                cell.innerHTML = tableHtml;
                cell.style.padding = '10px 14px';
            })
            .catch(() => { detailRow.querySelector('td:last-child > div').textContent = 'Could not reach the server.'; });
    }

    document.getElementById('fsProcessBtn').addEventListener('click', function () {
        const btn = this;
        const rcId = document.getElementById('fsRegionSelect').value;
        const accountIds = Array.from(document.querySelectorAll('#fsBody .fs-row-check:checked'))
            .map(cb => parseInt(cb.dataset.accountId));
        if (!accountIds.length) { toast('Select at least one account to sweep.', true); return; }
        btn.disabled = true;
        document.getElementById('fsHint').textContent = 'Processing…';
        fetch(BANK_MANAGEMENT_API + '/fund-sweep/process', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ rc_id: rcId, account_ids: accountIds }) })
            .then(r => r.json())
            .then(function (json) {
                document.getElementById('fsHint').textContent = '';
                if (!json || !json.success) { toast((json && json.message) || 'Fund sweep failed.', true); return; }
                const created = json.data.created || [];
                const skipped = json.data.skipped || [];
                const resultsEl = document.getElementById('fsResults');
                resultsEl.style.display = '';
                let html = '';
                if (created.length) {
                    html += '<div style="color:#16a34a;font-weight:600;margin-bottom:4px;">Created ' + created.length + ' draft FTLA(s):</div>' +
                        '<ul style="margin:0 0 8px 18px;padding:0;">' + created.map(c => '<li>' + escapeHtml(c.account_name) + ' — ' + escapeHtml(c.ftla_number || '') + '</li>').join('') + '</ul>';
                }
                if (skipped.length) {
                    html += '<div style="color:var(--fims-text-subtle);font-weight:600;margin-bottom:4px;">Skipped ' + skipped.length + ':</div>' +
                        '<ul style="margin:0;padding:0 0 0 18px;color:var(--fims-text-subtle);">' + skipped.map(s => '<li>' + escapeHtml(s.account_name) + (s.reason ? ' — ' + escapeHtml(s.reason) : ' — no balance or no Main Depository assigned') + '</li>').join('') + '</ul>';
                }
                resultsEl.innerHTML = html || 'Nothing to sweep.';
                toast(created.length ? 'Fund sweep prepared ' + created.length + ' draft FTLA(s).' : 'Nothing was eligible to sweep.');
                loadFundSweepPreview();
            })
            .catch(() => { document.getElementById('fsHint').textContent = ''; toast('Could not reach the server.', true); btn.disabled = false; });
    });

    // ── Countdown to the next Sweep Window (every configured day, all day) ──
    // No automatic trigger — this just tells whoever's on the page when
    // the "Process Fund Sweep" button will next accept a click.
    function nextSweepDayStart() {
        const now = new Date();
        const target = new Date(now);
        target.setHours(0, 0, 0, 0);
        const jsDay = fsSweepDayOfWeek % 7; // ISO 7 (Sunday) -> JS 0
        const daysUntil = ((jsDay - now.getDay() + 7) % 7) || 7; // today's the day too — next week's instead
        target.setDate(now.getDate() + daysUntil);
        return target;
    }

    function fsTickCountdown() {
        const el = document.getElementById('fsCountdown');
        const label = document.getElementById('fsCountdownLabel');
        if (!el || !label) return;
        fsUpdateProcessBtn();
        const dayName = FS_DAY_NAMES[fsSweepDayOfWeek % 7];
        if (fsIsSweepDay()) {
            label.textContent = 'Sweep Window';
            el.textContent = 'Open today — Process Fund Sweep is available now.';
            return;
        }
        label.textContent = 'Next Sweep Window (' + dayName + ')';
        const diffMs = nextSweepDayStart().getTime() - Date.now();
        const totalSeconds = Math.max(0, Math.floor(diffMs / 1000));
        const days = Math.floor(totalSeconds / 86400);
        const hours = Math.floor((totalSeconds % 86400) / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        const pad = n => String(n).padStart(2, '0');
        el.textContent = days + 'd ' + pad(hours) + 'h ' + pad(minutes) + 'm ' + pad(seconds) + 's';
    }

    // ── Boot ── no tabs to gate behind anymore — load immediately.
    loadRegions();
    loadFundSweepPreview();
    loadFundSweepSettings().then(function () {
        fsTickCountdown();
    });
    fsTickCountdown();
    setInterval(fsTickCountdown, 1000);
})();
</script>
