<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Dapro\Packeta\Controller\Admin;

use Dapro\Packeta\Api\PacketaApiClient;
use Dapro\Packeta\Api\PacketaApiException;
use Dapro\Packeta\Form\CarrierMappingType;
use Dapro\Packeta\Repository\CarrierMappingRepository;
use Dapro\Packeta\Repository\SelectionRepository;
use Dapro\Packeta\Service\CarrierLinker;
use PrestaShop\PrestaShop\Core\Form\FormHandlerInterface;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

if (!defined('_PS_VERSION_')) {
    exit;
}

class ConfigurationController extends PrestaShopAdminController
{
    public function __construct(
        private readonly FormHandlerInterface $settingsFormHandler,
        private readonly CarrierMappingRepository $mappingRepository,
        private readonly SelectionRepository $selectionRepository,
        private readonly CarrierLinker $carrierLinker,
    ) {
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'Access denied.')]
    public function indexAction(Request $request): Response
    {
        $settingsForm = $this->settingsFormHandler->getForm();
        $settingsForm->handleRequest($request);

        if ($settingsForm->isSubmitted() && $settingsForm->isValid()) {
            $errors = $this->settingsFormHandler->save($settingsForm->getData());
            if (empty($errors)) {
                $this->addFlash('success', $this->trans('Settings updated.', [], 'Admin.Notifications.Success'));

                return $this->redirectToRoute('dp_packeta_configuration');
            }
            $this->addFlashErrors($errors);
        }

        $mappingForm = $this->createMappingForm([
            'mode' => CarrierMappingRepository::MODE_PICKUP_POINT,
            'active' => true,
        ]);
        $mappingForm->handleRequest($request);

        if ($mappingForm->isSubmitted() && $mappingForm->isValid()) {
            if ($this->saveMapping($mappingForm->getData())) {
                return $this->redirectToRoute('dp_packeta_configuration');
            }
        }

        return $this->renderConfiguration($settingsForm, $mappingForm, null);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'Access denied.')]
    public function editMappingAction(Request $request, int $mappingId): Response
    {
        $mapping = $this->mappingRepository->findById($mappingId);
        if (null === $mapping) {
            $this->addFlash('error', $this->trans('The mapping no longer exists.', [], 'Modules.Dppacketa.Admin'));

            return $this->redirectToRoute('dp_packeta_configuration');
        }

        $mappingForm = $this->createMappingForm(
            [
                'id_reference' => (int) $mapping['id_reference'],
                'mode' => (string) $mapping['mode'],
                'countries' => (string) $mapping['countries'],
                'vendor_groups' => (string) $mapping['vendor_groups'],
                'packeta_carrier_id' => (string) $mapping['packeta_carrier_id'],
                'active' => (bool) $mapping['active'],
            ],
            (int) $mapping['id_reference']
        );
        $mappingForm->handleRequest($request);

        if ($mappingForm->isSubmitted() && $mappingForm->isValid()) {
            if ($this->saveMapping($mappingForm->getData(), $mapping)) {
                return $this->redirectToRoute('dp_packeta_configuration');
            }
        }

        return $this->renderConfiguration($this->settingsFormHandler->getForm(), $mappingForm, $mapping);
    }

    #[AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message: 'Access denied.')]
    public function deleteMappingAction(int $mappingId): RedirectResponse
    {
        $mapping = $this->mappingRepository->findById($mappingId);
        if (null !== $mapping) {
            $this->carrierLinker->release($mapping);
            $this->mappingRepository->delete($mappingId);
            $this->addFlash('success', $this->trans('The carrier link was removed and the carrier restored.', [], 'Modules.Dppacketa.Admin'));
        }

        return $this->redirectToRoute('dp_packeta_configuration');
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'Access denied.')]
    public function toggleMappingAction(int $mappingId): RedirectResponse
    {
        $mapping = $this->mappingRepository->findById($mappingId);
        if (null !== $mapping) {
            $this->mappingRepository->setActive($mappingId, !(bool) $mapping['active']);
            $this->addFlash('success', $this->trans('Successful update.', [], 'Admin.Notifications.Success'));
        }

        return $this->redirectToRoute('dp_packeta_configuration');
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message: 'Access denied.')]
    public function exportOrderAction(int $orderId): RedirectResponse
    {
        try {
            $this->exportOrder($orderId);
            $this->addFlash('success', $this->trans('The packet was submitted to Packeta.', [], 'Modules.Dppacketa.Admin'));
        } catch (PacketaApiException|\PrestaShopException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_orders_view', ['orderId' => $orderId]);
    }

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message: 'Access denied.')]
    public function orderLabelAction(int $orderId): Response
    {
        $selection = $this->selectionRepository->findByOrderId($orderId);
        if (null === $selection || '' === (string) $selection['packet_id']) {
            $this->addFlash('error', $this->trans('This order has not been exported to Packeta yet.', [], 'Modules.Dppacketa.Admin'));

            return $this->redirectToRoute('admin_orders_view', ['orderId' => $orderId]);
        }

        try {
            $pdf = $this->createApiClient()->packetLabelPdf((string) $selection['packet_id']);
        } catch (PacketaApiException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('admin_orders_view', ['orderId' => $orderId]);
        }

        return new Response($pdf, Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="packeta-label-%s.pdf"', $selection['packet_id']),
        ]);
    }

    private function renderConfiguration(FormInterface $settingsForm, FormInterface $mappingForm, ?array $editedMapping): Response
    {
        $packetaCarriers = [];
        try {
            $packetaCarriers = $this->createApiClient()->getCarriers();
        } catch (PacketaApiException) {
            // The carrier feed is a convenience: the page must stay usable offline.
        }

        return $this->render('@Modules/dp_packeta/views/templates/admin/configuration.html.twig', [
            'layoutTitle' => $this->trans('Packeta Delivery Methods', [], 'Modules.Dppacketa.Admin'),
            'settingsForm' => $settingsForm->createView(),
            'mappingForm' => $mappingForm->createView(),
            'editedMapping' => $editedMapping,
            'mappings' => $this->mappingRepository->findAll(),
            'packetaCarriers' => $packetaCarriers,
            'help_link' => false,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function createMappingForm(array $data, ?int $includeReference = null): FormInterface
    {
        $choices = [];
        foreach ($this->mappingRepository->getLinkableCarriers($includeReference) as $carrier) {
            $choices[sprintf('%s (#%d)', $carrier['name'], $carrier['id_reference'])] = $carrier['id_reference'];
        }

        return $this->createForm(CarrierMappingType::class, $data, ['carrier_choices' => $choices]);
    }

    /**
     * Creates or updates a mapping, claiming / releasing carrier ownership as
     * needed. Returns false when the submitted data cannot be saved so the
     * form is displayed again.
     *
     * @param array<string, mixed> $data submitted form data
     * @param array<string, mixed>|null $existing mapping row being edited
     */
    private function saveMapping(array $data, ?array $existing = null): bool
    {
        $idReference = (int) $data['id_reference'];
        $packetaCarrierId = trim((string) $data['packeta_carrier_id']);

        if (CarrierMappingRepository::MODE_ADDRESS_DELIVERY === $data['mode'] && !ctype_digit($packetaCarrierId)) {
            $this->addFlash('error', $this->trans('Home delivery requires a numeric Packeta carrier ID.', [], 'Modules.Dppacketa.Admin'));

            return false;
        }
        if ('' !== $packetaCarrierId && !ctype_digit($packetaCarrierId)) {
            $this->addFlash('error', $this->trans('The Packeta carrier ID must be numeric.', [], 'Modules.Dppacketa.Admin'));

            return false;
        }

        $row = [
            'id_reference' => $idReference,
            'mode' => (string) $data['mode'],
            'countries' => strtolower(str_replace(' ', '', (string) $data['countries'])),
            'vendor_groups' => strtolower(str_replace(' ', '', (string) $data['vendor_groups'])),
            'packeta_carrier_id' => $packetaCarrierId,
            'active' => (int) (bool) $data['active'],
        ];

        if (null === $existing) {
            $originals = $this->carrierLinker->claim($idReference);
            if (null === $originals) {
                $this->addFlash('error', $this->trans('The selected carrier could not be found.', [], 'Modules.Dppacketa.Admin'));

                return false;
            }
            $this->mappingRepository->insert(array_merge($row, $originals));
            $this->addFlash('success', $this->trans('The carrier is now linked to Packeta.', [], 'Modules.Dppacketa.Admin'));

            return true;
        }

        if ($idReference !== (int) $existing['id_reference']) {
            $originals = $this->carrierLinker->claim($idReference);
            if (null === $originals) {
                $this->addFlash('error', $this->trans('The selected carrier could not be found.', [], 'Modules.Dppacketa.Admin'));

                return false;
            }
            $this->carrierLinker->release($existing);
            $row = array_merge($row, $originals);
        }

        $this->mappingRepository->update((int) $existing['id_dp_packeta_carrier'], $row);
        $this->addFlash('success', $this->trans('Successful update.', [], 'Admin.Notifications.Success'));

        return true;
    }

    /**
     * @throws PacketaApiException|\PrestaShopException
     */
    private function exportOrder(int $orderId): void
    {
        $order = new \Order($orderId);
        if (!\Validate::isLoadedObject($order)) {
            throw new PacketaApiException('Order not found.');
        }

        $mapping = $this->mappingRepository->findByCarrierId((int) $order->id_carrier);
        if (null === $mapping) {
            throw new PacketaApiException('The order carrier is not linked to Packeta.');
        }

        $selection = $this->selectionRepository->findByOrderId($orderId);
        if (null !== $selection && '' !== (string) $selection['packet_id']) {
            throw new PacketaApiException('This order was already exported to Packeta.');
        }

        $attributes = $this->buildPacketAttributes($order, $mapping, $selection);
        $result = $this->createApiClient()->createPacket($attributes);

        if (null === $selection) {
            // Home delivery orders have no checkout selection: create the row
            // now so the packet id has somewhere to live.
            $this->selectionRepository->saveForCart((int) $order->id_cart, ['point_type' => '']);
            $this->selectionRepository->bindOrder((int) $order->id_cart, $orderId);
            $selection = $this->selectionRepository->findByOrderId($orderId);
        }

        if (null !== $selection) {
            $this->selectionRepository->savePacket((int) $selection['id_dp_packeta_selection'], $result['id'], $result['barcode']);
        }
    }

    /**
     * @param array<string, mixed> $mapping
     * @param array<string, mixed>|null $selection
     *
     * @return array<string, string>
     */
    private function buildPacketAttributes(\Order $order, array $mapping, ?array $selection): array
    {
        $address = new \Address((int) $order->id_address_delivery);
        $customer = new \Customer((int) $order->id_customer);
        $currency = new \Currency((int) $order->id_currency);

        $weight = (float) $order->getTotalWeight();
        if ($weight <= 0) {
            $weight = (float) $this->getConfiguration()->get(\Dp_Packeta::CONFIG_DEFAULT_WEIGHT);
        }

        $attributes = [
            'number' => (string) $order->reference,
            'name' => (string) $address->firstname,
            'surname' => (string) $address->lastname,
            'email' => (string) $customer->email,
            'phone' => '' !== (string) $address->phone_mobile ? (string) $address->phone_mobile : (string) $address->phone,
            'currency' => (string) $currency->iso_code,
            'value' => (string) round((float) $order->total_paid_tax_incl, 2),
            'cod' => $order->module === 'ps_cashondelivery' ? (string) round((float) $order->total_paid_tax_incl, 2) : '',
            'weight' => (string) $weight,
            'eshop' => (string) $this->getConfiguration()->get(\Dp_Packeta::CONFIG_SENDER),
        ];

        if (CarrierMappingRepository::MODE_PICKUP_POINT === $mapping['mode']) {
            if (null === $selection || '' === (string) $selection['point_id']) {
                throw new PacketaApiException('No pickup point was selected for this order.');
            }

            if (SelectionRepository::TYPE_EXTERNAL === $selection['point_type']) {
                $attributes['addressId'] = (string) $selection['packeta_carrier_id'];
                $attributes['carrierPickupPoint'] = (string) $selection['carrier_pickup_point'];
            } else {
                $attributes['addressId'] = (string) $selection['point_id'];
            }

            return $attributes;
        }

        // Home delivery through an external Packeta carrier.
        $attributes['addressId'] = (string) $mapping['packeta_carrier_id'];
        $attributes['street'] = (string) $address->address1;
        $attributes['city'] = (string) $address->city;
        $attributes['zip'] = (string) $address->postcode;

        return $attributes;
    }

    private function createApiClient(): PacketaApiClient
    {
        return new PacketaApiClient(
            trim((string) $this->getConfiguration()->get(\Dp_Packeta::CONFIG_API_PASSWORD)),
            trim((string) $this->getConfiguration()->get(\Dp_Packeta::CONFIG_API_KEY)),
        );
    }
}
