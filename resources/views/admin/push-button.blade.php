{{-- Кнопка «Уведомления о новых заказах» в шапке админки. Обычный JS: Filament не знает про наш React. --}}
<div x-data="ownerPush()" x-init="init()" x-show="state !== 'unsupported'" style="margin-inline-end: .5rem">
    <button type="button" x-on:click="toggle()" x-bind:disabled="busy" class="fi-btn fi-btn-size-sm"
        style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .7rem;border-radius:.5rem;font-size:.8rem;border:1px solid rgba(120,120,120,.35);cursor:pointer">
        <span x-text="state === 'subscribed' ? '🔔 Уведомления включены' : (state === 'ios-install' ? '🔔 Как включить на iPhone' : (state === 'denied' ? '🔕 Запрещены в браузере' : '🔔 Включить уведомления'))"></span>
    </button>
    <p x-show="msg" x-text="msg" style="font-size:.75rem;max-width:18rem;margin-top:.25rem;opacity:.8"></p>
</div>
<script>
    function ownerPush() {
        const VAPID = @json(config('shop.vapid.public'));
        const b64 = (s) => { const p = '='.repeat((4 - s.length % 4) % 4); const r = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from(r, (c) => c.charCodeAt(0)); };
        const ios = /iphone|ipad|ipod/i.test(navigator.userAgent), standalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
        return {
            state: 'unsupported', busy: false, msg: '',
            async init() {
                if (!VAPID) return;
                if (!('serviceWorker' in navigator) || !('PushManager' in window)) { if (ios && !standalone) this.state = 'ios-install'; return; }
                if (Notification.permission === 'denied') { this.state = 'denied'; return; }
                try { const reg = await navigator.serviceWorker.ready; this.state = (await reg.pushManager.getSubscription()) && Notification.permission === 'granted' ? 'subscribed' : 'default'; } catch (e) { this.state = 'default'; }
            },
            async toggle() {
                if (this.state === 'ios-install') { this.msg = 'В Safari нажмите «Поделиться» → «На экран “Домой”», откройте админку с иконки и включите уведомления.'; return; }
                if (this.state === 'denied') { this.msg = 'Разрешите уведомления для этого сайта в настройках браузера.'; return; }
                this.busy = true; this.msg = '';
                try {
                    const reg = await navigator.serviceWorker.ready;
                    let sub = await reg.pushManager.getSubscription();
                    if (this.state === 'subscribed' && sub) {
                        await fetch('/api/push/unsubscribe', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ endpoint: sub.endpoint }) });
                        await sub.unsubscribe(); this.state = 'default'; this.busy = false; return;
                    }
                    const perm = await Notification.requestPermission();
                    if (perm !== 'granted') { this.state = perm === 'denied' ? 'denied' : 'default'; this.busy = false; return; }
                    sub = sub || await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: b64(VAPID) });
                    const enc = (PushManager.supportedContentEncodings || []).includes('aes128gcm') ? 'aes128gcm' : 'aesgcm';
                    const r = await fetch('/api/push/subscribe', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': @json(csrf_token()) }, body: JSON.stringify({ kind: 'owner', subscription: Object.assign(sub.toJSON(), { contentEncoding: enc }) }) });
                    if (!r.ok) throw new Error('http ' + r.status);
                    this.state = 'subscribed';
                } catch (e) { this.msg = 'Не получилось включить уведомления. Попробуйте ещё раз.'; }
                this.busy = false;
            }
        };
    }
</script>
