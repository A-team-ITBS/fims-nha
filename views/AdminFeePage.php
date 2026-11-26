<?php

namespace PHPMaker2024\FIMS;

// Page object
$AdminFeePage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/admin-fee-page.php"); ?>
<?= GetDebugMessage() ?>
