<?php

namespace PHPMaker2024\FIMS;

/**
 * rfa-laa-monitoring-page.php — "RFA / LAA Monitoring": the COB page's roll-up (cob-page.php) with its own five amount columns, starting at the
 * Revised Amount.
 *
 * One tree table over the Approved budget requests that STARTS AT THE PROJECT: every project is a top-level row (no Program / Sub-Program /
 * Originating Unit rows above it — where a project sits is a muted line under its name) and its children are the RFAs / LAAs, then the voucher of an
 * RFA — with the columns
 *   Revised Amount · RFA / LAA Amount · Disbursement · Unallocated Amount · Account Payable
 * (CobService works the figures out per budget line; see its header comment for each definition). The Revised Amount is the proposed amount net
 * of its adjustments / realignments (proposed + adjustment, the adjustment being signed) — worked out per line, not the recorded revised_amount.
 * Disbursement is what the disbursement vouchers connected to the RFAs / LAAs have disbursed (each voucher's gross_amount, a cancelled or rejected one
 * not counted), and it is part of every total — the project rows, the TOTAL and the cards. Account Payable is the RFA / LAA Amount less the Disbursement.
 *
 * The server still answers with its Program > Sub-Program > Originating Unit > Project tree (layout_by=program, so every filter and the totals work as
 * before); the page lifts the projects out of it, in that same order, so a program's projects stay together.
 *
 * CO and COE: the tree is asked for with split_coe=1, so it comes in two halves and the page keeps them apart as two rows, each with its own figures.
 * CO (capital outlay — chart-of-accounts codes starting with 1) holds the projects of the programs, as above. COE holds the budget of the requests that have
 * NO program (a COE budget request has line items only, no Program / Sub-Program / Project — they used to be a "No project" row): PS (accounts 5-01) and
 * MOOE (accounts 5-02) are the rows under it, and under each the requests (type 'request', named by their reference number, the originating unit on the
 * muted line) — with their RFAs / LAAs and vouchers exactly like a project's. See CobService::splitCoe().
 *
 * Open a project and the RFAs and LAAs drawn against its budget lines are listed under it (RFA / LAA Amount = what each drew there); open an RFA and
 * its disbursement voucher is listed under it — fetched by the budget_allocation's dv_id from fims.disbursement_vouchers (Disbursement = the voucher's
 * gross_amount). That goes for an RFA or an LAA alike: a row with a voucher connected shows a "1 DV" tag and drops down to it when clicked; one without shows
 * "No DV". These rows are detail: the totals above them do not change. The server sends them when asked with with_allocations=1.
 *
 * Same API as the COB page: GET /cob/filters, /cob/summary, /cob/rollup (layout_by=program) and, for a project row's View, /cob/read/{id} through the
 * shared request viewer (readone/cob-readone.php → window.cobModal, which reads this page's COB_API). The server decides what each user may see — the
 * user's RC and the RCs below it; FMD and administrators see all — and nothing here can widen it.
 */
