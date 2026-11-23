<?php

namespace PHPMaker2024\FIMS;

// Page object
$FixedAssetsPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/fixed-assets-page.php"); ?>
<?= GetDebugMessage() ?>
