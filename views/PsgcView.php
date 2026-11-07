<?php

namespace PHPMaker2024\FIMS;

// Page object
$PsgcView = &$Page;
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
<form name="fpsgcview" id="fpsgcview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { psgc: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fpsgcview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpsgcview")
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
<input type="hidden" name="t" value="psgc">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->code_10->Visible) { // code_10 ?>
    <tr id="r_code_10"<?= $Page->code_10->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_code_10"><?= $Page->code_10->caption() ?></span></td>
        <td data-name="code_10"<?= $Page->code_10->cellAttributes() ?>>
<span id="el_psgc_code_10">
<span<?= $Page->code_10->viewAttributes() ?>>
<?= $Page->code_10->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->name->Visible) { // name ?>
    <tr id="r_name"<?= $Page->name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_name"><?= $Page->name->caption() ?></span></td>
        <td data-name="name"<?= $Page->name->cellAttributes() ?>>
<span id="el_psgc_name">
<span<?= $Page->name->viewAttributes() ?>>
<?= $Page->name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->psgc_code->Visible) { // psgc_code ?>
    <tr id="r_psgc_code"<?= $Page->psgc_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_psgc_code"><?= $Page->psgc_code->caption() ?></span></td>
        <td data-name="psgc_code"<?= $Page->psgc_code->cellAttributes() ?>>
<span id="el_psgc_psgc_code">
<span<?= $Page->psgc_code->viewAttributes() ?>>
<?= $Page->psgc_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->level->Visible) { // level ?>
    <tr id="r_level"<?= $Page->level->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_level"><?= $Page->level->caption() ?></span></td>
        <td data-name="level"<?= $Page->level->cellAttributes() ?>>
<span id="el_psgc_level">
<span<?= $Page->level->viewAttributes() ?>>
<?= $Page->level->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->od_name->Visible) { // od_name ?>
    <tr id="r_od_name"<?= $Page->od_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_od_name"><?= $Page->od_name->caption() ?></span></td>
        <td data-name="od_name"<?= $Page->od_name->cellAttributes() ?>>
<span id="el_psgc_od_name">
<span<?= $Page->od_name->viewAttributes() ?>>
<?= $Page->od_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->city_class->Visible) { // city_class ?>
    <tr id="r_city_class"<?= $Page->city_class->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_city_class"><?= $Page->city_class->caption() ?></span></td>
        <td data-name="city_class"<?= $Page->city_class->cellAttributes() ?>>
<span id="el_psgc_city_class">
<span<?= $Page->city_class->viewAttributes() ?>>
<?= $Page->city_class->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->income_class->Visible) { // income_class ?>
    <tr id="r_income_class"<?= $Page->income_class->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_income_class"><?= $Page->income_class->caption() ?></span></td>
        <td data-name="income_class"<?= $Page->income_class->cellAttributes() ?>>
<span id="el_psgc_income_class">
<span<?= $Page->income_class->viewAttributes() ?>>
<?= $Page->income_class->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->rural_urban->Visible) { // rural_urban ?>
    <tr id="r_rural_urban"<?= $Page->rural_urban->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_rural_urban"><?= $Page->rural_urban->caption() ?></span></td>
        <td data-name="rural_urban"<?= $Page->rural_urban->cellAttributes() ?>>
<span id="el_psgc_rural_urban">
<span<?= $Page->rural_urban->viewAttributes() ?>>
<?= $Page->rural_urban->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->population_2015->Visible) { // population_2015 ?>
    <tr id="r_population_2015"<?= $Page->population_2015->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_population_2015"><?= $Page->population_2015->caption() ?></span></td>
        <td data-name="population_2015"<?= $Page->population_2015->cellAttributes() ?>>
<span id="el_psgc_population_2015">
<span<?= $Page->population_2015->viewAttributes() ?>>
<?= $Page->population_2015->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->population_2020->Visible) { // population_2020 ?>
    <tr id="r_population_2020"<?= $Page->population_2020->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_population_2020"><?= $Page->population_2020->caption() ?></span></td>
        <td data-name="population_2020"<?= $Page->population_2020->cellAttributes() ?>>
<span id="el_psgc_population_2020">
<span<?= $Page->population_2020->viewAttributes() ?>>
<?= $Page->population_2020->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->status->Visible) { // status ?>
    <tr id="r_status"<?= $Page->status->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_status"><?= $Page->status->caption() ?></span></td>
        <td data-name="status"<?= $Page->status->cellAttributes() ?>>
<span id="el_psgc_status">
<span<?= $Page->status->viewAttributes() ?>>
<?= $Page->status->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->display->Visible) { // display ?>
    <tr id="r_display"<?= $Page->display->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_psgc_display"><?= $Page->display->caption() ?></span></td>
        <td data-name="display"<?= $Page->display->cellAttributes() ?>>
<span id="el_psgc_display">
<span<?= $Page->display->viewAttributes() ?>>
<?= $Page->display->getViewValue() ?></span>
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
