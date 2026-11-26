<?php

namespace PHPMaker2024\FIMS;

@session_start();
$_bgr_api = Config('API.FIMS_URL');

// User level 2 = Budget Officer: Add / Edit / Delete are hidden for this
// level, and the per-row "Budget Processing" action is shown only to this level.
// User level 8 = Budget Head: "Add Budget Request" is hidden, and only this
// level sees the "Budget Preview" action (whose modal has Return / Approve
// buttons on a "For Endorsement" request).
// User level -1 = System Administrator (superadmin): no restriction on the review chain — Budget Processing (the Budget Officer's
// step) and Budget Review with Return / Approve (the Budget Head's step) at EVERY step, whatever their office. Same people the API
// lets through (BudgetReqService::isSuperAdmin): the built-in administrator (user_id -1) or a level list that includes -1 ("-1,3").
// Hiding a button here is cosmetic — the API decides, and tells each row so in review.can_decide.
$userid = CurrentUserID();
$userLevel = CurrentUserLevel();
$_bgr_isAdmin = (int) $userid === -1
    || in_array(-1, array_map('intval', explode(',', (string) $userLevel)), true);

// The user's Responsibility Center: the current user id -> uac.users (only its rc_id is used) ->
// joined to fims.responsibility_code for that RC. The Financial Management Department (rc_code
// 35-074-0000000-032, "FMD") reviews every Regional / District office's request, so its users get a
// different list: one row per request (Submission Ref No., Region / Originating Unit, Date Submitted,
// Amount, Status) over every office. Everyone else keeps the flat one-row-per-Program list.
// FMD is recognised by its rc_code, NOT its rc_id (the id's format differs between datasets: "013" vs
// "RC-013"). Mirrors GlobalService::CENTRAL_REVIEW_RC_CODE, which the API checks again server-side.
$_bgr_fmdRcCode = '35-074-0000000-032';
$_bgr_fmdRcCodeFrom = '35-074-0000001-000';   // the Regional / District offices: rc_code from here to the end
$_bgr_userRc = null;
try {
    $_bgr_userRc = ExecuteRow(
        'SELECT rc.rc_id, rc.rc_code, rc.rc_name, rc.office_type FROM uac.users u '
        .'JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id WHERE u.user_id = '.(int) $userid,
        'DB'
    ) ?: null;
} catch (\Throwable $e) {
    $_bgr_userRc = null;   // can't tell who this is -> the flat list, never an error page
    LogError('budget-req-page: could not look up the current user\'s RC — '.$e->getMessage());
}
$_bgr_isFmd = $_bgr_userRc !== null && trim((string) ($_bgr_userRc['rc_code'] ?? '')) === $_bgr_fmdRcCode;
$_bgr_isBudgetOfficer = ((int) $userLevel === 2);
$_bgr_isBudgetHead = ((int) $userLevel === 8);
// who is offered the "Budget Review" action: the Budget Head, and the superadmin (who may decide at the Budget Head's step too)
$_bgr_canPreview = $_bgr_isBudgetHead || $_bgr_isAdmin;
$_bgr_canAdd = !$_bgr_isBudgetOfficer && !$_bgr_isBudgetHead && !$_bgr_isFmd;
// "Add Budget Request" (Program/Sub-Program/Project) — the Regional/District office now files COE only (no
// Program/Project of its own); Program-based origination belongs to FMD (or a superadmin). The API enforces
// this too (POST /budget_req/add, PUT /budget_req/program/update — GlobalService::isFmdUser/isSuperAdminUser),
// not just this hide.
$_bgr_canAddProgram = $_bgr_isFmd || $_bgr_isAdmin;
// The Regional/District (originating) office's own table shows COE rows only — Program/Sub-Program/Project
// origination is now FMD's (or a superadmin's); even a Program row left over from before this rule would
// just invite an Edit/Submit action that no longer belongs to this office. Budget Officer / Budget Head still
// see every office's Program AND COE rows in their own (non-FMD) flat list — they review both kinds — and
// FMD / a superadmin keep their own tables' full picture; only a plain originating-office user is narrowed.
$_bgr_isOriginatingOffice = $_bgr_canAdd && !$_bgr_isAdmin;
$_bgr_canModify = !$_bgr_isBudgetOfficer;
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?php echo @filemtime($_SERVER['DOCUMENT_ROOT'].'/_shared/css/nha-fims.css'); ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .bgr-summary-2col { grid-template-columns: repeat(2, 1fr); }
}
.nha-root #bgr-submit-review:disabled { opacity:.45;cursor:not-allowed; }
.nha-root #bgr-add-coe:disabled { opacity:.45;cursor:not-allowed; }

/* ── Actions dropdown menu ──────────────────────────────────── */
.nha-tools-menu { position:relative;display:inline-block; }
.nha-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;
    font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--afims-border, #E4ECF2);
    background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;
    font-family:'IBM Plex Sans',sans-serif;transition:all .15s; }
