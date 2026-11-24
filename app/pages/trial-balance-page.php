<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Trial Balance — total debit/credit per account, as of whatever has been posted so far
// (fims.v_trial_balance, over fims.general_ledger), laid out like the standard NGAS/COA Trial
// Balance report: a centered title block, then accounts grouped under a bold major classification
// header (Assets/Liabilities/Equity/Revenue/Expenses) and a bold account-group sub-header (e.g.
// "Cash and Cash Equivalents") — both resolved from chart_of_accounts.parent_account_id, see
// GeneralLedgerService::attachAccountGroups(). Read-only — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8. Separate module from Journals (chronological register),
// General Ledger (per-account ledger card) and Subsidiary Ledger (per-SL-code ledger card).
$_tb_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_tb_api = preg_match('~/api$~', $_tb_api) ? $_tb_api : $_tb_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css') ?>" />

<style>
    /* Table styling (.fims-report-table*) lives in app/fims-css/fims.css. Only page-specific, non-table rules remain here. */
    #tb-root .tb-report-title { text-align: center; margin: 0 0 18px; }
    #tb-root .tb-report-title div { font-weight: 700; line-height: 1.6; font-size: 1rem; }
    #tb-root .tb-report-title .tb-report-date { font-weight: 400; }

    /* The rest of the page used 10-13px (shared nha-fims.css), tiny next to the table. Raised here so the page reads as one scale.
       _shared/js/custom.js forces `1em * scale !important` on inputs/selects/buttons, so those get the same direct rem + !important. */
    #tb-root .nha-hdr-tag             { font-size: 0.75rem; }    /* 12px  (was 10) */
    #tb-root .nha-hdr-title           { font-size: 1.25rem; }    /* 20px  (was 17) */
    #tb-root .nha-btn-hdr             { font-size: calc(0.875rem * var(--button-scale, 1)) !important; } /* 14px  Refresh */
    #tb-root .nha-filter-label        { font-size: 0.75rem; }    /* 12px  (was 10) */
    #tb-root .nha-filter-select       { font-size: calc(0.875rem * var(--input-scale, 1)) !important; }  /* 14px  dropdowns (was 13) */
    #tb-root .nha-bdg                 { font-size: 0.8125rem; }  /* 13px  status badge (was 11) */
    /* The filter group is a flex column, which stretched the badge across the whole column — keep it pill-sized,
       lined up with the dropdowns beside it. */
    #tb-root #tb-badge { align-self: flex-start; display: inline-flex; align-items: center; min-height: 34px; padding: 0 14px; margin-top: auto; }
    #tb-root .nha-error               { font-size: 0.875rem; }   /* 14px  (was 13) */
    #tb-root .nha-hint                { font-size: 0.875rem; padding: 0 16px; margin: 0 0 14px; color: var(--afims-text-muted, #62657E); }   /* 14px  (was 13); 16px side padding lines it up with the filter card's content */

    /* the page header's Refresh button sits on the right of the banner, above its decorative circles (fims.css has no rule for it) */
    #tb-root .app-page-header { flex-wrap: wrap; }
    #tb-root .app-page-header-actions { position: relative; z-index: 1; margin-left: auto; display: flex; flex-wrap: wrap; gap: 8px; }

    @media print {
        #tb-root .tb-letterhead,
        #tb-root .tb-cert { display: block !important; }
    }
</style>

