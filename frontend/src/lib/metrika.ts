import { config, indexable } from '../config';

declare global {
  interface Window {
    ym?: ((id: number, method: string, ...args: unknown[]) => void) & { a?: unknown[]; l?: number };
  }
}

let loaded = false;
const CONSENT_KEY = 'zp_cookie_consent';

export function hasConsent(): boolean {
  try {
    return window.localStorage.getItem(CONSENT_KEY) === 'yes';
  } catch {
    return false;
  }
}

export function setConsent(value: boolean) {
  try {
    window.localStorage.setItem(CONSENT_KEY, value ? 'yes' : 'no');
  } catch {
    // без хранилища согласие действует до перезагрузки
  }
  if (value) scheduleMetrika();
}

/**
 * Отложенная загрузка Метрики: после согласия и первого взаимодействия или через 4 с простоя.
 * Счётчик не блокирует рендер и не влияет на LCP. Только production-сборка основного домена.
 */
export function scheduleMetrika() {
  if (loaded || !config.metrikaId || !indexable || !hasConsent()) return;
  const start = () => {
    if (loaded) return;
    loaded = true;
    ['pointerdown', 'keydown', 'scroll'].forEach((e) => window.removeEventListener(e, start));
    const id = Number(config.metrikaId);
    const ym = function (...args: unknown[]) {
      (ym.a = ym.a || []).push(args);
    } as NonNullable<Window['ym']>;
    ym.l = Date.now();
    window.ym = ym;
    const s = document.createElement('script');
    s.async = true;
    s.src = 'https://mc.yandex.ru/metrika/tag.js';
    document.head.appendChild(s);
    window.ym(id, 'init', { clickmap: true, trackLinks: true, accurateTrackBounce: true, webvisor: true });
  };
  ['pointerdown', 'keydown', 'scroll'].forEach((e) => window.addEventListener(e, start, { once: true, passive: true }));
  window.setTimeout(start, 4000);
}

/** Цель Метрики. Список целей — docs/seo.md, раздел «Аналитика». */
export function reachGoal(goal: string, params?: Record<string, unknown>) {
  if (!config.metrikaId || typeof window === 'undefined' || !window.ym) return;
  window.ym(Number(config.metrikaId), 'reachGoal', goal, params);
}
