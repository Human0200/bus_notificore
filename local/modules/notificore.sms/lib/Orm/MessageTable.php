<?php

namespace Notificore\Sms\Orm;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class MessageTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_notificore_messages';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary(true)->configureAutocomplete(true),
            new DatetimeField('CREATED_AT'),
            new DatetimeField('UPDATED_AT'),
            new DatetimeField('STATUS_UPDATED_AT'),
            (new StringField('SOURCE'))->configureSize(50),
            (new StringField('CHANNEL'))->configureSize(20),
            (new StringField('IS_TEST'))->configureSize(1)->configureDefaultValue('N'),
            (new StringField('PHONE'))->configureSize(32),
            new TextField('MESSAGE_TEXT'),
            (new StringField('STATUS'))->configureSize(50),
            new TextField('ERROR_MESSAGE'),
            (new StringField('PROVIDER_MESSAGE_ID'))->configureSize(100),
            (new StringField('PROVIDER_REFERENCE'))->configureSize(100),
            (new StringField('EXTERNAL_ID'))->configureSize(150),
            new IntegerField('RULE_ID'),
            (new StringField('EVENT_TYPE'))->configureSize(50),
            (new StringField('EVENT_CODE'))->configureSize(100),
            new TextField('SEND_RESULT_JSON'),
            new TextField('STATUS_PAYLOAD_JSON'),
            new TextField('RAW_PAYLOAD_JSON'),
        ];
    }
}