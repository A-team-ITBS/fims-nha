<?php

namespace PHPMaker2024\FIMS;

// Page object
$StatusOfFundPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/status-of-fund-page.php"); ?>
<?= GetDebugMessage() ?>
