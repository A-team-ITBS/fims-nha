<?php

namespace PHPMaker2024\FIMS;

// Page object
$UserOfficesView = &$Page;
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
<form name="fuser_officesview" id="fuser_officesview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { user_offices: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fuser_officesview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fuser_officesview")
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
<input type="hidden" name="t" value="user_offices">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->user_office_id->Visible) { // user_office_id ?>
    <tr id="r_user_office_id"<?= $Page->user_office_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_user_office_id"><?= $Page->user_office_id->caption() ?></span></td>
        <td data-name="user_office_id"<?= $Page->user_office_id->cellAttributes() ?>>
<span id="el_user_offices_user_office_id">
<span<?= $Page->user_office_id->viewAttributes() ?>>
<?= $Page->user_office_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <tr id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_user_id"><?= $Page->user_id->caption() ?></span></td>
        <td data-name="user_id"<?= $Page->user_id->cellAttributes() ?>>
<span id="el_user_offices_user_id">
<span<?= $Page->user_id->viewAttributes() ?>>
<?= $Page->user_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
    <tr id="r_office_id"<?= $Page->office_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_office_id"><?= $Page->office_id->caption() ?></span></td>
        <td data-name="office_id"<?= $Page->office_id->cellAttributes() ?>>
<span id="el_user_offices_office_id">
<span<?= $Page->office_id->viewAttributes() ?>>
<?= $Page->office_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_primary->Visible) { // is_primary ?>
    <tr id="r_is_primary"<?= $Page->is_primary->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_is_primary"><?= $Page->is_primary->caption() ?></span></td>
        <td data-name="is_primary"<?= $Page->is_primary->cellAttributes() ?>>
<span id="el_user_offices_is_primary">
<span<?= $Page->is_primary->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_primary->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->access_start_date->Visible) { // access_start_date ?>
    <tr id="r_access_start_date"<?= $Page->access_start_date->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_access_start_date"><?= $Page->access_start_date->caption() ?></span></td>
        <td data-name="access_start_date"<?= $Page->access_start_date->cellAttributes() ?>>
<span id="el_user_offices_access_start_date">
<span<?= $Page->access_start_date->viewAttributes() ?>>
<?= $Page->access_start_date->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->access_end_date->Visible) { // access_end_date ?>
    <tr id="r_access_end_date"<?= $Page->access_end_date->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_access_end_date"><?= $Page->access_end_date->caption() ?></span></td>
        <td data-name="access_end_date"<?= $Page->access_end_date->cellAttributes() ?>>
<span id="el_user_offices_access_end_date">
<span<?= $Page->access_end_date->viewAttributes() ?>>
<?= $Page->access_end_date->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_user_offices_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->assigned_by->Visible) { // assigned_by ?>
    <tr id="r_assigned_by"<?= $Page->assigned_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_assigned_by"><?= $Page->assigned_by->caption() ?></span></td>
        <td data-name="assigned_by"<?= $Page->assigned_by->cellAttributes() ?>>
<span id="el_user_offices_assigned_by">
<span<?= $Page->assigned_by->viewAttributes() ?>>
<?= $Page->assigned_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->assigned_at->Visible) { // assigned_at ?>
    <tr id="r_assigned_at"<?= $Page->assigned_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_user_offices_assigned_at"><?= $Page->assigned_at->caption() ?></span></td>
        <td data-name="assigned_at"<?= $Page->assigned_at->cellAttributes() ?>>
<span id="el_user_offices_assigned_at">
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
