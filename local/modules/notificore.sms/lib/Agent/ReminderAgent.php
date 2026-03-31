<?php

namespace Notificore\Sms\Agent;

use Notificore\Sms\Service\Container;

final class ReminderAgent
{
    public static function run(): string
    {
        Container::getInstance()->reminderService()->processDue(20);

        return '\\Notificore\\Sms\\Agent\\ReminderAgent::run();';
    }
}