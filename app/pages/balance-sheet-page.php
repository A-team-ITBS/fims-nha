<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Balance Sheet (Statement of Financial Position) — position as of a fiscal year-end, built from
// every JEV that was approved and POSTED to fims.general_ledger, via GET /balance_sheet
// (BalanceSheetService.php). Laid out like the Trial Balance page: centered print title, then
// Current/Non-Current Assets, Current/Non-Current Liabilities and Net Assets/Equity, each grouped
// by chart-of-accounts group (e.g. "1-01 Cash and Cash Equivalents"), with the prior year-end and
// the Increase/(Decrease) beside it. Read-only.
$_bs_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_bs_api = preg_match('~/api$~', $_bs_api) ? $_bs_api : $_bs_api . '/api';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #bs-root .bs-report-title { text-align: center; margin: 0 0 18px; }
    #bs-root .bs-report-title div { font-weight: 700; font-size: 14px; line-height: 1.6; }
    #bs-root .bs-report-title .bs-report-date { font-weight: 400; }
    #bs-root .bs-major-row td { background: #1B2B6B; color: #fff; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    #bs-root .bs-group-row td { background: var(--afims-bg-soft, #F5F6FA); font-weight: 600; text-transform: uppercase; font-size: 12px; }
    #bs-root .bs-account-row td:first-child { padding-left: 28px; }
    #bs-root .bs-subtotal-row td { font-weight: 600; border-top: 1px solid var(--afims-border, #DDE0EC); }
    #bs-root .bs-grand-row td { font-weight: 700; background: var(--afims-bg-soft, #F5F6FA); border-top: 2px solid #1B2B6B; border-bottom: 2px solid #1B2B6B; text-transform: uppercase; }
    #bs-root .bs-neg { color: #B42318; }
    #bs-root .bs-sample { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; background: #FFF4D6; color: #6B4A00; border: 1px solid #F0D58A; }
    #bs-root .bs-diff { display: none; margin: 0 0 14px; padding: 10px 14px; border-radius: 6px; font-size: 13px; background: #FDECEA; color: #8A1C12; border: 1px solid #F3B8B2; }

    /* Dark mode: same treatment as the Trial Balance page — --afims-bg-soft is not a theme token */
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-group-row td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; border-top: 1px solid var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-major-row td { background: #2E4A8F; color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-grand-row td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; border-color: var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-subtotal-row td { border-top-color: var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-neg { color: #FF8A80; }
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-sample { background: #3A2F10; color: #F5D98A; border-color: #6B5A22; }
    html.afims-dark[data-bs-theme="dark"] #bs-root .bs-diff { background: #3B1A17; color: #FFB4AB; border-color: #7A332C; }
    @media print {
        #bs-root .bs-report-title { display: block !important; }
        #bs-root .bs-sample { display: none !important; }
    }
</style>

<div id="bs-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M5 7h14M5 7l-2.5 6a3 3 0 005 0L5 7zm14 0l-2.5 6a3 3 0 005 0L19 7z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Balance Sheet</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="bs-sample-btn" type="button" class="nha-btn-hdr" title="Show example figures to review the layout">
                Preview sample data
            </button>
            <button id="bs-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        Statement of Financial Position as of the end of the selected fiscal year. It adds up every
        Journal Entry Voucher that was approved and posted to the General Ledger, so a voucher still
        in review does not appear here.
    </p>

    <div id="bs-sample" class="bs-sample no-print">
        <strong>Sample data.</strong> These figures are made up to show the layout. They are not from the General Ledger.
    </div>

    <!-- Error banner -->
    <div id="bs-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="bs-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="bs-filter-fy">Fiscal Year</label>
                <select id="bs-filter-fy" class="nha-filter-select"></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="bs-filter-book">Book</label>
                <select id="bs-filter-book" class="nha-filter-select">
                    <option value="">All Books</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <span id="bs-badge" class="nha-bdg nha-bdg-gray">—</span>
            </div>
        </div>
    </div>

    <div id="bs-diff" class="bs-diff no-print"></div>

    <!-- ── REPORT TITLE (shown only on print) ────────────── -->
    <div class="bs-report-title d-print-block" style="display:none;">
        <div>NATIONAL HOUSING AUTHORITY</div>
        <div>STATEMENT OF FINANCIAL POSITION<span id="bs-report-book"></span></div>
        <div class="bs-report-date">As of <span id="bs-report-date">—</span></div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Account Title</th>
                        <th>Acct Code</th>
                        <th class="text-end" id="bs-th-cur">Current Year</th>
                        <th class="text-end" id="bs-th-prev">Prior Year</th>
                        <th class="text-end">Increase / (Decrease)</th>
                    </tr>
                </thead>
                <tbody id="bs-tbody">
                    <tr><td colspan="5"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /#bs-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   BALANCE SHEET   prefix: bs_
═══════════════════════════════════════════════════════════════ */

const BS_BASE     = <?= json_encode($_bs_api) ?>;
const BS_API      = BS_BASE + '/balance_sheet';
const BS_FY_API   = BS_BASE + '/fiscal_year/all';
const BS_BOOK_API = BS_BASE + '/books/all';
let bs_books = [];        // [{book_id, name}] — labels the report title
let bs_useSample = false; // "Preview sample data" toggle

const $bs = id => document.getElementById(id);

function bs_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function bs_boot() {
    await Promise.all([bs_loadFiscalYears(), bs_loadBooks()]);
    bs_bindEvents();
    bs_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bs_boot);
else bs_boot();

function bs_bindEvents() {
    $bs('bs-refresh')?.addEventListener('click', bs_load);
    $bs('bs-filter-fy')?.addEventListener('change', bs_load);
    $bs('bs-filter-book')?.addEventListener('change', bs_load);
    $bs('bs-sample-btn')?.addEventListener('click', () => {
        bs_useSample = !bs_useSample;
        $bs('bs-sample-btn').textContent = bs_useSample ? 'Use live data' : 'Preview sample data';
        $bs('bs-sample').style.display = bs_useSample ? 'block' : 'none';
        bs_load();
    });
}

/* A balance sheet is "as of" one year-end, so there is no "All Years" option: default to the
   current year when it exists, otherwise the latest fiscal year on file. */
async function bs_loadFiscalYears() {
    const sel = $bs('bs-filter-fy');
    let years = [];
    try {
        const res  = await bs_authFetch(BS_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        years = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(Boolean);
    } catch (e) {
        bs_showError('Fiscal years: ' + e.message);
    }
    const now = new Date().getFullYear();
    if (!years.length) years = [now];
    years = [...new Set(years)].sort((a, b) => b - a);
    sel.innerHTML = years.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = String(years.includes(now) ? now : years[0]);
}

async function bs_loadBooks() {
    const sel = $bs('bs-filter-book');
    try {
        const res  = await bs_authFetch(BS_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        bs_books = json.data ?? [];
        sel.innerHTML = '<option value="">All Books</option>' +
            bs_books.map(r => `<option value="${bs_esc(r.book_id)}">${bs_esc(r.name)}</option>`).join('');
    } catch (e) {
        bs_showError('Books: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function bs_load() {
    window.PagePreloader?.show();
    bs_hideError();
    const fy   = $bs('bs-filter-fy').value;
    const book = $bs('bs-filter-book').value;
    try {
        let data;
        if (bs_useSample) {
            data = bs_sampleData(Number(fy) || new Date().getFullYear());
        } else {
            const params = new URLSearchParams();
            if (fy) params.set('fiscal_yr', fy);
            if (book) params.set('book_id', book);
            const res  = await bs_authFetch(`${BS_API}?${params}`);
            if (!res.ok) throw new Error('HTTP ' + res.status);
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to load the Balance Sheet.');
            data = json.data;
        }
        bs_renderTitle(data.fiscal_yr, book);
        bs_render(data);
    } catch (e) {
        bs_render(null, Number(fy) || new Date().getFullYear());
        bs_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function bs_renderTitle(fiscalYr, bookId) {
    const d = new Date(Number(fiscalYr), 11, 31);
    $bs('bs-report-date').textContent = d.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' });
    const book = bs_books.find(b => String(b.book_id) === String(bookId));
    $bs('bs-report-book').textContent = book ? ` — ${book.name}` : '';
}

/* ── Table ─────────────────────────────────────────────────── */
function bs_render(d, fallbackFy) {
    const tbody = $bs('bs-tbody');
    const fy    = d ? d.fiscal_yr : fallbackFy;
    $bs('bs-th-cur').textContent  = 'FY ' + fy;
    $bs('bs-th-prev').textContent = 'FY ' + (fy - 1);

    const badge = $bs('bs-badge'), diff = $bs('bs-diff');
    diff.style.display = 'none';

    const hasData = d && d.sections.some(s => s.groups.length) ;
    if (!d || !hasData) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet for this fiscal year.</span></div></td></tr>';
        badge.className = 'nha-bdg nha-bdg-gray';
        badge.textContent = '—';
        return;
    }

    const html = [];
    const amounts = (cur, prev) => `
        <td class="text-end nha-mono">${bs_peso(cur)}</td>
        <td class="text-end nha-mono">${bs_peso(prev)}</td>
        <td class="text-end nha-mono">${bs_peso(cur - prev)}</td>`;
    const row = (cls, title, code, cur, prev) =>
        `<tr class="${cls}"><td>${bs_esc(title)}</td><td class="nha-mono">${bs_esc(code)}</td>${amounts(cur, prev)}</tr>`;
    const grand = (title, cur, prev) =>
        `<tr class="bs-grand-row"><td colspan="2">${bs_esc(title)}</td>${amounts(cur, prev)}</tr>`;

    d.sections.forEach(s => {
        html.push(`<tr class="bs-major-row"><td colspan="5">${bs_esc(s.label)}</td></tr>`);
        s.groups.forEach(g => {
            html.push(row('bs-group-row', g.group_title, g.group_code, g.balance, g.prev_balance));
            g.accounts.forEach(a => html.push(row('bs-account-row', a.account_title, a.account_code, a.balance, a.prev_balance)));
        });
        if (s.key === 'equity' && (d.net_surplus_deficit.balance || d.net_surplus_deficit.prev_balance)) {
            html.push(row('bs-account-row', 'Net Surplus / (Deficit), not yet closed', '', d.net_surplus_deficit.balance, d.net_surplus_deficit.prev_balance));
        }
        html.push(row('bs-subtotal-row', 'Total ' + s.label, '', s.subtotal, s.prev_subtotal));

        if (s.key === 'non_current_assets')      html.push(grand('Total Assets', d.totals.total_assets, d.totals.prev_total_assets));
        if (s.key === 'non_current_liabilities') html.push(grand('Total Liabilities', d.totals.total_liabilities, d.totals.prev_total_liabilities));
        if (s.key === 'equity') {
            html.push(grand('Total Liabilities and Net Assets / Equity',
                d.totals.total_liabilities_and_equity,
                d.totals.prev_total_liabilities + d.totals.prev_total_equity));
        }
    });
    tbody.innerHTML = html.join('');

    if (d.balanced) {
        badge.className = 'nha-bdg nha-bdg-green';
        badge.textContent = 'Balanced';
    } else {
        badge.className = 'nha-bdg nha-bdg-red';
        badge.textContent = 'Out of balance';
        diff.style.display = 'block';
        diff.textContent = 'Assets and Liabilities + Equity differ by ' + bs_peso(d.difference) +
            '. The ledger is missing entries (for example opening balances); the report does not adjust it.';
    }
}

/* ── Sample figures (layout preview only; same shape as GET /balance_sheet) ── */
function bs_sampleData(fy) {
    const acct = (code, title, b, p) => ({ account_code: code, account_title: title, balance: b, prev_balance: p });
    const grp  = (code, title, accts) => ({
        group_code: code, group_title: title, accounts: accts,
        balance: accts.reduce((s, a) => s + a.balance, 0), prev_balance: accts.reduce((s, a) => s + a.prev_balance, 0),
    });
    const sec = (key, label, type, groups) => ({
        key, label, type, groups,
        subtotal: groups.reduce((s, g) => s + g.balance, 0), prev_subtotal: groups.reduce((s, g) => s + g.prev_balance, 0),
    });
    const ca  = sec('current_assets', 'Current Assets', 'asset', [
        grp('1-01', 'Cash and Cash Equivalents', [
            acct('1-01-01-020', 'Petty Cash', 150000, 120000),
            acct('1-01-02-040', 'Cash in Bank - Local Currency, Current Account', 48250000, 39100000),
        ]),
        grp('1-03', 'Receivables', [
            acct('1-03-01-010', 'Accounts Receivable', 12800000, 14350000),
            acct('1-03-03-010', 'Loans Receivable - Others', 96400000, 101200000),
        ]),
        grp('1-04', 'Inventories', [acct('1-04-04-010', 'Office Supplies Inventory', 1900000, 1650000)]),
    ]);
    const nca = sec('non_current_assets', 'Non-Current Assets', 'asset', [
        grp('1-06', 'Property, Plant and Equipment', [
            acct('1-06-04-010', 'Buildings', 210000000, 210000000),
            acct('1-06-04-011', 'Accumulated Depreciation - Buildings', -48500000, -43200000),
            acct('1-06-05-020', 'Office Equipment', 9600000, 8900000),
        ]),
    ]);
    const cl  = sec('current_liabilities', 'Current Liabilities', 'liability', [
        grp('2-01', 'Financial Liabilities', [acct('2-01-01-010', 'Accounts Payable', 18900000, 16400000)]),
        grp('2-02', 'Inter-Agency Payables', [acct('2-02-01-010', 'Due to BIR', 2100000, 1850000)]),
    ]);
    const ncl = sec('non_current_liabilities', 'Non-Current Liabilities', 'liability', [
        grp('2-06', 'Provisions', [acct('2-06-01-010', 'Leave Benefits Payable', 7400000, 6900000)]),
    ]);
    const eq  = sec('equity', 'Net Assets / Equity', 'equity', [
        grp('3-01', 'Government Equity', [acct('3-01-01-010', 'Accumulated Surplus/(Deficit)', 280200000, 306970000)]),
    ]);
    const surplus = { balance: 22000000, prev_balance: 0 };
    eq.subtotal += surplus.balance; eq.prev_subtotal += surplus.prev_balance;

    const sections = [ca, nca, cl, ncl, eq];
    const sum = (type, f) => sections.filter(s => s.type === type).reduce((t, s) => t + s[f], 0);
    const a = sum('asset', 'subtotal'), l = sum('liability', 'subtotal'), e = sum('equity', 'subtotal');
    return {
        fiscal_yr: fy, prev_year: fy - 1, book_id: null, sections, net_surplus_deficit: surplus,
        totals: {
            total_assets: a, total_liabilities: l, total_equity: e, total_liabilities_and_equity: l + e,
            prev_total_assets: sum('asset', 'prev_subtotal'),
            prev_total_liabilities: sum('liability', 'prev_subtotal'),
            prev_total_equity: sum('equity', 'prev_subtotal'),
        },
        balanced: Math.abs(a - (l + e)) < 0.005, difference: a - (l + e),
    };
}

/* ── Formatters ────────────────────────────────────────────── */
/* Negative amounts print in parentheses, the usual financial-statement convention. */
function bs_peso(v) {
    const n = Number(v || 0);
    const s = '₱ ' + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return n < -0.005 ? `<span class="bs-neg">(${s})</span>` : s;
}
function bs_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function bs_showError(msg) { $bs('bs-error').style.display = 'flex'; $bs('bs-error-msg').textContent = msg; }
function bs_hideError()    { $bs('bs-error').style.display = 'none'; }
</script>
