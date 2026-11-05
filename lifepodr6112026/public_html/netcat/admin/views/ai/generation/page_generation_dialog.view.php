<?php

if (!class_exists("nc_core")) {
    die;
}

/** @var nc_core $nc_core */
/** @var nc_ui $ui */
/** @var array $payload */
/** @var nc_ai_company_context $company_context */

$store_versions = $nc_core->get_settings(
    'StoreVersions', 'system', false, $nc_core->catalogue->get_current('Catalogue_ID')
);

$get_examples = function ($scenario_type, $prefix, $number) {
    $constant_prefix = 'NETCAT_AI_USER_PROMPT_' . $prefix;

    $result = '<div class="nc-ai-dialog-prompt-example-items nc--hide" ' .
        'data-ref-scenario-subtype="' . $scenario_type . '">';

    for ($i = 1; $i <= $number; $i++) {
        $result .= '<div class="nc-ai-dialog-prompt-example-item">' .
            '<a href="#" data-example="' .
            htmlspecialchars(constant($constant_prefix . $i)) .
            '">' .
            constant($constant_prefix . $i . '_TITLE') .
            '</a>' .
            ($i < $number ? ', ' : '') .
            '</div>';
    }

    $result .= '</div>';

    return $result;
};

?>

<div class="nc-modal-dialog nc-ai-dialog nc-ai-init-dialog" data-width="860">
    <div class="nc-modal-dialog-header">
        <h2><?= sprintf(NETCAT_AI_PAGE_GENERATION_DIALOG_TITLE, $payload['subdivision']['Subdivision_Name']) ?></h2>
    </div>

    <div class="nc-modal-dialog-body">
        <?php if ($payload['page_has_blocks']): ?>
            <div class='nc-alert nc--yellow'>
                <?= sprintf(
                    NETCAT_AI_SCENARIO_DELETING_BLOCKS_ALERT,
                    $store_versions ? ', ' . NETCAT_AI_SCENARIO_DELETING_BLOCKS_REVERT_CHANGES : '',
                    $nc_core->ADMIN_PATH . "/#subdivision.add($payload[subdivision_id])"
                ) ?>
            </div>
        <?php endif; ?>

        <form action="<?= $nc_core->SUB_FOLDER . $nc_core->HTTP_ROOT_PATH ?>action.php" method="post"
            class='nc-ai-init-form nc-form'>
            <input type="hidden" name="ctrl" value="ai_content_generation">
            <input type="hidden" name="action" value="start_generate_page">
            <input type="hidden" name="subdivision_id" value="<?= $payload['subdivision_id'] ?>">

            <?php
            // С версии 7.2 генерация использует единственный сценарий уникальной
            // вёрстки, поэтому выбор сценария убран. Значение шлём скрытым полем для
            // совместимости со стороной генератора.
            ?>
            <input type="hidden" name="scenario_type"
                value="<?= nc_ai_generation_scenario_type::FREE_LANDING_ON_MULTIPAGE ?>">
            <?php
            // prevent_append_headers оставлен «на будущее»: отправляем всегда с
            // falsy-значением, генератор его игнорирует.
            ?>
            <input type="hidden" name="settings[prevent_append_headers]" value="0">

            <div class='nc-form-row' style="width:50%;">
                <label><?= NETCAT_AI_CONTEXT_SOURCE ?>:</label>
                <select name="settings[context_source]" id="context-source-select">
                    <option value="generate"><?= NETCAT_AI_CONTEXT_SOURCE_GENERATE ?></option>
                    <?php if ($payload['page_has_blocks']): ?>
                        <option value="this-page"><?= NETCAT_AI_CONTEXT_SOURCE_PAGE_THIS ?></option>
                    <?php endif; ?>
                    <option value="another-page"><?= NETCAT_AI_CONTEXT_SOURCE_PAGE_ANOTHER ?></option>
                    <option value="text"><?= NETCAT_AI_CONTEXT_SOURCE_TEXT ?></option>
                </select>
            </div>

            <div class='nc-form-row' id="source-url-container" style="display: none;">
                <label><?= NETCAT_AI_CONTEXT_SOURCE_URL_TITLE ?>:</label>
                <input type="text" name="settings[source_url]" placeholder="<?= NETCAT_AI_CONTEXT_SOURCE_URL_PLACEHOLDER ?>"/>
            </div>

            <div class='nc-form-row' id="source-text-container" style="display: none;">
                <label><?= NETCAT_AI_CONTEXT_SOURCE_TEXT_TITLE ?>:</label>
                <textarea rows="8" placeholder="<?= NETCAT_AI_CONTEXT_SOURCE_TEXT_PLACEHOLDER ?>" name="settings[source_text]"></textarea>
            </div>

            <div class='nc-form-row nc-ai-dialog-user-prompt'>
                <div class="nc-ai-dialog-selected-scenario-heading nc--hide"><?= NETCAT_AI_HOMEPAGE_MULTIPAGE_HEADING ?></div>

                <label><?= NETCAT_AI_PASS_A_PROMPT ?>:</label>
                <textarea rows="8" placeholder="<?= NETCAT_AI_USER_PROMPT_PLACEHOLDER ?>" name="user_prompt"></textarea>

                <div class="nc-ai-dialog-prompt-examples">
                    <div class="nc-ai-dialog-heading"><?= NETCAT_AI_USER_PROMPT_EXAMPLES ?>:</div>
                    <?= $get_examples(nc_ai_generation_scenario_type::FREE_LANDING_ON_MULTIPAGE, 'F', 4) ?>
                    <?= $get_examples("source_extended", 'T', 4) ?>
                    </div>
            </div>

            <div class="nc-form-row">
                <div class="nc-ai-dialog-company-context-toggle"><?= NETCAT_AI_COMPANY_DESCRIPTION ?>
                    <?= $company_context->get_company_description(
                    ) ? '' : " <span class='nc-text-red'>" . NETCAT_AI_COMPANY_DESCRIPTION_IS_EMPTY . "</span>" ?>
                </div>
                <div class="nc-ai-dialog-company-context-hint"><?= NETCAT_AI_COMPANY_DESCRIPTION_FILL_HINT ?></div>
                <div class="nc-ai-dialog-company-context nc--hide">
                    <div class='nc-form-row'>
                        <label><?= NETCAT_AI_COMPANY_DESCRIPTION ?>:</label>
                        <textarea rows="5"
                            placeholder="<?= NETCAT_AI_COMPANY_DESCRIPTION_PLACEHOLDER ?>"
                            name="company_context[company_description]"><?= $company_context->get_company_description(
                            ) ?></textarea>
                    </div>

                    <div class="nc-ai-scenario-form">
                        <div class='nc-form-row' style="width:50%;">
                            <label><?= NETCAT_AI_COMPANY_NAME ?>:</label>
                            <input type="text" name="company_context[company_name]" placeholder="<?= NETCAT_AI_COMPANY_NAME ?>"
                                value="<?= $company_context->get_company_name() ?>"/>
                        </div>

                        <div class='nc-form-row'>
                            <label><?= NETCAT_AI_COMPANY_TARGET_AUDIENCE ?>:</label>
                            <textarea type="text" rows="10"
                                name="company_context[target_audience]"
                                placeholder="<?= NETCAT_AI_COMPANY_TARGET_AUDIENCE_PLACEHOLDER ?>"><?= $company_context->get_target_audience(
                                ) ?></textarea>
                        </div>

                        <div class='nc-form-row'>
                            <label><?= NETCAT_AI_COMPANY_ADVANTAGES ?>:</label>
                            <textarea name="company_context[advantages]" rows="3"
                                placeholder="<?= NETCAT_AI_COMPANY_ADVANTAGES_PLACEHOLDER ?>"><?= $company_context->get_advantages(
                                ) ?></textarea>
                        </div>

                        <div class='nc-form-row'>
                            <label><?= NETCAT_AI_COMPANY_FEELINGS ?>:</label>
                            <textarea name="company_context[feelings]"
                                rows="3"
                                placeholder="<?= NETCAT_AI_COMPANY_FEELINGS_PLACEHOLDER ?>"><?= $company_context->get_feelings(
                                ) ?></textarea>
                        </div>

                        <div class='nc-form-row'>
                            <label><?= NETCAT_AI_COMPANY_SERVICES ?>:</label>
                            <textarea name="company_context[services]"
                                rows="3"
                                placeholder="<?= NETCAT_AI_COMPANY_SERVICES_PLACEHOLDER ?>"><?= $company_context->get_services(
                                ) ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <div class="nc-modal-dialog-footer">
        <div class="nc-ai-dialog-tokens-balance">
            <div class="nc-ai-dialog-tokens-balance-text"></div>
        </div>

        <div>
            <button class="nc--blue nc-ai-dialog-submit-button"><?= NECAT_AI_GENERATE_PAGE ?></button>
            <button data-action="close"><?= CONTROL_BUTTON_CANCEL ?></button>
        </div>
    </div>
