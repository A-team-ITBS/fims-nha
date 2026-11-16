<?php

namespace PHPMaker2024\FIMS;

// Page object
$LiquidationVoucherPage = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/liquidation-voucher-page.php"); ?>
<?= GetDebugMessage() ?>
