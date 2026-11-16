<?php

namespace PHPMaker2024\FIMS;

// Page object
$RfaLaaMemoRequestPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/rfa-laa-memo-request-page.php"); ?>
<?= GetDebugMessage() ?>
