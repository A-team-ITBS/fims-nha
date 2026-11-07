<?php

namespace PHPMaker2024\FIMS;

// Page object
$PersonalData = &$Page;
?>
<?php
$Page->showMessage();
?>
<?php if (SameText(Param("cmd"), "Delete")) { ?>
<script>
var fpersonal_data;
loadjs.ready(["wrapper", "head"], function() {
    let $ = jQuery;
    let form = new ew.FormBuilder()
        .setId("fpersonal_data")
        // Add field
        .addFields([
            ["password", ew.Validators.required(ew.language.phrase("Password")), <?= $Page->Password->IsInvalid ? "true" : "false" ?>]
        ])
        // Extend page with Validate function
        .setValidate(
            function() {
                if (!this.validateRequired)
                    return true; // Ignore validation

                // Validate fields
                if (!this.validateFields())
                    return false;
                return true;
            }
        )
        // Use JavaScript validation
        .setValidateRequired(ew.CLIENT_VALIDATE)
        .build();
    window[form.id] = form;
    loadjs.done(form.id);
});
</script>
    <div class="alert alert-danger d-inline-block">
        <i class="icon fa-solid fa-ban"></i><?= $Language->phrase("PersonalDataWarning") ?>
    </div>
    <?php if (!EmptyString($Page->getFailureMessage())) { ?>
    <div class="text-danger">
        <ul>
            <li><?= $Page->getFailureMessage() ?></li>
        </ul>
    </div>
    <?php } ?>
    <div class="container-fluid">
        <form name="fpersonal_data" class="ew-form ew-personaldata-form" id="fpersonal_data" method="post" novalidate autocomplete="off">
            <input type="hidden" name="cmd" value="delete">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
            <div class="text-danger"></div>
            <div class="row">
                <div class="col-sm-auto">
                    <label id="label" class="control-label ew-label"><?= $Language->phrase("Password") ?></label>
                </div>
                <div class="col-sm-auto">
                    <div class="input-group">
                        <input type="password" name="<?= $Page->Password->FieldVar ?>" id="<?= $Page->Password->FieldVar ?>" autocomplete="current-password" placeholder="<?= HtmlEncode($Language->phrase("Password", true)) ?>"<?= $Page->Password->editAttributes() ?>>
                        <button type="button" class="btn btn-default ew-toggle-password rounded-end" data-ew-action="password"><i class="fa-solid fa-eye"></i></button>
                        <div class="invalid-feedback"><?= $Page->Password->getErrorMessage() ?></div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-auto">
                    <button class="btn btn-primary" type="submit"><?= $Language->phrase("CloseAccountBtn") ?></button>
                </div>
            </div>
        </form>
    </div>
<?php } else { ?>
    <div class="row">
        <div class="col">
            <p><?= $Language->phrase("PersonalDataContent") ?></p>
            <div class="alert alert-danger d-inline-block">
                <i class="icon fa-solid fa-ban"></i><?= $Language->phrase("PersonalDataWarning") ?>
            </div>
            <p>
                <a id="download" href="<?= HtmlEncode(GetUrl(CurrentPageUrl(false) . "?cmd=download")) ?>" class="btn btn-default"><?= $Language->phrase("DownloadBtn") ?></a>
                <a id="delete" href="<?= HtmlEncode(GetUrl(CurrentPageUrl(false) . "?cmd=delete")) ?>" class="btn btn-default"><?= $Language->phrase("DeleteBtn") ?></a>
            </p>
        </div>
    </div>
<?php } ?>
<?php $Page->clearFailureMessage(); ?>
