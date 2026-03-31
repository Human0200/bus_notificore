<?php

namespace Notificore\Sms\Repository;

use Notificore\Sms\Helper\DateTimeHelper;
use Notificore\Sms\Helper\JsonHelper;
use Notificore\Sms\Orm\ReminderTable;

final class ReminderRepository
{
    public function add(array $reminder): int
    {
        $result = ReminderTable::add([
            'STATUS' => 'queued',
            'EVENT_CODE' => trim((string)($reminder['event_code'] ?? '')),
            'PHONE' => trim((string)($reminder['phone'] ?? '')),
            'EXTERNAL_ID' => $this->nullify($reminder['external_id'] ?? null),
            'CONTEXT_JSON' => JsonHelper::encode(is_array($reminder['context'] ?? null) ? $reminder['context'] : []),
            'SEND_AT' => DateTimeHelper::toStorage($reminder['send_at'] ?? null),
            'CREATED_AT' => DateTimeHelper::now(),
            'UPDATED_AT' => DateTimeHelper::now(),
        ]);

        return (int)$result->getId();
    }

    public function getDue(int $limit = 20): array
    {
        $items = [];
        $result = ReminderTable::getList([
            'filter' => [
                '=STATUS' => 'queued',
                '<=SEND_AT' => DateTimeHelper::now(),
            ],
            'order' => ['SEND_AT' => 'ASC', 'ID' => 'ASC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            $items[] = $this->hydrate($row);
        }

        return $items;
    }

    public function getRecent(int $limit = 30): array
    {
        $items = [];
        $result = ReminderTable::getList([
            'order' => ['CREATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            $items[] = $this->hydrate($row);
        }

        return $items;
    }

    public function markProcessed(int $id, string $errorMessage = ''): void
    {
        ReminderTable::update($id, [
            'STATUS' => trim($errorMessage) === '' ? 'processed' : 'failed',
            'ERROR_MESSAGE' => trim($errorMessage),
            'SENT_AT' => DateTimeHelper::now(),
            'UPDATED_AT' => DateTimeHelper::now(),
        ]);
    }

    public function markFailed(int $id, string $errorMessage): void
    {
        ReminderTable::update($id, [
            'STATUS' => 'failed',
            'ERROR_MESSAGE' => trim($errorMessage),
            'UPDATED_AT' => DateTimeHelper::now(),
        ]);
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int)($row['ID'] ?? 0),
            'status' => (string)($row['STATUS'] ?? ''),
            'event_code' => (string)($row['EVENT_CODE'] ?? ''),
            'phone' => (string)($row['PHONE'] ?? ''),
            'external_id' => (string)($row['EXTERNAL_ID'] ?? ''),
            'context' => JsonHelper::decodeArray($row['CONTEXT_JSON'] ?? null),
            'error_message' => (string)($row['ERROR_MESSAGE'] ?? ''),
            'send_at' => DateTimeHelper::toString($row['SEND_AT'] ?? ''),
            'sent_at' => DateTimeHelper::toString($row['SENT_AT'] ?? ''),
            'created_at' => DateTimeHelper::toString($row['CREATED_AT'] ?? ''),
            'updated_at' => DateTimeHelper::toString($row['UPDATED_AT'] ?? ''),
        ];
    }

    private function nullify(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }
}