@session_start();
$_rlm_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<?php /* The shared theme, linked the way every other custom page links it (GetUrl adds the app's base path; the version comes from the file
          next to this page, so a changed stylesheet is never served stale). */ ?>
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
#rlm-root .nha-summary { grid-template-columns: repeat(3, 1fr); }
@media (max-width: 900px) { #rlm-root .nha-summary { grid-template-columns: repeat(2, 1fr); } }

/* the monitoring sheet: Project | five amount columns. No colour coding — the hierarchy is shown by indentation, the caret and bold project rows. */
/* Column widths: Actions is narrow, Project takes what is left (never less than 300px, so a name does not wrap word by word), each amount column is at
   least 116px. The headers wrap onto two lines. When the screen is narrower than the table, the table scrolls sideways. */
#rlm-root .rlm-tbl { min-width: 940px; table-layout: auto; }
#rlm-root .rlm-tbl th, #rlm-root .rlm-tbl td { box-sizing: border-box; }
#rlm-root .rlm-tbl thead th { cursor: default; vertical-align: middle; white-space: normal; line-height: 1.3; padding: 10px 10px; }
#rlm-root .rlm-tbl thead th.text-end { text-align: right; }
#rlm-root .rlm-tbl thead th.text-end, #rlm-root .rlm-tbl td.rlm-num { min-width: 116px; }
#rlm-root .rlm-tbl thead th:nth-child(1) { min-width: 300px; }
#rlm-root .rlm-tbl thead th:last-child { width: 72px; }

#rlm-root .rlm-toolbar { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; padding: 10px 14px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#rlm-root .rlm-hint { font-size: 12px; color: var(--afims-text-muted, #62657E); }
#rlm-root .rlm-tool-btns { display: flex; gap: 6px; }
#rlm-root .rlm-note { margin: 0; padding: 10px 14px; font-size: 11.5px; line-height: 1.5; color: var(--afims-text-muted, #62657E); border-top: 1px solid var(--afims-border, #E4ECF2); }

/* tree rows */
#rlm-root .rlm-row.rlm-has-kids { cursor: pointer; }
#rlm-root .rlm-row.rlm-has-kids:focus-visible { outline: 2px solid var(--afims-heading-fill, #000E3E); outline-offset: -2px; }
/* a project — or, in the COE half, a request — is the strong row; an RFA / LAA under it and a voucher under an RFA are plain */
#rlm-root .rlm-t-project > td, #rlm-root .rlm-t-request > td { font-weight: 600; color: var(--afims-heading, #000E3E); }
/* The two halves, CO and COE, each start on a row of their own (the PS / MOOE rows of the COE half sit between it and its requests) and each has a colour of
   its own (CO blue, COE green): a tinted band across the row, a solid bar down its left edge, and the code as a solid pill. */
#rlm-root .rlm-t-bucket { --b-accent: var(--afims-heading, #000E3E); --b-soft: var(--afims-surface-subtle, #F4F7FA); }
#rlm-root .rlm-b-co  { --b-accent: var(--afims-primary, #0035A9); --b-soft: var(--afims-primary-soft, #E6EBF6); }
#rlm-root .rlm-b-coe { --b-accent: var(--afims-success-hover, #0C7539); --b-soft: var(--afims-success-soft, #DBF2E5); }
#rlm-root .rlm-t-bucket > td { font-weight: 700; color: var(--b-accent); background: var(--b-soft); border-top: 2px solid var(--b-accent); }
#rlm-root .rlm-t-bucket > td:first-child { box-shadow: inset 5px 0 0 var(--b-accent); }
#rlm-root .rlm-t-bucket:hover > td { background-image: linear-gradient(rgba(0, 0, 0, .05), rgba(0, 0, 0, .05)); }
#rlm-root .rlm-bkt-badge { display: inline-block; min-width: 40px; padding: 2px 8px; border-radius: 4px; text-align: center; font-size: 11px; font-weight: 700; letter-spacing: .04em; color: #fff; background: var(--b-accent); }
#rlm-root .rlm-t-class > td { font-weight: 600; color: var(--afims-heading, #000E3E); }
#rlm-root .rlm-t-allocation > td, #rlm-root .rlm-t-dv > td { vertical-align: middle; }
/* a voucher is the deepest, quietest row */
#rlm-root .rlm-t-dv > td { font-size: 12px; background: var(--afims-surface-subtle, #F4F7FA); }
/* The name flows as ordinary text (name, then FY / documents after it) and wraps inside its cell; the caret is pinned to the left of it, so it can never end
   up on a line of its own. Indented 16px per level. */
#rlm-root .rlm-name { position: relative; line-height: 1.45; padding-left: calc(var(--d, 0) * 16px + 22px); }
#rlm-root .rlm-name > span:not(.rlm-caret-sp) ~ *:not(.rlm-sub) { margin-left: 8px; }
/* a project row: the name (+ paperclip) on the first line, then ONE muted line under it with the fiscal year */
#rlm-root .rlm-name > .rlm-sub { display: block; margin-top: 3px; line-height: 1.5; }
#rlm-root .rlm-name > .rlm-sub .nha-bdg { margin-right: 6px; vertical-align: 1px; }
#rlm-root .rlm-t-project > td, #rlm-root .rlm-t-request > td { vertical-align: middle; }
#rlm-root .rlm-where { margin-left: 6px; }
#rlm-root .rlm-caret { position: absolute; left: calc(var(--d, 0) * 16px); top: 2px; width: 14px; height: 14px; transition: transform .15s; stroke: currentColor; }
#rlm-root .rlm-caret.open { transform: rotate(90deg); }
#rlm-root .rlm-caret-sp { display: none; }
#rlm-root .rlm-sub { font-size: 11.5px; font-weight: 400; color: var(--afims-text-muted, #62657E); }
#rlm-root .rlm-unassigned { font-style: italic; color: #B45309; }
#rlm-root .rlm-num { font-family: 'IBM Plex Mono', monospace; font-size: 12px; text-align: right; white-space: nowrap; }
#rlm-root .rlm-neg { color: #dc2626; }
#rlm-root .rlm-dash { color: var(--afims-text-disabled, #A9AAB8); }
#rlm-root .rlm-clip { display: inline-flex; align-items: center; gap: 3px; font-size: 11px; font-weight: 600; color: var(--afims-primary-text, #0035A9); }
#rlm-root .rlm-clip svg { width: 12px; height: 12px; stroke: currentColor; }
/* the TOTAL row sits at the top of the body, so its rule is underneath it */
#rlm-root .rlm-total-row > td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); border-bottom: 2px solid var(--afims-border, #E4ECF2); }
</style>

<div id="rlm-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">RFA / LAA Monitoring</div>
                <div class="nha-hdr-sub">Approved budget requests only — the Revised Amount, what RFAs and LAAs have drawn from it, what has been disbursed, what is unallocated and what is payable</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="rlm-refresh" type="button" class="nha-btn-hdr" title="Refresh">
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
                <div class="nha-sc-val" id="rlm-kpi-requests">—</div>
            </div></div>
            <p class="nha-kpi-sub" id="rlm-kpi-requests-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Revised Amount</div>
                <div class="nha-sc-val" id="rlm-kpi-revised">—</div>
            </div></div>
            <p class="nha-kpi-sub">Proposed amount net of adjustments / realignments</p>
        </div>
        <div class="nha-sc nha-sc-blue">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">RFA / LAA Amount</div>
                <div class="nha-sc-val" id="rlm-kpi-rfalaa">—</div>
            </div></div>
            <p class="nha-kpi-sub">RFA Approved plus LAA Issued</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Disbursement</div>
                <div class="nha-sc-val" id="rlm-kpi-disbursement">—</div>
            </div></div>
            <p class="nha-kpi-sub">Gross amount of the connected disbursement vouchers</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Unallocated Amount</div>
                <div class="nha-sc-val" id="rlm-kpi-unallocated">—</div>
            </div></div>
            <p class="nha-kpi-sub">Revised Amount less the RFA / LAA Amount</p>
        </div>
        <div class="nha-sc nha-sc-red">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Account Payable</div>
                <div class="nha-sc-val" id="rlm-kpi-payable">—</div>
            </div></div>
            <p class="nha-kpi-sub">RFA / LAA Amount less Disbursement</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rlm-f-fy">Fiscal Year</label>
                <select id="rlm-f-fy" class="nha-filter-select"><option value="">All Years</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rlm-f-fund">Type of Fund</label>
                <select id="rlm-f-fund" class="nha-filter-select"><option value="">All Types of Fund</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rlm-f-component">Component</label>
                <select id="rlm-f-component" class="nha-filter-select"><option value="">All Components</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rlm-f-region">Region</label>
                <select id="rlm-f-region" class="nha-filter-select"><option value="">All Regions</option></select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rlm-f-program">Program</label>
                <select id="rlm-f-program" class="nha-filter-select"><option value="">All Programs</option></select>
            </div>
        </div>
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label" for="rlm-f-search">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="rlm-f-search" class="nha-filter-input" placeholder="Search request ref, program, project, account, office…" autocomplete="off">
                    <button class="nha-filter-clear-btn" id="rlm-f-search-clear" hidden type="button" aria-label="Clear search">×</button>
                </div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label" for="rlm-f-unit">Amount Unit</label>
                <select id="rlm-f-unit" class="nha-filter-select">
                    <option value="full">Full Amount</option>
                    <option value="thousand">Thousands (K)</option>
                    <option value="million">Millions (M)</option>
                    <option value="billion">Billions (B)</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button type="button" id="rlm-reset" class="nha-btn nha-btn-outline">Reset</button>
            </div>
        </div>
    </div>

    <!-- ── ROLL-UP: one row per Project, its RFAs / LAAs under it (and an RFA's voucher), five amount columns ── -->
    <?php
    // The amount columns (key = what the API sends, in this order) — the page's JS carries the same list (RLM_COLS).
    $_rlm_cols = [
        ['revised_computed', 'Revised Amount', 'The proposed amount net of its adjustments / realignments (a decrease lowers it): proposed + adjustment'],
        ['rfa_laa_amount', 'RFA / LAA Amount', 'RFA Approved plus LAA Issued — what approved RFAs and issued LAAs have drawn from the CBAs'],
        ['disbursement', 'Disbursement', 'The gross amount of the disbursement vouchers connected to the RFAs / LAAs (a cancelled or rejected voucher is not counted)'],
        ['unallocated_amount', 'Unallocated Amount', 'Revised Amount less the RFA / LAA Amount — the revised budget no RFA or LAA has drawn yet'],
        ['account_payable', 'Account Payable', 'RFA / LAA Amount less Disbursement — what the RFAs and LAAs have drawn that has not been disbursed yet'],
    ];
    ?>
    <div class="nha-table-wrap">
        <div class="rlm-toolbar no-print">
            <span class="rlm-hint"><span id="rlm-info">CO: Project → RFA / LAA → DV · COE: PS / MOOE → Request → RFA / LAA → DV</span> · <span class="rlm-unit-note">Amounts in ₱</span></span>
            <span class="rlm-tool-btns">
                <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" id="rlm-expand">Expand all</button>
                <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" id="rlm-collapse">Collapse all</button>
            </span>
        </div>
        <div class="nha-tbl-scroll">
            <table class="nha-tbl rlm-tbl" id="rlm-tbl">
                <thead>
                    <tr>
                        <th>Project</th>
                        <?php foreach ($_rlm_cols as [$_k, $_label, $_hint]) { ?>
                        <th class="text-end" data-col="<?php echo $_k; ?>" title="<?php echo htmlspecialchars($_hint); ?>"><?php echo htmlspecialchars($_label); ?></th>
                        <?php } ?>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="rlm-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <p class="rlm-note">Each row is a project of an <strong>Approved</strong> budget request; under the project's name is where it sits (program › sub-program › originating unit). <strong>Revised Amount</strong> is the proposed amount net of its adjustments and realignments (a decrease lowers it).
            <strong>RFA / LAA Amount</strong> is what approved RFAs and issued LAAs have drawn from the CBAs' reservations, and <strong>Unallocated Amount</strong> is the
            Revised Amount less it. <strong>Disbursement</strong> is the gross amount of the disbursement vouchers connected to the RFAs and LAAs (a cancelled or rejected voucher is not counted) and is included in every total, and <strong>Account Payable</strong> is the RFA / LAA Amount less it. Open a project to see
            the RFAs and LAAs drawn against it, and an RFA or LAA that has a disbursement voucher connected (a "1 DV" tag) to see it. A dash means zero; brackets mean negative.
            The table is in two parts. <strong>CO</strong> (capital outlay, accounts starting with 1) lists the projects of the programs. <strong>COE</strong> lists the budget that belongs to no program —
            a COE budget request has only line items, no program or project — split into <strong>PS</strong> (accounts 5-01) and <strong>MOOE</strong> (accounts 5-02), each request
            with its RFAs and LAAs under it.</p>
    </div>

<?php
// "View" on a project row: the approved request in full — programs, projects, line items, supporting documents.
// window.cobModal.open(budgetReferenceNumber, projectCode) — GET /cob/read/{id}. It is the COB page's viewer, shared as it is.
include __DIR__.'/readone/cob-readone.php';
?>

</div><!-- /#rlm-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   RFA / LAA MONITORING   prefix: rlm_   (read-only)
   The COB page's roll-up with its own five amount columns. Backed by CobService → app/routes/cob.php:
     GET /cob/filters   the filter drop-downs        GET /cob/summary   the KPI cards
     GET /cob/rollup?layout_by=program   the tree (Program > Sub-Program > Originating Unit > Project)
   and, for the View modal, GET /cob/read/{id} (readone/cob-readone.php → window.cobModal).
   The table STARTS AT THE PROJECT: rlm_projectsOf() lifts the projects out of that tree (same order, so a program's projects stay together) and they are
   the rows under CO; the levels above them survive only as the muted line under a project's name. The tree is asked for with split_coe=1, so it has two
   roots — CO (the projects of the programs) and COE (the budget of the requests with no program: PS / MOOE rows, then the requests, node type 'request') —
   told apart by chart-of-accounts code (1… CO, 5-01… PS, 5-02… MOOE; CobService::costTypeOf()). rlm_regroup() puts the table together from them.
   Under a project or a request — asked for with with_allocations=1 — the RFAs / LAAs drawn against it
   (node type 'allocation') and, under an RFA, its disbursement voucher (node type 'dv'). Those two are detail rows: they show their own figure in one
   column (RFA / LAA Amount, Disbursement) and dashes elsewhere, and no total counts them.
   What it shows: ONLY the Approved budget requests, in five amount columns that start at the Revised Amount:
     Revised Amount         budget_line_items.proposed_amount + adjustment (the adjustment is signed: a decrease / realignment out is negative), worked out per line
     RFA / LAA Amount       RFA Approved + LAA Issued: what approved RFAs / LAAs drew from the CBAs' reservations
     Disbursement           the gross_amount of the vouchers connected to the RFAs / LAAs (cancelled / rejected not counted), in every total
     Unallocated Amount     = Revised Amount - RFA / LAA Amount
     Account Payable        = RFA / LAA Amount - Disbursement
   The server decides what each user may see (their RC and the RCs below it; FMD and administrators see all) — nothing here can widen it.
═══════════════════════════════════════════════════════════════ */

// COB_API is the name the shared request viewer (readone/cob-readone.php) reads when a View is opened, so this page uses it for its own requests too
const COB_API = '<?php echo $_rlm_api; ?>' + '/cob';
const RLM_LAYOUT = 'program';
const RLM_UNITS = {
    full: { div: 1, suffix: '', note: 'Amounts in ₱' },
    thousand: { div: 1e3, suffix: ' K', note: 'Amounts in ₱ thousands' },
    million: { div: 1e6, suffix: ' M', note: 'Amounts in ₱ millions' },
    billion: { div: 1e9, suffix: ' B', note: 'Amounts in ₱ billions' },
};
// the five amount columns, in table order (the header in this file's markup lists the same keys)
const RLM_COLS = ['revised_computed', 'rfa_laa_amount', 'disbursement', 'unallocated_amount', 'account_payable'];
const RLM_UNIT_STORE = 'rlm.amountUnit';
// the id of each filter control -> the query-string key the API reads
const RLM_FILTER_IDS = { 'rlm-f-fy': 'fiscal_yr', 'rlm-f-fund': 'type_of_fund_id', 'rlm-f-component': 'component_id', 'rlm-f-region': 'region_id', 'rlm-f-program': 'program_code' };

const rlm = {
    unit: 'full',
    filters: { fiscal_yr: '', type_of_fund_id: '', component_id: '', region_id: '', program_code: '', search: '' },
    defaultFiscalYr: '',
    summary: null,
    data: null,                 // the tree
    totals: null,
    expanded: new Set(),        // paths of the rows that are open
    rootsSeen: new Set(),       // the CO / COE / PS / MOOE rows already opened once — so one the user closes stays closed after a reload
    searchTimer: null,          // the typing pause before the Search box reloads
    loaded: '',                 // the query the tree was loaded with (it reloads only when that changes)
    seq: 0,                     // the newest tree request; an older response is dropped
    summarySeq: 0,
    busy: 0,
};

const $rlm = id => document.getElementById(id);
const rlm_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ── Amounts ───────────────────────────────────────────────── */
function rlm_scaled(v) {
    const u = RLM_UNITS[rlm.unit] || RLM_UNITS.full;
    return (Number(v || 0) / u.div).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// the KPI cards: with the peso sign and the unit
function rlm_fmt(v) {
    const u = RLM_UNITS[rlm.unit] || RLM_UNITS.full;
    return '₱ ' + rlm_scaled(v) + u.suffix;
}
// the table, as on the sheet: no sign (the heading says ₱ and the unit), a dash for zero, a negative in red brackets
function rlm_num(v) {
    const n = Number(v || 0);
    if (Math.abs(n) < 0.005) return '<span class="rlm-dash">-</span>';
    return n < 0 ? `<span class="rlm-neg">(${rlm_scaled(-n)})</span>` : rlm_scaled(n);
}

/* ── Toast / busy / request ────────────────────────────────── */
function rlm_toast(msg, type = 'info') {
    if (!window.Swal) return;
    const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'info';
    Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 4000, timerProgressBar: true }).fire({ icon, title: msg });
}
function rlm_busy(on) {
    rlm.busy = Math.max(0, rlm.busy + (on ? 1 : -1));
    if (on && rlm.busy === 1) window.PagePreloader?.show();
    if (!on && rlm.busy === 0) window.PagePreloader?.hide();
}
// `path` is given WITHOUT a leading slash ('filters', 'summary', 'rollup') and joined with '/' here: a standalone '/filters' string is rewritten to
// '/fims-nha/filters' by _shared/subfolder-prefix.php on the live sub-folder deployment (see cob-page.php's cob_request).
async function rlm_request(path, params) {
    const qs = params ? '?' + new URLSearchParams(params) : '';
    const res = await fetch(COB_API + '/' + path + qs, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    let json = null;
    try { json = await res.json(); } catch (e) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) throw new Error((json && json.message) || ('HTTP ' + res.status));
    return json;
}
// The filters that are set, plus central=1: the server honours it only for the central review RC (FMD), so it widens nothing for anyone else.
function rlm_params(extra = {}) {
    const p = { central: '1' };
    for (const [k, v] of Object.entries(rlm.filters)) if (v !== '' && v != null) p[k] = v;
    return Object.assign(p, extra);
}

/* ── Boot ──────────────────────────────────────────────────── */
async function rlm_boot() {
    try { const u = localStorage.getItem(RLM_UNIT_STORE); if (u && RLM_UNITS[u]) rlm.unit = u; } catch (e) { /* storage may be blocked */ }
    $rlm('rlm-f-unit').value = rlm.unit;
    rlm_unitNote();
    rlm_bindEvents();
    rlm_busy(true);
    try { await rlm_loadFilterOptions(true); } catch (e) { rlm_toast(e.message, 'error'); }
    finally { rlm_busy(false); }
    rlm_reload();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', rlm_boot); else rlm_boot();

function rlm_bindEvents() {
    $rlm('rlm-refresh').addEventListener('click', rlm_refresh);
    $rlm('rlm-reset').addEventListener('click', rlm_resetFilters);
    for (const id of Object.keys(RLM_FILTER_IDS)) $rlm(id).addEventListener('change', rlm_onFilterChange);
    $rlm('rlm-f-unit').addEventListener('change', e => {
        rlm.unit = RLM_UNITS[e.target.value] ? e.target.value : 'full';
        try { localStorage.setItem(RLM_UNIT_STORE, rlm.unit); } catch (err) { /* convenience only */ }
        rlm_unitNote(); rlm_renderSummary(); if (rlm.data) rlm_renderTree();           // a display scale: nothing to refetch
    });
    $rlm('rlm-f-search').addEventListener('input', e => {
        $rlm('rlm-f-search-clear').hidden = !e.target.value;
        clearTimeout(rlm.searchTimer);
        rlm.searchTimer = setTimeout(rlm_onFilterChange, 350);
    });
    $rlm('rlm-f-search-clear').addEventListener('click', () => { $rlm('rlm-f-search').value = ''; $rlm('rlm-f-search-clear').hidden = true; rlm_onFilterChange(); });

    // the tree: one delegated handler — View buttons first, then the row toggle
    const body = $rlm('rlm-tbody');
    body.addEventListener('click', e => {
        const view = e.target.closest('[data-view]');
        if (view) { rlm_openRequest(view); return; }
        const row = e.target.closest('tr[data-path]');
        if (row && row.classList.contains('rlm-has-kids')) rlm_toggle(row.dataset.path);
    });
    body.addEventListener('keydown', e => {
        if (e.key !== 'Enter' && e.key !== ' ') return;
        const row = e.target.closest('tr[data-path].rlm-has-kids');
        if (row && e.target === row) { e.preventDefault(); rlm_toggle(row.dataset.path); }
    });
    $rlm('rlm-expand').addEventListener('click', () => rlm_expandAll(true));
    $rlm('rlm-collapse').addEventListener('click', () => rlm_expandAll(false));
}

function rlm_unitNote() {
    const note = (RLM_UNITS[rlm.unit] || RLM_UNITS.full).note;
    document.querySelectorAll('#rlm-root .rlm-unit-note').forEach(el => { el.textContent = note; });
}

function rlm_openRequest(btn) {
    if (!window.cobModal) { rlm_toast('The request viewer is not available.', 'error'); return; }
    window.cobModal.open(btn.dataset.view, btn.dataset.project || '');
}

/* ── Filters ───────────────────────────────────────────────── */
function rlm_fillSelect(id, allLabel, items, valueOf, labelOf) {
    const sel = $rlm(id), keep = sel.value;
    sel.innerHTML = `<option value="">${rlm_esc(allLabel)}</option>` + items.map(i => `<option value="${rlm_esc(valueOf(i))}">${rlm_esc(labelOf(i))}</option>`).join('');
    sel.value = [...sel.options].some(o => o.value === keep) ? keep : '';     // a choice that no longer exists falls back to "all"
}
async function rlm_loadFilterOptions(first) {
    const { data } = await rlm_request('filters', { central: '1' });
    rlm_fillSelect('rlm-f-fy', 'All Years', data.fiscal_years || [], y => y, y => y);
    rlm_fillSelect('rlm-f-fund', 'All Types of Fund', data.types_of_fund || [], f => f.type_of_fund_id, f => f.name);
    rlm_fillSelect('rlm-f-component', 'All Components', data.components || [], c => c.component_id, c => c.name);
    rlm_fillSelect('rlm-f-region', 'All Regions', data.regions || [], r => r.region_id, r => r.rc_name || r.region_id);
    rlm_fillSelect('rlm-f-program', 'All Programs', data.programs || [], p => p.program_code, p => p.program_name ? `${p.program_name} (${p.program_code})` : p.program_code);
    // the first load opens on the newest fiscal year that has approved data, not on every year at once
    rlm.defaultFiscalYr = (data.fiscal_years || [])[0] != null ? String(data.fiscal_years[0]) : '';
    if (first) $rlm('rlm-f-fy').value = rlm.defaultFiscalYr;
    rlm_readFilters();
}
function rlm_readFilters() {
    for (const [id, key] of Object.entries(RLM_FILTER_IDS)) rlm.filters[key] = $rlm(id).value;
    rlm.filters.search = $rlm('rlm-f-search').value.trim();
}
function rlm_onFilterChange() {
    clearTimeout(rlm.searchTimer);          // every filter change reads the search box too — a typing pause still waiting would only reload twice
    rlm_readFilters();
    rlm_reload();
}
function rlm_resetFilters() {
    for (const id of Object.keys(RLM_FILTER_IDS)) $rlm(id).value = '';
    $rlm('rlm-f-fy').value = rlm.defaultFiscalYr;
    $rlm('rlm-f-search').value = ''; $rlm('rlm-f-search-clear').hidden = true;
    rlm_onFilterChange();
}

/* ── Loading ───────────────────────────────────────────────── */
function rlm_reload() {
    rlm.loaded = '';
    return Promise.all([rlm_loadSummary(), rlm_loadTree()]);
}
async function rlm_refresh() {
    rlm_busy(true);
    try { await rlm_loadFilterOptions(false); } catch (e) { rlm_toast(e.message, 'error'); }
    finally { rlm_busy(false); }
    rlm_reload();
}
async function rlm_loadSummary() {
    const my = ++rlm.summarySeq;
    rlm_busy(true);                                  // the preloader covers the KPI cards too, not only the table
    try {
        const json = await rlm_request('summary', rlm_params());
        if (my !== rlm.summarySeq) return;
        rlm.summary = json.data;
        rlm_renderSummary();
    } catch (e) {
        if (my !== rlm.summarySeq) return;
        rlm.summary = null;
        rlm_renderSummary();
        rlm_toast(e.message, 'error');
    } finally {
        rlm_busy(false);
    }
}
async function rlm_loadTree() {
    const key = JSON.stringify(rlm_params());
    if (rlm.loaded === key) return;
    const my = ++rlm.seq;
    rlm_busy(true);
    try {
        const json = await rlm_request('rollup', rlm_params({ layout_by: RLM_LAYOUT, with_allocations: '1', split_coe: '1' }));
        if (my !== rlm.seq) return;
        rlm.data = rlm_regroup(json.data || []); rlm.totals = json.totals || null;
        if (json.allocation_error) rlm_toast(json.allocation_error, 'warning');
        rlm.loaded = key;
        rlm_openRoots();
        rlm_renderTree();
    } catch (e) {
        if (my !== rlm.seq) return;
        rlm.loaded = '';
        $rlm('rlm-tbody').innerHTML = `<tr><td colspan="7"><div class="nha-error">${rlm_esc(e.message)}</div></td></tr>`;
        rlm_toast(e.message, 'error');
    } finally {
        rlm_busy(false);
    }
}

/* ── KPI cards ─────────────────────────────────────────────── */
function rlm_renderSummary() {
    const s = rlm.summary;
    $rlm('rlm-kpi-requests').textContent = s ? s.requests : '—';
    $rlm('rlm-kpi-requests-sub').textContent = s ? `${s.programs} program${s.programs === 1 ? '' : 's'} · ${s.projects} project${s.projects === 1 ? '' : 's'}` : 'Matches current filters';
    $rlm('rlm-kpi-revised').textContent = s ? rlm_fmt(s.revised_computed) : '—';
    $rlm('rlm-kpi-rfalaa').textContent = s ? rlm_fmt(s.rfa_laa_amount) : '—';
    $rlm('rlm-kpi-disbursement').textContent = s ? rlm_fmt(s.disbursement) : '—';
    $rlm('rlm-kpi-unallocated').textContent = s ? rlm_fmt(s.unallocated_amount) : '—';
    $rlm('rlm-kpi-payable').textContent = s ? rlm_fmt(s.account_payable) : '—';
}

/* ── The tree ──────────────────────────────────────────────── */
const RLM_CARET = '<svg class="rlm-caret%OPEN%" viewBox="0 0 24 24" fill="none" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>';
const RLM_CLIP = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>';

// A date as "Nov 10, 2026" ('' when there is none)
function rlm_fmtDate(v) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v || ''));
    const d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(v);
    return !v || isNaN(d) ? '' : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}
