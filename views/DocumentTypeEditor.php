<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentTypeEditor = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/document-type-editor.php"); ?>

<?= GetDebugMessage() ?>