<div id="tb-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access: app-page-header (styled by app/fims-css/fims.css, already loaded above) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Trial Balance</h1>
            <p class="app-page-subtitle">The total debit and credit posted to every account, grouped under its major classification
                (Assets, Liabilities, Equity, Revenue, Expenses) — a snapshot of all General Ledger balances as of today.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="tb-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="tb-print" type="button" class="nha-btn-hdr" title="Print">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print
            </button>
            <button id="tb-excel" type="button" class="nha-btn-hdr" title="Export to Excel">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v12m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                Excel
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="tb-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="tb-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="tb-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book</label>
                <select id="tb-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <span id="tb-badge" class="nha-bdg nha-bdg-gray">—</span>
            </div>
        </div>
    </div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="tb-letterhead" style="display:none;border:1px solid #000;padding:8px 10px;margin-bottom:0;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:14%;vertical-align:middle;">
                <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;margin:0 auto;object-fit:contain;">
            </td>
            <td class="tb-report-title" style="width:51%;text-align:center;vertical-align:middle;">
                <div style="font-weight:700;">NATIONAL HOUSING AUTHORITY</div>
                <div style="font-weight:700;">TRIAL BALANCE<span id="tb-report-book"></span></div>
                <div style="font-weight:700;">MAIN OFFICE</div>
                <div class="tb-report-date"><span id="tb-report-date">—</span></div>
            </td>
            <td style="width:35%;text-align:center;vertical-align:middle;white-space:nowrap;">
                <img src="/_shared/images/letterhead/pambansang-pabahay.png" alt="Pambansang Pabahay"
                     style="height:46px;margin-right:5px;vertical-align:middle;object-fit:contain;">
                <img src="/_shared/images/letterhead/bagong-pilipinas.png" alt="Bagong Pilipinas"
                     style="height:46px;vertical-align:middle;object-fit:contain;">
            </td>
        </tr></table>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap fims-report-table-wrap">
        <div class="nha-tbl-scroll fims-report-table-scroll">
            <table class="nha-tbl fims-report-table">
                <thead>
                    <tr>
                        <th>Accounts</th>
                        <th>Acct. Code</th>
                        <th class="text-end">Debit Balances</th>
                        <th class="text-end">Credit Balances</th>
                    </tr>
                </thead>
                <tbody id="tb-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
                <tfoot>
                    <tr class="fims-report-table-total-row">
                        <td colspan="2">Total</td>
                        <td class="text-end nha-mono fims-report-table-num" id="tb-total-debit">₱ 0.00</td>
                        <td class="text-end nha-mono fims-report-table-num" id="tb-total-credit">₱ 0.00</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>

    <!-- ── CERTIFICATION (shown only on print) ───────────── -->
    <div class="tb-cert" style="display:none;margin-top:28px;font-size:9.5pt;">
        <p style="margin:0 0 30px;">I HEREBY CERTIFY that the trial balance is a true and complete statement of all
            accounts as shown in the ledger prepared and presented in conformity with International Public Sector
            Accounting Standards (IPSAS).</p>
        <div style="width:260px;text-align:center;">
            <div style="border-top:1px solid #000;padding-top:4px;">Signature Over Printed Name</div>
            <div>Head of Accounting Department</div>
        </div>
    </div>

</div><!-- /#tb-root -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   TRIAL BALANCE   prefix: tb_
═══════════════════════════════════════════════════════════════ */

const TB_BASE = <?= json_encode($_tb_api) ?>;
const TB_API  = TB_BASE + '/general_ledger';
const TB_FY_API = TB_BASE + '/fiscal_year/all';
const TB_BOOK_API = TB_BASE + '/books/all';
let tb_books = []; // [{book_id, name}] — kept around to label the report title

const $tb = id => document.getElementById(id);

