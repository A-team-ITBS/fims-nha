<?php

namespace PHPMaker2024\FIMS;

// Page object
$PayrollSetupPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/payroll-setup-page.php"); ?>
<?= GetDebugMessage() ?>
