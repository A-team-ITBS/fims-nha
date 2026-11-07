<?php

namespace PHPMaker2024\FIMS;

// Page object
$SystemsView = &$Page;
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
<form name="fsystemsview" id="fsystemsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { systems: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fsystemsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fsystemsview")
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
<input type="hidden" name="t" value="systems">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->system_id->Visible) { // system_id ?>
    <tr id="r_system_id"<?= $Page->system_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_systems_system_id"><?= $Page->system_id->caption() ?></span></td>
        <td data-name="system_id"<?= $Page->system_id->cellAttributes() ?>>
<span id="el_systems_system_id">
<span<?= $Page->system_id->viewAttributes() ?>>
<?= $Page->system_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->system_name->Visible) { // system_name ?>
    <tr id="r_system_name"<?= $Page->system_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_systems_system_name"><?= $Page->system_name->caption() ?></span></td>
        <td data-name="system_name"<?= $Page->system_name->cellAttributes() ?>>
<span id="el_systems_system_name">
<span<?= $Page->system_name->viewAttributes() ?>>
<?= $Page->system_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->system_code->Visible) { // system_code ?>
    <tr id="r_system_code"<?= $Page->system_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_systems_system_code"><?= $Page->system_code->caption() ?></span></td>
        <td data-name="system_code"<?= $Page->system_code->cellAttributes() ?>>
<span id="el_systems_system_code">
<span<?= $Page->system_code->viewAttributes() ?>>
<?= $Page->system_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->description->Visible) { // description ?>
    <tr id="r_description"<?= $Page->description->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_systems_description"><?= $Page->description->caption() ?></span></td>
        <td data-name="description"<?= $Page->description->cellAttributes() ?>>
<span id="el_systems_description">
<span<?= $Page->description->viewAttributes() ?>>
<?= $Page->description->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->level_permissions->Visible) { // level_permissions ?>
    <tr id="r_level_permissions"<?= $Page->level_permissions->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_systems_level_permissions"><?= $Page->level_permissions->caption() ?></span></td>
        <td data-name="level_permissions"<?= $Page->level_permissions->cellAttributes() ?>>
<span id="el_systems_level_permissions">
<span<?= $Page->level_permissions->viewAttributes() ?>>
<?= $Page->level_permissions->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_systems_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_systems_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->url->Visible) { // url ?>
    <tr id="r_url"<?= $Page->url->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_systems_url"><?= $Page->url->caption() ?></span></td>
        <td data-name="url"<?= $Page->url->cellAttributes() ?>>
<span id="el_systems_url">
<span<?= $Page->url->viewAttributes() ?>>
<?= $Page->url->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
</table>
<?php
    if (in_array("_user_levels", explode(",", $Page->getCurrentDetailTable())) && $_user_levels->DetailView) {
?>
<?php if ($Page->getCurrentDetailTable() != "") { ?>
<h4 class="ew-detail-caption"><?= $Language->tablePhrase("_user_levels", "TblCaption") ?></h4>
<?php } ?>
<?php include_once "UserLevelsGrid.php" ?>
<?php } ?>
<?php
    if (in_array("user_level_assignments", explode(",", $Page->getCurrentDetailTable())) && $user_level_assignments->DetailView) {
?>
<?php if ($Page->getCurrentDetailTable() != "") { ?>
<h4 class="ew-detail-caption"><?= $Language->tablePhrase("user_level_assignments", "TblCaption") ?></h4>
<?php } ?>
<?php include_once "UserLevelAssignmentsGrid.php" ?>
<?php } ?>
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
