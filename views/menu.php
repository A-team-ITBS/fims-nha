<?php

namespace PHPMaker2024\FIMS;

// Navbar menu
$topMenu = new Menu("navbar", true, true);
echo $topMenu->toScript();

// Sidebar menu
$sideMenu = new Menu("menu", true, false);
$sideMenu->addMenuItem(9, "mi_MainDashboard", $Language->menuPhrase("9", "MenuText"), "MainDashboard", -1, "", AllowListMenu('{FIMS}MainDashboard.php'), false, false, "fas fa-chart-line", "", false, true);
$sideMenu->addMenuItem(3, "mi_systems", $Language->menuPhrase("3", "MenuText"), "SystemsList", -1, "", AllowListMenu('{FIMS}systems'), false, false, "fas fa-network-wired", "", false, true);
$sideMenu->addMenuItem(69, "mi_DepartmentManagement", $Language->menuPhrase("69", "MenuText"), "DepartmentManagement", -1, "", AllowListMenu('{FIMS}DepartmentManagement.php'), false, false, "fas fa-building", "", false, true);
$sideMenu->addMenuItem(70, "mi_PositionManagement", $Language->menuPhrase("70", "MenuText"), "PositionManagement", -1, "", AllowListMenu('{FIMS}PositionManagement.php'), false, false, "fas fa-briefcase", "", false, true);
$sideMenu->addMenuItem(13, "mi_UserAccess", $Language->menuPhrase("13", "MenuText"), "UserAccess", -1, "", AllowListMenu('{FIMS}UserAccess.php'), false, false, "fas fa-user-lock", "", false, true);
$sideMenu->addMenuItem(12, "mi_UserManagement", $Language->menuPhrase("12", "MenuText"), "UserManagement", -1, "", AllowListMenu('{FIMS}UserManagement.php'), false, false, "fas fa-user-shield", "", false, true);
$sideMenu->addMenuItem(71, "mi_SignatoryManagement", $Language->menuPhrase("71", "MenuText"), "SignatoryManagement", -1, "", AllowListMenu('{FIMS}SignatoryManagement.php'), false, false, "fas fa-diagram-project", "", false, true);
echo $sideMenu->toScript();
