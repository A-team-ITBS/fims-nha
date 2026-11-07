<?php

namespace PHPMaker2024\FIMS;

// Page object
$PsgcMap = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/psgc-map.php"); ?>

<?= GetDebugMessage() ?>
