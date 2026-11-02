import { useEffect } from 'react';
import { load, useCart } from '../lib/cart';

export default function CartCount({ el }) {
    const { count } = useCart();
    useEffect(() => { load(); }, []);
    useEffect(() => { el.hidden = !count; }, [count, el]);
    return count || null;
}
