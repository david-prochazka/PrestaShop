<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Dapro\Packeta\Install;

use Dapro\Packeta\Repository\CarrierMappingRepository;
use Dapro\Packeta\Repository\SelectionRepository;
use Dapro\Packeta\Service\CarrierLinker;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class Installer
{
    private const HOOKS = [
        'displayCarrierExtraContent',
        'actionValidateStepComplete',
        'actionFrontControllerSetMedia',
        'actionValidateOrder',
        'displayAdminOrderSide',
        'displayOrderConfirmation',
        'displayOrderDetail',
    ];

    private const CONFIG_DEFAULTS = [
        \Dp_Packeta::CONFIG_API_KEY => '',
        \Dp_Packeta::CONFIG_API_PASSWORD => '',
        \Dp_Packeta::CONFIG_SENDER => '',
        \Dp_Packeta::CONFIG_WIDGET_LANGUAGE => '',
        \Dp_Packeta::CONFIG_DEFAULT_WEIGHT => '1',
    ];

    public function install(\Module $module): bool
    {
        return $module->registerHook(self::HOOKS)
            && $this->installConfiguration()
            && (new CarrierMappingRepository())->createTable()
            && (new SelectionRepository())->createTable()
            && $this->installTab();
    }

    public function uninstall(): bool
    {
        // Give the linked carriers back to the core before dropping the mappings.
        $mappingRepository = new CarrierMappingRepository();
        $linker = new CarrierLinker();
        foreach ($mappingRepository->findAll() as $mapping) {
            $linker->release($mapping);
        }

        foreach (array_keys(self::CONFIG_DEFAULTS) as $key) {
            \Configuration::deleteByName($key);
        }

        return $mappingRepository->dropTable()
            && (new SelectionRepository())->dropTable();
        // The admin tab is removed by Module::uninstall() (module-owned tabs).
    }

    private function installConfiguration(): bool
    {
        foreach (self::CONFIG_DEFAULTS as $key => $value) {
            if (false === \Configuration::get($key) && !\Configuration::updateValue($key, $value)) {
                return false;
            }
        }

        return true;
    }

    private function installTab(): bool
    {
        $tabId = (int) \Tab::getIdFromClassName('AdminDpPacketaConfiguration');
        if ($tabId > 0) {
            return true;
        }

        $tab = new \Tab();
        $tab->class_name = 'AdminDpPacketaConfiguration';
        $tab->route_name = 'dp_packeta_configuration';
        $tab->module = 'dp_packeta';
        $tab->id_parent = (int) \Tab::getIdFromClassName('AdminParentShipping');
        $tab->active = true;
        $tab->wording = 'Packeta';
        $tab->wording_domain = 'Modules.Dppacketa.Admin';
        foreach (\Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Packeta';
        }

        return (bool) $tab->add();
    }
}
