<?php

namespace PHPMaker2024\FIMS;

/**
 * budget-revision-page.php — "Budget Amendment Requests": the requests to change a BUDGET LINE's amount (a chart-of-accounts line
 * item of a budget request) and optionally move the difference to a second budget line. Originating offices file and edit
 * them; the Financial Management Department (FMD) reviews them — approves or declines. A request goes Saved -> Submitted -> Approved
 * (or Declined): filing it SAVES it, which leaves it editable; "Process" in its Actions menu submits it, after which it can no longer be
 * edited, and FMD reviews the Submitted ones. Approving does not change the budget lines in place: it writes a transaction row per line to the
 * ledger fims.budget_line_item_revision_transaction (the amount, the previous amount and the new amount) — the From line goes down, the To line goes up — and
 * the COB's Revision Amount is the sum of those rows.
 *
 * Who is looking decides what a row offers. There is no point-of-view switch: it follows the logged-in user's office.
 *
 * Two kinds of request, chosen with the Revision / Realignment tabs of the Add / Edit form (form/budget-rev-form.php): a REVISION
 * changes one budget line's amount (no To budget line); a REALIGNMENT moves funds from the From line to a To line. The table's Type
 * column says which one each request was filed as (fims.budget_revision.request_type, kept in step with the To line by the API).
 *
 * The Amount field means something different on each tab: a Revision's is the line's NEW amount, a Realignment's is the amount TRANSFERRED to
 * the To line. Either way the request is stored as the From line's amount before → after (from_amount → to_amount; a Realignment saves
 * to_amount = from_amount − the amount transferred), so the table's From / To / Change columns and the approval work the same for both.
 *
 * A third tab, SUPPLEMENTAL, files a NEW project released by a DBM SARO that is not in the approved COB (a Program / Sub-Program, a SARO, the project's
 * name and location, and its line items: account, particulars, amount — no Project Code field: the code is generated on approval). It has no budget line, so the
 * table, the details modal and the form describe it by its own supp_* fields. It is filed, processed and reviewed like the others; APPROVING it is what
 * adds the project to the budget — one transaction (BudgetRevisionService::approveSupplemental()): the project, its budget lines (suggested_amount left at 0)
 * and a SUPPLEMENTAL ledger row per line, so the amount shows in the COB's Amendment column and not in its Approved Amount.
 */
@session_start();
$_brv_api = Config('API.FIMS_URL');

// Who is looking: the logged-in user's office. uac.users gives the rc_id (that column only) and is joined to
// fims.responsibility_code for the RC itself — the same lookup as budget-req-page.php and memo-req-page.php.
//   FMD (the Financial Management Department: rc_code 35-074-0000000-032, rc_id 013 in this data) -> the FMD point of
//       view: a Submitted row gets a "Review Request" action (details + Approve / Decline), every other one "View Details".
//       FMD does not edit the offices' requests — only the Saved ones it filed itself get Edit and Process.
//   Anyone else -> the originating point of view: they file revision requests, and a Saved row gets Edit and Process actions.
//       Edit is switched off once the request has been processed (Submitted), approved or declined — a processed request is in
//       front of the reviewers, an approved one has been applied to its budget lines.
// FMD is recognised by its rc_code, NOT its rc_id (the id's format differs between datasets: "013" vs "RC-013"). Mirrors
// GlobalService::CENTRAL_REVIEW_RC_CODE. A user whose office can't be found (no users row, no rc_id, or a failed lookup) gets the
// originating view, never an error page and never the review actions — unless they are an administrator (see below).
//   Administrator (user level -1) -> no restriction: can review (Approve / Decline) any Submitted request, whatever office it
//       belongs to, and keeps Edit + Process on every Saved one. Same rule as the rest of the app (DisbursementVoucherService,
//       voucher/readone.php): the built-in administrator (user_id -1, no uac.users row) or a uac.users row whose user_level_id
//       is '-1'. NOT IsSysAdmin(), which only tracks PHPMaker's hardcoded admin login and is never true for a DB-authenticated user.
$userid = CurrentUserID();
$_brv_fmdRcCode = '35-074-0000000-032';
$_brv_isFmd = false;
$_brv_isAdmin = (int) $userid === -1
    || in_array(-1, array_map('intval', explode(',', (string) CurrentUserLevel())), true);
try {
    // LEFT JOIN: an administrator may have no office (rc_id) — the user row is still needed for its user_level_id
    $_brv_rc = ExecuteRow(
        'SELECT u.user_level_id, rc.rc_id, rc.rc_code, rc.rc_name FROM uac.users u '
        .'LEFT JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id WHERE u.user_id = '.(int) $userid,
        'DB'
    ) ?: null;
    $_brv_isFmd = $_brv_rc !== null && trim((string) ($_brv_rc['rc_code'] ?? '')) === $_brv_fmdRcCode;
    if ($_brv_rc !== null && trim((string) ($_brv_rc['user_level_id'] ?? '')) === '-1') {
        $_brv_isAdmin = true;
    }
} catch (\Throwable $e) {
    $_brv_isFmd = false;
    LogError('budget-revision-page: could not look up the current user\'s RC — '.$e->getMessage());
}
// Who gets the review actions (details + Approve / Decline on a Submitted request): FMD, and the administrator without restriction.
$_brv_canReview = $_brv_isFmd || $_brv_isAdmin;
?>

<?php /* GetUrl() without a leading slash adds the app's base path (the app may live in a sub-folder / virtual directory), and the
          cache-buster is read from the file next to this page, not from DOCUMENT_ROOT — so a changed stylesheet is never served
          from a browser's stale cache. */ ?>
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .brv-summary-2col { grid-template-columns: repeat(2, 1fr); }
}

/* ── Actions dropdown menu ──────────────────────────────────── */
/* The same menu as the Budget Proposal / Budget Requirements lists, which keep their copy inline too. */
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
    border:none;background:none;width:100%;text-align:left;transition:background .1s;
    font-family:'IBM Plex Sans',sans-serif; }
