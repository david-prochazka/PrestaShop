<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

use Dapro\Packeta\Repository\CarrierMappingRepository;
use Dapro\Packeta\Repository\SelectionRepository;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * AJAX endpoint persisting the pickup point chosen in the Packeta widget for
 * the current cart. Called from views/js/front.js.
 */
class Dp_PacketaSelectionModuleFrontController extends ModuleFrontController
{
    /** @var bool */
    public $ajax = true;

    public function postProcess(): void
    {
        header('Content-Type: application/json');

        if (!Tools::isSubmit('action')
            || Tools::getValue('token') !== Tools::getToken('dp_packeta')
            || !$this->context->cart
            || !$this->context->cart->id
        ) {
            $this->renderJson(['success' => false, 'error' => 'Invalid request.']);
        }

        $idCart = (int) $this->context->cart->id;

        if ('clear' === Tools::getValue('action')) {
            (new SelectionRepository())->deleteByCartId($idCart);
            $this->renderJson(['success' => true]);
        }

        if ('save' !== Tools::getValue('action')) {
            $this->renderJson(['success' => false, 'error' => 'Unknown action.']);
        }

        $idCarrier = (int) Tools::getValue('id_carrier');
        $mapping = (new CarrierMappingRepository())->findByCarrierId($idCarrier);
        if (null === $mapping || CarrierMappingRepository::MODE_PICKUP_POINT !== $mapping['mode']) {
            $this->renderJson(['success' => false, 'error' => 'This carrier does not use Packeta pickup points.']);
        }

        $point = json_decode((string) Tools::getValue('point'), true);
        if (!is_array($point) || '' === trim((string) ($point['id'] ?? ''))) {
            $this->renderJson(['success' => false, 'error' => 'Invalid pickup point.']);
        }

        $isExternal = 'external' === ($point['pickupPointType'] ?? 'internal');

        (new SelectionRepository())->saveForCart($idCart, [
            'point_id' => $this->cleanValue($point, 'id', 64),
            'point_type' => $isExternal ? SelectionRepository::TYPE_EXTERNAL : SelectionRepository::TYPE_INTERNAL,
            'packeta_carrier_id' => $this->cleanValue($point, 'carrierId', 32),
            'carrier_pickup_point' => $this->cleanValue($point, 'carrierPickupPointId', 64),
            'point_name' => $this->cleanValue($point, 'name', 255),
            'street' => $this->cleanValue($point, 'street', 128),
            'city' => $this->cleanValue($point, 'city', 64),
            'zip' => $this->cleanValue($point, 'zip', 12),
            'country' => strtolower($this->cleanValue($point, 'country', 2)),
            'point_url' => $this->cleanValue($point, 'url', 255),
        ]);

        $this->renderJson([
            'success' => true,
            'label' => trim(sprintf(
                '%s, %s, %s %s',
                (string) ($point['name'] ?? ''),
                (string) ($point['street'] ?? ''),
                (string) ($point['zip'] ?? ''),
                (string) ($point['city'] ?? '')
            ), ', '),
        ]);
    }

    /**
     * @param array<string, mixed> $point
     */
    private function cleanValue(array $point, string $key, int $maxLength): string
    {
        $value = trim((string) ($point[$key] ?? ''));

        return mb_substr(strip_tags($value), 0, $maxLength);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function renderJson(array $payload): never
    {
        $this->ajaxRender((string) json_encode($payload));
        exit;
    }
}
