<?php
/**
 * budget-processing-form.php
 *
 * "Budget Processing" modal for a budget request. Header: "Submission Ref. No. <ref>". Then, for every
 * Project of the request, one block:
 *   - an info card: Originating Unit / Region (the requesting office), Program, Sub-Program, Project
 *     (each with its code as a chip), and
 *   - that Project's line items as a table with the processing inputs per column:
 *       Type of Fund (fims.type_of_fund) · Program Sources · Component (fims.component_type) ·
 *       Account Code · Description (the account name, the line's particulars under it) ·
 *       Amount (what the office asked for, read-only) · Suggested Amount (the input),
 *     and a Total row under both amount columns; the Suggested Amount total follows the inputs as they are edited.
 * A Program that has no Projects yet gets its card and a "No projects yet." note.
 * Below the blocks: one REMARKS / COMPLIANCE NEEDED box, then Return / Endorse.
 *
 * Program Sources: a program source IS a program — a SEARCHABLE picker over the rows of fims.program
 * (GET /program/all). Type to filter by name or code, pick from the list (items read "Name — code"; arrow keys +
 * Enter work too); typed text that is not picked is dropped when the box loses focus, and emptying the box clears
 * the choice. The choice is saved on fims.budget_line_items.program_source_code (VARCHAR(64), FK to
 * fims.program.program_code; migration alter_table/17). Optional. The list is one shared element on <body>
 * (position:fixed), because the table's overflow would clip a normal dropdown — the same approach as the Account
 * Code picker of the Budget Request form.
 *
 * Amount is the line's proposed_amount, as the office requested it — read-only.
 * Suggested Amount is fims.budget_line_items.suggested_amount. It starts as a copy of the Amount (a previously
 * saved suggested amount above 0 is kept) and can be edited, or cleared (blank is saved as 0.00).
 *
 * Footer actions:
 *   Return  — needs REMARKS (the box above the footer; required): PUT /budget_req/return/{id} { remarks } sets
 *             the request's status to "Returned" and saves the remarks on fims.budget_req.remarks; the line
 *             items are left untouched.
 *   Endorse — PUT /budget_req/endorse/{id}: Type of Fund and Component are REQUIRED on every line item of
 *             EVERY Project (Program Source and Suggested Amount are optional); saves them to fims.budget_line_items and
 *             sets the request's status to "Endorsed" (one transaction). If something required is missing the
 *             first such field is focused and its block is outlined in red. The remarks box is not used.
 * Status lives on the request header, so Return/Endorse apply to the whole request, and the modal shows every
 * Project of the request it is given.
 *
 * Trigger: bpr_openModal(requestContext, programs) — called from the list pages' "Budget Processing" row
 * action, where:
 *   requestContext = { budget_reference_number, fiscal_yr, status_name, status_color, rc_id, rc_code, rc_name }
 *                    (the rc_* fields are the requesting office, from the list row)
 *   programs       = ONE program or an ARRAY of them, each
 *                    { program_code, program_name, sub_program_code, sub_program_name, projects[] }
 *                    (each project has project_code, project_name, line_items[], straight from the list data)
 * The line items are NOT re-fetched; only the three dropdown option lists are (once, then cached).
 *
 * Included directly into budget-req-page.php / budget-prop-page.php (inside #bgr-root, so the .nha-root-scoped
 * modal styles apply). Depends on bgr_esc, bgr_escHtmlAttr, bgr_fmtMoney, bgr_fmtAmount, bgr_fmtAmountInitial,
 * bgr_unformatAmount and bgr_toast from form/budget-req-form.php, which must be included on the same page.
 * After Return/Endorse it calls window.bgr_afterSave() so the list reloads.
 */
