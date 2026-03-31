<?php

namespace Notificore\Sms\Facade;

use DateTimeInterface;
use Notificore\Sms\Service\Container;

final class ModuleApi
{
    public static function send(array $payload): array
    {
        if (!isset($payload['source']) || trim((string)$payload['source']) === '') {
            $payload['source'] = 'module_api';
        }

        return Container::getInstance()->smsDispatchService()->send($payload);
    }

    public static function trigger(string $eventType, string $eventCode, array $payload): array
    {
        return Container::getInstance()->eventSmsService()->dispatch($eventType, $eventCode, $payload);
    }

    public static function triggerCustom(string $eventCode, array $payload): array
    {
        return self::trigger('custom_event', $eventCode, $payload);
    }

    public static function scheduleReminder(string $eventCode, string $phone, string|DateTimeInterface $sendAt, array $context = [], string $externalId = ''): int
    {
        return Container::getInstance()->reminderService()->schedule($eventCode, $phone, $sendAt, $context, $externalId);
    }
}