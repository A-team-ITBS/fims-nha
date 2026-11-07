<?php

namespace PHPMaker2024\FIMS;

// Page object
$Login = &$Page;
?>
<script>
loadjs.ready("head", function () {
    // Write your client script here, no need to add script tags.
});
</script>
<?php $Page->showPageHeader(); ?>
<div class="ew-login-box">
    <div class="login-logo"></div>
    <div class="card ew-login-card">
        <div class="card-body">
<?php
$Page->showMessage();
?>
<script>
// Script inside .card-body
var flogin;
loadjs.ready(["wrapper", "head"], function() {
    let $ = jQuery;
    ew.PAGE_ID ||= "login";
    window.currentPageID ||= "login";
    let form = new ew.FormBuilder()
        .setId("flogin")
        // Add fields
        .addFields([
            ["username", ew.Validators.required(ew.language.phrase("UserName")), <?= $Page->Username->IsInvalid ? "true" : "false" ?>],
            ["password", ew.Validators.required(ew.language.phrase("Password")), <?= $Page->Password->IsInvalid ? "true" : "false" ?>]
        ])

        // Captcha
        <?= Captcha()->getScript() ?>

        // Validate
        .setValidate(
            async function() {
                if (!this.validateRequired)
                    return true; // Ignore validation
                let fobj = this.getForm();

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
<form name="flogin" id="flogin" class="ew-form ew-login-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
    <?php if (Config("CHECK_TOKEN")) { ?>
    <input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
    <input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
    <?php } ?>
    <p class="login-box-msg"><?= $Language->phrase("LoginMsg") ?></p>
    <div class="row gx-0">
        <input type="text" name="<?= $Page->Username->FieldVar ?>" id="<?= $Page->Username->FieldVar ?>" autocomplete="username" value="<?= HtmlEncode($Page->Username->CurrentValue) ?>" placeholder="<?= HtmlEncode($Language->phrase("Username", true)) ?>"<?= $Page->Username->editAttributes() ?>>
        <div class="invalid-feedback"><?= $Page->Username->getErrorMessage() ?></div>
    </div>
    <?php if (!CONFIG("OTP_ONLY")) { // Disable password checking ?>
    <div class="row gx-0">
        <div class="input-group px-0">
            <input type="password" name="<?= $Page->Password->FieldVar ?>" id="<?= $Page->Password->FieldVar ?>" autocomplete="current-password" placeholder="<?= HtmlEncode($Language->phrase("Password", true)) ?>"<?= $Page->Password->editAttributes() ?>>
            <button type="button" class="btn btn-default ew-toggle-password rounded-end" data-ew-action="password"><i class="fa-solid fa-eye"></i></button>
        </div>
        <div class="valid-feedback"><?= $Language->phrase("LoginSucceeded") ?></div>
        <div class="invalid-feedback"><?= $Page->Password->getErrorMessage() ?></div>
    </div>
    <?php } ?>
    <div class="d-grid">
        <button class="btn btn-primary ew-btn disabled enable-on-init" name="btn-submit" id="btn-submit" type="submit" formaction="<?= CurrentPageUrl(false) ?>"><?= $Language->phrase("Login", true) ?></button>
    </div>
<?php
// Social login
$providers = array_filter(Config("AUTH_CONFIG.providers"), fn($provider) => $provider["enabled"]);
if (count($providers) > 0) {
?>
    <div class="social-auth-links text-center mt-3 d-grid gap-2">
        <p><?= $Language->phrase("LoginOr") ?></p>
        <?php foreach ($providers as $id => $provider) { ?>
            <a href="<?= CurrentPageUrl(false) ?>/<?= $id ?>" class="btn btn-outline-<?= strtolower($provider["color"]) ?>"><?= $Language->phrase("Login" . $id, null) ?></a>
        <?php } ?>
    </div>
<?php
}
?>
<div class="login-page-links text-center mt-3"></div>
<script type="text/html" class="ew-js-template"<?php if (!$Page->IsModal) { ?> data-name="login-page" data-seq="10"<?php } ?> data-data="login" data-target=".login-page-links">
{{if canResetPassword && resetPassword}}
<a class="card-link me-2"{{props resetPassword}} data-{{:key}}="{{>prop}}"{{/props}}>{{:resetPasswordText}}</a>
{{/if}}
{{if canRegister && register}}
<a class="card-link me-2"{{props register}} data-{{:key}}="{{>prop}}"{{/props}}>{{:registerText}}</a>
{{/if}}
</script>
</form>
        </div><!-- ./card-body -->
    </div><!-- ./card -->
</div><!-- ./ew-login-box -->
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
loadjs.ready("load", function () {
    // Write your startup script here, no need to add script tags.
});
</script>
