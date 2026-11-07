<?php

namespace PHPMaker2024\FIMS;

// Page object
$PositionsView = &$Page;
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
<form name="fpositionsview" id="fpositionsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { positions: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fpositionsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpositionsview")
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
<input type="hidden" name="t" value="positions">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->position_id->Visible) { // position_id ?>
    <tr id="r_position_id"<?= $Page->position_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_position_id"><?= $Page->position_id->caption() ?></span></td>
        <td data-name="position_id"<?= $Page->position_id->cellAttributes() ?>>
<span id="el_positions_position_id">
<span<?= $Page->position_id->viewAttributes() ?>>
<?= $Page->position_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->position_category_id->Visible) { // position_category_id ?>
    <tr id="r_position_category_id"<?= $Page->position_category_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_position_category_id"><?= $Page->position_category_id->caption() ?></span></td>
        <td data-name="position_category_id"<?= $Page->position_category_id->cellAttributes() ?>>
<span id="el_positions_position_category_id">
<span<?= $Page->position_category_id->viewAttributes() ?>>
<?= $Page->position_category_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->position_code->Visible) { // position_code ?>
    <tr id="r_position_code"<?= $Page->position_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_position_code"><?= $Page->position_code->caption() ?></span></td>
        <td data-name="position_code"<?= $Page->position_code->cellAttributes() ?>>
<span id="el_positions_position_code">
<span<?= $Page->position_code->viewAttributes() ?>>
<?= $Page->position_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->position_title->Visible) { // position_title ?>
    <tr id="r_position_title"<?= $Page->position_title->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_position_title"><?= $Page->position_title->caption() ?></span></td>
        <td data-name="position_title"<?= $Page->position_title->cellAttributes() ?>>
<span id="el_positions_position_title">
<span<?= $Page->position_title->viewAttributes() ?>>
<?= $Page->position_title->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->position_level->Visible) { // position_level ?>
    <tr id="r_position_level"<?= $Page->position_level->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_position_level"><?= $Page->position_level->caption() ?></span></td>
        <td data-name="position_level"<?= $Page->position_level->cellAttributes() ?>>
<span id="el_positions_position_level">
<span<?= $Page->position_level->viewAttributes() ?>>
<?= $Page->position_level->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->job_description->Visible) { // job_description ?>
    <tr id="r_job_description"<?= $Page->job_description->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_job_description"><?= $Page->job_description->caption() ?></span></td>
        <td data-name="job_description"<?= $Page->job_description->cellAttributes() ?>>
<span id="el_positions_job_description">
<span<?= $Page->job_description->viewAttributes() ?>>
<?= $Page->job_description->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->salary_grade->Visible) { // salary_grade ?>
    <tr id="r_salary_grade"<?= $Page->salary_grade->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_salary_grade"><?= $Page->salary_grade->caption() ?></span></td>
        <td data-name="salary_grade"<?= $Page->salary_grade->cellAttributes() ?>>
<span id="el_positions_salary_grade">
<span<?= $Page->salary_grade->viewAttributes() ?>>
<?= $Page->salary_grade->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_positions_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_by->Visible) { // created_by ?>
    <tr id="r_created_by"<?= $Page->created_by->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_created_by"><?= $Page->created_by->caption() ?></span></td>
        <td data-name="created_by"<?= $Page->created_by->cellAttributes() ?>>
<span id="el_positions_created_by">
<span<?= $Page->created_by->viewAttributes() ?>>
<?= $Page->created_by->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_positions_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <tr id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_updated_at"><?= $Page->updated_at->caption() ?></span></td>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_positions_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->step_increment->Visible) { // step_increment ?>
    <tr id="r_step_increment"<?= $Page->step_increment->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_step_increment"><?= $Page->step_increment->caption() ?></span></td>
        <td data-name="step_increment"<?= $Page->step_increment->cellAttributes() ?>>
<span id="el_positions_step_increment">
<span<?= $Page->step_increment->viewAttributes() ?>>
<?= $Page->step_increment->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->qualification_standards->Visible) { // qualification_standards ?>
    <tr id="r_qualification_standards"<?= $Page->qualification_standards->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_qualification_standards"><?= $Page->qualification_standards->caption() ?></span></td>
        <td data-name="qualification_standards"<?= $Page->qualification_standards->cellAttributes() ?>>
<span id="el_positions_qualification_standards">
<span<?= $Page->qualification_standards->viewAttributes() ?>>
<?= $Page->qualification_standards->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->competency_requirements->Visible) { // competency_requirements ?>
    <tr id="r_competency_requirements"<?= $Page->competency_requirements->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_positions_competency_requirements"><?= $Page->competency_requirements->caption() ?></span></td>
        <td data-name="competency_requirements"<?= $Page->competency_requirements->cellAttributes() ?>>
<span id="el_positions_competency_requirements">
<span<?= $Page->competency_requirements->viewAttributes() ?>>
<?= $Page->competency_requirements->getViewValue() ?></span>
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
