-- Tags the FMD-department sample users from "00 - uac/04 - seed_data/03 -
-- budget_approval_sample_users.sql" with the FMD responsibility center.
--
-- Lives here (fims phase) rather than alongside that file because the uac phase
-- runs entirely before the fims phase in the installer: uac.users.rc_id doesn't
-- exist yet (added by "03 - alter_table/09 - users_replace_rc_code_with_rc_id.sql")
-- and fims.responsibility_code doesn't exist yet either when the uac phase runs.
-- Same reasoning as "03 - dv_workflow_users.sql".

UPDATE uac.users
SET rc_id = (SELECT rc_id FROM fims.responsibility_code WHERE rc_name = 'Financial Management Department (FMD)')
WHERE username IN ('fmd_fundsourcing', 'budget_officer', 'fmd_divmanager', 'fmd_deptmanager', 'budget_head');
