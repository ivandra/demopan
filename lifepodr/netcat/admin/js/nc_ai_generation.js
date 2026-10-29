(function($nc, window) {
    'use strict';

    if (!window || !$nc || typeof nc === 'undefined') {
        return;
    }

    var DEFAULT_MAX_IMAGES = 4;

    var i18nDefaults = {
        prompt_required: 'Describe the image before generating.',
        select_image: 'Select an image first.',
        general_error: 'Generation failed. Please try again.',
        remove_button: 'Remove generated image'
    };

    function i18nGet(key) {
        var dict = window.nc_ai_image_generation_i18n || {};
        return dict[key] || i18nDefaults[key] || '';
    }

    function AiImageGeneration() {
    }

    AiImageGeneration.prototype.init = function() {
        var self = this;

        $nc(document).on('click', '.nc-ai-upload-generate-button', function(event) {
            event.preventDefault();
            var upload = $nc(this).closest('.nc-upload');

            if (!upload.length) {
                return;
            }

            var ncAiImageField = upload.data('aiImageField');
            var canUseAi = String(ncAiImageField) === '1';

            if (!canUseAi) {
                return;
            }

            self.openDialog(upload);
        });
    };

    AiImageGeneration.prototype.openDialog = function(upload) {
        var uploadId = upload.attr('id');

        if (!uploadId) {
            return;
        }

        var maxImages = parseInt(upload.data('aiImageMax'), 10) || DEFAULT_MAX_IMAGES;
        var url = NETCAT_PATH +
            'action.php?ctrl=ai_image_generation&action=show_dialog' +
            '&upload_id=' + encodeURIComponent(uploadId) +
            '&max_images=' + encodeURIComponent(maxImages);

        var self = this;
        var dialog = nc.load_dialog(url);
        dialog.set_option('ai_image_upload_target', uploadId);
        dialog.set_option('ai_image_max', maxImages);
        dialog.set_option('on_show', function() {
            self.initDialog(dialog, {
                upload_id: uploadId,
                max_images: maxImages,
                container: dialog.dialog_container
            });
        });
    };

    AiImageGeneration.prototype.applyImage = function(upload, fileInfo) {
        if (!upload || !upload.length) {
            return;
        }

        upload.find('.nc-upload-file-server-path').last().val(fileInfo.path).trigger('change');
    };

    AiImageGeneration.prototype.initDialog = function(dialog, options) {
        var self = this;

        var container = null;
        if (options && options.container) {
            container = options.container;
        } else if (dialog) {
            if (dialog.dialog_container && dialog.dialog_container.length) {
                container = dialog.dialog_container;
            }
        }

        if (!container || !container.length) {
            container = $nc('.nc-ai-image-dialog').last();
        }

        if (container && container.length && !container.is('.nc-ai-image-dialog')) {
            container = container.find('.nc-ai-image-dialog').last();
        }

        if (!container.length) {
            return;
        }

        if (container.data('aiImageInit')) {
            return;
        }
        container.data('aiImageInit', true);

        if (dialog && typeof dialog.set_option === 'function') {
            dialog.set_option('confirm_close', false);
        }

        var state = {
            dialog: dialog,
            uploadId: options.upload_id || dialog.get_option('ai_image_upload_target'),
            maxImages: options.max_images || dialog.get_option('ai_image_max') || DEFAULT_MAX_IMAGES,
            elements: {
                prompt: container.find('.nc-ai-image-prompt'),
                promptError: container.find('.nc-ai-image-prompt-error'),
                count: container.find('.nc-ai-image-count'),
                generate: container.find('.nc-ai-image-generate-button'),
                results: container.find('[data-role=results]'),
                preview: container.find('[data-role=preview]')
            },
            footer: {
                use: dialog.get_part('footer').find('[data-action=use]'),
                cancel: dialog.get_part('footer').find('[data-action=close]')
            },
            images: [],
            selectedIndex: null
        };

        state.elements.prompt.on('input', function() {
            self.clearPromptError(state);
        });

        state.elements.generate
            .off('.ncAiImage')
            .on('click.ncAiImage', function(event) {
                event.preventDefault();
                self.generateImages(state);
            });

        state.footer.use
            .off('.ncAiImage')
            .on('click.ncAiImage', function(event) {
                event.preventDefault();
                self.useSelectedImage(state);
            });

        state.footer.cancel
            .off('.ncAiImage')
            .on('click.ncAiImage', function(event) {
                event.preventDefault();
                dialog.destroy();
            });

        state.elements.results
            .off('.ncAiImage')
            .on('click.ncAiImage', '.nc-ai-image-card', function() {
                var index = $nc(this).data('imageIndex');
                self.selectImage(state, index);
            });

        this.setUseButtonState(state, false);
        state.dialog.remove_error();
    };

    AiImageGeneration.prototype.generateImages = function(state) {
        var self = this;
        var prompt = state.elements.prompt.val().trim();

        this.clearPromptError(state);

        if (!prompt) {
            this.showPromptError(state, i18nGet('prompt_required'));
            return;
        }

        var imagesCount = parseInt(state.elements.count.val(), 10) || 1;
        imagesCount = Math.min(state.maxImages, Math.max(1, imagesCount));

        this.toggleGenerating(state, true);
        state.dialog.remove_error();
        this.setUseButtonState(state, false);
        state.selectedIndex = null;

        $nc.ajax({
            type: 'POST',
            url: NETCAT_PATH + 'action.php?ctrl=ai_image_generation&action=generate',
            dataType: 'json',
            data: {
                prompt: prompt,
                images_count: imagesCount,
                max_images: state.maxImages,
                nc_token: (typeof nc_token !== 'undefined') ? nc_token : ''
            }
        }).done(function(response) {
            var data = response && response.result ? response.result : null;

            if (!data || !data.images || !data.images.length) {
                var message = (response && response.message) ? response.message : i18nGet('general_error');
                state.dialog.show_error(message);

                // Показываем панели, но только с сообщением об ошибке
                var panels = state.elements.results.closest('.nc-ai-image-panels');
                if (panels.length) {
                    panels.show();
                }

                state.elements.results.html(
                    '<div class="nc-ai-image-results-placeholder">' + message + '</div>'
                );

                // Скрываем панель preview при ошибке
                state.elements.preview.hide();
                return;
            }

            state.images = data.images;
            self.renderResultCards(state);
            self.setUseButtonState(state, false);
            nc_ai_update_balance_in_footer('image_cost_message');
        }).fail(function(jqXHR) {
            var message = i18nGet('general_error');
            if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
                message = jqXHR.responseJSON.message;
            } else if (jqXHR && jqXHR.responseText) {
                try {
                    var parsed = JSON.parse(jqXHR.responseText);
                    if (parsed && parsed.message) {
                        message = parsed.message;
                    }
                } catch (e) {
                    // ignore
                }
            }
            state.dialog.show_error(message);

            // Показываем панели, но только с сообщением об ошибке
            var panels = state.elements.results.closest('.nc-ai-image-panels');
            if (panels.length) {
                panels.show();
            }

            state.elements.results.html(
                '<div class="nc-ai-image-results-placeholder">' + message + '</div>'
            );

            // Скрываем панель preview при ошибке
            state.elements.preview.hide();
        }).always(function() {
            self.toggleGenerating(state, false);
        });
    };

    AiImageGeneration.prototype.renderResultCards = function(state) {
        var list = state.elements.results;
        list.empty();

        for (var i = 0; i < state.images.length; i++) {
            var image = state.images[i];
            var card = $nc('<div class="nc-ai-image-card" />').attr('data-image-index', i);

            var preview = $nc('<div class="nc-ai-image-card-preview" />')
                .css('background-image', 'url(' + encodeURI(image.url) + ')');

            var info = $nc('<div class="nc-ai-image-card-info" />')
                .append($nc('<div />').text(image.description || image.prompt || ''));

            card.append(preview).append(info);
            list.append(card);
        }

        // Показываем панели с результатами после успешной генерации
        var panels = list.closest('.nc-ai-image-panels');
        if (panels.length) {
            panels.show();
        }

        // Показываем панель preview при успешной генерации
        if (state.elements.preview) {
            state.elements.preview.show();
        }
    };

    AiImageGeneration.prototype.selectImage = function(state, index) {
        state.selectedIndex = index;

        var cards = state.elements.results.find('.nc-ai-image-card');
        cards.removeClass('nc--selected');
        cards.filter('[data-image-index=' + index + ']').addClass('nc--selected');

        var image = state.images[index];
        var preview = state.elements.preview;
        preview.empty();

        if (image && image.url) {
            var img = $nc('<img />', { src: image.url, alt: image.description || '' });
            preview.append(img);
        } else {
            preview.append(
                $nc('<div class="nc-ai-image-preview-placeholder" />').text(i18nGet('general_error'))
            );
        }

        this.setUseButtonState(state, typeof index === 'number');
    };

    AiImageGeneration.prototype.useSelectedImage = function(state) {
        if (state.selectedIndex === null || typeof state.selectedIndex === 'undefined') {
            state.dialog.show_error(i18nGet('select_image'));
            return;
        }

        var image = state.images[state.selectedIndex];
        if (!image || !image.url) {
            state.dialog.show_error(i18nGet('general_error'));
            return;
        }

        var upload = this.getUploadById(state.uploadId);
        if (!upload.length) {
            state.dialog.show_error(i18nGet('general_error'));
            return;
        }

        var self = this;
        this.setUseButtonState(state, false);

        $nc.ajax({
            type: 'POST',
            url: NETCAT_PATH + 'action.php?ctrl=ai_image_generation&action=download',
            dataType: 'json',
            data: {
                image_url: image.url,
                nc_token: (typeof nc_token !== 'undefined') ? nc_token : ''
            }
        }).done(function(response) {
            if (response && response.result && response.result.path) {
                window.nc_ai_image_generation.applyImage(upload, response.result);
                state.dialog.destroy();
            } else {
                state.dialog.show_error(i18nGet('general_error'));
            }
        }).fail(function(jqXHR) {
            var message = i18nGet('general_error');
            if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.message) {
                message = jqXHR.responseJSON.message;
            }
            state.dialog.show_error(message);
        }).always(function() {
            self.setUseButtonState(state, true);
        });
    };

    AiImageGeneration.prototype.getUploadById = function(uploadId) {
        return uploadId ? $nc('#' + uploadId) : $nc();
    };

    AiImageGeneration.prototype.toggleGenerating = function(state, isGenerating) {
        state.elements.generate
            .toggleClass('nc--is-generating', isGenerating)
            .prop('disabled', isGenerating);
    };

    AiImageGeneration.prototype.setUseButtonState = function(state, enabled) {
        if (state && state.footer && state.footer.use) {
            state.footer.use.prop('disabled', !enabled);
        }
    };

    AiImageGeneration.prototype.showPromptError = function(state, message) {
        if (!state || !state.elements) {
            return;
        }
        state.elements.prompt.addClass('nc--error');
        state.elements.promptError.text(message || '').show();
    };

    AiImageGeneration.prototype.clearPromptError = function(state) {
        if (!state || !state.elements) {
            return;
        }
        state.elements.prompt.removeClass('nc--error');
        state.elements.promptError.text('').hide();
    };

    var instance = new AiImageGeneration();
    instance.init();

    window.nc_ai_image_generation = {
        applyImage: function(target, fileInfo) {
            var upload = target && target.jquery ? target : $nc(target);
            instance.applyImage(upload, fileInfo || {});
        },
        openDialog: function(target, options) {
            if (!target) {
                return;
            }

            var upload = target.jquery ? target : $nc(target);
            if (!upload.length) {
                return;
            }

            if (options && options.max_images) {
                upload.data('aiImageMax', options.max_images);
            }

            instance.openDialog(upload);
        }
    };

    // Обновление баланса в футере диалога

    function initBalanceRefresh(dialog, priceMessageKey) {
        dialog.find(".nc-ai-balance-refresh-button").on("click", async function () {
            try {
                $nc(this).addClass("nc--rotating");
                await window.nc_ai_update_balance_in_footer(priceMessageKey);
            } catch (e) {
                console.error(e);
            } finally {
                $nc(this).removeClass("nc--rotating");
            }
        });
    }

    window.nc_ai_update_balance_in_footer = async function(priceMessageKey) {
        const { result } = await nc_http_get((window.NETCAT_PATH || '/netcat/') + "action.php", {
            ctrl: "ai_content_generation",
            action: "get_balance_info"
        });

        if (result && result.message) {
            var balance = result.message + ' ' +
                '<img src="' + (window.ADMIN_PATH || '/netcat/admin/') + 'skins/default/img/refresh.svg" class="nc-ai-balance-refresh-button">' +
                (result[priceMessageKey] ? ', ' + result[priceMessageKey] : '') +
                (result.link ? '. ' + result.link : '');

            var dialog = nc.ui.modal_dialog.get_current_dialog();
            dialog.find(".nc-ai-dialog-tokens-balance-text").html(balance);
            initBalanceRefresh(dialog, priceMessageKey);
        }
    };

})(window.$nc || window.jQuery, window);
