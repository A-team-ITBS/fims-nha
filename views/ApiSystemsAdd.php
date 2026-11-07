<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiSystemsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_systems: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fapi_systemsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_systemsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["system_code", [fields.system_code.visible && fields.system_code.required ? ew.Validators.required(fields.system_code.caption) : null], fields.system_code.isInvalid],
            ["system_name", [fields.system_name.visible && fields.system_name.required ? ew.Validators.required(fields.system_name.caption) : null], fields.system_name.isInvalid],
            ["base_url", [fields.base_url.visible && fields.base_url.required ? ew.Validators.required(fields.base_url.caption) : null], fields.base_url.isInvalid],
            ["auth_type", [fields.auth_type.visible && fields.auth_type.required ? ew.Validators.required(fields.auth_type.caption) : null], fields.auth_type.isInvalid],
            ["auth_token", [fields.auth_token.visible && fields.auth_token.required ? ew.Validators.required(fields.auth_token.caption) : null], fields.auth_token.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
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
<form name="fapi_systemsadd" id="fapi_systemsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="api_systems">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->system_code->Visible) { // system_code ?>
    <div id="r_system_code"<?= $Page->system_code->rowAttributes() ?>>
        <label id="elh_api_systems_system_code" for="x_system_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system_code->caption() ?><?= $Page->system_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system_code->cellAttributes() ?>>
<span id="el_api_systems_system_code">
<input type="<?= $Page->system_code->getInputTextType() ?>" name="x_system_code" id="x_system_code" data-table="api_systems" data-field="x_system_code" value="<?= $Page->system_code->EditValue ?>" size="30" maxlength="30" placeholder="<?= HtmlEncode($Page->system_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->system_code->formatPattern()) ?>"<?= $Page->system_code->editAttributes() ?> aria-describedby="x_system_code_help">
<?= $Page->system_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->system_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->system_name->Visible) { // system_name ?>
    <div id="r_system_name"<?= $Page->system_name->rowAttributes() ?>>
        <label id="elh_api_systems_system_name" for="x_system_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system_name->caption() ?><?= $Page->system_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system_name->cellAttributes() ?>>
<span id="el_api_systems_system_name">
<input type="<?= $Page->system_name->getInputTextType() ?>" name="x_system_name" id="x_system_name" data-table="api_systems" data-field="x_system_name" value="<?= $Page->system_name->EditValue ?>" size="30" maxlength="120" placeholder="<?= HtmlEncode($Page->system_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->system_name->formatPattern()) ?>"<?= $Page->system_name->editAttributes() ?> aria-describedby="x_system_name_help">
<?= $Page->system_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->system_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->base_url->Visible) { // base_url ?>
    <div id="r_base_url"<?= $Page->base_url->rowAttributes() ?>>
        <label id="elh_api_systems_base_url" for="x_base_url" class="<?= $Page->LeftColumnClass ?>"><?= $Page->base_url->caption() ?><?= $Page->base_url->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->base_url->cellAttributes() ?>>
<span id="el_api_systems_base_url">
<input type="<?= $Page->base_url->getInputTextType() ?>" name="x_base_url" id="x_base_url" data-table="api_systems" data-field="x_base_url" value="<?= $Page->base_url->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->base_url->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->base_url->formatPattern()) ?>"<?= $Page->base_url->editAttributes() ?> aria-describedby="x_base_url_help">
<?= $Page->base_url->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->base_url->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->auth_type->Visible) { // auth_type ?>
    <div id="r_auth_type"<?= $Page->auth_type->rowAttributes() ?>>
        <label id="elh_api_systems_auth_type" for="x_auth_type" class="<?= $Page->LeftColumnClass ?>"><?= $Page->auth_type->caption() ?><?= $Page->auth_type->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->auth_type->cellAttributes() ?>>
<span id="el_api_systems_auth_type">
<input type="<?= $Page->auth_type->getInputTextType() ?>" name="x_auth_type" id="x_auth_type" data-table="api_systems" data-field="x_auth_type" value="<?= $Page->auth_type->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->auth_type->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->auth_type->formatPattern()) ?>"<?= $Page->auth_type->editAttributes() ?> aria-describedby="x_auth_type_help">
<?= $Page->auth_type->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->auth_type->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->auth_token->Visible) { // auth_token ?>
    <div id="r_auth_token"<?= $Page->auth_token->rowAttributes() ?>>
        <label id="elh_api_systems_auth_token" for="x_auth_token" class="<?= $Page->LeftColumnClass ?>"><?= $Page->auth_token->caption() ?><?= $Page->auth_token->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->auth_token->cellAttributes() ?>>
<span id="el_api_systems_auth_token">
<textarea data-table="api_systems" data-field="x_auth_token" name="x_auth_token" id="x_auth_token" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->auth_token->getPlaceHolder()) ?>"<?= $Page->auth_token->editAttributes() ?> aria-describedby="x_auth_token_help"><?= $Page->auth_token->EditValue ?></textarea>
<?= $Page->auth_token->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->auth_token->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_api_systems_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_api_systems_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="api_systems" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_api_systems_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_api_systems_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="api_systems" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fapi_systemsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fapi_systemsadd", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <div id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <label id="elh_api_systems_updated_at" for="x_updated_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_at->caption() ?><?= $Page->updated_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_api_systems_updated_at">
<input type="<?= $Page->updated_at->getInputTextType() ?>" name="x_updated_at" id="x_updated_at" data-table="api_systems" data-field="x_updated_at" value="<?= $Page->updated_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->updated_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_at->formatPattern()) ?>"<?= $Page->updated_at->editAttributes() ?> aria-describedby="x_updated_at_help">
<?= $Page->updated_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_at->getErrorMessage() ?></div>
<?php if (!$Page->updated_at->ReadOnly && !$Page->updated_at->Disabled && !isset($Page->updated_at->EditAttrs["readonly"]) && !isset($Page->updated_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fapi_systemsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fapi_systemsadd", "x_updated_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fapi_systemsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fapi_systemsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("api_systems");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
