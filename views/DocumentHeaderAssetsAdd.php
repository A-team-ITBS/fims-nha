<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderAssetsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_assets: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fdocument_header_assetsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_header_assetsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["asset_code", [fields.asset_code.visible && fields.asset_code.required ? ew.Validators.required(fields.asset_code.caption) : null], fields.asset_code.isInvalid],
            ["asset_name", [fields.asset_name.visible && fields.asset_name.required ? ew.Validators.required(fields.asset_name.caption) : null], fields.asset_name.isInvalid],
            ["document_asset_type_id", [fields.document_asset_type_id.visible && fields.document_asset_type_id.required ? ew.Validators.required(fields.document_asset_type_id.caption) : null, ew.Validators.integer], fields.document_asset_type_id.isInvalid],
            ["scope_system_code", [fields.scope_system_code.visible && fields.scope_system_code.required ? ew.Validators.required(fields.scope_system_code.caption) : null], fields.scope_system_code.isInvalid],
            ["scope_document_code", [fields.scope_document_code.visible && fields.scope_document_code.required ? ew.Validators.required(fields.scope_document_code.caption) : null], fields.scope_document_code.isInvalid],
            ["file_name", [fields.file_name.visible && fields.file_name.required ? ew.Validators.required(fields.file_name.caption) : null], fields.file_name.isInvalid],
            ["mime_type", [fields.mime_type.visible && fields.mime_type.required ? ew.Validators.required(fields.mime_type.caption) : null], fields.mime_type.isInvalid],
            ["file_size", [fields.file_size.visible && fields.file_size.required ? ew.Validators.required(fields.file_size.caption) : null, ew.Validators.integer], fields.file_size.isInvalid],
            ["checksum_sha256", [fields.checksum_sha256.visible && fields.checksum_sha256.required ? ew.Validators.required(fields.checksum_sha256.caption) : null], fields.checksum_sha256.isInvalid],
            ["image_width", [fields.image_width.visible && fields.image_width.required ? ew.Validators.required(fields.image_width.caption) : null, ew.Validators.integer], fields.image_width.isInvalid],
            ["image_height", [fields.image_height.visible && fields.image_height.required ? ew.Validators.required(fields.image_height.caption) : null, ew.Validators.integer], fields.image_height.isInvalid],
            ["render_width", [fields.render_width.visible && fields.render_width.required ? ew.Validators.required(fields.render_width.caption) : null, ew.Validators.integer], fields.render_width.isInvalid],
            ["render_height", [fields.render_height.visible && fields.render_height.required ? ew.Validators.required(fields.render_height.caption) : null, ew.Validators.integer], fields.render_height.isInvalid],
            ["image_data", [fields.image_data.visible && fields.image_data.required ? ew.Validators.required(fields.image_data.caption) : null], fields.image_data.isInvalid],
            ["is_active", [fields.is_active.visible && fields.is_active.required ? ew.Validators.required(fields.is_active.caption) : null], fields.is_active.isInvalid],
            ["notes", [fields.notes.visible && fields.notes.required ? ew.Validators.required(fields.notes.caption) : null], fields.notes.isInvalid],
            ["created_at", [fields.created_at.visible && fields.created_at.required ? ew.Validators.required(fields.created_at.caption) : null, ew.Validators.datetime(fields.created_at.clientFormatPattern)], fields.created_at.isInvalid],
            ["updated_at", [fields.updated_at.visible && fields.updated_at.required ? ew.Validators.required(fields.updated_at.caption) : null, ew.Validators.datetime(fields.updated_at.clientFormatPattern)], fields.updated_at.isInvalid],
            ["created_by", [fields.created_by.visible && fields.created_by.required ? ew.Validators.required(fields.created_by.caption) : null, ew.Validators.integer], fields.created_by.isInvalid],
            ["updated_by", [fields.updated_by.visible && fields.updated_by.required ? ew.Validators.required(fields.updated_by.caption) : null, ew.Validators.integer], fields.updated_by.isInvalid],
            ["header_slot_id", [fields.header_slot_id.visible && fields.header_slot_id.required ? ew.Validators.required(fields.header_slot_id.caption) : null, ew.Validators.integer], fields.header_slot_id.isInvalid],
            ["part_kind", [fields.part_kind.visible && fields.part_kind.required ? ew.Validators.required(fields.part_kind.caption) : null], fields.part_kind.isInvalid],
            ["column_pct", [fields.column_pct.visible && fields.column_pct.required ? ew.Validators.required(fields.column_pct.caption) : null, ew.Validators.float], fields.column_pct.isInvalid],
            ["align", [fields.align.visible && fields.align.required ? ew.Validators.required(fields.align.caption) : null], fields.align.isInvalid],
            ["text_line1", [fields.text_line1.visible && fields.text_line1.required ? ew.Validators.required(fields.text_line1.caption) : null], fields.text_line1.isInvalid],
            ["text_line2", [fields.text_line2.visible && fields.text_line2.required ? ew.Validators.required(fields.text_line2.caption) : null], fields.text_line2.isInvalid],
            ["font_name", [fields.font_name.visible && fields.font_name.required ? ew.Validators.required(fields.font_name.caption) : null], fields.font_name.isInvalid],
            ["line1_size_pt", [fields.line1_size_pt.visible && fields.line1_size_pt.required ? ew.Validators.required(fields.line1_size_pt.caption) : null, ew.Validators.float], fields.line1_size_pt.isInvalid],
            ["line2_size_pt", [fields.line2_size_pt.visible && fields.line2_size_pt.required ? ew.Validators.required(fields.line2_size_pt.caption) : null, ew.Validators.float], fields.line2_size_pt.isInvalid],
            ["line1_bold", [fields.line1_bold.visible && fields.line1_bold.required ? ew.Validators.required(fields.line1_bold.caption) : null], fields.line1_bold.isInvalid],
            ["line2_bold", [fields.line2_bold.visible && fields.line2_bold.required ? ew.Validators.required(fields.line2_bold.caption) : null], fields.line2_bold.isInvalid],
            ["line1_color", [fields.line1_color.visible && fields.line1_color.required ? ew.Validators.required(fields.line1_color.caption) : null], fields.line1_color.isInvalid],
            ["line2_color", [fields.line2_color.visible && fields.line2_color.required ? ew.Validators.required(fields.line2_color.caption) : null], fields.line2_color.isInvalid]
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
            "line1_bold": <?= $Page->line1_bold->toClientList($Page) ?>,
            "line2_bold": <?= $Page->line2_bold->toClientList($Page) ?>,
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
<form name="fdocument_header_assetsadd" id="fdocument_header_assetsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="document_header_assets">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->asset_code->Visible) { // asset_code ?>
    <div id="r_asset_code"<?= $Page->asset_code->rowAttributes() ?>>
        <label id="elh_document_header_assets_asset_code" for="x_asset_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->asset_code->caption() ?><?= $Page->asset_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->asset_code->cellAttributes() ?>>
