<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_prp_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<script src="/_shared/js/searchable-select.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/js/searchable-select.js') ?>"></script>

<style>

    /* category cards (one per GL code) */
    .prp-sec-title { margin:6px 0 10px;font-size:13px;font-weight:700;color:var(--afims-heading, #000E3E);display:flex;align-items:center;gap:8px; }
    .prp-sec-title::before { content:'';width:4px;height:15px;border-radius:2px;background:var(--afims-primary, #0035A9); }
    .prp-cats { display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:20px; }
    @media (max-width:1400px) { .prp-cats { grid-template-columns:repeat(4,minmax(0,1fr)); } }
    @media (max-width:1100px) { .prp-cats { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media (max-width:760px)  { .prp-cats { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    .prp-cat .val { overflow-wrap:anywhere; }
    .prp-cat { cursor:pointer;padding:14px 16px;border:1px solid var(--afims-border, #E4ECF2);border-left:4px solid var(--afims-primary, #0035A9);border-radius:10px;
        background:var(--afims-surface, #FFFFFF);box-shadow:0 1px 2px rgba(15,32,69,.05);transition:box-shadow .15s, border-color .15s, background .15s; }
    .prp-cat:hover { box-shadow:0 4px 12px rgba(15,32,69,.10); }
    .prp-cat.active { background:var(--afims-primary-soft, #E6EBF6);border-color:var(--afims-primary, #0035A9); }
    .prp-cat .gl { font-family:'IBM Plex Mono',monospace;font-size:11px;font-weight:600;color:var(--afims-primary-text, #0035A9); }
    .prp-cat .nm { margin:0 0 6px;font-size:12px;font-weight:600;line-height:1.35;color:var(--afims-text, #1A1B21); }
    .prp-cat .val { font-size:18px;font-weight:700;color:var(--afims-heading, #000E3E);font-variant-numeric:tabular-nums; }
    .prp-cat .sub { margin-top:2px;font-size:11px;color:var(--afims-text-muted, #62657E); }
    .nha-root .nha-sc.prp-clickable { cursor:pointer; }
    .nha-root .nha-sc.prp-clickable.active { outline:2px solid var(--afims-primary, #0035A9);outline-offset:-2px; }

    table.prp-reg.nha-tbl { min-width:2700px; }
    table.prp-reg.nha-tbl thead th { text-align:center;vertical-align:middle;line-height:1.35; }
    table.prp-reg.nha-tbl thead th .fx { display:block;font-size:10px;font-weight:400;opacity:.8;text-transform:none; }
    table.prp-reg.nha-tbl tfoot td { padding:11px 12px;font-weight:700;font-size:13px;white-space:nowrap;background:var(--afims-surface-subtle, #F4F7FA);border-top:2px solid var(--afims-border, #E4ECF2); }
    table.prp-reg.nha-tbl tr.prp-grp td { padding:12px 10px;font-weight:700;font-size:13px;color:var(--afims-heading, #000E3E);background:var(--afims-surface-2, #F1F5F9);border-top:1px solid var(--afims-border, #E4ECF2); }
    /* ── Actions dropdown (same menu as the Memo Requests list) ── */
    .prp-menu { position:relative;display:inline-block; }
    .prp-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
        border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Sans',sans-serif;transition:all .15s; }
    .prp-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA);border-color:var(--afims-text-disabled, #A9AAB8); }
    .prp-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
    .prp-menu-drop { display:none;position:fixed;min-width:170px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
    .prp-menu-drop.open { display:block; }
    .prp-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);white-space:nowrap;cursor:pointer;
        border:none;background:none;width:100%;text-align:left;transition:background .1s;font-family:'IBM Plex Sans',sans-serif; }
    .prp-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
    .prp-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }

    /* ── Details modal (same design as the Memo Request details) ── */
    .prp-md-ov { display:none;position:fixed;inset:0;z-index:9060;padding:16px;box-sizing:border-box;align-items:center;justify-content:center;background:rgba(15,32,69,.55);font-family:'IBM Plex Sans',sans-serif; }
    .prp-md-ov.open { display:flex; }
    .prp-md { display:flex;flex-direction:column;width:100%;max-width:1400px;max-height:100%;overflow:hidden;border-radius:14px;background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text, #1A1B21);box-shadow:0 24px 64px rgba(0,0,0,.35); }
    .prp-md-hdr { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;background:var(--afims-heading-fill, #000E3E);color:#fff; }
    .prp-md-title { margin:0;font-size:15px;font-weight:600;color:inherit; }
    .prp-md-ref { margin-left:8px;font-family:'IBM Plex Mono',monospace;font-weight:600;letter-spacing:.02em;color:#C7D4F5; }
    .prp-md-status { margin-left:12px;vertical-align:1px; }
    .prp-md-close { width:30px;height:30px;font-size:20px;line-height:1;color:#fff;cursor:pointer;border-radius:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25); }
    .prp-md-close:hover { background:rgba(255,255,255,.22); }
    .prp-md-body { padding:18px 20px 6px;overflow-y:auto; }
    .prp-md-card { margin-bottom:14px;padding:14px 18px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:12px; }
    .prp-md-card-title { margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--afims-text-secondary, #475569); }
    .prp-md-row { display:grid;grid-template-columns:190px minmax(0,1fr);gap:12px;align-items:baseline;padding:4px 0;font-size:13px; }
    .prp-md-k { font-weight:600;color:var(--afims-text-secondary, #475569); }
    .prp-md-v { overflow-wrap:anywhere; }
    .prp-md-muted { color:#8A94A6; }
    .prp-md-code { display:inline-block;padding:2px 7px;font:600 11px/1.4 'IBM Plex Mono',monospace;color:#1D4ED8;background:var(--afims-surface, #FFFFFF);border:1px solid #E6EBF6;border-radius:4px; }
    .prp-md-num { font:700 13px/1.4 'IBM Plex Mono',monospace;letter-spacing:.02em; }
    .prp-md-sub { margin-top:2px;font-size:11.5px;color:#8A94A6; }
    .prp-md-tblwrap { overflow-x:auto;border:1px solid var(--afims-border, #E4ECF2);border-radius:10px; }
    .prp-md-tbl { width:100%;border-collapse:collapse;font-size:12.5px; }
    .prp-md-tbl th { padding:9px 12px;text-align:left;font-size:11px;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;color:var(--afims-text-secondary, #475569);background:var(--afims-surface-subtle, #F4F7FA); }
    .prp-md-tbl th .fx { display:block;font-size:10px;font-weight:400;text-transform:none;letter-spacing:0; }
    .prp-md-tbl td { padding:9px 12px;vertical-align:top;border-top:1px solid var(--afims-border, #E4ECF2); }
    .prp-md-tbl .amt { text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums; }
    .prp-md-tbl tfoot td { font-weight:700;background:var(--afims-surface-subtle, #F4F7FA); }
    .prp-md-foot { display:flex;justify-content:flex-end;gap:10px;padding:12px 20px;background:var(--afims-surface, #FFFFFF);border-top:1px solid var(--afims-border, #E4ECF2); }
    .prp-md-btn { padding:8px 18px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;border-radius:8px;color:var(--afims-text, #1A1B21);background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2); }
    .prp-md-btn:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    @media (max-width:640px) { .prp-md-row { grid-template-columns:1fr;gap:2px; } }

    .prp-empty { padding:56px 20px;text-align:center;color:var(--afims-text-muted, #62657E); }
    .prp-empty strong { display:block;margin-bottom:6px;font-size:15px;color:var(--afims-heading, #000E3E); }
    .prp-note { margin:0 0 14px;padding:9px 14px;border-radius:8px;font-size:12px;color:#92400E;background:#FEF3C7;border:1px solid #FDE68A; }
    .prp-err { margin:0 0 14px;padding:9px 14px;border-radius:8px;font-size:13px;color:var(--afims-danger, #DC3545);background:var(--afims-danger-soft, #FAE1E3);border:1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 25%, transparent); }
    .prp-sub { margin-top:2px;font-size:11px;color:var(--afims-text-muted, #62657E); }
    .prp-money { text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums; }

    /* the PO strip of a search result: PO, date, ONE responsibility center */
    .prp-po { display:flex;flex-wrap:wrap;gap:14px 32px;align-items:flex-start;margin:0 0 16px;padding:14px 18px;border:1px solid var(--afims-border, #E4ECF2);
        border-left:4px solid var(--afims-primary, #0035A9);border-radius:10px;background:var(--afims-surface, #FFFFFF); }
    .prp-po .k { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted, #62657E);margin-bottom:3px; }
    .prp-po .v { font-size:14px;font-weight:700;color:var(--afims-text, #1A1B21); }

    .prp-po .rc-none { color:#92400E; }

    /* save bar under the search results */
    .prp-savebar { display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:12px 16px;border-top:1px solid var(--afims-border, #E4ECF2); }
    .prp-savebar .sel { font-size:13px;color:var(--afims-text-muted, #62657E); }
    .prp-savebar .sel b { color:var(--afims-heading, #000E3E); }
    .prp-savebtn { height:38px;padding:0 22px;border-radius:8px;border:1px solid var(--afims-heading-fill, #000E3E);background:var(--afims-heading-fill, #000E3E);color:#fff;
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    .prp-savebtn:disabled { opacity:.5;cursor:not-allowed; }
    .prp-chk { width:16px;height:16px;cursor:pointer; }
    .prp-chk:disabled { cursor:not-allowed; }
    tr.prp-saved-row { background:color-mix(in srgb, var(--afims-success, #11A752) 6%, transparent); }

    .prp-pl { float:left; }                                  /* the peso sign sits at the left of an amount cell */
    /* pro-forma account rows (account | debit | credit | remove) */
    .prp-jev { border:1px solid var(--afims-border, #E4ECF2);border-radius:12px;background:var(--afims-surface-subtle, #F4F7FA);font-size:13px; }
    .prp-jev-row { display:grid;grid-template-columns:minmax(0,4fr) minmax(0,1.3fr) minmax(0,1.3fr) 34px;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF); }
    .prp-jev-head { background:var(--afims-surface-subtle, #F4F7FA);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text, #1A1B21); }
    .prp-jev-total { background:var(--afims-surface-subtle, #F4F7FA);border-bottom:0;border-radius:0 0 12px 12px; }
    .prp-jev-amt { text-align:right;white-space:nowrap; }
    .prp-jev-row .nha-filter-input { width:100%;height:36px; }
    .prp-jev-row input.prp-jev-num { text-align:right; }
    .prp-jev-del { border:0;background:none;color:#DC3545;font-size:18px;cursor:pointer;line-height:1; }
    .prp-pf-grid { display:grid;grid-template-columns:minmax(0,2fr) minmax(0,2fr) minmax(0,1fr);gap:14px; }
    @media (max-width:760px) { .prp-pf-grid { grid-template-columns:minmax(0,1fr); } }
    /* Pro-forma JEV modal: buttons (close, + Add row, remove row, Print JEV, Submit) */
    #prp-pf-overlay .nha-modal-close { width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;padding:0;font-size:20px;line-height:1;cursor:pointer;
        border-radius:8px;color:#fff;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25); }
    #prp-pf-overlay .nha-modal-close:hover { background:rgba(255,255,255,.24); }
    #prp-pf-overlay #prp-pf-add { display:inline-flex;align-items:center;gap:4px;height:34px;padding:0 16px;font-size:12px;font-weight:600;cursor:pointer;
        border-radius:8px;color:#fff;background:var(--afims-primary, #0035A9);border:1px solid var(--afims-primary, #0035A9); }
    #prp-pf-overlay #prp-pf-add:hover { filter:brightness(1.12); }
    #prp-pf-overlay .prp-jev-del { width:30px;height:30px;display:inline-flex;align-items:center;justify-content:center;padding:0;font-size:18px;font-weight:600;line-height:1;cursor:pointer;
        border-radius:6px;color:#DC3545;background:transparent;border:1px solid color-mix(in srgb, #DC3545 35%, transparent); }
    #prp-pf-overlay .prp-jev-del:hover { color:#fff;background:#DC3545;border-color:#DC3545; }
    #prp-pf-overlay .nha-modal-foot .nha-btn { height:36px;padding:0 20px;font-size:13px !important;font-weight:600;border-radius:8px;cursor:pointer; }
    #prp-pf-overlay #prp-pf-print { color:var(--afims-text, #1A1B21);background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2); }
    #prp-pf-overlay #prp-pf-print:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    #prp-pf-overlay #prp-pf-submit { color:#fff;background:var(--afims-primary, #0035A9);border:1px solid var(--afims-primary, #0035A9); }
    #prp-pf-overlay #prp-pf-submit:hover { filter:brightness(1.12); }
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay #prp-pf-add,
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay #prp-pf-submit { background:#3861BC;border-color:#3861BC; }
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay #prp-pf-add:hover,
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay #prp-pf-submit:hover { background:#2E59B8;border-color:#2E59B8;filter:none; }
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay #prp-pf-print { color:#E4ECF2;background:#22294A;border-color:#4A5270; }
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay #prp-pf-print:hover { background:#2E3656; }
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay .prp-jev-del { color:#F87171;border-color:rgba(248,113,113,.4); }
    html.afims-dark[data-bs-theme="dark"] #prp-pf-overlay .prp-jev-del:hover { color:#fff;background:#DC3545;border-color:#DC3545; }
    #prp-pf-overlay button:disabled { opacity:.5;cursor:not-allowed; }
    /* Items from the IAR module: an account row with an arrow that opens its items */
    .prp-v-toggle { width:28px;height:28px;border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);cursor:pointer;font-size:12px;line-height:1; }
    .prp-v-toggle:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    .prp-v-pill { display:inline-block;margin-left:8px;padding:2px 8px;border-radius:999px;font-size:11px;font-weight:600;color:#1D4ED8;background:color-mix(in srgb, #2563EB 12%, transparent); }
    .prp-v-sub > td { background:var(--afims-surface-subtle, #F4F7FA);padding:12px 16px 16px; }
    .prp-v-inner { background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .prp-v-tbl { width:100%;border-collapse:collapse;font-size:13px; }
    /* this table sits INSIDE .nha-tbl, whose header rules (white text) would apply to it too: override them with !important */
    #prp-root .prp-v-inner .prp-v-tbl thead th { padding:9px 14px !important;text-align:left;font-size:11px !important;font-weight:700 !important;text-transform:uppercase;letter-spacing:.04em;
        color:var(--afims-text, #1A1B21) !important;background:var(--afims-surface-subtle, #F4F7FA) !important;border-bottom:1px solid var(--afims-border, #E4ECF2) !important; }
    #prp-root .prp-v-inner .prp-v-tbl thead th[style*="right"] { text-align:right; }
    #prp-root .prp-v-inner .prp-v-tbl td { padding:9px 14px;color:var(--afims-text, #1A1B21);border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .prp-v-tbl tbody tr:last-child td { border-bottom:0; }
    .prp-v-tbl tbody tr:hover td { background:var(--afims-surface-subtle, #F4F7FA); }

    /* details modal */
    .prp-grid { display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px 20px;margin-bottom:18px; }
    .prp-grid .wide { grid-column:1 / -1; }
    .prp-grid .k { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted, #62657E);margin-bottom:3px; }
    .prp-grid .v { font-size:13px;font-weight:600;color:var(--afims-text, #1A1B21);word-break:break-word; }
    .prp-mh { margin:0 0 12px;padding-bottom:8px;border-bottom:1px solid var(--afims-border, #E4ECF2);font-size:13px;font-weight:700;color:var(--afims-heading, #000E3E);display:flex;align-items:center;gap:8px; }
    .prp-mh::before { content:'';width:4px;height:14px;border-radius:2px;background:var(--afims-primary, #0035A9); }
    @media (max-width:760px) { .prp-grid { grid-template-columns:minmax(0,1fr) minmax(0,1fr); } }
</style>

<div id="prp-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Property Records</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="prp-refresh" type="button" class="nha-btn-hdr" title="Reload the property records">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="prp-pf" type="button" class="nha-btn-hdr" title="Pro-forma JEV: the depreciation of the quarter">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Pro-forma JEV
            </button>
            <button id="prp-add" type="button" class="nha-btn-hdr" style="display:none;background:var(--afims-success, #11A752);" title="Search a PO number in the Property Inventory System and add its items to the property records">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add from PIS
            </button>
        </div>
    </div>

    <div id="prp-error" class="prp-err" style="display:none;"></div>

    <!-- ═════════ THE LIST: the rows of fims.property_records (cards, then the table). Nothing here is read from PIS. ═════════ -->
    <div id="prp-view-saved">

        <div id="prp-saved-result" style="display:none;">

            <div class="nha-filters no-print">
                <div class="nha-filter-row">
                    <div class="nha-filter-group">
                        <label class="nha-filter-label">Status</label>
                        <select id="prp-f-status" class="nha-filter-select">
                            <option value="">All Status</option>
                            <option value="1">Issued</option>
                            <option value="2">Returned: Unserviceable</option>
                            <option value="3">Returned: Serviceable</option>
                            <option value="4">Donation</option>
                        </select>
                    </div>
                    <div class="nha-filter-group">
                        <label class="nha-filter-label">Responsibility Center</label>
                        <select id="prp-f-rc" class="nha-filter-select"><option value="">All Centers</option></select>
                    </div>
                    <div class="nha-filter-group nha-filter-group-search">
                        <label class="nha-filter-label">Search</label>
                        <div class="nha-filter-search-wrap">
                            <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="text" id="prp-f-q" class="nha-filter-input" placeholder="Property no., IAR / PO no., description, model, serial, location, custodian…">
                            <button class="nha-filter-clear-btn" id="prp-f-clear" hidden type="button">×</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="nha-table-wrap">
                <div class="nha-tbl-scroll">
                    <table class="nha-tbl prp-reg">
                        <thead>
                            <tr>
                                <th rowspan="2" style="min-width:160px;">Responsibility Area</th>
                                <th colspan="3">Complete description of item</th>
                                <th rowspan="2" style="min-width:150px;">Location/<br>Department within<br>Responsibility<br>Area</th>
                                <th rowspan="2" style="min-width:150px;">JEV<br>Reference/Date **</th>
                                <th colspan="2" style="text-align:left;padding-left:16px;">Basis of the</th>
                                <th rowspan="2" style="min-width:150px;">PROPERTY<br>NUMBER</th>
                                <th rowspan="2" style="min-width:110px;">UNIT COST</th>
                                <th rowspan="2" style="min-width:130px;">Acquisition Cost<span class="fx">a</span></th>
                                <th rowspan="2" style="min-width:130px;">Additional Cost ****<span class="fx">b</span></th>
                                <th rowspan="2" style="min-width:130px;">Total book balance<span class="fx">c = a + b</span></th>
                                <th rowspan="2" style="min-width:120px;">Estimated Useful<br>life (in Years)</th>
                                <th rowspan="2" style="min-width:130px;">Salvage/<br>Residual value</th>
                                <th rowspan="2" style="min-width:130px;">Accumulated Dep.<br>January 1, <span id="prp-yr"></span></th>
                                <th rowspan="2" style="min-width:120px;">Depreciation for<br>the year<span class="fx">e</span></th>
                                <th rowspan="2" style="min-width:110px;">MONTHLY DEPR'N.</th>
                                <th rowspan="2" style="min-width:130px;">Accumulated<br>Depreciation<span class="fx">f = d + e</span></th>
                                <th rowspan="2" style="min-width:120px;">Book Value<span class="fx">c - f</span></th>
                                <th rowspan="2" style="min-width:130px;">Remarks *****</th>
                                <th rowspan="2" style="min-width:150px;">Status</th>
                                <th rowspan="2" style="width:90px;">Actions</th>
                            </tr>
                            <tr>
                                <th style="width:50px;">Qty</th>
                                <th style="min-width:110px;">Unit (pc, set, etc.)</th>
                                <th style="min-width:300px;">Description including serial number and model of item</th>
                                <th style="min-width:120px;">Particular<br>document ***</th>
                                <th style="min-width:130px;">Date<br>Acquired/Donated</th>
                            </tr>
                        </thead>
                        <tbody id="prp-tbody"></tbody>
                        <tfoot id="prp-tfoot"></tfoot>
                    </table>
                </div>
                <div class="nha-pag-wrap">
                    <span class="nha-pag-info">Showing <strong><span id="prp-from">0</span>–<span id="prp-to">0</span></strong> of <strong><span id="prp-total">0</span></strong></span>
                    <div id="prp-pagination" class="nha-pag-btns"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ═════════ ITEMS FROM THE IAR MODULE: the items entered for the property accounts (click one for its details) ═════════ -->
    <div id="prp-iar-section">
        <div class="nha-summary" id="prp-iar-kpis" style="grid-template-columns:repeat(3,minmax(0,1fr));"></div>
        <div class="nha-filters no-print">
            <div class="nha-filter-row">
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Responsibility Center</label>
                    <input type="text" id="prp-i-rc" class="nha-filter-input" placeholder="All Centers — type to search" style="width:100%;">
                </div>
                <div class="nha-filter-group">
                    <label class="nha-filter-label">Account Code</label>
                    <input type="text" id="prp-i-gl" class="nha-filter-input" placeholder="All Accounts — type to search" style="width:100%;">
                </div>
                <div class="nha-filter-group nha-filter-group-search">
                    <label class="nha-filter-label">Search</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="prp-i-q" class="nha-filter-input" placeholder="Responsibility center, account code / name, item, serial or property no…">
                        <button class="nha-filter-clear-btn" id="prp-i-clear" hidden type="button">×</button>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;">
            <button type="button" id="prp-i-expand" class="nha-btn nha-btn-secondary" style="font-size:12px;">Expand All</button>
        </div>
        <div class="nha-table-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl">
                    <thead>
                        <tr>
                            <th style="width:56px;"></th>
                            <th style="text-align:center;">ITEM</th>
                            <th style="text-align:center;">ACCT. CODE</th>
                            <th style="text-align:center;">Amount</th>
                            <th style="text-align:center;">Acct. Code</th>
                            <th style="text-align:center;">Accu. Depr'n</th>
                            <th style="text-align:center;">Net Book Value</th>
                        </tr>
                    </thead>
                    <tbody id="prp-iar-tbody"><tr><td colspan="7"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ═════════ PRO-FORMA JEV: the depreciation of a quarter, by category (Book, Quarter, Particulars, account rows) ═════════ -->
    <div id="prp-pf-overlay" class="nha-overlay">
        <div class="nha-modal" style="max-width:1500px;width:96vw;">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title">Pro-forma JEV — Depreciation of PPE</h3>
                <button type="button" class="nha-modal-close" id="prp-pf-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="prp-pf-grid">
                    <div class="nha-filter-group"><label class="nha-filter-label">Book <span style="color:#DC3545;">*</span></label>
                        <select id="prp-pf-book" class="nha-filter-select"><option value="">Select a book…</option></select></div>
                    <div class="nha-filter-group"><label class="nha-filter-label">Quarter</label>
                        <select id="prp-pf-q" class="nha-filter-select">
                            <option value="1">1st Quarter (January - March)</option><option value="2">2nd Quarter (April - June)</option>
                            <option value="3">3rd Quarter (July - September)</option><option value="4">4th Quarter (October - December)</option>
                        </select></div>
                    <div class="nha-filter-group"><label class="nha-filter-label">Year</label>
                        <input type="number" id="prp-pf-y" class="nha-filter-input" min="2000" max="2100" style="width:100%;"></div>
                </div>
                <div class="nha-filter-group" style="margin-top:12px;"><label class="nha-filter-label">Particulars</label>
                    <textarea id="prp-pf-part" class="nha-filter-input" rows="2" style="width:100%;height:auto;padding:8px 10px;"></textarea></div>
                <div style="display:flex;justify-content:flex-end;margin:14px 0 8px;">
                    <button type="button" class="nha-btn nha-btn-secondary" id="prp-pf-add" style="font-size:12px;">+ Add row</button>
                </div>
                <!-- the account rows: editable (account, debit or credit), like the IAR module's pro-forma -->
                <div class="prp-jev">
                    <div class="prp-jev-row prp-jev-head"><div>Account Code - Account Name</div><div style="text-align:center;">Debit (₱)</div><div style="text-align:center;">Credit (₱)</div><div></div></div>
                    <div id="prp-pf-rows"></div>
                    <div class="prp-jev-row prp-jev-total"><div style="text-align:right;">TOTAL</div>
                        <div class="prp-jev-amt"><span class="prp-pl">₱</span><b id="prp-pf-td">0.00</b></div>
                        <div class="prp-jev-amt"><span class="prp-pl">₱</span><b id="prp-pf-tc">0.00</b></div><div></div></div>
                </div>
                <div id="prp-pf-msg" style="margin-top:10px;font-size:13px;color:var(--afims-text-muted, #62657E);min-height:18px;"></div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-secondary" id="prp-pf-print" style="font-size:12px;">Print JEV</button>
                <button type="button" class="nha-btn nha-btn-primary" id="prp-pf-submit" style="font-size:12px;">Submit</button>
            </div>
        </div>
    </div>

    <!-- ── print preview of the pro-forma JEV (the JEV form from POST /print/jev) ── -->
    <div id="prp-pv-overlay" class="nha-overlay" style="z-index:100001;">
        <div class="nha-modal" style="max-width:1300px;width:96vw;height:92vh;display:flex;flex-direction:column;">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title">Journal Entry Voucher — Print Preview</h3>
                <button type="button" class="nha-modal-close" id="prp-pv-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" style="flex:1;padding:0;min-height:0;">
                <div id="prp-pv-msg" style="padding:16px;font-size:13px;">Loading preview…</div>
                <iframe id="prp-pv-frame" title="JEV preview" style="display:none;width:100%;height:100%;border:0;" sandbox="allow-same-origin"></iframe>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-secondary" id="prp-pv-done" style="font-size:12px;">Close</button>
                <button type="button" class="nha-btn nha-btn-primary" id="prp-pv-pdf" style="font-size:12px;" disabled>Download PDF</button>
            </div>
        </div>
    </div>

    <!-- ═════════ ADD FROM PIS (window): PIS is only a source to search; the selected items are INSERTED into fims.property_records ═════════ -->
    <div id="prp-add-overlay" class="nha-overlay">
        <div class="nha-modal nha-modal-xl">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title">Add Property Records from the Property Inventory System</h3>
                <button type="button" class="nha-modal-close" id="prp-add-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div id="prp-add-error" class="prp-err" style="display:none;"></div>
    <!-- search a PO in PIS → select items → save -->
    <div id="prp-view-search">

        <div class="nha-filters no-print">
            <div class="nha-filter-row" style="grid-template-columns:minmax(260px,1fr) auto;align-items:end;gap:12px;">
                <div class="nha-filter-group nha-filter-group-search">
                    <label class="nha-filter-label">PO Number</label>
                    <div class="nha-filter-search-wrap">
                        <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                        <input type="text" id="prp-po" class="nha-filter-input" maxlength="100" placeholder="Enter a PO number that is in the IAR module, e.g. RX-2406044" autocomplete="off">
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="nha-btn nha-btn-primary" id="prp-search" style="font-size:12px;">Search</button>
                    <button type="button" class="nha-btn nha-btn-secondary" id="prp-reset" style="font-size:12px;">Reset</button>
                </div>
            </div>
        </div>

        <div id="prp-search-empty" class="nha-table-wrap">
            <div class="prp-empty"><strong>Search a PO number</strong>
                Only a PO that already has an IAR in the IAR module can be searched. Its items (status Issued, Returned or Donation) are read from the Property Inventory System; select the ones to save to the property records.</div>
        </div>

        <div id="prp-search-result" style="display:none;">
            <!-- the PO, its date and its ONE responsibility center -->
            <div class="prp-po" id="prp-po-strip"></div>

            <div class="nha-summary">
                <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Items Found</div><div class="nha-sc-val" id="prp-s-found">—</div></div></div><p class="nha-kpi-sub">Status Issued / Returned / Donation</p></div>
                <div class="nha-sc nha-sc-teal"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Cost</div><div class="nha-sc-val" id="prp-s-cost">—</div></div></div><p class="nha-kpi-sub">Of the items found</p></div>
                <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">Already Saved</div><div class="nha-sc-val" id="prp-s-saved">—</div></div></div><p class="nha-kpi-sub">Cannot be saved again</p></div>
                <div class="nha-sc nha-sc-amber"><div class="nha-sc-top"><div><div class="nha-sc-label">Selected</div><div class="nha-sc-val" id="prp-s-sel">0</div></div></div><p class="nha-kpi-sub" id="prp-s-selcost">₱0.00</p></div>
            </div>

            <div id="prp-hidden-note" class="prp-note" style="display:none;"></div>

            <div class="nha-table-wrap">
                <div class="nha-tbl-scroll">
                    <table class="nha-tbl">
                        <thead>
                            <tr>
                                <th style="width:36px;"><input type="checkbox" class="prp-chk" id="prp-chk-all" title="Select all items that are not saved yet"></th>
                                <th>Property No.</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Date Acquired</th>
                                <th style="text-align:right;">Unit Cost</th>
                                <th style="min-width:170px;">Location/Department within<br>Responsibility Area</th>
                                <th>Status</th>
                                <th>Saved</th>
                            </tr>
                        </thead>
                        <tbody id="prp-s-tbody"></tbody>
                    </table>
                </div>
                <div class="prp-savebar">
                    <span class="sel"><b id="prp-sb-count">0</b> item(s) selected</span>
                    <button type="button" class="prp-savebtn" id="prp-save" disabled>Save Selected</button>
                </div>
            </div>
        </div>
    </div>

            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-secondary" id="prp-add-done" style="font-size:12px;">Close</button>
            </div>
        </div>
    </div>

    <!-- ── DETAILS (read-only; same design as the Memo Request details) ── -->
    <div id="prp-overlay" class="prp-md-ov">
        <div class="prp-md" role="dialog" aria-modal="true" aria-labelledby="prp-md-title">
            <div class="prp-md-hdr">
                <h3 class="prp-md-title" id="prp-md-title"><span>Property Record Details</span><span class="prp-md-ref" id="prp-md-ref"></span><span class="prp-md-status" id="prp-md-status"></span></h3>
                <button type="button" class="prp-md-close" id="prp-modal-close" aria-label="Close">&times;</button>
            </div>
            <div class="prp-md-body" id="prp-modal-body"></div>
            <div class="prp-md-foot">
                <button type="button" class="prp-md-btn" id="prp-modal-done">Close</button>
            </div>
        </div>
    </div>

</div><!-- /#prp-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PROPERTY RECORDS   prefix: prp_
   The LIST (this page)  : GET  /property-record/list     reads ONLY fims.property_records (saved per PO as JSONB): cards + table. Never PIS.
   "Add from PIS" window : GET  /property-record/search   searches ONE PO number in the Property Inventory System (status 1-4 items + the PO's ONE
                                                          responsibility center); PIS is only a source of data to insert
                           POST /property-record/save     inserts only the SELECTED items (inv_ids) into fims.property_records
═══════════════════════════════════════════════════════════════ */

const PRP_API = '<?php echo $_prp_api; ?>' + '/property-record';
const PRP_PAGE_SIZE = 15;
const PRP_STATUS_COLOR = { '1': '#16A34A', '2': '#DC2626', '3': '#2563EB', '4': '#7C3AED' };

// saved records
let prp_items = [];                       // every saved item
let prp_cats  = [];
let prp_view  = [];                       // after the filters
let prp_page  = 1;
let prp_flt   = { status: '', rc: '', cat: '', q: '' };
// search
let prp_found = null;                     // the last search result { po_number, iar_number, items, rc, … }
let prp_sel   = new Set();                // inv_id of the items ticked

const $prp = id => document.getElementById(id);

function prp_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}
function prp_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
const prp_money = v => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
function prp_date(v) {
    if (!v) return '—';
    const d = new Date(String(v).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? prp_esc(v) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}
const prp_dash = v => (v === null || v === undefined || String(v).trim() === '') ? '—' : prp_esc(v);
function prp_badge(code, label) {
    const c = PRP_STATUS_COLOR[code] || '#6B7280';
    return `<span class="nha-bdg" style="color:${c};background:color-mix(in srgb, ${c} 14%, #fff);white-space:nowrap;">${prp_esc(label || code)}</span>`;
}
function prp_specs(it) {
    return [it.model_no && it.model_no !== 'N/A' && it.model_no !== '0' ? 'Model: ' + it.model_no : '',
            it.serial_no && it.serial_no !== 'N/A' && it.serial_no !== '0' ? 'Serial: ' + it.serial_no : ''].filter(Boolean).join(' · ');
}
function prp_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${colors[type] || colors.info};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:380px;pointer-events:auto;`;
    const span = document.createElement('span'); span.style.flex = '1'; span.textContent = msg;
    const x = document.createElement('button'); x.innerHTML = '&times;';
    x.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;';
    x.onclick = () => t.remove();
    t.append(span, x); wrap.appendChild(t);
    setTimeout(() => t.remove(), 5000);
}
function prp_showErr(msg) { const el = $prp('prp-error'); el.textContent = msg; el.style.display = ''; }
function prp_hideErr() { $prp('prp-error').style.display = 'none'; }
function prp_showAddErr(msg) { const el = $prp('prp-add-error'); el.textContent = msg; el.style.display = ''; }   // errors of the Add-from-PIS window
function prp_hideAddErr() { $prp('prp-add-error').style.display = 'none'; }

/* ── the "Add from PIS" window ───────────────────────────── */
function prp_openAdd() {
    prp_hideAddErr();
    $prp('prp-add-overlay').classList.add('open');
    $prp('prp-po').focus();
}
function prp_closeAdd() { $prp('prp-add-overlay').classList.remove('open'); }

/* ── events ───────────────────────────────────────────────── */
function prp_bind() {
    $prp('prp-add').addEventListener('click', prp_openAdd);
    $prp('prp-add-close').addEventListener('click', prp_closeAdd);
    $prp('prp-add-done').addEventListener('click', prp_closeAdd);
    $prp('prp-add-overlay').addEventListener('mousedown', e => { if (e.target === $prp('prp-add-overlay')) prp_closeAdd(); });
    $prp('prp-refresh').addEventListener('click', () => { prp_loadSaved(); prp_loadIar(); });

    // saved records: filters
    $prp('prp-f-status').addEventListener('change', e => { prp_flt.status = e.target.value; prp_apply(); });
    $prp('prp-f-rc').addEventListener('change', e => { prp_flt.rc = e.target.value; prp_apply(); });
    let timer;
    $prp('prp-f-q').addEventListener('input', e => {
        $prp('prp-f-clear').hidden = !e.target.value;
        clearTimeout(timer);
        timer = setTimeout(() => { prp_flt.q = e.target.value.trim().toLowerCase(); prp_apply(); }, 250);
    });
    $prp('prp-f-clear').addEventListener('click', () => { $prp('prp-f-q').value = ''; $prp('prp-f-clear').hidden = true; prp_flt.q = ''; prp_apply(); });

    // search PIS
    $prp('prp-search').addEventListener('click', () => prp_search($prp('prp-po').value));
    $prp('prp-po').addEventListener('keydown', e => { if (e.key === 'Enter') prp_search($prp('prp-po').value); });
    $prp('prp-reset').addEventListener('click', prp_resetSearch);
    $prp('prp-chk-all').addEventListener('change', e => {
        (prp_found ? prp_found.items : []).forEach(it => { if (!it.saved && it.is_property) { e.target.checked ? prp_sel.add(it.inv_id) : prp_sel.delete(it.inv_id); } });
        prp_renderSearchRows(); prp_updateSel();
    });
    $prp('prp-save').addEventListener('click', prp_save);

    // details
    $prp('prp-modal-close').addEventListener('click', () => $prp('prp-overlay').classList.remove('open'));
    $prp('prp-modal-done').addEventListener('click', () => $prp('prp-overlay').classList.remove('open'));
    $prp('prp-overlay').addEventListener('mousedown', e => { if (e.target === $prp('prp-overlay')) $prp('prp-overlay').classList.remove('open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { $prp('prp-overlay').classList.remove('open'); prp_closeAdd(); } });
}

/* ══════════════ SAVED RECORDS ══════════════ */
async function prp_loadSaved() {
    prp_hideErr();
    window.PagePreloader?.show();
    try {
        const res  = await fetch(PRP_API + '/list', { credentials: 'same-origin', headers: prp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the property records.');
        const d = json.data;
        prp_items = d.items || [];
        prp_cats  = (d.summary && d.summary.by_category) || [];
        prp_flt   = { status: '', rc: '', cat: '', q: '' };
        $prp('prp-f-q').value = ''; $prp('prp-f-clear').hidden = true; $prp('prp-f-status').value = '';

        if (!prp_items.length) {
            $prp('prp-saved-result').style.display = 'none';
            return;
        }
        $prp('prp-saved-result').style.display = '';
        // the responsibility-center filter: one entry per center that has saved items
        const rcs = [...new Map(prp_items.filter(it => it.rc_name).map(it => [it.rc_name, it.rc_name])).keys()].sort();
        $prp('prp-f-rc').innerHTML = '<option value="">All Centers</option>' + rcs.map(n => '<option value="' + prp_esc(n) + '">' + prp_esc(n) + '</option>').join('');
        prp_apply();
    } catch (e) {
        prp_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function prp_apply() {
    const q = prp_flt.q;
    prp_view = prp_items.filter(it =>
        (!prp_flt.status || (it.statuses || [it.status]).includes(prp_flt.status)) &&
        (!prp_flt.rc || it.rc_name === prp_flt.rc) &&
        (!prp_flt.cat || it.gl_code === prp_flt.cat) &&
        (!q || [it.iar_number, it.saved_po, it.po_no, it.property_no, it.description_full, it.category, it.sub_category, it.department, it.location, it.rc_name]
                .some(v => String(v || '').toLowerCase().includes(q)))
    );
    prp_view.sort((a, b) => String(a.rc_name || '').localeCompare(String(b.rc_name || ''), undefined, { numeric: true }));
    prp_page = 1;
    $prp('prp-f-status').value = prp_flt.status;
    $prp('prp-f-rc').value = prp_flt.rc;
    prp_renderCats();
    prp_renderTable();
}

function prp_pickCat(i) {
    const gl = prp_cats[i] ? prp_cats[i].gl_code : '';
    prp_flt.cat = (prp_flt.cat === gl) ? '' : gl;           // click again to clear
    prp_apply();
}

function prp_renderCats() {
    if (!$prp('prp-cats')) return;                          // the register's category cards were replaced by the account cards (IAR items)
    $prp('prp-cats').innerHTML = prp_cats.map((c, i) => `
        <div class="prp-cat${prp_flt.cat && prp_flt.cat === c.gl_code ? ' active' : ''}" onclick="prp_pickCat(${i})" title="Click to filter the table by this category">
            <div class="nm">${prp_esc(c.category || 'Uncategorized')}</div>
            <div class="val">${prp_money(c.total_book)}</div>
            <div class="sub" style="display:flex;justify-content:space-between;gap:8px;">
                <span>Depr: ${prp_money(c.total_depr)}</span>
                <span style="white-space:nowrap;">${c.count} record${c.count === 1 ? '' : 's'}</span>
            </div>
        </div>`).join('');
}

function prp_renderTable() {
    const tbody = $prp('prp-tbody');
    const total = prp_view.length;
    if (!total) {
        tbody.innerHTML = '<tr><td colspan="23"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No items match the filters.</span></div></td></tr>';
        $prp('prp-tfoot').innerHTML = '';
        prp_renderPagination();
        return;
    }
    const offset = (prp_page - 1) * PRP_PAGE_SIZE;
    let lastRc = null;
    tbody.innerHTML = prp_view.slice(offset, offset + PRP_PAGE_SIZE).map((it, n) => {
        const par = it.row_type === 'PAR';
        const na  = '<span style="color:#94a3b8;">N/A</span>';
        const stack = (arr, fn) => (arr && arr.length ? arr : ['']).map(v => '<div style="margin:2px 0;">' + fn(v) + '</div>').join('');
        // the register loops per responsibility center: a "{center}:" line, then its items (the first column of the items stays blank)
        const grp = (it.rc_name || '') !== lastRc
            ? `<tr class="prp-grp"><td colspan="23">${prp_esc(it.rc_name || 'No responsibility center')}:</td></tr>` : '';
        lastRc = it.rc_name || '';
        const mid = (v, c) => `<td style="vertical-align:top;text-align:center;${c || ''}">${v}</td>`;
        return grp + `<tr>
            <td></td>
            ${mid(it.qty)}
            ${mid(prp_dash(it.unit))}
            <td style="vertical-align:top;"><div style="font-weight:600;">${prp_dash(it.description_full)}</div></td>
            ${mid(stack(it.departments, v => prp_dash(v)))}
            <td style="vertical-align:top;text-align:center;"><div class="prp-sub"><code class="nha-code">${prp_dash(it.iar_number)}</code></div><div class="prp-sub">PO#${prp_esc(it.saved_po)}</div></td>
            <td></td>
            ${mid(prp_date(it.date_acquired), 'white-space:nowrap;')}
            ${mid(stack(it.property_nos, v => '<code class="nha-code">' + prp_dash(v) + '</code>'))}
            <td style="vertical-align:top;" class="prp-money">${prp_money(it.cost)}</td>
            <td style="vertical-align:top;" class="prp-money">${prp_money(it.acq_cost)}</td>
            <td style="vertical-align:top;" class="prp-money">${prp_money(it.add_cost)}</td>
            <td style="vertical-align:top;" class="prp-money"><strong>${prp_money(it.total_cost)}</strong></td>
            <td style="vertical-align:top;text-align:center;">${it.life_years} years</td>
            <td style="vertical-align:top;" class="prp-money">${prp_money(it.salvage)}</td>
            <td style="vertical-align:top;color:#dc2626;" class="prp-money">${par ? prp_money(it.accum_begin) : na}</td>
            <td style="vertical-align:top;" class="prp-money">${par ? prp_money(it.depr_year) : na}</td>
            <td style="vertical-align:top;" class="prp-money">${par ? prp_money(it.monthly_depr) : na}</td>
            <td style="vertical-align:top;color:#dc2626;" class="prp-money">${par ? prp_money(it.accum_depr) : na}</td>
            <td style="vertical-align:top;color:#16a34a;font-weight:700;" class="prp-money">${prp_money(it.book_value)}</td>
            <td style="vertical-align:top;font-style:italic;font-size:11px;">${prp_esc(it.remarks)}</td>
            <td style="vertical-align:top;text-align:center;">${(it.statuses || [it.status]).map(c => prp_badge(c, (it.units.find(u => u.status === c) || {}).status_label)).join('<br>')}</td>
            <td style="vertical-align:top;text-align:center;white-space:nowrap;">
                <div class="prp-menu">
                    <button type="button" class="prp-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="prp_toggleMenu(this)">
                        <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        Actions
                    </button>
                    <div class="prp-menu-drop">
                        <button type="button" class="prp-menu-item" onclick="prp_closeMenus();prp_open(${prp_items.indexOf(it)});">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            View
                        </button>
                    </div>
                </div>
            </td>
        </tr>`;
    }).join('');
    prp_renderTotal();
    prp_renderPagination();
}

/* the grand total of the register: every row that matches the filters (not just this page) */
function prp_renderTotal() {
    const sum = k => prp_view.reduce((t, it) => t + Number(it[k] || 0), 0);
    const m = k => '<td class="prp-money">' + prp_money(sum(k)) + '</td>';
    $prp('prp-tfoot').innerHTML = `<tr>
        <td style="text-align:right;">GRAND TOTAL</td>
        <td colspan="11"></td>
        ${m('total_cost')}
        <td></td>
        ${m('salvage')}${m('accum_begin')}${m('depr_year')}${m('monthly_depr')}${m('accum_depr')}${m('book_value')}
        <td colspan="3"></td>
    </tr>`;
}

function prp_renderPagination() {
    const total = prp_view.length, last = Math.max(1, Math.ceil(total / PRP_PAGE_SIZE));
    $prp('prp-from').textContent  = total ? (prp_page - 1) * PRP_PAGE_SIZE + 1 : 0;
    $prp('prp-to').textContent    = Math.min(prp_page * PRP_PAGE_SIZE, total);
    $prp('prp-total').textContent = total;
    const box = $prp('prp-pagination');
    if (last <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, prp_page - 2); i <= Math.min(last, prp_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === prp_page ? ' active' : ''}" onclick="prp_goTo(${i})">${i}</button>`);
    }
    box.innerHTML = `<button class="nha-pag-btn${prp_page === 1 ? ' disabled' : ''}" onclick="prp_goTo(${prp_page - 1})">‹</button>` + pages.join('')
                  + `<button class="nha-pag-btn${prp_page === last ? ' disabled' : ''}" onclick="prp_goTo(${prp_page + 1})">›</button>`;
}
function prp_goTo(p) {
    const last = Math.max(1, Math.ceil(prp_view.length / PRP_PAGE_SIZE));
    if (p < 1 || p > last) return;
    prp_page = p; prp_renderTable();
}

/* ══════════════ ITEMS FROM THE IAR MODULE ══════════════
   GET /property-record/iar-items: the items entered in the IAR module for the property accounts. A row opens the item's details. */
let prp_iar = [];
let prp_iarGl = '';
const PRP_ACCOUNTS = [                                       // the property accounts: one row each, even with no item
    ['1-06-05-020', 'Office Equipment'], ['1-06-05-030', 'Info. And Comm. Tech. Equip.'], ['1-06-05-070', 'Communication Equipment'],
    ['1-06-05-080', 'Construction & Heavy Equip.'], ['1-06-05-110', 'Medical Equipment'], ['1-06-05-130', 'Sports Equipment'],
    ['1-06-05-990', 'Other Machinery & Equipment'], ['1-06-06-010', 'Motor Vehicles'], ['1-06-07-010', 'Furniture & Fixtures'],
    ['1-06-99-990', 'Other Property, Plant & Equipment'],
];
const prp_glOf = code => (String(code || '').match(/^(\d+-\d+-\d+-\d+)/) || [])[1] || '';
async function prp_loadIar() {
    try {
        const res  = await fetch(PRP_API + '/iar-items', { credentials: 'same-origin', headers: prp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the items of the IAR module.');
        prp_iar = json.data.items || [];
    } catch (e) {
        prp_iar = [];
        prp_toast(e.message, 'error');                         // the accounts are still listed (0.00)
    }
    // the searchable center list: the centers of the items (re-read on every load)
    const rcs = new Map(prp_iar.filter(it => it.rc_name).map(it => [it.rc_name, it.rc_id || it.rc_name]));
    nhaSearchableSelect($prp('prp-i-rc'), [...rcs.entries()].sort((a, b) => a[0].localeCompare(b[0])).map(([name, id]) => ({ value: String(id), label: name })),
        opt => { $prp('prp-i-rc').value = opt ? opt.label : ''; prp_iarF.rc = opt ? opt.label : ''; prp_renderIar(); });
    if (prp_iarF.rc && !rcs.has(prp_iarF.rc)) { prp_iarF.rc = ''; $prp('prp-i-rc').value = ''; }
    prp_renderIar();
}
/* Depreciation of an item (straight line; the register's convention: whole months used, 10% residual value):
     Monthly Depreciation     = Acquisition Cost x .9 / useful life in months   (360 building, 120 land improvement, 60 equipment)
     Accumulated Depreciation = Monthly Depreciation x number of months used (acquisition date → the "as of" date), never above 90% of the cost
     Net Book Value           = Acquisition Cost - Accumulated Depreciation
   The property accounts are equipment (60 months); the GL code decides the life, so a building / land improvement account would too. */
function prp_lifeMonths(code) { return /^1-06-04/.test(code) ? 360 : /^1-06-03/.test(code) ? 120 : 60; }
const prp_pad = n => String(n).padStart(2, '0');
// "as of": the current date (the net book value is always as of today)
const prp_asOf = (() => { const n = new Date(); return n.getFullYear() + '-' + prp_pad(n.getMonth() + 1) + '-' + prp_pad(n.getDate()); })();
function prp_asOfLabel() { const [y, m, d] = prp_asOf.split('-').map(Number); return m + '/' + d + '/' + y; }
// Depreciation starts the MONTH AFTER the acquisition (delivery) date: acquired Jan 1, 2026 → the first month is February 2026.
// So the months used are the calendar months after the acquisition month, up to and including the month of the "as of" date.
function prp_monthsUsed(from, to) {
    const a = String(from || '').slice(0, 10).split('-').map(Number), b = String(to).split('-').map(Number);
    if (a.length < 3 || a.some(isNaN)) return 0;
    return Math.max(0, (b[0] - a[0]) * 12 + (b[1] - a[1]));
}
function prp_depr(it) {
    const cost = Number(it.amount || 0), r2 = v => Math.round(v * 100) / 100;
    const monthly = r2(cost * 0.9 / prp_lifeMonths(it.account_code));
    const accum   = Math.min(r2(monthly * prp_monthsUsed(it.date_acquired, prp_asOf)), r2(cost * 0.9));
    // fully depreciated: the whole 90% is depreciated (after the useful life, e.g. 60 months for equipment) → the item's Remarks say so
    return { residual: r2(cost * 0.1), monthly, accum, nbv: r2(cost - accum), fully: cost > 0 && accum >= r2(cost * 0.9) };
}
/* ══════════════ PRO-FORMA JEV: depreciation of a quarter ══════════════
   Per property account (category): the depreciation of its items within the quarter is DEBITED to the depreciation expense account
   (+ its SL) and CREDITED to the accumulated depreciation account. Depreciation of an item within a quarter = its accumulated
   depreciation at the end of the quarter - at the end of the previous quarter (same formula as the item table above). */
const PRP_JEV_CFG = {                                        // GL code of the category → debit (expense) account + SL, credit (accumulated depreciation) account
    '1-06-05-020': { d: '5-05-01-050', sl: '02', c: '1-06-05-021' },
    '1-06-05-030': { d: '5-05-01-050', sl: '03', c: '1-06-05-031' },
    '1-06-05-070': { d: '5-05-01-050', sl: '07', c: '1-06-05-071' },
    '1-06-05-080': { d: '5-05-01-050', sl: '08', c: '1-06-05-081' },
    '1-06-05-110': { d: '5-05-01-050', sl: '11', c: '1-06-05-111' },
    '1-06-05-130': { d: '5-05-01-050', sl: '13', c: '1-06-05-131' },
    '1-06-05-990': { d: '5-05-01-050', sl: '99', c: '1-06-05-991' },
    '1-06-06-010': { d: '5-05-01-060', sl: '01', c: '1-06-06-011' },
    '1-06-07-010': { d: '5-05-01-070', sl: '',   c: '1-06-07-011' },
    '1-06-99-990': { d: '5-05-01-990', sl: '',   c: '1-06-99-991' },
};
const PRP_QUARTERS = { 1: ['1st', 'January - March'], 2: ['2nd', 'April - June'], 3: ['3rd', 'July - September'], 4: ['4th', 'October - December'] };
let prp_pfBooks = null, prp_pfTitles = {}, prp_pfPartEdited = false;
const prp_ymd = d => d.getFullYear() + '-' + prp_pad(d.getMonth() + 1) + '-' + prp_pad(d.getDate());
function prp_accumAt(it, date) {                             // accumulated depreciation of an item at a date
    const cost = Number(it.amount || 0), r2 = v => Math.round(v * 100) / 100, monthly = r2(cost * 0.9 / prp_lifeMonths(it.account_code));
    return Math.min(r2(monthly * prp_monthsUsed(it.date_acquired, date)), r2(cost * 0.9));
}
function prp_pfRows() {
    const q = Number($prp('prp-pf-q').value), y = Number($prp('prp-pf-y').value);
    const end = prp_ymd(new Date(y, q * 3, 0)), prev = prp_ymd(new Date(y, (q - 1) * 3, 0));   // last day of the quarter / of the previous quarter
    const byGl = new Map();
    prp_iar.forEach(it => {
        const gl = prp_glOf(it.account_code);
        if (!PRP_JEV_CFG[gl]) return;
        byGl.set(gl, Math.round(((byGl.get(gl) || 0) + prp_accumAt(it, end) - prp_accumAt(it, prev)) * 100) / 100);
    });
    const debits = new Map(), credits = new Map();
    PRP_ACCOUNTS.forEach(([gl]) => {
        const amt = byGl.get(gl) || 0, c = PRP_JEV_CFG[gl];
        if (amt <= 0) return;
        const dCode = c.d + (c.sl ? '-' + c.sl : '');
        debits.set(dCode, Math.round(((debits.get(dCode) || 0) + amt) * 100) / 100);
        credits.set(c.c, Math.round(((credits.get(c.c) || 0) + amt) * 100) / 100);
    });
    return { debits: [...debits.entries()], credits: [...credits.entries()] };
}
function prp_pfParticulars() {
    const q = Number($prp('prp-pf-q').value), y = $prp('prp-pf-y').value, b = $prp('prp-pf-book');
    const book = b.value ? b.options[b.selectedIndex].text : '';
    return `To take up Depreciation Cost of PPE for the ${PRP_QUARTERS[q][0]} Quarter (${PRP_QUARTERS[q][1]}) ${y}${book ? ' ' + book : ''}`;
}
/* The pro-forma rows are kept in prp_pfState = [{ code, title, debit, credit }]: they start from the quarter's depreciation (debits, then
   credits) and can then be edited like the IAR module's pro-forma (account, a debit OR a credit amount, add / remove a row).
   Changing the quarter or the year rebuilds them from the depreciation; changing the book only changes the particulars. */
let prp_pfState = [], prp_pfAccounts = null;
/* the depreciation accounts offered first: every expense (+ SL) and accumulated-depreciation code of PRP_JEV_CFG, expense accounts first */
function prp_pfDepOptions() {
    const codes = [...new Set([...Object.values(PRP_JEV_CFG).map(c => c.d + (c.sl ? '-' + c.sl : '')), ...Object.values(PRP_JEV_CFG).map(c => c.c)])];
    return codes.map(code => ({ value: code, label: ((prp_pfAccounts || []).find(a => a.value === code) || {}).label || prp_pfTitles[code] || '' }));
}
const prp_num = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const prp_unfmt = s => parseFloat(String(s || '').replace(/,/g, '')) || 0;
function prp_pfBuild() {
    const { debits, credits } = prp_pfRows();
    prp_pfState = [...debits.map(([c, v]) => ({ code: c, title: prp_pfTitles[c] || '', debit: v, credit: 0 })),
                   ...credits.map(([c, v]) => ({ code: c, title: prp_pfTitles[c] || '', debit: 0, credit: v }))];
}
function prp_pfRender() {
    if (!prp_pfPartEdited) $prp('prp-pf-part').value = prp_pfParticulars();
    const lbl = r => r.code ? r.code + (r.title ? ' - ' + r.title : '') : '';
    $prp('prp-pf-rows').innerHTML = prp_pfState.length ? prp_pfState.map((r, i) => `<div class="prp-jev-row" data-i="${i}">
            <input type="text" class="nha-filter-input prp-pf-acct" placeholder="Search account code or title" value="${prp_esc(lbl(r))}" autocomplete="off">
            <input type="text" inputmode="decimal" class="nha-filter-input prp-jev-num prp-pf-d" placeholder="0.00" value="${r.debit > 0 ? prp_num(r.debit) : ''}">
            <input type="text" inputmode="decimal" class="nha-filter-input prp-jev-num prp-pf-c" placeholder="0.00" value="${r.credit > 0 ? prp_num(r.credit) : ''}">
            <button type="button" class="prp-jev-del" title="Remove row">&times;</button></div>`).join('')
        : '<div class="prp-jev-row" style="display:block;"><div class="prp-empty" style="padding:14px;"><strong>No depreciation</strong>None of the items has depreciation in this quarter. Add rows to enter an entry manually.</div></div>';
    document.querySelectorAll('#prp-pf-rows .prp-jev-row[data-i]').forEach(el => {
        const i = Number(el.dataset.i), row = prp_pfState[i], acct = el.querySelector('.prp-pf-acct');
        const d = el.querySelector('.prp-pf-d'), c = el.querySelector('.prp-pf-c');
        // account: type to filter the chart of accounts (loaded once), click one to pick it
        // With nothing typed the list offers ONLY the depreciation accounts (the expense + accumulated depreciation accounts of PRP_JEV_CFG, like the IAR
        // module's picker offers the property accounts); once the user types, the whole chart of accounts is searched. These two listeners are added
        // BEFORE nhaSearchableSelect so the option list is switched before the dropdown draws it.
        const allOpts = prp_pfAccounts || [];
        const depOpts = prp_pfDepOptions();
        const pickList = () => { acct._nhaOptions = acct.value.trim() ? allOpts : depOpts; };
        acct.addEventListener('focus', () => { acct.value = ''; acct._nhaOptions = depOpts; });     // clicking into the field clears it to start a search
        acct.addEventListener('input', pickList);
        nhaSearchableSelect(acct, depOpts, opt => { if (opt) { row.code = opt.value; row.title = opt.label; } acct.value = lbl(row); prp_pfRefresh(); });
        acct.addEventListener('blur', () => setTimeout(() => { acct.value = lbl(row); }, 150));       // half-typed text goes back to the picked account
        // a row is either a debit or a credit: typing in one clears the other
        d.addEventListener('input', () => { row.debit = prp_unfmt(d.value); if (d.value) { c.value = ''; row.credit = 0; } prp_pfRefresh(); });
        c.addEventListener('input', () => { row.credit = prp_unfmt(c.value); if (c.value) { d.value = ''; row.debit = 0; } prp_pfRefresh(); });
        d.addEventListener('blur', () => { if (d.value) d.value = prp_num(prp_unfmt(d.value)); });
        c.addEventListener('blur', () => { if (c.value) c.value = prp_num(prp_unfmt(c.value)); });
        el.querySelector('.prp-jev-del').addEventListener('click', () => { prp_pfState.splice(i, 1); prp_pfRender(); });
    });
    // already submitted as a JEV: read-only — no editing, no Add row, no remove, and it cannot be submitted again
    const locked = !!prp_pfLock;
    document.querySelectorAll('#prp-pf-rows input').forEach(i => { i.disabled = locked; });
    document.querySelectorAll('#prp-pf-rows .prp-jev-del').forEach(b => { b.style.display = locked ? 'none' : ''; });
    $prp('prp-pf-add').style.display = locked ? 'none' : '';
    $prp('prp-pf-part').disabled = locked;
    prp_pfRefresh();
}
/* The JEV this quarter's pro-forma was already submitted as (same book + the standard particulars, or the typed ones). When there is one the
   pro-forma shows what was submitted, read-only, like the IAR module's pro-forma; changing the quarter / year / book looks again. */
let prp_pfLock = null, prp_pfSeq = 0, prp_pfTimer = null;
async function prp_pfCheck() {
    const seq = ++prp_pfSeq, book = $prp('prp-pf-book').value, base = PRP_API.replace(/\/property-record$/, '');
    let found = null;
    if (book) {
        const qs = new URLSearchParams({ book_id: book });
        [prp_pfParticulars(), $prp('prp-pf-part').value.trim()].forEach(t => { if (t) qs.append('p[]', t); });
        try {
            const res = await fetch(PRP_API + '/proforma-jev?' + qs.toString(), { credentials: 'same-origin', headers: prp_authHeaders() });
            const json = await res.json();
            found = json.success ? json.data : null;
        } catch (e) { found = null; }
    }
    if (seq !== prp_pfSeq) return;                            // a newer check is on its way
    if (!found) {
        const was = !!prp_pfLock;
        prp_pfLock = null;
        if (was) { prp_pfPartEdited = false; prp_pfBuild(); prp_pfRender(); } else prp_pfRefresh();
        return;
    }
    let lock = { ...found, status: '', jev_date: '' };
    try {                                                     // what was submitted: its own lines, particulars and date
        const jr = await fetch(base + '/voucher/journal-entry/' + encodeURIComponent(found.jev_id), { credentials: 'same-origin', headers: prp_authHeaders() });
        const jj = await jr.json();
        if (jj.success) {
            lock.status = jj.data.jev_status || ''; lock.jev_date = jj.data.jev_date || '';
            prp_pfState = (jj.data.lines || []).map(l => ({ code: l.account_code, title: l.account_title || '', debit: Number(l.debit) || 0, credit: Number(l.credit) || 0 }));
            if (jj.data.particulars) { $prp('prp-pf-part').value = jj.data.particulars; }
        }
    } catch (e) { /* the pro-forma rows stay (locked) */ }
    if (seq !== prp_pfSeq) return;
    prp_pfLock = lock; prp_pfPartEdited = true;               // keep the submitted particulars on screen
    prp_pfRender();
}
/* totals + the message under the rows + the Submit / Print buttons */
function prp_pfError() {
    const cents = k => Math.round(prp_pfState.reduce((s, r) => s + (Number(r[k]) || 0), 0) * 100);
    if (prp_pfState.length < 2) return 'A journal entry needs at least two rows (a debit and a credit).';
    if (prp_pfState.some(r => !r.code || !((Number(r.debit) > 0) || (Number(r.credit) > 0)))) return 'Choose an account code and enter a debit or a credit amount for every row.';
    if (cents('debit') !== cents('credit')) return 'Not balanced: total debit ₱' + prp_num(cents('debit') / 100) + ' and total credit ₱' + prp_num(cents('credit') / 100) + ' are not equal.';
    return '';
}
function prp_pfRefresh() {
    const sum = k => prp_pfState.reduce((s, r) => s + (Number(r[k]) || 0), 0);
    $prp('prp-pf-td').textContent = prp_num(sum('debit'));
    $prp('prp-pf-tc').textContent = prp_num(sum('credit'));
    if (prp_pfLock) {                                         // already submitted: read-only, it cannot be submitted again
        const b = $prp('prp-pf-submit');
        b.disabled = true; b.style.display = 'none';           // no Submit button once submitted: only Print JEV is left
        $prp('prp-pf-print').disabled = false;
        $prp('prp-pf-msg').innerHTML = 'Submitted as Journal Entry Voucher <strong>' + prp_esc(prp_pfLock.jev_no) + '</strong>'
            + (prp_pfLock.status ? ' (' + prp_esc(String(prp_pfLock.status).replace(/_/g, ' ').toLowerCase()) + ')' : '') + ' — read-only. <a href="JournalEntryVoucher">Open Journal Entry Vouchers</a>';
        return;
    }
    $prp('prp-pf-submit').style.display = '';
    const err = prp_pfError();
    $prp('prp-pf-msg').textContent = err;
    $prp('prp-pf-submit').disabled = !!err;
    $prp('prp-pf-print').disabled = !!err;
}
/* the JEV lines / header as the print form and the submit call take them */
function prp_pfPayload() {
    const q = Number($prp('prp-pf-q').value), y = Number($prp('prp-pf-y').value), b = $prp('prp-pf-book');
    return {
        jev_date: prp_ymd(new Date(y, q * 3, 0)),            // the last day of the quarter
        book_id: b.value, office: b.value ? b.options[b.selectedIndex].text : '',
        particulars: $prp('prp-pf-part').value.trim(),
        lines: prp_pfState.map(r => ({ account_code: r.code, account_title: r.title, debit: Number(r.debit) || 0, credit: Number(r.credit) || 0 })),
    };
}
async function prp_pfSubmit() {
    const err = prp_pfError(), p = prp_pfPayload();
    if (err) { prp_toast(err, 'warning'); return; }
    if (!p.book_id) { prp_toast('Select a Book before submitting.', 'warning'); $prp('prp-pf-book').focus(); return; }
    const text = 'It is submitted for approval right away (Section Chief review).';
    if (window.Swal) {
        const ok = await window.Swal.fire({ icon: 'question', title: 'Submit this Journal Entry Voucher?', text, showCancelButton: true,
            confirmButtonText: 'Submit', cancelButtonText: 'Cancel', confirmButtonColor: '#0f2045' });
        if (!ok.isConfirmed) return;
    } else if (!confirm('Submit this Journal Entry Voucher? ' + text)) return;
    const btn = $prp('prp-pf-submit'); btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await fetch(PRP_API + '/proforma-submit', { method: 'POST', credentials: 'same-origin', headers: prp_authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ jev_date: p.jev_date, book_id: p.book_id, particulars: p.particulars, jev_classification: 'REGULAR',
                lines: prp_pfState.map(r => ({ account_code: r.code, rc_id: '', debit: Number(r.debit) || 0, credit: Number(r.credit) || 0 })) }) });
        const json = await res.json();
        if (!json.success) throw new Error((json.errors && json.errors.length ? json.errors.join('\n') : null) || json.message || 'Failed to submit the Journal Entry Voucher.');
        prp_toast(json.message || 'Journal Entry Voucher submitted for approval.', 'success');
        $prp('prp-pf-overlay').classList.remove('open');
        window.location.href = 'JournalEntryVoucher';          // continue on the Journal Entry Vouchers page
    } catch (e) {
        prp_toast(e.message, 'error');
        prp_pfRefresh();
    } finally {
        window.PagePreloader?.hide();
    }
}
/* Print JEV: the JEV form rendered by POST {api}/print/jev (the same call as the print preview of the other modules) */
let prp_pvPayload = null;
const prp_pvUrl = () => PRP_API.replace(/\/property-record$/, '') + '/print/jev';
async function prp_pfPrint() {
    const p = prp_pfPayload();
    prp_pvPayload = { office: p.office, jev_date: p.jev_date, particulars: p.particulars, lines: p.lines,
        ...(prp_pfLock ? { jev_number: prp_pfLock.jev_no, jev_date: prp_pfLock.jev_date || p.jev_date } : {}) };   // a submitted JEV prints with its number and date
    $prp('prp-pv-overlay').classList.add('open');
    $prp('prp-pv-frame').style.display = 'none';
    $prp('prp-pv-msg').style.display = ''; $prp('prp-pv-msg').textContent = 'Loading preview…';
    $prp('prp-pv-pdf').disabled = true;
    try {
        const res  = await fetch(prp_pvUrl(), { method: 'POST', credentials: 'same-origin', headers: prp_authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ ...prp_pvPayload, format: 'html' }) });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'The preview could not be loaded.');
        const frame = $prp('prp-pv-frame');
        frame.srcdoc = json.html; frame.style.display = ''; $prp('prp-pv-msg').style.display = 'none';
        $prp('prp-pv-pdf').disabled = false;
    } catch (e) { $prp('prp-pv-msg').textContent = e.message; }
}
async function prp_pfPdf() {
    const btn = $prp('prp-pv-pdf'), label = btn.textContent;
    btn.disabled = true; btn.textContent = 'Generating…';
    try {
        const res  = await fetch(prp_pvUrl(), { method: 'POST', credentials: 'same-origin', headers: prp_authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ ...prp_pvPayload, format: 'pdf' }) });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'The PDF could not be generated.');
        const bin = atob(json.data), bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([bytes], { type: json.mime })); a.download = json.filename;
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    } catch (e) { prp_toast(e.message, 'error'); }
    finally { btn.textContent = label; btn.disabled = false; }
}
async function prp_pfOpen() {
    $prp('prp-pf-overlay').classList.add('open');
    if (!$prp('prp-pf-y').value) {                           // default: the latest completed quarter
        const n = new Date(), q = Math.floor(n.getMonth() / 3);
        $prp('prp-pf-q').value = q === 0 ? 4 : q;
        $prp('prp-pf-y').value = q === 0 ? n.getFullYear() - 1 : n.getFullYear();
    }
    if (prp_pfBooks === null) {                              // fims.books, once (the same list as the IAR module's pro-forma)
        try {
            const res = await fetch(PRP_API.replace(/\/property-record$/, '') + '/books/all', { credentials: 'same-origin', headers: prp_authHeaders() });
            const json = await res.json();
            prp_pfBooks = json.success ? (json.data || []) : [];
        } catch (e) { prp_pfBooks = []; }
        const main = prp_pfBooks.find(b => String(b.book_id) === '1' || b.book_code === 'MAIN-OFFICE');
        $prp('prp-pf-book').innerHTML = '<option value="">Select a book…</option>' + prp_pfBooks.map(b => `<option value="${prp_esc(b.book_id)}">${prp_esc(b.name)}</option>`).join('');
        if (main) $prp('prp-pf-book').value = String(main.book_id);      // Main Office unless the user picks another
    }
    // account titles of every code the pro-forma can use
    const codes = Object.values(PRP_JEV_CFG).flatMap(c => [c.d + (c.sl ? '-' + c.sl : ''), c.c]);
    try {
        const res = await fetch(PRP_API + '/account-titles?codes=' + encodeURIComponent(codes.join(',')), { credentials: 'same-origin', headers: prp_authHeaders() });
        const json = await res.json();
        if (json.success) prp_pfTitles = json.data || {};
    } catch (e) { /* the codes alone are shown */ }
    if (prp_pfAccounts === null) {                           // the accounts the rows can pick: active, postable, and a GL that has sub-ledgers is replaced by them
        try {
            const res = await fetch(PRP_API.replace(/\/property-record$/, '') + '/chart-of-accounts/all', { credentials: 'same-origin', headers: prp_authHeaders() });
            const json = await res.json();
            const on  = v => v === true || v === 't' || v === 'true' || v === 1;
            const act = (json.success ? json.data || [] : []).filter(a => on(a.posting_allowed) && String(a.status || 'Active').toLowerCase() === 'active');
            const parents = new Set(act.map(a => a.parent_account_id).filter(Boolean));
            prp_pfAccounts = act.filter(a => !parents.has(a.account_id)).map(a => ({ value: a.account_code, label: a.account_title }));
        } catch (e) { prp_pfAccounts = []; }
    }
    prp_pfPartEdited = false;
    prp_pfLock = null;
    prp_pfBuild();
    prp_pfRender();
    prp_pfCheck();                                           // locks it when this quarter was already submitted
}
function prp_bindPf() {
    $prp('prp-pf').addEventListener('click', prp_pfOpen);
    $prp('prp-pf-close').addEventListener('click', () => $prp('prp-pf-overlay').classList.remove('open'));
    $prp('prp-pf-overlay').addEventListener('mousedown', e => { if (e.target === $prp('prp-pf-overlay')) $prp('prp-pf-overlay').classList.remove('open'); });
    // the book only changes the particulars; the quarter / year rebuild the rows from that quarter's depreciation
    $prp('prp-pf-book').addEventListener('change', () => { prp_pfRender(); prp_pfCheck(); });
    ['prp-pf-q', 'prp-pf-y'].forEach(id => $prp(id).addEventListener('change', () => { prp_pfLock = null; prp_pfPartEdited = false; prp_pfBuild(); prp_pfRender(); prp_pfCheck(); }));
    $prp('prp-pf-part').addEventListener('input', () => {                                  // a typed particulars is kept (and checked against the submitted JEVs)
        prp_pfPartEdited = true; clearTimeout(prp_pfTimer); prp_pfTimer = setTimeout(prp_pfCheck, 500);
    });
    $prp('prp-pf-add').addEventListener('click', () => { prp_pfState.push({ code: '', title: '', debit: 0, credit: 0 }); prp_pfRender(); const a = document.querySelectorAll('#prp-pf-rows .prp-pf-acct'); a[a.length - 1]?.focus(); });
    $prp('prp-pf-submit').addEventListener('click', prp_pfSubmit);
    $prp('prp-pf-print').addEventListener('click', prp_pfPrint);
    $prp('prp-pv-close').addEventListener('click', () => $prp('prp-pv-overlay').classList.remove('open'));
    $prp('prp-pv-done').addEventListener('click', () => $prp('prp-pv-overlay').classList.remove('open'));
    $prp('prp-pv-pdf').addEventListener('click', prp_pfPdf);
}
const prp_iarF = { rc: '', gl: '', q: '' };                  // filters of the list: responsibility center, account code, search text
function prp_bindIar() {
    // Responsibility Center / Account Code: type to filter the list, click one to pick it; emptying the box shows all again
    nhaSearchableSelect($prp('prp-i-gl'), PRP_ACCOUNTS.map(([c, n]) => ({ value: c, label: n })),
        opt => { $prp('prp-i-gl').value = opt ? opt.value + ' — ' + opt.label : ''; prp_iarF.gl = opt ? opt.value : ''; prp_renderIar(); });
    [['prp-i-rc', 'rc'], ['prp-i-gl', 'gl']].forEach(([id, key]) => $prp(id).addEventListener('input', e => {
        if (!e.target.value.trim() && prp_iarF[key]) { prp_iarF[key] = ''; prp_renderIar(); }
    }));
    $prp('prp-i-expand').addEventListener('click', prp_expandAllIar);
    let timer;
    $prp('prp-i-q').addEventListener('input', e => {
        $prp('prp-i-clear').hidden = !e.target.value;
        clearTimeout(timer);
        timer = setTimeout(() => { prp_iarF.q = e.target.value.trim().toLowerCase(); prp_renderIar(); }, 250);
    });
    $prp('prp-i-clear').addEventListener('click', () => { $prp('prp-i-q').value = ''; $prp('prp-i-clear').hidden = true; prp_iarF.q = ''; prp_renderIar(); });
}
function prp_renderIar() {
    // the list: one row per account (Account | Account Code | Amount) with an arrow that opens its items, like the delivery receipts of the IAR module
    // every property account is listed, with 0.00 when it has no item (an item posted to a sub-ledger counts under its GL account);
    // the filters narrow the items, and the Account Code filter narrows the accounts. While a center / search filter is on, accounts without a match are left out.
    const f = prp_iarF, narrowing = !!(f.rc || f.q);
    const match = it => (!f.rc || it.rc_name === f.rc)
        && (!f.q || [it.rc_name, it.account_code, it.account_title, it.description, it.serial_no, it.property_no, it.po_number, it.iar_number].some(v => String(v || '').toLowerCase().includes(f.q)));
    const list = PRP_ACCOUNTS.filter(([code]) => !f.gl || code === f.gl).map(([code, title]) => {
        const items = prp_iar.map((it, i) => [it, i]).filter(([it]) => prp_glOf(it.account_code) === code && match(it));
        // total cost, accumulated depreciation and net book value (as of today) of the account's items
        const dep = items.map(([it]) => prp_depr(it));
        return [code, { title, items, total: items.reduce((s, [it]) => s + Number(it.amount || 0), 0),
            accum: dep.reduce((s, d) => s + d.accum, 0), nbv: dep.reduce((s, d) => s + d.nbv, 0) }];
    }).filter(([, g]) => !narrowing || g.items.length);
    // amount cell: the peso sign at the left of the cell, the number at the right; only the FIRST account and the TOTAL show the sign
    const numFmt = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const amtCell = (v, peso, bold) => `<td style="text-align:right;white-space:nowrap;${bold ? 'font-weight:700;' : ''}">${peso ? '<span class="prp-pl">₱</span>' : ''}${numFmt(v)}</td>`;
    // the cards (three to a row) — of the items in the list, as of today: depreciation of the current quarter and of the year to date,
    // accumulated depreciation, and the fully depreciated items (their number and acquisition cost)
    {
        const today = prp_asOf, y = Number(today.slice(0, 4)), q = Math.floor((Number(today.slice(5, 7)) - 1) / 3);
        const qPrev = prp_ymd(new Date(y, q * 3, 0)), yPrev = (y - 1) + '-12-31', r2 = v => Math.round(v * 100) / 100;
        const all = list.flatMap(([, g]) => g.items.map(([it]) => it));
        let quarterly = 0, ytd = 0, accum = 0, fullyN = 0;
        all.forEach(it => {
            const a = prp_accumAt(it, today), cost = Number(it.amount || 0);
            quarterly += a - prp_accumAt(it, qPrev);
            ytd       += a - prp_accumAt(it, yPrev);
            accum     += a;
            if (cost > 0 && a >= r2(cost * 0.9)) fullyN++;                             // the whole 90% is depreciated
        });
        const card = (cls, label, val, sub) => `<div class="nha-sc ${cls}"><div class="nha-sc-top"><div><div class="nha-sc-label">${label}</div><div class="nha-sc-val">${val}</div></div></div><p class="nha-kpi-sub">${sub}</p></div>`;
        $prp('prp-iar-kpis').innerHTML =
            card('nha-sc-navy',  'QUARTERLY DEPRECIATION', prp_money(r2(quarterly)), PRP_QUARTERS[q + 1][0] + ' Quarter (' + PRP_QUARTERS[q + 1][1] + ') ' + y + ', to date') +
            card('nha-sc-teal',  'YEAR-TO-DATE DEPRECIATION', prp_money(r2(ytd)), 'January 1 – ' + prp_asOfLabel()) +
            card('nha-sc-amber', 'ACCUMULATED DEPRECIATION', prp_money(r2(accum)), 'As of ' + prp_asOfLabel()) +
            card('nha-sc-green', 'FULLY DEPRECIATED', fullyN, 'Fully depreciated items only') +
            card('nha-sc-navy',  '# OF ITEMS', all.length, 'All the items listed') +
            card('nha-sc-teal',  'AMOUNT', prp_money(r2(all.reduce((s, it) => s + Number(it.amount || 0), 0))), 'Total amount (acquisition cost) of the items');
    }
    const grand = list.reduce((s, [, g]) => s + g.total, 0), grandAccum = list.reduce((s, [, g]) => s + g.accum, 0), grandNbv = list.reduce((s, [, g]) => s + g.nbv, 0);
    $prp('prp-iar-tbody').innerHTML = list.map(([code, g], idx) => {
        const has  = g.items.length > 0;
        const open = has && prp_iarOpen.has(code);
        // the whole account row opens / closes its items (the arrow only shows the state)
        return `<tr${has ? ` style="cursor:pointer;" onclick="prp_toggleIar('${prp_esc(code)}')" title="Click to ${open ? 'hide' : 'show'} the items"` : ''}>
            <td>${has ? `<button type="button" class="prp-v-toggle" aria-expanded="${open}" tabindex="-1">${open ? '&#9662;' : '&#9656;'}</button>` : ''}</td>
            <td style="font-weight:700;">${prp_dash(g.title)}</td>
            <td style="text-align:center;"><code class="nha-code">${prp_esc(code)}</code></td>
            ${amtCell(g.total, idx === 0, false)}
            <td style="text-align:center;"><code class="nha-code">${prp_esc((PRP_JEV_CFG[code] || {}).c || '')}</code></td>
            ${amtCell(g.accum, idx === 0, false)}
            ${amtCell(g.nbv, idx === 0, false)}
        </tr>` + (open ? `<tr class="prp-v-sub"><td colspan="7"><div class="prp-v-inner"><table class="prp-v-tbl">
            <thead><tr><th>Description - Item</th><th>Serial No.</th><th>Property No.</th><th>Responsibility Center</th><th>JEV Reference/Date **</th><th>Acq. Date</th><th style="text-align:right;">Acq. Cost</th><th style="text-align:right;">Residual<br>Value</th><th style="text-align:right;">Monthly<br>Depr'n.</th><th style="text-align:right;">Accumulated<br>Depreciation</th><th style="text-align:right;">Net Book Value<br>as of ${prp_asOfLabel()}</th><th>Remarks *****</th></tr></thead>
            <tbody>${g.items.map(([it, i]) => `<tr style="cursor:pointer;" onclick="prp_openIar(${i})" title="Click to see the details of this item">
                <td>${prp_dash(it.description)}</td><td>${prp_dash(it.serial_no)}</td><td>${prp_dash(it.property_no)}</td>
                <td>${prp_dash(it.rc_name)}</td>
                <td style="white-space:nowrap;"><div>${it.jev_no ? '<code class="nha-code">' + prp_esc(it.jev_no) + '</code>' : '—'}</div>
                    <div class="prp-sub">PO# ${prp_dash(it.po_number)}</div></td>
                <td style="white-space:nowrap;">${prp_date(it.date_acquired)}</td>
                <td style="text-align:right;white-space:nowrap;">${prp_money(it.amount)}</td>` + (d => `<td style="text-align:right;white-space:nowrap;">${prp_money(d.residual)}</td>
                <td style="text-align:right;white-space:nowrap;">${prp_money(d.monthly)}</td>
                <td style="text-align:right;white-space:nowrap;">${prp_money(d.accum)}</td>
                <td style="text-align:right;white-space:nowrap;">${prp_money(d.nbv)}</td>
                <td style="white-space:nowrap;">${d.fully ? '<em>Fully Depreciated</em>' : ''}</td></tr>`)(prp_depr(it))).join('')}</tbody></table></div></td></tr>` : '');
    }).join('') + (list.length ? `<tr style="background:var(--afims-surface-subtle, #F4F7FA);"><td></td><td style="font-weight:700;">TOTAL</td><td></td>${amtCell(grand, true, true)}<td></td>${amtCell(grandAccum, true, true)}${amtCell(grandNbv, true, true)}</tr>` : '');
    // the button reads "Collapse All" once every account with items is open
    const withItems = list.filter(([, g]) => g.items.length);
    const btn = $prp('prp-i-expand');
    btn.textContent = withItems.length && withItems.every(([c]) => prp_iarOpen.has(c)) ? 'Collapse All' : 'Expand All';
    btn.disabled = !withItems.length;
}
const prp_iarOpen = new Set();                                // the accounts whose items are shown
// Expand All / Collapse All: opens the items of every account in the list (that has items), or closes them all
function prp_expandAllIar() {
    const codes = [...document.querySelectorAll('#prp-iar-tbody tr[onclick]')].map(tr => (tr.getAttribute('onclick').match(/'([^']+)'/) || [])[1]).filter(Boolean);
    const allOpen = codes.length && codes.every(c => prp_iarOpen.has(c));
    codes.forEach(c => allOpen ? prp_iarOpen.delete(c) : prp_iarOpen.add(c));
    prp_renderIar();
}
function prp_toggleIar(code) { prp_iarOpen.has(code) ? prp_iarOpen.delete(code) : prp_iarOpen.add(code); prp_renderIar(); }

function prp_openIar(i) {
    const it = prp_iar[i];
    if (!it) return;
    const row  = (k, v) => `<div class="prp-md-row"><div class="prp-md-k">${k}</div><div class="prp-md-v">${v}</div></div>`;
    $prp('prp-md-title').firstElementChild.textContent = 'Item Details';
    $prp('prp-md-ref').textContent = it.account_code || '';
    $prp('prp-md-status').innerHTML = '';
    $prp('prp-modal-body').innerHTML = `<div class="prp-md-card">` +
        row('Description - Item', prp_dash(it.description)) +
        row('Responsibility Center', prp_dash(it.rc_name)) +
        row('Acq. Date', prp_date(it.date_acquired)) +
        row('Serial No.', prp_dash(it.serial_no)) +
        row('Prop. No.', prp_dash(it.property_no)) +
        row('Acquisition Cost', `<span class="prp-md-num">${prp_money(it.amount)}</span>`) +
        `</div>`;
    $prp('prp-overlay').classList.add('open');
}

/* details of a saved record (read-only) — the same design as the Memo Request details */
function prp_open(i) {
    const it = prp_items[i];
    if (!it) return;
    $prp('prp-md-title').firstElementChild.textContent = 'Property Record Details';
    const row  = (k, v) => `<div class="prp-md-row"><div class="prp-md-k">${k}</div><div class="prp-md-v">${v}</div></div>`;
    const card = (title, inner) => `<div class="prp-md-card"><h4 class="prp-md-card-title">${title}</h4>${inner}</div>`;
    const par  = it.row_type === 'PAR';
    const num  = v => `<span class="prp-md-num">${prp_money(v)}</span>`;
    const units = it.units || [];

    $prp('prp-md-ref').textContent = it.iar_number || it.saved_po || '';
    $prp('prp-md-status').innerHTML = (it.statuses || [it.status]).map(c => prp_badge(c, (units.find(u => u.status === c) || {}).status_label || it.status_label)).join(' ');

    $prp('prp-modal-body').innerHTML =
        card('Property Record',
            row('Description', prp_dash(it.description_full)) +
            row('Category', prp_dash(it.category) + (it.gl_code ? ` <span class="prp-md-muted">(${prp_esc(it.gl_code)})</span>` : '')) +
            row('Sub Category', prp_dash(it.sub_category)) +
            row('Quantity', prp_esc(it.qty) + ' ' + prp_esc(String(it.unit || '').toLowerCase()))) +
        card('Purchase Order',
            row('IAR Number', it.iar_number ? `<span class="prp-md-code">${prp_esc(it.iar_number)}</span>` : '<span class="prp-md-muted">—</span>') +
            row('PO Number', `<span class="prp-md-code">${prp_dash(it.po_no || it.saved_po)}</span>`) +
            row('Date Acquired', prp_date(it.date_acquired)) +
            row('Unit Cost', num(it.cost)) +
            row('Acquisition Cost (a)', num(it.acq_cost)) +
            row('Additional Cost (b)', num(it.add_cost))) +
        card('Responsibility Center &amp; Location',
            row('Responsibility Center', prp_dash(it.rc_name) + (it.rc_center_code ? `<div class="prp-md-sub">${prp_esc(it.rc_center_code)}</div>` : '')) +
            row('Location/Department within Responsibility Area', prp_dash(it.district || it.location) + (it.department ? ' (' + prp_esc(it.department) + ')' : ''))) +
        card('Units (' + units.length + ')',
            `<div class="prp-md-tblwrap"><table class="prp-md-tbl"><thead><tr><th>PAR No. (Property No.)</th><th>Department</th><th>Serial No.</th><th>Model No.</th><th style="text-align:right;">Cost</th><th>Status</th></tr></thead><tbody>` +
            units.map(u => `<tr><td><span class="prp-md-code">${prp_dash(u.property_no)}</span></td><td>${prp_dash(u.department)}</td><td>${prp_dash(u.serial_no)}</td><td>${prp_dash(u.model_no)}</td><td class="amt">${prp_money(u.cost)}</td><td>${prp_badge(u.status, u.status_label)}</td></tr>`).join('') +
            `</tbody><tfoot><tr><td colspan="4">Total</td><td class="amt">${prp_money(it.acq_cost)}</td><td></td></tr></tfoot></table></div>`) +
        card('Depreciation',
            `<div class="prp-md-tblwrap"><table class="prp-md-tbl"><thead><tr>
                <th style="text-align:center;">Total book<br>balance<span class="fx">c = a + b</span></th>
                <th style="text-align:center;">Estimated Useful<br>life (in Years)</th>
                <th style="text-align:center;">Salvage/<br>Residual value</th>
                <th style="text-align:center;">Accumulated Dep.<br>January 1, ${new Date().getFullYear()}</th>
                <th style="text-align:center;">Depreciation for<br>the year<span class="fx">e</span></th>
                <th style="text-align:center;">Monthly<br>Depr'n.</th>
                <th style="text-align:center;">Accumulated<br>Depreciation<span class="fx">f = d + e</span></th>
                <th style="text-align:center;">Book Value<span class="fx">c - f</span></th>
            </tr></thead><tbody>` +
            units.map(u => {
                const c = Number(u.cost || 0);
                return `<tr><td class="amt">${prp_money(c)}</td><td style="text-align:center;">${prp_esc(it.life_years)} years</td><td class="amt">${prp_money(c * 0.1)}</td><td class="amt">${prp_money(u.accum_begin)}</td><td class="amt">${prp_money(Math.max(0, Number(u.accum_depr || 0) - Number(u.accum_begin || 0)))}</td><td class="amt">${prp_money(u.monthly_depr)}</td><td class="amt">${prp_money(u.accum_depr)}</td><td class="amt">${prp_money(u.book_value)}</td></tr>`;
            }).join('') +
            `</tbody><tfoot><tr><td class="amt"><span class="prp-md-muted" style="margin-right:8px;">Total</span>${prp_money(it.total_cost)}</td><td></td><td class="amt">${prp_money(it.salvage)}</td><td class="amt">${prp_money(it.accum_begin)}</td><td class="amt">${prp_money(it.depr_year)}</td><td class="amt">${prp_money(it.monthly_depr)}</td><td class="amt">${prp_money(it.accum_depr)}</td><td class="amt">${prp_money(it.book_value)}</td></tr></tfoot></table></div>` +
            (it.remarks ? `<div class="prp-md-sub" style="margin-top:8px;">Remarks: ${prp_esc(it.remarks)}</div>` : ''));
    $prp('prp-overlay').classList.add('open');
}

/* Actions dropdown of a row (fixed position, so the table's scrolling never clips it) */
function prp_toggleMenu(btn) {
    const drop = btn.closest('.prp-menu').querySelector('.prp-menu-drop');
    const isOpen = drop.classList.contains('open');
    prp_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function prp_closeMenus() {
    document.querySelectorAll('.prp-menu-drop.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.prp-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('.prp-menu')) prp_closeMenus(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') prp_closeMenus(); });
document.addEventListener('scroll', prp_closeMenus, true);

/* ══════════════ SEARCH PIS → SELECT → SAVE ══════════════ */
function prp_resetSearch() {
    $prp('prp-po').value = '';
    prp_found = null; prp_sel = new Set();
    prp_hideAddErr();
    $prp('prp-search-result').style.display = 'none';
    $prp('prp-search-empty').style.display = '';
    $prp('prp-po').focus();
}

async function prp_search(po) {
    po = String(po || '').trim();
    prp_hideAddErr();
    if (!po) { prp_showAddErr('Enter a PO number to search.'); return; }
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${PRP_API}/search?po_number=${encodeURIComponent(po)}`, { credentials: 'same-origin', headers: prp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to search the Property Inventory System.');
        prp_found = json.data;
        prp_sel = new Set();
        prp_renderSearch(po);
    } catch (e) {
        prp_showAddErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

function prp_renderSearch(typed) {
    const d = prp_found;
    if (!d.items.length) {
        $prp('prp-search-result').style.display = 'none';
        $prp('prp-search-empty').style.display = '';
        const hid = d.hidden_by_status || 0;
        $prp('prp-search-empty').innerHTML = `<div class="prp-empty"><strong>No items found</strong>
            PO <b>${prp_esc(typed)}</b> has no PAR items with status Issued, Returned or Donation in the Property Inventory System.
            ${hid ? `<br>(${hid} item${hid === 1 ? '' : 's'} of this PO ${hid === 1 ? 'has' : 'have'} another status and ${hid === 1 ? 'is' : 'are'} not offered.)` : ''}</div>`;
        return;
    }
    $prp('prp-search-empty').style.display = 'none';
    $prp('prp-search-result').style.display = '';

    // the PO strip: PO number, date and the ONE responsibility center (and how it was found)
    const rc = d.rc;
    $prp('prp-po-strip').innerHTML = `
        <div><div class="k">IAR Number</div><div class="v">${prp_esc(d.iar_number)}</div></div>
        <div><div class="k">PO Number</div><div class="v">${prp_esc(d.po_number)}</div></div>
        <div><div class="k">Responsibility Center</div><div class="v">${rc
            ? prp_esc(rc.rc_name) + ' <span class="prp-sub" style="font-weight:600;">(' + prp_esc(rc.rc_code) + ')</span>'
            : '<span class="rc-none">No responsibility center could be matched</span>'}</div></div>`;

    $prp('prp-s-found').textContent = d.items.filter(it => it.is_property).length;
    $prp('prp-s-cost').textContent  = prp_money(d.summary.total_cost);
    $prp('prp-s-saved').textContent = d.saved_count;

    const note = $prp('prp-hidden-note');
    const msgs = [];
    if (d.hidden_by_status > 0) {
        const n = d.hidden_by_status;
        msgs.push(n + ' other item' + (n === 1 ? '' : 's') + ' of this PO ha' + (n === 1 ? 's' : 've') + ' a dropped status (Disposed, Destroyed, Donation or Transfer) and ' + (n === 1 ? 'is' : 'are') + ' not offered.');
    }
    note.style.display = msgs.length ? '' : 'none';
    note.innerHTML = msgs.map(prp_esc).join('<br>');
    prp_renderSearchRows();
    prp_updateSel();
}

function prp_renderSearchRows() {
    $prp('prp-s-tbody').innerHTML = prp_found.items.map((it, i) => {
        const specs = prp_specs(it);
        const lock = it.saved || !it.is_property;
        return `<tr class="${lock ? 'prp-saved-row' : ''}" style="${lock ? '' : 'cursor:pointer;'}" ${lock ? '' : `onclick="prp_toggle(${i}, event)"`}>
            <td style="vertical-align:top;"><input type="checkbox" class="prp-chk" ${lock ? 'disabled' : ''} ${prp_sel.has(it.inv_id) ? 'checked' : ''} onclick="event.stopPropagation();prp_toggle(${i}, event, true)"></td>
            <td style="vertical-align:top;"><code class="nha-code">${prp_dash(it.property_no)}</code></td>
            <td style="vertical-align:top;"><div style="font-weight:600;">${prp_dash(it.description)}</div>${specs ? `<div class="prp-sub">${prp_esc(specs)}</div>` : ''}</td>
            <td style="vertical-align:top;"><div>${prp_dash(it.category)}</div><div class="prp-sub">${prp_esc(it.gl_code)}${it.sub_category ? ' · ' + prp_esc(it.sub_category) : ''}</div></td>
            <td style="vertical-align:top;">${prp_dash(it.unit)}</td>
            <td style="vertical-align:top;white-space:nowrap;">${prp_date(it.date_acquired)}</td>
            <td style="vertical-align:top;" class="prp-money"><strong>${prp_money(it.cost)}</strong></td>
            <td style="vertical-align:top;"><div style="font-weight:700;">${prp_dash(it.district || it.location)}</div><div class="prp-sub">${prp_esc(it.department)}</div></td>
            <td style="vertical-align:top;">${prp_badge(it.status, it.status_label)}</td>
            <td style="vertical-align:top;">${it.saved ? '<span class="nha-bdg nha-bdg-green">Saved</span>' : (!it.is_property ? '<span class="nha-bdg" style="color:#B45309;background:#FEF3C7;white-space:normal;" title="PIS TYPENO ' + prp_esc(it.type_no) + '">Not a property record</span>' : '—')}</td>
        </tr>`;
    }).join('');
}

function prp_toggle(i, ev, fromCheckbox) {
    const it = prp_found.items[i];
    if (!it || it.saved || !it.is_property) return;
    if (fromCheckbox) { ev.target.checked ? prp_sel.add(it.inv_id) : prp_sel.delete(it.inv_id); }
    else { prp_sel.has(it.inv_id) ? prp_sel.delete(it.inv_id) : prp_sel.add(it.inv_id); prp_renderSearchRows(); }
    prp_updateSel();
}

function prp_updateSel() {
    const items = prp_found ? prp_found.items : [];
    const picked = items.filter(it => prp_sel.has(it.inv_id));
    const open = items.filter(it => !it.saved && it.is_property);
    $prp('prp-s-sel').textContent     = picked.length;
    $prp('prp-s-selcost').textContent = prp_money(picked.reduce((t, it) => t + Number(it.cost || 0), 0));
    $prp('prp-sb-count').textContent  = picked.length;
    $prp('prp-save').disabled         = picked.length === 0;
    const all = $prp('prp-chk-all');
    all.checked = open.length > 0 && picked.length === open.length;
    all.disabled = open.length === 0;
}

async function prp_save() {
    if (!prp_found || !prp_sel.size) return;
    const po  = prp_found.po_number;
    const ids = [...prp_sel];
    const btn = $prp('prp-save');
    btn.disabled = true;
    window.PagePreloader?.show();
    try {
        const res  = await fetch(PRP_API + '/save', {
            method: 'POST', credentials: 'same-origin',
            headers: prp_authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ po_number: po, inv_ids: ids }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save the property records.');
        prp_toast(json.message || 'Saved.', 'success');
        // saved: close the window, clear the search (the next "Add from PIS" starts empty) and show the new rows in the list
        prp_closeAdd();
        prp_found = null; prp_sel = new Set();
        $prp('prp-po').value = '';
        $prp('prp-search-result').style.display = 'none';
        $prp('prp-search-empty').style.display = '';
        prp_loadSaved();
    } catch (e) {
        prp_showAddErr(e.message);
        prp_updateSel();
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── start ────────────────────────────────────────────────── */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', prp_start);
} else {
    prp_start();
}
function prp_start() {
    $prp('prp-yr').textContent = new Date().getFullYear();
    prp_bind();
    prp_loadSaved();
    prp_bindIar();
    prp_bindPf();
    prp_loadIar();
}
</script>