.nha-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA);border-color:var(--afims-text-disabled, #A9AAB8); }
.nha-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.nha-tools-dropdown { display:none;position:fixed;min-width:170px;background:var(--afims-surface, #FFFFFF);
    border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);
    z-index:9999;padding:4px 0; }
.nha-tools-dropdown.open { display:block; }
.nha-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;
    color:var(--afims-text, #1A1B21);text-decoration:none;white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;transition:background .1s; }
.nha-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.nha-menu-item.danger:hover { background:#fdecec;color:#c0392b; }
.nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
.nha-menu-divider { border-top:1px solid var(--afims-border, #E4ECF2);margin:3px 0; }

/* ── Page header: copied from User Access (.app-page-header in app/fims-css/fims.css — that file is not loaded here,
      so the rules are repeated, scoped to this page). Stays navy in dark mode too, like User Access. ── */
#bgr-root .app-page-header { position:relative;display:flex;align-items:center;gap:1rem;flex-wrap:wrap;overflow:hidden;
    background:linear-gradient(135deg, #000E3E 0%, #001A5E 100%);border-radius:.75rem;padding:1.5rem 1.75rem;margin-bottom:1.5rem; }
#bgr-root .app-page-header::before,
#bgr-root .app-page-header::after { content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.05);pointer-events:none; }
#bgr-root .app-page-header::before { width:180px;height:180px;top:-90px;right:80px; }
#bgr-root .app-page-header::after { width:120px;height:120px;bottom:-70px;right:-20px;background:rgba(255,255,255,.06); }
#bgr-root .app-page-header-icon { position:relative;z-index:1;flex:0 0 auto;width:3rem;height:3rem;border-radius:.5rem;
    display:flex;align-items:center;justify-content:center;font-size:1.25rem;
    background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#FFFFFF; }
#bgr-root .app-page-title { position:relative;z-index:1;font-size:1.6rem;font-weight:800;letter-spacing:-.01em;color:#FFFFFF;margin:0 0 .25rem; }
#bgr-root .app-page-subtitle { position:relative;z-index:1;font-size:.9rem;color:rgba(255,255,255,.75);margin:0;max-width:640px; }
/* the Refresh / Add buttons stay on the right of the banner, above its decorative circles */
#bgr-root .app-page-header-actions { position:relative;z-index:1;margin-left:auto;display:flex;flex-wrap:wrap;gap:8px; }

/* ── Approved BUR tab: the chart-of-accounts tree (same shape as bur-page.php) ── */
#bgr-root #bgrbur-table { table-layout: fixed; }
#bgr-root #bgrbur-table .bgrbur-name span { overflow-wrap: break-word; }
#bgr-root .bgrbur-row-total > td { font-weight: 700; background: var(--afims-surface-subtle, #F4F7FA); border-top: 2px solid var(--afims-border, #E4ECF2); }
#bgr-root .bgrbur-name { display: flex; align-items: center; gap: 8px; }
#bgr-root .bgrbur-toggle {
    width: 20px; height: 20px; flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    border: none; background: none; cursor: pointer; padding: 0; border-radius: 4px;
    color: var(--afims-text-muted, #62657E); transition: background .15s, color .15s;
}
#bgr-root .bgrbur-toggle:hover { background: var(--afims-border, #E4ECF2); color: var(--afims-heading, #000E3E); }
#bgr-root .bgrbur-toggle svg { pointer-events: none; transition: transform .15s; }
#bgr-root .bgrbur-toggle-spacer { display: inline-block; width: 20px; flex-shrink: 0; }
#bgr-root .bgrbur-d0 > td { background: var(--afims-surface-subtle, #F4F7FA); font-weight: 700; }
#bgr-root .bgrbur-neg { color: #dc2626; }
</style>

<div id="bgr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Budget Requirements</h1>
            <p class="app-page-subtitle">Prepare budget requests by program, project and line item, then send them for review.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="bgr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <?php if ($_bgr_canAddProgram) { ?>
            <button id="bgr-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a budget request">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add CO Budget Request
            </button>
            <?php } ?>
            <?php if ($_bgr_canAdd) { ?>
            <button id="bgr-add-coe" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a COE budget request — no Program/Project, just PS/MOOE chart-of-accounts line items">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add COE Budget Request
            </button>
            <?php } ?>
        </div>
    </div>

    <?php /* The Approved BUR tab is for the Regional / District (originating) office only — the same
             group that gets "Add Budget Request" ($_bgr_canAdd: not Budget Officer, not Budget Head,
             not FMD). FMD already has its own org-wide "View Proposal"; Budget Officer / Budget Head
             act on requests, they don't file one, so a per-office Approved breakdown isn't theirs either. */ ?>
    <?php if ($_bgr_canAdd) { ?>
    <!-- ── TABS: the request list, vs. the Approved-only chart-of-accounts breakdown (BUR),
         scoped to the viewer's own RC the same way bur-page.php is (GET /bur/filters, /bur/rollup —
         GlobalService::getUserRcScope on the server). ── -->
    <div class="nha-tabs no-print" id="bgr-tabs" role="tablist">
        <button type="button" class="nha-tab active" data-tab="requests" role="tab">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/></svg>
            Budget Requests
        </button>
        <button type="button" class="nha-tab" data-tab="bur" role="tab">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Approved COE
        </button>
    </div>
    <?php } ?>

    <div class="nha-panel active" id="bgr-panel-requests">

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary bgr-summary-2col">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Requests</div>
                    <div class="nha-sc-val" id="bgr-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Amount</div>
                    <div class="nha-sc-val" id="bgr-kpi-amount">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="bgr-filter-fiscal-yr" class="nha-filter-select">
                    <option value="">All Years</option>
                    <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Program</label>
                <select id="bgr-filter-program" class="nha-filter-select">
                    <option value="">All</option>
                </select>
            </div>
            <?php if ($_bgr_isFmd) { /* central reviewer: browse every office's proposal, scoped server-side by /fmd/budget_req/list — no per-office Submit/Remarks/Add toolbar */ ?>
            <div class="nha-filter-group-actions">
                <button type="button" id="bgr-view-cob" class="nha-btn nha-btn-outline" onclick="bgr_viewAsCob()" title="Browse every Program, Sub-Program and Project">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    View Proposal
                </button>
            </div>
            <?php } else { /* each Regional / District office submits its own request; the central reviewers don't */ ?>
            <div class="nha-filter-group-actions">
                <button type="button" id="bgr-view-cob" class="nha-btn nha-btn-outline" onclick="bgrv_openCobModal($bgr('bgr-filter-fiscal-yr').value)" title="Browse every Program, Sub-Program and Project">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    View Proposal
                </button>
                <button type="button" id="bgr-view-remarks" class="nha-btn nha-btn-outline" hidden>
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                    View Remarks
                </button>
                <button type="button" id="bgr-submit-review" class="nha-btn nha-btn-success" disabled title="Filter by a single Draft fiscal year to submit its request">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Submit for Review
                </button>
            </div>
            <?php } ?>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <?php if ($_bgr_isFmd) { /* FMD: one row per request, over every Regional / District office */ ?>
            <table class="nha-tbl">
                <thead id="bgr-thead">
                    <tr>
                        <th>Submission Ref No.</th>
                        <th>Region / Originating Unit</th>
                        <th>Date Submitted</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bgr-tbody">
                    <tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
            <?php } else { ?>
            <table class="nha-tbl">
                <thead id="bgr-thead">
                    <tr>
                        <th>#</th>
                        <th>Fiscal Year</th>
                        <th>Program</th>
                        <th>Sub-Program</th>
                        <th># Projects</th>
                        <th># Line Items</th>
                        <th>Proposed Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bgr-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
            <?php } ?>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="bgr-from">0</span>–<span id="bgr-to">0</span></strong> of <strong><span id="bgr-total">0</span></strong></span>
            <div id="bgr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    </div><!-- /#bgr-panel-requests -->

    <?php if ($_bgr_canAdd) { /* Regional / District only — see the tab bar's own comment above */ ?>
    <!-- ── APPROVED BUR PANEL ───────────────────────────────
         Same tree the standalone Budget Utilization Report (bur-page.php) shows — Personal Services (5-01) /
         Maintenance and Other Operating Expenses (5-02), rolled up from every APPROVED budget request's line
         items, scoped to the viewer's own RC. Reuses its API (GET /bur/filters, /bur/rollup) unchanged; the
         JS below (prefix bgrbur_) is its own copy of the tree-building logic (bur_buildTree / bur_flattenTree
         in bur-page.php), not shared — this tab is a different page, not an iframe of that one. ── -->
    <div class="nha-panel" id="bgr-panel-bur">
        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label" for="bgrbur-f-fy">Fiscal Year</label>
                    <select id="bgrbur-f-fy" class="nha-filter-select"><option value="">All Years</option></select>
                </div>
                <div class="nha-filter-group" style="margin-left:auto;">
                    <label class="nha-filter-label">&nbsp;</label>
                    <span style="display:flex;gap:6px;">
                        <button id="bgrbur-expand-all" type="button" class="nha-btn nha-btn-outline nha-btn-sm">Expand All</button>
                        <button id="bgrbur-collapse-all" type="button" class="nha-btn nha-btn-outline nha-btn-sm">Collapse All</button>
                    </span>
                </div>
            </div>
        </div>

        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl" id="bgrbur-table">
                    <thead>
                        <tr>
                            <th style="width:280px;">Expense Classification (NGAS)</th>
                            <th style="width:110px;">Acct. Code</th>
                            <th class="text-end">Approved Budget</th>
                            <th class="text-end">Realigned Budget</th>
                            <th class="text-end">Increase (Decrease)</th>
                            <th class="text-end">Utilization</th>
                            <th class="text-end">Balance</th>
                            <th class="text-end">Payment</th>
                            <th class="text-end">Accounts Payable</th>
                        </tr>
                    </thead>
                    <tbody id="bgrbur-tbody">
                        <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div><!-- /#bgr-panel-bur -->
    <?php } ?>

<?php include __DIR__.'/readone/budget-req-readone.php'; ?>
<?php include __DIR__.'/form/budget-req-form.php'; ?>
<?php include __DIR__.'/form/budget-processing-form.php'; ?>
<?php include __DIR__.'/readone/budget-preview-readone.php'; ?>

</div><!-- /#bgr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REQUIREMENTS LIST   prefix: bgr_
   Modal-support functions (bgr_openModal, bgr_save, loaders, etc.) live
   in form/budget-req-form.php; the Program "View" modal (bgrv_*) lives in
   readone/budget-req-readone.php — both included above. This script
   fetches budget requests (each with nested programs[]/projects[]/
   line_items[]) and flattens them to one table row per Program (a
   fiscal year with no programs yet still gets one placeholder row).
═══════════════════════════════════════════════════════════════ */

let bgr_page = 1;
const BGR_PAGE_SIZE = 15;
// false for user level 2 (Budget Officer) — hides the row Edit / Delete actions.
const BGR_CAN_MODIFY = <?php echo $_bgr_canModify ? 'true' : 'false'; ?>;
// true only for user level 8 (Budget Head) — shows the row "Budget Preview" action (Return / Approve inside its modal
// additionally need the row's review.can_decide, see bgr_budgetPreview()). "Budget Processing" has no flag of its own:
// it is driven by the row's review (see bgr_actionButtons()).
const BGR_IS_BUDGET_HEAD = <?php echo $_bgr_isBudgetHead ? 'true' : 'false'; ?>;
// true for a superadmin (user level -1): no restriction on the review chain (see the PHP at the top)
const BGR_IS_ADMIN = <?php echo $_bgr_isAdmin ? 'true' : 'false'; ?>;
// who is offered the row "Budget Review" action: the Budget Head, or a superadmin
const BGR_CAN_PREVIEW = <?php echo $_bgr_canPreview ? 'true' : 'false'; ?>;
// true for a plain Regional/District (originating) office user — not Budget Officer/Head, not FMD, not a
// superadmin. Its own table shows COE rows only (see bgr_flattenRows): Program-based requests are no longer
// theirs to file (see $_bgr_isOriginatingOffice's own comment in the PHP at the top of this file).
const BGR_IS_ORIGINATING_OFFICE = <?php echo $_bgr_isOriginatingOffice ? 'true' : 'false'; ?>;
let bgr_flatRows = [];
// true for the Financial Management Department (FMD): the list is one row per request over every
// Regional / District office (see the PHP at the top of this file and the FMD list section below).
const BGR_FMD = <?php echo $_bgr_isFmd ? 'true' : 'false'; ?>;
const BGR_FMD_RC_FROM = <?php echo json_encode($_bgr_fmdRcCodeFrom); ?>;
// fims.responsibility_code.office_type of the logged-in user's own RC — same source "Export to Excel" /
// "Preview and Print" (readone/budget-req-readone.php, bgrv_openCobModal) reads via bgrv_rcDeptType() to
// decide whether a Regional / District user skips straight to "Per Program" instead of being offered all
// four layouts.
const BGR_USER_OFFICE_TYPE = <?php echo json_encode((string) ($_bgr_userRc['office_type'] ?? '')); ?>;
// FMD only — its own dedicated route (app/routes/fmd_budget_req.php): same request shape as
// BGR_API/list, but the scope (every Regional / District office) is decided server-side, so this
// page sends it no RC parameters at all (unlike BGR_API/list's own central/rc_code_from, used
// nowhere once BGR_FMD is true).
const BGR_LIST_API = '<?php echo $_bgr_api; ?>' + '/fmd/budget_req/list';

// FMD only — "View Proposal" toolbar button: every budget request for the Fiscal Year filter's
// current value ('' = every year), one block per ORIGINATING UNIT (byUnit), each Project showing
// its Type(s) of Fund (showFund). central=1 + rc_code_from widen GET /budget_req/all (used by the
// modal itself, readone/budget-req-readone.php) from the caller's own RC to every such office.
function bgr_viewAsCob() {
    bgrv_openCobModal($bgr('bgr-filter-fiscal-yr')?.value || '', { central: '1', rc_code_from: BGR_FMD_RC_FROM }, { byUnit: true, showFund: true });
}

window.bgr_afterSave = () => bgr_load();

async function bgr_bootstrapLoad() {
    await bgr_load();
    bgr_bindListEvents();
    bgr_openFromLink();
}

/* ── Opened from a notification ─────────────────────────────────
   When a budget revision / realignment is approved, every user of the originating office is notified (BudgetRevisionService); the
   notification's link is BudgetReqPage?open=cob&fy=<year>. Arriving with it, this sets the Fiscal Year filter to that year (the list
   behind follows it) and then presses "View as COB" exactly as a click would — so the user lands on the revised budget. The button only
   exists for the originating offices (not on the FMD's list), so nobody else gets a modal. The parameters are removed from the address
   afterwards, so a refresh or the Back button does not open it again.
   The parameter is "open", not "view": "view" is reserved by PHPMaker (Config("VIEW")) — the page class reads it as a template name, so
   ?view=cob failed with 'View cannot render "cob.php"' before this page even loaded. */
async function bgr_openFromLink() {
    const q = new URLSearchParams(location.search);
    if (q.get('open') !== 'cob') return;
    const fy = (q.get('fy') || '').trim();
    try {
        const u = new URL(location.href);
        u.searchParams.delete('open');
        u.searchParams.delete('fy');
        history.replaceState(null, '', u);
    } catch (e) { /* cosmetic */ }

    try { await window.bgrFormReady; } catch (e) { /* the form reports its own load errors */ }   // the year dropdown is filled by then

    const sel = $bgr('bgr-filter-fiscal-yr');
    if (sel && /^\d{4}$/.test(fy)) {
        if (![...sel.options].some(o => o.value === fy)) sel.insertAdjacentHTML('beforeend', `<option value="${fy}">${fy}</option>`);
        if (sel.value !== fy) {
            sel.value = fy;
            bgr_page = 1;
            await bgr_load();
        }
    }
    $bgr('bgr-view-cob')?.click();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_bootstrapLoad);
} else {
    bgr_bootstrapLoad();
}

function bgr_bindListEvents() {
    $bgr('bgr-refresh')?.addEventListener('click', () => { bgr_page = 1; bgr_load(); });
    $bgr('bgr-add')?.addEventListener('click', () => bgr_openModal('add'));
    $bgr('bgr-add-coe')?.addEventListener('click', () => bgr_openModal('add-coe'));

    ['bgr-filter-fiscal-yr', 'bgr-filter-program'].forEach(id => {
        $bgr(id)?.addEventListener('change', () => { bgr_page = 1; bgr_load(); });
    });

    // Tabs: the request list vs. the Approved BUR breakdown (see the section near the end of this script)
    $bgr('bgr-tabs')?.addEventListener('click', e => { const b = e.target.closest('.nha-tab'); if (b) bgr_switchTab(b.dataset.tab); });
    $bgr('bgrbur-f-fy')?.addEventListener('change', e => { bgrbur_fiscalYr = e.target.value; bgrbur_load(); });
    $bgr('bgrbur-expand-all')?.addEventListener('click', bgrbur_expandAll);
    $bgr('bgrbur-collapse-all')?.addEventListener('click', bgrbur_collapseAll);
}

/* ── Load ──────────────────────────────────────────────────── */
async function bgr_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            fiscal_yr   : $bgr('bgr-filter-fiscal-yr')?.value || '',
            program_code: $bgr('bgr-filter-program')?.value || '',
        });
        params.set('page', bgr_page);
        params.set('limit', BGR_PAGE_SIZE);

        // FMD: its own dedicated route, scoped server-side — no central/rc_code_from params (see BGR_LIST_API above).
        const url = BGR_FMD ? `${BGR_LIST_API}?${params}` : `${BGR_API}/list?${params}`;
        const res  = await fetch(url, { credentials: 'same-origin' });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'HTTP ' + res.status);
        bgr_renderTable(json.data ?? []);
        if (!BGR_FMD) {
            bgr_updateSubmitButton(json.data ?? []);
            bgr_updateRemarksButton(json.data ?? []);
            bgr_updateAddCoeButton(json.data ?? []);
        }

        bgr_renderPagination(json.pagination ?? {});
        $bgr('bgr-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $bgr('bgr-kpi-amount').textContent = bgr_fmtMoney(json.total_amount ?? 0);
    } catch (e) {
        bgr_renderTable([]);
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ══════════════════════════════════════════════════════════════
   Financial Management Department (FMD) list
   One row per budget request — i.e. per requesting office (budget_req.rc_id) and fiscal year — over
   every Regional / District office:
     Submission Ref No.        budget_reference_number (the fiscal year sits under it)
     Region / Originating Unit the requesting office, and the Region it belongs to (its parent RC)
     Date Submitted            budget_req.submitted_at — the date of the request's latest save / submission (stamped on
                               create, on adding / editing a Program, and on submit for review; "—" only for older requests)
     Amount                    the sum of the request's line items' proposed amounts (li_total)
     Status                    the request's status
   The row actions (Delete / Budget Processing / Budget Preview) act on that whole request. They use the
   same bgr_flatRows as the flat list. The Program-level View / Edit are not offered here (see
   bgr_actionButtons()).
