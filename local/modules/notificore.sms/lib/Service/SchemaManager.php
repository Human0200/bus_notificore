<?php

namespace Notificore\Sms\Service;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\Type\DateTime;
use Notificore\Sms\Orm\EventRuleTable;

final class SchemaManager
{
    private bool $ensured = false;

    public function ensure(): void
    {
        if ($this->ensured) {
            return;
        }

        $connection = Application::getConnection();

        foreach ($this->getCreateSql() as $sql) {
            $connection->queryExecute($sql);
        }

        $this->ensureMessagesSchema($connection);
        $this->migrateLegacyRules($connection);
        $this->ensured = true;
    }

    private function ensureMessagesSchema(Connection $connection): void
    {
        $fields = array_change_key_case($connection->getTableFields('b_notificore_messages'), CASE_UPPER);

        if (!isset($fields['EVENT_TYPE'])) {
            $connection->queryExecute("ALTER TABLE b_notificore_messages ADD EVENT_TYPE VARCHAR(50) NULL AFTER RULE_ID");
        }

        if (!isset($fields['EVENT_CODE'])) {
            $connection->queryExecute("ALTER TABLE b_notificore_messages ADD EVENT_CODE VARCHAR(100) NULL AFTER EVENT_TYPE");
        }
    }

    private function migrateLegacyRules(Connection $connection): void
    {
        if (!$connection->isTableExists('b_notificore_form_rules')) {
            return;
        }

        $countRow = $connection->query('SELECT COUNT(*) AS CNT FROM b_notificore_event_rules')->fetch();
        $count = (int)($countRow['CNT'] ?? 0);

        if ($count > 0) {
            return;
        }

        $result = $connection->query('SELECT * FROM b_notificore_form_rules');

        while ($row = $result->fetch()) {
            if (trim((string)($row['TRIGGER_TYPE'] ?? '')) !== 'mail_event') {
                continue;
            }

            EventRuleTable::add([
                'ACTIVE' => (string)($row['ACTIVE'] ?? 'Y'),
                'EVENT_TYPE' => 'mail_event',
                'EVENT_CODE' => trim((string)($row['EVENT_NAME'] ?? '')),
                'DESCRIPTION' => trim((string)($row['DESCRIPTION'] ?? '')),
                'PHONE_PATH' => trim((string)($row['PHONE_FIELD_SID'] ?? '')),
                'MESSAGE_TEMPLATE' => trim((string)($row['MESSAGE_TEMPLATE'] ?? '')),
                'EXTERNAL_ID_TEMPLATE' => null,
                'CREATED_AT' => $row['CREATED_AT'] ?? new DateTime(),
                'UPDATED_AT' => $row['UPDATED_AT'] ?? new DateTime(),
            ]);
        }
    }

