<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderAssetsList = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_assets: currentTable } });
var currentPageID = ew.PAGE_ID = "list";
var currentForm;
var <?= $Page->FormName ?>;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("<?= $Page->FormName ?>")
        .setPageId("list")
        .setSubmitWithFetch(<?= $Page->UseAjaxActions ? "true" : "false" ?>)
        .setFormKeyCountName("<?= $Page->FormKeyCountName ?>")
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
<?php if (!$Page->isExport()) { ?>
<div class="btn-toolbar ew-toolbar">
<?php if ($Page->TotalRecords > 0 && $Page->ExportOptions->visible()) { ?>
<?php $Page->ExportOptions->render("body") ?>
<?php } ?>
<?php if ($Page->ImportOptions->visible()) { ?>
<?php $Page->ImportOptions->render("body") ?>
<?php } ?>
<?php if ($Page->SearchOptions->visible()) { ?>
<?php $Page->SearchOptions->render("body") ?>
<?php } ?>
<?php if ($Page->FilterOptions->visible()) { ?>
<?php $Page->FilterOptions->render("body") ?>
<?php } ?>
</div>
<?php } ?>
<?php if (!$Page->IsModal) { ?>
<form name="fdocument_header_assetssrch" id="fdocument_header_assetssrch" class="ew-form ew-ext-search-form" action="<?= CurrentPageUrl(false) ?>" novalidate autocomplete="off">
<div id="fdocument_header_assetssrch_search_panel" class="mb-2 mb-sm-0 <?= $Page->SearchPanelClass ?>"><!-- .ew-search-panel -->
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_assets: currentTable } });
var currentForm;
var fdocument_header_assetssrch, currentSearchForm, currentAdvancedSearchForm;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery,
        fields = currentTable.fields;

    // Form object for search
    let form = new ew.FormBuilder()
        .setId("fdocument_header_assetssrch")
        .setPageId("list")
