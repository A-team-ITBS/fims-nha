<?php

namespace PHPMaker2024\FIMS;

// Page object
$PositionsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { positions: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fpositionsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpositionsdelete")
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
<form name="fpositionsdelete" id="fpositionsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="positions">
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
<?php if ($Page->position_id->Visible) { // position_id ?>
        <th class="<?= $Page->position_id->headerCellClass() ?>"><span id="elh_positions_position_id" class="positions_position_id"><?= $Page->position_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->position_category_id->Visible) { // position_category_id ?>
        <th class="<?= $Page->position_category_id->headerCellClass() ?>"><span id="elh_positions_position_category_id" class="positions_position_category_id"><?= $Page->position_category_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->position_code->Visible) { // position_code ?>
        <th class="<?= $Page->position_code->headerCellClass() ?>"><span id="elh_positions_position_code" class="positions_position_code"><?= $Page->position_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->position_title->Visible) { // position_title ?>
        <th class="<?= $Page->position_title->headerCellClass() ?>"><span id="elh_positions_position_title" class="positions_position_title"><?= $Page->position_title->caption() ?></span></th>
<?php } ?>
<?php if ($Page->position_level->Visible) { // position_level ?>
        <th class="<?= $Page->position_level->headerCellClass() ?>"><span id="elh_positions_position_level" class="positions_position_level"><?= $Page->position_level->caption() ?></span></th>
<?php } ?>
<?php if ($Page->salary_grade->Visible) { // salary_grade ?>
        <th class="<?= $Page->salary_grade->headerCellClass() ?>"><span id="elh_positions_salary_grade" class="positions_salary_grade"><?= $Page->salary_grade->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_positions_is_active" class="positions_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <th class="<?= $Page->created_by->headerCellClass() ?>"><span id="elh_positions_created_by" class="positions_created_by"><?= $Page->created_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_positions_created_at" class="positions_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <th class="<?= $Page->updated_at->headerCellClass() ?>"><span id="elh_positions_updated_at" class="positions_updated_at"><?= $Page->updated_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->step_increment->Visible) { // step_increment ?>
        <th class="<?= $Page->step_increment->headerCellClass() ?>"><span id="elh_positions_step_increment" class="positions_step_increment"><?= $Page->step_increment->caption() ?></span></th>
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
<?php if ($Page->position_id->Visible) { // position_id ?>
        <td<?= $Page->position_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->position_id->viewAttributes() ?>>
<?= $Page->position_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->position_category_id->Visible) { // position_category_id ?>
        <td<?= $Page->position_category_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->position_category_id->viewAttributes() ?>>
<?= $Page->position_category_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->position_code->Visible) { // position_code ?>
        <td<?= $Page->position_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->position_code->viewAttributes() ?>>
<?= $Page->position_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->position_title->Visible) { // position_title ?>
        <td<?= $Page->position_title->cellAttributes() ?>>
<span id="">
<span<?= $Page->position_title->viewAttributes() ?>>
<?= $Page->position_title->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->position_level->Visible) { // position_level ?>
        <td<?= $Page->position_level->cellAttributes() ?>>
<span id="">
<span<?= $Page->position_level->viewAttributes() ?>>
<?= $Page->position_level->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->salary_grade->Visible) { // salary_grade ?>
        <td<?= $Page->salary_grade->cellAttributes() ?>>
<span id="">
<span<?= $Page->salary_grade->viewAttributes() ?>>
<?= $Page->salary_grade->getViewValue() ?></span>
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
<?php if ($Page->created_by->Visible) { // created_by ?>
        <td<?= $Page->created_by->cellAttributes() ?>>
<span id="">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
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
<?php if ($Page->updated_at->Visible) { // updated_at ?>
        <td<?= $Page->updated_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->step_increment->Visible) { // step_increment ?>
        <td<?= $Page->step_increment->cellAttributes() ?>>
<span id="">
<span<?= $Page->step_increment->viewAttributes() ?>>
<?= $Page->step_increment->getViewValue() ?></span>
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
