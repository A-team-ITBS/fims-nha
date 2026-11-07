<?php

namespace PHPMaker2024\FIMS;

// Page object
$OfficesList = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { offices: currentTable } });
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
<form name="fofficessrch" id="fofficessrch" class="ew-form ew-ext-search-form" action="<?= CurrentPageUrl(false) ?>" novalidate autocomplete="off">
<div id="fofficessrch_search_panel" class="mb-2 mb-sm-0 <?= $Page->SearchPanelClass ?>"><!-- .ew-search-panel -->
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { offices: currentTable } });
var currentForm;
var fofficessrch, currentSearchForm, currentAdvancedSearchForm;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery,
        fields = currentTable.fields;

    // Form object for search
    let form = new ew.FormBuilder()
        .setId("fofficessrch")
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
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "" ? " active" : "" ?>" form="fofficessrch" data-ew-action="search-type"><?= $Language->phrase("QuickSearchAuto") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "=" ? " active" : "" ?>" form="fofficessrch" data-ew-action="search-type" data-search-type="="><?= $Language->phrase("QuickSearchExact") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "AND" ? " active" : "" ?>" form="fofficessrch" data-ew-action="search-type" data-search-type="AND"><?= $Language->phrase("QuickSearchAll") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "OR" ? " active" : "" ?>" form="fofficessrch" data-ew-action="search-type" data-search-type="OR"><?= $Language->phrase("QuickSearchAny") ?></button>
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
<input type="hidden" name="t" value="offices">
<?php if ($Page->IsModal) { ?>
<input type="hidden" name="modal" value="1">
<?php } ?>
<div id="gmp_offices" class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<?php if ($Page->TotalRecords > 0 || $Page->isGridEdit() || $Page->isMultiEdit()) { ?>
<table id="tbl_officeslist" class="<?= $Page->TableClass ?>"><!-- .ew-table -->
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
<?php if ($Page->office_id->Visible) { // office_id ?>
        <th data-name="office_id" class="<?= $Page->office_id->headerCellClass() ?>"><div id="elh_offices_office_id" class="offices_office_id"><?= $Page->renderFieldHeader($Page->office_id) ?></div></th>
<?php } ?>
<?php if ($Page->office_name->Visible) { // office_name ?>
        <th data-name="office_name" class="<?= $Page->office_name->headerCellClass() ?>"><div id="elh_offices_office_name" class="offices_office_name"><?= $Page->renderFieldHeader($Page->office_name) ?></div></th>
<?php } ?>
<?php if ($Page->office_code->Visible) { // office_code ?>
        <th data-name="office_code" class="<?= $Page->office_code->headerCellClass() ?>"><div id="elh_offices_office_code" class="offices_office_code"><?= $Page->renderFieldHeader($Page->office_code) ?></div></th>
<?php } ?>
<?php if ($Page->office_type->Visible) { // office_type ?>
        <th data-name="office_type" class="<?= $Page->office_type->headerCellClass() ?>"><div id="elh_offices_office_type" class="offices_office_type"><?= $Page->renderFieldHeader($Page->office_type) ?></div></th>
<?php } ?>
<?php if ($Page->region->Visible) { // region ?>
        <th data-name="region" class="<?= $Page->region->headerCellClass() ?>"><div id="elh_offices_region" class="offices_region"><?= $Page->renderFieldHeader($Page->region) ?></div></th>
<?php } ?>
<?php if ($Page->province->Visible) { // province ?>
        <th data-name="province" class="<?= $Page->province->headerCellClass() ?>"><div id="elh_offices_province" class="offices_province"><?= $Page->renderFieldHeader($Page->province) ?></div></th>
<?php } ?>
<?php if ($Page->city_municipality->Visible) { // city_municipality ?>
        <th data-name="city_municipality" class="<?= $Page->city_municipality->headerCellClass() ?>"><div id="elh_offices_city_municipality" class="offices_city_municipality"><?= $Page->renderFieldHeader($Page->city_municipality) ?></div></th>
<?php } ?>
<?php if ($Page->office_contact_number->Visible) { // office_contact_number ?>
        <th data-name="office_contact_number" class="<?= $Page->office_contact_number->headerCellClass() ?>"><div id="elh_offices_office_contact_number" class="offices_office_contact_number"><?= $Page->renderFieldHeader($Page->office_contact_number) ?></div></th>
<?php } ?>
<?php if ($Page->office_email->Visible) { // office_email ?>
        <th data-name="office_email" class="<?= $Page->office_email->headerCellClass() ?>"><div id="elh_offices_office_email" class="offices_office_email"><?= $Page->renderFieldHeader($Page->office_email) ?></div></th>
<?php } ?>
<?php if ($Page->office_telephone->Visible) { // office_telephone ?>
        <th data-name="office_telephone" class="<?= $Page->office_telephone->headerCellClass() ?>"><div id="elh_offices_office_telephone" class="offices_office_telephone"><?= $Page->renderFieldHeader($Page->office_telephone) ?></div></th>
<?php } ?>
<?php if ($Page->office_fax->Visible) { // office_fax ?>
        <th data-name="office_fax" class="<?= $Page->office_fax->headerCellClass() ?>"><div id="elh_offices_office_fax" class="offices_office_fax"><?= $Page->renderFieldHeader($Page->office_fax) ?></div></th>
<?php } ?>
<?php if ($Page->parent_office_id->Visible) { // parent_office_id ?>
        <th data-name="parent_office_id" class="<?= $Page->parent_office_id->headerCellClass() ?>"><div id="elh_offices_parent_office_id" class="offices_parent_office_id"><?= $Page->renderFieldHeader($Page->parent_office_id) ?></div></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th data-name="is_active" class="<?= $Page->is_active->headerCellClass() ?>"><div id="elh_offices_is_active" class="offices_is_active"><?= $Page->renderFieldHeader($Page->is_active) ?></div></th>
<?php } ?>
<?php if ($Page->date_opened->Visible) { // date_opened ?>
        <th data-name="date_opened" class="<?= $Page->date_opened->headerCellClass() ?>"><div id="elh_offices_date_opened" class="offices_date_opened"><?= $Page->renderFieldHeader($Page->date_opened) ?></div></th>
<?php } ?>
<?php if ($Page->date_closed->Visible) { // date_closed ?>
        <th data-name="date_closed" class="<?= $Page->date_closed->headerCellClass() ?>"><div id="elh_offices_date_closed" class="offices_date_closed"><?= $Page->renderFieldHeader($Page->date_closed) ?></div></th>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <th data-name="created_by" class="<?= $Page->created_by->headerCellClass() ?>"><div id="elh_offices_created_by" class="offices_created_by"><?= $Page->renderFieldHeader($Page->created_by) ?></div></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th data-name="created_at" class="<?= $Page->created_at->headerCellClass() ?>"><div id="elh_offices_created_at" class="offices_created_at"><?= $Page->renderFieldHeader($Page->created_at) ?></div></th>
<?php } ?>
<?php if ($Page->modified_by->Visible) { // modified_by ?>
        <th data-name="modified_by" class="<?= $Page->modified_by->headerCellClass() ?>"><div id="elh_offices_modified_by" class="offices_modified_by"><?= $Page->renderFieldHeader($Page->modified_by) ?></div></th>
<?php } ?>
<?php if ($Page->modified_at->Visible) { // modified_at ?>
        <th data-name="modified_at" class="<?= $Page->modified_at->headerCellClass() ?>"><div id="elh_offices_modified_at" class="offices_modified_at"><?= $Page->renderFieldHeader($Page->modified_at) ?></div></th>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <th data-name="rc_id" class="<?= $Page->rc_id->headerCellClass() ?>"><div id="elh_offices_rc_id" class="offices_rc_id"><?= $Page->renderFieldHeader($Page->rc_id) ?></div></th>
<?php } ?>
<?php if ($Page->region_code->Visible) { // region_code ?>
        <th data-name="region_code" class="<?= $Page->region_code->headerCellClass() ?>"><div id="elh_offices_region_code" class="offices_region_code"><?= $Page->renderFieldHeader($Page->region_code) ?></div></th>
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
    <?php if ($Page->office_id->Visible) { // office_id ?>
        <td data-name="office_id"<?= $Page->office_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_id" class="el_offices_office_id">
<span<?= $Page->office_id->viewAttributes() ?>>
<?= $Page->office_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_name->Visible) { // office_name ?>
        <td data-name="office_name"<?= $Page->office_name->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_name" class="el_offices_office_name">
<span<?= $Page->office_name->viewAttributes() ?>>
<?= $Page->office_name->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_code->Visible) { // office_code ?>
        <td data-name="office_code"<?= $Page->office_code->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_code" class="el_offices_office_code">
<span<?= $Page->office_code->viewAttributes() ?>>
<?= $Page->office_code->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_type->Visible) { // office_type ?>
        <td data-name="office_type"<?= $Page->office_type->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_type" class="el_offices_office_type">
<span<?= $Page->office_type->viewAttributes() ?>>
<?= $Page->office_type->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->region->Visible) { // region ?>
        <td data-name="region"<?= $Page->region->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_region" class="el_offices_region">
<span<?= $Page->region->viewAttributes() ?>>
<?= $Page->region->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->province->Visible) { // province ?>
        <td data-name="province"<?= $Page->province->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_province" class="el_offices_province">
<span<?= $Page->province->viewAttributes() ?>>
<?= $Page->province->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->city_municipality->Visible) { // city_municipality ?>
        <td data-name="city_municipality"<?= $Page->city_municipality->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_city_municipality" class="el_offices_city_municipality">
<span<?= $Page->city_municipality->viewAttributes() ?>>
<?= $Page->city_municipality->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_contact_number->Visible) { // office_contact_number ?>
        <td data-name="office_contact_number"<?= $Page->office_contact_number->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_contact_number" class="el_offices_office_contact_number">
<span<?= $Page->office_contact_number->viewAttributes() ?>>
<?= $Page->office_contact_number->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_email->Visible) { // office_email ?>
        <td data-name="office_email"<?= $Page->office_email->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_email" class="el_offices_office_email">
<span<?= $Page->office_email->viewAttributes() ?>>
<?= $Page->office_email->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_telephone->Visible) { // office_telephone ?>
        <td data-name="office_telephone"<?= $Page->office_telephone->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_telephone" class="el_offices_office_telephone">
<span<?= $Page->office_telephone->viewAttributes() ?>>
<?= $Page->office_telephone->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_fax->Visible) { // office_fax ?>
        <td data-name="office_fax"<?= $Page->office_fax->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_office_fax" class="el_offices_office_fax">
<span<?= $Page->office_fax->viewAttributes() ?>>
<?= $Page->office_fax->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->parent_office_id->Visible) { // parent_office_id ?>
        <td data-name="parent_office_id"<?= $Page->parent_office_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_parent_office_id" class="el_offices_parent_office_id">
<span<?= $Page->parent_office_id->viewAttributes() ?>>
<?= $Page->parent_office_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->is_active->Visible) { // is_active ?>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_is_active" class="el_offices_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->date_opened->Visible) { // date_opened ?>
        <td data-name="date_opened"<?= $Page->date_opened->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_date_opened" class="el_offices_date_opened">
<span<?= $Page->date_opened->viewAttributes() ?>>
<?= $Page->date_opened->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->date_closed->Visible) { // date_closed ?>
        <td data-name="date_closed"<?= $Page->date_closed->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_date_closed" class="el_offices_date_closed">
<span<?= $Page->date_closed->viewAttributes() ?>>
<?= $Page->date_closed->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_by->Visible) { // created_by ?>
        <td data-name="created_by"<?= $Page->created_by->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_created_by" class="el_offices_created_by">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_at->Visible) { // created_at ?>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_created_at" class="el_offices_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->modified_by->Visible) { // modified_by ?>
        <td data-name="modified_by"<?= $Page->modified_by->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_modified_by" class="el_offices_modified_by">
<span<?= $Page->modified_by->viewAttributes() ?>>
<?= $Page->modified_by->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->modified_at->Visible) { // modified_at ?>
        <td data-name="modified_at"<?= $Page->modified_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_modified_at" class="el_offices_modified_at">
<span<?= $Page->modified_at->viewAttributes() ?>>
<?= $Page->modified_at->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->rc_id->Visible) { // rc_id ?>
        <td data-name="rc_id"<?= $Page->rc_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_rc_id" class="el_offices_rc_id">
<span<?= $Page->rc_id->viewAttributes() ?>>
<?= $Page->rc_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->region_code->Visible) { // region_code ?>
        <td data-name="region_code"<?= $Page->region_code->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_offices_region_code" class="el_offices_region_code">
<span<?= $Page->region_code->viewAttributes() ?>>
<?= $Page->region_code->getViewValue() ?></span>
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
    ew.addEventHandlers("offices");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
