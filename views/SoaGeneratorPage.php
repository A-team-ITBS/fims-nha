<?php

namespace PHPMaker2024\FIMS;

// Page object
$SoaGeneratorPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/soa-generator-page.php"); ?>
<?= GetDebugMessage() ?>
