# Оплата: Точка Банк

Реализация — `app/Services/Payments/TochkaPaymentProvider.php` за интерфейсом `PaymentProvider`.
Для разработки — `FakePaymentProvider` (страница «Оплатить / Отклонить», подписанный URL, вебхук с HMAC).

## Что известно из документации Точки

Документация разработчика: https://developers.tochka.com/docs/tochka-api/ (в среде разработки домен был
недоступен, данные ниже собраны по страницам документации и changelog из поиска — **сверить перед запуском**).

- База API: `https://enter.tochka.com/uapi`, авторизация JWT-ключом из интернет-банка (права на эквайринг), нужны `customerCode` и `merchantId`.
- Платёжные ссылки: `POST /acquiring/v1.0/payments`, с чеком — `POST /acquiring/v1.0/payments_with_receipt`,
  статус — `GET /acquiring/v1.0/payments/{operationId}`, возврат — `.../refund` (только для статуса APPROVED).
- **Подписки (рекурренты)**: есть раздел `/acquiring/v1.0/subscriptions` (создание, `charge`, статус); оплата подписки — только картой.
- Вебхук `acquiringInternetPayment`; с 2026 г. в нём есть `paymentType` (sbp / card / digitalRuble), маскированный PAN и токен карты;
  у каждой ссылки/подписки должен быть уникальный `paymentLinkId` (передаём `payments.idempotency_key`).

## Режимы продления абонементов

- `TOCHKA_RECURRING=false` (по умолчанию) — **продление по ссылке**: за 3 дня до конца периода клиент получает
  ссылку на оплату (почта/Telegram/SMS), после конца периода — льготный период `SUBSCRIPTION_GRACE_DAYS`, затем expired.
- `TOCHKA_RECURRING=true` — после проверки договора: карта сохраняется при первой оплате, списание за 1 день,
  повторы через 6/24/48 часов, затем льготный период.

## Чек-лист перед `PAYMENT_PROVIDER=tochka`

- [ ] Договор интернет-эквайринга, `merchantId`, JWT-ключ с правами на эквайринг.
- [ ] Сверить имена полей запросов/ответов в `TochkaPaymentProvider` с актуальной OpenAPI-спецификацией (метод `create`, `fetchStatus`, `refund`, `chargeRecurring`).
- [ ] Получить публичный ключ для проверки JWT вебхуков → `TOCHKA_WEBHOOK_PUBLIC_KEY`.
- [ ] Указать URL вебхука в кабинете Точки: `https://api.xn-----mlchgbbqzthe9b5k.xn--p1ai/api/webhooks/payments`.
- [ ] Проверить на staging: оплата, отказ, возврат, повторный вебхук (идемпотентность), сверка `payments:reconcile`.
- [ ] Решить, поддерживаются ли рекурренты по договору → `TOCHKA_RECURRING`.
- [ ] Фискализация 54-ФЗ [УТОЧНИТЬ схему]: `FISCALIZATION=tochka_receipt` (чек через `payments_with_receipt`, СНО `FISCAL_TAX_SYSTEM`) или внешняя онлайн-касса — точка расширения в `TochkaPaymentProvider::create()` (параметр `$items`).

## Идемпотентность и сверка

- Каждый платёж — запись `payments` с уникальным `idempotency_key`.
- Вебхук: событие пишется в `webhook_events` (unique provider+event_id) до обработки; повтор — no-op.
- Финальный статус не перезаписывается (кроме paid → refunded). Оплата, пришедшая после отмены заказа по таймауту резерва, автоматически возвращается.
- `payments:reconcile` раз в 10 минут запрашивает статус зависших платежей (страховка от потерянных вебхуков).