    private function getCreateSql(): array
    {
        return [
            'CREATE TABLE IF NOT EXISTS b_notificore_settings (
                CODE VARCHAR(100) NOT NULL,
                VALUE MEDIUMTEXT NULL,
                UPDATED_AT DATETIME NOT NULL,
                PRIMARY KEY (CODE)
            )',
            'CREATE TABLE IF NOT EXISTS b_notificore_event_rules (
                ID INT NOT NULL AUTO_INCREMENT,
                ACTIVE CHAR(1) NOT NULL DEFAULT \'Y\',
                EVENT_TYPE VARCHAR(50) NOT NULL,
                EVENT_CODE VARCHAR(100) NULL,
                DESCRIPTION VARCHAR(255) NULL,
                PHONE_PATH VARCHAR(150) NOT NULL,
                MESSAGE_TEMPLATE MEDIUMTEXT NOT NULL,
                EXTERNAL_ID_TEMPLATE VARCHAR(255) NULL,
                CREATED_AT DATETIME NOT NULL,
                UPDATED_AT DATETIME NOT NULL,
                PRIMARY KEY (ID),
                INDEX IX_NOTIFICORE_EVENT_RULES_TYPE (EVENT_TYPE),
                INDEX IX_NOTIFICORE_EVENT_RULES_CODE (EVENT_CODE),
                INDEX IX_NOTIFICORE_EVENT_RULES_ACTIVE (ACTIVE)
            )',
            'CREATE TABLE IF NOT EXISTS b_notificore_messages (
                ID INT NOT NULL AUTO_INCREMENT,
                CREATED_AT DATETIME NOT NULL,
                UPDATED_AT DATETIME NOT NULL,
                STATUS_UPDATED_AT DATETIME NULL,
                SOURCE VARCHAR(50) NOT NULL,
                CHANNEL VARCHAR(20) NOT NULL,
                IS_TEST CHAR(1) NOT NULL DEFAULT \'N\',
                PHONE VARCHAR(32) NOT NULL,
                MESSAGE_TEXT MEDIUMTEXT NOT NULL,
                STATUS VARCHAR(50) NOT NULL,
                ERROR_MESSAGE MEDIUMTEXT NULL,
                PROVIDER_MESSAGE_ID VARCHAR(100) NULL,
                PROVIDER_REFERENCE VARCHAR(100) NULL,
                EXTERNAL_ID VARCHAR(150) NULL,
                RULE_ID INT NULL,
                EVENT_TYPE VARCHAR(50) NULL,
                EVENT_CODE VARCHAR(100) NULL,
                SEND_RESULT_JSON MEDIUMTEXT NULL,
                STATUS_PAYLOAD_JSON MEDIUMTEXT NULL,
                RAW_PAYLOAD_JSON MEDIUMTEXT NULL,
                PRIMARY KEY (ID),
                UNIQUE INDEX UX_NOTIFICORE_MESSAGES_EXTERNAL (EXTERNAL_ID),
                INDEX IX_NOTIFICORE_MESSAGES_STATUS (STATUS),
                INDEX IX_NOTIFICORE_MESSAGES_PROVIDER_ID (PROVIDER_MESSAGE_ID),
                INDEX IX_NOTIFICORE_MESSAGES_REFERENCE (PROVIDER_REFERENCE),
                INDEX IX_NOTIFICORE_MESSAGES_EVENT_TYPE (EVENT_TYPE),
                INDEX IX_NOTIFICORE_MESSAGES_CREATED (CREATED_AT)
            )',
            'CREATE TABLE IF NOT EXISTS b_notificore_logs (
                ID INT NOT NULL AUTO_INCREMENT,
                CREATED_AT DATETIME NOT NULL,
                LEVEL VARCHAR(20) NOT NULL,
                EVENT_TYPE VARCHAR(50) NOT NULL,
                MESSAGE TEXT NOT NULL,
                CONTEXT_JSON MEDIUMTEXT NULL,
                PRIMARY KEY (ID),
                INDEX IX_NOTIFICORE_LOGS_CREATED (CREATED_AT),
                INDEX IX_NOTIFICORE_LOGS_EVENT (EVENT_TYPE)
            )',
            'CREATE TABLE IF NOT EXISTS b_notificore_reminders (
                ID INT NOT NULL AUTO_INCREMENT,
                STATUS VARCHAR(20) NOT NULL DEFAULT \'queued\',
                EVENT_CODE VARCHAR(100) NOT NULL,
                PHONE VARCHAR(32) NOT NULL,
                EXTERNAL_ID VARCHAR(150) NULL,
                CONTEXT_JSON MEDIUMTEXT NULL,
                ERROR_MESSAGE MEDIUMTEXT NULL,
                SEND_AT DATETIME NOT NULL,
                SENT_AT DATETIME NULL,
                CREATED_AT DATETIME NOT NULL,
                UPDATED_AT DATETIME NOT NULL,
                PRIMARY KEY (ID),
                INDEX IX_NOTIFICORE_REMINDERS_STATUS (STATUS),
                INDEX IX_NOTIFICORE_REMINDERS_SEND_AT (SEND_AT)
            )',
        ];
    }
}