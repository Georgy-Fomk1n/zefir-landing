// Запросы к Laravel. Публичные страницы работают без сессии, поэтому CSRF-токен берём у API корзины.
let token = null;
let pending = null;

export async function getJson(url) {
    const r = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    if (!r.ok) throw new Error('http ' + r.status);
    return r.json();
}

export function csrf(force = false) {
    if (!force && token) return Promise.resolve(token);
    pending = pending && !force ? pending : getJson('/api/cart').then((j) => { token = j.csrf; pending = null; return j; });
    return pending.then(() => token);
}

export async function postJson(url, body, retry = true) {
    const t = await csrf();
    const r = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': t },
        body: JSON.stringify(body),
    });
    if (r.status === 419 && retry) { await csrf(true); return postJson(url, body, false); }
    const data = await r.json().catch(() => ({}));
    if (!r.ok) { const e = new Error('http ' + r.status); e.status = r.status; e.data = data; throw e; }
    return data;
}

export function setToken(t) { token = t; }
