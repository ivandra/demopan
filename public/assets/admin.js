(function () {
  function qs(selector, root) {
    return (root || document).querySelector(selector);
  }

  function qsa(selector, root) {
    return Array.prototype.slice.call((root || document).querySelectorAll(selector));
  }

  document.addEventListener('submit', function (e) {
    var form = e.target.closest('form');
    if (!form) return;

    if (form.dataset.confirmAccepted === '1') {
      form.dataset.confirmAccepted = '';
      return;
    }

    if (form.hasAttribute('data-confirm')) {
      var message = form.getAttribute('data-confirm') || 'Подтвердить действие?';
      if (!window.confirm(message)) {
        e.preventDefault();
      }
    }
  });

  document.addEventListener('click', function (e) {
    var target = e.target.closest('[data-confirm], [data-check-all], [data-check-none], [data-require-checked]');
    if (!target) return;

    if (target.hasAttribute('data-check-all')) {
      var selectorAll = target.getAttribute('data-check-all');
      qsa(selectorAll).forEach(function (el) {
        el.checked = true;
      });
      e.preventDefault();
      return;
    }

    if (target.hasAttribute('data-check-none')) {
      var selectorNone = target.getAttribute('data-check-none');
      qsa(selectorNone).forEach(function (el) {
        el.checked = false;
      });
      e.preventDefault();
      return;
    }

    if (target.hasAttribute('data-require-checked')) {
      var selectorChecked = target.getAttribute('data-require-checked');
      var checkedCount = qsa(selectorChecked).filter(function (el) {
        return !!el.checked;
      }).length;

      if (!checkedCount) {
        alert(target.getAttribute('data-require-checked-message') || 'Сначала выберите хотя бы один пункт.');
        e.preventDefault();
        return;
      }
    }

    if (target.hasAttribute('data-confirm')) {
      var parentForm = target.closest('form');
      if (parentForm && parentForm.hasAttribute('data-confirm')) {
        return;
      }

      var message = target.getAttribute('data-confirm') || 'Подтвердить действие?';
      if (!window.confirm(message)) {
        e.preventDefault();
        return;
      }

      if (parentForm) {
        parentForm.dataset.confirmAccepted = '1';
      }
    }
  });

  document.addEventListener('change', function (e) {
    var fillTarget = e.target.closest('[data-fill-target]');
    if (fillTarget) {
      var target = qs(fillTarget.getAttribute('data-fill-target'));
      if (target && fillTarget.value) {
        target.value = fillTarget.value;
      }
    }

    var paramSelect = e.target.closest('select[data-set-query-param]');
    if (paramSelect) {
      var paramName = paramSelect.getAttribute('data-set-query-param');
      if (!paramName) return;

      var url = new URL(window.location.href);
      url.searchParams.set(paramName, paramSelect.value);
      window.location.href = url.toString();
      return;
    }

    var toggleGroup = e.target.closest('[data-toggle-check-group]');
    if (toggleGroup) {
      var selector = toggleGroup.getAttribute('data-toggle-check-group');
      qsa(selector).forEach(function (el) {
        el.checked = !!toggleGroup.checked;
      });
    }
  });

  document.addEventListener('input', function (e) {
    var filterInput = e.target.closest('input[data-filter-options]');
    if (!filterInput) return;

    var targetSelector = filterInput.getAttribute('data-filter-options');
    var select = qs(targetSelector);
    if (!select) return;

    var q = (filterInput.value || '').toLowerCase().trim();

    qsa('option', select).forEach(function (opt) {
      var v = (opt.value || '').toLowerCase();
      var t = (opt.textContent || '').toLowerCase();
      opt.hidden = !!q && v.indexOf(q) === -1 && t.indexOf(q) === -1;
    });
  });
})();