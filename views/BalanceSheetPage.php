<?php

namespace PHPMaker2024\FIMS;

// Page object
$BalanceSheetPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/balance-sheet-page.php"); ?>
<?= GetDebugMessage() ?>
