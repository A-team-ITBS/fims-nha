<?php

namespace PHPMaker2024\FIMS;

// Page object
$PageEditor = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/editor-page.php"); ?>

<?= GetDebugMessage() ?>
