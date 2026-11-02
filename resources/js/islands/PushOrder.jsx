import { useEffect, useState } from 'react';
import { pushState, subscribePush } from '../lib/push';
import { AnimatePresence, Motion, m, spring } from '../lib/ui.jsx';

/** Страница «Заказ принят»: предложение получать статус заказа push-уведомлением */
export default function PushOrder({ token, vapid }) {
    const [st, setSt] = useState(null);
    const [busy, setBusy] = useState(false);
    useEffect(() => { pushState().then(setSt); }, []);

    if (!vapid || !st || st === 'unsupported') return null;

    const on = async () => {
        setBusy(true);
        try { const r = await subscribePush({ vapid, kind: 'customer', orderToken: token }); setSt(r === 'granted' ? 'subscribed' : r === 'denied' ? 'denied' : 'default'); }
        catch (_) { setSt('default'); }
        setBusy(false);
    };

    return (
        <Motion>
            <AnimatePresence mode="wait">
                <m.div key={st} className="pwa-card" role="status" initial={{ opacity: 0, y: 8 }} animate={{ opacity: 1, y: 0 }} exit={{ opacity: 0 }} transition={spring}>
                    {st === 'default' && (<><div className="t"><b>Сообщим, когда заказ будет готов</b>Один раз напишем, когда подтвердим и когда можно забирать. Без рекламы.</div><button className="btn btn-main btn-sm" type="button" onClick={on} disabled={busy}>{busy ? <span className="spin" aria-hidden="true" /> : null}Включить уведомления</button></>)}
                    {st === 'subscribed' && <div className="t"><b>Уведомления включены</b>Напишем, как только заказ подтвердят и соберут.</div>}
                    {st === 'denied' && <div className="t"><b>Уведомления выключены</b>Разрешите их для этого сайта в настройках браузера, если хотите получать статус заказа.</div>}
                    {st === 'ios-install' && <div className="t"><b>Уведомления на iPhone</b>Нажмите «Поделиться» в Safari, выберите «На экран “Домой”» и откройте сайт оттуда. После этого можно включить уведомления.</div>}
                </m.div>
            </AnimatePresence>
        </Motion>
    );
}
