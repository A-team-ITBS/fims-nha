<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderSlotsAdd = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_slots: currentTable } });
var currentPageID = ew.PAGE_ID = "add";
var currentForm;
var fdocument_header_slotsadd;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_header_slotsadd")
        .setPageId("add")

        // Add fields
        .setFields([
            ["slot_code", [fields.slot_code.visible && fields.slot_code.required ? ew.Validators.required(fields.slot_code.caption) : null], fields.slot_code.isInvalid],
            ["slot_name", [fields.slot_name.visible && fields.slot_name.required ? ew.Validators.required(fields.slot_name.caption) : null], fields.slot_name.isInvalid],
            ["part_kind", [fields.part_kind.visible && fields.part_kind.required ? ew.Validators.required(fields.part_kind.caption) : null], fields.part_kind.isInvalid],
            ["display_order", [fields.display_order.visible && fields.display_order.required ? ew.Validators.required(fields.display_order.caption) : null, ew.Validators.integer], fields.display_order.isInvalid],
            ["default_column_pct", [fields.default_column_pct.visible && fields.default_column_pct.required ? ew.Validators.required(fields.default_column_pct.caption) : null, ew.Validators.float], fields.default_column_pct.isInvalid],
            ["default_render_width", [fields.default_render_width.visible && fields.default_render_width.required ? ew.Validators.required(fields.default_render_width.caption) : null, ew.Validators.integer], fields.default_render_width.isInvalid],
            ["default_render_height", [fields.default_render_height.visible && fields.default_render_height.required ? ew.Validators.required(fields.default_render_height.caption) : null, ew.Validators.integer], fields.default_render_height.isInvalid],
            ["default_align", [fields.default_align.visible && fields.default_align.required ? ew.Validators.required(fields.default_align.caption) : null], fields.default_align.isInvalid],
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
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<form name="fdocument_header_slotsadd" id="fdocument_header_slotsadd" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="document_header_slots">
<input type="hidden" name="action" id="action" value="insert">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-add-div"><!-- page* -->
<?php if ($Page->slot_code->Visible) { // slot_code ?>
    <div id="r_slot_code"<?= $Page->slot_code->rowAttributes() ?>>
        <label id="elh_document_header_slots_slot_code" for="x_slot_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->slot_code->caption() ?><?= $Page->slot_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->slot_code->cellAttributes() ?>>
<span id="el_document_header_slots_slot_code">
<input type="<?= $Page->slot_code->getInputTextType() ?>" name="x_slot_code" id="x_slot_code" data-table="document_header_slots" data-field="x_slot_code" value="<?= $Page->slot_code->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->slot_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->slot_code->formatPattern()) ?>"<?= $Page->slot_code->editAttributes() ?> aria-describedby="x_slot_code_help">
<?= $Page->slot_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->slot_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->slot_name->Visible) { // slot_name ?>
    <div id="r_slot_name"<?= $Page->slot_name->rowAttributes() ?>>
        <label id="elh_document_header_slots_slot_name" for="x_slot_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->slot_name->caption() ?><?= $Page->slot_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->slot_name->cellAttributes() ?>>
<span id="el_document_header_slots_slot_name">
<input type="<?= $Page->slot_name->getInputTextType() ?>" name="x_slot_name" id="x_slot_name" data-table="document_header_slots" data-field="x_slot_name" value="<?= $Page->slot_name->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->slot_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->slot_name->formatPattern()) ?>"<?= $Page->slot_name->editAttributes() ?> aria-describedby="x_slot_name_help">
<?= $Page->slot_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->slot_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
    <div id="r_part_kind"<?= $Page->part_kind->rowAttributes() ?>>
        <label id="elh_document_header_slots_part_kind" for="x_part_kind" class="<?= $Page->LeftColumnClass ?>"><?= $Page->part_kind->caption() ?><?= $Page->part_kind->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->part_kind->cellAttributes() ?>>
<span id="el_document_header_slots_part_kind">
<input type="<?= $Page->part_kind->getInputTextType() ?>" name="x_part_kind" id="x_part_kind" data-table="document_header_slots" data-field="x_part_kind" value="<?= $Page->part_kind->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->part_kind->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->part_kind->formatPattern()) ?>"<?= $Page->part_kind->editAttributes() ?> aria-describedby="x_part_kind_help">
<?= $Page->part_kind->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->part_kind->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->display_order->Visible) { // display_order ?>
    <div id="r_display_order"<?= $Page->display_order->rowAttributes() ?>>
        <label id="elh_document_header_slots_display_order" for="x_display_order" class="<?= $Page->LeftColumnClass ?>"><?= $Page->display_order->caption() ?><?= $Page->display_order->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->display_order->cellAttributes() ?>>
<span id="el_document_header_slots_display_order">
<input type="<?= $Page->display_order->getInputTextType() ?>" name="x_display_order" id="x_display_order" data-table="document_header_slots" data-field="x_display_order" value="<?= $Page->display_order->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->display_order->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->display_order->formatPattern()) ?>"<?= $Page->display_order->editAttributes() ?> aria-describedby="x_display_order_help">
<?= $Page->display_order->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->display_order->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->default_column_pct->Visible) { // default_column_pct ?>
    <div id="r_default_column_pct"<?= $Page->default_column_pct->rowAttributes() ?>>
        <label id="elh_document_header_slots_default_column_pct" for="x_default_column_pct" class="<?= $Page->LeftColumnClass ?>"><?= $Page->default_column_pct->caption() ?><?= $Page->default_column_pct->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->default_column_pct->cellAttributes() ?>>
<span id="el_document_header_slots_default_column_pct">
<input type="<?= $Page->default_column_pct->getInputTextType() ?>" name="x_default_column_pct" id="x_default_column_pct" data-table="document_header_slots" data-field="x_default_column_pct" value="<?= $Page->default_column_pct->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->default_column_pct->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->default_column_pct->formatPattern()) ?>"<?= $Page->default_column_pct->editAttributes() ?> aria-describedby="x_default_column_pct_help">
<?= $Page->default_column_pct->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->default_column_pct->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
    <div id="r_default_render_width"<?= $Page->default_render_width->rowAttributes() ?>>
        <label id="elh_document_header_slots_default_render_width" for="x_default_render_width" class="<?= $Page->LeftColumnClass ?>"><?= $Page->default_render_width->caption() ?><?= $Page->default_render_width->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->default_render_width->cellAttributes() ?>>
<span id="el_document_header_slots_default_render_width">
<input type="<?= $Page->default_render_width->getInputTextType() ?>" name="x_default_render_width" id="x_default_render_width" data-table="document_header_slots" data-field="x_default_render_width" value="<?= $Page->default_render_width->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->default_render_width->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->default_render_width->formatPattern()) ?>"<?= $Page->default_render_width->editAttributes() ?> aria-describedby="x_default_render_width_help">
<?= $Page->default_render_width->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->default_render_width->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
    <div id="r_default_render_height"<?= $Page->default_render_height->rowAttributes() ?>>
        <label id="elh_document_header_slots_default_render_height" for="x_default_render_height" class="<?= $Page->LeftColumnClass ?>"><?= $Page->default_render_height->caption() ?><?= $Page->default_render_height->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->default_render_height->cellAttributes() ?>>
<span id="el_document_header_slots_default_render_height">
<input type="<?= $Page->default_render_height->getInputTextType() ?>" name="x_default_render_height" id="x_default_render_height" data-table="document_header_slots" data-field="x_default_render_height" value="<?= $Page->default_render_height->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->default_render_height->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->default_render_height->formatPattern()) ?>"<?= $Page->default_render_height->editAttributes() ?> aria-describedby="x_default_render_height_help">
<?= $Page->default_render_height->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->default_render_height->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->default_align->Visible) { // default_align ?>
    <div id="r_default_align"<?= $Page->default_align->rowAttributes() ?>>
        <label id="elh_document_header_slots_default_align" for="x_default_align" class="<?= $Page->LeftColumnClass ?>"><?= $Page->default_align->caption() ?><?= $Page->default_align->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->default_align->cellAttributes() ?>>
<span id="el_document_header_slots_default_align">
<input type="<?= $Page->default_align->getInputTextType() ?>" name="x_default_align" id="x_default_align" data-table="document_header_slots" data-field="x_default_align" value="<?= $Page->default_align->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->default_align->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->default_align->formatPattern()) ?>"<?= $Page->default_align->editAttributes() ?> aria-describedby="x_default_align_help">
<?= $Page->default_align->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->default_align->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <div id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <label id="elh_document_header_slots_is_active" class="<?= $Page->LeftColumnClass ?>"><?= $Page->is_active->caption() ?><?= $Page->is_active->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->is_active->cellAttributes() ?>>
<span id="el_document_header_slots_is_active">
<div class="form-check d-inline-block">
    <input type="checkbox" class="form-check-input<?= $Page->is_active->isInvalidClass() ?>" data-table="document_header_slots" data-field="x_is_active" data-boolean name="x_is_active" id="x_is_active" value="1"<?= ConvertToBool($Page->is_active->CurrentValue) ? " checked" : "" ?><?= $Page->is_active->editAttributes() ?> aria-describedby="x_is_active_help">
    <div class="invalid-feedback"><?= $Page->is_active->getErrorMessage() ?></div>
</div>
<?= $Page->is_active->getCustomMessage() ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <div id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <label id="elh_document_header_slots_created_at" for="x_created_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->created_at->caption() ?><?= $Page->created_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->created_at->cellAttributes() ?>>
<span id="el_document_header_slots_created_at">
<input type="<?= $Page->created_at->getInputTextType() ?>" name="x_created_at" id="x_created_at" data-table="document_header_slots" data-field="x_created_at" value="<?= $Page->created_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->created_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->created_at->formatPattern()) ?>"<?= $Page->created_at->editAttributes() ?> aria-describedby="x_created_at_help">
<?= $Page->created_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->created_at->getErrorMessage() ?></div>
<?php if (!$Page->created_at->ReadOnly && !$Page->created_at->Disabled && !isset($Page->created_at->EditAttrs["readonly"]) && !isset($Page->created_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fdocument_header_slotsadd", "datetimepicker"], function () {
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
    ew.createDateTimePicker("fdocument_header_slotsadd", "x_created_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
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
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fdocument_header_slotsadd"><?= $Language->phrase("AddBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fdocument_header_slotsadd" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("document_header_slots");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
