<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Dapro\Packeta\Repository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Persistence for carrier ↔ Packeta delivery method mappings.
 *
 * Mappings are keyed by the carrier `id_reference`: PrestaShop clones the
 * carrier row (new `id_carrier`) every time a carrier is edited in the back
 * office, while `id_reference` is stable across edits.
 */
final class CarrierMappingRepository
{
    public const MODE_PICKUP_POINT = 'pickup_point';
    public const MODE_ADDRESS_DELIVERY = 'address_delivery';

    public const TABLE = 'dp_packeta_carrier';

    public function createTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_dp_packeta_carrier` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_reference` INT UNSIGNED NOT NULL,
            `mode` VARCHAR(20) NOT NULL DEFAULT "' . self::MODE_PICKUP_POINT . '",
            `countries` VARCHAR(255) NOT NULL DEFAULT "",
            `vendor_groups` VARCHAR(255) NOT NULL DEFAULT "",
            `packeta_carrier_id` VARCHAR(32) NOT NULL DEFAULT "",
            `orig_is_module` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `orig_shipping_external` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `orig_need_range` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
            `orig_external_module_name` VARCHAR(64) NOT NULL DEFAULT "",
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_dp_packeta_carrier`),
            UNIQUE KEY `id_reference` (`id_reference`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        return \Db::getInstance()->execute($sql);
    }

    public function dropTable(): bool
    {
        return \Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . self::TABLE . '`');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $mappingId): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `id_dp_packeta_carrier` = ' . $mappingId
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByCarrierReference(int $idReference, bool $onlyActive = false): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '`
             WHERE `id_reference` = ' . $idReference
            . ($onlyActive ? ' AND `active` = 1' : '')
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null Active mapping for the given carrier row id
     */
    public function findByCarrierId(int $idCarrier, bool $onlyActive = true): ?array
    {
        $carrier = new \Carrier($idCarrier);
        if (!\Validate::isLoadedObject($carrier)) {
            return null;
        }

        return $this->findByCarrierReference((int) $carrier->id_reference, $onlyActive);
    }

    /**
     * All mappings, enriched with the current carrier name (if it still exists).
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT m.*, c.`name` AS carrier_name, c.`id_carrier`, c.`active` AS carrier_active
             FROM `' . _DB_PREFIX_ . self::TABLE . '` m
             LEFT JOIN `' . _DB_PREFIX_ . 'carrier` c
                ON c.`id_reference` = m.`id_reference` AND c.`deleted` = 0
             ORDER BY m.`id_dp_packeta_carrier` ASC'
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Active pickup point mappings resolved to the current carrier row ids,
     * used to build the checkout widget configuration.
     *
     * @return array<int, array<string, mixed>> indexed by id_carrier
     */
    public function findActiveByCurrentCarrierId(): array
    {
        $rows = \Db::getInstance()->executeS(
            'SELECT m.*, c.`id_carrier`
             FROM `' . _DB_PREFIX_ . self::TABLE . '` m
             INNER JOIN `' . _DB_PREFIX_ . 'carrier` c
                ON c.`id_reference` = m.`id_reference` AND c.`deleted` = 0
             WHERE m.`active` = 1'
        );

        $indexed = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $indexed[(int) $row['id_carrier']] = $row;
        }

        return $indexed;
    }

    /**
     * Carriers that can be linked: not deleted, and not owned by another module.
     * The optional $includeReference keeps the currently mapped carrier
     * selectable when editing an existing mapping.
     *
     * @return array<int, array{id_reference: int, name: string}>
     */
    public function getLinkableCarriers(?int $includeReference = null): array
    {
        $mapped = \Db::getInstance()->executeS(
            'SELECT `id_reference` FROM `' . _DB_PREFIX_ . self::TABLE . '`'
        );
        $mappedRefs = array_map(static fn (array $r): int => (int) $r['id_reference'], is_array($mapped) ? $mapped : []);
        if (null !== $includeReference) {
            $mappedRefs = array_diff($mappedRefs, [$includeReference]);
        }

        $sql = 'SELECT c.`id_reference`, c.`name`
            FROM `' . _DB_PREFIX_ . 'carrier` c
            WHERE c.`deleted` = 0
              AND (c.`external_module_name` = "" OR c.`external_module_name` IS NULL OR c.`external_module_name` = "dp_packeta")';
        if (!empty($mappedRefs)) {
            $sql .= ' AND c.`id_reference` NOT IN (' . implode(',', array_map('intval', $mappedRefs)) . ')';
        }
        $sql .= ' ORDER BY c.`name` ASC';

        $rows = \Db::getInstance()->executeS($sql);

        return array_map(
            static fn (array $r): array => ['id_reference' => (int) $r['id_reference'], 'name' => (string) $r['name']],
            is_array($rows) ? $rows : []
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $db = \Db::getInstance();
        $db->insert(self::TABLE, array_merge($this->escapeRow($data), [
            'date_add' => pSQL($now),
            'date_upd' => pSQL($now),
        ]));

        return (int) $db->Insert_ID();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $mappingId, array $data): bool
    {
        return \Db::getInstance()->update(
            self::TABLE,
            array_merge($this->escapeRow($data), ['date_upd' => pSQL(date('Y-m-d H:i:s'))]),
            '`id_dp_packeta_carrier` = ' . $mappingId
        );
    }

    public function delete(int $mappingId): bool
    {
        return \Db::getInstance()->delete(self::TABLE, '`id_dp_packeta_carrier` = ' . $mappingId);
    }

    public function setActive(int $mappingId, bool $active): bool
    {
        return $this->update($mappingId, ['active' => (int) $active]);
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, int|string>
     */
    private function escapeRow(array $data): array
    {
        $escaped = [];
        foreach ($data as $column => $value) {
            $escaped[bqSQL($column)] = is_int($value) ? $value : pSQL((string) $value);
        }

        return $escaped;
    }
}
