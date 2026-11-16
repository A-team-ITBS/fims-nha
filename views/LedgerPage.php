<?php

namespace PHPMaker2024\FIMS;

// Page object
$LedgerPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/ledger-page.php"); ?>
<?= GetDebugMessage() ?>
