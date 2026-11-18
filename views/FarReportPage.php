<?php

namespace PHPMaker2024\FIMS;

// Page object
$FarReportPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/far-report-page.php"); ?>
<?= GetDebugMessage() ?>
