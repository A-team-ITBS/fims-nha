<?php

namespace PHPMaker2024\FIMS;

// Page object
$SubsidiaryLedgerPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/subsidiary-ledger-page.php"); ?>
<?= GetDebugMessage() ?>
