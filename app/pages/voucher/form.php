<?php
namespace PHPMaker2024\FIMS;

// Disbursement Voucher ADD / EDIT modal — a partial included by app/pages/voucher.php (not a page of
// its own). Modeled on app/pages/po/form.php. One generic voucher shape for any claimant/payment
// type (vendor payment, payroll, reimbursement, etc.) — the paper General Form No. 1 is itself a
// single layout; what differs is just what gets written in Particulars/Amount. A claimant is
// optionally a registered vendor (fims.vendors via /vendors/all, autofills name/address/TIN) or
// free text typed directly (e.g. a payroll group name).
// "RFA" = fims.budget_allocation WHERE allocation_type = 'RFA' (/voucher/rfa/all, /voucher/rfa/read/{id}).
//
// API:  vf_open()      → new DV
//       vf_open(id)    → edit DV #id (DRAFT / REJECTED only)
//       vf_close()
// Hook: window.vf_onSaved = (data, message) => {...}   called after a successful save.
//
// Fonts and nha-fims.css are loaded by the including page.
if (!isset($_vf_api)) {
    $_vf_api = rtrim((string) Config('API.FIMS_URL'), '/');
    $_vf_api = preg_match('~/api$~', $_vf_api) ? $_vf_api : $_vf_api . '/api';
}
?>

