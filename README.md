# Зефир и пряник — интернет-магазин

Интернет-магазин московской кондитерской «Зефир и пряник»: корпусные пирожные ручной работы, заказ ко времени,
собственная курьерская доставка, карта лояльности, абонементы, личный кабинет. Бывший лендинг сохранён
в `legacy/index.html` — тексты, цены и контакты перенесены оттуда дословно.

| Хост | Что | Код |
|---|---|---|
| `зефир-и-пряник.рф` | магазин и кабинет покупателя: React + пререндер (SSG) | `frontend/` |
| `штат.зефир-и-пряник.рф` | админ, продавец, курьер (PWA), закрыт от индексации | `staff/` |
| `api.зефир-и-пряник.рф` | Laravel 13 API, MySQL, Sanctum (API-токены) | `backend/` |

```
frontend/          публичный сайт: vite-react-ssg, React Router, TanStack Query, react-hook-form + zod
  scripts/         fetch-catalog, media (AVIF/WebP/MP4), postbuild (sitemap, robots, CSP, YML, .htaccess), seo-check
staff/             служебное SPA: админка, продавец (очередь, QR), курьер (офлайн-очередь, PWA)
packages/shared/   общие типы API, клиент API, NAP-данные бренда, подписи статусов
backend/           Laravel: app/Services (оплата, доставка, лояльность, абонементы, заказы), tests/ (Pest)
deploy/htaccess/   .htaccess для публичного и служебного доменов
.github/workflows/ ci.yml (PR), deploy.yml (FTP: develop → staging, main → production)
docs/              хостинг, деплой, БД, SEO, безопасность, оплата, OpenAPI, Lighthouse, [УТОЧНИТЬ]
legacy/            старый одностраничник (источник контента)
```

## Локальный запуск

Нужны Node 22, PHP 8.3+ (на хостинге 8.5), Composer.

```bash
npm ci
cd backend && composer install && cp .env.example .env && php artisan key:generate
touch database/database.sqlite && php artisan migrate --seed   # каталог + тестовые пользователи
php artisan serve                                              # http://localhost:8000
```

```bash
npm run dev -w frontend      # http://localhost:5173 — магазин (dev-сервер без пререндера)
npm run dev -w staff         # http://localhost:5174 — штат
npm run build -w frontend && npm run preview -w frontend   # пререндер + сервер как на хостинге, :4173
```

Тестовые пользователи (только не-production, пароль `password-123`): `admin@example.test` (при входе — настройка 2FA),
`seller@example.test`, `courier@example.test`, `seller-courier@example.test`, `customer@example.test`.
Оплата в разработке — `PAYMENT_PROVIDER=fake`: ссылка ведёт на страницу «Оплатить / Отклонить».

## Проверки

```bash
npm run lint && npm run typecheck && npm test                 # ESLint, tsc, Vitest (frontend, staff, shared)
npm run test:coverage -w frontend && npm run test:coverage -w staff      # то же с порогами покрытия
npm run e2e                                                   # Playwright: магазин, штат, iPhone, push (48 сценариев)
VITE_APP_ENV=production npm run build -w frontend && npm run seo:check   # SEO-проверки пререндера
npx @lhci/cli@0.15.1 autorun                                  # Lighthouse CI (пороги из lighthouserc.json)
cd backend && vendor/bin/pint --test && vendor/bin/pest       # Pint, 125 тестов Pest
php artisan api:openapi                                       # docs/openapi.json из маршрутов
```

## Документация

- [Хостинг Sprinthost: домены, SSL, БД, .env, cron, первый запуск](docs/hosting.md)
- [CI/CD, секреты, деплой и откат](docs/deploy.md)
- [Схема БД и конечные автоматы](docs/database.md)
- [SEO: «запрос → страница», правила редактора, чек-лист запуска, цели Метрики](docs/seo.md)
- [Безопасность и персональные данные](docs/security.md)
- [Оплата Точка Банк](docs/payments.md)
- [API (OpenAPI)](docs/openapi.json)
- [Lighthouse и SEO-проверки](docs/lighthouse.md)
- [Автотесты: уровни, пороги покрытия, E2E](docs/testing.md)
- [Список [УТОЧНИТЬ] и допущений](docs/assumptions.md)
