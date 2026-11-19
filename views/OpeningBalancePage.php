<?php
$OpeningBalancePage = &$Page;
?>
<?php $Page->showMessage(); ?>
<?php include(dirname(__DIR__, 1) . "/app/pages/opening-balance-page.php"); ?>
<?php if (function_exists('GetDebugMessage')) { echo GetDebugMessage(); } ?>
