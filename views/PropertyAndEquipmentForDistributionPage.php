<?php

namespace PHPMaker2024\FIMS;

// Page object
$PropertyAndEquipmentForDistributionPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/property-and-equipment-for-distribution-page.php"); ?>
<?= GetDebugMessage() ?>
