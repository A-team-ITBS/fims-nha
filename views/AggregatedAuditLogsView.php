<?php

namespace PHPMaker2024\FIMS;

// Page object
$AggregatedAuditLogsView = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<div class="btn-toolbar ew-toolbar">
<?php $Page->ExportOptions->render("body") ?>
<?php $Page->OtherOptions->render("body") ?>
</div>
<?php } ?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="view">
<form name="faggregated_audit_logsview" id="faggregated_audit_logsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { aggregated_audit_logs: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var faggregated_audit_logsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("faggregated_audit_logsview")
        .setPageId("view")
        .build();
    window[form.id] = form;
    currentForm = form;
    loadjs.done(form.id);
});
</script>
<script>
loadjs.ready("head", function () {
    // Write your table-specific client script here, no need to add script tags.
});
</script>
<?php } ?>
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="aggregated_audit_logs">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->aggregated_id->Visible) { // aggregated_id ?>
    <tr id="r_aggregated_id"<?= $Page->aggregated_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs_aggregated_id"><?= $Page->aggregated_id->caption() ?></span></td>
        <td data-name="aggregated_id"<?= $Page->aggregated_id->cellAttributes() ?>>
<span id="el_aggregated_audit_logs_aggregated_id">
<span<?= $Page->aggregated_id->viewAttributes() ?>>
<?= $Page->aggregated_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->action_date->Visible) { // action_date ?>
    <tr id="r_action_date"<?= $Page->action_date->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs_action_date"><?= $Page->action_date->caption() ?></span></td>
        <td data-name="action_date"<?= $Page->action_date->cellAttributes() ?>>
<span id="el_aggregated_audit_logs_action_date">
<span<?= $Page->action_date->viewAttributes() ?>>
<?= $Page->action_date->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->script->Visible) { // script ?>
    <tr id="r_script"<?= $Page->script->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs_script"><?= $Page->script->caption() ?></span></td>
        <td data-name="script"<?= $Page->script->cellAttributes() ?>>
<span id="el_aggregated_audit_logs_script">
<span<?= $Page->script->viewAttributes() ?>>
<?= $Page->script->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->user->Visible) { // user ?>
    <tr id="r_user"<?= $Page->user->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs_user"><?= $Page->user->caption() ?></span></td>
        <td data-name="user"<?= $Page->user->cellAttributes() ?>>
<span id="el_aggregated_audit_logs_user">
<span<?= $Page->user->viewAttributes() ?>>
<?= $Page->user->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->_action->Visible) { // action ?>
    <tr id="r__action"<?= $Page->_action->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs__action"><?= $Page->_action->caption() ?></span></td>
        <td data-name="_action"<?= $Page->_action->cellAttributes() ?>>
<span id="el_aggregated_audit_logs__action">
<span<?= $Page->_action->viewAttributes() ?>>
<?= $Page->_action->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->_table->Visible) { // table ?>
    <tr id="r__table"<?= $Page->_table->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs__table"><?= $Page->_table->caption() ?></span></td>
        <td data-name="_table"<?= $Page->_table->cellAttributes() ?>>
<span id="el_aggregated_audit_logs__table">
<span<?= $Page->_table->viewAttributes() ?>>
<?= $Page->_table->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->action_type->Visible) { // action_type ?>
    <tr id="r_action_type"<?= $Page->action_type->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs_action_type"><?= $Page->action_type->caption() ?></span></td>
        <td data-name="action_type"<?= $Page->action_type->cellAttributes() ?>>
<span id="el_aggregated_audit_logs_action_type">
<span<?= $Page->action_type->viewAttributes() ?>>
<?= $Page->action_type->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->details->Visible) { // details ?>
    <tr id="r_details"<?= $Page->details->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs_details"><?= $Page->details->caption() ?></span></td>
        <td data-name="details"<?= $Page->details->cellAttributes() ?>>
<span id="el_aggregated_audit_logs_details">
<span<?= $Page->details->viewAttributes() ?>>
<?= $Page->details->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->action_count->Visible) { // action_count ?>
    <tr id="r_action_count"<?= $Page->action_count->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_aggregated_audit_logs_action_count"><?= $Page->action_count->caption() ?></span></td>
        <td data-name="action_count"<?= $Page->action_count->cellAttributes() ?>>
<span id="el_aggregated_audit_logs_action_count">
<span<?= $Page->action_count->viewAttributes() ?>>
<?= $Page->action_count->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
</table>
</form>
</main>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<?php if (!$Page->isExport()) { ?>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
