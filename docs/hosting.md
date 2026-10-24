# Первичная настройка хостинга (Sprinthost, только FTP)

Хостинг виртуальный: нет SSH и постоянных процессов. Всё, что обычно делают в консоли, делается
через панель Sprinthost, FTP и защищённый пост-деплой хук (`POST /api/_deploy/run`).

## 1. Домены и каталоги

| Хост (в панели — punycode) | Назначение | Корень сайта (docroot) | FTP-каталог (переменная) |
|---|---|---|---|
| `xn-----mlchgbbqzthe9b5k.xn--p1ai` (зефир-и-пряник.рф) | публичный магазин и кабинет покупателя | `~/domains/<домен>/public_html` | `FTP_PUBLIC_DIR` |
| `www.` того же домена | только 301 на домен без www | тот же | — |
| `xn--80a9abv.xn-----mlchgbbqzthe9b5k.xn--p1ai` (штат.…) | админ, продавец, курьер | `~/domains/<штат>/public_html` | `FTP_STAFF_DIR` (отдельный FTP-аккаунт) |
| `api.xn-----mlchgbbqzthe9b5k.xn--p1ai` (api.…) | Laravel API | **`~/zefir-api/public`** | `FTP_API_DIR` = `/zefir-api/` |
| `staging.…`, `xn---staging-06g3pb3e.…` (штат-staging.…), `api-staging.…` | staging | аналогично, отдельные каталоги | переменные Environment `staging` |

Код Laravel лежит **вне** публичной папки (`~/zefir-api/`), наружу смотрит только `~/zefir-api/public`.
Если панель не позволяет указать docroot в подкаталоге, создайте поддомен с корнем `~/zefir-api/public`
через «Сайты → Настройки → Корневая директория» [УТОЧНИТЬ в панели].

## 2. PHP и SSL

- Для всех трёх хостов: PHP **8.5** (Laravel 13), расширения `pdo_mysql`, `mbstring`, `intl`, `openssl`, `gd`.
- SSL: Let's Encrypt для каждого хоста отдельно, включая `штат` и `www`. Sprinthost выпускает сертификаты для
  IDN по punycode-имени. [УТОЧНИТЬ] выпуск для поддомена `штат` (кириллический поддомен в кириллическом домене):
  если выпуск не проходит, используйте wildcard `*.xn-----mlchgbbqzthe9b5k.xn--p1ai`.
- После выпуска включите «Перенаправлять на HTTPS» (дублирует правило в `.htaccess`, это не мешает).

## 3. База данных

Создайте в панели две базы MySQL (production и staging) с отдельными пользователями. Кодировка `utf8mb4`,
сравнение `utf8mb4_unicode_ci`. Таблицы создаёт `php artisan migrate --force` через деплой-хук.

## 4. `.env` (создаётся вручную один раз, деплоем не перезаписывается)

