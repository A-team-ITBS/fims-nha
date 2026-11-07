<?php

namespace PHPMaker2024\FIMS;

// Page object
$SystemsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { systems: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fsystemsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fsystemsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["system_name", [fields.system_name.visible && fields.system_name.required ? ew.Validators.required(fields.system_name.caption) : null], fields.system_name.isInvalid],
            ["system_code", [fields.system_code.visible && fields.system_code.required ? ew.Validators.required(fields.system_code.caption) : null], fields.system_code.isInvalid],
            ["description", [fields.description.visible && fields.description.required ? ew.Validators.required(fields.description.caption) : null], fields.description.isInvalid],
            ["level_permissions", [fields.level_permissions.visible && fields.level_permissions.required ? ew.Validators.required(fields.level_permissions.caption) : null], fields.level_permissions.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["url", [fields.url.visible && fields.url.required ? ew.Validators.required(fields.url.caption) : null], fields.url.isInvalid]
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
<form name="fsystemsadd" id="fsystemsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="systems">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->system_name->Visible) { // system_name ?>
    <div id="r_system_name"<?= $Page->system_name->rowAttributes() ?>>
        <label id="elh_systems_system_name" for="x_system_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system_name->caption() ?><?= $Page->system_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system_name->cellAttributes() ?>>
<span id="el_systems_system_name">
<input type="<?= $Page->system_name->getInputTextType() ?>" name="x_system_name" id="x_system_name" data-table="systems" data-field="x_system_name" value="<?= $Page->system_name->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->system_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->system_name->formatPattern()) ?>"<?= $Page->system_name->editAttributes() ?> aria-describedby="x_system_name_help">
<?= $Page->system_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->system_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->system_code->Visible) { // system_code ?>
    <div id="r_system_code"<?= $Page->system_code->rowAttributes() ?>>
        <label id="elh_systems_system_code" for="x_system_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system_code->caption() ?><?= $Page->system_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system_code->cellAttributes() ?>>
<span id="el_systems_system_code">
<input type="<?= $Page->system_code->getInputTextType() ?>" name="x_system_code" id="x_system_code" data-table="systems" data-field="x_system_code" value="<?= $Page->system_code->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->system_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->system_code->formatPattern()) ?>"<?= $Page->system_code->editAttributes() ?> aria-describedby="x_system_code_help">
<?= $Page->system_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->system_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->description->Visible) { // description ?>
    <div id="r_description"<?= $Page->description->rowAttributes() ?>>
        <label id="elh_systems_description" for="x_description" class="<?= $Page->LeftColumnClass ?>"><?= $Page->description->caption() ?><?= $Page->description->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->description->cellAttributes() ?>>
<span id="el_systems_description">
<textarea data-table="systems" data-field="x_description" name="x_description" id="x_description" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->description->getPlaceHolder()) ?>"<?= $Page->description->editAttributes() ?> aria-describedby="x_description_help"><?= $Page->description->EditValue ?></textarea>
<?= $Page->description->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->description->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->level_permissions->Visible) { // level_permissions ?>
    <div id="r_level_permissions"<?= $Page->level_permissions->rowAttributes() ?>>
        <label id="elh_systems_level_permissions" for="x_level_permissions" class="<?= $Page->LeftColumnClass ?>"><?= $Page->level_permissions->caption() ?><?= $Page->level_permissions->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->level_permissions->cellAttributes() ?>>
<span id="el_systems_level_permissions">
<textarea data-table="systems" data-field="x_level_permissions" name="x_level_permissions" id="x_level_permissions" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->level_permissions->getPlaceHolder()) ?>"<?= $Page->level_permissions->editAttributes() ?> aria-describedby="x_level_permissions_help"><?= $Page->level_permissions->EditValue ?></textarea>
<?= $Page->level_permissions->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->level_permissions->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_systems_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_systems_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="systems" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->url->Visible) { // url ?>
    <div id="r_url"<?= $Page->url->rowAttributes() ?>>
        <label id="elh_systems_url" for="x_url" class="<?= $Page->LeftColumnClass ?>"><?= $Page->url->caption() ?><?= $Page->url->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->url->cellAttributes() ?>>
<span id="el_systems_url">
<input type="<?= $Page->url->getInputTextType() ?>" name="x_url" id="x_url" data-table="systems" data-field="x_url" value="<?= $Page->url->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->url->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->url->formatPattern()) ?>"<?= $Page->url->editAttributes() ?> aria-describedby="x_url_help">
<?= $Page->url->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->url->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?php
    if (in_array("_user_levels", explode(",", $Page->getCurrentDetailTable())) && $_user_levels->DetailAdd) {
?>
<?php if ($Page->getCurrentDetailTable() != "") { ?>
<h4 class="ew-detail-caption"><?= $Language->tablePhrase("_user_levels", "TblCaption") ?></h4>
<?php } ?>
<?php include_once "UserLevelsGrid.php" ?>
<?php } ?>
<?php
    if (in_array("user_level_assignments", explode(",", $Page->getCurrentDetailTable())) && $user_level_assignments->DetailAdd) {
?>
<?php if ($Page->getCurrentDetailTable() != "") { ?>
<h4 class="ew-detail-caption"><?= $Language->tablePhrase("user_level_assignments", "TblCaption") ?></h4>
<?php } ?>
<?php include_once "UserLevelAssignmentsGrid.php" ?>
<?php } ?>
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fsystemsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fsystemsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("systems");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
