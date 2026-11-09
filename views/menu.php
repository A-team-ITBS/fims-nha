<?php

namespace PHPMaker2024\FIMS;

// Navbar menu
$topMenu = new Menu("navbar", true, true);
echo $topMenu->toScript();

// Sidebar menu
$sideMenu = new Menu("menu", true, false);
$sideMenu->addMenuItem(9, "mi_MainDashboard", $Language->menuPhrase("9", "MenuText"), "MainDashboard", -1, "", AllowListMenu('{FIMS}MainDashboard.php'), false, false, "fas fa-chart-line", "", false, true);
$sideMenu->addMenuItem(146, "mci_Budget", $Language->menuPhrase("146", "MenuText"), "", -1, "", true, false, true, "fas fa-wallet", "", false, true);
$sideMenu->addMenuItem(88, "mi_budget_allocation_form", $Language->menuPhrase("88", "MenuText"), "BudgetAllocationForm", 146, "", AllowListMenu('{FIMS}budget-allocation-form.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(81, "mi_budget_req_page", $Language->menuPhrase("81", "MenuText"), "BudgetReqPage", 146, "", AllowListMenu('{FIMS}budget-req-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(85, "mi_budget_revision_page", $Language->menuPhrase("85", "MenuText"), "BudgetRevisionPage", 146, "", AllowListMenu('{FIMS}budget-revision-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(147, "mci_Programs_&_Projects_", $Language->menuPhrase("147", "MenuText"), "", -1, "", true, false, true, "fas fa-sitemap", "", false, true);
$sideMenu->addMenuItem(80, "mi_program_page", $Language->menuPhrase("80", "MenuText"), "ProgramPage", 147, "", AllowListMenu('{FIMS}program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(82, "mi_sub_program_page", $Language->menuPhrase("82", "MenuText"), "SubProgramPage", 147, "", AllowListMenu('{FIMS}sub-program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(79, "mi_project_page", $Language->menuPhrase("79", "MenuText"), "ProjectPage", 147, "", AllowListMenu('{FIMS}project-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(148, "mci_Accounting_Records", $Language->menuPhrase("148", "MenuText"), "", -1, "", true, false, true, "fas fa-book", "", false, true);
$sideMenu->addMenuItem(89, "mi_chart_of_accounts_page", $Language->menuPhrase("89", "MenuText"), "ChartOfAccountsPage", 148, "", AllowListMenu('{FIMS}chart-of-accounts-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(90, "mi_responsibility_center_page", $Language->menuPhrase("90", "MenuText"), "ResponsibilityCenterPage", 148, "", AllowListMenu('{FIMS}responsibility-center-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(91, "mi_AIRModule", $Language->menuPhrase("91", "MenuText"), "AirModule", -1, "", AllowListMenu('{FIMS}AIRModule.php'), false, false, "fas fa-clipboard-check", "", false, true);
$sideMenu->addMenuItem(206, "mci_Administration", $Language->menuPhrase("206", "MenuText"), "", -1, "", true, false, true, "fas fa-user-cog", "", false, true);
$sideMenu->addMenuItem(70, "mi_PositionManagement", $Language->menuPhrase("70", "MenuText"), "PositionManagement", 206, "", AllowListMenu('{FIMS}PositionManagement.php'), false, false, "fas fa-briefcase", "", false, true);
$sideMenu->addMenuItem(13, "mi_UserAccess", $Language->menuPhrase("13", "MenuText"), "UserAccess", 206, "", AllowListMenu('{FIMS}UserAccess.php'), false, false, "fas fa-user-lock", "", false, true);
$sideMenu->addMenuItem(12, "mi_UserManagement", $Language->menuPhrase("12", "MenuText"), "UserManagement", 206, "", AllowListMenu('{FIMS}UserManagement.php'), false, false, "fas fa-user-shield", "", false, true);
$sideMenu->addMenuItem(69, "mi_DepartmentManagement", $Language->menuPhrase("69", "MenuText"), "DepartmentManagement", 206, "", AllowListMenu('{FIMS}DepartmentManagement.php'), false, false, "fas fa-building", "", false, true);
echo $sideMenu->toScript();
