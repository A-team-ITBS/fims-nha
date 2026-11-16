-- ============================================================================
-- Books of Accounts — NHA maintains separate books per office/program entity
-- (Main Office, CISFA, NGC, MPLC/PPMO, PPMO), not per fund-type cluster. This
-- replaces an earlier attempt at the same "Book" filter built on
-- fims.type_of_fund (a fund-cluster lookup — Trust Fund/GAA/NF/UF/Corporate
-- Receipt — that turned out to be the wrong dimension for this; type_of_fund
-- stays as-is for fims.budget_line_items, just no longer doubles as "Book"
-- here). See "03 - alter_table/31" for where book_id is actually used
-- (disbursement_vouchers -> journal_entry_vouchers -> general_ledger,
-- official_receipts).
-- ============================================================================
CREATE TABLE IF NOT EXISTS fims.books (
    book_id SERIAL PRIMARY KEY,
    code    VARCHAR(30) NOT NULL UNIQUE,
    name    VARCHAR(100) NOT NULL
);

COMMENT ON TABLE fims.books IS 'Books of Accounts — one per NHA office/program entity (Main Office, CISFA, NGC, MPLC/PPMO, PPMO); the "Book" scope on General Ledger/Subsidiary Ledger/Journals/Trial Balance.';

INSERT INTO fims.books (book_id, code, name) VALUES
    (1, 'MAIN-OFFICE', 'Main Office'),
    (2, 'CISFA', 'CISFA'),
    (3, 'NGC', 'NGC'),
    (4, 'MPLC', 'MPLC / PPMO'),
    (5, 'PPMO', 'PPMO')
ON CONFLICT (book_id) DO UPDATE SET code = EXCLUDED.code, name = EXCLUDED.name;

SELECT setval('fims.books_book_id_seq', (SELECT MAX(book_id) FROM fims.books));