Скопируйте `backend/.env.example` в `~/zefir-api/.env` по FTP и заполните. Ключевые отличия production:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.xn-----mlchgbbqzthe9b5k.xn--p1ai
APP_KEY=base64:…            # сгенерировать локально: php artisan key:generate --show
DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…
SESSION_DRIVER=array
CACHE_STORE=database
QUEUE_CONNECTION=database
CORS_ALLOWED_ORIGINS=https://xn-----mlchgbbqzthe9b5k.xn--p1ai,https://xn--80a9abv.xn-----mlchgbbqzthe9b5k.xn--p1ai
FRONTEND_URL=https://xn-----mlchgbbqzthe9b5k.xn--p1ai
STAFF_URL=https://xn--80a9abv.xn-----mlchgbbqzthe9b5k.xn--p1ai
PUBLIC_SITE_URL=https://зефир-и-пряник.рф
MAIL_MAILER=smtp            # SMTP хостинга [УТОЧНИТЬ]
PAYMENT_PROVIDER=tochka     # после прохождения чек-листа docs/payments.md
DEPLOY_HOOK_ENABLED=true
DEPLOY_HOOK_TOKEN=…         # тот же, что секрет DEPLOY_HOOK_TOKEN в GitHub
REBUILD_ENABLED=true
GITHUB_DISPATCH_TOKEN=…     # fine-grained PAT: только этот репозиторий, Contents: read & write
INDEXNOW_ENABLED=true
INDEXNOW_KEY=…              # тот же, что секрет INDEXNOW_KEY
VAPID_PUBLIC_KEY=…          # Web Push: php artisan push:vapid (локально), см. ниже
VAPID_PRIVATE_KEY=…
VAPID_SUBJECT=mailto:…      # контакт для push-сервисов
NOTIFY_CHANNELS=mail,push
```

**Push-уведомления.** Ключи VAPID генерируются один раз локально командой `php artisan push:vapid` и вписываются
в `.env` production (у staging — свои). Если ключи сменить, все подписки перестанут работать и покупателям придётся
включить уведомления заново. Без ключей кнопка «Включить уведомления» не появляется, остальное работает.
На iPhone push приходят только в веб-приложении, добавленном на экран «Домой» (iOS 16.4+); во вкладке Safari
покупатель и курьер видят инструкцию, как установить.

На staging: `APP_ENV=staging`, свои хосты в CORS и `*_URL`, `PAYMENT_PROVIDER=fake` допустим.
`PAYMENT_PROVIDER=fake` при `APP_ENV=production` приложение не запустит — это защита.

## 5. Cron (панель → «Планировщик задач»)

Очереди работают на драйвере `database`, воркер запускается кроном и завершается, когда задач нет.

```cron
* * * * * /usr/local/bin/php8.5 ~/zefir-api/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/local/bin/php8.5 ~/zefir-api/artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1
```

Путь к PHP 8.5 посмотрите в панели [УТОЧНИТЬ точное имя бинарника, например `/usr/local/bin/php8.5`].
Планировщик (`routes/console.php`) выполняет:

| Команда | Период | Что делает |
|---|---|---|
| `orders:release-expired` | каждую минуту | отменяет неоплаченные заказы, снимает резерв, слот и баллы |
| `payments:reconcile` | 10 минут | сверяет зависшие платежи с провайдером (страховка от потерянных вебхуков) |
| `subscriptions:process` | час | продления, повторы, напоминания со ссылкой, льготный период, истечение |
| `slots:generate` | 03:10 МСК | создаёт слоты на 7 дней вперёд |
| `loyalty:expire` | 03:30 МСК | сжигает просроченные баллы |
| `queue:prune-failed`, `sanctum:prune-expired` | сутки | чистка |

## 6. Первый запуск

SSH нет, поэтому начальное наполнение выполняет деплой-хук сам:

1. В `.env` задайте одноразово `BOOTSTRAP_ADMIN_EMAIL` и `BOOTSTRAP_ADMIN_PASSWORD` (не короче 12 символов).
2. Выполните первый деплой (`develop` → staging, затем `main` → production). Хук после `migrate`:
   - наполнит каталог из `database/data/catalog-seed.json`, если товаров ещё нет;
   - создаст администратора, если администраторов ещё нет.
3. Войдите на `штат.…`, настройте 2FA (QR для приложения-аутентификатора), смените пароль.
4. **Удалите** `BOOTSTRAP_*` из `.env`.
5. Остальных сотрудников (продавцов, курьеров) заводит администратор в разделе «Сотрудники».
6. Права на запись нужны каталогам `storage/` и `bootstrap/cache/` [УТОЧНИТЬ требования Sprinthost к правам, обычно 755].

## 7. Старый домен `zefiripryanik.ru`

Если домен живой и на нём есть трафик [УТОЧНИТЬ], положите в его корень `.htaccess`:

```apache
RewriteEngine On
RewriteRule ^(.*)$ https://xn-----mlchgbbqzthe9b5k.xn--p1ai/$1 [R=301,L]
```

и оформите «Переезд сайта» в Яндекс Вебмастере (docs/seo.md, чек-лист запуска).

## 8. Staging закрыт

- `.htaccess` staging-сборки включает basic auth: создайте `.htpasswd` вне публичной папки
  (`htpasswd -nb user pass` локально) и укажите путь в переменной `STAGING_HTPASSWD_PATH`.
- Логин/пароль для smoke-тестов — секрет `STAGING_BASIC_AUTH` (`user:pass`).
- robots.txt staging-сборки — `Disallow: /`, все страницы `noindex`.
