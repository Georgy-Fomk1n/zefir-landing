import { useEffect, useState } from 'react';
import { isIOS, isStandalone } from '../lib/push';

/** Установка на экран «Домой»: системный запрос на Android/desktop, подсказка на iPhone */
export default function Install() {
    const [bip, setBip] = useState(window.__bip || null);
    const [ios, setIos] = useState(false);
    const [show, setShow] = useState(false);
    const [installed, setInstalled] = useState(false);

    useEffect(() => {
        setInstalled(isStandalone());
        setIos(isIOS());
        const on = (e) => setBip(e.detail || window.__bip);
        const done = () => { setInstalled(true); setBip(null); };
        window.addEventListener('bip', on);
        window.addEventListener('appinstalled', done);
        return () => { window.removeEventListener('bip', on); window.removeEventListener('appinstalled', done); };
    }, []);

    if (installed || (!bip && !ios)) return null;

    const go = async () => {
        if (bip) { bip.prompt(); await bip.userChoice; setBip(null); window.__bip = null; }
        else setShow((s) => !s);
    };

    return (
        <div>
            <button className="link-btn" type="button" onClick={go} aria-expanded={bip ? undefined : show}>Установить как приложение</button>
            {show && !bip && <p className="small" style={{ marginTop: 8, maxWidth: 320 }}>В Safari нажмите «Поделиться», затем «На экран “Домой”». Сайт откроется как приложение, и сможет присылать уведомления о заказе.</p>}
        </div>
    );
}
