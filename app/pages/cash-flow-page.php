<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Statement of Cash Flows — one fiscal year's cash inflows/outflows grouped into Operating,
// Investing and Financing Activities, built from every JEV approved and POSTED to
// fims.general_ledger, via GET /cash_flow (CashFlowService.php). Laid out like the printed NHA
// form: Particulars / Note / Current Year / Previous Year, ending with the Cash and Cash
// Equivalents roll-forward (Beginning + Net Increase/(Decrease) = Ending). Read-only.
$_cf_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_cf_api = preg_match('~/api$~', $_cf_api) ? $_cf_api : $_cf_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #cf-root .cf-report-title { text-align: center; margin: 0 0 18px; }
    #cf-root .cf-report-title div { font-weight: 700; font-size: 14px; line-height: 1.6; }
    #cf-root .cf-report-title .cf-report-date { font-weight: 400; }
    #cf-root .cf-major-row td { background: #1B2B6B; color: #fff; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    #cf-root .cf-line-row td:first-child { padding-left: 20px; }
    #cf-root .cf-indent-row td:first-child { padding-left: 32px; }
    #cf-root .cf-subtotal-row td { font-weight: 600; border-top: 1px solid var(--afims-border, #DDE0EC); }
    #cf-root .cf-grand-row td { font-weight: 700; background: var(--afims-bg-soft, #F5F6FA); border-top: 2px solid #1B2B6B; border-bottom: 2px solid #1B2B6B; }
    #cf-root .cf-neg { color: #B42318; }
    #cf-root .cf-nodata { color: var(--afims-text-muted, #9A9DB0); font-style: italic; }
    #cf-root .cf-sample { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; background: #FFF4D6; color: #6B4A00; border: 1px solid #F0D58A; }

    #cf-root .nha-tbl-scroll { max-height: 70vh; overflow-y: auto; }
    #cf-root .nha-tbl thead th { position: sticky; top: 0; z-index: 1; }
    @media print {
        #cf-root .nha-tbl-scroll { max-height: none; overflow-y: visible; }
    }

    html.afims-dark[data-bs-theme="dark"] #cf-root .cf-major-row td { background: #2E4A8F; color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #cf-root .cf-grand-row td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; border-color: var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #cf-root .cf-subtotal-row td { border-top-color: var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #cf-root .cf-neg { color: #FF8A80; }
    html.afims-dark[data-bs-theme="dark"] #cf-root .cf-sample { background: #3A2F10; color: #F5D98A; border-color: #6B5A22; }
    @media print {
        #cf-root .cf-letterhead,
        #cf-root .cf-cert { display: block !important; }
        #cf-root .cf-sample { display: none !important; }
    }
</style>

<div id="cf-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 19V5m4 14v-8m4 8V9m4 10V7m4 12V3"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Statement of Cashflow</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="cf-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="cf-print" type="button" class="nha-btn-hdr" title="Print">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6v-8z"/></svg>
                Print
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        Statement of Cash Flows for the selected fiscal year, by Operating, Investing and Financing
        Activities (Direct Method). A few printed lines (Bayanihan Act BTr refund, Expropriated
        Account closure, and most project-specific disbursement lines) have no dedicated account in
        the chart of accounts yet and print at 0 rather than being dropped.
    </p>

    <div id="cf-sample" class="cf-sample no-print">
        <strong>Sample data.</strong> These figures are made up to show the layout. They are not from the General Ledger.
    </div>

    <!-- Error banner -->
    <div id="cf-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="cf-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cf-filter-fy">Fiscal Year</label>
                <select id="cf-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cf-filter-book">Book</label>
                <select id="cf-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="cf-letterhead" style="display:none;border:1px solid #000;padding:8px 10px;margin-bottom:0;">
        <table style="width:100%;border-collapse:collapse;"><tr>
            <td style="width:14%;vertical-align:middle;">
                <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA" style="height:54px;display:block;margin:0 auto;object-fit:contain;">
            </td>
            <td class="cf-report-title" style="width:51%;text-align:center;vertical-align:middle;">
                <div style="font-weight:700;">NATIONAL HOUSING AUTHORITY</div>
                <div style="font-weight:700;">STATEMENTS OF CASH FLOW</div>
                <div id="cf-title-book" style="font-weight:700;">MAIN OFFICE</div>
                <div class="cf-report-date"><span id="cf-report-date">—</span></div>
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
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Particulars</th>
                        <th>Note</th>
                        <th class="text-end" id="cf-th-cur">Current Year</th>
                        <th class="text-end" id="cf-th-prev">Previous Year</th>
                    </tr>
                </thead>
                <tbody id="cf-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ── CERTIFICATION (shown only on print) ───────────── -->
    <div class="cf-cert" style="display:none;margin-top:28px;font-size:9.5pt;">
        <p style="margin:0 0 30px;">I HEREBY CERTIFY that the foregoing Statement of Cash Flows is a true and
            complete statement of the cash receipts and disbursements as shown in the books of accounts, prepared
            and presented in conformity with International Public Sector Accounting Standards (IPSAS).</p>
        <div style="width:260px;text-align:center;">
            <div style="border-top:1px solid #000;padding-top:4px;">Signature Over Printed Name</div>
            <div>Head of Accounting Department</div>
        </div>
    </div>

