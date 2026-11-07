<?php

namespace PHPMaker2024\FIMS;

// Table
$_user_levels = Container("_user_levels");
$_user_levels->TableClass = "table table-bordered table-hover table-sm ew-table ew-master-table";
?>
<?php if ($_user_levels->Visible) { ?>
<div class="ew-master-div">
<table id="tbl__user_levelsmaster" class="table ew-view-table ew-master-table ew-vertical">
    <tbody>
<?php if ($_user_levels->user_level_id->Visible) { // user_level_id ?>
        <tr id="r_user_level_id"<?= $_user_levels->user_level_id->rowAttributes() ?>>
            <td class="<?= $_user_levels->TableLeftColumnClass ?>"><?= $_user_levels->user_level_id->caption() ?></td>
            <td<?= $_user_levels->user_level_id->cellAttributes() ?>>
<span id="el__user_levels_user_level_id">
<span<?= $_user_levels->user_level_id->viewAttributes() ?>>
<?= $_user_levels->user_level_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($_user_levels->system_id->Visible) { // system_id ?>
        <tr id="r_system_id"<?= $_user_levels->system_id->rowAttributes() ?>>
            <td class="<?= $_user_levels->TableLeftColumnClass ?>"><?= $_user_levels->system_id->caption() ?></td>
            <td<?= $_user_levels->system_id->cellAttributes() ?>>
<span id="el__user_levels_system_id">
<span<?= $_user_levels->system_id->viewAttributes() ?>>
<?= $_user_levels->system_id->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
<?php if ($_user_levels->name->Visible) { // name ?>
        <tr id="r_name"<?= $_user_levels->name->rowAttributes() ?>>
            <td class="<?= $_user_levels->TableLeftColumnClass ?>"><?= $_user_levels->name->caption() ?></td>
            <td<?= $_user_levels->name->cellAttributes() ?>>
<span id="el__user_levels_name">
<span<?= $_user_levels->name->viewAttributes() ?>>
<?= $_user_levels->name->getViewValue() ?></span>
</span>
</td>
        </tr>
<?php } ?>
    </tbody>
</table>
</div>
<?php } ?>
