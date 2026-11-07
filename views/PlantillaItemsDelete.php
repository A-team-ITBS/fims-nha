<?php

namespace PHPMaker2024\FIMS;

// Page object
$PlantillaItemsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { plantilla_items: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fplantilla_itemsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fplantilla_itemsdelete")
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
<form name="fplantilla_itemsdelete" id="fplantilla_itemsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="plantilla_items">
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
<?php if ($Page->plantilla_item_id->Visible) { // plantilla_item_id ?>
        <th class="<?= $Page->plantilla_item_id->headerCellClass() ?>"><span id="elh_plantilla_items_plantilla_item_id" class="plantilla_items_plantilla_item_id"><?= $Page->plantilla_item_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
        <th class="<?= $Page->department_id->headerCellClass() ?>"><span id="elh_plantilla_items_department_id" class="plantilla_items_department_id"><?= $Page->department_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->position_id->Visible) { // position_id ?>
        <th class="<?= $Page->position_id->headerCellClass() ?>"><span id="elh_plantilla_items_position_id" class="plantilla_items_position_id"><?= $Page->position_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->item_number->Visible) { // item_number ?>
        <th class="<?= $Page->item_number->headerCellClass() ?>"><span id="elh_plantilla_items_item_number" class="plantilla_items_item_number"><?= $Page->item_number->caption() ?></span></th>
<?php } ?>
<?php if ($Page->item_description->Visible) { // item_description ?>
        <th class="<?= $Page->item_description->headerCellClass() ?>"><span id="elh_plantilla_items_item_description" class="plantilla_items_item_description"><?= $Page->item_description->caption() ?></span></th>
<?php } ?>
<?php if ($Page->authorized_count->Visible) { // authorized_count ?>
        <th class="<?= $Page->authorized_count->headerCellClass() ?>"><span id="elh_plantilla_items_authorized_count" class="plantilla_items_authorized_count"><?= $Page->authorized_count->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_plantilla_items_is_active" class="plantilla_items_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->effective_date->Visible) { // effective_date ?>
        <th class="<?= $Page->effective_date->headerCellClass() ?>"><span id="elh_plantilla_items_effective_date" class="plantilla_items_effective_date"><?= $Page->effective_date->caption() ?></span></th>
<?php } ?>
<?php if ($Page->abolition_date->Visible) { // abolition_date ?>
        <th class="<?= $Page->abolition_date->headerCellClass() ?>"><span id="elh_plantilla_items_abolition_date" class="plantilla_items_abolition_date"><?= $Page->abolition_date->caption() ?></span></th>
<?php } ?>
<?php if ($Page->annual_budget_allocation->Visible) { // annual_budget_allocation ?>
        <th class="<?= $Page->annual_budget_allocation->headerCellClass() ?>"><span id="elh_plantilla_items_annual_budget_allocation" class="plantilla_items_annual_budget_allocation"><?= $Page->annual_budget_allocation->caption() ?></span></th>
<?php } ?>
<?php if ($Page->monthly_salary_allocation->Visible) { // monthly_salary_allocation ?>
        <th class="<?= $Page->monthly_salary_allocation->headerCellClass() ?>"><span id="elh_plantilla_items_monthly_salary_allocation" class="plantilla_items_monthly_salary_allocation"><?= $Page->monthly_salary_allocation->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <th class="<?= $Page->created_by->headerCellClass() ?>"><span id="elh_plantilla_items_created_by" class="plantilla_items_created_by"><?= $Page->created_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->approved_by->Visible) { // approved_by ?>
        <th class="<?= $Page->approved_by->headerCellClass() ?>"><span id="elh_plantilla_items_approved_by" class="plantilla_items_approved_by"><?= $Page->approved_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_plantilla_items_created_at" class="plantilla_items_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th class="<?= $Page->updated_at->headerCellClass() ?>"><span id="elh_plantilla_items_updated_at" class="plantilla_items_updated_at"><?= $Page->updated_at->caption() ?></span></th>
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
<?php if ($Page->plantilla_item_id->Visible) { // plantilla_item_id ?>
        <td<?= $Page->plantilla_item_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->plantilla_item_id->viewAttributes() ?>>
<?= $Page->plantilla_item_id->getViewValue() ?></span>
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
<?php if ($Page->position_id->Visible) { // position_id ?>
        <td<?= $Page->position_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->position_id->viewAttributes() ?>>
<?= $Page->position_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->item_number->Visible) { // item_number ?>
        <td<?= $Page->item_number->cellAttributes() ?>>
<span id="">
<span<?= $Page->item_number->viewAttributes() ?>>
<?= $Page->item_number->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->item_description->Visible) { // item_description ?>
        <td<?= $Page->item_description->cellAttributes() ?>>
<span id="">
<span<?= $Page->item_description->viewAttributes() ?>>
<?= $Page->item_description->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->authorized_count->Visible) { // authorized_count ?>
        <td<?= $Page->authorized_count->cellAttributes() ?>>
<span id="">
<span<?= $Page->authorized_count->viewAttributes() ?>>
<?= $Page->authorized_count->getViewValue() ?></span>
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
<?php if ($Page->effective_date->Visible) { // effective_date ?>
        <td<?= $Page->effective_date->cellAttributes() ?>>
<span id="">
<span<?= $Page->effective_date->viewAttributes() ?>>
<?= $Page->effective_date->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->abolition_date->Visible) { // abolition_date ?>
        <td<?= $Page->abolition_date->cellAttributes() ?>>
<span id="">
<span<?= $Page->abolition_date->viewAttributes() ?>>
<?= $Page->abolition_date->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->annual_budget_allocation->Visible) { // annual_budget_allocation ?>
        <td<?= $Page->annual_budget_allocation->cellAttributes() ?>>
<span id="">
<span<?= $Page->annual_budget_allocation->viewAttributes() ?>>
<?= $Page->annual_budget_allocation->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->monthly_salary_allocation->Visible) { // monthly_salary_allocation ?>
        <td<?= $Page->monthly_salary_allocation->cellAttributes() ?>>
<span id="">
<span<?= $Page->monthly_salary_allocation->viewAttributes() ?>>
<?= $Page->monthly_salary_allocation->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <td<?= $Page->created_by->cellAttributes() ?>>
<span id="">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->approved_by->Visible) { // approved_by ?>
        <td<?= $Page->approved_by->cellAttributes() ?>>
<span id="">
<span<?= $Page->approved_by->viewAttributes() ?>>
<?= $Page->approved_by->getViewValue() ?></span>
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
