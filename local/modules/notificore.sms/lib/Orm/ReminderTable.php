<?php

namespace Notificore\Sms\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class ReminderTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_notificore_reminders';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary(true)->configureAutocomplete(true),
            (new StringField('STATUS'))->configureSize(20)->configureDefaultValue('queued'),
            (new StringField('EVENT_CODE'))->configureSize(100)->configureRequired(true),
            (new StringField('PHONE'))->configureSize(32)->configureRequired(true),
            (new StringField('EXTERNAL_ID'))->configureSize(150),
            (new TextField('CONTEXT_JSON')),
            new TextField('ERROR_MESSAGE'),
            new DatetimeField('SEND_AT'),
            new DatetimeField('SENT_AT'),
            new DatetimeField('CREATED_AT'),
            new DatetimeField('UPDATED_AT'),
        ];
    }
}