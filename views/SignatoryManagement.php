<?php

namespace PHPMaker2024\FIMS;

// Page object
$SignatoryManagement = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/signatory-management.php"); ?>

<?= GetDebugMessage() ?>
