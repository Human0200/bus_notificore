<?php

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('PUBLIC_AJAX_MODE', true);

use Bitrix\Main\Loader;
use Notificore\Sms\Helper\RequestHelper;
use Notificore\Sms\Service\Container;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

header('Content-Type: application/json; charset=utf-8');

if (!Loader::includeModule('notificore.sms')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Module notificore.sms is not loaded'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return;
}

try {
    $payload = RequestHelper::payload();
    $settingsRepository = Container::getInstance()->settingsRepository();
    $settings = $settingsRepository->getAll();
    $expectedToken = trim((string)($settings['callback_token'] ?? ''));
    $receivedToken = trim((string)($payload['token'] ?? ($_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '')));

    if ($expectedToken !== '' && $receivedToken !== $expectedToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid callback token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return;
    }

    $result = Container::getInstance()->statusService()->handleCallback($payload);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
