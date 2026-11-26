-- fims.settings_status.status_code: the stable machine key of a status, so `name` can be the
-- editable display label. Only FTLA uses it so far (status_for = 'fund_transfers': status_code is the
-- value stored in fims.fund_transfers.status, name is what the page shows). NULL for every other
-- module, which keeps matching by name. Re-runnable.
ALTER TABLE fims.settings_status ADD COLUMN IF NOT EXISTS status_code VARCHAR(50);
COMMENT ON COLUMN fims.settings_status.status_code IS 'Stable key of the status when name is only a display label (FTLA: the fims.fund_transfers.status value); NULL = match by name';

-- Relabel the rows seeded by "02 - sample_data/12" (name was the raw key) ...
UPDATE fims.settings_status SET status_code = 'DRAFT', name = 'Draft'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'DRAFT';
UPDATE fims.settings_status SET status_code = 'FOR_RM_APPROVAL', name = 'For RM Approval'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'FOR_RM_APPROVAL';
UPDATE fims.settings_status SET status_code = 'FOR_REVIEW', name = 'For Review'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'FOR_REVIEW';
UPDATE fims.settings_status SET status_code = 'FOR_APPROVAL', name = 'For Approval'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'FOR_APPROVAL';
UPDATE fims.settings_status SET status_code = 'APPROVED', name = 'Approved'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'APPROVED';
UPDATE fims.settings_status SET status_code = 'TRANSMITTED', name = 'Transmitted to Bank'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'TRANSMITTED';
UPDATE fims.settings_status SET status_code = 'VALIDATED', name = 'Validated (Bank Confirmed)'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'VALIDATED';
UPDATE fims.settings_status SET status_code = 'SUBMITTED_TO_ACCOUNTING', name = 'Submitted to Accounting'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'SUBMITTED_TO_ACCOUNTING';
UPDATE fims.settings_status SET status_code = 'COMPLETED', name = 'Completed (With Validated FTLA)'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'COMPLETED';
UPDATE fims.settings_status SET status_code = 'CANCELLED', name = 'Cancelled'
WHERE status_for = 'fund_transfers' AND status_code IS NULL AND name = 'CANCELLED';

-- ... and insert any that are missing (the base color comes from the 12 seed; this only guards gaps).
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'Draft', 'fund_transfers', '#6B7280', 'DRAFT'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'DRAFT');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'For RM Approval', 'fund_transfers', '#6B7280', 'FOR_RM_APPROVAL'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'FOR_RM_APPROVAL');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'For Review', 'fund_transfers', '#6B7280', 'FOR_REVIEW'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'FOR_REVIEW');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'For Approval', 'fund_transfers', '#6B7280', 'FOR_APPROVAL'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'FOR_APPROVAL');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'Approved', 'fund_transfers', '#6B7280', 'APPROVED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'APPROVED');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'Transmitted to Bank', 'fund_transfers', '#6B7280', 'TRANSMITTED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'TRANSMITTED');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'Validated (Bank Confirmed)', 'fund_transfers', '#6B7280', 'VALIDATED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'VALIDATED');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'Submitted to Accounting', 'fund_transfers', '#6B7280', 'SUBMITTED_TO_ACCOUNTING'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'SUBMITTED_TO_ACCOUNTING');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'Completed (With Validated FTLA)', 'fund_transfers', '#6B7280', 'COMPLETED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'COMPLETED');
INSERT INTO fims.settings_status (name, status_for, color, status_code)
SELECT 'Cancelled', 'fund_transfers', '#6B7280', 'CANCELLED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE status_for = 'fund_transfers' AND status_code = 'CANCELLED');
