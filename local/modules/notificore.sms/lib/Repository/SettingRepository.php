<?php

namespace Notificore\Sms\Repository;

use Notificore\Sms\Helper\DateTimeHelper;
use Notificore\Sms\Helper\RequestHelper;
use Notificore\Sms\Orm\SettingTable;

final class SettingRepository
{
    public function getAll(): array
    {
        return array_replace($this->defaults(), $this->getStoredValues());
    }

    public function save(array $settings): void
    {
        $current = $this->getAll();
        $merged = array_replace($current, $settings);

        foreach ($merged as $code => $value) {
            $this->upsert((string)$code, (string)$value);
        }
    }

    public function ensureDefaults(): void
    {
        $stored = $this->getStoredValues();
        $defaults = $this->defaults();

        if (($stored['callback_token'] ?? '') === '') {
            $defaults['callback_token'] = $this->generateToken();
        }

        foreach ($defaults as $code => $value) {
            if (!array_key_exists($code, $stored) || ($code === 'callback_token' && trim((string)$stored[$code]) === '')) {
                $this->upsert($code, (string)$value);
            }
        }
    }

    public function buildCallbackUrl(?array $settings = null): string
    {
        $settings = is_array($settings) ? $settings : $this->getAll();
        $baseUrl = RequestHelper::resolveBaseUrl((string)($settings['site_base_url'] ?? ''));

        if ($baseUrl === '') {
            return '';
        }

        $token = trim((string)($settings['callback_token'] ?? ''));
        $url = $baseUrl . '/bitrix/tools/notificore.sms/status_callback.php';

        if ($token !== '') {
            $url .= '?token=' . rawurlencode($token);
        }

        return $url;
    }

    public function isIntegrationEnabled(?array $settings = null): bool
    {
        $settings = is_array($settings) ? $settings : $this->getAll();

        return strtoupper((string)($settings['active'] ?? 'Y')) === 'Y';
    }

    public function defaults(): array
    {
        return [
            'active' => 'Y',
            'base_url' => 'https://api.notificore.ru',
            'api_key' => '',
            'api_key_header' => 'X-API-KEY',
            'originator' => '',
            'validity' => '',
            'tariff' => '',
            'verify_ssl' => 'Y',
            'sms_send_path' => '/v1.0/sms/create',
            'balance_path' => '/rest/common/balance',
            'sms_status_path' => '/v1.0/sms/{id}',
            'sms_status_reference_path' => '/v1.0/sms/reference/{reference}',
            'site_base_url' => '',
            'callback_token' => '',
        ];
    }

    private function getStoredValues(): array
    {
        $values = [];
        $result = SettingTable::getList([
            'select' => ['CODE', 'VALUE'],
        ]);

        while ($row = $result->fetch()) {
            $values[(string)$row['CODE']] = (string)($row['VALUE'] ?? '');
        }

        return $values;
    }

    private function upsert(string $code, string $value): void
    {
        $payload = [
            'VALUE' => $value,
            'UPDATED_AT' => DateTimeHelper::now(),
        ];

        $existing = SettingTable::getByPrimary($code)->fetch();

        if ($existing) {
            SettingTable::update($code, $payload);
            return;
        }

        SettingTable::add(['CODE' => $code] + $payload);
    }

    private function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}