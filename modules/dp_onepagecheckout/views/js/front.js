/**
 * One Page Checkout — front office behavior.
 *
 * Strategy: every checkout step form still posts to the native order
 * controller, which validates, persists and re-renders the whole page.
 * We intercept those submits, POST them with fetch() and swap the
 * refreshed fragments (steps, cart summary, notifications) back into the
 * DOM — the customer never leaves the page.
 *
 * The classic theme binds its checkout handlers with event delegation on
 * <body>, so they keep working after the swap.
 *
 * @author    Integritty
 * @copyright 2026 Integritty
 * @license   https://opensource.org/licenses/MIT MIT License
 */
(function () {
  'use strict';

  var config = window.opcConfig || {};
  var SWAP_IDS = ['opc-wrapper', 'js-checkout-summary', 'notifications'];
  var deliveryDebounce = null;

  function onReady(fn) {
    if (document.readyState !== 'loading') {
      fn();
    } else {
      document.addEventListener('DOMContentLoaded', fn);
    }
  }

  function getWrapper() {
    return document.getElementById('opc-wrapper');
  }

  function applyStickySummary() {
    if (!config.stickySummary) {
      return;
    }
    var summary = document.getElementById('js-checkout-summary');
    if (summary) {
      summary.classList.add('opc-sticky-summary-active');
    }
  }

  /**
   * A form takes part in the OPC flow when it posts back to the order
   * page (explicitly or via an empty action). Payment module forms post
   * to their own controllers and are left untouched.
   */
  function isCheckoutForm(form) {
    var action = form.getAttribute('action');
    if (!action || action.charAt(0) === '#') {
      return true;
    }

    try {
      var target = new URL(action, window.location.href);
      var order = new URL(config.orderUrl || window.location.href, window.location.href);

      if (target.origin !== order.origin) {
        return false;
      }
      if (target.pathname !== order.pathname) {
        return false;
      }
      // Legacy URLs: /index.php?controller=order — compare controllers too.
      var targetController = target.searchParams.get('controller');
      var orderController = order.searchParams.get('controller');
      return targetController === orderController;
    } catch (e) {
      return false;
    }
  }

  function setBusy(panel, busy) {
    if (panel) {
      panel.classList.toggle('opc-panel--busy', busy);
    }
  }

  function emitUpdated() {
    if (window.prestashop && typeof window.prestashop.emit === 'function') {
      window.prestashop.emit('opc:updated');
    }
    document.dispatchEvent(new CustomEvent('opc:updated'));
  }

  function swapFromDocument(doc) {
    var incomingWrapper = doc.getElementById('opc-wrapper');
    if (!incomingWrapper) {
      // The server navigated somewhere else (cart, order confirmation,
      // payment redirect…) — follow it with a real navigation.
      return false;
    }

    SWAP_IDS.forEach(function (id) {
      var current = document.getElementById(id);
      var incoming = doc.getElementById(id);
      if (current && incoming) {
        current.replaceWith(incoming);
      }
    });

    applyStickySummary();
    emitUpdated();
    return true;
  }

  function submitForm(form, submitter) {
    var panel = form.closest('.opc-panel');
    var body = new FormData(form);

    if (submitter && submitter.name) {
      body.append(submitter.name, submitter.value || '');
    }

    setBusy(panel, true);

    fetch(form.getAttribute('action') || window.location.href, {
      method: 'POST',
      body: body,
      credentials: 'same-origin',
      headers: { Accept: 'text/html' },
    })
      .then(function (response) {
        return response.text().then(function (html) {
          return { url: response.url, html: html };
        });
      })
      .then(function (result) {
        var doc = new DOMParser().parseFromString(result.html, 'text/html');
        if (!swapFromDocument(doc)) {
          window.location.href = result.url;
        }
      })
      .catch(function () {
        // Network problem — fall back to a classic submit so the
        // customer is never stuck.
        form.submit();
      })
      .finally(function () {
        // After a successful swap the panel is detached; clearing the
        // busy state is then a harmless no-op.
        setBusy(panel, false);
      });
  }

  function bindAjax() {
    document.addEventListener('submit', function (event) {
      var form = event.target;
      var wrapper = getWrapper();

      if (
        !wrapper ||
        !wrapper.contains(form) ||
        form.hasAttribute('data-opc-skip') ||
        !isCheckoutForm(form)
      ) {
        return;
      }

      event.preventDefault();
      submitForm(form, event.submitter || null);
    });

    // Selecting a carrier confirms it right away, so shipping costs and
    // payment options refresh without pressing "Continue".
    document.addEventListener('change', function (event) {
      var input = event.target;
      var wrapper = getWrapper();

      if (
        !wrapper ||
        !input.name ||
        input.name.indexOf('delivery_option') !== 0 ||
        !wrapper.contains(input)
      ) {
        return;
      }

      var form = input.closest('form');
      if (!form || !isCheckoutForm(form)) {
        return;
      }

      window.clearTimeout(deliveryDebounce);
      deliveryDebounce = window.setTimeout(function () {
        var body = new FormData(form);
        body.append('confirmDeliveryOption', '1');
        var panel = form.closest('.opc-panel');
        setBusy(panel, true);

        fetch(form.getAttribute('action') || window.location.href, {
          method: 'POST',
          body: body,
          credentials: 'same-origin',
          headers: { Accept: 'text/html' },
        })
          .then(function (response) {
            return response.text();
          })
          .then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            if (!swapFromDocument(doc)) {
              window.location.reload();
            }
          })
          .catch(function () {
            setBusy(panel, false);
          });
      }, 350);
    });
  }

  onReady(function () {
    if (!getWrapper()) {
      return;
    }

    applyStickySummary();

    if (config.ajax) {
      bindAjax();
    }
  });
})();
