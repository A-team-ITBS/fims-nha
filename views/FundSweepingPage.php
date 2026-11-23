<?php

namespace PHPMaker2024\FIMS;

// Page object
$FundSweepingPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/fund-sweeping-page.php"); ?>
<?= GetDebugMessage() ?>
