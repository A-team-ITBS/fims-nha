<?php

namespace PHPMaker2024\FIMS;

// Page object
$DocumentHeaderSlotsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { document_header_slots: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fdocument_header_slotsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fdocument_header_slotsdelete")
        .setPageId("delete")
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
<form name="fdocument_header_slotsdelete" id="fdocument_header_slotsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="document_header_slots">
<input type="hidden" name="action" id="action" value="delete">
<?php foreach ($Page->RecKeys as $key) { ?>
<?php $keyvalue = is_array($key) ? implode(Config("COMPOSITE_KEY_SEPARATOR"), $key) : $key; ?>
<input type="hidden" name="key_m[]" value="<?= HtmlEncode($keyvalue) ?>">
<?php } ?>
<div class="card ew-card ew-grid <?= $Page->TableGridClass ?>">
<div class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<table class="<?= $Page->TableClass ?>">
    <thead>
    <tr class="ew-table-header">
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <th class="<?= $Page->header_slot_id->headerCellClass() ?>"><span id="elh_document_header_slots_header_slot_id" class="document_header_slots_header_slot_id"><?= $Page->header_slot_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->slot_code->Visible) { // slot_code ?>
        <th class="<?= $Page->slot_code->headerCellClass() ?>"><span id="elh_document_header_slots_slot_code" class="document_header_slots_slot_code"><?= $Page->slot_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->slot_name->Visible) { // slot_name ?>
        <th class="<?= $Page->slot_name->headerCellClass() ?>"><span id="elh_document_header_slots_slot_name" class="document_header_slots_slot_name"><?= $Page->slot_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
        <th class="<?= $Page->part_kind->headerCellClass() ?>"><span id="elh_document_header_slots_part_kind" class="document_header_slots_part_kind"><?= $Page->part_kind->caption() ?></span></th>
<?php } ?>
<?php if ($Page->display_order->Visible) { // display_order ?>
        <th class="<?= $Page->display_order->headerCellClass() ?>"><span id="elh_document_header_slots_display_order" class="document_header_slots_display_order"><?= $Page->display_order->caption() ?></span></th>
<?php } ?>
<?php if ($Page->default_column_pct->Visible) { // default_column_pct ?>
        <th class="<?= $Page->default_column_pct->headerCellClass() ?>"><span id="elh_document_header_slots_default_column_pct" class="document_header_slots_default_column_pct"><?= $Page->default_column_pct->caption() ?></span></th>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
        <th class="<?= $Page->default_render_width->headerCellClass() ?>"><span id="elh_document_header_slots_default_render_width" class="document_header_slots_default_render_width"><?= $Page->default_render_width->caption() ?></span></th>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
        <th class="<?= $Page->default_render_height->headerCellClass() ?>"><span id="elh_document_header_slots_default_render_height" class="document_header_slots_default_render_height"><?= $Page->default_render_height->caption() ?></span></th>
<?php } ?>
<?php if ($Page->default_align->Visible) { // default_align ?>
        <th class="<?= $Page->default_align->headerCellClass() ?>"><span id="elh_document_header_slots_default_align" class="document_header_slots_default_align"><?= $Page->default_align->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_document_header_slots_is_active" class="document_header_slots_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_document_header_slots_created_at" class="document_header_slots_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
    </tr>
    </thead>
    <tbody>
<?php
$Page->RecordCount = 0;
$i = 0;
while ($Page->fetch()) {
    $Page->RecordCount++;
    $Page->RowCount++;

    // Set row properties
    $Page->resetAttributes();
    $Page->RowType = RowType::VIEW; // View

    // Get the field contents
    $Page->loadRowValues($Page->CurrentRow);

    // Render row
    $Page->renderRow();
?>
    <tr <?= $Page->rowAttributes() ?>>
<?php if ($Page->header_slot_id->Visible) { // header_slot_id ?>
        <td<?= $Page->header_slot_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->header_slot_id->viewAttributes() ?>>
<?= $Page->header_slot_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->slot_code->Visible) { // slot_code ?>
        <td<?= $Page->slot_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->slot_code->viewAttributes() ?>>
<?= $Page->slot_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->slot_name->Visible) { // slot_name ?>
        <td<?= $Page->slot_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->slot_name->viewAttributes() ?>>
<?= $Page->slot_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->part_kind->Visible) { // part_kind ?>
        <td<?= $Page->part_kind->cellAttributes() ?>>
<span id="">
<span<?= $Page->part_kind->viewAttributes() ?>>
<?= $Page->part_kind->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->display_order->Visible) { // display_order ?>
        <td<?= $Page->display_order->cellAttributes() ?>>
<span id="">
<span<?= $Page->display_order->viewAttributes() ?>>
<?= $Page->display_order->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->default_column_pct->Visible) { // default_column_pct ?>
        <td<?= $Page->default_column_pct->cellAttributes() ?>>
<span id="">
<span<?= $Page->default_column_pct->viewAttributes() ?>>
<?= $Page->default_column_pct->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->default_render_width->Visible) { // default_render_width ?>
        <td<?= $Page->default_render_width->cellAttributes() ?>>
<span id="">
<span<?= $Page->default_render_width->viewAttributes() ?>>
<?= $Page->default_render_width->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->default_render_height->Visible) { // default_render_height ?>
        <td<?= $Page->default_render_height->cellAttributes() ?>>
<span id="">
<span<?= $Page->default_render_height->viewAttributes() ?>>
<?= $Page->default_render_height->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->default_align->Visible) { // default_align ?>
        <td<?= $Page->default_align->cellAttributes() ?>>
<span id="">
<span<?= $Page->default_align->viewAttributes() ?>>
<?= $Page->default_align->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <td<?= $Page->is_active->cellAttributes() ?>>
<span id="">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <td<?= $Page->created_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
    </tr>
<?php
}
$Page->Recordset?->free();
?>
</tbody>
</table>
</div>
</div>
<div class="ew-buttons ew-desktop-buttons">
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit"><?= $Language->phrase("DeleteBtn") ?></button>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
</div>
</form>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
