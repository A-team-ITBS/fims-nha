<?php

namespace PHPMaker2024\FIMS;

/**
 * status-of-fund-page.php — "Status of Funds": where each fund source stands — what is available, what has been disbursed —
 * with the SARO / NCA (or Trust Fund) records behind it. Sidebar: Registry → Status of Funds. Read-only.
 *
 * There is no fund-source master table in FIMS, so a fund source is a group of fims.fund_tracking records:
 * NG Subsidy (SARO + NCA), Trust Funds (Trust Fund + OR) and Supplemental Funds (SARO + NCA of the SUPPLEMENTAL nature). What each
 * figure means is written up in app/services/StatusOfFundService.php. Disbursed = what the RELEASED disbursement vouchers have paid,
 * each counted once: through the SARO it is linked to, or — with no SARO — through the RFA / LAA it is connected to (by the Type of Fund of the budget line it
 * drew from; a line a supplemental request created is Supplemental). A Trust Fund has no SARO, so its Disbursed is "—" until an RFA / LAA of the Trust Fund type
 * of fund has a released voucher. A released voucher that is counted nowhere is said so, above the table (#sof-gap): it needs a SARO, or an RFA / LAA with a Type of Fund.
 *
 * Two views in one page, switched in place (no reload; the URL hash holds the fund source, #NG / #TRUST / #SUPP):
 *   Overview  one row per fund source — Funds available, Disbursed, Balance, Utilization — and the Utilization Report of all of them
 *   Detail    one fund source: its cards, the per-Program split (or per Trust Fund), and the SARO → NCA tree table (the look of
 *             saro-registry-page.php, without its Actions column and entry forms: this page only reads)
 *
 * Data comes from the FIMS API only:
 *   /status_of_fund/overview  /status_of_fund/source/{key}   (app/routes/status_of_fund.php)
 *   /fund_tracking/list                                      (the tree table; app/routes/fund_tracking.php)
 *
 * The Utilization Report opens as a modal; Print sends just the report sheet to the printer (Save as PDF from there) and
 * Excel (.csv) downloads its table.
 */
@session_start();
$_sof_api = Config('API.FIMS_URL');
?>

