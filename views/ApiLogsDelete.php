<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiLogsDelete = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_logs: currentTable } });
var currentPageID = ew.PAGE_ID = "delete";
var currentForm;
var fapi_logsdelete;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_logsdelete")
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
<form name="fapi_logsdelete" id="fapi_logsdelete" class="ew-form ew-delete-form" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="api_logs">
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
<?php if ($Page->log_id->Visible) { // log_id ?>
        <th class="<?= $Page->log_id->headerCellClass() ?>"><span id="elh_api_logs_log_id" class="api_logs_log_id"><?= $Page->log_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->resource_id->Visible) { // resource_id ?>
        <th class="<?= $Page->resource_id->headerCellClass() ?>"><span id="elh_api_logs_resource_id" class="api_logs_resource_id"><?= $Page->resource_id->caption() ?></span></th>
<?php } ?>
<?php if ($Page->mode_used->Visible) { // mode_used ?>
        <th class="<?= $Page->mode_used->headerCellClass() ?>"><span id="elh_api_logs_mode_used" class="api_logs_mode_used"><?= $Page->mode_used->caption() ?></span></th>
<?php } ?>
<?php if ($Page->http_status->Visible) { // http_status ?>
        <th class="<?= $Page->http_status->headerCellClass() ?>"><span id="elh_api_logs_http_status" class="api_logs_http_status"><?= $Page->http_status->caption() ?></span></th>
<?php } ?>
<?php if ($Page->duration_ms->Visible) { // duration_ms ?>
        <th class="<?= $Page->duration_ms->headerCellClass() ?>"><span id="elh_api_logs_duration_ms" class="api_logs_duration_ms"><?= $Page->duration_ms->caption() ?></span></th>
<?php } ?>
<?php if ($Page->success->Visible) { // success ?>
        <th class="<?= $Page->success->headerCellClass() ?>"><span id="elh_api_logs_success" class="api_logs_success"><?= $Page->success->caption() ?></span></th>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <th class="<?= $Page->created_at->headerCellClass() ?>"><span id="elh_api_logs_created_at" class="api_logs_created_at"><?= $Page->created_at->caption() ?></span></th>
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
<?php if ($Page->log_id->Visible) { // log_id ?>
        <td<?= $Page->log_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->log_id->viewAttributes() ?>>
<?= $Page->log_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->resource_id->Visible) { // resource_id ?>
        <td<?= $Page->resource_id->cellAttributes() ?>>
<span id="">
<span<?= $Page->resource_id->viewAttributes() ?>>
<?= $Page->resource_id->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->mode_used->Visible) { // mode_used ?>
        <td<?= $Page->mode_used->cellAttributes() ?>>
<span id="">
<span<?= $Page->mode_used->viewAttributes() ?>>
<?= $Page->mode_used->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->http_status->Visible) { // http_status ?>
        <td<?= $Page->http_status->cellAttributes() ?>>
<span id="">
<span<?= $Page->http_status->viewAttributes() ?>>
<?= $Page->http_status->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->duration_ms->Visible) { // duration_ms ?>
        <td<?= $Page->duration_ms->cellAttributes() ?>>
<span id="">
<span<?= $Page->duration_ms->viewAttributes() ?>>
<?= $Page->duration_ms->getViewValue() ?></span>
</span>
</td>
<?php } ?>
<?php if ($Page->success->Visible) { // success ?>
        <td<?= $Page->success->cellAttributes() ?>>
<span id="">
<span<?= $Page->success->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->success->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
        <td<?= $Page->created_at->cellAttributes() ?>>
<span id="">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
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
