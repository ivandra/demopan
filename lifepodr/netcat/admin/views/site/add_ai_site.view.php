<?php

if (!class_exists('nc_core')) {
    die;
}

/** @var array $payload */
?>

<div class="nc-site-add-admin">
    <form class="nc-form nc--vertical nc-site-add-form" action="/netcat/action.php" method="post">
        <input type="hidden" name="ctrl" value="admin.site">
        <input type="hidden" name="action" value="download_and_import">
        <input type="hidden" name="data[Catalogue_Name]" value="<?= nc_array_value($payload['data'], 'Catalogue_Name') ?>">
        <input type="hidden" name="data[Domain]" value="<?= nc_array_value($payload['data'], 'Domain') ?>">

        <div class="nc-site-content ai_site">
            <?php
            $nc_core = nc_core::get_object();
            $system_info = nc_get_system_info();
            $query_parameters = http_build_query(
                array(
                    'copy_id' => $nc_core->get_copy_id(),
                    'code' => $system_info["code"],
                    'product_number' => $system_info["product_number"],
                    'domain' => $nc_core->DOMAIN_NAME,
                    'cms_version' => $nc_core->get_full_version_number(),
                    'is_standalone' => 1,
                ),
                '',
                '&'
            ) ?>

            <iframe id="ai-content-generation" src="<?= AI_SITE_CONTENT_GENERATION_URL . '/?' . $query_parameters ?>"></iframe>
        </div>
    </form>
</div>

<script>
    (function () {
        const parentWindow = window.parent.document;
        const siteGenerationIframe = document.getElementById("ai-content-generation");

        transformPageElements();
        initListeners();

        function transformPageElements() {
            $nc(parentWindow).find("body").addClass("ai-generation-page");
            $nc(parentWindow).find(".nc-navbar .nc-menu a").on("click", e => e.preventDefault());
            $nc(parentWindow).find(".nc-navbar .nc-menu").after(`<h3 class="nc-site-add-navbar-heading"><?= CONTROL_CONTENT_SITE_ADD_PAGE_HEADING ?></h3>`);
            $nc(parentWindow).find(".nc-navbar .nc-tray").append("<li class='nc-site-add-generation-cancel'><a href='#'><?= CONTROL_BUTTON_CANCEL ?></a></li");
        }

        function restorePageElements() {
            $nc(parentWindow).find("body").removeClass("ai-generation-page");
            $nc(parentWindow).find(".nc-navbar .nc-menu a").off("click");
            $nc(parentWindow).find(".nc-site-add-navbar-heading").remove();
            $nc(parentWindow).find(".nc-site-add-generation-cancel").remove();
        }

        function initListeners() {
            $nc(parentWindow).find(".nc-site-add-generation-cancel a").on("click", e => {
                e.preventDefault();
                siteGenerationIframe.contentWindow.postMessage({ type: "onUserStandaloneSiteGenerationCancelled" }, "*");
                restorePageElements();
                window.location.href = NETCAT_PATH + "action.php?ctrl=admin.site&action=show_add_form";
            })

            let createdSiteId = 0;

            window.addEventListener("message", async function (event) {
                if (event.origin !== "<?= AI_SITE_CONTENT_GENERATION_URL ?>") {
                    return;
                }

                if (event.data.type === "onBeforeGenerationStarted") {
                    try {
                        const formData = new FormData($nc(".nc-site-add-form")[0]);
                        formData.set("action", "download_and_import");
                        formData.set("site_type", "ai_site");

                        const { data } = await $nc.ajax($nc(".nc-site-add-form").attr("action"), {
                            type: "post",
                            dataType: "json",
                            data: formData,
                            processData: false,
                            contentType: false,
                        });

                        siteGenerationIframe.contentWindow.postMessage({ type: "onUserStandaloneSiteDeployedSuccess", payload: data }, "*");
                        createdSiteId = data.site_id;
                    } catch (e) {
                        const error = "responseJSON" in e && "message" in e.responseJSON ? e.responseJSON.message : "Standalone site deploying error";
                        siteGenerationIframe.contentWindow.postMessage({ type: "onUserStandaloneSiteDeployedFail", error }, "*");
                        console.error(e);
                    }
                }

                if (event.data.type === "onAfterGenerationCompleted" && createdSiteId) {
                    await $nc.get("<?= $nc_core->ADMIN_PATH ?>content_change.php?<?= $nc_core->token->get_url() ?>");
                    restorePageElements();
                    window.location = `${NETCAT_PATH}action.php?ctrl=admin.site&action=complete_ai_site_importing&site_id=${createdSiteId}`;
                }
            })
        }
    })();
</script>