// a status pill in the status's own colour (only a plain #RRGGBB goes into a style), else a grey one
function rlm_statusPill(name, color) {
    if (!name) return '';
    return /^#[0-9A-Fa-f]{6}$/.test(color || '')
        ? `<span class="nha-bdg" style="color:${color};background:${color}1A;border:1px solid ${color}40;">${rlm_esc(name)}</span>`
        : `<span class="nha-bdg nha-bdg-gray">${rlm_esc(name)}</span>`;
}

// the Project column's cell: the name, then what tells two of the same name apart. (There is no Code column: a project's code is not shown, an RFA / LAA row carries a badge.)
function rlm_nodeLabel(n) {
    if (n.type === 'bucket' || n.type === 'class') {
        // CO / COE (the two halves) and PS / MOOE (the classes of COE): the identifier as a badge, then the name
        // (CO / COE get a solid pill in their row's colour — .rlm-bkt-badge — PS / MOOE the quiet grey one)
        return `${n.code ? `<span class="${n.type === 'bucket' ? 'rlm-bkt-badge' : 'nha-bdg nha-bdg-gray nha-bdg-xs'}">${rlm_esc(n.code)}</span>` : ''}<span>${rlm_esc(n.label)}</span>`;
    }
    if (n.type === 'allocation') {
        // an RFA / LAA drawn against the project above it: a badge saying which, its number, then its description
        const desc = n.description && String(n.description).trim() ? `<span class="rlm-sub">${rlm_esc(String(n.description).trim())}</span>` : '';
        const kind = n.allocation_type === 'LAA' ? 'nha-bdg-teal' : 'nha-bdg-blue';
        // does it have a disbursement voucher connected (its dv_id)? a row that has one drops down to it when clicked
        const dvs = (n.children || []).filter(c => c.type === 'dv').length;
        const dvTag = dvs
            ? `<span class="nha-bdg nha-bdg-green nha-bdg-xs" title="Click the row to see its disbursement voucher">${dvs} DV</span>`
            : `<span class="nha-bdg nha-bdg-gray nha-bdg-xs" title="No disbursement voucher is connected to this ${rlm_esc(n.allocation_type || 'allocation')}">No DV</span>`;
        return `<span class="nha-bdg ${kind} nha-bdg-xs">${rlm_esc(n.allocation_type || n.code || '')}</span><span>${rlm_esc(n.label)}</span>${dvTag}${desc}`;
    }
    if (n.type === 'dv') {
        // the disbursement voucher of an RFA: its number and status, then its date and claimant
        const meta = [rlm_fmtDate(n.dv_date), n.claimant_name || '', n.counted === false ? 'not counted in Disbursement' : ''].filter(Boolean).map(rlm_esc).join(' · ');
        return `<span>${rlm_esc(n.label)}</span> ${rlm_statusPill(n.status_name, n.status_color)}${meta ? `<span class="rlm-sub">${meta}</span>` : ''}`;
    }
    let html = `<span>${n.unassigned ? `<span class="rlm-unassigned">${rlm_esc(n.label)}</span>` : rlm_esc(n.label)}</span>`;
    if (n.type === 'project' || n.type === 'request') {
        // line 1: the project name (a COE request: its reference number) and its supporting documents
        if (n.attachment_count > 0) html += ` <span class="rlm-clip" title="${n.attachment_count} supporting document${n.attachment_count === 1 ? '' : 's'}">${RLM_CLIP}${n.attachment_count}</span>`;
        // line 2 (muted): the fiscal year, then where the project sits — the levels this table no longer has rows for (program › sub-program › originating unit)
        const where = (n.trail || []).map(t => (t.type === 'program' || t.type === 'subprogram') && t.code ? `${t.code} — ${t.label}` : t.label).filter(Boolean).join(' › ');
        const fy = n.fiscal_yr ? `<span class="nha-bdg nha-bdg-gray nha-bdg-xs">FY ${rlm_esc(n.fiscal_yr)}</span>` : '';
        if (fy || where) html += `<span class="rlm-sub">${fy}${where ? `<span class="rlm-where">${rlm_esc(where)}</span>` : ''}</span>`;
    }
    return html;
}

