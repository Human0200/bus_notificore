<?php

namespace Notificore\Sms\Orm;

use Bitrix\Main\Entity\DataManager;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class SettingTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'b_notificore_settings';
    }

    public static function getMap(): array
    {
        return [
            (new StringField('CODE'))->configurePrimary(true),
            new TextField('VALUE'),
            new DatetimeField('UPDATED_AT'),
        ];
    }
}