<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
    #sof-root { --sof-line: color-mix(in srgb, var(--afims-success, #11A752) 50%, transparent); }

    /* ── Summary cards: grow with the amount instead of clipping it ── */
    #sof-root .nha-summary { display: flex; flex-wrap: wrap; gap: 14px; }
    #sof-root .nha-summary > .nha-sc { flex: 1 1 230px; min-width: 230px; }
    #sof-root .nha-sc-val { white-space: nowrap; font-size: 22px; }

    /* ── Breadcrumb + fund-source chips (detail view) ── */
    #sof-root .sof-crumb { display: flex; align-items: center; gap: 8px; margin: 0 0 12px; font-size: 12px; color: var(--afims-text-muted, #62657E); }
    #sof-root .sof-crumb a { color: inherit; text-decoration: none; cursor: pointer; }
    #sof-root .sof-crumb a:hover { text-decoration: underline; }
    #sof-root .sof-crumb b { color: var(--afims-heading, #000E3E); }
    #sof-root .sof-chips { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 16px; }
    #sof-root .sof-chip { padding: 8px 16px; border-radius: 10px; border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface, #FFFFFF);
        font-size: 13px; font-family: inherit; color: var(--afims-text, #1A1B21); cursor: pointer; transition: background .15s, color .15s; }
    #sof-root .sof-chip:hover { background: var(--afims-surface-subtle, #F4F7FA); }
    #sof-root .sof-chip.on { background: var(--afims-heading-fill, #000E3E); border-color: var(--afims-heading-fill, #000E3E); color: #FFFFFF; font-weight: 600; }

    /* ── Tables ── */
    #sof-root .sof-card-hd { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; padding: 13px 16px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
    #sof-root .sof-card-hd h3 { margin: 0; font-size: 14.5px; font-weight: 700; color: var(--afims-heading, #000E3E); }
    #sof-root .sof-card-hd span { font-size: 12px; color: var(--afims-text-muted, #62657E); }
    #sof-root .sof-sec { margin-top: 16px; }
    #sof-root .sof-tbl { min-width: 760px; }
    #sof-root .sof-tbl .sof-num { font-family: 'IBM Plex Mono', monospace; text-align: right; white-space: nowrap; }
    #sof-root .sof-amt { font-size: .85em; white-space: nowrap; }
    #sof-root .sof-tbl tr.sof-total td { background: var(--afims-surface-subtle, #F4F7FA); font-weight: 700; border-top: 2px solid var(--afims-border, #E4ECF2); color: var(--afims-heading, #000E3E); }
    #sof-root .sof-name { font-weight: 600; color: var(--afims-heading, #000E3E); }
    #sof-root .sof-empty { text-align: center; color: var(--afims-text-disabled, #A9AAB8); padding: 32px; font-size: 13px; }
    #sof-root .sof-dash { color: var(--afims-text-disabled, #A9AAB8); }
    #sof-root .sof-foot { padding: 10px 16px; border-top: 1px solid var(--afims-border, #E4ECF2); font-size: 12px; color: var(--afims-text-muted, #62657E);
        display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

    /* utilization bar */
    #sof-root .sof-ub { display: flex; align-items: center; gap: 8px; min-width: 130px; }
    #sof-root .sof-bar { flex: 1; height: 7px; border-radius: 99px; background: #E7ECF4; overflow: hidden; }
    #sof-root .sof-bar span { display: block; height: 100%; border-radius: 99px; background: var(--afims-primary, #0035A9); }
    #sof-root .sof-ub em { font-style: normal; font-family: 'IBM Plex Mono', monospace; font-size: 11.5px; color: var(--afims-text-muted, #62657E); width: 46px; text-align: right; }

    /* row icons */
    #sof-root .sof-act { display: flex; gap: 6px; justify-content: flex-end; }
    #sof-root .sof-ib { width: 30px; height: 30px; border-radius: 7px; border: 1px solid var(--afims-border, #E4ECF2); display: flex; align-items: center; justify-content: center;
        background: var(--afims-surface, #FFFFFF); color: var(--afims-heading, #000E3E); cursor: pointer; padding: 0; transition: background .15s; }
    #sof-root .sof-ib:hover { background: var(--afims-surface-subtle, #F4F7FA); }
    #sof-root .sof-ib svg { width: 14px; height: 14px; }
    #sof-root .sof-row-click { cursor: pointer; }
    #sof-root .sof-row-click:hover td { background: var(--afims-surface-subtle, #F4F7FA); }

    /* ── Tree table: a SARO is the strong parent row, the NCAs released under it the recessed rows below it (as saro-registry-page.php) ── */
    #sof-root .sof-tree { min-width: 1100px; }
    #sof-root .sof-tree .sof-desc { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; max-width: 320px;
        font-size: 12px; white-space: normal; overflow-wrap: anywhere; }
    #sof-root .sof-parent { --sof-row-bg: var(--afims-surface, #FFFFFF); }
    #sof-root .sof-parent > td, #sof-root .sof-child > td { background: var(--sof-row-bg); }
    #sof-root .sof-parent.has-kids { cursor: pointer; }
    #sof-root .sof-parent:hover { --sof-row-bg: var(--afims-surface-subtle, #F4F7FA); }
    #sof-root .sof-parent > td:first-child { vertical-align: top; white-space: nowrap; }
    #sof-root .sof-parent .sof-ref { font-size: 13px; font-weight: 700; }
    #sof-root .sof-parent.expanded { --sof-row-bg: color-mix(in srgb, var(--afims-primary-soft, #E6EBF6) 90%, var(--afims-primary, #0035A9)); }
    #sof-root .sof-parent.expanded > td { border-bottom: none; }
    #sof-root .sof-parent.expanded:hover { --sof-row-bg: color-mix(in srgb, var(--afims-primary-soft, #E6EBF6) 80%, var(--afims-primary, #0035A9)); }
    #sof-root .sof-parent.expanded > td:first-child { position: relative; box-shadow: inset 4px 0 0 var(--afims-primary, #0035A9); }
    #sof-root .sof-parent.expanded > td:first-child::after { content: ''; position: absolute; left: 26px; top: 34px; bottom: -1px; width: 2px; background: var(--sof-line); }
    #sof-root .sof-ref-line { display: flex; align-items: center; }
    #sof-root .sof-ref { font-family: 'IBM Plex Mono', monospace; color: var(--afims-primary-text, #0035A9); }
    #sof-root .sof-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; padding-left: 28px; }
    #sof-root .sof-caret { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 22px; height: 22px; margin-right: 6px;
        border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; background: var(--afims-surface, #FFFFFF); color: var(--afims-heading, #000E3E);
        transition: background .15s, color .15s, border-color .15s; }
    #sof-root .sof-caret svg { width: 12px; height: 12px; transition: transform .15s; }
    #sof-root .sof-caret.none { visibility: hidden; }
    #sof-root .sof-parent.expanded .sof-caret { background: var(--afims-heading-fill, #000E3E); border-color: var(--afims-primary, #0035A9); color: #FFFFFF; }
    #sof-root .sof-parent.expanded .sof-caret svg { transform: rotate(90deg); }
    #sof-root .sof-child { --sof-row-bg: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 14%, var(--afims-surface, #FFFFFF)); }
    #sof-root .sof-child > td { border-bottom: 1px dashed var(--afims-border, #E4ECF2); font-size: 12px; }
    #sof-root .sof-child:hover { --sof-row-bg: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 34%, var(--afims-surface, #FFFFFF)); }
    #sof-root .sof-child.last > td { border-bottom: 2px solid var(--afims-border-strong, #C5CEDA); }
    #sof-root .sof-child > td:first-child { position: relative; white-space: nowrap; box-shadow: inset 3px 0 0 var(--afims-success, #11A752); }
    #sof-root .sof-child > td:first-child::before { content: ''; position: absolute; left: 26px; top: 0; bottom: -1px; width: 2px; background: var(--sof-line); }
    #sof-root .sof-child.last > td:first-child::before { bottom: 50%; }
    #sof-root .sof-child > td:first-child::after { content: ''; position: absolute; left: 26px; top: 50%; width: 16px; height: 2px; background: var(--sof-line); }
    #sof-root .sof-child-ref { padding-left: 40px; }
    #sof-root .sof-child-ref .sof-tags { padding-left: 0; }
    #sof-root .sof-child .sof-ref { font-size: 12px; font-weight: 600; }
    #sof-root .sof-tag { display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    #sof-root .sof-tag-parent { background: var(--afims-primary-text, #0035A9); color: var(--afims-surface, #FFFFFF); letter-spacing: .04em; }
    #sof-root .sof-tag-count { background: var(--afims-surface, #FFFFFF); color: var(--afims-success-hover, #0C7539); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--afims-success, #11A752) 45%, transparent); }
    #sof-root .sof-tag-child { background: var(--afims-success-soft, #DBF2E5); color: var(--afims-success-hover, #0C7539); }
    #sof-root .sof-tag-none { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-disabled, #A9AAB8); }

    /* ── Utilization Report modal: a sheet of paper ── */
    #sof-root .sof-rp-body { background: #E9EDF4; padding: 22px; }
    #sof-root .sof-paper { background: #FFFFFF; color: #1A1B21; padding: 30px 36px 28px; box-shadow: 0 2px 10px rgba(16, 24, 40, .15); border-radius: 3px; font-family: 'IBM Plex Sans', sans-serif; }
    #sof-root .sof-lh { display: flex; align-items: center; gap: 16px; padding-bottom: 14px; border-bottom: 2px solid #000E3E; }
    #sof-root .sof-lh img { height: 62px; }
    #sof-root .sof-org { font-size: 13px; font-weight: 600; color: #62657E; letter-spacing: .04em; text-transform: uppercase; }
    #sof-root .sof-rt { font-size: 22px; font-weight: 800; color: #000E3E; letter-spacing: .02em; margin-top: 2px; }
    #sof-root .sof-rs { font-size: 13px; color: #62657E; margin-top: 2px; }
    #sof-root .sof-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin: 16px 0 4px; }
    #sof-root .sof-meta span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #62657E; }
    #sof-root .sof-meta b { font-size: 13.5px; color: #000E3E; }
    #sof-root .sof-st { margin: 18px 0 8px; font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #000E3E; }
    #sof-root .sof-rtbl { width: 100%; border-collapse: collapse; border: 1px solid #E4ECF2; }
    #sof-root .sof-rtbl th { background: #000E3E; color: #FFFFFF; text-align: left; font-size: 9.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; padding: 10px; white-space: nowrap; }
    #sof-root .sof-rtbl td { padding: 10px; font-size: 12.5px; border-bottom: 1px solid #E4ECF2; }
    #sof-root .sof-rtbl th.r, #sof-root .sof-rtbl td.r { text-align: right; }
    #sof-root .sof-rtbl td.r { font-family: 'IBM Plex Mono', monospace; white-space: nowrap; }
    #sof-root .sof-rtbl tr.sof-total td { background: #F4F7FA; font-weight: 700; color: #000E3E; border-top: 2px solid #E4ECF2; }
    #sof-root .sof-two { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
    #sof-root .sof-two > div { border: 1px solid #E4ECF2; border-radius: 8px; padding: 11px 13px; background: #FAFBFD; }
    #sof-root .sof-two span { display: block; font-size: 10px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase; color: #62657E; }
    #sof-root .sof-two b { display: block; font-size: 13px; color: #000E3E; margin: 3px 0 2px; }
    #sof-root .sof-two small { display: block; font-size: 12px; line-height: 1.5; color: #62657E; }
    #sof-root .sof-sig { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 34px; text-align: center; font-size: 12px; font-weight: 600; color: #000E3E; }
    #sof-root .sof-sig i { display: block; border-top: 1px solid #9AA0B4; margin-bottom: 6px; }
    #sof-root .sof-note { margin-top: 14px; font-size: 11px; color: #62657E; line-height: 1.5; }
    /* released vouchers that are counted in no fund source: why a voucher may not have changed a figure */
    #sof-root .sof-gap { margin: 0 0 14px; padding: 10px 14px; border-radius: 10px; border: 1px solid #F5D58A; background: #FFF8E6; color: #7A4B00; font-size: 12.5px; line-height: 1.55; }
    #sof-root .sof-gap strong { font-weight: 700; }
</style>

<div id="sof-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="app-page-header no-print">
        <div class="app-page-header-icon">
            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
            </svg>
        </div>
        <div>
            <h1 class="app-page-title" id="sof-title">Status of Funds</h1>
            <p class="app-page-subtitle" id="sof-subtitle">Where each fund source stands — what is available and what has been disbursed</p>
        </div>
        <div class="app-page-header-actions">
            <button id="sof-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="sof-report-open" type="button" class="nha-btn-hdr nha-btn-hdr-white" title="Utilization Report">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Utilization Report
            </button>
            <span class="nha-hdr-upd" id="sof-updated">—</span>
        </div>
    </div>

    <div id="sof-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="sof-error-msg">Failed to load data. Please refresh.</span>
    </div>

    <!-- ── DETAIL: breadcrumb + fund-source chips ───────────── -->
    <div id="sof-detail-nav" class="no-print" style="display:none;">
        <div class="sof-crumb"><a id="sof-back">Status of Funds</a><span>›</span><b id="sof-crumb-name"></b></div>
        <div class="sof-chips" id="sof-chips"></div>
    </div>

    <!-- ── SUMMARY CARDS ──────────────────────────────────── -->
    <div class="nha-summary" id="sof-cards"></div>

    <!-- ── released vouchers counted in no fund source (sof_renderGap) ── -->
    <div class="sof-gap no-print" id="sof-gap" style="display:none;"></div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-md">
                <label class="nha-filter-label" for="sof-year">Fiscal Year</label>
                <select id="sof-year" class="nha-filter-select"><option value="">All fiscal years</option></select>
            </div>
            <div class="nha-filter-group nha-filter-group-lg" id="sof-source-group">
                <label class="nha-filter-label" for="sof-source">Fund Source</label>
                <select id="sof-source" class="nha-filter-select"><option value="">All Fund Sources</option></select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button id="sof-reset" type="button" class="nha-btn nha-btn-ghost nha-btn-sm">Reset</button>
            </div>
        </div>
    </div>

    <!-- ── OVERVIEW: the fund sources ──────────────────────── -->
    <div id="sof-overview">
        <div class="nha-table-wrap">
            <div class="sof-card-hd"><h3>Fund sources</h3><span>A summary per fund source · open one for its records · Amounts in ₱</span></div>
            <div class="nha-tbl-scroll">
                <table class="nha-tbl sof-tbl">
                    <thead>
                        <tr>
                            <th>Fund source</th>
                            <th style="text-align:right;">Funds available (₱)</th>
                            <th style="text-align:right;">Disbursed (₱)</th>
                            <th style="text-align:right;">Balance (₱)</th>
                            <th>Utilization</th>
                            <th class="no-print"></th>
                        </tr>
                    </thead>
                    <tbody id="sof-tbody"><tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr></tbody>
                </table>
            </div>
            <div class="sof-foot"><span id="sof-count"></span><span>Funds available = NCA released (NG Subsidy, Supplemental) or receipts (Trust Funds) · Disbursed = released vouchers, through the SARO or through the RFA / LAA</span></div>
        </div>
    </div>

    <!-- ── DETAIL: one fund source ─────────────────────────── -->
    <div id="sof-detail" style="display:none;">
        <div class="nha-table-wrap sof-sec" id="sof-split-wrap">
            <div class="sof-card-hd"><h3 id="sof-split-title">By program</h3><span id="sof-split-hint">Amounts in ₱</span></div>
            <div class="nha-tbl-scroll"><table class="nha-tbl sof-tbl" id="sof-split"></table></div>
            <div class="sof-foot"><span id="sof-split-count"></span><span id="sof-split-note"></span></div>
        </div>

        <div class="nha-table-wrap sof-sec" id="sof-tree-wrap">
            <div class="sof-card-hd"><h3 id="sof-tree-title">SARO and NCA registry</h3><span>Each SARO with the NCAs released under it · Amounts in ₱</span></div>
            <div class="nha-tbl-scroll">
                <table class="nha-tbl sof-tbl sof-tree">
                    <thead>
                        <tr>
                            <th style="min-width:210px;">SARO ID</th>
                            <th style="min-width:240px;">Description</th>
                            <th>Nature of Fund</th>
                            <th>Fiscal Year</th>
                            <th>Date</th>
                            <th style="text-align:right;">Amount (₱)</th>
                            <th style="text-align:right;">NCA Received (₱)</th>
                            <th style="text-align:right;">Unreleased (₱)</th>
                        </tr>
                    </thead>
                    <tbody id="sof-tree-body"></tbody>
                </table>
            </div>
            <div class="nha-pag-wrap no-print">
                <span class="nha-pag-info">Showing <strong><span id="sof-from">0</span>–<span id="sof-to">0</span></strong> of <strong><span id="sof-total">0</span></strong> records — click a row with a chevron to expand or collapse its NCAs</span>
                <div id="sof-pagination" class="nha-pag-btns"></div>
            </div>
        </div>
    </div>

    <!-- ══════════════ UTILIZATION REPORT ══════════════ -->
    <div id="sof-rp-ov" class="nha-overlay no-print">
        <div class="nha-modal u-modal-xl">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="sof-rp-title">Utilization Report</h3>
                <div style="display:flex;gap:8px;align-items:center;margin-left:auto;">
                    <button type="button" class="nha-btn nha-btn-ghost nha-btn-sm" id="sof-rp-print">Print</button>
                    <button type="button" class="nha-btn nha-btn-ghost nha-btn-sm" id="sof-rp-csv">Excel (.csv)</button>
                    <button type="button" class="nha-modal-close" id="sof-rp-close" aria-label="Close">&times;</button>
                </div>
            </div>
            <div class="nha-modal-body sof-rp-body">
                <div class="sof-paper" id="sof-paper">
                    <div class="sof-lh">
                        <img src="/_shared/images/letterhead/nha-logo.png" alt="NHA">
                        <div>
                            <div class="sof-org">National Housing Authority</div>
                            <div class="sof-rt">UTILIZATION REPORT</div>
                            <div class="sof-rs" id="sof-rp-sub"></div>
                        </div>
                    </div>
                    <div id="sof-rp-content"></div>
                </div>
            </div>
        </div>
    </div>

</div><!-- /#sof-root -->

<script>
/* ═══════════════════════════════════════════════════════════════
   STATUS OF FUNDS   prefix: sof_
   Registry → Status of Funds | read-only | API-driven
═══════════════════════════════════════════════════════════════ */

const SOF_API = <?= json_encode($_sof_api) ?>;
const SOF_U = {
    overview: SOF_API + '/status_of_fund/overview',
    source:   key => SOF_API + '/status_of_fund/source/' + encodeURIComponent(key),
    tree:     SOF_API + '/fund_tracking/list',
};

// The three fund sources, in the order the API sends them. `tree`: the SARO / NCA tree table (Trust Funds have a flat table instead).
const SOF_SOURCES = {
    NG:    { name: 'NG Subsidy',         tree: true,  sub: 'SARO and NCA releases, and what has been disbursed against them' },
    TRUST: { name: 'Trust Funds',        tree: false, sub: 'Trust fund records and the official receipts collected under them' },
    SUPP:  { name: 'Supplemental Funds', tree: true,  sub: 'Supplemental SARO and NCA releases, and what has been disbursed against them' },
};
const SOF_NATURE_SUPP = 'SUPPLEMENTAL';

const $sof = id => document.getElementById(id);
const sof_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ── State ──────────────────────────────────────────────────── */
const sof = {
    year: '',                 // the Fiscal Year filter ('' = every year)
    years: [],
    filterKey: '',            // the Fund Source filter of the overview ('' = all)
    view: 'overview',         // 'overview' | 'detail'
    key: 'NG',                // the fund source open in the detail view
    overview: null,           // last /status_of_fund/overview data
    detail: null,             // last /status_of_fund/source/{key} data
    req: 0,                   // bumped per request so a stale response never overwrites a newer one
    tree: { rows: [], total: 0, page: 1, limit: 10, loaded: false, error: null, req: 0, expanded: new Set(), collapsed: new Set() },
    report: null,             // what the open Utilization Report holds: { title, sub, head, rows, total, csv }
};

/* ── API helpers ────────────────────────────────────────────── */
async function sof_api(url, opts = {}) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...opts,
        headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
    });
    let json = null;
    try { json = await res.json(); } catch (_) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) {
        throw new Error((json && (json.message || json.error)) || ('HTTP ' + res.status));
    }
    return json;
}

