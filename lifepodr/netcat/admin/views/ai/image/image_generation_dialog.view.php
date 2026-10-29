<?php

if (!class_exists('nc_core')) {
    exit;
}

$payload = $payload ?? array();
$max_images = (int)nc_array_value($payload, 'max_images', 4);
$max_images = $max_images > 0 ? $max_images : 4;
$upload_id = (string)nc_array_value($payload, 'upload_id', '');

?>
<script type="text/javascript">
window.nc_ai_image_generation_i18n = {
    prompt_required: <?= json_encode(NETCAT_AI_IMAGE_GENERATION_PROMPT_REQUIRED) ?>,
    select_image: <?= json_encode(NETCAT_AI_IMAGE_GENERATION_SELECT_IMAGE) ?>,
    general_error: <?= json_encode(NETCAT_AI_IMAGE_GENERATION_GENERAL_ERROR) ?>,
    remove_button: <?= json_encode(NETCAT_AI_IMAGE_GENERATION_REMOVE) ?>
};
</script>

<div class="nc-modal-dialog nc-ai-image-dialog"
     data-max-images="<?= $max_images ?>"
     data-upload-id="<?= htmlspecialchars($upload_id, ENT_QUOTES) ?>">
    <div class="nc-modal-dialog-header">
        <h2><?= NETCAT_AI_IMAGE_GENERATION_DIALOG_TITLE ?></h2>
    </div>

    <div class="nc-modal-dialog-body">
        <div class="nc-form nc-ai-image-form">
            <div class="nc-form-row">
                <label for="nc-ai-prompt"><?= NETCAT_AI_IMAGE_GENERATION_PROMPT_LABEL ?>:</label>
                <textarea name="prompt"
                    id="nc-ai-prompt"
                    rows="4"
                    class="nc-ai-image-prompt nc-form-input"
                    placeholder="<?= NETCAT_AI_IMAGE_GENERATION_PROMPT_PLACEHOLDER ?>"
                    required></textarea>
                <div class="nc-field-error nc-ai-image-prompt-error"></div>
            </div>

            <div class="nc-form-row nc-form-row--inline">
                <label for="nc-ai-count"><?= NETCAT_AI_IMAGE_GENERATION_VARIANTS_LABEL ?>:</label>
                <select name="images_count" id="nc-ai-count" class="nc-ai-image-count nc-form-input">
                    <?php for ($i = 1; $i <= $max_images; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="nc-form-row nc-form-row--actions">
                <button type="button"
                    class="nc-btn nc--blue nc-ai-image-generate-button"
                    data-action="generate">
                    <span class="nc-ai-image-generate-default"><?= NETCAT_AI_IMAGE_GENERATION_GENERATE ?></span>
                    <span class="nc-ai-image-generate-progress">
                        <i class="nc-icon nc--loading"></i>
                        <?= NETCAT_AI_IMAGE_GENERATION_GENERATING ?>
                    </span>
                </button>
            </div>

            <div class="nc-ai-image-panels" style="display: none;">
                <div class="nc-ai-image-results-panel">
                    <div class="nc-ai-image-results-list" data-role="results"></div>
                </div>

                <div class="nc-ai-image-preview-panel" data-role="preview">
                    <div class="nc-ai-image-preview-placeholder">
                        <?= NETCAT_AI_IMAGE_GENERATION_RESULT_PREVIEW ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="nc-modal-dialog-footer">
        <div class="nc-ai-dialog-tokens-balance">
            <div class="nc-ai-dialog-tokens-balance-text"></div>
        </div>

        <div>
            <button type="button"
                class="nc-btn nc--blue"
                data-action="use"
                disabled="disabled"><?= NETCAT_AI_IMAGE_GENERATION_USE ?></button>
            <button type="button"
                data-action="close">
                <?= NETCAT_AI_IMAGE_GENERATION_CANCEL ?>
            </button>
        </div>
    </div>
</div>

<script>
(function() {
    nc_ai_update_balance_in_footer('image_cost_message');
})();
</script>
