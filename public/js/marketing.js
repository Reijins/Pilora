(function () {
  'use strict';

  var toggle = document.querySelector('[data-marketing-nav-toggle]');
  var panel = document.querySelector('[data-marketing-nav-panel]');
  if (toggle && panel) {
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
  }

  var billingSwitch = document.getElementById('billing_cycle_switch');
  var billingInput = document.getElementById('billing_cycle');
  var billingLabels = document.querySelectorAll('[data-billing-label]');
  var packCards = document.querySelectorAll('.marketing-signup-pack-card');
  var priceBlocks = document.querySelectorAll('.marketing-signup-pack-card__price');

  function formatPrice(n) {
    return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  function setBillingCycle(isAnnual) {
    if (billingInput) {
      billingInput.value = isAnnual ? 'annual' : 'monthly';
    }
    if (billingSwitch) {
      billingSwitch.setAttribute('aria-checked', isAnnual ? 'true' : 'false');
    }
    billingLabels.forEach(function (lbl) {
      var cycle = lbl.getAttribute('data-billing-label');
      lbl.classList.toggle('is-active', (cycle === 'annual') === isAnnual);
    });
    priceBlocks.forEach(function (block) {
      if (block.getAttribute('data-trial') === '1') {
        return;
      }
      var monthly = parseInt(block.getAttribute('data-monthly') || '0', 10);
      var annual = parseInt(block.getAttribute('data-annual') || '0', 10);
      if (isAnnual) {
        block.innerHTML = '<strong class="marketing-signup-pack-card__amount">' + formatPrice(annual) + ' &euro;</strong><span class="marketing-signup-pack-card__period">/ an</span>';
      } else {
        block.innerHTML = '<strong class="marketing-signup-pack-card__amount">' + formatPrice(monthly) + ' &euro;</strong><span class="marketing-signup-pack-card__period">/ mois</span>';
      }
    });
  }

  if (billingSwitch) {
    billingSwitch.addEventListener('click', function () {
      var isAnnual = billingSwitch.getAttribute('aria-checked') !== 'true';
      setBillingCycle(isAnnual);
    });
  }

  packCards.forEach(function (card) {
    var radio = card.querySelector('input[type="radio"]');
    if (!radio) {
      return;
    }
    radio.addEventListener('change', function () {
      packCards.forEach(function (c) {
        c.classList.remove('is-selected');
      });
      if (radio.checked) {
        card.classList.add('is-selected');
      }
    });
  });
})();
