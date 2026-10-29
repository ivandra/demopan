/**
 * Отрисовывает Яндекс SmartCaptcha во всех формах на странице, где включена капча.
 */
function nc_yacaptcha_render() {
    var captchas = document.querySelectorAll(".smart-captcha"),
        smartCaptcha = window.smartCaptcha;

    if (smartCaptcha && smartCaptcha.render) {
        for (var i = 0; i < captchas.length; i++) {
            if (!captchas[i].children.length) {
                var attrs = captchas[i].attributes,
                    params = {};
                for (var j = 0; j < attrs.length; j++) {
                    var match = attrs[j].name.match(/^data-(.+)/);
                    if (match) {
                        params[match[1]] = attrs[j].value;
                    }
                }
                smartCaptcha.render(captchas[i], params);
            }
        }
    }
}

if (document.addEventListener) {
    document.addEventListener("DOMContentLoaded", nc_yacaptcha_render);
}

/**
 * Сохраняет значение токена SmartCaptcha в nc_captcha_code
 * (для обратной совместимости со встроенной каптчей Netcat)
 */
function nc_yacaptcha_save(token) {
    var forms = document.forms,
        smartField = "smart-token",
        netcatField = "nc_captcha_code";

    for (var i = 0; i < forms.length; i++) {
        var formElements = forms[i].elements;
        if (smartField in formElements) {
            var tokenValue = token || formElements[smartField].value;
            if (!(netcatField in formElements)) {
                var input = document.createElement("input"),
                    attributes = {
                        type: "hidden",
                        name: netcatField,
                        value: tokenValue
                    };
                for (var a in attributes) {
                    input.setAttribute(a, attributes[a]);
                }
                forms[i].appendChild(input);
            } else {
                formElements[netcatField].value = tokenValue;
            }
        }
    }
}
