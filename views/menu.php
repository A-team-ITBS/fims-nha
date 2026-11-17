<?php

namespace PHPMaker2024\FIMS;

// Navbar menu
$topMenu = new Menu("navbar", true, true);
echo $topMenu->toScript();

// Sidebar menu
$sideMenu = new Menu("menu", true, false);
$sideMenu->addMenuItem(262, "mi_notification_reads", $Language->menuPhrase("262", "MenuText"), "NotificationReadsList", -1, "", AllowListMenu('{FIMS}notification_reads'), false, false, "", "", false, true);
$sideMenu->addMenuItem(9, "mi_MainDashboard", $Language->menuPhrase("9", "MenuText"), "MainDashboard", -1, "", AllowListMenu('{FIMS}MainDashboard.php'), false, false, "fas fa-chart-line", "", false, true);
$sideMenu->addMenuItem(541, "mci_COB_Preparation", $Language->menuPhrase("541", "MenuText"), "", -1, "", true, false, true, "fas fa-file-circle-plus", "", false, true);
$sideMenu->addMenuItem(261, "mi_budget_prop_page", $Language->menuPhrase("261", "MenuText"), "BudgetPropPage", 541, "", AllowListMenu('{FIMS}budget-prop-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(81, "mi_budget_req_page", $Language->menuPhrase("81", "MenuText"), "BudgetReqPage", 541, "", AllowListMenu('{FIMS}budget-req-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(85, "mi_budget_revision_page", $Language->menuPhrase("85", "MenuText"), "BudgetRevisionPage", 541, "", AllowListMenu('{FIMS}budget-revision-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(657, "mi_ledger_page", $Language->menuPhrase("657", "MenuText"), "LedgerPage", 541, "", AllowListMenu('{FIMS}ledger-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(146, "mci_Budget_Process", $Language->menuPhrase("146", "MenuText"), "", -1, "", true, false, true, "fas fa-calculator", "", false, true);
$sideMenu->addMenuItem(553, "mi_cba_memo_request_page", $Language->menuPhrase("553", "MenuText"), "CbaMemoRequestPage", 146, "", AllowListMenu('{FIMS}cba-memo-request-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(554, "mi_rfa_laa_memo_request_page", $Language->menuPhrase("554", "MenuText"), "RfaLaaMemoRequestPage", 146, "", AllowListMenu('{FIMS}rfa-laa-memo-request-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(549, "mci_DV_Processing", $Language->menuPhrase("549", "MenuText"), "", -1, "", true, false, true, "fas fa-file-invoice", "", false, true);
$sideMenu->addMenuItem(207, "mi_Voucher", $Language->menuPhrase("207", "MenuText"), "Voucher", 549, "", AllowListMenu('{FIMS}Voucher.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(559, "mi_liquidation_voucher_page", $Language->menuPhrase("559", "MenuText"), "LiquidationVoucherPage", 549, "", AllowListMenu('{FIMS}liquidation-voucher-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(208, "mi_JournalEntryVoucher", $Language->menuPhrase("208", "MenuText"), "JournalEntryVoucher", 549, "", AllowListMenu('{FIMS}JournalEntryVoucher.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(758, "mi_check_disbursement_voucher_page", $Language->menuPhrase("758", "MenuText"), "CheckDisbursementVoucherPage", 549, "", AllowListMenu('{FIMS}check-disbursement-voucher-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(390, "mci_Inventory", $Language->menuPhrase("390", "MenuText"), "", -1, "", true, false, true, "fas fa-clipboard-check", "", false, true);
$sideMenu->addMenuItem(91, "mi_IARModule", $Language->menuPhrase("91", "MenuText"), "IarModule", 390, "", AllowListMenu('{FIMS}IARModule.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(755, "mci_Monitoring", $Language->menuPhrase("755", "MenuText"), "", -1, "", true, false, true, "fas fa-chart-bar", "", false, true);
$sideMenu->addMenuItem(400, "mi_cob_page", $Language->menuPhrase("400", "MenuText"), "CobPage", 755, "", AllowListMenu('{FIMS}cob-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(756, "mi_rfa_laa_monitoring_page", $Language->menuPhrase("756", "MenuText"), "RfaLaaMonitoringPage", 755, "", AllowListMenu('{FIMS}rfa-laa-monitoring-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(331, "mi_cash_position_page", $Language->menuPhrase("331", "MenuText"), "CashPositionPage", 755, "", AllowListMenu('{FIMS}cash-position-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(551, "mci_Registry/NCA", $Language->menuPhrase("551", "MenuText"), "", -1, "", true, false, true, "fas fa-book", "", false, true);
$sideMenu->addMenuItem(263, "mi_saro_registry_page", $Language->menuPhrase("263", "MenuText"), "SaroRegistryPage", 551, "", AllowListMenu('{FIMS}saro-registry-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(560, "mi_check_registry_page", $Language->menuPhrase("560", "MenuText"), "CheckRegistryPage", 551, "", AllowListMenu('{FIMS}check-registry-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(561, "mi_accountable_forms_registry_page", $Language->menuPhrase("561", "MenuText"), "AccountableFormsRegistryPage", 551, "", AllowListMenu('{FIMS}accountable-forms-registry-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(757, "mi_collections_page", $Language->menuPhrase("757", "MenuText"), "CollectionsPage", 551, "", AllowListMenu('{FIMS}collections-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(552, "mci_Reports", $Language->menuPhrase("552", "MenuText"), "", -1, "", true, false, true, "fas fa-chart-pie", "", false, true);
$sideMenu->addMenuItem(652, "mi_fmr_page", $Language->menuPhrase("652", "MenuText"), "FmrPage", 552, "", AllowListMenu('{FIMS}fmr-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(653, "mi_remittance_list_page", $Language->menuPhrase("653", "MenuText"), "RemittanceListPage", 552, "", AllowListMenu('{FIMS}remittance-list-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(394, "mci_Ledgers", $Language->menuPhrase("394", "MenuText"), "", -1, "", true, false, true, "fas fa-scale-balanced", "", false, true);
$sideMenu->addMenuItem(393, "mi_general_ledger_page", $Language->menuPhrase("393", "MenuText"), "GeneralLedgerPage", 394, "", AllowListMenu('{FIMS}general-ledger-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(395, "mi_subsidiary_ledger_page", $Language->menuPhrase("395", "MenuText"), "SubsidiaryLedgerPage", 394, "", AllowListMenu('{FIMS}subsidiary-ledger-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(396, "mi_journals_page", $Language->menuPhrase("396", "MenuText"), "JournalsPage", 394, "", AllowListMenu('{FIMS}journals-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(397, "mi_trial_balance_page", $Language->menuPhrase("397", "MenuText"), "TrialBalancePage", 394, "", AllowListMenu('{FIMS}trial-balance-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(651, "mci_Accounts_Set-up", $Language->menuPhrase("651", "MenuText"), "", -1, "", true, false, true, "fas fa-user-cog", "", false, true);
$sideMenu->addMenuItem(80, "mi_program_page", $Language->menuPhrase("80", "MenuText"), "ProgramPage", 651, "", AllowListMenu('{FIMS}program-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(89, "mi_chart_of_accounts_page", $Language->menuPhrase("89", "MenuText"), "ChartOfAccountsPage", 651, "", AllowListMenu('{FIMS}chart-of-accounts-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(90, "mi_responsibility_center_page", $Language->menuPhrase("90", "MenuText"), "ResponsibilityCenterPage", 651, "", AllowListMenu('{FIMS}responsibility-center-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(332, "mi_bank_management_page", $Language->menuPhrase("332", "MenuText"), "BankManagementPage", 651, "", AllowListMenu('{FIMS}bank-management-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(391, "mi_vendors_page", $Language->menuPhrase("391", "MenuText"), "VendorsPage", 651, "", AllowListMenu('{FIMS}vendors-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(658, "mi_accountable_officer_page", $Language->menuPhrase("658", "MenuText"), "AccountableOfficerPage", 651, "", AllowListMenu('{FIMS}accountable_officer-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(759, "mi_payroll_setup_page", $Language->menuPhrase("759", "MenuText"), "PayrollSetupPage", 651, "", AllowListMenu('{FIMS}payroll-setup-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(761, "mi_payroll_page", $Language->menuPhrase("761", "MenuText"), "PayrollPage", 651, "", AllowListMenu('{FIMS}payroll-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(762, "mi_remittance_page", $Language->menuPhrase("762", "MenuText"), "RemittancePage", 651, "", AllowListMenu('{FIMS}remittance-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(760, "mi_fidelity_bond_page", $Language->menuPhrase("760", "MenuText"), "FidelityBondPage", 651, "", AllowListMenu('{FIMS}fidelity-bond-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(330, "mci_Fund_Sourcing", $Language->menuPhrase("330", "MenuText"), "", -1, "", true, false, true, "fas fa-hand-holding-dollar", "", false, true);
$sideMenu->addMenuItem(392, "mi_fund_sourcing_dashboard_page", $Language->menuPhrase("392", "MenuText"), "FundSourcingDashboardPage", 330, "", AllowListMenu('{FIMS}fund-sourcing-dashboard-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(264, "mi_supplemental_funds_page", $Language->menuPhrase("264", "MenuText"), "SupplementalFundsPage", 330, "", AllowListMenu('{FIMS}supplemental-funds-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(265, "mi_trust_funds_page", $Language->menuPhrase("265", "MenuText"), "TrustFundsPage", 330, "", AllowListMenu('{FIMS}trust-funds-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(266, "mi_pricing_page", $Language->menuPhrase("266", "MenuText"), "PricingPage", 330, "", AllowListMenu('{FIMS}pricing-page.php'), false, false, "", "", false, true);
$sideMenu->addMenuItem(206, "mci_Administration", $Language->menuPhrase("206", "MenuText"), "", -1, "", true, false, true, "fas fa-user-cog", "", false, true);
$sideMenu->addMenuItem(70, "mi_PositionManagement", $Language->menuPhrase("70", "MenuText"), "PositionManagement", 206, "", AllowListMenu('{FIMS}PositionManagement.php'), false, false, "fas fa-briefcase", "", false, true);
$sideMenu->addMenuItem(13, "mi_UserAccess", $Language->menuPhrase("13", "MenuText"), "UserAccess", 206, "", AllowListMenu('{FIMS}UserAccess.php'), false, false, "fas fa-user-lock", "", false, true);
$sideMenu->addMenuItem(12, "mi_UserManagement", $Language->menuPhrase("12", "MenuText"), "UserManagement", 206, "", AllowListMenu('{FIMS}UserManagement.php'), false, false, "fas fa-user-shield", "", false, true);
$sideMenu->addMenuItem(69, "mi_DepartmentManagement", $Language->menuPhrase("69", "MenuText"), "DepartmentManagement", 206, "", AllowListMenu('{FIMS}DepartmentManagement.php'), false, false, "fas fa-building", "", false, true);
$sideMenu->addMenuItem(259, "mi_ChangePass", $Language->menuPhrase("259", "MenuText"), "ChangePass", 206, "", AllowListMenu('{FIMS}ChangePass.php'), false, false, "", "", false, true);
echo $sideMenu->toScript();
