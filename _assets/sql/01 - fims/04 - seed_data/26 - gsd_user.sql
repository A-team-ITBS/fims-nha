-- GSD (General Services Department, RC 010) sample user — notified when a delivery is accepted in the IAR module.
-- Lives in the fims seed folder because it needs uac.users.rc_id (alter_table/09) and fims.responsibility_code (010).
-- The role (user level 17) and its page permissions are created in "00 - uac/04 - seed_data/07 - gsd_role.sql".
--
-- Password is "password123" (bcrypt of the lowercased password — CASE_SENSITIVE_PASSWORD = false), the same shared hash as the other
-- sample users. CHANGE IT on any real server.
INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, rc_id, is_active
) VALUES
    ('gsd.officer', 'gsd.officer@nha.gov.ph', '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'GSD', 'Officer', '17', '010', true)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    rc_id         = EXCLUDED.rc_id,
    is_active     = true;
