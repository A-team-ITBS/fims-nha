<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiLogsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fapi_logsedit" id="fapi_logsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_logs: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fapi_logsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_logsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["log_id", [fields.log_id.visible && fields.log_id.required ? ew.Validators.required(fields.log_id.caption) : null], fields.log_id.isInvalid],
            ["resource_id", [fields.resource_id.visible && fields.resource_id.required ? ew.Validators.required(fields.resource_id.caption) : null, ew.Validators.integer], fields.resource_id.isInvalid],
            ["mode_used", [fields.mode_used.visible && fields.mode_used.required ? ew.Validators.required(fields.mode_used.caption) : null], fields.mode_used.isInvalid],
            ["http_status", [fields.http_status.visible && fields.http_status.required ? ew.Validators.required(fields.http_status.caption) : null, ew.Validators.integer], fields.http_status.isInvalid],
            ["duration_ms", [fields.duration_ms.visible && fields.duration_ms.required ? ew.Validators.required(fields.duration_ms.caption) : null, ew.Validators.integer], fields.duration_ms.isInvalid],
            ["success", [fields.success.visible && fields.success.required ? ew.Validators.required(fields.success.caption) : null], fields.success.isInvalid],
            ["_message", [fields._message.visible && fields._message.required ? ew.Validators.required(fields._message.caption) : null], fields._message.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null, ew.Validators.datetime(fields.created_at.clientFormatPattern)], fields.created_at.isInvalid]
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
            "success": <?= $Page->success->toClientList($Page) ?>,
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
<input type="hidden" name="t" value="api_logs">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->log_id->Visible) { // log_id ?>
    <div id="r_log_id"<?= $Page->log_id->rowAttributes() ?>>
        <label id="elh_api_logs_log_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->log_id->caption() ?><?= $Page->log_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->log_id->cellAttributes() ?>>
<span id="el_api_logs_log_id">
<span<?= $Page->log_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->log_id->getDisplayValue($Page->log_id->EditValue))) ?>"></span>
<input type="hidden" data-table="api_logs" data-field="x_log_id" data-hidden="1" name="x_log_id" id="x_log_id" value="<?= HtmlEncode($Page->log_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->resource_id->Visible) { // resource_id ?>
    <div id="r_resource_id"<?= $Page->resource_id->rowAttributes() ?>>
        <label id="elh_api_logs_resource_id" for="x_resource_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->resource_id->caption() ?><?= $Page->resource_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->resource_id->cellAttributes() ?>>
<span id="el_api_logs_resource_id">
<input type="<?= $Page->resource_id->getInputTextType() ?>" name="x_resource_id" id="x_resource_id" data-table="api_logs" data-field="x_resource_id" value="<?= $Page->resource_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->resource_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->resource_id->formatPattern()) ?>"<?= $Page->resource_id->editAttributes() ?> aria-describedby="x_resource_id_help">
<?= $Page->resource_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->resource_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->mode_used->Visible) { // mode_used ?>
    <div id="r_mode_used"<?= $Page->mode_used->rowAttributes() ?>>
        <label id="elh_api_logs_mode_used" for="x_mode_used" class="<?= $Page->LeftColumnClass ?>"><?= $Page->mode_used->caption() ?><?= $Page->mode_used->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->mode_used->cellAttributes() ?>>
<span id="el_api_logs_mode_used">
<input type="<?= $Page->mode_used->getInputTextType() ?>" name="x_mode_used" id="x_mode_used" data-table="api_logs" data-field="x_mode_used" value="<?= $Page->mode_used->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->mode_used->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->mode_used->formatPattern()) ?>"<?= $Page->mode_used->editAttributes() ?> aria-describedby="x_mode_used_help">
<?= $Page->mode_used->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->mode_used->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->http_status->Visible) { // http_status ?>
    <div id="r_http_status"<?= $Page->http_status->rowAttributes() ?>>
        <label id="elh_api_logs_http_status" for="x_http_status" class="<?= $Page->LeftColumnClass ?>"><?= $Page->http_status->caption() ?><?= $Page->http_status->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->http_status->cellAttributes() ?>>
<span id="el_api_logs_http_status">
<input type="<?= $Page->http_status->getInputTextType() ?>" name="x_http_status" id="x_http_status" data-table="api_logs" data-field="x_http_status" value="<?= $Page->http_status->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->http_status->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->http_status->formatPattern()) ?>"<?= $Page->http_status->editAttributes() ?> aria-describedby="x_http_status_help">
<?= $Page->http_status->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->http_status->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->duration_ms->Visible) { // duration_ms ?>
    <div id="r_duration_ms"<?= $Page->duration_ms->rowAttributes() ?>>
        <label id="elh_api_logs_duration_ms" for="x_duration_ms" class="<?= $Page->LeftColumnClass ?>"><?= $Page->duration_ms->caption() ?><?= $Page->duration_ms->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->duration_ms->cellAttributes() ?>>
<span id="el_api_logs_duration_ms">
<input type="<?= $Page->duration_ms->getInputTextType() ?>" name="x_duration_ms" id="x_duration_ms" data-table="api_logs" data-field="x_duration_ms" value="<?= $Page->duration_ms->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->duration_ms->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->duration_ms->formatPattern()) ?>"<?= $Page->duration_ms->editAttributes() ?> aria-describedby="x_duration_ms_help">
<?= $Page->duration_ms->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->duration_ms->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->success->Visible) { // success ?>
    <div id="r_success"<?= $Page->success->rowAttributes() ?>>
        <label id="elh_api_logs_success" class="<?= $Page->LeftColumnClass ?>"><?= $Page->success->caption() ?><?= $Page->success->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->success->cellAttributes() ?>>
<span id="el_api_logs_success">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->success->isInvalidClass() ?>" data-table="api_logs" data-field="x_success" data-boolean name="x_success" id="x_success" value="1"<?= ConvertToBool($Page->success->CurrentValue) ? " checked" : "" ?><?= $Page->success->editAttributes() ?> aria-describedby="x_success_help">
    <div class="invalid-feedback"><?= $Page->success->getErrorMessage() ?></div>
</div>
<?= $Page->success->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->_message->Visible) { // message ?>
    <div id="r__message"<?= $Page->_message->rowAttributes() ?>>
        <label id="elh_api_logs__message" for="x__message" class="<?= $Page->LeftColumnClass ?>"><?= $Page->_message->caption() ?><?= $Page->_message->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->_message->cellAttributes() ?>>
<span id="el_api_logs__message">
<textarea data-table="api_logs" data-field="x__message" name="x__message" id="x__message" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->_message->getPlaceHolder()) ?>"<?= $Page->_message->editAttributes() ?> aria-describedby="x__message_help"><?= $Page->_message->EditValue ?></textarea>
<?= $Page->_message->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->_message->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_api_logs_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_api_logs_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="api_logs" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fapi_logsedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fapi_logsedit", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fapi_logsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fapi_logsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("api_logs");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
