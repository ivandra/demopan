/**
 * Cookie Consent — фронтовый рантайм.
 *
 * Активирует «инертные» элементы, помеченные на сервере атрибутом
 * data-nc-consent="<категория>": <script type="text/plain"> пересоздаёт,
 * а <img>/<iframe> с data-nc-src — восстанавливает src. Делает это, когда на
 * категорию выдано согласие. Парный серверный хелпер —
 * files/netcat/system/nc_consent.class.php (общий контракт).
 *
 * Публичный API (window.ncConsent):
 *   - granted(category) -> bool         выдано ли согласие на категорию
 *   - getAll()          -> string[]     список выданных категорий
 *   - set(categories[, opts])           сохранить выбор, активировать,
 *                                       бросить событие 'nc:consent-change'
 *
 * Необязательный конфиг от баннера: window.ncConsentConfig = { mode, days }
 *
 * Журнал выбора: если на сайте включено журналирование, сервер выводит
 * window.ncConsentLog = { url } (nc_consent::runtime_script_tag()), и set()
 * отправляет выбор в журнал вместе с ID согласия (cookie cookie_consent_id).
 * @see docs/proposals/cookie-consent-log.md
 *
 * @see docs/proposals/cookie-consent.md
 */
(function (window, document) {
    'use strict';

    var COOKIE_NAME = 'cookie_consent';
    var ID_COOKIE_NAME = 'cookie_consent_id';
    var NECESSARY = 'necessary';
    var ALL = ['necessary', 'functional', 'analytics', 'marketing'];
    var EVENT = 'nc:consent-change';
    var warnedNoBanner = false;

    function config() {
        return window.ncConsentConfig || {};
    }

    function indexOf(arr, v) {
        for (var i = 0; i < arr.length; i++) {
            if (arr[i] === v) { return i; }
        }
        return -1;
    }

    function readCookie(name) {
        var parts = document.cookie ? document.cookie.split(';') : [];
        for (var i = 0; i < parts.length; i++) {
            var c = parts[i];
            while (c.charAt(0) === ' ') { c = c.substring(1); }
            if (c.indexOf(name + '=') === 0) {
                return c.substring(name.length + 1);
            }
        }
        return null;
    }

    function writeCookie(name, value, days) {
        var expires = '';
        if (days) {
            var d = new Date();
            d.setTime(d.getTime() + days * 24 * 60 * 60 * 1000);
            expires = '; expires=' + d.toUTCString();
        }
        document.cookie = name + '=' + value + expires + '; path=/; SameSite=Lax';
    }

    // Категории, на которые есть согласие (легаси "accepted" = все).
    function grantedCategories() {
        var raw = readCookie(COOKIE_NAME);
        if (!raw) { return [NECESSARY]; }
        if (raw === 'accepted') { return ALL.slice(); }

        var list = [];
        var items = raw.split(',');
        for (var i = 0; i < items.length; i++) {
            var v = items[i].replace(/^\s+|\s+$/g, '');
            if (v && indexOf(ALL, v) !== -1 && indexOf(list, v) === -1) {
                list.push(v);
            }
        }
        if (indexOf(list, NECESSARY) === -1) { list.push(NECESSARY); }
        return list;
    }

    function isGranted(category) {
        if (category === NECESSARY) { return true; }
        // На странице нет окна согласия (баннер не выводится — нет ncConsentConfig)
        // → собирать согласие негде, поэтому не блокируем (fail-open). Конфиг
        // ставится inline-скриптом баннера ещё на парсинге, до активации.
        if (!window.ncConsentConfig) { return true; }
        return indexOf(grantedCategories(), category) !== -1;
    }

    function hasClass(node, cls) {
        return !!node && node.nodeType === 1 && node.className
            && (' ' + node.className + ' ').indexOf(' ' + cls + ' ') !== -1;
    }

    function removeClass(node, cls) {
        if (!hasClass(node, cls)) { return; }
        var parts = node.className.split(/\s+/);
        var kept = [];
        for (var i = 0; i < parts.length; i++) {
            if (parts[i] !== cls && parts[i] !== '') { kept.push(parts[i]); }
        }
        node.className = kept.join(' ');
    }

    // Плейсхолдер блокированного видео-виджета (см. nc_consent.class.php
    // ::autodetect_wrap()) больше не нужен после активации узла — убираем его
    // текст+кнопку. Сервер оборачивает <img>/<iframe> внутрь wrapper-дива
    // (родитель), а синтетический wrapper перед <script> — предшествующим
    // сиблингом (сам <script> ничего не рендерит). Контейнеры карт
    // (data-nc-consent-reuse="1") сюда не попадают — их позиция относительно
    // блокируемого <script> произвольна (между ними может быть любая вёрстка,
    // см. /kontakty/), поэтому они чистятся отдельно и независимо от порядка
    // активации — см. cleanupPlaceholderHosts().
    function removePlaceholderNear(node) {
        if (hasClass(node.parentNode, 'nc-consent-placeholder')) {
            var body = node.parentNode.querySelector ? node.parentNode.querySelector('.nc-consent-placeholder__body') : null;
            if (body && body.parentNode) { body.parentNode.removeChild(body); }
            return;
        }
        var sibling = node.previousElementSibling;
        if (!hasClass(sibling, 'nc-consent-placeholder')) { return; }
        if (sibling.getAttribute('data-nc-consent-reuse') === '1') { return; }
        sibling.parentNode.removeChild(sibling);
    }

    // Переиспользуемые контейнеры виджетов (напр. <div class="map-container">
    // Яндекс.Карт), временно превращённые в хост плейсхолдера — см.
    // nc_consent.class.php::mark_as_placeholder_host(). Не привязаны к
    // конкретному <script> ни позицией, ни DOM-связью, поэтому чистятся
    // отдельным проходом по всем таким узлам сразу, как только согласие на их
    // категорию получено: убираем текст+кнопку, возвращаем исходные классы —
    // дальше виджет сам рисует в этот див как обычно.
    function cleanupPlaceholderHosts() {
        var hosts = document.querySelectorAll('[data-nc-consent-reuse="1"]');
        for (var i = 0; i < hosts.length; i++) {
            var host = hosts[i];
            if (!isGranted(host.getAttribute('data-nc-consent-placeholder'))) { continue; }
            var body = host.querySelector('.nc-consent-placeholder__body');
            if (body && body.parentNode) { body.parentNode.removeChild(body); }
            removeClass(host, 'nc-consent-placeholder');
            host.removeAttribute('data-nc-consent-placeholder');
            host.removeAttribute('data-nc-consent-reuse');
        }
    }

    // Активация одного инертного скрипта: пересоздаём узел, чтобы он исполнился.
    function activateScript(oldScript) {
        removePlaceholderNear(oldScript);
        var s = document.createElement('script');
        var attrs = oldScript.attributes;
        for (var i = 0; i < attrs.length; i++) {
            var a = attrs[i];
            if (a.name === 'type' || a.name === 'data-nc-consent') { continue; }
            s.setAttribute(a.name, a.value);
        }
        if (!oldScript.src) {
            s.text = oldScript.text || oldScript.textContent || '';
        }
        oldScript.parentNode.replaceChild(s, oldScript);
    }

    // Активация одного инертного узла по согласию: <script> пересоздаём,
    // <img>/<iframe> — восстанавливаем src из data-nc-src.
    function activateNode(node) {
        if (node.tagName === 'SCRIPT') {
            activateScript(node);
            return;
        }
        var src = node.getAttribute('data-nc-src');
        if (src !== null) {
            removePlaceholderNear(node);
            node.removeAttribute('data-nc-consent');
            node.removeAttribute('data-nc-src');
            node.setAttribute('src', src);
        } else {
            node.removeAttribute('data-nc-consent');
        }
    }

    // Активировать все инертные узлы, на категории которых есть согласие.
    function activateGranted() {
        var nodes = document.querySelectorAll('[data-nc-consent]');
        // активация меняет DOM по ходу — копируем узлы в массив заранее.
        var list = [];
        for (var i = 0; i < nodes.length; i++) { list.push(nodes[i]); }

        // fail-open: есть элементы под согласие, но баннера на странице нет
        // (нет ncConsentConfig) — активируем без согласия и предупреждаем разработчика.
        if (list.length && !window.ncConsentConfig && !warnedNoBanner) {
            warnedNoBanner = true;
            if (window.console && console.warn) {
                console.warn('[nc-consent] На странице есть элементы под согласие, но окно cookie-согласия не выведено (нет window.ncConsentConfig). Скрипты активированы без согласия (fail-open). Добавьте виджет/компонент окна согласия в макет либо переключите режим сайта на «только информирование».');
            }
        }

        for (var j = 0; j < list.length; j++) {
            var node = list[j];
            if (node.parentNode && isGranted(node.getAttribute('data-nc-consent'))) {
                activateNode(node);
            }
        }

        cleanupPlaceholderHosts();
    }

    // UUID v4 для ID согласия.
    function uuid() {
        var c = window.crypto || window.msCrypto;
        if (c && c.randomUUID) { return c.randomUUID(); }
        var b = [];
        if (c && c.getRandomValues && window.Uint8Array) {
            // заполняет массив на месте; возвращаемое значение не используем
            // (msCrypto в IE11 возвращает не тот же массив)
            b = new Uint8Array(16);
            c.getRandomValues(b);
        } else {
            for (var i = 0; i < 16; i++) { b[i] = Math.floor(Math.random() * 256); }
        }
        b[6] = (b[6] & 0x0f) | 0x40;
        b[8] = (b[8] & 0x3f) | 0x80;
        var h = '';
        for (var j = 0; j < 16; j++) {
            h += (b[j] + 0x100).toString(16).substring(1);
            if (j === 3 || j === 5 || j === 7 || j === 9) { h += '-'; }
        }
        return h;
    }

    // Отправка выбора в журнал (если он включён на сайте). В режиме notice
    // выбора нет, без окна согласия на странице (нет ncConsentConfig) — тоже
    // нечего фиксировать: не пишем.
    function logChoice(categories, days) {
        var log = window.ncConsentLog;
        var cfg = window.ncConsentConfig;
        if (!log || !log.url || !cfg || cfg.mode === 'notice') { return; }

        // ID согласия: при изменении выбора переиспользуем — история браузера
        // связана; срок — как у самого выбора.
        var id = readCookie(ID_COOKIE_NAME);
        if (!id || !/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(id)) {
            id = uuid();
        }
        writeCookie(ID_COOKIE_NAME, id, days);

        var body = 'uid=' + encodeURIComponent(id)
            + '&categories=' + encodeURIComponent(categories.join(','))
            + '&mode=' + encodeURIComponent(cfg.mode || '')
            + '&text=' + encodeURIComponent(cfg.text || '')
            + '&sig=' + encodeURIComponent(cfg.textSig || '')
            + '&url=' + encodeURIComponent(window.location.pathname);
        var type = 'application/x-www-form-urlencoded';

        // sendBeacon переживает уход со страницы (клик по кнопке и сразу переход)
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(log.url, new Blob([body], { type: type }))) {
                return;
            }
        } catch (e) {}
        try {
            if (window.fetch) {
                window.fetch(log.url, {
                    method: 'POST', body: body, keepalive: true, credentials: 'same-origin',
                    headers: { 'Content-Type': type }
                });
                return;
            }
            var xhr = new XMLHttpRequest();
            xhr.open('POST', log.url, true);
            xhr.setRequestHeader('Content-Type', type);
            xhr.send(body);
        } catch (e2) {}
    }

    function dispatchChange(categories) {
        var ev;
        try {
            ev = new CustomEvent(EVENT, { detail: { categories: categories } });
        } catch (e) {
            ev = document.createEvent('CustomEvent');
            ev.initCustomEvent(EVENT, false, false, { categories: categories });
        }
        window.dispatchEvent(ev);
    }

    window.ncConsent = {
        granted: function (category) { return isGranted(category); },

        getAll: function () { return grantedCategories(); },

        // categories — массив выдаваемых категорий; opts.days — срок (0/undefined = сессия)
        set: function (categories, opts) {
            opts = opts || {};
            var cats = [NECESSARY];
            if (categories && categories.length) {
                for (var i = 0; i < categories.length; i++) {
                    if (indexOf(ALL, categories[i]) !== -1 && indexOf(cats, categories[i]) === -1) {
                        cats.push(categories[i]);
                    }
                }
            }
            var days = (typeof opts.days === 'number') ? opts.days : (config().days || 365);
            writeCookie(COOKIE_NAME, cats.join(','), days);
            // событие синхронно вызовет слушатель ниже, который активирует скрипты
            dispatchChange(cats);
            // журнал — после применения выбора: его ошибка не должна мешать согласию
            try {
                logChoice(cats, days);
            } catch (e) {}
            return cats;
        }
    };

    // Кнопка «Изменить настройки cookie» в плейсхолдере (см. nc_consent.class.php
    // ::autodetect_wrap()) — переоткрывает баннер с текущим сохранённым выбором.
    // Делегирование на document — плейсхолдеры рендерятся сервером, их может
    // быть много и появляться динамически (AJAX-контент).
    document.addEventListener('click', function (e) {
        var target = e.target;
        while (target && target !== document) {
            if (hasClass(target, 'nc-consent-placeholder__btn')) {
                if (window.ncConsentBanner && window.ncConsentBanner.reopen) {
                    window.ncConsentBanner.reopen();
                }
                return;
            }
            target = target.parentNode;
        }
    });

    function init() { activateGranted(); }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.addEventListener(EVENT, function () { activateGranted(); });

})(window, document);