function sof_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${colors[type] || colors.info};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    t.innerHTML = `<div style="flex:1"><span>${sof_esc(msg)}</span></div><button type="button" style="border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    t.querySelector('button').addEventListener('click', () => t.remove());
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4500);
}

function sof_showError(msg) { $sof('sof-error').style.display = 'flex'; $sof('sof-error-msg').textContent = msg; }
function sof_hideError() { $sof('sof-error').style.display = 'none'; }
function sof_setUpdated() { $sof('sof-updated').textContent = 'Updated: ' + new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }); }

/* ── Formatting ─────────────────────────────────────────────── */
const sof_dash = '<span class="sof-dash">—</span>';
const sof_n = v => (parseFloat(v) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const sof_peso = v => '₱ ' + sof_n(v);
const sof_amtCell = v => (v === null || v === undefined) ? sof_dash : `<span class="sof-amt">${sof_esc(sof_peso(v))}</span>`;
const sof_pct = v => (v === null || v === undefined) ? null : (Math.round(v * 10) / 10).toFixed(1) + '%';
const sof_yearLabel = () => (sof.year ? 'FY ' + sof.year : 'All fiscal years');

function sof_fmtDate(s) {
    if (!s) return '—';
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s));
    const d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(s);
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}

// Utilization bar: the bar and the percentage. Null = nothing to measure it against.
function sof_util(p) {
    if (p === null || p === undefined) return sof_dash;
    const w = Math.max(0, Math.min(100, p));
    return `<div class="sof-ub"><div class="sof-bar"><span style="width:${w}%"></span></div><em>${sof_esc(sof_pct(p))}</em></div>`;
}

/* ── Released vouchers that no fund source counts ───────────── */
// The API's `vouchers` (overview or one fund source — the same, over every fiscal year): when something released is counted nowhere, say how much and why, so a voucher
// that changed nothing explains itself. The vouchers with no link at all (no SARO, no RFA / LAA) are the ones the user can fix; the rest went through a Type of Fund
// that is not a fund source on this page.
function sof_renderGap() {
    const el = $sof('sof-gap');
    const v = (sof.view === 'detail' ? sof.detail?.vouchers : null) || sof.overview?.vouchers;
    if (!v || !(v.not_counted > 0.004)) { el.style.display = 'none'; el.innerHTML = ''; return; }
    const parts = [`<strong>${sof_esc(sof_peso(v.not_counted))}</strong> of the released disbursement vouchers is not counted in any fund source.`];
    if (v.unlinked_count > 0) {
        parts.push(`${v.unlinked_count.toLocaleString()} voucher${v.unlinked_count === 1 ? '' : 's'} (${sof_esc(sof_peso(v.unlinked))}) ${v.unlinked_count === 1 ? 'has' : 'have'} no SARO and no RFA / LAA — pick the <strong>SARO</strong> on the voucher, or connect its <strong>RFA / LAA</strong>.`);
    }
    if (v.other_fund > 0.004) {
        parts.push(`${sof_esc(sof_peso(v.other_fund))} went through a Type of Fund that is not a fund source here (e.g. Corporate Receipt).`);
    }
    el.innerHTML = parts.join(' ');
    el.style.display = '';
}

/* ── Summary cards ──────────────────────────────────────────── */
function sof_card(cls, label, value, sub) {
    return `<div class="nha-sc ${cls}"><div class="nha-sc-top"><div><div class="nha-sc-label">${sof_esc(label)}</div><div class="nha-sc-val" title="${sof_esc(value)}">${sof_esc(value)}</div></div></div><p class="nha-kpi-sub">${sub}</p></div>`;
}

function sof_renderOverviewCards() {
    const t = sof.overview?.totals;
    $sof('sof-cards').innerHTML = !t ? '' :
        sof_card('nha-sc-navy', 'Fund sources', String(t.sources), 'NG Subsidy · Trust Funds · Supplemental Funds') +
        sof_card('nha-sc-blue', 'Funds available', sof_peso(t.available), 'NCA released + receipts, all sources') +
        sof_card('nha-sc-green', 'Disbursed', sof_peso(t.disbursed),
            (t.utilization === null ? 'Nothing to measure against yet' : `<strong>${sof_esc(sof_pct(t.utilization))}</strong> utilized`) + ` · ${sof_esc(sof_peso(t.balance))} left`);
}

function sof_renderDetailCards() {
    const d = sof.detail;
    if (!d) { $sof('sof-cards').innerHTML = ''; return; }
    if (d.key === 'TRUST') {
        const rec = d.trust_total ? (d.receipts / d.trust_total * 100) : null;
        $sof('sof-cards').innerHTML =
            sof_card('nha-sc-navy', 'Trust funds', sof_peso(d.trust_total), `${d.trust_count.toLocaleString()} trust fund record${d.trust_count === 1 ? '' : 's'}`) +
            sof_card('nha-sc-green', 'Receipts', sof_peso(d.receipts), (rec === null ? '' : `<strong>${sof_esc(sof_pct(rec))}</strong> of the trust funds · `) + `${d.or_count.toLocaleString()} OR record${d.or_count === 1 ? '' : 's'}`) +
            sof_card('nha-sc-blue', 'Not yet received', sof_peso(d.trust_total - d.receipts), 'Trust funds less receipts');
        return;
    }
    const ncaPct = d.saro_released ? (d.nca_released / d.saro_released * 100) : null;
    $sof('sof-cards').innerHTML =
        sof_card('nha-sc-navy', 'SARO released', sof_peso(d.saro_released), `${d.saro_count.toLocaleString()} SARO${d.saro_count === 1 ? '' : 's'}`) +
        sof_card('nha-sc-blue', 'NCA released', sof_peso(d.nca_released), (ncaPct === null ? '' : `<strong>${sof_esc(sof_pct(ncaPct))}</strong> of the SARO released · `) + `${d.nca_count.toLocaleString()} NCA${d.nca_count === 1 ? '' : 's'}`) +
        sof_card('nha-sc-green', 'Disbursed', d.disbursed === null ? '—' : sof_peso(d.disbursed), d.utilization === null ? 'Released vouchers (SARO · RFA / LAA)' : `<strong>${sof_esc(sof_pct(d.utilization))}</strong> of the NCA released`) +
        sof_card('nha-sc-amber', 'Balance', sof_peso(d.balance), 'NCA released less disbursed');
}

/* ── Overview: the fund sources table ───────────────────────── */
const SOF_EYE = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const SOF_PRINT = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>';

function sof_visibleSources() {
    const all = sof.overview?.sources || [];
    return sof.filterKey ? all.filter(s => s.key === sof.filterKey) : all;
}

function sof_renderOverview() {
    const body = $sof('sof-tbody');
    if (!sof.overview) { body.innerHTML = `<tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>`; return; }
    const rows = sof_visibleSources();
    if (!rows.length) { body.innerHTML = `<tr><td colspan="6" class="sof-empty">No fund sources match the filters.</td></tr>`; $sof('sof-count').textContent = '0 records'; return; }

    body.innerHTML = rows.map(s => `
        <tr class="sof-row-click" onclick="sof_open('${sof_esc(s.key)}')">
            <td class="sof-name">${sof_esc(s.name)}</td>
            <td class="sof-num">${sof_amtCell(s.available)}</td>
            <td class="sof-num">${sof_amtCell(s.disbursed)}</td>
            <td class="sof-num">${sof_amtCell(s.balance)}</td>
            <td>${sof_util(s.utilization)}</td>
            <td class="no-print" onclick="event.stopPropagation()">
                <div class="sof-act">
                    <button type="button" class="sof-ib" title="Open ${sof_esc(s.name)}" aria-label="Open ${sof_esc(s.name)}" onclick="sof_open('${sof_esc(s.key)}')">${SOF_EYE}</button>
                    <button type="button" class="sof-ib" title="Utilization report" aria-label="Utilization report of ${sof_esc(s.name)}" onclick="sof_openReport('${sof_esc(s.key)}')">${SOF_PRINT}</button>
                </div>
            </td>
        </tr>`).join('');

    // the total row follows what is shown
    const avail = rows.reduce((a, s) => a + s.available, 0);
    const disb = rows.reduce((a, s) => a + (s.disbursed || 0), 0);
    const anyDisb = rows.some(s => s.disbursed !== null);
    body.innerHTML += `
        <tr class="sof-total">
            <td>TOTAL · ${rows.length} fund source${rows.length === 1 ? '' : 's'}</td>
            <td class="sof-num">${sof_amtCell(avail)}</td>
            <td class="sof-num">${sof_amtCell(anyDisb ? disb : null)}</td>
            <td class="sof-num">${sof_amtCell(avail - disb)}</td>
            <td>${sof_util(avail > 0 && anyDisb ? disb / avail * 100 : null)}</td>
            <td class="no-print"></td>
        </tr>`;
    $sof('sof-count').textContent = rows.length + (rows.length === 1 ? ' record' : ' records');
}

/* ── Detail: the split and the tree ─────────────────────────── */
function sof_renderSplit() {
    const d = sof.detail, tbl = $sof('sof-split');
    if (!d) { tbl.innerHTML = ''; return; }

    if (d.key === 'TRUST') {
        $sof('sof-split-title').textContent = 'Trust funds';
        $sof('sof-split-hint').textContent = 'Each trust fund with the ORs collected under it · Amounts in ₱';
        const rows = d.trust_funds || [];
        tbl.innerHTML = `
            <thead><tr><th>Trust fund</th><th>Description</th><th>Fiscal Year</th><th style="text-align:right;">Amount (₱)</th><th style="text-align:right;">Receipts (₱)</th><th style="text-align:right;">Not yet received (₱)</th><th style="text-align:right;">ORs</th></tr></thead>
            <tbody>${rows.length ? rows.map(t => `
                <tr>
                    <td class="sof-ref sof-name">${sof_esc(t.reference || '#' + t.id)}</td>
                    <td><span class="sof-desc" style="display:block;max-width:320px;font-size:12px;">${sof_esc(t.description || '') || sof_dash}</span></td>
                    <td>${t.fiscal_year ? 'FY ' + sof_esc(t.fiscal_year) : sof_dash}</td>
                    <td class="sof-num">${sof_amtCell(t.amount)}</td>
                    <td class="sof-num">${sof_amtCell(t.receipts)}</td>
                    <td class="sof-num">${sof_amtCell(t.amount - t.receipts)}</td>
                    <td class="sof-num">${t.or_count}</td>
                </tr>`).join('') + `
                <tr class="sof-total"><td colspan="3">TOTAL</td><td class="sof-num">${sof_amtCell(d.trust_total)}</td><td class="sof-num">${sof_amtCell(d.receipts)}</td><td class="sof-num">${sof_amtCell(d.trust_total - d.receipts)}</td><td class="sof-num">${d.or_count}</td></tr>`
                : `<tr><td colspan="7" class="sof-empty">No trust funds in ${sof_esc(sof_yearLabel())}.</td></tr>`}</tbody>`;
        $sof('sof-split-count').textContent = rows.length + (rows.length === 1 ? ' record' : ' records');
        $sof('sof-split-note').textContent = d.or_count > (rows.reduce((a, t) => a + t.or_count, 0)) ? 'Includes ORs that belong to no trust fund' : '';
        return;
    }

    $sof('sof-split-title').textContent = d.name + ' by program';
    $sof('sof-split-hint').textContent = 'The SARO’s sub-program decides its program; an NCA follows its SARO · Amounts in ₱';
    const rows = d.programs || [];
    tbl.innerHTML = `
        <thead><tr><th>Program</th><th style="text-align:right;">SARO released (₱)</th><th style="text-align:right;">NCA released (₱)</th><th style="text-align:right;">Disbursed (₱)</th><th style="text-align:right;">Undisbursed NCA (₱)</th><th>Utilization</th></tr></thead>
        <tbody>${rows.length ? rows.map(p => `
            <tr>
                <td class="sof-name">${p.program_code ? sof_esc(p.program_name) : `<span class="sof-dash">${sof_esc(p.program_name)}</span>`}</td>
                <td class="sof-num">${sof_amtCell(p.saro_released)}</td>
                <td class="sof-num">${sof_amtCell(p.nca_released)}</td>
                <td class="sof-num">${sof_amtCell(p.disbursed)}</td>
                <td class="sof-num">${sof_amtCell(p.undisbursed)}</td>
                <td>${sof_util(p.utilization)}</td>
            </tr>`).join('') + `
            <tr class="sof-total"><td>TOTAL</td><td class="sof-num">${sof_amtCell(d.saro_released)}</td><td class="sof-num">${sof_amtCell(d.nca_released)}</td><td class="sof-num">${sof_amtCell(d.disbursed)}</td><td class="sof-num">${sof_amtCell(d.disbursed === null ? null : d.nca_released - d.disbursed)}</td><td>${sof_util(d.utilization)}</td></tr>`
            : `<tr><td colspan="6" class="sof-empty">No ${sof_esc(d.name)} records in ${sof_esc(sof_yearLabel())}.</td></tr>`}</tbody>`;
    $sof('sof-split-count').textContent = rows.length + (rows.length === 1 ? ' record' : ' records');
    $sof('sof-split-note').textContent = 'Disbursed = released vouchers, through the SARO or the RFA / LAA (by the budget line\'s program) · Undisbursed NCA = NCA released less disbursed';
}

// ── the SARO → NCA tree (the same rows as saro-registry-page.php, read-only) ──
const SOF_CARET = '<span class="sof-caret__CLS__" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>';
const sof_kids = r => (Array.isArray(r.children) ? r.children : []);
const sof_typeCode = r => String(r.tracking_type?.fund_tracking_code || '').trim().toUpperCase();
const sof_sum = list => list.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
const sof_isOpen = r => sof_kids(r).length > 0 && !sof.tree.collapsed.has(Number(r.id));

function sof_dateCell(r) {
    const has = r.date && sof_fmtDate(r.date) !== '—';
    const isNca = sof_typeCode(r) === 'NCA';
    return `<td style="white-space:nowrap;">${sof_esc(sof_fmtDate(r.date))}`
        + (has ? `<div style="margin-top:4px;"><span class="sof-tag ${isNca ? 'sof-tag-child' : 'sof-tag-parent'}">${isNca ? 'NCA' : 'SARO'} date</span></div>` : '') + '</td>';
}

const sof_nature = r => (r.nature_of_fund && sof_typeCode(r) !== 'NCA' ? `<span class="nha-bdg nha-bdg-navy">${sof_esc(r.nature_of_fund.name)}</span>` : sof_dash);
const sof_year = r => (r.fiscal_year ? `<span style="font-size:11px;">FY ${sof_esc(r.fiscal_year)}</span>` : sof_dash);
const sof_desc = r => (r.description && String(r.description).trim() ? `<span class="sof-desc" title="${sof_esc(r.description)}">${sof_esc(r.description)}</span>` : sof_dash);

function sof_parentRow(r) {
    const kids = sof_kids(r), has = kids.length > 0, open = sof_isOpen(r);
    const type = sof_typeCode(r) || 'SARO';
    const isSaro = type === 'SARO';
    const bal = (parseFloat(r.amount) || 0) - sof_sum(kids);
    const tags = isSaro
        ? `<span class="sof-tag sof-tag-parent">${sof_esc(type)}</span>` + (has ? `<span class="sof-tag sof-tag-count">${kids.length} NCA</span>` : '<span class="sof-tag sof-tag-none">No NCA yet</span>')
        : `<span class="sof-tag sof-tag-child" title="This NCA has no parent SARO">NCA · no SARO</span>`;
    return `
    <tr class="sof-parent${has ? ' has-kids' : ''}${open ? ' expanded' : ''}"${has ? ` aria-expanded="${open}" onclick="sof_toggle(${Number(r.id)})"` : ''}>
        <td><div class="sof-ref-line">${SOF_CARET.replace('__CLS__', has ? '' : ' none')}<span class="sof-ref">${sof_esc(r.saro_id || '—')}</span></div><div class="sof-tags">${tags}</div></td>
        <td>${sof_desc(r)}</td>
        <td>${sof_nature(r)}</td>
        <td>${sof_year(r)}</td>
        ${sof_dateCell(r)}
        <td class="sof-num">${isSaro ? sof_amtCell(parseFloat(r.amount) || 0) : sof_dash}</td>
        <td class="sof-num">${isSaro ? (has ? sof_amtCell(sof_sum(kids)) : sof_dash) : sof_amtCell(parseFloat(r.amount) || 0)}</td>
        <td class="sof-num">${isSaro ? `<span class="sof-amt"${bal < 0 ? ' style="color:var(--afims-danger, #DC3545);" title="The NCAs released exceed the SARO amount"' : ''}>${sof_esc(sof_peso(bal))}</span>` : sof_dash}</td>
    </tr>`;
}

function sof_childRow(c, isLast) {
    return `
    <tr class="sof-child${isLast ? ' last' : ''}">
        <td><div class="sof-child-ref"><div class="sof-ref-line"><span class="sof-ref">${sof_esc(c.saro_id || '—')}</span></div><div class="sof-tags"><span class="sof-tag sof-tag-child">${sof_esc(sof_typeCode(c) || 'NCA')}</span></div></div></td>
        <td>${sof_desc(c)}</td>
        <td>${sof_dash}</td>
        <td>${sof_year(c)}</td>
        ${sof_dateCell(c)}
        <td class="sof-num">${sof_dash}</td>
        <td class="sof-num">${sof_amtCell(parseFloat(c.amount) || 0)}</td>
        <td class="sof-num">${sof_dash}</td>
    </tr>`;
}

function sof_renderTree() {
    const t = sof.tree, body = $sof('sof-tree-body');
    if (t.error) body.innerHTML = `<tr><td colspan="8" class="sof-empty">Could not load records: ${sof_esc(t.error)}</td></tr>`;
    else if (!t.loaded) body.innerHTML = `<tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>`;
    else if (!t.rows.length) body.innerHTML = `<tr><td colspan="8" class="sof-empty">No SARO / NCA records in ${sof_esc(sof_yearLabel())}.</td></tr>`;
    else body.innerHTML = t.rows.map(r => sof_parentRow(r) + (sof_isOpen(r) ? sof_kids(r).map((c, i, all) => sof_childRow(c, i === all.length - 1)).join('') : '')).join('');
    sof_renderPager();
}

function sof_toggle(id) {
    id = Number(id);
    const c = sof.tree.collapsed;
    c.has(id) ? c.delete(id) : c.add(id);
    sof_renderTree();
}

function sof_renderPager() {
    const t = sof.tree;
    const total = t.loaded && !t.error ? t.total : 0;
    const from = total ? (t.page - 1) * t.limit + 1 : 0;
    const to = total ? Math.min(from + t.rows.length - 1, total) : 0;
    $sof('sof-from').textContent = from;
    $sof('sof-to').textContent = to;
    $sof('sof-total').textContent = total.toLocaleString();

    const box = $sof('sof-pagination');
    const last = Math.max(1, Math.ceil(total / t.limit));
    if (last <= 1) { box.innerHTML = ''; return; }
    const cur = t.page;
    const nums = [...new Set([1, last, cur - 1, cur, cur + 1])].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);
    let html = `<button type="button" class="nha-pag-btn${cur <= 1 ? ' disabled' : ''}" onclick="sof_goTo(${cur - 1})">‹</button>`;
    nums.forEach((p, i) => {
        if (i > 0 && p - nums[i - 1] > 1) html += '<span class="nha-pag-ellipsis">…</span>';
        html += `<button type="button" class="nha-pag-btn${p === cur ? ' active' : ''}" onclick="sof_goTo(${p})">${p}</button>`;
    });
    html += `<button type="button" class="nha-pag-btn${cur >= last ? ' disabled' : ''}" onclick="sof_goTo(${cur + 1})">›</button>`;
    box.innerHTML = html;
}

function sof_goTo(page) {
    const last = Math.max(1, Math.ceil(sof.tree.total / sof.tree.limit));
    if (page < 1 || page > last || page === sof.tree.page) return;
    sof.tree.page = page;
    sof_loadTree();
}

// The tree request: SARO roots with their NCAs. NG Subsidy leaves the Supplemental records out; Supplemental Funds asks for only those.
function sof_treeParams(key) {
    const p = new URLSearchParams();
    p.append('type', 'SARO');
    p.append('type', 'NCA');                         // `type` also decides which CHILDREN come back, so both are needed
    p.set('roots_only', '1');
    p.set('root_type', 'SARO');
    if (key === 'SUPP') p.set('nature_of_fund', SOF_NATURE_SUPP);
    else p.set('exclude_nature_of_fund', SOF_NATURE_SUPP);
    if (sof.year) p.set('fiscal_year', sof.year);
    p.set('page', sof.tree.page);
    p.set('limit', sof.tree.limit);
    return p;
}

async function sof_loadTree() {
    const t = sof.tree, token = ++t.req;
    try {
        const json = await sof_api(SOF_U.tree + '?' + sof_treeParams(sof.key));
        if (token !== t.req) return;
        t.rows = json.data || [];
        t.total = json.pagination?.total ?? t.rows.length;
        t.loaded = true; t.error = null;
    } catch (e) {
        if (token !== t.req) return;
        t.error = e.message;
        sof_showError(e.message);
    }
    sof_renderTree();
}

/* ── Views ──────────────────────────────────────────────────── */
function sof_renderChips() {
    const keys = (sof.overview?.sources || []).map(s => s.key);
    $sof('sof-chips').innerHTML = (keys.length ? keys : Object.keys(SOF_SOURCES)).map(k =>
        `<button type="button" class="sof-chip${k === sof.key ? ' on' : ''}" onclick="sof_open('${k}')">${sof_esc(SOF_SOURCES[k]?.name || k)}</button>`).join('');
}

// show / hide by inline style — the `hidden` attribute loses to any class that sets `display` (.nha-filter-group, .nha-table-wrap…)
function sof_show(id, on) { $sof(id).style.display = on ? '' : 'none'; }

function sof_showView() {
    const detail = sof.view === 'detail';
    sof_show('sof-overview', !detail);
    sof_show('sof-detail', detail);
    sof_show('sof-detail-nav', detail);
    sof_show('sof-source-group', !detail);
    const src = SOF_SOURCES[sof.key];
    $sof('sof-title').textContent = detail ? (src?.name || sof.key) : 'Status of Funds';
    $sof('sof-subtitle').textContent = detail ? (src?.sub || '') : 'Where each fund source stands — what is available and what has been disbursed';
    $sof('sof-crumb-name').textContent = detail ? (src?.name || sof.key) : '';
    sof_show('sof-tree-wrap', detail && !!src?.tree);
    $sof('sof-tree-title').textContent = sof.key === 'SUPP' ? 'Supplemental SARO and NCA registry' : 'SARO and NCA registry';
    if (detail) sof_renderChips();
}

async function sof_loadOverview() {
    const token = ++sof.req;
    try {
        const json = await sof_api(SOF_U.overview + (sof.year ? '?fiscal_year=' + encodeURIComponent(sof.year) : ''));
        if (token !== sof.req) return;
        sof.overview = json.data;
        sof_fillYears(json.data.fiscal_years || []);
        sof_fillSources(json.data.sources || []);
        sof_hideError(); sof_setUpdated();
    } catch (e) {
        if (token !== sof.req) return;
        sof.overview = { sources: [], totals: null };
        sof_showError(e.message);
    }
    sof_renderOverviewCards();
    sof_renderOverview();
    sof_renderGap();
}

async function sof_loadDetail() {
    const token = ++sof.req;
    sof.detail = null;
    sof.tree.page = 1; sof.tree.loaded = false; sof.tree.error = null; sof.tree.collapsed.clear();
    sof_renderDetailCards(); sof_renderSplit();
    if (SOF_SOURCES[sof.key]?.tree) { sof_renderTree(); sof_loadTree(); }
    try {
        const json = await sof_api(SOF_U.source(sof.key) + (sof.year ? '?fiscal_year=' + encodeURIComponent(sof.year) : ''));
        if (token !== sof.req) return;
        sof.detail = json.data;
        sof_hideError(); sof_setUpdated();
    } catch (e) {
        if (token !== sof.req) return;
        sof_showError(e.message);
    }
    sof_renderDetailCards();
    sof_renderSplit();
    sof_renderGap();
}

// Open a fund source (detail view), or go back to the overview (key = null)
function sof_open(key) {
    if (!key) {
        sof.view = 'overview';
        history.replaceState(null, '', location.pathname + location.search);
        sof_showView();
        sof_loadOverview();
        return;
    }
    sof.view = 'detail';
    sof.key = String(key).toUpperCase();
    history.replaceState(null, '', '#' + sof.key);
    sof_showView();
    sof_loadDetail();
    if (!sof.overview) sof_loadOverview();
}

function sof_reload() {
    return sof.view === 'detail' ? sof_loadDetail() : sof_loadOverview();
}

/* ── Filters ────────────────────────────────────────────────── */
function sof_fillYears(years) {
    sof.years = years;
    const sel = $sof('sof-year');
    sel.innerHTML = '<option value="">All fiscal years</option>' + years.map(y => `<option value="${y}">FY ${y}</option>`).join('');
    sel.value = sof.year;
}

function sof_fillSources(sources) {
    const sel = $sof('sof-source');
    sel.innerHTML = '<option value="">All Fund Sources</option>' + sources.map(s => `<option value="${sof_esc(s.key)}">${sof_esc(s.name)}</option>`).join('');
    sel.value = sof.filterKey;
}

function sof_resetFilters() {
    sof.year = sof.years[0] ? String(sof.years[0]) : '';
    sof.filterKey = '';
    $sof('sof-year').value = sof.year;
    $sof('sof-source').value = '';
    sof_reload();
}

/* ── Utilization Report ─────────────────────────────────────── */
// Builds the sheet from the figures already on screen: { sub, head: [...], rows: [[...]], total: [...], align: ['l','r',...], b: [...] }
function sof_buildReport(d) {
    const fy = sof_yearLabel();
    const num = v => v === null || v === undefined ? '—' : sof_n(v);
    if (!d) { // every fund source
        const rows = sof_visibleSources();
        const avail = rows.reduce((a, s) => a + s.available, 0), disb = rows.reduce((a, s) => a + (s.disbursed || 0), 0);
        const anyDisb = rows.some(s => s.disbursed !== null);
        return {
            title: 'Utilization Report', sub: `All fund sources · ${fy}`, scope: 'All fund sources',
            sectionA: 'A. Utilization per fund source',
            head: ['Fund source', 'Funds available', 'Disbursed', 'Balance', 'Utilization'],
            align: ['l', 'r', 'r', 'r', 'r'],
            rows: rows.map(s => [s.name, num(s.available), num(s.disbursed), num(s.balance), sof_pct(s.utilization) ?? '—']),
            total: ['TOTAL', num(avail), num(anyDisb ? disb : null), num(avail - disb), sof_pct(avail > 0 && anyDisb ? disb / avail * 100 : null) ?? '—'],
            cards: rows.map(s => ({ label: s.name, value: s.key === 'TRUST' ? `${s.trust_count} trust fund${s.trust_count === 1 ? '' : 's'} · ${num(s.trust_total)}` : `${s.saro_count} SARO${s.saro_count === 1 ? '' : 's'} · ${num(s.saro_released)}`,
                lines: s.key === 'TRUST' ? [`Receipts ${num(s.receipts)} (${s.or_count} OR)`] : [`NCA released ${num(s.nca_released)} (${s.nca_count} NCA)`, `Unreleased ${num(s.unreleased)}`] })),
            sectionB: 'B. Records per fund source',
            note: 'Funds available = NCA released (NG Subsidy, Supplemental Funds) or receipts (Trust Funds). Disbursed = what the released vouchers have paid, through the SAROs or through the RFAs / LAAs (by the Type of Fund of the budget line); a Trust Fund shows none until an RFA / LAA of that type of fund has a released voucher.',
        };
    }
    if (d.key === 'TRUST') {
        const rows = d.trust_funds || [];
        return {
            title: 'Utilization Report', sub: `${d.name} · ${fy}`, scope: d.name,
            sectionA: 'A. Receipts per trust fund',
            head: ['Trust fund', 'Fiscal year', 'Amount', 'Receipts', 'Not yet received', 'ORs'],
            align: ['l', 'l', 'r', 'r', 'r', 'r'],
            rows: rows.map(t => [t.reference || '#' + t.id, t.fiscal_year ? 'FY ' + t.fiscal_year : '—', num(t.amount), num(t.receipts), num(t.amount - t.receipts), String(t.or_count)]),
            total: ['TOTAL', '', num(d.trust_total), num(d.receipts), num(d.trust_total - d.receipts), String(d.or_count)],
            cards: [{ label: 'Trust funds', value: `${d.trust_count} · ${num(d.trust_total)}`, lines: [] }, { label: 'Official receipts', value: `${d.or_count} · ${num(d.receipts)}`, lines: [] }],
            sectionB: 'B. Trust fund and OR summary',
            note: 'A Trust Fund has no SARO, so its disbursed figure comes only from RFAs / LAAs of the Trust Fund type of fund (see the overview); it is not split per trust fund.',
        };
    }
    const rows = d.programs || [];
    return {
        title: 'Utilization Report', sub: `${d.name} · ${fy}`, scope: d.name,
        sectionA: 'A. Utilization per program',
        head: ['Program', 'SARO released', 'NCA released', 'Disbursed', 'Undisbursed NCA', 'Utilization'],
        align: ['l', 'r', 'r', 'r', 'r', 'r'],
        rows: rows.map(p => [p.program_name, num(p.saro_released), num(p.nca_released), num(p.disbursed), num(p.undisbursed), sof_pct(p.utilization) ?? '—']),
        total: ['TOTAL', num(d.saro_released), num(d.nca_released), num(d.disbursed), num(d.disbursed === null ? null : d.nca_released - d.disbursed), sof_pct(d.utilization) ?? '—'],
        cards: [
            { label: 'SARO registry', value: `${d.saro_count} SARO${d.saro_count === 1 ? '' : 's'} · ${num(d.saro_released)}`, lines: [`Unreleased ${num(d.unreleased)}`] },
            { label: 'NCA registry', value: `${d.nca_count} NCA${d.nca_count === 1 ? '' : 's'} · ${num(d.nca_released)}`, lines: [`Disbursed ${num(d.disbursed)}`] },
        ],
        sectionB: 'B. SARO and NCA summary',
        note: 'Disbursed = what the released vouchers have paid — through the SAROs, or through the RFAs / LAAs of the budget lines of this fund source. Utilization = disbursed / NCA released.',
    };
}

function sof_reportHtml(r) {
    const th = r.head.map((h, i) => `<th class="${r.align[i] === 'r' ? 'r' : ''}">${sof_esc(h)}</th>`).join('');
    const tr = cells => cells.map((c, i) => `<td class="${r.align[i] === 'r' ? 'r' : ''}">${sof_esc(c)}</td>`).join('');
    return `
        <div class="sof-meta">
            <div><span>Fund source</span><b>${sof_esc(r.scope)}</b></div>
            <div><span>Fiscal year</span><b>${sof_esc(sof.year || 'All')}</b></div>
            <div><span>Generated</span><b>${sof_esc(new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }))}</b></div>
            <div><span>Amounts in</span><b>₱ (PHP)</b></div>
        </div>
        <div class="sof-st">${sof_esc(r.sectionA)}</div>
        <table class="sof-rtbl"><thead><tr>${th}</tr></thead><tbody>
            ${r.rows.length ? r.rows.map(c => `<tr>${tr(c)}</tr>`).join('') + `<tr class="sof-total">${tr(r.total)}</tr>` : `<tr><td colspan="${r.head.length}" style="text-align:center;color:#A9AAB8;">No records.</td></tr>`}
        </tbody></table>
        <div class="sof-st">${sof_esc(r.sectionB)}</div>
        <div class="sof-two">${r.cards.map(c => `<div><span>${sof_esc(c.label)}</span><b>${sof_esc(c.value)}</b><small>${c.lines.map(sof_esc).join('<br>')}</small></div>`).join('')}</div>
        <div class="sof-sig"><div><i></i>Prepared by</div><div><i></i>Reviewed by</div><div><i></i>Noted by</div></div>
        <div class="sof-note">${sof_esc(r.note)}</div>`;
}

// key: a fund source key, or null/undefined for every fund source (the Overview's button). From the overview's row, the source is
// loaded first (its programs / trust funds are not in the overview).
async function sof_openReport(key) {
    try {
        let d = null;
        if (key) {
            d = sof.detail && sof.detail.key === key && sof.view === 'detail'
                ? sof.detail
                : (await sof_api(SOF_U.source(key) + (sof.year ? '?fiscal_year=' + encodeURIComponent(sof.year) : ''))).data;
        } else if (!sof.overview) {
            await sof_loadOverview();
        }
        sof.report = sof_buildReport(d);
        $sof('sof-rp-title').textContent = 'Utilization Report';
        $sof('sof-rp-sub').textContent = sof.report.sub;
        $sof('sof-rp-content').innerHTML = sof_reportHtml(sof.report);
        $sof('sof-rp-ov').classList.add('open');
    } catch (e) {
        sof_toast('Could not build the report: ' + e.message, 'error');
    }
}

function sof_closeReport() { $sof('sof-rp-ov').classList.remove('open'); }

// Print only the sheet: it goes into a hidden frame of its own, so the page around it (sidebar, filters) never prints
function sof_printReport() {
    const paper = $sof('sof-paper');
    const css = `body{margin:0;padding:24px;font-family:'IBM Plex Sans',Arial,sans-serif;color:#1A1B21}
        .sof-lh{display:flex;align-items:center;gap:16px;padding-bottom:14px;border-bottom:2px solid #000E3E}.sof-lh img{height:62px}
        .sof-org{font-size:13px;font-weight:600;color:#62657E;letter-spacing:.04em;text-transform:uppercase}.sof-rt{font-size:22px;font-weight:800;color:#000E3E;margin-top:2px}.sof-rs{font-size:13px;color:#62657E;margin-top:2px}
        .sof-meta{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin:16px 0 4px}.sof-meta span{display:block;font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#62657E}.sof-meta b{font-size:13.5px;color:#000E3E}
        .sof-st{margin:18px 0 8px;font-size:12px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#000E3E}
        .sof-rtbl{width:100%;border-collapse:collapse;border:1px solid #E4ECF2}.sof-rtbl th{background:#000E3E;color:#fff;text-align:left;font-size:9.5px;letter-spacing:.06em;text-transform:uppercase;padding:9px 10px;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .sof-rtbl td{padding:9px 10px;font-size:12px;border-bottom:1px solid #E4ECF2}.sof-rtbl th.r,.sof-rtbl td.r{text-align:right}.sof-rtbl td.r{font-family:'IBM Plex Mono',monospace;white-space:nowrap}
        .sof-rtbl tr.sof-total td{background:#F4F7FA;font-weight:700;color:#000E3E;-webkit-print-color-adjust:exact;print-color-adjust:exact}
        .sof-two{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.sof-two>div{border:1px solid #E4ECF2;border-radius:8px;padding:10px 12px}.sof-two span{display:block;font-size:10px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:#62657E}.sof-two b{display:block;font-size:13px;color:#000E3E;margin:3px 0 2px}.sof-two small{display:block;font-size:12px;line-height:1.5;color:#62657E}
        .sof-sig{display:grid;grid-template-columns:repeat(3,1fr);gap:30px;margin-top:40px;text-align:center;font-size:12px;font-weight:600;color:#000E3E}.sof-sig i{display:block;border-top:1px solid #9AA0B4;margin-bottom:6px}
        .sof-note{margin-top:14px;font-size:11px;color:#62657E;line-height:1.5}@page{margin:12mm}`;
    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    frame.srcdoc = `<!doctype html><html><head><meta charset="utf-8"><title>Utilization Report</title><style>${css}</style></head><body>${paper.innerHTML}</body></html>`;
    frame.onload = () => {
        const w = frame.contentWindow;
        const imgs = [...w.document.images];
        const go = () => { w.focus(); w.print(); setTimeout(() => frame.remove(), 1500); };
        Promise.all(imgs.map(i => i.complete ? null : new Promise(res => { i.onload = i.onerror = res; }))).then(go);
    };
    document.body.appendChild(frame);
}

function sof_csvReport() {
    const r = sof.report;
    if (!r) return;
    const q = v => '"' + String(v ?? '').replace(/"/g, '""') + '"';
    const lines = [
        [r.title], [r.sub], ['Amounts in PHP'], [],
        r.head, ...r.rows, ...(r.rows.length ? [r.total] : []),
    ].map(row => row.map(q).join(','));
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'utilization-report-' + (r.scope || 'all').toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-' + (sof.year || 'all-years') + '.csv';
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
}

/* ── Events ─────────────────────────────────────────────────── */
function sof_bind() {
    $sof('sof-refresh').addEventListener('click', async () => {
        const btn = $sof('sof-refresh');
        btn.classList.add('loading');
        try { await sof_reload(); } finally { btn.classList.remove('loading'); }
    });
    $sof('sof-report-open').addEventListener('click', () => sof_openReport(sof.view === 'detail' ? sof.key : null));
    $sof('sof-back').addEventListener('click', () => sof_open(null));

    $sof('sof-year').addEventListener('change', e => { sof.year = e.target.value; sof_reload(); });
    $sof('sof-source').addEventListener('change', e => { sof.filterKey = e.target.value; sof_renderOverview(); });
    $sof('sof-reset').addEventListener('click', sof_resetFilters);

    $sof('sof-rp-close').addEventListener('click', sof_closeReport);
    $sof('sof-rp-print').addEventListener('click', sof_printReport);
    $sof('sof-rp-csv').addEventListener('click', sof_csvReport);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') sof_closeReport(); });
}

/* ── Bootstrap ──────────────────────────────────────────────── */
async function sof_boot() {
    sof_bind();
    const hash = (location.hash || '').replace('#', '').toUpperCase();
    // the newest fiscal year first: the overview is asked once with no year just to learn which years exist
    await sof_loadOverview();
    if (sof.years.length && !sof.year) {
        sof.year = String(sof.years[0]);
        $sof('sof-year').value = sof.year;
        await sof_loadOverview();
    }
    if (SOF_SOURCES[hash]) sof_open(hash);
    else sof_showView();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sof_boot);
} else {
    sof_boot();
}
</script>