<?php if ($Page->UseAjaxActions) { ?>
        .setSubmitWithFetch(true)
<?php } ?>

        // Dynamic selection lists
        .setLists({
        })

        // Filters
        .setFilterList(<?= $Page->getFilterList() ?>)
        .build();
    window[form.id] = form;
    currentSearchForm = form;
    loadjs.done(form.id);
});
</script>
<input type="hidden" name="cmd" value="search">
<?php if ($Security->canSearch()) { ?>
<?php if (!$Page->isExport() && !($Page->CurrentAction && $Page->CurrentAction != "search") && $Page->hasSearchFields()) { ?>
<div class="ew-extended-search container-fluid ps-2">
<div class="row mb-0">
    <div class="col-sm-auto px-0 pe-sm-2">
        <div class="ew-basic-search input-group">
            <input type="search" name="<?= Config("TABLE_BASIC_SEARCH") ?>" id="<?= Config("TABLE_BASIC_SEARCH") ?>" class="form-control ew-basic-search-keyword" value="<?= HtmlEncode($Page->BasicSearch->getKeyword()) ?>" placeholder="<?= HtmlEncode($Language->phrase("Search")) ?>" aria-label="<?= HtmlEncode($Language->phrase("Search")) ?>">
            <input type="hidden" name="<?= Config("TABLE_BASIC_SEARCH_TYPE") ?>" id="<?= Config("TABLE_BASIC_SEARCH_TYPE") ?>" class="ew-basic-search-type" value="<?= HtmlEncode($Page->BasicSearch->getType()) ?>">
            <button type="button" data-bs-toggle="dropdown" class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" aria-haspopup="true" aria-expanded="false">
                <span id="searchtype"><?= $Page->BasicSearch->getTypeNameShort() ?></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end">
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "" ? " active" : "" ?>" form="fdocument_header_assetssrch" data-ew-action="search-type"><?= $Language->phrase("QuickSearchAuto") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "=" ? " active" : "" ?>" form="fdocument_header_assetssrch" data-ew-action="search-type" data-search-type="="><?= $Language->phrase("QuickSearchExact") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "AND" ? " active" : "" ?>" form="fdocument_header_assetssrch" data-ew-action="search-type" data-search-type="AND"><?= $Language->phrase("QuickSearchAll") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "OR" ? " active" : "" ?>" form="fdocument_header_assetssrch" data-ew-action="search-type" data-search-type="OR"><?= $Language->phrase("QuickSearchAny") ?></button>
            </div>
        </div>
    </div>
    <div class="col-sm-auto mb-3">
        <button class="btn btn-primary" name="btn-submit" id="btn-submit" type="submit"><?= $Language->phrase("SearchBtn") ?></button>
    </div>
</div>
</div><!-- /.ew-extended-search -->
<?php } ?>
<?php } ?>
</div><!-- /.ew-search-panel -->
</form>
<?php } ?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="list<?= ($Page->TotalRecords == 0 && !$Page->isAdd()) ? " ew-no-record" : "" ?>">
<div id="ew-header-options">
<?php $Page->HeaderOptions?->render("body") ?>
</div>
<div id="ew-list">
<?php if ($Page->TotalRecords > 0 || $Page->CurrentAction) { ?>
<div class="card ew-card ew-grid<?= $Page->isAddOrEdit() ? " ew-grid-add-edit" : "" ?> <?= $Page->TableGridClass ?>">
<form name="<?= $Page->FormName ?>" id="<?= $Page->FormName ?>" class="ew-form ew-list-form" action="<?= $Page->PageAction ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="document_header_assets">
<?php if ($Page->IsModal) { ?>
<input type="hidden" name="modal" value="1">
<?php } ?>
<div id="gmp_document_header_assets" class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<?php if ($Page->TotalRecords > 0 || $Page->isGridEdit() || $Page->isMultiEdit()) { ?>
<table id="tbl_document_header_assetslist" class="<?= $Page->TableClass ?>"><!-- .ew-table -->
<thead>
    <tr class="ew-table-header">
<?php
// Header row
$Page->RowType = RowType::HEADER;

// Render list options
$Page->renderListOptions();

// Render list options (header, left)
$Page->ListOptions->render("header", "left");
?>
<?php if ($Page->header_asset_id->Visible) { // header_asset_id ?>
        <th data-name="header_asset_id" class="<?= $Page->header_asset_id->headerCellClass() ?>"><div id="elh_document_header_assets_header_asset_id" class="document_header_assets_header_asset_id"><?= $Page->renderFieldHeader($Page->header_asset_id) ?></div></th>
<?php } ?>
<?php if ($Page->asset_code->Visible) { // asset_code ?>
        <th data-name="asset_code" class="<?= $Page->asset_code->headerCellClass() ?>"><div id="elh_document_header_assets_asset_code" class="document_header_assets_asset_code"><?= $Page->renderFieldHeader($Page->asset_code) ?></div></th>
<?php } ?>
<?php if ($Page->asset_name->Visible) { // asset_name ?>
        <th data-name="asset_name" class="<?= $Page->asset_name->headerCellClass() ?>"><div id="elh_document_header_assets_asset_name" class="document_header_assets_asset_name"><?= $Page->renderFieldHeader($Page->asset_name) ?></div></th>
<?php } ?>
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
        <th data-name="document_asset_type_id" class="<?= $Page->document_asset_type_id->headerCellClass() ?>"><div id="elh_document_header_assets_document_asset_type_id" class="document_header_assets_document_asset_type_id"><?= $Page->renderFieldHeader($Page->document_asset_type_id) ?></div></th>
<?php } ?>
<?php if ($Page->scope_system_code->Visible) { // scope_system_code ?>
        <th data-name="scope_system_code" class="<?= $Page->scope_system_code->headerCellClass() ?>"><div id="elh_document_header_assets_scope_system_code" class="document_header_assets_scope_system_code"><?= $Page->renderFieldHeader($Page->scope_system_code) ?></div></th>
<?php } ?>
<?php if ($Page->scope_document_code->Visible) { // scope_document_code ?>
        <th data-name="scope_document_code" class="<?= $Page->scope_document_code->headerCellClass() ?>"><div id="elh_document_header_assets_scope_document_code" class="document_header_assets_scope_document_code"><?= $Page->renderFieldHeader($Page->scope_document_code) ?></div></th>
<?php } ?>
<?php if ($Page->file_name->Visible) { // file_name ?>
        <th data-name="file_name" class="<?= $Page->file_name->headerCellClass() ?>"><div id="elh_document_header_assets_file_name" class="document_header_assets_file_name"><?= $Page->renderFieldHeader($Page->file_name) ?></div></th>
<?php } ?>
<?php if ($Page->mime_type->Visible) { // mime_type ?>
        <th data-name="mime_type" class="<?= $Page->mime_type->headerCellClass() ?>"><div id="elh_document_header_assets_mime_type" class="document_header_assets_mime_type"><?= $Page->renderFieldHeader($Page->mime_type) ?></div></th>
<?php } ?>
<?php if ($Page->file_size->Visible) { // file_size ?>
        <th data-name="file_size" class="<?= $Page->file_size->headerCellClass() ?>"><div id="elh_document_header_assets_file_size" class="document_header_assets_file_size"><?= $Page->renderFieldHeader($Page->file_size) ?></div></th>
<?php } ?>
<?php if ($Page->checksum_sha256->Visible) { // checksum_sha256 ?>
        <th data-name="checksum_sha256" class="<?= $Page->checksum_sha256->headerCellClass() ?>"><div id="elh_document_header_assets_checksum_sha256" class="document_header_assets_checksum_sha256"><?= $Page->renderFieldHeader($Page->checksum_sha256) ?></div></th>
<?php } ?>
<?php if ($Page->image_width->Visible) { // image_width ?>
        <th data-name="image_width" class="<?= $Page->image_width->headerCellClass() ?>"><div id="elh_document_header_assets_image_width" class="document_header_assets_image_width"><?= $Page->renderFieldHeader($Page->image_width) ?></div></th>
<?php } ?>
<?php if ($Page->image_height->Visible) { // image_height ?>
        <th data-name="image_height" class="<?= $Page->image_height->headerCellClass() ?>"><div id="elh_document_header_assets_image_height" class="document_header_assets_image_height"><?= $Page->renderFieldHeader($Page->image_height) ?></div></th>
<?php } ?>
<?php if ($Page->render_width->Visible) { // render_width ?>
        <th data-name="render_width" class="<?= $Page->render_width->headerCellClass() ?>"><div id="elh_document_header_assets_render_width" class="document_header_assets_render_width"><?= $Page->renderFieldHeader($Page->render_width) ?></div></th>
<?php } ?>
<?php if ($Page->render_height->Visible) { // render_height ?>
        <th data-name="render_height" class="<?= $Page->render_height->headerCellClass() ?>"><div id="elh_document_header_assets_render_height" class="document_header_assets_render_height"><?= $Page->renderFieldHeader($Page->render_height) ?></div></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th data-name="is_active" class="<?= $Page->is_active->headerCellClass() ?>"><div id="elh_document_header_assets_is_active" class="document_header_assets_is_active"><?= $Page->renderFieldHeader($Page->is_active) ?></div></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th data-name="created_at" class="<?= $Page->created_at->headerCellClass() ?>"><div id="elh_document_header_assets_created_at" class="document_header_assets_created_at"><?= $Page->renderFieldHeader($Page->created_at) ?></div></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th data-name="updated_at" class="<?= $Page->updated_at->headerCellClass() ?>"><div id="elh_document_header_assets_updated_at" class="document_header_assets_updated_at"><?= $Page->renderFieldHeader($Page->updated_at) ?></div></th>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <th data-name="created_by" class="<?= $Page->created_by->headerCellClass() ?>"><div id="elh_document_header_assets_created_by" class="document_header_assets_created_by"><?= $Page->renderFieldHeader($Page->created_by) ?></div></th>
<?php } ?>
<?php if ($Page->updated_by->Visible) { // updated_by ?>
        <th data-name="updated_by" class="<?= $Page->updated_by->headerCellClass() ?>"><div id="elh_document_header_assets_updated_by" class="document_header_assets_updated_by"><?= $Page->renderFieldHeader($Page->updated_by) ?></div></th>
<?php } ?>
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <th data-name="header_slot_id" class="<?= $Page->header_slot_id->headerCellClass() ?>"><div id="elh_document_header_assets_header_slot_id" class="document_header_assets_header_slot_id"><?= $Page->renderFieldHeader($Page->header_slot_id) ?></div></th>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
        <th data-name="part_kind" class="<?= $Page->part_kind->headerCellClass() ?>"><div id="elh_document_header_assets_part_kind" class="document_header_assets_part_kind"><?= $Page->renderFieldHeader($Page->part_kind) ?></div></th>
<?php } ?>
<?php if ($Page->column_pct->Visible) { // column_pct ?>
        <th data-name="column_pct" class="<?= $Page->column_pct->headerCellClass() ?>"><div id="elh_document_header_assets_column_pct" class="document_header_assets_column_pct"><?= $Page->renderFieldHeader($Page->column_pct) ?></div></th>
<?php } ?>
<?php if ($Page->align->Visible) { // align ?>
        <th data-name="align" class="<?= $Page->align->headerCellClass() ?>"><div id="elh_document_header_assets_align" class="document_header_assets_align"><?= $Page->renderFieldHeader($Page->align) ?></div></th>
<?php } ?>
<?php if ($Page->font_name->Visible) { // font_name ?>
        <th data-name="font_name" class="<?= $Page->font_name->headerCellClass() ?>"><div id="elh_document_header_assets_font_name" class="document_header_assets_font_name"><?= $Page->renderFieldHeader($Page->font_name) ?></div></th>
<?php } ?>
<?php if ($Page->line1_size_pt->Visible) { // line1_size_pt ?>
        <th data-name="line1_size_pt" class="<?= $Page->line1_size_pt->headerCellClass() ?>"><div id="elh_document_header_assets_line1_size_pt" class="document_header_assets_line1_size_pt"><?= $Page->renderFieldHeader($Page->line1_size_pt) ?></div></th>
<?php } ?>
<?php if ($Page->line2_size_pt->Visible) { // line2_size_pt ?>
        <th data-name="line2_size_pt" class="<?= $Page->line2_size_pt->headerCellClass() ?>"><div id="elh_document_header_assets_line2_size_pt" class="document_header_assets_line2_size_pt"><?= $Page->renderFieldHeader($Page->line2_size_pt) ?></div></th>
<?php } ?>
<?php if ($Page->line1_bold->Visible) { // line1_bold ?>
        <th data-name="line1_bold" class="<?= $Page->line1_bold->headerCellClass() ?>"><div id="elh_document_header_assets_line1_bold" class="document_header_assets_line1_bold"><?= $Page->renderFieldHeader($Page->line1_bold) ?></div></th>
<?php } ?>
<?php if ($Page->line2_bold->Visible) { // line2_bold ?>
        <th data-name="line2_bold" class="<?= $Page->line2_bold->headerCellClass() ?>"><div id="elh_document_header_assets_line2_bold" class="document_header_assets_line2_bold"><?= $Page->renderFieldHeader($Page->line2_bold) ?></div></th>
<?php } ?>
<?php if ($Page->line1_color->Visible) { // line1_color ?>
        <th data-name="line1_color" class="<?= $Page->line1_color->headerCellClass() ?>"><div id="elh_document_header_assets_line1_color" class="document_header_assets_line1_color"><?= $Page->renderFieldHeader($Page->line1_color) ?></div></th>
<?php } ?>
<?php if ($Page->line2_color->Visible) { // line2_color ?>
        <th data-name="line2_color" class="<?= $Page->line2_color->headerCellClass() ?>"><div id="elh_document_header_assets_line2_color" class="document_header_assets_line2_color"><?= $Page->renderFieldHeader($Page->line2_color) ?></div></th>
<?php } ?>
<?php
// Render list options (header, right)
$Page->ListOptions->render("header", "right");
?>
    </tr>
