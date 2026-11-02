import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { load, setCard, setQty, useCart } from '../lib/cart';
import { rub } from '../lib/format';
import { AnimatePresence, Close, Minus, Motion, Plus, m, softSpring, spring } from '../lib/ui.jsx';

function Line({ l }) {
    return (
        <m.div className="ci" initial={{ opacity: 0, x: 24 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -32, height: 0, paddingBlock: 0 }} transition={softSpring}>
            <a className="ph" href={l.url}><picture><img src={l.image ? `/${l.image.base}-${l.image.widths[0]}.${l.image.ext || 'webp'}` : ''} alt="" loading="lazy" width="72" height="88" /></picture></a>
            <div className="m">
                <div className="r1"><b>{l.name}</b><span className="tn" style={{ fontWeight: 500, whiteSpace: 'nowrap' }}>{rub(l.sum)}</span></div>
                <div className="r2">
                    <div className="qty sm" role="group" aria-label="Количество">
                        <button type="button" aria-label="Меньше" onClick={() => setQty(l.id, l.v, l.q - 1)}><Minus /></button>
                        <output className="tn">{l.q}</output>
                        <button type="button" aria-label="Больше" onClick={() => setQty(l.id, l.v, l.q + 1)}><Plus /></button>
                    </div>
                    <span className="small tn">{rub(l.price)} за шт.</span>
                </div>
                <button className="rm" type="button" style={{ alignSelf: 'flex-start' }} onClick={() => setQty(l.id, l.v, 0)}>Убрать</button>
            </div>
        </m.div>
    );
}

function CardNote({ card }) {
    const [text, setText] = useState(card.text);
    const t = useRef();
    useEffect(() => setText(card.text), [card.text]);
    const onText = (v) => { setText(v); clearTimeout(t.current); t.current = setTimeout(() => setCard(true, v), 450); };
    return (
        <>
            <label className="opt" style={{ borderTop: '1px solid var(--line)' }}>
                <input type="checkbox" checked={card.on} onChange={(e) => setCard(e.target.checked, text)} />
                <span className="t">Открытка от руки</span><span className="small">Бесплатно</span>
            </label>
            <AnimatePresence initial={false}>
                {card.on && (
                    <m.div key="note" initial={{ height: 0, opacity: 0 }} animate={{ height: 'auto', opacity: 1 }} exit={{ height: 0, opacity: 0 }} style={{ overflow: 'hidden' }}>
                        <div className="field" style={{ padding: '4px 0 16px' }}>
                            <label htmlFor="cardText">Текст открытки</label>
                            <input className="inp hand-i" id="cardText" maxLength={90} value={text} onChange={(e) => onText(e.target.value)} />
                            <span className="hint">Напишем от руки, до 90 знаков</span>
                        </div>
                    </m.div>
                )}
            </AnimatePresence>
        </>
    );
}

export default function CartDrawer() {
    const cart = useCart();
    const [open, setOpen] = useState(() => !!window.__cartOpen);
    const asideRef = useRef(null);
    const last = useRef(null);

    useEffect(() => { load(); }, []);
    useEffect(() => {
        const on = () => { last.current = document.activeElement; setOpen(true); };
        window.addEventListener('cart:open', on);
        return () => window.removeEventListener('cart:open', on);
    }, []);

    const close = useCallback(() => { window.__cartOpen = false; setOpen(false); last.current?.focus?.({ preventScroll: true }); }, []);

    useEffect(() => {
        if (!open) return;
        const html = document.documentElement, prev = html.style.overflow;
        html.style.overflow = 'hidden';
        const key = (e) => {
            if (e.key === 'Escape') close();
            if (e.key === 'Tab' && asideRef.current) {   // фокус не уходит за шторку
                const f = [...asideRef.current.querySelectorAll('a[href],button,input')].filter((x) => !x.disabled && x.offsetParent !== null);
                if (!f.length) return;
                const first = f[0], lastEl = f[f.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); lastEl.focus(); }
                else if (!e.shiftKey && document.activeElement === lastEl) { e.preventDefault(); first.focus(); }
            }
        };
        window.addEventListener('keydown', key);
        return () => { html.style.overflow = prev; window.removeEventListener('keydown', key); };
    }, [open, close]);

    const bar = createPortal(
        <Motion>
            <AnimatePresence>
                {cart.count > 0 && !open && !/^\/(checkout|order|product)/.test(location.pathname) && (
                    <m.div className="mobile-cta glass" initial={{ y: 90, opacity: 0 }} animate={{ y: 0, opacity: 1 }} exit={{ y: 90, opacity: 0 }} transition={spring}>
                        <button className="btn btn-main" type="button" onClick={() => { last.current = document.activeElement; window.__cartOpen = true; setOpen(true); }}>
                            Корзина · {cart.count} · {rub(cart.total)}
                        </button>
                    </m.div>
                )}
            </AnimatePresence>
        </Motion>, document.body);

    return (
        <>
            {bar}
            {createPortal(
                <Motion>
                    <AnimatePresence>
                        {open && (
                            <>
                                <m.div key="scrim" className="scrim" onClick={close} initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }} transition={{ duration: 0.28 }} />
                                <m.aside key="drawer" ref={asideRef} className="drawer glass" role="dialog" aria-modal="true" aria-label="Корзина"
                                    initial={{ x: '112%' }} animate={{ x: 0 }} exit={{ x: '112%' }} transition={{ type: 'spring', stiffness: 360, damping: 38, mass: 1 }}>
                                    <div className="drawer-h">
                                        <h2>Корзина</h2>
                                        <button className="ib" type="button" onClick={close} aria-label="Закрыть" autoFocus><Close /></button>
                                    </div>
                                    <div className="drawer-b">
                                        {!cart.lines.length ? (
                                            <div className="empty-cart">
                                                <p>Пока пусто. Загляните в каталог: пирожные собираем утром в день выдачи.</p>
                                                <a className="btn btn-line btn-sm" href="/catalog" onClick={close}>Перейти в каталог</a>
                                            </div>
                                        ) : (
                                            <>
                                                <AnimatePresence initial={false}>{cart.lines.map((l) => <Line key={l.id + ':' + l.v} l={l} />)}</AnimatePresence>
                                                <CardNote card={cart.card} />
                                            </>
                                        )}
                                    </div>
                                    {cart.lines.length > 0 && (
                                        <div className="drawer-f">
                                            <div className="sum tot"><span>Итого</span><span>{rub(cart.total)}</span></div>
                                            <a className="btn btn-main btn-block" href="/checkout">Оформить заказ</a>
                                            <span className="small" style={{ textAlign: 'center' }}>Стоимость доставки покажем на следующем шаге</span>
                                        </div>
                                    )}
                                </m.aside>
                            </>
                        )}
                    </AnimatePresence>
                </Motion>, document.body)}
        </>
    );
}
