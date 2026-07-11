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
 * Stores the pickup point chosen in checkout (one row per cart), later bound
 * to the order and enriched with the Packeta packet id after export.
 */
final class SelectionRepository
{
    public const TABLE = 'dp_packeta_selection';

    public const TYPE_INTERNAL = 'internal';
    public const TYPE_EXTERNAL = 'external';

    public function createTable(): bool
    {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . self::TABLE . '` (
            `id_dp_packeta_selection` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_cart` INT UNSIGNED NOT NULL,
            `id_order` INT UNSIGNED DEFAULT NULL,
            `point_id` VARCHAR(64) NOT NULL DEFAULT "",
            `point_type` VARCHAR(16) NOT NULL DEFAULT "' . self::TYPE_INTERNAL . '",
            `packeta_carrier_id` VARCHAR(32) NOT NULL DEFAULT "",
            `carrier_pickup_point` VARCHAR(64) NOT NULL DEFAULT "",
            `point_name` VARCHAR(255) NOT NULL DEFAULT "",
            `street` VARCHAR(128) NOT NULL DEFAULT "",
            `city` VARCHAR(64) NOT NULL DEFAULT "",
            `zip` VARCHAR(12) NOT NULL DEFAULT "",
            `country` VARCHAR(2) NOT NULL DEFAULT "",
            `point_url` VARCHAR(255) NOT NULL DEFAULT "",
            `packet_id` VARCHAR(32) NOT NULL DEFAULT "",
            `packet_barcode` VARCHAR(32) NOT NULL DEFAULT "",
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_dp_packeta_selection`),
            UNIQUE KEY `id_cart` (`id_cart`),
            KEY `id_order` (`id_order`)
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
    public function findByCartId(int $idCart): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_cart` = ' . $idCart
        );

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByOrderId(int $idOrder): ?array
    {
        $row = \Db::getInstance()->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . self::TABLE . '` WHERE `id_order` = ' . $idOrder
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Insert or replace the selection for a cart.
     *
     * @param array<string, mixed> $data selection columns (without id_cart)
     */
    public function saveForCart(int $idCart, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $existing = $this->findByCartId($idCart);

        if (null !== $existing) {
            return \Db::getInstance()->update(
                self::TABLE,
                array_merge($this->escapeRow($data), ['date_upd' => pSQL($now)]),
                '`id_cart` = ' . $idCart
            );
        }

        return \Db::getInstance()->insert(self::TABLE, array_merge($this->escapeRow($data), [
            'id_cart' => $idCart,
            'date_add' => pSQL($now),
            'date_upd' => pSQL($now),
        ]));
    }

    public function deleteByCartId(int $idCart): bool
    {
        return \Db::getInstance()->delete(self::TABLE, '`id_cart` = ' . $idCart . ' AND `id_order` IS NULL');
    }

    public function bindOrder(int $idCart, int $idOrder): bool
    {
        return \Db::getInstance()->update(
            self::TABLE,
            ['id_order' => $idOrder, 'date_upd' => pSQL(date('Y-m-d H:i:s'))],
            '`id_cart` = ' . $idCart
        );
    }

    public function savePacket(int $selectionId, string $packetId, string $barcode): bool
    {
        return \Db::getInstance()->update(
            self::TABLE,
            [
                'packet_id' => pSQL($packetId),
                'packet_barcode' => pSQL($barcode),
                'date_upd' => pSQL(date('Y-m-d H:i:s')),
            ],
            '`id_dp_packeta_selection` = ' . $selectionId
        );
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
