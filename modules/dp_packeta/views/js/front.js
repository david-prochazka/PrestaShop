/**
 * Packeta Delivery Methods — checkout widget integration.
 *
 * Opens the Packeta widget v6 for carriers linked as "pickup points", saves
 * the chosen point for the cart over AJAX and blocks the delivery step until
 * a point is selected. Everything is bound with event delegation so it
 * survives checkout fragments being re-rendered over AJAX (including by
 * one-page checkout modules).
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
(function () {
  'use strict';

  if (typeof window.dpPacketa === 'undefined') {
    return;
  }

  var config = window.dpPacketa;

  function allContainers() {
    return Array.prototype.slice.call(document.querySelectorAll('.js-dp-packeta'));
  }

  function hasSelection() {
    var containers = allContainers();
    if (!containers.length) {
      // Theme without extra-content containers: let the server-side
      // validation (actionValidateStepComplete) decide.
      return true;
    }

    return containers.every(function (box) {
      return box.dataset.selected === '1';
    });
  }

  function selectedPickupCarrierId() {
    var inputs = document.querySelectorAll('input[name^="delivery_option"]:checked');
    for (var i = 0; i < inputs.length; i += 1) {
      var parts = String(inputs[i].value).split(',');
      for (var j = 0; j < parts.length; j += 1) {
        var id = parseInt(parts[j], 10);
        if (id && config.carriers && config.carriers[id]) {
          return id;
        }
      }
    }

    return 0;
  }

  function showError(visible) {
    allContainers().forEach(function (box) {
      var error = box.querySelector('.js-dp-packeta-error');
      if (error) {
        error.classList.toggle('dp-packeta--hidden', !visible);
      }
    });
  }

  function applySelection(label) {
    allContainers().forEach(function (box) {
      var selected = box.querySelector('.js-dp-packeta-selected');
      if (selected) {
        selected.textContent = label;
        selected.classList.remove('dp-packeta--hidden');
      }
      box.dataset.selected = '1';
      var error = box.querySelector('.js-dp-packeta-error');
      if (error) {
        error.classList.add('dp-packeta--hidden');
      }
    });
  }

  function savePoint(box, point) {
    var body = new URLSearchParams();
    body.set('action', 'save');
    body.set('token', config.token);
    body.set('id_carrier', box.dataset.idCarrier || '0');
    body.set('point', JSON.stringify({
      id: point.id,
      pickupPointType: point.pickupPointType,
      carrierId: point.carrierId,
      carrierPickupPointId: point.carrierPickupPointId,
      name: point.name,
      street: point.street,
      city: point.city,
      zip: point.zip,
      country: point.country,
      url: point.url
    }));

    fetch(config.ajaxUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString(),
      credentials: 'same-origin'
    })
      .then(function (response) { return response.json(); })
      .then(function (data) {
        if (!data.success) {
          throw new Error(data.error || 'save failed');
        }
        applySelection(data.label || point.name);
      })
      .catch(function () {
        window.alert(config.i18n.saveFailed);
      });
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.js-dp-packeta-open');
    if (!button) {
      return;
    }
    event.preventDefault();

    var box = button.closest('.js-dp-packeta');
    if (!box || typeof window.Packeta === 'undefined' || !window.Packeta.Widget) {
      return;
    }

    var options = {};
    try {
      options = JSON.parse(box.dataset.widget || '{}');
    } catch (error) {
      options = {};
    }

    window.Packeta.Widget.pick(config.apiKey, function (point) {
      if (point) {
        savePoint(box, point);
      }
    }, options);
  });

  // Capture-phase guard: block confirming the delivery step while a linked
  // pickup point carrier is selected without a pickup point.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form || form.id !== 'js-delivery') {
      return;
    }

    if (!selectedPickupCarrierId() || hasSelection()) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    showError(true);

    var visible = document.querySelector('.js-dp-packeta');
    if (visible && typeof visible.scrollIntoView === 'function') {
      visible.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }, true);
})();
