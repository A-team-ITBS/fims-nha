<?php
namespace PHPMaker2024\FIMS;

/**
 * payroll-page.php — the older "Payroll" menu entry. The page itself now lives in
 * payroll-setup-page.php (Accounts Set-up → Payroll Setup); this file only includes it so both menu
 * entries show the same page and there is a single copy of the code to maintain.
 * Safe to delete together with the PayrollPage menu entry / controller / model / view once it is no longer used.
 */
include __DIR__ . '/payroll-setup-page.php';