══════════════════════════════════════════════════════════════ */

// Postgres timestamps arrive as "2026-11-10 08:30:00.123456+08": make that ISO enough for every browser's
// Date(), then show it as "Nov 10, 2026". '' when there is no date.
function bgr_fmtDate(value) {
    if (!value) return '';
    const iso = String(value).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d = new Date(iso);
    return isNaN(d) ? String(value) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

/* ── FMD table — one row per request ──────────────────────────────── */
function bgr_renderFmdTable(headers) {
    const tbody = $bgr('bgr-tbody');
    // `program` only tells the row actions whether the request has any Programs yet; the Budget Processing /
    // Budget Preview modals are both handed the request's real Programs (header.programs).
    bgr_flatRows = headers.map(header => ({ header, program: (header.programs ?? [])[0] ?? null }));

    if (!bgr_flatRows.length) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget requests found.</span></div></td></tr>';
        return;
    }

    tbody.innerHTML = bgr_flatRows.map((flatRow, idx) => {
        const h = flatRow.header;
        const submitted = bgr_fmtDate(h.submitted_at);
        return `<tr>
            <td>
                <code class="nha-code">${bgr_esc(h.budget_reference_number)}</code>
                <div class="nha-td-sub">FY ${bgr_esc(h.fiscal_yr)}</div>
            </td>
            <td>
                <div class="nha-td-bold">${bgr_esc(h.rc_name || h.rc_id || '—')}${h.rc_code ? ` <code class="nha-code">${bgr_esc(h.rc_code)}</code>` : ''}</div>
                ${h.rc_parent_name ? `<div class="nha-td-sub">${bgr_esc(h.rc_parent_name)}</div>` : ''}
            </td>
            <td>${submitted ? `<span class="nha-td-date">${bgr_esc(submitted)}</span>` : '<span class="nha-td-sub">—</span>'}</td>
            <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(h.li_total)}</div></td>
            <td>${bgr_statusBadge(h.status_name, h.status_color)}</td>
            <td>${bgr_actionButtons(flatRow, idx)}</td>
        </tr>`;
    }).join('');
}

