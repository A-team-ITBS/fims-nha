<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fdepartmentsedit" id="fdepartmentsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { departments: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fdepartmentsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdepartmentsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["department_id", [fields.department_id.visible && fields.department_id.required ? ew.Validators.required(fields.department_id.caption) : null], fields.department_id.isInvalid],
            ["department_name", [fields.department_name.visible && fields.department_name.required ? ew.Validators.required(fields.department_name.caption) : null], fields.department_name.isInvalid],
            ["description", [fields.description.visible && fields.description.required ? ew.Validators.required(fields.description.caption) : null], fields.description.isInvalid],
            ["parent_department_id", [fields.parent_department_id.visible && fields.parent_department_id.required ? ew.Validators.required(fields.parent_department_id.caption) : null], fields.parent_department_id.isInvalid],
            ["department_head_id", [fields.department_head_id.visible && fields.department_head_id.required ? ew.Validators.required(fields.department_head_id.caption) : null, ew.Validators.integer], fields.department_head_id.isInvalid],
            ["department_code", [fields.department_code.visible && fields.department_code.required ? ew.Validators.required(fields.department_code.caption) : null], fields.department_code.isInvalid],
            ["department_acronym", [fields.department_acronym.visible && fields.department_acronym.required ? ew.Validators.required(fields.department_acronym.caption) : null], fields.department_acronym.isInvalid],
            ["office_location", [fields.office_location.visible && fields.office_location.required ? ew.Validators.required(fields.office_location.caption) : null], fields.office_location.isInvalid],
            ["contact_number", [fields.contact_number.visible && fields.contact_number.required ? ew.Validators.required(fields.contact_number.caption) : null], fields.contact_number.isInvalid],
            ["_email", [fields._email.visible && fields._email.required ? ew.Validators.required(fields._email.caption) : null], fields._email.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null, ew.Validators.datetime(fields.created_at.clientFormatPattern)], fields.created_at.isInvalid],
            ["updated_at", [fields.updated_at.visible && fields.updated_at.required ? ew.Validators.required(fields.updated_at.caption) : null, ew.Validators.datetime(fields.updated_at.clientFormatPattern)], fields.updated_at.isInvalid],
            ["rc_id", [fields.rc_id.visible && fields.rc_id.required ? ew.Validators.required(fields.rc_id.caption) : null, ew.Validators.integer], fields.rc_id.isInvalid],
            ["office_id", [fields.office_id.visible && fields.office_id.required ? ew.Validators.required(fields.office_id.caption) : null, ew.Validators.integer], fields.office_id.isInvalid],
            ["department_type", [fields.department_type.visible && fields.department_type.required ? ew.Validators.required(fields.department_type.caption) : null], fields.department_type.isInvalid],
            ["hierarchy_level", [fields.hierarchy_level.visible && fields.hierarchy_level.required ? ew.Validators.required(fields.hierarchy_level.caption) : null, ew.Validators.integer], fields.hierarchy_level.isInvalid],
            ["is_budget_unit", [fields.is_budget_unit.visible && fields.is_budget_unit.required ? ew.Validators.required(fields.is_budget_unit.caption) : null], fields.is_budget_unit.isInvalid]
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
            "parent_department_id": <?= $Page->parent_department_id->toClientList($Page) ?>,
            "is_active": <?= $Page->is_active->toClientList($Page) ?>,
            "is_budget_unit": <?= $Page->is_budget_unit->toClientList($Page) ?>,
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
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="departments">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->department_id->Visible) { // department_id ?>
    <div id="r_department_id"<?= $Page->department_id->rowAttributes() ?>>
        <label id="elh_departments_department_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_id->caption() ?><?= $Page->department_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_id->cellAttributes() ?>>
<span id="el_departments_department_id">
<span<?= $Page->department_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->department_id->getDisplayValue($Page->department_id->EditValue))) ?>"></span>
<input type="hidden" data-table="departments" data-field="x_department_id" data-hidden="1" name="x_department_id" id="x_department_id" value="<?= HtmlEncode($Page->department_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->department_name->Visible) { // department_name ?>
    <div id="r_department_name"<?= $Page->department_name->rowAttributes() ?>>
        <label id="elh_departments_department_name" for="x_department_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_name->caption() ?><?= $Page->department_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_name->cellAttributes() ?>>
