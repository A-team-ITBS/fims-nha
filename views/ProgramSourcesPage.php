<?php

namespace PHPMaker2024\FIMS;

// Page object
$ProgramSourcesPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/program-sources-page.php"); ?>
<?= GetDebugMessage() ?>
