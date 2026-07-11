# dp_packeta — Packeta Delivery Methods for PrestaShop 9

Link your **existing carriers** to [Packeta](https://www.packeta.com/) (Zásilkovna)
delivery methods — **pickup points** (Packeta points, Z-BOXes and external
carrier pickup points via the official widget v6) or **home delivery** through
Packeta's external carriers — per country and per carrier, and export packets
to the Packeta API straight from the order page.

![PrestaShop 9](https://img.shields.io/badge/PrestaShop-9.x-blue)
![License MIT](https://img.shields.io/badge/license-MIT-green)

## Features

- **Link existing carriers** — you keep your carriers exactly as configured
  under *Shipping → Carriers* (name, logo, price ranges, zones, taxes).
  The module never computes shipping prices; your carrier ranges do.
- **Pickup points** — customers choose a point in the official
  [Packeta widget v6](https://widget.packeta.com/v6/) directly in checkout.
  Restrict the widget per mapping by **countries** (`cz,sk,…`), optional
  **vendor groups** (`zpoint`, `zbox`, …) and optionally to a single external
  carrier's pickup points (carrier ID).
- **Home delivery** — map a carrier to a Packeta external carrier ID
  (e.g. CZ/SK/HU/RO home delivery couriers); the customer's address is used.
- **Checkout validation** — the delivery step cannot be completed until a
  pickup point is chosen (server-side via `actionValidateStepComplete` +
  client-side guard). Works with AJAX/one-page checkouts
  (e.g. [dp_onepagecheckout](https://github.com/david-prochazka/dp_onepagecheckout)) —
  everything is bound with event delegation and survives fragment reloads.
- **Order page panel** — the selected pickup point is shown on the back
  office order page with one-click **Export to Packeta** (`createPacket`)
  and **label download** (`packetLabelPdf`), plus a tracking link.
- **Modern PS 9 admin** — Symfony controller, forms and Twig configuration
  page (no legacy HelperForm), reachable from *Shipping → Packeta* or the
  module's Configure button.

## How carrier linking works

PrestaShop only fires the pickup-point hooks (`displayCarrierExtraContent`,
`actionValidateStepComplete`) for carriers owned by a module. Linking a
carrier therefore *claims* it:

- `is_module = 1`, `external_module_name = dp_packeta`, `need_range = 1`
- `shipping_external` stays `0`, so **pricing keeps using the carrier's own
  ranges** — the module never touches shipping costs.

The original values are stored with the mapping and **restored when you
unlink the carrier or uninstall the module**. Carriers already owned by
another module are not offered for linking. Mappings are keyed by the
carrier's stable `id_reference`, so they survive carrier edits (PrestaShop
clones the carrier row on every edit).

## Requirements

- PrestaShop **9.0+**
- PHP **8.1+**
- A Packeta client account: **API key** (widget) and **API password**
  (packet export) from *Client section → Support & Resources → API keys*

## Installation

```bash
cd {shop_root}/modules
git clone git@github.com:david-prochazka/dp_packeta.git dp_packeta
cd dp_packeta && composer dump-autoload -o
```

or upload a zip of this repository (the archive folder must be named
`dp_packeta`). Then install it from **Back office → Modules → Module
Manager**, or:

```bash
php bin/console prestashop:module install dp_packeta
```

## Configuration

**Shipping → Packeta** (or Modules → Packeta Delivery Methods → Configure):

1. **Packeta account** — API key, API password, sender indication (the
   `eshop` label from your Packeta client section), widget language
   (empty = shop language), default packet weight.
2. **Link a carrier** — pick one of your carriers and choose:

   | Field | Pickup points | Home delivery |
   |---|---|---|
   | Countries | widget countries, e.g. `cz,sk` (empty = customer's country) | — |
   | Vendor groups | optional widget groups, e.g. `zpoint,zbox` | — |
   | Packeta carrier ID | optional — restrict widget to one external carrier's points | **required** (numeric ID) |

   When the API key is set, the page lists the external carriers available
   to your account (IDs, countries, pickup point support) from the Packeta
   carrier feed.

Typical setup: one carrier "Zásilkovna – výdejní místo" linked to pickup
points with countries `cz,sk`, one carrier "Packeta – domů" linked to home
delivery with the carrier ID of *CZ Packeta home delivery*, and similar pairs
for other countries — each with its own price ranges.

## Order flow

1. Customer selects a linked carrier in checkout → a **Select a pickup
   point** button appears under the carrier → widget opens → the point is
   saved for the cart (AJAX) and displayed.
2. The delivery step is blocked until a point is selected (pickup mappings).
3. After the order is placed, the point is bound to the order and shown on
   the order confirmation page, the customer's order detail, and the back
   office order page.
4. From the order page, **Export to Packeta** creates the packet
   (pickup point `addressId`, external points with `carrierPickupPoint`, or
   home delivery via the mapped carrier ID with the customer's address).
   Afterwards you can **download the label** and open the tracking page.
   Orders paid with `ps_cashondelivery` are exported with COD equal to the
   order total.

## Notes & limitations

- The pickup point selection is stored **per cart** — if the customer picks
  a point, then switches to another linked pickup carrier, the last chosen
  point applies (they can change it any time before ordering).
- Home delivery address validation (Packeta HD widget) is not integrated;
  the customer's PrestaShop address is sent as-is at export time.
- Multistore: settings use the standard `Configuration` scoping; carrier
  mappings are global (carriers themselves are per-shop objects).
- The Packeta REST endpoints used: `createPacket`, `packetLabelPdf`
  (https://www.zasilkovna.cz/api/rest) and the external carrier JSON feed
  (`pickup-point.api.packeta.com/v5/{apiKey}/carrier/json`). See the
  [Packeta API docs](https://docs.packeta.com/).

## Development

```bash
composer dump-autoload -o   # regenerate the autoloader (no runtime dependencies)
find . -name '*.php' -not -path './vendor/*' -exec php -l {} \;
```

CI (GitHub Actions) lints every PHP file on PHP 8.1–8.3.

## License

[MIT](LICENSE)
