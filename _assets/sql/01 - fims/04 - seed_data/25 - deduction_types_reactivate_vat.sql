-- Re-enables the VAT pill on the DV form's Deductions card ("15 - deduction_types_deactivate_vat.sql"

UPDATE fims.deduction_types
   SET is_active    = TRUE,
       name         = 'VAT',
       description  = 'Value Added Tax withheld from the gross amount',
       account_code = '2-02-01-120'
 WHERE code = 'VAT';
