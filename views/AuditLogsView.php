<?php

namespace PHPMaker2024\FIMS;

// Page object
$AuditLogsView = &$Page;
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
<form name="faudit_logsview" id="faudit_logsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { audit_logs: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var faudit_logsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("faudit_logsview")
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
<input type="hidden" name="t" value="audit_logs">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->id->Visible) { // id ?>
    <tr id="r_id"<?= $Page->id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_id"><?= $Page->id->caption() ?></span></td>
        <td data-name="id"<?= $Page->id->cellAttributes() ?>>
<span id="el_audit_logs_id">
<span<?= $Page->id->viewAttributes() ?>>
<?= $Page->id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->date_time->Visible) { // date_time ?>
    <tr id="r_date_time"<?= $Page->date_time->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_date_time"><?= $Page->date_time->caption() ?></span></td>
        <td data-name="date_time"<?= $Page->date_time->cellAttributes() ?>>
<span id="el_audit_logs_date_time">
<span<?= $Page->date_time->viewAttributes() ?>>
<?= $Page->date_time->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->script->Visible) { // script ?>
    <tr id="r_script"<?= $Page->script->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_script"><?= $Page->script->caption() ?></span></td>
        <td data-name="script"<?= $Page->script->cellAttributes() ?>>
<span id="el_audit_logs_script">
<span<?= $Page->script->viewAttributes() ?>>
<?= $Page->script->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->user->Visible) { // user ?>
    <tr id="r_user"<?= $Page->user->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_user"><?= $Page->user->caption() ?></span></td>
        <td data-name="user"<?= $Page->user->cellAttributes() ?>>
<span id="el_audit_logs_user">
<span<?= $Page->user->viewAttributes() ?>>
<?= $Page->user->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->_action->Visible) { // action ?>
    <tr id="r__action"<?= $Page->_action->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs__action"><?= $Page->_action->caption() ?></span></td>
        <td data-name="_action"<?= $Page->_action->cellAttributes() ?>>
<span id="el_audit_logs__action">
<span<?= $Page->_action->viewAttributes() ?>>
<?= $Page->_action->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->_table->Visible) { // table ?>
    <tr id="r__table"<?= $Page->_table->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs__table"><?= $Page->_table->caption() ?></span></td>
        <td data-name="_table"<?= $Page->_table->cellAttributes() ?>>
<span id="el_audit_logs__table">
<span<?= $Page->_table->viewAttributes() ?>>
<?= $Page->_table->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->field->Visible) { // field ?>
    <tr id="r_field"<?= $Page->field->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_field"><?= $Page->field->caption() ?></span></td>
        <td data-name="field"<?= $Page->field->cellAttributes() ?>>
<span id="el_audit_logs_field">
<span<?= $Page->field->viewAttributes() ?>>
<?= $Page->field->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->key_value->Visible) { // key_value ?>
    <tr id="r_key_value"<?= $Page->key_value->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_key_value"><?= $Page->key_value->caption() ?></span></td>
        <td data-name="key_value"<?= $Page->key_value->cellAttributes() ?>>
<span id="el_audit_logs_key_value">
<span<?= $Page->key_value->viewAttributes() ?>>
<?= $Page->key_value->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->old_value->Visible) { // old_value ?>
    <tr id="r_old_value"<?= $Page->old_value->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_old_value"><?= $Page->old_value->caption() ?></span></td>
        <td data-name="old_value"<?= $Page->old_value->cellAttributes() ?>>
<span id="el_audit_logs_old_value">
<span<?= $Page->old_value->viewAttributes() ?>>
<?= $Page->old_value->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->new_value->Visible) { // new_value ?>
    <tr id="r_new_value"<?= $Page->new_value->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_audit_logs_new_value"><?= $Page->new_value->caption() ?></span></td>
        <td data-name="new_value"<?= $Page->new_value->cellAttributes() ?>>
<span id="el_audit_logs_new_value">
<span<?= $Page->new_value->viewAttributes() ?>>
<?= $Page->new_value->getViewValue() ?></span>
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
