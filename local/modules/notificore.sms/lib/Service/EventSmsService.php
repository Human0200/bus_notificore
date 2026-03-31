<?php

namespace Notificore\Sms\Service;

use Notificore\Sms\Helper\ArrayHelper;
use Notificore\Sms\Helper\JsonHelper;
use Notificore\Sms\Helper\TemplateHelper;
use Notificore\Sms\Repository\EventRuleRepository;
use Notificore\Sms\Repository\LogRepository;

final class EventSmsService
{
    public function __construct(
        private readonly EventRuleRepository $eventRuleRepository,
        private readonly LogRepository $logRepository,
        private readonly SmsDispatchService $smsDispatchService,
    ) {
    }

    public function dispatch(string $eventType, string $eventCode, array $payload, array $meta = []): array
    {
        $eventType = trim($eventType);
        $eventCode = trim($eventCode);

        if ($eventType === '') {
            return [
                'matched_rules' => 0,
                'sent_count' => 0,
                'results' => [],
            ];
        }

        $rules = $this->eventRuleRepository->findActiveForEvent($eventType, $eventCode);
        $context = $this->buildContext($eventType, $eventCode, $payload, $meta);
        $results = [];
        $sentCount = 0;

        foreach ($rules as $rule) {
            $phone = ArrayHelper::stringByPath($payload, (string)$rule['phone_path']);
            $message = TemplateHelper::render((string)$rule['message_template'], $context);

            if ($phone === '') {
                $this->logRepository->add('error', 'event_sms_error', 'Не удалось извлечь телефон из полезной нагрузки события.', [
                    'event_type' => $eventType,
                    'event_code' => $eventCode,
                    'rule_id' => $rule['id'],
                    'phone_path' => $rule['phone_path'],
                    'payload' => $payload,
                ]);
                continue;
            }

            if ($message === '') {
                $this->logRepository->add('error', 'event_sms_error', 'Шаблон SMS стал пустым после подстановки данных события.', [
                    'event_type' => $eventType,
                    'event_code' => $eventCode,
                    'rule_id' => $rule['id'],
                ]);
                continue;
            }

            $result = $this->smsDispatchService->send([
                'phone' => $phone,
                'message' => $message,
                'source' => $eventType,
                'event_type' => $eventType,
                'event_code' => $eventCode,
                'rule_id' => $rule['id'],
                'external_id' => $this->buildExternalId($rule, $context, $phone, $message),
                'raw_payload' => [
                    'payload' => $payload,
                    'meta' => $meta,
                ],
            ]);

            if (($result['success'] ?? false) === true) {
                $sentCount++;
            }

            $results[] = [
                'rule' => $rule,
                'result' => $result,
            ];
        }

        return [
            'matched_rules' => count($rules),
            'sent_count' => $sentCount,
            'results' => $results,
        ];
    }

    private function buildContext(string $eventType, string $eventCode, array $payload, array $meta): array
    {
        $context = [
            'EVENT_TYPE' => $eventType,
            'EVENT_CODE' => $eventCode,
        ];

        foreach (ArrayHelper::flatten($payload) as $key => $value) {
            $context[$key] = $value;
        }

        foreach (ArrayHelper::flatten($meta) as $key => $value) {
            $context[$key] = $value;
        }

        return $context;
    }

    private function buildExternalId(array $rule, array $context, string $phone, string $message): string
    {
        $template = trim((string)($rule['external_id_template'] ?? ''));

        if ($template !== '') {
            return TemplateHelper::render($template, $context + [
                'RULE_ID' => (int)($rule['id'] ?? 0),
            ]);
        }

        foreach (['ORDER_ID', 'REMINDER_ID', 'ID', 'ENTITY_ID', 'REFERENCE'] as $key) {
            if (trim((string)($context[$key] ?? '')) !== '') {
                return sprintf('%s:%s:%d:%s', (string)($context['EVENT_TYPE'] ?? 'event'), (string)($context['EVENT_CODE'] ?? ''), (int)($rule['id'] ?? 0), trim((string)$context[$key]));
            }
        }

        return 'event:' . substr(md5($phone . $message . JsonHelper::encode($context)), 0, 40);
    }
}