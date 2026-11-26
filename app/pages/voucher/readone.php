<?php
namespace PHPMaker2024\FIMS;

// Disbursement Voucher VIEW modal — a partial included by app/pages/voucher.php (not a page of its
// own). Print opens the official DV (General Form No. 1, DvPrintService) in the shared print preview
// included at the end. Printing this DV's own Journal Entry Voucher(s) (JevPrintService) is done from
// the Journal Entry Voucher register instead (each row's own Print action there).
//
// API:  vr_open(id)
//       vr_close()
//
// Fonts and nha-fims.css are loaded by the including page.
if (!isset($_vr_api)) {
    $_vr_api = rtrim((string) Config('API.FIMS_URL'), '/');
    $_vr_api = preg_match('~/api$~', $_vr_api) ? $_vr_api : $_vr_api . '/api';
}
// Mirrors DisbursementVoucherService::authorizeTransition() for UX only (hiding a button here is
// cosmetic — the API is what actually enforces it); superadmin (uac.users.user_level_id = '-1')
// bypasses like everywhere else — GlobalService::getUserRcScope() would be the shared helper for
// this, but app/lib isn't autoloaded in page context (only app/api.php requires it), so this is
// inlined the same way $_vr_rcId already was. NOT IsSysAdmin(), which only tracks PHPMaker's own
// hardcoded admin-login path and is never true for a DB-authenticated user.
$_vr_currentUserId = (int) CurrentUserID();
$_vr_userRow        = $_vr_currentUserId > 0
    ? ExecuteRow("SELECT user_level_id, rc_id FROM uac.users WHERE user_id = {$_vr_currentUserId}", 'DB')
    : null;
$_vr_isSysAdmin    = !$_vr_userRow || SameText((string) $_vr_userRow['user_level_id'], '-1');
$_vr_rcId          = $_vr_isSysAdmin ? null : ($_vr_userRow['rc_id'] ?? null);
?>

