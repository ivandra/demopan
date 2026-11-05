/**
 *
 * Загрузчик файлов (для полей одиночной и множественной загрузки).
 *
 * Требует для работы наличия особой разметки — см. nc_file_field(),
 * nc_multifield_template::get_form().
 *
 *
 * При выборе одновременно нескольких файлов и дальнейшем их удалении файлы
 * всё равно передаются на сервер, но игнорируются там (это сделано для упрощения
 * сопоставления дополнительных данных о файлах с загруженными файлами).
 *
 * Для каждого файла в множественной загрузке передаются параметры (F = имя поля):
 *  — $_FILES[f_F_file][]          загружаемые файлы
 *  — multifile_id[F][]            ID файла в Multifield (для новых файлов = '')
 *  — multifile_upload_index[F][]  порядковый номер файла в $_FILES[f_F_file][] (для старых файлов = -1)
 *  — multifile_name[F][]          поле 'Name' (описание/пользовательское название) для файла (если включено в настройках)
 *  — multifile_delete[F][]        1, если файл нужно удалить (как для новых, так и для старых файлов)
 *
 *  Порядок сортировки файлов определяется последовательностью multifile_*.
 *
 *  Дополнительно для полей множественной загрузки передаётся параметр multifile_js[F] = 1.
 *
 */
