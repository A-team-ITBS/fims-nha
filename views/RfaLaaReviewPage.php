<?php

namespace PHPMaker2024\FIMS;

// Page object
$RfaLaaReviewPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/rfa-laa-review-page.php"); ?>
<?= GetDebugMessage() ?>
