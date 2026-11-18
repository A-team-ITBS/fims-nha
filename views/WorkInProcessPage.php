<?php

namespace PHPMaker2024\FIMS;

// Page object
$WorkInProcessPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/work-in-process-page.php"); ?>
<?= GetDebugMessage() ?>