function rlm_amountCells(o) {
    return RLM_COLS.map(k => `<td class="rlm-num">${rlm_num(o[k])}</td>`).join('');
}

function rlm_treeRow(n, depth, path, hasKids, open) {
    const view = (n.type === 'project' || n.type === 'request') && n.budget_reference_number
        ? `<button type="button" class="nha-btn-act view" data-view="${rlm_esc(n.budget_reference_number)}" data-project="${rlm_esc(n.code || '')}" title="View the approved request ${rlm_esc(n.budget_reference_number)}">View</button>`
        : '';
    // CO / COE: the row carries its own class (rlm-b-co / rlm-b-coe) so each half has its own colour
    const bucketCls = n.type === 'bucket' ? ' rlm-b-' + String(n.key).toLowerCase().replace(/[^a-z0-9]/g, '') : '';
    return `<tr class="rlm-row rlm-d${Math.min(depth, 4)} rlm-t-${rlm_esc(n.type)}${bucketCls}${hasKids ? ' rlm-has-kids' : ''}${open ? ' open' : ''}" data-path="${rlm_esc(path)}"${hasKids ? ` tabindex="0" role="button" aria-expanded="${open}"` : ''}>
        <td><div class="rlm-name" style="--d:${depth}">${hasKids ? RLM_CARET.replace('%OPEN%', open ? ' open' : '') : '<span class="rlm-caret-sp"></span>'}${rlm_nodeLabel(n)}</div></td>
        ${rlm_amountCells(n)}
        <td class="no-print">${view}</td></tr>`;
}

