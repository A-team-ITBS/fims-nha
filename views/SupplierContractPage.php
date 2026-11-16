<?php

namespace PHPMaker2024\FIMS;

// Page object
$SupplierContractPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/supplier-contract-page.php"); ?>
<?= GetDebugMessage() ?>
