<?php

namespace Notificore\Sms\Service;

use Notificore\Sms\Repository\LogRepository;
use Notificore\Sms\Repository\MessageRepository;
use Notificore\Sms\Repository\SettingRepository;
use RuntimeException;
use Throwable;

final class SmsDispatchService
{
    public function __construct(
        private readonly SettingRepository $settingRepository,
        private readonly MessageRepository $messageRepository,
        private readonly LogRepository $logRepository,
        private readonly NotificoreClientFactory $clientFactory,
    ) {
    }

    public function send(array $payload): array
    {
        $settings = $this->settingRepository->getAll();
        $force = $this->toBool($payload['force'] ?? false);
        $externalId = trim((string)($payload['external_id'] ?? ''));

        if (!$force && !$this->settingRepository->isIntegrationEnabled($settings)) {
            $message = 'Интеграция отключена в настройках модуля.';
            $this->logRepository->add('warning', 'sms_send_skipped', $message, ['payload' => $payload]);

            return [
                'success' => false,
                'error_message' => $message,
                'data' => null,
            ];
        }

        if ($externalId !== '') {
            $existing = $this->messageRepository->findByExternalId($externalId);

            if ($existing !== null) {
                $this->logRepository->add('warning', 'duplicate_skip', 'Повторная отправка пропущена.', [
                    'message_id' => $existing['id'],
                    'external_id' => $externalId,
                ]);

                return [
                    'success' => true,
                    'duplicate' => true,
                    'data' => $existing,
                ];
            }
        }

        $phone = trim((string)($payload['phone'] ?? ''));
        $messageText = trim((string)($payload['message'] ?? ''));

        if ($phone === '' || $messageText === '') {
            throw new RuntimeException('Phone or message is empty');
        }

        $sendResult = [];
        $success = false;
        $status = 'failed';
        $errorMessage = '';

        try {
            $client = $this->clientFactory->create();
            $reference = trim((string)($payload['provider_reference'] ?? ''));

            if ($reference === '') {
                $reference = $this->buildReference($externalId, $phone, $messageText);
            }

            $sendResult = $client->sendSms($phone, $messageText, $reference);
            $success = (bool)($sendResult['success'] ?? false);
            $status = (string)($sendResult['status'] ?? ($success ? 'accepted' : 'failed'));
            $errorMessage = trim((string)($sendResult['error_message'] ?? ''));
        } catch (Throwable $exception) {
            $sendResult = [
                'success' => false,
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ];
            $errorMessage = $exception->getMessage();
        }

        $record = $this->messageRepository->add([
            'created_at' => date(DATE_ATOM),
            'status_updated_at' => date(DATE_ATOM),
            'source' => (string)($payload['source'] ?? 'system'),
            'channel' => 'sms',
            'is_test' => $this->toBool($payload['is_test'] ?? false),
            'phone' => $phone,
            'message' => $messageText,
            'status' => $status,
            'error_message' => $errorMessage,
            'provider_message_id' => (string)($sendResult['provider_message_id'] ?? ''),
            'provider_reference' => (string)($sendResult['provider_reference'] ?? ''),
            'external_id' => $externalId,
            'rule_id' => (int)($payload['rule_id'] ?? 0),
            'event_type' => (string)($payload['event_type'] ?? ''),
            'event_code' => (string)($payload['event_code'] ?? ''),
            'send_result' => $sendResult,
            'raw_payload' => is_array($payload['raw_payload'] ?? null) ? $payload['raw_payload'] : [],
        ]);

        $this->logRepository->add($success ? 'info' : 'error', 'sms_send', $success ? 'SMS отправлено в Notificore.' : 'Ошибка отправки SMS.', [
            'message_id' => $record['id'] ?? 0,
            'status' => $status,
            'provider_message_id' => $record['provider_message_id'] ?? '',
            'provider_reference' => $record['provider_reference'] ?? '',
            'error_message' => $errorMessage,
            'event_type' => $record['event_type'] ?? '',
            'event_code' => $record['event_code'] ?? '',
        ]);

        return [
            'success' => $success,
            'duplicate' => false,
            'data' => $record,
        ];
    }

    private function buildReference(string $externalId, string $phone, string $message): string
    {
        if ($externalId !== '') {
            return 'nf' . substr(md5($externalId), 0, 30);
        }

        return 'nf' . substr(md5($phone . $message . microtime(true)), 0, 30);
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower(trim((string)$value)), ['1', 'y', 'yes', 'true', 'on'], true);
    }
}