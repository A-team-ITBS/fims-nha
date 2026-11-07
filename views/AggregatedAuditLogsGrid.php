<?php

namespace PHPMaker2024\FIMS;

// Set up and run Grid object
$Grid = Container("AggregatedAuditLogsGrid");
$Grid->run();
?>
<?php if (!$Grid->isExport()) { ?>
<script>
var faggregated_audit_logsgrid;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let currentTable = <?= JsonEncode($Grid->toClientVar()) ?>;
    ew.deepAssign(ew.vars, { tables: { aggregated_audit_logs: currentTable } });
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("faggregated_audit_logsgrid")
        .setPageId("grid")
        .setFormKeyCountName("<?= $Grid->FormKeyCountName ?>")

        // Add fields
        .setFields([
            ["aggregated_id", [fields.aggregated_id.visible && fields.aggregated_id.required ? ew.Validators.required(fields.aggregated_id.caption) : null, ew.Validators.integer], fields.aggregated_id.isInvalid],
            ["action_date", [fields.action_date.visible && fields.action_date.required ? ew.Validators.required(fields.action_date.caption) : null], fields.action_date.isInvalid],
            ["script", [fields.script.visible && fields.script.required ? ew.Validators.required(fields.script.caption) : null], fields.script.isInvalid],
            ["user", [fields.user.visible && fields.user.required ? ew.Validators.required(fields.user.caption) : null], fields.user.isInvalid],
            ["_table", [fields._table.visible && fields._table.required ? ew.Validators.required(fields._table.caption) : null], fields._table.isInvalid],
            ["action_type", [fields.action_type.visible && fields.action_type.required ? ew.Validators.required(fields.action_type.caption) : null], fields.action_type.isInvalid]
        ])

        // Check empty row
        .setEmptyRow(
            function (rowIndex) {
                let fobj = this.getForm(),
                    fields = [["aggregated_id",false],["action_date",false],["script",false],["user",false],["_table",false],["action_type",false]];
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
<div id="faggregated_audit_logsgrid" class="ew-form ew-list-form">
<div id="gmp_aggregated_audit_logs" class="card-body ew-grid-middle-panel <?= $Grid->TableContainerClass ?>" style="<?= $Grid->TableContainerStyle ?>">
<table id="tbl_aggregated_audit_logsgrid" class="<?= $Grid->TableClass ?>"><!-- .ew-table -->
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
<?php if ($Grid->aggregated_id->Visible) { // aggregated_id ?>
        <th data-name="aggregated_id" class="<?= $Grid->aggregated_id->headerCellClass() ?>"><div id="elh_aggregated_audit_logs_aggregated_id" class="aggregated_audit_logs_aggregated_id"><?= $Grid->renderFieldHeader($Grid->aggregated_id) ?></div></th>
<?php } ?>
<?php if ($Grid->action_date->Visible) { // action_date ?>
        <th data-name="action_date" class="<?= $Grid->action_date->headerCellClass() ?>"><div id="elh_aggregated_audit_logs_action_date" class="aggregated_audit_logs_action_date"><?= $Grid->renderFieldHeader($Grid->action_date) ?></div></th>
<?php } ?>
<?php if ($Grid->script->Visible) { // script ?>
        <th data-name="script" class="<?= $Grid->script->headerCellClass() ?>"><div id="elh_aggregated_audit_logs_script" class="aggregated_audit_logs_script"><?= $Grid->renderFieldHeader($Grid->script) ?></div></th>
<?php } ?>
<?php if ($Grid->user->Visible) { // user ?>
        <th data-name="user" class="<?= $Grid->user->headerCellClass() ?>"><div id="elh_aggregated_audit_logs_user" class="aggregated_audit_logs_user"><?= $Grid->renderFieldHeader($Grid->user) ?></div></th>
<?php } ?>
<?php if ($Grid->_table->Visible) { // table ?>
        <th data-name="_table" class="<?= $Grid->_table->headerCellClass() ?>"><div id="elh_aggregated_audit_logs__table" class="aggregated_audit_logs__table"><?= $Grid->renderFieldHeader($Grid->_table) ?></div></th>
<?php } ?>
<?php if ($Grid->action_type->Visible) { // action_type ?>
        <th data-name="action_type" class="<?= $Grid->action_type->headerCellClass() ?>"><div id="elh_aggregated_audit_logs_action_type" class="aggregated_audit_logs_action_type"><?= $Grid->renderFieldHeader($Grid->action_type) ?></div></th>
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
    <?php if ($Grid->aggregated_id->Visible) { // aggregated_id ?>
        <td data-name="aggregated_id"<?= $Grid->aggregated_id->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_aggregated_id" class="el_aggregated_audit_logs_aggregated_id">
<input type="<?= $Grid->aggregated_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_aggregated_id" id="x<?= $Grid->RowIndex ?>_aggregated_id" data-table="aggregated_audit_logs" data-field="x_aggregated_id" value="<?= $Grid->aggregated_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->aggregated_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->aggregated_id->formatPattern()) ?>"<?= $Grid->aggregated_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->aggregated_id->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_aggregated_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_aggregated_id" id="o<?= $Grid->RowIndex ?>_aggregated_id" value="<?= HtmlEncode($Grid->aggregated_id->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_aggregated_id" class="el_aggregated_audit_logs_aggregated_id">
<input type="<?= $Grid->aggregated_id->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_aggregated_id" id="x<?= $Grid->RowIndex ?>_aggregated_id" data-table="aggregated_audit_logs" data-field="x_aggregated_id" value="<?= $Grid->aggregated_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->aggregated_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->aggregated_id->formatPattern()) ?>"<?= $Grid->aggregated_id->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->aggregated_id->getErrorMessage() ?></div>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_aggregated_id" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_aggregated_id" id="o<?= $Grid->RowIndex ?>_aggregated_id" value="<?= HtmlEncode($Grid->aggregated_id->OldValue ?? $Grid->aggregated_id->CurrentValue) ?>">
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_aggregated_id" class="el_aggregated_audit_logs_aggregated_id">
<span<?= $Grid->aggregated_id->viewAttributes() ?>>
<?= $Grid->aggregated_id->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_aggregated_id" data-hidden="1" name="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_aggregated_id" id="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_aggregated_id" value="<?= HtmlEncode($Grid->aggregated_id->FormValue) ?>">
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_aggregated_id" data-hidden="1" data-old name="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_aggregated_id" id="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_aggregated_id" value="<?= HtmlEncode($Grid->aggregated_id->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } else { ?>
            <input type="hidden" data-table="aggregated_audit_logs" data-field="x_aggregated_id" data-hidden="1" name="x<?= $Grid->RowIndex ?>_aggregated_id" id="x<?= $Grid->RowIndex ?>_aggregated_id" value="<?= HtmlEncode($Grid->aggregated_id->CurrentValue) ?>">
    <?php } ?>
    <?php if ($Grid->action_date->Visible) { // action_date ?>
        <td data-name="action_date"<?= $Grid->action_date->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_action_date" class="el_aggregated_audit_logs_action_date">
<input type="<?= $Grid->action_date->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_action_date" id="x<?= $Grid->RowIndex ?>_action_date" data-table="aggregated_audit_logs" data-field="x_action_date" value="<?= $Grid->action_date->EditValue ?>" size="20" placeholder="<?= HtmlEncode($Grid->action_date->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->action_date->formatPattern()) ?>"<?= $Grid->action_date->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->action_date->getErrorMessage() ?></div>
<?php if (!$Grid->action_date->ReadOnly && !$Grid->action_date->Disabled && !isset($Grid->action_date->EditAttrs["readonly"]) && !isset($Grid->action_date->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["faggregated_audit_logsgrid", "datetimepicker"], function () {
    let format = "<?= "yyyy-MM-dd" ?>",
        options = {
            localization: {
                locale: ew.LANGUAGE_ID + "-u-nu-" + ew.getNumberingSystem(),
                hourCycle: format.match(/H/) ? "h24" : "h12",
                format,
                ...ew.language.phrase("datetimepicker")
            },
            display: {
                icons: {
                    previous: ew.IS_RTL ? "fa-solid fa-chevron-right" : "fa-solid fa-chevron-left",
                    next: ew.IS_RTL ? "fa-solid fa-chevron-left" : "fa-solid fa-chevron-right"
                },
                components: {
                    clock: !!format.match(/h/i) || !!format.match(/m/) || !!format.match(/s/i),
                    hours: !!format.match(/h/i),
                    minutes: !!format.match(/m/),
                    seconds: !!format.match(/s/i)
                },
                theme: ew.getPreferredTheme()
            }
        };
    ew.createDateTimePicker("faggregated_audit_logsgrid", "x<?= $Grid->RowIndex ?>_action_date", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_action_date" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_action_date" id="o<?= $Grid->RowIndex ?>_action_date" value="<?= HtmlEncode($Grid->action_date->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_action_date" class="el_aggregated_audit_logs_action_date">
<input type="<?= $Grid->action_date->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_action_date" id="x<?= $Grid->RowIndex ?>_action_date" data-table="aggregated_audit_logs" data-field="x_action_date" value="<?= $Grid->action_date->EditValue ?>" size="20" placeholder="<?= HtmlEncode($Grid->action_date->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->action_date->formatPattern()) ?>"<?= $Grid->action_date->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->action_date->getErrorMessage() ?></div>
<?php if (!$Grid->action_date->ReadOnly && !$Grid->action_date->Disabled && !isset($Grid->action_date->EditAttrs["readonly"]) && !isset($Grid->action_date->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["faggregated_audit_logsgrid", "datetimepicker"], function () {
    let format = "<?= "yyyy-MM-dd" ?>",
        options = {
            localization: {
                locale: ew.LANGUAGE_ID + "-u-nu-" + ew.getNumberingSystem(),
                hourCycle: format.match(/H/) ? "h24" : "h12",
                format,
                ...ew.language.phrase("datetimepicker")
            },
            display: {
                icons: {
                    previous: ew.IS_RTL ? "fa-solid fa-chevron-right" : "fa-solid fa-chevron-left",
                    next: ew.IS_RTL ? "fa-solid fa-chevron-left" : "fa-solid fa-chevron-right"
                },
                components: {
                    clock: !!format.match(/h/i) || !!format.match(/m/) || !!format.match(/s/i),
                    hours: !!format.match(/h/i),
                    minutes: !!format.match(/m/),
                    seconds: !!format.match(/s/i)
                },
                theme: ew.getPreferredTheme()
            }
        };
    ew.createDateTimePicker("faggregated_audit_logsgrid", "x<?= $Grid->RowIndex ?>_action_date", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_action_date" class="el_aggregated_audit_logs_action_date">
<span<?= $Grid->action_date->viewAttributes() ?>>
<?= $Grid->action_date->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_action_date" data-hidden="1" name="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_action_date" id="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_action_date" value="<?= HtmlEncode($Grid->action_date->FormValue) ?>">
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_action_date" data-hidden="1" data-old name="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_action_date" id="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_action_date" value="<?= HtmlEncode($Grid->action_date->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->script->Visible) { // script ?>
        <td data-name="script"<?= $Grid->script->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_script" class="el_aggregated_audit_logs_script">
<input type="<?= $Grid->script->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_script" id="x<?= $Grid->RowIndex ?>_script" data-table="aggregated_audit_logs" data-field="x_script" value="<?= $Grid->script->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Grid->script->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->script->formatPattern()) ?>"<?= $Grid->script->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->script->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_script" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_script" id="o<?= $Grid->RowIndex ?>_script" value="<?= HtmlEncode($Grid->script->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_script" class="el_aggregated_audit_logs_script">
<input type="<?= $Grid->script->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_script" id="x<?= $Grid->RowIndex ?>_script" data-table="aggregated_audit_logs" data-field="x_script" value="<?= $Grid->script->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Grid->script->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->script->formatPattern()) ?>"<?= $Grid->script->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->script->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_script" class="el_aggregated_audit_logs_script">
<span<?= $Grid->script->viewAttributes() ?>>
<?= $Grid->script->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_script" data-hidden="1" name="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_script" id="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_script" value="<?= HtmlEncode($Grid->script->FormValue) ?>">
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_script" data-hidden="1" data-old name="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_script" id="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_script" value="<?= HtmlEncode($Grid->script->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->user->Visible) { // user ?>
        <td data-name="user"<?= $Grid->user->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<?php if ($Grid->user->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_user" class="el_aggregated_audit_logs_user">
<span<?= $Grid->user->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Grid->user->getDisplayValue($Grid->user->ViewValue))) ?>"></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user" name="x<?= $Grid->RowIndex ?>_user" value="<?= HtmlEncode($Grid->user->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_user" class="el_aggregated_audit_logs_user">
<input type="<?= $Grid->user->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_user" id="x<?= $Grid->RowIndex ?>_user" data-table="aggregated_audit_logs" data-field="x_user" value="<?= $Grid->user->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Grid->user->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->user->formatPattern()) ?>"<?= $Grid->user->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->user->getErrorMessage() ?></div>
</span>
<?php } ?>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_user" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_user" id="o<?= $Grid->RowIndex ?>_user" value="<?= HtmlEncode($Grid->user->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<?php if ($Grid->user->getSessionValue() != "") { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_user" class="el_aggregated_audit_logs_user">
<span<?= $Grid->user->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Grid->user->getDisplayValue($Grid->user->ViewValue))) ?>"></span>
<input type="hidden" id="x<?= $Grid->RowIndex ?>_user" name="x<?= $Grid->RowIndex ?>_user" value="<?= HtmlEncode($Grid->user->CurrentValue) ?>" data-hidden="1">
</span>
<?php } else { ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_user" class="el_aggregated_audit_logs_user">
<input type="<?= $Grid->user->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_user" id="x<?= $Grid->RowIndex ?>_user" data-table="aggregated_audit_logs" data-field="x_user" value="<?= $Grid->user->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Grid->user->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->user->formatPattern()) ?>"<?= $Grid->user->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->user->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_user" class="el_aggregated_audit_logs_user">
<span<?= $Grid->user->viewAttributes() ?>>
<?= $Grid->user->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_user" data-hidden="1" name="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_user" id="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_user" value="<?= HtmlEncode($Grid->user->FormValue) ?>">
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_user" data-hidden="1" data-old name="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_user" id="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_user" value="<?= HtmlEncode($Grid->user->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->_table->Visible) { // table ?>
        <td data-name="_table"<?= $Grid->_table->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs__table" class="el_aggregated_audit_logs__table">
<input type="<?= $Grid->_table->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>__table" id="x<?= $Grid->RowIndex ?>__table" data-table="aggregated_audit_logs" data-field="x__table" value="<?= $Grid->_table->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Grid->_table->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->_table->formatPattern()) ?>"<?= $Grid->_table->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->_table->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x__table" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>__table" id="o<?= $Grid->RowIndex ?>__table" value="<?= HtmlEncode($Grid->_table->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs__table" class="el_aggregated_audit_logs__table">
<input type="<?= $Grid->_table->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>__table" id="x<?= $Grid->RowIndex ?>__table" data-table="aggregated_audit_logs" data-field="x__table" value="<?= $Grid->_table->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Grid->_table->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->_table->formatPattern()) ?>"<?= $Grid->_table->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->_table->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs__table" class="el_aggregated_audit_logs__table">
<span<?= $Grid->_table->viewAttributes() ?>>
<?= $Grid->_table->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x__table" data-hidden="1" name="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>__table" id="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>__table" value="<?= HtmlEncode($Grid->_table->FormValue) ?>">
<input type="hidden" data-table="aggregated_audit_logs" data-field="x__table" data-hidden="1" data-old name="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>__table" id="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>__table" value="<?= HtmlEncode($Grid->_table->OldValue) ?>">
<?php } ?>
<?php } ?>
</td>
    <?php } ?>
    <?php if ($Grid->action_type->Visible) { // action_type ?>
        <td data-name="action_type"<?= $Grid->action_type->cellAttributes() ?>>
<?php if ($Grid->RowType == RowType::ADD) { // Add record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_action_type" class="el_aggregated_audit_logs_action_type">
<input type="<?= $Grid->action_type->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_action_type" id="x<?= $Grid->RowIndex ?>_action_type" data-table="aggregated_audit_logs" data-field="x_action_type" value="<?= $Grid->action_type->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->action_type->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->action_type->formatPattern()) ?>"<?= $Grid->action_type->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->action_type->getErrorMessage() ?></div>
</span>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_action_type" data-hidden="1" data-old name="o<?= $Grid->RowIndex ?>_action_type" id="o<?= $Grid->RowIndex ?>_action_type" value="<?= HtmlEncode($Grid->action_type->OldValue) ?>">
<?php } ?>
<?php if ($Grid->RowType == RowType::EDIT) { // Edit record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_action_type" class="el_aggregated_audit_logs_action_type">
<input type="<?= $Grid->action_type->getInputTextType() ?>" name="x<?= $Grid->RowIndex ?>_action_type" id="x<?= $Grid->RowIndex ?>_action_type" data-table="aggregated_audit_logs" data-field="x_action_type" value="<?= $Grid->action_type->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Grid->action_type->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Grid->action_type->formatPattern()) ?>"<?= $Grid->action_type->editAttributes() ?>>
<div class="invalid-feedback"><?= $Grid->action_type->getErrorMessage() ?></div>
</span>
<?php } ?>
<?php if ($Grid->RowType == RowType::VIEW) { // View record ?>
<span id="el<?= $Grid->RowIndex == '$rowindex$' ? '$rowindex$' : $Grid->RowCount ?>_aggregated_audit_logs_action_type" class="el_aggregated_audit_logs_action_type">
<span<?= $Grid->action_type->viewAttributes() ?>>
<?= $Grid->action_type->getViewValue() ?></span>
</span>
<?php if ($Grid->isConfirm()) { ?>
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_action_type" data-hidden="1" name="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_action_type" id="faggregated_audit_logsgrid$x<?= $Grid->RowIndex ?>_action_type" value="<?= HtmlEncode($Grid->action_type->FormValue) ?>">
<input type="hidden" data-table="aggregated_audit_logs" data-field="x_action_type" data-hidden="1" data-old name="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_action_type" id="faggregated_audit_logsgrid$o<?= $Grid->RowIndex ?>_action_type" value="<?= HtmlEncode($Grid->action_type->OldValue) ?>">
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
loadjs.ready(["faggregated_audit_logsgrid","load"], () => faggregated_audit_logsgrid.updateLists(<?= $Grid->RowIndex ?><?= $Grid->isAdd() || $Grid->isEdit() || $Grid->isCopy() || $Grid->RowIndex === '$rowindex$' ? ", true" : "" ?>));
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
<input type="hidden" name="detailpage" value="faggregated_audit_logsgrid">
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
    ew.addEventHandlers("aggregated_audit_logs");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
