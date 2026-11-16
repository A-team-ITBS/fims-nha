<?php

namespace PHPMaker2024\FIMS;

// Page object
$AccountableOfficerPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/accountable-officer-page.php"); ?>
<?= GetDebugMessage() ?>