/* ── Flatten: one table row per Program, PLUS one extra row for the header's own headless
   COE line items (header.coe_line_items) if it has any — a header can hold Programs, COE
   line items, or both at once (they share the one budget_req row per fiscal year/office; see
   BudgetReqService::addCoeRequest()). Falls back to one placeholder row only when a header
   has neither yet. ──────────────────────────────────────────────────────────────────── */
function bgr_flattenRows(headers) {
    const rows = [];
    headers.forEach(header => {
        const coeLineItems = header.coe_line_items ?? [];
        // An originating office's own table is COE rows only — a Program row (even one left over from
        // before Program-based filing moved to FMD) is skipped here rather than shown with no action that
        // still applies to it. Everyone else sharing this flat list (Budget Officer / Budget Head) keeps
        // seeing every Program too, same as before.
        const programs = BGR_IS_ORIGINATING_OFFICE ? [] : (header.programs ?? []);
        if (programs.length) {
            programs.forEach(program => rows.push({ header, program }));
        } else if (!coeLineItems.length && !BGR_IS_ORIGINATING_OFFICE) {
            rows.push({ header, program: null });
        }
        if (coeLineItems.length) {
            rows.push({ header, program: null, isCoe: true });
        }
    });
    return rows;
}

function bgr_programTotals(program) {
    const projects = program.projects ?? [];
    let lineItemCount = 0;
    let amount = 0;
    projects.forEach(project => {
        const lineItems = project.line_items ?? [];
        lineItemCount += lineItems.length;
        lineItems.forEach(li => { amount += Number(li.proposed_amount || 0); });
    });
    return { projectCount: projects.length, lineItemCount, amount };
}

// COE row only — header.coe_line_items is flat already (no Project to count).
function bgr_coeTotals(header) {
    const lineItems = header.coe_line_items ?? [];
    const amount = lineItems.reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0);
    return { projectCount: 0, lineItemCount: lineItems.length, amount };
}

