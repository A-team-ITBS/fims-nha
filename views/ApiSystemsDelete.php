<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiSystemsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_systems: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fapi_systemsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_systemsdelete")
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
<form name="fapi_systemsdelete" id="fapi_systemsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="api_systems">
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
<?php if ($Page->system_id->Visible) { // system_id ?>
        <th class="<?= $Page->system_id->headerCellClass() ?>"><span id="elh_api_systems_system_id" class="api_systems_system_id"><?= $Page->system_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->system_code->Visible) { // system_code ?>
        <th class="<?= $Page->system_code->headerCellClass() ?>"><span id="elh_api_systems_system_code" class="api_systems_system_code"><?= $Page->system_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->system_name->Visible) { // system_name ?>
        <th class="<?= $Page->system_name->headerCellClass() ?>"><span id="elh_api_systems_system_name" class="api_systems_system_name"><?= $Page->system_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->base_url->Visible) { // base_url ?>
        <th class="<?= $Page->base_url->headerCellClass() ?>"><span id="elh_api_systems_base_url" class="api_systems_base_url"><?= $Page->base_url->caption() ?></span></th>
<?php } ?>
<?php if ($Page->auth_type->Visible) { // auth_type ?>
        <th class="<?= $Page->auth_type->headerCellClass() ?>"><span id="elh_api_systems_auth_type" class="api_systems_auth_type"><?= $Page->auth_type->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_api_systems_is_active" class="api_systems_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_api_systems_created_at" class="api_systems_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th class="<?= $Page->updated_at->headerCellClass() ?>"><span id="elh_api_systems_updated_at" class="api_systems_updated_at"><?= $Page->updated_at->caption() ?></span></th>
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
<?php if ($Page->system_id->Visible) { // system_id ?>
        <td<?= $Page->system_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->system_id->viewAttributes() ?>>
<?= $Page->system_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->system_code->Visible) { // system_code ?>
        <td<?= $Page->system_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->system_code->viewAttributes() ?>>
<?= $Page->system_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->system_name->Visible) { // system_name ?>
        <td<?= $Page->system_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->system_name->viewAttributes() ?>>
<?= $Page->system_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->base_url->Visible) { // base_url ?>
        <td<?= $Page->base_url->cellAttributes() ?>>
<span id="">
<span<?= $Page->base_url->viewAttributes() ?>>
<?= $Page->base_url->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->auth_type->Visible) { // auth_type ?>
        <td<?= $Page->auth_type->cellAttributes() ?>>
<span id="">
<span<?= $Page->auth_type->viewAttributes() ?>>
<?= $Page->auth_type->getViewValue() ?></span>
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
