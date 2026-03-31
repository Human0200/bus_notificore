<?php

namespace Notificore\Sms\Helper;

use Bitrix\Main\Context;

final class RequestHelper
{
    public static function payload(): array
    {
        $request = Context::getCurrent()->getRequest();
        $payload = array_replace_recursive(
            $request->getQueryList()->toArray(),
            $request->getPostList()->toArray()
        );

        $raw = trim((string)file_get_contents('php://input'));

        if ($raw === '') {
            return $payload;
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            return array_replace_recursive($payload, $decoded);
        }

        parse_str($raw, $parsed);

        if (is_array($parsed) && $parsed !== []) {
            return array_replace_recursive($payload, $parsed);
        }

        return $payload;
    }

    public static function resolveBaseUrl(string $configuredBaseUrl = ''): string
    {
        $configuredBaseUrl = rtrim(trim($configuredBaseUrl), '/');

        if ($configuredBaseUrl !== '') {
            return $configuredBaseUrl;
        }

        $request = Context::getCurrent()->getRequest();
        $host = trim((string)$request->getHttpHost());

        if ($host === '') {
            return '';
        }

        return ($request->isHttps() ? 'https' : 'http') . '://' . $host;
    }
}
