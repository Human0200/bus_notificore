<?php

namespace Notificore\Sms\Service;

use Notificore\Sms\Repository\LogRepository;
use Notificore\Sms\Repository\MessageRepository;
use RuntimeException;

final class StatusService
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly LogRepository $logRepository,
        private readonly NotificoreClientFactory $clientFactory,
    ) {
    }

    public function handleCallback(array $payload): array
    {
        $providerMessageId = trim((string)($payload['provider_message_id'] ?? $payload['message_id'] ?? $payload['id'] ?? ''));
        $providerReference = trim((string)($payload['provider_reference'] ?? $payload['reference'] ?? ''));
        $status = mb_strtolower(trim((string)($payload['status'] ?? $payload['delivery_status'] ?? $payload['message_status'] ?? 'unknown')));

        if ($providerMessageId === '' && $providerReference === '') {
            throw new RuntimeException('provider_message_id or reference is required');
        }

        $message = $providerMessageId !== ''
            ? $this->messageRepository->findByProviderMessageId($providerMessageId)
            : null;

        if ($message === null && $providerReference !== '') {
            $message = $this->messageRepository->findByProviderReference($providerReference);
        }

        if ($message === null) {
            $this->logRepository->add('warning', 'status_callback_miss', 'Callback получен, но сообщение в истории не найдено.', $payload);

            return [
                'success' => true,
                'message' => 'Status callback received, but message was not found',
            ];
        }

        $this->messageRepository->update((int)$message['id'], [
            'status' => $status,
            'status_updated_at' => date(DATE_ATOM),
            'provider_message_id' => $providerMessageId !== '' ? $providerMessageId : ($message['provider_message_id'] ?? ''),
            'provider_reference' => $providerReference !== '' ? $providerReference : ($message['provider_reference'] ?? ''),
            'status_payload' => $payload,
        ]);

        $updated = $this->messageRepository->findById((int)$message['id']) ?? $message;

        $this->logRepository->add('info', 'status_callback', 'Статус сообщения обновлён по callback.', [
            'message_id' => $updated['id'] ?? 0,
            'status' => $updated['status'] ?? '',
            'provider_message_id' => $updated['provider_message_id'] ?? '',
            'provider_reference' => $updated['provider_reference'] ?? '',
        ]);

        return [
            'success' => true,
            'data' => $updated,
        ];
    }

    public function syncPending(int $limit = 20): array
    {
        $client = $this->clientFactory->create();
        $messages = $this->messageRepository->findPending($limit);
        $updated = [];
        $skipped = [];

        foreach ($messages as $message) {
            $result = null;

            if (($message['provider_message_id'] ?? '') !== '') {
                $result = $client->getSmsStatus((string)$message['provider_message_id']);
            } elseif (($message['provider_reference'] ?? '') !== '') {
                $result = $client->getSmsStatusByReference((string)$message['provider_reference']);
            }

            if (!is_array($result)) {
                $skipped[] = ['id' => $message['id'], 'reason' => 'no status result'];
                continue;
            }

            $status = trim((string)($result['status'] ?? ''));

            if ($status === '' || $status === 'unknown') {
                $skipped[] = [
                    'id' => $message['id'],
                    'reason' => (string)($result['error_message'] ?? 'unknown status'),
                ];
                continue;
            }

            $this->messageRepository->update((int)$message['id'], [
                'status' => $status,
                'status_updated_at' => date(DATE_ATOM),
                'provider_message_id' => (string)($result['provider_message_id'] ?? ($message['provider_message_id'] ?? '')),
                'provider_reference' => (string)($result['provider_reference'] ?? ($message['provider_reference'] ?? '')),
                'status_payload' => ['source' => 'manual_sync', 'provider_result' => $result],
            ]);

            $updated[] = $this->messageRepository->findById((int)$message['id']) ?? $message;
        }

        $this->logRepository->add('info', 'status_sync', 'Выполнена ручная синхронизация статусов.', [
            'updated_count' => count($updated),
            'skipped_count' => count($skipped),
        ]);

        return [
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'updated_count' => count($updated),
            'skipped_count' => count($skipped),
        ];
    }
}