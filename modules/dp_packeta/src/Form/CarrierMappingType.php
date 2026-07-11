<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Dapro\Packeta\Form;

use Dapro\Packeta\Repository\CarrierMappingRepository;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Links one PrestaShop carrier (by stable id_reference) to a Packeta delivery
 * method: pickup points (widget) or home delivery via an external Packeta
 * carrier id.
 */
final class CarrierMappingType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_reference', ChoiceType::class, [
                'label' => $this->trans('Carrier', 'Modules.Dppacketa.Admin'),
                'choices' => $options['carrier_choices'],
                'constraints' => [new NotBlank()],
                'placeholder' => $this->trans('-- Select a carrier --', 'Modules.Dppacketa.Admin'),
            ])
            ->add('mode', ChoiceType::class, [
                'label' => $this->trans('Packeta delivery method', 'Modules.Dppacketa.Admin'),
                'choices' => [
                    $this->trans('Pickup points (widget)', 'Modules.Dppacketa.Admin') => CarrierMappingRepository::MODE_PICKUP_POINT,
                    $this->trans('Home delivery (external carrier)', 'Modules.Dppacketa.Admin') => CarrierMappingRepository::MODE_ADDRESS_DELIVERY,
                ],
            ])
            ->add('countries', TextType::class, [
                'label' => $this->trans('Countries', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Comma-separated ISO codes shown in the widget, e.g. "cz,sk". Leave empty to use the customer delivery address country.', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('vendor_groups', TextType::class, [
                'label' => $this->trans('Vendor groups', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Optional comma-separated widget vendor groups, e.g. "zpoint,zbox". Leave empty to offer all Packeta pickup points.', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('packeta_carrier_id', TextType::class, [
                'label' => $this->trans('Packeta carrier ID', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Numeric ID of the Packeta external carrier. Required for home delivery; optional for pickup points (restricts the widget to that carrier\'s pickup points).', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('active', SwitchType::class, [
                'label' => $this->trans('Enabled', 'Modules.Dppacketa.Admin'),
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'carrier_choices' => [],
        ]);
        $resolver->setAllowedTypes('carrier_choices', 'array');
    }
}
