<?php

namespace PHPMaker2024\FIMS;

// Page object
$AccountableFormsRegistryPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/accountable-forms-registry-page.php"); ?>
<?= GetDebugMessage() ?>
