<?php

namespace PHPMaker2024\FIMS;

// Navbar menu
$topMenu = new Menu("navbar", true, true);
echo $topMenu->toScript();

// Sidebar menu
$sideMenu = new Menu("menu", true, false);
$sideMenu->addMenuItem(88, "mi_budget_allocation_form", $Language->menuPhrase("88", "MenuText"), "BudgetAllocationForm", -1, "", AllowListMenu('{FIMS}budget-allocation-form.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(79, "mi_project_page", $Language->menuPhrase("79", "MenuText"), "ProjectPage", -1, "", AllowListMenu('{FIMS}project-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(80, "mi_program_page", $Language->menuPhrase("80", "MenuText"), "ProgramPage", -1, "", AllowListMenu('{FIMS}program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(81, "mi_budget_req_page", $Language->menuPhrase("81", "MenuText"), "BudgetReqPage", -1, "", AllowListMenu('{FIMS}budget-req-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(85, "mi_budget_revision_page", $Language->menuPhrase("85", "MenuText"), "BudgetRevisionPage", -1, "", AllowListMenu('{FIMS}budget-revision-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(82, "mi_sub_program_page", $Language->menuPhrase("82", "MenuText"), "SubProgramPage", -1, "", AllowListMenu('{FIMS}sub-program-page.php'), false, false, "", "", false, true);
<<<<<<< HEAD
$sideMenu->addMenuItem(83, "mi_chart_of_accounts_page", $Language->menuPhrase("83", "MenuText"), "ChartOfAccountsPage", -1, "", AllowListMenu('{FIMS}chart-of-accounts-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(84, "mi_responsibility_center_page", $Language->menuPhrase("84", "MenuText"), "ResponsibilityCenterPage", -1, "", AllowListMenu('{FIMS}responsibility-center-page.php'), false, false, "", "", false, true);
=======
$sideMenu->addMenuItem(83, "mi_permission_module_captions", $Language->menuPhrase("83", "MenuText"), "PermissionModuleCaptionsList", -1, "", AllowListMenu('{FIMS}permission_module_captions'), false, false, "", "", false, true);
$sideMenu->addMenuItem(84, "mi_aggregated_audit_logs", $Language->menuPhrase("84", "MenuText"), "AggregatedAuditLogsList", -1, "", AllowListMenu('{FIMS}aggregated_audit_logs'), false, false, "", "", false, true);
>>>>>>> b2e96bac53c44c6b3350834ce0314c0628b1beeb
$sideMenu->addMenuItem(9, "mi_MainDashboard", $Language->menuPhrase("9", "MenuText"), "MainDashboard", -1, "", AllowListMenu('{FIMS}MainDashboard.php'), false, false, "fas fa-chart-line", "", false, true);
$sideMenu->addMenuItem(3, "mi_systems", $Language->menuPhrase("3", "MenuText"), "SystemsList", -1, "", AllowListMenu('{FIMS}systems'), false, false, "fas fa-network-wired", "", false, true);
$sideMenu->addMenuItem(69, "mi_DepartmentManagement", $Language->menuPhrase("69", "MenuText"), "DepartmentManagement", -1, "", AllowListMenu('{FIMS}DepartmentManagement.php'), false, false, "fas fa-building", "", false, true);
$sideMenu->addMenuItem(70, "mi_PositionManagement", $Language->menuPhrase("70", "MenuText"), "PositionManagement", -1, "", AllowListMenu('{FIMS}PositionManagement.php'), false, false, "fas fa-briefcase", "", false, true);
$sideMenu->addMenuItem(13, "mi_UserAccess", $Language->menuPhrase("13", "MenuText"), "UserAccess", -1, "", AllowListMenu('{FIMS}UserAccess.php'), false, false, "fas fa-user-lock", "", false, true);
$sideMenu->addMenuItem(12, "mi_UserManagement", $Language->menuPhrase("12", "MenuText"), "UserManagement", -1, "", AllowListMenu('{FIMS}UserManagement.php'), false, false, "fas fa-user-shield", "", false, true);
echo $sideMenu->toScript();
