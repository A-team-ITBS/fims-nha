-- General Services Department (GSD, RC 010) — the office that is notified when a PO's delivery is accepted
-- (IAR module -> Actions -> Delivery Acceptance; PoDrService::acceptDelivery() -> NotificationService::toRc('010')).
-- The matching user account (needs uac.users.rc_id, added by the fims alter scripts that run after this uac folder) is seeded in
-- "01 - fims/04 - seed_data/26 - gsd_user.sql".
--
-- Role (uac.user_levels): 17 is the next free positive id (16 = FMD Section Chief).
INSERT INTO uac.user_levels (user_level_id, name, description, system_id) VALUES
    (17, 'GSD Officer', 'General Services Department: receives the delivery-acceptance notifications of the IAR module and works with the accepted deliveries (property records)', NULL)
ON CONFLICT (user_level_id) DO UPDATE SET
    name        = EXCLUDED.name,
    description = EXCLUDED.description;

-- Page access: only what GSD needs — the Voucher page (the delivery-acceptance notification links to the DV), the IAR module, Property Records, the dashboard and the notifications — with the same
-- permission value as the Accounting Officer (level 10) has on those pages. Widen this list if GSD needs more pages.
INSERT INTO uac.user_level_permissions (user_level_id, table_name, permission)
SELECT 17, p.table_name, p.permission
FROM uac.user_level_permissions p
WHERE p.user_level_id = 10
  AND p.table_name IN ('{FIMS}Voucher.php', '{FIMS}IARModule.php', '{FIMS}property-record-page.php', '{FIMS}MainDashboard.php',
                       '{FIMS}notifications', '{FIMS}notification_reads')
ON CONFLICT (user_level_id, table_name) DO UPDATE SET permission = EXCLUDED.permission;
