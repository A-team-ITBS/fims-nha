<?php

namespace PHPMaker2024\FIMS;

// Page object
$ApiLogsView = &$Page;
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
<form name="fapi_logsview" id="fapi_logsview" class="ew-form ew-view-form overlay-wrapper" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (!$Page->isExport()) { ?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { api_logs: currentTable } });
var currentPageID = ew.PAGE_ID = "view";
var currentForm;
var fapi_logsview;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fapi_logsview")
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
<input type="hidden" name="t" value="api_logs">
<input type="hidden" name="modal" value="<?= (int)$Page->IsModal ?>">
<table class="<?= $Page->TableClass ?>">
<?php if ($Page->log_id->Visible) { // log_id ?>
    <tr id="r_log_id"<?= $Page->log_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs_log_id"><?= $Page->log_id->caption() ?></span></td>
        <td data-name="log_id"<?= $Page->log_id->cellAttributes() ?>>
<span id="el_api_logs_log_id">
<span<?= $Page->log_id->viewAttributes() ?>>
<?= $Page->log_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->resource_id->Visible) { // resource_id ?>
    <tr id="r_resource_id"<?= $Page->resource_id->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs_resource_id"><?= $Page->resource_id->caption() ?></span></td>
        <td data-name="resource_id"<?= $Page->resource_id->cellAttributes() ?>>
<span id="el_api_logs_resource_id">
<span<?= $Page->resource_id->viewAttributes() ?>>
<?= $Page->resource_id->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->mode_used->Visible) { // mode_used ?>
    <tr id="r_mode_used"<?= $Page->mode_used->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs_mode_used"><?= $Page->mode_used->caption() ?></span></td>
        <td data-name="mode_used"<?= $Page->mode_used->cellAttributes() ?>>
<span id="el_api_logs_mode_used">
<span<?= $Page->mode_used->viewAttributes() ?>>
<?= $Page->mode_used->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->http_status->Visible) { // http_status ?>
    <tr id="r_http_status"<?= $Page->http_status->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs_http_status"><?= $Page->http_status->caption() ?></span></td>
        <td data-name="http_status"<?= $Page->http_status->cellAttributes() ?>>
<span id="el_api_logs_http_status">
<span<?= $Page->http_status->viewAttributes() ?>>
<?= $Page->http_status->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->duration_ms->Visible) { // duration_ms ?>
    <tr id="r_duration_ms"<?= $Page->duration_ms->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs_duration_ms"><?= $Page->duration_ms->caption() ?></span></td>
        <td data-name="duration_ms"<?= $Page->duration_ms->cellAttributes() ?>>
<span id="el_api_logs_duration_ms">
<span<?= $Page->duration_ms->viewAttributes() ?>>
<?= $Page->duration_ms->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->success->Visible) { // success ?>
    <tr id="r_success"<?= $Page->success->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs_success"><?= $Page->success->caption() ?></span></td>
        <td data-name="success"<?= $Page->success->cellAttributes() ?>>
<span id="el_api_logs_success">
<span<?= $Page->success->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($Page->success->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->_message->Visible) { // message ?>
    <tr id="r__message"<?= $Page->_message->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs__message"><?= $Page->_message->caption() ?></span></td>
        <td data-name="_message"<?= $Page->_message->cellAttributes() ?>>
<span id="el_api_logs__message">
<span<?= $Page->_message->viewAttributes() ?>>
<?= $Page->_message->getViewValue() ?></span>
</span>
</td>
    </tr>
<?php } ?>
<?php if ($Page->created_at->Visible) { // created_at ?>
    <tr id="r_created_at"<?= $Page->created_at->rowAttributes() ?>>
        <td class="<?= $Page->TableLeftColumnClass ?>"><span id="elh_api_logs_created_at"><?= $Page->created_at->caption() ?></span></td>
        <td data-name="created_at"<?= $Page->created_at->cellAttributes() ?>>
<span id="el_api_logs_created_at">
<span<?= $Page->created_at->viewAttributes() ?>>
<?= $Page->created_at->getViewValue() ?></span>
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
