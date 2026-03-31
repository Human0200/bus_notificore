<?php

namespace Notificore\Sms\Integration\Notificore;

use Notificore\Sms\Http\CurlHttpClient;
use RuntimeException;

final class NotificoreClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly string $originator,
        private readonly string $apiKeyHeader = 'X-API-KEY',
        private readonly bool $verifySsl = true,
        private readonly string $smsSendPath = '/v1.0/sms/create',
        private readonly string $balancePath = '/rest/common/balance',
        private readonly string $smsStatusPath = '/v1.0/sms/{id}',
        private readonly string $smsStatusReferencePath = '/v1.0/sms/reference/{reference}',
        private readonly string $callbackUrl = '',
        private readonly string $validity = '',
        private readonly string $tariff = '',
        private readonly ?CurlHttpClient $httpClient = null,
    ) {
    }

    public function sendSms(string $phone, string $message, string $reference = ''): array
    {
        $this->guardSmsConfig();

        $normalizedPhone = $this->normalizePhone($phone);

        if ($normalizedPhone === '') {
            throw new RuntimeException('Phone is empty after normalization');
        }

        $reference = trim($reference) !== '' ? trim($reference) : $this->buildReference($normalizedPhone, $message);
        $payload = [
            'destination' => 'phone',
            'msisdn' => $normalizedPhone,
            'reference' => $reference,
            'originator' => $this->originator,
            'body' => $message,
        ];

        if ($this->validity !== '') {
            $payload['validity'] = (int)$this->validity;
        }

        if ($this->tariff !== '') {
            $payload['tariff'] = (int)$this->tariff;
        }

        if (trim($this->callbackUrl) !== '') {
            $payload['callback_url'] = trim($this->callbackUrl);
        }

        $response = $this->request('POST', $this->smsSendPath, $payload);
        $body = $this->extractPayload($response);

        return [
            'success' => $this->isSuccessfulResponse($response),
            'status' => $this->extractSendStatus($response),
            'provider_message_id' => (string)($body['id'] ?? ''),
            'provider_reference' => (string)($body['reference'] ?? $reference),
            'price' => (string)($body['price'] ?? ''),
            'currency' => (string)($body['currency'] ?? ''),
            'channel' => 'sms',
            'error_message' => $this->extractErrorMessage($response),
            'request_payload' => $payload,
            'response' => $response,
        ];
    }

    public function getBalance(): array
    {
        $this->guardApiConfig();

        $response = $this->request('GET', $this->balancePath);
        $body = $this->extractPayload($response);

        return [
            'success' => $this->isSuccessfulResponse($response),
            'balance' => (string)($body['balance'] ?? $body['amount'] ?? ''),
            'currency' => (string)($body['currency'] ?? 'RUB'),
            'error_message' => $this->extractErrorMessage($response),
            'response' => $response,
        ];
    }

    public function getSmsStatus(string $providerMessageId): array
    {
        $this->guardApiConfig();

        $path = str_replace('{id}', rawurlencode($providerMessageId), $this->smsStatusPath);
        $response = $this->request('GET', $path);
        $body = $this->extractPayload($response);

        return [
            'success' => $this->isSuccessfulResponse($response),
            'status' => mb_strtolower((string)($body['status'] ?? 'unknown')),
            'provider_message_id' => (string)($body['id'] ?? $providerMessageId),
            'provider_reference' => (string)($body['reference'] ?? ''),
            'error_message' => $this->extractErrorMessage($response),
            'response' => $response,
        ];
    }

    public function getSmsStatusByReference(string $reference): array
    {
        $this->guardApiConfig();

        $path = str_replace('{reference}', rawurlencode($reference), $this->smsStatusReferencePath);
        $response = $this->request('GET', $path);
        $body = $this->extractPayload($response);

        return [
            'success' => $this->isSuccessfulResponse($response),
            'status' => mb_strtolower((string)($body['status'] ?? 'unknown')),
            'provider_message_id' => (string)($body['id'] ?? ''),
            'provider_reference' => (string)($body['reference'] ?? $reference),
            'error_message' => $this->extractErrorMessage($response),
            'response' => $response,
        ];
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
        $client = $this->httpClient ?? new CurlHttpClient();

        return $client->request(
            method: $method,
            url: $url,
            payload: $payload,
            headers: [
                'Content-Type: text/json; charset=utf-8',
                'Accept: application/json',
                $this->resolveApiKeyHeaderName() . ': ' . $this->apiKey,
            ],
            format: 'json',
            verifySsl: $this->verifySsl,
        );
    }

    private function extractPayload(array $response): array
    {
        $body = $response['body_json'] ?? [];

        if (!is_array($body)) {
            return [];
        }

        if (isset($body['result']) && is_array($body['result'])) {
            return $body['result'];
        }

        if (isset($body['data']) && is_array($body['data'])) {
            return $body['data'];
        }

        return $body;
    }

    private function guardSmsConfig(): void
    {
        $this->guardApiConfig();

        if ($this->originator === '') {
            throw new RuntimeException('Notificore originator is empty');
        }

        if (mb_strlen($this->originator) > 14) {
            throw new RuntimeException('Notificore originator must be 14 chars or less');
        }

        if ($this->validity !== '') {
            $validity = (int)$this->validity;

            if ($validity < 1 || $validity > 72) {
                throw new RuntimeException('Notificore validity must be from 1 to 72');
            }
        }

        if ($this->tariff !== '') {
            $tariff = (int)$this->tariff;

            if ($tariff < 0 || $tariff > 9) {
                throw new RuntimeException('Notificore tariff must be from 0 to 9');
            }
        }
    }

    private function guardApiConfig(): void
    {
        if (trim($this->baseUrl) === '') {
            throw new RuntimeException('Notificore baseUrl is empty');
        }

        if (trim($this->apiKey) === '') {
            throw new RuntimeException('Notificore apiKey is empty');
        }
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($normalized) === 11 && $normalized[0] === '8') {
            return '7' . substr($normalized, 1);
        }

        if (strlen($normalized) === 10) {
            return '7' . $normalized;
        }

        return $normalized;
    }

    private function buildReference(string $phone, string $message): string
    {
        return 'nf' . substr(md5($phone . $message . microtime(true)), 0, 30);
    }

    private function isSuccessfulResponse(array $response): bool
    {
        $body = $response['body_json'] ?? [];
        $error = (string)($body['error'] ?? '0');

        return $response['http_code'] >= 200
            && $response['http_code'] < 300
            && ($error === '' || $error === '0');
    }

    private function extractSendStatus(array $response): string
    {
        if ($this->isSuccessfulResponse($response)) {
            return 'accepted';
        }

        $body = $this->extractPayload($response);

        return mb_strtolower((string)($body['status'] ?? 'http_error'));
    }

    private function extractErrorMessage(array $response): string
    {
        $body = $response['body_json'] ?? [];
        $payload = $this->extractPayload($response);

        return trim((string)(
            $body['errorDescription']
            ?? $body['error_description']
            ?? $payload['errorDescription']
            ?? $payload['error_description']
            ?? $body['message']
            ?? $payload['message']
            ?? ''
        ));
    }

    private function resolveApiKeyHeaderName(): string
    {
        $header = trim($this->apiKeyHeader);

        return $header !== '' ? $header : 'X-API-KEY';
    }
}