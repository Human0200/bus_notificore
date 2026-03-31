<?php

namespace Notificore\Sms\Event;

use Bitrix\Main\Event;
use Notificore\Sms\Service\Container;
use Throwable;

final class SaleOrderEventHandler
{
    public static function onSaleOrderSaved(Event $event): void
    {
        try {
            Container::getInstance()->saleOrderEventService()->handleOrderSaved($event);
        } catch (Throwable $exception) {
            Container::getInstance()->logRepository()->add('error', 'sale_order_handler_error', 'Ошибка обработчика OnSaleOrderSaved.', [
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}