(function ($) {

    if (!$) {
        return;
    }

    // Максимальный размер изображений, которые отображаются при предпросмотре загружаемых файлов:
    const MAX_IMAGE_PREVIEW_SIZE = 5 * 1024 * 1024;

    /**
     * Добавляет один файл из <input type="file">
     * @param input
     */
    function processSingleFileInputChange(input) {
        if (!input.value) {
            return;
        }

        let hadOldFile = removeSingleFileOnChange(input);
        let newFile = addFileBlock(input, 0).hide();

        if (hadOldFile) {
            newFile.show();
        } else {
            newFile.slideDown(100);
        }

        newFile.find('.nc-upload-file-custom-name input:text').focus();
    }

    /**
     * Прячет ранее загруженный файл для одиночной загрузки
     */
    function removeSingleFileOnChange(input) {
        let container = getUploadContainer($(input)),
            oldFile = container.find('.nc-upload-file:visible');

        if (!oldFile) {
            return false;
        }

        if (container.hasClass('nc-upload-with-preview')) {
            oldFile.css({maxHeight: oldFile.height() + 'px', width: '1px', marginRight: '-1px'});
        } else {
            oldFile.css({height: '1px', marginBottom: '-1px'});
        }

        oldFile.css({
            opacity: 0.1,
            marginLeft: 0,
            padding: 0,
            overflow: 'hidden'
        });

        setTimeout(() => oldFile.hide(), 100);

        return true;
    }

    function addMultipleServerFileBlock() {
    }

    /**
     * Обрабатывает добавление файлов в поле множественной загрузки файлов
     * @param input
     */
    function processMultipleFileInputChange(input) {
        let $input = $(input),
            isServerPathInput = $input.hasClass('nc-upload-file-server-path'),
            $uploadContainer = getUploadContainer($input),
            maxFiles = $uploadContainer.data('maxFiles'),
            fieldName = $uploadContainer.data('fieldName'),
            getInputName = function (n) { return 'multifile_' + n + '[' + fieldName + '][]'; },
            fileList = input.files;

        // создание нового <input type=file> на замену текущему
        if (isServerPathInput) {
            fileList = [
                { file: $input.val() }
            ];
        } else {
            $input.hide().clone().val('').appendTo($uploadContainer).show();
            // перенос <input> с выбранным файлом (файлами) в скрытый блок
            $input.removeClass('nc-upload-input').appendTo($uploadContainer.find('.nc-upload-new-files'));
        }

        $.each(fileList, function (i) {
            let uploadIndex = getNextUploadIndex($uploadContainer);
            let uniqueIndexClass = 'nc-upload-file-index--' + uploadIndex;

            // если выбран только один файл, значение можно будет легко сбросить
            // при удалении файла в onFileRemoveClick()
            if (!isServerPathInput && fileList.length === 1) {
                $input.addClass(uniqueIndexClass)
            }

            // добавление блока для файла
            let $fileBlock = addFileBlock(input, i)
                .hide()
                .data('uploadIndex', uploadIndex)
                // добавление дополнительных полей для множественной загрузки
                .append(
                    makeHiddenInput(getInputName('id')),
                    makeHiddenInput(getInputName('upload_index'), uploadIndex),
                    makeHiddenInput(getInputName('delete'), 0).addClass('nc-upload-file-remove-hidden'),
                    makeHiddenInput(getInputName('tmp'), isServerPathInput ? $input.val() : '0')
                );

            if ($uploadContainer.data('customName')) {
                // поле для описания файла
                let placeholder = $uploadContainer.data('customNameCaption'),
                    $customNameInput = makeInput('text', getInputName('name'), '', placeholder);
                $('<div class="nc-upload-file-custom-name"/>').append($customNameInput).appendTo($fileBlock);
            }

            // если достигнут лимит файлов, файл должен быть проигнорирован
            if (maxFiles && maxFiles !== '0' && countFiles($uploadContainer) >= maxFiles) {
                markFileAsDeleted($fileBlock);
            } else {
                $fileBlock.slideDown(100);
            }
        });
    }

    /**
     * Добавляет div для загружаемого файла
     * @param input
     * @param fileIndex
     * @returns {*|jQuery|HTMLElement}
     */
    function addFileBlock(input, fileIndex) {
        let file;
        if (input.type === 'hidden') {
            let url = input.value,
                fileName = url.split('/').pop();
            file = {
                name: fileName,
                type: fileName.split('.').pop(),
                url: url + '?' + Math.random().toString().substring(2),
                size: 0,
            };
        } else {
            file = input.files[fileIndex];
        }
        let $fileBlock = $(
                '<div class="nc-upload-file">' +
                '<div class="nc-upload-file-info">' +
                '<span class="nc-upload-file-name">' + file.name + '</span> ' +
                '<a href="#" class="nc-upload-file-remove" tabindex="-1">×</a>' +
                '</div>' +
                '</div>'
            ),
            $uploadContainer = getUploadContainer(input),
            $filesContainer = $uploadContainer.find('.nc-upload-files');

        $fileBlock.appendTo($filesContainer);
        initFileRemoveClick($fileBlock);

        if (isImageFileType(file.type)) {
            if (file.url) {
                addPreviewBlockAndDragIcon($fileBlock, file.type, file.name, file.url, true);
            } else if (file.size < MAX_IMAGE_PREVIEW_SIZE) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    addPreviewBlockAndDragIcon($fileBlock, file.type, file.name, e.target.result, true);
                };
                reader.readAsDataURL(file);
            }
        } else {
            addPreviewBlockAndDragIcon($fileBlock, file.type, file.name, null, true);
        }

        // скрытие <input> при достижении ограничения на количество файлов
        checkMaxFileNumber($uploadContainer);

        return $fileBlock;
    }

    /**
     * Добавляет div для отображения картинки или иконки и ручку для перетаскивания
     * для режима без предпросмотра
     * @param $fileBlock
     * @param fileType
     * @param fileName
     * @param fileUrl
     * @param animate
     * @returns jQuery
     */
    function addPreviewBlockAndDragIcon($fileBlock, fileType, fileName, fileUrl, animate) {
        let previewClass = 'nc-upload-file-preview',
            dragClass = 'nc-upload-file-drag-handle',
            $preview = $('<div/>').addClass(previewClass + ' ' + dragClass),
            isImage = fileUrl && isImageFileType(fileType);

        if (isImage) {
            $preview.addClass(previewClass + '-image').append('<img src="' + fileUrl + '" />');
            getUploadContainer($fileBlock).addClass('nc-upload-with-preview');
        } else {
            let nameLastDot = fileName.lastIndexOf('.');
            $preview.addClass(previewClass + '-other');
            if (nameLastDot > 0) {
                $preview.append(
                    '<span class="nc-upload-file-extension">' + fileName.substring(nameLastDot + 1) + '</span>'
                );
            }
        }

        $fileBlock.prepend(
            '<div class="nc-upload-file-drag-icon ' + dragClass + '"><i class="nc-icon nc--file-text"></i></div>',
            $preview
        );

        if ((isImage || $fileBlock.closest('.nc-upload-with-preview').length)) {
            animate ? $preview.slideDown(100) : $preview.show();
        }

        return $preview;
    }

    /**
     * Возвращает true, если строка начинается с image
     * @param fileType
     * @returns Boolean
     */
    function isImageFileType(fileType) {
        return /^(image\/)?(jpe?g|png|gif|bmp|webp|svg([+-]xml)?)$/i.test(fileType);
    }

    /**
     * Возвращает true, если элемент имеет класс nc-upload-multifile
     * @param $uploadContainer
     * @returns Boolean
     */
    function isMultifile($uploadContainer) {
        return $uploadContainer.hasClass('nc-upload-multifile');
    }

    /**
     * Возвращает блок .nc-upload, к которому принадлежит элемент
     * @returns {*|jQuery|HTMLElement}
     */
    function getUploadContainer(element) {
        return $(element).closest('.nc-upload');
    }

    function getServerPathInput(element) {
        return getUploadContainer(element).find('.nc-upload-file-server-path');
    }

    /**
     * Возвращает значение свойства data-upload-index и увеличивает его на 1
     * @param $uploadContainer
     * @returns Number
     */
    function getNextUploadIndex($uploadContainer) {
        let property = 'nextUploadIndex',
            currentValue = $uploadContainer.data(property) || 0;
        $uploadContainer.data(property, currentValue + 1);
        return currentValue;
    }

    /**
     * Возвращает количество файлов в upload-блоке
     * @param $container
     * @returns Number
     */
    function countFiles($container) {
        let filterCallback = function () { return $(this).css('display') !== 'none' };
        return $container.find('.nc-upload-file').filter(filterCallback).length;
    }

    /**
     * Проверяет допустимое количество загружаемых файлов; если количество файлов
     * равно или больше допустимого, прячет кнопку загрузки файла
     * @param $uploadContainer
     */
    function checkMaxFileNumber($uploadContainer) {
        if (!isMultifile($uploadContainer)) {
            return;
        }

        let maxFiles = $uploadContainer.data('maxFiles');

        if (maxFiles) {
            let canUploadMoreFiles = countFiles($uploadContainer) < maxFiles;
            $uploadContainer.find('.nc-upload-hide-if-full').toggle(canUploadMoreFiles);
        }
    }

    /**
     * Возвращает новый <input type="hidden">
     * @param name
     * @param value
     * @returns {*}
     */
    function makeHiddenInput(name, value) {
        return makeInput('hidden', name, value)
    }

    /**
     * Возвращает новый <input>
     * @param type
     * @param name
     * @param value
     * @param placeholder
     * @returns {*|jQuery|HTMLElement}
     */
    function makeInput(type, name, value, placeholder = undefined) {
        return $('<input />', {
            type: type,
            name: name,
            value: value === undefined ? '' : value,
            placeholder
        })
    }

    /**
     * Обработка нажатия на «удалить файл»
     * @param e
     * @returns {boolean}
     */
    function onFileRemoveClick(e) {
        e.preventDefault();

        let $fileBlock = $(e.target).closest('.nc-upload-file'),
            $uploadContainer = getUploadContainer($fileBlock),
            $input = $uploadContainer.find('.nc-upload-input'),
            $targetForEvent = $input;

        markFileAsDeleted($fileBlock);

        if (isMultifile($uploadContainer)) {
            // Сбросить значения <input type="file"> при наличии нескольких файлов
            // без смены индексов не получится, так как собьются индексы
            // в multifile_upload_index[F][]...
            // Но можно очистить значение, если файл в поле был только один:
            $uploadContainer.find('.nc-upload-file-index--' + $fileBlock.data('uploadIndex')).val('');
        } else {
            $input.val('');
            getServerPathInput($uploadContainer).val('');
        }

        $fileBlock.slideUp(100, function () {
            // update display type (with/without preview)
            let c = 'nc-upload-with-preview';
            if ($uploadContainer.hasClass(c) && $uploadContainer.find('.nc-upload-file:visible .nc-upload-file-preview-image').length === 0) {
                $uploadContainer.removeClass(c);
            }

            // нужно для вызова обработчика nc_fields_form_inherited_value_div()
            let event = $.Event('change');
            event.target = $targetForEvent[0];
            $(document).trigger(event);

            checkMaxFileNumber($uploadContainer);
        });
    }

    /**
     * Отмечает файл как удалённый.
     * @param $fileBlock
     */
    function markFileAsDeleted($fileBlock) {
        $fileBlock.find('.nc-upload-file-remove-hidden').val(1).trigger('change');
    }

    /**
     * Инициализация обработчика нажатия на «удалить файл»
     * @param $container
     */
    function initFileRemoveClick($container) {
        let event = 'click.nc-upload';
        $container.find('.nc-upload-file-remove').off(event).on(event, onFileRemoveClick);
    }

    // -------------------------------------------------------------------------

    $.fn.upload = function () {

        return this.each(function () {
            let $uploadContainer = $(this);

            if ($uploadContainer.hasClass('nc-upload-applied')) { return; }
            $uploadContainer.addClass('nc-upload-applied');

            let multifile = isMultifile($uploadContainer),
                $filesContainer = $uploadContainer.find('.nc-upload-files'),
                $allFiles = $filesContainer.find('.nc-upload-file');

            if ($allFiles.length > 0 && !multifile) {
                $uploadContainer.find('.nc-upload-input').hide();
            }

            // Добавление превьюшек ко всем файлам
            $allFiles.each(function () {
                let $fileBlock = $(this),
                    fileType = $fileBlock.data('type'),
                    a = $fileBlock.find('.nc-upload-file-name'),
                    fileName = a.html(),
                    fileUrl = a.data('preview-url') ? a.data('preview-url') : a.prop('href');
                addPreviewBlockAndDragIcon($fileBlock, fileType, fileName, fileUrl, false);
            });

            // checkMaxFileNumber проверяет видимость элементов, а на момент выполнения
            // this.each() они скорее всего невидимы все
            setTimeout(function () {
                checkMaxFileNumber($uploadContainer);
            }, 10);

            // Обработка изменения input’а
            $uploadContainer.on('change', '.nc-upload-input', function () {
                if (multifile) {
                    processMultipleFileInputChange(this);
                } else {
                    processSingleFileInputChange(this);
                }
            });

            // Убираем onclick, добавленный в HTML для случая, когда этот скрипт не инициализирован
            $filesContainer.find('.nc-upload-file-remove').attr('onclick', '');

            // Обработка нажатия на «удалить файл»
            $filesContainer.on('click', '.nc-upload-file-remove', onFileRemoveClick);

            // Изменение поля со строкой с путём файла, загруженного в папку tmp (иконки из библиотеки, сгенерированные изображения)
            getServerPathInput($uploadContainer).change(function () {
                if (multifile) {
                    processMultipleFileInputChange(this);
                } else {
                    removeSingleFileOnChange($uploadContainer);
                    addFileBlock(this, 0);
                }
            });

            // Флаг, подсказывающий серверу, что этот скрипт был инициализирован
            if (multifile) {
                $uploadContainer.append(makeHiddenInput('multifile_js[' + $uploadContainer.data('fieldName') + ']', 1));
            }

            // Обработка тащи-и-бросай
            if (multifile) {
                let $draggedFile;

                // Начало перетаскивания
                $filesContainer.on('mousedown', '.nc-upload-file-drag-handle', function (e) {
                    e.preventDefault();
                    $filesContainer.addClass('nc--dragging');

                    $draggedFile = $(this).closest('.nc-upload-file').addClass('nc--dragged');

                    // Окончание перетаскивания
                    $(window).on('mouseup.nc-upload', function () {
                        $filesContainer.removeClass('nc--dragging');
                        $draggedFile.removeClass('nc--dragged');
                        $(window).off('mouseup.nc-upload');
                        $draggedFile = null;
                    });
                });

                // Перетаскивание
                $filesContainer.on('mousemove', '.nc-upload-file', function (e) {
                    let $hoveredFile = $(this),
                        insertBefore;

                    if (!$draggedFile || $hoveredFile.hasClass('nc--dragged')) {
                        return;
                    }

                    // определяем положение для элемента: до или после того, над которым мышь
                    if ($hoveredFile.css('float') === 'none') {  // тащат вертикально
                        let y = (e.pageY - $hoveredFile.offset().top),
                            height = $hoveredFile.height();
                        insertBefore = (y < (height / 2));
                    } else {  // тащат горизонтально
                        let x = (e.pageX - $hoveredFile.offset().left),
                            width = $hoveredFile.width();
                        insertBefore = (x < (width / 2));
                    }

                    if (insertBefore) {
                        $draggedFile.insertBefore($hoveredFile);
                    } else {
                        $draggedFile.insertAfter($hoveredFile);
                    }

                });
            }

            // Drag-n-drop для загрузки файлов извне
            if ($uploadContainer.find('.nc-upload-drag-hint').length) {
                let dragCounter = 0;

                $uploadContainer.on('dragenter', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dragCounter++;
                    if (dragCounter === 1) {
                        $uploadContainer.addClass('nc--drag-over');
                    }
                });

                $uploadContainer.on('dragleave', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dragCounter--;
                    if (dragCounter === 0) {
                        $uploadContainer.removeClass('nc--drag-over');
                    }
                });

                $uploadContainer.on('dragover', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                });

                $uploadContainer.on('drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dragCounter = 0;
                    $uploadContainer.removeClass('nc--drag-over');

                    let files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        if (!multifile) {
                            removeSingleFileOnChange($uploadContainer);
                        }

                        let maxFiles = multifile ? $uploadContainer.data('maxFiles') : 1;
                        let currentFileCount = countFiles($uploadContainer);

                        // Добавляем файлы в input
                        let $fileInput = $uploadContainer.find('.nc-upload-input').last();
                        let dataTransfer = new DataTransfer();

                        for (let i = 0; i < files.length; i++) {
                            // Для одиночного файла берем только первый
                            if (!multifile && i > 0) {
                                break;
                            }
                            // Для множественного учитываем лимит
                            if (multifile && maxFiles && currentFileCount + i >= maxFiles) {
                                break;
                            }

                            dataTransfer.items.add(files[i]);
                        }

                        $fileInput[0].files = dataTransfer.files;
                        $fileInput.trigger('change');
                    }
                });
            }

        });
    };

    $(document).on('apply-upload', function () {
        $('.nc-upload').upload();
    });

})(window.$nc || window.jQuery);
