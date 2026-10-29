document.addEventListener('DOMContentLoaded', () => {
    const select = (selector, root = document) => root.querySelector(selector);
    const selectAll = (selector, root = document) => [...root.querySelectorAll(selector)];

    selectAll('.specialist-page__actions > .quiz__btn, .mobile-menu__contacts > button').forEach(button => {
        button.addEventListener('click', () => location.href = '/kontakty/');
    });
    selectAll('.team__button').forEach(button => {
        button.addEventListener('click', () => location.href = '/o-nas/spetsialisty/');
    });

    selectAll('.mobile-menu li > a').forEach(link => {
        const children = link.nextElementSibling;
        if (!children || children.tagName !== 'UL') return;
        link.addEventListener('click', event => {
            event.preventDefault();
            const expanded = link.getAttribute('aria-expanded') === 'true';
            link.setAttribute('aria-expanded', String(!expanded));
            children.style.display = expanded ? 'none' : 'block';
        });
    });

    selectAll('[data-specialist-filter]').forEach(button => {
        button.addEventListener('click', () => {
            const category = button.dataset.specialistFilter;
            selectAll('[data-specialist-filter]').forEach(item =>
                item.classList.toggle('specialists-block__filter--active', item === button));
            selectAll('[data-specialist-category]').forEach(card => {
                card.style.display = category === 'all' || card.dataset.specialistCategory.split(/\s+/).includes(category) ? '' : 'none';
            });
        });
    });

    selectAll('.gallery .specialists-block__filter').forEach(button => {
        button.addEventListener('click', event => {
            event.stopPropagation();
            const category = button.dataset.filter || 'all';
            selectAll('.gallery .specialists-block__filter').forEach(item =>
                item.classList.toggle('specialists-block__filter--active', item === button));
            select('.gallery__list--all').style.display = '';
            selectAll('[data-photo-category]').forEach(card => {
                card.style.display = category === 'all' || card.dataset.photoCategory === category ? '' : 'none';
            });
        });
    });

    selectAll('[data-article-category]').forEach(button => {
        button.addEventListener('click', () => {
            const category = button.dataset.articleCategory;
            location.href = '/blog/' + (category ? '?category=' + encodeURIComponent(category) : '');
        });
    });

    if (select('.calc')) {
        const settings = window.projectCalculator;
        const update = () => {
            const days = $('#slider-range-max').slider('value');
            const rate = $('#slider-range-max2').slider('value');
            $('#amount').val(days + ' суток');
            $('#amount2').val(rate.toLocaleString('ru-RU') + ' ₽');
            $('.calc__result-value').text((days * rate).toLocaleString('ru-RU') + ' ₽');
            $('.calc__result-formula').text(days + ' суток × ' + rate.toLocaleString('ru-RU') + ' ₽');
        };
        $('#slider-range-max').slider({range: 'max', min: settings.days_min, max: settings.days_max, value: settings.days, slide: () => setTimeout(update)});
        $('#slider-range-max2').slider({range: 'max', min: settings.rate_min, max: settings.rate_max, value: settings.rate, step: 100, slide: () => setTimeout(update)});
        update();
    }

    selectAll('form[data-zhp-form]').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            if (form.dataset.busy || !form.reportValidity()) return;
            form.dataset.busy = '1';
            const status = select('[data-form-status]', form);
            const buttons = selectAll('[type=submit]', form);
            buttons.forEach(button => button.disabled = true);
            status.textContent = 'Отправляем…';
            try {
                const data = new FormData(form);
                const url = new URL('/netcat/add.php', location.origin);
                for (const key of ['catalogue', 'sub', 'cc']) url.searchParams.set(key, data.get(key));
                url.searchParams.set('isNaked', '1');
                const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store'});
                const document = new DOMParser().parseFromString(await response.text(), 'text/html');
                for (const input of selectAll('input[type=hidden]', document)) {
                    if (/token/i.test(input.name) || input.name === 'zhp_nonce') data.set(input.name, input.value);
                }
                const saved = await fetch('/netcat/add.php', {method: 'POST', credentials: 'same-origin', body: data});
                const result = new DOMParser().parseFromString(await saved.text(), 'text/html');
                const success = select('[data-zhp-success]', result);
                if (!saved.ok || !success) throw new Error(select('[data-zhp-error]', result)?.textContent || 'Не удалось отправить заявку.');
                status.textContent = success.textContent;
                form.reset();
                select('[name=f_request_key]', form).value = [...crypto.getRandomValues(new Uint8Array(16))].map(value => value.toString(16).padStart(2, '0')).join('');
            } catch (error) {
                status.textContent = error.message;
            } finally {
                delete form.dataset.busy;
                buttons.forEach(button => button.disabled = false);
            }
        });
    });
});
