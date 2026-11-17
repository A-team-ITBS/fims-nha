<?php

namespace PHPMaker2024\FIMS;

// Page object
$BudgetProposalReviewPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/budget-proposal-review-page.php"); ?>
<?= GetDebugMessage() ?>
