<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiResourcesAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_resources: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fapi_resourcesadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_resourcesadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["system_id", [fields.system_id.visible && fields.system_id.required ? ew.Validators.required(fields.system_id.caption) : null, ew.Validators.integer], fields.system_id.isInvalid],
            ["resource_key", [fields.resource_key.visible && fields.resource_key.required ? ew.Validators.required(fields.resource_key.caption) : null], fields.resource_key.isInvalid],
            ["caption", [fields.caption.visible && fields.caption.required ? ew.Validators.required(fields.caption.caption) : null], fields.caption.isInvalid],
            ["http_method", [fields.http_method.visible && fields.http_method.required ? ew.Validators.required(fields.http_method.caption) : null], fields.http_method.isInvalid],
            ["path_template", [fields.path_template.visible && fields.path_template.required ? ew.Validators.required(fields.path_template.caption) : null], fields.path_template.isInvalid],
            ["mode", [fields.mode.visible && fields.mode.required ? ew.Validators.required(fields.mode.caption) : null], fields.mode.isInvalid],
            ["required_fields", [fields.required_fields.visible && fields.required_fields.required ? ew.Validators.required(fields.required_fields.caption) : null], fields.required_fields.isInvalid],
            ["field_map", [fields.field_map.visible && fields.field_map.required ? ew.Validators.required(fields.field_map.caption) : null], fields.field_map.isInvalid],
            ["sample_payload", [fields.sample_payload.visible && fields.sample_payload.required ? ew.Validators.required(fields.sample_payload.caption) : null], fields.sample_payload.isInvalid],
            ["request_body", [fields.request_body.visible && fields.request_body.required ? ew.Validators.required(fields.request_body.caption) : null], fields.request_body.isInvalid],
            ["data_path", [fields.data_path.visible && fields.data_path.required ? ew.Validators.required(fields.data_path.caption) : null], fields.data_path.isInvalid],
            ["base_url", [fields.base_url.visible && fields.base_url.required ? ew.Validators.required(fields.base_url.caption) : null], fields.base_url.isInvalid],
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
<form name="fapi_resourcesadd" id="fapi_resourcesadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="api_resources">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->system_id->Visible) { // system_id ?>
    <div id="r_system_id"<?= $Page->system_id->rowAttributes() ?>>
        <label id="elh_api_resources_system_id" for="x_system_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system_id->caption() ?><?= $Page->system_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system_id->cellAttributes() ?>>