<style>
#vr-root .vr-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
#vr-root .vr-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#vr-root dl.vr-dl { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 20px; margin: 0; }
#vr-root dl.vr-dl.vr-dl3 { grid-template-columns: repeat(3, 1fr); }
#vr-root dl.vr-dl dt { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0; }
#vr-root dl.vr-dl dd { font-size: 14px; font-weight: 600; color: var(--afims-text, #1e293b); margin: 2px 0 0; }
#vr-root .vr-particulars { font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
#vr-root .vr-a-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px 20px; }
#vr-root .vr-a-f dt { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0; }
#vr-root .vr-a-f dd { font-size: 14px; font-weight: 600; color: var(--afims-text, #1e293b); margin: 2px 0 0; word-break: break-word; }
#vr-root .vr-a-full { grid-column: 1 / -1; }
@media (max-width: 768px) { #vr-root .vr-a-grid { grid-template-columns: 1fr; } }
#vr-root .vr-ledger { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vr-ledger td { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vr-ledger td.vr-l-label { color: var(--afims-text-muted, #62657E); font-weight: 600; width: 55%; }
#vr-root .vr-ledger td.vr-l-val { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; }
#vr-root .vr-ledger tr.vr-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; }
#vr-root .vr-net { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px; margin-bottom: 14px;
    border: 2px solid var(--afims-primary-soft, #E6EBF6); border-radius: 12px;
    background: linear-gradient(to right, var(--afims-primary-soft, #E6EBF6), var(--afims-surface, #fff)); }
#vr-root .vr-net small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
#vr-root .vr-net strong { font-family: "IBM Plex Mono", monospace; font-size: 20px; color: var(--afims-primary-text, #0035A9); }
#vr-root .vr-rejection { padding: 10px 14px; background: var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 8px; font-size: 13px; color: var(--afims-danger-text, #8F222D); margin-bottom: 14px; }
#vr-root .vje-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); padding: 4px 6px; border-radius: 5px; font-size: 16px; line-height: 1; }
#vr-root .vje-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
/* Accounting's editable Deduction / ATC rows — same blocks and "+ TYPE" pills as the entry form (form.php). */
#vr-root .vje-row { position: relative; margin-bottom: 10px; }
#vr-root .vje-row .vje-remove { position: absolute; top: 0; right: 0; }
#vr-root .vje-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 6px; }
#vr-root .vje-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 6px; }
/* Deduction / ATC rows laid out like the entry form's (form.php .vf-ded-row / .vf-atc-row): name on the left, Rate and Amount
   columns on the right, one hairline between rows. The VAT block keeps its own layout. */
#vr-root .vje-ded-row:not([data-vat="1"]), #vr-root .vr-ro-ded, #vr-root .vje-atc-row, #vr-root .vr-ro-atc {
    display: grid; grid-template-columns: minmax(0, 1fr) 180px 170px 44px; align-items: center; margin: 0; padding: 10px 14px; border-top: 1px solid #E4ECF6; }
#vr-root .vje-ded-row:not([data-vat="1"]) > .vr-ded-label, #vr-root .vr-ro-ded > .vr-ded-label { grid-column: 1; grid-row: 1; margin: 0; font-weight: 400; font-size: 14px; color: var(--afims-text, #1C1E29); }
#vr-root .vje-ded-row:not([data-vat="1"]) > .vje-grid2, #vr-root .vr-ro-ded > .vje-grid2, #vr-root .vje-atc-row > .vje-grid3, #vr-root .vr-ro-atc > .vje-grid3 { display: contents; }
#vr-root .vje-ded-row:not([data-vat="1"]) > .vje-grid2 > .vje-fg:nth-child(1), #vr-root .vr-ro-ded > .vje-grid2 > .vje-fg:nth-child(1) { grid-column: 2; grid-row: 1; padding-right: 14px; margin: 0; }
#vr-root .vje-ded-row:not([data-vat="1"]) > .vje-grid2 > .vje-fg:nth-child(2), #vr-root .vr-ro-ded > .vje-grid2 > .vje-fg:nth-child(2) { grid-column: 3; grid-row: 1; margin: 0; }
#vr-root .vje-ded-row:not([data-vat="1"]) > .vje-grid2 label, #vr-root .vr-ro-ded > .vje-grid2 label,
#vr-root .vje-atc-row > .vje-atc-codefg label, #vr-root .vr-ro-atc > .vje-fg label, #vr-root .vje-atc-row .vr-ded-label, #vr-root .vr-ro-atc .vr-ded-label { display: none; }
#vr-root .vje-ded-row:not([data-vat="1"]) input, #vr-root .vr-ro-ded input, #vr-root .vje-atc-row input, #vr-root .vr-ro-atc input { text-align: right; }
#vr-root .vje-atc-row > .vje-atc-codefg, #vr-root .vr-ro-atc > .vje-fg { grid-column: 1; grid-row: 1; margin: 0; padding-right: 14px; }
#vr-root .vje-atc-row > .vje-atc-codefg input, #vr-root .vr-ro-atc > .vje-fg input { text-align: left; }
#vr-root .vje-atc-row > .vje-grid3 > .vje-fg:nth-child(1), #vr-root .vr-ro-atc > .vje-grid3 > .vje-fg:nth-child(1) { grid-column: 2; grid-row: 1; padding-right: 14px; margin: 0; }
#vr-root .vje-atc-row > .vje-grid3 > .vje-fg:nth-child(3), #vr-root .vr-ro-atc > .vje-grid3 > .vje-fg:nth-child(3) { grid-column: 3; grid-row: 1; margin: 0; }
#vr-root .vje-atc-row > .vje-grid3 > .vje-fg:nth-child(2), #vr-root .vr-ro-atc > .vje-grid3 > .vje-fg:nth-child(2) { grid-column: 2 / 4; grid-row: 2; margin-top: 8px; }
#vr-root .vje-ded-row:not([data-vat="1"]) > .vje-fg, #vr-root .vr-ro-ded > .vje-fg { grid-column: 1 / -1; grid-row: 2; }
#vr-root .vje-ded-row:not([data-vat="1"]) > .vje-remove, #vr-root .vje-atc-row > .vje-remove { position: static; grid-column: 4; grid-row: 1; justify-self: center; }
/* editable VAT row (Accounting stage): same one-line layout as the entry form — Less: VAT withheld (Inclusive | Exclusive) | rate | amount | x */
#vr-root .vje-ded-row[data-vat="1"] { display: grid; grid-template-columns: minmax(0, 1fr) 180px 170px 44px; align-items: center; margin: 0; padding: 10px 14px; border-top: 1px solid #E4ECF6; }
#vr-root .vje-ded-row[data-vat="1"] > .vr-ded-label { grid-column: 1; grid-row: 1; margin: 0; font-weight: 400; font-size: 14px; color: var(--afims-text, #1C1E29); }
#vr-root .vje-ded-row[data-vat="1"] .vje-vat-types { display: inline-flex; margin: 0 0 0 10px; gap: 6px; vertical-align: middle; }
#vr-root .vje-ded-row[data-vat="1"] .vje-vat-opt { padding: 4px 10px; font-size: 12px; }
#vr-root .vje-ded-row[data-vat="1"] > .vr-vat-rate { grid-column: 2; grid-row: 1; padding-right: 14px; margin: 0; }
#vr-root .vje-ded-row[data-vat="1"] > .vr-vat-wamt { grid-column: 3; grid-row: 1; margin: 0; }
#vr-root .vje-ded-row[data-vat="1"] .vr-vat-rate input, #vr-root .vje-ded-row[data-vat="1"] .vr-vat-wamt input { text-align: right; }
#vr-root .vje-ded-row[data-vat="1"] > .vje-remove { position: static; grid-column: 4; grid-row: 1; justify-self: center; }
#vr-root .vje-ded-row[data-vat="1"] > .vr-vat-info { grid-column: 1 / -1; grid-row: 2; margin-top: 6px; font-size: 12px; color: var(--afims-text-muted, #62657E); }
#vr-root .vr-vat-info input { width: 110px; padding: 2px 6px; border: 0; border-bottom: 1px dashed var(--afims-border, #B9C4D0); background: transparent !important; font-size: 12px; text-align: right; color: inherit; }
#vr-root .vje-ded-row[data-vat="1"] > .vje-vat-hint { grid-column: 1 / -1; grid-row: 3; margin-top: 2px; font-size: 11.5px; color: var(--afims-text-muted, #62657E); }
#vr-root .vr-ded-section > :first-child, #vr-root #vje-ded-list > :first-child { border-top: 0; }
#vr-root .vje-fg label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin-bottom: 3px; }
#vr-root .vje-fg input, #vr-root .vje-fg select { width: 100%; font-size: 13px; padding: 8px 12px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; box-sizing: border-box; font-family: inherit; background: var(--afims-surface, #fff); color: var(--afims-text, #1e293b); }
#vr-root .vje-fg input[readonly] { background: var(--afims-surface-subtle, #F4F7FA); cursor: default; pointer-events: none; user-select: none; }
#vr-root .vje-fg input[readonly]:focus { outline: none; box-shadow: none; border-color: var(--afims-border, #E4ECF2); }
/* Amount column: the box text sits 12px inside its box, so the header, Gross and Net values are inset the same 12px to line up with it */
#vr-root .vr-bd-table th:nth-child(4), #vr-root .vr-bd-gross td:nth-child(4) { padding-right: 26px; }
#vr-root .vr-net-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 70px 12px 14px; background: #E3EBF8; border-top: 1px solid #D5E0F0; font-size: 14px; color: #0B1B43; }
#vr-root .vr-net-row b { font-family: "IBM Plex Mono", monospace; font-size: 15px; }
#vr-root .vr-acct-hint { font-family: "IBM Plex Mono", monospace; font-size: 12px; font-weight: 400; color: var(--afims-text-muted, #62657E); white-space: nowrap; }
#vr-root .vr-ded-label .vr-acct-hint { margin-left: 6px; }
#vr-root .vje-atc-codefg, #vr-root .vr-ro-atc > .vje-fg:first-child { display: flex; align-items: center; gap: 10px; }
#vr-root .vje-atc-codefg > input, #vr-root .vr-ro-atc > .vje-fg:first-child > input { flex: 0 1 430px; min-width: 0; }
/* the Tax Base line only matters when the base is split over several ATC rows */
#vr-root .vr-ded-section:not(.vr-atc-multi) .vr-ro-atc > .vje-grid3 > .vje-fg:nth-child(2),
#vr-root #vje-ded-list:not(.vr-atc-multi) .vje-atc-row > .vje-grid3 > .vje-fg:nth-child(2) { display: none; }
#vr-root .vr-ded-types { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-top: 1px solid #E4ECF6; }
#vr-root .vr-ded-less { font-size: 13px; color: var(--afims-text, #1C1E29); }
#vr-root .vr-ded-add-select {
    width: auto; min-width: 190px; padding: 7px 12px; border: 1px dashed var(--afims-border, #B9C4D0); border-radius: 8px;
    background: transparent; color: var(--afims-text-muted, #62657E); font-size: 13px; font-family: inherit; cursor: pointer;
}
#vr-root .vr-ded-add-select:hover, #vr-root .vr-ded-add-select:focus { border-color: var(--afims-heading-fill, #000E3E); outline: none; }
#vr-root .vr-ded-type-btn { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface, #FFFFFF);
    color: var(--afims-heading-fill, #000E3E); font-size: 12px; font-weight: 600; letter-spacing: .02em; cursor: pointer; transition: background .15s, border-color .15s; }
#vr-root .vr-ded-type-btn:hover { background: var(--afims-surface-subtle, #F4F7FA); border-color: var(--afims-heading-fill, #000E3E); }
#vr-root .vr-ledger input, #vr-root .vr-ledger select { width: 100%; font-size: 12.5px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; font-family: inherit; }
#vr-root .vje-section-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
#vr-root .vje-badge { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 3px 9px; border-radius: 999px;
    background: var(--afims-success-soft, #DBF2E5); color: var(--afims-success-hover, #0C7539); }
#vr-root .vje-amt-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text, #1e293b); }
#vr-root .vje-auto-tag { display: inline-block; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
    padding: 1px 6px; border-radius: 999px; background: var(--afims-primary-soft, #E6EBF6); color: var(--afims-primary-text, #0035A9); margin-left: 4px; }
/* Section B mirrors the entry form: RFA Breakdown, then plain deduction / ATC rows (no borders), all in one box. */
/* Section B as one table-like box (same as the entry form): RFA lines, Gross, "Less:" rows. */
#vr-root .vr-b-card { padding: 0; overflow: hidden; border-color: #D5E0F0; }
#vr-root .vr-b-card .vr-ded-section { margin: 0 !important; padding: 0 !important; border-top: 1px solid #E4ECF6 !important; }
#vr-root .vr-b-card > .vr-ded-section:first-child { border-top: 0 !important; }
#vr-root .vr-bd-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 14px; }
#vr-root .vr-bd-table th:nth-child(2), #vr-root .vr-bd-table td:nth-child(2) { width: 170px; }
#vr-root .vr-bd-table th:nth-child(3), #vr-root .vr-bd-table td:nth-child(3) { width: 180px; }
#vr-root .vr-bd-table th:nth-child(4), #vr-root .vr-bd-table td:nth-child(4) { width: 170px; }
#vr-root .vr-bd-table th:nth-child(5), #vr-root .vr-bd-table td:nth-child(5) { width: 44px; }
#vr-root .vr-bd-table th { padding: 12px 14px; background: #EEF3FB; color: #0B1B43; font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
#vr-root .vr-bd-table td { padding: 10px 14px; border-top: 1px solid #E4ECF6; vertical-align: middle; }
#vr-root .vr-bd-table tbody td:nth-child(2) { color: var(--afims-text-muted, #62657E); }
#vr-root .vr-bd-table td:nth-child(3), #vr-root .vr-bd-table td:nth-child(4) { text-align: right; }
#vr-root .vr-bd-table .vr-bd-amt { padding: 8px 12px; border: 1px solid var(--afims-border, #D7DCE6); border-radius: 8px; display: block; text-align: right; background: var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vr-bd-gross td { background: #E3EBF8; border-top: 1px solid #D5E0F0; padding: 8px 14px; }
#vr-root .vr-bd-gross .vr-bd-muted { color: var(--afims-text-muted, #62657E); font-size: 13px; }
#vr-root .vr-less { font-weight: 400; margin-right: 4px; }
/* read-only VAT row: Less: VAT withheld (Inclusive | Exclusive) | rate | amount, VAT computation on a muted line beneath */
#vr-root .vr-ro-vat { display: grid; grid-template-columns: minmax(0, 1fr) 180px 170px 44px; align-items: center; padding: 10px 14px; border-top: 1px solid #E4ECF6; }
#vr-root .vr-ro-vat > .vr-ded-label { grid-column: 1; grid-row: 1; margin: 0; font-weight: 400; font-size: 14px; }
#vr-root .vr-ro-vat .vr-vat-tag { font-size: 12px; margin-left: 10px; padding: 3px 10px; border-radius: 999px; background: #E3EBF8; color: #0B1B43; font-weight: 600; }
#vr-root .vr-ro-vat > .vje-fg:nth-of-type(2) { grid-column: 2; grid-row: 1; padding-right: 14px; margin: 0; }
#vr-root .vr-ro-vat > .vje-fg:nth-of-type(3) { grid-column: 3; grid-row: 1; margin: 0; }
#vr-root .vr-ro-vat .vje-fg label { display: none; }
#vr-root .vr-ro-vat input { text-align: right; }
#vr-root .vr-ro-vat > .vr-vat-info { grid-column: 1 / -1; grid-row: 2; margin-top: 6px; font-size: 12px; color: var(--afims-text-muted, #62657E); }
/* C. Accounting Entry (auto-generated) */
#vr-root .vr-ap-card { padding: 0; overflow: hidden; }
#vr-root .vr-ap-table { width: 100%; border-collapse: collapse; font-size: 14px; }
#vr-root .vr-ap-table th { padding: 12px 16px; text-align: left; font-size: 12.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; color: var(--afims-text-muted, #475069); background: #F7F9FC; border-bottom: 1px solid #E4ECF6; }
#vr-root .vr-ap-table td { padding: 10px 16px; border-bottom: 1px solid #EEF2F8; }
#vr-root .vr-ap-table td:nth-child(1) { font-family: "IBM Plex Mono", monospace; font-size: 13px; letter-spacing: .02em; }
#vr-root .vr-ap-table td:nth-child(3), #vr-root .vr-ap-table td:nth-child(4) { text-align: right; }
#vr-root .vr-ap-sub:not(:empty) { margin-top: 2px; font-size: 12px; color: var(--afims-text-muted, #62657E); }
#vr-root .vr-ap-table tr.vr-ap-total td { font-weight: 700; border-bottom: 0; }
#vr-root .vr-ap-table tr.vr-ap-bad td:nth-child(3), #vr-root .vr-ap-table tr.vr-ap-bad td:nth-child(4) { color: var(--afims-danger-text, #8F222D); }
#vr-root .vr-ded-section { margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vr-card > .vr-ded-section:first-child { margin-top: 0; padding-top: 0; border-top: none; }
#vr-root .vr-ded-section + .vr-ded-section { margin-top: 10px; padding-top: 0; border-top: none; } /* Deductions and ATC read as one list */
#vr-root .vr-ded-row { margin-bottom: 10px; }
#vr-root .vr-ded-row:last-child { margin-bottom: 0; }
#vr-root .vr-ded-label { font-size: 13px; font-weight: 600; color: var(--afims-heading-fill, #000E3E); margin-bottom: 4px; }
/* VAT deduction — Inclusive/Exclusive, VAT Computation (informational) and Withholding VAT (deducted). Same layout as the entry
   form's (form.php .vf-vat-*): editable here while Accounting holds the DV (vje_addVatLine()), plain read-only afterwards (vr_vatBlockHtml()). */
#vr-root .vje-vat-types { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; }
#vr-root .vje-vat-opt { display: flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-muted, #62657E); }
#vr-root label.vje-vat-opt { cursor: pointer; }
#vr-root .vje-vat-opt.active { border-color: var(--afims-primary, #0035A9); color: var(--afims-primary-text, #0035A9); }
#vr-root .vje-vat-opt small { font-size: 10px; font-weight: 400; }
#vr-root .vje-vat-block { margin-top: 8px; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-vat-block-title { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 2px; }
#vr-root .vje-vat-block-title span { font-weight: 400; text-transform: none; letter-spacing: 0; }
#vr-root .vje-vat-block.info .vje-vat-block-title { color: var(--afims-primary-text, #0035A9); }
#vr-root .vje-vat-block.real .vje-vat-block-title { color: var(--afims-danger, #DC3545); }
#vr-root .vje-vat-hint { display: block; margin-top: 6px; font-size: 11px; color: var(--afims-text-muted, #62657E); }
/* One line under the ATC rows: how much of the base their Tax Bases add up to (red when they exceed it). */
#vr-root .vje-atc-summary { margin: 4px 0 10px; font-size: 11.5px; line-height: 1.5; color: var(--afims-text-muted, #62657E); }
#vr-root .vje-atc-summary.over { padding: 6px 10px; border-radius: 6px; color: var(--afims-danger-text, #8F222D); background: var(--afims-danger-soft, #FAE1E3); }
#vr-root .vr-rfa-list { display: flex; flex-direction: column; gap: 10px; }
#vr-root .vr-rfa-item { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; padding: 10px 12px; }
#vr-root .vr-att-list { display: flex; flex-direction: column; gap: 6px; }
#vr-root .vr-att-item { display: block; font-size: 13px; color: var(--afims-primary-text, #0035A9); text-decoration: none; }
#vr-root .vr-att-item:hover { text-decoration: underline; }

#vr-root .vje-grp-table { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vje-grp-table th { padding: 5px 4px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--afims-text-muted, #62657E); border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vje-grp-table td { padding: 5px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-grp-hdr td { font-weight: 700; padding-top: 10px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vje-grp-hdr:first-child td { padding-top: 5px; }
#vr-root .vje-grp-sub td:first-child { padding-left: 20px; color: var(--afims-text, #1e293b); font-weight: 400; }
#vr-root .vje-grp-amt { text-align: right; font-family: "IBM Plex Mono", monospace; white-space: nowrap; width: 120px; }
#vr-root .vje-grp-code { text-align: center; font-family: "IBM Plex Mono", monospace; color: var(--afims-text-muted, #62657E); width: 60px; }

/* Flat Accounting Entries table */
#vr-root .vje-edit-table { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vje-edit-table td, #vr-root .vje-edit-table th { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-edit-table th { text-align: left; font-weight: 600; background: var(--afims-surface-subtle, #F4F7FA); }
/* The picked account's title, inside the Account Code field right after the code ("1-04-01-010 - Due to BIR"). The input keeps only the
   code; this is a click-through label whose left edge vje_renderAcctTitle() sets to the end of the typed text. */
#vr-root .vje-acct-wrap { position: relative; }
/* the vendor's SL, read-only under the Accounts Payable GL (see vje_renderSlLine()) */
#vr-root .vje-sl-line { margin: 6px 0 0 2px; font-size: 12.5px; color: var(--afims-text, #1C1E29); font-family: "IBM Plex Mono", monospace; }
#vr-root .vje-sl-line > span { font-family: inherit; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); margin-right: 6px; }
#vr-root .vje-sl-line em { font-style: normal; font-family: var(--font, inherit); color: var(--afims-text-muted, #62657E); }

#vr-root .vje-acct-title { position: absolute; top: 0; bottom: 0; right: 8px; display: none; align-items: center; pointer-events: none; font-size: 12.5px; font-family: inherit; color: var(--afims-text-muted, #62657E); }
#vr-root .vje-acct-title > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#vr-root .vje-edit-table input { width: 100%; font-size: 12.5px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; font-family: inherit; }
#vr-root .vje-edit-table tr.vr-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; text-align: right; }

/* "Document" layout — lettered sections (A/B/C/D) styled like a printed form, same convention as
   app/pages/voucher/form.php's own vf-doc-* rules (this view is the read-only counterpart of that
   form). Purely a presentation layer over the existing #vr-root markup — no id or JS binding
   renamed. */
#vr-root .vr-doc-section { margin-bottom: 22px; }
#vr-root .vr-doc-section-hdr {
    font-size: 14px; font-weight: 700; color: #fff; background: #0B1B43;
    margin: 0 0 12px; padding: 10px 14px; border-radius: 8px;
    text-transform: uppercase; letter-spacing: .02em;
}
#vr-root .vr-doc-table { width: 100%; border-collapse: collapse; border: 1px solid var(--afims-border, #D7DCE6); margin-bottom: 14px; }
#vr-root .vr-doc-table td { border: 1px solid var(--afims-border, #D7DCE6); padding: 10px 14px; vertical-align: top; width: 50%; }
#vr-root .vr-doc-table td.vr-doc-full { width: 100%; }
#vr-root .vr-doc-table .vr-doc-k { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); margin: 0 0 6px; }
#vr-root .vr-doc-summary-table { width: 100%; border-collapse: collapse; border: 1px solid var(--afims-border, #D7DCE6); margin-top: 14px; }
#vr-root .vr-doc-summary-table td { border: 1px solid var(--afims-border, #D7DCE6); padding: 10px 14px; font-size: 13px; }
#vr-root .vr-doc-summary-table td:first-child { font-weight: 600; color: var(--afims-text, #1C1E29); }
#vr-root .vr-doc-summary-table td:last-child { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; width: 220px; }
#vr-root .vr-doc-summary-table tr.vr-doc-summary-final td { font-weight: 700; background: #E8EEF9; color: #0B1B43; text-transform: uppercase; font-size: 12.5px; }
#vr-root .vr-doc-summary-table tr.vr-doc-summary-final td:last-child { text-transform: none; font-size: 14px; }

@media print {
    body * { visibility: hidden; }
    #vr-overlay, #vr-overlay * { visibility: visible; }
    #vr-overlay { position: absolute; top: 0; left: 0; width: 100%; }
    #vr-root .nha-modal-hdr, #vr-root .nha-modal-foot, #vr-root .no-print { display: none !important; }
}
</style>

<div id="vr-root" class="nha-root">
    <div id="vr-overlay" class="nha-overlay no-print">
        <div class="nha-modal u-modal-xl" role="dialog" aria-modal="true" aria-labelledby="vr-modal-title">

            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="vr-modal-title">Disbursement Voucher</h3>
                <button type="button" class="nha-modal-close" onclick="vr_close()" aria-label="Close">&times;</button>
            </div>

            <div class="nha-modal-body" id="vr-body">
                <div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>
            </div>

            <div class="nha-modal-foot no-print">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="vr_close()">Close</button>
                <button type="button" class="nha-btn nha-btn-outline" onclick="vr_print()">Print</button>
                <button type="button" class="nha-btn nha-btn-outline" id="vr-bir2307-btn" onclick="vr_printBir2307()" style="display:none;" title="BIR Form 2307 — Certificate of Creditable Tax Withheld at Source">BIR 2307</button>
                <span id="vr-actions"></span>
            </div>

        </div>
    </div>
</div><!-- /#vr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   DISBURSEMENT VOUCHER VIEW / PRINT MODAL   prefix: vr_
═══════════════════════════════════════════════════════════════ */

const VR_API = <?= json_encode($_vr_api) ?>;
const VR_IS_SYSADMIN     = <?= json_encode((bool) $_vr_isSysAdmin) ?>;
const VR_CURRENT_USER_ID = <?= json_encode($_vr_currentUserId) ?>;
const VR_RC_ID           = <?= json_encode($_vr_rcId) ?>;
// Mirrors DisbursementVoucherService::STAGE_HOLDER_RC — UX only, server is authoritative.
const VR_STAGE_HOLDER_RC = { FOR_FMD_REVIEW: '013', FOR_ACCOUNTING: '012', FOR_DIVISION_MANAGER: '012', FOR_DEPARTMENT_MANAGER: '012', FOR_TREASURY: '014' };
const $vr = id => document.getElementById(id);
let vr_id  = 0;
let vr_dv  = null;

function vr_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function vr_open(id) {
    vr_id = Number(id) || 0;
    if (!vr_id) return;
    $vr('vr-overlay').classList.add('open');
    $vr('vr-body').innerHTML = '<div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>';
    $vr('vr-actions').innerHTML = '';

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/read/${vr_id}`);
        if (!res.ok) throw new Error(`HTTP ${res.status}: ${res.statusText}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load disbursement voucher.');
        vr_dv = json.data;
        // vje_groupedEntriesCard() needs GL account titles even outside the Accounting stage (a
        // RELEASED voucher's printout should still show its entries) — vje_init() only loads this
        // while vje_visible(dv), so fetch it here too whenever there's a JEV to show at all.
        if (!VJE_COA.length) {
            try {
                const coaJson = await vr_authFetch(`${VR_API}/chart_of_accounts/all?postable_only=1`).then(r => r.json());
                VJE_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
            } catch (e) { /* grouped card falls back to showing the raw account_code */ }
        }
        vr_render(vr_dv);
    } catch (e) {
        $vr('vr-body').innerHTML = `<div class="nha-error" style="display:flex;"><span>${vr_esc(e.message)}</span></div>`;
    } finally {
        window.PagePreloader?.hide();
    }
}

function vr_close() {
    $vr('vr-overlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $vr('vr-overlay')?.classList.contains('open')) vr_close();
});

// Print → the official DV form (PDF, pdftk fill), via the shared print preview (included below) —
// the payload is built by its prp_dvPayload() helper. Printing this DV's own Journal Entry Voucher(s)
// is done from the Journal Entry Voucher register instead (each row's own Print action there).
function vr_print() {
    if (vr_dv) prp_open(prp_dvPayload(vr_dv), { type: 'DV' });
}

// BIR 2307 → the payee's Certificate of Creditable Tax Withheld at Source for this DV (Bir2307PrintService builds it from
// the DV id: its ATC lines, the vendor, NHA's payor details). Offered only when the DV withholds an EWT / percentage tax
// (an ATC line that isn't withholding VAT — WV… is not on a 2307) and isn't cancelled — see vr_render().
function vr_hasBir2307(dv) {
    return dv?.status !== 'CANCELLED' && (dv?.atc_lines ?? []).some(a =>
        Number(a.withholding_tax) > 0 && !String(a.atc_code || '').toUpperCase().startsWith('WV'));
}
function vr_printBir2307() {
    if (vr_dv) prp_open({ dv_id: vr_dv.dv_id }, { type: 'BIR2307' });
}

function vr_render(dv) {
    $vr('vr-modal-title').textContent = `Disbursement Voucher — ${dv.dv_no}`;
    $vr('vr-bir2307-btn').style.display = vr_hasBir2307(dv) ? '' : 'none';

    const rejection = dv.status === 'REJECTED' && dv.rejection_reason
        ? `<div class="vr-rejection"><strong>Returned:</strong> ${vr_esc(dv.rejection_reason)}</div>` : '';

    // Deductions / ATC are shown read-only (vr_dedAtcRowsHtml(), same look as the entry form) for every stage OTHER
    // than FOR_ACCOUNTING — while FOR_ACCOUNTING, vje_dedAtcEditor() renders the SAME data as editable
    // grids instead (Accounting can add/change/remove rows through the same pills as the entry form, not just Originating at creation).
    const totalDeductions = (dv.deductions ?? []).reduce((s, d) => s + (Number(d.deduction_amount) || 0), 0);
    const attachmentsHtml = vr_attachmentsSection(dv);
    const breakdownHtml = vr_rfaBreakdownHtml(dv);
    // Deductions + ATC live in the same box as the RFA Breakdown (like the entry form): editable grids for Accounting,
    // plain read-only rows otherwise. Each one is its own .vr-ded-section block.
    // A PAYROLL DV (made from an imported payroll month, dv.payroll): its deductions are the import's — entered once in
    // Payroll Management, re-read from it whenever the DV is saved — so at every stage they show read-only, each one's
    // name and amount without the (always empty) Rate (vr_dedAtcRowsHtml()). Accounting's editor is still RENDERED,
    // hidden, for it: vje_init() loads the deduction rows into it before the Accounting Entries lines (without
    // #vje-ded-list it throws and the entries grid stays empty) and vje_save() sends them back.
    const dedAtcHtml = vje_visible(dv)
        ? (dv.payroll ? vr_dedAtcRowsHtml(dv) + `<div style="display:none;">${vje_dedAtcEditor(dv)}</div>` : vje_dedAtcEditor(dv))
        : vr_dedAtcRowsHtml(dv);

    // Section D is Accounting's. While Originating/FMD hold the voucher it isn't shown at all (any entries
    // a payroll/remittance DV was pre-filled with stay stored, just not displayed until Accounting has it).
    const dHiddenStage = ['DRAFT', 'FOR_FMD_REVIEW', 'REJECTED'].includes(dv.status);
    // The PAYMENT JEV cards ("Payment Entry — JEV-… (TREASURY — CHECK NO. …)") and the "Prepare Payment JEV" prompt are not shown
    // here — a released voucher's payment entry lives in the Journal Entry Voucher register.
    const dEntriesHtml = dHiddenStage ? '' : (vje_visible(dv) ? vje_entriesCard(dv) : vje_groupedEntriesCard(dv));
    // One entry section, not two: once Accounting has entries (editable grid, or the saved/grouped table) THAT is section C;
    // before that — and for a regular DV with nothing saved — section C is the auto-generated preview built from the DV.
    const cSection = dEntriesHtml ? `
        <div class="vr-doc-section">
            <p class="vr-doc-section-hdr">C. Accounting Entry</p>
            ${dEntriesHtml}
        </div>` : vr_acctEntryHtml(dv);



    $vr('vr-body').innerHTML = `
        ${rejection}
        <div class="vr-net">
            <div><small>Net Amount</small><strong id="vr-top-net">${vr_peso(dv.net_amount)}</strong></div>
            <div style="text-align:right;"><small>Status</small><span class="nha-bdg ${vr_statusClass(dv.status)}">${vr_esc(dv.status === 'REJECTED' ? 'RETURNED' : dv.status)}</span></div>
        </div>

        <!-- ══ A. VOUCHER INFORMATION — same grouping as app/pages/voucher/form.php's own
             section A: Claimant/RFA, DV No./Date, Particulars. ══ -->
        <div class="vr-doc-section">
            <p class="vr-doc-section-hdr">A. Voucher Information</p>
            <div class="vr-card">
                <div class="vr-a-grid">
                    <div class="vr-a-f"><dt>DV Date</dt><dd>${vr_fmtDate(dv.dv_date)}</dd></div>
                    <div class="vr-a-f"><dt>Payee Name</dt><dd>${vr_esc(dv.claimant_name) || '—'}</dd></div>
                    <div class="vr-a-f"><dt>Address</dt><dd>${vr_esc(dv.claimant_address) || '—'}</dd></div>
                    <div class="vr-a-f"><dt>TIN / Employee No.</dt><dd>${vr_esc(dv.employee_no_tin) || '—'}</dd></div>
                    <div class="vr-a-f"><dt>Vendor (Supplier / Contractor) or Payroll</dt><dd>${vr_esc(dv.vendor_name || (dv.rfas ?? []).find(r => r.vendor_name)?.vendor_name) || '—'}</dd></div>
                    <div class="vr-a-f"><dt>PO / Contract</dt><dd>${vr_esc(dv.po_number) || '—'}${Number(dv.po_amount) > 0 ? ` <small>(${vr_peso(dv.po_amount)})</small>` : ''}</dd></div>
                    <div class="vr-a-f vr-a-full"><dt>Status</dt><dd><span class="nha-bdg ${vr_statusClass(dv.status)}">${vr_esc(dv.status === 'REJECTED' ? 'RETURNED' : dv.status)}</span></dd></div>
                    <div class="vr-a-f vr-a-full"><dt>Particulars</dt><dd class="vr-particulars" style="font-weight:400;">${vr_esc(dv.particulars) || '—'}</dd></div>
                </div>
            </div>
            ${vr_remitScheduleHtml(dv)}
        </div>

        <!-- ══ B. AMOUNT AND DEDUCTIONS — laid out like the entry form (form.php): the RFA Breakdown with the
             deduction / ATC rows under it in one box, then the Gross / Less / Net summary. ══ -->
        <div class="vr-doc-section">
            <p class="vr-doc-section-hdr">B. Amount and Deductions</p>
            ${breakdownHtml || dedAtcHtml ? `<div class="vr-card vr-b-card">
                ${breakdownHtml}
                ${dedAtcHtml}
                <div class="vr-net-row"><span>Net amount</span><b id="vr-sum-net-vis">${vr_peso(dv.net_amount)}</b></div>
            </div>` : ''}
            <table class="vr-doc-summary-table" style="display:none;">
                <tr><td>Gross Amount</td><td>${vr_peso(dv.gross_amount)}</td></tr>
                <tr><td>Less: Total Deductions</td><td id="vr-sum-ded">${vr_peso(totalDeductions)}</td></tr>
                <tr><td>Less: Withholding Tax (ATC)</td><td id="vr-sum-wh">${vr_peso((dv.atc_lines ?? []).reduce((s, a) => s + (Number(a.withholding_tax) || 0), 0))}</td></tr>
                <tr class="vr-doc-summary-final"><td>Net Amount Payable</td><td id="vr-sum-net">${vr_peso(dv.net_amount)}</td></tr>
            </table>
            ${dv.remarks ? `<p class="vr-particulars" style="margin:14px 0 0;"><strong>Remarks:</strong> ${vr_esc(dv.remarks)}</p>` : ''}
        </div>

        <!-- ══ C. ACCOUNTING ENTRY (AUTO-GENERATED) — same read-only entry the entry form previews, rebuilt here from the saved DV. ══ -->
        ${cSection}

        <!-- ══ D. SUPPORTING DOCUMENTS ══ -->
        <div class="vr-doc-section">
            <p class="vr-doc-section-hdr">D. Supporting Documents</p>
            ${attachmentsHtml || '<p class="vr-hint" style="margin:0;">None — no supporting documents attached.</p>'}
        </div>


        <div class="vr-card" style="margin-bottom:0;">
            <p class="vr-card-title">Workflow History</p>
            <dl class="vr-dl vr-dl3">
                <div><dt>Originating (Submitted)</dt><dd>${vr_fmtDateTime(dv.submitted_at)}</dd></div>
                <div><dt>FMD (Obligation Checked)</dt><dd>${vr_fmtDateTime(dv.fmd_approved_at)}</dd></div>
                <div><dt>Accounting — Section Chief (Processed)</dt><dd>${vr_fmtDateTime(dv.accounting_processed_at)}</dd></div>
                <div><dt>Accounting — Division Manager (Reviewed)</dt><dd>${vr_fmtDateTime(dv.division_reviewed_at)}</dd></div>
                <div><dt>Accounting — Department Manager (Approved)</dt><dd>${vr_fmtDateTime(dv.department_approved_at)}</dd></div>
                <div><dt>Treasury (Released)</dt><dd>${vr_fmtDateTime(dv.treasury_released_at)}</dd></div>
            </dl>
        </div>`;

    vr_renderActions(dv);
    if (vje_visible(dv)) vje_init(dv);
}

/* One or more RFAs this DV was drawn against (see app/pages/voucher/form.php's multi-select) —
   dv.rfas is the full-detail array from DisbursementVoucherService::fetchById(), built from
   fims.budget_allocation.dv_id (reverse lookup — see DisbursementVoucherService::linkRfas()). */
function vr_rfaSection(dv) {
    const rfas = dv.rfas ?? [];
    if (!rfas.length) return `<p class="vr-particulars">—</p>`;
    return `<div class="vr-rfa-list">${rfas.map(r => `
        <div class="vr-rfa-item">
            <strong class="nha-mono">${vr_esc(r.allocation_number)}</strong>
            <dl class="vr-dl vr-dl3" style="margin-top:6px;">
                <div><dt>Department</dt><dd>${vr_esc(r.department_name) || 'Unassigned'}</dd></div>
                <div><dt>Fiscal Year</dt><dd>${vr_esc(r.fiscal_year)}</dd></div>
                <div><dt>Vendor</dt><dd>${vr_esc(r.vendor_name) || '—'}</dd></div>
                <div><dt>Remaining</dt><dd>${vr_peso(r.remaining_balance)} of ${vr_peso(r.budget_amount)}</dd></div>
            </dl>
        </div>`).join('')}</div>`;
}

/* Supporting Documents attached via the Attachments card on the form — dv.file_attachments comes
   from FileUploadServices::attachmentsFor(). Nothing to show when the voucher has none. */
function vr_attachmentsSection(dv) {
    const files = dv.file_attachments ?? [];
    if (!files.length) return '';
    return `<div class="vr-card">
        <p class="vr-card-title">Attachments</p>
        <div class="vr-att-list">${files.map(f => {
            const url = f.file_meta?.view_url || f.file_meta?.link || '';
            const name = vr_esc(f.file_meta?.document_name || 'Supporting Document');
            return url ? `<a class="vr-att-item" href="${url}" target="_blank" rel="noopener noreferrer">${name}</a>` : `<span class="vr-att-item">${name}</span>`;
        }).join('')}</div>
    </div>`;
}

// Mirrors DisbursementVoucherService::authorizeTransition() — hiding a button is cosmetic only,
// the API re-checks and is the real gate.
function vr_mayTransition(dv, target) {
    // Whoever already approved/forwarded an earlier step of this voucher can't reject it (same rule as the server).
    if (target === 'REJECTED' && VR_CURRENT_USER_ID > 0
        && [dv.fmd_approved_by, dv.accounting_processed_by, dv.division_reviewed_by].some(u => Number(u) === VR_CURRENT_USER_ID)) return false;
    if (VR_IS_SYSADMIN) return true;
    const holderRc = VR_STAGE_HOLDER_RC[dv.status] || null; // RC currently holding the voucher (null = DRAFT)
    if (target === 'CANCELLED') {
        const isCreator = VR_CURRENT_USER_ID > 0 && Number(dv.created_by) === VR_CURRENT_USER_ID;
        const isHolder = holderRc !== null && VR_RC_ID === holderRc;
        return isCreator || isHolder;
    }
    if (holderRc === null) return true; // DRAFT -> FOR_FMD_REVIEW: Originating submits, open to anyone.
    if (VR_RC_ID !== holderRc) return false;
    // Separation of duties inside Accounting — whoever already acted on an earlier Accounting step of this
    // voucher can't take the next one (mirrors DisbursementVoucherService::authorizeTransition()).
    const me = VR_CURRENT_USER_ID;
    if (me > 0) {
        if (target === 'FOR_DEPARTMENT_MANAGER' && Number(dv.accounting_processed_by) === me) return false;
        if (target === 'FOR_TREASURY' && (Number(dv.accounting_processed_by) === me || Number(dv.division_reviewed_by) === me)) return false;
    }
    return true;
}

function vr_renderActions(dv) {
    const allowed = (dv.allowed_transitions || []).filter(t => vr_mayTransition(dv, t));
    const btn = (label, status, cls) => `<button type="button" class="nha-btn ${cls}" style="margin-left:8px;" onclick="vr_changeStatus('${status}')">${label}</button>`;
    let html = '';
    if (allowed.includes('FOR_FMD_REVIEW')) html += btn('Submit to FMD', 'FOR_FMD_REVIEW', 'nha-btn-primary');
    if (allowed.includes('FOR_ACCOUNTING')) html += btn('FMD Approve', 'FOR_ACCOUNTING', 'nha-btn-primary');
    if (allowed.includes('FOR_DIVISION_MANAGER'))   html += btn('Forward to Division Manager', 'FOR_DIVISION_MANAGER', 'nha-btn-primary');
    if (allowed.includes('FOR_DEPARTMENT_MANAGER')) html += btn('Forward to Department Manager', 'FOR_DEPARTMENT_MANAGER', 'nha-btn-primary');
    if (allowed.includes('FOR_TREASURY'))   html += btn('Approve &amp; Forward to Treasury', 'FOR_TREASURY', 'nha-btn-primary');
    if (allowed.includes('RELEASED'))       html += btn('Release Payment', 'RELEASED', 'nha-btn-primary');
    if (allowed.includes('REJECTED'))       html += btn('Return', 'REJECTED', 'nha-btn-outline'); // status code stays REJECTED (API / DB); only the label says "Return"
    if (allowed.includes('CANCELLED'))      html += btn('Cancel', 'CANCELLED', 'nha-btn-ghost');
    // Separation of duties hides the next Accounting step from whoever already took an earlier one — say so, otherwise the approve button just isn't there.
    const me = VR_CURRENT_USER_ID;
    let why = '';
    if (!VR_IS_SYSADMIN && me > 0 && VR_RC_ID === VR_STAGE_HOLDER_RC[dv.status]) {
        if (dv.status === 'FOR_DIVISION_MANAGER' && Number(dv.accounting_processed_by) === me) why = 'You forwarded this voucher to the Division Manager, so you can\'t also approve it. Another Accounting user must do the Division Manager step.';
        else if (dv.status === 'FOR_DEPARTMENT_MANAGER' && [dv.accounting_processed_by, dv.division_reviewed_by].some(u => Number(u) === me)) why = 'You already acted on an earlier Accounting step of this voucher, so the Department Manager approval must be done by a different user.';
    }
    if (why) html = `<span style="font-size:12px;color:var(--afims-warning-text,#8A6400);max-width:420px;display:inline-block;text-align:left;vertical-align:middle;">${vr_esc(why)}</span>` + html;
    $vr('vr-actions').innerHTML = html;
}

/* ══════════════════════════════════════════════════════════════
   JOURNAL ENTRY (Accounting stage)   prefix: vje_
   Three sub-sections, all saved together via one POST .../journal-entry:
     - Deduction — 0+ rows, fims.disbursement_voucher_deductions (editable here; was a static
       read-only table set only by Originating until Accounting could edit it too)
     - Withholding Tax Classification (ATC) — 0+ rows, fims.disbursement_voucher_atc_lines
     - Accounting Entries (DV) — the actual debit/credit JEV, with optional SL Code/SL Detail
═══════════════════════════════════════════════════════════════ */

let VJE_COA = []; // posting_allowed chart-of-accounts rows, loaded once per modal open
let VJE_ATC = []; // active ATC codes, loaded once per modal open
let VJE_DED = []; // active deduction types, loaded once per modal open
let VJE_RC  = []; // responsibility centers, for ATC "Chargeability", loaded once per modal open
let VJE_SL  = []; // SL masters, for the Accounting Entries grid's SL Code picker, loaded once per modal open
let VJE_BOOKS = []; // fims.books (Main Office/CISFA/NGC/MPLC-PPMO/PPMO), for the Accounting Entries Book picker, loaded once per modal open
let VJE_RFA = []; // RFAs, for the Accounting Entries RFA list, loaded once per modal open
let vje_seq    = 0;
let vje_atcSeq = 0;
let vje_dedSeq = 0;
let vje_dv     = null; // the open DV — vje_liveGrossLessDeductions() reads its gross_amount
let vje_atcFull = null; // the "full base" the ATC rows' bases were last split against (see vje_syncAtcBases()); null = not measured yet

/* ── VAT — same model as form.php's vf_addVatLine() and the Accounting DV Processing page's VAT tab ──
     1. Statutory VAT, fixed 12% — INFORMATIONAL, never subtracted from the Net Amount:
          Inclusive: Tax Base = Gross / 1.12, VAT = Gross - Tax Base
          Exclusive: Tax Base = Gross,        VAT = Gross x 12%
     2. Withholding VAT, a rate typed by Accounting — the real deduction: Tax Base x rate.
   The saved deduction line carries the Withholding VAT; its gross_amount is the Tax Base, and inclusive vs. exclusive is
   inferred from it (a base below the DV's gross means inclusive). */
const VJE_VAT_RATE = 12;
function vje_vatSplit(gross, vatType) {
    const r2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
    const vat  = r2(vatType === 'exclusive' ? gross * VJE_VAT_RATE / 100 : gross - gross / (1 + VJE_VAT_RATE / 100));
    const base = r2(vatType === 'exclusive' ? gross : gross - vat);
    return { vat, base };
}
function vje_vatTypeOf(gross, base) { return Number(base) > 0 && Number(base) < gross - 0.005 ? 'inclusive' : 'exclusive'; }
/* A legacy VAT line saved as a flat peso amount with no rate can't be shown as Withholding VAT %, so it keeps the plain Rate/Amount row. */
function vje_isFlatVat(d) { return (d.rate === null || d.rate === undefined || d.rate === '') && Number(d.deduction_amount) > 0; }
const vje_money2 = n => Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

/* Comma-formatted amount inputs (Deduction Amount, ATC Tax Base, Debit, Credit). They are type="text" — a type="number" input
   rejects "300,000.00" — so vje_num() strips the commas before any calculation reads the value, and vje_money2() writes it back
   with them. Typing re-inserts the commas live, keeping the caret where it was. Same convention as voucher/form.php's
   vf_numVal / vf_fmtMoneyVal / vf_liveFormatMoney / vf_bindMoneyInput (repeated here: this partial doesn't depend on that one). */
const vje_num = v => parseFloat(String(v ?? '').replace(/,/g, '')) || 0;
function vje_liveFormatMoney(el) {
    const cursor = el.selectionStart ?? el.value.length;
    let before = 0;
    for (let i = 0; i < cursor; i++) { if (el.value[i] !== ',') before++; }
    let clean = el.value.replace(/[^0-9.]/g, '');
    const dot = clean.indexOf('.');
    if (dot !== -1) clean = clean.slice(0, dot + 1) + clean.slice(dot + 1).replace(/\./g, '');
    let [intPart, decPart] = clean.split('.');
    intPart = (intPart || '').replace(/^0+(?=\d)/, '');
    el.value = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + (decPart !== undefined ? '.' + decPart : '');
    let pos = 0, seen = 0;
    while (pos < el.value.length && seen < before) { if (el.value[pos] !== ',') seen++; pos++; }
    el.setSelectionRange(pos, pos);
}
function vje_bindMoney(el) {
    if (!el || el.dataset.vjeMoneyBound) return;
    el.dataset.vjeMoneyBound = '1';
    el.addEventListener('input', () => vje_liveFormatMoney(el));
    el.addEventListener('blur', () => { el.value = el.value.trim() === '' ? '' : vje_money2(vje_num(el.value)); });
}

// Visible only while the voucher is at FOR_ACCOUNTING and the viewer is Accounting Dept (RC 012)
// or sysadmin — same rule the server enforces in DisbursementVoucherService::saveJournalEntry().
function vje_visible(dv) {
    return dv.status === 'FOR_ACCOUNTING' && (VR_IS_SYSADMIN || VR_RC_ID === '012');
}

function vje_section(dv) {
    return vje_dedAtcEditor(dv) + vje_entriesCard(dv);
}

/**
 * Read-only "Accounting Entries" presentation shown whenever a JEV exists but Accounting isn't
 * actively editing it (any stage past FOR_ACCOUNTING — see vje_visible()'s doc comment) — the
 * editable grid (vje_entriesCard) disappears once the DV moves on, so without this the printed
 * voucher would show no journal entry at all past that point. Grouped by GL account (UACS code):
 * the group's own row carries the account's Debit/Credit TOTAL (summed from its own lines), and
 * each line under it is its SL Code breakdown. The sub-line label is the line's own description
 * (set when Accounting prepared the JEV — see vje_syncAutoLines()) falling back to its SL Detail /
 * SL Code name, and the small number is the line's position in the JEV (jevl.line_number), not an
 * SL code.
 */
/** Account code rendered as separate boxed segments (e.g. "2 01 01 010 02") — same visual
    convention as the Chart of Accounts page's own code chip (chart-of-accounts-page.php's
    coa_codeChip()), reused here so a line's account reads the same way in both places. */
function vr_codeChip(code) {
    const parts = String(code ?? '').split('-').filter(p => p !== '');
    if (!parts.length) return '';
    const segs = parts.map((p, i) => {
        const bg     = i === 0 ? '#EEF1F7' : '#EAF1FE';
        const fg     = i === 0 ? '#1A1B21' : '#2F5FD6';
        const border = i > 0 ? 'border-left:1px solid rgba(15,32,69,.08);' : '';
        return `<span style="background:${bg};color:${fg};padding:2px 6px;font-size:11px;font-weight:700;font-family:'IBM Plex Mono',monospace;${border}">${vr_esc(p)}</span>`;
    }).join('');
    return `<span style="display:inline-flex;border:1px solid var(--afims-border, #E4ECF2);border-radius:5px;overflow:hidden;vertical-align:middle;">${segs}</span>`;
}

/** A group's sub-line labels, stacked inside the header row's own Description cell — General
    Form No. 1 convention: the claimant/vendor name prints in black (the filled-in variable), each
    withholding tax rate/type breakdown prints in plain black, and any line actually posted to a
    chart_of_accounts child row (parent_account_id set — a vendor/SL sub-account; SL is no longer a
    separate sl_masters table, see "03 - alter_table/37") shows that child account's own code
    labeled "SL Code:" — the row's Account Code cell already shows its PARENT control account. */
function vje_groupSubLabels(group, dv) {
    return group.items.map(it => {
        const l      = it.line;
        const debit  = Number(l.debit)  || 0;
        const credit = Number(l.credit) || 0;
        let label;
        if (it.isChild) {
            label = `<span style="font-size:12px;color:var(--afims-text-muted, #62657E);"><span style="margin-right:4px;">SL Code:</span>${vr_codeChip(l.account_code)}<span style="margin-left:6px;">${vr_esc(l.account_title || '—')}</span></span>`;
        } else if (dv?.account_code && group.account_code === dv.account_code && debit > 0 && (dv.claimant_name || dv.vendor_name)) {
            label = `<span style="font-size:13px;color:var(--afims-text, #1e293b);">${vr_esc(dv.claimant_name || dv.vendor_name)}</span>`;
        } else {
            const atc = (dv?.atc_lines ?? []).find(a => a.account_code === group.account_code && Math.abs((Number(a.withholding_tax) || 0) - credit) < 0.01);
            label = atc
                ? `<span style="font-size:13px;color:var(--afims-text, #1e293b);"><span style="display:inline-block;width:40px;">${vr_esc(atc.rate)}%</span>${vr_esc(atc.atc_description || atc.atc_code)}</span>`
                : (l.description ? `<span style="font-size:13px;color:var(--afims-text-muted, #62657E);">${vr_esc(l.description)}</span>` : '');
        }
        return label ? `<div style="text-align:left;">${label}</div>` : '';
    }).join('');
}
/** GL-grouped, read-only rendering of a posted JEV's lines — used by the SETUP JEV's read-only
    presentation (vje_groupedEntriesCard()). (It also drew each PAYMENT JEV's card; those are no longer shown in this modal.)
    A line posted directly to a chart_of_accounts child row (an SL/vendor sub-account) groups under
    its PARENT control account instead of its own code, same as the paper form shows one control
    account with its SL breakdown underneath — see vje_groupSubLabels() for that breakdown, stacked
    inside the same row's Description cell (one row per account, no separate line-number column). */
function vje_groupedTable(lines, dv = null) {
    const totalDebit  = lines.reduce((s, l) => s + (Number(l.debit)  || 0), 0);
    const totalCredit = lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);

    const resolved = lines.map(l => {
        const acct   = (VJE_COA || []).find(a => a.account_code === l.account_code);
        const parent = acct?.parent_account_id ? (VJE_COA || []).find(a => a.account_id === acct.parent_account_id) : null;
        return {
            line: l,
            isChild: !!parent,
            headerCode:  parent ? parent.account_code : l.account_code,
            headerTitle: parent ? parent.account_title : l.account_title,
        };
    });

    const groups = [];
    const byCode = new Map();
    resolved.forEach(it => {
        if (!byCode.has(it.headerCode)) {
            const g = { account_code: it.headerCode, account_title: it.headerTitle, items: [], debit: 0, credit: 0 };
            byCode.set(it.headerCode, g);
            groups.push(g);
        }
        const g = byCode.get(it.headerCode);
        g.items.push(it);
        g.debit  += Number(it.line.debit)  || 0;
        g.credit += Number(it.line.credit) || 0;
    });

    // Same format as the auto-generated entry (section C): Account Code | Account Description | Debit | Credit, debit rows first,
    // credit-only rows indented, a bold Total row. Sub-ledger detail stays under the title, muted.
    const isCr = g => !(Number(g.debit) > 0) && Number(g.credit) > 0;
    const ordered = groups.filter(g => !isCr(g)).concat(groups.filter(isCr));
    const num = n => Number(n) > 0 ? vr_fmtNum(n) : '';
    const rows = ordered.map(g => `<tr>
                <td>${vr_esc(g.account_code)}</td>
                <td${isCr(g) ? ' class="vr-ap-cr"' : ''}>${vr_esc(g.account_title || '—')}<div class="vr-ap-sub">${vje_groupSubLabels(g, dv)}</div></td>
                <td>${num(g.debit)}</td>
                <td>${num(g.credit)}</td>
            </tr>`).join('');
    const bad = Math.abs(totalDebit - totalCredit) > 0.005;

    return `<table class="vr-ap-table">
            <thead><tr>
                <th style="width:150px;">Account Code</th>
                <th>Account Description</th>
                <th style="text-align:right;width:190px;">Debit</th>
                <th style="text-align:right;width:190px;">Credit</th>
            </tr></thead>
            <tbody>${rows}
                <tr class="vr-ap-total${bad ? ' vr-ap-bad' : ''}"><td></td><td>Total</td><td>${vr_fmtNum(totalDebit)}</td><td>${vr_fmtNum(totalCredit)}</td></tr>
            </tbody>
        </table>`;
}

function vje_groupedEntriesCard(dv) {
    const lines = dv.jev?.lines ?? [];
    if (!lines.length) return '';

    return `${dv.jev?.book_name ? `<p class="vr-hint" style="margin:0 0 8px;">Book: ${vr_esc(dv.jev.book_name)}</p>` : ''}
    <div class="vr-card vr-ap-card">${vje_groupedTable(lines, dv)}</div>`;
}

/** Editable at the Accounting stage — was a static read-only table set only by Originating at
    creation; Accounting can now add/change/remove rows here too, same as ATC below. Feeds
    vje_syncAutoLines()'s Cr lines the same way a loaded one from creation always did. */
function vje_dedAtcEditor(dv) {
    // Same as the entry form: deduction and ATC rows share one list (added in the order clicked, each new one below the
    // previous), with one "+ TYPE" pill per deduction type and a "+ ATC ROW" pill under it. The pills are filled in by
    // vje_renderDedTypeButtons() once the deduction types have loaded (vje_init()).
    return `
        <div class="vr-ded-section">
            <div id="vje-ded-list"></div>
            <div class="vje-atc-summary" id="vje-atc-summary" style="display:none;"></div>
            <div class="vr-ded-types" id="vje-ded-types"></div>
        </div>`;
}

function vje_entriesCard(dv) {
    return `
        <div class="vr-card">
            <div class="vje-section-hdr">
                <p class="vr-card-title" style="margin:0;">Accounting Entries <span class="vr-hint" style="display:inline;font-weight:400;text-transform:none;">(optional)</span></p>
            </div>
            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:10px;">
                <div class="nha-fg" style="max-width:260px;">
                    <label>Book <span class="nha-req">*</span></label>
                    <select id="vje-book"><option value="">Select a book…</option></select>
                    <span class="vje-err" id="vje-err-book" style="display:none;color:var(--afims-danger,#C0392B);font-size:12px;">Book is required.</span>
                </div>
            </div>
            <p class="vr-hint" style="margin:0 0 8px;">Add one line per journal entry.</p>
            <div style="overflow-x:auto;">
                <table class="vr-ledger vje-edit-table" style="min-width:900px;">
                    <thead><tr>
                        <th style="text-align:left;min-width:220px;">Account Code</th>
                        <th style="text-align:left;min-width:200px;">Particular</th>
                        <th style="text-align:right;width:110px;">Debit (₱)</th>
                        <th style="text-align:right;width:110px;">Credit (₱)</th>
                        <th style="width:30px;"></th>
                    </tr></thead>
                    <tbody id="vje-lines-tbody"></tbody>
                    <tfoot><tr class="vr-l-total">
                        <td colspan="2" style="text-align:right;">Total</td>
                        <td class="vr-l-val" id="vje-total-debit">₱ 0.00</td>
                        <td class="vr-l-val" id="vje-total-credit">₱ 0.00</td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
            <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vje_addNewLine()">+ Add Line</button>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                <span id="vje-balance-msg" style="font-weight:600;font-size:13px;"></span>
            </div>
        </div>`;
}

// path must NOT start with "/" — see app/pages/voucher/form.php's vf_getJson() doc comment for why
// (the sub-folder deployment shim double-prefixes a bare-quoted "/word" literal).
async function vje_getJson(path) {
    const res  = await vr_authFetch(VR_API + '/' + path);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

async function vje_init(dv) {
    vje_dv = dv;
    vje_atcFull = null; // a different DV: measure the base afresh (vje_syncAtcBases())
    try {
        const [coaJson, atcJson, dedJson, rcJson, slJson, booksJson, rfaJson] = await Promise.all([
            // postable_only (not the region-scoped /chart-of-accounts/all) — posting a voucher
            // isn't restricted to the preparer's own region, so every active+postable GL account
            // should be selectable here regardless of which region it belongs to.
            vje_getJson('chart_of_accounts/all?postable_only=1'),
            vje_getJson('voucher/atc-codes'),
            vje_getJson('voucher/deduction-types'),
            vje_getJson('responsibility-center/all'),
            vje_getJson('voucher/sl-codes'),
            vje_getJson('books/all'),
            vje_getJson('voucher/rfa/all'),
        ]);
        VJE_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
        VJE_ATC = atcJson.data ?? [];
        VJE_DED = dedJson.data ?? [];
        VJE_RC  = rcJson.data ?? [];
        VJE_SL  = slJson.data ?? [];
        VJE_BOOKS = booksJson.data ?? [];
        VJE_RFA = rfaJson.data ?? [];
    } catch (e) {
        VJE_COA = []; VJE_ATC = []; VJE_DED = []; VJE_RC = []; VJE_SL = []; VJE_BOOKS = []; VJE_RFA = [];
    }

    const vjeBookSel = $vr('vje-book');
    if (vjeBookSel) {
        vjeBookSel.innerHTML = '<option value="">Select a book…</option>' +
            VJE_BOOKS.map(b => `<option value="${b.book_id}">${vr_esc(b.name)}</option>`).join('');
        vjeBookSel.value = dv.jev?.book_id || '';
    }
    vje_renderDedTypeButtons();
    vje_dedSeq = 0;
    vje_dedRows().forEach(r => r.remove());
    (dv.deductions ?? []).forEach(d => vje_addDeductionLine(d));
    vje_recalcDeductions();

    vje_atcSeq = 0;
    vje_atcRows().forEach(r => r.remove());
    (dv.atc_lines ?? []).forEach(a => vje_addAtcLine(a));
    vje_recalcAtc();

    vje_seq = 0;
    $vr('vje-lines-tbody').innerHTML = '';
    if (dv.jev && (dv.jev.lines ?? []).length) {
        // Reload whatever was saved before (one flat row each, same order), then TAG each loaded
        // line with the same data-auto-source vje_upsertAutoLine() would have given it, by matching
        // it against the Dr account/the just-loaded Deduction rows/the just-loaded ATC rows.
        // Accounting Entries now ALWAYS auto-syncs (see vje_syncAutoLines()'s doc comment) — without
        // this, that sync would duplicate a line that's already there instead of recognizing and
        // updating it. A loaded line matching none of those (a manual split Accounting entered by
        // hand, etc.) is left untagged, so sync never touches it.
        const usedDed = new Set(), usedAtc = new Set();
        let drUsed = false, apUsed = false;
        dv.jev.lines.forEach(l => {
            const lineId = vje_addLine(l);
            const tr = $vr(`vje-row-${lineId}`);
            const debit  = Number(l.debit)  || 0;
            const credit = Number(l.credit) || 0;

            if (!drUsed && debit > 0 && l.account_code === (dv.account_code || '')) {
                tr.dataset.autoSource = 'dr';
                drUsed = true;
                return;
            }
            const dedMatch = [...vje_dedRows()].find(dtr => {
                const idx = dtr.id.replace('vje-ded-row-', '');
                if (usedDed.has(idx)) return false;
                const type = VJE_DED.find(t => String(t.deduction_type_id) === dtr.querySelector('.vje-ded-type').value);
                const acctCode = type?.account_code || dtr.querySelector('.vje-ded-acct')?.value.trim() || '';
                const amount = vje_num(dtr.querySelector('.vje-ded-amount').value);
                return acctCode === l.account_code && Math.abs(amount - credit) < 0.01;
            });
            if (dedMatch) {
                const idx = dedMatch.id.replace('vje-ded-row-', '');
                usedDed.add(idx);
                tr.dataset.autoSource = `ded:${idx}`;
                return;
            }
            const atcMatch = [...vje_atcRows()].find(atr => {
                const idx = atr.id.replace('vje-atc-row-', '');
                if (usedAtc.has(idx)) return false;
                const def  = VJE_ATC.find(c => c.atc_code === atr.querySelector('.vje-atc-code').value);
                const rate = parseFloat(atr.querySelector('.vje-atc-rate').value) || 0;
                const base = vje_num(atr.querySelector('.vje-atc-base').value);
                return def?.account_code === l.account_code && Math.abs(vje_pctOf(base, rate) - credit) < 0.01;
            });
            if (atcMatch) {
                const idx = atcMatch.id.replace('vje-atc-row-', '');
                usedAtc.add(idx);
                tr.dataset.autoSource = `atc:${idx}`;
                return;
            }
            if (!apUsed && credit > 0 && (l.account_code === (dv.vendor_ap_account_code || '2-01-01-010') || String(l.account_code || '').startsWith('2-01-01-010'))) {
                tr.dataset.autoSource = 'ap';
                apUsed = true;
            }
        });
        vje_recalc();
    }
    vje_syncAutoLines();

    vje_updateAcctgEntryPanels(dv);
}

/** Gross minus whatever is currently in the Deduction grid — the live "net before withholding tax"
    figure. Used wherever the old code read the DV's static dv.net_amount (which was fixed at
    whatever deductions existed at save time); now that Deductions are editable here, that snapshot
    would go stale the moment a row changes. */
function vje_liveGrossLessDeductions() {
    return Math.max(0, (Number(vje_dv?.gross_amount) || 0) - vje_totalDeductions());
}

/* ── Deduction + ATC rows (editable — Accounting can add/change/remove them here too, not just Originating at creation).
   Same blocks as the entry form: one list (#vje-ded-list), each new row added below the previous one. ───────────── */
/** "2-04-01-040 Guaranty/Security Deposits Payable" — an account code with its Chart of Accounts title. */
function vr_acctLabel(code) {
    if (!code) return '';
    const t = (VJE_COA.find(a => a.account_code === code) || {}).account_title || '';
    return [code, t].filter(Boolean).join(' ');
}
function vje_atcAcctHint(tr) {
    const el = tr.querySelector('.vr-acct-hint');
    if (!el) return;
    el.textContent = vr_acctLabel((VJE_ATC.find(c => c.atc_code === tr.querySelector('.vje-atc-code').value) || {}).account_code);
}
function vje_markAtcMulti() { $vr('vje-ded-list').classList.toggle('vr-atc-multi', vje_atcRows().length > 1); }
function vje_dedRows() { return $vr('vje-ded-list').querySelectorAll('.vje-ded-row'); }
function vje_atcRows() { return $vr('vje-ded-list').querySelectorAll('.vje-atc-row'); }

/* One "+ TYPE" pill per deduction type (payroll-only types only on a payroll DV) plus "+ ATC ROW" — same as the form's. */
function vje_renderDedTypeButtons() {
    const box = $vr('vje-ded-types');
    if (!box) return;
    const types = VJE_DED.filter(t => vje_dv?.payroll || !String(t.code || '').startsWith('PAYROLL_'));
    // One "Less: + Add deduction…" dropdown (same as the entry form) instead of a pill per type: picking an option adds that row, then it resets.
    box.innerHTML = `<span class="vr-ded-less">Less:</span>
        <select class="vr-ded-add-select" id="vje-ded-add" aria-label="Add deduction">
            <option value="">+ Add deduction…</option>
            ${types.map(t => `<option value="t:${vr_esc(t.deduction_type_id)}">${vr_esc(t.name)}</option>`).join('')}
            <option value="atc">ATC row (withholding tax)</option>
        </select>`;
    $vr('vje-ded-add').addEventListener('change', e => {
        const v = e.target.value;
        e.target.value = '';
        if (v === 'atc') vje_addAtcLine();
        else if (v.startsWith('t:')) vje_addDeductionLine({ deduction_type_id: v.slice(2) });
    });
}

/* Retention, Advance Recoupment and Penalty Tax are one figure per voucher (VAT too) — same rule as the entry form's
   VF_SINGLE_DED and the server's SINGLE_DEDUCTION_TYPES. "Other" can repeat. A line already SAVED twice (older data) still loads. */
const VJE_SINGLE_DED = ['RETENTION', 'RECOUPMENT', 'PENALTY_TAX'];

function vje_addDeductionLine(d = null) {
    const idx = ++vje_dedSeq;
    const typeId = d?.deduction_type_id != null ? String(d.deduction_type_id) : '';
    const type = VJE_DED.find(t => String(t.deduction_type_id) === typeId);
    if (type && VJE_SINGLE_DED.includes(type.code) && !(d?.gross_amount != null)) {
        if ([...vje_dedRows()].some(r => r.querySelector('.vje-ded-type').value === typeId)) {
            alert(`"${type.name}" is already added — edit that line instead (it can only be added once).`);
            return;
        }
    }
    // VAT gets its own row (Inclusive/Exclusive, VAT Computation, Withholding VAT) — a singleton, like the entry form's.
    // A pill click passes only {deduction_type_id}; a saved line also carries gross_amount (its Tax Base).
    if (type?.code === 'VAT') {
        const saved = d?.gross_amount != null;
        if (!(saved && vje_isFlatVat(d))) {
            if (!vje_vatRow()) return vje_addVatLine(idx, d, saved);
            if (!saved) { alert('A VAT line is already added — edit it instead.'); return; }
        }
    }
    // The posting-account picker shows only when the deduction type has no default posting account
    // (deduction_types.account_code IS NULL — e.g. "OTHER"); the type's own default always wins — see fetchDeductions()'s COALESCE.
    const needsAcct = !!type && !type.account_code;
    const div = document.createElement('div');
    div.className = 'vje-row vje-ded-row';
    div.id = `vje-ded-row-${idx}`;
    div.innerHTML = `
        <button type="button" class="vje-remove" title="Remove" onclick="vje_removeDeductionLine(${idx})">&times;</button>
        <div class="vr-ded-label"><span class="vr-less">Less:</span> <b>${vr_esc(type?.name || d?.name) || '—'}</b>${type?.account_code ? `<span class="vr-acct-hint">${vr_esc(vr_acctLabel(type.account_code))}</span>` : ''}</div>
        <input type="hidden" class="vje-ded-type" value="${vr_esc(typeId)}">
        <div class="vje-grid2">
            <div class="vje-fg"><label>Rate (%)</label><input type="number" class="vje-ded-rate" min="0" step="0.01" placeholder="e.g. 10"></div>
            <div class="vje-fg"><label>Deduction Amount (₱)</label><input type="text" inputmode="decimal" class="vje-ded-amount" placeholder="Auto-computed"></div>
        </div>
        ${needsAcct ? `<div class="vje-fg" style="margin-top:6px;"><label>Posting Account *</label><input type="text" class="vje-ded-acct" placeholder="Search GL account code (UACS)…"></div>` : ''}`;
    // A saved VAT line is Withholding VAT: its rate applies to its own Tax Base (gross_amount), not the DV's gross.
    if (type?.code === 'VAT' && d?.gross_amount != null) div.dataset.base = Number(d.gross_amount) || 0;
    div.querySelector('.vje-ded-rate').value = d?.rate ?? '';
    div.querySelector('.vje-ded-amount').value = d?.deduction_amount != null ? vje_money2(d.deduction_amount) : '';
    vje_bindMoney(div.querySelector('.vje-ded-amount')); // before the listener below, so it reads the comma-formatted value
    div.querySelector('.vje-ded-rate').addEventListener('input', () => vje_recalcDeductionLine(idx));
    div.querySelector('.vje-ded-amount').addEventListener('input', () => vje_recalcDeductionLine(idx));
    $vr('vje-ded-list').appendChild(div);
    const acct = div.querySelector('.vje-ded-acct');
    if (acct) {
        acct.value = d?.account_code ?? '';
        nhaSearchableSelect(acct, VJE_COA.map(a => ({ value: a.account_code, label: a.account_title })));
        acct.addEventListener('input', () => vje_recalcDeductionLine(idx));
    }
    vje_recalcDeductionLine(idx);
}

function vje_removeDeductionLine(idx) {
    $vr(`vje-ded-row-${idx}`)?.remove();
    vje_recalcDeductions();
}

function vje_vatRow() { return $vr('vje-ded-list').querySelector('.vje-ded-row[data-vat="1"]'); }

/* The VAT row: the toggle, a read-only VAT Computation block and the Withholding VAT block. It reuses .vje-ded-rate /
   .vje-ded-amount for the Withholding VAT, so vje_collectDeductions() / vje_totalDeductions() need no special case — except
   gross_amount, which is the Tax Base (row.dataset.base, kept by vje_syncVat()). */
function vje_addVatLine(idx, d, saved) {
    const typeId = String(d.deduction_type_id);
    const gross  = Number(vje_dv?.gross_amount) || 0;
    const vatType = saved ? vje_vatTypeOf(gross, Number(d.gross_amount)) : 'inclusive';
    const div = document.createElement('div');
    div.className = 'vje-row vje-ded-row';
    div.id = `vje-ded-row-${idx}`;
    div.dataset.vat = '1';
    div.dataset.vatType = vatType;
    div.innerHTML = `
        <button type="button" class="vje-remove" title="Remove" onclick="vje_removeDeductionLine(${idx})">&times;</button>
        <div class="vr-ded-label"><span class="vr-less">Less:</span> <b>VAT withheld</b>
            <span class="vje-vat-types">
                <label class="vje-vat-opt" title="Gross already includes VAT"><input type="radio" name="vje-vat-type-${idx}" value="inclusive"> Inclusive</label>
                <label class="vje-vat-opt" title="VAT is added on top of gross"><input type="radio" name="vje-vat-type-${idx}" value="exclusive"> Exclusive</label>
            </span>
        </div>
        <input type="hidden" class="vje-ded-type" value="${vr_esc(typeId)}">
        <div class="vje-fg vr-vat-rate"><input type="number" class="vje-ded-rate" min="0" max="100" step="0.01" placeholder="Rate %" aria-label="Withholding VAT rate (%)"></div>
        <div class="vje-fg vr-vat-wamt"><input type="text" class="vje-ded-amount" readonly placeholder="Amount" aria-label="Withholding VAT amount"></div>
        <div class="vr-vat-info">VAT computation (informational, not deducted): statutory ${VJE_VAT_RATE}% &middot;
            VAT <input type="text" class="vje-vat-amt" readonly aria-label="VAT amount"> &middot;
            Tax base <input type="text" class="vje-vat-base" readonly aria-label="Tax base"></div>
        <span class="vje-vat-hint"></span>`;
    div.querySelector('.vje-ded-rate').value = saved ? (d.rate ?? '') : '';
    $vr('vje-ded-list').appendChild(div);
    div.querySelector('.vje-ded-rate').addEventListener('input', () => vje_recalcDeductions());
    div.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', () => {
        div.dataset.vatType = r.value;
        vje_recalcDeductions(); // vje_syncVat() + vje_syncAtcBases(): the ATC rows follow the new base, keeping their split
    }));
    vje_recalcDeductions();
}

/* Refreshes the VAT row's VAT Amount / Tax Base / Withholding VAT from the DV's (fixed) gross. */
function vje_syncVat(row) {
    const gross   = Number(vje_dv?.gross_amount) || 0;
    const vatType = row.dataset.vatType === 'exclusive' ? 'exclusive' : 'inclusive';
    const { vat, base } = vje_vatSplit(gross, vatType);
    row.dataset.base = base;
    row.querySelector('.vje-vat-amt').value  = vje_money2(vat);
    row.querySelector('.vje-vat-base').value = vje_money2(base);
    row.querySelectorAll('.vje-vat-opt').forEach(l => {
        const input = l.querySelector('input');
        input.checked = input.value === vatType;
        l.classList.toggle('active', input.checked);
    });
    const rate = parseFloat(row.querySelector('.vje-ded-rate').value);
    const wvat = rate > 0 ? vje_pctOf(base, rate) : 0;
    row.querySelector('.vje-ded-amount').value = wvat > 0 ? vje_money2(wvat) : '';
    row.querySelector('.vje-vat-hint').textContent =
        (vatType === 'inclusive' ? `Tax Base = Gross ÷ ${(1 + VJE_VAT_RATE / 100).toFixed(2)}` : 'Tax Base = Gross (VAT is added on top)')
        + (rate > 0 ? ` · Withholding VAT = Tax Base × ${rate}%` : ' · enter a Withholding VAT rate to deduct it');
}

/* A deduction's gross_amount always mirrors this DV's own gross_amount (not separately editable —
   see the RFA Breakdown / summary); its deduction_amount is rate% of that unless the
   preparer types an amount directly (no rate given = flat peso deduction). Same convention as
   app/pages/voucher/form.php's vf_recalcDeductionLine(). */
function vje_recalcDeductionLine(idx) {
    const tr = $vr(`vje-ded-row-${idx}`);
    if (!tr) return;
    if (tr.dataset.vat === '1') { vje_recalcDeductions(); return; } // VAT row: vje_recalcDeductions() -> vje_syncVat() owns its figures
    const gross = tr.dataset.base !== undefined ? Number(tr.dataset.base) : (Number(vje_dv?.gross_amount) || 0); // a legacy VAT line keeps its own Tax Base
    const rate = parseFloat(tr.querySelector('.vje-ded-rate').value);
    if (!isNaN(rate) && rate > 0) {
        tr.querySelector('.vje-ded-amount').value = vje_money2(vje_pctOf(gross, rate));
    }
    vje_recalcDeductions();
}

function vje_collectDeductions() {
    const out = [];
    vje_dedRows().forEach(tr => {
        const typeId = tr.querySelector('.vje-ded-type').value;
        const amount = vje_num(tr.querySelector('.vje-ded-amount').value);
        if (!typeId && amount <= 0) return; // blank trailing row
        out.push({
            deduction_type_id: typeId || null,
            rate             : tr.querySelector('.vje-ded-rate').value === '' ? null : parseFloat(tr.querySelector('.vje-ded-rate').value),
            gross_amount     : tr.dataset.base !== undefined ? Number(tr.dataset.base) : (Number(vje_dv?.gross_amount) || 0),
            deduction_amount : amount,
            account_code     : tr.querySelector('.vje-ded-acct')?.value.trim() || null,
        });
    });
    return out;
}

function vje_totalDeductions() {
    return vje_collectDeductions().reduce((s, d) => s + d.deduction_amount, 0);
}

function vje_recalcDeductions() {
    const vatRow = vje_vatRow();
    if (vatRow) vje_syncVat(vatRow); // VAT / Tax Base / Withholding VAT first — the totals below read the Withholding VAT amount
    const badge = $vr('vje-badge-net-ded');
    if (badge) badge.textContent = vr_peso(vje_liveGrossLessDeductions());
    vje_syncAtcBases(); // the base the ATC rows split may just have moved (a deduction, the VAT type) — before the entries are built from them
    vje_refreshSummary();
    vje_syncAutoLines();
}

/* ── Withholding Tax Classification (ATC) ────────────────────── */
function vje_addAtcLine(a = null) {
    const idx = ++vje_atcSeq;
    const div = document.createElement('div');
    div.className = 'vje-row vje-atc-row';
    div.id = `vje-atc-row-${idx}`;
    div.innerHTML = `
        <button type="button" class="vje-remove" title="Remove" onclick="vje_removeAtcLine(${idx})">&times;</button>
        <div class="vr-ded-label">ATC (Withholding Tax)</div>
        <div class="vje-fg vje-atc-codefg"><label>ATC Code — Description</label><input type="text" class="vje-atc-code" placeholder="Search ATC code…"><span class="vr-acct-hint"></span></div>
        <div class="vje-grid3">
            <div class="vje-fg"><label>Rate (%)</label><input type="number" class="vje-atc-rate" min="0" max="100" step="0.01" placeholder="0.00"></div>
            <div class="vje-fg"><label>Tax Base (₱)</label><input type="text" inputmode="decimal" class="vje-atc-base" placeholder="0.00"></div>
            <div class="vje-fg"><label>Withholding Tax (₱)</label><input type="text" class="vje-atc-wh" readonly value="₱ 0.00"></div>
        </div>
        <div class="vje-fg" style="display:none;"><label>Chargeability</label>
            <select class="vje-atc-rc">
                <option value="">— None —</option>
                ${VJE_RC.map(r => `<option value="${vr_esc(r.rc_id)}">${vr_esc(r.rc_id)} — ${vr_esc(r.rc_name)}</option>`).join('')}
            </select>
        </div>`;
    div.querySelector('.vje-atc-code').value = a?.atc_code ?? '';
    div.querySelector('.vje-atc-rate').value = a?.rate ?? '';
    // New row: the part of the base the other ATC rows haven't taken yet (a second row at the whole base made the
    // rows' total exceed it). The base is the VAT-exclusive Tax Base when this DV has a VAT line, else gross less deductions.
    div.querySelector('.vje-atc-base').value = a ? vje_money2(Number(a.tax_base) || 0) : vje_money2(Math.max(0, Math.round((vje_fullBase() - vje_atcBaseTotal()) * 100) / 100));
    vje_bindMoney(div.querySelector('.vje-atc-base')); // before the listeners below, so they read the comma-formatted value
    div.querySelector('.vje-atc-rc').value   = a?.chargeability ?? '';
    div.querySelector('.vje-atc-code').addEventListener('change', () => vje_onAtcCodeSelect(idx));
    div.querySelector('.vje-atc-base').addEventListener('input', () => vje_setAtcShare(div)); // typed by hand: its share of the base changes
    div.querySelectorAll('.vje-atc-rate, .vje-atc-base').forEach(el => el.addEventListener('input', () => vje_recalcAtcLine(idx)));
    $vr('vje-ded-list').appendChild(div);
    vje_markAtcMulti();
    vje_atcAcctHint(div);
    vje_setAtcShare(div);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(div.querySelector('.vje-atc-code'), VJE_ATC.map(c => ({ value: c.atc_code, label: c.description })));
    vje_recalcAtcLine(idx);
}

function vje_removeAtcLine(idx) {
    $vr(`vje-atc-row-${idx}`)?.remove();
    vje_markAtcMulti();
    vje_recalcAtc();
    vje_syncAutoLines();
}

/**
 * Picking an ATC code prefills the rest of its row — same convention as app/pages/voucher/form.php's
 * vf_onAtcCodeSelect(): Rate(%) always follows the code's own BIR default (fims.atc_codes.tax_rate,
 * overwriting whatever was there), Tax Base is already defaulted to this DV's net_amount at row
 * creation (see vje_addAtcLine) and is left alone here, and Chargeability defaults — if not already
 * set — to the first RFA this DV cites (fims.budget_allocation.requesting_rc_id), since that's who
 * the withholding is normally charged against.
 */
function vje_onAtcCodeSelect(idx) {
    const tr = $vr(`vje-atc-row-${idx}`);
    if (!tr) return;
    const code   = tr.querySelector('.vje-atc-code').value;
    const atcDef = VJE_ATC.find(c => c.atc_code === code);
    const rateEl = tr.querySelector('.vje-atc-rate');
    const rcEl   = tr.querySelector('.vje-atc-rc');

    vje_atcAcctHint(tr);
    if (atcDef && isFinite(Number(atcDef.tax_rate))) rateEl.value = Number(atcDef.tax_rate).toFixed(2);

    if (!rcEl.value) {
        const primaryRc = vje_dv?.rfas?.[0]?.requesting_rc_id;
        if (primaryRc) rcEl.value = primaryRc;
    }

    vje_recalcAtcLine(idx);
}

/* rate% of an amount, rounded to the centavo exactly like the server's round($base * $rate / 100, 2) — same helper as
   form.php's vf_pctOf(). Math.round(base * rate) / 100 is a centavo off on exact half-centavo products (~0.1% of the time),
   and here that also shifts the auto-built Cr lines, whose total must equal the Dr side exactly for the entry to save. */
function vje_pctOf(base, rate) {
    const cents = Math.round((Number(base) || 0) * 100), hundredths = Math.round((Number(rate) || 0) * 100);
    return Math.floor((cents * hundredths + 5000) / 10000) / 100;
}

/* Withholding Tax cell of one ATC row = Tax Base x Rate%, centavo-exact (vje_pctOf()). */
function vje_refreshAtcWh(tr) {
    const rate = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
    const base = vje_num(tr.querySelector('.vje-atc-base').value);
    tr.querySelector('.vje-atc-wh').value = vr_peso(vje_pctOf(base, rate));
}

function vje_recalcAtcLine(idx) {
    const tr = $vr(`vje-atc-row-${idx}`);
    if (!tr) return;
    vje_refreshAtcWh(tr);
    vje_recalcAtc();
    vje_syncAutoLines();
}

/* ── What the ATC rows split — same rules as form.php's vf_fullBase() / vf_syncAtcBases() ──
   The withholding is computed on the VAT-exclusive Tax Base when the DV has a VAT line, otherwise on gross less the
   deductions. The ATC rows' Tax Bases SPLIT that base between them: a new row defaults to the part not yet allocated, and
   when the base moves (a deduction edited, the VAT type toggled) the rows follow it in proportion — but only while they add up
   to the whole base, so a deliberately partial base is never overwritten. */
function vje_fullBase() {
    const vatRow = vje_vatRow();
    return vatRow ? (Number(vatRow.dataset.base) || 0) : vje_liveGrossLessDeductions();
}

function vje_atcBaseTotal(exceptRow = null) {
    let sum = 0;
    vje_atcRows().forEach(tr => { if (tr !== exceptRow) sum += vje_num(tr.querySelector('.vje-atc-base').value); });
    return Math.round(sum * 100) / 100;
}

function vje_setAtcShare(tr) {
    const full = vje_fullBase();
    tr.dataset.share = full > 0 ? (vje_num(tr.querySelector('.vje-atc-base').value)) / full : 0;
}

function vje_syncAtcBases() {
    const full = vje_fullBase();
    const prev = vje_atcFull;
    vje_atcFull = full;
    const rows = [...vje_atcRows()];
    if (prev !== null && prev > 0 && rows.length && Math.abs(full - prev) > 0.004) {
        const bases = rows.map(tr => vje_num(tr.querySelector('.vje-atc-base').value));
        if (Math.abs(bases.reduce((s, b) => s + b, 0) - prev) <= 0.01 * rows.length) { // fully allocated: the split follows the base
            const next = rows.map((tr, i) => {
                const share = tr.dataset.share !== undefined ? Number(tr.dataset.share) : bases[i] / prev;
                return Math.round(share * full * 100) / 100;
            });
            const big = next.indexOf(Math.max(...next)); // the centavos lost to rounding go to the largest row, never an empty one
            next[big] = Math.round((next[big] + (full - next.reduce((s, b) => s + b, 0))) * 100) / 100;
            rows.forEach((tr, i) => { tr.querySelector('.vje-atc-base').value = vje_money2(next[i]); vje_refreshAtcWh(tr); });
        }
    }
    vje_renderAtcSummary(full);
}

function vje_renderAtcSummary(full) {
    const el = $vr('vje-atc-summary');
    if (!el) return;
    const rows = vje_atcRows();
    if (!rows.length) { el.style.display = 'none'; return; }
    const total = vje_atcBaseTotal();
    const over  = total - full > 0.005;
    const what  = vje_vatRow() ? 'the VAT-exclusive Tax Base' : 'the amount net of deductions';
    el.className = 'vje-atc-summary' + (over ? ' over' : '');
    el.textContent = over
        ? `ATC Tax Bases total ${vr_peso(total)}, which is more than ${what} (${vr_peso(full)}) — the withholding would be taken on the same amount twice. Split the base between the rows.`
        : `ATC Tax Bases total ${vr_peso(total)} of ${what} (${vr_peso(full)})${full - total > 0.005 ? ` · ${vr_peso(full - total)} not allocated to an ATC row` : ''}.`;
    el.style.display = '';
}

function vje_collectAtcLines() {
    const lines = [];
    vje_atcRows().forEach(tr => {
        const atc_code = tr.querySelector('.vje-atc-code').value;
        const rate     = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
        const tax_base = vje_num(tr.querySelector('.vje-atc-base').value);
        if (!atc_code && tax_base <= 0) return; // blank trailing row
        // Chargeability isn't asked for any more — the system takes it from the first RFA this DV cites.
        lines.push({ atc_code, rate, tax_base, chargeability: tr.querySelector('.vje-atc-rc').value || vje_dv?.rfas?.[0]?.requesting_rc_id || null });
    });
    return lines;
}

function vje_recalcAtc() {
    vje_renderAtcSummary(vje_fullBase()); // the "ATC Tax Bases total … of …" line under the rows
    vje_refreshSummary();
}

/* Section B's Gross / Less: Total Deductions / Less: Withholding Tax / Net Amount Payable table (and the Net Amount banner at
   the top) are drawn once from the saved voucher by vr_render(). While Accounting is editing the Deduction / VAT / ATC rows
   they follow those rows live instead, the way form.php's vf_recalc() does — the server stores the same figures on save:
   net = gross - deductions - withholding. */
function vje_refreshSummary() {
    if (!$vr('vr-sum-net') || !vje_dv) return;
    const gross = Number(vje_dv.gross_amount) || 0;
    const ded   = Math.round(vje_totalDeductions() * 100) / 100;
    const wh    = vje_collectAtcLines().reduce((s, l) => s + Math.round(vje_pctOf(l.tax_base, l.rate) * 100), 0) / 100;
    const net   = Math.max(0, Math.round((gross - ded - wh) * 100) / 100);
    $vr('vr-sum-ded').textContent = vr_peso(ded);
    $vr('vr-sum-wh').textContent  = vr_peso(wh);
    $vr('vr-sum-net').textContent = vr_peso(net);
    const netVis = $vr('vr-sum-net-vis'); if (netVis) netVis.textContent = vr_peso(net);
    const top = $vr('vr-top-net');
    if (top) top.textContent = vr_peso(net);
}

/* ── Accounting Entries (DV) ──────────────────────────────────── */

/**
 * Upserts the auto-derived lines (Dr expense for the full gross, Cr each Deduction/ATC row that
 * carries a mapped UACS account code, Cr Accounts Payable for whatever's left) into the grid,
 * tracked via each row's data-auto-source so repeat syncs update in place instead of duplicating.
 * Always runs, on every Deduction/ATC/Account Code change, including after a Journal Entry has
 * already been saved once — a line the preparer added manually (no data-auto-source) is never
 * touched; see vje_init()'s matching logic for how a RELOADED saved line gets re-tagged so this
 * doesn't duplicate it.
 */
/* ── Accounting Entries grid: flat, one row per journal entry line — same convention as
   app/pages/voucher/form.php's own Accounting Entries table (vf_addJevLine). ── */

function vje_addNewLine() {
    vje_addLine();
}

function vje_addLine(d = null) {
    const lineId = ++vje_seq;
    const tr = document.createElement('tr');
    tr.id = `vje-row-${lineId}`;
    tr.className = 'vje-line';
    tr.dataset.lineId = lineId;
    tr.innerHTML = `
        <td><div class="vje-acct-wrap"><input type="text" class="vje-acct-code" placeholder="Search GL account code (UACS)…"><span class="vje-acct-title"><span></span></span></div></td>
        <td><input type="text" class="vje-line-desc" placeholder="Particular"></td>
        <td><input type="text" inputmode="decimal" class="vje-debit" placeholder="0.00"></td>
        <td><input type="text" inputmode="decimal" class="vje-credit" placeholder="0.00"></td>
        <td><button type="button" class="vje-remove" title="Remove line" onclick="vje_removeLine(${lineId})">&times;</button></td>`;
    const acctInput = tr.querySelector('.vje-acct-code');
    const descInput = tr.querySelector('.vje-line-desc');
    const debitInput = tr.querySelector('.vje-debit');
    const creditInput = tr.querySelector('.vje-credit');
    vje_setLineAccount(tr, d?.account_code ?? '');
    descInput.value = d?.description ?? '';
    debitInput.value = Number(d?.debit) > 0 ? vje_money2(d.debit) : '';
    creditInput.value = Number(d?.credit) > 0 ? vje_money2(d.credit) : '';
    vje_bindMoney(debitInput);  // before the vje_recalc listeners below, so they read the comma-formatted value
    vje_bindMoney(creditInput);
    nhaSearchableSelect(acctInput, VJE_COA.map(a => ({ value: a.account_code, label: a.account_title })), () => vje_renderAcctTitle(tr));
    acctInput.addEventListener('input', () => vje_renderAcctTitle(tr));
    vje_renderAcctTitle(tr);
    debitInput.addEventListener('input', vje_recalc);
    creditInput.addEventListener('input', vje_recalc);
    $vr('vje-lines-tbody').appendChild(tr);
    vje_recalc();
    return lineId;
}

function vje_removeLine(lineId) {
    const tr = $vr(`vje-row-${lineId}`);
    if (tr) {
        tr.remove();
        vje_recalc();
    }
}

/* ── Accounts Payable: the GL on the line, the vendor's SL under it ─────────────────────────────────────────────────────
   The line's account box shows the GL (2-01-01-010 Accounts Payable); the sub-ledger assigned to the vendor / contractor
   (vendors.sl_code, e.g. 2-01-01-010-01-01 SUB SL 1.1) is shown read-only underneath. What is SAVED is still the SL's own
   account code (tr.dataset.slCode — vje_collectLines() sends it instead of the GL), so posting and the grouped view are unchanged.
   Without an SL assigned to the vendor the line is just the GL. The SL is changed on the vendor, not here. */
const VJE_AP_ROOT = '2-01-01-010';
function vje_apDefaultCode() { return vje_dv?.vendor_ap_account_code || VJE_AP_ROOT; }
function vje_isApSl(code) { return String(code || '').startsWith(VJE_AP_ROOT + '-'); }

/** Sets a line's account: an Accounts Payable sub-ledger code goes into slCode (the box shows its GL), anything else goes in the box. */
function vje_setLineAccount(tr, code) {
    const input = tr.querySelector('.vje-acct-code');
    code = String(code || '').trim();
    if (vje_isApSl(code)) { tr.dataset.slCode = code; input.value = VJE_AP_ROOT; }
    else { delete tr.dataset.slCode; input.value = code; }
}
/** The account code this line posts to: its SL when it has one, else what is in the box. */
function vje_lineAccountCode(tr) {
    return (tr.dataset.slCode || tr.querySelector('.vje-acct-code').value).trim();
}

function vje_renderSlLine(tr) {
    const input = tr.querySelector('.vje-acct-code');
    // the box was edited to a different account: the SL no longer applies
    if (tr.dataset.slCode && input.value.trim() !== VJE_AP_ROOT) delete tr.dataset.slCode;
    let el = tr.querySelector('.vje-sl-line');
    const sl = tr.dataset.slCode;
    if (!sl) { el?.remove(); return; }
    const cell = tr.querySelector('.vje-acct-wrap')?.parentNode;
    if (!cell) return;
    if (!el) { el = document.createElement('div'); el.className = 'vje-sl-line'; cell.appendChild(el); }
    const t = ((VJE_COA || []).find(a => a.account_code === sl) || {}).account_title || '';
    el.innerHTML = `<span>SL</span> ${vr_esc(sl)}${t ? ` <em>— ${vr_esc(t)}</em>` : ''}`;
    el.title = 'Sub-ledger assigned to the vendor / contractor';
}

/** The picked account's title, inside the Account Code field right after the code — "1-04-01-010 - Due to BIR". The input still holds
    only the code (the save and every calculation read it as the code); the title is a click-through overlay whose left edge is the
    end of the typed text, measured with the input's own font. */
function vje_renderAcctTitle(tr) {
    const input = tr.querySelector('.vje-acct-code');
    const box   = tr.querySelector('.vje-acct-title');
    if (!input || !box) return;
    const code = input.value.trim();
    const acct = code ? (VJE_COA || []).find(a => a.account_code === code) : null;
    vje_renderSlLine(tr);
    if (!acct?.account_title) { box.style.display = 'none'; return; }
    const cs = getComputedStyle(input);
    if (!vje_renderAcctTitle.ctx) vje_renderAcctTitle.ctx = document.createElement('canvas').getContext('2d');
    const ctx = vje_renderAcctTitle.ctx;
    ctx.font = `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
    const start = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.borderLeftWidth) || 0) + ctx.measureText(input.value).width;
    box.style.left = (start + 5) + 'px';
    box.firstElementChild.textContent = '- ' + acct.account_title;
    box.title = code + ' - ' + acct.account_title;
    box.style.display = 'flex';
}

/**
 * A Remittance DV from the Remittance List (dv.dv_remit): the vendor and the voucher-by-voucher schedule of what it pays. '' for any other DV.
 */
function vr_remitScheduleHtml(dv) {
    const d = dv.dv_remit;
    if (!d) return '';
    // the vouchers this DV pays: each source DV with its ATC lines under it
    const groups = [];
    (d.schedule || []).forEach(s => {
        let g = groups.find(x => x.dv_id === s.dv_id);
        if (!g) { g = { dv_id: s.dv_id, dv_no: s.dv_no, dv_date: s.dv_date, vendor: s.claimant_name, total: 0, lines: [] }; groups.push(g); }
        g.total += Number(s.amount || 0);
        g.lines.push(s);
    });
    // A Remittance DV is for one vendor: its name is shown once at the top (per voucher only if they ever differ)
    const oneVendor = groups.length > 0 && groups.every(g => g.vendor === groups[0].vendor);
    const border = '1px solid var(--afims-border,#E4ECF2)';
    const th = 'padding:9px 14px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted,#62657E);background:var(--afims-surface-subtle,#F4F7FA);border-bottom:' + border + ';';
    const td = 'padding:11px 14px;font-size:13px;border-bottom:' + border + ';';
    const num = "text-align:right;white-space:nowrap;font-family:'IBM Plex Mono',monospace;";
    const sched = groups.map(g => `<div style="margin-top:14px;">
            <div style="display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 18px;margin-bottom:8px;">
                <span><span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted,#62657E);">DV No.</span> <strong class="nha-mono" style="font-size:14px;">${vr_esc(g.dv_no)}</strong></span>
                <span><span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text-muted,#62657E);">Date</span> <strong style="font-size:14px;">${vr_fmtDate(g.dv_date)}</strong></span>
                ${oneVendor ? '' : `<span style="font-weight:600;">${vr_esc(g.vendor)}</span>`}
            </div>
            <div style="overflow-x:auto;border:${border};border-radius:8px;"><table style="width:100%;border-collapse:collapse;">
                <thead><tr><th style="${th}text-align:left;">ATC</th><th style="${th}text-align:right;">Rate</th><th style="${th}text-align:right;">Tax Base</th><th style="${th}text-align:right;">Withheld</th></tr></thead>
                <tbody>${g.lines.map(s => `<tr><td style="${td}" class="nha-mono">${vr_esc(s.atc_code || d.type_name)}</td><td style="${td}${num}">${s.rate === null || s.rate === undefined ? '' : vr_esc(Number(s.rate) + '%')}</td><td style="${td}${num}">${vr_peso(s.base)}</td><td style="${td}${num}">${vr_peso(s.amount)}</td></tr>`).join('')}
                <tr><td colspan="3" style="${td}border-bottom:0;font-weight:700;">Total</td><td style="${td}${num}border-bottom:0;font-weight:700;">${vr_peso(g.total)}</td></tr></tbody>
            </table></div>
        </div>`).join('');
    return `<div class="vr-card">
        ${oneVendor ? `<div style="padding-bottom:14px;border-bottom:${border};">
            <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-muted,#62657E);margin-bottom:4px;">Vendor</div>
            <div style="font-size:20px;font-weight:700;line-height:1.25;color:var(--afims-heading,#000E3E);">${vr_esc(groups[0].vendor)}</div>
        </div>` : ''}
        <p class="vr-card-title" style="margin:14px 0 0;">Vouchers paid — ${Number(d.vouchers) || 0} DV(s), ${(d.schedule || []).length} ATC line(s)</p>
        ${sched}
    </div>`;
}

/** Keeps the Accounting Entries grid in sync with the DV's own numbers: Dr the claimant/expense
    line for the full gross, Cr each Deduction/ATC row that carries a mapped UACS account code, Cr
    Accounts Payable for whatever's left. Tracked via each row's data-auto-source so repeat syncs
    update it in place instead of duplicating it; a line the preparer added by hand (no
    data-auto-source) is never touched. */
function vje_syncAutoLines() {
    // A payroll DV's / remittance DV's entries are built by their own module (Dr each earnings account,
    // Cr each payroll item's payable, Cr Accounts Payable net — PayrollManagementService::dvData();
    // Dr the agency's payables + government share, Cr Accounts Payable — DeductionRemittanceService::dvData())
    // and arrive balanced. The vendor-DV auto lines below (Dr Account Code for the gross, Cr each deduction
    // type, Cr AP) would take over / double those lines, so they are left exactly as saved.
    // A Remittance DV from the Remittance List (dv_remit: Dr Due to BIR / VAT Payable, Cr Accounts Payable) is the same.
    if (vje_dv?.payroll || vje_dv?.remittance || vje_dv?.dv_remit) {
        $vr('vje-lines-tbody').querySelectorAll('tr.vje-line[data-auto-source]').forEach(tr => { delete tr.dataset.autoSource; });
        vje_recalc();
        return;
    }
    const gross = Number(vje_dv?.gross_amount) || 0;

    vje_upsertAutoLine('dr', { account_code: vje_dv?.account_code || '', debit: gross, credit: 0, description: vje_dv?.claimant_name || '' });

    // Reads the LIVE Deduction grid (vje_dedAtcEditor()), not a static vje_dv.deductions snapshot —
    // Accounting can now add/change/remove rows here, see vje_addDeductionLine()'s doc comment.
    const dedIds = new Set();
    let dedCreditTotal = 0;
    vje_dedRows().forEach(tr => {
        const idx      = tr.id.replace('vje-ded-row-', '');
        const typeId   = tr.querySelector('.vje-ded-type').value;
        const amount   = vje_num(tr.querySelector('.vje-ded-amount').value);
        const type     = VJE_DED.find(t => String(t.deduction_type_id) === typeId);
        const acctCode = type?.account_code || tr.querySelector('.vje-ded-acct')?.value.trim() || '';
        if (!typeId || !acctCode || amount <= 0) return;
        dedIds.add(idx);
        dedCreditTotal += amount;
        vje_upsertAutoLine(`ded:${idx}`, { account_code: acctCode, debit: 0, credit: amount, description: type.name });
    });
    vje_pruneAutoLines('ded:', id => dedIds.has(id));

    const atcIds = new Set();
    let atcCreditTotal = 0;
    vje_atcRows().forEach(tr => {
        const idx  = tr.id.replace('vje-atc-row-', '');
        const code = tr.querySelector('.vje-atc-code').value;
        const rate = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
        const base = vje_num(tr.querySelector('.vje-atc-base').value);
        const atcDef = VJE_ATC.find(c => c.atc_code === code);
        if (!code || !atcDef?.account_code || base <= 0 || rate <= 0) return;
        const credit = vje_pctOf(base, rate);
        atcIds.add(idx);
        atcCreditTotal += credit;
        vje_upsertAutoLine(`atc:${idx}`, { account_code: atcDef.account_code, debit: 0, credit, description: `${rate}% ${atcDef.description}` });
    });
    vje_pruneAutoLines('atc:', id => atcIds.has(id));

    // Credits the vendor's own Accounts Payable sub-ledger account (fims.vendors.coa_account_id,
    // e.g. "2-01-01-010-01") when the claimant is a registered vendor with one set up, so the
    // payable actually lands in that vendor's subsidiary ledger instead of just the bare AP
    // control account — see DisbursementVoucherService::fetchById()'s vendor_ap_account_code.
    // The vendor's own SL (vendors.sl_code) when it has one — shown under the GL, see vje_setLineAccount() — else the GL itself.
    const apAccountCode = vje_apDefaultCode();
    const apRemainder = Math.round((gross - dedCreditTotal - atcCreditTotal) * 100) / 100;
    if (apRemainder > 0.004) {
        vje_upsertAutoLine('ap', { account_code: apAccountCode, debit: 0, credit: apRemainder, description: 'Net Amount Payable' });
    } else {
        $vr('vje-lines-tbody').querySelector('tr.vje-line[data-auto-source="ap"]')?.remove();
    }

    vje_recalc();
}

function vje_upsertAutoLine(sourceKey, d) {
    let tr = $vr('vje-lines-tbody').querySelector(`tr.vje-line[data-auto-source="${sourceKey}"]`);
    if (!tr) {
        const lineId = vje_addLine(d);
        tr = $vr(`vje-row-${lineId}`);
        tr.dataset.autoSource = sourceKey;
        return;
    }
    vje_setLineAccount(tr, d.account_code || '');
    tr.querySelector('.vje-line-desc').value = d.description || '';
    tr.querySelector('.vje-debit').value  = d.debit  > 0 ? vje_money2(d.debit)  : '';
    tr.querySelector('.vje-credit').value = d.credit > 0 ? vje_money2(d.credit) : '';
}

/** Removes auto-tracked lines whose source (a Deduction or live ATC row) no longer applies. */
function vje_pruneAutoLines(prefix, keepFn) {
    $vr('vje-lines-tbody').querySelectorAll(`tr.vje-line[data-auto-source^="${prefix}"]`).forEach(tr => {
        if (keepFn(tr.dataset.autoSource.slice(prefix.length))) return;
        tr.remove();
    });
}

function vje_collectLines() {
    const lines = [];
    $vr('vje-lines-tbody').querySelectorAll('tr.vje-line').forEach(tr => {
        const account_code = vje_lineAccountCode(tr);
        const debit        = vje_num(tr.querySelector('.vje-debit').value);
        const credit       = vje_num(tr.querySelector('.vje-credit').value);
        const description  = tr.querySelector('.vje-line-desc').value.trim();
        if (!account_code && debit <= 0 && credit <= 0 && !description) return; // blank trailing line
        lines.push({ account_code, debit, credit, description: description || null });
    });
    return lines;
}

function vje_updateAcctgEntryPanels(dv) {
    const vendor = dv?.vendor_name || '—';
    const amount = Number(dv?.gross_amount) || 0;
    const acctCode = dv?.account_code || '—';

    const vendorEl = $vr('vje-acc-vendor-name');
    if (vendorEl) vendorEl.textContent = vendor;

    const amountEl = $vr('vje-acc-amount');
    if (amountEl) amountEl.textContent = vr_peso(amount);

    const acctEl = $vr('vje-acc-account-code');
    if (acctEl) acctEl.textContent = acctCode;

    // Populate RFA list
    const rfaList = $vr('vje-rfa-list');
    if (rfaList) {
        const rfarefs = VJE_RFA || [];
        rfaList.innerHTML = '';
        rfarefs.forEach(r => {
            const div = document.createElement('div');
            div.style.cssText = 'padding:8px;border-radius:6px;background:var(--afims-surface-subtle,#F4F7FA);cursor:pointer;';
            div.onclick = () => vje_selectRfa(r.allocation_number);
            div.innerHTML = `
                <div style="font-weight:600;color:var(--afims-text,#1e293b);">${r.allocation_number}</div>
                <div style="font-size:12px;color:var(--afims-text-muted,#62657E);margin-top:2px;">${r.request_referrence_no || ''}</div>
            `;
            rfaList.appendChild(div);
        });
    }
}

function vje_selectRfa(rfaNumber) {
    const tr = $vr('vje-lines-tbody').querySelector('tr.vje-line:last-child');
    if (tr) {
        tr.querySelector('.vje-line-rfa').value = rfaNumber;
        vje_recalc();
    }
}

function vje_recalc() {
    $vr('vje-lines-tbody').querySelectorAll('tr.vje-line').forEach(vje_renderAcctTitle); // codes can be filled in programmatically (auto lines) — keep each row's title in step
    const lines = vje_collectLines();
    const totalDebit  = lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
    $vr('vje-total-debit').textContent  = vr_peso(totalDebit);
    $vr('vje-total-credit').textContent = vr_peso(totalCredit);
    const balanced = lines.length >= 2 && totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.005;
    const msg = $vr('vje-balance-msg');
    msg.textContent = balanced ? '' : 'Not balanced — debits must equal credits'; // nothing to say when it balances
    msg.style.color = balanced ? 'var(--afims-success, #16A34A)' : 'var(--afims-danger, #DC3545)';
    return balanced;
}

// Saves the Deductions / ATC / Accounting Entries grids. There's no separate "Save Journal Entry"
// button anymore — vr_changeStatus() calls this automatically right before forwarding the voucher
// to the Division Manager, and only forwards if it returns true. Resolves false (after alerting)
// on a validation/server error; doesn't reload the modal — the status change that follows does.
async function vje_save() {
    const lines = vje_collectLines();
    const atc_lines = vje_collectAtcLines();
    const deductions = vje_collectDeductions();
    const book_id = $vr('vje-book')?.value || null;
    const jev_classification = 'REGULAR';

    const errBook = $vr('vje-err-book');
    if (lines.length && !book_id) {
        if (errBook) errBook.style.display = 'block';
        alert('Book is required once Accounting Entries lines are filled in.');
        return false;
    }
    if (errBook) errBook.style.display = 'none';

    const missingAcct = deductions.find(d => {
        const type = VJE_DED.find(t => String(t.deduction_type_id) === String(d.deduction_type_id));
        return !!d.deduction_type_id && !type?.account_code && !d.account_code;
    });
    if (missingAcct) {
        const type = VJE_DED.find(t => String(t.deduction_type_id) === String(missingAcct.deduction_type_id));
        alert(`"${type?.name || 'This deduction'}" has no default posting account — pick one before saving.`);
        return false;
    }

    // ATC Tax Bases are a split of one base: none may be left empty, and together they may not exceed the VAT-exclusive Tax Base
    // (with a VAT line) or the DV's Amount (the server enforces the Amount ceiling too).
    const emptyAtc = atc_lines.findIndex(l => l.atc_code && l.tax_base <= 0);
    if (emptyAtc >= 0) {
        alert(`ATC row ${emptyAtc + 1} (${atc_lines[emptyAtc].atc_code}) has no Tax Base — give it its share of the base, or remove the row.`);
        return false;
    }
    const atcTotal = vje_atcBaseTotal();
    const atcCeiling = vje_vatRow() ? vje_fullBase() : (Number(vje_dv?.gross_amount) || 0);
    if (atcTotal - atcCeiling > 0.005) {
        alert(`ATC Tax Bases total ${vr_peso(atcTotal)}, more than the ${vje_vatRow() ? 'VAT-exclusive Tax Base' : 'Amount'} (${vr_peso(atcCeiling)}) — split the base between the ATC rows.`);
        return false;
    }

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${vr_id}/journal-entry`, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({ atc_lines, lines, deductions, book_id, jev_classification }),
        });
        const json = await res.json();
        if (!json.success) {
            alert((json.errors?.length ? json.errors.join('\n') : null) || json.message || 'Failed to save Journal Entry.');
            return false;
        }
        return true;
    } catch (e) {
        alert('Could not save: ' + e.message);
        return false;
    } finally {
        window.PagePreloader?.hide();
    }
}

