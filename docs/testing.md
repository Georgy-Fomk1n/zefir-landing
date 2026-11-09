# Автотесты

Все уровни запускаются в CI на каждый pull request и перед каждым деплоем (`deploy.yml` вызывает `ci.yml`).
Красная проверка останавливает выкладку.

| Уровень | Инструмент | Что проверяет | Порог покрытия |
|---|---|---|---|
| Backend | Pest на MySQL 8.4 (в CI), pcov | API, конечные автоматы заказа и доставки, оплата и вебхуки Точки, слоты, лояльность и QR, абонементы, роли и 2FA, Web Push (шифрование, VAPID), деплой-хук | 90 % строк (`backend/scripts/coverage-check.php`) |
| Магазин + общий пакет | Vitest + Testing Library | страницы, корзина, оформление, кабинет, SEO-компоненты, скрипты postbuild, клиент API, push-логика и `PushToggle` | строки и выражения 90 %, функции 85 %, ветви 80 % |
| Штат | Vitest + Testing Library | вход и 2FA, админка, очередь продавца, сканер QR, доска доставок, курьер и офлайн-очередь | строки и выражения 85 %, функции 80 %, ветви 75 % |
| E2E | Playwright 1.63 | полные сценарии на настоящих сборках: пререндер, API (SQLite), служебное SPA | — |
| SEO и скорость | `seo:check`, Lighthouse CI | см. [lighthouse.md](lighthouse.md) | perf ≥ 98, SEO 100 |

## E2E (`e2e/`)

`npm run e2e` сам поднимает три сервера: Laravel (`php -S`, чистая SQLite-база с сидами), production-сборку
магазина с пререндером и сборку штата. Оплата — тестовый провайдер, письма — в память.

| Набор | Проекты | Сценарии |
|---|---|---|
| `public.spec.ts` | desktop, iphone-13 | HTML без JavaScript (контент, canonical, JSON-LD), настоящие 404, robots; гость: товар → корзина → самовывоз → оплата; регистрация → отказ банка → повторная оплата → QR лояльности |
| `courier.spec.ts` | desktop, iphone-13 | продавец ведёт заказ по очереди и назначает курьера; курьер принимает, теряет сеть, действие копится в очереди, после возврата сети уходит на сервер; доставка и закрытие смены |
| `iphone.spec.ts` | iphone-13, iphone-se | нет горизонтальной прокрутки на 10 страницах и на ширине 320 px, поля ввода от 16 px (Safari не зумит), зоны нажатия от 44 px, бургер-меню, мета-теги веб-приложения; push во вкладке Safari — инструкция «На экран Домой», на iOS 15 — сообщение о версии |
| `push.spec.ts` | desktop (Chromium) | включение уведомлений → подписка сохраняется на сервере → service worker показывает уведомление → выключение; запрет в браузере — подсказка |

В CI E2E идут в двух вариантах: Chromium (все проекты) и настоящий WebKit — движок Safari — для проектов iPhone
(`E2E_WEBKIT=1`). Локально без WebKit профили iPhone эмулируются в Chromium (размер экрана, плотность, UA, касания).

Push-сервис браузера (FCM) из CI недоступен, поэтому в `push.spec.ts` подписка создаётся заглушкой с настоящими
ключами P-256, а push-сообщение доставляется в service worker через Chrome DevTools Protocol. Шифрование и отправку
на сервере проверяют тесты Pest.

```bash
npm run e2e                               # всё
npm run e2e -- --project=iphone-se        # один проект
npm run e2e -- e2e/tests/courier.spec.ts  # один набор
E2E_WEBKIT=1 npm run e2e -- --project=iphone-13   # WebKit (нужен npx playwright install webkit)
```

При падении в CI отчёт и трассы лежат в артефакте `e2e-report-<движок>` (`npx playwright show-trace …`).

## Покрытие локально

```bash
cd backend && php -d pcov.enabled=1 vendor/bin/pest --coverage-clover build/clover.xml && php scripts/coverage-check.php
npm run test:coverage -w frontend
npm run test:coverage -w staff
```
