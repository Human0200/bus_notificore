<?php

namespace Notificore\Sms\Event;

use Notificore\Sms\Service\Container;
use Throwable;

final class MailEventHandler
{
    public static function onBeforeEventAdd(&$event, &$lid, &$arFields, &$messageId, &$files, &$languageId = ''): void
    {
        try {
            Container::getInstance()->eventSmsService()->dispatch('mail_event', (string)$event, is_array($arFields) ? $arFields : []);
        } catch (Throwable $exception) {
            Container::getInstance()->logRepository()->add('error', 'mail_event_handler_error', 'Ошибка обработчика OnBeforeEventAdd.', [
                'event_name' => (string)$event,
                'error_message' => $exception->getMessage(),
                'fields' => is_array($arFields) ? $arFields : [],
            ]);
        }
    }
}