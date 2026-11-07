<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderSlotsView = &$Page;
?>
<?php if (!$Page->isExport()) { ?>
<div class="btn-toolbar ew-toolbar">
<?php $Page->ExportOptions->render("body") ?>
<?php $Page->OtherOptions->render("body") ?>
</div>
<?php } ?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="view">
<form name="fdocument_header_slotsview" id="fdocument_header_slotsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_slots: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fdocument_header_slotsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_header_slotsview")
        .setPageId("view")
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
<?php } ?>
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="document_header_slots">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
    <tr id="r_header_slot_id"<?= $Page->header_slot_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_header_slot_id"><?= $Page->header_slot_id->caption() ?></span></td>
        <td data-name="header_slot_id"<?= $Page->header_slot_id->cellAttributes() ?>>
<span id="el_document_header_slots_header_slot_id">
<span<?= $Page->header_slot_id->viewAttributes() ?>>
<?= $Page->header_slot_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->slot_code->Visible) { // slot_code ?>
    <tr id="r_slot_code"<?= $Page->slot_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_slot_code"><?= $Page->slot_code->caption() ?></span></td>
        <td data-name="slot_code"<?= $Page->slot_code->cellAttributes() ?>>
<span id="el_document_header_slots_slot_code">
<span<?= $Page->slot_code->viewAttributes() ?>>
<?= $Page->slot_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->slot_name->Visible) { // slot_name ?>
    <tr id="r_slot_name"<?= $Page->slot_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_slot_name"><?= $Page->slot_name->caption() ?></span></td>
        <td data-name="slot_name"<?= $Page->slot_name->cellAttributes() ?>>
<span id="el_document_header_slots_slot_name">
<span<?= $Page->slot_name->viewAttributes() ?>>
<?= $Page->slot_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
    <tr id="r_part_kind"<?= $Page->part_kind->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_part_kind"><?= $Page->part_kind->caption() ?></span></td>
        <td data-name="part_kind"<?= $Page->part_kind->cellAttributes() ?>>
<span id="el_document_header_slots_part_kind">
<span<?= $Page->part_kind->viewAttributes() ?>>
<?= $Page->part_kind->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->display_order->Visible) { // display_order ?>
    <tr id="r_display_order"<?= $Page->display_order->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_display_order"><?= $Page->display_order->caption() ?></span></td>
        <td data-name="display_order"<?= $Page->display_order->cellAttributes() ?>>
<span id="el_document_header_slots_display_order">
<span<?= $Page->display_order->viewAttributes() ?>>
<?= $Page->display_order->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->default_column_pct->Visible) { // default_column_pct ?>
    <tr id="r_default_column_pct"<?= $Page->default_column_pct->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_default_column_pct"><?= $Page->default_column_pct->caption() ?></span></td>
        <td data-name="default_column_pct"<?= $Page->default_column_pct->cellAttributes() ?>>
<span id="el_document_header_slots_default_column_pct">
<span<?= $Page->default_column_pct->viewAttributes() ?>>
<?= $Page->default_column_pct->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
    <tr id="r_default_render_width"<?= $Page->default_render_width->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_default_render_width"><?= $Page->default_render_width->caption() ?></span></td>
        <td data-name="default_render_width"<?= $Page->default_render_width->cellAttributes() ?>>
<span id="el_document_header_slots_default_render_width">
<span<?= $Page->default_render_width->viewAttributes() ?>>
<?= $Page->default_render_width->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
    <tr id="r_default_render_height"<?= $Page->default_render_height->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_default_render_height"><?= $Page->default_render_height->caption() ?></span></td>
        <td data-name="default_render_height"<?= $Page->default_render_height->cellAttributes() ?>>
<span id="el_document_header_slots_default_render_height">
<span<?= $Page->default_render_height->viewAttributes() ?>>
<?= $Page->default_render_height->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->default_align->Visible) { // default_align ?>
    <tr id="r_default_align"<?= $Page->default_align->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_default_align"><?= $Page->default_align->caption() ?></span></td>
        <td data-name="default_align"<?= $Page->default_align->cellAttributes() ?>>
<span id="el_document_header_slots_default_align">
<span<?= $Page->default_align->viewAttributes() ?>>
<?= $Page->default_align->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_document_header_slots_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_document_header_slots_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_document_header_slots_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
</table>
</form>
</main>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<?php if (!$Page->isExport()) { ?>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
<?php } ?>
