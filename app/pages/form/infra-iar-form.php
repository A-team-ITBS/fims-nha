<?php
/**
 * infra-iar-form.php
 *
 * IAR Module — INFRASTRUCTURE tab (fims.statement_work_accomplishments, API /infra-iar):
 *   - the list (table in #ifr-tbody, filled by ifr_load())
 *   - the Add / Edit modal (#ifr-overlay): pick an approved Infrastructure RFA without a PO → its project details load
 *     (project, location, program, contractor, contract cost, project cost); the SWA number is generated on save; the user adds, the dates, the account
 *     entries (pre-filled from the RFA) and the Statement of Work Accomplishments (description, cost, % accomplishment)
 *   - the read-only View (re-uses the #amp-view-overlay window of the deliveries tab)
 *
 * Included from iar-module.php AFTER form/dr-module-form.php, whose styles (.amp-*) and helpers (amp_authHeaders, amp_fmtMoney,
 * amp_liveFmt, amp_textEsc, amp_attrEsc, amp_confirm …) it re-uses. Prefix: ifr_ / ifr-.
 */
$_ifr_api = $_amp_api ?? \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
    /* Account Code picker: the CIP / WIP categories are highlighted — a heading per group and a tinted, accented row */
    .ifr-acct-group { padding:6px 12px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
        color:var(--afims-primary, #1d4ed8);background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E3E6EE); }
    .ifr-acct-group:first-child { border-top:0; }
    /* an entry showing the RFA's own account, which is not valid here: marked until the user chooses a CIP / WIP account */
    .amp-input.ifr-needs-pick { border-color:var(--afims-warning, #D97706);background:rgba(217,119,6,.07);color:var(--afims-text-secondary, #5B5E73); }
    .ifr-acct-group.ifr-acct-dim { color:var(--afims-text-muted, #62657E); }
    .amp-combo-opt.ifr-acct-hl { border-left:3px solid var(--afims-primary, #1d4ed8);background:rgba(29,78,216,.06); }
    .amp-combo-opt.ifr-acct-hl:hover, .amp-combo-opt.ifr-acct-hl.active { background:rgba(29,78,216,.14); }
    /* Add / Edit modal: the same shell as the PO modal (#amp-overlay) */
    #ifr-overlay { display:none;position:fixed;inset:0;z-index:9100;box-sizing:border-box;padding:16px;
        background:rgba(15,32,69,.55);align-items:center;justify-content:center;overflow-y:auto; }
    #ifr-overlay.open { display:flex; }
    #ifr-card { display:flex;flex-direction:column;max-height:92vh;overflow:hidden;
        background:var(--afims-surface, #FFFFFF);border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.22);
        font-family:'IBM Plex Sans',sans-serif;font-size:14px;color:var(--afims-text, #1A1B21);animation:ampIn .18s ease; }
    #ifr-card *, #ifr-card *::before, #ifr-card *::after { box-sizing:border-box; }
    #ifr-card .amp-input, #ifr-card .amp-combo { box-sizing:border-box !important;width:100% !important;max-width:100% !important;min-width:0 !important; }
    #ifr-card .amp-group > *, #ifr-card .amp-row > * { min-width:0; }
    #ifr-header { display:flex;align-items:center;justify-content:space-between;flex-shrink:0;padding:16px 20px;background:var(--afims-heading-fill, #000E3E); }
    #ifr-modal-close { display:flex;align-items:center;justify-content:center;width:28px;height:28px;font-size:18px;line-height:1;cursor:pointer;
        color:var(--afims-pure-white, #FFFFFF);background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 15%, transparent);
        border:1px solid rgba(255,255,255,.25);border-radius:6px;opacity:.85; }
    #ifr-modal-close:hover { opacity:1; }
    #ifr-err { display:none;margin:16px 22px 0;padding:8px 12px;font-size:12px;line-height:1.5;color:var(--afims-danger, #DC3545);
        background:var(--afims-danger-soft, #FAE1E3);border:1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 25%, transparent);border-radius:8px; }
    #ifr-form { display:flex;flex-direction:column;min-height:0;flex:1; }
    #ifr-body { flex:1;overflow-y:auto;padding:18px 22px; }
    #ifr-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-shrink:0;padding:14px 20px;
        background:var(--afims-surface, #FFFFFF);border-top:1px solid var(--afims-border, #E4ECF2); }
    #ifr-footer .amp-btn { height:38px;padding:0 22px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    #ifr-footer .amp-btn:disabled { opacity:.5;cursor:not-allowed; }
    #ifr-footer .amp-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #ifr-footer .amp-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    .amp-row.c3 { grid-template-columns:repeat(3,minmax(0,1fr)); }
    .amp-row.c4 { grid-template-columns:repeat(4,minmax(0,1fr));column-gap:20px; }
    @media (max-width:1100px) { .amp-row.c4 { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:760px) { .amp-row.c3 { grid-template-columns:minmax(0,1fr) minmax(0,1fr) !important; } }
    #ifr-variation.neg { color:var(--afims-error, #C62828); } #ifr-variation.pos { color:var(--afims-success, #1B7F3B); }

    /* Add Accomplishment window: the same shell as the Add / Edit modal, a narrower card */
    #ifr-aa-overlay { display:none;position:fixed;inset:0;z-index:9100;box-sizing:border-box;padding:16px;
        background:rgba(15,32,69,.55);align-items:flex-start;justify-content:center;overflow-y:auto; }
    #ifr-aa-overlay.open { display:flex; }
    #ifr-aa-card { display:flex;flex-direction:column;width:100%;max-width:1360px;max-height:92vh;overflow:hidden;
        background:var(--afims-surface, #FFFFFF);border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.22);
        font-family:'IBM Plex Sans',sans-serif;font-size:14px;color:var(--afims-text, #1A1B21);animation:ampIn .18s ease; }
    #ifr-aa-card *, #ifr-aa-card *::before, #ifr-aa-card *::after { box-sizing:border-box; }
    #ifr-aa-card .amp-input, #ifr-aa-card .amp-combo { box-sizing:border-box !important;width:100% !important;max-width:100% !important;min-width:0 !important; }
    #ifr-aa-card .amp-group > *, #ifr-aa-card .amp-row > * { min-width:0; }
    #ifr-aa-header { display:flex;align-items:center;justify-content:space-between;flex-shrink:0;padding:16px 20px;background:var(--afims-heading-fill, #000E3E); }
    #ifr-aa-close { display:flex;align-items:center;justify-content:center;width:28px;height:28px;font-size:18px;line-height:1;cursor:pointer;
        color:var(--afims-pure-white, #FFFFFF);background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 15%, transparent);
        border:1px solid rgba(255,255,255,.25);border-radius:6px;opacity:.85; }
    #ifr-aa-close:hover { opacity:1; }
    #ifr-aa-err { display:none;margin:16px 22px 0;padding:8px 12px;font-size:12px;line-height:1.5;color:var(--afims-danger, #DC3545);
        background:var(--afims-danger-soft, #FAE1E3);border:1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 25%, transparent);border-radius:8px; }
    #ifr-aa-form { display:flex;flex-direction:column;min-height:0;flex:1; }
    #ifr-aa-body { flex:1;overflow-y:auto;padding:18px 22px; }
    #ifr-aa-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-shrink:0;padding:14px 20px;
        background:var(--afims-surface, #FFFFFF);border-top:1px solid var(--afims-border, #E4ECF2); }
    #ifr-aa-footer .amp-btn { height:38px;padding:0 22px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    #ifr-aa-footer .amp-btn:disabled { opacity:.5;cursor:not-allowed; }
    #ifr-aa-footer .amp-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #ifr-aa-footer .amp-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #ifr-aa-old .amp-input { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-secondary, #5B5E73); }
    #ifr-aa-warn { align-items:flex-start;gap:8px; }

    /* EDIT: only project cost, dates, duration and the statement of work can change */
    #ifr-edit-note { display:none;margin-bottom:18px;padding:10px 14px;border-radius:8px;font-size:13px;line-height:1.5;color:#92400E;background:#FEF3C7;border:1px solid #FDE68A; }
    .ifr-edit #ifr-edit-note { display:block; }
    .ifr-edit #ifr-swa-section { display:none; }       /* the Statement of Work items are not edited here either: only the project details */
    .ifr-edit #ifr-entries-section { display:none; }   /* the Account Entries cannot be changed once the record exists: hidden while editing */
    .ifr-edit #ifr-rfa, .ifr-edit #ifr-swa { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-secondary, #5B5E73);cursor:not-allowed; }

    /* Statement of Work rows: Description | Cost | % Accomplishment | remove */
    .ifr-itm { display:flex;align-items:flex-start;margin-bottom:12px; }
    .ifr-itm > .c { min-width:0;margin-left:16px; }
    .ifr-itm > .c:first-child { margin-left:0; }
    .ifr-itm .c-desc { flex:4 1 0; }
    .ifr-itm .c-cost { flex:1.6 1 0; }
    .ifr-itm .c-pct { flex:1.2 1 0; }
    .ifr-itm .c-x { flex:0 0 28px;text-align:center;padding-top:8px; }
    .ifr-itm textarea.amp-input { min-height:64px;resize:vertical; }
    .ifr-itm-head { font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text, #1A1B21);margin-bottom:6px;align-items:flex-end; }
    /* a work item whose cost (added to the items above it) takes the total past the Account Entries total */
    .ifr-itm .amp-input.ifr-over { border-color:var(--afims-error, #C62828) !important;color:var(--afims-error, #C62828);background:#FDECEC; }
    .ifr-itm-total { display:flex;justify-content:flex-end;align-items:center;gap:26px;margin-top:6px;padding-top:12px;font-size:13px;border-top:1px solid var(--afims-border, #E4ECF2); }
    @media (max-width:900px) { .ifr-itm { flex-wrap:wrap; } .ifr-itm .c-desc { flex:1 1 100%;margin-left:0;margin-bottom:10px; }
        .ifr-itm .c-cost, .ifr-itm .c-pct { flex:1 1 40%; } .ifr-itm-head { display:none; } }

    /* progress bar of the list / view */
    .ifr-bar { display:inline-block;vertical-align:middle;width:84px;height:8px;margin-right:8px;border-radius:4px;overflow:hidden;background:var(--afims-border, #E4ECF2); }
    .ifr-bar > i { display:block;height:100%;background:var(--afims-primary, #0035A9); }
    .ifr-bar.done > i { background:#16A34A; }
</style>

<div id="ifr-overlay">
    <div class="u-modal-2xl" id="ifr-card">
        <div id="ifr-header">
            <h3 class="amp-hdr-title" id="ifr-modal-title">Add Infrastructure Record</h3>
            <button type="button" id="ifr-modal-close" aria-label="Close">&times;</button>
        </div>
        <div id="ifr-err"></div>

        <form id="ifr-form" autocomplete="off">
            <div id="ifr-body">
                <input type="hidden" id="ifr-mode" value="add">
                <input type="hidden" id="ifr-id" value="">
                <div id="ifr-edit-note"><strong>Editing:</strong> only the project details (contract, area, TCT number, dates and contract duration) can be changed.
                    The RFA, the SWA number, the Account Entries and the Statement of Work Accomplishments are locked — use Actions → Add Accomplishment to add work items.</div>

                <!-- ── Project: the RFA (approved, Infrastructure, no PO) loads the details ── -->
                <div class="amp-section">
                    <h4 class="amp-section-header">Project</h4>
                    <div class="amp-row c3">
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-rfa">RFA Number <span class="req">*</span></label>
                            <div class="amp-combo" id="ifr-rfa-combo">
                                <input type="text" class="amp-input" id="ifr-rfa" maxlength="100" placeholder="Search approved RFA number…">
                                <div class="amp-combo-list"></div>
                            </div>
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-swa">SWA Number</label>
                            <input type="text" class="amp-input" id="ifr-swa" readonly tabindex="-1" placeholder="Generating…" style="font-weight:600;">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-office">Regional Office/District Office</label>
                            <input type="text" class="amp-input" id="ifr-office" readonly tabindex="-1" placeholder="Loaded from the RFA">
                        </div>
                    </div>
                    <div class="amp-row">
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-project">Project Name</label>
                            <input type="text" class="amp-input" id="ifr-project" readonly tabindex="-1" placeholder="Loaded from the RFA">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-location">Location</label>
                            <input type="text" class="amp-input" id="ifr-location" readonly tabindex="-1" placeholder="Loaded from the RFA">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-program">Program</label>
                            <input type="text" class="amp-input" id="ifr-program" readonly tabindex="-1" placeholder="Loaded from the RFA">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-contractor">Contractor</label>
                            <input type="text" class="amp-input" id="ifr-contractor" readonly tabindex="-1" placeholder="Loaded from the RFA">
                        </div>
                    </div>
                    <div class="amp-row c4">
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-original-cost">Original Contract Cost</label>
                            <input type="text" class="amp-input amp-num" id="ifr-original-cost" readonly tabindex="-1" placeholder="Loaded from the RFA">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-variation">Variation Order <span class="opt">(+ increase / &minus; decrease)</span></label>
                            <input type="text" class="amp-input amp-num" id="ifr-variation" readonly tabindex="-1" placeholder="Loaded from the RFA" style="font-weight:700;">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-contract-cost">Revised Contract Cost</label>
                            <input type="text" class="amp-input amp-num" id="ifr-contract-cost" readonly tabindex="-1" placeholder="Loaded from the RFA" style="font-weight:700;">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-contract">Contract</label>
                            <input type="text" class="amp-input" id="ifr-contract" maxlength="255" autocomplete="off" placeholder="Type the contract">
                        </div>
                    </div>
                    <div class="amp-row c2">
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-land-area">Area in SQM / Units <span class="req">*</span></label>
                            <input type="text" inputmode="decimal" class="amp-input amp-num" id="ifr-land-area" autocomplete="off" placeholder="0.00">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-tct">TCT Number <span class="opt">(optional)</span></label>
                            <input type="text" class="amp-input" id="ifr-tct" maxlength="100" autocomplete="off" placeholder="Type the TCT number">
                        </div>
                    </div>
                    <div class="amp-row c3">
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-start">Date Started <span class="req">*</span></label>
                            <input type="date" class="amp-input" id="ifr-start">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-end">Completion Date (Revised) <span class="req">*</span></label>
                            <input type="date" class="amp-input" id="ifr-end">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="ifr-duration">Contract Duration</label>
                            <input type="text" class="amp-input" id="ifr-duration" maxlength="100" placeholder="Auto from the dates — or type it">
                        </div>
                    </div>
                </div>

                <!-- ── Account Entries: pre-filled from the RFA; the total may not exceed the contract cost ── -->
                <div class="amp-section" id="ifr-entries-section">
                    <h4 class="amp-section-header">Account Entries</h4>
                    <div class="amp-sec-head">
                        <span class="amp-sec-title">Accounts</span>
                        <button type="button" class="amp-add-btn" id="ifr-add-entry">+ Add row</button>
                    </div>
                    <div class="amp-ent amp-ent-head"><span>Account Code <span class="req">*</span></span><span style="text-align:right;">Amount (₱) <span class="req">*</span></span><span></span></div>
                    <div id="ifr-entries"></div>
                    <div class="amp-ent amp-ent-total">
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;">
                            <span class="amp-ent-total-lbl">Total</span>
                            <span class="amp-jev-pill bad" id="ifr-tot-pill" style="padding:4px 10px;font-size:11px;display:none;">&#10006; Exceeds contract cost</span>
                        </div>
                        <div class="amp-num amp-ent-total-val" id="ifr-tot-amount">0.00</div>
                        <span></span>
                    </div>
                </div>

                <!-- ── Statement of Work Accomplishments (hidden while editing: more items are added with Actions → Add Accomplishment) ── -->
                <div class="amp-section" id="ifr-swa-section">
                    <h4 class="amp-section-header">Statement of Work Accomplishments <span class="opt">(description is required)</span>
                        <span class="amp-hdr-right"><button type="button" class="amp-add-btn" id="ifr-add-item">+ Add item</button></span>
                    </h4>
                    <div class="ifr-itm ifr-itm-head"><span class="c c-desc">Description</span><span class="c c-cost" style="text-align:right;">Cost (₱)</span><span class="c c-pct" style="text-align:right;">% Accomplishment</span><span class="c c-x"></span></div>
                    <div id="ifr-items"></div>
                    <div class="ifr-itm-total"><span>Total cost <strong id="ifr-items-cost">0.00</strong></span><span>Overall accomplishment <strong id="ifr-items-pct">0.00%</strong></span></div>
                </div>
            </div>
            <div id="ifr-footer">
                <button type="button" class="amp-btn amp-btn-ghost" id="ifr-cancel">Cancel</button>
                <button type="submit" class="amp-btn amp-btn-navy" id="ifr-save">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Actions → Add Accomplishment: same window design as the Add / Edit modal -->
<div id="ifr-aa-overlay">
    <div id="ifr-aa-card">
        <div id="ifr-aa-header">
            <h3 class="amp-hdr-title">Add Accomplishment</h3>
            <button type="button" id="ifr-aa-close" aria-label="Close">&times;</button>
        </div>
        <div id="ifr-aa-err"></div>
        <form id="ifr-aa-form" autocomplete="off">
            <div id="ifr-aa-body">
                <div class="amp-section">
                    <h4 class="amp-section-header">Project</h4>
                    <div class="amp-row c3">
                        <div class="amp-group"><label class="amp-label" for="ifr-aa-swa">SWA Number</label><input type="text" class="amp-input" id="ifr-aa-swa" readonly tabindex="-1" style="font-weight:600;"></div>
                        <div class="amp-group"><label class="amp-label" for="ifr-aa-rfa">RFA Number</label><input type="text" class="amp-input" id="ifr-aa-rfa" readonly tabindex="-1"></div>
                        <div class="amp-group"><label class="amp-label" for="ifr-aa-project">Project Name</label><input type="text" class="amp-input" id="ifr-aa-project" readonly tabindex="-1"></div>                    </div>
                </div>
                <div class="amp-section">
                    <h4 class="amp-section-header">Statement of Work Accomplishments <span class="opt">(description is required)</span></h4>
                    <div class="ifr-itm ifr-itm-head"><span class="c c-desc">Description</span><span class="c c-cost" style="text-align:right;">Cost (₱)</span><span class="c c-pct" style="text-align:right;">% Accomplishment</span><span class="c c-x"></span></div>
                    <div id="ifr-aa-old" class="ifr-aa-old"></div>
                    <div id="ifr-aa-new"></div>
                    <div class="ifr-itm-total"><span id="ifr-aa-left" style="margin-right:auto;"></span><span>Total cost <strong id="ifr-aa-cost-total">0.00</strong></span><span>Overall accomplishment <strong id="ifr-aa-pct-total">0.00%</strong></span></div>
                    <div id="ifr-aa-warn" class="amp-cf-warn" style="margin-top:12px;display:none;"></div>
                </div>
            </div>
            <div id="ifr-aa-footer">
                <button type="button" class="amp-btn amp-btn-ghost" id="ifr-aa-cancel">Cancel</button>
                <button type="submit" class="amp-btn amp-btn-navy" id="ifr-aa-save">Add</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   IAR MODULE — INFRASTRUCTURE   prefix: ifr_   (fims.statement_work_accomplishments)
═══════════════════════════════════════════════════════════════ */
const IFR_API = '<?php echo $_ifr_api; ?>' + '/infra-iar';
const IFR_PAGE_SIZE = 15;
let ifr_page = 1, ifr_rows = [], ifr_loaded = false;
const ifr_$ = id => document.getElementById(id);
const ifr_money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const ifr_pct = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
const ifr_dash = v => (v === null || v === undefined || v === '') ? '—' : amp_esc(v);

/* ── Filters: Regional Office/District Office and Contractor — searchable dropdowns (same design as the Deliveries tab; no date range here) ── */
const IFR_FC = { 'ifr-fc-office': { param: 'rc_id', key: 'offices' }, 'ifr-fc-contractor': { param: 'vendor_id', key: 'contractors' } };
const ifr_fcSel  = { rc_id: '', vendor_id: '' };            // the chosen value of each filter ('' = all)
let   ifr_fcData = { offices: [], contractors: [] };        // [{value, label}] from GET /infra-iar/filter-options
function ifr_fcBuild() {
    Object.entries(IFR_FC).forEach(([id, cfg]) => {
        const wrap = ifr_$(id);
        if (!wrap) return;
        wrap.innerHTML = '<input type="text" class="nha-filter-input" autocomplete="off" placeholder="' + amp_esc(wrap.dataset.all) + '">'
            + '<button type="button" class="amp-fc-clear" hidden title="Clear">×</button><div class="amp-fc-list"></div>';
        const input = wrap.querySelector('input'), list = wrap.querySelector('.amp-fc-list'), clear = wrap.querySelector('.amp-fc-clear');
        let shown = [];
        const labelOf = v => (ifr_fcData[cfg.key].find(o => String(o.value) === String(v)) || {}).label || '';
        const sync = () => { input.value = labelOf(ifr_fcSel[cfg.param]); clear.hidden = !ifr_fcSel[cfg.param]; };
        const render = () => {
            const q = input.dataset.typing === '1' ? input.value.trim().toLowerCase() : '';     // the shown label is not a search
            shown = ifr_fcData[cfg.key].filter(o => !q || String(o.label).toLowerCase().includes(q));
            list.innerHTML = '<div class="amp-fc-opt" data-i="-1">' + amp_esc(wrap.dataset.all) + '</div>'
                + (shown.length ? shown.map((o, i) => '<div class="amp-fc-opt' + (String(o.value) === String(ifr_fcSel[cfg.param]) ? ' active' : '') + '" data-i="' + i + '">' + amp_esc(o.label) + '</div>').join('')
                                : '<div class="amp-fc-empty">No match</div>');
        };
        const choose = v => {
            ifr_fcSel[cfg.param] = v;
            input.dataset.typing = '0'; wrap.classList.remove('open'); sync();
            ifr_page = 1; ifr_load();
        };
        input.addEventListener('focus', () => { input.dataset.typing = '0'; input.select(); wrap.classList.add('open'); render(); });
        input.addEventListener('click', () => { if (!wrap.classList.contains('open')) { wrap.classList.add('open'); render(); } });
        input.addEventListener('input', () => { input.dataset.typing = '1'; wrap.classList.add('open'); render(); });
        input.addEventListener('keydown', e => {
            if (e.key === 'Escape') { wrap.classList.remove('open'); sync(); input.blur(); }
            if (e.key === 'Enter') { e.preventDefault(); if (shown.length === 1 || (input.dataset.typing === '1' && shown[0])) choose(String(shown[0].value)); }
        });
        input.addEventListener('blur', () => { wrap.classList.remove('open'); input.dataset.typing = '0'; sync(); });   // half-typed text goes back to the chosen value
        list.addEventListener('mousedown', e => {
            const opt = e.target.closest('.amp-fc-opt');
            if (!opt) return;
            e.preventDefault();
            const i = Number(opt.dataset.i);
            choose(i < 0 ? '' : String(shown[i].value));
        });
        clear.addEventListener('mousedown', e => { e.preventDefault(); choose(''); });
        wrap._sync = sync;
        sync();
    });
}
async function ifr_loadFilterOptions() {
    try {
        const res  = await fetch(`${IFR_API}/filter-options`, { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (json.success) {
            ifr_fcData = { offices: json.data.offices || [], contractors: json.data.contractors || [] };
            Object.keys(IFR_FC).forEach(id => ifr_$(id)?._sync?.());
        }
    } catch (e) { /* the filters just stay empty */ }
}

/* ── List ──────────────────────────────────────────────────── */
async function ifr_load() {
    ifr_loaded = true;
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ search: ifr_$('ifr-filter-search')?.value || '', page: ifr_page, limit: IFR_PAGE_SIZE });
        if (ifr_fcSel.rc_id)     params.set('rc_id', ifr_fcSel.rc_id);              // Regional Office/District Office filter
        if (ifr_fcSel.vendor_id) params.set('vendor_id', ifr_fcSel.vendor_id);      // Contractor filter
        const res  = await fetch(`${IFR_API}/list?${params}`, { credentials: 'same-origin', headers: amp_authHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        ifr_renderTable(json.data ?? []);
        ifr_renderPagination(json.pagination ?? {});
    } catch (e) {
        ifr_renderTable([]);
        amp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function ifr_bar(p) {
    const n = Math.max(0, Math.min(100, Number(p) || 0));
    return `<span class="ifr-bar${n >= 100 ? ' done' : ''}"><i style="width:${n}%"></i></span>${ifr_pct(p)}`;
}

function ifr_renderTable(rows) {
    const tbody = ifr_$('ifr-tbody');
    ifr_rows = rows;
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No infrastructure records found.</span></div></td></tr>';
        return;
    }
    const offset = (ifr_page - 1) * IFR_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq" style="vertical-align:top;">${offset + i + 1}</td>
        <td style="vertical-align:top;white-space:nowrap;"><code class="nha-code">${amp_esc(r.swa_number)}</code></td>
        <td style="vertical-align:top;white-space:nowrap;">${ifr_dash(r.rfa_number)}</td>
        <td style="vertical-align:top;">${ifr_dash(r.project_name)}</td>
        <td style="vertical-align:top;">${ifr_dash(r.contractor)}</td>
        <td style="vertical-align:top;">${ifr_dash(r.requesting_office)}</td>
        <td style="vertical-align:top;">${r.category ? amp_esc(r.category) : '<span title="Set when the pro-forma JEV is submitted" style="color:#9ca3af;">—</span>'}</td>
        <td style="vertical-align:top;text-align:right;white-space:nowrap;"><strong>${ifr_money(r.contract_cost)}</strong></td>
        <td style="vertical-align:top;white-space:nowrap;">${ifr_bar(r.accomplishment)}</td>
        <td style="white-space:nowrap;vertical-align:top;">
            <div class="iar-menu">
                <button type="button" class="iar-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="amp_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="iar-menu-drop">
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();ifr_view(${i})" title="View">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </button>
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();ifr_proforma(${r.swa_id})" title="Pro-forma JEV">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Pro-forma
                    </button>
                    <div class="iar-menu-divider"></div>
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();ifr_edit(${i})" title="Edit">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </button>
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();ifr_addAccomplishment(${i})" title="Add Accomplishment">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add Accomplishment
                    </button>
                </div>
            </div>
        </td>
    </tr>`).join('');
}

function ifr_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = IFR_PAGE_SIZE } = p;
    ifr_$('ifr-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    ifr_$('ifr-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    ifr_$('ifr-total').textContent = total;
    const box = ifr_$('ifr-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="ifr_goTo(${i})">${i}</button>`);
    }
    box.innerHTML = `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="ifr_goTo(${current_page - 1})">‹</button>` + pages.join('')
        + `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="ifr_goTo(${current_page + 1})">›</button>`;
}
function ifr_goTo(p) { if (p < 1) return; ifr_page = p; ifr_load(); }

(function ifr_bindList() {
    const run = () => {
        let timer;
        const search = ifr_$('ifr-filter-search'), clear = ifr_$('ifr-search-clear');
        search?.addEventListener('input', () => {
            if (clear) clear.hidden = !search.value;
            clearTimeout(timer);
            timer = setTimeout(() => { ifr_page = 1; ifr_load(); }, 300);
        });
        clear?.addEventListener('click', () => { search.value = ''; search.focus(); clear.hidden = true; ifr_page = 1; ifr_load(); });
        ifr_fcBuild();
        ifr_loadFilterOptions();
        ifr_$('ifr-modal-close')?.addEventListener('click', ifr_closeModal);
        ifr_$('ifr-cancel')?.addEventListener('click', ifr_closeModal);
        ifr_$('ifr-form')?.addEventListener('submit', ifr_save);
        ifr_$('ifr-add-entry')?.addEventListener('click', () => ifr_addEntry());
        ifr_$('ifr-add-item')?.addEventListener('click', () => { const r = ifr_addItem(); r.querySelector('.ifr-i-desc').focus(); });
        ['ifr-swa', 'ifr-start', 'ifr-end'].forEach(id => ifr_$(id)?.addEventListener('input', ifr_refresh));
        ['ifr-start', 'ifr-end'].forEach(id => ['input', 'change'].forEach(ev => ifr_$(id)?.addEventListener(ev, () => { ifr_autoDuration(); ifr_refresh(); })));
        // Area in SQM / Units: typed with thousands separators and 2 decimals, like the amounts
        const area = ifr_$('ifr-land-area');
        area?.addEventListener('input', () => { area.value = amp_liveFmt(area.value, true); ifr_refresh(); });
        area?.addEventListener('blur', () => { if (area.value) area.value = amp_fmtMoney(amp_unfmt(area.value)); ifr_refresh(); });
        // the duration can be typed; clearing it hands it back to the dates
        ifr_$('ifr-duration')?.addEventListener('input', () => { ifr_state.durManual = !!ifr_$('ifr-duration').value.trim(); if (!ifr_state.durManual) ifr_autoDuration(); });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();

/* ── View (re-uses the deliveries tab's view window) ───────── */
function ifr_view(i) {
    const r = ifr_rows[i];
    if (!r) return;
    const vrow = (k, v) => `<div class="amp-vw-row"><div class="amp-vw-k">${k}</div><div class="amp-vw-v">${v}</div></div>`;
    const grid = (n, ...cells) => `<div style="display:grid;grid-template-columns:repeat(${n},minmax(0,1fr));column-gap:24px;">${cells.join('')}</div>`;
    const num  = v => '<span class="amp-vw-num">₱' + ifr_money(v) + '</span>';
    const entries = r.entries || [], items = r.items || [];
    const entTotal = entries.reduce((s, e) => s + Number(e.amount || 0), 0);
    amp_view_set('Infrastructure Record',r.swa_number || '', `
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Inspection and Acceptance Report</h4>
            ${grid(2, vrow('SWA No.','<span class="amp-vw-code">' + amp_esc(r.swa_number) + '</span>'), vrow('RFA No.', ifr_dash(r.rfa_number)),
                      vrow('Regional Office/District Office', ifr_dash(r.requesting_office)), vrow('Contractor', ifr_dash(r.contractor)))}
        </div>
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Project</h4>
            ${vrow('Project Code', r.project_code ? '<span class="amp-vw-code">' + amp_esc(r.project_code) + '</span>' : ifr_dash(null))}
            ${vrow('Project Name', ifr_dash(r.project_name))}
            ${grid(2, vrow('Location', ifr_dash(r.location)), vrow('Program', ifr_dash(r.program_name)),
                      vrow('Original Contract Cost', num(r.original_contract_cost)), vrow('Variation Order', ifr_signed(r.variation_order)),
                      vrow('Revised Contract Cost', num(r.contract_cost)), vrow('Contract', ifr_dash(r.contract)),
                      vrow('Date Started', ifr_dash(r.date_started)), vrow('Completion Date (Revised)', ifr_dash(r.completion_date_revised)),
                      vrow('Contract Duration', ifr_dash(r.contract_duration)),
                      vrow('Area in SQM / Units', r.total_land_area === null || r.total_land_area === undefined || r.total_land_area === '' ? ifr_dash(null) : amp_esc(Number(r.total_land_area).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))),
                      vrow('TCT Number', ifr_dash(r.tct_number)))}
        </div>
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Account Entries</h4><div class="amp-vw-tblwrap"><table class="amp-v-table">
            <thead><tr><th>Account Code</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>${entries.map(e => `<tr><td>${amp_esc(e.account_code)} - ${amp_esc(e.account_title)}</td><td style="text-align:right;white-space:nowrap;">${ifr_money(e.amount)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td style="text-align:right;">Total</td><td style="text-align:right;">${ifr_money(entTotal)}</td></tr></tfoot>
        </table></div></div>
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Statement of Work Accomplishments</h4><div class="amp-vw-tblwrap"><table class="amp-v-table">
            <thead><tr><th>Description</th><th style="text-align:right;">Cost</th><th style="text-align:right;">% Accomplishment</th></tr></thead>
            <tbody>${items.map(it => `<tr><td style="white-space:pre-wrap;">${amp_esc(it.description)}</td><td style="text-align:right;white-space:nowrap;">${ifr_money(it.cost)}</td><td style="text-align:right;white-space:nowrap;">${ifr_pct(it.accomplishment_pct)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td style="text-align:right;">Total</td><td style="text-align:right;">${ifr_money(r.items_cost)}</td><td style="text-align:right;">${ifr_pct(r.accomplishment)}</td></tr></tfoot>
        </table></div></div>`);
}

/* ── Actions → Add Accomplishment: one more Statement of Work item, in the same window design as Add / Edit (#ifr-aa-overlay) ──
   The record's existing items are shown (read-only) above the new row; the totals and the warnings follow the new row live, and Save is blocked
   while the new item would take the cost past the Account Entries total or the accomplishment past 100% (the server refuses it as well). */
const ifr_aa = { row: null, entC: 0, usedC: 0, usedP: 0 };

function ifr_aaHideErr() { const e = ifr_$('ifr-aa-err'); e.style.display = 'none'; e.textContent = ''; }
function ifr_aaShowErr(m) { const e = ifr_$('ifr-aa-err'); e.textContent = m; e.style.display = 'block'; }
function ifr_aaClose() { ifr_$('ifr-aa-overlay').classList.remove('open'); ifr_aaHideErr(); }

function ifr_addAccomplishment(i) {
    const r = ifr_rows[i];
    if (!r) return;
    const cents = v => Math.round((Number(v) || 0) * 100);
    ifr_aa.row   = r;
    ifr_aa.entC  = cents((r.entries || []).reduce((s, e) => s + Number(e.amount || 0), 0));
    ifr_aa.usedC = cents(r.items_cost);
    ifr_aa.usedP = cents(r.accomplishment);
    ifr_$('ifr-aa-swa').value      = r.swa_number || '';
    ifr_$('ifr-aa-rfa').value      = r.rfa_number || '';
    ifr_$('ifr-aa-project').value  = r.project_name || '';
    // the items already on the record: shown, not editable
    const old = (r.items || []).map(it => `<div class="ifr-itm">
        <div class="c c-desc"><input type="text" class="amp-input" readonly tabindex="-1" value="${amp_attrEsc(it.description || '')}"></div>
        <div class="c c-cost"><input type="text" class="amp-input amp-num" readonly tabindex="-1" value="${amp_attrEsc(amp_fmtMoney(it.cost))}"></div>
        <div class="c c-pct"><input type="text" class="amp-input amp-num" readonly tabindex="-1" value="${amp_attrEsc(amp_fmtMoney(it.accomplishment_pct))}"></div>
        <div class="c c-x"></div></div>`).join('');
    ifr_$('ifr-aa-old').innerHTML = old;
    ifr_$('ifr-aa-new').innerHTML = `<div class="ifr-itm">
        <div class="c c-desc"><div class="amp-combo"><input type="text" class="amp-input ifr-i-desc" id="ifr-aa-desc" maxlength="1000" autocomplete="off" placeholder="Select from the list or type the work item (required)"><div class="amp-combo-list"></div></div></div>
        <div class="c c-cost"><input type="text" inputmode="decimal" class="amp-input amp-num" id="ifr-aa-cost" placeholder="0.00"></div>
        <div class="c c-pct"><input type="text" inputmode="decimal" class="amp-input amp-num" id="ifr-aa-pct" placeholder="0.00"></div>
        <div class="c c-x"></div></div>`;
    ifr_swaCombo(ifr_$('ifr-aa-new').querySelector('.amp-combo'), ifr_aaRefresh);
    const cost = ifr_$('ifr-aa-cost'), pct = ifr_$('ifr-aa-pct'), desc = ifr_$('ifr-aa-desc');
    cost.addEventListener('input', () => { cost.value = amp_liveFmt(cost.value, true); ifr_aaRefresh(); });
    cost.addEventListener('blur', () => { if (cost.value) cost.value = amp_fmtMoney(amp_unfmt(cost.value)); });
    pct.addEventListener('input', () => {                                   // 0 – 100, 2 decimals (same as the Statement of Work rows)
        let s = pct.value.replace(/[^0-9.]/g, '');
        const d = s.indexOf('.');
        if (d !== -1) s = s.slice(0, d + 1) + s.slice(d + 1).replace(/\./g, '').slice(0, 2);
        if (parseFloat(s) > 100) s = '100';
        pct.value = s;
        ifr_aaRefresh();
    });
    pct.addEventListener('blur', () => { if (pct.value) pct.value = amp_fmtMoney(pct.value); });
    desc.addEventListener('input', ifr_aaRefresh);
    ifr_aaHideErr();
    ifr_aaRefresh();
    ifr_$('ifr-aa-overlay').classList.add('open');
    desc.focus();
}

/* the new row's message when it can't be added yet, '' when it can; also paints the totals and the red boxes */
function ifr_aaRefresh() {
    const nc = Math.round((parseFloat(amp_unfmt(ifr_$('ifr-aa-cost').value)) || 0) * 100);
    const np = Math.round((parseFloat(amp_unfmt(ifr_$('ifr-aa-pct').value)) || 0) * 100);
    const totC = ifr_aa.usedC + nc, totP = ifr_aa.usedP + np;
    const overC = totC > ifr_aa.entC, overP = totP > 10000;
    ifr_$('ifr-aa-cost').classList.toggle('ifr-over', overC);
    ifr_$('ifr-aa-pct').classList.toggle('ifr-over', overP);
    ifr_$('ifr-aa-cost-total').textContent = ifr_money(totC / 100);
    ifr_$('ifr-aa-cost-total').style.color = overC ? 'var(--afims-error, #C62828)' : '';
    ifr_$('ifr-aa-pct-total').textContent = ifr_pct(totP / 100);
    ifr_$('ifr-aa-pct-total').style.color = overP ? 'var(--afims-error, #C62828)' : '';
    ifr_$('ifr-aa-left').innerHTML = 'Cost left <strong>' + ifr_money(Math.max(0, ifr_aa.entC - totC) / 100) + '</strong>'
        + '&nbsp;&nbsp;·&nbsp;&nbsp;Accomplishment left <strong>' + ifr_pct(Math.max(0, 10000 - totP) / 100) + '</strong>';
    const warn = [];
    if (overC) warn.push('The cost would total ₱' + ifr_money(totC / 100) + ', which exceeds the Account Entries total of ₱' + ifr_money(ifr_aa.entC / 100) + '.');
    if (overP) warn.push('The accomplishment would reach ' + ifr_pct(totP / 100) + ', which exceeds 100%.');
    const w = ifr_$('ifr-aa-warn');
    w.style.display = warn.length ? 'flex' : 'none';
    w.innerHTML = '<span>&#9888;</span><span>' + warn.join('<br>') + '</span>';
    const bad = !ifr_$('ifr-aa-desc').value.trim() ? 'The description is required.' : (warn.length ? warn[0] : '');
    ifr_$('ifr-aa-save').disabled = !!bad;
    return bad;
}

async function ifr_aaSave(ev) {
    ev.preventDefault();
    ifr_aaHideErr();
    const bad = ifr_aaRefresh();
    if (bad) { ifr_aaShowErr(bad); return; }
    const btn = ifr_$('ifr-aa-save');
    btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${IFR_API}/add-accomplishment?id=${encodeURIComponent(ifr_aa.row.swa_id)}`, {
            method: 'POST', credentials: 'same-origin',
            headers: amp_authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({
                description: ifr_$('ifr-aa-desc').value.trim(),
                cost: amp_unfmt(ifr_$('ifr-aa-cost').value),
                accomplishment_pct: amp_unfmt(ifr_$('ifr-aa-pct').value),
            }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to add the accomplishment.');
        ifr_aaClose();
        amp_toast(json.message || 'Accomplishment added.', 'success');
        ifr_load();
    } catch (e) {
        ifr_aaShowErr(e.message);
        btn.disabled = false;
    } finally {
        window.PagePreloader?.hide();
    }
}

(function ifr_aaBind() {
    const run = () => {
        ifr_$('ifr-aa-close')?.addEventListener('click', ifr_aaClose);
        ifr_$('ifr-aa-cancel')?.addEventListener('click', ifr_aaClose);
        ifr_$('ifr-aa-form')?.addEventListener('submit', ifr_aaSave);
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
})();

function amp_view_set(title, ref, html) {
    ifr_$('amp-view-body').innerHTML = html;
    ifr_$('amp-view-card').classList.remove('amp-wide', 'u-modal-2xl'); ifr_$('amp-view-card').classList.add('u-modal-xl');
    ifr_$('amp-view-title').textContent = title;
    ifr_$('amp-view-ref').textContent = ref;
    ifr_$('amp-view-overlay').classList.add('open');
}

/* ── Pro-forma JEV (the deliveries tab's pro-forma window, in 'infra' mode) ──
   Each account entry is a debit; the total is credited to the contractor's AP sub-ledger, else Account Payable 1 (2-01-01-010-01).
   Book / Particulars / editable rows / Submit (auto-submitted for approval) / Print JEV / read-only after submit all work as there. */
async function ifr_proforma(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${IFR_API}/proforma?id=${encodeURIComponent(id)}`, { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the pro-forma.');
        const d = json.data;
        await amp_loadBooks();
        const rc = {
            dr_number: d.swa_number, delivery_date: '', rows: d.rows, total: d.total, rfa_number: d.rfa_number || '', office: d.requesting_office || '', program: d.program_name || '', project_code: d.project_code || '', project: d.project_name || '', location: d.location || '', contractor: d.contractor || '',
            jev_id: d.jev_id, jev_no: d.jev_no,
            particulars: 'To record the infrastructure SWA ' + d.swa_number + ' (RFA ' + d.rfa_number + ') — ' + (d.project_name || 'project') + (d.location ? ', ' + d.location : ''),
            print_particulars: 'Pro-forma JEV — Infrastructure SWA No. ' + d.swa_number + ' of RFA No. ' + d.rfa_number,
        };
        amp_pf = { po: d.rfa_number, iar: d.swa_number, rc: d.requesting_rc_id || '', receipts: [rc], kind: 'infra', id: d.id };
        if (rc.jev_id) {                                   // already submitted: read-only, showing the JEV's own book, particulars and lines
            try {
                const jr = await fetch(IFR_API.replace(/\/infra-iar$/, '') + '/voucher/journal-entry/' + encodeURIComponent(rc.jev_id), { credentials: 'same-origin', headers: amp_authHeaders() });
                const jj = await jr.json();
                if (jj.success) {
                    rc.jev_status = jj.data.jev_status || '';
                    rc.jev_date   = jj.data.jev_date || '';
                    rc.draft = {
                        rows: (jj.data.lines || []).map(l => ({ code: l.account_code, title: l.account_title || '', debit: Number(l.debit) || 0, credit: Number(l.credit) || 0 })),
                        book: jj.data.book_id || '', particulars: jj.data.particulars || '',
                    };
                }
            } catch (e) { /* keep the pro-forma rows */ }
        }
        amp_renderProforma(0);
        ifr_$('amp-view-card').classList.add('amp-wide', 'u-modal-2xl'); ifr_$('amp-view-card').classList.remove('u-modal-xl');
        ifr_$('amp-view-title').textContent = 'Pro-forma JEV';
        ifr_$('amp-view-ref').textContent = d.swa_number || '';
        ifr_$('amp-view-overlay').classList.add('open');
    } catch (e) {
        amp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Add / Edit modal ──────────────────────────────────────── */
const ifr_state = { valid: 0, contract: null, durManual: false, locked: false };   // durManual = the user typed the contract duration (the dates no longer overwrite it)

/* Contract Duration = the calendar days from the Date Started to the Completion Date (Revised), both days counted — filled in
   automatically while both dates are set, unless the user typed their own duration. */
function ifr_durationFor(start, end) {
    if (String(start || "").length !== 10 || String(end || "").length !== 10 || end < start) return "";
    const days = Math.round((Date.UTC(+end.slice(0, 4), +end.slice(5, 7) - 1, +end.slice(8)) - Date.UTC(+start.slice(0, 4), +start.slice(5, 7) - 1, +start.slice(8))) / 86400000) + 1;
    return days + ' calendar day' + (days === 1 ? '' : 's');
}
function ifr_autoDuration() {
    if (ifr_state.durManual) return;
    ifr_$('ifr-duration').value = ifr_durationFor(ifr_$('ifr-start').value, ifr_$('ifr-end').value);
}      // valid = allocation_id picked from the list; contract = its contract cost

function ifr_showErr(msg) { const el = ifr_$('ifr-err'); el.textContent = msg; el.style.display = 'block'; }
/* a signed amount: +1,000.00 / \u2212500.00 / 0.00 */
function ifr_signed(v) { v = Number(v) || 0; return (v > 0 ? '+' : (v < 0 ? '\u2212' : '')) + (amp_fmtMoney(Math.abs(v)) || '0.00'); }
function ifr_hideErr() { ifr_$('ifr-err').style.display = 'none'; }
function ifr_isEdit() { return ifr_$('ifr-mode').value === 'edit'; }

/* fill the project fields from an RFA (picker row) or a saved record */
function ifr_fillProject(p) {
    const set = (id, v) => { ifr_$(id).value = v ?? ''; };
    set('ifr-office', p?.requesting_office || '');
    set('ifr-project', p?.project_name || '');
    set('ifr-location', p?.location || '');
    set('ifr-program', p?.program_name || '');
    set('ifr-contractor', p?.contractor || '');
    set('ifr-contract-cost', p && p.contract_cost !== null && p.contract_cost !== undefined ? amp_fmtMoney(p.contract_cost) : '');
    set('ifr-original-cost', p && p.original_contract_cost !== null && p.original_contract_cost !== undefined ? amp_fmtMoney(p.original_contract_cost) : '');
    const vo = p && p.variation_order !== null && p.variation_order !== undefined ? Number(p.variation_order) : null;   // read-only: revised - original
    set('ifr-variation', vo === null ? '' : ifr_signed(vo));
    ifr_$('ifr-variation').className = 'amp-input amp-num' + (vo > 0 ? ' pos' : (vo < 0 ? ' neg' : ''));
    set('ifr-contract', p?.contract || p?.reference_number || '');   // an RFA that carries a contract / reference number (not a PO) starts with it as the Contract
    const cap = p ? (p.rfa_amount ?? p.contract_cost) : null;                    // the account entries may not exceed the RFA amount
    ifr_state.contract = cap !== null && cap !== undefined ? Number(cap) : null;
}

function ifr_openModal(mode, row = null) {
    ifr_hideErr();
    ifr_$('ifr-form').reset();
    ifr_$('ifr-mode').value = mode;
    ifr_$('ifr-id').value = row?.swa_id || '';
    ifr_state.locked = mode === 'edit';                        // the account entries are not editable (and hidden) once the record exists
    ifr_$('ifr-form').classList.toggle('ifr-edit', mode === 'edit');
    ifr_$('ifr-modal-title').textContent = mode === 'edit' ? 'Edit Infrastructure Record' : 'Add Infrastructure Record';
    ifr_$('ifr-rfa').value = ifr_state.validLabel = row?.rfa_number || '';
    ifr_state.valid = row ? Number(row.allocation_id) : 0;
    ifr_state.cat   = '';                                      // set when an RFA is picked
    ifr_$('ifr-swa').value = row?.swa_number || '';
    if (mode === 'add') ifr_loadNextNumber();
    ifr_fillProject(row);
    if (mode === 'edit') ifr_state.contract = null;            // the RFA amount is checked by the server on a save
    ifr_$('ifr-land-area').value = row && row.total_land_area !== null && row.total_land_area !== undefined && row.total_land_area !== '' ? amp_fmtMoney(row.total_land_area) : '';
    ifr_$('ifr-tct').value = row?.tct_number || '';
    ifr_$('ifr-start').value = row?.date_started || '';
    ifr_$('ifr-end').value = row?.completion_date_revised || '';
    ifr_$('ifr-duration').value = row?.contract_duration || '';
    // a saved duration that is not what the dates give was typed by the user: keep it; otherwise it follows the dates
    ifr_state.durManual = !!row?.contract_duration && row.contract_duration !== ifr_durationFor(row.date_started, row.completion_date_revised);
    ifr_autoDuration();
    ifr_$('ifr-entries').innerHTML = '';
    (row?.entries?.length ? row.entries : [{}]).forEach(en => ifr_addEntry(en));
    ifr_$('ifr-items').innerHTML = '';
    (row?.items?.length ? row.items : [{}]).forEach(it => ifr_addItem(it));
    // the RFA and the SWA number identify the record: shown but not editable
    [['ifr-rfa', mode === 'edit']].forEach(([id, lock]) => { const el = ifr_$(id); el.readOnly = lock; el.tabIndex = lock ? -1 : 0; });
    ifr_refresh();
    ifr_$('ifr-overlay').classList.add('open');
    if (mode === 'add') ifr_$('ifr-rfa').focus();
}

/* the SWA number the new record will get (GET /infra-iar/next-number); it is assigned again by the server on save */
async function ifr_loadNextNumber() {
    try {
        const res = await fetch(`${IFR_API}/next-number`, { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (json.success && ifr_$('ifr-mode').value === 'add') ifr_$('ifr-swa').value = json.data.swa_number || '';
    } catch (e) { /* the number is still generated on save */ }
}

function ifr_closeModal() {
    ifr_$('ifr-overlay').classList.remove('open');
    ifr_hideErr();
}

async function ifr_edit(i) {
    const r = ifr_rows[i];
    if (!r) return;
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${IFR_API}/read?id=${encodeURIComponent(r.swa_id)}`, { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the record.');
        ifr_openModal('edit', json.data);
    } catch (e) {
        amp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* RFA picker: searches the approved Infrastructure RFAs that have no PO (GET /infra-iar/rfas) */
(function ifr_initRfaCombo() {
    const wrap = ifr_$('ifr-rfa-combo');
    if (!wrap) return;
    const input = wrap.querySelector('#ifr-rfa'), list = wrap.querySelector('.amp-combo-list');
    let typed = false, timer = null, seq = 0, hits = [];
    const render = () => {
        list.innerHTML = hits.length
            ? hits.map((p, i) => '<div class="amp-combo-opt' + (Number(p.allocation_id) === ifr_state.valid ? ' active' : '') + '" data-i="' + i + '">'
                + '<strong>' + amp_textEsc(p.rfa_number) + '</strong>'
                + (p.reference_number ? '<span style="margin-left:8px;font-size:12px;color:var(--afims-primary,#1d4ed8);">Ref. ' + amp_textEsc(p.reference_number) + '</span>' : '')
                + '<span style="float:right;color:var(--afims-text-disabled,#6b7280);font-size:12px;margin-left:12px;">' + amp_textEsc(p.project_name || '') + '</span></div>').join('')
            : '<div class="amp-combo-empty">No approved Infrastructure RFA (no PO, or a contract / reference number instead of a PO)</div>';
    };
    const load = async () => {
        const mine = ++seq;
        try {
            const q = typed ? input.value.trim() : '';
            const res = await fetch(IFR_API + '/rfas?search=' + encodeURIComponent(q), { credentials: 'same-origin', headers: amp_authHeaders() });
            const json = await res.json();
            if (mine !== seq) return;
            hits = json.success ? (json.data || []) : [];
        } catch (e) { if (mine !== seq) return; hits = []; }
        render();
    };
    const open = () => { if (input.readOnly) return; wrap.classList.add('open'); render(); load(); };
    const hasData = () => [...document.querySelectorAll('#ifr-entries .amp-ent')].some(r => r.querySelector('.ifr-f-acct').dataset.code || r.querySelector('.ifr-f-amount').value.trim());
    const pick = async p => {
        const changing = !!ifr_state.valid && ifr_state.valid !== Number(p.allocation_id);
        if (changing && hasData()) {
            wrap.classList.remove('open');
            const yes = await amp_confirm({
                title: 'Change the RFA?',
                html: '<p>You are changing the RFA from <strong>' + amp_textEsc(ifr_state.validLabel) + '</strong> to <strong>' + amp_textEsc(p.rfa_number) + '</strong>.</p>'
                    + '<div class="amp-cf-warn"><span>&#9888;</span><span>The account entries already added will be replaced by the new RFA\'s accounts.</span></div>',
                ok: 'Change RFA',
            });
            if (!yes) { input.value = ifr_state.validLabel; typed = false; return; }
        }
        input.value = ifr_state.validLabel = p.rfa_number;
        ifr_state.valid = Number(p.allocation_id);
        ifr_state.cat   = p.category || '';                    // 'CIP' | 'WIP': the group the Account Code picker highlights for this RFA
        ifr_fillProject(p);
        typed = false;
        wrap.classList.remove('open');
        ifr_$('ifr-entries').innerHTML = '';
        const lines = (p.lines || []).filter(l => Number(l.amount) > 0);
        (lines.length ? lines : [{}]).forEach(l => ifr_addEntry(l));          // pre-filled from the RFA
        ifr_refresh();
        setTimeout(ifr_openNextPick, 0);                                      // a line the RFA could not fill: open its CIP / WIP choice right away
    };
    input.addEventListener('focus', () => { typed = false; input.select(); open(); });
    input.addEventListener('click', () => { if (!wrap.classList.contains('open')) open(); });
    input.addEventListener('input', () => { typed = true; wrap.classList.add('open'); clearTimeout(timer); timer = setTimeout(load, 250); });
    list.addEventListener('mousedown', e => {
        const opt = e.target.closest('.amp-combo-opt');
        if (!opt) return;
        e.preventDefault();
        pick(hits[+opt.dataset.i]);
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') wrap.classList.remove('open');
        if (e.key === 'Enter' && wrap.classList.contains('open')) { e.preventDefault(); if (hits[0]) pick(hits[0]); }
    });
    // only an RFA picked from the list is kept: half-typed text goes back to the last selection
    input.addEventListener('blur', () => {
        wrap.classList.remove('open');
        typed = false;
        if (input.value !== (ifr_state.validLabel || '')) input.value = ifr_state.validLabel || '';
    });
})();

/* ── Account entries (account code + amount), pre-filled from the RFA ── */
function ifr_acctCombo(wrap) {
    const input = wrap.querySelector('.ifr-f-acct'), list = wrap.querySelector('.amp-combo-list');
    let typed = false, timer = null, seq = 0, hits = [];
    const label = a => a.account_code + ' - ' + a.account_title;
    const rowCat = () => wrap.closest('.amp-ent')?.dataset.pick || ifr_state.cat || '';   // a row the RFA could not fill uses its own group (CIP | WIP)
    const render = () => {
        // The CIP / WIP categories are listed first under a CIP / WIP heading (the server tags them with `group`). The one that fits the
        // picked RFA is highlighted: CIP when the RFA's accounts are the capital-asset ones (building, land, leased assets improvements, …),
        // else WIP (ifr_state.cat, from the server). With no RFA picked yet both are highlighted.
        let last = null;
        const cat  = rowCat();
        const fits = g => !cat || g === cat;
        list.innerHTML = hits.length
            ? hits.map((a, i) => {
                const head = a.group && a.group !== last
                    ? '<div class="ifr-acct-group' + (fits(a.group) ? '' : ' ifr-acct-dim') + '">' + amp_textEsc(a.group === 'CIP' ? 'CIP — Construction in Progress' : 'WIP — Work in Process')
                        + (cat && fits(a.group) ? ' · for this RFA' : '') + '</div>'
                    : '';
                last = a.group || last;
                return head + '<div class="amp-combo-opt' + (a.group && fits(a.group) ? ' ifr-acct-hl' : '') + (a.account_code === input.dataset.code ? ' active' : '') + '" data-i="' + i + '">' + amp_textEsc(label(a)) + '</div>';
            }).join('')
            : '<div class="amp-combo-empty">No matching account code</div>';
    };
    const load = async () => {
        const mine = ++seq;
        try {
            const q = typed ? input.value.trim() : '';
            const res = await fetch(AMP_FORM_API + '/accounts?infra=1&cat=' + encodeURIComponent(rowCat()) + '&search=' + encodeURIComponent(q), { credentials: 'same-origin', headers: amp_authHeaders() });   // every account (infrastructure uses more than the property accounts)
            const json = await res.json();
            if (mine !== seq) return;
            hits = json.success ? (json.data || []) : [];
        } catch (e) { if (mine !== seq) return; hits = []; }
        render();
    };
    const open = () => { if (input.readOnly) return; wrap.classList.add('open'); render(); load(); };
    const pick = a => {
        // only ONE CIP / WIP account per record (not several, not CIP with WIP); other accounts can be added freely
        if (a.group && [...document.querySelectorAll('#ifr-entries .ifr-f-acct')].some(i => i !== input && i.dataset.grp)) {
            wrap.classList.remove('open');
            if (typeof amp_toast === 'function') amp_toast('Only one CIP or WIP account is allowed — CIP / WIP accounts cannot be repeated or combined.', 'error');
            else alert('Only one CIP or WIP account is allowed — CIP / WIP accounts cannot be repeated or combined.');
            return;
        }
        input.dataset.grp = a.group || '';
        input.value = input.dataset.label = label(a); input.dataset.code = a.account_code; typed = false; wrap.classList.remove('open');
        wrap.closest('.amp-ent')?.removeAttribute('data-pick');
        input.classList.remove('ifr-needs-pick');
        input.title = '';
        ifr_refresh();
        ifr_openNextPick();                                   // the next RFA line still waiting for a CIP / WIP choice
    };
    input.addEventListener('focus', () => { typed = false; input.select(); open(); });
    input.addEventListener('click', () => { if (!wrap.classList.contains('open')) open(); });
    input.addEventListener('input', () => { typed = true; wrap.classList.add('open'); clearTimeout(timer); timer = setTimeout(load, 250); });
    list.addEventListener('mousedown', e => { const opt = e.target.closest('.amp-combo-opt'); if (!opt) return; e.preventDefault(); pick(hits[+opt.dataset.i]); });
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') wrap.classList.remove('open');
        if (e.key === 'Enter' && wrap.classList.contains('open')) { e.preventDefault(); if (hits[0]) pick(hits[0]); }
    });
    input.addEventListener('blur', () => {
        wrap.classList.remove('open');
        typed = false;
        if (input.value !== (input.dataset.label || '')) input.value = input.dataset.label || '';
        if (!input.value) { input.dataset.code = ''; input.dataset.grp = ''; }
        ifr_refresh();
    });
}

/* Opens the account picker of the first entry row still waiting for a CIP / WIP choice (data-pick, set by ifr_addEntry()) */
function ifr_openNextPick() {
    const row = [...document.querySelectorAll('#ifr-entries .amp-ent[data-pick]')].find(r => !r.querySelector('.ifr-f-acct').dataset.code);
    if (!row) return;
    const input = row.querySelector('.ifr-f-acct');
    input.scrollIntoView({ block: 'nearest' });
    input.focus();                                            // the combo opens (and loads the group's accounts) on focus
}

const IFR_CIPWIP = ['1-06-99-010', '1-06-99-020', '1-06-99-030', '1-06-99-020-01', '1-06-99-020-02', '1-06-99-020-03', '1-06-99-020-04',
                    '1-04-03-020', '1-04-03-020-01', '1-04-03-020-02', '1-04-03-020-03', '1-04-03-020-04', '1-04-03-020-05'];

function ifr_addEntry(en = {}) {
    const el  = document.createElement('div');
    el.className = 'amp-ent';
    const a   = Number(en.amount || 0);
    // en.pick ('CIP' | 'WIP', from the server): the RFA's account is not valid as an Account Entry (a capital-asset account, or the WIP control
    // account that has sub-ledgers) — the row starts blank (the amount is kept) and its picker opens on that group for the user to choose
    // The RFA's own account is shown IN the box ("1-06-04-010 - Buildings") so it is clear which RFA account the amount comes from, but it is not
    // a valid choice: the code is left empty (data-code) and the box is marked until the user picks a CIP / WIP account from the list.
    const rfaLbl = en.pick ? en.account_code + (en.account_title ? ' - ' + en.account_title : '') : '';
    if (en.pick) {
        el.dataset.pick = en.pick;
        en = { ...en, account_code: '', account_title: '' };
    }
    const lbl = rfaLbl || (en.account_code ? en.account_code + (en.account_title ? ' - ' + en.account_title : '') : '');
    el.innerHTML = `
        <div class="amp-combo">
            <input type="text" class="amp-input ifr-f-acct${rfaLbl ? ' ifr-needs-pick' : ''}" placeholder="Search account code or title" value="${amp_attrEsc(lbl)}"
                   title="${rfaLbl ? 'The RFA account is not valid here — choose a ' + el.dataset.pick + ' account from the list' : ''}"
                   data-code="${amp_attrEsc(en.account_code || '')}" data-label="${amp_attrEsc(lbl)}">
            <div class="amp-combo-list"></div>
        </div>
        <input type="text" inputmode="decimal" class="amp-input ifr-f-amount amp-num" placeholder="0.00" value="${a > 0 ? amp_attrEsc(amp_fmtMoney(a)) : ''}">
        <button type="button" class="amp-link-danger amp-ent-remove" title="Remove row">&times;</button>`;
    if (IFR_CIPWIP.includes(en.account_code)) el.querySelector('.ifr-f-acct').dataset.grp = 'x';   // this row holds the record's one CIP / WIP account
    ifr_acctCombo(el.querySelector('.amp-combo'));
    if (ifr_state.locked) el.querySelectorAll('input').forEach(i => { i.readOnly = true; i.tabIndex = -1; });      // not editable once the record exists
    const amt = el.querySelector('.ifr-f-amount');
    amt.addEventListener('input', () => { amt.value = amp_liveFmt(amt.value, true); ifr_refresh(); });
    amt.addEventListener('blur', () => { if (amt.value) amt.value = amp_fmtMoney(amp_unfmt(amt.value)); });
    el.querySelector('.amp-ent-remove').addEventListener('click', () => { el.remove(); ifr_refresh(); });
    ifr_$('ifr-entries').appendChild(el);
    ifr_refresh();
    return el;
}

/* the total cost of the Statement of Work rows, in cents */
function ifr_itemsCents(items) { return items.reduce((s, r) => s + Math.round((r.cost || 0) * 100), 0); }

function ifr_entriesCents() {
    let c = 0;
    document.querySelectorAll('#ifr-entries .amp-ent').forEach(r => { c += Math.round((parseFloat(amp_unfmt(r.querySelector('.ifr-f-amount').value)) || 0) * 100); });
    return c;
}

/* ── Statement of Work rows ────────────────────────────────── */
/* Suggested work items: the user picks one from the list (or types to filter it) and can still edit the text or write their own */
const IFR_SWA_ITEMS = [
    'Mobilization & Demobilization',
    'Temporary Facilities (Field Office, Warehouse, Barracks)',
    'Project Billboard / Signboard',
    'Occupational Safety and Health Program (OSHP)',
    'Permits, Clearances, and Material Testing',
    'Clearing and Grubbing',
    'Removal of Structures and Obstructions',
    'Structure Excavation (Common Soil / Rock)',
    'Embankment / Fill & Compaction',
    'Subgrade Preparation',
    'Gravel Bedding / Aggregate Base Course',
    'Micropiles (Drilling, Grouting, and Steel Casing/Reinforcement)',
    'Bored Piles / Driven Concrete Piles',
    'Pile Cap and Tie Beam Excavation',
    'Soil Poisoning / Termite Control',
    'Lean Concrete / Blinding',
    'Structural Concrete (Footings, Columns, Beams, Suspended Slabs, Stairs)',
    'Reinforcing Steel Bars (Deformed Bars)',
    'Formworks and Falseworks (Scaffolding)',
    'Masonry Including Reinforcing Bars (CHB Laying, Dowels)',
    'Cement Plaster Finish (Interior and Exterior)',
    'Steel Framing (Columns, Rafters, Beams, Base Plates, Anchor Bolts)',
    'Steel Deck (Floor Decking Panels and Shear Studs)',
    'Roof Framing (Steel Trusses, Purlins, Sag Rods, Cross Bracing)',
    'Miscellaneous Metal Works (Railings, Grilles, Steel Doors/Frames)',
    'Roofing Sheets & Accessories (GI Sheets, Ridge Rolls, Flashing, Gutters)',
    'Ceiling Works (Fiber Cement / Gypsum Board on Metal Furring)',
    'Doors and Windows (Flush/Panel Doors, Casement/Sliding Windows)',
    'Tileworks & Floor Finishes (Ceramic/Porcelain Tiles, Plain Cement Finish)',
    'Painting Works (Masonry, Wood, and Metal Painting)',
    'Waterline Distribution System (Pipes, Fittings, Valves)',
    'Sewer and Drainage Piping (uPVC Sanitary Pipes, Catch Basins, Septic Tank)',
    'Plumbing Fixtures and Accessories (Water Closets, Lavatories, Faucets, Floor Drains)',
    'Conduits, Boxes, and Fittings (uPVC / RSC Pipes)',
    'Wires and Wiring Devices (THHN/THW Wires, Switches, Convenience Outlets)',
    'Panelboards, Circuit Breakers, and Service Entrance',
    'Lighting Fixtures',
    'Road Network (Portland Cement Concrete Pavement / PCCP, Curbs, Gutters, Sidewalks)',
    'Drainage System (Reinforced Concrete Pipe Culverts / RCPC, Open/Covered Canals, Manholes)',
    'Water Distribution Network & Elevated Water Tank',
    'Power Distribution Line (Concrete Poles, Transformers, Streetlights)',
    'Perimeter Fence and Retaining Walls / Slope Protection (Riprap / Grouted Riprap)',
];
/* the Description box: the suggestions drop down below it (all of them on focus, filtered while typing); picking one fills the box, and any other text can still be typed */
function ifr_swaCombo(wrap, onPick = ifr_refresh) {
    const input = wrap.querySelector('.ifr-i-desc'), list = wrap.querySelector('.amp-combo-list');
    let hits = [], typed = false;
    const render = () => {
        const q = typed ? input.value.trim().toLowerCase() : '';
        hits = IFR_SWA_ITEMS.filter(t => !q || t.toLowerCase().includes(q));
        list.innerHTML = hits.length
            ? hits.map((t, i) => '<div class="amp-combo-opt' + (t === input.value.trim() ? ' active' : '') + '" data-i="' + i + '">' + amp_textEsc(t) + '</div>').join('')
            : '<div class="amp-combo-empty">Not on the list: your text will be used as typed</div>';
    };
    const open = () => { wrap.classList.add('open'); render(); };
    input.addEventListener('focus', () => { typed = false; open(); });
    input.addEventListener('click', () => { if (!wrap.classList.contains('open')) open(); });
    input.addEventListener('input', () => { typed = true; open(); });
    list.addEventListener('mousedown', e => {
        const opt = e.target.closest('.amp-combo-opt');
        if (!opt) return;
        e.preventDefault();
        input.value = hits[+opt.dataset.i];
        typed = false;
        wrap.classList.remove('open');
        onPick();
    });
    input.addEventListener('keydown', e => { if (e.key === 'Escape') wrap.classList.remove('open'); if (e.key === 'Enter') { e.preventDefault(); wrap.classList.remove('open'); } });
    input.addEventListener('blur', () => { wrap.classList.remove('open'); typed = false; });
}
function ifr_addItem(it = {}) {
    const el = document.createElement('div');
    el.className = 'ifr-itm';
    const f2 = v => (v === undefined || v === null || v === '') ? '' : amp_fmtMoney(v);
    el.innerHTML = `
        <div class="c c-desc"><div class="amp-combo"><input type="text" class="amp-input ifr-i-desc" maxlength="1000" autocomplete="off" placeholder="Select from the list or type the work item (required)" value="${amp_attrEsc(it.description || '')}"><div class="amp-combo-list"></div></div></div>
        <div class="c c-cost"><input type="text" inputmode="decimal" class="amp-input amp-num ifr-i-cost" placeholder="0.00" value="${amp_attrEsc(f2(it.cost))}"></div>
        <div class="c c-pct"><input type="text" inputmode="decimal" class="amp-input amp-num ifr-i-pct" placeholder="0.00" value="${amp_attrEsc(f2(it.accomplishment_pct))}"></div>
        <div class="c c-x"><button type="button" class="amp-link-danger amp-ent-remove" title="Remove item">&times;</button></div>`;
    const money = inp => {
        inp.addEventListener('input', () => { inp.value = amp_liveFmt(inp.value, true); ifr_refresh(); });
        inp.addEventListener('blur', () => { if (inp.value) inp.value = amp_fmtMoney(amp_unfmt(inp.value)); });
    };
    money(el.querySelector('.ifr-i-cost'));
    const pct = el.querySelector('.ifr-i-pct');
    pct.addEventListener('input', () => {                                   // 0 – 100, 2 decimals
        let s = pct.value.replace(/[^0-9.]/g, '');
        const d = s.indexOf('.');
        if (d !== -1) s = s.slice(0, d + 1) + s.slice(d + 1).replace(/\./g, '').slice(0, 2);
        if (parseFloat(s) > 100) s = '100';
        pct.value = s;
        ifr_refresh();
    });
    pct.addEventListener('blur', () => { if (pct.value) pct.value = amp_fmtMoney(pct.value); });
    ifr_swaCombo(el.querySelector('.c-desc .amp-combo'));
    el.querySelector('.ifr-i-desc').addEventListener('input', ifr_refresh);
    el.querySelector('.amp-ent-remove').addEventListener('click', () => { el.remove(); ifr_refresh(); });
    ifr_$('ifr-items').appendChild(el);
    ifr_refresh();
    return el;
}

function ifr_itemRows() {
    const n = el => parseFloat(amp_unfmt(el.value)) || 0;
    return [...document.querySelectorAll('#ifr-items .ifr-itm')].map(r => ({
        description: r.querySelector('.ifr-i-desc').value.trim(),
        cost: n(r.querySelector('.ifr-i-cost')), pct: n(r.querySelector('.ifr-i-pct')),
        blank: !r.querySelector('.ifr-i-desc').value.trim() && !r.querySelector('.ifr-i-cost').value && !r.querySelector('.ifr-i-pct').value,
    }));
}

/* the message when the record can't be saved yet, '' when it can */
function ifr_error() {
    if (!ifr_isEdit()) {
        if (!ifr_state.valid) return 'Select an RFA number.';
    }
    if (!ifr_state.locked) {
        const rows = [...document.querySelectorAll('#ifr-entries .amp-ent')];
        if (!rows.length) return 'Add at least one account row.';
        for (let i = 0; i < rows.length; i++) {
            if (!rows[i].querySelector('.ifr-f-acct').dataset.code) return 'Account row ' + (i + 1) + ': choose an account code.';
            if (!((parseFloat(amp_unfmt(rows[i].querySelector('.ifr-f-amount').value)) || 0) > 0)) return 'Account row ' + (i + 1) + ': enter an amount greater than 0.';
        }
        if (ifr_state.contract !== null && ifr_entriesCents() > Math.round(ifr_state.contract * 100)) return 'The account entries exceed the RFA amount.';
    }
    if (!((parseFloat(amp_unfmt(ifr_$('ifr-land-area').value)) || 0) > 0)) return 'Enter the Area in SQM / Units (greater than 0).';
    const s = ifr_$('ifr-start').value, e = ifr_$('ifr-end').value;
    if (!s) return 'Enter the Date Started.';
    if (!e) return 'Enter the Completion Date (Revised).';
    if (s && e && e < s) return 'The completion date (revised) cannot be before the date started.';
    if (ifr_isEdit()) return '';                       // the Statement of Work items are not part of the edit
    const items = ifr_itemRows().filter(r => !r.blank);
    if (!items.length) return 'Add at least one item to the Statement of Work Accomplishments.';
    for (let i = 0; i < items.length; i++) {
        if (!items[i].description) return 'Work item ' + (i + 1) + ': the description is required.';
    }
    if (items.reduce((s, r) => s + Math.round(r.pct * 100), 0) > 10000) return 'The % Accomplishment of the items adds up to more than 100%.';
    if (ifr_itemsCents(items) > ifr_entriesCents()) return 'The total cost of the Statement of Work Accomplishments may not exceed the total of the Account Entries.';
    return '';
}

/* totals + the Save button, without redrawing (the inputs keep their focus) */
function ifr_refresh() {
    const cents = ifr_entriesCents();
    ifr_$('ifr-tot-amount').textContent = amp_fmtMoney(cents / 100) || '0.00';
    const over = ifr_state.contract !== null && cents > Math.round(ifr_state.contract * 100);
    ifr_$('ifr-tot-pill').style.display = over ? '' : 'none';
    const items = ifr_itemRows().filter(r => !r.blank);
    const cost = items.reduce((s, r) => s + r.cost, 0);
    const pct  = items.reduce((s, r) => s + r.pct, 0);   // each item's % is its share of the whole project: the overall % is their sum
    ifr_$('ifr-items-pct').style.color = Math.round(pct * 100) > 10000 ? 'var(--afims-error, #C62828)' : '';   // above 100%: red
    ifr_$('ifr-items-cost').textContent = amp_fmtMoney(cost) || '0.00';
    ifr_$('ifr-items-cost').style.color = ifr_itemsCents(items) > cents ? 'var(--afims-error, #C62828)' : '';   // above the Account Entries total: red
    // the work items may not cost more than the account entries: from the item that takes the running total past it, the cost box turns red
    let run = 0, runPct = 0;
    document.querySelectorAll('#ifr-items .ifr-itm').forEach(row => {
        const inp = row.querySelector('.ifr-i-cost');
        run += Math.round((parseFloat(amp_unfmt(inp.value)) || 0) * 100);
        const over = run > cents;
        inp.classList.toggle('ifr-over', over);
        runPct += Math.round((parseFloat(amp_unfmt(row.querySelector('.ifr-i-pct').value)) || 0) * 100);   // the % column adds up to at most 100
        const pinp = row.querySelector('.ifr-i-pct'), pover = runPct > 10000;
        pinp.classList.toggle('ifr-over', pover);
        pinp.title = pover ? 'The % Accomplishment of the items adds up to more than 100%' : '';
        inp.title = over ? 'The items\u2019 total cost exceeds the total of the Account Entries' : '';
    });
    ifr_$('ifr-items-pct').textContent = pct.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '%';
    ifr_$('ifr-save').disabled = !!ifr_error();
}

async function ifr_save(ev) {
    ev.preventDefault();
    ifr_hideErr();
    const bad = ifr_error();
    if (bad) { ifr_showErr(bad); return; }
    const edit = ifr_isEdit();
    const payload = {
        contract: ifr_$('ifr-contract').value.trim(),
        total_land_area: amp_unfmt(ifr_$('ifr-land-area').value),          // Area in SQM / Units (required)
        tct_number: ifr_$('ifr-tct').value.trim(),                         // TCT number (optional)
        date_started: ifr_$('ifr-start').value,
        completion_date_revised: ifr_$('ifr-end').value,
        contract_duration: ifr_$('ifr-duration').value.trim(),
    };
    if (!edit) payload.items = ifr_itemRows().filter(r => !r.blank).map(r => ({ description: r.description, cost: r.cost, accomplishment_pct: r.pct }));
    if (!edit) payload.allocation_id = ifr_state.valid;
    if (!ifr_state.locked) {
        payload.entries = [...document.querySelectorAll('#ifr-entries .amp-ent')].map(r => ({
            account_code: r.querySelector('.ifr-f-acct').dataset.code, amount: amp_unfmt(r.querySelector('.ifr-f-amount').value),
        }));
    }
    ifr_$('ifr-save').disabled = true;
    window.PagePreloader?.show();
    try {
        const url  = edit ? `${IFR_API}/update?id=${encodeURIComponent(ifr_$('ifr-id').value)}` : `${IFR_API}/add`;
        const res  = await fetch(url, { method: edit ? 'PUT' : 'POST', credentials: 'same-origin', headers: amp_authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save.');
        amp_toast(json.message || 'Saved.', 'success');
        ifr_closeModal();
        if (!edit) ifr_page = 1;
        ifr_load();
    } catch (e) {
        ifr_showErr(e.message);
        ifr_refresh();
    } finally {
        window.PagePreloader?.hide();
    }
}
</script>
