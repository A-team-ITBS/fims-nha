-- ============================================================================
-- JEV register view — one row per fims.journal_entry_vouchers header (not its
-- lines; see fims.v_journals in "05 - views/01" for the line-level General
-- Journal), with its DV, book, and both status columns' labels joined in.
-- Replaces the ad-hoc join DisbursementVoucherService::fetchJevList() used to
-- build inline — same "view over the raw table" convention as v_journals/
-- v_trial_balance, and the same reason: a JEV can now be SETUP or PAYMENT
-- (jev_type, "03 - alter_table/31") and carries its own lifecycle separate
-- from its DV's (jev_status via status_id, "03 - alter_table/29" — DRAFT ->
-- APPROVED -> POSTED, or REVERSED), neither of which the old inline query
-- exposed.
--
-- dv_id/dv_no/claimant_name/dv_status are still here (same as before) because
-- the JEV register's "View"/"Print" actions and search box key off the DV,
-- not the JEV, per journal-entry-voucher.php's own header comment. LEFT JOINed
-- (not INNER) as of "03 - alter_table/33" — a standalone JEV (ACCRUAL/
-- DEPRECIATION/PREPAYMENTS/CLOSING, prepared via the Add JEV form, no DV at
-- all) has a NULL dv_id and would otherwise disappear from this register
-- entirely.
--
-- jev_classification ("03 - alter_table/32" — REGULAR | ACCRUAL | DEPRECIATION
-- | PREPAYMENTS | CLOSING) is a separate, business-facing label from jev_type
-- (SETUP/PAYMENT, which is GL-posting mechanics) — both are exposed here.
-- ============================================================================
CREATE OR REPLACE VIEW fims.v_journal_entry_vouchers AS
SELECT
    j.jev_id,
    j.jev_no,
    j.jev_date,
    j.jev_type,
    j.source_module,
    j.doc_ref_no,
    j.fiscal_yr,
    j.book_id,
    b.name AS book_name,
    j.particulars,
    j.total_debit,
    j.total_credit,
    j.status_id AS jev_status_id,
    jst.name AS jev_status,
    j.posted_by,
    j.posted_at,
    j.created_by,
    j.created_at,
    dv.dv_id,
    dv.dv_no,
    dv.claimant_name,
    dv.status_id AS dv_status_id,
    dst.name AS dv_status,
    j.jev_classification
FROM fims.journal_entry_vouchers j
LEFT JOIN fims.disbursement_vouchers dv ON dv.dv_id = j.dv_id
LEFT JOIN fims.books b ON b.book_id = j.book_id
LEFT JOIN fims.settings_status jst ON jst.id = j.status_id
LEFT JOIN fims.settings_status dst ON dst.id = dv.status_id
ORDER BY j.created_at DESC, j.jev_id DESC;

COMMENT ON VIEW fims.v_journal_entry_vouchers IS 'Journal Entry Voucher register — one row per fims.journal_entry_vouchers header, joined with its DV/book/status labels. Read-only, same convention as fims.v_journals.';
