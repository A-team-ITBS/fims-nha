<?php

namespace PHPMaker2024\FIMS;

// Page object
$Userpriv = &$Page;
?>
<script>
var currentTable = <?= JsonEncode($Page->toClientVar()) ?>;
ew.deepAssign(ew.vars, { tables: { _user_levels: currentTable } });
var currentPageID = ew.PAGE_ID = "userpriv";
var currentForm;
var fuserpriv;
loadjs.ready(["wrapper", "head"], function () {
    let $ = jQuery;
    let fields = currentTable.fields;

    // Form object
    let form = new ew.FormBuilder()
        .setId("fuserpriv")
        .setPageId("userpriv")
        .build();
    window[form.id] = form;
    currentForm = form;
    loadjs.done(form.id);
});
</script>
<script>
var headerSortTristate = false,
    tableOptions = {
        locale: ew.LANGUAGE_ID,
        langs: {
            [ew.LANGUAGE_ID]: {
                "data": {
                    "loading": ew.language.phrase("Loading"),
                    "error": ew.language.phrase("Error")
                }
            }
        }
    },
    priv = <?= JsonEncode($Page->Privileges) ?>;
window.Tabulator || loadjs([
    ew.PATH_BASE + "css/<?= CssFile("tabulator_bootstrap5.css", false) ?>?v=24.16.8",
    ew.PATH_BASE + "js/tabulator.min.js?v=24.16.8"
], "tabulator");
</script>
<style>
main .tooltip {
    --bs-tooltip-max-width: 500px;
}
</style>
<script>
loadjs.ready("head", function () {
    // Write your client script here, no need to add script tags.
});
</script>
<?php
$Page->showMessage();
?>
<main>
<form name="fuserpriv" id="fuserpriv" class="ew-form ew-user-priv-form w-100" action="<?= CurrentPageUrl(false) ?>" method="post" novalidate autocomplete="off">
<?php if (Config("CHECK_TOKEN")) { ?>
<input type="hidden" name="<?= $TokenNameKey ?>" value="<?= $TokenName ?>"><!-- CSRF token name -->
<input type="hidden" name="<?= $TokenValueKey ?>" value="<?= $TokenValue ?>"><!-- CSRF token value -->
<?php } ?>
<input type="hidden" name="t" value="_user_levels">
<input type="hidden" name="action" id="action" value="update">
<input type="hidden" name="x_user_level_id" id="x_user_level_id" value="<?= $Page->user_level_id->CurrentValue ?>">
<div class="ew-desktop">
<div class="card ew-card ew-user-priv">
<div class="card-header">
    <h3 class="card-title"><?= $Language->phrase("UserLevel") ?><?= $Security->getUserLevelName((int)$Page->user_level_id->CurrentValue) ?> (<?= $Page->user_level_id->CurrentValue ?>)</h3>
    <div class="card-tools float-none float-sm-end">
        <div class="input-group">
            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input type="text" name="table-name" id="table-name" class="form-control form-control-sm" placeholder="<?= HtmlEncode($Language->phrase("Search", true)) ?>">
        </div>
    </div>
</div>
<div class="card-body ew-card-body p-0 <?= $Page->ResponsiveTableClass ?>"></div>
</div>
<div class="ew-buttons ew-desktop-buttons">
<button class="btn btn-primary ew-btn" name="btn-submit" id="btn-submit" type="submit"<?= $Page->Disabled ?>><?= $Language->phrase("Update") ?></button>
<button class="btn btn-default ew-btn" name="btn-cancel" id="btn-cancel" type="button" data-href="<?= HtmlEncode(GetUrl($Page->getReturnUrl())) ?>"><?= $Language->phrase("CancelBtn") ?></button>
</div>
</div>
</form>
</main>
<script>
loadjs.ready("load", function () {
    // Write your startup script here, no need to add script tags.
});
</script>
<script>
ew.ready(["load", "tabulator"], ew.PATH_BASE + "js/userpriv.min.js?v=24.16.8");
</script>
