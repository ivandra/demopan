// IE9+: Object.keys(), Array.forEach(), Array.indexOf(), JSON

// Зависимости: jquery.ui.sortable; minicolors

(function($) {

    // {a: {b: 1}} → {'a.b': 1}
    function flatten(object) {
        return Object.keys(object).reduce(function(result, key) {
            if ((typeof object[key]) === 'object' && object[key]) {
                var flat_object = flatten(object[key]);
                Object.keys(flat_object).forEach(function(key2) {
                    result[key + '.' + key2] = flat_object[key2];
                });
            } else {
                result[key] = object[key];
            }
            return result;
        }, {});
    }

    // {'a.b': 1} → {a: {b: 1}}
    function unflatten(object) {
        var result = {};
        for (var flat_key in object) {
            set_multilevel_object_value(result, flat_key.split('.'), object[flat_key]);
        }
        return result;
    }

    function set_multilevel_object_value(target, keys, end_value) {
        var recipient = target,
            last_index = keys.length - 1;
        for (var i = 0; i < last_index; i++) {
            if (recipient[keys[i]] === undefined) {
                recipient[keys[i]] = {};
            }
            recipient = recipient[keys[i]];
        }
        recipient[keys[last_index]] = end_value;
        return target;
    }

    function is_object(item) {
        return item && typeof item === 'object' && !Array.isArray(item);
    }

    function merge_objects(target, source) {
        if (is_object(target) && is_object(source)) {
            for (const key in source) {
                if (is_object(source[key])) {
                    if (!target[key]) {
                        Object.assign(target, { [key]: {} });
                    }
                    merge_objects(target[key], source[key]);
                } else {
                    Object.assign(target, { [key]: source[key] });
                }
            }
        }
        return target;
    }

    function replace_from_to(string, from, to) {
        return string.replace('%from', from).replace('%to', to);
    }

    var font_select_options_cache = '';

    /**
     * Constructor
     */
    var nc_mixin_settings_editor = window.nc_mixin_settings_editor = function(options) {
        if (!(this instanceof nc_mixin_settings_editor)) {
            return new nc_mixin_settings_editor(options);
        }

        for (var setting in options) {
            if (setting in this) {
                this[setting] = options[setting];
            }
        }

        this.scope = this.scope || this.field_name_prefix || 'Index';

        var target_container = $(this.target);
        target_container.html($('.nc-mixins-editor-template').html());
        this.container = target_container.find('.nc-mixins-editor');

        this.lang = this.container.data('lang');
        this.set_field_names();

        this.init_breakpoint_type_container(); // показываем или прячем блок с выбором типа брейкпоинтов
        this.reset_breakpoints();

        // запоминаем порядок групп миксинов для сохранения параметров в правильном порядке
        this.mixin_type_order = this.get_mixin_type_order();

        // устанавливаем/обрабатываем настройки миксинов из options.own_settings
        this.set_own_settings(this.own_settings);

        // Инициализируем палитру из PHP, если она не была найдена в own_settings
        this.init_site_color_list_from_php();

        this.init_preset_container(); // показываем или прячем блок с выбором пресета
        this.apply_preset_settings(); // применяем выбранный пресет

        this.rebuild_table();
        this.init_event_handlers();
        this.init_toolbar_buttons();

        // инициализация редактора свойств для четырёх сторон с 🔒
        var editor = this;
        this.find('.nc-mixins-mixin-settings-lock-sides-button').each(function() {
            editor.init_lock_sides_button($(this));
        });

        // инициализация селектов выбора шрифтов
        this.load_fonts();
        this.find('select.nc-mixins-mixin-font-select').each(function() {
            editor.init_font_select($(this));
        });

        // инициализация полей выбора цвета
        this.init_color_inputs(this.find('[data-color-input]'));

        // инициализация новых компонентов выбора цвета из палитры
        this.init_palette_color_pickers(this.find('[data-palette-color]'));

        // инициализация поля загрузчика файлов
        this.init_uploader(this.find('[data-uploader]'));

        this.is_initialized = true;
        this.update_mixin_json(); // изначально инпут пустой, и его значение не обновляется, пока is_initialized = false
    };

    // "Static" properties:
    /** Событие после инициализации формы с настройками миксина (установки значений) */
    nc_mixin_settings_editor.mixin_settings_set_values_event = 'mixin_settings_set_values_event';
    /** Событие после добавления новой пустой множественной настройки миксина (только после нажатия пользователем на «добавить») */
    nc_mixin_settings_editor.mixin_settings_new_row_event = 'mixin_settings_new_row_event';
    /** Событие после установки типа брейкпоинтов (также срабатывает при инициализации редактора **/
    nc_mixin_settings_editor.breakpoint_type_change_event = 'breakpoint_type_change';

    // Instance properties & methods:
    nc_mixin_settings_editor.prototype = {
        // брейкпоинт «любая ширина»
        MAX_WIDTH: 9999,

        is_initialized: false,

        // приходит снаружи из настроек, передаваемых в конструктор:
        target: '#nc_mixins_editor_container',
        field_name_template: 'data[%s]',
        field_name_prefix: '',
        scope: null, // совпадает с field_name_prefix за исключением некоторых особых случаев (field_name_prefix = 'MainArea')
        mixin_presets: [],
        own_settings: {},
        breakpoint_type: 'block',
        component_template_id: undefined, // ID шаблона компонента или ID компонента, для которого открыт диалог
        infoblock_id: undefined,
        subdivision_id: undefined, // ID раздела (для отличия от уровня сайта)
        site_id: undefined, // ID сайта (для добавления цветов в палитру)
        site_color_list: null, // Палитра сайта, переданная из PHP
        show_preset_select: false,
        show_breakpoint_type_select: false,
        slot_allowed_types: [], // для слотов: массив разрешённых типов миксинов (например, ['border_radius', 'shadow'])

        // приходит снаружи из data-атрибутов
        lang: {},
        fonts: [],

        // вычисляется на основании имеющихся данных:
        container: null,
        selectors: {}, // Set бы подошёл лучше, но он есть только в IE11+
        breakpoints: {},
        inherited_settings: {},
        temporary_own_settings: {},
        mixin_type_order: [],
        calculated_colors: {},

        storage_copy_key: 'nc_mixin_editor_copy',

        constructor: nc_mixin_settings_editor,

        /**
         * Находит элементы внутри редактора
         * @param selector
         * @return {*}
         */
        find: function(selector) {
            return this.container.find(selector);
        },

        /**
         * Инициализирует обработчики событий редактора
         */
        init_event_handlers: function() {
            var editor = this;

            // выбор пресета
            this.find('.nc-mixins-preset-select').change($.proxy(this, 'on_preset_select_change'));

            // редактирование пресета
            this.find('.nc-mixins-preset-actions .nc--edit').click($.proxy(this, 'open_preset_edit_dialog'));

            // удаление пресета
            this.find('.nc-mixins-preset-actions .nc--remove').click($.proxy(this, 'open_preset_delete_dialog'));

            // изменение типа брейкпоинтов
            this.find('.nc-mixins-breakpoint-type-select').change($.proxy(this, 'on_breakpoint_type_select_change')).change();

            // выбор селектора
            this.find('.nc-mixins-selector-select').change($.proxy(this, 'on_selector_select_change'));

            // заголовок ячеек диапазонов:
            // — добавление брейкпоинта
            this.container.on('click', '.nc-mixins-breakpoint-add-button', $.proxy(this, 'show_new_breakpoint_dialog'));

            // — редактирование брейкпоинта
            this.container.on('click', '.nc-mixins-breakpoint', $.proxy(this, 'show_edit_breakpoint_dialog'));

            // ячейки диапазонов:
            // — добавление настроек
            // — редактирование настроек
            this.container.on('click', '.nc-mixins-add-setting, .nc-mixins-settings-marker', $.proxy(this, 'show_mixin_settings'));

            // щелчок на нераскрытую ячейку настроек
            this.container.on('click', '.nc-mixins-settings-cell:not(.nc--active)', function() {
                var last_width_cell = $(this).prev(),
                    settings_marker = last_width_cell.find('.nc-mixins-settings-marker');
                if (settings_marker.length) {
                    settings_marker.click();
                } else {
                    last_width_cell.find('.nc-mixins-add-setting').click();
                }
            });

            // выбор миксина
            this.container.on('change', '.nc-mixins-mixin-select', function() {
                editor.on_mixin_select_change($(this), false);
            });

            // изменение настроек миксина
            // (также срабатывает каскадом при добавлении настроек для диапазона)
            this.container.on('change input', '.nc-mixins-mixin-settings :input', $.proxy(this, 'update_current_mixin_settings'));

            // удаление настроек
            this.container.on('click', '.nc-mixins-mixin-remove', $.proxy(this, 'on_remove_settings_button_click'));

            // добавление множественных настроек миксина
            this.container.on('click', '.nc-mixins-mixin-settings-row-add', $.proxy(this, 'on_mixin_multiple_settings_add_button_click'));

            // удаление множественных настроек миксина
            this.container.on('click', '.nc-mixins-mixin-settings-row-remove', $.proxy(this, 'on_mixin_multiple_settings_remove_button_click'));
        },

        /**
         * Возвращает имя input'а, исходя из настроек field_name_template и field_name_prefix
         * @param {string} field_name
         * @returns {string}
         */
        get_field_name(field_name) {
            return this.field_name_template.replace('%s', (this.field_name_prefix ? this.field_name_prefix + '_' : '') + field_name);
        },

        /**
         * Устанавливает атрибут name input’ов в соответствии с field_name_template и field_name_prefix
         */
        set_field_names: function() {
            var fields = {
                '.nc-mixins-json': 'Mixin_Settings',
                '.nc-mixins-preset-select': 'Mixin_Preset_ID',
                '.nc-mixins-breakpoint-type-select' : 'Mixin_BreakpointType'
            };
            for (var selector in fields) {
                this.find(selector).attr('name', this.get_field_name(fields[selector]));
            }
        },

        /**
         * Возвращает значения всех полей в указанном контейнере
         * (используется для переноса настроек из формы в own_settings и далее
         * в скрытое поле со всеми настройками в JSON)
         * @param container
         * @returns {{}}
         */
        get_mixin_input_values: function(container) {
            var values = {};
            var inputs = container.find('input, select, textarea').filter('[name^="mixin."]');
            inputs.each(function() {
                var input = $(this),
                    name_without_prefix = this.name.replace(/^mixin\./, ''),
                    value = input.val();

                if (input.is(':radio') && !input.is(':checked')) {
                    return;
                }

                // Для повторяющихся блоков настроек (например, слоёв фона или настроек колонок)
                // используется следующая структура:
                // div.nc-mixins-mixin-settings-rows > div.nc-mixins-mixin-settings-row
                // '#' в названии input’ов при сохранении заменяется на порядковый номер строки ...-row.
                if (name_without_prefix.indexOf('#') >= 0) {
                    // (а) пропускаем, если это инпут в div.nc-mixins-mixin-settings-row-template
                    if (input.closest('.nc-mixins-mixin-settings-row-template').length) {
                        return;
                    }
                    // (б) заменяем '#' на порядковый индекс строки
                    var row = input.closest('.nc-mixins-mixin-settings-row'),
                        index = row.parent().children().index(row);
                    if (index >= 0) {
                        name_without_prefix = name_without_prefix.replace(/#/g, index);
                    }
                }
                if (input.is(':checkbox')) {
                    values[name_without_prefix] = input.is(':checked') ? value : '';
                } else if (input.is('[data-color-input][data-nc-swatches][data-sync-color^="var"]')) {
                    values[name_without_prefix] = input.attr('data-sync-color');
                } else if (input.is('[data-palette-color]')) {
                    // Для palette-picker: если есть data-sync-color с var(), берём его, иначе value
                    var syncColor = input.attr('data-sync-color');
                    values[name_without_prefix] = (syncColor && syncColor.indexOf('var(') === 0) ? syncColor : value;
                } else {
                    values[name_without_prefix] = value;
                }
            });
            return values;
        },

        /**
         * Устанавливает унаследованные от пресета настройки
         * @param inherited_settings
         */
        set_inherited_settings: function(inherited_settings) {
            var had_preset = !$.isEmptyObject(this.inherited_settings),
                has_new_preset = !$.isEmptyObject(inherited_settings);

            if (has_new_preset) {
                // удаляем брейкпоинты, которых нет в пресете
                var existing_breakpoints = Object.keys(this.breakpoints),
                    inherited_breakpoints = this.get_breakpoints_as_array_from_settings(inherited_settings),
                    breakpoints_to_delete = existing_breakpoints.filter(function(value) {
                        return inherited_breakpoints.indexOf(value) < 0;
                    });
                if (breakpoints_to_delete.length) {
                    // @todo предупреждать, что будут потеряны настройки (удалены брейкпоинты)
                    // @todo (но только если это изменение, сделанное пользователем, а не инициализация?)
                    for (var i = 0; i < breakpoints_to_delete.length; i++) {
                        this.remove_breakpoint(breakpoints_to_delete[i]);
                    }
                    this.after_breakpoint_change();
                }
            } else if (had_preset) {
                // если был выбран пресет, но потом он «отвязан», то копируем настройки пресета в блок
                // @todo? спрашивать, переносить ли настройки
                this.set_own_settings($.extend({}, this.inherited_settings, this.own_settings));
            }

            // включаем или отключаем редактирование брейкпоинтов:
            this.container.toggleClass('nc-mixins-editor--with-preset', has_new_preset);

            this.inherited_settings = inherited_settings;
            this.extract_selectors_and_breakpoints(inherited_settings);
            // Кэшируем палитру из унаследованных настроек
            this.cache_site_color_list(inherited_settings);
        },

        /**
         * Сохраняет собственные (неунаследованные) настройки
         * @param own_settings
         */
        set_own_settings: function(own_settings) {
            this.reset_breakpoints();
            this.selectors = {};
            this.own_settings = own_settings;
            this.extract_selectors_and_breakpoints(own_settings);
            this.update_mixin_json();
            // Кэшируем палитру в window.ncSiteColorList для доступа из других миксинов
            this.cache_site_color_list(own_settings);
        },

        /**
         * Извлекает данные палитры из настроек и кэширует в window.ncSiteColorList
         * Не перезаписывает, если уже есть более полная палитра (с большим числом заполненных ролей)
         * @param settings
         */
        cache_site_color_list: function(settings) {
            if (!settings) return;

            // Считаем количество заполненных ролей в текущей палитре
            function countFilledRoles(paletteObj) {
                if (!paletteObj || !paletteObj.roles) return 0;
                var count = 0;
                var roleNames = ['foreground_main', 'foreground_accent', 'brand', 'background_main', 'background_accent'];
                for (var i = 0; i < roleNames.length; i++) {
                    if (paletteObj.roles[roleNames[i]]) count++;
                }
                return count;
            }

            var existingFilledCount = countFilledRoles(window.ncSiteColorList);

            for (var selector in settings) {
                var selectorSettings = settings[selector];
                if (!selectorSettings || !selectorSettings.color) continue;
                for (var bp in selectorSettings.color) {
                    var bpData = selectorSettings.color[bp];
                    if (bpData && bpData.mixin === 'netcat_color_palette' && bpData.settings) {
                        var paletteArr = bpData.settings.palette;
                        if (typeof paletteArr === 'string') {
                            try { paletteArr = JSON.parse(paletteArr); } catch(e) { paletteArr = null; }
                        }
                        // Поддержка как массива (старый формат), так и объекта (новый формат)
                        var hasPaletteData = paletteArr && (
                            (Array.isArray(paletteArr) && paletteArr.length > 0) ||
                            (typeof paletteArr === 'object' && !Array.isArray(paletteArr) && Object.keys(paletteArr).length > 0)
                        );
                        if (hasPaletteData) {
                            var roles = bpData.settings.roles || {};
                            var newPalette = {
                                palette: paletteArr,
                                roles: {
                                    foreground_main: roles.foreground_main || '',
                                    foreground_accent: roles.foreground_accent || '',
                                    brand: roles.brand || '',
                                    background_main: roles.background_main || '',
                                    background_accent: roles.background_accent || ''
                                }
                            };

                            var newFilledCount = countFilledRoles(newPalette);

                            // Перезаписываем только если новая палитра полнее или такая же полная
                            if (newFilledCount >= existingFilledCount) {
                                window.ncSiteColorList = newPalette;
                                // Также в parent/top для iframe
                                try {
                                    if (window.parent && window.parent !== window) {
                                        window.parent.ncSiteColorList = window.ncSiteColorList;
                                    }
                                    if (window.top && window.top !== window) {
                                        window.top.ncSiteColorList = window.ncSiteColorList;
                                    }
                                } catch(e) { /* cross-origin */ }
                            }
                            return;
                        }
                    }
                }
            }
        },

        /**
         * Инициализирует палитру из данных PHP (с уровня сайта)
         * Эта палитра имеет приоритет, так как это авторитетный источник данных с сервера
         */
        init_site_color_list_from_php: function() {
            // Функция подсчёта заполненных ролей
            function countFilledRoles(paletteObj) {
                if (!paletteObj || !paletteObj.roles) return 0;
                var count = 0;
                var roleNames = ['foreground_main', 'foreground_accent', 'brand', 'background_main', 'background_accent'];
                for (var i = 0; i < roleNames.length; i++) {
                    if (paletteObj.roles[roleNames[i]]) count++;
                }
                return count;
            }

            // Вспомогательная функция для проверки наличия цветов в палитре
            function hasPaletteColors(p) {
                if (!p || !p.palette) return false;
                if (Array.isArray(p.palette)) return p.palette.length > 0;
                return typeof p.palette === 'object' && Object.keys(p.palette).length > 0;
            }

            var existingFilledCount = countFilledRoles(window.ncSiteColorList);

            // Проверяем parent/top - берём только если там более полная палитра
            try {
                if (window.parent && window.parent !== window && window.parent.ncSiteColorList &&
                    hasPaletteColors(window.parent.ncSiteColorList)) {
                    var parentFilledCount = countFilledRoles(window.parent.ncSiteColorList);
                    if (parentFilledCount > existingFilledCount) {
                        window.ncSiteColorList = window.parent.ncSiteColorList;
                        existingFilledCount = parentFilledCount;
                    }
                }
                if (window.top && window.top !== window && window.top.ncSiteColorList &&
                    hasPaletteColors(window.top.ncSiteColorList)) {
                    var topFilledCount = countFilledRoles(window.top.ncSiteColorList);
                    if (topFilledCount > existingFilledCount) {
                        window.ncSiteColorList = window.top.ncSiteColorList;
                        existingFilledCount = topFilledCount;
                    }
                }
            } catch(e) { /* cross-origin */ }

            // Используем палитру из PHP - это авторитетный источник с сервера
            // Перезаписываем если PHP-палитра более полная
            if (this.site_color_list && this.site_color_list.palette) {
                var palette = this.site_color_list.palette;
                if (typeof palette === 'string') {
                    try { palette = JSON.parse(palette); } catch(e) { palette = null; }
                }
                // Поддержка как нового формата (объект), так и старого (массив)
                var hasPalette = palette && (
                    (Array.isArray(palette) && palette.length > 0) ||
                    (typeof palette === 'object' && !Array.isArray(palette) && Object.keys(palette).length > 0)
                );
                if (hasPalette) {
                    var roles = this.site_color_list.roles || {};
                    var phpPalette = {
                        palette: palette,
                        roles: {
                            foreground_main: roles.foreground_main || '',
                            foreground_accent: roles.foreground_accent || '',
                            brand: roles.brand || '',
                            background_main: roles.background_main || '',
                            background_accent: roles.background_accent || ''
                        }
                    };
                    var phpFilledCount = countFilledRoles(phpPalette);

                    // PHP-палитра (с сервера) имеет приоритет если она не менее полная
                    if (phpFilledCount >= existingFilledCount) {
                        window.ncSiteColorList = phpPalette;
                        // Синхронизируем в parent/top
                        try {
                            if (window.parent && window.parent !== window) {
                                window.parent.ncSiteColorList = window.ncSiteColorList;
                            }
                            if (window.top && window.top !== window) {
                                window.top.ncSiteColorList = window.ncSiteColorList;
                            }
                        } catch(e) { /* cross-origin */ }
                    }
                }
            }
        },

        /**
         * Возвращает настройки в виде объекта в том виде, как они будут сохранены
         */
        get_own_settings: function () {
            var settings = $.extend({}, this.own_settings);
            for (var selector in settings) {
                if ($.isEmptyObject(settings[selector])) {
                    delete settings[selector];
                } else {
                    settings[selector] = this.sort_mixins(settings[selector]);
                }
            }
            return settings;
        },

        /**
         * Извлекает и запоминает использованные в настройках брейкпоинты и селекторы
         * @param settings
         */
        extract_selectors_and_breakpoints: function(settings) {
            for (var selector in settings) {
                this.selectors[selector] = selector;
                for (var mixin_type in settings[selector]) {
                    for (var breakpoint in settings[selector][mixin_type]) {
                        this.breakpoints[breakpoint] = breakpoint; // имя свойства объекта — всегда строка
                    }
                }
            }
            this.sort_breakpoints();
            this.on_breakpoint_number_change();
            this.update_selectors_select();
        },

        /**
         * Сбрасывает брейкпоинты
         */
        reset_breakpoints: function () {
            this.find('.nc-mixins-breakpoint-type-select').val(this.breakpoint_type);
            this.breakpoints = {};
            this.breakpoints[this.MAX_WIDTH] = this.MAX_WIDTH.toString();
        },

        /**
         * Возвращает массив с используемыми брейкпоинтами
         * @param settings
         * @returns {string[]}
         */
        get_breakpoints_as_array_from_settings: function(settings) {
            var breakpoints = {};
            for (var selector in settings) {
                for (var mixin_type in settings[selector]) {
                    for (var breakpoint in settings[selector][mixin_type]) {
                        breakpoints[breakpoint] = breakpoint; // имя свойства объекта — всегда строка
                    }
                }
            }
            return Object.keys(breakpoints);
        },

        /**
         * Сортирует this.breakpoints по возрастанию (после изменений в нём)
         */
        sort_breakpoints: function() {
            // subtraction automatically casts strings to numbers
            var breakpoints_array = Object.keys(this.breakpoints).sort(function(a, b) { return a - b; });
            this.breakpoints = {};
            for (var i = 0; i < breakpoints_array.length; i++) {
                var b = breakpoints_array[i];
                this.breakpoints[b] = b;
            }
        },

        /**
         * Сортирует группы миксинов по их порядку (приоритетам)
         * @param group_settings
         */
        sort_mixins: function(group_settings) {
            var sorted = {};
            $.each(this.mixin_type_order, function(index, mixin_type) {
                if (mixin_type in group_settings) {
                    sorted[mixin_type] = group_settings[mixin_type];
                }
            });
            return sorted;
        },

        /**
         * Возвращает порядок групп миксинов
         */
        get_mixin_type_order: function() {
            var mixin_type_order = [];
            this.find('.nc-mixins-mixin-row').each(function() {
                mixin_type_order.push($(this).data('mixinType'));
            });
            return mixin_type_order;
        },

        /**
         * Прячет/убирает кнопку добавления брейкпоинта в зависимости от того,
         * сколько брейкпоинтов уже задано
         */
        on_breakpoint_number_change: function() {
            // атрибут data-breakpoint-number у контейнера — прячет кнопку добавления (стилями)
            // при достижении максимального числа диапазонов (задаётся в стилях)
            this.container.attr('data-breakpoint-number', Object.keys(this.breakpoints).length);
        },

        /**
         * Отображает диалог добавления брейкпоинта
         * @param event
         */
        show_new_breakpoint_dialog: function(event) {
            var add_button = $(event.target),
                cell = add_button.closest('td'),
                max = parseInt(cell.data('breakpoint'), 10),
                min = parseInt(cell.prev().data('breakpoint'), 10) || 0,
                lang = this.lang;

            // @todo proper dialog
            var message = lang.BREAKPOINT_ADD_PROMPT;
            if (min || max) {
                message += ' ' + replace_from_to(lang.BREAKPOINT_ADD_PROMPT_RANGE, min, max);
            }
            message += ':';
            var new_breakpoint = parseInt(prompt(message), 10);
            if (new_breakpoint && new_breakpoint > min && new_breakpoint < max) {
                this.add_new_breakpoint(new_breakpoint);
            }
        },

        /**
         * Добавляет новый брейкпоинт
         * @param new_breakpoint
         */
        add_new_breakpoint: function(new_breakpoint) {
            this.breakpoints[new_breakpoint] = new_breakpoint;
            this.sort_breakpoints();
            this.on_breakpoint_number_change();
            this.rebuild_table();
        },

        /**
         * Отображает диалог редактирования брейкпоинта
         * @param event
         */
        show_edit_breakpoint_dialog: function(event) {
            if (this.container.is('.nc-mixins-editor--with-preset')) {
                // если выбран пресет, не даём редактировать брейкпоинты
                return;
            }

            var old_breakpoint = $(event.target).closest('.nc-mixins-width').data('breakpoint');

            // @todo proper dialog and constraints
            var message = this.lang.BREAKPOINT_CHANGE_PROMPT;
            var new_breakpoint = prompt(message, old_breakpoint);
            if (new_breakpoint == old_breakpoint || new_breakpoint === null) {
                return;
            }
            if (new_breakpoint === '' || new_breakpoint === '0') {
                this.remove_breakpoint(old_breakpoint);
            } else {
                this.replace_breakpoint(old_breakpoint, new_breakpoint);
            }
            this.after_breakpoint_change();
        },

        /**
         * Заменяет в настройках значение брейкпоинта после его редактирования
         * @param old_breakpoint
         * @param new_breakpoint
         */
        replace_breakpoint: function(old_breakpoint, new_breakpoint) {
            var settings = this.own_settings;
            for (var selector in settings) {
                for (var mixin_type in settings[selector]) {
                    if (settings[selector][mixin_type][old_breakpoint]) {
                        settings[selector][mixin_type][new_breakpoint] = settings[selector][mixin_type][old_breakpoint];
                    }
                }
            }
            this.breakpoints[new_breakpoint] = new_breakpoint;

            this.remove_breakpoint(old_breakpoint);
        },

        /**
         * Удаляет настройки для указанного брейкпоинта
         * @param breakpoint
         */
        remove_breakpoint: function(breakpoint) {
            var settings = this.own_settings;
            for (var selector in settings) {
                for (var mixin_type in settings[selector]) {
                    delete this.own_settings[selector][mixin_type][breakpoint];
                }
            }
            delete this.breakpoints[breakpoint];
        },

        /**
         * Обновляет редактор после изменения и удаления брейкпоинтов
         */
        after_breakpoint_change: function() {
            this.temporary_own_settings = {};
            this.close_opened_mixin_settings();
            this.sort_breakpoints();
            this.on_breakpoint_number_change();
            this.rebuild_table();
            this.update_mixin_json();
        },

        /**
         * Обновляет select с селекторами
         * @param current_selector
         */
        update_selectors_select: function(current_selector) {
            var select = this.find('.nc-mixins-selector-select'),
                last_option = select.find('option:last'),
                selectors_array = Object.keys(this.selectors).sort();
            if (current_selector === undefined) {
                current_selector = select.val();
            }
            select.find('.nc-mixins-selector-select-selector').remove();
            for (var i = 0; i < selectors_array.length; i++) {
                var selector = selectors_array[i];
                if (selector) {
                    $('<option>', {
                        value: selector,
                        text: selector,
                        'class': 'nc-mixins-selector-select-selector'
                    }).insertBefore(last_option);
                }
            }
            select.val(current_selector in this.selectors ? current_selector : '').change();
        },

        /**
         * Перестраивает таблицу с миксинами
         */
        rebuild_table: function() {
            var breakpoints = Object.keys(this.breakpoints).map(Number);
            this.temporary_own_settings = {};
            this.rebuild_table_head_width_ranges(breakpoints);
            this.rebuild_table_body_width_ranges(breakpoints);
        },

        /**
         * Перестраивает заголовок таблицы с миксинами
         */
        rebuild_table_head_width_ranges: function(breakpoints) {
            var num_ranges = breakpoints.length,
                cells = '';

            this.find('.nc-mixins-width-icon').prop('colspan', num_ranges);
            this.find('.nc-mixins-width').remove();

            for (var i = 0; i < num_ranges; i++) {
                var breakpoint = breakpoints[i];
                cells +=
                    '<td class="nc-mixins-width nc-mixins-width-head" data-breakpoint="' + breakpoint + '">' +
                    '<div class="nc-mixins-breakpoint-add-button-container">' +
                    '<div class="nc-mixins-breakpoint-add-button" title="' + this.lang.BREAKPOINT_ADD + '">+</div>' +
                    '</div>' +
                    '<div class="nc-mixins-breakpoint"><span>' +
                    (breakpoint >= this.MAX_WIDTH ? '&#x2731;' : breakpoint) + // ✱
                    '</span></div>' +
                    '</td>';
            }

            cells = $(cells);
            cells.first().addClass('nc-mixins-width--first');
            cells.last().addClass('nc-mixins-width--last');

            this.find('.nc-mixins-width-ranges').prepend(cells)
        },

        /**
         * Перестраивает тело таблицы с миксинами
         */
        rebuild_table_body_width_ranges: function(breakpoints) {
            var editor = this;

            this.find('.nc-mixins-mixin-row').each(function() {
                var row = $(this),
                    cells = '',
                    prev_breakpoint = 0,
                    num_ranges = breakpoints.length;

                row.find('.nc-mixins-width').remove();

                for (var i = 0; i < num_ranges; i++) {
                    var breakpoint = breakpoints[i];
                    cells +=
                        '<td class="nc-mixins-width" data-breakpoint="' + breakpoint + '">' +
                        '<div class="nc-mixins-add-setting-container"><div class="nc-mixins-add-setting">+</div></div>' +
                        '</td>';
                    prev_breakpoint = breakpoint;
                }

                cells = $(cells);
                cells.first().addClass('nc-mixins-width--first');
                cells.last().addClass('nc-mixins-width--last');

                row.prepend(cells);

                editor.update_row_markers(row);

                // Не показываем строку, если в data-mixin-scopes нет scope этого редактора
                var mixin_scopes = row.data('mixin-scopes') || row.data('mixinScopes') || [];
                var mixin_type = row.data('mixin-type') || row.data('mixinType');
                var is_visible;

                if (editor.scope.indexOf('Slot_') === 0) {
                    // Для слотов: фильтруем по типам миксинов, указанным в slot_allowed_types
                    if (editor.slot_allowed_types && editor.slot_allowed_types.length > 0) {
                        is_visible = editor.slot_allowed_types.indexOf(mixin_type) !== -1;
                    } else {
                        // Если slot_allowed_types не указаны, показываем все миксины
                        is_visible = true;
                    }
                } else {
                    // Для Index/IndexItem: используем стандартную логику
                    is_visible = mixin_scopes.indexOf(editor.scope) !== -1;
                }
                row.toggle(is_visible);
            });
        },

        /**
         * Обновляет маркеры (● и 🡡) в указанной строке
         * @param row
         */
        update_row_markers: function(row) {
            var last_stop = -1,
                previous_existing_breakpoint = 0,
                row_mixin_type_name = row.find('.nc-mixins-mixin-type-name').html(),
                editor = this,
                mixin_type = row.data('mixinType');

            row.find('.nc-mixins-width').each(function(i) {
                var cell = $(this),
                    breakpoint = cell.data('breakpoint'),
                    range_description = editor.make_range_description(previous_existing_breakpoint, breakpoint);

                cell.prop('title', row_mixin_type_name + ' ' + range_description)
                    .data('range', range_description);

                // удаляем старые маркеры
                cell.find('.nc-mixins-settings-marker, .nc-mixins-base-settings-marker').remove();

                // удаляем имеющиеся классы nc-mixins-width--span-X
                cell.removeClass(function(j, className) {
                    return (className.match(/\bnc-mixins-width--span-\S+/g) || []).join(' ');
                });
                // на сколько диапазонов будут распространяться заданные здесь параметры?
                if (i - last_stop > 1) {
                    cell.addClass('nc-mixins-width--span-' + (i - last_stop));
                }

                // наличие настроек по их типу
                var has_own_settings = // собственные настройки
                        !!editor.get_settings(editor.own_settings, mixin_type, breakpoint) ||
                        !!editor.get_settings(editor.temporary_own_settings, mixin_type, breakpoint),
                    has_inherited_preset_settings = // унаследованные от пресета
                        !!editor.get_settings(editor.inherited_settings, mixin_type, breakpoint),
                    has_own_base_settings = // унаследованные от основного состояния (без дополнительного селектора)
                        editor.get_current_selector() &&
                        !!editor.get_settings(editor.own_settings, mixin_type, breakpoint, '');

                if (has_own_settings) {
                    cell.append('<div class="nc-mixins-settings-marker nc-mixins-own-settings-marker">●</div>');
                } else if (has_inherited_preset_settings) {
                    cell.append('<div class="nc-mixins-settings-marker nc-mixins-inherited-settings-marker">🡡</div>');
                } else if (has_own_base_settings) {
                    // Когда выбран дополнительный селектор, показывается индикатор наличия настроек в основном
                    // состоянии (без дополнительного селектора).
                    // Этот индикатор неактивный, поэтому у него нет класса nc-mixins-settings-marker.
                    cell.append('<div class="nc-mixins-base-settings-marker">○</div>');
                }

                cell.toggleClass('nc-mixins-width--with-own-settings', has_own_settings);
                cell.toggleClass('nc-mixins-width--with-inherited-settings', has_inherited_preset_settings);

                if (has_own_settings || has_inherited_preset_settings) {
                    last_stop = i;
                    previous_existing_breakpoint = breakpoint;
                }

            });
        },

        /**
         * Возвращает «координаты» выбранной ячейки маркера ширины/миксина в виде объекта {row,cell}
         * (или null, когда ячейка не выбрана)
         * @returns {null|Object}
         */
        get_active_cell_position: function() {
            var marker = this.container.find('.nc-mixins-settings-marker.nc--active');
            if (!marker.length) {
                return null;
            }
            var cell = marker.closest('.nc-mixins-width'),
                row = cell.closest('.nc-mixins-mixin-row'),
                tbody = row.closest('tbody');
            return {
                row: tbody.children().index(row),
                cell: row.children().index(cell)
            };
        },

        /**
         * Активирует ячейку настроек с заданными «координатами» (см. get_active_cell_position()).
         * @param {null|Object} position
         */
        restore_active_cell_position: function(position) {
            if (!position) {
                return;
            }
            this.container.find('.nc-mixins-table tbody')
                .children().eq(position.row)
                .children().eq(position.cell)
                .find('.nc-mixins-settings-marker, .nc-mixins-add-setting').first().click();
        },

        /**
         * Возвращает выбранный в редакторе селектор
         * @returns {string}
         */
        get_current_selector: function() {
            return this.find('.nc-mixins-selector-select').val();
        },

        /**
         * Действия после изменения select’а с селектором
         */
        on_selector_select_change: function() {
            var select = this.find('.nc-mixins-selector-select');
            if (select.val() === '+') {
                this.show_add_selector_dialog(); // может добавить и выбрать новый селектор
            } else {
                var active_cell_position = this.get_active_cell_position();
                this.rebuild_table();
                this.restore_active_cell_position(active_cell_position);
            }
        },

        /**
         * Показывает диалог добавления селектора
         */
        show_add_selector_dialog: function() {
            // @todo proper dialog
            var selector = prompt(this.lang.SELECTOR_ADD_PROMPT);
            if (selector && !(selector in this.selectors)) {
                this.selectors[selector] = selector;
            }
            this.update_selectors_select(selector || '');
        },

        /**
         * Возвращает значение настроек указанного типа (собственных или наследованных)
         * для указанного типа миксинов и брейкпоинта)
         * @param from
         * @param mixin_type
         * @param breakpoint
         * @param {undefined|string} [selector] по умолчанию — выбранный в редакторе
         * @returns {*}
         */
        get_settings: function(from, mixin_type, breakpoint, selector) {
            var value;
            (value = from[selector === undefined ? this.get_current_selector() : selector]) &&
            (value = value[mixin_type]) &&
            (value = value[breakpoint]);
            return value;
        },

        /**
         * Удаляет настройки для указанного типа миксинов и брейкпоинта
         * @param from
         * @param mixin_type
         * @param breakpoint
         */
        remove_settings: function(from, mixin_type, breakpoint) {
            var selector = this.get_current_selector();
            if (from[selector] && from[selector][mixin_type]) {
                delete from[selector][mixin_type][breakpoint];
                if ($.isEmptyObject(from[selector][mixin_type])) {
                    delete from[selector][mixin_type];
                }
            }
        },

        /**
         * Возвращает строку с описанием ширины диапазона
         * @param from
         * @param to
         * @returns {string}
         */
        make_range_description: function(from, to) {
            if (Object.keys(this.breakpoints).length < 2) {
                return '';
            }
            var type = this.breakpoint_type,
                key = 'FOR_' + (type ? type.toUpperCase() + '_' : '') + 'WIDTH_';
            if (from == 0) {
                key += to == this.MAX_WIDTH ? 'ANY' : 'TO';
            } else if (to == this.MAX_WIDTH) {
                key += 'FROM';
            } else {
                key += 'RANGE';
            }
            return replace_from_to(this.lang[key] || '', from, to - 1);
        },

        /**
         * Показывает или прячет блок выбора типа брейкпоинта в зависимости от
         * опции show_breakpoint_type_select
         */
        init_breakpoint_type_container: function() {
            var breakpoint_type_container = this.find('.nc-mixins-breakpoint-type-container');
            if (this.show_breakpoint_type_select) {
                breakpoint_type_container.show();
            } else {
                breakpoint_type_container
                    .hide()
                    .find('select').attr('name', ''); // чтобы не отправлялись ненужные/некорректные данные
            }
        },

        /**
         * Устанавливает значение this.breakpoint_type из соответствующего селекта (если он есть)
         */
        set_breakpoint_type_from_select: function() {
            this.breakpoint_type = this.find('.nc-mixins-breakpoint-type-select').val() || '';
        },

        /**
         * Обновляет подсказки ширины после изменения селекта типа брейкпоинтов;
         * инициирует событие 'breakpoint_type_change'
         */
        on_breakpoint_type_select_change: function() {
            var editor = this;
            editor.set_breakpoint_type_from_select();
            editor.find('.nc-mixins-mixin-row').each(function() {
                var row = $(this),
                    active_cell_position = editor.get_active_cell_position();
                editor.update_row_markers(row); // пересборка маркеров — другие подсказки диапазонов
                editor.restore_active_cell_position(active_cell_position);
            });
            this.container.trigger(nc_mixin_settings_editor.breakpoint_type_change_event, [{ editor: this }]);
        },

        /**
         * Устанавливает пресет
         * @param preset_id
         */
        set_preset_id: function (preset_id) {
            this.find('.nc-mixins-preset-select').val(preset_id);
            this.apply_preset_settings();
        },

        /**
         * Выполняет действия после изменения select’а выбора пресета
         */
        on_preset_select_change: function() {
            if (this.find('.nc-mixins-preset-select').val() === '+') {
                this.open_preset_edit_dialog();
            } else {
                this.apply_preset_settings();
            }
        },

        /**
         * Открывает диалог редактирования или создания пресета
         */
        open_preset_edit_dialog: function() {
            var preset_select_option = this.get_selected_preset_option(),
                preset_id = preset_select_option.data('id'),
                create_new_preset = preset_select_option.val() === '+',
                options = {
                    component_template_id: this.component_template_id,
                    'data[Scope]': this.scope
                };

            if (preset_id === '0') {
                return;
            }

            if (create_new_preset) {
                options['data[Mixin_Settings]'] = this.find('.nc-mixins-json').val();
            } else {
                options['mixin_preset_id'] = preset_id;
            }

            var opener_editor = this;
            nc.load_dialog(this.container.data('preset-edit-dialog-url'), options, 'POST')
              .set_option('on_submit_response', function(response) {
                  // this = диалог редактирования пресета
                  // response = ID пресета
                  var dialog = this, // PhpStorm без этого «думает», что this используется неправильно
                      preset_id = parseInt(response, 10),
                      preset_name = dialog.find('input[name="data[Mixin_Preset_Name]"]').val(),
                      preset_settings = dialog.find('.nc-mixins-json').val(),
                      set_as_default = dialog.find(':checkbox[name="set_as_default"]').is(':checked');
                  if (preset_id) {
                      opener_editor.update_preset(preset_id, preset_name, preset_settings, set_as_default);
                  }
                  dialog.destroy();
              });

            if (create_new_preset) {
                this.find('.nc-mixins-preset-select').val('-1').change();
            }
        },

        /**
         * Открывает диалог удаления пресета
         */
        open_preset_delete_dialog: function() {
            var options = {
                    mixin_preset_id: this.get_selected_preset_option().data('id')
                };

            var opener_editor = this;
            nc.load_dialog(this.container.data('preset-delete-dialog-url'), options, 'POST')
              .set_option('on_submit_response', function(response) {
                  // this = диалог редактирования пресета
                  // response = ID пресета
                  var preset_id = parseInt(response, 10);
                  if (preset_id) {
                      opener_editor.delete_preset(preset_id);
                  }
                  this.destroy();
              });
        },

        /**
         * Инициализирует и показывает блок с выбором пресета, либо прячет его,
         * в зависимости от опций show_preset_select и
         */
        init_preset_container: function() {
            var preset_container = this.find('.nc-mixins-preset-container');
            if (this.show_preset_select) {
                this.init_preset_select(preset_container); // заполняем select с пресетами из options.mixin_presets
                preset_container.show();
            } else {
                preset_container
                    .hide()
                    .find('select').attr('name', ''); // чтобы не отправлялись ненужные/некорректные данные
            }
        },

        /**
         * Устанавливает опции в селекте пресетов из this.mixin_presets
         */
        init_preset_select: function(preset_container) {
            var select = preset_container.find('.nc-mixins-preset-select');
            select.find('option').remove();
            if (this.mixin_presets.length) {
                $.each(this.mixin_presets, function(i, preset) {
                   $('<option>')
                       .attr({
                           value: (preset.value || 0).toString(),
                           selected: preset.selected,
                           'data-id': preset.id, // отличается от value для опции «использовать пресет по умолчанию»
                           'data-settings': preset.settings // не через data(), т. к. это JSON-строка
                       })
                       .html(preset.name)
                       .appendTo(select);
                });
            } else {
                select.append('<option>');
            }
            select.append('<option value="+">' + this.lang.PRESET_CREATE);
        },

        /**
         * Обновляет настройки пресета (после изменения в диалоге редактирования пресета)
         * @param preset_id
         * @param preset_name
         * @param preset_settings
         * @param set_as_default
         */
        update_preset: function(preset_id, preset_name, preset_settings, set_as_default) {
            var select = this.find('.nc-mixins-preset-select'),
                options_to_update = select.find('option[data-id="' + preset_id + '"]'),
                default_option = select.find('option[value="-1"]'),
                was_default = default_option.data('id') == preset_id;

            if (!options_to_update.length) {
                options_to_update = $('<option>').val(preset_id).insertBefore(select.find('option:last'));
                select.val(set_as_default ? '-1' : preset_id);
            }

            if (set_as_default) {
                options_to_update = options_to_update.add(default_option);
            }

            options_to_update
                .html(preset_name)
                .attr({'data-id': preset_id, 'data-settings': preset_settings})  // важно, чтобы был атрибут data-id в разметке
                .data({id: preset_id, 'settings': JSON.parse(preset_settings)}); // preset_settings — это JSON-строка

            if (set_as_default) {
                default_option.html(this.lang.PRESET_DEFAULT.replace('%s', preset_name));
            } else if (was_default) {
                this.remove_default_preset(preset_id);
            }

            select.change();
        },

        /**
         * Выполняет действия, необходимые после удаления пресета
         * @param preset_id
         */
        delete_preset: function(preset_id) {
            var select = this.find('.nc-mixins-preset-select');

            select.find('option[value="' + preset_id + '"]').remove();
            this.remove_default_preset(preset_id);

            select.val('-1').change();
        },

        /**
         * Сбрасывает дефолтный пресет в селекте пресетов в «нет»
         * @param preset_id
         */
        remove_default_preset: function(preset_id) {
            this.find('.nc-mixins-preset-select')
                .find('option[value="-1"][data-id="' + preset_id + '"]')
                .attr({'data-id': '0', 'data-settings': ''})
                .data({id: 0, 'settings': {}})
                .html(this.lang.PRESET_DEFAULT_NONE);
        },

        /**
         * Возвращает выбранную в select’е пресетов option
         * @returns {*|jQuery}
         */
        get_selected_preset_option: function() {
            var select = this.find('.nc-mixins-preset-select');
            return select.find('option[value="' + select.val() + '"]');
        },

        /**
         * Обновляет редактор после выбора пресета
         */
        apply_preset_settings: function() {
            if (this.show_preset_select) {
                var selected_preset_option = this.get_selected_preset_option();
                this.set_inherited_settings(selected_preset_option.data('settings') || {});
                this.rebuild_table();
                this.find('.nc-mixins-preset-actions').toggle(!!selected_preset_option.data('id'));
                this.update_mixin_json();
            }
        },

        /**
         * Прячет настройки для типа миксинов (например, при переходе к редактированию другого типа
         * миксинов)
         */
        close_opened_mixin_settings: function() {
            var editor = this;
            this.find('.nc-mixins-settings-cell.nc--active').removeClass('nc--active').each(function() {
                // если это был новый диапазон, но после добавления в нём ничего не изменено — удаляем его
                var previous_settings_cell = $(this);
                if (previous_settings_cell.is('.nc-mixins-settings-cell--temporary')) {
                    var previous_settings_row = previous_settings_cell.closest('tr');
                    editor.remove_settings(editor.temporary_own_settings, previous_settings_row.data('mixinType'), previous_settings_cell.data('breakpoint'));
                    editor.update_row_markers(previous_settings_row);
                }
            });
        },

        /**
         * Раскрывает настройки в строке типа миксина
         * @param click_event
         */
        show_mixin_settings: function(click_event) {
            var button = $(click_event.target),
                range_cell = button.closest('td'),
                row = range_cell.closest('tr'),
                settings_cell = row.find('.nc-mixins-settings-cell'),
                mixin_type = row.data('mixinType'),
                breakpoint = range_cell.data('breakpoint'),
                mixin_select = settings_cell.find('.nc-mixins-mixin-select');

            // клик на уже выбранном диапазоне — ничего не делаем
            if (button.is('.nc--active')) {
                return;
            }

            // если была открыта другая ячейка с настройками — прячем её
            this.close_opened_mixin_settings();

            // текстовое описание диапазона, для которого действуют настройки
            settings_cell.find('.nc-mixins-mixin-range-description').html(range_cell.data('range'));
            settings_cell.data('breakpoint', breakpoint);

            // раскрываем ячейку с настройками
            settings_cell.addClass('nc--active');

            // устанавливаем настройки
            var settings = this.get_mixin_settings_values(mixin_type, breakpoint),
                mixin_keyword = settings.mixin,
                settings_container = settings_cell.find('.nc-mixins-mixin-settings[data-mixin-keyword="' + mixin_keyword + '"]');
            mixin_select.val(mixin_keyword || '');
            this.on_mixin_select_change(mixin_select, true); // не mixin_select.change() — передаём доп. параметр
            this.set_mixin_input_values(settings_container, settings, true);

            // помечаем, что это новое правило (если ничего не поменяется, удалим его при потере фокуса)
            settings_cell.toggleClass('nc-mixins-settings-cell--temporary', !button.is('.nc-mixins-own-settings-marker'));

            // если это новое правило, обновляем маркеры (добавится маркер собственных настроек)
            if (button.is('.nc-mixins-add-setting')) {
                this.update_current_mixin_settings(true);
            }

            // убираем подсветку предыдущей выбранной ячейки диапазона ширины
            this.find('.nc-mixins-settings-marker').removeClass('nc--active');
            // подсветка ячейки выбранного диапазона
            range_cell.find('.nc-mixins-settings-marker').addClass('nc--active');
        },

        /**
         * Устанавливает значения полей в блоке типа миксина из настроек
         * @param mixin_settings_container
         * @param settings
         * @param trigger_events
         */
        set_mixin_input_values: function(mixin_settings_container, settings, trigger_events) {
            mixin_settings_container.find('.nc-mixins-mixin-settings-rows').children().remove();
            // для редактора всех свойств «с замком» нужно установить значение lock_sides до остальных значений
            for (var key in settings) {
                if (/^settings\.(.+\.)?lock_sides$/.test(key)) {
                    var lock = {};
                    lock[key] = settings[key];
                    settings = $.extend(lock, settings);
                }
            }
            for (var key in settings) {
                var multiple_settings_index = (key.match(/\.(\d+)\./) || [])[1],
                    input_container = (multiple_settings_index !== undefined) // множественные настройки?
                        ? this.get_mixin_multiple_settings_container(mixin_settings_container, multiple_settings_index)
                        : mixin_settings_container,
                    // в форме все инпуты вместо порядковых номеров имеют '#' в названии:
                    input_name = 'mixin.' + key.replace('.' + multiple_settings_index + '.', '.#.'),
                    input = input_container.find('[name="' + input_name + '"]');

                if (input.is(':checkbox')) {
                    input.prop('checked', !!settings[key]);
                } else if (input.is(':radio')) {
                    input.filter('[value="' + settings[key].replace('"', '\\"') + '"]').prop('checked', true);
                } else if (input.is('[data-color-input]')) {
                    input.attr('data-sync-color', settings[key]);
                    this.set_color_input(input, settings[key]);
                } else if (input.is('[data-palette-color]')) {
                    input.attr('data-sync-color', settings[key]);
                    input.val(settings[key]);
                } else {
                    input.val(settings[key]);
                }
                if (trigger_events) {
                    input.change();
                }
            }
            // Если есть шаблон для множественных настроек и строк нет — добавляем первую пустую строку
            var template = mixin_settings_container.find('.nc-mixins-mixin-settings-row-template');
            var rows_container = mixin_settings_container.find('.nc-mixins-mixin-settings-rows');
            if (template.length && rows_container.length && !rows_container.children().length) {
                this.get_mixin_multiple_settings_container(mixin_settings_container, 0);
            }

            mixin_settings_container.trigger(nc_mixin_settings_editor.mixin_settings_set_values_event, [{ editor: this }]);

            // Инициализация компонентов выбора цвета из палитры после установки значений
            this.init_palette_color_pickers(mixin_settings_container.find('[data-palette-color]'));
        },

        /**
         * Возвращает блок с множественными настройками (div.nc-mixins-mixin-settings-row) по номеру
         * (создаёт его при необходимости)
         * @param mixin_settings_container
         * @param {Number|String} index
         * @returns {*}
         */
        get_mixin_multiple_settings_container: function(mixin_settings_container, index) {
            index = parseInt(index, 10);
            var rows_container = mixin_settings_container.find('.nc-mixins-mixin-settings-rows'),
                rows = rows_container.find('.nc-mixins-mixin-settings-row'),
                existing_number_of_rows = rows.length,
                template = mixin_settings_container.find('.nc-mixins-mixin-settings-row-template');

            if (existing_number_of_rows > index) { // уже есть нужный блок
                return rows.eq(index);
            }

            if (!rows_container.length || !template.length) { // не хватает нужных блоков в разметке, мы тут бессильны
                return mixin_settings_container;
            }

            var last = mixin_settings_container;
            while (existing_number_of_rows++ < index + 1) {
                last = template.clone().removeClass('nc-mixins-mixin-settings-row-template').appendTo(rows_container);

                // инициализация полей выбора цвета
                this.init_color_inputs(last.find('[data-color-input]'));

                // инициализация новых компонентов выбора цвета из палитры
                this.init_palette_color_pickers(last.find('[data-palette-color]'));

                // инициализация загрузки файлов
                this.init_uploader(last.find('[data-uploader]'));
            }

            // инициализация перетаскивания
            if (template.find('.nc-mixins-mixin-settings-row-move').length > 0) {
                rows_container.sortable({
                    handle: '.nc-mixins-mixin-settings-row-move',
                    containment: 'parent',
                    update: $.proxy(this, 'update_current_mixin_settings')
                });
            }

            return last;
        },

        /**
         * Обработка нажатия кнопки добавления множественной настройки
         * @param event
         */
        on_mixin_multiple_settings_add_button_click: function(event) {
            var button = $(event.target),
                mixin_settings_container = button.closest('.nc-mixins-mixin-settings'),
                new_index = mixin_settings_container.find('.nc-mixins-mixin-settings-rows .nc-mixins-mixin-settings-row').length;

            var new_row = this.get_mixin_multiple_settings_container(mixin_settings_container, new_index);
            mixin_settings_container.trigger(
                nc_mixin_settings_editor.mixin_settings_new_row_event,
                [{ editor: this, row: new_row }]
            );

            this.update_current_mixin_settings();
            return false;
        },

        /**
         * Обработка нажатия кнопки удаления множественной настройки
         * @param event
         */
        on_mixin_multiple_settings_remove_button_click: function(event) {
            $(event.target).closest('.nc-mixins-mixin-settings-row').remove();
            this.update_current_mixin_settings();
            return false;
        },

        /**
         * Возвращает значения полей для указанного типа миксинов и брейкпоинта
         * @param mixin_type
         * @param breakpoint
         * @returns {*}
         */
        get_mixin_settings_values: function(mixin_type, breakpoint) {
            var settings =
                this.get_settings(this.own_settings, mixin_type, breakpoint) ||
                this.get_settings(this.inherited_settings, mixin_type, breakpoint);

            // если настроек нет — для заполнения формы берём значения следующего брейкпоинта
            if (!settings) {
                var all_breakpoints = Object.keys(this.breakpoints).map(Number),
                    breakpoint_index = all_breakpoints.indexOf(breakpoint);
                if (breakpoint_index < all_breakpoints.length - 1) {
                    settings = this.get_mixin_settings_values(mixin_type, all_breakpoints[breakpoint_index + 1]);
                }
            }

            return flatten(settings || {});
        },

        /**
         * Выполняет действия после выбора миксина
         * @param mixin_select
         * @param is_initializing_mixin_settings_container
         */
        on_mixin_select_change: function(mixin_select, is_initializing_mixin_settings_container) {
            var settings_cell = mixin_select.closest('.nc-mixins-settings-cell'),
                editor = this;
            var mixin_settings_container = settings_cell.find('.nc-mixins-mixin-settings')
                .hide()
                .filter('[data-mixin-keyword="' + mixin_select.val() + '"]')
                .show();

            mixin_settings_container.trigger(nc_mixin_settings_editor.mixin_settings_set_values_event, [{ editor: this }]);

            // Если есть шаблон для множественных настроек и строк нет — добавляем первую пустую строку
            mixin_settings_container.each(function() {
                var $container = $(this);
                var template = $container.find('.nc-mixins-mixin-settings-row-template');
                var rows_container = $container.find('.nc-mixins-mixin-settings-rows');
                if (template.length && rows_container.length && !rows_container.children().length) {
                    editor.get_mixin_multiple_settings_container($container, 0);
                }
            });

            if (!is_initializing_mixin_settings_container) {
                settings_cell.removeClass('nc-mixins-settings-cell--temporary');
                this.update_current_mixin_settings();
            }
        },

        /**
         * Выполняет действия после изменений настроек миксина
         * @param is_new_setting
         */
        update_current_mixin_settings: function(is_new_setting) {
            var editor = this;

            editor.find('.nc-mixins-settings-cell.nc--active').each(function() {
                var active_cell = $(this),
                    selector = editor.get_current_selector(),
                    row = active_cell.closest('.nc-mixins-mixin-row'),
                    mixin_type = row.data('mixinType'),
                    mixin_keyword = active_cell.find('.nc-mixins-mixin-select').val(),
                    breakpoint = active_cell.data('breakpoint'),
                    update_markers = false,
                    // для новых диапазонов до изменения настройки считаются «временными» (не сохраняются)
                    // (первый аргумент также может быть информацией о событии)
                    settings_type = 'own_settings';

                if (is_new_setting === true) { // первым аргументом функции [is_new_setting] также может быть eventData
                    update_markers = true;
                    settings_type = 'temporary_own_settings';
                } else if (active_cell.hasClass('nc-mixins-settings-cell--temporary')) { // при любом изменении перестаём считать настройки «временными»
                    active_cell.removeClass('nc-mixins-settings-cell--temporary');
                    update_markers = true;
                }

                set_multilevel_object_value(editor[settings_type], [selector, mixin_type, breakpoint], { mixin: mixin_keyword });

                active_cell.find('.nc-mixins-mixin-settings[data-mixin-keyword="' + mixin_keyword + '"]').each(function() {
                    var settings = editor.get_mixin_input_values($(this));
                    $.extend(editor[settings_type][selector][mixin_type][breakpoint], unflatten(settings));
                });

                if (update_markers) {
                    // перерисовываем маркеры диапазонов (🡡 → ●)
                    editor.update_row_markers(row);
                    row.find('.nc-mixins-width[data-breakpoint="' + breakpoint + '"] .nc-mixins-settings-marker')
                        .addClass('nc--active');
                }
            });

            if (is_new_setting !== true) {
                this.update_mixin_json();
            }
        },

        /**
         * Обновляет значения скрытого поля с JSON с настройками всех миксинов
         */
        update_mixin_json: function() {
            if (!this.is_initialized) { // чтобы не обновлять значение без необходимости при инициализации
                return;
            }

            var saved_settings = this.get_own_settings();
            this.find('.nc-mixins-json').val(JSON.stringify(saved_settings));
        },

        /**
         * Выполняет действия при нажатии на кнопку удаления настроек для типа миксина
         * @param event
         */
        on_remove_settings_button_click: function(event) {
            var remove_button = $(event.target),
                settings_cell = remove_button.closest('.nc-mixins-settings-cell'),
                row = settings_cell.closest('tr'),
                mixin_type = row.data('mixinType'),
                breakpoint = settings_cell.data('breakpoint');
            this.remove_settings(this.temporary_own_settings, mixin_type, breakpoint);
            this.remove_settings(this.own_settings, mixin_type, breakpoint);
            this.update_row_markers(row);
            this.close_opened_mixin_settings();
            this.update_mixin_json();
        },

        /**
         * Инициализирует кнопку одновременного изменения настроек по сторонам top/left/right/bottom.
         * Настройки будут меняться в блоке .nc-mixins-mixin-settings-lock-sides (или, если его нет, то
         * во всём блоке настроек миксина), в котором расположена указанная кнопка.
         * @param lock_button
         */
        init_lock_sides_button: function(lock_button) {
            var container = lock_button.closest('.nc-mixins-mixin-settings-lock-sides, .nc-mixins-mixin-settings'),
                lock_input = container.find(':hidden[name^="mixin.settings"][name$=".lock_sides"]'),
                inputs = container.find(':input');

            lock_input.change(function() {
                lock_button
                    .removeClass('nc-icon-lock nc-icon-unlock')
                    .addClass(lock_input.val() ? 'nc-icon-lock' : 'nc-icon-unlock');
            }).change();

            lock_button.click(function() {
                var wasLocked = lock_input.val() === '1';
                lock_input.val(wasLocked ? '' : '1').change();
                // При включении замка синхронизируем все поля со значениями из top
                if (!wasLocked) {
                    inputs.filter('[name*="top"]').each(function() {
                        $(this).trigger('change').trigger('input');
                    });
                }
            });

            var trbl = '(top|right|bottom|left)',
                trbl_regexp = new RegExp(trbl);

            inputs.on('change input blur', function() {
                if (lock_input.val() === '1') {
                    var attributes = this.attributes,
                        value = $(this).val(),
                        input_name_regexp = new RegExp(this.name.replace(/\./g, '\\.').replace(trbl_regexp, trbl)),
                        inputs_to_sync = inputs.filter(function() { return (this.name && this.name.match(input_name_regexp)); });
                    inputs_to_sync.val(value);
                    $.each(attributes, function(index, attribute){
                        if (!attribute.name.indexOf('data-sync-')) {
                            inputs_to_sync.attr(attribute.name, attribute.value);
                        }
                    });
                    inputs.filter('.minicolors-input').each(function() {
                        var input = $(this);
                        input.minicolors('value', input.val());
                    });
                    // Обновляем swatch'и palette-picker для синхронизированных цветовых полей
                    inputs_to_sync.filter('[data-palette-color]').each(function() {
                        var $input = $(this);
                        // Wrapper создаётся после input'а (или после родительского label)
                        var $parentLabel = $input.parent('label');
                        var $wrapper = $parentLabel.length
                            ? $parentLabel.next('.nc-palette-picker')
                            : $input.next('.nc-palette-picker');
                        if ($wrapper.length) {
                            var $swatch = $wrapper.find('.nc-palette-picker-swatch');
                            var color = $input.val();
                            if ($swatch.length && typeof ncPalettePicker !== 'undefined') {
                                ncPalettePicker.setSwatchColor($swatch, color || 'transparent');
                                $swatch.toggleClass('nc--empty', !color);
                            }
                        }
                    });
                }
            });
        },

        /**
         * Возвращает <options> с шрифтами
         * @returns {String}
         */
        get_font_options_html: function() {
            var editor = this;
            if (!font_select_options_cache) {
                $.each(editor.fonts, function(i, font) {
                    font_select_options_cache += "<option name='" + font.name +
                        "' data-asset='" + font.asset +
                        "' data-fallback='" + font.fallback +
                        "' style='font-family:&quot;" + font.name + "&quot;'>" +
                        font.name + "</option>";
                });
            }
            return font_select_options_cache;
        },

        /**
         * Добавляет опции для выбора шрифтов (список шрифтов передан в data-fonts
         * в шаблоне редактора)
         * @param select
         */
        init_font_select: function(select) {
            select.html(select.html() + this.get_font_options_html());
        },

        /**
         * Инициализирует библиотеку для выбора цвета на соответствующих полях
         * @param inputs
         */
        init_color_inputs: function(inputs) {
            var editor = this;
            var infoblock_id = this.infoblock_id ? this.infoblock_id : 0;
            var block = $('.tpl-block-' + infoblock_id + ', .tpl-container-' + infoblock_id);
            var minicolors_options = {
                format: 'rgb',
                keywords: 'transparent',
                opacity: true,
                swatches: [],
                theme: 'netcat'
            };

            // Семантические роли (5 базовых)
            var role_names = {
                '--tpl-color-foreground-main': 'Текст',
                '--tpl-color-foreground-accent': 'Текст доп.',
                '--tpl-color-brand': 'Бренд',
                '--tpl-color-background-accent': 'Фон доп.',
                '--tpl-color-background-main': 'Фон'
            };

            var semantic_roles = Object.keys(role_names);

            var calculated_colors = {};

            // Вспомогательная функция для проверки наличия цветов в палитре
            var hasPaletteColors = function(p) {
                if (!p || !p.palette) return false;
                if (Array.isArray(p.palette)) return p.palette.length > 0;
                return typeof p.palette === 'object' && Object.keys(p.palette).length > 0;
            };

            // Сначала пробуем получить палитру из глобального объекта (установленного редактором палитры)
            if (window.ncSiteColorList && hasPaletteColors(window.ncSiteColorList)) {
                var sitePalette = window.ncSiteColorList;
                var roleToVar = {
                    'foreground_main': '--tpl-color-foreground-main',
                    'foreground_accent': '--tpl-color-foreground-accent',
                    'brand': '--tpl-color-brand',
                    'background_main': '--tpl-color-background-main',
                    'background_accent': '--tpl-color-background-accent'
                };

                // Добавляем роли
                for (var role in sitePalette.roles) {
                    if (sitePalette.roles[role] && roleToVar[role]) {
                        var varName = roleToVar[role];
                        minicolors_options.swatches.push({
                            name: 'var(' + varName + ')',
                            color: sitePalette.roles[role],
                            label: role_names[varName] || role
                        });
                        calculated_colors['var(' + varName + ')'] = sitePalette.roles[role];
                    }
                }

                // Добавляем остальные цвета палитры
                // Поддержка как нового формата (объект с ключами), так и старого (массив)
                if (sitePalette.palette && typeof sitePalette.palette === 'object') {
                    if (Array.isArray(sitePalette.palette)) {
                        // Старый формат: массив
                        sitePalette.palette.forEach(function(color, index) {
                            var varName = '--tpl-color-list-' + index;
                            var alreadyAdded = minicolors_options.swatches.some(function(s) {
                                return s.color.toUpperCase() === color.toUpperCase();
                            });
                            if (!alreadyAdded) {
                                minicolors_options.swatches.push({
                                    name: 'var(' + varName + ')',
                                    color: color
                                });
                                calculated_colors['var(' + varName + ')'] = color;
                            }
                        });
                    } else {
                        // Новый формат: объект с 4-значными ключами
                        Object.keys(sitePalette.palette).forEach(function(key) {
                            var color = sitePalette.palette[key];
                            var varName = '--tpl-color-list-' + key;
                            var alreadyAdded = minicolors_options.swatches.some(function(s) {
                                return s.color.toUpperCase() === color.toUpperCase();
                            });
                            if (!alreadyAdded) {
                                minicolors_options.swatches.push({
                                    name: 'var(' + varName + ')',
                                    color: color
                                });
                                calculated_colors['var(' + varName + ')'] = color;
                            }
                        });
                    }
                }
            }
            // Fallback: читаем из CSS-переменных блока (если доступен)
            else if (infoblock_id > 0 && block.length > 0) {
                var styles = getComputedStyle(block[0]);

                // Добавляем семантические роли
                $.each(semantic_roles, function (index, color) {
                    var palette_color = styles.getPropertyValue(color);
                    if (palette_color) {
                        minicolors_options.swatches.push({
                            name: 'var(' + color + ')',
                            color: palette_color.trim(),
                            label: role_names[color]
                        });
                        calculated_colors['var(' + color + ')'] = palette_color.trim();
                    }
                });

                // Добавляем цвета из расширенной палитры (--tpl-color-list-N)
                for (var i = 0; i < 20; i++) {
                    var varName = '--tpl-color-list-' + i;
                    var paletteColor = styles.getPropertyValue(varName);
                    if (paletteColor && paletteColor.trim()) {
                        // Проверяем, что цвет ещё не добавлен (может дублироваться с ролями)
                        var colorValue = paletteColor.trim();
                        var alreadyAdded = minicolors_options.swatches.some(function(s) {
                            return s.color === colorValue;
                        });
                        if (!alreadyAdded) {
                            minicolors_options.swatches.push({
                                name: 'var(' + varName + ')',
                                color: colorValue
                            });
                            calculated_colors['var(' + varName + ')'] = colorValue;
                        }
                    } else {
                        break; // прекращаем при первом пустом
                    }
                }
            }

            if (minicolors_options.swatches.length > 0) {
                inputs.each(function () {
                    var input = $(this);
                    if (calculated_colors.hasOwnProperty(input.attr('data-sync-color'))) {
                        input.val(calculated_colors[input.attr('data-sync-color')]);
                    }
                });
            }

            this.calculated_colors = calculated_colors;

            inputs
                .minicolors('destroy')
                .minicolors(minicolors_options);

            if (typeof inputs.data('nc-swatches') !== "undefined") {
                $('.minicolors-swatch-color').on('click', function () {
                    var swatch = $(this);

                    if (!swatch.attr('title')) {
                        return true;
                    }

                    var input = swatch.closest('.minicolors-panel').siblings('.minicolors-input');
                    input.attr('data-sync-color', swatch.attr('title'));
                });

                $('.minicolors-panel *').on('click', function (event) {
                    var target = $(event.target);
                    if (target.is('.minicolors-swatches') || target.closest('.minicolors-swatches').length > 0) {
                        return true;
                    }

                    var input = $(this).closest('.minicolors-panel').siblings('.minicolors-input');
                    input.attr('data-sync-color', '').change();
                });
            }

            // Подписываемся на обновления палитры (если ещё не подписаны)
            if (!this._paletteUpdateBound) {
                this._paletteUpdateBound = true;
                $(document).on('nc-palette-updated', function(e, paletteData) {
                    // Переинициализируем color inputs с новой палитрой
                    var colorInputs = editor.find('[data-color-input]');
                    if (colorInputs.length > 0) {
                        editor.init_color_inputs(colorInputs);
                    }
                    // Также переинициализируем palette color pickers
                    var paletteColorPickers = editor.find('[data-palette-color]');
                    if (paletteColorPickers.length > 0) {
                        editor.init_palette_color_pickers(paletteColorPickers);
                    }
                });
            }
        },

        set_color_input: function(input, value) {
            if (value.indexOf('var') === 0 && this.calculated_colors.hasOwnProperty(value)) {
                input.minicolors('value', { color: this.calculated_colors[value] });
            } else {
                input.minicolors('value', { color: value });
            }
        },

        /**
         * Инициализирует новый компонент выбора цвета из палитры
         * Использует общую утилиту ncPalettePicker для единообразия с миксином "Цвет элементов"
         * @param inputs — элементы с атрибутом data-palette-color
         */
        init_palette_color_pickers: function(inputs) {
            var editor = this;

            // Проверяем наличие утилиты ncPalettePicker
            if (typeof window.ncPalettePicker === 'undefined') {
                console.warn('ncPalettePicker not loaded');
                return;
            }

            inputs.each(function() {
                var $input = $nc(this);

                // Пропускаем элементы внутри шаблона (они будут инициализированы после клонирования)
                if ($input.closest('.nc-mixins-mixin-settings-row-template').length) {
                    return;
                }

                var $parentLabel = $input.parent('label');

                // Если есть старый wrapper без обработчиков (склонированный из шаблона), удаляем его
                var $existingWrapper = $parentLabel.length
                    ? $parentLabel.next('.nc-palette-picker')
                    : $input.next('.nc-palette-picker');
                if ($existingWrapper.length && !$existingWrapper.data('has-events')) {
                    $existingWrapper.remove();
                }

                // Проверяем, не инициализирован ли уже
                if ($input.data('palette-picker-initialized')) {
                    // Picker уже создан — просто обновляем swatch с текущим значением
                    var syncColor = $input.attr('data-sync-color') || '';
                    var currentVal = (syncColor && syncColor.indexOf('var(') === 0) ? syncColor : ($input.val() || '');
                    var resolvedColor = editor.resolve_color_value(currentVal);
                    var $existingSwatch = $input.next('.nc-palette-picker').find('.nc-palette-picker-swatch');
                    if (!$existingSwatch.length) {
                        $existingSwatch = $input.parent().next('.nc-palette-picker').find('.nc-palette-picker-swatch');
                    }
                    if (resolvedColor) {
                        ncPalettePicker.setSwatchColor($existingSwatch, resolvedColor).removeClass('nc--empty');
                    } else {
                        ncPalettePicker.setSwatchColor($existingSwatch, 'transparent').addClass('nc--empty');
                    }
                    return;
                }

                // Создаём обёртку для компонента
                var $wrapper = $nc('<div class="nc-palette-picker"></div>').css('margin', '3px 6px');

                // Приоритет: data-sync-color (CSS-переменная) > val() (hex)
                var syncColorInit = $input.attr('data-sync-color') || '';
                var currentValue = (syncColorInit && syncColorInit.indexOf('var(') === 0) ? syncColorInit : ($input.val() || '');
                var displayColor = editor.resolve_color_value(currentValue);

                // Создаём превью цвета (квадратик)
                var isEmpty = !displayColor;
                var $swatch = $nc('<div class="nc-palette-picker-swatch' + (isEmpty ? ' nc--empty' : '') + '" title="' + editor.lang.COLOR_SELECT + '"></div>');
                if (!isEmpty) {
                    ncPalettePicker.setSwatchColor($swatch, displayColor);
                }

                $wrapper.append($swatch);

                // Если input находится внутри <label>, вставляем wrapper после label
                if ($parentLabel.length) {
                    $parentLabel.after($wrapper);
                } else {
                    $input.after($wrapper);
                }
                $input.addClass('nc-palette-picker-input');

                // Переменная для хранения текущего dropdown
                var $currentDropdown = null;

                // Функция для переключения связанного select в 'custom' (для netcat_link и подобных)
                function switchRelatedSelectToCustom() {
                    var $container = $input.closest('.nc-mixins-mixin-settings-netcat-link-color, .nc-mixins-mixin-settings-netcat-link-decoration');
                    if ($container.length) {
                        var $select = $container.find('select').filter(function() {
                            return $nc(this).find('option[value="custom"]').length > 0;
                        });
                        if ($select.length && $select.val() !== 'custom') {
                            $select.val('custom').trigger('change');
                        }
                    }
                }

                // Получаем палитру из доступных источников
                function getPalette() {
                    // 1. Сначала проверяем локальные настройки блока из own_settings
                    if (editor.own_settings) {
                        for (var selector in editor.own_settings) {
                            var selectorSettings = editor.own_settings[selector];
                            if (!selectorSettings || !selectorSettings.color) continue;
                            for (var bp in selectorSettings.color) {
                                var bpData = selectorSettings.color[bp];
                                if (bpData && bpData.mixin === 'netcat_color_palette' && bpData.settings && bpData.settings.palette) {
                                    var localPalette = bpData.settings.palette;
                                    if (typeof localPalette === 'string') {
                                        try { localPalette = JSON.parse(localPalette); } catch(e) { localPalette = null; }
                                    }
                                    if (localPalette && Object.keys(localPalette).length > 0) {
                                        return localPalette;
                                    }
                                }
                            }
                        }
                    }

                    // 2. Затем проверяем глобальную палитру
                    var sitePalette = ncPalettePicker.getSitePalette();
                    if (sitePalette && sitePalette.palette) {
                        return sitePalette.palette;
                    }
                    if (editor.site_color_list && editor.site_color_list.palette) {
                        return editor.site_color_list.palette;
                    }
                    return {};
                }

                // Получаем ролевые цвета из доступных источников
                function getRoleColors() {
                    // 1. Сначала получаем глобальные ролевые цвета как базу
                    var baseRoles = {};
                    var sitePalette = ncPalettePicker.getSitePalette();

                    if (sitePalette && sitePalette.roles && Object.keys(sitePalette.roles).length > 0) {
                        baseRoles = JSON.parse(JSON.stringify(sitePalette.roles)); // копия
                    } else if (editor.site_color_list && editor.site_color_list.roles && Object.keys(editor.site_color_list.roles).length > 0) {
                        baseRoles = JSON.parse(JSON.stringify(editor.site_color_list.roles)); // копия
                    }

                    // 2. Затем получаем локальные настройки блока и накладываем поверх
                    if (editor.own_settings) {
                        for (var selector in editor.own_settings) {
                            var selectorSettings = editor.own_settings[selector];
                            if (!selectorSettings || !selectorSettings.color) continue;
                            for (var bp in selectorSettings.color) {
                                var bpData = selectorSettings.color[bp];
                                if (bpData && bpData.mixin === 'netcat_color_palette' && bpData.settings && bpData.settings.roles) {
                                    var localRoles = bpData.settings.roles;
                                    if (typeof localRoles === 'string') {
                                        try { localRoles = JSON.parse(localRoles); } catch(e) { localRoles = null; }
                                    }
                                    if (localRoles) {
                                        // Накладываем локальные значения поверх базовых (только заполненные)
                                        for (var r in localRoles) {
                                            if (localRoles[r]) {
                                                baseRoles[r] = localRoles[r];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    // 3. Если есть результат — возвращаем
                    if (Object.keys(baseRoles).length > 0) {
                        return baseRoles;
                    }

                    // 4. Fallback: получаем из CSS-переменных iframe с сайтом
                    var roles = {};
                    var siteFrame = document.getElementById('nc-site-frame');
                    var targetDoc = siteFrame ? siteFrame.contentDocument : document;
                    var targetElement = targetDoc ? targetDoc.body : null;

                    if (targetElement) {
                        try {
                            var styles = getComputedStyle(targetElement);
                            var roleMap = {
                                '--tpl-color-foreground-main': 'foreground_main',
                                '--tpl-color-foreground-accent': 'foreground_accent',
                                '--tpl-color-brand': 'brand',
                                '--tpl-color-background-main': 'background_main',
                                '--tpl-color-background-accent': 'background_accent'
                            };
                            for (var cssVar in roleMap) {
                                var colorValue = styles.getPropertyValue(cssVar);
                                if (colorValue && colorValue.trim()) {
                                    roles[roleMap[cssVar]] = colorValue.trim();
                                }
                            }
                        } catch(e) { /* cross-origin or other error */ }
                    }

                    return roles;
                }

                // Получаем site_id для добавления в палитру
                function getSiteId() {
                    var sitePalette = ncPalettePicker.getSitePalette();
                    if (sitePalette && sitePalette.site_id) {
                        return sitePalette.site_id;
                    }
                    if (editor.site_color_list && editor.site_color_list.site_id) {
                        return editor.site_color_list.site_id;
                    }
                    return editor.site_id || null;
                }

                // Клик по квадратику (swatch) — открыть dropdown
                $swatch.on('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Закрываем другие открытые dropdown'ы
                    $nc('.nc-palette-picker-dropdown.nc--open').each(function() {
                        var cleanup = $nc(this).data('cleanup');
                        if (cleanup) cleanup();
                        $nc(this).remove();
                    });

                    // Если этот dropdown уже открыт — закрываем
                    if ($currentDropdown && $currentDropdown.length) {
                        var cleanup = $currentDropdown.data('cleanup');
                        if (cleanup) cleanup();
                        $currentDropdown.remove();
                        $currentDropdown = null;
                        return;
                    }

                    // Текущее значение цвета (разрешаем CSS-переменную в hex)
                    var syncColor = $input.attr('data-sync-color') || '';
                    var currentValue = (syncColor && syncColor.indexOf('var(') === 0) ? syncColor : ($input.val() || '');
                    var currentColor = editor.resolve_color_value(currentValue);

                    // Создаём dropdown через общую утилиту
                    $currentDropdown = ncPalettePicker.create({
                        palette: getPalette(),
                        roleColors: getRoleColors(),
                        currentColor: currentColor,
                        canAddToPalette: true,
                        siteId: getSiteId(),
                        lang: editor.lang,
                        onSelect: function(color, paletteKey, roleKey) {
                            if (!color) {
                                // Сброс цвета
                                $input.val('').attr('data-sync-color', '').trigger('change').trigger('input');
                                ncPalettePicker.setSwatchColor($swatch, 'transparent').addClass('nc--empty');
                            } else {
                                switchRelatedSelectToCustom();
                                var varName = '';
                                if (roleKey) {
                                    // Ролевой цвет: foreground_main → --tpl-color-foreground-main
                                    varName = 'var(--tpl-color-' + roleKey.replace(/_/g, '-') + ')';
                                } else if (paletteKey) {
                                    // Индексный цвет из палитры
                                    varName = 'var(--tpl-color-list-' + paletteKey + ')';
                                }
                                $input.val(color).attr('data-sync-color', varName).trigger('change').trigger('input');
                                ncPalettePicker.setSwatchColor($swatch, color).removeClass('nc--empty');
                            }
                        },
                        onClose: function() {
                            // Восстанавливаем overflow родительских контейнеров
                            $wrapper.parents('.nc-modal-dialog-body, .nc-mixins-mixin-settings-container').each(function() {
                                var $el = $nc(this);
                                var originalOverflow = $el.data('original-overflow');
                                if (originalOverflow !== undefined) {
                                    $el.css('overflow', originalOverflow);
                                    $el.removeData('original-overflow');
                                }
                            });
                            $currentDropdown = null;
                        }
                    });

                    // Временно отключаем прокрутку в родительских контейнерах
                    $wrapper.parents('.nc-modal-dialog-body, .nc-mixins-mixin-settings-container').each(function() {
                        var $el = $nc(this);
                        $el.data('original-overflow', $el.css('overflow'));
                        $el.css('overflow', 'visible');
                    });

                    $currentDropdown.addClass('nc--open');
                    $wrapper.append($currentDropdown);
                });

                // Закрытие dropdown при клике вне
                $nc(document).on('click.palette-picker', function(e) {
                    if (!$nc(e.target).closest('.nc-palette-picker').length) {
                        if ($currentDropdown && $currentDropdown.length) {
                            var cleanup = $currentDropdown.data('cleanup');
                            if (cleanup) cleanup();
                            $currentDropdown.remove();
                            $currentDropdown = null;
                        }
                    }
                });

                $input.data('palette-picker-initialized', true);
                $wrapper.data('has-events', true);
            });
        },

        /**
         * Преобразует цвет в hex формат (6 символов, без прозрачности)
         * @param {string} color - цвет в любом формате (rgb, rgba, hex)
         * @returns {string} - цвет в формате #RRGGBB
         */
        color_to_hex: function(color) {
            if (!color) return '#000000';

            // Если уже hex
            if (color.charAt(0) === '#') {
                return color.toUpperCase().substring(0, 7);
            }

            // Если rgb/rgba
            var match = color.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
            if (match) {
                var r = parseInt(match[1]).toString(16).padStart(2, '0');
                var g = parseInt(match[2]).toString(16).padStart(2, '0');
                var b = parseInt(match[3]).toString(16).padStart(2, '0');
                return ('#' + r + g + b).toUpperCase();
            }

            return color;
        },

        /**
         * Преобразует цвет в 8-значный hex формат с прозрачностью
         * @param {string} color - цвет в любом формате (rgb, rgba, hex)
         * @returns {string} - цвет в формате #RRGGBBAA или #RRGGBB если непрозрачный
         */
        color_to_hex8: function(color) {
            if (!color) return '#000000';

            // Если уже 8-значный hex
            if (color.charAt(0) === '#' && color.length === 9) {
                return color.toUpperCase();
            }

            // Если 6-значный hex — непрозрачный
            if (color.charAt(0) === '#' && color.length === 7) {
                return color.toUpperCase();
            }

            // Если rgb (без alpha)
            var matchRgb = color.match(/^rgb\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*\)$/i);
            if (matchRgb) {
                var r = parseInt(matchRgb[1]).toString(16).padStart(2, '0');
                var g = parseInt(matchRgb[2]).toString(16).padStart(2, '0');
                var b = parseInt(matchRgb[3]).toString(16).padStart(2, '0');
                return ('#' + r + g + b).toUpperCase();
            }

            // Если rgba
            var matchRgba = color.match(/^rgba\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*,\s*([\d.]+)\s*\)$/i);
            if (matchRgba) {
                var r = parseInt(matchRgba[1]).toString(16).padStart(2, '0');
                var g = parseInt(matchRgba[2]).toString(16).padStart(2, '0');
                var b = parseInt(matchRgba[3]).toString(16).padStart(2, '0');
                var a = Math.round(parseFloat(matchRgba[4]) * 255).toString(16).padStart(2, '0');
                // Если полностью непрозрачный — возвращаем 6-значный
                if (a.toUpperCase() === 'FF') {
                    return ('#' + r + g + b).toUpperCase();
                }
                return ('#' + r + g + b + a).toUpperCase();
            }

            return color;
        },


        /**
         * Получает цвета палитры для компонента выбора
         * @returns {Array}
         */
        get_palette_colors: function() {
            var colors = [];
            var role_names = {
                '--tpl-color-foreground-main': 'Текст',
                '--tpl-color-foreground-accent': 'Текст доп.',
                '--tpl-color-brand': 'Бренд',
                '--tpl-color-background-accent': 'Фон доп.',
                '--tpl-color-background-main': 'Фон'
            };

            // Сначала пробуем из глобального объекта ncSiteColorList
            // Вспомогательная функция для проверки наличия цветов в палитре
            var hasPaletteColors = function(p) {
                if (!p || !p.palette) return false;
                if (Array.isArray(p.palette)) return p.palette.length > 0;
                return typeof p.palette === 'object' && Object.keys(p.palette).length > 0;
            };

            if (window.ncSiteColorList && hasPaletteColors(window.ncSiteColorList)) {
                var sitePalette = window.ncSiteColorList;
                var roleToVar = {
                    'foreground_main': '--tpl-color-foreground-main',
                    'foreground_accent': '--tpl-color-foreground-accent',
                    'brand': '--tpl-color-brand',
                    'background_main': '--tpl-color-background-main',
                    'background_accent': '--tpl-color-background-accent'
                };

                // Сначала добавляем роли (с подписями)
                var rolesOrder = ['foreground_main', 'foreground_accent', 'brand', 'background_main', 'background_accent'];
                rolesOrder.forEach(function(role) {
                    if (sitePalette.roles[role]) {
                        var varName = roleToVar[role];
                        colors.push({
                            name: 'var(' + varName + ')',
                            color: sitePalette.roles[role],
                            label: role_names[varName]
                        });
                    }
                });

                // Затем остальные цвета палитры (без подписей)
                // Поддержка как нового формата (объект), так и старого (массив)
                if (Array.isArray(sitePalette.palette)) {
                    sitePalette.palette.forEach(function(color, index) {
                        var varName = '--tpl-color-list-' + index;
                        var alreadyAdded = colors.some(function(c) {
                            return c.color.toUpperCase() === color.toUpperCase();
                        });
                        if (!alreadyAdded) {
                            colors.push({
                                name: 'var(' + varName + ')',
                                color: color,
                                label: null
                            });
                        }
                    });
                } else if (typeof sitePalette.palette === 'object') {
                    Object.keys(sitePalette.palette).forEach(function(key) {
                        var color = sitePalette.palette[key];
                        var varName = '--tpl-color-list-' + key;
                        var alreadyAdded = colors.some(function(c) {
                            return c.color.toUpperCase() === color.toUpperCase();
                        });
                        if (!alreadyAdded) {
                            colors.push({
                                name: 'var(' + varName + ')',
                                color: color,
                                label: null
                            });
                        }
                    });
                }
            }

            // Fallback: из CSS-переменных iframe с сайтом
            if (colors.length === 0) {
                var siteFrame = document.getElementById('nc-site-frame');
                var targetDoc = siteFrame ? siteFrame.contentDocument : document;
                var targetElement = targetDoc ? targetDoc.body : null;

                if (targetElement) {
                    try {
                        var styles = getComputedStyle(targetElement);
                        var semantic_roles = [
                            '--tpl-color-foreground-main',
                            '--tpl-color-foreground-accent',
                            '--tpl-color-brand',
                            '--tpl-color-background-main',
                            '--tpl-color-background-accent'
                        ];

                        // Добавляем роли
                        semantic_roles.forEach(function(varName) {
                            var colorValue = styles.getPropertyValue(varName);
                            if (colorValue && colorValue.trim()) {
                                colors.push({
                                    name: 'var(' + varName + ')',
                                    color: colorValue.trim(),
                                    label: role_names[varName]
                                });
                            }
                        });

                        // Добавляем индексные цвета палитры
                        for (var i = 0; i < 20; i++) {
                            var varName = '--tpl-color-list-' + i;
                            var paletteColor = styles.getPropertyValue(varName);
                            if (paletteColor && paletteColor.trim()) {
                                var colorValue = paletteColor.trim();
                                var alreadyAdded = colors.some(function(c) {
                                    return c.color === colorValue;
                                });
                                if (!alreadyAdded) {
                                    colors.push({
                                        name: 'var(' + varName + ')',
                                        color: colorValue,
                                        label: null
                                    });
                                }
                            } else {
                                break;
                            }
                        }
                    } catch(e) {
                        // Ошибка чтения CSS-переменных — игнорируем
                    }
                }
            }

            // Последний fallback: дефолтные цвета
            if (colors.length === 0) {
                colors = [
                    { name: '#212121', color: '#212121', label: 'Текст' },
                    { name: '#757575', color: '#757575', label: 'Текст доп.' },
                    { name: '#1976D2', color: '#1976D2', label: 'Бренд' },
                    { name: '#FFFFFF', color: '#FFFFFF', label: 'Фон' },
                    { name: '#F5F5F5', color: '#F5F5F5', label: 'Фон доп.' },
                    { name: '#E3F2FD', color: '#E3F2FD', label: null },
                    { name: '#0D47A1', color: '#0D47A1', label: null },
                    { name: '#ECEFF1', color: '#ECEFF1', label: null }
                ];
            }

            return colors;
        },


        /**
         * Преобразует значение цвета (var() или hex) в отображаемый цвет
         * @param value
         * @returns {string}
         */
        resolve_color_value: function(value, _depth) {
            if (!value) return '';

            // Роль палитры может ссылаться на цвет палитры (var(--tpl-color-list-KEY)),
            // а не хранить hex напрямую — такую ссылку нужно дорезолвить рекурсивно,
            // иначе вернётся строка var(...) и превью-квадратик окажется белым.
            _depth = _depth || 0;
            if (_depth > 5) return value; // защита от зацикливания ссылок

            // Если это CSS-переменная, пытаемся разрешить её значение
            if (value.indexOf('var(') === 0) {
                // Извлекаем имя переменной из var(--name)
                var varMatch = value.match(/var\((--[^)]+)\)/);
                if (varMatch) {
                    var varName = varMatch[1];
                    var roleMap = {
                        '--tpl-color-foreground-main': 'foreground_main',
                        '--tpl-color-foreground-accent': 'foreground_accent',
                        '--tpl-color-brand': 'brand',
                        '--tpl-color-background-main': 'background_main',
                        '--tpl-color-background-accent': 'background_accent'
                    };

                    // Приоритет 1: проверяем локальные настройки блока (own_settings)
                    if (this.own_settings && roleMap[varName]) {
                        for (var selector in this.own_settings) {
                            var selectorSettings = this.own_settings[selector];
                            if (!selectorSettings || !selectorSettings.color) continue;
                            for (var bp in selectorSettings.color) {
                                var bpData = selectorSettings.color[bp];
                                if (bpData && bpData.mixin === 'netcat_color_palette' && bpData.settings && bpData.settings.roles) {
                                    var localRoles = bpData.settings.roles;
                                    if (typeof localRoles === 'string') {
                                        try { localRoles = JSON.parse(localRoles); } catch(e) { localRoles = null; }
                                    }
                                    if (localRoles && localRoles[roleMap[varName]]) {
                                        return this.resolve_color_value(localRoles[roleMap[varName]], _depth + 1);
                                    }
                                }
                            }
                        }
                    }

                    // Приоритет 2: читаем из актуального ncSiteColorList
                    if (window.ncSiteColorList && window.ncSiteColorList.roles) {
                        if (roleMap[varName] && window.ncSiteColorList.roles[roleMap[varName]]) {
                            return this.resolve_color_value(window.ncSiteColorList.roles[roleMap[varName]], _depth + 1);
                        }
                        // Проверяем индексные цвета палитры (--tpl-color-list-KEY или --tpl-color-list-N)
                        var paletteMatch = varName.match(/--tpl-color-list-([a-z0-9]+)/i);
                        if (paletteMatch && window.ncSiteColorList.palette) {
                            var key = paletteMatch[1];
                            // Поддержка как нового формата (объект с ключами), так и старого (массив с числовыми индексами)
                            if (Array.isArray(window.ncSiteColorList.palette)) {
                                var index = parseInt(key, 10);
                                if (!isNaN(index) && window.ncSiteColorList.palette[index]) {
                                    return window.ncSiteColorList.palette[index];
                                }
                            } else if (window.ncSiteColorList.palette[key]) {
                                return window.ncSiteColorList.palette[key];
                            }
                        }
                    }

                    // Приоритет 3: используем кэшированные значения
                    if (this.calculated_colors[value]) {
                        return this.calculated_colors[value];
                    }
                }
            }
            return value;
        },

        /**
         * Сравнивает два цвета (нормализует к uppercase hex)
         * @param a
         * @param b
         * @returns {boolean}
         */
        colors_equal: function(a, b) {
            if (!a || !b) return false;
            return this.normalize_color(a) === this.normalize_color(b);
        },

        /**
         * Нормализует цвет к uppercase hex
         * @param color
         * @returns {string}
         */
        normalize_color: function(color) {
            if (!color) return '';
            color = color.trim();
            if (color.charAt(0) === '#') {
                if (color.length === 4) {
                    color = '#' + color[1] + color[1] + color[2] + color[2] + color[3] + color[3];
                }
                return color.toUpperCase();
            }
            var match = color.match(/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
            if (match) {
                var r = parseInt(match[1], 10);
                var g = parseInt(match[2], 10);
                var b = parseInt(match[3], 10);
                return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1).toUpperCase();
            }
            return color;
        },

        /**
         * Добавляет в HEAD CSS-файлы шрифтов, указанных в data-fonts
         */
        load_fonts: function() {
            var head = $('head');
            this.fonts = this.container.data('fonts');
            $.each(this.fonts, function(i, font_data) {
                $.each(font_data.css, function(i, file) {
                    if (!$('link[href="' + file + '"]', head).length) {
                        head.append($('<link>', { rel: 'stylesheet', href: file }));
                    }
                });
            });
        },

        /**
         * Возвращает Element (не jQuery-коллекцию!), в котором находится инфоблок
         * (если инфоблок был указан в options.infoblock_id; если нет — то вернёт null)
         * Может использоваться в формах настроек миксинов.
         * @returns {Element|null}
         */
        get_infoblock_element: function() {
            var id = this.infoblock_id;
            return id ? document.querySelector('.tpl-block-' + id + ', .tpl-container-' + id) : null;
        },

        /**
         * Инициализирует загрузчик файлов в поле фона (изображение, видео, параллакс)
         * @param uploaders
         */
        init_uploader: function(uploaders) {
            var editor = this;

            function show_error(message) {
                var dialog = nc.ui.modal_dialog.get_current_dialog();
                if (dialog) {
                    dialog.show_error(message);
                } else {
                    alert(message);
                }
            }

            function save_file(container) {
                var url_field = container.find('input[type="url"]'),
                    file_field = container.find('input[type="file"]'),
                    path_field = container.find('.nc-upload-file-server-path'),
                    mixin_keyword = container.closest('.nc-mixins-mixin-settings').data('mixin-keyword'),
                    // max_file_size = uploader.closest('.nc-mixins-editor').data('upload-max-filesize'),
                    // allowed_types = uploader.data('uploader-allowed'),
                    // ext_filter = uploader.data('uploader-ext')
                    data = new FormData();

                url_field.addClass('nc--loading');

                data.append('file', file_field[0].files[0] || null);
                data.append('local_file', path_field.val());
                data.append('scope', editor.scope);
                data.append('infoblock_id', editor.infoblock_id);
                data.append('mixin_keyword', mixin_keyword);

                $nc.ajax({
                    url: NETCAT_PATH + 'action.php?ctrl=admin.mixin_file&action=save&isNaked=1&admin_modal=1',
                    type: 'POST',
                    dataType: 'json',
                    data: data,
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        if (data.result === 'ok') {
                            if (data.url) {
                                url_field.val(data.url).trigger('change');
                            }
                        } else {
                            show_error('Не удалось загрузить файл на сервер: ' + JSON.stringify(data));
                        }
                    },
                    error: function(xhr, status, error) {
                        show_error('Не удалось загрузить файл на сервер: ' + error);
                    },
                    complete: function() {
                        url_field.removeClass('nc--loading');
                    }
                });
            }

            uploaders.each(function() {
                var uploader = $nc(this),
                    file_field = uploader.find('input[type="file"]');

                uploader.find('.nc-mixin-upload').on('click', function() {
                    file_field.click();
                });

                file_field.on('change', function () {
                    save_file(uploader);
                });

                uploader.find('.nc-upload-file-server-path').on('change', function() {
                    save_file(uploader);
                });
            });
        },


        /**
         * Инициализирует тулбар (копирование, вставка, сброс)
         */
        init_toolbar_buttons: function () {
            var editor = this;
            var buttons = this.find('.nc-mixins-toolbar-button');
            buttons.click(this.toolbar_button_show_ok);

            var paste_button = buttons.filter('.nc-mixins-toolbar-button-paste')
                .click(function () {
                    editor.paste_from_storage();
                });

            var paste_icon = paste_button.find('.nc-icon').eq(0);

            if (localStorage.getItem(this.storage_copy_key) === null) {
                paste_icon.addClass('nc--disabled');
            }

            buttons.filter('.nc-mixins-toolbar-button-copy').click(function () {
                editor.copy_to_storage();
                paste_icon.removeClass('nc--disabled');
            });

            buttons.filter('.nc-mixins-toolbar-button-clear').click(function () {
                editor.set_preset_id('0');
                editor.breakpoint_type = 'block';
                editor.set_own_settings({});
            });
        },

        /**
         * Показывает галочку на месте иконки
         */
        toolbar_button_show_ok: function () {
            var icons = $(this).find('i'); // this == .nc-mixins-toolbar-button
            var main = icons.eq(0);
            var status = icons.eq(1);

            if (icons.length !== 2 || main.is('.nc--disabled') || main.data('showing_status')) {
                return;
            }

            var fadeSpeed = 250;
            var statusDuration = 5000;

            main.fadeOut(fadeSpeed, function () {
                main.data('showing_status', true);
                main.hide();
                status.fadeIn(fadeSpeed).delay(statusDuration).fadeOut(fadeSpeed, function () {
                    status.hide();
                    main.fadeIn(fadeSpeed);
                    main.data('showing_status', false);
                });
            });
        },

        /**
         * Копирует настройки в localStorage
         */
        copy_to_storage: function () {
            var data = {
                preset_id: this.find('.nc-mixins-preset-select').val(),
                breakpoint_type: this.find('.nc-mixins-breakpoint-type-select').val(),
                own_settings: this.get_own_settings(),
            };
            localStorage.setItem(this.storage_copy_key, JSON.stringify(data));
        },

        /**
         * Вставляет настройки из localStorage
         */
        paste_from_storage: function () {
            var data = JSON.parse(localStorage.getItem(this.storage_copy_key) || '{}');

            this.find('.nc-mixins-breakpoint-type-select').val(data.breakpoint_type)
            this.on_breakpoint_type_select_change();

            this.set_preset_id(data.preset_id);

            var new_settings = merge_objects(this.get_own_settings(), data.own_settings) || {};
            for (var selector in new_settings) {
                for (var mixin_type in new_settings[selector]) {
                    if (this.find('.nc-mixins-mixin-row[data-mixin-type="' + mixin_type + '"]').is(':hidden')) {
                        delete new_settings[selector][mixin_type];
                    }
                }
            }
            this.set_own_settings(new_settings);
        },

    };

})($nc);

