<?php

namespace PHPMaker2024\FIMS;

// Page object
$JwtSessionsEdit = &$Page;
?>
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<main class="edit">
<form name="fjwt_sessionsedit" id="fjwt_sessionsedit" class="<?= $Page->FormClassName ?>" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { jwt_sessions: currentTable } });
var currentPageID = ew.PAGE_ID = "edit";
var currentForm;
var fjwt_sessionsedit;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fjwt_sessionsedit")
        .setPageId("edit")

        // Add fields
        .setFields([
            ["session_id", [fields.session_id.visible && fields.session_id.required ? ew.Validators.required(fields.session_id.caption) : null], fields.session_id.isInvalid],
            ["token_hash", [fields.token_hash.visible && fields.token_hash.required ? ew.Validators.required(fields.token_hash.caption) : null], fields.token_hash.isInvalid],
            ["user_id", [fields.user_id.visible && fields.user_id.required ? ew.Validators.required(fields.user_id.caption) : null, ew.Validators.integer], fields.user_id.isInvalid],
            ["system", [fields.system.visible && fields.system.required ? ew.Validators.required(fields.system.caption) : null], fields.system.isInvalid],
            ["issued_at", [fields.issued_at.visible && fields.issued_at.required ? ew.Validators.required(fields.issued_at.caption) : null, ew.Validators.datetime(fields.issued_at.clientFormatPattern)], fields.issued_at.isInvalid],
            ["expires_at", [fields.expires_at.visible && fields.expires_at.required ? ew.Validators.required(fields.expires_at.caption) : null, ew.Validators.datetime(fields.expires_at.clientFormatPattern)], fields.expires_at.isInvalid],
            ["client_ip", [fields.client_ip.visible && fields.client_ip.required ? ew.Validators.required(fields.client_ip.caption) : null], fields.client_ip.isInvalid],
            ["user_agent", [fields.user_agent.visible && fields.user_agent.required ? ew.Validators.required(fields.user_agent.caption) : null], fields.user_agent.isInvalid],
            ["revoked_at", [fields.revoked_at.visible && fields.revoked_at.required ? ew.Validators.required(fields.revoked_at.caption) : null, ew.Validators.datetime(fields.revoked_at.clientFormatPattern)], fields.revoked_at.isInvalid],
            ["revoke_reason", [fields.revoke_reason.visible && fields.revoke_reason.required ? ew.Validators.required(fields.revoke_reason.caption) : null], fields.revoke_reason.isInvalid]
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
<input type="hidden" name="t" value="jwt_sessions">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<?php if (IsJsonResponse()) { ?>
<input type="hidden" name="json" value="1">
<?php } ?>
<input type="hidden" name="<?= $Page->OldKeyName ?>" value="<?= $Page->OldKey ?>">
<div class="ew-edit-div"><!-- page* -->
<?php if ($Page->session_id->Visible) { // session_id ?>
    <div id="r_session_id"<?= $Page->session_id->rowAttributes() ?>>
        <label id="elh_jwt_sessions_session_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->session_id->caption() ?><?= $Page->session_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->session_id->cellAttributes() ?>>
<span id="el_jwt_sessions_session_id">
<span<?= $Page->session_id->viewAttributes() ?>>
<input type="text" readonly class="form-control-plaintext" value="<?= HtmlEncode(RemoveHtml($Page->session_id->getDisplayValue($Page->session_id->EditValue))) ?>"></span>
<input type="hidden" data-table="jwt_sessions" data-field="x_session_id" data-hidden="1" name="x_session_id" id="x_session_id" value="<?= HtmlEncode($Page->session_id->CurrentValue) ?>">
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->token_hash->Visible) { // token_hash ?>
    <div id="r_token_hash"<?= $Page->token_hash->rowAttributes() ?>>
        <label id="elh_jwt_sessions_token_hash" for="x_token_hash" class="<?= $Page->LeftColumnClass ?>"><?= $Page->token_hash->caption() ?><?= $Page->token_hash->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->token_hash->cellAttributes() ?>>
<span id="el_jwt_sessions_token_hash">
<input type="<?= $Page->token_hash->getInputTextType() ?>" name="x_token_hash" id="x_token_hash" data-table="jwt_sessions" data-field="x_token_hash" value="<?= $Page->token_hash->EditValue ?>" size="30" maxlength="64" placeholder="<?= HtmlEncode($Page->token_hash->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->token_hash->formatPattern()) ?>"<?= $Page->token_hash->editAttributes() ?> aria-describedby="x_token_hash_help">
<?= $Page->token_hash->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->token_hash->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <div id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <label id="elh_jwt_sessions_user_id" for="x_user_id" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_id->caption() ?><?= $Page->user_id->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_id->cellAttributes() ?>>
<span id="el_jwt_sessions_user_id">
<input type="<?= $Page->user_id->getInputTextType() ?>" name="x_user_id" id="x_user_id" data-table="jwt_sessions" data-field="x_user_id" value="<?= $Page->user_id->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->user_id->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->user_id->formatPattern()) ?>"<?= $Page->user_id->editAttributes() ?> aria-describedby="x_user_id_help">
<?= $Page->user_id->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->user_id->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->system->Visible) { // system ?>
    <div id="r_system"<?= $Page->system->rowAttributes() ?>>
        <label id="elh_jwt_sessions_system" for="x_system" class="<?= $Page->LeftColumnClass ?>"><?= $Page->system->caption() ?><?= $Page->system->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->system->cellAttributes() ?>>
<span id="el_jwt_sessions_system">
<input type="<?= $Page->system->getInputTextType() ?>" name="x_system" id="x_system" data-table="jwt_sessions" data-field="x_system" value="<?= $Page->system->EditValue ?>" size="30" maxlength="20" placeholder="<?= HtmlEncode($Page->system->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->system->formatPattern()) ?>"<?= $Page->system->editAttributes() ?> aria-describedby="x_system_help">
<?= $Page->system->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->system->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->issued_at->Visible) { // issued_at ?>
    <div id="r_issued_at"<?= $Page->issued_at->rowAttributes() ?>>
        <label id="elh_jwt_sessions_issued_at" for="x_issued_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->issued_at->caption() ?><?= $Page->issued_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->issued_at->cellAttributes() ?>>
<span id="el_jwt_sessions_issued_at">
<input type="<?= $Page->issued_at->getInputTextType() ?>" name="x_issued_at" id="x_issued_at" data-table="jwt_sessions" data-field="x_issued_at" value="<?= $Page->issued_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->issued_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->issued_at->formatPattern()) ?>"<?= $Page->issued_at->editAttributes() ?> aria-describedby="x_issued_at_help">
<?= $Page->issued_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->issued_at->getErrorMessage() ?></div>
<?php if (!$Page->issued_at->ReadOnly && !$Page->issued_at->Disabled && !isset($Page->issued_at->EditAttrs["readonly"]) && !isset($Page->issued_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fjwt_sessionsedit", "datetimepicker"], function () {
    let format = "<?= DateFormat(0) ?>",
        options = {
            localization: {
                locale: ew.LANGUAGE_ID + "-u-nu-" + ew.getNumberingSystem(),
                hourCycle: format.match(/H/) ? "h24" : "h12",
                format,
                ...ew.language.phrase("datetimepicker")
            },
            display: {
                icons: {
                    previous: ew.IS_RTL ? "fa-solid fa-chevron-right" : "fa-solid fa-chevron-left",
                    next: ew.IS_RTL ? "fa-solid fa-chevron-left" : "fa-solid fa-chevron-right"
                },
                components: {
                    clock: !!format.match(/h/i) || !!format.match(/m/) || !!format.match(/s/i),
                    hours: !!format.match(/h/i),
                    minutes: !!format.match(/m/),
                    seconds: !!format.match(/s/i)
                },
                theme: ew.getPreferredTheme()
            }
        };
    ew.createDateTimePicker("fjwt_sessionsedit", "x_issued_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->expires_at->Visible) { // expires_at ?>
    <div id="r_expires_at"<?= $Page->expires_at->rowAttributes() ?>>
        <label id="elh_jwt_sessions_expires_at" for="x_expires_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->expires_at->caption() ?><?= $Page->expires_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->expires_at->cellAttributes() ?>>
<span id="el_jwt_sessions_expires_at">
<input type="<?= $Page->expires_at->getInputTextType() ?>" name="x_expires_at" id="x_expires_at" data-table="jwt_sessions" data-field="x_expires_at" value="<?= $Page->expires_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->expires_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->expires_at->formatPattern()) ?>"<?= $Page->expires_at->editAttributes() ?> aria-describedby="x_expires_at_help">
<?= $Page->expires_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->expires_at->getErrorMessage() ?></div>
<?php if (!$Page->expires_at->ReadOnly && !$Page->expires_at->Disabled && !isset($Page->expires_at->EditAttrs["readonly"]) && !isset($Page->expires_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fjwt_sessionsedit", "datetimepicker"], function () {
    let format = "<?= DateFormat(0) ?>",
        options = {
            localization: {
                locale: ew.LANGUAGE_ID + "-u-nu-" + ew.getNumberingSystem(),
                hourCycle: format.match(/H/) ? "h24" : "h12",
                format,
                ...ew.language.phrase("datetimepicker")
            },
            display: {
                icons: {
                    previous: ew.IS_RTL ? "fa-solid fa-chevron-right" : "fa-solid fa-chevron-left",
                    next: ew.IS_RTL ? "fa-solid fa-chevron-left" : "fa-solid fa-chevron-right"
                },
                components: {
                    clock: !!format.match(/h/i) || !!format.match(/m/) || !!format.match(/s/i),
                    hours: !!format.match(/h/i),
                    minutes: !!format.match(/m/),
                    seconds: !!format.match(/s/i)
                },
                theme: ew.getPreferredTheme()
            }
        };
    ew.createDateTimePicker("fjwt_sessionsedit", "x_expires_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->client_ip->Visible) { // client_ip ?>
    <div id="r_client_ip"<?= $Page->client_ip->rowAttributes() ?>>
        <label id="elh_jwt_sessions_client_ip" for="x_client_ip" class="<?= $Page->LeftColumnClass ?>"><?= $Page->client_ip->caption() ?><?= $Page->client_ip->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->client_ip->cellAttributes() ?>>
<span id="el_jwt_sessions_client_ip">
<input type="<?= $Page->client_ip->getInputTextType() ?>" name="x_client_ip" id="x_client_ip" data-table="jwt_sessions" data-field="x_client_ip" value="<?= $Page->client_ip->EditValue ?>" size="30" placeholder="<?= HtmlEncode($Page->client_ip->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->client_ip->formatPattern()) ?>"<?= $Page->client_ip->editAttributes() ?> aria-describedby="x_client_ip_help">
<?= $Page->client_ip->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->client_ip->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->user_agent->Visible) { // user_agent ?>
    <div id="r_user_agent"<?= $Page->user_agent->rowAttributes() ?>>
        <label id="elh_jwt_sessions_user_agent" for="x_user_agent" class="<?= $Page->LeftColumnClass ?>"><?= $Page->user_agent->caption() ?><?= $Page->user_agent->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->user_agent->cellAttributes() ?>>
<span id="el_jwt_sessions_user_agent">
<textarea data-table="jwt_sessions" data-field="x_user_agent" name="x_user_agent" id="x_user_agent" cols="35" rows="4" placeholder="<?= HtmlEncode($Page->user_agent->getPlaceHolder()) ?>"<?= $Page->user_agent->editAttributes() ?> aria-describedby="x_user_agent_help"><?= $Page->user_agent->EditValue ?></textarea>
<?= $Page->user_agent->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->user_agent->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->revoked_at->Visible) { // revoked_at ?>
    <div id="r_revoked_at"<?= $Page->revoked_at->rowAttributes() ?>>
        <label id="elh_jwt_sessions_revoked_at" for="x_revoked_at" class="<?= $Page->LeftColumnClass ?>"><?= $Page->revoked_at->caption() ?><?= $Page->revoked_at->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->revoked_at->cellAttributes() ?>>
<span id="el_jwt_sessions_revoked_at">
<input type="<?= $Page->revoked_at->getInputTextType() ?>" name="x_revoked_at" id="x_revoked_at" data-table="jwt_sessions" data-field="x_revoked_at" value="<?= $Page->revoked_at->EditValue ?>" placeholder="<?= HtmlEncode($Page->revoked_at->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->revoked_at->formatPattern()) ?>"<?= $Page->revoked_at->editAttributes() ?> aria-describedby="x_revoked_at_help">
<?= $Page->revoked_at->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->revoked_at->getErrorMessage() ?></div>
<?php if (!$Page->revoked_at->ReadOnly && !$Page->revoked_at->Disabled && !isset($Page->revoked_at->EditAttrs["readonly"]) && !isset($Page->revoked_at->EditAttrs["disabled"])) { ?>
<script>
loadjs.ready(["fjwt_sessionsedit", "datetimepicker"], function () {
    let format = "<?= DateFormat(0) ?>",
        options = {
            localization: {
                locale: ew.LANGUAGE_ID + "-u-nu-" + ew.getNumberingSystem(),
                hourCycle: format.match(/H/) ? "h24" : "h12",
                format,
                ...ew.language.phrase("datetimepicker")
            },
            display: {
                icons: {
                    previous: ew.IS_RTL ? "fa-solid fa-chevron-right" : "fa-solid fa-chevron-left",
                    next: ew.IS_RTL ? "fa-solid fa-chevron-left" : "fa-solid fa-chevron-right"
                },
                components: {
                    clock: !!format.match(/h/i) || !!format.match(/m/) || !!format.match(/s/i),
                    hours: !!format.match(/h/i),
                    minutes: !!format.match(/m/),
                    seconds: !!format.match(/s/i)
                },
                theme: ew.getPreferredTheme()
            }
        };
    ew.createDateTimePicker("fjwt_sessionsedit", "x_revoked_at", ew.deepAssign({"useCurrent":false,"display":{"sideBySide":false}}, options));
});
</script>
<?php } ?>
</span>
</div></div>
    </div>
<?php } ?>
<?php if ($Page->revoke_reason->Visible) { // revoke_reason ?>
    <div id="r_revoke_reason"<?= $Page->revoke_reason->rowAttributes() ?>>
        <label id="elh_jwt_sessions_revoke_reason" for="x_revoke_reason" class="<?= $Page->LeftColumnClass ?>"><?= $Page->revoke_reason->caption() ?><?= $Page->revoke_reason->Required ? $Language->phrase("FieldRequiredIndicator") : "" ?></label>
        <div class="<?= $Page->RightColumnClass ?>"><div<?= $Page->revoke_reason->cellAttributes() ?>>
<span id="el_jwt_sessions_revoke_reason">
<input type="<?= $Page->revoke_reason->getInputTextType() ?>" name="x_revoke_reason" id="x_revoke_reason" data-table="jwt_sessions" data-field="x_revoke_reason" value="<?= $Page->revoke_reason->EditValue ?>" size="30" maxlength="100" placeholder="<?= HtmlEncode($Page->revoke_reason->getPlaceHolder()) ?>" data-format-pattern="<?= HtmlEncode($Page->revoke_reason->formatPattern()) ?>"<?= $Page->revoke_reason->editAttributes() ?> aria-describedby="x_revoke_reason_help">
<?= $Page->revoke_reason->getCustomMessage() ?>
<div class="invalid-feedback"><?= $Page->revoke_reason->getErrorMessage() ?></div>
</span>
</div></div>
    </div>
<?php } ?>
</div><!-- /page* -->
<?= $Page->IsModal ? '<template class="ew-modal-buttons">' : '<div class="row ew-buttons">' ?><!-- buttons .row -->
    <div class="<?= $Page->OffsetColumnClass ?>"><!-- buttons offset -->
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit" form="fjwt_sessionsedit"><?= $Language->phrase("SaveBtn") ?></button>
<?php if (IsJsonResponse()) { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-bs-dismiss="modal"><?= $Language->phrase("CancelBtn") ?></button>
<?php } else { ?>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" form="fjwt_sessionsedit" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
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
    ew.addEventHandlers("jwt_sessions");
});
</script>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
