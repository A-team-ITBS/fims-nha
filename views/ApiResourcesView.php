<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiResourcesView = &$Page;
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
<form name="fapi_resourcesview" id="fapi_resourcesview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_resources: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fapi_resourcesview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_resourcesview")
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
<input type="hidden" name="t" value="api_resources">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->resource_id->Visible) { // resource_id ?>
    <tr id="r_resource_id"<?= $Page->resource_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_resource_id"><?= $Page->resource_id->caption() ?></span></td>
        <td data-name="resource_id"<?= $Page->resource_id->cellAttributes() ?>>
<span id="el_api_resources_resource_id">
<span<?= $Page->resource_id->viewAttributes() ?>>
<?= $Page->resource_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->system_id->Visible) { // system_id ?>
    <tr id="r_system_id"<?= $Page->system_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_system_id"><?= $Page->system_id->caption() ?></span></td>
        <td data-name="system_id"<?= $Page->system_id->cellAttributes() ?>>
<span id="el_api_resources_system_id">
<span<?= $Page->system_id->viewAttributes() ?>>
<?= $Page->system_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->resource_key->Visible) { // resource_key ?>
    <tr id="r_resource_key"<?= $Page->resource_key->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_resource_key"><?= $Page->resource_key->caption() ?></span></td>
        <td data-name="resource_key"<?= $Page->resource_key->cellAttributes() ?>>
<span id="el_api_resources_resource_key">
<span<?= $Page->resource_key->viewAttributes() ?>>
<?= $Page->resource_key->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->caption->Visible) { // caption ?>
    <tr id="r_caption"<?= $Page->caption->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_caption"><?= $Page->caption->caption() ?></span></td>
        <td data-name="caption"<?= $Page->caption->cellAttributes() ?>>
<span id="el_api_resources_caption">
<span<?= $Page->caption->viewAttributes() ?>>
<?= $Page->caption->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->http_method->Visible) { // http_method ?>
    <tr id="r_http_method"<?= $Page->http_method->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_http_method"><?= $Page->http_method->caption() ?></span></td>
        <td data-name="http_method"<?= $Page->http_method->cellAttributes() ?>>
<span id="el_api_resources_http_method">
<span<?= $Page->http_method->viewAttributes() ?>>
<?= $Page->http_method->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->path_template->Visible) { // path_template ?>
    <tr id="r_path_template"<?= $Page->path_template->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_path_template"><?= $Page->path_template->caption() ?></span></td>
        <td data-name="path_template"<?= $Page->path_template->cellAttributes() ?>>
<span id="el_api_resources_path_template">
<span<?= $Page->path_template->viewAttributes() ?>>
<?= $Page->path_template->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->mode->Visible) { // mode ?>
    <tr id="r_mode"<?= $Page->mode->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_mode"><?= $Page->mode->caption() ?></span></td>
        <td data-name="mode"<?= $Page->mode->cellAttributes() ?>>
<span id="el_api_resources_mode">
<span<?= $Page->mode->viewAttributes() ?>>
<?= $Page->mode->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->required_fields->Visible) { // required_fields ?>
    <tr id="r_required_fields"<?= $Page->required_fields->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_required_fields"><?= $Page->required_fields->caption() ?></span></td>
        <td data-name="required_fields"<?= $Page->required_fields->cellAttributes() ?>>
<span id="el_api_resources_required_fields">
<span<?= $Page->required_fields->viewAttributes() ?>>
<?= $Page->required_fields->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->field_map->Visible) { // field_map ?>
    <tr id="r_field_map"<?= $Page->field_map->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_field_map"><?= $Page->field_map->caption() ?></span></td>
        <td data-name="field_map"<?= $Page->field_map->cellAttributes() ?>>
<span id="el_api_resources_field_map">
<span<?= $Page->field_map->viewAttributes() ?>>
<?= $Page->field_map->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->sample_payload->Visible) { // sample_payload ?>
    <tr id="r_sample_payload"<?= $Page->sample_payload->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_sample_payload"><?= $Page->sample_payload->caption() ?></span></td>
        <td data-name="sample_payload"<?= $Page->sample_payload->cellAttributes() ?>>
<span id="el_api_resources_sample_payload">
<span<?= $Page->sample_payload->viewAttributes() ?>>
<?= $Page->sample_payload->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->request_body->Visible) { // request_body ?>
    <tr id="r_request_body"<?= $Page->request_body->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_request_body"><?= $Page->request_body->caption() ?></span></td>
        <td data-name="request_body"<?= $Page->request_body->cellAttributes() ?>>
<span id="el_api_resources_request_body">
<span<?= $Page->request_body->viewAttributes() ?>>
<?= $Page->request_body->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->data_path->Visible) { // data_path ?>
    <tr id="r_data_path"<?= $Page->data_path->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_data_path"><?= $Page->data_path->caption() ?></span></td>
        <td data-name="data_path"<?= $Page->data_path->cellAttributes() ?>>
<span id="el_api_resources_data_path">
<span<?= $Page->data_path->viewAttributes() ?>>
<?= $Page->data_path->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->base_url->Visible) { // base_url ?>
    <tr id="r_base_url"<?= $Page->base_url->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_base_url"><?= $Page->base_url->caption() ?></span></td>
        <td data-name="base_url"<?= $Page->base_url->cellAttributes() ?>>
<span id="el_api_resources_base_url">
<span<?= $Page->base_url->viewAttributes() ?>>
<?= $Page->base_url->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->auth_token->Visible) { // auth_token ?>
    <tr id="r_auth_token"<?= $Page->auth_token->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_auth_token"><?= $Page->auth_token->caption() ?></span></td>
        <td data-name="auth_token"<?= $Page->auth_token->cellAttributes() ?>>
<span id="el_api_resources_auth_token">
<span<?= $Page->auth_token->viewAttributes() ?>>
<?= $Page->auth_token->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->is_active->Visible) { // is_active ?>
    <tr id="r_is_active"<?= $Page->is_active->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_is_active"><?= $Page->is_active->caption() ?></span></td>
        <td data-name="is_active"<?= $Page->is_active->cellAttributes() ?>>
<span id="el_api_resources_is_active">
<span<?= $Page->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_api_resources_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->updated_at->Visible) { // updated_at ?>
    <tr id="r_updated_at"<?= $Page->updated_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_resources_updated_at"><?= $Page->updated_at->caption() ?></span></td>
        <td data-name="updated_at"<?= $Page->updated_at->cellAttributes() ?>>
<span id="el_api_resources_updated_at">
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
