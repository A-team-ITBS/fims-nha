<?php

namespace PHPMaker2024\FIMS;

// Page object
$PsgcEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fpsgcedit" id="fpsgcedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { psgc: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fpsgcedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fpsgcedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["code_10", [fields.code_10.visible && fields.code_10.required ? ew.Validators.required(fields.code_10.caption) : null], fields.code_10.isInvalid],
            ["name", [fields.name.visible && fields.name.required ? ew.Validators.required(fields.name.caption) : null], fields.name.isInvalid],
            ["psgc_code", [fields.psgc_code.visible && fields.psgc_code.required ? ew.Validators.required(fields.psgc_code.caption) : null], fields.psgc_code.isInvalid],
            ["level", [fields.level.visible && fields.level.required ? ew.Validators.required(fields.level.caption) : null], fields.level.isInvalid],
            ["od_name", [fields.od_name.visible && fields.od_name.required ? ew.Validators.required(fields.od_name.caption) : null], fields.od_name.isInvalid],
            ["city_class", [fields.city_class.visible && fields.city_class.required ? ew.Validators.required(fields.city_class.caption) : null], fields.city_class.isInvalid],
            ["income_class", [fields.income_class.visible && fields.income_class.required ? ew.Validators.required(fields.income_class.caption) : null], fields.income_class.isInvalid],
            ["rural_urban", [fields.rural_urban.visible && fields.rural_urban.required ? ew.Validators.required(fields.rural_urban.caption) : null], fields.rural_urban.isInvalid],
            ["population_2015", [fields.population_2015.visible && fields.population_2015.required ? ew.Validators.required(fields.population_2015.caption) : null, ew.Validators.integer], fields.population_2015.isInvalid],
            ["population_2020", [fields.population_2020.visible && fields.population_2020.required ? ew.Validators.required(fields.population_2020.caption) : null, ew.Validators.integer], fields.population_2020.isInvalid],
            ["status", [fields.status.visible && fields.status.required ? ew.Validators.required(fields.status.caption) : null], fields.status.isInvalid],
            ["display", [fields.display.visible && fields.display.required ? ew.Validators.required(fields.display.caption) : null], fields.display.isInvalid]
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
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="psgc">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->code_10->Visible) { // code_10 ?>
    <div id="r_code_10"<?= $Page->code_10->rowAttributes() ?>>
        <label id="elh_psgc_code_10" for="x_code_10" class="<?= $Page->LeftColumnClass ?>"><?= $Page->code_10->caption() ?><?= $Page->code_10->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->code_10->cellAttributes() ?>>
<span id="el_psgc_code_10">
<input type="<?= $Page->code_10->getInputTextType() ?>" name="x_code_10" id="x_code_10" data-table="psgc" data-field="x_code_10" value="<?= $Page->code_10->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->code_10->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->code_10->formatPattern()) ?>"<?= $Page->code_10->editAttributes() ?> aria-describedby="x_code_10_help">
<?= $Page->code_10->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->code_10->getErrorMessage() ?></div>
<input type="hidden" data-table="psgc" data-field="x_code_10" data-hidden="1" data-old name="o_code_10" id="o_code_10" value="<?= HtmlEncode($Page->code_10->OldValue ?? $Page->code_10->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->name->Visible) { // name ?>
    <div id="r_name"<?= $Page->name->rowAttributes() ?>>
        <label id="elh_psgc_name" for="x_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->name->caption() ?><?= $Page->name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->name->cellAttributes() ?>>
<span id="el_psgc_name">
<input type="<?= $Page->name->getInputTextType() ?>" name="x_name" id="x_name" data-table="psgc" data-field="x_name" value="<?= $Page->name->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->name->formatPattern()) ?>"<?= $Page->name->editAttributes() ?> aria-describedby="x_name_help">
<?= $Page->name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->psgc_code->Visible) { // psgc_code ?>
    <div id="r_psgc_code"<?= $Page->psgc_code->rowAttributes() ?>>
        <label id="elh_psgc_psgc_code" for="x_psgc_code" class="<?= $Page->LeftColumnClass ?>"><?= $Page->psgc_code->caption() ?><?= $Page->psgc_code->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->psgc_code->cellAttributes() ?>>
<span id="el_psgc_psgc_code">
<input type="<?= $Page->psgc_code->getInputTextType() ?>" name="x_psgc_code" id="x_psgc_code" data-table="psgc" data-field="x_psgc_code" value="<?= $Page->psgc_code->EditValue ?>" size="30" maxlength="12" placeholder="<?= HtmlEncode($Page->psgc_code->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->psgc_code->formatPattern()) ?>"<?= $Page->psgc_code->editAttributes() ?> aria-describedby="x_psgc_code_help">
<?= $Page->psgc_code->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->psgc_code->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->level->Visible) { // level ?>
    <div id="r_level"<?= $Page->level->rowAttributes() ?>>
        <label id="elh_psgc_level" for="x_level" class="<?= $Page->LeftColumnClass ?>"><?= $Page->level->caption() ?><?= $Page->level->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->level->cellAttributes() ?>>
<span id="el_psgc_level">
<input type="<?= $Page->level->getInputTextType() ?>" name="x_level" id="x_level" data-table="psgc" data-field="x_level" value="<?= $Page->level->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->level->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->level->formatPattern()) ?>"<?= $Page->level->editAttributes() ?> aria-describedby="x_level_help">
<?= $Page->level->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->level->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->od_name->Visible) { // od_name ?>
    <div id="r_od_name"<?= $Page->od_name->rowAttributes() ?>>
        <label id="elh_psgc_od_name" for="x_od_name" class="<?= $Page->LeftColumnClass ?>"><?= $Page->od_name->caption() ?><?= $Page->od_name->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->od_name->cellAttributes() ?>>
<span id="el_psgc_od_name">
<input type="<?= $Page->od_name->getInputTextType() ?>" name="x_od_name" id="x_od_name" data-table="psgc" data-field="x_od_name" value="<?= $Page->od_name->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->od_name->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->od_name->formatPattern()) ?>"<?= $Page->od_name->editAttributes() ?> aria-describedby="x_od_name_help">
<?= $Page->od_name->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->od_name->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->city_class->Visible) { // city_class ?>
    <div id="r_city_class"<?= $Page->city_class->rowAttributes() ?>>
        <label id="elh_psgc_city_class" for="x_city_class" class="<?= $Page->LeftColumnClass ?>"><?= $Page->city_class->caption() ?><?= $Page->city_class->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->city_class->cellAttributes() ?>>
<span id="el_psgc_city_class">
<input type="<?= $Page->city_class->getInputTextType() ?>" name="x_city_class" id="x_city_class" data-table="psgc" data-field="x_city_class" value="<?= $Page->city_class->EditValue ?>" size="30" maxlength="5" placeholder="<?= HtmlEncode($Page->city_class->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->city_class->formatPattern()) ?>"<?= $Page->city_class->editAttributes() ?> aria-describedby="x_city_class_help">
<?= $Page->city_class->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->city_class->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->income_class->Visible) { // income_class ?>
    <div id="r_income_class"<?= $Page->income_class->rowAttributes() ?>>
        <label id="elh_psgc_income_class" for="x_income_class" class="<?= $Page->LeftColumnClass ?>"><?= $Page->income_class->caption() ?><?= $Page->income_class->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->income_class->cellAttributes() ?>>
<span id="el_psgc_income_class">
<input type="<?= $Page->income_class->getInputTextType() ?>" name="x_income_class" id="x_income_class" data-table="psgc" data-field="x_income_class" value="<?= $Page->income_class->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->income_class->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->income_class->formatPattern()) ?>"<?= $Page->income_class->editAttributes() ?> aria-describedby="x_income_class_help">
<?= $Page->income_class->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->income_class->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->rural_urban->Visible) { // rural_urban ?>
    <div id="r_rural_urban"<?= $Page->rural_urban->rowAttributes() ?>>
        <label id="elh_psgc_rural_urban" for="x_rural_urban" class="<?= $Page->LeftColumnClass ?>"><?= $Page->rural_urban->caption() ?><?= $Page->rural_urban->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->rural_urban->cellAttributes() ?>>
<span id="el_psgc_rural_urban">
<input type="<?= $Page->rural_urban->getInputTextType() ?>" name="x_rural_urban" id="x_rural_urban" data-table="psgc" data-field="x_rural_urban" value="<?= $Page->rural_urban->EditValue ?>" size="30" maxlength="10" placeholder="<?= HtmlEncode($Page->rural_urban->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->rural_urban->formatPattern()) ?>"<?= $Page->rural_urban->editAttributes() ?> aria-describedby="x_rural_urban_help">
<?= $Page->rural_urban->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->rural_urban->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->population_2015->Visible) { // population_2015 ?>
    <div id="r_population_2015"<?= $Page->population_2015->rowAttributes() ?>>
        <label id="elh_psgc_population_2015" for="x_population_2015" class="<?= $Page->LeftColumnClass ?>"><?= $Page->population_2015->caption() ?><?= $Page->population_2015->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->population_2015->cellAttributes() ?>>
<span id="el_psgc_population_2015">
<input type="<?= $Page->population_2015->getInputTextType() ?>" name="x_population_2015" id="x_population_2015" data-table="psgc" data-field="x_population_2015" value="<?= $Page->population_2015->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->population_2015->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->population_2015->formatPattern()) ?>"<?= $Page->population_2015->editAttributes() ?> aria-describedby="x_population_2015_help">
<?= $Page->population_2015->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->population_2015->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->population_2020->Visible) { // population_2020 ?>
    <div id="r_population_2020"<?= $Page->population_2020->rowAttributes() ?>>
        <label id="elh_psgc_population_2020" for="x_population_2020" class="<?= $Page->LeftColumnClass ?>"><?= $Page->population_2020->caption() ?><?= $Page->population_2020->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->population_2020->cellAttributes() ?>>
<span id="el_psgc_population_2020">
<input type="<?= $Page->population_2020->getInputTextType() ?>" name="x_population_2020" id="x_population_2020" data-table="psgc" data-field="x_population_2020" value="<?= $Page->population_2020->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->population_2020->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->population_2020->formatPattern()) ?>"<?= $Page->population_2020->editAttributes() ?> aria-describedby="x_population_2020_help">
<?= $Page->population_2020->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->population_2020->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->status->Visible) { // status ?>
    <div id="r_status"<?= $Page->status->rowAttributes() ?>>
        <label id="elh_psgc_status" for="x_status" class="<?= $Page->LeftColumnClass ?>"><?= $Page->status->caption() ?><?= $Page->status->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->status->cellAttributes() ?>>
<span id="el_psgc_status">
<input type="<?= $Page->status->getInputTextType() ?>" name="x_status" id="x_status" data-table="psgc" data-field="x_status" value="<?= $Page->status->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->status->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->status->formatPattern()) ?>"<?= $Page->status->editAttributes() ?> aria-describedby="x_status_help">
<?= $Page->status->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->status->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->display->Visible) { // display ?>
    <div id="r_display"<?= $Page->display->rowAttributes() ?>>
        <label id="elh_psgc_display" for="x_display" class="<?= $Page->LeftColumnClass ?>"><?= $Page->display->caption() ?><?= $Page->display->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->display->cellAttributes() ?>>
<span id="el_psgc_display">
<input type="<?= $Page->display->getInputTextType() ?>" name="x_display" id="x_display" data-table="psgc" data-field="x_display" value="<?= $Page->display->EditValue ?>" size="30" maxlength="255" placeholder="<?= HtmlEncode($Page->display->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->display->formatPattern()) ?>"<?= $Page->display->editAttributes() ?> aria-describedby="x_display_help">
<?= $Page->display->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->display->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fpsgcedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fpsgcedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
<?php } ?>
    </div><!-- /buttons offset -->
<?= $Page->IsModal ? "</template>" : "</div>" ?><!-- /buttons .row -->
</form>
</main>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
// Field event handlers
loadjs.ready("head", function() {
    ew.addEventHandlers("psgc");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
