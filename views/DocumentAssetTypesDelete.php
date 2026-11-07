<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentAssetTypesDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_asset_types: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fdocument_asset_typesdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_asset_typesdelete")
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
<form name="fdocument_asset_typesdelete" id="fdocument_asset_typesdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="document_asset_types">
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
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
        <th class="<?= $Page->document_asset_type_id->headerCellClass() ?>"><span id="elh_document_asset_types_document_asset_type_id" class="document_asset_types_document_asset_type_id"><?= $Page->document_asset_type_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->category->Visible) { // category ?>
        <th class="<?= $Page->category->headerCellClass() ?>"><span id="elh_document_asset_types_category" class="document_asset_types_category"><?= $Page->category->caption() ?></span></th>
<?php } ?>
<?php if ($Page->type_code->Visible) { // type_code ?>
        <th class="<?= $Page->type_code->headerCellClass() ?>"><span id="elh_document_asset_types_type_code" class="document_asset_types_type_code"><?= $Page->type_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->type_name->Visible) { // type_name ?>
        <th class="<?= $Page->type_name->headerCellClass() ?>"><span id="elh_document_asset_types_type_name" class="document_asset_types_type_name"><?= $Page->type_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
        <th class="<?= $Page->default_render_width->headerCellClass() ?>"><span id="elh_document_asset_types_default_render_width" class="document_asset_types_default_render_width"><?= $Page->default_render_width->caption() ?></span></th>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
        <th class="<?= $Page->default_render_height->headerCellClass() ?>"><span id="elh_document_asset_types_default_render_height" class="document_asset_types_default_render_height"><?= $Page->default_render_height->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_document_asset_types_is_active" class="document_asset_types_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_document_asset_types_created_at" class="document_asset_types_created_at"><?= $Page->created_at->caption() ?></span></th>
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
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
        <td<?= $Page->document_asset_type_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->document_asset_type_id->viewAttributes() ?>>
<?= $Page->document_asset_type_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->category->Visible) { // category ?>
        <td<?= $Page->category->cellAttributes() ?>>
<span id="">
<span<?= $Page->category->viewAttributes() ?>>
<?= $Page->category->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->type_code->Visible) { // type_code ?>
        <td<?= $Page->type_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->type_code->viewAttributes() ?>>
<?= $Page->type_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->type_name->Visible) { // type_name ?>
        <td<?= $Page->type_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->type_name->viewAttributes() ?>>
<?= $Page->type_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
        <td<?= $Page->default_render_width->cellAttributes() ?>>
<span id="">
<span<?= $Page->default_render_width->viewAttributes() ?>>
<?= $Page->default_render_width->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
        <td<?= $Page->default_render_height->cellAttributes() ?>>
<span id="">
<span<?= $Page->default_render_height->viewAttributes() ?>>
<?= $Page->default_render_height->getViewValue() ?></span>
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
