<?php

namespace PHPMaker2024\FIMS;

// Navbar menu
$topMenu = new Menu("navbar", true, true);
$topMenu->addMenuItem(59, "mi_IDGenerator", $Language->menuPhrase("59", "MenuText"), "IdGenerator", -1, "", AllowListMenu('{FIMS}IDGenerator.php'), false, false, "fas fa-barcode", "", true, false);
$topMenu->addMenuItem(31, "mi_WorkflowManager", $Language->menuPhrase("31", "MenuText"), "WorkflowManager", -1, "", AllowListMenu('{FIMS}WorkflowManager.php'), false, false, "fas fa-diagram-project", "", true, false);
echo $topMenu->toScript();

// Sidebar menu
$sideMenu = new Menu("menu", true, false);
$sideMenu->addMenuItem(72, "mi_api_logs", $Language->menuPhrase("72", "MenuText"), "ApiLogsList", -1, "", AllowListMenu('{FIMS}api_logs'), false, false, "", "", false, true);
$sideMenu->addMenuItem(73, "mi_api_resources", $Language->menuPhrase("73", "MenuText"), "ApiResourcesList", -1, "", AllowListMenu('{FIMS}api_resources'), false, false, "", "", false, true);
$sideMenu->addMenuItem(74, "mi_api_systems", $Language->menuPhrase("74", "MenuText"), "ApiSystemsList", -1, "", AllowListMenu('{FIMS}api_systems'), false, false, "", "", false, true);
$sideMenu->addMenuItem(75, "mi_document_asset_types", $Language->menuPhrase("75", "MenuText"), "DocumentAssetTypesList", -1, "", AllowListMenu('{FIMS}document_asset_types'), false, false, "", "", false, true);
$sideMenu->addMenuItem(76, "mi_document_header_assets", $Language->menuPhrase("76", "MenuText"), "DocumentHeaderAssetsList", -1, "", AllowListMenu('{FIMS}document_header_assets'), false, false, "", "", false, true);
$sideMenu->addMenuItem(77, "mi_document_header_slots", $Language->menuPhrase("77", "MenuText"), "DocumentHeaderSlotsList", -1, "", AllowListMenu('{FIMS}document_header_slots'), false, false, "", "", false, true);
$sideMenu->addMenuItem(78, "mi_jwt_sessions", $Language->menuPhrase("78", "MenuText"), "JwtSessionsList", -1, "", AllowListMenu('{FIMS}jwt_sessions'), false, false, "", "", false, true);
$sideMenu->addMenuItem(9, "mi_MainDashboard", $Language->menuPhrase("9", "MenuText"), "MainDashboard", -1, "", AllowListMenu('{FIMS}MainDashboard.php'), false, false, "fas fa-chart-line", "", false, true);
$sideMenu->addMenuItem(3, "mi_systems", $Language->menuPhrase("3", "MenuText"), "SystemsList", -1, "", AllowListMenu('{FIMS}systems'), false, false, "fas fa-network-wired", "", false, true);
$sideMenu->addMenuItem(69, "mi_DepartmentManagement", $Language->menuPhrase("69", "MenuText"), "DepartmentManagement", -1, "", AllowListMenu('{FIMS}DepartmentManagement.php'), false, false, "fas fa-building", "", false, true);
$sideMenu->addMenuItem(70, "mi_PositionManagement", $Language->menuPhrase("70", "MenuText"), "PositionManagement", -1, "", AllowListMenu('{FIMS}PositionManagement.php'), false, false, "fas fa-briefcase", "", false, true);
$sideMenu->addMenuItem(13, "mi_UserAccess", $Language->menuPhrase("13", "MenuText"), "UserAccess", -1, "", AllowListMenu('{FIMS}UserAccess.php'), false, false, "fas fa-user-lock", "", false, true);
$sideMenu->addMenuItem(12, "mi_UserManagement", $Language->menuPhrase("12", "MenuText"), "UserManagement", -1, "", AllowListMenu('{FIMS}UserManagement.php'), false, false, "fas fa-user-shield", "", false, true);
$sideMenu->addMenuItem(59, "mi_IDGenerator", $Language->menuPhrase("59", "MenuText"), "IdGenerator", -1, "", AllowListMenu('{FIMS}IDGenerator.php'), false, false, "fas fa-barcode", "", true, true);
$sideMenu->addMenuItem(8, "mi_aggregated_audit_logs", $Language->menuPhrase("8", "MenuText"), "AggregatedAuditLogsList?cmd=resetall", -1, "", AllowListMenu('{FIMS}aggregated_audit_logs'), false, false, "fas fa-history", "", false, true);
$sideMenu->addMenuItem(31, "mi_WorkflowManager", $Language->menuPhrase("31", "MenuText"), "WorkflowManager", -1, "", AllowListMenu('{FIMS}WorkflowManager.php'), false, false, "fas fa-diagram-project", "", true, true);
$sideMenu->addMenuItem(71, "mi_SignatoryManagement", $Language->menuPhrase("71", "MenuText"), "SignatoryManagement", -1, "", AllowListMenu('{FIMS}SignatoryManagement.php'), false, false, "fas fa-diagram-project", "", false, true);
echo $sideMenu->toScript();
