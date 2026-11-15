-- Accounting DV-review-chain sample users. Lives in the fims seed folder (not uac)
-- because it needs uac.users.rc_id, which is added by
-- "03 - alter_table/09 - users_replace_rc_code_with_rc_id.sql" and references
-- fims.responsibility_code. The roles (levels 13/14/15) and their page permissions
-- are created in "00 - uac/04 - seed_data/06 - accounting_review_chain_roles.sql".
--
-- All three sit in the Accounting Department (RC 012) — the office that holds the DV
-- through FOR_ACCOUNTING -> FOR_DIVISION_MANAGER -> FOR_DEPARTMENT_MANAGER. Because
-- gating is by RC, any of them can advance any of the three Accounting steps; the
-- distinct roles just label each account's position.
--
-- Password is "password123" for all three (bcrypt of the lowercased password —
-- CASE_SENSITIVE_PASSWORD = false), same shared hash as the other workflow sample users.
INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, rc_id, is_active
) VALUES
    ('acctg.sectionchief',     'acctg.sectionchief@nha.gov.ph',     '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Carla',  'Mendoza',  '13', '012', true),
    ('acctg.divisionmanager',  'acctg.divisionmanager@nha.gov.ph',  '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Ramon',  'Villanueva','14', '012', true),
    ('acctg.departmentmanager','acctg.departmentmanager@nha.gov.ph','$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Teresa', 'Aquino',   '15', '012', true)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    rc_id         = EXCLUDED.rc_id,
    is_active     = true;
