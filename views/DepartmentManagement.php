<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentManagement = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/department-management.php"); ?>

<?= GetDebugMessage() ?>
