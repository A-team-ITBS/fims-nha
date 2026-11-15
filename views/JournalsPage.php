<?php

namespace PHPMaker2024\FIMS;

// Page object
$JournalsPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/journals-page.php"); ?>
<?= GetDebugMessage() ?>
