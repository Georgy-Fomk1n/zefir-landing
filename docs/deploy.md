# CI/CD: проверки, деплой по FTP, откат

## Ветки и окружения

| Событие | Что происходит |
|---|---|
| Pull request | `ci.yml`: Pint + Pest (MySQL), ESLint, `tsc`, Vitest, сборка staff, production-сборка с пререндером, SEO-проверки, поиск старого домена, Lighthouse CI. Без деплоя. |
| push в `develop` | `deploy.yml` → **staging**: проверки → backend → публичный сайт + штат → smoke |
| push в `main` | `deploy.yml` → **production**: проверки → ручное подтверждение → backend → сайт + штат → smoke → IndexNow |
| `repository_dispatch: catalog-updated` (из админки) | пересборка только публичного сайта для окружения из payload, без подтверждения → smoke → IndexNow изменённых URL |
| `workflow_dispatch` | ручной запуск: окружение и режим «только публичный сайт» |

Ручное подтверждение production — отдельное Environment **`production-approval`** с Required reviewers.
Деплой кода ждёт подтверждения; пересборка каталога — нет, иначе цены в статике не обновятся сами.

## GitHub Environments

Создайте в Settings → Environments: `staging`, `production`, `production-approval` (только reviewers, без секретов).

### Secrets (в `staging` и `production`, у каждого свои значения)

| Имя | Что это |
|---|---|
| `FTP_SERVER` | FTP-хост Sprinthost |
| `FTP_USERNAME`, `FTP_PASSWORD` | FTP-аккаунт основного домена и API |
| `FTP_STAFF_USERNAME`, `FTP_STAFF_PASSWORD` | отдельный FTP-аккаунт с доступом только к каталогу `штат` |
| `DEPLOY_HOOK_TOKEN` | токен пост-деплой хука, совпадает с `DEPLOY_HOOK_TOKEN` в `.env` |
| `INDEXNOW_KEY` | ключ IndexNow (8–128 символов `[a-zA-Z0-9-]`), совпадает с `.env` |
| `STAGING_BASIC_AUTH` | только staging: `user:pass` для smoke-тестов |

### Variables

| Имя | Пример (production) |
|---|---|
| `API_URL` | `https://api.xn-----mlchgbbqzthe9b5k.xn--p1ai` |
| `SITE_URL` | `https://xn-----mlchgbbqzthe9b5k.xn--p1ai` |
| `STAFF_URL` | `https://xn--80a9abv.xn-----mlchgbbqzthe9b5k.xn--p1ai` |
| `FTP_PUBLIC_DIR`, `FTP_STAFF_DIR`, `FTP_API_DIR` | каталоги на FTP, с `/` в конце |
| `METRIKA_ID`, `YANDEX_VERIFICATION`, `GOOGLE_VERIFICATION`, `GA4_ID` | счётчик и подтверждающие мета-теги (только production) |
| `STAGING_HTPASSWD_PATH` | только staging: абсолютный путь к `.htpasswd` |

Токен для `repository_dispatch` хранится **не в Actions**, а в `.env` бэкенда (`GITHUB_DISPATCH_TOKEN`):
fine-grained PAT, доступ только к этому репозиторию, право `Contents: read & write`.

## Шаги деплоя

1. **Backend**: `composer install --no-dev --optimize-autoloader`, FTP (инкрементально, состояние в
   `.ftp-deploy-backend.json` на сервере). Не загружаются: `.env`, `tests/`, логи, кеши, сессии.
2. **Пост-деплой хук** `POST {API_URL}/api/_deploy/run` с `X-Deploy-Token`: `down` → `migrate --force` →
   `config:cache` → `route:cache` → `view:cache` → `event:cache` → `up`. Ответ `{"ok":true|false}` без деталей;
   подробности — в `storage/logs`. Хук: `hash_equals`, опционально `DEPLOY_HOOK_IPS`, 3 запроса/мин, выключается `DEPLOY_HOOK_ENABLED=false`.
3. **Публичный сайт**: сборка с пререндером, каталог из `{API_URL}/api/catalog/export` (если API недоступен — сборка падает,
   чтобы не выложить устаревшую статику), `sitemap.xml`, `robots.txt`, `.htaccess` под окружение; FTP в корень домена.
4. **Штат**: отдельная сборка `/staff`, `.htaccess` со своим CSP, FTP отдельным аккаунтом.
5. **Smoke** (`scripts/smoke.mjs`): `/api/health`, главная, товар, `robots.txt`, `sitemap.xml`, настоящий 404,
   404 на `/admin` `/courier` `/seller` основного домена, 301 без слэша, вход на `штат` с `X-Robots-Tag: noindex`.
6. **IndexNow** (только production, `scripts/indexnow.mjs`): пути из payload админки или URL из sitemap с недавним `lastmod`.

## Пересборка из админки

Любое изменение каталога (товар, вариант, категория, зона доставки, отзыв, SEO страницы) вызывает событие
`CatalogChanged` → `StaticRebuild`:
- дебаунс **5 минут**: серия правок даёт одну сборку;
- **сразу**: смена цены, снятие с продажи, удаление, смена slug.

Сборка занимает ~3–5 минут. Расхождение статики и API в этот промежуток закрывается тремя механизмами:
живой запрос цены/остатка после гидратации (`useLiveProduct`), серверный пересчёт при оформлении
(клиентская цена не используется) и порог «сразу при смене цены».

## Откат

Каждый деплой сохраняет артефакты на 30 дней: `backend-release-<env>-<sha>`, `frontend-release-<env>-<run>`, `staff-release-<env>-<sha>`.

**Быстрый откат кода** (рекомендуется): `git revert` проблемного коммита в `main` → обычный деплой.

**Откат без нового кода** (срочно):
1. Actions → нужный прошлый успешный запуск Deploy → Artifacts → скачать `frontend-release-…` / `staff-release-…`.
2. Распаковать и залить FTP-клиентом в соответствующий каталог (или Re-run jobs у прошлого успешного запуска — он выложит свою версию).
3. Backend: `Re-run all jobs` у прошлого успешного запуска на нужном коммите. Миграции не откатываются автоматически:
   если релиз добавил миграцию, несовместимую с прошлым кодом, сначала выпустите миграцию-исправление вперёд.
4. После отката FTP-state-файлы (`.ftp-deploy-*.json`) на сервере описывают выложенную версию, следующий деплой будет инкрементальным от неё.
