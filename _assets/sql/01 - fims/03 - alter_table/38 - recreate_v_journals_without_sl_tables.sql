-- RECREATE VIEW: fims.v_journals
-- Removed JOINs to dropped fims.sl_masters and fims.sl_details tables.
-- sl_code and sl_detail_id are kept as NULL for backward compatibility.

CREATE OR REPLACE VIEW fims.v_journals AS
SELECT
    g.gl_id,
    g.posting_date,
    j.jev_no,
    d.dv_no,
    g.account_code,
    c.account_title,
    g.sl_code,
    NULL::VARCHAR(100) AS sl_name,
    g.sl_detail_id,
    NULL::VARCHAR(255) AS sl_detail_name,
    g.rc_id,
    g.debit,
    g.credit,
    g.reference_no,
    g.description,
    g.reversed_gl_id,
    g.fiscal_yr,
    g.book_id,
    b.name AS book_name,
    g.source_module,
    g.doc_ref_no
FROM fims.general_ledger g
JOIN fims.journal_entry_vouchers j ON j.jev_id = g.jev_id
LEFT JOIN fims.disbursement_vouchers d ON d.dv_id = j.dv_id
JOIN fims.chart_of_accounts c ON c.account_code = g.account_code
LEFT JOIN fims.books b ON b.book_id = g.book_id
ORDER BY g.posting_date, g.gl_id;
