-- ============================================================================
-- Backfills book_id ("Book of Accounts" — see "01 - table/41" and
-- "03 - alter_table/30") onto the demo JEVs seeded in
-- "09 - gl_sl_demo_postings.sql" and "10 - ckdj_adadj_crdj_demo.sql", plus the
-- original DV-processing demo (26-11-0001). Book is set directly on
-- fims.journal_entry_vouchers (it's an accounting classification, not a
-- DV-header field — see persistJournalEntry()'s $bookId parameter), then
-- copied down to fims.general_ledger the same way GeneralLedgerService::
-- postJev() already copies it when posting for real.
--
-- Spread across three different books on purpose — MAIN-OFFICE, CISFA, NGC —
-- so switching the Book filter on General Ledger/Subsidiary Ledger/Journals/
-- Trial Balance actually changes what shows, rather than every demo row
-- sharing one book.
--
-- Plain UPDATEs, safe to re-run (idempotent by construction — each one just
-- re-sets the same value it already set).
-- ============================================================================

UPDATE fims.journal_entry_vouchers SET book_id = (SELECT book_id FROM fims.books WHERE code = 'MAIN-OFFICE')
WHERE jev_no IN ('JEV-26-11-0001', 'JEV-GLDEMO-0002');

UPDATE fims.journal_entry_vouchers SET book_id = (SELECT book_id FROM fims.books WHERE code = 'CISFA')
WHERE jev_no = 'JEV-GLDEMO-0001';

UPDATE fims.journal_entry_vouchers SET book_id = (SELECT book_id FROM fims.books WHERE code = 'NGC')
WHERE jev_no = 'JEV-GLDEMO-0003';

UPDATE fims.general_ledger gl
SET book_id = jev.book_id
FROM fims.journal_entry_vouchers jev
WHERE gl.jev_id = jev.jev_id AND jev.jev_no IN ('JEV-26-11-0001', 'JEV-GLDEMO-0001', 'JEV-GLDEMO-0002', 'JEV-GLDEMO-0003');

UPDATE fims.official_receipts SET book_id = (SELECT book_id FROM fims.books WHERE code = 'CISFA')
WHERE or_no = 'OR-2026-0001';
