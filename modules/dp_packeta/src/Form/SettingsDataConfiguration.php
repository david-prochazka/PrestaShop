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
use PrestaShop\PrestaShop\Core\ConfigurationInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

final class SettingsDataConfiguration implements DataConfigurationInterface
{
    public function __construct(private readonly ConfigurationInterface $configuration)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfiguration(): array
    {
        return [
            'api_key' => (string) $this->configuration->get(\Dp_Packeta::CONFIG_API_KEY),
            'api_password' => (string) $this->configuration->get(\Dp_Packeta::CONFIG_API_PASSWORD),
            'sender' => (string) $this->configuration->get(\Dp_Packeta::CONFIG_SENDER),
            'widget_language' => (string) $this->configuration->get(\Dp_Packeta::CONFIG_WIDGET_LANGUAGE),
            'default_weight' => (float) $this->configuration->get(\Dp_Packeta::CONFIG_DEFAULT_WEIGHT),
        ];
    }

    /**
     * @param array<string, mixed> $configuration
     *
     * @return array<int, array<string, mixed>> validation errors
     */
    public function updateConfiguration(array $configuration): array
    {
        if (!$this->validateConfiguration($configuration)) {
            return [
                [
                    'key' => 'Invalid Packeta settings.',
                    'domain' => 'Modules.Dppacketa.Admin',
                    'parameters' => [],
                ],
            ];
        }

        $this->configuration->set(\Dp_Packeta::CONFIG_API_KEY, trim((string) $configuration['api_key']));
        $this->configuration->set(\Dp_Packeta::CONFIG_API_PASSWORD, trim((string) $configuration['api_password']));
        $this->configuration->set(\Dp_Packeta::CONFIG_SENDER, trim((string) $configuration['sender']));
        $this->configuration->set(\Dp_Packeta::CONFIG_WIDGET_LANGUAGE, strtolower(trim((string) $configuration['widget_language'])));
        $this->configuration->set(\Dp_Packeta::CONFIG_DEFAULT_WEIGHT, (string) max(0, (float) $configuration['default_weight']));

        return [];
    }

    public function validateConfiguration(array $configuration): bool
    {
        return isset($configuration['api_key'], $configuration['api_password'], $configuration['sender'], $configuration['widget_language'], $configuration['default_weight']);
    }
}
