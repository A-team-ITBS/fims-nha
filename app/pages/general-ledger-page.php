<?php
namespace PHPMaker2024\FIMS;
@session_start();

// General Ledger — pick ONE chart-of-accounts account, see every posting to it (fims.general_ledger,
// via fims.v_journals) in order with a running balance. Read-only — see GeneralLedgerService.php and
// system_docs/ERD/README.md §2.8. Separate module from Subsidiary Ledger (same idea, keyed by SL
// code instead), Journals (chronological, every account) and Trial Balance (totals, no detail).
//
// Column layout follows the illustrated "DISPLAY IN FIMS" format: account header carries Normal
// Balance/Account Class, each row has its JEV No. and DV Number alongside Debit/Credit/running
// Balance, and the footer reads "Ending Balance as of [date]". (The Doc Ref No. column was removed
// from the list and the SL popup; the ledger API still returns it, and the printed sheets use it.)
// (The Entry Side + single Amount convention in that same illustration is the raw JEV-listing/
// back-end representation — that's Journals (GJ), not this page.)
$_gl_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_gl_api = preg_match('~/api$~', $_gl_api) ? $_gl_api : $_gl_api . '/api';
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #gl-root :is(.nha-mono, .nha-amt, .u-font-mono, .fims-mono, .amt-cell) { font-family: var(--font-body); font-variant-numeric: tabular-nums; } /* plain zero, no dotted-zero glyph */
    #gl-root .gl-combo-wrap { position: relative; }
    #gl-root .gl-combo-results {
        position: absolute; top: calc(100% + 4px); left: 0; right: 0; z-index: 20;
        background: var(--afims-surface); border: 1px solid var(--afims-border, #E2E4ED); border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,.08); max-height: 260px; overflow-y: auto; display: none;
    }
    #gl-root .gl-combo-item { padding: 8px 12px; cursor: pointer; font-size:var(--fs-base); }
    #gl-root .gl-combo-item:hover { background: var(--afims-surface-subtle); }
    #gl-root .gl-combo-empty { padding: 8px 12px; font-size:var(--fs-base); color: var(--afims-text-muted,#62657E); }
    #gl-root .gl-summary-cards { display: flex; gap: 12px; flex-wrap: wrap; margin: 0 0 14px; }
    #gl-root .gl-summary-card {
        flex: 1 1 160px; border: 1px solid var(--afims-border, #E2E4ED); border-radius: 10px;
        padding: 12px 14px; background: var(--afims-surface);
    }
    #gl-root .gl-summary-label { font-size:var(--fs-xs); color: var(--afims-text-muted,#62657E); text-transform: uppercase; letter-spacing: .03em; }
    #gl-root .gl-summary-value { font-size:var(--fs-xl); font-weight: 600; margin-top: 4px; }
    #gl-root .gl-group-row td { background: var(--afims-surface-subtle); font-weight: 600; }
    #gl-root .gl-ending-balance { text-align: right; padding: 8px 4px; font-size:var(--fs-base); color: var(--afims-text-muted,#62657E); }
    #gl-root .gl-ending-balance strong { color: inherit; font-weight: 700; }
    #gl-root .gl-group-row.gl-clickable { cursor: pointer; }
    #gl-root .gl-group-row.gl-clickable:hover td { filter: brightness(.96); }
    #gl-root .gl-node .gl-lbl { padding-left: calc(12px + var(--gl-depth, 0) * 22px); }
    #gl-root .gl-txn td:first-child { padding-left: calc(12px + var(--gl-depth, 0) * 22px); }
    #gl-root .gl-k-group td { background: var(--afims-surface-subtle); font-weight: 700; }
    #gl-root .gl-k-gl td { font-weight: 600; }
    #gl-root .gl-k-gl .gl-lbl { color: var(--afims-primary-text); }
    #gl-root .gl-k-sl td { font-weight: 400; }
    #gl-root .gl-node.gl-clickable { cursor: pointer; }
    #gl-root .gl-node.gl-clickable:hover td { filter: brightness(.96); }
    #gl-root .gl-combo-wrap .gl-search-icon { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: var(--afims-text-muted, #62657E); pointer-events: none; z-index: 1; }
    /* light column header of the GL tree (Account code / Account description / Debit / Credit / Balance) and the SL preview modal */
    #gl-root thead.gl-head-light th { background: transparent !important; color: var(--afims-text-muted, #62657E) !important; font-size:var(--fs-xs); letter-spacing: .04em; text-transform: uppercase; border-bottom: 1px solid var(--afims-border, #E2E4ED); }
    #gl-root .gl-link { color: var(--afims-primary-text); text-decoration: none; cursor: pointer; font-weight: 500; font-size: var(--fs-sm); }
    #gl-sl-overlay .nha-tbl td, #gl-sl-overlay .nha-tbl th { white-space: nowrap; }
    /* transaction header row shown above an expanded SL's postings in the popup — same look as the dark table header */
    #gl-sl-overlay .nha-tbl tr.gl-txn-head th { background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF); padding: 11px 12px; font-size: var(--fs-xs); font-weight: 700; text-transform: uppercase; letter-spacing: .4px; text-align: left; }
    #gl-sl-overlay .nha-tbl tr.gl-txn-head th.text-end { text-align: right; }
    #gl-sl-overlay .nha-tbl tr.gl-txn-head th:first-child { padding-left: 12px; }
    #gl-sl-overlay .nha-tbl tr.gl-txn-head:hover th { background: var(--afims-heading-fill, #000E3E); }
    /* Particulars wraps and may shrink (was min-width 220px): with the long JEV / DV numbers and peso amounts the table got wider than
       the modal once the rows loaded, and the last column — Balance — was pushed out of view to the right. */
    #gl-sl-overlay .nha-tbl td:nth-child(4) { white-space: normal; min-width: 120px; }
    /* ...and on a narrow window the JEV No. / DV Number of a transaction row (the cells after the date cell) may wrap too, so Balance stays in view */
    #gl-sl-overlay .nha-tbl td.nha-td-date + td, #gl-sl-overlay .nha-tbl td.nha-td-date + td + td { white-space: normal; word-break: break-word; }
    #gl-root .gl-amt { color: inherit; text-decoration: none; cursor: pointer; }
    #gl-root .gl-amt:hover { color: var(--afims-primary-text); text-decoration: underline; }
    #gl-root .gl-link .gl-link-icon { margin-left: 6px; vertical-align: -1px; }
    #gl-root .gl-link:hover { text-decoration: underline; }
    #gl-root .gl-code { font-size:var(--fs-sm); color: var(--afims-text-muted, #62657E); margin-right: 8px; }
    #gl-root .gl-abnormal { color: var(--afims-danger); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-k-group td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-k-gl .gl-lbl, html.afims-dark[data-bs-theme="dark"] #gl-root .gl-link { color: #8FB4FF; }
    #gl-root .gl-sl-modal-sub { font-size:var(--fs-sm); color: rgba(255,255,255,.8); margin-top: 2px; font-weight: 400; }
    #gl-root .gl-sl-toggle { display: inline-block; width: 12px; margin-right: 2px; color: var(--afims-text-muted, #62657E); }
    /* Balance column hidden on the page's own table (GL list / one account's ledger) only — the preview modals (#gl-sl-overlay: SL list,
       All transactions) show it. The page table is a direct child of #gl-root; the modals' tables are nested in the overlay. To show it on the page too, delete this rule. */
    #gl-root > .nha-table-wrap .gl-bal-col { display: none; }
    #gl-printall[disabled] { opacity: .7; cursor: progress; }
    #gl-printall-menu .gl-printall-opt { display: block; width: 100%; text-align: left; background: none; border: 0; border-radius: 6px; padding: 8px 10px; cursor: pointer; color: var(--afims-text, #1e293b); font: inherit; font-size:var(--fs-base); }
    #gl-printall-menu .gl-printall-opt:hover { background: var(--afims-surface-subtle, #F4F7FA); }
    #gl-printall-menu .gl-printall-opt small { display: block; color: var(--afims-text-muted, #62657E); font-size:var(--fs-xs); margin-top: 1px; }
    #gl-load-overlay .gl-load-box { padding: 26px 28px 22px; text-align: center; }
    #gl-load-overlay .gl-load-track { background: var(--afims-border, #E2E4ED); height: 8px; border-radius: 4px; margin: 14px 0 10px; overflow: hidden; }
    #gl-load-overlay .gl-load-bar { height: 8px; width: 0; background: var(--afims-primary-text); border-radius: 4px; transition: width .2s; }

    /* Dark mode: --afims-bg-soft is not a theme token, so the account group rows fell back to a near-white #F5F6FA (light text on
       it = unreadable); the account search results and the summary cards were hard-coded white */
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-group-row td { background: var(--afims-surface-subtle, #22294A); color: #FFFFFF; border-top: 1px solid var(--afims-border-strong, #4A5270); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-group-row td span { color: var(--afims-text-muted, #AAAFBE) !important; }
    html.afims-dark[data-bs-theme="dark"] #gl-root :is(.gl-combo-results) { background: var(--afims-surface, #1A1F38); border-color: var(--afims-border-strong, #4A5270); color: var(--afims-text, #E4ECF2); }
    html.afims-dark[data-bs-theme="dark"] #gl-root .gl-combo-item:hover { background: var(--afims-primary-soft, #0F2456); color: #FFFFFF; }
    html.afims-dark[data-bs-theme="dark"] #gl-root :is(.gl-combo-empty, .gl-ending-balance) { color: var(--afims-text-muted, #AAAFBE); }

</style>

<div id="gl-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <!-- same banner as User Access (app-page-header in _shared/css/nha-fims.css) -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h7"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title">General Ledger</h1>
            <p class="app-page-subtitle">Showing every posted account. Pick one below to see just its ledger, oldest first, with a running balance.</p>
        </div>
        <div class="app-page-header-actions">
            <button id="gl-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <!-- Print ▾ in the GL list view (every GL at once): Print GL | Print SL. Still a preview — the report opens in its own
                 window with Print / Excel buttons. Slow, so a loading modal runs first — see gl_printAll(). The account view has its own Print below. -->
            <span id="gl-printall-wrap" style="position:relative;display:none;">
                <button id="gl-printall" type="button" class="nha-btn-hdr" title="Print / preview every GL account — General Ledger or Subsidiary Ledger" aria-haspopup="true" aria-expanded="false">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Print &#9662;
                </button>
            </span>
            <div id="gl-printall-menu" role="menu" class="no-print" style="display:none;position:fixed;z-index:1060;min-width:250px;background:var(--afims-surface,#fff);border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:6px;">
                <button type="button" role="menuitem" class="gl-printall-opt" data-mode="gl-detail">Print GL<small>General Ledger of every account — every transaction, JEV / DV # and check</small></button>
                <button type="button" role="menuitem" class="gl-printall-opt" data-mode="sl-detail">Print SL<small>Subsidiary Ledger of every GL that has SLs — every transaction</small></button>
                <button type="button" role="menuitem" class="gl-printall-opt" data-mode="gl">Print GL — Ledger card<small>NHA ledger card of every account, in yearly blocks</small></button>
                <button type="button" role="menuitem" class="gl-printall-opt" data-mode="sl">Print SL — Ledger card<small>NHA ledger card of every SL, in yearly blocks</small></button>
            </div>
            <span id="gl-print-wrap" style="position:relative;display:none;">
                <button id="gl-print" type="button" class="nha-btn-hdr" title="Print this account — General Ledger or Subsidiary Ledger" aria-haspopup="true" aria-expanded="false">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print &#9662;
            </button>
            </span>
            <div id="gl-print-menu" role="menu" class="no-print" style="display:none;position:fixed;z-index:1060;min-width:280px;background:var(--afims-surface,#fff);border:1px solid var(--afims-border,#E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(15,23,42,.18);padding:6px;">
                <div style="padding:6px 10px 6px;">
                    <strong style="font-size:var(--fs-base);color:var(--afims-text,#1e293b);">Format</strong>
                    <div id="gl-print-fmt" style="display:flex;flex-direction:column;gap:2px;margin-top:4px;font-size:var(--fs-sm);color:var(--afims-text,#1e293b);">
                        <label style="cursor:pointer;"><input type="radio" name="gl-print-fmt" value="detail" checked> Detailed <span style="color:var(--afims-text-muted,#62657E);">— every transaction, JEV / DV #, check</span></label>
                        <label style="cursor:pointer;"><input type="radio" name="gl-print-fmt" value="monthly"> Monthly <span style="color:var(--afims-text-muted,#62657E);">— Form 11 / books sheet</span></label>
                        <label style="cursor:pointer;"><input type="radio" name="gl-print-fmt" value="card"> Ledger card <span style="color:var(--afims-text-muted,#62657E);">— yearly blocks</span></label>
                    </div>
                </div>
                <div style="height:1px;background:var(--afims-border,#E4ECF2);margin:4px 2px;"></div>
                <div style="padding:6px 10px 4px;">
                    <strong style="font-size:var(--fs-base);color:var(--afims-text,#1e293b);">General Ledger</strong>
                    <div style="font-size:var(--fs-xs);color:var(--afims-text-muted,#62657E);">NHA Form No. 11 — pick the book and year to print</div>
                </div>
                <div id="gl-print-sheets" style="max-height:220px;overflow-y:auto;"></div>
                <div style="height:1px;background:var(--afims-border,#E4ECF2);margin:4px 2px;"></div>
                <div style="padding:6px 10px 4px;">
                    <strong style="font-size:var(--fs-base);color:var(--afims-text,#1e293b);">Subsidiary Ledger</strong>
                    <div style="font-size:var(--fs-xs);color:var(--afims-text-muted,#62657E);">A sheet per SL account under it — pick the book and year</div>
                </div>
                <div id="gl-print-sl-sheets" style="max-height:200px;overflow-y:auto;"></div>
                <div style="height:1px;background:var(--afims-border,#E4ECF2);margin:4px 2px;"></div>
                <div style="padding:6px 10px 4px;">
                    <strong style="font-size:var(--fs-base);color:var(--afims-text,#1e293b);">Schedule</strong>
                    <div style="font-size:var(--fs-xs);color:var(--afims-text-muted,#62657E);">Balance by sector / region → office → project → contract</div>
                </div>
                <div style="display:flex;gap:6px;align-items:center;padding:2px 10px 8px;">
                    <label for="gl-sched-asof" style="font-size:var(--fs-sm);color:var(--afims-text-muted,#62657E);white-space:nowrap;">As of</label>
                    <input type="date" id="gl-sched-asof" class="nha-filter-input" style="flex:1;min-width:0;padding:4px 8px;">
                    <button type="button" class="nha-btn" id="gl-sched-go" style="padding:5px 12px;">Preview</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error banner -->
    <div id="gl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="gl-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search" style="flex:1 1 320px;">
                <label class="nha-filter-label">Account</label>
                <div class="gl-combo-wrap">
                    <svg class="gl-search-icon" width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/></svg>
                    <input type="text" id="gl-account-search" class="nha-filter-input" style="width:100%;padding-left:34px;"
                           placeholder="4th-level code or title, e.g. 1-01-02" autocomplete="off">
                    <div id="gl-account-results" class="gl-combo-results"></div>
                </div>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal year</label>
                <select id="gl-filter-fy" class="nha-filter-select">
                    <option value="">All years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Book</label>
                <select id="gl-filter-book" class="nha-filter-select">
                    <option value="">All books</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── SUMMARY (account header — code/name/normal balance/class) ──────── -->
    <div id="gl-summary" class="nha-summary no-print" style="display:none;">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-label">Account</div>
            <div class="nha-sc-val" id="gl-summary-account" style="font-size:var(--fs-md);">—</div>
        </div>
        <div class="nha-sc nha-sc-blue">
            <div class="nha-sc-label">Normal Balance</div>
            <div class="nha-sc-val" id="gl-summary-normal" style="font-size:var(--fs-md);">—</div>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-label">Account Class</div>
            <div class="nha-sc-val" id="gl-summary-class" style="font-size:var(--fs-md);">—</div>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-label">Total Debit</div>
            <div class="nha-sc-val u-tabular-nums" id="gl-summary-debit">₱ 0.00</div>
        </div>
        <div class="nha-sc nha-sc-red">
            <div class="nha-sc-label">Total Credit</div>
            <div class="nha-sc-val u-tabular-nums" id="gl-summary-credit">₱ 0.00</div>
        </div>
    </div>

    <!-- Expand/Collapse All — only meaningful for the grouped (no-account-picked) view, where each
         GL rows are collapsed by default (see gl_toggleNode); hidden once one account is picked. -->
    <div id="gl-expand-toolbar" class="no-print" style="display:none;justify-content:flex-end;gap:8px;margin:0 0 10px;">
        <button id="gl-expand-all" type="button" class="nha-btn-hdr">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4m12-4v4h-4"/></svg>
            Expand All
        </button>
        <button id="gl-collapse-all" type="button" class="nha-btn-hdr">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 4v4H5m10-4v4h4M9 20v-4H5m10 4v-4h4"/></svg>
            Collapse All
        </button>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead id="gl-thead"></thead>
                <tbody id="gl-tbody">
                    <tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div id="gl-ending" class="gl-ending-balance no-print" style="display:none;"></div>

    <style>#gl-print-menu .gl-print-opt:hover:not(:disabled),#gl-print-menu .gl-print-sheet:hover{background:var(--afims-surface-subtle,#F4F7FA);}#gl-print-menu .gl-print-sheet{display:flex;justify-content:space-between;gap:12px;width:100%;text-align:left;background:none;border:0;border-radius:6px;padding:7px 10px;cursor:pointer;color:var(--afims-text,#1e293b);font:inherit;font-size:var(--fs-base);}#gl-print-menu .gl-print-sheet small{color:var(--afims-text-muted,#62657E);font-size:var(--fs-xs);white-space:nowrap;}#gl-print-menu .gl-print-note{padding:6px 10px;font-size:var(--fs-sm);color:var(--afims-text-muted,#62657E);}#gl-print-menu .gl-print-opt:disabled{opacity:.55;cursor:not-allowed !important;}</style>
    <!-- ── LOADING MODAL — shown while Print ▾ → Print GL / Print SL loads every GL, before the preview window opens ── -->
    <div id="gl-load-overlay" class="nha-overlay no-print">
        <div class="nha-modal u-modal-sm gl-load-box" role="dialog" aria-modal="true" aria-labelledby="gl-load-title">
            <h3 class="nha-modal-title" id="gl-load-title" style="margin:0 0 6px;">Preparing report</h3>
            <div id="gl-load-txt" style="font-size:var(--fs-2xl);font-weight:600;">Loading GL 0/0</div>
            <div class="gl-load-track"><div id="gl-load-bar" class="gl-load-bar"></div></div>
            <div id="gl-load-sub" style="font-size:var(--fs-sm);color:var(--afims-text-muted,#62657E);min-height:16px;">Please wait…</div>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:16px;">
                <button type="button" class="nha-btn" id="gl-load-ready" style="display:none;">Open Preview</button>
                <button type="button" class="nha-btn nha-btn-outline" id="gl-load-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ── SL POPUP (click a GL account → its SL accounts; click an SL → sub-SLs / transactions) ── -->
    <div id="gl-sl-overlay" class="nha-overlay no-print">
        <div class="nha-modal u-modal-xl" role="dialog" aria-modal="true" aria-labelledby="gl-sl-title">
            <div class="nha-modal-hdr">
                <div>
                    <h3 class="nha-modal-title" id="gl-sl-title">Subsidiary Ledgers</h3>
                    <div class="gl-sl-modal-sub" id="gl-sl-subtitle"></div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <button type="button" class="nha-btn nha-btn-outline" id="gl-sl-print" style="display:none;" title="Preview the General Ledger report — print or export to Excel">Preview</button>
                    <button type="button" class="nha-modal-close" id="gl-sl-close" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="nha-modal-body">
                <div class="nha-table-wrap">
                    <div class="nha-tbl-scroll">
                        <table class="nha-tbl">
                            <thead id="gl-sl-thead"></thead>
                            <tbody id="gl-sl-tbody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /#gl-root -->

<!-- ExcelJS — the Print ▾ GL / SL (all accounts) Excel export, one tab per GL; same build as the Trial Balance page -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>
<script>
/* ══════════════════════════════════════════════════════════════
   GENERAL LEDGER   prefix: gl_
═══════════════════════════════════════════════════════════════ */

const GL_BASE = <?= json_encode($_gl_api) ?>;
const GL_API  = GL_BASE + '/general_ledger';
const GL_COA_API = GL_BASE + '/chart_of_accounts/all';
const GL_FY_API  = GL_BASE + '/fiscal_year/all';
const GL_BOOK_API = GL_BASE + '/books/all';

let gl_selectedAccount = null; // { account_code, account_title }
let gl_searchTimer = null;
let gl_ledger = null;          // the last single-account ledger loaded (what Print prints); null in the all-accounts view

const $gl = id => document.getElementById(id);

function gl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

// The table header follows the view: the GL tree (account columns) or one account's ledger (transaction columns)
function gl_setHead(tree) {
    const h = $gl('gl-thead');
    h.className = tree ? 'gl-head-light' : '';
    h.innerHTML = tree
        ? '<tr><th>Account code</th><th colspan="3">Account description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end gl-bal-col">Balance</th></tr>'
        : '<tr><th>Posting Date</th><th>JEV No.</th><th>DV Number</th><th>Particulars</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end gl-bal-col">Balance</th></tr>';
}
const GL_TXN_HEAD_CELLS = '<th>Account / Posting Date</th><th>JEV No.</th><th>DV Number</th><th>Description / Particulars</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end gl-bal-col">Balance</th>';
// Popup header: the SL list gets the light account header (each expanded SL adds its own transaction header row); "All transactions" gets the transaction header
function gl_setSlHead(slList) {
    const h = $gl('gl-sl-thead');
    h.className = slList ? 'gl-head-light' : '';
    h.innerHTML = slList
        ? '<tr><th>Account code</th><th colspan="3">Account description</th><th class="text-end">Debit</th><th class="text-end">Credit</th><th class="text-end gl-bal-col">Balance</th></tr>'
        : `<tr>${GL_TXN_HEAD_CELLS}</tr>`;
}
async function gl_boot() {
    gl_setHead(true);
    await Promise.all([gl_loadFiscalYears(), gl_loadBooks()]);
    gl_bindEvents();
    gl_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', gl_boot);
else gl_boot();

function gl_bindEvents() {
    $gl('gl-refresh')?.addEventListener('click', () => gl_load());
    $gl('gl-print')?.addEventListener('click', e => {
        e.stopPropagation();
        const open = $gl('gl-print-menu').style.display !== 'none';
        gl_togglePrintMenu(!open, e.currentTarget, gl_ledger?.account?.account_code);
    });
    window.addEventListener('scroll', () => gl_togglePrintMenu(false), true);
    window.addEventListener('resize', () => gl_togglePrintMenu(false));
    $gl('gl-print-sheets')?.addEventListener('click', e => {
        e.stopPropagation();
        const b = e.target.closest('.gl-print-sheet');
        if (!b) return;
        gl_togglePrintMenu(false);
        gl_dispatchPrint('gl', gl_printCode, { book_id: b.dataset.book, fund: b.dataset.fund, fiscal_year: b.dataset.year });
    });
    $gl('gl-print-menu')?.addEventListener('click', e => e.stopPropagation());
    $gl('gl-print-sl-sheets')?.addEventListener('click', e => {
        e.stopPropagation();
        const b = e.target.closest('.gl-print-sheet');
        if (!b) return;
        gl_togglePrintMenu(false);
        gl_dispatchPrint('sl', gl_printCode, { book_id: b.dataset.book, fund: b.dataset.fund, fiscal_year: b.dataset.year });
    });
    document.addEventListener('click', () => gl_togglePrintMenu(false));
    $gl('gl-sched-go')?.addEventListener('click', e => {
        e.stopPropagation();
        const asOf = $gl('gl-sched-asof').value;
        gl_togglePrintMenu(false);
        gl_printSchedule(gl_printCode, asOf);
    });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') gl_togglePrintMenu(false); });
    $gl('gl-filter-fy')?.addEventListener('change', () => gl_load());
    $gl('gl-filter-book')?.addEventListener('change', () => gl_load());

    // Grouped (no-account-picked) view: click "View SLs" to open the SL drill-down popup;
    // click anywhere else on the header row to toggle its own transactions open/closed.
    // GL row → SL popup; inside the popup an SL row → its sub-SLs / transactions
    $gl('gl-tbody')?.addEventListener('click', e => {
        const amt = e.target.closest('.gl-amt');
        if (amt) { e.preventDefault(); gl_openGlTxns(amt.closest('tr').dataset.id); return; }
        const link = e.target.closest('.gl-link');
        if (!link) return;
        e.preventDefault();
        gl_openGlPopup(link.closest('tr').dataset.id);
    });
    $gl('gl-sl-tbody')?.addEventListener('click', e => {
        const row = e.target.closest('tr.gl-node.gl-clickable');
        if (row) gl_toggleNode(row);
    });
    $gl('gl-sl-close')?.addEventListener('click', gl_closeGlPopup);
    $gl('gl-sl-print')?.addEventListener('click', gl_previewTxns);
    // header Print ▾ of the GL list view → Print GL | Print SL (every GL, one preview window)
    $gl('gl-printall')?.addEventListener('click', e => {
        e.stopPropagation();
        gl_togglePrintAll($gl('gl-printall-menu').style.display === 'none', e.currentTarget);
    });
    $gl('gl-printall-menu')?.addEventListener('click', e => {
        e.stopPropagation();
        const b = e.target.closest('.gl-printall-opt');
        if (!b) return;
        gl_togglePrintAll(false);
        gl_printAll(b.dataset.mode);
    });
    document.addEventListener('click', () => gl_togglePrintAll(false));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') gl_togglePrintAll(false); });
    window.addEventListener('scroll', () => gl_togglePrintAll(false), true);
    window.addEventListener('resize', () => gl_togglePrintAll(false));
    // loading modal: Cancel stops the remaining requests; once the report is ready (pop-up blocked) the button opens it
    $gl('gl-load-cancel')?.addEventListener('click', () => { gl_allCancel = true; gl_allReady = null; gl_loadModal(false); });
    $gl('gl-load-ready')?.addEventListener('click', () => {
        if (gl_allReady && gl_openReport(gl_allReady)) { gl_allReady = null; gl_loadModal(false); }
        else if (gl_allReady) gl_showError('Allow pop-ups for this site to open the preview.');
    });
    $gl('gl-sl-overlay')?.addEventListener('click', e => { if (e.target.id === 'gl-sl-overlay') gl_closeGlPopup(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') gl_closeGlPopup(); });

    const input = $gl('gl-account-search');
    input.addEventListener('input', () => {
        gl_selectedAccount = null;
        clearTimeout(gl_searchTimer);
        gl_searchTimer = setTimeout(() => gl_searchAccounts(input.value.trim()), 300);
        if (!input.value.trim()) gl_load();
    });
    input.addEventListener('focus', () => { if (input.value.trim()) gl_searchAccounts(input.value.trim()); });
    document.addEventListener('click', e => {
        if (!e.target.closest('#gl-account-search') && !e.target.closest('#gl-account-results')) {
            $gl('gl-account-results').style.display = 'none';
        }
    });
}

/* Fiscal years come from fims.fiscal_year via GET /fiscal_year/all → {success, data:[{fiscal_yr}]}. */
async function gl_loadFiscalYears() {
    const sel = $gl('gl-filter-fy');
    try {
        const res  = await gl_authFetch(GL_FY_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${gl_esc(r.fiscal_yr)}">${gl_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        gl_showError('Fiscal years: ' + e.message);
    }
}

/* Books come from fims.books via GET /books/all → {success, data:[{book_id, code, name}]}. */
async function gl_loadBooks() {
    const sel = $gl('gl-filter-book');
    try {
        const res  = await gl_authFetch(GL_BOOK_API);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load books.');
        sel.innerHTML = '<option value="">All Books</option>' +
            (json.data ?? []).map(r => `<option value="${gl_esc(r.book_id)}">${gl_esc(r.name)}</option>`).join('');
    } catch (e) {
        gl_showError('Books: ' + e.message);
    }
}

/* Account combobox — any chart-of-accounts account (GL headers like 1-01-01 as well as postable ones),
   GET /chart_of_accounts/all?search=… — a GL account's ledger includes its SLs' postings. */
async function gl_searchAccounts(search) {
    const box = $gl('gl-account-results');
    try {
        const params = new URLSearchParams({ search });
        const res  = await gl_authFetch(`${GL_COA_API}?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        const rows = (json.data ?? []).slice(0, 30);
        if (!rows.length) {
            box.innerHTML = '<div class="gl-combo-empty">No matching accounts.</div>';
        } else {
            box.innerHTML = rows.map(r => `<div class="gl-combo-item" data-code="${gl_esc(r.account_code)}" data-title="${gl_esc(r.account_title)}">
                <strong class="nha-mono">${gl_esc(r.account_code)}</strong> — ${gl_esc(r.account_title)}
            </div>`).join('');
            box.querySelectorAll('.gl-combo-item').forEach(el => el.addEventListener('click', () => {
                gl_selectedAccount = { account_code: el.dataset.code, account_title: el.dataset.title };
                $gl('gl-account-search').value = `${el.dataset.code} — ${el.dataset.title}`;
                box.style.display = 'none';
                gl_load();
            }));
        }
        box.style.display = 'block';
    } catch (e) {
        gl_showError('Account search: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function gl_load() {
    window.PagePreloader?.show();
    gl_hideError();
    try {
        const params = new URLSearchParams();
        if (gl_selectedAccount) params.set('account_code', gl_selectedAccount.account_code);
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);

        // no account picked → the GL/SL tree; one account picked → its flat ledger
        const res  = await gl_authFetch(`${GL_API}/${gl_selectedAccount ? 'ledger' : 'tree'}?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the General Ledger.');

        const isTree = !gl_selectedAccount;
        gl_setHead(isTree);
        gl_setPrintable(isTree ? null : json);
        $gl('gl-printall-wrap').style.display = isTree ? 'inline-block' : 'none';   // Print ▾ (all GLs) belongs to the GL list view
        if (!isTree) gl_togglePrintAll(false);
        if (isTree) {
            $gl('gl-summary').style.display = 'none';
            $gl('gl-ending').style.display = 'none';
            $gl('gl-expand-toolbar').style.display = 'none';
            gl_renderTree(json.data ?? []);
        } else {
            $gl('gl-expand-toolbar').style.display = 'none';
            gl_renderSummary(json);
            gl_renderTable(json.data ?? [], json.debit_normal);
            gl_renderEnding(json.ending_balance, json.debit_normal);
        }
    } catch (e) {
        gl_setPrintable(null);
        $gl('gl-printall-wrap').style.display = 'none';
        $gl('gl-expand-toolbar').style.display = 'none';
        gl_renderTable([]);
        gl_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Print ────────────────────────────────────────────────────────────────────────────────────────────
   Print (a menu: General Ledger | Subsidiary Ledger) — in the header, once one account is picked in the Account
   search (the account shown). General Ledger: the GENERAL LEDGER sheet, NHA Form No. 11 (prp_glPayload → prp_open(…, GL)): the account shown, for
   ONE fiscal year and ONE book (the sheet is kept per book — the Book prints as the Fund): the Beginning Balance (the
   book's Opening Balance), then per month one line per journal (CKDJ / CRDJ / ADADJ / GJ), the Closing Entry and the
   Ending Balance. Needs one Book (the Fiscal Year filter, else the postings' single year).
   Subsidiary Ledger (header menu, or the SL popup's "Print SL") — the SUBSIDIARY LEDGER sheet of each SL with postings (prp_glSlPayload → prp_open(…, SL)). */
function gl_setPrintable(json) {
    gl_ledger = json;
    const wrap = $gl('gl-print-wrap');
    if (wrap) wrap.style.display = json ? 'inline-block' : 'none';   // shown once one account is picked
    if (!json) gl_togglePrintMenu(false);
}
// The Print menu (General Ledger | Subsidiary Ledger) — opened from the header Print (the account shown); `code` is the account it prints. Shown beside the button that opened it.
let gl_printCode = '';
function gl_togglePrintMenu(open, anchor, code) {
    const menu = $gl('gl-print-menu');
    if (!menu) return;
    if (!open || !anchor || !code) {
        menu.style.display = 'none';
        $gl('gl-print')?.setAttribute('aria-expanded', 'false');
        return;
    }
    gl_hideError();
    gl_printCode = code;
    gl_loadPrintSheets(code);
    const asOf = $gl('gl-sched-asof');
    if (asOf && !asOf.value) asOf.value = gl_todayIso();
    menu.style.display = '';
    const r = anchor.getBoundingClientRect();
    const w = menu.offsetWidth || 250;
    const h = menu.offsetHeight || 120;
    menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menu.style.top  = (r.bottom + 6 + h > window.innerHeight ? Math.max(8, r.top - h - 6) : r.bottom + 6) + 'px';
    if (anchor.id === 'gl-print') anchor.setAttribute('aria-expanded', 'true');
}
// The General Ledger sheets the account can print — only (book, fiscal year) pairs with postings or an Opening
// Balance (GET /print/gl-sheets), honouring the Fiscal Year filter — so the user picks from what exists.
let gl_sheetsReq = 0;
async function gl_loadPrintSheets(code) {
    const box = $gl('gl-print-sheets');
    const slBox = $gl('gl-print-sl-sheets');
    if (!box) return;
    const req = ++gl_sheetsReq;
    box.innerHTML = '<div class="gl-print-note">Loading…</div>';
    if (slBox) slBox.innerHTML = '';
    try {
        const params = new URLSearchParams({ account_code: code });
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const json = await gl_authFetch(`${GL_BASE.replace(/\/$/, '')}/print/gl-sheets?${params}`).then(r => r.json());
        if (req !== gl_sheetsReq) return;
        if (!json.success) throw new Error(json.message || 'Failed to load.');
        const rows = json.data ?? [];
        box.innerHTML = rows.length
            ? rows.map(r => `<button type="button" role="menuitem" class="gl-print-sheet" data-book="${r.book_id}" data-fund="${gl_esc(r.book_name)}" data-year="${r.fiscal_yr}">
                    <span>${gl_esc(r.book_name || ('Book ' + r.book_id))} — FY ${r.fiscal_yr}</span>
                    <small>${r.postings ? r.postings + ' posting' + (r.postings === 1 ? '' : 's') : 'Beginning balance only'}</small>
                </button>`).join('')
            : `<div class="gl-print-note">Nothing to print${fy ? ' for FY ' + gl_esc(fy) : ''} — this account has no postings${fy ? ' that year' : ''}.</div>`;
        // Subsidiary Ledger: only the book/year sheets where an SL account under it has postings or a beginning balance
        const slRows = rows.filter(r => r.sl_postings || r.sl_opening);
        if (slBox) slBox.innerHTML = slRows.length
            ? slRows.map(r => `<button type="button" role="menuitem" class="gl-print-sheet" data-book="${r.book_id}" data-fund="${gl_esc(r.book_name)}" data-year="${r.fiscal_yr}">
                    <span>${gl_esc(r.book_name || ('Book ' + r.book_id))} — FY ${r.fiscal_yr}</span>
                    <small>${r.sl_postings ? r.sl_postings + ' SL posting' + (r.sl_postings === 1 ? '' : 's') : 'Beginning balances only'}</small>
                </button>`).join('')
            : '<div class="gl-print-note">No subsidiary ledger postings under this account.</div>';
    } catch (e) {
        if (req !== gl_sheetsReq) return;
        box.innerHTML = `<div class="gl-print-note">Could not load the books: ${gl_esc(e.message)}</div>`;
    }
}
function gl_printOpts() {
    const book = $gl('gl-filter-book');
    return {
        fund: book && book.value ? (book.options[book.selectedIndex]?.text || '') : '',
        book_id: book ? book.value : '',
        fiscal_year: $gl('gl-filter-fy').value,
    };
}
// General Ledger — the account's ledger (GET /general_ledger/ledger with the page's Fiscal Year / Book filters; the one
// already loaded when it is the account shown) → NHA Form No. 11.
async function gl_print(code, pick = null) {
    if (!code || typeof prp_open !== 'function') return;
    gl_hideError();
    // a sheet picked from the menu (its own book and year), else the page's filters
    const opts = pick ? { book_id: String(pick.book_id), fund: pick.fund || '', fiscal_year: String(pick.fiscal_year || '') } : gl_printOpts();
    if (!opts.book_id) {
        gl_showError('Pick a Book to print — the General Ledger sheet is kept per book (the Book prints as the Fund).');
        return;
    }
    window.PagePreloader?.show();
    try {
        const f = gl_printOpts();
        let ledger = gl_ledger && gl_ledger.account?.account_code === code && f.book_id === opts.book_id && f.fiscal_year === opts.fiscal_year ? gl_ledger : null;
        if (!ledger) {
            const params = new URLSearchParams({ account_code: code, book_id: opts.book_id });
            if (opts.fiscal_year) params.set('fiscal_yr', opts.fiscal_year);
            ledger = await gl_authFetch(`${GL_API}/ledger?${params}`).then(r => r.json());
            if (!ledger.success) throw new Error(ledger.message || 'Failed to load the account ledger.');
        }
        if (!opts.fiscal_year) {
            const years = [...new Set((ledger.data ?? []).map(r => String(r.posting_date || '').slice(0, 4)).filter(Boolean))];
            if (years.length > 1) {
                gl_showError(code + ' has postings in ' + years.join(', ') + ' — pick a Fiscal Year to print (the ledger sheet is one year).');
                return;
            }
            opts.fiscal_year = years[0] || String(new Date().getFullYear());
        }
        await prp_open(prp_glPayload(ledger, opts), { type: 'GL' });
    } catch (e) {
        gl_showError('Could not print: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
// Subsidiary Ledger — the SL sheets of the account (GET /general_ledger/ledger-sl with the page's Fiscal Year / Book
// filters — the same data the SL popup loads) → one SUBSIDIARY LEDGER sheet per SL with data in that book and year.
async function gl_printSl(code, pick = null) {
    if (!code || typeof prp_open !== 'function') return;
    gl_hideError();
    const params = new URLSearchParams({ account_code: code });
    // a sheet picked from the menu (its own book and year), else the page's filters
    const opts = pick ? { book_id: String(pick.book_id), fund: pick.fund || '', fiscal_year: String(pick.fiscal_year || '') } : gl_printOpts();
    if (opts.fiscal_year) params.set('fiscal_yr', opts.fiscal_year);
    if (opts.book_id) params.set('book_id', opts.book_id);
    window.PagePreloader?.show();
    try {
        const json = await gl_authFetch(`${GL_API}/ledger-sl?${params}`).then(r => r.json());
        if (!json.success) throw new Error(json.message || 'Failed to load the sub-ledgers.');
        await prp_open(prp_glSlPayload(json, opts), { type: 'SL' });
    } catch (e) {
        gl_showError('Could not print: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
/* ── Summary (account header — code/name/normal balance/class) ──────────────── */
function gl_renderSummary(json) {
    $gl('gl-summary').style.display = '';
    $gl('gl-summary-account').textContent = `${json.account.account_code} — ${json.account.account_title}`;
    $gl('gl-summary-normal').textContent  = json.normal_balance || '—';
    $gl('gl-summary-class').textContent   = json.account.account_type || '—';
    $gl('gl-summary-debit').textContent   = gl_peso(json.total_debit);
    $gl('gl-summary-credit').textContent  = gl_peso(json.total_credit);
}

/* "Ending Balance as of {today}" footer, shown only for a single-account ledger (grouped/all-accounts
   view has its own per-account ending balance already in the group header). */
function gl_renderEnding(endingBalance, debitNormal) {
    const el = $gl('gl-ending');
    el.style.display = '';
    el.innerHTML = `Ending Balance as of ${gl_fmtDate(new Date().toISOString())}: <strong>${gl_peso(Math.abs(endingBalance))}${gl_balSide(endingBalance, debitNormal)}</strong>`;
}

/* ── Table ─────────────────────────────────────────────────── */
function gl_renderTable(rows, debitNormal) {
    const tbody = $gl('gl-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted transactions for this account.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => gl_rowHtml(r, debitNormal)).join('');
}

/* Default view (no account picked): the GL accounts only (GET /general_ledger/tree), in the Code / Description / Debit /
   Credit / Balance format. Click a GL → a popup lists its SL accounts; click an SL that has sub-SLs → they appear under it;
   click an account with postings → its transactions (GET /general_ledger/postings, fetched once). */
let gl_nodes = {};   // account_id → node (GL + every SL under it, depth re-based so a GL is 0)
let gl_open  = {};   // account_id → true when expanded in the popup
let gl_list  = [];   // nodes in display order

function gl_nodeRow(n, popup) {
    const expandable = popup ? (n.has_children || n.own_count > 0) : (n.has_children || n.own_count > 0);
    const bal = Number(n.balance);
    const side = bal === 0 ? '' : (bal > 0 ? ' Dr' : ' Cr');
    const abnormal = bal !== 0 && ((bal > 0) !== !!n.debit_normal);
    const blank = n.direct;   // "Direct postings" row — no own totals available
    const depth = popup ? n.depth - 1 : 0;
    // in the GL list the amounts are links → every transaction of the account (GL + its SLs)
    const amt = (html, show) => (!popup && show) ? `<a href="#" class="gl-amt" role="button" title="Click to see all transactions">${html}</a>` : html;
    return `<tr class="gl-node gl-k-${n.kind}${expandable && popup ? ' gl-clickable' : ''}" data-id="${gl_esc(n.account_id)}" data-parent="${gl_esc(n.parent_id ?? '')}" data-code="${gl_esc(n.account_code)}" style="--gl-depth:${depth};">
        ${popup ? '' : `<td class="gl-lbl"><span class="nha-mono gl-code">${gl_esc(n.account_code)}</span></td><td colspan="3">${expandable ? `<a href="#" class="gl-link" role="button" title="Click to see the subsidiary ledgers">${gl_esc(n.account_title)}<svg class="gl-link-icon" width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6                M15 3h6v6M10 14L21 3"/></svg></a>` : gl_esc(n.account_title)}</td>`}
                ${!popup ? '' : `<td colspan="4" class="gl-lbl"><span class="gl-sl-toggle">${expandable ? '&#9656;' : ''}</span><span class="nha-mono gl-code">${n.direct ? '' : gl_esc(n.account_code)}</span> ${n.direct ? 'Direct postings to ' + gl_esc(n.account_code) : gl_esc(n.account_title)}</td>`}
        <td class="text-end nha-mono u-tabular-nums u-text-sm">${blank ? '' : amt(Number(n.total_debit) ? gl_peso(n.total_debit) : '-', Number(n.total_debit))}</td>
        <td class="text-end nha-mono u-tabular-nums u-text-sm">${blank ? '' : amt(Number(n.total_credit) ? gl_peso(n.total_credit) : '-', Number(n.total_credit))}</td>
        <td class="text-end nha-mono u-tabular-nums u-text-sm gl-bal-col${abnormal && !blank ? ' gl-abnormal' : ''}">${blank ? '' : amt(bal === 0 ? (popup ? gl_peso(0) + gl_balSide(0, n.debit_normal) : '-') : gl_peso(Math.abs(bal)) + side, bal !== 0)}</td>
    </tr>`;
}

function gl_renderTree(nodes) {
    const tbody = $gl('gl-tbody');
    gl_nodes = {}; gl_open = {};
    // classification headers above the GL accounts are left out; depth is re-based so a GL sits at 0
    let glDepth = 0;
    gl_list = nodes.filter(n => n.kind !== 'group').map(n => {
        if (n.kind === 'gl') glDepth = n.depth;
        return { ...n, depth: n.depth - glDepth };
    });
    gl_list.forEach(n => { gl_nodes[n.account_id] = n; });
    const gls = gl_list.filter(n => n.kind === 'gl');
    if (!gls.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Nothing posted yet.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = gls.map(n => gl_nodeRow(n, false)).join('');
}

// the SL / sub-SL nodes listed under a GL node (they follow it in gl_list until the next GL)
function gl_slsUnder(id) {
    const idx = gl_list.findIndex(n => n.account_id === id);
    const sls = [];
    for (let i = idx + 1; idx >= 0 && i < gl_list.length && gl_list[i].kind !== 'gl'; i++) sls.push(gl_list[i]);
    return sls;
}

// GL click → popup with the SL accounts under it
function gl_openGlPopup(id) {
    const gl = gl_nodes[id];
    if (!gl) return;
    const sls = gl_slsUnder(id);
    gl_open = {};
    // postings made straight to the GL (not to an SL) are not listed here — they belong on an SL account
    gl_txnData = null; gl_showExport(false);
    const tbody = $gl('gl-sl-tbody');
    gl_setSlHead(true);
    $gl('gl-sl-title').textContent = `${gl.account_code} — ${gl.account_title}`;
    $gl('gl-sl-subtitle').textContent = sls.length
        ? `${sls.filter(n => !n.direct).length} subsidiary ledger${sls.filter(n => !n.direct).length === 1 ? '' : 's'} · click one to see its sub-accounts or transactions`
        : '';
    if (!sls.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No subsidiary ledgers under this account.</span></div></td></tr>';
        $gl('gl-sl-overlay').classList.add('open');
        return;
    }
    tbody.innerHTML = sls.map(n => gl_nodeRow(n, true)).join('');
    gl_txnData = { kind: 'sl', gl, sls: sls.filter(n => !n.direct), json: null };   // Preview fetches the postings when clicked
    gl_showExport(true);
    $gl('gl-sl-overlay').classList.add('open');
    gl_applyTree(tbody);
}
/* ── Preview / Print / Excel — of the transactions popup (kind 'txn') and of the SL list popup (kind 'sl') ─────────────── */
let gl_txnData = null;   // { kind, gl, json, sls? } of the popup now showing — what Preview / Print / Excel use
function gl_showExport(show) {
    ['gl-sl-print'].forEach(id => { const b = $gl(id); if (b) b.style.display = show ? '' : 'none'; });
}
function gl_filterText() {
    const sel = id => { const e = $gl(id); return e && e.value ? e.options[e.selectedIndex].text : ''; };
    return [['Fiscal year', sel('gl-filter-fy') || 'All years'], ['Book', sel('gl-filter-book') || 'All books']];
}
const gl_num = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
// Report layout (the NHA ledger card): a "SUBSIDIARY LEDGER" / "GENERAL LEDGER" line with REFERENCE n, a boxed header (account title
// on the left; GL title / code / book on the right), then DATE | ITEMS | FOLIO | DEBITS | CREDITS | BALANCE in yearly blocks ? a
// Beginning Balance line (from the 2nd year, or when there is an opening balance), the postings, and an ENDING BALANCE line that closes
// the year: the balance is put in the opposite column and the Balance column reads "-".
const GL_MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const GL_HEAD = ['DATE', 'ITEMS', 'FOLIO', 'DEBITS', 'CREDITS', 'BALANCE'];
const gl_monYr = (y, m) => y && m ? `${GL_MON[m - 1]}-${String(y).slice(2)}` : '';
// a balance (normal side positive) as printed: "-" for zero, (1,234.00) when it is on the abnormal side
const gl_balTxt = v => Math.abs(v) < 0.005 ? '-' : (v < 0 ? `(${gl_num(-v)})` : gl_num(v));
// the ledger lines of one account ? { rows: [{ t: 'beg' | 'post' | 'end' | 'gap', c: [date, items, folio, debit, credit, balance] }] }
function gl_ledgerSection(rows, debitNormal, balOf) {
    const mv = r => (debitNormal ? 1 : -1) * ((Number(r.debit) || 0) - (Number(r.credit) || 0));
    const side = v => {   // [debit, credit] cells holding the balance v on the side it sits on
        const a = Math.round(Math.abs(v) * 100) / 100;
        return (v > 0) === !!debitNormal ? [a, ''] : ['', a];
    };
    const out = [];
    let year = null, last = 0;
    const close = () => out.push({ t: 'end', c: [gl_monYr(year, 12), 'ENDING BALANCE', '/', ...(Math.abs(last) < 0.005 ? ['', ''] : side(-last)), '-'] });
    rows.forEach(r => {
        const b = balOf(r);
        const d = String(r.posting_date || '').slice(0, 10), y = d.slice(0, 4);
        if (y !== year) {
            const open = year === null ? b - mv(r) : last;
            if (year !== null) { close(); out.push({ t: 'gap' }); }
            year = y;
            if (Math.abs(open) >= 0.005) out.push({ t: 'beg', c: [gl_monYr(y, 1), 'Beginning Balance', '/', ...side(open), Math.round(open * 100) / 100] });
        }
        last = b;
        out.push({ t: 'post', c: [
            gl_monYr(y, Number(d.slice(5, 7))),
            r.jev_particulars || r.description || '',   // the JEV's particulars (else its DV's)
            r.jev_no ? (/^JEV/i.test(r.jev_no) ? r.jev_no : 'JEV # ' + r.jev_no) : (r.dv_no ? 'DV # ' + r.dv_no : ''),
            Number(r.debit) > 0 ? Number(r.debit) : '',
            Number(r.credit) > 0 ? Number(r.credit) : '',
            Math.round(b * 100) / 100,
        ] });
    });
    if (year !== null) close();
    return { rows: out };
}
function gl_report(d = gl_txnData) {
    const { json } = d;
    return gl_ledgerSection(json.data ?? [], json.debit_normal, r => Number(r.running_balance));
}
// the SL list popup ? one ledger section per SL / sub-SL that has postings, balances running per SL
function gl_slSections(d = gl_txnData) { return gl_slSectionsOf(d.json, d.sls); }
// the same, from a GET /general_ledger/ledger response of one GL and the SL nodes under it (also used by Print ? SL for every GL)
function gl_slSectionsOf(json, sls) {
    const dn = json.debit_normal;
    const byCode = {};
    (json.data ?? []).forEach(r => { if (r.sl_account_code) (byCode[r.sl_account_code] ||= []).push(r); });
    // only the SL / sub-SL accounts ? postings made straight to the GL account itself are not part of the Subsidiary Ledger
    const order = sls.map(n => ({ code: n.account_code, title: n.account_title }));
    return order.filter(o => byCode[o.code]).map(o => {
        let run = 0;
        return { code: o.code, title: o.title, ...gl_ledgerSection(byCode[o.code], dn, r => (run += dn ? (Number(r.debit) - Number(r.credit)) : (Number(r.credit) - Number(r.debit)))) };
    });
}
function gl_fund() {
    const bookSel = $gl('gl-filter-book');
    return bookSel && bookSel.value ? bookSel.options[bookSel.selectedIndex].text : '';
}
// the report's cards: one per SL with postings (SUBSIDIARY LEDGER) or one per GL (GENERAL LEDGER), numbered REFERENCE 1, 2, 3 ?
const gl_slCards = (gl, secs, fund) => secs.map(sec => ({ title: 'SUBSIDIARY LEDGER', left: sec.title, right: [gl.account_title, sec.code, fund], rows: sec.rows }));
const gl_glCard = (gl, led, fund) => ({ title: 'GENERAL LEDGER', left: gl.account_title, right: ['NATIONAL HOUSING AUTHORITY', gl.account_code, fund], rows: led.rows });
function gl_reportCards(d) {
    const fund = d.fund || gl_fund() || 'All books';
    if (d.kind === 'allsl') return d.items.flatMap(it => gl_slCards(it.gl, it.secs, fund));
    if (d.kind === 'allgl') return d.items.map(it => gl_glCard(it.gl, it.ledger, fund));
    if (d.kind === 'sl') return gl_slCards(d.gl, gl_slSections(d), fund);
    return [gl_glCard(d.gl, gl_report(d), fund)];
}
const GL_COLS = '<colgroup><col style="width:9%"><col style="width:36%"><col style="width:16%"><col style="width:13%"><col style="width:13%"><col style="width:13%"></colgroup>';
// excel = raw numbers + an Excel number format (the .xls of the popup's Preview)
function gl_ledgerRows(rows, excel) {
    const num = v => typeof v === 'number' ? (excel ? v : gl_num(v)) : gl_esc(v);
    const xf = v => excel && typeof v === 'number' ? ' style="mso-number-format:\'#,##0.00\'"' : '';
    return rows.map(r => r.t === 'gap' ? '<tr class="gap"><td></td><td></td><td></td><td></td><td></td><td></td></tr>'
        : `<tr class="${r.t}"><td class="d">${gl_esc(r.c[0])}</td><td>${gl_esc(r.c[1])}</td><td class="c">${gl_esc(r.c[2])}</td>`
            + `<td class="n"${xf(r.c[3])}>${num(r.c[3])}</td><td class="n"${xf(r.c[4])}>${num(r.c[4])}</td>`
            + `<td class="n">${typeof r.c[5] === 'number' ? gl_balTxt(r.c[5]) : gl_esc(r.c[5])}</td></tr>`).join('');
}
// one ledger card ? o: { title, ref, left, right: [line 1, line 2, line 3], rows }. brk = start it on a new printed page.
function gl_sheet(o, excel, brk) {
    return `<table class="gl-top${brk ? ' pb' : ''}"><tr><td colspan="3">${gl_esc(o.title)}</td><td colspan="3" class="r">REFERENCE ${o.ref}</td></tr></table>
<table class="gl-form">${GL_COLS}
 <tr class="bx"><td colspan="3" rowspan="3" class="lt">${gl_esc(o.left)}</td><td colspan="3" class="rt r1">${gl_esc(o.right[0])}</td></tr>
 <tr class="bx"><td colspan="3" class="rt r2">${gl_esc(o.right[1])}</td></tr>
 <tr class="bx"><td colspan="3" class="rt r3">${gl_esc(o.right[2])}</td></tr>
 <tr class="hd">${GL_HEAD.map(h => `<th>${h}</th>`).join('')}</tr>
 ${o.rows.length ? gl_ledgerRows(o.rows, excel) : '<tr class="post"><td></td><td>No postings.</td><td></td><td></td><td></td><td></td></tr>'}
</table>`;
}
function gl_reportTable(excel, d = gl_txnData) {
    if (d.kind === 'detail') return gl_detailHtml(d.cards, excel);
    const warn = d.failed && d.failed.length ? `<p style="font:12px Arial;color:#b00020">Could not load ${d.failed.length} GL account${d.failed.length === 1 ? '' : 's'} ? left out: ${gl_esc(d.failed.join(', '))}</p>` : '';
    const cards = gl_reportCards(d);
    if (!cards.length) return warn + `<p style="font:14px Arial">${d.kind === 'allsl' || d.kind === 'sl' ? 'No postings under the subsidiary ledgers.' : 'No posted transactions.'}</p>`;
    return warn + cards.map((c, i) => gl_sheet({ ...c, ref: i + 1 }, excel, i > 0)).join(excel ? '<br>' : '');
}
const GL_REPORT_CSS = `*{box-sizing:border-box} body{margin:0;font-family:"Times New Roman",Times,serif;color:#000;background:#fff}
 table.gl-top{width:100%;border-collapse:collapse;font:bold 13px Arial,Helvetica,sans-serif;margin:0 0 4px}
 table.gl-top td{padding:0 2px} table.gl-top td.r{text-align:right}
 table.gl-form{width:100%;border-collapse:collapse;font-size:12.5px;border:2px solid #000;margin-bottom:18px}
 table.gl-form td,table.gl-form th{padding:3px 6px;vertical-align:bottom}
 tr.bx td{border:0} tr.bx td.lt{font-size:24px;padding:8px 12px;text-transform:uppercase}
 tr.bx td.rt{text-align:center;font-weight:bold;text-transform:uppercase;padding:2px 12px}
 tr.bx td.r1{font-size:18px;padding-top:12px} tr.bx td.r2{font-size:14px} tr.bx td.r3{font-size:20px;padding-bottom:6px}
 tr.hd th{border:1px solid #000;border-top:2px solid #000;font-weight:bold;text-align:center;font-size:13px;padding:6px}
 tr.post td,tr.beg td,tr.end td,tr.gap td{border-left:1px solid #000;border-right:1px solid #000;border-bottom:1px solid #999}
 td.d{white-space:nowrap} td.c{text-align:center;white-space:nowrap} td.n{text-align:right;white-space:nowrap}
 tr.end td{font-weight:bold;border-bottom:2px solid #000}
 tr.gap td{height:12px}
 tr{page-break-inside:avoid;break-inside:avoid}
 table.gl-top.pb{page-break-before:always;break-before:page}`;
const gl_reportName = (d = gl_txnData) => d.kind === 'detail' ? d.name : d.kind === 'allsl' ? 'Subsidiary-Ledger_All-GL' : d.kind === 'allgl' ? 'General-Ledger_All' : d.kind === 'sl' ? `Subsidiary-Ledger_${d.gl.account_code}` : `General-Ledger_${d.gl.account_code}`;
// Preview → a report window with an Excel button and a Print button in its toolbar. The SL list popup fetches its postings first.
async function gl_previewTxns() {
    if (!gl_txnData) return;
    const w = window.open('', '_blank', 'width=1100,height=800');   // opened inside the click, before any await
    if (!w) { gl_showError('Allow pop-ups for this site to open the preview.'); return; }
    w.document.write('<p style="font-family:Arial;padding:24px">Preparing the report…</p>');
    try {
        if (gl_txnData.kind === 'sl' && !gl_txnData.json) {
            const params = new URLSearchParams({ account_code: gl_txnData.gl.account_code });
            const fy = $gl('gl-filter-fy').value;
            if (fy) params.set('fiscal_yr', fy);
            const book = $gl('gl-filter-book').value;
            if (book) params.set('book_id', book);
                const res  = await gl_authFetch(`${GL_API}/ledger?${params}`);
            const json = await res.json();
            if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load the postings.');
            gl_txnData.json = json;
        }
        // the popups' Preview defaults to the Detailed format (every transaction, JEV / DV #, check)
        const isSl = gl_txnData.kind === 'sl';
        const cards = await gl_detailCards(isSl ? 'sl' : 'gl', gl_txnData.json, gl_printOpts(), isSl ? gl_txnData.sls : null);
        gl_menuData = { kind: 'detail', cards, name: `${isSl ? 'Subsidiary' : 'General'}-Ledger-Detailed_${gl_txnData.gl.account_code}` };
        gl_writePreview(w, gl_reportTable(false, gl_menuData), gl_menuData.name, 'gl_excelMenu');
    } catch (e) {
        w.close();
        gl_showError('Could not prepare the preview: ' + e.message);
    }
}
// fills a preview window with the report and its Excel / Print toolbar; `excelFn` is the opener function the Excel button calls
function gl_writePreview(w, html, name, excelFn) {
    w.document.open();
    w.document.write(`<!doctype html><html><head><meta charset="utf-8"><title>${gl_esc(name)}</title>
<style>@page{size:landscape;margin:12mm}${GL_REPORT_CSS}
 .bar{position:sticky;top:0;background:#2f3336;padding:10px 24px;display:flex;justify-content:flex-end;gap:10px}
 .bar button{background:#1a73e8;color:#fff;border:0;border-radius:8px;padding:9px 16px;font-weight:600;font-size:14px;cursor:pointer}
 .bar button.alt{background:#fff;color:#1f497d} .page{padding:24px 40px 40px}
 @media print{.bar{display:none}.page{padding:0}}</style></head><body>
<div class="bar"><button class="alt" id="x">Excel</button><button id="p">Print</button></div>
<div class="page">${html}</div>
<script>
 document.getElementById('p').onclick = function () { window.print(); };
 document.getElementById('x').onclick = function () { try { window.opener.${excelFn}(); } catch (e) { alert('Close this preview and use the page again to export.'); } };
<\/script></body></html>`);
    w.document.close();
}
/* Excel of Print ▾ GL / SL (every GL) — a real .xlsx built with ExcelJS (same CDN build the Trial Balance page uses), ONE SHEET (TAB)
   PER GL ACCOUNT, named by its code. A GL sheet = the General Ledger ledger; an SL sheet = that GL's SL sections stacked. */
const GL_XL_WIDTHS = [10, 48, 20, 16, 16, 17];   // DATE | ITEMS | FOLIO | DEBITS | CREDITS | BALANCE
// one ledger card on a worksheet (same layout as the preview); borders of merged ranges are set on their top-left cell only
function gl_xlSheet(ws, o) {
    const blk = { argb: 'FF000000' };
    const thin = { style: 'thin', color: blk }, med = { style: 'medium', color: blk }, hair = { style: 'thin', color: { argb: 'FF999999' } };
    const TNR = 'Times New Roman';
    const top = ws.addRow([o.title, '', '', '', '', 'REFERENCE ' + o.ref]);
    [1, 6].forEach(i => { top.getCell(i).font = { bold: true, name: 'Arial' }; });
    top.getCell(6).alignment = { horizontal: 'right' };
    const bx = [ws.addRow([o.left, '', '', o.right[0]]), ws.addRow(['', '', '', o.right[1]]), ws.addRow(['', '', '', o.right[2]])];
    ws.mergeCells(bx[0].number, 1, bx[2].number, 3);
    bx.forEach((r, k) => {
        ws.mergeCells(r.number, 4, r.number, 6);
        r.height = 22;
        const c = r.getCell(4);
        c.value = String(c.value || '').toUpperCase();
        c.font = { bold: true, name: TNR, size: [14, 11, 16][k] };
        c.alignment = { horizontal: 'center', vertical: 'middle' };
        c.border = k === 0 ? { top: med, right: med } : { right: med };
    });
    const lt = bx[0].getCell(1);
    lt.value = String(lt.value || '').toUpperCase();
    lt.font = { name: TNR, size: 18 };
    lt.alignment = { vertical: 'bottom', horizontal: 'left', wrapText: true };
    lt.border = { top: med, left: med };
    ws.addRow(GL_HEAD).eachCell((c, i) => {
        c.font = { bold: true, name: TNR }; c.alignment = { horizontal: 'center', vertical: 'middle' };
        c.border = { top: med, bottom: thin, left: i === 1 ? med : thin, right: i === 6 ? med : thin };
    });
    const body = (cells, t) => {
        const r = ws.addRow(cells);
        for (let i = 1; i <= 6; i++) {
            const c = r.getCell(i);
            c.font = { name: TNR, bold: t === 'end' };
            c.border = { left: i === 1 ? med : thin, right: i === 6 ? med : thin, bottom: t === 'end' ? med : hair };
            c.alignment = { vertical: 'bottom', wrapText: i === 2, horizontal: i >= 4 ? 'right' : (i === 3 ? 'center' : undefined) };
            if (i >= 4) c.numFmt = '#,##0.00;(#,##0.00);"-"';
        }
        return r;
    };
    o.rows.forEach(r => body(r.t === 'gap' ? ['', '', '', '', '', ''] : r.c, r.t));
    const end = o.rows.length ? ws.lastRow : body(['', 'No postings.', '', '', '', ''], 'post');
    end.eachCell({ includeEmpty: true }, c => { c.border = { ...c.border, bottom: med }; });
    ws.addRow([]);
}
async function gl_excelWorkbook(d) {
    if (!window.ExcelJS) { alert('The Excel library failed to load. Check your internet connection and reload the page.'); return; }
    try {
        const isSl = d.kind === 'allsl';
        const fund = gl_fund() || 'All books';
        const wb = new ExcelJS.Workbook();
        const used = new Set();
        const sheetName = code => {   // tab names: max 31 chars, none of \ / ? * [ ] :, unique
            let base = String(code).replace(/[\\\/?*\[\]:]/g, '-').slice(0, 31) || 'GL', n = base, k = 1;
            while (used.has(n.toLowerCase())) n = base.slice(0, 31 - String(++k).length - 1) + '-' + k;
            used.add(n.toLowerCase());
            return n;
        };
        const newSheet = name => {
            const ws = wb.addWorksheet(sheetName(name), { pageSetup: { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 } });
            ws.columns = GL_XL_WIDTHS.map(width => ({ width }));
            return ws;
        };
        const line = (ws, text) => {
            const r = ws.addRow([text]);
            ws.mergeCells(r.number, 1, r.number, 6);
        };
        // one tab per GL; an SL tab stacks that GL's SL cards, each on its own printed page. REFERENCE runs across the workbook.
        let ref = 0;
        d.items.forEach(it => {
            const ws = newSheet(it.gl.account_code);
            const cards = isSl ? gl_slCards(it.gl, it.secs, fund) : [gl_glCard(it.gl, it.ledger, fund)];
            cards.forEach((c, i) => {
                if (i > 0) ws.lastRow.addPageBreak();
                gl_xlSheet(ws, { ...c, ref: ++ref });
            });
        });
        if (!d.items.length) line(newSheet('No data'), isSl ? 'No postings under any subsidiary ledger.' : 'No posted transactions.');
        if (d.failed.length) line(newSheet('Not loaded'), `Could not load ${d.failed.length} GL account(s) — left out: ${d.failed.join(', ')}`);

        const buf = await wb.xlsx.writeBuffer();
        const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = gl_reportName(d) + '.xlsx';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        gl_showError('Could not build the Excel file: ' + e.message);
        alert('Could not build the Excel file: ' + e.message);
    }
}
function gl_excelTxns(d = gl_txnData) {
    if (!d || !(d.json || d.items || d.cards)) return;
    if (d.kind === 'allsl' || d.kind === 'allgl') return gl_excelWorkbook(d);   // every GL → one tab each (.xlsx)
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><style>${GL_REPORT_CSS}</style></head><body>${gl_reportTable(true, d)}</body></html>`;
    const url = URL.createObjectURL(new Blob(['\ufeff' + html], { type: 'application/vnd.ms-excel;charset=utf-8' }));
    const a = document.createElement('a');
    a.href = url;
    a.download = gl_reportName(d) + '.xls';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/* ── Print ▾ format choice: Detailed (every transaction) and Ledger card ─────────────────────────────────────────────────
   Detailed = DATE | PARTICULARS | REF | JEV / DV # | CHECK NO. / CHECK DATE | DEBIT | CREDIT | BALANCE, a running balance per
   posting, yearly blocks (Beginning Balance … Ending Balance). Ledger card = the NHA card the list view's Print GL / SL uses.
   Both come from GET /general_ledger/ledger; the Detailed beginning balance is the book's Opening Balance for the year
   (GET /print/gl-opening), only when one fiscal year is picked. */
const gl_fmt = () => document.querySelector('input[name="gl-print-fmt"]:checked')?.value || 'detail';
let gl_menuData = null;   // the single-account Detailed / Ledger-card preview now open — what its Excel button exports
function gl_excelMenu() { gl_excelTxns(gl_menuData); }
const gl_chkDate = iso => { const [y, m, d] = String(iso || '').slice(0, 10).split('-').map(Number); return y ? `${GL_MON[m - 1]} ${d}, ${y}` : ''; };
function gl_dispatchPrint(kind, code, pick) {
    const f = gl_fmt();
    if (f === 'detail') return gl_printDetail(kind, code, pick);
    if (f === 'card') return gl_printCard(kind, code, pick);
    return kind === 'sl' ? gl_printSl(code, pick) : gl_print(code, pick);
}
async function gl_openings(codes, year, bookId, sign) {
    if (!year || !codes.length) return {};
    try {
        const p = new URLSearchParams({ codes: codes.join(','), fiscal_yr: year, sign });
        if (bookId) p.set('book_id', bookId);
        const json = await gl_authFetch(`${GL_BASE.replace(/\/$/, '')}/print/gl-opening?${p}`).then(r => r.json());
        return json.success ? (json.data || {}) : {};
    } catch (e) { return {}; }   // no opening → the sheet starts from its first posting
}
// the account's ledger rows (GL with its SLs rolled in, journal / check fields included)
async function gl_fetchLedger(code, opts) {
    const p = new URLSearchParams({ account_code: code });
    if (opts.fiscal_year) p.set('fiscal_yr', opts.fiscal_year);
    if (opts.book_id) p.set('book_id', opts.book_id);
    const res = await gl_authFetch(`${GL_API}/ledger?${p}`);
    const json = await res.json();
    if (!res.ok || !json.success) throw new Error(json.message || 'Failed to load the account ledger.');
    return json;
}
// the SL nodes under a GL for the reports — from the page's list when it has them, else from the postings themselves
function gl_slNodesOf(code, rows) {
    const glNode = gl_list.find(n => n.kind === 'gl' && n.account_code === code);
    const fromList = glNode ? gl_slsUnder(glNode.account_id).filter(n => !n.direct) : [];
    if (fromList.length) return fromList;
    const seen = {};
    rows.forEach(r => { if (r.sl_account_code && !seen[r.sl_account_code]) seen[r.sl_account_code] = { account_code: r.sl_account_code, account_title: r.sl_name || r.sl_account_code }; });
    return Object.keys(seen).sort().map(c => seen[c]);
}
// yearly blocks of a Detailed sheet: [{ year, beg, lines[{date, part, ref, jd, chk, d, c, bal}], td, tc, end }]; `open` signed on the normal side
function gl_detailBlocks(rows, dn, open, year) {
    const sg = dn ? 1 : -1, r2 = v => Math.round(v * 100) / 100;
    const byYear = new Map();
    rows.forEach(r => { const y = String(r.posting_date || '').slice(0, 4); if (!byYear.has(y)) byYear.set(y, []); byYear.get(y).push(r); });
    if (!byYear.size && Math.abs(open) >= 0.005 && year) byYear.set(String(year), []);
    let bal = r2(open || 0);
    return [...byYear.keys()].sort().map(y => {
        const beg = bal;
        let td = 0, tc = 0;
        const lines = byYear.get(y).map(r => {
            const d = Number(r.debit) || 0, c = Number(r.credit) || 0, ds = String(r.posting_date).slice(0, 10);
            td += d; tc += c; bal = r2(bal + sg * (d - c));
            return { date: `${GL_MON[Number(ds.slice(5, 7)) - 1].toUpperCase()} ${Number(ds.slice(8, 10))}`,
                part: r.jev_particulars || r.description || '', ref: r.journal || '',
                jd: [r.jev_no, r.dv_no].filter(Boolean).join(' / '),
                chk: r.check_no ? r.check_no + (r.check_date ? ' / ' + gl_chkDate(r.check_date) : '') : '',
                d, c, bal };
        });
        return { year: y, beg, lines, td: r2(td), tc: r2(tc), end: bal };
    });
}
// the Detailed cards of one /ledger response: kind 'gl' → one card; 'sl' → one per SL with postings (or an opening balance)
async function gl_detailCards(kind, json, opts, sls) {
    const dn = json.debit_normal !== false, gl = json.account, rows = json.data ?? [], sign = dn ? 1 : -1;
    const meta = { dn, gl, fund: opts.fund || 'All books', fy: opts.fiscal_year || 'All years', book: opts.fund || 'All books' };
    if (kind === 'gl') {
        const op = (await gl_openings([gl.account_code], opts.fiscal_year, opts.book_id, sign))[gl.account_code] || 0;
        const blocks = gl_detailBlocks(rows, dn, op, opts.fiscal_year);
        return blocks.length ? [{ ...meta, kind: 'GL', blocks }] : [];
    }
    const byCode = {};
    rows.forEach(r => { if (r.sl_account_code) (byCode[r.sl_account_code] ||= []).push(r); });
    const list = (sls && sls.length ? sls : gl_slNodesOf(gl.account_code, rows)).map(n => ({ code: n.account_code, title: n.account_title }));
    const ops = await gl_openings(list.map(o => o.code), opts.fiscal_year, opts.book_id, sign);
    return list
        .filter(o => byCode[o.code] || Math.abs(ops[o.code] || 0) >= 0.005)
        .map(o => ({ ...meta, kind: 'SL', sl: o, blocks: gl_detailBlocks(byCode[o.code] || [], dn, ops[o.code] || 0, opts.fiscal_year) }));
}
// a balance as the Detailed sheet prints it: "-" for zero, else the amount with the side it sits on
const gl_dBal = (v, dn) => Math.abs(v) < 0.005 ? '-' : gl_num(Math.abs(v)) + ((v > 0) === !!dn ? ' Dr' : ' Cr');
const GL_DETAIL_CSS = `<style>
.gd-wrap{font-family:Arial,Helvetica,sans-serif;color:#000;margin-bottom:26px} .gd-wrap.pb{page-break-before:always;break-before:page}
.gd-form{font-size:11px;font-weight:bold;min-height:13px} .gd-title{text-align:center;font-size:26px;font-weight:bold;margin:2px 0}
.gd-ag{text-align:center;font-weight:bold;font-size:13px;text-decoration:underline} .gd-agl{text-align:center;font-size:9px;font-style:italic}
table.gd-meta{width:100%;border-collapse:collapse;margin:12px 0 6px;font-size:13px} table.gd-meta td{padding:2px 0;vertical-align:top} table.gd-meta td.r{width:34%}
.gd-sm{font-size:9px;color:#444;font-style:italic} .gd-sl{font-size:13px;margin:16px 0 4px}
table.gd-t{width:100%;border-collapse:collapse;table-layout:fixed;border:2px solid #000}
table.gd-t th{border:1px solid #000;padding:6px 4px;font-size:12px}
table.gd-t td{padding:3px 6px;vertical-align:top;border-left:1px solid #000;border-right:1px solid #000;border-bottom:1px dotted #777;font-size:11.5px}
table.gd-t td.n{text-align:right;white-space:nowrap} table.gd-t td.c{text-align:center} table.gd-t td.d{white-space:nowrap}
table.gd-t tr.end td{font-weight:bold;border-top:2px solid #000;border-bottom:2px solid #000} table.gd-t tr{page-break-inside:avoid;break-inside:avoid}
</style>`;
function gl_detailCard(c, brk, excel) {
    const dn = c.dn, isGl = c.kind === 'GL';
    const xf = v => excel && v ? ' style="mso-number-format:\'#,##0.00\'"' : '';
    const show = v => v ? (excel ? v : gl_num(v)) : '';
    const dashAmt = v => `<td class="n"${xf(v)}>${v ? show(v) : '-'}</td>`;
    const body = c.blocks.map(b => {
        const begD = Math.abs(b.beg) >= 0.005 && (b.beg > 0) === !!dn ? Math.abs(b.beg) : 0;
        const begC = Math.abs(b.beg) >= 0.005 && !begD ? Math.abs(b.beg) : 0;
        return `<tr><td class="d"><b>${gl_esc(b.year)}</b></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
<tr><td class="d">JAN 1</td><td>Beginning Balance</td><td></td><td></td><td></td>${dashAmt(begD)}${dashAmt(begC)}<td class="n">${gl_dBal(b.beg, dn)}</td></tr>`
            + b.lines.map(l => `<tr><td class="d">${gl_esc(l.date)}</td><td>${gl_esc(l.part)}</td><td class="c">${gl_esc(l.ref)}</td><td>${gl_esc(l.jd)}</td><td>${gl_esc(l.chk)}</td>`
                + `<td class="n"${xf(l.d)}>${show(l.d)}</td><td class="n"${xf(l.c)}>${show(l.c)}</td><td class="n">${gl_dBal(l.bal, dn)}</td></tr>`).join('')
            + `<tr class="end"><td class="d">DEC 31</td><td>Ending Balance</td><td></td><td></td><td></td>${dashAmt(b.td)}${dashAmt(b.tc)}<td class="n">${gl_dBal(b.end, dn)}</td></tr>`;
    }).join('');
    return `<div class="gd-wrap${brk ? ' pb' : ''}">
<div class="gd-form">${isGl ? 'NHA Form No. 11' : ''}</div>
<div class="gd-title">${isGl ? 'GENERAL LEDGER' : 'SUBSIDIARY LEDGER'}</div>
<div class="gd-ag">NATIONAL HOUSING AUTHORITY</div><div class="gd-agl">Agency</div>
<table class="gd-meta"><tr><td><b>Fund:</b> ${gl_esc(c.fund)}</td><td class="r"><b>${isGl ? '' : 'GL '}Account Code:</b> ${gl_esc(c.gl.account_code)}</td></tr>
 <tr><td><b>${isGl ? '' : 'GL '}Account Title:</b> <i>${gl_esc(c.gl.account_title)}</i></td><td class="r"><b>Sheet No.</b></td></tr>
 <tr><td class="gd-sm">Fiscal year: ${gl_esc(c.fy)} &nbsp;|&nbsp; Book: ${gl_esc(c.book)}</td><td></td></tr></table>
${isGl ? '' : `<div class="gd-sl"><b>SL Account Code:</b> ${gl_esc(c.sl.code)} &nbsp; <b>SL Account Title:</b> <i>${gl_esc(c.sl.title)}</i></div>`}
<table class="gd-t"><colgroup><col style="width:7%"><col style="width:28%"><col style="width:7%"><col style="width:15%"><col style="width:12%"><col style="width:10%"><col style="width:10%"><col style="width:11%"></colgroup>
<tr><th>Date</th><th>Particulars</th><th>REF</th><th>JEV / DV #</th><th>Check No. / Check Date</th><th>DEBIT</th><th>CREDIT</th><th>BALANCE</th></tr>
${body || '<tr><td></td><td>No postings.</td><td></td><td></td><td></td><td></td><td></td><td></td></tr>'}
</table></div>`;
}
function gl_detailHtml(cards, excel) {
    if (!cards.length) return '<p style="font:14px Arial">No posted transactions.</p>';
    return GL_DETAIL_CSS + cards.map((c, i) => gl_detailCard(c, i > 0, excel)).join('');
}
// Print ▾ → Detailed, one account (the preview window is opened inside the click, before any await)
async function gl_printDetail(kind, code, pick = null) {
    const w = window.open('', '_blank', 'width=1100,height=800');
    if (!w) { gl_showError('Allow pop-ups for this site to open the preview.'); return; }
    w.document.write('<p style="font-family:Arial;padding:24px">Preparing the report…</p>');
    try {
        gl_hideError();
        const opts = pick ? { book_id: String(pick.book_id), fund: pick.fund || '', fiscal_year: String(pick.fiscal_year || '') } : gl_printOpts();
        const json = await gl_fetchLedger(code, opts);
        const cards = await gl_detailCards(kind, json, opts, null);
        gl_menuData = { kind: 'detail', cards, name: `${kind === 'sl' ? 'Subsidiary' : 'General'}-Ledger-Detailed_${code}` };
        gl_writePreview(w, gl_reportTable(false, gl_menuData), gl_menuData.name, 'gl_excelMenu');
    } catch (e) {
        w.close();
        gl_showError('Could not prepare the preview: ' + e.message);
    }
}
// Print ▾ → Ledger card, one account (the NHA card, same as the list view's Print GL / SL)
async function gl_printCard(kind, code, pick = null) {
    const w = window.open('', '_blank', 'width=1100,height=800');
    if (!w) { gl_showError('Allow pop-ups for this site to open the preview.'); return; }
    w.document.write('<p style="font-family:Arial;padding:24px">Preparing the report…</p>');
    try {
        gl_hideError();
        const opts = pick ? { book_id: String(pick.book_id), fund: pick.fund || '', fiscal_year: String(pick.fiscal_year || '') } : gl_printOpts();
        const json = await gl_fetchLedger(code, opts);
        gl_menuData = kind === 'sl'
            ? { kind: 'sl', gl: json.account, sls: gl_slNodesOf(code, json.data ?? []), json, fund: opts.fund }
            : { kind: 'txn', gl: json.account, json, fund: opts.fund };
        gl_writePreview(w, gl_reportTable(false, gl_menuData), gl_reportName(gl_menuData), 'gl_excelMenu');
    } catch (e) {
        w.close();
        gl_showError('Could not prepare the preview: ' + e.message);
    }
}

/* ── Schedule (header Print ▾ → Schedule) — the NHA "SCHEDULE OF …" of the account shown, as of a date ──────────────────
   GET /general_ledger/schedule → lines grouped Sector / Region → office → project / location → contract, a total per group
   and a grand total. Same preview window (Print / Excel) as the ledger reports. */
let gl_schedData = null;   // the schedule now in the preview — what its Excel button exports
const gl_todayIso = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
const GL_LONG_MON = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
const gl_longDate = iso => { const [y, m, d] = String(iso).split('-').map(Number); return `${GL_LONG_MON[m - 1]} ${d}, ${y}`; };
// "Region 3 Regional Office" → "Region 3"; "Region 3 - Bulacan District Office" / "NCR North Sector - Caloocan District Office" → the office after " - "
const gl_rgnName = s => String(s || '').replace(/\s+Regional Office$/i, '');
const gl_officeName = s => String(s || '').replace(/^.+?\s+-\s+/, '');
const gl_schedAmt = v => Math.abs(v) < 0.005 ? '-' : (v < 0 ? `(${gl_num(-v)})` : gl_num(v));
// the API's lines (already ordered) → [{ name, total, offices: [{ name, lines }] }]; an office that is its own group has no name
function gl_schedGroups(rows) {
    const groups = [];
    let g = null, o = null;
    rows.forEach(r => {
        const gk = r.group_rc_id || '';
        if (!g || g.key !== gk) { g = { key: gk, name: gk ? gl_rgnName(r.group_name) : 'Unassigned', offices: [], total: 0 }; groups.push(g); o = null; }
        const ok = r.rc_id || '';
        if (!o || o.key !== ok) { o = { key: ok, name: ok && ok !== gk ? gl_officeName(r.rc_name) : '', lines: [] }; g.offices.push(o); }
        o.lines.push(r);
        g.total += Number(r.amount) || 0;
    });
    groups.forEach(x => { x.total = Math.round(x.total * 100) / 100; });
    return groups;
}
const GL_SCHED_CSS = `.sc-hd{text-align:center;font:bold 13px Arial,Helvetica,sans-serif;line-height:1.55;margin-bottom:14px} .sc-hd .sc-fund{font-weight:normal;font-style:italic;font-size:11px}
 table.sc{width:100%;border-collapse:collapse;font:12px Arial,Helvetica,sans-serif}
 table.sc th{font-weight:bold;text-align:center;border-top:1px solid #000;border-bottom:1px solid #000;padding:5px 6px}
 table.sc th+th,table.sc td+td{border-left:1px solid #000}
 table.sc td{padding:2px 6px;vertical-align:bottom}
 tr.sc-g td:first-child{font-weight:bold;text-transform:uppercase;padding-top:6px} tr.sc-o td:first-child{font-weight:bold;padding-left:14px}
 table.sc td.i1{padding-left:14px} table.sc td.i2{padding-left:26px} div.i1{padding-left:8px}
 table.sc td.n{text-align:right;white-space:nowrap} table.sc td.c{text-align:center}
 tr.sc-t td{font-weight:bold;border-bottom:1px solid #000;padding-top:4px} tr.sc-t td.n{border-top:1px solid #000}
 tr.sc-gt td{font-weight:bold;padding-top:8px} tr.sc-gt td.n{border-bottom:3px double #000}
 table.sc tr{page-break-inside:avoid;break-inside:avoid}`;
function gl_schedHtml(s) {
    const groups = gl_schedGroups(s.data ?? []);
    const body = groups.map(g => `<tr class="sc-g"><td>${gl_esc(g.name)}</td><td></td><td></td></tr>`
        + g.offices.map(o => (o.name ? `<tr class="sc-o"><td>${gl_esc(o.name)}</td><td></td><td></td></tr>` : '')
            + o.lines.map(l => `<tr class="sc-l"><td class="${o.name ? 'i2' : 'i1'}">${gl_esc(l.location || '')}</td>`
                + `<td>${l.contractor ? `<div>${gl_esc(l.contractor)}</div>` : ''}${l.particulars ? `<div class="i1">${gl_esc(l.particulars)}</div>` : ''}</td>`
                + `<td class="n">${gl_schedAmt(Number(l.amount))}</td></tr>`).join('')).join('')
        + `<tr class="sc-t"><td></td><td class="c">Total ${gl_esc(g.name)}</td><td class="n">${gl_schedAmt(g.total)}</td></tr>`).join('');
    return `<style>${GL_SCHED_CSS}</style>
<div class="sc-hd">NATIONAL HOUSING AUTHORITY<br>SCHEDULE OF ${gl_esc(String(s.account.account_title).toUpperCase())}<br>${gl_esc(s.account.account_code)}<br>AS OF ${gl_longDate(s.as_of)}${s.fund ? `<br><span class="sc-fund">${gl_esc(s.fund)}</span>` : ''}</div>
<table class="sc"><colgroup><col style="width:36%"><col style="width:46%"><col style="width:18%"></colgroup>
 <tr><th>PROJECT/LOCATION</th><th>CONTRACT</th><th>AMOUNT</th></tr>
 ${groups.length ? body + `<tr class="sc-gt"><td></td><td class="c">GRAND TOTAL</td><td class="n">${gl_schedAmt(Number(s.total))}</td></tr>`
        : `<tr><td colspan="3" style="padding:10px 6px;">No postings as of ${gl_longDate(s.as_of)}.</td></tr>`}
</table>`;
}
async function gl_printSchedule(code, asOf) {
    if (!code) return;
    if (!/^\d{4}-\d{2}-\d{2}$/.test(asOf || '')) { gl_showError('Pick the "As of" date of the schedule.'); return; }
    const w = window.open('', '_blank', 'width=1000,height=800');   // opened inside the click, before any await
    if (!w) { gl_showError('Allow pop-ups for this site to open the preview.'); return; }
    w.document.write('<p style="font-family:Arial;padding:24px">Preparing the schedule…</p>');
    try {
        const params = new URLSearchParams({ account_code: code, as_of: asOf });
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);
        const res  = await gl_authFetch(`${GL_API}/schedule?${params}`);
        const json = await res.json();
        if (!res.ok || !json.success) throw new Error(json.message || 'HTTP ' + res.status);
        gl_schedData = { ...json, fund: gl_fund() };
        gl_writePreview(w, gl_schedHtml(gl_schedData), `Schedule_${code}_${asOf}`, 'gl_excelSchedule');
    } catch (e) {
        w.close();
        gl_showError('Could not prepare the schedule: ' + e.message);
    }
}
async function gl_excelSchedule(s = gl_schedData) {
    if (!s) return;
    if (!window.ExcelJS) { alert('The Excel library failed to load. Check your internet connection and reload the page.'); return; }
    try {
        const wb = new ExcelJS.Workbook();
        const ws = wb.addWorksheet('Schedule', { pageSetup: { orientation: 'portrait', fitToPage: true, fitToWidth: 1, fitToHeight: 0 } });
        ws.columns = [{ width: 46 }, { width: 62 }, { width: 18 }];
        const blk = { argb: 'FF000000' }, thin = { style: 'thin', color: blk };
        const fmt = '#,##0.00;(#,##0.00);"-"';
        ['NATIONAL HOUSING AUTHORITY', 'SCHEDULE OF ' + String(s.account.account_title).toUpperCase(), s.account.account_code, 'AS OF ' + gl_longDate(s.as_of), ...(s.fund ? [s.fund] : [])]
            .forEach(t => {
                const r = ws.addRow([t]);
                ws.mergeCells(r.number, 1, r.number, 3);
                r.getCell(1).font = { bold: true };
                r.getCell(1).alignment = { horizontal: 'center' };
            });
        ws.addRow([]);
        ws.addRow(['PROJECT/LOCATION', 'CONTRACT', 'AMOUNT']).eachCell((c, i) => {
            c.font = { bold: true }; c.alignment = { horizontal: 'center' };
            c.border = { top: thin, bottom: thin, ...(i > 1 ? { left: thin } : {}) };
        });
        const add = (a, b, amt, o = {}) => {
            const r = ws.addRow([a, b, amt === undefined ? '' : amt]);
            r.getCell(1).alignment = { indent: o.ind || 0, vertical: 'bottom', wrapText: true };
            r.getCell(2).alignment = { vertical: 'bottom', wrapText: true, horizontal: o.center ? 'center' : undefined };
            r.getCell(3).alignment = { horizontal: 'right', vertical: 'bottom' };
            r.getCell(3).numFmt = fmt;
            r.getCell(2).border = { left: thin };
            r.getCell(3).border = { left: thin };
            if (o.bold) [1, 2, 3].forEach(i => { r.getCell(i).font = { bold: true }; });
            if (o.b1) r.getCell(1).font = { bold: true };
            return r;
        };
        const groups = gl_schedGroups(s.data ?? []);
        groups.forEach(g => {
            add(g.name.toUpperCase(), '', undefined, { b1: true });
            g.offices.forEach(o => {
                if (o.name) add(o.name, '', undefined, { ind: 1, b1: true });
                o.lines.forEach(l => add(l.location || '', [l.contractor, l.particulars].filter(Boolean).join('\n'), Number(l.amount), { ind: o.name ? 2 : 1 }));
            });
            const t = add('', 'Total ' + g.name, g.total, { bold: true, center: true });
            t.getCell(1).border = { bottom: thin };
            t.getCell(2).border = { left: thin, bottom: thin };
            t.getCell(3).border = { left: thin, top: thin, bottom: thin };
        });
        if (groups.length) add('', 'GRAND TOTAL', Number(s.total), { bold: true, center: true }).getCell(3).border = { left: thin, bottom: { style: 'double', color: blk } };
        else add('No postings as of ' + gl_longDate(s.as_of), '', undefined);

        const buf = await wb.xlsx.writeBuffer();
        const url = URL.createObjectURL(new Blob([buf], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' }));
        const a = document.createElement('a');
        a.href = url;
        a.download = `Schedule_${s.account.account_code}_${s.as_of}.xlsx`;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        gl_showError('Could not build the Excel file: ' + e.message);
        alert('Could not build the Excel file: ' + e.message);
    }
}

/* ── Print (tree view) — every GL's report in ONE preview window ──────────────────────────────────────────────────────
   Header "Print ▾" → Print GL | Print SL. Both are still previews (the preview window has its own Print / Excel buttons).
     GL : the "All transactions" report (GENERAL LEDGER card) of every GL account — what clicking a Debit / Credit
          amount opens for one account — one sheet per GL.
     SL : the SUBSIDIARY LEDGER report (what the account-description click's Preview shows) of every GL that has SLs.
   Each GL costs one GET /general_ledger/ledger (a few in parallel), so it is slow: a loading modal on the page shows
   "Loading GL 3/50" first, and only then does the preview window open. Each GL sheet starts on its own printed page. */
let gl_allData = { sl: null, gl: null, 'gl-detail': null, 'sl-detail': null };   // last result per kind ({ kind, items, failed }) — what that preview's Excel button exports
let gl_allBusy = false;
let gl_allCancel = false;
let gl_allReady = null;   // a finished report waiting for the user's click when the browser blocked the automatic pop-up
function gl_excelAllSl() { gl_excelTxns(gl_allData.sl); }
function gl_excelAllGl() { gl_excelTxns(gl_allData.gl); }
function gl_excelAllGlDetail() { gl_excelTxns(gl_allData['gl-detail']); }
function gl_excelAllSlDetail() { gl_excelTxns(gl_allData['sl-detail']); }
function gl_loadModal(show) { $gl('gl-load-overlay')?.classList.toggle('open', !!show); }
function gl_loadProgress(done, total, last) {
    $gl('gl-load-txt').textContent = `Loading GL ${done}/${total}`;
    $gl('gl-load-bar').style.width = Math.round(done / Math.max(total, 1) * 100) + '%';
    $gl('gl-load-sub').textContent = last ? `Loaded ${last.account_code} — ${last.account_title}` : 'Please wait…';
}
function gl_togglePrintAll(open, anchor) {
    const menu = $gl('gl-printall-menu');
    if (!menu) return;
    if (!open || !anchor) { menu.style.display = 'none'; $gl('gl-printall')?.setAttribute('aria-expanded', 'false'); return; }
    menu.style.display = '';
    const r = anchor.getBoundingClientRect();
    const w = menu.offsetWidth || 240;
    menu.style.left = Math.max(8, Math.min(r.right - w, window.innerWidth - w - 8)) + 'px';
    menu.style.top  = (r.bottom + 6) + 'px';
    anchor.setAttribute('aria-expanded', 'true');
}
// opens the report window (inside a click when it can, so the pop-up blocker lets it through) and fills it
function gl_openReport(rep) {
    const w = window.open('', '_blank', 'width=1100,height=800');
    if (!w) return false;
    gl_writePreview(w, rep.html, rep.name, rep.excelFn);
    return true;
}

async function gl_printAll(mode) {   // mode: 'gl' | 'sl' | 'gl-detail' | 'sl-detail'
    if (gl_allBusy) return;
    const isDetail = mode.endsWith('-detail');
    const isSl = mode.startsWith('sl');
    const targets = gl_list.filter(n => n.kind === 'gl')
        .map(gl => ({ gl, sls: gl_slsUnder(gl.account_id).filter(n => !n.direct) }))
        .filter(t => !isSl || t.sls.length);   // SL: only GLs that have subsidiary ledgers
    if (!targets.length) { gl_showError(isSl ? 'No GL account has subsidiary ledgers to preview.' : 'No GL account to preview.'); return; }

    gl_allBusy = true; gl_allCancel = false; gl_allReady = null;
    const total = targets.length;
    $gl('gl-load-title').textContent = isSl ? 'Preparing Subsidiary Ledger' : 'Preparing General Ledger';
    $gl('gl-load-ready').style.display = 'none';
    $gl('gl-load-cancel').textContent = 'Cancel';
    $gl('gl-load-bar').style.background = '';
    gl_loadProgress(0, total);
    gl_loadModal(true);
    $gl('gl-printall').disabled = true;

    const params = new URLSearchParams();
    const fy = $gl('gl-filter-fy').value;
    if (fy) params.set('fiscal_yr', fy);
    const book = $gl('gl-filter-book').value;
    if (book) params.set('book_id', book);
    const failed = [];
    const results = new Array(total).fill(null);
    let done = 0, next = 0;

    const worker = async () => {
        while (!gl_allCancel) {
            const i = next++;
            if (i >= total) return;
            const { gl, sls } = targets[i];
            try {
                const p = new URLSearchParams(params);
                p.set('account_code', gl.account_code);
                const res  = await gl_authFetch(`${GL_API}/ledger?${p}`);
                const json = await res.json();
                if (!res.ok || !json.success) throw new Error(json.message || 'HTTP ' + res.status);
                // keep only what the report needs, not the whole response
                if (isDetail) {
                    const cards = await gl_detailCards(isSl ? 'sl' : 'gl', json, { fiscal_year: fy, book_id: book, fund: gl_fund() }, isSl ? sls : null);
                    if (cards.length) results[i] = { gl, cards };
                } else if (isSl) {
                    const secs = gl_slSectionsOf(json, sls);
                    if (secs.length) results[i] = { gl, secs };
                } else if ((json.data ?? []).length) {
                    results[i] = { gl, ledger: gl_ledgerSection(json.data, json.debit_normal, r => Number(r.running_balance)) };
                }
            } catch (e) {
                failed.push(gl.account_code);
            }
            done++;
            if (!gl_allCancel) gl_loadProgress(done, total, gl);
        }
    };

    try {
        await Promise.all(Array.from({ length: Math.min(3, total) }, worker));
        if (gl_allCancel) { gl_loadModal(false); return; }
        const data = isDetail
            ? { kind: 'detail', cards: results.filter(Boolean).flatMap(x => x.cards), failed, name: (isSl ? 'Subsidiary-Ledger-Detailed' : 'General-Ledger-Detailed') + '_All' }
            : { kind: isSl ? 'allsl' : 'allgl', items: results.filter(Boolean), failed };
        gl_allData[mode] = data;
        const rep = { html: gl_reportTable(false, data), name: gl_reportName(data), excelFn: isDetail ? (isSl ? 'gl_excelAllSlDetail' : 'gl_excelAllGlDetail') : isSl ? 'gl_excelAllSl' : 'gl_excelAllGl' };
        if (gl_openReport(rep)) {
            gl_loadModal(false);
        } else {
            // the wait outlasted the click, so the browser blocked the pop-up — one more click opens it
            gl_allReady = rep;
            $gl('gl-load-txt').textContent = 'Report ready';
            $gl('gl-load-sub').textContent = 'Your browser blocked the pop-up. Click below to open the preview.';
            $gl('gl-load-ready').style.display = '';
            $gl('gl-load-cancel').textContent = 'Close';
        }
    } catch (e) {
        gl_loadModal(false);
        gl_showError('Could not prepare the preview: ' + e.message);
    } finally {
        gl_allBusy = false;
        $gl('gl-printall').disabled = false;
    }
}

// Amount click → every posting of the account (its SLs' included, same rollup as the single-account ledger), oldest first
let gl_txnReq = 0;
async function gl_openGlTxns(id) {
    const gl = gl_nodes[id];
    if (!gl) return;
    const req = ++gl_txnReq;
    gl_txnData = null; gl_showExport(false);
    const tbody = $gl('gl-sl-tbody');
    gl_setSlHead(false);
    $gl('gl-sl-title').textContent = `${gl.account_code} — ${gl.account_title}`;
    $gl('gl-sl-subtitle').textContent = 'All transactions';
    tbody.innerHTML = '<tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    $gl('gl-sl-overlay').classList.add('open');
    try {
        const params = new URLSearchParams({ account_code: gl.account_code });
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);
        const res  = await gl_authFetch(`${GL_API}/ledger?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (req !== gl_txnReq) return;
        if (!json.success) throw new Error(json.message || 'Failed to load the transactions.');
        const rows = json.data ?? [];
        gl_txnData = { kind: 'txn', gl, json };
        gl_showExport(rows.length > 0);
        $gl('gl-sl-subtitle').textContent = `${rows.length} transaction${rows.length === 1 ? '' : 's'} · ${json.normal_balance}-normal, ${json.account.account_type}`;
        tbody.innerHTML = rows.length
            ? rows.map(r => gl_rowHtml(r, json.debit_normal)).join('') + `<tr class="gl-k-group"><td colspan="4" style="font-weight:700;">Total</td>
                <td class="text-end nha-mono u-tabular-nums u-text-sm" style="font-weight:700;">${gl_peso(json.total_debit)}</td>
                <td class="text-end nha-mono u-tabular-nums u-text-sm" style="font-weight:700;">${gl_peso(json.total_credit)}</td>
                <td class="text-end nha-mono u-tabular-nums u-text-sm gl-bal-col" style="font-weight:700;">${gl_peso(Math.abs(json.ending_balance))}${gl_balSide(json.ending_balance, json.debit_normal)}</td></tr>`
            : '<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No posted transactions for this account.</span></div></td></tr>';
    } catch (e) {
        if (req !== gl_txnReq) return;
        tbody.innerHTML = `<tr><td colspan="7"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">Could not load transactions: ${gl_esc(e.message)}</span></div></td></tr>`;
    }
}
function gl_closeGlPopup() { gl_txnReq++; gl_txnData = null; $gl('gl-sl-overlay')?.classList.remove('open'); }

// shows a row when every ancestor within the popup is open; a node's transaction rows (gl-txn) follow their owner
function gl_applyTree(tbody) {
    tbody = tbody || $gl('gl-sl-tbody');
    const vis = {};
    tbody.querySelectorAll('tr').forEach(tr => {
        const id = tr.dataset.id;
        if (id !== undefined) {
            const p = tr.dataset.parent;
            vis[id] = !(p in vis) || (vis[p] && gl_open[p]);
            tr.style.display = (vis[id] && !tr.classList.contains('gl-self')) ? '' : 'none';
            const t = tr.querySelector('.gl-sl-toggle');
            if (t && tr.classList.contains('gl-clickable')) t.innerHTML = gl_open[id] ? '&#9662;' : '&#9656;';
        } else if (tr.dataset.txnOf !== undefined) {
            tr.style.display = (vis[tr.dataset.txnOf] && gl_open[tr.dataset.txnOf]) ? '' : 'none';
        }
    });
}

async function gl_toggleNode(row) {
    const id = row.dataset.id, n = gl_nodes[id];
    gl_open[id] = !gl_open[id];
    gl_applyTree(row.closest('tbody'));
    if (gl_open[id] && n.own_count > 0 && !row.dataset.txnLoaded) await gl_loadTxns(row, n);
}

// The account's own postings, inserted right under its row (above its sub-accounts when it has any)
async function gl_loadTxns(row, n) {
    row.dataset.txnLoaded = '1';
    try {
        const params = new URLSearchParams({ account_code: n.account_code });
        const fy = $gl('gl-filter-fy').value;
        if (fy) params.set('fiscal_yr', fy);
        const book = $gl('gl-filter-book').value;
        if (book) params.set('book_id', book);
        const res  = await gl_authFetch(`${GL_API}/postings?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the transactions.');
        const label = (json.data ?? []).length ? `<tr class="gl-txn-head">${GL_TXN_HEAD_CELLS}</tr>` : '';
        const tpl = document.createElement('tbody');
        tpl.innerHTML = label + ((json.data ?? []).length ? json.data.map(r => gl_rowHtml(r, json.debit_normal)).join('') : '<tr><td colspan="7" style="color:var(--afims-text-muted,#62657E);font-size:var(--fs-xs);">No postings.</td></tr>');
        tpl.querySelectorAll('tr').forEach(tr => {
            tr.classList.add('gl-txn');
            tr.dataset.txnOf = n.account_id;
            tr.style.setProperty('--gl-depth', n.depth);
        });
        const tb = row.closest('tbody');
        row.after(...tpl.children);
        gl_applyTree(tb);
    } catch (e) {
        row.dataset.txnLoaded = '';
        gl_showError('Could not load transactions: ' + e.message);
    }
}

function gl_rowHtml(r, debitNormal, groupId) {
    const styles = [];
    if (groupId) styles.push('display:none');
    if (r.reversed_gl_id) styles.push('opacity:.7');
    const attrs = (groupId ? ` class="gl-sl-rows" data-group="${groupId}"` : '') + (styles.length ? ` style="${styles.join(';')}"` : '');
    return `<tr${attrs}>
        <td class="nha-td-date">${gl_fmtDate(r.posting_date)}</td>
        <td class="nha-mono">${gl_esc(r.jev_no) || '—'}${r.reference_no && r.reference_no.endsWith('-REV') ? ' <span class="nha-bdg nha-bdg-orange" style="margin-left:4px;">Reversal</span>' : ''}</td>
        <td class="nha-mono">${gl_esc(r.dv_no) || '—'}</td>
        <td>${r.sl_account_code ? `<span class="nha-mono" style="font-size:var(--fs-xs);color:var(--afims-text-muted,#62657E);margin-right:6px;" title="Posted to this subsidiary ledger">${gl_esc(r.sl_account_code)}</span>` : ''}${gl_esc(r.description) || gl_esc(r.sl_name) || '—'}</td>
        <td class="text-end nha-mono u-tabular-nums u-text-sm">${Number(r.debit) > 0 ? gl_peso(r.debit) : ''}</td>
        <td class="text-end nha-mono u-tabular-nums u-text-sm">${Number(r.credit) > 0 ? gl_peso(r.credit) : ''}</td>
        <td class="text-end nha-mono u-tabular-nums u-text-sm gl-bal-col">${gl_peso(Math.abs(r.running_balance))}${gl_balSide(r.running_balance, debitNormal)}</td>
    </tr>`;
}

/* Running/ending balance is signed (debit - credit) for debit-normal accounts, (credit - debit)
   otherwise — see GeneralLedgerService::DEBIT_NORMAL_TYPES. A positive value is always the
   account's NORMAL side, so the Dr/Cr suffix has to flip with debitNormal, not just the sign. */
function gl_balSide(balance, debitNormal) {
    const onDebitSide = debitNormal ? Number(balance) >= 0 : Number(balance) < 0;
    return onDebitSide ? ' Dr' : ' Cr';
}

/* ── Formatters ────────────────────────────────────────────── */
function gl_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function gl_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function gl_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function gl_showError(msg) { $gl('gl-error').style.display = 'flex'; $gl('gl-error-msg').textContent = msg; }
function gl_hideError()    { $gl('gl-error').style.display = 'none'; }
</script>
<?php
// Print → the shared print preview (prp_open + prp_glPayload — GL, NHA Form No. 11; prp_glSlPayload — SL).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
