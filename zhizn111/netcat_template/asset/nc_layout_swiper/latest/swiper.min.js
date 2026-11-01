/**
 * Класс с функциональностью для миксина netcat_layout_swiper
 * (адаптер для библиотеки Swiper)
 *
 * Использование:
 *   var swiper = new nc_layout_swiper(params)
 *   swiper.destroy();
 *
 */
(function () {

    /**
     * Используется как конструктор
     * @param {Object} params параметры в том же виде, что и для инициализации миксина
     */
    window.nc_layout_swiper = function(params) {
        var list_element = params.list_element;

        if (!list_element) {
            return;
        }

        function for_each_slide(fn) {
            var slides = list_element.children;
            for (var i = 0; i < slides.length; i++) {
                if (slides[i].className !== 'resize-sensor') { // play along with ElementQueries’ ResizeSensor.js
                    fn(slides[i]);
                }
            }
        }

        // Swiper 11 корректно вычисляет размеры слайдов, поэтому fix_slides_width больше не нужна.
        // Оставляем пустую функцию для обратной совместимости с вызовами.
        function fix_slides_width() {
            // Swiper 11+ handles slide widths correctly
        }

        // Основные настройки слайдера
        var settings = params.settings,
            swiper_container = create_swiper_container(),
            slides_per_view_original_value = settings.slides_per_view, // string (numeric or 'auto')
            slides_per_view_number = parseInt(slides_per_view_original_value, 10) || 0,
            space_between = parseInt(settings.space_between, 10) || 0,
            swiper_options = {
                slidesPerView: slides_per_view_original_value,
                spaceBetween: space_between,
                speed: parseInt(settings.speed || 300, 10), // ✔: parseInt('0' || 300) === 0
                loopAdditionalSlides: slides_per_view_number || 4,
                loop: !!settings.loop,
                effect: settings.effect,
                centerInsufficientSlides: !settings.loop,
                on: {
                    resize: fix_slides_width,
                    // обновление положения тулбаров и т. п. в режиме редактирования
                    transitionEnd: function () {
                        window.$nc && $nc('.swiper-slide-active', list_element).trigger('mouseenter');
                    }
                }
            };

        // Добавление необходимой для swiper разметки и классов
        // <div class="swiper">                 <!-- добавляется динамически (Swiper 11+) -->
        //     <div class="swiper-wrapper">     <!-- = list_element -->
        //         <div class="swiper-slide">Slide 1</div>
        //     </div>
        //     <div class="swiper-pagination"></div>
        //     <div class="swiper-button-prev"></div>
        //     <div class="swiper-button-next"></div>
        //     <div class="swiper-scrollbar"></div>
        // </div>
        // Подсчёт количества слайдов
        var slide_count = 0;
        for_each_slide(function() { slide_count++; });

        // Swiper 11: для loop режима нужно минимум 3 слайда (или slidesPerView * 2)
        var min_slides_for_loop = Math.max(3, (slides_per_view_number || 1) * 2);
        if (swiper_options.loop && slide_count < min_slides_for_loop) {
            swiper_options.loop = false;
            swiper_options.rewind = true; // альтернатива loop для малого количества слайдов
        }

        list_element.className += ' swiper-wrapper' +
            // также добавляем собственный класс swiper-nc-auto-slides-per-view,
            // без этих дополнительных стилей не получается получить нужный эффект (Swiper 4.4.6)
            (slides_per_view_original_value === 'auto' ? ' swiper-nc-auto-slides-per-view' : '');
        list_element.parentNode.insertBefore(swiper_container, list_element);
        swiper_container.appendChild(list_element);

        for_each_slide(function (slide) { slide.className += ' swiper-slide'; });

        if (settings.show_arrows) {
            // Если стрелки снаружи — создаём их как сиблинги .swiper, чтобы overflow:hidden не скрывал их
            var arrows_parent = settings.arrows_position === 'outside'
                ? swiper_container.parentNode
                : swiper_container;
            swiper_options.navigation = {
                prevEl: create_div('button-prev', arrows_parent),
                nextEl: create_div('button-next', arrows_parent)
            };
        }

        if (settings.pagination_type) {
            swiper_options.pagination = {
                el: create_div('pagination', swiper_container),
                type: settings.pagination_type,
                clickable: true
            };
        }

        if (settings.show_scrollbar) {
            swiper_options.scrollbar = {
                el: create_div('scrollbar', swiper_container),
                draggable: true,
                hide: settings.show_scrollbar !== 'always'
            };
        }

        if (settings.autoplay) {
            swiper_options.autoplay = {delay: settings.autoplay_delay * 1000};
        }

        // Настройки для эффектов
        if (settings.effect === 'fade') {
            swiper_options.fadeEffect = {
                crossFade: true
            };
        }

        if (settings.effect === 'cube') {
            swiper_options.cubeEffect = {
                shadow: true,
                slideShadows: true,
                shadowOffset: 20,
                shadowScale: 0.94
            };
            swiper_options.grabCursor = true;
        }

        if (settings.effect === 'coverflow') {
            swiper_options.coverflowEffect = {
                rotate: 50,
                stretch: 0,
                depth: 100,
                modifier: 1,
                slideShadows: true
            };
            swiper_options.grabCursor = true;
        }

        if (settings.effect === 'flip') {
            swiper_options.flipEffect = {
                slideShadows: true,
                limitRotation: true
            };
            swiper_options.grabCursor = true;
        }

        if (settings.effect === 'cards') {
            swiper_options.cardsEffect = {
                slideShadows: true,
                perSlideRotate: 2,
                perSlideOffset: 8
            };
            swiper_options.grabCursor = true;
        }

        fix_slides_width();

        // Инициализация библиотеки Swiper
        var swiper = new Swiper(swiper_container, swiper_options);

        // ------------------------------------------------------------------------------------------------------------

        // Функция деинициализации
        this.destroy = function() {
            var list_element = params.list_element,
                swiper_container = list_element.parentNode;

            if (!list_element || !swiper_container.swiper) {
                return;
            }

            var swiper_container_parent = swiper_container.parentNode,
                slides = list_element.children;

            // cleanStyles=false: Swiper полностью очищает атрибут style, чего хотелось бы на всякий случай избежать
            swiper_container.swiper.destroy(true, false);
            // так как destroy() вызван с cleanStyles=false, Swiper может оставить transform: translate3d,
            // из-за чего список будет невидим
            list_element.style.transform = '';

            remove_swiper_classes(list_element);
            swiper_container_parent.insertBefore(list_element, swiper_container);
            swiper_container_parent.removeChild(swiper_container); // IE: нет метода Element.remove()

            for (var i = 0; i < slides.length; i++) {
                remove_swiper_classes(slides[i]);
                var style = slides[i].style;
                style.width = style.maxWidth = style.marginRight = null;
            }
        };

        // ------------------------------------------------------------------------------------------------------------

        // ▽▽▽
        // В режиме редактирования выпадающие меню могут быть частично скрыты из-за { overflow: hidden }.
        // Если UI будет переработан так, что элементы управления не будут располагаться внутри блока,
        // необходимо будет убрать этот фрагмент:
        if (window.$nc && $nc('body').is('.nc--mode-edit')) {
            function get_bottom(el) {
                return el.offset().top + el.height();
            }
            function has_overflow(dropdown_menu) {
                // Поиск по обоим классам для обратной совместимости
                var swiper_container = dropdown_menu.closest('.swiper, .swiper-container');
                return swiper_container.length && get_bottom(dropdown_menu) > get_bottom(swiper_container);
            }
            function on_click() {
                var menu = $nc(this).children('ul');
                setTimeout(function() {
                    has_overflow(menu) && $nc(swiper_container).css('overflow', 'visible');
                }, 5);
            }
            function on_leave() {
                $nc(swiper_container).css('overflow', 'hidden');
            }

            if (/\[m/.test(params.block_selector)) { // селектор с уточнениями для брейкпоинтов?
                $nc('body')
                    .on('click', '$selector .nc-object-toolbar .nc--dropdown', on_click)
                    .on('mouseleave', '$selector .nc-object-toolbar', on_leave);
            } else {
                $nc(params.block_selector + ' .nc-object-toolbar').mouseleave(on_leave)
                    .find('.nc--dropdown').click(on_click);
            }
        }
        // △△△  конец фрагмента, который нужно будет удалить

    };

    // ------------------------------------------------------------------------------------------------------------
    // Вспомогательные функции
    // ------------------------------------------------------------------------------------------------------------

    // создаёт основной контейнер swiper (Swiper 11+: класс 'swiper' вместо 'swiper-container')
    // Для обратной совместимости добавляем оба класса
    function create_swiper_container() {
        var div = document.createElement('div');
        div.className = 'swiper swiper-container';
        return div;
    }

    // создаёт div с указанным 'swiper'-классом
    function create_div(class_name_suffix, parent) {
        var div = document.createElement('div');
        div.className = 'swiper-' + class_name_suffix;
        if (parent) {
            parent.appendChild(div);
        }
        return div;
    }

    // Удаляет из строки названия CSS-классов с префиксом 'swiper-'
    function remove_swiper_classes(element) {
        element.className = element.className.replace(/\bswiper-[\w-]+\s?\b/g, '').trim();
    }


})();