    (function (params) {
    var element = params.block_element;
    var settings = params.settings;

    // Типы эффектов, которые требуют отслеживания появления в viewport
    var viewportEffects = ['fadein', 'slide', 'zoom', 'rotate', 'bounce', 'stagger'];

    // Преобразуем settings в массив, если это объект с числовыми ключами
    var settingsArray = settings;
    if (settings && typeof settings === 'object' && !Array.isArray(settings)) {
        settingsArray = Object.keys(settings).map(function(key) {
            return settings[key];
        });
    }

    // Проверяем, есть ли viewport-эффекты
    if (!settingsArray || !settingsArray.length) {
        return;
    }

    var hasViewportEffects = settingsArray.some(function(item) {
        return item && viewportEffects.indexOf(item.type) !== -1;
    });

    if (!hasViewportEffects) {
        return;
    }

    // Intersection Observer для отслеживания появления элемента
    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    // Добавляем класс для запуска анимации
                    entry.target.classList.add('tpl-mixin-effect-visible');
                    // Отключаем наблюдение после первого появления
                    observer.unobserve(entry.target);
                }
            });
        }, {
            root: null,
            rootMargin: '0px',
            threshold: 0
        });

        observer.observe(element);
    } else {
        // Fallback для старых браузеров - сразу показываем
        element.classList.add('tpl-mixin-effect-visible');
    }
});
