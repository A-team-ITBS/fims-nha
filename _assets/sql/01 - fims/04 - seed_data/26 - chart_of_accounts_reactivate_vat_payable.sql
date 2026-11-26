-- Reactivate 2-02-01-120 "Value Added Tax Payable".
--
-- "22 - chart_of_accounts_deactivate_not_in_coa_pdf.sql" set it Inactive (it carried no book tag on NHA's COA sheet), but
-- "25 - deduction_types_reactivate_vat.sql" points the VAT deduction type at it. The DV form therefore filled in a Posting
-- Account that the account pickers (Active AND posting_allowed only) could not find. VAT withheld credits this account,
-- so it has to be Active and postable.
--
-- Idempotent: safe to re-run. UPDATE-only — the row already exists (seed 01 / sample COA).

UPDATE fims.chart_of_accounts
   SET status          = 'Active',
       posting_allowed = TRUE
 WHERE account_code = '2-02-01-120'
   AND (status <> 'Active' OR posting_allowed IS DISTINCT FROM TRUE);

-- Book scope: seed 21 only tagged the accounts on the COA sheet, so this one has no books. Give it the same books as its
-- sibling "Due to BIR" (2-02-01-010) so it is not hidden when the COA is filtered by book. Skipped when the install has no
-- `books` column, or when the account already has books.
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.columns
                WHERE table_schema = 'fims' AND table_name = 'chart_of_accounts' AND column_name = 'books') THEN
        UPDATE fims.chart_of_accounts AS vat
           SET books = bir.books
          FROM fims.chart_of_accounts AS bir
         WHERE vat.account_code = '2-02-01-120'
           AND bir.account_code = '2-02-01-010'
           AND (vat.books IS NULL OR btrim(vat.books) = '')
           AND bir.books IS NOT NULL;
    END IF;
END $$;
