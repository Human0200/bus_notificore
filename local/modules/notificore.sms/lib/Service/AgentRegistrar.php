<?php

namespace Notificore\Sms\Service;

final class AgentRegistrar
{
    private bool $ensured = false;

    public function ensure(): void
    {
        if ($this->ensured || !class_exists('CAgent')) {
            return;
        }

        $this->ensureAgent('\\Notificore\\Sms\\Agent\\ReminderAgent::run();', 60);
        $this->ensureAgent('\\Notificore\\Sms\\Agent\\StatusSyncAgent::run();', 300);
        $this->ensured = true;
    }

    private function ensureAgent(string $agentName, int $interval): void
    {
        $existing = \CAgent::GetList([], ['NAME' => $agentName, 'MODULE_ID' => 'notificore.sms'])->Fetch();

        if (!$existing) {
            \CAgent::AddAgent($agentName, 'notificore.sms', 'N', $interval, '', 'Y');
        }
    }
}