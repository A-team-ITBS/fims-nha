<?php

namespace PHPMaker2024\FIMS;

/**
 * payroll-page.php — Payroll Management (PayrollPage). A separate module from Payroll Setup (payroll-setup-page.php,
 * the payroll items master): this page only IMPORTS the monthly employee payroll (the Regular FIMS MONTHLY PAYROLL
 * Excel template) and lists one summary row per imported month: Payroll Month, Year, Import Date, Status, Total
 * Payroll Amount (gross). No DV creation here — the payroll DV is prepared in the DV module from an imported month.
 * Each template column is matched to a Payroll Setup item by its item code.
 * API: app/routes/payroll_management.php → app/services/PayrollManagementService.php.
 */
@session_start();
$_pr_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_pr_api = preg_match('~/api$~', $_pr_api) ? $_pr_api : $_pr_api . '/api';
?>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
#pr-root .nha-summary { grid-template-columns: repeat(4, 1fr); }
#pr-root .pr-num { text-align: right; font-family: 'IBM Plex Mono', monospace; white-space: nowrap; }
#pr-root .pr-modal { max-width: 640px; }
#pr-root .pr-form { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
#pr-root .pr-form .pr-full { grid-column: 1 / -1; }
#pr-root .pr-form label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
#pr-root .pr-form select, #pr-root .pr-form input[type=file] { width: 100%; padding: 8px 10px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 6px; font: inherit; background: var(--afims-surface, #fff); color: inherit; }
#pr-root .pr-hint { font-size: 12px; color: var(--afims-text-muted, #62657E); margin-top: 4px; }
#pr-root .pr-preview { margin-top: 16px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px; padding: 12px 14px; display: none; }
#pr-root .pr-preview.visible { display: block; }
#pr-root .pr-preview dl { display: grid; grid-template-columns: 1fr auto; gap: 6px 16px; margin: 0; font-size: 13px; }
#pr-root .pr-preview dt { color: var(--afims-text-muted, #62657E); }
#pr-root .pr-preview dd { margin: 0; text-align: right; font-weight: 600; font-family: 'IBM Plex Mono', monospace; }
#pr-root .pr-msg { margin-top: 12px; font-size: 12.5px; padding: 8px 10px; border-radius: 6px; display: none; }
#pr-root .pr-msg.err  { display: block; background: var(--red-bg, #FDECEC); color: var(--red-txt, #B42318); }
#pr-root .pr-msg.warn { display: block; background: var(--amber-bg, #FFF6E5); color: var(--amber-txt, #92400E); }
#pr-root .pr-msg ul { margin: 6px 0 0 18px; padding: 0; }
@media (max-width: 900px) { #pr-root .nha-summary { grid-template-columns: repeat(2, 1fr); } }
</style>

<div id="pr-root" class="nha-root">

    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.36-1.86M17 20H7m10 0v-2c0-.66-.13-1.28-.36-1.86M7 20H2v-2a3 3 0 015.36-1.86M7 20v-2c0-.66.13-1.28.36-1.86m0 0a5 5 0 019.28 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Payroll Management</div>
                <div class="nha-hdr-sub">Import the monthly employee payroll. The payroll DV is prepared in Disbursement Voucher from an imported month.</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="pr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="pr-import-btn" type="button" class="nha-btn-hdr">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Import Payroll
            </button>
        </div>
    </div>

    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Imported Months</div><div class="nha-sc-val" id="pr-kpi-imports">—</div></div></div><p class="nha-kpi-sub">In the selected year</p></div>
        <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Payroll (Gross)</div><div class="nha-sc-val" id="pr-kpi-gross">—</div></div></div><p class="nha-kpi-sub">Sum of the imports listed</p></div>
        <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Net Pay</div><div class="nha-sc-val" id="pr-kpi-net">—</div></div></div><p class="nha-kpi-sub">Gross less employee deductions</p></div>
        <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">Awaiting DV</div><div class="nha-sc-val" id="pr-kpi-pending">—</div></div></div><p class="nha-kpi-sub">Imported months with no DV yet</p></div>
    </div>

    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="pr-f-year">Year</label>
                <select id="pr-f-year" class="nha-filter-select"><option value="">All Years</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="pr-f-status">Status</label>
                <select id="pr-f-status" class="nha-filter-select">
                    <option value="">All Statuses</option>
                    <option value="IMPORTED">Imported (no DV yet)</option>
                    <option value="PARTIAL_DV">Partly in a DV</option>
                    <option value="DV_CREATED">DV Created</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button type="button" id="pr-reset" class="nha-btn nha-btn-outline">Reset</button>
            </div>
        </div>
    </div>

    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Payroll Month</th>
                        <th>Year</th>
                        <th>Import Date</th>
                        <th>Status</th>
                        <th class="text-end">Total Payroll Amount</th>
                        <th class="text-end">Net Pay</th>
                        <th class="text-end">Employees</th>
                        <th>Disbursement Voucher</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="pr-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Import modal -->
    <div id="pr-overlay" class="nha-overlay">
        <div class="nha-modal pr-modal" role="dialog" aria-modal="true" aria-labelledby="pr-modal-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="pr-modal-title">Import Monthly Payroll</h3>
                <button type="button" class="nha-modal-close" id="pr-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="pr-form">
                    <div>
                        <label for="pr-month">Payroll Month <span class="nha-req">*</span></label>
                        <select id="pr-month"></select>
                    </div>
                    <div>
                        <label for="pr-year">Payroll Year <span class="nha-req">*</span></label>
                        <select id="pr-year"></select>
                    </div>
                    <div class="pr-full">
                        <label for="pr-file">Payroll File (.xlsx) <span class="nha-req">*</span></label>
                        <input type="file" id="pr-file" accept=".xlsx">
                        <div class="pr-hint">The Regular FIMS MONTHLY PAYROLL template: column headers on row 1 (account codes on row 2 are optional). Each amount column must be a payroll item in Payroll Setup with the same item code.</div>
                    </div>
                </div>
                <div class="pr-preview" id="pr-preview">
                    <dl>
                        <dt>Payroll</dt><dd id="pr-pv-label">—</dd>
                        <dt>Employees</dt><dd id="pr-pv-count">—</dd>
                        <dt>Total Payroll Amount (Gross)</dt><dd id="pr-pv-gross">—</dd>
                        <dt>Employee Deductions</dt><dd id="pr-pv-ded">—</dd>
                        <dt>Refunds to Employees</dt><dd id="pr-pv-ref">—</dd>
                        <dt>Net Pay</dt><dd id="pr-pv-net">—</dd>
                        <dt>Employer Share (reference)</dt><dd id="pr-pv-er">—</dd>
                    </dl>
                </div>
                <div class="pr-msg" id="pr-msg"></div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-outline" id="pr-cancel">Cancel</button>
                <button type="button" class="nha-btn nha-btn-outline" id="pr-preview-btn">Preview</button>
                <button type="button" class="nha-btn nha-btn-primary" id="pr-save" disabled>Import</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   PAYROLL MANAGEMENT   prefix: pr_
═══════════════════════════════════════════════════════════════ */
const PR_API = <?= json_encode($_pr_api) ?>;
const PR_MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
const $pr = id => document.getElementById(id);
let pr_previewed = false;   // the file / month currently in the form was previewed successfully

function pr_authFetch(path, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(PR_API + '/' + path, { credentials: 'same-origin', ...opts, headers });
}
async function pr_json(res) {
    if (res.status === 401) throw new Error('Your session has expired. Please refresh the page and log in again.');
    return res.json().catch(() => ({ success: false, message: 'HTTP ' + res.status }));
}
const pr_peso = v => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const pr_esc = s => { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; };
const pr_date = s => { if (!s) return '—'; const d = new Date(String(s).replace(' ', 'T')); return isNaN(d) ? String(s) : d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: '2-digit', hour: '2-digit', minute: '2-digit' }); };

