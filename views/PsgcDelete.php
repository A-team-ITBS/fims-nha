<?php

namespace PHPMaker2024\FIMS;

// Page object
$PsgcDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { psgc: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fpsgcdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpsgcdelete")
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
<form name="fpsgcdelete" id="fpsgcdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="psgc">
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
<?php if ($Page->code_10->Visible) { // code_10 ?>
        <th class="<?= $Page->code_10->headerCellClass() ?>"><span id="elh_psgc_code_10" class="psgc_code_10"><?= $Page->code_10->caption() ?></span></th>
<?php } ?>
<?php if ($Page->psgc_code->Visible) { // psgc_code ?>
        <th class="<?= $Page->psgc_code->headerCellClass() ?>"><span id="elh_psgc_psgc_code" class="psgc_psgc_code"><?= $Page->psgc_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->level->Visible) { // level ?>
        <th class="<?= $Page->level->headerCellClass() ?>"><span id="elh_psgc_level" class="psgc_level"><?= $Page->level->caption() ?></span></th>
<?php } ?>
<?php if ($Page->display->Visible) { // display ?>
        <th class="<?= $Page->display->headerCellClass() ?>"><span id="elh_psgc_display" class="psgc_display"><?= $Page->display->caption() ?></span></th>
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
<?php if ($Page->code_10->Visible) { // code_10 ?>
        <td<?= $Page->code_10->cellAttributes() ?>>
<span id="">
<span<?= $Page->code_10->viewAttributes() ?>>
<?= $Page->code_10->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->psgc_code->Visible) { // psgc_code ?>
        <td<?= $Page->psgc_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->psgc_code->viewAttributes() ?>>
<?= $Page->psgc_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->level->Visible) { // level ?>
        <td<?= $Page->level->cellAttributes() ?>>
<span id="">
<span<?= $Page->level->viewAttributes() ?>>
<?= $Page->level->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->display->Visible) { // display ?>
        <td<?= $Page->display->cellAttributes() ?>>
<span id="">
<span<?= $Page->display->viewAttributes() ?>>
<?= $Page->display->getViewValue() ?></span>
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
