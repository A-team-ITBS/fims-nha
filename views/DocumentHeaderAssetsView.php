<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderAssetsView = &$Page;
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
<form name="fdocument_header_assetsview" id="fdocument_header_assetsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_assets: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fdocument_header_assetsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_header_assetsview")
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
<input type="hidden" name="t" value="document_header_assets">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->header_asset_id->Visible) { // header_asset_id ?>
    <tr id="r_header_asset_id"<?= $Page->header_asset_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_header_asset_id"><?= $Page->header_asset_id->caption() ?></span></td>
        <td data-name="header_asset_id"<?= $Page->header_asset_id->cellAttributes() ?>>
<span id="el_document_header_assets_header_asset_id">
<span<?= $Page->header_asset_id->viewAttributes() ?>>
<?= $Page->header_asset_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->asset_code->Visible) { // asset_code ?>
    <tr id="r_asset_code"<?= $Page->asset_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_asset_code"><?= $Page->asset_code->caption() ?></span></td>
        <td data-name="asset_code"<?= $Page->asset_code->cellAttributes() ?>>
<span id="el_document_header_assets_asset_code">
<span<?= $Page->asset_code->viewAttributes() ?>>
<?= $Page->asset_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->asset_name->Visible) { // asset_name ?>
    <tr id="r_asset_name"<?= $Page->asset_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_asset_name"><?= $Page->asset_name->caption() ?></span></td>
        <td data-name="asset_name"<?= $Page->asset_name->cellAttributes() ?>>
<span id="el_document_header_assets_asset_name">
<span<?= $Page->asset_name->viewAttributes() ?>>
<?= $Page->asset_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
    <tr id="r_document_asset_type_id"<?= $Page->document_asset_type_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_document_asset_type_id"><?= $Page->document_asset_type_id->caption() ?></span></td>
        <td data-name="document_asset_type_id"<?= $Page->document_asset_type_id->cellAttributes() ?>>
<span id="el_document_header_assets_document_asset_type_id">
<span<?= $Page->document_asset_type_id->viewAttributes() ?>>
<?= $Page->document_asset_type_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->scope_system_code->Visible) { // scope_system_code ?>
    <tr id="r_scope_system_code"<?= $Page->scope_system_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_scope_system_code"><?= $Page->scope_system_code->caption() ?></span></td>
        <td data-name="scope_system_code"<?= $Page->scope_system_code->cellAttributes() ?>>
<span id="el_document_header_assets_scope_system_code">
<span<?= $Page->scope_system_code->viewAttributes() ?>>
<?= $Page->scope_system_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->scope_document_code->Visible) { // scope_document_code ?>
    <tr id="r_scope_document_code"<?= $Page->scope_document_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_scope_document_code"><?= $Page->scope_document_code->caption() ?></span></td>
        <td data-name="scope_document_code"<?= $Page->scope_document_code->cellAttributes() ?>>
<span id="el_document_header_assets_scope_document_code">
<span<?= $Page->scope_document_code->viewAttributes() ?>>
<?= $Page->scope_document_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->file_name->Visible) { // file_name ?>
    <tr id="r_file_name"<?= $Page->file_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_file_name"><?= $Page->file_name->caption() ?></span></td>
        <td data-name="file_name"<?= $Page->file_name->cellAttributes() ?>>
<span id="el_document_header_assets_file_name">
<span<?= $Page->file_name->viewAttributes() ?>>
<?= $Page->file_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->mime_type->Visible) { // mime_type ?>
    <tr id="r_mime_type"<?= $Page->mime_type->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_mime_type"><?= $Page->mime_type->caption() ?></span></td>
        <td data-name="mime_type"<?= $Page->mime_type->cellAttributes() ?>>
<span id="el_document_header_assets_mime_type">
<span<?= $Page->mime_type->viewAttributes() ?>>
<?= $Page->mime_type->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->file_size->Visible) { // file_size ?>
    <tr id="r_file_size"<?= $Page->file_size->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_file_size"><?= $Page->file_size->caption() ?></span></td>
        <td data-name="file_size"<?= $Page->file_size->cellAttributes() ?>>
<span id="el_document_header_assets_file_size">
<span<?= $Page->file_size->viewAttributes() ?>>
<?= $Page->file_size->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->checksum_sha256->Visible) { // checksum_sha256 ?>
    <tr id="r_checksum_sha256"<?= $Page->checksum_sha256->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_checksum_sha256"><?= $Page->checksum_sha256->caption() ?></span></td>
        <td data-name="checksum_sha256"<?= $Page->checksum_sha256->cellAttributes() ?>>
<span id="el_document_header_assets_checksum_sha256">
<span<?= $Page->checksum_sha256->viewAttributes() ?>>
<?= $Page->checksum_sha256->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->image_width->Visible) { // image_width ?>
    <tr id="r_image_width"<?= $Page->image_width->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_image_width"><?= $Page->image_width->caption() ?></span></td>
        <td data-name="image_width"<?= $Page->image_width->cellAttributes() ?>>
<span id="el_document_header_assets_image_width">
<span<?= $Page->image_width->viewAttributes() ?>>
<?= $Page->image_width->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->image_height->Visible) { // image_height ?>
    <tr id="r_image_height"<?= $Page->image_height->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_image_height"><?= $Page->image_height->caption() ?></span></td>
        <td data-name="image_height"<?= $Page->image_height->cellAttributes() ?>>
<span id="el_document_header_assets_image_height">
<span<?= $Page->image_height->viewAttributes() ?>>
<?= $Page->image_height->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->render_width->Visible) { // render_width ?>
    <tr id="r_render_width"<?= $Page->render_width->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_render_width"><?= $Page->render_width->caption() ?></span></td>
        <td data-name="render_width"<?= $Page->render_width->cellAttributes() ?>>
<span id="el_document_header_assets_render_width">
<span<?= $Page->render_width->viewAttributes() ?>>
<?= $Page->render_width->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->render_height->Visible) { // render_height ?>
    <tr id="r_render_height"<?= $Page->render_height->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_render_height"><?= $Page->render_height->caption() ?></span></td>
        <td data-name="render_height"<?= $Page->render_height->cellAttributes() ?>>
<span id="el_document_header_assets_render_height">
<span<?= $Page->render_height->viewAttributes() ?>>
<?= $Page->render_height->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->image_data->Visible) { // image_data ?>
    <tr id="r_image_data"<?= $Page->image_data->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_image_data"><?= $Page->image_data->caption() ?></span></td>
        <td data-name="image_data"<?= $Page->image_data->cellAttributes() ?>>
<span id="el_document_header_assets_image_data">
<span<?= $Page->image_data->viewAttributes() ?>>
<?= $Page->image_data->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_document_header_assets_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->notes->Visible) { // notes ?>
    <tr id="r_notes"<?= $Page->notes->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_notes"><?= $Page->notes->caption() ?></span></td>
        <td data-name="notes"<?= $Page->notes->cellAttributes() ?>>
<span id="el_document_header_assets_notes">
<span<?= $Page->notes->viewAttributes() ?>>
<?= $Page->notes->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_document_header_assets_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <tr id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_updated_at"><?= $Page->updated_at->caption() ?></span></td>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_document_header_assets_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <tr id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_created_by"><?= $Page->created_by->caption() ?></span></td>
        <td data-name="created_by"<?= $Page->created_by->cellAttributes() ?>>
<span id="el_document_header_assets_created_by">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_by->Visible) { // updated_by ?>
    <tr id="r_updated_by"<?= $Page->updated_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_updated_by"><?= $Page->updated_by->caption() ?></span></td>
        <td data-name="updated_by"<?= $Page->updated_by->cellAttributes() ?>>
<span id="el_document_header_assets_updated_by">
<span<?= $Page->updated_by->viewAttributes() ?>>
<?= $Page->updated_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
    <tr id="r_header_slot_id"<?= $Page->header_slot_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_header_slot_id"><?= $Page->header_slot_id->caption() ?></span></td>
        <td data-name="header_slot_id"<?= $Page->header_slot_id->cellAttributes() ?>>
<span id="el_document_header_assets_header_slot_id">
<span<?= $Page->header_slot_id->viewAttributes() ?>>
<?= $Page->header_slot_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
    <tr id="r_part_kind"<?= $Page->part_kind->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_part_kind"><?= $Page->part_kind->caption() ?></span></td>
        <td data-name="part_kind"<?= $Page->part_kind->cellAttributes() ?>>
<span id="el_document_header_assets_part_kind">
<span<?= $Page->part_kind->viewAttributes() ?>>
<?= $Page->part_kind->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->column_pct->Visible) { // column_pct ?>
    <tr id="r_column_pct"<?= $Page->column_pct->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_column_pct"><?= $Page->column_pct->caption() ?></span></td>
        <td data-name="column_pct"<?= $Page->column_pct->cellAttributes() ?>>
<span id="el_document_header_assets_column_pct">
<span<?= $Page->column_pct->viewAttributes() ?>>
<?= $Page->column_pct->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->align->Visible) { // align ?>
    <tr id="r_align"<?= $Page->align->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_align"><?= $Page->align->caption() ?></span></td>
        <td data-name="align"<?= $Page->align->cellAttributes() ?>>
<span id="el_document_header_assets_align">
<span<?= $Page->align->viewAttributes() ?>>
<?= $Page->align->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->text_line1->Visible) { // text_line1 ?>
    <tr id="r_text_line1"<?= $Page->text_line1->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_text_line1"><?= $Page->text_line1->caption() ?></span></td>
        <td data-name="text_line1"<?= $Page->text_line1->cellAttributes() ?>>
<span id="el_document_header_assets_text_line1">
<span<?= $Page->text_line1->viewAttributes() ?>>
<?= $Page->text_line1->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->text_line2->Visible) { // text_line2 ?>
    <tr id="r_text_line2"<?= $Page->text_line2->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_text_line2"><?= $Page->text_line2->caption() ?></span></td>
        <td data-name="text_line2"<?= $Page->text_line2->cellAttributes() ?>>
<span id="el_document_header_assets_text_line2">
<span<?= $Page->text_line2->viewAttributes() ?>>
<?= $Page->text_line2->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->font_name->Visible) { // font_name ?>
    <tr id="r_font_name"<?= $Page->font_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_font_name"><?= $Page->font_name->caption() ?></span></td>
        <td data-name="font_name"<?= $Page->font_name->cellAttributes() ?>>
<span id="el_document_header_assets_font_name">
<span<?= $Page->font_name->viewAttributes() ?>>
<?= $Page->font_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->line1_size_pt->Visible) { // line1_size_pt ?>
    <tr id="r_line1_size_pt"<?= $Page->line1_size_pt->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_line1_size_pt"><?= $Page->line1_size_pt->caption() ?></span></td>
        <td data-name="line1_size_pt"<?= $Page->line1_size_pt->cellAttributes() ?>>
<span id="el_document_header_assets_line1_size_pt">
<span<?= $Page->line1_size_pt->viewAttributes() ?>>
<?= $Page->line1_size_pt->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->line2_size_pt->Visible) { // line2_size_pt ?>
    <tr id="r_line2_size_pt"<?= $Page->line2_size_pt->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_line2_size_pt"><?= $Page->line2_size_pt->caption() ?></span></td>
        <td data-name="line2_size_pt"<?= $Page->line2_size_pt->cellAttributes() ?>>
<span id="el_document_header_assets_line2_size_pt">
<span<?= $Page->line2_size_pt->viewAttributes() ?>>
<?= $Page->line2_size_pt->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->line1_bold->Visible) { // line1_bold ?>
    <tr id="r_line1_bold"<?= $Page->line1_bold->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_line1_bold"><?= $Page->line1_bold->caption() ?></span></td>
        <td data-name="line1_bold"<?= $Page->line1_bold->cellAttributes() ?>>
<span id="el_document_header_assets_line1_bold">
<span<?= $Page->line1_bold->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->line1_bold->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->line2_bold->Visible) { // line2_bold ?>
    <tr id="r_line2_bold"<?= $Page->line2_bold->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_line2_bold"><?= $Page->line2_bold->caption() ?></span></td>
        <td data-name="line2_bold"<?= $Page->line2_bold->cellAttributes() ?>>
<span id="el_document_header_assets_line2_bold">
<span<?= $Page->line2_bold->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->line2_bold->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->line1_color->Visible) { // line1_color ?>
    <tr id="r_line1_color"<?= $Page->line1_color->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_line1_color"><?= $Page->line1_color->caption() ?></span></td>
        <td data-name="line1_color"<?= $Page->line1_color->cellAttributes() ?>>
<span id="el_document_header_assets_line1_color">
<span<?= $Page->line1_color->viewAttributes() ?>>
<?= $Page->line1_color->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->line2_color->Visible) { // line2_color ?>
    <tr id="r_line2_color"<?= $Page->line2_color->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_assets_line2_color"><?= $Page->line2_color->caption() ?></span></td>
        <td data-name="line2_color"<?= $Page->line2_color->cellAttributes() ?>>
<span id="el_document_header_assets_line2_color">
<span<?= $Page->line2_color->viewAttributes() ?>>
<?= $Page->line2_color->getViewValue() ?></span>
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
