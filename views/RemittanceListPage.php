<?php

namespace PHPMaker2024\FIMS;

// Page object
$RemittanceListPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/remittance-list-page.php"); ?>
<?= GetDebugMessage() ?>
