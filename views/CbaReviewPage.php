<?php

namespace PHPMaker2024\FIMS;

// Page object
$CbaReviewPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/cba-review-page.php"); ?>
<?= GetDebugMessage() ?>
