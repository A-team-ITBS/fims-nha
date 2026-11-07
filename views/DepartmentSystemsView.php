<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentSystemsView = &$Page;
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
<form name="fdepartment_systemsview" id="fdepartment_systemsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { department_systems: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fdepartment_systemsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdepartment_systemsview")
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
<input type="hidden" name="t" value="department_systems">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->department_system_id->Visible) { // department_system_id ?>
    <tr id="r_department_system_id"<?= $Page->department_system_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_department_systems_department_system_id"><?= $Page->department_system_id->caption() ?></span></td>
        <td data-name="department_system_id"<?= $Page->department_system_id->cellAttributes() ?>>
<span id="el_department_systems_department_system_id">
<span<?= $Page->department_system_id->viewAttributes() ?>>
<?= $Page->department_system_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
    <tr id="r_department_id"<?= $Page->department_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_department_systems_department_id"><?= $Page->department_id->caption() ?></span></td>
        <td data-name="department_id"<?= $Page->department_id->cellAttributes() ?>>
<span id="el_department_systems_department_id">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->system_id->Visible) { // system_id ?>
    <tr id="r_system_id"<?= $Page->system_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_department_systems_system_id"><?= $Page->system_id->caption() ?></span></td>
        <td data-name="system_id"<?= $Page->system_id->cellAttributes() ?>>
<span id="el_department_systems_system_id">
<span<?= $Page->system_id->viewAttributes() ?>>
<?= $Page->system_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_department_systems_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_department_systems_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->access_level->Visible) { // access_level ?>
    <tr id="r_access_level"<?= $Page->access_level->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_department_systems_access_level"><?= $Page->access_level->caption() ?></span></td>
        <td data-name="access_level"<?= $Page->access_level->cellAttributes() ?>>
<span id="el_department_systems_access_level">
<span<?= $Page->access_level->viewAttributes() ?>>
<?= $Page->access_level->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->assigned_by->Visible) { // assigned_by ?>
    <tr id="r_assigned_by"<?= $Page->assigned_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_department_systems_assigned_by"><?= $Page->assigned_by->caption() ?></span></td>
        <td data-name="assigned_by"<?= $Page->assigned_by->cellAttributes() ?>>
<span id="el_department_systems_assigned_by">
<span<?= $Page->assigned_by->viewAttributes() ?>>
<?= $Page->assigned_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->assigned_at->Visible) { // assigned_at ?>
    <tr id="r_assigned_at"<?= $Page->assigned_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_department_systems_assigned_at"><?= $Page->assigned_at->caption() ?></span></td>
        <td data-name="assigned_at"<?= $Page->assigned_at->cellAttributes() ?>>
<span id="el_department_systems_assigned_at">
<span<?= $Page->assigned_at->viewAttributes() ?>>
<?= $Page->assigned_at->getViewValue() ?></span>
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
