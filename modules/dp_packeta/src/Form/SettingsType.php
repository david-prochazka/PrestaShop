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

use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsType extends TranslatorAwareType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('api_key', TextType::class, [
                'label' => $this->trans('API key', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Used by the pickup point widget. Found in the Packeta client section under Support & Resources > API keys.', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('api_password', TextType::class, [
                'label' => $this->trans('API password', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Used to submit packets to the Packeta API when exporting orders.', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('sender', TextType::class, [
                'label' => $this->trans('Sender indication', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Sender (eshop) label configured in the Packeta client section. Sent as the "eshop" attribute of each packet.', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('widget_language', TextType::class, [
                'label' => $this->trans('Widget language', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Two-letter code (e.g. "cs", "sk", "en"). Leave empty to use the shop language of the visitor.', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '',
            ])
            ->add('default_weight', NumberType::class, [
                'label' => $this->trans('Default packet weight (kg)', 'Modules.Dppacketa.Admin'),
                'help' => $this->trans('Used when the cart or order has no weight (products without weight set).', 'Modules.Dppacketa.Admin'),
                'required' => false,
                'empty_data' => '1',
                'scale' => 3,
            ]);
    }
}
