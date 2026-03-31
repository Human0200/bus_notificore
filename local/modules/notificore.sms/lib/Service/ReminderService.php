<?php

namespace Notificore\Sms\Service;

use DateTime;
use DateTimeInterface;
use Notificore\Sms\Repository\LogRepository;
use Notificore\Sms\Repository\ReminderRepository;
use RuntimeException;

final class ReminderService
{
    public function __construct(
        private readonly ReminderRepository $reminderRepository,
        private readonly EventSmsService $eventSmsService,
        private readonly LogRepository $logRepository,
    ) {
    }

    public function schedule(string $eventCode, string $phone, string|DateTimeInterface $sendAt, array $context = [], string $externalId = ''): int
    {
        $eventCode = trim($eventCode);
        $phone = trim($phone);
        $sendAtValue = $this->normalizeSendAt($sendAt);

        if ($eventCode === '' || $phone === '') {
            throw new RuntimeException('eventCode and phone are required for reminder scheduling');
        }

        return $this->reminderRepository->add([
            'event_code' => $eventCode,
            'phone' => $phone,
            'send_at' => $sendAtValue,
            'context' => $context,
            'external_id' => $externalId,
        ]);
    }

    public function processDue(int $limit = 20): array
    {
        $processed = 0;
        $failed = 0;
        $items = $this->reminderRepository->getDue($limit);

        foreach ($items as $item) {
            $payload = is_array($item['context'] ?? null) ? $item['context'] : [];
            $payload['PHONE'] = (string)$item['phone'];
            $payload['REMINDER_ID'] = (int)$item['id'];
            $payload['SEND_AT'] = (string)$item['send_at'];

            if ((string)($item['external_id'] ?? '') !== '') {
                $payload['EXTERNAL_ID'] = (string)$item['external_id'];
            }

            $result = $this->eventSmsService->dispatch('reminder', (string)$item['event_code'], $payload);

            if ((int)($result['matched_rules'] ?? 0) <= 0) {
                $this->reminderRepository->markFailed((int)$item['id'], 'No active reminder rule found');
                $failed++;
                continue;
            }

            if ((int)($result['sent_count'] ?? 0) <= 0) {
                $this->reminderRepository->markFailed((int)$item['id'], 'Reminder rule matched, but SMS was not sent successfully');
                $failed++;
                continue;
            }

            $this->reminderRepository->markProcessed((int)$item['id']);
            $processed++;
        }

        if ($processed > 0 || $failed > 0) {
            $this->logRepository->add('info', 'reminder_agent', 'Обработана очередь напоминаний.', [
                'processed' => $processed,
                'failed' => $failed,
            ]);
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
        ];
    }

    public function getRecent(int $limit = 30): array
    {
        return $this->reminderRepository->getRecent($limit);
    }

    private function normalizeSendAt(string|DateTimeInterface $sendAt): string
    {
        if ($sendAt instanceof DateTimeInterface) {
            return $sendAt->format(DATE_ATOM);
        }

        $value = trim($sendAt);

        if ($value === '') {
            throw new RuntimeException('sendAt is required');
        }

        return (new DateTime($value))->format(DATE_ATOM);
    }
}