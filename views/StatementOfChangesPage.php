<?php

namespace PHPMaker2024\FIMS;

// Page object
$StatementOfChangesPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/statement-of-changes-page.php"); ?>
<?= GetDebugMessage() ?>
