<?php

namespace Notificore\Sms\Service;

use Bitrix\Main\EventManager;

final class EventRegistrar
{
    private bool $ensured = false;

    public function ensure(): void
    {
        if ($this->ensured) {
            return;
        }

        $manager = EventManager::getInstance();
        $this->ensureHandler($manager, 'main', 'OnBeforeEventAdd', \Notificore\Sms\Event\MailEventHandler::class, 'onBeforeEventAdd');
        $this->ensureHandler($manager, 'sale', 'OnSaleOrderSaved', \Notificore\Sms\Event\SaleOrderEventHandler::class, 'onSaleOrderSaved');
        $this->ensured = true;
    }

    private function ensureHandler(EventManager $manager, string $fromModule, string $eventName, string $className, string $methodName): void
    {
        foreach ($manager->findEventHandlers($fromModule, $eventName) as $handler) {
            if ((string)($handler['TO_MODULE_ID'] ?? '') !== 'notificore.sms') {
                continue;
            }

            if ((string)($handler['TO_CLASS'] ?? '') === $className && (string)($handler['TO_METHOD'] ?? '') === $methodName) {
                return;
            }
        }

        $manager->registerEventHandlerCompatible($fromModule, $eventName, 'notificore.sms', $className, $methodName);
    }
}