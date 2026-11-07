<?php

namespace PHPMaker2024\FIMS;

// Page object
$AiAssistant = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php include(dirname(__DIR__, 1) . "/app/pages/chat-demo.php"); ?>

<?= GetDebugMessage() ?>
