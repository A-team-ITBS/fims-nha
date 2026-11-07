<?php

namespace PHPMaker2024\FIMS;

// Page object
$UserOfficesDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { user_offices: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fuser_officesdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fuser_officesdelete")
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
<form name="fuser_officesdelete" id="fuser_officesdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="user_offices">
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
<?php if ($Page->user_office_id->Visible) { // user_office_id ?>
        <th class="<?= $Page->user_office_id->headerCellClass() ?>"><span id="elh_user_offices_user_office_id" class="user_offices_user_office_id"><?= $Page->user_office_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
        <th class="<?= $Page->user_id->headerCellClass() ?>"><span id="elh_user_offices_user_id" class="user_offices_user_id"><?= $Page->user_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
        <th class="<?= $Page->office_id->headerCellClass() ?>"><span id="elh_user_offices_office_id" class="user_offices_office_id"><?= $Page->office_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_primary->Visible) { // is_primary ?>
        <th class="<?= $Page->is_primary->headerCellClass() ?>"><span id="elh_user_offices_is_primary" class="user_offices_is_primary"><?= $Page->is_primary->caption() ?></span></th>
<?php } ?>
<?php if ($Page->access_start_date->Visible) { // access_start_date ?>
        <th class="<?= $Page->access_start_date->headerCellClass() ?>"><span id="elh_user_offices_access_start_date" class="user_offices_access_start_date"><?= $Page->access_start_date->caption() ?></span></th>
<?php } ?>
<?php if ($Page->access_end_date->Visible) { // access_end_date ?>
        <th class="<?= $Page->access_end_date->headerCellClass() ?>"><span id="elh_user_offices_access_end_date" class="user_offices_access_end_date"><?= $Page->access_end_date->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_user_offices_is_active" class="user_offices_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->assigned_by->Visible) { // assigned_by ?>
        <th class="<?= $Page->assigned_by->headerCellClass() ?>"><span id="elh_user_offices_assigned_by" class="user_offices_assigned_by"><?= $Page->assigned_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->assigned_at->Visible) { // assigned_at ?>
        <th class="<?= $Page->assigned_at->headerCellClass() ?>"><span id="elh_user_offices_assigned_at" class="user_offices_assigned_at"><?= $Page->assigned_at->caption() ?></span></th>
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
<?php if ($Page->user_office_id->Visible) { // user_office_id ?>
        <td<?= $Page->user_office_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->user_office_id->viewAttributes() ?>>
<?= $Page->user_office_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
        <td<?= $Page->user_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->user_id->viewAttributes() ?>>
<?= $Page->user_id->getViewValue() ?></span>
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
<?php if ($Page->is_primary->Visible) { // is_primary ?>
        <td<?= $Page->is_primary->cellAttributes() ?>>
<span id="">
<span<?= $Page->is_primary->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_primary->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->access_start_date->Visible) { // access_start_date ?>
        <td<?= $Page->access_start_date->cellAttributes() ?>>
<span id="">
<span<?= $Page->access_start_date->viewAttributes() ?>>
<?= $Page->access_start_date->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->access_end_date->Visible) { // access_end_date ?>
        <td<?= $Page->access_end_date->cellAttributes() ?>>
<span id="">
<span<?= $Page->access_end_date->viewAttributes() ?>>
<?= $Page->access_end_date->getViewValue() ?></span>
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
