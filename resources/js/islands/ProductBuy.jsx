import { useEffect, useMemo, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { add, load } from '../lib/cart';
import { fly } from '../lib/fly';
import { rub } from '../lib/format';
import { AnimatePresence, Motion, Plus, Qty, m, softSpring, spring } from '../lib/ui.jsx';

/** Панель покупки на странице товара: формат, открытка по желанию, количество */
export default function ProductBuy({ id, name, variants, cake }) {
    const [vid, setVid] = useState(variants[0].id);
    const [qty, setQty] = useState(1);
    const [note, setNote] = useState('');
    const [noteOpen, setNoteOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [offscreen, setOffscreen] = useState(false);
    const row = useRef(null);
    useEffect(() => { load(); }, []);
    const v = useMemo(() => variants.find((x) => x.id === vid) || variants[0], [vid, variants]);

    // Нижняя панель покупки на телефоне появляется, когда основная кнопка ушла за экран
    useEffect(() => {
        const el = row.current; if (!el || !('IntersectionObserver' in window)) return;
        const io = new IntersectionObserver(([e]) => setOffscreen(!e.isIntersecting), { rootMargin: '0px 0px -8px 0px' });
        io.observe(el);
        return () => io.disconnect();
    }, []);

    const onAdd = async (e) => {
        fly(e.currentTarget);
        setBusy(true);
        await add(id, vid, qty, note.trim(), name);
        setBusy(false);
    };

    return (
        <Motion>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 28 }}>
                {variants.length > 1 && (
                    <fieldset className="opts">
                        <legend>Формат</legend>
                        {variants.map((x) => (
                            <label className="opt" key={x.id}>
                                <input type="radio" name="fmt" value={x.id} checked={x.id === vid} onChange={() => setVid(x.id)} />
                                <span className="t">{x.label}{x.sub ? <small>{x.sub}</small> : null}</span>
                                <span className="pr">{rub(x.price)}</span>
                            </label>
                        ))}
                    </fieldset>
                )}
                {cake && (
                    <div className="made"><svg className="i" aria-hidden="true" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5" /><path d="M12 7.5V12l3 2" /></svg>Соберём утром в день выдачи. Хранить 72 часа в холоде.</div>
                )}

                <div>
                    {!noteOpen ? (
                        <button className="note-toggle" type="button" onClick={() => setNoteOpen(true)}>
                            <span className="pl" aria-hidden="true"><Plus /></span>Открытка с подписью от руки, бесплатно
                        </button>
                    ) : (
                        <m.div className="field" style={{ flex: 'none' }} initial={{ opacity: 0, y: -6 }} animate={{ opacity: 1, y: 0 }} transition={softSpring}>
                            <label htmlFor="note">Текст открытки</label>
                            <input className="inp hand-i" id="note" maxLength={90} placeholder="Например: С днём рождения, Маша!" value={note} autoFocus onChange={(e) => setNote(e.target.value)} />
                            <span className="hint">Напишем от руки, до 90 знаков</span>
                        </m.div>
                    )}
                </div>

                <div className="buyrow" ref={row}>
                    <Qty size="lg" value={qty} onChange={(n) => setQty(Math.max(1, Math.min(20, n)))} />
                    <m.button className="btn btn-main" type="button" data-primary onClick={onAdd} disabled={busy} whileTap={{ scale: 0.97 }} transition={spring}>
                        {busy ? <span className="spin" aria-hidden="true" /> : null}
                        В корзину · <span className="tn">{rub(v.price * qty)}</span>
                    </m.button>
                </div>
            </div>

            {createPortal(
                <AnimatePresence>
                    {offscreen && (
                        <m.aside aria-label="Быстрая покупка" className="buybar glass" initial={{ y: 90 }} animate={{ y: 0 }} exit={{ y: 90 }} transition={spring}>
                            <div className="bb-t"><b>{rub(v.price * qty)}</b><small>{variants.length > 1 ? v.label : name}{qty > 1 ? ` × ${qty}` : ''}</small></div>
                            <button className="btn btn-main" type="button" onClick={onAdd} disabled={busy}>{busy ? <span className="spin" aria-hidden="true" /> : null}В корзину</button>
                        </m.aside>
                    )}
                </AnimatePresence>, document.body)}
        </Motion>
    );
}
