<?php

namespace PHPMaker2024\FIMS;

// Page object
$FundSourcingDashboardPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/fund-sourcing-dashboard-page.php"); ?>

<?= GetDebugMessage() ?>
