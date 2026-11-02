import { postJson } from './api';

const b64 = (s) => {
    const p = '='.repeat((4 - (s.length % 4)) % 4);
    const raw = atob((s + p).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from(raw, (c) => c.charCodeAt(0));
};

export const isStandalone = () => matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
export const isIOS = () => /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

/** unsupported | ios-install | denied | default | subscribed */
export async function pushState() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return isIOS() && !isStandalone() ? 'ios-install' : 'unsupported';
    if (Notification.permission === 'denied') return 'denied';
    try {
        const reg = await navigator.serviceWorker.ready;
        const sub = await reg.pushManager.getSubscription();
        if (sub && Notification.permission === 'granted') return 'subscribed';
    } catch (_) { }
    return 'default';
}

export async function subscribePush({ vapid, kind, orderToken }) {
    if (!vapid) throw new Error('no-vapid');
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') return perm;
    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(vapid) });
    const enc = (PushManager.supportedContentEncodings || []).includes('aes128gcm') ? 'aes128gcm' : 'aesgcm';
    await postJson('/api/push/subscribe', { kind, order_token: orderToken, subscription: { ...sub.toJSON(), contentEncoding: enc } });
    return 'granted';
}

export async function unsubscribePush() {
    const reg = await navigator.serviceWorker.ready;
    const sub = await reg.pushManager.getSubscription();
    if (sub) { await postJson('/api/push/unsubscribe', { endpoint: sub.endpoint }); await sub.unsubscribe(); }
}
