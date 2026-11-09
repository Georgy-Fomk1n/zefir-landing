// Публичные настройки сборки (VITE_*). Секретов здесь быть не может: всё попадает в бандл.
const env = import.meta.env;

export const config = {
  /** production | staging | development */
  appEnv: (env.VITE_APP_ENV as string | undefined) ?? 'development',
  apiUrl: (env.VITE_API_URL as string | undefined) ?? 'http://localhost:8000',
  metrikaId: (env.VITE_METRIKA_ID as string | undefined) ?? '',
  yandexVerification: (env.VITE_YANDEX_VERIFICATION as string | undefined) ?? '',
  googleVerification: (env.VITE_GOOGLE_VERIFICATION as string | undefined) ?? '',
  ga4Id: (env.VITE_GA4_ID as string | undefined) ?? '',
};

/** Индексируется только production; staging и dev получают noindex на всех страницах. */
export const indexable = config.appEnv === 'production';
