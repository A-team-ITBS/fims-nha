<?php

namespace PHPMaker2024\FIMS;

// Page object
$BankReconciliationPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/bank-reconciliation-page.php"); ?>
<?= GetDebugMessage() ?>
