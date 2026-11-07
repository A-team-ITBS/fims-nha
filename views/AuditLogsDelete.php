<?php

namespace PHPMaker2024\FIMS;

// Page object
$AuditLogsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { audit_logs: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var faudit_logsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("faudit_logsdelete")
        .setPageId("delete")
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
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<form name="faudit_logsdelete" id="faudit_logsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="audit_logs">
<input type="hidden" name="action" id="action" value="delete">
<?php foreach ($Page->RecKeys as $key) { ?>
<?php $keyvalue = is_array($key) ? implode(Config("COMPOSITE_KEY_SEPARATOR"), $key) : $key; ?>
<input type="hidden" name="key_m[]" value="<?= HtmlEncode($keyvalue) ?>">
<?php } ?>
<div class="card ew-card ew-grid <?= $Page->TableGridClass ?>">
<div class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<table class="<?= $Page->TableClass ?>">
    <thead>
    <tr class="ew-table-header">
<?php if ($Page->id->Visible) { // id ?>
        <th class="<?= $Page->id->headerCellClass() ?>"><span id="elh_audit_logs_id" class="audit_logs_id"><?= $Page->id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->date_time->Visible) { // date_time ?>
        <th class="<?= $Page->date_time->headerCellClass() ?>"><span id="elh_audit_logs_date_time" class="audit_logs_date_time"><?= $Page->date_time->caption() ?></span></th>
<?php } ?>
<?php if ($Page->script->Visible) { // script ?>
        <th class="<?= $Page->script->headerCellClass() ?>"><span id="elh_audit_logs_script" class="audit_logs_script"><?= $Page->script->caption() ?></span></th>
<?php } ?>
<?php if ($Page->user->Visible) { // user ?>
        <th class="<?= $Page->user->headerCellClass() ?>"><span id="elh_audit_logs_user" class="audit_logs_user"><?= $Page->user->caption() ?></span></th>
<?php } ?>
<?php if ($Page->_action->Visible) { // action ?>
        <th class="<?= $Page->_action->headerCellClass() ?>"><span id="elh_audit_logs__action" class="audit_logs__action"><?= $Page->_action->caption() ?></span></th>
<?php } ?>
<?php if ($Page->_table->Visible) { // table ?>
        <th class="<?= $Page->_table->headerCellClass() ?>"><span id="elh_audit_logs__table" class="audit_logs__table"><?= $Page->_table->caption() ?></span></th>
<?php } ?>
<?php if ($Page->field->Visible) { // field ?>
        <th class="<?= $Page->field->headerCellClass() ?>"><span id="elh_audit_logs_field" class="audit_logs_field"><?= $Page->field->caption() ?></span></th>
<?php } ?>
    </tr>
    </thead>
    <tbody>
<?php
$Page->RecordCount = 0;
$i = 0;
while ($Page->fetch()) {
    $Page->RecordCount++;
    $Page->RowCount++;

    // Set row properties
    $Page->resetAttributes();
    $Page->RowType = RowType::VIEW; // View

    // Get the field contents
    $Page->loadRowValues($Page->CurrentRow);

    // Render row
    $Page->renderRow();
?>
    <tr <?= $Page->rowAttributes() ?>>
<?php if ($Page->id->Visible) { // id ?>
        <td<?= $Page->id->cellAttributes() ?>>
<span id="">
<span<?= $Page->id->viewAttributes() ?>>
<?= $Page->id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->date_time->Visible) { // date_time ?>
        <td<?= $Page->date_time->cellAttributes() ?>>
<span id="">
<span<?= $Page->date_time->viewAttributes() ?>>
<?= $Page->date_time->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->script->Visible) { // script ?>
        <td<?= $Page->script->cellAttributes() ?>>
<span id="">
<span<?= $Page->script->viewAttributes() ?>>
<?= $Page->script->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->user->Visible) { // user ?>
        <td<?= $Page->user->cellAttributes() ?>>
<span id="">
<span<?= $Page->user->viewAttributes() ?>>
<?= $Page->user->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->_action->Visible) { // action ?>
        <td<?= $Page->_action->cellAttributes() ?>>
<span id="">
<span<?= $Page->_action->viewAttributes() ?>>
<?= $Page->_action->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->_table->Visible) { // table ?>
        <td<?= $Page->_table->cellAttributes() ?>>
<span id="">
<span<?= $Page->_table->viewAttributes() ?>>
<?= $Page->_table->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->field->Visible) { // field ?>
        <td<?= $Page->field->cellAttributes() ?>>
<span id="">
<span<?= $Page->field->viewAttributes() ?>>
<?= $Page->field->getViewValue() ?></span>
</span>
</td>
<?php } ?>
    </tr>
<?php
}
$Page->Recordset?->free();
?>
</tbody>
</table>
</div>
</div>
<div class="ew-buttons ew-desktop-buttons">
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit"><?= $Language->phrase("DeleteBtn") ?></button>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
</div>
</form>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
