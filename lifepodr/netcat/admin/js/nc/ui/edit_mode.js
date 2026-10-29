/**
 * edit_mode.js — дополнение к nc_admin.js для новой разметки data-nc-*
 *
 * nc_admin.js управляет hover/toolbar/dropdown для .nc-infoblock и .nc-container.
 * Этот модуль дополняет логику для случаев, не покрытых nc_admin.js:
 * - pointer-events на тулбарах (CSS-правило не срабатывает без .nc-infoblock обёртки)
 * - сдвиг object toolbar при наложении на block toolbar
 */
(function () {
    "use strict";
    var $ = null;

    /**
     * pointer-events: CSS-правило .nc-infoblock.nc--hover .nc-infoblock-toolbar { pointer-events: all }
     * не всегда срабатывает — дублируем через inline style при hover
     */
    function initPointerEvents() {
        $(document).on("mouseenter", ".nc-infoblock", function () {
            $(this).find(".nc-infoblock-toolbar").css("pointer-events", "all");
        });
        $(document).on("mouseleave", ".nc-infoblock", function () {
            $(this).find(".nc-infoblock-toolbar").css("pointer-events", "");
        });
        $(document).on("mouseenter", ".nc-container", function () {
            $(this).children(".nc-infoblock-toolbar").css("pointer-events", "all");
        });
        $(document).on("mouseleave", ".nc-container", function () {
            $(this).children(".nc-infoblock-toolbar").css("pointer-events", "");
        });
    }

    /**
     * Object toolbar: сдвиг вниз при наложении на block toolbar + compact
     * (nc_admin.js делает это для .nc-infoblock-object внутри .nc-infoblock,
     *  но с новой разметкой ищет .nc-infoblock через closest и не находит обёртку)
     */
    function initObjectToolbarAdjust() {
        $(document).on("mouseenter", ".nc-infoblock-object", function () {
            var object = $(this);
            var block = object.closest('[data-nc-type="block"]');
            if (!block.length) { return; }

            setTimeout(function () {
                var objectToolbar = object.find(".nc-object-toolbar");
                var blockToolbar = block.find(".nc-infoblock-toolbar");
                if (!objectToolbar.length || !blockToolbar.length) { return; }

                objectToolbar.css("transform", "");

                var btOffset = blockToolbar.offset();
                var otOffset = objectToolbar.offset();
                if (btOffset && otOffset) {
                    var btBottom = btOffset.top + blockToolbar.outerHeight();
                    var colliding = !(btBottom < otOffset.top ||
                        btOffset.top > otOffset.top + objectToolbar.outerHeight() ||
                        btOffset.left + blockToolbar.outerWidth() < otOffset.left ||
                        btOffset.left > otOffset.left + objectToolbar.outerWidth());

                    if (colliding) {
                        objectToolbar.css("transform", "translateY(" + (Math.ceil(btBottom - otOffset.top) + 4) + "px)");
                    }
                }

                var isShort = block.hasClass("nc--is-short");
                objectToolbar.removeClass("nc--compact");
                if (isShort) {
                    objectToolbar.toggleClass("nc--compact", objectToolbar.width() > block.width() / 2);
                } else {
                    objectToolbar.toggleClass("nc--compact", objectToolbar.offset().left < object.offset().left);
                }
            }, 1);
        });
    }

    function init() {
        if (!window.NETCAT_PATH) { return; }
        initPointerEvents();
        initObjectToolbarAdjust();
        if (typeof nc_init_toolbar_dropdowns === "function") {
            nc_init_toolbar_dropdowns();
        }
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", function () {
            $ = window.$nc || window.jQuery;
            if ($) { init(); }
        });
    } else {
        $ = window.$nc || window.jQuery;
        if ($) { init(); }
    }

})();
