<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentAssetTypesEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fdocument_asset_typesedit" id="fdocument_asset_typesedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_asset_types: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fdocument_asset_typesedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_asset_typesedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["document_asset_type_id", [fields.document_asset_type_id.visible && fields.document_asset_type_id.required ? ew.Validators.required(fields.document_asset_type_id.caption) : null], fields.document_asset_type_id.isInvalid],
            ["category", [fields.category.visible && fields.category.required ? ew.Validators.required(fields.category.caption) : null], fields.category.isInvalid],
            ["type_code", [fields.type_code.visible && fields.type_code.required ? ew.Validators.required(fields.type_code.caption) : null], fields.type_code.isInvalid],
            ["type_name", [fields.type_name.visible && fields.type_name.required ? ew.Validators.required(fields.type_name.caption) : null], fields.type_name.isInvalid],
            ["default_render_width", [fields.default_render_width.visible && fields.default_render_width.required ? ew.Validators.required(fields.default_render_width.caption) : null, ew.Validators.integer], fields.default_render_width.isInvalid],
            ["default_render_height", [fields.default_render_height.visible && fields.default_render_height.required ? ew.Validators.required(fields.default_render_height.caption) : null, ew.Validators.integer], fields.default_render_height.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
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
<input type="hidden" name="t" value="document_asset_types">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
    <div id="r_document_asset_type_id"<?= $Page->document_asset_type_id->rowAttributes() ?>>
        <label id="elh_document_asset_types_document_asset_type_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->document_asset_type_id->caption() ?><?= $Page->document_asset_type_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->document_asset_type_id->cellAttributes() ?>>
<span id="el_document_asset_types_document_asset_type_id">
<span<?= $Page->document_asset_type_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->document_asset_type_id->getDisplayValue($Page->document_asset_type_id->EditValue))) ?>"></span>
<input type="hidden" data-table="document_asset_types" data-field="x_document_asset_type_id" data-hidden="1" name="x_document_asset_type_id" id="x_document_asset_type_id" value="<?= HtmlEncode($Page->document_asset_type_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->category->Visible) { // category ?>
    <div id="r_category"<?= $Page->category->rowAttributes() ?>>
        <label id="elh_document_asset_types_category" for="x_category" class="<?= $Page->LeftColumnClass ?>"><?= $Page->category->caption() ?><?= $Page->category->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->category->cellAttributes() ?>>
<span id="el_document_asset_types_category">
<input type="<?= $Page->category->getInputTextType() ?>" name="x_category" id="x_category" data-table="document_asset_types" data-field="x_category" value="<?= $Page->category->EditValue ?>" size="30" maxlength="50" placeholder="<?= HtmlEncode($Page->category->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->category->formatPattern()) ?>"<?= $Page->category->editAttributes() ?> aria-describedby="x_category_help">
<?= $Page->category->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->category->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->type_code->Visible) { // type_code ?>
    <div id="r_type_code"<?= $Page->type_code->rowAttributes() ?>>
        <label id="elh_document_asset_types_type_code" for="x_type_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->type_code->caption() ?><?= $Page->type_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->type_code->cellAttributes() ?>>
<span id="el_document_asset_types_type_code">
<input type="<?= $Page->type_code->getInputTextType() ?>" name="x_type_code" id="x_type_code" data-table="document_asset_types" data-field="x_type_code" value="<?= $Page->type_code->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->type_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->type_code->formatPattern()) ?>"<?= $Page->type_code->editAttributes() ?> aria-describedby="x_type_code_help">
<?= $Page->type_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->type_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->type_name->Visible) { // type_name ?>
    <div id="r_type_name"<?= $Page->type_name->rowAttributes() ?>>
        <label id="elh_document_asset_types_type_name" for="x_type_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->type_name->caption() ?><?= $Page->type_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->type_name->cellAttributes() ?>>
<span id="el_document_asset_types_type_name">
<input type="<?= $Page->type_name->getInputTextType() ?>" name="x_type_name" id="x_type_name" data-table="document_asset_types" data-field="x_type_name" value="<?= $Page->type_name->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->type_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->type_name->formatPattern()) ?>"<?= $Page->type_name->editAttributes() ?> aria-describedby="x_type_name_help">
<?= $Page->type_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->type_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
    <div id="r_default_render_width"<?= $Page->default_render_width->rowAttributes() ?>>
        <label id="elh_document_asset_types_default_render_width" for="x_default_render_width" class="<?= $Page->LeftColumnClass ?>"><?= $Page->default_render_width->caption() ?><?= $Page->default_render_width->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->default_render_width->cellAttributes() ?>>
<span id="el_document_asset_types_default_render_width">
<input type="<?= $Page->default_render_width->getInputTextType() ?>" name="x_default_render_width" id="x_default_render_width" data-table="document_asset_types" data-field="x_default_render_width" value="<?= $Page->default_render_width->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->default_render_width->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->default_render_width->formatPattern()) ?>"<?= $Page->default_render_width->editAttributes() ?> aria-describedby="x_default_render_width_help">
<?= $Page->default_render_width->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->default_render_width->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
    <div id="r_default_render_height"<?= $Page->default_render_height->rowAttributes() ?>>
        <label id="elh_document_asset_types_default_render_height" for="x_default_render_height" class="<?= $Page->LeftColumnClass ?>"><?= $Page->default_render_height->caption() ?><?= $Page->default_render_height->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->default_render_height->cellAttributes() ?>>
<span id="el_document_asset_types_default_render_height">
<input type="<?= $Page->default_render_height->getInputTextType() ?>" name="x_default_render_height" id="x_default_render_height" data-table="document_asset_types" data-field="x_default_render_height" value="<?= $Page->default_render_height->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->default_render_height->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->default_render_height->formatPattern()) ?>"<?= $Page->default_render_height->editAttributes() ?> aria-describedby="x_default_render_height_help">
<?= $Page->default_render_height->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->default_render_height->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_document_asset_types_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_document_asset_types_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="document_asset_types" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_document_asset_types_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_document_asset_types_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="document_asset_types" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fdocument_asset_typesedit", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fdocument_asset_typesedit", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fdocument_asset_typesedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fdocument_asset_typesedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("document_asset_types");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
