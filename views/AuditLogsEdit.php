<?php

namespace PHPMaker2024\FIMS;

// Page object
$AuditLogsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="faudit_logsedit" id="faudit_logsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { audit_logs: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var faudit_logsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("faudit_logsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["id", [fields.id.visible && fields.id.required ? ew.Validators.required(fields.id.caption) : null], fields.id.isInvalid],
            ["date_time", [fields.date_time.visible && fields.date_time.required ? ew.Validators.required(fields.date_time.caption) : null, ew.Validators.datetime(fields.date_time.clientFormatPattern)], fields.date_time.isInvalid],
            ["script", [fields.script.visible && fields.script.required ? ew.Validators.required(fields.script.caption) : null], fields.script.isInvalid],
            ["user", [fields.user.visible && fields.user.required ? ew.Validators.required(fields.user.caption) : null], fields.user.isInvalid],
            ["_action", [fields._action.visible && fields._action.required ? ew.Validators.required(fields._action.caption) : null], fields._action.isInvalid],
            ["_table", [fields._table.visible && fields._table.required ? ew.Validators.required(fields._table.caption) : null], fields._table.isInvalid],
            ["field", [fields.field.visible && fields.field.required ? ew.Validators.required(fields.field.caption) : null], fields.field.isInvalid],
            ["key_value", [fields.key_value.visible && fields.key_value.required ? ew.Validators.required(fields.key_value.caption) : null], fields.key_value.isInvalid],
            ["old_value", [fields.old_value.visible && fields.old_value.required ? ew.Validators.required(fields.old_value.caption) : null], fields.old_value.isInvalid],
            ["new_value", [fields.new_value.visible && fields.new_value.required ? ew.Validators.required(fields.new_value.caption) : null], fields.new_value.isInvalid]
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
<input type="hidden" name="t" value="audit_logs">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->id->Visible) { // id ?>
    <div id="r_id"<?= $Page->id->rowAttributes() ?>>
        <label id="elh_audit_logs_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->id->caption() ?><?= $Page->id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->id->cellAttributes() ?>>
<span id="el_audit_logs_id">
<span<?= $Page->id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->id->getDisplayValue($Page->id->EditValue))) ?>"></span>
<input type="hidden" data-table="audit_logs" data-field="x_id" data-hidden="1" name="x_id" id="x_id" value="<?= HtmlEncode($Page->id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->date_time->Visible) { // date_time ?>
    <div id="r_date_time"<?= $Page->date_time->rowAttributes() ?>>
        <label id="elh_audit_logs_date_time" for="x_date_time" class="<?= $Page->LeftColumnClass ?>"><?= $Page->date_time->caption() ?><?= $Page->date_time->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->date_time->cellAttributes() ?>>
