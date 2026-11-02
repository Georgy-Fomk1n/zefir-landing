import { useEffect, useRef } from 'react';
import { add, load, qtyOf, setQty, useCart } from '../lib/cart';
import { fly } from '../lib/fly';
import { Minus, Motion, Plus, m, spring } from '../lib/ui.jsx';

/** Кнопка под карточкой: «В корзину» превращается в счётчик */
export default function Buy({ id, name }) {
    const cart = useCart();
    const ref = useRef(null);
    useEffect(() => { load(); }, []);
    const q = qtyOf(cart, id);
    const line = cart.lines.find((l) => l.id === id);

    const onAdd = async (e) => {
        fly(e.currentTarget);
        await add(id, 0, 1, '', name);
    };

    return (
        <Motion>
            <div ref={ref} style={{ display: 'contents' }}>
                {!q ? (
                    <m.button key="add" className="btn btn-main btn-sm" type="button" data-primary onClick={onAdd}
                        initial={{ scale: 0.92, opacity: 0 }} animate={{ scale: 1, opacity: 1 }} transition={spring} whileTap={{ scale: 0.96 }}>
                        В корзину
                    </m.button>
                ) : (
                    <m.div key="qty" style={{ display: 'contents' }} initial={{ opacity: 0, scale: 0.92 }} animate={{ opacity: 1, scale: 1 }} transition={spring}>
                        <div className="qty pq" role="group" aria-label={`${name}: количество в корзине`}>
                            <button type="button" aria-label="Меньше" onClick={() => line && setQty(id, line.v, line.q - 1)}><Minus /></button>
                            <output className="tn" aria-live="polite">{q}</output>
                            <button type="button" aria-label="Больше" onClick={(e) => { fly(e.currentTarget); line && setQty(id, line.v, line.q + 1); }}><Plus /></button>
                        </div>
                        <span className="small">в корзине</span>
                    </m.div>
                )}
            </div>
        </Motion>
    );
}
