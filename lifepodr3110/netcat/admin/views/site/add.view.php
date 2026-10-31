<?php

if (!class_exists('nc_core')) {
    die;
}

?>

<div class="nc-site-add-admin">
    <form class="nc-form nc--vertical nc-site-add-form" action="/netcat/action.php" method="post">
        <input type="hidden" name="ctrl" value="admin.site">
        <input type="hidden" name="action" value="create">
        <input type="hidden" name="site_id" value="0">

        <div class="control-wrapper">
            <div class="nc-form-row">
                <label for="form_domain"><?= CONTROL_CONTENT_CATALOUGE_FUNCS_CATALOGUEFORM_NAME ?>:</label>
                <input id="form_domain" type="text" name="data[Catalogue_Name]" value=""/>
            </div>

            <div class="nc-form-row">
                <label for="form_domain"><?= CONTROL_CONTENT_CATALOUGE_FUNCS_CATALOGUEFORM_DOMAIN ?>:</label>
                <input id="form_domain" type="text" name="data[Domain]" value="<?= htmlspecialchars($domain) ?>"/>
                <div class="nc-site-add-domain-hint"><?= CONTROL_CONTENT_CATALOUGE_DOMAIN_HINT ?></div>
            </div>

            <div class="nc-form-row">
                <div class="button-group">
                    <label class="button-group-item selected"><input data-site-type="nc-site-add-list" type="radio" name="site_type"
                                value="store_site"
                                checked><?= CONTROL_CONTENT_SITE_ADD_WITH_CONTENT ?></label>
                    <label class="button-group-item"><input type="radio" name="site_type"
                                value="ai_site"><?= CONTROL_CONTENT_SITE_ADD_WITH_AI_CONTENT ?></label>
                    <label class="button-group-item"><input type="radio" name="site_type"
                                value="blank"><?= CONTROL_CONTENT_SITE_ADD_EMPTY ?>
                    </label>
                </div>
            </div>
        </div>

        <div class="nc-site-content store_site">
            <?php if (!empty($data['error_text']) || !count($data['data']['sites'])): ?>
                <div class='nc-alert nc--red'><i class='nc-icon-l nc--status-error'></i><?= $data['error_text'] ?></div>
            <?php else: ?>

                <h2 class="nc-site-add-category-heading"><?= CONTROL_CONTENT_SITE_ADD_CATEGORY_HEADING ?>:</h2>
                <div class="nc-site-add-site-category-filter">
                    <div class="nc-site-add-category-label selected"><?= CONTROL_CONTENT_SITE_CATEGORY_ANY ?></div>

                    <?php foreach ($data['data']['site_types'] as $id => $site_type): ?>
                        <div class="nc-site-add-category-label" data-site-type-id="<?= $id ?>"><?= $site_type ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="nc-site-add-site-list-group">
                    <?php foreach ($data['data']['sites'] as $site): ?>
                        <div class="nc-site-add-list-site" style="display: none;" data-site-id="<?= $site['id'] ?>"
                                data-site-type-id="<?= $site['site_type_id'] ?>">
                            <div class='image-wrapper'>
                                <img src="<?= $site['screenshot'] ?>" alt="screenshot">
                            </div>

                            <div class="description-wrapper">
                                <div class="nc-site-add-list-site-name"><?= $site['name'] ?></div>
                                <div class="button-group">
                                    <a href="<?= $site['demo_url'] ?>" target="_blank"
                                            class="demo-link"><?= CONTROL_CONTENT_SITE_ADD_PREVIEW ?></a>
                                    <div class="site-select-button"><?= NETCAT_TRASH_FILTER_APPLY ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </form>
</div>

<script>
    (function () {
        initSiteTypeChangeListener();
        initCategoryChangeListener();
        initSiteChangeListener();

        function initSiteTypeChangeListener() {
            $nc(".nc-site-add-form input[name=site_type]").change(function () {
                $nc(".control-wrapper .button-group-item").removeClass("selected");
                $nc(this).parent().toggleClass("selected");
                $nc(".nc-site-content").hide();
                $nc(".nc-site-content." + $nc(this).val()).show();
            });
        }

        function initCategoryChangeListener() {
            $nc(".nc-site-add-category-label").on("click", function () {
                const siteTypeId = $nc(this).data("site-type-id") || 0;
                $nc(".nc-site-add-category-label").removeClass("selected");
                $nc(this).addClass("selected");

                $nc(".nc-site-add-list-site").hide();

                if (siteTypeId) {
                    $nc(".nc-site-add-list-site").filter(`[data-site-type-id="${siteTypeId}"]`).show();
                } else {
                    $nc(".nc-site-add-list-site").show();
                }
            });

            $nc(".nc-site-add-category-label").eq(0).trigger("click");
        }

        function initSiteChangeListener() {
            const siteIdInput = $nc(".nc-site-add-form").find("input[name='site_id']");
            const siteNameInput = $nc(".nc-site-add-form input[name='data[Catalogue_Name]']");

            $nc(".nc-site-add-list-site").on("click", function () {
                $nc(".nc-site-add-list-site").removeClass("selected");
                $nc(".site-select-button").text('<?= NETCAT_TRASH_FILTER_APPLY ?>');

                const _this = $nc(this);
                _this.addClass("selected");
                _this.find(".site-select-button").text('<?= CONTROL_CONTENT_SITE_ADD_SITE_SELECTED_BUTTON_CAPTION ?>');
                siteIdInput.val(_this.data("site-id"));
                siteNameInput.val(_this.find(".nc-site-add-list-site-name").text());
            });
        }
    })();
</script>
