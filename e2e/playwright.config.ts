import { defineConfig, devices } from '@playwright/test';
import { API, backendEnv, ROOT, SITE, STAFF } from './env';

// Полный путь пользователя на настоящих сборках: пререндер магазина, служебное SPA, Laravel API (SQLite).
// iPhone: в CI — настоящий WebKit (E2E_WEBKIT=1), локально — Chromium с профилем iPhone (вьюпорт, DPR, UA, touch).
const iphone = (name: keyof typeof devices) => (process.env.E2E_WEBKIT ? devices[name] : { ...devices[name], browserName: 'chromium' as const, defaultBrowserType: 'chromium' as const });

export default defineConfig({
  testDir: './tests',
  outputDir: './test-results',
  fullyParallel: false,
  workers: 1,
  retries: process.env.CI ? 1 : 0,
  timeout: 60_000,
  expect: { timeout: 10_000 },
  reporter: process.env.CI ? [['github'], ['html', { outputFolder: 'report', open: 'never' }]] : [['list']],
  use: {
    baseURL: SITE,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    locale: 'ru-RU',
    timezoneId: 'Europe/Moscow',
  },
  projects: [
    // channel 'chromium' — новый headless: в нём работают разрешения на уведомления
    { name: 'desktop', use: { ...devices['Desktop Chrome'], channel: 'chromium' }, testIgnore: /iphone\.spec\.ts/ },
    { name: 'iphone-13', use: iphone('iPhone 13'), testMatch: /(iphone|public|courier)\.spec\.ts/ },
    { name: 'iphone-se', use: iphone('iPhone SE'), testMatch: /iphone\.spec\.ts/ },
  ],
  webServer: [
    {
      // Сначала чистая база (каталог, тестовые сотрудники, демо-зона, слоты), затем php -S с несколькими воркерами
      command: `rm -f ${backendEnv.DB_DATABASE} && touch ${backendEnv.DB_DATABASE} && php artisan migrate:fresh --seed --force -q && cd public && php -S 127.0.0.1:8000 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
      cwd: `${ROOT}/backend`,
      url: `${API}/api/health`,
      env: backendEnv,
      reuseExistingServer: false,
      timeout: 60_000,
    },
    {
      command: 'npm run build -w frontend && node frontend/scripts/serve-dist.mjs',
      cwd: ROOT,
      url: `${SITE}/`,
      env: { VITE_API_URL: API, VITE_APP_ENV: 'production', PORT: '4173' },
      reuseExistingServer: false,
      timeout: 240_000,
    },
    {
      command: 'npm run build && npx vite preview --port 5174 --strictPort',
      cwd: `${ROOT}/staff`,
      url: `${STAFF}/login`,
      env: { VITE_API_URL: API },
      reuseExistingServer: false,
      timeout: 240_000,
    },
  ],
});
