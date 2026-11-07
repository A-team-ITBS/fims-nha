<?php

namespace PHPMaker2024\FIMS;

// Page object
$UserLevelPermissionsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fuser_level_permissionsedit" id="fuser_level_permissionsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { user_level_permissions: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fuser_level_permissionsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fuser_level_permissionsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["user_level_id", [fields.user_level_id.visible && fields.user_level_id.required ? ew.Validators.required(fields.user_level_id.caption) : null, ew.Validators.integer], fields.user_level_id.isInvalid],
            ["table_name", [fields.table_name.visible && fields.table_name.required ? ew.Validators.required(fields.table_name.caption) : null], fields.table_name.isInvalid],
            ["_permission", [fields._permission.visible && fields._permission.required ? ew.Validators.required(fields._permission.caption) : null, ew.Validators.integer], fields._permission.isInvalid]
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
<input type="hidden" name="t" value="user_level_permissions">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->user_level_id->Visible) { // user_level_id ?>
    <div id="r_user_level_id"<?= $Page->user_level_id->rowAttributes() ?>>
        <label id="elh_user_level_permissions_user_level_id" for="x_user_level_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_level_id->caption() ?><?= $Page->user_level_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_level_id->cellAttributes() ?>>
<span id="el_user_level_permissions_user_level_id">
<input type="<?= $Page->user_level_id->getInputTextType() ?>" name="x_user_level_id" id="x_user_level_id" data-table="user_level_permissions" data-field="x_user_level_id" value="<?= $Page->user_level_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->user_level_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->user_level_id->formatPattern()) ?>"<?= $Page->user_level_id->editAttributes() ?> aria-describedby="x_user_level_id_help">
<?= $Page->user_level_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->user_level_id->getErrorMessage() ?></div>
<input type="hidden" data-table="user_level_permissions" data-field="x_user_level_id" data-hidden="1" data-old name="o_user_level_id" id="o_user_level_id" value="<?= HtmlEncode($Page->user_level_id->OldValue ?? $Page->user_level_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->table_name->Visible) { // table_name ?>
    <div id="r_table_name"<?= $Page->table_name->rowAttributes() ?>>
        <label id="elh_user_level_permissions_table_name" for="x_table_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->table_name->caption() ?><?= $Page->table_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->table_name->cellAttributes() ?>>
<span id="el_user_level_permissions_table_name">
<input type="<?= $Page->table_name->getInputTextType() ?>" name="x_table_name" id="x_table_name" data-table="user_level_permissions" data-field="x_table_name" value="<?= $Page->table_name->EditValue ?>" size="30" maxlength="80" placeholder="<?= HtmlEncode($Page->table_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->table_name->formatPattern()) ?>"<?= $Page->table_name->editAttributes() ?> aria-describedby="x_table_name_help">
<?= $Page->table_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->table_name->getErrorMessage() ?></div>
<input type="hidden" data-table="user_level_permissions" data-field="x_table_name" data-hidden="1" data-old name="o_table_name" id="o_table_name" value="<?= HtmlEncode($Page->table_name->OldValue ?? $Page->table_name->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->_permission->Visible) { // permission ?>
    <div id="r__permission"<?= $Page->_permission->rowAttributes() ?>>
        <label id="elh_user_level_permissions__permission" for="x__permission" class="<?= $Page->LeftColumnClass ?>"><?= $Page->_permission->caption() ?><?= $Page->_permission->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->_permission->cellAttributes() ?>>
<span id="el_user_level_permissions__permission">
<input type="<?= $Page->_permission->getInputTextType() ?>" name="x__permission" id="x__permission" data-table="user_level_permissions" data-field="x__permission" value="<?= $Page->_permission->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->_permission->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->_permission->formatPattern()) ?>"<?= $Page->_permission->editAttributes() ?> aria-describedby="x__permission_help">
<?= $Page->_permission->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->_permission->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fuser_level_permissionsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fuser_level_permissionsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("user_level_permissions");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
