<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderSlotsList = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_slots: currentTable } });
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
<form name="fdocument_header_slotssrch" id="fdocument_header_slotssrch" class="ew-form ew-ext-search-form" action="<?= CurrentPageUrl(false) ?>" novalidate autocomplete="off">
<div id="fdocument_header_slotssrch_search_panel" class="mb-2 mb-sm-0 <?= $Page->SearchPanelClass ?>"><!-- .ew-search-panel -->
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_slots: currentTable } });
var currentForm;
var fdocument_header_slotssrch, currentSearchForm, currentAdvancedSearchForm;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery,
        fields = currentTable.fields;

    // Form object for search
    let form = new ew.FormBuilder()
        .setId("fdocument_header_slotssrch")
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
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "" ? " active" : "" ?>" form="fdocument_header_slotssrch" data-ew-action="search-type"><?= $Language->phrase("QuickSearchAuto") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "=" ? " active" : "" ?>" form="fdocument_header_slotssrch" data-ew-action="search-type" data-search-type="="><?= $Language->phrase("QuickSearchExact") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "AND" ? " active" : "" ?>" form="fdocument_header_slotssrch" data-ew-action="search-type" data-search-type="AND"><?= $Language->phrase("QuickSearchAll") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "OR" ? " active" : "" ?>" form="fdocument_header_slotssrch" data-ew-action="search-type" data-search-type="OR"><?= $Language->phrase("QuickSearchAny") ?></button>
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
<input type="hidden" name="t" value="document_header_slots">
<?php if ($Page->IsModal) { ?>
<input type="hidden" name="modal" value="1">
<?php } ?>
<div id="gmp_document_header_slots" class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<?php if ($Page->TotalRecords > 0 || $Page->isGridEdit() || $Page->isMultiEdit()) { ?>
<table id="tbl_document_header_slotslist" class="<?= $Page->TableClass ?>"><!-- .ew-table -->
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
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <th data-name="header_slot_id" class="<?= $Page->header_slot_id->headerCellClass() ?>"><div id="elh_document_header_slots_header_slot_id" class="document_header_slots_header_slot_id"><?= $Page->renderFieldHeader($Page->header_slot_id) ?></div></th>
<?php } ?>
<?php if ($Page->slot_code->Visible) { // slot_code ?>
        <th data-name="slot_code" class="<?= $Page->slot_code->headerCellClass() ?>"><div id="elh_document_header_slots_slot_code" class="document_header_slots_slot_code"><?= $Page->renderFieldHeader($Page->slot_code) ?></div></th>
<?php } ?>
<?php if ($Page->slot_name->Visible) { // slot_name ?>
        <th data-name="slot_name" class="<?= $Page->slot_name->headerCellClass() ?>"><div id="elh_document_header_slots_slot_name" class="document_header_slots_slot_name"><?= $Page->renderFieldHeader($Page->slot_name) ?></div></th>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
        <th data-name="part_kind" class="<?= $Page->part_kind->headerCellClass() ?>"><div id="elh_document_header_slots_part_kind" class="document_header_slots_part_kind"><?= $Page->renderFieldHeader($Page->part_kind) ?></div></th>
<?php } ?>
<?php if ($Page->display_order->Visible) { // display_order ?>
        <th data-name="display_order" class="<?= $Page->display_order->headerCellClass() ?>"><div id="elh_document_header_slots_display_order" class="document_header_slots_display_order"><?= $Page->renderFieldHeader($Page->display_order) ?></div></th>
<?php } ?>
<?php if ($Page->default_column_pct->Visible) { // default_column_pct ?>
        <th data-name="default_column_pct" class="<?= $Page->default_column_pct->headerCellClass() ?>"><div id="elh_document_header_slots_default_column_pct" class="document_header_slots_default_column_pct"><?= $Page->renderFieldHeader($Page->default_column_pct) ?></div></th>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
        <th data-name="default_render_width" class="<?= $Page->default_render_width->headerCellClass() ?>"><div id="elh_document_header_slots_default_render_width" class="document_header_slots_default_render_width"><?= $Page->renderFieldHeader($Page->default_render_width) ?></div></th>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
        <th data-name="default_render_height" class="<?= $Page->default_render_height->headerCellClass() ?>"><div id="elh_document_header_slots_default_render_height" class="document_header_slots_default_render_height"><?= $Page->renderFieldHeader($Page->default_render_height) ?></div></th>
<?php } ?>
<?php if ($Page->default_align->Visible) { // default_align ?>
        <th data-name="default_align" class="<?= $Page->default_align->headerCellClass() ?>"><div id="elh_document_header_slots_default_align" class="document_header_slots_default_align"><?= $Page->renderFieldHeader($Page->default_align) ?></div></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th data-name="is_active" class="<?= $Page->is_active->headerCellClass() ?>"><div id="elh_document_header_slots_is_active" class="document_header_slots_is_active"><?= $Page->renderFieldHeader($Page->is_active) ?></div></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th data-name="created_at" class="<?= $Page->created_at->headerCellClass() ?>"><div id="elh_document_header_slots_created_at" class="document_header_slots_created_at"><?= $Page->renderFieldHeader($Page->created_at) ?></div></th>
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
    <?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <td data-name="header_slot_id"<?= $Page->header_slot_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_header_slot_id" class="el_document_header_slots_header_slot_id">
<span<?= $Page->header_slot_id->viewAttributes() ?>>
<?= $Page->header_slot_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->slot_code->Visible) { // slot_code ?>
        <td data-name="slot_code"<?= $Page->slot_code->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_slot_code" class="el_document_header_slots_slot_code">
<span<?= $Page->slot_code->viewAttributes() ?>>
<?= $Page->slot_code->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->slot_name->Visible) { // slot_name ?>
        <td data-name="slot_name"<?= $Page->slot_name->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_slot_name" class="el_document_header_slots_slot_name">
<span<?= $Page->slot_name->viewAttributes() ?>>
<?= $Page->slot_name->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->part_kind->Visible) { // part_kind ?>
        <td data-name="part_kind"<?= $Page->part_kind->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_part_kind" class="el_document_header_slots_part_kind">
<span<?= $Page->part_kind->viewAttributes() ?>>
<?= $Page->part_kind->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->display_order->Visible) { // display_order ?>
        <td data-name="display_order"<?= $Page->display_order->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_display_order" class="el_document_header_slots_display_order">
<span<?= $Page->display_order->viewAttributes() ?>>
<?= $Page->display_order->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->default_column_pct->Visible) { // default_column_pct ?>
        <td data-name="default_column_pct"<?= $Page->default_column_pct->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_default_column_pct" class="el_document_header_slots_default_column_pct">
<span<?= $Page->default_column_pct->viewAttributes() ?>>
<?= $Page->default_column_pct->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->default_render_width->Visible) { // default_render_width ?>
        <td data-name="default_render_width"<?= $Page->default_render_width->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_default_render_width" class="el_document_header_slots_default_render_width">
<span<?= $Page->default_render_width->viewAttributes() ?>>
<?= $Page->default_render_width->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->default_render_height->Visible) { // default_render_height ?>
        <td data-name="default_render_height"<?= $Page->default_render_height->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_default_render_height" class="el_document_header_slots_default_render_height">
<span<?= $Page->default_render_height->viewAttributes() ?>>
<?= $Page->default_render_height->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->default_align->Visible) { // default_align ?>
        <td data-name="default_align"<?= $Page->default_align->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_default_align" class="el_document_header_slots_default_align">
<span<?= $Page->default_align->viewAttributes() ?>>
<?= $Page->default_align->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->is_active->Visible) { // is_active ?>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_is_active" class="el_document_header_slots_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_at->Visible) { // created_at ?>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_document_header_slots_created_at" class="el_document_header_slots_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
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
    ew.addEventHandlers("document_header_slots");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
