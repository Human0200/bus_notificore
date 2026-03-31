<?php

namespace Notificore\Sms\Helper;

use Bitrix\Main\Type\DateTime;
use DateTimeInterface;
use Throwable;

final class DateTimeHelper
{
    public static function now(): DateTime
    {
        return new DateTime();
    }

    public static function fromString(?string $value): ?DateTime
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        try {
            return new DateTime($value);
        } catch (Throwable) {
            return null;
        }
    }

    public static function toStorage(mixed $value): ?DateTime
    {
        if ($value instanceof DateTime) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return new DateTime($value->format('Y-m-d H:i:s'));
        }

        return self::fromString((string)$value);
    }

    public static function toString(mixed $value, string $format = DATE_ATOM): string
    {
        if ($value instanceof DateTime) {
            return $value->format($format);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($format);
        }

        return trim((string)$value);
    }

    public static function formatForUi(mixed $value): string
    {
        $value = self::toStorage($value);

        return $value ? $value->format('d.m.Y H:i:s') : '—';
    }
}