<?php
if (!class_exists("nc_core")) {
    die;
}
?>

<div class="nc-modal-dialog" data-width="400" data-height="200">
    <div class="nc-modal-dialog-header"></div>
    <div class="nc-modal-dialog-body"><?= $ui->alert->error($message) ?></div>
</div>
