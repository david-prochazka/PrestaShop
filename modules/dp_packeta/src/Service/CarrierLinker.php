<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Dapro\Packeta\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Takes and releases module ownership of an existing carrier.
 *
 * The core only fires `displayCarrierExtraContent` and
 * `actionValidateStepComplete` for carriers with `is_module = 1` and a
 * matching `external_module_name`, so linking a carrier means claiming it:
 *
 * - `is_module = 1`, `external_module_name = 'dp_packeta'`, `need_range = 1`
 *   (required by the carrier list filters so the carrier stays available);
 * - `shipping_external` stays 0, so the carrier's native price ranges keep
 *   computing the shipping cost — the module never touches pricing.
 *
 * Original values are returned by {@see claim()} so the mapping can restore
 * them on unlink or uninstall.
 */
final class CarrierLinker
{
    public const MODULE_NAME = 'dp_packeta';

    /**
     * Claims the current carrier row for this module.
     *
     * @return array{orig_is_module: int, orig_shipping_external: int, orig_need_range: int, orig_external_module_name: string}|null
     *                                                                                                                            Original ownership values to persist, or null if the carrier does not exist
     */
    public function claim(int $idReference): ?array
    {
        $carrier = \Carrier::getCarrierByReference($idReference);
        if (!$carrier instanceof \Carrier || !\Validate::isLoadedObject($carrier)) {
            return null;
        }

        $originals = [
            'orig_is_module' => (int) $carrier->is_module,
            'orig_shipping_external' => (int) $carrier->shipping_external,
            'orig_need_range' => (int) $carrier->need_range,
            'orig_external_module_name' => (string) $carrier->external_module_name,
        ];

        $carrier->is_module = true;
        $carrier->external_module_name = self::MODULE_NAME;
        $carrier->need_range = true;
        $carrier->update();

        return $originals;
    }

    /**
     * Restores the ownership fields captured when the mapping was created.
     *
     * @param array<string, mixed> $mapping a dp_packeta_carrier row
     */
    public function release(array $mapping): void
    {
        $carrier = \Carrier::getCarrierByReference((int) $mapping['id_reference']);
        if (!$carrier instanceof \Carrier || !\Validate::isLoadedObject($carrier)) {
            return;
        }

        // Only restore a carrier this module still owns.
        if ($carrier->external_module_name !== self::MODULE_NAME) {
            return;
        }

        $carrier->is_module = (bool) $mapping['orig_is_module'];
        $carrier->shipping_external = (bool) $mapping['orig_shipping_external'];
        $carrier->need_range = (bool) $mapping['orig_need_range'];
        $carrier->external_module_name = (string) $mapping['orig_external_module_name'];
        $carrier->update();
    }
}
