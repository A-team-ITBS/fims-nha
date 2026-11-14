<?php

namespace PHPMaker2024\FIMS;

/**
 * fund-sourcing-dashboard-page.php — "Fund Sourcing Dashboard"
 * (SRS 1.2.1.1 Fund Sourcing Division + 1.2.1.3 Fund Sources Determination, Financial Management Department).
 *
 * Ported from the FMD app's FP-FS/fund-sourcing-dashboard.php to FIMS, in the same shape as saro-registry-page.php: the shared
 * nha-fims.css look (.nha-root), the shared nhaSearchableSelect for the Program filter, no jQuery / fmd.css.
 *
 * UI ONLY FOR NOW — the page renders from sample data. The real API is already wired behind one switch:
 *
 *     const FSD_USE_MOCK = true;     // → false  = every call below goes to the FIMS API instead
 *
 * Every read goes through fsd_get(key, params). The mock answers with the SAME response shapes as the real endpoints
 * (and the same query-string names), so flipping the switch needs no change in any render code:
 *
 *   dashboard      GET /fund_tracking/dashboard   fiscal_year, program_code
 *                  → data.government_funds {saro_allocation, saro_unreleased, saro_record_count, nca_received, nca_unreleased,
 *                                           nca_record_count, available_funds}
 *                    data.special_funds {trust_funds, trust_funds_record_count, supplemental_funds, supplemental_funds_record_count}
 *   list           GET /fund_tracking/list        type (SARO | NCA | TRUST_FUND), nature_of_fund (SUPPLEMENTAL), fiscal_year,
 *                                                 program_code, agency_or_program_search, page, limit
 *                  → data[] (fund_tracking rows), pagination {total, per_page, current_page, last_page}
 *   fiscalYears    GET /fiscal_year/all           → data[].fiscal_yr
 *   programs       GET /program/all               → data[].program_code / program_name
 *
 * Not in FIMS yet (live mode shows "not available yet" for them instead of failing the page):
 *   – Fund Transfer / Borrowing of Funds (SRS 1.2.1.1.1): the dashboard payload has no `cross_access_funds`
 *     (treasury.fund_transfers does not exist here) and there is no GET /fund_transfer/list.
 *   – `pagination.total_amount` on /fund_tracking/list (the table footers' "Total Amount"). When the API does not send it,
 *     the footer falls back to the matching dashboard figure (fallbackTotal) and otherwise shows "—".
 */
@session_start();
$_fsd_api = rtrim((string) Config('API.FIMS_URL'), '/');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<script src="/_shared/js/searchable-select.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/js/searchable-select.js') ?>"></script>

