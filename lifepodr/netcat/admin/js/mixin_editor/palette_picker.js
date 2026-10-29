/**
 * Общая утилита для создания дропдауна выбора цвета из палитры
 * Используется в миксине "Цвет элементов" и в других миксинах с data-palette-color
 */
(function(window) {
    'use strict';

    var $ = window.$nc || window.jQuery || window.$;

    /**
     * Нормализация цвета в hex uppercase
     */
    function normalizeColor(color) {
        if (!color) return '';
        color = String(color).trim();

        if (color.charAt(0) === '#') {
            if (color.length === 4) {
                color = '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
            }
            if (color.length === 5) {
                color = '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3] + color[4] + color[4];
            }
            return color.toUpperCase();
        }

        var matchRgb = color.match(/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i);
        if (matchRgb) {
            var r = parseInt(matchRgb[1], 10).toString(16).padStart(2, '0');
            var g = parseInt(matchRgb[2], 10).toString(16).padStart(2, '0');
            var b = parseInt(matchRgb[3], 10).toString(16).padStart(2, '0');
            return ('#' + r + g + b).toUpperCase();
        }

        var matchRgba = color.match(/^rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/i);
        if (matchRgba) {
            var r = parseInt(matchRgba[1], 10).toString(16).padStart(2, '0');
            var g = parseInt(matchRgba[2], 10).toString(16).padStart(2, '0');
            var b = parseInt(matchRgba[3], 10).toString(16).padStart(2, '0');
            var a = Math.round(parseFloat(matchRgba[4]) * 255).toString(16).padStart(2, '0');
            if (a.toUpperCase() === 'FF') {
                return ('#' + r + g + b).toUpperCase();
            }
            return ('#' + r + g + b + a).toUpperCase();
        }

        return color;
    }

    /**
     * Конвертация цвета в hex для отображения
     */
    function colorToHex(color) {
        return normalizeColor(color);
    }

    /**
     * Конвертация цвета в rgba (для minicolors)
     * Поддерживает hex, rgb и rgba форматы
     */
    function hexToRgba(color) {
        if (!color) return 'rgba(0,0,0,1)';
        color = String(color).trim();

        // Если уже rgba
        var matchRgba = color.match(/^rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/i);
        if (matchRgba) {
            return 'rgba(' + matchRgba[1] + ',' + matchRgba[2] + ',' + matchRgba[3] + ',' + parseFloat(matchRgba[4]).toFixed(2) + ')';
        }

        // Если rgb
        var matchRgb = color.match(/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i);
        if (matchRgb) {
            return 'rgba(' + matchRgb[1] + ',' + matchRgb[2] + ',' + matchRgb[3] + ',1.00)';
        }

        // Если hex
        var hex = color.replace('#', '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        var r = parseInt(hex.substring(0, 2), 16) || 0;
        var g = parseInt(hex.substring(2, 4), 16) || 0;
        var b = parseInt(hex.substring(4, 6), 16) || 0;
        var a = hex.length >= 8 ? (parseInt(hex.substring(6, 8), 16) / 255) : 1;
        return 'rgba(' + r + ',' + g + ',' + b + ',' + a.toFixed(2) + ')';
    }

    /**
     * Форматирование rgba для отображения
     */
    function formatRgbaDisplay(color) {
        if (!color) return 'rgba(0, 0, 0, 1)';
        // Если уже rgba/rgb
        var match = color.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+))?\s*\)/i);
        if (match) {
            var r = match[1];
            var g = match[2];
            var b = match[3];
            var a = match[4] !== undefined ? parseFloat(match[4]) : 1;
            return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + a + ')';
        }
        // Если hex — конвертируем
        return hexToRgba(color).replace(/,/g, ', ');
    }

    /**
     * Сравнение цветов
     */
    function colorsEqual(a, b) {
        return normalizeColor(a) === normalizeColor(b);
    }

    /**
     * Установка CSS-переменной для цвета swatch
     */
    function setSwatchColor($el, color) {
        if ($el.length && $el[0]) {
            $el[0].style.setProperty('--swatch-color', color || 'transparent');
        }
        return $el;
    }

    /**
     * Получение палитры сайта из различных источников
     */
    function getSitePalette() {
        var palette = null;

        // Проверяем глобальный кэш
        try {
            if (window.ncSiteColorList && window.ncSiteColorList.palette) {
                palette = window.ncSiteColorList;
            } else if (window.parent && window.parent.ncSiteColorList && window.parent.ncSiteColorList.palette) {
                palette = window.parent.ncSiteColorList;
            } else if (window.top && window.top.ncSiteColorList && window.top.ncSiteColorList.palette) {
                palette = window.top.ncSiteColorList;
            }
        } catch(e) { /* cross-origin */ }

        return palette;
    }

    /**
     * Генерация уникального ключа для нового цвета
     */
    function generatePaletteKey(existingKeys) {
        var chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        var key;
        var attempts = 0;
        do {
            key = '';
            for (var i = 0; i < 4; i++) {
                key += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            attempts++;
        } while (existingKeys && existingKeys.indexOf(key) !== -1 && attempts < 100);
        return key;
    }

    /**
     * Разрешение CSS-переменной палитры в реальный цвет
     * @param {string} value - Значение (может быть var(--tpl-color-list-xxxx) или hex/rgb)
     * @param {object} palette - Объект палитры {key: color, ...}
     * @returns {string} - Разрешённый цвет или исходное значение
     */
    function resolveColorValue(value, palette) {
        if (!value) return '';

        // Проверяем, является ли значение CSS-переменной палитры
        var paletteMatch = value.match(/var\(--tpl-color-list-([a-z0-9]+)\)/i);
        if (paletteMatch) {
            var key = paletteMatch[1];

            // 1. Пробуем переданную палитру
            if (palette && palette[key]) {
                return palette[key];
            }

            // 2. Fallback: пробуем глобальный кэш ncSiteColorList
            var sitePalette = getSitePalette();
            if (sitePalette && sitePalette.palette && sitePalette.palette[key]) {
                return sitePalette.palette[key];
            }

            // 3. Fallback: пробуем ncSitePalette (публикуется миксином Цвет элементов)
            try {
                var contexts = [window];
                if (window.parent && window.parent !== window) contexts.push(window.parent);
                if (window.top && window.top !== window && window.top !== window.parent) contexts.push(window.top);

                for (var i = 0; i < contexts.length; i++) {
                    var ctx = contexts[i];
                    if (ctx.ncSitePalette && ctx.ncSitePalette.palette && ctx.ncSitePalette.palette[key]) {
                        return ctx.ncSitePalette.palette[key];
                    }
                }
            } catch(e) { /* cross-origin */ }

            // Если не удалось разрешить CSS-переменную, возвращаем пустую строку
            // чтобы swatch показал прозрачный фон вместо сломанной переменной
            return '';
        }

        return value;
    }

    /**
     * Создание дропдауна выбора цвета
     * @param {Object} options
     * @param {Object} options.palette - Объект палитры {key: color, ...}
     * @param {String} options.currentColor - Текущий выбранный цвет
     * @param {Boolean} options.canAddToPalette - Показывать ли чекбокс "в палитру"
     * @param {Number} options.siteId - ID сайта для добавления в палитру
     * @param {Function} options.onSelect - Callback при выборе цвета (color, paletteKey)
     * @param {Function} options.onClose - Callback при закрытии
     * @returns {jQuery} - Элемент дропдауна
     */
    function createDropdown(options) {
        var palette = options.palette || {};
        var roleColors = options.roleColors || {};

        // Если palette или roleColors пришли как JSON-строка, парсим
        if (typeof palette === 'string') {
            try { palette = JSON.parse(palette); } catch(e) { palette = {}; }
        }
        if (typeof roleColors === 'string') {
            try { roleColors = JSON.parse(roleColors); } catch(e) { roleColors = {}; }
        }
        var currentColor = options.currentColor || '';
        var canAddToPalette = options.canAddToPalette || false;
        var lang = options.lang || {
            COLOR_RESET: 'сброс',
            COLOR_APPLY: 'Выбрать',
            COLOR_SAVE_TO_LIST: 'сохранить цвет',
            COLOR_ROLE_FOREGROUND_MAIN: 'Текст',
            COLOR_ROLE_FOREGROUND_ACCENT: 'Текст доп.',
            COLOR_ROLE_BRAND: 'Бренд',
            COLOR_ROLE_BACKGROUND_MAIN: 'Фон',
            COLOR_ROLE_BACKGROUND_ACCENT: 'Фон доп.'
        };
        var siteId = options.siteId || options.catalogueId || null;
        var onSelect = options.onSelect || function() {};
        var onClose = options.onClose || function() {};

        var displayColor = currentColor || '#FFFFFF';
        var $dropdown = $('<div class="nc-palette-picker-dropdown"></div>');
        $dropdown.css('overflow', 'visible');

        // Названия ролей для отображения
        var roleLabels = {
            'foreground_main': lang.COLOR_ROLE_FOREGROUND_MAIN || 'Текст',
            'foreground_accent': lang.COLOR_ROLE_FOREGROUND_ACCENT || 'Текст доп.',
            'brand': lang.COLOR_ROLE_BRAND || 'Бренд',
            'background_main': lang.COLOR_ROLE_BACKGROUND_MAIN || 'Фон',
            'background_accent': lang.COLOR_ROLE_BACKGROUND_ACCENT || 'Фон доп.'
        };

        // Порядок ролей
        var roleOrder = ['foreground_main', 'foreground_accent', 'brand', 'background_main', 'background_accent'];

        // Собираем hex-значения и ключи палитры ролевых цветов для исключения дубликатов
        var roleColorValues = [];
        var rolePaletteKeys = [];
        roleOrder.forEach(function(role) {
            if (roleColors[role]) {
                var roleColor = roleColors[role];
                roleColorValues.push(normalizeColor(roleColor));
                // Если ролевой цвет — ссылка на палитру, извлекаем ключ
                var paletteMatch = roleColor.match(/var\(--tpl-color-list-([a-z0-9]+)\)/i);
                if (paletteMatch) {
                    rolePaletteKeys.push(paletteMatch[1]);
                }
            }
        });

        // === Секция: ролевые цвета ===
        var $rolesSection = $('<div class="nc-palette-picker-section nc-palette-picker-roles"></div>');
        $rolesSection.css({
            'display': 'flex',
            'flex-wrap': 'wrap',
            'gap': '8px',
            'padding': '12px',
            'padding-bottom': '8px',
            'border-bottom': '1px solid #eee',
            'box-sizing': 'border-box',
            'overflow': 'visible'
        });
        var hasRoles = false;

        roleOrder.forEach(function(role) {
            var color = roleColors[role];
            if (!color) return;
            hasRoles = true;

            // Разрешаем CSS-переменную в реальный цвет для отображения
            var resolvedColor = resolveColorValue(color, palette);
            // Если не удалось разрешить (пустая строка), пропускаем эту роль
            if (!resolvedColor) return;
            var isSelected = colorsEqual(resolvedColor, currentColor);
            var label = roleLabels[role] || role;
            // ВАЖНО: в data-color сохраняем разрешённый hex (для правильного отображения swatch после выбора)
            var $item = $('<div class="nc-palette-picker-color nc-palette-picker-role' + (isSelected ? ' nc--selected' : '') + '"></div>')
                .attr({ 'data-color': resolvedColor, 'data-role': role });
            $item.css({
                'width': '44px',
                'text-align': 'center',
                'cursor': 'pointer'
            });
            var $swatch = $('<div class="nc-palette-picker-color-swatch"></div>');
            $swatch.css({
                'width': '32px',
                'height': '32px',
                'border-radius': '4px',
                'margin': '0 auto 4px',
                'background-color': 'var(--swatch-color, transparent)',
                'box-shadow': 'inset 0 0 0 1px rgba(0,0,0,0.1)'
            });
            setSwatchColor($swatch, resolvedColor);
            $item.append($swatch);
            var $label = $('<div class="nc-palette-picker-color-label"></div>').text(label);
            $label.css({
                'font-size': '9px',
                'color': '#666',
                'text-align': 'center',
                'white-space': 'nowrap',
                'line-height': '1.2'
            });
            $item.append($label);
            $rolesSection.append($item);
        });

        if (hasRoles) {
            $dropdown.append($rolesSection);
        }

        // === Секция: индексные цвета палитры (без дубликатов ролевых) ===
        var $colorsSection = $('<div class="nc-palette-picker-section nc-palette-picker-colors"></div>');
        $colorsSection.css({
            'display': 'flex',
            'flex-wrap': 'wrap',
            'gap': '8px',
            'padding': '12px',
            'box-sizing': 'border-box',
            'overflow': 'visible'
        });
        var paletteKeys = Object.keys(palette);

        // Общие стили для цветовых элементов
        function applyColorItemStyles($item, $swatch, $text) {
            $item.css({
                'width': '44px',
                'text-align': 'center',
                'cursor': 'pointer'
            });
            $swatch.css({
                'width': '32px',
                'height': '32px',
                'border-radius': '4px',
                'margin': '0 auto 4px',
                'background-color': 'var(--swatch-color, transparent)',
                'box-shadow': 'inset 0 0 0 1px rgba(0,0,0,0.1)'
            });
            $text.css({
                'font-size': '9px',
                'color': '#666',
                'text-align': 'center',
                'white-space': 'nowrap',
                'line-height': '1.2'
            });
        }

        paletteKeys.forEach(function(key) {
            var color = palette[key];
            var normalizedColor = normalizeColor(color);

            // Пропускаем цвета, которые уже есть в ролях (по hex или по ключу палитры)
            if (roleColorValues.indexOf(normalizedColor) !== -1 || rolePaletteKeys.indexOf(key) !== -1) {
                return;
            }

            var isSelected = colorsEqual(color, currentColor);
            var hexValue = colorToHex(color);
            var $item = $('<div class="nc-palette-picker-color' + (isSelected ? ' nc--selected' : '') + '"></div>')
                .attr({ 'data-color': color, 'data-palette-key': key });
            var $swatch = $('<div class="nc-palette-picker-color-swatch"></div>');
            setSwatchColor($swatch, color);
            var $hex = $('<div class="nc-palette-picker-color-hex"></div>').text(hexValue);
            applyColorItemStyles($item, $swatch, $hex);
            $item.append($swatch);
            $item.append($hex);
            $colorsSection.append($item);
        });

        // Элемент сброса
        var isResetSelected = !currentColor;
        var $resetItem = $('<div class="nc-palette-picker-color nc-palette-picker-reset' + (isResetSelected ? ' nc--selected' : '') + '"></div>')
            .attr({ 'data-color': '' });
        var $resetSwatch = $('<div class="nc-palette-picker-color-swatch nc--empty"></div>');
        var $resetText = $('<div class="nc-palette-picker-color-hex"></div>').text(lang.COLOR_RESET);
        applyColorItemStyles($resetItem, $resetSwatch, $resetText);
        $resetSwatch.css({
            'background': 'linear-gradient(135deg, #fff 45%, #ff4444 50%, #fff 55%)'
        });
        $resetItem.append($resetSwatch);
        $resetItem.append($resetText);
        $colorsSection.append($resetItem);
        $dropdown.append($colorsSection);

        // === Секция: колорпикер ===
        var $pickerSection = $('<div class="nc-palette-picker-section nc-palette-picker-custom"></div>');
        $pickerSection.css('overflow', 'visible');
        var $pickerInput = $('<input type="text" class="nc-palette-picker-custom-input">');

        // Строка 1: hex инпут + rgba надпись
        var $colorRow = $('<div class="nc-palette-picker-color-row"></div>');
        var $hexInput = $('<input type="text" class="nc-palette-picker-hex-input" placeholder="#FFFFFF">')
            .val(colorToHex(displayColor));
        var $rgbaDisplay = $('<span class="nc-palette-picker-rgba-display"></span>')
            .text(formatRgbaDisplay(displayColor));
        $colorRow.append($hexInput).append($rgbaDisplay);

        // Строка 2: квадратик превью + кнопка "применить" + чекбокс
        var $applyRow = $('<div class="nc-palette-picker-apply-row"></div>');
        var $previewSwatch = $('<div class="nc-palette-picker-preview-swatch"></div>');
        setSwatchColor($previewSwatch, displayColor);
        var $applyButton = $('<button type="button" class="nc-palette-picker-apply-button">применить</button>');
        $applyButton.css({
            'height': '32px',
            'padding': '0 16px',
            'background': '#1a87c2',
            'color': '#fff',
            'border': 'none',
            'border-radius': '4px',
            'font-size': '13px',
            'cursor': 'pointer',
            'white-space': 'nowrap'
        });
        $applyRow.append($previewSwatch).append($applyButton);

        // Чекбокс "сохранить цвет"
        var $addToPaletteCheckbox = null;
        if (canAddToPalette && siteId) {
            var $checkboxLabel = $('<label class="nc-palette-picker-add-checkbox"><input type="checkbox"><span>' + lang.COLOR_SAVE_TO_LIST + '</span></label>');
            $addToPaletteCheckbox = $checkboxLabel.find('input');
            $applyRow.append($checkboxLabel);
        }

        $pickerSection.append($pickerInput).append($colorRow).append($applyRow);
        $dropdown.append($pickerSection);

        // Инициализируем minicolors
        var initialRgba = hexToRgba(displayColor);
        $pickerInput.minicolors({
            format: 'rgb',
            keywords: 'transparent',
            opacity: true,
            theme: 'netcat',
            inline: true,
            change: function(value) {
                setSwatchColor($previewSwatch, value);
                $hexInput.val(colorToHex(value));
                $rgbaDisplay.text(formatRgbaDisplay(value));
            }
        });
        // Устанавливаем значение после полной инициализации
        setTimeout(function() {
            $pickerInput.minicolors('value', initialRgba);
        }, 0);

        // Ввод hex вручную
        $hexInput.on('input change', function() {
            var val = $(this).val().trim();
            if (val.match(/^#[0-9a-f]{3,8}$/i)) {
                $pickerInput.minicolors('value', val);
                $rgbaDisplay.text(formatRgbaDisplay(val));
            }
        });

        // Функция закрытия и удаления dropdown
        function closeDropdown() {
            cleanup();
            $dropdown.remove();
            onClose();
        }

        // Клик по цвету из палитры или ролевому цвету
        $dropdown.on('click', '.nc-palette-picker-color', function(e) {
            e.stopPropagation();
            var $btn = $(this);
            var color = $btn.data('color');
            var paletteKey = $btn.data('palette-key') || null;
            var roleKey = $btn.data('role') || null;
            var isReset = $btn.hasClass('nc-palette-picker-reset') || color === '';

            onSelect(isReset ? '' : color, paletteKey, roleKey);
            closeDropdown();
        });

        // Функция применения цвета из колорпикера
        function applyCustomColor(e) {
            e.stopPropagation();
            var customColor = $pickerInput.minicolors('value');
            if (customColor) {
                var colorToSave = customColor;

                // Добавляем в палитру если чекбокс включен
                if ($addToPaletteCheckbox && $addToPaletteCheckbox.is(':checked') && siteId) {
                    var hexColor = colorToHex(customColor);
                    $.ajax({
                        url: NETCAT_PATH + 'action.php?ctrl=admin.site_color_list&action=add_color&isNaked=1',
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            site_id: siteId,
                            color: hexColor
                        },
                        success: function(response) {
                            if (response.result === 'ok' && response.palette) {
                                // Фильтруем палитру из БД, исключая удалённые цвета
                                var filteredPalette = {};
                                var deletedColors = (window.ncSiteColorList && window.ncSiteColorList.deletedColors) || [];
                                for (var key in response.palette) {
                                    var isDeleted = deletedColors.some(function(dc) {
                                        return dc.key === key;
                                    });
                                    if (!isDeleted) {
                                        filteredPalette[key] = response.palette[key];
                                    }
                                }

                                // Обновляем глобальный кэш во всех контекстах
                                var eventData = {palette: filteredPalette};
                                var contexts = [window];
                                try { if (window.parent && window.parent !== window) contexts.push(window.parent); } catch(e) {}
                                try { if (window.top && window.top !== window && window.top !== window.parent) contexts.push(window.top); } catch(e) {}

                                contexts.forEach(function(ctx) {
                                    try {
                                        if (!ctx.ncSiteColorList) ctx.ncSiteColorList = {};
                                        ctx.ncSiteColorList.palette = filteredPalette;
                                        // Сохраняем deletedColors
                                        if (deletedColors.length) {
                                            ctx.ncSiteColorList.deletedColors = deletedColors;
                                        }
                                        // Триггерим событие в каждом контексте
                                        var ctxJq = ctx.$nc || ctx.jQuery || ctx.$;
                                        if (ctxJq) {
                                            ctxJq(ctx.document).trigger('nc-palette-updated', [eventData]);
                                        }
                                    } catch(e) { /* cross-origin or other error */ }
                                });

                                // Сохраняем ссылку на CSS-переменную, а не hex
                                // response.key содержит ключ добавленного/найденного цвета
                                if (response.key) {
                                    onSelect(hexColor, response.key);
                                } else {
                                    onSelect(hexColor, null);
                                }
                                closeDropdown();
                            }
                        },
                        error: function() {
                            // При ошибке сохраняем просто hex
                            onSelect(hexColor, null);
                            closeDropdown();
                        }
                    });
                    return; // Ждём ответа от сервера
                }

                onSelect(colorToSave, null);
                closeDropdown();
            }
        }

        // Клик по превью или кнопке "Выбрать" — применить цвет из колорпикера
        $previewSwatch.on('click', applyCustomColor);
        $applyButton.on('click', applyCustomColor);

        // Предотвращаем закрытие при кликах внутри
        $dropdown.on('click', function(e) {
            e.stopPropagation();
        });

        // Очистка при закрытии
        function cleanup() {
            $pickerInput.minicolors('destroy');
        }

        // Метод для внешнего закрытия
        $dropdown.data('cleanup', cleanup);

        return $dropdown;
    }

    // Экспорт
    window.ncPalettePicker = {
        create: createDropdown,
        getSitePalette: getSitePalette,
        normalizeColor: normalizeColor,
        colorToHex: colorToHex,
        colorsEqual: colorsEqual,
        setSwatchColor: setSwatchColor,
        generatePaletteKey: generatePaletteKey,
        resolveColorValue: resolveColorValue
    };

})(window);
