<?php

namespace PHPMaker2024\FIMS;

// Page object
$GeneralLedgerPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/general-ledger-page.php"); ?>
<?= GetDebugMessage() ?>
