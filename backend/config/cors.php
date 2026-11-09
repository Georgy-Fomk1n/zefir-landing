<?php

// Разрешены только фронтенды своего окружения. Браузер шлёт Origin в punycode, поэтому значения —
// в ASCII-форме: https://xn-----mlchgbbqzthe9b5k.xn--p1ai и https://xn--80a9abv.xn-----mlchgbbqzthe9b5k.xn--p1ai
return [
    'paths' => ['api/*'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:5174,http://localhost:4173'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 600,
    // Токены передаются заголовком, cookie не нужны
    'supports_credentials' => false,
];
