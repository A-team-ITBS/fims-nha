<?php

namespace PHPMaker2024\FIMS;

// Page object
$DvReviewPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/dv-review-page.php"); ?>
<?= GetDebugMessage() ?>
