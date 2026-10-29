$(function () {

	 $('input[name="f_phone"]').mask("+7(999) 999-9999");
	 
	 $('.pricetable button').each(function () {
		$(this).attr('data-fancybox', 'popup').attr('data-src', '#popup');
	});
	
	$(function () {
	$('.pricetable button[data-fancybox]').attr('data-fancybox', '');
});
});


$(document).on('click', '.pricetable button', function () {
	var t = $(this).attr('data-btn-text') || '';
	$('#popup input[name="f_message"]').val(t);
});

$(document).on('click', '.pricelist__order', function () {
	var service = $(this).attr('data-service') || '';
	$('#popup input[name="f_message"]').val('Заказ услуги: ' + service);
});

$(function(){
	$('.header__burger, .mobile-menu__close').click(function() {
		$('.mobile-menu').toggleClass('mobile-menu--opened');
	});
	$('.mobile-menu__arrow').click(function() {
		$(this).toggleClass('mobile-menu__arrow--active');
		$(this).next('ul').slideToggle();
	});
	$('.faq__item h3').click(function() {
		$(this).parent().toggleClass('faq__item--active');
		$(this).next('.faq__answer').slideToggle();
	});
	$('.doctors__list .doctors__item').click(function() {
		$('.doctors__list .doctors__item').removeClass('doctors__item--active');
		$(this).addClass('doctors__item--active');
	});
	const swiper2 = new Swiper('.swiper2', {
		loop: true,
		slidesPerView: 3,
		spaceBetween: 16,
	});
	const swiper = new Swiper('.swiper', {
		loop: true,
		thumbs: {
			swiper: swiper2
		}
	});
	const swiper3 = new Swiper('.swiper3', {
		loop: true,
		breakpoints: {
			1: {
				slidesPerView: 1,
				spaceBetween: 0,
			},
			576: {
				slidesPerView: 2,
				spaceBetween: 16,
			}
		},
		navigation: {
			nextEl: ".article__next",
			prevEl: ".article__prev",
		},
	});
	const swiper4 = new Swiper('.swiper4', {
		loop: true,
		slidesPerView: "auto",
		spaceBetween: 16,
	});
});

$(function () {
	var $list = $('.blog__list');
	if (!$list.length) return;

	var perPage = 4;                 // карточек на страницу
	var $cards  = $list.find('.article__article');
	var $search = $('.blog__field input');
	var $pages  = $('.blog .pages');

	var filtered = $cards.toArray();  // текущий отфильтрованный набор
	var current  = 1;

	function render() {
		var total = filtered.length;
		var totalPages = Math.max(1, Math.ceil(total / perPage));
		if (current > totalPages) current = totalPages;

		// показываем только карточки текущей страницы
		$cards.hide();
		var start = (current - 1) * perPage;
		$(filtered.slice(start, start + perPage)).show();

		// сообщение «ничего не найдено»
		$list.find('.blog__empty').remove();
		if (total === 0) {
			$list.append('<div class="blog__empty">По запросу ничего не найдено</div>');
		}

		renderPages(totalPages);
	}

	function renderPages(totalPages) {
		$pages.empty();
		if (totalPages <= 1) return;

		for (var i = 1; i <= totalPages; i++) {
			$('<a href="#" class="pages__item">' + i + '</a>')
				.toggleClass('pages__item--active', i === current)
				.data('page', i)
				.appendTo($pages);
		}
	}

	// клик по номеру страницы
	$pages.on('click', '.pages__item', function (e) {
		e.preventDefault();
		current = $(this).data('page');
		render();
		// плавно к началу списка
		$('html, body').animate({ scrollTop: $('.blog__list').offset().top - 100 }, 300);
	});

	// поиск по заголовкам
	var searchTimer;
	function applySearch() {
		var q = $.trim($search.val()).toLowerCase();
		filtered = $cards.toArray().filter(function (el) {
			return !q || ($(el).attr('data-title') || '').indexOf(q) !== -1;
		});
		current = 1;
		render();
	}

	$search.on('input', function () {
		clearTimeout(searchTimer);
		searchTimer = setTimeout(applySearch, 200);   // не дёргаем на каждую букву
	});
	// поиск по кнопке и Enter
	$('.blog__field button').on('click', function (e) { e.preventDefault(); applySearch(); });
	$search.on('keydown', function (e) { if (e.which === 13) { e.preventDefault(); applySearch(); } });

	render();
});

