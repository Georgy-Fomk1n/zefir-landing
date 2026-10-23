// Единый источник NAP-данных и ссылок бренда. Значения перенесены дословно из legacy/index.html.
// Домен в разметке (canonical, og:url, schema.org, sitemap) — кириллица без www, по решению владельца.
// В технических конфигах (CORS, .env, Actions, SSL) используется punycode: SITE_HOST_PUNYCODE.

export const SITE_HOST = 'зефир-и-пряник.рф';
export const SITE_HOST_PUNYCODE = 'xn-----mlchgbbqzthe9b5k.xn--p1ai';
export const SITE_URL = `https://${SITE_HOST}`;
export const LEGACY_HOST = 'zefiripryanik.ru';

export const brand = {
  name: 'Зефир и пряник',
  tagline: 'Корпусные пирожные ручной работы в Москве',
  phone: '+79684791520',
  phoneDisplay: '+7 (968) 479-15-20',
  address: {
    street: 'ул. Базовская, 17',
    locality: 'Москва',
    country: 'RU',
    display: 'ул. Базовская, 17, Москва',
  },
  geo: { lat: 55.880223, lng: 37.51184 },
  // Пн — выходной, Вт–Вс 10:00–21:00
  hours: {
    closedDays: [1],
    opens: '10:00',
    closes: '21:00',
    schemaDays: ['Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
    lines: [
      { label: 'Пн', value: 'выходной' },
      { label: 'Вт-Вс', value: '10:00 – 21:00' },
    ],
  },
  legal: {
    entity: 'ИНДИВИДУАЛЬНЫЙ ПРЕДПРИНИМАТЕЛЬ ГУБАНОВА ТАТЬЯНА СЕРГЕЕВНА',
    ogrnip: '323774600785438',
    ogrnipDate: '28.11.2023',
    ogrnipDateUrl: 'https://zachestnyibiznes.ru/day/2023-11-28',
    inn: '772442265466',
  },
  founder: 'Красольда Тихоновна',
  yandexOrgId: '69698350171',
} as const;

export type ChannelId = 'flowwow' | 'telegram' | 'vk' | 'max' | 'yandex-maps';

export interface Channel {
  id: ChannelId;
  title: string;
  url: string;
  /** Имя цели Яндекс Метрики при клике */
  goal: string;
}

// Существующие каналы заказа — равноправные кнопки рядом с корзиной.
export const channels: Channel[] = [
  { id: 'flowwow', title: 'Flowwow', url: 'https://flowwow.com/shop/zefir-i-pryanik', goal: 'click_flowwow' },
  { id: 'telegram', title: 'Telegram', url: 'https://t.me/Krasolda_Tihonovna', goal: 'click_telegram' },
  { id: 'vk', title: 'ВКонтакте', url: 'https://vk.ru/zefir_i_pryanik.moscow', goal: 'click_vk' },
  { id: 'max', title: 'МАКС', url: 'https://max.ru/id772442265466_biz', goal: 'click_max' },
  { id: 'yandex-maps', title: 'Яндекс Карты', url: 'https://yandex.ru/maps/org/zefir_i_pryanik/69698350171/', goal: 'click_yandex_maps' },
];

export const yandex = {
  reviewsWidget: 'https://yandex.ru/maps-reviews-widget/69698350171?comments',
  mapWidget: 'https://yandex.ru/map-widget/v1/?ll=37.511840%2C55.880223&mode=search&oid=69698350171&ol=biz&z=16.62',
  orgUrl: 'https://yandex.ru/maps/org/zefir_i_pryanik/69698350171/',
  // [УТОЧНИТЬ] прямая ссылка «оставить отзыв» в карточке Яндекс Бизнеса
  reviewUrl: 'https://yandex.ru/maps/org/zefir_i_pryanik/69698350171/reviews/',
};

/** Абсолютный URL на основном домене. URL() не используем: он переводит IDN-хост в punycode. */
export function absoluteUrl(path: string): string {
  const p = path.startsWith('/') ? path : `/${path}`;
  return `${SITE_URL}${p}`;
}

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

/** Открыто ли в момент date по московскому времени (не по часовому поясу браузера). */
export function isOpenAt(date: Date): boolean {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Europe/Moscow', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23',
  }).formatToParts(date);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '';
  const day = WEEKDAYS.indexOf(get('weekday'));
  if ((brand.hours.closedDays as readonly number[]).includes(day)) return false;
  const [oh, om] = brand.hours.opens.split(':').map(Number);
  const [ch, cm] = brand.hours.closes.split(':').map(Number);
  const t = Number(get('hour')) * 60 + Number(get('minute'));
  return t >= oh * 60 + om && t < ch * 60 + cm;
}
