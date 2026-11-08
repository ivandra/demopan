(function () {
    'use strict';
    function digits(value) {
        var number = value.replace(/\D/g, '');
        if (/^\s*\+7/.test(value) || (number.length > 10 && /^[78]/.test(number))) number = number.slice(1);
        return number.slice(0, 10);
    }
    function format(number) {
        if (!number) return '';
        var value = '+7(' + number.slice(0, 3);
        if (number.length >= 3) value += ')';
        if (number.length > 3) value += number.slice(3, 6);
        if (number.length > 6) value += '-' + number.slice(6, 8);
        if (number.length > 8) value += '-' + number.slice(8, 10);
        return value;
    }
    function validate(input) {
        if (input.type === 'tel') {
            input.setCustomValidity(input.value && !/^\+7\([0-9]{3}\)[0-9]{3}-[0-9]{2}-[0-9]{2}$/.test(input.value) ? 'Введите номер полностью: +7(999)999-99-99.' : '');
        } else {
            input.setCustomValidity(input.value && !/^[^\s@]+@[^\s@.]+(?:\.[^\s@.]+)+$/.test(input.value) ? 'Введите email в формате name@example.ru.' : '');
        }
    }
    function ready() {
        document.querySelectorAll('form[data-zhp-form] input[type="tel"]').forEach(function (input) {
            input.autocomplete = 'tel';
            input.inputMode = 'tel';
            input.placeholder = '+7(___)___-__-__';
            input.setAttribute('pattern', '\\+7\\([0-9]{3}\\)[0-9]{3}-[0-9]{2}-[0-9]{2}');
            function update() {
                var before = input.value, caret = input.selectionStart;
                var count = digits(before.slice(0, caret)).length;
                input.value = format(digits(before));
                if (document.activeElement === input && caret !== null && caret < before.length) {
                    var position = 3;
                    while (position < input.value.length && count > 0) {
                        if (/\d/.test(input.value[position])) count--;
                        position++;
                    }
                    input.setSelectionRange(position, position);
                }
                validate(input);
            }
            input.addEventListener('input', update);
            input.addEventListener('change', update);
            input.addEventListener('keydown', function (event) {
                if (!['Backspace', 'Delete'].includes(event.key) || input.selectionStart !== input.selectionEnd) return;
                var pos = input.selectionStart + (event.key === 'Backspace' ? -1 : 0);
                if (pos < 3 || /\d/.test(input.value[pos] || '')) return;
                event.preventDefault();
                var step = event.key === 'Backspace' ? -1 : 1;
                while (pos >= 3 && pos < input.value.length && !/\d/.test(input.value[pos])) pos += step;
                if (pos >= 3 && pos < input.value.length) {
                    input.value = input.value.slice(0, pos) + input.value.slice(pos + 1);
                    input.setSelectionRange(pos, pos);
                    update();
                }
            });
            update();
        });
        document.querySelectorAll('form[data-zhp-form] input[type="email"]').forEach(function (input) {
            input.autocomplete = 'email';
            input.inputMode = 'email';
            input.spellcheck = false;
            input.addEventListener('input', function () { input.value = input.value.replace(/\s/g, ''); validate(input); });
            input.addEventListener('change', function () { input.value = input.value.trim(); validate(input); });
            validate(input);
        });
        document.querySelectorAll('form[data-zhp-form]').forEach(function (form) {
            form.addEventListener('reset', function () {
                setTimeout(function () { form.querySelectorAll('input[type="tel"],input[type="email"]').forEach(validate); }, 0);
            });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready); else ready();
}());