<span id="el_departments_department_name">
<input type="<?= $Page->department_name->getInputTextType() ?>" name="x_department_name" id="x_department_name" data-table="departments" data-field="x_department_name" value="<?= $Page->department_name->EditValue ?>" size="30" maxlength="150" placeholder="<?= HtmlEncode($Page->department_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_name->formatPattern()) ?>"<?= $Page->department_name->editAttributes() ?> aria-describedby="x_department_name_help">
<?= $Page->department_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->description->Visible) { // description ?>
    <div id="r_description"<?= $Page->description->rowAttributes() ?>>
        <label id="elh_departments_description" for="x_description" class="<?= $Page->LeftColumnClass ?>"><?= $Page->description->caption() ?><?= $Page->description->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->description->cellAttributes() ?>>
<span id="el_departments_description">
<textarea data-table="departments" data-field="x_description" name="x_description" id="x_description" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->description->getPlaceHolder()) ?>"<?= $Page->description->editAttributes() ?> aria-describedby="x_description_help"><?= $Page->description->EditValue ?></textarea>
<?= $Page->description->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->description->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->parent_department_id->Visible) { // parent_department_id ?>
    <div id="r_parent_department_id"<?= $Page->parent_department_id->rowAttributes() ?>>
        <label id="elh_departments_parent_department_id" for="x_parent_department_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->parent_department_id->caption() ?><?= $Page->parent_department_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->parent_department_id->cellAttributes() ?>>
<span id="el_departments_parent_department_id">
    <select
        id="x_parent_department_id"
        name="x_parent_department_id"
        class="form-select ew-select<?= $Page->parent_department_id->isInvalidClass() ?>"
        <?php if (!$Page->parent_department_id->IsNativeSelect) { ?>
        data-select2-id="fdepartmentsedit_x_parent_department_id"
        <?php } ?>
        data-table="departments"
        data-field="x_parent_department_id"
        data-value-separator="<?= $Page->parent_department_id->displayValueSeparatorAttribute() ?>"
        data-placeholder="<?= HtmlEncode($Page->parent_department_id->getPlaceHolder()) ?>"
        <?= $Page->parent_department_id->editAttributes() ?>>
        <?= $Page->parent_department_id->selectOptionListHtml("x_parent_department_id") ?>
    </select>
    <?= $Page->parent_department_id->getCustomMessage() ?>
    <div class="invalid-feedback"><?= $Page->parent_department_id->getErrorMessage() ?></div>
<?= $Page->parent_department_id->Lookup->getParamTag($Page, "p_x_parent_department_id") ?>
<?php if (!$Page->parent_department_id->IsNativeSelect) { ?>
<script>
loadjs.ready("fdepartmentsedit", function() {
    var options = { name: "x_parent_department_id", selectId: "fdepartmentsedit_x_parent_department_id" },
        el = document.querySelector("select[data-select2-id='" + options.selectId + "']");
    if (!el)
        return;
    options.closeOnSelect = !options.multiple;
    options.dropdownParent = el.closest("#ew-modal-dialog, #ew-add-opt-dialog");
    if (fdepartmentsedit.lists.parent_department_id?.lookupOptions.length) {
        options.data = { id: "x_parent_department_id", form: "fdepartmentsedit" };
    } else {
        options.ajax = { id: "x_parent_department_id", form: "fdepartmentsedit", limit: ew.LOOKUP_PAGE_SIZE };
    }
    options.minimumResultsForSearch = Infinity;
    options = Object.assign({}, ew.selectOptions, options, ew.vars.tables.departments.fields.parent_department_id.selectOptions);
    ew.createSelect(options);
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->department_head_id->Visible) { // department_head_id ?>
    <div id="r_department_head_id"<?= $Page->department_head_id->rowAttributes() ?>>
        <label id="elh_departments_department_head_id" for="x_department_head_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_head_id->caption() ?><?= $Page->department_head_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_head_id->cellAttributes() ?>>
<span id="el_departments_department_head_id">
<input type="<?= $Page->department_head_id->getInputTextType() ?>" name="x_department_head_id" id="x_department_head_id" data-table="departments" data-field="x_department_head_id" value="<?= $Page->department_head_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->department_head_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_head_id->formatPattern()) ?>"<?= $Page->department_head_id->editAttributes() ?> aria-describedby="x_department_head_id_help">
<?= $Page->department_head_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_head_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->department_code->Visible) { // department_code ?>
    <div id="r_department_code"<?= $Page->department_code->rowAttributes() ?>>
        <label id="elh_departments_department_code" for="x_department_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_code->caption() ?><?= $Page->department_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_code->cellAttributes() ?>>
<span id="el_departments_department_code">
<input type="<?= $Page->department_code->getInputTextType() ?>" name="x_department_code" id="x_department_code" data-table="departments" data-field="x_department_code" value="<?= $Page->department_code->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->department_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_code->formatPattern()) ?>"<?= $Page->department_code->editAttributes() ?> aria-describedby="x_department_code_help">
<?= $Page->department_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->department_acronym->Visible) { // department_acronym ?>
    <div id="r_department_acronym"<?= $Page->department_acronym->rowAttributes() ?>>
        <label id="elh_departments_department_acronym" for="x_department_acronym" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_acronym->caption() ?><?= $Page->department_acronym->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_acronym->cellAttributes() ?>>
<span id="el_departments_department_acronym">
<input type="<?= $Page->department_acronym->getInputTextType() ?>" name="x_department_acronym" id="x_department_acronym" data-table="departments" data-field="x_department_acronym" value="<?= $Page->department_acronym->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->department_acronym->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_acronym->formatPattern()) ?>"<?= $Page->department_acronym->editAttributes() ?> aria-describedby="x_department_acronym_help">
<?= $Page->department_acronym->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_acronym->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_location->Visible) { // office_location ?>
    <div id="r_office_location"<?= $Page->office_location->rowAttributes() ?>>
        <label id="elh_departments_office_location" for="x_office_location" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_location->caption() ?><?= $Page->office_location->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_location->cellAttributes() ?>>
<span id="el_departments_office_location">
<input type="<?= $Page->office_location->getInputTextType() ?>" name="x_office_location" id="x_office_location" data-table="departments" data-field="x_office_location" value="<?= $Page->office_location->EditValue ?>" size="30" maxlength="200" placeholder="<?= HtmlEncode($Page->office_location->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_location->formatPattern()) ?>"<?= $Page->office_location->editAttributes() ?> aria-describedby="x_office_location_help">
<?= $Page->office_location->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_location->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->contact_number->Visible) { // contact_number ?>
    <div id="r_contact_number"<?= $Page->contact_number->rowAttributes() ?>>
        <label id="elh_departments_contact_number" for="x_contact_number" class="<?= $Page->LeftColumnClass ?>"><?= $Page->contact_number->caption() ?><?= $Page->contact_number->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->contact_number->cellAttributes() ?>>
<span id="el_departments_contact_number">
<input type="<?= $Page->contact_number->getInputTextType() ?>" name="x_contact_number" id="x_contact_number" data-table="departments" data-field="x_contact_number" value="<?= $Page->contact_number->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->contact_number->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->contact_number->formatPattern()) ?>"<?= $Page->contact_number->editAttributes() ?> aria-describedby="x_contact_number_help">
<?= $Page->contact_number->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->contact_number->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->_email->Visible) { // email ?>
    <div id="r__email"<?= $Page->_email->rowAttributes() ?>>
        <label id="elh_departments__email" for="x__email" class="<?= $Page->LeftColumnClass ?>"><?= $Page->_email->caption() ?><?= $Page->_email->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->_email->cellAttributes() ?>>
<span id="el_departments__email">
<input type="<?= $Page->_email->getInputTextType() ?>" name="x__email" id="x__email" data-table="departments" data-field="x__email" value="<?= $Page->_email->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->_email->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->_email->formatPattern()) ?>"<?= $Page->_email->editAttributes() ?> aria-describedby="x__email_help">
<?= $Page->_email->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->_email->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_departments_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_departments_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="departments" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_departments_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_departments_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="departments" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fdepartmentsedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fdepartmentsedit", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <div id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <label id="elh_departments_updated_at" for="x_updated_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_at->caption() ?><?= $Page->updated_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_departments_updated_at">
<input type="<?= $Page->updated_at->getInputTextType() ?>" name="x_updated_at" id="x_updated_at" data-table="departments" data-field="x_updated_at" value="<?= $Page->updated_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->updated_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_at->formatPattern()) ?>"<?= $Page->updated_at->editAttributes() ?> aria-describedby="x_updated_at_help">
<?= $Page->updated_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_at->getErrorMessage() ?></div>
<?php if (!$Page->updated_at->ReadOnly && !$Page->updated_at->Disabled && !isset($Page->updated_at->EditAttrs["readonly"]) && !isset($Page->updated_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fdepartmentsedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fdepartmentsedit", "x_updated_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
    <div id="r_rc_id"<?= $Page->rc_id->rowAttributes() ?>>
        <label id="elh_departments_rc_id" for="x_rc_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->rc_id->caption() ?><?= $Page->rc_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->rc_id->cellAttributes() ?>>
<span id="el_departments_rc_id">
<input type="<?= $Page->rc_id->getInputTextType() ?>" name="x_rc_id" id="x_rc_id" data-table="departments" data-field="x_rc_id" value="<?= $Page->rc_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->rc_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->rc_id->formatPattern()) ?>"<?= $Page->rc_id->editAttributes() ?> aria-describedby="x_rc_id_help">
<?= $Page->rc_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->rc_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
    <div id="r_office_id"<?= $Page->office_id->rowAttributes() ?>>
        <label id="elh_departments_office_id" for="x_office_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_id->caption() ?><?= $Page->office_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_id->cellAttributes() ?>>
<span id="el_departments_office_id">
<input type="<?= $Page->office_id->getInputTextType() ?>" name="x_office_id" id="x_office_id" data-table="departments" data-field="x_office_id" value="<?= $Page->office_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->office_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_id->formatPattern()) ?>"<?= $Page->office_id->editAttributes() ?> aria-describedby="x_office_id_help">
<?= $Page->office_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->department_type->Visible) { // department_type ?>
    <div id="r_department_type"<?= $Page->department_type->rowAttributes() ?>>
        <label id="elh_departments_department_type" for="x_department_type" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_type->caption() ?><?= $Page->department_type->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_type->cellAttributes() ?>>
<span id="el_departments_department_type">
<input type="<?= $Page->department_type->getInputTextType() ?>" name="x_department_type" id="x_department_type" data-table="departments" data-field="x_department_type" value="<?= $Page->department_type->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->department_type->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_type->formatPattern()) ?>"<?= $Page->department_type->editAttributes() ?> aria-describedby="x_department_type_help">
<?= $Page->department_type->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_type->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->hierarchy_level->Visible) { // hierarchy_level ?>
    <div id="r_hierarchy_level"<?= $Page->hierarchy_level->rowAttributes() ?>>
        <label id="elh_departments_hierarchy_level" for="x_hierarchy_level" class="<?= $Page->LeftColumnClass ?>"><?= $Page->hierarchy_level->caption() ?><?= $Page->hierarchy_level->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->hierarchy_level->cellAttributes() ?>>
<span id="el_departments_hierarchy_level">
<input type="<?= $Page->hierarchy_level->getInputTextType() ?>" name="x_hierarchy_level" id="x_hierarchy_level" data-table="departments" data-field="x_hierarchy_level" value="<?= $Page->hierarchy_level->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->hierarchy_level->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->hierarchy_level->formatPattern()) ?>"<?= $Page->hierarchy_level->editAttributes() ?> aria-describedby="x_hierarchy_level_help">
<?= $Page->hierarchy_level->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->hierarchy_level->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_budget_unit->Visible) { // is_budget_unit ?>
    <div id="r_is_budget_unit"<?= $Page->is_budget_unit->rowAttributes() ?>>
        <label id="elh_departments_is_budget_unit" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_budget_unit->caption() ?><?= $Page->is_budget_unit->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_budget_unit->cellAttributes() ?>>
<span id="el_departments_is_budget_unit">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_budget_unit->isInvalidClass() ?>" data-table="departments" data-field="x_is_budget_unit" data-boolean name="x_is_budget_unit" id="x_is_budget_unit" value="1"<?= ConvertToBool($Page->is_budget_unit->CurrentValue) ? " checked" : "" ?><?= $Page->is_budget_unit->editAttributes() ?> aria-describedby="x_is_budget_unit_help">
    <div class="invalid-feedback"><?= $Page->is_budget_unit->getErrorMessage() ?></div>
</div>
<?= $Page->is_budget_unit->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fdepartmentsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fdepartmentsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
<?php } ?>
    </div><!-- /buttons offset -->
<?= $Page->IsModal ? "</template>" : "</div>" ?><!-- /buttons .row -->
</form>
</main>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
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