function bgr_actionButtons(flatRow, idx) {
    if (BGR_FMD) return bgr_fmdActionButtons(flatRow, idx);

    const { header, program, isCoe } = flatRow;
    const isDraft = header.status_name === 'Draft';
    // A request the review sent back to the originating office ("Returned" — NOT "Returned to Budget Officer", which is with
    // the Budget Officer) can be edited again, like a Draft (BudgetReqService::updateProgram() allows both).
    const isReturned = header.status_name === 'Returned';

    // View and Edit work on ONE Program (or, for a COE row, the whole flat request — it has no
    // Program to split into several rows in the first place, see bgr_flattenRows). FMD rows never
    // reach this function (see the early return above) — they get bgr_viewRequest instead, scoped
    // to the whole request, since a row there IS the whole request.
    const viewItem = (program || isCoe)
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="${isCoe ? `bgr_viewCoe(${idx})` : `bgr_viewProgram(${idx})`};bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            View
        </a>`
        : '';
    const editItem = (BGR_CAN_MODIFY && (program || isCoe) && (isDraft || isReturned))
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="${isCoe ? `bgr_editCoe(${idx})` : `bgr_editProgram(${idx})`};bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Edit
        </a>`
        : '';
    // Step 1 of the review chain (BudgetReqService::REVIEW_STEPS): only when the request is waiting for the Budget Officer
    // ("For Review", or "Returned to Budget Officer" after the Budget Head sent it back) AND this user is that Budget Officer (or a
    // superadmin) — the row's review says so (review.can_decide, decided on the server). Once it is "For Endorsement" it is the
    // Budget Head's turn. (A CO row never shows this at all — submitForReview() sends it straight to "For Endorsement".)
    const budgetProcessingItem = (header.review?.can_decide && header.review.actor === 'Budget Officer')
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_budgetProcessing(${idx});bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            Budget Processing
        </a>`
        : '';
    // Read-only counterpart of Budget Processing — every line item with its
    // Type of Fund, Suggested Amount and Component, nothing to fill in.
    // Budget Head (level 8) and superadmin (level -1) only. The Return / Approve buttons inside need the row's review.can_decide on
    // the Budget Head's step (see bgr_budgetPreview()).
    const budgetPreviewItem = (BGR_CAN_PREVIEW && (program || isCoe))
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_budgetPreview(${idx});bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Budget Review
        </a>`
        : '';
    // A flat-list Program row's Delete removes just that Program (bgr_deleteProgram); the
    // header's dedicated COE row removes just its headless line items (bgr_deleteCoe) — either
    // way, if that leaves the header with nothing at all, the server removes the header too (see
    // deleteProgram()/deleteCoeLineItems()). A row with no Program and not COE either (the "no
    // programs yet" placeholder) removes the whole request (bgr_delete).
    const deleteItem = (BGR_CAN_MODIFY && isDraft)
        ? (isCoe
            ? `<a class="nha-menu-item danger" href="javascript:void(0);" onclick="bgr_closeMenus();bgr_deleteCoe(${idx});">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </a>`
            : (program
                ? `<a class="nha-menu-item danger" href="javascript:void(0);" onclick="bgr_closeMenus();bgr_deleteProgram(${idx});">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete
                </a>`
                : `<a class="nha-menu-item danger" href="javascript:void(0);" onclick="bgr_closeMenus();bgr_delete('${header.budget_reference_number}', ${header.fiscal_yr});">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Delete
                </a>`))
        : '';

    const items = [viewItem, editItem, budgetProcessingItem, budgetPreviewItem].filter(Boolean).join('');
    const deleteSection = deleteItem ? `<div class="nha-menu-divider"></div>${deleteItem}` : '';

    if (!items && !deleteSection) return '';

    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="bgr_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">${items}${deleteSection}</div>
    </div>`;
}

/* ── FMD row actions — every action acts on the WHOLE request (there is no single Program to
   scope to in this list), unlike the flat list's bgr_actionButtons above. ──────────────────── */
function bgr_fmdActionButtons(flatRow, idx) {
    const { header, program } = flatRow;
    const isDraft = header.status_name === 'Draft';

    // Budget Head (level 8) on an Approved request: nothing left to DECIDE, so Budget Review drops off
    // (headIsDone below) — but View stays, so they can still look at what they approved.
    const headIsDone = BGR_IS_BUDGET_HEAD && header.status_name === 'Approved';

    // The request's details, read-only: every Program, Project and line item. Offered on every request,
    // whatever its status or who is looking.
    const viewItem = `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_viewRequest(${idx});bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            View
        </a>`;

    // Moves Draft/Returned -> For Endorsement (BudgetReqService::submitForReview()), which is what
    // actually unlocks Budget Processing below for a COE row (review.can_decide only turns true once
    // the status is one of REVIEW_STEPS — Draft isn't). CO ONLY: a COE row belongs to the originating
    // region, which submits it themselves from their OWN (non-FMD) page via bgr_updateSubmitButton's
    // toolbar button — FMD submitting on a region's behalf here would bypass that region entirely. A CO
    // row has no such owner but the Budget Officer who created it, so this is the only place it can be
    // submitted from; isCo below is the same Programs-vs-coe_line_items test submitForReview() itself
    // uses server-side. Gated the same way "+ Add Budget Request" is ($_bgr_canAddProgram = isFmd ||
    // isAdmin, same FMD-or-superadmin population already used at bgr_programTotals'/bgr_coeTotals' own
    // call sites below) — NOT BGR_CAN_MODIFY, which is specifically false for the Budget Officer (see
    // its own comment) and would hide this from exactly the user who created the Draft in the first place.
    const isCo = !!(header.programs ?? []).length;
    const submitForReviewItem = ((BGR_FMD || BGR_IS_ADMIN) && isCo && (isDraft || header.status_name === 'Returned'))
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_submitForReview('${header.budget_reference_number}');bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            Submit for Review
        </a>`
        : '';
    // Step 1 of the review chain: only when the request is waiting for the Budget Officer AND this
    // user is that Budget Officer (review.can_decide, decided on the server).
    const budgetProcessingItem = (header.review?.can_decide && header.review.actor === 'Budget Officer')
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_budgetProcessing(${idx});bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            Budget Processing
        </a>`
        : '';
    // Read-only counterpart of Budget Processing. Budget Head (level 8) only, hidden once the
    // request is Approved (headIsDone): nothing left to decide.
    const budgetPreviewItem = (BGR_IS_BUDGET_HEAD && (program || (header.coe_line_items ?? []).length) && !headIsDone)
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_budgetPreview(${idx});bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Budget Review
        </a>`
        : '';
    const deleteItem = (BGR_CAN_MODIFY && isDraft)
        ? `<a class="nha-menu-item danger" href="javascript:void(0);" onclick="bgr_closeMenus();bgr_delete('${header.budget_reference_number}', ${header.fiscal_yr});">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete
        </a>`
        : '';

    const items = [viewItem, submitForReviewItem, budgetProcessingItem, budgetPreviewItem].filter(Boolean).join('');
    const deleteSection = deleteItem ? `<div class="nha-menu-divider"></div>${deleteItem}` : '';

    if (!items && !deleteSection) return '';

    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="bgr_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">${items}${deleteSection}</div>
    </div>`;
}

// FMD row's "View" — the request's details, read-only: every Program, Project, line item AND the
// header's own headless COE line items (if any), never scoped to a single Program (unlike the flat
// list's bgr_viewProgram), since an FMD row IS the whole request.
function bgr_viewRequest(idx) {
    const { header } = bgr_flatRows[idx];
    bpv_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        rc_id: header.rc_id,
        rc_code: header.rc_code,
        rc_name: header.rc_name,
        can_decide: false,
    }, header.programs ?? [], header.coe_line_items ?? []);
}

/* ── Actions dropdown — open/close (closes others, positions via fixed
   coords so the table's own overflow never clips it) ─────────────── */
function bgr_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    bgr_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.style.top = (rect.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
    drop.classList.add('open');
}

function bgr_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) bgr_closeMenus();
});
document.addEventListener('scroll', bgr_closeMenus, true);

function bgr_viewProgram(idx) {
    const { header, program } = bgr_flatRows[idx];
    if (!program) return;
    bgrv_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        is_draft: header.status_name === 'Draft',
    }, program);
}

// COE row's "View" — read-only, so this reuses the Budget Preview modal (same flat
// line-items table bpv_openModal already renders for a COE header) just without the
// Return/Approve buttons (can_decide: false), the same way the regular flat list's
// "View" (Program-shaped) is distinct from Budget Head's "Budget Preview" action.
function bgr_viewCoe(idx) {
    const { header, isCoe } = bgr_flatRows[idx];
    if (!isCoe) return;
    bpv_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        rc_id: header.rc_id,
        rc_code: header.rc_code,
        rc_name: header.rc_name,
        can_decide: false,
        review: header.review,
    }, [], header.coe_line_items ?? []);
}

/* "Budget Processing" row action (level 2 / Budget Officer only) — opens the
   Budget Processing modal (form/budget-processing-form.php, bpr_*): one card per Program,
   a section per Project in it with its line-item table (Type of Fund, Account Code,
   Account Description, Amount, Suggested Amount), a Subtotal per Project and a Grand Total.
   The header's own COE line items (header.coe_line_items), if it has any, ALWAYS come along
   too — as their own sibling card (bpr_coeContainerHtml) — regardless of whether the row
   clicked was a Program row or the header's dedicated COE row: Budget Processing acts on the
   WHOLE request's line items at once, not just the clicked row's own slice (unlike View/Edit,
   which are per-Program). In the flat list a row's own `program` still narrows which Program(s)
   are included (one, not every sibling Program under the same header) — only COE is unconditional. */
function bgr_budgetProcessing(idx) {
    const { header, program } = bgr_flatRows[idx];
    // endorsing / returning acts on the WHOLE request: FMD's rows are whole requests already; a superadmin sees the flat list but
    // decides the whole request too, so the modal gets every Program (not only the clicked row's) — else the other Programs' line
    // items would never get their Type of Fund
    const programs = (BGR_FMD || BGR_IS_ADMIN) ? (header.programs ?? []) : (program ? [program] : []);
    const coeLineItems = header.coe_line_items ?? [];
    if (!programs.length && !coeLineItems.length) {
        bgr_toast('This budget request has no programs yet.', 'warning');
        return;
    }
    bpr_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        // the requesting office and when it was submitted, for the modal's "Originating Unit" / "Date Submitted"
        rc_id: header.rc_id,
        rc_code: header.rc_code,
        rc_name: header.rc_name,
        submitted_at: header.submitted_at,
        // after a Return by the Budget Head: its remarks and the review step (who a Return goes back to)
        remarks: header.remarks,
        review: header.review,
    }, programs, coeLineItems);
}

/* "Budget Preview" row action (level 8 / Budget Head only) — opens the read-only Budget Preview modal
   (readone/budget-preview-readone.php, bpv_*): the same columns as the Budget Processing one, one block per
   Project (info card + line-item table). The header's own COE line items (header.coe_line_items), if it has
   any, ALWAYS come along too as their own sibling block — see bgr_budgetProcessing above for why. */
function bgr_budgetPreview(idx) {
    const { header, program } = bgr_flatRows[idx];
    // the whole request, for FMD and for a superadmin — see bgr_budgetProcessing above
    const programs = (BGR_FMD || BGR_IS_ADMIN) ? (header.programs ?? []) : (program ? [program] : []);
    const coeLineItems = header.coe_line_items ?? [];
    if (!programs.length && !coeLineItems.length) return;
    bpv_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        // the requesting office, for the modal's "Originating Unit / Region"
        rc_id: header.rc_id,
        rc_code: header.rc_code,
        rc_name: header.rc_name,
        // Return / Approve (+ the remarks box): the last step of the review chain — only the Budget Head, and only while the
        // request is "For Endorsement". The row's review says so (review.can_decide, decided on the server).
        can_decide: !!header.review?.can_decide && header.review.actor === 'Budget Head',
        review: header.review,
    }, programs, coeLineItems);
}

// COE row only — opens the same Add/Edit modal (form/budget-req-form.php, bgr_*) in 'edit-coe' mode.
function bgr_editCoe(idx) {
    const { header, isCoe } = bgr_flatRows[idx];
    if (!isCoe) return;
    bgr_openModal('edit-coe', header);
}

function bgr_editProgram(idx) {
    const { header, program } = bgr_flatRows[idx];
    if (!program) return;
    bgr_openModal('edit-program', {
        ...program,
        fiscal_yr: header.fiscal_yr,
        budget_reference_number: header.budget_reference_number,
    });
}

/* ── Table — one row per Program ──────────────────────────────────── */
function bgr_renderTable(headers) {
    if (BGR_FMD) { bgr_renderFmdTable(headers); return; }   // FMD: one row per request (see above)

    const tbody = $bgr('bgr-tbody');
    bgr_flatRows = bgr_flattenRows(headers);

    if (!bgr_flatRows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget requests found.</span></div></td></tr>';
        return;
    }

    tbody.innerHTML = bgr_flatRows.map((flatRow, idx) => {
        const { header, program, isCoe } = flatRow;
        const totals = isCoe ? bgr_coeTotals(header) : (program ? bgr_programTotals(program) : { projectCount: 0, lineItemCount: 0, amount: 0 });

        return `<tr>
            <td class="nha-td-seq">${idx + 1}</td>
            <td>${bgr_esc(header.fiscal_yr)}</td>
            <td>
                ${isCoe
                    ? `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;
                        font-size:11px;font-weight:700;color:var(--afims-heading-fill, #000E3E);
                        background:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 10%, #fff);
                        border:1px solid color-mix(in srgb, var(--afims-heading-fill, #000E3E) 25%, transparent);">COE</span>`
                    : (program
                        ? `<div style="font-weight:500;">${bgr_esc(program.program_code)} — ${bgr_esc(program.program_name)}</div>`
                        : '<span class="nha-td-sub">No programs added yet</span>')}
            </td>
            <td>${(!isCoe && program && program.sub_program_name) ? bgr_esc(program.sub_program_name) : '<span class="nha-td-sub">—</span>'}</td>
            <td class="text-end">${totals.projectCount}</td>
            <td class="text-end">${totals.lineItemCount}</td>
            <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(totals.amount)}</div></td>
            <td>${bgr_statusBadge(header.status_name, header.status_color)}</td>
            <td>${bgr_actionButtons(flatRow, idx)}</td>
        </tr>`;
    }).join('');
}

// Shows one request's remarks — status/remarks live on the REQUEST, not the Program, so this is reached
// from the toolbar's "View Remarks" button (one header, scoped by the Fiscal Year filter — see
// bgr_updateRemarksButton), not from a per-row action, to avoid implying any one Program was singled out.
function bgr_openRemarksDialog(header) {
    Swal.fire({
        icon: 'warning',
        title: header.status_name || 'Remarks',
        html: `<div style="text-align:left;font-size:13px;line-height:1.5;">${bgr_esc(header.remarks)}</div>`,
        confirmButtonText: 'Close',
    });
}

/* ── Top-of-filters "Submit for Review" — acts on whichever single Draft OR
   Returned request the Fiscal Year filter currently narrows the list down
   to ('Returned' = the Budget Officer sent it back to this office; it's
   resubmittable the same way a Draft is — see BudgetReqService::submitForReview()).
   Disabled unless that filter names exactly one such request. ──────────── */
function bgr_updateSubmitButton(headers) {
    const btn = $bgr('bgr-submit-review');
    if (!btn) return;

    // Enabled whenever the current view (filtered or not) already narrows
    // down to exactly one submittable request — no need to type a fiscal
    // year into the filter first if there's only one to begin with.
    const submittableHeaders = headers.filter(h => h.status_name === 'Draft' || h.status_name === 'Returned');
    const match = submittableHeaders.length === 1 ? submittableHeaders[0] : null;

    btn.disabled = !match;
    btn.title = match
        ? `Submit the fiscal year ${match.fiscal_yr} request for review`
        : submittableHeaders.length > 1
            ? 'Filter by a single fiscal year to submit its request'
            : 'No Draft or Returned request to submit';
    btn.onclick = match ? () => bgr_submitForReview(match.budget_reference_number) : null;
}

/* ── Header "+ Add COE Budget Request" — at most one shared budget_req header per fiscal
   year per office already (fims.budget_req's own UNIQUE (fiscal_yr, rc_id) — see
   migration 45), which that header's own COE line items (if any) live on. Disabled here
   too, as soon as the Fiscal Year filter narrows the list down to one specific year whose
   header already has COE line items, pointing the user at that row's own Edit action
   instead of silently appending more via Add. Left enabled when "All Years" is selected
   (ambiguous which year to restrict) or the
   Program filter is active (a COE row never matches it, so this view can't tell either
   way) — the server-side constraint still catches an actual collision regardless. ──── */
function bgr_updateAddCoeButton(headers) {
    const btn = $bgr('bgr-add-coe');
    if (!btn) return;

    const fiscalYr    = $bgr('bgr-filter-fiscal-yr')?.value || '';
    const programCode = $bgr('bgr-filter-program')?.value || '';
    if (!fiscalYr || programCode) {
        btn.disabled = false;
        btn.title = 'Add a COE budget request — each line item has no Program/Project, just chart-of-accounts allocations';
        return;
    }

    const existing = headers.find(h => (h.coe_line_items ?? []).length);
    btn.disabled = !!existing;
    btn.title = existing
        ? `Fiscal year ${fiscalYr} already has a COE budget request (${existing.budget_reference_number}) — use its own Edit action to add more line items.`
        : 'Add a COE budget request — each line item has no Program/Project, just chart-of-accounts allocations';
}

/* ── Toolbar "View Remarks" — a pure view action, unlike Submit for Review's
   workflow gate, so it's HIDDEN rather than disabled when there's nothing to
   view: most requests (Draft / For Review / For Endorsement / Approved) never carry
   remarks, so a permanently greyed-out button would be clutter for the
   common case, and a disabled button's reason is invisible until hovered —
   no better than not being there. It's only shown DISABLED when there IS
   something to see but it's ambiguous which request (more than one in the
   current view has remarks) — that's recoverable by filtering, so it stays
   visible with an explanation instead of disappearing. Otherwise (current
   filters narrow the list down to exactly one request with remarks), it's
   shown enabled. Avoids repeating the same remarks on every one of that
   request's Program rows in the table. ───────────────────────────────── */
function bgr_updateRemarksButton(headers) {
    const btn = $bgr('bgr-view-remarks');
    if (!btn) return;

    const withRemarks = headers.filter(h => h.remarks);
    if (!withRemarks.length) {
        btn.hidden = true;
        return;
    }

    const match = withRemarks.length === 1 ? withRemarks[0] : null;
    btn.hidden = false;
    btn.disabled = !match;
    btn.title = match
        ? `View why the fiscal year ${match.fiscal_yr} request was returned`
        : 'Filter by a single fiscal year to view its remarks';
    btn.onclick = match ? () => bgr_openRemarksDialog(match) : null;
}

/* ── Pagination ────────────────────────────────────────────── */
function bgr_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = BGR_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $bgr('bgr-from').textContent  = from;
    $bgr('bgr-to').textContent    = to;
    $bgr('bgr-total').textContent = total;

    const container = $bgr('bgr-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="bgr_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="bgr_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="bgr_goTo(${current_page + 1})">›</button>`;
}

