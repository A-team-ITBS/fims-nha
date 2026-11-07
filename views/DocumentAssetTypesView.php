<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentAssetTypesView = &$Page;
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
<form name="fdocument_asset_typesview" id="fdocument_asset_typesview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_asset_types: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fdocument_asset_typesview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_asset_typesview")
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
<input type="hidden" name="t" value="document_asset_types">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
    <tr id="r_document_asset_type_id"<?= $Page->document_asset_type_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_document_asset_type_id"><?= $Page->document_asset_type_id->caption() ?></span></td>
        <td data-name="document_asset_type_id"<?= $Page->document_asset_type_id->cellAttributes() ?>>
<span id="el_document_asset_types_document_asset_type_id">
<span<?= $Page->document_asset_type_id->viewAttributes() ?>>
<?= $Page->document_asset_type_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->category->Visible) { // category ?>
    <tr id="r_category"<?= $Page->category->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_category"><?= $Page->category->caption() ?></span></td>
        <td data-name="category"<?= $Page->category->cellAttributes() ?>>
<span id="el_document_asset_types_category">
<span<?= $Page->category->viewAttributes() ?>>
<?= $Page->category->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->type_code->Visible) { // type_code ?>
    <tr id="r_type_code"<?= $Page->type_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_type_code"><?= $Page->type_code->caption() ?></span></td>
        <td data-name="type_code"<?= $Page->type_code->cellAttributes() ?>>
<span id="el_document_asset_types_type_code">
<span<?= $Page->type_code->viewAttributes() ?>>
<?= $Page->type_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->type_name->Visible) { // type_name ?>
    <tr id="r_type_name"<?= $Page->type_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_type_name"><?= $Page->type_name->caption() ?></span></td>
        <td data-name="type_name"<?= $Page->type_name->cellAttributes() ?>>
<span id="el_document_asset_types_type_name">
<span<?= $Page->type_name->viewAttributes() ?>>
<?= $Page->type_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
    <tr id="r_default_render_width"<?= $Page->default_render_width->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_default_render_width"><?= $Page->default_render_width->caption() ?></span></td>
        <td data-name="default_render_width"<?= $Page->default_render_width->cellAttributes() ?>>
<span id="el_document_asset_types_default_render_width">
<span<?= $Page->default_render_width->viewAttributes() ?>>
<?= $Page->default_render_width->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
    <tr id="r_default_render_height"<?= $Page->default_render_height->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_default_render_height"><?= $Page->default_render_height->caption() ?></span></td>
        <td data-name="default_render_height"<?= $Page->default_render_height->cellAttributes() ?>>
<span id="el_document_asset_types_default_render_height">
<span<?= $Page->default_render_height->viewAttributes() ?>>
<?= $Page->default_render_height->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_document_asset_types_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_asset_types_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_document_asset_types_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
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