</div><!-- /#cf-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   STATEMENT OF CASH FLOWS   prefix: cf_
═══════════════════════════════════════════════════════════════ */

const CF_BASE     = <?= json_encode($_cf_api) ?>;
const CF_API      = CF_BASE + '/cash_flow';
const CF_FY_API   = CF_BASE + '/fiscal_year/all';
const CF_BOOK_API = CF_BASE + '/books/all';
let cf_books = [];
let cf_useSample = false;

const $cf = id => document.getElementById(id);

function cf_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function cf_boot() {
    await Promise.all([cf_loadFiscalYears(), cf_loadBooks()]);
    cf_bindEvents();
    cf_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cf_boot);
else cf_boot();

function cf_bindEvents() {
    $cf('cf-refresh')?.addEventListener('click', cf_load);
    $cf('cf-print')?.addEventListener('click', () => window.print());
    $cf('cf-filter-fy').addEventListener('change', cf_load);
    $cf('cf-filter-book').addEventListener('change', cf_load);
    /* Sample preview has no button: type `cash_flow = true` in the browser console to show
       made-up figures, `cash_flow = false` to go back to live data. */
    Object.defineProperty(window, 'cash_flow', {
        configurable: true,
        get: () => cf_useSample,
        set: v => {
            cf_useSample = !!v;
            $cf('cf-sample').style.display = cf_useSample ? 'block' : 'none';
            cf_load();
        },
    });
}

