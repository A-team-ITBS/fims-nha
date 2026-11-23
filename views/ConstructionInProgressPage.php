<?php

namespace PHPMaker2024\FIMS;

// Page object
$ConstructionInProgressPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/construction-in-progress-page.php"); ?>
<?= GetDebugMessage() ?>
