<?php
/*
 * Настройки магазина. Скопируйте файл в config.php и поправьте значения:
 *   cp config.example.php config.php
 * config.php не попадает в git.
 */
return [
    // Часовой пояс кондитерской: от него зависят слоты выдачи и статус «открыто»
    'timezone' => 'Europe/Moscow',

    'shop' => [
        'phone'      => '+7 (968) 479-15-20',
        'phone_href' => 'tel:+79684791520',
        'telegram'   => 'https://t.me/Krasolda_Tihonovna',
        'vk'         => 'https://vk.ru/zefir_i_pryanik.moscow',
        'max'        => 'https://max.ru/id772442265466_biz',
        'flowwow'    => 'https://flowwow.com/shop/zefir-i-pryanik',
        'address'    => 'ул. Базовская, 17, Москва',
        'courier_fee' => 390,
    ],

    // Часы работы. closed_days: 0 = воскресенье … 6 = суббота (1 = понедельник выходной)
    'hours' => ['open' => 10, 'close' => 21, 'closed_days' => [1]],

    // Сколько дней вперёд можно выбрать выдачу и за сколько часов до слота заказ ещё принимается
    'schedule' => ['days' => 28, 'lead_hours' => 2],

    // Куда присылать новые заказы. Оставьте пустым то, что не нужно.
    'notify' => [
        // Telegram: создайте бота у @BotFather, добавьте его в чат и впишите токен и chat_id
        'telegram_token'   => '',
        'telegram_chat_id' => '',
        // Почта (работает, если на хостинге настроена функция mail())
        'mail_to'   => '',
        'mail_from' => 'shop@zefiripryanik.ru',
    ],

    // Файл базы заказов (SQLite). Папка data/ закрыта от веб-доступа через .htaccess
    'db_path' => __DIR__ . '/data/shop.sqlite',
];
