$(function(){
	$('.header__burger, .mobile-menu__close').click(function() {
		$('.mobile-menu').toggleClass('mobile-menu--active');
	});
	$('.mobile-menu__sublink--dropdown').click(function() {
		$(this).parent().toggleClass('mobile-menu__subitem--active');
		$(this).next('.mobile-menu__subsublist').slideToggle();
	});
	$('.mobile-menu__link--dropdown').click(function() {
		$(this).parent().toggleClass('mobile-menu__item--active');
		$(this).next('.mobile-menu__sublist').slideToggle();
	});
	$('.faq-block__trigger').click(function() {
		$(this).toggleClass('faq-block__trigger--active');
		$(this).next('.faq-block__answer').slideToggle();
	});
	$(document).ready(function() {
	    const $steps = $('.quiz__step');
	    const $btnBack = $('.quiz__btn--back');
	    const $btnNext = $('.quiz__btn--next');
	    const $btnSubmit = $('.quiz__btn--submit');
	    const $progressFill = $('.quiz__progress-fill');
	    const $progressPercent = $('.quiz__progress-percent');
	    const $stepCurrent = $('.quiz__step-current');
	    const totalSteps = $steps.length;
	    let currentStep = 1;
	    function updateUI() {
	        $steps.removeClass('active');
	        $steps.filter(`[data-step="${currentStep}"]`).addClass('active');
	        const percent = Math.round((currentStep / totalSteps) * 100);
	        $progressFill.css('width', percent + '%');
	        $progressPercent.text(percent + '%');
	        $stepCurrent.text(currentStep);
	        const canGoBack = currentStep > 1;
	        $btnBack.prop('disabled', !canGoBack).toggleClass('quiz__btn--disabled', !canGoBack);
	        const isLastStep = currentStep === totalSteps;
	        if (isLastStep) {
	            $btnNext.hide();
	            $btnSubmit.show();
	            const $currentStepEl = $steps.filter(`[data-step="${currentStep}"]`);
	            const $inputs = $currentStepEl.find('.quiz__input');
	            let allFilled = true;
	            $inputs.each(function() {
	                if ($(this).val().trim() === '') {
	                    allFilled = false;
	                    return false;
	                }
	            });
	            $btnSubmit.prop('disabled', !allFilled).toggleClass('quiz__btn--disabled', !allFilled);
	        } else {
	            $btnNext.show();
	            $btnSubmit.hide();
	            const $currentStepEl = $steps.filter(`[data-step="${currentStep}"]`);
	            const hasSelected = $currentStepEl.find('input[type="radio"]:checked').length > 0;
	            $btnNext.prop('disabled', !hasSelected).toggleClass('quiz__btn--disabled', !hasSelected);
	        }
	    }
	    function goToStep(step) {
	        if (step < 1 || step > totalSteps) return;
	        currentStep = step;
	        updateUI();
	    }
	    $btnBack.on('click', function() {
	        goToStep(currentStep - 1);
	    });
	    $btnNext.on('click', function() {
	        goToStep(currentStep + 1);
	    });
	    $btnSubmit.on('click', function() {
	        console.log('Форма отправлена');
	    });
	    $('input[type="radio"]').on('change', function() {
	        updateUI();
	    });
	    $('.quiz__contact-form').on('input', '.quiz__input', function() {
	        updateUI();
	    });
	    goToStep(1);
	});
	$(document).ready(function() {
		var $gallery = $('.gallery');
		if (!$gallery.length) {
			return;
		}

		var $filters = $gallery.find('.specialists-block__filters');
		var $lists = $gallery.find('.gallery__list');

		$filters.on('click', '.specialists-block__filter', function() {
			var $btn = $(this);
			var filterValue = $btn.data('filter') || 'all';

			$filters.find('.specialists-block__filter').removeClass('specialists-block__filter--active');
			$btn.addClass('specialists-block__filter--active');

			$lists.hide();

			if (filterValue === 'all') {
				$gallery.find('.gallery__list--all').fadeIn(300);
			} else {
				var $target = $gallery.find('.gallery__list--' + filterValue);
				if ($target.length) {
					$target.fadeIn(300);
				}
			}
		});
	});
	const swiper = new Swiper('.swiper', {
		loop: true,
		spaceBetween: 16,
		breakpoints: {
			1: {
				slidesPerView: "auto",
			},
			768: {
				slidesPerView: 3,
			},
			992: {
				slidesPerView: 4,
			},
			1290: {
				slidesPerView: 5,
			}
		},
	});
	const swiper2 = new Swiper('.swiper2', {
		loop: true,
		breakpoints: {
			1: {
				slidesPerView: 1,
				spaceBetween: 0,
			},
			768: {
				slidesPerView: 2,
				spaceBetween: 20,
			},
			1290: {
				slidesPerView: 3,
				spaceBetween: 20,
			},
		},
		navigation: {
			nextEl: ".reviews .slider-nav__link--next",
			prevEl: ".reviews .slider-nav__link--prev",
		},
		pagination: {
			el: ".reviews .slider-nav__dots",
			clickable: true,
		},
	});
	const swiper3 = new Swiper('.swiper3', {
		loop: true,
		breakpoints: {
			1: {
				slidesPerView: "auto",
				spaceBetween: 20,
			},
			768: {
				slidesPerView: 2,
				spaceBetween: 20,
			},
			992: {
				slidesPerView: 3,
				spaceBetween: 20,
			},
			1280: {
				slidesPerView: 4,
				spaceBetween: 20,
			},
		},
		navigation: {
			nextEl: ".certificates .slider-nav__link--next",
			prevEl: ".certificates .slider-nav__link--prev",
		},
		pagination: {
			el: ".certificates .slider-nav__dots",
			clickable: true,
		},
	});
	const swiper4 = new Swiper('.swiper4', {
		loop: true,
		slidesPerView: "auto",
		spaceBetween: 16,
	});
});