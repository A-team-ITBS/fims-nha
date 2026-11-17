<?php

namespace PHPMaker2024\FIMS;

// Page object
$RfaLaaMonitoringPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/rfa-laa-monitoring-page.php"); ?>
<?= GetDebugMessage() ?>
