<?php

namespace PHPMaker2024\FIMS;

// Page object
$OfficesDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { offices: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fofficesdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fofficesdelete")
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
<form name="fofficesdelete" id="fofficesdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="offices">
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
<?php if ($Page->office_id->Visible) { // office_id ?>
        <th class="<?= $Page->office_id->headerCellClass() ?>"><span id="elh_offices_office_id" class="offices_office_id"><?= $Page->office_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_name->Visible) { // office_name ?>
        <th class="<?= $Page->office_name->headerCellClass() ?>"><span id="elh_offices_office_name" class="offices_office_name"><?= $Page->office_name->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_code->Visible) { // office_code ?>
        <th class="<?= $Page->office_code->headerCellClass() ?>"><span id="elh_offices_office_code" class="offices_office_code"><?= $Page->office_code->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_type->Visible) { // office_type ?>
        <th class="<?= $Page->office_type->headerCellClass() ?>"><span id="elh_offices_office_type" class="offices_office_type"><?= $Page->office_type->caption() ?></span></th>
<?php } ?>
<?php if ($Page->region->Visible) { // region ?>
        <th class="<?= $Page->region->headerCellClass() ?>"><span id="elh_offices_region" class="offices_region"><?= $Page->region->caption() ?></span></th>
<?php } ?>
<?php if ($Page->province->Visible) { // province ?>
        <th class="<?= $Page->province->headerCellClass() ?>"><span id="elh_offices_province" class="offices_province"><?= $Page->province->caption() ?></span></th>
<?php } ?>
<?php if ($Page->city_municipality->Visible) { // city_municipality ?>
        <th class="<?= $Page->city_municipality->headerCellClass() ?>"><span id="elh_offices_city_municipality" class="offices_city_municipality"><?= $Page->city_municipality->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_contact_number->Visible) { // office_contact_number ?>
        <th class="<?= $Page->office_contact_number->headerCellClass() ?>"><span id="elh_offices_office_contact_number" class="offices_office_contact_number"><?= $Page->office_contact_number->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_email->Visible) { // office_email ?>
        <th class="<?= $Page->office_email->headerCellClass() ?>"><span id="elh_offices_office_email" class="offices_office_email"><?= $Page->office_email->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_telephone->Visible) { // office_telephone ?>
        <th class="<?= $Page->office_telephone->headerCellClass() ?>"><span id="elh_offices_office_telephone" class="offices_office_telephone"><?= $Page->office_telephone->caption() ?></span></th>
<?php } ?>
<?php if ($Page->office_fax->Visible) { // office_fax ?>
        <th class="<?= $Page->office_fax->headerCellClass() ?>"><span id="elh_offices_office_fax" class="offices_office_fax"><?= $Page->office_fax->caption() ?></span></th>
<?php } ?>
<?php if ($Page->parent_office_id->Visible) { // parent_office_id ?>
        <th class="<?= $Page->parent_office_id->headerCellClass() ?>"><span id="elh_offices_parent_office_id" class="offices_parent_office_id"><?= $Page->parent_office_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
        <th class="<?= $Page->is_active->headerCellClass() ?>"><span id="elh_offices_is_active" class="offices_is_active"><?= $Page->is_active->caption() ?></span></th>
<?php } ?>
<?php if ($Page->date_opened->Visible) { // date_opened ?>
        <th class="<?= $Page->date_opened->headerCellClass() ?>"><span id="elh_offices_date_opened" class="offices_date_opened"><?= $Page->date_opened->caption() ?></span></th>
<?php } ?>
<?php if ($Page->date_closed->Visible) { // date_closed ?>
        <th class="<?= $Page->date_closed->headerCellClass() ?>"><span id="elh_offices_date_closed" class="offices_date_closed"><?= $Page->date_closed->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
        <th class="<?= $Page->created_by->headerCellClass() ?>"><span id="elh_offices_created_by" class="offices_created_by"><?= $Page->created_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_offices_created_at" class="offices_created_at"><?= $Page->created_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->modified_by->Visible) { // modified_by ?>
        <th class="<?= $Page->modified_by->headerCellClass() ?>"><span id="elh_offices_modified_by" class="offices_modified_by"><?= $Page->modified_by->caption() ?></span></th>
<?php } ?>
<?php if ($Page->modified_at->Visible) { // modified_at ?>
        <th class="<?= $Page->modified_at->headerCellClass() ?>"><span id="elh_offices_modified_at" class="offices_modified_at"><?= $Page->modified_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <th class="<?= $Page->rc_id->headerCellClass() ?>"><span id="elh_offices_rc_id" class="offices_rc_id"><?= $Page->rc_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->region_code->Visible) { // region_code ?>
        <th class="<?= $Page->region_code->headerCellClass() ?>"><span id="elh_offices_region_code" class="offices_region_code"><?= $Page->region_code->caption() ?></span></th>
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
<?php if ($Page->office_id->Visible) { // office_id ?>
        <td<?= $Page->office_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_id->viewAttributes() ?>>
<?= $Page->office_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_name->Visible) { // office_name ?>
        <td<?= $Page->office_name->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_name->viewAttributes() ?>>
<?= $Page->office_name->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_code->Visible) { // office_code ?>
        <td<?= $Page->office_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_code->viewAttributes() ?>>
<?= $Page->office_code->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_type->Visible) { // office_type ?>
        <td<?= $Page->office_type->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_type->viewAttributes() ?>>
<?= $Page->office_type->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->region->Visible) { // region ?>
        <td<?= $Page->region->cellAttributes() ?>>
<span id="">
<span<?= $Page->region->viewAttributes() ?>>
<?= $Page->region->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->province->Visible) { // province ?>
        <td<?= $Page->province->cellAttributes() ?>>
<span id="">
<span<?= $Page->province->viewAttributes() ?>>
<?= $Page->province->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->city_municipality->Visible) { // city_municipality ?>
        <td<?= $Page->city_municipality->cellAttributes() ?>>
<span id="">
<span<?= $Page->city_municipality->viewAttributes() ?>>
<?= $Page->city_municipality->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_contact_number->Visible) { // office_contact_number ?>
        <td<?= $Page->office_contact_number->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_contact_number->viewAttributes() ?>>
<?= $Page->office_contact_number->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_email->Visible) { // office_email ?>
        <td<?= $Page->office_email->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_email->viewAttributes() ?>>
<?= $Page->office_email->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_telephone->Visible) { // office_telephone ?>
        <td<?= $Page->office_telephone->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_telephone->viewAttributes() ?>>
<?= $Page->office_telephone->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->office_fax->Visible) { // office_fax ?>
        <td<?= $Page->office_fax->cellAttributes() ?>>
<span id="">
<span<?= $Page->office_fax->viewAttributes() ?>>
<?= $Page->office_fax->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->parent_office_id->Visible) { // parent_office_id ?>
        <td<?= $Page->parent_office_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->parent_office_id->viewAttributes() ?>>
<?= $Page->parent_office_id->getViewValue() ?></span>
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
<?php if ($Page->date_opened->Visible) { // date_opened ?>
        <td<?= $Page->date_opened->cellAttributes() ?>>
<span id="">
<span<?= $Page->date_opened->viewAttributes() ?>>
<?= $Page->date_opened->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->date_closed->Visible) { // date_closed ?>
        <td<?= $Page->date_closed->cellAttributes() ?>>
<span id="">
<span<?= $Page->date_closed->viewAttributes() ?>>
<?= $Page->date_closed->getViewValue() ?></span>
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
<?php if ($Page->modified_by->Visible) { // modified_by ?>
        <td<?= $Page->modified_by->cellAttributes() ?>>
<span id="">
<span<?= $Page->modified_by->viewAttributes() ?>>
<?= $Page->modified_by->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->modified_at->Visible) { // modified_at ?>
        <td<?= $Page->modified_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->modified_at->viewAttributes() ?>>
<?= $Page->modified_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
        <td<?= $Page->rc_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->rc_id->viewAttributes() ?>>
<?= $Page->rc_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->region_code->Visible) { // region_code ?>
        <td<?= $Page->region_code->cellAttributes() ?>>
<span id="">
<span<?= $Page->region_code->viewAttributes() ?>>
<?= $Page->region_code->getViewValue() ?></span>
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