/* ── List ───────────────────────────────────────────────────── */
async function pr_load() {
    const params = new URLSearchParams();
    if ($pr('pr-f-year').value) params.set('year', $pr('pr-f-year').value);
    if ($pr('pr-f-status').value) params.set('status', $pr('pr-f-status').value);
    const body = $pr('pr-tbody');
    body.innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/list?' + params));
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        pr_fillYears(json.years || []);
        const k = json.kpis || {};
        $pr('pr-kpi-imports').textContent = k.imports ?? 0;
        $pr('pr-kpi-gross').textContent   = pr_peso(k.total_gross);
        $pr('pr-kpi-net').textContent     = pr_peso(k.total_net);
        $pr('pr-kpi-pending').textContent = k.without_dv ?? 0;
        const rows = json.data || [];
        if (!rows.length) {
            body.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No payroll imported yet.</span><div class="nha-empty-text">Use Import Payroll to bring in a month\'s payroll file.</div></div></td></tr>';
            return;
        }
        body.innerHTML = rows.map(r => {
            const dv = r.dv_nos ? `<span class="nha-mono">${pr_esc(r.dv_nos)}</span> <span class="nha-bdg nha-bdg-gray">${pr_esc(r.dv_statuses || '')}</span>` : '<span class="nha-text-muted">—</span>';
            const status = r.import_status === 'DV_CREATED' ? '<span class="nha-bdg nha-bdg-green">DV Created</span>'
                : r.import_status === 'PARTIAL_DV' ? '<span class="nha-bdg nha-bdg-blue">Partly in a DV</span>'
                : '<span class="nha-bdg nha-bdg-amber">Imported</span>';
            const locked = ['LOCKED', 'CLOSED'].includes(String(r.period_status || '').toUpperCase());
            const canDelete = !r.dv_nos && !locked;
            return `<tr>
                <td><strong>${pr_esc(r.month_name)}</strong></td>
                <td>${pr_esc(r.fiscal_yr)}</td>
                <td>${pr_esc(pr_date(r.imported_at))}${r.imported_by_name ? `<div class="pr-hint">by ${pr_esc(r.imported_by_name)}</div>` : ''}</td>
                <td>${status}</td>
                <td class="pr-num">${pr_peso(r.total_gross)}</td>
                <td class="pr-num">${pr_peso(r.total_net)}</td>
                <td class="pr-num">${pr_esc(r.employee_count)}</td>
                <td>${dv}</td>
                <td>${canDelete ? `<button type="button" class="nha-btn-act" onclick="pr_delete(${Number(r.payroll_period_id)}, '${pr_esc(r.month_name)} ${pr_esc(r.fiscal_yr)}')">Delete</button>` : `<span class="pr-hint">${locked ? 'Period ' + pr_esc(r.period_status) : 'In use by a DV'}</span>`}</td>
            </tr>`;
        }).join('');
    } catch (e) {
        body.innerHTML = `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${pr_esc(e.message)}</span></div></td></tr>`;
    }
}

