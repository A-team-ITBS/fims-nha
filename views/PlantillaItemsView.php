<?php

namespace PHPMaker2024\FIMS;

// Page object
$PlantillaItemsView = &$Page;
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
<form name="fplantilla_itemsview" id="fplantilla_itemsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { plantilla_items: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fplantilla_itemsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fplantilla_itemsview")
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
<input type="hidden" name="t" value="plantilla_items">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->plantilla_item_id->Visible) { // plantilla_item_id ?>
    <tr id="r_plantilla_item_id"<?= $Page->plantilla_item_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_plantilla_item_id"><?= $Page->plantilla_item_id->caption() ?></span></td>
        <td data-name="plantilla_item_id"<?= $Page->plantilla_item_id->cellAttributes() ?>>
<span id="el_plantilla_items_plantilla_item_id">
<span<?= $Page->plantilla_item_id->viewAttributes() ?>>
<?= $Page->plantilla_item_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
    <tr id="r_department_id"<?= $Page->department_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_department_id"><?= $Page->department_id->caption() ?></span></td>
        <td data-name="department_id"<?= $Page->department_id->cellAttributes() ?>>
<span id="el_plantilla_items_department_id">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->position_id->Visible) { // position_id ?>
    <tr id="r_position_id"<?= $Page->position_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_position_id"><?= $Page->position_id->caption() ?></span></td>
        <td data-name="position_id"<?= $Page->position_id->cellAttributes() ?>>
<span id="el_plantilla_items_position_id">
<span<?= $Page->position_id->viewAttributes() ?>>
<?= $Page->position_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->item_number->Visible) { // item_number ?>
    <tr id="r_item_number"<?= $Page->item_number->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_item_number"><?= $Page->item_number->caption() ?></span></td>
        <td data-name="item_number"<?= $Page->item_number->cellAttributes() ?>>
<span id="el_plantilla_items_item_number">
<span<?= $Page->item_number->viewAttributes() ?>>
<?= $Page->item_number->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->item_description->Visible) { // item_description ?>
    <tr id="r_item_description"<?= $Page->item_description->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_item_description"><?= $Page->item_description->caption() ?></span></td>
        <td data-name="item_description"<?= $Page->item_description->cellAttributes() ?>>
<span id="el_plantilla_items_item_description">
<span<?= $Page->item_description->viewAttributes() ?>>
<?= $Page->item_description->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->authorized_count->Visible) { // authorized_count ?>
    <tr id="r_authorized_count"<?= $Page->authorized_count->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_authorized_count"><?= $Page->authorized_count->caption() ?></span></td>
        <td data-name="authorized_count"<?= $Page->authorized_count->cellAttributes() ?>>
<span id="el_plantilla_items_authorized_count">
<span<?= $Page->authorized_count->viewAttributes() ?>>
<?= $Page->authorized_count->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_plantilla_items_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->effective_date->Visible) { // effective_date ?>
    <tr id="r_effective_date"<?= $Page->effective_date->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_effective_date"><?= $Page->effective_date->caption() ?></span></td>
        <td data-name="effective_date"<?= $Page->effective_date->cellAttributes() ?>>
<span id="el_plantilla_items_effective_date">
<span<?= $Page->effective_date->viewAttributes() ?>>
<?= $Page->effective_date->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->abolition_date->Visible) { // abolition_date ?>
    <tr id="r_abolition_date"<?= $Page->abolition_date->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_abolition_date"><?= $Page->abolition_date->caption() ?></span></td>
        <td data-name="abolition_date"<?= $Page->abolition_date->cellAttributes() ?>>
<span id="el_plantilla_items_abolition_date">
<span<?= $Page->abolition_date->viewAttributes() ?>>
<?= $Page->abolition_date->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->annual_budget_allocation->Visible) { // annual_budget_allocation ?>
    <tr id="r_annual_budget_allocation"<?= $Page->annual_budget_allocation->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_annual_budget_allocation"><?= $Page->annual_budget_allocation->caption() ?></span></td>
        <td data-name="annual_budget_allocation"<?= $Page->annual_budget_allocation->cellAttributes() ?>>
<span id="el_plantilla_items_annual_budget_allocation">
<span<?= $Page->annual_budget_allocation->viewAttributes() ?>>
<?= $Page->annual_budget_allocation->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->monthly_salary_allocation->Visible) { // monthly_salary_allocation ?>
    <tr id="r_monthly_salary_allocation"<?= $Page->monthly_salary_allocation->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_monthly_salary_allocation"><?= $Page->monthly_salary_allocation->caption() ?></span></td>
        <td data-name="monthly_salary_allocation"<?= $Page->monthly_salary_allocation->cellAttributes() ?>>
<span id="el_plantilla_items_monthly_salary_allocation">
<span<?= $Page->monthly_salary_allocation->viewAttributes() ?>>
<?= $Page->monthly_salary_allocation->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->remarks->Visible) { // remarks ?>
    <tr id="r_remarks"<?= $Page->remarks->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_remarks"><?= $Page->remarks->caption() ?></span></td>
        <td data-name="remarks"<?= $Page->remarks->cellAttributes() ?>>
<span id="el_plantilla_items_remarks">
<span<?= $Page->remarks->viewAttributes() ?>>
<?= $Page->remarks->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <tr id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_created_by"><?= $Page->created_by->caption() ?></span></td>
        <td data-name="created_by"<?= $Page->created_by->cellAttributes() ?>>
<span id="el_plantilla_items_created_by">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->approved_by->Visible) { // approved_by ?>
    <tr id="r_approved_by"<?= $Page->approved_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_approved_by"><?= $Page->approved_by->caption() ?></span></td>
        <td data-name="approved_by"<?= $Page->approved_by->cellAttributes() ?>>
<span id="el_plantilla_items_approved_by">
<span<?= $Page->approved_by->viewAttributes() ?>>
<?= $Page->approved_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_plantilla_items_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <tr id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_plantilla_items_updated_at"><?= $Page->updated_at->caption() ?></span></td>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_plantilla_items_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
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