.nha-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
/* an action that is switched off (Edit on an approved request) */
.nha-menu-item[disabled] { opacity:.5;cursor:not-allowed; }
.nha-menu-item[disabled]:hover { background:none;color:var(--afims-text, #1A1B21); }

/* ── Table cells ────────────────────────────────────────────── */
.nha-root .brv-up   { color: var(--green-txt, #0C7539); }
.nha-root .brv-down { color: var(--red-txt, #8F222D); }
.nha-root .brv-reason {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

</style>

<div id="brv-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">Budget Amendment Requests</h1>
            <p class="app-page-subtitle" id="brv-hdr-sub"><?php echo $_brv_canReview
                ? 'Review the amendment, realignment and supplemental requests the offices have submitted — approve or decline them.'
                : 'File an amendment, realignment or supplemental request and save it, then Process it to submit it for review — and follow its status.'; ?></p>
        </div>
        <div class="app-page-header-actions">
            <button id="brv-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="brv-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="File a budget amendment or realignment request">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Request
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover every request matching the filters, not just this page) ── -->
    <div class="nha-summary brv-summary-2col">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Amendments</div>
                    <div class="nha-sc-val" id="brv-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Net Change</div>
                    <div class="nha-sc-val" id="brv-kpi-change">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Sum of (to − from), matches current filters</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="brv-filter-fiscal-yr" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Budget Request</label>
                <select id="brv-filter-req" class="nha-filter-select">
                    <option value="">All</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>Request No.</th>
                        <th>Type</th>
                        <th>Budget Line</th>
                        <th class="text-right">From</th>
                        <th class="text-right">To</th>
                        <th class="text-right">Change</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="brv-tbody">
                    <tr><td colspan="10"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="brv-from">0</span>–<span id="brv-to">0</span></strong> of <strong><span id="brv-total">0</span></strong></span>
            <div id="brv-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- Modal must live inside .nha-root — every .nha-overlay / .nha-modal rule in nha-fims.css is scoped to it -->
    <?php include __DIR__ . '/form/budget-rev-form.php'; ?>
    <?php include __DIR__ . '/form/budget-rev-review.php'; ?>

</div><!-- /#brv-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET AMENDMENT REQUESTS LIST   prefix: brv_
   One row per amendment request: Request No. (its id — the number the details modal is titled with, the fiscal year of
   the budget line's request under it), the Budget Line being revised (its account, and where it sits; + the To line when
   funds move to another one), From / To / Change amounts, Reason, Status, and when it was requested and by whom.
   A request is filed as SAVED; Process (Saved -> Submitted) hands it to FMD and ends editing; FMD then approves or declines it.
   Row actions depend on who is looking (BRV_CAN_REVIEW / BRV_IS_ADMIN, set by the PHP at the top of this file) and on the request's status:
     FMD         -> Review Request (Submitted) / View Details (any other): the details modal with Approve / Decline;
                    and Edit + Process on a Saved request that FMD itself filed
     Admin       -> the same review actions as FMD, with no restriction; plus Edit + Process on ANY Saved request
     anyone else -> Edit + Process on a Saved request; Edit is off from then on (Submitted, Approved, Declined)
   Delete is not offered to either. The API enforces what can be edited / processed / reviewed by status (Saved / Saved / Submitted),
   but not who does it — that split only decides what the page offers.
═══════════════════════════════════════════════════════════════ */

const BRV_BASE = '<?php echo $_brv_api; ?>';
const BRV_API  = BRV_BASE + '/budget_revision';
// true when the logged-in user belongs to the Financial Management Department (see the PHP at the top).
const BRV_IS_FMD = <?php echo $_brv_isFmd ? 'true' : 'false'; ?>;
// true when the logged-in user is an administrator (user level -1): no restriction on reviewing or editing
const BRV_IS_ADMIN = <?php echo $_brv_isAdmin ? 'true' : 'false'; ?>;
// who is offered Review Request / Approve / Decline: FMD, or the administrator
const BRV_CAN_REVIEW = <?php echo $_brv_canReview ? 'true' : 'false'; ?>;
// the logged-in user's id (the built-in administrator is -1): a request's user_id is who filed it
const BRV_USER_ID = <?php echo (int) $userid; ?>;

let brv_page = 1;
let brv_viewRow = null;   // the revision currently open in the details modal
const BRV_PAGE_SIZE = 15;

// `status` codes returned by the API (BudgetRevisionService: the upper-cased name of the revision's fims.settings_status row,
// status_for = 'budget_revision') → label + badge class. A FALLBACK only: the badge is painted with the status row's own
// colour (status_color) and name (status_name) whenever the API sends them — see brv_statusBadge().
const BRV_STATUS = {
    SAVED    : { label: 'Saved',     cls: 'nha-bdg-gray'  },   // filed, still editable
    SUBMITTED: { label: 'Submitted', cls: 'nha-bdg-amber' },   // processed: waiting for FMD, no more editing
    APPROVED : { label: 'Approved',  cls: 'nha-bdg-green' },
    DECLINED : { label: 'Declined',  cls: 'nha-bdg-red'   },
};

// What kind of request a row is: a REALIGNMENT moves funds to a To budget line, a REVISION only changes the From line's amount. The API
// sends it as request_type (a column of fims.budget_revision); the To line is the fallback for a database that has not had
// "03 - alter_table/42" yet, whose rows have no request_type.
const BRV_TYPE = {
    REVISION    : { label: 'Amendment',    cls: 'nha-bdg-blue'  },
    REALIGNMENT : { label: 'Realignment',  cls: 'nha-bdg-teal'  },
    // a NEW project released by a SARO — no budget line until it is approved (see form/budget-rev-form.php, BudgetRevisionService::approveSupplemental())
    SUPPLEMENTAL: { label: 'Supplemental', cls: 'nha-bdg-amber' },
};
function brv_typeOf(r) {
    const t = String(r?.request_type || '').toUpperCase();
    return BRV_TYPE[t] ? t : (r?.to_budget_line_item_id ? 'REALIGNMENT' : 'REVISION');
}
function brv_typeBadge(r) {
    const t = BRV_TYPE[brv_typeOf(r)];
    return `<span class="nha-bdg ${t.cls}">${t.label}</span>`;
}

// A Supplemental request has no budget line, so the list and the details describe it by its own fields: where the new project goes
// ("101 — Socialized Housing Program › 101-003 — Resettlement Housing Units") and the SARO it was released under.
function brv_suppPlace(r) {
    const withName = (code, name) => (code ? code + (name ? ' — ' + name : '') : '');
    const where = [withName(r.supp_program_code, r.supp_program_name), withName(r.supp_sub_program_code, r.supp_sub_program_name)].filter(Boolean).join(' › ');
    return [where, r.supp_saro_no ? 'SARO NO. ' + r.supp_saro_no : ''].filter(Boolean).join(' · ');
}

const $brv = id => document.getElementById(id);

async function brv_bootstrapLoad() {
    // brvBudgetReqsReady comes from form/budget-rev-form.php, which fills the
    // form's Budget Request dropdown. Wait for it so Edit can preselect a value.
    await Promise.all([brv_load(), brv_fillFilterOptions(), brv_fillYears()]);
    brv_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', brv_bootstrapLoad);
} else {
    brv_bootstrapLoad();
}

// The filter reuses the budget requests the form already fetched from /budget_req/all.
// Pass `items` to rebuild the list from fresh data; otherwise it uses the form's first load.
async function brv_fillFilterOptions(items) {
    items = items ?? (await window.brvBudgetReqsReady) ?? [];
    const filter = $brv('brv-filter-req');
    const keep = filter.value;
    filter.querySelectorAll('option:not([value=""])').forEach(o => o.remove());
    items.forEach(({ value, label }) => {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        filter.appendChild(opt);
    });
    filter.value = keep;   // keep the current selection if it still exists
}

// The Fiscal Year filter lists the fiscal years from /fiscal_year/all (the same list the other pages use).
// If it can't be read the filter still works as "All Years".
async function brv_fillYears() {
    const select = $brv('brv-filter-fiscal-yr');
    try {
        const res  = await fetch(`${BRV_BASE}/fiscal_year/all`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'HTTP ' + res.status);

        const keep = select.value;
        select.querySelectorAll('option:not([value=""])').forEach(o => o.remove());
        (json.data ?? []).forEach(({ fiscal_yr }) => {
            if (fiscal_yr === null || fiscal_yr === undefined || fiscal_yr === '') return;
            const opt = document.createElement('option');
            opt.value = fiscal_yr;
            opt.textContent = fiscal_yr;
            select.appendChild(opt);
        });
        select.value = keep;
    } catch (e) {
        brv_toast('Could not load the fiscal years: ' + e.message, 'warning');
    }
}

// Approving changes a budget request's amount, so refresh what the form and the filter
// are showing (the labels carry the amount, and From Amount autofills from it).
async function brv_refreshBudgetReqs() {
    const items = await window.brvForm?.reload?.();
    if (items) brv_fillFilterOptions(items);
}

function brv_bindEvents() {
    $brv('brv-refresh')?.addEventListener('click', () => { brv_page = 1; brv_load(); });
    $brv('brv-add')?.addEventListener('click', () => brv_openModal('add'));
    $brv('brv-modal-close')?.addEventListener('click', brv_closeModal);
    $brv('brv-cancel')?.addEventListener('click', brv_closeModal);
    $brv('brv-form')?.addEventListener('submit', brv_save);

    $brv('brv-view-close')?.addEventListener('click', brv_closeView);
    $brv('brv-view-cancel')?.addEventListener('click', brv_closeView);
    $brv('brv-approve')?.addEventListener('click', () => brv_review('APPROVED'));
    $brv('brv-decline')?.addEventListener('click', () => brv_review('DECLINED'));

    ['brv-filter-fiscal-yr', 'brv-filter-req'].forEach(id => {
        $brv(id)?.addEventListener('change', () => { brv_page = 1; brv_load(); });
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function brv_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            fiscal_yr     : $brv('brv-filter-fiscal-yr')?.value || '',
            budget_reference_number : $brv('brv-filter-req')?.value || '',
            page          : brv_page,
            limit         : BRV_PAGE_SIZE,
        });
        const res  = await fetch(`${BRV_API}/list?${params}`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        // Read the body even on an error status, so the server's own message is shown rather than a bare "HTTP 4xx".
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'HTTP ' + res.status);

        brv_renderTable(json.data ?? []);
        brv_renderPagination(json.pagination ?? {});
        $brv('brv-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $brv('brv-kpi-change').textContent = brv_fmtChange(json.total_change ?? 0);
    } catch (e) {
        brv_renderTable([]);
        brv_renderPagination({});
        $brv('brv-kpi-total').textContent  = 0;
        $brv('brv-kpi-change').textContent = brv_fmtChange(0);
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table — one row per revision request ──────────────────── */
function brv_renderTable(rows) {
    const tbody = $brv('brv-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget amendment requests found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const supp   = brv_typeOf(r) === 'SUPPLEMENTAL';   // a new project: its fiscal year / program / SARO are the request's own (supp_*), it has no budget line
        const diff   = Number(r.to_amount || 0) - Number(r.from_amount || 0);
        const trend  = diff > 0 ? 'brv-up' : (diff < 0 ? 'brv-down' : '');
        const reason = r.reason ? brv_esc(r.reason) : '<span class="nha-td-sub">—</span>';
        const date   = brv_fmtDate(r.created_at);
        return `<tr>
        <td>
            <code class="nha-code">#${brv_esc(r.id)}</code>
            <div class="nha-td-sub">FY ${brv_esc((supp ? r.supp_fiscal_yr : r.fiscal_yr) ?? '—')}</div>
        </td>
        <td>${brv_typeBadge(r)}</td>
        <td>
            <div class="nha-td-bold">${brv_esc(supp ? (r.supp_project_name || '—') : brv_lineTitle(r, ''))}</div>
            <div class="nha-td-sub">${brv_esc(supp ? brv_suppPlace(r) : brv_linePlace(r, ''))}</div>
            ${r.to_budget_line_item_id ? `<div class="nha-td-sub" title="Funds move to this budget line">→ To: ${brv_esc(brv_lineTitle(r, 'to_'))}${r.to_budget_reference_number ? ' (' + brv_esc(r.to_budget_reference_number) + ')' : ''}</div>` : ''}
        </td>
        <td class="text-end">${brv_fmtMoney(r.from_amount)}</td>
        <td class="text-end"><div style="font-weight:600;">${brv_fmtMoney(r.to_amount)}</div></td>
        <td class="text-end ${trend}" style="font-weight:600;">${brv_fmtChange(diff)}</td>
        <td><div class="brv-reason" title="${brv_esc(r.reason || '')}">${reason}</div></td>
        <td>${brv_statusBadge(r)}</td>
        <td>
            ${date ? `<span class="nha-td-date">${brv_esc(date)}</span>` : '<span class="nha-td-sub">—</span>'}
            <div class="nha-td-sub">${brv_esc(brv_requestedBy(r))}</div>
        </td>
        <td>${brv_rowActions(r)}</td>
    </tr>`;
    }).join('');
}

/* ── Row actions ───────────────────────────────────────────── */
const BRV_ICON_VIEW  = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
const BRV_ICON_REVIEW = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
const BRV_ICON_EDIT  = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';

const BRV_ICON_PROCESS = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>';

// The menu a row gets depends on the request's status and on who is looking (BRV_CAN_REVIEW, BRV_IS_ADMIN):
//   Saved      -> Edit and Process (Process submits it; it can't be edited after that). FMD gets them only on a request it filed itself,
//                 and "View Details" besides — FMD does not touch the offices' drafts. An administrator gets them on any request.
//   Submitted  -> FMD / administrator: Review Request (Approve / Decline). Anyone else: Edit, switched off.
//   Approved / Declined -> FMD / administrator: View Details. Anyone else: Edit, switched off (an approved request has been applied to its budget lines).
function brv_rowActions(r) {
    const id = Number(r.id);
    const saved = r.status === 'SAVED';
    const own   = Number(r.user_id) === BRV_USER_ID;
    const items = [];
    const editAndProcess = () => [
        `<button type="button" class="nha-menu-item" onclick="brv_closeMenus();brv_edit(${id});">
            ${BRV_ICON_EDIT}
            Edit
        </button>`,
        `<button type="button" class="nha-menu-item" onclick="brv_closeMenus();brv_process(${id});">
            ${BRV_ICON_PROCESS}
            Process
        </button>`,
    ];

    if (BRV_CAN_REVIEW) {
        const review = r.status === 'SUBMITTED';
        items.push(`<button type="button" class="nha-menu-item" onclick="brv_closeMenus();brv_view(${id});">
            ${review ? BRV_ICON_REVIEW : BRV_ICON_VIEW}
            ${review ? 'Review Request' : 'View Details'}
        </button>`);
        if (saved && (own || BRV_IS_ADMIN)) items.push(...editAndProcess());
    } else if (saved) {
        items.push(...editAndProcess());
    } else {
        items.push(`<button type="button" class="nha-menu-item" disabled title="Only a saved request can be edited — this one is ${brv_esc((BRV_STATUS[r.status]?.label || r.status_name || 'no longer saved').toLowerCase())}">
            ${BRV_ICON_EDIT}
            Edit
        </button>`);
    }

    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="brv_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">${items.join('')}</div>
    </div>`;
}

/* ── Actions dropdown — open/close (closes others, positions via fixed
   coords so the table's own overflow never clips it; opens upwards when
   there is no room below) ───────────────────────────────────────── */
function brv_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    brv_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.classList.add('open');
    const below = rect.bottom + 4;
    const fits  = below + drop.offsetHeight <= window.innerHeight - 8;
    drop.style.top   = (fits ? below : Math.max(8, rect.top - drop.offsetHeight - 4)) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
}

function brv_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) brv_closeMenus();
});
document.addEventListener('scroll', brv_closeMenus, true);

/* ── Pagination ────────────────────────────────────────────── */
function brv_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = BRV_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $brv('brv-from').textContent  = from;
    $brv('brv-to').textContent    = to;
    $brv('brv-total').textContent = total;

    const container = $brv('brv-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="brv_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="brv_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="brv_goTo(${current_page + 1})">›</button>`;
}

function brv_goTo(page) { brv_page = page; brv_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function brv_openModal(mode, row = null) {
    $brv('brv-mode').value = mode;
    $brv('brv-id').value = row?.id || '';
    $brv('brv-modal-title').textContent = mode === 'edit' ? 'Edit Budget Amendment Request' : 'Add Budget Amendment Request';
    // the Revision / Realignment tabs: a new request starts as a Revision; an existing one opens on the kind it was filed as
    // (the form hides the To budget line for a Revision — see form/budget-rev-form.php)
    window.brvForm?.setType(row ? brv_typeOf(row) : 'REVISION');
    // a Supplemental request has no budget line: its own panel (window.brvSupp, form/budget-rev-form.php) shows the saved request
    if (row && brv_typeOf(row) === 'SUPPLEMENTAL') {
        window.brvSupp?.fill(row);
        $brv('brv-overlay').classList.add('open');
        return;
    }
    // the From / To budget line fields are searchable dropdowns owned by form/budget-rev-form.php
    if (window.brvForm) window.brvForm.setBudgetLine(row?.from_budget_line_item_id ?? '', row?.to_budget_line_item_id ?? '');
    else {
        $brv('brv-line-id').value = row?.from_budget_line_item_id || '';
        $brv('brv-to-line-id').value = row?.to_budget_line_item_id || '';
    }
    $brv('brv-from-amount').value = brv_amtText(row?.from_amount);
    // A Realignment's Amount field is the amount TRANSFERRED, which is stored as the From line's amount before → after (from_amount →
    // to_amount): show the difference. A Revision's is the line's new amount, shown as stored.
    const transferred = row && brv_typeOf(row) === 'REALIGNMENT'
        ? Math.max(0, Math.round((Number(row.from_amount || 0) - Number(row.to_amount || 0)) * 100) / 100)
        : null;
    $brv('brv-to-amount').value = brv_amtText(transferred !== null ? transferred : row?.to_amount);
    $brv('brv-reason').value = row?.reason || '';

    $brv('brv-overlay').classList.add('open');
}

function brv_closeModal() {
    $brv('brv-overlay').classList.remove('open');
    $brv('brv-form').reset();
}

async function brv_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BRV_API}/read/${id}`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget amendment request.');
        if (json.data.status !== 'SAVED') {   // processed (or decided) after this page was loaded
            brv_toast(`This request is already ${(BRV_STATUS[json.data.status]?.label || json.data.status_name || 'processed').toLowerCase()}, so it can't be edited — only a saved request can be.`, 'warning');
            brv_load();
            return;
        }
        brv_openModal('edit', json.data);
    } catch (e) {
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// Save a Supplemental request — a new project, so it has its own body (window.brvSupp.payload(), form/budget-rev-form.php) and no From / To lines or amounts.
async function brv_saveSupplemental(mode, id) {
    const problem = window.brvSupp?.validate?.() || '';
    if (problem) { brv_toast(problem, 'error'); return; }
    const payload = window.brvSupp.payload();

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${BRV_API}/update/${id}` : `${BRV_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: brv_apiHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload),
        });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'Failed to save the supplemental request.');

        brv_toast(mode === 'edit'
            ? 'Supplemental request updated. Choose Process to submit it for review.'
            : 'Supplemental request saved. Choose Process in its Actions menu to submit it for review.', 'success');
        brv_closeModal();
        brv_load();
    } catch (e) {
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function brv_save(ev) {
    ev.preventDefault();
    const mode = $brv('brv-mode').value;
    const id   = $brv('brv-id').value;

    // the Supplemental tab files a new project, not a change to a budget line
    if ($brv('brv-type').value === 'SUPPLEMENTAL') { await brv_saveSupplemental(mode, id); return; }

    const type = $brv('brv-type').value === 'REALIGNMENT' ? 'REALIGNMENT' : 'REVISION';   // the tab the form is on
    // What was typed in the Amount field: a Revision's new amount, or the amount a Realignment TRANSFERS. The form turns it into the
    // two amounts that are stored (a Realignment: to_amount = the From line's amount − the amount transferred — see brvForm.amounts()).
    const typedAmount = brv_amtRaw('brv-to-amount');
    const amounts = window.brvForm?.amounts?.() ?? { from_amount: brv_amtRaw('brv-from-amount'), to_amount: typedAmount };
    const payload = {
        request_type             : type,
        from_budget_line_item_id : $brv('brv-line-id').value,
        // a Revision has no To line ('' also clears one on edit); a Realignment must name it
        to_budget_line_item_id   : type === 'REALIGNMENT' ? $brv('brv-to-line-id').value : '',
        from_amount              : amounts.from_amount,
        to_amount                : amounts.to_amount,
        reason                   : $brv('brv-reason').value.trim(),
    };

    if (!payload.from_budget_line_item_id) { brv_toast('Please select a From budget line.', 'error'); return; }
    if (type === 'REALIGNMENT' && !payload.to_budget_line_item_id) { brv_toast('Please select a To budget line to move the funds to.', 'error'); return; }
    if (type === 'REALIGNMENT') {
        // more than zero, and no more than the From line holds
        const msg = window.brvForm?.moveAmountError?.(typedAmount, payload.from_amount)
            || (Number(typedAmount) > 0 ? '' : 'The amount to transfer must be more than zero.');
        if (msg) { brv_toast(msg, 'error'); return; }
    }

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${BRV_API}/update/${id}` : `${BRV_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: brv_apiHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save budget amendment request.');

        brv_toast(mode === 'edit'
            ? 'Budget amendment request updated. Choose Process to submit it for review.'
            : 'Budget amendment request saved. Choose Process in its Actions menu to submit it for review.', 'success');
        brv_closeModal();
        brv_load();
    } catch (e) {
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Process: submit a saved request for review ────────────── */
// Saved -> Submitted. Nothing is applied to the budget lines yet — that happens when FMD approves it. A processed request can no longer
// be edited, so ask first.
async function brv_process(id) {
    if (!id || !confirm(`Process budget amendment request #${id}?\n\nIt will be submitted for review and can no longer be edited.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BRV_API}/process/${id}`, { method: 'PUT', credentials: 'same-origin', headers: brv_apiHeaders() });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'Failed to process budget amendment request.');

        brv_toast(json.message || `Budget amendment request #${id} processed.`, 'success');
        brv_load();
    } catch (e) {
        brv_toast(e.message, 'error');
        brv_load();   // it may have been processed (or changed) by someone else: show what it is now
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Details / review modal ────────────────────────────────── */
async function brv_view(id) {
    window.PagePreloader?.show();
    try {
        // read fresh from the API so the status shown is the current one
        const res  = await fetch(`${BRV_API}/read/${id}`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget amendment request.');
        brv_openView(json.data);
    } catch (e) {
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function brv_openView(r) {
    const diff = Number(r.to_amount || 0) - Number(r.from_amount || 0);

    brv_viewRow = r;
    $brv('brv-view-id').value = r.id;
    $brv('brv-view-title').textContent = `Budget ${BRV_TYPE[brv_typeOf(r)].label} Request #${r.id}`;

    // a Supplemental request has no budget lines: its own block (the project, the SARO, the line items) replaces the From / To / Amendment one
    const supp = brv_typeOf(r) === 'SUPPLEMENTAL';
    $brv('brv-view-std').hidden  = supp;
    $brv('brv-view-supp').hidden = !supp;
    if (supp) { brv_fillSuppView(r); brv_viewTail(r); return; }

    // the budget line being revised: its request's status, its account, where it sits (request · office · fiscal year, program ›
    // project, particulars) and its CURRENT amount — its remaining balance, the "Current balance" the form shows. One that no longer exists says so.
    brv_paintStatus($brv('brv-view-type'), r.budget_req_status_name, r.budget_req_status_color);
    $brv('brv-view-title-req').textContent = brv_lineTitle(r, '');
    $brv('brv-view-req-sub').textContent = brv_lineSub(r, '');
    $brv('brv-view-req-amount').textContent = brv_fmtMoney(r.line_amount);
    $brv('brv-view-req-amt-lbl').textContent = 'Current balance';

    // the To line — a card when the revision has one, a one-line note when it doesn't
    const hasTo = !!r.to_budget_line_item_id;
    $brv('brv-view-to-card').hidden = !hasTo;
    $brv('brv-view-to-none').hidden = hasTo;
    if (hasTo) {
        brv_paintStatus($brv('brv-view-to-type'), r.to_budget_req_status_name, r.to_budget_req_status_color);
        $brv('brv-view-to-title-req').textContent = brv_lineTitle(r, 'to_');
        $brv('brv-view-to-req-sub').textContent = brv_lineSub(r, 'to_');
        $brv('brv-view-to-req-amount').textContent = brv_fmtMoney(r.to_line_amount);
        $brv('brv-view-to-req-amt-lbl').textContent = 'Current balance';
    }

    // the revision
    $brv('brv-view-from').textContent = brv_fmtMoney(r.from_amount);
    $brv('brv-view-to').textContent   = brv_fmtMoney(r.to_amount);
    const change = $brv('brv-view-change');
    change.textContent = brv_fmtChange(diff);
    change.className = 'brv-v-cell-v' + (diff > 0 ? ' is-up' : diff < 0 ? ' is-down' : '');
    brv_renderEffect(r);

    brv_viewTail(r);
}

// What the details of every kind of request share: the reason, who filed it and when, the status, and the review buttons.
function brv_viewTail(r) {
    const reason = $brv('brv-view-reason');
    reason.textContent = r.reason || 'No reason provided.';
    reason.classList.toggle('is-empty', !r.reason);
    $brv('brv-view-meta').textContent = `Requested by ${brv_requestedBy(r)} on ${brv_fmtDateTime(r.created_at)}`;

    // status — Decline / Approve only while the request is Submitted (processed, waiting for a decision), and only for the reviewers (FMD, or the administrator)
    const known     = BRV_STATUS[r.status];
    const s         = known || { label: r.status || 'Unknown', cls: 'nha-bdg-gray' };
    const submitted = r.status === 'SUBMITTED';
    const label     = r.status_name || s.label;   // the settings_status name, else the fallback label
    const style     = brv_statusStyle(r.status_color);
    const badge     = $brv('brv-view-status');
    badge.textContent = label;
    badge.className   = style ? 'nha-bdg' : 'nha-bdg ' + s.cls;
    badge.style.cssText = style;   // '' clears a colour left by the previously opened request
    $brv('brv-view-status-note').textContent = submitted ? 'Waiting for a decision'
        : r.status === 'SAVED' ? 'Saved — not submitted for review yet (Process it first)'
        : known ? `${label} on ${brv_fmtDateTime(r.update_at)}` : 'Unrecognised status';
    $brv('brv-view-actions').hidden = !(submitted && BRV_CAN_REVIEW);
    brv_setReviewBusy(false);

    $brv('brv-view-overlay').classList.add('open');
}

function brv_closeView() {
    $brv('brv-view-overlay').classList.remove('open');
}

// The details of a Supplemental request: the new project and where it goes, the SARO, the line items, and what approving does.
// Nothing of it exists in the budget until it is approved; the project code is generated then (supp_project_code is empty before).
function brv_fillSuppView(r) {
    const withName = (code, name) => (code ? code + (name ? ' — ' + name : '') : '');
    const where    = [withName(r.supp_program_code, r.supp_program_name), withName(r.supp_sub_program_code, r.supp_sub_program_name)].filter(Boolean).join(' › ');
    const total    = brv_fmtMoney(r.to_amount);
    const fy       = r.supp_fiscal_yr ?? '—';

    $brv('brv-view-sp-project').textContent = r.supp_project_name || '—';
    $brv('brv-view-sp-sub').textContent = [where, r.supp_location ? 'Location: ' + r.supp_location : '', r.supp_project_code ? 'Project code: ' + r.supp_project_code : ''].filter(Boolean).join('\n');

    const dt = (/^(\d{4})-(\d{2})-(\d{2})/.exec(String(r.supp_saro_date || '')) || []);
    $brv('brv-view-sp-saro').textContent = r.supp_saro_no
        ? 'SARO NO. ' + r.supp_saro_no + (dt.length ? ` dtd. ${dt[2]}-${dt[3]}-${dt[1]}` : '')
        : 'SARO no longer found';
    $brv('brv-view-sp-saro-sub').textContent = [`FY ${fy}`, Number(r.supp_saro_amount) > 0 ? 'SARO amount ' + brv_fmtMoney(r.supp_saro_amount) : ''].filter(Boolean).join(' · ');

    $brv('brv-view-sp-items').innerHTML = (r.items || []).map(it => `<tr>
        <td>${brv_esc(it.account_code || it.account_id)}${it.account_title ? ' — ' + brv_esc(it.account_title) : ''}</td>
        <td>${brv_esc(it.particulars)}</td>
        <td class="brv-v-num">${brv_fmtMoney(it.amount)}</td>
    </tr>`).join('') || '<tr><td colspan="3">No line items.</td></tr>';
    $brv('brv-view-sp-total').textContent = total;

    const effect = $brv('brv-view-sp-effect');
    effect.classList.remove('is-warn');
    effect.textContent = r.status === 'APPROVED' ? `Approved — the project${r.supp_project_code ? ' ' + r.supp_project_code : ''} was added to the FY ${fy} budget with ${total} as an Amendment.`
        : r.status === 'DECLINED' ? 'Declined — nothing was added to the budget.'
        : `On approval the project is added to the FY ${fy} budget${where ? ' under ' + where : ''} with ${total} as an Amendment (not as an Approved Amount), and its project code is generated.`;
}

// What approving does to the two budget lines. Only shown when the revision has a To line: then it MOVES
// funds — the From line goes down to To Amount (a negative transaction is recorded for it) and the To line goes up by the difference
// (a positive one), measured against the From line's current amount, as the server does.
function brv_renderEffect(r) {
    const box = $brv('brv-view-effect');
    if (!r.to_budget_line_item_id) { box.hidden = true; return; }

    const fromCode = brv_lineName(r, '') || 'the From budget line';
    const toCode   = brv_lineName(r, 'to_') || 'the To budget line';
    const fromNow  = Number(r.line_amount || 0);
    const toNow    = Number(r.to_line_amount || 0);
    const target   = Number(r.to_amount || 0);
    const moved    = Math.round((fromNow - target) * 100) / 100;

    let text, warn = false;
    if (r.status === 'APPROVED')      text = `Funds were moved from ${fromCode} to ${toCode}.`;
    else if (r.status === 'DECLINED') text = `Declined — no funds were moved from ${fromCode} to ${toCode}.`;
    else if (moved > 0) {
        text = `On approval ${brv_fmtMoney(moved)} moves from ${fromCode} (${brv_fmtMoney(fromNow)} → ${brv_fmtMoney(target)}) `
             + `to ${toCode} (${brv_fmtMoney(toNow)} → ${brv_fmtMoney(toNow + moved)}).`;
    } else {
        text = `This can't be approved: To Amount (${brv_fmtMoney(target)}) is not lower than ${fromCode}'s current amount (${brv_fmtMoney(fromNow)}).`;
        warn = true;
    }
    box.textContent = text;
    box.classList.toggle('is-warn', warn);
    box.hidden = false;
}

function brv_setReviewBusy(busy) {
    ['brv-approve', 'brv-decline'].forEach(id => { $brv(id).disabled = busy; });
}

async function brv_review(status) {
    const id   = $brv('brv-view-id').value;
    const verb = status === 'APPROVED' ? 'approve' : 'decline';

    // Approving changes money, so say exactly what will happen before asking.
    let question = `Are you sure you want to ${verb} budget amendment request #${id}?`;
    if (status === 'APPROVED' && brv_viewRow && brv_typeOf(brv_viewRow) === 'SUPPLEMENTAL') {
        // a new project, not a change to a budget line: say what approving adds
        question += `\n\nThe project "${brv_viewRow.supp_project_name || ''}" will be added to the FY ${brv_viewRow.supp_fiscal_yr ?? ''} budget of the office that filed it, `
                  + `with a generated project code, and ${brv_fmtMoney(brv_viewRow.to_amount)} will be recorded as an Amendment (the Approved Amount stays 0).`;
    } else if (status === 'APPROVED' && brv_viewRow) {
        const v       = brv_viewRow;
        const current = Number(v.line_amount || 0);
        const target  = Number(v.to_amount || 0);
        const moved   = Math.round((current - target) * 100) / 100;
        if (v.to_budget_line_item_id && moved > 0) {
            // a transfer: the From line gives up what the To line receives
            const toCurrent = Number(v.to_line_amount || 0);
            const fromCode  = brv_lineName(v, '') || 'The From budget line';
            const toCode    = brv_lineName(v, 'to_') || 'The To budget line';
            question += `\n\n${fromCode} (From budget line): its current balance will change from ${brv_fmtMoney(current)} to ${brv_fmtMoney(target)}, `
                      + `and a transaction of ${brv_fmtChange(-moved)} will be recorded for it.`
                      + `\n${toCode} (To budget line): its current balance will change from ${brv_fmtMoney(toCurrent)} to ${brv_fmtMoney(toCurrent + moved)}, `
                      + `and a transaction of ${brv_fmtChange(moved)} will be recorded for it.`;
        } else if (!v.to_budget_line_item_id) {
            question += `\n\nThe budget line's current balance will change from ${brv_fmtMoney(current)} to ${brv_fmtMoney(target)}, `
                      + `and a transaction of ${brv_fmtChange(target - current)} will be recorded for it.`;
        }
    }
    if (!id || !confirm(question)) return;

    brv_setReviewBusy(true);   // no double submit
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BRV_API}/review/${id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: brv_apiHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ status }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || `Failed to ${verb} budget amendment request.`);

        brv_toast(json.message || `Budget amendment request ${verb}d.`, 'success');
        brv_closeView();
        brv_load();
        if (status === 'APPROVED') brv_refreshBudgetReqs();
    } catch (e) {
        brv_toast(e.message, 'error');
        brv_setReviewBusy(false);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
// The API only knows who is logged in when the request carries the JWT PHPMaker puts
// on the page (ew.API_JWT_TOKEN). Without this header the request is anonymous, so
// /budget_revision/add cannot tell who is filing the revision.
function brv_apiHeaders(extra = {}) {
    const headers = { ...extra };
    if (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) {
        headers[ew.API_JWT_AUTHORIZATION_HEADER || 'X-Authorization'] = 'Bearer ' + ew.API_JWT_TOKEN;
    }
    return headers;
}
function brv_fmtMoney(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// The form's From / To Amount inputs show thousands commas ("1,000.00", see form/budget-rev-form.php): brv_amtText puts an amount into
// one ('' when there is none), brv_amtRaw reads one back as the plain number string the API takes ("1000.00").
function brv_amtText(v) {
    return v === '' || v === null || v === undefined || isNaN(Number(v)) ? '' : Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function brv_amtRaw(id) {
    return String($brv(id).value ?? '').replace(/,/g, '').trim();
}
// Signed amount: +₱ 1,000.00 / −₱ 500.00 / ₱ 0.00
function brv_fmtChange(v) {
    const n = Number(v || 0);
    if (n === 0) return brv_fmtMoney(0);
    return (n > 0 ? '+' : '−') + brv_fmtMoney(Math.abs(n));
}
// Postgres timestamps arrive as "2026-11-10 08:30:00.123456+08": make that ISO enough for every browser's
// Date(), then show it as "Nov 10, 2026". '' when there is no date.
function brv_fmtDate(ts) {
    if (!ts) return '';
    const iso = String(ts).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d = new Date(iso);
    return isNaN(d) ? String(ts) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}
function brv_fmtDateTime(ts) {
    return ts ? String(ts).slice(0, 16) : '—';
}
// The built-in administrator is user_id -1 and has no row in uac.users.
function brv_requestedBy(r) {
    if (r.requested_by_name) return r.requested_by_name;
    if (r.requested_by_username) return r.requested_by_username;
    return Number(r.user_id) === -1 ? 'Administrator' : `User #${r.user_id}`;
}
// The budget line of a revision row: $pre is '' for the From line, 'to_' for the To line (the row has account_code…, to_account_code…).
// "5-02-010 — Travelling Expenses"; a revision filed before budget lines were revised has none ('—'), a line that has since been
// deleted shows its id.
function brv_lineTitle(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    const code = r[pre + 'account_code'], title = r[pre + 'account_title'];
    if (code) return code + (title ? ' — ' + title : '');
    if (title) return title;
    return id ? `Budget line #${id}` : '—';
}
// "5-02-010 (BP-001)": short enough for a sentence ('' when there is no line)
function brv_lineName(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    if (!id) return '';
    return (r[pre + 'account_code'] || `line #${id}`) + (r[pre + 'budget_reference_number'] ? ` (${r[pre + 'budget_reference_number']})` : '');
}
// "BP-001 · P-100 / PJ-1" — where the line sits, for the list
function brv_linePlace(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    if (!id) return 'Filed before budget lines could be amended';
    if (r[pre + 'fiscal_yr'] === null || r[pre + 'fiscal_yr'] === undefined) return 'This budget line no longer exists';
    return [r[pre + 'budget_reference_number'], [r[pre + 'program_code'], r[pre + 'project_code']].filter(Boolean).join(' / ')].filter(Boolean).join(' · ');
}
// The details card's text, one line each: "BP-001 · Region III Office · FY 2026", "P-100 — Core Shelter › PJ-1 — Resettlement", the
// particulars, and — when approved revisions have changed it — "Approved amount ₱ … · Latest adjustment ±₱ …".
function brv_lineSub(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    if (!id) return 'Filed before budget lines could be amended — pick its budget line when editing it';
    const fy = r[pre + 'fiscal_yr'];
    if (fy === null || fy === undefined) return 'This budget line no longer exists';
    const withName = (code, name) => (code ? code + (name ? ' — ' + name : '') : '');
    const office = r[pre + 'rc_name'] || r[pre + 'rc_code'];
    const adj = Number(r[pre + 'line_adjustment'] || 0);
    const latest = Number(r[pre + 'line_latest'] || 0);
    const held = Number(r[pre + 'line_held'] || 0);
    return [
        `${r[pre + 'budget_reference_number']}${office ? ' · ' + office : ''} · FY ${fy}`,
        [withName(r[pre + 'program_code'], r[pre + 'program_name']), withName(r[pre + 'project_code'], r[pre + 'project_name'])].filter(Boolean).join(' › ') || 'No project yet',
        r[pre + 'particulars'] || '',
        // as on the form's card: the approved amount is the line's suggested_amount (a revision no longer changes it), the revision is its Revision Amount from the
        // ledger (line_adjustment), the revised amount the two added together, and what CBAs hold against it (line_held, the earmark table) comes off to give its
        // Current balance (line_amount) — the remaining balance
        // the LATEST adjustment (line_latest = budget_line_items.adjustment: − on the From line of a realignment, + on the To line); the revised amount is net of ALL of them
        adj !== 0 ? `Approved amount ${brv_fmtMoney(r[pre + 'suggested_amount'])} · Latest adjustment ${brv_fmtChange(latest)}` : '',
        adj !== 0 ? `Revised amount ${brv_fmtMoney(r[pre + 'line_revised'])}${Math.abs(latest - adj) > 0.004 ? ` (net of all adjustments ${brv_fmtChange(adj)})` : ''}` : '',
        held !== 0 ? `Less CBA Requested ${brv_fmtMoney(held)}` : '',
    ].filter(Boolean).join('\n');
}
// A budget request's status pill in the status's own colour (settings_status.color); only a plain #RRGGBB colour is put into a
// style, anything else (or no status) falls back to a grey pill.
function brv_paintStatus(el, name, color) {
    el.textContent = name || '—';
    if (name && /^#[0-9A-Fa-f]{6}$/.test(color || '')) {
        el.className = 'nha-bdg';
        el.style.cssText = `color:${color};background:${color}1A;border:1px solid ${color}40;`;
    } else {
        el.className = 'nha-bdg nha-bdg-gray';
        el.style.cssText = '';
    }
}
// The inline style of a status pill in the status's own colour (settings_status.color); '' unless it is a plain #RRGGBB colour —
// nothing else is ever put into a style attribute.
function brv_statusStyle(color) {
    return /^#[0-9A-Fa-f]{6}$/.test(color || '') ? `color:${color};background:${color}1A;border:1px solid ${color}40;` : '';
}
// A revision's status pill: its settings_status name and colour (status_name / status_color), falling back to BRV_STATUS's label and
// class when the row has no usable colour or the API did not send one.
function brv_statusBadge(r) {
    const known = BRV_STATUS[r.status];
    const label = r.status_name || (known ? known.label : (r.status || 'Unknown'));
    const style = brv_statusStyle(r.status_color);
    return style
        ? `<span class="nha-bdg" style="${style}">${brv_esc(label)}</span>`
        : `<span class="nha-bdg ${known ? known.cls : 'nha-bdg-gray'}">${brv_esc(label)}</span>`;
}
// HTML-escapes text for markup AND for attribute values (the Reason's tooltip is one): innerHTML leaves quotes alone, so
// they are escaped here — otherwise a reason containing a double quote could close the attribute and inject its own.
function brv_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function brv_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    const icons  = {
        success: '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
        error:   '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
        warning: '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        info:    '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const color = colors[type] || colors.info;
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    t.innerHTML = `<span style="color:${color};flex-shrink:0;margin-top:1px">${icons[type] || icons.info}</span><div style="flex:1"><span>${brv_esc(msg)}</span></div><button onclick="this.parentElement.remove()" style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