async function vr_changeStatus(status) {
    let notes = null;
    if (status === 'REJECTED') {
        notes = prompt('Reason for returning this disbursement voucher:');
        if (notes === null) return;
        if (!notes.trim()) { alert('A reason is required when returning.'); return; }
    } else if (!confirm(`Change this disbursement voucher to ${status}?`)) {
        return;
    }

    // Accounting's Journal Entry grids are saved as part of forwarding — no separate Save step.
    if (status === 'FOR_DIVISION_MANAGER' && vr_dv && vje_visible(vr_dv)) {
        if (!(await vje_save())) return;
    }

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${vr_id}/status`, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({ status, notes }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to change status.');
        window.vf_onSaved?.(null, json.message); // reuses the list page's toast + reload hook
        await vr_open(vr_id);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

// Accounting prepares the payment Journal Entry for a released DV (Dr A/P / Cr Cash from its check).
// Server re-checks RC-012/sysadmin — see DisbursementVoucherService::preparePaymentJev().
async function vr_preparePaymentJev(dvId) {
    if (!confirm('Prepare the payment Journal Entry for this voucher? It will enter the Accounting review chain: Section Chief → Division Manager → Department Manager posts it.')) return;
    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${dvId}/prepare-payment-jev`, { method: 'POST' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to prepare the payment entry.');
        window.vf_onSaved?.(null, json.message);
        await vr_open(vr_id);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

// Advance a payment Journal Entry one step along the review chain (and post it on the final step).
// Server re-checks RC-012/sysadmin and the allowed transition — see DisbursementVoucherService::changeJevStatus().
async function vr_jevStatus(jevId, status) {
    if (!confirm(`Move this payment Journal Entry to ${status.replace(/_/g, ' ')}?`)) return;
    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/journal-entry/${jevId}/jev-status`, {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ status }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to update the Journal Entry.');
        window.vf_onSaved?.(null, json.message);
        await vr_open(vr_id);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function vr_statusClass(s) {
    const map = {
        DRAFT: 'nha-bdg-gray', FOR_FMD_REVIEW: 'nha-bdg-amber', FOR_ACCOUNTING: 'nha-bdg-blue',
        FOR_DIVISION_MANAGER: 'nha-bdg-purple', FOR_DEPARTMENT_MANAGER: 'nha-bdg-navy',
        FOR_TREASURY: 'nha-bdg-teal', RELEASED: 'nha-bdg-green', REJECTED: 'nha-bdg-red', CANCELLED: 'nha-bdg-orange',
    };
    return map[s] || 'nha-bdg-gray';
}
function vr_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
/* Shows the account title alongside the code — same Chart of Accounts lookup the searchable-select
   pickers (nhaSearchableSelect, form.php / vje_addLine) show as their option labels — and flags it
   if this DV's own account isn't posting_allowed (shouldn't happen for a voucher saved since that
   check was added, but can for older/legacy data). */
/* "RFA Breakdown" — same table as the entry form: one row per itemized RFA line ("account_code - description"),
   the RFA it belongs to, that RFA's balance, and the Amount. The per-line split isn't stored (only gross_amount is),
   so the gross is spread over the lines in order, each up to its own amount, any excess on the last line — exactly
   what the form shows by default. No Gross Amount row here: the summary below has it. */
function vr_rfaBreakdownHtml(dv) {
    const gross = Number(dv.gross_amount) || 0;
    const rows = [];
    (dv.rfas ?? []).forEach(r => {
        const lines = (r.lines || []).length
            ? r.lines.map(l => ({ code: l.account_code, text: l.account_title || l.description, cap: Number(l.amount) || 0 }))
            : [{ code: r.account_code, text: r.account_title || r.description, cap: Number(r.remaining_balance) || 0 }];
        lines.forEach((l, i) => rows.push({ rfa: r, first: i === 0, span: lines.length, ...l }));
    });
    if (!rows.length) return '';
    let left = gross;
    rows.forEach((r, i) => {
        const v = i === rows.length - 1 ? left : Math.min(r.cap, left);
        r.amount = Math.max(0, Math.round(v * 100) / 100);
        left -= r.amount;
    });
    const label = (code, text) => [code, text].filter(Boolean).map(vr_esc).join(' - ') || '—';
    const body = rows.map(r => `<tr>
        <td>${label(r.code, r.text)}</td>
        ${r.first ? `<td rowspan="${r.span}" style="vertical-align:top;">${vr_esc(r.rfa.allocation_number)}</td>
        <td rowspan="${r.span}" style="text-align:right;vertical-align:top;">${vr_peso(r.rfa.remaining_balance)}</td>` : ''}
        <td><span class="vr-bd-amt">${r.amount ? vr_fmtNum(r.amount) : '—'}</span></td>
        <td></td>
    </tr>`).join('');
    const rfaBal = (dv.rfas ?? []).reduce((a, r) => a + (Number(r.remaining_balance) || 0), 0);
    return `<table class="vr-bd-table">
            <thead><tr>
                <th style="text-align:left;">Account Code &ndash; Description</th>
                <th style="text-align:left;">RFA</th>
                <th style="text-align:right;">RFA Balance / %</th>
                <th style="text-align:right;">Amount</th>
                <th></th>
            </tr></thead>
            <tbody>${body}</tbody>
            <tfoot><tr class="vr-bd-gross">
                <td colspan="2"><b>Gross amount</b> &middot; ${(dv.rfas ?? []).map(r => vr_esc(r.allocation_number)).join(', ')}</td>
                <td class="vr-bd-muted">${vr_peso(rfaBal)}</td>
                <td><b>${vr_peso(gross)}</b></td>
                <td></td>
            </tr></tfoot>
        </table>`;
}

function vr_fmtNum(v) {
    return Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* C. Accounting Entry (auto-generated) — read-only, built from the saved DV exactly like the entry form's live preview
   (and Accounting's vje_syncAutoLines()): Dr each RFA account line, Cr each deduction's / ATC's payable account, Cr Accounts
   Payable for the rest. Omitted for payroll / remittance DVs, which carry their own entries. */
function vr_acctEntryHtml(dv) {
    if (dv.payroll || dv.remittance || dv.dv_remit) return '';
    const title = code => (VJE_COA.find(a => a.account_code === code) || {}).account_title || '';
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
    const gross = Number(dv.gross_amount) || 0;
    const drs = [], crs = [];
    // debit lines: the same gross spread over the RFA lines as the breakdown above
    const lines = [];
    (dv.rfas ?? []).forEach(r => {
        const ls = (r.lines || []).length
            ? r.lines.map(l => ({ code: l.account_code, text: l.account_title || l.description, cap: Number(l.amount) || 0 }))
            : [{ code: r.account_code, text: r.account_title || r.description, cap: Number(r.remaining_balance) || 0 }];
        ls.forEach(l => lines.push(l));
    });
    let left = gross;
    lines.forEach((l, i) => {
        const v = r2(i === lines.length - 1 ? left : Math.min(l.cap, left));
        left -= v;
        if (v > 0) drs.push({ code: l.code || '', desc: l.text || title(l.code), amt: v });
    });
    if (!drs.length && gross > 0) drs.push({ code: dv.account_code || '', desc: dv.account_title || title(dv.account_code) || dv.claimant_name || '', amt: gross });
    if (!drs.length) return '';

    let credited = 0;
    (dv.deductions ?? []).forEach(d => {
        const amt = Number(d.deduction_amount) || 0, code = d.type_account_code || d.account_code || '';
        if (amt <= 0) return;
        credited += amt;
        crs.push({ code, desc: title(code) || d.name || '', amt });
    });
    (dv.atc_lines ?? []).forEach(a => {
        const amt = Number(a.withholding_tax) || 0;
        if (amt <= 0) return;
        credited += amt;
        crs.push({ code: a.account_code || '', desc: title(a.account_code) || a.atc_description || a.atc_code || '', amt });
    });
    const ap = r2(drs.reduce((x, d) => x + d.amt, 0) - credited);
    if (ap > 0.004) {
        const apCode = dv.vendor_ap_account_code || '2-01-01-010';
        crs.push({ code: apCode, desc: title(apCode) || 'Accounts Payable', amt: ap });
    }
    const totalDr = drs.reduce((x, d) => x + d.amt, 0), totalCr = crs.reduce((x, d) => x + d.amt, 0);
    const bad = Math.abs(totalDr - totalCr) > 0.005;
    return `<div class="vr-doc-section">
        <p class="vr-doc-section-hdr">C. Accounting Entry (Auto-generated)</p>
        <div class="vr-card vr-ap-card"><table class="vr-ap-table">
            <thead><tr><th style="width:150px;">Account Code</th><th>Account Description</th><th style="text-align:right;width:190px;">Debit</th><th style="text-align:right;width:190px;">Credit</th></tr></thead>
            <tbody>
                ${drs.map(d => `<tr><td>${vr_esc(d.code)}</td><td>${vr_esc(d.desc)}</td><td>${vr_fmtNum(d.amt)}</td><td></td></tr>`).join('')}
                ${crs.map(c => `<tr><td>${vr_esc(c.code)}</td><td class="vr-ap-cr">${vr_esc(c.desc)}</td><td></td><td>${vr_fmtNum(c.amt)}</td></tr>`).join('')}
                <tr class="vr-ap-total${bad ? ' vr-ap-bad' : ''}"><td></td><td>Total</td><td>${vr_fmtNum(totalDr)}</td><td>${vr_fmtNum(totalCr)}</td></tr>
            </tbody>
        </table></div>
    </div>`;
}

/* Read-only deduction + ATC rows — the SAME blocks Accounting sees (vje_addDeductionLine() / vje_addAtcLine()) and the entry
   form shows, but view-only: read-only boxes that can't be clicked or focused (see .vje-fg input[readonly] — no caret, no focus
   ring, no pointer), with no × remove mark and no "+ TYPE" pills. */
function vr_dedAtcRowsHtml(dv) {
    const ro = (label, value, extra = '') => `<div class="vje-fg"${extra}><label>${label}</label><input type="text" readonly tabindex="-1" value="${vr_esc(value)}"></div>`;
    // A VAT line is shown as on the entry form and in Accounting's editor: Inclusive/Exclusive, the VAT Computation block
    // (informational) and the Withholding VAT block (the deducted part).
    const vatBlock = d => {
        const gross = Number(dv.gross_amount) || 0;
        const type  = vje_vatTypeOf(gross, d.gross_amount);
        const { vat, base } = vje_vatSplit(gross, type);
        const opt = (value, title, sub) => `<span class="vje-vat-opt${type === value ? ' active' : ''}">${type === value ? '● ' : '○ '}${title} <small>${sub}</small></span>`;
        return `<div class="vr-ro-vat">
            <div class="vr-ded-label"><span class="vr-less">Less:</span> <b>VAT withheld</b><span class="vr-vat-tag">${type === 'inclusive' ? 'VAT Inclusive' : 'VAT Exclusive'}</span></div>
            ${ro('Withholding VAT Rate (%)', d.rate != null ? d.rate : '')}
            ${ro('Withholding VAT Amount (₱)', vr_fmtNum(d.deduction_amount))}
            <div class="vr-vat-info">VAT computation (informational, not deducted): statutory ${VJE_VAT_RATE}% &middot; VAT ${vr_fmtNum(vat)} &middot; Tax base ${vr_fmtNum(base)}</div>
        </div>`;
    };
    // a payroll DV's deductions come from the import, which has amounts only — no Rate box for them
    const ded = (dv.deductions ?? []).map(d => d.code === 'VAT' && !vje_isFlatVat(d) ? vatBlock(d) : `<div class="vje-row vr-ro-ded">
        <div class="vr-ded-label"><span class="vr-less">Less:</span> <b>${vr_esc(d.name) || '—'}</b>${d.type_account_code ? `<span class="vr-acct-hint">${vr_esc(vr_acctLabel(d.type_account_code))}</span>` : ''}</div>
        ${dv.payroll ? ro('Deduction Amount (₱)', vr_peso(d.deduction_amount)) : `<div class="vje-grid2">
            ${ro('Rate (%)', d.rate != null ? d.rate : '')}
            ${ro('Deduction Amount (₱)', vr_peso(d.deduction_amount))}
        </div>`}
        ${!d.type_account_code && d.account_code ? ro('Posting Account', d.account_code, ' style="margin-top:6px;"') : ''}
    </div>`);
    const atc = (dv.atc_lines ?? []).map(a => `<div class="vje-row vr-ro-atc">
        <div class="vr-ded-label">ATC (Withholding Tax)</div>
        <div class="vje-fg"><label>ATC Code — Description</label><input type="text" readonly tabindex="-1" value="${vr_esc([a.atc_code, a.atc_description].filter(Boolean).join(' — '))}"><span class="vr-acct-hint">${vr_esc(vr_acctLabel(a.account_code))}</span></div>
        <div class="vje-grid3">
            ${ro('Rate (%)', a.rate != null ? a.rate : '')}
            ${ro('Tax Base (₱)', vr_peso(a.tax_base))}
            ${ro('Withholding Tax (₱)', vr_peso(a.withholding_tax))}
        </div>
    </div>`);
    const all = ded.concat(atc);
    return all.length ? `<div class="vr-ded-section${atc.length > 1 ? ' vr-atc-multi' : ''}">${all.join('')}</div>` : '';
}

function vr_accountCodeDisplay(dv) {
    if (!dv.account_code) return '—';
    const code = vr_esc(dv.account_code);
    if (dv.account_posting_allowed === false) {
        return `${code} <span class="nha-bdg nha-bdg-red" title="Not postable or no longer exists in the Chart of Accounts">⚠ invalid</span>`;
    }
    return dv.account_title ? `${code} — ${vr_esc(dv.account_title)}` : code;
}
function vr_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function vr_fmtDateTime(s) {
    if (!s) return '— (not yet)';
    const d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function vr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
<?php
// Print → the shared print preview (prp_open + the prp_dvPayload helper).
include_once __DIR__ . '/../readone/print-preview-readone.php';
?>