<style>
    /* ── Section headings: the SRS reference sits next to the title ── */
    #fsd-root .nha-sec-hd { margin-top: 6px; }
    #fsd-root .fsd-ref { font-size: 10px; font-weight: 600; letter-spacing: .04em; text-transform: none; color: var(--muted); background: var(--surf); border: 1px solid var(--bdr); border-radius: 20px; padding: 1px 8px; }
    #fsd-root .fsd-section { margin-bottom: 22px; }

    /* ── Sample-data notice (only while FSD_USE_MOCK is on) ── */
    #fsd-root .fsd-note { display: flex; align-items: center; gap: 10px; background: var(--amber-bg); border: 1px solid color-mix(in srgb, var(--amber) 55%, transparent); border-radius: 8px; padding: 9px 14px; font-size: 12px; color: var(--amber-txt); margin-bottom: 14px; }
    #fsd-root .fsd-note svg { width: 15px; height: 15px; flex-shrink: 0; stroke: currentColor; fill: none; }
    #fsd-root .fsd-note code { font-family: var(--mono); font-size: 11px; background: color-mix(in srgb, var(--amber) 22%, transparent); padding: 1px 5px; border-radius: 4px; }

    /* ── Summary cards: grow with the amount instead of clipping it ── */
    #fsd-root .nha-summary { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 16px; }
    #fsd-root .nha-summary > .nha-sc { flex: 1 1 230px; min-width: 210px; }
    #fsd-root .nha-summary > .fsd-wide { flex: 2 1 380px; }
    #fsd-root .nha-sc-val { white-space: nowrap; }
    #fsd-root .nha-sc-ico svg { fill: none; stroke: currentColor; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
    #fsd-root .nha-ico-navy   { color: var(--afims-heading, #000E3E); }
    #fsd-root .nha-ico-blue   { color: var(--afims-primary, #0035A9); }
    #fsd-root .nha-ico-teal   { color: var(--green-txt); }
    #fsd-root .nha-ico-purple { color: var(--afims-primary, #0035A9); }
    #fsd-root .nha-ico-amber  { color: var(--amber-txt); }
    #fsd-root .fsd-prog { height: 5px; border-radius: 3px; background: var(--surf); overflow: hidden; margin-top: 12px; }
    #fsd-root .fsd-prog > span { display: block; height: 100%; width: 0; border-radius: 3px; background: var(--afims-heading-fill, #000E3E); transition: width .7s ease; }
    #fsd-root .fsd-prog-lbl { font-size: 10px; color: var(--muted); margin: 4px 0 0; }

    /* Payable / Receivable: one card, two halves with a divider between them (stacked on phones) */
    #fsd-root .fsd-pr { flex: 1 1 auto; min-width: 0; display: grid; grid-template-columns: 1fr 1fr; }
    #fsd-root .fsd-pr-col { min-width: 0; }
    #fsd-root .fsd-pr-col + .fsd-pr-col { border-left: 1px solid var(--bdr); padding-left: 32px; }
    #fsd-root .fsd-overdue { display: inline-flex; align-items: center; font-size: 10px; font-weight: 700; background: var(--red-bg); color: var(--red); padding: 2px 7px; border-radius: 4px; margin-left: 6px; }
    @media (max-width: 575px) {
        #fsd-root .fsd-pr { grid-template-columns: 1fr; row-gap: 14px; }
        #fsd-root .fsd-pr-col + .fsd-pr-col { border-left: 0; padding-left: 0; border-top: 1px solid var(--bdr); padding-top: 14px; }
    }

    /* ── Loading skeleton ── */
    #fsd-root .fsd-skel { background: linear-gradient(90deg, var(--surf) 25%, var(--bdr) 50%, var(--surf) 75%); background-size: 200% 100%; animation: fsd-shimmer 1.3s infinite; border-radius: 5px; }
    @keyframes fsd-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }

    /* ── Filters ── */
    #fsd-root .fsd-fy-group { flex: 0 1 170px; }
    #fsd-root .fsd-program-group { flex: 1 1 220px; min-width: 200px; }
    #fsd-root .fsd-program-group .nha-filter-input { padding-right: 10px; }

    /* ── Charts ── */
    #fsd-root .fsd-ch-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 8px; }
    #fsd-root .fsd-ch-grid > * { min-width: 0; }
    #fsd-root .fsd-ch-card { background: var(--card); border: 1px solid var(--bdr); border-radius: var(--r); padding: 16px 18px; box-shadow: var(--sh-sm); }
    #fsd-root .fsd-ch-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-bottom: 12px; }
    #fsd-root .fsd-ch-title { margin: 0; font-size: 13px; font-weight: 700; color: var(--afims-heading, #000E3E); }
    #fsd-root .fsd-ch-sub { margin: 2px 0 0; font-size: 11px; color: var(--sec); }
    #fsd-root .fsd-ch-pill { font-size: 10px; font-weight: 600; color: var(--sec); background: var(--surf); border: 1px solid var(--bdr); border-radius: 20px; padding: 2px 9px; white-space: nowrap; }
    #fsd-root .fsd-ch-wrap { position: relative; }
    #fsd-root .fsd-ch-off { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; font-size: 12px; color: var(--muted); text-align: center; }
    #fsd-root .fsd-legend { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 16px; margin-top: 10px; font-size: 11.5px; color: var(--sec); }
    #fsd-root .fsd-legend-item { display: inline-flex; align-items: center; gap: 6px; }
    #fsd-root .fsd-legend-dot { width: 9px; height: 9px; border-radius: 50%; flex-shrink: 0; }
    @media (max-width: 900px) { #fsd-root .fsd-ch-grid { grid-template-columns: 1fr; } }

    /* ── Detailed Breakdown ── */
    #fsd-root .nha-tabs { overflow-x: auto; }
    #fsd-root .fsd-sub-filter { display: flex; flex-direction: column; gap: 5px; max-width: 320px; }
    #fsd-root .fsd-sub-filter-wrap { background: var(--card); border: 1px solid var(--bdr); border-radius: var(--r); padding: 14px 18px; margin-bottom: 14px; box-shadow: var(--sh-sm); }
    #fsd-root .fsd-total { font-size: 12px; font-weight: 700; color: var(--afims-heading, #000E3E); white-space: nowrap; }
    #fsd-root .nha-pag-wrap { gap: 10px; flex-wrap: wrap; }
    #fsd-root .fsd-tbl .num { font-family: var(--mono); text-align: right; white-space: nowrap; }
    #fsd-root .fsd-tbl th.num { font-family: inherit; }
    #fsd-root .fsd-tbl .fsd-state { text-align: center; color: var(--muted); padding: 30px; font-size: 13px; }
    #fsd-root .fsd-tbl .fsd-state.err { color: var(--red); }

    /* Overview: each fund-source row expands into its own records */
    #fsd-root .fsd-ov-parent { cursor: pointer; }
    #fsd-root .fsd-ov-caret { display: inline-block; width: 10px; height: 10px; margin-right: 8px; vertical-align: middle; color: var(--sec); transition: transform .15s; fill: none; stroke: currentColor; stroke-width: 2.5; }
    #fsd-root .fsd-ov-parent.expanded .fsd-ov-caret { transform: rotate(90deg); }
    #fsd-root .fsd-ov-child > td { background: var(--surf); padding: 14px 16px; }
    #fsd-root .fsd-ov-child .nha-table-wrap { margin: 0; }
    #fsd-root .fsd-dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 6px; vertical-align: middle; }
</style>

<div id="fsd-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg viewBox="0 0 24 24" stroke-width="1.6"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/></svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Fund Sourcing Dashboard</div>
                <div class="nha-hdr-sub">Fund Sourcing Division · Fund Transfer / SARO / NCA / Trust / Supplemental Funds</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="fsd-refresh" type="button" class="nha-btn-hdr" title="Refresh data">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="fsd-print" type="button" class="nha-btn-hdr" title="Print">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print
            </button>
            <span class="nha-hdr-upd" id="fsd-updated">—</span>
        </div>
    </div>

    <!-- Sample-data notice — shown only while FSD_USE_MOCK is true -->
    <div id="fsd-note" class="fsd-note no-print" style="display:none;">
        <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Showing sample figures — the live API is not connected yet. Set <code>FSD_USE_MOCK = false</code> in this page to read from the FIMS API.</span>
    </div>

    <!-- Error banner -->
    <div id="fsd-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="fsd-error-msg">Failed to load data. Please refresh.</span>
    </div>

    <!-- ── FILTERS (fiscal_year / program_code pass-through) ──────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group fsd-fy-group">
                <label class="nha-filter-label" for="fsd-fy">Fiscal Year</label>
                <select id="fsd-fy" class="nha-filter-select"><option value="">All Fiscal Years</option></select>
            </div>
            <div class="nha-filter-group nha-filter-group-lg fsd-program-group">
                <label class="nha-filter-label" for="fsd-program">Program</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="fsd-program" class="nha-filter-input" placeholder="All programs" autocomplete="off" title="Type a program code or name, then pick it from the list. Clear the box for all programs.">
                </div>
            </div>
            <div class="nha-filter-group nha-filter-group-md">
                <label class="nha-filter-label" for="fsd-unit">Amount Unit</label>
                <select id="fsd-unit" class="nha-filter-select">
                    <option value="full">Centavos (Full Amount)</option>
                    <option value="thousand">Thousands (K)</option>
                    <option value="million">Millions (M)</option>
                    <option value="billion">Billions (B)</option>
                    <option value="trillion">Trillions (T)</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button id="fsd-clear" type="button" class="nha-btn nha-btn-ghost nha-btn-sm" disabled>Clear filters</button>
            </div>
        </div>
    </div>

    <!-- ══ SRS 1.2.1.1.1 — FUND TRANSFER / BORROWING OF FUNDS ══ -->
    <div class="fsd-section">
        <div class="nha-sec-hd"><span>Fund Transfer / Borrowing of Funds</span><span class="fsd-ref">SRS 1.2.1.1.1</span><span class="nha-sec-hd-line"></span></div>
        <div class="nha-summary" id="fsd-cards-cross"></div>
    </div>

    <!-- ══ SRS 1.2.1.1.2 — SARO ALLOCATIONS / NCA RECEIVED / AVAILABLE FUNDS ══ -->
    <div class="fsd-section">
        <div class="nha-sec-hd"><span>Government Funds — SARO, NCA &amp; Available Funds</span><span class="fsd-ref">SRS 1.2.1.1.2</span><span class="nha-sec-hd-line"></span></div>
        <div class="nha-summary" id="fsd-cards-gov"></div>

        <div class="fsd-ch-grid">
            <div class="fsd-ch-card">
                <div class="fsd-ch-head">
                    <div>
                        <p class="fsd-ch-title">SARO Allocation vs NCA Received vs Unreleased NCA</p>
                        <p class="fsd-ch-sub">Government fund flow comparison</p>
                    </div>
                    <span class="fsd-ch-pill">Government Funds</span>
                </div>
                <div class="fsd-ch-wrap" style="height:240px;"><canvas id="fsd-chart-gov"></canvas><div class="fsd-ch-off"></div></div>
            </div>
            <div class="fsd-ch-card">
                <div class="fsd-ch-head">
                    <div>
                        <p class="fsd-ch-title">SARO Utilization</p>
                        <p class="fsd-ch-sub">Released vs unreleased</p>
                    </div>
                </div>
                <div class="fsd-ch-wrap" style="height:200px;"><canvas id="fsd-chart-saro"></canvas><div class="fsd-ch-off"></div></div>
                <div class="fsd-legend" id="fsd-leg-saro"></div>
            </div>
        </div>
    </div>

    <!-- ══ SRS 1.2.1.3.2 — SPECIAL FUNDS (Trust / Supplemental) ══ -->
    <div class="fsd-section">
        <div class="nha-sec-hd"><span>Special Funds — Trust &amp; Supplemental</span><span class="fsd-ref">SRS 1.2.1.3.2</span><span class="nha-sec-hd-line"></span></div>
        <div class="nha-summary" id="fsd-cards-special"></div>
    </div>

    <!-- ══ SRS 1.2.1.3 — FUND SOURCES OVERVIEW ══ -->
    <div class="fsd-section">
        <div class="nha-sec-hd"><span>Fund Sources Overview</span><span class="fsd-ref">SRS 1.2.1.3</span><span class="nha-sec-hd-line"></span></div>
        <div class="fsd-ch-grid">
            <div class="fsd-ch-card">
                <div class="fsd-ch-head">
                    <div>
                        <p class="fsd-ch-title">Fund Sources Overview</p>
                        <p class="fsd-ch-sub">Total amount recorded per fund source type</p>
                    </div>
                    <span class="fsd-ch-pill">All Sources</span>
                </div>
                <div class="fsd-ch-wrap" style="height:230px;"><canvas id="fsd-chart-all"></canvas><div class="fsd-ch-off"></div></div>
            </div>
            <div class="fsd-ch-card">
                <div class="fsd-ch-head">
                    <div>
                        <p class="fsd-ch-title">Special Funds Composition</p>
                        <p class="fsd-ch-sub">Trust vs. Supplemental</p>
                    </div>
                </div>
                <div class="fsd-ch-wrap" style="height:190px;"><canvas id="fsd-chart-special"></canvas><div class="fsd-ch-off"></div></div>
                <div class="fsd-legend" id="fsd-leg-special"></div>
            </div>
        </div>
    </div>

    <!-- ══ DETAILED BREAKDOWN (tabbed) ══ -->
    <div class="fsd-section">
        <div class="nha-sec-hd"><span>Detailed Breakdown</span><span class="nha-sec-hd-line"></span></div>

        <div class="nha-tabs no-print" id="fsd-tabs" role="tablist">
            <button type="button" class="nha-tab active" data-tab="overview" role="tab">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h7v7H4V4zm9 0h7v7h-7V4zM4 13h7v7H4v-7zm9 0h7v7h-7v-7z"/></svg>
                Overview
            </button>
            <button type="button" class="nha-tab" data-tab="trust" role="tab">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Trust Fund
            </button>
            <button type="button" class="nha-tab" data-tab="saro" role="tab">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                SARO Allocation / GAAAO
            </button>
            <button type="button" class="nha-tab" data-tab="transfer" role="tab">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4"/></svg>
                Fund Transfer
            </button>
        </div>

        <!-- Overview — fund source summary; each row expands into its records -->
        <div class="nha-panel active" id="fsd-panel-overview">
            <div class="nha-table-wrap">
                <div class="nha-tbl-scroll">
                    <table class="nha-tbl fsd-tbl">
                        <thead>
                            <tr>
                                <th>Fund Source</th>
                                <th class="num" style="text-align:right;">Amount</th>
                                <th class="num" style="text-align:right;">Unreleased</th>
                                <th class="num" style="text-align:right;">Records</th>
                            </tr>
                        </thead>
                        <tbody id="fsd-ov-body">
                            <tr><td colspan="4" class="fsd-state"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Trust Fund — GET /fund_tracking/list?type=TRUST_FUND -->
        <div class="nha-panel" id="fsd-panel-trust">
            <div class="fsd-sub-filter-wrap no-print">
                <div class="fsd-sub-filter">
                    <label class="nha-filter-label" for="fsd-trust-search">Agency / Program</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="fsd-trust-search" class="nha-filter-input" placeholder="Filter by agency or program…" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="nha-table-wrap" id="fsd-box-trust"></div>
        </div>

        <!-- SARO Allocation / GAAAO — GET /fund_tracking/list?type=SARO -->
        <div class="nha-panel" id="fsd-panel-saro">
            <div class="nha-table-wrap" id="fsd-box-saro"></div>
        </div>

        <!-- Fund Transfer — records are not touched by the Fiscal Year / Program filters (a transfer carries neither) -->
        <div class="nha-panel" id="fsd-panel-transfer">
            <div class="fsd-sub-filter-wrap no-print">
                <div class="fsd-sub-filter">
                    <label class="nha-filter-label" for="fsd-transfer-search">Search</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" id="fsd-transfer-search" class="nha-filter-input" placeholder="FTLA no., purpose, payee…" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="nha-table-wrap" id="fsd-box-transfer"></div>
        </div>
    </div>

</div><!-- /#fsd-root -->

<script>
/* ═══════════════════════════════════════════════════════════════
   FUND SOURCING DASHBOARD   prefix: fsd_
   SRS 1.2.1.1 + 1.2.1.3 | Fund Sourcing Division
   UI is complete; data comes from fsd_get() — sample data until FSD_USE_MOCK is set to false.
═══════════════════════════════════════════════════════════════ */

/* ── Config ─────────────────────────────────────────────────── */
const FSD_USE_MOCK = true;                       // true = sample data · false = FIMS API
const FSD_BASE = <?= json_encode($_fsd_api) ?>;
const FSD_API = {
    dashboard:     FSD_BASE + '/fund_tracking/dashboard',
    list:          FSD_BASE + '/fund_tracking/list',
    fiscalYears:   FSD_BASE + '/fiscal_year/all',
    programs:      FSD_BASE + '/program/all',
    fundTransfers: FSD_BASE + '/fund_transfer/list',   // not in FIMS yet — see the header comment
};

const $f = id => document.getElementById(id);
const fsd_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const fsd_dash = '<span class="nha-text-muted">—</span>';

/* ── Data access — the ONLY place that knows whether data is real ───
   key: a FSD_API key · params: query-string names exactly as the API takes them.
   Resolves with the API's JSON ({success, data, pagination?}) or throws Error(message). */
async function fsd_get(key, params = {}) {
    if (FSD_USE_MOCK) return fsd_mock.respond(key, params);

    const qs = new URLSearchParams();
    Object.entries(params).forEach(([k, v]) => { if (v !== '' && v !== null && v !== undefined) qs.set(k, v); });
    const url = FSD_API[key] + (qs.toString() ? '?' + qs : '');

    const res = await fetch(url, { credentials: 'same-origin', headers: { 'Content-Type': 'application/json' } });
    let json = null;
    try { json = await res.json(); } catch (_) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) {
        throw new Error((json && (json.message || json.error)) || ('HTTP ' + res.status));
    }
    return json;
}

