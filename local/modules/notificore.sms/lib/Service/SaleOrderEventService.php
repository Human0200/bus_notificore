<?php

namespace Notificore\Sms\Service;

use Bitrix\Main\Event;
use Bitrix\Sale\Internals\OrderPropsValueTable;
use Bitrix\Sale\Order;
use Notificore\Sms\Repository\LogRepository;
use Throwable;

final class SaleOrderEventService
{
    public function __construct(
        private readonly EventSmsService $eventSmsService,
        private readonly LogRepository $logRepository,
    ) {
    }

    public function handleOrderSaved(Event $event): void
    {
        $isNew = (bool)$event->getParameter('IS_NEW');
        $order = $event->getParameter('ENTITY');

        if (!$isNew || !$order instanceof Order) {
            return;
        }

        try {
            $payload = $this->buildPayload($order);
            $this->eventSmsService->dispatch('sale_order_created', (string)$order->getSiteId(), $payload);
        } catch (Throwable $exception) {
            $this->logRepository->add('error', 'sale_order_event_error', 'Ошибка обработки создания заказа.', [
                'order_id' => (int)$order->getId(),
                'error_message' => $exception->getMessage(),
            ]);
        }
    }

    private function buildPayload(Order $order): array
    {
        $fields = $order->getFieldValues();
        $properties = $this->loadProperties((int)$order->getId());
        $phone = $this->findFirstNotEmpty($properties, ['PHONE', 'MOBILE', 'PERSONAL_PHONE', 'CONTACT_PHONE', 'CLIENT_PHONE', 'TEL']);
        $email = $this->findFirstNotEmpty($properties, ['EMAIL', 'CONTACT_EMAIL', 'CLIENT_EMAIL']);

        return [
            'ORDER_ID' => (int)$order->getId(),
            'ACCOUNT_NUMBER' => (string)$order->getField('ACCOUNT_NUMBER'),
            'USER_ID' => (int)$order->getUserId(),
            'PRICE' => (string)$order->getPrice(),
            'CURRENCY' => (string)$order->getCurrency(),
            'STATUS_ID' => (string)$order->getField('STATUS_ID'),
            'PAYED' => (string)$order->getField('PAYED'),
            'CANCELED' => (string)$order->getField('CANCELED'),
            'SITE_ID' => (string)$order->getSiteId(),
            'PERSON_TYPE_ID' => (string)$order->getPersonTypeId(),
            'PHONE' => $phone,
            'EMAIL' => $email,
            'PROPERTIES' => $properties,
            'FIELDS' => $fields,
        ];
    }

    private function loadProperties(int $orderId): array
    {
        if ($orderId <= 0) {
            return [];
        }

        $properties = [];
        $result = OrderPropsValueTable::getList([
            'filter' => ['=ORDER_ID' => $orderId],
            'select' => ['CODE', 'VALUE', 'NAME'],
        ]);

        while ($row = $result->fetch()) {
            $code = trim((string)($row['CODE'] ?? ''));
            $name = trim((string)($row['NAME'] ?? ''));
            $value = trim((string)($row['VALUE'] ?? ''));

            if ($value === '') {
                continue;
            }

            if ($code !== '') {
                $properties[$code] = $value;
            }

            if ($name !== '' && !isset($properties[$name])) {
                $properties[$name] = $value;
            }
        }

        return $properties;
    }

    private function findFirstNotEmpty(array $properties, array $keys): string
    {
        foreach ($keys as $key) {
            $value = trim((string)($properties[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}