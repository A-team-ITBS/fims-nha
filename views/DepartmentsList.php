<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentsList = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { departments: currentTable } });
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
<form name="fdepartmentssrch" id="fdepartmentssrch" class="ew-form ew-ext-search-form" action="<?= CurrentPageUrl(false) ?>" novalidate autocomplete="off">
<div id="fdepartmentssrch_search_panel" class="mb-2 mb-sm-0 <?= $Page->SearchPanelClass ?>"><!-- .ew-search-panel -->
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { departments: currentTable } });
var currentForm;
var fdepartmentssrch, currentSearchForm, currentAdvancedSearchForm;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery,
        fields = currentTable.fields;

    // Form object for search
    let form = new ew.FormBuilder()
        .setId("fdepartmentssrch")
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
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "" ? " active" : "" ?>" form="fdepartmentssrch" data-ew-action="search-type"><?= $Language->phrase("QuickSearchAuto") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "=" ? " active" : "" ?>" form="fdepartmentssrch" data-ew-action="search-type" data-search-type="="><?= $Language->phrase("QuickSearchExact") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "AND" ? " active" : "" ?>" form="fdepartmentssrch" data-ew-action="search-type" data-search-type="AND"><?= $Language->phrase("QuickSearchAll") ?></button>
                <button type="button" class="dropdown-item<?= $Page->BasicSearch->getType() == "OR" ? " active" : "" ?>" form="fdepartmentssrch" data-ew-action="search-type" data-search-type="OR"><?= $Language->phrase("QuickSearchAny") ?></button>
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
<input type="hidden" name="t" value="departments">
<?php if ($Page->IsModal) { ?>
<input type="hidden" name="modal" value="1">
<?php } ?>
<div id="gmp_departments" class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<?php if ($Page->TotalRecords > 0 || $Page->isGridEdit() || $Page->isMultiEdit()) { ?>
<table id="tbl_departmentslist" class="<?= $Page->TableClass ?>"><!-- .ew-table -->
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
<?php if ($Page->department_id->Visible) { // department_id ?>
        <th data-name="department_id" class="<?= $Page->department_id->headerCellClass() ?>"><div id="elh_departments_department_id" class="departments_department_id"><?= $Page->renderFieldHeader($Page->department_id) ?></div></th>
<?php } ?>
<?php if ($Page->department_name->Visible) { // department_name ?>
        <th data-name="department_name" class="<?= $Page->department_name->headerCellClass() ?>"><div id="elh_departments_department_name" class="departments_department_name"><?= $Page->renderFieldHeader($Page->department_name) ?></div></th>
<?php } ?>
<?php if ($Page->parent_department_id->Visible) { // parent_department_id ?>
        <th data-name="parent_department_id" class="<?= $Page->parent_department_id->headerCellClass() ?>"><div id="elh_departments_parent_department_id" class="departments_parent_department_id"><?= $Page->renderFieldHeader($Page->parent_department_id) ?></div></th>
<?php } ?>
<?php if ($Page->department_head_id->Visible) { // department_head_id ?>
        <th data-name="department_head_id" class="<?= $Page->department_head_id->headerCellClass() ?>"><div id="elh_departments_department_head_id" class="departments_department_head_id"><?= $Page->renderFieldHeader($Page->department_head_id) ?></div></th>
<?php } ?>
<?php if ($Page->department_code->Visible) { // department_code ?>
        <th data-name="department_code" class="<?= $Page->department_code->headerCellClass() ?>"><div id="elh_departments_department_code" class="departments_department_code"><?= $Page->renderFieldHeader($Page->department_code) ?></div></th>
<?php } ?>
<?php if ($Page->department_acronym->Visible) { // department_acronym ?>
        <th data-name="department_acronym" class="<?= $Page->department_acronym->headerCellClass() ?>"><div id="elh_departments_department_acronym" class="departments_department_acronym"><?= $Page->renderFieldHeader($Page->department_acronym) ?></div></th>
<?php } ?>
<?php if ($Page->office_location->Visible) { // office_location ?>
        <th data-name="office_location" class="<?= $Page->office_location->headerCellClass() ?>"><div id="elh_departments_office_location" class="departments_office_location"><?= $Page->renderFieldHeader($Page->office_location) ?></div></th>
<?php } ?>
<?php if ($Page->contact_number->Visible) { // contact_number ?>
        <th data-name="contact_number" class="<?= $Page->contact_number->headerCellClass() ?>"><div id="elh_departments_contact_number" class="departments_contact_number"><?= $Page->renderFieldHeader($Page->contact_number) ?></div></th>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
        <th data-name="_email" class="<?= $Page->_email->headerCellClass() ?>"><div id="elh_departments__email" class="departments__email"><?= $Page->renderFieldHeader($Page->_email) ?></div></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th data-name="is_active" class="<?= $Page->is_active->headerCellClass() ?>"><div id="elh_departments_is_active" class="departments_is_active"><?= $Page->renderFieldHeader($Page->is_active) ?></div></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th data-name="created_at" class="<?= $Page->created_at->headerCellClass() ?>"><div id="elh_departments_created_at" class="departments_created_at"><?= $Page->renderFieldHeader($Page->created_at) ?></div></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th data-name="updated_at" class="<?= $Page->updated_at->headerCellClass() ?>"><div id="elh_departments_updated_at" class="departments_updated_at"><?= $Page->renderFieldHeader($Page->updated_at) ?></div></th>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <th data-name="rc_id" class="<?= $Page->rc_id->headerCellClass() ?>"><div id="elh_departments_rc_id" class="departments_rc_id"><?= $Page->renderFieldHeader($Page->rc_id) ?></div></th>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
        <th data-name="office_id" class="<?= $Page->office_id->headerCellClass() ?>"><div id="elh_departments_office_id" class="departments_office_id"><?= $Page->renderFieldHeader($Page->office_id) ?></div></th>
<?php } ?>
<?php if ($Page->department_type->Visible) { // department_type ?>
        <th data-name="department_type" class="<?= $Page->department_type->headerCellClass() ?>"><div id="elh_departments_department_type" class="departments_department_type"><?= $Page->renderFieldHeader($Page->department_type) ?></div></th>
<?php } ?>
<?php if ($Page->hierarchy_level->Visible) { // hierarchy_level ?>
        <th data-name="hierarchy_level" class="<?= $Page->hierarchy_level->headerCellClass() ?>"><div id="elh_departments_hierarchy_level" class="departments_hierarchy_level"><?= $Page->renderFieldHeader($Page->hierarchy_level) ?></div></th>
<?php } ?>
<?php if ($Page->is_budget_unit->Visible) { // is_budget_unit ?>
        <th data-name="is_budget_unit" class="<?= $Page->is_budget_unit->headerCellClass() ?>"><div id="elh_departments_is_budget_unit" class="departments_is_budget_unit"><?= $Page->renderFieldHeader($Page->is_budget_unit) ?></div></th>
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
    <?php if ($Page->department_id->Visible) { // department_id ?>
        <td data-name="department_id"<?= $Page->department_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_department_id" class="el_departments_department_id">
<span<?= $Page->department_id->viewAttributes() ?>>
<?= $Page->department_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->department_name->Visible) { // department_name ?>
        <td data-name="department_name"<?= $Page->department_name->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_department_name" class="el_departments_department_name">
<span<?= $Page->department_name->viewAttributes() ?>>
<?= $Page->department_name->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->parent_department_id->Visible) { // parent_department_id ?>
        <td data-name="parent_department_id"<?= $Page->parent_department_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_parent_department_id" class="el_departments_parent_department_id">
<span<?= $Page->parent_department_id->viewAttributes() ?>>
<?= $Page->parent_department_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->department_head_id->Visible) { // department_head_id ?>
        <td data-name="department_head_id"<?= $Page->department_head_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_department_head_id" class="el_departments_department_head_id">
<span<?= $Page->department_head_id->viewAttributes() ?>>
<?= $Page->department_head_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->department_code->Visible) { // department_code ?>
        <td data-name="department_code"<?= $Page->department_code->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_department_code" class="el_departments_department_code">
<span<?= $Page->department_code->viewAttributes() ?>>
<?= $Page->department_code->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->department_acronym->Visible) { // department_acronym ?>
        <td data-name="department_acronym"<?= $Page->department_acronym->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_department_acronym" class="el_departments_department_acronym">
<span<?= $Page->department_acronym->viewAttributes() ?>>
<?= $Page->department_acronym->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_location->Visible) { // office_location ?>
        <td data-name="office_location"<?= $Page->office_location->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_office_location" class="el_departments_office_location">
<span<?= $Page->office_location->viewAttributes() ?>>
<?= $Page->office_location->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->contact_number->Visible) { // contact_number ?>
        <td data-name="contact_number"<?= $Page->contact_number->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_contact_number" class="el_departments_contact_number">
<span<?= $Page->contact_number->viewAttributes() ?>>
<?= $Page->contact_number->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->_email->Visible) { // email ?>
        <td data-name="_email"<?= $Page->_email->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments__email" class="el_departments__email">
<span<?= $Page->_email->viewAttributes() ?>>
<?= $Page->_email->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->is_active->Visible) { // is_active ?>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_is_active" class="el_departments_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->created_at->Visible) { // created_at ?>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_created_at" class="el_departments_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->updated_at->Visible) { // updated_at ?>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_updated_at" class="el_departments_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->rc_id->Visible) { // rc_id ?>
        <td data-name="rc_id"<?= $Page->rc_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_rc_id" class="el_departments_rc_id">
<span<?= $Page->rc_id->viewAttributes() ?>>
<?= $Page->rc_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->office_id->Visible) { // office_id ?>
        <td data-name="office_id"<?= $Page->office_id->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_office_id" class="el_departments_office_id">
<span<?= $Page->office_id->viewAttributes() ?>>
<?= $Page->office_id->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->department_type->Visible) { // department_type ?>
        <td data-name="department_type"<?= $Page->department_type->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_department_type" class="el_departments_department_type">
<span<?= $Page->department_type->viewAttributes() ?>>
<?= $Page->department_type->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->hierarchy_level->Visible) { // hierarchy_level ?>
        <td data-name="hierarchy_level"<?= $Page->hierarchy_level->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_hierarchy_level" class="el_departments_hierarchy_level">
<span<?= $Page->hierarchy_level->viewAttributes() ?>>
<?= $Page->hierarchy_level->getViewValue() ?></span>
</span>
</td>
    <?php } ?>
    <?php if ($Page->is_budget_unit->Visible) { // is_budget_unit ?>
        <td data-name="is_budget_unit"<?= $Page->is_budget_unit->cellAttributes() ?>>
<span id="el<?= $Page->RowIndex == '$rowindex$' ? '$rowindex$' : $Page->RowCount ?>_departments_is_budget_unit" class="el_departments_is_budget_unit">
<span<?= $Page->is_budget_unit->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_budget_unit->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
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
    ew.addEventHandlers("departments");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