/* ── Amount Unit — display scale for every peso amount on the page.
   Pure display preference: it never changes what is fetched, only how the cached payloads are shown. ── */
const FSD_UNITS = {
    full:     { divisor: 1,             suffix: '' },
    thousand: { divisor: 1e3,           suffix: 'K' },
    million:  { divisor: 1e6,           suffix: 'M' },
    billion:  { divisor: 1e9,           suffix: 'B' },
    trillion: { divisor: 1e12,          suffix: 'T' },
};

// A scaled value under 1 (₱2,000,000 in Billions = 0.002) would round to "0.00" and look like zero — widen the decimals.
function fsd_decimals(scaled) {
    const abs = Math.abs(scaled);
    if (abs === 0 || abs >= 1) return 2;
    return Math.min(8, Math.max(2, Math.ceil(-Math.log10(abs)) + 1));
}
function fsd_peso(v) {
    v = parseFloat(v) || 0;
    const unit = FSD_UNITS[fsd.unit] || FSD_UNITS.full;
    const scaled = Math.abs(v) / unit.divisor;
    return (v < 0 ? '-' : '') + '₱' + scaled.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: fsd_decimals(scaled) }) + unit.suffix;
}
const fsd_pesoFull = v => '₱ ' + (parseFloat(v) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fsd_plural = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
function fsd_date(str) {
    if (!str) return '—';
    const d = new Date(String(str).length === 10 ? str + 'T00:00:00' : str);
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}

/* ── State ──────────────────────────────────────────────────── */
const fsd = {
    fy: '', program: '',          // the two server-side filters (fiscal_year, program_code)
    unit: 'full',
    gov: null, special: null, cross: undefined,    // cross: undefined = not loaded · null = API does not provide it
    req: 0,                       // bumped per dashboard request so a stale response never overwrites a newer one
    charts: {},
    programCodes: new Set(),
    tab: 'overview',
    expanded: new Set(),          // Overview rows currently expanded
    errors: [],
};
const fsd_filters = () => ({ fiscal_year: fsd.fy, program_code: fsd.program });

function fsd_showError(msg) {
    if (!fsd.errors.includes(msg)) fsd.errors.push(msg);
    $f('fsd-error').style.display = 'flex';
    $f('fsd-error-msg').textContent = fsd.errors.join(' · ');
}
function fsd_hideError() { fsd.errors = []; $f('fsd-error').style.display = 'none'; }
function fsd_stamp() { $f('fsd-updated').textContent = 'Updated: ' + new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' }); }

/* ═══════════════════════════════════════════════════════════════
   SUMMARY CARDS
═══════════════════════════════════════════════════════════════ */
const FSD_ICON = {
    transfer: 'M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4',
    receipt:  'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
    plus:     'M12 4v16m8-8H4',
    clock:    'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
    trust:    'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
    wallet:   'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
};
const fsd_icon = (name, tone) => `<div class="nha-sc-ico nha-ico-${tone}"><svg viewBox="0 0 24 24"><path d="${FSD_ICON[name]}"/></svg></div>`;

function fsd_skeleton(n, wide) {
    const one = `<div class="nha-sc" style="height:112px;"><div class="fsd-skel" style="height:10px;width:50%;margin-bottom:12px;"></div><div class="fsd-skel" style="height:24px;width:42%;margin-bottom:10px;"></div><div class="fsd-skel" style="height:10px;width:70%;"></div></div>`;
    const first = wide ? one.replace('class="nha-sc"', 'class="nha-sc fsd-wide"') : one;
    return first + one.repeat(Math.max(0, n - 1));
}

function fsd_card({ tone, label, value, sub, icon, wide, extra = '' }) {
    return `
    <div class="nha-sc nha-sc-${tone}${wide ? ' fsd-wide' : ''}">
        <div class="nha-sc-top">
            <div>
                <p class="nha-sc-label">${fsd_esc(label)}</p>
                <p class="nha-sc-val" title="${fsd_pesoFull(value)}">${fsd_peso(value)}</p>
                <p class="nha-kpi-sub">${sub}</p>
            </div>
            ${fsd_icon(icon, tone)}
        </div>
        ${extra}
    </div>`;
}

/* Fund Transfer / Borrowing — ONE card, Payable and Receivable side by side.
   Payable = not settled yet · Receivable = completed. The pill flags payables past their repayment due date. */
function fsd_renderCross() {
    const el = $f('fsd-cards-cross');
    const c = fsd.cross;
    if (!c) {
        el.innerHTML = `<div class="nha-sc nha-sc-gray"><div class="nha-sc-top"><div>
            <p class="nha-sc-label">Payable / Receivable</p>
            <p class="nha-sc-val" style="font-size:18px;color:var(--muted);">Not available yet</p>
            <p class="nha-kpi-sub">The FIMS API does not provide fund transfer figures yet.</p></div></div></div>`;
        return;
    }
    const pay = parseFloat(c.payable_amount) || 0, rec = parseFloat(c.receivable_amount) || 0;
    const payN = parseInt(c.payable_count) || 0, recN = parseInt(c.receivable_count) || 0;
    const late = parseInt(c.payable_overdue_count) || 0;
    el.innerHTML = `
    <div class="nha-sc nha-sc-navy fsd-wide">
        <div class="nha-sc-top">
            <div class="fsd-pr">
                <div class="fsd-pr-col">
                    <p class="nha-sc-label">Payable</p>
                    <p class="nha-sc-val" title="${fsd_pesoFull(pay)}">${fsd_peso(pay)}</p>
                    <p class="nha-kpi-sub">${fsd_plural(payN, 'record')} · pending${late > 0 ? `<span class="fsd-overdue">${late} overdue</span>` : ''}</p>
                </div>
                <div class="fsd-pr-col">
                    <p class="nha-sc-label">Receivable</p>
                    <p class="nha-sc-val" title="${fsd_pesoFull(rec)}">${fsd_peso(rec)}</p>
                    <p class="nha-kpi-sub">${fsd_plural(recN, 'record')} · completed</p>
                </div>
            </div>
            ${fsd_icon('transfer', 'navy')}
        </div>
    </div>`;
}

function fsd_renderGov() {
    const g = fsd.gov || {};
    const saro = parseFloat(g.saro_allocation) || 0, nca = parseFloat(g.nca_received) || 0, avail = parseFloat(g.available_funds) || 0;
    const saroN = parseInt(g.saro_record_count) || 0, ncaN = parseInt(g.nca_record_count) || 0;
    const pct = saro > 0 ? Math.max(0, Math.min(100, Math.round((avail / saro) * 100))) : 0;

    $f('fsd-cards-gov').innerHTML =
        fsd_card({
            tone: 'navy', label: 'SARO Allocation', value: saro, wide: true, icon: 'receipt',
            sub: `${fsd_plural(saroN, 'SARO record')} · Unreleased: ${fsd_peso(avail)}`,
            extra: `<div class="fsd-prog"><span data-w="${pct}"></span></div><p class="fsd-prog-lbl">${pct}% of SARO allocation still available</p>`,
        }) +
        fsd_card({ tone: 'blue', label: 'NCA Received', value: nca, icon: 'plus', sub: fsd_plural(ncaN, 'record') }) +
        fsd_card({ tone: 'teal', label: 'Unreleased NCA', value: avail, icon: 'clock', sub: 'SARO − NCA' });

    requestAnimationFrame(() => setTimeout(() => {
        $f('fsd-cards-gov').querySelectorAll('.fsd-prog > span').forEach(b => { b.style.width = b.dataset.w + '%'; });
    }, 80));
}

function fsd_renderSpecial() {
    const s = fsd.special || {};
    const trust = parseFloat(s.trust_funds) || 0, supp = parseFloat(s.supplemental_funds) || 0;
    $f('fsd-cards-special').innerHTML =
        fsd_card({ tone: 'purple', label: 'Trust Funds', value: trust, icon: 'trust', sub: fsd_plural(parseInt(s.trust_funds_record_count) || 0, 'record') }) +
        fsd_card({ tone: 'amber', label: 'Supplemental Funds', value: supp, icon: 'wallet', sub: fsd_plural(parseInt(s.supplemental_funds_record_count) || 0, 'record') });
}

/* ═══════════════════════════════════════════════════════════════
   CHARTS (Chart.js — loaded on demand; the shared custom.js themes every chart)
═══════════════════════════════════════════════════════════════ */
const fsd_chartReady = new Promise(resolve => {
    if (window.Chart) return resolve(true);
    let s = document.getElementById('fsd-chartjs-lib');
    if (!s) {
        s = document.createElement('script');
        s.id = 'fsd-chartjs-lib';
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        document.head.appendChild(s);
    }
    s.addEventListener('load', () => resolve(!!window.Chart));
    s.addEventListener('error', () => resolve(false));
});

const FSD_COLOR = { navy: '#000E3E', blue: '#0035A9', green: '#11A752', amber: '#FCD20F', purple: '#6D5BD0', gray: '#CBD5E1' };
const fsd_compact = new Intl.NumberFormat('en-PH', { notation: 'compact', maximumFractionDigits: 1 });
// axis ticks: the chosen Amount Unit, or a compact number (₱1.2B) when the unit is "full"
const fsd_axis = v => fsd.unit === 'full' ? '₱' + fsd_compact.format(v) : fsd_peso(v).replace(/\.0+(?=[A-Z]?$)/, '');

async function fsd_chart(key, canvasId, config) {
    const ok = await fsd_chartReady;
    const canvas = $f(canvasId), off = canvas.parentElement.querySelector('.fsd-ch-off');
    if (fsd.charts[key]) { fsd.charts[key].destroy(); delete fsd.charts[key]; }
    if (!ok) {
        canvas.style.display = 'none';
        off.style.display = 'flex'; off.textContent = 'Chart unavailable — could not load Chart.js.';
        return;
    }
    canvas.style.display = ''; off.style.display = 'none';
    fsd.charts[key] = new Chart(canvas.getContext('2d'), config);
}

const fsd_tooltip = { callbacks: { label: ctx => ' ' + fsd_pesoFull(ctx.parsed.y ?? ctx.parsed) } };

function fsd_barConfig(labels, values, colors) {
    return {
        type: 'bar',
        data: { labels, datasets: [{ data: values, backgroundColor: colors, borderRadius: 6, maxBarThickness: 64 }] },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: fsd_tooltip },
            scales: { y: { beginAtZero: true, ticks: { callback: fsd_axis } } },
        },
    };
}

