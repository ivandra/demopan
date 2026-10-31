/**
 * Cookie Consent — баннер.
 *
 * Рисует нижнюю плашку cookie-согласия и связывает кнопки с window.ncConsent.
 * Режим и тексты приходят из window.ncConsentConfig:
 *   { mode: 'notice' | 'accept-all' | 'categories',
 *     text: '<html>', policyUrl: '<url>' | null, labels: { ... } }
 *
 * Требует nc-consent.js (window.ncConsent). Парный серверный хелпер —
 * nc_consent.class.php. @see docs/proposals/cookie-consent.md
 */
(function (window, document) {
    'use strict';

    if (!window.ncConsent) { return; }              // нет рантайма — нечего активировать
    if (window.ncConsentBannerLoaded) { return; }   // защита от дублей
    window.ncConsentBannerLoaded = true;

    var cfg = window.ncConsentConfig || {};
    var mode = cfg.mode || 'notice';

    var MANAGED = ['functional', 'analytics', 'marketing'];   // управляемые (необязательные) категории

    function indexOf(arr, v) {
        for (var i = 0; i < arr.length; i++) {
            if (arr[i] === v) { return i; }
        }
        return -1;
    }

    var L = {
        accept:        'Принять',
        acceptAll:     'Принять все',
        reject:        'Отклонить',
        rejectAll:     'Отклонить все',
        settings:      'Настроить',
        save:          'Принять выбранное',
        more:          'Подробнее',
        necessary:     'Необходимые',
        functional:    'Функциональные',
        analytics:     'Аналитические',
        marketing:     'Маркетинговые',
        necessaryHint: 'всегда активны — нужны для работы сайта'
    };
    if (cfg.labels) {
        for (var k in cfg.labels) {
            if (cfg.labels.hasOwnProperty(k)) { L[k] = cfg.labels[k]; }
        }
    }

    // Выбор уже сделан — баннер не показываем (рантайм уже активировал нужное).
    function hasChoice() {
        return (document.cookie || '').indexOf('cookie_consent=') !== -1;
    }

    function el(tag, cls) {
        var n = document.createElement(tag);
        if (cls) { n.className = cls; }
        return n;
    }

    function button(label, primary) {
        // Классы темы (tpl-button*) — чтобы на стандартных шаблонах кнопки
        // выглядели как родные кнопки сайта; наши nc-consent__btn* — фолбэк-скин
        // (нулевая специфичность через :where, тема его перебивает).
        var cls = primary
            ? 'tpl-button tpl-button-primary nc-consent__btn nc-consent__btn--primary'
            : 'tpl-button tpl-button-secondary nc-consent__btn';
        var b = el('button', cls);
        b.type = 'button';
        b.textContent = label;
        return b;
    }

    function categoryRow(key, checked, disabled) {
        var row = el('label', 'nc-consent__cat');
        var input = document.createElement('input');
        input.type = 'checkbox';
        if (checked) { input.checked = true; }
        if (disabled) { input.disabled = true; }
        row.appendChild(input);
        var name = el('span', 'nc-consent__cat-name');
        name.textContent = L[key] || key;
        row.appendChild(name);
        if (key === 'necessary') {
            var hint = el('span', 'nc-consent__cat-hint');
            hint.textContent = '— ' + L.necessaryHint;
            row.appendChild(hint);
        }
        return row;
    }

    var currentBar = null;

    function finish(bar, categories) {
        categories = categories || [];
        // Срок хранения: согласие (выдана хотя бы одна необязательная категория)
        // хранится дольше, полный отказ — короче (настраивается в настройках сайта).
        var granted = false;
        for (var i = 0; i < categories.length; i++) {
            if (MANAGED.indexOf(categories[i]) !== -1) { granted = true; break; }
        }
        var days = granted
            ? (typeof cfg.daysAccept === 'number' ? cfg.daysAccept : 180)
            : (typeof cfg.daysReject === 'number' ? cfg.daysReject : 30);
        window.ncConsent.set(categories, { days: days });
        if (bar && bar.parentNode) { bar.parentNode.removeChild(bar); }
        if (bar === currentBar) { currentBar = null; }
    }

    // opts.prefill — категории, отмеченные в панели изначально (переоткрытие
    // с текущим выбором); opts.expanded — сразу показать панель категорий,
    // не пряча за кнопкой «Настроить» (пользователь уже явно пришёл менять выбор).
    function buildBar(opts) {
        opts = opts || {};
        var prefill = opts.prefill || [];
        var bar = el('div', 'nc-consent');
        var bar = el('div', 'nc-consent');
        var inner = el('div', 'nc-consent__inner');

        var msg = el('div', 'nc-consent__text');
        msg.innerHTML = cfg.text || '';
        if (cfg.policyUrl) {
            msg.innerHTML += ' <a class="nc-consent__more" href="' + cfg.policyUrl + '">' + L.more + '</a>';
        }
        inner.appendChild(msg);

        var actions = el('div', 'nc-consent__actions');

        if (mode === 'notice') {
            var bAccept = button(L.accept, true);
            bAccept.onclick = function () { finish(bar, MANAGED); };
            actions.appendChild(bAccept);

        } else if (mode === 'accept-all') {
            var bAll = button(L.accept, true);
            bAll.onclick = function () { finish(bar, MANAGED); };
            var bNone = button(L.reject, false);
            bNone.onclick = function () { finish(bar, []); };
            actions.appendChild(bAll);
            actions.appendChild(bNone);

        } else { // categories
            var panel = el('div', 'nc-consent__panel');
            var expanded = !!opts.expanded;
            panel.style.display = expanded ? '' : 'none';
            panel.appendChild(categoryRow('necessary', true, true));
            // Отмечена только обязательная (неснимаемая) категория necessary —
            // если не задан prefill. Управляемые категории по умолчанию сняты,
            // посетитель сам включает нужные (соответствует требованиям ЕС к
            // pre-ticked, см. docs/proposals/cookie-consent.md). При
            // переоткрытии (prefill) отражаем текущий сохранённый выбор.
            var toggles = {};
            for (var i = 0; i < MANAGED.length; i++) {
                var checked = indexOf(prefill, MANAGED[i]) !== -1;
                var row = categoryRow(MANAGED[i], checked, false);
                toggles[MANAGED[i]] = row.getElementsByTagName('input')[0];
                panel.appendChild(row);
            }

            var bAcceptAll = button(L.acceptAll, true);
            bAcceptAll.onclick = function () { finish(bar, MANAGED); };
            var bRejectAll = button(L.rejectAll, false);
            bRejectAll.onclick = function () { finish(bar, []); };
            var bSettings = button(L.settings, false);
            var bSave = button(L.save, true);
            bSettings.style.display = expanded ? 'none' : '';
            bSave.style.display = expanded ? '' : 'none';

            bSettings.onclick = function () {
                panel.style.display = '';
                bSettings.style.display = 'none';
                bSave.style.display = '';
            };
            bSave.onclick = function () {
                var chosen = [];
                for (var j = 0; j < MANAGED.length; j++) {
                    if (toggles[MANAGED[j]].checked) { chosen.push(MANAGED[j]); }
                }
                finish(bar, chosen);
            };

            actions.appendChild(bAcceptAll);
            actions.appendChild(bRejectAll);
            actions.appendChild(bSettings);
            actions.appendChild(bSave);
            inner.appendChild(panel);
        }

        inner.appendChild(actions);
        bar.appendChild(inner);
        return bar;
    }

    // Показать плашку. Если уже открыта — не дублируем.
    function show(opts) {
        if (currentBar && currentBar.parentNode) { return; }
        currentBar = buildBar(opts);
        document.body.appendChild(currentBar);
    }

    function init() {
        if (hasChoice()) { return; }
        show();
    }

    // Публичный API — переоткрыть окно согласия с текущим сохранённым выбором
    // (нужно плейсхолдерам блокированных карт/видео — см. nc_consent.class.php
    // ::autodetect_wrap() — и может использоваться постоянной ссылкой
    // «Настройки cookie» на сайте).
    window.ncConsentBanner = {
        reopen: function () {
            show({ prefill: window.ncConsent.getAll(), expanded: true });
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})(window, document);
