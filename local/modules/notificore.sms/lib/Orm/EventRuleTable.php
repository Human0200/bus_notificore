<?php

namespace Notificore\Sms\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class EventRuleTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_notificore_event_rules';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary(true)->configureAutocomplete(true),
            (new StringField('ACTIVE'))->configureSize(1)->configureDefaultValue('Y'),
            (new StringField('EVENT_TYPE'))->configureSize(50)->configureRequired(true),
            (new StringField('EVENT_CODE'))->configureSize(100),
            (new StringField('DESCRIPTION'))->configureSize(255),
            (new StringField('PHONE_PATH'))->configureSize(150)->configureRequired(true),
            (new TextField('MESSAGE_TEMPLATE'))->configureRequired(true),
            (new StringField('EXTERNAL_ID_TEMPLATE'))->configureSize(255),
            new DatetimeField('CREATED_AT'),
            new DatetimeField('UPDATED_AT'),
        ];
    }
}