// The server answers Program > Sub-Program > Originating Unit > Project. This table starts at the project, so the projects are lifted out — in the tree's
// own order — each carrying the nodes it sat under (`trail`) for its muted "where" line. A project's children (its RFAs / LAAs) come along untouched.
// A COE request (the leaf of the COE half — it has no project) is lifted the same way.
function rlm_projectsOf(nodes, trail = []) {
    const out = [];
    for (const n of nodes) {
        if (n.type === 'project' || n.type === 'request') out.push({ ...n, trail });
        else out.push(...rlm_projectsOf(n.children || [], trail.concat(n)));
    }
    return out;
}
// With split_coe the server answers two roots: CO (Program > Sub-Program > Originating Unit > Project) and COE (PS / MOOE > Originating Unit > Request). Under each
// root the leaves are lifted out as above; in the COE half the PS / MOOE rows stay, each holding its requests (their office on the muted line).
function rlm_regroup(roots) {
    return roots.map(root => ({
        ...root,
        children: (root.children || []).flatMap(c => c.type === 'class' ? [{ ...c, children: rlm_projectsOf(c.children || []) }] : rlm_projectsOf([c])),
    }));
}
// The page opens with CO and COE unrolled, and PS / MOOE too (a COE request is one click below them) — each only the first time it is seen, so closing one
// is remembered across a refresh or a filter change.
function rlm_openRoots() {
    const walk = (nodes, parentPath) => nodes.forEach(n => {
        if (n.type !== 'bucket' && n.type !== 'class') return;
        const path = `${parentPath}/${n.type}:${n.key}`;
        if (!rlm.rootsSeen.has(path)) { rlm.rootsSeen.add(path); rlm.expanded.add(path); }
        walk(n.children || [], path);
    });
    walk(rlm.data || [], '');
}

