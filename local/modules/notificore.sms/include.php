<?php

use Bitrix\Main\Loader;

if (!defined('NOTIFICORE_SMS_AUTOLOAD_REGISTERED')) {
    define('NOTIFICORE_SMS_AUTOLOAD_REGISTERED', true);

    spl_autoload_register(static function (string $className): void {
        $prefix = 'Notificore\\Sms\\';

        if (strncmp($className, $prefix, strlen($prefix)) !== 0) {
            return;
        }

        $relativePath = str_replace('\\', '/', substr($className, strlen($prefix)));
        $filePath = __DIR__ . '/lib/' . $relativePath . '.php';

        if (is_file($filePath)) {
            require_once $filePath;
        }
    });
}

if (class_exists(Loader::class)) {
    Loader::registerAutoLoadClasses(null, []);
}
