<?php

namespace PHPMaker2024\FIMS;

// Page object
$CashFlowPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/cash-flow-page.php"); ?>
<?= GetDebugMessage() ?>
