<?php

return [
    'phone' => env('SHOP_PHONE', '+7 (968) 479-15-20'),
    'phone_href' => 'tel:' . preg_replace('/[^\d+]/', '', env('SHOP_PHONE', '+79684791520')),
    'telegram' => 'https://t.me/Krasolda_Tihonovna',
    'vk' => 'https://vk.ru/zefir_i_pryanik.moscow',
    'max' => 'https://max.ru/id772442265466_biz',
    'flowwow' => 'https://flowwow.com/shop/zefir-i-pryanik',
    'address' => 'ул. Базовская, 17, Москва',
    'courier_fee' => (int) env('SHOP_COURIER_FEE', 390),

    // Часы работы. closed_days: 0 = воскресенье … 6 = суббота (1 = понедельник выходной)
    'hours' => ['open' => 10, 'close' => 21, 'closed_days' => [1]],

    // Выдача: на сколько дней вперёд можно выбрать и за сколько часов до слота заказ ещё принимается
    'schedule' => [
        'days' => 28,
        'lead_hours' => 2,
        'parts' => [['Утро', [10, 11]], ['День', [12, 13, 15]], ['Вечер', [16, 17, 18, 19]]],
    ],

    'notify' => [
        'telegram_token' => env('TELEGRAM_BOT_TOKEN'),
        'telegram_chat_id' => env('TELEGRAM_CHAT_ID'),
        'mail_to' => env('ORDER_MAIL_TO'),
    ],

    'vapid' => [
        'public' => env('VAPID_PUBLIC_KEY'),
        'private' => env('VAPID_PRIVATE_KEY'),
        'subject' => env('VAPID_SUBJECT', 'mailto:shop@zefiripryanik.ru'),
    ],

    // Сколько секунд держим готовые HTML публичных страниц в кэше (сбрасывается при правке товаров)
    'page_cache_ttl' => (int) env('PAGE_CACHE_TTL', 600),
];