function pr_fillYears(years) {
    const sel = $pr('pr-f-year'), cur = sel.value;
    const now = new Date().getFullYear();
    const all = [...new Set([...years.map(Number), now])].sort((a, b) => b - a);
    sel.innerHTML = '<option value="">All Years</option>' + all.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = cur;
}

async function pr_delete(id, label) {
    if (!confirm(`Delete the imported ${label} payroll (all its employee records)? It can be imported again afterwards.`)) return;
    const json = await pr_json(await pr_authFetch('payroll-management/delete/' + id, { method: 'DELETE' }));
    if (!json.success) { alert(json.message || 'Failed to delete.'); return; }
    pr_load();
}

/* ── Import modal ───────────────────────────────────────────── */
function pr_open() {
    const now = new Date();
    $pr('pr-month').innerHTML = PR_MONTHS.map((m, i) => `<option value="${i + 1}">${m}</option>`).join('');
    $pr('pr-month').value = String(now.getMonth() + 1);
    const y = now.getFullYear();
    $pr('pr-year').innerHTML = [y + 1, y, y - 1, y - 2].map(v => `<option value="${v}">${v}</option>`).join('');
    $pr('pr-year').value = String(y);
    $pr('pr-file').value = '';
    pr_resetPreview();
    $pr('pr-overlay').classList.add('open');
}
function pr_close() { $pr('pr-overlay').classList.remove('open'); }
function pr_resetPreview() {
    pr_previewed = false;
    $pr('pr-save').disabled = true;
    $pr('pr-save').textContent = 'Import';
    $pr('pr-preview').classList.remove('visible');
    pr_msg('', '');
}
function pr_msg(kind, html) {
    const m = $pr('pr-msg');
    m.className = 'pr-msg' + (kind ? ' ' + kind : '');
    m.innerHTML = html;
}
function pr_formData(replace) {
    const fd = new FormData();
    fd.append('payroll_file', $pr('pr-file').files[0]);
    fd.append('payroll_month', $pr('pr-month').value);
    fd.append('payroll_year', $pr('pr-year').value);
    if (replace) fd.append('replace', '1');
    return fd;
}

