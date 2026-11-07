<?php

namespace PHPMaker2024\FIMS;

// Page object
$BullMqManager = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/bullmq-manager.php"); ?>

<?= GetDebugMessage() ?>
