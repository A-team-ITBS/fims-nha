-- status_for = 'fund_transfers' (fims.fund_transfers.status — the FTLA page, FtlaService).
-- Mirrors FtlaService::STATUSES / NEXT, i.e. the current workflow:
--   DRAFT -> [FOR_RM_APPROVAL (optional)] -> FOR_REVIEW -> FOR_APPROVAL -> APPROVED -> TRANSMITTED
--         -> VALIDATED -> SUBMITTED_TO_ACCOUNTING -> COMPLETED
--   Back-steps: FOR_REVIEW -> DRAFT, FOR_APPROVAL -> FOR_REVIEW (remarks required).
--   CANCELLED: from DRAFT / FOR_RM_APPROVAL / FOR_REVIEW / FOR_APPROVAL (remarks required).
-- fims.fund_transfers.status stays a plain VARCHAR (CHECK-constrained); these rows are the
-- configurable lookup (name = the status value, color = badge color) so the statuses show up
-- in Settings alongside every other module's. Names are the exact status values.
-- Idempotent: WHERE NOT EXISTS (settings_status has no unique key on (name, status_for)).

-- DRAFT: Preparer is still filling in / editing the FTLA (only status where fields are editable).
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'DRAFT', 'fund_transfers', '#6B7280'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'DRAFT' AND status_for = 'fund_transfers');

-- FOR_RM_APPROVAL: Optional step: Regional Manager approval before review. Draft -> here -> FOR_REVIEW.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'FOR_RM_APPROVAL', 'fund_transfers', '#D97706'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'FOR_RM_APPROVAL' AND status_for = 'fund_transfers');

-- FOR_REVIEW: Submitted; reviewer checks it. May be returned to DRAFT (remarks required).
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'FOR_REVIEW', 'fund_transfers', '#D97706'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'FOR_REVIEW' AND status_for = 'fund_transfers');

-- FOR_APPROVAL: Reviewed; approver decides. May be returned to FOR_REVIEW (remarks required).
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'FOR_APPROVAL', 'fund_transfers', '#7C3AED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'FOR_APPROVAL' AND status_for = 'fund_transfers');

-- APPROVED: Approved; ready to be transmitted to the bank.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'APPROVED', 'fund_transfers', '#2563EB'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'APPROVED' AND status_for = 'fund_transfers');

-- TRANSMITTED: Letter of advice sent to the bank; awaiting bank confirmation.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'TRANSMITTED', 'fund_transfers', '#0891B2'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'TRANSMITTED' AND status_for = 'fund_transfers');

-- VALIDATED: Bank confirmed the transfer (validated FTLA).
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'VALIDATED', 'fund_transfers', '#16A34A'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'VALIDATED' AND status_for = 'fund_transfers');

-- SUBMITTED_TO_ACCOUNTING: Settlement JEV created and handed to Accounting for posting.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'SUBMITTED_TO_ACCOUNTING', 'fund_transfers', '#15803D'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'SUBMITTED_TO_ACCOUNTING' AND status_for = 'fund_transfers');

-- COMPLETED: Terminal: transfer fully processed with a validated FTLA.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'COMPLETED', 'fund_transfers', '#166534'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'COMPLETED' AND status_for = 'fund_transfers');

-- CANCELLED: Terminal: cancelled from DRAFT/FOR_RM_APPROVAL/FOR_REVIEW/FOR_APPROVAL (remarks required).
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'CANCELLED', 'fund_transfers', '#DC2626'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'CANCELLED' AND status_for = 'fund_transfers');
