#!/usr/bin/env bash
# Собирает готовый к загрузке на хостинг архив dist/zefir-shop.zip:
# PHP-зависимости без dev-пакетов, собранный фронтенд, без тестов, исходников картинок и node_modules.
# Нужны: PHP 8.2+, Composer, Node 20+, zip, tar.
#
# Обычная сборка (для GitHub Actions и SSH-хостингов):  bash scripts/release.sh
# Сборка «под ключ» для хостинга без SSH:
#   ADMIN_EMAIL=you@mail.ru ADMIN_PASSWORD='длинный пароль' bash scripts/release.sh --prepare https://zefiripryanik.ru
# В архив тогда попадут готовые .env (с ключами приложения и push) и база с каталогом и вашим админом.
# Такой архив содержит секреты: не выкладывайте его в открытый доступ.
set -euo pipefail
cd "$(dirname "$0")/.."

PREPARE=0; APP_URL_ARG=""
if [ "${1:-}" = "--prepare" ]; then PREPARE=1; APP_URL_ARG="${2:-}"; fi

STAGE=$(mktemp -d)
trap 'rm -rf "$STAGE"' EXIT

echo "→ копирую проект"
tar -cf - \
  --exclude='./.git' --exclude='./node_modules' --exclude='./vendor' --exclude='./dist' --exclude='./tests' \
  --exclude='./.env' --exclude='./storage/logs/*' --exclude='./storage/framework/cache/data/*' --exclude='./storage/framework/sessions/*' \
  --exclude='./storage/framework/views/*' --exclude='./database/*.sqlite*' --exclude='./public/hot' --exclude='./public/build' \
  --exclude='./public/uploads/*' --exclude='./resources/legacy-assets' --exclude='./resources/images-src' --exclude='./.github' \
  --exclude='./phpunit.xml' --exclude='./.DS_Store' . | tar -xf - -C "$STAGE"
mkdir -p "$STAGE/public/uploads"
touch "$STAGE/public/uploads/.gitkeep"

echo "→ composer install --no-dev"
( cd "$STAGE" && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist )

if [ "$PREPARE" = 1 ]; then
  [ -n "$APP_URL_ARG" ] || { echo "Укажите адрес сайта: --prepare https://example.ru"; exit 1; }
  echo "→ готовлю .env и базу"
  (
    cd "$STAGE"
    cp .env.example .env
    sed -i.bak -e 's#^APP_ENV=.*#APP_ENV=production#' -e 's#^APP_DEBUG=.*#APP_DEBUG=false#' -e "s#^APP_URL=.*#APP_URL=$APP_URL_ARG#" -e 's#^LOG_LEVEL=.*#LOG_LEVEL=warning#' .env && rm -f .env.bak
    php artisan key:generate --force --no-interaction >/dev/null
    php artisan shop:vapid >/dev/null
    : > database/database.sqlite
    php artisan migrate --force --seed --no-interaction >/dev/null
    if [ -n "${ADMIN_EMAIL:-}" ] && [ -n "${ADMIN_PASSWORD:-}" ]; then
      php artisan shop:admin "$ADMIN_EMAIL" --password="$ADMIN_PASSWORD" >/dev/null
    else
      echo "  (ADMIN_EMAIL/ADMIN_PASSWORD не заданы: админа создайте командой php artisan shop:admin)"
    fi
    rm -rf storage/framework/cache/data/* storage/logs/*.log
  )
fi

echo "→ сборка фронтенда"
npm ci --no-audit --no-fund
npm run build
cp -r public/build "$STAGE/public/build"

mkdir -p dist
rm -f dist/zefir-shop.zip
( cd "$STAGE" && zip -qr "$OLDPWD/dist/zefir-shop.zip" . -x '.DS_Store' )
echo "Готово: dist/zefir-shop.zip ($(du -h dist/zefir-shop.zip | cut -f1))"