// Doughnut over [{label, value, color}] — an all-zero set draws a grey "no data" ring instead of an empty canvas
function fsd_doughnutConfig(items) {
    const total = items.reduce((a, i) => a + i.value, 0);
    return {
        type: 'doughnut',
        data: {
            labels: items.map(i => i.label),
            datasets: [{ data: total > 0 ? items.map(i => i.value) : [1], backgroundColor: total > 0 ? items.map(i => i.color) : [FSD_COLOR.gray] }],
        },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '68%',
            plugins: {
                legend: { display: false },
                tooltip: total > 0 ? { callbacks: { label: ctx => ` ${ctx.label}: ${fsd_pesoFull(ctx.parsed)} (${Math.round(ctx.parsed / total * 100)}%)` } } : { enabled: false },
            },
        },
    };
}

function fsd_legend(id, items) {
    $f(id).innerHTML = items.map(i =>
        `<span class="fsd-legend-item"><span class="fsd-legend-dot" style="background:${i.color};"></span>${fsd_esc(i.label)} (${fsd_peso(i.value)})</span>`).join('');
}

function fsd_renderCharts() {
    const g = fsd.gov || {}, s = fsd.special || {};
    const saro = parseFloat(g.saro_allocation) || 0, nca = parseFloat(g.nca_received) || 0, avail = parseFloat(g.available_funds) || 0;
    const unrel = parseFloat(g.saro_unreleased) || 0;
    const trust = parseFloat(s.trust_funds) || 0, supp = parseFloat(s.supplemental_funds) || 0;

    fsd_chart('gov', 'fsd-chart-gov', fsd_barConfig(['SARO Allocation', 'NCA Received', 'Unreleased NCA'], [saro, nca, avail], [FSD_COLOR.navy, FSD_COLOR.blue, FSD_COLOR.green]));

    const saroItems = [
        { label: 'Released', value: Math.max(0, saro - unrel), color: FSD_COLOR.blue },
        { label: 'Unreleased', value: unrel, color: FSD_COLOR.amber },
    ];
    fsd_chart('saro', 'fsd-chart-saro', fsd_doughnutConfig(saroItems));
    fsd_legend('fsd-leg-saro', saroItems);

    fsd_chart('all', 'fsd-chart-all', fsd_barConfig(['SARO', 'NCA', 'Trust Funds', 'Supplemental'], [saro, nca, trust, supp], [FSD_COLOR.navy, FSD_COLOR.blue, FSD_COLOR.purple, FSD_COLOR.amber]));

    const specialItems = [
        { label: 'Trust', value: trust, color: FSD_COLOR.purple },
        { label: 'Supplemental', value: supp, color: FSD_COLOR.amber },
    ];
    fsd_chart('special', 'fsd-chart-special', fsd_doughnutConfig(specialItems));
    fsd_legend('fsd-leg-special', specialItems);
}

