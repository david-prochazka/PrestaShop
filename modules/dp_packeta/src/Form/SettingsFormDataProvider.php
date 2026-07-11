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

use PrestaShop\PrestaShop\Core\Configuration\DataConfigurationInterface;
use PrestaShop\PrestaShop\Core\Form\FormDataProviderInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsFormDataProvider implements FormDataProviderInterface
{
    public function __construct(private readonly DataConfigurationInterface $settingsDataConfiguration)
    {
    }

    public function getData(): array
    {
        return $this->settingsDataConfiguration->getConfiguration();
    }

    public function setData(array $data): array
    {
        return $this->settingsDataConfiguration->updateConfiguration($data);
    }
}
