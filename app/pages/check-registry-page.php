<?php
/**
 * Check Registry — Check Processing module.
 *
 * Split out of check-disbursement-voucher-page.php's former "Check Registry"
 * tab into its own standalone page (views/CheckRegistryPage.php, menu item
 * "Check Registry" already existed pointing here — it previously just
 * included check-disbursement-voucher-page.php as a placeholder). Reuses the
 * exact same backend: app/routes/check-processing.php's /registry/* and
 * /preparation/bank-accounts endpoints, backed by
 * app/services/CheckProcessingService.php — no backend changes needed for
 * this move.
 *
 * Blank check stock (booklets) inventory: register new check batches (by
 * range or individually) and browse them grouped by bank account — each
 * section is one bank account with all its booklets underneath, and a
 * low-stock badge on the section itself when any booklet's remaining
 * (UNUSED) check count is at/below REGISTRY_LOW_STOCK_THRESHOLD (10,
 * mirroring CheckProcessingService::RAF_LOW_STOCK_THRESHOLD) — computed
 * client-side from the same /registry/list rows already loaded, not a
 * separate top-of-page alert banner.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-modal-overlay, etc.) match app/fims-css/fims.css, same convention as
 * check-disbursement-voucher-page.php.
 */

namespace PHPMaker2024\FIMS;

$crCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $crCssVer ?>" />

