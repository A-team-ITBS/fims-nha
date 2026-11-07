<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiSystemsView = &$Page;
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
<form name="fapi_systemsview" id="fapi_systemsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_systems: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fapi_systemsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_systemsview")
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
<input type="hidden" name="t" value="api_systems">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->system_id->Visible) { // system_id ?>
    <tr id="r_system_id"<?= $Page->system_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_system_id"><?= $Page->system_id->caption() ?></span></td>
        <td data-name="system_id"<?= $Page->system_id->cellAttributes() ?>>
<span id="el_api_systems_system_id">
<span<?= $Page->system_id->viewAttributes() ?>>
<?= $Page->system_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->system_code->Visible) { // system_code ?>
    <tr id="r_system_code"<?= $Page->system_code->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_system_code"><?= $Page->system_code->caption() ?></span></td>
        <td data-name="system_code"<?= $Page->system_code->cellAttributes() ?>>
<span id="el_api_systems_system_code">
<span<?= $Page->system_code->viewAttributes() ?>>
<?= $Page->system_code->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->system_name->Visible) { // system_name ?>
    <tr id="r_system_name"<?= $Page->system_name->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_system_name"><?= $Page->system_name->caption() ?></span></td>
        <td data-name="system_name"<?= $Page->system_name->cellAttributes() ?>>
<span id="el_api_systems_system_name">
<span<?= $Page->system_name->viewAttributes() ?>>
<?= $Page->system_name->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->base_url->Visible) { // base_url ?>
    <tr id="r_base_url"<?= $Page->base_url->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_base_url"><?= $Page->base_url->caption() ?></span></td>
        <td data-name="base_url"<?= $Page->base_url->cellAttributes() ?>>
<span id="el_api_systems_base_url">
<span<?= $Page->base_url->viewAttributes() ?>>
<?= $Page->base_url->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->auth_type->Visible) { // auth_type ?>
    <tr id="r_auth_type"<?= $Page->auth_type->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_auth_type"><?= $Page->auth_type->caption() ?></span></td>
        <td data-name="auth_type"<?= $Page->auth_type->cellAttributes() ?>>
<span id="el_api_systems_auth_type">
<span<?= $Page->auth_type->viewAttributes() ?>>
<?= $Page->auth_type->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->auth_token->Visible) { // auth_token ?>
    <tr id="r_auth_token"<?= $Page->auth_token->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_auth_token"><?= $Page->auth_token->caption() ?></span></td>
        <td data-name="auth_token"<?= $Page->auth_token->cellAttributes() ?>>
<span id="el_api_systems_auth_token">
<span<?= $Page->auth_token->viewAttributes() ?>>
<?= $Page->auth_token->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_api_systems_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_api_systems_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <tr id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_systems_updated_at"><?= $Page->updated_at->caption() ?></span></td>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_api_systems_updated_at">
<span<?= $Page->updated_at->viewAttributes() ?>>
<?= $Page->updated_at->getViewValue() ?></span>
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