<span id="el_api_resources_system_id">
<input type="<?= $Page->system_id->getInputTextType() ?>" name="x_system_id" id="x_system_id" data-table="api_resources" data-field="x_system_id" value="<?= $Page->system_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->system_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->system_id->formatPattern()) ?>"<?= $Page->system_id->editAttributes() ?> aria-describedby="x_system_id_help">
<?= $Page->system_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->system_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->resource_key->Visible) { // resource_key ?>
    <div id="r_resource_key"<?= $Page->resource_key->rowAttributes() ?>>
        <label id="elh_api_resources_resource_key" for="x_resource_key" class="<?= $Page->LeftColumnClass ?>"><?= $Page->resource_key->caption() ?><?= $Page->resource_key->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->resource_key->cellAttributes() ?>>
<span id="el_api_resources_resource_key">
<input type="<?= $Page->resource_key->getInputTextType() ?>" name="x_resource_key" id="x_resource_key" data-table="api_resources" data-field="x_resource_key" value="<?= $Page->resource_key->EditValue ?>" size="30" maxlength="80" placeholder="<?= HtmlEncode($Page->resource_key->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->resource_key->formatPattern()) ?>"<?= $Page->resource_key->editAttributes() ?> aria-describedby="x_resource_key_help">
<?= $Page->resource_key->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->resource_key->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->caption->Visible) { // caption ?>
    <div id="r_caption"<?= $Page->caption->rowAttributes() ?>>
        <label id="elh_api_resources_caption" for="x_caption" class="<?= $Page->LeftColumnClass ?>"><?= $Page->caption->caption() ?><?= $Page->caption->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->caption->cellAttributes() ?>>
<span id="el_api_resources_caption">
<input type="<?= $Page->caption->getInputTextType() ?>" name="x_caption" id="x_caption" data-table="api_resources" data-field="x_caption" value="<?= $Page->caption->EditValue ?>" size="30" maxlength="500" placeholder="<?= HtmlEncode($Page->caption->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->caption->formatPattern()) ?>"<?= $Page->caption->editAttributes() ?> aria-describedby="x_caption_help">
<?= $Page->caption->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->caption->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->http_method->Visible) { // http_method ?>
    <div id="r_http_method"<?= $Page->http_method->rowAttributes() ?>>
        <label id="elh_api_resources_http_method" for="x_http_method" class="<?= $Page->LeftColumnClass ?>"><?= $Page->http_method->caption() ?><?= $Page->http_method->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->http_method->cellAttributes() ?>>
<span id="el_api_resources_http_method">
<input type="<?= $Page->http_method->getInputTextType() ?>" name="x_http_method" id="x_http_method" data-table="api_resources" data-field="x_http_method" value="<?= $Page->http_method->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->http_method->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->http_method->formatPattern()) ?>"<?= $Page->http_method->editAttributes() ?> aria-describedby="x_http_method_help">
<?= $Page->http_method->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->http_method->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->path_template->Visible) { // path_template ?>
    <div id="r_path_template"<?= $Page->path_template->rowAttributes() ?>>
        <label id="elh_api_resources_path_template" for="x_path_template" class="<?= $Page->LeftColumnClass ?>"><?= $Page->path_template->caption() ?><?= $Page->path_template->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->path_template->cellAttributes() ?>>
<span id="el_api_resources_path_template">
<input type="<?= $Page->path_template->getInputTextType() ?>" name="x_path_template" id="x_path_template" data-table="api_resources" data-field="x_path_template" value="<?= $Page->path_template->EditValue ?>" size="30" maxlength="1200" placeholder="<?= HtmlEncode($Page->path_template->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->path_template->formatPattern()) ?>"<?= $Page->path_template->editAttributes() ?> aria-describedby="x_path_template_help">
<?= $Page->path_template->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->path_template->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->mode->Visible) { // mode ?>
    <div id="r_mode"<?= $Page->mode->rowAttributes() ?>>
        <label id="elh_api_resources_mode" for="x_mode" class="<?= $Page->LeftColumnClass ?>"><?= $Page->mode->caption() ?><?= $Page->mode->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->mode->cellAttributes() ?>>
<span id="el_api_resources_mode">
<input type="<?= $Page->mode->getInputTextType() ?>" name="x_mode" id="x_mode" data-table="api_resources" data-field="x_mode" value="<?= $Page->mode->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->mode->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->mode->formatPattern()) ?>"<?= $Page->mode->editAttributes() ?> aria-describedby="x_mode_help">
<?= $Page->mode->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->mode->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->required_fields->Visible) { // required_fields ?>
    <div id="r_required_fields"<?= $Page->required_fields->rowAttributes() ?>>
        <label id="elh_api_resources_required_fields" for="x_required_fields" class="<?= $Page->LeftColumnClass ?>"><?= $Page->required_fields->caption() ?><?= $Page->required_fields->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->required_fields->cellAttributes() ?>>
<span id="el_api_resources_required_fields">
<textarea data-table="api_resources" data-field="x_required_fields" name="x_required_fields" id="x_required_fields" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->required_fields->getPlaceHolder()) ?>"<?= $Page->required_fields->editAttributes() ?> aria-describedby="x_required_fields_help"><?= $Page->required_fields->EditValue ?></textarea>
<?= $Page->required_fields->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->required_fields->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->field_map->Visible) { // field_map ?>
    <div id="r_field_map"<?= $Page->field_map->rowAttributes() ?>>
        <label id="elh_api_resources_field_map" for="x_field_map" class="<?= $Page->LeftColumnClass ?>"><?= $Page->field_map->caption() ?><?= $Page->field_map->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->field_map->cellAttributes() ?>>
<span id="el_api_resources_field_map">
<textarea data-table="api_resources" data-field="x_field_map" name="x_field_map" id="x_field_map" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->field_map->getPlaceHolder()) ?>"<?= $Page->field_map->editAttributes() ?> aria-describedby="x_field_map_help"><?= $Page->field_map->EditValue ?></textarea>
<?= $Page->field_map->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->field_map->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->sample_payload->Visible) { // sample_payload ?>
    <div id="r_sample_payload"<?= $Page->sample_payload->rowAttributes() ?>>
        <label id="elh_api_resources_sample_payload" for="x_sample_payload" class="<?= $Page->LeftColumnClass ?>"><?= $Page->sample_payload->caption() ?><?= $Page->sample_payload->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->sample_payload->cellAttributes() ?>>
<span id="el_api_resources_sample_payload">
<textarea data-table="api_resources" data-field="x_sample_payload" name="x_sample_payload" id="x_sample_payload" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->sample_payload->getPlaceHolder()) ?>"<?= $Page->sample_payload->editAttributes() ?> aria-describedby="x_sample_payload_help"><?= $Page->sample_payload->EditValue ?></textarea>
<?= $Page->sample_payload->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->sample_payload->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->request_body->Visible) { // request_body ?>
    <div id="r_request_body"<?= $Page->request_body->rowAttributes() ?>>
        <label id="elh_api_resources_request_body" for="x_request_body" class="<?= $Page->LeftColumnClass ?>"><?= $Page->request_body->caption() ?><?= $Page->request_body->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->request_body->cellAttributes() ?>>
<span id="el_api_resources_request_body">
<textarea data-table="api_resources" data-field="x_request_body" name="x_request_body" id="x_request_body" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->request_body->getPlaceHolder()) ?>"<?= $Page->request_body->editAttributes() ?> aria-describedby="x_request_body_help"><?= $Page->request_body->EditValue ?></textarea>
<?= $Page->request_body->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->request_body->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->data_path->Visible) { // data_path ?>
    <div id="r_data_path"<?= $Page->data_path->rowAttributes() ?>>
        <label id="elh_api_resources_data_path" for="x_data_path" class="<?= $Page->LeftColumnClass ?>"><?= $Page->data_path->caption() ?><?= $Page->data_path->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->data_path->cellAttributes() ?>>
<span id="el_api_resources_data_path">
<input type="<?= $Page->data_path->getInputTextType() ?>" name="x_data_path" id="x_data_path" data-table="api_resources" data-field="x_data_path" value="<?= $Page->data_path->EditValue ?>" size="30" maxlength="120" placeholder="<?= HtmlEncode($Page->data_path->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->data_path->formatPattern()) ?>"<?= $Page->data_path->editAttributes() ?> aria-describedby="x_data_path_help">
<?= $Page->data_path->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->data_path->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->base_url->Visible) { // base_url ?>
    <div id="r_base_url"<?= $Page->base_url->rowAttributes() ?>>
        <label id="elh_api_resources_base_url" for="x_base_url" class="<?= $Page->LeftColumnClass ?>"><?= $Page->base_url->caption() ?><?= $Page->base_url->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->base_url->cellAttributes() ?>>
<span id="el_api_resources_base_url">
<input type="<?= $Page->base_url->getInputTextType() ?>" name="x_base_url" id="x_base_url" data-table="api_resources" data-field="x_base_url" value="<?= $Page->base_url->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->base_url->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->base_url->formatPattern()) ?>"<?= $Page->base_url->editAttributes() ?> aria-describedby="x_base_url_help">
<?= $Page->base_url->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->base_url->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->auth_token->Visible) { // auth_token ?>
    <div id="r_auth_token"<?= $Page->auth_token->rowAttributes() ?>>
        <label id="elh_api_resources_auth_token" for="x_auth_token" class="<?= $Page->LeftColumnClass ?>"><?= $Page->auth_token->caption() ?><?= $Page->auth_token->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->auth_token->cellAttributes() ?>>
<span id="el_api_resources_auth_token">
<textarea data-table="api_resources" data-field="x_auth_token" name="x_auth_token" id="x_auth_token" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->auth_token->getPlaceHolder()) ?>"<?= $Page->auth_token->editAttributes() ?> aria-describedby="x_auth_token_help"><?= $Page->auth_token->EditValue ?></textarea>
<?= $Page->auth_token->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->auth_token->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_api_resources_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_api_resources_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="api_resources" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_api_resources_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_api_resources_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="api_resources" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fapi_resourcesadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fapi_resourcesadd", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <div id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <label id="elh_api_resources_updated_at" for="x_updated_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_at->caption() ?><?= $Page->updated_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_api_resources_updated_at">
<input type="<?= $Page->updated_at->getInputTextType() ?>" name="x_updated_at" id="x_updated_at" data-table="api_resources" data-field="x_updated_at" value="<?= $Page->updated_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->updated_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_at->formatPattern()) ?>"<?= $Page->updated_at->editAttributes() ?> aria-describedby="x_updated_at_help">
<?= $Page->updated_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_at->getErrorMessage() ?></div>
<?php if (!$Page->updated_at->ReadOnly && !$Page->updated_at->Disabled && !isset($Page->updated_at->EditAttrs["readonly"]) && !isset($Page->updated_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fapi_resourcesadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fapi_resourcesadd", "x_updated_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fapi_resourcesadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fapi_resourcesadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("api_resources");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
