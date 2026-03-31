<?php

namespace Notificore\Sms\Orm;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class LogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_notificore_logs';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary(true)->configureAutocomplete(true),
            new DatetimeField('CREATED_AT'),
            (new StringField('LEVEL'))->configureSize(20),
            (new StringField('EVENT_TYPE'))->configureSize(50),
            (new TextField('MESSAGE'))->configureRequired(true),
            new TextField('CONTEXT_JSON'),
        ];
    }
}
