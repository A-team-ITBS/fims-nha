-- Assigns the office each DV-workflow stage belongs to via uac.users.rc_id
-- (Responsibility Center — fims.responsibility_code), the org-chart column this
-- project already uses elsewhere (fims.budget_req.rc_id), rather than
-- uac.departments. The NHA RC Listing (fims.responsibility_code, seeded by the
-- Financial Services Group hierarchy) already has the three offices the DV
-- workflow needs — no new org units to create:
--   RC-013 Financial Management Department (FMD)  — FOR_FMD_REVIEW -> FOR_ACCOUNTING
--   RC-012 Accounting Department                  — FOR_ACCOUNTING -> FOR_TREASURY
--   RC-014 Treasury Department                     — FOR_TREASURY -> RELEASED
-- See DisbursementVoucherService::STAGE_RC.
--
-- Roles (uac.user_levels) — positive IDs; -2/-1/0 are reserved
INSERT INTO uac.user_levels (user_level_id, name, description, system_id) VALUES
    (10, 'Accounting Officer', 'Processes disbursement vouchers (Accounting stage of the DV workflow)', NULL),
    (11, 'Treasury Officer',   'Releases payment on disbursement vouchers (Treasury stage of the DV workflow)', NULL)
ON CONFLICT (user_level_id) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;

-- Sample users, same convention as "03 - budget_approval_sample_users.sql":
-- password is "password123" (bcrypt of the lowercased password,
-- CASE_SENSITIVE_PASSWORD = false).
INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, rc_id, is_active
) VALUES
    ('accounting_officer', 'accounting.officer@nha.gov.ph', '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Lea',  'Fernandez', '10', 'RC-012', true),
    ('treasury_officer',   'treasury.officer@nha.gov.ph',   '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Noel', 'Ramirez',   '11', 'RC-014', true)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    rc_id = EXCLUDED.rc_id,
    is_active = true;

-- fmd_divmanager (from "03 - budget_approval_sample_users.sql") already exists for FMD
-- budget-approval testing; tag it with the FMD RC too so it doubles as the DV workflow's
-- FMD-stage test user.
UPDATE uac.users SET rc_id = 'RC-013' WHERE username = 'fmd_divmanager';
