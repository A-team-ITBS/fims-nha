<?php

namespace PHPMaker2024\FIMS;

// Page object
$PlantillaItemsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { plantilla_items: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fplantilla_itemsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fplantilla_itemsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["department_id", [fields.department_id.visible && fields.department_id.required ? ew.Validators.required(fields.department_id.caption) : null, ew.Validators.integer], fields.department_id.isInvalid],
            ["position_id", [fields.position_id.visible && fields.position_id.required ? ew.Validators.required(fields.position_id.caption) : null, ew.Validators.integer], fields.position_id.isInvalid],
            ["item_number", [fields.item_number.visible && fields.item_number.required ? ew.Validators.required(fields.item_number.caption) : null], fields.item_number.isInvalid],
            ["item_description", [fields.item_description.visible && fields.item_description.required ? ew.Validators.required(fields.item_description.caption) : null], fields.item_description.isInvalid],
            ["authorized_count", [fields.authorized_count.visible && fields.authorized_count.required ? ew.Validators.required(fields.authorized_count.caption) : null, ew.Validators.integer], fields.authorized_count.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["effective_date", [fields.effective_date.visible && fields.effective_date.required ? ew.Validators.required(fields.effective_date.caption) : null, ew.Validators.datetime(fields.effective_date.clientFormatPattern)], fields.effective_date.isInvalid],
            ["abolition_date", [fields.abolition_date.visible && fields.abolition_date.required ? ew.Validators.required(fields.abolition_date.caption) : null, ew.Validators.datetime(fields.abolition_date.clientFormatPattern)], fields.abolition_date.isInvalid],
            ["annual_budget_allocation", [fields.annual_budget_allocation.visible && fields.annual_budget_allocation.required ? ew.Validators.required(fields.annual_budget_allocation.caption) : null, ew.Validators.float], fields.annual_budget_allocation.isInvalid],
            ["monthly_salary_allocation", [fields.monthly_salary_allocation.visible && fields.monthly_salary_allocation.required ? ew.Validators.required(fields.monthly_salary_allocation.caption) : null, ew.Validators.float], fields.monthly_salary_allocation.isInvalid],
            ["remarks", [fields.remarks.visible && fields.remarks.required ? ew.Validators.required(fields.remarks.caption) : null], fields.remarks.isInvalid],
            ["created_by", [fields.created_by.visible && fields.created_by.required ? ew.Validators.required(fields.created_by.caption) : null, ew.Validators.integer], fields.created_by.isInvalid],
            ["approved_by", [fields.approved_by.visible && fields.approved_by.required ? ew.Validators.required(fields.approved_by.caption) : null, ew.Validators.integer], fields.approved_by.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null, ew.Validators.datetime(fields.created_at.clientFormatPattern)], fields.created_at.isInvalid],
            ["updated_at", [fields.updated_at.visible && fields.updated_at.required ? ew.Validators.required(fields.updated_at.caption) : null, ew.Validators.datetime(fields.updated_at.clientFormatPattern)], fields.updated_at.isInvalid]
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
<form name="fplantilla_itemsadd" id="fplantilla_itemsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="plantilla_items">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->department_id->Visible) { // department_id ?>
    <div id="r_department_id"<?= $Page->department_id->rowAttributes() ?>>
        <label id="elh_plantilla_items_department_id" for="x_department_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_id->caption() ?><?= $Page->department_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_id->cellAttributes() ?>>
<span id="el_plantilla_items_department_id">
<input type="<?= $Page->department_id->getInputTextType() ?>" name="x_department_id" id="x_department_id" data-table="plantilla_items" data-field="x_department_id" value="<?= $Page->department_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->department_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_id->formatPattern()) ?>"<?= $Page->department_id->editAttributes() ?> aria-describedby="x_department_id_help">
<?= $Page->department_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->position_id->Visible) { // position_id ?>
    <div id="r_position_id"<?= $Page->position_id->rowAttributes() ?>>
        <label id="elh_plantilla_items_position_id" for="x_position_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->position_id->caption() ?><?= $Page->position_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->position_id->cellAttributes() ?>>
<span id="el_plantilla_items_position_id">
<input type="<?= $Page->position_id->getInputTextType() ?>" name="x_position_id" id="x_position_id" data-table="plantilla_items" data-field="x_position_id" value="<?= $Page->position_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->position_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->position_id->formatPattern()) ?>"<?= $Page->position_id->editAttributes() ?> aria-describedby="x_position_id_help">
<?= $Page->position_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->position_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->item_number->Visible) { // item_number ?>
    <div id="r_item_number"<?= $Page->item_number->rowAttributes() ?>>
        <label id="elh_plantilla_items_item_number" for="x_item_number" class="<?= $Page->LeftColumnClass ?>"><?= $Page->item_number->caption() ?><?= $Page->item_number->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->item_number->cellAttributes() ?>>
<span id="el_plantilla_items_item_number">
<input type="<?= $Page->item_number->getInputTextType() ?>" name="x_item_number" id="x_item_number" data-table="plantilla_items" data-field="x_item_number" value="<?= $Page->item_number->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->item_number->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->item_number->formatPattern()) ?>"<?= $Page->item_number->editAttributes() ?> aria-describedby="x_item_number_help">
<?= $Page->item_number->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->item_number->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->item_description->Visible) { // item_description ?>
    <div id="r_item_description"<?= $Page->item_description->rowAttributes() ?>>
        <label id="elh_plantilla_items_item_description" for="x_item_description" class="<?= $Page->LeftColumnClass ?>"><?= $Page->item_description->caption() ?><?= $Page->item_description->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->item_description->cellAttributes() ?>>
<span id="el_plantilla_items_item_description">
<input type="<?= $Page->item_description->getInputTextType() ?>" name="x_item_description" id="x_item_description" data-table="plantilla_items" data-field="x_item_description" value="<?= $Page->item_description->EditValue ?>" size="30" maxlength="200" placeholder="<?= HtmlEncode($Page->item_description->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->item_description->formatPattern()) ?>"<?= $Page->item_description->editAttributes() ?> aria-describedby="x_item_description_help">
<?= $Page->item_description->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->item_description->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->authorized_count->Visible) { // authorized_count ?>
    <div id="r_authorized_count"<?= $Page->authorized_count->rowAttributes() ?>>
        <label id="elh_plantilla_items_authorized_count" for="x_authorized_count" class="<?= $Page->LeftColumnClass ?>"><?= $Page->authorized_count->caption() ?><?= $Page->authorized_count->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->authorized_count->cellAttributes() ?>>
<span id="el_plantilla_items_authorized_count">
<input type="<?= $Page->authorized_count->getInputTextType() ?>" name="x_authorized_count" id="x_authorized_count" data-table="plantilla_items" data-field="x_authorized_count" value="<?= $Page->authorized_count->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->authorized_count->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->authorized_count->formatPattern()) ?>"<?= $Page->authorized_count->editAttributes() ?> aria-describedby="x_authorized_count_help">
<?= $Page->authorized_count->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->authorized_count->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_plantilla_items_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_plantilla_items_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="plantilla_items" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->effective_date->Visible) { // effective_date ?>
    <div id="r_effective_date"<?= $Page->effective_date->rowAttributes() ?>>
        <label id="elh_plantilla_items_effective_date" for="x_effective_date" class="<?= $Page->LeftColumnClass ?>"><?= $Page->effective_date->caption() ?><?= $Page->effective_date->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->effective_date->cellAttributes() ?>>
<span id="el_plantilla_items_effective_date">
<input type="<?= $Page->effective_date->getInputTextType() ?>" name="x_effective_date" id="x_effective_date" data-table="plantilla_items" data-field="x_effective_date" value="<?= $Page->effective_date->EditValue ?>" placeholder="<?= HtmlEncode($Page->effective_date->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->effective_date->formatPattern()) ?>"<?= $Page->effective_date->editAttributes() ?> aria-describedby="x_effective_date_help">
<?= $Page->effective_date->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->effective_date->getErrorMessage() ?></div>
<?php if (!$Page->effective_date->ReadOnly && !$Page->effective_date->Disabled && !isset($Page->effective_date->EditAttrs["readonly"]) && !isset($Page->effective_date->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fplantilla_itemsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fplantilla_itemsadd", "x_effective_date", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->abolition_date->Visible) { // abolition_date ?>
    <div id="r_abolition_date"<?= $Page->abolition_date->rowAttributes() ?>>
        <label id="elh_plantilla_items_abolition_date" for="x_abolition_date" class="<?= $Page->LeftColumnClass ?>"><?= $Page->abolition_date->caption() ?><?= $Page->abolition_date->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->abolition_date->cellAttributes() ?>>
<span id="el_plantilla_items_abolition_date">
<input type="<?= $Page->abolition_date->getInputTextType() ?>" name="x_abolition_date" id="x_abolition_date" data-table="plantilla_items" data-field="x_abolition_date" value="<?= $Page->abolition_date->EditValue ?>" placeholder="<?= HtmlEncode($Page->abolition_date->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->abolition_date->formatPattern()) ?>"<?= $Page->abolition_date->editAttributes() ?> aria-describedby="x_abolition_date_help">
<?= $Page->abolition_date->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->abolition_date->getErrorMessage() ?></div>
<?php if (!$Page->abolition_date->ReadOnly && !$Page->abolition_date->Disabled && !isset($Page->abolition_date->EditAttrs["readonly"]) && !isset($Page->abolition_date->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fplantilla_itemsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fplantilla_itemsadd", "x_abolition_date", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->annual_budget_allocation->Visible) { // annual_budget_allocation ?>
    <div id="r_annual_budget_allocation"<?= $Page->annual_budget_allocation->rowAttributes() ?>>
        <label id="elh_plantilla_items_annual_budget_allocation" for="x_annual_budget_allocation" class="<?= $Page->LeftColumnClass ?>"><?= $Page->annual_budget_allocation->caption() ?><?= $Page->annual_budget_allocation->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->annual_budget_allocation->cellAttributes() ?>>
<span id="el_plantilla_items_annual_budget_allocation">
<input type="<?= $Page->annual_budget_allocation->getInputTextType() ?>" name="x_annual_budget_allocation" id="x_annual_budget_allocation" data-table="plantilla_items" data-field="x_annual_budget_allocation" value="<?= $Page->annual_budget_allocation->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->annual_budget_allocation->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->annual_budget_allocation->formatPattern()) ?>"<?= $Page->annual_budget_allocation->editAttributes() ?> aria-describedby="x_annual_budget_allocation_help">
<?= $Page->annual_budget_allocation->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->annual_budget_allocation->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->monthly_salary_allocation->Visible) { // monthly_salary_allocation ?>
    <div id="r_monthly_salary_allocation"<?= $Page->monthly_salary_allocation->rowAttributes() ?>>
        <label id="elh_plantilla_items_monthly_salary_allocation" for="x_monthly_salary_allocation" class="<?= $Page->LeftColumnClass ?>"><?= $Page->monthly_salary_allocation->caption() ?><?= $Page->monthly_salary_allocation->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->monthly_salary_allocation->cellAttributes() ?>>
<span id="el_plantilla_items_monthly_salary_allocation">
<input type="<?= $Page->monthly_salary_allocation->getInputTextType() ?>" name="x_monthly_salary_allocation" id="x_monthly_salary_allocation" data-table="plantilla_items" data-field="x_monthly_salary_allocation" value="<?= $Page->monthly_salary_allocation->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->monthly_salary_allocation->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->monthly_salary_allocation->formatPattern()) ?>"<?= $Page->monthly_salary_allocation->editAttributes() ?> aria-describedby="x_monthly_salary_allocation_help">
<?= $Page->monthly_salary_allocation->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->monthly_salary_allocation->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->remarks->Visible) { // remarks ?>
    <div id="r_remarks"<?= $Page->remarks->rowAttributes() ?>>
        <label id="elh_plantilla_items_remarks" for="x_remarks" class="<?= $Page->LeftColumnClass ?>"><?= $Page->remarks->caption() ?><?= $Page->remarks->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->remarks->cellAttributes() ?>>
<span id="el_plantilla_items_remarks">
<textarea data-table="plantilla_items" data-field="x_remarks" name="x_remarks" id="x_remarks" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->remarks->getPlaceHolder()) ?>"<?= $Page->remarks->editAttributes() ?> aria-describedby="x_remarks_help"><?= $Page->remarks->EditValue ?></textarea>
<?= $Page->remarks->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->remarks->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <div id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <label id="elh_plantilla_items_created_by" for="x_created_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_by->caption() ?><?= $Page->created_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_by->cellAttributes() ?>>
<span id="el_plantilla_items_created_by">
<input type="<?= $Page->created_by->getInputTextType() ?>" name="x_created_by" id="x_created_by" data-table="plantilla_items" data-field="x_created_by" value="<?= $Page->created_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->created_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_by->formatPattern()) ?>"<?= $Page->created_by->editAttributes() ?> aria-describedby="x_created_by_help">
<?= $Page->created_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->approved_by->Visible) { // approved_by ?>
    <div id="r_approved_by"<?= $Page->approved_by->rowAttributes() ?>>
        <label id="elh_plantilla_items_approved_by" for="x_approved_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->approved_by->caption() ?><?= $Page->approved_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->approved_by->cellAttributes() ?>>
<span id="el_plantilla_items_approved_by">
<input type="<?= $Page->approved_by->getInputTextType() ?>" name="x_approved_by" id="x_approved_by" data-table="plantilla_items" data-field="x_approved_by" value="<?= $Page->approved_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->approved_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->approved_by->formatPattern()) ?>"<?= $Page->approved_by->editAttributes() ?> aria-describedby="x_approved_by_help">
<?= $Page->approved_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->approved_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_plantilla_items_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_plantilla_items_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="plantilla_items" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fplantilla_itemsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fplantilla_itemsadd", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <div id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <label id="elh_plantilla_items_updated_at" for="x_updated_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_at->caption() ?><?= $Page->updated_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_plantilla_items_updated_at">
<input type="<?= $Page->updated_at->getInputTextType() ?>" name="x_updated_at" id="x_updated_at" data-table="plantilla_items" data-field="x_updated_at" value="<?= $Page->updated_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->updated_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_at->formatPattern()) ?>"<?= $Page->updated_at->editAttributes() ?> aria-describedby="x_updated_at_help">
<?= $Page->updated_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_at->getErrorMessage() ?></div>
<?php if (!$Page->updated_at->ReadOnly && !$Page->updated_at->Disabled && !isset($Page->updated_at->EditAttrs["readonly"]) && !isset($Page->updated_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fplantilla_itemsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fplantilla_itemsadd", "x_updated_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fplantilla_itemsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fplantilla_itemsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("plantilla_items");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
