import { useEffect, useMemo, useState } from 'react';
import { add, load } from '../lib/cart';
import { fly } from '../lib/fly';
import { rub } from '../lib/format';
import { Motion, Qty, m, spring } from '../lib/ui.jsx';

/** Панель покупки на странице товара: формат, открытка, количество */
export default function ProductBuy({ id, name, variants, cake }) {
    const [vid, setVid] = useState(variants[0].id);
    const [qty, setQty] = useState(1);
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    useEffect(() => { load(); }, []);
    const v = useMemo(() => variants.find((x) => x.id === vid) || variants[0], [vid, variants]);

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
                <div className="field" style={{ flex: 'none' }}>
                    <label htmlFor="note">Текст открытки</label>
                    <input className="inp hand-i" id="note" maxLength={90} placeholder="Необязательно" value={note} onChange={(e) => setNote(e.target.value)} />
                    <span className="hint">Напишем от руки, до 90 знаков</span>
                </div>
                <div className="buyrow">
                    <Qty size="lg" value={qty} onChange={(n) => setQty(Math.max(1, Math.min(20, n)))} />
                    <m.button className="btn btn-main" type="button" data-primary onClick={onAdd} disabled={busy} whileTap={{ scale: 0.97 }} transition={spring}>
                        {busy ? <span className="spin" aria-hidden="true" /> : null}
                        В корзину · <span className="tn">{rub(v.price * qty)}</span>
                    </m.button>
                </div>
            </div>
        </Motion>
    );
}