/* ===== +ДОПОЛНЕНИЯ: фикс-шапка + плавный якорь лицензии ===== */
/* =====================================================================
   МедЭльта — ДОПОЛНЕНИЯ К common.js   (добавлено 2026-10)
   Содержит:
     1) Расчёт высоты фиксированной шапки (спейсер, без лагов)
     2) Блокировку скролла фона при открытом мобильном меню
     3) Плавную прокрутку к #license (и другим якорям) с учётом шапки

   КУДА ВСТАВИТЬ: добавить В КОНЕЦ файла
   /netcat_template/template/medelta/js/common.js
   (jQuery уже подключён выше в шапке сайта).
   ===================================================================== */

/* -------- 1. Высота фиксированной шапки -> CSS-переменная ----------
   Важно: слушаем ТОЛЬКО load/resize/orientationchange, НЕ scroll —
   поэтому во время прокрутки ничего не пересчитывается и не дёргается. */
(function () {
  function setHeaderHeight() {
    var h = document.querySelector('.site-header');
    if (!h) return;
    document.documentElement.style.setProperty('--site-header-h', h.offsetHeight + 'px');
  }
  if (document.readyState !== 'loading') setHeaderHeight();
  else document.addEventListener('DOMContentLoaded', setHeaderHeight);
  window.addEventListener('load', setHeaderHeight);
  window.addEventListener('resize', setHeaderHeight);
  window.addEventListener('orientationchange', function () {
    setTimeout(setHeaderHeight, 200);
  });
})();

$(function () {

  /* -------- 2. Лок скролла фона при открытом мобильном меню --------
     common.js выше переключает класс .mobile-menu--opened по клику на
     .header__burger и .mobile-menu__close. Синхронизируем с body. */
  $('.header__burger, .mobile-menu__close').on('click', function () {
    $('body').toggleClass('menu-open', $('.mobile-menu').hasClass('mobile-menu--opened'));
  });

  /* -------- 3. Плавная прокрутка к якорям (в т.ч. #license) --------- */
  function headerOffset() {
    var h = document.querySelector('.site-header');
    // учитываем высоту шапки только когда она реально зафиксирована (мобила)
    if (h && getComputedStyle(h).position === 'fixed') return h.offsetHeight + 12;
    return 20;
  }

  function scrollToHash(hash) {
    var $t;
    try { $t = $(hash); } catch (e) { return false; }
    if (!$t || !$t.length) return false;
    $('html, body').animate({ scrollTop: $t.offset().top - headerOffset() }, 500);
    return true;
  }

  // Клик по ссылке-якорю (#license, /#license, https://medelta.clinic/#license)
  $(document).on('click', 'a[href*="#"]', function (e) {
    var href = $(this).attr('href') || '';
    var i = href.indexOf('#');
    if (i < 0) return;
    var hash = href.substring(i);
    if (hash.length < 2 || hash === '#') return;           // пустые #
    if (/\s/.test(hash)) return;                           // некорректные
    // прокручиваем только если такой якорь ЕСТЬ на текущей странице
    if ($(hash).length) {
      e.preventDefault();
      scrollToHash(hash);
      if (history.replaceState) history.replaceState(null, '', hash);
    }
    // иначе — обычный переход (например, /#license c внутренней страницы
    // на главную); доскролл сработает ниже после загрузки главной.
  });

  // Если открыли страницу сразу с #якорем в URL — доскроллим с учётом шапки
  if (window.location.hash && window.location.hash.length > 1) {
    try {
      if ($(window.location.hash).length) {
        setTimeout(function () { scrollToHash(window.location.hash); }, 350);
      }
    } catch (e) {}
  }
});

/* ===== Cookie notice (информационный; аналитику НЕ трогает) ===== */
(function(){
  var KEY='medelta_cookie';
  function getC(){try{return localStorage.getItem(KEY);}catch(e){return null;}}
  function setC(v){
    try{localStorage.setItem(KEY,v);}catch(e){}
    try{document.cookie=KEY+'='+v+';path=/;max-age=31536000;samesite=lax';}catch(e){}
  }
  document.addEventListener('DOMContentLoaded',function(){
    var b=document.getElementById('cookieBanner');
    if(!b) return;
    function show(){b.classList.add('is-visible');}
    function hide(){b.classList.remove('is-visible');}
    if(!getC()) show();
    var accept=b.querySelector('[data-cookie-accept]');
    if(accept) accept.addEventListener('click',function(){setC('accepted');hide();});
    // повторно показать уведомление: ссылка <a href="#cookie"> или [data-cookie-settings]
    function reopen(e){ if(e&&e.preventDefault) e.preventDefault(); show(); }
    window.medeltaOpenCookieSettings=reopen;
    document.querySelectorAll('a[href="#cookie"],[data-cookie-settings]').forEach(function(el){el.addEventListener('click',reopen);});
  });
})();