</div>

<script>
    setTimeout(function () {
        const dialog = nc.ui.modal_dialog.get_current_dialog();
        const submitButton = dialog.find("button.nc-ai-dialog-submit-button");
        const form = dialog.find(".nc-ai-init-form");
        const freeLandingScenario = '<?= nc_ai_generation_scenario_type::FREE_LANDING_ON_MULTIPAGE ?>';

        initExamplesChange();
        initSubmitFormListener();
        initContextSourceToggle();
        initCompanyContextBlockToggle();
        nc_ai_update_balance_in_footer('page_cost_message');
        updateExamplesVisibility();
        initDialogCloseHandler(); // После всех инициализаций

        function initContextSourceToggle() {
            const contextSourceSelect = dialog.find("select[name='settings[context_source]']");
            const sourceUrlContainer = dialog.find("input[name='settings[source_url]']").closest('.nc-form-row');
            const sourceTextContainer = dialog.find("textarea[name='settings[source_text]']").closest('.nc-form-row');

            contextSourceSelect.on('change', function () {
                const value = $nc(this).val();
                if (value === 'another-page') {
                    sourceUrlContainer.show();
                    sourceTextContainer.hide();
                } else if (value === 'text') {
                    sourceTextContainer.show();
                    sourceUrlContainer.hide();
                } else {
                    sourceUrlContainer.hide();
                    sourceTextContainer.hide();
                }
                updateExamplesVisibility();
            });
        }

        // Примеры промптов: для источников «другая страница / текст / эта страница»
        // показываем расширенные примеры, иначе — примеры уникального дизайна.
        function updateExamplesVisibility() {
            const value = dialog.find("select[name='settings[context_source]']").val();
            const useSourceExamples = value === 'another-page' || value === 'text' || value === 'this-page';
            dialog.find('[data-ref-scenario-subtype]').hide();
            dialog.find(`[data-ref-scenario-subtype='${useSourceExamples ? 'source_extended' : freeLandingScenario}']`).show();
        }

        function initExamplesChange() {
            const userPrompt = $nc(form).find("textarea[name=user_prompt]");

            dialog.find(".nc-ai-dialog-prompt-example-item a").on("click", function (e) {
                e.preventDefault();
                userPrompt.val($nc(this).data('example'));
            });
        }

        function initSubmitFormListener() {
            const event = 'click.ai-generate-page';
            submitButton.off(event).on(event, submitForm);
        }

        function isValidUrl(str) {
            try {
                new URL(str);
                return true;
            } 
            catch (_) {
                return false;
            }
        }

        const POLLING_INTERVAL = 15000; // 15 секунд
        let pollingTimer = null;

        async function submitForm() {

            const contextSourceSelect = dialog.find("select[name='settings[context_source]']");
            const sourceUrlInput = dialog.find("input[name='settings[source_url]']");
            const sourceTextArea = dialog.find("textarea[name='settings[source_text]']");

            const extUrl = sourceUrlInput.val();
            const extText = sourceTextArea.val();
            const extType = contextSourceSelect.val();

            if (extType === "another-page" && !isValidUrl(extUrl)) {
                dialog.show_error("<?= NETCAT_AI_ERROR_NO_URL ?>");
                return;
            }

            if (extType === "text" && !extText) {
                dialog.show_error("<?= NETCAT_AI_ERROR_NO_TEXT ?>");
                return;
            }

            submitButton.addClass("nc--loading");
            try {
                const response = await $nc.ajax({
                    type: "post",
                    url: form.attr("action"),
                    data: form.serialize(),
                    dataType: "json"
                });

                if (response.task_id) {
                    startPolling(response.task_id);
                } else {
                    throw new Error(response.message || "<?= NETCAT_AI_SCENARIO_ERROR ?>");
                }
            } catch (e) {
                submitButton.removeClass("nc--loading");
                const message = e.responseJSON?.message || e.message || "<?= NETCAT_AI_SCENARIO_ERROR ?>";
                dialog.show_error(message);
                console.error(e);
            }
        }

        function startPolling(taskId) {
            const checkUrl = "<?= $nc_core->SUB_FOLDER . $nc_core->HTTP_ROOT_PATH ?>action.php?" +
                "ctrl=ai_content_generation&action=check_generation_status&task_id=" + taskId;

            async function checkStatus() {
                try {
                    const response = await $nc.ajax({
                        type: "get",
                        url: checkUrl,
                        dataType: "json"
                    });

                    // json_success returns {result: {...}}, json_error returns {message: "..."}
                    if (response.message) {
                        throw new Error(response.message);
                    }

                    const status = response.result?.status;

                    if (status === "completed") {
                        stopPolling();
                        window.location.reload();
                    } else if (status === "error") {
                        stopPolling();
                        submitButton.removeClass("nc--loading");
                        const message = response.result?.message || "<?= NETCAT_AI_SCENARIO_ERROR ?>";
                        dialog.show_error(message);
                    }
                    // status === "in_progress" - продолжаем polling
                } catch (e) {
                    stopPolling();
                    submitButton.removeClass("nc--loading");
                    const message = e.responseJSON?.message || e.message || "<?= NETCAT_AI_SCENARIO_ERROR ?>";
                    dialog.show_error(message);
                    console.error(e);
                }
            }

            // Первая проверка через 15 секунд
            pollingTimer = setInterval(checkStatus, POLLING_INTERVAL);
        }

        function stopPolling() {
            if (pollingTimer) {
                clearInterval(pollingTimer);
                pollingTimer = null;
            }
        }

        function initDialogCloseHandler() {
            // dialog — объект nc.ui.modal_dialog; событие dialogclose не инициируется,
            // поэтому перехватываем метод close() для остановки polling при закрытии
            const originalClose = dialog.close;
            dialog.close = function() {
                stopPolling();
                return originalClose.apply(this, arguments);
            };
        }

        function initCompanyContextBlockToggle() {
            dialog.find(".nc-ai-dialog-company-context-toggle").on("click", function () {
                dialog.find(".nc-ai-dialog-company-context").animate({
                    height: "toggle",
                    opacity: "toggle"
                }, {
                    duration: 300
                });
            });
        }
    }, 1);
</script>
