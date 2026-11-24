<?php

namespace PHPMaker2024\FIMS;

// Page object
$BurPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/bur-page.php"); ?>
<?= GetDebugMessage() ?>
