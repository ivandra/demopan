(function (params) {
    var element = params.block_element;

    // Убираем класс видимости для сброса эффекта
    element.classList.remove('tpl-mixin-effect-visible');

    // Также для списка (stagger)
    var listElement = element.querySelector('.tpl-block-list');
    if (listElement) {
        listElement.classList.remove('tpl-mixin-effect-visible');
    }
});
