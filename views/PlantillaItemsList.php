<?php

namespace PHPMaker2024\FIMS;

// Page object
$PlantillaItemsList = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { plantilla_items: currentTable } });
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
<form name="fplantilla_itemssrch" id="fplantilla_itemssrch" class="ew-form ew-ext-search-form" action="<?= CurrentPageUrl(false) ?>" novalidate autocomplete="off">
<div id="fplantilla_itemssrch_search_panel" class="mb-2 mb-sm-0 <?= $Page->SearchPanelClass ?>"><!-- .ew-search-panel -->
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { plantilla_items: currentTable } });
var currentForm;
var fplantilla_itemssrch, currentSearchForm, currentAdvancedSearchForm;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery,
        fields = currentTable.fields;

    // Form object for search
    let form = new ew.FormBuilder()
        .setId("fplantilla_itemssrch")
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
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "" ? " active" : "" ?>" form="fplantilla_itemssrch" data-ew-action="search-type"><?= $Language->phrase("QuickSearchAuto") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "=" ? " active" : "" ?>" form="fplantilla_itemssrch" data-ew-action="search-type" data-search-type="="><?= $Language->phrase("QuickSearchExact") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "AND" ? " active" : "" ?>" form="fplantilla_itemssrch" data-ew-action="search-type" data-search-type="AND"><?= $Language->phrase("QuickSearchAll") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "OR" ? " active" : "" ?>" form="fplantilla_itemssrch" data-ew-action="search-type" data-search-type="OR"><?= $Language->phrase("QuickSearchAny") ?></button>
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
<input type="hidden" name="t" value="plantilla_items">
<?php if ($Page->IsModal) { ?>
<input type="hidden" name="modal" value="1">
<?php } ?>
<div id="gmp_plantilla_items" class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<?php if ($Page->TotalRecords > 0 || $Page->isGridEdit() || $Page->isMultiEdit()) { ?>
<table id="tbl_plantilla_itemslist" class="<?= $Page->TableClass ?>"><!-- .ew-table -->
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
<?php if ($Page->plantilla_item_id->Visible) { // plantilla_item_id ?>
        <th data-name="plantilla_item_id" class="<?= $Page->plantilla_item_id->headerCellClass() ?>"><div id="elh_plantilla_items_plantilla_item_id" class="plantilla_items_plantilla_item_id"><?= $Page->renderFieldHeader($Page->plantilla_item_id) ?></div></th>
<?php } ?>
<?php if ($Page->department_id->Visible) { // department_id ?>
        <th data-name="department_id" class="<?= $Page->department_id->headerCellClass() ?>"><div id="elh_plantilla_items_department_id" class="plantilla_items_department_id"><?= $Page->renderFieldHeader($Page->department_id) ?></div></th>
<?php } ?>
<?php if ($Page->position_id->Visible) { // position_id ?>
        <th data-name="position_id" class="<?= $Page->position_id->headerCellClass() ?>"><div id="elh_plantilla_items_position_id" class="plantilla_items_position_id"><?= $Page->renderFieldHeader($Page->position_id) ?></div></th>
<?php } ?>
<?php if ($Page->item_number->Visible) { // item_number ?>
        <th data-name="item_number" class="<?= $Page->item_number->headerCellClass() ?>"><div id="elh_plantilla_items_item_number" class="plantilla_items_item_number"><?= $Page->renderFieldHeader($Page->item_number) ?></div></th>
<?php } ?>
<?php if ($Page->item_description->Visible) { // item_description ?>
        <th data-name="item_description" class="<?= $Page->item_description->headerCellClass() ?>"><div id="elh_plantilla_items_item_description" class="plantilla_items_item_description"><?= $Page->renderFieldHeader($Page->item_description) ?></div></th>
<?php } ?>
<?php if ($Page->authorized_count->Visible) { // authorized_count ?>
        <th data-name="authorized_count" class="<?= $Page->authorized_count->headerCellClass() ?>"><div id="elh_plantilla_items_authorized_count" class="plantilla_items_authorized_count"><?= $Page->renderFieldHeader($Page->authorized_count) ?></div></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th data-name="is_active" class="<?= $Page->is_active->headerCellClass() ?>"><div id="elh_plantilla_items_is_active" class="plantilla_items_is_active"><?= $Page->renderFieldHeader($Page->is_active) ?></div></th>
<?php } ?>
<?php if ($Page->effective_date->Visible) { // effective_date ?>
        <th data-name="effective_date" class="<?= $Page->effective_date->headerCellClass() ?>"><div id="elh_plantilla_items_effective_date" class="plantilla_items_effective_date"><?= $Page->renderFieldHeader($Page->effective_date) ?></div></th>
<?php } ?>
<?php if ($Page->abolition_date->Visible) { // abolition_date ?>
        <th data-name="abolition_date" class="<?= $Page->abolition_date->headerCellClass() ?>"><div id="elh_plantilla_items_abolition_date" class="plantilla_items_abolition_date"><?= $Page->renderFieldHeader($Page->abolition_date) ?></div></th>
<?php } ?>
<?php if ($Page->annual_budget_allocation->Visible) { // annual_budget_allocation ?>
        <th data-name="annual_budget_allocation" class="<?= $Page->annual_budget_allocation->headerCellClass() ?>"><div id="elh_plantilla_items_annual_budget_allocation" class="plantilla_items_annual_budget_allocation"><?= $Page->renderFieldHeader($Page->annual_budget_allocation) ?></div></th>
<?php } ?>
<?php if ($Page->monthly_salary_allocation->Visible) { // monthly_salary_allocation ?>
        <th data-name="monthly_salary_allocation" class="<?= $Page->monthly_salary_allocation->headerCellClass() ?>"><div id="elh_plantilla_items_monthly_salary_allocation" class="plantilla_items_monthly_salary_allocation"><?= $Page->renderFieldHeader($Page->monthly_salary_allocation) ?></div></th>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <th data-name="created_by" class="<?= $Page->created_by->headerCellClass() ?>"><div id="elh_plantilla_items_created_by" class="plantilla_items_created_by"><?= $Page->renderFieldHeader($Page->created_by) ?></div></th>
<?php } ?>
<?php if ($Page->approved_by->Visible) { // approved_by ?>
        <th data-name="approved_by" class="<?= $Page->approved_by->headerCellClass() ?>"><div id="elh_plantilla_items_approved_by" class="plantilla_items_approved_by"><?= $Page->renderFieldHeader($Page->approved_by) ?></div></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th data-name="created_at" class="<?= $Page->created_at->headerCellClass() ?>"><div id="elh_plantilla_items_created_at" class="plantilla_items_created_at"><?= $Page->renderFieldHeader($Page->created_at) ?></div></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th data-name="updated_at" class="<?= $Page->updated_at->headerCellClass() ?>"><div id="elh_plantilla_items_updated_at" class="plantilla_items_updated_at"><?= $Page->renderFieldHeader($Page->updated_at) ?></div></th>
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
    <?php if ($Page->plantilla_item_id->Visible) { // plantilla_item_id ?>
        <td data-name="plantilla_item_id"<?= $Page->plantilla_item_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_plantilla_item_id" class="el_plantilla_items_plantilla_item_id">
<span<?= $Page->plantilla_item_id->viewAttributes() ?>>
<?= $Page->plantilla_item_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->department_id->Visible) { // department_id ?>
        <td data-name="department_id"<?= $Page->department_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_department_id" class="el_plantilla_items_department_id">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->position_id->Visible) { // position_id ?>
        <td data-name="position_id"<?= $Page->position_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_position_id" class="el_plantilla_items_position_id">
<span<?= $Page->position_id->viewAttributes() ?>>
<?= $Page->position_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->item_number->Visible) { // item_number ?>
        <td data-name="item_number"<?= $Page->item_number->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_item_number" class="el_plantilla_items_item_number">
<span<?= $Page->item_number->viewAttributes() ?>>
<?= $Page->item_number->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->item_description->Visible) { // item_description ?>
        <td data-name="item_description"<?= $Page->item_description->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_item_description" class="el_plantilla_items_item_description">
<span<?= $Page->item_description->viewAttributes() ?>>
<?= $Page->item_description->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->authorized_count->Visible) { // authorized_count ?>
        <td data-name="authorized_count"<?= $Page->authorized_count->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_authorized_count" class="el_plantilla_items_authorized_count">
<span<?= $Page->authorized_count->viewAttributes() ?>>
<?= $Page->authorized_count->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->is_active->Visible) { // is_active ?>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_is_active" class="el_plantilla_items_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->effective_date->Visible) { // effective_date ?>
        <td data-name="effective_date"<?= $Page->effective_date->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_effective_date" class="el_plantilla_items_effective_date">
<span<?= $Page->effective_date->viewAttributes() ?>>
<?= $Page->effective_date->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->abolition_date->Visible) { // abolition_date ?>
        <td data-name="abolition_date"<?= $Page->abolition_date->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_abolition_date" class="el_plantilla_items_abolition_date">
<span<?= $Page->abolition_date->viewAttributes() ?>>
<?= $Page->abolition_date->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->annual_budget_allocation->Visible) { // annual_budget_allocation ?>
        <td data-name="annual_budget_allocation"<?= $Page->annual_budget_allocation->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_annual_budget_allocation" class="el_plantilla_items_annual_budget_allocation">
<span<?= $Page->annual_budget_allocation->viewAttributes() ?>>
<?= $Page->annual_budget_allocation->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->monthly_salary_allocation->Visible) { // monthly_salary_allocation ?>
        <td data-name="monthly_salary_allocation"<?= $Page->monthly_salary_allocation->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_monthly_salary_allocation" class="el_plantilla_items_monthly_salary_allocation">
<span<?= $Page->monthly_salary_allocation->viewAttributes() ?>>
<?= $Page->monthly_salary_allocation->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_by->Visible) { // created_by ?>
        <td data-name="created_by"<?= $Page->created_by->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_created_by" class="el_plantilla_items_created_by">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->approved_by->Visible) { // approved_by ?>
        <td data-name="approved_by"<?= $Page->approved_by->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_approved_by" class="el_plantilla_items_approved_by">
<span<?= $Page->approved_by->viewAttributes() ?>>
<?= $Page->approved_by->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_at->Visible) { // created_at ?>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_created_at" class="el_plantilla_items_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->updated_at->Visible) { // updated_at ?>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_plantilla_items_updated_at" class="el_plantilla_items_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
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
    ew.addEventHandlers("plantilla_items");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
