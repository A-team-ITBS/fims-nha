<?php

namespace PHPMaker2024\FIMS;

// Page object
$OfficesView = &$Page;
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
<form name="fofficesview" id="fofficesview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { offices: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fofficesview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fofficesview")
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
<input type="hidden" name="t" value="offices">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->office_id->Visible) { // office_id ?>
    <tr id="r_office_id"<?= $Page->office_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_id"><?= $Page->office_id->caption() ?></span></td>
        <td data-name="office_id"<?= $Page->office_id->cellAttributes() ?>>
<span id="el_offices_office_id">
<span<?= $Page->office_id->viewAttributes() ?>>
<?= $Page->office_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_name->Visible) { // office_name ?>
    <tr id="r_office_name"<?= $Page->office_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_name"><?= $Page->office_name->caption() ?></span></td>
        <td data-name="office_name"<?= $Page->office_name->cellAttributes() ?>>
<span id="el_offices_office_name">
<span<?= $Page->office_name->viewAttributes() ?>>
<?= $Page->office_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_code->Visible) { // office_code ?>
    <tr id="r_office_code"<?= $Page->office_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_code"><?= $Page->office_code->caption() ?></span></td>
        <td data-name="office_code"<?= $Page->office_code->cellAttributes() ?>>
<span id="el_offices_office_code">
<span<?= $Page->office_code->viewAttributes() ?>>
<?= $Page->office_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_type->Visible) { // office_type ?>
    <tr id="r_office_type"<?= $Page->office_type->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_type"><?= $Page->office_type->caption() ?></span></td>
        <td data-name="office_type"<?= $Page->office_type->cellAttributes() ?>>
<span id="el_offices_office_type">
<span<?= $Page->office_type->viewAttributes() ?>>
<?= $Page->office_type->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->location->Visible) { // location ?>
    <tr id="r_location"<?= $Page->location->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_location"><?= $Page->location->caption() ?></span></td>
        <td data-name="location"<?= $Page->location->cellAttributes() ?>>
<span id="el_offices_location">
<span<?= $Page->location->viewAttributes() ?>>
<?= $Page->location->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->address->Visible) { // address ?>
    <tr id="r_address"<?= $Page->address->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_address"><?= $Page->address->caption() ?></span></td>
        <td data-name="address"<?= $Page->address->cellAttributes() ?>>
<span id="el_offices_address">
<span<?= $Page->address->viewAttributes() ?>>
<?= $Page->address->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->region->Visible) { // region ?>
    <tr id="r_region"<?= $Page->region->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_region"><?= $Page->region->caption() ?></span></td>
        <td data-name="region"<?= $Page->region->cellAttributes() ?>>
<span id="el_offices_region">
<span<?= $Page->region->viewAttributes() ?>>
<?= $Page->region->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->province->Visible) { // province ?>
    <tr id="r_province"<?= $Page->province->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_province"><?= $Page->province->caption() ?></span></td>
        <td data-name="province"<?= $Page->province->cellAttributes() ?>>
<span id="el_offices_province">
<span<?= $Page->province->viewAttributes() ?>>
<?= $Page->province->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->city_municipality->Visible) { // city_municipality ?>
    <tr id="r_city_municipality"<?= $Page->city_municipality->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_city_municipality"><?= $Page->city_municipality->caption() ?></span></td>
        <td data-name="city_municipality"<?= $Page->city_municipality->cellAttributes() ?>>
<span id="el_offices_city_municipality">
<span<?= $Page->city_municipality->viewAttributes() ?>>
<?= $Page->city_municipality->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_contact_number->Visible) { // office_contact_number ?>
    <tr id="r_office_contact_number"<?= $Page->office_contact_number->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_contact_number"><?= $Page->office_contact_number->caption() ?></span></td>
        <td data-name="office_contact_number"<?= $Page->office_contact_number->cellAttributes() ?>>
<span id="el_offices_office_contact_number">
<span<?= $Page->office_contact_number->viewAttributes() ?>>
<?= $Page->office_contact_number->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_email->Visible) { // office_email ?>
    <tr id="r_office_email"<?= $Page->office_email->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_email"><?= $Page->office_email->caption() ?></span></td>
        <td data-name="office_email"<?= $Page->office_email->cellAttributes() ?>>
<span id="el_offices_office_email">
<span<?= $Page->office_email->viewAttributes() ?>>
<?= $Page->office_email->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_telephone->Visible) { // office_telephone ?>
    <tr id="r_office_telephone"<?= $Page->office_telephone->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_telephone"><?= $Page->office_telephone->caption() ?></span></td>
        <td data-name="office_telephone"<?= $Page->office_telephone->cellAttributes() ?>>
<span id="el_offices_office_telephone">
<span<?= $Page->office_telephone->viewAttributes() ?>>
<?= $Page->office_telephone->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->office_fax->Visible) { // office_fax ?>
    <tr id="r_office_fax"<?= $Page->office_fax->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_office_fax"><?= $Page->office_fax->caption() ?></span></td>
        <td data-name="office_fax"<?= $Page->office_fax->cellAttributes() ?>>
<span id="el_offices_office_fax">
<span<?= $Page->office_fax->viewAttributes() ?>>
<?= $Page->office_fax->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->parent_office_id->Visible) { // parent_office_id ?>
    <tr id="r_parent_office_id"<?= $Page->parent_office_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_parent_office_id"><?= $Page->parent_office_id->caption() ?></span></td>
        <td data-name="parent_office_id"<?= $Page->parent_office_id->cellAttributes() ?>>
<span id="el_offices_parent_office_id">
<span<?= $Page->parent_office_id->viewAttributes() ?>>
<?= $Page->parent_office_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_offices_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->date_opened->Visible) { // date_opened ?>
    <tr id="r_date_opened"<?= $Page->date_opened->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_date_opened"><?= $Page->date_opened->caption() ?></span></td>
        <td data-name="date_opened"<?= $Page->date_opened->cellAttributes() ?>>
<span id="el_offices_date_opened">
<span<?= $Page->date_opened->viewAttributes() ?>>
<?= $Page->date_opened->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->date_closed->Visible) { // date_closed ?>
    <tr id="r_date_closed"<?= $Page->date_closed->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_date_closed"><?= $Page->date_closed->caption() ?></span></td>
        <td data-name="date_closed"<?= $Page->date_closed->cellAttributes() ?>>
<span id="el_offices_date_closed">
<span<?= $Page->date_closed->viewAttributes() ?>>
<?= $Page->date_closed->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <tr id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_created_by"><?= $Page->created_by->caption() ?></span></td>
        <td data-name="created_by"<?= $Page->created_by->cellAttributes() ?>>
<span id="el_offices_created_by">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_offices_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->modified_by->Visible) { // modified_by ?>
    <tr id="r_modified_by"<?= $Page->modified_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_modified_by"><?= $Page->modified_by->caption() ?></span></td>
        <td data-name="modified_by"<?= $Page->modified_by->cellAttributes() ?>>
<span id="el_offices_modified_by">
<span<?= $Page->modified_by->viewAttributes() ?>>
<?= $Page->modified_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->modified_at->Visible) { // modified_at ?>
    <tr id="r_modified_at"<?= $Page->modified_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_modified_at"><?= $Page->modified_at->caption() ?></span></td>
        <td data-name="modified_at"<?= $Page->modified_at->cellAttributes() ?>>
<span id="el_offices_modified_at">
<span<?= $Page->modified_at->viewAttributes() ?>>
<?= $Page->modified_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->remarks->Visible) { // remarks ?>
    <tr id="r_remarks"<?= $Page->remarks->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_remarks"><?= $Page->remarks->caption() ?></span></td>
        <td data-name="remarks"<?= $Page->remarks->cellAttributes() ?>>
<span id="el_offices_remarks">
<span<?= $Page->remarks->viewAttributes() ?>>
<?= $Page->remarks->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->metadata->Visible) { // metadata ?>
    <tr id="r_metadata"<?= $Page->metadata->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_metadata"><?= $Page->metadata->caption() ?></span></td>
        <td data-name="metadata"<?= $Page->metadata->cellAttributes() ?>>
<span id="el_offices_metadata">
<span<?= $Page->metadata->viewAttributes() ?>>
<?= $Page->metadata->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->rc_id->Visible) { // rc_id ?>
    <tr id="r_rc_id"<?= $Page->rc_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_rc_id"><?= $Page->rc_id->caption() ?></span></td>
        <td data-name="rc_id"<?= $Page->rc_id->cellAttributes() ?>>
<span id="el_offices_rc_id">
<span<?= $Page->rc_id->viewAttributes() ?>>
<?= $Page->rc_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->region_code->Visible) { // region_code ?>
    <tr id="r_region_code"<?= $Page->region_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_offices_region_code"><?= $Page->region_code->caption() ?></span></td>
        <td data-name="region_code"<?= $Page->region_code->cellAttributes() ?>>
<span id="el_offices_region_code">
<span<?= $Page->region_code->viewAttributes() ?>>
<?= $Page->region_code->getViewValue() ?></span>
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
