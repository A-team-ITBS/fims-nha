-- Accounting's internal DV review chain — the three positions inside the
-- Accounting Department (RC 012) that a Disbursement Voucher passes through
-- after FMD and before Treasury:
--   FOR_ACCOUNTING          Accounting Section Chief   (processes the DV + prepares the JEV)
--   FOR_DIVISION_MANAGER    Accounting Division Manager (reviews)
--   FOR_DEPARTMENT_MANAGER  Accounting Department Manager (approves onward to Treasury)
-- See DisbursementVoucherService::TRANSITIONS / STAGE_HOLDER_RC.
--
-- Gating is by Responsibility Center only (all three are RC 012), so these roles
-- don't need distinct per-step privileges — they exist so each test account has a
-- correctly-named position. The matching user accounts (which need uac.users.rc_id,
-- added by the fims alter scripts that run after this uac folder) are seeded in
-- "01 - fims/04 - seed_data/12 - accounting_review_chain_users.sql".
--
-- Roles (uac.user_levels) — positive IDs; -2/-1/0 are reserved. 10/11/12 are
-- already taken (Accounting Officer / Treasury Officer / FMD Section Chief).
INSERT INTO uac.user_levels (user_level_id, name, description, system_id) VALUES
    (13, 'Accounting Section Chief',    'Processes the DV and prepares the Journal Entry (FOR_ACCOUNTING step of the DV workflow)', NULL),
    (14, 'Accounting Division Manager', 'Reviews the DV in the Accounting review chain (FOR_DIVISION_MANAGER step)',                 NULL),
    (15, 'Accounting Department Manager','Approves the DV onward to Treasury (FOR_DEPARTMENT_MANAGER step)',                         NULL)
ON CONFLICT (user_level_id) DO UPDATE SET
    name        = EXCLUDED.name,
    description = EXCLUDED.description;

-- Page access: clone the proven Accounting Officer (level 10) permission set onto
-- each new role, so they can reach exactly the same pages (Voucher, Journal Entry
-- Voucher, etc.) a working Accounting user can. Which workflow stage each may move a
-- voucher to is enforced separately, server-side, in
-- DisbursementVoucherService::authorizeTransition() — this only grants page access.
INSERT INTO uac.user_level_permissions (user_level_id, table_name, permission)
SELECT lvl, p.table_name, p.permission
FROM unnest(ARRAY[13, 14, 15]) AS lvl
CROSS JOIN (SELECT table_name, permission FROM uac.user_level_permissions WHERE user_level_id = 10) AS p
ON CONFLICT (user_level_id, table_name) DO UPDATE SET permission = EXCLUDED.permission;
