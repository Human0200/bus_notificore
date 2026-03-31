<?php

namespace Notificore\Sms\Repository;

use Notificore\Sms\Helper\DateTimeHelper;
use Notificore\Sms\Helper\JsonHelper;
use Notificore\Sms\Orm\MessageTable;

final class MessageRepository
{
    private const FINAL_STATUSES = [
        'delivered',
        'undelivered',
        'failed',
        'rejected',
        'expired',
        'cancelled',
        'canceled',
    ];

    public function add(array $message): array
    {
        $result = MessageTable::add($this->normalize($message));

        return $this->findById((int)$result->getId()) ?? [];
    }

    public function update(int $id, array $patch): bool
    {
        $existing = $this->findById($id);

        if ($existing === null) {
            return false;
        }

        MessageTable::update($id, $this->normalize(array_replace($existing, $patch), false));

        return true;
    }

    public function findById(int $id): ?array
    {
        $row = MessageTable::getByPrimary($id)->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByExternalId(string $externalId): ?array
    {
        if (trim($externalId) === '') {
            return null;
        }

        $row = MessageTable::getList([
            'filter' => ['=EXTERNAL_ID' => $externalId],
            'limit' => 1,
            'order' => ['ID' => 'DESC'],
        ])->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProviderMessageId(string $providerMessageId): ?array
    {
        if (trim($providerMessageId) === '') {
            return null;
        }

        $row = MessageTable::getList([
            'filter' => ['=PROVIDER_MESSAGE_ID' => $providerMessageId],
            'limit' => 1,
            'order' => ['ID' => 'DESC'],
        ])->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByProviderReference(string $providerReference): ?array
    {
        if (trim($providerReference) === '') {
            return null;
        }

        $row = MessageTable::getList([
            'filter' => ['=PROVIDER_REFERENCE' => $providerReference],
            'limit' => 1,
            'order' => ['ID' => 'DESC'],
        ])->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function getRecent(int $limit = 50): array
    {
        $items = [];
        $result = MessageTable::getList([
            'order' => ['CREATED_AT' => 'DESC', 'ID' => 'DESC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            $items[] = $this->hydrate($row);
        }

        return $items;
    }

    public function findPending(int $limit = 20): array
    {
        $items = [];
        $result = MessageTable::getList([
            'filter' => [
                '!@STATUS' => self::FINAL_STATUSES,
                [
                    'LOGIC' => 'OR',
                    '!PROVIDER_MESSAGE_ID' => null,
                    '!PROVIDER_REFERENCE' => null,
                ],
            ],
            'order' => ['CREATED_AT' => 'ASC', 'ID' => 'ASC'],
            'limit' => max(1, $limit),
        ]);

        while ($row = $result->fetch()) {
            $items[] = $this->hydrate($row);
        }

        return $items;
    }

    private function normalize(array $message, bool $isCreate = true): array
    {
        $payload = [
            'UPDATED_AT' => DateTimeHelper::now(),
            'STATUS_UPDATED_AT' => DateTimeHelper::toStorage($message['status_updated_at'] ?? null),
            'SOURCE' => trim((string)($message['source'] ?? 'system')),
            'CHANNEL' => trim((string)($message['channel'] ?? 'sms')),
            'IS_TEST' => $this->toBool($message['is_test'] ?? false) ? 'Y' : 'N',
            'PHONE' => trim((string)($message['phone'] ?? '')),
            'MESSAGE_TEXT' => (string)($message['message_text'] ?? $message['message'] ?? ''),
            'STATUS' => trim((string)($message['status'] ?? 'unknown')),
            'ERROR_MESSAGE' => trim((string)($message['error_message'] ?? '')),
            'PROVIDER_MESSAGE_ID' => $this->nullify($message['provider_message_id'] ?? null),
            'PROVIDER_REFERENCE' => $this->nullify($message['provider_reference'] ?? null),
            'EXTERNAL_ID' => $this->nullify($message['external_id'] ?? null),
            'RULE_ID' => $this->nullifyInt($message['rule_id'] ?? null),
            'EVENT_TYPE' => $this->nullify($message['event_type'] ?? null),
            'EVENT_CODE' => $this->nullify($message['event_code'] ?? null),
            'SEND_RESULT_JSON' => JsonHelper::encode(is_array($message['send_result'] ?? null) ? $message['send_result'] : []),
            'STATUS_PAYLOAD_JSON' => JsonHelper::encode(is_array($message['status_payload'] ?? null) ? $message['status_payload'] : []),
            'RAW_PAYLOAD_JSON' => JsonHelper::encode(is_array($message['raw_payload'] ?? null) ? $message['raw_payload'] : []),
        ];

        if ($isCreate) {
            $payload['CREATED_AT'] = DateTimeHelper::toStorage($message['created_at'] ?? null) ?? DateTimeHelper::now();
        }

        return $payload;
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int)($row['ID'] ?? 0),
            'created_at' => DateTimeHelper::toString($row['CREATED_AT'] ?? ''),
            'updated_at' => DateTimeHelper::toString($row['UPDATED_AT'] ?? ''),
            'status_updated_at' => DateTimeHelper::toString($row['STATUS_UPDATED_AT'] ?? ''),
            'source' => (string)($row['SOURCE'] ?? ''),
            'channel' => (string)($row['CHANNEL'] ?? ''),
            'is_test' => (string)($row['IS_TEST'] ?? 'N') === 'Y',
            'phone' => (string)($row['PHONE'] ?? ''),
            'message' => (string)($row['MESSAGE_TEXT'] ?? ''),
            'message_text' => (string)($row['MESSAGE_TEXT'] ?? ''),
            'status' => (string)($row['STATUS'] ?? ''),
            'error_message' => (string)($row['ERROR_MESSAGE'] ?? ''),
            'provider_message_id' => (string)($row['PROVIDER_MESSAGE_ID'] ?? ''),
            'provider_reference' => (string)($row['PROVIDER_REFERENCE'] ?? ''),
            'external_id' => (string)($row['EXTERNAL_ID'] ?? ''),
            'rule_id' => (int)($row['RULE_ID'] ?? 0),
            'event_type' => (string)($row['EVENT_TYPE'] ?? ''),
            'event_code' => (string)($row['EVENT_CODE'] ?? ''),
            'send_result' => JsonHelper::decodeArray($row['SEND_RESULT_JSON'] ?? null),
            'status_payload' => JsonHelper::decodeArray($row['STATUS_PAYLOAD_JSON'] ?? null),
            'raw_payload' => JsonHelper::decodeArray($row['RAW_PAYLOAD_JSON'] ?? null),
        ];
    }

    private function nullify(mixed $value): ?string
    {
        $value = trim((string)$value);

        return $value === '' ? null : $value;
    }

    private function nullifyInt(mixed $value): ?int
    {
        $value = (int)$value;

        return $value > 0 ? $value : null;
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(mb_strtolower(trim((string)$value)), ['1', 'y', 'yes', 'true', 'on'], true);
    }
}