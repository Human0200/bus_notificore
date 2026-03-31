<?php

namespace Notificore\Sms\Agent;

use Notificore\Sms\Service\Container;

final class StatusSyncAgent
{
    public static function run(): string
    {
        Container::getInstance()->statusService()->syncPending(20);

        return '\\Notificore\\Sms\\Agent\\StatusSyncAgent::run();';
    }
}