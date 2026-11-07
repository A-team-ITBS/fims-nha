<?php

namespace PHPMaker2024\FIMS;

// Page object
$ChangePassword = &$Page;
?>
<script>
loadjs.ready("head", function () {
    // Write your client script here, no need to add script tags.
});
</script>
<?php $Page->showPageHeader(); ?>
<div class="ew-change-pwd-box">
<div class="card">
<div class="card-body">
<?php
$Page->showMessage();
?>
<script>
var fchange_password;
loadjs.ready(["wrapper", "head"], function() {
    let $ = jQuery;
    ew.PAGE_ID ||= "change_password";
    window.currentPageID ||= "change_password";
    let form = new ew.FormBuilder()
        .setId("fchange_password")
        // Add fields
        .addFields([
        <?php if (!IsPasswordReset()) { ?>
            ["opwd", ew.Validators.required(ew.language.phrase("OldPassword")), <?= $Page->OldPassword->IsInvalid ? "true" : "false" ?>],
        <?php } ?>
            ["npwd", [ew.Validators.required(ew.language.phrase("NewPassword")), ew.Validators.password(<?= $Page->NewPassword->Raw ? "true" : "false" ?>)], <?= $Page->NewPassword->IsInvalid ? "true" : "false" ?>],
            ["cpwd", [ew.Validators.required(ew.language.phrase("ConfirmPassword")), ew.Validators.mismatchPassword], <?= $Page->ConfirmPassword->IsInvalid ? "true" : "false" ?>]
        ])

        // Validate
        .setValidate(
            async function() {
                if (!this.validateRequired)
                    return true; // Ignore validation
                let $ = jQuery,
                    fobj = this.getForm();

                // Validate fields
                if (!this.validateFields())
                    return false;

                // Call Form_CustomValidate event
                if (!(await this.customValidate?.(fobj) ?? true)) {
                    this.focus();
                    return false;
                }
                return true;
            }
        )

        // Form_CustomValidate
        .setCustomValidate(
            function (fobj) { // DO NOT CHANGE THIS LINE! (except for adding "async" keyword)
                    // Your custom validation code in JAVASCRIPT here, return false if invalid.
                    return true;
                }
        )

        // Use JavaScript validation
        .setValidateRequired(ew.CLIENT_VALIDATE)
        .build();
    window[form.id] = form;
    window.currentForm ||= form;
    loadjs.done(form.id);
});
</script>
<form name="fchange_password" id="fchange_password" class="ew-form ew-change-pwd-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
    <?php if (Config("CHECK_TOKEN")) { ?>
    <input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
    <input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
    <?php } ?>
    <input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
    <p class="login-box-msg"><?= $Language->phrase("ChangePasswordMessage") ?></p>
<?php if (!IsPasswordReset()) { ?>
    <div class="row">
        <div class="input-group">
            <input type="password" name="<?= $Page->OldPassword->FieldVar ?>" id="<?= $Page->OldPassword->FieldVar ?>" autocomplete="current-password" placeholder="<?= HtmlEncode($Language->phrase("OldPassword", true)) ?>"<?= $Page->OldPassword->editAttributes() ?>>
            <button type="button" class="btn btn-default ew-toggle-password rounded-end" data-ew-action="password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="invalid-feedback"><?= $Page->OldPassword->getErrorMessage() ?></div>
    </div>
<?php } ?>
    <div class="row gx-0">
        <div class="input-group px-0">
            <input type="password" name="<?= $Page->NewPassword->FieldVar ?>" id="<?= $Page->NewPassword->FieldVar ?>" autocomplete="new-password" placeholder="<?= HtmlEncode($Language->phrase("NewPassword", true)) ?>"<?= $Page->NewPassword->editAttributes() ?>>
            <button type="button" class="btn btn-default ew-toggle-password rounded-end" data-ew-action="password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="invalid-feedback"><?= $Page->NewPassword->getErrorMessage() ?></div>
    </div>
    <div class="row gx-0">
        <div class="input-group px-0">
            <input type="password" name="<?= $Page->ConfirmPassword->FieldVar ?>" id="<?= $Page->ConfirmPassword->FieldVar ?>" autocomplete="new-password" placeholder="<?= HtmlEncode($Language->phrase("ConfirmPassword", true)) ?>"<?= $Page->ConfirmPassword->editAttributes() ?>>
            <button type="button" class="btn btn-default ew-toggle-password rounded-end" data-ew-action="password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="invalid-feedback"><?= $Page->ConfirmPassword->getErrorMessage() ?></div>
    </div>
<div class="d-grid mb-3">
    <button class="btn btn-primary ew-btn disabled enable-on-init" name="btn-submit" id="btn-submit" type="submit" formaction="<?= CurrentPageUrl(false) ?>"><?= $Language->phrase("ChangePasswordBtn") ?></button>
</div>
</form>
</div>
</div>
</div>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
loadjs.ready("load", function () {
    // Write your startup script here, no need to add script tags.
});
</script>
