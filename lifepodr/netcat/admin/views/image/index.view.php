<?php
if (!class_exists('nc_core')) {
    die;
}
$core = nc_core::get_object();
/** @var array $libraries */
/** @var string $current_library */
/** @var string $current_icon */
/** @var string $current_color */
?>

<div class='nc-modal-dialog nc-image-dialog' data-confirm-close="no">
    <div class='nc-modal-dialog-header'>
        <h2><?= NETCAT_MODAL_DIALOG_IMAGE_HEADER; ?></h2>
    </div>
    <form action="<?= nc_controller_url('admin.image', 'save'); ?>" method="post" class='nc-modal-dialog-body nc-form'>
        <input type="hidden" name="library" value="">
        <input type="hidden" name="icon" value="">
        <input type="hidden" name="class_id" value="">
        <input type="hidden" name="message_id" value="">
        <input type="hidden" name="field_id" value="">

        <div data-tab-id='tab-edit' data-tab-caption='<?= NETCAT_MODAL_DIALOG_IMAGE_TAB_EDIT_CAPTION; ?>'>
            <div class="nc-image-dialog-panel">
                <div class="nc-image-dialog-panel-side left">
                    <div class="nc-image-dialog-edit-icon"></div>
                </div>

                <div class="nc-image-dialog-panel-side right centerize">
                    <input type="text" name="color" class="nc-image-dialog-edit-icon-colorpicker">
                    <div class="nc-field nc-field-type-string">
                        <input type="text" class="nc-image-dialog-edit-icon-colorpicker-input"
                               value="#000000"
                               placeholder="<?= NETCAT_MODAL_DIALOG_IMAGE_TAB_EDIT_COLORPICKER_INPUT_PLACEHOLDER; ?>">
                    </div>
                </div>
            </div>
        </div>

        <div data-tab-id='tab-icons' data-tab-caption='<?= NETCAT_MODAL_DIALOG_IMAGE_TAB_ICONS_CAPTION; ?>'>
            <div class="nc-image-dialog-panel">
                <div class="nc-image-dialog-panel-side left">
                    <div class="nc-library-icons-container"></div>
                </div>

                <div class="nc-image-dialog-panel-side right">
                    <div class="nc-field nc-field-type-string nc-image-dialog-search">
                        <input type="text" name="search" value="" class="nc-image-dialog-search-input" id="search"
                               placeholder="<?= NETCAT_MODAL_DIALOG_IMAGE_TAB_ICONS_ICONS_SEARCH_INPUT_PLACEHOLDER; ?>"
                               autocomplete="off">
                    </div>

                    <div class="nc_radio_modal">
                        <div>
                            <label>
                                <input type="radio" name="selected_library" value="all"
                                       class="nc-image-dialog-library-choose" checked>
                                <?= NETCAT_MODAL_DIALOG_IMAGE_TAB_ICONS_LIBRARY_CHOOSE; ?>
                            </label>
                        </div>

                        <? foreach ($libraries as $library_keyword => $library_data) { ?>
                            <div>
                                <label>
                                    <input type="radio" name="selected_library" value="<?= $library_keyword; ?>"
                                           class="nc-image-dialog-library-choose">
                                    <?= $library_data['name']; ?>
                                    <span class="library-info">(<?= $library_data['icons_count']; ?>)</span>
                                </label>
                            </div>
                        <? } ?>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class='nc-modal-dialog-footer'>
        <button data-action='submit' class="nc-image-dialog-submit"><?= NETCAT_MODAL_DIALOG_IMAGE_BUTTON_SAVE; ?></button>
        <button data-action='close'><?= NETCAT_MODAL_DIALOG_IMAGE_BUTTON_CLOSE; ?></button>
    </div>

    <script>
        $nc(function() {
            var current_library = '';
            var current_icon = '';
            var current_color = '<?= htmlspecialchars($current_color) ?>';
            var current_dialog = nc.ui.modal_dialog.get_current_dialog();
            var libraries_data = <?= json_encode($libraries) ?>;
            var icons_index = {};
            var loaded_libraries = {};

            current_dialog.hide_tab('tab-edit');

            var items = ['class_id', 'message_id', 'field_id'];
            for (var i in items) {
                var item = items[i];
                var id = current_dialog.get_option(item);
                if (id) {
                    current_dialog.find('input[name="' + item + '"]').val(id);
                }
            }

            // ColorPicker
            function updateSvgColor(color) {
                var svg = current_dialog.find('.nc-image-dialog-edit-icon svg');
                svg.find("[fill]").removeAttr('fill');
                svg.css('fill', color);
            }
            function updateInputColor(color) {
                current_dialog.find('.nc-image-dialog-edit-icon-colorpicker-input').val(color);
            }
            function updatePickerColor(color) {
                colorpicker.minicolors('value', {color: color, opacity: 1});
            }
            var colorpicker = current_dialog.find('.nc-image-dialog-edit-icon-colorpicker').minicolors({
                inline: 'true',
                change: function(value, opacity) {
                    current_color = value;
                    updateSvgColor(value);
                    var $colorInput = current_dialog.find('.nc-image-dialog-edit-icon-colorpicker-input:not(:focus)');
                    if ($colorInput.length) {
                        updateInputColor(value);
                    }
                }
            });
            current_dialog.find('.minicolors-panel').click(function () {
                current_dialog.find('.nc-image-dialog-edit-icon-colorpicker-input').blur();
            });
            current_dialog.find('.nc-image-dialog-edit-icon-colorpicker-input').on('keyup change', function () {
                var color = $nc(this).val();
                if (color) {
                    updatePickerColor(color);
                }
            });

            /**
             * Загрузка HTML библиотеки
             */
            function loadLibrary(libraryKeyword, callback) {
                if (loaded_libraries[libraryKeyword]) {
                    callback(loaded_libraries[libraryKeyword]);
                    return;
                }

                var libraryData = libraries_data[libraryKeyword];
                if (!libraryData) {
                    console.error('Library not found:', libraryKeyword);
                    return;
                }

                $nc.ajax({
                    url: libraryData.http_path,
                    dataType: 'html',
                    success: function(html) {
                        var $icons = $nc(html).children();

                        $icons.each(function() {
                            var $icon = $nc(this);
                            var iconKeyword = $icon.data('icon');
                            var keywords = ($icon.data('keywords') || '').toString();

                            $icon.data('library', libraryKeyword)
                                .prop('title', iconKeyword)
                                .addClass('nc-icon-library-item');

                            if (!icons_index[libraryKeyword]) {
                                icons_index[libraryKeyword] = {};
                            }
                            icons_index[libraryKeyword][iconKeyword] = {
                                element: $icon,
                                keywords: keywords.toLowerCase()
                            };
                        });

                        loaded_libraries[libraryKeyword] = $icons;

                        callback($icons);
                    },
                    error: function() {
                        console.error('Failed to load library:', libraryKeyword);
                    }
                });
            }

            /**
             * Загрузка всех библиотек
             */
            function loadAllLibraries() {
                var $container = current_dialog.find('.nc-library-icons-container');
                $container.empty();

                var librariesCount = Object.keys(libraries_data).length;
                var loadedCount = 0;

                // Сначала создаём пустые секции в правильном порядке
                $nc.each(libraries_data, function(libraryKeyword, libraryData) {
                    var $librarySection = $nc('<div class="nc-image-dialog-library" data-keyword="' + libraryKeyword + '"></div>');
                    $librarySection.append('<h3 class="nc-image-dialog-library-name">' + libraryData.name + '</h3>'); //  + ' (' + libraryData.icons_count + ')
                    $librarySection.append('<div class="nc-image-dialog-library-icons"><span class="loading-text"><?= WIDGET_LIST_LOAD ?></span></div>');
                    $container.append($librarySection);
                });

                // Затем загружаем иконки и заполняем секции
                $nc.each(libraries_data, function(libraryKeyword, libraryData) {
                    loadLibrary(libraryKeyword, function($icons) {
                        loadedCount++;

                        var $librarySection = $container.find('.nc-image-dialog-library[data-keyword="' + libraryKeyword + '"]');
                        var $iconsContainer = $librarySection.find('.nc-image-dialog-library-icons');
                        $iconsContainer.empty().append($icons.clone());

                        if (loadedCount === librariesCount) {
                            attachIconClickHandlers();
                            // Прокручиваем в начало
                            $container.scrollTop(0);
                        }
                    });
                });
            }

            /**
             * Поиск по иконкам
             */
            var search_timeout = null;
            current_dialog.find('.nc-image-dialog-search-input').on('keyup change', function() {
                var query = $nc(this).val().toLowerCase().trim();

                if (search_timeout) {
                    clearTimeout(search_timeout);
                }

                search_timeout = setTimeout(function() {
                    performSearch(query);
                }, 300);
            });

            function performSearch(query) {
                var $libraries = current_dialog.find('.nc-image-dialog-library');

                if (!query) {
                    $libraries.show();
                    $libraries.find('.nc-icon-library-item').show();
                    return;
                }

                var selectedLibrary = current_dialog.find('.nc-image-dialog-library-choose:checked').val();

                $libraries.each(function() {
                    var $librarySection = $nc(this);
                    var libraryKeyword = $librarySection.data('keyword');

                    if (selectedLibrary !== 'all' && libraryKeyword !== selectedLibrary) {
                        return;
                    }

                    if (!icons_index[libraryKeyword]) {
                        return;
                    }

                    var hasVisibleIcons = false;
                    var icons = icons_index[libraryKeyword];

                    $nc.each(icons, function(iconKeyword, iconData) {
                        var $iconElement = $librarySection.find('.nc-icon-library-item[data-icon="' + iconKeyword + '"]');

                        if (iconData.keywords.indexOf(query) !== -1) {
                            $iconElement.show();
                            hasVisibleIcons = true;
                        } else {
                            $iconElement.hide();
                        }
                    });

                    if (hasVisibleIcons) {
                        $librarySection.show();
                    } else {
                        $librarySection.hide();
                    }
                });
            }

            /**
             * Фильтр по библиотеке
             */
            current_dialog.find('.nc-image-dialog-library-choose').change(function () {
                var libraryKeyword = current_dialog.find('.nc-image-dialog-library-choose:checked').val();
                var $libraries = current_dialog.find('.nc-image-dialog-library');

                if (libraryKeyword !== 'all') {
                    $libraries.each(function() {
                        var $library = $nc(this);
                        if ($library.data('keyword') === libraryKeyword) {
                            $library.show();
                        } else {
                            $library.hide();
                        }
                    });
                } else {
                    $libraries.show();
                }

                var query = current_dialog.find('.nc-image-dialog-search-input').val();
                if (query) {
                    performSearch(query);
                }
            });

            /**
             * Обработка клика по иконке
             */
            function attachIconClickHandlers() {
                current_dialog.find('.nc-icon-library-item').off('click').on('click', function() {
                    var $icon = $nc(this);

                    current_dialog.find('.nc-icon-library-item.selected').removeClass('selected');
                    $icon.addClass('selected');

                    // Получаем библиотеку из родительского контейнера
                    current_library = $icon.closest('.nc-image-dialog-library').data('keyword');
                    current_icon = $icon.data('icon');
                    current_dialog.find('input[name="library"]').val(current_library);
                    current_dialog.find('input[name="icon"]').val(current_icon);

                    var $svg = $icon.find('svg').clone();
                    var $editContainer = current_dialog.find('.nc-image-dialog-edit-icon');
                    $editContainer.html($svg);

                    if (current_color) {
                        updatePickerColor(current_color);
                        updateSvgColor(current_color);
                        updateInputColor(current_color);
                    }

                    current_dialog.change_tab('tab-edit');
                });
            }

            // Закрытие диалога
            current_dialog.set_option('on_submit_response', function(response) {
                response = JSON.parse(response);
                var $input = this.get_option('image_dialog_input');
                var tmp_icon_path = response.path;

                if (tmp_icon_path) {
                    $input.val(tmp_icon_path);
                    $input.trigger('change');
                }
                colorpicker.minicolors('destroy');
                current_dialog.destroy();
            });

            loadAllLibraries();

            // Всегда начинаем с вкладки выбора иконок
            current_dialog.change_tab('tab-icons');

            <? if ($current_icon && $current_library) { ?>
                // Если есть текущая иконка, подсвечиваем её в списке (но не переключаемся на редактирование)
                var highlightCurrentIcon = function() {
                    // Проверяем, что библиотека существует в списке
                    if (!libraries_data['<?= $current_library ?>']) {
                        return; // Библиотека не найдена
                    }

                    if (loaded_libraries['<?= $current_library ?>']) {
                        var $librarySection = current_dialog.find('.nc-image-dialog-library[data-keyword="<?= $current_library ?>"]');
                        var $currentIcon = $librarySection.find('.nc-icon-library-item[data-icon="<?= $current_icon ?>"]');
                        if ($currentIcon.length) {
                            // Подсвечиваем текущую иконку
                            $currentIcon.addClass('selected');
                            // Прокручиваем к ней
                            var $container = current_dialog.find('.nc-library-icons-container');
                            $container.scrollTop($currentIcon.offset().top - $container.offset().top + $container.scrollTop() - 100);
                        }
                    } else {
                        // Ждем загрузки библиотеки
                        setTimeout(highlightCurrentIcon, 100);
                    }
                };
                highlightCurrentIcon();
            <? } ?>
        });
    </script>
</div>