<div class="fims-wrap" id="crModule">

    <!-- same banner as User Access: app-page-header (styled by app/fims-css/fims.css, already loaded above) -->
    <div class="app-page-header">
        <div class="app-page-header-icon"><i class="fas fa-book"></i></div>
        <div>
            <h1 class="app-page-title">Check Registry</h1>
            <p class="app-page-subtitle">Blank check stock (booklets) inventory — Disbursing Section</p>
        </div>
    </div>

    <div class="fims-stat-grid" id="registryKpiCards" style="grid-template-columns:repeat(3,1fr)">
        <div class="fims-stat-card fims-card-blue">
            <div class="fims-stat-top"><span class="fims-stat-label">Unused / Available</span></div>
            <div class="fims-stat-value" data-kpi="UNUSED">0</div>
        </div>
        <div class="fims-stat-card fims-card-green">
            <div class="fims-stat-top"><span class="fims-stat-label">Issued</span></div>
            <div class="fims-stat-value" data-kpi="ISSUED">0</div>
        </div>
        <div class="fims-stat-card fims-card-red">
            <div class="fims-stat-top"><span class="fims-stat-label">Cancelled</span></div>
            <div class="fims-stat-value" data-kpi="CANCELLED">0</div>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
        <div class="fims-toolbar">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <select class="fims-select fims-input-sm" id="regFilterOfficeType" style="width:150px">
                    <option value="">Office Type: All</option>
                    <option value="MAIN_OFFICE">Main Office</option>
                    <option value="REGION">Region</option>
                </select>
                <select class="fims-select fims-input-sm" id="regFilterBank" style="width:170px"><option value="">All Banks</option></select>
                <select class="fims-select fims-input-sm" id="regFilterSeries" style="width:150px"><option value="">All Series</option></select>
                <select class="fims-select fims-input-sm" id="regFilterStatus" style="width:150px">
                    <option value="">Status: All</option>
                    <option value="UNUSED">Unused</option>
                    <option value="ISSUED">Issued</option>
                    <option value="ENCASHED">Encashed</option>
                    <option value="OUTSTANDING">Outstanding</option>
                    <option value="CANCELLED">Cancelled</option>
                    <option value="VOIDED">Voided</option>
                    <option value="SPOILED">Spoiled</option>
                    <option value="STALE">Stale</option>
                </select>
                <select class="fims-select fims-input-sm" id="regFilterEntryType" style="width:150px">
                    <option value="">Entry Type: All</option>
                    <option value="RECEIVED">Received</option>
                    <option value="ISSUED">Issued</option>
                    <option value="CANCELLED">Cancelled</option>
                    <option value="VOIDED">Voided</option>
                    <option value="SPOILED">Spoiled</option>
                    <option value="STALE">Stale</option>
                    <option value="REPLACED">Replaced</option>
                </select>
                <input type="date" class="fims-input fims-input-sm" id="regFilterDateFrom">
                <input type="date" class="fims-input fims-input-sm" id="regFilterDateTo">
                <div class="fims-search-wrap">
                    <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" class="fims-input fims-input-sm" id="regFilterSearch" placeholder="Check # or Series or RAF" style="width:200px">
                </div>
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regSearchBtn"><i class="fas fa-search mr-1"></i>Search</button>
                <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="regClearBtn">Clear</button>
            </div>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
        <div class="fims-panel-header">
            <div class="fims-panel-title">Check Register</div>
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regAddBatchBtn"><i class="fas fa-plus mr-1"></i>Add Batch</button>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table" id="registryTable">
                <thead>
                    <tr>
                        <th>#</th><th>Booklet No.</th><th>Series</th><th>Dept. Type</th>
                        <th class="text-center">Checks</th><th class="text-center">Remaining</th><th>Status Summary</th>
                    </tr>
                </thead>
                <tbody><tr class="fims-empty"><td colspan="7">No records.</td></tr></tbody>
            </table>
        </div>
        <div class="fims-pagination" id="registryPagination"></div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Register Check Batch
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-add-batch" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:680px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Register Check Batch</div></div>
                    <button class="fims-modal-close" data-close="m-add-batch">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Bank Account <span class="text-danger">*</span></label><select class="fims-select" id="batchBankAccount"><option value="">Select bank account...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Batch Series <span class="text-danger">*</span></label><input type="text" class="fims-input" id="batchSeries" placeholder="e.g. CHK-A-2026"></div>
                        <div class="col-md-12"><label class="fims-label">Department Type <span class="text-danger">*</span></label>
                            <select class="fims-select" id="batchDeptType">
                                <option value="MAIN_OFFICE">Main Office</option>
                                <option value="REGION">Region</option>
                                <option value="BRANCH">Branch</option>
                                <option value="PROJECT">Project</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="fims-tabs-underline mt-2 mb-2" id="batchTabNav">
                        <button type="button" class="fims-tab-underline active" data-batch-tab="range">Range</button>
                        <button type="button" class="fims-tab-underline" data-batch-tab="individual">Individual Checks</button>
                    </div>

                    <div id="batchRangePanel">
                        <div class="row g-2">
                            <div class="col-md-5"><label class="fims-label">Start Check No.</label><input type="text" class="fims-input" id="batchRangeStart" placeholder="e.g. 0010440"></div>
                            <div class="col-md-5"><label class="fims-label">End Check No.</label><input type="text" class="fims-input" id="batchRangeEnd" placeholder="e.g. 0010490"></div>
                            <div class="col-md-2"><label class="fims-label">Date</label><input type="date" class="fims-input" id="batchRangeDate"></div>
                        </div>
                        <div id="batchRangePreview" class="text-muted small mt-2"></div>
                    </div>

                    <div id="batchIndividualPanel" style="display:none">
                        <table class="fims-table">
                            <thead><tr><th>Check No.</th><th style="width:150px">Entry Date</th><th style="width:40px"></th></tr></thead>
                            <tbody id="batchIndividualRows"></tbody>
                        </table>
                        <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="batchAddRowBtn"><i class="fas fa-plus mr-1"></i>Add Row</button>
                        <div id="batchIndividualPreview" class="text-muted small mt-2"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-add-batch">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="batchSaveBtn">Save Batch</button>
                </div>
            </div>
        </div>
    </div>

    <div id="crToast" style="display:none;position:fixed;bottom:20px;right:20px;z-index:2000;padding:10px 16px;border-radius:8px;color:#fff;font-size:13px;"></div>
</div>

<style>
/* Dark-blue theme: same convention as check-disbursement-voucher-page.php's
   #cpModule — this module's KPI cards/buttons use the brand blue-shade
   family instead of the shared rainbow accents, scoped to #crModule only. */
#crModule .fims-card-blue,
#crModule .fims-card-green,
#crModule .fims-card-amber,
#crModule .fims-card-red,
#crModule .fims-card-gray,
#crModule .fims-card-indigo,
#crModule .fims-card-violet { --fims-card-color: #000E3E; }

