<?php
/**
 * ftla-readone.php
 * Self-contained detail modal for FTLA records.
 * Include at the bottom of FtlaPrepation.php.
 * Trigger with: ftla_ro_open(fundTransferId)
 *
 * JS namespace : ftla_ro_
 * HTML ID prefix: ftro-
 */
namespace PHPMaker2024\FIMS;
$_ftro_api = $apiBase ?? Config('API.FIMS_URL');
?>

<style>
    /* ── Modal shell ────────────────────────────────────────────── */
    #ftro-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9100;
        background: rgba(15, 32, 69, .6);
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(3px);
    }

    #ftro-card {
        background: var(--afims-surface, #FFFFFF);
        border-radius: 16px;
        width: 94vw;
        max-width: 960px;
        max-height: 92vh;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        box-shadow: 0 32px 80px rgba(0, 0, 0, .28);
        font-family: 'IBM Plex Sans', sans-serif;
        animation: ftroIn .2s ease;
    }

    @keyframes ftroIn {
        from { opacity: 0; transform: translateY(18px) scale(.98); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* ── Header ─────────────────────────────────────────────────── */
    #ftro-hdr {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 20px;
        background: var(--afims-heading-fill, #000E3E);
        flex-shrink: 0;
    }

    #ftro-hdr-l { display: flex; align-items: center; gap: 10px; }

    #ftro-hdr-l svg {
        width: 17px; height: 17px;
        stroke: var(--afims-text-disabled, #A9AAB8); stroke-width: 1.5; flex-shrink: 0;
    }

    .ftro-hdr-tag { font-size: 10px; color: var(--afims-text-disabled, #A9AAB8); margin: 0; letter-spacing: .05em; text-transform: uppercase; }
    .ftro-hdr-num { font-size: 13px; font-weight: 700; color: var(--afims-pure-white, #FFFFFF); margin: 0; font-family: 'IBM Plex Mono', monospace; }

    #ftro-hdr-r { display: flex; align-items: center; gap: 8px; }

    .ftro-hdr-btn {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 11px; font-weight: 600; padding: 5px 12px;
        border-radius: 8px; cursor: pointer;
        font-family: 'IBM Plex Sans', sans-serif;
        transition: background .15s, color .15s; text-decoration: none;
    }
    .ftro-hdr-btn-ghost {
        color: var(--afims-text-disabled, #A9AAB8); background: transparent;
        border: 1px solid rgba(255, 255, 255, .15);
    }
    .ftro-hdr-btn-ghost:hover { background: color-mix(in srgb, var(--afims-surface, #FFFFFF) 10%, transparent); color: var(--afims-pure-white, #FFFFFF); }

    .ftro-hdr-close {
        border: none; background: color-mix(in srgb, var(--afims-surface, #FFFFFF) 10%, transparent); color: var(--afims-text-disabled, #A9AAB8);
        width: 30px; height: 30px; border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px; cursor: pointer;
    }
    .ftro-hdr-close:hover { background: color-mix(in srgb, var(--afims-surface, #FFFFFF) 20%, transparent); color: var(--afims-pure-white, #FFFFFF); }

    /* ── Tab bar ────────────────────────────────────────────────── */
    #ftro-tabs {
        display: flex;
        padding: 0 20px;
        background: var(--afims-surface, #FFFFFF);
        border-bottom: 1px solid var(--afims-border, #E4ECF2);
        flex-shrink: 0;
    }

    .ftro-tab {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 11px 16px; font-size: 12px; font-weight: 600;
        color: var(--afims-text-disabled, #A9AAB8); background: none; border: none;
        border-bottom: 2px solid transparent; cursor: pointer;
        font-family: 'IBM Plex Sans', sans-serif;
        transition: color .15s, border-color .15s;
        margin-bottom: -1px; white-space: nowrap;
    }
    .ftro-tab:hover { color: var(--afims-text-muted, #62657E); }
    .ftro-tab.active { color: var(--afims-heading, #000E3E); border-bottom-color: var(--afims-heading-fill, #000E3E); }
    .ftro-tab svg { width: 13px; height: 13px; stroke: currentColor; stroke-width: 1.75; flex-shrink: 0; }

    .ftro-tab-panel { display: none; }
    .ftro-tab-panel.active { display: block; }

    /* ── Body ───────────────────────────────────────────────────── */
    #ftro-body {
        flex: 1; overflow-y: auto; padding: 20px 24px;
        scrollbar-width: thin; scrollbar-color: var(--afims-border, #E4ECF2) transparent;
    }

    /* ── States ─────────────────────────────────────────────────── */
    #ftro-loading {
        display: flex; align-items: center; justify-content: center;
        padding: 60px 0; flex-direction: column; gap: 12px;
        color: var(--afims-text-disabled, #A9AAB8); font-size: 13px;
    }

    #ftro-error {
        display: none; align-items: center; gap: 10px;
        background: var(--afims-danger-soft, #FAE1E3); border: 1px solid var(--afims-danger-soft, #FAE1E3);
        border-radius: 10px; padding: 14px 18px;
        font-size: 13px; color: var(--afims-danger, #DC3545); margin-bottom: 16px;
    }

    #ftro-error svg {
        width: 18px; height: 18px;
        stroke: var(--afims-danger, #DC3545); stroke-width: 2; flex-shrink: 0;
    }

    #ftro-content { display: none; }

    /* ── Scoped layout primitives ───────────────────────────────── */
    #ftro-card .ftro-sec {
        display: flex; align-items: center; gap: 10px;
        font-size: 10px; font-weight: 700; color: var(--afims-text-disabled, #A9AAB8);
        text-transform: uppercase; letter-spacing: .06em;
        margin: 18px 0 10px;
    }
    #ftro-card .ftro-sec:first-child { margin-top: 4px; }

    #ftro-card .ftro-sec span:first-child {
        color: var(--afims-text, #1A1B21);
        font-size: 12px;
    }

    #ftro-card .ftro-sec-line { flex: 1; height: 1px; background: var(--afims-border, #E4ECF2); }

    #ftro-card .ftro-box {
        background: var(--afims-surface, #FFFFFF);
        border: 0.5px solid var(--afims-border, #E4ECF2);
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 16px;
    }

    #ftro-card .ftro-box-hdr {
        display: flex; align-items: center; justify-content: space-between;
        padding: 12px 18px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA);
    }

    #ftro-card .ftro-box-title { font-size: 13px; font-weight: 600; color: var(--afims-text, #1A1B21); margin: 0; }
    #ftro-card .ftro-box-sub   { font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); margin: 0; }
    #ftro-card .ftro-box-body  { padding: 16px 18px; }

    /* ── Field grid ─────────────────────────────────────────────── */
    #ftro-card .ftro-grid { display: grid; gap: 14px 22px; }
    #ftro-card .ftro-g2   { grid-template-columns: repeat(2, 1fr); }
    #ftro-card .ftro-g3   { grid-template-columns: repeat(3, 1fr); }
    #ftro-card .ftro-s2   { grid-column: span 2; }
    #ftro-card .ftro-s3   { grid-column: span 3; }

    #ftro-card .ftro-field { display: flex; flex-direction: column; gap: 3px; }

    #ftro-card .ftro-lbl {
        font-size: 9.5px; font-weight: 700;
        text-transform: uppercase; letter-spacing: .06em; color: var(--afims-text-disabled, #A9AAB8);
    }

    #ftro-card .ftro-val {
        font-size: 12.5px; font-weight: 500; color: var(--afims-text, #1A1B21);
        min-height: 18px; line-height: 1.5;
    }

    #ftro-card .ftro-val.mono { font-family: 'IBM Plex Mono', monospace; font-size: 11.5px; }

    #ftro-card .ftro-hr { border: none; border-top: 1px solid var(--afims-surface-subtle, #F4F7FA); margin: 14px 0; }

    /* ── Amount card ────────────────────────────────────────────── */
    #ftro-card .ftro-amt-box {
        background: var(--afims-heading-fill, #000E3E);
        border-radius: 12px; padding: 18px 22px; margin-bottom: 16px;
        display: flex; align-items: center; justify-content: space-between;
    }
    #ftro-card .ftro-amt-label { font-size: 10px; color: var(--afims-text-disabled, #A9AAB8); text-transform: uppercase; letter-spacing: .06em; }
    #ftro-card .ftro-amt-val {
        font-size: 1.6rem; font-weight: 700;
        font-family: 'IBM Plex Mono', monospace; color: var(--afims-pure-white, #FFFFFF); margin-top: 4px;
    }

    /* ── Approval timeline ──────────────────────────────────────── */
    #ftro-card .ftro-tl-item { display: flex; gap: 12px; padding-bottom: 16px; }
    #ftro-card .ftro-tl-item:last-child { padding-bottom: 0; }
    #ftro-card .ftro-tl-item:last-child .ftro-tl-line { display: none; }

    #ftro-card .ftro-tl-dotw {
        display: flex; flex-direction: column; align-items: center;
        flex-shrink: 0; width: 26px;
    }

    #ftro-card .ftro-tl-dot {
        width: 26px; height: 26px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    #ftro-card .ftro-tl-dot svg { width: 12px; height: 12px; }

    #ftro-card .ftro-tl-line {
        width: 2px; flex: 1; background: var(--afims-border, #E4ECF2); margin-top: 4px;
    }

    #ftro-card .ftro-tl-body { flex: 1; padding-top: 2px; }

    #ftro-card .ftro-tl-stage { font-size: 12px; font-weight: 600; margin: 0 0 2px; }
    #ftro-card .ftro-tl-meta  { font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); margin: 0; }
    #ftro-card .ftro-tl-ts    { font-size: 10px; color: var(--afims-text-muted, #62657E); margin: 2px 0 0; }
    #ftro-card .ftro-tl-actor { font-size: 11px; color: var(--afims-text-muted, #62657E); margin: 2px 0 0; font-weight: 500; }

    .ftro-spinner {
        width: 20px; height: 20px; flex-shrink: 0;
        border: 2px solid var(--afims-border, #E4ECF2); border-top-color: var(--afims-heading-fill, #000E3E);
        border-radius: 50%; animation: ftroSpin .6s linear infinite;
    }
    @keyframes ftroSpin { to { transform: rotate(360deg); } }

    @keyframes admPulse {
        0%, 100% { box-shadow: 0 0 0 3px rgba(245,158,11,.25); }
        50%       { box-shadow: 0 0 0 6px rgba(245,158,11,.10); }
    }

    /* ── Documents tab ──────────────────────────────────────────── */
    #ftro-card .ftro-docs-header {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 16px;
    }
    #ftro-card .ftro-docs-title {
        font-size: 13px; font-weight: 700; color: var(--afims-text, #1A1B21);
    }
    #ftro-card .ftro-docs-count {
        font-size: 11px; font-weight: 600; color: var(--afims-text-muted, #62657E);
        background: var(--afims-surface-subtle, #F4F7FA); border: 1px solid var(--afims-border, #E4ECF2);
        border-radius: 20px; padding: 2px 10px;
    }
    #ftro-card .ftro-att-table {
        width: 100%; border-collapse: collapse;
        border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; overflow: hidden;
    }
    #ftro-card .ftro-att-table thead tr {
        background: var(--afims-surface-subtle, #F4F7FA); border-bottom: 1px solid var(--afims-border, #E4ECF2);
    }
    #ftro-card .ftro-att-table th {
        text-align: left; padding: 9px 14px;
        font-size: 10px; font-weight: 700; color: var(--afims-text-muted, #62657E);
        text-transform: uppercase; letter-spacing: .06em;
    }
    #ftro-card .ftro-att-table tbody tr {
        border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); transition: background .1s;
    }
    #ftro-card .ftro-att-table tbody tr:last-child { border-bottom: none; }
    #ftro-card .ftro-att-table tbody tr:hover { background: var(--afims-surface-subtle, #F4F7FA); }
    #ftro-card .ftro-att-table td {
        padding: 10px 14px; font-size: 12px; color: var(--afims-text, #1A1B21); vertical-align: middle;
    }
    #ftro-card .ftro-att-icon {
        width: 28px; height: 28px; border-radius: 6px;
        background: var(--afims-primary-soft, #E6EBF6); border: 1px solid var(--afims-primary-soft, #E6EBF6);
        display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    #ftro-card .ftro-att-icon svg { width: 14px; height: 14px; stroke: var(--afims-primary, #0035A9); stroke-width: 1.75; }
    #ftro-card .ftro-att-icon.validated { background: var(--afims-success-soft, #DBF2E5); border-color: var(--afims-success-soft-hover, #C4E9D4); }
    #ftro-card .ftro-att-icon.validated svg { stroke: var(--afims-success, #11A752); }
    #ftro-card .ftro-att-doc-name { font-weight: 600; color: var(--afims-text, #1A1B21); font-size: 12.5px; }
    #ftro-card .ftro-att-link { color: var(--afims-text-disabled, #A9AAB8); font-size: 11px; margin-top: 1px; word-break: break-all; }
    #ftro-card .ftro-att-open {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 5px 12px; border-radius: 6px; font-size: 11.5px; font-weight: 600;
        color: var(--afims-heading, #000E3E); background: var(--afims-surface, #FFFFFF); border: 1px solid var(--afims-border, #E4ECF2);
        text-decoration: none; white-space: nowrap; transition: all .15s;
    }
    #ftro-card .ftro-att-open:hover { background: var(--afims-surface-subtle, #F4F7FA); border-color: var(--afims-border, #E4ECF2); color: var(--afims-heading, #000E3E); }

    /* ── Workflow tab section heading overrides (match prd-sec-hd) ── */
    #ftro-panel-2 .ftro-sec { margin: 0 0 14px; }
    #ftro-panel-2 .ftro-sec span:first-child { font-size: 11px; color: var(--afims-heading, #000E3E); white-space: nowrap; }

    /* ── 2-col split ────────────────────────────────────────────── */
    #ftro-card .ftro-split {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    @media (max-width: 700px) {
        #ftro-card .ftro-g3  { grid-template-columns: repeat(2, 1fr); }
        #ftro-card .ftro-s3  { grid-column: span 2; }
        #ftro-card .ftro-split { grid-template-columns: 1fr; }
    }

    #ftro-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 22px;
        background: var(--afims-surface-subtle, #F4F7FA);
        border-top: 1px solid var(--afims-border, #E4ECF2);
        flex-shrink: 0;
        font-size: 11px;
        color: var(--afims-text-disabled, #A9AAB8);
    }
</style>

<!-- READONE OVERLAY -->
<div id="ftro-overlay">
    <div id="ftro-card">

        <!-- Header -->
        <div id="ftro-hdr">
            <div id="ftro-hdr-l">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <rect x="2" y="5" width="20" height="14" rx="2"/>
                    <path d="M2 10h20M7 15h2M11 15h4"/>
                </svg>
                <div>
                    <p class="ftro-hdr-tag">Fund Transfer Letter of Advice</p>
                    <p class="ftro-hdr-num" id="ftro-hdr-num">Loading…</p>
                </div>
            </div>
            <div id="ftro-hdr-r">
                <button class="ftro-hdr-btn ftro-hdr-btn-ghost" id="ftro-print-btn"
                        onclick="ftro_printDocx()" style="display:none;">
                    <svg fill="none" viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;stroke-width:2;">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002
                             2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002
                             2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Preview &amp; Print
                </button>
                <button class="ftro-hdr-close" onclick="ftla_ro_close()" title="Close">&times;</button>
            </div>
        </div>

        <!-- Tab bar -->
        <div id="ftro-tabs">
            <button class="ftro-tab active" data-tab="0" onclick="ftla_ro_tab(0)">
                <svg fill="none" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Details
            </button>
            <button class="ftro-tab" data-tab="1" onclick="ftla_ro_tab(1)">
                <svg fill="none" viewBox="0 0 24 24"><path d="M9 17v-6m4 6V7m4 10v-3M5 21h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                Utilization
            </button>
            <button class="ftro-tab" data-tab="2" onclick="ftla_ro_tab(2)">
                <svg fill="none" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Workflow
            </button>
            <button class="ftro-tab" data-tab="3" onclick="ftla_ro_tab(3)">
                <svg fill="none" viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                Documents
            </button>
        </div>

        <!-- Body -->
        <div id="ftro-body">

            <div id="ftro-loading">
                <div class="nha-spinner-sm"></div>
                <span>Loading…</span>
            </div>

            <div id="ftro-error">
                <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <span id="ftro-error-msg">Failed to load record.</span>
            </div>

            <div id="ftro-content">

                <!-- ══════════════════════════════════════════
                     TAB 0 — Details
                ══════════════════════════════════════════ -->
                <div class="ftro-tab-panel active" id="ftro-panel-0">

                    <div class="ftro-amt-box">
                        <div>
                            <div class="ftro-amt-label">Transfer Amount</div>
                            <div class="ftro-amt-val" id="ftro-amount">—</div>
                        </div>
                        <div style="text-align:right">
                            <div id="ftro-status-badge"></div>
                            <div style="font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin-top:6px" id="ftro-type-label"></div>
                        </div>
                    </div>

                    <div class="ftro-sec">
                        <span>Transfer Information</span>
                        <span class="ftro-sec-line"></span>
                    </div>
                    <div class="ftro-box">
                        <div class="ftro-box-hdr">
                            <div>
                                <p class="ftro-box-title" id="ftro-ftla-number">—</p>
                                <p class="ftro-box-sub">Transfer date, type, and repayment details</p>
                            </div>
                        </div>
                        <div class="ftro-box-body">
                            <div class="ftro-grid ftro-g3">
                                <div class="ftro-field">
                                    <span class="ftro-lbl">Transfer Date</span>
                                    <span class="ftro-val" id="ftro-transfer-date">—</span>
                                </div>
                                <div class="ftro-field">
                                    <span class="ftro-lbl">Transfer Type</span>
                                    <span class="ftro-val" id="ftro-transfer-type">—</span>
                                </div>
                                <div class="ftro-field">
                                    <span class="ftro-lbl">Temporary?</span>
                                    <span class="ftro-val" id="ftro-is-temporary">—</span>
                                </div>
                                <div class="ftro-field ftro-s3" id="ftro-repayment-wrap" style="display:none">
                                    <span class="ftro-lbl">Repayment Due Date</span>
                                    <span class="ftro-val" id="ftro-repayment-date">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ftro-sec">
                        <span>Memorandum</span>
                        <span class="ftro-sec-line"></span>
                    </div>
                    <div class="ftro-box">
                        <div class="ftro-box-body">
                            <div class="ftro-grid ftro-g2">
                                <div class="ftro-field">
                                    <span class="ftro-lbl">For</span>
                                    <span class="ftro-val" id="ftro-for-name">—</span>
                                    <span class="ftro-val" id="ftro-for-title" style="color:var(--afims-text-muted, #62657E);"></span>
                                </div>
                                <div class="ftro-field" id="ftro-thru-wrap">
                                    <span class="ftro-lbl">Thru</span>
                                    <span class="ftro-val" id="ftro-thru-name">—</span>
                                    <span class="ftro-val" id="ftro-thru-title" style="color:var(--afims-text-muted, #62657E);"></span>
                                </div>
                                <div class="ftro-field ftro-s2">
                                    <span class="ftro-lbl">Subject</span>
                                    <span class="ftro-val" id="ftro-subject" style="white-space:pre-line">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ftro-sec">
                        <span>Bank Accounts</span>
                        <span class="ftro-sec-line"></span>
                    </div>
                    <div class="ftro-box">
                        <div class="ftro-box-hdr">
                            <div>
                                <p class="ftro-box-title">Destination Bank Account</p>
                                <p class="ftro-box-sub">Where the requested funds should be credited</p>
                            </div>
                        </div>
                        <div class="ftro-box-body">
                            <div class="ftro-grid ftro-g3">
                                <div class="ftro-field ftro-s2">
                                    <span class="ftro-lbl">Bank Name</span>
                                    <span class="ftro-val" id="ftro-from-bank-name">—</span>
                                </div>
                                <div class="ftro-field">
                                    <span class="ftro-lbl">Account Number</span>
                                    <span class="ftro-val mono" id="ftro-from-acct-number">—</span>
                                </div>
                                <div class="ftro-field ftro-s3">
                                    <span class="ftro-lbl">Account Name</span>
                                    <span class="ftro-val" id="ftro-from-acct-name">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ftro-box" id="ftro-source-box" style="display:none;">
                        <div class="ftro-box-hdr">
                            <div>
                                <p class="ftro-box-title" id="ftro-source-title">Source / Debit Account(s)</p>
                                <p class="ftro-box-sub" id="ftro-source-sub">Where the transferred funds are being debited from</p>
                            </div>
                        </div>
                        <div class="ftro-box-body">
                            <table class="ftro-att-table" style="width:100%;border-collapse:collapse;" id="ftro-source-table"></table>
                        </div>
                    </div>

                    <div class="ftro-sec">
                        <span>Purpose &amp; References</span>
                        <span class="ftro-sec-line"></span>
                    </div>
                    <div class="ftro-box">
                        <div class="ftro-box-body">
                            <div class="ftro-grid ftro-g3">
                                <div class="ftro-field ftro-s3">
                                    <span class="ftro-lbl">Purpose / Particulars</span>
                                    <span class="ftro-val" id="ftro-purpose" style="white-space:pre-line">—</span>
                                </div>
                                <div class="ftro-field ftro-s3" id="ftro-dv-wrap">
                                    <span class="ftro-lbl">DV Numbers</span>
                                    <span class="ftro-val mono" id="ftro-dv-numbers" style="white-space:pre-line">—</span>
                                </div>
                                <div class="ftro-field ftro-s3" id="ftro-ref-wrap">
                                    <span class="ftro-lbl">Request Reference</span>
                                    <span class="ftro-val" id="ftro-request-ref">—</span>
                                </div>
                                <div class="ftro-field ftro-s3" id="ftro-remarks-wrap">
                                    <span class="ftro-lbl">Remarks</span>
                                    <span class="ftro-val" id="ftro-remarks" style="white-space:pre-line">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ftro-sec">
                        <span>Funding Reference &amp; Signatory</span>
                        <span class="ftro-sec-line"></span>
                    </div>
                    <div class="ftro-box">
                        <div class="ftro-box-body">
                            <div class="ftro-grid ftro-g3">
                                <div class="ftro-field" id="ftro-laa-wrap">
                                    <span class="ftro-lbl">LAA Number</span>
                                    <span class="ftro-val mono" id="ftro-laa-number">—</span>
                                </div>
                                <div class="ftro-field" id="ftro-cy-wrap">
                                    <span class="ftro-lbl">CY (Fiscal Year)</span>
                                    <span class="ftro-val" id="ftro-fiscal-year">—</span>
                                </div>
                                <div class="ftro-field" id="ftro-rfa-wrap">
                                    <span class="ftro-lbl">RFA Number</span>
                                    <span class="ftro-val mono" id="ftro-rfa-number">—</span>
                                </div>
                                <div class="ftro-field" id="ftro-rfa-date-wrap">
                                    <span class="ftro-lbl">RFA Date</span>
                                    <span class="ftro-val" id="ftro-rfa-date">—</span>
                                </div>
                                <div class="ftro-field ftro-s2">
                                    <span class="ftro-lbl">Signatory</span>
                                    <span class="ftro-val" id="ftro-signatory-name">—</span>
                                    <span class="ftro-val" id="ftro-signatory-title" style="color:var(--afims-text-muted, #62657E);"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div><!-- /panel-0 -->

                <!-- ══════════════════════════════════════════
                     TAB 1 — Utilization
                ══════════════════════════════════════════ -->
                <div class="ftro-tab-panel" id="ftro-panel-1">

                    <div class="ftro-sec">
                        <span>DV Utilization</span>
                        <span class="ftro-sec-line"></span>
                    </div>
                    <div class="ftro-box">
                        <div class="ftro-box-body">
                            <div class="ftro-grid ftro-g3">
                                <div class="ftro-field">
                                    <span class="ftro-lbl">Transfer Amount</span>
                                    <span class="ftro-val mono" id="ftro-util-transfer-amount">—</span>
                                </div>
                                <div class="ftro-field">
                                    <span class="ftro-lbl">Total DV Amount</span>
                                    <span class="ftro-val mono" id="ftro-util-dv-total">—</span>
                                </div>
                                <div class="ftro-field">
                                    <span class="ftro-lbl">Variance</span>
                                    <span class="ftro-val mono" id="ftro-util-variance">—</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="ftro-sec">
                        <span>Connected DVs</span>
                        <span class="ftro-sec-line"></span>
                    </div>
                    <div id="ftro-util-table-wrap"></div>

                </div><!-- /panel-1 -->

                <!-- ══════════════════════════════════════════
                     TAB 2 — Workflow
                ══════════════════════════════════════════ -->
                <div class="ftro-tab-panel" id="ftro-panel-2">

                    <div class="ftro-split">

                        <!-- Approval Timeline -->
                        <div>
                            <div class="ftro-sec">
                                <span>Approval Chain</span>
                                <span class="ftro-sec-line"></span>
                            </div>
                            <div class="ftro-box" style="height:calc(100% - 38px);box-sizing:border-box;">
                                <div class="ftro-box-hdr">
                                    <div>
                                        <p class="ftro-box-title">Workflow Progress</p>
                                        <p class="ftro-box-sub" id="ftro-wf-subtitle">—</p>
                                    </div>
                                </div>
                                <div class="ftro-box-body">
                                    <div id="ftro-timeline"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Audit Trail -->
                        <div>
                            <div class="ftro-sec">
                                <span>Record Metadata</span>
                                <span class="ftro-sec-line"></span>
                            </div>
                            <div class="ftro-box">
                                <div class="ftro-box-hdr">
                                    <div>
                                        <p class="ftro-box-title">Audit Trail</p>
                                        <p class="ftro-box-sub">Chronological action history</p>
                                    </div>
                                </div>
                                <div class="ftro-box-body">
                                    <div id="ftro-audit-trail"></div>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.ftro-split -->

                </div><!-- /panel-2 -->

                <!-- ══════════════════════════════════════════
                     TAB 3 — Documents
                ══════════════════════════════════════════ -->
                <div class="ftro-tab-panel" id="ftro-panel-3">
                    <div id="ftro-docs-list" style="padding-top:4px"></div>
                </div>

            </div><!-- /#ftro-content -->
        </div><!-- /#ftro-body -->

        <div id="ftro-foot">
            <span id="ftro-foot-txt">NHA-FIMS · Regional Operations · Fund Transfer Letter of Advice</span>
        </div>

    </div><!-- /#ftro-card -->
</div><!-- /#ftro-overlay -->

<script>
(function () {
    const FTRO_API   = '<?php echo $_ftro_api; ?>';
    const FTRO_TOKEN = () => window.ew?.API_JWT_TOKEN ?? '';

    const ftro$     = id => document.getElementById(id);
    const ftro_esc  = s  => { const d = document.createElement('span'); d.textContent = String(s ?? ''); return d.innerHTML; };
    const ftro_date = v  => v ? new Date(v).toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' }) : null;
    const ftro_money = v => 'PHP ' + parseFloat(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const TYPE_LABEL = {
        REGIONAL_FUND_TRANSFER:  'Regional Fund Transfer',
        INTER_ACCOUNT:           'Inter-Account Transfer',
        ERRONEOUS_CHARGEABILITY: 'Erroneous Chargeability',
        ERRONEOUS_DEPOSIT:       'Erroneous Deposit',
        REMITTANCE_ECHMLP:       'Remittance - ECHMLP',
        REMITTANCE_HLA:          'Remittance - HLA',
        SUBSIDY_TRANSFER:        'Subsidy Transfer',
        REVERSAL:                'Reversal',
        GENERAL_FUND:            'General Fund',
        TEMPORARY:               'Temporary',
        CROSS_ACCESS:            'Cross Access',
        POOL_MOVEMENT:           'Pool Movement',
        REVERSION:               'Reversion',
        SETTLEMENT_TO_MAIN:      'Settlement to Main Depository',
    };

    const STATUS_MAP = {
        DRAFT:                   ['nha-bdg-gray',   'Draft'],
        FOR_RM_APPROVAL:         ['nha-bdg-blue',   'For RM Approval'],
        FOR_REVIEW:              ['nha-bdg-amber',  'For Review'],
        FOR_APPROVAL:            ['nha-bdg-indigo', 'For Approval'],
        APPROVED:                ['nha-bdg-blue',   'Approved'],
        TRANSMITTED:             ['nha-bdg-orange', 'Transmitted'],
        VALIDATED:               ['nha-bdg-purple', 'Validated'],
        SUBMITTED_TO_ACCOUNTING: ['nha-bdg-teal',   'Submitted to Accounting'],
        COMPLETED:               ['nha-bdg-green',  'Completed'],
        CANCELLED:               ['nha-bdg-red',    'Cancelled'],
    };

    const STEP_COLORS = ['#475569', '#0284c7', '#0369a1', '#059669', '#7c3aed', '#b45309', '#0f2045', '#065f46'];

    let ftro_currentRecord = null;
    let ftro_wfLoaded      = false;
    let ftro_utilLoaded    = false;

    const UTIL_COLOR_MAP = {
        secondary: 'nha-bdg-gray', primary: 'nha-bdg-blue', info: 'nha-bdg-teal',
        success:   'nha-bdg-green', warning: 'nha-bdg-amber', danger: 'nha-bdg-red',
    };

    function ftro_statusBadge(s) {
        const [cls, label] = STATUS_MAP[s] ?? ['nha-bdg-gray', s || '—'];
        return `<span class="nha-bdg ${cls}">${label}</span>`;
    }

    async function ftro_apiFetch(url, opts = {}) {
        const headers = { 'Content-Type': 'application/json' };
        if (FTRO_TOKEN()) headers['Authorization'] = 'Bearer ' + FTRO_TOKEN();
        const res = await fetch(url, { credentials: 'same-origin', headers, ...opts });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    window.ftla_ro_open = async function (id) {
        ftro$('ftro-overlay').style.display = 'flex';
        ftro$('ftro-loading').style.display = 'flex';
        ftro$('ftro-error').style.display   = 'none';
        ftro$('ftro-content').style.display = 'none';
        ftro$('ftro-hdr-num').textContent   = 'Loading…';
        const _printBtn = ftro$('ftro-print-btn');
        if (_printBtn) _printBtn.style.display = 'none';
        ftla_ro_tab(0);
        ftro$('ftro-body').scrollTop = 0;

        try {
            const json = await ftro_apiFetch(`${FTRO_API}/ftla/${id}`);
            if (!json.success) throw new Error(json.message || 'API error');
            ftro_populate(json.data);
        } catch (e) {
            ftro$('ftro-loading').style.display = 'none';
            ftro$('ftro-error').style.display   = 'flex';
            ftro$('ftro-error-msg').textContent = 'Could not load record: ' + e.message;
        }
    };

    window.ftla_ro_close = function () {
        ftro$('ftro-overlay').style.display = 'none';
        ftro_wfLoaded   = false;
        ftro_utilLoaded = false;
    };

    window.ftla_ro_tab = function (n) {
        document.querySelectorAll('#ftro-card .ftro-tab').forEach((t, i) => t.classList.toggle('active', i === n));
        document.querySelectorAll('#ftro-card .ftro-tab-panel').forEach((p, i) => p.classList.toggle('active', i === n));
        if (n === 2 && ftro_currentRecord && !ftro_wfLoaded)   ftro_loadWorkflowTab(ftro_currentRecord.fund_transfer_id);
        if (n === 1 && ftro_currentRecord && !ftro_utilLoaded) ftro_loadUtilization(ftro_currentRecord.fund_transfer_id);
    };

    function ftro_set(id, v) {
        const el = ftro$(id);
        if (el) el.textContent = (v != null && v !== '') ? v : '—';
    }

    function ftro_populate(d) {
        ftro_currentRecord = d;

        // Header
        ftro$('ftro-hdr-num').textContent = d.ftla_number || '—';

        // Amount card
        ftro$('ftro-amount').textContent     = ftro_money(d.transfer_amount);
        ftro$('ftro-status-badge').innerHTML = ftro_statusBadge(d.status);
        ftro$('ftro-type-label').textContent = TYPE_LABEL[d.transfer_type] || d.transfer_type || '—';

        // Transfer info
        ftro_set('ftro-ftla-number',   d.ftla_number);
        ftro_set('ftro-transfer-date', ftro_date(d.transfer_date));
        ftro_set('ftro-transfer-type', TYPE_LABEL[d.transfer_type] || d.transfer_type);
        ftro_set('ftro-is-temporary',  d.is_temporary ? 'Yes' : 'No');
        if (d.is_temporary && d.repayment_due_date) {
            ftro$('ftro-repayment-wrap').style.display = '';
            ftro_set('ftro-repayment-date', ftro_date(d.repayment_due_date));
        } else {
            ftro$('ftro-repayment-wrap').style.display = 'none';
        }

        // The picked account is the request's destination — stored as
        // to_bank_account_id, not from (see ftla-form.php's note).
        ftro_set('ftro-from-bank-name',    d.to_bank_name);
        ftro_set('ftro-from-acct-number',  d.to_account_number);
        ftro_set('ftro-from-acct-name',    d.to_account_name);

        // Source/debit account(s) — empty for a legacy record with no declared source.
        // Paired records (from_accounts[i] <-> to_accounts[i], Treasury's ftlafundtransfer.php
        // "pairs" shape) get their own Credit (To) column instead of the single shared destination.
        const srcBox   = ftro$('ftro-source-box');
        const srcTable = ftro$('ftro-source-table');
        const fromAccounts = Array.isArray(d.from_accounts) ? d.from_accounts : [];
        const toAccounts   = Array.isArray(d.to_accounts) ? d.to_accounts : [];
        const paired       = !!d.is_paired;
        if (srcBox && srcTable) {
            if (fromAccounts.length) {
                srcBox.style.display = '';
                ftro_set('ftro-source-title', paired ? 'Debit / Credit Account Pairs' : 'Source / Debit Account(s)');
                ftro_set('ftro-source-sub', paired
                    ? 'Each debit account is paired with its own credit account'
                    : 'Where the transferred funds are being debited from');

                const th = (label, align) => `<th style="text-align:${align || 'left'};padding:5px 8px;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);text-transform:uppercase;">${label}</th>`;
                const head = paired
                    ? '<thead><tr>' + th('Debit (From)') + th('Credit (To)') + th('Amount', 'right') + '</tr></thead>'
                    : '<thead><tr>' + th('Bank / Account') + th('Account No.') + th('Amount', 'right') + '</tr></thead>';

                const acctLabel = a => `${ftro_esc(a.bank_name || '—')}${a.account_name ? ' — ' + ftro_esc(a.account_name) : ''}`;
                const rows = paired
                    ? fromAccounts.map((fa, i) => {
                        const ta = toAccounts[i] || {};
                        return `<tr>
                            <td style="padding:6px 8px;">${acctLabel(fa)}<div class="mono" style="font-size:10.5px;color:var(--afims-text-muted, #62657E);">${ftro_esc(fa.account_number || '—')}</div></td>
                            <td style="padding:6px 8px;">${acctLabel(ta)}<div class="mono" style="font-size:10.5px;color:var(--afims-text-muted, #62657E);">${ftro_esc(ta.account_number || '—')}</div></td>
                            <td style="padding:6px 8px;text-align:right;" class="mono">${ftro_money(fa.amount)}</td>
                        </tr>`;
                    }).join('')
                    : fromAccounts.map(fa => `
                        <tr>
                            <td style="padding:6px 8px;">${acctLabel(fa)}</td>
                            <td style="padding:6px 8px;" class="mono">${ftro_esc(fa.account_number || '—')}</td>
                            <td style="padding:6px 8px;text-align:right;" class="mono">${ftro_money(fa.amount)}</td>
                        </tr>
                    `).join('');

                srcTable.innerHTML = head + '<tbody>' + rows + '</tbody>';
            } else {
                srcBox.style.display = 'none';
                srcTable.innerHTML = '';
            }
        }

        // Memorandum — for_name/for_title/thru_name/thru_title/subject are real
        // columns (see migration "16 - Alter fund_transfers_add_for_thru_subject.sql").
        ftro_set('ftro-for-name',  d.for_name);
        ftro_set('ftro-for-title', d.for_title);
        if (d.thru_name || d.thru_title) {
            ftro$('ftro-thru-wrap').style.display = '';
            ftro_set('ftro-thru-name',  d.thru_name);
            ftro_set('ftro-thru-title', d.thru_title);
        } else {
            ftro$('ftro-thru-wrap').style.display = 'none';
        }
        ftro_set('ftro-subject', d.subject);

        // Purpose & references
        ftro_set('ftro-purpose', d.purpose);
        if (d.dv_numbers?.trim()) {
            ftro$('ftro-dv-wrap').style.display = '';
            ftro_set('ftro-dv-numbers', d.dv_numbers.trim());
        } else {
            ftro$('ftro-dv-wrap').style.display = 'none';
        }
        if (d.request_reference) {
            ftro$('ftro-ref-wrap').style.display = '';
            ftro_set('ftro-request-ref', d.request_reference);
        } else {
            ftro$('ftro-ref-wrap').style.display = 'none';
        }
        if (d.remarks) {
            ftro$('ftro-remarks-wrap').style.display = '';
            ftro_set('ftro-remarks', d.remarks);
        } else {
            ftro$('ftro-remarks-wrap').style.display = 'none';
        }

        // Funding Reference & Signatory — laa_number/fiscal_year/rfa_number/
        // rfa_date still ride in the signatories jsonb blob; signatory name/
        // title are real columns (signatory_name_memo/signatory_position_memo).
        const sigsRaw = d.signatories || {};
        const sigs = typeof sigsRaw === 'string' ? (() => { try { return JSON.parse(sigsRaw); } catch(e) { return {}; } })() : sigsRaw;
        if (sigs.laa_number) {
            ftro$('ftro-laa-wrap').style.display = '';
            ftro_set('ftro-laa-number', sigs.laa_number);
        } else {
            ftro$('ftro-laa-wrap').style.display = 'none';
        }
        if (sigs.fiscal_year) {
            ftro$('ftro-cy-wrap').style.display = '';
            ftro_set('ftro-fiscal-year', sigs.fiscal_year);
        } else {
            ftro$('ftro-cy-wrap').style.display = 'none';
        }
        if (sigs.rfa_number) {
            ftro$('ftro-rfa-wrap').style.display = '';
            ftro_set('ftro-rfa-number', sigs.rfa_number);
        } else {
            ftro$('ftro-rfa-wrap').style.display = 'none';
        }
        if (sigs.rfa_date) {
            ftro$('ftro-rfa-date-wrap').style.display = '';
            ftro_set('ftro-rfa-date', ftro_date(sigs.rfa_date));
        } else {
            ftro$('ftro-rfa-date-wrap').style.display = 'none';
        }
        ftro_set('ftro-signatory-name',  d.signatory_name_memo);
        ftro_set('ftro-signatory-title', d.signatory_position_memo);

        ftro_wfLoaded   = false;
        ftro_utilLoaded = false;

        // Documents
        const atts     = d.attachments || [];
        const fileBase = (window.FTLAConfig?.fileBaseUrl || '').replace(/\/$/, '');
        const validatedEntry = d.validated_ftla_url
            ? [{ _isValidated: true, link: d.validated_ftla_url, document_name: 'Validated FTLA' }]
            : [];
        const allDocs = [...validatedEntry, ...atts];

        const FILE_ICON = `<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>`;
        const EXT_ICON  = `<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>`;

        if (allDocs.length) {
            ftro$('ftro-docs-list').innerHTML = `
                <div class="ftro-docs-header">
                    <span class="ftro-docs-title">Supporting Documents</span>
                    <span class="ftro-docs-count">${allDocs.length} file${allDocs.length !== 1 ? 's' : ''}</span>
                </div>
                <table class="ftro-att-table">
                    <thead>
                        <tr>
                            <th style="width:32px;"></th>
                            <th>Document Name</th>
                            <th>File Link</th>
                            <th style="width:80px;text-align:right;padding-right:16px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>` +
                allDocs.map((a, idx) => {
                    const rawUrl = a.link || a.url || a.file_url || '';
                    const href   = rawUrl.startsWith('http') ? rawUrl : (fileBase + (rawUrl.startsWith('/') ? rawUrl : '/' + rawUrl));
                    const name   = ftro_esc(a.document_name || a.name || a.file_name || '—');
                    const iconCls = a._isValidated ? 'ftro-att-icon validated' : 'ftro-att-icon';
                    const validatedBadge = a._isValidated
                        ? `<span style="margin-left:7px;font-size:9.5px;font-weight:700;color:var(--afims-success-hover, #0C7539);background:var(--afims-success-soft, #DBF2E5);border:1px solid var(--afims-success-soft-hover, #C4E9D4);border-radius:4px;padding:1px 7px;letter-spacing:.03em;vertical-align:middle;">VALIDATED</span>`
                        : '';
                    const displayLink = rawUrl.length > 55 ? rawUrl.slice(0, 52) + '…' : rawUrl;
                    return `<tr>
                        <td>
                            <div class="${iconCls}">${FILE_ICON}</div>
                        </td>
                        <td>
                            <div class="ftro-att-doc-name">${name}${validatedBadge}</div>
                        </td>
                        <td>
                            <div class="ftro-att-link">${rawUrl ? ftro_esc(displayLink) : '<span style="color:var(--afims-border, #E4ECF2);">—</span>'}</div>
                        </td>
                        <td style="text-align:right;padding-right:14px;">
                            ${rawUrl
                                ? `<a href="${ftro_esc(href)}" target="_blank" rel="noopener" class="ftro-att-open">${EXT_ICON} Open</a>`
                                : `<span style="font-size:11px;color:var(--afims-border, #E4ECF2);">No file</span>`}
                        </td>
                    </tr>`;
                }).join('') +
                `</tbody></table>`;
        } else {
            ftro$('ftro-docs-list').innerHTML = `
                <div style="text-align:center;padding:48px 24px;">
                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" style="width:36px;height:36px;stroke:var(--afims-border, #E4ECF2);stroke-width:1.5;margin:0 auto 12px;display:block;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <p style="font-size:13px;font-weight:600;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 4px;">No documents attached</p>
                    <p style="font-size:11.5px;color:var(--afims-border, #E4ECF2);margin:0;">Supporting documents will appear here once uploaded.</p>
                </div>`;
        }

        ftro$('ftro-loading').style.display = 'none';
        ftro$('ftro-content').style.display = 'block';
        const printBtn = ftro$('ftro-print-btn');
        if (printBtn) printBtn.style.display = '';
    }

    function ftro_renderTimeline(steps) {
        const tl = ftro$('ftro-timeline');
        if (!tl) return;

        const sub = ftro$('ftro-wf-subtitle');
        if (sub) sub.textContent = steps.length ? steps.map(s => s.step_label).join(' → ') : '';

        if (!steps.length) {
            tl.innerHTML = '<p style="font-size:12px;color:var(--afims-text-disabled, #A9AAB8);text-align:center;padding:20px 0;">No workflow data available.</p>';
            return;
        }

        const ICON_PLUS   = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>`;
        const ICON_CHECK  = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>`;
        const ICON_CLOCK  = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>`;
        const ICON_EYE    = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>`;
        const ICON_SHIELD = `<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>`;

        const total = steps.length;
        tl.innerHTML = steps.map((step, idx) => {
            const done   = step.step_status === 'done';
            const active = step.step_status === 'active';
            const color  = STEP_COLORS[idx % STEP_COLORS.length];
            const statusName = step.status_name ?? (idx === 0 ? 'Prepared' : null);

            let dotStyle, iconStroke, icon;
            if (done) {
                dotStyle   = `background:${color};`;
                iconStroke = '#fff';
                icon = idx === 0 ? ICON_PLUS : (idx === total - 1 ? ICON_SHIELD : ICON_CHECK);
            } else if (active) {
                dotStyle   = `background:#fef3c7;border:2px solid #f59e0b;box-sizing:border-box;animation:admPulse 2s ease-in-out infinite;`;
                iconStroke = '#b45309';
                icon = ICON_CLOCK;
            } else {
                dotStyle   = `background:#e2e8f0;`;
                iconStroke = '#94a3b8';
                icon = idx === 0 ? ICON_PLUS : (idx === total - 1 ? ICON_SHIELD : ICON_EYE);
            }

            const stageColor = done ? '#0f172a' : (active ? '#92400e' : '#94a3b8');
            let subText;
            if (done) {
                subText = step.actor_name
                    ? `${statusName ? statusName + ' · ' : ''}${ftro_esc(step.actor_name)}`
                    : (statusName ?? 'Completed');
            } else if (active) {
                subText = 'Awaiting action';
            } else {
                subText = 'Pending';
            }

            const desig = done && step.actor_designation
                ? `<p class="ftro-tl-meta" style="color:var(--afims-text-muted, #62657E);">${ftro_esc(step.actor_designation)}</p>` : '';
            const ts = done && step.action_at
                ? `<p class="ftro-tl-ts">${ftro_date(step.action_at)}</p>` : '';

            return `<div class="ftro-tl-item">
                <div class="ftro-tl-dotw">
                    <div class="ftro-tl-dot" style="${dotStyle}">
                        <svg fill="none" viewBox="0 0 24 24" style="stroke:${iconStroke};stroke-width:2;">${icon}</svg>
                    </div>
                    <div class="ftro-tl-line"></div>
                </div>
                <div class="ftro-tl-body">
                    <p class="ftro-tl-stage" style="color:${stageColor};">${ftro_esc(step.step_label)}</p>
                    <p class="ftro-tl-meta">${subText}</p>
                    ${desig}${ts}
                </div>
            </div>`;
        }).join('');
    }

    function ftro_renderHistoryTrail(entries) {
        const el = ftro$('ftro-audit-trail');
        if (!el) return;
        if (!entries.length) {
            el.innerHTML = '<p style="font-size:11px;color:var(--afims-text-disabled, #A9AAB8);text-align:center;padding:16px 0;">No history yet.</p>';
            return;
        }
        const COLOR_DOT = {
            primary: '#0284c7', info: '#0891b2', success: '#059669',
            warning: '#d97706', danger: '#dc2626', secondary: '#475569',
        };
        const COLOR_MAP = {
            secondary: 'bdg-gray', primary: 'bdg-blue', info: 'bdg-teal',
            success: 'bdg-green',  danger:  'bdg-red',  warning: 'bdg-amber',
        };
        const last = entries.length - 1;
        el.innerHTML = entries.map((e, idx) => {
            const dotColor  = COLOR_DOT[e.status_color] ?? '#475569';
            const bdgCls    = COLOR_MAP[e.status_color]  ?? 'bdg-gray';
            const label     = e.signatory_label || e.step_label || '—';
            const statusTxt = e.status_name ?? 'Prepared';
            const actor     = e.actor_name  || '—';
            const desig     = e.actor_designation || '';
            const ts        = e.action_at ? ftro_date(e.action_at) : '';
            const isLast    = idx === last;
            return `
        <div style="display:flex;gap:10px;padding-bottom:${isLast ? '4px' : '14px'};">
            <div style="display:flex;flex-direction:column;align-items:center;flex-shrink:0;width:12px;">
                <div style="width:10px;height:10px;border-radius:50%;background:${dotColor};margin-top:3px;flex-shrink:0;box-shadow:0 0 0 3px ${dotColor}22;"></div>
                ${!isLast ? `<div style="width:1.5px;flex:1;background:var(--afims-border, #E4ECF2);margin-top:5px;border-radius:1px;"></div>` : ''}
            </div>
            <div style="flex:1;min-width:0;padding-top:1px;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:6px;margin-bottom:3px;">
                    <span style="font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);line-height:1.3;">${ftro_esc(label)}</span>
                    <span class="bdg ${bdgCls}" style="font-size:9px;white-space:nowrap;flex-shrink:0;">${ftro_esc(statusTxt)}</span>
                </div>
                <div style="font-size:11px;color:var(--afims-text-muted, #62657E);">${[actor, desig, ts].filter(Boolean).join(' — ')}</div>
            </div>
        </div>`;
        }).join('');
    }

    async function ftro_loadWorkflowTab(id) {
        ftro_wfLoaded = true;
        ftro_loadTimeline(id);
        ftro_loadHistory(id);
    }

    async function ftro_loadTimeline(id) {
        const tl = ftro$('ftro-timeline');
        if (tl) tl.innerHTML = '<div style="text-align:center;padding:24px 0;color:var(--afims-text-disabled, #A9AAB8);font-size:12px;"><div class="ftro-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';
        try {
            const j = await ftro_apiFetch(`${FTRO_API}/ftla/${id}/timeline`);
            ftro_renderTimeline(j.data ?? []);
        } catch (e) {
            if (tl) tl.innerHTML = `<p style="font-size:12px;color:var(--afims-danger, #DC3545);text-align:center;padding:20px 0;">Could not load timeline: ${ftro_esc(e.message)}</p>`;
        }
    }

    async function ftro_loadHistory(id) {
        const trail = ftro$('ftro-audit-trail');
        if (trail) trail.innerHTML = '<div style="text-align:center;padding:24px 0;color:var(--afims-text-disabled, #A9AAB8);font-size:12px;"><div class="ftro-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';
        try {
            const j = await ftro_apiFetch(`${FTRO_API}/ftla/${id}/history`);
            ftro_renderHistoryTrail(j.data ?? []);
        } catch (_) {
            const el = ftro$('ftro-audit-trail');
            if (el) el.innerHTML = '';
        }
    }

    async function ftro_loadUtilization(id) {
        ftro_utilLoaded = true;
        const wrap = ftro$('ftro-util-table-wrap');
        if (wrap) wrap.innerHTML = '<div style="text-align:center;padding:24px 0;color:var(--afims-text-disabled, #A9AAB8);font-size:12px;"><div class="ftro-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';
        try {
            const j = await ftro_apiFetch(`${FTRO_API}/ftla/${id}/utilization`);
            if (!j.success) throw new Error(j.message || 'API error');
            ftro_renderUtilization(j.data);
        } catch (e) {
            if (wrap) wrap.innerHTML = `<p style="font-size:12px;color:var(--afims-danger, #DC3545);text-align:center;padding:20px 0;">Could not load utilization: ${ftro_esc(e.message)}</p>`;
        }
    }

    function ftro_renderUtilization(d) {
        ftro_set('ftro-util-transfer-amount', ftro_money(d.transfer_amount));
        ftro_set('ftro-util-dv-total',        ftro_money(d.total_dv_amount));
        const varEl = ftro$('ftro-util-variance');
        if (varEl) {
            varEl.textContent = ftro_money(Math.abs(d.variance));
            varEl.style.color = d.variance < 0
                ? 'var(--afims-danger, #DC3545)'
                : (d.variance > 0 ? 'var(--afims-success, #11A752)' : '');
        }

        const wrap = ftro$('ftro-util-table-wrap');
        if (!wrap) return;
        const docs = d.documents || [];
        if (!docs.length) {
            wrap.innerHTML = `
                <div style="text-align:center;padding:40px 24px;">
                    <p style="font-size:13px;font-weight:600;color:var(--afims-text-disabled, #A9AAB8);margin:0;">No DV numbers attached to this FTLA.</p>
                </div>`;
            return;
        }
        wrap.innerHTML = `
            <table class="ftro-att-table">
                <thead>
                    <tr>
                        <th>DV Number</th>
                        <th>Payee</th>
                        <th style="text-align:right;">Amount</th>
                        <th style="text-align:center;">Status</th>
                    </tr>
                </thead>
                <tbody>` +
            docs.map(doc => {
                const badgeCls = UTIL_COLOR_MAP[doc.status_color] || 'nha-bdg-gray';
                const amt = doc.amount != null ? ftro_money(doc.amount) : '—';
                return `<tr>
                    <td style="font-weight:600;" class="nha-mono">${ftro_esc(doc.dv_number)}</td>
                    <td>${ftro_esc(doc.payee_name || '—')}</td>
                    <td style="text-align:right;" class="nha-mono">${amt}</td>
                    <td style="text-align:center;"><span class="nha-bdg ${badgeCls}">${ftro_esc(doc.status_name)}</span></td>
                </tr>`;
            }).join('') +
            `</tbody></table>`;
    }

    /* ── Preview & Print ──────────────────────────────────────────── */
    window.ftro_printDocx = async function () {
        const d = ftro_currentRecord;
        if (!d) return;
        const btn = ftro$('ftro-print-btn');
        if (btn) { btn.disabled = true; btn.textContent = 'Generating…'; }
        try {
            const j = await ftro_apiFetch(`${FTRO_API}/ftla/${d.fund_transfer_id}/print-pdf`, {
                method: 'POST',
                body: JSON.stringify({}),
            });
            if (!j.success) throw new Error(j.message || 'Generation failed.');

            const win = window.open(j.view_url, '_blank',
                `width=${Math.floor(screen.width/2)},height=${screen.height},left=0,top=0,menubar=no,toolbar=no,location=no,status=no`);
            if (!win) alert('Pop-up blocked. Please allow pop-ups for this site to print.');
        } catch (e) {
            alert('Print error: ' + e.message);
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;stroke-width:2;"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg> Preview &amp; Print`;
            }
        }
    };

    /* Modal closes only via close button */
})();
</script>

