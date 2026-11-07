<?php

namespace PHPMaker2024\FIMS;

// Page object
$JwtSessionsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { jwt_sessions: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fjwt_sessionsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fjwt_sessionsdelete")
        .setPageId("delete")
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
<?php $Page->showPageHeader(); ?>
<?php
$Page->showMessage();
?>
<form name="fjwt_sessionsdelete" id="fjwt_sessionsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="jwt_sessions">
<input type="hidden" name="action" id="action" value="delete">
<?php foreach ($Page->RecKeys as $key) { ?>
<?php $keyvalue = is_array($key) ? implode(Config("COMPOSITE_KEY_SEPARATOR"), $key) : $key; ?>
<input type="hidden" name="key_m[]" value="<?= HtmlEncode($keyvalue) ?>">
<?php } ?>
<div class="card ew-card ew-grid <?= $Page->TableGridClass ?>">
<div class="card-body ew-grid-middle-panel <?= $Page->TableContainerClass ?>" style="<?= $Page->TableContainerStyle ?>">
<table class="<?= $Page->TableClass ?>">
    <thead>
    <tr class="ew-table-header">
<?php if ($Page->session_id->Visible) { // session_id ?>
        <th class="<?= $Page->session_id->headerCellClass() ?>"><span id="elh_jwt_sessions_session_id" class="jwt_sessions_session_id"><?= $Page->session_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->token_hash->Visible) { // token_hash ?>
        <th class="<?= $Page->token_hash->headerCellClass() ?>"><span id="elh_jwt_sessions_token_hash" class="jwt_sessions_token_hash"><?= $Page->token_hash->caption() ?></span></th>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
        <th class="<?= $Page->user_id->headerCellClass() ?>"><span id="elh_jwt_sessions_user_id" class="jwt_sessions_user_id"><?= $Page->user_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->system->Visible) { // system ?>
        <th class="<?= $Page->system->headerCellClass() ?>"><span id="elh_jwt_sessions_system" class="jwt_sessions_system"><?= $Page->system->caption() ?></span></th>
<?php } ?>
<?php if ($Page->issued_at->Visible) { // issued_at ?>
        <th class="<?= $Page->issued_at->headerCellClass() ?>"><span id="elh_jwt_sessions_issued_at" class="jwt_sessions_issued_at"><?= $Page->issued_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->expires_at->Visible) { // expires_at ?>
        <th class="<?= $Page->expires_at->headerCellClass() ?>"><span id="elh_jwt_sessions_expires_at" class="jwt_sessions_expires_at"><?= $Page->expires_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->client_ip->Visible) { // client_ip ?>
        <th class="<?= $Page->client_ip->headerCellClass() ?>"><span id="elh_jwt_sessions_client_ip" class="jwt_sessions_client_ip"><?= $Page->client_ip->caption() ?></span></th>
<?php } ?>
<?php if ($Page->revoked_at->Visible) { // revoked_at ?>
        <th class="<?= $Page->revoked_at->headerCellClass() ?>"><span id="elh_jwt_sessions_revoked_at" class="jwt_sessions_revoked_at"><?= $Page->revoked_at->caption() ?></span></th>
<?php } ?>
<?php if ($Page->revoke_reason->Visible) { // revoke_reason ?>
        <th class="<?= $Page->revoke_reason->headerCellClass() ?>"><span id="elh_jwt_sessions_revoke_reason" class="jwt_sessions_revoke_reason"><?= $Page->revoke_reason->caption() ?></span></th>
<?php } ?>
    </tr>
    </thead>
    <tbody>
<?php
$Page->RecordCount = 0;
$i = 0;
while ($Page->fetch()) {
    $Page->RecordCount++;
    $Page->RowCount++;

    // Set row properties
    $Page->resetAttributes();
    $Page->RowType = RowType::VIEW; // View

    // Get the field contents
    $Page->loadRowValues($Page->CurrentRow);

    // Render row
    $Page->renderRow();
?>
    <tr <?= $Page->rowAttributes() ?>>
<?php if ($Page->session_id->Visible) { // session_id ?>
        <td<?= $Page->session_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->session_id->viewAttributes() ?>>
<?= $Page->session_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->token_hash->Visible) { // token_hash ?>
        <td<?= $Page->token_hash->cellAttributes() ?>>
<span id="">
<span<?= $Page->token_hash->viewAttributes() ?>>
<?= $Page->token_hash->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->user_id->Visible) { // user_id ?>
        <td<?= $Page->user_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->user_id->viewAttributes() ?>>
<?= $Page->user_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->system->Visible) { // system ?>
        <td<?= $Page->system->cellAttributes() ?>>
<span id="">
<span<?= $Page->system->viewAttributes() ?>>
<?= $Page->system->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->issued_at->Visible) { // issued_at ?>
        <td<?= $Page->issued_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->issued_at->viewAttributes() ?>>
<?= $Page->issued_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->expires_at->Visible) { // expires_at ?>
        <td<?= $Page->expires_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->expires_at->viewAttributes() ?>>
<?= $Page->expires_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->client_ip->Visible) { // client_ip ?>
        <td<?= $Page->client_ip->cellAttributes() ?>>
<span id="">
<span<?= $Page->client_ip->viewAttributes() ?>>
<?= $Page->client_ip->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->revoked_at->Visible) { // revoked_at ?>
        <td<?= $Page->revoked_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->revoked_at->viewAttributes() ?>>
<?= $Page->revoked_at->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->revoke_reason->Visible) { // revoke_reason ?>
        <td<?= $Page->revoke_reason->cellAttributes() ?>>
<span id="">
<span<?= $Page->revoke_reason->viewAttributes() ?>>
<?= $Page->revoke_reason->getViewValue() ?></span>
</span>
</td>
<?php } ?>
    </tr>
<?php
}
$Page->Recordset?->free();
?>
</tbody>
</table>
</div>
</div>
<div class="ew-buttons ew-desktop-buttons">
<button class="btn btn-primary ew-btn" name="btn-action" id="btn-action" type="submit"><?= $Language->phrase("DeleteBtn") ?></button>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
</div>
</form>
<?php
$Page->showPageFooter();
echo GetDebugMessage();
?>
<script>
loadjs.ready("load", function () {
    // Write your table-specific startup script here, no need to add script tags.
});
</script>
