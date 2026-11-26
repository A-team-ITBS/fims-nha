<?php

namespace PHPMaker2024\FIMS;

@session_start();
$_cob_api = Config('API.FIMS_URL');

// Generate Report (the PDF / Excel dialog on each table) is switched off: its button and its dialog are not rendered. Set this to true to bring both back.
// (The dialog only knows the Per Region / Per Type of Fund / Per Component views — see readone/cob-report-readone.php — not the Per Program one.)
$_cob_showReport = false;

// The explanatory note under the table ("Only Approved budget requests are counted. …") is not shown. Set this to true to bring it back.
$_cob_showNote = false;
?>

<?php /* The shared theme, linked the way every other custom page links it (GetUrl adds the app's base path; the version comes from the file
          next to this page, so a changed stylesheet is never served stale). */ ?>
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
#cob-root .nha-summary { grid-template-columns: repeat(4, 1fr); }
@media (max-width: 900px) { #cob-root .nha-summary { grid-template-columns: repeat(2, 1fr); } }

/* the COB Monitoring sheet: Code | Name | eight amount columns. No colour coding — the hierarchy is shown by indentation, the caret and bold group rows. */
/* Column widths: Code and Actions are narrow, Name takes what is left (never less than 300px, so a name does not wrap word by word), each amount column is at
   least 112px. The headers wrap onto two lines (nha-fims.css keeps them on one, which made the eight long headings use up the whole table and squeeze Name). When the
   screen is narrower than the table, the table scrolls sideways. */
#cob-root .cob-tbl { min-width: 1340px; table-layout: auto; }
#cob-root .cob-tbl th, #cob-root .cob-tbl td { box-sizing: border-box; }
#cob-root .cob-tbl thead th { cursor: default; vertical-align: middle; white-space: normal; line-height: 1.3; padding: 10px 10px; }
#cob-root .cob-tbl thead th.text-end { text-align: right; }
#cob-root .cob-tbl thead th.text-end, #cob-root .cob-tbl td.cob-num { min-width: 116px; }
#cob-root .cob-tbl thead th:nth-child(1), #cob-root .cob-tbl td.cob-code { width: 84px; min-width: 84px; }
#cob-root .cob-tbl thead th:nth-child(2) { min-width: 260px; }
#cob-root .cob-tbl thead th:last-child { width: 72px; }
#cob-root .cob-tbl .cob-code { white-space: nowrap; font-family: 'IBM Plex Mono', monospace; font-size: 12px; }   /* same colour and weight as the row's own text */

#cob-root .cob-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 14px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#cob-root .cob-hint { font-size: 12px; color: var(--afims-text-muted, #62657E); }
#cob-root .cob-tool-btns { display: flex; gap: 6px; }
#cob-root .cob-note { margin: 0; padding: 10px 14px; font-size: 11.5px; line-height: 1.5; color: var(--afims-text-muted, #62657E); border-top: 1px solid var(--afims-border, #E4ECF2); }

/* tree rows */
#cob-root .cob-row.cob-has-kids { cursor: pointer; }
#cob-root .cob-row.cob-has-kids:focus-visible { outline: 2px solid var(--afims-heading-fill, #000E3E); outline-offset: -2px; }
/* group rows (everything above a project) are bold; a project — or, under COE, a request — is plain */
#cob-root .cob-row:not(.cob-t-project):not(.cob-t-request) > td { font-weight: 600; color: var(--afims-heading, #000E3E); }
#cob-root .cob-d0:not(.cob-t-project):not(.cob-t-request) > td { font-weight: 700; }
/* The two halves of the sheet, CO and COE, each start on a row of their own and each has a colour of its own (CO blue, COE green): a tinted band across the row,
   a solid bar down its left edge, and the code as a solid pill. The rule below repeats the selector shape of the "group rows are bold" rule above so that
   it beats it on colour (same specificity, and it comes later). */
#cob-root .cob-t-bucket { --b-accent: var(--afims-heading, #000E3E); --b-soft: var(--afims-surface-subtle, #F4F7FA); }
#cob-root .cob-b-co  { --b-accent: var(--afims-primary, #0035A9); --b-soft: var(--afims-primary-soft, #E6EBF6); }
#cob-root .cob-b-coe { --b-accent: var(--afims-success-hover, #0C7539); --b-soft: var(--afims-success-soft, #DBF2E5); }
#cob-root .cob-row.cob-t-bucket[class*="cob-b-"] > td { background: var(--b-soft); color: var(--b-accent); font-weight: 700; border-top: 2px solid var(--b-accent); }
#cob-root .cob-t-bucket > td:first-child { box-shadow: inset 5px 0 0 var(--b-accent); }
#cob-root .cob-t-bucket:hover > td { background-image: linear-gradient(rgba(0, 0, 0, .05), rgba(0, 0, 0, .05)); }
#cob-root .cob-bkt-badge { display: inline-block; min-width: 40px; padding: 2px 8px; border-radius: 4px; text-align: center; font-weight: 700; letter-spacing: .04em; color: #fff; background: var(--b-accent); }
/* The name flows as ordinary text (name, then FY / office / documents after it) and wraps inside its cell; the caret is pinned to the left of it, so it can never end
   up on a line of its own. Indented 16px per level. */
#cob-root .cob-name { position: relative; line-height: 1.45; padding-left: calc(var(--d, 0) * 16px + 22px); }
#cob-root .cob-name > span:not(.cob-caret-sp) ~ *:not(.cob-sub) { margin-left: 8px; }
/* a project row: the name (+ paperclip) on the first line, then ONE muted line under it with the fiscal year and the requesting office */
#cob-root .cob-name > .cob-sub { display: block; margin-top: 3px; line-height: 1.5; }
#cob-root .cob-name > .cob-sub .nha-bdg { margin-right: 6px; vertical-align: 1px; }
#cob-root .cob-t-project > td, #cob-root .cob-t-request > td { vertical-align: middle; }
#cob-root .cob-caret { position: absolute; left: calc(var(--d, 0) * 16px); top: 2px; width: 14px; height: 14px; transition: transform .15s; stroke: currentColor; }
#cob-root .cob-caret.open { transform: rotate(90deg); }
#cob-root .cob-caret-sp { display: none; }
#cob-root .cob-sub { font-size: 11.5px; font-weight: 400; color: var(--afims-text-muted, #62657E); }
#cob-root .cob-unassigned { font-style: italic; color: #B45309; }
#cob-root .cob-num { font-family: 'IBM Plex Mono', monospace; font-size: 12px; text-align: right; white-space: nowrap; }
#cob-root .cob-neg { color: #dc2626; }
#cob-root .cob-pos { color: var(--green-txt, #0C7539); }
#cob-root .cob-dash { color: var(--afims-text-disabled, #A9AAB8); }
#cob-root .cob-clip { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 600; color: var(--afims-primary-text, #0035A9); }
#cob-root .cob-clip svg { width: 12px; height: 12px; stroke: currentColor; }
/* the TOTAL row sits at the top of the body, so its rule is underneath it */
#cob-root .cob-total-row > td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); border-bottom: 2px solid var(--afims-border, #E4ECF2); }

</style>

<div id="cob-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Corporate Operating Budget (COB)</h1>
            <p class="app-page-subtitle">Corporate Operating Budget Monitoring — approved budget requests only, and how much of them has been earmarked and used</p>
        </div>
        <div class="app-page-header-actions">
            <button id="cob-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover everything matching the filters) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Approved Requests</div>
                <div class="nha-sc-val" id="cob-kpi-requests">—</div>
            </div></div>
            <p class="nha-kpi-sub" id="cob-kpi-requests-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Revised Amount</div>
                <div class="nha-sc-val" id="cob-kpi-revised">—</div>
            </div></div>
            <p class="nha-kpi-sub" id="cob-kpi-revised-sub">Approved Amount net of Amendment / Realignment</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Available for Earmarking</div>
                <div class="nha-sc-val" id="cob-kpi-available">—</div>
            </div></div>
            <p class="nha-kpi-sub">Revised Amount less Outstanding CBA, RFA Approved and LAA Issued</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Remaining Balance</div>
                <div class="nha-sc-val" id="cob-kpi-remaining">—</div>
            </div></div>
            <p class="nha-kpi-sub">Revised Amount less Outstanding CBA, RFA Approved and LAA Issued</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cob-f-fy">Fiscal Year</label>
                <select id="cob-f-fy" class="nha-filter-select"><option value="">All Years</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cob-f-fund">Type of Fund</label>
                <select id="cob-f-fund" class="nha-filter-select"><option value="">All Types of Fund</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cob-f-component">Component</label>
                <select id="cob-f-component" class="nha-filter-select"><option value="">All Components</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cob-f-region">Region</label>
                <select id="cob-f-region" class="nha-filter-select"><option value="">All Regions</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cob-f-program">Program</label>
                <select id="cob-f-program" class="nha-filter-select"><option value="">All Programs</option></select>
            </div>
        </div>
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label" for="cob-f-search">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="cob-f-search" class="nha-filter-input" placeholder="Search request ref, program, project, account, office…" autocomplete="off">
                    <button class="nha-filter-clear-btn" id="cob-f-search-clear" hidden type="button" aria-label="Clear search">×</button>
                </div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="cob-f-unit">Amount Unit</label>
                <select id="cob-f-unit" class="nha-filter-select">
                    <option value="full">Full Amount</option>
                    <option value="thousand">Thousands (K)</option>
                    <option value="million">Millions (M)</option>
                    <option value="billion">Billions (B)</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button type="button" id="cob-reset" class="nha-btn nha-btn-outline">Reset</button>
            </div>
        </div>
    </div>

    <!-- ── ROLL-UP: one tree per view, same eight columns. There is no tab bar: the page opens on Per Program (Program → Sub-Program →
         Originating Unit → Project) and that is the only view on screen. The Per Region, Per Type of Fund and Per Component panels are still
         defined here (hidden, never loaded) so a tab bar can be put back without rebuilding them. ──────── -->
    <?php
    // The columns of the COB Monitoring sheet (key = what the API sends, in this order) — cob-page's JS carries the same list (COB_COLS).
    $_cob_cols = [
        ['approved_amount', 'Approved Amount', 'The amount FMD suggested in the Budget Proposal module, before any revision or CBA'],
        ['revision_amount', 'Amendment / Realignment', 'A project shows the total of ALL its approved amendments and realignments to date, with its sign: an addition is + (green), a deduction − (red); a realignment is − on the budget that gave funds up and + on the one that received them. Parent rows and the TOTAL have no figure here. Approved Amount + this = Revised Amount'],
        ['revised_amount', 'Revised Amount', 'Approved Amount net of Amendment / Realignment (a decrease lowers it)'],
        ['rfa_approved', 'RFA Approved', 'Drawn from the CBAs by approved RFAs'],
        ['laa_issued', 'LAA Issued', 'Drawn from the CBAs by issued LAAs'],
        ['remaining_balance', 'Budget Balance', 'Revised Amount less Outstanding CBA, RFA Approved and LAA Issued — what is left of the revised budget once the CBAs (and what RFAs / LAAs drew from them) are taken off'],
        ['cba_requested', 'Outstanding CBA', 'Still held against the budget by approved CBAs (Certificates of Budget Availability) — read from the earmark table. Once an RFA / LAA is approved, the amount it drew comes off this column and shows under RFA Approved / LAA Issued'],
        ['available_amount', 'Budget Balance Available for Earmarking', 'Revised Amount less Outstanding CBA, RFA Approved and LAA Issued — what a new CBA can still draw'],
    ];
    foreach ([
        'program'     => 'CO: Program → Sub-Program → Originating Unit → Project · COE: PS / MOOE → Originating Unit → Request',
        'region'      => 'Region → Program → Sub-Program → Project',
        'fund_source' => 'Type of Fund → Component → Program → Sub-Program → Project',
        'component'   => 'Component → Type of Fund → Program → Sub-Program → Project',
    ] as $_cob_tab => $_cob_levels) { ?>
    <div class="nha-panel<?php echo $_cob_tab === 'program' ? ' active' : ''; ?>" id="cob-panel-<?php echo $_cob_tab; ?>" role="tabpanel">
        <div class="nha-table-wrap">
            <div class="cob-toolbar no-print">
                <span class="cob-hint"><span id="cob-info-<?php echo $_cob_tab; ?>"><?php echo htmlspecialchars($_cob_levels); ?></span> · <span class="cob-unit-note">Amounts in ₱</span></span>
                <span class="cob-tool-btns">
                    <?php if ($_cob_showReport) { ?>
                    <button type="button" class="nha-btn nha-btn-primary nha-btn-sm" data-act="report" data-tab="<?php echo $_cob_tab; ?>" title="Generate a PDF or Excel report of this table">
                        <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Generate Report
                    </button>
                    <?php } ?>
                    <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" data-act="expand" data-tab="<?php echo $_cob_tab; ?>">Expand all</button>
                    <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" data-act="collapse" data-tab="<?php echo $_cob_tab; ?>">Collapse all</button>
                </span>
            </div>
            <div class="nha-tbl-scroll">
                <table class="nha-tbl cob-tbl" id="cob-tbl-<?php echo $_cob_tab; ?>">
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <?php foreach ($_cob_cols as [$_k, $_label, $_hint]) { ?>
                            <th class="text-end" data-col="<?php echo $_k; ?>" title="<?php echo htmlspecialchars($_hint); ?>"><?php echo htmlspecialchars($_label); ?></th>
                            <?php } ?>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="cob-tbody-<?php echo $_cob_tab; ?>">
                        <tr><td colspan="11"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
            <?php if ($_cob_showNote) { ?>
            <p class="cob-note">Only <strong>Approved</strong> budget requests are counted. <strong>Approved Amount</strong> is what FMD suggested in the Budget Proposal module, as first suggested — before any revision or CBA;
                <strong>Amendment / Realignment</strong> shows, on a project, the <strong>total</strong> of all its approved amendments and realignments — signed and coloured: a deduction <strong>−</strong> in red and an addition <strong>+</strong> in green, so the budget that gave funds up reads <strong>−</strong> and the one that received them <strong>+</strong>. Parent rows and the <strong>TOTAL</strong> have no figure in this column; <strong>Approved Amount</strong> plus this column is the <strong>Revised Amount</strong>. <strong>Outstanding CBA</strong> is what approved CBAs still hold against the
                budget (their active reservations, read from the earmark table). A CBA reserves money: once an RFA or LAA is approved, the amount it draws comes off Outstanding CBA and shows under <strong>RFA Approved</strong> / <strong>LAA Issued</strong> instead. <strong>Budget Balance</strong> and <strong>Budget Balance Available for Earmarking</strong> are the Revised Amount less Outstanding CBA, RFA Approved and LAA Issued. A dash means zero; brackets mean negative.
                The table is in two parts. <strong>CO</strong> (capital outlay, accounts starting with 1) is the budget of the programs and their projects. <strong>COE</strong> is the budget
                that belongs to no program — a COE budget request has only line items, no program or project — and is split into <strong>PS</strong> (accounts 5-01) and
                <strong>MOOE</strong> (accounts 5-02), then by originating unit.</p>
            <?php } ?>
        </div>
    </div>
    <?php } ?>

<?php
// "View" on a project row: the approved request in full — programs, projects, line items, supporting documents.
// window.cobModal.open(budgetReferenceNumber, projectCode) — GET /cob/read/{id}.
include __DIR__.'/readone/cob-readone.php';

// "Generate Report" on a tree tab: a dialog to pick how deep the report goes, preview it, and export it to PDF or Excel.
// window.cobReport.open(tab) — GET /cob/report (CobReportService). Only loaded while $_cob_showReport is on (nothing opens it otherwise).
if ($_cob_showReport) {
    include __DIR__.'/readone/cob-report-readone.php';
}
?>

</div><!-- /#cob-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   CORPORATE OPERATING BUDGET (COB)   prefix: cob_   (read-only)
   Backed by CobService → app/routes/cob.php:
     GET /cob/filters   the filter drop-downs        GET /cob/summary   the KPI cards
     GET /cob/rollup    one tree per tab (layout_by)
   and, for the View modal, GET /cob/read/{id} (readone/cob-readone.php → window.cobModal), and for Generate Report — a button on each tree tab —
   GET /cob/report (readone/cob-report-readone.php → window.cobReport): pick how deep the report goes, preview it, export PDF or Excel. The
   report is built by the server from the same filters and the same Amount Unit as this page.

   What it shows: ONLY the Approved budget requests (fims.budget_req, status 'Approved') and what hangs off them — programs, sub-programs, projects,
   chart-of-accounts line items, supporting documents — in the eight columns of the COB Monitoring sheet (CobService has the exact definitions):
     Approved Amount        what FMD suggested in the Budget Proposal module, as first suggested (before any revision or CBA)
     Amendment / Realignment on a project, the TOTAL of all its approved ones (signed: an addition is + in green, a deduction − in red; a realignment is − on the budget that
                            gave funds up and + on the one that received them; the server's revision_amount); nothing on a parent row or the TOTAL
     Revised Amount         = Approved Amount + the net of all the amendments and realignments (worked out per line)
     RFA Approved / LAA Issued   drawn from the CBAs' reservations by approved RFAs / LAAs
     Budget Balance         = Revised Amount - Outstanding CBA - RFA Approved - LAA Issued (the server's remaining_balance)
     Outstanding CBA        still held against the budget by approved CBAs: their active reservations only — what an approved RFA / LAA has drawn comes OFF it (the server's cba_requested)
     Budget Balance Available for Earmarking = Revised Amount - Outstanding CBA - RFA Approved - LAA Issued (what a new CBA can still draw; the server's available_amount)
   The columns are shown in that order (COB_COLS / $_cob_cols), after Approved Amount and Amendment / Realignment; the keys the API sends keep their old names.
   Outstanding CBA, RFA Approved and LAA Issued are all summed by the server from fims.budget_line_item_earmark (BudgetLineBalanceSql::earmarkAggregateSql()), so they —
   and Budget Balance and Budget Balance Available for Earmarking, which are worked out from them — change as soon as a CBA / RFA / LAA is approved or deleted; this page only displays them.
   CO and COE: the Per Program tree is asked for with split_coe=1 and so starts with two rows — CO (capital outlay: Program > Sub-Program > Originating Unit >
   Project) and COE (the lines of a budget request that has no program, as PS / MOOE > Originating Unit > Request). A line is told apart by its account code:
   1… is CO, 5-01… is PS, 5-02… is MOOE; PS and MOOE are the COE (CobService::expenseClassSql() / costTypeOf()). Those are the 'bucket', 'class' and 'request' nodes.
   The server decides what each user may see (their RC and the RCs below it; FMD and administrators see all) — nothing here can widen it.
═══════════════════════════════════════════════════════════════ */

const COB_API = '<?php echo $_cob_api; ?>' + '/cob';
// The trees the page can show; the FIRST is the one it opens on (Per Program: Program > Sub-Program > Originating Unit > Project). There is no tab
// bar, so only that one is ever loaded — the others are kept so a tab bar can come back.
const COB_TABS = ['program', 'region', 'fund_source', 'component'];
const COB_UNITS = {
    full: { div: 1, suffix: '', note: 'Amounts in ₱' },
    thousand: { div: 1e3, suffix: ' K', note: 'Amounts in ₱ thousands' },
    million: { div: 1e6, suffix: ' M', note: 'Amounts in ₱ millions' },
    billion: { div: 1e9, suffix: ' B', note: 'Amounts in ₱ billions' },
};
// the eight amount columns, in table order (the header in this file's markup lists the same keys)
const COB_COLS = ['approved_amount', 'revision_amount', 'revised_amount', 'rfa_approved', 'laa_issued', 'remaining_balance', 'cba_requested', 'available_amount'];
const COB_UNIT_STORE = 'cob.amountUnit';
// the id of each filter control -> the query-string key the API reads
const COB_FILTER_IDS = { 'cob-f-fy': 'fiscal_yr', 'cob-f-fund': 'type_of_fund_id', 'cob-f-component': 'component_id', 'cob-f-region': 'region_id', 'cob-f-program': 'program_code' };

const cob_state = {
    tab: COB_TABS[0],
    unit: 'full',
    filters: { fiscal_yr: '', type_of_fund_id: '', component_id: '', region_id: '', program_code: '', search: '' },
    defaultFiscalYr: '',
    summary: null,
    data: Object.fromEntries(COB_TABS.map(t => [t, null])),             // the last tree of each tab
    totals: Object.fromEntries(COB_TABS.map(t => [t, null])),
    expanded: Object.fromEntries(COB_TABS.map(t => [t, new Set()])),
    rootsSeen: Object.fromEntries(COB_TABS.map(t => [t, new Set()])),    // the CO / COE rows already opened once — so one the user closes stays closed after a reload
    searchTimer: null,   // the typing pause before the Search box reloads
    loaded: {},    // tab -> the query it was loaded with (a tab reloads only when that changes)
    seq: {},       // tab -> the newest request number; an older response is dropped
    busy: 0,
};

const $cob = id => document.getElementById(id);
const cob_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ── Amounts ───────────────────────────────────────────────── */
function cob_scaled(v) {
    const u = COB_UNITS[cob_state.unit] || COB_UNITS.full;
    return (Number(v || 0) / u.div).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// the KPI cards: with the peso sign and the unit
function cob_fmt(v) {
    const u = COB_UNITS[cob_state.unit] || COB_UNITS.full;
    return '₱ ' + cob_scaled(v) + u.suffix;
}
// the table, as on the sheet: no sign (the heading says ₱ and the unit), a dash for zero, a negative in red brackets
function cob_num(v) {
    const n = Number(v || 0);
    if (Math.abs(n) < 0.005) return '<span class="cob-dash">-</span>';
    return n < 0 ? `<span class="cob-neg">(${cob_scaled(-n)})</span>` : cob_scaled(n);
}

/* ── Toast / busy / request ────────────────────────────────── */
function cob_toast(msg, type = 'info') {
    if (!window.Swal) return;
    const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'info';
    Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true }).fire({ icon, title: msg });
}
function cob_busy(on) {
    cob_state.busy = Math.max(0, cob_state.busy + (on ? 1 : -1));
    if (on && cob_state.busy === 1) window.PagePreloader?.show();
    if (!on && cob_state.busy === 0) window.PagePreloader?.hide();
}
// `path` is given WITHOUT a leading slash ('filters', 'summary', 'rollup') and joined with '/' here. A standalone '/filters' string would be
// rewritten to '/fims-nha/filters' by _shared/subfolder-prefix.php on a sub-folder deployment (the live site), giving /api/cob/fims-nha/filters — which
// matches no route, falls into the API catch-all and comes back 401. A '/' after a '+' is left alone, like every other page's base + '/path'.
async function cob_request(path, params) {
    const qs = params ? '?' + new URLSearchParams(params) : '';
    const res = await fetch(COB_API + '/' + path + qs, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    let json = null;
    try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
    return json;
}
// The filters that are set, plus central=1: the server honours it only for the central review RC (FMD), so it widens nothing for anyone else.
function cob_params(extra = {}) {
    const p = { central: '1' };
    for (const [k, v] of Object.entries(cob_state.filters)) if (v !== '' && v != null) p[k] = v;
    return Object.assign(p, extra);
}

/* ── Boot ──────────────────────────────────────────────────── */
async function cob_boot() {
    try { const u = localStorage.getItem(COB_UNIT_STORE); if (u && COB_UNITS[u]) cob_state.unit = u; } catch (e) { /* storage may be blocked */ }
    $cob('cob-f-unit').value = cob_state.unit;
    cob_unitNote();
    cob_bindEvents();
    cob_showTab(cob_state.tab);
    cob_busy(true);
    try { await cob_loadFilterOptions(true); } catch (e) { cob_toast(e.message, 'error'); }
    finally { cob_busy(false); }
    cob_reload();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', cob_boot); else cob_boot();

function cob_bindEvents() {
    $cob('cob-refresh').addEventListener('click', cob_refresh);
    $cob('cob-reset').addEventListener('click', cob_resetFilters);
    for (const id of Object.keys(COB_FILTER_IDS)) $cob(id).addEventListener('change', cob_onFilterChange);
    $cob('cob-f-unit').addEventListener('change', e => {
        cob_state.unit = COB_UNITS[e.target.value] ? e.target.value : 'full';
        try { localStorage.setItem(COB_UNIT_STORE, cob_state.unit); } catch (err) { /* convenience only */ }
        cob_unitNote(); cob_renderSummary(); cob_renderActive();           // a display scale: nothing to refetch
    });
    $cob('cob-f-search').addEventListener('input', e => {
        $cob('cob-f-search-clear').hidden = !e.target.value;
        clearTimeout(cob_state.searchTimer);
        cob_state.searchTimer = setTimeout(cob_onFilterChange, 350);
    });
    $cob('cob-f-search-clear').addEventListener('click', () => { $cob('cob-f-search').value = ''; $cob('cob-f-search-clear').hidden = true; cob_onFilterChange(); });

    // trees: one delegated handler per body — View buttons first, then the row toggle
    for (const tab of COB_TABS) {
        const body = $cob('cob-tbody-' + tab);
        body.addEventListener('click', e => {
            const view = e.target.closest('[data-view]');
            if (view) { cob_openRequest(view); return; }
            const row = e.target.closest('tr[data-path]');
            if (row && row.classList.contains('cob-has-kids')) cob_toggle(tab, row.dataset.path);
        });
        body.addEventListener('keydown', e => {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            const row = e.target.closest('tr[data-path].cob-has-kids');
            if (row && e.target === row) { e.preventDefault(); cob_toggle(tab, row.dataset.path); }
        });
    }
    document.querySelectorAll('#cob-root [data-act="expand"], #cob-root [data-act="collapse"]').forEach(b =>
        b.addEventListener('click', () => cob_expandAll(b.dataset.tab, b.dataset.act === 'expand')));
    document.querySelectorAll('#cob-root [data-act="report"]').forEach(b =>
        b.addEventListener('click', () => {
            if (!window.cobReport) { cob_toast('The report dialog is not available.', 'error'); return; }
            window.cobReport.open(b.dataset.tab);
        }));
}

function cob_unitNote() {
    const note = (COB_UNITS[cob_state.unit] || COB_UNITS.full).note;
    document.querySelectorAll('#cob-root .cob-unit-note').forEach(el => { el.textContent = note; });
}

function cob_openRequest(btn) {
    if (!window.cobModal) { cob_toast('The request viewer is not available.', 'error'); return; }
    window.cobModal.open(btn.dataset.view, btn.dataset.project || '');
}

/* ── Filters ───────────────────────────────────────────────── */
function cob_fillSelect(id, allLabel, items, valueOf, labelOf) {
    const sel = $cob(id), keep = sel.value;
    sel.innerHTML = `<option value="">${cob_esc(allLabel)}</option>` + items.map(i => `<option value="${cob_esc(valueOf(i))}">${cob_esc(labelOf(i))}</option>`).join('');
    sel.value = [...sel.options].some(o => o.value === keep) ? keep : '';     // a choice that no longer exists falls back to "all"
}
async function cob_loadFilterOptions(first) {
    const { data } = await cob_request('filters', { central: '1' });
    cob_fillSelect('cob-f-fy', 'All Years', data.fiscal_years || [], y => y, y => y);
    cob_fillSelect('cob-f-fund', 'All Types of Fund', data.types_of_fund || [], f => f.type_of_fund_id, f => f.name);
    cob_fillSelect('cob-f-component', 'All Components', data.components || [], c => c.component_id, c => c.name);
    cob_fillSelect('cob-f-region', 'All Regions', data.regions || [], r => r.region_id, r => r.rc_name || r.region_id);
    cob_fillSelect('cob-f-program', 'All Programs', data.programs || [], p => p.program_code, p => p.program_name ? `${p.program_name} (${p.program_code})` : p.program_code);
    // the first load opens on the newest fiscal year that has approved data, not on every year at once
    cob_state.defaultFiscalYr = (data.fiscal_years || [])[0] != null ? String(data.fiscal_years[0]) : '';
    if (first) $cob('cob-f-fy').value = cob_state.defaultFiscalYr;
    cob_readFilters();
}
function cob_readFilters() {
    for (const [id, key] of Object.entries(COB_FILTER_IDS)) cob_state.filters[key] = $cob(id).value;
    cob_state.filters.search = $cob('cob-f-search').value.trim();
}
function cob_onFilterChange() {
    clearTimeout(cob_state.searchTimer);          // every filter change reads the search box too — a typing pause still waiting would only reload twice
    cob_readFilters();
    cob_reload();
}
function cob_resetFilters() {
    for (const id of Object.keys(COB_FILTER_IDS)) $cob(id).value = '';
    $cob('cob-f-fy').value = cob_state.defaultFiscalYr;
    $cob('cob-f-search').value = ''; $cob('cob-f-search-clear').hidden = true;
    cob_onFilterChange();
}

/* ── Loading ───────────────────────────────────────────────── */
function cob_reload() {
    cob_state.loaded = {};
    return Promise.all([cob_loadSummary(), cob_loadTab(cob_state.tab)]);
}
async function cob_refresh() {
    cob_busy(true);
    try { await cob_loadFilterOptions(false); } catch (e) { cob_toast(e.message, 'error'); }
    finally { cob_busy(false); }
    cob_reload();
}
async function cob_loadSummary() {
    const my = cob_state.seq.summary = (cob_state.seq.summary || 0) + 1;
    cob_busy(true);                                  // the preloader covers the KPI cards too, not only the table
    try {
        const json = await cob_request('summary', cob_params());
        if (my !== cob_state.seq.summary) return;
        cob_state.summary = json.data;
        cob_renderSummary();
    } catch (e) {
        if (my !== cob_state.seq.summary) return;
        cob_state.summary = null;
        cob_renderSummary();
        cob_toast(e.message, 'error');
    } finally {
        cob_busy(false);
    }
}
async function cob_loadTab(tab) {
    const key = JSON.stringify(cob_params());
    if (cob_state.loaded[tab] === key) return;
    const my = cob_state.seq[tab] = (cob_state.seq[tab] || 0) + 1;
    cob_busy(true);
    try {
        // Per Program is cut into CO and COE (split_coe) — a budget request with no program is COE, told apart into PS and MOOE by account code
        const json = await cob_request('rollup', cob_params(Object.assign({ layout_by: tab }, tab === 'program' ? { split_coe: '1' } : {})));
        if (my !== cob_state.seq[tab]) return;
        cob_state.data[tab] = json.data || []; cob_state.totals[tab] = json.totals || null;
        cob_state.loaded[tab] = key;
        cob_openRoots(tab);
        cob_renderTree(tab);
    } catch (e) {
        if (my !== cob_state.seq[tab]) return;
        delete cob_state.loaded[tab];
        $cob('cob-tbody-' + tab).innerHTML = `<tr><td colspan="11"><div class="nha-error">${cob_esc(e.message)}</div></td></tr>`;
        cob_toast(e.message, 'error');
    } finally {
        cob_busy(false);
    }
}

/* ── The view on screen ────────────────────────────────────── */
// There is no tab bar any more, so this runs once, at boot, for the page's first view (Per Program): it shows that panel and hides the others.
function cob_showTab(tab) {
    cob_state.tab = tab;
    for (const t of COB_TABS) $cob('cob-panel-' + t).classList.toggle('active', t === tab);
}
function cob_renderActive() {
    if (cob_state.data[cob_state.tab]) cob_renderTree(cob_state.tab);
}

/* ── KPI cards ─────────────────────────────────────────────── */
function cob_renderSummary() {
    const s = cob_state.summary;
    $cob('cob-kpi-requests').textContent = s ? s.requests : '—';
    $cob('cob-kpi-requests-sub').textContent = s ? `${s.programs} program${s.programs === 1 ? '' : 's'} · ${s.projects} project${s.projects === 1 ? '' : 's'}` : 'Matches current filters';
    $cob('cob-kpi-revised').textContent = s ? cob_fmt(s.revised_amount) : '—';
    $cob('cob-kpi-available').textContent = s ? cob_fmt(s.available_amount) : '—';
    $cob('cob-kpi-remaining').textContent = s ? cob_fmt(s.remaining_balance) : '—';
}

/* ── Trees ─────────────────────────────────────────────────── */
const COB_CARET = '<svg class="cob-caret%OPEN%" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>';
const COB_CLIP = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>';

// The page opens with its two halves, CO and COE, already unrolled (the programs / the PS and MOOE rows are what people come for) — each only the
// first time it is seen, so closing one is remembered across a refresh or a filter change.
function cob_openRoots(tab) {
    for (const n of cob_state.data[tab] || []) {
        if (n.type !== 'bucket' || cob_state.rootsSeen[tab].has(n.key)) continue;
        cob_state.rootsSeen[tab].add(n.key);
        cob_state.expanded[tab].add(`/${n.type}:${n.key}`);
    }
}

// the Name cell: the name, then what tells two of the same name apart. A node's code has a column of its own.
// A request (the leaf of the COE half — a COE budget request has no project) is drawn like a project: its reference number, then its fiscal year.
function cob_nodeLabel(n, tab) {
    let html = `<span>${n.unassigned ? `<span class="cob-unassigned">${cob_esc(n.label)}</span>` : cob_esc(n.label)}</span>`;
    if (n.type === 'project' || n.type === 'request') {
        // line 1: the project name and its supporting documents; line 2 (muted): the fiscal year and the requesting office — except in the Per Program
        // view, where the office is the row just above the project and saying it again would only repeat it
        if (n.attachment_count > 0) html += ` <span class="cob-clip" title="${n.attachment_count} supporting document${n.attachment_count === 1 ? '' : 's'}">${COB_CLIP}${n.attachment_count}</span>`;
        const meta = [n.fiscal_yr ? `<span class="nha-bdg nha-bdg-gray nha-bdg-xs">FY ${cob_esc(n.fiscal_yr)}</span>` : '', n.rc_name && tab !== 'program' ? cob_esc(n.rc_name) : ''].filter(Boolean).join(' ');
        if (meta) html += `<span class="cob-sub">${meta}</span>`;
    }
    return html;
}

// The Amendment / Realignment column is SIGNED and coloured: funds taken off a budget (a decrease, or what a realignment gave up) read - in red, funds added to it (an
// increase, or what a realignment received) + in green. Every other column keeps the sheet's brackets.
function cob_signed(v) {
    const n = Number(v || 0);
    if (Math.abs(n) < 0.005) return '<span class="cob-dash">-</span>';
    return n < 0 ? `<span class="cob-neg">−${cob_scaled(-n)}</span>` : `<span class="cob-pos">+${cob_scaled(n)}</span>`;
}
// The Amendment / Realignment cell. A project (or a COE request) shows the TOTAL of every approved amendment and realignment on it to date — the server's revision_amount, the same figure
// Revised Amount is built on (Approved Amount + this = Revised Amount) — whether the funds came by amendment or by realignment: an amendment and a realignment into the same project ADD UP
// (+1,000,000 and +1,000,000 read +2,000,000). Nothing at all is a dash. A parent row (bucket, program, sub-program, unit, class) and the grand TOTAL row show NOTHING in this column: the
// figure is per project and is not rolled up.
function cob_adjCell(o, leaf) {
    return leaf ? `<td class="cob-num">${cob_signed(o.revision_amount)}</td>` : '<td class="cob-num"></td>';
}
function cob_amountCells(o, leaf) {
    return COB_COLS.map(k => k === 'revision_amount' ? cob_adjCell(o, !!leaf) : `<td class="cob-num">${cob_num(o[k])}</td>`).join('');
}

function cob_treeRow(n, depth, path, hasKids, open, tab) {
    const view = (n.type === 'project' || n.type === 'request') && n.budget_reference_number
        ? `<button type="button" class="nha-btn-act view" data-view="${cob_esc(n.budget_reference_number)}" data-project="${cob_esc(n.code || '')}" title="View the approved request ${cob_esc(n.budget_reference_number)}">View</button>`
        : '';
    // CO / COE: the row carries its own class (cob-b-co / cob-b-coe) so each half has its own colour, and its code is drawn as a pill
    const bucket = n.type === 'bucket';
    const bucketCls = bucket ? ' cob-b-' + String(n.key).toLowerCase().replace(/[^a-z0-9]/g, '') : '';
    const codeCell = bucket && n.code ? `<span class="cob-bkt-badge">${cob_esc(n.code)}</span>` : cob_esc(n.code || '');
    return `<tr class="cob-row cob-d${Math.min(depth, 4)} cob-t-${cob_esc(n.type)}${bucketCls}${hasKids ? ' cob-has-kids' : ''}${open ? ' open' : ''}" data-path="${cob_esc(path)}"${hasKids ? ` tabindex="0" role="button" aria-expanded="${open}"` : ''}>
        <td class="cob-code">${codeCell}</td>
        <td><div class="cob-name" style="--d:${depth}">${hasKids ? COB_CARET.replace('%OPEN%', open ? ' open' : '') : '<span class="cob-caret-sp"></span>'}${cob_nodeLabel(n, tab)}</div></td>
        ${cob_amountCells(n, n.type === 'project' || n.type === 'request')}
        <td class="no-print">${view}</td></tr>`;
}

// Only the rows that are visible are built: a collapsed node's children never reach the DOM.
function cob_collect(tab, nodes, depth, parentPath, out) {
    for (const n of nodes) {
        const path = `${parentPath}/${n.type}:${n.key}`;
        const hasKids = Array.isArray(n.children) && n.children.length > 0;
        const open = hasKids && cob_state.expanded[tab].has(path);
        out.push(cob_treeRow(n, depth, path, hasKids, open, tab));
        if (open) cob_collect(tab, n.children, depth + 1, path, out);
    }
}
function cob_renderTree(tab) {
    const nodes = cob_state.data[tab] || [], totals = cob_state.totals[tab];
    const body = $cob('cob-tbody-' + tab);
    if (!nodes.length) {
        body.innerHTML = `<tr><td colspan="11"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No approved budget found.</span>
            <div class="nha-empty-text">Only Approved budget requests are part of the COB. Try a different fiscal year or clear the filters.</div></div></td></tr>`;
        $cob('cob-info-' + tab).textContent = 'No approved line items match the current filters';
        return;
    }
    const rows = [];
    cob_collect(tab, nodes, 0, '', rows);
    // the TOTAL row goes first, right under the headings, not after the last row (as on the RFA / LAA Monitoring page)
    if (totals) rows.unshift(`<tr class="cob-total-row"><td colspan="2">TOTAL</td>${cob_amountCells(totals)}<td class="no-print"></td></tr>`);
    body.innerHTML = rows.join('');
    const n = totals ? totals.line_count : 0;
    $cob('cob-info-' + tab).textContent = `${n} approved line item${n === 1 ? '' : 's'} — click a row to expand it`;
}
function cob_toggle(tab, path) {
    const set = cob_state.expanded[tab];
    if (set.has(path)) set.delete(path); else set.add(path);
    cob_renderTree(tab);
    // the re-render replaced the row: put the keyboard focus back on it
    const row = [...$cob('cob-tbody-' + tab).querySelectorAll('tr[data-path]')].find(r => r.dataset.path === path);
    if (row && document.activeElement === document.body) row.focus();
}
function cob_expandAll(tab, expand) {
    const set = cob_state.expanded[tab];
    set.clear();
    if (expand) {
        const walk = (nodes, parentPath) => nodes.forEach(n => {
            const path = `${parentPath}/${n.type}:${n.key}`;
            if (Array.isArray(n.children) && n.children.length) { set.add(path); walk(n.children, path); }
        });
        walk(cob_state.data[tab] || [], '');
    }
    cob_renderTree(tab);
}
</script>
