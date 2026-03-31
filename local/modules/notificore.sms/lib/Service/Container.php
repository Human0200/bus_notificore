<?php

namespace Notificore\Sms\Service;

use Notificore\Sms\Repository\EventRuleRepository;
use Notificore\Sms\Repository\LogRepository;
use Notificore\Sms\Repository\MessageRepository;
use Notificore\Sms\Repository\ReminderRepository;
use Notificore\Sms\Repository\SettingRepository;

final class Container
{
    private static ?self $instance = null;

    private bool $bootstrapped = false;
    private ?SchemaManager $schemaManager = null;
    private ?EventRegistrar $eventRegistrar = null;
    private ?AgentRegistrar $agentRegistrar = null;
    private ?SettingRepository $settingRepository = null;
    private ?EventRuleRepository $eventRuleRepository = null;
    private ?ReminderRepository $reminderRepository = null;
    private ?MessageRepository $messageRepository = null;
    private ?LogRepository $logRepository = null;
    private ?NotificoreClientFactory $clientFactory = null;
    private ?SmsDispatchService $smsDispatchService = null;
    private ?EventSmsService $eventSmsService = null;
    private ?SaleOrderEventService $saleOrderEventService = null;
    private ?ReminderService $reminderService = null;
    private ?StatusService $statusService = null;

    public static function getInstance(): self
    {
        $instance = self::$instance ??= new self();
        $instance->boot();

        return $instance;
    }

    public function schemaManager(): SchemaManager
    {
        return $this->schemaManager ??= new SchemaManager();
    }

    public function eventRegistrar(): EventRegistrar
    {
        return $this->eventRegistrar ??= new EventRegistrar();
    }

    public function agentRegistrar(): AgentRegistrar
    {
        return $this->agentRegistrar ??= new AgentRegistrar();
    }

    public function settingsRepository(): SettingRepository
    {
        return $this->settingRepository ??= new SettingRepository();
    }

    public function eventRuleRepository(): EventRuleRepository
    {
        return $this->eventRuleRepository ??= new EventRuleRepository();
    }

    public function reminderRepository(): ReminderRepository
    {
        return $this->reminderRepository ??= new ReminderRepository();
    }

    public function messageRepository(): MessageRepository
    {
        return $this->messageRepository ??= new MessageRepository();
    }

    public function logRepository(): LogRepository
    {
        return $this->logRepository ??= new LogRepository();
    }

    public function clientFactory(): NotificoreClientFactory
    {
        return $this->clientFactory ??= new NotificoreClientFactory($this->settingsRepository());
    }

    public function smsDispatchService(): SmsDispatchService
    {
        return $this->smsDispatchService ??= new SmsDispatchService(
            $this->settingsRepository(),
            $this->messageRepository(),
            $this->logRepository(),
            $this->clientFactory()
        );
    }

    public function eventSmsService(): EventSmsService
    {
        return $this->eventSmsService ??= new EventSmsService(
            $this->eventRuleRepository(),
            $this->logRepository(),
            $this->smsDispatchService()
        );
    }

    public function saleOrderEventService(): SaleOrderEventService
    {
        return $this->saleOrderEventService ??= new SaleOrderEventService(
            $this->eventSmsService(),
            $this->logRepository()
        );
    }

    public function reminderService(): ReminderService
    {
        return $this->reminderService ??= new ReminderService(
            $this->reminderRepository(),
            $this->eventSmsService(),
            $this->logRepository()
        );
    }

    public function statusService(): StatusService
    {
        return $this->statusService ??= new StatusService(
            $this->messageRepository(),
            $this->logRepository(),
            $this->clientFactory()
        );
    }

    private function boot(): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->schemaManager()->ensure();
        $this->settingsRepository()->ensureDefaults();
        $this->eventRegistrar()->ensure();
        $this->agentRegistrar()->ensure();
        $this->bootstrapped = true;
    }
}