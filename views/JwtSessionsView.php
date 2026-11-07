<?php

namespace PHPMaker2024\FIMS;

// Page object
$JwtSessionsView = &$Page;
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
<form name="fjwt_sessionsview" id="fjwt_sessionsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { jwt_sessions: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fjwt_sessionsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fjwt_sessionsview")
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
<input type="hidden" name="t" value="jwt_sessions">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->session_id->Visible) { // session_id ?>
    <tr id="r_session_id"<?= $Page->session_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_session_id"><?= $Page->session_id->caption() ?></span></td>
        <td data-name="session_id"<?= $Page->session_id->cellAttributes() ?>>
<span id="el_jwt_sessions_session_id">
<span<?= $Page->session_id->viewAttributes() ?>>
<?= $Page->session_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->token_hash->Visible) { // token_hash ?>
    <tr id="r_token_hash"<?= $Page->token_hash->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_token_hash"><?= $Page->token_hash->caption() ?></span></td>
        <td data-name="token_hash"<?= $Page->token_hash->cellAttributes() ?>>
<span id="el_jwt_sessions_token_hash">
<span<?= $Page->token_hash->viewAttributes() ?>>
<?= $Page->token_hash->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
    <tr id="r_user_id"<?= $Page->user_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_user_id"><?= $Page->user_id->caption() ?></span></td>
        <td data-name="user_id"<?= $Page->user_id->cellAttributes() ?>>
<span id="el_jwt_sessions_user_id">
<span<?= $Page->user_id->viewAttributes() ?>>
<?= $Page->user_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->system->Visible) { // system ?>
    <tr id="r_system"<?= $Page->system->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_system"><?= $Page->system->caption() ?></span></td>
        <td data-name="system"<?= $Page->system->cellAttributes() ?>>
<span id="el_jwt_sessions_system">
<span<?= $Page->system->viewAttributes() ?>>
<?= $Page->system->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->issued_at->Visible) { // issued_at ?>
    <tr id="r_issued_at"<?= $Page->issued_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_issued_at"><?= $Page->issued_at->caption() ?></span></td>
        <td data-name="issued_at"<?= $Page->issued_at->cellAttributes() ?>>
<span id="el_jwt_sessions_issued_at">
<span<?= $Page->issued_at->viewAttributes() ?>>
<?= $Page->issued_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->expires_at->Visible) { // expires_at ?>
    <tr id="r_expires_at"<?= $Page->expires_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_expires_at"><?= $Page->expires_at->caption() ?></span></td>
        <td data-name="expires_at"<?= $Page->expires_at->cellAttributes() ?>>
<span id="el_jwt_sessions_expires_at">
<span<?= $Page->expires_at->viewAttributes() ?>>
<?= $Page->expires_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->client_ip->Visible) { // client_ip ?>
    <tr id="r_client_ip"<?= $Page->client_ip->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_client_ip"><?= $Page->client_ip->caption() ?></span></td>
        <td data-name="client_ip"<?= $Page->client_ip->cellAttributes() ?>>
<span id="el_jwt_sessions_client_ip">
<span<?= $Page->client_ip->viewAttributes() ?>>
<?= $Page->client_ip->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->user_agent->Visible) { // user_agent ?>
    <tr id="r_user_agent"<?= $Page->user_agent->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_user_agent"><?= $Page->user_agent->caption() ?></span></td>
        <td data-name="user_agent"<?= $Page->user_agent->cellAttributes() ?>>
<span id="el_jwt_sessions_user_agent">
<span<?= $Page->user_agent->viewAttributes() ?>>
<?= $Page->user_agent->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->revoked_at->Visible) { // revoked_at ?>
    <tr id="r_revoked_at"<?= $Page->revoked_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_revoked_at"><?= $Page->revoked_at->caption() ?></span></td>
        <td data-name="revoked_at"<?= $Page->revoked_at->cellAttributes() ?>>
<span id="el_jwt_sessions_revoked_at">
<span<?= $Page->revoked_at->viewAttributes() ?>>
<?= $Page->revoked_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->revoke_reason->Visible) { // revoke_reason ?>
    <tr id="r_revoke_reason"<?= $Page->revoke_reason->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_jwt_sessions_revoke_reason"><?= $Page->revoke_reason->caption() ?></span></td>
        <td data-name="revoke_reason"<?= $Page->revoke_reason->cellAttributes() ?>>
<span id="el_jwt_sessions_revoke_reason">
<span<?= $Page->revoke_reason->viewAttributes() ?>>
<?= $Page->revoke_reason->getViewValue() ?></span>
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
