<?php

namespace Notificore\Sms\Helper;

final class ArrayHelper
{
    public static function getByPath(array $data, string $path, mixed $default = null): mixed
    {
        $path = trim($path);

        if ($path === '') {
            return $default;
        }

        foreach (array_unique([$path, mb_strtoupper($path), mb_strtolower($path)]) as $variant) {
            if (array_key_exists($variant, $data)) {
                return $data[$variant];
            }
        }

        $segments = preg_split('/\./', $path) ?: [];
        $current = $data;

        foreach ($segments as $segment) {
            $segment = trim((string)$segment);

            if ($segment === '') {
                continue;
            }

            if (!is_array($current)) {
                return $default;
            }

            $found = false;

            foreach (array_unique([$segment, mb_strtoupper($segment), mb_strtolower($segment)]) as $variant) {
                if (array_key_exists($variant, $current)) {
                    $current = $current[$variant];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return $default;
            }
        }

        return $current;
    }

    public static function stringByPath(array $data, string $path): string
    {
        return self::normalizeScalar(self::getByPath($data, $path));
    }

    public static function flatten(array $data, string $prefix = ''): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $normalizedKey = trim((string)$key);

            if ($normalizedKey === '') {
                continue;
            }

            $path = $prefix !== '' ? $prefix . '.' . $normalizedKey : $normalizedKey;

            if (is_array($value)) {
                if (self::isListOfScalars($value)) {
                    $result[$path] = implode(', ', array_map([self::class, 'normalizeScalar'], $value));
                    continue;
                }

                $result += self::flatten($value, $path);
                continue;
            }

            if (is_object($value)) {
                continue;
            }

            $result[$path] = self::normalizeScalar($value);
        }

        return $result;
    }

    public static function normalizeScalar(mixed $value): string
    {
        if (is_array($value)) {
            if ($value === []) {
                return '';
            }

            return implode(', ', array_map([self::class, 'normalizeScalar'], $value));
        }

        if (is_object($value)) {
            return '';
        }

        return trim((string)$value);
    }

    private static function isListOfScalars(array $value): bool
    {
        foreach ($value as $item) {
            if (is_array($item) || is_object($item)) {
                return false;
            }
        }

        return true;
    }
}