/* ═══════════════════════════════════════════════════════════════
   RECORD LISTS — one factory serves the Overview child rows and the three tabs.
   Each list owns its page/limit/rows, renders a whole table (head + rows + footer) into its box, and re-renders from
   its cache when the Amount Unit changes.
═══════════════════════════════════════════════════════════════ */
function fsd_pageList(cur, last) {
    const sorted = [...new Set([1, last, cur - 1, cur, cur + 1])].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);
    const out = [];
    sorted.forEach((p, i) => { if (i > 0 && p - sorted[i - 1] > 1) out.push('…'); out.push(p); });
    return out;
}

const fsd_badge = (text, tone) => text ? `<span class="nha-bdg nha-bdg-${tone}">${fsd_esc(text)}</span>` : fsd_dash;
const fsd_amtCell = v => { const n = parseFloat(v) || 0; return `<td class="num" title="${fsd_pesoFull(n)}">${n ? fsd_peso(n) : '—'}</td>`; };
const fsd_parentRef = r => (Array.isArray(r.parent) ? r.parent[0] : null)?.reference_number || '';
const fsd_refCell = ref => ref ? `<span class="nha-mono nha-td-bold">${fsd_esc(ref)}</span>` : fsd_dash;
const fsd_yearCell = r => r.fiscal_year ? `<span style="font-size:11px;">FY ${fsd_esc(r.fiscal_year)}</span>` : fsd_dash;
function fsd_programCell(sp) {
    if (!sp || !(sp.program_name || sp.sub_program_name)) return fsd_dash;
    return `<div style="font-size:12px;line-height:1.35;"><div style="font-weight:500;">${fsd_esc(sp.sub_program_name || sp.program_name)}</div>
        <div class="nha-mono" style="font-size:10px;color:var(--muted);">${fsd_esc(sp.sub_program_code || sp.program_code || '')}${sp.program_name ? ' · ' + fsd_esc(sp.program_name) : ''}</div></div>`;
}
const fsd_transferTone = s => ({ completed: 'green', pending: 'amber', overdue: 'red', cancelled: 'gray' }[String(s || '').toLowerCase()] || 'gray');

// Column sets. `head`: [label, right-aligned?] · `row(r)`: the cells after the leading #.
const FSD_COLS = {
    // SARO / NCA / Unreleased NCA records (the SARO / NCA Tracking register's columns)
    tracking: {
        head: [['Reference No.'], ['Name / Title'], ['Program'], ['Tracking Type'], ['Nature of Fund'], ['Fiscal Year'], ['Amount (₱)', 1], ['Date'], ['Unreleased (₱)', 1]],
        row: r => `<td>${fsd_refCell(r.reference_number)}</td><td>${fsd_esc(r.name) || fsd_dash}</td><td>${fsd_programCell(r.sub_program)}</td>
            <td>${fsd_badge(r.tracking_type?.fund_tracking_code, 'navy')}</td><td>${fsd_badge(r.nature_of_fund?.name, 'blue')}</td><td>${fsd_yearCell(r)}</td>
            ${fsd_amtCell(r.amount)}<td>${fsd_date(r.date)}</td>${fsd_amtCell(r.unreleased)}`,
    },
    // Trust Fund MOAs register
    trust: {
        head: [['Agency'], ['Program'], ['SARO No. / OR No. / CA No.'], ['Amount (₱)', 1], ['Date'], ['Remarks']],
        row: r => `<td class="nha-td-bold">${fsd_esc(r.agency?.name) || fsd_dash}</td><td>${fsd_programCell(r.sub_program)}</td><td>${fsd_refCell(fsd_parentRef(r))}</td>
            ${fsd_amtCell(r.amount)}<td>${fsd_date(r.date)}</td>
            <td title="${fsd_esc(r.remarks)}">${r.remarks ? fsd_esc(r.remarks.length > 60 ? r.remarks.slice(0, 60) + '…' : r.remarks) : fsd_dash}</td>`,
    },
    // Supplemental Funds — identified by `purpose` rather than a name
    supp: {
        head: [['Reference No.'], ['Program'], ['Purpose'], ['Nature of Fund'], ['Fiscal Year'], ['Amount (₱)', 1], ['Parent Reference'], ['Date']],
        row: r => `<td>${fsd_refCell(r.reference_number)}</td><td>${fsd_programCell(r.sub_program)}</td><td style="font-size:12px;">${fsd_esc(r.purpose) || fsd_dash}</td>
            <td>${fsd_badge(r.nature_of_fund?.name, 'amber')}</td><td>${fsd_yearCell(r)}</td>${fsd_amtCell(r.amount)}
            <td>${fsd_parentRef(r) ? `<span class="nha-mono" style="font-size:12px;">${fsd_esc(fsd_parentRef(r))}</span>` : fsd_dash}</td><td>${fsd_date(r.date)}</td>`,
    },
    transfer: {
        head: [['FTLA No. / Reference'], ['Transfer Date'], ['From'], ['To'], ['Payee'], ['Amount (₱)', 1], ['Purpose'], ['Status']],
        row: r => `<td>${fsd_refCell(r.ftla_no)}</td><td>${fsd_date(r.transfer_date)}</td><td>${fsd_esc(r.from) || fsd_dash}</td><td>${fsd_esc(r.to) || fsd_dash}</td>
            <td>${fsd_esc(r.payee) || fsd_dash}</td>${fsd_amtCell(r.amount)}<td style="font-size:12px;">${fsd_esc(r.purpose) || fsd_dash}</td>
            <td>${fsd_badge(r.status, fsd_transferTone(r.status))}</td>`,
    },
};

/* cfg: { cols, endpoint?, params(): extra query params, fallbackTotal(): number|null, totalLabel?,
          unreleasedOnly? — fetch one big batch, keep records with an unreleased amount, page client-side, emptyText? } */
function fsd_makeList(cfg) {
    const st = { page: 1, limit: 10, total: 0, rows: [], totalAmount: null, loaded: false, loading: false, error: null, req: 0, box: null, allData: null };
    const cols = FSD_COLS[cfg.cols];

    async function load() {
        const my = ++st.req;
        st.loading = true; st.error = null; render();
        try {
            if (cfg.unreleasedOnly) {
                if (!st.allData) {
                    const json = await fsd_get(cfg.endpoint || 'list', { ...cfg.params(), page: 1, limit: 500 });
                    if (my !== st.req) return;
                    st.allData = (json.data || []).filter(r => (parseFloat(r.unreleased) || 0) > 0);
                }
                const off = (st.page - 1) * st.limit;
                st.total = st.allData.length;
                st.rows = st.allData.slice(off, off + st.limit);
                st.totalAmount = st.allData.reduce((a, r) => a + (parseFloat(r.unreleased) || 0), 0);
            } else {
                const json = await fsd_get(cfg.endpoint || 'list', { ...cfg.params(), page: st.page, limit: st.limit });
                if (my !== st.req) return;
                const pg = json.pagination || {};
                st.rows = json.data || [];
                st.total = parseInt(pg.total) || st.rows.length;
                st.totalAmount = pg.total_amount ?? json.total_amount ?? (cfg.fallbackTotal ? cfg.fallbackTotal() : null);
            }
            st.loaded = true;
        } catch (e) {
            if (my !== st.req) return;
            st.rows = []; st.total = 0; st.totalAmount = null; st.error = e.message;
        }
        st.loading = false;
        render();
    }

    function render() {
        if (!st.box) return;
        const span = cols.head.length + 1;
        let body;
        if (st.error) body = `<tr><td colspan="${span}" class="fsd-state err">Could not load records: ${fsd_esc(st.error)}</td></tr>`;
        else if (st.loading && !st.rows.length) body = `<tr><td colspan="${span}" class="fsd-state"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>`;
        else if (!st.rows.length) body = `<tr><td colspan="${span}" class="fsd-state">${fsd_esc(cfg.emptyText || 'No records found.')}</td></tr>`;
        else body = st.rows.map((r, i) => `<tr><td class="nha-td-seq">${(st.page - 1) * st.limit + i + 1}</td>${cols.row(r)}</tr>`).join('');

        const last = Math.max(1, Math.ceil(st.total / st.limit));
        const from = st.total ? (st.page - 1) * st.limit + 1 : 0;
        const to = Math.min(st.page * st.limit, st.total);
        const btn = (label, page, cls = '') => `<button type="button" class="nha-pag-btn ${cls}" data-page="${page}">${label}</button>`;
        const pager = btn('‹', st.page - 1, st.page <= 1 ? 'disabled' : '') +
            fsd_pageList(st.page, last).map(p => p === '…' ? '<span class="nha-pag-ellipsis">…</span>' : btn(p, p, p === st.page ? 'active' : '')).join('') +
            btn('›', st.page + 1, st.page >= last ? 'disabled' : '');

        st.box.innerHTML = `
            <div class="nha-tbl-scroll"><table class="nha-tbl fsd-tbl">
                <thead><tr><th>#</th>${cols.head.map(([t, right]) => `<th${right ? ' class="num" style="text-align:right;"' : ''}>${t}</th>`).join('')}</tr></thead>
                <tbody>${body}</tbody>
            </table></div>
            <div class="nha-pag-wrap no-print">
                <span class="nha-pag-info">${st.total ? `Showing <strong>${from}–${to}</strong> of <strong>${st.total}</strong> records` : '—'}</span>
                <span class="fsd-total">${cfg.totalLabel || 'Total Amount'}: ${st.totalAmount === null || st.totalAmount === undefined ? '—' : fsd_peso(st.totalAmount)}</span>
                <div class="nha-pag-btns">${pager}</div>
            </div>`;

        st.box.querySelectorAll('.nha-pag-btn[data-page]:not(.disabled):not(.active)').forEach(b => b.addEventListener('click', () => {
            st.page = parseInt(b.dataset.page, 10); load();
        }));
    }

    return {
        st, load, render,
        mount(box) { st.box = box; render(); },
        // filters changed → drop the cache and go back to page 1 (the caller reloads it if it is on screen)
        reset() { st.page = 1; st.loaded = false; st.allData = null; st.rows = []; st.total = 0; st.totalAmount = null; st.error = null; st.req++; st.loading = false; },
    };
}

