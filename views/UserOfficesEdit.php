<?php

namespace PHPMaker2024\FIMS;

// Page object
$UserOfficesEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fuser_officesedit" id="fuser_officesedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { user_offices: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fuser_officesedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fuser_officesedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["user_office_id", [fields.user_office_id.visible && fields.user_office_id.required ? ew.Validators.required(fields.user_office_id.caption) : null], fields.user_office_id.isInvalid],
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null, ew.Validators.integer], fields.user_id.isInvalid],
            ["office_id", [fields.office_id.visible && fields.office_id.required ? ew.Validators.required(fields.office_id.caption) : null, ew.Validators.integer], fields.office_id.isInvalid],
            ["is_primary", [fields.is_primary.visible && fields.is_primary.required ? ew.Validators.required(fields.is_primary.caption) : null], fields.is_primary.isInvalid],
            ["access_start_date", [fields.access_start_date.visible && fields.access_start_date.required ? ew.Validators.required(fields.access_start_date.caption) : null, ew.Validators.datetime(fields.access_start_date.clientFormatPattern)], fields.access_start_date.isInvalid],
            ["access_end_date", [fields.access_end_date.visible && fields.access_end_date.required ? ew.Validators.required(fields.access_end_date.caption) : null, ew.Validators.datetime(fields.access_end_date.clientFormatPattern)], fields.access_end_date.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["assigned_by", [fields.assigned_by.visible && fields.assigned_by.required ? ew.Validators.required(fields.assigned_by.caption) : null, ew.Validators.integer], fields.assigned_by.isInvalid],
            ["assigned_at", [fields.assigned_at.visible && fields.assigned_at.required ? ew.Validators.required(fields.assigned_at.caption) : null, ew.Validators.datetime(fields.assigned_at.clientFormatPattern)], fields.assigned_at.isInvalid]
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
            "is_primary": <?= $Page->is_primary->toClientList($Page) ?>,
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
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="user_offices">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->user_office_id->Visible) { // user_office_id ?>
    <div id="r_user_office_id"<?= $Page->user_office_id->rowAttributes() ?>>
        <label id="elh_user_offices_user_office_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_office_id->caption() ?><?= $Page->user_office_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_office_id->cellAttributes() ?>>
<span id="el_user_offices_user_office_id">
<span<?= $Page->user_office_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->user_office_id->getDisplayValue($Page->user_office_id->EditValue))) ?>"></span>
<input type="hidden" data-table="user_offices" data-field="x_user_office_id" data-hidden="1" name="x_user_office_id" id="x_user_office_id" value="<?= HtmlEncode($Page->user_office_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <div id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <label id="elh_user_offices_user_id" for="x_user_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_id->caption() ?><?= $Page->user_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_id->cellAttributes() ?>>
<span id="el_user_offices_user_id">
<input type="<?= $Page->user_id->getInputTextType() ?>" name="x_user_id" id="x_user_id" data-table="user_offices" data-field="x_user_id" value="<?= $Page->user_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->user_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->user_id->formatPattern()) ?>"<?= $Page->user_id->editAttributes() ?> aria-describedby="x_user_id_help">
<?= $Page->user_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->user_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->office_id->Visible) { // office_id ?>
    <div id="r_office_id"<?= $Page->office_id->rowAttributes() ?>>
        <label id="elh_user_offices_office_id" for="x_office_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->office_id->caption() ?><?= $Page->office_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->office_id->cellAttributes() ?>>
<span id="el_user_offices_office_id">
<input type="<?= $Page->office_id->getInputTextType() ?>" name="x_office_id" id="x_office_id" data-table="user_offices" data-field="x_office_id" value="<?= $Page->office_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->office_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->office_id->formatPattern()) ?>"<?= $Page->office_id->editAttributes() ?> aria-describedby="x_office_id_help">
<?= $Page->office_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->office_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_primary->Visible) { // is_primary ?>
    <div id="r_is_primary"<?= $Page->is_primary->rowAttributes() ?>>
        <label id="elh_user_offices_is_primary" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_primary->caption() ?><?= $Page->is_primary->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_primary->cellAttributes() ?>>
<span id="el_user_offices_is_primary">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_primary->isInvalidClass() ?>" data-table="user_offices" data-field="x_is_primary" data-boolean name="x_is_primary" id="x_is_primary" value="1"<?= ConvertToBool($Page->is_primary->CurrentValue) ? " checked" : "" ?><?= $Page->is_primary->editAttributes() ?> aria-describedby="x_is_primary_help">
    <div class="invalid-feedback"><?= $Page->is_primary->getErrorMessage() ?></div>
</div>
<?= $Page->is_primary->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->access_start_date->Visible) { // access_start_date ?>
    <div id="r_access_start_date"<?= $Page->access_start_date->rowAttributes() ?>>
        <label id="elh_user_offices_access_start_date" for="x_access_start_date" class="<?= $Page->LeftColumnClass ?>"><?= $Page->access_start_date->caption() ?><?= $Page->access_start_date->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->access_start_date->cellAttributes() ?>>
<span id="el_user_offices_access_start_date">
<input type="<?= $Page->access_start_date->getInputTextType() ?>" name="x_access_start_date" id="x_access_start_date" data-table="user_offices" data-field="x_access_start_date" value="<?= $Page->access_start_date->EditValue ?>" placeholder="<?= HtmlEncode($Page->access_start_date->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->access_start_date->formatPattern()) ?>"<?= $Page->access_start_date->editAttributes() ?> aria-describedby="x_access_start_date_help">
<?= $Page->access_start_date->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->access_start_date->getErrorMessage() ?></div>
<?php if (!$Page->access_start_date->ReadOnly && !$Page->access_start_date->Disabled && !isset($Page->access_start_date->EditAttrs["readonly"]) && !isset($Page->access_start_date->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fuser_officesedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fuser_officesedit", "x_access_start_date", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->access_end_date->Visible) { // access_end_date ?>
    <div id="r_access_end_date"<?= $Page->access_end_date->rowAttributes() ?>>
        <label id="elh_user_offices_access_end_date" for="x_access_end_date" class="<?= $Page->LeftColumnClass ?>"><?= $Page->access_end_date->caption() ?><?= $Page->access_end_date->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->access_end_date->cellAttributes() ?>>
<span id="el_user_offices_access_end_date">
<input type="<?= $Page->access_end_date->getInputTextType() ?>" name="x_access_end_date" id="x_access_end_date" data-table="user_offices" data-field="x_access_end_date" value="<?= $Page->access_end_date->EditValue ?>" placeholder="<?= HtmlEncode($Page->access_end_date->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->access_end_date->formatPattern()) ?>"<?= $Page->access_end_date->editAttributes() ?> aria-describedby="x_access_end_date_help">
<?= $Page->access_end_date->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->access_end_date->getErrorMessage() ?></div>
<?php if (!$Page->access_end_date->ReadOnly && !$Page->access_end_date->Disabled && !isset($Page->access_end_date->EditAttrs["readonly"]) && !isset($Page->access_end_date->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fuser_officesedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fuser_officesedit", "x_access_end_date", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_user_offices_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_user_offices_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="user_offices" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->assigned_by->Visible) { // assigned_by ?>
    <div id="r_assigned_by"<?= $Page->assigned_by->rowAttributes() ?>>
        <label id="elh_user_offices_assigned_by" for="x_assigned_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->assigned_by->caption() ?><?= $Page->assigned_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->assigned_by->cellAttributes() ?>>
<span id="el_user_offices_assigned_by">
<input type="<?= $Page->assigned_by->getInputTextType() ?>" name="x_assigned_by" id="x_assigned_by" data-table="user_offices" data-field="x_assigned_by" value="<?= $Page->assigned_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->assigned_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->assigned_by->formatPattern()) ?>"<?= $Page->assigned_by->editAttributes() ?> aria-describedby="x_assigned_by_help">
<?= $Page->assigned_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->assigned_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->assigned_at->Visible) { // assigned_at ?>
    <div id="r_assigned_at"<?= $Page->assigned_at->rowAttributes() ?>>
        <label id="elh_user_offices_assigned_at" for="x_assigned_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->assigned_at->caption() ?><?= $Page->assigned_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->assigned_at->cellAttributes() ?>>
<span id="el_user_offices_assigned_at">
<input type="<?= $Page->assigned_at->getInputTextType() ?>" name="x_assigned_at" id="x_assigned_at" data-table="user_offices" data-field="x_assigned_at" value="<?= $Page->assigned_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->assigned_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->assigned_at->formatPattern()) ?>"<?= $Page->assigned_at->editAttributes() ?> aria-describedby="x_assigned_at_help">
<?= $Page->assigned_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->assigned_at->getErrorMessage() ?></div>
<?php if (!$Page->assigned_at->ReadOnly && !$Page->assigned_at->Disabled && !isset($Page->assigned_at->EditAttrs["readonly"]) && !isset($Page->assigned_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fuser_officesedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fuser_officesedit", "x_assigned_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fuser_officesedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fuser_officesedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("user_offices");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
