<?php
namespace PHPMaker2024\FIMS;
?>
<style>
/* ── Budget revision details / review modal ────────────────────────────────
   Scoped to .nha-root so it picks up the nha-fims.css variables. Filled by the
   brv_openView() / brv_review() functions in budget-revision-page.php. */
.nha-root .brv-v-label {
    margin: 18px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--sec, #62657E);
}
.nha-root .brv-v-label:first-of-type { margin-top: 0; }

.nha-root .brv-v-card {
    padding: 10px 12px; font-size: 12.5px;
    background: var(--blue-bg, #E6EBF6); border: 1px solid var(--navy-bdr, #E6EBF6);
    border-radius: var(--rs, 7px);
}
.nha-root .brv-v-card-top { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.nha-root .brv-v-card-sub { margin-top: 2px; color: var(--subtle, #62657E); white-space: pre-line; overflow-wrap: anywhere; line-height: 1.45; }
.nha-root .brv-v-card-amt { margin-top: 6px; }

.nha-root .brv-v-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.nha-root .brv-v-cell {
    padding: 8px 10px; background: var(--surf, #F4F7FA); border: 1px solid var(--bdr, #E4ECF2);
    border-radius: var(--rs, 7px); min-width: 0;
}
.nha-root .brv-v-cell-k { font-size: 11px; color: var(--subtle, #62657E); }
.nha-root .brv-v-cell-v { margin-top: 2px; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
.nha-root .brv-v-cell-v.is-up   { color: var(--green-txt, #0C7539); }
.nha-root .brv-v-cell-v.is-down { color: var(--red-txt, #8F222D); }

.nha-root .brv-v-card[hidden],
.nha-root .brv-v-reason[hidden],
.nha-root .brv-v-effect[hidden] { display: none; }

.nha-root .brv-v-reason {
    padding: 8px 10px; font-size: 13px; white-space: pre-wrap; overflow-wrap: anywhere;
    background: var(--surf, #F4F7FA); border: 1px solid var(--bdr, #E4ECF2); border-radius: var(--rs, 7px);
}
.nha-root .brv-v-reason.is-empty { color: var(--subtle, #62657E); font-style: italic; }

/* "A moves X to B" line under the revision grid */
.nha-root .brv-v-effect {
    margin-top: 8px; padding: 7px 10px; font-size: 12.5px; font-weight: 600;
    background: var(--blue-bg, #E6EBF6); color: var(--afims-primary-text, #0035A9);
    border-radius: var(--rs, 7px);
}
.nha-root .brv-v-effect.is-warn { background: var(--red-bg, #FAE1E3); color: var(--red-txt, #8F222D); }

.nha-root .brv-v-meta { margin-top: 10px; font-size: 12px; color: var(--subtle, #62657E); }

/* the two lines read as a transfer: funds leave the From line (red accent) and arrive at the To line (green accent) */
.nha-root .brv-v-card.is-from { border-left: 3px solid var(--red-txt, #8F222D); }
.nha-root .brv-v-card.is-to   { border-left: 3px solid var(--green-txt, #0C7539); }

.nha-root .brv-v-effect { border-left: 3px solid currentColor; }

/* the current status — informational only; the Decline / Approve buttons live in the footer so they never scroll out of reach */
.nha-root .brv-v-decision {
    display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    padding: 10px 12px; background: var(--surf, #F4F7FA);
    border: 1px solid var(--bdr, #E4ECF2); border-radius: var(--rs, 7px);
}
.nha-root .brv-v-status { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: var(--subtle, #62657E); }

/* footer: Close on the left, the decision on the right */
.nha-root .brv-v-foot { flex-wrap: wrap; }
.nha-root .brv-v-foot #brv-view-cancel { margin-right: auto; }
.nha-root .brv-v-actions { display: flex; gap: 8px; }
.nha-root .brv-v-actions[hidden] { display: none; }
.nha-root .brv-v-actions .nha-btn { padding: 9px 18px; border-radius: 8px; }
.nha-root .brv-v-actions .nha-btn svg { width: 15px; height: 15px; fill: none; stroke-width: 2.4; }
.nha-root .brv-v-actions .nha-btn[disabled] { opacity: .6; cursor: not-allowed; }
/* Decline is the quieter of the two: outlined red, filling in on hover; Approve stays the solid green call to action */
.nha-root .brv-v-actions .brv-v-decline {
    background: var(--afims-surface, #FFFFFF); color: var(--red-txt, #8F222D);
    border: 1px solid var(--red-txt, #8F222D);
}
.nha-root .brv-v-actions .brv-v-decline:hover:not([disabled]) { background: var(--red-bg, #FAE1E3); opacity: 1; }

@media (max-width: 520px) {
    .nha-root .brv-v-grid { grid-template-columns: 1fr; }
    .nha-root .brv-v-foot #brv-view-cancel { margin-right: 0; order: 2; flex: 1 1 100%; justify-content: center; }
    .nha-root .brv-v-actions { order: 1; flex: 1 1 100%; }
    .nha-root .brv-v-actions .nha-btn { flex: 1; justify-content: center; }
}
</style>

<!-- Same wrapper trick as the add/edit form: every .nha-modal rule is scoped to .nha-root. -->
<div class="nha-root" style="padding:0;background:none;">

<!-- ── DETAILS / REVIEW MODAL ───────────────────────────── -->
<div id="brv-view-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-md" role="dialog" aria-modal="true" aria-labelledby="brv-view-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="brv-view-title">Budget Revision Request</h3>
            <button type="button" class="nha-modal-close" id="brv-view-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body">
            <input type="hidden" id="brv-view-id" value="">

            <!-- the From budget line (from_budget_line_item_id): the line being revised -->
            <div class="brv-v-label">From Budget Line</div>
            <div class="brv-v-card is-from">
                <div class="brv-v-card-top">
                    <span class="nha-bdg nha-bdg-gray" id="brv-view-type">Status</span>
                    <strong id="brv-view-title-req"></strong>
                </div>
                <div class="brv-v-card-sub" id="brv-view-req-sub"></div>
                <div class="brv-v-card-amt">Current amount <strong id="brv-view-req-amount"></strong></div>
            </div>

            <!-- the To budget line (to_budget_line_item_id): the line that receives the funds; optional -->
            <div class="brv-v-label">To Budget Line</div>
            <div class="brv-v-card is-to" id="brv-view-to-card" hidden>
                <div class="brv-v-card-top">
                    <span class="nha-bdg nha-bdg-gray" id="brv-view-to-type">Status</span>
                    <strong id="brv-view-to-title-req"></strong>
                </div>
                <div class="brv-v-card-sub" id="brv-view-to-req-sub"></div>
                <div class="brv-v-card-amt">Current amount <strong id="brv-view-to-req-amount"></strong></div>
            </div>
            <div class="brv-v-reason is-empty" id="brv-view-to-none" hidden>No To budget line — only the From budget line's amount changes.</div>

            <!-- the revision -->
            <div class="brv-v-label">Revision</div>
            <div class="brv-v-grid">
                <div class="brv-v-cell"><div class="brv-v-cell-k">From Amount</div><div class="brv-v-cell-v" id="brv-view-from"></div></div>
                <div class="brv-v-cell"><div class="brv-v-cell-k">To Amount</div><div class="brv-v-cell-v" id="brv-view-to"></div></div>
                <div class="brv-v-cell"><div class="brv-v-cell-k">Change</div><div class="brv-v-cell-v" id="brv-view-change"></div></div>
            </div>
            <!-- what approving does to the two budget lines (only while a To line is set) -->
            <div class="brv-v-effect" id="brv-view-effect" hidden></div>

            <div class="brv-v-label">Reason</div>
            <div class="brv-v-reason" id="brv-view-reason"></div>
            <div class="brv-v-meta" id="brv-view-meta"></div>

            <!-- the current status (the decision buttons are in the footer) -->
            <div class="brv-v-label">Status</div>
            <div class="brv-v-decision">
                <div class="brv-v-status">
                    <span class="nha-bdg nha-bdg-amber" id="brv-view-status">Pending</span>
                    <span id="brv-view-status-note"></span>
                </div>
            </div>
        </div>
        <div class="nha-modal-foot brv-v-foot">
            <button type="button" class="nha-btn nha-btn-ghost" id="brv-view-cancel">Close</button>
            <!-- shown by brv_openView() only while the request is pending AND the user is FMD -->
            <div class="brv-v-actions" id="brv-view-actions" hidden>
                <button type="button" class="nha-btn brv-v-decline" id="brv-decline">
                    <svg viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    Decline
                </button>
                <button type="button" class="nha-btn nha-btn-success" id="brv-approve">
                    <svg viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                    Approve
                </button>
            </div>
        </div>
    </div>
</div>

</div><!-- /.nha-root (review modal wrapper) -->
