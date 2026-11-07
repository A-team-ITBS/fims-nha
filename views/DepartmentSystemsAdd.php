<?php

namespace PHPMaker2024\FIMS;

// Page object
$DepartmentSystemsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { department_systems: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fdepartment_systemsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdepartment_systemsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["department_id", [fields.department_id.visible && fields.department_id.required ? ew.Validators.required(fields.department_id.caption) : null, ew.Validators.integer], fields.department_id.isInvalid],
            ["system_id", [fields.system_id.visible && fields.system_id.required ? ew.Validators.required(fields.system_id.caption) : null, ew.Validators.integer], fields.system_id.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["access_level", [fields.access_level.visible && fields.access_level.required ? ew.Validators.required(fields.access_level.caption) : null], fields.access_level.isInvalid],
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
<form name="fdepartment_systemsadd" id="fdepartment_systemsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="department_systems">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->department_id->Visible) { // department_id ?>
    <div id="r_department_id"<?= $Page->department_id->rowAttributes() ?>>
        <label id="elh_department_systems_department_id" for="x_department_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->department_id->caption() ?><?= $Page->department_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->department_id->cellAttributes() ?>>
<span id="el_department_systems_department_id">
<input type="<?= $Page->department_id->getInputTextType() ?>" name="x_department_id" id="x_department_id" data-table="department_systems" data-field="x_department_id" value="<?= $Page->department_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->department_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->department_id->formatPattern()) ?>"<?= $Page->department_id->editAttributes() ?> aria-describedby="x_department_id_help">
<?= $Page->department_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->department_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->system_id->Visible) { // system_id ?>
    <div id="r_system_id"<?= $Page->system_id->rowAttributes() ?>>
        <label id="elh_department_systems_system_id" for="x_system_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system_id->caption() ?><?= $Page->system_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system_id->cellAttributes() ?>>
<span id="el_department_systems_system_id">
<input type="<?= $Page->system_id->getInputTextType() ?>" name="x_system_id" id="x_system_id" data-table="department_systems" data-field="x_system_id" value="<?= $Page->system_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->system_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->system_id->formatPattern()) ?>"<?= $Page->system_id->editAttributes() ?> aria-describedby="x_system_id_help">
<?= $Page->system_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->system_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_department_systems_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_department_systems_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="department_systems" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->access_level->Visible) { // access_level ?>
    <div id="r_access_level"<?= $Page->access_level->rowAttributes() ?>>
        <label id="elh_department_systems_access_level" for="x_access_level" class="<?= $Page->LeftColumnClass ?>"><?= $Page->access_level->caption() ?><?= $Page->access_level->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->access_level->cellAttributes() ?>>
<span id="el_department_systems_access_level">
<input type="<?= $Page->access_level->getInputTextType() ?>" name="x_access_level" id="x_access_level" data-table="department_systems" data-field="x_access_level" value="<?= $Page->access_level->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->access_level->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->access_level->formatPattern()) ?>"<?= $Page->access_level->editAttributes() ?> aria-describedby="x_access_level_help">
<?= $Page->access_level->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->access_level->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->assigned_by->Visible) { // assigned_by ?>
    <div id="r_assigned_by"<?= $Page->assigned_by->rowAttributes() ?>>
        <label id="elh_department_systems_assigned_by" for="x_assigned_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->assigned_by->caption() ?><?= $Page->assigned_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->assigned_by->cellAttributes() ?>>
<span id="el_department_systems_assigned_by">
<input type="<?= $Page->assigned_by->getInputTextType() ?>" name="x_assigned_by" id="x_assigned_by" data-table="department_systems" data-field="x_assigned_by" value="<?= $Page->assigned_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->assigned_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->assigned_by->formatPattern()) ?>"<?= $Page->assigned_by->editAttributes() ?> aria-describedby="x_assigned_by_help">
<?= $Page->assigned_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->assigned_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->assigned_at->Visible) { // assigned_at ?>
    <div id="r_assigned_at"<?= $Page->assigned_at->rowAttributes() ?>>
        <label id="elh_department_systems_assigned_at" for="x_assigned_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->assigned_at->caption() ?><?= $Page->assigned_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->assigned_at->cellAttributes() ?>>
<span id="el_department_systems_assigned_at">
<input type="<?= $Page->assigned_at->getInputTextType() ?>" name="x_assigned_at" id="x_assigned_at" data-table="department_systems" data-field="x_assigned_at" value="<?= $Page->assigned_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->assigned_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->assigned_at->formatPattern()) ?>"<?= $Page->assigned_at->editAttributes() ?> aria-describedby="x_assigned_at_help">
<?= $Page->assigned_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->assigned_at->getErrorMessage() ?></div>
<?php if (!$Page->assigned_at->ReadOnly && !$Page->assigned_at->Disabled && !isset($Page->assigned_at->EditAttrs["readonly"]) && !isset($Page->assigned_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fdepartment_systemsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fdepartment_systemsadd", "x_assigned_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fdepartment_systemsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fdepartment_systemsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("department_systems");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