<style>
#vf-root .vf-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
#vf-root .vf-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#vf-root .vf-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
#vf-root .vf-a-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px 16px; align-items: start; }
#vf-root .vf-a-grid > .vf-a-full { grid-column: 1 / -1; }
#vf-root .vf-a-grid .vf-vendor-card { margin-top: 0; }
@media (max-width: 768px) { #vf-root .vf-a-grid { grid-template-columns: 1fr; } }
#vf-root .vf-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
#vf-root .vf-span2 { grid-column: span 2; }
#vf-root .vf-span3 { grid-column: span 3; }
#vf-root .vf-hint  { font-size: 11px; color: var(--afims-text-muted, #62657E); margin-top: 3px; }
#vf-root .vf-err   { display: none; font-size: 11px; color: var(--afims-danger, #DC3545); margin-top: 3px; }

#vf-root .vf-number { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px; margin-bottom: 14px;
    border: 2px solid var(--afims-primary-soft, #E6EBF6); border-radius: 12px;
    background: linear-gradient(to right, var(--afims-primary-soft, #E6EBF6), var(--afims-surface, #fff)); }
#vf-root .vf-number small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-number strong { font-family: "IBM Plex Mono", monospace; font-size: 20px; color: var(--afims-primary-text, #0035A9); }

#vf-root .vf-vendor-card { display: none; margin-top: 10px; padding: 12px 16px; gap: 20px; flex-wrap: wrap;
    background: var(--afims-success-soft, #DBF2E5); border: 1px solid var(--afims-success-soft-hover, #C4E9D4); border-radius: 10px; }
#vf-root .vf-vendor-card.visible { display: flex; }
#vf-root .vf-remit-sched { margin-bottom: 12px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; padding: 10px 12px; font-size: 12.5px; }
#vf-root .vf-rs-hdr { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin-bottom: 6px; }
#vf-root .vf-rs-tbl { width: 100%; border-collapse: collapse; margin: 4px 0 8px; }
#vf-root .vf-rs-tbl th { text-align: left; font-size: 10.5px; font-weight: 700; color: var(--afims-text-muted, #62657E); padding: 4px 6px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#vf-root .vf-rs-tbl td { padding: 4px 6px; border-bottom: 1px dashed var(--afims-border, #E4ECF2); }
#vf-root .vf-rs-tbl .r { text-align: right; font-variant-numeric: tabular-nums; }
#vf-root .vf-rs-tbl .m { font-family: "IBM Plex Mono", monospace; }
#vf-root .vf-rs-tbl tr.rate td { font-weight: 600; background: var(--afims-surface-alt, #F6F8FB); }
#vf-root .vf-rs-tbl tr.ven td { color: var(--afims-text, #1e293b); }
#vf-root .vf-rs-tbl tr.dv td { font-weight: 600; background: var(--afims-surface-alt, #F6F8FB); }
#vf-root .vf-rs-tbl tr.atc td { color: var(--afims-text-muted, #62657E); border-bottom-style: dotted; }
#vf-root .vf-rs-tbl tr.atc td:first-child { padding-left: 18px; }
#vf-root .vf-rs-tbl tr.tot td { font-weight: 700; border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: 0; }
#vf-root .vf-remit-sched summary { cursor: pointer; font-weight: 600; margin: 4px 0; }
/* Remittance DV: section A spans the whole card (no RFA column) with the vendor and claimant side by side; section B is just the amount */
#vf-root #vf-claimant-grid.vf-remit-on { grid-template-columns: 1fr; margin-bottom: 0; }
#vf-root #vf-claimant-grid.vf-remit-on > div:first-child { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 20px; align-items: start; }
#vf-root #vf-claimant-grid.vf-remit-on .nha-fg { margin-top: 0 !important; }
#vf-root .vf-remit-sched.vf-remit-main { border: 0; padding: 0; margin: 0; font-size: 13px; }
#vf-root .vf-remit-total { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; padding: 12px 14px; margin: 4px 0 12px; border-radius: 8px; background: var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-remit-total span { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-remit-total strong { font-family: "IBM Plex Mono", monospace; font-size: 18px; }
#vf-root .vf-remit-sched details { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; padding: 6px 12px; margin-top: 8px; }
@media (max-width: 768px) { #vf-root #vf-claimant-grid.vf-remit-on > div:first-child { grid-template-columns: 1fr; } }
#vf-root .vf-vendor-card label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-success-hover, #0C7539); }
#vf-root .vf-vendor-card span { font-size: 13px; font-weight: 600; color: var(--afims-success-hover, #0C7539); }

#vf-root .vf-ledger { border-collapse: collapse; width: 100%; font-size: 13px; }
#vf-root .vf-ledger td, #vf-root .vf-ledger th { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-ledger td.vf-l-label { color: var(--afims-text-muted, #62657E); font-weight: 600; width: 45%; }
#vf-root .vf-ledger td.vf-l-val { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; }
#vf-root .vf-ledger tr.vf-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; }
#vf-root .vf-ledger input, #vf-root .vf-ledger select { width: 100%; font-size: 12.5px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; font-family: inherit; }

/* GL-grouped Accounting Entries grid (Journal Entry Voucher layout) — same convention as
   readone.php's #vr-root rules. */
#vf-root .vje-grp-edit-table tr.vje-grp-hdr td { padding-top: 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vje-grp-edit-table tr.vje-grp-hdr:first-child td { padding-top: 6px; }
#vf-root .vje-grp-edit-table tr.vje-grp-hdr input.vje-grp-acct { font-weight: 700; background: var(--afims-surface, #fff); }
#vf-root .vje-grp-edit-table tr.vje-grp-hdr .vje-grp-debit,
#vf-root .vje-grp-edit-table tr.vje-grp-hdr .vje-grp-credit { font-weight: 700; font-family: "IBM Plex Mono", monospace; text-align: right; }
#vf-root .vje-grp-edit-table tr.vje-grp-line td:first-child { padding-left: 22px; }
#vf-root .vje-grp-edit-table .vje-grp-add-btn { font-size: 11.5px; padding: 4px 10px; }

#vf-root .vje-sl-hint { margin-top: 4px; font-size: 11px; color: var(--afims-text-muted, #62657E); line-height: 1.6; min-height: 1px; }
/* The picked account's title, inside the Account Code field right after the code ("1-04-01-010 - Due to BIR"). The input keeps only the
   code; this is a click-through label whose left edge vf_renderAcctTitle() sets to the end of the typed text. */
#vf-root .vje-acct-wrap { position: relative; }
#vf-root .vje-acct-title { position: absolute; top: 0; bottom: 0; right: 8px; display: none; align-items: center; pointer-events: none; font-size: 12.5px; font-family: inherit; color: var(--afims-text-muted, #62657E); }
#vf-root .vje-acct-title > span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
/* Deduction / ATC rows are plain blocks inside the RFA Breakdown box — no card border or padding of their own. */
#vf-root .vf-ded-row { margin-bottom: 14px; position: relative; }
/* Deductions sit inside the RFA Breakdown box, under its table — a rule separates them from it once a breakdown is shown. */
/* a payroll DV's deductions (imported): name + amount only — see vf_renderDedTypeButtons() */
#vf-root .vf-ded-payroll .nha-fg:has(> .vf-ded-rate),
#vf-root .vf-ded-payroll .vf-ded-remove,
#vf-root .vf-ded-payroll .vf-ded-types { display: none; }
#vf-root .vf-ded-payroll .vf-ded-row .vf-grid2 { grid-template-columns: 1fr; }
#vf-root #vf-rfa-breakdown-body:not(:empty) + .vf-err + .vf-ded-section { border-top: 1px solid var(--afims-border, #E4ECF2); margin-top: 16px; padding-top: 14px; }
#vf-root .vf-atc-row { margin-bottom: 14px; position: relative; }
/* Tight spacing inside deduction / ATC rows: .nha-fg's own 14px bottom margin is dropped, rows are separated by a small gap instead. */
#vf-root .vf-ded-row, #vf-root .vf-atc-row { margin-bottom: 10px; }
#vf-root .vf-ded-row .nha-fg, #vf-root .vf-atc-row .nha-fg { margin-bottom: 0; }
#vf-root .vf-ded-row .nha-fg label, #vf-root .vf-atc-row .nha-fg label { margin-bottom: 3px; }
#vf-root .vf-ded-row .vf-grid2, #vf-root .vf-atc-row .vf-grid3 { gap: 8px; }
#vf-root .vf-ded-remove { position: absolute; top: 8px; right: 10px; background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 4px 6px; border-radius: 5px; }
#vf-root .vf-ded-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#vf-root .vf-ded-type-label { font-weight: 600; font-size: 13px; color: var(--afims-heading-fill, #000E3E); padding-right: 24px; }
/* VAT deduction row — Inclusive/Exclusive toggle, an informational VAT Computation block and the Withholding VAT block
   (see vf_addVatLine()). Same two-block layout as the Accounting DV Processing page's VAT tab. */
#vf-root .vf-ap-card { padding: 0; overflow: hidden; }
#vf-root .vf-ap-table { width: 100%; border-collapse: collapse; font-size: 14px; }
#vf-root .vf-ap-table th { padding: 12px 16px; text-align: left; font-size: 12.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; color: var(--afims-text-muted, #475069); background: #F7F9FC; border-bottom: 1px solid #E4ECF6; }
#vf-root .vf-ap-table td { padding: 10px 16px; border-bottom: 1px solid #EEF2F8; }
#vf-root .vf-ap-table td:nth-child(1) { font-family: "IBM Plex Mono", monospace; font-size: 13px; letter-spacing: .02em; }
#vf-root .vf-ap-table td:nth-child(3), #vf-root .vf-ap-table td:nth-child(4) { text-align: right; }
#vf-root .vf-ap-table tr.vf-ap-total td { font-weight: 700; border-bottom: 0; }
#vf-root .vf-ap-table tr.vf-ap-empty td { text-align: center; color: var(--afims-text-muted, #62657E); padding: 18px; }
#vf-root .vf-ap-table tr.vf-ap-bad td:nth-child(3), #vf-root .vf-ap-table tr.vf-ap-bad td:nth-child(4) { color: var(--afims-danger-text, #8F222D); }
#vf-root .vf-vat-types { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 8px; }
#vf-root .vf-vat-opt { display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 7px 14px; border-radius: 6px; font-size: 12px; font-weight: 600; border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-muted, #62657E); }
#vf-root .vf-vat-opt.active { border-color: var(--afims-primary, #0035A9); color: var(--afims-primary-text, #0035A9); }
#vf-root .vf-vat-opt small { font-size: 10px; font-weight: 400; }
#vf-root .vf-vat-block { margin-top: 8px; padding: 8px 10px; border-radius: 8px; border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-vat-block-title { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; margin-bottom: 6px; }
#vf-root .vf-vat-block-title span { font-weight: 400; text-transform: none; letter-spacing: 0; }
#vf-root .vf-vat-block.info .vf-vat-block-title { color: var(--afims-primary-text, #0035A9); }
#vf-root .vf-vat-block.real .vf-vat-block-title { color: var(--afims-danger, #DC3545); }
#vf-root .vf-vat-block input[readonly] { background: var(--afims-surface, #FFFFFF); }
/* RFA Breakdown: the editable Amount cell of each line. */
#vf-root .vf-rfa-line-amt {
    width: 100%; padding: 6px 8px; text-align: right; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px;
    font-size: 12.5px; font-family: var(--font); color: var(--afims-text, #1C1E29);
    background: var(--afims-surface, #FFFFFF); box-sizing: border-box; transition: border-color .15s, box-shadow .15s;
}
#vf-root .vf-rfa-line-amt:focus { outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15,32,69,.1); }
/* One line under the ATC rows: how much of the base the ATC rows' Tax Bases add up to (red when they exceed it). */
#vf-root .vf-atc-summary { margin: 4px 0 10px; font-size: 11.5px; line-height: 1.5; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-atc-summary.over { padding: 6px 10px; border-radius: 6px; color: var(--afims-danger-text, #8F222D); background: var(--afims-danger-soft, #FAE1E3); }
/* One pill button per deduction type (Retention, Advance Recoupment, etc.) — clicking a pill adds
   a row already bound to that type, instead of a single "+ Add Deduction" control. */
/* ── Section B as one table-like box: RFA lines, Gross, Computation, "Less:" rows, add-deduction row ── */
#vf-root #vf-rfa-breakdown { padding: 0; overflow: hidden; background: var(--afims-surface, #fff); border-color: #D5E0F0; }
#vf-root .vf-bd-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 14px; }
#vf-root .vf-bd-table th:nth-child(2), #vf-root .vf-bd-table td:nth-child(2) { width: 170px; }
#vf-root .vf-bd-table th:nth-child(3), #vf-root .vf-bd-table td:nth-child(3) { width: 180px; }
#vf-root .vf-bd-table th:nth-child(4), #vf-root .vf-bd-table td:nth-child(4) { width: 170px; }
#vf-root .vf-bd-table th:nth-child(5), #vf-root .vf-bd-table td:nth-child(5) { width: 44px; }
#vf-root .vf-bd-table th { padding: 12px 14px; background: #EEF3FB; color: #0B1B43; font-size: 12.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .02em; }
#vf-root .vf-bd-table td { padding: 10px 14px; border-top: 1px solid #E4ECF6; vertical-align: middle; }
#vf-root .vf-bd-table tbody td:nth-child(2) { color: var(--afims-text-muted, #62657E); }
#vf-root .vf-bd-table td:nth-child(3), #vf-root .vf-bd-table td:nth-child(4) { text-align: right; }
#vf-root .vf-bd-table .vf-rfa-line-amt, #vf-root .vf-bd-table .vf-comp-base, #vf-root .vf-bd-table .vf-comp-pct, #vf-root .vf-comp-line input {
    width: 100%; padding: 8px 12px; text-align: right; border: 1px solid var(--afims-border, #D7DCE6); border-radius: 8px; font-size: 14px;
    font-family: var(--font); background: var(--afims-surface, #fff); box-sizing: border-box;
}
#vf-root .vf-bd-gross td { background: #E3EBF8; border-top: 1px solid #D5E0F0; padding: 8px 14px; }
#vf-root .vf-bd-gross .vf-bd-muted { color: var(--afims-text-muted, #62657E); font-size: 13px; }
#vf-root .vf-bd-comp small { display: block; margin-top: 6px; font-size: 12px; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-comp-line { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-comp-line span { white-space: nowrap; }
#vf-root .vf-comp-line input { text-align: left; }
/* deduction rows line up under the table's columns: label | rate (under RFA Balance / %) | amount | remove */
#vf-root #vf-rfa-breakdown .vf-ded-section { border-top: 1px solid #E4ECF6 !important; margin: 0 !important; padding: 0 !important; }
#vf-root #vf-rfa-breakdown #vf-rfa-breakdown-body:empty + .vf-err + .vf-ded-section { border-top: 0 !important; }
#vf-root .vf-less { font-weight: 400; margin-right: 4px; }
#vf-root .vf-ded-acct-hint { font-size: 12px; font-weight: 400; color: var(--afims-text-muted, #62657E); margin-left: 6px; }
#vf-root .vf-ded-row, #vf-root .vf-atc-row { margin: 0; padding: 10px 14px; border-top: 1px solid #E4ECF6; }
#vf-root .vf-ded-section #vf-ded-container > :first-child { border-top: 0; }
#vf-root .vf-ded-row:not([data-vat="1"]) { display: grid; grid-template-columns: minmax(0, 1fr) 180px 170px 44px; align-items: center; }
#vf-root .vf-ded-row:not([data-vat="1"]) .vf-ded-type-label { grid-column: 1; grid-row: 1; padding: 0; font-weight: 400; font-size: 14px; color: var(--afims-text, #1C1E29); }
#vf-root .vf-ded-row:not([data-vat="1"]) > .vf-grid2 { display: contents; }
#vf-root .vf-ded-row:not([data-vat="1"]) > .vf-grid2 > .nha-fg { margin: 0; }
#vf-root .vf-ded-row:not([data-vat="1"]) > .vf-grid2 > .nha-fg:nth-child(1) { grid-column: 2; grid-row: 1; padding: 0 14px 0 0; }
#vf-root .vf-ded-row:not([data-vat="1"]) > .vf-grid2 > .nha-fg:nth-child(2) { grid-column: 3; grid-row: 1; }
#vf-root .vf-ded-row:not([data-vat="1"]) > .vf-grid2 > .nha-fg label { display: none; }
#vf-root .vf-ded-row:not([data-vat="1"]) > .vf-grid2 input { width: 100%; padding: 8px 12px; text-align: right; border: 1px solid var(--afims-border, #D7DCE6); border-radius: 8px; font-size: 14px; box-sizing: border-box; background: var(--afims-surface, #fff); }
#vf-root .vf-ded-row:not([data-vat="1"]) > .nha-fg { grid-column: 1 / -1; grid-row: 2; margin-top: 8px !important; }
#vf-root .vf-ded-row:not([data-vat="1"]) > .vf-ded-remove { position: static; grid-column: 4; grid-row: 1; justify-self: center; }
/* VAT row: same columns as the other deductions; the informational VAT computation sits on a muted line beneath */
#vf-root .vf-ded-row[data-vat="1"] { display: grid; grid-template-columns: minmax(0, 1fr) 180px 170px 44px; align-items: center; }
#vf-root .vf-ded-row[data-vat="1"] .vf-ded-type-label { grid-column: 1; grid-row: 1; padding: 0; font-weight: 400; font-size: 14px; color: var(--afims-text, #1C1E29); }
#vf-root .vf-ded-row[data-vat="1"] .vf-vat-types { display: inline-flex; margin: 0 0 0 10px; gap: 6px; vertical-align: middle; }
#vf-root .vf-ded-row[data-vat="1"] .vf-vat-opt { padding: 4px 10px; font-size: 12px; }
#vf-root .vf-ded-row[data-vat="1"] .vf-vat-rate { grid-column: 2; grid-row: 1; margin: 0; padding-right: 14px; }
#vf-root .vf-ded-row[data-vat="1"] .vf-vat-wamt { grid-column: 3; grid-row: 1; margin: 0; }
#vf-root .vf-ded-row[data-vat="1"] .vf-vat-rate input, #vf-root .vf-ded-row[data-vat="1"] .vf-vat-wamt input {
    width: 100%; padding: 8px 12px; text-align: right; border: 1px solid var(--afims-border, #D7DCE6); border-radius: 8px; font-size: 14px; box-sizing: border-box; background: var(--afims-surface, #fff);
}
#vf-root .vf-ded-row[data-vat="1"] > .vf-ded-remove { position: static; grid-column: 4; grid-row: 1; justify-self: center; }
#vf-root .vf-vat-info { grid-column: 1 / -1; grid-row: 2; margin-top: 6px; font-size: 12px; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-vat-info input { width: 110px; padding: 2px 6px; border: 0; border-bottom: 1px dashed var(--afims-border, #B9C4D0); background: transparent; font-size: 12px; text-align: right; color: inherit; }
#vf-root .vf-ded-row[data-vat="1"] .vf-vat-hint { grid-column: 1 / -1; grid-row: 3; margin-top: 2px; }
/* ATC row: Less: [ATC code] | Rate % | Withholding tax | x — the editable Tax Base sits on a muted line beneath */
#vf-root .vf-atc-row { display: grid; grid-template-columns: minmax(0, 1fr) 180px 170px 44px; align-items: center; }
#vf-root .vf-atc-label { grid-column: 1; grid-row: 1; display: flex; align-items: center; gap: 6px; padding: 0 14px 0 0; font-weight: 400; font-size: 14px; color: var(--afims-text, #1C1E29); }
#vf-root .vf-atc-codewrap { position: relative; flex: 1; min-width: 0; }
#vf-root .vf-atc-rate-cell { grid-column: 2; grid-row: 1; padding-right: 14px; }
#vf-root .vf-atc-wh-cell { grid-column: 3; grid-row: 1; }
#vf-root .vf-atc-row > .vf-ded-remove { position: static; grid-column: 4; grid-row: 1; justify-self: center; }
#vf-root .vf-atc-row .vf-atc-code, #vf-root .vf-atc-row .vf-atc-rate, #vf-root .vf-atc-row .vf-atc-wh {
    width: 100%; padding: 8px 12px; border: 1px solid var(--afims-border, #D7DCE6); border-radius: 8px; font-size: 14px; box-sizing: border-box; background: var(--afims-surface, #fff);
}
#vf-root .vf-atc-row .vf-atc-rate, #vf-root .vf-atc-row .vf-atc-wh { text-align: right; }
#vf-root .vf-atc-info { grid-column: 1 / -1; grid-row: 2; margin-top: 6px; font-size: 12px; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-atc-info input { width: 130px; padding: 2px 6px; border: 0; border-bottom: 1px dashed var(--afims-border, #B9C4D0); background: transparent; font-size: 12px; text-align: right; color: var(--afims-text, #1C1E29); }
/* Amount column: the box text sits 12px inside its box, so the header, Gross and Net values are inset the same 12px to line up with it */
#vf-root .vf-bd-table th:nth-child(4), #vf-root .vf-bd-gross td:nth-child(4) { padding-right: 26px; }
#vf-root .vf-net-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 70px 12px 14px; background: #E3EBF8; border-top: 1px solid #D5E0F0; font-size: 14px; color: #0B1B43; }
#vf-root .vf-net-row b { font-family: "IBM Plex Mono", monospace; font-size: 15px; }
#vf-root .vf-atc-acct-hint, #vf-root .vf-ded-acct-hint { font-family: "IBM Plex Mono", monospace; font-size: 12px; font-weight: 400; color: var(--afims-text-muted, #62657E); white-space: nowrap; }
#vf-root .vf-atc-label .vf-atc-codewrap { flex: 0 1 430px; }
/* the Tax Base line only matters when the base is split over several ATC rows */
#vf-root .vf-atc-info { display: none; }
#vf-root #vf-ded-container.vf-atc-multi .vf-atc-info { display: block; }
#vf-root .vf-ded-types { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-top: 1px solid #E4ECF6; }
#vf-root .vf-ded-less { font-size: 13px; color: var(--afims-text, #1C1E29); }
#vf-root .vf-ded-add-select {
    width: auto; min-width: 190px; padding: 7px 12px; border: 1px dashed var(--afims-border, #B9C4D0); border-radius: 8px;
    background: transparent; color: var(--afims-text-muted, #62657E); font-size: 13px; font-family: var(--font); cursor: pointer;
}
#vf-root .vf-ded-add-select:hover, #vf-root .vf-ded-add-select:focus { border-color: var(--afims-heading-fill, #000E3E); outline: none; }
#vf-root .vf-ded-type-btn {
    display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px;
    border: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface, #FFFFFF);
    color: var(--afims-heading-fill, #000E3E); font-size: 12px; font-weight: 600; letter-spacing: .02em;
    cursor: pointer; transition: background .15s, border-color .15s;
}
#vf-root .vf-ded-type-btn:hover { background: var(--afims-surface-subtle, #F4F7FA); border-color: var(--afims-heading-fill, #000E3E); }
/* Table-cell remove buttons (ATC / Accounting Entries rows) — same look as .vf-ded-remove but
   WITHOUT position:absolute, which inside a <td> (no positioned ancestor of its own) escaped to
   the nearest positioned ancestor up the DOM tree — the modal itself — landing as a stray red ×
   floating near the modal's close button instead of inside its own row. */
#vf-root .vf-table-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 4px 6px; border-radius: 5px; }
#vf-root .vf-table-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#vf-root .vf-atc-rc {
    width: 100%; padding: 8px 12px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px;
    font-size: 13px; font-family: var(--font); color: var(--afims-text, #1C1E29);
    background: var(--afims-surface, #FFFFFF); box-sizing: border-box; transition: border-color .15s, box-shadow .15s;
}
#vf-root .vf-atc-rc:focus {
    outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15,32,69,.1);
}

#vf-root .vf-errors { display: none; margin-bottom: 14px; padding: 12px 16px; background: var(--afims-danger-soft, #FAE1E3);
    border: 1px solid var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 10px; }
#vf-root .vf-errors.visible { display: block; }
#vf-root .vf-errors ul { margin: 6px 0 0; padding-left: 18px; font-size: 13px; color: var(--afims-danger-text, #8F222D); line-height: 1.7; }

#vf-root .vf-rfa-dropdown { position: relative; display: none; margin-top: 4px; max-height: 260px; overflow-y: auto;
    border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; background: var(--afims-surface, #fff); box-shadow: 0 6px 16px rgba(0,0,0,.08); }
#vf-root .vf-rfa-dropdown.open { display: block; }
#vf-root .vf-rfa-opt { padding: 8px 12px; cursor: pointer; font-size: 12.5px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-rfa-opt:hover { background: var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-rfa-opt small { display: block; color: var(--afims-text-muted, #62657E); margin-top: 2px; }
#vf-root .vf-rfa-opt.vf-rfa-opt-empty { cursor: default; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-rfa-chips { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
#vf-root .vf-rfa-chip { border: 1px solid var(--afims-primary-soft, #E6EBF6); border-radius: 10px; padding: 10px 12px; background: var(--afims-primary-soft, #E6EBF6); }
#vf-root .vf-rfa-chip-hdr { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
#vf-root .vf-rfa-chip-hdr strong { font-size: 13px; color: var(--afims-primary-text, #0035A9); }
#vf-root .vf-rfa-chip-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 2px 6px; border-radius: 5px; }
#vf-root .vf-rfa-chip-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#vf-root .vf-rfa-chip-dl { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 16px; margin: 8px 0 0; }
#vf-root .vf-rfa-chip-dl dt { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); margin: 0; }
#vf-root .vf-rfa-chip-dl dd { font-size: 12.5px; font-weight: 600; color: var(--afims-text, #1e293b); margin: 1px 0 0; }

#vf-root .vf-dz { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; border: 1px dashed var(--afims-border, #E4ECF2); border-radius: 10px; padding: 10px 14px; font-size: 14px; }
#vf-root .vf-dz.drag-over { border-color: var(--afims-primary, #0035A9); background: var(--afims-primary-soft, #E6EBF6); }
#vf-root .vf-dz-hint { font-size: 11px; color: var(--afims-text-muted, #62657E); margin-left: auto; }
#vf-root .vf-att-link-row { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
#vf-root .vf-att-link-row input { flex: 1; font-size: 13px; padding: 8px 10px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; }
#vf-root .vf-att-link-row input#vf-att-link-name { flex: 0 0 200px; }
#vf-root .vf-att-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
#vf-root .vf-att-name a { color: var(--afims-primary-text, #0035A9); text-decoration: none; }
#vf-root .vf-att-name a:hover { text-decoration: underline; }
#vf-root .vf-att-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; }
#vf-root .vf-att-name { flex: 1; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#vf-root .vf-att-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 2px 6px; border-radius: 5px; }
#vf-root .vf-att-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }

/* Dark mode — the Budget / Obligation inputs, the attachment link inputs and the Accounting Entries lines / SL Code selects
   have no background of their own, so the browser painted its native grey (#3B3B3B) instead of the theme surface.
   Give every field of the form the same dark look as the .nha-fg ones. */
html.afims-dark[data-bs-theme="dark"] #vf-root { color-scheme: dark; }
html.afims-dark[data-bs-theme="dark"] #vf-root :is(input:not([type="checkbox"]):not([type="radio"]):not([type="file"]), select, textarea) {
    background-color: var(--afims-surface, #1A1F38); color: var(--afims-text, #E4ECF2); border-color: var(--afims-border-strong, #4A5270);
}
html.afims-dark[data-bs-theme="dark"] #vf-root select option { background-color: var(--afims-surface, #1A1F38); color: var(--afims-text, #E4ECF2); }
html.afims-dark[data-bs-theme="dark"] #vf-root :is(input, textarea)::placeholder { color: #9AA3B5; opacity: 1; }
html.afims-dark[data-bs-theme="dark"] #vf-root :is(input, select, textarea):focus {
    outline: none; border-color: var(--afims-primary, #3861BC); box-shadow: 0 0 0 3px color-mix(in srgb, var(--afims-primary, #3861BC) 25%, transparent);
}
html.afims-dark[data-bs-theme="dark"] #vf-root :is(input, select, textarea):is(:disabled, [readonly]) {
    background-color: var(--afims-surface-subtle, #22294A); color: var(--afims-text-muted, #AAAFBE);
}

/* "Document" layout — lettered sections (A/B/C/D) styled like a printed form: a bold navy
   heading per section, each field group that's just a handful of label/value pairs laid out as a
   thin-bordered 2-column table (same visual language as a Pro Forma mockup), rather than the
   free-form card grid used elsewhere in the app. Purely a presentation layer over the existing
   #vf-root markup/ids — no field, id, or JS binding moved or renamed. */
#vf-root .vf-doc-section { margin-bottom: 22px; }
#vf-root .vf-doc-section-hdr {
    font-size: 14px; font-weight: 700; color: #fff; background: #0B1B43;
    margin: 0 0 12px; padding: 10px 14px; border-radius: 8px;
    text-transform: uppercase; letter-spacing: .02em;
}
#vf-root .vf-doc-table { width: 100%; border-collapse: collapse; border: 1px solid var(--afims-border, #D7DCE6); margin-bottom: 14px; }
#vf-root .vf-doc-table td {
    border: 1px solid var(--afims-border, #D7DCE6); padding: 10px 14px; vertical-align: top; width: 50%;
}
#vf-root .vf-doc-table td.vf-doc-full { width: 100%; }
#vf-root .vf-doc-table .vf-doc-k { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); margin: 0 0 6px; }
#vf-root .vf-doc-table .nha-fg { margin: 0; }
#vf-root .vf-doc-table .nha-fg label { display: none; } /* the table cell's own .vf-doc-k already labels it */
#vf-root .vf-doc-summary-table { width: 100%; border-collapse: collapse; border: 1px solid var(--afims-border, #D7DCE6); margin-top: 14px; }
#vf-root .vf-doc-summary-table td { border: 1px solid var(--afims-border, #D7DCE6); padding: 10px 14px; font-size: 13px; }
#vf-root .vf-doc-summary-table td:first-child { font-weight: 600; color: var(--afims-text, #1C1E29); }
#vf-root .vf-doc-summary-table td:last-child { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; width: 220px; }
#vf-root .vf-doc-summary-table tr.vf-doc-summary-final td { font-weight: 700; background: #E8EEF9; color: #0B1B43; text-transform: uppercase; font-size: 12.5px; }
#vf-root .vf-doc-summary-table tr.vf-doc-summary-final td:last-child { text-transform: none; font-size: 14px; }

@media (max-width: 768px) {
    #vf-root .vf-doc-table { display: block; }
    #vf-root .vf-doc-table tr { display: flex; flex-direction: column; }
    #vf-root .vf-doc-table td { width: 100% !important; }
    #vf-root .vf-grid3, #vf-root .vf-grid2 { grid-template-columns: 1fr; }
    #vf-root .vf-span2, #vf-root .vf-span3 { grid-column: span 1; }
    #vf-root .vf-rfa-chip-dl { grid-template-columns: repeat(2, 1fr); }
    #vf-root .vf-att-link-row { flex-wrap: wrap; }
    #vf-root .vf-att-link-row input#vf-att-link-name { flex: 1 1 100%; }
}
</style>

<div id="vf-root" class="nha-root">
    <div id="vf-overlay" class="nha-overlay no-print">
        <div class="nha-modal u-modal-xl" role="dialog" aria-modal="true" aria-labelledby="vf-modal-title">

            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="vf-modal-title">New Disbursement Voucher</h3>
                <button type="button" class="nha-modal-close" onclick="vf_close()" aria-label="Close">&times;</button>
            </div>

            <div class="nha-modal-body">

                <div class="vf-number">
                    <div><small>DV Number</small><strong id="vf-dv-number">—</strong></div>
                    <div style="text-align:right;"><small>Status</small><span class="nha-bdg nha-bdg-gray" id="vf-status">DRAFT</span></div>
                </div>

                <div class="vf-errors" id="vf-errors">
                    <strong style="font-size:13px;color:var(--afims-danger,#DC3545);">Please fix the following before saving:</strong>
                    <ul id="vf-errors-ul"></ul>
                </div>

                <!-- ══ A. VOUCHER INFORMATION — one flat grid: DV Date, Claimant, Address, TIN, Vendor, PO / Contract (typed),
                     RFA (multi-select), SARO, Particulars. Picking a vendor auto-fills the claimant fields; picking one or more
                     RFAs auto-fills department and updates Budget Reference (section B). ══ -->
                <div class="vf-doc-section">
                    <p class="vf-doc-section-hdr">A. Voucher Information</p>

                    <div class="vf-card">
                        <p class="vf-card-title" id="vf-claimant-title" style="display:none;">Claimant &amp; RFA</p>
                        <div id="vf-claimant-grid" class="vf-a-grid">

                            <!-- DV Date drives the DV Number preview; Fiscal Year is derived from it (vf_derivedFiscalYear()). -->
                            <div class="nha-fg" id="vf-doc-date-td">
                                <label>DV date <span class="nha-req">*</span></label>
                                <input type="date" id="vf-dv-date">
                                <span class="vf-err" id="err-dv-date">DV Date is required.</span>
                            </div>
                            <div class="nha-fg">
                                <label>Payee Name <span class="nha-req">*</span></label>
                                <input type="text" id="vf-claimant" placeholder="e.g. Jackson Auto Services, or Abelar, Reena E. et al.">
                                <span class="vf-err" id="err-claimant">Payee Name is required.</span>
                            </div>
                            <div class="nha-fg">
                                <label>Address</label>
                                <input type="text" id="vf-claimant-address" placeholder="Auto-filled from the list of vendors/contractors">
                            </div>

                            <div class="nha-fg">
                                <label>TIN / Employee No.</label>
                                <input type="text" id="vf-claimant-tin" placeholder="Auto-filled from the list of vendors/contractors">
                            </div>
                            <div class="nha-fg">
                                <label>Vendor (Supplier / Contractor) or Payroll <span class="vf-hint" style="display:inline;">(optional)</span></label>
                                <select id="vf-vendor"><option value="">— None / type claimant manually —</option></select>
                            </div>
                            <div class="nha-fg">
                                <label>PO / Contract <span class="vf-hint" style="display:inline;">(optional · type the PO or contract number)</span></label>
                                <input type="text" id="vf-po-number" placeholder="e.g. GSD-2025-09-103">
                                <!-- shown when the PO has no IAR (Inspection and Acceptance Report) — checked via GET /voucher/po-iar -->
                                <span id="vf-po-warn" role="alert" style="display:none;margin-top:4px;font-size:12px;color:var(--afims-danger-text,#8F222D);"></span>
                            </div>

                            <div class="vf-vendor-card vf-a-full" id="vf-vendor-card" style="display:none !important;"><!-- hidden: repeats Payee / Address / TIN above; kept because the vendor JS still fills it -->
                                <div><label>Vendor Name</label><span id="vf-vc-name">—</span></div>
                                <div><label>Type</label><span id="vf-vc-type">—</span></div>
                                <div><label>Address</label><span id="vf-vc-address">—</span></div>
                                <div><label>TIN</label><span id="vf-vc-tin">—</span></div>
                            </div>
                            <div id="vf-remit-vendor-box" class="vf-a-full" style="display:none;"></div>

                            <!-- RFA multi-select + SARO -->
                            <div class="vf-a-full" id="vf-rfa-col">
                                <div class="nha-fg">
                                    <label>RFA <span class="nha-req" id="vf-rfa-req">*</span> <span class="vf-hint" style="display:inline;" id="vf-rfa-note">(search and select one or more)</span></label>
                                    <input type="text" id="vf-rfa-search" placeholder="Search RFA number or reference no…" autocomplete="off">
                                    <div id="vf-rfa-dropdown" class="vf-rfa-dropdown"></div>
                                    <span class="vf-hint" id="vf-rfa-empty-hint" style="display:none;color:var(--afims-warning-text,#8A6400);">No RFAs are available yet — an RFA (Request for Allotment) must exist before a Disbursement Voucher can be created. Contact Budget/FMD to set one up.</span>
                                    <span class="vf-err" id="err-rfa">At least one RFA is required.</span>
                                </div>
                                <div id="vf-rfa-chips" class="vf-rfa-chips"></div>

                                <!-- SARO (fims.fund_tracking WHERE tracking_type = SARO) — one per DV, unlike the RFA
                                     multi-select above (fund_tracking.dv_id, see DisbursementVoucherService::linkSaro()).
                                     Optional. Its linked bank account auto-fills Check Preparation's Bank Account select once
                                     this DV reaches FOR_TREASURY — see CheckProcessingService::getSaroForDv(). -->
                                <div class="nha-fg" style="margin-top:12px;">
                                    <label>SARO <span class="vf-hint" style="display:inline;">(optional — the SARO this voucher's bank account comes from)</span></label>
                                    <select id="vf-saro"><option value="">— None —</option></select>
                                </div>
                            </div>

                            <div class="nha-fg" id="vf-doc-status-td">
                                <label>Status</label>
                                <div><span class="nha-bdg nha-bdg-gray" id="vf-status-doc">DRAFT</span></div>
                            </div>

                            <!-- Particulars: General Form No. 1's own "what this DV is for" box. -->
                            <div class="nha-fg vf-a-full">
                                <label>Particulars <span class="nha-req">*</span></label>
                                <textarea id="vf-particulars" rows="3" placeholder="To payment for…"></textarea>
                                <span class="vf-err" id="err-particulars">Particulars is required.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ══ B. AMOUNT AND DEDUCTIONS — Gross Amount, non-tax Deductions, ATC
                     (withholding tax), Budget Reference (the paper form's Accounting Entries
                     box/Box B — Dept./Project/Fund Source/Budget/Obligation, filled in by the
                     RFA(s) above), and the Gross/Less Deductions/Net Amount Payable summary. ══ -->
                <div class="vf-doc-section">
                    <p class="vf-doc-section-hdr" id="vf-sec-b-hdr">B. Amount and Deductions</p>
                    <p class="vf-hint" id="vf-sec-b-note" style="margin:0 0 10px;font-size:13px;">Account codes and amounts are filled in from the RFAs selected above, starting at each RFA balance. Change the amount to what this DV charges.</p>

                    <!-- A Remittance DV's amount: the total and the rate table, with the accounting entry and the vouchers it pays folded away
                         underneath (vf_renderRemitSchedule()). Hidden for every other DV. -->
                    <div class="vf-remit-sched" id="vf-remit-sched" style="display:none;"></div>

                    <!-- No standalone Amount input any more: the read-only Amount column of the RFA Breakdown below
                         shows how Gross Amount (the selected RFA(s)' remaining balance) is spread over their lines. #vf-gross stays as a hidden input — the rest of the form
                         (deductions, ATC, net, validation, save payload) reads it, and payroll/remittance DVs set it. -->
                    <input type="hidden" id="vf-gross">

                    <!-- One box: the RFA Breakdown (filled by vf_renderRfaBreakdown() from the selected RFA(s); empty until
                         one is picked) with the Deductions below it. Deductions ("Less:" on the paper form — non-tax
                         amounts withheld from gross, e.g. contractor retention) and ATC (withholding tax) rows share one
                         list: clicking a "+ TYPE" / "+ ATC ROW" pill adds a new card below the previous ones. Each ATC
                         row is its own card (not one combined table). Accounting can add/change either later regardless
                         of what Originating pre-fills here. Same ATC fields/behavior as readone.php's ATC card. -->
                    <div class="vf-card" id="vf-rfa-breakdown">
                        <div id="vf-rfa-breakdown-body"></div>
                        <span class="vf-err" id="err-gross">Amount must be greater than zero.</span>

                        <div class="vf-ded-section" id="vf-ded-section">
                            <div id="vf-ded-container"></div>
                            <div class="vf-atc-summary" id="vf-atc-summary" style="display:none;"></div>
                            <div class="vf-ded-types" id="vf-ded-types"></div>
                        </div>
                        <div class="vf-net-row"><span>Net amount</span><b id="vf-net-vis">&#8369; 0.00</b></div>
                    </div>

                    <!-- Gross Amount / Less: Total Deductions / NET AMOUNT PAYABLE — vf-doc-gross
                         and vf-doc-less-ded are set alongside vf-net in vf_recalc(). -->
                    <table class="vf-doc-summary-table" id="vf-doc-summary" style="display:none;">
                        <tr><td>Gross Amount</td><td id="vf-doc-gross">₱ 0.00</td></tr>
                        <tr><td>Less: Total Deductions</td><td id="vf-doc-less-ded">₱ 0.00</td></tr>
                        <tr><td>Less: Withholding Tax (ATC)</td><td id="vf-doc-less-wh">₱ 0.00</td></tr>
                        <tr class="vf-doc-summary-final"><td>Net Amount Payable</td><td id="vf-net">₱ 0.00</td></tr>
                    </table>

                    <!-- The "Budget Reference" card is no longer shown. Its inputs stay in the DOM, hidden, because
                         the form's JS and the save payload still read them (Dept/Project and Budget/Obligation
                         autofill from the selected RFA(s); the ledger rows are recomputed by vf_recalc()).
                         Account Code is required by the server but is no longer shown: it autofills from the selected RFA(s) / vendor.
                         Remarks is hidden too (kept so a saved DV's remarks aren't wiped on edit). -->
                    <div id="vf-budget-ref-hidden" style="display:none;">
                        <input type="text" id="vf-account-code">
                        <span class="vf-err" id="err-account-code">Account Code is required.</span>
                        <textarea id="vf-remarks"></textarea>
                        <input type="text" id="vf-dept-project">
                        <input type="text" id="vf-fund-source">
                        <input type="text" inputmode="decimal" id="vf-budget">
                        <input type="text" inputmode="decimal" id="vf-obligation">
                        <span id="vf-payments-hint"></span>
                        <span id="vf-payments"></span>
                        <span id="vf-this-request"></span>
                        <span id="vf-total-payments"></span>
                        <span id="vf-balance"></span>
                    </div>
                </div>

                <!-- ══ C. ACCOUNTING ENTRY (AUTO-GENERATED) — read-only preview built live from section B (see vf_renderAcctPreview()):
                     Dr each RFA account line, Cr each deduction's / ATC's payable account, Cr Accounts Payable for the net.
                     Display only — nothing here is sent on save; Accounting still prepares the real entry at FOR_ACCOUNTING. ══ -->
                <div class="vf-doc-section" id="vf-sec-acct-prev">
                    <p class="vf-doc-section-hdr">C. Accounting Entry (Auto-generated)</p>
                    <div class="vf-card vf-ap-card">
                        <table class="vf-ap-table">
                            <thead><tr>
                                <th style="width:150px;">Account Code</th>
                                <th>Account Description</th>
                                <th style="text-align:right;width:190px;">Debit</th>
                                <th style="text-align:right;width:190px;">Credit</th>
                            </tr></thead>
                            <tbody id="vf-acct-prev-body"></tbody>
                        </table>
                    </div>
                </div>

                <!-- ══ D. SUPPORTING DOCUMENTS — same fims.file_attachments / multiple_node_id
                     convention the CBA/RFA form uses (see FileUploadServices). Files upload to
                     storage as soon as they're dropped; the batch itself (multiple_node_id) is
                     only registered when the voucher is saved (vf_save -> vf_persistAttachments). ══ -->
                <div class="vf-doc-section">
                    <p class="vf-doc-section-hdr">D. Supporting Documents</p>
                    <div class="vf-card">
                        <div class="vf-dz" id="vf-dz"
                             ondragover="event.preventDefault(); this.classList.add('drag-over');"
                             ondragleave="this.classList.remove('drag-over');"
                             ondrop="vf_onAttDrop(event)">
                            <button type="button" class="nha-btn nha-btn-ghost" onclick="document.getElementById('vf-att-input').click()">+ Attach files</button>
                            <span id="vf-att-status">No files attached</span>
                            <span class="vf-dz-hint">Max 25MB each &middot; PDF, DOC, XLS, JPG, PNG</span>
                        </div>
                        <input type="file" id="vf-att-input" multiple hidden
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" onchange="vf_onAttChange(event)">
                        <div class="vf-att-link-row">
                            <input type="text" id="vf-att-link-name" placeholder="Document name">
                            <input type="text" id="vf-att-link-url" placeholder="e.g. https://drive.google.com/…">
                            <button type="button" class="nha-btn nha-btn-ghost" onclick="vf_addLinkAttachment()">+ Add Link</button>
                        </div>
                        <div class="vf-att-list" id="vf-att-list"></div>
                    </div>
                </div>

                <!-- ══ E. PRO FORMA JOURNAL ENTRY — HIDDEN from Originating (regional users). Accounting
                     fills it in at the FOR_ACCOUNTING stage (readone.php's editable grid, see
                     vje_visible()). Kept in the DOM, not removed, because the payroll/remittance
                     flows still pre-fill this grid silently (vf_applyPayroll()/vf_applyRemittance())
                     and vf_save() sends whatever it holds; regular DVs no longer auto-fill it, see
                     vf_syncAutoJevLines(). ══ -->
                <div class="vf-doc-section" id="vf-sec-d" style="display:none;margin-bottom:0;">
                    <p class="vf-doc-section-hdr">E. Pro Forma Journal Entry</p>
                    <div class="vf-card" style="margin-bottom:0;">
                        <div class="nha-fg" style="max-width:260px;margin-bottom:10px;">
                            <label>Book <span id="vf-book-req" class="nha-req" style="display:none;">*</span></label>
                            <select id="vf-book"><option value="">Select a book…</option></select>
                            <span class="vf-err" id="err-book">Book is required once Accounting Entries lines are filled in.</span>
                        </div>
                        <p class="vf-hint" style="margin:0 0 8px;">Add one line per journal entry.</p>
                        <div style="overflow-x:auto;">
                            <table class="vf-ledger vje-edit-table" style="min-width:900px;">
                                <thead><tr>
                                    <th style="text-align:left;min-width:220px;">Account Code</th>
                                    <th style="text-align:left;min-width:200px;">Particular</th>
                                    <th style="text-align:right;width:110px;">Debit (₱)</th>
                                    <th style="text-align:right;width:110px;">Credit (₱)</th>
                                    <th style="width:30px;"></th>
                                </tr></thead>
                                <tbody id="vf-jev-tbody"></tbody>
                                <tfoot><tr class="vf-l-total">
                                    <td colspan="2" style="text-align:right;">Total</td>
                                    <td class="vf-l-val" id="vf-jev-total-debit">₱ 0.00</td>
                                    <td class="vf-l-val" id="vf-jev-total-credit">₱ 0.00</td>
                                    <td></td>
                                </tr></tfoot>
                            </table>
                        </div>
                        <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vf_addJevLine()">+ Add Line</button>
                        <div style="margin-top:12px;">
                            <span id="vf-jev-balance-msg" style="font-weight:600;font-size:13px;"></span>
                        </div>
                    </div>
                </div>

            </div><!-- /.nha-modal-body -->

            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="vf_close()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-outline" id="vf-btn-draft"  onclick="vf_save('DRAFT')">Save as Draft</button>
                <button type="button" class="nha-btn nha-btn-primary" id="vf-btn-submit" onclick="vf_save('FOR_FMD_REVIEW')">Save &amp; Submit to FMD</button>
            </div>

        </div>
    </div>
</div><!-- /#vf-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   DISBURSEMENT VOUCHER FORM MODAL   prefix: vf_
═══════════════════════════════════════════════════════════════ */

const VF_API      = <?= json_encode($_vf_api) ?>;
const VF_EDITABLE = ['DRAFT', 'REJECTED'];

const $vf = id => document.getElementById(id);
let vf_id      = 0;         // 0 = add mode, otherwise the dv_id being edited
let vf_saving  = false;
let vf_vendors = {};         // vendor_id -> row, for the summary card
let vf_rfas    = {};         // allocation_id -> row
let vf_refReady = null;      // promise: fiscal years, loaded once on first open
let vf_booksReady = null;    // promise: books of accounts, loaded once on first open
let vf_dedTypesReady = null;  // promise: deduction types, loaded once on first open
let vf_coaReady = null;       // promise: postable chart-of-accounts codes, loaded once on first open
let vf_atcReady = null;       // promise: active ATC codes, loaded once on first open
let vf_rcReady  = null;       // promise: responsibility centers (ATC Chargeability), loaded once on first open
let vf_slReady  = null;       // promise: SL masters (Accounting Entries SL Code picker), loaded once on first open
let vf_bound   = false;
let vf_paymentsPrior = 0;    // sum of net_amount other active vouchers already drew against the selected RFA(s)
let vf_selectedRfaIds = [];  // allocation_id values picked in the RFA multi-select, in selection order
let vf_payrollPeriodId = null; // imported payroll month (payroll_period_id) this DV pays — picked in the Vendor dropdown's Payroll group — or null
let vf_remittanceId = null;    // payroll remittance this DV pays — the month's agencies (GSIS / BIR / …), one DV per month — Voucher?remittance={id} — or null (no RFA needed)
let vf_dvRemitVendors = [];   // the vendors the month's open withholding came from (new Remittance DV only) — the select under the claimant
let vf_dvRemit = null;       // {period, type}: a month's EWT / Withholding VAT / Penalty Tax this DV pays to BIR — Remittance List "Remit" → Voucher?remit_period=YYYY-MM&remit_type={type} — or null (no RFA needed)
let vf_payrollMonths = [];     // imported payroll months a DV can be prepared from (GET payroll-management/available)
let vf_attachments = [];     // staged Supporting Documents: { document_name, link, size, uploading }
let vf_attDirty = false;     // true once the attachments list has changed since open — avoids re-registering an unchanged batch on every save
let vf_existingMultipleNodeId = null; // this voucher's current multiple_node_id (edit mode), reused as-is when attachments weren't touched
let vf_dedTypes = [];         // deduction type options (fims.deduction_types)
let vf_dedSeq   = 0;          // row-id counter for the Deductions grid
let vf_coaOptions = [];       // postable chart-of-accounts rows — reused by the Account Code field and the Accounting Entries grid
let VF_ATC = [];              // active ATC codes — see the ATC subsection of card 5 (Deductions)
let VF_RC  = [];              // responsibility centers, for ATC "Chargeability"
let VF_SL  = [];              // SL masters, for the Accounting Entries grid's SL Code picker
let vf_atcSeq = 0;            // row-id counter for the ATC grid
let vf_atcFull = null;        // the "full base" the ATC rows' bases were last split against (see vf_syncAtcBases()); null = not measured yet
let vf_jevSeq = 0;            // row-id counter for the Accounting Entries grid
let vf_jevGroupSeq = 0;       // GL account-group id counter for the Accounting Entries grid (see vf_addJevGroup())

function vf_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

// The API JWT (window.ew.API_JWT_TOKEN, read by vf_authFetch) expires 10 minutes after login/page
// load with no silent refresh — leaving this modal open past that turns every call below into a
// 401. Give that one specific, actionable message instead of a raw "HTTP 401".
const VF_SESSION_EXPIRED_MSG = 'Your session has expired. Please refresh the page and log in again.';
function vf_isSessionExpiredMessage(msg) { return msg === VF_SESSION_EXPIRED_MSG; }

// path must NOT start with "/" — the sub-folder deployment shim
// (_shared/subfolder-prefix.php) rewrites any bare-quoted "/word" literal it finds in the
// rendered JS, which would double-prefix a path handed in with its own leading slash (VF_API
// already carries the deployment prefix, e.g. "/fims-nha/api"). Concatenating the "/" here,
// outside any quoted literal, keeps this call immune to that rewrite.
async function vf_getJson(path) {
    const res = await vf_authFetch(VF_API + '/' + path);
    if (res.status === 401) throw new Error(VF_SESSION_EXPIRED_MSG);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

/* ── Open / close ──────────────────────────────────────────── */
async function vf_open(id = 0) {
    vf_id = Number(id) || 0;
    vf_reset();
    $vf('vf-modal-title').textContent = vf_id ? 'Edit Disbursement Voucher' : 'New Disbursement Voucher';
    $vf('vf-overlay').classList.add('open');

    if (!vf_bound) { vf_bindEvents(); vf_bound = true; }

    window.PagePreloader?.show();
    try {
        vf_refReady ??= vf_loadFiscalYears();
        vf_booksReady ??= vf_loadBooks();
        vf_dedTypesReady ??= vf_loadDeductionTypes();
        vf_coaReady ??= vf_loadCoaOptions();
        vf_atcReady ??= vf_loadAtcCodes();
        vf_rcReady  ??= vf_loadRcOptions();
        vf_slReady  ??= vf_loadSlOptions();
        // allSettled, not all: one reference-data fetch failing (e.g. deduction types) used to
        // short-circuit the whole load — hiding whatever else also failed behind a single error
        // message, and skipping vf_loadExisting()/vf_recalc() below entirely even though most of
        // the form (including the RFA list) may have loaded fine. Collect every failure instead so
        // the form still opens with whatever did load, and the user sees the full picture.
        const results = await Promise.allSettled([
            vf_refReady, vf_booksReady, vf_dedTypesReady, vf_coaReady, vf_atcReady, vf_rcReady, vf_slReady,
            vf_loadVendors(), vf_loadRfaOptions(), vf_loadSaroOptions(), vf_loadPayrollMonths(),
        ]);
        const loadErrors = results.filter(r => r.status === 'rejected').map(r => r.reason?.message || String(r.reason));
        vf_renderPayrollOptions(); // after vf_loadVendors() rebuilt the Vendor dropdown

        if (vf_id) {
            try {
                await vf_loadExisting();
            } catch (e) {
                loadErrors.push(e.message);
            }
        } else {
            vf_refreshDvNumber();
        }
        // Defensive: if the RFA search box picked up an autofocus while vf_loadExisting() was still
        // in flight, its dropdown may have rendered before vf_selectedRfaIds was populated — close it
        // now so a stale, unfiltered list isn't left showing an RFA the chips below already cover.
        $vf('vf-rfa-dropdown').classList.remove('open');
        vf_recalc();
        // One expired token fails every one of the reference-data calls above at once — show the
        // single actionable message instead of eight copies of the same thing.
        if (loadErrors.some(vf_isSessionExpiredMessage)) {
            vf_showErrors([VF_SESSION_EXPIRED_MSG]);
        } else if (loadErrors.length) {
            vf_showErrors(loadErrors);
        }
    } catch (e) {
        vf_showErrors([e.message]);
    } finally {
        window.PagePreloader?.hide();
    }
}

function vf_close() {
    if (vf_saving) return;
    $vf('vf-overlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $vf('vf-overlay')?.classList.contains('open')) vf_close();
});

function vf_reset() {
    vf_showErrors([]);
    ['dv-date', 'book', 'rfa', 'claimant', 'particulars', 'gross'].forEach(vf_clearFieldError);
    $vf('vf-dv-date').value       = new Date().toISOString().slice(0, 10);
    $vf('vf-book').value          = '';
    $vf('vf-rfa-search').value    = '';
    $vf('vf-rfa-dropdown').classList.remove('open');
    $vf('vf-rfa-empty-hint').style.display = 'none';
    vf_selectedRfaIds = [];
    vf_renderRfaChips();
    $vf('vf-saro').value = '';

    vf_payrollPeriodId = null;
    vf_remittanceId = null;
    vf_dvRemit = null;
    vf_dvRemitVendors = [];
    vf_renderRemitSchedule(null);
    vf_renderDedTypeButtons();
    $vf('vf-rfa-search').disabled = false;
    vf_markRemittanceFields(null);   // back to a normal DV: vendor dropdown and required RFA

    vf_attachments  = [];
    vf_attDirty     = false;
    vf_existingMultipleNodeId = null;
    $vf('vf-att-input').value = '';
    $vf('vf-att-link-name').value = '';
    $vf('vf-att-link-url').value  = '';
    vf_renderAttachments();

    $vf('vf-vendor').value = '';
    $vf('vf-vendor-card').classList.remove('visible');
    $vf('vf-po-number').value = '';
    $vf('vf-po-warn').style.display = 'none';
    $vf('vf-claimant').value = '';
    $vf('vf-claimant-address').value = '';
    $vf('vf-claimant-tin').value = '';
    $vf('vf-particulars').value = '';
    $vf('vf-gross').value = '';
    vf_lineAmounts = null;
    vf_renderRfaBreakdown();

    vf_clearDeductionRows();
    vf_dedSeq = 0;

    vf_clearAtcRows();
    vf_atcSeq = 0;
    vf_recalcAtc();

    $vf('vf-jev-tbody').innerHTML = '';
    vf_jevSeq = 0;
    vf_jevGroupSeq = 0;
    vf_jevAutoMode = true; // fresh voucher — reset in case a previous edit session in this same modal turned it off
    vf_recalcJev();

    $vf('vf-dept-project').value = '';
    $vf('vf-fund-source').value = '';
    $vf('vf-account-code').value = '';
    $vf('vf-budget').value = '';
    $vf('vf-obligation').value = '';
    $vf('vf-remarks').value = '';
    vf_paymentsPrior = 0;
    $vf('vf-payments-hint').textContent = '';

    $vf('vf-dv-number').textContent = '—';
    $vf('vf-status').textContent    = $vf('vf-status-doc').textContent = 'DRAFT';
    $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = false;
}

function vf_bindEvents() {
    $vf('vf-dv-date').addEventListener('change', () => { if (!vf_id) vf_refreshDvNumber(); });
    $vf('vf-rfa-search').addEventListener('input', vf_renderRfaDropdown);
    $vf('vf-rfa-search').addEventListener('focus', vf_renderRfaDropdown);
    document.addEventListener('click', e => {
        if (!$vf('vf-rfa-search').contains(e.target) && !$vf('vf-rfa-dropdown').contains(e.target)) {
            $vf('vf-rfa-dropdown').classList.remove('open');
        }
    });
    $vf('vf-vendor').addEventListener('change', vf_onVendorChange);
    $vf('vf-po-number').addEventListener('change', () => vf_checkPoIar());
    $vf('vf-po-number').addEventListener('input', () => { $vf('vf-po-warn').style.display = 'none'; }); // stale until re-checked on change
    vf_bindMoneyInput($vf('vf-gross'));
    vf_bindMoneyInput($vf('vf-budget'));
    vf_bindMoneyInput($vf('vf-obligation'));
    $vf('vf-gross').addEventListener('input', () => vf_recalc());
    $vf('vf-obligation').addEventListener('input', () => vf_recalc());
    // resync whenever either changes, same as every other input that feeds it — vf_recalc() itself
    // now also refreshes the (hidden) Accounting Entries grid's totals (vf_syncAutoJevLines()).
    $vf('vf-account-code').addEventListener('input', () => vf_recalc());
    $vf('vf-claimant').addEventListener('input', () => vf_recalc());
    // Event delegation: deductions are added/removed dynamically, so bind once on their shared
    // container rather than per-row — a typed-in-directly flat amount (no Rate given) had no
    // listener at all before, so Accounting Entries (and Net Amount) never resynced from it.
    $vf('vf-ded-container').addEventListener('input', e => { if (e.target.classList.contains('vf-ded-amount')) vf_recalc(); });
    // RFA Breakdown's Amount cells are re-rendered with the table, so delegate from its static body too.
    const bdBody = $vf('vf-rfa-breakdown-body');
    bdBody.addEventListener('input', e => {
        if (!e.target.classList.contains('vf-rfa-line-amt')) return;
        vf_liveFormatMoney(e.target);
        vf_onLineAmountInput();
    });
    bdBody.addEventListener('input', e => {
        if (e.target.classList.contains('vf-comp-base')) vf_liveFormatMoney(e.target);
        if (e.target.classList.contains('vf-comp-base') || e.target.classList.contains('vf-comp-pct')) vf_onComputationInput();
    });
    bdBody.addEventListener('focusout', e => {
        if (!e.target.classList.contains('vf-rfa-line-amt')) return;
        e.target.value = e.target.value.trim() === '' ? '' : vf_fmtMoneyVal(vf_numVal(e.target.value));
    });
}

/* ── Reference data ────────────────────────────────────────── */
let vf_fiscalYears = []; // fims.fiscal_year rows — Fiscal Year isn't asked for directly anymore
                          // (see vf_derivedFiscalYear()), but a DV Date's year still has to match
                          // one of these for the backend's own fiscal_yr existence check to pass.

async function vf_loadFiscalYears() {
    try {
        const json = await vf_getJson('voucher/fiscal-years');
        vf_fiscalYears = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(n => !isNaN(n));
    } catch (e) {
        vf_fiscalYears = [];
        vf_refReady = null;
        throw new Error('Failed to load fiscal years: ' + e.message);
    }
}

/* Books (fims.books — Main Office/CISFA/NGC/MPLC-PPMO/PPMO) via GET /books/all. Book is an
   accounting classification, not a DV-header field, so it lives in the Accounting Entries card
   (see vf-book there) — required only once at least one Accounting Entries line is filled in. */
async function vf_loadBooks() {
    try {
        const json = await vf_getJson('books/all');
        const sel = $vf('vf-book');
        const current = sel.value;
        sel.innerHTML = '<option value="">Select a book…</option>' +
            (json.data ?? []).map(b => `<option value="${b.book_id}">${vf_esc(b.name)}</option>`).join('');
        if (current) sel.value = current;
    } catch (e) {
        vf_booksReady = null;
        throw new Error('Failed to load books: ' + e.message);
    }
}

/** DV Date's own year if it's a configured fiscal year, otherwise the closest configured one. */
function vf_derivedFiscalYear() {
    const wanted = new Date($vf('vf-dv-date').value || Date.now()).getFullYear();
    if (!vf_fiscalYears.length) return wanted;
    if (vf_fiscalYears.includes(wanted)) return wanted;
    return vf_fiscalYears.reduce((best, y) => Math.abs(y - wanted) < Math.abs(best - wanted) ? y : best, vf_fiscalYears[0]);
}

/* One "+ TYPE" pill per fims.deduction_type row — clicking it adds a row already bound to that
   type (see vf_addDeductionLine), instead of a single "+ Add Deduction" control. */
function vf_renderDedTypeButtons() {
    // the payroll types (PAYROLL_GSIS, PAYROLL_WTAX, …) belong to payroll DVs only — the imported payroll fills them
    const types = vf_dedTypes.filter(t => vf_payrollPeriodId || !String(t.code || '').startsWith('PAYROLL_'));
    // The "+ ATC ROW" pill sits with the deduction-type pills (it adds a row to the ATC table above), so it's always shown.
    // One "Less: + Add deduction…" dropdown instead of a pill per type: picking an option adds that row, then the select resets.
    $vf('vf-ded-types').innerHTML = `<span class="vf-ded-less">Less:</span>
        <select class="vf-ded-add-select" id="vf-ded-add" aria-label="Add deduction">
            <option value="">+ Add deduction…</option>
            ${types.map(t => `<option value="t:${vf_esc(t.deduction_type_id)}">${vf_esc(t.name)}</option>`).join('')}
            <option value="atc">ATC row (withholding tax)</option>
        </select>`;
    $vf('vf-ded-add').addEventListener('change', e => {
        const v = e.target.value;
        e.target.value = '';
        if (v === 'atc') vf_addAtcLine();
        else if (v.startsWith('t:')) vf_addDeductionLine({ deduction_type_id: v.slice(2) });
    });
    // A payroll DV's deductions are the imported month's (filled in, and re-read from Payroll Management on save): their
    // rows show each name and amount only — no Rate (the import has none), no × remove, no "+ TYPE" pills (.vf-ded-payroll).
    // Runs whenever the payroll month is picked, changed, cleared or restored on edit.
    $vf('vf-ded-section').classList.toggle('vf-ded-payroll', !!vf_payrollPeriodId);
}

async function vf_loadDeductionTypes() {
    try {
        const json = await vf_getJson('voucher/deduction-types');
        vf_dedTypes = json.data ?? [];
        vf_renderDedTypeButtons();
    } catch (e) {
        vf_dedTypesReady = null;
        throw new Error('Failed to load deduction types: ' + e.message);
    }
}

async function vf_loadCoaOptions() {
    try {
        // postable_only (not the region-scoped /chart-of-accounts/all) — posting a voucher isn't
        // restricted to the preparer's own region, so every active+postable GL account should be
        // selectable here regardless of which region it belongs to.
        const json = await vf_getJson('chart_of_accounts/all?postable_only=1');
        vf_coaOptions = (json.data ?? []).filter(a => a.posting_allowed);
        nhaSearchableSelect($vf('vf-account-code'), vf_coaOptions.map(a => ({ value: a.account_code, label: a.account_title })));
    } catch (e) {
        vf_coaReady = null;
        throw new Error('Failed to load chart of accounts: ' + e.message);
    }
}

async function vf_loadAtcCodes() {
    try {
        const json = await vf_getJson('voucher/atc-codes');
        VF_ATC = json.data ?? [];
    } catch (e) {
        vf_atcReady = null;
        throw new Error('Failed to load ATC codes: ' + e.message);
    }
}

async function vf_loadRcOptions() {
    try {
        const json = await vf_getJson('responsibility-center/all');
        VF_RC = json.data ?? [];
    } catch (e) {
        vf_rcReady = null;
        throw new Error('Failed to load responsibility centers: ' + e.message);
    }
}

async function vf_loadSlOptions() {
    try {
        const json = await vf_getJson('voucher/sl-codes');
        VF_SL = json.data ?? [];
    } catch (e) {
        vf_slReady = null;
        throw new Error('Failed to load SL codes: ' + e.message);
    }
}

async function vf_loadVendors() {
    const sel = $vf('vf-vendor');
    try {
        const json = await vf_getJson('vendors/all?active=1');
        vf_vendors = {};
        (json.data ?? []).forEach(v => { vf_vendors[v.vendor_id] = v; });
        sel.innerHTML = '<option value="">— None / type claimant manually —</option>' + (json.data ?? []).map(v =>
            `<option value="${vf_esc(v.vendor_id)}">${vf_esc(v.vendor_code)} — ${vf_esc(v.vendor_name)}${v.vendor_type ? ' (' + vf_esc(v.vendor_type) + ')' : ''}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load vendors</option>';
        throw new Error('Failed to load vendors: ' + e.message);
    }
}

async function vf_loadRfaOptions() {
    try {
        // exclude_dv_id: lets THIS voucher's own already-linked RFAs keep showing (as "already
        // yours"), and leaves its own prior draw out of paid_amount so it doesn't double-count
        // against itself. One RFA only backs one DV at a time server-side (budget_allocation.dv_id)
        // — an RFA linked to a DIFFERENT, still-active DV simply isn't in this list at all.
        const qs   = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const json = await vf_getJson(`voucher/rfa/all${qs}`);
        vf_rfas = {};
        (json.data ?? []).forEach(r => { vf_rfas[r.allocation_id] = r; });
        $vf('vf-rfa-empty-hint').style.display = (json.data ?? []).length ? 'none' : 'block';
        vf_renderRfaDropdown();
    } catch (e) {
        vf_rfas = {};
        $vf('vf-rfa-empty-hint').style.display = 'none';
        throw new Error('Failed to load RFAs: ' + e.message);
    }
}

/** SARO single-select (fims.fund_tracking WHERE tracking type is 'SARO', /voucher/saro/all) — one
    per DV, unlike the RFA multi-select above. exclude_dv_id keeps THIS voucher's own already-linked
    SARO showing (as "already yours") the same way it does for vf_loadRfaOptions(). The actual link
    (fims.fund_tracking.dv_id) is only written server-side on save — see
    DisbursementVoucherService::linkSaro(). */
async function vf_loadSaroOptions() {
    const sel = $vf('vf-saro');
    const picked = sel.value;
    try {
        const qs   = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const json = await vf_getJson(`voucher/saro/all${qs}`);
        const rows = json.data ?? [];
        sel.innerHTML = '<option value="">— None —</option>' + rows.map(r =>
            `<option value="${vf_esc(r.id)}">${vf_esc(r.saro_id || '#' + r.id)}${r.account_number ? ' — ' + vf_esc(r.account_number) : ''}${r.bank_name ? ' (' + vf_esc(r.bank_name) + ')' : ''}</option>`).join('');
        if (picked && rows.some(r => String(r.id) === picked)) sel.value = picked; // keep the in-progress pick across a reload
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load SAROs</option>';
        throw new Error('Failed to load SAROs: ' + e.message);
    }
}

/* ── RFA multi-select ─────────────────────────────────────────
   vf_rfas (allocation_id -> row, from /voucher/rfa/all) is the full option pool; vf_selectedRfaIds
   is which of those the user has picked, in order. Each selection renders as a removable chip
   showing that RFA's own details (department, fiscal year, vendor, remaining balance) — "show the
   details of the RFA" — and Budget Reference (4) further down sums Budget/Payments/Balance across
   every selected RFA, see vf_recalc(). The actual link (fims.budget_allocation.dv_id) is only
   written server-side on save — see DisbursementVoucherService::linkRfas(). ───────────────────── */
/** The class (PS/MOOE/CAPITAL_OUTLAY/EQUIPMENT_OUTLAY) the first selected RFA locks this voucher
    to, once one is picked — null while nothing is selected yet, so every class is still offered. */
function vf_lockedRfaClass() {
    if (!vf_selectedRfaIds.length) return null;
    return vf_rfas[vf_selectedRfaIds[0]]?.expense_class ?? null;
}

/** The Vendor the RFA picker is locked to — whatever is currently chosen in the Vendor dropdown
    (card 1), regardless of whether it got there by the preparer picking it directly or by an RFA
    selection autofilling it (vf_onRfaSelectionChange()'s "withVendor" block). Two-way with Vendor:
    vf_onVendorChange() clears any selected RFA(s) that no longer match when the preparer changes it. */
function vf_lockedRfaVendorId() {
    return vf_isPayrollValue($vf('vf-vendor').value) ? null : ($vf('vf-vendor').value || null); // a payroll month is not a vendor
}

function vf_renderRfaDropdown() {
    const box    = $vf('vf-rfa-dropdown');
    const searchEl = $vf('vf-rfa-search');
    // the RFA number vf_openForPo() put in the box is not a search: ignore it until the user types something else
    const search = (searchEl.dataset.auto !== undefined && searchEl.value === searchEl.dataset.auto) ? '' : searchEl.value.trim().toLowerCase();
    const lockedClass  = vf_lockedRfaClass();
    const lockedVendor = vf_lockedRfaVendorId();
    const options = Object.values(vf_rfas)
        .filter(r => !vf_selectedRfaIds.includes(String(r.allocation_id)))
        .filter(r => !lockedClass || r.expense_class === lockedClass)
        .filter(r => !lockedVendor || String(r.vendor_id || '') === String(lockedVendor))
        .filter(r => !search || r.allocation_number?.toLowerCase().includes(search) || r.request_referrence_no?.toLowerCase().includes(search))
        .slice(0, 30);

    box.innerHTML = options.length
        ? options.map(r => `
            <div class="vf-rfa-opt" onclick="vf_selectRfa('${r.allocation_id}')">
                <strong>${vf_esc(r.allocation_number)}</strong>
                <small>${vf_esc(r.department_name || 'Unassigned Dept.')} &middot; FY ${vf_esc(r.fiscal_year)} &middot; ${vf_peso(r.remaining_balance)} left of ${vf_peso(r.budget_amount)}</small>
            </div>`).join('')
        : `<div class="vf-rfa-opt vf-rfa-opt-empty">${vf_esc(vf_rfaEmptyMessage(lockedClass, lockedVendor))}</div>`;
    box.classList.add('open');
}

function vf_rfaEmptyMessage(lockedClass, lockedVendor) {
    if (lockedClass && lockedVendor) return `No other ${lockedClass} RFAs for this vendor match.`;
    if (lockedClass)  return `No other ${lockedClass} RFAs match.`;
    if (lockedVendor) return 'No RFAs for this vendor match.';
    return 'No matching approved RFAs.';
}

function vf_selectRfa(id) {
    id = String(id);
    if (!vf_selectedRfaIds.includes(id)) vf_selectedRfaIds.push(id);
    $vf('vf-rfa-search').value = '';
    $vf('vf-rfa-dropdown').classList.remove('open');
    vf_clearFieldError('rfa');
    vf_onRfaSelectionChange();
}

function vf_removeRfa(id) {
    vf_selectedRfaIds = vf_selectedRfaIds.filter(x => x !== String(id));
    vf_onRfaSelectionChange();
}

function vf_renderRfaChips() {
    const box = $vf('vf-rfa-chips');
    vf_renderRfaBreakdown();
    if (!vf_selectedRfaIds.length) { box.innerHTML = ''; return; }
    box.innerHTML = vf_selectedRfaIds.map(id => {
        const r = vf_rfas[id];
        if (!r) return '';
        return `<div class="vf-rfa-chip">
            <div class="vf-rfa-chip-hdr">
                <strong>${vf_esc(r.allocation_number)}</strong>
                <button type="button" class="vf-rfa-chip-remove" title="Remove" onclick="vf_removeRfa('${id}')">&times;</button>
            </div>
            <dl class="vf-rfa-chip-dl">
                <div><dt>Department</dt><dd>${vf_esc(r.department_name) || 'Unassigned'}</dd></div>
                <div><dt>Fiscal Year</dt><dd>${vf_esc(r.fiscal_year)}</dd></div>
                <div><dt>Vendor</dt><dd>${vf_esc(r.vendor_name) || '—'}</dd></div>
                <div><dt>Remaining</dt><dd>${vf_peso(r.remaining_balance)} of ${vf_peso(r.budget_amount)}</dd></div>
            </dl>
        </div>`;
    }).join('');
}

/* "RFA Breakdown" (section B) — replaces the old standalone Amount input. One row per itemized RFA line as
   "account_code - description", with the RFA it belongs to, that RFA's remaining balance, and an EDITABLE
   Amount per line (it starts as Gross spread over the lines, Gross coming from the selected RFA(s); typing in a
   cell re-sums Gross — see vf_onLineAmountInput()). Gross Amount sits under the whole table and is the sum of those amounts; the hidden #vf-gross
   input is kept in step with it because the rest of the form (deductions, ATC, net, save payload) reads it.
   An RFA with no itemized lines gets one row built from its own account/description.
   The per-line split isn't stored — only the gross is saved (gross_amount). */
let vf_lineAmounts   = null; // "allocation_id:lineIndex" -> amount the user typed; null until a line amount is edited
let vf_lineAmountsSig = '';  // selected RFA ids the typed amounts belong to — a different selection discards them
let vf_renderedGross = 0;    // gross the table was last rendered/summed to; vf_recalc() re-renders when #vf-gross drifts from it

function vf_rfaBreakdownRows() {
    const rows = [];
    vf_selectedRfaIds.map(id => vf_rfas[id]).filter(Boolean).forEach(r => {
        const lines = (r.lines || []).length
            ? r.lines.map(l => ({ code: l.account_code, text: l.account_title || l.description, cap: Number(l.amount) || 0 }))
            : [{ code: r.account_code, text: r.account_title || r.description, cap: Number(r.remaining_balance) || 0 }];
        lines.forEach((l, i) => rows.push({ key: `${r.allocation_id}:${i}`, rfa: r, first: i === 0, span: lines.length, ...l }));
    });
    return rows;
}

function vf_renderRfaBreakdown() {
    const box = $vf('vf-rfa-breakdown-body');
    const card = $vf('vf-rfa-breakdown');
    if (!box || !card) return;
    const rows  = vf_rfaBreakdownRows();
    const gross = vf_numVal($vf('vf-gross').value);
    if (!rows.length && gross <= 0) { box.innerHTML = ''; vf_renderedGross = 0; return; }

    // Amounts: what the user typed if it still matches the gross and the same RFAs are selected; otherwise
    // the gross is spread over the lines in order (each up to its own amount, any excess on the last line).
    const sig = vf_selectedRfaIds.join(',');
    const typed = vf_lineAmounts && vf_lineAmountsSig === sig
        && Math.abs(rows.reduce((s, r) => s + (Number(vf_lineAmounts[r.key]) || 0), 0) - gross) < 0.005;
    let left = gross;
    const amounts = {};
    // Each RFA can only give what it has left (an RFA with no known budget is unbounded), so one RFA's lines can't
    // soak up the share that belongs to the next one — the per-RFA check in vf_validate() would otherwise flag a fresh selection.
    const rfaRoom = {};
    rows.forEach(r => { if (!(r.rfa.allocation_id in rfaRoom)) rfaRoom[r.rfa.allocation_id] = Number(r.rfa.budget_amount) > 0 ? (Number(r.rfa.remaining_balance) || 0) : Infinity; });
    rows.forEach((r, i) => {
        if (typed) { amounts[r.key] = Number(vf_lineAmounts[r.key]) || 0; return; }
        const v = i === rows.length - 1 ? left : Math.min(r.cap, left, rfaRoom[r.rfa.allocation_id]);
        amounts[r.key] = Math.max(0, Math.round(v * 100) / 100);
        left -= amounts[r.key];
        rfaRoom[r.rfa.allocation_id] -= amounts[r.key];
    });
    vf_renderedGross = gross;

    const label = (code, text) => [code, text].filter(Boolean).map(vf_esc).join(' - ') || '—';
    const body = rows.map(r => `<tr>
        <td>${label(r.code, r.text)}</td>
        ${r.first ? `<td rowspan="${r.span}" style="vertical-align:top;">${vf_esc(r.rfa.allocation_number)}</td>
        <td rowspan="${r.span}" style="text-align:right;vertical-align:top;">${vf_peso(r.rfa.remaining_balance)}</td>` : ''}
        <td class="vf-bd-amt"><input type="text" inputmode="decimal" class="vf-rfa-line-amt" data-key="${vf_esc(r.key)}" placeholder="0.00" value="${amounts[r.key] ? vf_fmtMoneyVal(amounts[r.key]) : ''}" aria-label="Amount for ${vf_esc(label(r.code, r.text))}"></td>
        <td></td>
    </tr>`).join('');
    const seen = new Set();
    const rfaList = rows.filter(r => !seen.has(r.rfa.allocation_id) && seen.add(r.rfa.allocation_id)).map(r => r.rfa);
    const rfaBal  = rfaList.reduce((a, r) => a + (Number(r.remaining_balance) || 0), 0);
    box.innerHTML = `<table class="vf-bd-table">
            <thead><tr>
                <th style="text-align:left;">Account Code &ndash; Description</th>
                <th style="text-align:left;">RFA</th>
                <th style="text-align:right;">RFA Balance / %</th>
                <th style="text-align:right;">Amount</th>
                <th></th>
            </tr></thead>
            <tbody>${body}</tbody>
            <tfoot>
                <tr class="vf-bd-gross">
                    <td colspan="2"><b>Gross amount</b> &middot; ${rfaList.map(r => vf_esc(r.allocation_number)).join(', ')}</td>
                    <td class="vf-bd-muted">${vf_peso(rfaBal)}</td>
                    <td id="vf-bd-gross-amt"><b>${vf_peso(gross)}</b></td>
                    <td></td>
                </tr>
                <tr class="vf-bd-comp">
                    <td colspan="2">
                        <div class="vf-comp-line"><span>Computation (optional):</span><input type="text" id="vf-comp-label" placeholder="e.g. Total Contract Cost"></div>
                        <small>Base amount &times; % = total amount due (fills the amount when there is one account)</small>
                    </td>
                    <td><input type="text" inputmode="decimal" class="vf-comp-base" placeholder="Base amount"></td>
                    <td><input type="text" inputmode="decimal" class="vf-comp-pct" placeholder="&times; %"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>`;
}

/** Computation row: Base amount x % fills the Amount cell when the breakdown has exactly one account line. */
function vf_onComputationInput() {
    const box = $vf('vf-rfa-breakdown-body');
    const amts = box.querySelectorAll('.vf-rfa-line-amt');
    if (amts.length !== 1) return;
    const base = vf_numVal(box.querySelector('.vf-comp-base').value);
    const pct  = parseFloat(box.querySelector('.vf-comp-pct').value);
    if (!(base > 0) || !(pct > 0)) return;
    amts[0].value = vf_fmtMoneyVal(Math.round(base * pct) / 100);
    vf_onLineAmountInput();
}

/** An Amount cell was edited: remember the split, set Gross Amount to their sum, and let the rest of the form follow. */
function vf_onLineAmountInput() {
    const inputs = $vf('vf-rfa-breakdown-body').querySelectorAll('.vf-rfa-line-amt');
    vf_lineAmounts = {};
    let sum = 0;
    inputs.forEach(inp => {
        const v = vf_numVal(inp.value);
        vf_lineAmounts[inp.dataset.key] = v;
        sum += v;
    });
    sum = Math.round(sum * 100) / 100;
    vf_lineAmountsSig = vf_selectedRfaIds.join(',');
    $vf('vf-gross').value = sum > 0 ? vf_fmtMoneyVal(sum) : '';
    vf_renderedGross = sum;
    const bdGross = $vf('vf-bd-gross-amt');
    if (bdGross) bdGross.innerHTML = `<b>${vf_peso(sum)}</b>`;
    vf_clearFieldError('gross');
    vf_recalc();
}

/** Re-fetches each selected RFA's live detail, re-autofills dept/fund/vendor/expense class, and
    recomputes the Budget Reference ledger. */
async function vf_onRfaSelectionChange() {
    vf_renderRfaChips();
    if (!vf_selectedRfaIds.length) {
        vf_paymentsPrior = 0;
        $vf('vf-payments-hint').textContent = '';
        vf_recalc();
        return;
    }

    window.PagePreloader?.show();
    try {
        const qs = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const details = await Promise.all(vf_selectedRfaIds.map(id =>
            vf_getJson(`voucher/rfa/read/${id}${qs}`).then(j => j.data).catch(() => vf_rfas[id])));
        details.forEach(rfa => { if (rfa) vf_rfas[rfa.allocation_id] = rfa; });
        vf_renderRfaChips();

        // Dept/Project joins every selected RFA's own department — several RFAs commonly share one
        // requesting office, but this still reads correctly when they don't.
        const depts = [...new Set(details.filter(Boolean).map(r => r.department_name).filter(Boolean))];
        $vf('vf-dept-project').value = depts.join('; ');

        $vf('vf-budget').value     = vf_fmtMoneyVal(details.filter(Boolean).reduce((s, r) => s + (Number(r.budget_amount) || 0), 0));
        $vf('vf-obligation').value = $vf('vf-budget').value;
        vf_paymentsPrior = details.filter(Boolean).reduce((s, r) => s + (Number(r.paid_amount) || 0), 0);
        $vf('vf-payments-hint').textContent = vf_paymentsPrior > 0 ? ' (prior draws on these RFAs)' : '';

        // Amount: the RFA(s)' own requested/remaining amount, same as Budget/Obligation above —
        // always set from the RFA, not left for the preparer to re-type (a DV draws what its RFA
        // was approved for; it isn't a free-typed figure). Still a normal, editable input afterward
        // (e.g. a partial draw), this just stops it from defaulting to blank/stale on every
        // selection change.
        // …except a payroll DV: its Amount is the imported payroll's gross (vf_applyPayroll()).
        if (!vf_payrollPeriodId && !vf_remittanceId && !vf_dvRemit) {
            $vf('vf-gross').value = vf_fmtMoneyVal(details.filter(Boolean).reduce((s, r) => s + (Number(r.remaining_balance) || 0), 0));
        }

        // PO number / vendor / account code / particulars: first selected RFA that carries one wins —
        // best-effort autofill, never overwrites what the user already set.
        const withPo      = details.find(r => r?.po_number);
        const withVendor  = details.find(r => r?.vendor_id);
        const withAccount = details.find(r => r?.account_code);
        const withDescription = details.find(r => r?.description);
        if (withPo && !$vf('vf-po-number').value) { $vf('vf-po-number').value = withPo.po_number; vf_checkPoIar(); }
        if (withAccount && !$vf('vf-account-code').value) $vf('vf-account-code').value = withAccount.account_code;
        if (withDescription && !$vf('vf-particulars').value) $vf('vf-particulars').value = withDescription.description;
        if (withVendor) {
            if (!vf_vendors[withVendor.vendor_id]) {
                vf_vendors[withVendor.vendor_id] = { vendor_id: withVendor.vendor_id, vendor_name: withVendor.vendor_name, address: withVendor.vendor_address, tin: withVendor.vendor_tin };
                $vf('vf-vendor').insertAdjacentHTML('beforeend',
                    `<option value="${vf_esc(withVendor.vendor_id)}">${vf_esc(withVendor.vendor_name)}</option>`);
            }
            if (!$vf('vf-vendor').value) {
                $vf('vf-vendor').value = withVendor.vendor_id;
                vf_onVendorChange();
            }
        }
    } catch (e) {
        vf_showErrors([vf_isSessionExpiredMessage(e.message) ? e.message : 'Could not load RFA details: ' + e.message]);
    } finally {
        window.PagePreloader?.hide();
        vf_recalc(); // also refreshes the hidden Accounting Entries grid's totals — see vf_syncAutoJevLines()
    }
}

/** Vendor <-> RFA is two-way: an RFA selection already autofills Vendor (vf_onRfaSelectionChange()'s
    "withVendor" block); this is the other direction — changing Vendor (to a different one, or
    clearing it back to "None") drops any already-selected RFA(s) that no longer match it, since a
    voucher can't cite an RFA approved for a different vendor than who it's now paying. Re-renders
    the RFA dropdown too, so a still-open picker immediately reflects the new vendor lock. */
async function vf_onVendorChange() {
    // Payroll group of this dropdown: an imported payroll month fills the whole DV (vf_applyPayroll());
    // leaving it (None or a vendor) clears everything it filled first.
    const value = $vf('vf-vendor').value;
    if (vf_isPayrollValue(value)) {
        await vf_applyPayroll(value.slice(VF_PAYROLL_PREFIX.length));
        return;
    }
    if (vf_payrollPeriodId) vf_clearPayrollFill();

    const v = vf_vendors[$vf('vf-vendor').value];
    const card = $vf('vf-vendor-card');
    if (!v) {
        card.classList.remove('visible');
    } else {
        $vf('vf-vc-name').textContent    = v.vendor_name || '—';
        $vf('vf-vc-type').textContent    = v.vendor_type || '—';
        $vf('vf-vc-address').textContent = v.address || '—';
        $vf('vf-vc-tin').textContent     = v.tin || '—';
        card.classList.add('visible');
        // Vendor fields win over whatever was typed manually, so the claimant always matches the vendor on file.
        $vf('vf-claimant').value         = v.vendor_name || '';
        $vf('vf-claimant-address').value = v.address || '';
        $vf('vf-claimant-tin').value     = v.tin || '';
        vf_clearFieldError('claimant');
        // The vendor's own Chart of Accounts assignment (fims.vendors.coa_account_id, resolved here
        // as coa_account_code — see VendorService::fetchAlls()) — never overwrites an Account Code
        // the preparer (or an already-selected RFA) already set.
        if (v.coa_account_code && !$vf('vf-account-code').value.trim()) {
            $vf('vf-account-code').value = v.coa_account_code;
            vf_clearFieldError('account-code');
        }
    }

    const vendorId  = $vf('vf-vendor').value;
    const mismatched = vf_selectedRfaIds.some(id => String(vf_rfas[id]?.vendor_id || '') !== String(vendorId || ''));
    if (mismatched) {
        vf_selectedRfaIds = [];
        vf_onRfaSelectionChange();
    }
    if ($vf('vf-rfa-dropdown').classList.contains('open')) vf_renderRfaDropdown();
    vf_syncAutoJevLines();
}

/**
 * Called whenever gross/deductions/ATC/vendor change (it used to keep Accounting Entries auto-filled
 * from them). Section D is hidden from Originating now, so a regular DV no longer generates entries
 * here — Accounting builds them at the FOR_ACCOUNTING stage (readone.php's vje_syncAutoLines()).
 * What this form's grid still holds is only what was put there deliberately:
 *   - a payroll DV's entries (vf_applyPayroll()) or a remittance DV's (vf_applyRemittance()), which
 *     come from those modules and are sent silently with the save, and
 *   - lines already saved on this DV (edit mode), sent back as-is so editing a REJECTED DV never
 *     wipes entries Accounting already prepared.
 * So all that's left to do here is refresh the grid's totals/hints.
 */
function vf_syncAutoJevLines() {
    vf_recalcJev();
    vf_refreshAllJevHints();
    vf_renderAcctPreview();
}

/**
 * Section C — the auto-generated Accounting Entry, a read-only preview of what Accounting will post, rebuilt from
 * section B every time it recalculates (called from vf_syncAutoJevLines()). Mirrors readone.php's vje_syncAutoLines():
 * Dr each RFA account line, Cr each deduction type's account, Cr each ATC code's account, Cr Accounts Payable for the rest.
 * Not sent on save. Hidden for payroll / remittance DVs, which bring their own entries.
 */
function vf_renderAcctPreview() {
    const sec = $vf('vf-sec-acct-prev'), body = $vf('vf-acct-prev-body');
    if (!sec || !body) return;
    const own = !!(vf_payrollPeriodId || vf_remittanceId || vf_dvRemit);
    sec.style.display = own ? 'none' : '';
    if (own) return;

    const title = code => (vf_coaOptions.find(a => a.account_code === code) || {}).account_title || '';
    const r2 = n => Math.round((Number(n) || 0) * 100) / 100;
    const money = n => vf_fmtMoneyVal(n);
    const drs = [], crs = [];

    const byKey = {};
    vf_rfaBreakdownRows().forEach(r => { byKey[r.key] = r; });
    $vf('vf-rfa-breakdown-body').querySelectorAll('.vf-rfa-line-amt').forEach(inp => {
        const amt = vf_numVal(inp.value), r = byKey[inp.dataset.key];
        if (r && amt > 0) drs.push({ code: r.code || '', desc: r.text || title(r.code), amt });
    });

    let credited = 0;
    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        const amt = vf_numVal(row.querySelector('.vf-ded-amount').value);
        const t = vf_dedTypes.find(t => String(t.deduction_type_id) === String(row.dataset.typeId));
        const code = t?.account_code || row.querySelector('.vf-ded-acct')?.value.trim() || '';
        if (!t || amt <= 0) return;
        credited += amt;
        crs.push({ code, desc: title(code) || t.name, amt });
    });
    vf_collectAtcLines().forEach(l => {
        const def = VF_ATC.find(c => c.atc_code === l.atc_code);
        const amt = r2(vf_pctOf(l.tax_base, l.rate));
        if (!def || amt <= 0) return;
        credited += amt;
        crs.push({ code: def.account_code || '', desc: title(def.account_code) || def.description, amt });
    });

    const gross = drs.reduce((a, d) => a + d.amt, 0);
    const ap = r2(gross - credited);
    if (ap > 0.004) {
        const v = vf_vendors[$vf('vf-vendor').value];
        const apCode = v?.coa_account_code || '2-01-01-010';
        crs.push({ code: apCode, desc: title(apCode) || 'Accounts Payable', amt: ap });
    }

    if (!drs.length) {
        body.innerHTML = '<tr class="vf-ap-empty"><td colspan="4">Select an RFA above — the entry is generated from it.</td></tr>';
        return;
    }
    const totalDr = drs.reduce((a, d) => a + d.amt, 0), totalCr = crs.reduce((a, d) => a + d.amt, 0);
    const bad = Math.abs(totalDr - totalCr) > 0.005;
    body.innerHTML =
        drs.map(d => `<tr><td>${vf_esc(d.code)}</td><td>${vf_esc(d.desc)}</td><td>${money(d.amt)}</td><td></td></tr>`).join('')
        + crs.map(c => `<tr><td>${vf_esc(c.code)}</td><td class="vf-ap-cr">${vf_esc(c.desc)}</td><td></td><td>${money(c.amt)}</td></tr>`).join('')
        + `<tr class="vf-ap-total${bad ? ' vf-ap-bad' : ''}"><td></td><td>Total</td><td>${money(totalDr)}</td><td>${money(totalCr)}</td></tr>`;
}


async function vf_refreshDvNumber() {
    const d = $vf('vf-dv-date').value;
    if (!d) return;
    try {
        const json = await vf_getJson('voucher/dv-number?dv_date=' + encodeURIComponent(d));
        $vf('vf-dv-number').textContent = json.data.dv_no;
    } catch (e) { /* the server assigns a number on save if this stays "—" */ }
}

/* ── Attachments (optional — Supporting Documents, same fims.file_attachments /
   multiple_node_id convention as the CBA/RFA form; see FileUploadServices) ──
   Each file uploads to storage (POST /upload/file) as soon as it's picked — vf_attachments just
   tracks the resulting {document_name, link} entries. The batch itself (multiple_node_id) is only
   registered — via POST /upload/multi-upload — when the voucher is actually saved, see
   vf_persistAttachments(). ─────────────────────────────────────────────────────────────────────── */
const VF_ATT_MAX_BYTES = 26214400; // 25MB — mirrors FileUploadServices' own limit

function vf_onAttDrop(e) {
    e.preventDefault();
    $vf('vf-dz').classList.remove('drag-over');
    if (e.dataTransfer.files?.length) vf_stageAttFiles(e.dataTransfer.files);
}
function vf_onAttChange(e) {
    if (e.target.files?.length) vf_stageAttFiles(e.target.files);
    e.target.value = '';
}

async function vf_stageAttFiles(fileList) {
    for (const file of Array.from(fileList)) {
        if (file.size > VF_ATT_MAX_BYTES) {
            vl_toastOrAlert(`"${file.name}" is over the 25MB limit and was not added.`);
            continue;
        }
        const row = { document_name: file.name.replace(/\.[^./]+$/, ''), link: null, size: file.size, uploading: true };
        vf_attachments.push(row);
        vf_renderAttachments();

        const form = new FormData();
        form.append('file', file);
        try {
            const res  = await vf_authFetch(`${VF_API}/upload/file`, { method: 'POST', body: form });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Upload failed.');
            row.link = json.link;
            row.uploading = false;
            vf_attDirty = true;
        } catch (e) {
            vf_attachments = vf_attachments.filter(r => r !== row);
            vl_toastOrAlert(`Failed to upload "${file.name}": ${e.message}`);
        }
        vf_renderAttachments();
    }
}

function vf_removeAttachment(idx) {
    vf_attachments.splice(idx, 1);
    vf_attDirty = true;
    vf_renderAttachments();
}

/** A typed reference (Google Drive, SharePoint, etc.) instead of an uploaded file — same
    fims.ip_filemeta.link convention FileUploadServices already accepts either way; nothing to
    upload, so this just stages the entry straight into vf_attachments like a finished upload. */
function vf_addLinkAttachment() {
    const nameEl = $vf('vf-att-link-name');
    const urlEl  = $vf('vf-att-link-url');
    const name = nameEl.value.trim();
    const url  = urlEl.value.trim();
    if (!name || !url) { vl_toastOrAlert('Enter both a document name and a link.'); return; }
    if (!/^https?:\/\//i.test(url)) { vl_toastOrAlert('Link must start with http:// or https://'); return; }
    vf_attachments.push({ document_name: name, link: url, size: null });
    vf_attDirty = true;
    nameEl.value = '';
    urlEl.value  = '';
    vf_renderAttachments();
}

function vf_renderAttachments() {
    $vf('vf-att-status').textContent = vf_attachments.length
        ? `${vf_attachments.length} file${vf_attachments.length > 1 ? 's' : ''} attached`
        : 'No files attached';
    $vf('vf-att-list').innerHTML = vf_attachments.map((a, idx) => {
        const isLink = /^https?:\/\//i.test(a.link || '');
        const name   = isLink
            ? `<a href="${vf_esc(a.link)}" target="_blank" rel="noopener noreferrer">${vf_esc(a.document_name)}</a>`
            : vf_esc(a.document_name);
        return `<div class="vf-att-row">
            <span class="vf-att-name" title="${vf_esc(a.document_name)}">${a.uploading ? '⏳ ' : ''}${name}${a.size ? ' <small>(' + vf_fmtSize(a.size) + ')</small>' : ''}</span>
            <button type="button" class="vf-att-remove" title="Remove" onclick="vf_removeAttachment(${idx})">&times;</button>
        </div>`;
    }).join('');
}

function vf_fmtSize(bytes) {
    return bytes >= 1024 * 1024 ? (bytes / (1024 * 1024)).toFixed(1) + ' MB' : Math.ceil(bytes / 1024) + ' KB';
}

/** Small helper so an upload failure is visible even before the list page's toast container exists. */
function vl_toastOrAlert(msg) {
    if (typeof window.vl_toast === 'function') window.vl_toast(msg, 'error');
    else alert(msg);
}

/**
 * Registers the staged files as one Supporting Documents batch (POST /upload/multi-upload) and
 * returns the multiple_node_id to save on the voucher — null if there's nothing to attach, or
 * undefined if attachments haven't changed since this voucher was opened (vf_attDirty), in which
 * case the caller reuses whatever multiple_node_id the voucher already has.
 */
async function vf_persistAttachments() {
    if (!vf_attDirty) return undefined;
    if (!vf_attachments.length) return null;

    const file_data = vf_attachments.filter(a => a.link).map(a => ({ document_name: a.document_name, link: a.link }));
    if (!file_data.length) return null;

    const res  = await vf_authFetch(`${VF_API}/upload/multi-upload`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ file_data }),
    });
    if (res.status === 401) throw new Error(VF_SESSION_EXPIRED_MSG);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to save attachments.');
    return json.multiple_node_id;
}

/* ── Deductions (optional — e.g. contractor retention) ─────────
   The type is picked once, up front, from its own "+ TYPE" pill (vf_renderDedTypeButtons) instead
   of a per-row <select> — each row is created already bound to one fixed deduction type. */
// A deduction type with no default posting account (account_code NULL — e.g. "OTHER", see
// "08 - deduction_types_other_recoupment_penalty_vat.sql") used to silently get no Accounting
// Entries line at all. Now the preparer must pick one here — required, same searchable
// chart-of-accounts picker the Accounting Entries grid itself uses (vf_coaOptions).
/* Retention, Advance Recoupment and Penalty Tax are one figure per voucher (VAT too — see vf_addVatLine()); a second line of
   the same type is almost always an accidental double click that deducts the amount twice. "Other" can repeat. The server enforces
   the same rule (DisbursementVoucherService::SINGLE_DEDUCTION_TYPES). A line already SAVED twice (older data) still loads. */
const VF_SINGLE_DED = ['RETENTION', 'RECOUPMENT', 'PENALTY_TAX'];

function vf_addDeductionLine(d = null) {
    const idx = ++vf_dedSeq;
    const t = vf_dedTypes.find(t => String(t.deduction_type_id) === String(d?.deduction_type_id));
    if (t && VF_SINGLE_DED.includes(t.code) && !(d?.gross_amount != null)) {
        const already = [...$vf('vf-ded-container').querySelectorAll('.vf-ded-row')].some(r => r.dataset.typeId === String(t.deduction_type_id));
        if (already) { vl_toastOrAlert(`"${t.name}" is already added — edit that line instead (it can only be added once).`); return; }
    }
    // VAT gets its own row layout (Inclusive/Exclusive, VAT Computation, Withholding VAT) — a singleton.
    // A pill click passes only {deduction_type_id}; a saved line also carries gross_amount (its tax base).
    // A legacy saved VAT line with a flat peso amount and no rate can't be expressed as Withholding VAT %,
    // so it falls through to the plain Rate/Amount row below instead of losing its amount.
    if (t?.code === 'VAT') {
        const saved = d?.gross_amount !== undefined && d?.gross_amount !== null;
        const flatLegacy = saved && (d.rate === null || d.rate === undefined || d.rate === '') && Number(d.deduction_amount) > 0;
        if (!flatLegacy) {
            if (!vf_vatRow()) return vf_addVatLine(idx, d, saved);
            if (!saved) { vl_toastOrAlert('A VAT line is already added — edit it instead.'); return; }
        }
    }
    const needsAcct = !!t && !t.account_code;
    const div = document.createElement('div');
    div.className = 'vf-ded-row';
    div.id = `vf-ded-row-${idx}`;
    div.dataset.typeId = d?.deduction_type_id ?? '';
    div.innerHTML = `
        <button type="button" class="vf-ded-remove" title="Remove" onclick="vf_removeDeductionLine(${idx})">&times;</button>
        <div class="vf-ded-type-label"><span class="vf-less">Less:</span> <b>${vf_esc(t?.name) || '—'}</b>${t?.account_code ? ` <span class="vf-ded-acct-hint">${vf_esc([t.account_code, (vf_coaOptions.find(a => a.account_code === t.account_code) || {}).account_title].filter(Boolean).join(' '))}</span>` : ''}</div>
        <div class="vf-grid2" style="margin-top:6px;">
            <div class="nha-fg"><label>Rate (%)</label><input type="number" class="vf-ded-rate" min="0" step="0.01" placeholder="Rate %"></div>
            <div class="nha-fg"><label>Deduction Amount (₱)</label><input type="text" inputmode="decimal" class="vf-ded-amount" placeholder="Amount"></div>
        </div>
        ${needsAcct ? `<div class="nha-fg" style="margin-top:8px;">
            <label>Posting Account <span class="nha-req">*</span></label>
            <input type="text" class="vf-ded-acct" placeholder="Search GL account code (UACS)…">
            <span class="vf-hint">No default account for "${vf_esc(t?.name)}" — required before this can post to the General Ledger.</span>
        </div>` : ''}`;
    $vf('vf-ded-container').appendChild(div); // new rows go below the previous ones

    div.querySelector('.vf-ded-rate').addEventListener('input', () => vf_recalcDeductionLine(idx));
    vf_bindMoneyInput(div.querySelector('.vf-ded-amount'));

    if (needsAcct) {
        const acctInput = div.querySelector('.vf-ded-acct');
        acctInput.value = d?.account_code ?? '';
        nhaSearchableSelect(acctInput, vf_coaOptions.map(a => ({ value: a.account_code, label: a.account_title })), () => vf_recalcDeductionLine(idx));
        acctInput.addEventListener('input', () => vf_recalcDeductionLine(idx));
    }

    if (d) {
        div.querySelector('.vf-ded-rate').value = d.rate ?? '';
        div.querySelector('.vf-ded-amount').value = vf_fmtMoneyVal(d.deduction_amount);
    }
    vf_recalcDeductionLine(idx); // also resyncs Accounting Entries — the deduction's own GL account (t.account_code) just changed
}

function vf_removeDeductionLine(idx) {
    $vf(`vf-ded-row-${idx}`)?.remove();
    vf_recalc();
}

/* ── VAT deduction (Inclusive/Exclusive + Withholding VAT) ─────
   Same model as the Accounting DV Processing page's VAT tab (ACCOUNTING-DCD dv-processing.php calcVAT()):
     1. Statutory VAT, fixed 12% — INFORMATIONAL, never subtracted from the Net Amount:
          Inclusive: Tax Base = Gross / 1.12, VAT = Gross - Tax Base
          Exclusive: Tax Base = Gross,        VAT = Gross x 12%
     2. Withholding VAT, rate typed by the preparer — the real cash deduction: Tax Base x rate.
   The row reuses .vf-ded-rate / .vf-ded-amount for the Withholding VAT so vf_totalDeductions() and
   vf_collectDeductions() need no special case, except gross_amount: it is the Tax Base (row.dataset.base),
   which makes the server's gross_amount x rate / 100 land on the same peso amount. Inclusive vs. exclusive
   isn't stored — on load it's inferred from that base (below the DV's gross => inclusive).
   The ATC grid's Tax Base follows the same VAT-exclusive base (vf_rebaseAtcToVat()). */
const VF_VAT_RATE = 12;

function vf_vatRow() { return $vf('vf-ded-container').querySelector('.vf-ded-row[data-vat="1"]'); }

function vf_vatSplit(gross, vatType) {
    const r2 = n => Math.round((n + Number.EPSILON) * 100) / 100;
    const vat  = r2(vatType === 'exclusive' ? gross * VF_VAT_RATE / 100 : gross - gross / (1 + VF_VAT_RATE / 100));
    const base = r2(vatType === 'exclusive' ? gross : gross - vat);
    return { vat, base };
}

function vf_addVatLine(idx, d, saved) {
    const gross   = vf_numVal($vf('vf-gross').value);
    const savedBase = saved ? Number(d.gross_amount) : 0;
    const vatType = saved && savedBase > 0 && savedBase < gross - 0.005 ? 'inclusive' : (saved ? 'exclusive' : 'inclusive');
    const div = document.createElement('div');
    div.className = 'vf-ded-row';
    div.id = `vf-ded-row-${idx}`;
    div.dataset.typeId = d.deduction_type_id;
    div.dataset.vat = '1';
    div.dataset.vatType = vatType;
    div.innerHTML = `
        <button type="button" class="vf-ded-remove" title="Remove" onclick="vf_removeDeductionLine(${idx})">&times;</button>
        <div class="vf-ded-type-label"><span class="vf-less">Less:</span> <b>VAT withheld</b>
            <span class="vf-vat-types">
                <label class="vf-vat-opt" title="Gross already includes VAT"><input type="radio" name="vf-vat-type-${idx}" value="inclusive"> Inclusive</label>
                <label class="vf-vat-opt" title="VAT is added on top of gross"><input type="radio" name="vf-vat-type-${idx}" value="exclusive"> Exclusive</label>
            </span>
        </div>
        <div class="vf-vat-rate nha-fg"><input type="number" class="vf-ded-rate" min="0" max="100" step="0.01" placeholder="Rate %" aria-label="Withholding VAT rate (%)"></div>
        <div class="vf-vat-wamt nha-fg"><input type="text" class="vf-ded-amount" readonly placeholder="Amount" aria-label="Withholding VAT amount"></div>
        <div class="vf-vat-info">
            VAT computation (informational, not deducted): statutory ${VF_VAT_RATE}% &middot;
            VAT <input type="text" class="vf-vat-amt" readonly aria-label="VAT amount"> &middot;
            Tax base <input type="text" class="vf-vat-base" readonly aria-label="Tax base">
        </div>
        <span class="vf-hint vf-vat-hint"></span>`;
    $vf('vf-ded-container').appendChild(div);

    div.querySelector('.vf-ded-rate').value = saved ? (d.rate ?? '') : '';
    div.querySelector('.vf-ded-rate').addEventListener('input', () => vf_recalc());
    div.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', () => {
        div.dataset.vatType = r.value;
        vf_recalc(); // vf_syncVat() + vf_syncAtcBases(): the ATC rows follow the new base, keeping their split
    }));
    vf_recalc();
}

/* Refreshes a VAT row's VAT Amount / Tax Base / Withholding VAT from the DV's Gross. The ATC rows are NOT
   touched here: vf_syncAtcBases() moves them when the base they split changes, without losing their split. */
function vf_syncVat(row) {
    const gross = vf_numVal($vf('vf-gross').value);
    const vatType = row.dataset.vatType === 'exclusive' ? 'exclusive' : 'inclusive';

    const { vat, base } = vf_vatSplit(gross, vatType);
    row.dataset.base = base;
    row.querySelector('.vf-vat-amt').value  = vf_fmtMoneyVal(vat);
    row.querySelector('.vf-vat-base').value = vf_fmtMoneyVal(base);
    row.querySelectorAll('.vf-vat-opt').forEach(l => {
        const input = l.querySelector('input');
        input.checked = input.value === vatType;
        l.classList.toggle('active', input.checked);
    });

    const rate = parseFloat(row.querySelector('.vf-ded-rate').value);
    const wvat = rate > 0 ? vf_pctOf(base, rate) : 0;
    row.querySelector('.vf-ded-amount').value = wvat > 0 ? vf_fmtMoneyVal(wvat) : '';
    row.querySelector('.vf-vat-hint').textContent =
        (vatType === 'inclusive' ? `Tax Base = Gross ÷ ${(1 + VF_VAT_RATE / 100).toFixed(2)}` : 'Tax Base = Gross (VAT is added on top)')
        + (rate > 0 ? ` · Withholding VAT = Tax Base × ${rate}%` : ' · enter a Withholding VAT rate to deduct it');
}

/* A deduction's gross_amount always mirrors the DV's own Amount (not separately editable — see
   the RFA Breakdown / summary); its deduction_amount is rate% of that unless the
   user types an amount directly (no rate given = flat peso deduction). */
function vf_recalcDeductionLine(idx) {
    const row = $vf(`vf-ded-row-${idx}`);
    if (!row) return;
    if (row.dataset.vat === '1') { vf_recalc(); return; } // VAT row: vf_recalc() -> vf_syncVat() owns its figures
    const gross = vf_numVal($vf('vf-gross').value);
    const rate = parseFloat(row.querySelector('.vf-ded-rate').value);
    if (!isNaN(rate) && rate > 0) {
        row.querySelector('.vf-ded-amount').value = vf_fmtMoneyVal(vf_pctOf(gross, rate));
    }
    vf_recalc();
}

function vf_totalDeductions() {
    let total = 0;
    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        total += vf_numVal(row.querySelector('.vf-ded-amount').value);
    });
    return total;
}

function vf_collectDeductions() {
    const out = [];
    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        const typeId = row.dataset.typeId || '';
        const amount = vf_numVal(row.querySelector('.vf-ded-amount').value);
        if (!typeId && amount <= 0) return; // blank trailing row
        out.push({
            deduction_type_id: typeId || null,
            rate             : row.querySelector('.vf-ded-rate').value === '' ? null : parseFloat(row.querySelector('.vf-ded-rate').value),
            // VAT line: the Tax Base its Withholding VAT rate applies to (see vf_syncVat()), not the DV's Gross.
            gross_amount     : row.dataset.vat === '1' ? (Number(row.dataset.base) || 0) : vf_numVal($vf('vf-gross').value),
            deduction_amount : amount,
            account_code     : row.querySelector('.vf-ded-acct')?.value.trim() || null,
        });
    });
    return out;
}

/* ── ATC (optional — see card 5's doc comment) — same fields/behavior as readone.php's vje_ ATC
   grid, minus the auto-sync-into-Accounting-Entries wiring (that's Accounting's job once the DV
   reaches FOR_ACCOUNTING; here it's just a plain add/remove grid, saved as-is). ───────────────── */
/* Each ATC row is its own card in the same list as the deductions (#vf-ded-container), added below the previous one. */
function vf_clearDeductionRows() { $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(r => r.remove()); }
function vf_clearAtcRows()       { $vf('vf-ded-container').querySelectorAll('.vf-atc-row').forEach(r => r.remove()); vf_atcFull = null; $vf('vf-ded-container').classList.remove('vf-atc-multi'); }
function vf_atcRows()            { return $vf('vf-ded-container').querySelectorAll('.vf-atc-row'); }

/* ── What the ATC rows split ────────────────────────────────────
   The withholding is computed on the VAT-exclusive Tax Base when the DV has a VAT line, otherwise on Gross less
   the deductions (what vf_onAtcCodeSelect() has always defaulted to). The ATC rows' Tax Bases are meant to SPLIT
   that base between them (e.g. 60k at 2% + 40k at 5%), so:
     - a new row defaults to the part not yet allocated to the other rows (never the whole base again, which made two
       rows withhold on the same money);
     - when the base itself moves (Gross edited, VAT type toggled, a deduction added/removed) the rows follow it in
       proportion, so the split survives — but only while the rows add up to the whole base, so a deliberately
       partial base is never overwritten;
     - the rows' total is shown under them, and a total above the base is flagged (blocked on save above Gross). */
function vf_fullBase() {
    const vatRow = vf_vatRow();
    if (vatRow) return Number(vatRow.dataset.base) || 0;
    return Math.max(0, vf_numVal($vf('vf-gross').value) - vf_totalDeductions());
}

function vf_atcBaseTotal(exceptRow = null) {
    let sum = 0;
    vf_atcRows().forEach(tr => { if (tr !== exceptRow) sum += vf_numVal(tr.querySelector('.vf-atc-base').value); });
    return Math.round(sum * 100) / 100;
}

/* A row's share of the base (base / full base): remembered so a later move of the base re-splits without rounding drift. */
function vf_setAtcShare(tr) {
    const full = vf_fullBase();
    tr.dataset.share = full > 0 ? vf_numVal(tr.querySelector('.vf-atc-base').value) / full : 0;
}

function vf_syncAtcBases() {
    const full = vf_fullBase();
    const prev = vf_atcFull;
    vf_atcFull = full;
    const rows = [...vf_atcRows()];
    if (prev !== null && prev > 0 && rows.length && Math.abs(full - prev) > 0.004) {
        const bases = rows.map(tr => vf_numVal(tr.querySelector('.vf-atc-base').value));
        const fullyAllocated = Math.abs(bases.reduce((s, b) => s + b, 0) - prev) <= 0.01 * rows.length;
        if (fullyAllocated) {
            const next = rows.map((tr, i) => {
                const share = tr.dataset.share !== undefined ? Number(tr.dataset.share) : bases[i] / prev;
                return Math.round(share * full * 100) / 100;
            });
            // The split keeps adding up to the base exactly: the few centavos lost to rounding go to the largest row
            // (never to a row that holds no base — that would hand it a stray 0.01).
            const big = next.indexOf(Math.max(...next));
            next[big] = Math.round((next[big] + (full - next.reduce((s, b) => s + b, 0))) * 100) / 100;
            rows.forEach((tr, i) => {
                tr.querySelector('.vf-atc-base').value = next[i] > 0 ? vf_fmtMoneyVal(next[i]) : '';
                vf_refreshAtcWh(tr);
            });
        }
    }
    vf_renderAtcSummary(full);
}

function vf_renderAtcSummary(full) {
    const el = $vf('vf-atc-summary');
    const rows = vf_atcRows();
    if (!rows.length) { el.style.display = 'none'; return; }
    const total = vf_atcBaseTotal();
    const over  = total - full > 0.005;
    const what  = vf_vatRow() ? 'the VAT-exclusive Tax Base' : 'the amount net of deductions';
    el.className = 'vf-atc-summary' + (over ? ' over' : '');
    el.textContent = over
        ? `ATC Tax Bases total ${vf_peso(total)}, which is more than ${what} (${vf_peso(full)}) — the withholding would be taken on the same amount twice. Split the base between the rows.`
        : `ATC Tax Bases total ${vf_peso(total)} of ${what} (${vf_peso(full)})${full - total > 0.005 ? ` · ${vf_peso(full - total)} not allocated to an ATC row` : ''}.`;
    el.style.display = '';
}

function vf_addAtcLine(a = null) {
    const idx = ++vf_atcSeq;
    const div = document.createElement('div');
    div.className = 'vf-atc-row';
    div.id = `vf-atc-row-${idx}`;
    div.innerHTML = `
        <button type="button" class="vf-ded-remove" title="Remove" onclick="vf_removeAtcLine(${idx})">&times;</button>
        <div class="vf-ded-type-label vf-atc-label"><span class="vf-less">Less:</span>
            <span class="vf-atc-codewrap"><input type="text" class="vf-atc-code" placeholder="Search ATC code — description…" aria-label="ATC code"></span>
            <span class="vf-atc-acct-hint"></span>
        </div>
        <div class="vf-atc-rate-cell"><input type="number" class="vf-atc-rate" min="0" max="100" step="0.01" placeholder="Rate %" aria-label="Rate (%)"></div>
        <div class="vf-atc-wh-cell"><input type="text" class="vf-atc-wh" readonly value="₱ 0.00" aria-label="Withholding tax"></div>
        <div class="vf-atc-info">Tax base (&#8369;) <input type="text" inputmode="decimal" class="vf-atc-base" placeholder="0.00" aria-label="Tax base"> &times; rate = withholding tax</div>
        <div class="nha-fg" style="display:none;"><label>Chargeability</label>
            <select class="vf-atc-rc">
                <option value="">— None —</option>
                ${VF_RC.map(r => `<option value="${vf_esc(r.rc_id)}">${vf_esc(r.rc_id)} — ${vf_esc(r.rc_name)}</option>`).join('')}
            </select>
        </div>`;
    div.querySelector('.vf-atc-code').value = a?.atc_code ?? '';
    div.querySelector('.vf-atc-rate').value = a?.rate ?? '';
    div.querySelector('.vf-atc-base').value = a ? vf_fmtMoneyVal(a.tax_base) : '';
    div.querySelector('.vf-atc-rc').value   = a?.chargeability ?? '';
    div.querySelector('.vf-atc-code').addEventListener('change', () => vf_onAtcCodeSelect(idx));
    vf_bindMoneyInput(div.querySelector('.vf-atc-base'));
    div.querySelector('.vf-atc-base').addEventListener('input', () => vf_setAtcShare(div)); // typed by hand: its share of the base changes
    div.querySelectorAll('.vf-atc-rate, .vf-atc-base').forEach(el => {
        el.addEventListener('input', () => vf_recalcAtcLine(idx));
    });
    $vf('vf-ded-container').appendChild(div);
    vf_markAtcMulti();
    vf_atcAcctHint(div);
    vf_setAtcShare(div);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(div.querySelector('.vf-atc-code'), VF_ATC.map(c => ({ value: c.atc_code, label: c.description })));
    vf_recalcAtcLine(idx);
}

function vf_removeAtcLine(idx) {
    $vf(`vf-atc-row-${idx}`)?.remove();
    vf_markAtcMulti();
    vf_recalcAtc();
}

/** "2-02-01-010 Due to BIR" beside an ATC code: the account its withholding is credited to. */
function vf_atcAcctHint(tr) {
    const el = tr.querySelector('.vf-atc-acct-hint');
    if (!el) return;
    const def = VF_ATC.find(c => c.atc_code === tr.querySelector('.vf-atc-code').value);
    const code = def?.account_code || '';
    const title = (vf_coaOptions.find(a => a.account_code === code) || {}).account_title || '';
    el.textContent = [code, title].filter(Boolean).join(' ');
}

/** The Tax Base line under an ATC row is shown only when there are two or more ATC rows (the base is being split). */
function vf_markAtcMulti() {
    $vf('vf-ded-container').classList.toggle('vf-atc-multi', vf_atcRows().length > 1);
}

/**
 * Picking an ATC code prefills the rest of its row — the Rate(%) always follows the code's own
 * BIR default (fims.atc_codes.tax_rate), overwriting whatever was there before, since the rate is
 * intrinsic to the code, not a free choice; Tax Base and Chargeability are "never overwrite what's
 * already there" fills instead — Tax Base defaults to this DV's net-of-deductions amount, and
 * Chargeability defaults to the first selected RFA's own Responsibility Center
 * (fims.budget_allocation.requesting_rc_id), since that's who the withholding is normally charged
 * against.
 */
function vf_onAtcCodeSelect(idx) {
    const tr = $vf(`vf-atc-row-${idx}`);
    if (!tr) return;
    const code    = tr.querySelector('.vf-atc-code').value;
    const atcDef  = VF_ATC.find(c => c.atc_code === code);
    const rateEl  = tr.querySelector('.vf-atc-rate');
    const baseEl  = tr.querySelector('.vf-atc-base');
    const rcEl    = tr.querySelector('.vf-atc-rc');

    vf_atcAcctHint(tr);
    if (atcDef && isFinite(Number(atcDef.tax_rate))) rateEl.value = Number(atcDef.tax_rate).toFixed(2);

    if (!baseEl.value) {
        // Only the part of the base the other ATC rows haven't taken yet — a second row defaulting to the whole base
        // made the rows' total exceed it at once. With a VAT line the base is the VAT-exclusive Tax Base (see vf_fullBase()).
        const free = Math.max(0, Math.round((vf_fullBase() - vf_atcBaseTotal(tr)) * 100) / 100);
        baseEl.value = vf_fmtMoneyVal(free);
        vf_setAtcShare(tr);
    }

    if (!rcEl.value) {
        const primaryRc = vf_rfas[vf_selectedRfaIds[0]]?.requesting_rc_id;
        if (primaryRc) rcEl.value = primaryRc;
    }

    vf_recalcAtcLine(idx);
}

/* Withholding Tax cell of one ATC row = Tax Base x Rate%, centavo-exact (vf_pctOf()). */
function vf_refreshAtcWh(tr) {
    const rate = parseFloat(tr.querySelector('.vf-atc-rate').value) || 0;
    const base = vf_numVal(tr.querySelector('.vf-atc-base').value);
    tr.querySelector('.vf-atc-wh').value = vf_peso(vf_pctOf(base, rate));
}

function vf_recalcAtcLine(idx) {
    const tr = $vf(`vf-atc-row-${idx}`);
    if (!tr) return;
    vf_refreshAtcWh(tr);
    vf_recalcAtc();
}

function vf_collectAtcLines() {
    const lines = [];
    vf_atcRows().forEach(tr => {
        const atc_code = tr.querySelector('.vf-atc-code').value;
        const rate     = parseFloat(tr.querySelector('.vf-atc-rate').value) || 0;
        const tax_base = vf_numVal(tr.querySelector('.vf-atc-base').value);
        if (!atc_code && tax_base <= 0) return; // blank trailing row
        // Chargeability isn't asked for any more — the system takes it from the first selected RFA's Responsibility Center.
        lines.push({ atc_code, rate, tax_base, chargeability: tr.querySelector('.vf-atc-rc').value || vf_rfas[vf_selectedRfaIds[0]]?.requesting_rc_id || null });
    });
    return lines;
}

function vf_recalcAtc() {
    vf_syncAutoJevLines(); // an ATC row just changed — refresh the hidden Accounting Entries grid's totals
    vf_recalc();           // withholding feeds Net Amount Payable
}

/* ── Accounting Entries (optional) — flat list of journal entry lines ──── */

function vf_addJevLine(d = null) {
    const lineId = ++vf_jevSeq;
    const tr = document.createElement('tr');
    tr.id = `vf-jev-row-${lineId}`;
    tr.className = 'vje-line';
    tr.innerHTML = `
        <td><div class="vje-acct-wrap"><input type="text" class="vje-acct-code" placeholder="Search GL account code (UACS)…"><span class="vje-acct-title"><span></span></span></div><div class="vje-sl-hint"></div></td>
        <td><input type="text" class="vje-line-desc" placeholder="Particular"></td>
        <td><input type="text" inputmode="decimal" class="vje-debit" placeholder="0.00"></td>
        <td><input type="text" inputmode="decimal" class="vje-credit" placeholder="0.00"></td>
        <td><button type="button" class="vf-table-remove" title="Remove line" onclick="vf_removeJevLine(${lineId})">&times;</button></td>`;
    const acctInput = tr.querySelector('.vje-acct-code');
    acctInput.value = d?.account_code ?? '';
    tr.querySelector('.vje-line-desc').value = d?.description ?? '';
    tr.querySelector('.vje-debit').value = Number(d?.debit) > 0 ? vf_fmtMoneyVal(d.debit) : '';
    tr.querySelector('.vje-credit').value = Number(d?.credit) > 0 ? vf_fmtMoneyVal(d.credit) : '';
    nhaSearchableSelect(acctInput, vf_coaOptions.map(a => ({ value: a.account_code, label: a.account_title })), () => vf_renderAcctHint(tr));
    acctInput.addEventListener('input', () => vf_renderAcctHint(tr));
    vf_renderAcctHint(tr);
    vf_bindMoneyInput(tr.querySelector('.vje-debit'));
    vf_bindMoneyInput(tr.querySelector('.vje-credit'));
    tr.querySelector('.vje-debit').addEventListener('input', vf_recalcJev);
    tr.querySelector('.vje-credit').addEventListener('input', vf_recalcJev);
    $vf('vf-jev-tbody').appendChild(tr);
    vf_recalcJev();
    return lineId;
}

/** A row's sub-hint under its Account Code — General Form No. 1 convention: the claimant/vendor
    name prints in red when this row IS the DV's own primary expense/claim account (same
    readone.php's vje_groupSubLines() uses for the posted JEV), each matching ATC withholding
    row's rate/type prints in plain black when this row is a "Due to ..." withholding account,
    and otherwise — when the picked account is itself a chart_of_accounts child row
    (parent_account_id set, a vendor/SL sub-account; SL is no longer a separate sl_masters table,
    see "03 - alter_table/37") — shows that account's own code labeled "SL Code:", same as the
    sub-line readone.php's vje_groupSubLines() shows under its PARENT control account header. */
function vf_renderAcctHint(tr) {
    vf_renderAcctTitle(tr); // the title in the field itself, whatever hint (if any) follows below it
    const code = tr.querySelector('.vje-acct-code').value.trim();
    const hint = tr.querySelector('.vje-sl-hint');
    if (!code) { hint.innerHTML = ''; return; }

    if (code === $vf('vf-account-code').value.trim()) {
        const name = $vf('vf-claimant').value.trim();
        if (name) { hint.innerHTML = `<span style="color:#D32F2F;">${vf_esc(name)}</span>`; return; }
    }

    const atcMatches = [];
    vf_atcRows().forEach(atcTr => {
        const def = VF_ATC.find(c => c.atc_code === atcTr.querySelector('.vf-atc-code').value);
        if (def && def.account_code === code) {
            const rate = atcTr.querySelector('.vf-atc-rate').value || def.tax_rate || 0;
            atcMatches.push(`<span style="display:inline-block;width:36px;">${vf_esc(rate)}%</span>${vf_esc(def.description || def.atc_code)}`);
        }
    });
    if (atcMatches.length) { hint.innerHTML = atcMatches.join('<br>'); return; }

    const acct = vf_coaOptions.find(a => a.account_code === code);
    if (acct?.parent_account_id) {
        hint.innerHTML = `<span style="margin-right:3px;">SL Code:</span>${vf_codeChip(acct.account_code)}`; // the account's title is already in the field above
        return;
    }
    hint.innerHTML = '';
}

/** The picked account's title, inside the Account Code field right after the code — "1-04-01-010 - Due to BIR". The input still
    holds only the code (the save and every calculation read it as the code); the title is a click-through overlay whose left edge
    is the end of the typed text, measured with the input's own font. Called from vf_renderAcctHint(), which already runs whenever
    a row's code changes (pick, typing, auto lines, saved DV load). */
function vf_renderAcctTitle(tr) {
    const input = tr.querySelector('.vje-acct-code');
    const box   = tr.querySelector('.vje-acct-title');
    if (!input || !box) return;
    const code = input.value.trim();
    const acct = code ? vf_coaOptions.find(a => a.account_code === code) : null;
    if (!acct?.account_title) { box.style.display = 'none'; return; }
    const cs = getComputedStyle(input);
    if (!vf_renderAcctTitle.ctx) vf_renderAcctTitle.ctx = document.createElement('canvas').getContext('2d');
    const ctx = vf_renderAcctTitle.ctx;
    ctx.font = `${cs.fontStyle} ${cs.fontWeight} ${cs.fontSize} ${cs.fontFamily}`;
    const start = (parseFloat(cs.paddingLeft) || 0) + (parseFloat(cs.borderLeftWidth) || 0) + ctx.measureText(input.value).width;
    box.style.left = (start + 5) + 'px';
    box.firstElementChild.textContent = '- ' + acct.account_title;
    box.title = code + ' - ' + acct.account_title;
    box.style.display = 'flex';
}

/** Re-renders every Accounting Entries row's sub-hint (vf_renderAcctHint()) — called whenever
    something its hint depends on changes elsewhere: the Claimant Name, the Budget Reference
    Account Code, or the ATC grid, not just the Account Code field each hint is actually attached to. */
function vf_refreshAllJevHints() {
    $vf('vf-jev-tbody').querySelectorAll('tr.vje-line').forEach(vf_renderAcctHint);
}

/** Account code rendered as boxed segments (e.g. "2 01 01 010 02") — same visual convention as
    the Chart of Accounts page's own code chip (chart-of-accounts-page.php's coa_codeChip()). */
function vf_codeChip(code) {
    const parts = String(code ?? '').split('-').filter(p => p !== '');
    if (!parts.length) return '';
    const segs = parts.map((p, i) => {
        const bg     = i === 0 ? '#EEF1F7' : '#EAF1FE';
        const fg     = i === 0 ? '#1A1B21' : '#2F5FD6';
        const border = i > 0 ? 'border-left:1px solid rgba(15,32,69,.08);' : '';
        return `<span style="background:${bg};color:${fg};padding:2px 6px;font-size:11px;font-weight:700;font-family:'IBM Plex Mono',monospace;${border}">${vf_esc(p)}</span>`;
    }).join('');
    return `<span style="display:inline-flex;border:1px solid var(--afims-border, #E4ECF2);border-radius:5px;overflow:hidden;vertical-align:middle;">${segs}</span>`;
}

function vf_removeJevLine(lineId) {
    const tr = $vf(`vf-jev-row-${lineId}`);
    if (tr) {
        tr.remove();
        vf_recalcJev();
    }
}

function vf_collectJevLines() {
    const lines = [];
    $vf('vf-jev-tbody').querySelectorAll('tr.vje-line').forEach(tr => {
        const account_code = tr.querySelector('.vje-acct-code').value.trim();
        const debit = vf_numVal(tr.querySelector('.vje-debit').value);
        const credit = vf_numVal(tr.querySelector('.vje-credit').value);
        const description = tr.querySelector('.vje-line-desc').value.trim();
        if (!account_code && debit <= 0 && credit <= 0 && !description) return;
        lines.push({ account_code, debit, credit, description: description || null });
    });
    return lines;
}

function vf_recalcJev() {
    vf_refreshAllJevHints(); // an account code can be filled in programmatically (auto lines, saved DV) — keep each row's title in step
    const lines = vf_collectJevLines();
    const totalDebit = lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
    $vf('vf-jev-total-debit').textContent = vf_peso(totalDebit);
    $vf('vf-jev-total-credit').textContent = vf_peso(totalCredit);
    const msg = $vf('vf-jev-balance-msg');
    if (lines.length === 0) {
        msg.textContent = '';
    } else {
        const balanced = lines.length >= 2 && totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.005;
        msg.textContent = balanced ? '' : 'Not balanced — debits must equal credits'; // nothing to say when it balances
        msg.style.color = balanced ? 'var(--afims-success, #16A34A)' : 'var(--afims-danger, #DC3545)';
    }
}

/* ── Live computation ──────────────────────────────────────── */
function vf_recalc() {
    const gross = vf_numVal($vf('vf-gross').value);
    // Gross was set by something other than the Amount cells (RFA picked, payroll/remittance fill, saved DV
    // loaded) — rebuild the breakdown so its rows follow it.
    if (Math.abs(gross - vf_renderedGross) > 0.005) vf_renderRfaBreakdown();
    const vatRow = vf_vatRow();
    if (vatRow) vf_syncVat(vatRow); // VAT / Tax Base / Withholding VAT follow Gross (and re-base the ATC rows if Gross changed)
    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        if (row.dataset.vat === '1') return; // handled by vf_syncVat() above — its rate applies to the Tax Base, not Gross
        // A rate-based deduction follows Gross (the server recomputes rate% x gross on save, so the form must too);
        // a flat peso deduction (no rate) is left as typed.
        const rate = parseFloat(row.querySelector('.vf-ded-rate').value);
        if (!isNaN(rate) && rate > 0) {
            row.querySelector('.vf-ded-amount').value = vf_fmtMoneyVal(vf_pctOf(gross, rate));
        }
    });

    const totalDeductions = vf_totalDeductions();
    vf_syncAtcBases(); // the base the ATC rows split may just have moved (Gross, VAT type, a deduction) — before their withholding is summed
    // Withholding tax (ATC) is taken off too — same Gross - Deductions - Withholding the server stores as net_amount.
    // Summed in centavos: adding the per-row floats directly can leave a stray 0.0000000001 that shows up as a cent.
    const totalWithholding = vf_collectAtcLines().reduce((s, l) => s + Math.round(vf_pctOf(l.tax_base, l.rate) * 100), 0) / 100;
    const netAmount = Math.max(0, gross - totalDeductions - totalWithholding);
    $vf('vf-doc-gross').textContent    = vf_peso(gross);
    $vf('vf-doc-less-ded').textContent = vf_peso(totalDeductions);
    $vf('vf-doc-less-wh').textContent  = vf_peso(totalWithholding);
    $vf('vf-net').textContent = vf_peso(netAmount);
    const netVis = $vf('vf-net-vis'); if (netVis) netVis.textContent = vf_peso(netAmount);
    vf_syncAutoJevLines(); // gross/deductions just changed — refresh the hidden Accounting Entries grid's totals

    const obligation    = vf_numVal($vf('vf-obligation').value);
    const totalPayments = vf_paymentsPrior + netAmount;
    const balance       = obligation - totalPayments;

    $vf('vf-payments').textContent       = vf_peso(vf_paymentsPrior);
    $vf('vf-this-request').textContent   = vf_peso(netAmount);
    $vf('vf-total-payments').textContent = vf_peso(totalPayments);
    $vf('vf-balance').textContent        = vf_peso(balance);
}

/* ── Payroll DV ────────────────────────────────────────────────
   No separate picker: the imported payroll months (Payroll Management) are listed in the Vendor dropdown's
   "Payroll" group (value "payroll:{payroll_period_id}"), since a payroll month is who this DV pays.
   GET payroll-management/available (months no other live DV uses, plus this DV's own) and
   GET payroll-management/dv-data/{id} (claimant, particulars, account, gross, deductions, accounting entry).
   Picking one fills the DV; picking None or a vendor afterwards clears everything it filled. */
const VF_PAYROLL_PREFIX = 'payroll:';
function vf_isPayrollValue(v) { return String(v || '').startsWith(VF_PAYROLL_PREFIX); }

async function vf_loadPayrollMonths() {
    const json = await vf_getJson('payroll-management/available' + (vf_id ? '?dv_id=' + vf_id : ''));
    vf_payrollMonths = json.data ?? [];
}

function vf_renderPayrollOptions() {
    const sel = $vf('vf-vendor');
    sel.querySelector('optgroup[data-payroll]')?.remove();
    if (!vf_payrollMonths.length) return;
    sel.insertAdjacentHTML('beforeend', '<optgroup data-payroll label="Payroll (imported months)">' + vf_payrollMonths.map(p =>
        `<option value="${VF_PAYROLL_PREFIX}${vf_esc(p.payroll_period_id)}">Payroll — ${vf_esc(p.label)} (${vf_esc(p.employee_count)} employees, gross ${vf_peso(p.total_gross)})</option>`).join('') + '</optgroup>');
}

/** Shows the payroll month in the vendor card (Name / Type / Address / TIN slots). */
function vf_showPayrollCard(d) {
    $vf('vf-vc-name').textContent    = d.claimant_name || 'Officers and Employees of NHA';
    $vf('vf-vc-type').textContent    = `Payroll — ${d.label}`;
    $vf('vf-vc-address').textContent = d.gross_amount !== undefined
        ? `${d.employee_count} employees · gross ${vf_peso(d.gross_amount)} · deductions ${vf_peso(d.total_deductions)} · net ${vf_peso(d.net_amount)}`
        : `${d.employee_count} employees · gross ${vf_peso(d.total_gross)} · net ${vf_peso(d.total_net)}`;
    $vf('vf-vc-tin').textContent     = (d.warnings ?? []).join(' ') || '—';
    $vf('vf-vendor-card').classList.add('visible');
}

async function vf_applyPayroll(id) {
    window.PagePreloader?.show();
    try {
        // read the body on a 4xx too — its message says why (e.g. an item without an account)
        const res = await vf_authFetch(VF_API + '/' + 'payroll-management/dv-data/' + id);
        if (res.status === 401) throw new Error(VF_SESSION_EXPIRED_MSG);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) throw new Error(json.message || 'HTTP ' + res.status);
        const d = json.data;
        if (vf_payrollPeriodId) vf_clearPayrollFill(); // switching from one month to another
        vf_payrollPeriodId = Number(d.payroll_period_id);
        vf_renderDedTypeButtons();

        // payroll RFAs carry no vendor — drop RFAs picked for a vendor
        if (vf_selectedRfaIds.some(rid => vf_rfas[rid]?.vendor_id)) {
            vf_selectedRfaIds = [];
            vf_renderRfaChips();
        }
        $vf('vf-claimant').value         = d.claimant_name;
        $vf('vf-claimant-address').value = '';
        $vf('vf-claimant-tin').value     = '';
        $vf('vf-particulars').value      = d.particulars;
        $vf('vf-account-code').value     = d.account_code || '';
        $vf('vf-gross').value            = vf_fmtMoneyVal(d.gross_amount);
        ['claimant', 'particulars', 'gross', 'account-code'].forEach(vf_clearFieldError);

        vf_clearDeductionRows();
        vf_dedSeq = 0;
        $vf('vf-jev-tbody').innerHTML = '';
        vf_jevSeq = 0;
        (d.deductions ?? []).forEach(x => vf_addDeductionLine(x));
        (d.lines ?? []).forEach(l => vf_addJevLine(l));
        vf_recalc();
        vf_refreshAllJevHints();
        vf_showPayrollCard(d);
        if ($vf('vf-rfa-dropdown').classList.contains('open')) vf_renderRfaDropdown();
    } catch (e) {
        if (vf_payrollPeriodId) vf_clearPayrollFill();
        $vf('vf-vendor').value = '';
        $vf('vf-vendor-card').classList.remove('visible');
        vf_showErrors([e.message]);
    } finally {
        window.PagePreloader?.hide();
    }
}

/**
 * The GSD notification of a Delivery Acceptance (Voucher?po={po_number}): opens a new DV and picks the approved RFA that carries that PO —
 * vf_onRfaSelectionChange() then fills the PO Number, the Vendor (Supplier / Contractor), the Dept/Project, the amount and the particulars,
 * exactly as when the RFA is picked by hand.
 */
async function vf_openForPo(poNumber) {
    await vf_open(0);
    const po  = String(poNumber).trim().toLowerCase();
    const rfa = Object.values(vf_rfas).find(r => String(r.po_number || '').trim().toLowerCase() === po);
    if (!rfa) {
        vf_showErrors([`No approved RFA with PO ${poNumber} is available for a new DV — it may already have a Disbursement Voucher.`]);
        return;
    }
    vf_selectRfa(rfa.allocation_id);                                  // clears the search box and loads the RFA's details…
    $vf('vf-rfa-search').value = $vf('vf-rfa-search').dataset.auto = rfa.allocation_number || '';   // …so show which RFA it picked in the box too (the chip below holds the selection; vf_renderRfaDropdown() ignores this text)
}

/** Payroll Management's "Create DV" (Voucher?payroll={id}): a new DV with that imported month already picked. */
async function vf_openForPayroll(periodId) {
    await vf_open(0);
    const sel = $vf('vf-vendor');
    const value = VF_PAYROLL_PREFIX + Number(periodId);
    if (![...sel.options].some(o => o.value === value)) {
        vf_showErrors(['That payroll month is not available for a new DV — it has no imported records or another Disbursement Voucher already uses it.']);
        return;
    }
    sel.value = value;
    await vf_onVendorChange();
}

/**
 * Deductions & Remittances' "Create Remittance DV" (Voucher?remittance={id}): a new DV paying a scheduled payroll
 * remittance to its agency — GET deduction-remittance/dv-data/{id}: claimant = the agency, particulars, account, amount,
 * and the entry (Dr the payables / gov't share, Cr Accounts Payable). No RFA: the payroll DV already obligated it.
 */
async function vf_openForRemittance(remittanceId) {
    await vf_open(0);
    window.PagePreloader?.show();
    try {
        const res = await vf_authFetch(VF_API + '/' + 'deduction-remittance/dv-data/' + Number(remittanceId));
        if (res.status === 401) throw new Error(VF_SESSION_EXPIRED_MSG);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) throw new Error(json.message || 'HTTP ' + res.status);
        vf_applyRemittance(json.data);
    } catch (e) {
        vf_showErrors([e.message]);
    } finally {
        window.PagePreloader?.hide();
    }
}

/**
 * Remittance List's "Remit" (Voucher?remit_period=YYYY-MM&remit_type={EWT|VAT|PENALTY_TAX}): a new DV paying to BIR what the month's released
 * vouchers withheld — GET remittance-list/dv-data/{period}/{type}: claimant = BIR, the particulars ("Remittance of Expanded Withholding Taxes
 * withheld for the month of …" + the rate breakdown), account, amount (= the month's total) and the entry (Dr Due to BIR / VAT Payable, Cr Cash
 * in Bank). No RFA: the vouchers already obligated it. The rate rows and the voucher-by-voucher schedule are listed under the claimant.
 */
async function vf_openForDvRemit(period, type) {
    await vf_open(0);
    await vf_loadDvRemit(period, type, '');
}

/** The vendor select of a new Remittance DV: refills the form for another vendor of the month (one vendor per DV). */
function vf_remitVendorChanged(idx) {
    const v = vf_dvRemitVendors[Number(idx)];
    if (v && vf_dvRemit) vf_loadDvRemit(vf_dvRemit.period, vf_dvRemit.type, v.vendor);
}

/** Fills the (already open) new-DV form with a month's remittance for one vendor ('' = the vendor with the most withheld). */
async function vf_loadDvRemit(period, type, vendor) {
    window.PagePreloader?.show();
    try {
        const res = await vf_authFetch(VF_API + '/' + 'remittance-list/dv-data/' + encodeURIComponent(period) + '/' + encodeURIComponent(type) + (vendor ? '?vendor=' + encodeURIComponent(vendor) : ''));
        if (res.status === 401) throw new Error(VF_SESSION_EXPIRED_MSG);
        const json = await res.json().catch(() => ({}));
        if (!res.ok || !json.success) throw new Error(json.message || 'HTTP ' + res.status);
        vf_applyDvRemit(json.data);
    } catch (e) {
        vf_showErrors([e.message]);
    } finally {
        window.PagePreloader?.hide();
    }
}

/** Card / field wording of a month's remittance DV, in the shape vf_fillRemittance() reads. */
function vf_dvRemitView(d) {
    const n = Number(d.vouchers) || 0;
    return {
        ...d,
        dvRemit: true,
        label: `${d.type_name} — ${d.period_label}`,
        card_type: `Remittance to BIR — ${d.type_name}`,
        card_detail: `For the month of ${d.period_label}${d.remit_vendor ? ' · ' + d.remit_vendor : ''} · ${n} voucher${n === 1 ? '' : 's'} · Total ${vf_peso(d.gross_amount)}`,
        card_ref: 'Pays what the released vouchers of the month withheld — no RFA needed on this DV',
        option_label: `Remittance to BIR — ${d.type_name}, ${d.period_label}`,
        rfa_note: `(not needed — remitting what the ${d.period_label} vouchers withheld)`,
        rfa_placeholder: 'Not needed for a remittance of withheld tax',
    };
}

function vf_applyDvRemit(d) {
    vf_dvRemit = { period: d.remit_period, type: d.remit_type, vendor: d.remit_vendor || '' };
    vf_dvRemitVendors = d.vendors || [];
    vf_fillRemittance(vf_dvRemitView(d));
    vf_renderRemitSchedule(d);
    $vf('vf-particulars').rows = Math.min(10, Math.max(3, String(d.particulars || '').split('\n').length));
}

/**
 * Under the claimant of a monthly remittance DV: the paper form's rate rows (1% … 15% Withholding Tax, each with the Contractors/Suppliers — the
 * vendors — it was withheld from and their amounts) and the voucher-by-voucher schedule behind them. null hides it.
 */
function vf_renderRemitSchedule(d) {
    const box = $vf('vf-remit-sched');
    const vbox = $vf('vf-remit-vendor-box');
    if (!box) return;
    if (!d) {
        box.style.display = 'none'; box.innerHTML = '';
        if (vbox) { vbox.style.display = 'none'; vbox.innerHTML = ''; }
        return;
    }
    const oneVendor = !!d.remit_vendor;   // a DV pays one vendor, so naming that vendor on every row would only repeat it
    // the vendor lives in section A (who this DV is for): pick it on a new DV, read-only once saved
    if (vbox) {
        if ((d.vendors || []).length) {
            const cur = Math.max(0, vf_dvRemitVendors.findIndex(v => v.vendor === d.remit_vendor));
            vbox.innerHTML = `<div class="nha-fg"><label>Vendor the tax was withheld from <span class="nha-req">*</span></label>
                <select id="vf-remit-vendor" onchange="vf_remitVendorChanged(this.value)">${vf_dvRemitVendors.map((v, i) =>
                    `<option value="${i}"${i === cur ? ' selected' : ''}>${vf_esc(v.vendor)} — ${vf_peso(v.amount)} (${Number(v.vouchers) || 0} voucher${Number(v.vouchers) === 1 ? '' : 's'})</option>`).join('')}</select>
                <span class="vf-hint">One vendor per remittance DV. The other vendors stay ready to remit.</span></div>`;
        } else if (d.remit_vendor) {
            vbox.innerHTML = `<div class="nha-fg"><label>Vendor the tax was withheld from</label><div style="padding:8px 0;font-weight:600;">${vf_esc(d.remit_vendor)}</div></div>`;
        } else {
            vbox.innerHTML = '';
        }
        vbox.style.display = vbox.innerHTML ? '' : 'none';
    }

    const money = v => Number(v || 0) > 0 ? vf_peso(v) : '-';
    // 1) the amount: one row per rate that has something (empty rates are left out), the vendors only when they are not all the same one
    const rows = (d.breakdown || []).filter(b => Number(b.amount) > 0).map(b => `<tr class="rate"><td>${vf_esc(b.label)}</td>${oneVendor ? '' : '<td></td>'}<td class="r">${money(b.amount)}</td></tr>` +
        (oneVendor ? '' : (b.vendors || []).map(v => `<tr class="ven"><td></td><td>${vf_esc(v.vendor)}</td><td class="r">${vf_peso(v.amount)}</td></tr>`).join(''))).join('');
    // 2) the accounting entry the DV posts (Dr the liability, Cr Cash in Bank) — section D is Accounting's, so it is only shown here, folded
    const lines = d.lines || [];
    const ent = lines.map(l => `<tr><td class="m">${vf_esc(l.account_code)}</td><td>${vf_esc(l.account_title || '')}</td>
        <td class="r">${Number(l.debit) > 0 ? vf_peso(l.debit) : ''}</td><td class="r">${Number(l.credit) > 0 ? vf_peso(l.credit) : ''}</td></tr>`).join('');
    const names = side => lines.filter(l => Number(l[side]) > 0).map(l => l.account_title || l.account_code).join(', ');
    // 3) the vouchers this DV pays: each source DV as a row, its ATC lines as its children — folded too
    const groups = [];
    (d.schedule || []).forEach(sc => {
        let g = groups.find(x => x.dv_id === sc.dv_id);
        if (!g) { g = { dv_id: sc.dv_id, dv_no: sc.dv_no, dv_date: sc.dv_date, vendor: sc.claimant_name, total: 0, lines: [] }; groups.push(g); }
        g.total += Number(sc.amount || 0);
        g.lines.push(sc);
    });
    const sched = groups.map(g => `<tr class="dv"><td class="m">${vf_esc(g.dv_no)}</td><td>${vf_esc(String(g.dv_date || '').slice(0, 10))}</td>${oneVendor ? '' : `<td>${vf_esc(g.vendor)}</td>`}<td></td><td></td><td class="r">${vf_peso(g.total)}</td></tr>` +
        g.lines.map(sc => `<tr class="atc"><td class="m">↳ ${vf_esc(sc.atc_code || d.type_name)}</td><td></td>${oneVendor ? '' : '<td></td>'}<td class="r">${sc.rate === null || sc.rate === undefined ? '' : vf_esc(Number(sc.rate) + '%')}</td><td class="r">${vf_peso(sc.base)}</td><td class="r">${vf_peso(sc.amount)}</td></tr>`).join('')).join('');
    const nv = Number(d.vouchers) || 0, nl = (d.schedule || []).length;

    box.innerHTML = `<div class="vf-remit-total"><span>Total to remit — ${vf_esc(d.type_name)}, ${vf_esc(d.period_label)}</span><strong>${vf_peso(d.gross_amount)}</strong></div>
        <table class="vf-rs-tbl"><thead><tr><th>Rate</th>${oneVendor ? '' : '<th>Contractors/Suppliers</th>'}<th class="r">Amount</th></tr></thead><tbody>${rows}</tbody></table>
        <details><summary>Vouchers included — ${nv} voucher${nv === 1 ? '' : 's'}, ${nl} line${nl === 1 ? '' : 's'}</summary>
        <div style="overflow-x:auto;"><table class="vf-rs-tbl"><thead><tr><th>DV No. / ATC</th><th>Date</th>${oneVendor ? '' : '<th>Vendor</th>'}<th class="r">Rate</th><th class="r">Tax Base</th><th class="r">Withheld</th></tr></thead><tbody>${sched}</tbody></table></div></details>
        <details><summary>Accounting entry — Dr ${vf_esc(names('debit'))} / Cr ${vf_esc(names('credit'))}</summary>
        <div style="overflow-x:auto;"><table class="vf-rs-tbl"><thead><tr><th>Account code</th><th>Account title</th><th class="r">Debit</th><th class="r">Credit</th></tr></thead><tbody>${ent}</tbody></table></div></details>`;
    box.classList.add('vf-remit-main');
    box.style.display = '';
}

function vf_applyRemittance(d) {
    vf_remittanceId = Number(d.payroll_remittance_id);
    vf_fillRemittance(d);
}

/** Fills the form from a remittance (payroll month or a voucher's withheld tax): claimant, particulars, amount, entry; no RFA. */
function vf_fillRemittance(d) {
    vf_selectedRfaIds = [];
    vf_renderRfaChips();
    $vf('vf-rfa-search').disabled = true;
    $vf('vf-vendor').value = '';
    $vf('vf-claimant').value         = d.claimant_name;
    $vf('vf-claimant-address').value = '';
    $vf('vf-claimant-tin').value     = '';
    $vf('vf-particulars').value      = d.particulars;
    $vf('vf-account-code').value     = d.account_code || '';
    $vf('vf-gross').value            = vf_fmtMoneyVal(d.gross_amount);
    ['claimant', 'particulars', 'gross', 'account-code', 'rfa'].forEach(vf_clearFieldError);
    vf_clearDeductionRows();
    vf_dedSeq = 0;
    $vf('vf-jev-tbody').innerHTML = '';
    vf_jevSeq = 0;
    (d.lines ?? []).forEach(l => vf_addJevLine(l));
    vf_recalc();
    vf_refreshAllJevHints();
    vf_showRemittanceCard(d);
    vf_markRemittanceFields(d);
}

/**
 * A remittance DV has no vendor (the claimant is the agency, filled in) and needs no RFA (budgeted with the payroll):
 * say so in those two fields instead of leaving "— None —" and a required, empty RFA. null puts them back.
 */
function vf_markRemittanceFields(d) {
    const sel = $vf('vf-vendor');
    sel.querySelector('option[data-remittance]')?.remove();
    if (d) {
        const label = d.option_label || `Payroll Remittance — ${d.agency_name || ''} ${d.label || ''}`.trim();
        sel.insertAdjacentHTML('afterbegin', `<option value="" data-remittance="1">${vf_esc(label)}</option>`);
        sel.value = '';
        sel.selectedIndex = 0;
    }
    sel.disabled = !!d;
    // A remittance DV hides what it never uses (all filled in or not applicable):
    //   Vendor / PO Number row · claimant Address / Employee No. / TIN (the claimant is the agency) · the RFA column ·
    //   the Deductions + ATC card · the Budget Reference fields and figures (Dept./Project, Fund Source, Account Code —
    //   set from the remittance —, Budget / Obligation / Payments / Balance, which come from an RFA). Remarks stays.
    const show = el => { if (el) el.style.display = d ? 'none' : ''; };
    show(sel.closest('.nha-fg'));
    show($vf('vf-po-number').closest('.nha-fg'));
    show($vf('vf-claimant-tin').closest('.nha-fg'));
    show($vf('vf-claimant-address').closest('.nha-fg'));
    show($vf('vf-rfa-col'));
    show($vf('vf-ded-section')); // the Deductions + ATC rows and pills (the RFA Breakdown box above them stays: it is empty without an RFA)
    // layout: no RFA column, so section A spans the whole card; a month's remittance DV (dvRemit) also drops what only repeats the figures
    // (the vendor card, the second Status cell, the empty RFA breakdown box and the Gross / Less / Net table — the amount is shown once, in section B)
    $vf('vf-claimant-grid').classList.toggle('vf-remit-on', !!d);
    const dr = !!(d && d.dvRemit);
    $vf('vf-claimant-title').textContent = d ? 'Claimant' : 'Claimant & RFA';
    $vf('vf-doc-status-td').style.display = dr ? 'none' : '';
    
    $vf('vf-dv-date').style.maxWidth = dr ? '260px' : '';
    $vf('vf-sec-b-hdr').textContent = dr ? 'B. Amount to Remit' : 'B. Amount and Deductions';
    $vf('vf-rfa-breakdown').style.display = dr ? 'none' : '';
    $vf('vf-sec-b-note').style.display = dr ? 'none' : '';
    if (dr) $vf('vf-vendor-card').classList.remove('visible');
    if (!d) $vf('vf-remit-sched').classList.remove('vf-remit-main');
    $vf('vf-rfa-req').style.display = d ? 'none' : '';
    $vf('vf-rfa-note').textContent  = d ? (d.rfa_note || '(not needed — payroll remittance, budgeted with the payroll)') : '(search and select one or more)';
    $vf('vf-rfa-search').placeholder = d ? (d.rfa_placeholder || 'Not needed for a payroll remittance') : 'Search RFA number or reference no…';
}

/** The vendor card shows the remittance (agency, payroll month, employee / government share, no RFA needed). */
function vf_showRemittanceCard(d) {
    $vf('vf-vc-name').textContent    = d.claimant_name || d.agency_name;
    $vf('vf-vc-type').textContent    = d.card_type || `Payroll Remittance — ${d.agency_name} ${d.label}`;
    $vf('vf-vc-address').textContent = d.card_detail ?? (d.employee_share !== undefined
        ? `Employee share ${vf_peso(d.employee_share)} · Government share ${vf_peso(d.employer_share)} · Total ${vf_peso(d.gross_amount)}`
        : `Total ${vf_peso(d.total_amount)}`);
    // for reference: the payroll DV / RFA this remittance belongs to (the remittance DV itself takes no RFA)
    const ref = d.payroll_dv_no ? `Belongs to payroll DV ${d.payroll_dv_no}${(d.payroll_rfa_nos || []).length ? ' · RFA ' + d.payroll_rfa_nos.join(', ') : ''}` : 'Payroll DV not created yet';
    $vf('vf-vc-tin').textContent     = d.card_ref ?? `${ref} — no RFA needed on this DV`;
    $vf('vf-vendor-card').classList.add('visible');
}

/** Undo vf_applyPayroll(): empties every field the payroll month filled, then auto Accounting Entries resume. */
function vf_clearPayrollFill() {
    vf_payrollPeriodId = null;
    vf_renderDedTypeButtons();
    ['vf-claimant', 'vf-claimant-address', 'vf-claimant-tin', 'vf-particulars', 'vf-account-code', 'vf-gross'].forEach(id => { $vf(id).value = ''; });
    vf_clearDeductionRows();
    vf_dedSeq = 0;
    $vf('vf-jev-tbody').innerHTML = '';
    vf_jevSeq = 0;
    $vf('vf-vendor-card').classList.remove('visible');
    vf_recalc();
}

/* ── Edit mode ─────────────────────────────────────────────── */
async function vf_loadExisting() {
    const json = await vf_getJson('voucher/read/' + vf_id);
    const dv   = json.data;
    if (!VF_EDITABLE.includes(dv.status)) {
        vf_showErrors([`This disbursement voucher is ${dv.status} and can no longer be edited.`]);
        $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = true;
    }

    $vf('vf-dv-number').textContent = dv.dv_no;
    $vf('vf-status').textContent    = $vf('vf-status-doc').textContent = dv.status;
    $vf('vf-dv-date').value         = String(dv.dv_date).slice(0, 10);
    $vf('vf-book').value            = dv.jev?.book_id || '';

    vf_selectedRfaIds = (dv.rfas ?? []).map(r => String(r.allocation_id));
    (dv.rfas ?? []).forEach(r => { vf_rfas[r.allocation_id] = r; });
    vf_renderRfaChips();

    // dv.saro — the SARO currently linked to this voucher (fims.fund_tracking.dv_id), if any; added
    // to the vf-saro select's options by vf_loadSaroOptions() (exclude_dv_id keeps it there even
    // though it's "taken" — by this same voucher).
    $vf('vf-saro').value = dv.saro ? String(dv.saro.id) : '';

    vf_attachments = (dv.file_attachments ?? []).map(a => ({
        document_name: a.file_meta?.document_name || 'Supporting Document',
        link: a.file_meta?.link || null,
        size: null,
    }));
    vf_attDirty = false;
    vf_existingMultipleNodeId = dv.multiple_node_id || null;
    vf_renderAttachments();

    if (dv.vendor_id && !vf_vendors[dv.vendor_id]) {
        vf_vendors[dv.vendor_id] = { vendor_id: dv.vendor_id, vendor_name: dv.claimant_name, address: dv.claimant_address, tin: dv.employee_no_tin };
        $vf('vf-vendor').insertAdjacentHTML('beforeend',
            `<option value="${vf_esc(dv.vendor_id)}">${vf_esc(dv.claimant_name)} (inactive)</option>`);
    }
    $vf('vf-vendor').value = dv.vendor_id || '';
    vf_onVendorChange();
    // A payroll DV: its month is shown in the Vendor dropdown's Payroll group (set after vf_onVendorChange(),
    // so restoring it doesn't refill or clear what was saved).
    if (dv.payroll) {
        vf_payrollPeriodId = Number(dv.payroll.payroll_period_id);
        vf_renderDedTypeButtons();
        $vf('vf-vendor').value = VF_PAYROLL_PREFIX + vf_payrollPeriodId;
        vf_showPayrollCard({ ...dv.payroll, claimant_name: dv.claimant_name });
    }
    // A payroll remittance DV (GSIS / BIR / …): keeps its remittance, no RFA
    if (dv.remittance) {
        vf_remittanceId = Number(dv.remittance.payroll_remittance_id);
        $vf('vf-rfa-search').disabled = true;
        vf_showRemittanceCard({ ...dv.remittance, claimant_name: dv.claimant_name });
        vf_markRemittanceFields(dv.remittance);
    }
    // A Remittance DV for a month's withheld EWT / VAT / Penalty Tax (Remittance List): keeps its month and tax, no RFA
    if (dv.dv_remit) {
        vf_dvRemit = { period: dv.dv_remit.remit_period, type: dv.dv_remit.remit_type, vendor: dv.dv_remit.remit_vendor || '' };
        vf_dvRemitVendors = [];
        $vf('vf-rfa-search').disabled = true;
        const view = vf_dvRemitView({ ...dv.dv_remit, claimant_name: dv.claimant_name });
        vf_showRemittanceCard(view);
        vf_markRemittanceFields(view);
        vf_renderRemitSchedule(dv.dv_remit);
    }

    $vf('vf-po-number').value        = dv.po_number || '';
    vf_checkPoIar();
    $vf('vf-claimant').value         = dv.claimant_name || '';
    $vf('vf-claimant-address').value = dv.claimant_address || '';
    $vf('vf-claimant-tin').value     = dv.employee_no_tin || '';
    $vf('vf-particulars').value      = dv.particulars || '';
    $vf('vf-gross').value            = dv.gross_amount ? vf_fmtMoneyVal(dv.gross_amount) : '';

    vf_clearDeductionRows();
    vf_dedSeq = 0;
    (dv.deductions ?? []).forEach(d => vf_addDeductionLine(d));

    vf_clearAtcRows();
    vf_atcSeq = 0;
    (dv.atc_lines ?? []).forEach(a => vf_addAtcLine(a));
    vf_recalcAtc();

    $vf('vf-jev-tbody').innerHTML = '';
    vf_jevSeq = 0;
    vf_jevGroupSeq = 0;
    // Already saved at least once — respect what was committed; auto-sync stays off for the rest
    // of this edit session even if Deductions/ATC/Account Code change afterward (same rule
    // readone.php's vje_init() uses). A DRAFT that never reached a saved Accounting Entry yet
    // keeps auto-syncing normally.
    vf_jevAutoMode = !(dv.jev?.lines ?? []).length;
    // Accounting Entries is a flat list of lines now (vf_addJevLine()) — the old grouped-by-account
    // helpers (vf_addJevGroup / vf_addJevGroupLine) no longer exist.
    (dv.jev?.lines ?? []).forEach(l => vf_addJevLine(l));
    vf_recalcJev();

    $vf('vf-dept-project').value = dv.dept_project || '';
    $vf('vf-fund-source').value  = dv.fund_source || '';
    $vf('vf-account-code').value = dv.account_code || '';
    $vf('vf-budget').value       = dv.budget_amount ? vf_fmtMoneyVal(dv.budget_amount) : '';
    $vf('vf-obligation').value   = dv.obligation_amount ? vf_fmtMoneyVal(dv.obligation_amount) : '';
    $vf('vf-remarks').value      = dv.remarks || '';

    // As last saved — same snapshot-not-live convention as budget_amount/obligation_amount above;
    // re-selecting an RFA chip (vf_onRfaSelectionChange) refreshes it live if needed.
    vf_paymentsPrior = parseFloat(dv.payments_prior_amount) || 0;
    $vf('vf-payments-hint').textContent = vf_paymentsPrior > 0 ? ' (prior draws on these RFAs)' : '';
}

/* ── Validation ────────────────────────────────────────────── */
function vf_setFieldError(field, show) {
    const err = $vf(`err-${field}`);
    if (err) err.style.display = show ? 'block' : 'none';
}
function vf_clearFieldError(field) { vf_setFieldError(field, false); }

function vf_validate() {
    const errors = [];
    const check = (ok, field, msg) => { vf_setFieldError(field, !ok); if (!ok) errors.push(msg); };

    check(!!$vf('vf-dv-date').value,   'dv-date',   'DV Date is required.');
    // No Book check here: section D (Book + Accounting Entries) is hidden from Originating, so Book
    // can't be picked here. Payroll/remittance DVs still send their pre-filled lines without one
    // (persistJournalEntry() allows that for this pre-fill path); Accounting picks the Book when it
    // saves the Journal Entry at the FOR_ACCOUNTING stage, where it is required.
    // a remittance DV pays deductions the payroll DV's RFA already obligated — no RFA of its own
    check(vf_remittanceId || vf_dvRemit || vf_selectedRfaIds.length > 0, 'rfa', 'At least one RFA is required.');
    check(!!$vf('vf-claimant').value.trim(), 'claimant', 'Payee Name is required.');
    check(!!$vf('vf-particulars').value.trim(), 'particulars', 'Particulars is required.');
    check((vf_numVal($vf('vf-gross').value)) > 0, 'gross', 'Amount must be greater than zero.');
    // Same ceiling the server enforces: a manual DV can't draw more than its RFA(s) have left (RFAs with no
    // known budget are ignored; payroll/remittance DVs take their amount from their own import).
    if (!vf_payrollPeriodId && !vf_remittanceId && !vf_dvRemit) {
        const known = vf_selectedRfaIds.map(id => vf_rfas[id]).filter(r => r && Number(r.budget_amount) > 0);
        const remaining = known.reduce((s, r) => s + (Number(r.remaining_balance) || 0), 0);
        if (known.length && vf_numVal($vf('vf-gross').value) - remaining > 0.005) {
            errors.push(`Amount (${vf_peso(vf_numVal($vf('vf-gross').value))}) cannot exceed the selected RFA(s)' remaining balance (${vf_peso(remaining)}).`);
        }
        // The Amount cells are editable per line, so one RFA could be overdrawn while the total still fits (the
        // other RFA's cells lowered to compensate). The per-line split isn't stored, so only the form can catch that.
        const perRfa = {};
        $vf('vf-rfa-breakdown-body').querySelectorAll('.vf-rfa-line-amt').forEach(inp => {
            const id = inp.dataset.key.split(':')[0];
            perRfa[id] = (perRfa[id] || 0) + vf_numVal(inp.value);
        });
        Object.entries(perRfa).forEach(([id, amt]) => {
            const r = vf_rfas[id];
            if (r && Number(r.budget_amount) > 0 && amt - (Number(r.remaining_balance) || 0) > 0.005) {
                errors.push(`${r.allocation_number}: its Amount lines total ${vf_peso(amt)}, more than its remaining balance (${vf_peso(r.remaining_balance)}).`);
            }
        });
    }
    check(!!$vf('vf-account-code').value.trim(), 'account-code', 'Account Code could not be determined from the selected RFA(s) or vendor — pick an RFA that has an account.');

    // ATC Tax Bases are a split of one base: with a VAT line they may not exceed the VAT-exclusive Tax Base,
    // otherwise not the DV's Amount (the server enforces the Amount ceiling too). Going above the net-of-deductions
    // default but staying within the Amount is flagged under the rows (vf_renderAtcSummary()) but allowed.
    if (vf_atcRows().length) {
        // A row with a code but no base left (e.g. a second row added once the first already holds the whole base).
        vf_collectAtcLines().forEach((l, i) => {
            if (l.atc_code && l.tax_base <= 0) errors.push(`ATC row ${i + 1} (${l.atc_code}) has no Tax Base — give it its share of the base, or remove the row.`);
        });
        const atcTotal = vf_atcBaseTotal();
        const ceiling  = vf_vatRow() ? vf_fullBase() : vf_numVal($vf('vf-gross').value);
        if (atcTotal - ceiling > 0.005) {
            errors.push(`ATC Tax Bases total ${vf_peso(atcTotal)}, more than the ${vf_vatRow() ? 'VAT-exclusive Tax Base' : 'Amount'} (${vf_peso(ceiling)}) — split the base between the ATC rows.`);
        }
    }

    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        const acctInput = row.querySelector('.vf-ded-acct');
        if (!acctInput) return; // this deduction type already has a default posting account
        const label = row.querySelector('.vf-ded-type-label')?.textContent || 'This deduction';
        if (!acctInput.value.trim()) errors.push(`${label} has no default posting account — pick one before saving.`);
    });

    return errors;
}

function vf_showErrors(list) {
    const ul = $vf('vf-errors-ul');
    ul.innerHTML = '';
    list.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
    $vf('vf-errors').classList.toggle('visible', list.length > 0);
    if (list.length) $vf('vf-errors').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── PO → IAR check ────────────────────────────────────────── */
/* Asks GET /voucher/po-iar whether the typed PO number has an IAR. Shows / hides the warning under the field and
   resolves true when the DV may proceed (blank PO, PO with an IAR, or the check itself failed — the server is the real
   gate, so a network hiccup here never blocks the form). */
async function vf_checkPoIar() {
    const warn = $vf('vf-po-warn');
    const po   = $vf('vf-po-number').value.trim();
    if (!po) { warn.style.display = 'none'; return true; }
    try {
        const res = await vf_authFetch(`${VF_API}/voucher/po-iar?po_number=${encodeURIComponent(po)}`);
        if (!res.ok) return true;
        const json = await res.json();
        if (!json.success) return true;
        if ($vf('vf-po-number').value.trim() !== po) return true; // typed on while waiting — a newer check is coming
        if (json.data.has_iar) { warn.style.display = 'none'; return true; }
        warn.textContent = `⚠ PO ${po} has no IAR yet. A disbursement voucher can't proceed until the IAR is recorded.`;
        warn.style.display = 'block';
        return false;
    } catch (e) {
        return true;
    }
}

/* ── Save ──────────────────────────────────────────────────── */
async function vf_save(status) {
    if (vf_saving) return;
    vf_showErrors([]);

    const errors = vf_validate();
    // A PO must have an IAR before a voucher can be prepared against it (the server refuses it too).
    if (!(await vf_checkPoIar())) {
        errors.push(`PO '${$vf('vf-po-number').value.trim()}' has no IAR (Inspection and Acceptance Report) yet — record the IAR first, or clear the PO Number.`);
    }
    if (errors.length) { vf_showErrors(errors); return; }

    const dvNumber = $vf('vf-dv-number').textContent.trim();
    const body = {
        dv_no              : vf_id || dvNumber === '—' ? undefined : dvNumber,
        dv_date            : $vf('vf-dv-date').value,
        fiscal_yr          : vf_derivedFiscalYear(),
        book_id            : $vf('vf-book').value || null,
        rfa_allocation_ids : vf_selectedRfaIds.map(Number),
        saro_id            : $vf('vf-saro').value || null,
        dept_project       : $vf('vf-dept-project').value.trim(),
        fund_source        : $vf('vf-fund-source').value.trim(),
        account_code       : $vf('vf-account-code').value.trim(),
        budget_amount      : vf_numVal($vf('vf-budget').value),
        obligation_amount  : vf_numVal($vf('vf-obligation').value),
        remarks            : $vf('vf-remarks').value.trim(),
        vendor_id          : vf_isPayrollValue($vf('vf-vendor').value) ? null : ($vf('vf-vendor').value || null),
        claimant_name      : $vf('vf-claimant').value.trim(),
        claimant_address   : $vf('vf-claimant-address').value.trim(),
        employee_no_tin    : $vf('vf-claimant-tin').value.trim(),
        po_number          : $vf('vf-po-number').value.trim(),
        particulars        : $vf('vf-particulars').value.trim(),
        gross_amount       : vf_numVal($vf('vf-gross').value),
        deductions         : vf_collectDeductions(),
        atc_lines          : vf_collectAtcLines(),
        lines              : vf_collectJevLines(),
        payroll_period_id  : vf_payrollPeriodId,
        payroll_remittance_id : vf_remittanceId,
        remit_period          : vf_dvRemit ? vf_dvRemit.period : null,
        remit_type            : vf_dvRemit ? vf_dvRemit.type : null,
        remit_vendor          : vf_dvRemit ? (vf_dvRemit.vendor || null) : null,
        status,
    };

    vf_saving = true;
    $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = true;
    window.PagePreloader?.show();
    let saved = null;
    try {
        const persistedNodeId = await vf_persistAttachments();
        body.multiple_node_id = persistedNodeId === undefined ? vf_existingMultipleNodeId : persistedNodeId;

        const res  = await vf_authFetch(vf_id ? `${VF_API}/voucher/update/${vf_id}` : `${VF_API}/voucher/add`, {
            method  : vf_id ? 'PUT' : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify(body),
        });
        if (res.status === 401) { vf_showErrors([VF_SESSION_EXPIRED_MSG]); return; }
        const json = await res.json();
        if (!json.success) {
            vf_showErrors(json.errors?.length ? json.errors : [json.message || 'Failed to save disbursement voucher.']);
            return;
        }
        saved = json;
    } catch (e) {
        vf_showErrors(['Could not save: ' + e.message]);
        return;
    } finally {
        vf_saving = false;
        window.PagePreloader?.hide();
        $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = false;
    }

    vf_close();
    if (typeof window.vf_onSaved === 'function') window.vf_onSaved(saved.data, saved.message);
}

/* ── Helpers ───────────────────────────────────────────────── */
function vf_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/* ── Comma-formatted amount inputs ──────────────────────────
   Amount/Budget/Obligation/Deduction Amount/Tax Base/Debit/Credit are all type="text" (not
   type="number" — the browser rejects a comma as an invalid number string), so they can show
   "300,000.00" instead of "300000.00". vf_numVal() strips the commas back out before any
   calculation reads the value; vf_fmtMoneyVal() re-adds them. Formatting only happens on blur (not
   on every keystroke) so the cursor doesn't jump while typing — on focus the field shows the plain
   editable number, same convention as a spreadsheet currency cell. */
function vf_numVal(v) {
    return parseFloat(String(v ?? '').replace(/,/g, '')) || 0;
}
function vf_fmtMoneyVal(n) {
    return Number(n || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
/* rate% of an amount, rounded to the centavo exactly like the server's round($base * $rate / 100, 2).
   Plain float math (Math.round(base * rate) / 100) lands a centavo off on exact half-centavo products
   (e.g. 83,680.05 x 10% -> 8,368.005): it ran ~0.1% of the time, so the on-screen net could differ from the saved
   one. Done in integer centavos x hundredths-of-a-percent instead, which is exact. */
function vf_pctOf(base, rate) {
    const cents = Math.round((Number(base) || 0) * 100), hundredths = Math.round((Number(rate) || 0) * 100);
    return Math.floor((cents * hundredths + 5000) / 10000) / 100;
}
/* Re-inserts thousands commas on every keystroke (not just on blur), preserving the caret —
   counts plain (non-comma) characters before the caret in the OLD value, reformats, then walks
   the NEW value forward until that many non-comma characters have been consumed again. Same
   technique as a typical money-input component (e.g. Stripe's). */
function vf_liveFormatMoney(el) {
    const cursor = el.selectionStart ?? el.value.length;
    let digitsBeforeCursor = 0;
    for (let i = 0; i < cursor; i++) {
        if (el.value[i] !== ',') digitsBeforeCursor++;
    }

    let clean = el.value.replace(/[^0-9.]/g, '');
    const firstDot = clean.indexOf('.');
    if (firstDot !== -1) {
        clean = clean.slice(0, firstDot + 1) + clean.slice(firstDot + 1).replace(/\./g, '');
    }
    let [intPart, decPart] = clean.split('.');
    intPart = (intPart || '').replace(/^0+(?=\d)/, '');
    const grouped = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    el.value = grouped + (decPart !== undefined ? '.' + decPart : '');

    let pos = 0, seen = 0;
    while (pos < el.value.length && seen < digitsBeforeCursor) {
        if (el.value[pos] !== ',') seen++;
        pos++;
    }
    el.setSelectionRange(pos, pos);
}
function vf_bindMoneyInput(el) {
    if (!el || el.dataset.vfMoneyBound) return;
    el.dataset.vfMoneyBound = '1';
    el.addEventListener('input', () => vf_liveFormatMoney(el));
    el.addEventListener('blur', () => {
        el.value = el.value.trim() === '' ? '' : vf_fmtMoneyVal(vf_numVal(el.value));
    });
}
function vf_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