/* Fund-source rows of the Overview tab: key → [label, badge tone, dot colour, list config].
   "Unreleased NCA" has no tracking type of its own (its card is SARO − NCA), so its breakdown pulls NCA records and keeps the
   ones that carry an unreleased amount. Supplemental Funds are matched by nature of fund, not by tracking type. */
const fsd_filterParams = () => ({ fiscal_year: fsd.fy, program_code: fsd.program });
const FSD_OV = {
    saro:  { label: 'SARO Allocation / GAAAO', tone: 'blue',   dot: FSD_COLOR.blue,   cols: 'tracking', params: () => ({ ...fsd_filterParams(), type: 'SARO' }),       fallbackTotal: () => fsd.gov?.saro_allocation },
    nca:   { label: 'NCA Received',            tone: 'green',  dot: FSD_COLOR.green,  cols: 'tracking', params: () => ({ ...fsd_filterParams(), type: 'NCA' }),        fallbackTotal: () => fsd.gov?.nca_received },
    unrel: { label: 'Unreleased NCA',          tone: 'teal',   dot: '#047857',        cols: 'tracking', params: () => ({ ...fsd_filterParams(), type: 'NCA' }),        unreleasedOnly: true, totalLabel: 'Total Unreleased', emptyText: 'No records with unreleased NCA found.' },
    trust: { label: 'Trust Funds',             tone: 'purple', dot: FSD_COLOR.purple, cols: 'trust',    params: () => ({ ...fsd_filterParams(), type: 'TRUST_FUND' }), fallbackTotal: () => fsd.special?.trust_funds },
    supp:  { label: 'Supplemental Funds',      tone: 'amber',  dot: '#B45309',        cols: 'supp',     params: () => ({ ...fsd_filterParams(), nature_of_fund: 'SUPPLEMENTAL' }), fallbackTotal: () => fsd.special?.supplemental_funds },
};
const fsd_ovLists = {};
Object.entries(FSD_OV).forEach(([k, c]) => { fsd_ovLists[k] = fsd_makeList(c); });

const fsd_lists = {
    trust: fsd_makeList({
        cols: 'trust', emptyText: 'No trust fund records found.',
        params: () => ({ ...fsd_filterParams(), type: 'TRUST_FUND', agency_or_program_search: $f('fsd-trust-search').value.trim() }),
        fallbackTotal: () => $f('fsd-trust-search').value.trim() ? null : fsd.special?.trust_funds,
    }),
    saro: fsd_makeList({
        cols: 'tracking', emptyText: 'No SARO records found.',
        params: () => ({ ...fsd_filterParams(), type: 'SARO' }),
        fallbackTotal: () => fsd.gov?.saro_allocation,
    }),
    transfer: fsd_makeList({
        cols: 'transfer', endpoint: 'fundTransfers', emptyText: 'No fund transfer records found.',
        params: () => ({ search: $f('fsd-transfer-search').value.trim() }),
    }),
};
fsd_lists.trust.mount($f('fsd-box-trust'));
fsd_lists.saro.mount($f('fsd-box-saro'));
fsd_lists.transfer.mount($f('fsd-box-transfer'));

/* ── Overview table ── */
const FSD_CARET = '<svg class="fsd-ov-caret" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>';

function fsd_renderOverview() {
    const g = fsd.gov, s = fsd.special;
    const body = $f('fsd-ov-body');
    if (!g || !s) { body.innerHTML = `<tr><td colspan="4" class="fsd-state"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>`; return; }

    const figures = {
        saro:  [g.saro_allocation, g.saro_unreleased, g.saro_record_count],
        nca:   [g.nca_received, null, g.nca_record_count],
        unrel: [g.available_funds, null, null],
        trust: [s.trust_funds, null, s.trust_funds_record_count],
        supp:  [s.supplemental_funds, null, s.supplemental_funds_record_count],
    };
    body.innerHTML = Object.entries(FSD_OV).map(([k, c]) => {
        const [amount, unreleased, count] = figures[k];
        const open = fsd.expanded.has(k);
        return `
        <tr class="fsd-ov-parent${open ? ' expanded' : ''}" data-ov="${k}">
            <td>${FSD_CARET}<span class="nha-bdg nha-bdg-${c.tone}"><span class="fsd-dot" style="background:${c.dot};"></span>${fsd_esc(c.label)}</span></td>
            <td class="num" title="${fsd_pesoFull(amount)}">${fsd_peso(amount)}</td>
            <td class="num">${unreleased !== null ? fsd_peso(unreleased) : '—'}</td>
            <td class="num">${count !== null ? count : '—'}</td>
        </tr>${open ? `<tr class="fsd-ov-child"><td colspan="4"><div class="nha-table-wrap" id="fsd-ov-box-${k}"></div></td></tr>` : ''}`;
    }).join('');

    // the child tables were just rebuilt — hand each its new box (rendering from cache, or loading on first open)
    fsd.expanded.forEach(k => {
        fsd_ovLists[k].mount($f('fsd-ov-box-' + k));
        if (!fsd_ovLists[k].st.loaded && !fsd_ovLists[k].st.loading) fsd_ovLists[k].load();
    });
}

$f('fsd-ov-body').addEventListener('click', e => {
    const row = e.target.closest('tr[data-ov]');
    if (!row) return;
    const k = row.dataset.ov;
    fsd.expanded.has(k) ? fsd.expanded.delete(k) : fsd.expanded.add(k);
    fsd_renderOverview();
});

/* ── Tabs ── */
function fsd_switchTab(tab) {
    fsd.tab = tab;
    document.querySelectorAll('#fsd-tabs .nha-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('#fsd-root .nha-panel').forEach(p => p.classList.toggle('active', p.id === 'fsd-panel-' + tab));
    const list = fsd_lists[tab];
    if (list && !list.st.loaded && !list.st.loading && !list.st.error) list.load();
}
$f('fsd-tabs').addEventListener('click', e => { const b = e.target.closest('.nha-tab'); if (b) fsd_switchTab(b.dataset.tab); });

function fsd_debounced(fn, ms = 350) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; }
$f('fsd-trust-search').addEventListener('input', fsd_debounced(() => { fsd_lists.trust.st.page = 1; fsd_lists.trust.load(); }));
$f('fsd-transfer-search').addEventListener('input', fsd_debounced(() => { fsd_lists.transfer.st.page = 1; fsd_lists.transfer.load(); }));

