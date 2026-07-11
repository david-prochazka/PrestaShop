<?php
/**
 * Packeta Delivery Methods — Packeta (Zásilkovna) carriers for PrestaShop 9.
 *
 * @author    David Procházka
 * @copyright 2026 David Procházka
 * @license   https://opensource.org/licenses/MIT MIT License
 */
declare(strict_types=1);

namespace Dapro\Packeta\Api;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Minimal client for the Packeta REST API (https://docs.packeta.com).
 *
 * - createPacket / packetLabelPdf go to the XML REST gateway
 *   (https://www.zasilkovna.cz/api/rest) authenticated by the API password.
 * - The external carrier list is the public JSON feed keyed by the API key
 *   (https://pickup-point.api.packeta.com/v5/{apiKey}/carrier/json).
 */
final class PacketaApiClient
{
    private const REST_ENDPOINT = 'https://www.zasilkovna.cz/api/rest';
    private const CARRIER_FEED = 'https://pickup-point.api.packeta.com/v5/%s/carrier/json?lang=en';
    private const TIMEOUT = 20;

    public function __construct(
        private readonly string $apiPassword,
        private readonly string $apiKey,
    ) {
    }

    /**
     * Creates a packet and returns ['id' => ..., 'barcode' => ...].
     *
     * @param array<string, string> $attributes packetAttributes children (already scalar)
     *
     * @return array{id: string, barcode: string}
     *
     * @throws PacketaApiException
     */
    public function createPacket(array $attributes): array
    {
        if ('' === $this->apiPassword) {
            throw new PacketaApiException('Packeta API password is not configured.');
        }

        $xml = new \SimpleXMLElement('<createPacket/>');
        $xml->addChild('apiPassword', htmlspecialchars($this->apiPassword, ENT_XML1));
        $packet = $xml->addChild('packetAttributes');
        foreach ($attributes as $name => $value) {
            if ('' === (string) $value) {
                continue;
            }
            $packet->addChild($name, htmlspecialchars((string) $value, ENT_XML1));
        }

        $response = $this->postXml($xml->asXML() ?: '');

        return [
            'id' => (string) $response->result->id,
            'barcode' => (string) $response->result->barcode,
        ];
    }

    /**
     * Returns the label PDF binary for a packet.
     *
     * @throws PacketaApiException
     */
    public function packetLabelPdf(string $packetId, string $format = 'A6 on A6', int $offset = 0): string
    {
        if ('' === $this->apiPassword) {
            throw new PacketaApiException('Packeta API password is not configured.');
        }

        $xml = new \SimpleXMLElement('<packetLabelPdf/>');
        $xml->addChild('apiPassword', htmlspecialchars($this->apiPassword, ENT_XML1));
        $xml->addChild('packetId', htmlspecialchars($packetId, ENT_XML1));
        $xml->addChild('format', htmlspecialchars($format, ENT_XML1));
        $xml->addChild('offset', (string) $offset);

        $response = $this->postXml($xml->asXML() ?: '');
        $pdf = base64_decode((string) $response->result, true);

        if (false === $pdf) {
            throw new PacketaApiException('Packeta API returned an invalid label payload.');
        }

        return $pdf;
    }

    /**
     * External carriers usable for home delivery / carrier pickup points.
     *
     * @return array<int, array{id: string, name: string, country: string, pickupPoints: bool}>
     */
    public function getCarriers(): array
    {
        if ('' === $this->apiKey) {
            return [];
        }

        $body = $this->httpRequest(sprintf(self::CARRIER_FEED, rawurlencode($this->apiKey)));
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            throw new PacketaApiException('Packeta carrier feed returned invalid JSON.');
        }

        // The feed wraps the list in {"carriers": [...]} in newer versions.
        $carriers = $decoded['carriers'] ?? $decoded;
        $result = [];
        foreach ($carriers as $carrier) {
            if (!is_array($carrier) || !isset($carrier['id'])) {
                continue;
            }
            $result[] = [
                'id' => (string) $carrier['id'],
                'name' => (string) ($carrier['name'] ?? ''),
                'country' => (string) ($carrier['country'] ?? ''),
                'pickupPoints' => filter_var($carrier['pickupPoints'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }

        return $result;
    }

    /**
     * @throws PacketaApiException
     */
    private function postXml(string $xml): \SimpleXMLElement
    {
        $body = $this->httpRequest(self::REST_ENDPOINT, $xml);

        $previous = libxml_use_internal_errors(true);
        $response = simplexml_load_string($body);
        libxml_use_internal_errors($previous);

        if (false === $response) {
            throw new PacketaApiException('Packeta API returned an unreadable response.');
        }

        if ('ok' !== (string) $response->status) {
            $detail = (string) $response->string;
            if (isset($response->detail->attributes->fault)) {
                foreach ($response->detail->attributes->fault as $fault) {
                    $detail .= sprintf(' [%s: %s]', (string) $fault->name, (string) $fault->fault);
                }
            }

            throw new PacketaApiException('Packeta API error: ' . ('' !== $detail ? $detail : 'unknown fault'));
        }

        return $response;
    }

    /**
     * @throws PacketaApiException
     */
    private function httpRequest(string $url, ?string $xmlBody = null): string
    {
        $curl = curl_init($url);
        if (false === $curl) {
            throw new PacketaApiException('Unable to initialize the HTTP client.');
        }

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::TIMEOUT,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if (null !== $xmlBody) {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $xmlBody,
                CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8'],
            ]);
        }

        $body = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        if (!is_string($body) || '' !== $error) {
            throw new PacketaApiException('Packeta API request failed: ' . ('' !== $error ? $error : 'no response'));
        }

        if ($status >= 400) {
            throw new PacketaApiException('Packeta API request failed with HTTP ' . $status . '.');
        }

        return $body;
    }
}
