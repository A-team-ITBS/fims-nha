<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderAssetsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_assets: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fdocument_header_assetsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_header_assetsdelete")
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
<form name="fdocument_header_assetsdelete" id="fdocument_header_assetsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="document_header_assets">
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
<?php if ($Page->header_asset_id->Visible) { // header_asset_id ?>
        <th class="<?= $Page->header_asset_id->headerCellClass() ?>"><span id="elh_document_header_assets_header_asset_id" class="document_header_assets_header_asset_id"><?= $Page->header_asset_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->asset_code->Visible) { // asset_code ?>
        <th class="<?= $Page->asset_code->headerCellClass() ?>"><span id="elh_document_header_assets_asset_code" class="document_header_assets_asset_code"><?= $Page->asset_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->asset_name->Visible) { // asset_name ?>
        <th class="<?= $Page->asset_name->headerCellClass() ?>"><span id="elh_document_header_assets_asset_name" class="document_header_assets_asset_name"><?= $Page->asset_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
        <th class="<?= $Page->document_asset_type_id->headerCellClass() ?>"><span id="elh_document_header_assets_document_asset_type_id" class="document_header_assets_document_asset_type_id"><?= $Page->document_asset_type_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->scope_system_code->Visible) { // scope_system_code ?>
        <th class="<?= $Page->scope_system_code->headerCellClass() ?>"><span id="elh_document_header_assets_scope_system_code" class="document_header_assets_scope_system_code"><?= $Page->scope_system_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->scope_document_code->Visible) { // scope_document_code ?>
        <th class="<?= $Page->scope_document_code->headerCellClass() ?>"><span id="elh_document_header_assets_scope_document_code" class="document_header_assets_scope_document_code"><?= $Page->scope_document_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->file_name->Visible) { // file_name ?>
        <th class="<?= $Page->file_name->headerCellClass() ?>"><span id="elh_document_header_assets_file_name" class="document_header_assets_file_name"><?= $Page->file_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->mime_type->Visible) { // mime_type ?>
        <th class="<?= $Page->mime_type->headerCellClass() ?>"><span id="elh_document_header_assets_mime_type" class="document_header_assets_mime_type"><?= $Page->mime_type->caption() ?></span></th>
<?php } ?>
<?php if ($Page->file_size->Visible) { // file_size ?>
        <th class="<?= $Page->file_size->headerCellClass() ?>"><span id="elh_document_header_assets_file_size" class="document_header_assets_file_size"><?= $Page->file_size->caption() ?></span></th>
<?php } ?>
<?php if ($Page->checksum_sha256->Visible) { // checksum_sha256 ?>
        <th class="<?= $Page->checksum_sha256->headerCellClass() ?>"><span id="elh_document_header_assets_checksum_sha256" class="document_header_assets_checksum_sha256"><?= $Page->checksum_sha256->caption() ?></span></th>
<?php } ?>
<?php if ($Page->image_width->Visible) { // image_width ?>
        <th class="<?= $Page->image_width->headerCellClass() ?>"><span id="elh_document_header_assets_image_width" class="document_header_assets_image_width"><?= $Page->image_width->caption() ?></span></th>
<?php } ?>
<?php if ($Page->image_height->Visible) { // image_height ?>
        <th class="<?= $Page->image_height->headerCellClass() ?>"><span id="elh_document_header_assets_image_height" class="document_header_assets_image_height"><?= $Page->image_height->caption() ?></span></th>
<?php } ?>
<?php if ($Page->render_width->Visible) { // render_width ?>
        <th class="<?= $Page->render_width->headerCellClass() ?>"><span id="elh_document_header_assets_render_width" class="document_header_assets_render_width"><?= $Page->render_width->caption() ?></span></th>
<?php } ?>
<?php if ($Page->render_height->Visible) { // render_height ?>
        <th class="<?= $Page->render_height->headerCellClass() ?>"><span id="elh_document_header_assets_render_height" class="document_header_assets_render_height"><?= $Page->render_height->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_document_header_assets_is_active" class="document_header_assets_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_document_header_assets_created_at" class="document_header_assets_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th class="<?= $Page->updated_at->headerCellClass() ?>"><span id="elh_document_header_assets_updated_at" class="document_header_assets_updated_at"><?= $Page->updated_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <th class="<?= $Page->created_by->headerCellClass() ?>"><span id="elh_document_header_assets_created_by" class="document_header_assets_created_by"><?= $Page->created_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->updated_by->Visible) { // updated_by ?>
        <th class="<?= $Page->updated_by->headerCellClass() ?>"><span id="elh_document_header_assets_updated_by" class="document_header_assets_updated_by"><?= $Page->updated_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <th class="<?= $Page->header_slot_id->headerCellClass() ?>"><span id="elh_document_header_assets_header_slot_id" class="document_header_assets_header_slot_id"><?= $Page->header_slot_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
        <th class="<?= $Page->part_kind->headerCellClass() ?>"><span id="elh_document_header_assets_part_kind" class="document_header_assets_part_kind"><?= $Page->part_kind->caption() ?></span></th>
<?php } ?>
<?php if ($Page->column_pct->Visible) { // column_pct ?>
        <th class="<?= $Page->column_pct->headerCellClass() ?>"><span id="elh_document_header_assets_column_pct" class="document_header_assets_column_pct"><?= $Page->column_pct->caption() ?></span></th>
<?php } ?>
<?php if ($Page->align->Visible) { // align ?>
        <th class="<?= $Page->align->headerCellClass() ?>"><span id="elh_document_header_assets_align" class="document_header_assets_align"><?= $Page->align->caption() ?></span></th>
<?php } ?>
<?php if ($Page->font_name->Visible) { // font_name ?>
        <th class="<?= $Page->font_name->headerCellClass() ?>"><span id="elh_document_header_assets_font_name" class="document_header_assets_font_name"><?= $Page->font_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->line1_size_pt->Visible) { // line1_size_pt ?>
        <th class="<?= $Page->line1_size_pt->headerCellClass() ?>"><span id="elh_document_header_assets_line1_size_pt" class="document_header_assets_line1_size_pt"><?= $Page->line1_size_pt->caption() ?></span></th>
<?php } ?>
<?php if ($Page->line2_size_pt->Visible) { // line2_size_pt ?>
        <th class="<?= $Page->line2_size_pt->headerCellClass() ?>"><span id="elh_document_header_assets_line2_size_pt" class="document_header_assets_line2_size_pt"><?= $Page->line2_size_pt->caption() ?></span></th>
<?php } ?>
<?php if ($Page->line1_bold->Visible) { // line1_bold ?>
        <th class="<?= $Page->line1_bold->headerCellClass() ?>"><span id="elh_document_header_assets_line1_bold" class="document_header_assets_line1_bold"><?= $Page->line1_bold->caption() ?></span></th>
<?php } ?>
<?php if ($Page->line2_bold->Visible) { // line2_bold ?>
        <th class="<?= $Page->line2_bold->headerCellClass() ?>"><span id="elh_document_header_assets_line2_bold" class="document_header_assets_line2_bold"><?= $Page->line2_bold->caption() ?></span></th>
<?php } ?>
<?php if ($Page->line1_color->Visible) { // line1_color ?>
        <th class="<?= $Page->line1_color->headerCellClass() ?>"><span id="elh_document_header_assets_line1_color" class="document_header_assets_line1_color"><?= $Page->line1_color->caption() ?></span></th>
<?php } ?>
<?php if ($Page->line2_color->Visible) { // line2_color ?>
        <th class="<?= $Page->line2_color->headerCellClass() ?>"><span id="elh_document_header_assets_line2_color" class="document_header_assets_line2_color"><?= $Page->line2_color->caption() ?></span></th>
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
<?php if ($Page->header_asset_id->Visible) { // header_asset_id ?>
        <td<?= $Page->header_asset_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->header_asset_id->viewAttributes() ?>>
<?= $Page->header_asset_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->asset_code->Visible) { // asset_code ?>
        <td<?= $Page->asset_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->asset_code->viewAttributes() ?>>
<?= $Page->asset_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->asset_name->Visible) { // asset_name ?>
        <td<?= $Page->asset_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->asset_name->viewAttributes() ?>>
<?= $Page->asset_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
        <td<?= $Page->document_asset_type_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->document_asset_type_id->viewAttributes() ?>>
<?= $Page->document_asset_type_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->scope_system_code->Visible) { // scope_system_code ?>
        <td<?= $Page->scope_system_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->scope_system_code->viewAttributes() ?>>
<?= $Page->scope_system_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->scope_document_code->Visible) { // scope_document_code ?>
        <td<?= $Page->scope_document_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->scope_document_code->viewAttributes() ?>>
<?= $Page->scope_document_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->file_name->Visible) { // file_name ?>
        <td<?= $Page->file_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->file_name->viewAttributes() ?>>
<?= $Page->file_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->mime_type->Visible) { // mime_type ?>
        <td<?= $Page->mime_type->cellAttributes() ?>>
<span id="">
<span<?= $Page->mime_type->viewAttributes() ?>>
<?= $Page->mime_type->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->file_size->Visible) { // file_size ?>
        <td<?= $Page->file_size->cellAttributes() ?>>
<span id="">
<span<?= $Page->file_size->viewAttributes() ?>>
<?= $Page->file_size->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->checksum_sha256->Visible) { // checksum_sha256 ?>
        <td<?= $Page->checksum_sha256->cellAttributes() ?>>
<span id="">
<span<?= $Page->checksum_sha256->viewAttributes() ?>>
<?= $Page->checksum_sha256->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->image_width->Visible) { // image_width ?>
        <td<?= $Page->image_width->cellAttributes() ?>>
<span id="">
<span<?= $Page->image_width->viewAttributes() ?>>
<?= $Page->image_width->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->image_height->Visible) { // image_height ?>
        <td<?= $Page->image_height->cellAttributes() ?>>
<span id="">
<span<?= $Page->image_height->viewAttributes() ?>>
<?= $Page->image_height->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->render_width->Visible) { // render_width ?>
        <td<?= $Page->render_width->cellAttributes() ?>>
<span id="">
<span<?= $Page->render_width->viewAttributes() ?>>
<?= $Page->render_width->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->render_height->Visible) { // render_height ?>
        <td<?= $Page->render_height->cellAttributes() ?>>
<span id="">
<span<?= $Page->render_height->viewAttributes() ?>>
<?= $Page->render_height->getViewValue() ?></span>
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
<?php if ($Page->created_by->Visible) { // created_by ?>
        <td<?= $Page->created_by->cellAttributes() ?>>
<span id="">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->updated_by->Visible) { // updated_by ?>
        <td<?= $Page->updated_by->cellAttributes() ?>>
<span id="">
<span<?= $Page->updated_by->viewAttributes() ?>>
<?= $Page->updated_by->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <td<?= $Page->header_slot_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->header_slot_id->viewAttributes() ?>>
<?= $Page->header_slot_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
        <td<?= $Page->part_kind->cellAttributes() ?>>
<span id="">
<span<?= $Page->part_kind->viewAttributes() ?>>
<?= $Page->part_kind->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->column_pct->Visible) { // column_pct ?>
        <td<?= $Page->column_pct->cellAttributes() ?>>
<span id="">
<span<?= $Page->column_pct->viewAttributes() ?>>
<?= $Page->column_pct->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->align->Visible) { // align ?>
        <td<?= $Page->align->cellAttributes() ?>>
<span id="">
<span<?= $Page->align->viewAttributes() ?>>
<?= $Page->align->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->font_name->Visible) { // font_name ?>
        <td<?= $Page->font_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->font_name->viewAttributes() ?>>
<?= $Page->font_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->line1_size_pt->Visible) { // line1_size_pt ?>
        <td<?= $Page->line1_size_pt->cellAttributes() ?>>
<span id="">
<span<?= $Page->line1_size_pt->viewAttributes() ?>>
<?= $Page->line1_size_pt->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->line2_size_pt->Visible) { // line2_size_pt ?>
        <td<?= $Page->line2_size_pt->cellAttributes() ?>>
<span id="">
<span<?= $Page->line2_size_pt->viewAttributes() ?>>
<?= $Page->line2_size_pt->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->line1_bold->Visible) { // line1_bold ?>
        <td<?= $Page->line1_bold->cellAttributes() ?>>
<span id="">
<span<?= $Page->line1_bold->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->line1_bold->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->line2_bold->Visible) { // line2_bold ?>
        <td<?= $Page->line2_bold->cellAttributes() ?>>
<span id="">
<span<?= $Page->line2_bold->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->line2_bold->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->line1_color->Visible) { // line1_color ?>
        <td<?= $Page->line1_color->cellAttributes() ?>>
<span id="">
<span<?= $Page->line1_color->viewAttributes() ?>>
<?= $Page->line1_color->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->line2_color->Visible) { // line2_color ?>
        <td<?= $Page->line2_color->cellAttributes() ?>>
<span id="">
<span<?= $Page->line2_color->viewAttributes() ?>>
<?= $Page->line2_color->getViewValue() ?></span>
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
