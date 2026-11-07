<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentSystemsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { department_systems: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fdepartment_systemsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdepartment_systemsdelete")
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
<form name="fdepartment_systemsdelete" id="fdepartment_systemsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="department_systems">
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
<?php if ($Page->department_system_id->Visible) { // department_system_id ?>
        <th class="<?= $Page->department_system_id->headerCellClass() ?>"><span id="elh_department_systems_department_system_id" class="department_systems_department_system_id"><?= $Page->department_system_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
        <th class="<?= $Page->department_id->headerCellClass() ?>"><span id="elh_department_systems_department_id" class="department_systems_department_id"><?= $Page->department_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->system_id->Visible) { // system_id ?>
        <th class="<?= $Page->system_id->headerCellClass() ?>"><span id="elh_department_systems_system_id" class="department_systems_system_id"><?= $Page->system_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_department_systems_is_active" class="department_systems_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->access_level->Visible) { // access_level ?>
        <th class="<?= $Page->access_level->headerCellClass() ?>"><span id="elh_department_systems_access_level" class="department_systems_access_level"><?= $Page->access_level->caption() ?></span></th>
<?php } ?>
<?php if ($Page->assigned_by->Visible) { // assigned_by ?>
        <th class="<?= $Page->assigned_by->headerCellClass() ?>"><span id="elh_department_systems_assigned_by" class="department_systems_assigned_by"><?= $Page->assigned_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->assigned_at->Visible) { // assigned_at ?>
        <th class="<?= $Page->assigned_at->headerCellClass() ?>"><span id="elh_department_systems_assigned_at" class="department_systems_assigned_at"><?= $Page->assigned_at->caption() ?></span></th>
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
<?php if ($Page->department_system_id->Visible) { // department_system_id ?>
        <td<?= $Page->department_system_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_system_id->viewAttributes() ?>>
<?= $Page->department_system_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
        <td<?= $Page->department_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->system_id->Visible) { // system_id ?>
        <td<?= $Page->system_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->system_id->viewAttributes() ?>>
<?= $Page->system_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <td<?= $Page->is_active->cellAttributes() ?>>
<span id="">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->access_level->Visible) { // access_level ?>
        <td<?= $Page->access_level->cellAttributes() ?>>
<span id="">
<span<?= $Page->access_level->viewAttributes() ?>>
<?= $Page->access_level->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->assigned_by->Visible) { // assigned_by ?>
        <td<?= $Page->assigned_by->cellAttributes() ?>>
<span id="">
<span<?= $Page->assigned_by->viewAttributes() ?>>
<?= $Page->assigned_by->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->assigned_at->Visible) { // assigned_at ?>
        <td<?= $Page->assigned_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->assigned_at->viewAttributes() ?>>
<?= $Page->assigned_at->getViewValue() ?></span>
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