#crModule .fims-btn-primary { background:#000E3E; color:#fff; }
#crModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#crModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#crModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

/* Bank-account group header row inside #registryTable's tbody (not a real
   <thead> — it repeats once per bank account among the data rows), its bank
   logo chip, and the per-booklet low-stock highlight (same convention
   TREASURY's own check-inventory-raf.php used for its low-stock rows). */
#crModule #registryTable tr.reg-bank-group td {
    background: linear-gradient(0deg, #EEF1F8, #F6F8FC);
    font-weight: 700;
    color: #000E3E;
    font-size: 14px;
    padding: 14px 12px;
    border-top: 1px solid #000E3E;
    border-bottom: 1px solid var(--fims-border);
}
#crModule #registryTable tr.reg-bank-group:first-child td { border-top: none; }
#crModule #registryTable tr.reg-bank-group:hover td { background: linear-gradient(0deg, #E6EAF5, #EEF1F8); }
#crModule #registryTable tr.reg-bank-group .reg-chevron { color: #6B7390; transition: transform .15s ease; }
#crModule .reg-bank-logo {
    width: 56px; height: 56px; object-fit: contain; vertical-align: middle;
    margin-right: 12px;
}
#crModule .reg-bank-logo-fallback {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; height: 56px; border-radius: 8px; margin-right: 12px;
    background: #000E3E; color: #fff; font-size: 18px; font-weight: 700;
    vertical-align: middle;
}
#crModule .reg-booklet-count { font-weight: 400; color: var(--fims-text-subtle); margin-left: 4px; }
#crModule #registryTable tr.reg-booklet-row td { font-variant-numeric: tabular-nums; }
#crModule #registryTable tr.reg-booklet-row:hover td { background: #FAFBFD; }
#crModule #registryTable tr.reg-low-stock-row td { background: #FFFBEB; }
#crModule #registryTable tr.reg-low-stock-row:hover td { background: #FFF3CD; }
#crModule #registryTable tr.reg-low-stock-row td:nth-child(6) { color: #dc2626; font-weight: 700; }

/* Dark mode: native <select>s rendered grey — match them to the text inputs
   (filter bar + Register Check Batch modal). */
html.afims-dark[data-bs-theme="dark"] #crModule select.fims-select {
    color-scheme: dark;
    background-color: #1A1F38 !important;
    color: #E4ECF2 !important;
    border: 1px solid #2E3656 !important;
}
html.afims-dark[data-bs-theme="dark"] #crModule select.fims-select:hover { border-color: #4A5270 !important; }
html.afims-dark[data-bs-theme="dark"] #crModule select.fims-select:focus {
    border-color: #3861BC !important;
    box-shadow: 0 0 0 3px rgba(56,97,188,.25) !important;
    outline: none;
}
html.afims-dark[data-bs-theme="dark"] #crModule select.fims-select option {
    background-color: #1A1F38;
    color: #E4ECF2;
}
html.afims-dark[data-bs-theme="dark"] #crModule input.fims-input[type="date"] { color-scheme: dark; }
</style>

