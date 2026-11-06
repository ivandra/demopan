(function () {
    'use strict';
    if (window.lifeNativeEditor) { window.lifeNativeEditor(document); return; }
    function init(root) {
        const widgets = [];
        if (root.matches && root.matches('[data-education-editor]')) widgets.push(root);
        if (root.querySelectorAll) widgets.push(...root.querySelectorAll('[data-education-editor]'));
        widgets.forEach(function (widget) {
            if (widget.dataset.initialized) return;
            widget.dataset.initialized = '1';
            const store = widget.querySelector('[data-education-store]');
            const rows = widget.querySelector('[data-education-rows]');
            function sync() {
                store.value = JSON.stringify(Array.from(rows.querySelectorAll('[data-education-row]')).map(function (row) {
                    const value = {};
                    row.querySelectorAll('[data-education-field]').forEach(function (input) { value[input.dataset.educationField] = input.value.trim(); });
                    return value;
                }).filter(function (row) { return Object.values(row).some(Boolean); }));
            }
            widget.addEventListener('input', sync);
            widget.addEventListener('click', function (event) {
                if (event.target.closest('[data-education-add]')) {
                    rows.appendChild(widget.querySelector('template').content.cloneNode(true));
                    rows.lastElementChild.querySelector('input').focus();
                    sync();
                }
                if (event.target.closest('[data-education-remove]')) {
                    event.target.closest('[data-education-row]').remove();
                    sync();
                }
            });
            if (widget.closest('form')) widget.closest('form').addEventListener('submit', sync);
            sync();
        });
    }
    window.lifeNativeEditor = init;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', function () { init(document); });
    else init(document);
    new MutationObserver(function (changes) { changes.forEach(function (change) { change.addedNodes.forEach(function (node) { if (node.nodeType === 1) init(node); }); }); }).observe(document.documentElement, {childList: true, subtree: true});
}());