<span id="el_document_header_assets_asset_code">
<input type="<?= $Page->asset_code->getInputTextType() ?>" name="x_asset_code" id="x_asset_code" data-table="document_header_assets" data-field="x_asset_code" value="<?= $Page->asset_code->EditValue ?>" size="30" maxlength="40" placeholder="<?= HtmlEncode($Page->asset_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->asset_code->formatPattern()) ?>"<?= $Page->asset_code->editAttributes() ?> aria-describedby="x_asset_code_help">
<?= $Page->asset_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->asset_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->asset_name->Visible) { // asset_name ?>
    <div id="r_asset_name"<?= $Page->asset_name->rowAttributes() ?>>
        <label id="elh_document_header_assets_asset_name" for="x_asset_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->asset_name->caption() ?><?= $Page->asset_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->asset_name->cellAttributes() ?>>
<span id="el_document_header_assets_asset_name">
<input type="<?= $Page->asset_name->getInputTextType() ?>" name="x_asset_name" id="x_asset_name" data-table="document_header_assets" data-field="x_asset_name" value="<?= $Page->asset_name->EditValue ?>" size="30" maxlength="150" placeholder="<?= HtmlEncode($Page->asset_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->asset_name->formatPattern()) ?>"<?= $Page->asset_name->editAttributes() ?> aria-describedby="x_asset_name_help">
<?= $Page->asset_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->asset_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->document_asset_type_id->Visible) { // document_asset_type_id ?>
    <div id="r_document_asset_type_id"<?= $Page->document_asset_type_id->rowAttributes() ?>>
        <label id="elh_document_header_assets_document_asset_type_id" for="x_document_asset_type_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->document_asset_type_id->caption() ?><?= $Page->document_asset_type_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->document_asset_type_id->cellAttributes() ?>>
<span id="el_document_header_assets_document_asset_type_id">
<input type="<?= $Page->document_asset_type_id->getInputTextType() ?>" name="x_document_asset_type_id" id="x_document_asset_type_id" data-table="document_header_assets" data-field="x_document_asset_type_id" value="<?= $Page->document_asset_type_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->document_asset_type_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->document_asset_type_id->formatPattern()) ?>"<?= $Page->document_asset_type_id->editAttributes() ?> aria-describedby="x_document_asset_type_id_help">
<?= $Page->document_asset_type_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->document_asset_type_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->scope_system_code->Visible) { // scope_system_code ?>
    <div id="r_scope_system_code"<?= $Page->scope_system_code->rowAttributes() ?>>
        <label id="elh_document_header_assets_scope_system_code" for="x_scope_system_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->scope_system_code->caption() ?><?= $Page->scope_system_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->scope_system_code->cellAttributes() ?>>
<span id="el_document_header_assets_scope_system_code">
<input type="<?= $Page->scope_system_code->getInputTextType() ?>" name="x_scope_system_code" id="x_scope_system_code" data-table="document_header_assets" data-field="x_scope_system_code" value="<?= $Page->scope_system_code->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->scope_system_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->scope_system_code->formatPattern()) ?>"<?= $Page->scope_system_code->editAttributes() ?> aria-describedby="x_scope_system_code_help">
<?= $Page->scope_system_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->scope_system_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->scope_document_code->Visible) { // scope_document_code ?>
    <div id="r_scope_document_code"<?= $Page->scope_document_code->rowAttributes() ?>>
        <label id="elh_document_header_assets_scope_document_code" for="x_scope_document_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->scope_document_code->caption() ?><?= $Page->scope_document_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->scope_document_code->cellAttributes() ?>>
<span id="el_document_header_assets_scope_document_code">
<input type="<?= $Page->scope_document_code->getInputTextType() ?>" name="x_scope_document_code" id="x_scope_document_code" data-table="document_header_assets" data-field="x_scope_document_code" value="<?= $Page->scope_document_code->EditValue ?>" size="30" maxlength="30" placeholder="<?= HtmlEncode($Page->scope_document_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->scope_document_code->formatPattern()) ?>"<?= $Page->scope_document_code->editAttributes() ?> aria-describedby="x_scope_document_code_help">
<?= $Page->scope_document_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->scope_document_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->file_name->Visible) { // file_name ?>
    <div id="r_file_name"<?= $Page->file_name->rowAttributes() ?>>
        <label id="elh_document_header_assets_file_name" for="x_file_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->file_name->caption() ?><?= $Page->file_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->file_name->cellAttributes() ?>>
<span id="el_document_header_assets_file_name">
<input type="<?= $Page->file_name->getInputTextType() ?>" name="x_file_name" id="x_file_name" data-table="document_header_assets" data-field="x_file_name" value="<?= $Page->file_name->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->file_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->file_name->formatPattern()) ?>"<?= $Page->file_name->editAttributes() ?> aria-describedby="x_file_name_help">
<?= $Page->file_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->file_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->mime_type->Visible) { // mime_type ?>
    <div id="r_mime_type"<?= $Page->mime_type->rowAttributes() ?>>
        <label id="elh_document_header_assets_mime_type" for="x_mime_type" class="<?= $Page->LeftColumnClass ?>"><?= $Page->mime_type->caption() ?><?= $Page->mime_type->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->mime_type->cellAttributes() ?>>
<span id="el_document_header_assets_mime_type">
<input type="<?= $Page->mime_type->getInputTextType() ?>" name="x_mime_type" id="x_mime_type" data-table="document_header_assets" data-field="x_mime_type" value="<?= $Page->mime_type->EditValue ?>" size="30" maxlength="60" placeholder="<?= HtmlEncode($Page->mime_type->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->mime_type->formatPattern()) ?>"<?= $Page->mime_type->editAttributes() ?> aria-describedby="x_mime_type_help">
<?= $Page->mime_type->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->mime_type->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->file_size->Visible) { // file_size ?>
    <div id="r_file_size"<?= $Page->file_size->rowAttributes() ?>>
        <label id="elh_document_header_assets_file_size" for="x_file_size" class="<?= $Page->LeftColumnClass ?>"><?= $Page->file_size->caption() ?><?= $Page->file_size->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->file_size->cellAttributes() ?>>
<span id="el_document_header_assets_file_size">
<input type="<?= $Page->file_size->getInputTextType() ?>" name="x_file_size" id="x_file_size" data-table="document_header_assets" data-field="x_file_size" value="<?= $Page->file_size->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->file_size->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->file_size->formatPattern()) ?>"<?= $Page->file_size->editAttributes() ?> aria-describedby="x_file_size_help">
<?= $Page->file_size->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->file_size->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->checksum_sha256->Visible) { // checksum_sha256 ?>
    <div id="r_checksum_sha256"<?= $Page->checksum_sha256->rowAttributes() ?>>
        <label id="elh_document_header_assets_checksum_sha256" for="x_checksum_sha256" class="<?= $Page->LeftColumnClass ?>"><?= $Page->checksum_sha256->caption() ?><?= $Page->checksum_sha256->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->checksum_sha256->cellAttributes() ?>>
<span id="el_document_header_assets_checksum_sha256">
<input type="<?= $Page->checksum_sha256->getInputTextType() ?>" name="x_checksum_sha256" id="x_checksum_sha256" data-table="document_header_assets" data-field="x_checksum_sha256" value="<?= $Page->checksum_sha256->EditValue ?>" size="30" maxlength="64" placeholder="<?= HtmlEncode($Page->checksum_sha256->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->checksum_sha256->formatPattern()) ?>"<?= $Page->checksum_sha256->editAttributes() ?> aria-describedby="x_checksum_sha256_help">
<?= $Page->checksum_sha256->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->checksum_sha256->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->image_width->Visible) { // image_width ?>
    <div id="r_image_width"<?= $Page->image_width->rowAttributes() ?>>
        <label id="elh_document_header_assets_image_width" for="x_image_width" class="<?= $Page->LeftColumnClass ?>"><?= $Page->image_width->caption() ?><?= $Page->image_width->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->image_width->cellAttributes() ?>>
<span id="el_document_header_assets_image_width">
<input type="<?= $Page->image_width->getInputTextType() ?>" name="x_image_width" id="x_image_width" data-table="document_header_assets" data-field="x_image_width" value="<?= $Page->image_width->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->image_width->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->image_width->formatPattern()) ?>"<?= $Page->image_width->editAttributes() ?> aria-describedby="x_image_width_help">
<?= $Page->image_width->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->image_width->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->image_height->Visible) { // image_height ?>
    <div id="r_image_height"<?= $Page->image_height->rowAttributes() ?>>
        <label id="elh_document_header_assets_image_height" for="x_image_height" class="<?= $Page->LeftColumnClass ?>"><?= $Page->image_height->caption() ?><?= $Page->image_height->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->image_height->cellAttributes() ?>>
<span id="el_document_header_assets_image_height">
<input type="<?= $Page->image_height->getInputTextType() ?>" name="x_image_height" id="x_image_height" data-table="document_header_assets" data-field="x_image_height" value="<?= $Page->image_height->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->image_height->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->image_height->formatPattern()) ?>"<?= $Page->image_height->editAttributes() ?> aria-describedby="x_image_height_help">
<?= $Page->image_height->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->image_height->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->render_width->Visible) { // render_width ?>
    <div id="r_render_width"<?= $Page->render_width->rowAttributes() ?>>
        <label id="elh_document_header_assets_render_width" for="x_render_width" class="<?= $Page->LeftColumnClass ?>"><?= $Page->render_width->caption() ?><?= $Page->render_width->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->render_width->cellAttributes() ?>>
<span id="el_document_header_assets_render_width">
<input type="<?= $Page->render_width->getInputTextType() ?>" name="x_render_width" id="x_render_width" data-table="document_header_assets" data-field="x_render_width" value="<?= $Page->render_width->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->render_width->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->render_width->formatPattern()) ?>"<?= $Page->render_width->editAttributes() ?> aria-describedby="x_render_width_help">
<?= $Page->render_width->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->render_width->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->render_height->Visible) { // render_height ?>
    <div id="r_render_height"<?= $Page->render_height->rowAttributes() ?>>
        <label id="elh_document_header_assets_render_height" for="x_render_height" class="<?= $Page->LeftColumnClass ?>"><?= $Page->render_height->caption() ?><?= $Page->render_height->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->render_height->cellAttributes() ?>>
<span id="el_document_header_assets_render_height">
<input type="<?= $Page->render_height->getInputTextType() ?>" name="x_render_height" id="x_render_height" data-table="document_header_assets" data-field="x_render_height" value="<?= $Page->render_height->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->render_height->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->render_height->formatPattern()) ?>"<?= $Page->render_height->editAttributes() ?> aria-describedby="x_render_height_help">
<?= $Page->render_height->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->render_height->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->image_data->Visible) { // image_data ?>
    <div id="r_image_data"<?= $Page->image_data->rowAttributes() ?>>
        <label id="elh_document_header_assets_image_data" for="x_image_data" class="<?= $Page->LeftColumnClass ?>"><?= $Page->image_data->caption() ?><?= $Page->image_data->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->image_data->cellAttributes() ?>>
<span id="el_document_header_assets_image_data">
<textarea data-table="document_header_assets" data-field="x_image_data" name="x_image_data" id="x_image_data" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->image_data->getPlaceHolder()) ?>"<?= $Page->image_data->editAttributes() ?> aria-describedby="x_image_data_help"><?= $Page->image_data->EditValue ?></textarea>
<?= $Page->image_data->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->image_data->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_document_header_assets_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_document_header_assets_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="document_header_assets" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->notes->Visible) { // notes ?>
    <div id="r_notes"<?= $Page->notes->rowAttributes() ?>>
        <label id="elh_document_header_assets_notes" for="x_notes" class="<?= $Page->LeftColumnClass ?>"><?= $Page->notes->caption() ?><?= $Page->notes->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->notes->cellAttributes() ?>>
<span id="el_document_header_assets_notes">
<textarea data-table="document_header_assets" data-field="x_notes" name="x_notes" id="x_notes" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->notes->getPlaceHolder()) ?>"<?= $Page->notes->editAttributes() ?> aria-describedby="x_notes_help"><?= $Page->notes->EditValue ?></textarea>
<?= $Page->notes->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->notes->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_document_header_assets_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_document_header_assets_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="document_header_assets" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fdocument_header_assetsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fdocument_header_assetsadd", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <div id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <label id="elh_document_header_assets_updated_at" for="x_updated_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_at->caption() ?><?= $Page->updated_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_document_header_assets_updated_at">
<input type="<?= $Page->updated_at->getInputTextType() ?>" name="x_updated_at" id="x_updated_at" data-table="document_header_assets" data-field="x_updated_at" value="<?= $Page->updated_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->updated_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_at->formatPattern()) ?>"<?= $Page->updated_at->editAttributes() ?> aria-describedby="x_updated_at_help">
<?= $Page->updated_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_at->getErrorMessage() ?></div>
<?php if (!$Page->updated_at->ReadOnly && !$Page->updated_at->Disabled && !isset($Page->updated_at->EditAttrs["readonly"]) && !isset($Page->updated_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fdocument_header_assetsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fdocument_header_assetsadd", "x_updated_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <div id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <label id="elh_document_header_assets_created_by" for="x_created_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_by->caption() ?><?= $Page->created_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_by->cellAttributes() ?>>
<span id="el_document_header_assets_created_by">
<input type="<?= $Page->created_by->getInputTextType() ?>" name="x_created_by" id="x_created_by" data-table="document_header_assets" data-field="x_created_by" value="<?= $Page->created_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->created_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_by->formatPattern()) ?>"<?= $Page->created_by->editAttributes() ?> aria-describedby="x_created_by_help">
<?= $Page->created_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->updated_by->Visible) { // updated_by ?>
    <div id="r_updated_by"<?= $Page->updated_by->rowAttributes() ?>>
        <label id="elh_document_header_assets_updated_by" for="x_updated_by" class="<?= $Page->LeftColumnClass ?>"><?= $Page->updated_by->caption() ?><?= $Page->updated_by->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->updated_by->cellAttributes() ?>>
<span id="el_document_header_assets_updated_by">
<input type="<?= $Page->updated_by->getInputTextType() ?>" name="x_updated_by" id="x_updated_by" data-table="document_header_assets" data-field="x_updated_by" value="<?= $Page->updated_by->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->updated_by->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->updated_by->formatPattern()) ?>"<?= $Page->updated_by->editAttributes() ?> aria-describedby="x_updated_by_help">
<?= $Page->updated_by->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->updated_by->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
    <div id="r_header_slot_id"<?= $Page->header_slot_id->rowAttributes() ?>>
        <label id="elh_document_header_assets_header_slot_id" for="x_header_slot_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->header_slot_id->caption() ?><?= $Page->header_slot_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->header_slot_id->cellAttributes() ?>>
<span id="el_document_header_assets_header_slot_id">
<input type="<?= $Page->header_slot_id->getInputTextType() ?>" name="x_header_slot_id" id="x_header_slot_id" data-table="document_header_assets" data-field="x_header_slot_id" value="<?= $Page->header_slot_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->header_slot_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->header_slot_id->formatPattern()) ?>"<?= $Page->header_slot_id->editAttributes() ?> aria-describedby="x_header_slot_id_help">
<?= $Page->header_slot_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->header_slot_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
    <div id="r_part_kind"<?= $Page->part_kind->rowAttributes() ?>>
        <label id="elh_document_header_assets_part_kind" for="x_part_kind" class="<?= $Page->LeftColumnClass ?>"><?= $Page->part_kind->caption() ?><?= $Page->part_kind->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->part_kind->cellAttributes() ?>>
<span id="el_document_header_assets_part_kind">
<input type="<?= $Page->part_kind->getInputTextType() ?>" name="x_part_kind" id="x_part_kind" data-table="document_header_assets" data-field="x_part_kind" value="<?= $Page->part_kind->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->part_kind->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->part_kind->formatPattern()) ?>"<?= $Page->part_kind->editAttributes() ?> aria-describedby="x_part_kind_help">
<?= $Page->part_kind->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->part_kind->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->column_pct->Visible) { // column_pct ?>
    <div id="r_column_pct"<?= $Page->column_pct->rowAttributes() ?>>
        <label id="elh_document_header_assets_column_pct" for="x_column_pct" class="<?= $Page->LeftColumnClass ?>"><?= $Page->column_pct->caption() ?><?= $Page->column_pct->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->column_pct->cellAttributes() ?>>
<span id="el_document_header_assets_column_pct">
<input type="<?= $Page->column_pct->getInputTextType() ?>" name="x_column_pct" id="x_column_pct" data-table="document_header_assets" data-field="x_column_pct" value="<?= $Page->column_pct->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->column_pct->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->column_pct->formatPattern()) ?>"<?= $Page->column_pct->editAttributes() ?> aria-describedby="x_column_pct_help">
<?= $Page->column_pct->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->column_pct->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->align->Visible) { // align ?>
    <div id="r_align"<?= $Page->align->rowAttributes() ?>>
        <label id="elh_document_header_assets_align" for="x_align" class="<?= $Page->LeftColumnClass ?>"><?= $Page->align->caption() ?><?= $Page->align->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->align->cellAttributes() ?>>
<span id="el_document_header_assets_align">
<input type="<?= $Page->align->getInputTextType() ?>" name="x_align" id="x_align" data-table="document_header_assets" data-field="x_align" value="<?= $Page->align->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->align->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->align->formatPattern()) ?>"<?= $Page->align->editAttributes() ?> aria-describedby="x_align_help">
<?= $Page->align->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->align->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->text_line1->Visible) { // text_line1 ?>
    <div id="r_text_line1"<?= $Page->text_line1->rowAttributes() ?>>
        <label id="elh_document_header_assets_text_line1" for="x_text_line1" class="<?= $Page->LeftColumnClass ?>"><?= $Page->text_line1->caption() ?><?= $Page->text_line1->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->text_line1->cellAttributes() ?>>
<span id="el_document_header_assets_text_line1">
<textarea data-table="document_header_assets" data-field="x_text_line1" name="x_text_line1" id="x_text_line1" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->text_line1->getPlaceHolder()) ?>"<?= $Page->text_line1->editAttributes() ?> aria-describedby="x_text_line1_help"><?= $Page->text_line1->EditValue ?></textarea>
<?= $Page->text_line1->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->text_line1->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->text_line2->Visible) { // text_line2 ?>
    <div id="r_text_line2"<?= $Page->text_line2->rowAttributes() ?>>
        <label id="elh_document_header_assets_text_line2" for="x_text_line2" class="<?= $Page->LeftColumnClass ?>"><?= $Page->text_line2->caption() ?><?= $Page->text_line2->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->text_line2->cellAttributes() ?>>
<span id="el_document_header_assets_text_line2">
<textarea data-table="document_header_assets" data-field="x_text_line2" name="x_text_line2" id="x_text_line2" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->text_line2->getPlaceHolder()) ?>"<?= $Page->text_line2->editAttributes() ?> aria-describedby="x_text_line2_help"><?= $Page->text_line2->EditValue ?></textarea>
<?= $Page->text_line2->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->text_line2->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->font_name->Visible) { // font_name ?>
    <div id="r_font_name"<?= $Page->font_name->rowAttributes() ?>>
        <label id="elh_document_header_assets_font_name" for="x_font_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->font_name->caption() ?><?= $Page->font_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->font_name->cellAttributes() ?>>
<span id="el_document_header_assets_font_name">
<input type="<?= $Page->font_name->getInputTextType() ?>" name="x_font_name" id="x_font_name" data-table="document_header_assets" data-field="x_font_name" value="<?= $Page->font_name->EditValue ?>" size="30" maxlength="60" placeholder="<?= HtmlEncode($Page->font_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->font_name->formatPattern()) ?>"<?= $Page->font_name->editAttributes() ?> aria-describedby="x_font_name_help">
<?= $Page->font_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->font_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->line1_size_pt->Visible) { // line1_size_pt ?>
    <div id="r_line1_size_pt"<?= $Page->line1_size_pt->rowAttributes() ?>>
        <label id="elh_document_header_assets_line1_size_pt" for="x_line1_size_pt" class="<?= $Page->LeftColumnClass ?>"><?= $Page->line1_size_pt->caption() ?><?= $Page->line1_size_pt->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->line1_size_pt->cellAttributes() ?>>
<span id="el_document_header_assets_line1_size_pt">
<input type="<?= $Page->line1_size_pt->getInputTextType() ?>" name="x_line1_size_pt" id="x_line1_size_pt" data-table="document_header_assets" data-field="x_line1_size_pt" value="<?= $Page->line1_size_pt->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->line1_size_pt->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->line1_size_pt->formatPattern()) ?>"<?= $Page->line1_size_pt->editAttributes() ?> aria-describedby="x_line1_size_pt_help">
<?= $Page->line1_size_pt->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->line1_size_pt->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->line2_size_pt->Visible) { // line2_size_pt ?>
    <div id="r_line2_size_pt"<?= $Page->line2_size_pt->rowAttributes() ?>>
        <label id="elh_document_header_assets_line2_size_pt" for="x_line2_size_pt" class="<?= $Page->LeftColumnClass ?>"><?= $Page->line2_size_pt->caption() ?><?= $Page->line2_size_pt->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->line2_size_pt->cellAttributes() ?>>
<span id="el_document_header_assets_line2_size_pt">
<input type="<?= $Page->line2_size_pt->getInputTextType() ?>" name="x_line2_size_pt" id="x_line2_size_pt" data-table="document_header_assets" data-field="x_line2_size_pt" value="<?= $Page->line2_size_pt->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->line2_size_pt->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->line2_size_pt->formatPattern()) ?>"<?= $Page->line2_size_pt->editAttributes() ?> aria-describedby="x_line2_size_pt_help">
<?= $Page->line2_size_pt->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->line2_size_pt->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->line1_bold->Visible) { // line1_bold ?>
    <div id="r_line1_bold"<?= $Page->line1_bold->rowAttributes() ?>>
        <label id="elh_document_header_assets_line1_bold" class="<?= $Page->LeftColumnClass ?>"><?= $Page->line1_bold->caption() ?><?= $Page->line1_bold->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->line1_bold->cellAttributes() ?>>
<span id="el_document_header_assets_line1_bold">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->line1_bold->isInvalidClass() ?>" data-table="document_header_assets" data-field="x_line1_bold" data-boolean name="x_line1_bold" id="x_line1_bold" value="1"<?= ConvertToBool($Page->line1_bold->CurrentValue) ? " checked" : "" ?><?= $Page->line1_bold->editAttributes() ?> aria-describedby="x_line1_bold_help">
    <div class="invalid-feedback"><?= $Page->line1_bold->getErrorMessage() ?></div>
</div>
<?= $Page->line1_bold->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->line2_bold->Visible) { // line2_bold ?>
    <div id="r_line2_bold"<?= $Page->line2_bold->rowAttributes() ?>>
        <label id="elh_document_header_assets_line2_bold" class="<?= $Page->LeftColumnClass ?>"><?= $Page->line2_bold->caption() ?><?= $Page->line2_bold->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->line2_bold->cellAttributes() ?>>
<span id="el_document_header_assets_line2_bold">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->line2_bold->isInvalidClass() ?>" data-table="document_header_assets" data-field="x_line2_bold" data-boolean name="x_line2_bold" id="x_line2_bold" value="1"<?= ConvertToBool($Page->line2_bold->CurrentValue) ? " checked" : "" ?><?= $Page->line2_bold->editAttributes() ?> aria-describedby="x_line2_bold_help">
    <div class="invalid-feedback"><?= $Page->line2_bold->getErrorMessage() ?></div>
</div>
<?= $Page->line2_bold->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->line1_color->Visible) { // line1_color ?>
    <div id="r_line1_color"<?= $Page->line1_color->rowAttributes() ?>>
        <label id="elh_document_header_assets_line1_color" for="x_line1_color" class="<?= $Page->LeftColumnClass ?>"><?= $Page->line1_color->caption() ?><?= $Page->line1_color->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->line1_color->cellAttributes() ?>>
<span id="el_document_header_assets_line1_color">
<input type="<?= $Page->line1_color->getInputTextType() ?>" name="x_line1_color" id="x_line1_color" data-table="document_header_assets" data-field="x_line1_color" value="<?= $Page->line1_color->EditValue ?>" size="30" maxlength="8" placeholder="<?= HtmlEncode($Page->line1_color->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->line1_color->formatPattern()) ?>"<?= $Page->line1_color->editAttributes() ?> aria-describedby="x_line1_color_help">
<?= $Page->line1_color->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->line1_color->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->line2_color->Visible) { // line2_color ?>
    <div id="r_line2_color"<?= $Page->line2_color->rowAttributes() ?>>
        <label id="elh_document_header_assets_line2_color" for="x_line2_color" class="<?= $Page->LeftColumnClass ?>"><?= $Page->line2_color->caption() ?><?= $Page->line2_color->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->line2_color->cellAttributes() ?>>
<span id="el_document_header_assets_line2_color">
<input type="<?= $Page->line2_color->getInputTextType() ?>" name="x_line2_color" id="x_line2_color" data-table="document_header_assets" data-field="x_line2_color" value="<?= $Page->line2_color->EditValue ?>" size="30" maxlength="8" placeholder="<?= HtmlEncode($Page->line2_color->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->line2_color->formatPattern()) ?>"<?= $Page->line2_color->editAttributes() ?> aria-describedby="x_line2_color_help">
<?= $Page->line2_color->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->line2_color->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fdocument_header_assetsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fdocument_header_assetsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("document_header_assets");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
