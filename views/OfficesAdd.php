<?php

namespace PHPMaker2024\FIMS;

// Page object
$OfficesAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { offices: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fofficesadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fofficesadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["office_name", [fields.office_name.visible && fields.office_name.required ? ew.Validators.required(fields.office_name.caption) : null], fields.office_name.isInvalid],
            ["office_code", [fields.office_code.visible && fields.office_code.required ? ew.Validators.required(fields.office_code.caption) : null], fields.office_code.isInvalid],
            ["office_type", [fields.office_type.visible && fields.office_type.required ? ew.Validators.required(fields.office_type.caption) : null], fields.office_type.isInvalid],
            ["location", [fields.location.visible && fields.location.required ? ew.Validators.required(fields.location.caption) : null], fields.location.isInvalid],
            ["address", [fields.address.visible && fields.address.required ? ew.Validators.required(fields.address.caption) : null], fields.address.isInvalid],
            ["region", [fields.region.visible && fields.region.required ? ew.Validators.required(fields.region.caption) : null], fields.region.isInvalid],
            ["province", [fields.province.visible && fields.province.required ? ew.Validators.required(fields.province.caption) : null], fields.province.isInvalid],
            ["city_municipality", [fields.city_municipality.visible && fields.city_municipality.required ? ew.Validators.required(fields.city_municipality.caption) : null], fields.city_municipality.isInvalid],
            ["office_contact_number", [fields.office_contact_number.visible && fields.office_contact_number.required ? ew.Validators.required(fields.office_contact_number.caption) : null], fields.office_contact_number.isInvalid],
            ["office_email", [fields.office_email.visible && fields.office_email.required ? ew.Validators.required(fields.office_email.caption) : null], fields.office_email.isInvalid],
            ["office_telephone", [fields.office_telephone.visible && fields.office_telephone.required ? ew.Validators.required(fields.office_telephone.caption) : null], fields.office_telephone.isInvalid],
            ["office_fax", [fields.office_fax.visible && fields.office_fax.required ? ew.Validators.required(fields.office_fax.caption) : null], fields.office_fax.isInvalid],
            ["parent_office_id", [fields.parent_office_id.visible && fields.parent_office_id.required ? ew.Validators.required(fields.parent_office_id.caption) : null, ew.Validators.integer], fields.parent_office_id.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["date_opened", [fields.date_opened.visible && fields.date_opened.required ? ew.Validators.required(fields.date_opened.caption) : null, ew.Validators.datetime(fields.date_opened.clientFormatPattern)], fields.date_opened.isInvalid],
            ["date_closed", [fields.date_closed.visible && fields.date_closed.required ? ew.Validators.required(fields.date_closed.caption) : null, ew.Validators.datetime(fields.date_closed.clientFormatPattern)], fields.date_closed.isInvalid],
            ["created_by", [fields.created_by.visible && fields.created_by.required ? ew.Validators.required(fields.created_by.caption) : null, ew.Validators.integer], fields.created_by.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null, ew.Validators.datetime(fields.created_at.clientFormatPattern)], fields.created_at.isInvalid],
            ["modified_by", [fields.modified_by.visible && fields.modified_by.required ? ew.Validators.required(fields.modified_by.caption) : null, ew.Validators.integer], fields.modified_by.isInvalid],
            ["modified_at", [fields.modified_at.visible && fields.modified_at.required ? ew.Validators.required(fields.modified_at.caption) : null, ew.Validators.datetime(fields.modified_at.clientFormatPattern)], fields.modified_at.isInvalid],
            ["remarks", [fields.remarks.visible && fields.remarks.required ? ew.Validators.required(fields.remarks.caption) : null], fields.remarks.isInvalid],
            ["metadata", [fields.metadata.visible && fields.metadata.required ? ew.Validators.required(fields.metadata.caption) : null], fields.metadata.isInvalid],
            ["rc_id", [fields.rc_id.visible && fields.rc_id.required ? ew.Validators.required(fields.rc_id.caption) : null, ew.Validators.integer], fields.rc_id.isInvalid],
            ["region_code", [fields.region_code.visible && fields.region_code.required ? ew.Validators.required(fields.region_code.caption) : null], fields.region_code.isInvalid]
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
<form name="fofficesadd" id="fofficesadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="offices">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->office_name->Visible) { // office_name ?>
    <div id="r_office_name"<?= $Page->office_name->rowAttributes() ?>>
        <label id="elh_offices_office_name" for="x_office_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_name->caption() ?><?= $Page->office_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_name->cellAttributes() ?>>
<span id="el_offices_office_name">
<input type="<?= $Page->office_name->getInputTextType() ?>" name="x_office_name" id="x_office_name" data-table="offices" data-field="x_office_name" value="<?= $Page->office_name->EditValue ?>" size="30" maxlength="150" placeholder="<?= HtmlEncode($Page->office_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_name->formatPattern()) ?>"<?= $Page->office_name->editAttributes() ?> aria-describedby="x_office_name_help">
<?= $Page->office_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_code->Visible) { // office_code ?>
    <div id="r_office_code"<?= $Page->office_code->rowAttributes() ?>>
        <label id="elh_offices_office_code" for="x_office_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_code->caption() ?><?= $Page->office_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_code->cellAttributes() ?>>
<span id="el_offices_office_code">
<input type="<?= $Page->office_code->getInputTextType() ?>" name="x_office_code" id="x_office_code" data-table="offices" data-field="x_office_code" value="<?= $Page->office_code->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->office_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_code->formatPattern()) ?>"<?= $Page->office_code->editAttributes() ?> aria-describedby="x_office_code_help">
<?= $Page->office_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_type->Visible) { // office_type ?>
    <div id="r_office_type"<?= $Page->office_type->rowAttributes() ?>>
        <label id="elh_offices_office_type" for="x_office_type" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_type->caption() ?><?= $Page->office_type->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_type->cellAttributes() ?>>
<span id="el_offices_office_type">
<input type="<?= $Page->office_type->getInputTextType() ?>" name="x_office_type" id="x_office_type" data-table="offices" data-field="x_office_type" value="<?= $Page->office_type->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->office_type->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_type->formatPattern()) ?>"<?= $Page->office_type->editAttributes() ?> aria-describedby="x_office_type_help">
<?= $Page->office_type->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_type->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->location->Visible) { // location ?>
    <div id="r_location"<?= $Page->location->rowAttributes() ?>>
        <label id="elh_offices_location" for="x_location" class="<?= $Page->LeftColumnClass ?>"><?= $Page->location->caption() ?><?= $Page->location->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->location->cellAttributes() ?>>
<span id="el_offices_location">
<textarea data-table="offices" data-field="x_location" name="x_location" id="x_location" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->location->getPlaceHolder()) ?>"<?= $Page->location->editAttributes() ?> aria-describedby="x_location_help"><?= $Page->location->EditValue ?></textarea>
<?= $Page->location->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->location->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->address->Visible) { // address ?>
    <div id="r_address"<?= $Page->address->rowAttributes() ?>>
        <label id="elh_offices_address" for="x_address" class="<?= $Page->LeftColumnClass ?>"><?= $Page->address->caption() ?><?= $Page->address->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->address->cellAttributes() ?>>
<span id="el_offices_address">
<textarea data-table="offices" data-field="x_address" name="x_address" id="x_address" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->address->getPlaceHolder()) ?>"<?= $Page->address->editAttributes() ?> aria-describedby="x_address_help"><?= $Page->address->EditValue ?></textarea>
<?= $Page->address->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->address->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->region->Visible) { // region ?>
    <div id="r_region"<?= $Page->region->rowAttributes() ?>>
        <label id="elh_offices_region" for="x_region" class="<?= $Page->LeftColumnClass ?>"><?= $Page->region->caption() ?><?= $Page->region->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->region->cellAttributes() ?>>
<span id="el_offices_region">
<input type="<?= $Page->region->getInputTextType() ?>" name="x_region" id="x_region" data-table="offices" data-field="x_region" value="<?= $Page->region->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->region->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->region->formatPattern()) ?>"<?= $Page->region->editAttributes() ?> aria-describedby="x_region_help">
<?= $Page->region->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->region->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->province->Visible) { // province ?>
    <div id="r_province"<?= $Page->province->rowAttributes() ?>>
        <label id="elh_offices_province" for="x_province" class="<?= $Page->LeftColumnClass ?>"><?= $Page->province->caption() ?><?= $Page->province->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->province->cellAttributes() ?>>
<span id="el_offices_province">
<input type="<?= $Page->province->getInputTextType() ?>" name="x_province" id="x_province" data-table="offices" data-field="x_province" value="<?= $Page->province->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->province->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->province->formatPattern()) ?>"<?= $Page->province->editAttributes() ?> aria-describedby="x_province_help">
<?= $Page->province->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->province->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->city_municipality->Visible) { // city_municipality ?>
    <div id="r_city_municipality"<?= $Page->city_municipality->rowAttributes() ?>>
        <label id="elh_offices_city_municipality" for="x_city_municipality" class="<?= $Page->LeftColumnClass ?>"><?= $Page->city_municipality->caption() ?><?= $Page->city_municipality->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->city_municipality->cellAttributes() ?>>
<span id="el_offices_city_municipality">
<input type="<?= $Page->city_municipality->getInputTextType() ?>" name="x_city_municipality" id="x_city_municipality" data-table="offices" data-field="x_city_municipality" value="<?= $Page->city_municipality->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->city_municipality->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->city_municipality->formatPattern()) ?>"<?= $Page->city_municipality->editAttributes() ?> aria-describedby="x_city_municipality_help">
<?= $Page->city_municipality->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->city_municipality->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_contact_number->Visible) { // office_contact_number ?>
    <div id="r_office_contact_number"<?= $Page->office_contact_number->rowAttributes() ?>>
        <label id="elh_offices_office_contact_number" for="x_office_contact_number" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_contact_number->caption() ?><?= $Page->office_contact_number->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_contact_number->cellAttributes() ?>>
<span id="el_offices_office_contact_number">
<input type="<?= $Page->office_contact_number->getInputTextType() ?>" name="x_office_contact_number" id="x_office_contact_number" data-table="offices" data-field="x_office_contact_number" value="<?= $Page->office_contact_number->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->office_contact_number->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_contact_number->formatPattern()) ?>"<?= $Page->office_contact_number->editAttributes() ?> aria-describedby="x_office_contact_number_help">
<?= $Page->office_contact_number->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_contact_number->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_email->Visible) { // office_email ?>
    <div id="r_office_email"<?= $Page->office_email->rowAttributes() ?>>
        <label id="elh_offices_office_email" for="x_office_email" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_email->caption() ?><?= $Page->office_email->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_email->cellAttributes() ?>>
<span id="el_offices_office_email">
<input type="<?= $Page->office_email->getInputTextType() ?>" name="x_office_email" id="x_office_email" data-table="offices" data-field="x_office_email" value="<?= $Page->office_email->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->office_email->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_email->formatPattern()) ?>"<?= $Page->office_email->editAttributes() ?> aria-describedby="x_office_email_help">
<?= $Page->office_email->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_email->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_telephone->Visible) { // office_telephone ?>
    <div id="r_office_telephone"<?= $Page->office_telephone->rowAttributes() ?>>
        <label id="elh_offices_office_telephone" for="x_office_telephone" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_telephone->caption() ?><?= $Page->office_telephone->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_telephone->cellAttributes() ?>>
<span id="el_offices_office_telephone">
<input type="<?= $Page->office_telephone->getInputTextType() ?>" name="x_office_telephone" id="x_office_telephone" data-table="offices" data-field="x_office_telephone" value="<?= $Page->office_telephone->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->office_telephone->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_telephone->formatPattern()) ?>"<?= $Page->office_telephone->editAttributes() ?> aria-describedby="x_office_telephone_help">
<?= $Page->office_telephone->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_telephone->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_fax->Visible) { // office_fax ?>
    <div id="r_office_fax"<?= $Page->office_fax->rowAttributes() ?>>
        <label id="elh_offices_office_fax" for="x_office_fax" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_fax->caption() ?><?= $Page->office_fax->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_fax->cellAttributes() ?>>
<span id="el_offices_office_fax">
<input type="<?= $Page->office_fax->getInputTextType() ?>" name="x_office_fax" id="x_office_fax" data-table="offices" data-field="x_office_fax" value="<?= $Page->office_fax->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->office_fax->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_fax->formatPattern()) ?>"<?= $Page->office_fax->editAttributes() ?> aria-describedby="x_office_fax_help">
<?= $Page->office_fax->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_fax->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->parent_office_id->Visible) { // parent_office_id ?>
    <div id="r_parent_office_id"<?= $Page->parent_office_id->rowAttributes() ?>>
        <label id="elh_offices_parent_office_id" for="x_parent_office_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->parent_office_id->caption() ?><?= $Page->parent_office_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->parent_office_id->cellAttributes() ?>>
<span id="el_offices_parent_office_id">
<input type="<?= $Page->parent_office_id->getInputTextType() ?>" name="x_parent_office_id" id="x_parent_office_id" data-table="offices" data-field="x_parent_office_id" value="<?= $Page->parent_office_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->parent_office_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->parent_office_id->formatPattern()) ?>"<?= $Page->parent_office_id->editAttributes() ?> aria-describedby="x_parent_office_id_help">
<?= $Page->parent_office_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->parent_office_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_offices_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_offices_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="offices" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->date_opened->Visible) { // date_opened ?>
    <div id="r_date_opened"<?= $Page->date_opened->rowAttributes() ?>>
        <label id="elh_offices_date_opened" for="x_date_opened" class="<?= $Page->LeftColumnClass ?>"><?= $Page->date_opened->caption() ?><?= $Page->date_opened->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->date_opened->cellAttributes() ?>>
<span id="el_offices_date_opened">
<input type="<?= $Page->date_opened->getInputTextType() ?>" name="x_date_opened" id="x_date_opened" data-table="offices" data-field="x_date_opened" value="<?= $Page->date_opened->EditValue ?>" placeholder="<?= HtmlEncode($Page->date_opened->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->date_opened->formatPattern()) ?>"<?= $Page->date_opened->editAttributes() ?> aria-describedby="x_date_opened_help">
<?= $Page->date_opened->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->date_opened->getErrorMessage() ?></div>
<?php if (!$Page->date_opened->ReadOnly && !$Page->date_opened->Disabled && !isset($Page->date_opened->EditAttrs["readonly"]) && !isset($Page->date_opened->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fofficesadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fofficesadd", "x_date_opened", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->date_closed->Visible) { // date_closed ?>
    <div id="r_date_closed"<?= $Page->date_closed->rowAttributes() ?>>
        <label id="elh_offices_date_closed" for="x_date_closed" class="<?= $Page->LeftColumnClass ?>"><?= $Page->date_closed->caption() ?><?= $Page->date_closed->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->date_closed->cellAttributes() ?>>
<span id="el_offices_date_closed">
<input type="<?= $Page->date_closed->getInputTextType() ?>" name="x_date_closed" id="x_date_closed" data-table="offices" data-field="x_date_closed" value="<?= $Page->date_closed->EditValue ?>" placeholder="<?= HtmlEncode($Page->date_closed->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->date_closed->formatPattern()) ?>"<?= $Page->date_closed->editAttributes() ?> aria-describedby="x_date_closed_help">
<?= $Page->date_closed->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->date_closed->getErrorMessage() ?></div>
<?php if (!$Page->date_closed->ReadOnly && !$Page->date_closed->Disabled && !isset($Page->date_closed->EditAttrs["readonly"]) && !isset($Page->date_closed->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fofficesadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fofficesadd", "x_date_closed", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <div id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <label id="elh_offices_created_by" for="x_created_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_by->caption() ?><?= $Page->created_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_by->cellAttributes() ?>>
<span id="el_offices_created_by">
<input type="<?= $Page->created_by->getInputTextType() ?>" name="x_created_by" id="x_created_by" data-table="offices" data-field="x_created_by" value="<?= $Page->created_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->created_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_by->formatPattern()) ?>"<?= $Page->created_by->editAttributes() ?> aria-describedby="x_created_by_help">
<?= $Page->created_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_offices_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_offices_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="offices" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fofficesadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fofficesadd", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->modified_by->Visible) { // modified_by ?>
    <div id="r_modified_by"<?= $Page->modified_by->rowAttributes() ?>>
        <label id="elh_offices_modified_by" for="x_modified_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->modified_by->caption() ?><?= $Page->modified_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->modified_by->cellAttributes() ?>>
<span id="el_offices_modified_by">
<input type="<?= $Page->modified_by->getInputTextType() ?>" name="x_modified_by" id="x_modified_by" data-table="offices" data-field="x_modified_by" value="<?= $Page->modified_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->modified_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->modified_by->formatPattern()) ?>"<?= $Page->modified_by->editAttributes() ?> aria-describedby="x_modified_by_help">
<?= $Page->modified_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->modified_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->modified_at->Visible) { // modified_at ?>
    <div id="r_modified_at"<?= $Page->modified_at->rowAttributes() ?>>
        <label id="elh_offices_modified_at" for="x_modified_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->modified_at->caption() ?><?= $Page->modified_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->modified_at->cellAttributes() ?>>
<span id="el_offices_modified_at">
<input type="<?= $Page->modified_at->getInputTextType() ?>" name="x_modified_at" id="x_modified_at" data-table="offices" data-field="x_modified_at" value="<?= $Page->modified_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->modified_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->modified_at->formatPattern()) ?>"<?= $Page->modified_at->editAttributes() ?> aria-describedby="x_modified_at_help">
<?= $Page->modified_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->modified_at->getErrorMessage() ?></div>
<?php if (!$Page->modified_at->ReadOnly && !$Page->modified_at->Disabled && !isset($Page->modified_at->EditAttrs["readonly"]) && !isset($Page->modified_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fofficesadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fofficesadd", "x_modified_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->remarks->Visible) { // remarks ?>
    <div id="r_remarks"<?= $Page->remarks->rowAttributes() ?>>
        <label id="elh_offices_remarks" for="x_remarks" class="<?= $Page->LeftColumnClass ?>"><?= $Page->remarks->caption() ?><?= $Page->remarks->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->remarks->cellAttributes() ?>>
<span id="el_offices_remarks">
<textarea data-table="offices" data-field="x_remarks" name="x_remarks" id="x_remarks" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->remarks->getPlaceHolder()) ?>"<?= $Page->remarks->editAttributes() ?> aria-describedby="x_remarks_help"><?= $Page->remarks->EditValue ?></textarea>
<?= $Page->remarks->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->remarks->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->metadata->Visible) { // metadata ?>
    <div id="r_metadata"<?= $Page->metadata->rowAttributes() ?>>
        <label id="elh_offices_metadata" for="x_metadata" class="<?= $Page->LeftColumnClass ?>"><?= $Page->metadata->caption() ?><?= $Page->metadata->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->metadata->cellAttributes() ?>>
<span id="el_offices_metadata">
<textarea data-table="offices" data-field="x_metadata" name="x_metadata" id="x_metadata" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->metadata->getPlaceHolder()) ?>"<?= $Page->metadata->editAttributes() ?> aria-describedby="x_metadata_help"><?= $Page->metadata->EditValue ?></textarea>
<?= $Page->metadata->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->metadata->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
    <div id="r_rc_id"<?= $Page->rc_id->rowAttributes() ?>>
        <label id="elh_offices_rc_id" for="x_rc_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->rc_id->caption() ?><?= $Page->rc_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->rc_id->cellAttributes() ?>>
<span id="el_offices_rc_id">
<input type="<?= $Page->rc_id->getInputTextType() ?>" name="x_rc_id" id="x_rc_id" data-table="offices" data-field="x_rc_id" value="<?= $Page->rc_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->rc_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->rc_id->formatPattern()) ?>"<?= $Page->rc_id->editAttributes() ?> aria-describedby="x_rc_id_help">
<?= $Page->rc_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->rc_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->region_code->Visible) { // region_code ?>
    <div id="r_region_code"<?= $Page->region_code->rowAttributes() ?>>
        <label id="elh_offices_region_code" for="x_region_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->region_code->caption() ?><?= $Page->region_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->region_code->cellAttributes() ?>>
<span id="el_offices_region_code">
<input type="<?= $Page->region_code->getInputTextType() ?>" name="x_region_code" id="x_region_code" data-table="offices" data-field="x_region_code" value="<?= $Page->region_code->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->region_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->region_code->formatPattern()) ?>"<?= $Page->region_code->editAttributes() ?> aria-describedby="x_region_code_help">
<?= $Page->region_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->region_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fofficesadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fofficesadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("offices");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
