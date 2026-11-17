<?php

namespace PHPMaker2024\FIMS;

// Page object
$RemittancePage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/remittance-page.php"); ?>
<?= GetDebugMessage() ?>