/* ═══════════════════════════════════════════════════════════════
   LOADING
═══════════════════════════════════════════════════════════════ */
function fsd_renderAll() {
    fsd_renderCross();
    fsd_renderGov();
    fsd_renderSpecial();
    fsd_renderCharts();
    fsd_renderOverview();
}

async function fsd_loadDashboard() {
    const my = ++fsd.req;
    $f('fsd-cards-cross').innerHTML = fsd_skeleton(1, true);
    $f('fsd-cards-gov').innerHTML = fsd_skeleton(3, true);
    $f('fsd-cards-special').innerHTML = fsd_skeleton(2);
    try {
        const json = await fsd_get('dashboard', fsd_filters());
        if (my !== fsd.req) return;                       // a newer request replaced this one
        const d = json.data || {};
        fsd.gov = d.government_funds || {};
        fsd.special = d.special_funds || {};
        fsd.cross = d.cross_access_funds || null;         // FIMS does not send it yet → "not available" card
        fsd_hideError();
        fsd_renderAll();
        fsd_stamp();
    } catch (e) {
        if (my !== fsd.req) return;
        fsd.gov = fsd.gov || {}; fsd.special = fsd.special || {}; fsd.cross = fsd.cross ?? null;
        fsd_showError('Could not load dashboard data: ' + e.message);
        fsd_renderAll();
    }
}

// Filters changed (or Refresh): every list's cache is stale. Reload whatever is on screen; the rest loads when opened.
function fsd_reloadLists({ includeTransfer = false } = {}) {
    Object.values(fsd_ovLists).forEach(l => l.reset());
    ['trust', 'saro'].forEach(k => fsd_lists[k].reset());
    if (includeTransfer) fsd_lists.transfer.reset();
    fsd_renderOverview();
    const list = fsd_lists[fsd.tab];
    if (list && !list.st.loaded) list.load();
}

function fsd_applyFilters() {
    $f('fsd-clear').disabled = !(fsd.fy || fsd.program);
    fsd_hideError();
    fsd_loadDashboard();
    fsd_reloadLists();
}

async function fsd_loadFilterOptions() {
    const [fy, pr] = await Promise.allSettled([fsd_get('fiscalYears'), fsd_get('programs')]);

    if (fy.status === 'fulfilled') {
        const years = [...new Set((fy.value.data || []).map(r => Number(r.fiscal_yr)).filter(Boolean))].sort((a, b) => b - a);
        $f('fsd-fy').insertAdjacentHTML('beforeend', years.map(y => `<option value="${y}">${y}</option>`).join(''));
    } else console.warn('[fsd] fiscal years:', fy.reason);   // filters are a convenience — the page still works unfiltered

    if (pr.status === 'fulfilled') {
        const programs = (pr.value.data || []).map(p => ({ value: String(p.program_code), label: String(p.program_name ?? '') }));
        fsd.programCodes = new Set(programs.map(p => p.value));
        if (typeof nhaSearchableSelect === 'function') nhaSearchableSelect($f('fsd-program'), programs);
    } else console.warn('[fsd] programs:', pr.reason);
}

/* ═══════════════════════════════════════════════════════════════
   UI WIRING
═══════════════════════════════════════════════════════════════ */
$f('fsd-fy').addEventListener('change', e => { fsd.fy = e.target.value; fsd_applyFilters(); });

// Program: the box takes a code or a name; it only filters once a suggestion is picked (or the box is emptied)
$f('fsd-program').addEventListener('change', e => {
    const v = e.target.value.trim();
    if (v !== '' && !fsd.programCodes.has(v)) return;
    if (v === fsd.program) return;
    fsd.program = v; fsd_applyFilters();
});
$f('fsd-program').addEventListener('input', e => {
    if (e.target.value.trim() === '' && fsd.program !== '') { fsd.program = ''; fsd_applyFilters(); }
});

// Amount Unit: display only — re-render everything that is on screen from cache, no refetch
$f('fsd-unit').addEventListener('change', e => {
    fsd.unit = FSD_UNITS[e.target.value] ? e.target.value : 'full';
    fsd_renderAll();
    Object.values(fsd_ovLists).forEach(l => l.render());
    Object.values(fsd_lists).forEach(l => l.render());
});

$f('fsd-clear').addEventListener('click', () => {
    fsd.fy = ''; fsd.program = '';
    $f('fsd-fy').value = ''; $f('fsd-program').value = '';
    fsd_applyFilters();
});

$f('fsd-refresh').addEventListener('click', () => {
    fsd_hideError();
    fsd_loadDashboard();
    fsd_reloadLists({ includeTransfer: true });
});
$f('fsd-print').addEventListener('click', () => window.print());