async function pr_preview() {
    pr_resetPreview();
    if (!$pr('pr-file').files.length) { pr_msg('err', 'Choose the payroll file (.xlsx).'); return; }
    window.PagePreloader?.show();
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/preview', { method: 'POST', body: pr_formData(false) }));
        if (!json.success) {
            pr_msg('err', pr_esc(json.message || 'The file could not be read.') + (json.errors?.length ? '<ul>' + json.errors.map(e => `<li>${pr_esc(e)}</li>`).join('') + '</ul>' : ''));
            return;
        }
        const d = json.data;
        $pr('pr-pv-label').textContent = d.label;
        $pr('pr-pv-count').textContent = d.employee_count;
        $pr('pr-pv-gross').textContent = pr_peso(d.total_gross);
        $pr('pr-pv-ded').textContent   = pr_peso(d.total_deductions);
        $pr('pr-pv-ref').textContent   = pr_peso(d.total_refunds);
        $pr('pr-pv-net').textContent   = pr_peso(d.total_net);
        $pr('pr-pv-er').textContent    = pr_peso(d.total_employer_share);
        $pr('pr-preview').classList.add('visible');

        if (d.existing?.blocked) {
            pr_msg('err', pr_esc(d.existing.blocked));
            return;
        }
        if (d.blocking?.length) {
            pr_msg('err', '<strong>Cannot import yet:</strong><ul>' + d.blocking.map(b => `<li>${pr_esc(b)}</li>`).join('') + '</ul>');
            return;
        }
        const replace = !!(d.existing && d.existing.record_count > 0);
        const notes = [];
        if (replace) notes.push(`${pr_esc(d.label)} was already imported — importing replaces it.`);
        if (d.skipped_columns?.length) notes.push('Columns not imported (the payroll item is inactive in Payroll Setup): ' + d.skipped_columns.map(pr_esc).join(', '));
        (d.warnings || []).slice(0, 10).forEach(w => notes.push(pr_esc(w)));
        if (notes.length) pr_msg('warn', '<strong>Check before importing:</strong><ul>' + notes.map(n => `<li>${n}</li>`).join('') + '</ul>');

        pr_previewed = { replace };
        $pr('pr-save').disabled = false;
        $pr('pr-save').textContent = replace ? 'Replace Import' : 'Import';
    } catch (e) {
        pr_msg('err', pr_esc(e.message));
    } finally {
        window.PagePreloader?.hide();
    }
}

async function pr_import() {
    if (!pr_previewed) return;
    $pr('pr-save').disabled = true;
    window.PagePreloader?.show();
    try {
        const json = await pr_json(await pr_authFetch('payroll-management/import', { method: 'POST', body: pr_formData(pr_previewed.replace) }));
        if (!json.success) {
            pr_msg('err', pr_esc(json.message || 'Import failed.') + (json.errors?.length ? '<ul>' + json.errors.map(e => `<li>${pr_esc(e)}</li>`).join('') + '</ul>' : ''));
            $pr('pr-save').disabled = false;
            return;
        }
        pr_close();
        pr_load();
    } catch (e) {
        pr_msg('err', pr_esc(e.message));
        $pr('pr-save').disabled = false;
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Boot ───────────────────────────────────────────────────── */
function pr_boot() {
    $pr('pr-refresh').addEventListener('click', pr_load);
    $pr('pr-f-year').addEventListener('change', pr_load);
    $pr('pr-f-status').addEventListener('change', pr_load);
    $pr('pr-reset').addEventListener('click', () => { $pr('pr-f-year').value = ''; $pr('pr-f-status').value = ''; pr_load(); });
    $pr('pr-import-btn').addEventListener('click', pr_open);
    $pr('pr-close').addEventListener('click', pr_close);
    $pr('pr-cancel').addEventListener('click', pr_close);
    $pr('pr-preview-btn').addEventListener('click', pr_preview);
    $pr('pr-save').addEventListener('click', pr_import);
    ['pr-month', 'pr-year', 'pr-file'].forEach(id => $pr(id).addEventListener('change', pr_resetPreview));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && $pr('pr-overlay').classList.contains('open')) pr_close(); });
    pr_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pr_boot);
else pr_boot();
</script>
