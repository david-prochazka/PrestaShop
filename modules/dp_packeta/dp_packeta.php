<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use Dapro\Packeta\Install\Installer;
use Dapro\Packeta\Repository\CarrierMappingRepository;
use Dapro\Packeta\Repository\SelectionRepository;

class Dp_Packeta extends CarrierModule
{
    public const CONFIG_API_KEY = 'DP_PACKETA_API_KEY';
    public const CONFIG_API_PASSWORD = 'DP_PACKETA_API_PASSWORD';
    public const CONFIG_SENDER = 'DP_PACKETA_SENDER';
    public const CONFIG_WIDGET_LANGUAGE = 'DP_PACKETA_WIDGET_LANGUAGE';
    public const CONFIG_DEFAULT_WEIGHT = 'DP_PACKETA_DEFAULT_WEIGHT';

    public const WIDGET_LIBRARY_URL = 'https://widget.packeta.com/v6/www/js/library.js';

    public function __construct()
    {
        $this->name = 'dp_packeta';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'David Procházka';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('Packeta Delivery Methods', [], 'Modules.Dppacketa.Admin');
        $this->description = $this->trans(
            'Link existing carriers to Packeta (Zásilkovna) pickup points or home delivery per country, with pickup point selection in checkout and packet export to the Packeta API.',
            [],
            'Modules.Dppacketa.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall Packeta Delivery Methods? Linked carriers will be restored and stored pickup point selections deleted.',
            [],
            'Modules.Dppacketa.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install() && (new Installer())->install($this);
    }

    public function uninstall(): bool
    {
        return (new Installer())->uninstall() && parent::uninstall();
    }

    public function getContent(): void
    {
        Tools::redirectAdmin($this->context->link->getAdminLink('AdminDpPacketaConfiguration'));
    }

    /**
     * Linked carriers keep shipping_external = 0, so the core computes the
     * price from the carrier's own ranges and never asks the module. These
     * implementations only exist because CarrierModule declares them abstract;
     * if one is ever reached the carrier is misconfigured, so it is disabled
     * rather than given a wrong price.
     *
     * @param Cart $params
     * @param float $shipping_cost
     *
     * @return float
     */
    public function getOrderShippingCost($params, $shipping_cost)
    {
        return $shipping_cost;
    }

    /**
     * @param Cart $params
     *
     * @return bool
     */
    public function getOrderShippingCostExternal($params)
    {
        return false;
    }

    /**
     * Renders the pickup point selector under a linked carrier in checkout.
     *
     * @param array{carrier: array<string, mixed>|Carrier} $params
     */
    public function hookDisplayCarrierExtraContent(array $params): string
    {
        if (!$this->active || !isset($params['carrier'])) {
            return '';
        }

        $idCarrier = is_array($params['carrier'])
            ? (int) ($params['carrier']['id'] ?? 0)
            : (int) $params['carrier']->id;
        if (0 === $idCarrier) {
            return '';
        }

        $mapping = (new CarrierMappingRepository())->findByCarrierId($idCarrier);
        if (null === $mapping
            || CarrierMappingRepository::MODE_PICKUP_POINT !== $mapping['mode']
            || '' === $this->getApiKey()
        ) {
            return '';
        }

        $selection = null;
        if ($this->context->cart) {
            $selection = (new SelectionRepository())->findByCartId((int) $this->context->cart->id);
        }

        $this->context->smarty->assign([
            'dp_packeta' => [
                'id_carrier' => $idCarrier,
                'widget' => json_encode($this->buildWidgetOptions($mapping)),
                'selection' => $selection,
            ],
        ]);

        return $this->display(__FILE__, 'views/templates/hook/carrier-extra-content.tpl');
    }

    /**
     * Blocks completion of the delivery step while a linked pickup point
     * carrier is selected without a pickup point. Only fired by the core for
     * this module's carriers.
     *
     * @param array{step_name: string, request_params: array<string, mixed>, completed: bool} $params
     */
    public function hookActionValidateStepComplete(array &$params): void
    {
        if (!$this->active || 'delivery' !== ($params['step_name'] ?? '')) {
            return;
        }

        if (null === $this->context->cart) {
            return;
        }

        $deliveryOption = $params['request_params']['delivery_option'] ?? [];
        if (!is_array($deliveryOption) || empty($deliveryOption)) {
            // Theme did not repost the option: fall back to the cart session.
            $deliveryOption = $this->context->cart->getDeliveryOption(null, false, false) ?: [];
        }

        $mappings = (new CarrierMappingRepository())->findActiveByCurrentCarrierId();
        $selection = (new SelectionRepository())->findByCartId((int) $this->context->cart->id);

        foreach ($deliveryOption as $optionValue) {
            foreach (array_filter(explode(',', (string) $optionValue)) as $idCarrier) {
                $mapping = $mappings[(int) $idCarrier] ?? null;
                if (null === $mapping || CarrierMappingRepository::MODE_PICKUP_POINT !== $mapping['mode']) {
                    continue;
                }

                if (null === $selection || '' === (string) $selection['point_id']) {
                    $params['completed'] = false;
                    if ($this->context->controller instanceof FrontController) {
                        $this->context->controller->errors[] = $this->trans(
                            'Please select a pickup point to continue.',
                            [],
                            'Modules.Dppacketa.Shop'
                        );
                    }

                    return;
                }
            }
        }
    }

    public function hookActionFrontControllerSetMedia(array $params): void
    {
        $controller = $this->context->controller;

        if (!$this->active
            || '' === $this->getApiKey()
            || !$controller instanceof FrontController
            || 'order' !== $controller->php_self
        ) {
            return;
        }

        $pickupMappings = array_filter(
            (new CarrierMappingRepository())->findActiveByCurrentCarrierId(),
            static fn (array $mapping): bool => CarrierMappingRepository::MODE_PICKUP_POINT === $mapping['mode']
        );
        if (empty($pickupMappings)) {
            return;
        }

        $controller->registerJavascript(
            'dp-packeta-widget',
            self::WIDGET_LIBRARY_URL,
            ['server' => 'remote', 'position' => 'bottom', 'priority' => 190]
        );
        $controller->registerJavascript(
            'module-dp_packeta-front',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );
        $controller->registerStylesheet(
            'module-dp_packeta-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 200]
        );

        $carriers = [];
        foreach ($pickupMappings as $idCarrier => $mapping) {
            $carriers[$idCarrier] = $this->buildWidgetOptions($mapping);
        }

        Media::addJsDef([
            'dpPacketa' => [
                'apiKey' => $this->getApiKey(),
                'ajaxUrl' => $this->context->link->getModuleLink($this->name, 'selection'),
                'token' => Tools::getToken('dp_packeta'),
                'carriers' => $carriers,
                'i18n' => [
                    'selectPoint' => $this->trans('Please select a pickup point to continue.', [], 'Modules.Dppacketa.Shop'),
                    'saveFailed' => $this->trans('The pickup point could not be saved. Please try again.', [], 'Modules.Dppacketa.Shop'),
                ],
            ],
        ]);
    }

    /**
     * Binds the cart's pickup point selection to the newly created order.
     *
     * @param array{cart: Cart, order: Order} $params
     */
    public function hookActionValidateOrder(array $params): void
    {
        if (!isset($params['cart'], $params['order'])) {
            return;
        }

        $order = $params['order'];
        $mapping = (new CarrierMappingRepository())->findByCarrierId((int) $order->id_carrier);
        if (null === $mapping || CarrierMappingRepository::MODE_PICKUP_POINT !== $mapping['mode']) {
            return;
        }

        (new SelectionRepository())->bindOrder((int) $params['cart']->id, (int) $order->id);
    }

    /**
     * Packeta panel on the back office order page: pickup point details,
     * packet export and label download.
     *
     * @param array{id_order: int} $params
     */
    public function hookDisplayAdminOrderSide(array $params): string
    {
        $order = new Order((int) ($params['id_order'] ?? 0));
        if (!Validate::isLoadedObject($order)) {
            return '';
        }

        $mapping = (new CarrierMappingRepository())->findByCarrierId((int) $order->id_carrier);
        if (null === $mapping) {
            return '';
        }

        $selection = (new SelectionRepository())->findByOrderId((int) $order->id);
        $isPickup = CarrierMappingRepository::MODE_PICKUP_POINT === $mapping['mode'];

        if ($isPickup && null === $selection) {
            return '';
        }

        $packetId = null !== $selection ? (string) $selection['packet_id'] : '';

        $this->context->smarty->assign([
            'dp_packeta_order' => [
                'is_pickup' => $isPickup,
                'selection' => $selection,
                'packeta_carrier_id' => (string) $mapping['packeta_carrier_id'],
                'packet_id' => $packetId,
                'packet_barcode' => null !== $selection ? (string) $selection['packet_barcode'] : '',
                'tracking_url' => '' !== $packetId ? 'https://tracking.packeta.com/?id=' . rawurlencode($packetId) : '',
                'can_export' => '' === $packetId && '' !== (string) Configuration::get(self::CONFIG_API_PASSWORD),
                'export_url' => $this->getAdminRouteUrl('dp_packeta_export_order', ['orderId' => (int) $order->id]),
                'label_url' => '' !== $packetId ? $this->getAdminRouteUrl('dp_packeta_order_label', ['orderId' => (int) $order->id]) : '',
            ],
        ]);

        return $this->display(__FILE__, 'views/templates/hook/admin-order-side.tpl');
    }

    /**
     * @param array{order: Order} $params
     */
    public function hookDisplayOrderConfirmation(array $params): string
    {
        return $this->renderFrontOrderPoint($params['order'] ?? null);
    }

    /**
     * @param array{order: Order} $params
     */
    public function hookDisplayOrderDetail(array $params): string
    {
        return $this->renderFrontOrderPoint($params['order'] ?? null);
    }

    private function renderFrontOrderPoint(mixed $order): string
    {
        if (!$order instanceof Order || !Validate::isLoadedObject($order)) {
            return '';
        }

        $selection = (new SelectionRepository())->findByOrderId((int) $order->id);
        if (null === $selection || '' === (string) $selection['point_id']) {
            return '';
        }

        $this->context->smarty->assign(['dp_packeta_selection' => $selection]);

        return $this->display(__FILE__, 'views/templates/hook/order-point.tpl');
    }

    /**
     * Builds the Packeta widget v6 options for a pickup point mapping:
     * language, and a vendor list combining countries, optional vendor groups
     * and an optional external carrier restriction.
     *
     * @param array<string, mixed> $mapping
     *
     * @return array<string, mixed>
     */
    private function buildWidgetOptions(array $mapping): array
    {
        $countries = $this->csvToArray((string) $mapping['countries']);
        if (empty($countries) && $this->context->cart) {
            $address = new Address((int) $this->context->cart->id_address_delivery);
            $iso = (string) Country::getIsoById((int) $address->id_country);
            if ('' !== $iso) {
                $countries = [strtolower($iso)];
            }
        }

        $groups = $this->csvToArray((string) $mapping['vendor_groups']);
        $vendors = [];
        foreach ($countries as $country) {
            if (empty($groups)) {
                $vendors[] = ['country' => $country];
                continue;
            }
            foreach ($groups as $group) {
                $vendors[] = ['country' => $country, 'group' => $group];
            }
        }
        if ('' !== (string) $mapping['packeta_carrier_id']) {
            $vendors[] = ['carrierId' => (string) $mapping['packeta_carrier_id']];
        }

        $language = (string) Configuration::get(self::CONFIG_WIDGET_LANGUAGE);
        if ('' === $language) {
            $language = (string) $this->context->language->iso_code;
        }

        $options = [
            'language' => $language,
            'country' => implode(',', $countries),
        ];
        if (!empty($vendors)) {
            $options['vendors'] = $vendors;
        }

        $weight = $this->context->cart ? (float) $this->context->cart->getTotalWeight() : 0.0;
        if ($weight <= 0) {
            $weight = (float) Configuration::get(self::CONFIG_DEFAULT_WEIGHT);
        }
        if ($weight > 0) {
            $options['weight'] = $weight;
        }

        return $options;
    }

    /**
     * @return array<int, string> lowercase trimmed values
     */
    private function csvToArray(string $csv): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): string => strtolower(trim($value)),
            explode(',', $csv)
        )));
    }

    private function getApiKey(): string
    {
        return trim((string) Configuration::get(self::CONFIG_API_KEY));
    }

    /**
     * @param array<string, int|string> $routeParams
     */
    private function getAdminRouteUrl(string $routeName, array $routeParams): string
    {
        try {
            $container = PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance();
            if (null === $container) {
                return '';
            }

            return (string) $container->get('router')->generate($routeName, $routeParams);
        } catch (Exception) {
            return '';
        }
    }
}
