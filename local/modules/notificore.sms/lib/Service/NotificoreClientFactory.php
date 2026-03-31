<?php

namespace Notificore\Sms\Service;

use Notificore\Sms\Integration\Notificore\NotificoreClient;
use Notificore\Sms\Repository\SettingRepository;

final class NotificoreClientFactory
{
    public function __construct(
        private readonly SettingRepository $settingRepository
    ) {
    }

    public function create(): NotificoreClient
    {
        $settings = $this->settingRepository->getAll();

        return new NotificoreClient(
            baseUrl: (string)($settings['base_url'] ?? ''),
            apiKey: (string)($settings['api_key'] ?? ''),
            originator: (string)($settings['originator'] ?? ''),
            apiKeyHeader: (string)($settings['api_key_header'] ?? 'X-API-KEY'),
            verifySsl: strtoupper((string)($settings['verify_ssl'] ?? 'Y')) === 'Y',
            smsSendPath: (string)($settings['sms_send_path'] ?? '/v1.0/sms/create'),
            balancePath: (string)($settings['balance_path'] ?? '/rest/common/balance'),
            smsStatusPath: (string)($settings['sms_status_path'] ?? '/v1.0/sms/{id}'),
            smsStatusReferencePath: (string)($settings['sms_status_reference_path'] ?? '/v1.0/sms/reference/{reference}'),
            callbackUrl: $this->settingRepository->buildCallbackUrl($settings),
            validity: (string)($settings['validity'] ?? ''),
            tariff: (string)($settings['tariff'] ?? ''),
        );
    }
}