$_bpr_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
/* ── Modal header: "Submission Ref. No. <ref>" ── */
.nha-root #bpr-overlay .bpr-ref {
    margin-left: 8px; font-family: var(--mono); font-weight: 600; letter-spacing: .02em;
    color: color-mix(in srgb, var(--afims-primary, #0035A9) 40%, #FFFFFF);
}

/* ── One block per Project: info card + line-items table ── */
.nha-root .bpr-block { margin-bottom: 20px; }
.nha-root .bpr-info {
    display: grid; gap: 9px; padding: 15px 18px;
    background: var(--surf); border: 1px solid var(--bdr); border-radius: 12px;
}
.nha-root .bpr-info-row { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 12px; align-items: baseline; font-size: 13px; }
.nha-root .bpr-info-k { font-weight: 600; color: var(--sec); }
.nha-root .bpr-info-v { color: var(--txt); overflow-wrap: anywhere; }
.nha-root .bpr-info-v strong { font-weight: 700; }
.nha-root .bpr-muted { color: var(--subtle); }
.nha-root .bpr-chip {
    display: inline-block; margin-right: 1px; padding: 2px 7px; border-radius: 4px; vertical-align: 1px;
    font: 600 11px/1.4 var(--mono); color: var(--blue-txt, #1D4ED8);
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--navy-bdr, #E6EBF6);
}

.nha-root .bpr-tbl-wrap { margin-top: 14px; border: 1px solid var(--bdr); border-radius: 12px; overflow: hidden; }
.nha-root .bpr-block.has-error .bpr-tbl-wrap { border-color: var(--red, #DC3545); box-shadow: 0 0 0 3px rgba(220, 53, 69, .12); }
.nha-root .bpr-tbl td { padding: 10px 12px; vertical-align: middle; }
.nha-root .bpr-tbl .bpr-col-select { min-width: 150px; }
.nha-root .bpr-tbl .bpr-col-amount { min-width: 150px; }
.nha-root .bpr-tbl select.bpr-field { width: 100%; max-width: 210px; text-overflow: ellipsis; }
.nha-root .bpr-tbl .bpr-src-input { width: 100%; max-width: 210px; text-overflow: ellipsis; }
.nha-root .bpr-tbl td.bpr-nowrap { white-space: nowrap; }
.nha-root .bpr-tbl td.bpr-mono { font-family: var(--mono); font-size: 12px; }
.nha-root .bpr-desc-sub { margin-top: 2px; font-size: 11.5px; color: var(--subtle); }
.nha-root .bpr-tbl td.bpr-proposed { white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; }
.nha-root .bpr-tbl input.bpr-suggested { width: 100%; min-width: 140px; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
.nha-root .bpr-tbl tfoot td { padding: 11px 12px; font-weight: 700; background: var(--surf); border-top: 1px solid var(--bdr); }
.nha-root .bpr-req { color: #FF8A8A; margin-left: 2px; }
.nha-root .bpr-empty, .nha-root .bpr-noproj { padding: 18px 12px; text-align: center; font-size: 12.5px; color: var(--subtle); }
.nha-root .bpr-noproj { margin-top: 14px; border: 1px solid var(--bdr); border-radius: 12px; font-style: italic; }

/* a required dropdown left empty (or an invalid amount) when Endorse is clicked */
.nha-root .bpr-field.is-invalid, .nha-root .bpr-remarks.is-invalid {
    border-color: var(--red, #DC3545);
    box-shadow: 0 0 0 3px rgba(220, 53, 69, .15);
}

/* ── Remarks ── */
.nha-root .bpr-remarks-label {
    display: block; margin: 4px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .04em;
    text-transform: uppercase; color: var(--sec);
}
.nha-root .bpr-remarks {
    display: block; width: 100%; min-height: 78px; padding: 10px 12px; box-sizing: border-box;
    font: inherit; font-size: 13px; color: var(--txt); resize: vertical;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 8px;
}
.nha-root .bpr-remarks:focus { outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15, 32, 69, .08); }

.nha-root .bpr-err {
    margin-top: 12px; padding: 8px 10px; font-size: 12.5px; border-radius: var(--rs, 7px);
    background: var(--red-bg, #FAE1E3); color: var(--red-txt, #8F222D);
}
.nha-root .bpr-err[hidden] { display: none; }

/* ── Footer: Return (outlined) / Endorse (filled) ── */
.nha-root .nha-modal-foot .nha-btn[disabled] { opacity: .6; cursor: not-allowed; }
.nha-root .nha-modal-foot .bpr-btn-return {
    background: var(--afims-surface, #FFFFFF); color: #B45309; border: 1px solid #F59E0B;
}
.nha-root .nha-modal-foot .bpr-btn-return:hover:not([disabled]) { background: #FFF8EB; }
.nha-root .nha-modal-foot .bpr-btn-endorse {
    background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF);
    border: 1px solid var(--afims-heading-fill, #000E3E);
}
.nha-root .nha-modal-foot .bpr-btn-endorse:hover:not([disabled]) { opacity: .92; }

@media (max-width: 640px) {
    .nha-root .bpr-info-row { grid-template-columns: 1fr; gap: 2px; }
}

/* ── Program Sources picker list. NOT scoped under .nha-root: it is appended to <body> (see the script), above the
      modal overlay, so it only uses the global --afims-* tokens. ── */
.bpr-src-menu {
    display: none; position: fixed; z-index: 10000; max-height: 260px; overflow-x: hidden; overflow-y: auto; padding: 4px 0;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .14); font-family: 'IBM Plex Sans', sans-serif;
}
.bpr-src-menu.open { display: block; }
.bpr-src-option { padding: 8px 12px; font-size: 13px; line-height: 1.4; color: var(--afims-text, #1A1B21); cursor: pointer; }
.bpr-src-option:hover, .bpr-src-option.active { background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF); }
.bpr-src-option.selected { font-weight: 700; }
.bpr-src-empty, .bpr-src-hint { padding: 8px 12px; font-size: 12px; font-style: italic; color: var(--afims-text-disabled, #A9AAB8); }
</style>

<!-- ── BUDGET PROCESSING MODAL ───────────────────────────── -->
<div id="bpr-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="bpr-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="bpr-title">Submission Ref. No. <span class="bpr-ref" id="bpr-ref"></span></h3>
            <button type="button" class="nha-modal-close" id="bpr-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body" id="bpr-body">

            <!-- one block per Project: info card + its table of line items (Type of Fund + Component are required to Endorse) -->
            <div id="bpr-blocks"></div>

            <label class="bpr-remarks-label" for="bpr-remarks">Remarks / Compliance Needed (required when returning)</label>
            <textarea class="bpr-remarks" id="bpr-remarks" rows="3" maxlength="2000"
                      placeholder="e.g. missing supporting documents, suggested proposal amount"></textarea>

            <div class="bpr-err" id="bpr-err" hidden></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn bpr-btn-return" id="bpr-return">Return</button>
            <button type="button" class="nha-btn bpr-btn-endorse" id="bpr-endorse">Endorse</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET PROCESSING MODAL   prefix: bpr_
   Depends on bgr_esc / bgr_escHtmlAttr / bgr_fmtMoney / bgr_fmtAmount /
   bgr_fmtAmountInitial / bgr_unformatAmount / bgr_toast
   from form/budget-req-form.php, included on the same page. The line items
   come from the already-fetched list data; only the Type of Fund / Program
   Source / Component option lists are fetched (once, then cached).
═══════════════════════════════════════════════════════════════ */

const BPR_TYPE_OF_FUND_API  = '<?php echo $_bpr_api; ?>' + '/type_of_fund/all';
const BPR_COMPONENT_API     = '<?php echo $_bpr_api; ?>' + '/component_type/all';
const BPR_PROGRAM_API       = '<?php echo $_bpr_api; ?>' + '/program/all';       // the program sources: every program
const BPR_BUDGET_REQ_API    = '<?php echo $_bpr_api; ?>' + '/budget_req';

let BPR_FUNDS = null;       // [{ type_of_fund_id, name }]       — null until loaded
let BPR_COMPONENTS = null;  // [{ component_type_id, name }]     — null until loaded
let BPR_SOURCES = null;     // [{ program_code, program_name }]  — null until loaded

let bpr_ctx = null;         // requestContext of the modal that is currently open
let bpr_rowCount = 0;       // number of line-item rows rendered, across all blocks

const $bpr = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bpr('bpr-close')?.addEventListener('click', bpr_closeModal);
    $bpr('bpr-return')?.addEventListener('click', bpr_return);
    $bpr('bpr-endorse')?.addEventListener('click', bpr_endorse);
    bpr_srcInit();

    $bpr('bpr-body')?.addEventListener('input', e => {
        const el = e.target;

        // the Suggested Amount total under a table follows its inputs as they are typed
        if (el.matches('.bpr-suggested')) bpr_updateTotal(el.closest('table'));

        // a field that was flagged as missing stops looking invalid once filled; its block loses the
        // red outline once nothing is left to fix in it
        if (el.matches('.bpr-field')) {
            el.classList.remove('is-invalid');
            const block = el.closest('.bpr-block');
            if (block && !block.querySelector('.is-invalid')) block.classList.remove('has-error');
        }
        if (el.matches('.bpr-remarks')) el.classList.remove('is-invalid');
    });
});

async function bpr_loadOptions() {
    if (BPR_FUNDS && BPR_COMPONENTS && BPR_SOURCES) return;

    const responses = await Promise.all([
        fetch(BPR_TYPE_OF_FUND_API, { credentials: 'same-origin' }),
        fetch(BPR_COMPONENT_API,    { credentials: 'same-origin' }),
        fetch(BPR_PROGRAM_API,      { credentials: 'same-origin' }),
    ]);
    if (responses.some(r => !r.ok)) throw new Error('Failed to load the Type of Fund / Program Sources / Component lists.');

    const [funds, components, programs] = await Promise.all(responses.map(r => r.json()));
    if (!funds.success)      throw new Error(funds.message || 'Failed to load types of fund.');
    if (!components.success) throw new Error(components.message || 'Failed to load components.');
    if (!programs.success)   throw new Error(programs.message || 'Failed to load the program sources.');

    BPR_FUNDS      = funds.data ?? [];
    BPR_COMPONENTS = components.data ?? [];
    BPR_SOURCES    = programs.data ?? [];
}

// `programs` is one program or an array of them (see the header comment).
async function bpr_openModal(requestContext, programs) {
    window.PagePreloader?.show();
    try {
        await bpr_loadOptions();
    } catch (e) {
        bgr_toast(e.message, 'error');
        return;
    } finally {
        window.PagePreloader?.hide();
    }

    bpr_ctx = requestContext;
    bpr_render(requestContext, programs);
    bpr_hideErr();
    $bpr('bpr-remarks').value = '';
    $bpr('bpr-remarks').classList.remove('is-invalid');
    bpr_updateButtons(false);
    $bpr('bpr-overlay').classList.add('open');
}

function bpr_closeModal() {
    bpr_srcClose();
    $bpr('bpr-overlay').classList.remove('open');
}

/* ── Dropdowns ───────────────────────────────────────────────── */
function bpr_optionsHtml(options, idKey, selectedId) {
    const sel = selectedId === null || selectedId === undefined ? '' : String(selectedId);
    return '<option value="">— Select —</option>' + options.map(o =>
        `<option value="${Number(o[idKey])}"${String(o[idKey]) === sel ? ' selected' : ''}>${bgr_esc(o.name)}</option>`
    ).join('');
}

/* ── Program Sources: a searchable picker ────────────────────────────────────────────────────────────────
   Program Sources are programs: the stored value is the program_code (a string), the label "Name — code".
   Each row holds a visible search input (.bpr-src-input, carrying the text of the current choice in
   data-label) and a hidden .bpr-source with the program_code that Endorse sends.
   ONE shared list (.bpr-src-menu) serves every row. It lives on <body> and is position:fixed because the
   table's overflow would clip a normal dropdown, and it is positioned by hand under (or, when there is no room
   below, over) whichever input it is open for. Because it is one element — not one per row — re-rendering the
   table leaves nothing behind to clean up. Events are delegated from #bpr-blocks for the same reason. */
const BPR_SRC_MAX = 50;       // most items listed at once; typing narrows the rest down
let bpr_srcMenu = null;       // the shared list element (created on first use)
let bpr_srcInput = null;      // the input the list is currently open for
let bpr_srcMatches = [];      // the programs currently listed
let bpr_srcActive = -1;       // index of the highlighted item (-1 = none)

const bpr_sourceLabel = p => `${p.program_name} — ${p.program_code}`;

// The label to show for a saved code: its program's "Name — code"; the bare code if that program is gone.
function bpr_sourceLabelFor(code) {
    if (code === null || code === undefined || code === '') return '';
    const p = BPR_SOURCES.find(x => String(x.program_code) === String(code));
    return p ? bpr_sourceLabel(p) : String(code);
}

function bpr_srcEnsureMenu() {
    if (bpr_srcMenu) return bpr_srcMenu;
    bpr_srcMenu = document.createElement('div');
    bpr_srcMenu.className = 'bpr-src-menu';
    bpr_srcMenu.setAttribute('role', 'listbox');
    document.body.appendChild(bpr_srcMenu);

    // mousedown, not click: a click would first blur the input (nothing in the list takes focus), which closes the
    // list from under the click. preventDefault() keeps focus in the input, so the pick always registers.
    bpr_srcMenu.addEventListener('mousedown', ev => {
        ev.preventDefault();
        const opt = ev.target.closest('.bpr-src-option');
        if (opt && bpr_srcInput) bpr_srcChoose(bpr_srcInput, opt.dataset.code);
    });
    return bpr_srcMenu;
}

function bpr_srcIsOpen() {
    return !!bpr_srcMenu && bpr_srcMenu.classList.contains('open');
}

// Lists the programs matching `query` (name or code, any case) for the current input.
function bpr_srcRender(query) {
    const q = query.trim().toLowerCase();
    const all = BPR_SOURCES.filter(p => !q
        || String(p.program_code).toLowerCase().includes(q)
        || String(p.program_name).toLowerCase().includes(q));
    bpr_srcMatches = all.slice(0, BPR_SRC_MAX);
    bpr_srcActive  = q && bpr_srcMatches.length ? 0 : -1;   // once something is typed, Enter takes the best match

    const chosen = bpr_srcInput.closest('.bpr-src-wrap').querySelector('.bpr-source').value;
    bpr_srcMenu.innerHTML = bpr_srcMatches.length
        ? bpr_srcMatches.map((p, i) =>
            `<div class="bpr-src-option${i === bpr_srcActive ? ' active' : ''}${String(p.program_code) === chosen ? ' selected' : ''}" role="option" data-code="${bgr_escHtmlAttr(p.program_code)}">${bgr_esc(bpr_sourceLabel(p))}</div>`
          ).join('') + (all.length > BPR_SRC_MAX ? `<div class="bpr-src-hint">Showing the first ${BPR_SRC_MAX} of ${all.length} — type to narrow down.</div>` : '')
        : '<div class="bpr-src-empty">No matching program.</div>';
}

// Puts the list under the input — or over it when there is more room above — wide enough to read, kept on screen.
function bpr_srcPosition() {
    if (!bpr_srcInput) return;
    const r      = bpr_srcInput.getBoundingClientRect();
    const width  = Math.min(Math.max(r.width, 340), window.innerWidth - 16);
    const left   = Math.max(8, Math.min(r.left, window.innerWidth - width - 8));
    const below  = window.innerHeight - r.bottom;
    const menu   = bpr_srcMenu;
    menu.style.width = width + 'px';
    menu.style.left  = left + 'px';
    if (below < 200 && r.top > below) {
        menu.style.top       = 'auto';
        menu.style.bottom    = (window.innerHeight - r.top + 4) + 'px';
        menu.style.maxHeight = Math.max(120, Math.min(260, r.top - 12)) + 'px';
    } else {
        menu.style.bottom    = 'auto';
        menu.style.top       = (r.bottom + 4) + 'px';
        menu.style.maxHeight = Math.max(120, Math.min(260, below - 12)) + 'px';
    }
}

// Browsers undo a select()-on-focus with the mouse-up of the same click, which would leave the old label in the box
// and make typing APPEND to it. So the mouse-up that ends the focusing click is cancelled (once; bpr_srcCommit
// removes it if no click came, e.g. when focus arrived by Tab).
const bpr_srcKeepSelection = ev => ev.preventDefault();

// `fresh` = just focused: show EVERY program (not only the current choice) and select the text, so typing replaces it.
function bpr_srcOpen(input, fresh) {
    bpr_srcInput = input;
    bpr_srcEnsureMenu();
    if (fresh) {
        input.select();
        input.addEventListener('mouseup', bpr_srcKeepSelection, { once: true });
    }
    bpr_srcRender(fresh ? '' : input.value);
    bpr_srcPosition();
    bpr_srcMenu.classList.add('open');
    input.setAttribute('aria-expanded', 'true');
}

function bpr_srcClose() {
    if (bpr_srcMenu) bpr_srcMenu.classList.remove('open');
    if (bpr_srcInput) bpr_srcInput.setAttribute('aria-expanded', 'false');
    bpr_srcInput  = null;
    bpr_srcActive = -1;
}

function bpr_srcSetActive(i) {
    const opts = [...bpr_srcMenu.querySelectorAll('.bpr-src-option')];
    if (!opts.length) return;
    bpr_srcActive = Math.max(0, Math.min(opts.length - 1, i));
    opts.forEach((o, k) => o.classList.toggle('active', k === bpr_srcActive));
    opts[bpr_srcActive].scrollIntoView?.({ block: 'nearest' });
}

// Makes `code` this row's program source (an unknown / empty code clears it) and closes the list.
function bpr_srcChoose(input, code) {
    const p     = BPR_SOURCES.find(x => String(x.program_code) === String(code));
    const label = p ? bpr_sourceLabel(p) : '';
    input.closest('.bpr-src-wrap').querySelector('.bpr-source').value = p ? p.program_code : '';
    input.value         = label;
    input.dataset.label = label;
    bpr_srcClose();
}

// The input lost focus. An EMPTY box means "no program source". Text that is exactly an item's label counts as
// picking it. Any other typed text was never picked: it is dropped and the previous choice is shown again.
function bpr_srcCommit(input) {
    input.removeEventListener('mouseup', bpr_srcKeepSelection);
    const text = input.value.trim();
    if (text === '') {
        bpr_srcChoose(input, '');
    } else {
        const exact = BPR_SOURCES.find(p => bpr_sourceLabel(p).toLowerCase() === text.toLowerCase());
        if (exact) bpr_srcChoose(input, exact.program_code);
        else       input.value = input.dataset.label || '';
    }
    if (bpr_srcInput === input) bpr_srcClose();
}

function bpr_srcKey(ev) {
    const input = ev.target;
    const open  = bpr_srcIsOpen() && bpr_srcInput === input;

    if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        if (!open) { bpr_srcOpen(input, true); bpr_srcSetActive(0); return; }
        bpr_srcSetActive(bpr_srcActive + (ev.key === 'ArrowDown' ? 1 : -1));
    } else if (ev.key === 'Enter' && open && bpr_srcActive >= 0) {
        ev.preventDefault();
        bpr_srcChoose(input, bpr_srcMatches[bpr_srcActive].program_code);
    } else if (ev.key === 'Escape' && open) {
        ev.preventDefault();
        ev.stopPropagation();
        input.value = input.dataset.label || '';    // cancel: back to the previous choice
        bpr_srcClose();
    }
}

// Wires the delegated events once. The list follows its input when something scrolls or the window resizes.
function bpr_srcInit() {
    const host = $bpr('bpr-blocks');
    if (!host) return;
    host.addEventListener('focusin',  e => { if (e.target.matches('.bpr-src-input')) bpr_srcOpen(e.target, true); });
    host.addEventListener('input',    e => { if (e.target.matches('.bpr-src-input')) bpr_srcOpen(e.target, false); });
    host.addEventListener('keydown',  e => { if (e.target.matches('.bpr-src-input')) bpr_srcKey(e); });
    host.addEventListener('focusout', e => { if (e.target.matches('.bpr-src-input')) bpr_srcCommit(e.target); });

    // a click on a part of the page that takes no focus doesn't blur the input, so close the list here too
    document.addEventListener('mousedown', e => {
        if (bpr_srcIsOpen() && !bpr_srcMenu.contains(e.target) && e.target !== bpr_srcInput) bpr_srcClose();
    });
    // Something else scrolled (the modal body, the table): the list follows its input — unless the input has scrolled
    // out of the visible part of the modal, when the list closes rather than float over unrelated content.
    // Scrolling the LIST itself must not move or close it.
    document.addEventListener('scroll', e => {
        if (!bpr_srcIsOpen() || bpr_srcMenu.contains(e.target)) return;
        const r = bpr_srcInput.getBoundingClientRect();
        const b = $bpr('bpr-body').getBoundingClientRect();
        if (r.bottom < b.top || r.top > b.bottom) bpr_srcClose();
        else bpr_srcPosition();
    }, true);
    window.addEventListener('resize', () => { if (bpr_srcIsOpen()) bpr_srcPosition(); });
}

/* ── Table ───────────────────────────────────────────────────── */
// Column order: Type of Fund · Program Sources · Component · Account Code · Description · Amount · Suggested Amount
const BPR_THEAD = `<thead><tr>
    <th class="bpr-col-select">Type of Fund<span class="bpr-req">*</span></th>
    <th class="bpr-col-select">Program Sources</th>
    <th class="bpr-col-select">Component<span class="bpr-req">*</span></th>
    <th>Account Code</th>
    <th>Description</th>
    <th class="text-right bpr-col-amount">Amount</th>
    <th class="text-right bpr-col-amount">Suggested Amount</th>
</tr></thead>`;

// The Suggested Amount is optional. It starts as a copy of the Amount (the proposed amount) — unless the line item
// already has a saved suggested amount (above 0), which is kept. Either way it is an ordinary input: it can be
// changed, or cleared (blank is saved as 0.00).
function bpr_initialSuggested(li) {
    return Number(li.suggested_amount || 0) > 0 ? li.suggested_amount : li.proposed_amount;
}

function bpr_rowHtml(li) {
    return `<tr data-li-id="${Number(li.budget_line_item_id)}">
        <td><select class="nha-filter-select bpr-field bpr-fund">${bpr_optionsHtml(BPR_FUNDS, 'type_of_fund_id', li.type_of_fund_id)}</select></td>
        <td><div class="bpr-src-wrap">
            <input type="text" class="nha-filter-input bpr-src-input" autocomplete="off" placeholder="Type to search…"
                   role="combobox" aria-autocomplete="list" aria-expanded="false"
                   value="${bgr_escHtmlAttr(bpr_sourceLabelFor(li.program_source_code))}" data-label="${bgr_escHtmlAttr(bpr_sourceLabelFor(li.program_source_code))}">
            <input type="hidden" class="bpr-source" value="${bgr_escHtmlAttr(li.program_source_code ?? '')}">
        </div></td>
        <td><select class="nha-filter-select bpr-field bpr-component">${bpr_optionsHtml(BPR_COMPONENTS, 'component_type_id', li.component_id)}</select></td>
        <td class="bpr-nowrap bpr-mono">${bgr_esc(li.account_code)}</td>
        <td><div>${bgr_esc(li.account_title)}</div>${li.particulars ? `<div class="bpr-desc-sub">${bgr_esc(li.particulars)}</div>` : ''}</td>
        <td class="text-right bpr-proposed" data-amount="${bgr_escHtmlAttr(li.proposed_amount ?? 0)}">${bgr_fmtMoney(li.proposed_amount)}</td>
        <td class="text-right"><input type="text" inputmode="decimal" autocomplete="off" class="nha-filter-input bpr-field bpr-suggested"
                   placeholder="0.00"
                   value="${bgr_escHtmlAttr(bgr_fmtAmountInitial(bpr_initialSuggested(li)))}"
                   oninput="bgr_fmtAmount(this)"></td>
    </tr>`;
}

function bpr_amountOf(input) {
    return Number(bgr_unformatAmount(input.value)) || 0;
}

// Sets the Total row of one table: the sum of its Amounts (fixed) and of its Suggested Amount inputs (live).
function bpr_updateTotal(table) {
    if (!table) return;
    const proposed  = [...table.querySelectorAll('.bpr-proposed')].reduce((s, el) => s + (Number(el.dataset.amount) || 0), 0);
    const suggested = [...table.querySelectorAll('.bpr-suggested')].reduce((s, el) => s + bpr_amountOf(el), 0);
    table.querySelector('.bpr-total-p').textContent = bgr_fmtMoney(proposed);
    table.querySelector('.bpr-total-s').textContent = bgr_fmtMoney(suggested);
}

function bpr_tableHtml(project) {
    const lineItems = project.line_items ?? [];
    const rowsHtml  = lineItems.length
        ? lineItems.map(bpr_rowHtml).join('')
        : '<tr><td colspan="7"><div class="bpr-empty">No line items have been added under this project.</div></td></tr>';

    return `<div class="bpr-tbl-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl bpr-tbl">${BPR_THEAD}<tbody>${rowsHtml}</tbody>
                <tfoot><tr><td colspan="5" class="text-right">Total</td><td class="text-right bpr-total-p">${bgr_fmtMoney(0)}</td><td class="text-right bpr-total-s">${bgr_fmtMoney(0)}</td></tr></tfoot>
            </table>
        </div>
    </div>`;
}

/* ── Info card + block ───────────────────────────────────────── */
// "<chip: code> name" — a dash when there is neither
function bpr_codeNameHtml(code, name) {
    if (!code && !name) return '<span class="bpr-muted">—</span>';
    return (code ? `<span class="bpr-chip">${bgr_esc(code)}</span> ` : '') + bgr_esc(name || '');
}

// The requesting office: its id in bold (e.g. "R07"), its name after it
function bpr_unitHtml(ctx) {
    const id = ctx.rc_id ? String(ctx.rc_id) : (ctx.rc_code ? String(ctx.rc_code) : '');
    if (!id && !ctx.rc_name) return '<span class="bpr-muted">—</span>';
    return `<strong>${bgr_esc(id || ctx.rc_name)}</strong>` + (id && ctx.rc_name ? ` <span class="bpr-muted">${bgr_esc(ctx.rc_name)}</span>` : '');
}

function bpr_cardHtml(ctx, program, project) {
    const row = (label, valueHtml) => `<div class="bpr-info-row"><span class="bpr-info-k">${label}</span><span class="bpr-info-v">${valueHtml}</span></div>`;
    return `<div class="bpr-info">
        ${row('Originating Unit / Region', bpr_unitHtml(ctx))}
        ${row('Program', bpr_codeNameHtml(program.program_code, program.program_name))}
        ${row('Sub-Program', bpr_codeNameHtml(program.sub_program_code, program.sub_program_name))}
        ${row('Project', project ? bpr_codeNameHtml(project.project_code, project.project_name) : '<span class="bpr-muted">—</span>')}
    </div>`;
}

// One block = the info card + (the Project's table | "No projects yet." when the Program has no Project)
function bpr_blockHtml(ctx, program, project, id) {
    return `<section class="bpr-block" data-block="${id}">
        ${bpr_cardHtml(ctx, program, project)}
        ${project ? bpr_tableHtml(project) : '<div class="bpr-noproj">No projects yet.</div>'}
    </section>`;
}

// `programArg`: one program or an array of them
function bpr_render(requestContext, programArg) {
    const programs = Array.isArray(programArg) ? programArg : (programArg ? [programArg] : []);
    bpr_srcClose();   // a list left open over the old rows would point at inputs that are about to be replaced
    bpr_rowCount = programs.flatMap(p => p.projects ?? []).reduce((n, pj) => n + (pj.line_items ?? []).length, 0);

    $bpr('bpr-ref').textContent = requestContext.budget_reference_number ?? '';

    const blocks = [];
    programs.forEach((program, pi) => {
        const projects = program.projects ?? [];
        if (!projects.length) {
            blocks.push(bpr_blockHtml(requestContext, program, null, `b${pi}`));
        } else {
            projects.forEach((project, j) => blocks.push(bpr_blockHtml(requestContext, program, project, `b${pi}_${j}`)));
        }
    });
    $bpr('bpr-blocks').innerHTML = blocks.length ? blocks.join('') : '<div class="bpr-noproj">No programs yet.</div>';

    document.querySelectorAll('#bpr-blocks table.bpr-tbl').forEach(bpr_updateTotal);   // totals of the opening amounts
}

/* ── Messages / buttons ──────────────────────────────────────── */
function bpr_showErr(msg) {
    const el = $bpr('bpr-err');
    el.textContent = msg;
    el.hidden = false;
}
function bpr_hideErr() {
    $bpr('bpr-err').hidden = true;
}

// While a request is in flight both actions are locked; Endorse also stays
// disabled when there are no line items to endorse.
function bpr_updateButtons(busy) {
    $bpr('bpr-return').disabled  = busy;
    $bpr('bpr-endorse').disabled = busy || bpr_rowCount === 0;
}

/* ── Validation / collecting ─────────────────────────────────── */
// A Suggested Amount is optional — blank is fine (saved as 0.00) — but if something is typed it has to be a number, 0 or more.
function bpr_isValidAmount(value) {
    const v = bgr_unformatAmount(value).trim();
    return v === '' || (!isNaN(Number(v)) && Number(v) >= 0);
}

// Endorse needs a Type of Fund AND a Component on every line item, in every block (Program Sources is optional).
// The first offender is focused (which scrolls it into view) and every block with a problem is outlined in red.
function bpr_validate() {
    let firstInvalid = null;
    const badBlocks = new Set();

    document.querySelectorAll('#bpr-blocks .bpr-field').forEach(field => {
        // (Program Sources is optional and is not a .bpr-field)
        const invalid = field.matches('.bpr-suggested') ? !bpr_isValidAmount(field.value)
                                                        : !field.value;   // Type of Fund, Component
        field.classList.toggle('is-invalid', invalid);
        if (invalid) {
            badBlocks.add(field.closest('.bpr-block'));
            if (!firstInvalid) firstInvalid = field;
        }
    });

    document.querySelectorAll('#bpr-blocks .bpr-block').forEach(block => {
        block.classList.toggle('has-error', badBlocks.has(block));
    });

    if (firstInvalid) {
        firstInvalid.focus();
        bpr_showErr(firstInvalid.matches('.bpr-suggested')
            ? 'Suggested Amount must be a number, 0 or more.'
            : 'Type of Fund and Component are required for every line item.');
        return false;
    }
    return true;
}

// Rows of every block.
function bpr_collectItems() {
    return [...document.querySelectorAll('#bpr-blocks tr[data-li-id]')].map(tr => ({
        budget_line_item_id: Number(tr.dataset.liId),
        type_of_fund_id:     tr.querySelector('.bpr-fund').value || null,
        program_source_code: tr.querySelector('.bpr-source').value || null,
        suggested_amount:    bgr_unformatAmount(tr.querySelector('.bpr-suggested').value).trim(),
        component_id:        tr.querySelector('.bpr-component').value || null,
    }));
}

/* ── Actions ─────────────────────────────────────────────────── */
async function bpr_endorse() {
    bpr_hideErr();
    if (!bpr_validate()) return;
    if (!confirm(`Endorse budget request ${bpr_ctx.budget_reference_number}? The Type of Fund, Program Source, Component and Suggested Amount will be saved to its line items and its status will change to Endorsed.`)) return;

    await bpr_send('endorse', { items: bpr_collectItems() }, 'Failed to endorse the budget request.');
}

// Returning needs a reason: the REMARKS box above the footer is required.
async function bpr_return() {
    bpr_hideErr();
    const box = $bpr('bpr-remarks');
    const remarks = box.value.trim();
    if (remarks === '') {
        box.classList.add('is-invalid');
        box.focus();
        bpr_showErr('Remarks are required when returning a request — tell the requesting office what to fix.');
        return;
    }
    if (!confirm(`Return budget request ${bpr_ctx.budget_reference_number} to the requesting office? Your remarks will be sent with it.`)) return;

    await bpr_send('return', { remarks }, 'Failed to return the budget request.');
}

async function bpr_send(action, body, failMessage) {
    bpr_updateButtons(true);
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BPR_BUDGET_REQ_API}/${action}/${encodeURIComponent(bpr_ctx.budget_reference_number)}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || failMessage);

        bgr_toast(json.message, 'success');
        bpr_closeModal();
        window.bgr_afterSave?.();
    } catch (e) {
        bpr_showErr(e.message);
    } finally {
        bpr_updateButtons(false);
        window.PagePreloader?.hide();
    }
}
</script>
