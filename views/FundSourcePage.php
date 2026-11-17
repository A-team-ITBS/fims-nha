<?php

namespace PHPMaker2024\FIMS;

// Page object
$FundSourcePage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/fund-source-page.php"); ?>
<?= GetDebugMessage() ?>
