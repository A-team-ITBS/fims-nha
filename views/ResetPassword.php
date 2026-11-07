<?php

namespace PHPMaker2024\FIMS;

// Page object
$ResetPassword = &$Page;
?>
<script>
loadjs.ready("head", function () {
    // Write your client script here, no need to add script tags.
});
</script>
<script>
var freset_password;
loadjs.ready(["wrapper", "head"], function() {
    let $ = jQuery;
    ew.PAGE_ID ||= "reset_password";
    window.currentPageID ||= "reset_password";
    let form = new ew.FormBuilder()
        .setId("freset_password")
        // Add field
        .addFields([
            ["email", [ew.Validators.required(ew.language.phrase("Email")), ew.Validators.email], <?= $Page->Email->IsInvalid ? "true" : "false" ?>]
        ])

        // Validate
        .setValidate(
            async function() {
                if (!this.validateRequired)
                    return true; // Ignore validation
                var fobj = this.getForm();

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
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<div class="ew-reset-pwd-box">
<div class="card">
<div class="card-body">
<form name="freset_password" id="freset_password" class="ew-form ew-forgot-pwd-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<p class="login-box-msg"><?= $Language->phrase("ResetPwdTitle") ?></p>
    <div class="row gx-0">
        <input type="text" name="<?= $Page->Email->FieldVar ?>" id="<?= $Page->Email->FieldVar ?>" value="<?= HtmlEncode($Page->Email->CurrentValue) ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Language->phrase("UserEmail", true)) ?>"<?= $Page->Email->editAttributes() ?>>
        <div class="invalid-feedback"><?= $Language->phrase("IncorrectEmail") ?></div>
    </div>
<div class="d-grid mb-3">
    <button class="btn btn-primary ew-btn disabled enable-on-init" name="btn-submit" id="btn-submit" type="submit" formaction="<?= CurrentPageUrl(false) ?>"><?= $Language->phrase("SendPwd") ?></button>
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
