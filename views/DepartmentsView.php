<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentsView = &$Page;
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
<form name="fdepartmentsview" id="fdepartmentsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { departments: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fdepartmentsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdepartmentsview")
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
<input type="hidden" name="t" value="departments">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->department_id->Visible) { // department_id ?>
    <tr id="r_department_id"<?= $Page->department_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_department_id"><?= $Page->department_id->caption() ?></span></td>
        <td data-name="department_id"<?= $Page->department_id->cellAttributes() ?>>
<span id="el_departments_department_id">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->department_name->Visible) { // department_name ?>
    <tr id="r_department_name"<?= $Page->department_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_department_name"><?= $Page->department_name->caption() ?></span></td>
        <td data-name="department_name"<?= $Page->department_name->cellAttributes() ?>>
<span id="el_departments_department_name">
<span<?= $Page->department_name->viewAttributes() ?>>
<?= $Page->department_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->description->Visible) { // description ?>
    <tr id="r_description"<?= $Page->description->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_description"><?= $Page->description->caption() ?></span></td>
        <td data-name="description"<?= $Page->description->cellAttributes() ?>>
<span id="el_departments_description">
<span<?= $Page->description->viewAttributes() ?>>
<?= $Page->description->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->parent_department_id->Visible) { // parent_department_id ?>
    <tr id="r_parent_department_id"<?= $Page->parent_department_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_parent_department_id"><?= $Page->parent_department_id->caption() ?></span></td>
        <td data-name="parent_department_id"<?= $Page->parent_department_id->cellAttributes() ?>>
<span id="el_departments_parent_department_id">
<span<?= $Page->parent_department_id->viewAttributes() ?>>
<?= $Page->parent_department_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->department_head_id->Visible) { // department_head_id ?>
    <tr id="r_department_head_id"<?= $Page->department_head_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_department_head_id"><?= $Page->department_head_id->caption() ?></span></td>
        <td data-name="department_head_id"<?= $Page->department_head_id->cellAttributes() ?>>
<span id="el_departments_department_head_id">
<span<?= $Page->department_head_id->viewAttributes() ?>>
<?= $Page->department_head_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->department_code->Visible) { // department_code ?>
    <tr id="r_department_code"<?= $Page->department_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_department_code"><?= $Page->department_code->caption() ?></span></td>
        <td data-name="department_code"<?= $Page->department_code->cellAttributes() ?>>
<span id="el_departments_department_code">
<span<?= $Page->department_code->viewAttributes() ?>>
<?= $Page->department_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->department_acronym->Visible) { // department_acronym ?>
    <tr id="r_department_acronym"<?= $Page->department_acronym->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_department_acronym"><?= $Page->department_acronym->caption() ?></span></td>
        <td data-name="department_acronym"<?= $Page->department_acronym->cellAttributes() ?>>
<span id="el_departments_department_acronym">
<span<?= $Page->department_acronym->viewAttributes() ?>>
<?= $Page->department_acronym->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_location->Visible) { // office_location ?>
    <tr id="r_office_location"<?= $Page->office_location->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_office_location"><?= $Page->office_location->caption() ?></span></td>
        <td data-name="office_location"<?= $Page->office_location->cellAttributes() ?>>
<span id="el_departments_office_location">
<span<?= $Page->office_location->viewAttributes() ?>>
<?= $Page->office_location->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->contact_number->Visible) { // contact_number ?>
    <tr id="r_contact_number"<?= $Page->contact_number->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_contact_number"><?= $Page->contact_number->caption() ?></span></td>
        <td data-name="contact_number"<?= $Page->contact_number->cellAttributes() ?>>
<span id="el_departments_contact_number">
<span<?= $Page->contact_number->viewAttributes() ?>>
<?= $Page->contact_number->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
    <tr id="r__email"<?= $Page->_email->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments__email"><?= $Page->_email->caption() ?></span></td>
        <td data-name="_email"<?= $Page->_email->cellAttributes() ?>>
<span id="el_departments__email">
<span<?= $Page->_email->viewAttributes() ?>>
<?= $Page->_email->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_departments_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_departments_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <tr id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_updated_at"><?= $Page->updated_at->caption() ?></span></td>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_departments_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
    <tr id="r_rc_id"<?= $Page->rc_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_rc_id"><?= $Page->rc_id->caption() ?></span></td>
        <td data-name="rc_id"<?= $Page->rc_id->cellAttributes() ?>>
<span id="el_departments_rc_id">
<span<?= $Page->rc_id->viewAttributes() ?>>
<?= $Page->rc_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
    <tr id="r_office_id"<?= $Page->office_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_office_id"><?= $Page->office_id->caption() ?></span></td>
        <td data-name="office_id"<?= $Page->office_id->cellAttributes() ?>>
<span id="el_departments_office_id">
<span<?= $Page->office_id->viewAttributes() ?>>
<?= $Page->office_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->department_type->Visible) { // department_type ?>
    <tr id="r_department_type"<?= $Page->department_type->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_department_type"><?= $Page->department_type->caption() ?></span></td>
        <td data-name="department_type"<?= $Page->department_type->cellAttributes() ?>>
<span id="el_departments_department_type">
<span<?= $Page->department_type->viewAttributes() ?>>
<?= $Page->department_type->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->hierarchy_level->Visible) { // hierarchy_level ?>
    <tr id="r_hierarchy_level"<?= $Page->hierarchy_level->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_hierarchy_level"><?= $Page->hierarchy_level->caption() ?></span></td>
        <td data-name="hierarchy_level"<?= $Page->hierarchy_level->cellAttributes() ?>>
<span id="el_departments_hierarchy_level">
<span<?= $Page->hierarchy_level->viewAttributes() ?>>
<?= $Page->hierarchy_level->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_budget_unit->Visible) { // is_budget_unit ?>
    <tr id="r_is_budget_unit"<?= $Page->is_budget_unit->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_departments_is_budget_unit"><?= $Page->is_budget_unit->caption() ?></span></td>
        <td data-name="is_budget_unit"<?= $Page->is_budget_unit->cellAttributes() ?>>
<span id="el_departments_is_budget_unit">
<span<?= $Page->is_budget_unit->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_budget_unit->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
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
