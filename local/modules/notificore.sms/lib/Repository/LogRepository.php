<?php

namespace Notificore\Sms\Repository;

use Notificore\Sms\Helper\DateTimeHelper;
use Notificore\Sms\Helper\JsonHelper;
use Notificore\Sms\Orm\LogTable;

final class LogRepository
{
    public function add(string $level, string $eventType, string $message, array $context = []): void
    {
        LogTable::add([
            'CREATED_AT' => DateTimeHelper::now(),
            'LEVEL' => trim($level) !== '' ? trim($level) : 'info',
            'EVENT_TYPE' => trim($eventType) !== '' ? trim($eventType) : 'system',
            'MESSAGE' => trim($message) !== '' ? trim($message) : 'No message',
            'CONTEXT_JSON' => JsonHelper::encode($context),
        ]);
    }

    public function getRecent(int $limit = 50): array
    {
        $items = [];
        $result = LogTable::getList([
            'order' => ['CREATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            $items[] = [
                'id' => (int)($row['ID'] ?? 0),
                'created_at' => DateTimeHelper::toString($row['CREATED_AT'] ?? ''),
                'level' => (string)($row['LEVEL'] ?? ''),
                'event_type' => (string)($row['EVENT_TYPE'] ?? ''),
                'message' => (string)($row['MESSAGE'] ?? ''),
                'context' => JsonHelper::decodeArray($row['CONTEXT_JSON'] ?? null),
            ];
        }

        return $items;
    }
}