function bgr_goTo(page) { bgr_page = page; bgr_load(); }

function bgr_submitForReview(id) {
    Swal.fire({
        title: 'Submit for Review',
        text: `Submit budget reference number ${id} for review? No more programs can be added or edited once submitted.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, proceed',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#0f2045',
    }).then(result => {
        if (result.isConfirmed) bgr_doSubmitForReview(id);
    });
}

async function bgr_doSubmitForReview(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/submit-for-review/${id}`, { method: 'PUT', credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to submit for review.');

        bgr_toast(json.message || 'Submitted for review.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function bgr_delete(id, fiscalYr) {
    Swal.fire({
        title: 'Delete Budget Request',
        text: `Delete the entire budget request for fiscal year ${fiscalYr}? This removes ALL its programs and cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545',
    }).then(result => {
        if (result.isConfirmed) bgr_doDelete(id);
    });
}

async function bgr_doDelete(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/delete/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete budget request.');

        bgr_toast('Budget request deleted successfully.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// Flat-list row only — deletes just that one Program (its projects/line
// items cascade). If it was the request's last Program, the backend
// removes the now-empty header too.
function bgr_deleteProgram(idx) {
    const { program } = bgr_flatRows[idx];
    if (!program) return;

    Swal.fire({
        title: 'Delete Program',
        text: `Delete ${program.program_code} — ${program.program_name}? This removes its projects and line items and cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545',
    }).then(result => {
        if (result.isConfirmed) bgr_doDeleteProgram(program.budget_req_program_id);
    });
}

async function bgr_doDeleteProgram(budgetReqProgramId) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/program/delete/${budgetReqProgramId}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete program.');

        bgr_toast(json.message || 'Program deleted successfully.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// COE row only — deletes just that header's headless line items (its Programs, if any,
// are untouched). If it had no Programs either, the server removes the now-empty header too.
function bgr_deleteCoe(idx) {
    const { header, isCoe } = bgr_flatRows[idx];
    if (!isCoe) return;

    Swal.fire({
        title: 'Delete COE Line Items',
        text: `Delete the COE line items of budget reference number ${header.budget_reference_number}? This cannot be undone.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Yes, delete',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#dc3545',
    }).then(result => {
        if (result.isConfirmed) bgr_doDeleteCoe(header.budget_reference_number);
    });
}

async function bgr_doDeleteCoe(budgetReferenceNumber) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/coe/delete/${budgetReferenceNumber}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete the COE line items.');

        bgr_toast(json.message || 'COE line items deleted successfully.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

<?php if ($_bgr_canAdd) { /* Regional / District only — no tab bar / BUR panel rendered otherwise, so none of this runs */ ?>
/* ── Tabs: the request list vs. the Approved BUR breakdown ── */
function bgr_switchTab(tab) {
    document.querySelectorAll('#bgr-tabs .nha-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('#bgr-root .nha-panel').forEach(p => p.classList.toggle('active', p.id === 'bgr-panel-' + tab));
    if (tab === 'bur') bgrbur_ensureLoaded();   // lazy: only hit the API the first time this tab is opened
}

/* ══════════════════════════════════════════════════════════════
   APPROVED BUR TAB   prefix: bgrbur_   (read-only)
   The same tree the standalone Budget Utilization Report (bur-page.php) shows — Personal Services (5-01) /
   Maintenance and Other Operating Expenses (5-02), rolled up from every APPROVED budget request's line
   items, scoped to the viewer's own RC — reusing that page's own API unchanged (GET /bur/filters,
   GET /bur/rollup?fiscal_yr=; see BurService for what each of the seven amount columns means). This is this
   tab's own copy of the tree-building logic (bur_buildTree / bur_flattenTree in bur-page.php) — not shared,
   since this is a different page, not an iframe of that one.
═══════════════════════════════════════════════════════════════ */
const BGRBUR_API = '<?php echo $_bgr_api; ?>' + '/bur';
const BGRBUR_COLS = ['approved_amount', 'revised_amount', 'revision_amount', 'utilization', 'balance', 'payment', 'account_payable'];

let bgrbur_loaded = false;   // true once the tab has been opened at least once (lazy load)
let bgrbur_rows = [];
let bgrbur_totals = null;
let bgrbur_collapsed = new Set();
let bgrbur_fiscalYr = '';

function bgrbur_cell(v) {
    const n = Number(v) || 0;
    return Math.abs(n) < 0.005 ? bgr_fmtMoney(0) : `<span class="${n < 0 ? 'bgrbur-neg' : ''}">${bgr_fmtMoney(n)}</span>`;
}
// Only the middle level (depth 1 — e.g. "5-01-01 Salaries and Wages", "5-01-02 Other Compensation") shows no
// amounts of its own: left blank rather than the rolled-up total the server already computed for it. The
// root (depth 0) keeps its combined figure, same as a leaf account (no children) does; the TOTAL row (no
// `_depth` of its own — see bgrbur_render) is unaffected either way.
function bgrbur_amountCells(o) {
    const blank = o?._depth === 1;
    return BGRBUR_COLS.map(k => `<td class="nha-mono text-end">${blank ? '' : bgrbur_cell(o?.[k])}</td>`).join('');
}

async function bgrbur_loadFilterOptions() {
    const res = await fetch(`${BGRBUR_API}/filters`, { credentials: 'same-origin' });
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to load filters.');

    const years = json.data?.fiscal_years ?? [];
    const sel = $bgr('bgrbur-f-fy');
    sel.innerHTML = '<option value="">All Years</option>' + years.map(y => `<option value="${y}">${y}</option>`).join('');
    sel.value = years.length ? String(years[0]) : '';
    bgrbur_fiscalYr = sel.value;
}

async function bgrbur_load() {
    window.PagePreloader?.show();
    $bgr('bgrbur-tbody').innerHTML = '<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    try {
        const qs = bgrbur_fiscalYr ? `?${new URLSearchParams({ fiscal_yr: bgrbur_fiscalYr })}` : '';
        const res = await fetch(`${BGRBUR_API}/rollup${qs}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the report.');

        bgrbur_rows = json.data ?? [];
        bgrbur_totals = json.totals ?? null;
        bgrbur_render();
    } catch (e) {
        bgrbur_rows = []; bgrbur_totals = null;
        $bgr('bgrbur-tbody').innerHTML = `<tr><td colspan="9"><div class="nha-error">${bgr_esc(e.message)}</div></td></tr>`;
    } finally {
        window.PagePreloader?.hide();
    }
}

function bgrbur_buildTree(rows) {
    const byId = new Map(rows.map(r => [r.account_id, { ...r, children: [] }]));
    const roots = [];
    byId.forEach(node => {
        const parent = node.parent_account_id && byId.get(node.parent_account_id);
        if (parent) parent.children.push(node); else roots.push(node);
    });
    const cmp = (a, b) => (a.account_code || '').localeCompare(b.account_code || '', undefined, { numeric: true, sensitivity: 'base' });
    const sortRec = list => { list.sort(cmp); list.forEach(n => sortRec(n.children)); };
    sortRec(roots);
    return roots;
}
function bgrbur_flattenTree(nodes, depth, out) {
    nodes.forEach(node => {
        out.push({ ...node, _depth: depth, _hasChildren: node.children.length > 0 });
        if (node.children.length && !bgrbur_collapsed.has(node.account_id)) bgrbur_flattenTree(node.children, depth + 1, out);
    });
    return out;
}
function bgrbur_toggleNode(id) {
    if (bgrbur_collapsed.has(id)) bgrbur_collapsed.delete(id); else bgrbur_collapsed.add(id);
    bgrbur_render();
}

function bgrbur_render() {
    const tbody = $bgr('bgrbur-tbody');
    const totalRow = `<tr class="bgrbur-row-total"><td>TOTAL</td><td></td>${bgrbur_amountCells(bgrbur_totals)}</tr>`;

    if (!bgrbur_rows.length) {
        tbody.innerHTML = totalRow + '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No approved budget found.</span></div></td></tr>';
        return;
    }

    const flat = bgrbur_flattenTree(bgrbur_buildTree(bgrbur_rows), 0, []);
    const bodyRows = flat.map(r => {
        const indent = r._depth * 22;
        const expanded = !bgrbur_collapsed.has(r.account_id);
        const toggle = r._hasChildren
            ? `<button type="button" class="bgrbur-toggle" onclick="bgrbur_toggleNode('${bgr_esc(r.account_id)}')" title="${expanded ? 'Collapse' : 'Expand'}">
                   <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"
                        style="transform:rotate(${expanded ? 90 : 0}deg);">
                       <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                   </svg>
               </button>`
            : '<span class="bgrbur-toggle-spacer"></span>';

        return `<tr class="bgrbur-d${Math.min(r._depth, 4)}">
            <td><div class="bgrbur-name" style="padding-left:${indent}px;">${toggle}<span>${bgr_esc(r.account_title || '—')}</span></div></td>
            <td class="nha-mono nha-td-sub">${bgr_esc(r.account_code || '—')}</td>
            ${bgrbur_amountCells(r)}
        </tr>`;
    }).join('');

    tbody.innerHTML = totalRow + bodyRows;
}

function bgrbur_expandAll() { bgrbur_collapsed.clear(); bgrbur_render(); }
function bgrbur_collapseAll() { bgrbur_collapsed = new Set(bgrbur_rows.map(r => r.parent_account_id).filter(Boolean)); bgrbur_render(); }

async function bgrbur_ensureLoaded() {
    if (bgrbur_loaded) return;
    bgrbur_loaded = true;
    window.PagePreloader?.show();
    try { await bgrbur_loadFilterOptions(); } catch (e) { /* the Fiscal Year filter just stays empty */ }
    finally { window.PagePreloader?.hide(); }
    bgrbur_load();
}
<?php } ?>
</script>
