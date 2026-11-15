-- status_for = 'journal_entry_voucher' (fims.journal_entry_vouchers.status_id — see
-- "03 - alter_table/29 - journal_entry_vouchers_add_status.sql"). Posting pipeline:
--   DRAFT     Accounting is still filling in / editing the entry (saveJournalEntry()).
--   APPROVED  the DV it belongs to was approved on the Accounting side (FOR_ACCOUNTING ->
--             FOR_TREASURY) — set by GeneralLedgerService::postJev() immediately before POSTED,
--             in the same transaction; there is no separate manual "approve" action.
--   POSTED    its lines have been written to fims.general_ledger. Terminal unless reversed.
--   REVERSED  the DV was CANCELLED after its JEV was POSTED; GeneralLedgerService::reverseJev()
--             posted an offsetting entry and flipped the original JEV to this status.
-- Same WHERE NOT EXISTS idempotent-seed convention as the rest of this file's sibling blocks
-- (fims.settings_status has no unique constraint on (name, status_for) to ON CONFLICT off of).
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'DRAFT', 'journal_entry_voucher', '#6B7280'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'DRAFT' AND status_for = 'journal_entry_voucher');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'APPROVED', 'journal_entry_voucher', '#2563EB'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'APPROVED' AND status_for = 'journal_entry_voucher');

-- JEV review chain for PAYMENT and standalone JEVs (Setup JEVs still post via the DV's own chain).
-- Preparer (Accounting) prepares -> Section Chief review -> Division Manager -> Department Manager
-- approves & posts. All held by the Accounting Department (RC 012); see
-- DisbursementVoucherService::JEV_TRANSITIONS / preparePaymentJev() / changeJevStatus().
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'FOR_SECTION_CHIEF', 'journal_entry_voucher', '#D97706'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'FOR_SECTION_CHIEF' AND status_for = 'journal_entry_voucher');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'FOR_DIVISION_MANAGER', 'journal_entry_voucher', '#7C3AED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'FOR_DIVISION_MANAGER' AND status_for = 'journal_entry_voucher');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'FOR_DEPARTMENT_MANAGER', 'journal_entry_voucher', '#4F46E5'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'FOR_DEPARTMENT_MANAGER' AND status_for = 'journal_entry_voucher');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'POSTED', 'journal_entry_voucher', '#16A34A'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'POSTED' AND status_for = 'journal_entry_voucher');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'REVERSED', 'journal_entry_voucher', '#DC3545'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'REVERSED' AND status_for = 'journal_entry_voucher');
