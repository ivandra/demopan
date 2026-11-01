/**
 * NcAuthPopup - Современный попап авторизации для Netcat CMS
 *
 * Состояния:
 * - initial: Email + OAuth кнопки
 * - register: Форма регистрации
 * - login_password: Ввод пароля
 * - code_input: Ввод 6-значного кода
 * - set_password: Установка пароля (опционально)
 * - success: Успешная авторизация
 *
 * @version 1.0.0
 */
(function(window, document) {
    'use strict';

    var API_URL = '/netcat/modules/auth/api/auth.php';
    var POPUP_TEMPLATE_URL = '/netcat/modules/auth/templates/popup.html';

    /**
     * Конструктор попапа авторизации
     * @param {Object} options Настройки
     */
    function NcAuthPopup(options) {
        this.options = Object.assign({
            containerSelector: '#nc-auth-popup-container',
            onSuccess: null,
            onClose: null,
            redirectUrl: null,
            purpose: 'login'
        }, options || {});

        this.state = 'initial';
        this.email = '';
        this.userExists = false;
        this.hasPassword = false;
        this.passwordMode = 'both'; // 'password', 'both', 'code'
        this.isLoading = false;
        this.resendTimer = 0;
        this.resendInterval = null;

        this.container = null;
        this.popup = null;
        this.templateLoaded = false;

        // Поля для регистрации
        this.registerFields = null;
        this.requireAgreement = true;

        // OAuth провайдеры
        this.oauthProviders = null;

        // Режим сброса пароля
        this.isPasswordReset = false;
        this.resetCode = null;

        // Флаг привязки событий (защита от двойной привязки)
        this.eventsBound = false;

        this.init();
    }

    /**
     * Инициализация
     */
    NcAuthPopup.prototype.init = function() {
        var self = this;

        // Загружаем шаблон если нужно
        this.loadTemplate(function() {
            self.templateLoaded = true;
            self.bindEvents();
            self.loadOAuthProviders();
        });
    };

    /**
     * Загрузка HTML шаблона
     */
    NcAuthPopup.prototype.loadTemplate = function(callback) {
        var self = this;
        var container = document.querySelector(this.options.containerSelector);

        if (container && container.innerHTML.trim()) {
            this.container = container;
            this.popup = container.querySelector('.nc-auth-popup');
            callback();
            return;
        }

        // Создаем контейнер если нет
        if (!container) {
            container = document.createElement('div');
            container.id = this.options.containerSelector.replace('#', '');
            document.body.appendChild(container);
        }
        this.container = container;

        // Загружаем шаблон
        var xhr = new XMLHttpRequest();
        xhr.open('GET', POPUP_TEMPLATE_URL, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    container.innerHTML = xhr.responseText;
                    self.popup = container.querySelector('.nc-auth-popup');
                    // Применяем классы блока для слотов миксинов
                    if (window.NcAuth) {
                        if (window.NcAuth._blockId) {
                            window.NcAuth._applyBlockClasses(self.popup);
                        }
                        // Применяем текст согласия
                        window.NcAuth._applyConsentTexts(self.popup);
                    }
                    callback();
                } else {
                    console.error('NcAuthPopup: Failed to load template');
                }
            }
        };
        xhr.send();
    };

    /**
     * Привязка событий
     */
    NcAuthPopup.prototype.bindEvents = function() {
        var self = this;

        if (!this.popup) return;

        // Защита от повторной привязки
        if (this.eventsBound) return;
        this.eventsBound = true;

        // Закрытие попапа (клик по оверлею или кнопке закрытия)
        this.popup.addEventListener('click', function(e) {
            // Клик по оверлею (самому попапу)
            if (e.target.classList.contains('nc-auth-popup')) {
                self.close();
                return;
            }
            // Клик по кнопке закрытия или её дочерним элементам (SVG)
            if (e.target.closest('.nc-auth-popup__close')) {
                self.close();
            }
        });

        // Escape для закрытия
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && self.isOpen()) {
                self.close();
            }
        });

        // Форма email
        var emailForm = this.popup.querySelector('#nc-auth-email-form');
        if (emailForm) {
            emailForm.addEventListener('submit', function(e) {
                e.preventDefault();
                self.handleEmailSubmit();
            });
        }

        // Форма пароля
        var passwordForm = this.popup.querySelector('#nc-auth-password-form');
        if (passwordForm) {
            passwordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                self.handlePasswordSubmit();
            });
        }

        // Кнопка "Войти без пароля"
        var codeLoginBtn = this.popup.querySelector('#nc-auth-code-login');
        if (codeLoginBtn) {
            codeLoginBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                if (self.isLoading) return false;
                self.isPasswordReset = false;
                self.sendCode();
                return false;
            });
        }

        // Кнопка "Забыл пароль"
        var forgotPasswordBtn = this.popup.querySelector('#nc-auth-forgot-password');
        if (forgotPasswordBtn) {
            forgotPasswordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                console.log('Forgot password clicked, email:', self.email, 'isLoading:', self.isLoading);
                if (self.isLoading) return false;
                self.isPasswordReset = true;
                self.sendCode('recovery');
                return false;
            });
        }

        // Форма кода
        var codeForm = this.popup.querySelector('#nc-auth-code-form');
        if (codeForm) {
            codeForm.addEventListener('submit', function(e) {
                e.preventDefault();
                self.handleCodeSubmit();
            });
        }

        // Ввод кода - автофокус между полями
        var codeInputs = this.popup.querySelectorAll('.nc-auth-code-input');
        codeInputs.forEach(function(input, index) {
            input.addEventListener('input', function(e) {
                var value = e.target.value;
                if (value.length === 1 && index < codeInputs.length - 1) {
                    codeInputs[index + 1].focus();
                }
                // Автоотправка при полном вводе
                if (index === codeInputs.length - 1 && value.length === 1) {
                    self.handleCodeSubmit();
                }
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    codeInputs[index - 1].focus();
                }
            });

            // Вставка из буфера обмена
            input.addEventListener('paste', function(e) {
                e.preventDefault();
                var paste = (e.clipboardData || window.clipboardData).getData('text');
                var digits = paste.replace(/\D/g, '').split('');

                for (var i = 0; i < Math.min(digits.length, codeInputs.length); i++) {
                    codeInputs[i].value = digits[i];
                }

                if (digits.length >= codeInputs.length) {
                    self.handleCodeSubmit();
                }
            });
        });

        // Повторная отправка кода
        var resendBtn = this.popup.querySelector('#nc-auth-resend-code');
        if (resendBtn) {
            resendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (!resendBtn.classList.contains('disabled')) {
                    self.sendCode(self.lastCodePurpose);
                }
            });
        }

        // Форма регистрации
        var registerForm = this.popup.querySelector('#nc-auth-register-form');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                self.handleRegisterSubmit();
            });
        }

        // Форма установки пароля
        var setPasswordForm = this.popup.querySelector('#nc-auth-set-password-form');
        if (setPasswordForm) {
            setPasswordForm.addEventListener('submit', function(e) {
                e.preventDefault();
                self.handleSetPasswordSubmit();
            });
        }

        // Кнопка "Пропустить"
        var skipPasswordBtn = this.popup.querySelector('#nc-auth-skip-password');
        if (skipPasswordBtn) {
            skipPasswordBtn.addEventListener('click', function(e) {
                e.preventDefault();
                self.completeAuth();
            });
        }

        // Кнопка "Назад"
        var backBtns = this.popup.querySelectorAll('.nc-auth-back');
        backBtns.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                self.goBack();
            });
        });

        // Кнопка показа/скрытия пароля
        this.bindPasswordToggle(this.popup);
    };

    /**
     * Привязка событий для кнопок показа/скрытия пароля
     */
    NcAuthPopup.prototype.bindPasswordToggle = function(container) {
        var toggleBtns = container.querySelectorAll('.nc-auth-field__toggle-password');
        toggleBtns.forEach(function(btn) {
            // Убираем старые обработчики (если есть)
            var newBtn = btn.cloneNode(true);
            btn.parentNode.replaceChild(newBtn, btn);

            newBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var wrapper = newBtn.closest('.nc-auth-field__password-wrapper');
                var input = wrapper ? wrapper.querySelector('input') : null;
                if (!input) return;

                var isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                newBtn.classList.toggle('is-visible', isPassword);
                newBtn.title = isPassword ? 'Скрыть пароль' : 'Показать пароль';
            });
        });
    };

    /**
     * Открытие попапа
     */
    NcAuthPopup.prototype.open = function() {
        if (!this.templateLoaded) {
            var self = this;
            setTimeout(function() { self.open(); }, 100);
            return;
        }

        this.popup.classList.add('active');
        document.body.classList.add('nc-auth-popup-open');
        this.setState('initial');
        this.clearErrors();

        // Фокус на email
        var emailInput = this.popup.querySelector('#nc-auth-email');
        if (emailInput) {
            setTimeout(function() { emailInput.focus(); }, 100);
        }
    };

    /**
     * Открытие с предзаполненным email
     * @param {string} email - Email адрес
     * @param {Object} options - Дополнительные опции (onSuccess, onClose, context)
     */
    NcAuthPopup.prototype.openWithEmail = function(email, options) {
        var self = this;

        // Мержим options если переданы
        if (options) {
            Object.assign(this.options, options);
        }

        this.email = email;

        // Ждём загрузки шаблона перед работой с DOM
        if (!this.templateLoaded) {
            setTimeout(function() { self.openWithEmail(email, options); }, 100);
            return;
        }

        this.open();

        var emailInput = this.popup.querySelector('#nc-auth-email');
        if (emailInput) {
            emailInput.value = email;
        }

        // Сразу проверяем email
        this.handleEmailSubmit();
    };

    /**
     * Закрытие попапа
     */
    NcAuthPopup.prototype.close = function() {
        if (this.popup) {
            this.popup.classList.remove('active');
        }
        document.body.classList.remove('nc-auth-popup-open');
        this.clearResendTimer();

        if (typeof this.options.onClose === 'function') {
            this.options.onClose();
        }
    };

    /**
     * Проверка открыт ли попап
     */
    NcAuthPopup.prototype.isOpen = function() {
        return this.popup && this.popup.classList.contains('active');
    };

    /**
     * Установка состояния
     */
    NcAuthPopup.prototype.setState = function(state) {
        var self = this;
        this.state = state;

        // Скрываем все экраны
        var screens = this.popup.querySelectorAll('.nc-auth-screen');
        screens.forEach(function(screen) {
            screen.classList.remove('active');
        });

        // Показываем нужный
        var activeScreen = this.popup.querySelector('[data-screen="' + state + '"]');
        if (activeScreen) {
            activeScreen.classList.add('active');
        }

        // Обновляем email в заголовках
        var emailDisplays = this.popup.querySelectorAll('.nc-auth-email-display');
        emailDisplays.forEach(function(el) {
            el.textContent = this.email;
        }.bind(this));

        // При переходе на регистрацию загружаем поля
        if (state === 'register') {
            this.loadRegisterFields(function() {
                // Фокус на первом поле после загрузки
                var firstInput = self.popup.querySelector('#nc-auth-register-fields input, #nc-auth-register-fields select');
                if (firstInput) {
                    setTimeout(function() { firstInput.focus(); }, 100);
                }
            });
        }
    };

    /**
     * Назад к предыдущему состоянию
     */
    NcAuthPopup.prototype.goBack = function() {
        var prevState = {
            'login_password': 'initial',
            'code_input': this.hasPassword ? 'login_password' : 'initial',
            'register': 'initial',
            'set_password': 'success'
        };

        this.setState(prevState[this.state] || 'initial');
    };

    /**
     * Обработка отправки email
     */
    NcAuthPopup.prototype.handleEmailSubmit = function() {
        var emailInput = this.popup.querySelector('#nc-auth-email');
        this.email = emailInput.value.trim().toLowerCase();

        if (!this.validateEmail(this.email)) {
            this.showError('email', window.ncAuthLang.invalidEmail || 'Некорректный email');
            return;
        }

        this.setLoading(true);
        this.clearErrors();

        var self = this;
        this.apiRequest('check_email', { email: this.email }, function(response) {
            self.setLoading(false);

            if (response.success) {
                self.userExists = response.exists;
                self.hasPassword = response.has_password;
                self.passwordMode = response.password_mode || 'both';

                if (response.exists) {
                    // Пользователь существует - определяем flow в зависимости от режима
                    if (self.passwordMode === 'code') {
                        // Только код - всегда отправляем код
                        self.sendCode();
                    } else if (self.passwordMode === 'password') {
                        // Только пароль - показываем форму пароля
                        self.setState('login_password');
                        self.updatePasswordFormForMode();
                        var passInput = self.popup.querySelector('#nc-auth-password');
                        if (passInput) setTimeout(function() { passInput.focus(); }, 100);
                    } else {
                        // 'both' - пароль или код
                        if (response.has_password) {
                            self.setState('login_password');
                            self.updatePasswordFormForMode();
                            var passInput = self.popup.querySelector('#nc-auth-password');
                            if (passInput) setTimeout(function() { passInput.focus(); }, 100);
                        } else {
                            // Пользователь есть, но пароля нет - отправляем код
                            self.sendCode();
                        }
                    }
                } else {
                    // Новый пользователь - регистрация
                    self.setState('register');
                }

                // Если требуется капча для входа — показываем её сразу на экране пароля
                if (self.state === 'login_password' && response.need_captcha && response.captcha_html) {
                    self.renderCaptcha(response.captcha_html, 'login_password');
                }
            } else {
                self.showError('email', response.message);
            }
        });
    };

    /**
     * Обработка отправки пароля
     */
    NcAuthPopup.prototype.handlePasswordSubmit = function() {
        var passwordInput = this.popup.querySelector('#nc-auth-password');
        var password = passwordInput.value;

        if (!password) {
            this.showError('password', window.ncAuthLang.enterPassword || 'Введите пароль');
            return;
        }

        this.setLoading(true);
        this.clearErrors();

        var self = this;
        this.apiRequest('authorize', this.withCaptcha({
            email: this.email,
            password: password
        }), function(response) {
            self.setLoading(false);

            if (response.success) {
                self.completeAuth(response.user);
            } else if (!self.handleCaptchaResponse(response)) {
                self.showError('password', response.message);
            }
        });
    };

    /**
     * Обновляет форму пароля в зависимости от режима
     */
    NcAuthPopup.prototype.updatePasswordFormForMode = function() {
        var codeLoginBtn = this.popup.querySelector('#nc-auth-code-login');
        var forgotPasswordBtn = this.popup.querySelector('#nc-auth-forgot-password');

        if (codeLoginBtn) {
            // Скрываем "Войти по коду" если режим только пароль
            codeLoginBtn.style.display = this.passwordMode === 'password' ? 'none' : '';
        }

        // "Забыл пароль" показываем всегда (сброс через код работает независимо от режима)
        if (forgotPasswordBtn) {
            forgotPasswordBtn.style.display = '';
        }
    };

    /**
     * Отправка кода верификации
     * @param {string} purpose - цель кода (login, registration, checkout, recovery)
     */
    NcAuthPopup.prototype.sendCode = function(purpose) {
        // Проверка email
        if (!this.email) {
            console.error('NcAuthPopup.sendCode: email is empty');
            return;
        }

        this.setLoading(true);
        this.clearErrors();

        var self = this;
        var codePurpose = purpose || this.options.purpose;
        this.lastCodePurpose = codePurpose;

        this.apiRequest('send_code', this.withCaptcha({
            email: this.email,
            purpose: codePurpose
        }), function(response) {
            self.setLoading(false);

            // Требуется капча для отправки кода. Если на текущем экране нет слота
            // (например, экран ввода email) — уводим на экран ввода кода, где слот есть,
            // и даём решить капчу и запросить код повторно.
            if (response.error === 'captcha_required' || response.error === 'captcha_wrong') {
                var active = self.popup.querySelector('.nc-auth-screen.active');
                var hasSlot = active && active.querySelector('.nc-auth-captcha-slot');
                if (!hasSlot) {
                    self.setState('code_input');
                }
                if (response.captcha_html) {
                    self.renderCaptcha(response.captcha_html);
                }
                self.showError(self.state === 'code_input' ? 'code' : 'password', response.message);
                self.clearResendTimer();
                var resendEl = self.popup.querySelector('#nc-auth-resend-code');
                if (resendEl) resendEl.classList.remove('disabled');
                return;
            }

            if (response.success) {
                self.setState('code_input');
                self.startResendTimer(response.resend_after || 60);

                // Фокус на первом поле кода
                var firstCodeInput = self.popup.querySelector('.nc-auth-code-input');
                if (firstCodeInput) {
                    setTimeout(function() { firstCodeInput.focus(); }, 100);
                }

                // Очищаем поля кода
                var codeInputs = self.popup.querySelectorAll('.nc-auth-code-input');
                codeInputs.forEach(function(input) { input.value = ''; });
            } else {
                // Если ошибка "too_soon" - код уже был отправлен, показываем экран ввода
                if (response.error === 'too_soon' && response.resend_after) {
                    self.setState('code_input');
                    self.startResendTimer(response.resend_after);

                    // Фокус на первом поле кода
                    var firstCodeInput = self.popup.querySelector('.nc-auth-code-input');
                    if (firstCodeInput) {
                        setTimeout(function() { firstCodeInput.focus(); }, 100);
                    }
                } else {
                    // Показываем ошибку на текущем экране
                    var errorField = self.state === 'login_password' ? 'password' : 'code';
                    self.showError(errorField, response.message);

                    if (response.resend_after) {
                        self.startResendTimer(response.resend_after);
                    }
                }
            }
        });
    };

    /**
     * Обработка отправки кода
     */
    NcAuthPopup.prototype.handleCodeSubmit = function() {
        var codeInputs = this.popup.querySelectorAll('.nc-auth-code-input');
        var code = '';
        codeInputs.forEach(function(input) {
            code += input.value;
        });

        if (code.length !== codeInputs.length) {
            this.showError('code', window.ncAuthLang.enterFullCode || 'Введите полный код');
            return;
        }

        this.setLoading(true);
        this.clearErrors();

        var self = this;

        // Если это сброс пароля - только проверяем код, не авторизуем
        if (this.isPasswordReset) {
            this.apiRequest('verify_code', {
                email: this.email,
                code: code
            }, function(response) {
                self.setLoading(false);

                if (response.valid) {
                    // Код верный - сохраняем и показываем форму нового пароля
                    self.resetCode = code;
                    self.setState('set_password');
                    // Обновляем заголовок
                    var title = self.popup.querySelector('[data-screen="set_password"] .nc-auth-popup__title');
                    var subtitle = self.popup.querySelector('[data-screen="set_password"] .nc-auth-popup__subtitle');
                    if (title) title.textContent = window.ncAuthLang.newPassword || 'Новый пароль';
                    if (subtitle) subtitle.textContent = window.ncAuthLang.enterNewPassword || 'Придумайте новый пароль';
                    // Скрываем кнопку "Пропустить"
                    var skipBtn = self.popup.querySelector('#nc-auth-skip-password');
                    if (skipBtn) skipBtn.style.display = 'none';
                } else {
                    self.showError('code', response.message);
                    if (response.attempts_left !== undefined && response.attempts_left < 3) {
                        var attemptsMsg = self.popup.querySelector('.nc-auth-attempts-left');
                        if (attemptsMsg) {
                            attemptsMsg.textContent = (window.ncAuthLang.attemptsLeft || 'Осталось попыток: ') + response.attempts_left;
                            attemptsMsg.style.display = 'block';
                        }
                    }
                }
            });
            return;
        }

        // Обычная авторизация по коду
        this.apiRequest('authorize', this.withCaptcha({
            email: this.email,
            code: code
        }), function(response) {
            self.setLoading(false);

            if (self.handleCaptchaResponse(response)) {
                return;
            }

            if (response.success) {
                // Обновляем режим пароля из ответа
                if (response.password_mode) {
                    self.passwordMode = response.password_mode;
                }

                // В режиме 'code' не предлагаем устанавливать пароль
                if (self.passwordMode === 'code') {
                    self.completeAuth(response.user);
                } else if (self.passwordMode === 'both' && (response.just_registered || !response.has_password)) {
                    // В режиме 'both' предлагаем установить пароль:
                    // - если только что зарегистрировались (пароль сгенерирован случайно)
                    // - или если пароля нет вообще
                    self.setState('set_password');
                } else {
                    self.completeAuth(response.user);
                }
            } else if (response.need_registration) {
                // Email верифицирован, но пользователя нет - показываем регистрацию
                self.setState('register');
                // Отмечаем что email уже верифицирован
                var verifiedInput = self.popup.querySelector('#nc-auth-email-verified');
                if (verifiedInput) verifiedInput.value = '1';
            } else {
                self.showError('code', response.message);

                // Показываем оставшиеся попытки
                if (response.attempts_left !== undefined && response.attempts_left < 3) {
                    var attemptsMsg = self.popup.querySelector('.nc-auth-attempts-left');
                    if (attemptsMsg) {
                        attemptsMsg.textContent = (window.ncAuthLang.attemptsLeft || 'Осталось попыток: ') + response.attempts_left;
                        attemptsMsg.style.display = 'block';
                    }
                }
            }
        });
    };

    /**
     * Загрузка полей регистрации из API
     */
    NcAuthPopup.prototype.loadRegisterFields = function(callback) {
        var self = this;

        // Если поля уже загружены, используем кэш
        if (this.registerFields !== null) {
            if (this.registerNeedCaptcha && this.registerCaptchaHtml) {
                this.renderCaptcha(this.registerCaptchaHtml, 'register');
            }
            callback();
            return;
        }

        this.apiRequest('get_register_fields', {}, function(response) {
            if (response.success) {
                self.registerFields = response.fields || [];
                self.requireAgreement = response.require_agreement !== false;
                self.registerNeedCaptcha = !!response.need_captcha;
                self.registerCaptchaHtml = response.captcha_html || '';
                self.renderRegisterFields();
                if (self.registerNeedCaptcha && self.registerCaptchaHtml) {
                    self.renderCaptcha(self.registerCaptchaHtml, 'register');
                }
            }
            callback();
        });
    };

    /**
     * Рендеринг полей регистрации в форму
     */
    NcAuthPopup.prototype.renderRegisterFields = function() {
        var container = this.popup.querySelector('#nc-auth-register-fields');
        if (!container) return;

        var html = '';

        for (var i = 0; i < this.registerFields.length; i++) {
            var field = this.registerFields[i];
            html += this.renderField(field);
        }

        // В режиме 'password' добавляем поле пароля
        if (this.passwordMode === 'password') {
            html += this.renderPasswordFieldForRegistration();
        }

        container.innerHTML = html;

        // Показываем/скрываем чекбокс согласия
        var agreementBlock = this.popup.querySelector('.nc-auth-agreement-block');
        if (agreementBlock) {
            agreementBlock.style.display = this.requireAgreement ? 'block' : 'none';
        }

        // Привязываем события для кнопок показа пароля
        this.bindPasswordToggle(container);
    };

    /**
     * Рендеринг поля пароля для регистрации
     */
    NcAuthPopup.prototype.renderPasswordFieldForRegistration = function() {
        var html = '';

        html += '<div class="nc-auth-field">';
        html += '<label class="nc-auth-field__label" for="nc-auth-register-password">' +
                (window.ncAuthLang.password || 'Пароль') + ' <span class="nc-auth-required">*</span></label>';
        html += '<div class="nc-auth-field__password-wrapper">';
        html += '<input type="password" id="nc-auth-register-password" name="password" ' +
                'class="nc-auth-field__input" required minlength="6" placeholder="' +
                (window.ncAuthLang.passwordPlaceholder || 'Минимум 6 символов') + '">';
        html += '<button type="button" class="nc-auth-field__toggle-password" aria-label="Показать пароль" title="Показать пароль"></button>';
        html += '</div>';
        html += '<div class="nc-auth-error" data-error="password"></div>';
        html += '</div>';

        return html;
    };

    /**
     * Рендеринг одного поля
     */
    NcAuthPopup.prototype.renderField = function(field) {
        var required = field.required ? ' required' : '';
        var requiredMark = field.required ? ' <span class="nc-auth-required">*</span>' : '';
        var html = '<div class="nc-auth-field">';
        html += '<label class="nc-auth-field__label" for="nc-auth-field-' + field.name + '">' +
                field.label + requiredMark + '</label>';

        switch (field.type) {
            case 'string':
            case 'int':
            case 'float':
                var inputType = field.type === 'string' ? 'text' : 'number';
                html += '<input type="' + inputType + '" id="nc-auth-field-' + field.name + '" ' +
                        'name="' + field.name + '" class="nc-auth-field__input"' + required + '>';
                break;

            case 'text':
                html += '<textarea id="nc-auth-field-' + field.name + '" name="' + field.name + '" ' +
                        'class="nc-auth-field__input nc-auth-field__textarea" rows="3"' + required + '></textarea>';
                break;

            case 'select':
                html += '<select id="nc-auth-field-' + field.name + '" name="' + field.name + '" ' +
                        'class="nc-auth-field__input nc-auth-field__select"' + required + '>';
                html += '<option value="">-- выберите --</option>';
                if (field.options) {
                    for (var j = 0; j < field.options.length; j++) {
                        var opt = field.options[j];
                        html += '<option value="' + opt.value + '">' + opt.label + '</option>';
                    }
                }
                html += '</select>';
                break;

            case 'multiselect':
                html += '<select id="nc-auth-field-' + field.name + '" name="' + field.name + '" ' +
                        'class="nc-auth-field__input nc-auth-field__select" multiple' + required + '>';
                if (field.options) {
                    for (var k = 0; k < field.options.length; k++) {
                        var opt2 = field.options[k];
                        html += '<option value="' + opt2.value + '">' + opt2.label + '</option>';
                    }
                }
                html += '</select>';
                break;

            case 'boolean':
                html += '<div class="nc-auth-checkbox">';
                html += '<input type="checkbox" id="nc-auth-field-' + field.name + '" name="' + field.name + '">';
                html += '<label for="nc-auth-field-' + field.name + '">' + field.label + '</label>';
                html += '</div>';
                break;

            case 'datetime':
                html += '<input type="datetime-local" id="nc-auth-field-' + field.name + '" ' +
                        'name="' + field.name + '" class="nc-auth-field__input"' + required + '>';
                break;

            default:
                html += '<input type="text" id="nc-auth-field-' + field.name + '" ' +
                        'name="' + field.name + '" class="nc-auth-field__input"' + required + '>';
        }

        html += '<div class="nc-auth-error" data-error="' + field.name + '"></div>';
        html += '</div>';

        return html;
    };

    /**
     * Сбор данных из динамических полей
     */
    NcAuthPopup.prototype.collectFieldsData = function() {
        var data = {};

        if (!this.registerFields) return data;

        for (var i = 0; i < this.registerFields.length; i++) {
            var field = this.registerFields[i];
            var input = this.popup.querySelector('#nc-auth-field-' + field.name);

            if (!input) continue;

            if (field.type === 'boolean') {
                data[field.name] = input.checked ? '1' : '0';
            } else if (field.type === 'multiselect') {
                var selected = [];
                for (var j = 0; j < input.options.length; j++) {
                    if (input.options[j].selected) {
                        selected.push(input.options[j].value);
                    }
                }
                data[field.name] = selected.join(',');
            } else {
                data[field.name] = input.value;
            }
        }

        return data;
    };

    /**
     * Валидация обязательных полей
     */
    NcAuthPopup.prototype.validateRegisterFields = function() {
        if (!this.registerFields) return true;

        for (var i = 0; i < this.registerFields.length; i++) {
            var field = this.registerFields[i];
            if (!field.required) continue;

            var input = this.popup.querySelector('#nc-auth-field-' + field.name);
            if (!input) continue;

            var value = field.type === 'boolean' ? input.checked : input.value.trim();

            if (!value) {
                this.showError(field.name, (window.ncAuthLang.fieldRequired || 'Поле обязательно').replace('%s', field.label));
                input.focus();
                return false;
            }
        }

        return true;
    };

    /**
     * Обработка регистрации
     */
    NcAuthPopup.prototype.handleRegisterSubmit = function() {
        var consentPDCheckbox = this.popup.querySelector('#nc-auth-consent-pd-checkbox');
        var consentRulesCheckbox = this.popup.querySelector('#nc-auth-consent-rules-checkbox');
        var emailVerified = this.popup.querySelector('#nc-auth-email-verified');

        // Валидация динамических полей
        if (!this.validateRegisterFields()) {
            return;
        }

        // В режиме 'password' валидация пароля
        var password = null;
        if (this.passwordMode === 'password') {
            var passwordInput = this.popup.querySelector('#nc-auth-register-password');
            password = passwordInput ? passwordInput.value : '';

            if (password.length < 6) {
                this.showError('password', window.ncAuthLang.passwordTooShort || 'Минимум 6 символов');
                if (passwordInput) passwordInput.focus();
                return;
            }
        }

        // Проверка согласия на обработку ПД
        var consentPDRequired = !!(window.NcAuth && window.NcAuth._consentPDText);
        var consentPDGiven = consentPDCheckbox ? consentPDCheckbox.checked : false;
        if (consentPDRequired && !consentPDGiven) {
            this.showError('consentPD', window.ncAuthLang.acceptConsentPD || 'Необходимо дать согласие на обработку персональных данных');
            return;
        }

        // Проверка согласия с правилами сайта
        var consentRulesRequired = !!(window.NcAuth && window.NcAuth._consentRulesText);
        var consentRulesGiven = consentRulesCheckbox ? consentRulesCheckbox.checked : false;
        if (consentRulesRequired && !consentRulesGiven) {
            this.showError('consentRules', window.ncAuthLang.acceptConsentRules || 'Необходимо принять правила сайта');
            return;
        }

        this.setLoading(true);
        this.clearErrors();

        // Собираем данные полей
        var fieldsData = this.collectFieldsData();

        var requestData = {
            email: this.email,
            agreed_pd: consentPDRequired ? consentPDGiven : true,
            agreed_rules: consentRulesRequired ? consentRulesGiven : true,
            consent_pd_required: consentPDRequired,
            consent_rules_required: consentRulesRequired,
            fields: fieldsData,
            email_verified: emailVerified && emailVerified.value === '1'
        };

        // Добавляем пароль если есть
        if (password !== null) {
            requestData.password = password;
        }

        // Данные капчи (если показана на экране регистрации)
        this.withCaptcha(requestData);

        var self = this;
        this.apiRequest('register', requestData, function(response) {
            self.setLoading(false);

            if (self.handleCaptchaResponse(response)) {
                return;
            }

            if (response.success) {
                if (response.need_confirmation) {
                    // Требуется подтверждение email
                    if (response.code_sent) {
                        self.setState('code_input');
                        self.startResendTimer(60);
                    } else {
                        // Код не отправился - показываем сообщение и даём повторить
                        self.showRegistrationPending(response.message);
                    }
                } else if (response.need_premoderation) {
                    // Требуется премодерация - показываем сообщение
                    self.showRegistrationPending(response.message);
                } else if (response.auto_authorized) {
                    // Автоматически авторизован - предлагаем установить пароль
                    self.setState('set_password');
                } else {
                    // Регистрация без автоавторизации - показываем успех
                    self.completeAuth();
                }
            } else {
                // Показываем ошибку для конкретного поля если указано
                if (response.field) {
                    self.showError(response.field, response.message);
                } else {
                    self.showError('register', response.message);
                }
            }
        });
    };

    /**
     * Обработка установки пароля
     */
    NcAuthPopup.prototype.handleSetPasswordSubmit = function() {
        var passwordInput = this.popup.querySelector('#nc-auth-new-password');
        var password = passwordInput.value;

        if (password.length < 6) {
            this.showError('new_password', window.ncAuthLang.passwordTooShort || 'Минимум 6 символов');
            return;
        }

        this.setLoading(true);
        this.clearErrors();

        var self = this;

        // Если это сброс пароля - используем reset_password API
        if (this.isPasswordReset && this.resetCode) {
            this.apiRequest('reset_password', {
                email: this.email,
                code: this.resetCode,
                password: password
            }, function(response) {
                self.setLoading(false);

                if (response.success) {
                    // Сбрасываем флаги
                    self.isPasswordReset = false;
                    self.resetCode = null;
                    self.completeAuth(response.user);
                } else {
                    self.showError('new_password', response.message);
                }
            });
            return;
        }

        // Обычная установка пароля (после входа по коду)
        this.apiRequest('set_password', {
            password: password
        }, function(response) {
            self.setLoading(false);

            if (response.success) {
                self.completeAuth();
            } else {
                self.showError('new_password', response.message);
            }
        });
    };

    /**
     * Завершение авторизации
     */
    NcAuthPopup.prototype.completeAuth = function(user) {
        this.setState('success');

        // Обновляем виджеты авторизации на странице
        this.updateAuthWidgets(user);

        // Отправляем событие для сторонних компонентов
        try {
            document.dispatchEvent(new CustomEvent('nc:auth:login', {
                detail: { user: user }
            }));
        } catch (e) {
            // Fallback для старых браузеров
            var event = document.createEvent('CustomEvent');
            event.initCustomEvent('nc:auth:login', true, true, { user: user });
            document.dispatchEvent(event);
        }

        var self = this;

        // Если есть callback onSuccess — вызываем его и не перезагружаем страницу
        if (typeof this.options.onSuccess === 'function') {
            try {
                this.options.onSuccess(user);
            } catch (e) {
                console.error('NcAuth: Error in onSuccess callback:', e);
            }
            setTimeout(function() {
                self.close();
            }, 1000);
            return;
        }

        // Стандартное поведение — redirect или reload
        setTimeout(function() {
            if (self.options.redirectUrl) {
                window.location.href = self.options.redirectUrl;
            } else {
                self.close();
                window.location.reload();
            }
        }, 1500);
    };

    /**
     * Обновление виджетов авторизации на странице
     */
    NcAuthPopup.prototype.updateAuthWidgets = function(user) {
        var widgets = document.querySelectorAll('[data-nc-auth-widget], .nc-auth-widget--guest');

        widgets.forEach(function(widget) {
            // Меняем класс с guest на auth
            widget.classList.remove('nc-auth-widget--guest');
            widget.classList.add('nc-auth-widget--auth');

            // Убираем onclick
            widget.removeAttribute('onclick');

            // Устанавливаем ссылку на личный кабинет
            var cabinetUrl = widget.getAttribute('data-cabinet-url');
            if (cabinetUrl) {
                widget.href = cabinetUrl;
            }

            // Обновляем текст
            var displayName = user ? (user.Name || user.Email || '') : '';
            var textEl = widget.querySelector('.nc-auth-widget-text');
            var tooltipEl = widget.querySelector('.nc-auth-widget-tooltip');

            if (textEl) textEl.textContent = displayName;
            if (tooltipEl) tooltipEl.textContent = displayName;
        });
    };

    /**
     * Показывает сообщение о том, что регистрация ожидает действия
     * (подтверждение email или премодерация)
     */
    NcAuthPopup.prototype.showRegistrationPending = function(message) {
        var content = this.popup.querySelector('.nc-auth-popup__content');
        if (!content) return;

        content.innerHTML =
            '<div class="nc-auth-success">' +
                '<div class="nc-auth-success__icon" style="background: #f59e0b;">&#9993;</div>' +
                '<h3 class="nc-auth-success__title">' + (window.ncAuthLang.registrationPending || 'Регистрация принята') + '</h3>' +
                '<p class="nc-auth-success__message">' + message + '</p>' +
                '<button type="button" class="nc-auth-btn nc-auth-btn--secondary" style="margin-top: 20px;" onclick="this.closest(\'.nc-auth-popup\').querySelector(\'.nc-auth-popup__close\').click();">' +
                    (window.ncAuthLang.close || 'Закрыть') +
                '</button>' +
            '</div>';
    };

    /**
     * Запуск таймера повторной отправки
     */
    NcAuthPopup.prototype.startResendTimer = function(seconds) {
        var self = this;
        var resendBtn = this.popup.querySelector('#nc-auth-resend-code');
        var timerSpan = this.popup.querySelector('#nc-auth-resend-timer');

        this.resendTimer = seconds;
        resendBtn.classList.add('disabled');

        this.clearResendTimer();

        var updateTimer = function() {
            if (timerSpan) {
                timerSpan.textContent = self.resendTimer > 0 ? ' (' + self.resendTimer + ')' : '';
            }

            if (self.resendTimer <= 0) {
                resendBtn.classList.remove('disabled');
                self.clearResendTimer();
            } else {
                self.resendTimer--;
            }
        };

        updateTimer();
        this.resendInterval = setInterval(updateTimer, 1000);
    };

    /**
     * Очистка таймера
     */
    NcAuthPopup.prototype.clearResendTimer = function() {
        if (this.resendInterval) {
            clearInterval(this.resendInterval);
            this.resendInterval = null;
        }
    };

    /**
     * Загрузка списка OAuth провайдеров
     */
    NcAuthPopup.prototype.loadOAuthProviders = function() {
        var self = this;

        this.apiRequest('get_oauth_providers', {}, function(response) {
            if (response.success && response.providers && response.providers.length > 0) {
                self.oauthProviders = response.providers;
                self.renderOAuthButtons();
            } else {
                // Нет провайдеров - скрываем блок OAuth и разделитель
                self.hideOAuthSection();
            }
        });
    };

    /**
     * Рендеринг кнопок OAuth провайдеров
     */
    NcAuthPopup.prototype.renderOAuthButtons = function() {
        var container = this.popup.querySelector('.nc-auth-oauth');
        if (!container || !this.oauthProviders) return;

        var html = '';
        var self = this;

        for (var i = 0; i < this.oauthProviders.length; i++) {
            var provider = this.oauthProviders[i];
            html += '<button type="button" class="nc-auth-oauth-btn nc-auth-oauth-btn--' + provider.id + '" ' +
                    'data-provider="' + provider.id + '">' +
                    '<span class="nc-auth-oauth-btn__icon">' + this.getProviderIcon(provider.id) + '</span>' +
                    '<span class="nc-auth-oauth-btn__text">' + provider.name + '</span>' +
                    '</button>';
        }

        container.innerHTML = html;

        // Показываем разделитель
        var divider = this.popup.querySelector('.nc-auth-divider');
        if (divider) divider.style.display = '';

        // Привязываем события
        var buttons = container.querySelectorAll('.nc-auth-oauth-btn');
        buttons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                var providerId = btn.getAttribute('data-provider');
                self.startOAuth(providerId);
            });
        });
    };

    /**
     * Скрытие секции OAuth
     */
    NcAuthPopup.prototype.hideOAuthSection = function() {
        var container = this.popup.querySelector('.nc-auth-oauth');
        var divider = this.popup.querySelector('.nc-auth-divider');

        if (container) container.style.display = 'none';
        if (divider) divider.style.display = 'none';
    };

    /**
     * Начало OAuth авторизации
     */
    NcAuthPopup.prototype.startOAuth = function(providerId) {
        var redirectTo = this.options.redirectUrl || window.location.href;
        var authorizeUrl = '/netcat/modules/auth/api/authorize.php?provider=' + encodeURIComponent(providerId) +
                          '&redirect_to=' + encodeURIComponent(redirectTo);

        window.location.href = authorizeUrl;
    };

    /**
     * Получение SVG иконки провайдера
     */
    NcAuthPopup.prototype.getProviderIcon = function(providerId) {
        var icons = {
            yandex: '<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" fill="currentColor"/><path fill="#fff" d="M13.6 7h-1.8c-1.8 0-3 1.2-3 2.9 0 1.3.6 2.2 1.7 3L8 17h2l2.5-4.4h.2V17H15V7h-1.4zm-.8 4.3c-.8 0-1.2-.5-1.2-1.4 0-.9.5-1.4 1.2-1.4h.8v2.8h-.8z"/></svg>',
            vkid: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm5.01 14h-1.64c-.62 0-.81-.49-1.92-1.64-.97-.93-1.37-1.05-1.61-1.05-.33 0-.43.1-.43.56v1.5c0 .4-.13.63-1.15.63-1.7 0-3.58-1.03-4.9-2.96C4.16 10.91 3.5 9.03 3.5 8.6c0-.24.1-.46.56-.46h1.64c.42 0 .58.19.74.63.81 2.34 2.16 4.39 2.72 4.39.21 0 .3-.1.3-.63V10.5c-.06-1.11-.65-1.21-.65-1.61 0-.19.16-.38.42-.38h2.57c.35 0 .48.19.48.6v2.71c0 .35.16.48.26.48.21 0 .38-.13.77-.52 1.18-1.33 2.03-3.38 2.03-3.38.11-.24.3-.46.72-.46h1.64c.49 0 .6.25.49.6-.2.93-2.21 3.79-2.21 3.79-.17.29-.24.42 0 .74.17.24.74.74 1.12 1.19.71.81 1.25 1.49 1.39 1.96.16.46-.08.7-.54.7z"/></svg>',
            google: '<svg viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/></svg>',
            telegram: '<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69a.2.2 0 00-.05-.18c-.06-.05-.14-.03-.21-.02-.09.02-1.49.95-4.22 2.79-.4.27-.76.41-1.08.4-.36-.01-1.04-.2-1.55-.37-.63-.2-1.12-.31-1.08-.66.02-.18.27-.36.74-.55 2.92-1.27 4.86-2.11 5.83-2.51 2.78-1.16 3.35-1.36 3.73-1.36.08 0 .27.02.39.12.1.08.13.19.14.27-.01.06.01.24 0 .38z"/></svg>'
        };

        return icons[providerId] || '';
    };

    /**
     * Валидация email
     */
    NcAuthPopup.prototype.validateEmail = function(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    };

    /**
     * Установка состояния загрузки
     */
    NcAuthPopup.prototype.setLoading = function(loading) {
        this.isLoading = loading;
        this.popup.classList.toggle('loading', loading);

        var buttons = this.popup.querySelectorAll('button[type="submit"]');
        buttons.forEach(function(btn) {
            btn.disabled = loading;
        });
    };

    /**
     * Показ ошибки
     */
    NcAuthPopup.prototype.showError = function(field, message) {
        var errorEl = this.popup.querySelector('[data-error="' + field + '"]');
        if (errorEl) {
            errorEl.textContent = message;
            errorEl.style.display = 'block';
        }
    };

    /**
     * Очистка ошибок
     */
    NcAuthPopup.prototype.clearErrors = function() {
        var errors = this.popup.querySelectorAll('[data-error]');
        errors.forEach(function(el) {
            el.textContent = '';
            el.style.display = 'none';
        });

        var attemptsMsg = this.popup.querySelector('.nc-auth-attempts-left');
        if (attemptsMsg) attemptsMsg.style.display = 'none';
    };

    /**
     * Вставляет HTML капчи в слот экрана и исполняет его скрипты.
     * innerHTML не исполняет <script>, поэтому пересоздаём script-узлы.
     * Внешний загрузчик капчи (captcha.js) грузим один раз на страницу.
     * @param {string} html  HTML виджета капчи (от сервера)
     * @param {string} [screenName]  целевой экран; по умолчанию — активный
     */
    NcAuthPopup.prototype.renderCaptcha = function(html, screenName) {
        var screen = screenName
            ? this.popup.querySelector('[data-screen="' + screenName + '"]')
            : this.popup.querySelector('.nc-auth-screen.active');
        if (!screen) return;
        var slot = screen.querySelector('.nc-auth-captcha-slot');
        if (!slot) return;

        // Очищаем слоты других экранов, чтобы не плодить виджеты
        var allSlots = this.popup.querySelectorAll('.nc-auth-captcha-slot');
        allSlots.forEach(function(s) { if (s !== slot) s.innerHTML = ''; });

        if (!html) { slot.innerHTML = ''; return; }

        slot.innerHTML = html;

        var scripts = slot.querySelectorAll('script');
        for (var i = 0; i < scripts.length; i++) {
            var old = scripts[i];
            if (old.src) {
                if (!document.querySelector('script[data-nc-captcha-src="' + old.src + '"]')) {
                    var ext = document.createElement('script');
                    ext.src = old.src;
                    ext.async = true;
                    ext.setAttribute('data-nc-captcha-src', old.src);
                    document.head.appendChild(ext);
                }
            } else {
                var inl = document.createElement('script');
                inl.text = old.textContent;
                document.head.appendChild(inl);
                if (inl.parentNode) inl.parentNode.removeChild(inl);
            }
            if (old.parentNode) old.parentNode.removeChild(old);
        }

        // Если загрузчик уже был подключён, его onload повторно не сработает —
        // рендерим виджет вручную.
        setTimeout(function() {
            if (window.smartCaptcha && typeof window.nc_yacaptcha_render === 'function') {
                window.nc_yacaptcha_render();
            }
            if (window.grecaptcha && typeof window.nc_recaptcha_render === 'function') {
                window.nc_recaptcha_render();
            }
        }, 50);
    };

    /**
     * Считывает ответ капчи из активного экрана: токен (nc_captcha_code) и
     * хэш (nc_captcha_hash — для встроенной символьной капчи).
     */
    NcAuthPopup.prototype.getCaptchaData = function() {
        var screen = this.popup.querySelector('.nc-auth-screen.active');
        var token = '', hash = '';
        if (screen) {
            var codeEl = screen.querySelector('[name="nc_captcha_code"]');
            var hashEl = screen.querySelector('[name="nc_captcha_hash"]');
            if (codeEl) token = codeEl.value || '';
            if (hashEl) hash = hashEl.value || '';
        }
        return { token: token, hash: hash };
    };

    /**
     * Добавляет данные капчи в объект запроса.
     */
    NcAuthPopup.prototype.withCaptcha = function(data) {
        var cap = this.getCaptchaData();
        data.captcha_token = cap.token;
        data.captcha_hash = cap.hash;
        return data;
    };

    /**
     * Обрабатывает ответ сервера, требующий капчу. Возвращает true, если ответ
     * был «капча-ответом» (отрисована капча / показана ошибка).
     */
    NcAuthPopup.prototype.handleCaptchaResponse = function(response) {
        if (!response || (response.error !== 'captcha_required' && response.error !== 'captcha_wrong')) {
            return false;
        }
        if (response.captcha_html) {
            this.renderCaptcha(response.captcha_html);
        }
        if (response.error === 'captcha_wrong') {
            this.showError('captcha', response.message || (window.ncAuthLang && window.ncAuthLang.captchaWrong) || '');
        }
        return true;
    };

    /**
     * API запрос
     */
    NcAuthPopup.prototype.apiRequest = function(action, data, callback) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', API_URL, true);
        xhr.withCredentials = true; // Передаём cookies для сессии
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.timeout = 30000; // 30 секунд таймаут

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                var response;
                if (xhr.status === 0) {
                    // Сетевая ошибка или отмена
                    response = { success: false, error: 'network_error', message: 'Ошибка сети' };
                } else {
                    try {
                        response = JSON.parse(xhr.responseText);
                    } catch (e) {
                        response = { success: false, error: 'parse_error', message: 'Ошибка сервера' };
                    }
                }
                callback(response);
            }
        };

        xhr.onerror = function() {
            callback({ success: false, error: 'network_error', message: 'Ошибка сети' });
        };

        xhr.ontimeout = function() {
            callback({ success: false, error: 'timeout', message: 'Превышено время ожидания' });
        };

        data.action = action;
        xhr.send(JSON.stringify(data));
    };

    // Глобальный интерфейс
    window.NcAuth = {
        _instance: null,
        _blockId: null,
        _consentText: '',

        /**
         * Установка ID блока для применения слотов миксинов
         * Вызывается компонентом netcat_module_auth_popup
         * @param {number} blockId - ID инфоблока (Sub_Class_ID)
         */
        setBlockId: function(blockId) {
            this._blockId = blockId;
            // Если попап уже создан, добавляем классы
            if (this._instance && this._instance.popup) {
                this._applyBlockClasses(this._instance.popup);
            }
        },

        /**
         * Установка текста согласия
         * Вызывается компонентом netcat_module_auth_popup
         * @param {string} text - HTML-текст согласия (если пусто - галочка не показывается)
         */
        /**
         * @deprecated Используйте setConsentPDText. Оставлено для обратной совместимости.
         */
        setConsentText: function(text) {
            this.setConsentPDText(text);
        },

        setConsentPDText: function(text) {
            this._consentPDText = text || '';
            if (this._instance && this._instance.popup) {
                this._applyConsentTexts(this._instance.popup);
            }
        },

        setConsentRulesText: function(text) {
            this._consentRulesText = text || '';
            if (this._instance && this._instance.popup) {
                this._applyConsentTexts(this._instance.popup);
            }
        },

        /**
         * Применение текстов согласия к попапу
         * @param {Element} popup - элемент попапа
         */
        _applyConsentTexts: function(popup) {
            if (!popup) return;

            var pdBlock = popup.querySelector('.nc-auth-consent-pd');
            var pdText = pdBlock ? pdBlock.querySelector('.nc-auth-consent__text') : null;
            if (pdBlock && pdText) {
                if (this._consentPDText) {
                    pdText.innerHTML = this._consentPDText;
                    pdBlock.style.display = '';
                } else {
                    pdBlock.style.display = 'none';
                }
            }

            var rulesBlock = popup.querySelector('.nc-auth-consent-rules');
            var rulesText = rulesBlock ? rulesBlock.querySelector('.nc-auth-consent__text') : null;
            if (rulesBlock && rulesText) {
                if (this._consentRulesText) {
                    rulesText.innerHTML = this._consentRulesText;
                    rulesBlock.style.display = '';
                } else {
                    rulesBlock.style.display = 'none';
                }
            }
        },

        /**
         * Применение классов блока к элементу попапа
         * @param {Element} popup - элемент попапа
         */
        _applyBlockClasses: function(popup) {
            if (!popup) return;

            // Копируем классы tpl-body-site-X и tpl-body-sub-X с body для наследования CSS переменных палитры
            var bodyClasses = document.body.className.match(/tpl-body-(?:site|sub)-\d+/g);
            if (bodyClasses) {
                bodyClasses.forEach(function(cls) {
                    popup.classList.add(cls);
                });
            }

            // Также добавляем класс tpl-styled если он есть на body
            if (document.body.classList.contains('tpl-styled')) {
                popup.classList.add('tpl-styled');
            }

            // Добавляем классы для применения слотов миксинов
            if (this._blockId) {
                popup.classList.add('tpl-block-' + this._blockId);
                popup.classList.add('tpl-block-full-' + this._blockId);
            }
        },

        /**
         * Получение или создание экземпляра
         */
        getInstance: function(options) {
            if (!this._instance) {
                this._instance = new NcAuthPopup(options);
            }
            return this._instance;
        },

        /**
         * Открытие попапа
         */
        open: function(options) {
            var instance = this.getInstance(options);
            // Применяем классы блока перед открытием
            if (instance.popup) {
                this._applyBlockClasses(instance.popup);
            }
            instance.open();
        },

        /**
         * Открытие с email
         * @param {string} email - Email адрес
         * @param {Object} options - Дополнительные опции (onSuccess, onClose, context)
         */
        openWithEmail: function(email, options) {
            var instance = this.getInstance(options);
            // Применяем классы блока перед открытием
            if (instance.popup) {
                this._applyBlockClasses(instance.popup);
            }
            instance.openWithEmail(email, options);
        },

        /**
         * Закрытие попапа
         */
        close: function() {
            if (this._instance) {
                this._instance.close();
            }
        }
    };

    // Языковые строки по умолчанию
    window.ncAuthLang = window.ncAuthLang || {
        invalidEmail: 'Некорректный email',
        enterPassword: 'Введите пароль',
        enterFullCode: 'Введите полный код',
        acceptAgreement: 'Необходимо принять соглашение',
        passwordTooShort: 'Минимум 6 символов',
        passwordsMismatch: 'Пароли не совпадают',
        attemptsLeft: 'Осталось попыток: ',
        password: 'Пароль',
        passwordConfirm: 'Подтверждение пароля',
        passwordPlaceholder: 'Минимум 6 символов',
        registrationPending: 'Регистрация принята',
        close: 'Закрыть',
        fieldRequired: 'Поле обязательно',
        newPassword: 'Новый пароль',
        enterNewPassword: 'Придумайте новый пароль',
        setPasswordTitle: 'Установите пароль',
        setPasswordSubtitle: 'Это позволит входить быстрее в будущем',
        resetPasswordTitle: 'Новый пароль',
        resetPasswordSubtitle: 'Придумайте новый пароль',
        captchaWrong: 'Подтвердите, что вы не робот'
    };

    /**
     * Проверка URL параметров и автоматическое открытие попапа
     * Вызывается при загрузке страницы
     */
    function checkUrlAction() {
        var urlParams = new URLSearchParams(window.location.search);
        var action = urlParams.get('nc_auth_action');
        var fromRecovery = urlParams.get('from_recovery') === '1';

        if (action === 'set_password') {
            // Очищаем параметры из URL (для красоты)
            var newUrl = window.location.pathname;
            var otherParams = [];
            urlParams.forEach(function(value, key) {
                if (key !== 'nc_auth_action' && key !== 'from_recovery') {
                    otherParams.push(key + '=' + encodeURIComponent(value));
                }
            });
            if (otherParams.length > 0) {
                newUrl += '?' + otherParams.join('&');
            }
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, '', newUrl);
            }

            // Открываем попап на экране установки пароля
            var instance = window.NcAuth.getInstance();

            // Ждём загрузки шаблона
            var waitForTemplate = function() {
                if (!instance.templateLoaded) {
                    setTimeout(waitForTemplate, 100);
                    return;
                }

                instance.popup.classList.add('active');
                document.body.classList.add('nc-auth-popup-open');

                // Переходим сразу на экран set_password
                instance.setState('set_password');

                // Настраиваем заголовки в зависимости от контекста
                var title = instance.popup.querySelector('[data-screen="set_password"] .nc-auth-popup__title');
                var subtitle = instance.popup.querySelector('[data-screen="set_password"] .nc-auth-popup__subtitle');
                var skipBtn = instance.popup.querySelector('#nc-auth-skip-password');

                if (fromRecovery) {
                    // После сброса пароля - пароль обязателен
                    if (title) title.textContent = window.ncAuthLang.resetPasswordTitle || 'Новый пароль';
                    if (subtitle) subtitle.textContent = window.ncAuthLang.resetPasswordSubtitle || 'Придумайте новый пароль';
                    if (skipBtn) skipBtn.style.display = 'none';
                    instance.isPasswordReset = false; // Уже авторизован, просто устанавливаем пароль
                } else {
                    // После регистрации/входа - можно пропустить
                    if (title) title.textContent = window.ncAuthLang.setPasswordTitle || 'Установите пароль';
                    if (subtitle) subtitle.textContent = window.ncAuthLang.setPasswordSubtitle || 'Это позволит входить быстрее в будущем';
                    if (skipBtn) skipBtn.style.display = '';
                }

                // Фокус на поле пароля
                var passwordInput = instance.popup.querySelector('#nc-auth-new-password');
                if (passwordInput) {
                    setTimeout(function() { passwordInput.focus(); }, 100);
                }
            };

            waitForTemplate();
        }
    }

    // Запускаем проверку при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkUrlAction);
    } else {
        checkUrlAction();
    }

})(window, document);
