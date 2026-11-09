import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

// Окружение E2E: отдельная SQLite-база, тестовая оплата, кеш в памяти (без лимитов входа между тестами).
export const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
export const API = 'http://localhost:8000';
export const SITE = 'http://localhost:4173';
export const STAFF = 'http://localhost:5174';

export const backendEnv: Record<string, string> = {
  APP_ENV: 'local',
  APP_DEBUG: 'false',
  APP_URL: API,
  APP_KEY: 'base64:Nhcn4yqT1oTWtYI2pTq0yDBG/6nSUIOI2xgKDH4X0jQ=',
  DB_CONNECTION: 'sqlite',
  DB_DATABASE: join(ROOT, 'backend', 'database', 'e2e.sqlite'),
  CACHE_STORE: 'array',
  SESSION_DRIVER: 'array',
  QUEUE_CONNECTION: 'sync',
  MAIL_MAILER: 'array',
  LOG_CHANNEL: 'stderr',
  LOG_LEVEL: 'error',
  PAYMENT_PROVIDER: 'fake',
  GEO_PROVIDER: 'null',
  // Push-канал выключен: сервер не ходит к настоящим push-сервисам; подписку и показ проверяет push.spec
  NOTIFY_CHANNELS: 'mail',
  // Ключи VAPID только для E2E (сгенерированы php artisan push:vapid)
  VAPID_PUBLIC_KEY: 'BIis-otuX0DptAn1v0nPlzInkLwDI-oWYryE5tMxM7UExamphTaynygVxtWg-t_a3hoH3a8uPpklhItCUb3k2rQ',
  VAPID_PRIVATE_KEY: 'Pe7NYQvhkGcgGq6SZGBjp82J7oDqDAQXevxwt76U9Qo',
  PUBLIC_SITE_URL: SITE,
  FRONTEND_URL: SITE,
  STAFF_URL: STAFF,
  CORS_ALLOWED_ORIGINS: `${SITE},${STAFF}`,
  REBUILD_ENABLED: 'false',
  PHP_CLI_SERVER_WORKERS: '6',
};
