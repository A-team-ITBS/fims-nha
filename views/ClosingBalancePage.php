<?php
$ClosingBalancePage = &$Page;
?>
<?php $Page->showMessage(); ?>
<?php include(dirname(__DIR__, 1) . "/app/pages/closing-balance-page.php"); ?>
<?php if (function_exists('GetDebugMessage')) { echo GetDebugMessage(); } ?>
