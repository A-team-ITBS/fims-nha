-- ============================================================================
-- Books of Accounts — General Ledger/Subsidiary Ledger/Journals/Trial Balance
-- need to scope "per book" (fims.books, "01 - table/41": Main Office, CISFA,
-- NGC, MPLC/PPMO, PPMO), not just per fiscal year. Nothing on the JEV/GL side
-- carried a book tag at all until now.
--
-- Book is an ACCOUNTING classification, not a DV-header property — it's
-- chosen on the Accounting Entries section of the DV form / readone.php (the
-- same place Account Code and SL Code are chosen), not alongside DV Date.
-- disbursement_vouchers deliberately carries no book_id column of its own —
-- an earlier version of this put it there and copied it down to the JEV, but
-- that made it look like an Originating-time decision when it's really
-- Accounting's call when they prepare the Journal Entry
-- (DisbursementVoucherService::persistJournalEntry()). Tagged at the same two
-- points fiscal_yr already is from the JEV down, copied forward rather than
-- looked up live each time, same "snapshot, not a live join" convention as
-- everything else on these tables:
--   - journal_entry_vouchers.book_id — set directly when Accounting (or an
--     Originating pre-fill) saves the Journal Entry Voucher lines — see
--     persistJournalEntry()'s $bookId parameter.
--   - general_ledger.book_id — copied from the JEV in
--     GeneralLedgerService::postJev() when it's posted, same stage/method
--     that already copies jev.fiscal_yr onto each posted line.
--   - official_receipts.book_id — official_receipts isn't linked to a
--     DV/JEV at all (see "01 - table/40"), so it carries its own book tag
--     directly, set when the receipt is recorded.
--
-- fims.checks/fims.ada_releases need no column of their own — CKDJ/ADADJ read
-- the book through the JEV they join to (jev.book_id via dv_number -> dv_id
-- -> jev — see fetchCkdj()/fetchAdadj()), same as they already read jev_no.
-- ============================================================================
ALTER TABLE fims.journal_entry_vouchers
ADD COLUMN IF NOT EXISTS book_id INTEGER REFERENCES fims.books(book_id);
CREATE INDEX IF NOT EXISTS idx_journal_entry_vouchers_book_id ON fims.journal_entry_vouchers (book_id);

ALTER TABLE fims.general_ledger
ADD COLUMN IF NOT EXISTS book_id INTEGER REFERENCES fims.books(book_id);
CREATE INDEX IF NOT EXISTS idx_general_ledger_book_id ON fims.general_ledger (book_id);

ALTER TABLE fims.official_receipts
ADD COLUMN IF NOT EXISTS book_id INTEGER REFERENCES fims.books(book_id);
CREATE INDEX IF NOT EXISTS idx_official_receipts_book_id ON fims.official_receipts (book_id);
