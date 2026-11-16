<?php

namespace PHPMaker2024\FIMS;

// Page object
$LedgerBudgetAllocation = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/ledger-budget-allocation.php"); ?>

<?= GetDebugMessage() ?>
