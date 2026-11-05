(function () {
    'use strict';
    window.lifeProjectUI = true;
    function ready() {
        var banner = document.querySelector('[data-project-cookie]');
        var saved = false;
        try { saved = localStorage.getItem('life_cookie_consent') === 'v1'; } catch (error) {}
        if (!saved) saved = document.cookie.split(';').some(function (part) { return part.trim() === 'life_cookie_consent=v1'; });
        if (banner) {
            banner.hidden = saved;
            banner.querySelector('button').addEventListener('click', function () {
                try { localStorage.setItem('life_cookie_consent', 'v1'); } catch (error) {}
                document.cookie = 'life_cookie_consent=v1; Max-Age=31536000; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
                banner.hidden = true;
            });
        }
        document.querySelectorAll('.team__slider').forEach(function (slider) {
            if (!window.Swiper || slider.swiper) return;
            new Swiper(slider, {
                slidesPerView: 1.15, spaceBetween: 18, watchOverflow: true,
                breakpoints: { 600: { slidesPerView: 2 }, 1000: { slidesPerView: 3 } },
                navigation: { prevEl: slider.querySelector('[data-team-prev]'), nextEl: slider.querySelector('[data-team-next]') },
                a11y: { prevSlideMessage: 'Предыдущие специалисты', nextSlideMessage: 'Следующие специалисты' }
            });
        });
        function layoutGallery(list) {
            var index = 0;
            list.querySelectorAll('[data-photo-category]').forEach(function (item) {
                if (item.hidden || item.style.display === 'none') return;
                item.classList.toggle('gallery__item--small', [1, 3, 4].indexOf(index % 6) !== -1);
                item.classList.toggle('is-compact', index % 3 !== 0);
                index++;
            });
        }
        document.querySelectorAll('.gallery__list').forEach(layoutGallery);
        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('.faq-block__trigger');
            if (trigger) {
                event.preventDefault();

                var answer = trigger.closest('.faq-block__item').querySelector('.faq-block__answer');
                var open = trigger.getAttribute('aria-expanded') !== 'true';
                trigger.setAttribute('aria-expanded', String(open));
                trigger.classList.toggle('faq-block__trigger--active', open);
                var duration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 400;
                if (window.jQuery) {
                    var panel = window.jQuery(answer);
                    panel.stop(true, false);
                    if (open) {
                        if (answer.hidden) {
                            answer.hidden = false;
                            panel.hide();
                        }
                        panel.slideDown(duration);
                    } else {
                        panel.slideUp(duration, function () {
                            if (trigger.getAttribute('aria-expanded') === 'false') answer.hidden = true;
                        });
                    }
                } else {
                    answer.hidden = !open;
                    answer.style.display = open ? 'block' : 'none';
                }
                return;
            }
            var specialistFilter = event.target.closest('[data-specialist-filter]');
            if (specialistFilter) {
                event.preventDefault();

                var directory = specialistFilter.closest('.specialists-block');
                var specialty = specialistFilter.dataset.specialistFilter;
                directory.querySelectorAll('[data-specialist-filter]').forEach(function (button) {
                    button.classList.toggle('specialists-block__filter--active', button === specialistFilter);
                    button.setAttribute('aria-pressed', String(button === specialistFilter));
                });
                directory.querySelectorAll('[data-specialist-category]').forEach(function (card) {
                    var values = card.dataset.specialistCategory.split(/\s+/);
                    var other = values.indexOf('other') !== -1 || !values.some(function (value) { return ['psychologist','psychotherapist','family','educator'].indexOf(value) !== -1; });
                    card.style.display = specialty === 'all' || values.indexOf(specialty) !== -1 || (specialty === 'other' && other) ? '' : 'none';
                });
                return;
            }
            var filter = event.target.closest('.gallery .specialists-block__filter');
            if (!filter) return;
            event.preventDefault();

            var gallery = filter.closest('.gallery');
            var list = gallery.querySelector('.gallery__list--all');
            var category = filter.dataset.filter || 'all';
            gallery.querySelectorAll('.specialists-block__filter').forEach(function (item) {
                var active = item === filter;
                item.classList.toggle('specialists-block__filter--active', active);
                item.setAttribute('aria-pressed', String(active));
            });
            list.style.display = 'flex';
            list.querySelectorAll('[data-photo-category]').forEach(function (item) {
                var visible = category === 'all' || item.dataset.photoCategory === category;
                item.hidden = !visible;
                item.style.display = visible ? '' : 'none';
            });
            layoutGallery(list);
        });
        var articleSection = document.querySelector('.articles[data-project-articles]');
        var request = null;
        function markArticleFilter(category) {
            articleSection.querySelectorAll('[data-article-category]').forEach(function (button) {
                var active = button.dataset.articleCategory === category;
                button.classList.toggle('specialists-block__filter--active', active);
                button.setAttribute('aria-pressed', String(active));
            });
        }
        async function loadArticles(url, push) {
            if (request) request.abort();
            request = new AbortController();
            var status = articleSection.querySelector('[data-article-status]');
            articleSection.setAttribute('aria-busy', 'true');
            if (status) status.textContent = '';
            try {
                var response = await fetch(url.href, { credentials: 'same-origin', signal: request.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('HTTP ' + response.status);
                var page = new DOMParser().parseFromString(await response.text(), 'text/html');
                var content = page.querySelector('.articles[data-project-articles] [data-article-results]');
                if (!content) throw new Error('Не найден список статей.');
                articleSection.querySelector('[data-article-results]').replaceWith(content);
                markArticleFilter(url.searchParams.get('category') || '');
                if (push) history.pushState({ lifeArticles: true }, '', url.href);
                if (status) status.textContent = '';
            } catch (error) {
                if (error.name !== 'AbortError' && status) status.textContent = 'Не удалось обновить статьи. Повторите попытку.';
            } finally {
                articleSection.removeAttribute('aria-busy');
            }
        }
        if (articleSection) {
            document.addEventListener('click', function (event) {
                var pagination = event.target.closest('.articles__pagination a');
                if (pagination && articleSection.contains(pagination)) { event.preventDefault();  loadArticles(new URL(pagination.href), true); return; }
                var button = event.target.closest('[data-article-category]');
                if (button && articleSection.contains(button)) {
                    event.preventDefault();

                    if (button.disabled) return;
                    var url = new URL(location.href);
                    url.searchParams.delete('curPos');
                    if (button.dataset.articleCategory) url.searchParams.set('category', button.dataset.articleCategory);
                    else url.searchParams.delete('category');
                    loadArticles(url, true);
                }
            });
            window.addEventListener('popstate', function () { loadArticles(new URL(location.href), false); });
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready);
    else ready();
})();