/* ═══════════════════════════════════════════════════════════════
   SAMPLE DATA (only used while FSD_USE_MOCK is true)
   Deterministic and internally consistent: the dashboard totals are computed from the very same records the tables list,
   and every response has the shape of the real endpoint it stands in for (see the header comment).
═══════════════════════════════════════════════════════════════ */
const fsd_mock = (() => {
    let seed = 20260;
    const rnd = () => { seed = (seed + 0x6D2B79F5) | 0; let t = Math.imul(seed ^ (seed >>> 15), 1 | seed); t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t; return ((t ^ (t >>> 14)) >>> 0) / 4294967296; };
    const between = (a, b) => a + rnd() * (b - a);
    const money = (a, b) => Math.round(between(a, b) * 100) / 100;
    const pick = arr => arr[Math.floor(rnd() * arr.length)];
    const pad = (n, w = 4) => String(n).padStart(w, '0');
    const iso = (y, m, d) => `${y}-${pad(m, 2)}-${pad(d, 2)}`;
    const sum = (arr, k = 'amount') => arr.reduce((a, r) => a + (parseFloat(r[k]) || 0), 0);

    const YEARS = [2023, 2024, 2025, 2026];
    const PROGRAMS = [
        { program_code: 'HDP', program_name: 'Housing Development Program', subs: [['HDP-01', 'Resettlement Housing'], ['HDP-02', 'Site Development']] },
        { program_code: 'SHP', program_name: 'Socialized Housing Program', subs: [['SHP-01', 'Community Mortgage'], ['SHP-02', 'Medium-Rise Housing']] },
        { program_code: 'CRP', program_name: 'Calamity Recovery Program', subs: [['CRP-01', 'Emergency Shelter'], ['CRP-02', 'Rehabilitation']] },
        { program_code: 'URP', program_name: 'Urban Renewal Program', subs: [['URP-01', 'Slum Upgrading']] },
    ];
    const subProgram = (pg, sub) => ({ sub_program_code: sub[0], sub_program_name: sub[1], program_code: pg.program_code, program_name: pg.program_name });
    const TYPE = {
        SARO: { fund_tracking_code: 'SARO', fund_tracking_name: 'Special Allotment Release Order' },
        NCA: { fund_tracking_code: 'NCA', fund_tracking_name: 'Notice of Cash Allocation' },
        TRUST: { fund_tracking_code: 'TRUST_FUND', fund_tracking_name: 'Trust Fund' },
    };

    let id = 1000, saroSeq = 0, ncaSeq = 0, trustSeq = 0, suppSeq = 0;
    const saros = [], ncas = [], trusts = [], supps = [];

    // SAROs: one per program per fiscal year, each released in 1–3 NCAs that cover 55–95% of it
    YEARS.forEach(fy => PROGRAMS.forEach(pg => {
        const sub = pick(pg.subs);
        const saro = {
            id: ++id, fiscal_year: fy, reference_number: `SARO-ROCS-${fy}-${pad(++saroSeq)}`, name: `${sub[1]} ${fy}`,
            amount: money(90e6, 850e6), unreleased: 0, date: iso(fy, 1 + Math.floor(rnd() * 5), 1 + Math.floor(rnd() * 27)),
            tracking_type: TYPE.SARO, nature_of_fund: { name: pick(['GAA', 'GAA', 'GAAAO', 'Continuing']) },
            sub_program: subProgram(pg, sub), agency: null, remarks: null, parent: [], children: [],
        };
        const released = saro.amount * between(0.55, 0.95), n = 1 + Math.floor(rnd() * 3);
        let total = 0;
        for (let i = 0; i < n; i++) {
            const amount = Math.round(released / n * 100) / 100;
            total += amount;
            const nca = {
                id: ++id, fiscal_year: fy, reference_number: `NCA-${fy}-${pad(++ncaSeq)}`, name: `${sub[1]} — release ${i + 1}`,
                amount, unreleased: rnd() < 0.45 ? money(0.5e6, amount * 0.3) : 0, date: iso(fy, 4 + i * 2, 1 + Math.floor(rnd() * 27)),
                tracking_type: TYPE.NCA, nature_of_fund: saro.nature_of_fund, sub_program: saro.sub_program, agency: null, remarks: null,
                parent: [{ id: saro.id, reference_number: saro.reference_number, name: saro.name, amount: saro.amount }], children: [],
            };
            ncas.push(nca); saro.children.push(nca);
        }
        saro.unreleased = Math.round((saro.amount - total) * 100) / 100;
        saros.push(saro);
    }));

    const AGENCIES = ['Department of Public Works and Highways', 'Quezon City Government', 'Department of Social Welfare and Development', 'Provincial Government of Leyte', 'Bureau of Fire Protection', 'Cebu City Government'];
    const REMARKS = ['Counterpart fund for site development works', 'Downloaded per MOA, first tranche', 'For relocation of informal settler families', 'Funds for road and drainage works', 'Remaining balance to be downloaded upon completion of Phase 1 of the housing project', null];
    for (let i = 0; i < 16; i++) {
        const fy = pick(YEARS), pg = pick(PROGRAMS), sub = pick(pg.subs), parentRef = pick(['OR', 'CA', 'SARO']);
        trusts.push({
            id: ++id, fiscal_year: fy, reference_number: `TF-${fy}-${pad(++trustSeq)}`, name: null, amount: money(5e6, 120e6), unreleased: 0,
            date: iso(fy, 1 + Math.floor(rnd() * 11), 1 + Math.floor(rnd() * 27)), tracking_type: TYPE.TRUST, nature_of_fund: { name: 'Trust Fund' },
            sub_program: subProgram(pg, sub), agency: { name: pick(AGENCIES) }, remarks: pick(REMARKS),
            parent: [{ id: ++id, reference_number: `${parentRef}-${fy}-${pad(Math.floor(rnd() * 900) + 100)}`, amount: money(100e6, 400e6) }], children: [],
        });
    }

    const PURPOSES = ['Supplemental budget for typhoon-damaged housing units', 'Additional funding — slum upgrading phase 2', 'Augmentation for resettlement site development', 'Emergency shelter assistance, additional tranche'];
    for (let i = 0; i < 9; i++) {
        const parent = pick(saros);
        supps.push({
            id: ++id, fiscal_year: parent.fiscal_year, reference_number: `SUP-${parent.fiscal_year}-${pad(++suppSeq)}`, name: null, amount: money(8e6, 90e6), unreleased: 0,
            date: iso(parent.fiscal_year, 6 + Math.floor(rnd() * 6), 1 + Math.floor(rnd() * 27)), tracking_type: TYPE.SARO, nature_of_fund: { name: 'SUPPLEMENTAL' },
            sub_program: parent.sub_program, agency: null, remarks: null, purpose: pick(PURPOSES),
            parent: [{ id: parent.id, reference_number: parent.reference_number, amount: parent.amount }], children: [],
        });
    }

    // Fund transfers — not filtered by fiscal year / program. Pending ones past due_date are "overdue" payables.
    const today = new Date(), shift = days => { const d = new Date(today); d.setDate(d.getDate() + days); return d.toISOString().slice(0, 10); };
    const FUNDS = ['Regular Agency Fund', 'Trust Fund 101', 'Special Account — Housing', 'Revolving Fund', 'Calamity Fund'];
    const PAYEES = ['NHA Region IV-A', 'NHA Region VII', 'NHA National Capital Region', 'NHA Region VIII', 'NHA Region XI'];
    const transfers = [];
    for (let i = 0; i < 12; i++) {
        const completed = rnd() < 0.5, from = pick(FUNDS);
        const due = completed ? null : shift(Math.floor(between(-60, 90)));
        transfers.push({
            id: ++id, ftla_no: `FTLA-${today.getFullYear()}-${pad(i + 1)}`, transfer_date: shift(-Math.floor(between(5, 220))),
            from, to: pick(FUNDS.filter(f => f !== from)), payee: pick(PAYEES), amount: money(2e6, 150e6),
            purpose: pick(['Cash flow support for ongoing housing projects', 'Temporary borrowing — payroll of project personnel', 'Advance for site acquisition', 'Bridge funding pending NCA release']),
            due_date: due, status: completed ? 'Completed' : (due < shift(0) ? 'Overdue' : 'Pending'),
        });
    }
    transfers.sort((a, b) => (a.transfer_date < b.transfer_date ? 1 : -1));

    const inScope = p => r =>
        (!p.fiscal_year || String(r.fiscal_year) === String(p.fiscal_year)) &&
        (!p.program_code || r.sub_program?.program_code === p.program_code);

    const paginate = (rows, p) => {
        const limit = Math.max(1, parseInt(p.limit) || 10), page = Math.max(1, parseInt(p.page) || 1);
        return {
            data: rows.slice((page - 1) * limit, page * limit),
            pagination: { total: rows.length, per_page: limit, current_page: page, last_page: Math.ceil(rows.length / limit), total_amount: sum(rows) },
        };
    };
    const newestFirst = rows => [...rows].sort((a, b) => ((a.date || a.transfer_date) < (b.date || b.transfer_date) ? 1 : -1));
    const wait = () => new Promise(r => setTimeout(r, 250 + Math.random() * 250));    // lets the loading states show

    const handlers = {
        dashboard(p) {
            const S = saros.filter(inScope(p)), N = ncas.filter(inScope(p)), T = trusts.filter(inScope(p)), P = supps.filter(inScope(p));
            const open = transfers.filter(t => t.status !== 'Completed'), done = transfers.filter(t => t.status === 'Completed');
            return {
                success: true,
                data: {
                    government_funds: {
                        saro_allocation: sum(S), saro_unreleased: sum(S, 'unreleased'), saro_record_count: S.length,
                        nca_received: sum(N), nca_unreleased: sum(N, 'unreleased'), nca_record_count: N.length,
                        available_funds: sum(S) - sum(N),
                    },
                    special_funds: {
                        trust_funds: sum(T), trust_funds_record_count: T.length,
                        supplemental_funds: sum(P), supplemental_funds_record_count: P.length,
                    },
                    // the real FIMS payload does not carry this yet — the page copes when it is missing
                    cross_access_funds: {
                        payable_amount: sum(open), payable_count: open.length, payable_overdue_count: open.filter(t => t.status === 'Overdue').length,
                        receivable_amount: sum(done), receivable_count: done.length,
                    },
                },
                filters: { fiscal_year: p.fiscal_year || null, program_code: p.program_code || null },
            };
        },
        list(p) {
            const nature = String(p.nature_of_fund || '').toUpperCase();
            const source = nature === 'SUPPLEMENTAL' ? supps : ({ SARO: saros, NCA: ncas, TRUST_FUND: trusts }[String(p.type || '').toUpperCase()] || []);
            let rows = source.filter(inScope(p));
            const q = String(p.agency_or_program_search || '').trim().toLowerCase();
            if (q) rows = rows.filter(r => [r.agency?.name, r.sub_program?.program_name, r.sub_program?.program_code, r.sub_program?.sub_program_name].some(v => String(v || '').toLowerCase().includes(q)));
            return { success: true, ...paginate(newestFirst(rows), p) };
        },
        fundTransfers(p) {
            const q = String(p.search || '').trim().toLowerCase();
            const rows = q ? transfers.filter(t => [t.ftla_no, t.purpose, t.payee, t.from, t.to].some(v => String(v).toLowerCase().includes(q))) : transfers;
            return { success: true, ...paginate(rows, p) };
        },
        fiscalYears() { return { success: true, data: YEARS.map(y => ({ fiscal_yr: y })) }; },
        programs() { return { success: true, data: PROGRAMS.map(({ program_code, program_name }) => ({ program_code, program_name })) }; },
    };

    return {
        async respond(key, params) {
            await wait();
            if (!handlers[key]) throw new Error('No sample data for "' + key + '"');
            return JSON.parse(JSON.stringify(handlers[key](params)));      // a copy, like a real response
        },
    };
})();

/* ── Boot ───────────────────────────────────────────────────── */
if (FSD_USE_MOCK) $f('fsd-note').style.display = 'flex';
fsd_renderOverview();
fsd_loadFilterOptions();
fsd_loadDashboard();
</script>
