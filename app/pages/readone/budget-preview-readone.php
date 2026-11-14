<?php
/**
 * budget-preview-readone.php
 *
 * Read-only "Budget Preview" modal for a budget request — the read-only counterpart of the Budget Processing
 * modal (form/budget-processing-form.php): the same columns, but laid out per Project (the Processing modal has
 * since moved to Program cards with a meta line, Subtotals and a Grand Total; this one has not). Header: "Submission Ref. No. <ref>" with the
 * request's status badge. Then, for every Project of the request, one block:
 *   - an info card: Originating Unit / Region (the requesting office), Program, Sub-Program, Project
 *     (each with its code as a chip), and
 *   - that Project's line items as a table, as plain text (nothing to fill in):
 *       Type of Fund · Component · Account Code · Description (the account name,
 *       the line's particulars under it) · Amount (proposed) · Adjustment (budget_line_items.adjustment, signed: green
 *       "+", red "−", a dash for none) · Suggested Amount, with a Total row under all three amount columns.
 *   - under that table, the Project's Supporting Documents — the same read-only section as the Budget Processing modal:
 *     the documents the originating office attached to THAT Project (fims.budget_req_project_attachment: an uploaded file or a
 *     pasted link, keyed by the Program and the project code), one line each: the name (a link that opens the document),
 *     File / Link, the date and "Open ↗". They are not part of the list data (opening a file needs a freshly signed URL), so
 *     when the modal opens each Project asks for its own (GET /budget_req_project_attachment/list?budget_req_program_id=…&project_code=…,
 *     all in parallel); the table is usable straight away and each section says "Loading…" until its answer comes. A section
 *     whose request failed says so and offers Retry. A link is only opened when it is a web address (http / https) — the server
 *     stores a pasted link as typed, so anything else is shown as text, never as a clickable link.
 * A Program that has no Projects yet gets its card and a "No projects yet." note.
 * (There used to be Program Sources / Program Uses columns — budget_line_items.program_source_code / program_uses
 * were dropped, migration alter_table/22.)
 *
 * Trigger: bpv_openModal(requestContext, programs) — called from the list pages' "Budget Preview" row action:
 *   requestContext = { budget_reference_number, fiscal_yr, status_name, status_color, rc_id, rc_code, rc_name, can_decide, review }
 *                    (the rc_* fields are the requesting office, review = the row's review-chain info; both from the list row)
 *   programs       = ONE program or an ARRAY of them, each
 *                    { program_code, program_name, sub_program_code, sub_program_name, projects[] }
 *                    (each project has project_code, project_name, line_items[] carrying type_of_fund_name and
 *                    component_name, straight from the list data; the program's budget_req_program_id is what a Project's
 *                    Supporting Documents are looked up by)
 * Takes already-fetched request data; the only thing it looks up is each Project's Supporting Documents.
 *
 * Footer (Return / Approve and the REMARKS box are hidden unless requestContext.can_decide is true — the list
 * page sets it for the Budget Head on an Endorsed request, from the row's review.can_decide; without them the footer is
 * just Close). This is the LAST step of the review chain (BudgetReqService::REVIEW_STEPS): For Review -> Budget Officer ->
 * Endorsed -> Budget Head (this modal) -> Approved.
 *   Return  — needs REMARKS (the box above the footer; required): PUT /budget_req/return/{id} { remarks }:
 *             ONE step back — status -> "Returned to Budget Officer" (the Officer sees it in Budget Processing again, with
 *             these remarks, and can endorse it again or return it to the originating office); remarks saved on
 *             fims.budget_req.remarks.
 *   Approve — PUT /budget_req/approve/{id}: status -> "Approved" (only an Endorsed request can be approved, and only by the
 *             Budget Head; the server enforces both). The remarks box is not used.
 * Status lives on the request header, so both apply to the whole request. After either one it calls
 * window.bgr_afterSave() so the list reloads.
 *
 * Included directly into budget-req-page.php / budget-prop-page.php (inside #bgr-root, so the .nha-root-scoped
 * modal styles apply). Depends on bgr_esc, bgr_escHtmlAttr, bgr_fmtMoney, bgr_statusBadge and bgr_toast from
 * form/budget-req-form.php, which must be included on the same page.
 */
