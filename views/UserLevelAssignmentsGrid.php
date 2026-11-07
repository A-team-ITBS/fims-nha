<?php

namespace PHPMaker2024\FIMS;

// Set up and run Grid object
$Grid = Container("UserLevelAssignmentsGrid");
$Grid->run();
?>
<?php if (!$Grid->isExport()) { ?>
<script>
var fuser_level_assignmentsgrid;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let currentTable = <?= JsonEncode($Grid->toClientVar()) ?>;
    ew.deepAssign(ew.vars, { tables: { user_level_assignments: currentTable } });
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fuser_level_assignmentsgrid")
        .setPageId("grid")
        .setFormKeyCountName("<?= $Grid->FormKeyCountName ?>")

        // Add fields
        .setFields([
            ["assignment_id", [fields.assignment_id.visible && fields.assignment_id.required ? ew.Validators.required(fields.assignment_id.caption) : null], fields.assignment_id.isInvalid],
            ["user_level_id", [fields.user_level_id.visible && fields.user_level_id.required ? ew.Validators.required(fields.user_level_id.caption) : null], fields.user_level_id.isInvalid],
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null], fields.user_id.isInvalid],
            ["assigned_by", [fields.assigned_by.visible && fields.assigned_by.required ? ew.Validators.required(fields.assigned_by.caption) : null], fields.assigned_by.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null], fields.created_at.isInvalid]
        ])

        // Check empty row
        .setEmptyRow(
            function (rowIndex) {
                let fobj = this.getForm(),
                    fields = [["user_level_id",false],["user_id",false]];
                if (fields.some(field => ew.valueChanged(fobj, rowIndex, ...field)))
                    return false;
                return true;
            }
        )

        // Form_CustomValidate
        .setCustomValidate(
            function (fobj) { // DO NOT CHANGE THIS LINE! (except for adding "async" keyword)!
                    // Your custom validation code in JAVASCRIPT here, return false if invalid.
                    return true;
                }
        )

        // Use JavaScript validation or not
        .setValidateRequired(ew.CLIENT_VALIDATE)

        // Dynamic selection lists
        .setLists({
            "user_level_id": <?= $Grid->user_level_id->toClientList($Grid) ?>,
            "user_id": <?= $Grid->user_id->toClientList($Grid) ?>,
        })
        .build();
    window[form.id] = form;
    loadjs.done(form.id);
});
</script>
<?php } ?>
<main class="list">
<div id="ew-header-options">
<?php $Grid->HeaderOptions?->render("body") ?>
</div>
<div id="ew-list">
<?php if ($Grid->TotalRecords > 0 || $Grid->CurrentAction) { ?>
<div class="card ew-card ew-grid<?= $Grid->isAddOrEdit() ? " ew-grid-add-edit" : "" ?> <?= $Grid->TableGridClass ?>">
<div id="fuser_level_assignmentsgrid" class="ew-form ew-list-form">
<div id="gmp_user_level_assignments" class="card-body ew-grid-middle-panel <?= $Grid->TableContainerClass ?>" style="<?= $Grid->TableContainerStyle ?>">
<table id="tbl_user_level_assignmentsgrid" class="<?= $Grid->TableClass ?>"><!-- .ew-table -->
<thead>
    <tr class="ew-table-header">
<?php
// Header row
$Grid->RowType = RowType::HEADER;

// Render list options
$Grid->renderListOptions();

// Render list options (header, left)
$Grid->ListOptions->render("header", "left");
?>
<?php if ($Grid->assignment_id->Visible) { // assignment_id ?>
        <th data-name="assignment_id" class="<?= $Grid->assignment_id->headerCellClass() ?>"><div id="elh_user_level_assignments_assignment_id" class="user_level_assignments_assignment_id"><?= $Grid->renderFieldHeader($Grid->assignment_id) ?></div></th>
<?php } ?>
<?php if ($Grid->user_level_id->Visible) { // user_level_id ?>
        <th data-name="user_level_id" class="<?= $Grid->user_level_id->headerCellClass() ?>"><div id="elh_user_level_assignments_user_level_id" class="user_level_assignments_user_level_id"><?= $Grid->renderFieldHeader($Grid->user_level_id) ?></div></th>
<?php } ?>
<?php if ($Grid->user_id->Visible) { // user_id ?>
        <th data-name="user_id" class="<?= $Grid->user_id->headerCellClass() ?>"><div id="elh_user_level_assignments_user_id" class="user_level_assignments_user_id"><?= $Grid->renderFieldHeader($Grid->user_id) ?></div></th>
<?php } ?>
<?php if ($Grid->assigned_by->Visible) { // assigned_by ?>
        <th data-name="assigned_by" class="<?= $Grid->assigned_by->headerCellClass() ?>"><div id="elh_user_level_assignments_assigned_by" class="user_level_assignments_assigned_by"><?= $Grid->renderFieldHeader($Grid->assigned_by) ?></div></th>
<?php } ?>
<?php if ($Grid->created_at->Visible) { // created_at ?>
        <th data-name="created_at" class="<?= $Grid->created_at->headerCellClass() ?>"><div id="elh_user_level_assignments_created_at" class="user_level_assignments_created_at"><?= $Grid->renderFieldHeader($Grid->created_at) ?></div></th>
<?php } ?>
<?php
// Render list options (header, right)
$Grid->ListOptions->render("header", "right");
?>
    </tr>
</thead>
<tbody data-page="<?= $Grid->getPageNumber() ?>">
<?php
$Grid->setupGrid();
$isInlineAddOrCopy = ($Grid->isCopy() || $Grid->isAdd());
while ($Grid->RecordCount < $Grid->StopRecord || $Grid->RowIndex === '$rowindex$' || $isInlineAddOrCopy && $Grid->RowIndex == 0) {
    if (
        $Grid->CurrentRow !== false &&
        $Grid->RowIndex !== '$rowindex$' &&
        (!$Grid->isGridAdd() || $Grid->CurrentMode == "copy") &&
        !($isInlineAddOrCopy && $Grid->RowIndex == 0)
    ) {
        $Grid->fetch();
    }
    $Grid->RecordCount++;
    if ($Grid->RecordCount >= $Grid->StartRecord) {
        $Grid->setupRow();

        // Skip 1) delete row / empty row for confirm page, 2) hidden row
        if (
            $Grid->RowAction != "delete" &&
            $Grid->RowAction != "insertdelete" &&
            !($Grid->RowAction == "insert" && $Grid->isConfirm() && $Grid->emptyRow()) &&
            $Grid->RowAction != "hide"
        ) {
?>
    <tr <?= $Grid->rowAttributes() ?>>
<?php
// Render list options (body, left)
$Grid->ListOptions->render("body", "left", $Grid->RowCount);
?>
    <?php if ($Grid->assignment_id->Visible) { // assignment_id ?>
        <td data-name="assignment_id"<?= $Grid->assignment_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_assignment_id" class="el_user_level_assignments_assignment_id"></span>
<input type="hidden" data-table="user_level_assignments" data-field="x_assignment_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_assignment_id" id="o<?= $Grid->RowIndex ?>_assignment_id" value="<?= HtmlEncode($Grid->assignment_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_assignment_id" class="el_user_level_assignments_assignment_id">
<span<?= $Grid->assignment_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Grid->assignment_id->getDisplayValue($Grid->assignment_id->EditValue))) ?>"></span>
<input type="hidden" data-table="user_level_assignments" data-field="x_assignment_id" data-hidden="1" name="x<?= $Grid->RowIndex ?>_assignment_id" id="x<?= $Grid->RowIndex ?>_assignment_id" value="<?= HtmlEncode($Grid->assignment_id->CurrentValue) ?>">
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_assignment_id" class="el_user_level_assignments_assignment_id">
<span<?= $Grid->assignment_id->viewAttributes() ?>>
<?= $Grid->assignment_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_assignment_id" data-hidden="1" name="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_assignment_id" id="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_assignment_id" value="<?= HtmlEncode($Grid->assignment_id->FormValue) ?>">
<input type="hidden" data-table="user_level_assignments" data-field="x_assignment_id" data-hidden="1" data-old name="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_assignment_id" id="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_assignment_id" value="<?= HtmlEncode($Grid->assignment_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } else { ?>
            <input type="hidden" data-table="user_level_assignments" data-field="x_assignment_id" data-hidden="1" name="x<?= $Grid->RowIndex ?>_assignment_id" id="x<?= $Grid->RowIndex ?>_assignment_id" value="<?= HtmlEncode($Grid->assignment_id->CurrentValue) ?>">
    <?php } ?>
    <?php if ($Grid->user_level_id->Visible) { // user_level_id ?>
        <td data-name="user_level_id"<?= $Grid->user_level_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<?php if ($Grid->user_level_id->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_level_id" class="el_user_level_assignments_user_level_id">
<span<?= $Grid->user_level_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Grid->user_level_id->getDisplayValue($Grid->user_level_id->ViewValue) ?></span></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user_level_id" name="x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_level_id" class="el_user_level_assignments_user_level_id">
    <select
        id="x<?= $Grid->RowIndex ?>_user_level_id"
        name="x<?= $Grid->RowIndex ?>_user_level_id"
        class="form-select ew-select<?= $Grid->user_level_id->isInvalidClass() ?>"
        <?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
        data-select2-id="fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_level_id"
        <?php } ?>
        data-table="user_level_assignments"
        data-field="x_user_level_id"
        data-value-separator="<?= $Grid->user_level_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->user_level_id->getPlaceHolder()) ?>"
        <?= $Grid->user_level_id->editAttributes() ?>>
        <?= $Grid->user_level_id->selectOptionListHtml("x{$Grid->RowIndex}_user_level_id") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->user_level_id->getErrorMessage() ?></div>
<?= $Grid->user_level_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_user_level_id") ?>
<?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fuser_level_assignmentsgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_user_level_id", selectId: "fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_level_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fuser_level_assignmentsgrid.lists.user_level_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_user_level_id", form: "fuser_level_assignmentsgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_user_level_id", form: "fuser_level_assignmentsgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.user_level_assignments.fields.user_level_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_user_level_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_user_level_id" id="o<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<?php if ($Grid->user_level_id->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_level_id" class="el_user_level_assignments_user_level_id">
<span<?= $Grid->user_level_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Grid->user_level_id->getDisplayValue($Grid->user_level_id->ViewValue) ?></span></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user_level_id" name="x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_level_id" class="el_user_level_assignments_user_level_id">
    <select
        id="x<?= $Grid->RowIndex ?>_user_level_id"
        name="x<?= $Grid->RowIndex ?>_user_level_id"
        class="form-select ew-select<?= $Grid->user_level_id->isInvalidClass() ?>"
        <?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
        data-select2-id="fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_level_id"
        <?php } ?>
        data-table="user_level_assignments"
        data-field="x_user_level_id"
        data-value-separator="<?= $Grid->user_level_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->user_level_id->getPlaceHolder()) ?>"
        <?= $Grid->user_level_id->editAttributes() ?>>
        <?= $Grid->user_level_id->selectOptionListHtml("x{$Grid->RowIndex}_user_level_id") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->user_level_id->getErrorMessage() ?></div>
<?= $Grid->user_level_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_user_level_id") ?>
<?php if (!$Grid->user_level_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fuser_level_assignmentsgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_user_level_id", selectId: "fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_level_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fuser_level_assignmentsgrid.lists.user_level_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_user_level_id", form: "fuser_level_assignmentsgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_user_level_id", form: "fuser_level_assignmentsgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.user_level_assignments.fields.user_level_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_level_id" class="el_user_level_assignments_user_level_id">
<span<?= $Grid->user_level_id->viewAttributes() ?>>
<?= $Grid->user_level_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_user_level_id" data-hidden="1" name="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_user_level_id" id="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->FormValue) ?>">
<input type="hidden" data-table="user_level_assignments" data-field="x_user_level_id" data-hidden="1" data-old name="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_user_level_id" id="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_user_level_id" value="<?= HtmlEncode($Grid->user_level_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->user_id->Visible) { // user_id ?>
        <td data-name="user_id"<?= $Grid->user_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<?php if ($Grid->user_id->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_id" class="el_user_level_assignments_user_id">
<span<?= $Grid->user_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Grid->user_id->getDisplayValue($Grid->user_id->ViewValue) ?></span></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user_id" name="x<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_id" class="el_user_level_assignments_user_id">
    <select
        id="x<?= $Grid->RowIndex ?>_user_id"
        name="x<?= $Grid->RowIndex ?>_user_id"
        class="form-select ew-select<?= $Grid->user_id->isInvalidClass() ?>"
        <?php if (!$Grid->user_id->IsNativeSelect) { ?>
        data-select2-id="fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_id"
        <?php } ?>
        data-table="user_level_assignments"
        data-field="x_user_id"
        data-value-separator="<?= $Grid->user_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->user_id->getPlaceHolder()) ?>"
        <?= $Grid->user_id->editAttributes() ?>>
        <?= $Grid->user_id->selectOptionListHtml("x{$Grid->RowIndex}_user_id") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->user_id->getErrorMessage() ?></div>
<?= $Grid->user_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_user_id") ?>
<?php if (!$Grid->user_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fuser_level_assignmentsgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_user_id", selectId: "fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fuser_level_assignmentsgrid.lists.user_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_user_id", form: "fuser_level_assignmentsgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_user_id", form: "fuser_level_assignmentsgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.user_level_assignments.fields.user_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_user_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_user_id" id="o<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<?php if ($Grid->user_id->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_id" class="el_user_level_assignments_user_id">
<span<?= $Grid->user_id->viewAttributes() ?>>
<span class="form-control-plaintext"><?= $Grid->user_id->getDisplayValue($Grid->user_id->ViewValue) ?></span></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user_id" name="x<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_id" class="el_user_level_assignments_user_id">
    <select
        id="x<?= $Grid->RowIndex ?>_user_id"
        name="x<?= $Grid->RowIndex ?>_user_id"
        class="form-select ew-select<?= $Grid->user_id->isInvalidClass() ?>"
        <?php if (!$Grid->user_id->IsNativeSelect) { ?>
        data-select2-id="fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_id"
        <?php } ?>
        data-table="user_level_assignments"
        data-field="x_user_id"
        data-value-separator="<?= $Grid->user_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Grid->user_id->getPlaceHolder()) ?>"
        <?= $Grid->user_id->editAttributes() ?>>
        <?= $Grid->user_id->selectOptionListHtml("x{$Grid->RowIndex}_user_id") ?>
    </select>
    <div class="invalid-feedback"><?= $Grid->user_id->getErrorMessage() ?></div>
<?= $Grid->user_id->Lookup->getParamTag($Grid, "p_x" . $Grid->RowIndex . "_user_id") ?>
<?php if (!$Grid->user_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fuser_level_assignmentsgrid", function() {
    var options = { name: "x<?= $Grid->RowIndex ?>_user_id", selectId: "fuser_level_assignmentsgrid_x<?= $Grid->RowIndex ?>_user_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fuser_level_assignmentsgrid.lists.user_id?.lookupOptions.length) {
        options.data = { id: "x<?= $Grid->RowIndex ?>_user_id", form: "fuser_level_assignmentsgrid" };
    } else {
        options.ajax = { id: "x<?= $Grid->RowIndex ?>_user_id", form: "fuser_level_assignmentsgrid", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.user_level_assignments.fields.user_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
<?php } ?>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_user_id" class="el_user_level_assignments_user_id">
<span<?= $Grid->user_id->viewAttributes() ?>>
<?= $Grid->user_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_user_id" data-hidden="1" name="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_user_id" id="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->FormValue) ?>">
<input type="hidden" data-table="user_level_assignments" data-field="x_user_id" data-hidden="1" data-old name="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_user_id" id="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_user_id" value="<?= HtmlEncode($Grid->user_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->assigned_by->Visible) { // assigned_by ?>
        <td data-name="assigned_by"<?= $Grid->assigned_by->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_assigned_by" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_assigned_by" id="o<?= $Grid->RowIndex ?>_assigned_by" value="<?= HtmlEncode($Grid->assigned_by->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_assigned_by" class="el_user_level_assignments_assigned_by">
<span<?= $Grid->assigned_by->viewAttributes() ?>>
<?= $Grid->assigned_by->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_assigned_by" data-hidden="1" name="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_assigned_by" id="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_assigned_by" value="<?= HtmlEncode($Grid->assigned_by->FormValue) ?>">
<input type="hidden" data-table="user_level_assignments" data-field="x_assigned_by" data-hidden="1" data-old name="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_assigned_by" id="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_assigned_by" value="<?= HtmlEncode($Grid->assigned_by->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->created_at->Visible) { // created_at ?>
        <td data-name="created_at"<?= $Grid->created_at->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_created_at" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_created_at" id="o<?= $Grid->RowIndex ?>_created_at" value="<?= HtmlEncode($Grid->created_at->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_user_level_assignments_created_at" class="el_user_level_assignments_created_at">
<span<?= $Grid->created_at->viewAttributes() ?>>
<?= $Grid->created_at->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="user_level_assignments" data-field="x_created_at" data-hidden="1" name="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_created_at" id="fuser_level_assignmentsgrid$x<?= $Grid->RowIndex ?>_created_at" value="<?= HtmlEncode($Grid->created_at->FormValue) ?>">
<input type="hidden" data-table="user_level_assignments" data-field="x_created_at" data-hidden="1" data-old name="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_created_at" id="fuser_level_assignmentsgrid$o<?= $Grid->RowIndex ?>_created_at" value="<?= HtmlEncode($Grid->created_at->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
<?php
// Render list options (body, right)
$Grid->ListOptions->render("body", "right", $Grid->RowCount);
?>
    </tr>
<?php if ($Grid->RowType == RowType::ADD || $Grid->RowType == RowType::EDIT) { ?>
<script data-rowindex="<?= $Grid->RowIndex ?>">
loadjs.ready(["fuser_level_assignmentsgrid","load"], () => fuser_level_assignmentsgrid.updateLists(<?= $Grid->RowIndex ?><?= $Grid->isAdd() || $Grid->isEdit() || $Grid->isCopy() || $Grid->RowIndex === '$rowindex$' ? ", true" : "" ?>));
</script>
<?php } ?>
<?php
    }
    } // End delete row checking

    // Reset for template row
    if ($Grid->RowIndex === '$rowindex$') {
        $Grid->RowIndex = 0;
    }
    // Reset inline add/copy row
    if (($Grid->isCopy() || $Grid->isAdd()) && $Grid->RowIndex == 0) {
        $Grid->RowIndex = 1;
    }
}
?>
</tbody>
</table><!-- /.ew-table -->
<?php if ($Grid->CurrentMode == "add" || $Grid->CurrentMode == "copy") { ?>
<input type="hidden" name="<?= $Grid->FormKeyCountName ?>" id="<?= $Grid->FormKeyCountName ?>" value="<?= $Grid->KeyCount ?>">
<?= $Grid->MultiSelectKey ?>
<?php } ?>
<?php if ($Grid->CurrentMode == "edit") { ?>
<input type="hidden" name="<?= $Grid->FormKeyCountName ?>" id="<?= $Grid->FormKeyCountName ?>" value="<?= $Grid->KeyCount ?>">
<?= $Grid->MultiSelectKey ?>
<?php } ?>
</div><!-- /.ew-grid-middle-panel -->
<?php if ($Grid->CurrentMode == "") { ?>
<input type="hidden" name="action" id="action" value="">
<?php } ?>
<input type="hidden" name="detailpage" value="fuser_level_assignmentsgrid">
</div><!-- /.ew-list-form -->
<?php
// Close result set
$Grid->Recordset?->free();
?>
<?php if ($Grid->ShowOtherOptions) { ?>
<div class="card-footer ew-grid-lower-panel">
<?php $Grid->OtherOptions->render("body", "bottom") ?>
</div>
<?php } ?>
</div><!-- /.ew-grid -->
<?php } else { ?>
<div class="ew-list-other-options">
<?php $Grid->OtherOptions->render("body") ?>
</div>
<?php } ?>
</div>
<div id="ew-footer-options">
<?php $Grid->FooterOptions?->render("body") ?>
</div>
</main>
<?php if (!$Grid->isExport()) { ?>
<script>
// Field event handlers
loadjs.ready("head", function() {
    ew.addEventHandlers("user_level_assignments");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
