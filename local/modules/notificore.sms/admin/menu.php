<?php

return [
    [
        'parent_menu' => 'global_menu_services',
        'sort' => 900,
        'text' => 'Notificore SMS',
        'title' => 'Notificore SMS',
        'icon' => 'form_menu_icon',
        'page_icon' => 'form_page_icon',
        'items_id' => 'menu_notificore_sms',
        'items' => [
            [
                'text' => 'Настройки и история',
                'title' => 'Настройки и история Notificore SMS',
                'url' => 'notificore_sms.php?lang=' . LANGUAGE_ID,
            ],
        ],
    ],
];