async function cf_loadFiscalYears() {
    const sel = $cf('cf-filter-fy');
    let years = [];
    try {
        const res  = await cf_authFetch(CF_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        years = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(Boolean);
    } catch (e) {
        cf_showError('Fiscal years: ' + e.message);
    }
    const now = new Date().getFullYear();
    if (!years.length) years = [now];
    years = [...new Set(years)].sort((a, b) => b - a);
    sel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = String(years.includes(now) ? now : years[0]);
}

async function cf_loadBooks() {
    const sel = $cf('cf-filter-book');
    try {
        const res  = await cf_authFetch(CF_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        cf_books = json.data ?? [];
        sel.innerHTML = '<option value="">All Books</option>' +
            cf_books.map(r => `<option value="${cf_esc(r.book_id)}">${cf_esc(r.name)}</option>`).join('');
    } catch (e) {
        cf_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function cf_load() {
    window.PagePreloader?.show();
    cf_hideError();
    const fy   = $cf('cf-filter-fy').value;
    const book = $cf('cf-filter-book').value;
    try {
        let data;
        if (cf_useSample) {
            data = cf_sampleData(Number(fy) || new Date().getFullYear());
        } else {
            const params = new URLSearchParams();
            if (fy) params.set('fiscal_yr', fy);
            if (book) params.set('book_id', book);
            const res  = await cf_authFetch(`${CF_API}?${params}`);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load the Statement of Cash Flows.');
            data = json.data;
        }
        cf_renderTitle(data.fiscal_yr, book);
        cf_render(data);
    } catch (e) {
        cf_render(null, Number(fy) || new Date().getFullYear());
        cf_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function cf_renderTitle(fiscalYr, bookId) {
    const book = cf_books.find(b => String(b.book_id) === String(bookId));
    $cf('cf-title-book').textContent = book ? book.name.toUpperCase() : 'MAIN OFFICE';
    $cf('cf-report-date').textContent = 'For the Year Ended December 31, ' + fiscalYr + ' and ' + (fiscalYr - 1);
}

/* ── Table ─────────────────────────────────────────────────── */
function cf_render(d, fallbackFy) {
    const tbody = $cf('cf-tbody');
    const fy    = d ? d.fiscal_yr : fallbackFy;
    $cf('cf-th-cur').textContent  = String(fy);
    $cf('cf-th-prev').textContent = String(fy - 1);

    if (!d) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet for this fiscal year.</span></div></td></tr>';
        return;
    }

    const html = [];
    const line = l => `<tr class="cf-line-row ${l.indent ? 'cf-indent-row' : ''}"><td>${cf_esc(l.label)}</td><td></td>${cf_amt(l.cur)}${cf_amt(l.prev)}</tr>`;
    const subtotal = (label, cur, prev) => `<tr class="cf-subtotal-row"><td colspan="2">${cf_esc(label)}</td>${cf_amt(cur)}${cf_amt(prev)}</tr>`;
    const grand = (label, cur, prev) => `<tr class="cf-grand-row"><td colspan="2">${cf_esc(label)}</td>${cf_amt(cur)}${cf_amt(prev)}</tr>`;

    const sectionSubtotalLabel = {
        operating: 'Net cash provided by (used in) operating activities',
        investing: 'Net Cash Provided by (Used in) Investing Activities',
        financing: 'Net Cash Provided by (Used in) Financing Activities',
    };

    d.sections.forEach(s => {
        html.push(`<tr class="cf-major-row"><td colspan="4">${cf_esc(s.label)}</td></tr>`);
        let disbHeaderShown = false;
        s.lines.forEach(l => {
            if (l.indent && !disbHeaderShown) {
                html.push('<tr class="cf-line-row"><td colspan="4">Disbursements made for:</td></tr>');
                disbHeaderShown = true;
            }
            html.push(line(l));
        });
        html.push(subtotal(sectionSubtotalLabel[s.key] || ('Net cash — ' + s.label), s.subtotal_cur, s.subtotal_prev));
    });

    const t = d.totals;
    html.push(grand('Net Increase/(Decrease) in Cash and Cash Equivalents', t.net_increase_cur, t.net_increase_prev));
    html.push(`<tr class="cf-line-row"><td colspan="2">Add: Cash and Cash Equivalents - Beginning</td>${cf_amt(t.beginning_cur)}${cf_amt(t.beginning_prev)}</tr>`);
    html.push(grand('Cash and Cash Equivalents, Ending', t.ending_cur, t.ending_prev));

    tbody.innerHTML = html.join('');
}

/* ── Sample figures (layout preview only; same shape as GET /cash_flow) ── */
function cf_sampleData(fy) {
    const l = (key, label, cur, prev, indent = false) => ({ key, label, cur, prev, indent });
    const sec = (key, label, lines) => ({
        key, label, lines,
        subtotal_cur: lines.reduce((s, x) => s + x.cur, 0),
        subtotal_prev: lines.reduce((s, x) => s + x.prev, 0),
    });

    const operating = sec('operating', 'CASH FLOWS FROM OPERATING ACTIVITIES', [
        l('subsidy_ng', 'Subsidy from National Government', 5531211983, 5102340112),
        l('trust_fund_borrowed', 'Cash borrowed from (reverted to) trust funds', 12500000, -3200000),
        l('collections_awardees', 'Collections from awardees/beneficiaries', 847392104, 790115223),
        l('other_collections', 'Other collections', 44050438, 43342390),
        l('interest_earned', 'Interest earned on savings/time deposit', 3184512, 2910044),
        l('trust_fund_received', 'Trust Fund received from other agencies', 9800000, 7250000),
        l('cash_donations', 'Cash Donations Received', 0, 150000),
        l('btr_refund_ra11469', 'Refund from BTr re: Remittance in compliance to RA No. 11469 (Bayanihan to Heal as One Act)', 0, 0),
        l('expropriated_closure', 'Proceeds from Closure of Expropriated Account', 0, 0),
        l('cash_paid_suppliers', 'Cash paid to suppliers and employees', -1040110483, -1038551436),
        l('disb_calamity', 'Various calamity projects', 0, 0, true),
        l('disb_resettlement', 'Various resettlement projects', -62400000, -58100000, true),
        l('disb_marawi', 'Rehabilitation of most affected areas in Marawi City', 0, 0, true),
        l('disb_afp_pnp', 'AFP/PNP Housing', 0, 0, true),
        l('disb_relocation', 'Northrail/Southrail/North Triangle Relocation Projects', 0, 0, true),
        l('disb_other_housing', 'Other housing programs/projects', -18750000, -15400000, true),
        l('remittances_trust', 'Remittances to other agencies under Trust Agreement', -9800000, -7250000),
        l('financial_expenses', 'Financial expenses', -129193, -118785),
    ]);
    const investing = sec('investing', 'CASH FLOWS FROM INVESTING ACTIVITIES', [
        l('purchase_ppe', 'Purchase of property and equipment', -29120671, -29069283),
        l('sale_unserviceable', 'Collections on sale of unserviceable property', 145412, 145412),
    ]);
    const financing = sec('financing', 'CASH FLOWS FROM FINANCING ACTIVITIES', [
        l('dividends_paid', 'Dividends paid to the National Government', -5000000, -4500000),
    ]);

    const netCur  = operating.subtotal_cur + investing.subtotal_cur + financing.subtotal_cur;
    const netPrev = operating.subtotal_prev + investing.subtotal_prev + financing.subtotal_prev;
    const beginCur  = 612300000;
    const beginPrev = beginCur - netPrev;

    return {
        fiscal_yr: fy, prev_year: fy - 1, book_id: null,
        sections: [operating, investing, financing],
        totals: {
            net_increase_cur: netCur, net_increase_prev: netPrev,
            beginning_cur: beginCur, beginning_prev: beginPrev,
            ending_cur: beginCur + netCur, ending_prev: beginPrev + netPrev,
        },
    };
}

/* ── Formatters ────────────────────────────────────────────── */
function cf_amt(v) {
    return `<td class="text-end nha-mono">${cf_peso(v)}</td>`;
}
function cf_peso(v) {
    const n = Number(v || 0);
    if (n === 0) return '-';
    const s = Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < -0.005 ? `<span class="cf-neg">(${s})</span>` : s;
}
function cf_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function cf_showError(msg) { $cf('cf-error').style.display = 'flex'; $cf('cf-error-msg').textContent = msg; }
function cf_hideError()    { $cf('cf-error').style.display = 'none'; }
</script>
