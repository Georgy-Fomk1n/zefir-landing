import { useSyncExternalStore } from 'react';
import { getJson, postJson, setToken } from './api';
import { toast } from './toast';

// Состояние корзины одно на все острова страницы. Источник правды — сервер (сессия Laravel).
let state = { lines: [], count: 0, total: 0, card: { on: false, text: '' }, ready: false };
const subs = new Set();
let started = null;

const emit = () => { subs.forEach((f) => f()); };
function set(cart) {
    state = { ...cart, ready: true };
    try { localStorage.setItem('zp_count', String(cart.count)); } catch (_) { }
    document.dispatchEvent(new CustomEvent('cart:change', { detail: state }));
    emit();
}

export const subscribe = (f) => { subs.add(f); return () => subs.delete(f); };
export const getState = () => state;
export const useCart = () => useSyncExternalStore(subscribe, getState, getState);

export function load() {
    started ??= getJson('/api/cart').then((j) => { setToken(j.csrf); set(j.cart); }).catch(() => { started = null; });
    return started;
}

async function mutate(body, okMsg) {
    try {
        const j = await postJson('/api/cart', body);
        set(j.cart);
        if (okMsg) toast(okMsg);
        return true;
    } catch (e) {
        toast('Не получилось обновить корзину. Обновите страницу.');
        return false;
    }
}

export const add = (id, v, q, cardText, name) => mutate({ action: 'add', id, v: v ?? 0, q: q || 1, card_text: cardText || '' }, `${name || 'Товар'} в корзине`);
export const setQty = (id, v, q) => mutate({ action: 'set', id, v, q });
export const setCard = (on, text) => mutate({ action: 'card', on, text });
export const qtyOf = (s, id) => s.lines.filter((l) => l.id === id).reduce((n, l) => n + l.q, 0);
