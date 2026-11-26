<?php

namespace PHPMaker2024\FIMS;

// Page object
$AdminFeeRatesPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/admin-fee-rates-page.php"); ?>
<?= GetDebugMessage() ?>
