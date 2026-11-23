<?php

namespace PHPMaker2024\FIMS;

// Page object
$MerchandiseInventoryPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/merchandise-inventory-page.php"); ?>
<?= GetDebugMessage() ?>
