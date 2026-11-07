<?php

namespace PHPMaker2024\FIMS;

// Page object
$PositionManagement = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/position-management.php"); ?>

<?= GetDebugMessage() ?>
