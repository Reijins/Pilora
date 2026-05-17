(function () {
  'use strict';

  var toggle = document.querySelector('[data-marketing-nav-toggle]');
  var panel = document.querySelector('[data-marketing-nav-panel]');
  if (!toggle || !panel) {
    return;
  }

  toggle.addEventListener('click', function () {
    var open = panel.classList.toggle('is-open');
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  document.addEventListener('click', function (e) {
    if (!panel.classList.contains('is-open')) {
      return;
    }
    if (panel.contains(e.target) || toggle.contains(e.target)) {
      return;
    }
    panel.classList.remove('is-open');
    toggle.setAttribute('aria-expanded', 'false');
  });
})();