<script>
(function () {
    const API = '/api/check-processing';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }

    function toast(msg, isError) {
        const el = document.getElementById('crToast');
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

    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'flex';
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'none';
    }
    document.querySelectorAll('#crModule [data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#crModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(overlay.id); });
    });

    function fillSelect(selector, items, placeholder) {
        const sel = document.querySelector(selector);
        if (!sel) return;
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        (items || []).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id ?? item.bank_account_id ?? '';
            opt.textContent = item.name ?? item.account_name ?? '';
            sel.appendChild(opt);
        });
    }

    // ── Registry — ported from TREASURY's check-registry.php ──
    document.getElementById('regSearchBtn')?.addEventListener('click', loadRegistry);
    document.getElementById('regClearBtn')?.addEventListener('click', function () {
        ['regFilterOfficeType', 'regFilterBank', 'regFilterSeries', 'regFilterStatus', 'regFilterEntryType', 'regFilterDateFrom', 'regFilterDateTo', 'regFilterSearch'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        loadRegistry();
    });
    document.getElementById('regAddBatchBtn')?.addEventListener('click', function () {
        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#batchBankAccount', json.data, 'Select bank account...')).catch(() => {});
        document.getElementById('batchSeries').value = '';
        document.getElementById('batchRangeStart').value = '';
        document.getElementById('batchRangeEnd').value = '';
        document.getElementById('batchRangeDate').value = '';
        document.getElementById('batchRangePreview').textContent = '';
        document.getElementById('batchIndividualRows').innerHTML = '';
        document.getElementById('batchIndividualPreview').textContent = '';
        switchBatchTab('range');
        addIndividualRow();
        openModal('m-add-batch');
    });

    // ── Add Batch: Range / Individual tabs ──
    function switchBatchTab(tab) {
        document.querySelectorAll('#batchTabNav [data-batch-tab]').forEach(b => b.classList.toggle('active', b.dataset.batchTab === tab));
        document.getElementById('batchRangePanel').style.display = tab === 'range' ? '' : 'none';
        document.getElementById('batchIndividualPanel').style.display = tab === 'individual' ? '' : 'none';
    }
    document.querySelectorAll('#batchTabNav [data-batch-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () { switchBatchTab(this.dataset.batchTab); });
    });

    // Parses a check number string into {prefix, num, padLen} — e.g.
    // "0010440" -> {prefix:'', num:10440, padLen:7}. Mirrors TREASURY's
    // _parseCheckNumber() exactly: padLen comes from the literal digit
    // count the user typed, not the numeric magnitude, so a correctly
    // computed Math.max(start.padLen, end.padLen) never truncates when
    // padding every number in the range (see the Postgres LPAD pitfall
    // noted server-side in addRegisterBatch()'s docblock).
    function parseCheckNumber(val) {
        const m = String(val || '').match(/^(.*?)(\d+)$/);
        if (!m) return null;
        return { prefix: m[1], num: parseInt(m[2], 10), padLen: m[2].length };
    }

    function buildRangeChecks(startVal, endVal, date) {
        const s = parseCheckNumber(startVal);
        const e = parseCheckNumber(endVal);
        if (!s || !e) return { error: 'Check numbers must end with digits (e.g. 0010440).' };
        if (s.prefix !== e.prefix) return { error: 'Start and End check numbers have different prefixes.' };
        if (e.num < s.num) return { error: 'End check number must be ≥ Start check number.' };
        const count = e.num - s.num + 1;
        if (count > 2000) return { error: 'Range of ' + count + ' exceeds the 2000-check limit per batch.' };
        const padLen = Math.max(s.padLen, e.padLen);
        const checks = [];
        for (let n = s.num; n <= e.num; n++) {
            checks.push({ check_number: s.prefix + String(n).padStart(padLen, '0'), entry_date: date });
        }
        return { checks, count };
    }

    document.getElementById('batchRangeStart')?.addEventListener('input', updateRangePreview);
    document.getElementById('batchRangeEnd')?.addEventListener('input', updateRangePreview);
    function updateRangePreview() {
        const start = document.getElementById('batchRangeStart').value.trim();
        const end = document.getElementById('batchRangeEnd').value.trim();
        const preview = document.getElementById('batchRangePreview');
        if (!start || !end) { preview.textContent = ''; return; }
        const result = buildRangeChecks(start, end, '2026-01-01');
        preview.textContent = result.error ? result.error : (result.count + ' check(s) will be registered.');
        preview.style.color = result.error ? '#dc2626' : '';
    }

    function addIndividualRow() {
        const tbody = document.getElementById('batchIndividualRows');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" class="fims-input fims-input-sm ind-check-number"></td>' +
            '<td><input type="date" class="fims-input fims-input-sm ind-entry-date"></td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-row><i class="fas fa-trash"></i></button></td>';
        tr.querySelector('[data-remove-row]').addEventListener('click', function () { tr.remove(); });
        tbody.appendChild(tr);
    }
    document.getElementById('batchAddRowBtn')?.addEventListener('click', addIndividualRow);

    // Groups checks into "Booklet N" batches of 100 (by array index, not
    // check-number value), continuing from whatever booklet numbers
    // already exist for this bank account's check series — mirrors
    // TREASURY's _assignBookletNames()/_nextBookletStart().
    function assignBookletNames(checks, bankAccountId, series) {
        return fetch(API + '/registry/booklets?bank_account_id=' + encodeURIComponent(bankAccountId), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                let next = 1;
                ((json && json.data) || []).forEach(function (b) {
                    if (b.check_series !== series) return;
                    const m = /^Booklet\s+(\d+)$/i.exec(b.booklet_no || '');
                    if (m) next = Math.max(next, parseInt(m[1], 10) + 1);
                });
                checks.forEach(function (c, i) {
                    c.booklet_no = 'Booklet ' + (next + Math.floor(i / 100));
                });
                return checks;
            })
            .catch(() => { checks.forEach(c => { c.booklet_no = ''; }); return checks; });
    }

    document.getElementById('batchSaveBtn')?.addEventListener('click', function () {
        const bankAccountId = document.getElementById('batchBankAccount').value;
        const series = document.getElementById('batchSeries').value.trim();
        const deptType = document.getElementById('batchDeptType').value;
        if (!bankAccountId) { toast('Select a bank account.', true); return; }
        if (!series) { toast('Batch series is required.', true); return; }

        const activeTab = document.querySelector('#batchTabNav [data-batch-tab].active')?.dataset.batchTab;
        let checks = [];
        if (activeTab === 'range') {
            const start = document.getElementById('batchRangeStart').value.trim();
            const end = document.getElementById('batchRangeEnd').value.trim();
            const date = document.getElementById('batchRangeDate').value;
            if (!date) { toast('Date is required.', true); return; }
            const result = buildRangeChecks(start, end, date);
            if (result.error) { toast(result.error, true); return; }
            checks = result.checks;
        } else {
            document.querySelectorAll('#batchIndividualRows tr').forEach(function (tr) {
                const checkNumber = tr.querySelector('.ind-check-number').value.trim();
                const entryDate = tr.querySelector('.ind-entry-date').value;
                if (checkNumber && entryDate) checks.push({ check_number: checkNumber, entry_date: entryDate });
            });
            if (!checks.length) { toast('Add at least one check.', true); return; }
        }

        const btn = this;
        btn.disabled = true;
        assignBookletNames(checks, bankAccountId, series).then(function (checksWithBooklets) {
            const payload = { bank_account_id: bankAccountId, check_series: series, department_type: deptType, checks: checksWithBooklets };
            return fetch(API + '/registry/add-batch', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
                .then(r => r.json())
                .then(function (json) {
                    toast(json.message || (json.success ? ('Registered ' + json.data.inserted + ' check(s).') : 'Could not save batch.'), !json.success);
                    if (json.success) {
                        closeModal('m-add-batch');
                        loadRegistrySeries();
                        loadRegistry();
                    }
                });
        }).finally(() => { btn.disabled = false; });
    });

    function loadRegistryFilters() {
        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#regFilterBank', json.data, 'All Banks')).catch(() => {});
        loadRegistrySeries();
    }

    function loadRegistrySeries() {
        fetch(API + '/registry/series-list', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const sel = document.getElementById('regFilterSeries');
                const current = sel.value;
                sel.innerHTML = '<option value="">All Series</option>';
                ((json && json.data) || []).forEach(function (row) {
                    const opt = document.createElement('option');
                    opt.value = row.check_series;
                    opt.textContent = row.check_series + ' (' + row.total_checks + ')';
                    sel.appendChild(opt);
                });
                sel.value = current;
            })
            .catch(() => {});
    }

    function buildRegistryParams() {
        const params = new URLSearchParams();
        const map = {
            regFilterOfficeType: 'office_type', regFilterBank: 'bank_account_id', regFilterSeries: 'check_series',
            regFilterStatus: 'status', regFilterEntryType: 'entry_type',
            regFilterDateFrom: 'date_from', regFilterDateTo: 'date_to', regFilterSearch: 'search',
        };
        Object.keys(map).forEach(function (id) {
            const val = document.getElementById(id)?.value?.trim();
            if (val) params.set(map[id], val);
        });
        return params;
    }

    const REGISTRY_STATUS_BADGE = {
        UNUSED: 'fims-badge-blue', ISSUED: 'fims-badge-green', ENCASHED: 'fims-badge-indigo',
        OUTSTANDING: 'fims-badge-amber', CANCELLED: 'fims-badge-red', VOIDED: 'fims-badge-gray',
        SPOILED: 'fims-badge-gray', STALE: 'fims-badge-amber',
    };

    // Resolves a bank name to its logo asset under /_assets/image-logos —
    // same bank-name matching rules check-disbursement-voucher-page.php's
    // getCheckTemplate() uses for the printed check letterhead, extended
    // with BDO since that asset already exists in the same folder. Returns
    // null for an unrecognized bank so the caller can fall back to an
    // initials chip instead of a broken <img>.
    function bankLogoUrl(bankName) {
        const bn = (bankName || '').toUpperCase();
        if (bn.includes('DEVELOPMENT BANK') || bn.includes('DBP')) return '/_assets/image-logos/DBP_company_logo.svg.png';
        if (bn.includes('VETERANS') || bn.includes('PVB')) return '/_assets/image-logos/Philippine_Veterans_Bank_logo.svg';
        if (bn.includes('BDO') || bn.includes('UNIBANK')) return '/_assets/image-logos/BDO_Unibank_(logo).svg.webp';
        if (bn.includes('LAND BANK') || bn.includes('LANDBANK') || bn.includes('LBP')) return '/_assets/image-logos/Landbank_New.png';
        return null;
    }

    function bankLogoInitials(bankName) {
        const words = (bankName || '').trim().split(/\s+/).filter(Boolean);
        return (words[0]?.[0] || '') + (words[1]?.[0] || '');
    }

    function bankLogoHtml(bankName) {
        const url = bankLogoUrl(bankName);
        const fallback = '<span class="reg-bank-logo-fallback">' + escapeHtml(bankLogoInitials(bankName)).toUpperCase() + '</span>';
        if (!url) return fallback;
        // onerror swaps in the initials chip if the logo asset 404s, using
        // this.replaceWith() (not outerHTML, which would need HTML-escaping
        // nested quotes inside an already-double-quoted attribute).
        return '<img class="reg-bank-logo" src="' + url + '" alt="" data-fallback="' + escapeHtml(fallback.replace(/"/g, '&quot;')) + '" onerror="var d=document.createElement(\'span\'); d.innerHTML=this.dataset.fallback; this.replaceWith(d.firstChild);">';
    }

    // Groups the flat register list by (bank account, booklet) — mirrors
    // TREASURY's bank -> series -> booklet grouping, collapsed to one
    // level since booklet_no already carries the series context. Rows with
    // no booklet_no (individually-added checks) fall into their own
    // "No Booklet" group per bank account rather than being hidden. This
    // tab shows only these booklet/batch-level summaries — never the
    // individual check rows within a booklet.
    function groupRegistryRows(rows) {
        const groups = [];
        const index = {};
        rows.forEach(function (r) {
            const key = r.bank_account_id + '|' + (r.booklet_no || '');
            if (!index[key]) {
                index[key] = {
                    id: groups.length, bank_account_id: r.bank_account_id,
                    account_name: r.account_name, bank_name: r.bank_name,
                    booklet_no: r.booklet_no || null, check_series: r.check_series,
                    department_type: r.department_type, rows: [],
                };
                groups.push(index[key]);
            }
            index[key].rows.push(r);
        });
        return groups;
    }

    // Low-stock threshold for a booklet's remaining (UNUSED) checks — mirrors
    // CheckProcessingService::RAF_LOW_STOCK_THRESHOLD (10) so this page and
    // the Inventory (RAF) tab always agree on what counts as low stock.
    const REGISTRY_LOW_STOCK_THRESHOLD = 10;

    function bookletRemaining(booklet) {
        return booklet.rows.filter(r => r.current_status === 'UNUSED').length;
    }

    // Nests the flat booklet groups under their owning bank account — the
    // registry table now reads as one section per bank account (with a
    // low-stock flag on the section itself) rather than a flat booklet list
    // that repeats the bank/account name on every row.
    function groupByBankAccount(bookletGroups) {
        const banks = [];
        const index = {};
        bookletGroups.forEach(function (g) {
            const key = String(g.bank_account_id);
            if (!index[key]) {
                index[key] = { bank_account_id: g.bank_account_id, account_name: g.account_name, bank_name: g.bank_name, booklets: [] };
                banks.push(index[key]);
            }
            index[key].booklets.push(g);
        });
        banks.forEach(function (b) {
            b.lowStockCount = b.booklets.filter(g => bookletRemaining(g) <= REGISTRY_LOW_STOCK_THRESHOLD).length;
        });
        return banks;
    }

    function registryGroupSummary(rows) {
        const counts = {};
        rows.forEach(function (r) { counts[r.current_status] = (counts[r.current_status] || 0) + 1; });
        return Object.keys(counts).map(function (status) {
            const label = status.charAt(0) + status.slice(1).toLowerCase();
            const badgeClass = REGISTRY_STATUS_BADGE[status] || 'fims-badge-gray';
            return '<span class="fims-badge ' + badgeClass + '" style="margin-right:4px;">' + counts[status] + ' ' + escapeHtml(label) + '</span>';
        }).join(' ');
    }

    // Client-side pagination (no refetch) over bank-account groups — each
    // page shows a handful of whole bank accounts (with all their booklets),
    // not a fixed slice of booklet rows, so a bank's low-stock flag and its
    // booklets always stay together.
    const REG_BANKS_PER_PAGE = 10;
    let regBankGroups = [];
    let regPage = 1;

    function loadRegistry() {
        fetch(API + '/registry/summary', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#registryKpiCards [data-kpi]').forEach(el => { el.textContent = d[el.dataset.kpi] ?? 0; });
            }).catch(() => {});

        const tbody = document.querySelector('#registryTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        fetch(API + '/registry/list?' + buildRegistryParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                regBankGroups = groupByBankAccount(groupRegistryRows(rows));
                regPage = 1;
                renderRegistryPage();
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Could not reach the server.</td></tr>'; });
    }

    // Expand/collapse state per bank account, keyed by bank_account_id —
    // persists across re-renders (filter/search/pagination) within this
    // page load, so a toggle the user already made doesn't snap back. Every
    // bank account starts collapsed; the low-stock badge on its header row
    // is what surfaces the alert without needing it auto-expanded.
    const regBankExpanded = {};

    // One collapsible section per bank account: a header row (account/bank
    // name + a low-stock badge if any of its booklets are at/below
    // threshold) that toggles its own booklet summary rows (never the
    // individual check rows within a booklet) open/closed on click.
    function renderRegistryPage() {
        const tbody = document.querySelector('#registryTable tbody');
        if (!regBankGroups.length) {
            tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">No records.</td></tr>';
            renderRegistryPagination();
            return;
        }
        const lastPage = Math.max(1, Math.ceil(regBankGroups.length / REG_BANKS_PER_PAGE));
        regPage = Math.min(Math.max(1, regPage), lastPage);
        const start = (regPage - 1) * REG_BANKS_PER_PAGE;
        const pageBanks = regBankGroups.slice(start, start + REG_BANKS_PER_PAGE);

        let rowNum = 0;
        tbody.innerHTML = pageBanks.map(function (b) {
            if (!(b.bank_account_id in regBankExpanded)) regBankExpanded[b.bank_account_id] = false;
            const expanded = regBankExpanded[b.bank_account_id];

            const bankLabel = b.account_name
                ? escapeHtml(b.account_name) + (b.bank_name ? ' — ' + escapeHtml(b.bank_name) : '')
                : '—';
            const lowStockBadge = b.lowStockCount
                ? ' <span class="fims-badge fims-badge-red"><i class="fas fa-exclamation-triangle"></i> ' + b.lowStockCount + ' low stock</span>'
                : '';
            const chevron = '<i class="fas fa-chevron-' + (expanded ? 'down' : 'right') + ' reg-chevron" style="width:14px;margin-right:8px;"></i>';
            const headerRow = '<tr class="reg-bank-group" data-bank-toggle="' + b.bank_account_id + '" style="cursor:pointer;">' +
                '<td colspan="7">' + chevron + bankLogoHtml(b.bank_name) + bankLabel +
                '<span class="reg-booklet-count">(' + b.booklets.length + ' booklet(s))</span>' + lowStockBadge + '</td></tr>';
            const bookletRows = b.booklets.map(function (g) {
                const remaining = bookletRemaining(g);
                const isLow = remaining <= REGISTRY_LOW_STOCK_THRESHOLD;
                rowNum++;
                const rowClass = ['reg-booklet-row'];
                if (isLow) rowClass.push('reg-low-stock-row');
                return '<tr class="' + rowClass.join(' ') + '" data-bank-rows="' + b.bank_account_id + '"' + (expanded ? '' : ' style="display:none;"') + '>' +
                    '<td>' + rowNum + '</td>' +
                    '<td>' + escapeHtml(g.booklet_no || 'No Booklet') + '</td>' +
                    '<td><span class="fims-badge">' + escapeHtml(g.check_series || '—') + '</span></td>' +
                    '<td>' + escapeHtml(g.department_type || '—') + '</td>' +
                    '<td class="text-center">' + g.rows.length + '</td>' +
                    '<td class="text-center">' + remaining + (isLow ? ' <i class="fas fa-exclamation-triangle" title="Low stock"></i>' : '') + '</td>' +
                    '<td>' + (registryGroupSummary(g.rows) || '—') + '</td>' +
                    '</tr>';
            }).join('');
            return headerRow + bookletRows;
        }).join('');

        tbody.querySelectorAll('[data-bank-toggle]').forEach(function (tr) {
            tr.addEventListener('click', function () {
                const id = this.dataset.bankToggle;
                regBankExpanded[id] = !regBankExpanded[id];
                const expanded = regBankExpanded[id];
                this.querySelector('.reg-chevron').className = 'fas fa-chevron-' + (expanded ? 'down' : 'right') + ' reg-chevron';
                tbody.querySelectorAll('[data-bank-rows="' + id + '"]').forEach(function (row) {
                    row.style.display = expanded ? '' : 'none';
                });
            });
        });
        renderRegistryPagination();
    }

    function renderRegistryPagination() {
        const box = document.getElementById('registryPagination');
        const totalBooklets = regBankGroups.reduce((sum, b) => sum + b.booklets.length, 0);
        const totalChecks = regBankGroups.reduce((sum, b) => sum + b.booklets.reduce((s, g) => s + g.rows.length, 0), 0);
        const lastPage = Math.max(1, Math.ceil(regBankGroups.length / REG_BANKS_PER_PAGE));
        if (!regBankGroups.length) { box.innerHTML = ''; return; }

        const pages = [];
        for (let i = Math.max(1, regPage - 2); i <= Math.min(lastPage, regPage + 2); i++) {
            pages.push('<button type="button" class="fims-page-btn' + (i === regPage ? ' active' : '') + '" data-reg-page="' + i + '">' + i + '</button>');
        }
        box.innerHTML =
            '<span style="font-size:12px;color:var(--fims-text-subtle);margin-right:10px;">' + regBankGroups.length + ' bank account(s), ' + totalBooklets + ' booklet(s), ' + totalChecks + ' check(s)</span>' +
            '<button type="button" class="fims-page-btn" data-reg-page="' + (regPage - 1) + '"' + (regPage === 1 ? ' disabled' : '') + '>‹</button>' +
            pages.join('') +
            '<button type="button" class="fims-page-btn" data-reg-page="' + (regPage + 1) + '"' + (regPage === lastPage ? ' disabled' : '') + '>›</button>';
        box.querySelectorAll('[data-reg-page]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                regPage = parseInt(this.dataset.regPage, 10);
                renderRegistryPage();
            });
        });
    }

    // ── Boot ──
    loadRegistryFilters();
    loadRegistry();
})();
</script>