function tb_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function tb_boot() {
    await Promise.all([tb_loadFiscalYears(), tb_loadBooks()]);
    tb_bindEvents();
    tb_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', tb_boot);
else tb_boot();

function tb_bindEvents() {
    $tb('tb-refresh')?.addEventListener('click', tb_load);
    $tb('tb-print')?.addEventListener('click', () => window.print());
    $tb('tb-excel')?.addEventListener('click', tb_exportExcel);
    $tb('tb-filter-fy')?.addEventListener('change', tb_load);
    $tb('tb-filter-book')?.addEventListener('change', tb_load);
}

/* Fiscal years come from fims.fiscal_year via GET /fiscal_year/all → {success, data:[{fiscal_yr}]}. */
async function tb_loadFiscalYears() {
    const sel = $tb('tb-filter-fy');
    try {
        const res  = await tb_authFetch(TB_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${tb_esc(r.fiscal_yr)}">${tb_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        tb_showError('Fiscal years: ' + e.message);
    }
}

/* Books come from fims.books via GET /books/all → {success, data:[{book_id, code, name}]}. */
async function tb_loadBooks() {
    const sel = $tb('tb-filter-book');
    try {
        const res  = await tb_authFetch(TB_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        tb_books = json.data ?? [];
        sel.innerHTML = '<option value="">All Books</option>' +
            tb_books.map(r => `<option value="${tb_esc(r.book_id)}">${tb_esc(r.name)}</option>`).join('');
    } catch (e) {
        tb_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function tb_load() {
    window.PagePreloader?.show();
    tb_hideError();
    try {
        const fy   = $tb('tb-filter-fy').value;
        const book = $tb('tb-filter-book').value;
        const params = new URLSearchParams();
        if (fy) params.set('fiscal_yr', fy);
        if (book) params.set('book_id', book);

        const res  = await tb_authFetch(`${TB_API}/trial-balance?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the Trial Balance.');

        tb_renderDate(fy);
        tb_renderBookLabel(book);
        tb_render(json);
    } catch (e) {
        tb_render({ data: [], total_debit: 0, total_credit: 0, balanced: true });
        tb_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* "As of December 31, {fiscal_yr}" when one year is picked; otherwise today's date (an all-years
   trial balance is a running snapshot, not tied to one fiscal year-end). */
function tb_renderDate(fiscalYr) {
    const d = fiscalYr ? new Date(Number(fiscalYr), 11, 31) : new Date();
    $tb('tb-report-date').textContent = d.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
}

/* "TRIAL BALANCE — CISFA" when one book is picked; plain "TRIAL BALANCE" for all books combined. */
function tb_renderBookLabel(bookId) {
    const book = tb_books.find(b => String(b.book_id) === String(bookId));
    $tb('tb-report-book').textContent = book ? ` — ${book.name}` : '';
}

/* ── Table ─────────────────────────────────────────────────── */
let tb_last = { data: [], total_debit: 0, total_credit: 0 }; // last rendered result, reused by the Excel export

function tb_render(json) {
    tb_last = json;
    const rows  = json.data ?? [];
    const tbody = $tb('tb-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
    } else {
        let lastMajor = null, lastGroup = null;
        tbody.innerHTML = rows.map(r => {
            let header = '';
            if (r.major_group !== lastMajor) {
                header += `<tr class="fims-report-table-major-row"><td colspan="4">${tb_esc(r.major_group)}</td></tr>`;
                lastGroup = null; // a new major group always restarts its own sub-groups
            }
            if (r.account_group && r.account_group !== lastGroup) {
                // Assets keep the filled sub-header (e.g. "Cash and Cash Equivalents"). Every other class —
                // Liabilities especially — lists its group (e.g. "Financial Liabilities" 2-01, "Inter-Agency
                // Payables" 2-02) as a plain row at the same level as the accounts, like the NHA reference.
                if (/^assets$/i.test(String(r.major_group).trim())) {
                    header += `<tr class="fims-report-table-group-row"><td colspan="4">${tb_esc(r.account_group)}</td></tr>`;
                } else {
                    header += `<tr class="fims-report-table-row">
                        <td>${tb_esc(r.account_group)}</td>
                        <td class="nha-mono fims-report-table-num">${tb_esc(r.account_group_code)}</td>
                        <td></td><td></td>
                    </tr>`;
                }
            }
            lastMajor = r.major_group;
            lastGroup = r.account_group;

            const contra = /^(Accumulated Depreciation|Allowance for Impairment)/i.test(String(r.account_title || ''));
            return header + `<tr class="fims-report-table-row${contra ? ' fims-report-table-contra-row' : ''}">
                <td>${tb_esc(r.account_title) || '—'}</td>
                <td class="nha-mono fims-report-table-num">${tb_esc(r.account_code)}</td>
                <td class="text-end nha-mono fims-report-table-num">${tb_peso(r.total_debit)}</td>
                <td class="text-end nha-mono fims-report-table-num">${tb_peso(r.total_credit)}</td>
            </tr>`;
        }).join('');
    }
    $tb('tb-total-debit').textContent  = tb_peso(json.total_debit);
    $tb('tb-total-credit').textContent = tb_peso(json.total_credit);

    const badge = $tb('tb-badge');
    if (json.balanced) {
        badge.className = 'nha-bdg nha-bdg-green';
        badge.textContent = 'Balanced';
    } else {
        badge.className = 'nha-bdg nha-bdg-red';
        badge.textContent = 'Out of balance';
    }
}

/* ── Excel export ──────────────────────────────────────────── */
/* Mirrors the on-screen/print layout: title block, then Major group → account group → accounts, then Total + certification. */
async function tb_exportExcel() {
    if (!window.ExcelJS) { tb_showError('Excel library failed to load. Check your internet connection and reload.'); return; }
    const rows = tb_last.data ?? [];
    if (!rows.length) { tb_showError('Nothing to export — no postings yet.'); return; }

    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Trial Balance', {
        pageSetup: { paperSize: 9, orientation: 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
    });
    ws.columns = [{ width: 58 }, { width: 18 }, { width: 22 }, { width: 22 }];
    const NUM = '#,##0.00;(#,##0.00);"-"';
    const thin = { style: 'thin' };

    const title = (text, bold = true) => {
        const r = ws.addRow([text]);
        ws.mergeCells(r.number, 1, r.number, 4);
        r.getCell(1).alignment = { horizontal: 'center' };
        r.getCell(1).font = { bold, size: 11 };
    };
    title('NATIONAL HOUSING AUTHORITY');
    title($tb('tb-report-book').textContent ? 'TRIAL BALANCE' + $tb('tb-report-book').textContent : 'TRIAL BALANCE');
    title('MAIN OFFICE');
    title($tb('tb-report-date').textContent, false);
    ws.addRow([]);

    const head = ws.addRow(['Accounts', 'Acct. Code', 'Debit Balances', 'Credit Balances']);
    head.eachCell((c, i) => {
        c.font = { bold: true };
        c.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFD9E1F2' } };
        c.border = { top: thin, bottom: thin, left: thin, right: thin };
        c.alignment = { horizontal: i > 2 ? 'right' : 'center' };
    });

    let lastMajor = null, lastGroup = null;
    rows.forEach(r => {
        if (r.major_group !== lastMajor) {
            const m = ws.addRow([r.major_group]);
            ws.mergeCells(m.number, 1, m.number, 4);
            m.getCell(1).font = { bold: true };
            m.getCell(1).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFEDEDED' } };
            lastGroup = null;
        }
        if (r.account_group && r.account_group !== lastGroup) {
            if (/^assets$/i.test(String(r.major_group).trim())) {
                const g = ws.addRow([r.account_group]);
                ws.mergeCells(g.number, 1, g.number, 4);
                g.getCell(1).font = { bold: true };
            } else {
                ws.addRow([r.account_group, r.account_group_code ?? '']);
            }
        }
        lastMajor = r.major_group;
        lastGroup = r.account_group;

        const row = ws.addRow([r.account_title || '—', r.account_code ?? '', Number(r.total_debit || 0), Number(r.total_credit || 0)]);
        row.getCell(1).alignment = { indent: 1 };
        row.getCell(3).numFmt = NUM;
        row.getCell(4).numFmt = NUM;
    });

    const tot = ws.addRow(['Total', '', Number(tb_last.total_debit || 0), Number(tb_last.total_credit || 0)]);
    ws.mergeCells(tot.number, 1, tot.number, 2);
    tot.eachCell(c => { c.font = { bold: true }; c.border = { top: thin, bottom: { style: 'double' } }; });
    tot.getCell(3).numFmt = NUM;
    tot.getCell(4).numFmt = NUM;

    ws.addRow([]);
    const cert = ws.addRow(['I HEREBY CERTIFY that the trial balance is a true and complete statement of all accounts as shown in the ledger prepared and presented in conformity with International Public Sector Accounting Standards (IPSAS).']);
    ws.mergeCells(cert.number, 1, cert.number, 4);
    cert.getCell(1).alignment = { wrapText: true, vertical: 'top' };
    cert.height = 48;
    ws.addRow([]);
    ws.addRow([]);
    const sig = ws.addRow(['Signature Over Printed Name']);
    sig.getCell(1).border = { top: thin };
    sig.getCell(1).alignment = { horizontal: 'center' };
    ws.addRow(['Head of Accounting Department']).getCell(1).alignment = { horizontal: 'center' };

    ws.views = [{ state: 'frozen', ySplit: head.number }];

    const fy = $tb('tb-filter-fy').value || 'all-years';
    const buf  = await wb.xlsx.writeBuffer();
    const blob = new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = `Trial-Balance_${fy}.xlsx`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/* ── Formatters ────────────────────────────────────────────── */
function tb_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function tb_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function tb_showError(msg) { $tb('tb-error').style.display = 'flex'; $tb('tb-error-msg').textContent = msg; }
function tb_hideError()    { $tb('tb-error').style.display = 'none'; }
</script>
