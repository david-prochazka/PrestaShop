<?php
/**
 * One Page Checkout — modern single-page checkout for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use DavidProchazka\OnePageCheckout\OpcCheckoutProcess;

class Dp_OnePageCheckout extends Module
{
    public const CONFIG_ENABLED = 'DP_OPC_ENABLED';
    public const CONFIG_LAYOUT = 'DP_OPC_LAYOUT';
    public const CONFIG_AJAX = 'DP_OPC_AJAX';
    public const CONFIG_UNLOCK_ALL = 'DP_OPC_UNLOCK_ALL';
    public const CONFIG_STICKY_SUMMARY = 'DP_OPC_STICKY_SUMMARY';

    public const LAYOUT_COLUMNS = 'columns';
    public const LAYOUT_STACKED = 'stacked';

    private const HOOKS = [
        'actionCheckoutRender',
        'actionFrontControllerSetMedia',
    ];

    public function __construct()
    {
        $this->name = 'dp_onepagecheckout';
        $this->tab = 'checkout';
        $this->version = '1.0.0';
        $this->author = 'David Procházka';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];

        parent::__construct();

        $this->displayName = $this->trans('One Page Checkout', [], 'Modules.Dponepagecheckout.Admin');
        $this->description = $this->trans(
            'Modern one-page checkout: all checkout steps on a single page, updated over AJAX without full page reloads.',
            [],
            'Modules.Dponepagecheckout.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall One Page Checkout? The default multi-step checkout will be restored.',
            [],
            'Modules.Dponepagecheckout.Admin'
        );
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook(self::HOOKS)
            && Configuration::updateValue(self::CONFIG_ENABLED, 1)
            && Configuration::updateValue(self::CONFIG_LAYOUT, self::LAYOUT_COLUMNS)
            && Configuration::updateValue(self::CONFIG_AJAX, 1)
            && Configuration::updateValue(self::CONFIG_UNLOCK_ALL, 0)
            && Configuration::updateValue(self::CONFIG_STICKY_SUMMARY, 1);
    }

    public function uninstall(): bool
    {
        foreach ([
            self::CONFIG_ENABLED,
            self::CONFIG_LAYOUT,
            self::CONFIG_AJAX,
            self::CONFIG_UNLOCK_ALL,
            self::CONFIG_STICKY_SUMMARY,
        ] as $key) {
            Configuration::deleteByName($key);
        }

        return parent::uninstall();
    }

    /**
     * Replaces the native multi-step CheckoutProcess with the one-page variant.
     *
     * The hook receives the process by reference, so reassigning
     * $params['checkoutProcess'] swaps the instance used by OrderController.
     *
     * @param array{checkoutProcess: CheckoutProcess} $params
     */
    public function hookActionCheckoutRender(array $params): void
    {
        if (!$this->isOpcActive() || !isset($params['checkoutProcess'])) {
            return;
        }

        require_once __DIR__ . '/src/OpcCheckoutProcess.php';

        $process = $params['checkoutProcess'];
        if (!$process instanceof CheckoutProcess || $process instanceof OpcCheckoutProcess) {
            return;
        }

        $opcProcess = OpcCheckoutProcess::fromCheckoutProcess(
            $process,
            $this->context,
            (bool) Configuration::get(self::CONFIG_UNLOCK_ALL)
        );
        $opcProcess->setTemplate('module:dp_onepagecheckout/views/templates/front/checkout-process.tpl');

        $this->context->smarty->assign('opc', [
            'layout' => $this->getLayout(),
            'ajax' => (bool) Configuration::get(self::CONFIG_AJAX),
            'sticky_summary' => (bool) Configuration::get(self::CONFIG_STICKY_SUMMARY),
        ]);

        $params['checkoutProcess'] = $opcProcess;
    }

    public function hookActionFrontControllerSetMedia(array $params): void
    {
        $controller = $this->context->controller;

        if (!$this->isOpcActive() || !$controller instanceof FrontController || $controller->php_self !== 'order') {
            return;
        }

        $controller->registerStylesheet(
            'module-dp_onepagecheckout-front',
            'modules/' . $this->name . '/views/css/front.css',
            ['media' => 'all', 'priority' => 200]
        );
        $controller->registerJavascript(
            'module-dp_onepagecheckout-front',
            'modules/' . $this->name . '/views/js/front.js',
            ['position' => 'bottom', 'priority' => 200]
        );

        Media::addJsDef([
            'opcConfig' => [
                'ajax' => (bool) Configuration::get(self::CONFIG_AJAX),
                'stickySummary' => (bool) Configuration::get(self::CONFIG_STICKY_SUMMARY),
                'orderUrl' => $this->context->link->getPageLink('order'),
            ],
        ]);
    }

    public function getContent(): string
    {
        $output = '';

        if (Tools::isSubmit('submitDpOnepagecheckout')) {
            $layout = (string) Tools::getValue(self::CONFIG_LAYOUT, self::LAYOUT_COLUMNS);
            if (!in_array($layout, [self::LAYOUT_COLUMNS, self::LAYOUT_STACKED], true)) {
                $layout = self::LAYOUT_COLUMNS;
            }

            Configuration::updateValue(self::CONFIG_ENABLED, (int) Tools::getValue(self::CONFIG_ENABLED, 0));
            Configuration::updateValue(self::CONFIG_LAYOUT, $layout);
            Configuration::updateValue(self::CONFIG_AJAX, (int) Tools::getValue(self::CONFIG_AJAX, 0));
            Configuration::updateValue(self::CONFIG_UNLOCK_ALL, (int) Tools::getValue(self::CONFIG_UNLOCK_ALL, 0));
            Configuration::updateValue(self::CONFIG_STICKY_SUMMARY, (int) Tools::getValue(self::CONFIG_STICKY_SUMMARY, 0));

            $output .= $this->displayConfirmation($this->trans('Settings updated.', [], 'Admin.Notifications.Success'));
        }

        return $output
            . $this->display(__FILE__, 'views/templates/admin/configure.tpl')
            . $this->renderSettingsForm();
    }

    private function isOpcActive(): bool
    {
        return $this->active && (bool) Configuration::get(self::CONFIG_ENABLED);
    }

    private function getLayout(): string
    {
        $layout = (string) Configuration::get(self::CONFIG_LAYOUT);

        return in_array($layout, [self::LAYOUT_COLUMNS, self::LAYOUT_STACKED], true)
            ? $layout
            : self::LAYOUT_COLUMNS;
    }

    private function renderSettingsForm(): string
    {
        $form = [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Settings', [], 'Modules.Dponepagecheckout.Admin'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Enable one-page checkout', [], 'Modules.Dponepagecheckout.Admin'),
                        'name' => self::CONFIG_ENABLED,
                        'is_bool' => true,
                        'desc' => $this->trans('Turn off to restore the default multi-step checkout without uninstalling.', [], 'Modules.Dponepagecheckout.Admin'),
                        'values' => $this->getSwitchValues(self::CONFIG_ENABLED),
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->trans('Layout', [], 'Modules.Dponepagecheckout.Admin'),
                        'name' => self::CONFIG_LAYOUT,
                        'desc' => $this->trans('"Columns" shows the steps side by side on large screens. "Stacked" keeps them in a single column.', [], 'Modules.Dponepagecheckout.Admin'),
                        'options' => [
                            'query' => [
                                ['id' => self::LAYOUT_COLUMNS, 'name' => $this->trans('Columns (recommended)', [], 'Modules.Dponepagecheckout.Admin')],
                                ['id' => self::LAYOUT_STACKED, 'name' => $this->trans('Stacked', [], 'Modules.Dponepagecheckout.Admin')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('AJAX updates', [], 'Modules.Dponepagecheckout.Admin'),
                        'name' => self::CONFIG_AJAX,
                        'is_bool' => true,
                        'desc' => $this->trans('Submit checkout steps in the background and refresh the page content without a full reload.', [], 'Modules.Dponepagecheckout.Admin'),
                        'values' => $this->getSwitchValues(self::CONFIG_AJAX),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Unlock all steps immediately', [], 'Modules.Dponepagecheckout.Admin'),
                        'name' => self::CONFIG_UNLOCK_ALL,
                        'is_bool' => true,
                        'desc' => $this->trans('Show every step form right away instead of unlocking them progressively. Delivery and payment options may stay empty until an address is provided.', [], 'Modules.Dponepagecheckout.Admin'),
                        'values' => $this->getSwitchValues(self::CONFIG_UNLOCK_ALL),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Sticky order summary', [], 'Modules.Dponepagecheckout.Admin'),
                        'name' => self::CONFIG_STICKY_SUMMARY,
                        'is_bool' => true,
                        'desc' => $this->trans('Keep the cart summary visible while the customer scrolls.', [], 'Modules.Dponepagecheckout.Admin'),
                        'values' => $this->getSwitchValues(self::CONFIG_STICKY_SUMMARY),
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->submit_action = 'submitDpOnepagecheckout';
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
        $helper->fields_value = [
            self::CONFIG_ENABLED => (int) Configuration::get(self::CONFIG_ENABLED),
            self::CONFIG_LAYOUT => $this->getLayout(),
            self::CONFIG_AJAX => (int) Configuration::get(self::CONFIG_AJAX),
            self::CONFIG_UNLOCK_ALL => (int) Configuration::get(self::CONFIG_UNLOCK_ALL),
            self::CONFIG_STICKY_SUMMARY => (int) Configuration::get(self::CONFIG_STICKY_SUMMARY),
        ];

        return $helper->generateForm([$form]);
    }

    /**
     * @return array<int, array{id: string, value: int, label: string}>
     */
    private function getSwitchValues(string $name): array
    {
        return [
            ['id' => $name . '_on', 'value' => 1, 'label' => $this->trans('Yes', [], 'Admin.Global')],
            ['id' => $name . '_off', 'value' => 0, 'label' => $this->trans('No', [], 'Admin.Global')],
        ];
    }
}