<span id="el_audit_logs_date_time">
<input type="<?= $Page->date_time->getInputTextType() ?>" name="x_date_time" id="x_date_time" data-table="audit_logs" data-field="x_date_time" value="<?= $Page->date_time->EditValue ?>" placeholder="<?= HtmlEncode($Page->date_time->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->date_time->formatPattern()) ?>"<?= $Page->date_time->editAttributes() ?> aria-describedby="x_date_time_help">
<?= $Page->date_time->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->date_time->getErrorMessage() ?></div>
<?php if (!$Page->date_time->ReadOnly && !$Page->date_time->Disabled && !isset($Page->date_time->EditAttrs["readonly"]) && !isset($Page->date_time->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["faudit_logsedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("faudit_logsedit", "x_date_time", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->script->Visible) { // script ?>
    <div id="r_script"<?= $Page->script->rowAttributes() ?>>
        <label id="elh_audit_logs_script" for="x_script" class="<?= $Page->LeftColumnClass ?>"><?= $Page->script->caption() ?><?= $Page->script->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->script->cellAttributes() ?>>
<span id="el_audit_logs_script">
<input type="<?= $Page->script->getInputTextType() ?>" name="x_script" id="x_script" data-table="audit_logs" data-field="x_script" value="<?= $Page->script->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->script->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->script->formatPattern()) ?>"<?= $Page->script->editAttributes() ?> aria-describedby="x_script_help">
<?= $Page->script->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->script->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user->Visible) { // user ?>
    <div id="r_user"<?= $Page->user->rowAttributes() ?>>
        <label id="elh_audit_logs_user" for="x_user" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user->caption() ?><?= $Page->user->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user->cellAttributes() ?>>
<span id="el_audit_logs_user">
<input type="<?= $Page->user->getInputTextType() ?>" name="x_user" id="x_user" data-table="audit_logs" data-field="x_user" value="<?= $Page->user->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->user->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->user->formatPattern()) ?>"<?= $Page->user->editAttributes() ?> aria-describedby="x_user_help">
<?= $Page->user->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->user->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->_action->Visible) { // action ?>
    <div id="r__action"<?= $Page->_action->rowAttributes() ?>>
        <label id="elh_audit_logs__action" for="x__action" class="<?= $Page->LeftColumnClass ?>"><?= $Page->_action->caption() ?><?= $Page->_action->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->_action->cellAttributes() ?>>
<span id="el_audit_logs__action">
<input type="<?= $Page->_action->getInputTextType() ?>" name="x__action" id="x__action" data-table="audit_logs" data-field="x__action" value="<?= $Page->_action->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->_action->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->_action->formatPattern()) ?>"<?= $Page->_action->editAttributes() ?> aria-describedby="x__action_help">
<?= $Page->_action->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->_action->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->_table->Visible) { // table ?>
    <div id="r__table"<?= $Page->_table->rowAttributes() ?>>
        <label id="elh_audit_logs__table" for="x__table" class="<?= $Page->LeftColumnClass ?>"><?= $Page->_table->caption() ?><?= $Page->_table->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->_table->cellAttributes() ?>>
<span id="el_audit_logs__table">
<input type="<?= $Page->_table->getInputTextType() ?>" name="x__table" id="x__table" data-table="audit_logs" data-field="x__table" value="<?= $Page->_table->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->_table->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->_table->formatPattern()) ?>"<?= $Page->_table->editAttributes() ?> aria-describedby="x__table_help">
<?= $Page->_table->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->_table->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->field->Visible) { // field ?>
    <div id="r_field"<?= $Page->field->rowAttributes() ?>>
        <label id="elh_audit_logs_field" for="x_field" class="<?= $Page->LeftColumnClass ?>"><?= $Page->field->caption() ?><?= $Page->field->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->field->cellAttributes() ?>>
<span id="el_audit_logs_field">
<input type="<?= $Page->field->getInputTextType() ?>" name="x_field" id="x_field" data-table="audit_logs" data-field="x_field" value="<?= $Page->field->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->field->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->field->formatPattern()) ?>"<?= $Page->field->editAttributes() ?> aria-describedby="x_field_help">
<?= $Page->field->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->field->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->key_value->Visible) { // key_value ?>
    <div id="r_key_value"<?= $Page->key_value->rowAttributes() ?>>
        <label id="elh_audit_logs_key_value" for="x_key_value" class="<?= $Page->LeftColumnClass ?>"><?= $Page->key_value->caption() ?><?= $Page->key_value->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->key_value->cellAttributes() ?>>
<span id="el_audit_logs_key_value">
<textarea data-table="audit_logs" data-field="x_key_value" name="x_key_value" id="x_key_value" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->key_value->getPlaceHolder()) ?>"<?= $Page->key_value->editAttributes() ?> aria-describedby="x_key_value_help"><?= $Page->key_value->EditValue ?></textarea>
<?= $Page->key_value->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->key_value->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->old_value->Visible) { // old_value ?>
    <div id="r_old_value"<?= $Page->old_value->rowAttributes() ?>>
        <label id="elh_audit_logs_old_value" for="x_old_value" class="<?= $Page->LeftColumnClass ?>"><?= $Page->old_value->caption() ?><?= $Page->old_value->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->old_value->cellAttributes() ?>>
<span id="el_audit_logs_old_value">
<textarea data-table="audit_logs" data-field="x_old_value" name="x_old_value" id="x_old_value" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->old_value->getPlaceHolder()) ?>"<?= $Page->old_value->editAttributes() ?> aria-describedby="x_old_value_help"><?= $Page->old_value->EditValue ?></textarea>
<?= $Page->old_value->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->old_value->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->new_value->Visible) { // new_value ?>
    <div id="r_new_value"<?= $Page->new_value->rowAttributes() ?>>
        <label id="elh_audit_logs_new_value" for="x_new_value" class="<?= $Page->LeftColumnClass ?>"><?= $Page->new_value->caption() ?><?= $Page->new_value->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->new_value->cellAttributes() ?>>
<span id="el_audit_logs_new_value">
<textarea data-table="audit_logs" data-field="x_new_value" name="x_new_value" id="x_new_value" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->new_value->getPlaceHolder()) ?>"<?= $Page->new_value->editAttributes() ?> aria-describedby="x_new_value_help"><?= $Page->new_value->EditValue ?></textarea>
<?= $Page->new_value->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->new_value->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="faudit_logsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="faudit_logsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("audit_logs");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
