<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { departments: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fdepartmentsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdepartmentsdelete")
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
<form name="fdepartmentsdelete" id="fdepartmentsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="departments">
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
<?php if ($Page->department_id->Visible) { // department_id ?>
        <th class="<?= $Page->department_id->headerCellClass() ?>"><span id="elh_departments_department_id" class="departments_department_id"><?= $Page->department_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_name->Visible) { // department_name ?>
        <th class="<?= $Page->department_name->headerCellClass() ?>"><span id="elh_departments_department_name" class="departments_department_name"><?= $Page->department_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->parent_department_id->Visible) { // parent_department_id ?>
        <th class="<?= $Page->parent_department_id->headerCellClass() ?>"><span id="elh_departments_parent_department_id" class="departments_parent_department_id"><?= $Page->parent_department_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_head_id->Visible) { // department_head_id ?>
        <th class="<?= $Page->department_head_id->headerCellClass() ?>"><span id="elh_departments_department_head_id" class="departments_department_head_id"><?= $Page->department_head_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_code->Visible) { // department_code ?>
        <th class="<?= $Page->department_code->headerCellClass() ?>"><span id="elh_departments_department_code" class="departments_department_code"><?= $Page->department_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_acronym->Visible) { // department_acronym ?>
        <th class="<?= $Page->department_acronym->headerCellClass() ?>"><span id="elh_departments_department_acronym" class="departments_department_acronym"><?= $Page->department_acronym->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_location->Visible) { // office_location ?>
        <th class="<?= $Page->office_location->headerCellClass() ?>"><span id="elh_departments_office_location" class="departments_office_location"><?= $Page->office_location->caption() ?></span></th>
<?php } ?>
<?php if ($Page->contact_number->Visible) { // contact_number ?>
        <th class="<?= $Page->contact_number->headerCellClass() ?>"><span id="elh_departments_contact_number" class="departments_contact_number"><?= $Page->contact_number->caption() ?></span></th>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
        <th class="<?= $Page->_email->headerCellClass() ?>"><span id="elh_departments__email" class="departments__email"><?= $Page->_email->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_departments_is_active" class="departments_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_departments_created_at" class="departments_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th class="<?= $Page->updated_at->headerCellClass() ?>"><span id="elh_departments_updated_at" class="departments_updated_at"><?= $Page->updated_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <th class="<?= $Page->rc_id->headerCellClass() ?>"><span id="elh_departments_rc_id" class="departments_rc_id"><?= $Page->rc_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
        <th class="<?= $Page->office_id->headerCellClass() ?>"><span id="elh_departments_office_id" class="departments_office_id"><?= $Page->office_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_type->Visible) { // department_type ?>
        <th class="<?= $Page->department_type->headerCellClass() ?>"><span id="elh_departments_department_type" class="departments_department_type"><?= $Page->department_type->caption() ?></span></th>
<?php } ?>
<?php if ($Page->hierarchy_level->Visible) { // hierarchy_level ?>
        <th class="<?= $Page->hierarchy_level->headerCellClass() ?>"><span id="elh_departments_hierarchy_level" class="departments_hierarchy_level"><?= $Page->hierarchy_level->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_budget_unit->Visible) { // is_budget_unit ?>
        <th class="<?= $Page->is_budget_unit->headerCellClass() ?>"><span id="elh_departments_is_budget_unit" class="departments_is_budget_unit"><?= $Page->is_budget_unit->caption() ?></span></th>
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
<?php if ($Page->department_id->Visible) { // department_id ?>
        <td<?= $Page->department_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->department_name->Visible) { // department_name ?>
        <td<?= $Page->department_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_name->viewAttributes() ?>>
<?= $Page->department_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->parent_department_id->Visible) { // parent_department_id ?>
        <td<?= $Page->parent_department_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->parent_department_id->viewAttributes() ?>>
<?= $Page->parent_department_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->department_head_id->Visible) { // department_head_id ?>
        <td<?= $Page->department_head_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_head_id->viewAttributes() ?>>
<?= $Page->department_head_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->department_code->Visible) { // department_code ?>
        <td<?= $Page->department_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_code->viewAttributes() ?>>
<?= $Page->department_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->department_acronym->Visible) { // department_acronym ?>
        <td<?= $Page->department_acronym->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_acronym->viewAttributes() ?>>
<?= $Page->department_acronym->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_location->Visible) { // office_location ?>
        <td<?= $Page->office_location->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_location->viewAttributes() ?>>
<?= $Page->office_location->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->contact_number->Visible) { // contact_number ?>
        <td<?= $Page->contact_number->cellAttributes() ?>>
<span id="">
<span<?= $Page->contact_number->viewAttributes() ?>>
<?= $Page->contact_number->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
        <td<?= $Page->_email->cellAttributes() ?>>
<span id="">
<span<?= $Page->_email->viewAttributes() ?>>
<?= $Page->_email->getViewValue() ?></span>
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
<?php if ($Page->created_at->Visible) { // created_at ?>
        <td<?= $Page->created_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <td<?= $Page->updated_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <td<?= $Page->rc_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->rc_id->viewAttributes() ?>>
<?= $Page->rc_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
        <td<?= $Page->office_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_id->viewAttributes() ?>>
<?= $Page->office_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->department_type->Visible) { // department_type ?>
        <td<?= $Page->department_type->cellAttributes() ?>>
<span id="">
<span<?= $Page->department_type->viewAttributes() ?>>
<?= $Page->department_type->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->hierarchy_level->Visible) { // hierarchy_level ?>
        <td<?= $Page->hierarchy_level->cellAttributes() ?>>
<span id="">
<span<?= $Page->hierarchy_level->viewAttributes() ?>>
<?= $Page->hierarchy_level->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->is_budget_unit->Visible) { // is_budget_unit ?>
        <td<?= $Page->is_budget_unit->cellAttributes() ?>>
<span id="">
<span<?= $Page->is_budget_unit->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_budget_unit->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
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