</thead>
<tbody data-page="<?= $Page->getPageNumber() ?>">
<?php
$Page->setupGrid();
$isInlineAddOrCopy = ($Page->isCopy() || $Page->isAdd());
while ($Page->RecordCount < $Page->StopRecord || $Page->RowIndex === '$rowindex$' || $isInlineAddOrCopy && $Page->RowIndex == 0) {
    if (
        $Page->CurrentRow !== false &&
        $Page->RowIndex !== '$rowindex$' &&
        (!$Page->isGridAdd() || $Page->CurrentMode == "copy") &&
        !($isInlineAddOrCopy && $Page->RowIndex == 0)
    ) {
        $Page->fetch();
    }
    $Page->RecordCount++;
    if ($Page->RecordCount >= $Page->StartRecord) {
        $Page->setupRow();
?>
    <tr <?= $Page->rowAttributes() ?>>
<?php
// Render list options (body, left)
$Page->ListOptions->render("body", "left", $Page->RowCount);
?>
    <?php if ($Page->header_asset_id->Visible) { // header_asset_id ?>
        <td data-name="header_asset_id"<?= $Page->header_asset_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_header_asset_id" class="el_document_header_assets_header_asset_id">
<span<?= $Page->header_asset_id->viewAttributes() ?>>
<?= $Page->header_asset_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->asset_code->Visible) { // asset_code ?>
        <td data-name="asset_code"<?= $Page->asset_code->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_asset_code" class="el_document_header_assets_asset_code">
<span<?= $Page->asset_code->viewAttributes() ?>>
<?= $Page->asset_code->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->asset_name->Visible) { // asset_name ?>
        <td data-name="asset_name"<?= $Page->asset_name->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_asset_name" class="el_document_header_assets_asset_name">
<span<?= $Page->asset_name->viewAttributes() ?>>
<?= $Page->asset_name->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
        <td data-name="document_asset_type_id"<?= $Page->document_asset_type_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_document_asset_type_id" class="el_document_header_assets_document_asset_type_id">
<span<?= $Page->document_asset_type_id->viewAttributes() ?>>
<?= $Page->document_asset_type_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->scope_system_code->Visible) { // scope_system_code ?>
        <td data-name="scope_system_code"<?= $Page->scope_system_code->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_scope_system_code" class="el_document_header_assets_scope_system_code">
<span<?= $Page->scope_system_code->viewAttributes() ?>>
<?= $Page->scope_system_code->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->scope_document_code->Visible) { // scope_document_code ?>
        <td data-name="scope_document_code"<?= $Page->scope_document_code->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_scope_document_code" class="el_document_header_assets_scope_document_code">
<span<?= $Page->scope_document_code->viewAttributes() ?>>
<?= $Page->scope_document_code->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->file_name->Visible) { // file_name ?>
        <td data-name="file_name"<?= $Page->file_name->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_file_name" class="el_document_header_assets_file_name">
<span<?= $Page->file_name->viewAttributes() ?>>
<?= $Page->file_name->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->mime_type->Visible) { // mime_type ?>
        <td data-name="mime_type"<?= $Page->mime_type->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_mime_type" class="el_document_header_assets_mime_type">
<span<?= $Page->mime_type->viewAttributes() ?>>
<?= $Page->mime_type->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->file_size->Visible) { // file_size ?>
        <td data-name="file_size"<?= $Page->file_size->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_file_size" class="el_document_header_assets_file_size">
<span<?= $Page->file_size->viewAttributes() ?>>
<?= $Page->file_size->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->checksum_sha256->Visible) { // checksum_sha256 ?>
        <td data-name="checksum_sha256"<?= $Page->checksum_sha256->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_checksum_sha256" class="el_document_header_assets_checksum_sha256">
<span<?= $Page->checksum_sha256->viewAttributes() ?>>
<?= $Page->checksum_sha256->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->image_width->Visible) { // image_width ?>
        <td data-name="image_width"<?= $Page->image_width->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_image_width" class="el_document_header_assets_image_width">
<span<?= $Page->image_width->viewAttributes() ?>>
<?= $Page->image_width->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->image_height->Visible) { // image_height ?>
        <td data-name="image_height"<?= $Page->image_height->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_image_height" class="el_document_header_assets_image_height">
<span<?= $Page->image_height->viewAttributes() ?>>
<?= $Page->image_height->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->render_width->Visible) { // render_width ?>
        <td data-name="render_width"<?= $Page->render_width->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_render_width" class="el_document_header_assets_render_width">
<span<?= $Page->render_width->viewAttributes() ?>>
<?= $Page->render_width->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->render_height->Visible) { // render_height ?>
        <td data-name="render_height"<?= $Page->render_height->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_render_height" class="el_document_header_assets_render_height">
<span<?= $Page->render_height->viewAttributes() ?>>
<?= $Page->render_height->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->is_active->Visible) { // is_active ?>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_is_active" class="el_document_header_assets_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_at->Visible) { // created_at ?>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_created_at" class="el_document_header_assets_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->updated_at->Visible) { // updated_at ?>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_updated_at" class="el_document_header_assets_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_by->Visible) { // created_by ?>
        <td data-name="created_by"<?= $Page->created_by->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_created_by" class="el_document_header_assets_created_by">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->updated_by->Visible) { // updated_by ?>
        <td data-name="updated_by"<?= $Page->updated_by->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_updated_by" class="el_document_header_assets_updated_by">
<span<?= $Page->updated_by->viewAttributes() ?>>
<?= $Page->updated_by->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <td data-name="header_slot_id"<?= $Page->header_slot_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_header_slot_id" class="el_document_header_assets_header_slot_id">
<span<?= $Page->header_slot_id->viewAttributes() ?>>
<?= $Page->header_slot_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->part_kind->Visible) { // part_kind ?>
        <td data-name="part_kind"<?= $Page->part_kind->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_part_kind" class="el_document_header_assets_part_kind">
<span<?= $Page->part_kind->viewAttributes() ?>>
<?= $Page->part_kind->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->column_pct->Visible) { // column_pct ?>
        <td data-name="column_pct"<?= $Page->column_pct->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_column_pct" class="el_document_header_assets_column_pct">
<span<?= $Page->column_pct->viewAttributes() ?>>
<?= $Page->column_pct->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->align->Visible) { // align ?>
        <td data-name="align"<?= $Page->align->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_align" class="el_document_header_assets_align">
<span<?= $Page->align->viewAttributes() ?>>
<?= $Page->align->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->font_name->Visible) { // font_name ?>
        <td data-name="font_name"<?= $Page->font_name->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_font_name" class="el_document_header_assets_font_name">
<span<?= $Page->font_name->viewAttributes() ?>>
<?= $Page->font_name->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->line1_size_pt->Visible) { // line1_size_pt ?>
        <td data-name="line1_size_pt"<?= $Page->line1_size_pt->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_line1_size_pt" class="el_document_header_assets_line1_size_pt">
<span<?= $Page->line1_size_pt->viewAttributes() ?>>
<?= $Page->line1_size_pt->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->line2_size_pt->Visible) { // line2_size_pt ?>
        <td data-name="line2_size_pt"<?= $Page->line2_size_pt->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_line2_size_pt" class="el_document_header_assets_line2_size_pt">
<span<?= $Page->line2_size_pt->viewAttributes() ?>>
<?= $Page->line2_size_pt->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->line1_bold->Visible) { // line1_bold ?>
        <td data-name="line1_bold"<?= $Page->line1_bold->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_line1_bold" class="el_document_header_assets_line1_bold">
<span<?= $Page->line1_bold->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->line1_bold->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->line2_bold->Visible) { // line2_bold ?>
        <td data-name="line2_bold"<?= $Page->line2_bold->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_line2_bold" class="el_document_header_assets_line2_bold">
<span<?= $Page->line2_bold->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->line2_bold->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->line1_color->Visible) { // line1_color ?>
        <td data-name="line1_color"<?= $Page->line1_color->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_line1_color" class="el_document_header_assets_line1_color">
<span<?= $Page->line1_color->viewAttributes() ?>>
<?= $Page->line1_color->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->line2_color->Visible) { // line2_color ?>
        <td data-name="line2_color"<?= $Page->line2_color->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_assets_line2_color" class="el_document_header_assets_line2_color">
<span<?= $Page->line2_color->viewAttributes() ?>>
<?= $Page->line2_color->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
<?php
// Render list options (body, right)
$Page->ListOptions->render("body", "right", $Page->RowCount);
?>
    </tr>
<?php
    }

    // Reset for template row
    if ($Page->RowIndex === '$rowindex$') {
        $Page->RowIndex = 0;
    }
    // Reset inline add/copy row
    if (($Page->isCopy() || $Page->isAdd()) && $Page->RowIndex == 0) {
        $Page->RowIndex = 1;
    }
}
?>
</tbody>
</table><!-- /.ew-table -->
<?php } ?>
</div><!-- /.ew-grid-middle-panel -->
<?php if (!$Page->CurrentAction && !$Page->UseAjaxActions) { ?>
<input type="hidden" name="action" id="action" value="">
<?php } ?>
</form><!-- /.ew-list-form -->
<?php
// Close result set
$Page->Recordset?->free();
?>
<?php if (!$Page->isExport()) { ?>
<div class="card-footer ew-grid-lower-panel">
<?php if (!$Page->isGridAdd() && !($Page->isGridEdit() && $Page->ModalGridEdit) && !$Page->isMultiEdit()) { ?>
<?= $Page->Pager->render() ?>
<?php } ?>
<div class="ew-list-other-options">
<?php $Page->OtherOptions->render("body", "bottom") ?>
</div>
</div>
<?php } ?>
</div><!-- /.ew-grid -->
<?php } else { ?>
<div class="ew-list-other-options">
<?php $Page->OtherOptions->render("body") ?>
</div>
<?php } ?>
</div>
<div id="ew-footer-options">
<?php $Page->FooterOptions?->render("body") ?>
</div>
</main>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<?php if (!$Page->isExport()) { ?>
<script>
// Field event handlers
loadjs.ready("head", function() {
    ew.addEventHandlers("document_header_assets");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
