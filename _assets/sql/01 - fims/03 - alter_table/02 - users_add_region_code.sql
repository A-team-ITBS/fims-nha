-- ============================================================
-- ALTER: uac.users
-- Adds the column used to scope a user to a single RCA (Responsibility
-- Center/Accounting) code for row-level security on the Chart of
-- Accounts page:
--   * region_code — FK fims.responsibility_code(rc_code). NULL = this
--     user is NOT scoped (sees every region, same as before this
--     column existed). Superadmin (user_level_id = -1) always bypasses
--     this restriction regardless of region_code.
--
-- Lives in the fims alter_table phase (not uac's own alter_table phase)
-- because it must run AFTER fims.responsibility_code exists — the uac
-- phase runs before the fims phase in the installer.
--
-- Idempotent (IF NOT EXISTS / guarded), safe to run any number of times.
-- ============================================================

ALTER TABLE uac.users
    ADD COLUMN IF NOT EXISTS region_code VARCHAR(30);

COMMENT ON COLUMN uac.users.region_code IS
    'RCA (Responsibility Center/Accounting) code this user is scoped to, referencing fims.responsibility_code.rc_code. NULL = unrestricted (sees every region). Superadmin always bypasses.';

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'users_region_code_fkey') THEN
        ALTER TABLE uac.users
            ADD CONSTRAINT users_region_code_fkey
            FOREIGN KEY (region_code) REFERENCES fims.responsibility_code (rc_code)
            ON DELETE SET NULL;
    END IF;
END $$;

CREATE INDEX IF NOT EXISTS idx_users_region_code ON uac.users (region_code);
