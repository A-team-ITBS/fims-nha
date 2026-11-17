<?php

namespace PHPMaker2024\FIMS;

// Page object
$FidelityBondPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/fidelity-bond-page.php"); ?>
<?= GetDebugMessage() ?>
