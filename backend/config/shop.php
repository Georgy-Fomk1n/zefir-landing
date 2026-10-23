<?php

// Бизнес-настройки магазина. Всё, что помечено [УТОЧНИТЬ] в docs/assumptions.md, задаётся через .env.

return [
    'timezone' => 'Europe/Moscow',

    'frontend_url' => env('FRONTEND_URL', 'http://localhost:5173'),
    // Публичный адрес сайта для ссылок в письмах и уведомлениях (кириллица — так же, как в canonical)
    'public_url' => env('PUBLIC_SITE_URL', 'https://зефир-и-пряник.рф'),
    'staff_url' => env('STAFF_URL', 'http://localhost:5174'),

    'tokens' => [
        // Срок жизни токенов в минутах. Служебные — короткие, с принудительным повторным входом.
        'customer_ttl' => (int) env('SANCTUM_CUSTOMER_TTL', 60 * 24 * 30),
        'staff_ttl' => (int) env('SANCTUM_STAFF_TTL', 60 * 12),
    ],

    'orders' => [
        // Резерв остатков на время оплаты
        'reserve_minutes' => (int) env('ORDER_RESERVE_MINUTES', 30),
        'number_prefix' => 'ZP',
    ],

    'slots' => [
        // [УТОЧНИТЬ] минимальное время на подготовку заказа ко времени
        'prep_minutes' => (int) env('SLOT_PREP_MINUTES', 120),
        'pickup_capacity' => (int) env('SLOT_PICKUP_CAPACITY', 6),
        'courier_capacity' => (int) env('SLOT_COURIER_CAPACITY', 4),
        'length_minutes' => (int) env('SLOT_LENGTH_MINUTES', 60),
        'days_ahead' => (int) env('SLOT_DAYS_AHEAD', 7),
        // Часы работы из текущего сайта: Пн — выходной, Вт–Вс 10:00–21:00 (ISO: 1 = Пн)
        'closed_weekdays' => [1],
        'opens' => '10:00',
        'closes' => '21:00',
        // [УТОЧНИТЬ] часы работы курьеров; по умолчанию совпадают с часами точки
        'courier_opens' => env('COURIER_OPENS', '10:00'),
        'courier_closes' => env('COURIER_CLOSES', '21:00'),
    ],

    'loyalty' => [
        // [УТОЧНИТЬ] правила кешбэка
        'earn_percent' => (float) env('LOYALTY_EARN_PERCENT', 5),
        'max_spend_share' => (float) env('LOYALTY_MAX_SPEND_SHARE', 0.3),
        'expire_days' => (int) env('LOYALTY_EXPIRE_DAYS', 365),
        'qr_ttl_minutes' => 5,
    ],

    'subscriptions' => [
        'renew_days_before' => (int) env('SUBSCRIPTION_RENEW_DAYS_BEFORE', 1),
        'retry_hours' => [6, 24, 48],
        'grace_days' => (int) env('SUBSCRIPTION_GRACE_DAYS', 3),
        'reminder_days_before' => 3,
    ],

    'payments' => [
        'provider' => env('PAYMENT_PROVIDER', 'fake'), // fake | tochka
        'tochka' => [
            'base_url' => env('TOCHKA_BASE_URL', 'https://enter.tochka.com/uapi'),
            'token' => env('TOCHKA_JWT'),
            'customer_code' => env('TOCHKA_CUSTOMER_CODE'),
            'merchant_id' => env('TOCHKA_MERCHANT_ID'),
            // Публичный ключ Точки для проверки подписи вебхуков (JWT RS256), PEM
            'webhook_public_key' => env('TOCHKA_WEBHOOK_PUBLIC_KEY'),
            // Рекуррентные списания включаются только после проверки договора и документации Точки
            'recurring' => (bool) env('TOCHKA_RECURRING', false),
        ],
        // [УТОЧНИТЬ] схема фискализации 54-ФЗ: none | tochka_receipt | external
        'fiscalization' => env('FISCALIZATION', 'none'),
        'tax_system' => env('FISCAL_TAX_SYSTEM', 'usn_income'),
    ],

    'delivery' => [
        'cdek' => ['enabled' => (bool) env('CDEK_ENABLED', false), 'client_id' => env('CDEK_CLIENT_ID'), 'secret' => env('CDEK_SECRET')],
        'russian_post' => ['enabled' => (bool) env('RUSSIAN_POST_ENABLED', false), 'token' => env('RUSSIAN_POST_TOKEN')],
        // [УТОЧНИТЬ] автоназначение курьеров
        'auto_assign' => (bool) env('COURIER_AUTO_ASSIGN', false),
        // [УТОЧНИТЬ] политика телефона клиента для курьера: active_only | masked | always
        'courier_phone_policy' => env('COURIER_PHONE_POLICY', 'active_only'),
        // [УТОЧНИТЬ] приём оплаты при получении
        'cash_on_delivery' => (bool) env('CASH_ON_DELIVERY', false),
        'pickup_address' => 'ул. Базовская, 17, Москва',
        'pickup_point' => ['lat' => 55.880223, 'lng' => 37.511840],
    ],

    // [УТОЧНИТЬ] Яндекс Геосаджест или DaData: yandex | dadata | null
    'geo' => [
        'provider' => env('GEO_PROVIDER', 'null'),
        'yandex_key' => env('YANDEX_GEOSUGGEST_KEY'),
        'dadata_token' => env('DADATA_TOKEN'),
    ],

    // [УТОЧНИТЬ] каналы уведомлений клиентов: mail, telegram, sms
    'notifications' => [
        'channels' => array_filter(explode(',', env('NOTIFY_CHANNELS', 'mail'))),
        'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'sms_provider' => env('SMS_PROVIDER', 'log'),
    ],

    'rebuild' => [
        // repository_dispatch для пересборки статики после изменений каталога
        'enabled' => (bool) env('REBUILD_ENABLED', false),
        'repository' => env('GITHUB_REPOSITORY', 'georgy-fomk1n/zefir-landing'),
        'token' => env('GITHUB_DISPATCH_TOKEN'),
        'event' => env('GITHUB_DISPATCH_EVENT', 'catalog-updated'),
        'environment' => env('APP_ENV'),
        'debounce_minutes' => 5,
    ],

    'indexnow' => [
        'enabled' => (bool) env('INDEXNOW_ENABLED', false),
        'key' => env('INDEXNOW_KEY'),
        'endpoint' => 'https://yandex.com/indexnow',
    ],

    'deploy_hook' => [
        'enabled' => (bool) env('DEPLOY_HOOK_ENABLED', false),
        'token' => env('DEPLOY_HOOK_TOKEN'),
        // Через запятую; пусто — без ограничения по IP
        'allowed_ips' => array_filter(explode(',', (string) env('DEPLOY_HOOK_IPS', ''))),
    ],
];
