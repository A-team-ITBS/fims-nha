<?php

namespace PHPMaker2024\FIMS;

// Table
$systems = Container("systems");
$systems->TableClass = "table table-bordered table-hover table-sm ew-table ew-master-table";
?>
<?php if ($systems->Visible) { ?>
<div class="ew-master-div">
<table id="tbl_systemsmaster" class="table ew-view-table ew-master-table ew-vertical">
    <tbody>
<?php if ($systems->system_id->Visible) { // system_id ?>
        <tr id="r_system_id"<?= $systems->system_id->rowAttributes() ?>>
            <td class="<?= $systems->TableLeftColumnClass ?>"><?= $systems->system_id->caption() ?></td>
            <td<?= $systems->system_id->cellAttributes() ?>>
<span id="el_systems_system_id">
<span<?= $systems->system_id->viewAttributes() ?>>
<?= $systems->system_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($systems->system_name->Visible) { // system_name ?>
        <tr id="r_system_name"<?= $systems->system_name->rowAttributes() ?>>
            <td class="<?= $systems->TableLeftColumnClass ?>"><?= $systems->system_name->caption() ?></td>
            <td<?= $systems->system_name->cellAttributes() ?>>
<span id="el_systems_system_name">
<span<?= $systems->system_name->viewAttributes() ?>>
<?= $systems->system_name->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($systems->system_code->Visible) { // system_code ?>
        <tr id="r_system_code"<?= $systems->system_code->rowAttributes() ?>>
            <td class="<?= $systems->TableLeftColumnClass ?>"><?= $systems->system_code->caption() ?></td>
            <td<?= $systems->system_code->cellAttributes() ?>>
<span id="el_systems_system_code">
<span<?= $systems->system_code->viewAttributes() ?>>
<?= $systems->system_code->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($systems->description->Visible) { // description ?>
        <tr id="r_description"<?= $systems->description->rowAttributes() ?>>
            <td class="<?= $systems->TableLeftColumnClass ?>"><?= $systems->description->caption() ?></td>
            <td<?= $systems->description->cellAttributes() ?>>
<span id="el_systems_description">
<span<?= $systems->description->viewAttributes() ?>>
<?= $systems->description->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($systems->is_active->Visible) { // is_active ?>
        <tr id="r_is_active"<?= $systems->is_active->rowAttributes() ?>>
            <td class="<?= $systems->TableLeftColumnClass ?>"><?= $systems->is_active->caption() ?></td>
            <td<?= $systems->is_active->cellAttributes() ?>>
<span id="el_systems_is_active">
<span<?= $systems->is_active->viewAttributes() ?>>
<i class="fa-regular fa-square<?php if (ConvertToBool($systems->is_active->CurrentValue)) { ?>-check<?php } ?> ew-icon ew-boolean"></i>
</span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($systems->url->Visible) { // url ?>
        <tr id="r_url"<?= $systems->url->rowAttributes() ?>>
            <td class="<?= $systems->TableLeftColumnClass ?>"><?= $systems->url->caption() ?></td>
            <td<?= $systems->url->cellAttributes() ?>>
<span id="el_systems_url">
<span<?= $systems->url->viewAttributes() ?>>
<?= $systems->url->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
    </tbody>
</table>
</div>
<?php } ?>
