<?php

namespace PHPMaker2024\FIMS;

// Page object
$PositionsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { positions: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fpositionsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpositionsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["position_category_id", [fields.position_category_id.visible && fields.position_category_id.required ? ew.Validators.required(fields.position_category_id.caption) : null], fields.position_category_id.isInvalid],
            ["position_code", [fields.position_code.visible && fields.position_code.required ? ew.Validators.required(fields.position_code.caption) : null], fields.position_code.isInvalid],
            ["position_title", [fields.position_title.visible && fields.position_title.required ? ew.Validators.required(fields.position_title.caption) : null], fields.position_title.isInvalid],
            ["position_level", [fields.position_level.visible && fields.position_level.required ? ew.Validators.required(fields.position_level.caption) : null, ew.Validators.integer], fields.position_level.isInvalid],
            ["job_description", [fields.job_description.visible && fields.job_description.required ? ew.Validators.required(fields.job_description.caption) : null], fields.job_description.isInvalid],
            ["salary_grade", [fields.salary_grade.visible && fields.salary_grade.required ? ew.Validators.required(fields.salary_grade.caption) : null, ew.Validators.integer], fields.salary_grade.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["created_by", [fields.created_by.visible && fields.created_by.required ? ew.Validators.required(fields.created_by.caption) : null, ew.Validators.integer], fields.created_by.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null, ew.Validators.datetime(fields.created_at.clientFormatPattern)], fields.created_at.isInvalid],
            ["updated_at", [fields.updated_at.visible && fields.updated_at.required ? ew.Validators.required(fields.updated_at.caption) : null, ew.Validators.datetime(fields.updated_at.clientFormatPattern)], fields.updated_at.isInvalid],
            ["step_increment", [fields.step_increment.visible && fields.step_increment.required ? ew.Validators.required(fields.step_increment.caption) : null, ew.Validators.integer], fields.step_increment.isInvalid],
            ["qualification_standards", [fields.qualification_standards.visible && fields.qualification_standards.required ? ew.Validators.required(fields.qualification_standards.caption) : null], fields.qualification_standards.isInvalid],
            ["competency_requirements", [fields.competency_requirements.visible && fields.competency_requirements.required ? ew.Validators.required(fields.competency_requirements.caption) : null], fields.competency_requirements.isInvalid]
        ])

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
            "position_category_id": <?= $Page->position_category_id->toClientList($Page) ?>,
            "is_active": <?= $Page->is_active->toClientList($Page) ?>,
        })
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
<form name="fpositionsadd" id="fpositionsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="positions">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->position_category_id->Visible) { // position_category_id ?>
    <div id="r_position_category_id"<?= $Page->position_category_id->rowAttributes() ?>>
        <label id="elh_positions_position_category_id" for="x_position_category_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->position_category_id->caption() ?><?= $Page->position_category_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->position_category_id->cellAttributes() ?>>
<span id="el_positions_position_category_id">
    <select
        id="x_position_category_id"
        name="x_position_category_id"
        class="form-select ew-select<?= $Page->position_category_id->isInvalidClass() ?>"
        <?php if (!$Page->position_category_id->IsNativeSelect) { ?>
        data-select2-id="fpositionsadd_x_position_category_id"
        <?php } ?>
        data-table="positions"
        data-field="x_position_category_id"
        data-value-separator="<?= $Page->position_category_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Page->position_category_id->getPlaceHolder()) ?>"
        <?= $Page->position_category_id->editAttributes() ?>>
        <?= $Page->position_category_id->selectOptionListHtml("x_position_category_id") ?>
    </select>
    <?= $Page->position_category_id->getCustomMessage() ?>
    <div class="invalid-feedback"><?= $Page->position_category_id->getErrorMessage() ?></div>