$_bpv_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
/* ── Modal header: "Submission Ref. No. <ref>" + status ── */
.nha-root #bpv-overlay .bpv-ref {
    margin-left: 8px; font-family: var(--mono); font-weight: 600; letter-spacing: .02em;
    color: color-mix(in srgb, var(--afims-primary, #0035A9) 40%, #FFFFFF);
}
.nha-root #bpv-overlay .bpv-status { margin-left: 12px; vertical-align: 1px; }

/* ── One block per Project: info card + line-items table ── */
.nha-root .bpv-block { margin-bottom: 20px; }
.nha-root .bpv-info {
    display: grid; gap: 9px; padding: 15px 18px;
    background: var(--surf); border: 1px solid var(--bdr); border-radius: 12px;
}
.nha-root .bpv-info-row { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 12px; align-items: baseline; font-size: 13px; }
.nha-root .bpv-info-k { font-weight: 600; color: var(--sec); }
.nha-root .bpv-info-v { color: var(--txt); overflow-wrap: anywhere; }
.nha-root .bpv-info-v strong { font-weight: 700; }
.nha-root .bpv-muted { color: var(--subtle); }
.nha-root .bpv-chip {
    display: inline-block; margin-right: 1px; padding: 2px 7px; border-radius: 4px; vertical-align: 1px;
    font: 600 11px/1.4 var(--mono); color: var(--blue-txt, #1D4ED8);
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--navy-bdr, #E6EBF6);
}

.nha-root .bpv-tbl-wrap { margin-top: 14px; border: 1px solid var(--bdr); border-radius: 12px; overflow: hidden; }
.nha-root .bpv-tbl td { padding: 10px 12px; vertical-align: middle; }
.nha-root .bpv-tbl td.bpv-nowrap { white-space: nowrap; }
.nha-root .bpv-tbl td.bpv-mono { font-family: var(--mono); font-size: 12px; }
.nha-root .bpv-tbl td.bpv-amt { white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; }
.nha-root .bpv-tbl .bpv-adj-up   { color: var(--green-txt, #16A34A); }
.nha-root .bpv-tbl .bpv-adj-down { color: var(--red-txt, #DC2626); }
.nha-root .bpv-desc-sub { margin-top: 2px; font-size: 11.5px; color: var(--subtle); }
.nha-root .bpv-tbl tfoot td { padding: 11px 12px; font-weight: 700; background: var(--surf); border-top: 1px solid var(--bdr); }
.nha-root .bpv-empty, .nha-root .bpv-noproj { padding: 18px 12px; text-align: center; font-size: 12.5px; color: var(--subtle); }
.nha-root .bpv-noproj { margin-top: 14px; border: 1px solid var(--bdr); border-radius: 12px; font-style: italic; }

/* ── Supporting Documents of a Project (under its table): one line per document ── */
.nha-root .bpv-docs { margin-top: 14px; }
.nha-root .bpv-docs-hd {
    display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
    font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--sec);
}
.nha-root .bpv-docs-n {
    min-width: 18px; padding: 1px 6px; border-radius: 10px; text-align: center; letter-spacing: 0;
    font-size: 11px; color: var(--sec); background: var(--surf); border: 1px solid var(--bdr);
}
.nha-root .bpv-doc-list { margin: 0; padding: 0; list-style: none; border: 1px solid var(--bdr); border-radius: 10px; overflow: hidden; }
.nha-root .bpv-doc { display: flex; align-items: center; gap: 10px; padding: 9px 12px; font-size: 13px; color: var(--txt); background: var(--afims-surface, #FFFFFF); }
.nha-root .bpv-doc + .bpv-doc { border-top: 1px solid var(--bdr); }
.nha-root .bpv-doc-ico { width: 16px; height: 16px; flex-shrink: 0; color: var(--subtle); }
.nha-root .bpv-doc-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
.nha-root a.bpv-doc-link { color: var(--afims-primary-text, #0035A9); text-decoration: none; }
.nha-root a.bpv-doc-link:hover { text-decoration: underline; }
.nha-root a.bpv-doc-link:focus-visible, .nha-root .bpv-doc-open:focus-visible { outline: 2px solid var(--afims-primary, #0035A9); outline-offset: 2px; border-radius: 3px; }
.nha-root .bpv-doc-kind {
    flex-shrink: 0; padding: 1px 7px; border-radius: 4px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    color: var(--sec); background: var(--surf); border: 1px solid var(--bdr);
}
.nha-root .bpv-doc-date { flex-shrink: 0; font-size: 11.5px; color: var(--subtle); white-space: nowrap; }
.nha-root .bpv-doc-open { flex-shrink: 0; font-size: 12px; font-weight: 600; white-space: nowrap; text-decoration: none; color: var(--afims-heading-fill, #000E3E); }
.nha-root .bpv-doc-open:hover { text-decoration: underline; }
.nha-root .bpv-doc-na { flex-shrink: 0; max-width: 40%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; font-style: italic; color: var(--subtle); }
.nha-root .bpv-docs-note {
    padding: 10px 12px; font-size: 12.5px; font-style: italic; color: var(--subtle);
    background: var(--surf); border: 1px dashed var(--bdr); border-radius: 10px;
}
.nha-root .bpv-docs-err { font-style: normal; color: var(--red-txt, #8F222D); background: var(--red-bg, #FAE1E3); border-color: transparent; }
.nha-root .bpv-docs-retry {
    margin-left: 6px; padding: 0; border: 0; background: none; cursor: pointer; font: inherit; font-weight: 700;
    text-decoration: underline; color: inherit;
}
@media (max-width: 640px) { .nha-root .bpv-doc-date, .nha-root .bpv-doc-kind { display: none; } }

/* ── Remarks (only when the user can decide) ── */
.nha-root .bpv-remarks-label {
    display: block; margin: 4px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .04em;
    text-transform: uppercase; color: var(--sec);
}
.nha-root .bpv-remarks {
    display: block; width: 100%; min-height: 78px; padding: 10px 12px; box-sizing: border-box;
    font: inherit; font-size: 13px; color: var(--txt); resize: vertical;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 8px;
}
.nha-root .bpv-remarks:focus { outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15, 32, 69, .08); }
.nha-root .bpv-remarks.is-invalid { border-color: var(--red, #DC3545); box-shadow: 0 0 0 3px rgba(220, 53, 69, .15); }
.nha-root .bpv-remarks-wrap[hidden] { display: none; }

.nha-root .bpv-err {
    margin-top: 12px; padding: 8px 10px; font-size: 12.5px; border-radius: var(--rs, 7px);
    background: var(--red-bg, #FAE1E3); color: var(--red-txt, #8F222D);
}
.nha-root .bpv-err[hidden] { display: none; }

/* ── Footer: Return (outlined) / Approve (filled) — or just Close ── */
.nha-root .nha-modal-foot .nha-btn[hidden], .nha-root .nha-modal-foot .nha-btn-ghost[hidden] { display: none; }
.nha-root .nha-modal-foot .nha-btn[disabled] { opacity: .6; cursor: not-allowed; }
.nha-root .nha-modal-foot .bpv-btn-return {
    background: var(--afims-surface, #FFFFFF); color: #B45309; border: 1px solid #F59E0B;
}
.nha-root .nha-modal-foot .bpv-btn-return:hover:not([disabled]) { background: #FFF8EB; }
.nha-root .nha-modal-foot .bpv-btn-approve {
    background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF);
    border: 1px solid var(--afims-heading-fill, #000E3E);
}
.nha-root .nha-modal-foot .bpv-btn-approve:hover:not([disabled]) { opacity: .92; }

@media (max-width: 640px) {
    .nha-root .bpv-info-row { grid-template-columns: 1fr; gap: 2px; }
}
</style>

<!-- ── BUDGET PREVIEW MODAL ──────────────────────────────── -->
<div id="bpv-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="bpv-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="bpv-title">Submission Ref. No. <span class="bpv-ref" id="bpv-ref"></span><span class="bpv-status" id="bpv-status"></span></h3>
            <button type="button" class="nha-modal-close" id="bpv-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body" id="bpv-body">

            <!-- one block per Project: info card + its table of line items (read-only) -->
            <div id="bpv-blocks"></div>

            <div class="bpv-remarks-wrap" id="bpv-remarks-wrap" hidden>
                <label class="bpv-remarks-label" for="bpv-remarks">Remarks / Compliance Needed (required when returning)</label>
                <textarea class="bpv-remarks" id="bpv-remarks" rows="3" maxlength="2000"
                          placeholder="e.g. missing supporting documents, suggested proposal amount"></textarea>
            </div>

            <div class="bpv-err" id="bpv-err" hidden></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn-ghost" id="bpv-cancel" hidden>Close</button>
            <button type="button" class="nha-btn bpv-btn-return" id="bpv-return" hidden>Return</button>
            <button type="button" class="nha-btn bpv-btn-approve" id="bpv-approve" hidden>Approve</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET PREVIEW MODAL   prefix: bpv_
   Depends on bgr_esc / bgr_escHtmlAttr / bgr_fmtMoney / bgr_statusBadge /
   bgr_toast from form/budget-req-form.php, included on the same page.
   Takes already-fetched request data; only each Project's Supporting Documents are looked up. Return / Approve call the API.
═══════════════════════════════════════════════════════════════ */

const BPV_BUDGET_REQ_API = '<?php echo $_bpv_api; ?>' + '/budget_req';
const BPV_ATTACHMENT_API = '<?php echo $_bpv_api; ?>' + '/budget_req_project_attachment';   // a Project's Supporting Documents

let bpv_ctx = null;       // requestContext of the modal that is currently open
let bpv_renderSeq = 0;    // bumped by every render and every close: a Supporting Documents answer that arrives after that is dropped

const $bpv = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bpv('bpv-close')?.addEventListener('click', bpv_closeModal);
    $bpv('bpv-cancel')?.addEventListener('click', bpv_closeModal);
    $bpv('bpv-return')?.addEventListener('click', () => bpv_decide('return'));
    $bpv('bpv-approve')?.addEventListener('click', () => bpv_decide('approve'));

    $bpv('bpv-remarks')?.addEventListener('input', e => e.target.classList.remove('is-invalid'));

    // "Retry" in a Project's Supporting Documents section whose request failed
    $bpv('bpv-blocks')?.addEventListener('click', e => {
        const retry = e.target.closest('.bpv-docs-retry');
        if (retry) bpv_loadDocsFor(retry.closest('.bpv-docs'), bpv_renderSeq);
    });
});

// `programs` is one program or an array of them (see the header comment).
async function bpv_openModal(requestContext, programs) {
    bpv_ctx = requestContext;
    bpv_render(requestContext, programs);

    // Return / Approve (and the remarks they need) only appear when the list page says this user may decide;
    // otherwise the footer is just Close.
    const canDecide = !!requestContext.can_decide;
    $bpv('bpv-return').hidden       = !canDecide;
    $bpv('bpv-approve').hidden      = !canDecide;
    $bpv('bpv-cancel').hidden       = canDecide;
    $bpv('bpv-remarks-wrap').hidden = !canDecide;
    $bpv('bpv-remarks').value = '';
    $bpv('bpv-remarks').classList.remove('is-invalid');
    bpv_setBusy(false);
    $bpv('bpv-err').hidden = true;

    $bpv('bpv-overlay').classList.add('open');
}

function bpv_setBusy(busy) {
    $bpv('bpv-return').disabled  = busy;
    $bpv('bpv-approve').disabled = busy;
}

function bpv_showErr(msg) {
    $bpv('bpv-err').textContent = msg;
    $bpv('bpv-err').hidden = false;
}

async function bpv_decide(action) {
    $bpv('bpv-err').hidden = true;
    const init = { method: 'PUT', credentials: 'same-origin' };

    if (action === 'approve') {
        if (!confirm(`Approve budget request ${bpv_ctx.budget_reference_number}? Its status will change to Approved.`)) return;
    } else {
        // Returning needs a reason: the REMARKS box above the footer is required.
        const box = $bpv('bpv-remarks');
        const remarks = box.value.trim();
        // One step back: the Budget Head's Return goes to the Budget Officer (review.return_actor), who can fix it and endorse it again.
        const returnTo = bpv_ctx.review?.return_actor ? `the ${bpv_ctx.review.return_actor}` : 'the originating office';
        if (remarks === '') {
            box.classList.add('is-invalid');
            box.focus();
            bpv_showErr(`Remarks are required when returning a request — tell ${returnTo} what to fix.`);
            return;
        }
        if (!confirm(`Return budget request ${bpv_ctx.budget_reference_number} to ${returnTo}? Your remarks will be sent with it.`)) return;
        init.headers = { 'Content-Type': 'application/json' };
        init.body    = JSON.stringify({ remarks });
    }

    bpv_setBusy(true);
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BPV_BUDGET_REQ_API}/${action}/${encodeURIComponent(bpv_ctx.budget_reference_number)}`, init);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || `Failed to ${action} the budget request.`);

        bgr_toast(json.message, 'success');
        bpv_closeModal();
        window.bgr_afterSave?.();
    } catch (e) {
        bpv_showErr(e.message);
    } finally {
        bpv_setBusy(false);
        window.PagePreloader?.hide();
    }
}

function bpv_closeModal() {
    bpv_renderSeq++;   // documents still on their way belong to a modal nobody is looking at
    $bpv('bpv-overlay').classList.remove('open');
}

/* ── Table ───────────────────────────────────────────────────── */
// A line item that hasn't been through Budget Processing has no Type of Fund / Component yet.
function bpv_value(text) {
    return text ? bgr_esc(text) : '<span class="nha-td-sub">—</span>';
}

// A line's adjustment (budget_line_items.adjustment — what approved budget revisions added to / deducted from the line):
// "+ ₱ 50,000.00" in green, "− ₱ 50,000.00" in red, a dash when there is none. Plain text, never HTML from the data.
function bpv_adjHtml(v) {
    const n = Number(v) || 0;
    if (!n) return '<span class="bpv-muted">—</span>';
    const abs = Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `<span class="${n > 0 ? 'bpv-adj-up' : 'bpv-adj-down'}">${n > 0 ? '+' : '−'} ₱ ${abs}</span>`;
}

// Column order matches Budget Processing, with the line's Adjustment after its Amount:
// Type of Fund · Component · Account Code · Description · Amount · Adjustment · Suggested Amount
const BPV_THEAD = `<thead><tr>
    <th>Type of Fund</th>
    <th>Component</th>
    <th>Account Code</th>
    <th>Description</th>
    <th class="text-right">Amount</th>
    <th class="text-right">Adjustment</th>
    <th class="text-right">Suggested Amount</th>
</tr></thead>`;

function bpv_rowHtml(li) {
    return `<tr>
        <td>${bpv_value(li.type_of_fund_name)}</td>
        <td>${bpv_value(li.component_name)}</td>
        <td class="bpv-nowrap bpv-mono">${bgr_esc(li.account_code)}</td>
        <td><div>${bgr_esc(li.account_title)}</div>${li.particulars ? `<div class="bpv-desc-sub">${bgr_esc(li.particulars)}</div>` : ''}</td>
        <td class="text-right bpv-amt">${bgr_fmtMoney(li.proposed_amount)}</td>
        <td class="text-right bpv-amt bpv-adj">${bpv_adjHtml(li.adjustment)}</td>
        <td class="text-right bpv-amt">${bgr_fmtMoney(li.suggested_amount)}</td>
    </tr>`;
}

function bpv_tableHtml(project) {
    const lineItems = project.line_items ?? [];
    const sum = key => lineItems.reduce((s, li) => s + (Number(li[key]) || 0), 0);
    const rowsHtml = lineItems.length
        ? lineItems.map(bpv_rowHtml).join('')
        : '<tr><td colspan="7"><div class="bpv-empty">No line items have been added under this project.</div></td></tr>';

    return `<div class="bpv-tbl-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl bpv-tbl">${BPV_THEAD}<tbody>${rowsHtml}</tbody>
                <tfoot><tr><td colspan="4" class="text-right">Total</td><td class="text-right bpv-total-p">${bgr_fmtMoney(sum('proposed_amount'))}</td><td class="text-right bpv-total-a">${bpv_adjHtml(sum('adjustment'))}</td><td class="text-right bpv-total-s">${bgr_fmtMoney(sum('suggested_amount'))}</td></tr></tfoot>
            </table>
        </div>
    </div>`;
}

/* ── Info card + block ───────────────────────────────────────── */
// "<chip: code> name" — a dash when there is neither
function bpv_codeNameHtml(code, name) {
    if (!code && !name) return '<span class="bpv-muted">—</span>';
    return (code ? `<span class="bpv-chip">${bgr_esc(code)}</span> ` : '') + bgr_esc(name || '');
}

// The requesting office: its id in bold (e.g. "R07"), its name after it
function bpv_unitHtml(ctx) {
    const id = ctx.rc_id ? String(ctx.rc_id) : (ctx.rc_code ? String(ctx.rc_code) : '');
    if (!id && !ctx.rc_name) return '<span class="bpv-muted">—</span>';
    return `<strong>${bgr_esc(id || ctx.rc_name)}</strong>` + (id && ctx.rc_name ? ` <span class="bpv-muted">${bgr_esc(ctx.rc_name)}</span>` : '');
}

function bpv_cardHtml(ctx, program, project) {
    const row = (label, valueHtml) => `<div class="bpv-info-row"><span class="bpv-info-k">${label}</span><span class="bpv-info-v">${valueHtml}</span></div>`;
    return `<div class="bpv-info">
        ${row('Originating Unit / Region', bpv_unitHtml(ctx))}
        ${row('Program', bpv_codeNameHtml(program.program_code, program.program_name))}
        ${row('Sub-Program', bpv_codeNameHtml(program.sub_program_code, program.sub_program_name))}
        ${row('Project', project ? bpv_codeNameHtml(project.project_code, project.project_name) : '<span class="bpv-muted">—</span>')}
    </div>`;
}

/* ── Supporting Documents of a Project ───────────────────────────────── */
const BPV_ICON_FILE = '<svg class="bpv-doc-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
const BPV_ICON_LINK = '<svg class="bpv-doc-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14a3.5 3.5 0 005 0l4-4a3.5 3.5 0 00-5-5l-.5.5M14 10a3.5 3.5 0 00-5 0l-4 4a3.5 3.5 0 005 5l.5-.5"/></svg>';

// Postgres timestamps arrive as "2026-11-10 08:30:00.123456+08": make that ISO enough for every browser's Date(), then show it
// as "Nov 10, 2026". '' when there is no date.
function bpv_fmtDate(value) {
    if (!value) return '';
    const iso = String(value).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d = new Date(iso);
    return isNaN(d) ? String(value) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

// A pasted link is stored as typed (the server does not check what it is), so only a web address may become a clickable
// link: a javascript: / data: / "file:" link is shown as text instead. '' = not openable.
function bpv_safeUrl(url) {
    const u = String(url ?? '').trim();
    return /^https?:\/\/\S+$/i.test(u) ? u : '';
}

// The section's frame. Its documents are looked up by the Program's id + the project code (that is how they are stored), so a
// Project without either gets no section rather than a false "none attached".
function bpv_docsHtml(program, project) {
    const programId = program.budget_req_program_id;
    if (programId === null || programId === undefined || programId === '' || !project.project_code) return '';
    return `<div class="bpv-docs" data-program-id="${bgr_escHtmlAttr(programId)}" data-project-code="${bgr_escHtmlAttr(project.project_code)}">
        <div class="bpv-docs-hd"><span>Supporting Documents</span><span class="bpv-docs-n" hidden></span></div>
        <div class="bpv-docs-body"><div class="bpv-docs-note">Loading documents…</div></div>
    </div>`;
}

// One attachment: { attachment_id, document_name, storage_key, external_link, uploaded_at, view_url }.
// view_url is a signed link for an uploaded file, the pasted link otherwise.
function bpv_docRowHtml(a) {
    const isFile = !!a.storage_key;
    const name   = a.document_name || (isFile ? 'Document' : (a.external_link || 'Document'));
    const url    = bpv_safeUrl(a.view_url || a.external_link);
    const date   = bpv_fmtDate(a.uploaded_at);
    const href   = bgr_escHtmlAttr(url);
    // the name opens the document too (the same address as "Open ↗"); with no openable address it is plain text
    const nameEl = url
        ? `<a class="bpv-doc-name bpv-doc-link" href="${href}" target="_blank" rel="noopener noreferrer" title="${bgr_escHtmlAttr(name)}">${bgr_esc(name)}</a>`
        : `<span class="bpv-doc-name" title="${bgr_escHtmlAttr(name)}">${bgr_esc(name)}</span>`;
    const open   = url
        ? `<a class="bpv-doc-open" href="${href}" target="_blank" rel="noopener noreferrer" aria-label="Open ${bgr_escHtmlAttr(name)}">Open ↗</a>`
        : (isFile ? '<span class="bpv-doc-na">File unavailable</span>'
                  : `<span class="bpv-doc-na" title="${bgr_escHtmlAttr(a.external_link || '')}">Not a web link: ${bgr_esc(a.external_link || '—')}</span>`);
    return `<li class="bpv-doc">
        ${isFile ? BPV_ICON_FILE : BPV_ICON_LINK}
        ${nameEl}
        <span class="bpv-doc-kind">${isFile ? 'File' : 'Link'}</span>
        ${date ? `<span class="bpv-doc-date">${bgr_esc(date)}</span>` : ''}
        ${open}
    </li>`;
}

function bpv_fillDocs(box, docs) {
    const count = box.querySelector('.bpv-docs-n');
    count.textContent = docs.length;
    count.hidden = docs.length === 0;
    box.querySelector('.bpv-docs-body').innerHTML = docs.length
        ? `<ul class="bpv-doc-list">${docs.map(bpv_docRowHtml).join('')}</ul>`
        : '<div class="bpv-docs-note">No supporting documents were attached to this project.</div>';
}

// Asks for ONE Project's documents. `seq` is the render it was asked for: an answer that arrives after the modal was closed or
// re-opened for another request (or after its section was replaced) is dropped.
async function bpv_loadDocsFor(box, seq) {
    if (!box) return;
    const body  = box.querySelector('.bpv-docs-body');
    const stale = () => seq !== bpv_renderSeq || !box.isConnected;
    body.innerHTML = '<div class="bpv-docs-note">Loading documents…</div>';
    try {
        const params = new URLSearchParams({ budget_req_program_id: box.dataset.programId, project_code: box.dataset.projectCode });
        const res    = await fetch(`${BPV_ATTACHMENT_API}/list?${params}`, { credentials: 'same-origin' });
        const json   = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error((json && json.message) || 'HTTP ' + res.status);
        if (stale()) return;
        bpv_fillDocs(box, Array.isArray(json.data) ? json.data : []);
    } catch (e) {
        if (stale()) return;
        body.innerHTML = `<div class="bpv-docs-note bpv-docs-err">Could not load the supporting documents — ${bgr_esc(e.message)}<button type="button" class="bpv-docs-retry">Retry</button></div>`;
    }
}

// Every Project section of the freshly rendered modal, at once.
function bpv_loadDocs(seq) {
    document.querySelectorAll('#bpv-blocks .bpv-docs').forEach(box => { bpv_loadDocsFor(box, seq); });
}

// One block = the info card + (the Project's table + its Supporting Documents | "No projects yet." when the Program has no Project)
function bpv_blockHtml(ctx, program, project) {
    return `<section class="bpv-block">
        ${bpv_cardHtml(ctx, program, project)}
        ${project ? bpv_tableHtml(project) + bpv_docsHtml(program, project) : '<div class="bpv-noproj">No projects yet.</div>'}
    </section>`;
}

// `programArg`: one program or an array of them
function bpv_render(requestContext, programArg) {
    const programs = Array.isArray(programArg) ? programArg : (programArg ? [programArg] : []);

    $bpv('bpv-ref').textContent = requestContext.budget_reference_number ?? '';
    $bpv('bpv-status').innerHTML = requestContext.status_name ? bgr_statusBadge(requestContext.status_name, requestContext.status_color) : '';

    const blocks = [];
    programs.forEach(program => {
        const projects = program.projects ?? [];
        if (!projects.length) blocks.push(bpv_blockHtml(requestContext, program, null));
        else projects.forEach(project => blocks.push(bpv_blockHtml(requestContext, program, project)));
    });
    $bpv('bpv-blocks').innerHTML = blocks.length ? blocks.join('') : '<div class="bpv-noproj">No programs yet.</div>';

    bpv_loadDocs(++bpv_renderSeq);   // the Projects' Supporting Documents fill in as their answers arrive
}
</script>
