# dp_onepagecheckout — One Page Checkout for PrestaShop 9

Modern single-page checkout module. All native checkout steps — personal
information, addresses, delivery and payment — are displayed together on one
page and submitted over AJAX, with no full page reloads.

![PrestaShop 9](https://img.shields.io/badge/PrestaShop-9.x-blue)
![License MIT](https://img.shields.io/badge/license-MIT-green)

## How it works

The module does **not** fork the checkout. It plugs into the
`actionCheckoutRender` hook, where PrestaShop passes the `CheckoutProcess` by
reference, and swaps in a thin subclass ([`src/OpcCheckoutProcess.php`](src/OpcCheckoutProcess.php))
that renders all steps through the module's one-page template. All business
logic — step validation, session persistence, carrier and payment resolution,
theme step templates, hooks fired inside steps — remains 100% core:

1. **Layout** — [`views/templates/front/checkout-process.tpl`](views/templates/front/checkout-process.tpl)
   renders every native step inside a responsive panel grid; the CSS forces
   step contents visible (the default theme collapses non-current steps).
2. **AJAX flow** — [`views/js/front.js`](views/js/front.js) intercepts step form
   submits, POSTs them to the native order controller with `fetch()`, and swaps
   the refreshed steps / cart summary / notifications back into the DOM.
   Selecting a carrier confirms it immediately so shipping costs and payment
   options update live.
3. **Payment stays native** — payment module forms post to their own
   controllers and are never intercepted, so every payment gateway works
   unchanged.

Because each successful step response comes from the real controller, the
next step unlocks progressively on the same page — the modern
"progressive one-page" pattern. An optional **Unlock all steps** mode marks
every step reachable from the start instead.

## Requirements

- PrestaShop **9.0+** (uses the `actionCheckoutRender` by-reference process swap)
- A theme based on **classic** (default theme, child themes, hummingbird-style
  markup with `.checkout-step` sections)
- PHP 8.1+

> PrestaShop 9.2 (develop) ships a native *one_page_checkout* beta feature
> flag. Keep that flag **disabled** while this module is enabled — the two
> approaches would compete for the same page.

## Installation

```bash
cd {shop_root}/modules
git clone git@github.com:david-prochazka/dp_onepagecheckout.git dp_onepagecheckout
```

or upload a zip of this repository (the archive folder must be named
`dp_onepagecheckout`). Then install it from **Back office → Modules → Module
Manager**, or:

```bash
php bin/console prestashop:module install dp_onepagecheckout
```

## Configuration

**Modules → Module Manager → One Page Checkout → Configure**

| Setting | Default | Description |
|---|---|---|
| Enable one-page checkout | on | Turn off to restore the multi-step checkout without uninstalling. |
| Layout | Columns | `Columns` shows steps side by side on large screens; `Stacked` keeps a single column. |
| AJAX updates | on | Submit steps in the background and refresh fragments without reloading. |
| Unlock all steps immediately | off | Show every form at once. Delivery/payment options may stay empty until an address exists. |
| Sticky order summary | on | Keep the cart summary pinned while scrolling. |

All settings are shop-scoped (multistore-safe via `Configuration`).

## Compatibility notes

- **Payment / shipping modules**: fully compatible — options are produced by
  the native `PaymentOptionsFinder` / `DeliveryOptionsFinder`, and the classic
  theme binds its checkout handlers with event delegation on `<body>`, which
  survives the AJAX fragment swaps. A payment module that binds directly to
  payment-option DOM nodes at page load may need the *AJAX updates* setting
  turned off (the one-page layout is kept; steps then submit classically).
- **Heavily customized checkout themes** that removed the `.checkout-step`
  sections or `#js-checkout-summary` are not supported.
- On any unexpected response (redirect to cart, order confirmation, payment
  gateway), the script follows the redirect with a real navigation — the
  customer can never get stuck on a stale page.

## Development

```bash
composer install   # dev tooling only, the module has no runtime dependencies
php -l dp_onepagecheckout.php src/OpcCheckoutProcess.php
```

CI (GitHub Actions) lints every PHP file on PHP 8.1–8.3.

## License

[MIT](LICENSE)