<?= $Page->position_category_id->Lookup->getParamTag($Page, "p_x_position_category_id") ?>
<?php if (!$Page->position_category_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fpositionsadd", function() {
    var options = { name: "x_position_category_id", selectId: "fpositionsadd_x_position_category_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fpositionsadd.lists.position_category_id?.lookupOptions.length) {
        options.data = { id: "x_position_category_id", form: "fpositionsadd" };
    } else {
        options.ajax = { id: "x_position_category_id", form: "fpositionsadd", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.positions.fields.position_category_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->position_code->Visible) { // position_code ?>
    <div id="r_position_code"<?= $Page->position_code->rowAttributes() ?>>
        <label id="elh_positions_position_code" for="x_position_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->position_code->caption() ?><?= $Page->position_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->position_code->cellAttributes() ?>>
<span id="el_positions_position_code">
<input type="<?= $Page->position_code->getInputTextType() ?>" name="x_position_code" id="x_position_code" data-table="positions" data-field="x_position_code" value="<?= $Page->position_code->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->position_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->position_code->formatPattern()) ?>"<?= $Page->position_code->editAttributes() ?> aria-describedby="x_position_code_help">
<?= $Page->position_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->position_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->position_title->Visible) { // position_title ?>
    <div id="r_position_title"<?= $Page->position_title->rowAttributes() ?>>
        <label id="elh_positions_position_title" for="x_position_title" class="<?= $Page->LeftColumnClass ?>"><?= $Page->position_title->caption() ?><?= $Page->position_title->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->position_title->cellAttributes() ?>>
<span id="el_positions_position_title">
<input type="<?= $Page->position_title->getInputTextType() ?>" name="x_position_title" id="x_position_title" data-table="positions" data-field="x_position_title" value="<?= $Page->position_title->EditValue ?>" size="30" maxlength="200" placeholder="<?= HtmlEncode($Page->position_title->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->position_title->formatPattern()) ?>"<?= $Page->position_title->editAttributes() ?> aria-describedby="x_position_title_help">
<?= $Page->position_title->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->position_title->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->position_level->Visible) { // position_level ?>
    <div id="r_position_level"<?= $Page->position_level->rowAttributes() ?>>
        <label id="elh_positions_position_level" for="x_position_level" class="<?= $Page->LeftColumnClass ?>"><?= $Page->position_level->caption() ?><?= $Page->position_level->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->position_level->cellAttributes() ?>>
<span id="el_positions_position_level">
<input type="<?= $Page->position_level->getInputTextType() ?>" name="x_position_level" id="x_position_level" data-table="positions" data-field="x_position_level" value="<?= $Page->position_level->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->position_level->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->position_level->formatPattern()) ?>"<?= $Page->position_level->editAttributes() ?> aria-describedby="x_position_level_help">
<?= $Page->position_level->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->position_level->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->job_description->Visible) { // job_description ?>
    <div id="r_job_description"<?= $Page->job_description->rowAttributes() ?>>
        <label id="elh_positions_job_description" for="x_job_description" class="<?= $Page->LeftColumnClass ?>"><?= $Page->job_description->caption() ?><?= $Page->job_description->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->job_description->cellAttributes() ?>>
<span id="el_positions_job_description">
<textarea data-table="positions" data-field="x_job_description" name="x_job_description" id="x_job_description" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->job_description->getPlaceHolder()) ?>"<?= $Page->job_description->editAttributes() ?> aria-describedby="x_job_description_help"><?= $Page->job_description->EditValue ?></textarea>
<?= $Page->job_description->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->job_description->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->salary_grade->Visible) { // salary_grade ?>
    <div id="r_salary_grade"<?= $Page->salary_grade->rowAttributes() ?>>
        <label id="elh_positions_salary_grade" for="x_salary_grade" class="<?= $Page->LeftColumnClass ?>"><?= $Page->salary_grade->caption() ?><?= $Page->salary_grade->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->salary_grade->cellAttributes() ?>>
<span id="el_positions_salary_grade">
<input type="<?= $Page->salary_grade->getInputTextType() ?>" name="x_salary_grade" id="x_salary_grade" data-table="positions" data-field="x_salary_grade" value="<?= $Page->salary_grade->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->salary_grade->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->salary_grade->formatPattern()) ?>"<?= $Page->salary_grade->editAttributes() ?> aria-describedby="x_salary_grade_help">
<?= $Page->salary_grade->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->salary_grade->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_positions_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_positions_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="positions" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <div id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <label id="elh_positions_created_by" for="x_created_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_by->caption() ?><?= $Page->created_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_by->cellAttributes() ?>>
<span id="el_positions_created_by">
<input type="<?= $Page->created_by->getInputTextType() ?>" name="x_created_by" id="x_created_by" data-table="positions" data-field="x_created_by" value="<?= $Page->created_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->created_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_by->formatPattern()) ?>"<?= $Page->created_by->editAttributes() ?> aria-describedby="x_created_by_help">
<?= $Page->created_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_positions_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_positions_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="positions" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fpositionsadd", "datetimepicker"], function () {
    let format = "<?= DateFormat(0) ?>",
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
    ew.createDateTimePicker("fpositionsadd", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <div id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <label id="elh_positions_updated_at" for="x_updated_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_at->caption() ?><?= $Page->updated_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_positions_updated_at">
<input type="<?= $Page->updated_at->getInputTextType() ?>" name="x_updated_at" id="x_updated_at" data-table="positions" data-field="x_updated_at" value="<?= $Page->updated_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->updated_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_at->formatPattern()) ?>"<?= $Page->updated_at->editAttributes() ?> aria-describedby="x_updated_at_help">
<?= $Page->updated_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_at->getErrorMessage() ?></div>
<?php if (!$Page->updated_at->ReadOnly && !$Page->updated_at->Disabled && !isset($Page->updated_at->EditAttrs["readonly"]) && !isset($Page->updated_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fpositionsadd", "datetimepicker"], function () {
    let format = "<?= DateFormat(0) ?>",
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
    ew.createDateTimePicker("fpositionsadd", "x_updated_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->step_increment->Visible) { // step_increment ?>
    <div id="r_step_increment"<?= $Page->step_increment->rowAttributes() ?>>
        <label id="elh_positions_step_increment" for="x_step_increment" class="<?= $Page->LeftColumnClass ?>"><?= $Page->step_increment->caption() ?><?= $Page->step_increment->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->step_increment->cellAttributes() ?>>
<span id="el_positions_step_increment">
<input type="<?= $Page->step_increment->getInputTextType() ?>" name="x_step_increment" id="x_step_increment" data-table="positions" data-field="x_step_increment" value="<?= $Page->step_increment->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->step_increment->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->step_increment->formatPattern()) ?>"<?= $Page->step_increment->editAttributes() ?> aria-describedby="x_step_increment_help">
<?= $Page->step_increment->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->step_increment->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->qualification_standards->Visible) { // qualification_standards ?>
    <div id="r_qualification_standards"<?= $Page->qualification_standards->rowAttributes() ?>>
        <label id="elh_positions_qualification_standards" for="x_qualification_standards" class="<?= $Page->LeftColumnClass ?>"><?= $Page->qualification_standards->caption() ?><?= $Page->qualification_standards->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->qualification_standards->cellAttributes() ?>>
<span id="el_positions_qualification_standards">
<textarea data-table="positions" data-field="x_qualification_standards" name="x_qualification_standards" id="x_qualification_standards" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->qualification_standards->getPlaceHolder()) ?>"<?= $Page->qualification_standards->editAttributes() ?> aria-describedby="x_qualification_standards_help"><?= $Page->qualification_standards->EditValue ?></textarea>
<?= $Page->qualification_standards->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->qualification_standards->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->competency_requirements->Visible) { // competency_requirements ?>
    <div id="r_competency_requirements"<?= $Page->competency_requirements->rowAttributes() ?>>
        <label id="elh_positions_competency_requirements" for="x_competency_requirements" class="<?= $Page->LeftColumnClass ?>"><?= $Page->competency_requirements->caption() ?><?= $Page->competency_requirements->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->competency_requirements->cellAttributes() ?>>
<span id="el_positions_competency_requirements">
<textarea data-table="positions" data-field="x_competency_requirements" name="x_competency_requirements" id="x_competency_requirements" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->competency_requirements->getPlaceHolder()) ?>"<?= $Page->competency_requirements->editAttributes() ?> aria-describedby="x_competency_requirements_help"><?= $Page->competency_requirements->EditValue ?></textarea>
<?= $Page->competency_requirements->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->competency_requirements->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fpositionsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fpositionsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
<?php } ?>
    </div><!-- /buttons offset -->
<?= $Page->IsModal ? "</template>" : "</div>" ?><!-- /buttons .row -->
</form>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
// Field event handlers
loadjs.ready("head", function() {
    ew.addEventHandlers("positions");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