// Only the rows that are visible are built: a collapsed node's children never reach the DOM.
function rlm_collect(nodes, depth, parentPath, out) {
    for (const n of nodes) {
        const path = `${parentPath}/${n.type}:${n.key}`;
        const hasKids = Array.isArray(n.children) && n.children.length > 0;
        const open = hasKids && rlm.expanded.has(path);
        out.push(rlm_treeRow(n, depth, path, hasKids, open));
        if (open) rlm_collect(n.children, depth + 1, path, out);
    }
}
function rlm_renderTree() {
    const nodes = rlm.data || [], totals = rlm.totals;
    const body = $rlm('rlm-tbody');
    if (!nodes.length) {
        body.innerHTML = `<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No approved budget found.</span>
            <div class="nha-empty-text">Only Approved budget requests are part of this monitoring. Try a different fiscal year or clear the filters.</div></div></td></tr>`;
        $rlm('rlm-info').textContent = 'No approved line items match the current filters';
        return;
    }
    const rows = [];
    rlm_collect(nodes, 0, '', rows);
    // the TOTAL row goes first, right under the headings, not after the last row
    if (totals) rows.unshift(`<tr class="rlm-total-row"><td>TOTAL</td>${rlm_amountCells(totals)}<td class="no-print"></td></tr>`);
    body.innerHTML = rows.join('');
    const n = totals ? totals.line_count : 0;
    $rlm('rlm-info').textContent = `${n} approved line item${n === 1 ? '' : 's'} — click a row to expand it`;
}
function rlm_toggle(path) {
    if (rlm.expanded.has(path)) rlm.expanded.delete(path); else rlm.expanded.add(path);
    rlm_renderTree();
    // the re-render replaced the row: put the keyboard focus back on it
    const row = [...$rlm('rlm-tbody').querySelectorAll('tr[data-path]')].find(r => r.dataset.path === path);
    if (row && document.activeElement === document.body) row.focus();
}
function rlm_expandAll(expand) {
    rlm.expanded.clear();
    if (expand) {
        const walk = (nodes, parentPath) => nodes.forEach(n => {
            const path = `${parentPath}/${n.type}:${n.key}`;
            if (Array.isArray(n.children) && n.children.length) { rlm.expanded.add(path); walk(n.children, path); }
        });
        walk(rlm.data || [], '');
    }
    rlm_renderTree();
}
</script>
